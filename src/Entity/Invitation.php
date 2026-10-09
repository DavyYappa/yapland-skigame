<?php

namespace App\Entity;

use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\ByteString;

/**
 * One client contact who may claim one game. Only invited contacts can make a game,
 * so a forwarded link can never produce more than this one game.
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\Table(name: 'app_invitations')]
class Invitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $company;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    /** 24 random characters: the claim link and the unsubscribe link. */
    #[ORM\Column(length: 24, unique: true)]
    private string $token;

    #[ORM\Column(length: 16, enumType: InvitationStatus::class)]
    private InvitationStatus $status = InvitationStatus::New;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $client = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $claimedAt = null;

    public function __construct(string $company, string $email)
    {
        $this->company = trim($company);
        $this->email = mb_strtolower(trim($email));
        $this->token = ByteString::fromRandom(24, 'abcdefghijkmnpqrstuvwxyz23456789')->toString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getStatus(): InvitationStatus
    {
        return $this->status;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getClaimedAt(): ?\DateTimeImmutable
    {
        return $this->claimedAt;
    }

    public function canBeMailed(): bool
    {
        return InvitationStatus::New === $this->status;
    }

    public function canBeClaimed(): bool
    {
        return \in_array($this->status, [InvitationStatus::New, InvitationStatus::Queued, InvitationStatus::Sent], true);
    }

    public function queue(): void
    {
        $this->status = InvitationStatus::Queued;
    }

    public function markSent(): void
    {
        if (InvitationStatus::Queued === $this->status) {
            $this->status = InvitationStatus::Sent;
        }
        $this->sentAt = new \DateTimeImmutable();
    }

    public function claim(Client $client): void
    {
        $this->client = $client;
        $this->status = InvitationStatus::Claimed;
        $this->claimedAt = new \DateTimeImmutable();
    }

    public function revoke(): void
    {
        $this->status = InvitationStatus::Revoked;
    }

    public function unsubscribe(): void
    {
        if (InvitationStatus::Claimed !== $this->status) {
            $this->status = InvitationStatus::Unsubscribed;
        }
    }
}
