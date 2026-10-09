<?php

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\Mailing;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds and sends the invitation mail. Links are absolute, so the worker needs DEFAULT_URI.
 */
final class InvitationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly Campaign $campaign,
        #[Autowire('%app.mail_from%')] private readonly string $from,
    ) {
    }

    public function send(Invitation $invitation, Mailing $mailing, bool $test = false): void
    {
        $this->mailer->send($this->build($invitation, $mailing, $test));
    }

    /** @return array<string, mixed> the template context, also used for the preview in the admin */
    public function context(Invitation $invitation, Mailing $mailing, bool $test = false): array
    {
        return [
            'paragraphs' => $mailing->paragraphsFor($invitation),
            'button_label' => $mailing->getButtonLabel(),
            'claim_url' => $test ? null : $this->urls->generate('claim', ['token' => $invitation->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            'unsubscribe_url' => $test ? null : $this->urls->generate('unsubscribe', ['token' => $invitation->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            'last_claim_day' => $this->campaign->lastClaimDay(),
            'test' => $test,
        ];
    }

    private function build(Invitation $invitation, Mailing $mailing, bool $test): TemplatedEmail
    {
        $context = $this->context($invitation, $mailing, $test);

        $email = (new TemplatedEmail())
            ->from(new Address($this->from, 'Yappa'))
            ->to(new Address($invitation->getEmail()))
            ->subject(($test ? '[TEST] ' : '').$mailing->getSubject())
            ->htmlTemplate('email/invitation.html.twig')
            ->textTemplate('email/invitation.txt.twig')
            ->context($context);

        if (null !== $context['unsubscribe_url']) {
            $email->getHeaders()
                ->addTextHeader('List-Unsubscribe', '<'.$context['unsubscribe_url'].'>')
                ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        return $email;
    }
}
