<?php

namespace Tests\Feature;

use App\Console\Commands\FuturesStopLossSync;
use App\Models\Order;
use App\Models\User;
use App\Models\UserExchange;
use App\Services\Exchanges\BybitApiService;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class FuturesStopLossSyncTest extends TestCase
{
    use RefreshDatabase;

    public function testStopLossSyncWithCorrectApiParameters()
    {
        // Create test user with strict mode enabled
        $user = User::factory()->create([
            'future_strict_mode' => true
        ]);

        // Create user exchange
        $userExchange = UserExchange::factory()->create([
            'user_id' => $user->id,
            'exchange_name' => 'bybit',
            'api_key' => 'test_key',
            'api_secret' => 'test_secret',
            'is_active' => true
        ]);

        // Create a filled order
        $order = Order::factory()->create([
            'user_exchange_id' => $userExchange->id,
            'status' => 'filled',
            'symbol' => 'ETHUSDT',
            'side' => 'Buy',
            'sl' => 2500.50
        ]);

        // Mock the exchange service
        $mockExchangeService = Mockery::mock(BybitApiService::class);
        
        // Mock getPositions to return a position with different SL
        $mockExchangeService->shouldReceive('getPositions')
            ->with('ETHUSDT')
            ->andReturn([
                'list' => [
                    [
                        'symbol' => 'ETHUSDT',
                        'side' => 'Buy',
                        'stopLoss' => '2400.00', // Different from DB (2500.50)
                        'takeProfit' => '3000.00',
                        'positionIdx' => 0,
                        'tpTriggerBy' => 'LastPrice',
                        'slTriggerBy' => 'LastPrice'
                    ]
                ]
            ]);

        // Mock setStopLossAdvanced to verify correct parameters are passed
        $mockExchangeService->shouldReceive('setStopLossAdvanced')
            ->once()
            ->with(Mockery::on(function ($params) {
                // Verify all required parameters are present and correct
                return $params['category'] === 'linear' &&
                       $params['symbol'] === 'ETHUSDT' &&
                       $params['stopLoss'] === '2500.5' &&
                       $params['tpslMode'] === 'Full' &&
                       $params['positionIdx'] === 0 &&
                       $params['takeProfit'] === '3000.00' &&
                       isset($params['tpTriggerBy']) &&
                       isset($params['slTriggerBy']);
            }))
            ->andReturn(['retCode' => 0, 'retMsg' => 'OK']);

        // Mock ExchangeFactory
        ExchangeFactory::shouldReceive('create')
            ->with('bybit', 'test_key', 'test_secret')
            ->andReturn($mockExchangeService);

        // Run the command
        $command = new FuturesStopLossSync();
        $result = $this->artisan('futures:sync-sl', ['--user' => $user->id]);

        $result->assertExitCode(0);
    }

    public function testStopLossSyncSkipsNonStrictModeUsers()
    {
        // Create test user with strict mode disabled
        $user = User::factory()->create([
            'future_strict_mode' => false
        ]);

        // Create user exchange
        UserExchange::factory()->create([
            'user_id' => $user->id,
            'exchange_name' => 'bybit',
            'api_key' => 'test_key',
            'api_secret' => 'test_secret',
            'is_active' => true
        ]);

        // Run the command
        $result = $this->artisan('futures:sync-sl', ['--user' => $user->id]);

        $result->assertExitCode(0);
        $result->expectsOutput("User {$user->id} does not have future strict mode enabled. Skipping...");
    }

    public function testBybitApiServiceSetStopLossWithRequiredParameters()
    {
        $service = new BybitApiService();
        $service->setCredentials('test_key', 'test_secret');

        // Mock the setTradingStop method to verify correct parameters
        $service = Mockery::mock(BybitApiService::class)->makePartial();
        $service->shouldReceive('setTradingStop')
            ->once()
            ->with(Mockery::on(function ($params) {
                // Verify tpslMode is included (this was the missing parameter)
                return $params['category'] === 'linear' &&
                       $params['symbol'] === 'BTCUSDT' &&
                       $params['stopLoss'] === '45000' &&
                       $params['positionIdx'] === 0 &&
                       $params['tpslMode'] === 'Full'; // This is the critical fix
            }))
            ->andReturn(['retCode' => 0, 'retMsg' => 'OK']);

        $result = $service->setStopLoss('BTCUSDT', 45000.00, 'Buy');
        
        $this->assertEquals(['retCode' => 0, 'retMsg' => 'OK'], $result);
    }

    public function testBybitApiServiceSetStopLossAdvanced()
    {
        $service = new BybitApiService();
        $service->setCredentials('test_key', 'test_secret');

        // Mock the setTradingStop method
        $service = Mockery::mock(BybitApiService::class)->makePartial();
        $service->shouldReceive('setTradingStop')
            ->once()
            ->with(Mockery::on(function ($params) {
                return $params['category'] === 'linear' &&
                       $params['symbol'] === 'ETHUSDT' &&
                       $params['stopLoss'] === '2500.5' &&
                       $params['takeProfit'] === '3000' &&
                       $params['tpslMode'] === 'Full' &&
                       $params['positionIdx'] === 0 &&
                       $params['tpTriggerBy'] === 'LastPrice' &&
                       $params['slTriggerBy'] === 'LastPrice';
            }))
            ->andReturn(['retCode' => 0, 'retMsg' => 'OK']);

        $params = [
            'category' => 'linear',
            'symbol' => 'ETHUSDT',
            'stopLoss' => '2500.5',
            'takeProfit' => '3000',
            'tpslMode' => 'Full',
            'positionIdx' => 0,
            'tpTriggerBy' => 'LastPrice',
            'slTriggerBy' => 'LastPrice'
        ];

        $result = $service->setStopLossAdvanced($params);
        
        $this->assertEquals(['retCode' => 0, 'retMsg' => 'OK'], $result);
    }

    public function testStopLossSyncWithStrategy4CancelAndReset()
    {
        // Create test user with strict mode enabled
        $user = User::factory()->create([
            'future_strict_mode' => true
        ]);

        // Create user exchange
        $userExchange = UserExchange::factory()->create([
            'user_id' => $user->id,
            'exchange_name' => 'bybit',
            'api_key' => 'test_key',
            'api_secret' => 'test_secret',
            'is_active' => true
        ]);

        // Create a filled order
        $order = Order::factory()->create([
            'user_exchange_id' => $userExchange->id,
            'status' => 'filled',
            'symbol' => 'ETHUSDT',
            'side' => 'Buy',
            'sl' => 2500.50
        ]);

        // Mock the exchange service
        $mockExchangeService = Mockery::mock(BybitApiService::class);
        
        // Mock getPositions to return a position with different SL
        $mockExchangeService->shouldReceive('getPositions')
            ->with('ETHUSDT')
            ->andReturn([
                'list' => [
                    [
                        'symbol' => 'ETHUSDT',
                        'side' => 'Buy',
                        'stopLoss' => '2400.00', // Different from DB (2500.50)
                        'takeProfit' => '3000.00',
                        'positionIdx' => 0,
                        'size' => '0.1'
                    ]
                ]
            ]);

        // Mock all first 3 strategies to fail (simulating the "Unknown error" scenario)
        $mockExchangeService->shouldReceive('setStopLossAdvanced')
            ->times(3) // Strategy 1, 2, and 3
            ->andThrow(new \Exception('Bybit API Error: Unknown error'));

        // Mock Strategy 4: getConditionalOrders to return existing SL orders
        $mockExchangeService->shouldReceive('getConditionalOrders')
            ->with('ETHUSDT')
            ->andReturn([
                'list' => [
                    [
                        'orderId' => 'sl-order-123',
                        'symbol' => 'ETHUSDT',
                        'stopLoss' => '2400.00',
                        'reduceOnly' => true,
                        'triggerPrice' => '2400.00',
                        'stopOrderType' => 'Stop'
                    ]
                ]
            ]);

        // Mock cancelOrderWithSymbol for the existing SL order
        $mockExchangeService->shouldReceive('cancelOrderWithSymbol')
            ->with('sl-order-123', 'ETHUSDT')
            ->andReturn(['retCode' => 0, 'retMsg' => 'OK']);

        // Mock final setStopLossAdvanced for Strategy 4 (should succeed)
        $mockExchangeService->shouldReceive('setStopLossAdvanced')
            ->once()
            ->with(Mockery::on(function ($params) {
                return $params['category'] === 'linear' &&
                       $params['symbol'] === 'ETHUSDT' &&
                       $params['stopLoss'] === '2500.5' &&
                       $params['tpslMode'] === 'Full' &&
                       $params['positionIdx'] === 0 &&
                       $params['takeProfit'] === '3000.00';
            }))
            ->andReturn(['retCode' => 0, 'retMsg' => 'OK']);

        // Mock ExchangeFactory
        ExchangeFactory::shouldReceive('create')
            ->with('bybit', 'test_key', 'test_secret')
            ->andReturn($mockExchangeService);

        // Run the command
        $result = $this->artisan('futures:sync-sl', ['--user' => $user->id]);

        $result->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}