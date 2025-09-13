<?php

use App\Http\Controllers\Auth\ApiAuthController;
use App\Http\Controllers\Api\V1\ExchangeController;
use App\Http\Controllers\Api\V1\FuturesController;
use App\Http\Controllers\Api\V1\MarketController;
use App\Http\Controllers\Api\V1\PnlHistoryController;
use App\Http\Controllers\Api\V1\SpotTradingController;
use App\Http\Controllers\Api\V1\WalletBalanceController;
use App\Http\Controllers\Api\ExchangeConfigController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public auth routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [ApiAuthController::class, 'login'])->name('api.auth.login');
    Route::post('/register', [ApiAuthController::class, 'register'])->name('api.auth.register');
});

// Protected routes (require authentication)
Route::middleware(['api.auth'])->group(function () {
    // Auth routes for authenticated users
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [ApiAuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/user', [ApiAuthController::class, 'user'])->name('api.auth.user');
        Route::post('/refresh', [ApiAuthController::class, 'refresh'])->name('api.auth.refresh');
    });

    // Versioned API routes
    Route::prefix('v1')->group(function() {
        // Futures Trading
        Route::prefix('futures')->group(function () {
            Route::get('/orders', [FuturesController::class, 'index'])->name('api.v1.futures.orders.index');
            Route::post('/orders', [FuturesController::class, 'store'])->name('api.v1.futures.orders.store');
            Route::post('/orders/{order}/close', [FuturesController::class, 'close'])->name('api.v1.futures.orders.close');
            Route::delete('/orders/{order}', [FuturesController::class, 'destroy'])->name('api.v1.futures.orders.destroy');
        });

        // Spot Trading
        Route::prefix('spot')->group(function () {
            Route::get('/orders', [SpotTradingController::class, 'index'])->name('api.v1.spot.orders.index');
            Route::get('/orders/{spotOrder}', [SpotTradingController::class, 'show'])->name('api.v1.spot.orders.show');
            Route::post('/orders', [SpotTradingController::class, 'store'])->name('api.v1.spot.orders.store');
            Route::delete('/orders/{spotOrder}', [SpotTradingController::class, 'destroy'])->name('api.v1.spot.orders.destroy');
        });

        // PNL History
        Route::get('/pnl-history', [PnlHistoryController::class, 'index'])->name('api.v1.pnl-history.index');

        // Wallet Balance
        Route::get('/balance', [WalletBalanceController::class, 'balance'])->name('api.v1.balance.index');

        // Exchange Management
        Route::prefix('exchanges')->group(function () {
            Route::get('/', [ExchangeController::class, 'index'])->name('api.v1.exchanges.index');
            Route::post('/', [ExchangeController::class, 'store'])->name('api.v1.exchanges.store');
            Route::put('/{exchange}', [ExchangeController::class, 'update'])->name('api.v1.exchanges.update');
            Route::delete('/{exchange}', [ExchangeController::class, 'destroy'])->name('api.v1.exchanges.destroy');
            Route::post('/{exchange}/switch', [ExchangeController::class, 'switchTo'])->name('api.v1.exchanges.switch');
            Route::post('/{exchange}/test', [ExchangeController::class, 'testConnection'])->name('api.v1.exchanges.test');
        });

        // Market
        Route::post('/best-price', [MarketController::class, 'getBestPrice'])->name('api.v1.best-price');
    });
});
