<?php

namespace App\Listeners;

use App\Events\CreditRequiresAttention;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCreditAttentionNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * No dependencies needed; broadcasting handled by Laravel Reverb
     */
    public function __construct()
    {
        // no-op
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
        Log::info('CreditRequiresAttention event will be broadcast via Reverb', [
            'credit_id' => $event->credit->id,
            'cobrador_id' => $event->cobrador->id ?? null,
        ]);
    }
}
