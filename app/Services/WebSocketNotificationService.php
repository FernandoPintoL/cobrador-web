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
                Log::warning('WebSocket notification failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (Exception $e) {
            Log::error('WebSocket notification error', [
                'url' => $this->getServerUrl() . $this->endpoint,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Send credit lifecycle notification
     */
    public function sendCreditNotification($credit, $action, $user, $manager = null, $cobrador = null)
    {
        try {
            $url = $this->getServerUrl() . '/credit-notification';
            
            $payload = [
                'action' => $action,
                'credit' => [
                    'id' => $credit->id,
                    'amount' => $credit->amount,
                    'total_amount' => $credit->total_amount,
                    'status' => $credit->status,
                    'client_name' => $credit->client->name ?? 'Cliente desconocido'
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'type' => $user->getRoleNames()->first()
                ]
            ];

            // Agregar información del manager si está disponible
            if ($manager) {
                $payload['manager'] = [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'type' => 'manager'
                ];
            }

            // Agregar información del cobrador si está disponible
            if ($cobrador) {
                $payload['cobrador'] = [
                    'id' => $cobrador->id,
                    'name' => $cobrador->name,
                    'type' => 'cobrador'
                ];
            }

            $response = Http::timeout(10)->post($url, $payload);

            if ($response->successful()) {
                Log::info('Credit WebSocket notification sent successfully', [
                    'action' => $action,
                    'credit_id' => $credit->id,
                    'user_id' => $user->id
                ]);
                return true;
            } else {
                Log::warning('Credit WebSocket notification failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (Exception $e) {
            Log::error('Credit WebSocket notification error', [
                'action' => $action,
                'credit_id' => $credit->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send payment notification
     */
    public function sendPaymentNotification($payment, $cobrador, $manager = null)
    {
        try {
            $url = $this->getServerUrl() . '/payment-notification';
            
            $payload = [
                'payment' => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date,
                    'payment_method' => $payment->payment_method,
                    'credit_id' => $payment->credit_id
                ],
                'cobrador' => [
                    'id' => $cobrador->id,
                    'name' => $cobrador->name,
                    'type' => 'cobrador'
                ],
                'client' => [
                    'id' => $payment->credit->client->id,
                    'name' => $payment->credit->client->name,
                    'type' => 'client'
                ]
            ];

            // Agregar información del manager si está disponible
            if ($manager) {
                $payload['manager'] = [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'type' => 'manager'
                ];
            }

            $response = Http::timeout(10)->post($url, $payload);

            if ($response->successful()) {
                Log::info('Payment WebSocket notification sent successfully', [
                    'payment_id' => $payment->id,
                    'cobrador_id' => $cobrador->id,
                    'amount' => $payment->amount
                ]);
                return true;
            } else {
                Log::warning('Payment WebSocket notification failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (Exception $e) {
            Log::error('Payment WebSocket notification error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
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
            'payment_frequency' => $credit->frequency,
            'end_date' => $credit->end_date->format('Y-m-d'),
            'days_overdue' => now()->diffInDays($credit->end_date, false),
        ]);
    }

    /**
     * Send payment received notification (legacy method for compatibility)
     */
    public function sendPaymentReceived($payment, $cobrador)
    {
        // Usar el nuevo método mejorado
        $manager = $cobrador->assignedManager;
        return $this->sendPaymentNotification($payment, $cobrador, $manager);
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
