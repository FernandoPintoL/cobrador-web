<?php

namespace App\Services;

use App\DTOs\OverdueReportDTO;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Support\Collection;

class OverdueReportService
{
    public function generateReport(array $filters, object $currentUser): OverdueReportDTO
    {
        $query = $this->buildQuery($filters, $currentUser);
        $credits = $query->orderBy('start_date', 'asc')->get();

        $overdueCredits = $this->filterOverdueCredits($credits, $filters);
        $transformedCredits = $this->transformCredits($overdueCredits);
        $summary = $this->calculateSummary($overdueCredits);

        return new OverdueReportDTO(
            credits: $transformedCredits,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }

    private function buildQuery(array $filters, object $currentUser)
    {
        $query = Credit::with(['client', 'createdBy', 'deliveredBy', 'payments'])
            ->where('status', 'active')
            ->where('balance', '>', 0);

        if (!empty($filters['cobrador_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('created_by', $filters['cobrador_id'])
                    ->orWhere('delivered_by', $filters['cobrador_id']);
            });
        }

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['client_category'])) {
            $query->whereHas('client', function ($q) use ($filters) {
                $q->where('client_category', $filters['client_category']);
            });
        }

        if ($currentUser->hasRole('cobrador')) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('created_by', $currentUser->id)
                    ->orWhere('delivered_by', $currentUser->id);
            });
        } elseif ($currentUser->hasRole('manager')) {
            $directClientIds = User::whereHas('roles', function ($q) {
                $q->where('name', 'client');
            })->where('assigned_manager_id', $currentUser->id)->pluck('id')->toArray();

            $cobradorIds = User::role('cobrador')
                ->where('assigned_manager_id', $currentUser->id)
                ->pluck('id')->toArray();

            $cobradorClientIds = User::whereHas('roles', function ($q) {
                $q->where('name', 'client');
            })->whereIn('assigned_cobrador_id', $cobradorIds)->pluck('id')->toArray();

            $allClientIds = array_unique(array_merge($directClientIds, $cobradorClientIds));
            $query->whereIn('client_id', $allClientIds);
        }

        return $query;
    }

    private function filterOverdueCredits(Collection $credits, array $filters): Collection
    {
        return $credits->filter(function ($credit) use ($filters) {
            $expectedInstallments = $credit->getExpectedInstallments();
            $completedInstallments = $credit->getCompletedInstallmentsCount();
            $isOverdue = $completedInstallments < $expectedInstallments;

            if (!$isOverdue) {
                return false;
            }

            $daysOverdue = $this->calculateDaysOverdue($credit);
            $overdueAmount = $credit->getOverdueAmount();

            if (!empty($filters['min_days_overdue']) && $daysOverdue < $filters['min_days_overdue']) {
                return false;
            }

            if (!empty($filters['max_days_overdue']) && $daysOverdue > $filters['max_days_overdue']) {
                return false;
            }

            if (!empty($filters['min_overdue_amount']) && $overdueAmount < $filters['min_overdue_amount']) {
                return false;
            }

            $credit->days_overdue = $daysOverdue;
            $credit->overdue_amount = round($overdueAmount, 2);
            $credit->overdue_installments = $expectedInstallments - $completedInstallments;
            $credit->completion_rate = $expectedInstallments > 0
                ? round(($completedInstallments / $expectedInstallments) * 100, 2)
                : 0;

            return true;
        })->sortByDesc('days_overdue')->values();
    }

    private function calculateDaysOverdue(Credit $credit): int
    {
        $lastPaymentDate = $credit->payments->max('payment_date');
        $startDate = $lastPaymentDate ?? $credit->start_date;

        return now()->diffInDays($startDate);
    }

    private function transformCredits(Collection $credits): Collection
    {
        return $credits->map(function ($credit) {
            return [
                'id' => $credit->id,
                'client_id' => $credit->client_id,
                'client_name' => $credit->client?->name ?? 'N/A',
                'client_category' => $credit->client?->client_category ?? 'N/A',
                'amount' => (float) $credit->amount,
                'amount_formatted' => 'Bs ' . number_format($credit->amount, 2),
                'balance' => (float) $credit->balance,
                'balance_formatted' => 'Bs ' . number_format($credit->balance, 2),
                'start_date' => $credit->start_date->format('Y-m-d'),
                'start_date_formatted' => $credit->start_date->format('d/m/Y'),
                'days_overdue' => $credit->days_overdue,
                'overdue_amount' => (float) $credit->overdue_amount,
                'overdue_amount_formatted' => 'Bs ' . number_format($credit->overdue_amount, 2),
                'overdue_installments' => $credit->overdue_installments,
                'completion_rate' => $credit->completion_rate,
                'cobrador_name' => ($credit->deliveredBy ?? $credit->createdBy)?->name ?? 'Sin asignar',
                'severity' => $this->getSeverity($credit->days_overdue),
                '_model' => $credit,
            ];
        });
    }

    private function calculateSummary(Collection $credits): array
    {
        return [
            'total_overdue_credits' => $credits->count(),
            'total_overdue_amount' => (float) round($credits->sum('overdue_amount'), 2),
            'total_overdue_amount_formatted' => 'Bs ' . number_format($credits->sum('overdue_amount'), 2),
            'total_balance_overdue' => (float) round($credits->sum('balance'), 2),
            'total_balance_overdue_formatted' => 'Bs ' . number_format($credits->sum('balance'), 2),
            'average_days_overdue' => (float) round($credits->avg('days_overdue'), 2),
            'max_days_overdue' => (int) ($credits->max('days_overdue') ?? 0),
            'min_days_overdue' => (int) ($credits->min('days_overdue') ?? 0),
            'by_severity' => [
                'light' => $credits->filter(fn ($c) => $c->days_overdue <= 7)->count(),
                'moderate' => $credits->filter(fn ($c) => $c->days_overdue > 7 && $c->days_overdue <= 30)->count(),
                'severe' => $credits->filter(fn ($c) => $c->days_overdue > 30)->count(),
            ],
        ];
    }

    private function getSeverity(int $days): string
    {
        if ($days <= 7) return 'light';
        if ($days <= 30) return 'moderate';
        return 'severe';
    }
}
