<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('map endpoints return successfully for manager without errors', function () {
    ensureRole('admin');
    ensureRole('manager');
    ensureRole('cobrador');
    ensureRole('client');

    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $cobrador = User::factory()->create(['assigned_manager_id' => $manager->id]);
    $cobrador->assignRole('cobrador');

    $client = User::factory()->create([
        'assigned_cobrador_id' => $cobrador->id,
        // optional location fields to avoid null-related edge cases
        'latitude' => -17.78,
        'longitude' => -63.18,
    ]);
    $client->assignRole('client');

    $this->actingAs($manager, 'sanctum');

    // Clients endpoint
    $respClients = $this->getJson('/api/map/clients?cobrador_id='.$cobrador->id);
    $respClients->assertSuccessful();

    // Stats endpoint
    $respStats = $this->getJson('/api/map/stats?cobrador_id='.$cobrador->id);
    $respStats->assertSuccessful();

    // Basic structure checks
    expect($respStats->json('success'))->toBeTrue();
    expect($respClients->json('success'))->toBeTrue();
});
