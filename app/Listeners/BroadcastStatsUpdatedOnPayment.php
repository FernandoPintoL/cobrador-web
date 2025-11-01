<?php

namespace App\Listeners;

use App\Events\PaymentCreated;
use App\Services\RealtimeStatsService;
use App\Services\WebSocketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * 📊 Enviar estadísticas actualizadas por WebSocket cuando se crea un pago
 *
 * Se ejecuta DESPUÉS de UpdateRealtimeStatsOnPayment,
 * enviando las estadísticas actualizadas a través del WebSocket.
 */
class BroadcastStatsUpdatedOnPayment implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries = 3;
    public int $backoff = 5;

    public function handle(PaymentCreated $event): void
    {
        try {
            $payment = $event->payment;
            $wsService = app(WebSocketNotificationService::class);

            // Obtener estadísticas globales actualizadas
            $globalStats = RealtimeStatsService::getGlobalStats();

            // Notificar estadísticas globales actualizadas
            $wsService->notifyAll('stats.global.updated', [
                'stats' => $globalStats,
                'event_type' => 'payment_created',
                'payment_id' => $payment->id,
            ]);

            // Si el pago está asociado a un cobrador, también notificar sus estadísticas
            if ($payment->cobrador_id) {
                $cobradorStats = RealtimeStatsService::getCobradorStats($payment->cobrador_id);

                $wsService->notifyUser(
                    (string) $payment->cobrador_id,
                    'stats.cobrador.updated',
                    [
                        'stats' => $cobradorStats,
                        'event_type' => 'payment_created',
                        'payment_id' => $payment->id,
                    ]
                );

                // Si el cobrador tiene un manager, notificar sus estadísticas también
                $cobrador = $event->payment->cobrador;
                if ($cobrador && $cobrador->assigned_manager_id) {
                    $managerStats = RealtimeStatsService::getManagerStats($cobrador->assigned_manager_id);

                    $wsService->notifyUser(
                        (string) $cobrador->assigned_manager_id,
                        'stats.manager.updated',
                        [
                            'stats' => $managerStats,
                            'event_type' => 'payment_created',
                            'payment_id' => $payment->id,
                        ]
                    );
                }
            }

            Log::debug('Stats broadcast sent for payment', [
                'payment_id' => $payment->id,
                'cobrador_id' => $payment->cobrador_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error broadcasting stats on payment', [
                'error' => $e->getMessage(),
                'payment_id' => $event->payment->id,
            ]);
            // No relanzamos el error para no fallar el flujo de pagos
        }
    }
}
