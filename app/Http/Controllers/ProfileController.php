<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Exchanges\ExchangeFactory;
use App\Models\UserExchange;

class ProfileController extends Controller
{
    protected $exchangeFactory;

    public function __construct(ExchangeFactory $exchangeFactory)
    {
        $this->exchangeFactory = $exchangeFactory;
    }

    public function index()
    {
        $user = Auth::user();
        $totalEquity = 'N/A';
        $totalBalance = 'N/A';
        $currentExchange = null;
        $activeExchanges = [];
        
        // Load user's default/active exchange
        $defaultExchange = $user->defaultExchange;
        
        if ($defaultExchange) {
            $currentExchange = $defaultExchange;
            
            try {
                $exchangeService = $this->exchangeFactory->createForUserExchange($defaultExchange);
                
                if ($defaultExchange->exchange_name === 'bybit') {
                    $balanceInfo = $exchangeService->getWalletBalance('UNIFIED', 'USDT');
                    $usdtBalanceData = $balanceInfo['list'][0] ?? null;
                    if ($usdtBalanceData) {
                        if (isset($usdtBalanceData['totalEquity'])) {
                            $totalEquity = number_format((float)$usdtBalanceData['totalEquity'], 2);
                        }
                        if (isset($usdtBalanceData['totalWalletBalance'])) {
                            $totalBalance = number_format((float)$usdtBalanceData['totalWalletBalance'], 2);
                        }
                    }
                } else {
                    // For other exchanges, use generic balance method when implemented
                    $balance = $exchangeService->getAccountBalance();
                    if ($balance && isset($balance['total'])) {
                        $totalEquity = number_format((float)$balance['total'], 2);
                        $totalBalance = number_format((float)$balance['available'], 2);
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Could not fetch wallet balance for profile: " . $e->getMessage());
            }
        }
        
        // Load all active exchanges for switching
        $activeExchanges = $user->activeExchanges()->get();
        
        return view('profile.index', [
            'user' => $user,
            'totalEquity' => $totalEquity,
            'totalBalance' => $totalBalance,
            'currentExchange' => $currentExchange,
            'activeExchanges' => $activeExchanges,
            'availableExchanges' => UserExchange::getAvailableExchanges(),
        ]);
    }
}
