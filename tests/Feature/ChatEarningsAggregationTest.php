<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\Chat;
use App\Models\ChatSession;
use App\Models\CustomerAstrologerConsent;
use App\Models\Jajman;
use App\Models\ProfessionalService;
use App\Models\ProfessionalTransaction;
use App\Models\User;
use App\Models\UserAstrologerChatAllowance;
use App\Services\Chat\ChatServiceProvisioner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatEarningsAggregationTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerA;
    protected User $customerB;
    protected Admin $practitioner;
    protected Astrologer $astrologer;
    protected ProfessionalService $chatService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        $this->practitioner = Admin::create([
            'name' => 'Acharya Raman',
            'email' => 'raman@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $this->practitioner->assignRole('Astrologer');

        $this->astrologer = Astrologer::create([
            'admin_id' => $this->practitioner->id,
            'name' => 'Acharya Raman',
            'language' => 'en',
            'astrologer_language' => 'Hindi, English',
            'expertise' => 'Vedic Astrology',
            'experience' => '12 years',
            'price' => 10.00,
            'status' => 'online',
            'active' => 1,
        ]);

        $this->chatService = app(ChatServiceProvisioner::class)->provisionForAstrologer($this->astrologer);

        $this->customerA = User::create([
            'name' => 'Aarav Sharma',
            'email' => 'aarav@example.com',
            'phone' => '+919876543210',
            'password' => bcrypt('secret123'),
            'wallet_balance' => 500.00,
        ]);

        $this->customerB = User::create([
            'name' => 'Kavita Patel',
            'email' => 'kavita@example.com',
            'phone' => '+919876543211',
            'password' => bcrypt('secret123'),
            'wallet_balance' => 500.00,
        ]);
    }

    /**
     * Test 1: Paid chat message automatically populates jajman_id when linked Jajman exists.
     */
    public function test_paid_chat_message_associates_jajman_id_when_linked_jajman_exists(): void
    {
        $jajman = Jajman::create([
            'user_id' => $this->customerA->id,
            'name' => 'Aarav Sharma',
            'phone' => '+919876543210',
            'city' => 'Varanasi',
        ]);
        $jajman->professionals()->attach($this->practitioner->id, ['status' => 'active', 'source' => 'manual']);

        // Mark 5 free messages used up
        UserAstrologerChatAllowance::create([
            'user_id' => $this->customerA->id,
            'astrologer_id' => $this->astrologer->id,
            'free_messages_used' => 5,
        ]);

        $session = ChatSession::create([
            'user_id' => $this->customerA->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => now(),
        ]);

        $this->actingAs($this->customerA, 'sanctum');
        $res = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $session->id,
            'message' => 'Hello Acharya ji, need paid consultation',
            'client_nonce' => 'nonce-task-d1-paid-1',
        ]);

        $res->assertStatus(201);

        $profTx = ProfessionalTransaction::where('chat_session_id', $session->id)->first();
        $this->assertNotNull($profTx);
        $this->assertEquals($jajman->id, $profTx->jajman_id);
        $this->assertEquals($this->customerA->id, $profTx->user_id);
        $this->assertEquals(0.10, (float) $profTx->amount);
        $this->assertEquals(0.12, (float) $profTx->gross_amount);
        $this->assertEquals(0.02, (float) $profTx->platform_fee);
    }

    /**
     * Test 2: Chat earnings grouped by session returns accurate totals and drilldown.
     */
    public function test_chat_earnings_grouped_by_session_returns_accurate_totals_and_drilldown(): void
    {
        $session1 = ChatSession::create([
            'user_id' => $this->customerA->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => now()->subHours(2),
        ]);

        $session2 = ChatSession::create([
            'user_id' => $this->customerB->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => now()->subHours(1),
        ]);

        // Session 1: 2 transactions (₹10 net each, ₹12 gross each)
        for ($i = 1; $i <= 2; $i++) {
            $chat = Chat::create([
                'chat_session_id' => $session1->id,
                'user_id' => $this->customerA->id,
                'admin_id' => $this->practitioner->id,
                'astrologer_id' => $this->astrologer->id,
                'message' => "Message S1-$i",
                'sender' => 'user',
                'billing_type' => 'paid',
                'base_price' => 10.00,
                'platform_markup' => 2.00,
                'customer_price' => 12.00,
                'sent_at' => now(),
            ]);

            ProfessionalTransaction::create([
                'admin_id' => $this->practitioner->id,
                'user_id' => $this->customerA->id,
                'service_id' => $this->chatService->id,
                'chat_session_id' => $session1->id,
                'chat_id' => $chat->id,
                'transaction_type' => 'chat_message',
                'payment_method' => 'wallet',
                'payment_status' => 'completed',
                'amount' => 10.00,
                'gross_amount' => 12.00,
                'platform_fee' => 2.00,
                'net_amount' => 10.00,
                'currency' => 'INR',
                'transaction_at' => now(),
                'reference_id' => "EARN_CHAT_{$chat->id}",
            ]);
        }

        // Session 2: 1 transaction (₹25 net, ₹30 gross)
        $chat2 = Chat::create([
            'chat_session_id' => $session2->id,
            'user_id' => $this->customerB->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'message' => 'Message S2-1',
            'sender' => 'user',
            'billing_type' => 'paid',
            'base_price' => 25.00,
            'platform_markup' => 5.00,
            'customer_price' => 30.00,
            'sent_at' => now(),
        ]);

        ProfessionalTransaction::create([
            'admin_id' => $this->practitioner->id,
            'user_id' => $this->customerB->id,
            'service_id' => $this->chatService->id,
            'chat_session_id' => $session2->id,
            'chat_id' => $chat2->id,
            'transaction_type' => 'chat_message',
            'payment_method' => 'wallet',
            'payment_status' => 'completed',
            'amount' => 25.00,
            'gross_amount' => 30.00,
            'platform_fee' => 5.00,
            'net_amount' => 25.00,
            'currency' => 'INR',
            'transaction_at' => now(),
            'reference_id' => "EARN_CHAT_{$chat2->id}",
        ]);

        $this->actingAs($this->practitioner, 'sanctum');

        $res = $this->getJson('/api/astrologer/payments/chat-earnings?group_by=session');
        $res->assertOk();
        $res->assertJsonStructure([
            'success',
            'summary' => [
                'period',
                'group_by',
                'timezone',
                'total_gross_amount',
                'total_platform_fee',
                'total_completed_earnings',
                'total_pending_earnings',
                'total_refunded_amount',
                'total_net_earnings',
                'total_transactions',
                'total_sessions',
                'total_customers',
            ],
            'data',
            'meta',
        ]);

        $this->assertEquals(45.00, (float) $res->json('summary.total_net_earnings'));
        $this->assertEquals(54.00, (float) $res->json('summary.total_gross_amount'));
        $this->assertEquals(9.00, (float) $res->json('summary.total_platform_fee'));
        $this->assertEquals(2, $res->json('summary.total_sessions'));
        $this->assertEquals(3, $res->json('summary.total_transactions'));
        $this->assertCount(2, $res->json('data'));

        // Test drill-down with session filter and underlying transactions
        $drillRes = $this->getJson("/api/astrologer/payments/chat-earnings?chat_session_id={$session1->id}&with_transactions=true");
        $drillRes->assertOk();
        $this->assertCount(1, $drillRes->json('data'));
        $this->assertEquals(20.00, (float) $drillRes->json('data.0.net_earnings'));
        $this->assertEquals(2, $drillRes->json('data.0.message_count'));
        $this->assertArrayHasKey('transactions', $drillRes->json('data.0'));
        $this->assertCount(2, $drillRes->json('data.0.transactions'));
    }

    /**
     * Test 3: Chat earnings grouped by customer consolidates multiple sessions for same customer.
     */
    public function test_chat_earnings_grouped_by_customer_consolidates_multiple_sessions(): void
    {
        $session1 = ChatSession::create([
            'user_id' => $this->customerA->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => now()->subDays(2),
        ]);

        $session2 = ChatSession::create([
            'user_id' => $this->customerA->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => now()->subDay(),
        ]);

        // Session 1: ₹20
        ProfessionalTransaction::create([
            'admin_id' => $this->practitioner->id,
            'user_id' => $this->customerA->id,
            'service_id' => $this->chatService->id,
            'chat_session_id' => $session1->id,
            'transaction_type' => 'chat_message',
            'payment_method' => 'wallet',
            'payment_status' => 'completed',
            'amount' => 20.00,
            'gross_amount' => 24.00,
            'platform_fee' => 4.00,
            'net_amount' => 20.00,
            'currency' => 'INR',
            'transaction_at' => now(),
            'reference_id' => 'TX_CUST_1',
        ]);

        // Session 2: ₹15
        ProfessionalTransaction::create([
            'admin_id' => $this->practitioner->id,
            'user_id' => $this->customerA->id,
            'service_id' => $this->chatService->id,
            'chat_session_id' => $session2->id,
            'transaction_type' => 'chat_message',
            'payment_method' => 'wallet',
            'payment_status' => 'completed',
            'amount' => 15.00,
            'gross_amount' => 18.00,
            'platform_fee' => 3.00,
            'net_amount' => 15.00,
            'currency' => 'INR',
            'transaction_at' => now(),
            'reference_id' => 'TX_CUST_2',
        ]);

        $this->actingAs($this->practitioner, 'sanctum');

        $res = $this->getJson('/api/astrologer/payments/chat-earnings?group_by=customer');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));

        $custItem = $res->json('data.0');
        $this->assertEquals($this->customerA->id, $custItem['user_id']);
        $this->assertEquals(2, $custItem['session_count']);
        $this->assertEquals(2, $custItem['transaction_count']);
        $this->assertEquals(35.00, (float) $custItem['net_earnings']);
        $this->assertEquals(42.00, (float) $custItem['gross_amount']);
        $this->assertEquals(7.00, (float) $custItem['platform_fee']);
    }

    /**
     * Test 4: Full and partial refunds are accounted for accurately without double-counting.
     */
    public function test_refunds_against_chat_transactions_are_deducted_without_double_counting(): void
    {
        $session = ChatSession::create([
            'user_id' => $this->customerA->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => now(),
        ]);

        $tx = ProfessionalTransaction::create([
            'admin_id' => $this->practitioner->id,
            'user_id' => $this->customerA->id,
            'service_id' => $this->chatService->id,
            'chat_session_id' => $session->id,
            'transaction_type' => 'chat_message',
            'payment_method' => 'wallet',
            'payment_status' => 'completed',
            'amount' => 50.00,
            'gross_amount' => 60.00,
            'platform_fee' => 10.00,
            'net_amount' => 50.00,
            'currency' => 'INR',
            'transaction_at' => now(),
            'reference_id' => 'TX_REFUND_PARENT',
        ]);

        $this->actingAs($this->practitioner, 'sanctum');

        // Issue ₹15 partial refund
        $refundRes = $this->postJson("/api/astrologer/payments/{$tx->id}/refund", [
            'amount' => 15.00,
            'reason' => 'Partial session disruption refund',
        ]);
        $refundRes->assertStatus(201);

        $res = $this->getJson('/api/astrologer/payments/chat-earnings?group_by=session');
        $res->assertOk();

        $this->assertEquals(50.00, (float) $res->json('summary.total_completed_earnings'));
        $this->assertEquals(15.00, (float) $res->json('summary.total_refunded_amount'));
        $this->assertEquals(35.00, (float) $res->json('summary.total_net_earnings'));

        $sessionData = $res->json('data.0');
        $this->assertEquals(35.00, (float) $sessionData['net_earnings']);
        $this->assertEquals(15.00, (float) $sessionData['refunded_amount']);
        $this->assertEquals(2, $sessionData['transaction_count']); // 1 payment + 1 refund
        $this->assertEquals(1, $sessionData['status_summary']['partially_refunded']);
    }

    /**
     * Test 5: Timezone-aware date filtering works for today, yesterday, and custom IST ranges.
     */
    public function test_timezone_aware_date_filtering_today_yesterday_and_custom_ist(): void
    {
        $tz = 'Asia/Kolkata';
        $nowIst = Carbon::now($tz);

        $sessionToday = ChatSession::create([
            'user_id' => $this->customerA->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => $nowIst->copy()->subMinutes(10)->setTimezone('UTC'),
        ]);

        $sessionYesterday = ChatSession::create([
            'user_id' => $this->customerB->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => $nowIst->copy()->subDay()->setTimezone('UTC'),
        ]);

        // Transaction today in IST
        ProfessionalTransaction::create([
            'admin_id' => $this->practitioner->id,
            'user_id' => $this->customerA->id,
            'service_id' => $this->chatService->id,
            'chat_session_id' => $sessionToday->id,
            'transaction_type' => 'chat_message',
            'payment_method' => 'wallet',
            'payment_status' => 'completed',
            'amount' => 10.00,
            'currency' => 'INR',
            'transaction_at' => $nowIst->copy()->setTimezone('UTC'),
            'reference_id' => 'TX_TODAY',
        ]);

        // Transaction yesterday in IST
        ProfessionalTransaction::create([
            'admin_id' => $this->practitioner->id,
            'user_id' => $this->customerB->id,
            'service_id' => $this->chatService->id,
            'chat_session_id' => $sessionYesterday->id,
            'transaction_type' => 'chat_message',
            'payment_method' => 'wallet',
            'payment_status' => 'completed',
            'amount' => 20.00,
            'currency' => 'INR',
            'transaction_at' => $nowIst->copy()->subDay()->setTimezone('UTC'),
            'reference_id' => 'TX_YESTERDAY',
        ]);

        $this->actingAs($this->practitioner, 'sanctum');

        // Query today
        $resToday = $this->getJson('/api/astrologer/payments/chat-earnings?period=today&timezone=Asia/Kolkata');
        $resToday->assertOk();
        $this->assertEquals(10.00, (float) $resToday->json('summary.total_net_earnings'));
        $this->assertEquals(1, $resToday->json('summary.total_transactions'));

        // Query yesterday
        $resYesterday = $this->getJson('/api/astrologer/payments/chat-earnings?period=yesterday&timezone=Asia/Kolkata');
        $resYesterday->assertOk();
        $this->assertEquals(20.00, (float) $resYesterday->json('summary.total_net_earnings'));
        $this->assertEquals(1, $resYesterday->json('summary.total_transactions'));

        // Custom range covering both
        $fromStr = $nowIst->copy()->subDays(2)->toDateString();
        $toStr = $nowIst->copy()->toDateString();
        $resCustom = $this->getJson("/api/astrologer/payments/chat-earnings?period=custom&from={$fromStr}&to={$toStr}&timezone=Asia/Kolkata");
        $resCustom->assertOk();
        $this->assertEquals(30.00, (float) $resCustom->json('summary.total_net_earnings'));
        $this->assertEquals(2, $resCustom->json('summary.total_transactions'));
    }

    /**
     * Test 6: Task B2 consent privacy masks unconsented consumer contact fields while keeping financial audit data.
     */
    public function test_task_b2_consent_privacy_masks_unconsented_consumer_contact_fields(): void
    {
        $session = ChatSession::create([
            'user_id' => $this->customerA->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => now(),
        ]);

        $tx = ProfessionalTransaction::create([
            'admin_id' => $this->practitioner->id,
            'user_id' => $this->customerA->id,
            'service_id' => $this->chatService->id,
            'chat_session_id' => $session->id,
            'transaction_type' => 'chat_message',
            'payment_method' => 'wallet',
            'payment_status' => 'completed',
            'amount' => 15.00,
            'gross_amount' => 18.00,
            'platform_fee' => 3.00,
            'net_amount' => 15.00,
            'currency' => 'INR',
            'transaction_at' => now(),
            'reference_id' => 'TX_CONSENT_MASK',
        ]);

        $this->actingAs($this->practitioner, 'sanctum');

        // 1. Without consent: phone number MUST be null/masked
        $resEarnings = $this->getJson('/api/astrologer/payments/chat-earnings?group_by=session');
        $resEarnings->assertOk();
        $this->assertNull($resEarnings->json('data.0.customer_phone'));
        $this->assertEquals('Aarav Sharma', $resEarnings->json('data.0.customer_name'));
        $this->assertEquals(15.00, (float) $resEarnings->json('data.0.net_earnings'));

        // Payments list
        $resList = $this->getJson('/api/astrologer/payments');
        $resList->assertOk();
        $this->assertNull($resList->json('data.0.user.phone'));
        $this->assertEquals(15.00, (float) $resList->json('data.0.amount'));

        // Payments show
        $resShow = $this->getJson("/api/astrologer/payments/{$tx->id}");
        $resShow->assertOk();
        $this->assertNull($resShow->json('data.user.phone'));

        // 2. Grant active consent
        CustomerAstrologerConsent::create([
            'user_id' => $this->customerA->id,
            'admin_id' => $this->practitioner->id,
            'status' => CustomerAstrologerConsent::STATUS_GRANTED,
            'scope' => CustomerAstrologerConsent::SCOPE_PERSONAL_BIRTH_PROFILE,
            'consented_at' => now(),
        ]);

        // Phone number should now be visible
        $resEarningsAfter = $this->getJson('/api/astrologer/payments/chat-earnings?group_by=session');
        $resEarningsAfter->assertOk();
        $this->assertEquals('+919876543210', $resEarningsAfter->json('data.0.customer_phone'));

        $resListAfter = $this->getJson('/api/astrologer/payments');
        $resListAfter->assertOk();
        $this->assertEquals('+919876543210', $resListAfter->json('data.0.user.phone'));
    }

    /**
     * Test 7: Manual offline Jajman contact details are preserved without consent masking.
     */
    public function test_manual_offline_jajman_contact_details_are_not_masked(): void
    {
        $manualJajman = Jajman::create([
            'user_id' => null, // practitioner's offline record
            'name' => 'Sohan Lal',
            'phone' => '+919999988888',
            'city' => 'Mathura',
        ]);
        $manualJajman->professionals()->attach($this->practitioner->id, ['status' => 'active', 'source' => 'manual']);

        $tx = ProfessionalTransaction::create([
            'admin_id' => $this->practitioner->id,
            'jajman_id' => $manualJajman->id,
            'transaction_type' => 'general_payment',
            'payment_method' => 'cash',
            'payment_status' => 'completed',
            'amount' => 500.00,
            'currency' => 'INR',
            'transaction_at' => now(),
            'reference_id' => 'MANUAL_CASH_01',
        ]);

        $this->actingAs($this->practitioner, 'sanctum');

        $res = $this->getJson('/api/astrologer/payments');
        $res->assertOk();
        $this->assertEquals('+919999988888', $res->json('data.0.jajman.phone'));
        $this->assertEquals('Sohan Lal', $res->json('data.0.jajman.name'));
    }
}
