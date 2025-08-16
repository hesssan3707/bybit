<?php

// app/Console/Commands/BybitEnforceOrders.php
namespace App\Console\Commands;

use App\Models\BybitOrders;
use Illuminate\Console\Command;

class BybitEnforceOrders extends Command
{
    protected $signature = 'bybit:enforce';
    protected $description = 'لغو تمام سفارش‌های Bybit که در دیتابیس ما ثبت نشده‌اند';

    public function handle(): int
    {
        require_once base_path('vendor/autoload.php');

        $exchange = new \ccxt\bybit([
            'apiKey' => env('BYBIT_API_KEY'),
            'secret' => env('BYBIT_API_SECRET'),
            'enableRateLimit' => true,
            'options' => [
                'defaultType' => 'unified',
                'recvWindow' => 5000,
                'adjustForTimeDifference' => true
            ]
        ]);
        if (env('BYBIT_TESTNET', false)) {
            $exchange->set_sandbox_mode(true);
        }

        $symbol = 'ETH/USDT';

        try {
            // لیست سفارش‌های «باز» فعلی در Bybit
            $openOrders = $exchange->fetchOpenOrders($symbol);
            $openIds    = array_map(fn($o) => $o['id'] ?? null, $openOrders);
            $openIds    = array_filter($openIds);

            // لیست سفارش‌هایی که ما ساختیم
            $ourIds = BybitOrders::whereIn('status',['pending','filled'])
                ->pluck('order_id')
                ->filter()
                ->values()
                ->toArray();

            // هر چی در بایبیت هست ولی تو دیتابیس ما نیست
            $foreign = array_values(array_diff($openIds, $ourIds));

            foreach ($foreign as $oid) {
                try {
                    $exchange->cancelOrder($oid, $symbol);
                    $this->info("Canceled foreign order: {$oid}");
                } catch (\Throwable $e) {
                    $this->warn("Failed cancel foreign {$oid}: ".$e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $this->error("Enforce failed: ".$e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
