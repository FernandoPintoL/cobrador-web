<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Events\TestNotification;
use App\Models\Notification;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\Log;

class SendPaymentReceivedNotification
{

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
            $cobrador = $event->cobrador;
            $payment = $event->payment;
            $manager = $cobrador->assignedManager;

            // Enviar notificación especializada de pago usando el nuevo endpoint
            $sent = $this->webSocketService->sendPaymentNotification(
                $payment,
                $cobrador,
                $manager
            );

            if ($sent) {
                Log::info('Payment notification sent via WebSocket', [
                    'payment_id' => $payment->id,
                    'cobrador_id' => $cobrador->id,
                    'manager_id' => $manager ? $manager->id : null,
                    'amount' => $payment->amount
                ]);
            } else {
                Log::warning('Failed to send payment notification via WebSocket', [
                    'payment_id' => $payment->id,
                    'cobrador_id' => $cobrador->id
                ]);
            }

            // Notificar también al manager del cobrador (notificación en base de datos)
            $this->notifyManager($event);

        } catch (\Exception $e) {
            Log::error('Error sending payment received notification', [
                'payment_id' => $event->payment->id,
                'cobrador_id' => $event->cobrador->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify the manager about the payment received by their cobrador
     */
    private function notifyManager(PaymentReceived $event): void
    {
        $cobrador = $event->cobrador;
        $payment = $event->payment;
        
        // Verificar que el cobrador tenga un manager asignado
        if (!$cobrador->assignedManager) {
            Log::info('Cobrador has no assigned manager, skipping manager notification', [
                'cobrador_id' => $cobrador->id,
                'payment_id' => $payment->id
            ]);
            return;
        }

        $manager = $cobrador->assignedManager;
        
        try {
            // Crear notificación en base de datos para el manager
            $managerNotification = Notification::create([
                'user_id' => $manager->id,
                'payment_id' => $payment->id,
                'type' => 'cobrador_payment_received',
                'message' => "El cobrador {$cobrador->name} recibió un pago de {$payment->amount} Bs de {$payment->credit->client->name}",
                'status' => 'unread'
            ]);
            
            // Enviar notificación en tiempo real al manager vía WebSocket
            event(new TestNotification($managerNotification, $manager));
            
            Log::info('Manager notification sent for payment received', [
                'manager_id' => $manager->id,
                'cobrador_id' => $cobrador->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'client_name' => $payment->credit->client->name
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error sending manager notification for payment received', [
                'manager_id' => $manager->id,
                'cobrador_id' => $cobrador->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
