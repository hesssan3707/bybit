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

        $symbol = 'ETHUSDT'; // Make this configurable if needed

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
                    Log::warning("Could not find matching position for user {$userId} on {$userExchange->exchange_name}, order ID: {$dbOrder->id}");
                    continue;
                }

                $exchangeSl = (float)($matchingPosition['stopLoss'] ?? 0);
                $databaseSl = (float)$dbOrder->sl;

                // Compare SL with tolerance for floating point precision
                if (abs($exchangeSl - $databaseSl) > 0.001) {
                    $this->warn("    SL mismatch for user {$userId} on {$userExchange->exchange_name}, {$symbol} (Side: {$dbOrder->side}). Exchange: {$exchangeSl}, DB: {$databaseSl}. Resetting...");

                    try {
                        // Prepare parameters to update stop loss with all required fields
                        $params = [
                            'category' => 'linear',
                            'symbol' => $symbol,
                            'stopLoss' => (string)$databaseSl,
                            'tpslMode' => 'Full', // Use Full mode for entire position
                            'positionIdx' => (int)($matchingPosition['positionIdx'] ?? 0),
                        ];

                        // Preserve existing take profit if it exists
                        $existingTp = (float)($matchingPosition['takeProfit'] ?? 0);
                        if ($existingTp > 0) {
                            $params['takeProfit'] = (string)$existingTp;
                        }

                        // Preserve trigger settings if they exist
                        if (isset($matchingPosition['tpTriggerBy']) && !empty($matchingPosition['tpTriggerBy'])) {
                            $params['tpTriggerBy'] = $matchingPosition['tpTriggerBy'];
                        }
                        if (isset($matchingPosition['slTriggerBy']) && !empty($matchingPosition['slTriggerBy'])) {
                            $params['slTriggerBy'] = $matchingPosition['slTriggerBy'];
                        }

                        // Use the advanced method with all parameters
                        $exchangeService->setStopLossAdvanced($params);
                        $this->info("    Successfully reset SL for user {$userId} on {$userExchange->exchange_name}, {$symbol} to {$databaseSl}");
                    } catch (\Exception $e) {
                        $this->error("    Failed to reset SL for user {$userId} on {$userExchange->exchange_name}, {$symbol}: " . $e->getMessage());
                        Log::error("Failed to reset SL for user {$userId} on exchange {$userExchange->exchange_name}", [
                            'symbol' => $symbol,
                            'order_id' => $dbOrder->id,
                            'error' => $e->getMessage(),
                            'api_params' => $params ?? null
                        ]);
                    }
                } else {
                    $this->info("    SL for user {$userId} on {$userExchange->exchange_name}, {$symbol} (Side: {$dbOrder->side}) is in sync");
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
}