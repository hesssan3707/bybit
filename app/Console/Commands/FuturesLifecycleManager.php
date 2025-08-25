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

class FuturesLifecycleManager extends Command
{
    protected $signature = 'futures:lifecycle {--user= : Specific user ID to sync for}';
    protected $description = 'Sync local order statuses and PnL records with active exchanges for all users';

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
        $users = User::whereHas('activeExchanges')->get();
        
        if ($users->isEmpty()) {
            $this->info('No users with active exchanges found.');
            return;
        }

        $this->info("Found {$users->count()} users with active exchanges.");
        
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
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->api_key,
                $userExchange->api_secret
            );
        } catch (\Exception $e) {
            $this->warn("  Cannot create exchange service for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            return;
        }

        $this->syncOrderStatuses($userId, $userExchange, $exchangeService);
        $this->syncPnlRecords($userId, $userExchange, $exchangeService);
    }

    private function syncOrderStatuses(int $userId, $userExchange, ExchangeApiServiceInterface $exchangeService): void
    {
        $ordersToCheck = Order::where('user_exchange_id', $userExchange->id)
            ->whereIn('status', ['pending', 'filled'])
            ->where('symbol', 'ETHUSDT') // Make this configurable if needed
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

        try {
            $positionResult = $exchangeService->getPositions('ETHUSDT');
            $positions = $positionResult['list'] ?? [];
            $position = collect($positions)->firstWhere('symbol', 'ETHUSDT');
            
            if (!$position || (float)($position['size'] ?? 0) == 0) {
                foreach ($filledOrders as $order) {
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

    private function syncPnlRecords(int $userId, $userExchange, ExchangeApiServiceInterface $exchangeService): void
    {
        $this->info("    Syncing P&L records for user {$userId} on {$userExchange->exchange_name}...");
        $symbol = 'ETHUSDT'; // Make this configurable if needed

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
                Trade::create([
                    'user_exchange_id' => $userExchange->id,
                    'symbol' => $pnlEvent['symbol'],
                    'side' => $pnlEvent['side'],
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
            $this->warn("    Failed to sync P&L for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
        }
    }
}