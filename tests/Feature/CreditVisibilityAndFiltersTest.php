<?php

use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeRole(string $name): Role
{
    return Role::findOrCreate($name);
}

it('cobrador solo ve sus créditos creados y los de sus clientes asignados', function () {
    // Roles
    makeRole('admin');
    makeRole('manager');
    makeRole('cobrador');
    makeRole('client');

    // Usuarios
    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $cobradorA = User::factory()->create(['assigned_manager_id' => $manager->id]);
    $cobradorA->assignRole('cobrador');

    $cobradorB = User::factory()->create();
    $cobradorB->assignRole('cobrador');

    $clientX = User::factory()->create(['assigned_cobrador_id' => $cobradorA->id]);
    $clientX->assignRole('client');

    $clientY = User::factory()->create(['assigned_cobrador_id' => $cobradorB->id]);
    $clientY->assignRole('client');

    $clientZ = User::factory()->create(['assigned_cobrador_id' => $cobradorA->id]);
    $clientZ->assignRole('client');

    // Créditos
    $credit1 = Credit::create([
        'client_id' => $clientX->id,
        'created_by' => $cobradorA->id,
        'amount' => 150,
        'interest_rate' => 10,
        'total_amount' => 165,
        'balance' => 165,
        'frequency' => 'daily',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'status' => 'active',
    ]);

    // Creado por A pero para cliente de B (visible por creado_por)
    $credit2 = Credit::create([
        'client_id' => $clientY->id,
        'created_by' => $cobradorA->id,
        'amount' => 200,
        'interest_rate' => 10,
        'total_amount' => 220,
        'balance' => 220,
        'frequency' => 'weekly',
        'start_date' => now()->subDays(10),
        'end_date' => now()->addDays(20),
        'status' => 'active',
    ]);

    // Creado por B para cliente de A (visible por cliente asignado)
    $credit3 = Credit::create([
        'client_id' => $clientZ->id,
        'created_by' => $cobradorB->id,
        'amount' => 300,
        'interest_rate' => 10,
        'total_amount' => 330,
        'balance' => 330,
        'frequency' => 'monthly',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
        'status' => 'active',
    ]);

    // No visible para A (ni creado por él ni cliente asignado)
    $creditHidden = Credit::create([
        'client_id' => $clientY->id,
        'created_by' => $cobradorB->id,
        'amount' => 400,
        'interest_rate' => 10,
        'total_amount' => 440,
        'balance' => 440,
        'frequency' => 'daily',
        'start_date' => now()->subDays(1),
        'end_date' => now()->addDays(10),
        'status' => 'active',
    ]);

    $this->actingAs($cobradorA, 'sanctum');

    $response = $this->getJson('/api/credits');
    $response->assertSuccessful();

    $ids = collect($response->json('data.data') ?? $response->json('data'))
        ->pluck('id')->all();

    expect($ids)->toContain($credit1->id)
        ->and($ids)->toContain($credit2->id)
        ->and($ids)->toContain($credit3->id)
        ->and($ids)->not->toContain($creditHidden->id);
});

it('manager ve sus créditos creados y los asociados a sus clientes (directos o vía cobradores)', function () {
    makeRole('admin');
    makeRole('manager');
    makeRole('cobrador');
    makeRole('client');

    // Manager y su equipo
    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $cobradorTeam = User::factory()->create(['assigned_manager_id' => $manager->id]);
    $cobradorTeam->assignRole('cobrador');

    $clientDirect = User::factory()->create(['assigned_manager_id' => $manager->id]);
    $clientDirect->assignRole('client');

    $clientViaCobrador = User::factory()->create(['assigned_cobrador_id' => $cobradorTeam->id]);
    $clientViaCobrador->assignRole('client');

    $otherManager = User::factory()->create();
    $otherManager->assignRole('manager');

    $outsideClient = User::factory()->create();
    $outsideClient->assignRole('client');

    // Créditos visibles
    $creditOwn = Credit::create([
        'client_id' => $outsideClient->id, // fuera de su ámbito pero creado por el manager
        'created_by' => $manager->id,
        'amount' => 120,
        'interest_rate' => 10,
        'total_amount' => 132,
        'balance' => 132,
        'frequency' => 'daily',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(8),
        'status' => 'active',
    ]);

    $creditDirect = Credit::create([
        'client_id' => $clientDirect->id,
        'created_by' => $cobradorTeam->id,
        'amount' => 200,
        'interest_rate' => 10,
        'total_amount' => 220,
        'balance' => 220,
        'frequency' => 'weekly',
        'start_date' => now()->subWeeks(1),
        'end_date' => now()->addWeeks(3),
        'status' => 'active',
    ]);

    $creditViaCobrador = Credit::create([
        'client_id' => $clientViaCobrador->id,
        'created_by' => $cobradorTeam->id,
        'amount' => 180,
        'interest_rate' => 10,
        'total_amount' => 198,
        'balance' => 198,
        'frequency' => 'monthly',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
        'status' => 'active',
    ]);

    // No visible
    $hidden = Credit::create([
        'client_id' => $outsideClient->id,
        'created_by' => $otherManager->id,
        'amount' => 500,
        'interest_rate' => 10,
        'total_amount' => 550,
        'balance' => 550,
        'frequency' => 'daily',
        'start_date' => now(),
        'end_date' => now()->addDays(10),
        'status' => 'active',
    ]);

    $this->actingAs($manager, 'sanctum');
    $response = $this->getJson('/api/credits');
    $response->assertSuccessful();

    $ids = collect($response->json('data.data') ?? $response->json('data'))
        ->pluck('id')->all();

    expect($ids)->toContain($creditOwn->id)
        ->and($ids)->toContain($creditDirect->id)
        ->and($ids)->toContain($creditViaCobrador->id)
        ->and($ids)->not->toContain($hidden->id);
});

it('aplica filtros de frecuencia y montos', function () {
    makeRole('cobrador');
    makeRole('client');

    $cobrador = User::factory()->create();
    $cobrador->assignRole('cobrador');

    $client = User::factory()->create(['assigned_cobrador_id' => $cobrador->id]);
    $client->assignRole('client');

    $c1 = Credit::create([
        'client_id' => $client->id,
        'created_by' => $cobrador->id,
        'amount' => 100,
        'interest_rate' => 10,
        'total_amount' => 110,
        'balance' => 110,
        'frequency' => 'daily',
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-10',
        'status' => 'active',
    ]);

    $c2 = Credit::create([
        'client_id' => $client->id,
        'created_by' => $cobrador->id,
        'amount' => 250,
        'interest_rate' => 10,
        'total_amount' => 275,
        'balance' => 275,
        'frequency' => 'weekly',
        'start_date' => '2025-02-01',
        'end_date' => '2025-03-01',
        'status' => 'active',
    ]);

    $this->actingAs($cobrador, 'sanctum');

    // frecuencia multiple y rango de monto
    $resp = $this->getJson('/api/credits?frequency=daily,weekly&amount_min=120&amount_max=260');
    $resp->assertSuccessful();

    $ids = collect($resp->json('data.data') ?? $resp->json('data'))
        ->pluck('id')->all();

    expect($ids)->toContain($c2->id)
        ->and($ids)->not->toContain($c1->id);

    // rango de fechas
    $resp2 = $this->getJson('/api/credits?start_date_from=2025-01-15');
    $resp2->assertSuccessful();
    $ids2 = collect($resp2->json('data.data') ?? $resp2->json('data'))->pluck('id')->all();
    expect($ids2)->toContain($c2->id)->and($ids2)->not->toContain($c1->id);
});


it('filtra por search aceptando nombre, ci y phone', function () {
    makeRole('cobrador');
    makeRole('client');

    $cobrador = User::factory()->create();
    $cobrador->assignRole('cobrador');

    $client = User::factory()->create([
        'assigned_cobrador_id' => $cobrador->id,
        'name' => 'Juan Perez',
        'ci' => '8210151',
        'phone' => '76543210',
    ]);
    $client->assignRole('client');

    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $cobrador->id,
        'amount' => 100,
        'interest_rate' => 10,
        'total_amount' => 110,
        'balance' => 110,
        'frequency' => 'daily',
        'start_date' => now()->subDays(1),
        'end_date' => now()->addDays(10),
        'status' => 'active',
    ]);

    $this->actingAs($cobrador, 'sanctum');

    // por nombre
    $r1 = $this->getJson('/api/credits?search=Juan');
    $r1->assertSuccessful();
    $ids1 = collect($r1->json('data.data') ?? $r1->json('data'))->pluck('id')->all();
    expect($ids1)->toContain($credit->id);

    // por CI
    $r2 = $this->getJson('/api/credits?search=8210151');
    $r2->assertSuccessful();
    $ids2 = collect($r2->json('data.data') ?? $r2->json('data'))->pluck('id')->all();
    expect($ids2)->toContain($credit->id);

    // por phone
    $r3 = $this->getJson('/api/credits?search=76543210');
    $r3->assertSuccessful();
    $ids3 = collect($r3->json('data.data') ?? $r3->json('data'))->pluck('id')->all();
    expect($ids3)->toContain($credit->id);
});
