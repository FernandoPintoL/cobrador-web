<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'cobrador_id',
        'credit_id',
        'amount',
        'payment_date',
        'payment_method',
        'latitude',
        'longitude',
        'status',
        'transaction_id',
        'installment_number',
        'received_by',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'amount' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
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
     * Get the location as an array with latitude and longitude.
     */
    public function getLocationAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ];
        }
        return null;
    }

    /**
     * Set the location from an array with latitude and longitude.
     */
    public function setLocationAttribute($value): void
    {
        if (is_array($value) && isset($value['latitude']) && isset($value['longitude'])) {
            $this->attributes['latitude'] = $value['latitude'];
            $this->attributes['longitude'] = $value['longitude'];
        }
    }

    /**
     * Check if the payment has a location set.
     */
    public function hasLocation(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
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
                    $credit->save();
                }

                // Disparar evento de pago recibido
                $cobrador = $payment->cobrador;
                if ($cobrador) {
                    event(new \App\Events\PaymentReceived($payment, $cobrador));
                }
            } catch (\Exception $e) {
                \Log::error('Error processing payment creation events', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }
        });

        // Evento cuando se actualiza un pago
        static::updated(function ($payment) {
            try {
                // Si el monto cambió, actualizar el balance del crédito
                if ($payment->wasChanged('amount')) {
                    $credit = $payment->credit;
                    if ($credit) {
                        $oldAmount = $payment->getOriginal('amount');
                        $newAmount = $payment->amount;
                        $difference = $newAmount - $oldAmount;

                        $credit->balance = $credit->balance - $difference;
                        $credit->save();
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error processing payment update events', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }
        });
    }
}
