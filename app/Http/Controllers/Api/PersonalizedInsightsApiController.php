<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PersonalizedInsightsRequest;
use App\Models\AstrologyCalculationJob;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\PersonalizedInsightsCalculationHandler;
use App\Services\Astrology\Interpretation\PersonalizedDailyInterpretationService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PersonalizedInsightsApiController extends Controller
{
    public function __construct(
        protected CalculationJobManager $jobManager,
        protected CalculationWorkerLauncher $workerLauncher,
        protected PersonalizedDailyInterpretationService $interpretationService
    ) {}

    /**
     * Retrieve or calculate personalized daily astrology insights for today.
     *
     * GET /api/v1/astrology/personalized-insights/today
     */
    public function today(PersonalizedInsightsRequest $request): JsonResponse
    {
        return $this->handlePersonalizedInsights($request);
    }

    /**
     * Explicitly trigger calculation of personalized astrology insights for a specific date or profile.
     *
     * POST /api/v1/astrology/personalized-insights/calculate
     */
    public function calculate(PersonalizedInsightsRequest $request): JsonResponse
    {
        return $this->handlePersonalizedInsights($request);
    }

    /**
     * Internal handler for personalized insights calculation requests.
     */
    protected function handlePersonalizedInsights(PersonalizedInsightsRequest $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        // Resolve requested or default profile
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
        $targetDate = $request->filled('date')
            ? (string) $request->input('date')
            : Carbon::now($timezone)->toDateString();

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
            'target_date' => $targetDate,
            'name' => $profile->name,
            'relationship' => $profile->relationship,
            'birth_city' => $profile->birth_city,
        ];

        try {
            $lookup = $this->jobManager->findOrCreateJob(
                type: PersonalizedInsightsCalculationHandler::TYPE,
                payload: $payload,
                userId: $user->id
            );

            // Case A: Result is already available in cache
            if ($lookup['ready'] && !empty($lookup['result'])) {
                $locale = (string) $request->input('lang', app()->getLocale());
                if (!in_array($locale, ['en', 'hi'], true)) {
                    $locale = 'en';
                }

                $interpretedData = $this->interpretationService->getOrGenerateDailyInsights(
                    calculationResult: $lookup['result'],
                    canonicalKey: (string) $lookup['canonical_key'],
                    profile: $profile,
                    locale: $locale
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Personalized daily insights retrieved successfully.',
                    'data' => $interpretedData,
                ], 200, [
                    'X-Astrology-Cached' => 'HIT',
                    'X-Astrology-Cache-Key' => "astrology:personalized_insights:{$lookup['canonical_key']}",
                ]);
            }

            /** @var AstrologyCalculationJob $job */
            $job = $lookup['job'];

            // Case B: Job previously failed and exhausted retries
            if ($job->isFailed() && !$job->canRetry()) {
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Personalized insights calculation failed: ' . ($job->error_message ?? 'Calculation error'),
                    'data' => [
                        'job_id' => $job->job_id,
                        'status' => 'failed',
                        'attempts' => $job->attempts,
                    ],
                ], 500);
            }

            // Case C: Job is pending or processing. Trigger background worker launch if not already running.
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
                'message' => 'Personalized insights calculation in progress. Poll status URL for result.',
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
            Log::error('Personalized insights request handling failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while queueing personalized insights calculation.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
