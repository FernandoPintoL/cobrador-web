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
        Schema::table('client_categories', function (Blueprint $table) {
            $table->decimal('max_amount', 12, 2)->nullable()->after('description')
                ->comment('Monto máximo permitido por crédito');
            $table->decimal('min_amount', 12, 2)->default(0)->after('max_amount')
                ->comment('Monto mínimo permitido por crédito');
            $table->unsignedInteger('max_credits')->nullable()->after('min_amount')
                ->comment('Máximo de créditos activos permitidos');
        });

        // Establecer valores por defecto para categorías existentes
        DB::table('client_categories')->where('code', 'A')->update([
            'max_amount' => 10000,
            'min_amount' => 0,
            'max_credits' => 5,
        ]);
        DB::table('client_categories')->where('code', 'B')->update([
            'max_amount' => 5000,
            'min_amount' => 0,
            'max_credits' => 3,
        ]);
        DB::table('client_categories')->where('code', 'C')->update([
            'max_amount' => 0,
            'min_amount' => 0,
            'max_credits' => 0,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_categories', function (Blueprint $table) {
            $table->dropColumn(['max_amount', 'min_amount', 'max_credits']);
        });
    }
};
