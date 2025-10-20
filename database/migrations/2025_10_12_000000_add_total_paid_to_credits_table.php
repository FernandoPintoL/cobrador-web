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
            $table->decimal('total_paid', 10, 2)->default(0.00)->after('balance');
        });

        // Actualizar los valores existentes calculando la suma de pagos completados
        DB::statement("
            UPDATE credits
            SET total_paid = (
                SELECT COALESCE(SUM(amount), 0)
                FROM payments
                WHERE payments.credit_id = credits.id
                AND status = 'completed'
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('total_paid');
        });
    }
};
