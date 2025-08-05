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
        // Eliminar la restricción check existente en status
        DB::statement('ALTER TABLE credits DROP CONSTRAINT IF EXISTS credits_status_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recrear la restricción check original
        DB::statement("ALTER TABLE credits ADD CONSTRAINT credits_status_check CHECK (status::text = ANY (ARRAY['active'::character varying, 'completed'::character varying, 'defaulted'::character varying, 'cancelled'::character varying]::text[]))");
    }
};
