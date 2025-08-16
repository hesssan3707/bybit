<?php

namespace App\Console\Commands;

use App\Models\BybitOrders;
use Illuminate\Console\Command;

class CheckBybitOrders extends Command
{
    protected $signature = 'bybit:check-orders';
    protected $description = 'بررسی و حذف سفارش‌های منقضی';

    public function handle()
    {
        require_once base_path('vendor/autoload.php');

        $apiKey    = env('BYBIT_API_KEY');
        $apiSecret = env('BYBIT_API_SECRET');
        $testnet   = env('BYBIT_TESTNET', false);

        $exchange = new \ccxt\bybit([
            'apiKey' => $apiKey,
            'secret' => $apiSecret,
            'enableRateLimit' => true,
            'options' => [
                'defaultType' => 'unified',
                'recvWindow' => 5000,
                'adjustForTimeDifference' => true
            ]
        ]);

        if ($testnet) {
            $exchange->set_sandbox_mode(true);
        }

        $orders = BybitOrders::where('status', 'pending')->get();

        foreach ($orders as $order) {
            $createdAt = $order->created_at->timestamp;
            $expireAt = $createdAt + ($order->expire_minutes * 60);

            if (time() >= $expireAt) {
                $o = $exchange->fetch_order($order->order_id, $order->symbol);
                if ($o['status'] === 'open') {
                    $exchange->cancel_order($order->order_id, $order->symbol);
                    $order->status = 'canceled';
                    $order->save();
                    $this->info("Order {$order->order_id} canceled.");
                }
            }
        }
    }
}
