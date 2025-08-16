<?php

use App\Http\Controllers\BybitController;
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

Route::get('/set-order', function () {
    return view('set_order');
});
Route::post('/set-order', [BybitController::class, 'store'])->name('order.store');
