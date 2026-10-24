<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Domain\Auth\Exceptions\UserManagementException;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Domain\Store\Repositories\StoreUserRepositoryInterface;
use Illuminate\Support\Facades\DB;

final readonly class DeleteUserAction
{
    public function __construct(
        private StoreUserRepositoryInterface $users,
        private EnsureOwnerRemains $owners,
    ) {}

    /**
     * @throws UserNotFoundException
     * @throws UserManagementException
     */
    public function execute(int $actorId, string $userId): void
    {
        DB::transaction(function () use ($actorId, $userId): void {
            $user = $this->users->findById($userId) ?? throw UserNotFoundException::withId($userId);

            if ($user->id === $actorId) {
                throw UserManagementException::cannotDeleteSelf();
            }

            if ($user->hasRole('owner')) {
                $this->owners->assertAnotherOwnerExists($user);
            }

            $this->users->delete($user);
        });
    }
}
