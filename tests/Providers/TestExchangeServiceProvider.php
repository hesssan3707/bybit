<?php

namespace Tests\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Exchanges\ExchangeFactory;
use Tests\Mocks\TestExchangeService;

class TestExchangeServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Override the ExchangeFactory to return our test service
        $this->app->bind(ExchangeFactory::class, function ($app) {
            return new class {
                public static function createForUser($userId)
                {
                    return new TestExchangeService();
                }

                public static function createForUserExchange($userExchange)
                {
                    return new TestExchangeService();
                }

                public static function create($exchangeName, $apiKey = null, $apiSecret = null, $isDemo = false)
                {
                    return new TestExchangeService();
                }
            };
        });
    }
}