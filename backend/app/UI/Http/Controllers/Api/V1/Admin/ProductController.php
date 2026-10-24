<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Admin;

use App\Application\Product\Actions\CreateProductAction;
use App\Application\Product\Actions\DeleteProductAction;
use App\Application\Product\Actions\GetProductAction;
use App\Application\Product\Actions\ListProductsAction;
use App\Application\Product\Actions\UpdateProductAction;
use App\UI\Http\Requests\Product\ListProductsRequest;
use App\UI\Http\Requests\Product\StoreProductRequest;
use App\UI\Http\Requests\Product\UpdateProductRequest;
use App\UI\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * HTTP in → DTO → Action → Resource out. Sin lógica de negocio.
 */
final class ProductController
{
    public function index(ListProductsRequest $request, ListProductsAction $action): AnonymousResourceCollection
    {
        return ProductResource::collection($action->execute($request->toFilters()));
    }

    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        return ProductResource::make($action->execute($request->toData()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $product, GetProductAction $action): ProductResource
    {
        return ProductResource::make($action->execute($product));
    }

    public function update(UpdateProductRequest $request, string $product, UpdateProductAction $action): ProductResource
    {
        return ProductResource::make($action->execute($product, $request->toData()));
    }

    public function destroy(string $product, DeleteProductAction $action): Response
    {
        $action->execute($product);

        return response()->noContent();
    }
}
