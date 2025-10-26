<?php

namespace App\Services;

use App\DTOs\WaitingListDTO;
use App\Models\Credit;

class WaitingListService
{
    public function generateReport(array $filters, object $currentUser): WaitingListDTO
    {
        $query = Credit::with(['client', 'createdBy', 'deliveredBy'])
            ->where('status', 'pending_delivery')
            ->orderBy('created_at', 'desc');

        if ($currentUser->hasRole('cobrador')) {
            $query->where('created_by', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $credits = $query->get();
        $data = $credits->map(function ($credit) {
            return [
                'id' => $credit->id,
                'client_name' => $credit->client?->name ?? 'N/A',
                'amount' => (float) $credit->amount,
                'amount_formatted' => 'Bs ' . number_format($credit->amount, 2),
                'status' => $credit->status,
                'created_at' => $credit->created_at->format('Y-m-d'),
                'created_at_formatted' => $credit->created_at->format('d/m/Y'),
                'days_waiting' => $credit->created_at->diffInDays(now()),
                'created_by' => $credit->createdBy?->name ?? 'N/A',
                '_model' => $credit,
            ];
        });

        $summary = [
            'total_waiting' => $credits->count(),
            'total_amount_waiting' => (float) round($credits->sum('amount'), 2),
            'total_amount_waiting_formatted' => 'Bs ' . number_format($credits->sum('amount'), 2),
            'average_days_waiting' => round($data->avg('days_waiting'), 2),
        ];

        return new WaitingListDTO(
            data: $data,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }
}
