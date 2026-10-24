<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Domain\Store\Repositories\StoreUserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class ListUsersAction
{
    public function __construct(
        private StoreUserRepositoryInterface $users,
    ) {}

    /**
     * @return Collection<int, array{user: User, role: string|null}>
     */
    public function execute(): Collection
    {
        return $this->users->all()->map(fn (User $user): array => [
            'user' => $user,
            'role' => ($role = $user->getRoleNames()->first()) === null ? null : (string) $role,
        ]);
    }
}
