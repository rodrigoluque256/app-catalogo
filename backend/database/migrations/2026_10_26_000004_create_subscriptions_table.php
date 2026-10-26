<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Suscripción vigente de cada tienda (una por tienda). El historial de pagos llega con Mercado Pago (P2.6).
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('billing_cycle', ['monthly', 'annual'])->nullable(); // null en el plan Gratis
            $table->enum('status', ['active', 'past_due', 'canceled'])->default('active');
            $table->timestamp('current_period_end')->nullable();
            $table->string('mercadopago_preapproval_id')->nullable()->unique();
            $table->timestamps();
        });

        // Las tiendas que ya existen arrancan en Gratis.
        $free = DB::table('plans')->where('code', 'free')->value('id');
        $now = now();

        foreach (DB::table('stores')->pluck('id') as $storeId) {
            DB::table('subscriptions')->insert([
                'id' => (string) Str::uuid7(),
                'store_id' => $storeId,
                'plan_id' => $free,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
