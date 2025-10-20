<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;

    public User $cobrador;

    public ?User $manager;

    public ?User $client;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, User $cobrador, ?User $manager = null, ?User $client = null)
    {
        $this->payment = $payment;
        $this->cobrador = $cobrador;
        $this->manager = $manager;
        $this->client = $client;
    }
}
