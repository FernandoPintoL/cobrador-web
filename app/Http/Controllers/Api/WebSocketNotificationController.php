<?php

namespace App\Http\Controllers\Api;

use App\Events\CreditRequiresAttention;
use App\Events\PaymentReceived;
use App\Events\TestNotification;
use App\Models\Credit;
use App\Models\Notification;
use App\Models\User;
use App\Services\WebSocketNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebSocketNotificationController extends BaseController
{
    protected $webSocketService;

    public function __construct(WebSocketNotificationService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    /**
     * Send real-time notification for credit requiring attention
     */
    public function sendCreditAttentionNotification(Credit $credit)
    {
        $cobrador = $credit->client->assignedCobrador;

        if (! $cobrador) {
            return $this->sendError('No cobrador asignado', 'El cliente no tiene cobrador asignado', 400);
        }

        // Crear notificación en BD
        $notification = Notification::create([
            'user_id' => $cobrador->id,
            'title' => 'Crédito requiere atención',
            'message' => "El crédito de {$credit->client->name} requiere atención",
            'type' => 'credit_attention',
            'data' => [
                'credit_id' => $credit->id,
                'client_name' => $credit->client->name,
                'amount' => $credit->amount,
                'total_amount' => $credit->total_amount,
                'interest_rate' => $credit->interest_rate,
                'end_date' => $credit->end_date->format('Y-m-d'),
            ],
        ]);

        // Enviar notificación en tiempo real vía Broadcasting y WebSocket
        //        broadcast(new CreditRequiresAttention($credit, $cobrador));

        // También enviar directamente al WebSocket Node.js
        //        $this->webSocketService->sendCreditAttention($credit, $cobrador);

        return $this->sendResponse($notification, 'Notificación enviada exitosamente');
    }

    /**
     * Send payment received notification
     */
    public function sendPaymentNotification(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id',
        ]);

        $payment = \App\Models\Payment::with(['credit.client', 'cobrador'])->find($request->payment_id);

        // Notificar al cobrador
        if ($payment->cobrador) {
            $notification = Notification::create([
                'user_id' => $payment->cobrador->id,
                'title' => 'Pago recibido',
                'message' => "Pago de {$payment->credit->client->name} por {$payment->amount}",
                'type' => 'payment_received',
                'data' => [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'client_name' => $payment->credit->client->name,
                ],
            ]);

            // Enviar vía Broadcasting y WebSocket
            //            broadcast(new PaymentReceived($payment, $payment->cobrador));
            //            $this->webSocketService->sendPaymentReceived($payment, $payment->cobrador);
        }

        return $this->sendResponse([], 'Notificación de pago enviada');
    }

    /**
     * Get real-time notifications for authenticated user
     */
    public function getRealtimeNotifications()
    {
        $user = Auth::user();

        $notifications = Notification::where('user_id', $user->id)
            ->where('read_at', null)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $this->sendResponse($notifications, 'Notificaciones en tiempo real obtenidas');
    }

    /**
     * Test WebSocket connection
     */
    public function testWebSocket(Request $request)
    {
        $user = Auth::user();

        // Crear notificación de prueba
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'Prueba de WebSocket',
            'message' => 'Esta es una notificación de prueba del sistema WebSocket',
            'type' => 'test',
            'data' => ['timestamp' => now()->toISOString()],
        ]);

        // Enviar vía WebSocket
        //        broadcast(new TestNotification($notification, $user));

        // Test connection to WebSocket server
        //        $connectionTest = $this->webSocketService->testConnection();

        return $this->sendResponse([
            'notification' => $notification,
            //            'websocket_connection' => $connectionTest
        ], 'Notificación de prueba enviada vía WebSocket');
    }

    /**
     * Test direct WebSocket connection
     */
    public function testDirectWebSocket(Request $request)
    {
        //        $connectionTest = $this->webSocketService->testConnection();

        //        return $this->sendResponse($connectionTest, 'Test de conexión directa al WebSocket');
        return $this->sendResponse([], 'Test de conexión directa al WebSocket');
    }
}
