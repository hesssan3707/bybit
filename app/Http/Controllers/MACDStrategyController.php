<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Price;
use App\Models\User;

class MACDStrategyController extends Controller
{
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

        $macd = trader_macd($prices, 12, 26, 9);
        if ($macd === false) {
            return null;
        }

        $macdLine = $macd[0];
        $signalLine = $macd[1];
        $histogram = $macd[2];

        // Get the last values
        $lastMacd = end($macdLine);
        $lastSignal = end($signalLine);
        $lastHistogram = end($histogram);

        // Normalize the MACD values
        $max = max(max($macdLine), max($signalLine));
        $min = min(min($macdLine), min($signalLine));

        $normalizedMacd = $this->normalize(end($macdLine), $min, $max);
        $normalizedSignal = $this->normalize(end($signalLine), $min, $max);

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
