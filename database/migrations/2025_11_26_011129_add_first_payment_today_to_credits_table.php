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
            // Agregar campo para indicar si el primer pago se hizo el mismo día de entrega
            // o si el cronograma inicia al día siguiente
            $table->boolean('first_payment_today')->default(false)->after('delivered_at');
        });

        // Actualizar datos existentes: por defecto asumimos que los créditos
        // existentes tienen el cronograma iniciando al día siguiente (comportamiento anterior)
        DB::table('credits')
            ->where('status', 'active')
            ->whereNull('first_payment_today')
            ->update(['first_payment_today' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('first_payment_today');
        });
    }
};
