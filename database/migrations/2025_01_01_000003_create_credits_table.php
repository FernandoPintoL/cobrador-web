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
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance', 15, 2);
            $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly']);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('pending_approval')->comment("Estados: pending_approval, waiting_delivery, active, paid_off, defaulted, cancelled");
            $table->decimal('interest_rate', 5, 2)->default(0)->after('amount')->comment('Porcentaje de interés (ej: 20.00 para 20%)');
            $table->decimal('total_amount', 10, 2)->nullable()->after('interest_rate')->comment('Monto total con interés incluido');
            $table->decimal('installment_amount', 8, 2)->nullable()->after('balance')->comment('Monto de cada cuota');
            // Fecha programada para la entrega del crédito
            $table->datetime('scheduled_delivery_date')->nullable()->after('end_date');
            // Usuario que aprobó la entrega (cobrador o manager)
            $table->foreignId('approved_by')->nullable()->constrained('users');
            // Fecha cuando se aprobó para entrega
            $table->datetime('approved_at')->nullable()->after('approved_by');
            // Fecha real de entrega al cliente
            $table->datetime('delivered_at')->nullable()->after('approved_at');
            // Usuario que entregó el crédito al cliente
            $table->foreignId('delivered_by')->nullable()->constrained('users');
            // Notas sobre la aprobación o entrega
            $table->text('delivery_notes')->nullable()->after('delivered_by');
            // Motivo de rechazo si aplica
            $table->text('rejection_reason')->nullable()->after('delivery_notes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
