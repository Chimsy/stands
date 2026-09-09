<?php

use App\Http\Controllers\Api\V1\SitePlanController;
use App\Http\Controllers\Api\V1\StandController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('login', [TokenController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [TokenController::class, 'destroy'])->name('logout');
        Route::get('user', [UserController::class, 'show'])->name('user.show');

        Route::get('site-plan', [SitePlanController::class, 'show'])->name('site-plan.show');
        Route::apiResource('stands', StandController::class)->only(['index', 'show']);
    });
});
