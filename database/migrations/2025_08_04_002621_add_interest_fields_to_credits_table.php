<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->default(0)->after('amount')
                ->comment('Porcentaje de interés (ej: 20.00 para 20%)');
            $table->decimal('total_amount', 10, 2)->nullable()->after('interest_rate')
                ->comment('Monto total con interés incluido');
            $table->decimal('installment_amount', 8, 2)->nullable()->after('balance')
                ->comment('Monto de cada cuota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'total_amount', 'installment_amount']);
        });
    }
};
