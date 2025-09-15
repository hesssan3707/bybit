<?php

use App\Http\Controllers\FuturesController;
use App\Http\Controllers\MACDStrategyController;
use App\Http\Controllers\PnlHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SpotTradingController;
use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\WalletBalanceController;
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

// Registration Routes
Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [RegisterController::class, 'register']);

// Password Reset Routes
Route::get('password/forgot', [PasswordController::class, 'showForgotForm'])->name('password.forgot');
Route::post('password/forgot', [PasswordController::class, 'forgotPassword']);
Route::get('password/reset/{token}', [PasswordController::class, 'showResetForm'])->name('password.reset.form');
Route::post('password/reset', [PasswordController::class, 'resetPassword'])->name('password.reset');

// Public API Documentation Route
Route::get('api-documentation', [ApiDocumentationController::class, 'index'])->name('api.documentation');

Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('futures.orders');
    });

    Route::prefix('futures')->name('futures.')->middleware('exchange.access:futures')->group(function () {
        Route::get('/orders', [FuturesController::class, 'index'])->name('orders');
        Route::get('/set-order', [FuturesController::class, 'create'])->name('order.create');
        Route::post('/set-order', [FuturesController::class, 'store'])->name('order.store');
        Route::post('/orders/{order}/close', [FuturesController::class, 'close'])->name('orders.close');
        Route::delete('/orders/{order}', [FuturesController::class, 'destroy'])->name('orders.destroy');
        Route::get('/pnl-history', [PnlHistoryController::class, 'index'])->name('pnl_history');
    });
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::get('/profile/show', [ProfileController::class, 'index'])->name('profile.show');

    Route::prefix('strategies')->name('strategies.')->group(function () {
        Route::get('/macd', [MACDStrategyController::class, 'index'])->name('macd');
    });

    // Password Change Routes (requires authentication)
    Route::get('/password/change', [PasswordController::class, 'showChangePasswordForm'])->name('password.change.form');
    Route::post('/password/change', [PasswordController::class, 'changePassword'])->name('password.change');

    // Settings Routes (requires authentication)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/activate-future-strict-mode', [SettingsController::class, 'activateFutureStrictMode'])->name('settings.activate-future-strict-mode');

    // Exchange Management Routes (requires authentication)
    Route::prefix('exchanges')->group(function () {
        Route::get('/', [ExchangeController::class, 'index'])->name('exchanges.index');
        Route::get('/create', [ExchangeController::class, 'create'])->name('exchanges.create');
        Route::post('/', [ExchangeController::class, 'store'])->name('exchanges.store');
        Route::get('/{exchange}/edit', [ExchangeController::class, 'edit'])->name('exchanges.edit');
        Route::put('/{exchange}', [ExchangeController::class, 'update'])->name('exchanges.update');
        Route::post('/{exchange}/switch', [ExchangeController::class, 'switchTo'])->name('exchanges.switch');
        Route::post('/{exchange}/test-connection', [ExchangeController::class, 'testConnection'])->name('exchanges.test');
    });

    // Admin Routes (requires authentication and admin privileges)
    Route::prefix('admin')->middleware('admin')->group(function () {
        // User Management
        Route::get('/pending-users', [UserManagementController::class, 'pendingUsers'])->name('admin.pending-users');
        Route::get('/all-users', [UserManagementController::class, 'allUsers'])->name('admin.all-users');
        Route::post('/users/{user}/activate', [UserManagementController::class, 'activateUser'])->name('admin.activate-user');
        Route::post('/users/{user}/deactivate', [UserManagementController::class, 'deactivateUser'])->name('admin.deactivate-user');
        Route::delete('/users/{user}', [UserManagementController::class, 'deleteUser'])->name('admin.delete-user');

        // Exchange Management
        Route::get('/pending-exchanges', [UserManagementController::class, 'pendingExchanges'])->name('admin.pending-exchanges');
        Route::get('/all-exchanges', [UserManagementController::class, 'allExchanges'])->name('admin.all-exchanges');
        Route::post('/exchanges/{exchange}/approve', [UserManagementController::class, 'approveExchange'])->name('admin.approve-exchange');
        Route::post('/exchanges/{exchange}/reject', [UserManagementController::class, 'rejectExchange'])->name('admin.reject-exchange');
        Route::post('/exchanges/{exchange}/deactivate', [UserManagementController::class, 'deactivateExchange'])->name('admin.deactivate-exchange');
        Route::post('/exchanges/{exchange}/test', [UserManagementController::class, 'testExchangeConnection'])->name('admin.test-exchange');
    });

    // Spot Trading Routes - All require authentication
    Route::prefix('spot')->group(function () {
        Route::get('/orders', [SpotTradingController::class, 'spotOrdersView'])->name('spot.orders.view')->middleware('exchange.access:spot');
        Route::get('/create-order', [SpotTradingController::class, 'createSpotOrderView'])->name('spot.order.create.view')->middleware('exchange.access:spot');
        Route::post('/create-order', [SpotTradingController::class, 'storeSpotOrderFromWeb'])->name('spot.order.store.web')->middleware('exchange.access:spot');
        Route::post('/cancel-order', [SpotTradingController::class, 'cancelSpotOrderFromWeb'])->name('spot.order.cancel.web')->middleware('exchange.access:spot');
    });

    // Universal Balance Route (works for both web and mobile)
    Route::get('/balance', [WalletBalanceController::class, 'balance'])->name('balance');

    // Maintenance Routes (should also be protected)

});
Route::get('/schedule', function() {
    Artisan::call('futures:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------------------ lifecycle done----------------------------------------- <br>';
    Artisan::call('futures:enforce');
    print_r(Artisan::output());
    echo '<br>-------------------------------------- enforce done -------------------------------------------------<br>';
    Artisan::call('futures:sync-sltp');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ sync sl tp done --------------------------------------------------<br> ';
    sleep(10);
    Artisan::call('futures:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------------------ lifecycle done----------------------------------------- <br>';
    Artisan::call('futures:enforce');
    print_r(Artisan::output());
    echo '<br>-------------------------------------- enforce done -------------------------------------------------<br>';
    Artisan::call('futures:sync-sltp');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ sync sl tp done --------------------------------------------------<br> ';
    sleep(10);
    Artisan::call('futures:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------------------ lifecycle done----------------------------------------- <br>';
    Artisan::call('futures:enforce');
    print_r(Artisan::output());
    echo '<br>-------------------------------------- enforce done -------------------------------------------------<br>';
    Artisan::call('futures:sync-sltp');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ sync sl tp done --------------------------------------------------<br> ';
    sleep(10);
    Artisan::call('futures:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------------------ lifecycle done----------------------------------------- <br>';
    Artisan::call('futures:enforce');
    print_r(Artisan::output());
    echo '<br>-------------------------------------- enforce done -------------------------------------------------<br>';
    Artisan::call('futures:sync-sltp');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ sync sl tp done --------------------------------------------------<br> ';
    return '************************************************************DONE*******************************************************';
})->middleware('throttle:4');
Route::get('/get-prices', function() {
	Artisan::call('prices:save');
    print_r(Artisan::output());
    return 'DONE';
})->middleware('throttle:4');
Route::get('/validate-exchanges', function() {
	Artisan::call('exchanges:validate-active --force');
    print_r(Artisan::output());
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
