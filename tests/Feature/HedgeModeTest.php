<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;
use Illuminate\Support\Facades\Log;

class HedgeModeTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->generateApiToken();
    }

    /**
     * Test that exchange switching endpoints exist and respond correctly
     */
    public function test_api_exchange_switch_endpoint_exists()
    {
        $exchange1 = UserExchange::factory()->withExchangeName('binance')->create([
            'user_id' => $this->user->id, 
            'is_active' => true, 
            'status' => 'approved', 
            'is_default' => true
        ]);
        
        $exchange2 = UserExchange::factory()->withExchangeName('bybit')->create([
            'user_id' => $this->user->id, 
            'is_active' => false, 
            'status' => 'approved'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/exchanges/{$exchange2->id}/switch");

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_exchanges', ['id' => $exchange2->id, 'is_active' => true]);
    }

    /**
     * Test web exchange switch endpoint exists and responds correctly
     */
    public function test_web_exchange_switch_endpoint_exists()
    {
        $this->actingAs($this->user);
        
        $exchange1 = UserExchange::factory()->withExchangeName('binance')->create([
            'user_id' => $this->user->id, 
            'is_active' => true, 
            'status' => 'approved', 
            'is_default' => true
        ]);
        
        $exchange2 = UserExchange::factory()->withExchangeName('bybit')->create([
            'user_id' => $this->user->id, 
            'is_active' => true, 
            'status' => 'approved'
        ]);

        $response = $this->post("/exchanges/{$exchange2->id}/switch");

        $response->assertRedirect();
        $this->assertDatabaseHas('user_exchanges', ['id' => $exchange2->id, 'is_default' => true]);
    }

    /**
     * Test User model exchange switch method works
     */
    public function test_user_model_exchange_switch_works()
    {
        $exchange1 = UserExchange::factory()->withExchangeName('binance')->create([
            'user_id' => $this->user->id, 
            'is_active' => true, 
            'status' => 'approved', 
            'is_default' => true
        ]);
        
        $exchange2 = UserExchange::factory()->withExchangeName('bybit')->create([
            'user_id' => $this->user->id, 
            'is_active' => true, 
            'status' => 'approved'
        ]);

        // This should work even if hedge mode fails (due to no real API credentials)
        $result = $this->user->switchToExchange('bybit');

        $this->assertTrue($result);
        $this->assertDatabaseHas('user_exchanges', ['id' => $exchange2->id, 'is_default' => true]);
    }

    /**
     * Test exchange deactivation makes another exchange default
     */
    public function test_exchange_deactivation_makes_another_default()
    {
        $exchange1 = UserExchange::factory()->withExchangeName('binance')->create([
            'user_id' => $this->user->id, 
            'is_active' => true, 
            'status' => 'approved', 
            'is_default' => true
        ]);
        
        $exchange2 = UserExchange::factory()->withExchangeName('bybit')->create([
            'user_id' => $this->user->id, 
            'is_active' => true, 
            'status' => 'approved'
        ]);

        // Deactivate the default exchange
        $exchange1->deactivate(1, 'Test deactivation');

        $this->assertDatabaseHas('user_exchanges', [
            'id' => $exchange1->id, 
            'is_active' => false, 
            'is_default' => false,
            'status' => 'deactivated'
        ]);
        
        $this->assertDatabaseHas('user_exchanges', [
            'id' => $exchange2->id, 
            'is_default' => true
        ]);
    }

    /**
     * Test that all three exchanges can be created
     */
    public function test_all_three_exchanges_can_be_created()
    {
        $exchanges = ['bybit', 'binance', 'bingx'];
        
        foreach ($exchanges as $exchangeName) {
            $exchange = UserExchange::factory()->withExchangeName($exchangeName)->create([
                'user_id' => $this->user->id, 
                'is_active' => true, 
                'status' => 'approved'
            ]);

            $this->assertDatabaseHas('user_exchanges', [
                'id' => $exchange->id,
                'exchange_name' => $exchangeName,
                'user_id' => $this->user->id
            ]);
        }
    }

    /**
     * Test that makeDefault method works correctly
     */
    public function test_make_default_method_works()
    {
        $exchange1 = UserExchange::factory()->withExchangeName('binance')->create([
            'user_id' => $this->user->id, 
            'is_active' => true, 
            'status' => 'approved', 
            'is_default' => true
        ]);
        
        $exchange2 = UserExchange::factory()->withExchangeName('bybit')->create([
            'user_id' => $this->user->id, 
            'is_active' => true, 
            'status' => 'approved'
        ]);

        $result = $exchange2->makeDefault();

        $this->assertTrue($result);
        $this->assertDatabaseHas('user_exchanges', ['id' => $exchange1->id, 'is_default' => false]);
        $this->assertDatabaseHas('user_exchanges', ['id' => $exchange2->id, 'is_default' => true]);
    }

    /**
     * Test that hedge mode switching code exists in controllers
     */
    public function test_hedge_mode_code_exists_in_controllers()
    {
        // Check that the hedge mode switching code exists in the controllers
        $apiControllerPath = app_path('Http/Controllers/Api/V1/ExchangeController.php');
        $webControllerPath = app_path('Http/Controllers/ExchangeController.php');
        $userModelPath = app_path('Models/User.php');
        $userExchangeModelPath = app_path('Models/UserExchange.php');

        $this->assertFileExists($apiControllerPath);
        $this->assertFileExists($webControllerPath);
        $this->assertFileExists($userModelPath);
        $this->assertFileExists($userExchangeModelPath);

        // Check that hedge mode switching code exists
        $apiControllerContent = file_get_contents($apiControllerPath);
        $webControllerContent = file_get_contents($webControllerPath);
        $userModelContent = file_get_contents($userModelPath);
        $userExchangeContent = file_get_contents($userExchangeModelPath);

        $this->assertStringContainsString('switchPositionMode(true)', $apiControllerContent);
        $this->assertStringContainsString('switchPositionMode(true)', $webControllerContent);
        $this->assertStringContainsString('switchPositionMode(true)', $userModelContent);
        $this->assertStringContainsString('switchPositionMode(true)', $userExchangeContent);

        $this->assertStringContainsString('Hedge mode activated', $apiControllerContent);
        $this->assertStringContainsString('Hedge mode activated', $webControllerContent);
        $this->assertStringContainsString('Hedge mode activated', $userModelContent);
        $this->assertStringContainsString('Hedge mode activated', $userExchangeContent);
    }
}