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
        Schema::table('users', function (Blueprint $table): void {
            // null = invitado que todavía no eligió su contraseña (no puede entrar).
            $table->timestamp('invitation_accepted_at')->nullable()->after('must_change_password');
        });

        // Quienes ya existen tienen contraseña: cuentan como aceptados.
        DB::table('users')->update(['invitation_accepted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('invitation_accepted_at');
        });
    }
};
