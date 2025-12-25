<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SpotOrder;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use App\Traits\ParsesExchangeErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SpotTradingController extends Controller
{
    use ParsesExchangeErrors;
    private function getExchangeService(): ExchangeApiServiceInterface
    {
        if (!auth()->check()) {
            throw new \Exception('User not authenticated');
        }

        try {
            return ExchangeFactory::createForUser(auth()->id());
        } catch (\Exception $e) {
            throw new \Exception('Please activate your desired exchange on your profile page first.');
        }
    }

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'symbol' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $symbol = $validated['symbol'] ?? null;
            $limit = $validated['limit'] ?? 20;

            $query = SpotOrder::forUser($request->user()->id);

            // Filter by current account type (demo/real)
            $user = $request->user();
            $currentExchange = $user->getCurrentExchange();
            if ($currentExchange) {
                $query->accountType($currentExchange->is_demo_active);
            }

            if ($symbol) {
                $query->where('symbol', $symbol);
            }

            $orders = $query->latest('created_at')
                          ->limit($limit)
                          ->get();

            return response()->json(['success' => true, 'data' => $orders]);

        } catch (\Exception $e) {
            Log::error('Get spot order history failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get order history: ' . $e->getMessage()], 500);
        }
    }

    public function show(SpotOrder $spotOrder)
    {
        // Load the user_exchange relationship to avoid N+1 queries
        $spotOrder->load('userExchange');
        
        if ($spotOrder->userExchange->user_id !== auth()->user()->getAccountOwner()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'data' => $spotOrder]);
    }

    public function store(Request $request)
    {
        try {
            $exchangeService = $this->getExchangeService();

            $validated = $request->validate([
                'side' => 'required|string|in:Buy,Sell',
                'symbol' => 'required|string',
                'orderType' => 'required|string|in:Market,Limit',
                'qty' => 'required|numeric|min:0.00000001',
                'price' => 'nullable|numeric|min:0.00000001',
                'timeInForce' => 'nullable|string|in:GTC,IOC,FOK',
            ]);

            if ($validated['orderType'] === 'Limit' && !isset($validated['price'])) {
                return response()->json(['success' => false, 'message' => 'Price is required for Limit orders'], 400);
            }

            $instrumentInfo = $exchangeService->getSpotInstrumentsInfo($validated['symbol']);

            if (empty($instrumentInfo['list'])) {
                return response()->json(['success' => false, 'message' => 'Invalid trading symbol: ' . $validated['symbol']], 400);
            }

            $instrument = $instrumentInfo['list'][0];
            $qty = $this->validateAndAdjustQuantity($validated['qty'], $instrument);

            $orderParams = [
                'side' => $validated['side'],
                'symbol' => $validated['symbol'],
                'orderType' => $validated['orderType'],
                'qty' => $qty,
                'orderLinkId' => (string) Str::uuid(),
            ];

            if ($validated['orderType'] === 'Limit') {
                $priceFilter = $instrument['priceFilter'] ?? [];
                $priceStep = (float)($priceFilter['tickSize'] ?? $priceFilter['quotePrecision'] ?? 0.01);
                $price = $this->roundToStep($validated['price'], $priceStep);
                $orderParams['price'] = $price;
            }

            if (isset($validated['timeInForce'])) {
                $orderParams['timeInForce'] = $validated['timeInForce'];
            }

            $result = $exchangeService->createSpotOrder($orderParams);

            $baseCoin = substr($orderParams['symbol'], 0, strpos($orderParams['symbol'], 'USDT'));
            $quoteCoin = 'USDT';

            $user = $request->user();
            $currentExchange = $user->getCurrentExchange();

            $spotOrder = SpotOrder::create([
                'user_exchange_id' => $currentExchange->id,
                'is_demo' => $currentExchange->is_demo_active,
                'order_id' => $result['orderId'] ?? null,
                'order_link_id' => $orderParams['orderLinkId'],
                'symbol' => $orderParams['symbol'],
                'base_coin' => $baseCoin,
                'quote_coin' => $quoteCoin,
                'side' => $orderParams['side'],
                'order_type' => $orderParams['orderType'],
                'qty' => $qty,
                'price' => $orderParams['price'] ?? null,
                'time_in_force' => $orderParams['timeInForce'] ?? 'GTC',
                'status' => 'New',
                'order_created_at' => now(),
                'raw_response' => $result,
            ]);

            return response()->json(['success' => true, 'message' => 'Spot order created successfully', 'data' => $spotOrder]);

        } catch (\Exception $e) {
            Log::error('Spot order creation failed: ' . $e->getMessage());
            
            // Parse exchange error message for user-friendly response
            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());
            
            return response()->json(['success' => false, 'message' => $userFriendlyMessage], 500);
        }
    }

    public function destroy(SpotOrder $spotOrder)
    {
        // Load the user_exchange relationship to avoid N+1 queries
        $spotOrder->load('userExchange');
        
        if ($spotOrder->userExchange->user_id !== auth()->user()->getAccountOwner()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $exchangeService = $this->getExchangeService();
            $exchangeService->cancelSpotOrderWithSymbol($spotOrder->order_id, $spotOrder->symbol);
            $spotOrder->update(['status' => 'Cancelled']);
            return response()->json(['success' => true, 'message' => 'Spot order cancelled successfully.']);
        } catch (\Exception $e) {
            Log::error('Spot order cancellation failed: ' . $e->getMessage());
            
            // Parse exchange error message for user-friendly response
            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());
            
            return response()->json(['success' => false, 'message' => $userFriendlyMessage], 500);
        }
    }

    private function roundToStep($number, $step)
    {
        if ($step == 0) {
            return $number;
        }

        return round($number / $step) * $step;
    }

    private function validateAndAdjustQuantity($qty, $instrument)
    {
        $lotSizeFilter = $instrument['lotSizeFilter'] ?? [];
        $qtyStep = (float)($lotSizeFilter['qtyStep'] ?? $lotSizeFilter['basePrecision'] ?? 0.00000001);
        $minOrderQty = (float)($lotSizeFilter['minOrderQty'] ?? $lotSizeFilter['minQty'] ?? 0.00000001);

        if ($qty < $minOrderQty) {
            throw new \Exception("Quantity ({$qty}) is less than the minimum allowed ({$minOrderQty}).");
        }

        return $this->roundToStep($qty, $qtyStep);
    }
}
