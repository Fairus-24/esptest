<?php

namespace App\Providers;

use App\Services\LaravelHttpAutoStarter;
use App\Services\MosquittoAutoStarter;
use App\Services\MqttWorkerAutoStarter;
use App\Services\RuntimeConfigOverrideService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        app(RuntimeConfigOverrideService::class)->apply();
        $this->configureRateLimiters();

        if ($this->app->runningInConsole() || $this->app->runningUnitTests()) {
            return;
        }

        app(LaravelHttpAutoStarter::class)->ensureRunning();
        app(MosquittoAutoStarter::class)->ensureRunning();
        app(MqttWorkerAutoStarter::class)->ensureRunning();
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('http-data', function (Request $request) {
            $perMinute = max(30, (int) config('http_server.ingest_rate_limit_per_minute', 240));
            $deviceId = (string) $request->input('device_id', 'unknown');

            return [
                Limit::perMinute($perMinute)->by('http-data:ip:' . $request->ip()),
                Limit::perMinute($perMinute)->by('http-data:device:' . $deviceId),
            ];
        });

        RateLimiter::for('reset-data', function (Request $request) {
            return Limit::perMinute(6)->by('reset-data:' . $request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(12)->by('admin-login:' . $request->ip());
        });
    }
}
