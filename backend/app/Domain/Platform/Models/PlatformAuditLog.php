<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de lo que hace cada administrador de la plataforma (y de los intentos fallidos de entrar).
 *
 * @property int $id
 * @property int|null $platform_admin_id
 * @property string $action
 * @property string|null $store_id
 * @property array<string, mixed> $metadata
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
class PlatformAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'platform_admin_id',
        'action',
        'store_id',
        'metadata',
        'ip',
        'user_agent',
    ];

    protected $attributes = [
        'metadata' => '{}',
    ];

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
