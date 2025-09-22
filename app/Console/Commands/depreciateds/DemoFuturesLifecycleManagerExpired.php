<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DemoFuturesLifecycleManagerExpired extends Command
{
    protected $signature = 'demo:futures:lifecycle {--user= : Specific user ID to sync demo orders for}';
    protected $description = 'Sync local demo order statuses and PnL records with active exchanges for demo accounts only';

    public function handle(): int
    {
        $this->info('Starting demo futures lifecycle management...');

        try {
            if ($this->option('user')) {
                $this->syncForUser($this->option('user'));
            } else {
                $this->syncForAllUsers();
            }
        } catch (\Throwable $e) {
            $this->error("Demo lifecycle management failed: " . $e->getMessage());
            Log::error('Demo futures lifecycle management failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info('Successfully finished demo futures lifecycle management.');
        return self::SUCCESS;
    }

    private function syncForAllUsers(): void
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
                $this->syncForUser($user->id);
            } catch (\Exception $e) {
                $this->warn("Failed to sync demo lifecycle for user {$user->id}: " . $e->getMessage());
                Log::warning("Failed to sync demo lifecycle for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function syncForUser(int $userId): void
    {
        $this->info("Syncing demo lifecycle for user {$userId}...");
        
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

        // Check if user has a selected market in strict mode
        if (empty($user->selected_market)) {
            $this->info("User {$userId} is in strict mode but has no selected market. Skipping...");
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
                $this->syncForUserExchange($userId, $userExchange);
            } catch (\Exception $e) {
                $this->error("Failed to sync demo lifecycle for user {$userId} on exchange {$userExchange->exchange_name}: " . $e->getMessage());
                Log::error("Demo lifecycle sync failed", [
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
        $this->info("  Syncing demo lifecycle for user {$userId} on {$userExchange->exchange_name}...");
        
        try {
            // Force demo mode for exchange service
            $exchangeService = ExchangeFactory::createForUserExchange($userExchange, true);
        } catch (\Exception $e) {
            $this->warn("  Cannot create demo exchange service for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            return;
        }

        $user = User::find($userId);
        $symbol = $user->selected_market;

        try {
            // 1. Sync pending demo orders
            $this->syncPendingOrders($exchangeService, $userExchange, $symbol);
            
            // 2. Sync filled demo orders and create trades
            $this->syncFilledOrders($exchangeService, $userExchange, $symbol);
            
        } catch (\Throwable $e) {
            $this->error("  Failed to sync demo lifecycle for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            Log::error("Demo lifecycle sync failed for user exchange", [
                'user_exchange_id' => $userExchange->id,
                'user_id' => $userId,
                'exchange' => $userExchange->exchange_name,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function syncPendingOrders(ExchangeApiServiceInterface $exchangeService, $userExchange, string $symbol): void
    {
        // Get pending demo orders from database
        $pendingOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('status', 'pending')
            ->where('is_demo', true) // Only demo orders
            ->get();

        if ($pendingOrders->isEmpty()) {
            return;
        }

        $this->info("    Found {$pendingOrders->count()} pending demo orders to sync.");

        // Get open orders from exchange
        $openOrdersResult = $exchangeService->getOpenOrders($symbol);
        $exchangeOpenOrders = $openOrdersResult['list'] ?? [];
        $exchangeOpenOrderIds = array_map(fn($o) => $o['orderId'], $exchangeOpenOrders);

        foreach ($pendingOrders as $order) {
            // If order is not in exchange's open orders, it might be filled or canceled
            if (!in_array($order->order_id, $exchangeOpenOrderIds)) {
                try {
                    // Check order history to see what happened
                    $orderHistoryResult = $exchangeService->getHistoryOrder($order->order_id);
                    $orderHistory = isset($orderHistoryResult['list']) ? $orderHistoryResult['list'] : [$orderHistoryResult];
                    
                    if (!empty($orderHistory)) {
                        $exchangeOrder = $orderHistory[0];
                        $orderStatus = $exchangeOrder['orderStatus'] ?? '';
                        
                        if ($orderStatus === 'Filled') {
                            $this->info("    Demo order {$order->order_id} was filled. Updating status...");
                            $order->status = 'filled';
                            $order->filled_at = now();
                            $order->avg_price = (float)($exchangeOrder['avgPrice'] ?? $order->entry_price);
                            $order->save();
                        } elseif (in_array($orderStatus, ['Cancelled', 'Rejected'])) {
                            $this->info("    Demo order {$order->order_id} was {$orderStatus}. Updating status...");
                            $order->status = strtolower($orderStatus);
                            $order->closed_at = now();
                            $order->save();
                        }
                    }
                } catch (\Throwable $e) {
                    $this->warn("    Failed to check demo order history for {$order->order_id}: " . $e->getMessage());
                }
            }
        }
    }

    private function syncFilledOrders(ExchangeApiServiceInterface $exchangeService, $userExchange, string $symbol): void
    {
        // Get recently filled demo orders that don't have trades yet
        $filledOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('status', 'filled')
            ->where('is_demo', true) // Only demo orders
            ->whereDoesntHave('trade')
            ->where('filled_at', '>=', Carbon::now()->subDays(7)) // Only recent orders
            ->get();

        if ($filledOrders->isEmpty()) {
            return;
        }

        $this->info("    Found {$filledOrders->count()} filled demo orders without trades to process.");

        foreach ($filledOrders as $order) {
            try {
                // Get order details from exchange to confirm fill
                $orderHistoryResult = $exchangeService->getHistoryOrder($order->order_id);
                $orderHistory = isset($orderHistoryResult['list']) ? $orderHistoryResult['list'] : [$orderHistoryResult];
                
                if (!empty($orderHistory)) {
                    $exchangeOrder = $orderHistory[0];
                    
                    if (($exchangeOrder['orderStatus'] ?? '') === 'Filled') {
                        // Create trade record for demo order
                        $avgPrice = (float)($exchangeOrder['avgPrice'] ?? $order->avg_price ?? $order->entry_price);
                        $qty = (float)($exchangeOrder['qty'] ?? $order->amount);
                        
                        Trade::create([
                            'user_exchange_id' => $userExchange->id,
                            'is_demo' => true, // Mark as demo trade
                            'symbol' => $symbol,
                            'side' => $order->side,
                            'order_type' => $order->order_type,
                            'leverage' => $order->leverage,
                            'qty' => $qty,
                            'avg_entry_price' => $avgPrice,
                            'avg_exit_price' => null, // Will be set when position is closed
                            'pnl' => 0, // Will be calculated when position is closed
                            'order_id' => $order->order_id,
                            'closed_at' => null, // Will be set when position is closed
                        ]);
                        
                        $this->info("    Created demo trade record for order {$order->order_id}");
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("    Failed to create demo trade for order {$order->order_id}: " . $e->getMessage());
            }
        }
    }
}