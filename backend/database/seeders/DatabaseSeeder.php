<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Product\Actions\CreateProductAction;
use App\Application\Product\DTOs\CreateProductData;
use App\Application\Product\DTOs\ProductVariantData;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\Category;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(TenantContext $tenant, CreateProductAction $createProduct): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Super admin de desarrollo, sin 2FA: el primer ingreso a /platform obliga a configurarlo.
        PlatformAdmin::factory()->create(['name' => 'Admin Plataforma', 'email' => 'admin@plataforma.test']);

        // Intermediate: 3 usuarios, los que necesita el e2e para crear y borrar una editora.
        $store = Store::factory()->onPlan('intermediate')->create([
            'name' => 'Tienda Demo',
            'slug' => 'demo',
            'whatsapp_number' => '+5491100000000',
        ]);

        $owner = User::factory()->forStore($store)->create([
            'name' => 'Owner Demo',
            'email' => 'owner@demo.test',
        ]);
        setPermissionsTeamId($store->id);
        $owner->assignRole('owner');

        $tenant->runAs($store, function () use ($store, $createProduct): void {
            $remeras = Category::factory()->forStore($store)->create(['name' => 'Remeras', 'slug' => 'remeras']);
            Category::factory()->forStore($store)->create(['name' => 'Pantalones', 'slug' => 'pantalones', 'position' => 1]);

            // Se usa la Action real: el seed pasa por las mismas reglas que la API.
            $createProduct->execute(new CreateProductData(
                name: 'Remera Oversize Algodón',
                priceAmount: 1_850_000,
                status: ProductStatus::Active,
                description: 'Remera de algodón peinado 24/1, calce oversize.',
                categoryId: $remeras->id,
                sku: 'REM-OVS',
                isFeatured: true,
                variants: [
                    new ProductVariantData(['talle' => 'S', 'color' => 'Negro'], 'REM-OVS-S-NEG', position: 0),
                    new ProductVariantData(['talle' => 'M', 'color' => 'Negro'], 'REM-OVS-M-NEG', position: 1),
                    new ProductVariantData(['talle' => 'L', 'color' => 'Blanco'], 'REM-OVS-L-BLA', stock: 0, position: 2),
                ],
            ));
        });
    }
}
