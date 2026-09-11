<?php

use App\Http\Controllers\Api\V1\BalanceSheetController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BuyerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\IncomeStatementController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReceivablesAgeingController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SalePaymentController;
use App\Http\Controllers\Api\V1\SitePlanController;
use App\Http\Controllers\Api\V1\StandController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\TrialBalanceController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('login', [TokenController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login');

    /**
     * `branch` settles which office the request is worked from before anything
     * queries, so an administrator switching branches changes every list,
     * document number and statement on the page at once.
     */
    Route::middleware(['auth:sanctum', 'branch'])->group(function (): void {
        Route::post('logout', [TokenController::class, 'destroy'])->name('logout');
        Route::get('user', [UserController::class, 'show'])->name('user.show');
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');

        Route::get('site-plan', [SitePlanController::class, 'show'])->name('site-plan.show');
        Route::apiResource('stands', StandController::class)->only(['index', 'show']);

        Route::apiResource('buyers', BuyerController::class)->only(['index', 'store']);
        Route::apiResource('sales', SaleController::class)->only(['index', 'show', 'store']);
        Route::post('sales/{sale}/payments', [SalePaymentController::class, 'store'])->name('sales.payments.store');

        /** A receipt is a payment seen from the buyer's side, so both read from the same record. */
        Route::apiResource('receipts', ReceiptController::class)
            ->only(['index', 'show'])
            ->parameters(['receipts' => 'payment']);

        /** Head office only: it reports across every branch at once. */
        Route::get('dashboard', [DashboardController::class, 'show'])
            ->middleware('admin')
            ->name('dashboard.show');

        Route::prefix('reports')->name('reports.')->group(function (): void {
            Route::get('trial-balance', [TrialBalanceController::class, 'show'])->name('trial-balance');
            Route::get('income-statement', [IncomeStatementController::class, 'show'])->name('income-statement');
            Route::get('balance-sheet', [BalanceSheetController::class, 'show'])->name('balance-sheet');
            Route::get('receivables-ageing', [ReceivablesAgeingController::class, 'show'])->name('receivables-ageing');
        });
    });
});
