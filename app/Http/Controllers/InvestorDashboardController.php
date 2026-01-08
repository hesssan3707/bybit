<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InvestorDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if (!$user || !$user->isInvestor()) {
            return redirect()->route('dashboard')->with('error', 'دسترسی غیرمجاز.');
        }

        $settings = $user->investorSettings()->firstOrCreate([
            'user_id' => $user->id
        ], [
            'is_trading_enabled' => true,
            'allocation_percentage' => 100,
            'investment_limit' => null
        ]);

        $balance = DB::table('investor_wallets')
            ->where('investor_user_id', $user->id)
            ->where('currency', 'USDT')
            ->value('balance') ?? 0;

        return view('investor.dashboard', compact('user', 'settings', 'balance'));
    }

    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isInvestor()) {
            return abort(403);
        }

        $request->validate([
            'is_trading_enabled' => 'required|boolean',
            'allocation_percentage' => 'required|integer|min:0|max:100',
        ]);

        $user->investorSettings()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'is_trading_enabled' => $request->is_trading_enabled,
                'allocation_percentage' => $request->allocation_percentage,
            ]
        );

        return redirect()->back()->with('success', 'تنظیمات با موفقیت ذخیره شد.');
    }

    public function deposit(Request $request)
    {
        // Mock implementation
        return redirect()->back()->with('success', 'درخواست افزایش موجودی با موفقیت ثبت شد (آزمایشی).');
    }

    public function withdraw(Request $request)
    {
        // Mock implementation
        return redirect()->back()->with('success', 'درخواست برداشت وجه با موفقیت ثبت شد (آزمایشی).');
    }
}
