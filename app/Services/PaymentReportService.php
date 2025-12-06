<?php

namespace App\Services;

use App\DTOs\PaymentReportDTO;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Traits\AuthorizeReportAccessTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * PaymentReportService - Servicio Centralizado de Reportes de Pagos
 *
 * ✅ ARQUITECTURA CENTRALIZADA - OPCIÓN 3 (RECOMENDADA)
 *
 * Este servicio encapsula TODA la lógica de reportes de pagos:
 * 1. Filtrado y consultas
 * 2. Transformación de datos
 * 3. Cálculos de resumen
 * 4. Agregaciones
 *
 * BENEFICIOS:
 * - Un único punto de verdad para lógica de reportes
 * - Fácil de mantener y testear
 * - Evita duplicación entre Controller, Resource, Export, Blade
 * - Performance optimizado con caché en memoria
 * - SOLID principles: Single Responsibility
 *
 * ✅ SEGURIDAD:
 * - Usa AuthorizeReportAccessTrait para autorización centralizada
 * - Cobrador: Ve solo sus propios pagos
 * - Manager: Ve pagos de sus cobradores asignados
 * - Admin: Ve todo
 *
 * FLUJO:
 * Controller → Service.generateReport() → DTO → Controller → [Resource|Export|Blade]
 */
class PaymentReportService
{
    use AuthorizeReportAccessTrait;
    /**
     * Genera el reporte completo de pagos
     *
     * @param array $filters Filtros (start_date, end_date, cobrador_id, etc)
     * @param object $currentUser Usuario autenticado para validaciones de rol
     * @return PaymentReportDTO
     */
    public function generateReport(array $filters, object $currentUser): PaymentReportDTO
    {
        // 1. Obtener pagos con filtros aplicados
        $query = $this->buildQuery($filters, $currentUser);

        // 2. Ordenar por cobrador (para agrupar visualmente) y luego por fecha
        // Esto agrupa visualmente los pagos del mismo cobrador
        $payments = $query
            ->join('users as cobrador_user', 'payments.cobrador_id', '=', 'cobrador_user.id')
            ->orderBy('cobrador_user.name', 'asc')  // Agrupar por nombre del cobrador
            ->orderBy('payments.payment_date', 'desc')  // Dentro de cada grupo, ordenar por fecha
            ->select('payments.*')  // Seleccionar solo columnas de payments para evitar conflictos
            ->get();

        // 3. Transformar payments a PaymentResourceData (cálculos + formatos)
        $transformedPayments = $this->transformPayments($payments);

        // 4. Calcular resumen agregado (reutiliza caché de transformación)
        $summary = $this->calculateSummary($payments, $transformedPayments);

        // 5. Retornar DTO con todos los datos
        return new PaymentReportDTO(
            payments: $transformedPayments,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }

    /**
     * Construye la query base con filtros aplicados
     *
     * ✅ SEGURIDAD: Usa AuthorizeReportAccessTrait para garantizar que:
     * - Cobradores ven SOLO sus propios pagos
     * - Managers ven pagos de sus cobradores asignados
     * - Admin ve todo
     */
    private function buildQuery(array $filters, object $currentUser): Builder
    {
        $query = Payment::with(['cobrador', 'credit.client']);

        // Filtros por fecha
        if (!empty($filters['start_date'])) {
            $query->whereDate('payment_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('payment_date', '<=', $filters['end_date']);
        }

        // Filtro por cobrador específico (si se solicita)
        // Pero solo si el usuario tiene autorización para verlo
        if (!empty($filters['cobrador_id'])) {
            $cobradorIds = $this->getAuthorizedCobradorIds($currentUser);
            if (in_array($filters['cobrador_id'], $cobradorIds)) {
                $query->where('cobrador_id', $filters['cobrador_id']);
            }
        }

        // ✅ AUTORIZACIÓN CENTRALIZADA
        $this->authorizeUserAccess($query, $currentUser, 'cobrador_id');

        return $query;
    }

    /**
     * Transforma cada Payment a estructura con cálculos
     *
     * ✅ OPTIMIZACIÓN: Usa caché en memoria para evitar cálculos redundantes
     * - getPrincipalPortion() (caché estática)
     * - getInterestPortion() (caché estática)
     * - getRemainingForInstallment() (caché estática)
     *
     * Reutiliza el MISMO caché cuando se hace JSON + Excel simultáneamente
     */
    private function transformPayments(Collection $payments): Collection
    {
        // Aquí reutilizamos PaymentResource que ya contiene la lógica de transformación
        // Esto asegura consistencia entre JSON API, Excel y Blade
        return $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payment_date' => $payment->payment_date->format('d/m/Y'),
                'payment_date_iso' => $payment->payment_date->format('Y-m-d'),
                'cobrador_id' => $payment->cobrador_id,
                'cobrador_name' => $payment->cobrador?->name ?? 'N/A',
                'credit_id' => $payment->credit_id,
                'client_id' => $payment->credit?->client_id,
                'client_name' => $payment->credit?->client?->name ?? 'N/A',
                'amount' => (float) $payment->amount,
                'amount_formatted' => 'Bs ' . number_format($payment->amount, 2),
                'status' => $payment->status,

                // ✅ CÁLCULOS CACHEADOS - Métodos que usan caché en memoria
                'principal_portion' => round($payment->getPrincipalPortion(), 2),
                'principal_portion_formatted' => 'Bs ' . number_format($payment->getPrincipalPortion(), 2),

                'interest_portion' => round($payment->getInterestPortion(), 2),
                'interest_portion_formatted' => 'Bs ' . number_format($payment->getInterestPortion(), 2),

                'remaining_for_installment' => $payment->getRemainingForInstallment(),
                'remaining_for_installment_formatted' => !is_null($payment->getRemainingForInstallment())
                    ? 'Bs ' . number_format($payment->getRemainingForInstallment(), 2)
                    : 'N/A',

                // Información del crédito
                'credit' => [
                    'id' => $payment->credit?->id,
                    'installment_number' => $payment->installment_number,
                    'installment_number_display' => $payment->installment_number > 0 ? $payment->installment_number : 'N/A',
                    'pending_installments' => $payment->credit ? $payment->credit->getPendingInstallments() : 'N/A',
                    'balance' => (float) ($payment->credit?->balance ?? 0),
                    'balance_formatted' => 'Bs ' . number_format($payment->credit?->balance ?? 0, 2),
                ],

                // Información adicional
                'payment_method' => $payment->payment_method ?? 'N/A',
                'accumulated_amount' => (float) $payment->accumulated_amount,
                'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                'created_at_formatted' => $payment->created_at->format('d/m/Y H:i'),

                // Raw model para acceso a métodos (si es necesario)
                '_model' => $payment,
            ];
        });
    }

    /**
     * Calcula el resumen agregado del reporte
     *
     * ✅ EFICIENCIA: Reutiliza los valores CACHEADOS en cada payment
     * No recalcula principal_portion, interest_portion, remaining_for_installment
     * Simplemente suma los valores ya calculados
     */
    private function calculateSummary(Collection $payments, Collection $transformedPayments): array
    {
        // Totales usando métodos cacheados (NO hace query adicionales)
        $totalWithoutInterest = $payments->sum(function ($p) {
            return $p->getPrincipalPortion() ?? 0;
        });

        $totalInterest = $payments->sum(function ($p) {
            return $p->getInterestPortion() ?? 0;
        });

        $totalRemainingToFinish = $payments->sum(function ($p) {
            return $p->getRemainingForInstallment() ?? 0;
        });

        return [
            'total_payments' => $payments->count(),
            'total_amount' => (float) round($payments->sum('amount'), 2),
            'total_amount_formatted' => 'Bs ' . number_format($payments->sum('amount'), 2),
            'average_payment' => (float) round($payments->avg('amount') ?? 0, 2),
            'average_payment_formatted' => 'Bs ' . number_format($payments->avg('amount') ?? 0, 2),
            'total_without_interest' => (float) round($totalWithoutInterest, 2),
            'total_without_interest_formatted' => 'Bs ' . number_format($totalWithoutInterest, 2),
            'total_interest' => (float) round($totalInterest, 2),
            'total_interest_formatted' => 'Bs ' . number_format($totalInterest, 2),
            'total_remaining_to_finish_installments' => (float) round($totalRemainingToFinish, 2),
            'total_remaining_to_finish_installments_formatted' => 'Bs ' . number_format($totalRemainingToFinish, 2),
        ];
    }

    /**
     * Obtiene los datos en formato para PaymentResource::collection()
     * Útil para JSON API
     */
    public function getPaymentsForResource(PaymentReportDTO $dto): Collection
    {
        return collect($dto->getPayments())->map(function ($payment) {
            return $payment['_model'];
        });
    }
}
