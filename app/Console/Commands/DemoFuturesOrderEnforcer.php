<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DemoFuturesOrderEnforcer extends Command
{
    protected $signature = 'demo:futures:enforce {--user= : Specific user ID to enforce demo orders for}';
    protected $description = 'Enforce order consistency between database and active exchanges for demo accounts only';

    public function handle(): int
    {
        $this->info('Starting demo futures order enforcement...');

        try {
            if ($this->option('user')) {
                $this->enforceForUser($this->option('user'));
            } else {
                $this->enforceForAllUsers();
            }
        } catch (\Throwable $e) {
            $this->error("Demo order enforcement failed: " . $e->getMessage());
            Log::error('Demo futures order enforcement failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info('Successfully finished demo futures order enforcement.');
        return self::SUCCESS;
    }

    private function enforceForAllUsers(): void
    {
        // Get users with futures strict mode enabled and who have demo-active exchanges
        $users = User::where('future_strict_mode', true)
                    ->whereHas('activeExchanges', function($query) {
                        $query->where('is_demo_active', true);
                    })
                    ->get();

        if ($users->isEmpty()) {
            $this->info('No users with future strict mode enabled, demo active, and active exchanges found.');
            return;
        }

        $this->info("Found {$users->count()} users with demo accounts enabled and active exchanges.");

        foreach ($users as $user) {
            try {
                $this->enforceForUser($user->id);
            } catch (\Exception $e) {
                $this->warn("Failed to enforce demo orders for user {$user->id}: " . $e->getMessage());
                Log::warning("Failed to enforce demo orders for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function enforceForUser(int $userId): void
    {
        $this->info("Enforcing demo orders for user {$userId}...");

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

        // Get demo-active exchanges for this user
        $activeExchanges = $user->activeExchanges()->where('is_demo_active', true)->get();
        if ($activeExchanges->isEmpty()) {
            $this->warn("No active exchanges for user {$userId}.");
            return;
        }

        foreach ($activeExchanges as $userExchange) {
            try {
                $this->enforceForUserExchange($userId, $userExchange);
            } catch (\Exception $e) {
                $this->error("Failed to enforce demo orders for user {$userId} on exchange {$userExchange->exchange_name}: " . $e->getMessage());
                Log::error("Demo order enforcement failed", [
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
        $this->info("  Enforcing demo orders for user {$userId} on {$userExchange->exchange_name}...");

        try {
            // Force demo mode for exchange service
            $exchangeService = ExchangeFactory::createForUserExchangeWithCredentialType($userExchange, 'demo');
        } catch (\Exception $e) {
            $this->warn("  Cannot create demo exchange service for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
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

            // 2. Handle local 'pending' demo orders only
            $this->info("    Checking local pending demo orders for user {$userId} on {$userExchange->exchange_name}...");
            $ourPendingOrders = Order::where('user_exchange_id', $userExchange->id)
                ->where('status', 'pending')
                ->where('is_demo', true) // Only demo orders
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
                        $this->info("    Canceled and removed modified demo order: {$dbOrder->order_id} (Price/Qty mismatch)");
                        continue;
                    } catch (\Throwable $e) {
                        $this->warn("    Failed to cancel modified demo order {$dbOrder->order_id}: " . $e->getMessage());
                    }
                }

                // Check for expiration (skip if expire_minutes is null)
                if ($dbOrder->expire_minutes !== null) {
                    $expireAt = $dbOrder->created_at->timestamp + ($dbOrder->expire_minutes * 60);
                    if ($now >= $expireAt) {
                        try {
                            $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                            $dbOrder->status = 'expired';
                            $dbOrder->closed_at = now();
                            $dbOrder->save();
                            $this->info("    Canceled expired demo order: {$dbOrder->order_id}");
                        } catch (\Throwable $e) {
                            $this->warn("    Failed to cancel expired demo order {$dbOrder->order_id}: " . $e->getMessage());
                        }
                        continue;
                    }
                }

                // Check if order has reached cancel price
                if ($dbOrder->cancel_price) {
                    try {
                        $klines = $exchangeService->getKlines($symbol , 1 , 2);

                        $shouldCancel = ($dbOrder->side === 'buy' && max($klines['list'][0][2],$klines['list'][1][2]) >= $dbOrder->cancel_price) ||
                            ($dbOrder->side === 'sell' && min($klines['list'][0][3],$klines['list'][1][3]) <= $dbOrder->cancel_price);

                        if ($shouldCancel) {
                            $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                            $dbOrder->status = 'canceled';
                            $dbOrder->closed_at = now();
                            $dbOrder->save();
                            $this->info("    Canceled demo order due to price trigger: {$dbOrder->order_id}");
                            continue;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("    Failed to check cancel price for demo order {$dbOrder->order_id}: " . $e->getMessage());
                    }
                }
            }

            // 3. Handle local 'filled' demo orders only
            $this->info("    Checking local filled demo orders for user {$userId} on {$userExchange->exchange_name}...");
            $ourFilledOrders = Order::where('user_exchange_id', $userExchange->id)
                ->where('status', 'filled')
                ->where('is_demo', true) // Only demo orders
                ->get();

            if (!$ourFilledOrders->isEmpty()) {
                $this->info("    Found {$ourFilledOrders->count()} filled demo orders to verify.");
                
                foreach ($ourFilledOrders as $dbOrder) {
                    // Check if this filled order still exists on exchange as open
                    if (in_array($dbOrder->order_id, $exchangeOpenOrderIds)) {
                        $this->warn("    Demo order {$dbOrder->order_id} is marked as filled but still open on exchange. Updating status...");
                        $dbOrder->status = 'pending';
                        $dbOrder->save();
                    }
                }
            }

            // 4. Handle orphaned exchange orders (orders on exchange but not in our database)
            $this->info("    Checking for orphaned demo orders on exchange for user {$userId}...");
            $ourOrderIds = Order::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', true) // Only demo orders
                ->whereIn('status', ['pending', 'filled'])
                ->pluck('order_id')
                ->toArray();

            $orphanedOrderIds = array_diff($exchangeOpenOrderIds, $ourOrderIds);

            if (!empty($orphanedOrderIds)) {
                $this->info("    Found " . count($orphanedOrderIds) . " orphaned demo orders on exchange. Canceling...");
                
                foreach ($orphanedOrderIds as $orphanedOrderId) {
                    try {
                        $exchangeService->cancelOrderWithSymbol($orphanedOrderId, $symbol);
                        $this->info("    Canceled orphaned demo order: {$orphanedOrderId}");
                    } catch (\Throwable $e) {
                        $this->warn("    Failed to cancel orphaned demo order {$orphanedOrderId}: " . $e->getMessage());
                    }
                }
            }

        } catch (\Throwable $e) {
            $this->error("  Failed to enforce demo orders for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            Log::error("Demo order enforcement failed for user exchange", [
                'user_exchange_id' => $userExchange->id,
                'user_id' => $userId,
                'exchange' => $userExchange->exchange_name,
                'error' => $e->getMessage()
            ]);
        }
    }
}