<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Clients can claim a game until 1 December 2026 included. The games stay online until
 * 31 January 2027 included; after that every game is offline and the scores are deleted
 * (app:scores:purge).
 */
final class Campaign
{
    private \DateTimeImmutable $claimEndsAt;
    private \DateTimeImmutable $endsAt;

    public function __construct(
        #[Autowire('%app.claim_ends_at%')] string $claimEndsAt,
        #[Autowire('%app.campaign_ends_at%')] string $endsAt,
    ) {
        $this->claimEndsAt = new \DateTimeImmutable($claimEndsAt);
        $this->endsAt = new \DateTimeImmutable($endsAt);
    }

    /** The last day a client can claim a game (1 December 2026). */
    public function lastClaimDay(): \DateTimeImmutable
    {
        return $this->claimEndsAt->modify('-1 second');
    }

    public function claimIsOver(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable()) >= $this->claimEndsAt;
    }

    public function endsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    /** The last day the games are online (31 January 2027). */
    public function lastDay(): \DateTimeImmutable
    {
        return $this->endsAt->modify('-1 second');
    }

    public function isOver(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable()) >= $this->endsAt;
    }
}
