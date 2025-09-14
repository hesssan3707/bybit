<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;
use App\Services\Exchanges\ExchangeFactory;
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
        ]);

        $order = Order::factory()->create([
            'user_exchange_id' => $userExchange->id,
            'status' => 'filled',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'sl' => 100.0, // Database SL
            'tp' => 150.0, // Database TP
        ]);

        // Mock the BybitApiService
        $mockBybitService = Mockery::mock(BybitApiService::class);

        // Mock getPositions to return a position with different SL and TP
        $mockBybitService->shouldReceive('getPositions')
            ->with('BTCUSDT')
            ->andReturn([
                'list' => [
                    [
                        'symbol' => 'BTCUSDT',
                        'side' => 'Buy',
                        'stopLoss' => '110.0', // Exchange SL (mismatched)
                        'takeProfit' => '160.0', // Exchange TP (mismatched)
                        'positionIdx' => 1
                    ]
                ]
            ]);

        // Mock setStopLossAdvanced to expect a call with the correct database SL and TP
        $mockBybitService->shouldReceive('setStopLossAdvanced')
            ->once()
            ->with(Mockery::on(function ($params) use ($order) {
                return $params['stopLoss'] == (string)$order->sl &&
                       $params['takeProfit'] == (string)$order->tp;
            }))
            ->andReturn(['retCode' => 0]); // Simulate success

        // Replace the service container binding with our mock
        $this->app->instance(BybitApiService::class, $mockBybitService);

        // 2. Act
        $this->artisan('futures:sync-sltp', ['--user' => $user->id])
            ->expectsOutputToContain('Successfully updated SL/TP for user')
            ->assertExitCode(0);
    }
}
