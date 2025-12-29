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
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('key'); // Clave de configuración (ej: "allow_custom_interest_per_credit")
            $table->text('value'); // Valor (puede ser JSON, string, boolean, etc)
            $table->string('type')->default('string'); // Tipo: string, boolean, integer, json, decimal
            $table->text('description')->nullable(); // Descripción de qué hace este setting
            $table->timestamps();

            // Un tenant no puede tener dos settings con la misma clave
            $table->unique(['tenant_id', 'key']);

            // Índice para búsquedas rápidas
            $table->index(['tenant_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
