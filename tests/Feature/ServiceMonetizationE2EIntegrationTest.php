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
use App\Services\Pricing\PricingService;
use App\Services\Services\ServiceOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceMonetizationE2EIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerA;
    protected User $customerB;
    protected Admin $astrologerAdminA;
    protected Astrologer $astrologerA;
    protected Admin $astrologerAdminB;
    protected Astrologer $astrologerB;
    protected Admin $platformAdmin;
    protected ProfessionalService $deliverableService;
    protected ProfessionalService $appointmentService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);

        // Platform Super Admin
        $this->platformAdmin = Admin::create([
            'name' => 'Platform Super Admin',
            'email' => 'admin@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->platformAdmin->assignRole('Super Admin');

        // Astrologer A
        $this->astrologerAdminA = Admin::create([
            'name' => 'Acharya Raman Shastri',
            'email' => 'raman@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->astrologerAdminA->assignRole('Astrologer');

        $this->astrologerA = Astrologer::create([
            'admin_id' => $this->astrologerAdminA->id,
            'name' => 'Acharya Raman Shastri',
            'language' => 'en',
            'astrologer_language' => 'Hindi, English',
            'expertise' => 'Vedic, Vastu, Palmistry',
            'experience' => '15 years',
            'price' => 500.00,
            'status' => 'online',
            'active' => 1,
        ]);

        // Astrologer B (Isolation Testing)
        $this->astrologerAdminB = Admin::create([
            'name' => 'Pt. Brijesh Mishra',
            'email' => 'brijesh@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->astrologerAdminB->assignRole('Astrologer');

        $this->astrologerB = Astrologer::create([
            'admin_id' => $this->astrologerAdminB->id,
            'name' => 'Pt. Brijesh Mishra',
            'language' => 'en',
            'astrologer_language' => 'Hindi',
            'expertise' => 'Kundli, Gemology',
            'experience' => '10 years',
            'price' => 300.00,
            'status' => 'online',
            'active' => 1,
        ]);

        // Customer A with ₹3,000 wallet balance
        $this->customerA = User::create([
            'name' => 'Deepak Verma',
            'phone' => '9811111111',
            'email' => 'deepak@example.com',
            'wallet_balance' => 3000.00,
            'password' => bcrypt('password123'),
        ]);

        // Customer B with ₹100 wallet balance (Insufficient)
        $this->customerB = User::create([
            'name' => 'Amit Kumar',
            'phone' => '9822222222',
            'email' => 'amit@example.com',
            'wallet_balance' => 100.00,
            'password' => bcrypt('password123'),
        ]);

        // Deliverable Service by Astrologer A: Base ₹500, Custom SLA 4 days
        $this->deliverableService = ProfessionalService::create([
            'admin_id' => $this->astrologerAdminA->id,
            'name' => 'Comprehensive Vedic Career Report',
            'description' => 'Detailed 15-page career roadmap and planetary dasha analysis.',
            'category' => 'janam_kundli',
            'pricing_model' => 'fixed',
            'price' => 500.00, // Base price B
            'currency' => 'INR',
            'duration_minutes' => 0,
            'delivery_timeframe_days' => 4, // Custom SLA
            'delivery_methods' => ['online'],
            'is_active' => true,
            'is_public' => true,
        ]);

        // Appointment Service by Astrologer A: Base ₹500, 30 min duration
        $this->appointmentService = ProfessionalService::create([
            'admin_id' => $this->astrologerAdminA->id,
            'name' => 'Live Kundli Video Consultation',
            'description' => '30-minute one-on-one live video astrological consultation.',
            'category' => 'kundli_consultation',
            'pricing_model' => 'fixed',
            'price' => 500.00, // Base price B
            'currency' => 'INR',
            'duration_minutes' => 30,
            'delivery_methods' => ['online'],
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    /**
     * Scenario A: Complete Deliverable Order Lifecycle
     */
    public function test_scenario_a_complete_deliverable_order_lifecycle(): void
    {
        // 1. Verify 20% platform markup formula (B = 500, M = 100, C = 600)
        $pricing = app(PricingService::class)->calculateCommercialPricing($this->deliverableService->price);
        $this->assertEquals(500.00, $pricing['base_price']);
        $this->assertEquals(100.00, $pricing['platform_markup']);
        $this->assertEquals(600.00, $pricing['customer_price']);

        // 2. Discover service in Astro Setu
        $discoveryResponse = $this->getJson('/api/services/' . $this->deliverableService->id);
        $discoveryResponse->assertStatus(200);
        $this->assertEquals(500.00, $discoveryResponse->json('data.pricing.base_price'));
        $this->assertEquals(100.00, $discoveryResponse->json('data.pricing.platform_markup'));
        $this->assertEquals(600.00, $discoveryResponse->json('data.pricing.customer_price'));
        $this->assertEquals(4, $discoveryResponse->json('data.delivery_timeframe_days'));

        // 3. Purchase deliverable service from Astro Setu with wallet checkout
        Sanctum::actingAs($this->customerA);
        $purchasePayload = [
            'order_type' => 'custom',
            'client_nonce' => 'NONCE-' . Str::uuid(),
            'input_payload' => [
                'full_name' => 'Deepak Verma',
                'gender' => 'Male',
                'date_of_birth' => '1992-08-15',
                'time_of_birth' => '14:30',
                'place_of_birth' => 'New Delhi',
                'query_description' => 'When will I switch jobs to senior management?',
            ],
        ];

        $orderResponse = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", $purchasePayload);
        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');
        $this->assertNotNull($orderId);

        // 4. Verify wallet deduction: 3000 - 600 = 2400
        $this->customerA->refresh();
        $this->assertEquals(2400.00, (float) $this->customerA->wallet_balance);

        $walletTx = UserWalletTransaction::where('user_id', $this->customerA->id)
            ->where('service_order_id', $orderId)
            ->first();
        $this->assertNotNull($walletTx);
        $this->assertEquals('service_order_debit', $walletTx->transaction_type);
        $this->assertEquals(600.00, (float) $walletTx->amount);
        $this->assertEquals(3000.00, (float) $walletTx->balance_before);
        $this->assertEquals(2400.00, (float) $walletTx->balance_after);

        // 5. Verify Escrow transaction created and pending
        $escrowTx = ProfessionalTransaction::where('service_order_id', $orderId)->first();
        $this->assertNotNull($escrowTx);
        $this->assertEquals('pending', $escrowTx->payment_status);
        $this->assertEquals(500.00, (float) $escrowTx->amount);
        $this->assertEquals(600.00, (float) $escrowTx->gross_amount);
        $this->assertEquals(100.00, (float) $escrowTx->platform_fee);

        // 6. Astrologer views order in Pandit Setu
        Sanctum::actingAs($this->astrologerAdminA, ['*']);
        $panditList = $this->getJson('/api/astrologer/service-orders');
        $panditList->assertStatus(200);
        $panditList->assertJsonFragment(['id' => $orderId, 'status' => 'submitted']);

        $panditDetail = $this->getJson("/api/astrologer/service-orders/{$orderId}");
        $panditDetail->assertStatus(200);
        $panditDetail->assertJsonPath('data.input_payload.full_name', 'Deepak Verma');
        $panditDetail->assertJsonPath('data.input_payload.query_description', 'When will I switch jobs to senior management?');

        // 7. Astrologer starts order
        $startResponse = $this->postJson("/api/astrologer/service-orders/{$orderId}/start");
        $startResponse->assertStatus(200);
        $startResponse->assertJsonPath('data.status', 'in_progress');

        // 8. Astrologer uploads a report PDF attachment
        $fakePdf = UploadedFile::fake()->create('vedic_career_reading.pdf', 250, 'application/pdf');
        $uploadResponse = $this->postJson("/api/service-orders/{$orderId}/attachments", [
            'file' => $fakePdf,
            'type' => 'deliverable',
        ]);
        $uploadResponse->assertStatus(201);
        $attachmentUrl = $uploadResponse->json('data.url');
        $this->assertNotNull($attachmentUrl);

        // 9. Astrologer delivers report
        $deliverResponse = $this->postJson("/api/astrologer/service-orders/{$orderId}/deliver", [
            'deliverable_text' => 'Jupiter transits into your 10th house in April 2027, bringing a promotion.',
            'deliverable_attachments' => [$attachmentUrl],
        ]);
        $deliverResponse->assertStatus(200);
        $deliverResponse->assertJsonPath('data.status', 'delivered');

        // Verify Escrow is STILL pending! (Authoritative Policy 4: Astrologer submission alone does NOT release escrow)
        $escrowTx->refresh();
        $this->assertEquals('pending', $escrowTx->payment_status);

        // 10. Customer verifies order is delivered and views findings in Astro Setu
        Sanctum::actingAs($this->customerA);
        $customerDetail = $this->getJson("/api/user/service-orders/{$orderId}");
        $customerDetail->assertStatus(200);
        $customerDetail->assertJsonPath('data.status', 'delivered');
        $customerDetail->assertJsonPath('data.deliverable_text', 'Jupiter transits into your 10th house in April 2027, bringing a promotion.');

        // 11. Customer confirms completion from Astro Setu
        $confirmResponse = $this->postJson("/api/user/service-orders/{$orderId}/confirm-completed");
        $confirmResponse->assertStatus(200);
        $confirmResponse->assertJsonPath('data.status', 'completed');

        // 12. Verify Escrow is released to Astrologer
        $escrowTx->refresh();
        $this->assertEquals('completed', $escrowTx->payment_status);

        // 13. Verify Pandit Setu financial dashboard
        Sanctum::actingAs($this->astrologerAdminA, ['*']);
        $summaryResponse = $this->getJson('/api/astrologer/payments/summary');
        $summaryResponse->assertStatus(200);
        $this->assertEquals(500.00, (float) $summaryResponse->json('data.total_received'));
        $this->assertEquals(0.00, (float) $summaryResponse->json('data.total_pending'));
        $this->assertEquals(500.00, (float) $summaryResponse->json('data.service_orders_earnings'));
    }

    /**
     * Scenario B: Appointment Lifecycle and Double Booking Prevention
     */
    public function test_scenario_b_appointment_lifecycle_and_double_booking_prevention(): void
    {
        // 1. Fetch available slots for tomorrow
        $targetDate = Carbon::tomorrow()->toDateString();
        $availabilityResponse = $this->getJson("/api/astrologers/{$this->astrologerA->id}/availability?date={$targetDate}&service_id={$this->appointmentService->id}");
        $availabilityResponse->assertStatus(200);
        $slots = $availabilityResponse->json('slots');
        $this->assertNotEmpty($slots);
        $chosenSlot = $slots[0]['time']; // e.g. "10:00"

        $startAt = "{$targetDate} {$chosenSlot}:00";
        $endAt = Carbon::parse($startAt)->addMinutes(30)->toDateTimeString();

        // 2. Customer A books this slot
        Sanctum::actingAs($this->customerA);
        $bookPayload = [
            'start_at' => $startAt,
            'end_at' => $endAt,
            'appointment_type' => 'online',
            'client_nonce' => 'APT-NONCE-' . Str::uuid(),
            'notes' => 'Discuss business growth in upcoming year.',
        ];

        $bookResponse = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", $bookPayload);
        $bookResponse->assertStatus(201);
        $appointmentId = $bookResponse->json('data.id');

        // 3. Double Booking Prevention: Customer A (or another customer) attempts to book the SAME slot
        $conflictPayload = [
            'start_at' => $startAt,
            'end_at' => $endAt,
            'appointment_type' => 'online',
            'client_nonce' => 'APT-NONCE-' . Str::uuid(),
            'notes' => 'Attempting duplicate booking.',
        ];
        $conflictResponse = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", $conflictPayload);
        $conflictResponse->assertStatus(422);
        $conflictResponse->assertJsonFragment(['message' => 'The selected appointment slot is no longer available. Please select another slot.']);

        // 4. Astrologer sees pending appointment in Pandit Setu
        Sanctum::actingAs($this->astrologerAdminA, ['*']);
        $apptDetail = $this->getJson("/api/astrologer/appointments/{$appointmentId}");
        $apptDetail->assertStatus(200);
        $apptDetail->assertJsonPath('data.status', 'pending_confirmation');

        // 5. Astrologer accepts appointment within 24h
        $acceptResponse = $this->postJson("/api/astrologer/appointments/{$appointmentId}/accept");
        $acceptResponse->assertStatus(200);
        $acceptResponse->assertJsonPath('data.status', 'confirmed');

        // 6. Astrologer completes appointment -> Escrow releases
        $completeResponse = $this->postJson("/api/astrologer/appointments/{$appointmentId}/complete");
        $completeResponse->assertStatus(200);
        $completeResponse->assertJsonPath('data.status', 'completed');

        $appt = Appointment::find($appointmentId);
        $this->assertEquals('paid', $appt->payment_status);

        // 7. Test rejection & automatic full refund flow on a new appointment
        $rejectSlot = $slots[1]['time'];
        $rejectStart = "{$targetDate} {$rejectSlot}:00";
        $rejectEnd = Carbon::parse($rejectStart)->addMinutes(30)->toDateTimeString();

        Sanctum::actingAs($this->customerA);
        $balanceBeforeReject = (float) $this->customerA->fresh()->wallet_balance;

        $newBook = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", [
            'start_at' => $rejectStart,
            'end_at' => $rejectEnd,
            'appointment_type' => 'online',
            'client_nonce' => 'APT-NONCE-' . Str::uuid(),
        ]);
        $newApptId = $newBook->json('data.id');

        // Astrologer rejects
        Sanctum::actingAs($this->astrologerAdminA, ['*']);
        $rejectResponse = $this->postJson("/api/astrologer/appointments/{$newApptId}/reject", [
            'reason' => 'Emergency temple puja scheduled at this time.',
        ]);
        $rejectResponse->assertStatus(200);

        // Customer wallet balance must be restored in full
        $this->customerA->refresh();
        $this->assertEquals($balanceBeforeReject, (float) $this->customerA->wallet_balance);

        $refundTx = UserWalletTransaction::where('user_id', $this->customerA->id)
            ->where('appointment_id', $newApptId)
            ->where('transaction_type', 'refund')
            ->first();
        $this->assertNotNull($refundTx);
        $this->assertEquals(600.00, (float) $refundTx->amount);
    }

    /**
     * Scenario C: Authoritative Business Policies Verification
     */
    public function test_scenario_c_authoritative_business_policies(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        // 1. Policy 1: 24h acceptance deadline auto-expiry
        $now = Carbon::now();
        Carbon::setTestNow($now);

        Sanctum::actingAs($this->customerA);
        $balanceBefore = (float) $this->customerA->wallet_balance;

        $bookResult = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => $now->copy()->addDays(3)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ]);
        $expiredAppt = $bookResult['appointment'];

        // Fast forward 25 hours
        Carbon::setTestNow($now->copy()->addHours(25));

        // Astrologer attempts to accept after 25h -> Must be rejected & auto refunded
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment acceptance deadline has expired');

        try {
            $serviceOrderService->acceptAppointment($expiredAppt->id, $this->astrologerAdminA->id);
        } finally {
            $expiredAppt->refresh();
            $this->assertEquals('cancelled', $expiredAppt->status);
            $this->assertEquals('refunded', $expiredAppt->payment_status);

            // User refunded in full
            $this->customerA->refresh();
            $this->assertEquals($balanceBefore, (float) $this->customerA->wallet_balance);

            Carbon::setTestNow(); // Reset clock
        }
    }

    /**
     * Scenario C2: 12-hour cancellation cutoff policies
     */
    public function test_scenario_c2_cancellation_cutoff_policy(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        // Case A: User cancels >= 12h before appointment -> Auto full refund
        $apptFar = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(24)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];

        $balanceBefore = (float) $this->customerA->fresh()->wallet_balance;

        $cancelFar = $serviceOrderService->cancelAppointment($apptFar->id, $this->customerA, 'Schedule change', 'user');
        $this->assertEquals('cancelled', $cancelFar->status);
        $this->assertEquals('refunded', $cancelFar->payment_status);
        $this->assertEquals($balanceBefore + 600.00, (float) $this->customerA->fresh()->wallet_balance);

        // Case B: User cancels < 12h before appointment -> Enters admin review, NO auto-refund
        $apptClose = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(6)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];

        $balanceBeforeClose = (float) $this->customerA->fresh()->wallet_balance;

        $cancelClose = $serviceOrderService->cancelAppointment($apptClose->id, $this->customerA, 'Urgent meeting', 'user');
        $this->assertEquals('cancelled_pending_review', $cancelClose->status);
        $this->assertEquals('pending', $cancelClose->payment_status);

        // Wallet balance must NOT be refunded yet
        $this->assertEquals($balanceBeforeClose, (float) $this->customerA->fresh()->wallet_balance);
    }

    /**
     * Scenario C3: No-show policy enters admin review
     */
    public function test_scenario_c3_no_show_admin_review_policy(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        $appt = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];

        $noShowAppt = $serviceOrderService->reportNoShow($appt->id, 'user', 'Practitioner did not join the video meeting', $this->customerA->id);

        $this->assertEquals('no_show_pending_review', $noShowAppt->status);
        $this->assertEquals('pending', $noShowAppt->payment_status); // Escrow remains held
    }

    /**
     * Scenario D: Financial Integrity and Idempotency
     */
    public function test_scenario_d_financial_integrity_and_idempotency(): void
    {
        // 1. Insufficient wallet balance returns HTTP 402
        Sanctum::actingAs($this->customerB); // ₹100 wallet balance
        $insufficientResponse = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", [
            'order_type' => 'custom',
            'client_nonce' => 'NONCE-' . Str::uuid(),
        ]);
        $insufficientResponse->assertStatus(402);
        $insufficientResponse->assertJsonPath('error_code', 'INSUFFICIENT_WALLET_BALANCE');
        $this->assertEquals(600.00, $insufficientResponse->json('required_amount'));

        // 2. Idempotency nonce prevents double-billing on network retry
        Sanctum::actingAs($this->customerA);
        $fixedNonce = 'IDEMPOTENT-NONCE-12345';
        $balanceStart = (float) $this->customerA->fresh()->wallet_balance;

        $firstCall = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", [
            'order_type' => 'custom',
            'client_nonce' => $fixedNonce,
        ]);
        $firstCall->assertStatus(201);
        $firstOrderId = $firstCall->json('data.id');

        $balanceAfterFirst = (float) $this->customerA->fresh()->wallet_balance;
        $this->assertEquals($balanceStart - 600.00, $balanceAfterFirst);

        // Duplicate call with same nonce
        $secondCall = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", [
            'order_type' => 'custom',
            'client_nonce' => $fixedNonce,
        ]);
        $this->assertTrue(in_array($secondCall->status(), [200, 201]));
        $secondOrderId = $secondCall->json('data.id');

        // Must return identical order without second wallet debit
        $this->assertEquals($firstOrderId, $secondOrderId);
        $this->assertEquals($balanceAfterFirst, (float) $this->customerA->fresh()->wallet_balance);
        $this->assertEquals(1, UserWalletTransaction::where('service_order_id', $firstOrderId)->count());
    }

    /**
     * Scenario E: Security, Cross-Account Isolation and Private Attachments
     */
    public function test_scenario_e_security_and_private_attachments(): void
    {
        // Create an order belonging to Customer A and Astrologer A
        $serviceOrderService = app(ServiceOrderService::class);
        $orderA = $serviceOrderService->orderDeliverable($this->customerA, $this->deliverableService->id, [
            'order_type' => 'custom',
        ])['order'];

        // 1. Customer B cannot view Customer A's order (403 Forbidden)
        Sanctum::actingAs($this->customerB);
        $unauthOrderView = $this->getJson("/api/user/service-orders/{$orderA->id}");
        $unauthOrderView->assertStatus(403);
        $unauthOrderView->assertJsonFragment(['message' => 'Unauthorized: You do not own this service order.']);

        // Non-existent order returns 404
        $notFoundOrder = $this->getJson("/api/user/service-orders/99999");
        $notFoundOrder->assertStatus(404);

        // 2. Customer B cannot confirm Customer A's order (403 Forbidden)
        $unauthConfirm = $this->postJson("/api/user/service-orders/{$orderA->id}/confirm-completed");
        $unauthConfirm->assertStatus(403);

        // 3. Customer B cannot dispute Customer A's order (403 Forbidden)
        $unauthDispute = $this->postJson("/api/user/service-orders/{$orderA->id}/dispute", [
            'reason' => 'Unauthorized dispute',
        ]);
        $unauthDispute->assertStatus(403);

        // 4. Astrologer B cannot view Astrologer A's order (403 Forbidden)
        Sanctum::actingAs($this->astrologerAdminB, ['*']);
        $unauthAstroView = $this->getJson("/api/astrologer/service-orders/{$orderA->id}");
        $unauthAstroView->assertStatus(403);
        $unauthAstroView->assertJsonFragment(['message' => 'Unauthorized: You are not assigned to this service order.']);

        // Non-existent astrologer order returns 404
        $notFoundAstroOrder = $this->getJson("/api/astrologer/service-orders/99999");
        $notFoundAstroOrder->assertStatus(404);

        // 5. Astrologer B cannot start Astrologer A's order (403 Forbidden, NEVER 500)
        $unauthStart = $this->postJson("/api/astrologer/service-orders/{$orderA->id}/start");
        $unauthStart->assertStatus(403);

        // 6. Astrologer B cannot deliver Astrologer A's order (403 Forbidden, NEVER 500)
        $unauthDeliver = $this->postJson("/api/astrologer/service-orders/{$orderA->id}/deliver", [
            'deliverable_text' => 'Malicious delivery attempt',
        ]);
        $unauthDeliver->assertStatus(403);

        // 7. Cross-Practitioner Appointment Security
        $apptA = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addDays(4)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];

        // Astrologer B cannot view Astrologer A's appointment (403 Forbidden)
        $unauthApptView = $this->getJson("/api/astrologer/appointments/{$apptA->id}");
        $unauthApptView->assertStatus(403);

        // Astrologer B cannot accept Astrologer A's appointment (403 Forbidden, NEVER 500)
        $unauthAccept = $this->postJson("/api/astrologer/appointments/{$apptA->id}/accept");
        $unauthAccept->assertStatus(403);

        // Astrologer B cannot reject Astrologer A's appointment (403 Forbidden, NEVER 500)
        $unauthReject = $this->postJson("/api/astrologer/appointments/{$apptA->id}/reject", ['reason' => 'Not mine']);
        $unauthReject->assertStatus(403);

        // Astrologer B cannot cancel Astrologer A's appointment (403 Forbidden)
        $unauthCancel = $this->postJson("/api/astrologer/appointments/{$apptA->id}/cancel", ['cancellation_reason' => 'Not mine']);
        $unauthCancel->assertStatus(403);

        // Astrologer B cannot complete Astrologer A's appointment (403 Forbidden)
        $unauthComplete = $this->postJson("/api/astrologer/appointments/{$apptA->id}/complete");
        $unauthComplete->assertStatus(403);

        // Astrologer B cannot report no-show on Astrologer A's appointment (403 Forbidden)
        $unauthAstroNoShow = $this->postJson("/api/astrologer/appointments/{$apptA->id}/no-show", ['notes' => 'Not mine']);
        $unauthAstroNoShow->assertStatus(403);

        // 8. Cross-Customer Appointment Security
        Sanctum::actingAs($this->customerB);

        // Customer B cannot cancel Customer A's appointment (403 Forbidden)
        $unauthCustCancel = $this->postJson("/api/user/appointments/{$apptA->id}/cancel", [
            'reason' => 'Unauthorized cancellation',
        ]);
        $unauthCustCancel->assertStatus(403);

        // Customer B cannot report no-show on Customer A's appointment (403 Forbidden)
        $unauthNoShow = $this->postJson("/api/user/appointments/{$apptA->id}/report-no-show", [
            'notes' => 'Tampering attempt',
        ]);
        $unauthNoShow->assertStatus(403);
        $unauthNoShow->assertJsonFragment(['message' => 'Unauthorized: Appointment does not belong to this user.']);

        // Non-existent appointment returns 404
        $notFoundAppt = $this->postJson("/api/user/appointments/99999/cancel", [
            'reason' => 'Cancelled non-existent appointment',
        ]);
        $notFoundAppt->assertStatus(404);

        // 9. Private Attachment Security: Upload valid file
        Sanctum::actingAs($this->astrologerAdminA, ['*']);
        $pdfFile = UploadedFile::fake()->create('confidential_horoscope.pdf', 300, 'application/pdf');

        $uploadRes = $this->postJson("/api/service-orders/{$orderA->id}/attachments", [
            'file' => $pdfFile,
            'type' => 'deliverable',
        ]);
        $uploadRes->assertStatus(201);
        $filename = $uploadRes->json('data.filename');

        // Customer B cannot download this attachment (403 Forbidden)
        Sanctum::actingAs($this->customerB);
        $unauthDownload = $this->getJson("/api/service-orders/{$orderA->id}/attachments/{$filename}");
        $unauthDownload->assertStatus(403);

        // Astrologer B cannot download this attachment (403 Forbidden)
        Sanctum::actingAs($this->astrologerAdminB, ['*']);
        $unauthAstroDownload = $this->getJson("/api/service-orders/{$orderA->id}/attachments/{$filename}");
        $unauthAstroDownload->assertStatus(403);

        // Path Traversal Attack is blocked
        Sanctum::actingAs($this->customerA);
        $traversal = $this->getJson("/api/service-orders/{$orderA->id}/attachments/..%2F..%2F..%2Fenv");
        $this->assertTrue(in_array($traversal->status(), [400, 404]));

        // Authorized Customer A can download attachment
        $authDownload = $this->getJson("/api/service-orders/{$orderA->id}/attachments/{$filename}");
        $authDownload->assertStatus(200);
        $this->assertStringContainsString('no-store', $authDownload->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $authDownload->headers->get('Cache-Control'));

        // Invalid file format (e.g. .php or .sh) rejected by validation
        Sanctum::actingAs($this->astrologerAdminA, ['*']);
        $maliciousFile = UploadedFile::fake()->create('script.php', 10, 'text/x-php');
        $maliciousUpload = $this->postJson("/api/service-orders/{$orderA->id}/attachments", [
            'file' => $maliciousFile,
        ]);
        $maliciousUpload->assertStatus(422);
    }

    /**
     * Scenario F: Earnings and Cross-Ledger Consistency
     */
    public function test_scenario_f_cross_ledger_and_dashboard_consistency(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        // Deliverable Order: B = 500, C = 600
        $order = $serviceOrderService->orderDeliverable($this->customerA, $this->deliverableService->id, [
            'order_type' => 'custom',
        ])['order'];

        // Appointment: B = 500, C = 600
        $appt = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];

        // Before completion: Total Pending Escrow = 1000, Total Received = 0
        Sanctum::actingAs($this->astrologerAdminA, ['*']);
        $summaryBefore = $this->getJson('/api/astrologer/payments/summary')->json('data');
        $this->assertEquals(0.00, (float) $summaryBefore['total_received']);
        $this->assertEquals(1000.00, (float) $summaryBefore['total_pending']);

        // Deliver and confirm deliverable order
        $serviceOrderService->submitDeliverable($order->id, $this->astrologerAdminA->id, 'Done reading');
        $serviceOrderService->confirmDeliverableCompletion($order->id, $this->customerA->id);

        // Complete appointment
        $serviceOrderService->acceptAppointment($appt->id, $this->astrologerAdminA->id);
        $serviceOrderService->completeAppointment($appt->id, $this->astrologerAdminA->id);

        // After completion: Total Received = 1000, Total Pending = 0
        $summaryAfter = $this->getJson('/api/astrologer/payments/summary')->json('data');
        $this->assertEquals(1000.00, (float) $summaryAfter['total_received']);
        $this->assertEquals(0.00, (float) $summaryAfter['total_pending']);
        $this->assertEquals(500.00, (float) $summaryAfter['service_orders_earnings']);
        $this->assertEquals(500.00, (float) $summaryAfter['appointments_earnings']);

        // Check categorised breakdown (both services fall under kundli)
        $this->assertEquals(1000.00, (float) $summaryAfter['category_breakdown']['kundli']);
    }

    /**
     * Scenario G: Database-Level Idempotency and Concurrency Protection
     */
    public function test_scenario_g_database_level_idempotency_and_concurrency_protection(): void
    {
        $nonce = 'test-nonce-' . Str::uuid();

        // 1. First booking with nonce succeeds
        Sanctum::actingAs($this->customerA);
        $res1 = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", [
            'start_at' => Carbon::now()->addDays(6)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
            'client_nonce' => $nonce,
        ]);
        $res1->assertStatus(201);
        $apptId = $res1->json('data.id');

        // 2. Second booking with identical nonce is idempotent (returns existing appointment, does not double debit)
        $walletBefore = (float) $this->customerA->fresh()->wallet_balance;
        $res2 = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", [
            'start_at' => Carbon::now()->addDays(6)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
            'client_nonce' => $nonce,
        ]);
        $res2->assertStatus(200);
        $this->assertEquals($apptId, $res2->json('data.id'));
        $this->assertTrue($res2->json('idempotent'));
        $this->assertEquals($walletBefore, (float) $this->customerA->fresh()->wallet_balance);

        // 3. Database unique constraint on appointments.client_nonce prevents raw duplicate insert
        $this->expectException(\Illuminate\Database\QueryException::class);
        Appointment::create([
            'admin_id' => $this->astrologerAdminA->id,
            'user_id' => $this->customerA->id,
            'service_id' => $this->appointmentService->id,
            'title' => 'Duplicate Nonce Test',
            'appointment_type' => 'online',
            'start_at' => Carbon::now()->addDays(7),
            'end_at' => Carbon::now()->addDays(7)->addMinutes(30),
            'client_nonce' => $nonce,
            'status' => 'confirmed',
        ]);
    }

    /**
     * Scenario H: Administrative Resolution Workflows & Audit Tracking
     */
    public function test_scenario_h_administrative_resolution_workflows(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        // 1. Create a disputed deliverable order
        $order = $serviceOrderService->orderDeliverable($this->customerA, $this->deliverableService->id, [
            'order_type' => 'custom',
        ])['order'];
        $serviceOrderService->submitDeliverable($order->id, $this->astrologerAdminA->id, 'Draft reading completed');
        $order = $serviceOrderService->disputeOrder($order->id, $this->customerA->id, 'Incomplete deliverable provided');
        $this->assertEquals('disputed', $order->status);

        // 2. Create a late-cancelled appointment (<12h cutoff)
        $lateAppt = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(2)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];
        $lateAppt = $serviceOrderService->cancelAppointment($lateAppt->id, $this->customerA->id, 'Late personal emergency', 'user');
        $this->assertEquals('cancelled_pending_review', $lateAppt->status);

        // 3. Create a no-show appointment
        $noShowAppt = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->subHours(1)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];
        $noShowAppt = $serviceOrderService->reportNoShow($noShowAppt->id, 'user', 'Practitioner was absent', $this->customerA->id);
        $this->assertEquals('no_show_pending_review', $noShowAppt->status);

        // 4. Unauthorized non-admin access is rejected (403 Forbidden)
        Sanctum::actingAs($this->customerB);
        $unauthQueue = $this->getJson('/api/admin/monetization/review-queue');
        $unauthQueue->assertStatus(403);

        // 5. Platform Admin views Review Queue
        Sanctum::actingAs($this->platformAdmin, ['*']);
        $queueRes = $this->getJson('/api/admin/monetization/review-queue');
        $queueRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $queueRes->json('data.counts.disputed_orders'));
        $this->assertGreaterThanOrEqual(1, $queueRes->json('data.counts.late_cancellations'));
        $this->assertGreaterThanOrEqual(1, $queueRes->json('data.counts.no_shows'));
        $this->assertGreaterThanOrEqual(3, $queueRes->json('data.counts.total_pending'));

        // 6. Admin resolves Disputed Order -> Refund
        $custWalletBefore = (float) $this->customerA->fresh()->wallet_balance;
        $disputeRes = $this->postJson("/api/admin/monetization/orders/{$order->id}/resolve-dispute", [
            'resolution' => 'refund',
            'notes' => 'Customer claims verified, issuing full refund.',
        ]);
        $disputeRes->assertStatus(200);
        $orderFresh = $order->fresh();
        $this->assertEquals('refunded', $orderFresh->status);
        $this->assertEquals('resolved_refund', $orderFresh->dispute_status);
        $this->assertEquals($this->platformAdmin->id, $orderFresh->resolved_by_admin_id);
        $this->assertNotNull($orderFresh->dispute_resolved_at);
        $this->assertEquals($custWalletBefore + (float) $order->customer_price, (float) $this->customerA->fresh()->wallet_balance);

        // 7. Admin resolves Late Cancellation -> Payout to Practitioner
        $cancelRes = $this->postJson("/api/admin/monetization/appointments/{$lateAppt->id}/resolve-cancellation", [
            'resolution' => 'payout',
            'notes' => 'Late cancellation under 2 hours without medical certificate, practitioner compensated.',
        ]);
        $cancelRes->assertStatus(200);
        $lateApptFresh = $lateAppt->fresh();
        $this->assertEquals('cancelled', $lateApptFresh->status);
        $this->assertEquals('resolved_payout', $lateApptFresh->cancellation_review_status);
        $this->assertEquals('paid', $lateApptFresh->payment_status);
        $this->assertEquals($this->platformAdmin->id, $lateApptFresh->resolved_by_admin_id);
        $this->assertNotNull($lateApptFresh->resolved_at);
        $this->assertStringContainsString('practitioner compensated', $lateApptFresh->resolution_notes);

        // Escrow transaction marked completed
        $escrowTx = ProfessionalTransaction::find($lateApptFresh->escrow_transaction_id);
        $this->assertEquals('completed', $escrowTx->payment_status);

        // 8. Admin resolves No-Show -> Refund to Customer
        $custWalletBefore2 = (float) $this->customerA->fresh()->wallet_balance;
        $noShowRes = $this->postJson("/api/admin/monetization/appointments/{$noShowAppt->id}/resolve-no-show", [
            'resolution' => 'refund',
            'notes' => 'Customer confirmed practitioner absence, refunding.',
        ]);
        $noShowRes->assertStatus(200);
        $noShowApptFresh = $noShowAppt->fresh();
        $this->assertEquals('cancelled', $noShowApptFresh->status);
        $this->assertEquals('resolved_refund', $noShowApptFresh->no_show_review_status);
        $this->assertEquals($this->platformAdmin->id, $noShowApptFresh->resolved_by_admin_id);
        $this->assertNotNull($noShowApptFresh->resolved_at);
        $this->assertStringContainsString('refunding', $noShowApptFresh->resolution_notes);
        $this->assertEquals($custWalletBefore2 + (float) $noShowAppt->customer_price, (float) $this->customerA->fresh()->wallet_balance);
    }
}
