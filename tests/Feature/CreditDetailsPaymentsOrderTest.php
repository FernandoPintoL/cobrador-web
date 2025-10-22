<?php

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('orders payments_history by installment_number ascending in credit details endpoint', function () {
    ensureRole('admin');
    ensureRole('client');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create();
    $client->assignRole('client');

    Sanctum::actingAs($admin);

    // Create a credit with 3 installments
    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'amount' => 300,
        'interest_rate' => 0,
        'total_amount' => 300,
        'balance' => 300,
        'installment_amount' => 100,
        'total_installments' => 3,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
        'status' => 'active',
    ]);

    // Create payments intentionally out of chronological order
    Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $admin->id,
        'credit_id' => $credit->id,
        'amount' => 100,
        'payment_date' => now()->subDay(),
        'payment_method' => 'cash',
        'status' => 'completed',
        'installment_number' => 2,
        'received_by' => $admin->id,
    ]);

    Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $admin->id,
        'credit_id' => $credit->id,
        'amount' => 100,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'status' => 'completed',
        'installment_number' => 1,
        'received_by' => $admin->id,
    ]);

    Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $admin->id,
        'credit_id' => $credit->id,
        'amount' => 100,
        'payment_date' => now()->addDay(),
        'payment_method' => 'cash',
        'status' => 'completed',
        'installment_number' => 3,
        'received_by' => $admin->id,
    ]);

    $resp = $this->getJson("/api/credits/{$credit->id}/details");
    $resp->assertSuccessful();

    $history = data_get($resp->json(), 'data.payments_history');
    expect($history)->toHaveCount(3);

    // Verify order is by installment_number: 1, 2, 3
    $numbers = array_map(fn ($p) => (int) data_get($p, 'installment_number'), $history);
    expect($numbers)->toBe([1, 2, 3]);

    // Also verify summary still present
    $summary = data_get($resp->json(), 'data.summary');
    expect($summary)->not()->toBeNull();
    expect(data_get($summary, 'completed_installments_count'))->toBe(3);
    expect(data_get($summary, 'pending_installments'))->toBe(0);
});
