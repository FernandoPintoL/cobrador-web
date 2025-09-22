<?php

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function ensureRole(string $name): Role
{
    return Role::findOrCreate($name);
}

it('returns correct paid and pending installments in credit details', function () {
    // Roles
    ensureRole('admin');
    ensureRole('client');

    // Users
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create();
    $client->assignRole('client');

    // Auth
    Sanctum::actingAs($admin);

    // Credit with 10 installments of 100
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

    // Three completed installments (1,2,3)
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

    $response = $this->getJson("/api/credits/{$credit->id}/details");
    $response->assertSuccessful();

    $summary = data_get($response->json(), 'data.summary');

    expect(data_get($summary, 'completed_installments_count'))->toBe(3);
    expect(data_get($summary, 'pending_installments'))->toBe(7);

    // Legacy fields related to installments should not be present
    expect(data_get($summary, 'expected_installments'))->toBeNull();
    expect(data_get($summary, 'overdue_amount'))->toBeNull();
    expect(data_get($summary, 'is_overdue'))->toBeNull();
});
