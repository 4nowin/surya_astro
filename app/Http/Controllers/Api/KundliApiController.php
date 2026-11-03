<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KundliRequest;
use App\Models\AstrologyCalculationJob;
use App\Models\SavedKundli;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use App\Services\Astrology\DashaService;
use App\Services\Astrology\Pdf\KundliPdfService;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KundliApiController extends Controller
{
    public function __construct(
        protected CalculationJobManager $jobManager,
        protected CalculationWorkerLauncher $workerLauncher,
        protected DashaService $dashaService,
        protected KundliPdfService $pdfService
    ) {}

    /**
     * Retrieve or generate personal Kundli for the authenticated user's profile.
     *
     * GET /api/v1/astrology/kundli
     */
    public function show(KundliRequest $request): JsonResponse
    {
        return $this->handleKundliRequest($request);
    }

    /**
     * Explicitly trigger Kundli generation (Roadmap deliverable: POST /api/v1/kundli/generate).
     *
     * POST /api/v1/kundli/generate
     */
    public function generate(KundliRequest $request): JsonResponse
    {
        return $this->handleKundliRequest($request);
    }

    /**
     * Explicitly trigger Kundli calculation for a specific profile.
     *
     * POST /api/v1/astrology/kundli/calculate
     */
    public function calculate(KundliRequest $request): JsonResponse
    {
        return $this->handleKundliRequest($request);
    }

    /**
     * Internal handler for Kundli calculation requests.
     */
    protected function handleKundliRequest(KundliRequest $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        // Resolve requested or default profile belonging strictly to this user
        /** @var UserAstrologyProfile|null $profile */
        if ($request->filled('profile_id')) {
            $profile = $user->astrologyProfiles()->find($request->input('profile_id'));
            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'The specified astrology profile was not found or does not belong to your account.',
                    'data' => null,
                ], 404);
            }
        } else {
            $profile = $user->astrologyProfiles()->where('is_default', true)->first()
                ?? $user->astrologyProfiles()->first();

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'No astrology profile found for user. Please create a birth profile first.',
                    'data' => null,
                ], 404);
            }
        }

        $timezone = $profile->timezone ?: 'UTC';

        $dob = $profile->date_of_birth instanceof DateTimeInterface
            ? $profile->date_of_birth->format('Y-m-d')
            : (string) $profile->date_of_birth;

        $payload = [
            'profile_id' => $profile->id,
            'user_id' => $user->id,
            'date_of_birth' => $dob,
            'birth_time_precision' => (string) $profile->birth_time_precision,
            'birth_time' => $profile->birth_time,
            'approximate_time_window' => $profile->approximate_time_window,
            'latitude' => (float) $profile->latitude,
            'longitude' => (float) $profile->longitude,
            'timezone' => $timezone,
            'name' => $profile->name,
            'relationship' => $profile->relationship,
            'birth_city' => $profile->birth_city,
        ];

        try {
            $lookup = $this->jobManager->findOrCreateJob(
                type: KundliCalculationHandler::TYPE,
                payload: $payload,
                userId: $user->id
            );

            // Case A: Result is already available in cache
            if ($lookup['ready'] && !empty($lookup['result'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kundli retrieved successfully.',
                    'data' => $lookup['result'],
                ], 200, [
                    'X-Astrology-Cached' => 'HIT',
                    'X-Astrology-Cache-Key' => "astrology:kundli:{$lookup['canonical_key']}",
                ]);
            }

            /** @var AstrologyCalculationJob $job */
            $job = $lookup['job'];

            // Case B: Job previously failed and exhausted retries
            if ($job->isFailed() && !$job->canRetry()) {
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Kundli calculation failed: ' . ($job->error_message ?? 'Calculation error'),
                    'data' => [
                        'job_id' => $job->job_id,
                        'status' => 'failed',
                        'attempts' => $job->attempts,
                    ],
                ], 500);
            }

            // Case C: Job is pending or processing. Trigger background worker launch if needed.
            $launchResult = $this->workerLauncher->launchWorkerIfNeeded();
            if (!empty($launchResult['error'])) {
                Log::warning("Background calculation worker launch notice for job [{$job->job_id}]: {$launchResult['error']}", [
                    'reason' => $launchResult['reason'],
                ]);
            }

            $statusUrl = url("/api/v1/calculations/{$job->job_id}");

            return response()->json([
                'success' => true,
                'status' => $job->status,
                'message' => 'Kundli calculation in progress. Poll status URL for result.',
                'data' => [
                    'job_id' => $job->job_id,
                    'calculation_type' => $job->calculation_type,
                    'status' => $job->status,
                    'status_url' => $statusUrl,
                    'poll_interval_ms' => 2000,
                    'worker_launched' => $launchResult['launched'],
                ],
            ], 202, [
                'X-Astrology-Status' => $job->status,
                'X-Astrology-Job-Id' => $job->job_id,
                'Location' => $statusUrl,
                'Retry-After' => '2',
            ]);
        } catch (\Throwable $e) {
            Log::error("Kundli calculation dispatch failed: {$e->getMessage()}", [
                'profile_id' => $profile->id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to dispatch Kundli calculation. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve calculated Kundli result from cache or calculation job infrastructure.
     *
     * @return array{ready: bool, result: ?array<string, mixed>, job: ?AstrologyCalculationJob, canonical_key: string}
     */
    protected function resolveKundliData(UserAstrologyProfile $profile, int $userId): array
    {
        $timezone = $profile->timezone ?: 'UTC';
        $dob = $profile->date_of_birth instanceof DateTimeInterface
            ? $profile->date_of_birth->format('Y-m-d')
            : (string) $profile->date_of_birth;

        $payload = [
            'profile_id' => $profile->id,
            'user_id' => $userId,
            'date_of_birth' => $dob,
            'birth_time_precision' => (string) $profile->birth_time_precision,
            'birth_time' => $profile->birth_time,
            'approximate_time_window' => $profile->approximate_time_window,
            'latitude' => (float) $profile->latitude,
            'longitude' => (float) $profile->longitude,
            'timezone' => $timezone,
            'name' => $profile->name,
            'relationship' => $profile->relationship,
            'birth_city' => $profile->birth_city,
        ];

        return $this->jobManager->findOrCreateJob(
            type: KundliCalculationHandler::TYPE,
            payload: $payload,
            userId: $userId
        );
    }

    /**
     * Retrieve Vimshottari Dasha timeline for a profile.
     *
     * GET /api/v1/kundli/{profileId}/dashas
     */
    public function dashas(Request $request, string $profileId): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $profile = ($profileId === 'default')
            ? ($user->astrologyProfiles()->where('is_default', true)->first() ?? $user->astrologyProfiles()->first())
            : $user->astrologyProfiles()->find($profileId);

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        $lookup = $this->resolveKundliData($profile, $user->id);

        if ($lookup['ready'] && !empty($lookup['result'])) {
            $result = $lookup['result'];
            if (!empty($result['dasha'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Vimshottari Dasha timeline retrieved successfully.',
                    'data' => $result['dasha'],
                ]);
            }

            // Fallback: Compute dasha from cached natal moon longitude using pure PHP DashaService
            $moonLon = $result['planets']['moon']['longitude'] ?? null;
            $dob = $profile->date_of_birth instanceof DateTimeInterface
                ? $profile->date_of_birth->format('Y-m-d')
                : (string) $profile->date_of_birth;
            $birthDateTimeString = ($profile->birth_time && $profile->birth_time_precision === UserAstrologyProfile::PRECISION_VERIFIED)
                ? "{$dob} {$profile->birth_time}"
                : "{$dob} 12:00:00";
            $dashaData = $this->dashaService->calculateVimshottariTimeline(
                moonLongitude: $moonLon,
                birthDate: $birthDateTimeString,
                precision: (string) $profile->birth_time_precision,
                locale: $request->query('locale', 'en')
            );

            return response()->json([
                'success' => true,
                'message' => 'Vimshottari Dasha timeline calculated successfully.',
                'data' => $dashaData,
            ]);
        }

        // Job is pending or queued: launch worker if needed and return 202
        $this->workerLauncher->launchWorkerIfNeeded();

        return response()->json([
            'success' => true,
            'status' => 'pending',
            'message' => 'Kundli calculation in progress. Please poll calculation status.',
            'data' => [
                'status' => 'pending',
                'job_id' => $lookup['job']?->job_id,
                'status_url' => url("/api/v1/calculations/{$lookup['job']?->job_id}"),
            ],
        ], 202);
    }

    /**
     * List all saved Kundli snapshots for the authenticated user.
     *
     * GET /api/v1/kundli/saved
     */
    public function listSaved(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $savedList = SavedKundli::with('profile')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (SavedKundli $saved) {
                return [
                    'id' => $saved->id,
                    'profile_id' => $saved->profile_id,
                    'title' => $saved->title,
                    'person_name' => $saved->person_name,
                    'relationship' => $saved->relationship,
                    'date_of_birth' => $saved->date_of_birth ? $saved->date_of_birth->format('Y-m-d') : null,
                    'birth_time' => $saved->birth_time,
                    'birth_city' => $saved->birth_city,
                    'is_outdated' => $saved->isOutdated(),
                    'created_at' => $saved->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Saved Kundlis retrieved successfully.',
            'data' => $savedList,
        ]);
    }

    /**
     * Retrieve a single saved Kundli snapshot.
     *
     * GET /api/v1/kundli/saved/{id}
     */
    public function showSaved(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $saved = SavedKundli::where('user_id', $user->id)->find($id);
        if (!$saved) {
            return response()->json(['success' => false, 'message' => 'Saved Kundli not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved Kundli retrieved successfully.',
            'data' => [
                'id' => $saved->id,
                'profile_id' => $saved->profile_id,
                'title' => $saved->title,
                'person_name' => $saved->person_name,
                'relationship' => $saved->relationship,
                'date_of_birth' => $saved->date_of_birth ? $saved->date_of_birth->format('Y-m-d') : null,
                'birth_time' => $saved->birth_time,
                'birth_city' => $saved->birth_city,
                'is_outdated' => $saved->isOutdated(),
                'chart_data' => $saved->chart_data,
                'notes' => $saved->notes,
                'created_at' => $saved->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Save a Kundli snapshot.
     *
     * POST /api/v1/kundli/save
     */
    public function saveSnapshot(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'profile_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($request->filled('profile_id')) {
            $profile = $user->astrologyProfiles()->find($request->input('profile_id'));
        } else {
            $profile = $user->astrologyProfiles()->where('is_default', true)->first()
                ?? $user->astrologyProfiles()->first();
        }

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Astrology profile not found.'], 404);
        }

        // 1. Consume provided chart_data from client if passed
        $chartData = $request->input('chart_data');

        // 2. Otherwise retrieve from cached calculation result
        if (empty($chartData)) {
            $lookup = $this->resolveKundliData($profile, $user->id);
            if ($lookup['ready'] && !empty($lookup['result'])) {
                $chartData = $lookup['result'];
            } else {
                $this->workerLauncher->launchWorkerIfNeeded();
                return response()->json([
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Kundli calculation is still in progress. Please wait for completion before saving snapshot.',
                    'data' => [
                        'status' => 'pending',
                        'job_id' => $lookup['job']?->job_id,
                    ],
                ], 202);
            }
        }

        $title = $request->input('title') ?: "{$profile->name}'s Kundli";

        $saved = SavedKundli::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'title' => $title,
            'person_name' => $profile->name,
            'relationship' => $profile->relationship,
            'canonical_key' => $profile->computeCanonicalKey(),
            'date_of_birth' => $profile->date_of_birth,
            'birth_time' => $profile->birth_time,
            'birth_time_precision' => (string) $profile->birth_time_precision,
            'approximate_time_window' => $profile->approximate_time_window,
            'birth_city' => $profile->birth_city,
            'latitude' => (float) $profile->latitude,
            'longitude' => (float) $profile->longitude,
            'timezone' => $profile->timezone ?: 'UTC',
            'chart_data' => $chartData,
            'notes' => $request->input('notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kundli report saved successfully.',
            'data' => [
                'id' => $saved->id,
                'title' => $saved->title,
                'person_name' => $saved->person_name,
                'created_at' => $saved->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Delete a saved Kundli.
     *
     * DELETE /api/v1/kundli/saved/{id}
     */
    public function deleteSaved(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $saved = SavedKundli::where('user_id', $user->id)->find($id);
        if (!$saved) {
            return response()->json(['success' => false, 'message' => 'Saved Kundli not found.'], 404);
        }

        $saved->delete();

        return response()->json([
            'success' => true,
            'message' => 'Saved Kundli deleted successfully.',
        ]);
    }

    /**
     * Download or stream the Kundli PDF report.
     *
     * GET /api/v1/kundli/pdf
     */
    public function downloadPdf(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $locale = $request->query('locale', 'en');
        if (!in_array($locale, ['en', 'hi'], true)) {
            $locale = 'en';
        }

        $chartData = null;
        if ($request->filled('saved_id')) {
            $saved = SavedKundli::where('user_id', $user->id)->find($request->input('saved_id'));
            if ($saved) {
                $chartData = $saved->chart_data;
            }
        }

        if (!$chartData) {
            if ($request->filled('profile_id')) {
                $profile = $user->astrologyProfiles()->find($request->input('profile_id'));
            } else {
                $profile = $user->astrologyProfiles()->where('is_default', true)->first()
                    ?? $user->astrologyProfiles()->first();
            }

            if (!$profile) {
                return response()->json(['success' => false, 'message' => 'Astrology profile not found.'], 404);
            }

            $lookup = $this->resolveKundliData($profile, $user->id);
            if ($lookup['ready'] && !empty($lookup['result'])) {
                $chartData = $lookup['result'];
            } else {
                $this->workerLauncher->launchWorkerIfNeeded();
                return response()->json([
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Kundli calculation in progress. Please retry PDF export once calculation completes.',
                    'data' => [
                        'status' => 'pending',
                        'job_id' => $lookup['job']?->job_id,
                    ],
                ], 202);
            }
        }

        $pdfBinary = $this->pdfService->generatePdf($chartData, $locale);
        $fileName = 'kundli_' . preg_replace('/[^a-z0-9_-]/i', '_', $chartData['profile']['name'] ?? 'report') . '.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Content-Length' => strlen($pdfBinary),
        ]);
    }
}
