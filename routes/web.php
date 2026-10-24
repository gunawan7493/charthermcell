<?php

use App\Http\Controllers\Api\ControlController;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::prefix('api')->name('api.')->group(function (): void {
    Route::get('/telemetry', TelemetryController::class)
        ->middleware('throttle:telemetry')
        ->name('telemetry');

    Route::post('/controls/buzzer', [ControlController::class, 'buzzer'])
        ->middleware('throttle:controls')
        ->name('controls.buzzer');

    Route::post('/controls/calibrate', [ControlController::class, 'calibrate'])
        ->middleware('throttle:controls')
        ->name('controls.calibrate');
});
