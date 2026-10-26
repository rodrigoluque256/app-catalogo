<?php

declare(strict_types=1);

use App\Application\Product\Actions\CreateProductAction;
use App\Application\Product\DTOs\CreateProductData;
use App\Application\Product\DTOs\ProductVariantData;
use App\Application\Product\Services\ProductSlugGenerator;
use App\Application\Product\Services\SkuUniquenessGuard;
use App\Domain\Billing\Contracts\PlanLimitGuard;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Events\ProductCreated;
use App\Domain\Product\Exceptions\CategoryNotFoundException;
use App\Domain\Product\Exceptions\DuplicateSkuException;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->products = Mockery::mock(ProductRepositoryInterface::class);
    $this->categories = Mockery::mock(CategoryRepositoryInterface::class);
    $this->events = Mockery::mock(Dispatcher::class);

    $db = Mockery::mock(ConnectionInterface::class);
    $db->shouldReceive('transaction')->andReturnUsing(fn (Closure $callback): mixed => $callback());

    $this->action = new CreateProductAction(
        $this->products,
        $this->categories,
        new ProductSlugGenerator($this->products),
        new SkuUniquenessGuard($this->products),
        $db,
        $this->events,
        Mockery::mock(PlanLimitGuard::class)->shouldIgnoreMissing(),
    );
});

function remeraData(mixed ...$overrides): CreateProductData
{
    return new CreateProductData(...array_merge([
        'name' => 'Remera Oversize',
        'priceAmount' => 1_850_000,
        'status' => ProductStatus::Active,
        'sku' => 'REM-001',
        'variants' => [
            new ProductVariantData(['talle' => 'M', 'color' => 'Negro'], 'REM-001-M'),
            new ProductVariantData(['talle' => 'L', 'color' => 'Negro'], 'REM-001-L', position: 1),
        ],
    ], $overrides));
}

function persistedProduct(): Product
{
    return (new Product)->forceFill([
        'id' => (string) Str::uuid7(),
        'store_id' => (string) Str::uuid7(),
        'name' => 'Remera Oversize',
        'slug' => 'remera-oversize',
    ]);
}

it('creates a product with its variants and dispatches ProductCreated', function (): void {
    $product = persistedProduct();

    $this->products->shouldReceive('existingSkus')
        ->once()->with(['REM-001', 'REM-001-M', 'REM-001-L'], null)->andReturn([]);
    $this->products->shouldReceive('slugExists')
        ->once()->with('remera-oversize', null)->andReturnFalse();
    $this->products->shouldReceive('create')
        ->once()
        ->withArgs(fn (CreateProductData $data): bool => $data->slug === 'remera-oversize'
            && count($data->variants) === 2
            && $data->variants[0]->options === ['talle' => 'M', 'color' => 'Negro'])
        ->andReturn($product);
    $this->events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(fn (mixed $event): bool => $event instanceof ProductCreated
            && $event->productId === $product->id
            && $event->storeId === $product->store_id));

    expect($this->action->execute(remeraData()))->toBe($product);
});

it('appends a numeric suffix when the slug is already taken in the store', function (): void {
    $this->products->shouldReceive('existingSkus')->andReturn([]);
    $this->products->shouldReceive('slugExists')->with('remera-oversize', null)->andReturnTrue();
    $this->products->shouldReceive('slugExists')->with('remera-oversize-2', null)->andReturnTrue();
    $this->products->shouldReceive('slugExists')->with('remera-oversize-3', null)->andReturnFalse();
    $this->products->shouldReceive('create')
        ->once()
        ->withArgs(fn (CreateProductData $data): bool => $data->slug === 'remera-oversize-3')
        ->andReturn(persistedProduct());
    $this->events->shouldReceive('dispatch')->once();

    $this->action->execute(remeraData());
});

it('uses the explicit slug as the base when provided', function (): void {
    $this->products->shouldReceive('existingSkus')->andReturn([]);
    $this->products->shouldReceive('slugExists')->once()->with('remera-negra', null)->andReturnFalse();
    $this->products->shouldReceive('create')
        ->once()
        ->withArgs(fn (CreateProductData $data): bool => $data->slug === 'remera-negra')
        ->andReturn(persistedProduct());
    $this->events->shouldReceive('dispatch')->once();

    $this->action->execute(remeraData(slug: 'Remera Negra'));
});

it('rejects SKUs repeated inside the same payload without hitting the repository', function (): void {
    $this->products->shouldNotReceive('existingSkus');
    $this->products->shouldNotReceive('create');
    $this->events->shouldNotReceive('dispatch');

    $this->action->execute(remeraData(variants: [
        new ProductVariantData(['talle' => 'M'], 'REM-001'),
    ]));
})->throws(DuplicateSkuException::class, 'REM-001');

it('rejects SKUs already used by another product or variant of the store', function (): void {
    $this->products->shouldReceive('existingSkus')->once()->andReturn(['REM-001-M']);
    $this->products->shouldNotReceive('create');
    $this->events->shouldNotReceive('dispatch');

    $this->action->execute(remeraData());
})->throws(DuplicateSkuException::class, 'REM-001-M');

it('rejects a category that does not belong to the current store', function (): void {
    $categoryId = (string) Str::uuid7();

    $this->categories->shouldReceive('exists')->once()->with($categoryId)->andReturnFalse();
    $this->products->shouldNotReceive('create');
    $this->events->shouldNotReceive('dispatch');

    $this->action->execute(remeraData(categoryId: $categoryId));
})->throws(CategoryNotFoundException::class);

it('skips the SKU lookup when no SKUs are given', function (): void {
    $this->products->shouldNotReceive('existingSkus');
    $this->products->shouldReceive('slugExists')->andReturnFalse();
    $this->products->shouldReceive('create')->once()->andReturn(persistedProduct());
    $this->events->shouldReceive('dispatch')->once();

    $this->action->execute(remeraData(sku: null, variants: [
        new ProductVariantData(['talle' => 'M']),
    ]));
});
