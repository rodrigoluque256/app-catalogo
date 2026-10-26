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
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 60);
            $table->unsignedInteger('monthly_price'); // centavos ARS
            $table->unsignedInteger('annual_price'); // centavos ARS: mensual × 12 × 0,6 (anual −40 %)
            $table->unsignedInteger('max_products')->nullable(); // null = sin límite
            $table->unsignedSmallInteger('max_images_per_product');
            $table->unsignedSmallInteger('max_users');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Datos de referencia: van en la migración para que producción los tenga con `migrate` (sin seeder).
        $now = now();
        $plans = [
            ['free', 'Gratis', 0, 25, 3, 1],
            ['basic', 'Basic', 1_000_000, 250, 5, 2],
            ['intermediate', 'Intermediate', 1_900_000, 500, 10, 3],
            ['advanced', 'Advanced', 3_600_000, 1_000, 15, 5],
            ['unlimited', 'Unlimited', 7_200_000, null, 20, 20],
        ];

        DB::table('plans')->insert(array_map(fn (array $plan, int $position): array => [
            'code' => $plan[0],
            'name' => $plan[1],
            'monthly_price' => $plan[2],
            'annual_price' => intdiv($plan[2] * 12 * 6, 10),
            'max_products' => $plan[3],
            'max_images_per_product' => $plan[4],
            'max_users' => $plan[5],
            'position' => $position,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $plans, array_keys($plans)));
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
