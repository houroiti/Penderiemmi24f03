<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Item;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ItemControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testOwnerGetsTheItemAsJson(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $item = $this->createItem('Vinyle Daft Punk', $camille);

        $this->client->loginUser($camille);
        $this->client->request('GET', '/api/items/'.$item->getId());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame($item->getId(), $data['id']);
        self::assertSame('Vinyle Daft Punk', $data['name']);
        self::assertFalse($data['isPersonal']);
        self::assertArrayHasKey('createdAt', $data);
        // only the "item:read" fields of the owner are exposed: never the email, roles or password
        self::assertSame(['id' => $camille->getId(), 'pseudo' => 'Camille'], $data['owner']);
    }

    public function testOtherUserIsForbidden(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $sophie = $this->createUser('sophie@example.com', 'Sophie');
        $item = $this->createItem('Vinyle Daft Punk', $camille);

        $this->client->loginUser($sophie);
        $this->client->request('GET', '/api/items/'.$item->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testOtherUserCannotSeeAPersonalItem(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $sophie = $this->createUser('sophie@example.com', 'Sophie');
        $item = $this->createItem('Montre de famille', $camille, true);

        $this->client->loginUser($sophie);
        $this->client->request('GET', '/api/items/'.$item->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousIsUnauthorized(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');
        $item = $this->createItem('Vinyle Daft Punk', $camille);

        $this->client->request('GET', '/api/items/'.$item->getId());

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnknownItemReturnsNotFound(): void
    {
        $camille = $this->createUser('camille@example.com', 'Camille');

        $this->client->loginUser($camille);
        $this->client->request('GET', '/api/items/999999');

        self::assertResponseStatusCodeSame(404);
    }

    private function createUser(string $email, string $pseudo): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo)
            ->setPassword('not-a-real-hash');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createItem(string $name, User $owner, bool $isPersonal = false): Item
    {
        $item = (new Item())
            ->setName($name)
            ->setOwner($owner)
            ->setIsPersonal($isPersonal);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }
}