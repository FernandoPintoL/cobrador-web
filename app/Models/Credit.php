<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Credit extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'created_by',
        'amount',
        'balance',
        'frequency',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * Get the client that owns this credit.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the cobrador who created this credit.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the payments for this credit.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the remaining installments for this credit.
     */
    public function getRemainingInstallments(): int
    {
        $totalInstallments = $this->calculateTotalInstallments();
        $paidInstallments = $this->payments()->where('status', 'completed')->count();
        
        return max(0, $totalInstallments - $paidInstallments);
    }

    /**
     * Calculate the total number of installments based on frequency and dates.
     */
    private function calculateTotalInstallments(): int
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);
        
        switch ($this->frequency) {
            case 'daily':
                return $startDate->diffInDays($endDate) + 1;
            case 'weekly':
                return $startDate->diffInWeeks($endDate) + 1;
            case 'biweekly':
                return $startDate->diffInWeeks($endDate) / 2 + 1;
            case 'monthly':
                return $startDate->diffInMonths($endDate) + 1;
            default:
                return 0;
        }
    }
} 