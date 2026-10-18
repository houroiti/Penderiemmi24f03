<?php

namespace App\DataFixtures;

use App\Entity\Item;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    // demo password for every fixture account (dev only)
    private const PASSWORD = 'password';

    private const CLOTHES = ['Pull', 'Veste', 'Jean', 'Chemise', 'Robe', 'Manteau', 'Écharpe', 'Sweat'];
    // invariable colours: no gender agreement needed (« un pull kaki », « une robe kaki »)
    private const COLORS = ['bleu marine', 'beige', 'rouge', 'kaki', 'bordeaux', 'marron', 'orange'];
    private const PERSONAL = ['Montre de famille', 'Bague de fiançailles', 'Appareil photo argentique', 'Collier en or'];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        // same seed = same data on every load
        $faker->seed(1234);

        $camille = $this->createUser($manager, 'camille@penderie.test', 'Camille');
        $sophie = $this->createUser($manager, 'sophie@penderie.test', 'Sophie');
        // will become Camille's follower once FOLLOWS exists; for now a plain user without items
        $this->createUser($manager, 'leo@penderie.test', 'Léo');

        $this->createItems($manager, $faker, $camille, 8, 2);
        $this->createItems($manager, $faker, $sophie, 6, 1);

        $manager->flush();
    }

    private function createUser(ObjectManager $manager, string $email, string $pseudo): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));

        $manager->persist($user);

        return $user;
    }

    private function createItems(ObjectManager $manager, Generator $faker, User $owner, int $count, int $personalCount): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $item = (new Item())
                ->setName($this->randomItemName($faker))
                ->setOwner($owner);
            $manager->persist($item);
        }

        foreach ($faker->randomElements(self::PERSONAL, $personalCount) as $name) {
            $item = (new Item())
                ->setName($name)
                ->setOwner($owner)
                ->setIsPersonal(true);
            $manager->persist($item);
        }
    }

    private function randomItemName(Generator $faker): string
    {
        return match ($faker->numberBetween(1, 3)) {
            1 => $faker->randomElement(self::CLOTHES).' '.$faker->randomElement(self::COLORS),
            2 => 'Vinyle · '.$faker->name(),
            default => 'Livre · '.$faker->name(),
        };
    }
}