<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\UserExchange;
use App\Models\UserPeriod;
use Illuminate\Support\Collection;

class JournalPeriodService
{
    /**
     * Public wrapper to compute metrics for a given period, exchange filter, and side.
     *
     * @param UserPeriod $period
     * @param array<int>|null $userExchangeIds
     * @param 'all'|'buy'|'sell' $side
     */
    public function computeMetricsFor(UserPeriod $period, ?array $userExchangeIds, string $side): array
    {
        return $this->computeMetrics($period, $userExchangeIds, $side);
    }
    public function ensureDefaultPeriod(int $userId, bool $isDemo): ?UserPeriod
    {
        $existingActiveDefault = UserPeriod::forUser($userId)
            ->accountType($isDemo)
            ->default()
            ->orderBy('started_at', 'desc')
            ->first();

        if (!$existingActiveDefault) {
            $firstClosedTrade = Trade::whereHas('userExchange', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->where('is_demo', $isDemo)
                ->whereNotNull('closed_at')
                ->orderBy('closed_at', 'asc')
                ->first();

            if (!$firstClosedTrade) {
                // No closed trades yet; we cannot determine a period start anchored to trading.
                return null;
            }

            // Ensure default name uniqueness across all periods of this account type
            $baseName = '۱ ساله';
            $existingCount = UserPeriod::forUser($userId)
                ->accountType($isDemo)
                ->where('name', 'like', $baseName . '%')
                ->count();
            $uniqueName = $existingCount > 0 ? ($baseName . ' (' . ($existingCount + 1) . ')') : $baseName;

            $period = new UserPeriod([
                'user_id' => $userId,
                'is_demo' => $isDemo,
                'name' => $uniqueName,
                'started_at' => $firstClosedTrade->closed_at,
                'ended_at' => $firstClosedTrade->closed_at->copy()->addYear(),
                'is_default' => true,
                'is_active' => true,
            ]);
            $period->save();
            $this->updatePeriodMetrics($period);
            return $period;
        }

        // If default period has passed, auto start new 1-year period
        if ($existingActiveDefault->ended_at && now()->gte($existingActiveDefault->ended_at)) {
            // Mark previous default as inactive
            $existingActiveDefault->is_active = false;
            $existingActiveDefault->save();

            $start = $existingActiveDefault->ended_at->copy();

            // Ensure default name uniqueness when auto-starting new default
            $baseName = '۱ ساله';
            $existingCount = UserPeriod::forUser($userId)
                ->accountType($isDemo)
                ->where('name', 'like', $baseName . '%')
                ->count();
            $uniqueName = $existingCount > 0 ? ($baseName . ' (' . ($existingCount + 1) . ')') : $baseName;

            $period = new UserPeriod([
                'user_id' => $userId,
                'is_demo' => $isDemo,
                'name' => $uniqueName,
                'started_at' => $start,
                'ended_at' => $start->copy()->addYear(),
                'is_default' => true,
                'is_active' => true,
            ]);
            $period->save();
            $this->updatePeriodMetrics($period);
            return $period;
        }

        return $existingActiveDefault;
    }

    public function updatePeriodMetrics(UserPeriod $period): void
    {
        $period->metrics_all = $this->computeMetrics($period, null, 'all');
        $period->metrics_buy = $this->computeMetrics($period, null, 'buy');
        $period->metrics_sell = $this->computeMetrics($period, null, 'sell');

        $period->exchange_metrics = $this->computeExchangeMetrics($period);
        $period->save();
    }

    protected function computeExchangeMetrics(UserPeriod $period): array
    {
        $exchanges = UserExchange::where('user_id', $period->user_id)->get();
        $grouped = [];
        foreach ($exchanges as $ue) {
            $grouped[$ue->exchange_name] = $grouped[$ue->exchange_name] ?? [];
            $grouped[$ue->exchange_name][] = $ue->id;
        }

        $result = [];
        foreach ($grouped as $exchangeName => $ids) {
            $result[$exchangeName] = [
                'all' => $this->computeMetrics($period, $ids, 'all'),
                'buy' => $this->computeMetrics($period, $ids, 'buy'),
                'sell' => $this->computeMetrics($period, $ids, 'sell'),
            ];
        }
        return $result;
    }

    /**
     * @param UserPeriod $period
     * @param array<int>|null $userExchangeIds
     * @param 'all'|'buy'|'sell' $side
     */
    protected function computeMetrics(UserPeriod $period, ?array $userExchangeIds, string $side): array
    {
        $query = Trade::query()
            ->where('is_demo', $period->is_demo)
            ->whereNotNull('closed_at')
            ->where('synchronized', 1)
            ->when($period->ended_at, function ($q) use ($period) {
                $q->whereBetween('closed_at', [$period->started_at, $period->ended_at]);
            }, function ($q) use ($period) {
                $q->where('closed_at', '>=', $period->started_at);
            })
            ->when($userExchangeIds, function ($q) use ($userExchangeIds) {
                $q->whereIn('user_exchange_id', $userExchangeIds);
            })
            ->when($side !== 'all', function ($q) use ($side) {
                $q->where('side', $side);
            })
            ->whereHas('userExchange', function ($q) use ($period) {
                $q->where('user_id', $period->user_id);
            })
            ->with(['order', 'userExchange'])
            ->orderBy('closed_at', 'asc');

        /** @var Collection<int, Trade> $trades */
        $trades = $query->get();

        $tradeCount = $trades->count();
        $totalPnl = (float) $trades->sum('pnl');
        $profits = (float) $trades->where('pnl', '>', 0)->sum('pnl');
        $losses = (float) $trades->where('pnl', '<', 0)->sum('pnl');
        $biggestProfit = (float) ($trades->max('pnl') ?? 0);
        $biggestLoss = (float) ($trades->min('pnl') ?? 0);
        // Clamp per requested logic: do not consider positive numbers as losses or negative numbers as profits
        if ($biggestLoss > 0) { $biggestLoss = 0.0; }
        if ($biggestProfit < 0) { $biggestProfit = 0.0; }
        $wins = $trades->where('pnl', '>', 0)->count();
        $lossesCount = $trades->where('pnl', '<', 0)->count();

        // Risk and RRR estimates based on order data when available
        $risks = [];
        $rrrs = [];
        foreach ($trades as $t) {
            $order = $t->order; // may be null
            if ($order && $order->entry_price) {
                $entry = (float) $order->entry_price;
                $sl = (float) ($order->sl ?? 0.0);
                $tp = (float) ($order->tp ?? 0.0);

                if ($entry > 0 && $sl > 0) {
                    $risk = abs($entry - $sl) / $entry * 100.0;
                    $risks[] = $risk;
                }

                $slDistance = abs($entry - $sl);
                if ($slDistance > 0) {
                    $tpDistance = abs($tp - $entry);
                    $rrr = $tpDistance / $slDistance;
                    $rrrs[] = $rrr;
                }
            }
        }
        $avgRisk = count($risks) ? array_sum($risks) / count($risks) : 0.0;
        $avgRRR = count($rrrs) ? array_sum($rrrs) / count($rrrs) : 0.0;

        // Prepare chart data
        $pnlSeries = [];
        $percentSeries = [];
        $cumPnlSeries = [];
        $cumPnlPercentSeries = [];

        $cum = 0.0;
        $initialCapital = 0.0;
        if ($tradeCount > 0) {
            $firstOrder = $trades->first()->order;
            $initialCapital = $firstOrder && $firstOrder->balance_at_creation ? (float) $firstOrder->balance_at_creation : 1.0;
        } else {
            $initialCapital = 1.0;
        }

        foreach ($trades as $idx => $t) {
            $cum += (float) $t->pnl;
            $pnlSeries[] = [
                'x' => 'ترید ' . ($idx + 1),
                'y' => (float) $t->pnl,
                'date' => optional($t->closed_at)->format('Y-m-d'),
            ];
            $cumPnlSeries[] = [
                'x' => 'ترید ' . ($idx + 1),
                'y' => $cum,
                'date' => optional($t->closed_at)->format('Y-m-d'),
            ];
            $capital = $t->order && $t->order->balance_at_creation ? (float) $t->order->balance_at_creation : $initialCapital;
            $percent = $capital > 0 ? ((float)$t->pnl / $capital) * 100.0 : 0.0;
            $percentSeries[] = [
                'x' => 'ترید ' . ($idx + 1),
                'y' => $percent,
                'date' => optional($t->closed_at)->format('Y-m-d'),
            ];
            $cumPnlPercentSeries[] = [
                'x' => 'ترید ' . ($idx + 1),
                'y' => $capital > 0 ? ($cum / $capital) * 100.0 : 0.0,
                'date' => optional($t->closed_at)->format('Y-m-d'),
            ];
        }

        return [
            'trade_count' => $tradeCount,
            'total_pnl' => round($totalPnl, 8),
            'profits_sum' => round($profits, 8),
            'losses_sum' => round($losses, 8),
            'biggest_profit' => round($biggestProfit, 8),
            'biggest_loss' => round($biggestLoss, 8),
            'wins' => $wins,
            'losses' => $lossesCount,
            'avg_risk_percent' => round($avgRisk, 4),
            'avg_rrr' => round($avgRRR, 6),
            'pnl_per_trade' => $pnlSeries,
            'per_trade_percent' => $percentSeries,
            'cum_pnl' => $cumPnlSeries,
            'cum_pnl_percent' => $cumPnlPercentSeries,
        ];
    }
}