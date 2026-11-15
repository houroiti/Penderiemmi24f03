<?php

namespace App\Tests\Functional\BackOffice;

use App\Entity\Furniture;
use App\Entity\Home;
use App\Entity\Item;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Homes, rooms and furniture in "Mon espace": shortcuts to add inside a place,
 * and a clear warning before a deletion that removes more than one thing.
 */
final class PlacesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $camille;
    private Home $home;
    private Room $room;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->camille = $this->persist((new User())->setEmail('camille@example.com')->setPseudo('Camille')->setPassword('not-a-real-hash'));
        $this->home = $this->persist((new Home())->setName('Appartement')->setOwner($this->camille));
        $this->room = $this->persist((new Room())->setName('Chambre')->setHome($this->home));
        $wardrobe = $this->persist((new Furniture())->setName('Armoire')->setRoom($this->room));
        $this->persist((new Furniture())->setName('Commode')->setRoom($this->room));
        $this->persist((new Item())->setName('Pull')->setOwner($this->camille)->setFurniture($wardrobe));
        $this->entityManager->flush();
        // forget the objects in memory: the pages must read the rooms and furniture from the database
        $this->entityManager->clear();

        $this->client->loginUser($this->camille);
    }

    public function testAHomeHasAShortcutToAddARoomWithTheHomeAlreadyChosen(): void
    {
        $crawler = $this->client->request('GET', '/fr/mon-espace/my-home');
        $link = $crawler->filter('a:contains("Ajouter une pièce")')->first();
        self::assertStringContainsString('home='.$this->home->getId(), (string) $link->attr('href'));

        $crawler = $this->client->request('GET', (string) $link->attr('href'));

        self::assertResponseIsSuccessful();
        self::assertSame((string) $this->home->getId(), $crawler->filter('select[name="Room[home]"] option[selected]')->attr('value'));
    }

    public function testARoomHasAShortcutToAddFurnitureWithTheRoomAlreadyChosen(): void
    {
        $crawler = $this->client->request('GET', '/fr/mon-espace/my-furniture/new?room='.$this->room->getId());

        self::assertResponseIsSuccessful();
        self::assertSame((string) $this->room->getId(), $crawler->filter('select[name="Furniture[room]"] option[selected]')->attr('value'));
    }

    /**
     * Putting someone else's home id in the URL must not pre-select it (nor show it).
     */
    public function testSomeoneElsesHomeIsNeverPreselected(): void
    {
        $sophie = $this->persist((new User())->setEmail('sophie@example.com')->setPseudo('Sophie')->setPassword('not-a-real-hash'));
        $sophiesHome = $this->persist((new Home())->setName('Maison de Sophie')->setOwner($sophie));
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/fr/mon-espace/my-room/new?home='.$sophiesHome->getId());

        self::assertResponseIsSuccessful();
        // the empty choice is shown, never one of the user's homes picked silently
        self::assertSame('', $crawler->filter('select[name="Room[home]"] option[selected]')->attr('value'));
        self::assertSame('Choisir un logement…', trim($crawler->filter('select[name="Room[home]"] option[selected]')->text()));
        self::assertSelectorTextNotContains('select[name="Room[home]"]', 'Maison de Sophie');
    }

    /**
     * Each list starts with an empty choice, so nothing is silently pre-selected.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function emptyChoiceProvider(): iterable
    {
        yield 'room: home' => ['/fr/mon-espace/my-room/new', 'Room[home]', 'Choisir un logement…'];
        yield 'furniture: room' => ['/fr/mon-espace/my-furniture/new', 'Furniture[room]', 'Choisir une pièce…'];
        yield 'loan: item' => ['/fr/mon-espace/my-loan/new', 'Loan[item]', 'Choisir un objet…'];
        yield 'item: furniture (optional)' => ['/fr/mon-espace/my-item/new', 'Item[furniture]', 'Pas encore rangé'];
        yield 'arabic' => ['/ar/mon-espace/my-room/new', 'Room[home]', 'اختر مسكنًا…'];
    }

    #[DataProvider('emptyChoiceProvider')]
    public function testListsStartWithAnEmptyChoice(string $url, string $field, string $label): void
    {
        $crawler = $this->client->request('GET', $url);

        $first = $crawler->filter("select[name=\"{$field}\"] option")->first();
        self::assertSame('', $first->attr('value'));
        self::assertSame($label, trim($first->text()));
        // no real choice is pre-selected: the browser shows the empty first one
        self::assertCount(0, $crawler->filter("select[name=\"{$field}\"] option[selected]:not([value=''])"));
    }

    public function testARoomCannotBeCreatedWithoutChoosingAHome(): void
    {
        $crawler = $this->client->request('GET', '/fr/mon-espace/my-room/new');
        $form = $crawler->filter('form[name="Room"]')->form(['Room[name]' => 'Bureau']);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager->getRepository(Room::class)->findOneBy(['name' => 'Bureau']));
    }

    public function testDeletingAHomeWarnsAboutEverythingThatGoesWithIt(): void
    {
        $this->client->request('GET', '/fr/mon-espace/my-home');

        self::assertStringContainsString(
            'Supprimer « Appartement » supprimera aussi ses pièces (1) et ses meubles (2). Les objets rangés dedans (1) ne seront pas supprimés',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testDeletingARoomWarnsAboutItsFurnitureAndItems(): void
    {
        $this->client->request('GET', '/fr/mon-espace/my-room');

        self::assertStringContainsString(
            'Supprimer « Chambre » supprimera aussi ses meubles (2). Les objets rangés dedans (1) ne seront pas supprimés',
            (string) $this->client->getResponse()->getContent(),
        );
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
