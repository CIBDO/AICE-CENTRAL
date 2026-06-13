<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BanqueController;
use App\Http\Controllers\Api\CentralSummaryController;
use App\Http\Controllers\Api\DashboardReceiverController;
use App\Http\Controllers\Api\DashboardSummaryController;
use App\Http\Controllers\Api\ExecutiveController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MouvementController;
use App\Http\Controllers\Api\NatureCeController;
use App\Http\Controllers\Api\RecetteController;
use App\Http\Controllers\Api\ProgrammeController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('region.token')->group(function () {
    Route::post('/health', [HealthController::class, 'check'])->name('api.health');
    Route::post('/receive/dashboard', [DashboardReceiverController::class, 'receive'])->name('api.receive.dashboard');
});

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'publicCheck'])->name('api.public.health');

    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/auth/user', [AuthController::class, 'user'])->name('api.auth.user');
        Route::put('/auth/first-login', [AuthController::class, 'firstLogin'])->name('api.auth.first-login');
        Route::put('/auth/password', [AuthController::class, 'changePassword'])->name('api.auth.password');

        Route::get('/regions', [RegionController::class, 'index'])->name('api.regions.index');
        Route::get('/regions/admin', [RegionController::class, 'adminIndex'])->name('api.regions.admin');
        Route::put('/regions/{region}', [RegionController::class, 'update'])->name('api.regions.update');
        Route::get('/dashboards/summary', [DashboardSummaryController::class, 'show'])->name('api.dashboards.summary');
        Route::get('/central/summary', [CentralSummaryController::class, 'show'])->name('api.central.summary');

        Route::get('/executive/kpis', [ExecutiveController::class, 'kpis'])->name('api.executive.kpis');
        Route::get('/executive/alertes', [ExecutiveController::class, 'alertes'])->name('api.executive.alertes');
        Route::get('/executive/anomalies', [ExecutiveController::class, 'anomalies'])->name('api.executive.anomalies');
        Route::get('/executive/predictions', [ExecutiveController::class, 'predictions'])->name('api.executive.predictions');

        Route::get('/mouvements', [MouvementController::class, 'index'])->name('api.mouvements.index');
        Route::get('/mouvements/export', [MouvementController::class, 'export'])->name('api.mouvements.export');
        Route::get('/mouvements/{id}', [MouvementController::class, 'show'])->name('api.mouvements.show');
        Route::get('/recettes', [RecetteController::class, 'index'])->name('api.recettes.index');
        Route::get('/recettes/export', [RecetteController::class, 'export'])->name('api.recettes.export');
        Route::get('/banques', [BanqueController::class, 'index'])->name('api.banques.index');
        Route::get('/banques/export', [BanqueController::class, 'export'])->name('api.banques.export');
        Route::get('/programmes', [ProgrammeController::class, 'index'])->name('api.programmes.index');
        Route::get('/programmes/export', [ProgrammeController::class, 'export'])->name('api.programmes.export');
        Route::get('/natures-ce', [NatureCeController::class, 'index'])->name('api.natures-ce.index');
        Route::get('/natures-ce/export', [NatureCeController::class, 'export'])->name('api.natures-ce.export');

        Route::get('/users', [UserController::class, 'index'])->name('api.users.index');
        Route::post('/users', [UserController::class, 'store'])->name('api.users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('api.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('api.users.destroy');

        Route::get('/roles', [RoleController::class, 'index'])->name('api.roles.index');
    });
});
