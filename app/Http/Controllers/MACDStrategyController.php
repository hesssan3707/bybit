<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Price;
use App\Services\TechnicalAnalysisService;

class MACDStrategyController extends Controller
{
    protected $technicalAnalysisService;

    public function __construct(TechnicalAnalysisService $technicalAnalysisService)
    {
        $this->technicalAnalysisService = $technicalAnalysisService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->future_strict_mode) {
            return redirect()->route('profile.index')->with('error', 'You must have strict mode enabled to access this page.');
        }

        $timeframes = ['1m', '5m', '15m', '1h', '4h', '1d'];
        $altcoins = Price::where('market', '!=', 'BTCUSDT')
            ->where('market', '!=', 'ETHUSDT')
            ->distinct()
            ->pluck('market')
            ->toArray();

        $selectedAltcoin = $request->input('altcoin', $altcoins[0] ?? null);
        $baseMarket = $request->input('base_market', 'BTCUSDT');

        $comparisonData = [];

        foreach ($timeframes as $timeframe) {
            $altcoinPrices = Price::where('market', $selectedAltcoin)->where('timeframe', $timeframe)->orderBy('timestamp')->pluck('price')->toArray();
            $baseMarketPrices = Price::where('market', $baseMarket)->where('timeframe', $timeframe)->orderBy('timestamp')->pluck('price')->toArray();

            $altcoinMacdData = $this->calculateMACD($altcoinPrices);
            $baseMarketMacdData = $this->calculateMACD($baseMarketPrices);

            $trend = 'neutral';
            $trendPower = 0;
            if ($altcoinMacdData && $baseMarketMacdData) {
                if ($altcoinMacdData['normalized_macd'] > $baseMarketMacdData['normalized_macd']) {
                    $trend = 'up';
                } elseif ($altcoinMacdData['normalized_macd'] < $baseMarketMacdData['normalized_macd']) {
                    $trend = 'down';
                }
                $trendPower = $altcoinMacdData['histogram_value'];
            }

            $comparisonData[$timeframe] = [
                'altcoin' => $altcoinMacdData,
                'base' => $baseMarketMacdData,
                'trend' => $trend,
                'trend_power' => $trendPower,
            ];
        }

        return view('macd.index', [
            'comparisonData' => $comparisonData,
            'altcoins' => $altcoins,
            'selectedAltcoin' => $selectedAltcoin,
            'baseMarket' => $baseMarket,
            'timeframes' => $timeframes,
        ]);
    }

    private function calculateMACD(array $prices)
    {
        if (count($prices) < 34) { // Not enough data for MACD
            return null;
        }

        $macd = $this->technicalAnalysisService->macd($prices);
        if ($macd === null) {
            return null;
        }

        $macdLine = $macd['macd'];
        $signalLine = $macd['signal'];
        $histogram = $macd['histogram'];

        // Get the last values
        $lastMacd = end($macdLine);
        $lastSignal = end($signalLine);
        $lastHistogram = end($histogram);

        // Normalize the MACD values
        $max = max(max($macdLine), max($signalLine));
        $min = min(min($macdLine), min($signalLine));

        $normalizedMacd = $this->normalize($lastMacd, $min, $max);
        $normalizedSignal = $this->normalize($lastSignal, $min, $max);

        return [
            'macd' => $lastMacd,
            'signal' => $lastSignal,
            'histogram_value' => $lastHistogram,
            'normalized_macd' => $normalizedMacd,
            'normalized_signal' => $normalizedSignal,
        ];
    }

    private function normalize($value, $min, $max)
    {
        if ($max - $min == 0) {
            return 0; // Avoid division by zero
        }
        return ($value - $min) / ($max - $min);
    }
}
