<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Price;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceController extends Controller
{
    /**
     * Get prices for specified currencies at different time intervals.
     * 
     * Returns:
     * - Latest price
     * - Price 15 minutes ago
     * - Price 1 hour ago
     * - Price 4 hours ago
     * - Price 1 day ago
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $inputCurrencies = $request->input('currencies');
        
        if (is_string($inputCurrencies)) {
            $inputCurrencies = explode(',', $inputCurrencies);
        }
        if (!is_array($inputCurrencies)) {
            $inputCurrencies = [];
        }

        // Normalize input currencies to market names (e.g. BTC -> BTCUSDT)
        $requestedMarkets = array_map(function($currency) {
            $currency = strtoupper(trim($currency));
            return str_ends_with($currency, 'USDT') ? $currency : $currency . 'USDT';
        }, array_filter($inputCurrencies));

        // Get all existing markets from DB
        $existingMarkets = Price::distinct()->pluck('market')->toArray();

        // Identify missing markets that user requested but we don't have
        $missingMarkets = array_diff($requestedMarkets, $existingMarkets);

        // Fetch and store missing markets from Binance
        foreach ($missingMarkets as $market) {
            $price = $this->fetchBinancePrice($market);
            if ($price !== null) {
                Price::create([
                    'market' => $market,
                    'timeframe' => '1m', // Default timeframe
                    'price' => $price,
                    'timestamp' => Carbon::now(),
                ]);
            }
        }

        // Final list of markets to return: All existing DB markets + requested markets (which are now in DB if valid)
        // Re-fetch existing markets in case we just added some, or just merge arrays
        // Actually, "Return all currencies that already exist... plus any... we don't have yet".
        // This effectively means: All unique markets from (DB + Request).
        $allMarkets = array_unique(array_merge($existingMarkets, $requestedMarkets));
        sort($allMarkets);

        $results = [];

        foreach ($allMarkets as $market) {
            $currency = str_replace('USDT', '', $market); // Simple display name

            // Define time points
            $now = Carbon::now();
            $timePoints = [
                'latest' => $now,
                '15m_ago' => $now->copy()->subMinutes(15),
                '1h_ago' => $now->copy()->subHour(),
                '4h_ago' => $now->copy()->subHours(4),
                '1d_ago' => $now->copy()->subDay(),
            ];

            $currencyData = [
                'currency' => $currency,
                'market' => $market,
                'prices' => []
            ];

            foreach ($timePoints as $label => $time) {
                $query = Price::where('market', $market);
                
                if ($label === 'latest') {
                    $priceRecord = $query->orderBy('timestamp', 'desc')->first();
                } else {
                    // Find the closest record BEFORE or AT the target time
                    $priceRecord = $query->where('timestamp', '<=', $time)
                        ->orderBy('timestamp', 'desc')
                        ->first();
                    
                    // Validation: Check if the found record is "close enough" to the target time
                    // If the gap is too large, it means we don't have accurate data for that specific time point.
                    // For example, if we want 4h ago, but the closest record is from 2 days ago, we should return null.
                    
                    if ($priceRecord) {
                        $recordTime = Carbon::parse($priceRecord->timestamp);
                        $diffInMinutes = $recordTime->diffInMinutes($time);
                        
                        // Define tolerance threshold based on timeframe
                        // 15m ago -> tolerance 5 mins
                        // 1h ago -> tolerance 10 mins
                        // 4h ago -> tolerance 30 mins
                        // 1d ago -> tolerance 2 hours
                        
                        $tolerance = 0;
                        if ($label === '15m_ago') $tolerance = 10;
                        elseif ($label === '1h_ago') $tolerance = 20;
                        elseif ($label === '4h_ago') $tolerance = 60; // 1 hour tolerance
                        elseif ($label === '1d_ago') $tolerance = 180; // 3 hours tolerance
                        
                        if ($diffInMinutes > $tolerance) {
                            $priceRecord = null; // Discard invalid/stale data
                        }
                    }
                }

                $currencyData['prices'][$label] = $priceRecord ? (float) $priceRecord->price : null;
            }
            
            $results[$currency] = $currencyData;
        }

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Fetch latest price from Binance API
     * 
     * @param string $symbol
     * @return float|null
     */
    private function fetchBinancePrice($symbol)
    {
        try {
            // Binance public API endpoint for ticker price
            $response = Http::get("https://api.binance.com/api/v3/ticker/price", [
                'symbol' => $symbol
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['price']) ? (float) $data['price'] : null;
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch Binance price for {$symbol}: " . $e->getMessage());
        }

        return null;
    }
}
