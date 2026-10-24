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
        Schema::create('categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Destino de las FKs compuestas (id, store_id): garantiza a nivel DB
            // que nada referencie una categoría de otra tienda.
            $table->unique(['id', 'store_id']);
            $table->index(['store_id', 'parent_id', 'position']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE categories
                ADD CONSTRAINT categories_parent_same_store_fk
                FOREIGN KEY (parent_id, store_id) REFERENCES categories (id, store_id)
                ON DELETE SET NULL (parent_id)
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX categories_store_slug_unique
                ON categories (store_id, slug) WHERE deleted_at IS NULL
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
