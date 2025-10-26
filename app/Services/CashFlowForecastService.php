<?php

namespace App\Services;

use App\DTOs\CashFlowForecastDTO;
use App\Models\Credit;
use Illuminate\Support\Collection;

class CashFlowForecastService
{
    public function generateReport(array $filters, object $currentUser): CashFlowForecastDTO
    {
        $months = $filters['months'] ?? 6;
        $forecast = $this->calculateForecast($months, $currentUser);
        $summary = $this->calculateSummary(collect($forecast));

        return new CashFlowForecastDTO(
            data: collect($forecast),
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }

    private function calculateForecast(int $months, object $currentUser): array
    {
        $forecast = [];
        $baseQuery = Credit::with('payments');

        if ($currentUser->hasRole('cobrador')) {
            $baseQuery->where('created_by', $currentUser->id)
                ->orWhere('delivered_by', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $baseQuery->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        for ($i = 0; $i < $months; $i++) {
            $month = now()->addMonths($i);
            $monthStart = $month->startOfMonth();
            $monthEnd = $month->endOfMonth();

            $creditsInMonth = $baseQuery->whereBetween('delivered_at', [$monthStart, $monthEnd])->get();

            $forecast[] = [
                'month' => $month->format('m/Y'),
                'month_name' => $month->format('F Y'),
                'expected_credits' => $creditsInMonth->count(),
                'expected_amount' => (float) round($creditsInMonth->sum('amount'), 2),
                'expected_amount_formatted' => 'Bs ' . number_format($creditsInMonth->sum('amount'), 2),
                'forecast_revenue' => (float) round($creditsInMonth->sum('amount') * 0.1, 2), // 10% estimado
            ];
        }

        return $forecast;
    }

    private function calculateSummary(Collection $data): array
    {
        return [
            'total_months' => $data->count(),
            'total_expected_credits' => (int) $data->sum('expected_credits'),
            'total_expected_amount' => (float) round($data->sum('expected_amount'), 2),
            'total_expected_amount_formatted' => 'Bs ' . number_format($data->sum('expected_amount'), 2),
            'total_forecast_revenue' => (float) round($data->sum('forecast_revenue'), 2),
            'total_forecast_revenue_formatted' => 'Bs ' . number_format($data->sum('forecast_revenue'), 2),
            'avg_monthly' => (float) round($data->avg('expected_amount'), 2),
        ];
    }
}
