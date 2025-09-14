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
        $markets = Price::distinct()->pluck('market')->toArray();

        $selectedMarket = $request->input('market', 'BTCUSDT');
        if (!in_array($selectedMarket, $markets)) {
            $selectedMarket = 'BTCUSDT';
        }

        $macdData = [];

        foreach ($timeframes as $timeframe) {
            $btcPrices = Price::where('market', 'BTCUSDT')->where('timeframe', $timeframe)->orderBy('timestamp')->pluck('price')->toArray();
            $ethPrices = Price::where('market', 'ETHUSDT')->where('timeframe', $timeframe)->orderBy('timestamp')->pluck('price')->toArray();
            $selectedMarketPrices = Price::where('market', $selectedMarket)->where('timeframe', $timeframe)->orderBy('timestamp')->pluck('price')->toArray();

            $macdData[$timeframe] = [
                'BTCUSDT' => $this->calculateMACD($btcPrices),
                'ETHUSDT' => $this->calculateMACD($ethPrices),
                $selectedMarket => $this->calculateMACD($selectedMarketPrices),
            ];
        }

        return view('macd.index', [
            'macdData' => $macdData,
            'markets' => $markets,
            'selectedMarket' => $selectedMarket,
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
            'histogram' => $lastHistogram,
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
