<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsureAdminSession;
use App\Http\Middleware\VerifyIngestKey;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust reverse-proxy headers from Nginx so Laravel can detect HTTPS/original host.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'ingest.key' => VerifyIngestKey::class,
            'admin.session' => EnsureAdminSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (SymfonyResponse $response, \Throwable $exception, Request $request) {
            if (
                $response->getStatusCode() === 419
                && $request->isMethod('post')
                && ($request->routeIs('reset.data') || $request->is('reset-data'))
            ) {
                $controller = app(DashboardController::class);
                $payload = $controller->buildResetPagePayload();
                $payload['statusType'] = 'error';
                $payload['statusMessage'] = 'Gagal: sesi keamanan reset sudah kedaluwarsa. Muat ulang halaman lalu kirim ulang.';

                return $controller->renderResetPage($payload);
            }

            return $response;
        });
    })->create();
