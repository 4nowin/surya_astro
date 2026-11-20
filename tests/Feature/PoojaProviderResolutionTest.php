<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\Pooja;
use App\Models\ProfessionalPublicProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PoojaProviderResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $astroRole = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        $this->admin = Admin::create([
            'name' => 'Admin Acharya',
            'email' => 'acharya@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->admin->assignRole($astroRole);
    }

    protected function createAstrologer(array $overrides = []): Astrologer
    {
        return Astrologer::create(array_merge([
            'admin_id' => $this->admin->id,
            'name' => 'Pandit Dev Sharma',
            'language' => 'en',
            'astrologer_language' => 'Hindi, English',
            'expertise' => 'Vedic Astrology',
            'experience' => '5 years',
            'excerpt' => 'Vedic expert',
            'description' => 'Detailed description',
            'image' => 'astrologer.jpg',
            'price' => 10,
            'active' => 1,
            'status' => 'online',
        ], $overrides));
    }

    public function test_resolves_provider_name_matching_requested_language(): void
    {
        // Multi-language astrologers under one admin_id
        $this->createAstrologer([
            'name' => 'Pandit Dev Sharma',
            'language' => 'en',
        ]);

        $this->createAstrologer([
            'name' => 'पंडित देव शर्मा',
            'language' => 'hi',
            'astrologer_language' => 'हिंदी',
            'expertise' => 'वैदिक ज्योतिष',
        ]);

        $poojaEn = Pooja::create([
            'admin_id' => $this->admin->id,
            'title' => 'Rudra Abhishek',
            'language' => 'en',
            'tag' => 'Shiva',
            'price' => 1100,
            'original_price' => 2100,
            'active' => 1,
            'image' => 'rudra.jpg',
        ]);

        $poojaHi = Pooja::create([
            'admin_id' => $this->admin->id,
            'title' => 'रुद्राभिषेक',
            'language' => 'hi',
            'tag' => 'Shiva',
            'price' => 1100,
            'original_price' => 2100,
            'active' => 1,
            'image' => 'rudra.jpg',
        ]);

        $resEn = $this->getJson('/api/en/poojas');
        $resEn->assertStatus(200);
        $resEn->assertJsonFragment([
            'id' => $poojaEn->id,
            'provider_name' => 'Pandit Dev Sharma',
        ]);

        $resHi = $this->getJson('/api/hi/poojas');
        $resHi->assertStatus(200);
        $resHi->assertJsonFragment([
            'id' => $poojaHi->id,
            'provider_name' => 'पंडित देव शर्मा',
        ]);
    }

    public function test_falls_back_to_active_astrologer_when_language_not_found(): void
    {
        // Only English astrologer active
        $this->createAstrologer([
            'name' => 'Pandit Dev Sharma',
            'language' => 'en',
        ]);

        $poojaHi = Pooja::create([
            'admin_id' => $this->admin->id,
            'title' => 'महामृत्युंजय जाप',
            'language' => 'hi',
            'tag' => 'Shiva',
            'price' => 2500,
            'original_price' => 5000,
            'active' => 1,
            'image' => 'jaap.jpg',
        ]);

        $response = $this->getJson('/api/hi/poojas');
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $poojaHi->id,
            'provider_name' => 'Pandit Dev Sharma', // Clean fallback to available active row
        ]);
    }

    public function test_falls_back_to_public_profile_display_name_when_no_astrologer_rows(): void
    {
        ProfessionalPublicProfile::create([
            'admin_id' => $this->admin->id,
            'display_name' => 'Acharya Raman Shastri',
            'headline' => 'Vedic Specialist',
            'profile_status' => 'approved',
            'is_published' => true,
        ]);

        $pooja = Pooja::create([
            'admin_id' => $this->admin->id,
            'title' => 'Navagraha Shanti',
            'language' => 'en',
            'tag' => 'Graha',
            'price' => 3100,
            'original_price' => 5100,
            'active' => 1,
            'image' => 'navagraha.jpg',
        ]);

        $response = $this->getJson('/api/en/poojas');
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $pooja->id,
            'provider_name' => 'Acharya Raman Shastri',
        ]);
    }

    public function test_falls_back_to_admin_name_when_no_public_profile_or_astrologer(): void
    {
        $pooja = Pooja::create([
            'admin_id' => $this->admin->id,
            'title' => 'Ganesh Pooja',
            'language' => 'en',
            'tag' => 'Ganesh',
            'price' => 500,
            'original_price' => 1000,
            'active' => 1,
            'image' => 'ganesh.jpg',
        ]);

        $response = $this->getJson('/api/en/poojas');
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $pooja->id,
            'provider_name' => 'Admin Acharya',
        ]);
    }

    public function test_home_poojas_endpoint_includes_provider_name_and_image_url(): void
    {
        $this->createAstrologer([
            'name' => 'Pandit Dev Sharma',
            'language' => 'en',
        ]);

        $pooja = Pooja::create([
            'admin_id' => $this->admin->id,
            'title' => 'Home Featured Pooja',
            'language' => 'en',
            'tag' => 'Featured',
            'price' => 1500,
            'original_price' => 3000,
            'active' => 1,
            'home_priority' => 1,
            'image' => 'featured_pooja.png',
        ]);

        $response = $this->getJson('/api/en/home-poojas');
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertEquals('Pandit Dev Sharma', $data[0]['provider_name']);
        $this->assertNotNull($data[0]['image_url']);
        $this->assertStringContainsString('featured_pooja.png', $data[0]['image_url']);
    }

    public function test_image_url_attribute_handles_full_urls_storage_paths_and_nulls(): void
    {
        $pooja1 = new Pooja(['image' => 'https://cdn.example.com/pooja.png']);
        $this->assertEquals('https://cdn.example.com/pooja.png', $pooja1->image_url);

        $pooja2 = new Pooja(['image' => 'uploads/pooja123.jpg']);
        $this->assertStringContainsString('pooja123.jpg', $pooja2->image_url);

        $pooja3 = new Pooja(['image' => null]);
        $this->assertNull($pooja3->image_url);
    }
}
