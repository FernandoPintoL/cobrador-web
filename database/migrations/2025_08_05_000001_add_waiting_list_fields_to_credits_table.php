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
            // Agregar nuevos campos sin modificar el enum todavía
            
            // Fecha programada para la entrega del crédito
            $table->datetime('scheduled_delivery_date')->nullable()->after('end_date');
            
            // Usuario que aprobó la entrega (cobrador o manager)
            $table->foreignId('approved_by')->nullable()->constrained('users')->after('created_by');
            
            // Fecha cuando se aprobó para entrega
            $table->datetime('approved_at')->nullable()->after('approved_by');
            
            // Fecha real de entrega al cliente
            $table->datetime('delivered_at')->nullable()->after('approved_at');
            
            // Usuario que entregó el crédito al cliente
            $table->foreignId('delivered_by')->nullable()->constrained('users')->after('delivered_at');
            
            // Notas sobre la aprobación o entrega
            $table->text('delivery_notes')->nullable()->after('delivered_by');
            
            // Motivo de rechazo si aplica
            $table->text('rejection_reason')->nullable()->after('delivery_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['delivered_by']);
            $table->dropColumn([
                'scheduled_delivery_date',
                'approved_by',
                'approved_at',
                'delivered_at',
                'delivered_by',
                'delivery_notes',
                'rejection_reason'
            ]);
        });
    }
};
