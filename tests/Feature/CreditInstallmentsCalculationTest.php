<?php

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('calculates pending installments correctly with partial and completed payments', function () {
    $current = User::factory()->create();
    Sanctum::actingAs($current);

    $client = User::factory()->create();
    $creator = User::factory()->create();

    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $creator->id,
        'amount' => 1000.00,
        'interest_rate' => 0,
        'total_amount' => 1000.00,
        'installment_amount' => 250.00,
        'total_installments' => 4,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'status' => 'active',
    ]);

    // Cuota 1: 100 (parcial) + 150 (completa)
    Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $creator->id,
        'credit_id' => $credit->id,
        'amount' => 100.00,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'status' => 'partial',
        'installment_number' => 1,
    ]);
    Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $creator->id,
        'credit_id' => $credit->id,
        'amount' => 150.00,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'status' => 'completed',
        'installment_number' => 1,
    ]);

    // Cuota 2: 200 (parcial)
    Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $creator->id,
        'credit_id' => $credit->id,
        'amount' => 200.00,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'status' => 'partial',
        'installment_number' => 2,
    ]);

    expect($credit->getCompletedInstallmentsCount())->toBe(1)
        ->and($credit->getPendingInstallments())->toBe(3)
        ->and($credit->getRemainingInstallments())->toBe(3);
});
