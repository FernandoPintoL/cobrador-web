<?php

namespace App\Listeners;

use App\Events\CreditCreated;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\Log;

class SendCreditCreatedNotification
{

    protected WebSocketNotificationService $webSocketService;

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
    public function handle(CreditCreated $event): void
    {
        Log::info('🎧 Listener SendCreditCreatedNotification ejecutándose', [
            'credit_id' => $event->credit->id,
            'manager_id' => $event->manager->id,
            'cobrador_id' => $event->cobrador->id,
        ]);

        try {
            Log::info('📤 Enviando notificación WebSocket de crédito creado...');

            $this->webSocketService->notifyCreditCreated(
                $event->credit,
                $event->manager,
                $event->cobrador
            );

            Log::info('✅ Notificación WebSocket de crédito creado enviada exitosamente');
        } catch (\Exception $e) {
            Log::error('❌ Failed to send credit created WebSocket notification', [
                'credit_id' => $event->credit->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
