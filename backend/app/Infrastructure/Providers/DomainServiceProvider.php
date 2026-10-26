<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Storefront\Listeners\RevalidateStorefront;
use App\Domain\Platform\Repositories\PlatformStoreRepositoryInterface;
use App\Domain\Product\Events\CategoriesChanged;
use App\Domain\Product\Events\ProductCreated;
use App\Domain\Product\Events\ProductDeleted;
use App\Domain\Product\Events\ProductUpdated;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Contracts\StorefrontRevalidator;
use App\Domain\Store\Contracts\UploadedObjectInspector;
use App\Domain\Store\Contracts\UploadUrlSigner;
use App\Domain\Store\Events\StoreAvailabilityChanged;
use App\Domain\Store\Events\StoreSettingsUpdated;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use App\Domain\Store\Repositories\StoreUserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentCategoryRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentPlatformStoreRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentProductRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentStoreRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentStoreUserRepository;
use App\Infrastructure\Storefront\NextJsStorefrontRevalidator;
use App\Infrastructure\Storefront\R2UploadedObjectInspector;
use App\Infrastructure\Storefront\R2UploadUrlSigner;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        StoreRepositoryInterface::class => EloquentStoreRepository::class,
        PlatformStoreRepositoryInterface::class => EloquentPlatformStoreRepository::class,
        StoreUserRepositoryInterface::class => EloquentStoreUserRepository::class,
        ProductRepositoryInterface::class => EloquentProductRepository::class,
        CategoryRepositoryInterface::class => EloquentCategoryRepository::class,
        UploadUrlSigner::class => R2UploadUrlSigner::class,
        UploadedObjectInspector::class => R2UploadedObjectInspector::class,
    ];

    public function register(): void
    {
        // scoped: una instancia por request/job, se descarta entre requests en Octane y workers.
        $this->app->scoped(TenantContext::class);

        $this->app->bind(StorefrontRevalidator::class, fn ($app): NextJsStorefrontRevalidator => new NextJsStorefrontRevalidator(
            $app->make(Http::class),
            config('services.storefront.revalidate_url'),
            config('services.storefront.revalidate_secret'),
        ));
    }

    public function boot(): void
    {
        foreach ([ProductCreated::class, ProductUpdated::class, ProductDeleted::class, CategoriesChanged::class, StoreSettingsUpdated::class, StoreAvailabilityChanged::class] as $event) {
            Event::listen($event, RevalidateStorefront::class);
        }
    }
}
