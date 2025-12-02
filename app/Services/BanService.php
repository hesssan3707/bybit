<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\UserBan;
use App\Models\UserExchange;
use Illuminate\Support\Facades\Log;

class BanService
{
    /**
     * Process all ban types for a closed trade
     */
    public function processTradeBans(Trade $trade): void
    {
        try {
            $userExchange = $trade->userExchange;
            if (!$userExchange) {
                Log::warning("[BanService] Trade {$trade->id} has no userExchange");
                return;
            }

            $userId = $userExchange->user_id;
            $isDemo = (bool)$trade->is_demo;

            // 1. Exchange Force Close Ban (3 days)
            $this->checkExchangeForceCloseBan($trade, $userId, $isDemo);

            // 2. Loss-based Bans (Single: 1h, Double: 24h)
            if ($trade->pnl !== null && (float)$trade->pnl < 0) {
                $this->checkSingleLossBan($trade, $userId, $isDemo);
                $this->checkDoubleLossBan($trade, $userExchange, $userId, $isDemo);
            }

        } catch (\Throwable $e) {
            Log::error("[BanService] Error processing bans for trade {$trade->id}: " . $e->getMessage());
            Log::error("[BanService] Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Check if user should be banned for exchange force close (manual close on exchange)
     * Ban duration: 3 days (72 hours) from trade close time
     */
    private function checkExchangeForceCloseBan(Trade $trade, int $userId, bool $isDemo): void
    {
        $order = $trade->order;
        
        if (!$order || !$trade->avg_exit_price) {
            return;
        }

        $exit = (float)$trade->avg_exit_price;
        $tp = $order->tp;
        $sl = $order->sl;
        
        $tpDelta = isset($tp) ? abs(((float)$tp - $exit) / $exit) : null;
        $slDelta = isset($sl) ? abs(((float)$sl - $exit) / $exit) : null;

        if ($trade->closed_at !== null
            && ((int)($trade->closed_by_user ?? 0)) !== 1
            && $tpDelta !== null && $slDelta !== null
            && $tpDelta > 0.002 && $slDelta > 0.002) {
            
            $exists = UserBan::active()
                ->forUser($userId)
                ->accountType($isDemo)
                ->where('ban_type', 'exchange_force_close')
                ->exists();

            if (!$exists) {
                $closedAt = \Carbon\Carbon::parse($trade->closed_at);
                UserBan::create([
                    'user_id' => $userId,
                    'is_demo' => $isDemo,
                    'trade_id' => $trade->id,
                    'ban_type' => 'exchange_force_close',
                    'starts_at' => $closedAt,
                    'ends_at' => $closedAt->copy()->addHours(72),
                ]);
            }
        }
    }

    /**
     * Check if user should be banned for single loss
     * Ban duration: 1 hour from trade close time
     */
    private function checkSingleLossBan(Trade $trade, int $userId, bool $isDemo): void
    {
        $exists = UserBan::where('trade_id', $trade->id)
            ->where('ban_type', 'single_loss')
            ->exists();

        if (!$exists) {
            $closedAt = \Carbon\Carbon::parse($trade->closed_at);
            UserBan::create([
                'user_id' => $userId,
                'is_demo' => $isDemo,
                'trade_id' => $trade->id,
                'ban_type' => 'single_loss',
                'starts_at' => $closedAt,
                'ends_at' => $closedAt->copy()->addHours(1),
            ]);
        }
    }

    /**
     * Check if user should be banned for two consecutive losses
     * Ban duration: 24 hours from second trade's close time
     */
    private function checkDoubleLossBan(Trade $trade, UserExchange $userExchange, int $userId, bool $isDemo): void
    {
        $lastTwo = Trade::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', $isDemo)
            ->whereNotNull('closed_at')
            ->orderBy('closed_at', 'desc')
            ->limit(2)
            ->get();

        if ($lastTwo->count() === 2
            && (float)$lastTwo[0]->pnl < 0
            && (float)$lastTwo[1]->pnl < 0
            && now()->diffInHours($lastTwo[0]->closed_at) < 24
            && now()->diffInHours($lastTwo[1]->closed_at) < 24) {
            
            $hasActiveDouble = UserBan::active()
                ->forUser($userId)
                ->accountType($isDemo)
                ->where('ban_type', 'double_loss')
                ->exists();

            if (!$hasActiveDouble) {
                // Ban starts from the second (most recent) trade's closing time
                $closedAt = \Carbon\Carbon::parse($trade->closed_at);
                UserBan::create([
                    'user_id' => $userId,
                    'is_demo' => $isDemo,
                    'trade_id' => $trade->id,
                    'ban_type' => 'double_loss',
                    'starts_at' => $closedAt,
                    'ends_at' => $closedAt->copy()->addHours(24),
                ]);
            }
        }
    }

    /**
     * Proactively check and create bans based on user's trading history
     * This is called when user tries to create/submit an order
     * 
     * @param int $userId
     * @param bool $isDemo
     * @return void
     */
    public function checkAndCreateHistoricalBans(int $userId, bool $isDemo): void
    {
        try {
            // Get user's active exchange
            $userExchange = \App\Models\UserExchange::where('user_id', $userId)
                ->where('is_demo_active', $isDemo)
                ->first();

            if (!$userExchange) {
                return;
            }

            // Fetch only last 2 synchronized closed trades in a single query
            $recentTrades = Trade::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', $isDemo)
                ->where('synchronized', 1)
                ->whereNotNull('closed_at')
                ->orderBy('closed_at', 'desc')
                ->limit(2)
                ->get();

            if ($recentTrades->isEmpty()) {
                return;
            }

            $lastTrade = $recentTrades->first();

            // Check single loss ban (only if loss happened within last hour)
            if ($lastTrade->pnl !== null && (float)$lastTrade->pnl < 0) {
                $hoursSinceClosed = now()->diffInHours($lastTrade->closed_at);
                if ($hoursSinceClosed < 1) {
                    $this->checkSingleLossBan($lastTrade, $userId, $isDemo);
                }
            }

            // Check exchange force close ban (only most recent trade)
            $this->checkExchangeForceCloseBan($lastTrade, $userId, $isDemo);

            // Check double loss ban (consecutive losses within 24 hours)
            if ($recentTrades->count() >= 2) {
                $secondTrade = $recentTrades->get(1);
                
                // Both must be losses
                if ($lastTrade->pnl !== null && (float)$lastTrade->pnl < 0 &&
                    $secondTrade->pnl !== null && (float)$secondTrade->pnl < 0) {
                    
                    // Both must be within last 24 hours
                    $firstWithin24h = now()->diffInHours($lastTrade->closed_at) < 24;
                    $secondWithin24h = now()->diffInHours($secondTrade->closed_at) < 24;
                    
                    if ($firstWithin24h && $secondWithin24h) {
                        $this->checkDoubleLossBan($lastTrade, $userExchange, $userId, $isDemo);
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error("[BanService] Error in proactive ban checking for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Check strict mode limits (weekly/monthly profit/loss)
     */
    public function checkStrictLimits(\App\Models\User $user, bool $isDemo): void
    {
        if (!$user->future_strict_mode) {
            return;
        }

        $settings = \App\Models\UserAccountSetting::getUserSettings($user->id, $isDemo);
        
        $weeklyEnabled = $settings['weekly_limit_enabled'] ?? false;
        $monthlyEnabled = $settings['monthly_limit_enabled'] ?? false;

        if (!$weeklyEnabled && !$monthlyEnabled) {
            return;
        }

        // Calculate Weekly PnL %
        if ($weeklyEnabled) {
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();
            $weeklyPnlPercent = $this->calculatePeriodPnlPercent($user->id, $isDemo, $startOfWeek, $endOfWeek);
            
            $profitLimit = isset($settings['weekly_profit_limit']) ? (float)$settings['weekly_profit_limit'] : null;
            $lossLimit = isset($settings['weekly_loss_limit']) ? (float)$settings['weekly_loss_limit'] : null;

            if ($profitLimit !== null && $weeklyPnlPercent >= $profitLimit) {
                $this->createLimitBan($user->id, $isDemo, 'weekly_profit_limit', $endOfWeek);
            }
            
            if ($lossLimit !== null && $weeklyPnlPercent <= -$lossLimit) {
                $this->createLimitBan($user->id, $isDemo, 'weekly_loss_limit', $endOfWeek);
            }
        }

        // Calculate Monthly PnL %
        if ($monthlyEnabled) {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();
            $monthlyPnlPercent = $this->calculatePeriodPnlPercent($user->id, $isDemo, $startOfMonth, $endOfMonth);

            $profitLimit = isset($settings['monthly_profit_limit']) ? (float)$settings['monthly_profit_limit'] : null;
            $lossLimit = isset($settings['monthly_loss_limit']) ? (float)$settings['monthly_loss_limit'] : null;

            if ($profitLimit !== null && $monthlyPnlPercent >= $profitLimit) {
                $this->createLimitBan($user->id, $isDemo, 'monthly_profit_limit', $endOfMonth);
            }

            if ($lossLimit !== null && $monthlyPnlPercent <= -$lossLimit) {
                $this->createLimitBan($user->id, $isDemo, 'monthly_loss_limit', $endOfMonth);
            }
        }
    }

    private function calculatePeriodPnlPercent($userId, $isDemo, $startDate, $endDate)
    {
        $trades = Trade::whereHas('userExchange', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->join('orders', 'trades.order_id', '=', 'orders.order_id')
            ->where('trades.is_demo', $isDemo)
            ->whereNotNull('trades.closed_at')
            ->whereBetween('trades.closed_at', [$startDate, $endDate])
            ->orderBy('trades.closed_at', 'asc')
            ->select('trades.pnl', 'trades.closed_at', 'orders.balance_at_creation')
            ->get();

        $compound = 1.0;
        foreach ($trades as $trade) {
            $capital = (float)($trade->balance_at_creation ?? 0.0);
            if ($capital <= 0.0) { continue; }
            $percent = ((float)$trade->pnl / $capital) * 100.0;
            $compound = $compound * (1.0 + ($percent / 100.0));
        }
        return ($compound - 1.0) * 100.0;
    }

    private function createLimitBan($userId, $isDemo, $type, $endsAt)
    {
        // Check if active ban of this type exists
        $exists = UserBan::active()
            ->forUser($userId)
            ->accountType($isDemo)
            ->where('ban_type', $type)
            ->exists();

        if (!$exists) {
            UserBan::create([
                'user_id' => $userId,
                'is_demo' => $isDemo,
                'ban_type' => $type,
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);
        }
    }

    /**
     * Get Persian ban message with remaining time
     * 
     * @param \App\Models\UserBan $ban
     * @return string
     */
    public static function getPersianBanMessage(\App\Models\UserBan $ban): string
    {
        $reasonMap = [
            'single_loss' => 'ضرر در یک معامله',
            'double_loss' => 'دو ضرر متوالی',
            'exchange_force_close' => 'بستن اجباری سفارش توسط صرافی',
            'weekly_profit_limit' => 'رسیدن به حد سود هفتگی',
            'weekly_loss_limit' => 'رسیدن به حد ضرر هفتگی',
            'monthly_profit_limit' => 'رسیدن به حد سود ماهانه',
            'monthly_loss_limit' => 'رسیدن به حد ضرر ماهانه',
        ];

        $reason = $reasonMap[$ban->ban_type] ?? 'دلیل نامشخص';
        $remainingSeconds = max(0, $ban->ends_at->diffInSeconds(now()));
        
        // Calculate days, hours, minutes
        $days = floor($remainingSeconds / 86400);
        $hours = floor(($remainingSeconds % 86400) / 3600);
        $minutes = floor(($remainingSeconds % 3600) / 60);

        $timeParts = [];
        if ($days > 0) {
            $timeParts[] = $days . ' روز';
        }
        if ($hours > 0) {
            $timeParts[] = $hours . ' ساعت';
        }
        if ($minutes > 0 || empty($timeParts)) {
            $timeParts[] = $minutes . ' دقیقه';
        }

        $timeRemaining = implode(' و ', $timeParts);

        return "به دلیل {$reason}، شما تا {$timeRemaining} دیگر امکان ثبت سفارش جدید را ندارید.";
    }
}
