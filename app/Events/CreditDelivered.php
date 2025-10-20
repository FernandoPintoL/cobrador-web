<?php

namespace App\Events;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditDelivered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Credit $credit;

    public User $manager;

    public User $cobrador;

    /**
     * Create a new event instance.
     */
    public function __construct(Credit $credit, User $manager, User $cobrador)
    {
        $this->credit = $credit;
        $this->manager = $manager;
        $this->cobrador = $cobrador;
    }
}
