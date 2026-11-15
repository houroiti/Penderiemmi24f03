<?php

namespace App\Security\Voter;

use App\Entity\OwnedInterface;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * "MANAGE": only the owner may show, edit or delete something in their personal space
 * (home, room, furniture, item, loan, follow). Protects against changing the id in the URL.
 * Without a subject (creation form: nothing exists yet), any logged-in user may create:
 * the owner is then set by the code, never by the form.
 *
 * @extends Voter<string, OwnedInterface|null>
 */
final class OwnedVoter extends Voter
{
    public const MANAGE = 'MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && ($subject instanceof OwnedInterface || null === $subject);
    }

    /**
     * @param OwnedInterface|null $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User || null === $user->getId()) {
            $vote?->addReason('The user must be logged in to access this resource.');

            return false;
        }

        if (null === $subject) {
            return true;
        }

        return $subject->getOwner()?->getId() === $user->getId();
    }
}
