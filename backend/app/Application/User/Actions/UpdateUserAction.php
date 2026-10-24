<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Domain\Auth\Exceptions\UserManagementException;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Domain\Store\Repositories\StoreUserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserAction
{
    public function __construct(
        private StoreUserRepositoryInterface $users,
        private EnsureOwnerRemains $owners,
    ) {}

    /**
     * @return array{user: User, role: string}
     *
     * @throws UserNotFoundException
     * @throws UserManagementException
     */
    public function execute(int $actorId, string $userId, string $name, string $role): array
    {
        return DB::transaction(function () use ($actorId, $userId, $name, $role): array {
            $user = $this->users->findById($userId) ?? throw UserNotFoundException::withId($userId);
            $currentRole = $user->getRoleNames()->first();

            if ($currentRole !== $role) {
                if ($user->id === $actorId) {
                    throw UserManagementException::cannotChangeOwnRole();
                }

                if ($currentRole === 'owner') {
                    $this->owners->assertAnotherOwnerExists($user);
                }

                $user->syncRoles([$role]);
            }

            return ['user' => $this->users->update($user, $name), 'role' => $role];
        });
    }
}
