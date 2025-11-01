<?php

namespace App\Services;

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use App\Models\CashBalance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * 📊 RealtimeStatsService - Servicio de estadísticas en tiempo real
 *
 * Mantiene un caché de estadísticas actualizadas que se recalculan
 * cuando ocurren eventos como pagos o créditos.
 *
 * Las estadísticas se cachean para evitar queries costosas en cada request.
 */
class RealtimeStatsService
{
    // TTL de 5 minutos para estadísticas
    protected const CACHE_TTL = 300;

    // Prefijos de caché
    protected const CACHE_PREFIX = 'stats:';
    protected const GLOBAL_STATS = 'stats:global';
    protected const COBRADOR_STATS = 'stats:cobrador:';
    protected const MANAGER_STATS = 'stats:manager:';

    /**
     * Actualizar estadísticas globales cuando ocurre un evento
     *
     * Se llama cuando:
     * - Se crea un pago
     * - Se crea un crédito
     * - Se entrega un crédito
     */
    public static function updateGlobalStats(): void
    {
        try {
            $stats = self::calculateGlobalStats();
            Cache::put(self::GLOBAL_STATS, $stats, self::CACHE_TTL);
            Log::debug('Global stats updated', $stats);

            // Notificar al WebSocket server sobre la actualización
            self::notifyStatsUpdate('global', $stats);
        } catch (\Exception $e) {
            Log::error('Error updating global stats', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Actualizar estadísticas de un cobrador específico
     */
    public static function updateCobradorStats(int $cobradorId): void
    {
        try {
            $stats = self::calculateCobradorStats($cobradorId);
            Cache::put(self::COBRADOR_STATS . $cobradorId, $stats, self::CACHE_TTL);
            Log::debug("Cobrador {$cobradorId} stats updated", $stats);

            // Notificar al WebSocket server
            self::notifyStatsUpdate('cobrador', $stats, $cobradorId);

            // También actualizar estadísticas del manager si existe
            $cobrador = User::find($cobradorId);
            if ($cobrador && $cobrador->assigned_manager_id) {
                self::updateManagerStats($cobrador->assigned_manager_id);
            }
        } catch (\Exception $e) {
            Log::error("Error updating cobrador {$cobradorId} stats", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Actualizar estadísticas de un manager
     */
    public static function updateManagerStats(int $managerId): void
    {
        try {
            $stats = self::calculateManagerStats($managerId);
            Cache::put(self::MANAGER_STATS . $managerId, $stats, self::CACHE_TTL);
            Log::debug("Manager {$managerId} stats updated", $stats);

            // Notificar al WebSocket server
            self::notifyStatsUpdate('manager', $stats, $managerId);
        } catch (\Exception $e) {
            Log::error("Error updating manager {$managerId} stats", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtener estadísticas globales (cacheadas)
     */
    public static function getGlobalStats(): array
    {
        return Cache::remember(self::GLOBAL_STATS, self::CACHE_TTL, function () {
            return self::calculateGlobalStats();
        });
    }

    /**
     * Obtener estadísticas de un cobrador (cacheadas)
     */
    public static function getCobradorStats(int $cobradorId): array
    {
        return Cache::remember(self::COBRADOR_STATS . $cobradorId, self::CACHE_TTL, function () {
            return self::calculateCobradorStats($cobradorId);
        });
    }

    /**
     * Obtener estadísticas de un manager (cacheadas)
     */
    public static function getManagerStats(int $managerId): array
    {
        return Cache::remember(self::MANAGER_STATS . $managerId, self::CACHE_TTL, function () {
            return self::calculateManagerStats($managerId);
        });
    }

    /**
     * Calcular estadísticas globales (query de base de datos)
     */
    protected static function calculateGlobalStats(): array
    {
        $today = Carbon::now()->startOfDay();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        return [
            'total_clients' => User::role('client')->count(),
            'total_cobradores' => User::role('cobrador')->count(),
            'total_managers' => User::role('manager')->count(),
            'total_credits' => Credit::where('status', 'active')->count(),
            'total_payments' => Payment::count(),
            'overdue_payments' => Payment::where('status', 'overdue')->count(),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'total_balance' => Credit::where('status', 'active')->sum('balance') ?? 0,
            'today_collections' => Payment::whereDate('payment_date', $today)
                ->where('status', 'paid')
                ->sum('amount') ?? 0,
            'month_collections' => Payment::whereBetween('payment_date', [$monthStart, $monthEnd])
                ->where('status', 'paid')
                ->sum('amount') ?? 0,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Calcular estadísticas de un cobrador
     */
    protected static function calculateCobradorStats(int $cobradorId): array
    {
        $today = Carbon::now()->startOfDay();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $creditsQuery = Credit::where('created_by', $cobradorId);
        $paymentsQuery = Payment::where('cobrador_id', $cobradorId);

        return [
            'cobrador_id' => $cobradorId,
            'total_clients' => User::role('client')
                ->where('assigned_cobrador_id', $cobradorId)
                ->count(),
            'total_credits' => $creditsQuery->where('status', 'active')->count(),
            'total_payments' => $paymentsQuery->count(),
            'overdue_payments' => $paymentsQuery->where('status', 'overdue')->count(),
            'pending_payments' => $paymentsQuery->where('status', 'pending')->count(),
            'total_balance' => $creditsQuery->where('status', 'active')->sum('balance') ?? 0,
            'today_collections' => $paymentsQuery
                ->whereDate('payment_date', $today)
                ->where('status', 'paid')
                ->sum('amount') ?? 0,
            'month_collections' => $paymentsQuery
                ->whereBetween('payment_date', [$monthStart, $monthEnd])
                ->where('status', 'paid')
                ->sum('amount') ?? 0,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Calcular estadísticas de un manager y su equipo
     */
    protected static function calculateManagerStats(int $managerId): array
    {
        $today = Carbon::now()->startOfDay();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $cobradorIds = User::role('cobrador')
            ->where('assigned_manager_id', $managerId)
            ->pluck('id')
            ->toArray();

        $creditsQuery = Credit::whereIn('created_by', $cobradorIds);
        $paymentsQuery = Payment::whereIn('cobrador_id', $cobradorIds);

        return [
            'manager_id' => $managerId,
            'total_cobradores' => count($cobradorIds),
            'total_credits' => $creditsQuery->where('status', 'active')->count(),
            'total_payments' => $paymentsQuery->count(),
            'overdue_payments' => $paymentsQuery->where('status', 'overdue')->count(),
            'pending_payments' => $paymentsQuery->where('status', 'pending')->count(),
            'total_balance' => $creditsQuery->where('status', 'active')->sum('balance') ?? 0,
            'today_collections' => $paymentsQuery
                ->whereDate('payment_date', $today)
                ->where('status', 'paid')
                ->sum('amount') ?? 0,
            'month_collections' => $paymentsQuery
                ->whereBetween('payment_date', [$monthStart, $monthEnd])
                ->where('status', 'paid')
                ->sum('amount') ?? 0,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Notificar al servidor WebSocket sobre la actualización de estadísticas
     * Se ejecuta en background para no bloquear la request
     */
    protected static function notifyStatsUpdate(string $type, array $stats, ?int $userId = null): void
    {
        // Usar queue job en background
        try {
            app(\App\Jobs\NotifyWebSocketStatsUpdate::class)->dispatch($type, $stats, $userId);
        } catch (\Exception $e) {
            Log::warning('Could not dispatch stats update job', [
                'error' => $e->getMessage(),
                'type' => $type,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Invalidar todo el caché de estadísticas
     */
    public static function invalidateAll(): void
    {
        Cache::forget(self::GLOBAL_STATS);

        // Invalidar caché de todos los cobradores
        foreach (User::role('cobrador')->pluck('id') as $cobradorId) {
            Cache::forget(self::COBRADOR_STATS . $cobradorId);
        }

        // Invalidar caché de todos los managers
        foreach (User::role('manager')->pluck('id') as $managerId) {
            Cache::forget(self::MANAGER_STATS . $managerId);
        }

        Log::info('All stats cache invalidated');
    }
}
