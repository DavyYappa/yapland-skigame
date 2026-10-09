<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Invitation;
use App\Entity\InvitationStatus;
use App\Form\ClaimType;
use App\Repository\InvitationRepository;
use App\Service\Campaign;
use App\Service\LogoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The page a client reaches from the invitation mail: set up the game, then copy the signature.
 * The same link keeps showing the result, and the client can change that one game until the
 * claim period ends. One invitation never makes a second game.
 */
class ClaimController extends AbstractController
{
    private const TOKEN = '[a-z0-9]{24}';

    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly Campaign $campaign,
    ) {
    }

    #[Route('/claim/{token}', name: 'claim', requirements: ['token' => self::TOKEN], methods: ['GET', 'POST'])]
    public function claim(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        LogoStorage $logos,
        #[Autowire(service: 'limiter.claim_submit')] RateLimiterFactoryInterface $claimLimiter,
    ): Response {
        $invitation = $this->findInvitation($token);

        if (InvitationStatus::Claimed === $invitation->getStatus() && null !== $invitation->getClient()) {
            return $this->noindex($this->render('claim/done.html.twig', [
                'invitation' => $invitation,
                'client' => $invitation->getClient(),
                'just_saved' => $request->query->getBoolean('klaar'),
                'can_edit' => !$this->campaign->claimIsOver(),
                'last_claim_day' => $this->campaign->lastClaimDay(),
            ]));
        }

        if ($response = $this->unavailable($invitation)) {
            return $response;
        }

        $client = new Client();
        $client->setName($invitation->getCompany());

        return $this->handleForm($request, $invitation, $client, false, $em, $logos, $claimLimiter);
    }

    /** The same game, changed by the client until the claim period ends. Never a second game. */
    #[Route('/claim/{token}/aanpassen', name: 'claim_edit', requirements: ['token' => self::TOKEN], methods: ['GET', 'POST'])]
    public function edit(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        LogoStorage $logos,
        #[Autowire(service: 'limiter.claim_submit')] RateLimiterFactoryInterface $claimLimiter,
    ): Response {
        $invitation = $this->findInvitation($token);
        $client = $invitation->getClient();

        if (InvitationStatus::Claimed !== $invitation->getStatus() || null === $client) {
            return $this->redirectToRoute('claim', ['token' => $token]);
        }
        if ($this->campaign->claimIsOver() || $this->campaign->isOver()) {
            return $this->noindex($this->render('claim/unavailable.html.twig', [
                'reason' => 'expired',
                'last_claim_day' => $this->campaign->lastClaimDay(),
            ], new Response('', Response::HTTP_GONE)));
        }

        return $this->handleForm($request, $invitation, $client, true, $em, $logos, $claimLimiter);
    }

    private function handleForm(
        Request $request,
        Invitation $invitation,
        Client $client,
        bool $editing,
        EntityManagerInterface $em,
        LogoStorage $logos,
        RateLimiterFactoryInterface $claimLimiter,
    ): Response {
        $token = $invitation->getToken();
        $form = $this->createForm(ClaimType::class, $client, ['editing' => $editing]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$claimLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                $this->addFlash('error', 'Te veel pogingen. Probeer het over een kwartier opnieuw.');

                return $this->redirect($request->getUri());
            }

            if ($form->isValid()) {
                $logo = $form->get('logo')->getData();
                if ($logo instanceof UploadedFile) {
                    // The old file stays: signatures pasted earlier still point to it
                    $client->setLogoFilename($logos->store($logo));
                }
                if (!$editing) {
                    $client->setActive(true);
                    $invitation->claim($client);
                    $em->persist($client);
                }
                $em->flush();

                return $this->redirectToRoute('claim', ['token' => $token, 'klaar' => 1]);
            }
        }

        return $this->noindex($this->render('claim/form.html.twig', [
            'invitation' => $invitation,
            'client' => $client,
            'form' => $form,
            'editing' => $editing,
            'last_claim_day' => $this->campaign->lastClaimDay(),
        ], new Response('', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK)));
    }

    private function unavailable(Invitation $invitation): ?Response
    {
        if (!$invitation->canBeClaimed() || $this->campaign->isOver()) {
            return $this->noindex($this->render('claim/unavailable.html.twig', ['reason' => 'gone'], new Response('', Response::HTTP_GONE)));
        }
        if ($this->campaign->claimIsOver()) {
            return $this->noindex($this->render('claim/unavailable.html.twig', [
                'reason' => 'expired',
                'last_claim_day' => $this->campaign->lastClaimDay(),
            ], new Response('', Response::HTTP_GONE)));
        }

        return null;
    }

    #[Route('/afmelden/{token}', name: 'unsubscribe', requirements: ['token' => self::TOKEN], methods: ['GET', 'POST'])]
    public function unsubscribe(string $token, Request $request, EntityManagerInterface $em): Response
    {
        $invitation = $this->findInvitation($token);

        // One-click unsubscribe from the mail client (List-Unsubscribe-Post) has no CSRF token
        $oneClick = 'List-Unsubscribe=One-Click' === $request->getContent() || $request->request->has('List-Unsubscribe');
        if ($request->isMethod('POST') && ($oneClick || $this->isCsrfTokenValid('unsubscribe'.$token, $request->request->getString('_token')))) {
            $invitation->unsubscribe();
            $em->flush();

            if ($oneClick) {
                return new Response('', Response::HTTP_OK);
            }
        }

        return $this->noindex($this->render('claim/unsubscribe.html.twig', [
            'invitation' => $invitation,
            'done' => InvitationStatus::Unsubscribed === $invitation->getStatus(),
        ]));
    }

    private function findInvitation(string $token): Invitation
    {
        return $this->invitations->findOneBy(['token' => $token]) ?? throw $this->createNotFoundException();
    }

    private function noindex(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
