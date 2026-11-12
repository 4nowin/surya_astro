<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfessionalAstrologyEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $practitionerA;
    protected Admin $practitionerB;
    protected Jajman $jajmanA;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        $this->practitionerA = Admin::create([
            'name' => 'Pandit Sharma',
            'email' => 'sharma_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('secret123'),
        ]);
        $this->practitionerA->assignRole('Astrologer');

        $this->practitionerB = Admin::create([
            'name' => 'Acharya Verma',
            'email' => 'verma_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('secret123'),
        ]);
        $this->practitionerB->assignRole('Astrologer');

        $this->jajmanA = Jajman::create([
            'name' => 'Rahul Verma',
            'phone' => '+919876543210',
            'city' => 'New Delhi',
            'date_of_birth' => '1990-05-15',
        ]);
        $this->jajmanA->professionals()->attach($this->practitionerA->id, ['status' => 'active', 'source' => 'manual']);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $resKundli = $this->getJson("/api/astrologer/jajmans/{$this->jajmanA->id}/kundli");
        $resKundli->assertUnauthorized();

        $resPanchang = $this->getJson('/api/astrologer/astrology/panchang/daily');
        $resPanchang->assertUnauthorized();

        $resCalc = $this->postJson('/api/astrologer/astrology/kundli/calculate', []);
        $resCalc->assertUnauthorized();
    }

    public function test_practitioner_cannot_access_another_practitioners_jajman_kundli(): void
    {
        $this->actingAs($this->practitionerB, 'sanctum');

        $res = $this->getJson("/api/astrologer/jajmans/{$this->jajmanA->id}/kundli");
        $res->assertNotFound();
    }

    public function test_jajman_without_birth_details_returns_appropriate_error(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        $res = $this->getJson("/api/astrologer/jajmans/{$this->jajmanA->id}/kundli");
        $res->assertStatus(404)
            ->assertJsonPath('status', 'no_birth_details');
    }

    public function test_jajman_with_birth_details_generates_complete_kundli(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        JajmanBirthDetail::create([
            'jajman_id' => $this->jajmanA->id,
            'date_of_birth' => '1990-05-15',
            'birth_time' => '14:30:00',
            'time_unknown' => false,
            'birth_place' => 'New Delhi',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'is_primary' => true,
        ]);

        $res = $this->getJson("/api/astrologer/jajmans/{$this->jajmanA->id}/kundli");
        $res->assertOk()
            ->assertJsonPath('success', true);

        $data = $res->json('data');
        $this->assertNotNull($data);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('ascendant', $data);
        $this->assertArrayHasKey('planets', $data);
        $this->assertArrayHasKey('houses', $data);
        $this->assertArrayHasKey('chandra_kundli', $data);
        $this->assertArrayHasKey('dasha', $data);
        $this->assertArrayHasKey('manglik_dosha', $data);
        $this->assertArrayHasKey('yogas', $data);
        $this->assertArrayHasKey('navamsha', $data);
        $this->assertArrayHasKey('saturn_transit', $data);

        // Verify specific astrological calculations
        $this->assertEquals('TRUE_CITRA_LAHIRI', $data['metadata']['ayanamsha']);
        $this->assertEquals('Virgo', $data['ascendant']['name']);
        $this->assertCount(9, $data['planets']); // Sun..Ketu
        $this->assertCount(12, $data['houses']); // 12 houses
        $this->assertEquals(10, $data['planets']['moon']['rashi']['id']); // Capricorn Moon
        $this->assertNotNull($data['navamsha']['houses']);
        $this->assertCount(12, $data['navamsha']['houses']);
    }

    public function test_recalculate_jajman_kundli_endpoint(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        JajmanBirthDetail::create([
            'jajman_id' => $this->jajmanA->id,
            'date_of_birth' => '1995-10-24',
            'birth_time' => '06:15:00',
            'time_unknown' => false,
            'birth_place' => 'Varanasi',
            'latitude' => 25.3176,
            'longitude' => 82.9739,
            'timezone' => 'Asia/Kolkata',
            'is_primary' => true,
        ]);

        $res = $this->postJson("/api/astrologer/jajmans/{$this->jajmanA->id}/kundli/calculate");
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.metadata.ayanamsha', 'TRUE_CITRA_LAHIRI');
    }

    public function test_ephemeral_kundli_calculator_endpoint(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        $res = $this->postJson('/api/astrologer/astrology/kundli/calculate', [
            'name' => 'Walk-in Client',
            'date_of_birth' => '1985-11-20',
            'birth_time' => '08:30:00',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'New Delhi',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ascendant.name', 'Scorpio');
    }

    public function test_daily_panchang_endpoint(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        $res = $this->getJson('/api/astrologer/astrology/panchang/daily?date=2026-11-12&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');
        $res->assertOk()
            ->assertJsonPath('success', true);

        $data = $res->json('data');
        $this->assertArrayHasKey('sun_moon', $data);
        $this->assertArrayHasKey('panchanga', $data);
        $this->assertArrayHasKey('inauspicious_timings', $data);
        $this->assertArrayHasKey('auspicious_timings', $data);
        $this->assertNotNull($data['sun_moon']['sunrise']);
        $this->assertNotNull($data['panchanga']['tithi']);
        $this->assertNotNull($data['inauspicious_timings']['rahu_kaal']);
    }

    public function test_cities_geocoding_endpoint(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        $res = $this->getJson('/api/astrologer/astrology/cities?q=delhi');
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city', 'New Delhi');
    }
}
