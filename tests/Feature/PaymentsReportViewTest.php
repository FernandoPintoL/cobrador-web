<?php

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('renders payments report HTML with merged columns and credit remaining', function () {
    // Autenticar usuario actual
    $current = User::factory()->create([
        'name' => 'Admin Tester',
        'email' => 'admin@example.com',
    ]);
    Sanctum::actingAs($current);

    // Crear cliente y cobrador
    $client = User::factory()->create(['name' => 'Cliente Uno']);
    $cobrador = User::factory()->create(['name' => 'Cobrador Uno']);

    // Crear un crédito activo básico
    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $current->id,
        'amount' => 1000.00,
        'interest_rate' => 20.00, // 20%
        'frequency' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->endOfMonth()->toDateString(),
        'status' => 'active',
    ]);

    // Pago completado
    Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $cobrador->id,
        'credit_id' => $credit->id,
        'amount' => 100.00,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'status' => 'completed',
        'installment_number' => 1,
    ]);

    // Solicitar el reporte en HTML
    $response = $this->get('/api/reports/payments?format=html');

    $response->assertSuccessful();

    // Encabezados nuevos
    $response->assertSee('Cobrador / Cliente / Estado', false);
    $response->assertSee('Falta para crédito', false);

    // Contenido combinado
    $response->assertSee('Cobrador:', false);
    $response->assertSee('Cliente:', false);
    $response->assertSee('Estado:', false);

    // Valores
    $response->assertSee('COBRADOR UNO', false);
    $response->assertSee('CLIENTE UNO', false);
    $response->assertSee('completed', false);
});
