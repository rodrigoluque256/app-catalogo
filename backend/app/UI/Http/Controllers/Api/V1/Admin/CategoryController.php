<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Admin;

use App\Application\Category\Actions\CreateCategoryAction;
use App\Application\Category\Actions\DeleteCategoryAction;
use App\Application\Category\Actions\GetCategoryAction;
use App\Application\Category\Actions\ListCategoriesAction;
use App\Application\Category\Actions\UpdateCategoryAction;
use App\UI\Http\Requests\Category\CategoryRequest;
use App\UI\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CategoryController
{
    public function index(ListCategoriesAction $action): AnonymousResourceCollection
    {
        return CategoryResource::collection($action->execute());
    }

    public function store(CategoryRequest $request, CreateCategoryAction $action): JsonResponse
    {
        return CategoryResource::make($action->execute($request->toData()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $category, GetCategoryAction $action): CategoryResource
    {
        return CategoryResource::make($action->execute($category));
    }

    public function update(CategoryRequest $request, string $category, UpdateCategoryAction $action): CategoryResource
    {
        return CategoryResource::make($action->execute($category, $request->toData()));
    }

    public function destroy(string $category, DeleteCategoryAction $action): Response
    {
        $action->execute($category);

        return response()->noContent();
    }
}
