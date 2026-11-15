<?php

namespace App\Tests\Functional\BackOffice;

use App\Entity\Follow;
use App\Entity\Item;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminDashboardTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAnonymousIsRedirectedToTheLoginPageInTheSameLanguage(): void
    {
        $this->client->request('GET', '/ar/admin');

        self::assertResponseRedirects('/ar/login');
    }

    public function testASimpleUserIsForbidden(): void
    {
        $this->client->loginUser($this->createUser('camille@example.com', []));
        $this->client->request('GET', '/fr/admin');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param 'fr'|'en'|'ar' $locale
     */
    #[DataProvider('localeProvider')]
    public function testSuperAdminSeesTheKpisInEachLanguage(string $locale, string $title, string $direction): void
    {
        $this->client->loginUser($this->createUser('admin@example.com', ['ROLE_SUPER_ADMIN']));
        $crawler = $this->client->request('GET', "/{$locale}/admin");

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-cy="admin-kpis"]', $title);
        self::assertSame($direction, $crawler->filter('html')->attr('dir'));
        self::assertSame($locale, $crawler->filter('html')->attr('lang'));
        self::assertSelectorExists('.content-header h1');
        self::assertNotSame('', trim($crawler->filter('.content-header h1')->text()));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function localeProvider(): iterable
    {
        yield 'french' => ['fr', 'Utilisateurs', 'ltr'];
        yield 'english' => ['en', 'Users', 'ltr'];
        yield 'arabic, right to left' => ['ar', 'المستخدمون', 'rtl'];
    }

    public function testSuperAdminCanListUsersWithoutSeeingPasswords(): void
    {
        $this->client->loginUser($this->createUser('admin@example.com', ['ROLE_SUPER_ADMIN']));
        $this->client->request('GET', '/fr/admin/user');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'admin@example.com');
        self::assertSelectorTextNotContains('body', 'not-a-real-hash');
    }

    public function testSuperAdminCanDeleteAUserWhoOwnsItems(): void
    {
        $victor = $this->createUser('victor@example.com', []);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist((new Item())->setName('Pull')->setOwner($victor));
        $entityManager->flush();
        $victorId = $victor->getId();

        $this->client->loginUser($this->createUser('admin@example.com', ['ROLE_SUPER_ADMIN']));
        $crawler = $this->client->request('GET', '/fr/admin/user');
        // the delete form of EasyAdmin carries a CSRF token, read from the page like a browser does
        $token = $crawler->filter('input[name="token"]')->attr('value');

        $this->client->request('POST', "/fr/admin/user/{$victorId}/delete", ['token' => $token]);

        self::assertResponseRedirects();
        $entityManager->clear();
        self::assertNull($entityManager->getRepository(User::class)->find($victorId));
        self::assertSame(0, $entityManager->getRepository(Item::class)->count(['name' => 'Pull']));
    }

    public function testSuperAdminCanModerateButNeverGrantARelation(): void
    {
        $camille = $this->createUser('camille@example.com', []);
        $leo = $this->createUser('leo@example.com', []);
        $follow = (new Follow())->setFollower($leo)->setFollowed($camille);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($follow);
        $entityManager->flush();

        $this->client->loginUser($this->createUser('admin@example.com', ['ROLE_SUPER_ADMIN']));

        // seeing and deleting (moderation) is allowed
        $this->client->request('GET', '/fr/admin/follow');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-action-name="delete"], .action-delete');

        // creating or changing a relation would grant an access without consent: refused
        $this->client->request('GET', '/fr/admin/follow/new');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/fr/admin/follow/'.$follow->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, array $roles): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(strstr($email, '@', true) ?: $email)
            ->setPassword('not-a-real-hash')
            ->setRoles($roles);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
