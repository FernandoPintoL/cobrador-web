<?php

use App\Models\Credit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function roles_setup(): void
{
    Role::findOrCreate('admin');
    Role::findOrCreate('manager');
    Role::findOrCreate('cobrador');
    Role::findOrCreate('client');
}

it('stores immediate_delivery_requested when cobrador creates and sets pending_approval', function () {
    roles_setup();

    $cobrador = User::factory()->create();
    $cobrador->assignRole('cobrador');

    $client = User::factory()->create(['assigned_cobrador_id' => $cobrador->id]);
    $client->assignRole('client');

    $this->actingAs($cobrador, 'sanctum');

    $payload = [
        'client_id' => $client->id,
        'amount' => 1000,
        'balance' => 1000,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'immediate_delivery_requested' => true,
    ];

    $resp = $this->postJson('/api/credits', $payload);
    $resp->assertSuccessful();

    $creditId = $resp->json('data.id') ?? $resp->json('data.data.id');
    $credit = Credit::findOrFail($creditId);

    expect($credit->status)->toBe('pending_approval')
        ->and($credit->immediate_delivery_requested)->toBeTrue();
});

it('approve without date defaults delivery to next day; immediate=true delivers now and notifies delivered event later', function () {
    roles_setup();

    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $cobrador = User::factory()->create(['assigned_manager_id' => $manager->id]);
    $cobrador->assignRole('cobrador');

    $client = User::factory()->create(['assigned_cobrador_id' => $cobrador->id]);
    $client->assignRole('client');

    // Create credit as cobrador
    $this->actingAs($cobrador, 'sanctum');
    $resp = $this->postJson('/api/credits', [
        'client_id' => $client->id,
        'amount' => 500,
        'balance' => 500,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(15)->toDateString(),
    ]);
    $resp->assertSuccessful();
    $creditId = $resp->json('data.id') ?? $resp->json('data.data.id');

    // Approve as manager without date and without immediate
    $this->actingAs($manager, 'sanctum');
    $approve = $this->postJson("/api/credits/{$creditId}/waiting-list/approve", [
        'immediate_delivery' => false,
    ]);
    $approve->assertSuccessful();

    $credit = Credit::findOrFail($creditId);
    // scheduled_delivery_date should be tomorrow (>= now + 1 day at date level)
    expect(Carbon::parse($credit->scheduled_delivery_date)->isSameDay(Carbon::now()->addDay()))->toBeTrue();
    // Credit should remain waiting_delivery (not active) since not immediate
    expect($credit->status)->toBe('waiting_delivery');

    // Approve immediate for another credit
    $resp2 = $this->actingAs($cobrador, 'sanctum')->postJson('/api/credits', [
        'client_id' => $client->id,
        'amount' => 600,
        'balance' => 600,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(20)->toDateString(),
    ]);
    $resp2->assertSuccessful();
    $creditId2 = $resp2->json('data.id') ?? $resp2->json('data.data.id');

    $approve2 = $this->actingAs($manager, 'sanctum')->postJson("/api/credits/{$creditId2}/waiting-list/approve", [
        'immediate_delivery' => true,
    ]);
    $approve2->assertSuccessful();

    $credit2 = Credit::findOrFail($creditId2);
    expect($credit2->status)->toBe('active')
        ->and(Carbon::parse($credit2->scheduled_delivery_date)->lessThanOrEqualTo(Carbon::now()))->toBeTrue();
});
