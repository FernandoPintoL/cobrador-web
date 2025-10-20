<?php

namespace App\Listeners;

use App\Events\PaymentCreated;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\Log;

class SendPaymentCreatedNotification
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
    public function handle(PaymentCreated $event): void
    {
        Log::info('🎧 Listener SendPaymentCreatedNotification ejecutándose', [
            'payment_id' => $event->payment->id,
            'cobrador_id' => $event->cobrador->id,
            'manager_id' => $event->manager?->id,
        ]);

        try {
            Log::info('📤 Enviando notificación WebSocket de pago...');

            $this->webSocketService->notifyPaymentReceived(
                $event->payment,
                $event->cobrador,
                $event->manager,
                $event->client
            );

            Log::info('✅ Notificación WebSocket de pago enviada exitosamente');
        } catch (\Exception $e) {
            Log::error('❌ Failed to send payment created WebSocket notification', [
                'payment_id' => $event->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
