<?php

namespace App\Console\Commands;

use App\Events\CashBalanceAutoClosed;
use App\Models\CashBalance;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoCloseCashBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cash-balances:auto-close
                          {--dry-run : Run without making changes}
                          {--date= : Specific date to close (default: yesterday)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-close open cash balances from previous days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now()->subDay();

        $this->info("🔍 Buscando cajas abiertas antes de {$date->toDateString()}...");

        if ($dryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios en la base de datos');
        }

        try {
            // Buscar cajas abiertas de días anteriores
            $openBalances = CashBalance::with(['cobrador', 'cobrador.assignedManager'])
                ->where('status', 'open')
                ->where('date', '<', $date->toDateString())
                ->get();

            if ($openBalances->isEmpty()) {
                $this->info('✅ No se encontraron cajas abiertas de días anteriores');

                return Command::SUCCESS;
            }

            $this->info("📦 Se encontraron {$openBalances->count()} cajas abiertas");

            $closedCount = 0;
            $failedCount = 0;

            // Crear tabla para mostrar resultados
            $tableData = [];

            DB::beginTransaction();

            foreach ($openBalances as $balance) {
                try {
                    $cobrador = $balance->cobrador;
                    $manager = $cobrador->assignedManager;

                    $tableRow = [
                        'id' => $balance->id,
                        'cobrador' => $cobrador->name,
                        'fecha' => $balance->date->toDateString(),
                        'saldo_final' => number_format($balance->final_amount, 2),
                    ];

                    if (! $dryRun) {
                        // Auto-cerrar la caja
                        $notes = "Cierre automático del sistema - Caja del {$balance->date->format('d/m/Y')}";
                        $balance->autoClose($notes);

                        // Disparar evento para notificaciones WebSocket
                        event(new CashBalanceAutoClosed($balance, $cobrador, $manager));

                        $tableRow['estado'] = '✅ Cerrada';
                        $closedCount++;

                        Log::info('Cash balance auto-closed', [
                            'cash_balance_id' => $balance->id,
                            'cobrador_id' => $cobrador->id,
                            'date' => $balance->date->toDateString(),
                            'final_amount' => $balance->final_amount,
                        ]);
                    } else {
                        $tableRow['estado'] = '🔍 Pendiente (dry-run)';
                    }

                    $tableData[] = $tableRow;
                } catch (\Exception $e) {
                    $failedCount++;
                    $tableData[] = [
                        'id' => $balance->id,
                        'cobrador' => $balance->cobrador->name ?? 'N/A',
                        'fecha' => $balance->date->toDateString(),
                        'saldo_final' => number_format($balance->final_amount, 2),
                        'estado' => "❌ Error: {$e->getMessage()}",
                    ];

                    Log::error('Failed to auto-close cash balance', [
                        'cash_balance_id' => $balance->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (! $dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            // Mostrar tabla de resultados
            $this->table(
                ['ID', 'Cobrador', 'Fecha', 'Saldo Final', 'Estado'],
                $tableData
            );

            // Resumen
            $this->newLine();
            $this->info('📊 Resumen:');
            $this->info("   ✅ Cajas cerradas: {$closedCount}");

            if ($failedCount > 0) {
                $this->error("   ❌ Cajas con error: {$failedCount}");
            }

            if ($dryRun) {
                $this->warn('   ⚠️  Cambios no aplicados (dry-run mode)');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error('❌ Error al ejecutar el auto-cierre de cajas: '.$e->getMessage());
            Log::error('Auto-close cash balances command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
