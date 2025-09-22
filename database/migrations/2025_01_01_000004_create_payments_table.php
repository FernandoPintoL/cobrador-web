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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cobrador_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('credit_id')->constrained('credits')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->timestamp('payment_date');
            $table->enum('payment_method', ['cash', 'transfer', 'card', 'mobile_payment']);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'partial'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->integer('installment_number')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
