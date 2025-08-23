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
                $this->warn("Failed to sync stop loss for user {$user->id}: " . $e->getMessage());
                Log::warning("Failed to sync stop loss for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function syncForUser(int $userId): void
    {
        $this->info("Syncing stop loss for user {$userId}...");
        
        try {
            $exchangeService = ExchangeFactory::createForUser($userId);
        } catch (\Exception $e) {
            $this->warn("No active exchange for user {$userId}: " . $e->getMessage());
            return;
        }

        $symbol = 'ETHUSDT'; // Make this configurable if needed

        // Get all filled orders for this user
        $filledOrders = Order::where('user_id', $userId)
            ->where('status', 'filled')
            ->where('symbol', $symbol)
            ->get();

        if ($filledOrders->isEmpty()) {
            $this->info("  No filled {$symbol} orders found for user {$userId}");
            return;
        }

        try {
            $positionResult = $exchangeService->getPositions($symbol);
            $positions = $positionResult['list'] ?? [];

            if (empty($positions)) {
                $this->info("  No open positions found for user {$userId} on {$symbol}");
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
                    Log::warning("Could not find matching position for user {$userId}, order ID: {$dbOrder->id}");
                    continue;
                }

                $exchangeSl = (float)($matchingPosition['stopLoss'] ?? 0);
                $databaseSl = (float)$dbOrder->sl;

                // Compare SL with tolerance for floating point precision
                if (abs($exchangeSl - $databaseSl) > 0.001) {
                    $this->warn("  SL mismatch for user {$userId}, {$symbol} (Side: {$dbOrder->side}). Exchange: {$exchangeSl}, DB: {$databaseSl}. Resetting...");

                    try {
                        // Prepare parameters to update stop loss
                        $params = [
                            'category' => 'linear',
                            'symbol' => $symbol,
                            'stopLoss' => (string)$databaseSl,
                            'takeProfit' => (string)(float)($matchingPosition['takeProfit'] ?? "0"),
                            'tpslMode' => $matchingPosition['tpslMode'] ?? 'Full',
                            'tpTriggerBy' => $matchingPosition['tpTriggerBy'] ?? 'LastPrice',
                            'slTriggerBy' => $matchingPosition['slTriggerBy'] ?? 'LastPrice',
                            'positionIdx' => $matchingPosition['positionIdx'] ?? 0,
                        ];

                        $exchangeService->setStopLoss($symbol, $databaseSl, $dbOrder->side);
                        $this->info("  Successfully reset SL for user {$userId}, {$symbol} to {$databaseSl}");
                    } catch (\Exception $e) {
                        $this->error("  Failed to reset SL for user {$userId}, {$symbol}: " . $e->getMessage());
                        Log::error("Failed to reset SL for user {$userId}", [
                            'symbol' => $symbol,
                            'order_id' => $dbOrder->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    $this->info("  SL for user {$userId}, {$symbol} (Side: {$dbOrder->side}) is in sync");
                }
            }

        } catch (\Exception $e) {
            $this->error("  Failed to sync SL for user {$userId}, symbol {$symbol}: " . $e->getMessage());
            Log::error("Stop loss sync error for user {$userId}", [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
        }
    }
}