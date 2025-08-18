<?php

namespace App\Console\Commands;

use App\Models\BybitOrders;
use App\Services\BybitApiService;
use Illuminate\Console\Command;

class BybitLifecycle extends Command
{
    protected $signature = 'bybit:lifecycle';
    protected $description = 'Syncs local order statuses with the exchange.';

    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        parent::__construct();
        $this->bybitApiService = $bybitApiService;
    }

    public function handle(): int
    {
        $this->info('Starting order status sync...');

        $ordersToCheck = BybitOrders::whereIn('status', ['pending', 'filled'])
            ->where('symbol', 'ETHUSDT')
            ->get();

        $this->info("Found " . $ordersToCheck->count() . " pending or filled ETHUSDT orders to check.");

        foreach ($ordersToCheck as $dbOrder) {
            try {
                $symbol = $dbOrder->symbol;

                // Logic for 'pending' orders: Check if they have been filled or externally canceled.
                if ($dbOrder->status === 'pending') {
                    // We use getHistoryOrder because we only care about final states (Filled, Cancelled).
                    // Active 'New' orders are handled by the 'bybit:enforce' command for expiration checks.
                    $orderResult = $this->bybitApiService->getHistoryOrder($dbOrder->order_id);
                    $order = $orderResult['list'][0] ?? null;

                    if (!$order) {
                        // This is expected if the order is still 'New' and not in history.
                        // We can safely skip it, as it's not filled or canceled yet.
                        continue;
                    }

                    $bybitStatus = $order['orderStatus'];

                    if ($bybitStatus === 'Filled') {
                        $dbOrder->status = 'filled';
                        $dbOrder->save();
                        $this->info("Order {$dbOrder->order_id} is filled. Awaiting TP/SL execution.");
                    } elseif (in_array($bybitStatus, ['Cancelled', 'Deactivated', 'Rejected'])) {
                        $dbOrder->status = 'canceled';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("Marked order {$dbOrder->order_id} as canceled to match exchange status.");
                    }
                }
                // Logic for 'filled' orders: Check if the corresponding position has been closed.
                elseif ($dbOrder->status === 'filled') {
                    $positionResult = $this->bybitApiService->getPositionInfo($symbol);
                    $position = $positionResult['list'][0] ?? null;

                    // If no position exists or its size is 0, it means it has been closed.
                    if (!$position || (float)$position['size'] === 0.0) {
                        $dbOrder->status = 'closed';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("Position for order {$dbOrder->order_id} is closed. Marked as 'closed' in DB.");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Lifecycle check failed for order ID {$dbOrder->id} (Bybit ID {$dbOrder->order_id}): " . $e->getMessage());
            }
        }

        $this->info('Finished order lifecycle management.');
        return self::SUCCESS;
    }
}
