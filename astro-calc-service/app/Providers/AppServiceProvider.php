<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Astrology\Calculation\Handlers\DailyPanchangCalculationHandler;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use App\Services\Astrology\DailyPanchangService;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\PersonalizedAstrologyService;
use App\Services\Astrology\Stability\AstrologyIntervalStabilityService;
use App\Services\Astrology\Transits\SaturnTransitService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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

        $this->app->singleton(DailyPanchangCalculationHandler::class, function () {
            return new DailyPanchangCalculationHandler();
        });

        $this->app->singleton(KundliCalculationHandler::class, function () {
            return new KundliCalculationHandler();
        });

        $this->app->singleton(PersonalizedAstrologyService::class, function () {
            return new PersonalizedAstrologyService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Standalone calculation daemon boot logic
    }
}
