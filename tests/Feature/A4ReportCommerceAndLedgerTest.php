<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KundliReport;
use App\Models\ReportPurchase;
use App\Models\User;
use App\Services\Astrology\Reports\Exceptions\DuplicateRefundException;
use App\Services\Astrology\Reports\Exceptions\InsufficientWalletBalanceException;
use App\Services\Astrology\Reports\ReportPurchaseService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class A4ReportCommerceAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected ReportPurchaseService $purchaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purchaseService = app(ReportPurchaseService::class);
    }

    /**
     * Test successful wallet purchase:
     * Balance is deducted, debit record created, kundli_reports entry queued.
     */
    public function test_successful_wallet_purchase_deducts_balance_and_creates_records(): void
    {
        $user = User::factory()->create([
            'wallet_balance' => 200.00,
        ]);

        $birthPayload = [
            'name' => 'Aditi Sharma',
            'date_of_birth' => '1995-08-15',
            'birth_time' => '14:30:00',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ];

        $idempotencyKey = 'purchase_test_' . Str::random(16);

        $result = $this->purchaseService->purchaseReport(
            user: $user,
            birthPayload: $birthPayload,
            idempotencyKey: $idempotencyKey,
            language: 'en',
            amount: 149.00
        );

        $this->assertFalse($result['is_duplicate']);
        $this->assertInstanceOf(ReportPurchase::class, $result['purchase']);
        $this->assertInstanceOf(KundliReport::class, $result['report']);

        // Assert user wallet balance reduced by exact amount
        $this->assertEquals(51.00, (float) $user->fresh()->wallet_balance);

        // Assert authoritative debit ledger record
        $this->assertDatabaseHas('report_purchases', [
            'id' => $result['purchase']->id,
            'user_id' => $user->id,
            'idempotency_key' => $idempotencyKey,
            'transaction_type' => 'debit',
            'amount' => 149.00,
            'currency' => 'INR',
            'related_purchase_id' => null,
            'status' => 'completed',
        ]);

        // Assert report delivery row
        $this->assertDatabaseHas('kundli_reports', [
            'id' => $result['report']->id,
            'user_id' => $user->id,
            'report_purchase_id' => $result['purchase']->id,
            'status' => 'queued',
            'language' => 'en',
        ]);
    }

    /**
     * Test insufficient wallet balance:
     * Request rejected, no deduction, no purchase record created.
     */
    public function test_insufficient_wallet_balance_rejects_purchase(): void
    {
        $user = User::factory()->create([
            'wallet_balance' => 50.00, // Less than ₹149
        ]);

        $birthPayload = ['name' => 'Rahul Verma'];
        $idempotencyKey = 'purchase_insufficient_' . Str::random(16);

        $this->expectException(InsufficientWalletBalanceException::class);

        try {
            $this->purchaseService->purchaseReport(
                user: $user,
                birthPayload: $birthPayload,
                idempotencyKey: $idempotencyKey,
                amount: 149.00
            );
        } finally {
            // Balance remains untouched
            $this->assertEquals(50.00, (float) $user->fresh()->wallet_balance);
            $this->assertDatabaseMissing('report_purchases', ['idempotency_key' => $idempotencyKey]);
            $this->assertEquals(0, KundliReport::count());
        }
    }

    /**
     * Test duplicate idempotency key:
     * Returns existing purchase and report without duplicate wallet deduction.
     */
    public function test_duplicate_idempotency_key_prevents_duplicate_deduction(): void
    {
        $user = User::factory()->create([
            'wallet_balance' => 500.00,
        ]);

        $birthPayload = ['name' => 'Sanjay Patel'];
        $idempotencyKey = 'idempotent_test_key_123';

        // First purchase
        $res1 = $this->purchaseService->purchaseReport($user, $birthPayload, $idempotencyKey, 'en', 149.00);
        $this->assertFalse($res1['is_duplicate']);
        $this->assertEquals(351.00, (float) $user->fresh()->wallet_balance);

        // Repeated purchase with identical idempotency key
        $res2 = $this->purchaseService->purchaseReport($user, $birthPayload, $idempotencyKey, 'en', 149.00);
        $this->assertTrue($res2['is_duplicate']);

        // Assert IDs match exactly
        $this->assertSame($res1['purchase']->id, $res2['purchase']->id);
        $this->assertSame($res1['report']->id, $res2['report']->id);

        // Assert balance deducted ONLY ONCE (remains 351.00)
        $this->assertEquals(351.00, (float) $user->fresh()->wallet_balance);
        $this->assertEquals(1, ReportPurchase::where('idempotency_key', $idempotencyKey)->count());
        $this->assertEquals(1, KundliReport::count());
    }

    /**
     * Test successful compensating refund:
     * Recredits wallet balance, creates refund ledger record, and marks report failed.
     */
    public function test_successful_compensating_refund_credits_wallet(): void
    {
        $user = User::factory()->create([
            'wallet_balance' => 200.00,
        ]);

        $purchaseResult = $this->purchaseService->purchaseReport(
            user: $user,
            birthPayload: ['name' => 'Pooja Iyer'],
            idempotencyKey: 'purchase_for_refund_' . Str::random(10),
            amount: 149.00
        );

        $this->assertEquals(51.00, (float) $user->fresh()->wallet_balance);
        $debit = $purchaseResult['purchase'];
        $report = $purchaseResult['report'];

        // Issue compensating refund
        $refund = $this->purchaseService->refundPurchase($debit, 'Engine timeout error');

        $this->assertInstanceOf(ReportPurchase::class, $refund);
        $this->assertSame('compensating_refund', $refund->transaction_type);
        $this->assertSame($debit->id, $refund->related_purchase_id);
        $this->assertEquals(149.00, (float) $refund->amount);

        // User balance credited back to original 200.00
        $this->assertEquals(200.00, (float) $user->fresh()->wallet_balance);

        // Report marked failed
        $this->assertSame('failed', $report->fresh()->status);
        $this->assertStringContainsString('Engine timeout error', (string) $report->fresh()->error_message);
    }

    /**
     * Test duplicate refund prevention at application and database level:
     * Calling refund twice throws DuplicateRefundException, second insert rejected by UNIQUE constraint.
     */
    public function test_duplicate_refund_prevention(): void
    {
        $user = User::factory()->create(['wallet_balance' => 300.00]);
        $purchaseResult = $this->purchaseService->purchaseReport(
            user: $user,
            birthPayload: ['name' => 'Vikram Seth'],
            idempotencyKey: 'debit_single_refund_' . Str::random(10),
            amount: 149.00
        );

        $debit = $purchaseResult['purchase'];

        // First refund succeeds
        $this->purchaseService->refundPurchase($debit);
        $this->assertEquals(300.00, (float) $user->fresh()->wallet_balance);

        // Second refund fails via application check
        $this->expectException(DuplicateRefundException::class);
        $this->purchaseService->refundPurchase($debit);
    }

    /**
     * Test database-level UNIQUE constraint on report_purchases.related_purchase_id:
     * Directly attempting to insert a second refund row violates UNIQUE constraint.
     */
    public function test_database_enforces_unique_related_purchase_id(): void
    {
        $user = User::factory()->create(['wallet_balance' => 200.00]);
        $debit = ReportPurchase::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'idempotency_key' => 'debit_raw_' . Str::random(10),
            'transaction_type' => 'debit',
            'amount' => 149.00,
            'currency' => 'INR',
            'related_purchase_id' => null,
            'status' => 'completed',
        ]);

        // First refund row
        ReportPurchase::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'idempotency_key' => 'refund_raw_1_' . Str::random(10),
            'transaction_type' => 'compensating_refund',
            'amount' => 149.00,
            'currency' => 'INR',
            'related_purchase_id' => $debit->id,
            'status' => 'completed',
        ]);

        // Second refund row with identical related_purchase_id must trigger DB unique exception
        $this->expectException(QueryException::class);

        ReportPurchase::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'idempotency_key' => 'refund_raw_2_' . Str::random(10),
            'transaction_type' => 'compensating_refund',
            'amount' => 149.00,
            'currency' => 'INR',
            'related_purchase_id' => $debit->id, // Violates UNIQUE key
            'status' => 'completed',
        ]);
    }

    /**
     * Test database-level UNIQUE constraint on kundli_reports.report_purchase_id:
     * A purchase cannot be associated with more than one report.
     */
    public function test_database_enforces_unique_report_purchase_id(): void
    {
        $user = User::factory()->create(['wallet_balance' => 200.00]);
        $debit = ReportPurchase::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'idempotency_key' => 'debit_report_' . Str::random(10),
            'transaction_type' => 'debit',
            'amount' => 149.00,
            'currency' => 'INR',
            'related_purchase_id' => null,
            'status' => 'completed',
        ]);

        KundliReport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'report_purchase_id' => $debit->id,
            'status' => 'queued',
        ]);

        // Second report for same purchase must fail DB constraint
        $this->expectException(QueryException::class);

        KundliReport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'report_purchase_id' => $debit->id, // Violates UNIQUE key
            'status' => 'queued',
        ]);
    }

    /**
     * Test transaction rollback when a subsequent step throws an exception:
     * Balance deduction and purchase record must roll back cleanly.
     */
    public function test_transaction_rollback_on_failure(): void
    {
        $user = User::factory()->create(['wallet_balance' => 200.00]);

        try {
            DB::transaction(function () use ($user) {
                $user->wallet_balance -= 149.00;
                $user->save();

                ReportPurchase::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'idempotency_key' => 'rollback_test_key',
                    'transaction_type' => 'debit',
                    'amount' => 149.00,
                    'currency' => 'INR',
                    'status' => 'completed',
                ]);

                // Simulate unexpected failure before commit
                throw new \RuntimeException('Simulated downstream failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Wallet balance must be restored by rollback
        $this->assertEquals(200.00, (float) $user->fresh()->wallet_balance);
        $this->assertDatabaseMissing('report_purchases', ['idempotency_key' => 'rollback_test_key']);
    }

    /**
     * Test ON DELETE SET NULL constraint:
     * When user account is deleted, financial records and report metadata preserve user_id = null.
     */
    public function test_user_deletion_sets_null_on_purchases_and_reports(): void
    {
        $user = User::factory()->create(['wallet_balance' => 300.00]);

        $res = $this->purchaseService->purchaseReport(
            user: $user,
            birthPayload: ['name' => 'To Be Deleted'],
            idempotencyKey: 'delete_user_test_' . Str::random(10)
        );

        $purchaseId = $res['purchase']->id;
        $reportId = $res['report']->id;

        // Delete user
        $user->delete();

        // Financial purchase audit record remains intact with user_id = NULL
        $purchase = ReportPurchase::find($purchaseId);
        $this->assertNotNull($purchase);
        $this->assertNull($purchase->user_id);

        // Report row remains intact with user_id = NULL
        $report = KundliReport::find($reportId);
        $this->assertNotNull($report);
        $this->assertNull($report->user_id);
    }
}
