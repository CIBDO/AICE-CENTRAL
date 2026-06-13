<?php

use App\Http\Controllers\Api\DashboardReceiverController;
use App\Http\Controllers\Api\DashboardSummaryController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\RegionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('region.token')->group(function () {
    Route::post('/health', [HealthController::class, 'check'])->name('api.health');
    Route::post('/receive/dashboard', [DashboardReceiverController::class, 'receive'])->name('api.receive.dashboard');
});

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'publicCheck'])->name('api.public.health');

    Route::get('/regions', [RegionController::class, 'index'])->name('api.regions.index');
    Route::get('/dashboards/summary', [DashboardSummaryController::class, 'show'])->name('api.dashboards.summary');
});
