<?php

namespace App\Tests\Functional\BackOffice;

use App\Entity\Item;
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
    private User $admin;
    private User $camille;
    private Item $item;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->camille = $this->createUser('camille@example.com', 'Camille');
        $this->item = (new Item())->setName('Bague')->setOwner($this->camille)->setIsPersonal(true);
        $this->entityManager->persist($this->item);
        $this->admin = $this->createUser('admin@example.com', 'Admin', ['ROLE_SUPER_ADMIN']);
        $this->entityManager->flush();

        $this->client->loginUser($this->admin);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function privateScreenProvider(): iterable
    {
        foreach (['home', 'room', 'furniture', 'loan'] as $crud) {
            yield $crud => [$crud];
        }
    }

    /**
     * How people organise their home and to whom they lend is private: no such screen in the admin.
     */
    #[DataProvider('privateScreenProvider')]
    public function testPrivateScreensDoNotExistInTheAdmin(string $crud): void
    {
        $this->client->request('GET', "/fr/admin/{$crud}");

        self::assertResponseStatusCodeSame(404);
    }

    public function testDashboardShowsTheShareOfItemsPutAway(): void
    {
        $this->client->request('GET', '/fr/admin');

        self::assertSelectorTextContains('[data-cy="admin-kpis"]', 'Objets rangés dans un meuble');
    }

    public function testAdminCanSeeAnItemAndDeleteItForModeration(): void
    {
        $this->client->request('GET', '/fr/admin/item/'.$this->item->getId());
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/fr/admin/item');
        self::assertSelectorExists('[data-action-name="delete"], .action-delete');
    }

    public function testAdminCannotCreateOrEditAnItem(): void
    {
        $this->client->request('GET', '/fr/admin/item/new');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/fr/admin/item/'.$this->item->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUserEditFormOnlyLetsTheAdminChangeTheAdminRight(): void
    {
        $crawler = $this->client->request('GET', '/fr/admin/user/'.$this->camille->getId().'/edit');
        self::assertResponseIsSuccessful();

        foreach (['User[pseudo]', 'User[email]', 'User[parent]'] as $name) {
            self::assertNotNull($crawler->filter("[name=\"{$name}\"]")->attr('disabled'), $name);
        }
        // no free list of roles any more (ROLE_CHILD cannot be added or removed here)
        self::assertCount(0, $crawler->filter('[name="User[roles][]"]'));
        self::assertNull($crawler->filter('[name="User[superAdmin]"]')->attr('disabled'));
    }

    public function testAdminCanGiveTheAdminRight(): void
    {
        $this->submitUserForm($this->camille, true);

        self::assertTrue($this->reload($this->camille)->isSuperAdmin());
    }

    public function testChildStatusIsKeptWhenTheAdminSavesTheAccount(): void
    {
        $lea = $this->createUser('lea@example.com', 'Léa', ['ROLE_CHILD'])->setParent($this->camille);
        $this->entityManager->flush();

        $this->submitUserForm($lea, false);

        $lea = $this->reload($lea);
        self::assertTrue($lea->isChild());
        self::assertSame($this->camille->getId(), $lea->getParent()?->getId());
    }

    public function testAdminCannotRemoveTheirOwnAdminRight(): void
    {
        $crawler = $this->client->request('GET', '/fr/admin/user/'.$this->admin->getId().'/edit');
        // the attribute is present (its value may be empty): the box is greyed out
        self::assertNotNull($crawler->filter('[name="User[superAdmin]"]')->attr('disabled'));

        $this->client->submit($crawler->filter('form[name="User"]')->form());

        self::assertTrue($this->reload($this->admin)->isSuperAdmin());
    }

    public function testAdminCannotDeleteTheirOwnAccount(): void
    {
        $this->client->request('GET', '/fr/admin/user/'.$this->admin->getId());
        self::assertSelectorNotExists('[data-action-name="delete"], .action-delete');

        $this->client->request('GET', '/fr/admin/user/'.$this->camille->getId());
        self::assertSelectorExists('[data-action-name="delete"], .action-delete');
    }

    private function submitUserForm(User $user, bool $superAdmin): void
    {
        $crawler = $this->client->request('GET', '/fr/admin/user/'.$user->getId().'/edit');
        $form = $crawler->filter('form[name="User"]')->form();
        $superAdmin ? $form['User[superAdmin]']->tick() : $form['User[superAdmin]']->untick();
        $this->client->submit($form);

        self::assertResponseRedirects();
    }

    private function reload(User $user): User
    {
        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, string $pseudo, array $roles = []): User
    {
        $user = (new User())->setEmail($email)->setPseudo($pseudo)->setPassword('not-a-real-hash')->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
