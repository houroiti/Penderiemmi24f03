<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\FollowRepository;
use App\Repository\HomeRepository;
use App\Repository\ItemRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;

/**
 * Computes the KPIs shown in the two back offices.
 * The repositories count, this service turns the counts into indicators.
 */
final class StatsService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ItemRepository $itemRepository,
        private readonly LoanRepository $loanRepository,
        private readonly FollowRepository $followRepository,
        private readonly HomeRepository $homeRepository,
    ) {
    }

    /**
     * KPIs of the whole application, for the super admin.
     *
     * @return array{users: int, newUsersLast7Days: int, items: int, personalItems: int, personalRatio: float, itemsPerUser: float, activeLoans: int, overdueLoans: int, itemsByCategory: array<string, int>}
     */
    public function getGlobalStats(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $users = $this->userRepository->count([]);
        $items = $this->itemRepository->countItems();
        $personalItems = $this->itemRepository->countPersonal();

        return [
            'users' => $users,
            'newUsersLast7Days' => $this->userRepository->countCreatedSince($today->modify('-7 days')),
            'items' => $items,
            'personalItems' => $personalItems,
            'personalRatio' => self::percentage($personalItems, $items),
            'itemsPerUser' => 0 === $users ? 0.0 : round($items / $users, 1),
            'activeLoans' => $this->loanRepository->countActive(),
            'overdueLoans' => $this->loanRepository->countOverdue($today),
            'itemsByCategory' => $this->itemRepository->countByCategory(),
        ];
    }

    /**
     * KPIs of one user, for their personal space.
     *
     * @return array{items: int, personalItems: int, unstoredItems: int, homes: int, activeLoans: int, overdueLoans: int, closeFriends: int, followers: int}
     */
    public function getUserStats(User $user, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');

        return [
            'items' => $this->itemRepository->countItems($user),
            'personalItems' => $this->itemRepository->countPersonal($user),
            'unstoredItems' => $this->itemRepository->countUnstored($user),
            'homes' => $this->homeRepository->countByOwner($user),
            'activeLoans' => $this->loanRepository->countActive($user),
            'overdueLoans' => $this->loanRepository->countOverdue($today, $user),
            'closeFriends' => $this->followRepository->countFollowersOf($user, true),
            'followers' => $this->followRepository->countFollowersOf($user),
        ];
    }

    /**
     * Percentage rounded to one decimal; 0 when there is nothing to divide.
     */
    public static function percentage(int $part, int $total): float
    {
        return 0 === $total ? 0.0 : round($part * 100 / $total, 1);
    }
}
