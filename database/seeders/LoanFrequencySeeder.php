<?php

namespace Database\Seeders;

use App\Models\LoanFrequency;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class LoanFrequencySeeder extends Seeder
{
    /**
     * Seed loan frequencies for all tenants.
     *
     * Configura las frecuencias de pago disponibles por tenant:
     * - Diaria: 24 cuotas / 28 días (FIJO)
     * - Semanal: 4-24 cuotas / 7 días por cuota (FLEXIBLE)
     * - Quincenal: 2-12 cuotas / 15 días por cuota (FLEXIBLE)
     * - Mensual: 1-6 cuotas / 30 días por cuota (FLEXIBLE)
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->seedFrequenciesForTenant($tenant->id);
        }
    }

    /**
     * Seed frequencies for a specific tenant
     */
    private function seedFrequenciesForTenant(int $tenantId): void
    {
        $frequencies = [
            // FRECUENCIA DIARIA - FIJA (no editable)
            [
                'tenant_id' => $tenantId,
                'code' => 'daily',
                'name' => 'Diario',
                'description' => 'Pago diario de lunes a sábado. Duración fija de 24 cuotas en ~28 días.',
                'is_enabled' => true,
                'is_fixed_duration' => true, // ⭐ NO EDITABLE
                'fixed_installments' => 24,  // ⭐ Siempre 24 cuotas
                'fixed_duration_days' => 28, // ⭐ Siempre ~28 días
                'period_days' => 1,
                'default_installments' => 24,
                'min_installments' => 24,
                'max_installments' => 24,
                'interest_rate' => 20.00,
            ],

            // FRECUENCIA SEMANAL - FLEXIBLE
            [
                'tenant_id' => $tenantId,
                'code' => 'weekly',
                'name' => 'Semanal',
                'description' => 'Pago cada 7 días. Número de cuotas configurable entre 4 y 24.',
                'is_enabled' => true,
                'is_fixed_duration' => false, // ✅ Editable
                'fixed_installments' => null,
                'fixed_duration_days' => null,
                'period_days' => 7,
                'default_installments' => 12, // Sugerencia: 12 cuotas = ~3 meses
                'min_installments' => 4,      // Mínimo: 4 cuotas = ~1 mes
                'max_installments' => 24,     // Máximo: 24 cuotas = ~6 meses
                'interest_rate' => 15.00,
            ],

            // FRECUENCIA QUINCENAL - FLEXIBLE
            [
                'tenant_id' => $tenantId,
                'code' => 'biweekly',
                'name' => 'Quincenal',
                'description' => 'Pago cada 15 días. Número de cuotas configurable entre 2 y 12.',
                'is_enabled' => true,
                'is_fixed_duration' => false, // ✅ Editable
                'fixed_installments' => null,
                'fixed_duration_days' => null,
                'period_days' => 15,
                'default_installments' => 6,  // Sugerencia: 6 cuotas = ~3 meses
                'min_installments' => 2,      // Mínimo: 2 cuotas = ~1 mes
                'max_installments' => 12,     // Máximo: 12 cuotas = ~6 meses
                'interest_rate' => 12.00,
            ],

            // FRECUENCIA MENSUAL - FLEXIBLE
            [
                'tenant_id' => $tenantId,
                'code' => 'monthly',
                'name' => 'Mensual',
                'description' => 'Pago cada 30 días. Número de cuotas configurable entre 1 y 6.',
                'is_enabled' => true,
                'is_fixed_duration' => false, // ✅ Editable
                'fixed_installments' => null,
                'fixed_duration_days' => null,
                'period_days' => 30,
                'default_installments' => 3,  // Sugerencia: 3 cuotas = ~3 meses
                'min_installments' => 1,      // Mínimo: 1 cuota = ~1 mes
                'max_installments' => 6,      // Máximo: 6 cuotas = ~6 meses
                'interest_rate' => 10.00,
            ],
        ];

        foreach ($frequencies as $frequency) {
            LoanFrequency::updateOrCreate(
                [
                    'tenant_id' => $frequency['tenant_id'],
                    'code' => $frequency['code'],
                ],
                $frequency
            );
        }
    }
}
