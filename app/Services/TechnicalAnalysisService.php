<?php

namespace App\Services;

class TechnicalAnalysisService
{
    public function calculateMACD(array $closePrices, int $fastPeriod = 12, int $slowPeriod = 26, int $signalPeriod = 9)
    {
        $prices = collect($closePrices);

        if ($prices->count() < $slowPeriod) {
            return null;
        }

        $fastEma = $this->calculateEMA($prices, $fastPeriod);
        $slowEma = $this->calculateEMA($prices, $slowPeriod);

        $macdLine = $fastEma->slice($slowPeriod - $fastPeriod)->values()
            ->map(function ($item, $key) use ($slowEma) {
                return $item - $slowEma[$key];
            });

        if ($macdLine->count() < $signalPeriod) {
            return null;
        }

        $signalLine = $this->calculateEMA($macdLine, $signalPeriod);
        $histogram = $macdLine->slice($signalPeriod - 1)->values()
            ->map(function ($item, $key) use ($signalLine) {
                return $item - $signalLine[$key];
            });

        $lastMacd = $macdLine->last();
        $lastHistogram = $histogram->last();

        $price = $prices->last();
        $normalizedMacd = $price > 0 ? ($lastMacd / $price) * 100 : 0;
        $normalizedHistogram = $price > 0 ? ($lastHistogram / $price) * 100 : 0;

        return [
            'normalized_macd' => $normalizedMacd,
            'normalized_histogram' => $normalizedHistogram,
        ];
    }

    private function calculateEMA(\Illuminate\Support\Collection $prices, int $period)
    {
        $ema = collect();
        $multiplier = 2 / ($period + 1);
        $initialSma = $prices->slice(0, $period)->avg();
        $ema->push($initialSma);

        for ($i = $period; $i < $prices->count(); $i++) {
            $currentPrice = $prices[$i];
            $previousEma = $ema->last();
            $currentEma = ($currentPrice - $previousEma) * $multiplier + $previousEma;
            $ema->push($currentEma);
        }

        return $ema;
    }
}
