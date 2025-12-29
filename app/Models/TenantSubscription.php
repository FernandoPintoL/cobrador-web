<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'amount',
        'period_start',
        'period_end',
        'status',
        'paid_at',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'date',
    ];

    /**
     * Relación: tenant al que pertenece
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Marcar como pagada
     */
    public function markAsPaid(?string $paymentMethod = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
        ]);
    }

    /**
     * Verificar si está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verificar si está pagada
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Verificar si está vencida
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' ||
               ($this->status === 'pending' && $this->period_end->isPast());
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeForPeriod($query, $year, $month)
    {
        return $query->whereYear('period_start', $year)
                     ->whereMonth('period_start', $month);
    }
}
