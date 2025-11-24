<?php

namespace App\Console\Commands;

use App\Jobs\CollectOrderCandlesJob;
use App\Models\OrderCandleData;
use App\Models\Trade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectMissingCandleDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'futures:collect-missing-candles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect candle data for recently closed trades that are missing candle records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('بررسی معاملات بسته شده بدون داده کندل...');

        // Find trades closed in the last hour that don't have candle data
        $trades = Trade::whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subHour())
            ->whereDoesntHave('order.candleData')
            ->get();

        if ($trades->isEmpty()) {
            $this->info('هیچ معامله‌ای برای جمع‌آوری کندل پیدا نشد.');
            return 0;
        }

        $this->info("پیدا شد {$trades->count()} معامله بدون داده کندل");

        $dispatched = 0;
        foreach ($trades as $trade) {
            try {
                // Check if order exists
                $order = $trade->order;
                if (!$order) {
                    $this->warn("معامله {$trade->id} فاقد سفارش مرتبط است");
                    continue;
                }

                // Check if candle data already exists (double-check)
                $exists = OrderCandleData::where('order_id', $order->id)->exists();
                if ($exists) {
                    continue;
                }

                // Dispatch job
                $job = new CollectOrderCandlesJob($trade->id);
                $job->handle();
                $dispatched++;
                
                $this->info("  ✓ پردازش کندل برای معامله {$trade->id} (سفارش {$order->order_id}) انجام شد");
            } catch (\Throwable $e) {
                $this->error("  ✗ خطا در پردازش برای معامله {$trade->id}: " . $e->getMessage());
                Log::error('Failed to dispatch CollectOrderCandlesJob', [
                    'trade_id' => $trade->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("✓ تعداد {$dispatched} job به صف اضافه شد");
        return 0;
    }
}
