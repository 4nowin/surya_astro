<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CustomerAstrologerConsent;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\JajmanKundliReport;
use App\Models\ProfessionalDocumentSetting;
use App\Models\ProfessionalPublicProfile;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use App\Services\Astrology\DailyPanchangService;
use App\Services\Astrology\Interpretation\PersonalizedDailyInterpretationService;
use App\Services\Astrology\Pdf\KundliPdfService;
use App\Services\Astrology\Pdf\PremiumKundliPdfService;
use App\Services\Astrology\PersonalizedAstrologyService;
use App\Services\Astrology\Reports\DetailedKundliReportService;
use App\Services\Jajman\JajmanAutoLinkService;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class JajmanKundliReportApiController extends Controller
{
    public function __construct(
        protected KundliCalculationHandler $kundliHandler,
        protected CalculationJobManager $jobManager,
        protected CalculationWorkerLauncher $workerLauncher,
        protected KundliPdfService $pdfService,
        protected JajmanAutoLinkService $autoLinkService,
        protected DailyPanchangService $panchangService,
        protected PersonalizedAstrologyService $personalizedAstrologyService,
        protected PersonalizedDailyInterpretationService $dailyInterpretationService,
        protected DetailedKundliReportService $detailedReportService,
        protected PremiumKundliPdfService $premiumPdfService
    ) {}

    /**
     * Build practitioner branding snapshot from public profile and document settings.
     *
     * @param Admin $admin
     * @param array<string, mixed>|null $customOverrides
     * @return array<string, mixed>
     */
    public function buildBrandingSnapshot(Admin $admin, ?array $customOverrides = null): array
    {
        /** @var ProfessionalDocumentSetting|null $docSetting */
        $docSetting = $admin->documentSetting ?? ProfessionalDocumentSetting::where('admin_id', $admin->id)->first();
        /** @var ProfessionalPublicProfile|null $pubProfile */
        $pubProfile = $admin->publicProfile ?? ProfessionalPublicProfile::where('admin_id', $admin->id)->first();

        $practitionerName = $customOverrides['practitioner_name']
            ?? ($customOverrides['display_name']
            ?? ($customOverrides['business_name']
            ?? ($docSetting?->business_name
            ?: ($pubProfile?->display_name ?: $admin->name))));

        $headline = $customOverrides['headline']
            ?? ($customOverrides['designation']
            ?? ($docSetting?->designation
            ?: ($pubProfile?->headline ?: 'Vedic Astrologer')));

        $phone = $customOverrides['phone']
            ?? ($docSetting?->phone ?: ($pubProfile?->public_phone ?: ($admin->phone ?? null)));
        $email = $customOverrides['email']
            ?? ($docSetting?->email ?: ($pubProfile?->public_email ?: $admin->email));
        $website = $customOverrides['website']
            ?? ($docSetting?->website ?: ($pubProfile?->website ?? null));
        $city = $customOverrides['city']
            ?? ($pubProfile?->city ?? null);
        $logoUrl = $customOverrides['logo_url']
            ?? ($docSetting?->logo_url ?: ($pubProfile?->profile_photo_url ?? null));

        return [
            'practitioner_id' => $admin->id,
            'practitioner_name' => $practitionerName,
            'headline' => $headline,
            'phone' => $phone,
            'email' => $email,
            'website' => $website,
            'city' => $city,
            'logo_url' => $logoUrl,
            'platform_brand' => 'Astro Setu',
            'platform_footer' => 'Powered by Astro Setu — Authentic Vedic Astrology Platform',
        ];
    }

    /**
     * Get the authenticated practitioner's report branding profile.
     */
    public function getBranding(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildBrandingSnapshot($admin),
        ]);
    }

    /**
     * Update the authenticated practitioner's report branding profile.
     * Updates both ProfessionalPublicProfile and ProfessionalDocumentSetting.
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'display_name' => 'nullable|string|max:191',
            'business_name' => 'nullable|string|max:191',
            'headline' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:191',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:191',
            'city' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:191',
            'logo_url' => 'nullable|string|max:500',
            'logo' => 'nullable|file|mimes:jpeg,png,jpg,webp,svg|max:5120',
        ]);

        $logoUrl = null;
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('practitioner_logos', 'public');
            $logoUrl = Storage::disk('public')->url($path);
        } elseif (!empty($validated['logo_url'])) {
            $logoUrl = $validated['logo_url'];
        }

        // 1. Update or create ProfessionalPublicProfile
        $pubProfile = ProfessionalPublicProfile::firstOrCreate(['admin_id' => $admin->id]);
        $pubUpdates = [];
        if (!empty($validated['display_name']) || !empty($validated['business_name'])) {
            $pubUpdates['display_name'] = $validated['display_name'] ?? $validated['business_name'];
        }
        if (!empty($validated['headline']) || !empty($validated['designation'])) {
            $pubUpdates['headline'] = $validated['headline'] ?? $validated['designation'];
        }
        if (isset($validated['phone'])) {
            $pubUpdates['public_phone'] = $validated['phone'];
        }
        if (isset($validated['email'])) {
            $pubUpdates['public_email'] = $validated['email'];
        }
        if (isset($validated['city'])) {
            $pubUpdates['city'] = $validated['city'];
        }
        if (isset($validated['website'])) {
            $pubUpdates['website'] = $validated['website'];
        }
        if ($logoUrl !== null) {
            $pubUpdates['profile_photo_url'] = $logoUrl;
        }
        if (!empty($pubUpdates)) {
            $pubProfile->update($pubUpdates);
        }

        // 2. Update or create ProfessionalDocumentSetting
        $docSetting = ProfessionalDocumentSetting::getOrCreateForAdmin($admin);
        $docUpdates = [];
        if (!empty($validated['business_name']) || !empty($validated['display_name'])) {
            $docUpdates['business_name'] = $validated['business_name'] ?? $validated['display_name'];
        }
        if (!empty($validated['designation']) || !empty($validated['headline'])) {
            $docUpdates['designation'] = $validated['designation'] ?? $validated['headline'];
        }
        if (isset($validated['phone'])) {
            $docUpdates['phone'] = $validated['phone'];
        }
        if (isset($validated['email'])) {
            $docUpdates['email'] = $validated['email'];
        }
        if (isset($validated['website'])) {
            $docUpdates['website'] = $validated['website'];
        }
        if ($logoUrl !== null) {
            $docUpdates['logo_url'] = $logoUrl;
        }
        if (!empty($docUpdates)) {
            $docSetting->update($docUpdates);
        }

        // Unset cached relations
        $admin->unsetRelation('documentSetting');
        $admin->unsetRelation('publicProfile');

        return response()->json([
            'success' => true,
            'message' => 'Practitioner report branding updated successfully.',
            'data' => $this->buildBrandingSnapshot($admin),
        ]);
    }

    /**
     * Check entitlement for generating detailed premium reports.
     * Provides an extensible, clean hook for monetization / subscription / wallet checks.
     */
    protected function checkDetailedReportEntitlement(Admin $admin): ?JsonResponse
    {
        // Preview / active entitlement hook.
        // Decoupled from live payment gateway charging before pricing approval.
        return null;
    }

    /**
     * Check if practitioner has exceeded their daily free report generation limit.
     */
    protected function checkFreeDailyLimit(int $adminId): ?JsonResponse
    {
        $limit = (int) config('astrology.free_daily_report_limit', 20);
        $startOfDay = now('Asia/Kolkata')->startOfDay()->setTimezone('UTC');
        $count = JajmanKundliReport::where('admin_id', $adminId)
            ->where('tier', 'free')
            ->where('created_at', '>=', $startOfDay)
            ->count();

        if ($count >= $limit) {
            return response()->json([
                'success' => false,
                'status' => 'rate_limit_exceeded',
                'message' => "Daily free report generation limit reached ({$limit}/day). Please upgrade or try again tomorrow.",
                'data' => [
                    'limit' => $limit,
                    'used' => $count,
                    'resets_at' => now('Asia/Kolkata')->endOfDay()->toIso8601String(),
                ],
            ], 429);
        }

        return null;
    }

    /**
     * Generate & store a basic or detailed Kundli PDF report for a Jajman.
     * Preserves existing reports without overwriting them.
     * Enforces Task B2 consent if reading client's saved birth profile.
     * Allows explicit manual birth parameters entered by practitioner.
     */
    public function generate(Request $request, int|string $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // Authorize practitioner ownership of the Jajman
        $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
            $q->where('admin_id', $admin->id);
        })->find($id);

        if (!$jajman) {
            return response()->json([
                'success' => false,
                'message' => 'Jajman not found or you are not authorized to manage this record.',
            ], 404);
        }

        $validated = $request->validate([
            'language' => 'nullable|string|in:en,hi',
            'report_type' => 'nullable|string|in:basic,detailed',
            'tier' => 'nullable|string|in:free,detailed',
            'title' => 'nullable|string|max:191',
            'branding' => 'nullable|array',
            // Explicit manual birth detail overrides
            'name' => 'nullable|string|max:191',
            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'birth_time' => 'nullable|string',
            'birth_time_precision' => 'nullable|string|in:verified,approximate,unknown',
            'approximate_time_window' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'timezone' => 'nullable|string|max:100',
            'birth_city' => 'nullable|string|max:191',
        ]);

        $language = $validated['language'] ?? 'en';
        $reportType = $validated['report_type'] ?? 'basic';
        $tier = $validated['tier'] ?? ($reportType === 'detailed' ? 'detailed' : 'free');
        $isDetailed = ($tier === 'detailed' || $reportType === 'detailed');

        if ($isDetailed) {
            if ($entitlementResponse = $this->checkDetailedReportEntitlement($admin)) {
                return $entitlementResponse;
            }
        } else {
            if ($rateLimitResponse = $this->checkFreeDailyLimit($admin->id)) {
                return $rateLimitResponse;
            }
        }

        // Check whether manual birth details were explicitly supplied
        $hasManualBirthParams = !empty($validated['date_of_birth'])
            && isset($validated['latitude'])
            && isset($validated['longitude']);

        if ($hasManualBirthParams) {
            // Practitioner is explicitly providing their own astrological coordinates/data
            $name = $validated['name'] ?? $jajman->name;
            $dob = $validated['date_of_birth'];
            $birthTime = $validated['birth_time'] ?? null;
            $precision = $validated['birth_time_precision'] ?? (!empty($birthTime) ? 'verified' : 'unknown');
            $lat = (float) $validated['latitude'];
            $lng = (float) $validated['longitude'];
            $tz = $validated['timezone'] ?? 'Asia/Kolkata';
            $city = $validated['birth_city'] ?? ($jajman->city ?: 'Unknown');
            $isManualOverride = true;
        } else {
            // Practitioner is reading Jajman's existing saved birth details from the CRM
            // Enforce Task B2 consent if linked to an Astro Setu consumer
            if ($jajman->user_id !== null) {
                $hasConsent = CustomerAstrologerConsent::where('user_id', $jajman->user_id)
                    ->where('admin_id', $admin->id)
                    ->where('status', CustomerAstrologerConsent::STATUS_GRANTED)
                    ->exists();

                if (!$hasConsent) {
                    return response()->json([
                        'success' => false,
                        'status' => 'consent_required',
                        'message' => 'Client has not granted personal data sharing consent for astrological chart calculation.',
                        'data' => null,
                    ], 403);
                }
            }

            $birthDetail = $jajman->primaryBirthDetail
                ?? $jajman->birthDetails()->latest()->first();

            if (!$birthDetail) {
                return response()->json([
                    'success' => false,
                    'status' => 'no_birth_details',
                    'message' => 'No birth details recorded for this Jajman. Please record birth date and location first or provide manual birth parameters.',
                    'data' => null,
                ], 404);
            }

            $name = $jajman->name;
            $dob = $birthDetail->date_of_birth instanceof DateTimeInterface
                ? $birthDetail->date_of_birth->format('Y-m-d')
                : (string) $birthDetail->date_of_birth;
            $birthTime = $birthDetail->birth_time;
            $precision = $birthDetail->time_unknown ? 'unknown' : 'verified';
            $lat = (float) ($birthDetail->latitude ?? 28.6139);
            $lng = (float) ($birthDetail->longitude ?? 77.2090);
            $tz = $birthDetail->timezone ?: 'Asia/Kolkata';
            $city = $birthDetail->birth_place ?: ($jajman->city ?: 'Unknown');
            $isManualOverride = false;
        }

        $branding = $this->buildBrandingSnapshot($admin, $validated['branding'] ?? null);

        // Branch 1: Detailed Premium Kundli Report
        if ($isDetailed) {
            $detailedPayload = [
                'name' => $name,
                'date_of_birth' => $dob,
                'birth_time' => $birthTime,
                'birth_time_precision' => $precision,
                'approximate_time_window' => $validated['approximate_time_window'] ?? null,
                'latitude' => $lat,
                'longitude' => $lng,
                'timezone' => $tz,
                'birth_city' => $city,
                'birth_country' => 'India',
            ];

            try {
                $reportData = $this->detailedReportService->buildReportData($detailedPayload, $language);
                $reportData['branding'] = $branding;
                $pdfBinary = $this->premiumPdfService->generatePdf($reportData, $language, $branding);
            } catch (Throwable $e) {
                $isRendererUnavailable = str_contains($e->getMessage(), 'Hindi Kundli PDF generation requires headless Chromium')
                    || str_contains($e->getMessage(), 'Chromium binary was not found');
                if ($isRendererUnavailable && $language === 'hi') {
                    return response()->json([
                        'success' => false,
                        'status' => 'renderer_unavailable',
                        'error_code' => 'KUNDLI_PDF_TEMPORARILY_UNAVAILABLE',
                        'message' => 'हिंदी जन्म कुंडली PDF जनरेशन के लिए हेडलेस क्रोमियम आवश्यक है।',
                    ], 503);
                }

                Log::error("Jajman Detailed Kundli PDF generation failed: {$e->getMessage()}", [
                    'jajman_id' => $jajman->id,
                    'admin_id' => $admin->id,
                    'language' => $language,
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate detailed Kundli PDF report: ' . $e->getMessage(),
                ], 500);
            }

            $dir = "kundli_reports/{$admin->id}/{$jajman->id}";
            $fileName = 'kundli_detailed_' . $jajman->id . '_' . time() . '_' . Str::random(8) . '_' . $language . '.pdf';
            $filePath = "{$dir}/{$fileName}";
            Storage::disk('local')->put($filePath, $pdfBinary);
            $fileSizeBytes = strlen($pdfBinary);

            $title = $validated['title'] ?? ($language === 'hi'
                ? ("विस्तृत जन्म पत्रिका - " . $name)
                : ("Detailed Janam Kundli - " . $name)
            );

            $report = JajmanKundliReport::create([
                'admin_id' => $admin->id,
                'jajman_id' => $jajman->id,
                'user_id' => $jajman->user_id,
                'report_type' => 'detailed',
                'report_category' => JajmanKundliReport::CATEGORY_KUNDLI,
                'tier' => JajmanKundliReport::TIER_DETAILED,
                'language' => $language,
                'title' => $title,
                'person_name' => $name,
                'file_disk' => 'local',
                'file_path' => $filePath,
                'file_size_bytes' => $fileSizeBytes,
                'birth_snapshot' => [
                    'name' => $name,
                    'date_of_birth' => $dob,
                    'birth_time' => $birthTime,
                    'birth_time_precision' => $precision,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'timezone' => $tz,
                    'birth_city' => $city,
                    'is_manual_override' => $isManualOverride,
                ],
                'calculation_settings' => [
                    'ayanamsa' => 'lahiri',
                    'report_version' => DetailedKundliReportService::REPORT_VERSION,
                ],
                'branding_snapshot' => $branding,
                'meta_payload' => [
                    'tier' => 'detailed',
                    'report_version' => DetailedKundliReportService::REPORT_VERSION,
                ],
                'generated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Detailed Kundli PDF generated and stored successfully.',
                'data' => $report,
            ], 201);
        }

        // Branch 2: Basic Kundli Report
        $calculationPayload = [
            'jajman_id' => $jajman->id,
            'user_id' => $admin->id,
            'name' => $name,
            'date_of_birth' => $dob,
            'birth_time' => $birthTime,
            'birth_time_precision' => $precision,
            'latitude' => $lat,
            'longitude' => $lng,
            'timezone' => $tz,
            'birth_city' => $city,
        ];

        $normalized = KundliCalculationHandler::normalizePayload($calculationPayload);
        $canonicalKey = $this->kundliHandler->computeCanonicalKey($normalized);

        // Check cached calculation first
        $chartData = $this->kundliHandler->getCachedResult($canonicalKey, $normalized);

        if ($chartData === null) {
            $lookup = $this->jobManager->findOrCreateJob(
                type: KundliCalculationHandler::TYPE,
                payload: $normalized,
                userId: null,
                forceRefresh: false
            );

            if ($lookup['ready'] && !empty($lookup['result'])) {
                $chartData = $lookup['result'];
            } elseif (app()->environment('testing') || PHP_SAPI === 'cli') {
                $chartData = $this->kundliHandler->calculate($normalized);
            } else {
                $this->workerLauncher->launchWorkerForJob($lookup['job']);
                return response()->json([
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Kundli calculation in progress. Please retry once calculation completes.',
                    'data' => [
                        'status' => 'pending',
                        'job_id' => $lookup['job']?->job_id,
                        'status_url' => url("/api/v1/calculations/{$lookup['job']?->job_id}"),
                    ],
                ], 202);
            }
        }

        if (!is_array($chartData) || empty($chartData['planets']) || empty($chartData['houses'])) {
            return response()->json([
                'success' => false,
                'message' => 'Kundli calculation data is incomplete or unprocessable for PDF generation.',
            ], 422);
        }

        // Render PDF binary via KundliPdfService
        try {
            $pdfBinary = $this->pdfService->generatePdf($chartData, $language);
        } catch (Throwable $e) {
            $isRendererUnavailable = str_contains($e->getMessage(), 'Hindi Kundli PDF generation requires headless Chromium');
            if ($isRendererUnavailable) {
                return response()->json([
                    'success' => false,
                    'status' => 'renderer_unavailable',
                    'error_code' => 'KUNDLI_PDF_TEMPORARILY_UNAVAILABLE',
                    'message' => $language === 'hi'
                        ? 'हिंदी जन्म कुंडली PDF जनरेशन के लिए हेडलेस क्रोमियम आवश्यक है।'
                        : 'PDF generation temporarily unavailable.',
                ], 503);
            }

            Log::error("Jajman Kundli PDF generation failed: {$e->getMessage()}", [
                'jajman_id' => $jajman->id,
                'admin_id' => $admin->id,
                'language' => $language,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Kundli PDF report: ' . $e->getMessage(),
            ], 500);
        }

        // Store PDF in Storage disk 'local'
        $dir = "kundli_reports/{$admin->id}/{$jajman->id}";
        $fileName = 'kundli_' . $jajman->id . '_' . time() . '_' . Str::random(8) . '_' . $language . '.pdf';
        $filePath = "{$dir}/{$fileName}";
        Storage::disk('local')->put($filePath, $pdfBinary);
        $fileSizeBytes = strlen($pdfBinary);

        $title = $validated['title'] ?? ("Janam Kundli - " . $name);

        $report = JajmanKundliReport::create([
            'admin_id' => $admin->id,
            'jajman_id' => $jajman->id,
            'user_id' => $jajman->user_id,
            'report_type' => $reportType,
            'report_category' => JajmanKundliReport::CATEGORY_KUNDLI,
            'tier' => JajmanKundliReport::TIER_FREE,
            'language' => $language,
            'title' => $title,
            'person_name' => $name,
            'file_disk' => 'local',
            'file_path' => $filePath,
            'file_size_bytes' => $fileSizeBytes,
            'birth_snapshot' => [
                'name' => $name,
                'date_of_birth' => $dob,
                'birth_time' => $birthTime,
                'birth_time_precision' => $precision,
                'latitude' => $lat,
                'longitude' => $lng,
                'timezone' => $tz,
                'birth_city' => $city,
                'is_manual_override' => $isManualOverride,
            ],
            'calculation_settings' => [
                'canonical_key' => $canonicalKey,
                'ayanamsa' => 'lahiri',
            ],
            'branding_snapshot' => $this->buildBrandingSnapshot($admin),
            'meta_payload' => [
                'canonical_key' => $canonicalKey,
            ],
            'generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kundli PDF generated and stored successfully.',
            'data' => $report,
        ], 201);
    }

    /**
     * Generate & store a basic Kundli PDF report for a new person,
     * atomically resolving or creating the Jajman record in practitioner's CRM
     * using Task B3 deduplication and ambiguity rules.
     */
    public function generateForNewPerson(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'phone' => 'nullable|string|max:25',
            'email' => 'nullable|email|max:191',
            'gender' => 'nullable|string|in:male,female,other',
            'date_of_birth' => 'required|date_format:Y-m-d',
            'birth_time' => 'nullable|string',
            'birth_time_precision' => 'nullable|string|in:verified,approximate,unknown',
            'approximate_time_window' => 'nullable|string',
            'time_unknown' => 'nullable|boolean',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'timezone' => 'nullable|string|max:100',
            'birth_city' => 'nullable|string|max:191',
            'language' => 'nullable|string|in:en,hi',
            'report_type' => 'nullable|string|in:basic,detailed',
            'tier' => 'nullable|string|in:free,detailed',
            'title' => 'nullable|string|max:191',
            'branding' => 'nullable|array',
        ]);

        $language = $validated['language'] ?? 'en';
        $reportType = $validated['report_type'] ?? 'basic';
        $tier = $validated['tier'] ?? ($reportType === 'detailed' ? 'detailed' : 'free');
        $isDetailed = ($tier === 'detailed' || $reportType === 'detailed');

        if ($isDetailed) {
            if ($entitlementResponse = $this->checkDetailedReportEntitlement($admin)) {
                return $entitlementResponse;
            }
        } else {
            if ($rateLimitResponse = $this->checkFreeDailyLimit($admin->id)) {
                return $rateLimitResponse;
            }
        }

        // 1. Resolve or create Jajman in practitioner's CRM (Task B3 rules)
        $jajman = $this->autoLinkService->ensureJajmanForPerson($admin->id, $validated);

        // 2. Perform calculation & render PDF
        $dob = $validated['date_of_birth'];
        $birthTime = $validated['birth_time'] ?? null;
        $precision = $validated['birth_time_precision'] ?? (!empty($birthTime) ? 'verified' : 'unknown');
        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];
        $tz = $validated['timezone'] ?? 'Asia/Kolkata';
        $city = $validated['birth_city'] ?? ($jajman->city ?: 'Unknown');
        $name = $validated['name'];

        $branding = $this->buildBrandingSnapshot($admin, $validated['branding'] ?? null);

        if ($isDetailed) {
            $detailedPayload = [
                'name' => $name,
                'date_of_birth' => $dob,
                'birth_time' => $birthTime,
                'birth_time_precision' => $precision,
                'approximate_time_window' => $validated['approximate_time_window'] ?? null,
                'latitude' => $lat,
                'longitude' => $lng,
                'timezone' => $tz,
                'birth_city' => $city,
                'birth_country' => 'India',
            ];

            try {
                $reportData = $this->detailedReportService->buildReportData($detailedPayload, $language);
                $reportData['branding'] = $branding;
                $pdfBinary = $this->premiumPdfService->generatePdf($reportData, $language, $branding);
            } catch (Throwable $e) {
                $isRendererUnavailable = str_contains($e->getMessage(), 'Hindi Kundli PDF generation requires headless Chromium')
                    || str_contains($e->getMessage(), 'Chromium binary was not found');
                if ($isRendererUnavailable && $language === 'hi') {
                    return response()->json([
                        'success' => false,
                        'status' => 'renderer_unavailable',
                        'error_code' => 'KUNDLI_PDF_TEMPORARILY_UNAVAILABLE',
                        'message' => 'हिंदी जन्म कुंडली PDF जनरेशन के लिए हेडलेस क्रोमियम आवश्यक है।',
                    ], 503);
                }

                Log::error("New person Detailed Kundli PDF generation failed: {$e->getMessage()}", [
                    'jajman_id' => $jajman->id,
                    'admin_id' => $admin->id,
                    'language' => $language,
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate detailed Kundli PDF report: ' . $e->getMessage(),
                ], 500);
            }

            $dir = "kundli_reports/{$admin->id}/{$jajman->id}";
            $fileName = 'kundli_detailed_' . $jajman->id . '_' . time() . '_' . Str::random(8) . '_' . $language . '.pdf';
            $filePath = "{$dir}/{$fileName}";
            Storage::disk('local')->put($filePath, $pdfBinary);
            $fileSizeBytes = strlen($pdfBinary);

            $title = $validated['title'] ?? ($language === 'hi'
                ? ("विस्तृत जन्म पत्रिका - " . $name)
                : ("Detailed Janam Kundli - " . $name)
            );

            $report = JajmanKundliReport::create([
                'admin_id' => $admin->id,
                'jajman_id' => $jajman->id,
                'user_id' => $jajman->user_id,
                'report_type' => 'detailed',
                'report_category' => JajmanKundliReport::CATEGORY_KUNDLI,
                'tier' => JajmanKundliReport::TIER_DETAILED,
                'language' => $language,
                'title' => $title,
                'person_name' => $name,
                'file_disk' => 'local',
                'file_path' => $filePath,
                'file_size_bytes' => $fileSizeBytes,
                'birth_snapshot' => [
                    'name' => $name,
                    'date_of_birth' => $dob,
                    'birth_time' => $birthTime,
                    'birth_time_precision' => $precision,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'timezone' => $tz,
                    'birth_city' => $city,
                    'is_manual_override' => true,
                ],
                'calculation_settings' => [
                    'ayanamsa' => 'lahiri',
                    'report_version' => DetailedKundliReportService::REPORT_VERSION,
                ],
                'branding_snapshot' => $branding,
                'meta_payload' => [
                    'tier' => 'detailed',
                    'report_version' => DetailedKundliReportService::REPORT_VERSION,
                ],
                'generated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Detailed Kundli PDF generated and Jajman linked to CRM successfully.',
                'data' => [
                    'report' => $report,
                    'jajman' => [
                        'id' => $jajman->id,
                        'name' => $jajman->name,
                        'phone' => $jajman->phone,
                        'email' => $jajman->email,
                        'gender' => $jajman->gender,
                        'date_of_birth' => $jajman->date_of_birth instanceof DateTimeInterface
                            ? $jajman->date_of_birth->format('Y-m-d')
                            : (string) ($jajman->date_of_birth ?? ''),
                        'city' => $jajman->city,
                        'has_possible_duplicates' => (bool) $jajman->has_possible_duplicates,
                        'possible_duplicate_ids' => $jajman->possible_duplicate_ids ?? [],
                    ],
                ],
            ], 201);
        }

        $calculationPayload = [
            'jajman_id' => $jajman->id,
            'user_id' => $admin->id,
            'name' => $name,
            'date_of_birth' => $dob,
            'birth_time' => $birthTime,
            'birth_time_precision' => $precision,
            'latitude' => $lat,
            'longitude' => $lng,
            'timezone' => $tz,
            'birth_city' => $city,
        ];

        $normalized = KundliCalculationHandler::normalizePayload($calculationPayload);
        $canonicalKey = $this->kundliHandler->computeCanonicalKey($normalized);

        $chartData = $this->kundliHandler->getCachedResult($canonicalKey, $normalized);

        if ($chartData === null) {
            $lookup = $this->jobManager->findOrCreateJob(
                type: KundliCalculationHandler::TYPE,
                payload: $normalized,
                userId: null,
                forceRefresh: false
            );

            if ($lookup['ready'] && !empty($lookup['result'])) {
                $chartData = $lookup['result'];
            } elseif (app()->environment('testing') || PHP_SAPI === 'cli') {
                $chartData = $this->kundliHandler->calculate($normalized);
            } else {
                $this->workerLauncher->launchWorkerForJob($lookup['job']);
                return response()->json([
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Kundli calculation in progress. Please retry once calculation completes.',
                    'data' => [
                        'status' => 'pending',
                        'job_id' => $lookup['job']?->job_id,
                        'status_url' => url("/api/v1/calculations/{$lookup['job']?->job_id}"),
                        'jajman' => [
                            'id' => $jajman->id,
                            'name' => $jajman->name,
                            'phone' => $jajman->phone,
                            'email' => $jajman->email,
                            'has_possible_duplicates' => (bool) $jajman->has_possible_duplicates,
                            'possible_duplicate_ids' => $jajman->possible_duplicate_ids ?? [],
                        ],
                    ],
                ], 202);
            }
        }

        if (!is_array($chartData) || empty($chartData['planets']) || empty($chartData['houses'])) {
            return response()->json([
                'success' => false,
                'message' => 'Kundli calculation data is incomplete or unprocessable for PDF generation.',
            ], 422);
        }

        // Render PDF binary via KundliPdfService
        try {
            $pdfBinary = $this->pdfService->generatePdf($chartData, $language);
        } catch (Throwable $e) {
            $isRendererUnavailable = str_contains($e->getMessage(), 'Hindi Kundli PDF generation requires headless Chromium');
            if ($isRendererUnavailable) {
                return response()->json([
                    'success' => false,
                    'status' => 'renderer_unavailable',
                    'error_code' => 'KUNDLI_PDF_TEMPORARILY_UNAVAILABLE',
                    'message' => $language === 'hi'
                        ? 'हिंदी जन्म कुंडली PDF जनरेशन के लिए हेडलेस क्रोमियम आवश्यक है।'
                        : 'PDF generation temporarily unavailable.',
                ], 503);
            }

            Log::error("New person Kundli PDF generation failed: {$e->getMessage()}", [
                'jajman_id' => $jajman->id,
                'admin_id' => $admin->id,
                'language' => $language,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Kundli PDF report: ' . $e->getMessage(),
            ], 500);
        }

        // Store PDF in Storage disk 'local'
        $dir = "kundli_reports/{$admin->id}/{$jajman->id}";
        $fileName = 'kundli_' . $jajman->id . '_' . time() . '_' . Str::random(8) . '_' . $language . '.pdf';
        $filePath = "{$dir}/{$fileName}";
        Storage::disk('local')->put($filePath, $pdfBinary);
        $fileSizeBytes = strlen($pdfBinary);

        $title = $validated['title'] ?? ("Janam Kundli - " . $name);

        $report = JajmanKundliReport::create([
            'admin_id' => $admin->id,
            'jajman_id' => $jajman->id,
            'user_id' => $jajman->user_id,
            'report_type' => $reportType,
            'report_category' => JajmanKundliReport::CATEGORY_KUNDLI,
            'tier' => JajmanKundliReport::TIER_FREE,
            'language' => $language,
            'title' => $title,
            'person_name' => $name,
            'file_disk' => 'local',
            'file_path' => $filePath,
            'file_size_bytes' => $fileSizeBytes,
            'birth_snapshot' => [
                'name' => $name,
                'date_of_birth' => $dob,
                'birth_time' => $birthTime,
                'birth_time_precision' => $precision,
                'latitude' => $lat,
                'longitude' => $lng,
                'timezone' => $tz,
                'birth_city' => $city,
                'is_manual_override' => true,
            ],
            'calculation_settings' => [
                'canonical_key' => $canonicalKey,
                'ayanamsa' => 'lahiri',
            ],
            'branding_snapshot' => $this->buildBrandingSnapshot($admin),
            'meta_payload' => [
                'canonical_key' => $canonicalKey,
            ],
            'generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Basic Kundli PDF generated and Jajman linked to CRM successfully.',
            'data' => [
                'report' => $report,
                'jajman' => [
                    'id' => $jajman->id,
                    'name' => $jajman->name,
                    'phone' => $jajman->phone,
                    'email' => $jajman->email,
                    'gender' => $jajman->gender,
                    'date_of_birth' => $jajman->date_of_birth instanceof DateTimeInterface
                        ? $jajman->date_of_birth->format('Y-m-d')
                        : (string) ($jajman->date_of_birth ?? ''),
                    'city' => $jajman->city,
                    'has_possible_duplicates' => (bool) $jajman->has_possible_duplicates,
                    'possible_duplicate_ids' => $jajman->possible_duplicate_ids ?? [],
                ],
            ],
        ], 201);
    }

    /**
     * Generate & store a daily Panchang PDF report for a Jajman.
     */
    public function generatePanchang(Request $request, int|string $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
            $q->where('admin_id', $admin->id);
        })->find($id);

        if (!$jajman) {
            return response()->json([
                'success' => false,
                'message' => 'Jajman not found or you are not authorized to manage this record.',
            ], 404);
        }

        if ($rateLimitResponse = $this->checkFreeDailyLimit($admin->id)) {
            return $rateLimitResponse;
        }

        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'timezone' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:191',
            'language' => 'nullable|string|in:en,hi',
            'tier' => 'nullable|string|in:free,detailed',
            'title' => 'nullable|string|max:191',
            'branding' => 'nullable|array',
        ]);

        $language = $validated['language'] ?? 'en';
        $tier = $validated['tier'] ?? JajmanKundliReport::TIER_FREE;
        $targetDate = $validated['date'] ?? now('Asia/Kolkata')->toDateString();

        $birthDetail = $jajman->primaryBirthDetail ?? $jajman->birthDetails()->latest()->first();

        $lat = isset($validated['latitude'])
            ? (float) $validated['latitude']
            : (float) ($birthDetail?->latitude ?? 28.6139);

        $lng = isset($validated['longitude'])
            ? (float) $validated['longitude']
            : (float) ($birthDetail?->longitude ?? 77.2090);

        $tz = $validated['timezone'] ?? ($birthDetail?->timezone ?: 'Asia/Kolkata');
        $city = $validated['city'] ?? ($birthDetail?->birth_place ?: ($jajman->city ?: 'Varanasi'));

        // Compute Panchang
        try {
            $panchangData = $this->panchangService->getDailyPanchang(
                date: $targetDate,
                latitude: $lat,
                longitude: $lng,
                timezone: $tz
            );
        } catch (Throwable $e) {
            Log::error("Daily Panchang calculation failed: {$e->getMessage()}", [
                'jajman_id' => $jajman->id,
                'admin_id' => $admin->id,
                'target_date' => $targetDate,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate Panchang: ' . $e->getMessage(),
            ], 500);
        }

        $branding = $this->buildBrandingSnapshot($admin, $validated['branding'] ?? null);
        $location = [
            'city' => $city,
            'latitude' => $lat,
            'longitude' => $lng,
            'timezone' => $tz,
        ];

        // Render PDF binary
        try {
            $pdfBinary = $this->pdfService->generatePanchangPdf($panchangData, $branding, $location, $language);
        } catch (Throwable $e) {
            $isRendererUnavailable = str_contains($e->getMessage(), 'Hindi Panchang PDF generation requires headless Chromium');
            if ($isRendererUnavailable) {
                return response()->json([
                    'success' => false,
                    'status' => 'renderer_unavailable',
                    'error_code' => 'PANCHANG_PDF_TEMPORARILY_UNAVAILABLE',
                    'message' => $language === 'hi'
                        ? 'हिंदी पंचांग PDF जनरेशन के लिए हेडलेस क्रोमियम आवश्यक है।'
                        : 'PDF generation temporarily unavailable.',
                ], 503);
            }

            Log::error("Jajman Panchang PDF generation failed: {$e->getMessage()}", [
                'jajman_id' => $jajman->id,
                'admin_id' => $admin->id,
                'language' => $language,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Panchang PDF report: ' . $e->getMessage(),
            ], 500);
        }

        $dir = "panchang_reports/{$admin->id}/{$jajman->id}";
        $fileName = 'panchang_' . $jajman->id . '_' . time() . '_' . Str::random(8) . '_' . $language . '.pdf';
        $filePath = "{$dir}/{$fileName}";
        Storage::disk('local')->put($filePath, $pdfBinary);
        $fileSizeBytes = strlen($pdfBinary);

        $title = $validated['title'] ?? ($language === 'hi'
            ? ("दैनिक पंचांग - " . $city . " (" . \Carbon\Carbon::parse($targetDate)->format('d M Y') . ")")
            : ("Daily Panchang - " . $city . " (" . \Carbon\Carbon::parse($targetDate)->format('d M Y') . ")")
        );

        $report = JajmanKundliReport::create([
            'admin_id' => $admin->id,
            'jajman_id' => $jajman->id,
            'user_id' => $jajman->user_id,
            'report_type' => 'basic',
            'report_category' => JajmanKundliReport::CATEGORY_PANCHANG,
            'tier' => $tier,
            'language' => $language,
            'title' => $title,
            'person_name' => $jajman->name,
            'file_disk' => 'local',
            'file_path' => $filePath,
            'file_size_bytes' => $fileSizeBytes,
            'birth_snapshot' => [
                'target_date' => $targetDate,
                'city' => $city,
                'latitude' => $lat,
                'longitude' => $lng,
                'timezone' => $tz,
            ],
            'calculation_settings' => [
                'ayanamsa' => 'lahiri',
            ],
            'branding_snapshot' => $branding,
            'meta_payload' => [
                'target_date' => $targetDate,
                'location' => $location,
                'tithi' => $panchangData['panchanga']['tithi']['name'] ?? null,
                'nakshatra' => $panchangData['panchanga']['nakshatra']['name'] ?? null,
            ],
            'generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daily Panchang PDF generated and stored successfully.',
            'data' => $report,
        ], 201);
    }

    /**
     * Generate & store a daily Personal Insights PDF report for a Jajman.
     * Enforces Task B2 consent if reading client's saved birth profile.
     */
    public function generateDailyInsights(Request $request, int|string $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
            $q->where('admin_id', $admin->id);
        })->find($id);

        if (!$jajman) {
            return response()->json([
                'success' => false,
                'message' => 'Jajman not found or you are not authorized to manage this record.',
            ], 404);
        }

        if ($rateLimitResponse = $this->checkFreeDailyLimit($admin->id)) {
            return $rateLimitResponse;
        }

        $validated = $request->validate([
            'target_date' => 'nullable|date_format:Y-m-d',
            'language' => 'nullable|string|in:en,hi',
            'tier' => 'nullable|string|in:free,detailed',
            'title' => 'nullable|string|max:191',
            'branding' => 'nullable|array',
            // Explicit manual birth overrides
            'name' => 'nullable|string|max:191',
            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'birth_time' => 'nullable|string',
            'birth_time_precision' => 'nullable|string|in:verified,approximate,unknown',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'timezone' => 'nullable|string|max:100',
            'birth_city' => 'nullable|string|max:191',
        ]);

        $language = $validated['language'] ?? 'en';
        $tier = $validated['tier'] ?? JajmanKundliReport::TIER_FREE;
        $targetDate = $validated['target_date'] ?? now('Asia/Kolkata')->toDateString();

        $hasManualBirthParams = !empty($validated['date_of_birth'])
            && isset($validated['latitude'])
            && isset($validated['longitude']);

        if ($hasManualBirthParams) {
            $name = $validated['name'] ?? $jajman->name;
            $dob = $validated['date_of_birth'];
            $birthTime = $validated['birth_time'] ?? null;
            $precision = $validated['birth_time_precision'] ?? (!empty($birthTime) ? 'verified' : 'unknown');
            $lat = (float) $validated['latitude'];
            $lng = (float) $validated['longitude'];
            $tz = $validated['timezone'] ?? 'Asia/Kolkata';
            $city = $validated['birth_city'] ?? ($jajman->city ?: 'Unknown');
            $isManualOverride = true;
        } else {
            // Enforce Task B2 consent if linked to Astro Setu consumer
            if ($jajman->user_id !== null) {
                $hasConsent = CustomerAstrologerConsent::where('user_id', $jajman->user_id)
                    ->where('admin_id', $admin->id)
                    ->where('status', CustomerAstrologerConsent::STATUS_GRANTED)
                    ->exists();

                if (!$hasConsent) {
                    return response()->json([
                        'success' => false,
                        'status' => 'consent_required',
                        'message' => 'Client has not granted personal data sharing consent for astrological chart calculation.',
                        'data' => null,
                    ], 403);
                }
            }

            $birthDetail = $jajman->primaryBirthDetail
                ?? $jajman->birthDetails()->latest()->first();

            if (!$birthDetail) {
                return response()->json([
                    'success' => false,
                    'status' => 'no_birth_details',
                    'message' => 'No birth details recorded for this Jajman. Please record birth date and location first or provide manual birth parameters.',
                    'data' => null,
                ], 404);
            }

            $name = $jajman->name;
            $dob = $birthDetail->date_of_birth instanceof DateTimeInterface
                ? $birthDetail->date_of_birth->format('Y-m-d')
                : (string) $birthDetail->date_of_birth;
            $birthTime = $birthDetail->birth_time;
            $precision = $birthDetail->time_unknown ? 'unknown' : 'verified';
            $lat = (float) ($birthDetail->latitude ?? 28.6139);
            $lng = (float) ($birthDetail->longitude ?? 77.2090);
            $tz = $birthDetail->timezone ?: 'Asia/Kolkata';
            $city = $birthDetail->birth_place ?: ($jajman->city ?: 'Unknown');
            $isManualOverride = false;
        }

        // Compute Daily Insights
        try {
            $calcResult = $this->personalizedAstrologyService->computePersonalizedInsights(
                birthDate: $dob,
                birthTime: $birthTime,
                precision: $precision,
                approximateWindow: null,
                latitude: $lat,
                longitude: $lng,
                timezone: $tz,
                targetDate: $targetDate
            );

            $dummyProfile = new UserAstrologyProfile([
                'id' => $jajman->id,
                'name' => $name,
                'relationship' => 'self',
                'birth_city' => $city,
                'birth_time_precision' => $precision,
            ]);

            $insights = $this->dailyInterpretationService->interpret($calcResult, $dummyProfile, $language);
        } catch (Throwable $e) {
            Log::error("Personalized daily insights computation failed: {$e->getMessage()}", [
                'jajman_id' => $jajman->id,
                'admin_id' => $admin->id,
                'target_date' => $targetDate,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to compute Daily Insights: ' . $e->getMessage(),
            ], 500);
        }

        $branding = $this->buildBrandingSnapshot($admin, $validated['branding'] ?? null);
        $native = [
            'name' => $name,
            'city' => $city,
            'dob' => $dob,
            'moon_sign' => $insights['birth_chart_reference']['moon_sign']['name'] ?? '',
            'nakshatra' => $insights['birth_chart_reference']['nakshatra']['name'] ?? '',
        ];

        // Render PDF binary
        try {
            $pdfBinary = $this->pdfService->generateDailyInsightsPdf($insights, $branding, $native, $language);
        } catch (Throwable $e) {
            $isRendererUnavailable = str_contains($e->getMessage(), 'Hindi Daily Insights PDF generation requires headless Chromium');
            if ($isRendererUnavailable) {
                return response()->json([
                    'success' => false,
                    'status' => 'renderer_unavailable',
                    'error_code' => 'DAILY_INSIGHTS_PDF_TEMPORARILY_UNAVAILABLE',
                    'message' => $language === 'hi'
                        ? 'हिंदी दैनिक भविष्यफल PDF जनरेशन के लिए हेडलेस क्रोमियम आवश्यक है।'
                        : 'PDF generation temporarily unavailable.',
                ], 503);
            }

            Log::error("Jajman Daily Insights PDF generation failed: {$e->getMessage()}", [
                'jajman_id' => $jajman->id,
                'admin_id' => $admin->id,
                'language' => $language,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Daily Insights PDF report: ' . $e->getMessage(),
            ], 500);
        }

        $dir = "daily_insights_reports/{$admin->id}/{$jajman->id}";
        $fileName = 'insights_' . $jajman->id . '_' . time() . '_' . Str::random(8) . '_' . $language . '.pdf';
        $filePath = "{$dir}/{$fileName}";
        Storage::disk('local')->put($filePath, $pdfBinary);
        $fileSizeBytes = strlen($pdfBinary);

        $title = $validated['title'] ?? ($language === 'hi'
            ? ("दैनिक भविष्यफल - " . $name . " (" . \Carbon\Carbon::parse($targetDate)->format('d M Y') . ")")
            : ("Daily Insights - " . $name . " (" . \Carbon\Carbon::parse($targetDate)->format('d M Y') . ")")
        );

        $report = JajmanKundliReport::create([
            'admin_id' => $admin->id,
            'jajman_id' => $jajman->id,
            'user_id' => $jajman->user_id,
            'report_type' => 'basic',
            'report_category' => JajmanKundliReport::CATEGORY_DAILY_INSIGHTS,
            'tier' => $tier,
            'language' => $language,
            'title' => $title,
            'person_name' => $name,
            'file_disk' => 'local',
            'file_path' => $filePath,
            'file_size_bytes' => $fileSizeBytes,
            'birth_snapshot' => [
                'name' => $name,
                'date_of_birth' => $dob,
                'birth_time' => $birthTime,
                'birth_time_precision' => $precision,
                'latitude' => $lat,
                'longitude' => $lng,
                'timezone' => $tz,
                'birth_city' => $city,
                'is_manual_override' => $isManualOverride,
            ],
            'calculation_settings' => [
                'ayanamsa' => 'lahiri',
            ],
            'branding_snapshot' => $branding,
            'meta_payload' => [
                'target_date' => $targetDate,
                'overall_tone' => $insights['summary']['overall_tone'] ?? 'mixed',
                'headline' => $insights['summary']['headline'] ?? '',
                'moon_sign' => $insights['birth_chart_reference']['moon_sign'] ?? null,
            ],
            'generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daily Personal Insights PDF generated and stored successfully.',
            'data' => $report,
        ], 201);
    }

    /**
     * List saved reports for a Jajman with optional category and tier filtering.
     * Accessible by the astrologer who generated them, preserving access even if Jajman is unlinked/soft-deleted.
     */
    public function index(Request $request, int|string $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $query = JajmanKundliReport::where('admin_id', $admin->id)
            ->where('jajman_id', $id);

        if ($request->filled('report_category') && $request->input('report_category') !== 'all') {
            $query->where('report_category', $request->input('report_category'));
        }

        if ($request->filled('tier') && $request->input('tier') !== 'all') {
            $query->where('tier', $request->input('tier'));
        }

        $reports = $query->latest('id')->get();

        return response()->json([
            'success' => true,
            'message' => 'Reports retrieved successfully.',
            'data' => $reports,
        ]);
    }

    /**
     * List all reports across all Jajmans for this practitioner (Unified Report History).
     */
    public function unifiedReports(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $query = JajmanKundliReport::with(['jajman' => function ($q) {
            $q->withTrashed()->select('id', 'name', 'phone', 'email', 'city');
        }])->where('admin_id', $admin->id);

        if ($request->filled('report_category') && $request->input('report_category') !== 'all') {
            $query->where('report_category', $request->input('report_category'));
        }

        if ($request->filled('tier') && $request->input('tier') !== 'all') {
            $query->where('tier', $request->input('tier'));
        }

        if ($request->filled('jajman_id')) {
            $query->where('jajman_id', $request->input('jajman_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('person_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 25);
        $reports = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Unified report history retrieved successfully.',
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
            ],
        ]);
    }

    /**
     * Show metadata for a specific Kundli PDF report.
     */
    public function show(Request $request, int|string $id, int|string $reportId): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $report = JajmanKundliReport::where('admin_id', $admin->id)
            ->where('jajman_id', $id)
            ->find($reportId);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Kundli report not found or unauthorized.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Download or stream a specific stored Kundli PDF.
     */
    public function download(Request $request, int|string $id, int|string $reportId)
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $report = JajmanKundliReport::where('admin_id', $admin->id)
            ->where('jajman_id', $id)
            ->find($reportId);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Kundli report not found or unauthorized.',
            ], 404);
        }

        $disk = Storage::disk($report->file_disk ?: 'local');
        if (!$disk->exists($report->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'The requested Kundli PDF file was not found in storage.',
            ], 404);
        }

        $downloadName = Str::slug($report->title ?: 'kundli_report') . '.pdf';

        return response($disk->get($report->file_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$downloadName}\"",
            'Content-Length' => $report->file_size_bytes ?? $disk->size($report->file_path),
        ]);
    }
}
