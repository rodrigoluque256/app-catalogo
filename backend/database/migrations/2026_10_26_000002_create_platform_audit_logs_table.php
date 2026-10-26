<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            // Sin FK: el registro tiene que sobrevivir al borrado definitivo de la tienda.
            $table->uuid('store_id')->nullable()->index();
            $table->jsonb('metadata')->default('{}');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
    }
};
