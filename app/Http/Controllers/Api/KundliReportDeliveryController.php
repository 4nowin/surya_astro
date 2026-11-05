<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KundliReport;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\DetailedKundliReportCalculationHandler;
use App\Services\Astrology\Reports\Exceptions\InsufficientWalletBalanceException;
use App\Services\Astrology\Reports\ReportPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KundliReportDeliveryController extends Controller
{
    public const SIGNED_URL_TTL_MINUTES = 20;

    public function __construct(
        protected ReportPurchaseService $purchaseService,
        protected CalculationJobManager $jobManager,
        protected CalculationWorkerLauncher $workerLauncher
    ) {}

    /**
     * Purchase a detailed Kundli report with atomic wallet balance deduction.
     *
     * POST /api/v1/kundli/reports/purchase
     */
    public function purchase(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'profile_id' => 'nullable|integer',
            'idempotency_key' => 'required|string|max:64',
            'language' => 'nullable|string|in:en,hi',
        ]);

        $idempotencyKey = $validated['idempotency_key'];
        $language = $validated['language'] ?? 'en';

        // Resolve birth profile
        if (!empty($validated['profile_id'])) {
            $profile = $user->astrologyProfiles()->find($validated['profile_id']);
            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'The specified astrology profile was not found on your account.',
                ], 404);
            }
        } else {
            $profile = $user->astrologyProfiles()->where('is_default', true)->first()
                ?? $user->astrologyProfiles()->first();

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'No astrology profile found for user. Please create a birth profile first.',
                ], 404);
            }
        }

        $birthPayload = [
            'profile_id' => $profile->id,
            'name' => $profile->name,
            'gender' => $profile->gender,
            'date_of_birth' => $profile->date_of_birth->format('Y-m-d'),
            'birth_time' => $profile->birth_time,
            'birth_time_precision' => (string) $profile->birth_time_precision,
            'approximate_time_window' => $profile->approximate_time_window,
            'latitude' => (float) $profile->latitude,
            'longitude' => (float) $profile->longitude,
            'timezone' => (string) $profile->timezone,
            'language' => $language,
        ];

        try {
            $result = $this->purchaseService->purchaseReport(
                user: $user,
                birthPayload: $birthPayload,
                idempotencyKey: $idempotencyKey,
                language: $language
            );

            $purchase = $result['purchase'];
            $report = $result['report'];

            // Dispatch background calculation job if newly created
            if (!$result['is_duplicate'] && $report) {
                $jobResult = $this->jobManager->findOrCreateJob(
                    type: DetailedKundliReportCalculationHandler::TYPE,
                    payload: [
                        'report_uuid' => $report->uuid,
                        'birth_payload' => $birthPayload,
                        'language' => $language,
                    ],
                    userId: $user->id
                );

                // Detached CLI background execution
                $this->workerLauncher->launchWorkerIfNeeded();
            }

            return response()->json([
                'success' => true,
                'message' => $result['is_duplicate']
                    ? 'Purchase request already processed (idempotent).'
                    : 'Kundli report purchased successfully and queued for background calculation.',
                'data' => [
                    'purchase_uuid' => $purchase->uuid,
                    'report_uuid' => $report?->uuid,
                    'status' => $report?->status ?? 'queued',
                    'amount' => (float) $purchase->amount,
                    'currency' => $purchase->currency,
                    'wallet_balance' => (float) $user->fresh()->wallet_balance,
                    'is_duplicate' => $result['is_duplicate'],
                ],
            ], $result['is_duplicate'] ? 200 : 201);
        } catch (InsufficientWalletBalanceException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'current_balance' => $e->currentBalance,
                    'required_amount' => $e->requiredAmount,
                ],
            ], 402);
        }
    }

    /**
     * Retrieve status and metadata for a purchased report.
     *
     * GET /api/v1/kundli/reports/{reportUuid}/status
     */
    public function status(Request $request, string $reportUuid): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $report = KundliReport::where('uuid', $reportUuid)->first();
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        // Ownership verification
        if ($report->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to report.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'report_uuid' => $report->uuid,
                'status' => $report->status,
                'language' => $report->language,
                'file_hash' => $report->file_hash,
                'file_size_bytes' => $report->file_size_bytes,
                'generation_duration_ms' => $report->generation_duration_ms,
                'download_count' => $report->download_count,
                'created_at' => $report->created_at?->toIso8601String(),
                'updated_at' => $report->updated_at?->toIso8601String(),
                'error_message' => $report->error_message,
            ],
        ]);
    }

    /**
     * Generate an authenticated, temporary signed download URL (TTL 20 mins).
     *
     * GET /api/v1/kundli/reports/{reportUuid}/download-url
     */
    public function downloadUrl(Request $request, string $reportUuid): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $report = KundliReport::where('uuid', $reportUuid)->first();
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        // Ownership verification
        if ($report->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to report.'], 403);
        }

        if (!$report->isCompleted() || empty($report->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Report calculation is not yet completed. Current status: ' . $report->status,
                'data' => ['status' => $report->status],
            ], 409);
        }

        $expiresAt = now()->addMinutes(self::SIGNED_URL_TTL_MINUTES);
        $signedUrl = URL::temporarySignedRoute(
            'kundli.reports.download',
            $expiresAt,
            ['reportUuid' => $report->uuid]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'report_uuid' => $report->uuid,
                'download_url' => $signedUrl,
                'expires_at' => $expiresAt->toIso8601String(),
                'ttl_seconds' => self::SIGNED_URL_TTL_MINUTES * 60,
            ],
        ]);
    }

    /**
     * Download the report PDF via temporary signed URL.
     *
     * GET /api/v1/kundli/reports/download/{reportUuid}
     */
    public function download(Request $request, string $reportUuid): BinaryFileResponse|JsonResponse
    {
        // 1. Validate signature & expiration
        if (!$request->hasValidSignature()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired report download link.',
            ], 403);
        }

        $report = KundliReport::where('uuid', $reportUuid)->first();
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        if (empty($report->file_path)) {
            return response()->json(['success' => false, 'message' => 'Report file path is missing.'], 404);
        }

        $fullPath = storage_path("app/private/{$report->file_path}");
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Report PDF is missing from storage or retention period has expired.',
            ], 404);
        }

        // Increment download count
        $report->increment('download_count');

        $personName = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($report->birth_payload['name'] ?? 'Kundli'));
        $downloadFilename = "AstroSetu_Kundli_{$personName}.pdf";

        return response()->download($fullPath, $downloadFilename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
