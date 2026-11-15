<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Furniture;
use App\Entity\Home;
use App\Entity\Item;
use App\Entity\Loan;
use App\Entity\Room;
use App\Entity\User;
use App\Security\Voter\OwnedVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class OwnedVoterTest extends TestCase
{
    public function testOwnerManagesTheWholeChainHomeRoomFurnitureItemLoan(): void
    {
        $camille = self::user(1);
        $home = (new Home())->setName('Appartement')->setOwner($camille);
        $room = (new Room())->setName('Chambre')->setHome($home);
        $furniture = (new Furniture())->setName('Armoire')->setRoom($room);
        $item = (new Item())->setName('Pull')->setOwner($camille)->setFurniture($furniture);
        $loan = (new Loan())->setItem($item)->setBorrowerName('Sophie');

        $voter = new OwnedVoter();
        $token = new UsernamePasswordToken($camille, 'main', $camille->getRoles());

        foreach ([$home, $room, $furniture, $item, $loan] as $subject) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $subject, [OwnedVoter::MANAGE]), $subject::class);
        }
    }

    public function testAnotherUserCannotManage(): void
    {
        $room = (new Room())->setName('Chambre')->setHome((new Home())->setName('Appartement')->setOwner(self::user(1)));
        $sophie = self::user(2);

        $vote = (new OwnedVoter())->vote(new UsernamePasswordToken($sophie, 'main', $sophie->getRoles()), $room, [OwnedVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    public function testAnonymousCannotManage(): void
    {
        $home = (new Home())->setName('Appartement')->setOwner(self::user(1));

        self::assertSame(VoterInterface::ACCESS_DENIED, (new OwnedVoter())->vote(new NullToken(), $home, [OwnedVoter::MANAGE]));
    }

    public function testAbstainsOnOtherAttributesAndSubjects(): void
    {
        $camille = self::user(1);
        $token = new UsernamePasswordToken($camille, 'main', $camille->getRoles());
        $home = (new Home())->setOwner($camille);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, (new OwnedVoter())->vote($token, $home, ['VIEW']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, (new OwnedVoter())->vote($token, new \stdClass(), [OwnedVoter::MANAGE]));
    }

    private static function user(int $id): User
    {
        $user = (new User())->setEmail("user{$id}@example.com")->setPseudo("user{$id}");
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
