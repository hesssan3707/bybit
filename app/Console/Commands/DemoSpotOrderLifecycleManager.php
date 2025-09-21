<?php

namespace App\Console\Commands;

use App\Models\SpotOrder;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DemoSpotOrderLifecycleManager extends Command
{
    protected $signature = 'demo:spot:lifecycle {--user= : Specific user ID to manage demo spot orders for}';
    protected $description = 'Manage demo spot order lifecycle for all users (not affected by strict mode)';

    public function handle(): int
    {
        $this->info('Starting demo spot order lifecycle management...');

        try {
            if ($this->option('user')) {
                $this->syncForUser($this->option('user'));
            } else {
                $this->syncForAllUsers();
            }
        } catch (\Throwable $e) {
            $this->error("Demo spot lifecycle management failed: " . $e->getMessage());
            Log::error('Demo spot lifecycle management failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info('Successfully finished demo spot lifecycle management.');
        return self::SUCCESS;
    }

    private function syncForAllUsers(): void
    {
        // Process ALL users with active exchanges - demo commands use demo credentials
        $users = User::whereHas('activeExchanges')->get();
        
        if ($users->isEmpty()) {
            $this->info('No users with demo active and active exchanges found.');
            return;
        }

        $this->info("Found {$users->count()} users with demo accounts enabled and active exchanges.");
        
        foreach ($users as $user) {
            try {
                $this->syncForUser($user->id);
            } catch (\Exception $e) {
                $this->warn("Failed to sync demo spot lifecycle for user {$user->id}: " . $e->getMessage());
                Log::warning("Failed to sync demo spot lifecycle for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function syncForUser(int $userId): void
    {
        $this->info("Syncing demo spot lifecycle for user {$userId}...");
        
        $user = User::find($userId);
        if (!$user) {
            $this->warn("User {$userId} not found.");
            return;
        }

        // Check if user exists
        if (!$user) {
            $this->info("Skipping user {$userId}: user not found");
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
                $this->error("Failed to sync demo spot lifecycle for user {$userId} on exchange {$userExchange->exchange_name}: " . $e->getMessage());
                Log::error("Demo spot lifecycle sync failed", [
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
        $this->info("  Syncing demo spot lifecycle for user {$userId} on {$userExchange->exchange_name}...");
        
        try {
            // Force demo mode for exchange service
            $exchangeService = ExchangeFactory::createForUserExchangeWithCredentialType($userExchange, 'demo');
        } catch (\Exception $e) {
            $this->warn("  Cannot create demo exchange service for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            return;
        }

        try {
            // Get pending demo spot orders from database
            $pendingOrders = SpotOrder::where('user_exchange_id', $userExchange->id)
                ->where('status', 'pending')
                ->where('is_demo', true) // Only demo orders
                ->get();

            if ($pendingOrders->isEmpty()) {
                $this->info("    No pending demo spot orders found for user {$userId}.");
                return;
            }

            $this->info("    Found {$pendingOrders->count()} pending demo spot orders to sync.");

            foreach ($pendingOrders as $order) {
                try {
                    $this->syncSpotOrder($exchangeService, $order);
                } catch (\Throwable $e) {
                    $this->warn("    Failed to sync demo spot order {$order->order_id}: " . $e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $this->error("  Failed to sync demo spot lifecycle for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            Log::error("Demo spot lifecycle sync failed for user exchange", [
                'user_exchange_id' => $userExchange->id,
                'user_id' => $userId,
                'exchange' => $userExchange->exchange_name,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function syncSpotOrder(ExchangeApiServiceInterface $exchangeService, SpotOrder $order): void
    {
        try {
            // Check if order still exists on exchange
            $orderResult = $exchangeService->getSpotOrderHistory($order->symbol, $order->order_id);
            $orderHistory = $orderResult['list'] ?? [];
            
            if (empty($orderHistory)) {
                $this->info("    Demo spot order {$order->order_id} not found in exchange history.");
                return;
            }

            $exchangeOrder = $orderHistory[0];
            $orderStatus = $exchangeOrder['orderStatus'] ?? '';
            
            switch ($orderStatus) {
                case 'Filled':
                    if ($order->status !== 'filled') {
                        $this->info("    Demo spot order {$order->order_id} was filled. Updating status...");
                        $order->status = 'filled';
                        $order->filled_at = now();
                        $order->avg_price = (float)($exchangeOrder['avgPrice'] ?? $order->price);
                        $order->executed_qty = (float)($exchangeOrder['executedQty'] ?? $order->quantity);
                        $order->save();
                    }
                    break;
                    
                case 'Cancelled':
                case 'Rejected':
                    if ($order->status !== 'cancelled') {
                        $this->info("    Demo spot order {$order->order_id} was {$orderStatus}. Updating status...");
                        $order->status = 'cancelled';
                        $order->cancelled_at = now();
                        $order->save();
                    }
                    break;
                    
                case 'PartiallyFilled':
                    if ($order->status !== 'partially_filled') {
                        $this->info("    Demo spot order {$order->order_id} is partially filled. Updating status...");
                        $order->status = 'partially_filled';
                        $order->executed_qty = (float)($exchangeOrder['executedQty'] ?? 0);
                        $order->save();
                    }
                    break;
                    
                case 'New':
                case 'PartiallyFilledCanceled':
                    // Order is still active, no action needed
                    break;
                    
                default:
                    $this->warn("    Unknown demo spot order status '{$orderStatus}' for order {$order->order_id}");
                    break;
            }
            
        } catch (\Throwable $e) {
            $this->warn("    Exception syncing demo spot order {$order->order_id}: " . $e->getMessage());
        }
    }
}