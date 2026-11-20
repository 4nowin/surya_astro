<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Pooja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AstrologerPoojaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $astrologerA;
    protected Admin $astrologerB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $astroRole = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        $this->astrologerA = Admin::create([
            'name' => 'Pandit Raman Sharma',
            'email' => 'raman@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->astrologerA->assignRole($astroRole);
        $this->tokenA = $this->astrologerA->createToken('test_token')->plainTextToken;

        $this->astrologerB = Admin::create([
            'name' => 'Acharya Mohan Shastri',
            'email' => 'mohan@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->astrologerB->assignRole($astroRole);
        $this->tokenB = $this->astrologerB->createToken('test_token')->plainTextToken;
    }

    public function test_lists_own_poojas_scoped_by_admin_id_and_language(): void
    {
        $poojaEn = Pooja::create([
            'admin_id' => $this->astrologerA->id,
            'title' => 'Rudra Abhishek English',
            'language' => 'en',
            'tag' => 'Shiva',
            'price' => 1100,
            'original_price' => 2100,
            'active' => 1,
        ]);

        $poojaHi = Pooja::create([
            'admin_id' => $this->astrologerA->id,
            'title' => 'रुद्राभिषेक हिंदी',
            'language' => 'hi',
            'tag' => 'Shiva',
            'price' => 1100,
            'original_price' => 2100,
            'active' => 1,
        ]);

        // Pooja belonging to Astrologer B
        $poojaB = Pooja::create([
            'admin_id' => $this->astrologerB->id,
            'title' => 'Other Astrologer Pooja',
            'language' => 'en',
            'tag' => 'Other',
            'price' => 500,
            'original_price' => 1000,
            'active' => 1,
        ]);

        // Astrologer A lists all own poojas
        $resAll = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/astrologer/poojas');
        $resAll->assertStatus(200);
        $this->assertCount(2, $resAll->json('data'));

        // Language filter: 'hi'
        $resHi = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/astrologer/poojas?language=hi');
        $resHi->assertStatus(200);
        $this->assertCount(1, $resHi->json('data'));
        $this->assertEquals($poojaHi->id, $resHi->json('data.0.id'));

        // Language filter: 'en'
        $resEn = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/astrologer/poojas?language=en');
        $resEn->assertStatus(200);
        $this->assertCount(1, $resEn->json('data'));
        $this->assertEquals($poojaEn->id, $resEn->json('data.0.id'));

        // Astrologer B sees only own poojas
        app('auth')->forgetGuards();
        $resB = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->getJson('/api/astrologer/poojas');
        $resB->assertStatus(200);
        $this->assertCount(1, $resB->json('data'));
        $this->assertEquals($poojaB->id, $resB->json('data.0.id'));
    }

    public function test_tenant_isolation_blocks_cross_astrologer_access(): void
    {
        $poojaA = Pooja::create([
            'admin_id' => $this->astrologerA->id,
            'title' => 'Secret Ceremony',
            'language' => 'en',
            'tag' => 'Special',
            'price' => 2100,
            'original_price' => 4100,
            'active' => 1,
        ]);

        // Astrologer B attempts to read A's pooja -> 403
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->getJson("/api/astrologer/poojas/{$poojaA->id}")
            ->assertStatus(403);

        // Astrologer B attempts to update A's pooja -> 403
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->postJson("/api/astrologer/poojas/{$poojaA->id}", [
                'title' => 'Hacked Title',
            ])
            ->assertStatus(403);

        // Astrologer B attempts to toggle active -> 403
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->postJson("/api/astrologer/poojas/{$poojaA->id}/toggle-active")
            ->assertStatus(403);

        // Astrologer B attempts to delete A's pooja -> 403
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->deleteJson("/api/astrologer/poojas/{$poojaA->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('poojas', [
            'id' => $poojaA->id,
            'title' => 'Secret Ceremony',
        ]);
    }

    public function test_creates_pooja_and_toggles_active_status(): void
    {
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/astrologer/poojas', [
                'title' => 'Maha Ganapati Hawan',
                'language' => 'hi',
                'tag' => 'Ganesh',
                'excerpt' => 'Vedic Hawan for prosperity',
                'description' => 'Detailed ritual guidelines',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-05',
                'price' => 1500,
                'original_price' => 3000,
                'active' => true,
            ]);

        $res->assertStatus(201);
        $poojaId = $res->json('data.id');
        $this->assertNotNull($poojaId);
        $this->assertEquals($this->astrologerA->id, $res->json('data.admin_id'));

        // Toggle to inactive
        $toggleRes1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/astrologer/poojas/{$poojaId}/toggle-active");
        $toggleRes1->assertStatus(200);
        $this->assertFalse((bool) $toggleRes1->json('data.active'));

        // Toggle back to active
        $toggleRes2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/astrologer/poojas/{$poojaId}/toggle-active");
        $toggleRes2->assertStatus(200);
        $this->assertTrue((bool) $toggleRes2->json('data.active'));
    }

    public function test_image_upload_validation_rejects_invalid_types_and_oversized_files(): void
    {
        $pooja = Pooja::create([
            'admin_id' => $this->astrologerA->id,
            'title' => 'Navagraha Shanti',
            'language' => 'en',
            'tag' => 'Graha',
            'price' => 1100,
            'original_price' => 2100,
            'active' => 1,
        ]);

        // 1. Invalid MIME type (text file)
        $invalidFile = UploadedFile::fake()->create('document.txt', 100, 'text/plain');
        $resInvalid = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/astrologer/poojas/{$pooja->id}/image", [
                'image' => $invalidFile,
            ]);
        $resInvalid->assertStatus(422);

        // 2. Oversized file (> 5MB = 5120 KB)
        $oversizedFile = UploadedFile::fake()->create('huge.png', 6000, 'image/png');
        $resOversized = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/astrologer/poojas/{$pooja->id}/image", [
                'image' => $oversizedFile,
            ]);
        $resOversized->assertStatus(422);
    }

    public function test_preserves_existing_image_when_updating_other_fields(): void
    {
        $pooja = Pooja::create([
            'admin_id' => $this->astrologerA->id,
            'title' => 'Satyanarayan Katha',
            'language' => 'en',
            'tag' => 'Vishnu',
            'price' => 1100,
            'original_price' => 2100,
            'active' => 1,
            'image' => 'existing_pooja_image.jpg',
        ]);

        // Update title and price without touching image
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/astrologer/poojas/{$pooja->id}", [
                'title' => 'Satyanarayan Katha Updated',
                'price' => 1500,
            ]);

        $res->assertStatus(200);
        $this->assertEquals('Satyanarayan Katha Updated', $res->json('data.title'));
        $this->assertEquals(1500, $res->json('data.price'));
        // Image must remain intact
        $this->assertEquals('existing_pooja_image.jpg', $res->json('data.image'));
        $this->assertNotNull($res->json('data.image_url'));
    }

    public function test_explicit_remove_image_clears_image(): void
    {
        $pooja = Pooja::create([
            'admin_id' => $this->astrologerA->id,
            'title' => 'Shani Shanti',
            'language' => 'en',
            'tag' => 'Shani',
            'price' => 750,
            'original_price' => 1500,
            'active' => 1,
            'image' => 'shani.jpg',
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson("/api/astrologer/poojas/{$pooja->id}/image");

        $res->assertStatus(200);
        $this->assertNull($res->json('data.image'));
        $this->assertNull($res->json('data.image_url'));

        $this->assertDatabaseHas('poojas', [
            'id' => $pooja->id,
            'image' => null,
        ]);
    }
}
