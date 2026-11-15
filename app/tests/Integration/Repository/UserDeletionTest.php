<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Follow;
use App\Entity\Furniture;
use App\Entity\Home;
use App\Entity\Item;
use App\Entity\Loan;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Right to erasure: deleting an account deletes everything that belongs to it, and only that.
 * The deletion is done by PostgreSQL itself (ON DELETE CASCADE on the foreign keys).
 */
final class UserDeletionTest extends KernelTestCase
{
    public function testDeletingAUserDeletesTheirThingsButNotTheOthers(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $victor = $this->user($entityManager, 'victor@example.com', 'Victor');
        $sophie = $this->user($entityManager, 'sophie@example.com', 'Sophie');

        $home = (new Home())->setName('Appartement')->setOwner($victor);
        $room = (new Room())->setName('Chambre')->setHome($home);
        $furniture = (new Furniture())->setName('Armoire')->setRoom($room);
        $item = (new Item())->setName('Pull')->setOwner($victor)->setFurniture($furniture);
        $loan = (new Loan())->setItem($item)->setBorrowerName('Sophie');
        $follow = (new Follow())->setFollower($sophie)->setFollowed($victor);
        $sophiesItem = (new Item())->setName('Robe')->setOwner($sophie);
        foreach ([$home, $room, $furniture, $item, $loan, $follow, $sophiesItem] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        // SQL DELETE, as a real deletion would do: the database must clean up by itself
        $entityManager->getConnection()->executeStatement('DELETE FROM "user" WHERE id = ?', [$victor->getId()]);
        $entityManager->clear();

        foreach ([Item::class, Home::class, Room::class, Furniture::class, Loan::class, Follow::class] as $class) {
            $remaining = $entityManager->getRepository($class)->count([]);
            self::assertSame(Item::class === $class ? 1 : 0, $remaining, $class);
        }
        // Sophie and her dress are untouched
        self::assertNotNull($entityManager->getRepository(Item::class)->findOneBy(['name' => 'Robe']));
        self::assertNotNull($entityManager->getRepository(User::class)->findOneBy(['email' => 'sophie@example.com']));
    }

    private function user(EntityManagerInterface $entityManager, string $email, string $pseudo): User
    {
        $user = (new User())->setEmail($email)->setPseudo($pseudo)->setPassword('not-a-real-hash');
        $entityManager->persist($user);

        return $user;
    }
}
