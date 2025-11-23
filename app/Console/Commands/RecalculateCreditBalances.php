<?php

namespace App\Console\Commands;

use App\Models\Credit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecalculateCreditBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:recalculate-balances {--dry-run : Mostrar cambios sin aplicarlos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula balance, total_paid y paid_installments de todos los créditos activos basándose en sus pagos reales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 Modo DRY RUN - No se aplicarán cambios');
        }

        $this->info('🔄 Recalculando balances de créditos...');

        // Obtener todos los créditos activos y completados
        $credits = Credit::whereIn('status', ['active', 'completed'])
            ->with('payments')
            ->get();

        $this->info("📊 Se encontraron {$credits->count()} créditos para revisar");

        $fixed = 0;
        $errors = 0;
        $alreadyCorrect = 0;

        foreach ($credits as $credit) {
            try {
                // Guardar valores actuales para comparar
                $oldBalance = $credit->balance;
                $oldTotalPaid = $credit->total_paid;
                $oldPaidInstallments = $credit->paid_installments;

                // Recalcular
                $hasChanges = $credit->recalculateBalance();

                if (!$hasChanges) {
                    $alreadyCorrect++;
                    $this->line("✅ Crédito #{$credit->id} - Ya está correcto");
                    continue;
                }

                // Mostrar cambios detectados
                $this->warn("⚠️  Crédito #{$credit->id} - Cambios detectados:");

                if ($credit->balance != $oldBalance) {
                    $this->line("   - Balance: {$oldBalance} → {$credit->balance}");
                }
                if ($credit->total_paid != $oldTotalPaid) {
                    $this->line("   - Total Pagado: {$oldTotalPaid} → {$credit->total_paid}");
                }
                if ($credit->paid_installments != $oldPaidInstallments) {
                    $this->line("   - Cuotas Pagadas: {$oldPaidInstallments} → {$credit->paid_installments}");
                }

                if (!$dryRun) {
                    // Guardar cambios
                    $credit->save();
                    $this->info("✅ Crédito #{$credit->id} - Corregido");
                    $fixed++;
                } else {
                    $this->info("   [DRY RUN] Se corregiría este crédito");
                    $fixed++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("❌ Error en crédito #{$credit->id}: {$e->getMessage()}");
                Log::error("Error recalculando balance para crédito {$credit->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine();
        $this->info('📊 Resumen:');
        $this->line("   - Ya correctos: {$alreadyCorrect}");
        $this->line("   - " . ($dryRun ? 'Para corregir' : 'Corregidos') . ": {$fixed}");
        if ($errors > 0) {
            $this->error("   - Errores: {$errors}");
        }

        if ($dryRun && $fixed > 0) {
            $this->newLine();
            $this->warn('⚠️  Ejecuta sin --dry-run para aplicar los cambios');
        }

        if (!$dryRun && $fixed > 0) {
            $this->newLine();
            $this->info('✅ Balances recalculados exitosamente');
        }

        return Command::SUCCESS;
    }
}
