<?php

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear roles necesarios
    Role::findOrCreate('admin');
    Role::findOrCreate('manager');
    Role::findOrCreate('cobrador');
    Role::findOrCreate('client');
});

describe('Overdue Report - Manager Visibility', function () {
    it('manager sees overdue credits from direct clients', function () {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        // Cliente asignado directamente al manager
        $directClient = User::factory()->create(['assigned_manager_id' => $manager->id]);
        $directClient->assignRole('client');

        // Crear crédito en mora para cliente directo
        $credit = Credit::create([
            'client_id' => $directClient->id,
            'created_by' => $manager->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 3, // Debería tener 11, tiene 3 = MORA
        ]);

        $this->actingAs($manager, 'sanctum');

        $response = $this->getJson('/api/reports/overdue?format=json');
        $response->assertSuccessful();

        $creditIds = collect($response->json('data.credits'))->pluck('id')->all();
        expect($creditIds)->toContain($credit->id);
    });

    it('manager sees overdue credits from cobrador team clients', function () {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        // Cobrador asignado al manager
        $cobrador = User::factory()->create(['assigned_manager_id' => $manager->id]);
        $cobrador->assignRole('cobrador');

        // Cliente asignado al cobrador
        $cobradorClient = User::factory()->create(['assigned_cobrador_id' => $cobrador->id]);
        $cobradorClient->assignRole('client');

        // Crear crédito en mora para cliente del cobrador
        $credit = Credit::create([
            'client_id' => $cobradorClient->id,
            'created_by' => $cobrador->id,
            'amount' => 1000,
            'balance' => 800,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'weekly',
            'start_date' => now()->subWeeks(5),
            'end_date' => now()->addWeeks(5),
            'status' => 'active',
            'paid_installments' => 2, // Debería tener 6, tiene 2 = MORA
        ]);

        $this->actingAs($manager, 'sanctum');

        $response = $this->getJson('/api/reports/overdue?format=json');
        $response->assertSuccessful();

        $creditIds = collect($response->json('data.credits'))->pluck('id')->all();
        expect($creditIds)->toContain($credit->id);
    });

    it('manager does not see overdue credits from other teams', function () {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        // Otro manager y su equipo
        $otherManager = User::factory()->create();
        $otherManager->assignRole('manager');

        $otherCobrador = User::factory()->create(['assigned_manager_id' => $otherManager->id]);
        $otherCobrador->assignRole('cobrador');

        $otherClient = User::factory()->create(['assigned_cobrador_id' => $otherCobrador->id]);
        $otherClient->assignRole('client');

        // Crear crédito en mora para el otro equipo
        $outsideCredit = Credit::create([
            'client_id' => $otherClient->id,
            'created_by' => $otherCobrador->id,
            'amount' => 1000,
            'balance' => 900,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 2,
        ]);

        $this->actingAs($manager, 'sanctum');

        $response = $this->getJson('/api/reports/overdue?format=json');
        $response->assertSuccessful();

        $creditIds = collect($response->json('data.credits'))->pluck('id')->all();
        expect($creditIds)->not->toContain($outsideCredit->id);
    });
});

describe('Overdue Report - Calculations', function () {
    it('correctly identifies overdue credits', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $client = User::factory()->create();
        $client->assignRole('client');

        // Crédito AL DÍA
        $onTimeCredit = Credit::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'amount' => 1000,
            'balance' => 500,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
            'paid_installments' => 6, // Esperadas: 6, Completadas: 6 = AL DÍA
        ]);

        // Crédito EN MORA
        $overdueCredit = Credit::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'amount' => 2000,
            'balance' => 1500,
            'total_amount' => 2200,
            'installment_amount' => 200,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 5, // Esperadas: 11, Completadas: 5 = MORA
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/reports/overdue?format=json');
        $response->assertSuccessful();

        $creditIds = collect($response->json('data.credits'))->pluck('id')->all();

        // Solo debe aparecer el crédito en mora
        expect($creditIds)->toContain($overdueCredit->id)
            ->and($creditIds)->not->toContain($onTimeCredit->id);
    });

    it('calculates days overdue correctly for daily frequency', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $client = User::factory()->create();
        $client->assignRole('client');

        $credit = Credit::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'amount' => 1000,
            'balance' => 600,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 7, // Esperadas: 11, Completadas: 7, Atrasadas: 4 días
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/reports/overdue?format=json');
        $response->assertSuccessful();

        $credits = collect($response->json('data.credits'));
        $overdueCredit = $credits->firstWhere('id', $credit->id);

        expect($overdueCredit)->not->toBeNull()
            ->and($overdueCredit['days_overdue'])->toBe(4)
            ->and($overdueCredit['overdue_amount'])->toBe(400);
    });

    it('includes overdue summary statistics', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $client = User::factory()->create();
        $client->assignRole('client');

        // Crear 3 créditos con diferentes niveles de mora
        Credit::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
            'paid_installments' => 3, // Mora leve: 3 días
        ]);

        Credit::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'amount' => 2000,
            'balance' => 1200,
            'total_amount' => 2200,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(20),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 6, // Mora moderada: 15 días
        ]);

        Credit::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'amount' => 3000,
            'balance' => 2000,
            'total_amount' => 3300,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(50),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 10, // Mora severa: 41 días
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/reports/overdue?format=json');
        $response->assertSuccessful();

        $summary = $response->json('data.summary');

        expect($summary)->toHaveKey('total_overdue_credits')
            ->and($summary)->toHaveKey('total_overdue_amount')
            ->and($summary)->toHaveKey('by_severity')
            ->and($summary['total_overdue_credits'])->toBe(3)
            ->and($summary['by_severity']['light'])->toBe(1)
            ->and($summary['by_severity']['moderate'])->toBe(1)
            ->and($summary['by_severity']['severe'])->toBe(1);
    });
});

describe('Overdue Report - Filters', function () {
    it('filters by cobrador_id', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cobrador1 = User::factory()->create();
        $cobrador1->assignRole('cobrador');

        $cobrador2 = User::factory()->create();
        $cobrador2->assignRole('cobrador');

        $client1 = User::factory()->create();
        $client1->assignRole('client');

        $client2 = User::factory()->create();
        $client2->assignRole('client');

        $credit1 = Credit::create([
            'client_id' => $client1->id,
            'created_by' => $cobrador1->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 5,
        ]);

        $credit2 = Credit::create([
            'client_id' => $client2->id,
            'created_by' => $cobrador2->id,
            'amount' => 2000,
            'balance' => 1500,
            'total_amount' => 2200,
            'installment_amount' => 200,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 5,
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson("/api/reports/overdue?format=json&cobrador_id={$cobrador1->id}");
        $response->assertSuccessful();

        $creditIds = collect($response->json('data.credits'))->pluck('id')->all();

        expect($creditIds)->toContain($credit1->id)
            ->and($creditIds)->not->toContain($credit2->id);
    });

    it('filters by client_category', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $clientA = User::factory()->create(['client_category' => 'A']);
        $clientA->assignRole('client');

        $clientB = User::factory()->create(['client_category' => 'B']);
        $clientB->assignRole('client');

        $creditA = Credit::create([
            'client_id' => $clientA->id,
            'created_by' => $admin->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 5,
        ]);

        $creditB = Credit::create([
            'client_id' => $clientB->id,
            'created_by' => $admin->id,
            'amount' => 2000,
            'balance' => 1500,
            'total_amount' => 2200,
            'installment_amount' => 200,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 5,
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/reports/overdue?format=json&client_category=A');
        $response->assertSuccessful();

        $creditIds = collect($response->json('data.credits'))->pluck('id')->all();

        expect($creditIds)->toContain($creditA->id)
            ->and($creditIds)->not->toContain($creditB->id);
    });

    it('filters by min_days_overdue', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $client = User::factory()->create();
        $client->assignRole('client');

        // Mora leve (3 días)
        $creditLeve = Credit::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 3,
        ]);

        // Mora severa (20 días)
        $creditSevera = Credit::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'amount' => 2000,
            'balance' => 1000,
            'total_amount' => 2200,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 11,
        ]);

        $this->actingAs($admin, 'sanctum');

        // Filtrar solo moras mayores a 10 días
        $response = $this->getJson('/api/reports/overdue?format=json&min_days_overdue=10');
        $response->assertSuccessful();

        $creditIds = collect($response->json('data.credits'))->pluck('id')->all();

        expect($creditIds)->toContain($creditSevera->id)
            ->and($creditIds)->not->toContain($creditLeve->id);
    });
});

describe('Overdue Report - Export Formats', function () {
    it('exports to JSON format', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/reports/overdue?format=json');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'credits',
                    'summary',
                    'generated_at',
                    'generated_by',
                ],
                'message',
            ]);
    });

    it('exports to Excel format', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $client = User::factory()->create();
        $client->assignRole('client');

        Credit::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'paid_installments' => 5,
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->get('/api/reports/overdue?format=excel');

        $response->assertSuccessful()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });
});
