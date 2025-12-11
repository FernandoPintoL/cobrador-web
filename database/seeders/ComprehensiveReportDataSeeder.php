<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use App\Models\InterestRate;
use Carbon\Carbon;

/**
 * Seeder completo para generar datos de prueba para reportes
 *
 * Crea créditos en TODOS los estados posibles:
 * - pending_approval: Esperando aprobación
 * - waiting_delivery: Aprobado, esperando entrega
 * - active: Activo (varios niveles de pago)
 * - completed: Completamente pagado
 * - defaulted: En mora grave
 * - cancelled: Cancelado
 * - rejected: Rechazado
 *
 * Uso:
 *   php artisan db:seed --class=ComprehensiveReportDataSeeder
 */
class ComprehensiveReportDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📊 Generando datos completos para reportes...');
        $this->command->newLine();

        // Obtener usuarios necesarios
        $manager = User::whereHas('roles', function ($query) {
            $query->where('name', 'manager');
        })->first();

        $cobrador = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })->first();

        if (!$manager || !$cobrador) {
            $this->command->error('❌ No hay usuarios con roles de manager o cobrador.');
            $this->command->info('Ejecuta primero: php artisan db:seed');
            return;
        }

        // Obtener o crear tasa de interés
        $interestRate = InterestRate::where('is_active', true)->first();
        if (!$interestRate) {
            $interestRate = InterestRate::create([
                'name' => 'Tasa estándar',
                'rate' => 20.00,
                'is_active' => true,
                'created_by' => $manager->id,
            ]);
        }

        // Crear o obtener clientes
        $clients = $this->ensureClients($cobrador, $manager, 15);

        $this->command->info("✓ Encontrados {$clients->count()} clientes");
        $this->command->newLine();

        // Crear créditos en diferentes estados
        $clientIndex = 0;

        // 1. PENDING_APPROVAL - 2 créditos esperando aprobación
        $this->command->info('1️⃣  Creando créditos PENDING_APPROVAL...');
        $this->createPendingApprovalCredits($clients, $cobrador, $interestRate, $clientIndex);
        $clientIndex += 2;

        // 2. WAITING_DELIVERY - 2 créditos aprobados esperando entrega
        $this->command->info('2️⃣  Creando créditos WAITING_DELIVERY...');
        $this->createWaitingDeliveryCredits($clients, $cobrador, $manager, $interestRate, $clientIndex);
        $clientIndex += 2;

        // 3. REJECTED - 1 crédito rechazado
        $this->command->info('3️⃣  Creando créditos REJECTED...');
        $this->createRejectedCredit($clients, $cobrador, $manager, $interestRate, $clientIndex);
        $clientIndex += 1;

        // 4. ACTIVE - 5 créditos activos con diferentes niveles de pago
        $this->command->info('4️⃣  Creando créditos ACTIVE...');
        $this->createActiveCredits($clients, $cobrador, $manager, $interestRate, $clientIndex);
        $clientIndex += 5;

        // 5. DEFAULTED - 2 créditos en mora grave
        $this->command->info('5️⃣  Creando créditos DEFAULTED...');
        $this->createDefaultedCredits($clients, $cobrador, $manager, $interestRate, $clientIndex);
        $clientIndex += 2;

        // 6. COMPLETED - 2 créditos completamente pagados
        $this->command->info('6️⃣  Creando créditos COMPLETED...');
        $this->createCompletedCredits($clients, $cobrador, $manager, $interestRate, $clientIndex);
        $clientIndex += 2;

        // 7. CANCELLED - 1 crédito cancelado
        $this->command->info('7️⃣  Creando créditos CANCELLED...');
        $this->createCancelledCredit($clients, $cobrador, $manager, $interestRate, $clientIndex);

        $this->command->newLine();
        $this->command->info('✅ ¡Datos completos para reportes generados exitosamente!');
        $this->command->info('📈 Ahora tienes créditos en todos los estados posibles.');
    }

    /**
     * Asegurar que existan suficientes clientes
     */
    private function ensureClients($cobrador, $manager, $needed)
    {
        $clients = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })->get();

        if ($clients->count() < $needed) {
            $this->command->info("👥 Creando " . ($needed - $clients->count()) . " clientes adicionales...");

            for ($i = $clients->count(); $i < $needed; $i++) {
                $client = User::create([
                    'name' => "Cliente Reporte {$i}",
                    'email' => "cliente.reporte{$i}@example.com",
                    'ci' => '2000000' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'phone' => '61' . rand(1000000, 9999999),
                    'password' => bcrypt('password'),
                    'assigned_cobrador_id' => $cobrador->id,
                    'assigned_manager_id' => $manager->id,
                ]);
                $client->assignRole('client');
                $clients->push($client);
            }
        }

        return $clients;
    }

    /**
     * Crear créditos en estado PENDING_APPROVAL
     */
    private function createPendingApprovalCredits($clients, $cobrador, $interestRate, $startIndex)
    {
        $configs = [
            [
                'amount' => 2000.00,
                'installments' => 8,
                'frequency' => 'weekly',
                'days_ago' => 2,
            ],
            [
                'amount' => 3500.00,
                'installments' => 12,
                'frequency' => 'weekly',
                'days_ago' => 5,
            ],
        ];

        foreach ($configs as $index => $config) {
            $client = $clients[$startIndex + $index];

            // Fechas temporales (se actualizarán cuando se apruebe y entregue)
            $tempStartDate = Carbon::now()->addDays(7);
            $tempEndDate = $this->calculateEndDate($tempStartDate, $config['installments'], $config['frequency']);

            Credit::create([
                'client_id' => $client->id,
                'created_by' => $cobrador->id,
                'amount' => $config['amount'],
                'interest_rate' => $interestRate->rate,
                'interest_rate_id' => $interestRate->id,
                'total_installments' => $config['installments'],
                'frequency' => $config['frequency'],
                'status' => 'pending_approval',
                'start_date' => $tempStartDate,
                'end_date' => $tempEndDate,
                'created_at' => Carbon::now()->subDays($config['days_ago']),
            ]);

            $this->command->line("  ✓ Crédito pendiente: {$config['amount']} Bs | {$config['installments']} cuotas");
        }

        $this->command->newLine();
    }

    /**
     * Crear créditos en estado WAITING_DELIVERY
     */
    private function createWaitingDeliveryCredits($clients, $cobrador, $manager, $interestRate, $startIndex)
    {
        $configs = [
            [
                'amount' => 2500.00,
                'installments' => 10,
                'frequency' => 'weekly',
                'approved_days_ago' => 3,
                'scheduled_delivery' => Carbon::now()->addDays(1), // Entrega programada para mañana
            ],
            [
                'amount' => 1800.00,
                'installments' => 6,
                'frequency' => 'biweekly',
                'approved_days_ago' => 1,
                'scheduled_delivery' => Carbon::now(), // Entrega programada para hoy (listo para entregar)
            ],
        ];

        foreach ($configs as $index => $config) {
            $client = $clients[$startIndex + $index];

            // Fechas temporales basadas en la fecha programada de entrega
            $tempStartDate = $config['scheduled_delivery']->copy()->addDay();
            $tempEndDate = $this->calculateEndDate($tempStartDate, $config['installments'], $config['frequency']);

            Credit::create([
                'client_id' => $client->id,
                'created_by' => $cobrador->id,
                'approved_by' => $manager->id,
                'approved_at' => Carbon::now()->subDays($config['approved_days_ago']),
                'amount' => $config['amount'],
                'interest_rate' => $interestRate->rate,
                'interest_rate_id' => $interestRate->id,
                'total_installments' => $config['installments'],
                'frequency' => $config['frequency'],
                'status' => 'waiting_delivery',
                'scheduled_delivery_date' => $config['scheduled_delivery'],
                'start_date' => $tempStartDate,
                'end_date' => $tempEndDate,
                'immediate_delivery_requested' => false,
            ]);

            $deliveryInfo = $config['scheduled_delivery']->isToday() ? 'HOY' : $config['scheduled_delivery']->format('Y-m-d');
            $this->command->line("  ✓ Crédito aprobado: {$config['amount']} Bs | Entrega: {$deliveryInfo}");
        }

        $this->command->newLine();
    }

    /**
     * Crear crédito REJECTED
     */
    private function createRejectedCredit($clients, $cobrador, $manager, $interestRate, $startIndex)
    {
        $client = $clients[$startIndex];

        // Fechas temporales (el crédito nunca se entregará)
        $tempStartDate = Carbon::now()->addDays(7);
        $tempEndDate = $this->calculateEndDate($tempStartDate, 15, 'weekly');

        Credit::create([
            'client_id' => $client->id,
            'created_by' => $cobrador->id,
            'approved_by' => $manager->id,
            'approved_at' => Carbon::now()->subDays(2),
            'amount' => 5000.00,
            'interest_rate' => $interestRate->rate,
            'interest_rate_id' => $interestRate->id,
            'total_installments' => 15,
            'frequency' => 'weekly',
            'status' => 'rejected',
            'start_date' => $tempStartDate,
            'end_date' => $tempEndDate,
            'rejection_reason' => 'Cliente no cumple con los requisitos de ingresos mínimos',
        ]);

        $this->command->line("  ✓ Crédito rechazado: 5000.00 Bs | Razón: Requisitos no cumplidos");
        $this->command->newLine();
    }

    /**
     * Crear créditos ACTIVE con diferentes niveles de pago
     */
    private function createActiveCredits($clients, $cobrador, $manager, $interestRate, $startIndex)
    {
        $configs = [
            [
                'name' => 'Crédito al día (100%)',
                'amount' => 1500.00,
                'installments' => 6,
                'frequency' => 'weekly',
                'days_ago' => 42,
                'payment_percentage' => 1.0, // 100% de pagos esperados
            ],
            [
                'name' => 'Crédito al día (80%)',
                'amount' => 2000.00,
                'installments' => 8,
                'frequency' => 'weekly',
                'days_ago' => 35,
                'payment_percentage' => 0.8, // 80% de pagos esperados
            ],
            [
                'name' => 'Crédito con atraso leve (60%)',
                'amount' => 1800.00,
                'installments' => 10,
                'frequency' => 'weekly',
                'days_ago' => 56,
                'payment_percentage' => 0.6, // 60% de pagos esperados
            ],
            [
                'name' => 'Crédito con atraso moderado (40%)',
                'amount' => 2200.00,
                'installments' => 12,
                'frequency' => 'biweekly',
                'days_ago' => 84,
                'payment_percentage' => 0.4, // 40% de pagos esperados
            ],
            [
                'name' => 'Crédito recién entregado (0%)',
                'amount' => 1200.00,
                'installments' => 5,
                'frequency' => 'weekly',
                'days_ago' => 3,
                'payment_percentage' => 0.0, // Sin pagos aún
            ],
        ];

        foreach ($configs as $index => $config) {
            $client = $clients[$startIndex + $index];

            $deliveredDate = Carbon::now()->subDays($config['days_ago']);
            $startDate = $deliveredDate->copy()->addDay();
            $endDate = $this->calculateEndDate($startDate, $config['installments'], $config['frequency']);

            $credit = Credit::create([
                'client_id' => $client->id,
                'created_by' => $cobrador->id,
                'approved_by' => $manager->id,
                'delivered_by' => $cobrador->id,
                'amount' => $config['amount'],
                'interest_rate' => $interestRate->rate,
                'interest_rate_id' => $interestRate->id,
                'total_installments' => $config['installments'],
                'frequency' => $config['frequency'],
                'status' => 'active',
                'approved_at' => $deliveredDate->copy()->subDays(2),
                'delivered_at' => $deliveredDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'delivery_notes' => 'Entrega en efectivo',
                'latitude' => fake()->latitude(),
                'longitude' => fake()->longitude(),
            ]);

            // Crear pagos según el porcentaje especificado
            $this->createPaymentsForActiveCredit($credit, $cobrador, $config['payment_percentage']);

            $this->command->line("  ✓ {$config['name']}: {$config['amount']} Bs | Pagos: " .
                round($config['payment_percentage'] * 100) . "%");
        }

        $this->command->newLine();
    }

    /**
     * Crear créditos DEFAULTED (en mora grave)
     */
    private function createDefaultedCredits($clients, $cobrador, $manager, $interestRate, $startIndex)
    {
        $configs = [
            [
                'amount' => 3000.00,
                'installments' => 10,
                'frequency' => 'weekly',
                'days_ago' => 90,
                'payment_percentage' => 0.2, // Solo 20% de pagos (muy atrasado)
            ],
            [
                'amount' => 2500.00,
                'installments' => 8,
                'frequency' => 'biweekly',
                'days_ago' => 120,
                'payment_percentage' => 0.1, // Solo 10% de pagos (casi sin pagar)
            ],
        ];

        foreach ($configs as $index => $config) {
            $client = $clients[$startIndex + $index];

            $deliveredDate = Carbon::now()->subDays($config['days_ago']);
            $startDate = $deliveredDate->copy()->addDay();
            $endDate = $this->calculateEndDate($startDate, $config['installments'], $config['frequency']);

            $credit = Credit::create([
                'client_id' => $client->id,
                'created_by' => $cobrador->id,
                'approved_by' => $manager->id,
                'delivered_by' => $cobrador->id,
                'amount' => $config['amount'],
                'interest_rate' => $interestRate->rate,
                'interest_rate_id' => $interestRate->id,
                'total_installments' => $config['installments'],
                'frequency' => $config['frequency'],
                'status' => 'defaulted',
                'approved_at' => $deliveredDate->copy()->subDays(2),
                'delivered_at' => $deliveredDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'delivery_notes' => 'Entrega en efectivo',
                'latitude' => fake()->latitude(),
                'longitude' => fake()->longitude(),
            ]);

            // Crear pocos pagos para simular mora
            $this->createPaymentsForActiveCredit($credit, $cobrador, $config['payment_percentage']);

            $this->command->line("  ✓ Crédito en mora: {$config['amount']} Bs | Pagado solo: " .
                round($config['payment_percentage'] * 100) . "%");
        }

        $this->command->newLine();
    }

    /**
     * Crear créditos COMPLETED (completamente pagados)
     */
    private function createCompletedCredits($clients, $cobrador, $manager, $interestRate, $startIndex)
    {
        $configs = [
            [
                'amount' => 1500.00,
                'installments' => 5,
                'frequency' => 'weekly',
                'days_ago' => 45,
            ],
            [
                'amount' => 2000.00,
                'installments' => 8,
                'frequency' => 'biweekly',
                'days_ago' => 120,
            ],
        ];

        foreach ($configs as $index => $config) {
            $client = $clients[$startIndex + $index];

            $deliveredDate = Carbon::now()->subDays($config['days_ago']);
            $startDate = $deliveredDate->copy()->addDay();
            $endDate = $this->calculateEndDate($startDate, $config['installments'], $config['frequency']);

            $credit = Credit::create([
                'client_id' => $client->id,
                'created_by' => $cobrador->id,
                'approved_by' => $manager->id,
                'delivered_by' => $cobrador->id,
                'amount' => $config['amount'],
                'interest_rate' => $interestRate->rate,
                'interest_rate_id' => $interestRate->id,
                'total_installments' => $config['installments'],
                'frequency' => $config['frequency'],
                'status' => 'completed',
                'approved_at' => $deliveredDate->copy()->subDays(2),
                'delivered_at' => $deliveredDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'delivery_notes' => 'Entrega en efectivo',
                'latitude' => fake()->latitude(),
                'longitude' => fake()->longitude(),
            ]);

            // Crear TODOS los pagos (100%)
            $this->createPaymentsForActiveCredit($credit, $cobrador, 1.0);

            $this->command->line("  ✓ Crédito completado: {$config['amount']} Bs | 100% pagado");
        }

        $this->command->newLine();
    }

    /**
     * Crear crédito CANCELLED
     */
    private function createCancelledCredit($clients, $cobrador, $manager, $interestRate, $startIndex)
    {
        $client = $clients[$startIndex];

        $deliveredDate = Carbon::now()->subDays(15);
        $startDate = $deliveredDate->copy()->addDay();
        $endDate = $this->calculateEndDate($startDate, 10, 'weekly');

        $credit = Credit::create([
            'client_id' => $client->id,
            'created_by' => $cobrador->id,
            'approved_by' => $manager->id,
            'delivered_by' => $cobrador->id,
            'amount' => 1800.00,
            'interest_rate' => $interestRate->rate,
            'interest_rate_id' => $interestRate->id,
            'total_installments' => 10,
            'frequency' => 'weekly',
            'status' => 'cancelled',
            'approved_at' => $deliveredDate->copy()->subDays(2),
            'delivered_at' => $deliveredDate,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'delivery_notes' => 'Crédito cancelado por acuerdo mutuo',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ]);

        // Crear algunos pagos antes de la cancelación (30%)
        $this->createPaymentsForActiveCredit($credit, $cobrador, 0.3);

        $this->command->line("  ✓ Crédito cancelado: 1800.00 Bs | Razón: Acuerdo mutuo");
        $this->command->newLine();
    }

    /**
     * Crear pagos para un crédito activo según porcentaje de cuotas esperadas
     */
    private function createPaymentsForActiveCredit($credit, $cobrador, $paymentPercentage)
    {
        // Calcular cuántas cuotas se esperarían haber pagado a esta fecha
        $schedule = $credit->getPaymentSchedule();
        $today = Carbon::now();

        $expectedInstallments = 0;
        foreach ($schedule as $installment) {
            $dueDate = Carbon::parse($installment['due_date']);
            if ($dueDate->lte($today)) {
                $expectedInstallments++;
            }
        }

        // Calcular cuántas cuotas realmente pagar según el porcentaje
        $installmentsToPay = floor($expectedInstallments * $paymentPercentage);

        $totalPaid = 0;
        $paidInstallments = 0;

        for ($i = 0; $i < $installmentsToPay; $i++) {
            if (!isset($schedule[$i])) break;

            $installment = $schedule[$i];
            $paymentDate = Carbon::parse($installment['due_date']);

            Payment::create([
                'client_id' => $credit->client_id,
                'cobrador_id' => $cobrador->id,
                'credit_id' => $credit->id,
                'amount' => $credit->installment_amount,
                'payment_date' => $paymentDate,
                'payment_method' => fake()->randomElement(['cash', 'transfer', 'card']),
                'status' => 'completed',
                'installment_number' => $installment['installment_number'],
                'received_by' => $cobrador->id,
                'latitude' => fake()->latitude(),
                'longitude' => fake()->longitude(),
            ]);

            $totalPaid += $credit->installment_amount;
            $paidInstallments++;
        }

        // Actualizar el crédito con los totales
        $credit->update([
            'paid_installments' => $paidInstallments,
            'total_paid' => $totalPaid,
            'balance' => max(0, $credit->total_amount - $totalPaid),
        ]);
    }

    /**
     * Calcular fecha final del crédito
     */
    private function calculateEndDate($startDate, $totalInstallments, $frequency)
    {
        $endDate = $startDate->copy();

        switch ($frequency) {
            case 'daily':
                $endDate->addDays($totalInstallments - 1);
                break;
            case 'weekly':
                $endDate->addWeeks($totalInstallments - 1);
                break;
            case 'biweekly':
                $endDate->addWeeks(($totalInstallments - 1) * 2);
                break;
            case 'monthly':
                $endDate->addMonths($totalInstallments - 1);
                break;
        }

        // Si cae domingo, mover al lunes
        if ($endDate->dayOfWeek === 0) {
            $endDate->addDay();
        }

        return $endDate;
    }
}
