<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;

class FuturesApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        UserExchange::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    public function test_can_get_futures_orders()
    {
        Order::factory()->count(3)->create(['user_exchange_id' => $this->user->exchanges->first()->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/futures/orders');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    }

    public function test_can_create_futures_order()
    {
        $orderData = [
            'symbol' => 'BTCUSDT',
            'entry1' => 50000,
            'entry2' => 50000,
            'tp'     => 52000,
            'sl'     => 48000,
            'steps'  => 1,
            'expire' => 60,
            'risk_percentage' => 1,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/futures/orders', $orderData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', ['symbol' => 'BTCUSDT']);
    }

    public function test_can_close_futures_order()
    {
        $order = Order::factory()->create([
            'user_exchange_id' => $this->user->exchanges->first()->id,
            'status' => 'filled',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/futures/orders/{$order->id}/close", ['price_distance' => 0]);

        $response->assertStatus(200);
    }

    public function test_can_delete_futures_order()
    {
        $order = Order::factory()->create([
            'user_exchange_id' => $this->user->exchanges->first()->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/futures/orders/{$order->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }
}
