<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Trade;

class PnlHistoryApiTest extends TestCase
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

    public function test_can_get_pnl_history()
    {
        Trade::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/pnl-history');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    }
}
