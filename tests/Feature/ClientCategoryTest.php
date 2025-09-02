<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('seeds client_categories and returns them from the API', function () {
    // Ensure migration seeded categories
    $count = DB::table('client_categories')->count();
    expect($count)->toBeGreaterThan(0);

    $codes = DB::table('client_categories')->pluck('code')->toArray();
    expect($codes)->toContain('A');
    expect($codes)->toContain('B');
    expect($codes)->toContain('C');

    // Authenticate any user to pass auth:sanctum
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/client-categories');
    $response->assertSuccessful();

    // Validate standard API envelope and that 'data' is an array
    $response->assertJsonStructure([
        'success', 'data', 'message',
    ]);

    $data = $response->json('data');
    expect(is_array($data))->toBeTrue();
    $apiCodes = collect($data)->pluck('code')->all();

    expect($apiCodes)->toContain('A');
    expect($apiCodes)->toContain('B');
    expect($apiCodes)->toContain('C');
});
