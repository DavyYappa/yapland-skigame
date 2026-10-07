<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\ByteString;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A client's version of the ski game. The token is the only thing in its URL,
 * so nobody can read from a link which clients Yappa works with.
 */
#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'app_clients')]
class Client
{
    public const MESSAGE_MAX = 140;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 16, unique: true)]
    private string $token;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $logoFilename = null;

    #[ORM\Column(length: 7)]
    #[Assert\NotBlank]
    #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG)]
    private string $primaryColor = '#1D408E';

    #[ORM\Column(length: 7)]
    #[Assert\NotBlank]
    #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG)]
    private string $secondaryColor = '#2F6444';

    #[ORM\Column(length: 7)]
    #[Assert\NotBlank]
    #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG)]
    private string $accentColor = '#F6BA93';

    #[ORM\Column(length: self::MESSAGE_MAX)]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::MESSAGE_MAX)]
    private string $message = '';

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->token = self::newToken();
        $this->createdAt = new \DateTimeImmutable();
    }

    /** 12 random lowercase letters and digits, about 62 bits: not guessable, easy to type. */
    public static function newToken(): string
    {
        return ByteString::fromRandom(12, 'abcdefghijkmnpqrstuvwxyz23456789')->toString();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getLogoFilename(): ?string
    {
        return $this->logoFilename;
    }

    public function setLogoFilename(?string $logoFilename): void
    {
        $this->logoFilename = $logoFilename;
    }

    public function getPrimaryColor(): string
    {
        return $this->primaryColor;
    }

    public function setPrimaryColor(string $color): void
    {
        $this->primaryColor = strtoupper($color);
    }

    /** Black or white, whichever reads best on the primary color (WCAG relative luminance). */
    public function getTextOnPrimary(): string
    {
        $channel = static function (string $hex): float {
            $c = hexdec($hex) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = str_split(ltrim($this->primaryColor, '#'), 2);
        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);

        return $luminance > 0.179 ? '#111111' : '#FFFFFF';
    }

    public function getSecondaryColor(): string
    {
        return $this->secondaryColor;
    }

    public function setSecondaryColor(string $color): void
    {
        $this->secondaryColor = strtoupper($color);
    }

    public function getAccentColor(): string
    {
        return $this->accentColor;
    }

    public function setAccentColor(string $color): void
    {
        $this->accentColor = strtoupper($color);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
