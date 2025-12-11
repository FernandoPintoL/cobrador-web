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
            // Agregar campo completed_at después de delivered_at
            // Nullable porque créditos existentes no tienen este valor
            $table->timestamp('completed_at')
                ->nullable()
                ->after('delivered_at')
                ->comment('Fecha y hora en que el crédito fue completado (balance = 0)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
