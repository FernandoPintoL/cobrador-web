<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CreditResource - Recurso JSON para Créditos
 *
 * ✅ CONSISTENCIA API
 * Asegura que la API JSON devuelva la misma estructura que:
 * - Blade HTML templates
 * - Excel exports
 * - DTOs del servicio
 */
class CreditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->client?->name ?? 'N/A',
            'amount' => (float) $this->amount,
            'amount_formatted' => 'Bs ' . number_format($this->amount, 2),
            'balance' => (float) $this->balance,
            'balance_formatted' => 'Bs ' . number_format($this->balance, 2),
            'status' => $this->status,
            'interest_rate' => (float) $this->interest_rate,
            'created_by_id' => $this->created_by,
            'created_by_name' => $this->createdBy?->name ?? 'N/A',
            'delivered_by_id' => $this->delivered_by,
            'delivered_by_name' => $this->deliveredBy?->name ?? 'N/A',
            'total_installments' => $this->total_installments,
            'pending_installments' => $this->getPendingInstallments(),
            'payments_count' => $this->payments->count(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_formatted' => $this->created_at->format('d/m/Y'),
        ];
    }
}
