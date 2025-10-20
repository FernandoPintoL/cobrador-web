<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'cobrador_id',
        'credit_id',
        'amount',
        'accumulated_amount',
        'payment_date',
        'payment_method',
        'latitude',
        'longitude',
        'status',
        'transaction_id',
        'installment_number',
        'received_by',
        'cash_balance_id',
    ];

    protected $casts = [
        'payment_date'       => 'datetime',
        'amount'             => 'decimal:2',
        'accumulated_amount' => 'decimal:2',
        'cash_balance_id'    => 'integer',
        'latitude'           => 'decimal:8',
        'longitude'          => 'decimal:8',
        'installment_number' => 'integer',
    ];

    /**
     * Get the client that made this payment.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the cobrador that collected this payment.
     */
    public function cobrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cobrador_id');
    }

    /**
     * Get the credit that this payment belongs to.
     */
    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    /**
     * Get the user who received this payment.
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Get the notifications for this payment.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Calculate principal portion of this payment.
     * Uses credit's total installments and amount to estimate principal per installment.
     * Returns 0 when calculation is not possible.
     */
    public function getPrincipalPortionAttribute(): float
    {
        if (! $this->credit) {
            return 0.0;
        }

        $credit            = $this->credit;
        $totalInstallments = $credit->calculateTotalInstallments();
        if ($totalInstallments <= 0) {
            return 0.0;
        }

        $installmentAmount       = $credit->installment_amount ?? $credit->calculateInstallmentAmount();
        $principalPerInstallment = $credit->amount ? ($credit->amount / $totalInstallments) : 0.0;

        // Proporción del pago correspondiente a principal según la composición de la cuota
        $ratio = $installmentAmount > 0 ? ($principalPerInstallment / $installmentAmount) : 0;

        return (float) round($this->amount * $ratio, 2);
    }

    /**
     * Interest portion: remainder of the payment after principal portion.
     */
    public function getInterestPortionAttribute(): float
    {
        return (float) round($this->amount - $this->principal_portion, 2);
    }

    /**
     * Amount remaining to finish the installment this payment is paying.
     * If installment_number is not set (0), returns null.
     */
    public function getRemainingForInstallmentAttribute(): ?float
    {
        if (! $this->credit) {
            return null;
        }

        $installmentNumber = (int) $this->installment_number;
        if ($installmentNumber <= 0) {
            return null;
        }

        $credit            = $this->credit;
        $installmentAmount = $credit->installment_amount ?? $credit->calculateInstallmentAmount();

        // Sum all payments that are associated to this credit and installment number
        $paidForInstallment = $credit->payments()->where('installment_number', $installmentNumber)->sum('amount');

        $remaining = $installmentAmount - $paidForInstallment;
        if ($remaining < 0) {
            $remaining = 0;
        }

        return (float) round($remaining, 2);
    }

    /**
     * Check if the payment has a valid location set.
     */
    public function hasValidLocation(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }

    /**
     * Model events
     */
    protected static function booted()
    {
        // Evento cuando se crea un nuevo pago
        static::created(function ($payment) {
            try {
                // Actualizar el balance del crédito
                $credit = $payment->credit;
                if ($credit) {
                    $credit->balance = $credit->balance - $payment->amount;

                    // Actualizar el contador de cuotas pagadas si este pago completa una cuota
                    if ($payment->installment_number) {
                        $installmentAmount = $credit->installment_amount ?? $credit->calculateInstallmentAmount();

                        // Verificar si con este pago se completa la cuota
                        $totalPaid = $credit->payments()
                            ->where('installment_number', $payment->installment_number)
                            ->whereNotIn('status', ['cancelled', 'failed'])
                            ->sum('amount');

                        if ((float) $totalPaid >= (float) $installmentAmount) {
                            // Esta cuota está completa, incrementar contador
                            $credit->paid_installments = ($credit->paid_installments ?? 0) + 1;
                        }
                    }

                    // Actualizar el total pagado
                    if ($payment->status === 'completed') {
                        $credit->total_paid = ($credit->total_paid ?? 0) + $payment->amount;
                    }

                    $credit->save();
                }

                // Recalcular categoría del cliente según atrasos
                if ($payment->client) {
                    $payment->client->recalculateCategoryFromOverdues();
                }

                // Disparar evento de pago recibido
                $cobrador = $payment->cobrador;
                if ($cobrador) {
                    // event(new \App\Events\PaymentReceived($payment, $cobrador));
                }
            } catch (\Exception $e) {
                Log::error('Error processing payment creation events', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        });

        // Evento cuando se actualiza un pago
        static::updated(function ($payment) {
            try {
                // Si el monto cambió, actualizar el balance del crédito
                if ($payment->wasChanged('amount') || $payment->wasChanged('status')) {
                    $credit = $payment->credit;
                    if ($credit) {
                        $oldAmount  = $payment->getOriginal('amount');
                        $newAmount  = $payment->amount;
                        $difference = $newAmount - $oldAmount;

                        $credit->balance = $credit->balance - $difference;

                        // Actualizar el total pagado si el estado es completado
                        if ($payment->status === 'completed') {
                            if ($payment->wasChanged('status') && $payment->getOriginal('status') !== 'completed') {
                                // Cambió de otro estado a completado
                                $credit->total_paid = ($credit->total_paid ?? 0) + $newAmount;
                            } elseif ($payment->wasChanged('amount') && ! $payment->wasChanged('status')) {
                                // Solo cambió el monto, pero ya era completado
                                $credit->total_paid = ($credit->total_paid ?? 0) + $difference;
                            }
                        } else if ($payment->wasChanged('status') && $payment->getOriginal('status') === 'completed') {
                            // Cambió de completado a otro estado
                            $credit->total_paid = max(0, ($credit->total_paid ?? 0) - $oldAmount);
                        }

                        $credit->save();
                    }
                }

                // Recalcular categoría del cliente (por si se revertieron o ajustaron pagos)
                if ($payment->client) {
                    $payment->client->recalculateCategoryFromOverdues();
                }
            } catch (\Exception $e) {
                Log::error('Error processing payment update events', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        });

        // Evento cuando se elimina un pago
        static::deleted(function ($payment) {
            try {
                $credit = $payment->credit;
                if ($credit) {
                    // Si el pago eliminado estaba asociado a una cuota, verificar si esa cuota ahora queda incompleta
                    if ($payment->installment_number) {
                        $installmentAmount = $credit->installment_amount ?? $credit->calculateInstallmentAmount();

                        // Verificar el total pagado para esta cuota después de eliminar este pago
                        $totalPaid = $credit->payments()
                            ->where('installment_number', $payment->installment_number)
                            ->whereNotIn('status', ['cancelled', 'failed'])
                            ->sum('amount');

                        // Si el total pagado ahora es menor que el monto de la cuota, decrementar el contador de cuotas pagadas
                        if ((float) $totalPaid < (float) $installmentAmount && $credit->paid_installments > 0) {
                            $credit->paid_installments = $credit->paid_installments - 1;
                        }
                    }

                    // Actualizar el total pagado si el pago era completado
                    if ($payment->status === 'completed') {
                        $credit->total_paid = max(0, ($credit->total_paid ?? 0) - $payment->amount);
                    }

                    $credit->save();
                }

                // Recalcular categoría del cliente
                if ($payment->client) {
                    $payment->client->recalculateCategoryFromOverdues();
                }
            } catch (\Exception $e) {
                Log::error('Error processing payment deletion events', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        });
    }
}
