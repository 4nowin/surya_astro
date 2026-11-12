<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Jajman;
use App\Models\ProfessionalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfessionalServicesTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $practitionerA;
    protected Admin $practitionerB;
    protected Jajman $jajmanA;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        $this->practitionerA = Admin::create([
            'name' => 'Pandit Dev Sharma',
            'email' => 'sharma_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('secret123'),
        ]);
        $this->practitionerA->assignRole('Astrologer');

        $this->practitionerB = Admin::create([
            'name' => 'Acharya Manoj Joshi',
            'email' => 'joshi_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('secret123'),
        ]);
        $this->practitionerB->assignRole('Astrologer');

        $this->jajmanA = Jajman::create([
            'name' => 'Vikas Gupta',
            'phone' => '+919876500001',
            'city' => 'Jaipur',
        ]);
        $this->jajmanA->professionals()->attach($this->practitionerA->id, ['status' => 'active', 'source' => 'manual']);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/astrologer/services')->assertUnauthorized();
        $this->postJson('/api/astrologer/services', [])->assertUnauthorized();
        $this->getJson('/api/astrologer/services/templates')->assertUnauthorized();
        $this->getJson('/api/astrologer/services/summary')->assertUnauthorized();
    }

    public function test_practitioner_can_create_services_across_pricing_models(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        // 1. Fixed Price Service (Astrology)
        $res1 = $this->postJson('/api/astrologer/services', [
            'name' => 'Kundli Analysis (Detailed)',
            'description' => 'Complete birth chart reading with dasha timeline.',
            'category' => 'astrologer',
            'specialization' => 'Vedic Astrology',
            'pricing_model' => 'fixed',
            'price' => 1500,
            'currency' => 'INR',
            'duration_minutes' => 45,
            'delivery_methods' => ['online', 'phone_call'],
            'preparation_instructions' => 'Bring exact birth details.',
        ]);
        $res1->assertCreated();
        $this->assertDatabaseHas('professional_services', [
            'name' => 'Kundli Analysis (Detailed)',
            'admin_id' => $this->practitionerA->id,
            'pricing_model' => 'fixed',
            'price' => 1500,
        ]);

        // 2. Starting From Service (Puja / Ritual)
        $res2 = $this->postJson('/api/astrologer/services', [
            'name' => 'Griha Pravesh Puja',
            'description' => 'Auspicious house-warming Vedic rituals.',
            'category' => 'pandit_purohit',
            'specialization' => 'Griha Pravesh & Vastu Shanti',
            'pricing_model' => 'starting_from',
            'price' => 5100,
            'duration_minutes' => 180,
            'delivery_methods' => ['home_visit'],
        ]);
        $res2->assertCreated();
        $this->assertDatabaseHas('professional_services', [
            'name' => 'Griha Pravesh Puja',
            'admin_id' => $this->practitionerA->id,
            'pricing_model' => 'starting_from',
            'price' => 5100,
        ]);

        // 3. Custom / Price on Request (Wedding Rituals)
        $res3 = $this->postJson('/api/astrologer/services', [
            'name' => 'Vivah Sanskar Vedic Ceremony',
            'description' => 'Full day marriage ceremony with Saptapadi and Hawan.',
            'category' => 'pandit_purohit',
            'pricing_model' => 'custom',
            'duration_minutes' => 300,
            'delivery_methods' => ['home_visit', 'at_location'],
        ]);
        $res3->assertCreated();
        $this->assertDatabaseHas('professional_services', [
            'name' => 'Vivah Sanskar Vedic Ceremony',
            'admin_id' => $this->practitionerA->id,
            'pricing_model' => 'custom',
            'price' => null,
        ]);

        // 4. Hourly Rate (Vastu Consultation)
        $res4 = $this->postJson('/api/astrologer/services', [
            'name' => 'Commercial Vastu Inspection',
            'category' => 'vastu_consultant',
            'pricing_model' => 'hourly',
            'price' => 2000,
            'duration_minutes' => 60,
            'delivery_methods' => ['home_visit'],
        ]);
        $res4->assertCreated();
        $this->assertDatabaseHas('professional_services', [
            'name' => 'Commercial Vastu Inspection',
            'admin_id' => $this->practitionerA->id,
            'pricing_model' => 'hourly',
            'price' => 2000,
        ]);
    }

    public function test_tenant_isolation_prevents_unauthorized_access(): void
    {
        // Service owned by Practitioner A
        $serviceA = ProfessionalService::create([
            'admin_id' => $this->practitionerA->id,
            'name' => 'Private Jyotish Reading',
            'category' => 'astrologer',
            'pricing_model' => 'fixed',
            'price' => 2500,
            'duration_minutes' => 60,
            'delivery_methods' => ['online'],
            'is_active' => true,
        ]);

        // Practitioner B attempts to view, update, toggle or delete
        $this->actingAs($this->practitionerB, 'sanctum');

        $this->getJson("/api/astrologer/services/{$serviceA->id}")
            ->assertNotFound();

        $this->putJson("/api/astrologer/services/{$serviceA->id}", [
            'name' => 'Hacked Service Name',
            'price' => 10,
        ])->assertNotFound();

        $this->postJson("/api/astrologer/services/{$serviceA->id}/toggle-active")
            ->assertNotFound();

        $this->deleteJson("/api/astrologer/services/{$serviceA->id}")
            ->assertNotFound();

        // Verify service was not modified
        $this->assertDatabaseHas('professional_services', [
            'id' => $serviceA->id,
            'name' => 'Private Jyotish Reading',
            'price' => 2500,
        ]);
    }

    public function test_listing_services_supports_search_and_category_filtering(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        ProfessionalService::create([
            'admin_id' => $this->practitionerA->id,
            'name' => 'Shri Satyanarayan Katha',
            'category' => 'pandit_purohit',
            'pricing_model' => 'fixed',
            'price' => 3100,
            'duration_minutes' => 120,
            'delivery_methods' => ['home_visit'],
            'is_active' => true,
        ]);

        ProfessionalService::create([
            'admin_id' => $this->practitionerA->id,
            'name' => 'Vastu Floor Plan Audit',
            'category' => 'vastu_consultant',
            'pricing_model' => 'fixed',
            'price' => 4500,
            'duration_minutes' => 60,
            'delivery_methods' => ['online'],
            'is_active' => false,
        ]);

        // Filter by category
        $resCat = $this->getJson('/api/astrologer/services?category=pandit_purohit');
        $resCat->assertOk();
        $this->assertCount(1, $resCat->json('data'));
        $this->assertEquals('Shri Satyanarayan Katha', $resCat->json('data.0.name'));

        // Filter by active status
        $resActive = $this->getJson('/api/astrologer/services?is_active=false');
        $resActive->assertOk();
        $this->assertCount(1, $resActive->json('data'));
        $this->assertEquals('Vastu Floor Plan Audit', $resActive->json('data.0.name'));

        // Search by keyword
        $resSearch = $this->getJson('/api/astrologer/services?search=Floor');
        $resSearch->assertOk();
        $this->assertCount(1, $resSearch->json('data'));
        $this->assertEquals('Vastu Floor Plan Audit', $resSearch->json('data.0.name'));
    }

    public function test_toggle_active_status(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        $service = ProfessionalService::create([
            'admin_id' => $this->practitionerA->id,
            'name' => 'Name Numerology',
            'category' => 'numerologist',
            'pricing_model' => 'fixed',
            'price' => 1200,
            'duration_minutes' => 30,
            'delivery_methods' => ['online'],
            'is_active' => true,
        ]);

        $res = $this->postJson("/api/astrologer/services/{$service->id}/toggle-active");
        $res->assertOk();
        $this->assertFalse($res->json('data.is_active'));
        $this->assertFalse($service->fresh()->is_active);

        // Toggle back
        $res2 = $this->postJson("/api/astrologer/services/{$service->id}/toggle-active");
        $res2->assertOk();
        $this->assertTrue($res2->json('data.is_active'));
        $this->assertTrue($service->fresh()->is_active);
    }

    public function test_suggested_service_templates_and_summary(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        // Templates endpoint
        $resTemplates = $this->getJson('/api/astrologer/services/templates');
        $resTemplates->assertOk();
        $this->assertArrayHasKey('astrologer', $resTemplates->json('data'));
        $this->assertArrayHasKey('pandit_purohit', $resTemplates->json('data'));
        $this->assertArrayHasKey('vastu_consultant', $resTemplates->json('data'));

        // Summary endpoint
        ProfessionalService::create([
            'admin_id' => $this->practitionerA->id,
            'name' => 'Test S1',
            'category' => 'astrologer',
            'pricing_model' => 'fixed',
            'price' => 500,
            'duration_minutes' => 30,
            'delivery_methods' => ['online'],
            'is_active' => true,
        ]);

        $resSummary = $this->getJson('/api/astrologer/services/summary');
        $resSummary->assertOk();
        $this->assertEquals(1, $resSummary->json('data.total_services'));
        $this->assertEquals(1, $resSummary->json('data.active_services'));
        $this->assertEquals(0, $resSummary->json('data.inactive_services'));
    }

    public function test_appointment_creation_with_service_linkage_preserves_historical_snapshot(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        $service = ProfessionalService::create([
            'admin_id' => $this->practitionerA->id,
            'name' => 'Special Muhurat Consultation',
            'category' => 'astrologer',
            'pricing_model' => 'fixed',
            'price' => 850,
            'currency' => 'INR',
            'duration_minutes' => 45,
            'delivery_methods' => ['online', 'phone_call'],
            'is_active' => true,
        ]);

        $startAt = Carbon::now()->addDays(2)->setHour(11)->setMinute(0)->setSecond(0);
        $endAt = $startAt->copy()->addMinutes(45);

        $res = $this->postJson('/api/astrologer/appointments', [
            'title' => 'Muhurat with Vikas',
            'appointment_type' => 'online',
            'jajman_id' => $this->jajmanA->id,
            'service_id' => $service->id,
            'start_at' => $startAt->toIso8601String(),
            'end_at' => $endAt->toIso8601String(),
            'override_availability' => true,
        ]);

        $res->assertCreated();
        $apptId = $res->json('data.id');

        $appointment = Appointment::find($apptId);
        $this->assertNotNull($appointment);
        $this->assertEquals($service->id, $appointment->service_id);
        $this->assertEquals('Special Muhurat Consultation', $appointment->service_name_snapshot);
        $this->assertEquals(850.0, $appointment->agreed_price_snapshot);
        $this->assertEquals('INR', $appointment->currency_snapshot);
        $this->assertEquals(45, $appointment->duration_minutes_snapshot);
        $this->assertEquals('online', $appointment->delivery_method_snapshot);

        // Verify changing service price later does NOT alter the historical appointment snapshot!
        $service->update(['price' => 1500]);

        $appointmentFresh = Appointment::find($apptId);
        $this->assertEquals(850.0, $appointmentFresh->agreed_price_snapshot, 'Historical appointment agreed price was preserved!');
        $this->assertEquals('Special Muhurat Consultation', $appointmentFresh->service_name_snapshot);
    }

    public function test_consultation_recording_with_service_linkage(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        $service = ProfessionalService::create([
            'admin_id' => $this->practitionerA->id,
            'name' => 'Rudrabhishek Hawan',
            'category' => 'pandit_purohit',
            'pricing_model' => 'fixed',
            'price' => 3500,
            'duration_minutes' => 120,
            'delivery_methods' => ['home_visit'],
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/astrologer/consultations', [
            'client_name' => 'Vikas Gupta',
            'jajman_id' => $this->jajmanA->id,
            'service_id' => $service->id,
            'type' => 'ritual_puja',
            'status' => 'completed',
            'fee_charged' => 3500,
            'duration_minutes' => 120,
            'summary' => 'Performed Shukla Yajurveda Rudra abhishek successfully.',
        ]);

        $res->assertCreated();
        $consultationId = $res->json('data.id');

        $consultation = Consultation::find($consultationId);
        $this->assertNotNull($consultation);
        $this->assertEquals($service->id, $consultation->service_id);
        $this->assertEquals('Rudrabhishek Hawan', $consultation->service_name_snapshot);
        $this->assertEquals(3500.0, $consultation->fee_charged);
    }

    public function test_safe_deletion_archives_service_when_used_in_appointments(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        $service = ProfessionalService::create([
            'admin_id' => $this->practitionerA->id,
            'name' => 'Satyanarayan Vidhi',
            'category' => 'pandit_purohit',
            'pricing_model' => 'fixed',
            'price' => 2500,
            'duration_minutes' => 90,
            'delivery_methods' => ['home_visit'],
            'is_active' => true,
        ]);

        // Link appointment
        $startAt = Carbon::now()->addDays(3)->setHour(10)->setMinute(0)->setSecond(0);
        Appointment::create([
            'admin_id' => $this->practitionerA->id,
            'jajman_id' => $this->jajmanA->id,
            'service_id' => $service->id,
            'service_name_snapshot' => $service->name,
            'agreed_price_snapshot' => $service->price,
            'title' => 'Puja Booking',
            'appointment_type' => 'puja',
            'booking_source' => 'professional_created',
            'status' => 'confirmed',
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addMinutes(90),
            'timezone' => 'Asia/Kolkata',
        ]);

        // Call delete endpoint
        $resDelete = $this->deleteJson("/api/astrologer/services/{$service->id}");
        $resDelete->assertOk();
        $this->assertStringContainsString('safely archived', $resDelete->json('message'));

        // Verify soft deleted in database (not destroyed)
        $this->assertSoftDeleted('professional_services', ['id' => $service->id]);

        // Verify service no longer appears in active catalogue list
        $resList = $this->getJson('/api/astrologer/services');
        $resList->assertOk();
        $this->assertCount(0, $resList->json('data'));
    }
}
