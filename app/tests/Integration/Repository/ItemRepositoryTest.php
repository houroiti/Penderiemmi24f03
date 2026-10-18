<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Item;
use App\Entity\User;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ItemRepositoryTest extends KernelTestCase
{
    public function testSavedItemCanBeReadBackFromDatabase(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $repository = $container->get(ItemRepository::class);

        $owner = (new User())
            ->setEmail('camille@example.com')
            ->setPseudo('Camille')
            ->setPassword('not-a-real-hash');
        $item = (new Item())
            ->setName('Vinyle Daft Punk')
            ->setOwner($owner);

        $entityManager->persist($owner);
        $entityManager->persist($item);
        $entityManager->flush();

        $itemId = $item->getId();
        $ownerId = $owner->getId();
        $createdAt = $item->getCreatedAt();

        // forget every loaded object, so that find() really reads the database
        $entityManager->clear();

        $reloaded = $repository->find($itemId);

        self::assertInstanceOf(Item::class, $reloaded);
        self::assertNotSame($item, $reloaded);
        self::assertSame('Vinyle Daft Punk', $reloaded->getName());
        self::assertFalse($reloaded->isPersonal());
        self::assertSame($ownerId, $reloaded->getOwner()?->getId());
        self::assertSame('Camille', $reloaded->getOwner()->getPseudo());
        // the column is TIMESTAMP(0): microseconds are not stored
        self::assertSame($createdAt->format('Y-m-d H:i:s'), $reloaded->getCreatedAt()->format('Y-m-d H:i:s'));
    }
}