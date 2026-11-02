<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class GeoCitiesApiTest extends TestCase
{
    public function test_can_search_cities_without_query_returns_top_cities(): void
    {
        $response = $this->getJson('/api/v1/geo/cities');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'city',
                        'state',
                        'country',
                        'latitude',
                        'longitude',
                        'timezone',
                        'label',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_can_search_city_by_prefix(): void
    {
        $response = $this->getJson('/api/v1/geo/cities?q=del');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertStringContainsStringIgnoringCase('delhi', $data[0]['city']);
        $this->assertEquals('Asia/Kolkata', $data[0]['timezone']);
        $this->assertEquals(28.6139391, $data[0]['latitude']);
        $this->assertEquals(77.2090212, $data[0]['longitude']);
    }

    public function test_search_respects_limit_parameter(): void
    {
        $response = $this->getJson('/api/v1/geo/cities?q=a&limit=3');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertLessThanOrEqual(3, count($data));
    }

    public function test_returns_empty_array_for_unknown_location(): void
    {
        $response = $this->getJson('/api/v1/geo/cities?q=NonExistentPlaceXYZ999');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }
}
