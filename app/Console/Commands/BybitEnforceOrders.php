<?php

namespace App\Console\Commands;

use App\Models\BybitOrders;
use App\Services\BybitApiService;
use Illuminate\Console\Command;

class BybitEnforceOrders extends Command
{
    protected $signature = 'bybit:enforce';
    protected $description = 'Cancels foreign orders and expired local pending orders.';

    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        parent::__construct();
        $this->bybitApiService = $bybitApiService;
    }

    public function handle(): int
    {
        $this->info('Starting order enforcement...');
        $symbol = 'ETHUSDT';

        try {
            // 1. Get all open orders from Bybit
            $openOrdersResult = $this->bybitApiService->getOpenOrders($symbol);
            $bybitOpenOrders = $openOrdersResult['list'];
            $bybitOpenOrderIds = array_map(fn($o) => $o['orderId'], $bybitOpenOrders);

            // Create a map for efficient lookups
            $bybitOpenOrdersMap = array_combine($bybitOpenOrderIds, $bybitOpenOrders);

            // 2. Handle expired local 'pending' orders
            $this->info('Checking for expired local orders...');
            $ourPendingOrders = BybitOrders::where('status', 'pending')->get();
            $now = time();

            foreach ($ourPendingOrders as $dbOrder) {
                // Check if the order still exists on Bybit before trying to cancel
                if (!isset($bybitOpenOrdersMap[$dbOrder->order_id])) {
                    // If our 'pending' order is not on Bybit's open list, it might have been filled or canceled.
                    // The bybit:lifecycle command will handle this state change. We can skip it here.
                    continue;
                }

                $expireAt = $dbOrder->created_at->timestamp + ($dbOrder->expire_minutes * 60);
                if ($now >= $expireAt) {
                    try {
                        $this->bybitApiService->cancelOrder($dbOrder->order_id, $symbol);
                        $dbOrder->status = 'expired';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("Canceled expired local order: {$dbOrder->order_id}");
                    } catch (\Throwable $e) {
                        $this->warn("Failed to cancel expired local order {$dbOrder->order_id}: " . $e->getMessage());
                    }
                }
            }

            // 3. Handle foreign orders (not in our DB)
            $this->info('Checking for foreign orders to cancel...');
            $ourTrackedIds = BybitOrders::whereIn('status', ['pending', 'filled'])
                ->pluck('order_id')
                ->filter()
                ->all();

            $foreignOrderIds = array_diff($bybitOpenOrderIds, $ourTrackedIds);

            if (empty($foreignOrderIds)) {
                $this->info('No foreign orders found to cancel.');
            } else {
                $this->info("Found " . count($foreignOrderIds) . " foreign orders. Checking which ones to cancel...");
                foreach ($foreignOrderIds as $orderId) {
                    try {
                        $orderToCancel = $bybitOpenOrdersMap[$orderId] ?? null;
                        if ($orderToCancel && ($orderToCancel['reduceOnly'] ?? false) === true) {
                            $this->info("Skipping cancellation of reduce-only foreign order: {$orderId}");
                            continue;
                        }
                        $this->bybitApiService->cancelOrder($orderId, $symbol);
                        $this->info("Canceled foreign order: {$orderId}");
                    } catch (\Throwable $e) {
                        $this->warn("Failed to process foreign order {$orderId}: " . $e->getMessage());
                    }
                }
            }

        } catch (\Throwable $e) {
            $this->error("Order enforcement failed: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Successfully finished order enforcement.');
        return self::SUCCESS;
    }
}
