<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Jajman;
use App\Models\ProfessionalService;
use App\Models\ProfessionalTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfessionalPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $practitionerA;
    protected Admin $practitionerB;
    protected Jajman $jajmanA;
    protected Appointment $appointmentA;
    protected Consultation $consultationA;

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

        $this->appointmentA = Appointment::create([
            'admin_id' => $this->practitionerA->id,
            'jajman_id' => $this->jajmanA->id,
            'title' => 'Satyanarayan Katha Puja',
            'appointment_type' => 'puja',
            'booking_source' => 'professional_created',
            'status' => 'confirmed',
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHours(2),
            'agreed_price_snapshot' => 3100.0,
            'payment_status' => 'unpaid',
        ]);

        $this->consultationA = Consultation::create([
            'admin_id' => $this->practitionerA->id,
            'jajman_id' => $this->jajmanA->id,
            'client_name' => 'Vikas Gupta',
            'type' => 'in_person',
            'status' => 'completed',
            'consultation_date' => Carbon::now()->subDays(1),
            'fee_charged' => 1500.0,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/astrologer/payments')->assertUnauthorized();
        $this->postJson('/api/astrologer/payments', [])->assertUnauthorized();
        $this->getJson('/api/astrologer/payments/summary')->assertUnauthorized();
        $this->getJson("/api/astrologer/jajmans/{$this->jajmanA->id}/financial-summary")->assertUnauthorized();
    }

    public function test_practitioner_can_record_payments_across_methods(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        // 1. Cash Payment
        $resCash = $this->postJson('/api/astrologer/payments', [
            'amount' => 500,
            'jajman_id' => $this->jajmanA->id,
            'appointment_id' => $this->appointmentA->id,
            'transaction_type' => 'appointment_advance',
            'payment_method' => 'cash',
            'notes' => 'Received cash advance during visit',
        ]);
        $resCash->assertCreated();
        $this->assertDatabaseHas('professional_transactions', [
            'admin_id' => $this->practitionerA->id,
            'amount' => 500,
            'payment_method' => 'cash',
            'transaction_type' => 'appointment_advance',
        ]);

        // 2. UPI Payment with UTR reference
        $resUpi = $this->postJson('/api/astrologer/payments', [
            'amount' => 1500,
            'jajman_id' => $this->jajmanA->id,
            'consultation_id' => $this->consultationA->id,
            'transaction_type' => 'consultation_payment',
            'payment_method' => 'upi',
            'reference_id' => 'UPI/409823489234/REF',
            'notes' => 'Paid via GPay to QR code',
        ]);
        $resUpi->assertCreated();
        $this->assertDatabaseHas('professional_transactions', [
            'admin_id' => $this->practitionerA->id,
            'amount' => 1500,
            'payment_method' => 'upi',
            'reference_id' => 'UPI/409823489234/REF',
        ]);

        // Verify consultation payment status updated to 'paid'
        $this->assertEquals('paid', $this->consultationA->fresh()->payment_status);
    }

    public function test_tenant_isolation_prevents_unauthorized_access(): void
    {
        $txnA = ProfessionalTransaction::create([
            'admin_id' => $this->practitionerA->id,
            'jajman_id' => $this->jajmanA->id,
            'amount' => 2000,
            'transaction_type' => 'service_payment',
            'payment_method' => 'cash',
            'payment_status' => 'completed',
            'transaction_at' => now(),
        ]);

        // Practitioner B attempts to view or refund Practitioner A's transaction
        $this->actingAs($this->practitionerB, 'sanctum');

        $this->getJson("/api/astrologer/payments/{$txnA->id}")
            ->assertNotFound();

        $this->postJson("/api/astrologer/payments/{$txnA->id}/refund", [
            'amount' => 500,
            'reason' => 'Unauthorized refund attempt',
        ])->assertNotFound();

        // Practitioner B attempts to record payment for Practitioner A's Jajman
        $this->postJson('/api/astrologer/payments', [
            'amount' => 1000,
            'jajman_id' => $this->jajmanA->id,
            'transaction_type' => 'general_payment',
            'payment_method' => 'cash',
        ])->assertNotFound();
    }

    public function test_partial_payments_advance_and_balance_aggregation(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        // Appointment obligation = ₹3,100
        // Step 1: Advance payment of ₹1,000
        $this->postJson('/api/astrologer/payments', [
            'amount' => 1000,
            'jajman_id' => $this->jajmanA->id,
            'appointment_id' => $this->appointmentA->id,
            'transaction_type' => 'appointment_advance',
            'payment_method' => 'bank_transfer',
            'reference_id' => 'IMPS9823472',
        ])->assertCreated();

        $financials1 = $this->appointmentA->fresh()->calculateFinancialStatus();
        $this->assertEquals(3100.0, $financials1['total_agreed']);
        $this->assertEquals(1000.0, $financials1['total_received']);
        $this->assertEquals(2100.0, $financials1['outstanding_balance']);
        $this->assertEquals('partially_paid', $financials1['payment_status']);
        $this->assertEquals('partially_paid', $this->appointmentA->fresh()->payment_status);

        // Step 2: Second installment of ₹1,500
        $this->postJson('/api/astrologer/payments', [
            'amount' => 1500,
            'jajman_id' => $this->jajmanA->id,
            'appointment_id' => $this->appointmentA->id,
            'transaction_type' => 'service_payment',
            'payment_method' => 'upi',
        ])->assertCreated();

        $financials2 = $this->appointmentA->fresh()->calculateFinancialStatus();
        $this->assertEquals(2500.0, $financials2['total_received']);
        $this->assertEquals(600.0, $financials2['outstanding_balance']);
        $this->assertEquals('partially_paid', $financials2['payment_status']);

        // Step 3: Final settlement of ₹600
        $this->postJson('/api/astrologer/payments', [
            'amount' => 600,
            'jajman_id' => $this->jajmanA->id,
            'appointment_id' => $this->appointmentA->id,
            'transaction_type' => 'service_payment',
            'payment_method' => 'cash',
        ])->assertCreated();

        $financials3 = $this->appointmentA->fresh()->calculateFinancialStatus();
        $this->assertEquals(3100.0, $financials3['total_received']);
        $this->assertEquals(0.0, $financials3['outstanding_balance']);
        $this->assertEquals('paid', $financials3['payment_status']);
        $this->assertEquals('paid', $this->appointmentA->fresh()->payment_status);
    }

    public function test_recording_full_and_partial_refunds(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        $payment = ProfessionalTransaction::create([
            'admin_id' => $this->practitionerA->id,
            'jajman_id' => $this->jajmanA->id,
            'appointment_id' => $this->appointmentA->id,
            'amount' => 2000,
            'transaction_type' => 'service_payment',
            'payment_method' => 'upi',
            'payment_status' => 'completed',
            'transaction_at' => now(),
        ]);

        $this->assertEquals(2000.0, $payment->refundableBalance());

        // 1. Partial refund of ₹500
        $resPartial = $this->postJson("/api/astrologer/payments/{$payment->id}/refund", [
            'amount' => 500,
            'reason' => 'Client requested partial refund due to puja reschedule',
        ]);
        $resPartial->assertCreated();

        $paymentFresh = $payment->fresh();
        $this->assertEquals('partially_refunded', $paymentFresh->payment_status);
        $this->assertEquals(1500.0, $paymentFresh->refundableBalance());
        $this->assertEquals(500.0, $paymentFresh->totalRefunded());

        // 2. Reject refund exceeding remaining refundable balance (e.g. ₹1600 > ₹1500)
        $resExceed = $this->postJson("/api/astrologer/payments/{$payment->id}/refund", [
            'amount' => 1600,
            'reason' => 'Excess refund attempt',
        ]);
        $resExceed->assertStatus(422);
        $this->assertStringContainsString('exceeds available refundable balance', $resExceed->json('message'));

        // 3. Complete remaining refund of ₹1500
        $resFinal = $this->postJson("/api/astrologer/payments/{$payment->id}/refund", [
            'amount' => 1500,
            'reason' => 'Settled remaining refund',
        ]);
        $resFinal->assertCreated();

        $paymentFinal = $payment->fresh();
        $this->assertEquals('refunded', $paymentFinal->payment_status);
        $this->assertEquals(0.0, $paymentFinal->refundableBalance());
        $this->assertEquals(2000.0, $paymentFinal->totalRefunded());

        // 4. Reject any further refund once fully refunded
        $resPost = $this->postJson("/api/astrologer/payments/{$payment->id}/refund", [
            'amount' => 100,
            'reason' => 'Extra refund after full refund',
        ]);
        $resPost->assertStatus(422);
    }

    public function test_payments_summary_and_period_filtering(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        // Completed payment today: ₹1,500
        ProfessionalTransaction::create([
            'admin_id' => $this->practitionerA->id,
            'jajman_id' => $this->jajmanA->id,
            'amount' => 1500,
            'transaction_type' => 'service_payment',
            'payment_method' => 'upi',
            'payment_status' => 'completed',
            'transaction_at' => Carbon::now(),
        ]);

        // Completed refund today: ₹300
        ProfessionalTransaction::create([
            'admin_id' => $this->practitionerA->id,
            'jajman_id' => $this->jajmanA->id,
            'amount' => 300,
            'transaction_type' => 'refund',
            'payment_method' => 'upi',
            'payment_status' => 'completed',
            'transaction_at' => Carbon::now(),
        ]);

        // Pending payment (must NOT be counted in received earnings)
        ProfessionalTransaction::create([
            'admin_id' => $this->practitionerA->id,
            'jajman_id' => $this->jajmanA->id,
            'amount' => 2500,
            'transaction_type' => 'general_payment',
            'payment_method' => 'cheque',
            'payment_status' => 'pending',
            'transaction_at' => Carbon::now(),
        ]);

        $resToday = $this->getJson('/api/astrologer/payments/summary?period=today');
        $resToday->assertOk();
        $this->assertEquals(1500.0, $resToday->json('data.total_received'));
        $this->assertEquals(300.0, $resToday->json('data.total_refunded'));
        $this->assertEquals(1200.0, $resToday->json('data.net_received'));
        $this->assertEquals(2500.0, $resToday->json('data.total_pending'));
        $this->assertGreaterThan(0, $resToday->json('data.total_outstanding'));
    }

    public function test_jajman_and_appointment_financial_summary_endpoints(): void
    {
        $this->actingAs($this->practitionerA, 'sanctum');

        // Record ₹2,000 payment for appointment
        $this->postJson('/api/astrologer/payments', [
            'amount' => 2000,
            'jajman_id' => $this->jajmanA->id,
            'appointment_id' => $this->appointmentA->id,
            'transaction_type' => 'service_payment',
            'payment_method' => 'cash',
        ])->assertCreated();

        // 1. Appointment financial summary
        $resAppt = $this->getJson("/api/astrologer/appointments/{$this->appointmentA->id}/financial-summary");
        $resAppt->assertOk();
        $this->assertEquals(3100.0, $resAppt->json('data.total_agreed'));
        $this->assertEquals(2000.0, $resAppt->json('data.total_received'));
        $this->assertEquals(1100.0, $resAppt->json('data.outstanding_balance'));
        $this->assertEquals('partially_paid', $resAppt->json('data.payment_status'));

        // 2. Consultation financial summary
        $resCons = $this->getJson("/api/astrologer/consultations/{$this->consultationA->id}/financial-summary");
        $resCons->assertOk();
        $this->assertEquals(1500.0, $resCons->json('data.fee_charged'));
        $this->assertEquals(0.0, $resCons->json('data.total_received'));
        $this->assertEquals('unpaid', $resCons->json('data.payment_status'));

        // 3. Jajman financial summary
        $resJajman = $this->getJson("/api/astrologer/jajmans/{$this->jajmanA->id}/financial-summary");
        $resJajman->assertOk();
        // Total charged = 3100 (appt) + 1500 (consultation) = 4600
        $this->assertEquals(4600.0, $resJajman->json('data.total_charged'));
        $this->assertEquals(2000.0, $resJajman->json('data.total_received'));
        $this->assertEquals(2600.0, $resJajman->json('data.outstanding_balance'));
    }
}
