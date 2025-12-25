<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserPeriod;
use App\Services\JournalPeriodService;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    /**
     * List periods for the authenticated user based on current account type.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $currentExchange = $user->getCurrentExchange();
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        // Ensure default period exists
        (new JournalPeriodService())->ensureDefaultPeriod($user->id, $isDemo);

        // Clean up ended periods with no records
        try {
            UserPeriod::forUser($user->id)
                ->accountType($isDemo)
                ->where('is_active', false)
                ->whereNotNull('ended_at')
                ->get()
                ->each(function ($p) {
                    $tc = (int)($p->metrics_all['trade_count'] ?? 0);
                    if ($tc === 0) { $p->delete(); }
                });
        } catch (\Throwable $e) {}

        $periods = UserPeriod::forUser($user->id)
            ->accountType($isDemo)
            ->orderByDesc('is_default')
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json([
            'data' => $periods->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'is_default' => (bool)$p->is_default,
                    'is_active' => (bool)$p->is_active,
                    'started_at' => optional($p->started_at)->toDateTimeString(),
                    'ended_at' => optional($p->ended_at)->toDateTimeString(),
                    'metrics' => $p->metrics_all,
                ];
            }),
        ]);
    }

    /**
     * Start a new custom period.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $currentExchange = $user->getCurrentExchange();
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        $activeCount = UserPeriod::forUser($user->id)
            ->accountType($isDemo)
            ->where('is_default', false)
            ->where('is_active', true)
            ->count();
        if ($activeCount >= 5) {
            return response()->json(['message' => 'حداکثر ۵ دوره فعال مجاز است. ابتدا یکی را پایان دهید.'], 422);
        }

        $nameRaw = $request->input('name', '');
        $name = trim((string) $nameRaw);
        if ($name === '') {
            return response()->json(['message' => 'نام دوره نمی‌تواند خالی باشد.'], 422);
        }

        $duplicate = UserPeriod::forUser($user->id)
            ->accountType($isDemo)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'نام دوره تکراری است. لطفاً نام دیگری انتخاب کنید.'], 422);
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

        (new JournalPeriodService())->updatePeriodMetrics($period);

        return response()->json(['message' => 'دوره جدید شروع شد.', 'period_id' => $period->id]);
    }

    /**
     * End a custom period.
     */
    public function end(UserPeriod $period)
    {
        $user = auth()->user();
        if ($period->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ($period->is_default) {
            return response()->json(['message' => 'پایان دوره پیش‌فرض مجاز نیست.'], 422);
        }

        $period->ended_at = now();
        $period->is_active = false;
        $period->save();

        (new JournalPeriodService())->updatePeriodMetrics($period);

        $tc = (int)($period->metrics_all['trade_count'] ?? 0);
        if ($tc === 0) {
            $period->delete();
            return response()->json(['message' => 'دوره پایان یافت و به دلیل نبود رکورد حذف شد.']);
        }

        return response()->json(['message' => 'دوره با موفقیت پایان یافت.']);
    }
}
