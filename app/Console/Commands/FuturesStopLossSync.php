<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FuturesStopLossSync extends Command
{
    protected $signature = 'futures:sync-sl {--user= : Specific user ID to sync for}';
    protected $description = 'Sync stop loss levels between database and active exchanges for all users';

    public function handle(): int
    {
        $this->info('Starting stop loss synchronization...');

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
        // Only process users with future_strict_mode enabled
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
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->api_key,
                $userExchange->api_secret
            );
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
                    $this->warn("    SL mismatch for user {$userId} on {$userExchange->exchange_name}, {$symbol} (Side: {$dbOrder->side}). Exchange: {$exchangeSl}, DB: {$databaseSl}. Resetting...");

                    // Try multiple strategies to set the stop loss
                    $success = $this->resetStopLoss($exchangeService, $matchingPosition, $symbol, $databaseSl, $userId, $userExchange->exchange_name, $dbOrder->id);
                    
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
     * Reset stop loss with multiple fallback strategies
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
    private function resetStopLoss(
        ExchangeApiServiceInterface $exchangeService, 
        array $position, 
        string $symbol, 
        float $targetSl, 
        int $userId, 
        string $exchangeName, 
        int $orderId
    ): bool {
        
        // Strategy 1: Direct modification with proper parameters
        if ($this->tryDirectSlModification($exchangeService, $position, $symbol, $targetSl, $userId, $exchangeName, $orderId)) {
            return true;
        }
        
        // Strategy 2: Remove existing SL and set new one
        if ($this->tryRemoveAndSetSl($exchangeService, $position, $symbol, $targetSl, $userId, $exchangeName, $orderId)) {
            return true;
        }
        
        // Strategy 3: Try with different tpslMode (Partial if Full failed, or vice versa)
        if ($this->tryAlternativeTpslMode($exchangeService, $position, $symbol, $targetSl, $userId, $exchangeName, $orderId)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Strategy 1: Direct modification with proper parameters
     */
    private function tryDirectSlModification(
        ExchangeApiServiceInterface $exchangeService,
        array $position, 
        string $symbol, 
        float $targetSl, 
        int $userId, 
        string $exchangeName, 
        int $orderId
    ): bool {
        try {
            $this->info("      Strategy 1: Attempting direct SL modification...");
            
            // Ensure positionIdx is properly set
            $positionIdx = $this->determinePositionIdx($position);
            
            $params = [
                'category' => 'linear',
                'symbol' => $symbol,
                'stopLoss' => (string)$targetSl,
                'tpslMode' => 'Full',
                'positionIdx' => $positionIdx,
            ];

            // Preserve existing take profit if it exists
            $existingTp = (float)($position['takeProfit'] ?? 0);
            if ($existingTp > 0) {
                $params['takeProfit'] = (string)$existingTp;
            }

            // Preserve trigger settings if they exist
            if (isset($position['tpTriggerBy']) && !empty($position['tpTriggerBy']) && $position['tpTriggerBy'] !== '') {
                $params['tpTriggerBy'] = $position['tpTriggerBy'];
            }
            if (isset($position['slTriggerBy']) && !empty($position['slTriggerBy']) && $position['slTriggerBy'] !== '') {
                $params['slTriggerBy'] = $position['slTriggerBy'];
            }

            $exchangeService->setStopLossAdvanced($params);
            $this->info("      Strategy 1: Success - SL modified directly");
            return true;
            
        } catch (\Exception $e) {
            $this->warn("      Strategy 1: Failed - " . $e->getMessage());
            Log::warning("Direct SL modification failed for user {$userId} on {$exchangeName}", [
                'symbol' => $symbol,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'params' => $params ?? null
            ]);
            return false;
        }
    }
    
    /**
     * Strategy 2: Remove existing SL and set new one
     */
    private function tryRemoveAndSetSl(
        ExchangeApiServiceInterface $exchangeService,
        array $position, 
        string $symbol, 
        float $targetSl, 
        int $userId, 
        string $exchangeName, 
        int $orderId
    ): bool {
        try {
            $this->info("      Strategy 2: Attempting remove and re-set SL...");
            
            $positionIdx = $this->determinePositionIdx($position);
            
            // Step 1: Remove existing stop loss by setting it to 0
            $removeParams = [
                'category' => 'linear',
                'symbol' => $symbol,
                'stopLoss' => '0', // 0 means cancel SL according to Bybit docs
                'tpslMode' => 'Full',
                'positionIdx' => $positionIdx,
            ];
            
            // Preserve existing TP during SL removal
            $existingTp = (float)($position['takeProfit'] ?? 0);
            if ($existingTp > 0) {
                $removeParams['takeProfit'] = (string)$existingTp;
            }
            
            $this->info("        Step 2a: Removing existing SL...");
            $exchangeService->setStopLossAdvanced($removeParams);
            
            // Small delay to ensure the removal is processed
            usleep(500000); // 0.5 seconds
            
            // Step 2: Set new stop loss
            $setParams = [
                'category' => 'linear',
                'symbol' => $symbol,
                'stopLoss' => (string)$targetSl,
                'tpslMode' => 'Full',
                'positionIdx' => $positionIdx,
            ];
            
            // Restore TP if it existed
            if ($existingTp > 0) {
                $setParams['takeProfit'] = (string)$existingTp;
            }
            
            $this->info("        Step 2b: Setting new SL...");
            $exchangeService->setStopLossAdvanced($setParams);
            
            $this->info("      Strategy 2: Success - SL removed and re-set");
            return true;
            
        } catch (\Exception $e) {
            $this->warn("      Strategy 2: Failed - " . $e->getMessage());
            Log::warning("Remove and re-set SL failed for user {$userId} on {$exchangeName}", [
                'symbol' => $symbol,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'remove_params' => $removeParams ?? null,
                'set_params' => $setParams ?? null
            ]);
            return false;
        }
    }
    
    /**
     * Strategy 3: Try with alternative tpslMode
     */
    private function tryAlternativeTpslMode(
        ExchangeApiServiceInterface $exchangeService,
        array $position, 
        string $symbol, 
        float $targetSl, 
        int $userId, 
        string $exchangeName, 
        int $orderId
    ): bool {
        try {
            $this->info("      Strategy 3: Attempting with Partial tpslMode...");
            
            $positionIdx = $this->determinePositionIdx($position);
            $positionSize = abs((float)($position['size'] ?? 0));
            
            if ($positionSize <= 0) {
                $this->warn("      Strategy 3: Failed - Cannot determine position size");
                return false;
            }
            
            $params = [
                'category' => 'linear',
                'symbol' => $symbol,
                'stopLoss' => (string)$targetSl,
                'tpslMode' => 'Partial',
                'positionIdx' => $positionIdx,
                'slSize' => (string)$positionSize, // For partial mode, specify the size
                'slOrderType' => 'Market', // Ensure we use Market orders for SL
            ];
            
            // For partial mode with TP, both tpSize and slSize must be equal
            $existingTp = (float)($position['takeProfit'] ?? 0);
            if ($existingTp > 0) {
                $params['takeProfit'] = (string)$existingTp;
                $params['tpSize'] = (string)$positionSize;
                $params['tpOrderType'] = 'Market';
            }

            $exchangeService->setStopLossAdvanced($params);
            $this->info("      Strategy 3: Success - SL set with Partial mode");
            return true;
            
        } catch (\Exception $e) {
            $this->warn("      Strategy 3: Failed - " . $e->getMessage());
            Log::warning("Alternative tpslMode SL setting failed for user {$userId} on {$exchangeName}", [
                'symbol' => $symbol,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'params' => $params ?? null
            ]);
            return false;
        }
    }
    
    /**
     * Determine the correct positionIdx from position data
     */
    private function determinePositionIdx(array $position): int
    {
        // First try to get positionIdx from the position data
        if (isset($position['positionIdx']) && is_numeric($position['positionIdx'])) {
            return (int)$position['positionIdx'];
        }
        
        // Fallback: Try to determine from side in hedge mode
        if (isset($position['side'])) {
            $side = strtolower($position['side']);
            if ($side === 'buy') {
                return 1; // Hedge mode Buy side
            } elseif ($side === 'sell') {
                return 2; // Hedge mode Sell side
            }
        }
        
        // Default to one-way mode
        return 0;
    }
}