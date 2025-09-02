<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

it('allows updating notification type to cobrador_payment_received', function () {
    // Create a user to own the notification
    $user = User::factory()->create();

    // Seed an initial notification with a valid type different from the target
    $notification = Notification::create([
        'user_id' => $user->id,
        'payment_id' => null,
        'type' => 'payment_due',
        'message' => 'Initial message',
        'status' => 'unread',
    ]);

    // Authenticate as the same user (adjust guard if needed)
    actingAs($user);

    $payload = [
        'user_id' => $user->id,
        'payment_id' => null,
        'type' => 'cobrador_payment_received',
        'message' => 'Updated message',
        // status is optional in validation; omit to keep current
    ];

    $response = putJson(route('api.notifications.update', $notification), $payload);

    $response->assertSuccessful();

    // Refresh and assert the type changed
    $notification->refresh();

    expect($notification->type)->toBe('cobrador_payment_received')
        ->and($notification->message)->toBe('Updated message');
});
