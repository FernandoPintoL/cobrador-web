<?php

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('asigna correctamente el numero de cuota llenando saldos parciales y continuando a la siguiente', function () {
    ensureRoleExists('admin');
    ensureRoleExists('client');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create();
    $client->assignRole('client');

    // Crédito con 3 cuotas de 100 (total 300)
    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'amount' => 300,
        'interest_rate' => 0,
        'total_amount' => 300,
        'balance' => 300,
        'installment_amount' => 100,
        'total_installments' => 3,
        'frequency' => 'daily',
        'start_date' => now()->subDays(1),
        'end_date' => now()->addDays(30),
        'status' => 'active',
    ]);

    // Pago parcial previo de 40 a la cuota 1
    Payment::create([
        'credit_id' => $credit->id,
        'client_id' => $client->id,
        'cobrador_id' => $admin->id,
        'amount' => 40,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'received_by' => $admin->id,
        'installment_number' => 1,
        'status' => 'partial',
    ]);

    // Ejecutar el endpoint: pagar 160 sin especificar numero de cuota
    $this->actingAs($admin, 'sanctum');

    $response = $this->postJson('/api/payments', [
        'credit_id' => $credit->id,
        'amount' => 160,
        'payment_method' => 'card',
    ]);

    $response->assertSuccessful();

    $data = $response->json('data');

    expect($data)->toHaveKey('payments');

    $payments = $data['payments'];
    expect(count($payments))->toBe(2);

    // Primer pago debe completar la cuota 1 con 60
    $p1 = $payments[0];
    $p2 = $payments[1];

    expect((int) $p1['installment_number'])->toBe(1)
        ->and((float) $p1['amount'])->toBeFloat()->and((float) $p1['amount'])->toBe(60.0)
        ->and($p1['status'])->toBe('completed');

    // Segundo pago debe ser la cuota 2 completa 100
    expect((int) $p2['installment_number'])->toBe(2)
        ->and((float) $p2['amount'])->toBe(100.0)
        ->and($p2['status'])->toBe('completed');

    // Total pagado reportado
    expect((float) $data['total_paid'])->toBe(160.0);
});

it('valida payment_method segun el enum: rechaza check y acepta mobile_payment', function () {
    ensureRoleExists('admin');
    ensureRoleExists('client');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create();
    $client->assignRole('client');

    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'amount' => 100,
        'interest_rate' => 0,
        'total_amount' => 100,
        'balance' => 100,
        'installment_amount' => 100,
        'total_installments' => 1,
        'frequency' => 'daily',
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
        'status' => 'active',
    ]);

    $this->actingAs($admin, 'sanctum');

    // Rechazado por validador
    $bad = $this->postJson('/api/payments', [
        'credit_id' => $credit->id,
        'amount' => 10,
        'payment_method' => 'check',
    ]);
    $bad->assertStatus(422);

    // Aceptado
    $ok = $this->postJson('/api/payments', [
        'credit_id' => $credit->id,
        'amount' => 100,
        'payment_method' => 'mobile_payment',
    ]);
    $ok->assertSuccessful();
});
