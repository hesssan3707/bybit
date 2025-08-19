<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\BybitApiService;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        $this->bybitApiService = $bybitApiService;
    }

    public function index()
    {
        $user = Auth::user();
        $totalEquity = 'N/A';
        $totalBalance = 'N/A';

        try {
            $balanceInfo = $this->bybitApiService->getWalletBalance('UNIFIED', 'USDT');
            $usdtBalanceData = $balanceInfo['list'][0] ?? null;
            if ($usdtBalanceData) {
                if (isset($usdtBalanceData['totalEquity'])) {
                    $totalEquity = number_format((float)$usdtBalanceData['totalEquity'], 2);
                }
                if (isset($usdtBalanceData['totalWalletBalance'])) {
                    $totalBalance = number_format((float)$usdtBalanceData['totalWalletBalance'], 2);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Could not fetch Bybit wallet balance for profile: " . $e->getMessage());
        }

        return view('profile.index', [
            'user' => $user,
            'totalEquity' => $totalEquity,
            'totalBalance' => $totalBalance,
        ]);
    }
}
