<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\Trade;
use App\Observers\TradeObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        // Use our unified, simple pagination view globally
        Paginator::defaultView('pagination::default');
        Paginator::defaultSimpleView('pagination::default');

        // Register observers
        Trade::observe(TradeObserver::class);
    }
}
