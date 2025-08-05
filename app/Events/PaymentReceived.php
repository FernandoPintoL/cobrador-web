<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payment;
    public $cobrador;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, User $cobrador)
    {
        $this->payment = $payment;
        $this->cobrador = $cobrador;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('cobrador.' . $this->cobrador->id),
            new Channel('payments.received')
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'credit_id' => $this->payment->credit_id,
            'client_id' => $this->payment->credit->client_id,
            'client_name' => $this->payment->credit->client->name,
            'amount' => $this->payment->amount,
            'payment_date' => $this->payment->payment_date->format('Y-m-d'),
            'payment_method' => $this->payment->payment_method,
            'cobrador_id' => $this->cobrador->id,
            'remaining_balance' => $this->payment->credit->total_amount - $this->payment->credit->payments()->sum('amount'),
            'installments_paid' => $this->payment->credit->payments()->count(),
            'type' => 'payment_received',
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.received';
    }
}
