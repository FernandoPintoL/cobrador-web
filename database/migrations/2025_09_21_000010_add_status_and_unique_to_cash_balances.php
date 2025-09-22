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
        Schema::table('cash_balances', function (Blueprint $table) {
            // status: 'open' | 'closed' | 'reconciled'
            $table->string('status')->default('open')->after('final_amount');
            $table->unique(['cobrador_id', 'date'], 'cash_balances_cobrador_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_balances', function (Blueprint $table) {
            $table->dropUnique('cash_balances_cobrador_date_unique');
            $table->dropColumn('status');
        });
    }
};
