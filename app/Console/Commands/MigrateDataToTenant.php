<?php

namespace App\Console\Commands;

use App\Models\CashBalance;
use App\Models\Credit;
use App\Models\InterestRate;
use App\Models\Payment;
use App\Models\Route;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateDataToTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate-data {tenant_id=1 : ID del tenant al que migrar los datos} {--force : Forzar migración sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrar todos los datos existentes (sin tenant_id) a un tenant específico';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        $this->info("🚀 Iniciando migración de datos al Tenant ID: {$tenantId}");
        $this->newLine();

        // Confirmar antes de proceder
        if (!$this->option('force') && !$this->confirm('¿Estás seguro de querer migrar todos los datos sin tenant_id a este tenant?')) {
            $this->warn('Operación cancelada.');
            return Command::FAILURE;
        }

        DB::beginTransaction();

        try {
            // Migrar users
            $this->migrateModel(User::class, $tenantId);

            // Migrar credits
            $this->migrateModel(Credit::class, $tenantId);

            // Migrar payments
            $this->migrateModel(Payment::class, $tenantId);

            // Migrar cash_balances
            $this->migrateModel(CashBalance::class, $tenantId);

            // Migrar routes
            $this->migrateModel(Route::class, $tenantId);

            // Migrar interest_rates
            $this->migrateModel(InterestRate::class, $tenantId);

            DB::commit();

            $this->newLine();
            $this->info('✅ Migración completada exitosamente!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();

            $this->error('❌ Error durante la migración: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Migrar un modelo específico
     */
    private function migrateModel(string $modelClass, int $tenantId): void
    {
        $modelName = class_basename($modelClass);
        $table = (new $modelClass)->getTable();

        // Contar registros sin tenant_id
        $count = DB::table($table)->whereNull('tenant_id')->count();

        if ($count === 0) {
            $this->line("⏭️  {$modelName}: No hay registros para migrar");
            return;
        }

        // Actualizar registros
        $updated = DB::table($table)
            ->whereNull('tenant_id')
            ->update(['tenant_id' => $tenantId]);

        $this->info("✅ {$modelName}: {$updated} registros migrados");
    }
}
