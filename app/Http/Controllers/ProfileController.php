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
        $investorBalance = 0.0;
        
        // Load user's default/active exchange
        $defaultExchange = $user->defaultExchange;
        
        if ($user->isInvestor()) {
            $investorBalance = (float) (DB::table('investor_wallets')
                ->where('investor_user_id', $user->id)
                ->where('currency', 'USDT')
                ->value('balance') ?? 0);

            $totalEquity = number_format($investorBalance, 2);
            $totalBalance = number_format($investorBalance, 2);

            $currentExchange = $user->getCurrentExchange();
        } elseif ($defaultExchange) {
            $currentExchange = $defaultExchange;
            
            try {
                $exchangeService = $this->exchangeFactory->createForUserExchange($defaultExchange);


                $name = $defaultExchange->exchange_name;
                $equityNumeric = null;
                $walletNumeric = null;

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
                            $equityNumeric = $equity;
                            $walletNumeric = $wallet;
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
                            $equityNumeric = $equity;
                            $walletNumeric = $wallet;
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
                            $equityNumeric = $equity;
                            $walletNumeric = $wallet;
                        }
                    } else {
                        // Fallback to generic if available
                        $account = $exchangeService->getAccountBalance();
                        if ($account && isset($account['success']) && $account['success']) {
                            if (isset($account['total'])) {
                                $equityNumeric = (float) $account['total'];
                            }
                            if (isset($account['available'])) {
                                $walletNumeric = (float) $account['available'];
                            }
                        }
                    }

                if ($equityNumeric !== null || $walletNumeric !== null) {
                    $investorTotal = (float) (DB::table('users')
                        ->join('investor_wallets', 'users.id', '=', 'investor_wallets.investor_user_id')
                        ->where('users.parent_id', $user->id)
                        ->where('users.role', 'investor')
                        ->where('investor_wallets.currency', 'USDT')
                        ->sum('investor_wallets.balance') ?? 0);

                    if ($equityNumeric !== null) {
                        $equityNumeric = max(0.0, $equityNumeric - $investorTotal);
                    }
                    if ($walletNumeric !== null) {
                        $walletNumeric = max(0.0, $walletNumeric - $investorTotal);
                    }

                    if ($equityNumeric !== null) {
                        $totalEquity = number_format($equityNumeric, 2);
                    }
                    if ($walletNumeric !== null) {
                        $totalBalance = number_format($walletNumeric, 2);
                    }
                }

                // }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Could not fetch wallet balance for profile: " . $e->getMessage());
            }
        }
        
        // Load all active exchanges for switching
        $activeExchanges = $user->activeExchanges()->get();
        
        $investors = $user->investors()->get();
        $investorBalances = [];
        if (!$user->isInvestor() && $investors->isNotEmpty()) {
            $investorBalances = DB::table('investor_wallets')
                ->whereIn('investor_user_id', $investors->pluck('id')->all())
                ->where('currency', 'USDT')
                ->pluck('balance', 'investor_user_id')
                ->toArray();
        }

        return view('profile.index', [
            'user' => $user,
            'totalEquity' => $totalEquity,
            'totalBalance' => $totalBalance,
            'currentExchange' => $currentExchange,
            'activeExchanges' => $activeExchanges,
            'availableExchanges' => UserExchange::getAvailableExchanges(),
            'investors' => $investors,
            'investorBalances' => $investorBalances,
        ]);
    }

    public function storeInvestor(Request $request)
    {
        $user = Auth::user();

        if ($user->investors()->count() >= 3) {
            return redirect()->back()->with('error', 'شما حداکثر می‌توانید ۳ کاربر سرمایه‌گذار داشته باشید.');
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

        $realEmail = strtolower(trim($request->email));
        $realEmail = preg_replace('/^investor\s*-\s*/i', '', $realEmail);
        $realEmail = preg_replace('/^watcher\s*-\s*/i', '', $realEmail);
        $investorEmail = 'investor-' . $realEmail;

        $legacyInvestorEmails = [
            $investorEmail,
            'Investor-' . $realEmail,
            'Investor - ' . $realEmail,
            'watcher-' . $realEmail,
            'Watcher-' . $realEmail,
            'Watcher - ' . $realEmail,
        ];

        if (User::whereIn('email', $legacyInvestorEmails)->exists()) {
            return redirect()->back()->with('error', 'این ایمیل قبلاً به عنوان سرمایه‌گذار ثبت شده است.');
        }

        $investor = User::create([
            'name' => $request->name,
            'username' => $investorEmail,
            'email' => $investorEmail,
            'password' => Hash::make($request->password),
            'parent_id' => $user->id,
            'role' => 'investor',
            'is_active' => true,
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);

        DB::table('investor_wallets')->updateOrInsert(
            [
                'investor_user_id' => $investor->id,
                'currency' => 'USDT',
            ],
            [
                'balance' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return redirect()->back()->with('success', 'کاربر سرمایه‌گذار با موفقیت ایجاد شد.');
    }

    public function updateInvestor(Request $request, $id)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|min:8',
        ], [
            'name.required' => 'نام الزامی است.',
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'ایمیل نامعتبر است.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ]);

        $investor = $user->investors()->findOrFail($id);

        $realEmail = strtolower(trim($request->email));
        $realEmail = preg_replace('/^investor\s*-\s*/i', '', $realEmail);
        $realEmail = preg_replace('/^watcher\s*-\s*/i', '', $realEmail);
        $investorEmail = 'investor-' . $realEmail;

        $legacyInvestorEmails = [
            $investorEmail,
            'Investor-' . $realEmail,
            'Investor - ' . $realEmail,
            'watcher-' . $realEmail,
            'Watcher-' . $realEmail,
            'Watcher - ' . $realEmail,
        ];

        if ($investor->email !== $investorEmail && User::whereIn('email', $legacyInvestorEmails)->exists()) {
            return redirect()->back()->with('error', 'این ایمیل قبلاً به عنوان سرمایه‌گذار ثبت شده است.');
        }

        $update = [
            'name' => $request->name,
            'email' => $investorEmail,
            'username' => $investorEmail,
        ];

        if ($request->filled('password')) {
            $update['password'] = Hash::make($request->password);
        }

        $investor->update($update);

        return redirect()->back()->with('success', 'اطلاعات سرمایه‌گذار با موفقیت به‌روزرسانی شد.');
    }

    public function deleteInvestor($id)
    {
        $user = Auth::user();
        $investor = $user->investors()->findOrFail($id);

        $investor->delete();

        return redirect()->back()->with('success', 'کاربر سرمایه‌گذار با موفقیت حذف شد.');
    }

    public function storeWatcher(Request $request)
    {
        return $this->storeInvestor($request);
    }

    public function updateWatcher(Request $request, $id)
    {
        return $this->updateInvestor($request, $id);
    }

    public function deleteWatcher($id)
    {
        return $this->deleteInvestor($id);
    }

    public function updateName(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
        ], [
            'name.required' => 'نام الزامی است.',
        ]);

        $user->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'name' => $user->name,
        ]);
    }
}
