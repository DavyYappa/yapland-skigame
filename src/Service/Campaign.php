<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The campaign runs until 31 January 2027 included. After that every game is
 * offline and the scores are deleted (app:scores:purge).
 */
final class Campaign
{
    private \DateTimeImmutable $endsAt;

    public function __construct(
        #[Autowire('%app.campaign_ends_at%')] string $endsAt,
    ) {
        $this->endsAt = new \DateTimeImmutable($endsAt);
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
