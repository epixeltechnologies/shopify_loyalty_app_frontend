<?php

use App\Http\Controllers\Health\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health check routes — see docs/MONITORING.md#health-checks
|--------------------------------------------------------------------------
| Deliberately unauthenticated and outside the 'web'/'api' middleware
| groups — see RouteServiceProvider and HealthController's docblock.
*/
Route::get('/live', [HealthController::class, 'live']);
Route::get('/ready', [HealthController::class, 'ready']);
