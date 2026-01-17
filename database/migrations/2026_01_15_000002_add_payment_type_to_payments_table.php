<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campo para identificar el tipo de pago:
     * - regular: Pago normal de cuota
     * - down_payment: Anticipo inicial del crédito
     * - extra: Pago adicional/adelanto
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('payment_type', ['regular', 'down_payment', 'extra'])
                ->default('regular')
                ->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
};
