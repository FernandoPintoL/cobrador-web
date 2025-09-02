<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uppercases name/address/ci and lowercases email on Auth register', function () {
    $payload = [
        'name' => 'fernando pérez',
        'email' => 'Fernando.Perez@Example.COM',
        'password' => 'secretpass',
        'password_confirmation' => 'secretpass',
        'phone' => '70000001',
        'address' => 'av. siempre viva 742',
        'ci' => 'lp123-45',
    ];

    $resp = $this->postJson('/api/register', $payload);
    $resp->assertSuccessful();

    $userArr = $resp->json('data.user');
    expect($userArr['name'])->toBe('FERNANDO PÉREZ')
        ->and($userArr['email'])->toBe('fernando.perez@example.com')
        ->and($userArr['address'])->toBe('AV. SIEMPRE VIVA 742');

    // Fetch from DB to ensure persisted formatting
    $user = User::find($userArr['id']);
    expect($user->name)->toBe('FERNANDO PÉREZ')
        ->and($user->email)->toBe('fernando.perez@example.com')
        ->and($user->address)->toBe('AV. SIEMPRE VIVA 742');
});

it('uppercases fields when creating via UserController@store', function () {
    // Create admin to authenticate, since /api/users is protected by sanctum
    // Create roles first to avoid Spatie exception
    Spatie\Permission\Models\Role::findOrCreate('admin');
    Spatie\Permission\Models\Role::findOrCreate('client');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin, 'sanctum');

    $payload = [
        'name' => 'maria lopez',
        'email' => 'Maria+Test@MAIL.com',
        'password' => 'secretpass',
        'roles' => ['client'],
        'ci' => 'lp-123abc',
        'address' => 'calle falsa 123',
    ];

    $resp = $this->postJson('/api/users', $payload);
    $resp->assertSuccessful();

    $userArr = $resp->json('data');
    expect($userArr['name'])->toBe('MARIA LOPEZ')
        ->and($userArr['email'])->toBe('maria+test@mail.com')
        ->and($userArr['address'])->toBe('CALLE FALSA 123')
        ->and($userArr['ci'])->toBe('LP-123ABC');

    $user = User::find($userArr['id']);
    expect($user->name)->toBe('MARIA LOPEZ')
        ->and($user->email)->toBe('maria+test@mail.com')
        ->and($user->address)->toBe('CALLE FALSA 123')
        ->and($user->ci)->toBe('LP-123ABC');
});
