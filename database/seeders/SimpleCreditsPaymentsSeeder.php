<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

/**
 * Seeder simple para crear créditos y pagos sin crear usuarios nuevos.
 *
 * Uso:
 *   php artisan db:seed --class=SimpleCreditsPaymentsSeeder
 */
class SimpleCreditsPaymentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏧 Creando 5 créditos con pagos para reportes...');

        // Obtener usuarios necesarios
        $cobrador = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })->first();

        $manager = User::whereHas('roles', function ($query) {
            $query->where('name', 'manager');
        })->first();

        if (!$cobrador || !$manager) {
            $this->command->error('❌ No hay usuarios con roles de cobrador o manager.');
            $this->command->info('Crea usuarios primero ejecutando: php artisan db:seed');
            return;
        }

        // Obtener 5 clientes existentes
        $clients = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })->limit(5)->get();

        if ($clients->count() < 5) {
            $this->command->warn("⚠️ Solo hay {$clients->count()} clientes. Se necesitan al menos 5.");
            $this->command->info('Creando clientes adicionales...');

            for ($i = $clients->count(); $i < 5; $i++) {
                $client = User::create([
                    'name' => "Cliente Test {$i}",
                    'email' => "cliente.test{$i}@example.com",
                    'ci' => '1000000' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'phone' => '61' . rand(1000000, 9999999),
                    'password' => bcrypt('password'),
                    'assigned_cobrador_id' => $cobrador->id,
                    'assigned_manager_id' => $manager->id,
                ]);
                $client->assignRole('client');
                $clients->push($client);
            }
        }

        // Crear 5 créditos con diferentes estados
        $creditConfigs = [
            [
                'name' => 'Crédito 1 - Completamente Pagado',
                'amount' => 1500.00,
                'installments' => 5,
                'frequency' => 'weekly',
                'days_ago' => 35,
                'paid_count' => 5, // Todas las cuotas
            ],
            [
                'name' => 'Crédito 2 - Parcialmente Pagado (50%)',
                'amount' => 2000.00,
                'installments' => 8,
                'frequency' => 'weekly',
                'days_ago' => 28,
                'paid_count' => 4, // 50% de cuotas
            ],
            [
                'name' => 'Crédito 3 - Recién Entregado (Sin Pagos)',
                'amount' => 1200.00,
                'installments' => 6,
                'frequency' => 'biweekly',
                'days_ago' => 5,
                'paid_count' => 0, // Sin pagos
            ],
            [
                'name' => 'Crédito 4 - En Progreso (60%)',
                'amount' => 2500.00,
                'installments' => 10,
                'frequency' => 'weekly',
                'days_ago' => 21,
                'paid_count' => 6, // 60% de cuotas
            ],
            [
                'name' => 'Crédito 5 - Con Atrasos (30%)',
                'amount' => 1800.00,
                'installments' => 9,
                'frequency' => 'biweekly',
                'days_ago' => 42,
                'paid_count' => 3, // 30% de cuotas
            ],
        ];

        $this->command->newLine();

        foreach ($creditConfigs as $index => $config) {
            if (!isset($clients[$index])) {
                continue;
            }

            $this->createCreditWithPayments(
                $clients[$index],
                $cobrador,
                $manager,
                $config
            );
        }

        $this->command->newLine();
        $this->command->info('✅ ¡Créditos y pagos creados exitosamente!');
        $this->command->info('Puedes usar estos datos en tus reportes.');
    }

    /**
     * Crear un crédito con sus pagos
     */
    private function createCreditWithPayments($client, $cobrador, $manager, $config)
    {
        $this->command->line("📝 {$config['name']}");

        // Calcular fechas
        $deliveredDate = Carbon::now()->subDays($config['days_ago']);
        $startDate = $deliveredDate->copy()->addDay();

        // Calcular fecha final
        $endDate = $this->calculateEndDate(
            $startDate,
            $config['installments'],
            $config['frequency']
        );

        // Crear crédito
        $credit = Credit::create([
            'client_id' => $client->id,
            'created_by' => $cobrador->id,
            'approved_by' => $manager->id,
            'delivered_by' => $cobrador->id,
            'amount' => $config['amount'],
            'interest_rate' => 20.00,
            'total_installments' => $config['installments'],
            'frequency' => $config['frequency'],
            'status' => 'active',
            'approved_at' => $deliveredDate->copy()->subDays(2),
            'delivered_at' => $deliveredDate,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'delivery_notes' => 'Entrega en efectivo al cliente',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ]);

        $this->command->line("  ├─ ID: {$credit->id} | Monto: {$credit->amount} Bs");
        $this->command->line("  ├─ Cuotas: {$credit->total_installments} ({$config['frequency']})");
        $this->command->line("  ├─ Monto Total: {$credit->total_amount} Bs");
        $this->command->line("  ├─ Cuota: {$credit->installment_amount} Bs");

        // Crear pagos
        $paidInstallments = 0;
        $totalPaid = 0.00;

        for ($i = 1; $i <= $config['paid_count']; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i, $config['frequency']);

            Payment::create([
                'client_id' => $client->id,
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
            $totalPaid += $credit->installment_amount;
        }

        // Actualizar resumen de crédito
        $credit->update([
            'paid_installments' => $paidInstallments,
            'total_paid' => $totalPaid,
            'balance' => max(0, $credit->total_amount - $totalPaid),
        ]);

        // Mostrar información
        if ($config['paid_count'] > 0) {
            $progress = round(($paidInstallments / $credit->total_installments) * 100);
            $this->command->line("  ├─ Pagos: {$paidInstallments}/{$credit->total_installments} cuotas ({$progress}%)");
            $this->command->line("  └─ Total Pagado: {$totalPaid} Bs | Balance: {$credit->balance} Bs");
        } else {
            $this->command->line("  └─ Sin pagos | Balance: {$credit->balance} Bs");
        }

        $this->command->newLine();
    }

    /**
     * Calcular fecha de pago para una cuota
     */
    private function getPaymentDate($startDate, $installmentNumber, $frequency)
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
