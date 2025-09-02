<?php

use App\Models\ClientCategory;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('maps overdue counts to categories using ranges', function () {
    // Rango por defecto: A=0, B=1-3, C=4+
    expect(ClientCategory::findForOverdueCount(0)?->code)->toBe('A');
    expect(ClientCategory::findForOverdueCount(1)?->code)->toBe('B');
    expect(ClientCategory::findForOverdueCount(3)?->code)->toBe('B');
    expect(ClientCategory::findForOverdueCount(4)?->code)->toBe('C');
    expect(ClientCategory::findForOverdueCount(20)?->code)->toBe('C');
});

it('recalculates client category based on overdue installments', function () {
    // Crear cliente simple
    $client = User::factory()->create();

    // Crear un crédito activo con pagos diarios iniciado hace 4 días (esperados ~5 cuotas)
    $start = Carbon::now()->subDays(4)->startOfDay();

    $credit = Credit::create([
        'client_id' => $client->id,
        'created_by' => $client->id,
        'amount' => 1000,
        'interest_rate' => 10,
        'total_amount' => 1100,
        'balance' => 1100,
        'installment_amount' => 100,
        'total_installments' => 11,
        'frequency' => 'daily',
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->addDays(10)->toDateString(),
        'status' => 'active',
    ]);

    // Sin pagos: debería estar al menos en B o C según días esperados
    $client->recalculateCategoryFromOverdues();
    $firstCategory = $client->client_category;
    expect(in_array($firstCategory, ['B', 'C']))->toBeTrue();

    // Registrar 3 pagos completados para reducir atrasos
    for ($i = 0; $i < 3; $i++) {
        Payment::create([
            'client_id' => $client->id,
            'cobrador_id' => $client->id,
            'credit_id' => $credit->id,
            'amount' => 100,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'status' => 'completed',
            'installment_number' => $i + 1,
        ]);
    }

    // Recalcular categoría
    $client->refresh();
    $client->recalculateCategoryFromOverdues();

    // Con varios pagos, la categoría debería mejorar o mantenerse, nunca empeorar
    $secondCategory = $client->client_category;

    $order = ['A' => 1, 'B' => 2, 'C' => 3];
    expect($order[$secondCategory])->toBeLessThanOrEqual($order[$firstCategory]);
});
