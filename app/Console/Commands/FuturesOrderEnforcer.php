<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FuturesOrderEnforcer extends Command
{
    protected $signature = 'futures:enforce {--user= : Specific user ID to enforce orders for}';
    protected $description = 'Enforce order consistency between database and active exchanges for all users';

    public function handle(): int
    {
        $this->info('Starting futures order enforcement...');
        
        try {
            if ($this->option('user')) {
                $this->enforceForUser($this->option('user'));
            } else {
                $this->enforceForAllUsers();
            }
        } catch (\Throwable $e) {
            $this->error("Order enforcement failed: " . $e->getMessage());
            Log::error('Futures order enforcement failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info('Successfully finished futures order enforcement.');
        return self::SUCCESS;
    }

    private function enforceForAllUsers(): void
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
                $this->enforceForUser($user->id);
            } catch (\Exception $e) {
                $this->warn("Failed to enforce orders for user {$user->id}: " . $e->getMessage());
                Log::warning("Failed to enforce orders for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function enforceForUser(int $userId): void
    {
        $this->info("Enforcing orders for user {$userId}...");
        
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
                $this->enforceForUserExchange($userId, $userExchange);
            } catch (\Exception $e) {
                $this->error("Failed to enforce orders for user {$userId} on exchange {$userExchange->exchange_name}: " . $e->getMessage());
                Log::error("Order enforcement failed", [
                    'user_exchange_id' => $userExchange->id,
                    'user_id' => $userId,
                    'exchange' => $userExchange->exchange_name,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function enforceForUserExchange(int $userId, $userExchange): void
    {
        $this->info("  Enforcing orders for user {$userId} on {$userExchange->exchange_name}...");
        
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

        try {
            // 1. Get all open orders from the exchange
            $openOrdersResult = $exchangeService->getOpenOrders($symbol);
            $exchangeOpenOrders = $openOrdersResult['list'] ?? [];
            $exchangeOpenOrderIds = array_map(fn($o) => $o['orderId'], $exchangeOpenOrders);

            // Create a map for efficient lookups
            $exchangeOpenOrdersMap = array_combine($exchangeOpenOrderIds, $exchangeOpenOrders);

            // 2. Handle local 'pending' orders (check for modifications, expiration)
            $this->info("    Checking local pending orders for user {$userId} on {$userExchange->exchange_name}...");
            $ourPendingOrders = Order::where('user_exchange_id', $userExchange->id)
                ->where('status', 'pending')
                ->get();
            
            $now = time();

            foreach ($ourPendingOrders as $dbOrder) {
                $exchangeOrder = $exchangeOpenOrdersMap[$dbOrder->order_id] ?? null;

                // If our 'pending' order is not on exchange's open list, it might have been filled or canceled
                if (!$exchangeOrder) {
                    continue; // Let lifecycle command handle this
                }

                // Check for external modifications (price or quantity change)
                $exchangePrice = (float)($exchangeOrder['price'] ?? 0);
                $dbPrice = (float)$dbOrder->entry_price;
                $exchangeQty = (float)($exchangeOrder['qty'] ?? 0);
                $dbQty = (float)$dbOrder->amount;

                if (abs($exchangePrice - $dbPrice) > 0.0001 || abs($exchangeQty - $dbQty) > 0.000001) {
                    try {
                        $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                        $dbOrder->delete();
                        $this->info("    Canceled and removed modified order: {$dbOrder->order_id} (Price/Qty mismatch)");
                        continue;
                    } catch (\Throwable $e) {
                        $this->warn("    Failed to cancel modified order {$dbOrder->order_id}: " . $e->getMessage());
                    }
                }

                // Check for expiration
                $expireAt = $dbOrder->created_at->timestamp + ($dbOrder->expire_minutes * 60);
                if ($now >= $expireAt) {
                    try {
                        $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                        $dbOrder->status = 'expired';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("    Canceled expired order: {$dbOrder->order_id}");
                    } catch (\Throwable $e) {
                        $this->warn("    Failed to cancel expired order {$dbOrder->order_id}: " . $e->getMessage());
                    }
                    continue;
                }
                
                // Check if order has reached cancel price
                if ($dbOrder->cancel_price) {
                    try {
                        $ticker = $exchangeService->getTicker($symbol);
                        $currentPrice = (float)$ticker['lastPrice'];
                        
                        $shouldCancel = ($dbOrder->side === 'buy' && $currentPrice >= $dbOrder->cancel_price) || 
                                        ($dbOrder->side === 'sell' && $currentPrice <= $dbOrder->cancel_price);
                        
                        if ($shouldCancel) {
                            $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                            $dbOrder->status = 'canceled';
                            $dbOrder->closed_at = now();
                            $dbOrder->save();
                            $this->info("    Canceled order due to price trigger: {$dbOrder->order_id}");
                            continue;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("    Failed to check cancel price for order {$dbOrder->order_id}: " . $e->getMessage());
                    }
                }
            }

            // 3. Handle foreign orders (not in our DB for this user)
            // Skip SL/TP orders as they are legitimate system orders that should remain active
            $this->info("    Checking for foreign orders to cancel for user {$userId} on {$userExchange->exchange_name}...");
            $ourTrackedIds = Order::where('user_exchange_id', $userExchange->id)
                ->whereIn('status', ['pending', 'filled'])
                ->pluck('order_id')
                ->filter()
                ->all();

            $foreignOrderIds = array_diff($exchangeOpenOrderIds, $ourTrackedIds);

            if (empty($foreignOrderIds)) {
                $this->info("    No foreign orders found for user {$userId} on {$userExchange->exchange_name}");
            } else {
                $this->info("    Found " . count($foreignOrderIds) . " foreign orders for user {$userId} on {$userExchange->exchange_name}");
                foreach ($foreignOrderIds as $orderId) {
                    try {
                        $orderToCancel = $exchangeOpenOrdersMap[$orderId] ?? null;
                        
                        // Skip SL/TP orders - these are legitimate system orders
                        if ($orderToCancel) {
                            $isReduceOnly = ($orderToCancel['reduceOnly'] ?? false) === true;
                            $isStopLoss = !empty($orderToCancel['stopLoss']) || $orderToCancel['orderType'] === 'Market' && $isReduceOnly;
                            $isTakeProfit = !empty($orderToCancel['takeProfit']) || (isset($orderToCancel['triggerPrice']) && $isReduceOnly);
                            
                            if ($isReduceOnly || $isStopLoss || $isTakeProfit) {
                                $this->info("  Skipping SL/TP order: {$orderId} (reduceOnly: {$isReduceOnly})");
                                continue;
                            }
                        }
                        
                        $exchangeService->cancelOrderWithSymbol($orderId, $symbol);
                        $this->info("  Canceled foreign order: {$orderId}");
                    } catch (\Throwable $e) {
                        $this->warn("  Failed to cancel foreign order {$orderId}: " . $e->getMessage());
                    }
                }
            }

        } catch (\Throwable $e) {
            $this->error("    Order enforcement failed for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            throw $e;
        }
    }
}