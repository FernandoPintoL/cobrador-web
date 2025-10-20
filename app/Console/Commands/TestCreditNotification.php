<?php

namespace App\Console\Commands;

use App\Events\CreditCreated;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Console\Command;

class TestCreditNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:credit-notification {credit_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test credit creation notification to WebSocket';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $creditId = $this->argument('credit_id');

        if ($creditId) {
            $credit = Credit::with(['client', 'createdBy'])->find($creditId);
            if (!$credit) {
                $this->error("❌ Crédito con ID {$creditId} no encontrado");
                return 1;
            }
        } else {
            // Obtener el último crédito con status pending_approval
            $credit = Credit::with(['client', 'createdBy'])
                ->where('status', 'pending_approval')
                ->latest()
                ->first();

            if (!$credit) {
                $this->error("❌ No hay créditos con status 'pending_approval'");
                return 1;
            }
        }

        $this->info("🔍 Crédito encontrado:");
        $this->info("   ID: {$credit->id}");
        $this->info("   Monto: {$credit->amount}");
        $this->info("   Status: {$credit->status}");
        $this->info("   Cliente: {$credit->client->name}");
        $this->info("   Creado por: {$credit->createdBy->name}");

        // Determinar cobrador y manager
        $cobrador = $credit->createdBy;
        $manager = null;

        if ($cobrador->hasRole('cobrador')) {
            $manager = $cobrador->assignedManager;
        }

        if (!$manager) {
            $this->error("❌ No se pudo determinar el manager para este crédito");
            $this->info("   Cobrador ID: {$cobrador->id}");
            $this->info("   assigned_manager_id: " . ($cobrador->assigned_manager_id ?? 'NULL'));
            return 1;
        }

        $this->info("\n👥 Notificación será enviada a:");
        $this->info("   Cobrador: {$cobrador->name} (ID: {$cobrador->id})");
        $this->info("   Manager: {$manager->name} (ID: {$manager->id})");

        $this->info("\n🚀 Disparando evento CreditCreated...");

        try {
            event(new CreditCreated($credit, $manager, $cobrador));
            $this->info("✅ Evento disparado exitosamente");
            $this->info("\n📋 Verifica:");
            $this->info("   1. Los logs de Laravel");
            $this->info("   2. La consola del WebSocket server");
            $this->info("   3. La app de Flutter del manager");
        } catch (\Exception $e) {
            $this->error("❌ Error al disparar evento: " . $e->getMessage());
            $this->error("   Trace: " . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
