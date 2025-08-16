<?php

// app/Console/Commands/BybitLifecycle.php
namespace App\Console\Commands;

use App\Models\BybitOrders;
use Illuminate\Console\Command;

class BybitLifecycle extends Command
{
    protected $signature = 'bybit:lifecycle';
    protected $description = 'اعمال انقضا و گذاشتن اردر کلوز TP برای سفارش‌های پر شده';

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

        $now = time();

        // 1) اگر زمان انقضا گذشته و هنوز open است: لغو
        $pendings = BybitOrders::where('status','pending')->get();
        foreach ($pendings as $row) {
            try {
                $o = $exchange->fetchOrder($row->order_id, $row->symbol);
                if ($o && isset($o['status'])) {
                    if ($o['status'] === 'open') {
                        $expireAt = $row->created_at->timestamp + ($row->expire_minutes * 60);
                        if ($now >= $expireAt) {
                            $exchange->cancelOrder($row->order_id, $row->symbol);
                            $row->status = 'canceled';
                            $row->save();
                            $this->info("Canceled expired order {$row->order_id}");
                        }
                    } elseif ($o['status'] === 'closed') {
                        // 2) اگر پر شد، اردر کلوز در TP بگذار
                        $closeSide = ($row->side === 'buy') ? 'sell' : 'buy';
                        $tpPrice   = (float)$row->tp;

                        $exchange->createOrder($row->symbol, 'limit', $closeSide, (float)$row->amount, $tpPrice, [
                            'reduceOnly'  => true,
                            'timeInForce' => 'GTC',
                        ]);

                        $row->status = 'filled';
                        $row->save();
                        $this->info("Placed TP close for {$row->order_id} at {$tpPrice}");
                    } elseif ($o['status'] === 'canceled') {
                        $row->status = 'canceled';
                        $row->save();
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("Lifecycle check failed for {$row->order_id}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
