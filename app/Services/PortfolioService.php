<?php

namespace App\Services;

use App\DTOs\PortfolioDTO;
use App\Models\Credit;

class PortfolioService
{
    public function generateReport(array $filters, object $currentUser): PortfolioDTO
    {
        $query = Credit::with(['client', 'createdBy', 'deliveredBy', 'payments'])
            ->where('status', 'active');

        if ($currentUser->hasRole('cobrador')) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('created_by', $currentUser->id)
                    ->orWhere('delivered_by', $currentUser->id);
            });
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $credits = $query->orderBy('created_at', 'desc')->get();

        $data = $credits->map(function ($credit) {
            $expectedInstallments = $credit->getExpectedInstallments();
            $completedInstallments = $credit->getCompletedInstallmentsCount();
            $completionRate = $expectedInstallments > 0
                ? round(($completedInstallments / $expectedInstallments) * 100, 2)
                : 0;

            return [
                'id' => $credit->id,
                'client_name' => $credit->client?->name ?? 'N/A',
                'amount' => (float) $credit->amount,
                'amount_formatted' => 'Bs ' . number_format($credit->amount, 2),
                'balance' => (float) $credit->balance,
                'balance_formatted' => 'Bs ' . number_format($credit->balance, 2),
                'total_installments' => $expectedInstallments,
                'completed_installments' => $completedInstallments,
                'pending_installments' => $credit->getPendingInstallments(),
                'completion_rate' => $completionRate,
                'interest_rate' => (float) $credit->interest_rate,
                'status' => $credit->status,
                '_model' => $credit,
            ];
        });

        $summary = [
            'total_active_credits' => $credits->count(),
            'total_portfolio_amount' => (float) round($credits->sum('amount'), 2),
            'total_portfolio_amount_formatted' => 'Bs ' . number_format($credits->sum('amount'), 2),
            'total_pending_balance' => (float) round($credits->sum('balance'), 2),
            'total_pending_balance_formatted' => 'Bs ' . number_format($credits->sum('balance'), 2),
            'portfolio_completion_rate' => round($data->avg('completion_rate'), 2),
            'at_risk_count' => $data->filter(fn ($c) => $c['completion_rate'] < 50)->count(),
        ];

        return new PortfolioDTO(
            data: $data,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }
}
