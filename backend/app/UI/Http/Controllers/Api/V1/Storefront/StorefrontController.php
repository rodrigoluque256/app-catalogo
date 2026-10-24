<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Storefront;

use App\Application\Category\Actions\ListCategoriesAction;
use App\Application\Product\Actions\GetStorefrontProductAction;
use App\Application\Product\Actions\ListStorefrontProductsAction;
use App\Domain\Shared\Tenancy\TenantContext;
use App\UI\Http\Requests\Product\ListStorefrontProductsRequest;
use App\UI\Http\Resources\CategoryResource;
use App\UI\Http\Resources\StoreResource;
use App\UI\Http\Resources\StorefrontProductResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StorefrontController
{
    public function store(TenantContext $tenant): StoreResource
    {
        return StoreResource::make($tenant->store());
    }

    public function categories(ListCategoriesAction $action): AnonymousResourceCollection
    {
        return CategoryResource::collection($action->execute(onlyActive: true));
    }

    public function products(ListStorefrontProductsRequest $request, ListStorefrontProductsAction $action): AnonymousResourceCollection
    {
        return StorefrontProductResource::collection($action->execute($request->toFilters()));
    }

    public function product(string $slug, GetStorefrontProductAction $action): StorefrontProductResource
    {
        return StorefrontProductResource::make($action->execute($slug));
    }
}
