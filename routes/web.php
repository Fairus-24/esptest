
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

Route::post('/reset-data', [DashboardController::class, 'resetData'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('reset.data');
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
