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
        Schema::table('cash_balances', function (Blueprint $table) {
            // Campo para indicar si fue cerrada automáticamente
            $table->timestamp('auto_closed_at')->nullable()->after('status');

            // Campo para indicar si fue cerrada manualmente
            $table->timestamp('manually_closed_at')->nullable()->after('auto_closed_at');

            // Usuario que cerró manualmente (si aplica)
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null')->after('manually_closed_at');

            // Notas del cierre (manual o automático)
            $table->text('closure_notes')->nullable()->after('closed_by');

            // Flag para indicar si requiere conciliación
            $table->boolean('requires_reconciliation')->default(false)->after('closure_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_balances', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropColumn([
                'auto_closed_at',
                'manually_closed_at',
                'closed_by',
                'closure_notes',
                'requires_reconciliation',
            ]);
        });
    }
};
