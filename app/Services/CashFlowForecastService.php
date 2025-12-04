<?php

namespace App\Services;

use App\DTOs\CashFlowForecastDTO;
use App\Models\Credit;
use App\Traits\AuthorizeReportAccessTrait;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * CashFlowForecastService - Proyección Completa de Flujo de Caja
 *
 * ✅ FUNCIONALIDAD:
 * - Proyecta ENTRADAS: Pagos esperados día a día (usando getPaymentSchedule)
 * - Proyecta SALIDAS: Entregas programadas día a día
 * - Calcula balance neto proyectado
 * - Identifica pagos vencidos vs pendientes
 *
 * ✅ SEGURIDAD:
 * - Usa AuthorizeReportAccessTrait para autorización centralizada
 * - Cobrador: Ve proyecciones de sus propios créditos
 * - Manager: Ve proyecciones de créditos de sus cobradores
 * - Admin: Ve todas las proyecciones
 */
class CashFlowForecastService
{
    use AuthorizeReportAccessTrait;

    public function generateReport(array $filters, object $currentUser): CashFlowForecastDTO
    {
        $months = $filters['months'] ?? 6;
        $endDate = now()->addMonths($months)->endOfDay();

        // Generar proyecciones día a día
        $projections = $this->calculateDailyProjections($currentUser, $endDate);

        // Calcular resumen
        $summary = $this->calculateSummary($projections, $months, $endDate);

        return new CashFlowForecastDTO(
            data: $projections,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }

    /**
     * Calcula proyecciones día a día de entradas (pagos) y salidas (entregas)
     */
    private function calculateDailyProjections(object $currentUser, Carbon $endDate): Collection
    {
        $projections = collect();

        // 1. Proyectar ENTRADAS (pagos esperados)
        $paymentProjections = $this->projectExpectedPayments($currentUser, $endDate);
        $projections = $projections->merge($paymentProjections);

        // 2. Proyectar SALIDAS (entregas programadas)
        $deliveryProjections = $this->projectScheduledDeliveries($currentUser, $endDate);
        $projections = $projections->merge($deliveryProjections);

        // 3. Ordenar cronológicamente
        return $projections->sortBy('date')->values();
    }

    /**
     * Proyecta pagos esperados usando getPaymentSchedule() de cada crédito activo
     */
    private function projectExpectedPayments(object $currentUser, Carbon $endDate): Collection
    {
        $projections = collect();
        $today = now()->startOfDay();

        // Obtener créditos activos según rol del usuario
        $activeCredits = $this->getActiveCreditsForUser($currentUser);

        foreach ($activeCredits as $credit) {
            // Usar función existente que calcula calendario completo
            $schedule = $credit->getPaymentSchedule();

            foreach ($schedule as $installment) {
                $dueDate = Carbon::parse($installment['due_date'])->startOfDay();

                // Solo incluir pagos pendientes dentro del rango de proyección
                if ($installment['status'] !== 'paid' && $dueDate->lte($endDate)) {
                    $isOverdue = $dueDate->lt($today);

                    $projections->push([
                        'date' => $installment['due_date'],
                        'type' => 'payment',
                        'credit_id' => $credit->id,
                        'client_name' => $credit->client->name ?? 'N/A',
                        'cobrador_name' => $credit->createdBy->name ?? 'N/A',
                        'amount' => (float) $installment['amount'],
                        'frequency' => $credit->frequency,
                        'installment_number' => $installment['installment_number'],
                        'is_overdue' => $isOverdue,
                        'status' => $isOverdue ? 'overdue' : 'pending',
                        'payment_date' => $installment['due_date'], // Alias para compatibilidad con blade
                        'projected_amount' => (float) $installment['amount'], // Alias para compatibilidad con blade
                    ]);
                }
            }
        }

        return $projections;
    }

    /**
     * Proyecta entregas programadas de créditos aprobados pendientes
     */
    private function projectScheduledDeliveries(object $currentUser, Carbon $endDate): Collection
    {
        $projections = collect();
        $today = now()->startOfDay();

        // Obtener créditos aprobados pero no entregados
        $baseQuery = Credit::with(['client', 'createdBy'])
            ->where('status', 'approved')
            ->whereNull('delivered_at');

        // Aplicar filtros según rol
        if ($currentUser->hasRole('cobrador')) {
            $baseQuery->where('created_by', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $baseQuery->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $pendingDeliveries = $baseQuery->get();

        foreach ($pendingDeliveries as $credit) {
            // Usar scheduled_delivery_date si existe, sino usar fecha de aprobación + 1 día
            $deliveryDate = $credit->scheduled_delivery_date
                ? Carbon::parse($credit->scheduled_delivery_date)
                : ($credit->approved_at ? Carbon::parse($credit->approved_at)->addDay() : $today);

            // Solo incluir entregas dentro del rango de proyección
            if ($deliveryDate->lte($endDate)) {
                $isOverdue = $deliveryDate->lt($today);

                $projections->push([
                    'date' => $deliveryDate->format('Y-m-d'),
                    'type' => 'delivery',
                    'credit_id' => $credit->id,
                    'client_name' => $credit->client->name ?? 'N/A',
                    'cobrador_name' => $credit->createdBy->name ?? 'N/A',
                    'amount' => (float) $credit->amount,
                    'frequency' => $credit->frequency,
                    'installment_number' => null,
                    'is_overdue' => $isOverdue,
                    'status' => $isOverdue ? 'overdue' : 'scheduled',
                    'payment_date' => $deliveryDate->format('Y-m-d'), // Alias para compatibilidad
                    'projected_amount' => (float) $credit->amount, // Alias para compatibilidad
                ]);
            }
        }

        return $projections;
    }

    /**
     * Obtiene créditos activos según rol del usuario
     */
    private function getActiveCreditsForUser(object $currentUser): Collection
    {
        $baseQuery = Credit::with(['client', 'createdBy', 'payments'])
            ->whereIn('status', ['active', 'overdue']);

        if ($currentUser->hasRole('cobrador')) {
            $baseQuery->where('created_by', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $baseQuery->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        return $baseQuery->get();
    }

    /**
     * Calcula resumen con totales de entradas, salidas y balance neto
     */
    private function calculateSummary(Collection $projections, int $months, Carbon $endDate): array
    {
        $payments = $projections->where('type', 'payment');
        $deliveries = $projections->where('type', 'delivery');

        $totalEntries = $payments->sum('amount');
        $totalExits = $deliveries->sum('amount');
        $netBalance = $totalEntries - $totalExits;

        $overdueAmount = $projections->where('is_overdue', true)->sum('amount');
        $pendingAmount = $projections->where('is_overdue', false)->sum('amount');

        return [
            'period' => [
                'start' => now()->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'months' => $months,
            ],
            'total_projected_payments' => $payments->count(),
            'total_projected_deliveries' => $deliveries->count(),
            'total_projected_transactions' => $projections->count(),
            'total_entries' => round($totalEntries, 2),
            'total_exits' => round($totalExits, 2),
            'net_balance' => round($netBalance, 2),
            'overdue_amount' => round($overdueAmount, 2),
            'pending_amount' => round($pendingAmount, 2),
            'total_active_credits' => $payments->unique('credit_id')->count(),
            'total_pending_deliveries' => $deliveries->count(),
            // Métricas adicionales
            'avg_daily_entries' => $payments->count() > 0
                ? round($totalEntries / max(1, now()->diffInDays($endDate)), 2)
                : 0,
            'avg_daily_exits' => $deliveries->count() > 0
                ? round($totalExits / max(1, now()->diffInDays($endDate)), 2)
                : 0,
        ];
    }
}
