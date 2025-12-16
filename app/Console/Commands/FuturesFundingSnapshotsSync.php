<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\FuturesFundingSnapshot;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class FuturesFundingSnapshotsSync extends Command
{
    protected $signature = 'futures:sync-funding-snapshots {--exchange=} {--symbols=}';

    protected $description = 'همگام‌سازی نرخ فاندینگ و اوپن اینترست برای بازارهای BTC و ETH';

    public function handle()
    {
        $exchangeOption = $this->option('exchange');
        $symbolsOption = $this->option('symbols');

        if ($symbolsOption) {
            $symbols = array_filter(array_map('trim', explode(',', $symbolsOption)));
        } else {
            $symbols = ['BTCUSDT', 'ETHUSDT'];
        }

        if (empty($symbols)) {
            $this->error('هیچ نمادی برای پردازش وجود ندارد.');
            return 1;
        }

        $allExchanges = ['bybit', 'binance', 'bingx'];

        if ($exchangeOption) {
            $exchangeOption = strtolower(trim($exchangeOption));
            if (!in_array($exchangeOption, $allExchanges, true)) {
                $this->error('صرافی نامعتبر است. گزینه‌های مجاز: bybit, binance, bingx');
                return 1;
            }
            $exchanges = [$exchangeOption];
        } else {
            $exchanges = $allExchanges;
        }

        foreach ($exchanges as $exchange) {
            $this->info('در حال همگام‌سازی برای صرافی ' . $exchange . '...');
            foreach ($symbols as $symbol) {
                try {
                    [$fundingRate, $openInterest, $metricTime] = $this->fetchMetrics($exchange, $symbol);
                    if ($fundingRate === null && $openInterest === null) {
                        continue;
                    }

                    FuturesFundingSnapshot::create([
                        'exchange' => $exchange,
                        'symbol' => $symbol,
                        'funding_rate' => $fundingRate,
                        'open_interest' => $openInterest,
                        'metric_time' => $metricTime ?: Carbon::now(),
                    ]);
                } catch (\Throwable $e) {
                    $this->error('خطا در همگام‌سازی برای ' . $exchange . ' ' . $symbol . ': ' . $e->getMessage());
                }
            }
        }

        try {
            $levels = [];
            foreach ($exchanges as $exchange) {
                foreach ($symbols as $symbol) {
                    $latest = FuturesFundingSnapshot::where('exchange', $exchange)
                        ->where('symbol', $symbol)
                        ->orderByDesc('metric_time')
                        ->orderByDesc('id')
                        ->first();
                    if ($latest) {
                        $funding = $latest->funding_rate;
                        $oi = $latest->open_interest;
                        $absFunding = $funding !== null ? abs((float)$funding) : null;
                        $level = 'normal';
                        if ($absFunding !== null) {
                            if ($absFunding > 0.0005) {
                                $level = 'critical';
                            } elseif ($absFunding > 0.0002) {
                                $level = 'elevated';
                            }
                        }
                        if ($level === 'normal' && $oi !== null) {
                            if ((float)$oi > 0) {
                                $level = 'elevated';
                            }
                        }
                        $levels[] = $level;
                    }
                }
            }
            $worst = 'normal';
            foreach ($levels as $lvl) {
                if ($lvl === 'critical') { $worst = 'critical'; break; }
                if ($lvl === 'elevated' && $worst === 'normal') { $worst = 'elevated'; }
            }
            if ($worst === 'critical') {
                Cache::put('market:risk', 'risky', now()->addMinutes(10));
            } else {
                Cache::forget('market:risk');
            }
        } catch (\Throwable $e) {
            // silent cache failure
        }

        $this->info('همگام‌سازی اسنپ‌شات‌های فاندینگ تکمیل شد.');
        return 0;
    }

    private function fetchMetrics(string $exchange, string $symbol): array
    {
        if ($exchange === 'bybit') {
            return $this->fetchBybitMetrics($symbol);
        }
        if ($exchange === 'binance') {
            return $this->fetchBinanceMetrics($symbol);
        }
        if ($exchange === 'bingx') {
            return $this->fetchBingxMetrics($symbol);
        }
        return [null, null, null];
    }

    private function fetchBybitMetrics(string $symbol): array
    {
        $fundingRate = null;
        $openInterest = null;
        $metricTime = null;

        try {
            $resp = Http::get('https://api.bybit.com/v5/market/funding/history', [
                'category' => 'linear',
                'symbol' => $symbol,
                'limit' => 1,
            ]);
            if ($resp->ok()) {
                $json = $resp->json();
                $list = $json['result']['list'] ?? [];
                if ($list && isset($list[0]['fundingRate'])) {
                    $fundingRate = (float) $list[0]['fundingRate'];
                    $ts = $list[0]['fundingRateTimestamp'] ?? $list[0]['fundingTime'] ?? null;
                    if ($ts !== null) {
                        $metricTime = Carbon::createFromTimestampMs((int) $ts);
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $resp2 = Http::get('https://api.bybit.com/v5/market/open-interest', [
                'category' => 'linear',
                'symbol' => $symbol,
                'interval' => '5min',
                'limit' => 1,
            ]);
            if ($resp2->ok()) {
                $json2 = $resp2->json();
                $list2 = $json2['result']['list'] ?? [];
                if ($list2 && isset($list2[0]['openInterest'])) {
                    $openInterest = (float) $list2[0]['openInterest'];
                    if ($metricTime === null) {
                        $ts2 = $list2[0]['timestamp'] ?? null;
                        if ($ts2 !== null) {
                            $metricTime = Carbon::createFromTimestampMs((int) $ts2);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return [$fundingRate, $openInterest, $metricTime];
    }

    private function fetchBinanceMetrics(string $symbol): array
    {
        $fundingRate = null;
        $openInterest = null;
        $metricTime = null;

        try {
            $resp = Http::get('https://fapi.binance.com/fapi/v1/fundingRate', [
                'symbol' => $symbol,
                'limit' => 1,
            ]);
            if ($resp->ok()) {
                $data = $resp->json();
                if (is_array($data) && isset($data[0]['fundingRate'])) {
                    $fundingRate = (float) $data[0]['fundingRate'];
                    $ts = $data[0]['fundingTime'] ?? null;
                    if ($ts !== null) {
                        $metricTime = Carbon::createFromTimestampMs((int) $ts);
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $resp2 = Http::get('https://fapi.binance.com/fapi/v1/openInterest', [
                'symbol' => $symbol,
            ]);
            if ($resp2->ok()) {
                $json2 = $resp2->json();
                if (isset($json2['openInterest'])) {
                    $openInterest = (float) $json2['openInterest'];
                    if ($metricTime === null) {
                        $ts2 = $json2['time'] ?? null;
                        if ($ts2 !== null) {
                            $metricTime = Carbon::createFromTimestampMs((int) $ts2);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return [$fundingRate, $openInterest, $metricTime];
    }

    private function fetchBingxMetrics(string $symbol): array
    {
        $fundingRate = null;
        $openInterest = null;
        $metricTime = null;

        try {
            $resp = Http::get('https://open-api.bingx.com/openApi/swap/v2/quote/fundingRate', [
                'symbol' => $symbol,
            ]);
            if ($resp->ok()) {
                $json = $resp->json();
                $rate = $json['data']['fundingRate'] ?? ($json['data'][0]['fundingRate'] ?? ($json['fundingRate'] ?? null));
                $ts = $json['data']['time'] ?? ($json['data'][0]['time'] ?? ($json['time'] ?? null));
                if ($rate !== null) {
                    $fundingRate = (float) $rate;
                }
                if ($ts !== null) {
                    $metricTime = Carbon::createFromTimestampMs((int) $ts);
                }
            }
        } catch (\Throwable $e) {
        }

        return [$fundingRate, $openInterest, $metricTime];
    }
}
