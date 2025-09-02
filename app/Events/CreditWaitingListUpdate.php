<?php

namespace App\Events;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditWaitingListUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $credit;

    public $action; // 'created', 'approved', 'rejected', 'delivered', 'rescheduled'

    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct(Credit $credit, string $action, User $user)
    {
        $this->credit = $credit->load(['client', 'createdBy']);
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
            new PrivateChannel('user.'.$this->credit->client_id),
            new PrivateChannel('user.'.$this->credit->created_by),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'credit' => [
                'id' => $this->credit->id,
                'client_id' => $this->credit->client_id,
                'amount' => $this->credit->amount,
                'total_amount' => $this->credit->total_amount,
                'interest_rate' => $this->credit->interest_rate,
                'status' => $this->credit->status,
                'scheduled_delivery_date' => $this->credit->scheduled_delivery_date,
                'created_at' => $this->credit->created_at,
                'client' => [
                    'id' => $this->credit->client->id,
                    'name' => $this->credit->client->name,
                    'email' => $this->credit->client->email,
                    'phone' => $this->credit->client->phone,
                ],
                'created_by' => [
                    'id' => $this->credit->createdBy->id,
                    'name' => $this->credit->createdBy->name,
                ],
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->getRoleNames()->first(),
            ],
            'timestamp' => now()->toISOString(),
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
