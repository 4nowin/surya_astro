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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceOrderCheckoutIdempotencyAndAdminFinancialControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerA;
    protected User $customerB;
    protected Admin $astrologerAdmin;
    protected Astrologer $astrologer;
    protected Admin $superAdmin;
    protected ProfessionalService $deliverableService;
    protected ProfessionalService $appointmentService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);

        // 1. Astrologer Admin
        $this->astrologerAdmin = Admin::create([
            'name' => 'Pt. Kashi Nath',
            'email' => 'kashinath@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->astrologerAdmin->assignRole('Astrologer');

        $this->astrologer = Astrologer::create([
            'admin_id' => $this->astrologerAdmin->id,
            'name' => 'Pt. Kashi Nath',
            'language' => 'en',
            'astrologer_language' => 'Hindi, Sanskrit',
            'expertise' => 'Vedic, Kundli, Vastu',
            'experience' => '20 years',
            'price' => 500.00,
            'status' => 'online',
            'active' => 1,
        ]);

        // 2. Super Administrator
        $this->superAdmin = Admin::create([
            'name' => 'Platform Super Administrator',
            'email' => 'superadmin@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->superAdmin->assignRole('Super Admin');

        // 3. Customer A (₹3,000 balance)
        $this->customerA = User::create([
            'name' => 'Rajesh Sharma',
            'phone' => '9811111111',
            'email' => 'rajesh@example.com',
            'wallet_balance' => 3000.00,
            'password' => bcrypt('password123'),
        ]);

        // 4. Customer B (₹3,000 balance)
        $this->customerB = User::create([
            'name' => 'Sunil Gupta',
            'phone' => '9822222222',
            'email' => 'sunil@example.com',
            'wallet_balance' => 3000.00,
            'password' => bcrypt('password123'),
        ]);

        // 5. Deliverable Service (Base ₹500, Customer pays ₹600)
        $this->deliverableService = ProfessionalService::create([
            'admin_id' => $this->astrologerAdmin->id,
            'name' => 'Detailed Janam Kundli Report',
            'description' => 'Comprehensive 50-page life prediction report',
            'category' => 'kundli',
            'service_type' => 'deliverable',
            'pricing_model' => 'fixed',
            'price' => 500.00,
            'currency' => 'INR',
            'delivery_timeframe_days' => 3,
            'is_active' => true,
        ]);

        // 6. Appointment Service (Base ₹500, Customer pays ₹600)
        $this->appointmentService = ProfessionalService::create([
            'admin_id' => $this->astrologerAdmin->id,
            'name' => 'Live Vastu Consultation',
            'description' => '30-minute one-on-one video consultation',
            'category' => 'vastu',
            'service_type' => 'appointment',
            'pricing_model' => 'fixed',
            'price' => 500.00,
            'currency' => 'INR',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Database-Level Unique Constraint on professional_service_orders.client_nonce
     */
    public function test_professional_service_orders_client_nonce_database_unique_constraint_enforced(): void
    {
        $nonce = 'raw-order-nonce-' . Str::uuid();

        ProfessionalServiceOrder::create([
            'order_number' => 'ORD-TEST-001',
            'user_id' => $this->customerA->id,
            'admin_id' => $this->astrologerAdmin->id,
            'service_id' => $this->deliverableService->id,
            'base_price' => 500.00,
            'platform_markup' => 100.00,
            'customer_price' => 600.00,
            'client_nonce' => $nonce,
            'status' => 'submitted',
        ]);

        // Attempting to insert a duplicate client_nonce directly must fail with QueryException
        $this->expectException(QueryException::class);

        ProfessionalServiceOrder::create([
            'order_number' => 'ORD-TEST-002',
            'user_id' => $this->customerB->id,
            'admin_id' => $this->astrologerAdmin->id,
            'service_id' => $this->deliverableService->id,
            'base_price' => 500.00,
            'platform_markup' => 100.00,
            'customer_price' => 600.00,
            'client_nonce' => $nonce,
            'status' => 'submitted',
        ]);
    }

    /**
     * 2. Database-Level Unique Constraint on user_wallet_transactions.reference_id
     */
    public function test_user_wallet_transactions_reference_id_database_unique_constraint_enforced(): void
    {
        $refId = 'REF-TX-' . Str::uuid();

        UserWalletTransaction::create([
            'user_id' => $this->customerA->id,
            'transaction_type' => 'service_order_debit',
            'amount' => 600.00,
            'balance_before' => 3000.00,
            'balance_after' => 2400.00,
            'reference_id' => $refId,
            'status' => 'completed',
        ]);

        $this->expectException(QueryException::class);

        UserWalletTransaction::create([
            'user_id' => $this->customerA->id,
            'transaction_type' => 'service_order_debit',
            'amount' => 600.00,
            'balance_before' => 2400.00,
            'balance_after' => 1800.00,
            'reference_id' => $refId,
            'status' => 'completed',
        ]);
    }

    /**
     * 3. Service Order Checkout Idempotency: Duplicate requests return original order and prevent double debit
     */
    public function test_service_order_checkout_idempotency_returns_original_order_and_prevents_double_debit(): void
    {
        Sanctum::actingAs($this->customerA);
        $clientNonce = 'checkout-nonce-' . Str::uuid();

        // Initial Request
        $res1 = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", [
            'order_type' => 'janam_kundli',
            'client_nonce' => $clientNonce,
            'input_payload' => [
                'name' => 'Rajesh Sharma',
                'dob' => '1990-05-15',
                'tob' => '14:30',
                'pob' => 'Varanasi, UP',
            ],
        ]);

        $res1->assertStatus(201);
        $res1->assertJson(['success' => true]);
        $orderId = $res1->json('data.id');
        $orderNumber = $res1->json('data.order_number');

        // Customer wallet balance must be ₹2,400 (₹3,000 - ₹600)
        $this->assertEquals(2400.00, (float) $this->customerA->fresh()->wallet_balance);

        // Exactly 1 wallet debit transaction
        $this->assertEquals(1, UserWalletTransaction::where('service_order_id', $orderId)->count());

        // Replay identical checkout request with same client_nonce
        $res2 = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", [
            'order_type' => 'janam_kundli',
            'client_nonce' => $clientNonce,
            'input_payload' => [
                'name' => 'Rajesh Sharma',
                'dob' => '1990-05-15',
                'tob' => '14:30',
                'pob' => 'Varanasi, UP',
            ],
        ]);

        $res2->assertStatus(200);
        $this->assertTrue($res2->json('idempotent'));
        $this->assertEquals($orderId, $res2->json('data.id'));
        $this->assertEquals($orderNumber, $res2->json('data.order_number'));

        // Customer wallet balance must REMAIN ₹2,400 (ZERO secondary debit)
        $this->assertEquals(2400.00, (float) $this->customerA->fresh()->wallet_balance);

        // Still exactly 1 wallet debit transaction and 1 escrow transaction
        $this->assertEquals(1, UserWalletTransaction::where('service_order_id', $orderId)->count());
        $this->assertEquals(1, ProfessionalTransaction::where('service_order_id', $orderId)->count());
    }

    /**
     * 4. Scope Isolation: Replaying another customer's client_nonce is rejected with 403
     */
    public function test_client_nonce_cross_customer_tampering_rejected_with_403(): void
    {
        $nonceA = 'nonce-customer-a-' . Str::uuid();

        // Customer A places legitimate order
        Sanctum::actingAs($this->customerA);
        $orderA = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", [
            'order_type' => 'custom',
            'client_nonce' => $nonceA,
        ]);
        $orderA->assertStatus(201);

        // Customer B tries to submit checkout with Customer A's client_nonce
        Sanctum::actingAs($this->customerB);
        $walletBeforeB = (float) $this->customerB->fresh()->wallet_balance;

        $tamperRes = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", [
            'order_type' => 'custom',
            'client_nonce' => $nonceA,
        ]);

        // Must reject with 403 Forbidden
        $tamperRes->assertStatus(403);
        $tamperRes->assertJsonFragment(['message' => "Unauthorized: Client nonce already associated with another user's order."]);

        // Customer B was not charged
        $this->assertEquals($walletBeforeB, (float) $this->customerB->fresh()->wallet_balance);
    }

    /**
     * 5. Unauthenticated and Non-Admin access to admin monetization endpoints rejected
     */
    public function test_unauthenticated_and_customer_access_to_admin_endpoints_rejected(): void
    {
        // 1. Unauthenticated request -> 401
        $unauthQueue = $this->getJson('/api/admin/monetization/review-queue');
        $unauthQueue->assertStatus(401);

        // 2. Customer A (User) -> 403
        Sanctum::actingAs($this->customerA);
        $custQueue = $this->getJson('/api/admin/monetization/review-queue');
        $custQueue->assertStatus(403);
        $custQueue->assertJsonFragment(['message' => 'Unauthorized: Platform administrator privileges required.']);

        $custDispute = $this->postJson('/api/admin/monetization/orders/1/resolve-dispute', [
            'resolution' => 'refund',
            'notes' => 'Attempt by consumer',
        ]);
        $custDispute->assertStatus(403);

        $custCancel = $this->postJson('/api/admin/monetization/appointments/1/resolve-cancellation', [
            'resolution' => 'payout',
            'notes' => 'Attempt by consumer',
        ]);
        $custCancel->assertStatus(403);

        $custNoShow = $this->postJson('/api/admin/monetization/appointments/1/resolve-no-show', [
            'resolution' => 'refund',
            'notes' => 'Attempt by consumer',
        ]);
        $custNoShow->assertStatus(403);
    }

    /**
     * 6. Astrologers cannot perform administrative settlement actions
     */
    public function test_astrologers_cannot_perform_administrative_settlement_actions(): void
    {
        // AstrologerAdmin is an Admin model with role 'Astrologer' (NOT Super Admin)
        Sanctum::actingAs($this->astrologerAdmin, ['*']);

        $astroQueue = $this->getJson('/api/admin/monetization/review-queue');
        $astroQueue->assertStatus(403);
        $astroQueue->assertJsonFragment(['message' => 'Unauthorized: Platform administrator privileges required.']);

        $astroDispute = $this->postJson('/api/admin/monetization/orders/1/resolve-dispute', [
            'resolution' => 'payout',
            'notes' => 'Astrologer self-payout attempt',
        ]);
        $astroDispute->assertStatus(403);
        $astroDispute->assertJsonFragment(['message' => 'Unauthorized: Platform administrator privileges required.']);

        $astroCancel = $this->postJson('/api/admin/monetization/appointments/1/resolve-cancellation', [
            'resolution' => 'payout',
            'notes' => 'Astrologer self-resolution attempt',
        ]);
        $astroCancel->assertStatus(403);
        $astroCancel->assertJsonFragment(['message' => 'Unauthorized: Platform administrator privileges required.']);

        $astroNoShow = $this->postJson('/api/admin/monetization/appointments/1/resolve-no-show', [
            'resolution' => 'payout',
            'notes' => 'Astrologer self-resolution attempt',
        ]);
        $astroNoShow->assertStatus(403);
        $astroNoShow->assertJsonFragment(['message' => 'Unauthorized: Platform administrator privileges required.']);
    }

    /**
     * 7. Authorized Administrator can view review queue with active items
     */
    public function test_authorized_administrator_can_view_review_queue(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        // 1 Disputed order
        $order = $serviceOrderService->orderDeliverable($this->customerA, $this->deliverableService->id, ['order_type' => 'custom'])['order'];
        $serviceOrderService->submitDeliverable($order->id, $this->astrologerAdmin->id, 'Draft delivered');
        $serviceOrderService->disputeOrder($order->id, $this->customerA->id, 'Missing sections');

        // 1 Late cancellation (<12h)
        $appt = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(3)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];
        $serviceOrderService->cancelAppointment($appt->id, $this->customerA->id, 'Emergency cancellation', 'user');

        // 1 No-show report
        $noShowAppt = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->subHours(1)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];
        $serviceOrderService->reportNoShow($noShowAppt->id, 'user', 'Practitioner was absent', $this->customerA->id);

        // Super Admin access
        Sanctum::actingAs($this->superAdmin, ['*']);
        $res = $this->getJson('/api/admin/monetization/review-queue');

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'success',
            'data' => [
                'disputed_orders',
                'late_cancellations',
                'no_shows',
                'counts' => [
                    'disputed_orders',
                    'late_cancellations',
                    'no_shows',
                    'total_pending',
                ],
            ],
        ]);

        $this->assertEquals(1, $res->json('data.counts.disputed_orders'));
        $this->assertEquals(1, $res->json('data.counts.late_cancellations'));
        $this->assertEquals(1, $res->json('data.counts.no_shows'));
        $this->assertEquals(3, $res->json('data.counts.total_pending'));
    }

    /**
     * 8. Dispute Resolution: Refund and duplicate resolution prevention
     */
    public function test_administrative_dispute_resolution_with_duplicate_resolution_prevention(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        $order = $serviceOrderService->orderDeliverable($this->customerA, $this->deliverableService->id, ['order_type' => 'custom'])['order'];
        $serviceOrderService->submitDeliverable($order->id, $this->astrologerAdmin->id, 'Draft reading');
        $order = $serviceOrderService->disputeOrder($order->id, $this->customerA->id, 'Not comprehensive');

        $walletBefore = (float) $this->customerA->fresh()->wallet_balance;

        Sanctum::actingAs($this->superAdmin, ['*']);

        // First resolution: REFUND
        $res1 = $this->postJson("/api/admin/monetization/orders/{$order->id}/resolve-dispute", [
            'resolution' => 'refund',
            'notes' => 'Customer claim validated by admin.',
        ]);

        $res1->assertStatus(200);

        $freshOrder = $order->fresh();
        $this->assertEquals('refunded', $freshOrder->status);
        $this->assertEquals('resolved_refund', $freshOrder->dispute_status);
        $this->assertEquals($this->superAdmin->id, $freshOrder->resolved_by_admin_id);
        $this->assertNotNull($freshOrder->dispute_resolved_at);
        $this->assertEquals('Customer claim validated by admin.', $freshOrder->dispute_resolution_notes);

        // Customer wallet balance credited by full customer price (₹600)
        $this->assertEquals($walletBefore + 600.00, (float) $this->customerA->fresh()->wallet_balance);

        // Escrow transaction marked refunded
        $escrowTx = ProfessionalTransaction::find($freshOrder->escrow_transaction_id);
        $this->assertEquals('refunded', $escrowTx->payment_status);

        // Second resolution attempt MUST BE REJECTED (cannot refund or release twice)
        $res2 = $this->postJson("/api/admin/monetization/orders/{$order->id}/resolve-dispute", [
            'resolution' => 'refund',
            'notes' => 'Duplicate refund attempt',
        ]);

        $res2->assertStatus(422);
        $res2->assertJsonFragment(['message' => 'Order is not in disputed state (current status: refunded).']);

        // Wallet balance must not increase again
        $this->assertEquals($walletBefore + 600.00, (float) $this->customerA->fresh()->wallet_balance);
    }

    /**
     * 9. Late Cancellation Resolution: Payout and duplicate resolution prevention
     */
    public function test_administrative_late_cancellation_resolution_with_duplicate_resolution_prevention(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        $appt = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->addHours(2)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];

        $appt = $serviceOrderService->cancelAppointment($appt->id, $this->customerA->id, 'Late cancellation', 'user');
        $this->assertEquals('cancelled_pending_review', $appt->status);

        Sanctum::actingAs($this->superAdmin, ['*']);

        // First resolution: PAYOUT
        $res1 = $this->postJson("/api/admin/monetization/appointments/{$appt->id}/resolve-cancellation", [
            'resolution' => 'payout',
            'notes' => 'Late cancellation under 2 hours without medical proof.',
        ]);

        $res1->assertStatus(200);

        $freshAppt = $appt->fresh();
        $this->assertEquals('cancelled', $freshAppt->status);
        $this->assertEquals('resolved_payout', $freshAppt->cancellation_review_status);
        $this->assertEquals('paid', $freshAppt->payment_status);
        $this->assertEquals($this->superAdmin->id, $freshAppt->resolved_by_admin_id);
        $this->assertNotNull($freshAppt->resolved_at);

        // Practitioner escrow marked completed
        $escrowTx = ProfessionalTransaction::find($freshAppt->escrow_transaction_id);
        $this->assertEquals('completed', $escrowTx->payment_status);

        // Second resolution attempt MUST BE REJECTED (cannot payout twice)
        $res2 = $this->postJson("/api/admin/monetization/appointments/{$appt->id}/resolve-cancellation", [
            'resolution' => 'payout',
            'notes' => 'Duplicate payout attempt',
        ]);

        $res2->assertStatus(422);
        $res2->assertJsonFragment(['message' => 'Appointment is not pending cancellation review (current status: cancelled).']);
    }

    /**
     * 10. No-Show Resolution: Refund and duplicate resolution prevention
     */
    public function test_administrative_no_show_resolution_with_duplicate_resolution_prevention(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        $appt = $serviceOrderService->bookAppointment($this->customerA, $this->appointmentService->id, [
            'start_at' => Carbon::now()->subHours(1)->format('Y-m-d H:i:s'),
            'appointment_type' => 'online',
        ])['appointment'];

        $appt = $serviceOrderService->reportNoShow($appt->id, 'user', 'Practitioner was offline', $this->customerA->id);
        $this->assertEquals('no_show_pending_review', $appt->status);

        $walletBefore = (float) $this->customerA->fresh()->wallet_balance;

        Sanctum::actingAs($this->superAdmin, ['*']);

        // First resolution: REFUND
        $res1 = $this->postJson("/api/admin/monetization/appointments/{$appt->id}/resolve-no-show", [
            'resolution' => 'refund',
            'notes' => 'Confirmed practitioner technical fault.',
        ]);

        $res1->assertStatus(200);

        $freshAppt = $appt->fresh();
        $this->assertEquals('cancelled', $freshAppt->status);
        $this->assertEquals('resolved_refund', $freshAppt->no_show_review_status);
        $this->assertEquals('refunded', $freshAppt->payment_status);
        $this->assertEquals($this->superAdmin->id, $freshAppt->resolved_by_admin_id);
        $this->assertNotNull($freshAppt->resolved_at);

        // Customer wallet balance credited by full customer price (₹600)
        $this->assertEquals($walletBefore + 600.00, (float) $this->customerA->fresh()->wallet_balance);

        // Escrow transaction marked refunded
        $escrowTx = ProfessionalTransaction::find($freshAppt->escrow_transaction_id);
        $this->assertEquals('refunded', $escrowTx->payment_status);

        // Second resolution attempt MUST BE REJECTED (cannot refund twice)
        $res2 = $this->postJson("/api/admin/monetization/appointments/{$appt->id}/resolve-no-show", [
            'resolution' => 'refund',
            'notes' => 'Duplicate refund attempt',
        ]);

        $res2->assertStatus(422);
        $res2->assertJsonFragment(['message' => 'Appointment is not pending no-show review (current status: cancelled).']);

        // Wallet balance must not increase again
        $this->assertEquals($walletBefore + 600.00, (float) $this->customerA->fresh()->wallet_balance);
    }

    /**
     * 11. Financial Ledger Consistency and Audit Trail Verification
     */
    public function test_financial_ledger_integrity_and_audit_trail_after_resolution(): void
    {
        $serviceOrderService = app(ServiceOrderService::class);

        // Customer A: ₹3,000 initial balance
        $this->assertEquals(3000.00, (float) $this->customerA->wallet_balance);

        // Place deliverable order: ₹600 customer price, ₹500 base, ₹100 markup
        $order = $serviceOrderService->orderDeliverable($this->customerA, $this->deliverableService->id, [
            'order_type' => 'custom',
        ])['order'];

        $this->assertEquals(2400.00, (float) $this->customerA->fresh()->wallet_balance);

        // Astrologer delivers, Customer disputes
        $serviceOrderService->submitDeliverable($order->id, $this->astrologerAdmin->id, 'Draft deliverable');
        $order = $serviceOrderService->disputeOrder($order->id, $this->customerA->id, 'Unacceptable quality');

        // Admin resolves dispute via refund
        Sanctum::actingAs($this->superAdmin, ['*']);
        $res = $this->postJson("/api/admin/monetization/orders/{$order->id}/resolve-dispute", [
            'resolution' => 'refund',
            'notes' => 'Arbitration concluded in customer favor.',
        ]);
        $res->assertStatus(200);

        // Reconcile Wallet Ledger:
        // Initial: 3000, Debit: 600 (balance: 2400), Refund: 600 (balance: 3000)
        $this->assertEquals(3000.00, (float) $this->customerA->fresh()->wallet_balance);

        $walletTxs = UserWalletTransaction::where('service_order_id', $order->id)->orderBy('id', 'asc')->get();
        $this->assertCount(2, $walletTxs);

        $debitTx = $walletTxs[0];
        $this->assertEquals('service_order_debit', $debitTx->transaction_type);
        $this->assertEquals(600.00, (float) $debitTx->amount);
        $this->assertEquals(3000.00, (float) $debitTx->balance_before);
        $this->assertEquals(2400.00, (float) $debitTx->balance_after);
        $this->assertStringStartsWith('ORD-DEB-', $debitTx->reference_id);

        $refundTx = $walletTxs[1];
        $this->assertEquals('refund', $refundTx->transaction_type);
        $this->assertEquals(600.00, (float) $refundTx->amount);
        $this->assertEquals(2400.00, (float) $refundTx->balance_before);
        $this->assertEquals(3000.00, (float) $refundTx->balance_after);
        $this->assertStringStartsWith('ORD-REF-', $refundTx->reference_id);

        // Reconcile Professional Ledger:
        $escrowTx = ProfessionalTransaction::where('service_order_id', $order->id)->first();
        $this->assertEquals('refunded', $escrowTx->payment_status);
        $this->assertEquals(500.00, (float) $escrowTx->amount);
        $this->assertEquals(600.00, (float) $escrowTx->gross_amount);
        $this->assertEquals(100.00, (float) $escrowTx->platform_fee);
        $this->assertStringContainsString('Refunded by dispute resolution', $escrowTx->notes);

        // Reconcile Order Record Audit:
        $freshOrder = $order->fresh();
        $this->assertEquals($this->superAdmin->id, $freshOrder->resolved_by_admin_id);
        $this->assertNotNull($freshOrder->dispute_resolved_at);
        $this->assertEquals('Arbitration concluded in customer favor.', $freshOrder->dispute_resolution_notes);
    }
}
