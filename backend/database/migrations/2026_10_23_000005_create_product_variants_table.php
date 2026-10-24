<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('store_id'); // desnormalizado: permite scope por tenant y FK compuesta
            $table->uuid('product_id');
            $table->string('sku', 64)->nullable();
            $table->jsonb('options'); // {"talle": "M", "color": "Rojo"}
            $table->bigInteger('price_amount')->nullable(); // null = hereda el precio del producto
            $table->integer('stock')->nullable(); // null = sin control de stock (modo catálogo)
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign(['product_id', 'store_id'])
                ->references(['id', 'store_id'])->on('products')
                ->cascadeOnDelete();

            $table->unique(['id', 'store_id']);
            $table->unique(['product_id', 'options']);
            $table->index(['product_id', 'position']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE product_variants
                ADD CONSTRAINT product_variants_options_object_check
                    CHECK (jsonb_typeof(options) = 'object'),
                ADD CONSTRAINT product_variants_price_check
                    CHECK (price_amount IS NULL OR price_amount >= 0),
                ADD CONSTRAINT product_variants_stock_check
                    CHECK (stock IS NULL OR stock >= 0)
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX product_variants_store_sku_unique
                ON product_variants (store_id, sku) WHERE sku IS NOT NULL
            SQL);

        // Filtros del storefront tipo options @> '{"talle":"M"}'.
        DB::statement('CREATE INDEX product_variants_options_idx ON product_variants USING GIN (options jsonb_path_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
