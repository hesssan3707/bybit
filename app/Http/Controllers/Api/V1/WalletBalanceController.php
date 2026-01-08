<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletBalanceController extends Controller
{
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

    public function balance()
    {
        try {
            $user = auth()->user();
            $spot = ['balances' => [], 'total_equity' => 0, 'error' => null];
            $futures = ['balances' => [], 'total_equity' => 0, 'error' => null];

            if ($user->isInvestor()) {
                // Fetch investor specific balance from database
                $investorBalance = (float) (\Illuminate\Support\Facades\DB::table('investor_wallets')
                    ->where('investor_user_id', $user->id)
                    ->where('currency', 'USDT')
                    ->value('balance') ?? 0);

                $futures['total_equity'] = $investorBalance;
                $futures['balances'][] = [
                    'coin' => 'USDT',
                    'wallet_balance' => $investorBalance,
                    'available_to_withdraw' => $investorBalance,
                    'usd_value' => $investorBalance,
                ];

                // Spot is empty for investors
                $spot['total_equity'] = 0;
                $spot['balances'] = [];
            } else {
                $exchangeService = $this->getExchangeService();

                // Get Spot Balances
                try {
                    $spotBalanceData = $exchangeService->getSpotAccountBalance();
                    if (!empty($spotBalanceData['list'])) {
                        $account = $spotBalanceData['list'][0];
                        $spot['total_equity'] = (float)($account['totalEquity'] ?? 0);
                        if (isset($account['coin']) && is_array($account['coin'])) {
                            foreach ($account['coin'] as $coin) {
                                if ((float)$coin['walletBalance'] > 0) {
                                    $spot['balances'][] = [
                                        'currency' => $coin['coin'],
                                        'wallet_balance' => (float)$coin['walletBalance'],
                                        'usd_value' => isset($coin['usdValue']) ? (float)$coin['usdValue'] : null,
                                    ];
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to fetch spot balances for API: ' . $e->getMessage());
                    $spot['error'] = 'Failed to fetch spot balances: ' . $e->getMessage();
                }

                // Get Futures Balances
                try {
                    $futuresBalanceData = $exchangeService->getWalletBalance('UNIFIED', null);
                    if (!empty($futuresBalanceData['list'])) {
                        $account = $futuresBalanceData['list'][0];
                        $futures['total_equity'] = (float)($account['totalEquity'] ?? 0);
                        if (isset($account['coin']) && is_array($account['coin'])) {
                            foreach ($account['coin'] as $coin) {
                                if ((float)$coin['walletBalance'] > 0) {
                                    $futures['balances'][] = [
                                        'coin' => $coin['coin'],
                                        'wallet_balance' => (float)$coin['walletBalance'],
                                        'available_to_withdraw' => (float)($coin['availableToWithdraw'] ?? 0),
                                        'usd_value' => isset($coin['usdValue']) ? (float)$coin['usdValue'] : null,
                                    ];
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to fetch futures balances for API: ' . $e->getMessage());
                    $futures['error'] = 'Failed to fetch futures balances: ' . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'spot' => $spot,
                    'futures' => $futures,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to load balance for API: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
