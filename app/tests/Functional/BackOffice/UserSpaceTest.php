<?php

namespace App\Tests\Functional\BackOffice;

use App\Entity\Item;
use App\Entity\Loan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * "Voler les affaires du voisin" (sessions 8-9): the personal space must never show or modify another user's things.
 */
final class UserSpaceTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAnonymousIsRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/fr/mon-espace');

        self::assertResponseRedirects('/fr/login');
    }

    public function testDashboardShowsTheUserKpis(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $this->createItem('Pull bordeaux', $camille);

        $this->client->loginUser($camille);
        $this->client->request('GET', '/fr/mon-espace');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-cy="user-kpis"]', 'Mes objets');
        // the greeting is shown in the page itself, not only in the browser tab
        self::assertSelectorTextContains('.content-header h1', 'Bonjour Camille');
        self::assertPageTitleContains('Bonjour Camille');
    }

    public function testGreetingIsTranslated(): void
    {
        $this->client->loginUser($this->createUser('camille@example.com', 'Camille'));
        $this->client->request('GET', '/ar/mon-espace');

        self::assertSelectorTextContains('.content-header h1', 'مرحبًا Camille');
    }

    public function testListOnlyContainsMyItems(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $sophie = $this->createUser('sophie@example.com', 'Sophie');
        $this->createItem('Pull bordeaux', $camille);
        $this->createItem('Robe de Sophie', $sophie);

        $this->client->loginUser($camille);
        $this->client->request('GET', '/fr/mon-espace/my-item');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Pull bordeaux');
        self::assertSelectorTextNotContains('body', 'Robe de Sophie');
    }

    public function testCreatingAnItemAlwaysMakesMeTheOwner(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');

        $this->client->loginUser($camille);
        $crawler = $this->client->request('GET', '/fr/mon-espace/my-item/new');
        self::assertResponseIsSuccessful();

        // there is no owner field in the form: it cannot be forged
        self::assertCount(0, $crawler->filter('[name="Item[owner]"]'));

        $form = $crawler->filter('form[name="Item"]')->form(['Item[name]' => 'Trench beige']);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $item = $this->entityManager->getRepository(Item::class)->findOneBy(['name' => 'Trench beige']);
        self::assertNotNull($item);
        self::assertSame($camille->getId(), $item->getOwner()?->getId());
    }

    /**
     * The form greys the box out in the browser, but a forged request could still send both:
     * the server must refuse it.
     */
    public function testAPersonalItemCannotBeOpenedToFollowers(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');

        $this->client->loginUser($camille);
        $crawler = $this->client->request('GET', '/fr/mon-espace/my-item/new');
        // the comfort script is on the page
        self::assertStringContainsString('Item[isVisibleToFollowers]', (string) $this->client->getResponse()->getContent());

        $form = $crawler->filter('form[name="Item"]')->form(['Item[name]' => 'Bague de famille']);
        $form['Item[isPersonal]']->tick();
        $form['Item[isVisibleToFollowers]']->tick();
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Un objet personnel ne peut pas être visible par les abonnés.');
        self::assertNull($this->entityManager->getRepository(Item::class)->findOneBy(['name' => 'Bague de famille']));
    }

    public function testCreatingALoanOnlyOffersMyOwnItems(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $sophie = $this->createUser('sophie@example.com', 'Sophie');
        $pull = $this->createItem('Pull bordeaux', $camille);
        $this->createItem('Robe de Sophie', $sophie);

        $this->client->loginUser($camille);
        $crawler = $this->client->request('GET', '/fr/mon-espace/my-loan/new');
        self::assertResponseIsSuccessful();

        $options = $crawler->filter('select[name="Loan[item]"] option')->extract(['_text']);
        self::assertContains('Pull bordeaux', $options);
        self::assertNotContains('Robe de Sophie', $options);

        $form = $crawler->filter('form[name="Loan"]')->form([
            'Loan[item]' => (string) $pull->getId(),
            'Loan[borrowerName]' => 'Sophie',
            'Loan[lentAt]' => '2026-11-01',
            'Loan[dueDate]' => '2026-11-20',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertSame(1, $this->entityManager->getRepository(Loan::class)->count(['item' => $pull]));
    }

    public function testLoanListShowsTheStatusDeducedFromTheDates(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $loan = (new Loan())
            ->setItem($this->createItem('Vinyle · Fairuz', $camille))
            ->setBorrowerName('Sophie')
            ->setLentAt(new \DateTimeImmutable('-20 days'))
            ->setDueDate(new \DateTimeImmutable('-5 days'));
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $this->client->loginUser($camille);
        $this->client->request('GET', '/fr/mon-espace/my-loan');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.badge-danger', 'En retard');
    }

    public function testChangingTheIdInTheUrlToEditSomeoneElsesItemIsForbidden(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $sophie = $this->createUser('sophie@example.com', 'Sophie');
        $sophiesItem = $this->createItem('Robe de Sophie', $sophie);

        $this->client->loginUser($camille);
        $this->client->request('GET', '/fr/mon-espace/my-item/'.$sophiesItem->getId().'/edit');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAChildAccountCannotManageFollowers(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $child = $this->createUser('lea@example.com', 'Léa')->setRoles(['ROLE_CHILD'])->setParent($camille);
        $this->entityManager->flush();

        $this->client->loginUser($child);
        $this->client->request('GET', '/fr/mon-espace/my-follower');

        self::assertResponseStatusCodeSame(403);
    }

    private function createUser(string $email, string $pseudo): User
    {
        $user = (new User())->setEmail($email)->setPseudo($pseudo)->setPassword('not-a-real-hash');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createItem(string $name, User $owner): Item
    {
        $item = (new Item())->setName($name)->setOwner($owner);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }
}
