<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', function () {
                return $this->tenant->name;
            }),

            // Amount
            'amount' => $this->amount,
            'amount_formatted' => number_format($this->amount, 2) . ' Bs',

            // Period
            'period_start' => $this->period_start,
            'period_start_formatted' => $this->period_start->format('d/m/Y'),
            'period_end' => $this->period_end,
            'period_end_formatted' => $this->period_end->format('d/m/Y'),

            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Calculated fields
            'is_overdue' => $this->status === 'pending' && $this->period_end < now(),
            'days_overdue' => $this->getDaysOverdue(),
            'is_current_period' => $this->isCurrentPeriod(),

            // Timestamps
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_formatted' => $this->created_at->format('d/m/Y'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'updated_at_formatted' => $this->updated_at->format('d/m/Y'),
        ];
    }

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'paid' => 'Pagado',
            'overdue' => 'Vencido',
            'cancelled' => 'Cancelado',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get status color for UI.
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'paid' => 'green',
            'pending' => 'yellow',
            'overdue' => 'red',
            'cancelled' => 'gray',
            default => 'blue',
        };
    }

    /**
     * Get days overdue (0 if not overdue).
     */
    private function getDaysOverdue(): int
    {
        if ($this->status === 'pending' && $this->period_end < now()) {
            return now()->diffInDays($this->period_end);
        }
        if ($this->status === 'overdue') {
            return now()->diffInDays($this->period_end);
        }
        return 0;
    }

    /**
     * Check if this subscription is for the current period.
     */
    private function isCurrentPeriod(): bool
    {
        $now = now();
        return $this->period_start <= $now && $this->period_end >= $now;
    }
}
