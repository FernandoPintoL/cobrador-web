<?php

namespace App\Events;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditRequiresAttention implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $credit;
    public $cobrador;
    public $manager;
    public $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Credit $credit, string $reason = 'overdue')
    {
        $this->credit = $credit->load(['client']);
        $this->reason = $reason;

        // Obtener el cobrador asignado al cliente del crédito
        if ($this->credit->client && $this->credit->client->assigned_cobrador_id) {
            $this->cobrador = User::find($this->credit->client->assigned_cobrador_id);

            // Obtener el manager del cobrador
            if ($this->cobrador && $this->cobrador->assigned_manager_id) {
                $this->manager = User::find($this->cobrador->assigned_manager_id);
            }
        }
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('credits-attention'),
        ];

        // Canal del cobrador
        if ($this->cobrador) {
            $channels[] = new PrivateChannel('user.' . $this->cobrador->id);
        }

        // Canal del manager
        if ($this->manager) {
            $channels[] = new PrivateChannel('user.' . $this->manager->id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'credit' => [
                'id' => $this->credit->id,
                'amount' => $this->credit->amount,
                'balance' => $this->credit->balance,
                'end_date' => $this->credit->end_date,
                'status' => $this->credit->status,
                'client' => [
                    'id' => $this->credit->client->id,
                    'name' => $this->credit->client->name,
                    'phone' => $this->credit->client->phone,
                ],
            ],
            'cobrador' => $this->cobrador ? [
                'id' => $this->cobrador->id,
                'name' => $this->cobrador->name,
            ] : null,
            'manager' => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ] : null,
            'reason' => $this->reason,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs()
    {
        return 'credit.requires.attention';
    }
}
