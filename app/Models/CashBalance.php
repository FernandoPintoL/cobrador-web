<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'cobrador_id',
        'date',
        'initial_amount',
        'collected_amount',
        'lent_amount',
        'final_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'initial_amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'lent_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    /**
     * Get the cobrador that owns this cash balance.
     */
    public function cobrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cobrador_id');
    }
} 