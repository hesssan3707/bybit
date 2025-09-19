<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugApiTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->generateApiToken();

        UserExchange::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'is_default' => true,
            'exchange_name' => 'bybit',
            'status' => 'approved',
            'futures_access' => true,
        ]);
    }

    public function test_debug_api_error()
    {
        // Test the ExchangeFactory directly
        try {
            $service = \App\Services\Exchanges\ExchangeFactory::createForUser($this->user->id);
            dump('ExchangeFactory worked!');
        } catch (\Exception $e) {
            dump('ExchangeFactory Error: ' . $e->getMessage());
        }

        // Test the UserExchange query directly
        $exchanges = \App\Models\UserExchange::where('user_id', $this->user->id)->active()->get();
        dump('Active exchanges count: ' . $exchanges->count());
        
        $defaultExchanges = \App\Models\UserExchange::where('user_id', $this->user->id)->active()->where('is_default', true)->get();
        dump('Default active exchanges count: ' . $defaultExchanges->count());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/futures/orders');

        // Print the actual response for debugging
        if ($response->status() !== 200) {
            dump('Response Status: ' . $response->status());
            dump('Response Content: ' . $response->getContent());
        }

        $response->assertStatus(200);
    }
}