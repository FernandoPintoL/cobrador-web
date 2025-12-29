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
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->decimal('amount', 10, 2); // Monto cobrado este mes
            $table->date('period_start'); // Inicio del período (ej: 2025-01-01)
            $table->date('period_end'); // Fin del período (ej: 2025-01-31)
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->date('paid_at')->nullable(); // Fecha de pago
            $table->string('payment_method')->nullable(); // "efectivo", "transferencia", "tarjeta", etc
            $table->text('notes')->nullable(); // Notas adicionales
            $table->timestamps();

            // Índices para búsquedas y reportes
            $table->index(['tenant_id', 'status']);
            $table->index('period_start');
            $table->index('period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};
