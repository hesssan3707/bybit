<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;

class WalletBalanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->generateApiToken();

        UserExchange::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'status' => 'approved',
            'is_default' => true,
        ]);
    }

    public function test_can_get_wallet_balance()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'spot' => [
                        'balances',
                        'total_equity',
                        'error',
                    ],
                    'futures' => [
                        'balances',
                        'total_equity',
                        'error',
                    ],
                ]
            ]);
    }
}
