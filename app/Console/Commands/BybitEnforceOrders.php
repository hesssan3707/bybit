<?php

namespace App\Console\Commands;

use App\Models\BybitOrders;
use App\Services\BybitApiService;
use Illuminate\Console\Command;

class BybitEnforceOrders extends Command
{
    protected $signature = 'bybit:enforce';
    protected $description = 'Cancels all Bybit orders that are not registered in our database.';

    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        parent::__construct();
        $this->bybitApiService = $bybitApiService;
    }

    public function handle(): int
    {
        $this->info('Starting to enforce Bybit orders...');

        $symbol = 'ETHUSDT'; // V5 API uses ETHUSDT

        try {
            // Get the list of current 'open' orders from Bybit
            $openOrdersResult = $this->bybitApiService->getOpenOrders($symbol);
            $openOrders = $openOrdersResult['list'];

            $openIds = array_map(fn($o) => $o['orderId'] ?? null, $openOrders);
            $openIds = array_filter($openIds);

            if (empty($openIds)) {
                $this->info('No open orders found on Bybit. Nothing to do.');
                return self::SUCCESS;
            }

            // Get the list of orders we created and are tracking
            $ourIds = BybitOrders::whereIn('status', ['pending', 'filled'])
                ->pluck('order_id')
                ->filter()
                ->values()
                ->toArray();

            // Find orders that are on Bybit but not in our database
            $foreignIds = array_values(array_diff($openIds, $ourIds));

            if (empty($foreignIds)) {
                $this->info('No foreign orders found to cancel.');
                return self::SUCCESS;
            }

            $this->info("Found " . count($foreignIds) . " foreign orders to cancel.");

            foreach ($foreignIds as $orderId) {
                try {
                    $this->bybitApiService->cancelOrder($orderId, $symbol);
                    $this->info("Canceled foreign order: {$orderId}");
                } catch (\Throwable $e) {
                    $this->warn("Failed to cancel foreign order {$orderId}: " . $e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $this->error("Order enforcement failed: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Successfully finished enforcing Bybit orders.');
        return self::SUCCESS;
    }
}
