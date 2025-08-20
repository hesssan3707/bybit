<?php

use App\Http\Controllers\BybitController;
use App\Http\Controllers\PnlHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Auth Routes
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/', [BybitController::class, 'index']); // Redirect home to orders list
    Route::get('/set-order', [BybitController::class, 'create'])->name('order.create');
    Route::post('/set-order', [BybitController::class, 'store'])->name('order.store');
    Route::get('/orders', [BybitController::class, 'index'])->name('orders.index');
    Route::post('/orders/{order}/close', [BybitController::class, 'close'])->name('orders.close');
    Route::delete('/orders/{order}', [BybitController::class, 'destroy'])->name('orders.destroy');
    Route::get('/pnl-history', [PnlHistoryController::class, 'index'])->name('pnl.history');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');

    // Maintenance Routes (should also be protected)
    
});
Route::get('/schedule', function() {
    Artisan::call('bybit:lifecycle');
    print_r(Artisan::output());
    echo '<br> lifecycle 1 done  <br>';
    Artisan::call('bybit:enforce');
    print_r(Artisan::output());
    echo '<br> enforce done <br>';
    Artisan::call('bybit:sync-sl');
    print_r(Artisan::output());
    echo '<br> sync sl done <br> ';
    return 'DONE';
})->middleware('throttle:2');

// Non-protected utility routes if needed, but it's better to protect them.
// For simplicity, we can leave these out for now or protect them as well.
// Route::get('/re-cache', function() {
//     Artisan::call('config:clear');
//     Artisan::call('cache:clear');
//     // Artisan::call('route:clear');
//     // Artisan::call('view:clear');

//     Artisan::call('config:cache');
//     // Artisan::call('route:cache');
//     // Artisan::call('view:cache');
//     // Artisan::call('event:cache');

//     // Optional: dump output for debugging
//     echo Artisan::output();
// 	return 'DONE';
// });
// Route::get('/migrate', function() {
//     Artisan::call('migrate');
//     return 'Migration done!';
// });
