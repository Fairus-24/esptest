<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

Route::middleware(['throttle:http-data', 'ingest.key'])
    ->post('/http-data', [ApiController::class, 'storeHttp']);
