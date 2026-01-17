<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campos para el modo de crédito personalizado:
     * - description: Concepto/descripción del crédito (ej: "Colchón 2 plazas")
     * - down_payment: Anticipo que deja el cliente antes de iniciar el crédito
     * - is_custom_credit: Flag para identificar créditos creados en modo personalizado
     *
     * Fórmula del crédito personalizado:
     * monto_financiado = amount - down_payment
     * total_amount = monto_financiado × (1 + interest_rate/100)
     * balance = total_amount (al inicio)
     * cuota = total_amount / total_installments
     */
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            // Descripción/concepto del crédito (ej: "Colchón", "Celular Samsung", etc.)
            $table->string('description')->nullable()->after('tenant_id');

            // Anticipo que deja el cliente (se resta del monto antes de calcular intereses)
            $table->decimal('down_payment', 15, 2)->default(0)->after('amount');

            // Flag para identificar créditos creados en modo personalizado
            $table->boolean('is_custom_credit')->default(false)->after('first_payment_today');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn(['description', 'down_payment', 'is_custom_credit']);
        });
    }
};
