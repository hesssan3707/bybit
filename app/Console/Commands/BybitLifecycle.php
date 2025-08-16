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

        $pendingDbOrders = BybitOrders::where('status', 'pending')->get();
        $this->info("Found " . $pendingDbOrders->count() . " pending orders in the database to check.");

        foreach ($pendingDbOrders as $dbOrder) {
            try {
                // V5 API uses orderId, not a combination of ID and symbol
                $orderResult = $this->bybitApiService->getHistoryOrder($dbOrder->order_id);
                $order = $orderResult['list'][0] ?? null;

                if (!$order) {
                    $this->warn("Could not fetch order details for DB order ID {$dbOrder->id} (Bybit ID: {$dbOrder->order_id}).");
                    continue;
                }

                $bybitStatus = $order['orderStatus'];
                $symbol = $order['symbol'];

                // 1) If the order is still open (V5 status 'New') and expired, cancel it.
                if ($bybitStatus === 'New') {
                    $expireAt = $dbOrder->created_at->timestamp + ($dbOrder->expire_minutes * 60);
                    if ($now >= $expireAt) {
                        $this->bybitApiService->cancelOrder($dbOrder->order_id, $symbol);
                        $dbOrder->status = 'canceled';
                        $dbOrder->save();
                        $this->info("Canceled expired order: {$dbOrder->order_id}");
                    }
                }
                // 2) If the order is filled, just update our DB status. TP/SL are set on creation.
                elseif ($bybitStatus === 'Filled') {
                    $dbOrder->status = 'filled';
                    $dbOrder->save();
                    $this->info("Marked order {$dbOrder->order_id} as filled to match exchange status.");
                }
                // 3) If the order was canceled on the exchange, update our DB.
                elseif (in_array($bybitStatus, ['Cancelled', 'Deactivated', 'Rejected'])) {
                    $dbOrder->status = 'canceled';
                    $dbOrder->save();
                    $this->info("Marked order {$dbOrder->order_id} as canceled to match exchange status.");
                }

            } catch (\Throwable $e) {
                $this->error("Lifecycle check failed for our order ID {$dbOrder->id} (Bybit ID {$dbOrder->order_id}): " . $e->getMessage());
            }
        }

        $this->info('Finished order lifecycle management.');
        return self::SUCCESS;
    }
}
