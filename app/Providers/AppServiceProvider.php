<?php

namespace App\Providers;

use App\Services\Astrology\Calculation\CalculationHandlerRegistry;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\DailyPanchangCalculationHandler;
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
            $credentialsPath = storage_path('app/' . config('services.firebase.credentials_path'));
            return (new Factory)
                ->withServiceAccount($credentialsPath)
                ->createAuth();
        });

        $this->app->singleton(GeocodingServiceInterface::class, function ($app) {
            return new LocalGazetteerGeocodingProvider();
        });

        // Reusable Astrology Background Calculation System
        // Explicitly instantiate without resolving DailyPanchangService to keep web SAPI free from native FFI initialization
        $this->app->singleton(DailyPanchangCalculationHandler::class, function () {
            return new DailyPanchangCalculationHandler();
        });

        $this->app->singleton(CalculationHandlerRegistry::class, function ($app) {
            $registry = new CalculationHandlerRegistry();
            $registry->register($app->make(DailyPanchangCalculationHandler::class));
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
