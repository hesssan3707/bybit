<?php

namespace App\Http\Controllers;

use App\Models\UserPeriod;
use App\Services\JournalPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PeriodController extends Controller
{
    /**
     * Start a new custom period (non-default). Limit 5 active per user per account type.
     */
    public function start(Request $request)
    {
        $user = auth()->user();
        $currentExchange = $user->getCurrentExchange();
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        // Enforce limit of 5 active non-default periods
        $activeCount = UserPeriod::forUser($user->id)
            ->accountType($isDemo)
            ->where('is_default', false)
            ->where('is_active', true)
            ->count();

        if ($activeCount >= 5) {
            return back()->with('error', 'حداکثر ۵ دوره فعال مجاز است. ابتدا یکی را پایان دهید.');
        }

        // Backend validation: enforce non-empty, trimmed period name
        $nameRaw = $request->input('name', '');
        $name = trim((string) $nameRaw);
        if ($name === '') {
            return back()->with('error', 'نام دوره نمی‌تواند خالی باشد.');
        }

        // Prevent duplicate names across all periods of this account type (active + ended, default included)
        $duplicate = UserPeriod::forUser($user->id)
            ->accountType($isDemo)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
        if ($duplicate) {
            return back()->with('error', 'نام دوره تکراری است. لطفاً نام دیگری انتخاب کنید.');
        }

        $period = new UserPeriod([
            'user_id' => $user->id,
            'is_demo' => $isDemo,
            'name' => mb_substr($name, 0, 64),
            'started_at' => now(),
            'ended_at' => null,
            'is_default' => false,
            'is_active' => true,
        ]);
        $period->save();

        // Compute initial metrics (will update as trades close)
        (new JournalPeriodService())->updatePeriodMetrics($period);

        return back()->with('success', 'دوره جدید شروع شد.');
    }

    /**
     * End a custom period. Default periods cannot be ended manually.
     */
    public function end(UserPeriod $period)
    {
        $user = auth()->user();

        if ($period->user_id !== $user->id) {
            abort(403);
        }
        if ($period->is_default) {
            return back()->with('error', 'پایان دوره پیش‌فرض مجاز نیست.');
        }

        $period->ended_at = now();
        $period->is_active = false;
        $period->save();

        (new JournalPeriodService())->updatePeriodMetrics($period);

        // If no records in this period, remove from DB level
        $tc = (int)($period->metrics_all['trade_count'] ?? 0);
        if ($tc === 0) {
            $period->delete();
            return back()->with('success', 'دوره با موفقیت پایان یافت و به دلیل نبود رکورد حذف شد.');
        }

        return back()->with('success', 'دوره با موفقیت پایان یافت.');
    }

    /**
     * Recompute metrics for all periods of the current account type (demo/real).
     * Limits invocations to once every 10 minutes per user/account type.
     */
    public function recomputeAll(Request $request)
    {
        $user = auth()->user();

        // Determine account type from current or default exchange
        $currentExchange = $user->getCurrentExchange();
        $isDemo = $currentExchange ? (bool) $currentExchange->is_demo_active : false;

        // Cooldown key per user + account type
        $cooldownKey = 'periods:recompute_all:' . $user->id . ':' . ($isDemo ? 'demo' : 'real');
        if (Cache::has($cooldownKey)) {
            return back()->with('error', 'این عملیات اخیراً انجام شده است. لطفاً پس از ۱۵ دقیقه دوباره تلاش کنید. در صورت عدم به‌روزرسانی، به ادمین اطلاع دهید.');
        }

        $service = new JournalPeriodService();
        $periods = UserPeriod::forUser($user->id)
            ->accountType($isDemo)
            ->get();

        foreach ($periods as $period) {
            $service->updatePeriodMetrics($period);
        }

        // Set cooldown for 10 minutes
        Cache::put($cooldownKey, 1, now()->addMinutes(10));

        return back()->with('success', 'به‌روزرسانی ژورنال برای همه دوره‌ها انجام شد. اگر تغییرات را نمی‌بینید، بعد از ۱۵ دقیقه دوباره تلاش کنید. در صورت تداوم مشکل به ادمین اطلاع دهید. <a href="#" id="reportJournalIssue" class="btn btn-link" style="margin-inline-start:8px; text-decoration: underline;">گزارش مشکل</a>');
    }
}
