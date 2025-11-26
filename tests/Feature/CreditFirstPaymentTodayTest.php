<?php

use App\Models\Credit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function setupRolesAndUsers(): array
{
    Role::findOrCreate('admin');
    Role::findOrCreate('manager');
    Role::findOrCreate('cobrador');
    Role::findOrCreate('client');

    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $cobrador = User::factory()->create(['assigned_manager_id' => $manager->id]);
    $cobrador->assignRole('cobrador');

    $client = User::factory()->create(['assigned_cobrador_id' => $cobrador->id]);
    $client->assignRole('client');

    return compact('manager', 'cobrador', 'client');
}

test('crédito entregado con first_payment_today=false inicia cronograma mañana', function () {
    $users = setupRolesAndUsers();
    extract($users);

    // Crear crédito
    $this->actingAs($cobrador, 'sanctum');
    $resp = $this->postJson('/api/credits', [
        'client_id' => $client->id,
        'amount' => 1000,
        'balance' => 1000,
        'frequency' => 'daily',
        'total_installments' => 10,
        'interest_rate' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
    ]);
    $resp->assertSuccessful();
    $creditId = $resp->json('data.id') ?? $resp->json('data.data.id');

    // Aprobar
    $this->actingAs($manager, 'sanctum');
    $approve = $this->postJson("/api/credits/{$creditId}/waiting-list/approve", [
        'immediate_delivery' => false,
        'scheduled_delivery_date' => now()->toIso8601String(),
    ]);
    $approve->assertSuccessful();

    // Entregar con first_payment_today=false (comportamiento por defecto)
    $this->actingAs($cobrador, 'sanctum');
    $deliver = $this->postJson("/api/credits/{$creditId}/waiting-list/deliver", [
        'notes' => 'Entrega de prueba',
        'first_payment_today' => false,
    ]);
    $deliver->assertSuccessful();

    $credit = Credit::findOrFail($creditId);

    // Validar que el crédito esté activo
    expect($credit->status)->toBe('active');

    // Validar que first_payment_today sea false
    expect($credit->first_payment_today)->toBeFalse();

    // Validar que start_date sea mañana (delivered_at + 1 día)
    $deliveredDate = Carbon::parse($credit->delivered_at);
    $startDate = Carbon::parse($credit->start_date);
    $expectedStartDate = $deliveredDate->copy()->addDay();

    expect($startDate->isSameDay($expectedStartDate))->toBeTrue(
        "start_date debería ser {$expectedStartDate->toDateString()} pero es {$startDate->toDateString()}"
    );

    // Validar cronograma de pagos
    $schedule = $credit->getPaymentSchedule();
    expect($schedule)->toBeArray();
    expect(count($schedule))->toBe(10); // 10 cuotas

    // La primera cuota debe vencer mañana
    $firstInstallment = $schedule[0];
    expect($firstInstallment['installment_number'])->toBe(1);
    expect(Carbon::parse($firstInstallment['due_date'])->isSameDay($expectedStartDate))->toBeTrue(
        "Primera cuota debería vencer el {$expectedStartDate->toDateString()} pero vence el {$firstInstallment['due_date']}"
    );
});

test('crédito entregado con first_payment_today=true inicia cronograma hoy', function () {
    $users = setupRolesAndUsers();
    extract($users);

    // Crear crédito
    $this->actingAs($cobrador, 'sanctum');
    $resp = $this->postJson('/api/credits', [
        'client_id' => $client->id,
        'amount' => 1000,
        'balance' => 1000,
        'frequency' => 'daily',
        'total_installments' => 10,
        'interest_rate' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
    ]);
    $resp->assertSuccessful();
    $creditId = $resp->json('data.id') ?? $resp->json('data.data.id');

    // Aprobar
    $this->actingAs($manager, 'sanctum');
    $approve = $this->postJson("/api/credits/{$creditId}/waiting-list/approve", [
        'immediate_delivery' => false,
        'scheduled_delivery_date' => now()->toIso8601String(),
    ]);
    $approve->assertSuccessful();

    // Entregar con first_payment_today=true
    $this->actingAs($cobrador, 'sanctum');
    $deliver = $this->postJson("/api/credits/{$creditId}/waiting-list/deliver", [
        'notes' => 'Entrega con pago inmediato',
        'first_payment_today' => true,
    ]);
    $deliver->assertSuccessful();

    $credit = Credit::findOrFail($creditId);

    // Validar que el crédito esté activo
    expect($credit->status)->toBe('active');

    // Validar que first_payment_today sea true
    expect($credit->first_payment_today)->toBeTrue();

    // Validar que start_date sea HOY (mismo día que delivered_at)
    $deliveredDate = Carbon::parse($credit->delivered_at);
    $startDate = Carbon::parse($credit->start_date);

    expect($startDate->isSameDay($deliveredDate))->toBeTrue(
        "start_date debería ser {$deliveredDate->toDateString()} pero es {$startDate->toDateString()}"
    );

    // Validar cronograma de pagos
    $schedule = $credit->getPaymentSchedule();
    expect($schedule)->toBeArray();
    expect(count($schedule))->toBe(10); // 10 cuotas

    // La primera cuota debe vencer HOY
    $firstInstallment = $schedule[0];
    expect($firstInstallment['installment_number'])->toBe(1);
    expect(Carbon::parse($firstInstallment['due_date'])->isSameDay($deliveredDate))->toBeTrue(
        "Primera cuota debería vencer el {$deliveredDate->toDateString()} pero vence el {$firstInstallment['due_date']}"
    );
});

test('endpoint de cronograma retorna schedule correctamente', function () {
    $users = setupRolesAndUsers();
    extract($users);

    // Crear y entregar crédito
    $this->actingAs($cobrador, 'sanctum');
    $resp = $this->postJson('/api/credits', [
        'client_id' => $client->id,
        'amount' => 1000,
        'balance' => 1000,
        'frequency' => 'weekly',
        'total_installments' => 4,
        'interest_rate' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
    ]);
    $resp->assertSuccessful();
    $creditId = $resp->json('data.id') ?? $resp->json('data.data.id');

    // Aprobar
    $this->actingAs($manager, 'sanctum');
    $this->postJson("/api/credits/{$creditId}/waiting-list/approve", [
        'scheduled_delivery_date' => now()->toIso8601String(),
    ])->assertSuccessful();

    // Entregar
    $this->actingAs($cobrador, 'sanctum');
    $this->postJson("/api/credits/{$creditId}/waiting-list/deliver", [
        'first_payment_today' => true,
    ])->assertSuccessful();

    // Obtener cronograma via endpoint
    $scheduleResp = $this->getJson("/api/credits/{$creditId}/payment-schedule");
    $scheduleResp->assertSuccessful();

    $data = $scheduleResp->json('data');
    expect($data)->toHaveKey('schedule');
    expect($data)->toHaveKey('credit_info');

    $schedule = $data['schedule'];
    expect($schedule)->toBeArray();
    expect(count($schedule))->toBe(4);

    // Validar estructura de cada cuota
    foreach ($schedule as $index => $installment) {
        expect($installment)->toHaveKey('installment_number');
        expect($installment)->toHaveKey('due_date');
        expect($installment)->toHaveKey('amount');
        expect($installment)->toHaveKey('status');
        expect($installment)->toHaveKey('paid_amount');
        expect($installment)->toHaveKey('remaining_amount');
        expect($installment['installment_number'])->toBe($index + 1);
    }

    // Validar credit_info
    $creditInfo = $data['credit_info'];
    expect($creditInfo)->toHaveKey('first_payment_today');
    expect($creditInfo['first_payment_today'])->toBeTrue();
});

test('cronograma respeta días hábiles para frecuencia diaria', function () {
    $users = setupRolesAndUsers();
    extract($users);

    // Crear crédito
    $this->actingAs($cobrador, 'sanctum');
    $resp = $this->postJson('/api/credits', [
        'client_id' => $client->id,
        'amount' => 700,
        'balance' => 700,
        'frequency' => 'daily',
        'total_installments' => 7,
        'interest_rate' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
    ]);
    $resp->assertSuccessful();
    $creditId = $resp->json('data.id') ?? $resp->json('data.data.id');

    // Aprobar
    $this->actingAs($manager, 'sanctum');
    $this->postJson("/api/credits/{$creditId}/waiting-list/approve", [
        'scheduled_delivery_date' => now()->toIso8601String(),
    ])->assertSuccessful();

    // Entregar
    $this->actingAs($cobrador, 'sanctum');
    $this->postJson("/api/credits/{$creditId}/waiting-list/deliver")->assertSuccessful();

    $credit = Credit::findOrFail($creditId);
    $schedule = $credit->getPaymentSchedule();

    // Validar que ninguna cuota caiga en domingo
    foreach ($schedule as $installment) {
        $dueDate = Carbon::parse($installment['due_date']);
        expect($dueDate->dayOfWeek)->not->toBe(Carbon::SUNDAY,
            "La cuota {$installment['installment_number']} cae en domingo: {$dueDate->toDateString()}"
        );
    }
});
