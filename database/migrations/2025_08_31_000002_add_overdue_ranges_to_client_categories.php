<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_categories', function (Blueprint $table) {
            $table->unsignedInteger('min_overdue_count')->nullable()->after('is_active');
            $table->unsignedInteger('max_overdue_count')->nullable()->after('min_overdue_count');
        });

        // Definir rangos por defecto: A=0, B=1-3, C=4+
        try {
            DB::table('client_categories')->where('code', 'A')->update([
                'min_overdue_count' => 0,
                'max_overdue_count' => 0,
                'updated_at' => now(),
            ]);
            DB::table('client_categories')->where('code', 'B')->update([
                'min_overdue_count' => 1,
                'max_overdue_count' => 3,
                'updated_at' => now(),
            ]);
            DB::table('client_categories')->where('code', 'C')->update([
                'min_overdue_count' => 4,
                'max_overdue_count' => null,
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Ignorar si la tabla no existe aún en algún entorno
        }
    }

    public function down(): void
    {
        Schema::table('client_categories', function (Blueprint $table) {
            $table->dropColumn(['min_overdue_count', 'max_overdue_count']);
        });
    }
};
