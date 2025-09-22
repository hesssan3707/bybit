<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OldFuturesStopLossSync extends Command
{
    protected $signature = 'futures:old-sync-sl {--user= : Specific user ID to sync for}';
    protected $description = 'OLD VERSION - Sync stop loss levels between database and active exchanges for real accounts only';

    public function handle(): int
    {
        $this->info('Starting stop loss synchronization (OLD VERSION)...');

        try {
            if ($this->option('user')) {
                $this->syncForUser($this->option('user'));
            } else {
                $this->syncForAllUsers();
            }
        } catch (\Throwable $e) {
            $this->error("Stop loss sync failed: " . $e->getMessage());
            Log::error('Futures stop loss sync failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info('Successfully finished stop loss synchronization.');
        return self::SUCCESS;
    }

    private function syncForAllUsers(): void
    {
        // Only process users with future_strict_mode enabled and active exchanges
        $users = User::where('future_strict_mode', true)
                    ->whereHas('activeExchanges')
                    ->get();
        
        if ($users->isEmpty()) {
            $this->info('No users with future strict mode enabled and active exchanges found.');
            return;
        }

        $this->info("Found {$users->count()} users with future strict mode enabled and active exchanges.");
        
        foreach ($users as $user) {
            try {
                $this->syncForUser($user->id);
            } catch (\Exception $e) {
                $this->warn("Failed to sync stop loss for user {$user->id}: " . $e->getMessage());
                Log::warning("Failed to sync stop loss for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function syncForUser(int $userId): void
    {
        $this->info("Syncing stop loss for user {$userId}...");
        
        $user = User::find($userId);
        if (!$user) {
            $this->warn("User {$userId} not found.");
            return;
        }

        // Check if user has future strict mode enabled
        if (!$user->future_strict_mode) {
            $this->info("User {$userId} does not have future strict mode enabled. Skipping...");
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
                $this->error("Failed to sync stop loss for user {$userId} on exchange {$userExchange->exchange_name}: " . $e->getMessage());
                Log::error("Stop loss sync failed", [
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
        $this->info("  Syncing stop loss for user {$userId} on {$userExchange->exchange_name}...");
        
        try {
            // Force real mode (not demo) for stop loss sync
            $exchangeService = ExchangeFactory::createForUserExchangeWithCredentialType($userExchange, 'real');
        } catch (\Exception $e) {
            $this->warn("  Cannot create exchange service for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            return;
        }

        // Get user's selected market, default to ETHUSDT if not set
        $user = User::find($userId);
        $symbol = ($user && $user->selected_market) ? $user->selected_market : 'ETHUSDT';

        // Get all filled orders for this user on this exchange
        $filledOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('status', 'filled')
            ->where('symbol', $symbol)
            ->get();

        if ($filledOrders->isEmpty()) {
            $this->info("    No filled {$symbol} orders found for user {$userId} on {$userExchange->exchange_name}");
            return;
        }

        try {
            $positionResult = $exchangeService->getPositions($symbol);
            $positions = $positionResult['list'] ?? [];

            if (empty($positions)) {
                $this->info("    No open positions found for user {$userId} on {$userExchange->exchange_name} for {$symbol}");
                return;
            }

            foreach ($filledOrders as $dbOrder) {
                // Find matching position
                $matchingPosition = null;
                foreach ($positions as $pos) {
                    if (strtolower($pos['side']) === strtolower($dbOrder->side)) {
                        $matchingPosition = $pos;
                        break;
                    }
                }

                if (!$matchingPosition) {
                    Log::warning("Could not find matching position for user {$userId} on {$userExchange->exchange_name}, order ID: {$dbOrder->id}");
                    continue;
                }

                $exchangeSl = (float)($matchingPosition['stopLoss'] ?? 0);
                $databaseSl = (float)$dbOrder->sl;

                // Compare SL with tolerance for floating point precision
                if (abs($exchangeSl - $databaseSl) > 0.001) {
                    $this->warn("    SL mismatch for user {$userId} on {$userExchange->exchange_name}, {$symbol} (Side: {$dbOrder->side}). Exchange: {$exchangeSl}, DB: {$databaseSl}. Resetting using OLD method...");

                    // Try multiple strategies to set the stop loss (OLD VERSION with complex fallback)
                    $success = $this->resetStopLossOldMethod($exchangeService, $matchingPosition, $symbol, $databaseSl, $userId, $userExchange->exchange_name, $dbOrder->id);
                    
                    if ($success) {
                        $this->info("    Successfully reset SL for user {$userId} on {$userExchange->exchange_name}, {$symbol} to {$databaseSl}");
                    } else {
                        $this->error("    All SL reset strategies failed for user {$userId} on {$userExchange->exchange_name}, {$symbol}");
                    }
                } else {
                    $this->info("    SL for user {$userId} on {$userExchange->exchange_name}, {$symbol} (Side: {$dbOrder->side}) is in sync");
                }
            }

        } catch (\Exception $e) {
            $this->error("    Failed to sync SL for user {$userId} on {$userExchange->exchange_name}, symbol {$symbol}: " . $e->getMessage());
            Log::error("Stop loss sync error for user {$userId} on exchange {$userExchange->exchange_name}", [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * OLD METHOD: Reset stop loss with multiple fallback strategies
     * This was the original complex approach that tried various methods
     * 
     * @param ExchangeApiServiceInterface $exchangeService
     * @param array $position
     * @param string $symbol
     * @param float $targetSl
     * @param int $userId
     * @param string $exchangeName
     * @param int $orderId
     * @return bool Success status
     */
    private function resetStopLossOldMethod(
        ExchangeApiServiceInterface $exchangeService, 
        array $position, 
        string $symbol, 
        float $targetSl, 
        int $userId, 
        string $exchangeName, 
        int $orderId
    ): bool {
        
        // Strategy 1: Try to find and cancel existing SL orders, then set new SL
        if ($this->tryCancelExistingSlAndReset($exchangeService, $position, $symbol, $targetSl, $userId, $exchangeName, $orderId)) {
            return true;
        }
        
        // Strategy 2: Cancel existing SL orders and create new conditional order
        if ($this->tryCancelAndCreateConditionalSl($exchangeService, $position, $symbol, $targetSl, $userId, $exchangeName, $orderId)) {
            return true;
        }
        
        // Strategy 3: Direct position modification (fallback)
        if ($this->tryDirectPositionModification($exchangeService, $position, $symbol, $targetSl, $userId, $exchangeName, $orderId)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * OLD Strategy 1: Find and cancel existing stop loss orders, then set new one
     */
    private function tryCancelExistingSlAndReset(
        ExchangeApiServiceInterface $exchangeService,
        array $position, 
        string $symbol, 
        float $targetSl, 
        int $userId, 
        string $exchangeName, 
        int $orderId
    ): bool {
        try {
            $this->info("      OLD Strategy 1: Finding and canceling existing SL orders...");
            
            // Get conditional orders for this symbol
            if (method_exists($exchangeService, 'getConditionalOrders')) {
                $conditionalOrdersResult = $exchangeService->getConditionalOrders($symbol);
                $conditionalOrders = $conditionalOrdersResult['list'] ?? [];
                
                // Cancel existing stop loss orders
                $canceledOrders = 0;
                foreach ($conditionalOrders as $order) {
                    if ($this->isStopLossOrder($order)) {
                        try {
                            $exchangeService->cancelOrderWithSymbol($order['orderId'], $symbol);
                            $canceledOrders++;
                            usleep(200000); // 0.2 seconds
                        } catch (\Exception $e) {
                            $this->warn("        Failed to cancel order {$order['orderId']}: {$e->getMessage()}");
                        }
                    }
                }
                
                $this->info("        Canceled {$canceledOrders} stop loss orders");
                
                if ($canceledOrders > 0) {
                    usleep(500000); // 0.5 seconds
                }
            }
            
            // Set new stop loss using position modification
            $positionIdx = $this->determinePositionIdx($position);
            $existingTp = (float)($position['takeProfit'] ?? 0);
            
            $params = [
                'category' => 'linear',
                'symbol' => $symbol,
                'stopLoss' => (string)$targetSl,
                'tpslMode' => 'Full',
                'positionIdx' => $positionIdx,
            ];

            if ($existingTp > 0) {
                $params['takeProfit'] = (string)$existingTp;
            }

            if (method_exists($exchangeService, 'setStopLossAdvanced')) {
                $exchangeService->setStopLossAdvanced($params);
            } else {
                $exchangeService->setTradingStop($params);
            }
            
            $this->info("      OLD Strategy 1: Success");
            return true;
            
        } catch (\Exception $e) {
            $this->warn("      OLD Strategy 1: Failed - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * OLD Strategy 2: Cancel existing SL orders and create new conditional stop loss order
     */
    private function tryCancelAndCreateConditionalSl(
        ExchangeApiServiceInterface $exchangeService,
        array $position, 
        string $symbol, 
        float $targetSl, 
        int $userId, 
        string $exchangeName, 
        int $orderId
    ): bool {
        try {
            $this->info("      OLD Strategy 2: Creating conditional SL order...");
            
            $positionIdx = $this->determinePositionIdx($position);
            $positionSize = abs((float)($position['size'] ?? 0));
            $positionSide = strtolower($position['side'] ?? 'buy');
            
            if ($positionSize <= 0) {
                throw new \Exception('Cannot determine position size for conditional order');
            }
            
            // Create conditional order parameters
            $orderParams = [
                'category' => 'linear',
                'symbol' => $symbol,
                'side' => $positionSide === 'buy' ? 'Sell' : 'Buy',
                'orderType' => 'Market',
                'qty' => (string)$positionSize,
                'triggerPrice' => (string)$targetSl,
                'triggerBy' => 'LastPrice',
                'triggerDirection' => $positionSide === 'buy' ? 2 : 1,
                'reduceOnly' => true,
                'closeOnTrigger' => true,
                'positionIdx' => $positionIdx,
                'timeInForce' => 'GTC',
                'orderLinkId' => 'sl_old_' . time() . '_' . rand(1000, 9999),
            ];
            
            $orderResult = $exchangeService->createOrder($orderParams);
            
            $this->info("      OLD Strategy 2: Success - Conditional SL order created: {$orderResult['orderId']}");
            return true;
            
        } catch (\Exception $e) {
            $this->warn("      OLD Strategy 2: Failed - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * OLD Strategy 3: Direct position modification
     */
    private function tryDirectPositionModification(
        ExchangeApiServiceInterface $exchangeService,
        array $position, 
        string $symbol, 
        float $targetSl, 
        int $userId, 
        string $exchangeName, 
        int $orderId
    ): bool {
        try {
            $this->info("      OLD Strategy 3: Direct position modification...");
            
            $exchangeService->setStopLoss($symbol, $targetSl, $position['side']);
            
            $this->info("      OLD Strategy 3: Success");
            return true;
            
        } catch (\Exception $e) {
            $this->warn("      OLD Strategy 3: Failed - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if an order is a stop loss order
     */
    private function isStopLossOrder(array $order): bool
    {
        return (isset($order['stopLoss']) && !empty($order['stopLoss']) && $order['stopLoss'] !== '0') ||
               (isset($order['triggerPrice']) && !empty($order['triggerPrice']) && isset($order['reduceOnly']) && $order['reduceOnly'] === true) ||
               (isset($order['stopOrderType']) && in_array($order['stopOrderType'], ['Stop', 'StopLoss', 'sl'])) ||
               (isset($order['orderLinkId']) && str_starts_with($order['orderLinkId'], 'sl_'));
    }
    
    /**
     * Determine the correct positionIdx from position data
     */
    private function determinePositionIdx(array $position): int
    {
        if (isset($position['positionIdx']) && is_numeric($position['positionIdx'])) {
            return (int)$position['positionIdx'];
        }
        
        if (isset($position['side'])) {
            $side = strtolower($position['side']);
            if ($side === 'buy') {
                return 1; 
            } elseif ($side === 'sell') {
                return 2; 
            }
        }
        
        return 0;
    }
}