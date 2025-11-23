<?php

namespace App\Events;

use App\Models\CashBalance;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashBalanceAutoClosed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CashBalance $cashBalance;

    public User $cobrador;

    public ?User $manager;

    /**
     * Create a new event instance.
     */
    public function __construct(CashBalance $cashBalance, User $cobrador, ?User $manager = null)
    {
        $this->cashBalance = $cashBalance;
        $this->cobrador = $cobrador;
        $this->manager = $manager;
    }
}
