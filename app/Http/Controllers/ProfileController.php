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

                // In local environment, skip calling exchanges entirely
                if (app()->environment('local')) {
                    $totalEquity = 'N/A';
                    $totalBalance = 'N/A';
                } else {
                    $name = $defaultExchange->exchange_name;

                    if ($name === 'bybit') {
                        // Prefer wallet balance (excludes unrealized PnL)
                        $balanceInfo = $exchangeService->getWalletBalance('UNIFIED', 'USDT');
                        $usdt = $balanceInfo['list'][0] ?? null;
                        if ($usdt) {
                            $equityExUPL = (float)($usdt['totalWalletBalance'] ?? ($usdt['availableBalance'] ?? ($usdt['walletBalance'] ?? 0)));
                            $totalEquity = number_format($equityExUPL, 2);
                            $totalBalance = number_format($equityExUPL, 2);
                        }
                    } elseif ($name === 'binance') {
                        // Binance futures: use USDT asset's balance/availableBalance (excludes unrealized PnL)
                        $balanceInfo = $exchangeService->getWalletBalance('FUTURES', 'USDT');
                        $usdtRow = $balanceInfo['list'][0] ?? null;
                        if ($usdtRow) {
                            $equityExUPL = (float)($usdtRow['balance'] ?? ($usdtRow['availableBalance'] ?? ($usdtRow['crossWalletBalance'] ?? ($usdtRow['maxWithdrawAmount'] ?? 0))));
                            $totalEquity = number_format($equityExUPL, 2);
                            $totalBalance = number_format($equityExUPL, 2);
                        }
                    } elseif ($name === 'bingx') {
                        // BingX futures: single object; prefer totalWalletBalance/availableBalance/walletBalance
                        $balanceInfo = $exchangeService->getWalletBalance('FUTURES');
                        $obj = $balanceInfo['list'][0] ?? null;
                        if ($obj) {
                            $equityExUPL = (float)($obj['totalWalletBalance'] ?? ($obj['availableBalance'] ?? ($obj['walletBalance'] ?? ($obj['equity'] ?? 0))));
                            $totalEquity = number_format($equityExUPL, 2);
                            $totalBalance = number_format($equityExUPL, 2);
                        }
                    } else {
                        // Fallback to generic if available
                        $account = $exchangeService->getAccountBalance();
                        if ($account && isset($account['success']) && $account['success']) {
                            if (isset($account['total'])) {
                                $totalEquity = number_format((float)$account['total'], 2);
                            }
                            if (isset($account['available'])) {
                                $totalBalance = number_format((float)$account['available'], 2);
                            }
                        }
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
