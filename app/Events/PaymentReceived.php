<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payment;

    public $cobrador;

    public $manager;

    public $client;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment->load(['credit.client', 'receivedBy']);
        $this->client = $this->payment->credit->client;
        $this->cobrador = $this->payment->receivedBy;

        // Obtener el manager del cobrador
        if ($this->cobrador && $this->cobrador->hasRole('cobrador')) {
            $this->manager = User::find($this->cobrador->assigned_manager_id);
        }
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('payments'),
        ];

        // Canal del cobrador que recibió el pago
        if ($this->cobrador) {
            $channels[] = new PrivateChannel('user.'.$this->cobrador->id);
        }

        // Canal del manager del cobrador
        if ($this->manager) {
            $channels[] = new PrivateChannel('user.'.$this->manager->id);
        }

        // Canal del cliente
        if ($this->client) {
            $channels[] = new PrivateChannel('user.'.$this->client->id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'payment' => [
                'id' => $this->payment->id,
                'amount' => $this->payment->amount,
                'payment_date' => $this->payment->payment_date,
                'payment_method' => $this->payment->payment_method,
                'credit_id' => $this->payment->credit_id,
                'received_by' => $this->payment->received_by,
            ],
            'client' => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
                'phone' => $this->client->phone,
            ] : null,
            'cobrador' => $this->cobrador ? [
                'id' => $this->cobrador->id,
                'name' => $this->cobrador->name,
                'email' => $this->cobrador->email,
                'phone' => $this->cobrador->phone,
            ] : null,
            'manager' => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
            ] : null,
            'timestamp' => now()->toISOString(),
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
