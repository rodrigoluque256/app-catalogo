<?php

declare(strict_types=1);

use App\Domain\Store\Contracts\UploadedObject;
use App\Domain\Store\Contracts\UploadedObjectInspector;
use App\Domain\Store\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Unit: bootean la app (facades, helpers) pero no tocan la base; las dependencias se mockean.
pest()->extend(TestCase::class)->in('Unit');

// Feature: PostgreSQL real (ver phpunit.xml), transacción por test.
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// R2 no existe en tests: por defecto toda key "subida" es una imagen WebP válida de 100 KB.
pest()->beforeEach(function (): void {
    app()->instance(UploadedObjectInspector::class, new class implements UploadedObjectInspector
    {
        public function inspect(string $path): ?UploadedObject
        {
            return new UploadedObject(100_000, 'image/webp');
        }
    });
})->in('Feature');

/**
 * Autentica un usuario de $store con el rol dado dentro de esa tienda (team de Spatie).
 */
function actingAsStoreUser(Store $store, ?string $role = 'owner'): User
{
    $user = User::factory()->forStore($store)->create();

    if ($role !== null) {
        setPermissionsTeamId($store->id);
        $user->assignRole($role);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    Sanctum::actingAs($user);

    return $user;
}

/**
 * Request con un bearer token real (no Sanctum::actingAs). Olvida el usuario cacheado por el guard
 * entre requests del mismo test, para poder alternar tokens.
 *
 * @param  array<string, mixed>  $data
 */
function withBearer(string $token, string $method, string $uri, array $data = []): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withToken($token)->json($method, $uri, $data);
}

/**
 * Token del link que se le mandó a $user (invitación o reseteo), con Notification::fake() activo.
 *
 * @param  class-string  $notification
 */
function sentLinkToken(User $user, string $notification): string
{
    $token = null;
    Notification::assertSentTo($user, $notification, function (object $sent) use (&$token): bool {
        $token = $sent->token;

        return true;
    });

    return (string) $token;
}
