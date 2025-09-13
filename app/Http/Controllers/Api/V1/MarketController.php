<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarketController extends Controller
{
    public function getBestPrice(Request $request)
    {
        $validated = $request->validate([
            'markets' => 'required|array',
            'markets.*' => 'required|string',
            'type' => 'required|string|in:spot,futures',
            'side' => 'required|string|in:buy,sell'
        ]);

        $markets = $validated['markets'];
        $type = $validated['type'];
        $side = $validated['side'];
        $results = [];

        $exchanges = UserExchange::forUser(auth()->id())->where('is_active', true)->get();

        if ($exchanges->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No active exchanges found for the user.'], 404);
        }

        foreach ($markets as $market) {
            $bestPrice = null;
            $bestExchange = null;

            foreach ($exchanges as $exchange) {
                try {
                    $exchangeService = ExchangeFactory::create($exchange->exchange_name, $exchange->api_key, $exchange->api_secret, $exchange->password);

                    if ($type === 'spot') {
                        $tickerInfo = $exchangeService->getTickerInfo($market, 'spot');
                    } else {
                        $tickerInfo = $exchangeService->getTickerInfo($market, 'linear');
                    }

                    if (isset($tickerInfo['list'][0]['lastPrice'])) {
                        $price = (float)$tickerInfo['list'][0]['lastPrice'];
                        if (is_null($bestPrice) || ($side === 'buy' && $price < $bestPrice) || ($side === 'sell' && $price > $bestPrice)) {
                            $bestPrice = $price;
                            $bestExchange = $exchange->exchange_name;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Could not fetch price for {$market} on {$exchange->exchange_name}: " . $e->getMessage());
                }
            }

            if (!is_null($bestPrice)) {
                $results[] = [
                    'market' => $market,
                    'best_price' => $bestPrice,
                    'exchange' => $bestExchange,
                ];
            } else {
                $results[] = [
                    'market' => $market,
                    'error' => 'Could not find a price for this market on any of your active exchanges.',
                ];
            }
        }

        return response()->json(['success' => true, 'data' => $results]);
    }
}
