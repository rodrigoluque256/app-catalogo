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
        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->uuid('category_id')->nullable();
            $table->string('name', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->string('sku', 64)->nullable();
            $table->bigInteger('price_amount'); // centavos: evita errores de punto flotante
            $table->bigInteger('compare_at_amount')->nullable(); // precio "antes" para mostrar descuento
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'store_id']);
            $table->index(['store_id', 'status', 'published_at']);
            $table->index(['store_id', 'category_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE products
                ADD CONSTRAINT products_category_same_store_fk
                    FOREIGN KEY (category_id, store_id) REFERENCES categories (id, store_id)
                    ON DELETE SET NULL (category_id),
                ADD CONSTRAINT products_price_check
                    CHECK (price_amount >= 0),
                ADD CONSTRAINT products_compare_at_check
                    CHECK (compare_at_amount IS NULL OR compare_at_amount > price_amount)
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX products_store_slug_unique
                ON products (store_id, slug) WHERE deleted_at IS NULL
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX products_store_sku_unique
                ON products (store_id, sku) WHERE sku IS NOT NULL AND deleted_at IS NULL
            SQL);

        // Búsqueda full-text en español mantenida por Postgres (sin triggers ni jobs).
        DB::statement(<<<'SQL'
            ALTER TABLE products ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (
                    setweight(to_tsvector('spanish', coalesce(name, '')), 'A') ||
                    setweight(to_tsvector('spanish', coalesce(description, '')), 'B')
                ) STORED
            SQL);

        DB::statement('CREATE INDEX products_search_vector_idx ON products USING GIN (search_vector)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
