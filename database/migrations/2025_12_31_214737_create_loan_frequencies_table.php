<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla para configurar las frecuencias de pago disponibles por tenant.
     * Permite centralizar las reglas de negocio para cada tipo de frecuencia.
     */
    public function up(): void
    {
        Schema::create('loan_frequencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Identificación
            $table->string('code', 20); // 'daily', 'weekly', 'biweekly', 'monthly'
            $table->string('name', 50); // 'Diario', 'Semanal', 'Quincenal', 'Mensual'
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);

            // Configuración de duración fija (para frecuencia diaria)
            $table->boolean('is_fixed_duration')->default(false);
            $table->integer('fixed_installments')->nullable(); // 24 para diaria
            $table->integer('fixed_duration_days')->nullable(); // 28 para diaria

            // Configuración flexible (para otras frecuencias)
            $table->integer('period_days'); // 1=diario, 7=semanal, 15=quincenal, 30=mensual
            $table->integer('default_installments')->nullable(); // Cuotas sugeridas por defecto
            $table->integer('min_installments')->nullable(); // Mínimo de cuotas permitidas
            $table->integer('max_installments')->nullable(); // Máximo de cuotas permitidas

            // Configuración de interés (opcional, si varía por frecuencia)
            $table->decimal('interest_rate', 5, 2)->nullable();

            $table->timestamps();

            // Índices
            $table->unique(['tenant_id', 'code']); // Un solo registro por frecuencia por tenant
            $table->index('is_enabled'); // Para queries de frecuencias activas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_frequencies');
    }
};
