<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Repositories\StoreUserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class EloquentStoreUserRepository implements StoreUserRepositoryInterface
{
    public function __construct(
        private TenantContext $tenant,
    ) {}

    public function all(): Collection
    {
        return $this->query()->orderBy('name')->orderBy('id')->get();
    }

    public function findById(string $id): ?User
    {
        // El id es bigint: un valor no numérico haría fallar a Postgres.
        return ctype_digit($id) ? $this->query()->whereKey((int) $id)->first() : null;
    }

    public function create(string $name, string $email, string $hashedPassword): User
    {
        $user = new User;
        $user->forceFill([
            'store_id' => $this->tenant->id(),
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            // Invitado: entra cuando elige su contraseña desde el email (ver AccessLinks).
            'must_change_password' => false,
            'invitation_accepted_at' => null,
        ]);
        $user->saveQuietly();

        return $user;
    }

    public function update(User $user, string $name): User
    {
        $this->assertBelongsToTenant($user);

        $user->forceFill(['name' => $name])->save();

        return $user;
    }

    public function delete(User $user): void
    {
        $this->assertBelongsToTenant($user);

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * @return Builder<User>
     */
    private function query(): Builder
    {
        return User::query()->where('users.store_id', $this->tenant->id());
    }

    private function assertBelongsToTenant(User $user): void
    {
        if ($user->store_id !== $this->tenant->id()) {
            throw UserNotFoundException::withId((string) $user->id);
        }
    }
}
