<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Empresa 1: Empresa Inicial (datos existentes migrados aquí)
        $tenant1 = Tenant::create([
            'name' => 'Empresa Inicial',
            'slug' => 'empresa-inicial',
            'email' => 'contacto@empresa-inicial.com',
            'phone' => '12345678',
            'address' => 'Dirección Empresa Inicial',
            'status' => 'active', // Activa porque ya tienen datos
            'trial_ends_at' => null, // No trial porque ya están activos
            'monthly_price' => 200.00, // Precio mensual en Bs
        ]);

        // Configuraciones de Empresa Inicial
        $tenant1->setSetting('allow_custom_interest_per_credit', true, 'boolean');
        $tenant1->setSetting('max_credits_per_client', 5, 'integer');
        $tenant1->setSetting('default_interest_rate', 20.00, 'decimal');
        $tenant1->setSetting('allow_negative_cash_balance', false, 'boolean');

        // Empresa 2: Empresa Demo (nueva empresa en trial)
        $tenant2 = Tenant::create([
            'name' => 'Empresa Demo',
            'slug' => 'empresa-demo',
            'email' => 'contacto@empresa-demo.com',
            'phone' => '87654321',
            'address' => 'Dirección Empresa Demo',
            'status' => 'trial', // En período de prueba
            'trial_ends_at' => now()->addMonth(), // 1 mes de prueba gratis
            'monthly_price' => 200.00,
        ]);

        // Configuraciones de Empresa Demo (más restrictivas)
        $tenant2->setSetting('allow_custom_interest_per_credit', false, 'boolean'); // NO pueden editar intereses
        $tenant2->setSetting('max_credits_per_client', 3, 'integer'); // Máximo 3 créditos
        $tenant2->setSetting('default_interest_rate', 15.00, 'decimal');
        $tenant2->setSetting('allow_negative_cash_balance', false, 'boolean');

        $this->command->info('✅ 2 Tenants creados exitosamente:');
        $this->command->info("   - {$tenant1->name} (ID: {$tenant1->id}) - Status: {$tenant1->status}");
        $this->command->info("   - {$tenant2->name} (ID: {$tenant2->id}) - Status: {$tenant2->status}");
    }
}
