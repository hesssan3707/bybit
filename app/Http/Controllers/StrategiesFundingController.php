<?php

namespace App\Http\Controllers;

use App\Models\FuturesFundingSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StrategiesFundingController extends Controller
{
    public function index(Request $request)
    {
        $exchanges = ['bybit', 'binance', 'bingx', 'okx', 'bitget', 'gate'];
        $symbols = ['BTCUSDT', 'ETHUSDT'];

        $now = Carbon::now();
        $from = $now->copy()->subDays(3);

        $latest = [];

        foreach ($exchanges as $exchange) {
            foreach ($symbols as $symbol) {
                $latestSnapshot = FuturesFundingSnapshot::where('exchange', $exchange)
                    ->where('symbol', $symbol)
                    ->orderByDesc('metric_time')
                    ->orderByDesc('id')
                    ->first();

                if ($latestSnapshot) {
                    $latest[$exchange][$symbol] = $latestSnapshot;
                }
            }
        }

        $history = FuturesFundingSnapshot::whereBetween('metric_time', [$from, $now])
            ->whereIn('exchange', $exchanges)
            ->whereIn('symbol', $symbols)
            ->orderBy('metric_time')
            ->orderBy('id')
            ->get();

        $analysis = $this->analyzeMarket($latest, $symbols, $exchanges);

        return view('strategies.funding_overview', [
            'latest' => $latest,
            'history' => $history,
            'analysis' => $analysis,
            'exchanges' => $exchanges,
            'symbols' => $symbols,
        ]);
    }

    private function analyzeMarket(array $latest, array $symbols, array $exchanges): array
    {
        $levels = [];
        $aggregates = [];

        foreach ($latest as $exchange => $exchangeSymbols) {
            foreach ($exchangeSymbols as $symbol => $snapshot) {
                $funding = $snapshot->funding_rate;
                $oi = $snapshot->open_interest;
                $absFunding = $funding !== null ? abs((float) $funding) : null;

                $level = 'normal';

                if ($absFunding !== null) {
                    if ($absFunding > 0.0005) {
                        $level = 'critical';
                    } elseif ($absFunding > 0.0002) {
                        $level = 'elevated';
                    }
                }

                if ($level === 'normal' && $oi !== null) {
                    if ((float) $oi > 0) {
                        $level = 'elevated';
                    }
                }

                $levels[$exchange][$symbol] = [
                    'level' => $level,
                    'funding' => $funding,
                    'open_interest' => $oi,
                ];
            }
        }

        foreach ($symbols as $sym) {
            $rates = [];
            $ois = [];
            foreach ($exchanges as $ex) {
                if (isset($latest[$ex][$sym])) {
                    $r = $latest[$ex][$sym]->funding_rate;
                    $o = $latest[$ex][$sym]->open_interest;
                    if ($r !== null) { $rates[] = (float) $r; }
                    if ($o !== null) { $ois[] = (float) $o; }
                }
            }
            $avgFunding = !empty($rates) ? array_sum($rates) / count($rates) : null;
            $sumOi = !empty($ois) ? array_sum($ois) : null;
            $aggregates[$sym] = [
                'avg_funding_rate' => $avgFunding,
                'sum_open_interest' => $sumOi,
                'funding_count' => count($rates),
                'oi_count' => count($ois),
            ];
        }

        $worstLevel = 'normal';

        foreach ($levels as $exchangeLevels) {
            foreach ($exchangeLevels as $entry) {
                if ($entry['level'] === 'critical') {
                    $worstLevel = 'critical';
                    break 2;
                }
                if ($entry['level'] === 'elevated' && $worstLevel === 'normal') {
                    $worstLevel = 'elevated';
                }
            }
        }

        $message = 'شرایط بازار بر اساس داده‌های فعلی نسبتاً عادی است.';

        if ($worstLevel === 'elevated') {
            $message = 'در بخشی از بازار، ترکیب فاندینگ و اوپن اینترست نشان‌دهنده افزایش ریسک و شلوغی معاملات است. بهتر است اندازه پوزیشن‌ها را با احتیاط انتخاب کنید.';
        } elseif ($worstLevel === 'critical') {
            $message = 'بازار آتی در حال حاضر در وضعیت پرریسک قرار دارد؛ فاندینگ و اوپن اینترست در برخی نمادها در سطوح غیرعادی هستند. در این شرایط، ورود با اهرم بالا می‌تواند بسیار خطرناک باشد.';
        }

        return [
            'levels' => $levels,
            'worst_level' => $worstLevel,
            'message' => $message,
            'aggregates' => $aggregates,
        ];
    }
}
