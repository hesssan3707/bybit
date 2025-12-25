<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Exchanges\ExchangeFactory;
use App\Models\UserExchange;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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


                $name = $defaultExchange->exchange_name;

                if ($name === 'bybit') {
                        // BYBIT (UNIFIED, USDT):
                        // موجودی کل (Total Equity) = equity (includes unrealized PnL)
                        // کیف پول (Wallet) = wallet balance = equity - unrealizedPnl
                        $balanceInfo = $exchangeService->getWalletBalance('UNIFIED', 'USDT');
                        $usdt = $balanceInfo['list'][0] ?? null;
                        if ($usdt) {
                            $equity = (float)($usdt['totalEquity'] ?? ($usdt['equity'] ?? ((($usdt['totalWalletBalance'] ?? ($usdt['walletBalance'] ?? 0))) + ($usdt['unrealizedPnl'] ?? 0))));
                            $unrealized = (float)($usdt['unrealizedPnl'] ?? 0);
                            $wallet = (float)($usdt['totalWalletBalance'] ?? ($usdt['walletBalance'] ?? ($equity - $unrealized)));
                            $totalEquity = number_format($equity, 2);
                            $totalBalance = number_format($wallet, 2);
                        }
                    } elseif ($name === 'binance') {
                        // BINANCE (FUTURES, USDT from /fapi/v2/balance):
                        // موجودی کل (Total Equity) = wallet + crossUnPnl (if available)
                        // کیف پول (Wallet) = wallet balance (balance/crossWalletBalance)
                        $balanceInfo = $exchangeService->getWalletBalance('FUTURES', 'USDT');
                        $usdtRow = $balanceInfo['list'][0] ?? null;
                        if ($usdtRow) {
                            $walletBase = (float)($usdtRow['crossWalletBalance'] ?? ($usdtRow['balance'] ?? 0));
                            $unpnl = (float)($usdtRow['crossUnPnl'] ?? 0);
                            $equity = $walletBase + $unpnl;
                            $wallet = (float)($usdtRow['crossWalletBalance'] ?? ($usdtRow['balance'] ?? ($usdtRow['availableBalance'] ?? ($usdtRow['maxWithdrawAmount'] ?? 0))));
                            $totalEquity = number_format($equity, 2);
                            $totalBalance = number_format($wallet, 2);
                        }
                    } elseif ($name === 'bingx') {
                        // BINGX (FUTURES, single object):
                        // موجودی کل (Total Equity) = equity (includes unrealized PnL)
                        // کیف پول (Wallet) = totalWalletBalance/walletBalance/availableBalance or equity - unrealizedPnl
                        $balanceInfo = $exchangeService->getWalletBalance('FUTURES');
                        $obj = $balanceInfo['list'][0] ?? null;
                        if ($obj) {
                            $equity = (float)($obj['equity'] ?? ((($obj['totalWalletBalance'] ?? ($obj['walletBalance'] ?? 0))) + ($obj['unrealizedPnl'] ?? 0)));
                            $unrealized = (float)($obj['unrealizedPnl'] ?? 0);
                            $wallet = (float)($obj['totalWalletBalance'] ?? ($obj['walletBalance'] ?? ($obj['availableBalance'] ?? ($equity - $unrealized))));
                            $totalEquity = number_format($equity, 2);
                            $totalBalance = number_format($wallet, 2);
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

                // }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Could not fetch wallet balance for profile: " . $e->getMessage());
            }
        }
        
        // Load all active exchanges for switching
        $activeExchanges = $user->activeExchanges()->get();
        
        $watchers = $user->watchers;

        return view('profile.index', [
            'user' => $user,
            'totalEquity' => $totalEquity,
            'totalBalance' => $totalBalance,
            'currentExchange' => $currentExchange,
            'activeExchanges' => $activeExchanges,
            'availableExchanges' => UserExchange::getAvailableExchanges(),
            'watchers' => $watchers,
        ]);
    }

    public function storeWatcher(Request $request)
    {
        $user = Auth::user();

        if ($user->watchers()->count() >= 3) {
            return redirect()->back()->with('error', 'شما حداکثر می‌توانید ۳ کاربر ناظر داشته باشید.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ], [
            'name.required' => 'نام الزامی است.',
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'ایمیل نامعتبر است.',
            'password.required' => 'رمز عبور الزامی است.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ]);

        $watcherEmail = 'Watcher-' . $request->email;

        if (User::where('email', $watcherEmail)->exists()) {
            return redirect()->back()->with('error', 'این ایمیل قبلاً به عنوان ناظر ثبت شده است.');
        }

        User::create([
            'name' => $request->name,
            'email' => $watcherEmail,
            'password' => Hash::make($request->password),
            'parent_id' => $user->id,
            'role' => 'watcher',
            'is_active' => true,
            'activated_at' => now(),
            'email_verified_at' => now(), // Watchers don't need verification
        ]);

        return redirect()->back()->with('success', 'کاربر ناظر با موفقیت ایجاد شد.');
    }

    public function deleteWatcher($id)
    {
        $user = Auth::user();
        $watcher = $user->watchers()->findOrFail($id);

        $watcher->delete();

        return redirect()->back()->with('success', 'کاربر ناظر با موفقیت حذف شد.');
    }
}
