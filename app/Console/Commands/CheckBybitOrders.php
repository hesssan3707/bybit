<?php

namespace App\Console\Commands;

use App\Models\BybitOrders;
use App\Services\BybitApiService;
use Illuminate\Console\Command;

class CheckBybitOrders extends Command
{
    protected $signature = 'bybit:check-orders';
    protected $description = 'Checks for and cancels expired pending orders.';

    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        parent::__construct();
        $this->bybitApiService = $bybitApiService;
    }

    public function handle()
    {
        $this->info('Checking for expired orders...');
        $now = time();

        $pendingDbOrders = BybitOrders::where('status', 'pending')->get();
        $this->info("Found " . $pendingDbOrders->count() . " pending orders to check for expiration.");

        foreach ($pendingDbOrders as $dbOrder) {
            $expireAt = $dbOrder->created_at->timestamp + ($dbOrder->expire_minutes * 60);

            if ($now >= $expireAt) {
                try {
                    // Fetch the order from Bybit to ensure it's still 'New' (i.e., open and not filled)
                    $orderResult = $this->bybitApiService->getHistoryOrder($dbOrder->order_id);
                    $order = $orderResult['list'][0] ?? null;

                    if ($order && $order['orderStatus'] === 'New') {
                        $this->bybitApiService->cancelOrder($dbOrder->order_id, $order['symbol']);
                        $dbOrder->status = 'canceled';
                        $dbOrder->save();
                        $this->info("Canceled expired order: {$dbOrder->order_id}");
                    } elseif ($order && $order['orderStatus'] !== 'New') {
                        // If it's filled or already canceled, we can just update our DB
                        // This logic is better handled by BybitLifecycle, but we'll log it here.
                        $this->info("Order {$dbOrder->order_id} is no longer 'New' on the exchange (Status: {$order['orderStatus']}). Skipping cancellation.");
                        // To be robust, you might want to update the status here too.
                        // $dbOrder->status = strtolower($order['orderStatus']);
                        // $dbOrder->save();
                    }

                } catch (\Throwable $e) {
                    $this->error("Failed to process order ID {$dbOrder->order_id}: " . $e->getMessage());
                }
            }
        }
        $this->info('Finished checking for expired orders.');
        return self::SUCCESS;
    }
}
