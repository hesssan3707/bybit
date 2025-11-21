<?php

namespace App\Observers;

use App\Models\Trade;
use App\Models\UserPeriod;
use App\Services\JournalPeriodService;

class TradeObserver
{
    public function updated(Trade $trade): void
    {
        // When a trade closes for the first time, update relevant periods
        $originalClosedAt = $trade->getOriginal('closed_at');
        if ($originalClosedAt === null && $trade->closed_at !== null) {
            $userExchange = $trade->userExchange;
            if (!$userExchange) {
                return;
            }

            $userId = $userExchange->user_id;
            $isDemo = (bool) $trade->is_demo;

            $service = new JournalPeriodService();
            // Ensure default period exists and is current
            $service->ensureDefaultPeriod($userId, $isDemo);

            // Find all periods that include this closed_at
            $periods = UserPeriod::forUser($userId)
                ->accountType($isDemo)
                ->where('started_at', '<=', $trade->closed_at)
                ->where(function ($q) use ($trade) {
                    $q->whereNull('ended_at')->orWhere('ended_at', '>=', $trade->closed_at);
                })
                ->get();

            foreach ($periods as $period) {
                $service->updatePeriodMetrics($period);
            }
        }
    }
}