<?php

use App\Models\CashBalance;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function ensureRoleExists(string $name): Role
{
    return Role::findOrCreate($name);
}

it('requires an open cash box for cobrador when delivering a credit', function () {
    // Roles
    ensureRoleExists('cobrador');
    ensureRoleExists('client');

    // Users
    $cobrador = User::factory()->create();
    $cobrador->assignRole('cobrador');

    $client = User::factory()->create([
        'assigned_cobrador_id' => $cobrador->id,
    ]);
    $client->assignRole('client');

    // Auth as cobrador
    Sanctum::actingAs($cobrador);

    // Credit waiting for delivery
    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $cobrador->id,
        'amount' => 500,
        'interest_rate' => 0,
        'total_amount' => 500,
        'balance' => 500,
        'installment_amount' => 25,
        'total_installments' => 20,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(20)->toDateString(),
        'status' => 'waiting_delivery',
        'scheduled_delivery_date' => now(),
    ]);

    // 1) Attempt to deliver without open cash -> expect 400
    $resp1 = $this->postJson("/api/credits/{$credit->id}/waiting-list/deliver", []);
    $resp1->assertStatus(400);
    expect(data_get($resp1->json(), 'success'))->toBeFalse();

    // 2) Open cash for today
    CashBalance::create([
        'cobrador_id' => $cobrador->id,
        'date' => now()->toDateString(),
        'initial_amount' => 0,
        'collected_amount' => 0,
        'lent_amount' => 0,
        'final_amount' => 0,
        'status' => 'open',
    ]);

    // Retry deliver -> expect success and credit active
    $resp2 = $this->postJson("/api/credits/{$credit->id}/waiting-list/deliver", []);
    $resp2->assertSuccessful();
    expect(data_get($resp2->json(), 'success'))->toBeTrue();

    $credit->refresh();
    expect($credit->status)->toBe('active');
    expect($credit->delivered_by)->toBe($cobrador->id);
    expect($credit->delivered_at)->not()->toBeNull();
});
