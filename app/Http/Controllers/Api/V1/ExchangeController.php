<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExchangeController extends Controller
{
    public function index()
    {
        $exchanges = UserExchange::forUser(auth()->id())->get();
        return response()->json(['success' => true, 'data' => $exchanges]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exchange_name' => 'required|string|in:bybit,bingx,binance',
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
            'password' => 'nullable|string',
        ]);

        $exchange = UserExchange::create([
            'user_id' => auth()->id(),
            'exchange_name' => $validated['exchange_name'],
            'api_key' => $validated['api_key'],
            'api_secret' => $validated['api_secret'],
            'password' => $validated['password'] ?? null,
            'is_active' => false,
        ]);

        return response()->json(['success' => true, 'message' => 'Exchange created successfully.', 'data' => $exchange]);
    }

    public function update(Request $request, UserExchange $exchange)
    {
        if ($exchange->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
            'password' => 'nullable|string',
        ]);

        $exchange->update($validated);

        return response()->json(['success' => true, 'message' => 'Exchange updated successfully.', 'data' => $exchange]);
    }

    public function destroy(UserExchange $exchange)
    {
        if ($exchange->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $exchange->delete();

        return response()->json(['success' => true, 'message' => 'Exchange deleted successfully.']);
    }

    public function switchTo(UserExchange $exchange)
    {
        if ($exchange->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            auth()->user()->exchanges()->update(['is_active' => false]);
            $exchange->update(['is_active' => true]);
            
            // Always switch to hedge mode when switching exchanges
            $exchangeService = \App\Services\Exchanges\ExchangeFactory::createForUserExchange($exchange);
            $exchangeService->switchPositionMode(true);
            
            return response()->json(['success' => true, 'message' => "Switched to {$exchange->exchange_name} successfully and set to hedge mode."]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Exchange switch failed in API', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to switch exchange or set hedge mode.'], 500);
        }
    }

    public function testConnection(UserExchange $exchange)
    {
        if ($exchange->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $exchangeService = ExchangeFactory::create($exchange->exchange_name, $exchange->api_key, $exchange->api_secret, $exchange->password);
            $balance = $exchangeService->getWalletBalance('UNIFIED', 'USDT');
            if (isset($balance['list'][0])) {
                return response()->json(['success' => true, 'message' => 'Connection successful.']);
            } else {
                return response()->json(['success' => false, 'message' => 'Connection failed: Could not retrieve balance.']);
            }
        } catch (\Exception $e) {
            Log::error('Exchange connection test failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()], 500);
        }
    }
}
