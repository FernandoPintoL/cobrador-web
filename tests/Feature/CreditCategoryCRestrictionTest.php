<?php

use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createRoles(): void
{
    Role::findOrCreate('admin');
    Role::findOrCreate('manager');
    Role::findOrCreate('cobrador');
    Role::findOrCreate('client');
}

it('blocks creating a credit for a client in category C', function () {
    createRoles();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $cobrador = User::factory()->create();
    $cobrador->assignRole('cobrador');

    $clientC = User::factory()->create([
        'assigned_cobrador_id' => $cobrador->id,
        'client_category' => 'C',
    ]);
    $clientC->assignRole('client');

    $this->actingAs($admin, 'sanctum');

    $payload = [
        'client_id' => $clientC->id,
        'amount' => 100,
        'balance' => 100,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
    ];

    $resp = $this->postJson('/api/credits', $payload);
    $resp->assertStatus(422);
    expect($resp->json('message') ?? $resp->json('data.message') ?? '')->toContain('categoría C');
});

it('blocks updating a credit to point to a client in category C', function () {
    createRoles();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $cobrador = User::factory()->create();
    $cobrador->assignRole('cobrador');

    $clientB = User::factory()->create([
        'assigned_cobrador_id' => $cobrador->id,
        'client_category' => 'B',
    ]);
    $clientB->assignRole('client');

    $clientC = User::factory()->create([
        'assigned_cobrador_id' => $cobrador->id,
        'client_category' => 'C',
    ]);
    $clientC->assignRole('client');

    $credit = Credit::create([
        'client_id' => $clientB->id,
        'created_by' => $cobrador->id,
        'amount' => 100,
        'interest_rate' => 10,
        'total_amount' => 110,
        'balance' => 110,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($admin, 'sanctum');

    $update = [
        'client_id' => $clientC->id,
        'amount' => 120,
        'balance' => 120,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(12)->toDateString(),
        'status' => 'active',
    ];

    $resp = $this->putJson('/api/credits/'.$credit->id, $update);
    $resp->assertStatus(422);
    expect($resp->json('message') ?? $resp->json('data.message') ?? '')->toContain('categoría C');
});
