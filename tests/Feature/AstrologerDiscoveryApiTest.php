<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Astrologer;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\AdminSeeder;
use Database\Seeders\AstrologerSeeder;

class AstrologerDiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
        $this->seed(AstrologerSeeder::class);
    }

    public function test_can_list_astrologers_for_english()
    {
        $response = $this->getJson('/api/en/astrologers');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'astrologer' => [
                    'id',
                    'name',
                    'language',
                    'expertise',
                    'price',
                    'status',
                    'active',
                ],
                'reviews',
                'average_rating',
            ]
        ]);
    }

    public function test_invalid_language_returns_400()
    {
        $response = $this->getJson('/api/fr/astrologers');
        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid language selected']);
    }

    public function test_filter_by_valid_specialisation()
    {
        // Ravinder and Suman have Numerology
        $response = $this->getJson('/api/en/astrologers?expertise=Numerology');
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('Numerology', $item['astrologer']['expertise']);
        }
    }

    public function test_filter_by_specialisation_with_no_matches_returns_404()
    {
        // Currently no astrologer has Vastu in the seed DB
        $response = $this->getJson('/api/en/astrologers?expertise=Vastu');
        $response->assertStatus(404);
        $response->assertJson(['message' => 'No Astrologer found']);
    }

    public function test_invalid_specialisation_filter_returns_422()
    {
        $response = $this->getJson('/api/en/astrologers?expertise=invalid_hacker_filter_123');
        $response->assertStatus(422);
        $response->assertJson(['error' => 'Invalid specialisation filter']);
    }

    public function test_online_astrologers_appear_first()
    {
        $admin = Admin::first();

        Astrologer::create([
            'admin_id' => $admin->id,
            'name' => 'Offline Astro',
            'language' => 'en',
            'astrologer_language' => 'English',
            'expertise' => 'Tarot Reading',
            'experience' => '3 years',
            'chat_minutes' => 10,
            'call_minutes' => 10,
            'price' => 5,
            'original_price' => 10,
            'status' => 'offline',
            'active' => true,
        ]);

        Astrologer::create([
            'admin_id' => $admin->id,
            'name' => 'Online Astro',
            'language' => 'en',
            'astrologer_language' => 'English',
            'expertise' => 'Tarot Reading',
            'experience' => '4 years',
            'chat_minutes' => 10,
            'call_minutes' => 10,
            'price' => 6,
            'original_price' => 12,
            'status' => 'online',
            'active' => true,
        ]);

        $response = $this->getJson('/api/en/astrologers?expertise=Tarot Reading');
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertNotEmpty($data);
        // The first element should be online
        $this->assertEquals('online', $data[0]['astrologer']['status']);
    }

    public function test_astrologer_with_multiple_specialisations_discovered_by_each()
    {
        $admin = Admin::first();
        Astrologer::create([
            'admin_id' => $admin->id,
            'name' => 'Multi Astro',
            'language' => 'en',
            'astrologer_language' => 'English',
            'expertise' => 'Kundli, Vastu Shastra, Muhurat',
            'experience' => '8 years',
            'chat_minutes' => 10,
            'call_minutes' => 10,
            'price' => 10,
            'original_price' => 20,
            'status' => 'online',
            'active' => true,
        ]);

        // Discoverable via Kundli
        $kundliRes = $this->getJson('/api/en/astrologers?expertise=Kundli');
        $kundliRes->assertStatus(200);
        $kundliData = $kundliRes->json();
        $this->assertNotEmpty(array_filter($kundliData, fn($i) => $i['astrologer']['name'] === 'Multi Astro'));

        // Discoverable via Vastu
        $vastuRes = $this->getJson('/api/en/astrologers?expertise=Vastu');
        $vastuRes->assertStatus(200);
        $vastuData = $vastuRes->json();
        $this->assertNotEmpty(array_filter($vastuData, fn($i) => $i['astrologer']['name'] === 'Multi Astro'));

        // Discoverable via Muhurat
        $muhuratRes = $this->getJson('/api/en/astrologers?expertise=Muhurat');
        $muhuratRes->assertStatus(200);
        $muhuratData = $muhuratRes->json();
        $this->assertNotEmpty(array_filter($muhuratData, fn($i) => $i['astrologer']['name'] === 'Multi Astro'));
    }
}
