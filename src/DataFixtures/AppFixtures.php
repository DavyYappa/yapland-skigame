<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Score;
use App\Entity\User;
use App\Service\PlayerNames;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Local demo data only: a dev account and two example clients with scores.
 */
class AppFixtures extends Fixture
{
    public const DEV_EMAIL = 'dev@yappa.be';
    public const DEV_PASSWORD = 'skikerst-lokaal';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly PlayerNames $names,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User(self::DEV_EMAIL);
        $user->setPassword($this->hasher->hashPassword($user, self::DEV_PASSWORD));
        $manager->persist($user);

        $examples = [
            ['Voorbeeld Bakkerij', '#8C2F1B', '#2F6444', '#F2C14E', 'Warme feestdagen en een knapperig 2027, van ons hele team.', true],
            ['Voorbeeld Logistiek', '#1D408E', '#A3262A', '#F6BA93', 'Wij zijn even op de latten. Vanaf 5 januari staan we weer voor je klaar.', false],
        ];

        foreach ($examples as [$name, $primary, $secondary, $accent, $message, $active]) {
            $client = new Client();
            $client->setName($name);
            $client->setPrimaryColor($primary);
            $client->setSecondaryColor($secondary);
            $client->setAccentColor($accent);
            $client->setMessage($message);
            $client->setActive($active);
            $manager->persist($client);

            for ($i = 0; $i < 6; ++$i) {
                $manager->persist(new Score($client, $this->names->random(), random_int(80, 900)));
            }
        }

        $manager->flush();
    }
}
