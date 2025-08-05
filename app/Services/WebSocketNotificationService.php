<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WebSocketNotificationService
{
    private $host;
    private $port;
    private $secure;
    private $endpoint;

    public function __construct()
    {
        $this->host = config('broadcasting.connections.websocket.host', '192.168.5.44');
        $this->port = config('broadcasting.connections.websocket.port', 3001);
        $this->secure = config('broadcasting.connections.websocket.secure', false);
        $this->endpoint = config('broadcasting.connections.websocket.endpoint', '/notify');
    }

    /**
     * Get WebSocket server URL
     */
    private function getServerUrl()
    {
        $protocol = $this->secure ? 'https' : 'http';
        return "{$protocol}://{$this->host}:{$this->port}";
    }

    /**
     * Send notification to WebSocket server
     */
    public function sendNotification(array $data)
    {
        try {
            $url = $this->getServerUrl() . $this->endpoint;
            
            $response = Http::timeout(10)->post($url, [
                'event' => $data['event'] ?? 'notification',
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);

            if ($response->successful()) {
                Log::info('WebSocket notification sent successfully', [
                    'url' => $url,
                    'data' => $data
                ]);
                return true;
            } else {
                Log::error('Failed to send WebSocket notification', [
                    'url' => $url,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (Exception $e) {
            Log::error('WebSocket notification error', [
                'message' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Send credit attention notification
     */
    public function sendCreditAttention($credit, $cobrador)
    {
        return $this->sendNotification([
            'event' => 'credit_notification',
            'type' => 'credit_attention',
            'user_id' => $cobrador->id,
            'credit_id' => $credit->id,
            'client_id' => $credit->client_id,
            'client_name' => $credit->client->name,
            'amount' => $credit->amount,
            'total_amount' => $credit->total_amount,
            'interest_rate' => $credit->interest_rate,
            'installment_amount' => $credit->installment_amount,
            'payment_frequency' => $credit->payment_frequency,
            'end_date' => $credit->end_date->format('Y-m-d'),
            'days_overdue' => now()->diffInDays($credit->end_date, false),
        ]);
    }

    /**
     * Send payment received notification
     */
    public function sendPaymentReceived($payment, $cobrador)
    {
        return $this->sendNotification([
            'event' => 'payment_update',
            'type' => 'payment_received',
            'user_id' => $cobrador->id,
            'payment_id' => $payment->id,
            'credit_id' => $payment->credit_id,
            'client_id' => $payment->credit->client_id,
            'client_name' => $payment->credit->client->name,
            'amount' => $payment->amount,
            'payment_date' => $payment->payment_date->format('Y-m-d'),
            'payment_method' => $payment->payment_method,
            'remaining_balance' => $payment->credit->total_amount - $payment->credit->payments()->sum('amount'),
            'installments_paid' => $payment->credit->payments()->count(),
        ]);
    }

    /**
     * Send route notification
     */
    public function sendRouteNotification($route, $cobrador)
    {
        return $this->sendNotification([
            'event' => 'route_notification',
            'type' => 'route_update',
            'user_id' => $cobrador->id,
            'route_id' => $route->id,
            'route_name' => $route->name,
            'clients_count' => $route->clients()->count(),
            'status' => $route->status,
        ]);
    }

    /**
     * Send location update
     */
    public function sendLocationUpdate($userId, $latitude, $longitude)
    {
        return $this->sendNotification([
            'event' => 'location_update',
            'type' => 'location',
            'user_id' => $userId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Send custom message
     */
    public function sendMessage($fromUserId, $toUserId, $message)
    {
        return $this->sendNotification([
            'event' => 'send_message',
            'type' => 'message',
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'message' => $message,
        ]);
    }

    /**
     * Test WebSocket connection
     */
    public function testConnection()
    {
        try {
            $url = $this->getServerUrl() . '/health';
            $response = Http::timeout(5)->get($url);
            
            return [
                'connected' => $response->successful(),
                'status' => $response->status(),
                'response' => $response->json(),
                'url' => $url
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'url' => $this->getServerUrl()
            ];
        }
    }
}
