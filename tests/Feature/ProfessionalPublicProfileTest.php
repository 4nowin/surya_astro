<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Jajman;
use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalBookingRequest;
use App\Models\ProfessionalPublicProfile;
use App\Models\ProfessionalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfessionalPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);
    }

    protected function createPractitioner(string $name = 'Acharya Vidyadhar', string $email = 'vidyadhar@panditsetu.com'): Admin
    {
        $admin = Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('Secret@123'),
        ]);
        $admin->assignRole('Astrologer');
        return $admin;
    }

    public function test_unauthenticated_requests_cannot_access_profile_management()
    {
        $response = $this->getJson('/api/astrologer/public-profile');
        $response->assertStatus(401);
    }

    public function test_practitioner_can_configure_and_publish_public_profile()
    {
        $admin = $this->createPractitioner();
        Sanctum::actingAs($admin);

        // 1. Get profile (auto-initialized)
        $res = $this->getJson('/api/astrologer/public-profile');
        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.display_name', 'Acharya Vidyadhar')
            ->assertJsonPath('data.is_published', false);

        // 2. Update profile
        $updateRes = $this->postJson('/api/astrologer/public-profile', [
            'display_name' => 'Acharya Vidyadhar Sharma',
            'slug' => 'acharya-vidyadhar',
            'headline' => 'Renowned Vedic Astrologer & Puja Acharya',
            'about' => 'Practicing authentic Vedic astrology and Vedic rituals for 15+ years.',
            'categories' => ['astrologer', 'pandit'],
            'specializations' => ['Kundli Milan', 'Satyanarayan Katha', 'Vastu'],
            'languages' => ['Hindi', 'Sanskrit', 'English'],
            'years_of_experience' => 15,
            'city' => 'Varanasi',
            'service_area' => 'Varanasi & Online Worldwide',
            'is_online_available' => true,
            'is_in_person_available' => true,
            'is_home_visit_available' => true,
            'public_phone' => '+91 98765 43210',
            'public_email' => 'contact@vidyadhar.com',
            'is_published' => true,
            'is_accepting_bookings' => true,
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'acharya-vidyadhar')
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.profile_status', 'published');
    }

    public function test_public_client_can_view_published_profile_and_services()
    {
        $admin = $this->createPractitioner();

        // Create published profile
        ProfessionalPublicProfile::create([
            'admin_id' => $admin->id,
            'slug' => 'pandit-sharma',
            'display_name' => 'Pandit Sharma',
            'headline' => 'Vedic Purohit',
            'about' => 'Spiritual ceremonies and consultations',
            'city' => 'Haridwar',
            'is_published' => true,
            'is_accepting_bookings' => true,
            'profile_status' => 'published',
        ]);

        // Create 1 public service and 1 private service
        $pubService = ProfessionalService::create([
            'admin_id' => $admin->id,
            'name' => 'Griha Pravesh Puja',
            'description' => 'Complete home consecration ceremony',
            'category' => 'pandit',
            'pricing_model' => 'fixed',
            'price' => 5100.00,
            'duration_minutes' => 120,
            'delivery_methods' => ['in_person', 'home_visit'],
            'is_active' => true,
            'is_public' => true,
        ]);

        $privService = ProfessionalService::create([
            'admin_id' => $admin->id,
            'name' => 'Private VIP Consultation',
            'description' => 'Internal only',
            'category' => 'astrologer',
            'pricing_model' => 'custom',
            'price' => 15000.00,
            'duration_minutes' => 60,
            'delivery_methods' => ['online'],
            'is_active' => true,
            'is_public' => false,
        ]);

        // 1. Public Profile retrieval
        $profileRes = $this->getJson('/api/public/practitioners/pandit-sharma');
        $profileRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.display_name', 'Pandit Sharma')
            ->assertJsonPath('data.city', 'Haridwar');

        // 2. Public Services retrieval
        $servicesRes = $this->getJson('/api/public/practitioners/pandit-sharma/services');
        $servicesRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Griha Pravesh Puja')
            ->assertJsonPath('data.0.price', 5100);
    }

    public function test_unpublished_profile_returns_404_to_public()
    {
        $admin = $this->createPractitioner();

        ProfessionalPublicProfile::create([
            'admin_id' => $admin->id,
            'slug' => 'draft-profile',
            'display_name' => 'Draft Pandit',
            'is_published' => false,
            'profile_status' => 'not_published',
        ]);

        $res = $this->getJson('/api/public/practitioners/draft-profile');
        $res->assertStatus(404);
    }

    public function test_client_can_calculate_availability_and_submit_booking_request()
    {
        $admin = $this->createPractitioner();

        $profile = ProfessionalPublicProfile::create([
            'admin_id' => $admin->id,
            'slug' => 'acharya-ji',
            'display_name' => 'Acharya Ji',
            'is_published' => true,
            'is_accepting_bookings' => true,
            'profile_status' => 'published',
        ]);

        // Setup weekly availability (Monday = 1)
        ProfessionalAvailability::create([
            'admin_id' => $admin->id,
            'day_of_week' => 1,
            'is_enabled' => true,
            'slots' => [['start' => '10:00', 'end' => '14:00']],
            'timezone' => 'Asia/Kolkata',
        ]);

        $service = ProfessionalService::create([
            'admin_id' => $admin->id,
            'name' => 'Kundli Consultation',
            'category' => 'astrologer',
            'pricing_model' => 'fixed',
            'price' => 1100.00,
            'duration_minutes' => 30,
            'delivery_methods' => ['online'],
            'is_active' => true,
            'is_public' => true,
        ]);

        // Find next Monday
        $nextMonday = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->format('Y-m-d');

        // Check availability
        $availRes = $this->getJson("/api/public/practitioners/acharya-ji/availability?date={$nextMonday}&service_id={$service->id}");
        $availRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Submit Booking Request
        $bookRes = $this->postJson('/api/public/practitioners/acharya-ji/booking-requests', [
            'client_name' => 'Amitabh Bachchan',
            'client_phone' => '9820098200',
            'client_email' => 'amitabh@example.com',
            'service_id' => $service->id,
            'requested_date' => $nextMonday,
            'requested_time' => '10:00',
            'delivery_method' => 'online',
            'client_message' => 'Need advice regarding upcoming film release date.',
        ]);

        $bookRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $bookingRef = $bookRes->json('data.booking_reference');
        $token = $bookRes->json('data.access_token');
        $this->assertNotEmpty($bookingRef);
        $this->assertNotEmpty($token);

        // Check booking request status via public endpoint
        $statusRes = $this->getJson("/api/public/booking-requests/{$bookingRef}?token={$token}");
        $statusRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.client_name', 'Amitabh Bachchan');
    }

    public function test_practitioner_can_accept_booking_request_creating_jajman_and_appointment()
    {
        $admin = $this->createPractitioner();

        $service = ProfessionalService::create([
            'admin_id' => $admin->id,
            'name' => 'Vastu Inspection',
            'category' => 'vastu',
            'pricing_model' => 'fixed',
            'price' => 7500.00,
            'duration_minutes' => 60,
            'delivery_methods' => ['home_visit'],
            'is_active' => true,
            'is_public' => true,
        ]);

        $startUtc = Carbon::tomorrow('UTC')->setTime(10, 0);
        $endUtc = $startUtc->copy()->addMinutes(60);

        $booking = ProfessionalBookingRequest::create([
            'admin_id' => $admin->id,
            'booking_reference' => 'BK-2026-TEST01',
            'access_token' => 'token12345678901234567890123456',
            'service_id' => $service->id,
            'service_name_snapshot' => $service->name,
            'agreed_price_snapshot' => 7500.00,
            'currency_snapshot' => 'INR',
            'duration_minutes_snapshot' => 60,
            'delivery_method' => 'home_visit',
            'requested_start_at' => $startUtc,
            'requested_end_at' => $endUtc,
            'timezone' => 'Asia/Kolkata',
            'client_name' => 'Sanjay Singhania',
            'client_phone' => '9988776655',
            'client_email' => 'sanjay@airvoice.com',
            'location' => 'Bandra West, Mumbai',
            'status' => 'pending',
        ]);

        // Practitioner accepts request with new Jajman creation
        Sanctum::actingAs($admin);
        $acceptRes = $this->postJson("/api/astrologer/booking-requests/{$booking->id}/accept", [
            'create_new_jajman' => true,
            'jajman_city' => 'Mumbai',
        ]);

        $acceptRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking.status', 'confirmed');

        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
        $this->assertNotNull($booking->jajman_id);
        $this->assertNotNull($booking->appointment_id);

        // Verify appointment was created properly
        $appt = Appointment::find($booking->appointment_id);
        $this->assertNotNull($appt);
        $this->assertEquals('client_requested', $appt->booking_source);
        $this->assertEquals(7500.00, (float) $appt->agreed_price_snapshot);
        $this->assertEquals('confirmed', $appt->status);

        // Verify Jajman record was created
        $jajman = Jajman::find($booking->jajman_id);
        $this->assertNotNull($jajman);
        $this->assertEquals('Sanjay Singhania', $jajman->name);
        $this->assertEquals('Mumbai', $jajman->city);
    }

    public function test_conflict_detection_prevents_double_booking()
    {
        $admin = $this->createPractitioner();

        $startUtc = Carbon::tomorrow('UTC')->setTime(14, 0);
        $endUtc = $startUtc->copy()->addMinutes(60);

        // Pre-existing confirmed appointment
        Appointment::create([
            'admin_id' => $admin->id,
            'title' => 'Existing Confirmed Puja',
            'start_at' => $startUtc,
            'end_at' => $endUtc,
            'status' => 'confirmed',
            'booking_source' => 'professional_created',
        ]);

        $service = ProfessionalService::create([
            'admin_id' => $admin->id,
            'name' => 'Consultation',
            'category' => 'astrologer',
            'price' => 1000.00,
            'duration_minutes' => 60,
            'delivery_methods' => ['online'],
            'is_active' => true,
            'is_public' => true,
        ]);

        $booking = ProfessionalBookingRequest::create([
            'admin_id' => $admin->id,
            'booking_reference' => 'BK-2026-CONFLICT',
            'access_token' => 'tokentokentokentokentokentoken',
            'service_id' => $service->id,
            'service_name_snapshot' => $service->name,
            'agreed_price_snapshot' => 1000.00,
            'currency_snapshot' => 'INR',
            'duration_minutes_snapshot' => 60,
            'delivery_method' => 'online',
            'requested_start_at' => $startUtc,
            'requested_end_at' => $endUtc,
            'client_name' => 'Conflicted Client',
            'client_phone' => '9111122222',
            'status' => 'pending',
        ]);

        // Attempt to accept conflicting booking request
        Sanctum::actingAs($admin);
        $res = $this->postJson("/api/astrologer/booking-requests/{$booking->id}/accept", [
            'create_new_jajman' => true,
        ]);

        $res->assertStatus(409)
            ->assertJsonPath('success', false);
    }
}
