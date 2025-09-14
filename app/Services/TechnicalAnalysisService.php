<?php

namespace App\Services;

class TechnicalAnalysisService
{
    /**
     * Calculate the Simple Moving Average (SMA).
     *
     * @param array $data
     * @param int $period
     * @return array
     */
    public function sma(array $data, int $period): array
    {
        $sma = [];
        $dataCount = count($data);
        for ($i = $period - 1; $i < $dataCount; $i++) {
            $sum = 0;
            for ($j = 0; $j < $period; $j++) {
                $sum += $data[$i - $j];
            }
            $sma[] = $sum / $period;
        }
        return $sma;
    }

    /**
     * Calculate the Exponential Moving Average (EMA).
     *
     * @param array $data
     * @param int $period
     * @return array
     */
    public function ema(array $data, int $period): array
    {
        $ema = [];
        $multiplier = 2 / ($period + 1);
        $initialSma = $this->sma(array_slice($data, 0, $period), $period);
        $ema[] = $initialSma[0];

        $dataCount = count($data);
        for ($i = $period; $i < $dataCount; $i++) {
            $emaValue = ($data[$i] - end($ema)) * $multiplier + end($ema);
            $ema[] = $emaValue;
        }

        return $ema;
    }

    /**
     * Calculate the Moving Average Convergence Divergence (MACD).
     *
     * @param array $data
     * @param int $fastPeriod
     * @param int $slowPeriod
     * @param int $signalPeriod
     * @return array|null
     */
    public function macd(array $data, int $fastPeriod = 12, int $slowPeriod = 26, int $signalPeriod = 9): ?array
    {
        $dataCount = count($data);
        if ($dataCount < $slowPeriod) {
            return null;
        }

        $emaFast = $this->ema($data, $fastPeriod);
        $emaSlow = $this->ema($data, $slowPeriod);

        $macdLine = [];
        $emaFastSlice = array_slice($emaFast, $slowPeriod - $fastPeriod);

        foreach ($emaSlow as $key => $value) {
            $macdLine[] = $emaFastSlice[$key] - $value;
        }

        if (count($macdLine) < $signalPeriod) {
            return null;
        }

        $signalLine = $this->ema($macdLine, $signalPeriod);

        $histogram = [];
        $macdLineSlice = array_slice($macdLine, $signalPeriod - 1);

        foreach ($signalLine as $key => $value) {
            $histogram[] = $macdLineSlice[$key] - $value;
        }

        return [
            'macd' => array_slice($macdLineSlice, -count($histogram)),
            'signal' => $signalLine,
            'histogram' => $histogram,
        ];
    }
}
