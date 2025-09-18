<?php

namespace App\Http\Controllers;

use App\Models\SpotOrder;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use App\Traits\HandlesExchangeAccess;
use App\Traits\ParsesExchangeErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SpotTradingController extends Controller
{
    use ParsesExchangeErrors;

    /**
     * Get the exchange service for the authenticated user
     */
    private function getExchangeService(): ExchangeApiServiceInterface
    {
        if (!auth()->check()) {
            throw new \Exception('User not authenticated');
        }

        try {
            return ExchangeFactory::createForUser(auth()->id());
        } catch (\Exception $e) {
            throw new \Exception('لطفاً ابتدا در صفحه پروفایل، صرافی مورد نظر خود را فعال کنید.');
        }
    }

    /**
     * Check if user has an active exchange and return status
     */
    private function checkActiveExchange()
    {
        try {
            $this->getExchangeService();
            return ['hasActiveExchange' => true, 'message' => null];
        } catch (\Exception $e) {
            return [
                'hasActiveExchange' => false, 
                'message' => 'برای استفاده از این قسمت، لطفاً ابتدا صرافی خود را فعال کنید.'
            ];
        }
    }

    /**
     * Round a number to the nearest step
     * 
     * @param float $number
     * @param float $step
     * @return float
     */
    private function roundToStep($number, $step)
    {
        if ($step == 0) {
            return $number;
        }
        
        return round($number / $step) * $step;
    }

    /**
     * Validate and adjust quantity to meet minimum requirements
     * 
     * @param float $qty
     * @param array $instrument
     * @return float
     * @throws \Exception
     */
    private function validateAndAdjustQuantity($qty, $instrument)
    {
        // Handle different data structures defensively
        $lotSizeFilter = $instrument['lotSizeFilter'] ?? [];
        $qtyStep = (float)($lotSizeFilter['qtyStep'] ?? $lotSizeFilter['basePrecision'] ?? 0.00000001);
        $minOrderQty = (float)($lotSizeFilter['minOrderQty'] ?? $lotSizeFilter['minQty'] ?? 0.00000001);
        
        // If we couldn't get proper values, throw descriptive error
        if ($qtyStep <= 0) {
            throw new \Exception("خطا در دریافت اطلاعات دقت مقدار از صرافی. لطفاً دوباره تلاش کنید.");
        }
        
        if ($minOrderQty <= 0) {
            throw new \Exception("خطا در دریافت حداقل مقدار مجاز از صرافی. لطفاً دوباره تلاش کنید.");
        }
        
        // Additional validation before processing
        if ($qty <= 0) {
            throw new \Exception("مقدار سفارش باید بزرگتر از صفر باشد. حداقل مجاز: {$minOrderQty}");
        }
        
        // If input quantity is already less than minimum, reject immediately
        if ($qty < $minOrderQty) {
            throw new \Exception("مقدار وارد شده ({$qty}) کمتر از حداقل مجاز است. حداقل: {$minOrderQty}");
        }
        
        // Round to step
        $adjustedQty = $this->roundToStep($qty, $qtyStep);
        
        // Ensure rounding didn't make it zero or negative
        if ($adjustedQty <= 0) {
            throw new \Exception("مقدار پس از تنظیم دقت صفر شد. مقدار ورودی: {$qty}, حداقل مجاز: {$minOrderQty}, دقت: {$qtyStep}");
        }
        
        // Final check after rounding
        if ($adjustedQty < $minOrderQty) {
            throw new \Exception("مقدار پس از تنظیم دقت ({$adjustedQty}) کمتر از حداقل مجاز است. حداقل: {$minOrderQty}");
        }
        
        // Additional safety check - ensure it's not effectively zero
        if ((string)$adjustedQty === '0' || (string)$adjustedQty === '0.0' || (string)$adjustedQty === '0.00000000') {
            throw new \Exception("مقدار نهایی صفر محاسبه شد. مقدار ورودی: {$qty}, حداقل مجاز: {$minOrderQty}");
        }
        
        return $adjustedQty;
    }

    // Web View Methods

    /**
     * Display spot orders list view
     */
    public function spotOrdersView(Request $request)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        
        // Get current exchange to filter by account type
        $user = auth()->user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        
        $ordersQuery = SpotOrder::forUser(auth()->id());
        
        // Filter by current account type (demo/real) if exchange is available
        if ($currentExchange) {
            $ordersQuery->accountType($currentExchange->is_demo_active);
        }
        
        $orders = $ordersQuery->latest('created_at')->paginate(20);

        return view('spot.orders', [
            'orders' => $orders,
            'hasActiveExchange' => $exchangeStatus['hasActiveExchange'],
            'exchangeMessage' => $exchangeStatus['message']
        ]);
    }

    /**
     * Display spot balances view
     */
    public function spotBalancesView()
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        
        if (!$exchangeStatus['hasActiveExchange']) {
            return view('spot.balances', [
                'balances' => [],
                'totalEquity' => 0,
                'totalWalletBalance' => 0,
                'hasActiveExchange' => false,
                'exchangeMessage' => $exchangeStatus['message'],
                'error' => null
            ]);
        }
        
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $balanceData = $exchangeService->getSpotAccountBalance();
            
            $balances = [];
            if (!empty($balanceData['list'])) {
                $account = $balanceData['list'][0];
                $totalEquity = (float)($account['totalEquity'] ?? 0);
                $totalWalletBalance = (float)($account['totalWalletBalance'] ?? 0);
                
                if (isset($account['coin']) && is_array($account['coin'])) {
                    foreach ($account['coin'] as $coin) {
                        if ((float)$coin['walletBalance'] > 0) {
                            $balances[] = [
                                'currency' => $coin['coin'],
                                'walletBalance' => (float)$coin['walletBalance'],
                                'transferBalance' => (float)($coin['transferBalance'] ?? $coin['walletBalance']),
                                'bonus' => (float)($coin['bonus'] ?? 0),
                                'usdValue' => isset($coin['usdValue']) ? (float)$coin['usdValue'] : null,
                            ];
                        }
                    }
                }
                
                return view('spot.balances', [
                    'balances' => $balances,
                    'totalEquity' => $totalEquity,
                    'totalWalletBalance' => $totalWalletBalance,
                    'hasActiveExchange' => true,
                    'exchangeMessage' => null,
                    'error' => null
                ]);
            }
            
            return view('spot.balances', [
                'balances' => [],
                'totalEquity' => 0,
                'totalWalletBalance' => 0,
                'hasActiveExchange' => true,
                'exchangeMessage' => null,
                'error' => 'No balance data available'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch spot balances for view: ' . $e->getMessage());
            
            return view('spot.balances', [
                'balances' => [],
                'totalEquity' => 0,
                'totalWalletBalance' => 0,
                'hasActiveExchange' => true,
                'exchangeMessage' => null,
                'error' => 'Failed to fetch balance data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Display create spot order form
     */
    public function createSpotOrderView()
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        
        if (!$exchangeStatus['hasActiveExchange']) {
            return view('spot.create_order', [
                'tradingPairs' => [],
                'favoriteMarkets' => [],
                'hasActiveExchange' => false,
                'exchangeMessage' => $exchangeStatus['message'],
                'error' => null
            ]);
        }
        
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            // Get available trading pairs
            $pairsData = $exchangeService->getSpotInstrumentsInfo();
            $tradingPairs = [];
            
            if (isset($pairsData['list']) && is_array($pairsData['list'])) {
                foreach ($pairsData['list'] as $instrument) {
                    if ($instrument['status'] === 'Trading') {
                        $tradingPairs[] = [
                            'symbol' => $instrument['symbol'],
                            'baseCoin' => $instrument['baseCoin'],
                            'quoteCoin' => $instrument['quoteCoin'],
                        ];
                    }
                }
            }
            
            // Get favorite markets (popular/high volume pairs)
            $favoriteMarkets = $this->getFavoriteMarkets($tradingPairs);
            
            return view('spot.create_order', [
                'tradingPairs' => $tradingPairs,
                'favoriteMarkets' => $favoriteMarkets,
                'hasActiveExchange' => true,
                'exchangeMessage' => null,
                'error' => null
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch trading pairs for create order view: ' . $e->getMessage());
            
            return view('spot.create_order', [
                'tradingPairs' => [],
                'favoriteMarkets' => [],
                'hasActiveExchange' => true,
                'exchangeMessage' => null,
                'error' => 'Failed to fetch trading pairs: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle spot order creation from web form
     */
    public function storeSpotOrderFromWeb(Request $request)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return back()->withErrors(['msg' => $exchangeStatus['message']])->withInput();
        }
        
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $validated = $request->validate([
                'side' => 'required|string|in:Buy,Sell',
                'symbol' => 'required|string',
                'orderType' => 'required|string|in:Market,Limit',
                'qty' => 'required|numeric|min:0.00000001',
                'price' => 'nullable|numeric|min:0.00000001',
                'timeInForce' => 'nullable|string|in:GTC,IOC,FOK',
            ]);

            // Validate that price is provided for limit orders
            if ($validated['orderType'] === 'Limit' && !isset($validated['price'])) {
                return back()->withErrors(['price' => 'Price is required for Limit orders'])->withInput();
            }

            // Use the same logic as API method
            $instrumentInfo = $exchangeService->getSpotInstrumentsInfo($validated['symbol']);
            
            if (empty($instrumentInfo['list'])) {
                return back()->withErrors(['symbol' => 'Invalid trading symbol: ' . $validated['symbol']])->withInput();
            }

            $instrument = $instrumentInfo['list'][0];
            
            $lotSizeFilter = $instrument['lotSizeFilter'] ?? [];
            $priceFilter = $instrument['priceFilter'] ?? [];
            $qtyStep = (float)($lotSizeFilter['qtyStep'] ?? $lotSizeFilter['basePrecision'] ?? 0.00000001);
            $priceStep = (float)($priceFilter['tickSize'] ?? $priceFilter['quotePrecision'] ?? 0.01);
            
            // Validate and adjust quantity to meet minimum requirements
            $qty = $this->validateAndAdjustQuantity($validated['qty'], $instrument);

            $orderParams = [
                'side' => $validated['side'],
                'symbol' => $validated['symbol'],
                'orderType' => $validated['orderType'],
                'qty' => $qty,
                'orderLinkId' => (string) Str::uuid(),
            ];

            if ($validated['orderType'] === 'Limit') {
                $price = $this->roundToStep($validated['price'], $priceStep);
                $orderParams['price'] = $price;
            }

            if (isset($validated['timeInForce'])) {
                $orderParams['timeInForce'] = $validated['timeInForce'];
            }

            $result = $exchangeService->createSpotOrder($orderParams);

            // Save to database (same logic as API method)
            $baseCoin = substr($orderParams['symbol'], 0, -4);
            $quoteCoin = substr($orderParams['symbol'], -4);
            
            if (str_ends_with($orderParams['symbol'], 'USDC')) {
                $baseCoin = substr($orderParams['symbol'], 0, -4);
                $quoteCoin = 'USDC';
            } elseif (str_ends_with($orderParams['symbol'], 'BTC')) {
                $baseCoin = substr($orderParams['symbol'], 0, -3);
                $quoteCoin = 'BTC';
            } elseif (str_ends_with($orderParams['symbol'], 'ETH')) {
                $baseCoin = substr($orderParams['symbol'], 0, -3);
                $quoteCoin = 'ETH';
            }

            // Get user's current active exchange ID
            $user = auth()->user();
            $currentExchange = $user->currentExchange ?? $user->defaultExchange;
            if (!$currentExchange) {
                return back()->withErrors(['msg' => 'لطفاً ابتدا در صفحه پروفایل، صرافی مورد نظر خود را فعال کنید.'])->withInput();
            }

            SpotOrder::create([
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

            return redirect()->route('spot.orders.view')
                ->with('success', 'Spot order created successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Spot order creation from web failed: ' . $e->getMessage());
            
            // Parse exchange error message for user-friendly response
            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());
            
            return back()->withErrors(['error' => $userFriendlyMessage])->withInput();
        }
    }

    /**
     * Get favorite/popular markets based on common trading pairs
     * Since we can't get actual favorites from exchange, we'll use popular pairs
     */
    private function getFavoriteMarkets(array $tradingPairs): array
    {
        // Define popular trading pairs that users typically favor
        $popularSymbols = [
            'BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'ADAUSDT', 'XRPUSDT', 
            'SOLUSDT', 'DOGEUSDT', 'DOTUSDT', 'LINKUSDT', 'LTCUSDT',
            'MATICUSDT', 'AVAXUSDT', 'UNIUSDT', 'ATOMUSDT', 'FILUSDT'
        ];
        
        $favorites = [];
        
        // Filter trading pairs to get only the popular ones that are available
        foreach ($popularSymbols as $symbol) {
            foreach ($tradingPairs as $pair) {
                if ($pair['symbol'] === $symbol) {
                    $favorites[] = $pair;
                    break;
                }
            }
        }
        
        return $favorites;
    }

    /**
     * Cancel spot order from web interface
     */
    public function cancelSpotOrderFromWeb(Request $request)
    {
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $validated = $request->validate([
                'orderId' => 'required|string',
                'symbol' => 'required|string',
            ]);

            // Cancel order via exchange API
            $result = $exchangeService->cancelSpotOrderWithSymbol(
                $validated['orderId'],
                $validated['symbol']
            );

            // Update order status in local database
            $spotOrder = SpotOrder::where('order_id', $validated['orderId'])
                                ->where('symbol', $validated['symbol'])
                                ->forUser(auth()->id())
                                ->first();

            if ($spotOrder) {
                $spotOrder->update([
                    'status' => 'Cancelled',
                    'updated_at' => now()
                ]);
            }

            return redirect()->route('spot.orders.view')
                ->with('success', 'سفارش با موفقیت لغو شد.');

        } catch (\Exception $e) {
            Log::error('Cancel spot order from web failed: ' . $e->getMessage());
            
            // Parse error for user-friendly message
            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());
            
            return back()->withErrors(['error' => $userFriendlyMessage]);
        }
    }
}