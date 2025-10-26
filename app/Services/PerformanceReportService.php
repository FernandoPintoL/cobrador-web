<?php

namespace App\Services;

use App\DTOs\PerformanceReportDTO;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

class PerformanceReportService
{
    public function generateReport(array $filters, object $currentUser): PerformanceReportDTO
    {
        $startDate = $filters['start_date'] ?? now()->subMonth()->startOfDay();
        $endDate = $filters['end_date'] ?? now()->endOfDay();

        $cobradores = $this->getCobradores($filters, $currentUser);
        $performanceData = $this->calculatePerformance($cobradores, $startDate, $endDate);
        $summary = $this->calculateSummary($performanceData);

        return new PerformanceReportDTO(
            performance: collect($performanceData),
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }

    private function getCobradores(array $filters, object $currentUser)
    {
        $query = User::role('cobrador')->with(['assignedClients', 'assignedManager']);

        if (!empty($filters['cobrador_id'])) {
            $query->where('id', $filters['cobrador_id']);
        }

        if (!empty($filters['manager_id'])) {
            $query->where('assigned_manager_id', $filters['manager_id']);
        }

        if ($currentUser->hasRole('cobrador')) {
            $query->where('id', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $query->where('assigned_manager_id', $currentUser->id);
        }

        return $query->get();
    }

    private function calculatePerformance($cobradores, $startDate, $endDate): array
    {
        $data = [];

        foreach ($cobradores as $cobrador) {
            $creditsDelivered = Credit::where('delivered_by', $cobrador->id)
                ->whereBetween('delivered_at', [$startDate, $endDate])
                ->get();

            $paymentsCollected = Payment::where('cobrador_id', $cobrador->id)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->get();

            $activeCredits = Credit::where(function ($q) use ($cobrador) {
                $q->where('created_by', $cobrador->id)
                    ->orWhere('delivered_by', $cobrador->id);
            })->where('status', 'active')->with('payments')->get();

            $completedCredits = Credit::where(function ($q) use ($cobrador) {
                $q->where('created_by', $cobrador->id)
                    ->orWhere('delivered_by', $cobrador->id);
            })->where('status', 'completed')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->get();

            $overdueCredits = $activeCredits->filter(function ($credit) {
                $expectedInstallments = $credit->getExpectedInstallments();
                $completedInstallments = $credit->getCompletedInstallmentsCount();
                return $completedInstallments < $expectedInstallments;
            });

            $avgDaysToComplete = $this->calculateAvgDaysToComplete($completedCredits);

            $data[] = [
                'cobrador_id' => $cobrador->id,
                'cobrador_name' => $cobrador->name,
                'credits_delivered' => $creditsDelivered->count(),
                'payments_collected' => $paymentsCollected->count(),
                'total_amount_collected' => (float) round($paymentsCollected->sum('amount'), 2),
                'total_amount_collected_formatted' => 'Bs ' . number_format($paymentsCollected->sum('amount'), 2),
                'active_credits' => $activeCredits->count(),
                'completed_credits' => $completedCredits->count(),
                'overdue_credits' => $overdueCredits->count(),
                'overdue_rate' => $activeCredits->count() > 0
                    ? round(($overdueCredits->count() / $activeCredits->count()) * 100, 2)
                    : 0,
                'avg_days_to_complete' => round($avgDaysToComplete, 2),
                'collection_rate' => $creditsDelivered->count() > 0
                    ? round(($paymentsCollected->count() / $creditsDelivered->count()) * 100, 2)
                    : 0,
                '_model' => $cobrador,
            ];
        }

        return $data;
    }

    private function calculateAvgDaysToComplete(Collection $credits): float
    {
        if ($credits->count() === 0) return 0;

        $totalDays = $credits->sum(function ($credit) {
            if ($credit->start_date && $credit->updated_at) {
                return $credit->start_date->diffInDays($credit->updated_at);
            }
            return 0;
        });

        return $credits->count() > 0 ? $totalDays / $credits->count() : 0;
    }

    private function calculateSummary(array $data): array
    {
        $performance = collect($data);

        return [
            'total_cobradores' => $performance->count(),
            'total_credits_delivered' => (int) $performance->sum('credits_delivered'),
            'total_payments_collected' => (int) $performance->sum('payments_collected'),
            'total_amount_collected' => (float) round($performance->sum('total_amount_collected'), 2),
            'total_amount_collected_formatted' => 'Bs ' . number_format($performance->sum('total_amount_collected'), 2),
            'avg_collection_rate' => round($performance->avg('collection_rate'), 2),
            'avg_overdue_rate' => round($performance->avg('overdue_rate'), 2),
            'top_collector' => $performance->sortByDesc('total_amount_collected')->first(),
        ];
    }
}
