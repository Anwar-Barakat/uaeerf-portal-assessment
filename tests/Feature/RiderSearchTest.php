<?php

use App\Models\User;
use App\Services\Soap\RidersService;

test('rider search requires authentication', function () {
    $response = $this->getJson('/api/riders/search?q=ahmad');

    $response->assertStatus(401);
});

test('rider search requires minimum query length', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->getJson('/api/riders/search?q=a');

    $response->assertStatus(422);
});

test('rider search returns mapped riders', function () {
    $this->actingAs(User::factory()->create());

    $this->mock(RidersService::class, function ($mock) {
        $mock->shouldReceive('searchByName')
            ->once()
            ->with('ahmad')
            ->andReturn([
                [
                    'rider_id' => 'E0022141',
                    'name' => 'Mubashir Ahmad',
                    'dob' => '1990-08-13',
                    'nationality' => 'PAK',
                    'registered_current_season' => true,
                ],
            ]);
    });

    $response = $this->getJson('/api/riders/search?q=ahmad');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['rider_id' => 'E0022141', 'name' => 'Mubashir Ahmad'],
            ],
        ]);
});

test('rider search handles soap failure gracefully', function () {
    $this->actingAs(User::factory()->create());

    $this->mock(RidersService::class, function ($mock) {
        $mock->shouldReceive('searchByName')
            ->andThrow(new \RuntimeException('SOAP down'));
    });

    $response = $this->getJson('/api/riders/search?q=ahmad');

    $response->assertStatus(500)->assertJson(['success' => false]);
});
