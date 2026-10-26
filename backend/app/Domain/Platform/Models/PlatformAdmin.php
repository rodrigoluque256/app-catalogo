<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Database\Factories\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Administrador de la plataforma (super panel). No pertenece a ninguna tienda ni usa roles de Spatie:
 * la separación con los usuarios de tienda es por tabla y por tipo de token.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $two_factor_secret
 * @property Carbon|null $two_factor_confirmed_at
 * @property list<string>|null $two_factor_recovery_codes
 * @property int|null $two_factor_last_step
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 */
class PlatformAdmin extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    protected static function newFactory(): PlatformAdminFactory
    {
        return PlatformAdminFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_last_step' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }
}
