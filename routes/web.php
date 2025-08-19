<?php

use App\Http\Controllers\BybitController;
use App\Http\Controllers\PnlHistoryController;
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

Route::get('/', function () {
    return view('welcome');
});
Route::get('/clear-cache', function() {
    $exitCode = Artisan::call('cache:clear');
    $exitCode = Artisan::call('config:cache');
    return 'DONE'; //Return anything
});
Route::get('/migrate', function() {
    Artisan::call('migrate');
    return 'DONE'; //Return anything
});
Route::get('/seed', function() {
    Artisan::call('db:seed');
    return 'DONE'; //Return anything
});
Route::get('/link', function() {
    Artisan::call('storage:link');
    return 'DONE'; //Return anything
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
    // sleep(25);
    // Artisan::call('bybit:lifecycle');
    echo '<br> lifecycle 2 done <br> ';
    return 'DONE'; //Return anything
});

Route::get('/set-order', [BybitController::class, 'create'])->name('order.create');
Route::post('/set-order', [BybitController::class, 'store'])->name('order.store');
Route::get('/orders', [BybitController::class, 'index'])->name('orders.index');
Route::post('/orders/{bybitOrder}/close', [BybitController::class, 'close'])->name('orders.close');
Route::delete('/orders/{bybitOrder}', [BybitController::class, 'destroy'])->name('orders.destroy');

Route::get('/pnl-history', [PnlHistoryController::class, 'index'])->name('pnl.history');
