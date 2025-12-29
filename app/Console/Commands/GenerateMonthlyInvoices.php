<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:generate-invoices {--month= : Mes específico (YYYY-MM)} {--force : Generar incluso si ya existen}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar facturas mensuales para todos los tenants activos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧾 Generando facturas mensuales para tenants activos...');
        $this->newLine();

        // Determinar el período
        $month = $this->option('month')
            ? Carbon::parse($this->option('month'))->startOfMonth()
            : now()->startOfMonth();

        $periodStart = $month->copy();
        $periodEnd = $month->copy()->endOfMonth();

        $this->info("📅 Período: {$periodStart->format('Y-m-d')} a {$periodEnd->format('Y-m-d')}");
        $this->newLine();

        // Obtener tenants activos (no en trial)
        $tenants = Tenant::where('status', 'active')
            ->where('monthly_price', '>', 0)
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No hay tenants activos con precio mensual configurado.');
            return Command::SUCCESS;
        }

        $this->info("🏢 Tenants encontrados: {$tenants->count()}");
        $this->newLine();

        $generated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            try {
                // Verificar si ya existe factura para este período
                $exists = TenantSubscription::where('tenant_id', $tenant->id)
                    ->where('period_start', $periodStart)
                    ->where('period_end', $periodEnd)
                    ->exists();

                if ($exists && !$this->option('force')) {
                    $this->line("⏭️  {$tenant->name}: Factura ya existe para este período");
                    $skipped++;
                    continue;
                }

                // Crear la factura
                $subscription = TenantSubscription::create([
                    'tenant_id' => $tenant->id,
                    'amount' => $tenant->monthly_price,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'status' => 'pending',
                ]);

                $this->info("✅ {$tenant->name}: Factura generada - Monto: {$tenant->monthly_price} Bs");
                $generated++;

            } catch (\Exception $e) {
                $this->error("❌ {$tenant->name}: Error - {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✅ Facturas generadas: {$generated}");
        $this->info("⏭️  Facturas omitidas: {$skipped}");

        if ($errors > 0) {
            $this->error("❌ Errores: {$errors}");
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return Command::SUCCESS;
    }
}
