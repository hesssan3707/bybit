<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderCandleData;
use App\Models\Trade;
use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CollectOrderCandlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tradeId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tradeId)
    {
        $this->tradeId = $tradeId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $trade = Trade::find($this->tradeId);
            if (!$trade) { return; }

            $userExchange = UserExchange::find($trade->user_exchange_id);
            if (!$userExchange) { return; }

            // Find the related Order by exchange order_id and user_exchange_id
            $order = Order::where('user_exchange_id', $trade->user_exchange_id)
                ->where('is_demo', (bool)$userExchange->is_demo_active)
                ->where('order_id', $trade->order_id)
                ->first();

            if (!$order) { return; }

            // Determine entry and exit timestamps (seconds)
            $entryAt = $order->filled_at ? $order->filled_at->getTimestamp() : ($order->created_at?->getTimestamp() ?? null);
            $exitAt = $trade->closed_at ? $trade->closed_at->getTimestamp() : null;
            if (!$entryAt || !$exitAt) { return; }

            // Define timeframe specs and target columns
            $specs = [
                '1m'  => ['seconds' => 60,    'before' => 50, 'after' => 10, 'column' => 'candles_m1'],
                '5m'  => ['seconds' => 300,   'before' => 50, 'after' => 10, 'column' => 'candles_m5'],
                '15m' => ['seconds' => 900,   'before' => 50, 'after' => 10, 'column' => 'candles_m15'],
                '1h'  => ['seconds' => 3600,  'before' => 20, 'after' => 5,  'column' => 'candles_h1'],
                '4h'  => ['seconds' => 14400, 'before' => 20, 'after' => 5,  'column' => 'candles_h4'],
            ];

            // Prepare exchange service if needed
            // Prepare exchange service
            $exchangeService = null;
            $exchangeName = strtolower((string)($userExchange->exchange_name ?? 'bybit'));

            $exchangeService = ExchangeFactory::createForUserExchange($userExchange);
            if (!method_exists($exchangeService, 'getKlines')) { return; }
            $exchangeName = method_exists($exchangeService, 'getExchangeName') ? $exchangeService->getExchangeName() : $exchangeName;

            // Upsert snapshot row for this order (we will fill columns below)
            $snapshot = OrderCandleData::firstOrNew(['order_id' => $order->id]);
            $snapshot->exchange = $exchangeName;
            $snapshot->symbol = $order->symbol;
            $snapshot->entry_price = $order->entry_price;
            $snapshot->exit_price = $trade->avg_exit_price ?: null;
            $snapshot->entry_time = $order->filled_at ?: $order->created_at;
            $snapshot->exit_time = $trade->closed_at ?: null;

            foreach ($specs as $tf => $cfg) {
                $tfSeconds = (int)$cfg['seconds'];
                $before = (int)$cfg['before'];
                $after  = (int)$cfg['after'];
                $column = $cfg['column'];

                // Align start/end to candle opens for this timeframe
                $alignedEntry = (int)floor($entryAt / $tfSeconds) * $tfSeconds;
                $alignedExit  = (int)floor($exitAt / $tfSeconds) * $tfSeconds;
                $startTs = $alignedEntry - ($before * $tfSeconds);
                $endTs   = $alignedExit + ($after * $tfSeconds);

                $candles = [];

                
                $barsBetween = max(0, (int)floor(($endTs - $startTs) / $tfSeconds));
                // add buffer to be safe with exchange windowing behavior
                $limit = min(1000, $barsBetween + $before + $after + 50);
                $raw = $exchangeService->getKlines($order->symbol, $tf, $limit);
                $candles = $this->normalizeKlines($exchangeName, $raw);
                // Filter to the requested window
                $candles = array_values(array_filter($candles, function ($c) use ($startTs, $endTs) {
                    $t = (int)($c['time'] ?? 0);
                    return $t >= $startTs && $t <= $endTs;
                }));

                if (count($candles) > 0) {
                    // Re-index and store
                    $candles = array_values($candles);
                    $snapshot->{$column} = $candles;
                }
            }

            $snapshot->save();
        } catch (\Throwable $e) {
            Log::error('CollectOrderCandlesJob failed: ' . $e->getMessage());
        }
    }

    private function generateSyntheticCandles(int $startTs, int $endTs, int $tfSeconds, float $basePrice, string $side): array
    {
        $candles = [];
        $steps = max(1, (int)floor(($endTs - $startTs) / $tfSeconds));
        $price = max(0.0001, $basePrice);
        $bias = $side === 'Buy' ? 0.0008 : -0.0008;
        for ($i = 0; $i <= $steps; $i++) {
            $t = $startTs + ($i * $tfSeconds);
            $delta = sin($i * 0.35) * 0.003 + $bias;
            $open = $price;
            $close = max(0.0001, $price * (1 + $delta));
            $high = max($open, $close) * (1 + 0.0015);
            $low  = min($open, $close) * (1 - 0.0015);
            $candles[] = [
                'time' => (int)$t,
                'open' => (float)$open,
                'high' => (float)$high,
                'low'  => (float)$low,
                'close'=> (float)$close,
            ];
            $price = $close;
        }
        return $candles;
    }

    private function normalizeKlines(string $exchangeName, $raw): array
    {
        $candles = [];
        $name = strtolower($exchangeName);

        if ($name === 'bybit') {
            $list = $raw['result']['list'] ?? $raw['result'] ?? $raw['list'] ?? [];
            foreach ($list as $c) {
                $candles[] = [
                    'time' => (int)floor(($c['start'] ?? $c['startTime'] ?? $c['openTime'] ?? $c['t'] ?? 0) / 1000),
                    'open' => (float)($c['open'] ?? $c['o'] ?? 0),
                    'high' => (float)($c['high'] ?? $c['h'] ?? 0),
                    'low'  => (float)($c['low']  ?? $c['l'] ?? 0),
                    'close'=> (float)($c['close']?? $c['c'] ?? 0),
                ];
            }
        } elseif ($name === 'binance') {
            $list = $raw['data'] ?? $raw['result'] ?? $raw ?? [];
            foreach ($list as $k) {
                // Binance kline format: [ openTime, open, high, low, close, ... ]
                if (is_array($k) && isset($k[0])) {
                    $candles[] = [
                        'time' => (int)($k[0] / 1000),
                        'open' => (float)$k[1],
                        'high' => (float)$k[2],
                        'low'  => (float)$k[3],
                        'close'=> (float)$k[4],
                    ];
                } elseif (is_array($k)) {
                    $candles[] = [
                        'time' => (int)floor(($k['openTime'] ?? $k['t'] ?? 0) / 1000),
                        'open' => (float)($k['open'] ?? $k['o'] ?? 0),
                        'high' => (float)($k['high'] ?? $k['h'] ?? 0),
                        'low'  => (float)($k['low']  ?? $k['l'] ?? 0),
                        'close'=> (float)($k['close']?? $k['c'] ?? 0),
                    ];
                }
            }
        } elseif ($name === 'bingx') {
            $list = $raw['data'] ?? $raw['result'] ?? $raw['list'] ?? [];
            foreach ($list as $k) {
                $candles[] = [
                    'time' => (int)floor(($k['time'] ?? $k['openTime'] ?? $k['t'] ?? 0) / 1000),
                    'open' => (float)($k['open'] ?? $k['o'] ?? 0),
                    'high' => (float)($k['high'] ?? $k['h'] ?? 0),
                    'low'  => (float)($k['low']  ?? $k['l'] ?? 0),
                    'close'=> (float)($k['close']?? $k['c'] ?? 0),
                ];
            }
        } else {
            // Fallback: try to coerce array of objects to normalized
            $list = is_array($raw) ? $raw : [];
            foreach ($list as $c) {
                if (!is_array($c)) { continue; }
                $candles[] = [
                    'time' => (int)($c['time'] ?? $c['t'] ?? 0),
                    'open' => (float)($c['open'] ?? $c['o'] ?? 0),
                    'high' => (float)($c['high'] ?? $c['h'] ?? 0),
                    'low'  => (float)($c['low']  ?? $c['l'] ?? 0),
                    'close'=> (float)($c['close']?? $c['c'] ?? 0),
                ];
            }
        }

        usort($candles, fn($a, $b) => ($a['time'] <=> $b['time']));
        return $candles;
    }
}