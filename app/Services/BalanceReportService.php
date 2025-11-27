<?php

namespace App\Services;

use App\DTOs\BalanceReportDTO;
use App\Models\CashBalance;
use App\Traits\AuthorizeReportAccessTrait;
use Illuminate\Support\Collection;

/**
 * BalanceReportService - Servicio Centralizado de Reportes de Balances
 *
 * ✅ SEGURIDAD:
 * - Usa AuthorizeReportAccessTrait para autorización centralizada
 * - Cobrador: Ve SOLO sus balances
 * - Manager: Ve balances de sus cobradores asignados
 * - Admin: Ve todo
 */
class BalanceReportService
{
    use AuthorizeReportAccessTrait;
    public function generateReport(array $filters, object $currentUser): BalanceReportDTO
    {
        $query = $this->buildQuery($filters, $currentUser);
        $balances = $query->orderBy('date', 'desc')->get();

        if (!empty($filters['with_discrepancies'])) {
            $balances = $balances->filter(function ($balance) {
                $expected = $balance->initial_amount + $balance->collected_amount - $balance->lent_amount;
                $difference = abs($balance->final_amount - $expected);
                return $difference > 0.01;
            });
        }

        $transformedBalances = $this->transformBalances($balances);
        $summary = $this->calculateSummary($balances);

        return new BalanceReportDTO(
            balances: $transformedBalances,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }

    private function buildQuery(array $filters, object $currentUser)
    {
        $query = CashBalance::with(['cobrador', 'credits.client']);

        if (!empty($filters['start_date'])) {
            $query->whereDate('date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('date', '<=', $filters['end_date']);
        }

        if (!empty($filters['cobrador_id'])) {
            $query->where('cobrador_id', $filters['cobrador_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($currentUser->hasRole('cobrador')) {
            $query->where('cobrador_id', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('cobrador', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        return $query;
    }

    private function transformBalances(Collection $balances): Collection
    {
        return $balances->map(function ($balance) {
            $expected = $balance->initial_amount + $balance->collected_amount - $balance->lent_amount;
            $difference = $balance->final_amount - $expected;

            return [
                'id' => $balance->id,
                'date' => $balance->date->format('Y-m-d'),
                'date_formatted' => $balance->date->format('d/m/Y'),
                'cobrador_id' => $balance->cobrador_id,
                'cobrador_name' => $balance->cobrador?->name ?? 'N/A',
                'initial_amount' => (float) $balance->initial_amount,
                'initial_amount_formatted' => 'Bs ' . number_format($balance->initial_amount, 2),
                'collected_amount' => (float) $balance->collected_amount,
                'collected_amount_formatted' => 'Bs ' . number_format($balance->collected_amount, 2),
                'lent_amount' => (float) $balance->lent_amount,
                'lent_amount_formatted' => 'Bs ' . number_format($balance->lent_amount, 2),
                'final_amount' => (float) $balance->final_amount,
                'final_amount_formatted' => 'Bs ' . number_format($balance->final_amount, 2),
                'expected_amount' => (float) $expected,
                'expected_amount_formatted' => 'Bs ' . number_format($expected, 2),
                'difference' => (float) $difference,
                'difference_formatted' => 'Bs ' . number_format($difference, 2),
                'has_discrepancy' => abs($difference) > 0.01,
                'status' => $balance->status,
                'credits_delivered' => $balance->credits->count(),
                '_model' => $balance,
            ];
        });
    }

    private function calculateSummary(Collection $balances): array
    {
        $totalDiscrepancies = 0;
        $balancesWithIssues = 0;
        $totalDifferences = 0; // Para calcular promedio

        foreach ($balances as $balance) {
            $expected = $balance->initial_amount + $balance->collected_amount - $balance->lent_amount;
            $difference = $balance->final_amount - $expected;

            $totalDifferences += $difference; // Sumar diferencia (puede ser + o -)

            if (abs($difference) > 0.01) {
                $totalDiscrepancies += abs($difference);
                $balancesWithIssues++;
            }
        }

        // Calcular diferencia promedio
        $averageDifference = $balances->count() > 0
            ? $totalDifferences / $balances->count()
            : 0;

        return [
            'total_records' => $balances->count(),
            'total_initial' => (float) round($balances->sum('initial_amount'), 2),
            'total_initial_formatted' => 'Bs ' . number_format($balances->sum('initial_amount'), 2),
            'total_collected' => (float) round($balances->sum('collected_amount'), 2),
            'total_collected_formatted' => 'Bs ' . number_format($balances->sum('collected_amount'), 2),
            'total_lent' => (float) round($balances->sum('lent_amount'), 2),
            'total_lent_formatted' => 'Bs ' . number_format($balances->sum('lent_amount'), 2),
            'total_final' => (float) round($balances->sum('final_amount'), 2),
            'total_final_formatted' => 'Bs ' . number_format($balances->sum('final_amount'), 2),
            'total_credits_delivered' => $balances->sum(fn ($b) => $b->credits->count()),
            'total_discrepancies' => (float) round($totalDiscrepancies, 2),
            'total_discrepancies_formatted' => 'Bs ' . number_format($totalDiscrepancies, 2),
            'balances_with_issues' => $balancesWithIssues,
            'balances_ok' => $balances->count() - $balancesWithIssues,
            'open_balances' => $balances->where('status', 'open')->count(),
            'closed_balances' => $balances->where('status', 'closed')->count(),
            'reconciled_balances' => $balances->where('status', 'reconciled')->count(),
            // ✅ FIX: Agregar average_difference que la vista necesita
            'average_difference' => (float) round($averageDifference, 2),
            'average_difference_formatted' => 'Bs ' . number_format($averageDifference, 2),
        ];
    }
}
