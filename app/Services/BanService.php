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
     * Ban duration: 3 days
     */
    private function checkExchangeForceCloseBan(Trade $trade, int $userId, bool $isDemo): void
    {
        $order = $trade->order;
        
        // Debug logging
        Log::info("[BanService] Checking exchange force close for trade {$trade->id}");
        Log::info("[BanService] Order exists: " . ($order ? 'YES' : 'NO'));
        Log::info("[BanService] Avg exit price: " . ($trade->avg_exit_price ?? 'NULL'));
        
        if (!$order || !$trade->avg_exit_price) {
            Log::info("[BanService] Skipping - no order or no exit price");
            return;
        }

        $exit = (float)$trade->avg_exit_price;
        $tp = $order->tp;
        $sl = $order->sl;
        
        $tpDelta = isset($tp) ? abs(((float)$tp - $exit) / $exit) : null;
        $slDelta = isset($sl) ? abs(((float)$sl - $exit) / $exit) : null;

        // Detailed debug logging
        Log::info("[BanService] Trade {$trade->id} details:");
        Log::info("  Exit Price: $exit");
        Log::info("  TP: " . ($tp ?? 'NULL') . " (Delta: " . ($tpDelta ?? 'NULL') . ")");
        Log::info("  SL: " . ($sl ?? 'NULL') . " (Delta: " . ($slDelta ?? 'NULL') . ")");
        Log::info("  Closed At: " . ($trade->closed_at ?? 'NULL'));
        Log::info("  Closed By User: " . ($trade->closed_by_user ?? 'NULL'));

        // Check all conditions
        $closedAtNotNull = $trade->closed_at !== null;
        $notClosedByUser = ((int)($trade->closed_by_user ?? 0)) !== 1;
        $deltasNotNull = $tpDelta !== null && $slDelta !== null;
        $deltasFarEnough = $tpDelta > 0.002 && $slDelta > 0.002;

        Log::info("[BanService] Condition checks:");
        Log::info("  Closed at not null: " . ($closedAtNotNull ? 'YES' : 'NO'));
        Log::info("  Not closed by user: " . ($notClosedByUser ? 'YES' : 'NO'));
        Log::info("  Deltas not null: " . ($deltasNotNull ? 'YES' : 'NO'));
        Log::info("  Deltas far enough (>0.2%): " . ($deltasFarEnough ? 'YES' : 'NO'));

        if ($closedAtNotNull && $notClosedByUser && $deltasNotNull && $deltasFarEnough) {
            $exists = UserBan::active()
                ->forUser($userId)
                ->accountType($isDemo)
                ->where('ban_type', 'exchange_force_close')
                ->exists();

            Log::info("[BanService] Active ban already exists: " . ($exists ? 'YES' : 'NO'));

            if (!$exists) {
                UserBan::create([
                    'user_id' => $userId,
                    'is_demo' => $isDemo,
                    'trade_id' => $trade->id,
                    'ban_type' => 'exchange_force_close',
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(3),
                ]);
                Log::info("[BanService] ✅ Created exchange_force_close ban for user {$userId}");
            }
        } else {
            Log::info("[BanService] ❌ Conditions not met - no ban created");
        }
    }

    /**
     * Check if user should be banned for single loss
     * Ban duration: 1 hour
     */
    private function checkSingleLossBan(Trade $trade, int $userId, bool $isDemo): void
    {
        $exists = UserBan::where('trade_id', $trade->id)
            ->where('ban_type', 'single_loss')
            ->exists();

        if (!$exists) {
            UserBan::create([
                'user_id' => $userId,
                'is_demo' => $isDemo,
                'trade_id' => $trade->id,
                'ban_type' => 'single_loss',
                'starts_at' => now(),
                'ends_at' => now()->addHours(1),
            ]);
            Log::info("[BanService] ✅ Created single_loss ban for user {$userId}");
        }
    }

    /**
     * Check if user should be banned for two consecutive losses
     * Ban duration: 24 hours
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
                UserBan::create([
                    'user_id' => $userId,
                    'is_demo' => $isDemo,
                    'trade_id' => $trade->id,
                    'ban_type' => 'double_loss',
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(1),
                ]);
                Log::info("[BanService] ✅ Created double_loss ban for user {$userId}");
            }
        }
    }
}
