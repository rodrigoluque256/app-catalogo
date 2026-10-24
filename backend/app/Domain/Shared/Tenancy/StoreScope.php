<?php

declare(strict_types=1);

namespace App\Domain\Shared\Tenancy;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class StoreScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Se resuelve en cada query (no al bootear el modelo) para no retener un tenant viejo en Octane/queues.
        $tenant = Container::getInstance()->make(TenantContext::class);

        $builder->where($model->qualifyColumn('store_id'), $tenant->id());
    }
}
