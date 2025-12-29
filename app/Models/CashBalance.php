<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBalance extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'cobrador_id',
        'date',
        'initial_amount',
        'collected_amount',
        'lent_amount',
        'final_amount',
        'status',
        'auto_closed_at',
        'manually_closed_at',
        'closed_by',
        'closure_notes',
        'requires_reconciliation',
        'has_pending_previous_boxes',
        'pending_boxes_info',
    ];

    protected $casts = [
        'date' => 'date',
        'initial_amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'lent_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'status' => 'string',
        'auto_closed_at' => 'datetime',
        'manually_closed_at' => 'datetime',
        'requires_reconciliation' => 'boolean',
        'has_pending_previous_boxes' => 'boolean',
        'pending_boxes_info' => 'array',
    ];

    /**
     * Get the cobrador that owns this cash balance.
     */
    public function cobrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cobrador_id');
    }

    /**
     * Get the credits delivered using this cash balance.
     */
    public function credits()
    {
        return $this->hasMany(Credit::class, 'cash_balance_id');
    }

    /**
     * Get the user who closed this cash balance manually.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Check if this cash balance was auto-closed.
     */
    public function wasAutoClosed(): bool
    {
        return $this->auto_closed_at !== null;
    }

    /**
     * Check if this cash balance was manually closed.
     */
    public function wasManuallyClosed(): bool
    {
        return $this->manually_closed_at !== null;
    }

    /**
     * Get closure type (auto, manual, or open).
     */
    public function getClosureType(): string
    {
        if ($this->status === 'open') {
            return 'open';
        }

        if ($this->wasAutoClosed()) {
            return 'auto';
        }

        if ($this->wasManuallyClosed()) {
            return 'manual';
        }

        return 'closed'; // Closed before this feature was implemented
    }

    /**
     * Auto-close this cash balance.
     */
    public function autoClose(?string $notes = null): bool
    {
        if ($this->status === 'closed') {
            return false;
        }

        $this->update([
            'status' => 'closed',
            'auto_closed_at' => now(),
            'closure_notes' => $notes ?? 'Cierre automático nocturno del sistema',
        ]);

        return true;
    }

    /**
     * Manually close this cash balance.
     */
    public function manualClose(int $userId, ?string $notes = null): bool
    {
        if ($this->status === 'closed') {
            return false;
        }

        $this->update([
            'status' => 'closed',
            'manually_closed_at' => now(),
            'closed_by' => $userId,
            'closure_notes' => $notes,
        ]);

        return true;
    }

    /**
     * Scope: Get open cash balances.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope: Get auto-closed cash balances.
     */
    public function scopeAutoClosed($query)
    {
        return $query->whereNotNull('auto_closed_at');
    }

    /**
     * Scope: Get manually closed cash balances.
     */
    public function scopeManuallyClosed($query)
    {
        return $query->whereNotNull('manually_closed_at');
    }

    /**
     * Scope: Get cash balances requiring reconciliation.
     */
    public function scopeRequiringReconciliation($query)
    {
        return $query->where('requires_reconciliation', true);
    }
}
