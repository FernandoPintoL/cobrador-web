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
    Role::findOrCreate('client');
    Role::findOrCreate('cobrador');

    // Crear usuario admin para créditos
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    // Crear cliente
    $this->client = User::factory()->create();
    $this->client->assignRole('client');

    // Crear cobrador
    $this->cobrador = User::factory()->create();
    $this->cobrador->assignRole('cobrador');
});

describe('getExpectedInstallments', function () {
    it('calculates expected daily installments correctly', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 1000,
            'total_amount' => 1100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(20),
            'status' => 'active',
        ]);

        // Debería esperar 11 pagos (10 días pasados + hoy)
        expect($credit->getExpectedInstallments())->toBe(11);
    });

    it('calculates expected weekly installments correctly', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 1000,
            'total_amount' => 1100,
            'frequency' => 'weekly',
            'start_date' => now()->subWeeks(3),
            'end_date' => now()->addWeeks(5),
            'status' => 'active',
        ]);

        // Debería esperar 4 pagos (3 semanas pasadas + esta semana)
        expect($credit->getExpectedInstallments())->toBe(4);
    });

    it('calculates expected monthly installments correctly', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 1000,
            'total_amount' => 1100,
            'frequency' => 'monthly',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->addMonths(4),
            'status' => 'active',
        ]);

        // Debería esperar 3 pagos (2 meses pasados + este mes)
        expect($credit->getExpectedInstallments())->toBe(3);
    });

    it('returns 0 for credits not yet started', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 1000,
            'total_amount' => 1100,
            'frequency' => 'daily',
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(20),
            'status' => 'active',
        ]);

        expect($credit->getExpectedInstallments())->toBe(0);
    });
});

describe('getCompletedInstallmentsCount', function () {
    it('uses paid_installments field when available', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
            'paid_installments' => 3,
        ]);

        expect($credit->getCompletedInstallmentsCount())->toBe(3);
    });

    it('counts fully paid installments from payments', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
        ]);

        // Crear 2 cuotas completamente pagadas
        Payment::create([
            'credit_id' => $credit->id,
            'client_id' => $this->client->id,
            'cobrador_id' => $this->cobrador->id,
            'amount' => 100,
            'installment_number' => 1,
            'payment_date' => now()->subDays(4),
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        Payment::create([
            'credit_id' => $credit->id,
            'client_id' => $this->client->id,
            'cobrador_id' => $this->cobrador->id,
            'amount' => 100,
            'installment_number' => 2,
            'payment_date' => now()->subDays(3),
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        expect($credit->fresh()->getCompletedInstallmentsCount())->toBe(2);
    });

    it('counts partial payments towards installment completion', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 800,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
        ]);

        // Dos pagos parciales que suman una cuota completa
        Payment::create([
            'credit_id' => $credit->id,
            'client_id' => $this->client->id,
            'cobrador_id' => $this->cobrador->id,
            'amount' => 60,
            'installment_number' => 1,
            'payment_date' => now()->subDays(4),
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        Payment::create([
            'credit_id' => $credit->id,
            'client_id' => $this->client->id,
            'cobrador_id' => $this->cobrador->id,
            'amount' => 40,
            'installment_number' => 1,
            'payment_date' => now()->subDays(3),
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        expect($credit->fresh()->getCompletedInstallmentsCount())->toBe(1);
    });

    it('does not count cancelled or failed payments', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 900,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
        ]);

        Payment::create([
            'credit_id' => $credit->id,
            'client_id' => $this->client->id,
            'cobrador_id' => $this->cobrador->id,
            'amount' => 100,
            'installment_number' => 1,
            'payment_date' => now()->subDays(4),
            'payment_method' => 'cash',
            'status' => 'cancelled',
        ]);

        Payment::create([
            'credit_id' => $credit->id,
            'client_id' => $this->client->id,
            'cobrador_id' => $this->cobrador->id,
            'amount' => 100,
            'installment_number' => 2,
            'payment_date' => now()->subDays(3),
            'payment_method' => 'cash',
            'status' => 'failed',
        ]);

        expect($credit->fresh()->getCompletedInstallmentsCount())->toBe(0);
    });
});

describe('isOverdue', function () {
    it('returns true when completed installments are less than expected', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
            'paid_installments' => 2,
        ]);

        // Esperadas: 6 (5 días + hoy), Completadas: 2 = MORA
        expect($credit->isOverdue())->toBeTrue();
    });

    it('returns false when completed installments match or exceed expected', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 400,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
            'paid_installments' => 6,
        ]);

        // Esperadas: 6, Completadas: 6 = AL DÍA
        expect($credit->isOverdue())->toBeFalse();
    });

    it('returns false for credits not yet started', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 1000,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(20),
            'status' => 'active',
            'paid_installments' => 0,
        ]);

        // Esperadas: 0 (no ha empezado), Completadas: 0 = AL DÍA
        expect($credit->isOverdue())->toBeFalse();
    });
});

describe('getOverdueAmount', function () {
    it('calculates overdue amount correctly', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 700,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
            'paid_installments' => 2,
        ]);

        // Esperadas: 6, Completadas: 2, Atrasadas: 4
        // Monto atrasado: 4 * 100 = 400
        expect($credit->getOverdueAmount())->toBe(400.0);
    });

    it('returns 0 when credit is not overdue', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 400,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
            'paid_installments' => 6,
        ]);

        expect($credit->getOverdueAmount())->toBe(0.0);
    });

    it('handles different frequencies correctly', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 2000,
            'balance' => 1500,
            'total_amount' => 2200,
            'installment_amount' => 200,
            'frequency' => 'weekly',
            'start_date' => now()->subWeeks(4),
            'end_date' => now()->addWeeks(6),
            'status' => 'active',
            'paid_installments' => 2,
        ]);

        // Esperadas: 5 semanas, Completadas: 2, Atrasadas: 3
        // Monto atrasado: 3 * 200 = 600
        expect($credit->getOverdueAmount())->toBe(600.0);
    });
});

describe('getPendingInstallments', function () {
    it('calculates pending installments correctly', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 500,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'total_installments' => 10,
            'frequency' => 'daily',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
            'status' => 'active',
            'paid_installments' => 5,
        ]);

        // Total: 10, Completadas: 5, Pendientes: 5
        expect($credit->getPendingInstallments())->toBe(5);
    });

    it('returns 0 when all installments are paid', function () {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'amount' => 1000,
            'balance' => 0,
            'total_amount' => 1100,
            'installment_amount' => 100,
            'total_installments' => 10,
            'frequency' => 'daily',
            'start_date' => now()->subDays(10),
            'end_date' => now(),
            'status' => 'completed',
            'paid_installments' => 10,
        ]);

        expect($credit->getPendingInstallments())->toBe(0);
    });
});
