<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FuturesSlTpSync extends Command
{
    protected $signature = 'futures:sync-sltp {--user= : Specific user ID to sync for}';
    protected $description = 'Sync stop-loss and take-profit levels between database and active exchanges';

    public function handle(): int
    {
        $this->info('Starting stop-loss and take-profit synchronization...');

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

            foreach ($positions as $position) {
                // Ignore positions that are not open
                if ((float)($position['positionAmt'] ?? $position['size'] ?? 0) == 0) {
                    continue;
                }

                // Find matching DB order
                $matchingOrder = null;
                foreach ($filledOrders as $order) {
                    $positionSide = strtolower($position['side'] ?? ($position['positionSide'] ?? ''));
                    $orderSide = strtolower($order->side);

                    if ($positionSide === $orderSide) {
                        $matchingOrder = $order;
                        break;
                    }
                }

                if (!$matchingOrder) {
                    Log::warning("Could not find matching DB order for position", [
                        'user_id' => $userId,
                        'exchange' => $userExchange->exchange_name,
                        'symbol' => $symbol,
                        'position' => $position
                    ]);
                    continue;
                }

                $exchangeSl = (float)($position['stopLoss'] ?? 0);
                $databaseSl = (float)$matchingOrder->sl;
                $exchangeTp = (float)($position['takeProfit'] ?? 0);
                $databaseTp = (float)$matchingOrder->tp;

                // Compare SL or TP with tolerance for floating point precision
                if (abs($exchangeSl - $databaseSl) > 0.001 || abs($exchangeTp - $databaseTp) > 0.001) {
                    $this->warn("    SL/TP mismatch for user {$userId} on {$userExchange->exchange_name}, {$symbol} (Side: {$matchingOrder->side}). Exchange SL:{$exchangeSl}/TP:{$exchangeTp}, DB SL:{$databaseSl}/TP:{$databaseTp}. Updating...");

                    $success = $this->updateStopLossUsingTradingStop($exchangeService, $position, $symbol, $databaseSl, $databaseTp, $userId, $userExchange->exchange_name, $matchingOrder->id);
                    
                    if ($success) {
                        $this->info("    Successfully updated SL/TP for user {$userId} on {$userExchange->exchange_name}, {$symbol} to SL:{$databaseSl}/TP:{$databaseTp}");
                        
                        // Additionally ensure TP is set as reduce-only order if needed
                        $this->ensureTpAsReduceOrder($exchangeService, $position, $symbol, $databaseTp, $matchingOrder, $userId, $userExchange->exchange_name);
                    } else {
                        $this->error("    Failed to update SL/TP for user {$userId} on {$userExchange->exchange_name}, {$symbol}");
                    }
                } else {
                    $this->info("    SL/TP for user {$userId} on {$userExchange->exchange_name}, {$symbol} (Side: {$matchingOrder->side}) is in sync");
                    
                    // Even if SL/TP are in sync, ensure TP is properly set as reduce-only order
                    $this->ensureTpAsReduceOrder($exchangeService, $position, $symbol, $databaseTp, $matchingOrder, $userId, $userExchange->exchange_name);
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
     * Update stop loss using the correct Bybit API endpoint: POST /v5/position/trading-stop
     * This directly modifies the stopLoss on an open position without creating/canceling orders
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
    private function updateStopLossUsingTradingStop(
        ExchangeApiServiceInterface $exchangeService,
        array $position,
        string $symbol,
        float $targetSl,
        float $targetTp,
        int $userId,
        string $exchangeName,
        int $orderId
    ): bool {
        try {
            $this->info("      Using POST /v5/position/trading-stop to update SL/TP...");

            // Determine position index
            $positionIdx = $this->determinePositionIdx($position);

            // Prepare parameters for the trading-stop endpoint
            $params = [
                'category' => 'linear', // for futures trading
                'symbol' => $symbol,
                'positionIdx' => $positionIdx,
                'stopLoss' => (string)$targetSl,
                'tpslMode' => 'Full', // Full position stop loss/take profit
            ];

            // Set take profit from database value if it's greater than zero
            if ($targetTp > 0) {
                $params['takeProfit'] = (string)$targetTp;
            }

            $this->info("        Updating position SL/TP to {$targetSl}/{$targetTp} for position index {$positionIdx}");

            // Log the API call parameters
            Log::info("Updating SL/TP using trading-stop endpoint", [
                'user_id' => $userId,
                'exchange' => $exchangeName,
                'symbol' => $symbol,
                'positionIdx' => $positionIdx,
                'new_stop_loss' => $targetSl,
                'new_take_profit' => $targetTp,
                'params' => $params
            ]);

            // Call the setStopLossAdvanced method which uses the trading-stop endpoint
            $result = $exchangeService->setStopLossAdvanced($params);

            $this->info("      Success: SL/TP updated using trading-stop endpoint");

            Log::info("SL/TP successfully updated", [
                'user_id' => $userId,
                'exchange' => $exchangeName,
                'symbol' => $symbol,
                'new_stop_loss' => $targetSl,
                'new_take_profit' => $targetTp,
                'api_response' => $result
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("      Failed to update SL/TP using trading-stop endpoint: " . $e->getMessage());

            Log::error("SL/TP update failed using trading-stop endpoint", [
                'user_id' => $userId,
                'exchange' => $exchangeName,
                'symbol' => $symbol,
                'target_sl' => $targetSl,
                'target_tp' => $targetTp,
                'position_idx' => $positionIdx ?? 'unknown',
                'error' => $e->getMessage(),
                'params' => $params ?? []
            ]);

            return false;
        }
    }

    /**
     * Ensure TP is set as a reduce-only order
     * TP must always be of type reduce and function as closing the main order
     * 
     * @param ExchangeApiServiceInterface $exchangeService
     * @param array $position
     * @param string $symbol
     * @param float $targetTp
     * @param object $matchingOrder
     * @param int $userId
     * @param string $exchangeName
     */
    private function ensureTpAsReduceOrder(
        ExchangeApiServiceInterface $exchangeService,
        array $position,
        string $symbol,
        float $targetTp,
        object $matchingOrder,
        int $userId,
        string $exchangeName
    ): void {
        try {
            if ($targetTp <= 0) {
                return; // No TP to set
            }

            // Get current open orders to check for existing TP orders
            $openOrdersResult = $exchangeService->getOpenOrders($symbol);
            $openOrders = $openOrdersResult['list'] ?? [];
            
            $positionSize = abs((float)($position['size'] ?? 0));
            $positionSide = strtolower($position['side'] ?? '');
            $tpSide = ($positionSide === 'buy') ? 'Sell' : 'Buy'; // Opposite side for TP
            
            // Check if there's already a valid TP order
            $hasValidTpOrder = false;
            foreach ($openOrders as $order) {
                $orderPrice = (float)($order['price'] ?? $order['triggerPrice'] ?? 0);
                $orderSide = strtolower($order['side'] ?? '');
                $isReduceOnly = ($order['reduceOnly'] ?? false) === true;
                
                // Check if this is our TP order
                if ($isReduceOnly && 
                    $orderSide === strtolower($tpSide) && 
                    abs($orderPrice - $targetTp) < 0.01) {
                    $hasValidTpOrder = true;
                    $this->info("      Valid TP reduce-only order already exists at {$targetTp}");
                    break;
                }
            }
            
            if (!$hasValidTpOrder) {
                // Create TP as reduce-only order
                $positionIdx = $this->determinePositionIdx($position);
                
                $tpOrderParams = [
                    'category' => 'linear',
                    'symbol' => $symbol,
                    'side' => $tpSide,
                    'orderType' => 'Limit',
                    'qty' => (string)$positionSize,
                    'price' => (string)$targetTp,
                    'reduceOnly' => true,
                    'positionIdx' => $positionIdx,
                    'timeInForce' => 'GTC'
                ];
                
                $this->info("      Creating TP reduce-only order at {$targetTp} for {$positionSize} {$symbol}");
                
                $result = $exchangeService->createOrder($tpOrderParams);
                
                if (isset($result['orderId'])) {
                    $this->info("      Successfully created TP reduce-only order: {$result['orderId']}");
                } else {
                    $this->warn("      TP order creation response: " . json_encode($result));
                }
            }
            
        } catch (\Exception $e) {
            $this->warn("      Failed to ensure TP as reduce-only order: " . $e->getMessage());
            Log::warning("TP reduce-only order creation failed", [
                'user_id' => $userId,
                'exchange' => $exchangeName,
                'symbol' => $symbol,
                'target_tp' => $targetTp,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine the correct positionIdx from position data
     * This is critical for the trading-stop endpoint to work correctly
     * 
     * @param array $position
     * @return int
     */
    private function determinePositionIdx(array $position): int
    {
        // First try to get positionIdx directly from the position data
        if (isset($position['positionIdx']) && is_numeric($position['positionIdx'])) {
            return (int)$position['positionIdx'];
        }
        
        // Fallback: Try to determine from side in hedge mode
        if (isset($position['side'])) {
            $side = strtolower($position['side']);
            // In hedge mode:
            // positionIdx = 1 for Buy side (long positions)
            // positionIdx = 2 for Sell side (short positions)
            if ($side === 'buy') {
                return 1; 
            } elseif ($side === 'sell') {
                return 2; 
            }
        }
        
        // Default to one-way mode
        // positionIdx = 0 for one-way mode (both buy and sell in same position)
        return 0;
    }
}