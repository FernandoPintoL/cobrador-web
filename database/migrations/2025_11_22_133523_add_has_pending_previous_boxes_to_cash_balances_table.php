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
            $table->boolean('has_pending_previous_boxes')->default(false)->after('requires_reconciliation');
            $table->json('pending_boxes_info')->nullable()->after('has_pending_previous_boxes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_balances', function (Blueprint $table) {
            $table->dropColumn(['has_pending_previous_boxes', 'pending_boxes_info']);
        });
    }
};
