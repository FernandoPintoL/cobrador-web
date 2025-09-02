<?php

namespace App\Events;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic notification broadcasting event used across the app to push
 * database-stored notifications to a specific user's private channel.
 *
 * Broadcast name: test.notification
 */
class TestNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification, public ?User $user = null)
    {
        // Ensure relationships that could be useful are loaded lazily when required.
        $this->notification->refresh();
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('notifications')];

        if ($this->user) {
            $channels[] = new PrivateChannel('user.'.$this->user->id);
        } elseif ($this->notification->user_id) {
            $channels[] = new PrivateChannel('user.'.$this->notification->user_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->id,
                'user_id' => $this->notification->user_id,
                'title' => $this->notification->title ?? null,
                'message' => $this->notification->message,
                'type' => $this->notification->type,
                'payment_id' => $this->notification->payment_id ?? null,
                'data' => $this->notification->data ?? null,
                'status' => $this->notification->status ?? null,
                'read_at' => $this->notification->read_at,
                'created_at' => optional($this->notification->created_at)->toISOString(),
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'test.notification';
    }
}
