<?php

namespace App\Http\Controllers;

use App\Models\FuturesFundingSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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

        $fundingSeries = [];
        $oiSeries = [];

        foreach ($history as $row) {
            $ts = $row->metric_time ? $row->metric_time->getTimestampMs() : null;
            if ($ts === null) {
                continue;
            }
            $name = strtoupper((string) $row->exchange) . ' ' . (string) $row->symbol;
            if (!isset($fundingSeries[$name])) {
                $fundingSeries[$name] = [];
            }
            if (!isset($oiSeries[$name])) {
                $oiSeries[$name] = [];
            }

            if ($row->funding_rate !== null) {
                $fundingSeries[$name][] = ['x' => $ts, 'y' => (float) $row->funding_rate * 100.0];
            }
            if ($row->open_interest !== null) {
                $oiSeries[$name][] = ['x' => $ts, 'y' => (float) $row->open_interest];
            }
        }

        $fundingSeriesList = [];
        foreach ($fundingSeries as $name => $data) {
            if (!empty($data)) {
                $fundingSeriesList[] = ['name' => $name, 'data' => $data];
            }
        }

        $oiSeriesList = [];
        foreach ($oiSeries as $name => $data) {
            if (!empty($data)) {
                $oiSeriesList[] = ['name' => $name, 'data' => $data];
            }
        }

        $analysis = $this->analyzeMarket($latest, $history, $symbols, $exchanges);

        try {
            if (($analysis['worst_level'] ?? null) === 'critical') {
                Cache::put('market:risk', 'risky', now()->addMinutes(15));
                Cache::put('market:risk_level', 'critical', now()->addMinutes(15));
                Cache::put('market:risk_message', $analysis['message'], now()->addMinutes(15));
                Cache::put('market:risk_updated_at', now()->toIso8601String(), now()->addMinutes(15));
            } else {
                Cache::forget('market:risk');
                Cache::forget('market:risk_level');
                Cache::forget('market:risk_message');
                Cache::forget('market:risk_updated_at');
            }
        } catch (\Throwable $e) {
        }

        return view('strategies.funding_overview', [
            'latest' => $latest,
            'history' => $history,
            'fundingSeries' => $fundingSeriesList,
            'oiSeries' => $oiSeriesList,
            'analysis' => $analysis,
            'exchanges' => $exchanges,
            'symbols' => $symbols,
        ]);
    }

    private function analyzeMarket(array $latest, $history, array $symbols, array $exchanges): array
    {
        $levels = [];
        $aggregates = [];

        $oiStats = [];
        foreach ($history as $row) {
            if ($row->open_interest === null) {
                continue;
            }
            $ex = (string) $row->exchange;
            $sym = (string) $row->symbol;
            if (!isset($oiStats[$ex][$sym])) {
                $oiStats[$ex][$sym] = ['sum' => 0.0, 'count' => 0];
            }
            $oiStats[$ex][$sym]['sum'] += (float) $row->open_interest;
            $oiStats[$ex][$sym]['count'] += 1;
        }

        $fundingElevatedThreshold = 0.0002;
        $fundingCriticalThreshold = 0.0005;
        $oiMinSamples = 12;
        $oiElevatedRatio = 1.4;
        $oiCriticalRatio = 1.8;

        foreach ($latest as $exchange => $exchangeSymbols) {
            foreach ($exchangeSymbols as $symbol => $snapshot) {
                $funding = $snapshot->funding_rate;
                $oi = $snapshot->open_interest;
                $absFunding = $funding !== null ? abs((float) $funding) : null;

                $level = 'normal';

                if ($absFunding !== null) {
                    if ($absFunding > $fundingCriticalThreshold) {
                        $level = 'critical';
                    } elseif ($absFunding > $fundingElevatedThreshold) {
                        $level = 'elevated';
                    }
                }

                if ($oi !== null) {
                    $stats = $oiStats[$exchange][$symbol] ?? null;
                    $count = (int) ($stats['count'] ?? 0);
                    $avg = $count > 0 ? ((float) ($stats['sum'] ?? 0.0) / $count) : null;
                    if ($avg !== null && $avg > 0 && $count >= $oiMinSamples) {
                        $ratio = ((float) $oi) / $avg;
                        if ($ratio >= $oiCriticalRatio) {
                            $level = 'critical';
                        } elseif ($ratio >= $oiElevatedRatio && $level === 'normal') {
                            $level = 'elevated';
                        }
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
