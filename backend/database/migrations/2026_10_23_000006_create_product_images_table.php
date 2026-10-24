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
        Schema::create('product_images', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable(); // imagen específica de un color, por ejemplo
            $table->string('disk', 32)->default('r2');
            $table->string('path', 500); // key del objeto en Cloudflare R2
            $table->string('alt_text', 160)->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign(['product_id', 'store_id'])
                ->references(['id', 'store_id'])->on('products')
                ->cascadeOnDelete();

            $table->index(['product_id', 'position']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE product_images
                ADD CONSTRAINT product_images_variant_same_store_fk
                FOREIGN KEY (variant_id, store_id) REFERENCES product_variants (id, store_id)
                ON DELETE SET NULL (variant_id)
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX product_images_one_primary_per_product
                ON product_images (product_id) WHERE is_primary
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
