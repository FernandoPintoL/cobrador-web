<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use App\Models\Credit;
use Illuminate\Support\Facades\DB;

class UpdatePartialPaymentsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:update-partial-status {--dry-run : Ejecutar sin hacer cambios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el status de pagos parciales a completed cuando la cuota está completa';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN: No se realizarán cambios en la base de datos');
        }

        $this->info('📊 Analizando pagos parciales...');

        // Obtener todos los créditos activos
        $credits = Credit::whereIn('status', ['active', 'paid', 'overdue'])->get();

        $totalUpdated = 0;
        $totalAnalyzed = 0;

        foreach ($credits as $credit) {
            $installmentAmount = (float) $credit->installment_amount;

            if ($installmentAmount <= 0) {
                continue;
            }

            // Obtener pagos agrupados por cuota
            $installmentPayments = Payment::where('credit_id', $credit->id)
                ->whereNotNull('installment_number')
                ->orderBy('installment_number')
                ->orderBy('id')
                ->get()
                ->groupBy('installment_number');

            foreach ($installmentPayments as $installmentNumber => $payments) {
                $totalAnalyzed++;

                // Calcular total pagado para esta cuota
                $totalPaid = $payments->sum('amount');

                // Verificar si la cuota está completa
                if ($totalPaid >= $installmentAmount) {
                    // Buscar pagos con status 'partial' en esta cuota
                    $partialPayments = $payments->where('status', 'partial');

                    if ($partialPayments->isNotEmpty()) {
                        $this->line(sprintf(
                            '  Crédito #%d, Cuota #%d: %d pago(s) parcial(es) → completed (Total: %.2f/%.2f)',
                            $credit->id,
                            $installmentNumber,
                            $partialPayments->count(),
                            $totalPaid,
                            $installmentAmount
                        ));

                        if (!$dryRun) {
                            // Actualizar status a 'completed'
                            Payment::whereIn('id', $partialPayments->pluck('id'))
                                ->update(['status' => 'completed']);

                            $totalUpdated += $partialPayments->count();
                        } else {
                            $totalUpdated += $partialPayments->count();
                        }
                    }
                }
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("✅ Análisis completado:");
            $this->info("   📋 Cuotas analizadas: {$totalAnalyzed}");
            $this->info("   🔄 Pagos que se actualizarían: {$totalUpdated}");
            $this->newLine();
            $this->warn('⚠️  Para aplicar los cambios, ejecuta el comando sin --dry-run');
        } else {
            $this->info("✅ Actualización completada:");
            $this->info("   📋 Cuotas analizadas: {$totalAnalyzed}");
            $this->info("   ✔️  Pagos actualizados: {$totalUpdated}");
        }

        return Command::SUCCESS;
    }
}
