<?php

namespace Tests\Feature;

use App\Models\BybitOrders;
use App\Services\BybitApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BybitControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_prevents_creating_an_order_if_a_loss_occurred_within_the_last_hour()
    {
        // Arrange
        BybitOrders::create([
            'status' => 'closed',
            'pnl' => -10,
            'closed_at' => now()->subMinutes(30),
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
            'access_password' => env('FORM_ACCESS_PASSWORD'),
        ];

        // Act
        $response = $this->post(route('order.store'), $postData);

        // Assert
        $response->assertSessionHasErrors('msg');
        $this->assertDatabaseMissing('bybit_orders', ['entry_price' => 3000]);
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

        BybitOrders::create([
            'status' => 'closed',
            'pnl' => -10,
            'closed_at' => now()->subMinutes(70),
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
            'access_password' => env('FORM_ACCESS_PASSWORD'),
        ];

        // Act
        $response = $this->post(route('order.store'), $postData);

        // Assert
        $response->assertSessionDoesntHaveErrors('msg');
    }

    /**
     * @test
     */
    public function it_prevents_creating_an_order_if_another_order_is_already_active()
    {
        // Arrange
        BybitOrders::create([
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
            'access_password' => env('FORM_ACCESS_PASSWORD'),
        ];

        // Act
        $response = $this->post(route('order.store'), $postData);

        // Assert
        $response->assertSessionHasErrors('msg');
        $this->assertDatabaseCount('bybit_orders', 1); // Ensure no new order was created
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
        $order = BybitOrders::create(['status' => 'pending', 'order_id' => '123', 'entry_price' => 2500, 'tp' => 2600, 'sl' => 2400]);

        // Act
        $response = $this->delete(route('orders.destroy', $order));

        // Assert
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('bybit_orders', ['id' => $order->id]);
    }

    /**
     * @test
     */
    public function it_removes_an_expired_order()
    {
        // Arrange
        $order = BybitOrders::create(['status' => 'expired', 'entry_price' => 2500, 'tp' => 2600, 'sl' => 2400]);

        // Act
        $response = $this->delete(route('orders.destroy', $order));

        // Assert
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('bybit_orders', ['id' => $order->id]);
    }

}
