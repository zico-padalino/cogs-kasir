<?php

namespace App\Providers;

use App\Support\SetupProgress;
use App\Support\ShopSettings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        ShopSettings::applyToConfig();

        View::composer('layouts.app', function ($view) {
            $view->with('setupSteps', SetupProgress::steps());
            $view->with('setupCurrentStep', SetupProgress::currentStepNumber());
            $view->with('setupPercent', SetupProgress::percentComplete());
            $view->with('setupFullyComplete', SetupProgress::isFullyComplete());
        });
    }
}
