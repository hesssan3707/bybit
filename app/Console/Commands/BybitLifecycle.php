<?php

namespace App\Console\Commands;

use App\Models\BybitOrders;
use App\Services\BybitApiService;
use Illuminate\Console\Command;

class BybitLifecycle extends Command
{
    protected $signature = 'bybit:lifecycle';
    protected $description = 'Manages order lifecycle by expiring open orders and syncing statuses.';

    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        parent::__construct();
        $this->bybitApiService = $bybitApiService;
    }

    public function handle(): int
    {
        $this->info('Starting order lifecycle management...');
        $now = time();

        $ordersToCheck = BybitOrders::whereIn('status', ['pending', 'filled'])
            ->where('symbol', 'ETHUSDT')
            ->get();

        $this->info("Found " . $ordersToCheck->count() . " pending or filled ETHUSDT orders to check.");

        foreach ($ordersToCheck as $dbOrder) {
            try {
                $symbol = $dbOrder->symbol; // Assuming symbol is stored like 'ETHUSDT'

                // Logic for 'pending' orders: Check if they are filled, canceled or expired.
                if ($dbOrder->status === 'pending') {
                    $orderResult = $this->bybitApiService->getHistoryOrder($dbOrder->order_id);
                    $order = $orderResult['list'][0] ?? null;

                    if (!$order) {
                        $this->warn("Could not fetch order details for DB order ID {$dbOrder->id} (Bybit ID: {$dbOrder->order_id}).");
                        continue;
                    }

                    $bybitStatus = $order['orderStatus'];

                    if ($bybitStatus === 'New') {
                        $expireAt = $dbOrder->created_at->timestamp + ($dbOrder->expire_minutes * 60);
                        if ($now >= $expireAt) {
                            $this->bybitApiService->cancelOrder($dbOrder->order_id, $symbol);
                            $dbOrder->status = 'canceled';
                            $dbOrder->closed_at = now();
                            $dbOrder->save();
                            $this->info("Canceled expired order: {$dbOrder->order_id}");
                        }
                    } elseif ($bybitStatus === 'Filled') {
                        // TP order is now placed via SyncStopLoss command, just update status
                        $closeSide = (strtolower($dbOrder->side) === 'buy') ? 'Sell' : 'Buy';
                        $tpPrice = (float)$dbOrder->tp;
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

                        $this->bybitApiService->createOrder($tpOrderParams);
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
                // Logic for 'filled' orders: Check if the position is closed.
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
