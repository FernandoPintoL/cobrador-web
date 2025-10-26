<?php

namespace App\Services;

use App\DTOs\DailyActivityDTO;
use App\Models\Payment;

class DailyActivityService
{
    public function generateReport(array $filters, object $currentUser): DailyActivityDTO
    {
        $date = $filters['date'] ?? now()->format('Y-m-d');

        $query = Payment::with(['cobrador', 'credit.client'])
            ->whereDate('payment_date', $date);

        if ($currentUser->hasRole('cobrador')) {
            $query->where('cobrador_id', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('cobrador', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $payments = $query->orderBy('payment_date', 'desc')->get();

        $data = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'time' => $payment->payment_date->format('H:i'),
                'cobrador' => $payment->cobrador?->name ?? 'N/A',
                'client' => $payment->credit?->client?->name ?? 'N/A',
                'amount' => (float) $payment->amount,
                'amount_formatted' => 'Bs ' . number_format($payment->amount, 2),
                'payment_method' => $payment->payment_method ?? 'N/A',
                'status' => $payment->status,
                '_model' => $payment,
            ];
        });

        $summary = [
            'total_payments' => $payments->count(),
            'total_amount' => (float) round($payments->sum('amount'), 2),
            'total_amount_formatted' => 'Bs ' . number_format($payments->sum('amount'), 2),
            'by_cobrador' => $payments->groupBy('cobrador_id')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => round($group->sum('amount'), 2),
                ];
            })->toArray(),
        ];

        return new DailyActivityDTO(
            data: $data,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }
}
