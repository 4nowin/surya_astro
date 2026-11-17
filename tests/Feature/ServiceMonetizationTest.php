<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Astrologer;
use App\Models\ProfessionalService;
use App\Models\ProfessionalServiceOrder;
use App\Models\ProfessionalTransaction;
use App\Models\User;
use App\Models\UserWalletTransaction;
use App\Services\Services\ServiceOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceMonetizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Admin $admin;
    protected Astrologer $astrologer;
    protected ProfessionalService $appointmentService;
    protected ProfessionalService $deliverableService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        // Astrologer
        $this->admin = Admin::create([
            'name' => 'Pt. Vidyadhar Shastri',
            'email' => 'vidyadhar@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $this->admin->assignRole('Astrologer');

        $this->astrologer = Astrologer::create([
            'admin_id' => $this->admin->id,
            'name' => 'Pt. Vidyadhar Shastri',
            'language' => 'en',
            'astrologer_language' => 'Hindi, Sanskrit',
            'expertise' => 'Vedic Astrology, Vastu',
            'experience' => '20 years',
            'price' => 100.00,
            'status' => 'online',
            'active' => 1,
        ]);

        // Scheduled Appointment Service (Base: ₹500, Customer pays: ₹600)
        $this->appointmentService = ProfessionalService::create([
            'admin_id' => $this->admin->id,
            'name' => '1-on-1 Kundli Video Consultation',
            'description' => 'Comprehensive 30 min consultation on birth chart and life questions.',
            'category' => 'kundli_consultation',
            'pricing_model' => 'fixed',
            'price' => 500.00, // Base price B
            'currency' => 'INR',
            'duration_minutes' => 30,
            'delivery_methods' => ['online'],
            'is_active' => true,
            'is_public' => true,
        ]);

        // Asynchronous Deliverable Service (Base: ₹1000, Custom SLA: 5 days, Customer pays: ₹1200)
        $this->deliverableService = ProfessionalService::create([
            'admin_id' => $this->admin->id,
            'name' => 'Vastu Floor Plan Detailed Audit',
            'description' => 'Detailed 10-page residential Vastu audit and correction guide.',
            'category' => 'vastu',
            'pricing_model' => 'fixed',
            'price' => 1000.00, // Base price B
            'currency' => 'INR',
            'duration_minutes' => 0,
            'delivery_timeframe_days' => 5, // Custom SLA
            'delivery_methods' => ['online'],
            'is_active' => true,
            'is_public' => true,
        ]);

        // Consumer User with ₹2000 initial wallet balance
        $this->user = User::create([
            'name' => 'Rahul Sharma',
            'phone' => '9876543210',
            'email' => 'rahul@example.com',
            'wallet_balance' => 2000.00,
            'password' => bcrypt('secret123'),
        ]);
    }

    /**
     * Test 1: Service catalogue transparent pricing (Base + 20% platform markup).
     */
    public function test_services_catalogue_returns_transparent_20_percent_markup(): void
    {
        $response = $this->getJson("/api/astrologers/{$this->astrologer->id}/services");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $services = $response->json('data');
        $this->assertCount(2, $services);

        // Appointment Service: Base ₹500 + Markup ₹100 = Customer ₹600
        $appt = collect($services)->firstWhere('id', $this->appointmentService->id);
        $this->assertEquals(500.00, $appt['base_price']);
        $this->assertEquals(100.00, $appt['platform_markup']);
        $this->assertEquals(600.00, $appt['customer_price']);

        // Deliverable Service: Base ₹1000 + Markup ₹200 = Customer ₹1200, 5 days SLA
        $deliv = collect($services)->firstWhere('id', $this->deliverableService->id);
        $this->assertEquals(1000.00, $deliv['base_price']);
        $this->assertEquals(200.00, $deliv['platform_markup']);
        $this->assertEquals(1200.00, $deliv['customer_price']);
        $this->assertEquals(5, $deliv['delivery_timeframe_days']);
    }

    /**
     * Test 2: Scheduled appointment booking with atomic wallet deduction and escrow holding.
     */
    public function test_appointment_booking_deducts_wallet_and_holds_funds_in_escrow(): void
    {
        Sanctum::actingAs($this->user);

        $startAt = Carbon::now()->addDays(2)->setTime(14, 0, 0);

        $response = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", [
            'start_at' => $startAt->toIso8601String(),
            'appointment_type' => 'online',
            'timezone' => 'Asia/Kolkata',
            'notes' => 'Career and financial growth queries.',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending_confirmation',
                    'base_price' => 500.00,
                    'platform_markup' => 100.00,
                    'customer_price' => 600.00,
                ],
            ]);

        // Consumer wallet debited from ₹2000 to ₹1400 (customer price ₹600)
        $this->user->refresh();
        $this->assertEquals(1400.00, (float) $this->user->wallet_balance);

        // Ledger record exists
        $walletTx = UserWalletTransaction::where('user_id', $this->user->id)->latest()->first();
        $this->assertEquals('appointment_booking_debit', $walletTx->transaction_type);
        $this->assertEquals(600.00, (float) $walletTx->amount);
        $this->assertEquals(2000.00, (float) $walletTx->balance_before);
        $this->assertEquals(1400.00, (float) $walletTx->balance_after);

        // Appointment created with 24h acceptance deadline
        $appointment = Appointment::where('user_id', $this->user->id)->first();
        $this->assertNotNull($appointment);
        $this->assertEquals('pending_confirmation', $appointment->status);
        $this->assertNotNull($appointment->acceptance_deadline_at);
        $this->assertTrue(Carbon::parse($appointment->acceptance_deadline_at)->greaterThan(Carbon::now()));

        // Professional escrow transaction created with status 'pending' (Held in Escrow!)
        $escrowTx = ProfessionalTransaction::where('appointment_id', $appointment->id)->first();
        $this->assertNotNull($escrowTx);
        $this->assertEquals('pending', $escrowTx->payment_status);
        $this->assertEquals(500.00, (float) $escrowTx->net_amount); // Astrologer's earning when released
        $this->assertEquals(100.00, (float) $escrowTx->platform_fee); // Platform markup
        $this->assertEquals(600.00, (float) $escrowTx->gross_amount); // Customer paid
    }

    /**
     * Test 3: Insufficient wallet balance returns HTTP 402 with structured required balance.
     */
    public function test_booking_with_insufficient_wallet_returns_402(): void
    {
        $poorUser = User::create([
            'name' => 'Poor User',
            'phone' => '9999999999',
            'email' => 'poor@example.com',
            'wallet_balance' => 50.00,
            'password' => bcrypt('secret123'),
        ]);

        Sanctum::actingAs($poorUser);

        $response = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", [
            'start_at' => Carbon::now()->addDays(2)->toIso8601String(),
        ]);

        $response->assertStatus(402)
            ->assertJson([
                'success' => false,
                'error_code' => 'INSUFFICIENT_WALLET_BALANCE',
                'required_amount' => 600.00,
                'wallet_balance' => 50.00,
            ]);

        // Balance untouched
        $poorUser->refresh();
        $this->assertEquals(50.00, (float) $poorUser->wallet_balance);
    }

    /**
     * Test 4: Astrologer accepts appointment within 24 hours (Authoritative Policy 1).
     */
    public function test_astrologer_accepts_appointment_within_24_hours(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);
        $booking = $serviceOrderService->bookAppointment($this->user, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addDays(3)->toIso8601String(),
        ]);

        $appointment = $booking['appointment'];
        $this->assertEquals('pending_confirmation', $appointment->status);

        // Astrologer accepts
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/astrologer/appointments/{$appointment->id}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Appointment accepted successfully.',
                'data' => [
                    'status' => 'confirmed',
                ],
            ]);

        $appointment->refresh();
        $this->assertEquals('confirmed', $appointment->status);
        $this->assertNotNull($appointment->accepted_at);
    }

    /**
     * Test 5: 24-hour acceptance deadline expiration triggers automatic user refund (Authoritative Policy 1).
     */
    public function test_appointment_acceptance_expired_after_24_hours_refunds_user(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);
        $booking = $serviceOrderService->bookAppointment($this->user, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addDays(3)->toIso8601String(),
        ]);

        $appointment = $booking['appointment'];

        // Fast-forward acceptance deadline past 24 hours
        $appointment->acceptance_deadline_at = Carbon::now()->subHours(2);
        $appointment->save();

        // Astrologer attempts to accept after expiration
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/astrologer/appointments/{$appointment->id}/accept");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // User wallet refunded
        $this->user->refresh();
        $this->assertEquals(2000.00, (float) $this->user->wallet_balance);

        $appointment->refresh();
        $this->assertEquals('cancelled', $appointment->status);
        $this->assertEquals('system', $appointment->cancelled_by);

        // Escrow transaction marked refunded
        $escrowTx = ProfessionalTransaction::find($appointment->escrow_transaction_id);
        $this->assertEquals('refunded', $escrowTx->payment_status);
    }

    /**
     * Test 6: User cancellation outside 12-hour cutoff gives full refund (Authoritative Policy 2).
     */
    public function test_user_cancellation_outside_12_hours_cutoff_refunds_fully(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);
        $booking = $serviceOrderService->bookAppointment($this->user, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(36)->toIso8601String(), // 36 hours in advance (> 12h)
        ]);

        $appointment = $booking['appointment'];
        $this->user->refresh();
        $this->assertEquals(1400.00, (float) $this->user->wallet_balance);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/user/appointments/{$appointment->id}/cancel", [
            'reason' => 'Schedule conflict, need to reschedule.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'cancelled',
                    'cancelled_by' => 'user',
                ],
            ]);

        // Full refund to user wallet
        $this->user->refresh();
        $this->assertEquals(2000.00, (float) $this->user->wallet_balance);

        $refundTx = UserWalletTransaction::where('user_id', $this->user->id)->latest()->first();
        $this->assertEquals('refund', $refundTx->transaction_type);
        $this->assertEquals(600.00, (float) $refundTx->amount);
    }

    /**
     * Test 7: User cancellation inside 12-hour cutoff enters admin review queue (Authoritative Policy 2).
     */
    public function test_user_cancellation_inside_12_hours_cutoff_enters_admin_review(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);
        $booking = $serviceOrderService->bookAppointment($this->user, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(3)->toIso8601String(), // 3 hours in advance (< 12h)
        ]);

        $appointment = $booking['appointment'];
        $this->user->refresh();
        $this->assertEquals(1400.00, (float) $this->user->wallet_balance);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/user/appointments/{$appointment->id}/cancel", [
            'reason' => 'Sudden emergency, cancelling last minute.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'cancelled_pending_review',
                    'cancelled_by' => 'user',
                ],
            ]);

        // No immediate automatic refund inside 12h cutoff; held for admin review
        $this->user->refresh();
        $this->assertEquals(1400.00, (float) $this->user->wallet_balance);

        $appointment->refresh();
        $this->assertEquals('cancelled_pending_review', $appointment->status);
    }

    /**
     * Test 8: User no-show enters admin review queue without automatic release (Authoritative Policy 5).
     */
    public function test_appointment_no_show_enters_admin_review(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);
        $booking = $serviceOrderService->bookAppointment($this->user, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(24)->toIso8601String(),
        ]);

        $appointment = $booking['appointment'];

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/astrologer/appointments/{$appointment->id}/no-show", [
            'notes' => 'Client did not connect on Google Meet after 15 minutes of waiting.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Appointment marked as no-show.',
            ]);

        $appointment->refresh();
        $this->assertEquals('no_show_pending_review', $appointment->status);
        $this->assertEquals('pending_review', $appointment->no_show_review_status);
        $this->assertEquals('astrologer', $appointment->no_show_reported_by);

        // Funds still in escrow (no automatic payout, no automatic refund)
        $escrowTx = ProfessionalTransaction::find($appointment->escrow_transaction_id);
        $this->assertEquals('pending', $escrowTx->payment_status);
    }

    /**
     * Test 9: Appointment completion releases escrow payment to the astrologer.
     */
    public function test_appointment_completion_releases_escrow_to_astrologer(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);
        $booking = $serviceOrderService->bookAppointment($this->user, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(24)->toIso8601String(),
        ]);

        $appointment = $booking['appointment'];

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/astrologer/appointments/{$appointment->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Appointment marked as completed.',
            ]);

        $appointment->refresh();
        $this->assertEquals('completed', $appointment->status);

        // Escrow funds now completed and released to astrologer earnings!
        $escrowTx = ProfessionalTransaction::find($appointment->escrow_transaction_id);
        $this->assertEquals('completed', $escrowTx->payment_status);
        $this->assertEquals(500.00, (float) $escrowTx->amount);
    }

    /**
     * Test 10: Asynchronous deliverable order with custom SLA deadline snapshot (Authoritative Policy 3).
     */
    public function test_deliverable_order_takes_custom_service_deadline(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", [
            'order_type' => 'vastu_audit',
            'input_payload' => [
                'property_type' => 'apartment',
                'floor_plan_url' => 'https://example.com/blueprint.pdf',
                'facing_direction' => 'North-East',
            ],
            'client_nonce' => 'nonce-vastu-12345',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_type' => 'vastu_audit',
                    'status' => 'submitted',
                    'base_price' => 1000.00,
                    'platform_markup' => 200.00,
                    'customer_price' => 1200.00,
                    'delivery_timeframe_days' => 5,
                ],
            ]);

        // User debited ₹1200 (₹2000 -> ₹800)
        $this->user->refresh();
        $this->assertEquals(800.00, (float) $this->user->wallet_balance);

        $order = ProfessionalServiceOrder::where('user_id', $this->user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('submitted', $order->status);
        $this->assertEquals(5, $order->delivery_timeframe_days);

        // Deadline snapshot is approx 5 days from now
        $expectedDeadline = Carbon::now()->addDays(5);
        $this->assertEquals($expectedDeadline->format('Y-m-d'), Carbon::parse($order->delivery_deadline_at)->format('Y-m-d'));

        // Escrow transaction created with status 'pending'
        $escrowTx = ProfessionalTransaction::find($order->escrow_transaction_id);
        $this->assertEquals('pending', $escrowTx->payment_status);
        $this->assertEquals(1000.00, (float) $escrowTx->amount);
    }

    /**
     * Test 11: Deliverable submission does NOT release payment until manual client confirmation (Authoritative Policy 4).
     */
    public function test_deliverable_submission_requires_manual_client_confirmation_to_release_funds(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);
        $booking = $serviceOrderService->orderDeliverable($this->user, $this->deliverableService->id, [
            'order_type' => 'vastu_audit',
        ]);

        $order = $booking['order'];

        // 1. Astrologer submits deliverable
        $this->actingAs($this->admin, 'sanctum');

        $submitRes = $this->postJson("/api/astrologer/service-orders/{$order->id}/deliver", [
            'deliverable_text' => 'Vastu report completed: Recommended shifting kitchen to South-East Agneya कोण.',
            'deliverable_attachments' => ['https://example.com/reports/vastu_final.pdf'],
        ]);

        $submitRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'delivered',
                ],
            ]);

        $order->refresh();
        $this->assertEquals('delivered', $order->status);

        // CRITICAL CHECK: Escrow funds MUST STILL BE 'pending' (Authoritative Policy 4)!
        $escrowTx = ProfessionalTransaction::find($order->escrow_transaction_id);
        $this->assertEquals('pending', $escrowTx->payment_status);

        // 2. Client reviews and confirms completion
        Sanctum::actingAs($this->user);

        $confirmRes = $this->postJson("/api/user/service-orders/{$order->id}/confirm-completed");

        $confirmRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                ],
            ]);

        $order->refresh();
        $this->assertEquals('completed', $order->status);

        // Escrow funds released to astrologer earnings upon client confirmation!
        $escrowTx->refresh();
        $this->assertEquals('completed', $escrowTx->payment_status);
    }

    /**
     * Test 12: Deliverable dispute workflow and administrative resolution.
     */
    public function test_deliverable_dispute_and_admin_refund_resolution(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);
        $booking = $serviceOrderService->orderDeliverable($this->user, $this->deliverableService->id, [
            'order_type' => 'vastu_audit',
        ]);

        $order = $booking['order'];
        $this->user->refresh();
        $this->assertEquals(800.00, (float) $this->user->wallet_balance);

        // Astrologer delivers
        $serviceOrderService->submitDeliverable($order->id, $this->admin->id, 'Draft notes');

        // Client disputes deliverable
        Sanctum::actingAs($this->user);

        $disputeRes = $this->postJson("/api/user/service-orders/{$order->id}/dispute", [
            'reason' => 'Report was incomplete and did not cover the requested kitchen remedies.',
        ]);

        $disputeRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'disputed',
                    'dispute_status' => 'pending_review',
                ],
            ]);

        $order->refresh();
        $this->assertEquals('disputed', $order->status);

        // Admin resolves dispute with refund to client
        $serviceOrderService->resolveOrderDispute($order->id, 'refund', 'Legitimate dispute; astrologer missed remedies.');

        $order->refresh();
        $this->assertEquals('refunded', $order->status);

        // Consumer wallet refunded ₹1200 (₹800 -> ₹2000)
        $this->user->refresh();
        $this->assertEquals(2000.00, (float) $this->user->wallet_balance);

        $escrowTx = ProfessionalTransaction::find($order->escrow_transaction_id);
        $this->assertEquals('refunded', $escrowTx->payment_status);
    }

    /**
     * Test 13: Astrologer payments summary includes categorized service breakdown.
     */
    public function test_payments_summary_includes_categorized_breakdown(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        // 1. Completed appointment (₹500 earning)
        $apptBooking = $serviceOrderService->bookAppointment($this->user, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(24)->toIso8601String(),
        ]);
        $serviceOrderService->completeAppointment($apptBooking['appointment']->id, $this->admin->id);

        // 2. Completed deliverable order (₹1000 earning)
        $delivBooking = $serviceOrderService->orderDeliverable($this->user, $this->deliverableService->id, [
            'order_type' => 'vastu_audit',
        ]);
        $serviceOrderService->submitDeliverable($delivBooking['order']->id, $this->admin->id, 'Full report');
        $serviceOrderService->confirmDeliverableCompletion($delivBooking['order']->id, $this->user->id);

        // Query Payments Summary
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/astrologer/payments/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_received' => 1500.00,
                    'category_breakdown' => [
                        'kundli' => 500.00,
                        'vastu' => 1000.00,
                    ],
                ],
            ]);
    }
}
