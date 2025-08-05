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
    public function handle(CreditRequiresAttention $event): void
    {
        try {
            // Enviar notificación al WebSocket Node.js
            $sent = $this->webSocketService->sendCreditAttention(
                $event->credit,
                $event->cobrador
            );

            if ($sent) {
                Log::info('Credit attention notification sent via WebSocket', [
                    'credit_id' => $event->credit->id,
                    'cobrador_id' => $event->cobrador->id
                ]);
            } else {
                Log::warning('Failed to send credit attention notification via WebSocket', [
                    'credit_id' => $event->credit->id,
                    'cobrador_id' => $event->cobrador->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending credit attention notification', [
                'credit_id' => $event->credit->id,
                'cobrador_id' => $event->cobrador->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
