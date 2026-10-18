<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Item;
use App\Entity\User;
use App\Security\Voter\ItemVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ItemVoterTest extends TestCase
{
    #[DataProvider('voteProvider')]
    public function testVote(?User $user, mixed $subject, string $attribute, int $expected): void
    {
        $voter = new ItemVoter();

        self::assertSame($expected, $voter->vote(self::token($user), $subject, [$attribute]));
    }

    /**
     * @return iterable<string, array{?User, mixed, string, int}>
     */
    public static function voteProvider(): iterable
    {
        $owner = self::user(1);
        $other = self::user(2);
        $item = (new Item())->setName('Vinyle')->setOwner($owner);
        $personal = (new Item())->setName('Montre')->setOwner($owner)->setIsPersonal(true);

        yield 'owner can view' => [$owner, $item, ItemVoter::VIEW, VoterInterface::ACCESS_GRANTED];
        yield 'owner can edit' => [$owner, $item, ItemVoter::EDIT, VoterInterface::ACCESS_GRANTED];
        yield 'owner can view a personal item' => [$owner, $personal, ItemVoter::VIEW, VoterInterface::ACCESS_GRANTED];
        yield 'other user cannot view' => [$other, $item, ItemVoter::VIEW, VoterInterface::ACCESS_DENIED];
        yield 'other user cannot edit' => [$other, $item, ItemVoter::EDIT, VoterInterface::ACCESS_DENIED];
        yield 'other user cannot view a personal item' => [$other, $personal, ItemVoter::VIEW, VoterInterface::ACCESS_DENIED];
        yield 'anonymous cannot view' => [null, $item, ItemVoter::VIEW, VoterInterface::ACCESS_DENIED];
        yield 'abstains on an unknown attribute' => [$owner, $item, 'DELETE', VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains when the subject is not an item' => [$owner, 'not an item', ItemVoter::VIEW, VoterInterface::ACCESS_ABSTAIN];
    }

    /**
     * Simulates a user already saved in database: the id is normally set by Doctrine.
     */
    private static function user(int $id): User
    {
        $user = (new User())->setEmail("user{$id}@example.com")->setPseudo("user{$id}");
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private static function token(?User $user): TokenInterface
    {
        return null === $user ? new NullToken() : new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}