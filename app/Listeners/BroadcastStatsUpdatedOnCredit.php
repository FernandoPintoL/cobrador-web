<?php

namespace App\Listeners;

use App\Events\CreditCreated;
use App\Events\CreditApproved;
use App\Events\CreditDelivered;
use App\Events\CreditRejected;
use App\Services\RealtimeStatsService;
use App\Services\WebSocketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * 📊 Enviar estadísticas actualizadas por WebSocket cuando ocurren eventos de crédito
 *
 * Se ejecuta DESPUÉS de UpdateRealtimeStatsOnCredit*,
 * enviando las estadísticas actualizadas a través del WebSocket.
 */
class BroadcastStatsUpdatedOnCredit implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries = 3;
    public int $backoff = 5;

    protected WebSocketNotificationService $wsService;

    public function __construct()
    {
        $this->wsService = app(WebSocketNotificationService::class);
    }

    /**
     * Cuando se crea un crédito
     */
    public function handleCreditCreated(CreditCreated $event): void
    {
        $this->broadcastStatsForCredit($event->credit, 'credit_created');
    }

    /**
     * Cuando se aprueba un crédito
     */
    public function handleCreditApproved(CreditApproved $event): void
    {
        $this->broadcastStatsForCredit($event->credit, 'credit_approved');
    }

    /**
     * Cuando se entrega un crédito
     */
    public function handleCreditDelivered(CreditDelivered $event): void
    {
        $this->broadcastStatsForCredit($event->credit, 'credit_delivered');
    }

    /**
     * Cuando se rechaza un crédito
     */
    public function handleCreditRejected(CreditRejected $event): void
    {
        $this->broadcastStatsForCredit($event->credit, 'credit_rejected');
    }

    /**
     * Enviar estadísticas actualizadas para un crédito
     */
    protected function broadcastStatsForCredit($credit, string $eventType): void
    {
        try {
            // Notificar estadísticas globales
            $globalStats = RealtimeStatsService::getGlobalStats();
            $this->wsService->notifyAll('stats.global.updated', [
                'stats' => $globalStats,
                'event_type' => $eventType,
                'credit_id' => $credit->id,
            ]);

            // Notificar estadísticas del cobrador
            if ($credit->created_by) {
                $cobradorStats = RealtimeStatsService::getCobradorStats($credit->created_by);

                $this->wsService->notifyUser(
                    (string) $credit->created_by,
                    'stats.cobrador.updated',
                    [
                        'stats' => $cobradorStats,
                        'event_type' => $eventType,
                        'credit_id' => $credit->id,
                    ]
                );

                // Notificar estadísticas del manager si existe
                $cobrador = $credit->cobrador;
                if ($cobrador && $cobrador->assigned_manager_id) {
                    $managerStats = RealtimeStatsService::getManagerStats($cobrador->assigned_manager_id);

                    $this->wsService->notifyUser(
                        (string) $cobrador->assigned_manager_id,
                        'stats.manager.updated',
                        [
                            'stats' => $managerStats,
                            'event_type' => $eventType,
                            'credit_id' => $credit->id,
                        ]
                    );
                }
            }

            Log::debug('Stats broadcast sent for credit', [
                'credit_id' => $credit->id,
                'event_type' => $eventType,
            ]);
        } catch (\Exception $e) {
            Log::error('Error broadcasting stats on credit event', [
                'error' => $e->getMessage(),
                'credit_id' => $credit->id,
                'event_type' => $eventType,
            ]);
            // No relanzamos el error para no afectar el flujo de créditos
        }
    }
}
