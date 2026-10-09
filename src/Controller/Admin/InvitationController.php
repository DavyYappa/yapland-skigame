<?php

namespace App\Controller\Admin;

use App\Entity\Invitation;
use App\Entity\InvitationStatus;
use App\Entity\User;
use App\Form\MailingType;
use App\Message\SendInvitation;
use App\Repository\InvitationRepository;
use App\Repository\MailingRepository;
use App\Service\Campaign;
use App\Service\InvitationImporter;
use App\Service\InvitationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/invitations')]
class InvitationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvitationRepository $invitations,
    ) {
    }

    #[Route('', name: 'admin_invitations', methods: ['GET'])]
    public function index(Campaign $campaign): Response
    {
        return $this->render('admin/invitation/index.html.twig', [
            'invitations' => $this->invitations->findAllNewestFirst(),
            'counts' => $this->invitations->countByStatus(),
            'unsubscribed' => $this->invitations->countUnsubscribed(),
            'to_mail' => \count($this->invitations->findToMail()),
            'campaign' => $campaign,
            'statuses' => InvitationStatus::cases(),
        ]);
    }

    #[Route('/import', name: 'admin_invitations_import', methods: ['POST'])]
    public function import(Request $request, InvitationImporter $importer): Response
    {
        $this->checkCsrf('invitations_import', $request);

        $file = $request->files->get('csv');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Kies een CSV-bestand.');

            return $this->redirectToRoute('admin_invitations');
        }
        if (!\in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true) || $file->getSize() > 1024 * 1024) {
            $this->addFlash('error', 'Kies een CSV-bestand van max. 1 MB.');

            return $this->redirectToRoute('admin_invitations');
        }

        $result = $importer->import($file->getPathname());
        $message = 1 === $result['added'] ? '1 uitnodiging toegevoegd.' : \sprintf('%d uitnodigingen toegevoegd.', $result['added']);
        if ($result['duplicate'] > 0) {
            $message .= 1 === $result['duplicate'] ? ' 1 adres stond er al in.' : \sprintf(' %d adressen stonden er al in.', $result['duplicate']);
        }
        if ($result['unsubscribed'] > 0) {
            $message .= 1 === $result['unsubscribed']
                ? ' 1 adres heeft zich afgemeld en krijgt geen mail.'
                : \sprintf(' %d adressen hebben zich afgemeld en krijgen geen mail.', $result['unsubscribed']);
        }
        if ([] !== $result['invalid']) {
            $message .= \sprintf(' Overgeslagen, geen geldig bedrijf of e-mailadres: lijn %s.', implode(', ', \array_slice($result['invalid'], 0, 20)));
        }
        $this->addFlash('success', $message);

        return $this->redirectToRoute('admin_invitations');
    }

    #[Route('/send', name: 'admin_invitations_send', methods: ['POST'])]
    public function send(Request $request, MessageBusInterface $bus): Response
    {
        $this->checkCsrf('invitations_send', $request);

        $toMail = $this->invitations->findToMail();
        foreach ($toMail as $invitation) {
            $invitation->queue();
        }
        $this->em->flush();
        foreach ($toMail as $invitation) {
            $bus->dispatch(new SendInvitation((int) $invitation->getId()));
        }

        $this->addFlash('success', \sprintf('%d mails staan in de wachtrij. Ze gaan de komende minuten de deur uit.', \count($toMail)));

        return $this->redirectToRoute('admin_invitations');
    }

    #[Route('/{id}/revoke', name: 'admin_invitation_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revoke(#[MapEntity] Invitation $invitation, Request $request): Response
    {
        $this->checkCsrf('revoke'.$invitation->getId(), $request);

        if (InvitationStatus::Claimed === $invitation->getStatus()) {
            $this->addFlash('error', 'Deze klant heeft al een spel. Haal het spel offline via de klant.');
        } else {
            $invitation->revoke();
            $this->em->flush();
            $this->addFlash('success', \sprintf('De uitnodiging van %s werkt niet meer.', $invitation->getCompany()));
        }

        return $this->redirectToRoute('admin_invitations');
    }

    #[Route('/mail', name: 'admin_mailing', methods: ['GET', 'POST'])]
    public function mailing(Request $request, MailingRepository $mailings, InvitationMailer $mailer): Response
    {
        $mailing = $mailings->current();
        $form = $this->createForm(MailingType::class, $mailing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Mail opgeslagen.');

            return $this->redirectToRoute('admin_mailing');
        }

        $example = new Invitation('Voorbeeld bv', 'voorbeeld@yappa.be');

        return $this->render('admin/invitation/mailing.html.twig', [
            'form' => $form,
            'preview' => $this->renderView('email/invitation.html.twig', $mailer->context($example, $mailing, true) + ['email' => ['subject' => $mailing->getSubject()]]),
        ]);
    }

    #[Route('/mail/test', name: 'admin_mailing_test', methods: ['POST'])]
    public function testMail(Request $request, MailingRepository $mailings, InvitationMailer $mailer): Response
    {
        $this->checkCsrf('mailing_test', $request);

        $user = $this->getUser();
        \assert($user instanceof User);
        $mailer->send(new Invitation('Voorbeeld bv', $user->getEmail()), $mailings->current(), true);
        $this->addFlash('success', \sprintf('Testmail verstuurd naar %s.', $user->getEmail()));

        return $this->redirectToRoute('admin_mailing');
    }

    private function checkCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
