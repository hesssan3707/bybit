<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;
use App\Models\Trade;
use App\Models\UserBan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class BanDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a user and exchange for testing
        $this->user = User::factory()->create();
        $this->userExchange = UserExchange::factory()->create([
            'user_id' => $this->user->id,
            'exchange_name' => 'bybit',
            // 'is_demo' => false // Removed as column doesn't exist
        ]);
    }

    /**
     * Helper to simulate the ban detection logic from FuturesLifecycleManager
     */
    private function checkBanCondition($trade, $order)
    {
        $userId = $this->user->id;
        $isDemo = false;
        $banCreated = false;

        // Heuristic: exchange force close detected if exit far from both TP and SL (>0.2%)
        if ($order && $trade->avg_exit_price) {
            $exit = (float)$trade->avg_exit_price;
            
            // Logic from FuturesLifecycleManager
            $tpDelta = isset($order->tp) ? abs(((float)$order->tp - $exit) / $exit) : null;
            $slDelta = isset($order->sl) ? abs(((float)$order->sl - $exit) / $exit) : null;
            
            // Debug output
            echo "\n--- Debug Info ---\n";
            echo "Exit Price: $exit\n";
            echo "TP: " . ($order->tp ?? 'NULL') . "\n";
            echo "SL: " . ($order->sl ?? 'NULL') . "\n";
            echo "TP Delta: " . ($tpDelta ?? 'NULL') . "\n";
            echo "SL Delta: " . ($slDelta ?? 'NULL') . "\n";
            echo "Closed By User: " . ($trade->closed_by_user ?? 0) . "\n";
            
            if ($trade->closed_at !== null
                && ((int)($trade->closed_by_user ?? 0) !== 1)
                && $tpDelta !== null && $slDelta !== null
                && $tpDelta > 0.002 && $slDelta > 0.002) {
                
                $banCreated = true;
            }
        }

        return $banCreated;
    }

    /** @test */
    public function it_should_ban_when_exit_is_far_from_tp_and_sl()
    {
        // Scenario: Order with TP/SL, closed far from both (e.g. manual exchange close)
        // TP: 50000, SL: 40000, Exit: 45000
        // TP Delta: |50000 - 45000| / 45000 = 5000 / 45000 = 0.11 (11%) > 0.2%
        // SL Delta: |40000 - 45000| / 45000 = 5000 / 45000 = 0.11 (11%) > 0.2%
        
        $order = Order::factory()->create([
            'user_exchange_id' => $this->userExchange->id,
            'symbol' => 'BTCUSDT',
            'tp' => 50000,
            'sl' => 40000,
            'average_price' => 45000,
        ]);

        $trade = Trade::factory()->create([
            'user_exchange_id' => $this->userExchange->id,
            'order_id' => $order->order_id,
            'avg_entry_price' => 45000,
            'avg_exit_price' => 45000, 
            'closed_at' => now(),
            'closed_by_user' => 0, 
        ]);

        $shouldBan = $this->checkBanCondition($trade, $order);
        $this->assertTrue($shouldBan, "Ban should be created when exit is far from TP and SL");
    }

    /** @test */
    public function it_should_NOT_ban_when_exit_is_close_to_tp()
    {
        // Scenario: Hit TP (or close to it)
        // TP: 50000, Exit: 49950 (0.1% difference)
        // Delta: |50000 - 49950| / 49950 = 50 / 49950 = 0.001 (0.1%) < 0.2%
        
        $order = Order::factory()->create([
            'user_exchange_id' => $this->userExchange->id,
            'symbol' => 'BTCUSDT',
            'tp' => 50000,
            'sl' => 40000,
            'average_price' => 45000,
        ]);

        $trade = Trade::factory()->create([
            'user_exchange_id' => $this->userExchange->id,
            'order_id' => $order->order_id,
            'avg_entry_price' => 45000,
            'avg_exit_price' => 49950, 
            'closed_at' => now(),
            'closed_by_user' => 0,
        ]);

        $shouldBan = $this->checkBanCondition($trade, $order);
        $this->assertFalse($shouldBan, "Should NOT ban when exit is close to TP");
    }

    /** @test */
    public function it_should_NOT_ban_when_exit_is_close_to_sl()
    {
        // Scenario: Hit SL (or close to it)
        // SL: 40000, Exit: 40050 (0.125% difference)
        // Delta: |40000 - 40050| / 40050 = 50 / 40050 = 0.0012 < 0.2%
        
        $order = Order::factory()->create([
            'user_exchange_id' => $this->userExchange->id,
            'symbol' => 'BTCUSDT',
            'tp' => 50000,
            'sl' => 40000,
            'average_price' => 45000,
        ]);

        $trade = Trade::factory()->create([
            'user_exchange_id' => $this->userExchange->id,
            'order_id' => $order->order_id,
            'avg_entry_price' => 45000,
            'avg_exit_price' => 40050, 
            'closed_at' => now(),
            'closed_by_user' => 0,
        ]);

        $shouldBan = $this->checkBanCondition($trade, $order);
        $this->assertFalse($shouldBan, "Should NOT ban when exit is close to SL");
    }

    /** @test */
    public function it_fails_to_ban_if_closed_by_user_flag_is_set()
    {
        // Scenario: Closed by user in app
        $order = Order::factory()->create([
            'user_exchange_id' => $this->userExchange->id,
            'symbol' => 'BTCUSDT',
            'tp' => 50000,
            'sl' => 40000,
            'average_price' => 45000,
        ]);

        $trade = Trade::factory()->create([
            'user_exchange_id' => $this->userExchange->id,
            'order_id' => $order->order_id,
            'avg_entry_price' => 45000,
            'avg_exit_price' => 45000,
            'closed_at' => now(),
            'closed_by_user' => 1, // Closed by user in app
        ]);

        $shouldBan = $this->checkBanCondition($trade, $order);
        $this->assertFalse($shouldBan, "Should not ban if closed_by_user is true");
    }
}
