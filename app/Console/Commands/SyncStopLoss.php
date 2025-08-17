<?php

namespace App\Console\Commands;

use App\Models\BybitOrders;
use App\Services\BybitApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncStopLoss extends Command
{
    protected $signature = 'bybit:sync-sl';
    protected $description = 'Checks active positions and resets the Stop Loss if it has been changed on the exchange.';

    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        parent::__construct();
        $this->bybitApiService = $bybitApiService;
    }

    public function handle(): int
    {
        $this->info('Starting Stop Loss synchronization...');

        $symbol = 'ETHUSDT';
        $this->info("Starting Stop Loss synchronization for {$symbol}...");

        // Get all orders that should have an active position on the exchange
        $filledOrders = BybitOrders::where('status', 'filled')
            ->where('symbol', $symbol)
            ->get();

        if ($filledOrders->isEmpty()) {
            $this->info("No filled {$symbol} orders found in the database. Nothing to sync.");
            return self::SUCCESS;
        }

        try {
            $positionInfoResult = $this->bybitApiService->getPositionInfo($symbol);
            $positions = $positionInfoResult['list'] ?? [];

            if (empty($positions)) {
                $this->warn("No open positions found for symbol {$symbol} on Bybit.");
                return self::SUCCESS;
            }

            foreach ($filledOrders as $dbOrder) {
                try {
                    // Find the matching position from the API response
                    // V5 API side values: 'Buy', 'Sell', 'None'
                    $matchingPosition = null;
                    foreach ($positions as $pos) {
                        // Position side 'Buy' matches our 'buy' orders, 'Sell' matches 'sell'
                        if (strtolower($pos['side']) === strtolower($dbOrder->side)) {
                            $matchingPosition = $pos;
                            break;
                        }
                    }

                    if (!$matchingPosition) {
                        Log::warning("Could not find matching Bybit position for our filled order ID: {$dbOrder->id}");
                        continue;
                    }

                    $exchangeSl = (float)($matchingPosition['stopLoss'] ?? 0);
                    $databaseSl = (float)$dbOrder->sl;

                    // Compare SL, allowing for floating point inaccuracies
                    if (abs($exchangeSl - $databaseSl) > 0.00001) {
                        $this->warn("SL mismatch for {$symbol} (Side: {$dbOrder->side}). Exchange: {$exchangeSl}, DB: {$databaseSl}. Resetting...");

                        $params = [
                            'category' => 'linear',
                            'symbol' => $symbol,
                            'stopLoss' => (string)$databaseSl,
                            // We must also provide the takeProfit, otherwise it will be removed.
                            // Assuming TP from the first leg is the one we want to maintain.
                            'takeProfit' => (string)$dbOrder->tp,
                        ];

                        $this->bybitApiService->setTradingStop($params);
                        $this->info("Successfully reset SL for {$symbol} to {$databaseSl}.");
                    } else {
                        $this->info("SL for {$symbol} (Side: {$dbOrder->side}) is in sync.");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Failed to sync SL for symbol {$symbol}: " . $e->getMessage());
                Log::error("Bybit SL Sync Error for {$symbol}: " . $e->getMessage(), ['exception' => $e]);
            }
        }

        $this->info('Finished Stop Loss synchronization.');
        return self::SUCCESS;
    }
}
