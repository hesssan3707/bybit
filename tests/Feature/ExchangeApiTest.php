<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;

class ExchangeApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_can_get_exchanges()
    {
        UserExchange::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/exchanges');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_exchange()
    {
        $exchangeData = [
            'exchange_name' => 'bybit',
            'api_key' => 'test_key',
            'api_secret' => 'test_secret',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/exchanges', $exchangeData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_exchanges', ['api_key' => 'test_key']);
    }

    public function test_can_update_exchange()
    {
        $exchange = UserExchange::factory()->create(['user_id' => $this->user->id]);

        $updateData = [
            'api_key' => 'updated_key',
            'api_secret' => 'updated_secret',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/exchanges/{$exchange->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_exchanges', ['api_key' => 'updated_key']);
    }

    public function test_can_delete_exchange()
    {
        $exchange = UserExchange::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('user_exchanges', ['id' => $exchange->id]);
    }

    public function test_can_switch_exchange()
    {
        $exchange1 = UserExchange::factory()->create(['user_id' => $this->user->id, 'is_active' => true]);
        $exchange2 = UserExchange::factory()->create(['user_id' => $this->user->id, 'is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/exchanges/{$exchange2->id}/switch");

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_exchanges', ['id' => $exchange2->id, 'is_active' => true]);
        $this->assertDatabaseHas('user_exchanges', ['id' => $exchange1->id, 'is_active' => false]);
    }

    public function test_can_test_exchange_connection()
    {
        $exchange = UserExchange::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/exchanges/{$exchange->id}/test");

        $response->assertStatus(200);
    }
}
