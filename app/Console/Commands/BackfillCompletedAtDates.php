<?php

namespace App\Console\Commands;

use App\Models\Credit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCompletedAtDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:backfill-completed-at {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill completed_at dates for completed credits using their last payment date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->info('Buscando créditos completados sin completed_at...');

        // Encontrar créditos completados sin completed_at
        $completedCredits = Credit::where('status', 'completed')
            ->whereNull('completed_at')
            ->with(['payments' => function($query) {
                $query->whereIn('status', ['completed', 'partial'])
                      ->orderBy('payment_date', 'desc');
            }])
            ->get();

        $total = $completedCredits->count();

        if ($total === 0) {
            $this->info('✅ No hay créditos que necesiten actualización');
            return Command::SUCCESS;
        }

        $this->info("📊 Encontrados {$total} créditos para actualizar");
        $this->newLine();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($completedCredits as $credit) {
            try {
                // Obtener el último pago
                $lastPayment = $credit->payments->first();

                if (!$lastPayment) {
                    $this->newLine();
                    $this->warn("⚠️  Crédito #{$credit->id} no tiene pagos registrados - Saltando");
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                // Usar la fecha del último pago como completed_at
                $completedAt = $lastPayment->payment_date;

                if (!$isDryRun) {
                    $credit->completed_at = $completedAt;
                    $credit->save();
                }

                $updated++;
                $progressBar->advance();

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("❌ Error en crédito #{$credit->id}: " . $e->getMessage());
                $errors++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resumen
        $this->info('=================================');
        $this->info('         RESUMEN');
        $this->info('=================================');
        $this->info("Total procesados:  {$total}");
        $this->info("✅ Actualizados:   {$updated}");
        $this->info("⚠️  Saltados:       {$skipped}");
        $this->info("❌ Errores:        {$errors}");
        $this->info('=================================');

        if ($isDryRun) {
            $this->newLine();
            $this->warn('⚠️  DRY RUN - No se realizaron cambios reales');
            $this->info('💡 Ejecuta sin --dry-run para aplicar los cambios');
        } else {
            $this->newLine();
            $this->info('✅ Backfill completado exitosamente');
        }

        // Mostrar algunos ejemplos
        if (!$isDryRun && $updated > 0) {
            $this->newLine();
            $this->info('📋 Ejemplos de créditos actualizados:');
            $this->newLine();

            $examples = Credit::where('status', 'completed')
                ->whereNotNull('completed_at')
                ->orderBy('completed_at', 'desc')
                ->limit(5)
                ->get(['id', 'client_id', 'end_date', 'completed_at']);

            $this->table(
                ['ID', 'Cliente', 'Fecha Fin (Planeada)', 'Fecha Completado (Real)', 'Timing'],
                $examples->map(function($credit) {
                    $timing = '👌 A tiempo';
                    if ($credit->completed_at < $credit->end_date) {
                        $timing = '✅ Anticipado';
                    } elseif ($credit->completed_at->format('Y-m-d') > $credit->end_date->format('Y-m-d')) {
                        $timing = '⚠️ Tardío';
                    }

                    return [
                        $credit->id,
                        $credit->client_id,
                        $credit->end_date->format('d/m/Y'),
                        $credit->completed_at->format('d/m/Y H:i'),
                        $timing
                    ];
                })
            );
        }

        return Command::SUCCESS;
    }
}
