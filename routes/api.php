<?php

use App\Http\Controllers\Auth\ApiAuthController;
use App\Http\Controllers\Api\V1\ExchangeController;
use App\Http\Controllers\Api\V1\FuturesController;
use App\Http\Controllers\Api\V1\MarketController;
use App\Http\Controllers\Api\V1\PriceController;
use App\Http\Controllers\Api\V1\SpotTradingController;
use App\Http\Controllers\Api\V1\WalletBalanceController;
use App\Http\Controllers\Api\V1\PeriodController as ApiPeriodController;
use App\Http\Controllers\Api\ExchangeConfigController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

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

// Public API routes
Route::prefix('v1')->group(function() {
    // Prices Service (Public)
    Route::get('/prices', [PriceController::class, 'index'])->name('api.v1.prices.index');
});

// Test connection route (requires authentication)
Route::middleware(['api.auth'])->group(function () {
    Route::post('/test-connection', [\App\Http\Controllers\ExchangeController::class, 'testConnectionApi'])->name('api.test-connection');
});

// Protected routes (require authentication)
Route::middleware(['api.auth', 'restrict.investor'])->group(function () {
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

        // Futures Periods
        Route::prefix('futures/periods')->group(function () {
            Route::get('/', [ApiPeriodController::class, 'index'])->name('api.v1.futures.periods.index');
            Route::post('/', [ApiPeriodController::class, 'store'])->name('api.v1.futures.periods.store');
            Route::post('/{period}/end', [ApiPeriodController::class, 'end'])->name('api.v1.futures.periods.end');
        });

        // Spot Trading
        Route::prefix('spot')->group(function () {
            Route::get('/orders', [SpotTradingController::class, 'index'])->name('api.v1.spot.orders.index');
            Route::get('/orders/{spotOrder}', [SpotTradingController::class, 'show'])->name('api.v1.spot.orders.show');
            Route::post('/orders', [SpotTradingController::class, 'store'])->name('api.v1.spot.orders.store');
            Route::delete('/orders/{spotOrder}', [SpotTradingController::class, 'destroy'])->name('api.v1.spot.orders.destroy');
        });

        // PNL History
        Route::get('/pnl-history', [FuturesController::class, 'pnlHistory'])->name('api.v1.pnl-history.index');

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

Route::get('/funding-snapshots-sync', function () {
    Artisan::call('futures:sync-funding-snapshots');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ sync funding snapshots done --------------------------------------------------</div>';
})->middleware('throttle:4');

// Public maintenance / cron-style routes (no session, api middleware group)
Route::middleware('throttle:4')->get('/schedule', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #007cba;border-radius:5px;white-space:pre-wrap;}.separator{color:#007cba;font-weight:bold;text-align:center;margin:15px 0;padding:10px;background:#e7f3ff;border-radius:5px;}</style></head><body>';

    Artisan::call('futures:lifecycle');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------------------ lifecycle done -----------------------------------------</div>';

    Artisan::call('futures:enforce');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">-------------------------------------- enforce done -------------------------------------------------</div>';

    Artisan::call('futures:sync-sltp');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ sync sl tp done --------------------------------------------------</div>';

    sleep(10);

    Artisan::call('futures:lifecycle');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------------------ lifecycle done -----------------------------------------</div>';

    Artisan::call('futures:enforce');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">-------------------------------------- enforce done -------------------------------------------------</div>';

    Artisan::call('futures:sync-sltp');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ sync sl tp done --------------------------------------------------</div>';

    sleep(10);

    Artisan::call('futures:lifecycle');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------------------ lifecycle done -----------------------------------------</div>';

    Artisan::call('futures:enforce');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">-------------------------------------- enforce done -------------------------------------------------</div>';

    Artisan::call('futures:sync-sltp');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ sync sl tp done --------------------------------------------------</div>';

    sleep(10);

    Artisan::call('futures:lifecycle');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------------------ lifecycle done -----------------------------------------</div>';

    Artisan::call('futures:enforce');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">-------------------------------------- enforce done -------------------------------------------------</div>';

    Artisan::call('futures:sync-sltp');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ sync sl tp done --------------------------------------------------</div>';

    echo '<div style="text-align:center;color:#28a745;font-weight:bold;font-size:18px;margin-top:20px;">************************************************************DONE*******************************************************</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:4')->get('/demo-schedule', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #ff6b35;border-radius:5px;white-space:pre-wrap;}.separator{color:#ff6b35;font-weight:bold;text-align:center;margin:15px 0;padding:10px;background:#fff3e0;border-radius:5px;}</style></head><body>';

    Artisan::call('demo:futures:lifecycle');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------------------ demo lifecycle done -----------------------------------------</div>';

    Artisan::call('demo:futures:enforce');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">-------------------------------------- demo enforce done -------------------------------------------------</div>';

    Artisan::call('demo:futures:sync-sltp');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ demo sync sl tp done --------------------------------------------------</div>';

    Artisan::call('demo:spot:lifecycle');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ demo spot lifecycle done --------------------------------------------------</div>';

    echo '<div style="text-align:center;color:#ff6b35;font-weight:bold;font-size:18px;margin-top:20px;">************************************************************DEMO DONE*******************************************************</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:4')->get('/orchestrate', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #007cba;border-radius:5px;white-space:pre-wrap;}.separator{color:#007cba;font-weight:bold;text-align:center;margin:15px 0;padding:10px;background:#e7f3ff;border-radius:5px;}</style></head><body>';

    Artisan::call('futures:orchestrate');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ orchestrate done --------------------------------------------------</div>';

    sleep(10);

    Artisan::call('futures:orchestrate');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ orchestrate done --------------------------------------------------</div>';

    sleep(10);

    Artisan::call('futures:orchestrate');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ orchestrate done --------------------------------------------------</div>';

    sleep(10);

    Artisan::call('futures:orchestrate');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ orchestrate done --------------------------------------------------</div>';

    echo '<div style="text-align:center;color:#28a745;font-weight:bold;font-size:18px;margin-top:20px;">************************************************************DONE*******************************************************</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:4')->get('/demo-orchestrate', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #ff6b35;border-radius:5px;white-space:pre-wrap;}.separator{color:#ff6b35;font-weight:bold;text-align:center;margin:15px 0;padding:10px;background:#fff3e0;border-radius:5px;}</style></head><body>';

    Artisan::call('demo:futures:orchestrate');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div class="separator">------------------------------------------ demo orchestrate done --------------------------------------------------</div>';

    echo '<div style="text-align:center;color:#ff6b35;font-weight:bold;font-size:18px;margin-top:20px;">************************************************************DEMO DONE*******************************************************</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:4')->get('/get-prices', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #28a745;border-radius:5px;white-space:pre-wrap;}</style></head><body>';

    Artisan::call('prices:save');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div style="text-align:center;color:#28a745;font-weight:bold;font-size:18px;margin-top:20px;">DONE</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:2')->get('/validate-exchanges', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #007cba;border-radius:5px;white-space:pre-wrap;}</style></head><body>';

    Artisan::call('exchanges:validate-active --force');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div style="text-align:center;color:#007cba;font-weight:bold;font-size:18px;margin-top:20px;">DONE</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:2')->get('/demo-validate-exchanges', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #ff6b35;border-radius:5px;white-space:pre-wrap;}</style></head><body>';

    Artisan::call('demo:exchanges:validate-active --force');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div style="text-align:center;color:#ff6b35;font-weight:bold;font-size:18px;margin-top:20px;">DEMO VALIDATION DONE</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:2')->get('/spot-lifecycle', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #6f42c1;border-radius:5px;white-space:pre-wrap;}</style></head><body>';

    Artisan::call('spot:lifecycle');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div style="text-align:center;color:#6f42c1;font-weight:bold;font-size:18px;margin-top:20px;">DONE</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:2')->get('/demo-spot-lifecycle', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #ff6b35;border-radius:5px;white-space:pre-wrap;}</style></head><body>';

    Artisan::call('demo:spot:lifecycle');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div style="text-align:center;color:#ff6b35;font-weight:bold;font-size:18px;margin-top:20px;">DEMO SPOT LIFECYCLE DONE</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:4')->get('/collect-candles', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #28a745;border-radius:5px;white-space:pre-wrap;}</style></head><body>';
    
    Artisan::call('futures:collect-missing-candles');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div style="text-align:center;color:#28a745;font-weight:bold;font-size:18px;margin-top:20px;">CANDLE COLLECTION DONE</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:10')->get('/process-queue', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #6f42c1;border-radius:5px;white-space:pre-wrap;}</style></head><body>';
    
    Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--max-jobs' => 100,
        '--max-time' => 30,
    ]);
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div style="text-align:center;color:#6f42c1;font-weight:bold;font-size:18px;margin-top:20px;">QUEUE PROCESSING DONE</div>';
    echo '</body></html>';
    return '';
});

Route::middleware('throttle:2')->get('/cleanup-sessions', function () {
    echo '<html><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:Arial,sans-serif;background:#f5f5f5;margin:20px;}.output{background:#fff;padding:15px;margin:10px 0;border-right:4px solid #6f42c1;border-radius:5px;white-space:pre-wrap;}</style></head><body>';
    
    Artisan::call('sessions:cleanup-database');
    echo '<div class="output">' . htmlspecialchars(Artisan::output()) . '</div>';
    echo '<div style="text-align:center;color:#6f42c1;font-weight:bold;font-size:18px;margin-top:20px;">SESSION CLEANUP DONE</div>';
    echo '</body></html>';
    return '';
});
