<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Repository\InvitationRepository;
use App\Repository\ScoreRepository;
use App\Service\Campaign;
use App\Service\LogoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/clients')]
class ClientController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LogoStorage $logos,
    ) {
    }

    #[Route('', name: 'admin_clients', methods: ['GET'])]
    public function index(ClientRepository $clients, ScoreRepository $scores, Campaign $campaign): Response
    {
        $list = $clients->findAllNewestFirst();

        return $this->render('admin/client/index.html.twig', [
            'clients' => $list,
            'score_counts' => array_combine(
                array_map(static fn (Client $c) => $c->getId(), $list),
                array_map(static fn (Client $c) => $scores->countFor($c), $list),
            ),
            'campaign' => $campaign,
        ]);
    }

    #[Route('/new', name: 'admin_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeLogo($form, $client);
            $this->em->persist($client);
            $this->em->flush();
            $this->addFlash('success', 'Spel aangemaakt. Bekijk het voorbeeld en stuur dan de handtekening door.');

            return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
        }

        return $this->render('admin/client/form.html.twig', ['form' => $form, 'client' => null]);
    }

    #[Route('/{id}', name: 'admin_client_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(#[MapEntity] Client $client, ScoreRepository $scores, InvitationRepository $invitations): Response
    {
        return $this->render('admin/client/show.html.twig', [
            'client' => $client,
            'top' => $scores->top($client),
            'invitation' => $invitations->findOneBy(['client' => $client]),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_client_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(#[MapEntity] Client $client, Request $request): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeLogo($form, $client);
            $this->em->flush();
            $this->addFlash('success', 'Opgeslagen.');

            return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
        }

        return $this->render('admin/client/form.html.twig', ['form' => $form, 'client' => $client]);
    }

    #[Route('/{id}/toggle', name: 'admin_client_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(#[MapEntity] Client $client, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle'.$client->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $client->setActive(!$client->isActive());
        $this->em->flush();
        $this->addFlash('success', $client->isActive() ? 'Het spel staat online.' : 'Het spel staat offline.');

        return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
    }

    #[Route('/{id}/preview', name: 'admin_client_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function preview(#[MapEntity] Client $client): Response
    {
        // Same page as the client sees, but scores are not saved
        return $this->render('ski/play.html.twig', [
            'client' => $client,
            'player_name' => 'Voorbeeld',
            'score_url' => null,
            'top' => [],
            'preview' => true,
        ]);
    }

    private function storeLogo(FormInterface $form, Client $client): void
    {
        $file = $form->get('logo')->getData();
        if ($file instanceof UploadedFile) {
            $this->logos->remove($client->getLogoFilename());
            $client->setLogoFilename($this->logos->store($file));
        }
    }
}
