<?php

declare(strict_types=1);

namespace App\Services\Astrology\Reports;

use App\Models\KundliReport;
use App\Models\ReportPurchase;
use App\Models\User;
use App\Services\Astrology\Reports\Exceptions\DuplicateRefundException;
use App\Services\Astrology\Reports\Exceptions\InsufficientWalletBalanceException;
use App\Services\Astrology\Reports\Exceptions\InvalidPurchaseException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportPurchaseService
{
    public const DEFAULT_REPORT_PRICE = 149.00;

    /**
     * Atomically purchase a detailed Kundli report from the user's wallet balance.
     *
     * Invariants enforced:
     * - Checked and deducted inside DB::transaction() with lockForUpdate().
     * - Guaranteed idempotency via unique idempotency_key.
     * - Creates authoritative debit ledger entry in report_purchases.
     * - Creates associated delivery row in kundli_reports.
     *
     * @param User $user
     * @param array<string, mixed> $birthPayload
     * @param string $idempotencyKey
     * @param string $language 'en' or 'hi'
     * @param float $amount
     * @return array{purchase: ReportPurchase, report: KundliReport, is_duplicate: bool}
     *
     * @throws InsufficientWalletBalanceException
     */
    public function purchaseReport(
        User $user,
        array $birthPayload,
        string $idempotencyKey,
        string $language = 'en',
        float $amount = self::DEFAULT_REPORT_PRICE
    ): array {
        // 1. Fast check for existing idempotent purchase
        $existing = ReportPurchase::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            $existingReport = KundliReport::where('report_purchase_id', $existing->id)->first();
            return [
                'purchase' => $existing,
                'report' => $existingReport,
                'is_duplicate' => true,
            ];
        }

        try {
            return DB::transaction(function () use ($user, $birthPayload, $idempotencyKey, $language, $amount) {
                // Lock user row to prevent race conditions on wallet balance
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

                $currentBalance = (float) $lockedUser->wallet_balance;
                if ($currentBalance < $amount) {
                    throw new InsufficientWalletBalanceException($currentBalance, $amount);
                }

                // Deduct from wallet balance atomically
                $lockedUser->wallet_balance = round($currentBalance - $amount, 2);
                $lockedUser->save();

                // Record authoritative debit ledger entry
                $purchase = ReportPurchase::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $lockedUser->id,
                    'idempotency_key' => $idempotencyKey,
                    'transaction_type' => ReportPurchase::TYPE_DEBIT,
                    'amount' => $amount,
                    'currency' => 'INR',
                    'related_purchase_id' => null,
                    'status' => ReportPurchase::STATUS_COMPLETED,
                ]);

                // Create initial report delivery record
                $report = KundliReport::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $lockedUser->id,
                    'report_purchase_id' => $purchase->id,
                    'birth_payload' => $birthPayload,
                    'language' => $language,
                    'status' => KundliReport::STATUS_QUEUED,
                ]);

                return [
                    'purchase' => $purchase,
                    'report' => $report,
                    'is_duplicate' => false,
                ];
            });
        } catch (UniqueConstraintViolationException|QueryException $e) {
            // Concurrent race condition caught by database unique index
            $existing = ReportPurchase::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $existingReport = KundliReport::where('report_purchase_id', $existing->id)->first();
                return [
                    'purchase' => $existing,
                    'report' => $existingReport,
                    'is_duplicate' => true,
                ];
            }

            throw $e;
        }
    }

    /**
     * Issue an atomic compensating refund for a failed report generation.
     *
     * Invariants enforced:
     * - Only debits can be refunded.
     * - Database-level UNIQUE constraint on related_purchase_id mathematically prevents
     *   more than one compensating refund per debit record.
     * - Credits wallet_balance under lockForUpdate().
     *
     * @param ReportPurchase $debitPurchase
     * @param string $reason
     * @return ReportPurchase The created compensating refund ledger record
     *
     * @throws InvalidPurchaseException
     * @throws DuplicateRefundException
     */
    public function refundPurchase(ReportPurchase $debitPurchase, string $reason = ''): ReportPurchase
    {
        if (!$debitPurchase->isDebit()) {
            throw new InvalidPurchaseException('Cannot issue a compensating refund for a non-debit record.');
        }

        // Fast check before transaction
        $existingRefund = ReportPurchase::where('related_purchase_id', $debitPurchase->id)->first();
        if ($existingRefund) {
            throw new DuplicateRefundException($debitPurchase->id);
        }

        return DB::transaction(function () use ($debitPurchase, $reason) {
            // Lock debit record to serialize concurrent refund requests
            $lockedDebit = ReportPurchase::where('id', $debitPurchase->id)->lockForUpdate()->firstOrFail();

            $existingRefund = ReportPurchase::where('related_purchase_id', $lockedDebit->id)->first();
            if ($existingRefund) {
                throw new DuplicateRefundException($lockedDebit->id);
            }

            // Lock user and refund wallet balance if user still exists
            if ($lockedDebit->user_id !== null) {
                $lockedUser = User::where('id', $lockedDebit->user_id)->lockForUpdate()->first();
                if ($lockedUser) {
                    $lockedUser->wallet_balance = round((float) $lockedUser->wallet_balance + (float) $lockedDebit->amount, 2);
                    $lockedUser->save();
                }
            }

            // Create compensating refund record (related_purchase_id has UNIQUE DB constraint)
            $refund = ReportPurchase::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $lockedDebit->user_id,
                'idempotency_key' => 'refund_' . $lockedDebit->uuid,
                'transaction_type' => ReportPurchase::TYPE_REFUND,
                'amount' => $lockedDebit->amount,
                'currency' => $lockedDebit->currency,
                'related_purchase_id' => $lockedDebit->id,
                'status' => ReportPurchase::STATUS_COMPLETED,
            ]);

            // Mark associated report as failed if not already completed
            $report = KundliReport::where('report_purchase_id', $lockedDebit->id)->first();
            if ($report && !$report->isCompleted()) {
                $report->update([
                    'status' => KundliReport::STATUS_FAILED,
                    'error_message' => $reason ?: 'Compensating refund issued due to report generation failure.',
                ]);
            }

            return $refund;
        });
    }
}
