<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Composer\Autoload\ClassLoader;
use FFI;
use Illuminate\Console\Command;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use JmeEph\FFI\JmeEphFFI;
use Throwable;

class TestAstrologyPoc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astrology:poc
                            {--date=2026-11-01 : Date for calculation (YYYY-MM-DD)}
                            {--time=12:00:00 : Time in UTC for planetary calculations (HH:MM:SS)}
                            {--lat=28.6139 : Geographic Latitude (default: New Delhi)}
                            {--lon=77.2090 : Geographic Longitude (default: New Delhi)}
                            {--tz=Asia/Kolkata : Timezone identifier}
                            {--json : Output report as raw JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute isolated proof of concept for the Vedic Astrology & Ephemeris Engine (Moshier)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->output->title('ASTRO SETU — VEDIC ASTROLOGY ENGINE PROOF OF CONCEPT (POC)');

        $asJson = (bool) $this->option('json');
        $dateStr = (string) $this->option('date');
        $timeStr = (string) $this->option('time');
        $lat = (float) $this->option('lat');
        $lon = (float) $this->option('lon');
        $tz = (string) $this->option('tz');

        $overallStart = hrtime(true);
        $memStart = memory_get_usage(true);

        $report = [
            'timestamp' => now()->toIso8601String(),
            'platform' => [
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'os' => PHP_OS . ' (' . php_uname('m') . ')',
                'ffi_loaded' => extension_loaded('FFI'),
            ],
            'native_library' => [],
            'ephemeris_engine' => [],
            'planetary_positions' => [],
            'panchang' => [],
            'execution_metrics' => [],
        ];

        // 1. Verify and Register Autoloader for isolated packages
        $jmeSrc = base_path('packages/astrology/jpl-moshier-ephemeris-php/src/');
        $panchangSrc = base_path('packages/astrology/panchang-core/src/');

        if (!is_dir($jmeSrc) || !is_dir($panchangSrc)) {
            $this->error("Isolated packages directory not found at packages/astrology/.");
            $this->line("Ensure 'packages/astrology/jpl-moshier-ephemeris-php' and 'packages/astrology/panchang-core' are present.");
            return Command::FAILURE;
        }

        $loader = new ClassLoader();
        $loader->addPsr4('JmeEph\\', $jmeSrc);
        $loader->addPsr4('JayeshMepani\\PanchangCore\\', $panchangSrc);
        $loader->register();

        // 2. Load Native Ephemeris Library via FFI
        $this->info('1. Loading Native Ephemeris Library...');
        try {
            $isWindows = PHP_OS_FAMILY === 'Windows';
            $defaultLib = $isWindows
                ? base_path('packages/astrology/jpl-moshier-ephemeris-php/libs/jme-windows-x64-2022/jme.dll')
                : base_path('packages/astrology/jpl-moshier-ephemeris-php/libs/jme-linux-x64-ubuntu22/libjme.so');

            // Fallback for newer Linux kernels if ubuntu22 is not present
            if (!$isWindows && !file_exists($defaultLib)) {
                $altLib = base_path('packages/astrology/jpl-moshier-ephemeris-php/libs/jme-linux-x64-ubuntu24/libjme.so');
                if (file_exists($altLib)) {
                    $defaultLib = $altLib;
                }
            }

            $jme = new JmeEphFFI($defaultLib);
            $buf = $jme->getFFI()->new('char[128]');
            $jme->jme_version($buf, 128);
            $nativeVersion = FFI::string($buf);

            $report['native_library'] = [
                'status' => 'SUCCESS',
                'library_path' => $defaultLib,
                'engine_version' => $nativeVersion,
            ];

            $this->line("   <info>✓</info> Native C Library loaded successfully: version {$nativeVersion}");
            $this->line("   <comment>→</comment> Path: {$defaultLib}");
        } catch (Throwable $e) {
            $this->error("   ✗ Failed to load native ephemeris library: " . $e->getMessage());
            $report['native_library'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
            ];
            if ($asJson) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            return Command::FAILURE;
        }

        // 3. Configure Moshier Engine Explicitly (No silent fallback)
        $this->info('2. Configuring Engine: Explicit MOSHIER Analytical Mode (No JPL .bsp kernels required)...');
        try {
            $jme->configureEngine('MOSHIER');

            // Assert that Sun and Moon resolve to MOSHIER and do not fall back
            $resolved = JmeRuntime::assertSunMoonEngine($jme, 'MOSHIER');
            JmeRuntime::setTrueCitra($jme); // True Citra Paksha Lahiri Ayanamsha

            $report['ephemeris_engine'] = [
                'selected_engine' => 'MOSHIER',
                'ayanamsha' => 'True Citra (Lahiri)',
                'resolved_bodies' => $resolved,
                'silent_fallback_check' => 'PASSED (Strictly enforced)',
                'jpl_kernels_required' => false,
            ];

            $this->line("   <info>✓</info> Engine active: MOSHIER Analytical Series (Sun ID: {$resolved['sun']}, Moon ID: {$resolved['moon']})");
            $this->line("   <info>✓</info> Ayanamsha: True Citra Paksha Lahiri (Spica 180° calibrated)");
        } catch (Throwable $e) {
            $this->error("   ✗ Failed to configure or assert Moshier engine: " . $e->getMessage());
            $report['ephemeris_engine'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
            ];
            if ($asJson) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            return Command::FAILURE;
        }

        // 4. Calculate Planetary Positions
        $this->info("3. Calculating Planetary Positions for {$dateStr} {$timeStr} UTC...");
        $planetStart = hrtime(true);

        [$year, $month, $day] = array_map('intval', explode('-', $dateStr));
        [$hour, $minute, $second] = array_map('floatval', explode(':', $timeStr));
        $decimalHour = $hour + ($minute / 60.0) + ($second / 3600.0);

        $jd = $jme->jme_julian_day($year, $month, $day, $decimalHour, JmeEphFFI::JME_CALENDAR_GREGORIAN);
        $ayanamsaUt = $jme->jme_get_ayanamsa_ut($jd);

        $bodies = [
            'Sun' => JmeEphFFI::JME_BODY_SUN,
            'Moon' => JmeEphFFI::JME_BODY_MOON,
            'Mercury' => JmeEphFFI::JME_BODY_MERCURY,
            'Venus' => JmeEphFFI::JME_BODY_VENUS,
            'Mars' => JmeEphFFI::JME_BODY_MARS,
            'Jupiter' => JmeEphFFI::JME_BODY_JUPITER,
            'Saturn' => JmeEphFFI::JME_BODY_SATURN,
            'Uranus' => JmeEphFFI::JME_BODY_URANUS,
            'Neptune' => JmeEphFFI::JME_BODY_NEPTUNE,
            'Pluto' => JmeEphFFI::JME_BODY_PLUTO,
            'Rahu (Mean Node)' => JmeEphFFI::JME_BODY_MEAN_NODE,
            'Rahu (True Node)' => JmeEphFFI::JME_BODY_TRUE_NODE,
        ];

        $ffi = $jme->getFFI();
        $results = $ffi->new('double[6]');
        $errorBuf = $ffi->new('char[256]');

        $planetRows = [];
        foreach ($bodies as $name => $bodyId) {
            // Tropical
            $jme->jme_calc_ut($jd, $bodyId, JmeEphFFI::JME_CALC_SPEED, $results, $errorBuf);
            $tropLon = $results[0];
            $speed = $results[3];

            // Sidereal (Lahiri)
            $jme->jme_calc_ut($jd, $bodyId, JmeEphFFI::JME_CALC_SPEED | JmeEphFFI::JME_CALC_SIDEREAL, $results, $errorBuf);
            $sidLon = $results[0];

            $signIndex = (int) floor($sidLon / 30.0);
            $signDeg = fmod($sidLon, 30.0);
            $signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];
            $signName = $signs[$signIndex] ?? 'Unknown';

            $planetRows[] = [
                'body' => $name,
                'tropical' => sprintf('%.4f°', $tropLon),
                'sidereal' => sprintf('%.4f°', $sidLon),
                'rashi' => sprintf('%s %.2f°', $signName, $signDeg),
                'speed' => sprintf('%.4f°/d', $speed),
                'motion' => $speed < 0 ? 'Retrograde (R)' : 'Direct',
            ];

            $report['planetary_positions'][$name] = [
                'body_id' => $bodyId,
                'tropical_longitude' => $tropLon,
                'sidereal_longitude' => $sidLon,
                'sign' => $signName,
                'sign_degrees' => $signDeg,
                'daily_speed' => $speed,
                'is_retrograde' => $speed < 0,
            ];
        }

        // Derive Ketu (Opposite to Rahu by 180 degrees)
        $rahuSid = $report['planetary_positions']['Rahu (Mean Node)']['sidereal_longitude'];
        $ketuSid = fmod($rahuSid + 180.0, 360.0);
        $ketuSignIdx = (int) floor($ketuSid / 30.0);
        $ketuSignName = $signs[$ketuSignIdx] ?? 'Unknown';
        $planetRows[] = [
            'body' => 'Ketu (Mean Node)',
            'tropical' => sprintf('%.4f°', fmod($report['planetary_positions']['Rahu (Mean Node)']['tropical_longitude'] + 180.0, 360.0)),
            'sidereal' => sprintf('%.4f°', $ketuSid),
            'rashi' => sprintf('%s %.2f°', $ketuSignName, fmod($ketuSid, 30.0)),
            'speed' => $planetRows[10]['speed'],
            'motion' => 'Retrograde (R)',
        ];
        $report['planetary_positions']['Ketu (Mean Node)'] = [
            'body_id' => -999,
            'sidereal_longitude' => $ketuSid,
            'sign' => $ketuSignName,
            'is_retrograde' => true,
        ];

        $planetElapsed = (hrtime(true) - $planetStart) / 1e6;

        $this->table(['Celestial Body', 'Tropical Lon', 'Sidereal Lon (Lahiri)', 'Rashi Placement', 'Daily Speed', 'Motion'], $planetRows);
        $this->line("   <comment>→</comment> Julian Day: {$jd} | Lahiri Ayanamsha: " . sprintf('%.5f°', $ayanamsaUt) . " | Calculated in " . round($planetElapsed, 2) . " ms");

        // 5. Calculate Vedic Panchang
        $this->info("4. Calculating Vedic Panchang for {$dateStr} at Lat: {$lat}, Lon: {$lon} ({$tz})...");
        $panchangStart = hrtime(true);

        try {
            app()->instance(JmeEphFFI::class, $jme);
            config(['panchang.jme_settings.mode' => 'moshier']);

            $provider = new PanchangServiceProvider(app());
            $provider->register();

            /** @var PanchangService $panchangService */
            $panchangService = app()->make(PanchangService::class);
            $calcDate = CarbonImmutable::parse($dateStr, $tz);

            $panchanga = $panchangService->getPanchanga($calcDate, $lat, $lon, $tz);
            $rahuKaalData = $panchangService->getSection($calcDate, $lat, $lon, $tz, 'Rahu_Kaal_Gulika_Yamaganda');

            // Format Panchang attributes
            $tithiIdx = (int) ($panchanga['Tithi']['index'] ?? 0);
            $nakIdx = (int) (isset($panchanga['Nakshatra']['name']) ? array_search($panchanga['Nakshatra']['name'], [
                'Ashwini', 'Bharani', 'Krittika', 'Rohini', 'Mrigashirsha', 'Ardra', 'Punarvasu', 'Pushya', 'Ashlesha',
                'Magha', 'Purva Phalguni', 'Uttara Phalguni', 'Hasta', 'Chitra', 'Swati', 'Vishakha', 'Anuradha', 'Jyeshtha',
                'Mula', 'Purva Ashadha', 'Uttara Ashadha', 'Shravana', 'Dhanishtha', 'Shatabhisha', 'Purva Bhadrapada', 'Uttara Bhadrapada', 'Revati'
            ], true) : 0);
            $yogaIdx = (int) ($panchanga['Yoga']['index'] ?? 0);
            $karanaIdx = (int) ($panchanga['Karana']['index'] ?? 0);
            $varaIdx = (int) ($panchanga['Vara']['index'] ?? 0);

            $panchangTable = [
                ['Element', 'English Name', 'Hindi Translation (हिन्दी)', 'Timing / Window'],
                ['Vara (Day)', $panchanga['Vara']['name'] ?? 'N/A', Localization::translate('Vara', $varaIdx, 'hi'), 'All Day'],
                ['Tithi', $panchanga['Tithi']['name'] ?? 'N/A', Localization::translate('Tithi', $tithiIdx, 'hi'), 'Ends at ' . ($panchanga['tithi_end_dt'] ?? 'N/A')],
                ['Nakshatra', $panchanga['Nakshatra']['name'] ?? 'N/A', Localization::translate('Nakshatra', $nakIdx, 'hi'), 'Pada ' . ($panchanga['Nakshatra']['pada'] ?? 1) . ' | Ends at ' . ($panchanga['nakshatra_end_dt'] ?? 'N/A')],
                ['Yoga', $panchanga['Yoga']['name'] ?? 'N/A', Localization::translate('Yoga', $yogaIdx, 'hi'), 'Ends at ' . ($panchanga['yoga_end_dt'] ?? 'N/A')],
                ['Karana', $panchanga['Karana']['name'] ?? 'N/A', Localization::translate('Karana', $karanaIdx % 11, 'hi'), 'Ends at ' . ($panchanga['karana_end_dt'] ?? 'N/A')],
                ['Sunrise', $panchanga['Sunrise']['display'] ?? 'N/A', 'सूर्योदय', $panchanga['Sunrise']['display'] ?? 'N/A'],
                ['Sunset', $panchanga['Sunset']['display'] ?? 'N/A', 'सूर्यास्त', $panchanga['Sunset']['display'] ?? 'N/A'],
                ['Rahu Kaal', $rahuKaalData['Rahu Kaal']['start'] . ' - ' . $rahuKaalData['Rahu Kaal']['end'], 'राहु काल (अशुभ)', 'Duration: ' . ($rahuKaalData['Rahu Kaal']['duration_min'] ?? 'N/A')],
                ['Gulika Kaal', $rahuKaalData['Gulika']['start'] . ' - ' . $rahuKaalData['Gulika']['end'], 'गुलिक काल', 'Duration: ' . ($rahuKaalData['Gulika']['duration_min'] ?? 'N/A')],
                ['Yamaganda', $rahuKaalData['Yamaganda']['start'] . ' - ' . $rahuKaalData['Yamaganda']['end'], 'यमगण्ड', 'Duration: ' . ($rahuKaalData['Yamaganda']['duration_min'] ?? 'N/A')],
            ];

            $panchangElapsed = (hrtime(true) - $panchangStart) / 1e6;

            $this->table($panchangTable[0], array_slice($panchangTable, 1));
            $this->line("   <info>✓</info> Panchang calculated in " . round($panchangElapsed, 2) . " ms");

            $report['panchang'] = [
                'status' => 'SUCCESS',
                'vara' => [
                    'english' => $panchanga['Vara']['name'] ?? null,
                    'hindi' => Localization::translate('Vara', $varaIdx, 'hi'),
                ],
                'tithi' => [
                    'english' => $panchanga['Tithi']['name'] ?? null,
                    'hindi' => Localization::translate('Tithi', $tithiIdx, 'hi'),
                    'ends_at' => $panchanga['tithi_end_dt'] ?? null,
                ],
                'nakshatra' => [
                    'english' => $panchanga['Nakshatra']['name'] ?? null,
                    'hindi' => Localization::translate('Nakshatra', $nakIdx, 'hi'),
                    'pada' => $panchanga['Nakshatra']['pada'] ?? null,
                    'ends_at' => $panchanga['nakshatra_end_dt'] ?? null,
                ],
                'yoga' => [
                    'english' => $panchanga['Yoga']['name'] ?? null,
                    'hindi' => Localization::translate('Yoga', $yogaIdx, 'hi'),
                    'ends_at' => $panchanga['yoga_end_dt'] ?? null,
                ],
                'karana' => [
                    'english' => $panchanga['Karana']['name'] ?? null,
                    'hindi' => Localization::translate('Karana', $karanaIdx % 11, 'hi'),
                    'ends_at' => $panchanga['karana_end_dt'] ?? null,
                ],
                'solar_events' => [
                    'sunrise' => $panchanga['Sunrise']['display'] ?? null,
                    'sunset' => $panchanga['Sunset']['display'] ?? null,
                    'moonrise' => $panchanga['Moonrise']['display'] ?? null,
                    'moonset' => $panchanga['Moonset']['display'] ?? null,
                ],
                'inauspicious_periods' => [
                    'rahu_kaal' => $rahuKaalData['Rahu Kaal'] ?? null,
                    'gulika' => $rahuKaalData['Gulika'] ?? null,
                    'yamaganda' => $rahuKaalData['Yamaganda'] ?? null,
                ],
            ];
        } catch (Throwable $e) {
            $this->error("   ✗ Failed to calculate Panchang: " . $e->getMessage());
            $report['panchang'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
            ];
        }

        // 6. Metrics & Summary
        $overallElapsed = (hrtime(true) - $overallStart) / 1e6;
        $memEnd = memory_get_usage(true);
        $memPeak = memory_get_peak_usage(true);

        $report['execution_metrics'] = [
            'total_execution_time_ms' => round($overallElapsed, 2),
            'memory_used_mb' => round(($memEnd - $memStart) / 1024 / 1024, 2),
            'memory_peak_mb' => round($memPeak / 1024 / 1024, 2),
        ];

        $this->line('');
        $this->alert(sprintf(
            "POC EXECUTION COMPLETE — Total Time: %.2f ms | Peak Memory: %.2f MB",
            $overallElapsed,
            $memPeak / 1024 / 1024
        ));

        if ($asJson) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return Command::SUCCESS;
    }
}
