<?php

namespace App\Console\Commands;

use App\Models\SpotOrder;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SpotOrderLifecycleManagerExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:lifecycle {--user= : Specific user ID to manage spot orders for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage spot order lifecycle for all users (not affected by strict mode)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting spot order lifecycle management...');
        
        try {
            if ($this->option('user')) {
                $this->manageForUser($this->option('user'));
            } else {
                $this->manageForAllUsers();
            }
        } catch (\Throwable $e) {
            $this->error("Spot order lifecycle management failed: " . $e->getMessage());
            Log::error('Spot order lifecycle management failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info('Successfully finished spot order lifecycle management.');
        return self::SUCCESS;
    }

    private function manageForAllUsers(): void
    {
        // Process ALL users with active exchanges - strict mode does NOT affect spot orders
        $users = User::whereHas('activeExchanges')->get();
        
        if ($users->isEmpty()) {
            $this->info('No users with active exchanges found.');
            return;
        }

        $this->info("Found {$users->count()} users with active exchanges.");
        
        foreach ($users as $user) {
            try {
                $this->manageForUser($user->id);
            } catch (\Exception $e) {
                $this->warn("Failed to manage spot orders for user {$user->id}: " . $e->getMessage());
                Log::warning("Failed to manage spot orders for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function manageForUser(int $userId): void
    {
        $this->info("Managing spot orders for user {$userId}...");
        
        $user = User::find($userId);
        if (!$user) {
            $this->warn("User {$userId} not found.");
            return;
        }

        // Note: Spot order management is NOT affected by future_strict_mode
        $this->info("Processing spot orders for user {$userId} (strict mode does not affect spot orders)");

        $activeExchanges = $user->activeExchanges;
        if ($activeExchanges->isEmpty()) {
            $this->warn("No active exchanges for user {$userId}.");
            return;
        }

        foreach ($activeExchanges as $userExchange) {
            try {
                $this->manageForUserExchange($userId, $userExchange);
            } catch (\Exception $e) {
                $this->error("Failed to manage spot orders for user {$userId} on exchange {$userExchange->exchange_name}: " . $e->getMessage());
                Log::error("Spot order lifecycle management failed", [
                    'user_exchange_id' => $userExchange->id,
                    'user_id' => $userId,
                    'exchange' => $userExchange->exchange_name,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function manageForUserExchange(int $userId, $userExchange): void
    {
        $this->info("  Managing spot orders for user {$userId} on {$userExchange->exchange_name}...");
        
        try {
            $exchangeService = ExchangeFactory::createForUserExchangeWithCredentialType($userExchange, 'real');
        } catch (\Exception $e) {
            $this->warn("  Cannot create exchange service for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            return;
        }

        try {
            // 1. Sync pending spot orders
            $this->syncPendingSpotOrders($userId, $userExchange, $exchangeService);
            
            // 2. Update order statuses
            $this->updateSpotOrderStatuses($userId, $userExchange, $exchangeService);
            
        } catch (\Throwable $e) {
            $this->error("    Spot order lifecycle management failed for user {$userId} on {$userExchange->exchange_name}: " . $e->getMessage());
            throw $e;
        }
    }

    private function syncPendingSpotOrders(int $userId, $userExchange, ExchangeApiServiceInterface $exchangeService): void
    {
        $this->info("    Syncing pending spot orders for user {$userId} on {$userExchange->exchange_name}...");
        
        $pendingOrders = SpotOrder::where('user_exchange_id', $userExchange->id)
            ->where('status', 'pending')
            ->get();

        if ($pendingOrders->isEmpty()) {
            $this->info("    No pending spot orders found for user {$userId} on {$userExchange->exchange_name}");
            return;
        }

        $this->info("    Found {$pendingOrders->count()} pending spot orders for user {$userId} on {$userExchange->exchange_name}");

        foreach ($pendingOrders as $dbOrder) {
            try {
                // Get order status from exchange
                $exchangeOrderStatus = $exchangeService->getSpotOrderStatus($dbOrder->order_id, $dbOrder->symbol);
                
                if ($exchangeOrderStatus) {
                    $status = strtolower($exchangeOrderStatus['orderStatus'] ?? 'unknown');
                    
                    switch ($status) {
                        case 'filled':
                        case 'partiallyfilled':
                            $dbOrder->update([
                                'status' => 'filled',
                                'executed_qty' => $exchangeOrderStatus['cumExecQty'] ?? $dbOrder->executed_qty,
                                'executed_price' => $exchangeOrderStatus['avgPrice'] ?? $dbOrder->executed_price,
                                'order_updated_at' => now(),
                                'raw_response' => $exchangeOrderStatus,
                            ]);
                            $this->info("      Updated spot order {$dbOrder->order_id} status to filled");
                            break;
                            
                        case 'cancelled':
                        case 'rejected':
                            $dbOrder->update([
                                'status' => $status,
                                'reject_reason' => $exchangeOrderStatus['rejectReason'] ?? null,
                                'order_updated_at' => now(),
                                'raw_response' => $exchangeOrderStatus,
                            ]);
                            $this->info("      Updated spot order {$dbOrder->order_id} status to {$status}");
                            break;
                            
                        case 'new':
                        case 'partiallyfilled':
                            // Order is still active, update any partial fill info
                            $dbOrder->update([
                                'executed_qty' => $exchangeOrderStatus['cumExecQty'] ?? $dbOrder->executed_qty,
                                'executed_price' => $exchangeOrderStatus['avgPrice'] ?? $dbOrder->executed_price,
                                'raw_response' => $exchangeOrderStatus,
                            ]);
                            break;
                    }
                }
            } catch (\Exception $e) {
                $this->warn("      Failed to sync spot order {$dbOrder->order_id}: " . $e->getMessage());
                Log::warning("Failed to sync spot order for user {$userId}", [
                    'order_id' => $dbOrder->order_id,
                    'exchange' => $userExchange->exchange_name,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function updateSpotOrderStatuses(int $userId, $userExchange, ExchangeApiServiceInterface $exchangeService): void
    {
        $this->info("    Updating spot order statuses for user {$userId} on {$userExchange->exchange_name}...");
        
        // Check for orders that might have been filled or cancelled outside our tracking
        $activeOrders = SpotOrder::where('user_exchange_id', $userExchange->id)
            ->whereIn('status', ['pending', 'new'])
            ->whereNotNull('order_id')
            ->get();

        if ($activeOrders->isEmpty()) {
            $this->info("    No active spot orders to update for user {$userId} on {$userExchange->exchange_name}");
            return;
        }

        foreach ($activeOrders as $dbOrder) {
            try {
                $exchangeOrderStatus = $exchangeService->getSpotOrderStatus($dbOrder->order_id, $dbOrder->symbol);
                
                if (!$exchangeOrderStatus) {
                    // Order not found on exchange, might have been cancelled or filled
                    $this->warn("      Spot order {$dbOrder->order_id} not found on exchange, marking as unknown");
                    continue;
                }
                
                $status = strtolower($exchangeOrderStatus['orderStatus'] ?? 'unknown');
                
                if ($status !== strtolower($dbOrder->status)) {
                    $dbOrder->update([
                        'status' => $status,
                        'executed_qty' => $exchangeOrderStatus['cumExecQty'] ?? $dbOrder->executed_qty,
                        'executed_price' => $exchangeOrderStatus['avgPrice'] ?? $dbOrder->executed_price,
                        'order_updated_at' => now(),
                        'raw_response' => $exchangeOrderStatus,
                    ]);
                    $this->info("      Updated spot order {$dbOrder->order_id} status from {$dbOrder->status} to {$status}");
                }
                
            } catch (\Exception $e) {
                $this->warn("      Failed to update spot order {$dbOrder->order_id}: " . $e->getMessage());
            }
        }
    }
}
