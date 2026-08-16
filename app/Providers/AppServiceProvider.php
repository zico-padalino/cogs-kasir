<?php

namespace App\Providers;

use App\Services\ActivityLogger;
use App\Support\SetupProgress;
use App\Support\ShopSettings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
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

        Event::listen(Login::class, fn (Login $event) => app(ActivityLogger::class)->fromLogin($event));
        Event::listen(Logout::class, fn (Logout $event) => app(ActivityLogger::class)->fromLogout($event));
        Event::listen(Failed::class, fn (Failed $event) => app(ActivityLogger::class)->fromFailed($event));

        View::composer('layouts.app', function ($view) {
            $view->with('setupSteps', SetupProgress::steps());
            $view->with('setupCurrentStep', SetupProgress::currentStepNumber());
            $view->with('setupPercent', SetupProgress::percentComplete());
            $view->with('setupFullyComplete', SetupProgress::isFullyComplete());
        });
    }
}
