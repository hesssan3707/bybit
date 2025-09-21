<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DemoFuturesSlTpSync extends Command
{
    protected $signature = 'demo:futures:sync-sltp {--user= : Specific user ID to sync demo stop-loss for}';
    protected $description = 'Sync stop-loss and take-profit levels between database and active exchanges for demo accounts only';

    public function handle(): int
    {
        $this->info('Starting demo stop-loss and take-profit synchronization...');

        try {
            if ($this->option('user')) {
                $this->syncForUser($this->option('user'));
            } else {
                $this->syncForAllUsers();
            }
        } catch (\Throwable $e) {
            $this->error("Demo stop loss sync failed: " . $e->getMessage());
            Log::error('Demo futures stop loss sync failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info('Successfully finished demo stop loss synchronization.');
        return self::SUCCESS;
    }

    private function syncForAllUsers(): void
    {
        // Only process users with future_strict_mode enabled and active exchanges
        $users = User::where('future_strict_mode', true)
                    ->whereHas('activeExchanges')
                    ->get();
        
        if ($users->isEmpty()) {
            $this->info('No users with future strict mode enabled, demo active, and active exchanges found.');
            return;
        }

        $this->info("Found {$users->count()} users with demo accounts enabled and active exchanges.");
        
        foreach ($users as $user) {
            try {
                $this->syncForUser($user->id);
            } catch (\Exception $e) {
                $this->warn("Failed to sync demo stop loss for user {$user->id}: " . $e->getMessage());
                Log::warning("Failed to sync demo stop loss for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function syncForUser(int $userId): void
    {
        $this->info("Syncing demo stop loss for user {$userId}...");
        
        $user = User::find($userId);
        if (!$user) {
            $this->warn("User {$userId} not found.");
            return;
        }

        // Only process users with future_strict_mode enabled
        if (!$user->future_strict_mode) {
            $this->info("Skipping user {$user->id}: futures strict mode not enabled");
            return;
        }

        // Get all active exchanges for this user
        $activeExchanges = $user->activeExchanges;
        if ($activeExchanges->isEmpty()) {
            $this->warn("No active exchanges for user {$userId}.");
            return;
        }

        foreach ($activeExchanges as $userExchange) {
            try {
                $this->syncForUserExchange($userId, $userExchange);
            } catch (\Exception $e) {
                $this->error("Failed to sync demo stop loss for user {$userId} on exchange {$userExchange->exchange_name}: " . $e->getMessage());
                Log::error("Demo stop loss sync failed", [
                    'user_exchange_id' => $userExchange->id,
                    'user_id' => $userId,
                    'exchange' => $userExchange->exchange_name,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function syncForUserExchange(int $userId, $userExchange): void
    {
        $this->info("  Syncing demo stop loss for user {$userId} on {$userExchange->exchange_name}...");
        
        try {
            // Force demo mode for exchange service
            $exchangeService = ExchangeFactory::createForUserExchangeWithCredentialType($userExchange, 'demo');
        } catch (\Exception $e) {
            $this->warn("  Cannot create demo exchange service for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            return;
        }

        $user = User::find($userId);
        $symbol = ($user && $user->selected_market) ? $user->selected_market : 'ETHUSDT';

        try {
            // Get filled demo orders that have stop loss but no active stop loss order
            $filledOrdersWithSl = Order::where('user_exchange_id', $userExchange->id)
                ->where('status', 'filled')
                ->where('is_demo', true) // Only demo orders
                ->whereNotNull('sl')
                ->where('sl', '>', 0)
                ->get();

            if ($filledOrdersWithSl->isEmpty()) {
                $this->info("    No filled demo orders with stop loss found for user {$userId}.");
                return;
            }

            $this->info("    Found {$filledOrdersWithSl->count()} filled demo orders with stop loss to sync.");

            // Get current positions from exchange
            $positionsResult = $exchangeService->getPositions($symbol);
            $positions = $positionsResult['list'] ?? [];

            // Get current conditional orders (stop loss orders)
            $conditionalOrdersResult = $exchangeService->getConditionalOrders($symbol);
            $conditionalOrders = $conditionalOrdersResult['list'] ?? [];

            foreach ($filledOrdersWithSl as $order) {
                try {
                    $this->syncStopLossForOrder($exchangeService, $order, $positions, $conditionalOrders, $symbol);
                } catch (\Throwable $e) {
                    $this->warn("    Failed to sync demo stop loss for order {$order->order_id}: " . $e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $this->error("  Failed to sync demo stop loss for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            Log::error("Demo stop loss sync failed for user exchange", [
                'user_exchange_id' => $userExchange->id,
                'user_id' => $userId,
                'exchange' => $userExchange->exchange_name,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function syncStopLossForOrder(ExchangeApiServiceInterface $exchangeService, $order, array $positions, array $conditionalOrders, string $symbol): void
    {
        // Find the position for this order's symbol
        $position = null;
        foreach ($positions as $pos) {
            if ($pos['symbol'] === $symbol) {
                $position = $pos;
                break;
            }
        }

        if (!$position) {
            $this->info("    No position found for demo order {$order->order_id} on symbol {$symbol}.");
            return;
        }

        $positionSize = (float)($position['size'] ?? 0);
        if ($positionSize == 0) {
            $this->info("    Position size is 0 for demo order {$order->order_id}. No stop loss needed.");
            return;
        }

        $positionSide = $position['side'] ?? '';
        $positionIdx = (int)($position['positionIdx'] ?? 0);

        // Check if there's already a stop loss order for this position
        $hasStopLoss = false;
        foreach ($conditionalOrders as $condOrder) {
            if ($condOrder['symbol'] === $symbol && 
                isset($condOrder['triggerPrice']) && 
                $condOrder['triggerPrice'] > 0) {
                $hasStopLoss = true;
                break;
            }
        }

        if ($hasStopLoss) {
            $this->info("    Demo stop loss already exists for order {$order->order_id}.");
            return;
        }

        // Create stop loss order
        $targetSl = (float)$order->sl;
        
        try {
            $orderParams = [
                'category' => 'linear',
                'symbol' => $symbol,
                'side' => $positionSide === 'Buy' ? 'Sell' : 'Buy', // Opposite side
                'orderType' => 'Market',
                'qty' => (string)abs($positionSize),
                'triggerPrice' => (string)$targetSl,
                'triggerBy' => 'LastPrice',
                'triggerDirection' => $positionSide === 'Buy' ? 2 : 1, // 2: falls, 1: rises
                'reduceOnly' => true,
                'closeOnTrigger' => true,
                'positionIdx' => $positionIdx,
                'timeInForce' => 'GTC',
                'orderLinkId' => 'demo_sl_' . time() . '_' . rand(1000, 9999),
            ];

            $response = $exchangeService->createOrder($orderParams);
            
            if (isset($response['retCode']) && $response['retCode'] == 0) {
                $this->info("    Created demo stop loss order for {$order->order_id} at price {$targetSl}");
            } else {
                $this->warn("    Failed to create demo stop loss for order {$order->order_id}: " . json_encode($response));
            }
            
        } catch (\Throwable $e) {
            $this->warn("    Exception creating demo stop loss for order {$order->order_id}: " . $e->getMessage());
        }
    }
}