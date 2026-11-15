<?php

namespace App\Security\Voter;

use App\Entity\Item;
use App\Entity\User;
use App\Repository\FollowRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who can see or modify an item (permission table of sessions 8-9).
 *
 * @extends Voter<string, Item>
 */
final class ItemVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';

    public function __construct(
        private readonly FollowRepository $followRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Item;
    }

    /**
     * @param Item $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        // if the user is anonymous, do not grant access
        if (!$user instanceof User) {
            $vote?->addReason('The user must be logged in to access this resource.');

            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            default => false,
        };
    }

    private function canView(Item $item, User $user): bool
    {
        if ($this->isOwner($item, $user)) {
            return true;
        }

        // a personal item is never visible to anyone but its owner, whatever the sharing
        if ($item->isPersonal()) {
            return false;
        }

        $owner = $item->getOwner();
        if (null === $owner) {
            return false;
        }

        // the child sub-account sees the items of its parent
        if (null !== $owner->getId() && $user->getParent()?->getId() === $owner->getId()) {
            return true;
        }

        // FOLLOWS is read again on every request: removing a friend takes effect immediately
        $follow = $this->followRepository->findRelation($user, $owner);
        if (null === $follow) {
            return false;
        }

        return $follow->isCloseFriend() || $item->isVisibleToFollowers();
    }

    private function canEdit(Item $item, User $user): bool
    {
        return $this->isOwner($item, $user);
    }

    private function isOwner(Item $item, User $user): bool
    {
        return null !== $user->getId() && $item->getOwner()?->getId() === $user->getId();
    }
}
