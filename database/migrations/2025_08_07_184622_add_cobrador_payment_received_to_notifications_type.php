<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Eliminar la restricción CHECK existente
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_type_check');

        // Crear nueva restricción CHECK que incluye el nuevo tipo
        DB::statement("
            ALTER TABLE notifications
            ADD CONSTRAINT notifications_type_check
            CHECK (type::text = ANY (ARRAY[
                'payment_received'::character varying,
                'payment_due'::character varying,
                'credit_approved'::character varying,
                'credit_rejected'::character varying,
                'system_alert'::character varying,
                'cobrador_payment_received'::character varying
            ]::text[]))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar la restricción CHECK modificada
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_type_check');

        // Eliminar o actualizar filas con valores no permitidos
        DB::table('notifications')
            ->where('type', 'cobrador_payment_received')
            ->delete(); // O puedes usar ->update(['type' => 'payment_received']) si deseas conservar las filas.

        // Restaurar la restricción CHECK original sin 'cobrador_payment_received'
        DB::statement("
            ALTER TABLE notifications
            ADD CONSTRAINT notifications_type_check
            CHECK (type::text = ANY (ARRAY[
                'payment_received'::character varying,
                'payment_due'::character varying,
                'credit_approved'::character varying,
                'credit_rejected'::character varying,
                'system_alert'::character varying
            ]::text[]))
        ");
    }
};
