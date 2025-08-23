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
        $users = User::whereHas('activeExchanges')->get();
        
        if ($users->isEmpty()) {
            $this->info('No users with active exchanges found.');
            return;
        }

        $this->info("Found {$users->count()} users with active exchanges.");
        
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
        
        try {
            $exchangeService = ExchangeFactory::createForUser($userId);
        } catch (\Exception $e) {
            $this->warn("No active exchange for user {$userId}: " . $e->getMessage());
            return;
        }

        $symbol = 'ETHUSDT'; // You might want to make this configurable

        try {
            // 1. Get all open orders from the exchange
            $openOrdersResult = $exchangeService->getOpenOrders($symbol);
            $exchangeOpenOrders = $openOrdersResult['list'] ?? [];
            $exchangeOpenOrderIds = array_map(fn($o) => $o['orderId'], $exchangeOpenOrders);

            // Create a map for efficient lookups
            $exchangeOpenOrdersMap = array_combine($exchangeOpenOrderIds, $exchangeOpenOrders);

            // 2. Handle local 'pending' orders (check for modifications, expiration)
            $this->info("  Checking local pending orders for user {$userId}...");
            $ourPendingOrders = Order::where('user_id', $userId)
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
                        $exchangeService->cancelOrder($dbOrder->order_id, $symbol);
                        $dbOrder->delete();
                        $this->info("  Canceled and removed modified order: {$dbOrder->order_id} (Price/Qty mismatch)");
                        continue;
                    } catch (\Throwable $e) {
                        $this->warn("  Failed to cancel modified order {$dbOrder->order_id}: " . $e->getMessage());
                    }
                }

                // Check for expiration
                $expireAt = $dbOrder->created_at->timestamp + ($dbOrder->expire_minutes * 60);
                if ($now >= $expireAt) {
                    try {
                        $exchangeService->cancelOrder($dbOrder->order_id, $symbol);
                        $dbOrder->status = 'expired';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("  Canceled expired order: {$dbOrder->order_id}");
                    } catch (\Throwable $e) {
                        $this->warn("  Failed to cancel expired order {$dbOrder->order_id}: " . $e->getMessage());
                    }
                }
            }

            // 3. Handle foreign orders (not in our DB for this user)
            $this->info("  Checking for foreign orders to cancel for user {$userId}...");
            $ourTrackedIds = Order::where('user_id', $userId)
                ->whereIn('status', ['pending', 'filled'])
                ->pluck('order_id')
                ->filter()
                ->all();

            $foreignOrderIds = array_diff($exchangeOpenOrderIds, $ourTrackedIds);

            if (empty($foreignOrderIds)) {
                $this->info("  No foreign orders found for user {$userId}");
            } else {
                $this->info("  Found " . count($foreignOrderIds) . " foreign orders for user {$userId}");
                foreach ($foreignOrderIds as $orderId) {
                    try {
                        $orderToCancel = $exchangeOpenOrdersMap[$orderId] ?? null;
                        if ($orderToCancel && ($orderToCancel['reduceOnly'] ?? false) === true) {
                            $this->info("  Skipping reduce-only foreign order: {$orderId}");
                            continue;
                        }
                        $exchangeService->cancelOrder($orderId, $symbol);
                        $this->info("  Canceled foreign order: {$orderId}");
                    } catch (\Throwable $e) {
                        $this->warn("  Failed to cancel foreign order {$orderId}: " . $e->getMessage());
                    }
                }
            }

        } catch (\Throwable $e) {
            $this->error("  Order enforcement failed for user {$userId}: " . $e->getMessage());
            throw $e;
        }
    }
}