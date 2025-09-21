<?php

namespace Tests\Traits;

use App\Services\Exchanges\ExchangeApiServiceInterface;
use App\Services\Exchanges\ExchangeFactory;
use Mockery;

trait MocksExchangeFactory
{
    protected $mockExchangeService;

    protected function mockExchangeFactory()
    {
        $this->mockExchangeService = Mockery::mock(ExchangeApiServiceInterface::class);
        
        // Mock the basic exchange service methods that might be called
        $this->mockExchangeService->shouldReceive('setCredentials')
            ->andReturn($this->mockExchangeService);
            
        $this->mockExchangeService->shouldReceive('getAccountInfo')
            ->andReturn(['success' => true, 'data' => []]);
            
        $this->mockExchangeService->shouldReceive('testConnection')
            ->andReturn(['success' => true]);

        // Mock the ExchangeFactory static methods
        $this->partialMock(ExchangeFactory::class, function ($mock) {
            $mock->shouldReceive('create')
                ->andReturn($this->mockExchangeService);
                
            $mock->shouldReceive('createForUserExchange')
                ->andReturn($this->mockExchangeService);
                
            $mock->shouldReceive('createForUser')
                ->andReturn($this->mockExchangeService);
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}