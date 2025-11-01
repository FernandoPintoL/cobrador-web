<?php

namespace App\Listeners;

use App\Events\CreditCreated;
use App\Events\CreditApproved;
use App\Events\CreditDelivered;
use App\Events\CreditRejected;
use App\Services\RealtimeStatsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * 📊 Actualizar estadísticas en tiempo real cuando ocurren eventos de crédito
 *
 * Se ejecuta DESPUÉS de las notificaciones WebSocket existentes,
 * sin interferir con el flujo de créditos.
 */
class UpdateRealtimeStatsOnCredit implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * El nombre de la cola
     */
    public string $queue = 'default';

    /**
     * Número de intentos
     */
    public int $tries = 3;

    /**
     * Backoff en segundos
     */
    public int $backoff = 5;

    /**
     * Actualizar estadísticas cuando se crea un crédito
     */
    public function handleCreditCreated(CreditCreated $event): void
    {
        try {
            $credit = $event->credit;

            if ($credit->created_by) {
                RealtimeStatsService::updateCobradorStats($credit->created_by);
            }

            RealtimeStatsService::updateGlobalStats();

            Log::info('Real-time stats updated for credit created', [
                'credit_id' => $credit->id,
                'cobrador_id' => $credit->created_by,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating stats on credit created', [
                'error' => $e->getMessage(),
                'credit_id' => $event->credit->id,
            ]);
            throw $e;
        }
    }

    /**
     * Actualizar estadísticas cuando se aprueba un crédito
     */
    public function handleCreditApproved(CreditApproved $event): void
    {
        try {
            $credit = $event->credit;

            if ($credit->created_by) {
                RealtimeStatsService::updateCobradorStats($credit->created_by);
            }

            RealtimeStatsService::updateGlobalStats();

            Log::info('Real-time stats updated for credit approved', [
                'credit_id' => $credit->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating stats on credit approved', [
                'error' => $e->getMessage(),
                'credit_id' => $event->credit->id,
            ]);
            throw $e;
        }
    }

    /**
     * Actualizar estadísticas cuando se entrega un crédito
     */
    public function handleCreditDelivered(CreditDelivered $event): void
    {
        try {
            $credit = $event->credit;

            if ($credit->created_by) {
                RealtimeStatsService::updateCobradorStats($credit->created_by);
            }

            RealtimeStatsService::updateGlobalStats();

            Log::info('Real-time stats updated for credit delivered', [
                'credit_id' => $credit->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating stats on credit delivered', [
                'error' => $e->getMessage(),
                'credit_id' => $event->credit->id,
            ]);
            throw $e;
        }
    }

    /**
     * Actualizar estadísticas cuando se rechaza un crédito
     */
    public function handleCreditRejected(CreditRejected $event): void
    {
        try {
            $credit = $event->credit;

            if ($credit->created_by) {
                RealtimeStatsService::updateCobradorStats($credit->created_by);
            }

            RealtimeStatsService::updateGlobalStats();

            Log::info('Real-time stats updated for credit rejected', [
                'credit_id' => $credit->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating stats on credit rejected', [
                'error' => $e->getMessage(),
                'credit_id' => $event->credit->id,
            ]);
            throw $e;
        }
    }
}
