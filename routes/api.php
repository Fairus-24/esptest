<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

Route::post('/http-data', [ApiController::class, 'storeHttp']);
