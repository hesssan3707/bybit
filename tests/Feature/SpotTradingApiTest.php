<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\SpotOrder;

class SpotTradingApiTest extends TestCase
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

    public function test_can_get_spot_orders()
    {
        SpotOrder::factory()->count(3)->create(['user_exchange_id' => $this->user->exchanges->first()->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/spot/orders');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_spot_order()
    {
        $orderData = [
            'symbol' => 'BTCUSDT',
            'side' => 'Buy',
            'orderType' => 'Limit',
            'qty' => 0.001,
            'price' => 30000,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/spot/orders', $orderData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('spot_orders', ['symbol' => 'BTCUSDT']);
    }

    public function test_can_get_single_spot_order()
    {
        $order = SpotOrder::factory()->create(['user_exchange_id' => $this->user->exchanges->first()->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/spot/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_can_delete_spot_order()
    {
        $order = SpotOrder::factory()->create([
            'user_exchange_id' => $this->user->exchanges->first()->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/spot/orders/{$order->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('spot_orders', ['id' => $order->id, 'status' => 'Cancelled']);
    }
}
