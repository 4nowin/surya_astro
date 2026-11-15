<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation\Drivers;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\DailyPanchangService;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\NativeEphemerisResolver;
use App\Services\Astrology\PersonalizedAstrologyService;
use App\Services\Astrology\Stability\AstrologyIntervalStabilityService;
use App\Services\Astrology\Transits\SaturnTransitService;
use App\Services\Astrology\Yogas\ManglikDoshaService;
use App\Services\Astrology\Yogas\YogaDoshaOrchestrator;
use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JmeEph\FFI\JmeEphFFI;
use Throwable;

class LocalCalculationDriver implements CalculationDriverInterface
{
    protected ?JmeEphFFI $jme = null;
    protected ?KundliService $kundliService = null;
    protected ?DailyPanchangService $dailyPanchangService = null;

    public function __construct(
        ?JmeEphFFI $jme = null,
        ?KundliService $kundliService = null,
        ?DailyPanchangService $dailyPanchangService = null
    ) {
        $this->jme = $jme;
        $this->kundliService = $kundliService;
        $this->dailyPanchangService = $dailyPanchangService;
    }

    protected function getJme(): JmeEphFFI
    {
        if ($this->jme !== null) {
            return $this->jme;
        }

        $lib = NativeEphemerisResolver::resolve();
        $this->jme = new JmeEphFFI($lib);
        JmeRuntime::setTrueCitra($this->jme);
        return $this->jme;
    }

    protected function getKundliService(): KundliService
    {
        return $this->kundliService ??= app(KundliService::class);
    }

    protected function getDailyPanchangService(): DailyPanchangService
    {
        return $this->dailyPanchangService ??= app(DailyPanchangService::class);
    }

    protected function getPersonalizedAstrologyService(): PersonalizedAstrologyService
    {
        return app(PersonalizedAstrologyService::class);
    }

    public function calculatePlanetaryPositions(float $jd): array
    {
        $jme = $this->getJme();
        $ffi = $jme->getFFI();
        $buf = $ffi->new('double[6]');
        $err = $ffi->new('char[256]');

        $ayan = $jme->jme_get_ayanamsa_ut($jd);
        $bodies = [
            JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_BODY_MOON, JmeEphFFI::JME_BODY_MERCURY,
            JmeEphFFI::JME_BODY_VENUS, JmeEphFFI::JME_BODY_MARS, JmeEphFFI::JME_BODY_JUPITER,
            JmeEphFFI::JME_BODY_SATURN, JmeEphFFI::JME_BODY_URANUS, JmeEphFFI::JME_BODY_NEPTUNE,
            JmeEphFFI::JME_BODY_PLUTO, JmeEphFFI::JME_BODY_MEAN_NODE
        ];

        $positions = [];
        foreach ($bodies as $b) {
            $jme->jme_calc_ut($jd, $b, JmeEphFFI::JME_CALC_SPEED, $buf, $err);
            $positions[$b] = [
                'longitude' => $buf[0],
                'latitude' => $buf[1],
                'distance_au' => $buf[2],
                'speed_deg_per_day' => $buf[3],
            ];
        }

        return [
            'status' => 'success',
            'julian_day' => $jd,
            'ayanamsha' => $ayan,
            'planets' => $positions,
        ];
    }

    public function calculateDailyPanchang(array $payload): array
    {
        $date = (string) ($payload['date'] ?? now('Asia/Kolkata')->toDateString());
        $lat = (float) ($payload['lat'] ?? $payload['latitude'] ?? 28.6139);
        $lon = (float) ($payload['lon'] ?? $payload['longitude'] ?? 77.2090);
        $tz = (string) ($payload['tz'] ?? $payload['timezone'] ?? 'Asia/Kolkata');
        $elevation = (float) ($payload['elevation'] ?? 0.0);

        return $this->getDailyPanchangService()->getDailyPanchang($date, $lat, $lon, $tz, $elevation);
    }

    public function calculateKundli(array $payload): array
    {
        $dob = (string) ($payload['date_of_birth'] ?? $payload['dob'] ?? now('Asia/Kolkata')->toDateString());
        $tob = isset($payload['birth_time']) ? (string) $payload['birth_time'] : (isset($payload['time']) ? (string) $payload['time'] : '12:00:00');
        $precision = (string) ($payload['birth_time_precision'] ?? $payload['precision'] ?? UserAstrologyProfile::PRECISION_VERIFIED);
        $approxWindow = isset($payload['approximate_time_window']) ? (string) $payload['approximate_time_window'] : null;
        $lat = (float) ($payload['latitude'] ?? $payload['lat'] ?? 28.6139);
        $lon = (float) ($payload['longitude'] ?? $payload['lon'] ?? 77.2090);
        $tz = (string) ($payload['timezone'] ?? $payload['tz'] ?? 'Asia/Kolkata');

        return $this->getKundliService()->computeKundli(
            birthDate: $dob,
            birthTime: $tob,
            precision: $precision,
            approximateWindow: $approxWindow,
            latitude: $lat,
            longitude: $lon,
            timezone: $tz,
            profileMetadata: [
                'name' => $payload['name'] ?? 'Native',
                'birth_city' => $payload['birth_city'] ?? 'Unknown',
            ]
        );
    }

    public function calculateKundliComplete(array $payload): array
    {
        $kundli = $this->calculateKundli($payload);

        $dob = (string) ($payload['date_of_birth'] ?? $payload['dob'] ?? now('Asia/Kolkata')->toDateString());
        $tob = isset($payload['birth_time']) ? (string) $payload['birth_time'] : (isset($payload['time']) ? (string) $payload['time'] : '12:00:00');
        $precision = (string) ($payload['birth_time_precision'] ?? $payload['precision'] ?? UserAstrologyProfile::PRECISION_VERIFIED);
        $approximateWindow = isset($payload['approximate_time_window']) ? (string) $payload['approximate_time_window'] : null;
        $lat = (float) ($payload['latitude'] ?? $payload['lat'] ?? 28.6139);
        $lon = (float) ($payload['longitude'] ?? $payload['lon'] ?? 77.2090);
        $tz = (string) ($payload['timezone'] ?? $payload['tz'] ?? 'Asia/Kolkata');

        try {
            if (isset($kundli['planets']) && isset($kundli['ascendant'])) {
                if (class_exists(ManglikDoshaService::class)) {
                    $manglikService = app(ManglikDoshaService::class);
                    $kundli['manglik_dosha'] = $manglikService->evaluate(
                        planets: $kundli['planets'],
                        lagnaRashiId: $kundli['ascendant']['id'] ?? $kundli['ascendant']
                    );
                }

                if (class_exists(YogaDoshaOrchestrator::class)) {
                    $yogaOrchestrator = app(YogaDoshaOrchestrator::class);
                    $yogas = $yogaOrchestrator->evaluate(
                        planets: $kundli['planets'],
                        ascendantData: $kundli['ascendant'],
                        stabilityData: ['is_lagna_invariant' => true],
                        userAgeYears: 30
                    );
                    $kundli['yogas'] = $yogas['yogas'] ?? [];
                    $kundli['aspects'] = $yogas['aspects'] ?? [];
                }

                if (class_exists(AstrologyIntervalStabilityService::class)) {
                    $stabilityService = app(AstrologyIntervalStabilityService::class);
                    $stability = $stabilityService->analyze(
                        birthDate: $dob,
                        birthTime: $tob,
                        precision: $precision,
                        approximateWindow: $approximateWindow,
                        latitude: $lat,
                        longitude: $lon,
                        timezone: $tz
                    );

                    $d9Asc = $stability['d9_ascendant'] ?? null;
                    $d9Planets = $stability['planetary_navamshas'] ?? [];
                    $d9Houses = null;
                    if ($d9Asc && !empty($d9Asc['id'])) {
                        $d9Houses = \App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler::buildNavamshaHouses((int) $d9Asc['id'], $d9Planets);
                    }

                    $kundli['navamsha'] = [
                        'status' => $d9Asc['status'] ?? 'available',
                        'ascendant' => $d9Asc,
                        'planets' => $d9Planets,
                        'houses' => $d9Houses,
                    ];
                }

                if (class_exists(SaturnTransitService::class)) {
                    $moonSignId = $kundli['planets']['moon']['rashi']['id'] ?? 1;
                    $saturnService = app(SaturnTransitService::class);
                    $kundli['saturn_transit'] = $saturnService->evaluateCurrentPhaseAt($moonSignId, now($tz)->toDateString());
                }
            }
        } catch (Throwable $e) {
            // Keep Core Kundli intact if enrichment encounters non-fatal issue
        }

        return $kundli;
    }

    public function calculatePersonalizedInsights(array $payload): array
    {
        return $this->getPersonalizedAstrologyService()->calculateFromPayload($payload);
    }

    public function isHealthy(): bool
    {
        return extension_loaded('FFI');
    }
}
