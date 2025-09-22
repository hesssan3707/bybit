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

class FuturesLifecycleManagerExpired extends Command
{
    protected $signature = 'futures:lifecycle {--user= : Specific user ID to sync for}';
    protected $description = 'Sync futures order lifecycle for real accounts only (users with strict mode enabled)';

    public function handle(): int
    {
        $this->info('Starting futures lifecycle management...');

        try {
            if ($this->option('user')) {
                $this->syncForUser($this->option('user'));
            } else {
                $this->syncForAllUsers();
            }
        } catch (\Throwable $e) {
            $this->error("Lifecycle management failed: " . $e->getMessage());
            Log::error('Futures lifecycle management failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info('Successfully finished futures lifecycle management.');
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
                $this->warn("Failed to sync lifecycle for user {$user->id}: " . $e->getMessage());
                Log::warning("Failed to sync lifecycle for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function syncForUser(int $userId): void
    {
        $this->info("Syncing lifecycle for user {$userId}...");
        
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

        // Check if user has a selected market in strict mode
        if (empty($user->selected_market)) {
            $this->info("User {$userId} is in strict mode but has no selected market. Skipping...");
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
                $this->error("Failed to sync lifecycle for user {$userId} on exchange {$userExchange->exchange_name}: " . $e->getMessage());
                Log::error("Lifecycle sync failed", [
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
        $this->info("  Syncing lifecycle for user {$userId} on {$userExchange->exchange_name}...");
        
        try {
            // Force real mode (not demo) for exchange service
            $exchangeService = ExchangeFactory::createForUserExchangeWithCredentialType($userExchange, 'real');
        } catch (\Exception $e) {
            $this->warn("  Cannot create exchange service for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            return;
        }

        $this->syncOrderStatuses($userId, $userExchange, $exchangeService);
        $this->syncPnlRecords($userId, $userExchange, $exchangeService);
    }

    private function syncOrderStatuses(int $userId, $userExchange, ExchangeApiServiceInterface $exchangeService): void
    {
        // Get user's selected market, default to ETHUSDT if not set
        $user = User::find($userId);
        $symbol = ($user && $user->selected_market) ? $user->selected_market : 'ETHUSDT';
        
        $ordersToCheck = Order::where('user_exchange_id', $userExchange->id)
            ->whereIn('status', ['pending', 'filled'])
            ->where('symbol', $symbol)
            ->get();

        $this->info("    Found " . $ordersToCheck->count() . " orders to check for user {$userId} on {$userExchange->exchange_name}");

        foreach ($ordersToCheck as $dbOrder) {
            try {
                $symbol = $dbOrder->symbol;

                // Logic for 'pending' orders: Check if they have been filled or externally canceled
                if ($dbOrder->status === 'pending') {
                    // Check order history for final states (Filled, Cancelled)
                    $orderResult = $exchangeService->getOrderHistory($symbol, 50);
                    $orders = $orderResult['list'] ?? [];
                    
                    // Find our specific order
                    $order = collect($orders)->firstWhere('orderId', $dbOrder->order_id);

                    if (!$order) {
                        // Order is still active, skip
                        continue;
                    }

                    $exchangeStatus = $order['orderStatus'];

                    if ($exchangeStatus === 'Filled') {
                        // Create take profit order
                        $closeSide = (strtolower($dbOrder->side) === 'buy') ? 'Sell' : 'Buy';
                        $tpPrice = (float)$dbOrder->tp;
                        
                        try {
                            $tpOrderParams = [
                                'category' => 'linear',
                                'symbol' => $symbol,
                                'side' => $closeSide,
                                'orderType' => 'Limit',
                                'qty' => (string)$dbOrder->amount,
                                'price' => (string)$tpPrice,
                                'reduceOnly' => true,
                                'timeInForce' => 'GTC',
                            ];

                            $exchangeService->createOrder($tpOrderParams);
                            $dbOrder->status = 'filled';
                            $dbOrder->save();
                            $this->info("    Order {$dbOrder->order_id} is now 'filled' with TP order created");
                        } catch (\Exception $e) {
                            $this->warn("    Failed to create TP order for {$dbOrder->order_id}: " . $e->getMessage());
                            // Still mark as filled even if TP creation fails
                            $dbOrder->status = 'filled';
                            $dbOrder->save();
                        }
                    } elseif (in_array($exchangeStatus, ['Cancelled', 'Deactivated', 'Rejected'])) {
                        $dbOrder->status = 'canceled';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("    Marked order {$dbOrder->order_id} as canceled");
                    }
                }
                
                // For already filled orders, check if they've been closed via TP/SL
                if ($dbOrder->status === 'filled') {
                    $this->checkForTpSlClosure($dbOrder, $userExchange, $exchangeService);
                }
            } catch (\Throwable $e) {
                $this->error("    Lifecycle check failed for order {$dbOrder->id}: " . $e->getMessage());
            }
        }

        // Mark 'filled' orders as 'closed' if position is no longer open
        $this->checkClosedPositions($userId, $userExchange, $exchangeService);
    }

    private function checkClosedPositions(int $userId, $userExchange, ExchangeApiServiceInterface $exchangeService): void
    {
        $filledOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('status', 'filled')
            ->get();
            
        if ($filledOrders->isEmpty()) {
            return;
        }

        // Get user's selected market, default to ETHUSDT if not set
        $user = User::find($userId);
        $symbol = ($user && $user->selected_market) ? $user->selected_market : 'ETHUSDT';

        try {
            $positionResult = $exchangeService->getPositions($symbol);
            $positions = $positionResult['list'] ?? [];
            $position = collect($positions)->firstWhere('symbol', $symbol);
            
            if (!$position || (float)($position['size'] ?? 0) == 0) {
                // Position is closed, retrieve and store closed position data
                foreach ($filledOrders as $order) {
                    $this->storeClosedPositionData($order, $userExchange, $exchangeService);
                    $order->status = 'closed';
                    $order->closed_at = now();
                    $order->save();
                    $this->info("    Marked order {$order->order_id} as closed (no open position)");
                }
            }
        } catch (\Exception $e) {
            $this->warn("    Failed to check positions for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
        }
    }

    /**
     * Retrieve and store closed position data from exchange for a specific order
     */
    private function storeClosedPositionData(Order $order, $userExchange, ExchangeApiServiceInterface $exchangeService): void
    {
        try {
            // Get recent closed PnL records to find the matching closed position
            $pnlResult = $exchangeService->getClosedPnl($order->symbol, 10);
            $pnlEvents = $pnlResult['list'] ?? [];
            
            // Find matching closed PnL event for this order (by order ID or time proximity)
            foreach ($pnlEvents as $pnlEvent) {
                // Check if we already have this trade recorded
                $existingTrade = Trade::where('user_exchange_id', $userExchange->id)
                    ->where('order_id', $pnlEvent['orderId'])
                    ->first();
                    
                if (!$existingTrade && !empty($pnlEvent['orderId'])) {
                    // Create new trade record for the closed position
                    Trade::create([
                        'user_exchange_id' => $userExchange->id,
                        'is_demo' => $order->is_demo ?? $userExchange->is_demo_active,
                        'symbol' => $pnlEvent['symbol'],
                        'side' => $order->side,
                        'order_type' => $pnlEvent['orderType'],
                        'leverage' => $pnlEvent['leverage'],
                        'qty' => $pnlEvent['qty'],
                        'avg_entry_price' => $pnlEvent['avgEntryPrice'],
                        'avg_exit_price' => $pnlEvent['avgExitPrice'],
                        'pnl' => $pnlEvent['closedPnl'],
                        'order_id' => $pnlEvent['orderId'],
                        'closed_at' => Carbon::createFromTimestampMs($pnlEvent['updatedTime']),
                    ]);
                    
                    $this->info("    Stored closed position data for order {$order->order_id}, P&L: {$pnlEvent['closedPnl']}");
                    break; // Found and stored the relevant closed position
                }
            }
        } catch (\Exception $e) {
            $this->warn("    Failed to retrieve closed position data for order {$order->order_id}: " . $e->getMessage());
            Log::warning("Failed to retrieve closed position data", [
                'order_id' => $order->order_id,
                'user_exchange_id' => $userExchange->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if a filled order has been closed via TP/SL
     */
    private function checkForTpSlClosure(Order $order, $userExchange, ExchangeApiServiceInterface $exchangeService): void
    {
        try {
            // Get position info to check if it's still open
            $positionResult = $exchangeService->getPositions($order->symbol);
            $positions = $positionResult['list'] ?? [];
            
            $hasOpenPosition = false;
            foreach ($positions as $position) {
                if ((float)($position['size'] ?? 0) > 0) {
                    $hasOpenPosition = true;
                    break;
                }
            }
            
            // If no open position, the filled order has been closed
            if (!$hasOpenPosition) {
                // Store the closed position data before updating status
                $this->storeClosedPositionData($order, $userExchange, $exchangeService);
                
                $order->status = 'closed';
                $order->closed_at = now();
                $order->save();
                
                $this->info("    Order {$order->order_id} closed via TP/SL, updated status to closed");
            }
        } catch (\Exception $e) {
            $this->warn("    Failed to check TP/SL closure for order {$order->order_id}: " . $e->getMessage());
        }
    }

    private function syncPnlRecords(int $userId, $userExchange, ExchangeApiServiceInterface $exchangeService): void
    {
        $this->info("    Syncing P&L records for user {$userId} on {$userExchange->exchange_name}...");
        
        // Get user's selected market, default to ETHUSDT if not set
        $user = User::find($userId);
        $symbol = ($user && $user->selected_market) ? $user->selected_market : 'ETHUSDT';

        try {
            // Find the timestamp of the last saved trade for this user on this exchange
            $lastTrade = Trade::where('user_exchange_id', $userExchange->id)
                ->latest('closed_at')
                ->first();
                
            // Add a 1-second buffer to avoid fetching the same last record
            $startTime = $lastTrade ? 
                Carbon::parse($lastTrade->closed_at)->addSecond()->timestamp * 1000 : 
                null;

            // Get closed PnL from exchange
            $pnlResult = $exchangeService->getClosedPnl($symbol, 50, $startTime);
            $pnlEvents = $pnlResult['list'] ?? [];

            if (empty($pnlEvents)) {
                $this->info('    No new P&L events found for user ' . $userId . ' on ' . $userExchange->exchange_name);
                return;
            }

            // Filter out existing records
            $existingPnlOrderIds = Trade::where('user_exchange_id', $userExchange->id)
                ->pluck('order_id')
                ->all();
                
            $newPnlEvents = collect($pnlEvents)
                ->whereNotIn('orderId', $existingPnlOrderIds);

            if ($newPnlEvents->isEmpty()) {
                $this->info('    No new P&L events to save for user ' . $userId . ' on ' . $userExchange->exchange_name);
                return;
            }

            foreach ($newPnlEvents->reverse() as $pnlEvent) { // Process oldest first
                $originalOrder = Order::where('order_id', $pnlEvent['orderId'])
                                      ->where('user_exchange_id', $userExchange->id)
                                      ->first();

                Trade::create([
                    'user_exchange_id' => $userExchange->id,
                    'is_demo' => $originalOrder ? ($originalOrder->is_demo ?? $userExchange->is_demo_active) : $userExchange->is_demo_active,
                    'symbol' => $pnlEvent['symbol'],
                    'side' => $originalOrder ? $originalOrder->side : strtolower($pnlEvent['side']),
                    'order_type' => $pnlEvent['orderType'],
                    'leverage' => $pnlEvent['leverage'],
                    'qty' => $pnlEvent['qty'],
                    'avg_entry_price' => $pnlEvent['avgEntryPrice'],
                    'avg_exit_price' => $pnlEvent['avgExitPrice'],
                    'pnl' => $pnlEvent['closedPnl'],
                    'order_id' => $pnlEvent['orderId'],
                    'closed_at' => Carbon::createFromTimestampMs($pnlEvent['updatedTime']),
                ]);
                $this->info("    Saved new P&L record for user {$userId} on {$userExchange->exchange_name}, order ID: {$pnlEvent['orderId']}");
            }

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Provide more specific error handling for common issues
            if (str_contains($errorMessage, 'Unknown error') && str_contains($errorMessage, 'closed-pnl')) {
                $this->warn("    P&L sync temporarily unavailable for user {$userId} on {$userExchange->exchange_name}: API endpoint returned unknown error (possibly rate limited or maintenance)");
                Log::warning("Closed P&L API returned unknown error", [
                    'user_exchange_id' => $userExchange->id,
                    'user_id' => $userId,
                    'exchange' => $userExchange->exchange_name,
                    'symbol' => $symbol,
                    'error' => $errorMessage
                ]);
            } elseif (str_contains($errorMessage, 'rate limit')) {
                $this->warn("    P&L sync rate limited for user {$userId} on {$userExchange->exchange_name}");
            } elseif (str_contains($errorMessage, 'permission') || str_contains($errorMessage, 'Forbidden')) {
                $this->warn("    P&L sync permission denied for user {$userId} on {$userExchange->exchange_name}: Check API key permissions");
                Log::warning("P&L sync permission issue", [
                    'user_exchange_id' => $userExchange->id,
                    'user_id' => $userId,
                    'exchange' => $userExchange->exchange_name,
                    'error' => $errorMessage
                ]);
            } else {
                $this->warn("    Failed to sync P&L for user {$userId} on {$userExchange->exchange_name}: " . $errorMessage);
                Log::error("P&L sync failed", [
                    'user_exchange_id' => $userExchange->id,
                    'user_id' => $userId,
                    'exchange' => $userExchange->exchange_name,
                    'symbol' => $symbol,
                    'error' => $errorMessage
                ]);
            }
        }
    }
}