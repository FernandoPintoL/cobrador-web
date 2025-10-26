<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * ✅ RECURSO CONSISTENTE CON BLADE
     *
     * Este resource mapea los campos del modelo Payment
     * para asegurar que JSON API devuelva lo MISMO que Blade
     *
     * Incluye:
     * - principal_portion (método cacheado)
     * - interest_portion (método cacheado)
     * - remaining_for_installment (método cacheado)
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'payment_date_formatted' => $this->payment_date->format('d/m/Y'),
            'cobrador_id' => $this->cobrador_id,
            'cobrador_name' => $this->cobrador?->name ?? 'N/A',
            'credit_id' => $this->credit_id,
            'client_id' => $this->credit?->client_id,
            'client_name' => $this->credit?->client?->name ?? 'N/A',
            'amount' => (float) $this->amount,
            'amount_formatted' => 'Bs ' . number_format($this->amount, 2),
            'status' => $this->status,

            /**
             * ✅ MÉTODOS CACHEADOS - Mismo que Blade
             * Estos valores se calculan UNA SOLA VEZ por payment
             * en la serialización, gracias al caché en memoria
             */
            'principal_portion' => round($this->getPrincipalPortion(), 2),
            'principal_portion_formatted' => 'Bs ' . number_format($this->getPrincipalPortion(), 2),

            'interest_portion' => round($this->getInterestPortion(), 2),
            'interest_portion_formatted' => 'Bs ' . number_format($this->getInterestPortion(), 2),

            'remaining_for_installment' => $this->getRemainingForInstallment(),
            'remaining_for_installment_formatted' => !is_null($this->getRemainingForInstallment())
                ? 'Bs ' . number_format($this->getRemainingForInstallment(), 2)
                : 'N/A',

            /**
             * Información del crédito asociado
             */
            'credit' => [
                'id' => $this->credit?->id,
                'installment_number' => $this->installment_number,
                'installment_number_display' => $this->installment_number > 0 ? $this->installment_number : 'N/A',
                'pending_installments' => $this->credit ? $this->credit->getPendingInstallments() : 'N/A',
                'balance' => (float) ($this->credit?->balance ?? 0),
                'balance_formatted' => 'Bs ' . number_format($this->credit?->balance ?? 0, 2),
            ],

            /**
             * Información adicional
             */
            'payment_method' => $this->payment_method ?? 'N/A',
            'accumulated_amount' => (float) $this->accumulated_amount,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_formatted' => $this->created_at->format('d/m/Y H:i'),
        ];
    }
}
