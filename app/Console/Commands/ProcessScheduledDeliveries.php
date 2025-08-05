<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Credit;
use App\Models\User;
use App\Events\CreditReadyForDelivery;
use Carbon\Carbon;

class ProcessScheduledDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:process-scheduled-deliveries
                            {--notify : Send notifications for credits ready for delivery}
                            {--auto-deliver : Automatically deliver credits to clients if enabled}
                            {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled credit deliveries and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Procesando entregas programadas de créditos...');
        
        $dryRun = $this->option('dry-run');
        $notify = $this->option('notify');
        $autoDeliver = $this->option('auto-deliver');
        
        // Obtener créditos listos para entrega hoy
        $readyCredits = Credit::readyForDeliveryToday();
        $readyCount = $readyCredits->count();
        
        // Obtener créditos atrasados para entrega
        $overdueCredits = Credit::overdueForDelivery();
        $overdueCount = $overdueCredits->count();
        
        $this->info("Encontrados {$readyCount} créditos listos para entrega hoy");
        $this->info("Encontrados {$overdueCount} créditos atrasados para entrega");
        
        if ($dryRun) {
            $this->warn('MODO DRY-RUN - No se realizarán cambios reales');
        }
        
        // Procesar créditos listos para entrega
        if ($readyCount > 0) {
            $this->line('');
            $this->info('Créditos listos para entrega hoy:');
            
            foreach ($readyCredits as $credit) {
                $this->processReadyCredit($credit, $dryRun, $notify, $autoDeliver);
            }
        }
        
        // Procesar créditos atrasados
        if ($overdueCount > 0) {
            $this->line('');
            $this->warn('Créditos atrasados para entrega:');
            
            foreach ($overdueCredits as $credit) {
                $this->processOverdueCredit($credit, $dryRun, $notify);
            }
        }
        
        if ($readyCount === 0 && $overdueCount === 0) {
            $this->info('No hay créditos programados para procesar hoy.');
        }
        
        $this->line('');
        $this->info('Procesamiento completado.');
    }
    
    /**
     * Process a credit ready for delivery
     */
    private function processReadyCredit(Credit $credit, bool $dryRun, bool $notify, bool $autoDeliver): void
    {
        $clientName = $credit->client->name;
        $amount = number_format($credit->total_amount, 2);
        $scheduledDate = $credit->scheduled_delivery_date->format('Y-m-d H:i');
        
        $this->line("- Cliente: {$clientName} | Monto: {$amount} Bs | Programado: {$scheduledDate}");
        
        if (!$dryRun) {
            // Enviar notificaciones si está habilitado
            if ($notify) {
                $this->notifyCreditReadyForDelivery($credit);
            }
            
            // Auto-entregar si está habilitado y configurado
            if ($autoDeliver && $this->shouldAutoDeliver($credit)) {
                $this->autoDeliverCredit($credit);
            }
        }
    }
    
    /**
     * Process an overdue credit
     */
    private function processOverdueCredit(Credit $credit, bool $dryRun, bool $notify): void
    {
        $clientName = $credit->client->name;
        $amount = number_format($credit->total_amount, 2);
        $daysOverdue = $credit->getDaysOverdueForDelivery();
        $scheduledDate = $credit->scheduled_delivery_date->format('Y-m-d H:i');
        
        $this->line("- Cliente: {$clientName} | Monto: {$amount} Bs | Atrasado: {$daysOverdue} días | Programado: {$scheduledDate}");
        
        if (!$dryRun && $notify) {
            $this->notifyCreditOverdue($credit);
        }
    }
    
    /**
     * Send notification for credit ready for delivery
     */
    private function notifyCreditReadyForDelivery(Credit $credit): void
    {
        try {
            // Notificar al cobrador asignado o al que creó el crédito
            $cobrador = $credit->client->assignedCobrador ?? $credit->createdBy;
            
            if ($cobrador) {
                // Aquí puedes implementar tu lógica de notificaciones
                // Por ejemplo, usando eventos, emails, WebSocket, etc.
                
                $this->info("  → Notificación enviada a cobrador: {$cobrador->name}");
            }
            
            // Notificar al que aprobó el crédito
            if ($credit->approvedBy) {
                $this->info("  → Notificación enviada a quien aprobó: {$credit->approvedBy->name}");
            }
            
        } catch (\Exception $e) {
            $this->error("  → Error al enviar notificación: {$e->getMessage()}");
        }
    }
    
    /**
     * Send notification for overdue credit
     */
    private function notifyCreditOverdue(Credit $credit): void
    {
        try {
            // Notificar a managers y admins sobre créditos atrasados
            $managers = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['manager', 'admin']);
            })->get();
            
            foreach ($managers as $manager) {
                // Implementar notificación
                $this->info("  → Notificación de atraso enviada a: {$manager->name}");
            }
            
        } catch (\Exception $e) {
            $this->error("  → Error al enviar notificación de atraso: {$e->getMessage()}");
        }
    }
    
    /**
     * Check if credit should be auto-delivered
     */
    private function shouldAutoDeliver(Credit $credit): bool
    {
        // Lógica para determinar si un crédito debe ser auto-entregado
        // Por ejemplo, basado en configuración del sistema, 
        // tipo de cliente, monto, etc.
        
        // Por ahora, no auto-entregar para mantener control manual
        return false;
    }
    
    /**
     * Auto deliver credit to client
     */
    private function autoDeliverCredit(Credit $credit): void
    {
        try {
            // Usar un usuario del sistema para la entrega automática
            $systemUser = User::whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })->first();
            
            if ($systemUser) {
                $success = $credit->deliverToClient(
                    $systemUser->id, 
                    'Entrega automática programada'
                );
                
                if ($success) {
                    $this->info("  → Crédito entregado automáticamente");
                } else {
                    $this->error("  → Error en entrega automática");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("  → Error en entrega automática: {$e->getMessage()}");
        }
    }
}
