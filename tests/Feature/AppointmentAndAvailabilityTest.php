<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Jajman;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentAndAvailabilityTest extends TestCase
{
    use DatabaseMigrations;

    public function test_full_appointment_and_availability_lifecycle_with_conflict_prevention_and_isolation()
    {
        // 1. Role setup
        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        // 2. Create Practitioner A
        $adminA = Admin::create([
            'name' => 'Acharya Raman',
            'email' => 'raman_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
        ]);
        $adminA->assignRole('Astrologer');

        // 3. Create Practitioner B
        $adminB = Admin::create([
            'name' => 'Pandit Dev',
            'email' => 'dev_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
        ]);
        $adminB->assignRole('Astrologer');

        // 4. Configure weekly availability for Practitioner A
        // Monday (1): 09:00 - 13:00 and 14:00 - 18:00 (Break: 13:00 - 14:00)
        $availResponse = $this->actingAs($adminA, 'sanctum')->putJson('/api/astrologer/availability', [
            'timezone' => 'Asia/Kolkata',
            'days' => [
                [
                    'day_of_week' => 1, // Monday
                    'is_enabled' => true,
                    'slots' => [
                        ['start' => '09:00', 'end' => '13:00'],
                        ['start' => '14:00', 'end' => '18:00'],
                    ],
                ],
                [
                    'day_of_week' => 0, // Sunday
                    'is_enabled' => false,
                    'slots' => [],
                ],
            ],
        ]);
        $availResponse->assertStatus(200);

        // 5. Practitioner A creates Jajman
        $jajmanA = Jajman::create([
            'name' => 'Kavita Joshi',
            'phone' => '9811223344',
            'city' => 'Jaipur',
        ]);
        $jajmanA->professionals()->attach($adminA->id, ['status' => 'active', 'source' => 'manual']);

        // Find next Monday date
        $nextMonday = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY);
        $validStart = $nextMonday->copy()->setTime(10, 0)->toIso8601String(); // 10:00 AM IST
        $validEnd = $nextMonday->copy()->setTime(11, 0)->toIso8601String();   // 11:00 AM IST

        // 6. Practitioner A creates valid appointment
        $createResponse = $this->actingAs($adminA, 'sanctum')->postJson('/api/astrologer/appointments', [
            'title' => 'Kundali Milan & Career Consultation',
            'appointment_type' => 'online',
            'start_at' => $validStart,
            'end_at' => $validEnd,
            'timezone' => 'Asia/Kolkata',
            'jajman_id' => $jajmanA->id,
            'meeting_link' => 'https://meet.jit.si/astroraman101',
        ]);
        $createResponse->assertStatus(201);
        $appointmentId = $createResponse->json('data.id');
        $this->assertEquals('confirmed', $createResponse->json('data.status'));

        // 7. Prevent overlapping double booking: Try booking 10:30 AM - 11:30 AM
        $overlapStart = $nextMonday->copy()->setTime(10, 30)->toIso8601String();
        $overlapEnd = $nextMonday->copy()->setTime(11, 30)->toIso8601String();

        $conflictResponse = $this->actingAs($adminA, 'sanctum')->postJson('/api/astrologer/appointments', [
            'title' => 'Conflicting Puja Booking',
            'appointment_type' => 'in_person',
            'start_at' => $overlapStart,
            'end_at' => $overlapEnd,
            'timezone' => 'Asia/Kolkata',
        ]);
        $conflictResponse->assertStatus(422);
        $conflictResponse->assertJson(['conflict' => true]);

        // 8. Prevent booking during lunch break (13:15 - 13:45) without override
        $breakStart = $nextMonday->copy()->setTime(13, 15)->toIso8601String();
        $breakEnd = $nextMonday->copy()->setTime(13, 45)->toIso8601String();

        $breakResponse = $this->actingAs($adminA, 'sanctum')->postJson('/api/astrologer/appointments', [
            'title' => 'Lunch Break Attempt',
            'appointment_type' => 'phone_call',
            'start_at' => $breakStart,
            'end_at' => $breakEnd,
            'timezone' => 'Asia/Kolkata',
        ]);
        $breakResponse->assertStatus(422);
        $breakResponse->assertJson(['availability_conflict' => true, 'can_override' => true]);

        // 9. With explicit override_availability = true, professional can book
        $overrideResponse = $this->actingAs($adminA, 'sanctum')->postJson('/api/astrologer/appointments', [
            'title' => 'Emergency Lunch Consultation',
            'appointment_type' => 'phone_call',
            'start_at' => $breakStart,
            'end_at' => $breakEnd,
            'timezone' => 'Asia/Kolkata',
            'override_availability' => true,
        ]);
        $overrideResponse->assertStatus(201);

        // 10. Reschedule appointment
        $newStart = $nextMonday->copy()->setTime(15, 0)->toIso8601String();
        $newEnd = $nextMonday->copy()->setTime(16, 0)->toIso8601String();

        $rescheduleResponse = $this->actingAs($adminA, 'sanctum')->postJson("/api/astrologer/appointments/{$appointmentId}/reschedule", [
            'start_at' => $newStart,
            'end_at' => $newEnd,
            'timezone' => 'Asia/Kolkata',
        ]);
        $rescheduleResponse->assertStatus(200);

        // 11. Calendar endpoint returns grouped appointments
        $calResponse = $this->actingAs($adminA, 'sanctum')->getJson('/api/astrologer/calendar?timezone=Asia/Kolkata');
        $calResponse->assertStatus(200);
        $calDays = $calResponse->json('days');
        $this->assertNotEmpty($calDays);

        // 12. Complete appointment and auto-generate linked Consultation
        $completeResponse = $this->actingAs($adminA, 'sanctum')->postJson("/api/astrologer/appointments/{$appointmentId}/complete", [
            'create_consultation' => true,
            'fee_charged' => 1500.00,
            'summary' => 'Analyzed chart; recommended Pukhraj stone.',
        ]);
        $completeResponse->assertStatus(200);
        $this->assertEquals('completed', $completeResponse->json('data.status'));
        $this->assertNotNull($completeResponse->json('data.consultation_id'));

        $this->assertDatabaseHas('consultations', [
            'admin_id' => $adminA->id,
            'jajman_id' => $jajmanA->id,
            'fee_charged' => 1500.00,
        ]);

        // 13. Practitioner B isolation: Practitioner B CANNOT view or manage Practitioner A's appointment
        $unauthView = $this->actingAs($adminB, 'sanctum')->getJson("/api/astrologer/appointments/{$appointmentId}");
        $unauthView->assertStatus(404);

        $unauthCancel = $this->actingAs($adminB, 'sanctum')->postJson("/api/astrologer/appointments/{$appointmentId}/cancel");
        $unauthCancel->assertStatus(404);

        $listB = $this->actingAs($adminB, 'sanctum')->getJson('/api/astrologer/appointments');
        $listB->assertStatus(200);
        $ids = collect($listB->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($appointmentId));
    }
}
