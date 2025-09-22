<?php

use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function ensureRole(string $name): Role
{
    return Role::findOrCreate($name);
}

it('users report includes summary counts and respects manager scoping', function () {
    ensureRole('admin');
    ensureRole('manager');
    ensureRole('cobrador');
    ensureRole('client');

    // Manager and team
    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $cobradorTeam = User::factory()->create(['assigned_manager_id' => $manager->id]);
    $cobradorTeam->assignRole('cobrador');

    $clientViaCobrador = User::factory()->create(['assigned_cobrador_id' => $cobradorTeam->id]);
    $clientViaCobrador->assignRole('client');

    // Outside users
    $otherManager = User::factory()->create();
    $otherManager->assignRole('manager');

    $outsideCobrador = User::factory()->create();
    $outsideCobrador->assignRole('cobrador');

    $outsideClient = User::factory()->create(['assigned_cobrador_id' => $outsideCobrador->id]);
    $outsideClient->assignRole('client');

    $this->actingAs($manager, 'sanctum');

    $response = $this->getJson('/api/reports/users?format=json');
    $response->assertSuccessful();

    $summary = $response->json('data.summary');
    expect($summary)->toHaveKey('cobradores_count')
        ->and($summary)->toHaveKey('managers_count')
        ->and($summary['managers_count'])->toBeGreaterThanOrEqual(1)
        ->and($summary['cobradores_count'])->toBeGreaterThanOrEqual(1);

    $ids = collect($response->json('data.users'))->pluck('id')->all();
    expect($ids)->toContain($manager->id)
        ->and($ids)->toContain($cobradorTeam->id)
        ->and($ids)->toContain($clientViaCobrador->id)
        ->and($ids)->not->toContain($otherManager->id)
        ->and($ids)->not->toContain($outsideCobrador->id)
        ->and($ids)->not->toContain($outsideClient->id);
});

it('credits report summary includes pending_amount equal to sum of balances', function () {
    ensureRole('admin');
    ensureRole('client');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Create credits with balances
    $clientA = User::factory()->create();
    $clientA->assignRole('client');

    $clientB = User::factory()->create();
    $clientB->assignRole('client');

    $c1 = Credit::create([
        'client_id' => $clientA->id,
        'created_by' => $admin->id,
        'amount' => 100,
        'interest_rate' => 10,
        'total_amount' => 110,
        'balance' => 80,
        'frequency' => 'daily',
        'start_date' => now()->subDays(1),
        'end_date' => now()->addDays(10),
        'status' => 'active',
    ]);

    $c2 = Credit::create([
        'client_id' => $clientB->id,
        'created_by' => $admin->id,
        'amount' => 200,
        'interest_rate' => 10,
        'total_amount' => 220,
        'balance' => 150,
        'frequency' => 'weekly',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(20),
        'status' => 'active',
    ]);

    $this->actingAs($admin, 'sanctum');

    $response = $this->getJson('/api/reports/credits?format=json');
    $response->assertSuccessful();

    $pending = $response->json('data.summary.pending_amount');
    expect($pending)->toEqual(80 + 150);
});
