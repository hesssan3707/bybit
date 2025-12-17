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

        $allExchanges = ['bybit', 'binance', 'bingx', 'okx', 'bitget', 'gate'];

        if ($exchangeOption) {
            $exchangeOption = strtolower(trim($exchangeOption));
            if (!in_array($exchangeOption, $allExchanges, true)) {
                $this->error('صرافی نامعتبر است. گزینه‌های مجاز: bybit, binance, bingx, okx, bitget, gate');
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
                    $metrics = $this->fetchMetrics($exchange, $symbol);
                    $fundingRate = $metrics['funding_rate'] ?? null;
                    $openInterest = $metrics['open_interest'] ?? null;
                    $totalMarketValue = $metrics['total_market_value'] ?? null;
                    $metricTime = $metrics['metric_time'] ?? null;

                    if ($fundingRate === null && $openInterest === null && $totalMarketValue === null) {
                        continue;
                    }

                    FuturesFundingSnapshot::create([
                        'exchange' => $exchange,
                        'symbol' => $symbol,
                        'funding_rate' => $fundingRate,
                        'open_interest' => $openInterest,
                        'total_market_value' => $totalMarketValue,
                        'metric_time' => $metricTime ?: Carbon::now(),
                    ]);
                } catch (\Throwable $e) {
                    $this->error('خطا در همگام‌سازی برای ' . $exchange . ' ' . $symbol . ': ' . $e->getMessage());
                }
            }
        }

        try {
            $from = now()->subDays(3);
            $oiAverages = [];
            try {
                $rows = FuturesFundingSnapshot::query()
                    ->select('exchange', 'symbol')
                    ->selectRaw('AVG(open_interest) as avg_oi')
                    ->selectRaw('COUNT(open_interest) as cnt_oi')
                    ->whereIn('exchange', $exchanges)
                    ->whereIn('symbol', $symbols)
                    ->whereNotNull('open_interest')
                    ->where('metric_time', '>=', $from)
                    ->groupBy('exchange', 'symbol')
                    ->get();

                foreach ($rows as $r) {
                    $ex = (string) $r->exchange;
                    $sym = (string) $r->symbol;
                    $oiAverages[$ex][$sym] = [
                        'avg' => $r->avg_oi !== null ? (float) $r->avg_oi : null,
                        'count' => $r->cnt_oi !== null ? (int) $r->cnt_oi : 0,
                    ];
                }
            } catch (\Throwable $e) {
            }

            $fundingElevatedThreshold = 0.0002;
            $fundingCriticalThreshold = 0.0005;
            $oiMinSamples = 12;
            $oiElevatedRatio = 1.4;
            $oiCriticalRatio = 1.8;

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
                            if ($absFunding > $fundingCriticalThreshold) {
                                $level = 'critical';
                            } elseif ($absFunding > $fundingElevatedThreshold) {
                                $level = 'elevated';
                            }
                        }

                        if ($oi !== null) {
                            $stats = $oiAverages[$exchange][$symbol] ?? null;
                            $avg = $stats['avg'] ?? null;
                            $count = (int) ($stats['count'] ?? 0);
                            if ($avg !== null && $avg > 0 && $count >= $oiMinSamples) {
                                $ratio = ((float) $oi) / (float) $avg;
                                if ($ratio >= $oiCriticalRatio) {
                                    $level = 'critical';
                                } elseif ($ratio >= $oiElevatedRatio && $level === 'normal') {
                                    $level = 'elevated';
                                }
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
            $message = 'شرایط بازار بر اساس داده‌های فعلی نسبتاً عادی است.';
            if ($worst === 'elevated') {
                $message = 'در بخشی از بازار، ترکیب فاندینگ و اوپن اینترست نشان‌دهنده افزایش ریسک و شلوغی معاملات است. بهتر است اندازه پوزیشن‌ها را با احتیاط انتخاب کنید.';
            } elseif ($worst === 'critical') {
                $message = 'بازار آتی در حال حاضر در وضعیت پرریسک قرار دارد؛ فاندینگ و اوپن اینترست در برخی نمادها در سطوح غیرعادی هستند. در این شرایط، ورود با اهرم بالا می‌تواند بسیار خطرناک باشد.';
            }

            if ($worst === 'critical') {
                Cache::put('market:risk', 'risky', now()->addMinutes(15));
                Cache::put('market:risk_level', 'critical', now()->addMinutes(15));
                Cache::put('market:risk_message', $message, now()->addMinutes(15));
                Cache::put('market:risk_updated_at', now()->toIso8601String(), now()->addMinutes(15));
            } else {
                Cache::forget('market:risk');
                Cache::forget('market:risk_level');
                Cache::forget('market:risk_message');
                Cache::forget('market:risk_updated_at');
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
        if ($exchange === 'okx') {
            return $this->fetchOkxMetrics($symbol);
        }
        if ($exchange === 'bitget') {
            return $this->fetchBitgetMetrics($symbol);
        }
        if ($exchange === 'gate') {
            return $this->fetchGateMetrics($symbol);
        }
        return [
            'funding_rate' => null,
            'open_interest' => null,
            'total_market_value' => null,
            'metric_time' => null,
        ];
    }

    private function fetchBybitMetrics(string $symbol): array
    {
        $fundingRate = null;
        $openInterest = null;
        $totalMarketValue = null;
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
                'intervalTime' => '5min',
                'limit' => 1,
            ]);
            if ($resp2->ok()) {
                $json2 = $resp2->json();
                $list2 = $json2['result']['list'] ?? [];
                if ($list2 && isset($list2[0]['openInterest'])) {
                    $openInterest = (float) $list2[0]['openInterest'];
                    if ($metricTime === null) {
                        $ts2 = $list2[0]['timestamp'] ?? null;
                        $metricTime = $this->parseTs($ts2);
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $resp3 = Http::get('https://api.bybit.com/v5/market/tickers', [
                'category' => 'linear',
                'symbol' => $symbol,
            ]);
            if ($resp3->ok()) {
                $json3 = $resp3->json();
                $tick = $json3['result']['list'][0] ?? null;
                if ($tick) {
                    $v = $tick['openInterestValue'] ?? null;
                    if ($v !== null) {
                        $totalMarketValue = (float) $v;
                    } elseif ($openInterest !== null) {
                        $px = $tick['markPrice'] ?? ($tick['lastPrice'] ?? null);
                        if ($px !== null) {
                            $totalMarketValue = (float) $openInterest * (float) $px;
                        }
                    }
                    if ($metricTime === null) {
                        $metricTime = $this->parseTs($json3['time'] ?? null);
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return [
            'funding_rate' => $fundingRate,
            'open_interest' => $openInterest,
            'total_market_value' => $totalMarketValue,
            'metric_time' => $metricTime,
        ];
    }

    private function fetchBinanceMetrics(string $symbol): array
    {
        $fundingRate = null;
        $openInterest = null;
        $totalMarketValue = null;
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
            $resp2 = Http::get('https://fapi.binance.com/futures/data/openInterestHist', [
                'symbol' => $symbol,
                'period' => '5m',
                'limit' => 1,
            ]);
            if ($resp2->ok()) {
                $list2 = $resp2->json();
                if (is_array($list2) && isset($list2[0])) {
                    $row = $list2[0];
                    if (isset($row['sumOpenInterest'])) {
                        $openInterest = (float) $row['sumOpenInterest'];
                    }
                    if (isset($row['sumOpenInterestValue'])) {
                        $totalMarketValue = (float) $row['sumOpenInterestValue'];
                    }
                    if ($metricTime === null) {
                        $metricTime = $this->parseTs($row['timestamp'] ?? null);
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        if ($openInterest === null || $metricTime === null) {
            try {
                $resp3 = Http::get('https://fapi.binance.com/fapi/v1/openInterest', [
                    'symbol' => $symbol,
                ]);
                if ($resp3->ok()) {
                    $json3 = $resp3->json();
                    if ($openInterest === null && isset($json3['openInterest'])) {
                        $openInterest = (float) $json3['openInterest'];
                    }
                    if ($metricTime === null) {
                        $metricTime = $this->parseTs($json3['time'] ?? null);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return [
            'funding_rate' => $fundingRate,
            'open_interest' => $openInterest,
            'total_market_value' => $totalMarketValue,
            'metric_time' => $metricTime,
        ];
    }

    private function fetchBingxMetrics(string $symbol): array
    {
        $fundingRate = null;
        $openInterest = null;
        $totalMarketValue = null;
        $metricTime = null;

        try {
            $resp = Http::get('https://open-api.bingx.com/openApi/swap/v2/quote/fundingRate', [
                'symbol' => $this->mapBingxSymbol($symbol),
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

        try {
            $resp2 = Http::get('https://open-api.bingx.com/openApi/swap/v2/quote/ticker', [
                'symbol' => $this->mapBingxSymbol($symbol),
            ]);
            if ($resp2->ok()) {
                $json2 = $resp2->json();
                $data2 = $json2['data'] ?? ($json2['result'] ?? null);
                if (is_array($data2) && isset($data2[0]) && is_array($data2[0])) {
                    $data2 = $data2[0];
                }

                if (is_array($data2)) {
                    $oi = $data2['openInterest'] ?? ($data2['open_interest'] ?? null);
                    $oiv = $data2['openInterestValue'] ?? ($data2['open_interest_value'] ?? ($data2['open_interest_usd'] ?? null));
                    if ($oi !== null) {
                        $openInterest = (float) $oi;
                    }
                    if ($oiv !== null) {
                        $totalMarketValue = (float) $oiv;
                    } elseif ($openInterest !== null) {
                        $px = $data2['markPrice'] ?? ($data2['lastPrice'] ?? ($data2['last_price'] ?? null));
                        if ($px !== null) {
                            $totalMarketValue = (float) $openInterest * (float) $px;
                        }
                    }
                    if ($metricTime === null) {
                        $metricTime = $this->parseTs($data2['time'] ?? ($data2['timestamp'] ?? ($json2['timestamp'] ?? ($json2['time'] ?? null))));
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return [
            'funding_rate' => $fundingRate,
            'open_interest' => $openInterest,
            'total_market_value' => $totalMarketValue,
            'metric_time' => $metricTime,
        ];
    }

    private function fetchOkxMetrics(string $symbol): array
    {
        $fundingRate = null;
        $openInterest = null;
        $totalMarketValue = null;
        $metricTime = null;

        $instId = $this->mapOkxSymbol($symbol);
        try {
            $resp = Http::get('https://www.okx.com/api/v5/public/funding-rate', [
                'instId' => $instId,
            ]);
            if ($resp->ok()) {
                $json = $resp->json();
                $data = $json['data'][0] ?? null;
                if ($data) {
                    $rate = $data['fundingRate'] ?? null;
                    $ts = $data['fundingTime'] ?? ($data['nextFundingRateTime'] ?? null);
                    if ($rate !== null) {
                        $fundingRate = (float) $rate;
                    }
                    if ($ts !== null) {
                        $metricTime = Carbon::createFromTimestampMs((int) $ts);
                    }
                }
            }
        } catch (\Throwable $e) {}

        try {
            $resp2 = Http::get('https://www.okx.com/api/v5/public/open-interest', [
                'instId' => $instId,
            ]);
            if ($resp2->ok()) {
                $json2 = $resp2->json();
                $data2 = $json2['data'][0] ?? null;
                if ($data2) {
                    $oi = $data2['oiCcy'] ?? ($data2['oi'] ?? null);
                    $oiUsd = $data2['oiUsd'] ?? null;
                    $ts2 = $data2['ts'] ?? null;
                    if ($oi !== null) {
                        $openInterest = (float) $oi;
                    }
                    if ($oiUsd !== null) {
                        $totalMarketValue = (float) $oiUsd;
                    }
                    if ($metricTime === null && $ts2 !== null) {
                        $metricTime = Carbon::createFromTimestampMs((int) $ts2);
                    }
                }
            }
        } catch (\Throwable $e) {}

        if ($totalMarketValue === null && $openInterest !== null) {
            try {
                $resp3 = Http::get('https://www.okx.com/api/v5/market/ticker', [
                    'instId' => $instId,
                ]);
                if ($resp3->ok()) {
                    $json3 = $resp3->json();
                    $tick = $json3['data'][0] ?? null;
                    if ($tick) {
                        $px = $tick['markPx'] ?? ($tick['last'] ?? null);
                        if ($px !== null) {
                            $totalMarketValue = (float) $openInterest * (float) $px;
                        }
                        if ($metricTime === null) {
                            $metricTime = $this->parseTs($tick['ts'] ?? null);
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        return [
            'funding_rate' => $fundingRate,
            'open_interest' => $openInterest,
            'total_market_value' => $totalMarketValue,
            'metric_time' => $metricTime,
        ];
    }

    private function fetchBitgetMetrics(string $symbol): array
    {
        $fundingRate = null;
        $openInterest = null;
        $totalMarketValue = null;
        $metricTime = null;

        try {
            $resp = Http::get('https://api.bitget.com/api/v2/mix/market/current-fund-rate', [
                'symbol' => $symbol,
                'productType' => 'usdt-futures',
            ]);
            if ($resp->ok()) {
                $json = $resp->json();
                $data = $json['data'][0] ?? null;
                if ($data) {
                    $rate = $data['fundingRate'] ?? null;
                    $ts = $data['nextUpdate'] ?? null;
                    if ($rate !== null) {
                        $fundingRate = (float) $rate;
                    }
                    if ($ts !== null) {
                        $metricTime = Carbon::createFromTimestampMs((int) $ts);
                    }
                }
            }
        } catch (\Throwable $e) {}

        try {
            $resp2 = Http::get('https://api.bitget.com/api/v2/mix/market/open-interest', [
                'symbol' => $symbol,
                'productType' => 'usdt-futures',
            ]);
            if ($resp2->ok()) {
                $json2 = $resp2->json();
                $data2 = $json2['data'] ?? null;
                $list2 = $data2['openInterestList'] ?? [];
                if ($list2 && isset($list2[0]['size'])) {
                    $openInterest = (float) $list2[0]['size'];
                }
                if ($metricTime === null) {
                    $metricTime = $this->parseTs($data2['ts'] ?? null);
                }
            }
        } catch (\Throwable $e) {}

        if ($openInterest !== null) {
            try {
                $resp3 = Http::get('https://api.bitget.com/api/v2/mix/market/ticker', [
                    'symbol' => $symbol,
                    'productType' => 'usdt-futures',
                ]);
                if ($resp3->ok()) {
                    $json3 = $resp3->json();
                    $tick = $json3['data'][0] ?? ($json3['data'] ?? null);
                    if (is_array($tick)) {
                        $px = $tick['markPrice'] ?? ($tick['lastPr'] ?? ($tick['last'] ?? null));
                        if ($px !== null) {
                            $totalMarketValue = (float) $openInterest * (float) $px;
                        }
                        if ($metricTime === null) {
                            $metricTime = $this->parseTs($tick['ts'] ?? null);
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        return [
            'funding_rate' => $fundingRate,
            'open_interest' => $openInterest,
            'total_market_value' => $totalMarketValue,
            'metric_time' => $metricTime,
        ];
    }

    private function fetchGateMetrics(string $symbol): array
    {
        $fundingRate = null;
        $openInterest = null;
        $totalMarketValue = null;
        $metricTime = null;

        $contract = $this->mapGateSymbol($symbol);
        try {
            $resp = Http::get('https://fx-api.gateio.ws/api/v4/futures/usdt/contracts/' . $contract);
            if ($resp->ok()) {
                $json = $resp->json();
                $rate = $json['funding_rate'] ?? null;
                $ts = $json['funding_next_apply'] ?? null;
                if ($rate !== null) {
                    $fundingRate = (float) $rate;
                }
                if ($ts !== null) {
                    $metricTime = Carbon::createFromTimestamp((int) $ts);
                }
            }
        } catch (\Throwable $e) {}

        try {
            $resp2 = Http::get('https://api.gateio.ws/api/v4/futures/usdt/contract_stats', [
                'contract' => $contract,
                'limit' => 1,
            ]);
            if ($resp2->ok()) {
                $json2 = $resp2->json();
                $row = is_array($json2) ? ($json2[0] ?? null) : null;
                if (is_array($row)) {
                    $oi = $row['open_interest'] ?? null;
                    $oiUsd = $row['open_interest_usd'] ?? null;
                    $ts2 = $row['time'] ?? null;
                    if ($oi !== null) {
                        $openInterest = (float) $oi;
                    }
                    if ($oiUsd !== null) {
                        $totalMarketValue = (float) $oiUsd;
                    }
                    if ($metricTime === null) {
                        $metricTime = $this->parseTs($ts2);
                    }
                }
            }
        } catch (\Throwable $e) {}

        return [
            'funding_rate' => $fundingRate,
            'open_interest' => $openInterest,
            'total_market_value' => $totalMarketValue,
            'metric_time' => $metricTime,
        ];
    }

    private function parseTs($ts): ?Carbon
    {
        if ($ts === null || $ts === '') {
            return null;
        }
        $n = (int) $ts;
        if ($n <= 0) {
            return null;
        }
        if ($n >= 100000000000) {
            return Carbon::createFromTimestampMs($n);
        }
        return Carbon::createFromTimestamp($n);
    }

    private function mapBingxSymbol(string $symbol): string
    {
        if (strtoupper($symbol) === 'BTCUSDT') return 'BTC-USDT';
        if (strtoupper($symbol) === 'ETHUSDT') return 'ETH-USDT';
        return $symbol;
    }

    private function mapOkxSymbol(string $symbol): string
    {
        if (strtoupper($symbol) === 'BTCUSDT') return 'BTC-USDT-SWAP';
        if (strtoupper($symbol) === 'ETHUSDT') return 'ETH-USDT-SWAP';
        return $symbol;
    }

    private function mapGateSymbol(string $symbol): string
    {
        if (strtoupper($symbol) === 'BTCUSDT') return 'BTC_USDT';
        if (strtoupper($symbol) === 'ETHUSDT') return 'ETH_USDT';
        return $symbol;
    }
}
