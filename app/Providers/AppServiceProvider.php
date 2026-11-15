<?php

namespace App\Providers;

use App\Services\Astrology\Calculation\CalculationHandlerRegistry;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Drivers\CalculationDriverInterface;
use App\Services\Astrology\Calculation\Drivers\LocalCalculationDriver;
use App\Services\Astrology\Calculation\Drivers\RemoteHttpCalculationDriver;
use App\Services\Astrology\Calculation\Handlers\DailyPanchangCalculationHandler;
use App\Services\Astrology\Calculation\Handlers\DetailedKundliReportCalculationHandler;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use App\Services\Astrology\Calculation\Handlers\PersonalizedInsightsCalculationHandler;
use App\Services\Astrology\DailyPanchangService;
use App\Services\Astrology\Interpretation\DetailedInterpretationService;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\Pdf\SvgVargaRenderer;
use App\Services\Astrology\Reports\DetailedKundliReportService;
use App\Services\Astrology\Stability\AstrologyIntervalStabilityService;
use App\Services\Astrology\Transits\SaturnTransitService;
use App\Services\Astrology\Yogas\YogaDoshaOrchestrator;
use App\Services\Geo\GeocodingServiceInterface;
use App\Services\Geo\LocalGazetteerGeocodingProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\Kreait\Firebase\Auth::class, function ($app) {
            $configured = config('services.firebase.credentials_path');
            $candidates = [
                $configured,
                $configured ? base_path($configured) : null,
                $configured ? storage_path($configured) : null,
                $configured ? storage_path('app/' . $configured) : null,
                $configured && str_starts_with($configured, 'storage/app/') ? storage_path(substr($configured, strlen('storage/app/'))) : null,
                $configured && str_starts_with($configured, 'storage/') ? storage_path(substr($configured, strlen('storage/'))) : null,
                storage_path('app/firebase/navgarah-bba2c-firebase-adminsdk-fbsvc-a2292dc6bc.json'),
            ];

            $resolved = null;
            foreach ($candidates as $candidate) {
                if ($candidate && file_exists($candidate) && !is_dir($candidate)) {
                    $resolved = $candidate;
                    break;
                }
            }

            if (!$resolved) {
                \Illuminate\Support\Facades\Log::warning('Firebase credentials file could not be resolved from configured path.');
                throw new \RuntimeException('Firebase credentials file not found.');
            }

            return (new Factory)
                ->withServiceAccount($resolved)
                ->createAuth();
        });

        $this->app->singleton(GeocodingServiceInterface::class, function ($app) {
            return new LocalGazetteerGeocodingProvider();
        });

        // Reusable Astrology Background Calculation System
        // Explicitly instantiate without resolving native calculation services to keep web SAPI free from native FFI initialization
        $this->app->singleton(KundliService::class, function () {
            return new KundliService();
        });

        $this->app->singleton(DailyPanchangService::class, function () {
            return new DailyPanchangService();
        });

        $this->app->singleton(SaturnTransitService::class, function () {
            return new SaturnTransitService();
        });

        $this->app->singleton(AstrologyIntervalStabilityService::class, function () {
            return new AstrologyIntervalStabilityService();
        });

        $this->app->singleton(CalculationDriverInterface::class, function ($app) {
            $driver = config('astrology.driver', 'local');
            if ($driver === 'remote') {
                $remoteConfig = config('astrology.remote', []);
                // Strictly disable local fallback when driver is remote to prevent native FFI crashes on Hostinger
                return new RemoteHttpCalculationDriver(
                    baseUrl: (string) ($remoteConfig['url'] ?? $remoteConfig['base_url'] ?? 'http://127.0.0.1:8080'),
                    secret: (string) ($remoteConfig['secret'] ?? ''),
                    connectTimeout: (float) ($remoteConfig['connect_timeout'] ?? 3.0),
                    timeout: (float) ($remoteConfig['timeout'] ?? 10.0),
                    fallbackToLocal: false,
                    localDriver: null
                );
            }

            return new LocalCalculationDriver();
        });

        $this->app->singleton(DailyPanchangCalculationHandler::class, function ($app) {
            return new DailyPanchangCalculationHandler(null, $app->make(CalculationDriverInterface::class));
        });

        $this->app->singleton(PersonalizedInsightsCalculationHandler::class, function ($app) {
            return new PersonalizedInsightsCalculationHandler(null, $app->make(CalculationDriverInterface::class));
        });

        $this->app->singleton(KundliCalculationHandler::class, function ($app) {
            return new KundliCalculationHandler(null, $app->make(CalculationDriverInterface::class));
        });

        $this->app->singleton(DetailedKundliReportCalculationHandler::class, function () {
            return new DetailedKundliReportCalculationHandler();
        });

        $this->app->singleton(DetailedKundliReportService::class, function ($app) {
            if (config('astrology.driver') === 'remote') {
                return new DetailedKundliReportService(
                    kundliService: null,
                    stabilityService: null,
                    yogaOrchestrator: $app->make(YogaDoshaOrchestrator::class),
                    transitService: null,
                    svgRenderer: $app->make(SvgVargaRenderer::class),
                    interpretationService: $app->make(DetailedInterpretationService::class)
                );
            }

            return new DetailedKundliReportService();
        });

        $this->app->singleton(CalculationHandlerRegistry::class, function ($app) {
            $registry = new CalculationHandlerRegistry();
            $registry->register($app->make(DailyPanchangCalculationHandler::class));
            $registry->register($app->make(PersonalizedInsightsCalculationHandler::class));
            $registry->register($app->make(KundliCalculationHandler::class));
            $registry->register($app->make(DetailedKundliReportCalculationHandler::class));
            return $registry;
        });

        $this->app->singleton(CalculationJobManager::class);
        $this->app->singleton(CalculationWorkerLauncher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Schema::defaultStringLength(191);

        $locale = Session::get('locale', config('app.locale'));
        App::setLocale($locale);
    }
}
