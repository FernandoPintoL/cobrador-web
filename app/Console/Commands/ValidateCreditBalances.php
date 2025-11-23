<?php

namespace App\Console\Commands;

use App\Models\Credit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateCreditBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:validate-balances
                            {--fix : Corregir automáticamente las inconsistencias encontradas}
                            {--credit= : Validar solo un crédito específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validar coherencia entre créditos y pagos, detectar inconsistencias en balances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fix = $this->option('fix');
        $creditId = $this->option('credit');

        $this->info('🔍 Validando coherencia de créditos y pagos...');
        $this->newLine();

        // Query base
        $query = Credit::query()
            ->whereIn('status', ['active', 'completed', 'defaulted'])
            ->with('payments');

        // Filtrar por crédito específico si se proporciona
        if ($creditId) {
            $query->where('id', $creditId);
        }

        $credits = $query->get();

        $this->info("📊 Analizando {$credits->count()} créditos...");
        $this->newLine();

        $inconsistencies = [];
        $bar = $this->output->createProgressBar($credits->count());

        foreach ($credits as $credit) {
            $bar->advance();

            $issues = $this->validateCredit($credit);

            if (!empty($issues)) {
                $inconsistencies[] = [
                    'credit' => $credit,
                    'issues' => $issues
                ];
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Mostrar resultados
        if (empty($inconsistencies)) {
            $this->info('✅ No se encontraron inconsistencias. Todos los créditos están coherentes.');
            return 0;
        }

        $this->warn("⚠️  Se encontraron " . count($inconsistencies) . " créditos con inconsistencias:");
        $this->newLine();

        // Tabla de resumen
        $rows = [];
        foreach ($inconsistencies as $item) {
            $credit = $item['credit'];
            $issues = $item['issues'];

            $rows[] = [
                $credit->id,
                $credit->client->name ?? 'N/A',
                number_format($credit->amount, 2),
                number_format($credit->balance, 2),
                number_format($credit->total_paid, 2),
                implode(', ', $issues)
            ];
        }

        $this->table(
            ['ID', 'Cliente', 'Monto', 'Balance', 'Total Pagado', 'Problemas'],
            $rows
        );

        $this->newLine();

        // Ofrecer corrección
        if ($fix) {
            $this->warn('🔧 Corrigiendo inconsistencias...');
            $this->newLine();

            $fixed = 0;
            foreach ($inconsistencies as $item) {
                if ($this->fixCredit($item['credit'])) {
                    $fixed++;
                }
            }

            $this->info("✅ Se corrigieron {$fixed} de " . count($inconsistencies) . " créditos.");
        } else {
            $this->info('💡 Para corregir automáticamente, ejecuta:');
            $this->line('   php artisan credits:validate-balances --fix');
        }

        return 0;
    }

    /**
     * Validate a single credit for inconsistencies
     */
    private function validateCredit(Credit $credit): array
    {
        $issues = [];

        // Calcular suma real de pagos completados
        $realTotalPaid = $credit->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $realPaidCount = $credit->payments()
            ->where('status', 'completed')
            ->count();

        // Verificar inconsistencia 1: total_paid vs suma de pagos
        if (abs($credit->total_paid - $realTotalPaid) > 0.01) {
            $diff = $credit->total_paid - $realTotalPaid;
            $issues[] = "total_paid incorrecto (diff: " . number_format($diff, 2) . ")";
        }

        // Verificar inconsistencia 2: balance vs (total_amount - total_paid)
        $expectedBalance = $credit->total_amount - $realTotalPaid;
        if (abs($credit->balance - $expectedBalance) > 0.01) {
            $diff = $credit->balance - $expectedBalance;
            $issues[] = "balance incorrecto (diff: " . number_format($diff, 2) . ")";
        }

        // Verificar inconsistencia 3: paid_installments vs count de pagos
        if ($credit->paid_installments != $realPaidCount) {
            $diff = $credit->paid_installments - $realPaidCount;
            $issues[] = "paid_installments incorrecto (diff: {$diff})";
        }

        // Verificar inconsistencia 4: estado completed/active
        if ($credit->balance <= 0 && $credit->status === 'active') {
            $issues[] = "debería estar 'completed'";
        }

        if ($credit->balance > 0 && $credit->status === 'completed') {
            $issues[] = "no debería estar 'completed'";
        }

        return $issues;
    }

    /**
     * Fix a credit's balance and status
     */
    private function fixCredit(Credit $credit): bool
    {
        try {
            DB::transaction(function () use ($credit) {
                // Recalcular total_paid
                $credit->total_paid = $credit->payments()
                    ->where('status', 'completed')
                    ->sum('amount');

                // Recalcular balance
                $credit->balance = $credit->total_amount - $credit->total_paid;

                // Recalcular paid_installments
                $credit->paid_installments = $credit->payments()
                    ->where('status', 'completed')
                    ->count();

                // Actualizar estado
                if ($credit->balance <= 0 && $credit->status !== 'completed') {
                    $credit->status = 'completed';
                } elseif ($credit->balance > 0 && $credit->status === 'completed') {
                    $credit->status = 'active';
                }

                $credit->save();
            });

            $this->line("  ✓ Crédito #{$credit->id} corregido");
            return true;

        } catch (\Exception $e) {
            $this->error("  ✗ Error corrigiendo crédito #{$credit->id}: {$e->getMessage()}");
            return false;
        }
    }
}
