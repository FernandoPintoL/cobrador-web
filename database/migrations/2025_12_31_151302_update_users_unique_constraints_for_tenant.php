<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cambia los constraints UNIQUE de ci y phone para que sean por tenant.
     * Esto permite que un mismo cliente (mismo CI) pueda estar en diferentes empresas,
     * pero NO permite duplicados dentro de la misma empresa.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1. Eliminar constraints unique individuales
            $table->dropUnique(['ci']);
            $table->dropUnique(['phone']);

            // 2. Crear indices unique compuestos (ci + tenant_id, phone + tenant_id)
            // Esto permite el mismo CI en diferentes tenants, pero NO duplicados en el mismo tenant
            $table->unique(['ci', 'tenant_id'], 'users_ci_tenant_unique');
            $table->unique(['phone', 'tenant_id'], 'users_phone_tenant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revertir cambios
            $table->dropUnique('users_ci_tenant_unique');
            $table->dropUnique('users_phone_tenant_unique');

            // Restaurar constraints individuales (puede fallar si hay duplicados)
            $table->unique('ci');
            $table->unique('phone');
        });
    }
};
