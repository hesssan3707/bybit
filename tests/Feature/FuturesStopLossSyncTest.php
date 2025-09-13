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

class FuturesStopLossSyncTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        UserExchange::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    public function test_futures_stop_loss_sync_command()
    {
        $this->actingAs($this->user);

        $mock = Mockery::mock(ExchangeApiServiceInterface::class);
        $mock->shouldReceive('getOpenPositions')->andReturn([
            [
                'symbol' => 'BTCUSDT',
                'side' => 'Buy',
                'size' => 0.001,
                'positionValue' => 50,
                'entryPrice' => 50000,
                'liqPrice' => 25000,
                'bustPrice' => 25000,
                'markPrice' => 50000,
                'unrealisedPnl' => 0,
                'leverage' => 1,
                'positionIdx' => 1,
                'stopLoss' => 48000,
                'takeProfit' => 52000,
            ]
        ]);
        $mock->shouldReceive('setStopLossAdvanced')->andReturn(true);

        ExchangeFactory::shouldReceive('createForUser')->andReturn($mock);

        $this->artisan('futures:sync-sl')
            ->expectsOutput('Futures stop loss sync started.')
            ->expectsOutput('Futures stop loss sync completed.')
            ->assertExitCode(0);
    }
}
