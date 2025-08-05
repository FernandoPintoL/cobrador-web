<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Credit;
use App\Models\User;
use Carbon\Carbon;

class WaitingListCreditsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener usuarios necesarios
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $manager = User::whereHas('roles', function ($query) {
            $query->where('name', 'manager');
        })->first();

        $cobrador = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })->first();

        $clients = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })->take(5)->get();

        if (!$admin || !$manager || !$cobrador || $clients->count() < 5) {
            $this->command->warn('No hay suficientes usuarios con roles para crear los créditos de ejemplo.');
            return;
        }

        $this->command->info('Creando créditos de ejemplo para el sistema de lista de espera...');

        // 1. Créditos pendientes de aprobación
        $this->createPendingApprovalCredits($clients, $cobrador);

        // 2. Créditos en lista de espera (aprobados)
        $this->createWaitingDeliveryCredits($clients, $cobrador, $manager);

        // 3. Créditos listos para entrega hoy
        $this->createReadyTodayCredits($clients, $cobrador, $manager);

        // 4. Créditos atrasados para entrega
        $this->createOverdueCredits($clients, $cobrador, $manager);

        // 5. Créditos rechazados
        $this->createRejectedCredits($clients, $cobrador, $admin);

        $this->command->info('✅ Créditos de ejemplo creados exitosamente!');
    }

    private function createPendingApprovalCredits($clients, $cobrador)
    {
        $this->command->info('📋 Creando créditos pendientes de aprobación...');

        for ($i = 0; $i < 3; $i++) {
            Credit::create([
                'client_id' => $clients[$i]->id,
                'created_by' => $cobrador->id,
                'amount' => fake()->randomFloat(2, 500, 2000),
                'interest_rate' => fake()->randomFloat(2, 15, 25),
                'frequency' => fake()->randomElement(['daily', 'weekly', 'biweekly', 'monthly']),
                'start_date' => now()->addDays(rand(1, 7)),
                'end_date' => now()->addDays(rand(15, 45)),
                'status' => 'pending_approval',
            ]);
        }
    }

    private function createWaitingDeliveryCredits($clients, $cobrador, $manager)
    {
        $this->command->info('⏳ Creando créditos en lista de espera...');

        for ($i = 0; $i < 4; $i++) {
            $credit = Credit::create([
                'client_id' => $clients[$i + 1]->id,
                'created_by' => $cobrador->id,
                'amount' => fake()->randomFloat(2, 800, 3000),
                'interest_rate' => fake()->randomFloat(2, 18, 22),
                'frequency' => fake()->randomElement(['daily', 'weekly']),
                'start_date' => now()->addDays(rand(2, 10)),
                'end_date' => now()->addDays(rand(20, 50)),
                'status' => 'waiting_delivery',
                'approved_by' => $manager->id,
                'approved_at' => now()->subDays(rand(1, 3)),
                'scheduled_delivery_date' => now()->addDays(rand(1, 5))->setTime(rand(9, 17), rand(0, 59)),
                'delivery_notes' => fake()->sentence(),
            ]);

            $this->command->line("  - Cliente: {$credit->client->name} | Entrega: {$credit->scheduled_delivery_date->format('Y-m-d H:i')}");
        }
    }

    private function createReadyTodayCredits($clients, $cobrador, $manager)
    {
        $this->command->info('🚀 Creando créditos listos para entrega hoy...');

        for ($i = 0; $i < 2; $i++) {
            $credit = Credit::create([
                'client_id' => $clients[$i + 2]->id,
                'created_by' => $cobrador->id,
                'amount' => fake()->randomFloat(2, 1000, 2500),
                'interest_rate' => 20.00,
                'frequency' => 'daily',
                'start_date' => now(),
                'end_date' => now()->addDays(30),
                'status' => 'waiting_delivery',
                'approved_by' => $manager->id,
                'approved_at' => now()->subDay(),
                'scheduled_delivery_date' => now()->setTime(rand(10, 16), 0),
                'delivery_notes' => 'Listo para entrega hoy',
            ]);

            $this->command->line("  - Cliente: {$credit->client->name} | Hora: {$credit->scheduled_delivery_date->format('H:i')}");
        }
    }

    private function createOverdueCredits($clients, $cobrador, $manager)
    {
        $this->command->info('⚠️ Creando créditos atrasados para entrega...');

        $credit = Credit::create([
            'client_id' => $clients[3]->id,
            'created_by' => $cobrador->id,
            'amount' => 1500.00,
            'interest_rate' => 18.00,
            'frequency' => 'weekly',
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(28),
            'status' => 'waiting_delivery',
            'approved_by' => $manager->id,
            'approved_at' => now()->subDays(4),
            'scheduled_delivery_date' => now()->subDays(2)->setTime(14, 0),
            'delivery_notes' => 'Cliente no se encontraba en domicilio',
        ]);

        $this->command->line("  - Cliente: {$credit->client->name} | Atrasado: {$credit->getDaysOverdueForDelivery()} días");
    }

    private function createRejectedCredits($clients, $cobrador, $admin)
    {
        $this->command->info('❌ Creando créditos rechazados...');

        $credit = Credit::create([
            'client_id' => $clients[4]->id,
            'created_by' => $cobrador->id,
            'amount' => 5000.00,
            'interest_rate' => 25.00,
            'frequency' => 'monthly',
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'status' => 'rejected',
            'approved_by' => $admin->id,
            'approved_at' => now()->subHours(2),
            'rejection_reason' => 'Monto excede el límite permitido para el perfil del cliente',
        ]);

        $this->command->line("  - Cliente: {$credit->client->name} | Motivo: {$credit->rejection_reason}");
    }
}
