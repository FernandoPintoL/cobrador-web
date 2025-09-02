<?php

namespace App\Listeners;

use App\Events\CreditWaitingListUpdate;
use App\Events\PaymentReceived;
use App\Events\CreditRequiresAttention;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSocketNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    private $websocketUrl;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        $this->websocketUrl = env('WEBSOCKET_URL', 'http://localhost:3001');
    }

    /**
     * Handle credit waiting list updates.
     */
    public function handleCreditWaitingListUpdate(CreditWaitingListUpdate $event)
    {
        $payload = [
            'action' => $event->action,
            'credit' => $event->broadcastWith()['credit'],
            'user' => $event->broadcastWith()['user'],
            'manager' => null,
            'cobrador' => null,
        ];

        // Determinar quién es el manager y el cobrador según el contexto
        if ($event->credit->createdBy->hasRole('cobrador')) {
            $payload['cobrador'] = [
                'id' => $event->credit->createdBy->id,
                'name' => $event->credit->createdBy->name,
                'email' => $event->credit->createdBy->email,
            ];

            // Obtener el manager del cobrador
            if ($event->credit->createdBy->assigned_manager_id) {
                $manager = \App\Models\User::find($event->credit->createdBy->assigned_manager_id);
                if ($manager) {
                    $payload['manager'] = [
                        'id' => $manager->id,
                        'name' => $manager->name,
                        'email' => $manager->email,
                    ];
                }
            }
        }

        $this->sendToWebSocket('/credit-notification', $payload);

        Log::info('Credit waiting list update sent to WebSocket', [
            'action' => $event->action,
            'credit_id' => $event->credit->id,
            'user_id' => $event->user->id,
        ]);
    }

    /**
     * Handle payment received events.
     */
    public function handlePaymentReceived(PaymentReceived $event)
    {
        $payload = $event->broadcastWith();

        $this->sendToWebSocket('/payment-notification', $payload);

        Log::info('Payment received notification sent to WebSocket', [
            'payment_id' => $event->payment->id,
            'amount' => $event->payment->amount,
            'cobrador_id' => $event->cobrador?->id,
            'manager_id' => $event->manager?->id,
        ]);
    }

    /**
     * Handle credit requires attention events.
     */
    public function handleCreditRequiresAttention(CreditRequiresAttention $event)
    {
        $payload = [
            'action' => 'requires_attention',
            'credit' => $event->broadcastWith()['credit'],
            'cobrador' => $event->broadcastWith()['cobrador'],
            'manager' => $event->broadcastWith()['manager'],
            'reason' => $event->reason,
        ];

        $this->sendToWebSocket('/credit-notification', $payload);

        Log::info('Credit attention notification sent to WebSocket', [
            'credit_id' => $event->credit->id,
            'reason' => $event->reason,
            'cobrador_id' => $event->cobrador?->id,
        ]);
    }

    /**
     * Send notification to WebSocket server.
     */
    private function sendToWebSocket(string $endpoint, array $payload)
    {
        try {
            $secret = config('services.websocket.ws_secret') ?? env('WS_SECRET');
            $url = rtrim($this->websocketUrl, '/') . $endpoint;

            $request = Http::timeout(10);
            if ($secret) {
                $request = $request->withHeaders([
                    'X-WS-SECRET' => $secret,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);
            }

            $response = $request->post($url, $payload);

            if (!$response->successful()) {
                Log::error('WebSocket notification failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('WebSocket notification exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }
}
