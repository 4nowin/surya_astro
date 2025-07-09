<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
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
