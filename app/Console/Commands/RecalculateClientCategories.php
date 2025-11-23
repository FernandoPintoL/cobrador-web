<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecalculateClientCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:recalculate-categories {--dry-run : Mostrar cambios sin aplicarlos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula las categorías de todos los clientes basándose en sus cuotas atrasadas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 Modo DRY RUN - No se aplicarán cambios');
        }

        $this->info('🔄 Recalculando categorías de clientes...');

        // Obtener todos los usuarios que son clientes (tienen créditos)
        $clients = User::has('credits')->get();

        $this->info("📊 Se encontraron {$clients->count()} clientes para revisar");

        $updated = 0;
        $errors = 0;
        $unchanged = 0;

        foreach ($clients as $client) {
            try {
                $oldCategory = $client->client_category;
                $overdueCount = $client->getTotalOverdueInstallments();

                if (!$dryRun) {
                    // Recalcular categoría
                    $newCategory = $client->recalculateCategoryFromOverdues();
                } else {
                    // En dry-run, calcular manualmente sin guardar
                    $matching = \App\Models\ClientCategory::findForOverdueCount($overdueCount);
                    $newCategory = $matching ? $matching->code : $oldCategory;
                }

                if ($oldCategory !== $newCategory) {
                    $this->warn("⚠️  Cliente #{$client->id} ({$client->name}):");
                    $this->line("   - Categoría: {$oldCategory} → {$newCategory}");
                    $this->line("   - Cuotas atrasadas: {$overdueCount}");

                    if ($dryRun) {
                        $this->info("   [DRY RUN] Se actualizaría la categoría");
                    } else {
                        $this->info("   ✅ Categoría actualizada");
                    }
                    $updated++;
                } else {
                    $unchanged++;
                    $this->line("✅ Cliente #{$client->id} ({$client->name}) - Categoría correcta: {$oldCategory} (atrasos: {$overdueCount})");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("❌ Error en cliente #{$client->id}: {$e->getMessage()}");
                Log::error("Error recalculando categoría para cliente {$client->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine();
        $this->info('📊 Resumen:');
        $this->line("   - Sin cambios: {$unchanged}");
        $this->line("   - " . ($dryRun ? 'Para actualizar' : 'Actualizados') . ": {$updated}");
        if ($errors > 0) {
            $this->error("   - Errores: {$errors}");
        }

        if ($dryRun && $updated > 0) {
            $this->newLine();
            $this->warn('⚠️  Ejecuta sin --dry-run para aplicar los cambios');
        }

        if (!$dryRun && $updated > 0) {
            $this->newLine();
            $this->info('✅ Categorías recalculadas exitosamente');
        }

        return Command::SUCCESS;
    }
}
