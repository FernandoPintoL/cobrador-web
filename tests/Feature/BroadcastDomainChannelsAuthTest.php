<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('authorizes private domain channel payments with Sanctum Bearer', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
        'Accept' => 'application/json',
    ])->post('/broadcasting/auth', [
        'channel_name' => 'private-payments',
        'socket_id' => '1234.5678',
    ]);

    $response->assertSuccessful();
});
