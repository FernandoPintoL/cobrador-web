<?php

namespace App\Services;

use App\DTOs\CommissionsDTO;
use App\Models\Payment;
use App\Models\User;

class CommissionsService
{
    public function generateReport(array $filters, object $currentUser): CommissionsDTO
    {
        $startDate = $filters['start_date'] ?? now()->subMonth()->startOfDay();
        $endDate = $filters['end_date'] ?? now()->endOfDay();

        $query = Payment::with(['cobrador', 'credit.client'])
            ->whereBetween('payment_date', [$startDate, $endDate]);

        if ($currentUser->hasRole('cobrador')) {
            $query->where('cobrador_id', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('cobrador', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $payments = $query->get();

        $commissionsData = $payments->groupBy('cobrador_id')->map(function ($group) {
            $cobrador = User::find($group->first()?->cobrador_id);
            $totalCollected = $group->sum('amount');
            $commissionRate = 0.05; // 5% default
            $commission = $totalCollected * $commissionRate;

            return [
                'cobrador_id' => $cobrador?->id,
                'cobrador_name' => $cobrador?->name ?? 'N/A',
                'payments_count' => $group->count(),
                'total_collected' => (float) round($totalCollected, 2),
                'total_collected_formatted' => 'Bs ' . number_format($totalCollected, 2),
                'commission_rate' => $commissionRate * 100,
                'commission_earned' => (float) round($commission, 2),
                'commission_earned_formatted' => 'Bs ' . number_format($commission, 2),
                '_model' => $cobrador,
            ];
        })->values();

        $summary = [
            'total_cobradores' => $commissionsData->count(),
            'total_collected' => (float) round($payments->sum('amount'), 2),
            'total_collected_formatted' => 'Bs ' . number_format($payments->sum('amount'), 2),
            'total_commissions' => (float) round($commissionsData->sum('commission_earned'), 2),
            'total_commissions_formatted' => 'Bs ' . number_format($commissionsData->sum('commission_earned'), 2),
            'average_commission' => (float) round($commissionsData->avg('commission_earned'), 2),
        ];

        return new CommissionsDTO(
            data: $commissionsData,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }
}
