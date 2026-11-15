<?php

namespace App\DataFixtures;

use App\DataFixtures\Faker\WardrobeProvider;
use App\Entity\Follow;
use App\Entity\Furniture;
use App\Entity\Home;
use App\Entity\Item;
use App\Entity\Loan;
use App\Entity\Room;
use App\Entity\User;
use App\Enum\ItemCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo data set, reloaded with "make fixtures": the same data every time (fixed seed),
 * around a thousand items so the back-office KPIs and lists look real.
 */
class AppFixtures extends Fixture
{
    // demo password for every fixture account (dev only)
    private const PASSWORD = 'password';
    private const OTHER_USERS = 40;

    private const PERSONAL = ['Montre de famille', 'Bague de fiançailles', 'Appareil photo argentique', 'Collier en or', 'Lettres de ma grand-mère'];
    private const HOMES = [
        'Appartement' => ['Chambre' => ['Armoire', 'Commode'], 'Dressing' => ['Penderie', 'Étagère à chaussures'], 'Salon' => ['Bibliothèque', 'Meuble à vinyles']],
        'Maison de famille' => ['Grenier' => ['Malle', 'Cartons'], 'Chambre d\'amis' => ['Armoire']],
    ];

    private Generator $faker;
    private WardrobeProvider $wardrobe;
    private string $hashedPassword = '';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->faker = Factory::create('fr_FR');
        $this->wardrobe = new WardrobeProvider($this->faker);
        $this->faker->addProvider($this->wardrobe);
        // same seed = same data on every load
        $this->faker->seed(1234);

        $this->createUser($manager, 'admin@penderie.test', 'Admin', ['ROLE_SUPER_ADMIN']);
        $camille = $this->createUser($manager, 'camille@penderie.test', 'Camille');
        $sophie = $this->createUser($manager, 'sophie@penderie.test', 'Sophie');
        $leo = $this->createUser($manager, 'leo@penderie.test', 'Léo');
        $lea = $this->createUser($manager, 'lea@penderie.test', 'Léa', ['ROLE_CHILD'])->setParent($camille);

        // the client and her circle: Sophie is a close friend, Léo a simple follower
        $this->follow($manager, $sophie, $camille, true);
        $this->follow($manager, $camille, $sophie, true);
        $this->follow($manager, $leo, $camille, false);

        $camilleItems = $this->createWardrobe($manager, $camille, 320, true);
        $this->createWardrobe($manager, $sophie, 140, false);
        $this->createWardrobe($manager, $leo, 60, false);
        $this->createWardrobe($manager, $lea, 30, false);

        $this->createLoans($manager, $camilleItems);

        // other members, signed up over the last months, to make the admin KPIs meaningful
        for ($i = 0; $i < self::OTHER_USERS; ++$i) {
            $user = $this->createUser($manager, $this->faker->unique()->userName().'@penderie.test', $this->faker->firstName());
            $this->setCreatedAt($user, $this->faker->dateTimeBetween('-6 months', '-8 days'));
            $this->createWardrobe($manager, $user, $this->faker->numberBetween(5, 15), false);
            if ($this->faker->boolean(60)) {
                $this->follow($manager, $user, $camille, false);
            }
        }
        // a few recent sign-ups for the "last 7 days" KPI
        $this->setCreatedAt($leo, new \DateTimeImmutable('-2 days'));

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(ObjectManager $manager, string $email, string $pseudo, array $roles = []): User
    {
        $user = (new User())->setEmail($email)->setPseudo($pseudo)->setRoles($roles);

        // hashing is slow on purpose: hash once and reuse it for every demo account
        if ('' === $this->hashedPassword) {
            $this->hashedPassword = $this->passwordHasher->hashPassword($user, self::PASSWORD);
        }
        $user->setPassword($this->hashedPassword);

        $manager->persist($user);

        return $user;
    }

    /**
     * Homes, rooms, furniture and $count items. About 15% are not put away yet, 5% are personal.
     *
     * @return list<Item>
     */
    private function createWardrobe(ObjectManager $manager, User $owner, int $count, bool $allHomes): array
    {
        $furniture = [];
        foreach (self::HOMES as $homeName => $rooms) {
            $home = (new Home())->setName($homeName)->setOwner($owner);
            $manager->persist($home);

            foreach ($rooms as $roomName => $furnitureNames) {
                $room = (new Room())->setName($roomName)->setHome($home)->setIsSharedWithCloseFriends('Dressing' === $roomName);
                $manager->persist($room);

                foreach ($furnitureNames as $furnitureName) {
                    $piece = (new Furniture())->setName($furnitureName)->setRoom($room);
                    $manager->persist($piece);
                    $furniture[] = $piece;
                }
            }

            if (!$allHomes) {
                break;
            }
        }

        $items = [];
        for ($i = 0; $i < $count; ++$i) {
            $item = $this->randomItem()->setOwner($owner);
            $item->setFurniture($this->faker->boolean(85) ? $this->faker->randomElement($furniture) : null);
            $item->setIsVisibleToFollowers($this->faker->boolean(25));
            $manager->persist($item);
            $items[] = $item;
        }

        if ($count >= 30) {
            foreach ($this->faker->randomElements(self::PERSONAL, 2) as $name) {
                $personal = (new Item())->setName($name)->setOwner($owner)->setIsPersonal(true)->setFurniture($furniture[0]);
                $manager->persist($personal);
            }
        }

        return $items;
    }

    private function randomItem(): Item
    {
        $item = new Item();
        $category = $this->faker->randomElement([ItemCategory::Clothing, ItemCategory::Clothing, ItemCategory::Clothing, ItemCategory::Book, ItemCategory::Vinyl]);
        $item->setCategory($category);

        return match ($category) {
            ItemCategory::Clothing => (function () use ($item): Item {
                ['name' => $name, 'color' => $color, 'brand' => $brand] = $this->wardrobe->clothing();

                return $item->setName($name)->setColor($color)->setBrand($brand);
            })(),
            ItemCategory::Book => $item->setName($this->wardrobe->bookTitle()),
            default => $item->setName('Vinyle · '.$this->wardrobe->vinylArtist()),
        };
    }

    /**
     * Camille's loans: 2 overdue, 2 in progress, 2 returned.
     *
     * @param list<Item> $items
     */
    private function createLoans(ObjectManager $manager, array $items): void
    {
        $loans = [
            ['Sophie', '-20 days', '-5 days', null],
            ['Léo', '-30 days', '-10 days', null],
            ['Sophie', '-3 days', '+10 days', null],
            ['Maman', '-1 day', '+30 days', null],
            ['Léo', '-60 days', '-40 days', '-41 days'],
            ['Sophie', '-15 days', '-8 days', '-9 days'],
        ];

        foreach ($loans as $i => [$borrower, $lentAt, $dueDate, $returnedAt]) {
            $loan = (new Loan())
                ->setItem($items[$i])
                ->setBorrowerName($borrower)
                ->setLentAt(new \DateTimeImmutable($lentAt))
                ->setDueDate(new \DateTimeImmutable($dueDate))
                ->setReturnedAt(null === $returnedAt ? null : new \DateTimeImmutable($returnedAt));
            $manager->persist($loan);
        }
    }

    private function follow(ObjectManager $manager, User $follower, User $followed, bool $closeFriend): void
    {
        $manager->persist((new Follow())->setFollower($follower)->setFollowed($followed)->setIsCloseFriend($closeFriend));
    }

    /**
     * The creation date is set by the entity and has no setter: in the demo data only, we backdate it.
     */
    private function setCreatedAt(User $user, \DateTimeInterface $date): void
    {
        (new \ReflectionProperty(User::class, 'createdAt'))->setValue($user, \DateTimeImmutable::createFromInterface($date));
    }
}
