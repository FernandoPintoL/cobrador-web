<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Empresa 1: Empresa Inicial (datos existentes migrados aquí)
        $tenant1 = Tenant::updateOrCreate(
            ['slug' => 'empresa-inicial'], // Buscar por slug
            [
                'name' => 'Empresa Inicial',
                'email' => 'contacto@empresa-inicial.com',
                'phone' => '12345678',
                'address' => 'Dirección Empresa Inicial',
                'status' => 'active', // Activa porque ya tienen datos
                'trial_ends_at' => null, // No trial porque ya están activos
                'monthly_price' => 250.00, // Precio mensual en Bs
            ]
        );

        // Configuraciones de Empresa Inicial
        $tenant1->setSetting('allow_custom_interest_per_credit', false, 'boolean'); // NO puede editar interés
        $tenant1->setSetting('max_credits_per_client', 5, 'integer');
        $tenant1->setSetting('default_interest_rate', 20.00, 'decimal');
        $tenant1->setSetting('allow_negative_cash_balance', false, 'boolean');
        $tenant1->setSetting('auto_suspend_overdue_credits', true, 'boolean');
        $tenant1->setSetting('allow_custom_payment_frequency', false, 'boolean'); // NO puede editar frecuencia
        $tenant1->setSetting('default_payment_frequency', 'mensual', 'string');

        // Crear o actualizar admin para Empresa Inicial
        $admin1 = User::updateOrCreate(
            ['email' => 'admin@empresa-inicial.com'], // Buscar por email
            [
                'name' => 'Admin Empresa Inicial',
                'ci' => 'ADMIN001',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant1->id,
            ]
        );
        if (!$admin1->hasRole('admin')) {
            $admin1->assignRole('admin');
        }

        // Empresa 2: Empresa Rosa
        $tenant2 = Tenant::updateOrCreate(
            ['slug' => 'empresa-rosa'], // Buscar por slug
            [
                'name' => 'Empresa Rosa',
                'email' => 'rosa@empresa.com',
                'phone' => '87654321',
                'address' => 'Dirección Empresa Rosa',
                'status' => 'active',
                'trial_ends_at' => null,
                'monthly_price' => 250.00,
            ]
        );

        // Configuraciones de Empresa Rosa
        $tenant2->setSetting('allow_custom_interest_per_credit', true, 'boolean'); // SÍ puede editar interés
        $tenant2->setSetting('max_credits_per_client', 5, 'integer');
        $tenant2->setSetting('default_interest_rate', 20.00, 'decimal');
        $tenant2->setSetting('allow_negative_cash_balance', false, 'boolean');
        $tenant2->setSetting('auto_suspend_overdue_credits', true, 'boolean');
        $tenant2->setSetting('allow_custom_payment_frequency', true, 'boolean'); // SÍ puede editar frecuencia
        $tenant2->setSetting('default_payment_frequency', 'mensual', 'string');

        // Crear o actualizar admin para Empresa Rosa
        $admin2 = User::updateOrCreate(
            ['email' => 'rosa@empresa-rosa.com'], // Buscar por email
            [
                'name' => 'Rosa',
                'ci' => 'ROSA001',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant2->id,
            ]
        );
        if (!$admin2->hasRole('admin')) {
            $admin2->assignRole('admin');
        }

        $this->command->info('✅ 2 Tenants creados exitosamente:');
        $this->command->info("   - {$tenant1->name} (ID: {$tenant1->id}) - Admin: {$admin1->email}");
        $this->command->info("   - {$tenant2->name} (ID: {$tenant2->id}) - Admin: {$admin2->email}");
    }
}
