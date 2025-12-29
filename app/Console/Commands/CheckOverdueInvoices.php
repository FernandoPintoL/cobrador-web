<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Console\Command;

class CheckOverdueInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:check-overdue-invoices
                            {--grace-days=7 : Días de gracia después del vencimiento}
                            {--dry-run : Mostrar qué se haría sin ejecutar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar facturas vencidas y suspender tenants con pagos atrasados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('💰 Verificando facturas vencidas...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $graceDays = (int) $this->option('grace-days');

        if ($dryRun) {
            $this->warn('⚠️  Modo DRY-RUN: No se realizarán cambios');
            $this->newLine();
        }

        $this->info("⏰ Período de gracia: {$graceDays} días");
        $this->newLine();

        // Buscar facturas vencidas (período terminado + días de gracia)
        $gracePeriodEnd = now()->subDays($graceDays)->startOfDay();

        $overdueInvoices = TenantSubscription::where('status', 'pending')
            ->where('period_end', '<', $gracePeriodEnd)
            ->with('tenant')
            ->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('✅ No hay facturas vencidas fuera del período de gracia');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  Facturas vencidas encontradas: {$overdueInvoices->count()}");
        $this->newLine();

        $markedOverdue = 0;
        $suspended = 0;

        foreach ($overdueInvoices as $invoice) {
            $tenant = $invoice->tenant;
            $daysOverdue = now()->diffInDays($invoice->period_end);

            if (!$dryRun) {
                // Marcar factura como vencida
                $invoice->update(['status' => 'overdue']);
                $markedOverdue++;
            }

            // Si el tenant está activo, suspenderlo
            if ($tenant->status === 'active') {
                if ($dryRun) {
                    $this->line("🔸 {$tenant->name}:");
                    $this->line("   - Factura vencida hace {$daysOverdue} días");
                    $this->line("   - Monto: {$invoice->amount} Bs");
                    $this->line("   - Acción: Se suspendería automáticamente");
                } else {
                    $tenant->update(['status' => 'suspended']);

                    $this->error("❌ {$tenant->name}:");
                    $this->error("   - Suspendido por factura vencida hace {$daysOverdue} días");
                    $this->error("   - Monto pendiente: {$invoice->amount} Bs");

                    $suspended++;

                    // Aquí podrías enviar notificación por email
                    // event(new InvoiceOverdue($tenant, $invoice));
                }

                $this->newLine();
            }
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($dryRun) {
            $this->info("📊 Facturas que se marcarían como vencidas: {$overdueInvoices->count()}");
            $this->info("📊 Tenants que se suspenderían: " . $overdueInvoices->filter(fn($i) => $i->tenant->status === 'active')->count());
        } else {
            $this->info("✅ Facturas marcadas como vencidas: {$markedOverdue}");
            $this->info("✅ Tenants suspendidos: {$suspended}");
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return Command::SUCCESS;
    }
}
