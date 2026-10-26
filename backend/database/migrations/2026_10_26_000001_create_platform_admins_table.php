<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Administradores de la plataforma (super panel). Tabla aparte de `users`: un usuario de tienda
        // nunca puede volverse super admin por un dato mal cargado.
        Schema::create('platform_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->string('password');
            $table->text('two_factor_secret')->nullable(); // cifrado con APP_KEY
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable(); // cifrado: JSON de hashes SHA-256
            $table->unsignedBigInteger('two_factor_last_step')->nullable(); // evita reutilizar un código TOTP
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admins');
    }
};
