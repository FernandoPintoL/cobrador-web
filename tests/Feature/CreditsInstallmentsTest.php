<?php

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createRole(string $name): Role
{
    return Role::findOrCreate($name);
}

it('returns correct paid and remaining installments in index and show', function () {
    // Create roles
    createRole('admin');
    createRole('client');

    // Create users
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create();
    $client->assignRole('client');

    // Authenticate as admin (Sanctum guard)
    Sanctum::actingAs($admin);

    // Create a credit with 10 total installments
    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'amount' => 1000,
        'interest_rate' => 0,
        'total_amount' => 1000,
        'balance' => 1000,
        'installment_amount' => 100,
        'total_installments' => 10,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'status' => 'active',
    ]);

    // Create 3 completed payments
    for ($i = 1; $i <= 3; $i++) {
        Payment::create([
            'client_id' => $client->id,
            'cobrador_id' => $admin->id,
            'credit_id' => $credit->id,
            'amount' => 100,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'status' => 'completed',
            'installment_number' => $i,
            'received_by' => $admin->id,
        ]);
    }

    // Index endpoint
    $indexResponse = $this->getJson('/api/credits');
    $indexResponse->assertSuccessful();

    $indexData = $indexResponse->json('data');
    expect($indexData)->not()->toBeNull();

    // Paginator returns data under data.data
    $first = data_get($indexResponse->json(), 'data.data.0');
    expect($first)->not()->toBeNull();

    expect(data_get($first, 'completed_installments_count'))->toBe(3);
    expect(data_get($first, 'pending_installments'))->toBe(7);

    // Show endpoint
    $showResponse = $this->getJson("/api/credits/{$credit->id}");
    $showResponse->assertSuccessful();

    $show = $showResponse->json('data');
    expect(data_get($show, 'completed_installments_count'))->toBe(3);
    expect(data_get($show, 'pending_installments'))->toBe(7);
});
