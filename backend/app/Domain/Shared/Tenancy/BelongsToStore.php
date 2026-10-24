<?php

declare(strict_types=1);

namespace App\Domain\Shared\Tenancy;

use App\Domain\Store\Models\Store;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Para todo modelo con columna store_id:
 *  - aplica StoreScope a cada query,
 *  - completa store_id al crear con el tenant actual si no vino seteado.
 *
 * @mixin Model
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(static function (Model $model): void {
            if ($model->getAttribute('store_id') === null) {
                $model->setAttribute('store_id', Container::getInstance()->make(TenantContext::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
