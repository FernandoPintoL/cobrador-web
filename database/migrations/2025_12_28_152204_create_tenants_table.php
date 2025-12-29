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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre de la empresa
            $table->string('slug')->unique(); // Identificador único (ej: "empresa-abc")
            $table->string('logo')->nullable(); // Ruta del logo
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'suspended', 'trial'])->default('trial');
            $table->date('trial_ends_at')->nullable(); // Fecha fin del período de prueba (1 mes gratis)
            $table->decimal('monthly_price', 10, 2)->default(0); // Precio mensual a cobrar
            $table->json('settings')->nullable(); // Configuraciones extra en JSON
            $table->timestamps();
            $table->softDeletes(); // Para borrado lógico

            // Índices para mejorar performance
            $table->index('status');
            $table->index('trial_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
