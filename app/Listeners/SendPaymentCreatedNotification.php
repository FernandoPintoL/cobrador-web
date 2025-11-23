<?php

namespace App\Listeners;

use App\Events\PaymentCreated;
use App\Services\NotificationService;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\Log;

class SendPaymentCreatedNotification
{

    protected WebSocketNotificationService $webSocketService;
    protected NotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(
        WebSocketNotificationService $webSocketService,
        NotificationService $notificationService
    ) {
        $this->webSocketService = $webSocketService;
        $this->notificationService = $notificationService;
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
            // 1. Guardar notificación en la base de datos
            Log::info('💾 Guardando notificación de pago en DB...');

            $this->notificationService->createPaymentReceivedNotification(
                $event->payment,
                $event->cobrador,
                $event->manager,
                $event->client
            );

            Log::info('✅ Notificación de pago guardada en DB exitosamente');
        } catch (\Exception $e) {
            Log::error('❌ Failed to save payment notification to database', [
                'payment_id' => $event->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        try {
            // 2. Enviar notificación en tiempo real por WebSocket
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
