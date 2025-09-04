<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Events\TestNotification;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SendPaymentReceivedNotification
{
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        try {
            $cobrador = $event->cobrador;
            $payment = $event->payment;
            $manager = $cobrador->assignedManager;

            // Keep existing Laravel broadcasting (Reverb) for compatibility
            Log::info('PaymentReceived event broadcast + Node WebSocket notification', [
                'payment_id' => $payment->id,
                'cobrador_id' => $cobrador->id,
                'manager_id' => $manager ? $manager->id : null,
                'amount' => $payment->amount,
            ]);

            // Node WebSocket forwarding is centralized in WebSocketNotificationListener to avoid duplicates.
            // $this->webSocketService->sendPaymentNotification($event->payment, $event->cobrador, $manager);

            // Notificar también al manager del cobrador (notificación en base de datos)
            $this->notifyManager($event);

        } catch (\Exception $e) {
            Log::error('Error sending payment received notification', [
                'payment_id' => $event->payment->id,
                'cobrador_id' => $event->cobrador->id,
                'error' => $e->getMessage(),
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
        if (! $cobrador->assignedManager) {
            Log::info('Cobrador has no assigned manager, skipping manager notification', [
                'cobrador_id' => $cobrador->id,
                'payment_id' => $payment->id,
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
                'status' => 'unread',
            ]);

            // Enviar notificación en tiempo real al manager vía broadcasting
            //            event(new TestNotification($managerNotification, $manager));

            Log::info('Manager notification sent for payment received', [
                'manager_id' => $manager->id,
                'cobrador_id' => $cobrador->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'client_name' => $payment->credit->client->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending manager notification for payment received', [
                'manager_id' => $manager->id,
                'cobrador_id' => $cobrador->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
