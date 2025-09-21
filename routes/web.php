<?php

use App\Http\Controllers\FuturesController;
use App\Http\Controllers\MACDStrategyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AccountSettingsController;
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
Route::get('register/confirmation', [RegisterController::class, 'showConfirmationForm'])->name('register.confirmation');
Route::post('register/confirmation', [RegisterController::class, 'verifyConfirmation'])->name('register.confirmation.verify');

// Password Reset Routes
Route::get('password/forgot', [PasswordController::class, 'showForgotForm'])->name('password.forgot');
Route::post('password/forgot', [PasswordController::class, 'forgotPassword']);
Route::get('password/reset/{token}', [PasswordController::class, 'showResetForm'])->name('password.reset.form');
Route::post('password/reset', [PasswordController::class, 'resetPassword'])->name('password.reset');

// Public API Documentation Route
Route::get('api-documentation', [ApiDocumentationController::class, 'index'])->name('api.documentation');

// Root route - handles both authenticated and unauthenticated users
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('futures.orders');
    }
    return redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function () {

    Route::prefix('futures')->name('futures.')->middleware('exchange.access:futures')->group(function () {
        Route::get('/orders', [FuturesController::class, 'index'])->name('orders');
        Route::get('/set-order', [FuturesController::class, 'create'])->name('order.create');
        Route::post('/set-order', [FuturesController::class, 'store'])->name('order.store');
        Route::post('/orders/{order}/close', [FuturesController::class, 'close'])->name('orders.close');
        Route::delete('/orders/{order}', [FuturesController::class, 'destroy'])->name('orders.destroy');
        Route::get('/pnl-history', [FuturesController::class, 'pnlHistory'])->name('pnl_history');
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

    // Account Settings Routes (requires authentication)
    Route::prefix('account-settings')->name('account-settings.')->group(function () {
        Route::get('/', [AccountSettingsController::class, 'index'])->name('index');
        Route::post('/update', [AccountSettingsController::class, 'update'])->name('update');
        Route::get('/settings', [AccountSettingsController::class, 'getSettings'])->name('settings');
    });

    // Exchange Management Routes (requires authentication)
    Route::prefix('exchanges')->group(function () {
        Route::get('/', [ExchangeController::class, 'index'])->name('exchanges.index');
        Route::get('/create', [ExchangeController::class, 'create'])->name('exchanges.create');
        Route::post('/', [ExchangeController::class, 'store'])->name('exchanges.store');
        Route::get('/{exchange}/edit', [ExchangeController::class, 'edit'])->name('exchanges.edit');
        Route::put('/{exchange}', [ExchangeController::class, 'update'])->name('exchanges.update');
        Route::post('/{exchange}/switch', [ExchangeController::class, 'switchTo'])->name('exchanges.switch');
        Route::post('/{exchange}/switch-mode', [ExchangeController::class, 'switchMode'])->name('exchanges.switch-mode');
        Route::post('/{exchange}/test-connection', [ExchangeController::class, 'testConnection'])->name('exchanges.test');
        Route::post('/{exchange}/test-real-connection', [ExchangeController::class, 'testRealConnection'])->name('exchanges.test-real-connection');
        Route::post('/{exchange}/test-demo-connection', [ExchangeController::class, 'testDemoConnection'])->name('exchanges.test-demo-connection');
        
        // Routes for testing connections during creation (without existing exchange)
        Route::post('/test-real-connection', [ExchangeController::class, 'testConnectionApi'])->name('exchanges.test-real-connection-create');
        Route::post('/test-demo-connection', [ExchangeController::class, 'testConnectionApi'])->name('exchanges.test-demo-connection-create');
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

Route::get('/demo-schedule', function() {
    Artisan::call('demo:futures:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------------------ demo lifecycle done----------------------------------------- <br>';
    Artisan::call('demo:futures:enforce');
    print_r(Artisan::output());
    echo '<br>-------------------------------------- demo enforce done -------------------------------------------------<br>';
    Artisan::call('demo:futures:sync-sltp');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ demo sync sl tp done --------------------------------------------------<br> ';
    Artisan::call('demo:spot:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ demo spot lifecycle done --------------------------------------------------<br> ';
    sleep(10);
    Artisan::call('demo:futures:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------------------ demo lifecycle done----------------------------------------- <br>';
    Artisan::call('demo:futures:enforce');
    print_r(Artisan::output());
    echo '<br>-------------------------------------- demo enforce done -------------------------------------------------<br>';
    Artisan::call('demo:futures:sync-sltp');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ demo sync sl tp done --------------------------------------------------<br> ';
    Artisan::call('demo:spot:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ demo spot lifecycle done --------------------------------------------------<br> ';
    sleep(10);
    Artisan::call('demo:futures:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------------------ demo lifecycle done----------------------------------------- <br>';
    Artisan::call('demo:futures:enforce');
    print_r(Artisan::output());
    echo '<br>-------------------------------------- demo enforce done -------------------------------------------------<br>';
    Artisan::call('demo:futures:sync-sltp');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ demo sync sl tp done --------------------------------------------------<br> ';
    Artisan::call('demo:spot:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ demo spot lifecycle done --------------------------------------------------<br> ';
    sleep(10);
    Artisan::call('demo:futures:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------------------ demo lifecycle done----------------------------------------- <br>';
    Artisan::call('demo:futures:enforce');
    print_r(Artisan::output());
    echo '<br>-------------------------------------- demo enforce done -------------------------------------------------<br>';
    Artisan::call('demo:futures:sync-sltp');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ demo sync sl tp done --------------------------------------------------<br> ';
    Artisan::call('demo:spot:lifecycle');
    print_r(Artisan::output());
    echo '<br>------------------------------------------ demo spot lifecycle done --------------------------------------------------<br> ';
    return '************************************************************DEMO DONE*******************************************************';
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

Route::get('/demo-validate-exchanges', function() {
    Artisan::call('demo:exchanges:validate-active --force');
    print_r(Artisan::output());
    return 'DEMO VALIDATION DONE';
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
