<?php

namespace Tests\Traits;

use App\Services\Exchanges\ExchangeApiServiceInterface;
use App\Services\Exchanges\ExchangeFactory;
use Mockery;

trait MocksExchangeFactory
{
    protected $mockExchangeService;

    protected function setUpExchangeMocking()
    {
        $this->mockExchangeService = Mockery::mock(ExchangeApiServiceInterface::class);
        
        // Override the ExchangeFactory methods using a custom approach
        $originalFactory = ExchangeFactory::class;
        
        // Create a mock that will be returned by the factory
        $this->app->bind('exchange.service.mock', function () {
            return $this->mockExchangeService;
        });
        
        // We'll need to modify the controllers to use dependency injection instead
        // For now, let's try a different approach
    }

    protected function tearDownExchangeMocking()
    {
        Mockery::close();
    }
}