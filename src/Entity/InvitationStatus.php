<?php

namespace App\Entity;

enum InvitationStatus: string
{
    case New = 'new';
    case Queued = 'queued';
    case Sent = 'sent';
    case Claimed = 'claimed';
    case Revoked = 'revoked';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nieuw',
            self::Queued => 'In de wachtrij',
            self::Sent => 'Verstuurd',
            self::Claimed => 'Geclaimd',
            self::Revoked => 'Ingetrokken',
            self::Unsubscribed => 'Afgemeld',
        };
    }
}
