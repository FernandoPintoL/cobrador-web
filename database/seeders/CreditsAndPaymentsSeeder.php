<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use App\Models\InterestRate;
use Carbon\Carbon;

class CreditsAndPaymentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏧 Iniciando creación de créditos y pagos para reportes...');

        // Obtener usuarios necesarios
        $manager = User::whereHas('roles', function ($query) {
            $query->where('name', 'manager');
        })->first();

        $cobrador = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })->first();

        if (!$manager || !$cobrador) {
            $this->command->error('❌ No hay usuarios con roles de manager o cobrador.');
            return;
        }

        // Obtener o crear una tasa de interés
        $interestRate = InterestRate::where('is_active', true)->first();
        if (!$interestRate) {
            $interestRate = InterestRate::create([
                'name' => 'Tasa estándar',
                'rate' => 20.00,
                'is_active' => true,
                'created_by' => $manager->id,
            ]);
        }

        // Crear clientes de ejemplo si no existen suficientes
        $clients = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })->limit(5)->get();

        if ($clients->count() < 5) {
            $this->command->info('👥 Creando clientes de ejemplo...');
            for ($i = $clients->count(); $i < 5; $i++) {
                $client = User::factory()->create([
                    'name' => "Cliente Ejemplo {$i}",
                    'email' => "cliente{$i}@example.com",
                    'ci' => '999000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'password' => bcrypt('password'),
                    'assigned_cobrador_id' => $cobrador->id,
                    'assigned_manager_id' => $manager->id,
                ]);
                $client->assignRole('client');
                $clients->push($client);
            }
        }

        // Crear 5 créditos completos con pagos
        $this->createCompleteCredits($clients, $cobrador, $manager, $interestRate);

        $this->command->info('✅ ¡Créditos y pagos creados exitosamente!');
    }

    /**
     * Crear 5 créditos completos con diferentes estados de pagos
     */
    private function createCompleteCredits($clients, $cobrador, $manager, $interestRate)
    {
        $this->command->info('💰 Creando 5 créditos activos con pagos...');

        // Crédito 1: Completamente pagado
        $this->createCredit(
            $clients[0],
            $cobrador,
            $manager,
            $interestRate,
            'Crédito 1 - Completamente Pagado',
            1500.00,
            5,
            'weekly',
            Carbon::now()->subDays(35)
        );

        // Crédito 2: Parcialmente pagado
        $this->createCredit(
            $clients[1],
            $cobrador,
            $manager,
            $interestRate,
            'Crédito 2 - Parcialmente Pagado',
            2000.00,
            8,
            'weekly',
            Carbon::now()->subDays(28)
        );

        // Crédito 3: Recién entregado, sin pagos
        $this->createCredit(
            $clients[2],
            $cobrador,
            $manager,
            $interestRate,
            'Crédito 3 - Recién Entregado',
            1200.00,
            6,
            'biweekly',
            Carbon::now()->subDays(5)
        );

        // Crédito 4: En progreso normal
        $this->createCredit(
            $clients[3],
            $cobrador,
            $manager,
            $interestRate,
            'Crédito 4 - En Progreso',
            2500.00,
            10,
            'weekly',
            Carbon::now()->subDays(21)
        );

        // Crédito 5: Con pagos atrasados
        $this->createCredit(
            $clients[4],
            $cobrador,
            $manager,
            $interestRate,
            'Crédito 5 - Con Atrasos',
            1800.00,
            9,
            'biweekly',
            Carbon::now()->subDays(42)
        );
    }

    /**
     * Crear un crédito activo con cronograma de pagos
     */
    private function createCredit(
        $client,
        $cobrador,
        $manager,
        $interestRate,
        $description,
        $amount,
        $totalInstallments,
        $frequency,
        $deliveredDate
    ) {
        $this->command->line("  📝 {$description}");

        // Crear el crédito
        $credit = Credit::create([
            'client_id' => $client->id,
            'created_by' => $cobrador->id,
            'approved_by' => $manager->id,
            'delivered_by' => $cobrador->id,
            'amount' => $amount,
            'interest_rate' => $interestRate->rate,
            'interest_rate_id' => $interestRate->id,
            'total_installments' => $totalInstallments,
            'frequency' => $frequency,
            'status' => 'active',
            'approved_at' => $deliveredDate->copy()->subDays(2),
            'delivered_at' => $deliveredDate,
            'start_date' => $deliveredDate->copy()->addDay(),
            'end_date' => $this->calculateEndDate($deliveredDate->copy()->addDay(), $totalInstallments, $frequency),
            'delivery_notes' => "Crédito entregado en efectivo al cliente",
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'immediate_delivery_requested' => false,
        ]);

        // Recalcular campos calculados
        $credit->refresh();

        $this->command->line("    ├─ ID: {$credit->id} | Monto: {$credit->amount} | Cuotas: {$credit->total_installments}");
        $this->command->line("    ├─ Monto Total con Interés: {$credit->total_amount}");
        $this->command->line("    ├─ Monto por Cuota: {$credit->installment_amount}");
        $this->command->line("    └─ Período: {$credit->start_date->format('Y-m-d')} a {$credit->end_date->format('Y-m-d')}");

        // Crear pagos según el progreso del crédito
        $this->createPaymentsForCredit($credit, $cobrador, $manager, $description);
    }

    /**
     * Crear pagos para un crédito específico
     */
    private function createPaymentsForCredit($credit, $cobrador, $manager, $description)
    {
        $paymentCount = match ($description) {
            'Crédito 1 - Completamente Pagado' => $credit->total_installments, // Todas las cuotas pagadas
            'Crédito 2 - Parcialmente Pagado' => floor($credit->total_installments * 0.5), // 50% de cuotas
            'Crédito 3 - Recién Entregado' => 0, // Sin pagos
            'Crédito 4 - En Progreso' => floor($credit->total_installments * 0.6), // 60% de cuotas
            'Crédito 5 - Con Atrasos' => floor($credit->total_installments * 0.3), // 30% de cuotas (atrasado)
            default => 0,
        };

        $startDate = Carbon::parse($credit->start_date);
        $paidInstallments = 0;
        $totalPaid = 0;

        for ($i = 1; $i <= $paymentCount; $i++) {
            // Calcular fecha de pago según frecuencia
            $paymentDate = $this->getPaymentDateForInstallment($startDate, $i, $credit->frequency);

            // Crear pago
            $payment = Payment::create([
                'client_id' => $credit->client_id,
                'cobrador_id' => $cobrador->id,
                'credit_id' => $credit->id,
                'amount' => $credit->installment_amount,
                'payment_date' => $paymentDate,
                'payment_method' => fake()->randomElement(['cash', 'transfer', 'card']),
                'status' => 'completed',
                'installment_number' => $i,
                'received_by' => $cobrador->id,
                'latitude' => fake()->latitude(),
                'longitude' => fake()->longitude(),
            ]);

            $paidInstallments++;
            $totalPaid += $payment->amount;

            $this->command->line("      └─ Cuota {$i}/{$credit->total_installments} | Monto: {$payment->amount} | Fecha: {$payment->payment_date->format('Y-m-d')}");
        }

        // Actualizar crédito con totales
        $credit->update([
            'paid_installments' => $paidInstallments,
            'total_paid' => $totalPaid,
            'balance' => max(0, $credit->total_amount - $totalPaid),
        ]);
    }

    /**
     * Calcular la fecha de pago para una cuota específica
     */
    private function getPaymentDateForInstallment($startDate, $installmentNumber, $frequency)
    {
        $paymentDate = $startDate->copy();

        switch ($frequency) {
            case 'daily':
                $paymentDate->addDays($installmentNumber - 1);
                break;
            case 'weekly':
                $paymentDate->addWeeks($installmentNumber - 1);
                break;
            case 'biweekly':
                $paymentDate->addWeeks(($installmentNumber - 1) * 2);
                break;
            case 'monthly':
                $paymentDate->addMonths($installmentNumber - 1);
                break;
        }

        // Si cae domingo, mover al lunes
        if ($paymentDate->dayOfWeek === 0) {
            $paymentDate->addDay();
        }

        return $paymentDate;
    }

    /**
     * Calcular fecha final basada en cantidad de cuotas y frecuencia
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
