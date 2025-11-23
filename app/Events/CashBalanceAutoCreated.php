<?php

namespace App\Events;

use App\Models\CashBalance;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashBalanceAutoCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CashBalance $cashBalance;

    public User $cobrador;

    public ?User $manager;

    public string $reason; // 'payment' or 'credit_delivery'

    /**
     * Create a new event instance.
     */
    public function __construct(CashBalance $cashBalance, User $cobrador, ?User $manager = null, string $reason = 'payment')
    {
        $this->cashBalance = $cashBalance;
        $this->cobrador = $cobrador;
        $this->manager = $manager;
        $this->reason = $reason;
    }
}
