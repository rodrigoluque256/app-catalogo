<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Domain\Auth\Exceptions\UserManagementException;
use App\Domain\Store\Repositories\StoreUserRepositoryInterface;
use App\Models\User;

final readonly class EnsureOwnerRemains
{
    public function __construct(
        private StoreUserRepositoryInterface $users,
    ) {}

    /**
     * @throws UserManagementException si $leaving es el único dueño de la tienda
     */
    public function assertAnotherOwnerExists(User $leaving): void
    {
        $otherOwners = $this->users->all()->filter(
            fn (User $user): bool => $user->id !== $leaving->id && $user->hasRole('owner'),
        );

        if ($otherOwners->isEmpty()) {
            throw UserManagementException::lastOwner();
        }
    }
}
