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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonetizationDeploymentAndAdminWebDashboardTest extends TestCase
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

        // Astrologer
        $this->astrologerAdmin = Admin::create([
            'name' => 'Pt. Vidyadhar',
            'email' => 'vidyadhar@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $this->astrologerAdmin->assignRole('Astrologer');

        $this->astrologer = Astrologer::create([
            'admin_id' => $this->astrologerAdmin->id,
            'name' => 'Pt. Vidyadhar',
            'language' => 'en',
            'astrologer_language' => 'Hindi, Sanskrit',
            'expertise' => 'Vedic, Kundli, Vastu',
            'experience' => '15 years',
            'price' => 500.00,
            'status' => 'online',
            'active' => 1,
            'slug' => 'pt-vidyadhar',
        ]);

        // Super Admin
        $this->superAdmin = Admin::create([
            'name' => 'Root Administrator',
            'email' => 'root@astrosetu.com',
            'password' => bcrypt('adminsecret123'),
        ]);
        $this->superAdmin->assignRole('Super Admin');

        // Customer A & B
        $this->customerA = User::create([
            'name' => 'Vikram Sharma',
            'email' => 'vikram@example.com',
            'mobile_no' => '9876543210',
            'wallet_balance' => 2000.00,
        ]);

        $this->customerB = User::create([
            'name' => 'Rohit Verma',
            'email' => 'rohit@example.com',
            'mobile_no' => '9876543211',
            'wallet_balance' => 2000.00,
        ]);

        // Deliverable Service
        $this->deliverableService = ProfessionalService::create([
            'admin_id' => $this->astrologerAdmin->id,
            'name' => 'Detailed Janam Kundli Report',
            'title' => 'Detailed Janam Kundli Report',
            'service_type' => 'deliverable',
            'category' => 'janam_kundli',
            'price' => 1000.00,
            'pricing_model' => 'fixed',
            'duration_minutes' => 0,
            'delivery_time_days' => 2,
            'is_active' => true,
            'is_public' => true,
            'intake_form_schema' => [
                'fields' => [
                    ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'required' => true],
                ],
            ],
        ]);

        // Appointment Service
        $this->appointmentService = ProfessionalService::create([
            'admin_id' => $this->astrologerAdmin->id,
            'name' => 'Live Kundli Video Consultation',
            'title' => 'Live Kundli Video Consultation',
            'service_type' => 'appointment',
            'category' => 'kundli_consultation',
            'price' => 500.00,
            'pricing_model' => 'fixed',
            'duration_minutes' => 30,
            'delivery_time_days' => null,
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 1. Automatic 24-Hour Expiry Command Tests                                   */
    /* -------------------------------------------------------------------------- */

    public function test_artisan_command_expires_overdue_appointments_and_refunds_wallet(): void
    {
        Carbon::setTestNow('2026-11-20 10:00:00');

        // Customer books appointment
        Sanctum::actingAs($this->customerA);
        $res = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", [
            'start_at' => '2026-11-22 14:00:00',
            'duration_minutes' => 30,
            'client_nonce' => 'nonce-expire-test-01',
        ]);
        $res->assertStatus(201);
        $appointmentId = $res->json('data.id');

        $appt = Appointment::findOrFail($appointmentId);
        $this->assertEquals('pending_confirmation', $appt->status);
        $this->assertEquals('pending', $appt->payment_status);
        $this->assertEquals('2026-11-21 10:00:00', $appt->acceptance_deadline_at->toDateTimeString());

        // Fast forward 24 hours + 1 minute (past acceptance deadline)
        Carbon::setTestNow('2026-11-21 10:01:00');

        // Run artisan command
        $this->artisan('monetization:expire-appointments')
            ->expectsOutputToContain('Successfully expired 1 appointment(s)')
            ->assertExitCode(0);

        // Verify appointment status updated to expired
        $appt->refresh();
        $this->assertEquals('expired', $appt->status);
        $this->assertEquals('refunded', $appt->payment_status);
        $this->assertEquals('system', $appt->cancelled_by);

        // Verify customer wallet refunded in full (₹2000.00 restored)
        $this->customerA->refresh();
        $this->assertEquals(2000.00, (float) $this->customerA->wallet_balance);

        // Verify refund ledger transaction
        $refundTx = UserWalletTransaction::where('appointment_id', $appt->id)
            ->where('transaction_type', 'refund')
            ->first();
        $this->assertNotNull($refundTx);
        $this->assertEquals(600.00, (float) $refundTx->amount);

        // Verify escrow transaction marked refunded
        $escrowTx = ProfessionalTransaction::find($appt->escrow_transaction_id);
        $this->assertEquals('refunded', $escrowTx->payment_status);

        // Re-run command: must be strictly idempotent (0 expired, no duplicate refund)
        $this->artisan('monetization:expire-appointments')
            ->expectsOutputToContain('No overdue pending appointments found.')
            ->assertExitCode(0);

        $this->customerA->refresh();
        $this->assertEquals(2000.00, (float) $this->customerA->wallet_balance);
        $this->assertEquals(1, UserWalletTransaction::where('appointment_id', $appt->id)->where('transaction_type', 'refund')->count());
    }

    public function test_artisan_command_does_not_expire_appointments_within_acceptance_window(): void
    {
        Carbon::setTestNow('2026-11-20 10:00:00');

        Sanctum::actingAs($this->customerA);
        $res = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", [
            'start_at' => '2026-11-22 14:00:00',
            'duration_minutes' => 30,
            'client_nonce' => 'nonce-within-window-01',
        ]);
        $res->assertStatus(201);
        $appointmentId = $res->json('data.id');

        // Fast forward 12 hours (still 12 hours remaining before acceptance deadline)
        Carbon::setTestNow('2026-11-20 22:00:00');

        $this->artisan('monetization:expire-appointments')
            ->expectsOutputToContain('No overdue pending appointments found.')
            ->assertExitCode(0);

        $appt = Appointment::findOrFail($appointmentId);
        $this->assertEquals('pending_confirmation', $appt->status);
        $this->customerA->refresh();
        $this->assertEquals(1400.00, (float) $this->customerA->wallet_balance);
    }

    /* -------------------------------------------------------------------------- */
    /* 2. Nonce Re-use Payload Tampering Tests                                    */
    /* -------------------------------------------------------------------------- */

    public function test_checkout_rejects_nonce_reused_with_different_service_id(): void
    {
        Sanctum::actingAs($this->customerA);

        // Service 2
        $service2 = ProfessionalService::create([
            'admin_id' => $this->astrologerAdmin->id,
            'name' => 'Vastu Audit Consultation',
            'title' => 'Vastu Audit Consultation',
            'service_type' => 'appointment',
            'category' => 'vastu',
            'price' => 800.00,
            'pricing_model' => 'fixed',
            'duration_minutes' => 60,
            'is_active' => true,
            'is_public' => true,
        ]);

        $nonce = 'nonce-tamper-payload-service-01';

        // 1. Initial checkout with appointmentService
        $res1 = $this->postJson("/api/services/{$this->appointmentService->id}/book-appointment", [
            'start_at' => '2026-11-25 10:00:00',
            'duration_minutes' => 30,
            'client_nonce' => $nonce,
        ]);
        $res1->assertStatus(201);

        // 2. Tampered checkout with the same nonce but different service
        $res2 = $this->postJson("/api/services/{$service2->id}/book-appointment", [
            'start_at' => '2026-11-25 12:00:00',
            'duration_minutes' => 60,
            'client_nonce' => $nonce,
        ]);
        $res2->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonFragment([
                'message' => 'Idempotency conflict: Client nonce has already been used for a different appointment booking.',
            ]);
    }

    public function test_deliverable_checkout_rejects_nonce_reused_with_different_service(): void
    {
        Sanctum::actingAs($this->customerA);

        $deliverable2 = ProfessionalService::create([
            'admin_id' => $this->astrologerAdmin->id,
            'name' => 'Varshphal Yearly Horoscope',
            'title' => 'Varshphal Yearly Horoscope',
            'service_type' => 'deliverable',
            'category' => 'janam_kundli',
            'price' => 1500.00,
            'pricing_model' => 'fixed',
            'duration_minutes' => 0,
            'delivery_time_days' => 3,
            'is_active' => true,
            'is_public' => true,
        ]);

        $nonce = 'nonce-tamper-deliverable-01';

        // 1. Initial checkout
        $res1 = $this->postJson("/api/services/{$this->deliverableService->id}/order-deliverable", [
            'notes' => 'DOB: 1990-01-01',
            'client_nonce' => $nonce,
        ]);
        $res1->assertStatus(201);

        // 2. Tampered checkout with different service
        $res2 = $this->postJson("/api/services/{$deliverable2->id}/order-deliverable", [
            'notes' => 'DOB: 1990-01-01',
            'client_nonce' => $nonce,
        ]);
        $res2->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Idempotency conflict: Client nonce has already been used for a different service order.',
            ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 3. Super Admin Web Dashboard Authorization & Adjudication                  */
    /* -------------------------------------------------------------------------- */

    public function test_super_admin_can_access_monetization_web_dashboard(): void
    {
        // 1. Unauthenticated redirect to login
        $resGuest = $this->get('/admin/monetization');
        $resGuest->assertRedirect('/login');

        // 2. Astrologer receives 403 Forbidden
        $this->actingAs($this->astrologerAdmin, 'admin');
        $resAstrologer = $this->get('/admin/monetization');
        $resAstrologer->assertStatus(403);

        // 3. Super Admin accesses successfully
        $this->actingAs($this->superAdmin, 'admin');
        $resSuperAdmin = $this->get('/admin/monetization');
        $resSuperAdmin->assertStatus(200)
            ->assertSee('Service Monetization Review')
            ->assertSee('Disputed Orders')
            ->assertSee('Late Cancellations')
            ->assertSee('No-Show Reports');
    }

    public function test_super_admin_can_view_and_resolve_order_dispute_via_web(): void
    {
        // Setup a disputed order
        $order = ProfessionalServiceOrder::create([
            'order_number' => 'ORD-DISPUTE-WEB-01',
            'user_id' => $this->customerA->id,
            'admin_id' => $this->astrologerAdmin->id,
            'service_id' => $this->deliverableService->id,
            'base_price' => 1000.00,
            'platform_fee' => 200.00,
            'platform_markup' => 200.00,
            'customer_price' => 1200.00,
            'status' => 'disputed',
            'order_type' => 'custom',
            'notes' => 'DOB: 1992-05-15',
            'deliverable_text' => 'Here is your brief report.',
            'delivered_at' => Carbon::now()->subHours(2),
            'disputed_at' => Carbon::now()->subHour(),
            'dispute_reason' => 'Report was extremely generic and inaccurate.',
        ]);

        $escrowTx = ProfessionalTransaction::create([
            'admin_id' => $this->astrologerAdmin->id,
            'transaction_type' => 'service_earning',
            'payment_method' => 'wallet',
            'transaction_at' => Carbon::now(),
            'reference_id' => 'ESCROW-DISPUTE-01',
            'amount' => 1000.00,
            'payment_status' => 'pending',
            'notes' => 'Escrow for order #' . $order->order_number,
        ]);
        $order->escrow_transaction_id = $escrowTx->id;
        $order->save();

        $this->actingAs($this->superAdmin, 'admin');

        // View adjudication page
        $resView = $this->get("/admin/monetization/orders/{$order->id}");
        $resView->assertStatus(200)
            ->assertSee('Order Dispute: ORD-DISPUTE-WEB-01')
            ->assertSee('Report was extremely generic and inaccurate.')
            ->assertSee('Commercial Breakdown');

        // Submit Refund Adjudication via Web Form
        $resResolve = $this->post("/admin/monetization/orders/{$order->id}/resolve", [
            'resolution' => 'refund',
            'notes' => 'Report lacked required astrological planetary calculations. Full customer refund granted.',
        ]);

        $resResolve->assertRedirect(route('admin.monetization.orders.show', $order->id));
        $resResolve->assertSessionHas('success');

        // Verify order status
        $order->refresh();
        $this->assertEquals('refunded', $order->status);
        $this->assertEquals('resolved_refund', $order->dispute_status);
        $this->assertEquals($this->superAdmin->id, $order->resolved_by_admin_id);

        // Verify customer wallet refunded ₹1200
        $this->customerA->refresh();
        $this->assertEquals(3200.00, (float) $this->customerA->wallet_balance);

        // Verify escrow cancelled
        $escrowTx->refresh();
        $this->assertEquals('refunded', $escrowTx->payment_status);
    }

    public function test_super_admin_can_view_and_resolve_late_cancellation_via_web(): void
    {
        $appt = Appointment::create([
            'title' => 'Live Kundli Video Consultation',
            'user_id' => $this->customerA->id,
            'admin_id' => $this->astrologerAdmin->id,
            'service_id' => $this->appointmentService->id,
            'base_price' => 500.00,
            'customer_price' => 600.00,
            'start_at' => Carbon::now()->addHours(3),
            'end_at' => Carbon::now()->addHours(3)->addMinutes(30),
            'duration_minutes_snapshot' => 30,
            'status' => 'cancelled_pending_review',
            'payment_status' => 'paid',
            'cancelled_by' => 'user',
            'cancelled_at' => Carbon::now(),
            'cancellation_reason' => 'Emergency meeting came up suddenly.',
        ]);

        $escrowTx = ProfessionalTransaction::create([
            'admin_id' => $this->astrologerAdmin->id,
            'transaction_type' => 'service_earning',
            'payment_method' => 'wallet',
            'transaction_at' => Carbon::now(),
            'reference_id' => 'ESCROW-APPT-LATE-01',
            'amount' => 500.00,
            'payment_status' => 'pending',
            'notes' => 'Escrow for appointment #' . $appt->id,
        ]);
        $appt->escrow_transaction_id = $escrowTx->id;
        $appt->save();

        $this->actingAs($this->superAdmin, 'admin');

        // View show page
        $resView = $this->get("/admin/monetization/appointments/{$appt->id}");
        $resView->assertStatus(200)
            ->assertSee('Late Cancellation Review')
            ->assertSee('12-hour cancellation cutoff');

        // Resolve: release payout to astrologer due to late customer default
        $resResolve = $this->post("/admin/monetization/appointments/{$appt->id}/resolve-cancellation", [
            'resolution' => 'payout',
            'notes' => 'Customer cancelled only 3 hours before consultation without medical note. Payout released to practitioner.',
        ]);

        $resResolve->assertRedirect(route('admin.monetization.appointments.show', $appt->id));
        $resResolve->assertSessionHas('success');

        $appt->refresh();
        $this->assertEquals('cancelled', $appt->status);
        $this->assertEquals('resolved_payout', $appt->cancellation_review_status);
        $this->assertEquals('paid', $appt->payment_status);

        // Verify escrow released to practitioner
        $escrowTx->refresh();
        $this->assertEquals('completed', $escrowTx->payment_status);
    }

    public function test_super_admin_can_view_and_resolve_noshow_via_web(): void
    {
        $appt = Appointment::create([
            'title' => 'Live Kundli Video Consultation',
            'user_id' => $this->customerA->id,
            'admin_id' => $this->astrologerAdmin->id,
            'service_id' => $this->appointmentService->id,
            'base_price' => 500.00,
            'customer_price' => 600.00,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1)->subMinutes(30),
            'duration_minutes_snapshot' => 30,
            'status' => 'no_show_pending_review',
            'payment_status' => 'paid',
            'no_show_reported_by' => 'user',
            'no_show_reported_at' => Carbon::now()->subHour(),
            'no_show_notes' => 'Astrologer never joined the video session.',
        ]);

        $escrowTx = ProfessionalTransaction::create([
            'admin_id' => $this->astrologerAdmin->id,
            'transaction_type' => 'service_earning',
            'payment_method' => 'wallet',
            'transaction_at' => Carbon::now(),
            'reference_id' => 'ESCROW-APPT-NOSHOW-01',
            'amount' => 500.00,
            'payment_status' => 'pending',
            'notes' => 'Escrow for appointment #' . $appt->id,
        ]);
        $appt->escrow_transaction_id = $escrowTx->id;
        $appt->save();

        $this->actingAs($this->superAdmin, 'admin');

        // View show page
        $resView = $this->get("/admin/monetization/appointments/{$appt->id}");
        $resView->assertStatus(200)
            ->assertSee('No-Show Claim Review')
            ->assertSee('Astrologer never joined the video session.');

        // Resolve: issue full customer refund
        $resResolve = $this->post("/admin/monetization/appointments/{$appt->id}/resolve-noshow", [
            'resolution' => 'refund',
            'notes' => 'Verified practitioner absence from video logs. Full refund granted to customer.',
        ]);

        $resResolve->assertRedirect(route('admin.monetization.appointments.show', $appt->id));
        $resResolve->assertSessionHas('success');

        $appt->refresh();
        $this->assertEquals('cancelled', $appt->status);
        $this->assertEquals('resolved_refund', $appt->no_show_review_status);
        $this->assertEquals('refunded', $appt->payment_status);

        // Verify customer wallet refunded ₹600
        $this->customerA->refresh();
        $this->assertEquals(2600.00, (float) $this->customerA->wallet_balance);

        // Verify escrow cancelled
        $escrowTx->refresh();
        $this->assertEquals('refunded', $escrowTx->payment_status);
    }
}
