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

class CreditWaitingListUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $credit;
    public $action; // 'approved', 'rejected', 'delivered', 'rescheduled'
    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct(Credit $credit, string $action, User $user)
    {
        $this->credit = $credit;
        $this->action = $action;
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('waiting-list'),
            new PrivateChannel('user.' . $this->credit->client_id),
            new PrivateChannel('user.' . $this->credit->created_by),
            new PrivateChannel('cobrador.' . $this->credit->created_by),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'credit_id' => $this->credit->id,
            'client_id' => $this->credit->client_id,
            'client_name' => $this->credit->client->name,
            'action' => $this->action,
            'amount' => $this->credit->amount,
            'total_amount' => $this->credit->total_amount,
            'status' => $this->credit->status,
            'scheduled_delivery_date' => $this->credit->scheduled_delivery_date,
            'delivery_status' => $this->credit->getDeliveryStatusInfo(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'credit.waiting.list.update';
    }
}
