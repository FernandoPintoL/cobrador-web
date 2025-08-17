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
     *
     * IMPORTANT: WebSocket delivery for CreditRequiresAttention is centralized in
     * WebSocketNotificationListener to avoid duplicated notifications.
     * This listener now only logs for traceability.
     */
    public function handle(CreditRequiresAttention $event): void
    {
        Log::info('CreditRequiresAttention received; WebSocket sending handled by WebSocketNotificationListener to avoid duplicates', [
            'credit_id' => $event->credit->id,
            'cobrador_id' => $event->cobrador->id ?? null,
        ]);
    }
}
