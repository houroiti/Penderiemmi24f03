<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Follow;
use App\Entity\Item;
use App\Entity\User;
use App\Repository\FollowRepository;
use App\Security\Voter\ItemVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ItemVoterTest extends TestCase
{
    private const OWNER = 1;
    private const OTHER = 2;
    private const CHILD = 3;

    /**
     * @param 'owner'|'other'|'child'|null $who      null = anonymous
     * @param 'normal'|'personal'|'for_followers'|'personal_for_followers' $itemKind
     * @param 'close_friend'|'follower'|null $relation relation between "other" and the owner
     */
    #[DataProvider('voteProvider')]
    public function testVote(?string $who, string $itemKind, string $attribute, ?string $relation, int $expected): void
    {
        $owner = self::user(self::OWNER);
        $users = [
            'owner' => $owner,
            'other' => self::user(self::OTHER),
            'child' => self::user(self::CHILD)->setParent($owner),
        ];

        $item = (new Item())
            ->setName('Vinyle')
            ->setOwner($owner)
            ->setIsPersonal(in_array($itemKind, ['personal', 'personal_for_followers'], true))
            ->setIsVisibleToFollowers(in_array($itemKind, ['for_followers', 'personal_for_followers'], true));

        // the voter reads FOLLOWS through the repository: here we fake its answer, no database needed
        $follow = null === $relation ? null : (new Follow())->setIsCloseFriend('close_friend' === $relation);
        $followRepository = $this->createStub(FollowRepository::class);
        $followRepository->method('findRelation')->willReturn($follow);

        $voter = new ItemVoter($followRepository);
        $token = null === $who ? new NullToken() : self::token($users[$who]);

        self::assertSame($expected, $voter->vote($token, $item, [$attribute]));
    }

    /**
     * @return iterable<string, array{?string, string, string, ?string, int}>
     */
    public static function voteProvider(): iterable
    {
        $granted = VoterInterface::ACCESS_GRANTED;
        $denied = VoterInterface::ACCESS_DENIED;

        yield 'owner can view' => ['owner', 'normal', ItemVoter::VIEW, null, $granted];
        yield 'owner can edit' => ['owner', 'normal', ItemVoter::EDIT, null, $granted];
        yield 'owner can view a personal item' => ['owner', 'personal', ItemVoter::VIEW, null, $granted];
        yield 'stranger cannot view' => ['other', 'normal', ItemVoter::VIEW, null, $denied];
        yield 'stranger cannot edit' => ['other', 'normal', ItemVoter::EDIT, null, $denied];
        yield 'anonymous cannot view' => [null, 'normal', ItemVoter::VIEW, null, $denied];

        yield 'close friend can view' => ['other', 'normal', ItemVoter::VIEW, 'close_friend', $granted];
        yield 'close friend cannot edit' => ['other', 'normal', ItemVoter::EDIT, 'close_friend', $denied];
        yield 'close friend cannot view a personal item' => ['other', 'personal', ItemVoter::VIEW, 'close_friend', $denied];
        yield 'follower cannot view a normal item' => ['other', 'normal', ItemVoter::VIEW, 'follower', $denied];
        yield 'follower can view an item opened to followers' => ['other', 'for_followers', ItemVoter::VIEW, 'follower', $granted];

        // even if such data existed (the validation forbids it), "personal" wins over any sharing
        yield 'follower cannot view a personal item, even opened to followers' => ['other', 'personal_for_followers', ItemVoter::VIEW, 'follower', $denied];
        yield 'close friend cannot view a personal item, even opened to followers' => ['other', 'personal_for_followers', ItemVoter::VIEW, 'close_friend', $denied];

        yield 'child can view the parent item' => ['child', 'normal', ItemVoter::VIEW, null, $granted];
        yield 'child cannot edit the parent item' => ['child', 'normal', ItemVoter::EDIT, null, $denied];
        yield 'child cannot view a personal item of the parent' => ['child', 'personal', ItemVoter::VIEW, null, $denied];
    }

    public function testAbstainsOnAnUnknownAttribute(): void
    {
        $voter = new ItemVoter($this->createStub(FollowRepository::class));
        $item = (new Item())->setOwner(self::user(self::OWNER));

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(self::token(self::user(self::OWNER)), $item, ['DELETE']));
    }

    public function testAbstainsWhenTheSubjectIsNotAnItem(): void
    {
        $voter = new ItemVoter($this->createStub(FollowRepository::class));

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(self::token(self::user(self::OWNER)), 'not an item', [ItemVoter::VIEW]));
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

    private static function token(User $user): TokenInterface
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
