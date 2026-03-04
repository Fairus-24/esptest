<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\GitSyncWebhookController;

Route::middleware(['throttle:http-data', 'ingest.key'])
    ->post('/http-data', [ApiController::class, 'storeHttp']);

Route::middleware(['throttle:30,1'])
    ->post('/git-sync/webhook', GitSyncWebhookController::class);
