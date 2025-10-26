<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expected = $this->initial_amount + $this->collected_amount - $this->lent_amount;
        $difference = $this->final_amount - $expected;

        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'date_formatted' => $this->date->format('d/m/Y'),
            'cobrador_name' => $this->cobrador?->name ?? 'N/A',
            'initial_amount' => (float) $this->initial_amount,
            'collected_amount' => (float) $this->collected_amount,
            'lent_amount' => (float) $this->lent_amount,
            'final_amount' => (float) $this->final_amount,
            'expected_amount' => (float) $expected,
            'difference' => (float) $difference,
            'has_discrepancy' => abs($difference) > 0.01,
            'status' => $this->status,
            'credits_delivered' => $this->credits->count(),
        ];
    }
}
