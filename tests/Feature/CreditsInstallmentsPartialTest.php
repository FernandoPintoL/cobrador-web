<?php

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createRoleIfMissing(string $name): Role
{
    return Role::findOrCreate($name);
}

it('distinguishes between payments count and completed installments count', function () {
    // Roles
    createRoleIfMissing('admin');
    createRoleIfMissing('client');

    // Users
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create();
    $client->assignRole('client');

    // Auth
    Sanctum::actingAs($admin);

    // Credit with 5 installments of 100
    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'amount' => 500,
        'interest_rate' => 0,
        'total_amount' => 500,
        'balance' => 500,
        'installment_amount' => 100,
        'total_installments' => 5,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => 'active',
    ]);

    // Two partial payments for installment 1 (50 + 50)
    Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $admin->id,
        'credit_id' => $credit->id,
        'amount' => 50,
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
        'amount' => 50,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'status' => 'completed',
        'installment_number' => 1,
        'received_by' => $admin->id,
    ]);

    // One exact payment for installment 2 (100)
    Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $admin->id,
        'credit_id' => $credit->id,
        'amount' => 100,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'status' => 'completed',
        'installment_number' => 2,
        'received_by' => $admin->id,
    ]);

    // Index
    $indexResponse = $this->getJson('/api/credits');
    $indexResponse->assertSuccessful();
    $first = data_get($indexResponse->json(), 'data.data.0');

    expect(data_get($first, 'completed_installments_count'))->toBe(2);
    expect(data_get($first, 'pending_installments'))->toBe(3);

    // Show
    $showResponse = $this->getJson("/api/credits/{$credit->id}");
    $showResponse->assertSuccessful();
    $show = $showResponse->json('data');

    expect(data_get($show, 'completed_installments_count'))->toBe(2);
    expect(data_get($show, 'pending_installments'))->toBe(3);
});
