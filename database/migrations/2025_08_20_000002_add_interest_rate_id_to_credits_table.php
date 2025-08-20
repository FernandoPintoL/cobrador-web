<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->foreignId('interest_rate_id')->nullable()->after('amount')->constrained('interest_rates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interest_rate_id');
        });
    }
};
