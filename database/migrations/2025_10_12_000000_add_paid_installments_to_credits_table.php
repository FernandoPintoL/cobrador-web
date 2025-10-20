<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            // Añadir campo para rastrear cuotas pagadas
            $table->integer('paid_installments')->default(0)->after('total_installments');
        });

        // Actualizar los registros existentes con la cantidad correcta de cuotas pagadas
        DB::statement('
            UPDATE credits SET paid_installments = (
                SELECT COUNT(DISTINCT installment_number)
                FROM payments
                WHERE payments.credit_id = credits.id
                AND payments.status = \'completed\'
                AND payments.installment_number IS NOT NULL
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('paid_installments');
        });
    }
};
