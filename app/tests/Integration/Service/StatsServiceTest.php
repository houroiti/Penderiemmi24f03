<?php

namespace App\Tests\Integration\Service;

use App\Entity\Follow;
use App\Entity\Furniture;
use App\Entity\Home;
use App\Entity\Item;
use App\Entity\Loan;
use App\Entity\Room;
use App\Entity\User;
use App\Enum\ItemCategory;
use App\Repository\FollowRepository;
use App\Repository\HomeRepository;
use App\Repository\ItemRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use App\Service\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The KPIs are computed by real SQL queries: they are tested against the test database.
 */
final class StatsServiceTest extends KernelTestCase
{
    private const TODAY = '2026-11-15';

    private EntityManagerInterface $entityManager;
    private StatsService $stats;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $container = static::getContainer();
        // built by hand from the real repositories, so the test does not depend on how the service is wired
        $this->stats = new StatsService(
            $container->get(UserRepository::class),
            $container->get(ItemRepository::class),
            $container->get(LoanRepository::class),
            $container->get(FollowRepository::class),
            $container->get(HomeRepository::class),
        );
    }

    public function testGlobalStats(): void
    {
        [$camille] = $this->createDataSet();

        $stats = $this->stats->getGlobalStats(new \DateTimeImmutable(self::TODAY));

        self::assertSame(2, $stats['users']);
        self::assertSame(2, $stats['newUsersLast7Days']);
        self::assertSame(4, $stats['items']);
        self::assertSame(1, $stats['personalItems']);
        self::assertSame(25.0, $stats['personalRatio']);
        self::assertSame(2.0, $stats['itemsPerUser']);
        self::assertSame(2, $stats['activeLoans']);
        self::assertSame(1, $stats['overdueLoans']);
        self::assertSame(['clothing' => 3, 'book' => 1], $stats['itemsByCategory']);
        self::assertNotNull($camille->getId());
    }

    public function testUserStatsOnlyCountTheirOwnThings(): void
    {
        [$camille, $sophie] = $this->createDataSet();

        $stats = $this->stats->getUserStats($camille, new \DateTimeImmutable(self::TODAY));

        self::assertSame([
            'items' => 3,
            'personalItems' => 1,
            'unstoredItems' => 2,
            'homes' => 1,
            'activeLoans' => 2,
            'overdueLoans' => 1,
            'closeFriends' => 1,
            'followers' => 1,
        ], $stats);

        self::assertSame(1, $this->stats->getUserStats($sophie, new \DateTimeImmutable(self::TODAY))['items']);
    }

    public function testPercentageNeverDividesByZero(): void
    {
        self::assertSame(0.0, StatsService::percentage(0, 0));
        self::assertSame(33.3, StatsService::percentage(1, 3));
    }

    /**
     * Camille: 3 items (1 personal, 1 stored), 3 loans (1 overdue, 1 in progress, 1 returned), Sophie is her close friend.
     * Sophie: 1 book.
     *
     * @return array{User, User}
     */
    private function createDataSet(): array
    {
        $camille = $this->user('camille@example.com', 'Camille');
        $sophie = $this->user('sophie@example.com', 'Sophie');

        $home = (new Home())->setName('Appartement')->setOwner($camille);
        $room = (new Room())->setName('Chambre')->setHome($home);
        $wardrobe = (new Furniture())->setName('Armoire')->setRoom($room);

        $pull = $this->item('Pull', $camille, ItemCategory::Clothing)->setFurniture($wardrobe);
        $jean = $this->item('Jean', $camille, ItemCategory::Clothing);
        $this->item('Bague', $camille, ItemCategory::Clothing)->setIsPersonal(true);
        $this->item('Roman', $sophie, ItemCategory::Book);

        $this->loan($pull, '2026-11-01', '2026-11-10', null);          // overdue
        $this->loan($jean, '2026-11-12', '2026-11-30', null);          // in progress
        $this->loan($jean, '2026-10-01', '2026-10-10', '2026-10-09');  // returned

        $follow = (new Follow())->setFollower($sophie)->setFollowed($camille)->setIsCloseFriend(true);

        foreach ([$home, $room, $wardrobe, $follow] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        return [$camille, $sophie];
    }

    private function user(string $email, string $pseudo): User
    {
        $user = (new User())->setEmail($email)->setPseudo($pseudo)->setPassword('not-a-real-hash');
        $this->entityManager->persist($user);

        return $user;
    }

    private function item(string $name, User $owner, ItemCategory $category): Item
    {
        $item = (new Item())->setName($name)->setOwner($owner)->setCategory($category);
        $this->entityManager->persist($item);

        return $item;
    }

    private function loan(Item $item, string $lentAt, string $dueDate, ?string $returnedAt): void
    {
        $loan = (new Loan())
            ->setItem($item)
            ->setBorrowerName('Léo')
            ->setLentAt(new \DateTimeImmutable($lentAt))
            ->setDueDate(new \DateTimeImmutable($dueDate))
            ->setReturnedAt(null === $returnedAt ? null : new \DateTimeImmutable($returnedAt));
        $this->entityManager->persist($loan);
    }
}
