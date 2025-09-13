<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;

class MarketApiTest extends TestCase
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

    public function test_can_get_best_price()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/best-price', [
            'markets' => ['BTCUSDT'],
            'type' => 'spot',
            'side' => 'buy',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'market',
                        'best_price',
                        'exchange',
                    ]
                ]
            ]);
    }
}
