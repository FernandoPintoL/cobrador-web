<?php

namespace App\Listeners;

use App\Events\CreditRequiresAttention;
use App\Services\WebSocketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCreditAttentionNotification implements ShouldQueue
{
    use InteractsWithQueue;

    private $webSocketService;

    /**
     * Create the event listener.
     */
    public function __construct(WebSocketNotificationService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    /**
     * Handle the event.
     */
    public function handle(CreditRequiresAttention $event): void
    {
        $credit = $event->credit;
        
        try {
            // Enviar notificación WebSocket
            $success = $this->webSocketService->notifyCreditRequiresAttention($credit);
            
            if ($success) {
                Log::info("Notificación WebSocket enviada para crédito {$credit->id}");
            } else {
                Log::warning("No se pudo enviar notificación WebSocket para crédito {$credit->id}");
            }
            
        } catch (\Exception $e) {
            Log::error("Error enviando notificación WebSocket: {$e->getMessage()}");
            
            // No fallar el job, solo registrar el error
            // El resto de la funcionalidad debe continuar
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(CreditRequiresAttention $event, \Throwable $exception): void
    {
        Log::error("Job fallido para notificación de crédito {$event->credit->id}: {$exception->getMessage()}");
    }
}
