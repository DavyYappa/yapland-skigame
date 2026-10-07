<?php

namespace App\Command;

use App\Repository\ScoreRepository;
use App\Service\Campaign;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Run daily from cron; it only deletes once the campaign is over.
 */
#[AsCommand(name: 'app:scores:purge', description: 'Delete all scores once the campaign has ended')]
final class PurgeScoresCommand
{
    public function __construct(
        private readonly ScoreRepository $scores,
        private readonly Campaign $campaign,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if (!$this->campaign->isOver()) {
            $io->note(\sprintf('De campagne loopt nog tot %s. Er is niets gewist.', $this->campaign->endsAt()->format('d/m/Y H:i')));

            return Command::SUCCESS;
        }

        $io->success(\sprintf('%d scores gewist.', $this->scores->deleteAll()));

        return Command::SUCCESS;
    }
}
