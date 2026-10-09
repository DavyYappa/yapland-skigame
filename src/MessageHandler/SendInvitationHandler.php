<?php

namespace App\MessageHandler;

use App\Entity\InvitationStatus;
use App\Message\SendInvitation;
use App\Repository\InvitationRepository;
use App\Repository\MailingRepository;
use App\Service\InvitationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendInvitationHandler
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly MailingRepository $mailings,
        private readonly InvitationMailer $mailer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SendInvitation $message): void
    {
        $invitation = $this->invitations->find($message->invitationId);

        // Revoked, unsubscribed or claimed while it waited in the queue: send nothing
        if (null === $invitation || InvitationStatus::Queued !== $invitation->getStatus()) {
            return;
        }

        $this->mailer->send($invitation, $this->mailings->current());
        $invitation->markSent();
        $this->em->flush();
    }
}
