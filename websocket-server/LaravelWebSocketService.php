<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class WebSocketNotificationService
{
    private $client;
    private $baseUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->baseUrl = config('websocket.url', 'http://localhost:3001');
    }

    /**
     * Enviar notificación a un usuario específico
     */
    public function notifyUser($userId, $notification, $event = 'notification')
    {
        try {
            $response = $this->client->post($this->baseUrl . '/notify', [
                'json' => [
                    'userId' => $userId,
                    'notification' => $notification,
                    'event' => $event
                ],
                'timeout' => 5
            ]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            Log::error('Error enviando notificación WebSocket: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación a todos los usuarios de un tipo
     */
    public function notifyUserType($userType, $notification, $event = 'notification')
    {
        try {
            $response = $this->client->post($this->baseUrl . '/notify', [
                'json' => [
                    'userType' => $userType,
                    'notification' => $notification,
                    'event' => $event
                ],
                'timeout' => 5
            ]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            Log::error('Error enviando notificación WebSocket: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Notificar nuevo crédito a cobrador
     */
    public function notifyNewCredit($cobradorId, $credit)
    {
        $notification = [
            'title' => 'Nuevo Crédito Asignado',
            'message' => "Se te ha asignado un crédito de {$credit->amount} Bs.",
            'type' => 'credit',
            'credit_id' => $credit->id,
            'amount' => $credit->amount,
            'client_name' => $credit->client->name ?? 'Cliente',
            'due_date' => $credit->due_date
        ];

        return $this->notifyUser($cobradorId, $notification, 'new_credit_notification');
    }

    /**
     * Notificar pago actualizado
     */
    public function notifyPaymentUpdate($cobradorId, $clientId, $payment)
    {
        $notification = [
            'title' => 'Pago Actualizado',
            'message' => "Pago de {$payment->amount} Bs. ha sido procesado",
            'type' => 'payment',
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'status' => $payment->status
        ];

        // Notificar al cobrador
        if ($cobradorId) {
            $this->notifyUser($cobradorId, $notification, 'payment_updated');
        }

        // Notificar al cliente
        if ($clientId) {
            $this->notifyUser($clientId, $notification, 'payment_updated');
        }

        // Notificar a admins
        $this->notifyUserType('admin', $notification, 'payment_updated');

        return true;
    }

    /**
     * Notificar crédito que requiere atención
     */
    public function notifyCreditRequiresAttention($credit)
    {
        $notification = [
            'title' => 'Crédito Requiere Atención',
            'message' => "El crédito #{$credit->id} requiere atención urgente",
            'type' => 'urgent',
            'credit_id' => $credit->id,
            'amount' => $credit->amount,
            'days_overdue' => $credit->days_overdue ?? 0,
            'client_name' => $credit->client->name ?? 'Cliente'
        ];

        // Notificar al cobrador asignado
        if ($credit->cobrador_id) {
            $this->notifyUser($credit->cobrador_id, $notification, 'credit_attention_required');
        }

        // Notificar a todos los admins
        $this->notifyUserType('admin', $notification, 'credit_attention_required');

        return true;
    }

    /**
     * Notificar actualización de ruta
     */
    public function notifyRouteUpdate($cobradorId, $route)
    {
        $notification = [
            'title' => 'Ruta Actualizada',
            'message' => "Tu ruta del {$route->date} ha sido actualizada",
            'type' => 'route',
            'route_id' => $route->id,
            'date' => $route->date,
            'clients_count' => $route->clients->count() ?? 0
        ];

        return $this->notifyUser($cobradorId, $notification, 'route_updated');
    }

    /**
     * Verificar el estado del servidor WebSocket
     */
    public function checkServerStatus()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/health', [
                'timeout' => 3
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['status'] === 'OK';
        } catch (GuzzleException $e) {
            Log::warning('Servidor WebSocket no disponible: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener usuarios activos
     */
    public function getActiveUsers()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/active-users', [
                'timeout' => 5
            ]);

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            Log::error('Error obteniendo usuarios activos: ' . $e->getMessage());
            return null;
        }
    }
}
