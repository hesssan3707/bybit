<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Services\Exchanges\BybitApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class BybitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Order::truncate();
        Trade::truncate();
    }

    /**
     * @test
     */
    public function it_prevents_creating_an_order_if_a_loss_occurred_within_the_last_hour()
    {
        // Arrange
        Trade::create([
            'pnl' => -10,
            'closed_at' => now()->subMinutes(30),
            'symbol' => 'ETHUSDT',
            'side' => 'Buy',
            'order_type' => 'Limit',
            'leverage' => 1,
            'qty' => 1,
            'avg_entry_price' => 2500,
            'avg_exit_price' => 2490,
            'order_id' => '123',
        ]);

        $postData = [
            'entry1' => 3000,
            'entry2' => 3000,
            'tp' => 3100,
            'sl' => 2900,
            'steps' => 1,
            'expire' => 15,
            'risk_percentage' => 1,
        ];

        // Act
        $response = $this->actingAs($this->user)->post(route('order.store'), $postData);

        // Assert
        $response->assertSessionHasErrors('msg');
        $this->assertDatabaseMissing('orders', ['entry_price' => 3000]);
    }

    /**
     * @test
     */
    public function it_allows_creating_an_order_if_the_last_loss_was_more_than_an_hour_ago()
    {
        // Arrange
        $this->mock(BybitApiService::class, function ($mock) {
            $mock->shouldReceive('getTickerInfo')->andReturn(['list' => [['lastPrice' => '3000']]]);
            $mock->shouldReceive('getWalletBalance')->andReturn(['list' => [['totalWalletBalance' => '1000', 'totalEquity' => '1000']]]);
            $mock->shouldReceive('getInstrumentsInfo')->andReturn(['list' => [['lotSizeFilter' => ['qtyStep' => '0.01'], 'priceScale' => '2']]]);
            $mock->shouldReceive('createOrder')->andReturn(['orderId' => '12345']);
        });

        Trade::create([
            'pnl' => -10,
            'closed_at' => now()->subMinutes(70),
            'symbol' => 'ETHUSDT',
            'side' => 'Buy',
            'order_type' => 'Limit',
            'leverage' => 1,
            'qty' => 1,
            'avg_entry_price' => 2500,
            'avg_exit_price' => 2490,
            'order_id' => '123',
        ]);

        $postData = [
            'entry1' => 3000,
            'entry2' => 3000,
            'tp' => 3100,
            'sl' => 2900,
            'steps' => 1,
            'expire' => 15,
            'risk_percentage' => 1,
        ];

        // Act
        $response = $this->actingAs($this->user)->post(route('order.store'), $postData);

        // Assert
        $response->assertSessionDoesntHaveErrors('msg');
    }

    /**
     * @test
     */
    public function it_prevents_creating_an_order_if_another_order_is_already_active()
    {
        // Arrange
        Order::create([
            'status' => 'pending', // or 'filled'
            'entry_price' => 2500,
            'tp' => 2600,
            'sl' => 2400,
        ]);

        $postData = [
            'entry1' => 3000,
            'entry2' => 3000,
            'tp' => 3100,
            'sl' => 2900,
            'steps' => 1,
            'expire' => 15,
            'risk_percentage' => 1,
        ];

        // Act
        $response = $this->actingAs($this->user)->post(route('order.store'), $postData);

        // Assert
        $response->assertSessionHasErrors('msg');
        $this->assertDatabaseCount('orders', 1); // Ensure no new order was created
    }

    /**
     * @test
     */
    public function it_revokes_a_pending_order()
    {
        // Arrange
        $this->mock(BybitApiService::class, function ($mock) {
            $mock->shouldReceive('cancelOrder')->once();
        });
        $order = Order::create(['status' => 'pending', 'order_id' => '123', 'entry_price' => 2500, 'tp' => 2600, 'sl' => 2400]);

        // Act
        $response = $this->actingAs($this->user)->delete(route('orders.destroy', $order));

        // Assert
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    /**
     * @test
     */
    public function it_removes_an_expired_order()
    {
        // Arrange
        $order = Order::create(['status' => 'expired', 'entry_price' => 2500, 'tp' => 2600, 'sl' => 2400]);

        // Act
        $response = $this->actingAs($this->user)->delete(route('orders.destroy', $order));

        // Assert
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    /**
     * @test
     */
    public function it_closes_a_filled_order()
    {
        // Arrange
        $this->mock(BybitApiService::class, function ($mock) {
            $mock->shouldReceive('getTickerInfo')->andReturn(['list' => [['lastPrice' => '3000']]]);
            $mock->shouldReceive('getInstrumentsInfo')->andReturn(['list' => [['priceScale' => '2']]]);
            $mock->shouldReceive('createOrder')->once()->andReturn(['orderId' => '54321']);
        });

        $order = Order::create([
            'status' => 'filled',
            'side' => 'buy',
            'amount' => 0.01,
            'symbol' => 'ETHUSDT',
            'entry_price' => 2500,
            'tp' => 2600,
            'sl' => 2400,
        ]);

        // Act
        $response = $this->actingAs($this->user)->post(route('orders.close', $order), ['price_distance' => 15]);

        // Assert
        $response->assertSessionHas('success');
    }

    /**
     * @test
     */
    public function it_prevents_creating_an_order_in_the_loss_zone_of_a_filled_order()
    {
        // Arrange
        Order::create([
            'status' => 'filled',
            'side' => 'buy',
            'entry_price' => 2500,
            'tp' => 2600,
            'sl' => 2400,
        ]);

        $postData = [
            'entry1' => 2450, // In loss zone (2400-2500)
            'entry2' => 2450,
            'tp' => 2700,
            'sl' => 2300,
            'steps' => 1,
            'expire' => 15,
            'risk_percentage' => 1,
        ];

        // Act
        $response = $this->actingAs($this->user)->post(route('order.store'), $postData);

        // Assert
        $response->assertSessionHasErrors('msg');
        $this->assertDatabaseMissing('orders', ['entry_price' => 2450]);
    }

    /**
     * @test
     */
    public function it_prevents_same_direction_order_in_profit_zone_of_a_filled_order()
    {
        // Arrange
        Order::create([
            'status' => 'filled',
            'side' => 'buy',
            'entry_price' => 2500,
            'tp' => 2600,
            'sl' => 2400,
        ]);

        $postData = [
            'entry1' => 2550, // In profit zone (2500-2600)
            'entry2' => 2550,
            'tp' => 2700,
            'sl' => 2500, // This makes it a BUY order
            'steps' => 1,
            'expire' => 15,
            'risk_percentage' => 1,
        ];

        // Act
        $response = $this->actingAs($this->user)->post(route('order.store'), $postData);

        // Assert
        $response->assertSessionHasErrors('msg');
        $this->assertDatabaseMissing('orders', ['entry_price' => 2550]);
    }

}
