<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan de la plataforma. Precios en centavos ARS; `max_products` null = sin límite.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $monthly_price
 * @property int $annual_price
 * @property int|null $max_products
 * @property int $max_images_per_product
 * @property int $max_users
 * @property int $position
 * @property bool $is_active
 */
class Plan extends Model
{
    public const string FREE = 'free';

    protected $guarded = ['id'];

    public function isFree(): bool
    {
        return $this->monthly_price === 0;
    }

    public static function free(): self
    {
        return self::query()->where('code', self::FREE)->firstOrFail();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'annual_price' => 'integer',
            'max_products' => 'integer',
            'max_images_per_product' => 'integer',
            'max_users' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
