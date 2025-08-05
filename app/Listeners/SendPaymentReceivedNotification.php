<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Services\WebSocketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentReceivedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected $webSocketService;

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
    public function handle(PaymentReceived $event): void
    {
        try {
            // Enviar notificación al WebSocket Node.js
            $sent = $this->webSocketService->sendPaymentReceived(
                $event->payment,
                $event->cobrador
            );

            if ($sent) {
                Log::info('Payment received notification sent via WebSocket', [
                    'payment_id' => $event->payment->id,
                    'cobrador_id' => $event->cobrador->id,
                    'amount' => $event->payment->amount
                ]);
            } else {
                Log::warning('Failed to send payment received notification via WebSocket', [
                    'payment_id' => $event->payment->id,
                    'cobrador_id' => $event->cobrador->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending payment received notification', [
                'payment_id' => $event->payment->id,
                'cobrador_id' => $event->cobrador->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
