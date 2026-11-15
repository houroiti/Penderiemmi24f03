<?php

namespace App\Tests\Functional\BackOffice;

use App\Entity\Furniture;
use App\Entity\Home;
use App\Entity\Item;
use App\Entity\Loan;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The super admin pilots and moderates, but never changes a user's things nor who can see them.
 */
final class AdminReadOnlyContentTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    /** @var array<string, int> crud path => id of a row belonging to Camille */
    private array $ids = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $camille = $this->persist((new User())->setEmail('camille@example.com')->setPseudo('Camille')->setPassword('not-a-real-hash'));
        $home = $this->persist((new Home())->setName('Appartement')->setOwner($camille));
        $room = $this->persist((new Room())->setName('Chambre')->setHome($home));
        $furniture = $this->persist((new Furniture())->setName('Armoire')->setRoom($room));
        $item = $this->persist((new Item())->setName('Bague')->setOwner($camille)->setIsPersonal(true)->setFurniture($furniture));
        $loan = $this->persist((new Loan())->setItem($item)->setBorrowerName('Sophie'));
        $this->entityManager->flush();

        $this->ids = [
            'home' => (int) $home->getId(),
            'room' => (int) $room->getId(),
            'furniture' => (int) $furniture->getId(),
            'item' => (int) $item->getId(),
            'loan' => (int) $loan->getId(),
        ];

        $admin = $this->persist((new User())->setEmail('admin@example.com')->setPseudo('Admin')->setPassword('not-a-real-hash')->setRoles(['ROLE_SUPER_ADMIN']));
        $this->entityManager->flush();
        $this->client->loginUser($admin);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contentProvider(): iterable
    {
        foreach (['home', 'room', 'furniture', 'item', 'loan'] as $crud) {
            yield $crud => [$crud];
        }
    }

    #[DataProvider('contentProvider')]
    public function testAdminCanListAndSeeTheDetail(string $crud): void
    {
        $this->client->request('GET', "/fr/admin/{$crud}");
        self::assertResponseIsSuccessful();

        $this->client->request('GET', "/fr/admin/{$crud}/{$this->ids[$crud]}");
        self::assertResponseIsSuccessful();
    }

    #[DataProvider('contentProvider')]
    public function testAdminCannotCreateOrEdit(string $crud): void
    {
        $this->client->request('GET', "/fr/admin/{$crud}/new");
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', "/fr/admin/{$crud}/{$this->ids[$crud]}/edit");
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Places and loans cannot be deleted by the admin: nothing there grants an access, nothing to moderate.
     */
    public function testAdminCannotDeletePlacesNorLoans(): void
    {
        foreach (['home', 'room', 'furniture', 'loan'] as $crud) {
            $this->client->request('POST', "/fr/admin/{$crud}/{$this->ids[$crud]}/delete");
            self::assertResponseStatusCodeSame(403, $crud);
        }
    }

    /**
     * An item can still be deleted by the admin: moderation of inappropriate content.
     */
    public function testAdminCanStillDeleteAnItemForModeration(): void
    {
        $this->client->request('GET', '/fr/admin/item');

        self::assertSelectorExists('[data-action-name="delete"], .action-delete');
    }

    public function testUserEditFormOnlyLetsTheAdminChangeTheRoles(): void
    {
        $camille = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'camille@example.com']);
        self::assertNotNull($camille);

        $crawler = $this->client->request('GET', '/fr/admin/user/'.$camille->getId().'/edit');
        self::assertResponseIsSuccessful();

        foreach (['User[pseudo]', 'User[email]', 'User[parent]'] as $name) {
            self::assertSame('disabled', $crawler->filter("[name=\"{$name}\"]")->attr('disabled'), $name);
        }
        self::assertNull($crawler->filter('[name="User[roles][]"]')->first()->attr('disabled'));
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $this->entityManager->persist($entity);

        return $entity;
    }
}
