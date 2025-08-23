<?php

use App\Http\Controllers\BybitController;
use App\Http\Controllers\SpotTradingController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Existing futures trading route
Route::post('/store', [BybitController::class, 'store'])->name('order.store');

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
