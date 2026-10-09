<?php

namespace App\Message;

/**
 * Sends one invitation mail. Handled by the async transport, so a mailing of a few hundred
 * contacts goes out one by one from the cron worker instead of in one web request.
 */
final readonly class SendInvitation
{
    public function __construct(public int $invitationId)
    {
    }
}
