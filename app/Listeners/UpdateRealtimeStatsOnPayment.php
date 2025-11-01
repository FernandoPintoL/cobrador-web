<?php

namespace App\Listeners;

use App\Events\PaymentCreated;
use App\Services\RealtimeStatsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * 📊 Actualizar estadísticas en tiempo real cuando se crea un pago
 *
 * Se ejecuta DESPUÉS de SendPaymentCreatedNotification,
 * sin interferir con el flujo de notificaciones existente.
 */
class UpdateRealtimeStatsOnPayment implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * El nombre de la cola en la que se coloca este job
     */
    public string $queue = 'default';

    /**
     * Número de intentos antes de abandonar
     */
    public int $tries = 3;

    /**
     * Esperar X segundos antes de reintentar
     */
    public int $backoff = 5;

    public function handle(PaymentCreated $event): void
    {
        try {
            $payment = $event->payment;

            // Actualizar estadísticas del cobrador que recibió el pago
            if ($payment->cobrador_id) {
                RealtimeStatsService::updateCobradorStats($payment->cobrador_id);
            }

            // Actualizar estadísticas globales
            RealtimeStatsService::updateGlobalStats();

            Log::info('Real-time stats updated for payment', [
                'payment_id' => $payment->id,
                'cobrador_id' => $payment->cobrador_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating realtime stats on payment', [
                'error' => $e->getMessage(),
                'payment_id' => $event->payment->id,
            ]);

            // Re-lanzar para que intente de nuevo
            throw $e;
        }
    }
}
