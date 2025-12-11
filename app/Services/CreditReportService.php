<?php

namespace App\Services;

use App\DTOs\CreditReportDTO;
use App\Models\Credit;
use App\Traits\AuthorizeReportAccessTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * CreditReportService - Servicio Centralizado de Reportes de Créditos
 *
 * ✅ ARQUITECTURA CENTRALIZADA - OPCIÓN 3
 * Encapsula toda la lógica de reportes de créditos en un único servicio.
 *
 * ✅ SEGURIDAD:
 * - Usa AuthorizeReportAccessTrait para autorización centralizada
 * - Cobrador: Ve créditos que creó O entregó
 * - Manager: Ve créditos creados o entregados por sus cobradores
 * - Admin: Ve todo
 */
class CreditReportService
{
    use AuthorizeReportAccessTrait;
    /**
     * Genera el reporte completo de créditos
     *
     * @param array $filters Filtros (status, cobrador_id, client_id, etc)
     * @param object $currentUser Usuario autenticado
     * @return CreditReportDTO
     */
    public function generateReport(array $filters, object $currentUser): CreditReportDTO
    {
        // 1. Obtener créditos con filtros
        $query = $this->buildQuery($filters, $currentUser);

        // 2. Ordenar por cobrador (createdBy) y luego por fecha
        // Esto agrupa visualmente los créditos del mismo cobrador
        $credits = $query
            ->join('users as creator', 'credits.created_by', '=', 'creator.id')
            ->orderBy('creator.name', 'asc')  // Agrupar por nombre del cobrador
            ->orderBy('credits.created_at', 'desc')  // Dentro de cada grupo, ordenar por fecha
            ->select('credits.*')  // Seleccionar solo columnas de credits para evitar conflictos
            ->get();

        // 3. Transformar créditos
        $transformedCredits = $this->transformCredits($credits);

        // 4. Calcular resumen
        $summary = $this->calculateSummary($credits);

        // 5. Retornar DTO
        return new CreditReportDTO(
            credits: $transformedCredits,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }

    /**
     * Construye la query base con filtros
     */
    private function buildQuery(array $filters, object $currentUser): Builder
    {
        $query = Credit::with(['client', 'createdBy', 'deliveredBy', 'payments', 'cashBalance']);

        // Filtros específicos
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Cobrador flexible: creador o quien entregó
        if (!empty($filters['cobrador_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('created_by', $filters['cobrador_id'])
                    ->orWhere('delivered_by', $filters['cobrador_id']);
            });
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (!empty($filters['delivered_by'])) {
            $query->where('delivered_by', $filters['delivered_by']);
        }

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        // Filtros por fecha
        if (!empty($filters['start_date'])) {
            $query->whereDate('credits.created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('credits.created_at', '<=', $filters['end_date']);
        }

        // ✅ AUTORIZACIÓN CENTRALIZADA - Maneja múltiples relaciones con nombres correctos
        // Usa las relaciones camelCase definidas en el modelo Credit: createdBy() y deliveredBy()
        $this->authorizeUserAccessMultiple($query, $currentUser, ['createdBy', 'deliveredBy']);

        return $query;
    }

    /**
     * Transforma cada Credit a estructura con datos completos
     * Incluye cálculo avanzado de estado de pago basado en cuotas pagadas vs esperadas
     */
    private function transformCredits(Collection $credits): Collection
    {
        return $credits->map(function ($credit) {
            // Calcular estado de pago avanzado
            $totalInstallments = $credit->total_installments;
            $completedInstallments = $credit->getCompletedInstallmentsCount();
            $expectedInstallments = $credit->getExpectedInstallments();
            $pendingInstallments = $credit->getPendingInstallments();

            // Determinar estado de pago
            $paymentStatus = $this->calculatePaymentStatus(
                $completedInstallments,
                $expectedInstallments,
                $totalInstallments,
                $pendingInstallments,
                $credit->status
            );

            return [
                'id' => $credit->id,
                'client_id' => $credit->client_id,
                'client_name' => $credit->client?->name ?? 'N/A',
                'amount' => (float) $credit->amount,
                'amount_formatted' => 'Bs ' . number_format($credit->amount, 2),
                'balance' => (float) $credit->balance,
                'balance_formatted' => 'Bs ' . number_format($credit->balance, 2),
                'status' => $credit->status,
                'interest_rate' => (float) $credit->interest_rate,
                'created_by_id' => $credit->created_by,
                'created_by_name' => $credit->createdBy?->name ?? 'N/A',
                'delivered_by_id' => $credit->delivered_by,
                'delivered_by_name' => $credit->deliveredBy?->name ?? 'N/A',
                'total_installments' => $credit->total_installments,
                'completed_installments' => $completedInstallments,
                'expected_installments' => $expectedInstallments,
                'pending_installments' => $pendingInstallments,
                'installments_overdue' => max(0, $expectedInstallments - $completedInstallments),
                'payments_count' => $credit->payments->count(),
                'created_at' => $credit->created_at->format('Y-m-d H:i:s'),
                'created_at_formatted' => $credit->created_at->format('d/m/Y'),
                'payment_status' => $paymentStatus['status'],
                'payment_status_icon' => $paymentStatus['icon'],
                'payment_status_color' => $paymentStatus['color'],
                'payment_status_label' => $paymentStatus['label'],

                // ⭐ Campos estandarizados del sistema de severidad
                'days_overdue' => $credit->days_overdue,
                'overdue_severity' => $credit->overdue_severity,
                'requires_attention' => $credit->requires_attention,

                // ⭐ Campos de fechas importantes
                'delivered_at' => $credit->delivered_at?->format('Y-m-d H:i:s'),
                'delivered_at_formatted' => $credit->delivered_at?->format('d/m/Y'),
                'completed_at' => $credit->completed_at?->format('Y-m-d H:i:s'),
                'completed_at_formatted' => $credit->completed_at?->format('d/m/Y'),

                '_model' => $credit,
            ];
        });
    }

    /**
     * Calcula el estado de pago basado en múltiples criterios
     *
     * Estados:
     * - completed: Todas las cuotas pagadas
     * - current: Al día (cuotas pagadas = cuotas esperadas)
     * - ahead: Adelantado (cuotas pagadas > cuotas esperadas)
     * - warning: Retraso bajo (1-3 cuotas atrasadas)
     * - danger: Retraso alto (>3 cuotas atrasadas)
     */
    private function calculatePaymentStatus(int $completed, int $expected, int $total, int $pending, string $creditStatus): array
    {
        // Si el crédito está completado o todas las cuotas fueron pagadas
        if ($creditStatus === 'completed' || $pending === 0) {
            return [
                'status' => 'completed',
                'icon' => '✓',
                'color' => 'success',
                'label' => 'Completado',
            ];
        }

        // Calcular cuotas atrasadas
        $overdueInstallments = max(0, $expected - $completed);

        // Si está al día o adelantado
        if ($overdueInstallments === 0) {
            if ($completed > $expected) {
                // Adelantado
                return [
                    'status' => 'ahead',
                    'icon' => '↑',
                    'color' => 'info',
                    'label' => 'Adelantado',
                ];
            } else {
                // Al día
                return [
                    'status' => 'current',
                    'icon' => '→',
                    'color' => 'primary',
                    'label' => 'Al día',
                ];
            }
        }

        // Retraso bajo (1-3 cuotas)
        if ($overdueInstallments >= 1 && $overdueInstallments <= 3) {
            return [
                'status' => 'warning',
                'icon' => '⚠',
                'color' => 'warning',
                'label' => "Retraso bajo ({$overdueInstallments} cuota" . ($overdueInstallments > 1 ? 's' : '') . ")",
            ];
        }

        // Retraso alto (>3 cuotas)
        return [
            'status' => 'danger',
            'icon' => '✕',
            'color' => 'danger',
            'label' => "Retraso alto ({$overdueInstallments} cuotas)",
        ];
    }

    /**
     * Calcula el resumen agregado
     */
    private function calculateSummary(Collection $credits): array
    {
        $activeCredits = $credits->where('status', 'active');
        $completedCredits = $credits->where('status', 'completed');

        return [
            'total_credits' => $credits->count(),
            'total_amount' => (float) round($credits->sum('amount'), 2),
            'total_amount_formatted' => 'Bs ' . number_format($credits->sum('amount'), 2),
            'active_credits' => $activeCredits->count(),
            'active_amount' => (float) round($activeCredits->sum('amount'), 2),
            'active_amount_formatted' => 'Bs ' . number_format($activeCredits->sum('amount'), 2),
            'completed_credits' => $completedCredits->count(),
            'completed_amount' => (float) round($completedCredits->sum('amount'), 2),
            'completed_amount_formatted' => 'Bs ' . number_format($completedCredits->sum('amount'), 2),
            'total_balance' => (float) round($credits->sum('balance'), 2),
            'total_balance_formatted' => 'Bs ' . number_format($credits->sum('balance'), 2),
            'pending_amount' => (float) round($credits->sum('balance'), 2),
            'pending_amount_formatted' => 'Bs ' . number_format($credits->sum('balance'), 2),
            'average_amount' => (float) round($credits->avg('amount') ?? 0, 2),
            'average_amount_formatted' => 'Bs ' . number_format($credits->avg('amount') ?? 0, 2),
            'total_paid' => (float) round($credits->sum('total_paid') ?? 0, 2),
            'total_paid_formatted' => 'Bs ' . number_format($credits->sum('total_paid') ?? 0, 2),
        ];
    }
}
