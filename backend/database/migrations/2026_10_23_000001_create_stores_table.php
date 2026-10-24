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
        Schema::create('stores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 63)->unique(); // subdominio: un label DNS admite hasta 63 caracteres
            $table->string('custom_domain')->nullable()->unique();
            $table->string('logo_path')->nullable();
            $table->char('primary_color', 7)->default('#18181B');
            $table->char('accent_color', 7)->default('#2563EB');
            $table->string('whatsapp_number', 20)->nullable(); // formato E.164: +5491122334455
            $table->text('whatsapp_message_template')->nullable();
            $table->char('currency', 3)->default('ARS');
            $table->string('locale', 10)->default('es-AR');
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->jsonb('settings')->default('{}');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE stores
                ADD CONSTRAINT stores_slug_format_check
                    CHECK (slug ~ '^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$'),
                ADD CONSTRAINT stores_colors_hex_check
                    CHECK (primary_color ~ '^#[0-9A-Fa-f]{6}$' AND accent_color ~ '^#[0-9A-Fa-f]{6}$'),
                ADD CONSTRAINT stores_whatsapp_e164_check
                    CHECK (whatsapp_number IS NULL OR whatsapp_number ~ '^\+[1-9][0-9]{7,14}$')
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
