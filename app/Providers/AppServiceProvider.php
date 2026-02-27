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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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
        $this->configureProductionUrlScheme();
        $this->configureRateLimiters();

        if ($this->app->runningInConsole() || $this->app->runningUnitTests()) {
            return;
        }

        app(LaravelHttpAutoStarter::class)->ensureRunning();
        app(MosquittoAutoStarter::class)->ensureRunning();
        app(MqttWorkerAutoStarter::class)->ensureRunning();
    }

    private function configureProductionUrlScheme(): void
    {
        // Behind Nginx reverse proxy, force URL generation to HTTPS when APP_URL is https://
        // so forms/redirects do not downgrade to HTTP in browser.
        $appUrl = trim((string) config('app.url', ''));
        $forceHttps = filter_var(env('APP_FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)
            || Str::startsWith(strtolower($appUrl), 'https://');

        if (!$forceHttps) {
            return;
        }

        URL::forceScheme('https');
        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
        }
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
