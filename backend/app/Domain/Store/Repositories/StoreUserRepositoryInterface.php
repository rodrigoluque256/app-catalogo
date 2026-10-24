<?php

declare(strict_types=1);

namespace App\Domain\Store\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Usuarios de la tienda del TenantContext actual. Nunca devuelve usuarios de otra tienda ni de plataforma.
 */
interface StoreUserRepositoryInterface
{
    /**
     * @return Collection<int, User>
     */
    public function all(): Collection;

    public function findById(string $id): ?User;

    public function create(string $name, string $email, string $hashedPassword): User;

    public function update(User $user, string $name): User;

    public function delete(User $user): void;
}
