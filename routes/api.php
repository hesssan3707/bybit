<?php

use App\Http\Controllers\FuturesController;
use App\Http\Controllers\SpotTradingController;
use App\Http\Controllers\Auth\ApiAuthController;
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
    Route::post('/exchanges', [ApiAuthController::class, 'getAvailableExchanges'])->name('api.auth.exchanges');
});

// Public exchange configuration routes
Route::prefix('exchanges')->group(function () {
    Route::get('/', [ExchangeConfigController::class, 'index'])->name('api.exchanges.index');
    Route::get('/{exchange}', [ExchangeConfigController::class, 'show'])->name('api.exchanges.show');
    Route::get('/{exchange}/features/{feature}', [ExchangeConfigController::class, 'checkFeature'])->name('api.exchanges.feature');
    Route::get('/{exchange}/symbols/{symbol}', [ExchangeConfigController::class, 'checkSymbol'])->name('api.exchanges.symbol');
});

// Public market price API (no authentication required)
Route::get('/market-price/{symbol}', [FuturesController::class, 'getMarketPrice'])->name('api.market.price');

// Protected routes (require authentication)
Route::middleware(['api.auth'])->group(function () {
    // Auth routes for authenticated users
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [ApiAuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/user', [ApiAuthController::class, 'user'])->name('api.auth.user');
        Route::post('/refresh', [ApiAuthController::class, 'refresh'])->name('api.auth.refresh');
    });

    // Legacy futures trading route
    Route::post('/store', [FuturesController::class, 'store'])->name('order.store');

    // Spot Trading API Routes
    Route::prefix('spot')->group(function () {
        // Create spot trading order
        Route::post('/order', [SpotTradingController::class, 'createSpotOrder'])->name('spot.order.create');
        
        // Get account balance separated by currency
        Route::get('/balance', [SpotTradingController::class, 'getAccountBalance'])->name('spot.balance');
        
        // Get spot order history
        Route::get('/orders', [SpotTradingController::class, 'getOrderHistory'])->name('spot.orders.history');
        
        // Get ticker information for a symbol
        Route::get('/ticker', [SpotTradingController::class, 'getTickerInfo'])->name('spot.ticker');
        
        // Cancel spot order
        Route::delete('/order', [SpotTradingController::class, 'cancelOrder'])->name('spot.order.cancel');
        
        // Get available trading pairs
        Route::get('/pairs', [SpotTradingController::class, 'getTradingPairs'])->name('spot.pairs');
    });
    
    // Protected exchange configuration routes
    Route::prefix('exchanges')->group(function () {
        Route::get('/statistics', [ExchangeConfigController::class, 'statistics'])->name('api.exchanges.statistics');
    });
});
