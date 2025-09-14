<?php

namespace App\Http\Controllers;

use App\Models\Price;
use App\Services\TechnicalAnalysisService;
use Illuminate\Http\Request;

class MACDStrategyController extends Controller
{
    protected $technicalAnalysisService;

    public function __construct(TechnicalAnalysisService $technicalAnalysisService)
    {
        $this->middleware('auth');
        $this->technicalAnalysisService = $technicalAnalysisService;
    }

    public function index(Request $request)
    {
        $altcoins = [
            'CAKEUSDT', 'ATOMUSDT', 'SOLUSDT', 'ADAUSDT',
            'DOTUSDT', 'DOGEUSDT', 'SHIBUSDT', 'MATICUSDT', 'LTCUSDT', 'LINKUSDT',
            'UNIUSDT', 'AAVEUSDT', 'AVAXUSDT', 'FTMUSDT', 'NEARUSDT'
        ];
        $selectedAltcoin = $request->input('altcoin', 'SOLUSDT');
        $baseMarket = $request->input('base_market', 'BTCUSDT');
        $timeframes = ['1m', '5m', '15m', '1h', '4h', '1d'];
        $comparisonData = [];

        foreach ($timeframes as $timeframe) {
            $altcoinPrices = $this->getPricesForSymbol($selectedAltcoin, $timeframe);
            $baseMarketPrices = $this->getPricesForSymbol($baseMarket, $timeframe);

            $altcoinMacd = $this->technicalAnalysisService->calculateMACD($altcoinPrices);
            $baseMarketMacd = $this->technicalAnalysisService->calculateMACD($baseMarketPrices);

            $comparisonData[$timeframe] = $this->calculateTrend($altcoinMacd, $baseMarketMacd);
        }

        return view('macd.index', compact('comparisonData', 'altcoins', 'selectedAltcoin', 'baseMarket', 'timeframes'));
    }

    private function getPricesForSymbol(string $market, string $timeframe): array
    {
        return Price::where('market', $market)
            ->where('timeframe', $timeframe)
            ->orderBy('created_at', 'asc')
            ->pluck('price')
            ->toArray();
    }

    private function calculateTrend(?array $altcoinMacd, ?array $baseMarketMacd): array
    {
        $trend = 'neutral';
        $histogram_diff = 0;

        if ($altcoinMacd && $baseMarketMacd) {
            $histogram_diff = $altcoinMacd['normalized_histogram'] - $baseMarketMacd['normalized_histogram'];

            if ($altcoinMacd['normalized_macd'] > $baseMarketMacd['normalized_macd']) {
                $trend = $histogram_diff > 0.1 ? 'strong_up' : 'up';
            } else {
                $trend = $histogram_diff < -0.1 ? 'strong_down' : 'down';
            }
        }

        return [
            'altcoin' => $altcoinMacd,
            'base' => $baseMarketMacd,
            'trend' => $trend,
            'histogram_diff' => $histogram_diff,
        ];
    }
}
