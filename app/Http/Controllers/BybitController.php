<?php

namespace App\Http\Controllers;

use App\Models\BybitOrders;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BybitController extends Controller
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('BYBIT_API_KEY');
        $this->apiSecret = env('BYBIT_API_SECRET');
        $isTestnet = env('BYBIT_TESTNET', false);
        $this->baseUrl = $isTestnet ? 'https://api-testnet.bybit.com' : 'https://api.bybit.com';
    }

    private function generateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->apiSecret);
    }

    public function store(Request $request)
    {
        // 1. Validate Input
        $validated = $request->validate([
            'entry1' => 'required|numeric',
            'entry2' => 'required|numeric',
            'tp'     => 'required|numeric',
            'sl'     => 'required|numeric',
            'steps'  => 'required|integer|min:1',
            'expire' => 'required|integer|min:1',
        ]);

        // 2. Business Logic
        $symbol = 'ETHUSDT';
        $steps  = $validated['steps'];
        $entry1 = (float) $validated['entry1'];
        $entry2 = (float) $validated['entry2'];
        if ($entry2 < $entry1) { [$entry1, $entry2] = [$entry2, $entry1]; }

        $avgEntry = ($entry1 + $entry2) / 2.0;
        $side = ($validated['sl'] > $avgEntry) ? 'Sell' : 'Buy';

        $capitalUSD = (float) env('TRADING_CAPITAL_USD', 1000);
        $maxLossUSD = $capitalUSD * 0.10;
        $slDistance = abs($avgEntry - (float) $validated['sl']);

        if ($slDistance <= 0) {
            return back()->withErrors(['sl' => 'SL must be different from the entry price.'])->withInput();
        }
        $amount = $maxLossUSD / $slDistance;

        // 3. Get Market Precision via V5 API
        try {
            $response = Http::get("{$this->baseUrl}/v5/market/instruments-info", ['category' => 'linear', 'symbol' => $symbol]);
            if ($response->failed() || $response->json('retCode') !== 0) {
                throw new \Exception('Failed to fetch instrument info: ' . $response->json('retMsg'));
            }
            $instrumentInfo = $response->json('result.list.0');
            $amountPrec = strlen(substr(strrchr($instrumentInfo['lotSizeFilter']['qtyStep'], "."), 1));
            $pricePrec = (int) $instrumentInfo['priceScale'];
            $amount = round($amount, $amountPrec);
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => 'Error getting market info from Bybit (V5): ' . $e->getMessage()])->withInput();
        }

        // 4. Create Orders via V5 API
        $amountPerStep = round($amount / $steps, $amountPrec);
        $stepSize = ($steps > 1) ? (($entry2 - $entry1) / ($steps - 1)) : 0;
        $recvWindow = 5000;

        try {
            foreach (range(0, $steps - 1) as $i) {
                $price = round($entry1 + ($stepSize * $i), $pricePrec);

                $timestamp = intval(microtime(true) * 1000);
                $params = [
                    'category' => 'linear',
                    'symbol' => $symbol,
                    'side' => $side,
                    'orderType' => 'Limit',
                    'qty' => (string)$amountPerStep,
                    'price' => (string)$price,
                    'timeInForce' => 'GTC',
                ];
                $jsonPayload = json_encode($params);
                $payloadToSign = $timestamp . $this->apiKey . $recvWindow . $jsonPayload;
                $signature = $this->generateSignature($payloadToSign);

                $response = Http::withHeaders([
                    'X-BAPI-API-KEY' => $this->apiKey,
                    'X-BAPI-SIGN' => $signature,
                    'X-BAPI-TIMESTAMP' => $timestamp,
                    'X-BAPI-RECV-WINDOW' => $recvWindow,
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/v5/order/create", $params);

                $responseData = $response->json();
                if ($response->failed() || $responseData['retCode'] !== 0) {
                    throw new \Exception("Failed to create order for price {$price}: " . $responseData['retMsg']);
                }

                BybitOrders::create([
                    'order_id'       => $responseData['result']['orderId'] ?? null,
                    'symbol'         => $symbol,
                    'entry_price'    => $price,
                    'tp'             => (float)$validated['tp'],
                    'sl'             => (float)$validated['sl'],
                    'steps'          => $steps,
                    'expire_minutes' => (int)$validated['expire'],
                    'status'         => 'pending',
                    'side'           => strtolower($side),
                    'amount'         => $amountPerStep,
                    'entry_low'      => $entry1,
                    'entry_high'     => $entry2,
                ]);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => 'Error creating order with Bybit (V5): ' . $e->getMessage()])->withInput();
        }

        return back()->with('success', "{$steps} order(s) successfully created using V5 API.");
    }

}
