<?php

namespace App\Entity;

use App\Repository\MailingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The invitation mail, editable in the admin. There is only one.
 * {bedrijf} in the text is replaced by the invitation's company.
 */
#[ORM\Entity(repositoryClass: MailingRepository::class)]
#[ORM\Table(name: 'app_mailing')]
class Mailing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $subject = 'Jouw eigen kerstskigame, voor in je handtekening';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    private string $body = "Dag {bedrijf},\n\nDit jaar sturen we je geen kaartje, maar een spel. Zet je eigen kerstskigame op met jullie logo, kleuren en kerstwens. Je krijgt meteen een stukje handtekening voor je mails of je out-of-office, zodat je klanten en collega's tijdens de feestdagen kunnen skiën.\n\nHet kost je twee minuten.";

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    private string $buttonLabel = 'Maak jouw skigame';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function getButtonLabel(): string
    {
        return $this->buttonLabel;
    }

    public function setButtonLabel(string $buttonLabel): void
    {
        $this->buttonLabel = $buttonLabel;
    }

    /** @return list<string> paragraphs with {bedrijf} filled in */
    public function paragraphsFor(Invitation $invitation): array
    {
        $text = str_replace('{bedrijf}', $invitation->getCompany(), $this->body);

        return array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $text) ?: [])));
    }
}
