<?php

use Illuminate\Support\Facades\Config;

it('returns reverb config for mobile clients', function () {
    $response = $this->getJson('/api/reverb/config');

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'broadcaster', 'key', 'wsHost', 'wsPort', 'scheme', 'useTLS', 'cluster', 'authEndpoint',
    ]);

    $reverb = Config::get('broadcasting.connections.reverb');
    $options = $reverb['options'] ?? [];

    $response->assertJson([
        'broadcaster' => 'reverb',
        'key' => (string) ($reverb['key'] ?? ''),
        'wsHost' => (string) ($options['host'] ?? '127.0.0.1'),
        'wsPort' => (int) ($options['port'] ?? 8080),
        'scheme' => (string) ($options['scheme'] ?? 'http'),
        'useTLS' => (bool) ($options['useTLS'] ?? false),
        'cluster' => 'mt1',
    ]);
});
