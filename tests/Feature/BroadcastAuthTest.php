<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('authorizes private user channel user.{id} with Sanctum Bearer', function () {
    $user = User::factory()->create();

    // Issue a Sanctum personal access token and authenticate via Bearer
    Sanctum::actingAs($user, ['*']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
        'Accept' => 'application/json',
    ])->post('/broadcasting/auth', [
        'channel_name' => 'private-user.'.$user->id,
        'socket_id' => '1234.1234',
    ]);

    $response->assertSuccessful();
});
