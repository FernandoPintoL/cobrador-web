<?php

use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('auto-assigns installment_number and splits payment across installments when not provided', function () {
    $current = User::factory()->create();
    Sanctum::actingAs($current);

    $client = User::factory()->create();
    $creator = User::factory()->create();

    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $creator->id,
        'amount' => 600.00,
        'interest_rate' => 0,
        'total_amount' => 600.00,
        'installment_amount' => 200.00,
        'total_installments' => 3,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
        'status' => 'active',
    ]);

    // Pre-existente: cuota 1 pagada parcialmente con 50
    $this->postJson('/api/payments', [
        'credit_id' => $credit->id,
        'amount' => 50.00,
        'payment_method' => 'cash',
        'installment_number' => 1,
    ])->assertSuccessful();

    // Enviar pago de 350 sin número de cuota => debe completar cuota 1 (150) y pagar cuota 2 (200)
    $response = $this->postJson('/api/payments', [
        'credit_id' => $credit->id,
        'amount' => 350.00,
        'payment_method' => 'cash',
    ]);

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toHaveKey('payments');
    expect(count($data['payments']))->toBe(2);

    // Orden esperado: primero cuota 1, luego cuota 2
    expect($data['payments'][0]['installment_number'])->toBe(1)
        ->and((float) $data['payments'][0]['amount'])->toBe(150.0)
        ->and($data['payments'][0]['status'])->toBe('completed');

    expect($data['payments'][1]['installment_number'])->toBe(2)
        ->and((float) $data['payments'][1]['amount'])->toBe(200.0)
        ->and($data['payments'][1]['status'])->toBe('completed');
});

it('respects provided installment_number when creating a payment', function () {
    $current = User::factory()->create();
    Sanctum::actingAs($current);

    $client = User::factory()->create();
    $creator = User::factory()->create();

    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $creator->id,
        'amount' => 600.00,
        'interest_rate' => 0,
        'total_amount' => 600.00,
        'installment_amount' => 200.00,
        'total_installments' => 3,
        'frequency' => 'daily',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
        'status' => 'active',
    ]);

    // Pago parcial dirigido a la cuota 3
    $response = $this->postJson('/api/payments', [
        'credit_id' => $credit->id,
        'amount' => 100.00,
        'payment_method' => 'cash',
        'installment_number' => 3,
    ]);

    $response->assertSuccessful();
    $data = $response->json('data');

    expect(count($data['payments']))->toBe(1)
        ->and($data['payments'][0]['installment_number'])->toBe(3)
        ->and((float) $data['payments'][0]['amount'])->toBe(100.0)
        ->and($data['payments'][0]['status'])->toBe('partial');
});
