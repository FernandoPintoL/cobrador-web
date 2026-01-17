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
        Schema::table('users', function (Blueprint $table) {
            // Límites personalizados que sobreescriben los de la categoría
            $table->decimal('credit_limit_override', 12, 2)->nullable()->after('client_category')
                ->comment('Límite de monto personalizado (sobreescribe el de categoría)');
            $table->unsignedInteger('max_credits_override')->nullable()->after('credit_limit_override')
                ->comment('Máximo de créditos personalizado (sobreescribe el de categoría)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['credit_limit_override', 'max_credits_override']);
        });
    }
};
