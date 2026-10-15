<?php

namespace App\Security\Voter;

use App\Entity\Item;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Item>
 */
final class ItemVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';

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

        // a personal item is never visible to anyone but its owner, even once sharing exists
        if ($item->isPersonal()) {
            return false;
        }

        // no sharing yet: friends/followers will be granted here (FOLLOWS)
        return false;
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