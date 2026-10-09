<?php

namespace App\Service;

use App\Entity\Invitation;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Reads a CSV with two columns, company and e-mail address, in any order and separated
 * by ; or ,. A header row is skipped. Known and invalid addresses are left out.
 */
final class InvitationImporter
{
    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** @return array{added: int, duplicate: int, invalid: list<int>} invalid holds line numbers */
    public function import(string $path): array
    {
        $handle = fopen($path, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Het bestand kon niet gelezen worden.');
        }

        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        $invalid = [];
        $line = 0;
        while (false !== ($row = fgetcsv($handle, null, $delimiter, '"', '')) && $line < self::MAX_ROWS) {
            ++$line;
            $row = array_map(static fn ($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $v)), $row);
            if ([] === array_filter($row)) {
                continue;
            }
            [$company, $email] = $this->companyAndEmail($row);
            if (1 === $line && null === $email) {
                continue; // header
            }
            if (null === $email || '' === $company || \count($this->validator->validate($email, new Email())) > 0) {
                $invalid[] = $line;
                continue;
            }
            $rows[mb_strtolower($email)] ??= $company;
        }
        fclose($handle);

        $existing = array_flip($this->invitations->existingEmails(array_keys($rows)));
        $added = 0;
        foreach ($rows as $email => $company) {
            if (isset($existing[$email])) {
                continue;
            }
            $this->em->persist(new Invitation($company, $email));
            ++$added;
        }
        $this->em->flush();

        return ['added' => $added, 'duplicate' => \count($existing), 'invalid' => $invalid];
    }

    /** @param list<string> $row @return array{string, ?string} */
    private function companyAndEmail(array $row): array
    {
        foreach ($row as $i => $value) {
            if (str_contains($value, '@')) {
                $others = $row;
                unset($others[$i]);

                return [(string) (array_values(array_filter($others))[0] ?? ''), $value];
            }
        }

        return [$row[0] ?? '', null];
    }
}
