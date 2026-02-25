<?php

namespace App\Providers;

use App\Services\LaravelHttpAutoStarter;
use App\Services\MosquittoAutoStarter;
use App\Services\MqttWorkerAutoStarter;
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
        if ($this->app->runningInConsole() || $this->app->runningUnitTests()) {
            return;
        }

        app(LaravelHttpAutoStarter::class)->ensureRunning();
        app(MosquittoAutoStarter::class)->ensureRunning();
        app(MqttWorkerAutoStarter::class)->ensureRunning();
    }
}
