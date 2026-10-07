<?php

namespace App\Entity;

use App\Repository\ScoreRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One finished run. The name is picked by the server from a fixed list,
 * so the scoreboard holds no personal data and needs no filter.
 */
#[ORM\Entity(repositoryClass: ScoreRepository::class)]
#[ORM\Table(name: 'app_scores')]
#[ORM\Index(columns: ['client_id', 'points'])]
class Score
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\Column(length: 60)]
    private string $playerName;

    #[ORM\Column]
    private int $points;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Client $client, string $playerName, int $points)
    {
        $this->client = $client;
        $this->playerName = $playerName;
        $this->points = $points;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
