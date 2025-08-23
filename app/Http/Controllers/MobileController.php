<?php

namespace App\Http\Controllers;

use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileController extends Controller
{
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
     * Display mobile balance page with spot and perpetual balances
     */
    public function balance()
    {
        try {
            $exchangeService = $this->getExchangeService();
            
            // Get current exchange info
            $user = auth()->user();
            $currentExchange = $user->getCurrentExchange();
            $exchangeInfo = [
                'name' => $currentExchange->exchange_name ?? 'Unknown',
                'logo' => $this->getExchangeLogo($currentExchange->exchange_name ?? '')
            ];

            $spotBalances = [];
            $spotTotalEquity = 0;
            $spotError = null;

            $perpetualBalances = [];
            $perpetualTotalEquity = 0;
            $perpetualError = null;

            // Try to get spot balances
            try {
                $spotBalanceData = $exchangeService->getSpotAccountBalance();
                
                if (!empty($spotBalanceData['list'])) {
                    $account = $spotBalanceData['list'][0];
                    $spotTotalEquity = (float)($account['totalEquity'] ?? 0);
                    
                    if (isset($account['coin']) && is_array($account['coin'])) {
                        foreach ($account['coin'] as $coin) {
                            if ((float)$coin['walletBalance'] > 0) {
                                $spotBalances[] = [
                                    'currency' => $coin['coin'],
                                    'walletBalance' => (float)$coin['walletBalance'],
                                    'transferBalance' => (float)$coin['transferBalance'],
                                    'bonus' => (float)$coin['bonus'],
                                    'usdValue' => isset($coin['usdValue']) ? (float)$coin['usdValue'] : null,
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to fetch spot balances: ' . $e->getMessage());
                
                // Provide exchange-specific error messages
                $exchangeName = $currentExchange->exchange_name ?? 'Unknown';
                if (str_contains($e->getMessage(), 'not supported') || str_contains($e->getMessage(), 'Invalid')) {
                    $spotError = "صرافی {$exchangeName} از معاملات اسپات پشتیبانی نمی‌کند. به جای آن موجودی کل را از قسمت آتی مشاهده کنید.";
                } else {
                    $spotError = 'خطا در دریافت موجودی اسپات: ' . $e->getMessage();
                }
            }

            // Try to get perpetual/futures balances
            try {
                $perpetualBalanceData = $exchangeService->getWalletBalance('UNIFIED', null);
                
                if (!empty($perpetualBalanceData['list'])) {
                    $account = $perpetualBalanceData['list'][0];
                    $perpetualTotalEquity = (float)($account['totalEquity'] ?? 0);
                    
                    if (isset($account['coin']) && is_array($account['coin'])) {
                        foreach ($account['coin'] as $coin) {
                            if ((float)$coin['walletBalance'] > 0) {
                                $perpetualBalances[] = [
                                    'coin' => $coin['coin'],
                                    'walletBalance' => (float)$coin['walletBalance'],
                                    'availableToWithdraw' => (float)($coin['availableToWithdraw'] ?? 0),
                                    'usdValue' => isset($coin['usdValue']) ? (float)$coin['usdValue'] : null,
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to fetch perpetual balances: ' . $e->getMessage());
                
                $exchangeName = $currentExchange->exchange_name ?? 'Unknown';
                // If exchange doesn't support perpetual trading, show alternative
                if (str_contains($e->getMessage(), 'not supported') || str_contains($e->getMessage(), 'Invalid') || str_contains($e->getMessage(), 'category')) {
                    $perpetualError = "صرافی {$exchangeName} از معاملات آتی پشتیبانی نمی‌کند. فقط موجودی اسپات در دسترس است.";
                    
                    // For some exchanges, try to get the main wallet balance as alternative
                    try {
                        $mainBalanceData = $exchangeService->getAccountBalance();
                        if (!empty($mainBalanceData) && isset($mainBalanceData['list'])) {
                            $account = $mainBalanceData['list'][0] ?? $mainBalanceData;
                            $perpetualTotalEquity = (float)($account['totalEquity'] ?? $account['totalWalletBalance'] ?? 0);
                            
                            if (isset($account['coin']) && is_array($account['coin'])) {
                                foreach ($account['coin'] as $coin) {
                                    if ((float)($coin['walletBalance'] ?? $coin['free'] ?? 0) > 0) {
                                        $perpetualBalances[] = [
                                            'coin' => $coin['coin'] ?? $coin['asset'] ?? 'Unknown',
                                            'walletBalance' => (float)($coin['walletBalance'] ?? $coin['free'] ?? 0),
                                            'availableToWithdraw' => (float)($coin['availableToWithdraw'] ?? $coin['free'] ?? 0),
                                            'usdValue' => isset($coin['usdValue']) ? (float)$coin['usdValue'] : null,
                                        ];
                                    }
                                }
                            }
                            $perpetualError = "صرافی {$exchangeName} از معاملات آتی پشتیبانی نمی‌کند. موجودی کل نمایش داده شده است.";
                        }
                    } catch (\Exception $altException) {
                        Log::error('Failed to fetch alternative balance: ' . $altException->getMessage());
                    }
                } else {
                    $perpetualError = 'خطا در دریافت موجودی آتی: ' . $e->getMessage();
                }
            }

            return view('mobile.balance', compact(
                'exchangeInfo',
                'spotBalances',
                'spotTotalEquity',
                'spotError',
                'perpetualBalances',
                'perpetualTotalEquity',
                'perpetualError'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to load mobile balance page: ' . $e->getMessage());
            
            return view('mobile.balance', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get exchange logo emoji/icon
     */
    private function getExchangeLogo(string $exchangeName): string
    {
        $logos = [
            'bybit' => '🟡',
            'binance' => '🟨',
            'bingx' => '🔶',
        ];

        return $logos[strtolower($exchangeName)] ?? '🔹';
    }
}