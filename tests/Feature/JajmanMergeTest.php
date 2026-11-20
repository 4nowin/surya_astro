<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\JajmanFamilyRelationship;
use App\Models\JajmanMergeLog;
use App\Models\JajmanNote;
use App\Models\ProfessionalInvoice;
use App\Models\ProfessionalTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JajmanMergeTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $practitioner;
    protected Admin $otherPractitioner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Astrologer']);

        $this->practitioner = Admin::create([
            'name' => 'Pandit Dev Sharma',
            'email' => 'dev@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->practitioner->assignRole('Astrologer');

        $this->otherPractitioner = Admin::create([
            'name' => 'Pandit Ramesh Shastri',
            'email' => 'ramesh@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->otherPractitioner->assignRole('Astrologer');
    }

    public function test_astrologer_can_fetch_comparison_for_two_jajmans(): void
    {
        $jajmanA = Jajman::create([
            'name' => 'Amitabh Bachchan',
            'phone' => '+91 98765 00001',
            'email' => 'amitabh@example.com',
            'city' => 'Mumbai',
        ]);
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $jajmanA->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jajmanB = Jajman::create([
            'name' => 'Amitabh B',
            'phone' => '+91 98765 00001',
            'city' => 'Allahabad',
        ]);
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $jajmanB->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add child records for jajmanB
        JajmanBirthDetail::create([
            'jajman_id' => $jajmanB->id,
            'date_of_birth' => '1942-10-11',
            'birth_time' => '16:00:00',
            'birth_place' => 'Allahabad',
            'is_primary' => true,
        ]);

        JajmanNote::create([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $jajmanB->id,
            'content' => 'Discussed career and longevity.',
        ]);

        Consultation::create([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $jajmanB->id,
            'client_name' => 'Amitabh B',
            'type' => 'chat',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->practitioner, 'sanctum')
            ->getJson("/api/astrologer/jajmans/merge/compare?jajman_a={$jajmanA->id}&jajman_b={$jajmanB->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.jajman_a.id', $jajmanA->id)
            ->assertJsonPath('data.jajman_a.name', 'Amitabh Bachchan')
            ->assertJsonPath('data.jajman_b.id', $jajmanB->id)
            ->assertJsonPath('data.jajman_b.counts.birth_details', 1)
            ->assertJsonPath('data.jajman_b.counts.notes', 1)
            ->assertJsonPath('data.jajman_b.counts.consultations', 1);
    }

    public function test_cannot_compare_jajman_with_itself(): void
    {
        $jajman = Jajman::create([
            'name' => 'Single Record',
            'phone' => '+91 99999 11111',
        ]);
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $jajman->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->practitioner, 'sanctum')
            ->getJson("/api/astrologer/jajmans/merge/compare?jajman_a={$jajman->id}&jajman_b={$jajman->id}");

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_cannot_compare_or_merge_unauthorized_jajman(): void
    {
        $jajmanA = Jajman::create(['name' => 'Mine']);
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $jajmanA->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jajmanOther = Jajman::create(['name' => 'Someone Elses']);
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->otherPractitioner->id,
            'jajman_id' => $jajmanOther->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $compareResponse = $this->actingAs($this->practitioner, 'sanctum')
            ->getJson("/api/astrologer/jajmans/merge/compare?jajman_a={$jajmanA->id}&jajman_b={$jajmanOther->id}");

        $compareResponse->assertStatus(403);

        $mergeResponse = $this->actingAs($this->practitioner, 'sanctum')
            ->postJson('/api/astrologer/jajmans/merge', [
                'primary_jajman_id' => $jajmanA->id,
                'secondary_jajman_id' => $jajmanOther->id,
            ]);

        $mergeResponse->assertStatus(403);
    }

    public function test_successful_merge_consolidates_all_child_records_and_soft_deletes_secondary(): void
    {
        // Primary: Has name and city, but missing phone and email
        $primary = Jajman::create([
            'name' => 'Sunita Sharma',
            'city' => 'Delhi',
            'phone' => null,
            'email' => null,
        ]);
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $primary->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Secondary: Has phone, email, and DOB
        $secondary = Jajman::create([
            'name' => 'Sunita S.',
            'city' => 'New Delhi', // primary's city should NOT be overwritten
            'phone' => '+91 98111 22233',
            'email' => 'sunita@example.com',
            'date_of_birth' => '1985-05-15',
        ]);
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $secondary->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Child records on secondary
        $birthDetail = JajmanBirthDetail::create([
            'jajman_id' => $secondary->id,
            'date_of_birth' => '1985-05-15',
            'birth_time' => '10:15:00',
            'birth_place' => 'Delhi',
            'is_primary' => true,
        ]);

        $note = JajmanNote::create([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $secondary->id,
            'content' => 'Secondary note to preserve.',
        ]);

        $consultation = Consultation::create([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $secondary->id,
            'client_name' => 'Sunita S.',
            'type' => 'chat',
            'status' => 'completed',
        ]);

        $appointment = Appointment::create([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $secondary->id,
            'title' => 'Vedic Astrology Consultation',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addMinutes(30),
            'status' => 'confirmed',
        ]);

        $transaction = ProfessionalTransaction::create([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $secondary->id,
            'transaction_type' => 'service_payment',
            'payment_method' => 'upi',
            'amount' => 500.00,
            'transaction_at' => now(),
            'payment_status' => 'completed',
        ]);

        $invoice = ProfessionalInvoice::create([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $secondary->id,
            'invoice_number' => 'INV-TEST-001',
            'invoice_date' => now()->format('Y-m-d'),
            'grand_total' => 500.00,
            'status' => 'paid',
        ]);

        // Related third Jajman for family relationships
        $relative = Jajman::create(['name' => 'Rahul Sharma']);
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $relative->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $familyRel = JajmanFamilyRelationship::create([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $secondary->id,
            'related_jajman_id' => $relative->id,
            'relationship_type' => 'son',
        ]);

        // Execute merge
        $response = $this->actingAs($this->practitioner, 'sanctum')
            ->postJson('/api/astrologer/jajmans/merge', [
                'primary_jajman_id' => $primary->id,
                'secondary_jajman_id' => $secondary->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Jajman records merged successfully.')
            ->assertJsonPath('data.consolidated_counts.birth_details_moved', 1)
            ->assertJsonPath('data.consolidated_counts.notes_moved', 1)
            ->assertJsonPath('data.consolidated_counts.family_relationships_moved', 1)
            ->assertJsonPath('data.consolidated_counts.consultations_moved', 1)
            ->assertJsonPath('data.consolidated_counts.appointments_moved', 1)
            ->assertJsonPath('data.consolidated_counts.transactions_moved', 1)
            ->assertJsonPath('data.consolidated_counts.invoices_moved', 1);

        // Verify primary was backfilled without overwriting existing non-empty values
        $primary->refresh();
        $this->assertEquals('Sunita Sharma', $primary->name);
        $this->assertEquals('Delhi', $primary->city); // Not overwritten by 'New Delhi'
        $this->assertEquals('+91 98111 22233', $primary->phone); // Backfilled
        $this->assertEquals('sunita@example.com', $primary->email); // Backfilled
        $this->assertEquals('1985-05-15', $primary->date_of_birth->format('Y-m-d')); // Backfilled

        // Verify child records now point to primary
        $this->assertEquals($primary->id, $birthDetail->fresh()->jajman_id);
        $this->assertEquals($primary->id, $note->fresh()->jajman_id);
        $this->assertEquals($primary->id, $consultation->fresh()->jajman_id);
        $this->assertEquals($primary->id, $appointment->fresh()->jajman_id);
        $this->assertEquals($primary->id, $transaction->fresh()->jajman_id);
        $this->assertEquals($primary->id, $invoice->fresh()->jajman_id);
        $this->assertEquals($primary->id, $familyRel->fresh()->jajman_id);

        // Verify secondary is marked as merged and soft-deleted
        $secondaryRefreshed = Jajman::withTrashed()->find($secondary->id);
        $this->assertNotNull($secondaryRefreshed->deleted_at);
        $this->assertEquals($primary->id, $secondaryRefreshed->merged_into_jajman_id);
        $this->assertNotNull($secondaryRefreshed->merged_at);

        // Verify secondary pivot status is 'merged'
        $pivot = DB::table('professional_jajmans')
            ->where('admin_id', $this->practitioner->id)
            ->where('jajman_id', $secondary->id)
            ->first();
        $this->assertEquals('merged', $pivot->status);

        // Verify audit log exists
        $mergeLog = JajmanMergeLog::where('primary_jajman_id', $primary->id)
            ->where('secondary_jajman_id', $secondary->id)
            ->first();
        $this->assertNotNull($mergeLog);
        $this->assertEquals($this->practitioner->id, $mergeLog->admin_id);
        $this->assertEquals(1, $mergeLog->consolidated_counts['notes_moved']);
    }

    public function test_merging_cleans_duplicate_candidates_flags(): void
    {
        $primary = Jajman::create(['name' => 'Primary']);
        $secondary = Jajman::create(['name' => 'Secondary']);

        // Set up duplicate candidate flag on primary pointing to secondary
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $primary->id,
            'status' => 'active',
            'has_possible_duplicates' => true,
            'duplicate_candidates' => json_encode([$secondary->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $secondary->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->practitioner, 'sanctum')
            ->postJson('/api/astrologer/jajmans/merge', [
                'primary_jajman_id' => $primary->id,
                'secondary_jajman_id' => $secondary->id,
            ]);

        $response->assertStatus(200);

        // After merge, primary should have no more duplicate candidates and flag is false
        $pivot = DB::table('professional_jajmans')
            ->where('admin_id', $this->practitioner->id)
            ->where('jajman_id', $primary->id)
            ->first();

        $this->assertEquals(0, (int)$pivot->has_possible_duplicates);
        $this->assertNull($pivot->duplicate_candidates);
    }

    public function test_cannot_merge_jajman_with_itself(): void
    {
        $jajman = Jajman::create(['name' => 'Single Record']);
        DB::table('professional_jajmans')->insert([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $jajman->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->practitioner, 'sanctum')
            ->postJson('/api/astrologer/jajmans/merge', [
                'primary_jajman_id' => $jajman->id,
                'secondary_jajman_id' => $jajman->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }
}
