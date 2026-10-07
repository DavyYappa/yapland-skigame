<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Create or update a Yappa account for the admin')]
final class CreateUserCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Yappa e-mail address')] string $email): int
    {
        if (!str_ends_with(mb_strtolower($email), '@yappa.be')) {
            $io->error('Alleen @yappa.be-adressen krijgen toegang.');

            return Command::FAILURE;
        }

        $password = $io->askHidden('Wachtwoord (min. 12 tekens)');
        if (!\is_string($password) || mb_strlen($password) < 12) {
            $io->error('Het wachtwoord moet minstens 12 tekens hebben.');

            return Command::FAILURE;
        }

        $user = $this->users->findOneBy(['email' => mb_strtolower($email)]) ?? new User($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(\sprintf('%s kan inloggen op /login.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
