<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BybitOrders;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ccxt\bybit;

class BybitController extends Controller
{
    public function store(Request $request)
    {
        // بررسی محدودیت زمانی بعد از استاپ‌لاس خوردن
        $lastStopLoss = BybitOrders::where('status', 'stopped') // اینو باید مطابق ساختار دیتابیس خودت بگذاری
            ->orderBy('closed_at', 'desc')
            ->first();

        if ($lastStopLoss && Carbon::parse($lastStopLoss->closed_at)->gt(Carbon::now()->subHour())) {
            return back()->withErrors(['msg' => 'به دلیل بسته شدن آخرین معامله با ضرر، تا 1 ساعت آینده امکان ثبت سفارش جدید وجود ندارد.']);
        }
        $validated = $request->validate([
            'entry1' => 'required|numeric',
            'entry2' => 'required|numeric',
            'tp'     => 'required|numeric',
            'sl'     => 'required|numeric',
            'steps'  => 'nullable|integer|min:1',
            'expire' => 'required|integer|min:1',
        ]);

        require_once base_path('vendor/autoload.php');

        $apiKey    = env('BYBIT_API_KEY');
        $apiSecret = env('BYBIT_API_SECRET');
        $testnet   = env('BYBIT_TESTNET', false);

        $exchange = new bybit([
            'apiKey' => 'YOUR_API_KEY',
            'secret' => 'YOUR_API_SECRET',
            'enableRateLimit' => true,
        ]);
        if ($testnet) {
            $exchange->set_sandbox_mode(true);
        }

        $symbol = 'ETH/USDT';
        $steps  = $validated['steps'] ?? 4;

        $entry1 = (float) $validated['entry1'];
        $entry2 = (float) $validated['entry2'];
        if ($entry2 < $entry1) { [$entry1, $entry2] = [$entry2, $entry1]; }

        $avgEntry = ($entry1 + $entry2) / 2.0;
        $side = ($validated['sl'] > $avgEntry) ? 'sell' : 'buy';

        // -------------------------------
        // محاسبه حجم بر اساس ضرر 10٪ سرمایه
        // -------------------------------
        $capitalUSD = (float) env('TRADING_CAPITAL_USD', 1000); // سرمایه کل دلاری
        $maxLossUSD = $capitalUSD * 0.10; // 10٪ سرمایه

        $slDistance = abs($avgEntry - (float) $validated['sl']);
        if ($slDistance <= 0) {
            return back()->withErrors(['sl' => 'SL باید با نقطه ورود فاصله داشته باشد']);
        }

        // چند ETH می‌توان خرید/فروخت
        $amount = $maxLossUSD / $slDistance; // چون فاصله * حجم = ضرر دلاری

        // دقت بازار
        $market         = $exchange->market($symbol);
        $amountPrec     = $market['precision']['amount'] ?? 3;
        $pricePrec      = $market['precision']['price'] ?? 2;
        $amount         = round($amount, $amountPrec);

        // تقسیم بین پله‌ها
        $amountPerStep = round($amount / $steps, $amountPrec);

        // -------------------------------
        // ساخت سفارش‌ها
        // -------------------------------
        $stepSize = ($entry2 - $entry1) / max($steps - 1, 1);
        foreach (range(0, $steps - 1) as $i) {
            $price = round($entry1 + ($stepSize * $i), $pricePrec);

            $order = $exchange->createOrder($symbol, 'limit', $side, $amountPerStep, $price, [
                'timeInForce' => 'GTC',
                'reduceOnly'  => false,
            ]);

            BybitOrders::create([
                'order_id'       => $order['id'] ?? null,
                'symbol'         => $symbol,
                'entry_price'    => $price,
                'tp'             => (float)$validated['tp'],
                'sl'             => (float)$validated['sl'],
                'steps'          => $steps,
                'expire_minutes' => (int)$validated['expire'],
                'status'         => 'pending',
                'side'           => $side,
                'amount'         => $amountPerStep,
                'entry_low'      => $entry1,
                'entry_high'     => $entry2,
            ]);
        }

        return back()->with('success', 'سفارش‌ها ثبت شدند.');
    }

}
