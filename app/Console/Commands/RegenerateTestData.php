<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegenerateTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test-data:regenerate
                            {--keep-clients : Mantener los clientes de prueba existentes}
                            {--force : Ejecutar sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia y regenera todos los datos de prueba (créditos y pagos) sin afectar usuarios admin/manager/cobrador';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Regenerador de Datos de Prueba');
        $this->newLine();

        // Verificar si hay usuarios importantes
        $hasAdminUsers = $this->checkImportantUsers();

        if (!$hasAdminUsers) {
            $this->error('⚠️  No se encontraron usuarios admin, manager o cobrador.');
            $this->info('Ejecuta primero: php artisan db:seed');
            return 1;
        }

        // Mostrar resumen de lo que se va a hacer
        $this->showSummary();

        // Confirmar acción
        if (!$this->option('force')) {
            if (!$this->confirm('¿Deseas continuar con la limpieza y regeneración?', true)) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }

        $this->newLine();

        // Paso 1: Limpiar datos
        $this->cleanData();

        // Paso 2: Regenerar datos
        $this->regenerateData();

        $this->newLine();
        $this->info('✅ ¡Datos de prueba regenerados exitosamente!');

        return 0;
    }

    /**
     * Verificar si existen usuarios importantes
     */
    private function checkImportantUsers(): bool
    {
        $adminExists = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->exists();

        $managerExists = User::whereHas('roles', function ($query) {
            $query->where('name', 'manager');
        })->exists();

        $cobradorExists = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })->exists();

        return $adminExists || $managerExists || $cobradorExists;
    }

    /**
     * Mostrar resumen de la operación
     */
    private function showSummary()
    {
        $creditsCount = Credit::count();
        $paymentsCount = Payment::count();
        $clientsCount = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })->count();

        $this->table(
            ['Tipo', 'Cantidad Actual', 'Acción'],
            [
                ['Créditos', $creditsCount, '🗑️  Eliminar todos'],
                ['Pagos', $paymentsCount, '🗑️  Eliminar todos'],
                ['Clientes', $clientsCount, $this->option('keep-clients') ? '✓ Mantener' : '🗑️  Eliminar'],
                ['Admin/Manager/Cobrador', $this->countImportantUsers(), '✓ Mantener (protegidos)'],
            ]
        );

        $this->newLine();
        $this->warn('⚠️  Esta acción eliminará permanentemente los datos de prueba.');

        if (!$this->option('keep-clients')) {
            $this->warn('⚠️  Se eliminarán TODOS los clientes de prueba.');
            $this->info('💡 Usa --keep-clients para mantener los clientes existentes.');
        }
    }

    /**
     * Contar usuarios importantes
     */
    private function countImportantUsers(): int
    {
        return User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['admin', 'manager', 'cobrador']);
        })->count();
    }

    /**
     * Limpiar datos de prueba
     */
    private function cleanData()
    {
        $this->info('🧹 Limpiando datos de prueba...');

        DB::beginTransaction();

        try {
            // Paso 1: Eliminar pagos
            $paymentsCount = Payment::count();
            if ($paymentsCount > 0) {
                $this->info("  🗑️  Eliminando {$paymentsCount} pagos...");
                Payment::truncate();
                $this->info('  ✓ Pagos eliminados');
            }

            // Paso 2: Eliminar créditos
            $creditsCount = Credit::count();
            if ($creditsCount > 0) {
                $this->info("  🗑️  Eliminando {$creditsCount} créditos...");
                Credit::truncate();
                $this->info('  ✓ Créditos eliminados');
            }

            // Paso 3: Eliminar clientes (si no se especifica --keep-clients)
            if (!$this->option('keep-clients')) {
                $clients = User::whereHas('roles', function ($query) {
                    $query->where('name', 'client');
                })->get();

                if ($clients->count() > 0) {
                    $this->info("  🗑️  Eliminando {$clients->count()} clientes...");

                    foreach ($clients as $client) {
                        // Eliminar relaciones del usuario
                        DB::table('model_has_roles')->where('model_id', $client->id)->delete();
                        $client->delete();
                    }

                    $this->info('  ✓ Clientes eliminados');
                }
            } else {
                $this->info('  ✓ Clientes mantenidos (--keep-clients)');
            }

            DB::commit();
            $this->info('✓ Limpieza completada');
            $this->newLine();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error durante la limpieza: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Regenerar datos de prueba
     */
    private function regenerateData()
    {
        $this->info('📊 Regenerando datos de prueba...');
        $this->newLine();

        try {
            // Ejecutar el seeder completo
            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\ComprehensiveReportDataSeeder'
            ]);

            $this->newLine();
            $this->info('✓ Datos regenerados correctamente');

            // Mostrar estadísticas
            $this->showStatistics();

        } catch (\Exception $e) {
            $this->error('Error durante la regeneración: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mostrar estadísticas de los datos generados
     */
    private function showStatistics()
    {
        $this->newLine();
        $this->info('📈 Estadísticas de datos generados:');
        $this->newLine();

        $creditsByStatus = Credit::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->total];
            });

        $data = [];
        $statusLabels = [
            'pending_approval' => 'Pendiente aprobación',
            'waiting_delivery' => 'Esperando entrega',
            'active' => 'Activos',
            'completed' => 'Completados',
            'defaulted' => 'En mora',
            'rejected' => 'Rechazados',
            'cancelled' => 'Cancelados',
        ];

        foreach ($statusLabels as $status => $label) {
            $count = $creditsByStatus[$status] ?? 0;
            if ($count > 0) {
                $data[] = [$label, $count];
            }
        }

        $totalCredits = Credit::count();
        $totalPayments = Payment::count();
        $totalClients = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })->count();

        $this->table(['Estado del Crédito', 'Cantidad'], $data);

        $this->newLine();
        $this->table(
            ['Resumen', 'Total'],
            [
                ['Total Créditos', $totalCredits],
                ['Total Pagos', $totalPayments],
                ['Total Clientes', $totalClients],
            ]
        );
    }
}
