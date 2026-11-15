<?php

namespace App\Entity;

/**
 * Implemented by every entity that belongs to a user, directly or through its parent
 * (a room belongs to the owner of its home, a loan to the owner of its item...).
 */
interface OwnedInterface
{
    public function getOwner(): ?User;
}
