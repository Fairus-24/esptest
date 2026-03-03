<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SimulationController;

Route::get('/reset-data', [DashboardController::class, 'showResetPage'])
    ->name('reset.data.page');
Route::post('/reset-data', [DashboardController::class, 'resetData'])
    ->middleware('throttle:reset-data')
    ->name('reset.data');

Route::prefix('/simulation')->name('simulation.')->group(function () {
    Route::get('/', [SimulationController::class, 'index'])->name('index');
    Route::get('/status', [SimulationController::class, 'status'])->name('status');
    Route::post('/start', [SimulationController::class, 'start'])->name('start');
    Route::post('/stop', [SimulationController::class, 'stop'])->name('stop');
    Route::post('/reset', [SimulationController::class, 'reset'])->name('reset');
    Route::post('/tick', [SimulationController::class, 'tick'])->name('tick');
});
Route::redirect('/simulasi', '/simulation');

Route::get('/admin/login', [AdminConfigController::class, 'loginForm'])->name('admin.login');
Route::get('/admin/login/api/auth/google/redirect', [AdminConfigController::class, 'redirectToGoogle'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.google.redirect');
Route::get('/admin/login/api/auth/google/callback', [AdminConfigController::class, 'handleGoogleCallback'])
    ->name('admin.login.google.callback');
Route::post('/admin/logout', [AdminConfigController::class, 'logout'])->name('admin.logout');

Route::prefix('/admin/config')
    ->middleware('admin.session')
    ->name('admin.config.')
    ->group(function () {
        Route::get('/', [AdminConfigController::class, 'index'])->name('index');
        Route::post('/runtime', [AdminConfigController::class, 'saveRuntime'])->name('runtime.save');
        Route::post('/devices', [AdminConfigController::class, 'storeDevice'])->name('devices.store');
        Route::patch('/devices/{device}', [AdminConfigController::class, 'updateDevice'])->name('devices.update');
        Route::delete('/devices/{device}', [AdminConfigController::class, 'destroyDevice'])->name('devices.destroy');
        Route::post('/devices/{device}/profile', [AdminConfigController::class, 'saveDeviceProfile'])->name('devices.profile.save');
        Route::get('/devices/{device}/firmware/main.cpp', [AdminConfigController::class, 'downloadMain'])->name('devices.firmware.main');
        Route::get('/devices/{device}/firmware/platformio.ini', [AdminConfigController::class, 'downloadPlatformio'])->name('devices.firmware.platformio');
        Route::post('/devices/{device}/firmware/apply', [AdminConfigController::class, 'applyFirmware'])->name('devices.firmware.apply');
    });

Route::view('/doc', 'doc')->name('doc');
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
