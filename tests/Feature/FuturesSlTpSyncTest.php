<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;
use App\Models\Trade;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Mockery;

class FuturesSlTpSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_syncs_sl_and_tp_for_a_user_with_mismatched_data()
    {
        // 1. Arrange
        $user = User::factory()->create(['future_strict_mode' => true]);
        $userExchange = UserExchange::factory()->create([
            'user_id' => $user->id,
            'exchange_name' => 'bybit',
            'is_active' => true
            ,'is_default' => true
            ,'status' => 'approved'
            ,'ip_access' => true
            ,'futures_access' => true
            ,'api_key' => 'test_key'
            ,'api_secret' => 'test_secret'
        ]);

        $order = Order::factory()->create([
            'user_exchange_id' => $userExchange->id,
            'status' => 'filled',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'sl' => 100.0, // Database SL
            'tp' => 150.0, // Database TP
            'order_id' => 'order-1',
        ]);

        Trade::factory()->create([
            'user_exchange_id' => $userExchange->id,
            'is_demo' => false,
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'qty' => 1,
            'order_id' => 'order-1',
            'closed_at' => null,
        ]);

        $mockExchangeService = Mockery::mock(ExchangeApiServiceInterface::class);
        Mockery::mock('alias:App\Services\Exchanges\ExchangeFactory')
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($exchangeName, $apiKey, $apiSecret, $isDemo) {
                return strtolower((string)$exchangeName) === 'bybit' && $isDemo === false;
            })
            ->andReturn($mockExchangeService);

        $mockExchangeService->shouldReceive('getPositions')
            ->once()
            ->andReturn([
                'list' => [
                    [
                        'symbol' => 'BTCUSDT',
                        'side' => 'Buy',
                        'size' => '1',
                        'positionIdx' => 1,
                    ]
                ]
            ]);

        $mockExchangeService->shouldReceive('getPositionIdx')->andReturn(1);

        $mockExchangeService->shouldReceive('getConditionalOrders')
            ->once()
            ->with('BTCUSDT')
            ->andReturn(['list' => []]);

        $mockExchangeService->shouldReceive('getOpenOrders')
            ->once()
            ->with('BTCUSDT')
            ->andReturn(['list' => []]);

        $mockExchangeService->shouldReceive('createOrder')
            ->once()
            ->with(Mockery::on(function ($params) use ($order) {
                $triggerPrice = $params['triggerPrice'] ?? null;
                return ($params['symbol'] ?? null) === 'BTCUSDT'
                    && $triggerPrice !== null
                    && abs(((float)$triggerPrice) - ((float)$order->sl)) < 0.0001;
            }))
            ->andReturn(['orderId' => 'sl-1']);

        $mockExchangeService->shouldReceive('createOrder')
            ->once()
            ->with(Mockery::on(function ($params) use ($order) {
                $price = $params['price'] ?? null;
                return ($params['symbol'] ?? null) === 'BTCUSDT'
                    && $price !== null
                    && abs(((float)$price) - ((float)$order->tp)) < 0.0001;
            }))
            ->andReturn(['orderId' => 'tp-1']);

        // 2. Act
        $this->artisan('futures:sync-sltp', ['--user' => $user->id])
            ->expectsOutputToContain('شروع همگام‌سازی Stop Loss و Take Profit...')
            ->assertExitCode(0);
    }
}
