<?php

declare(strict_types=1);

namespace App\Application\Auth\Actions;

use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;

final readonly class LoginAction
{
    public const int TOKEN_TTL_HOURS = 12;

    /** Hash válido (de "dummy") para gastar el mismo tiempo cuando el email no existe. */
    private const string DUMMY_HASH = '$2y$12$5JmYdf1gROt5YjDgStzgDu91P6ZUVmnHzBHT61M3iMrhzOqZHjwZC';

    public function __construct(
        private StoreRepositoryInterface $stores,
        private Hasher $hasher,
    ) {}

    /**
     * Solo usuarios de una tienda activa. Los administradores de la plataforma son otra tabla
     * (`platform_admins`) con su propio login: ver PlatformLoginAction.
     *
     * @return array{user: User, token: string}
     *
     * @throws InvalidCredentialsException
     */
    public function execute(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        $passwordMatches = $this->hasher->check($password, $user->password ?? self::DUMMY_HASH);

        if ($user === null || ! $passwordMatches || $user->store_id === null || $this->stores->findActiveById($user->store_id) === null) {
            throw new InvalidCredentialsException;
        }

        return [
            'user' => $user,
            'token' => $user->createToken('admin', ['*'], now()->addHours(self::TOKEN_TTL_HOURS))->plainTextToken,
        ];
    }
}
