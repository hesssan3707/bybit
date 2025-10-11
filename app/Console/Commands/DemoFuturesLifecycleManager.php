<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;
use App\Models\Trade;
use App\Services\Exchanges\ExchangeFactory;
use Exception;

class DemoFuturesLifecycleManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:futures:lifecycle {--user=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مدیریت چرخه حیات سفارشات آتی دمو برای تمام کاربران تایید شده';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('شروع مدیریت چرخه حیات سفارشات آتی دمو...');

        $userOption = $this->option('user');

        if ($userOption) {
            $user = User::find($userOption);
            if (!$user) {
                $this->error("کاربر با شناسه {$userOption} یافت نشد.");
                return 1;
            }
            $this->syncForUser($user);
        } else {
            $this->syncForAllUsers();
        }

        $this->info('مدیریت چرخه حیات سفارشات آتی دمو با موفقیت تکمیل شد.');
        return 0;
    }

    /**
     * Sync lifecycle for all verified users
     */
    private function syncForAllUsers()
    {
        $users = User::whereNotNull('email_verified_at')->get();
        
        $this->info("پردازش {$users->count()} کاربر تایید شده...");

        foreach ($users as $user) {
            $this->syncForUser($user);
        }
    }

    /**
     * Sync lifecycle for a specific user
     */
    private function syncForUser(User $user)
    {
        $this->info("پردازش کاربر: {$user->email}");

        // Get all user exchanges with demo API keys
        $userExchanges = UserExchange::where('user_id', $user->id)
            ->where('futures_access', true)
            ->whereNotNull('demo_api_key')
            ->whereNotNull('demo_api_secret')
            ->get();

        foreach ($userExchanges as $userExchange) {
            $this->syncForUserExchange($user, $userExchange);
        }
    }

    /**
     * Sync lifecycle for a specific user exchange
     */
    private function syncForUserExchange(User $user, UserExchange $userExchange)
    {
        try {
            $this->info("پردازش صرافی {$userExchange->exchange_name} (دمو) برای کاربر {$user->email}");

            // If dry-run, skip any external API/network calls
            if ($this->option('dry-run')) {
                $this->info("[Dry-run] فراخوانی‌های شبکه‌ای برای صرافی {$userExchange->exchange_name} (دمو) نادیده گرفته شد.");
                return;
            }

            // Create exchange service (demo mode)
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->demo_api_key,
                $userExchange->demo_api_secret,
                true // Demo mode
            );

            // Test connection by trying to get account info
            try {
                $exchangeService->getAccountInfo();
            } catch (Exception $e) {
                $this->warn("صرافی {$userExchange->exchange_name} (دمو) برای کاربر {$user->email} در دسترس نیست: " . $e->getMessage());
                return;
            }

            // Sync lifecycle using new efficient approach
            $this->syncLifecycleForExchange($exchangeService, $user, $userExchange);

        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} (دمو) برای کاربر {$user->email}: " . $e->getMessage());
        }
    }

    /**
     * Sync order statuses with exchange
     */
    private function syncOrderStatuses($exchangeService, UserExchange $userExchange)
    {
        // Get all pending orders for this user exchange (demo mode)
        $orders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->whereIn('status', ['pending', 'partially_filled'])
            ->get();

        foreach ($orders as $order) {
            try {
                // Get order status from exchange
                $exchangeOrder = $exchangeService->getOrder($order->order_id, $order->symbol);

                // Update order status based on exchange response
                $newStatus = $this->mapExchangeStatus($exchangeOrder['list'][0]['orderStatus']);
                
                if ($order->status !== $newStatus) {
                    $order->status = $newStatus;
                    
                    // Update filled quantity if available
                    if (isset($exchangeOrder['filled'])) {
                        $order->filled_quantity = $exchangeOrder['filled'];
                    }
                    
                    // Update average price if available
                    if (isset($exchangeOrder['average'])) {
                        $order->average_price = $exchangeOrder['average'];
                    }
                    
                    $order->save();
                    
                    $this->info("وضعیت سفارش دمو {$order->order_id} به {$newStatus} تغییر یافت");
                }

            } catch (Exception $e) {
                // Order might be deleted/expired on exchange
                if (strpos($e->getMessage(), 'not found') !== false || 
                    strpos($e->getMessage(), 'does not exist') !== false) {
                    $order->status = 'deleted';
                    $order->save();
                    $this->info("سفارش دمو {$order->order_id} به عنوان حذف شده علامت‌گذاری شد");
                }
            }
        }
    }

    /**
     * Sync lifecycle for a specific exchange using the new approach:
     * Get orders from exchange and sync to database
     */
    private function syncLifecycleForExchange($exchangeService, User $user, UserExchange $userExchange)
    {
        // در محیط محلی اتصال به صرافی انجام نمی‌شود (دمو)
        if (app()->environment('local')) {
            $this->info("[Demo] در محیط محلی، اتصال به صرافی {$userExchange->exchange_name} انجام نمی‌شود");
            return;
        }
        // Get the oldest pending/filled order date for this user exchange (demo)
        $oldestOrder = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->whereIn('status', ['pending', 'filled', 'partially_filled'])
            ->orderBy('created_at', 'asc')
            ->first();

        // Calculate start time: oldest order date minus 15 minutes, or 24 hours ago if no orders
        $startTime = null;
        if ($oldestOrder) {
            $startTime = $oldestOrder->created_at->subMinutes(15)->timestamp * 1000; // Convert to milliseconds
            try {
            // Get orders from exchange with time filtering
            $exchangeOrders = $exchangeService->getOrderHistory(null, 100, $startTime);
            $orders = $exchangeOrders['list'] ?? [];

            $this->info("دریافت " . count($orders) . " سفارش از صرافی {$userExchange->exchange_name} (دمو)");

            foreach ($orders as $exchangeOrder) {
                $this->syncExchangeOrderToDatabase($exchangeOrder, $userExchange);
            }

            // Sync PnL records for hedge mode - disabled per new logic
            // $this->syncPnlRecords($exchangeService, $userExchange);

            } catch (Exception $e) {
                $this->error("خطا در همگام‌سازی سفارشات از صرافی (دمو): " . $e->getMessage());
            }
        } else {
            $this->info("سفارشی یافت نشد");
        }
    }

    /**
     * Sync individual exchange order to database
     */
    private function syncExchangeOrderToDatabase($exchangeOrder, UserExchange $userExchange)
    {
        $orderId = $this->extractOrderId($exchangeOrder, $userExchange->exchange_name);
        if (!$orderId) {
            return;
        }

        // Skip system-created TP/SL or reduce-only closing orders
        if ($this->isTpSlOrClosing($exchangeOrder, $userExchange->exchange_name)) {
            $this->info("[Demo] سفارش {$orderId} از نوع TP/SL یا بستن موقعیت است و نادیده گرفته شد");
            return;
        }

        $order = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->where('order_id', $orderId)
            ->first();

        if ($order) {
            $newStatus = $this->mapExchangeStatus($this->extractOrderStatus($exchangeOrder, $userExchange->exchange_name));
            if ($order->status !== $newStatus) {
                $order->status = $newStatus;

                $filledQty = $this->extractFilledQuantity($exchangeOrder, $userExchange->exchange_name);
                if ($filledQty !== null) {
                    $order->filled_quantity = $filledQty;
                }
                $avgPrice = $this->extractAveragePrice($exchangeOrder, $userExchange->exchange_name);
                if ($avgPrice !== null) {
                    $order->average_price = $avgPrice;
                }

                if (in_array($newStatus, ['filled', 'canceled', 'expired', 'closed'])) {
                    $order->closed_at = now();
                }
                $order->save();
                $this->info("[Demo] وضعیت سفارش {$orderId} به {$newStatus} تغییر یافت");

                if ($newStatus === 'filled') {
                    $this->handlePotentialClosureDemo($userExchange, $order, $exchangeOrder);
                }
            }
        } else {
            // Skip unknown orders to ensure only system-created orders are processed
            $this->info("[Demo] سفارش ناشناس {$orderId} نادیده گرفته شد");
            return;
        }
    }

    private function syncPnlRecords($exchangeService, UserExchange $userExchange)
    {
        if ($userExchange->position_mode !== 'hedge') {
            return;
        }

        try {
            $positionsRaw = $exchangeService->getPositions();
            $positions = $this->normalizePositionsList($userExchange->exchange_name, $positionsRaw);

            foreach ($positions as $rawPosition) {
                if (!is_array($rawPosition)) {
                    continue;
                }
                $position = $this->mapRawPositionToCommon($userExchange->exchange_name, $rawPosition);
                if (!$position) {
                    continue;
                }
                if (empty($position['size']) || (float)$position['size'] == 0.0) {
                    continue;
                }

                $trade = Trade::where('user_exchange_id', $userExchange->id)
                    ->where('is_demo', true)
                    ->where('symbol', $position['symbol'])
                    ->where('side', $position['side'])
                    ->first();

                if (!$trade) {
                    $trade = new Trade([
                        'user_exchange_id' => $userExchange->id,
                        'is_demo' => true,
                        'symbol' => $position['symbol'],
                        'side' => $position['side'],
                        'order_type' => 'Market',
                        'leverage' => $position['leverage'] ?? 1.0,
                        'qty' => (float)$position['size'],
                        'avg_entry_price' => $position['entryPrice'] ?? 0,
                        'avg_exit_price' => 0,
                        'pnl' => $position['unrealizedPnl'] ?? 0,
                        'order_id' => $position['orderId'] ?? 'position_' . uniqid(),
                        'closed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $trade->save();
                } else {
                    $trade->qty = (float)$position['size'];
                    $trade->avg_entry_price = $position['entryPrice'] ?? $trade->avg_entry_price;
                    $trade->leverage = $position['leverage'] ?? $trade->leverage;
                    $trade->pnl = $position['unrealizedPnl'] ?? 0;
                    $trade->updated_at = now();
                    $trade->save();
                }
            }

            $symbols = collect($positions)
                ->map(function($p){ return is_array($p) ? ($p['symbol'] ?? null) : null; })
                ->filter()
                ->unique();
            Trade::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', true)
                ->whereNotIn('symbol', $symbols)
                ->update(['pnl' => 0, 'updated_at' => now()]);

        } catch (Exception $e) {
            $this->warn("[Demo] خطا در همگام‌سازی سوابق PnL: " . $e->getMessage());
        }
    }

    private function normalizePositionsList(string $exchangeName, $positionsRaw): array
    {
        if (is_string($positionsRaw)) {
            $decoded = json_decode($positionsRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $positionsRaw = $decoded;
            }
        }
        if (is_array($positionsRaw) && array_key_exists('list', $positionsRaw)) {
            $list = $positionsRaw['list'];
        } else {
            $list = $positionsRaw;
        }
        if (!is_array($list)) {
            return [];
        }
        return $list;
    }

    private function mapRawPositionToCommon(string $exchangeName, array $raw): ?array
    {
        $symbol = $raw['symbol'] ?? null;
        if (!$symbol) return null;

        $side = $raw['side'] ?? null;
        if (!$side && isset($raw['positionSide'])) {
            $ps = strtoupper((string)$raw['positionSide']);
            $side = ($ps === 'LONG') ? 'Buy' : (($ps === 'SHORT') ? 'Sell' : null);
        }

        $size = null;
        if (isset($raw['size'])) {
            $size = (float)$raw['size'];
        } elseif (isset($raw['positionAmt'])) {
            $size = abs((float)$raw['positionAmt']);
        }

        $entryPrice = $raw['entryPrice'] ?? ($raw['avgPrice'] ?? ($raw['avg_entry_price'] ?? null));
        $unrealizedPnl = $raw['unrealizedPnl'] ?? ($raw['unRealizedProfit'] ?? null);
        $leverage = $raw['leverage'] ?? null;

        if ($side === null || $size === null) {
            return null;
        }

        return [
            'symbol' => $symbol,
            'side' => $side,
            'size' => $size,
            'entryPrice' => $entryPrice,
            'unrealizedPnl' => $unrealizedPnl,
            'leverage' => $leverage,
        ];
    }

    private function handlePotentialClosureDemo(UserExchange $userExchange, Order $order, $exchangeOrder): void
    {
        try {
            $symbol = $this->extractSymbol($exchangeOrder, $userExchange->exchange_name) ?: $order->symbol;
            $side = $this->extractSide($exchangeOrder, $userExchange->exchange_name);

            $isClosed = true; // In demo, assume filled means closed unless a position exists with same symbol/side

            if (!$isClosed) return;

            $existingTrade = Trade::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', true)
                ->where('order_id', $order->order_id)
                ->first();
            if ($existingTrade) return;

            Trade::create([
                'user_exchange_id' => $userExchange->id,
                'is_demo' => true,
                'symbol' => $symbol,
                'side' => $side ?: ($order->side ?? 'Buy'),
                'order_type' => 'Market',
                'leverage' => 1.0,
                'qty' => $order->filled_quantity ?? 0,
                'avg_entry_price' => $order->average_price ?? 0,
                'avg_exit_price' => $order->average_price ?? 0,
                'pnl' => 0,
                'order_id' => $order->order_id,
                'closed_at' => now(),
            ]);

            $this->info("[Demo] معامله بسته شده برای سفارش {$order->order_id} ثبت شد");
        } catch (Exception $e) {
            $this->warn("[Demo] خطا در بررسی بسته شدن موقعیت و ثبت معامله: " . $e->getMessage());
        }
    }


    /**
     * Handle closed orders not found in database - sync PnL (Demo)
     */
    private function syncClosedOrderPnl($exchangeOrder, UserExchange $userExchange)
    {
        // Only process filled orders that represent closed positions
        $status = $this->extractOrderStatus($exchangeOrder, $userExchange->exchange_name);
        if (strtoupper($status) !== 'FILLED') {
            return;
        }

        try {
            // Extract order details
            $orderId = $this->extractOrderId($exchangeOrder, $userExchange->exchange_name);
            $symbol = $this->extractSymbol($exchangeOrder, $userExchange->exchange_name);
            $side = $this->extractSide($exchangeOrder, $userExchange->exchange_name);
            $qty = $this->extractFilledQuantity($exchangeOrder, $userExchange->exchange_name);
            $avgPrice = $this->extractAveragePrice($exchangeOrder, $userExchange->exchange_name);

            if (!$orderId || !$symbol || !$side || !$qty || !$avgPrice) {
                return; // Skip if essential data is missing
            }

            // Create trade record for closed position in demo mode
            Trade::create([
                'user_exchange_id' => $userExchange->id,
                'is_demo' => true,
                'symbol' => $symbol,
                'side' => $side,
                'order_type' => 'Market', // Default for closed positions
                'leverage' => 1.0, // Default leverage
                'qty' => $qty,
                'avg_entry_price' => $avgPrice,
                'avg_exit_price' => $avgPrice, // Same as entry for closed positions
                'pnl' => 0, // Will be calculated separately
                'order_id' => $orderId,
                'closed_at' => now(),
            ]);

            $this->info("[Demo] سفارش بسته شده {$orderId} در جدول معاملات ثبت شد");

        } catch (Exception $e) {
            $this->warn("[Demo] خطا در ثبت سفارش بسته شده: " . $e->getMessage());
        }
    }

    /**
     * Extract order ID from exchange order based on exchange format
     */
    private function extractOrderId($exchangeOrder, string $exchangeName): ?string
    {
        switch ($exchangeName) {
            case 'binance':
                return $exchangeOrder['orderId'] ?? null;
            case 'bybit':
                return $exchangeOrder['orderId'] ?? null;
            case 'bingx':
                return $exchangeOrder['orderId'] ?? null;
            default:
                return null;
        }
    }

    /**
     * Extract order status from exchange order based on exchange format
     */
    private function extractOrderStatus($exchangeOrder, string $exchangeName): string
    {
        switch ($exchangeName) {
            case 'binance':
                return $exchangeOrder['status'] ?? 'UNKNOWN';
            case 'bybit':
                return $exchangeOrder['orderStatus'] ?? 'UNKNOWN';
            case 'bingx':
                return $exchangeOrder['status'] ?? 'UNKNOWN';
            default:
                return 'UNKNOWN';
        }
    }

    /**
     * Extract filled quantity from exchange order based on exchange format
     */
    private function extractFilledQuantity($exchangeOrder, string $exchangeName): ?float
    {
        switch ($exchangeName) {
            case 'binance':
                return isset($exchangeOrder['executedQty']) ? (float)$exchangeOrder['executedQty'] : null;
            case 'bybit':
                return isset($exchangeOrder['cumExecQty']) ? (float)$exchangeOrder['cumExecQty'] : null;
            case 'bingx':
                return isset($exchangeOrder['executedQty']) ? (float)$exchangeOrder['executedQty'] : null;
            default:
                return null;
        }
    }

    /**
     * Extract average price from exchange order based on exchange format
     */
    private function extractAveragePrice($exchangeOrder, string $exchangeName): ?float
    {
        switch ($exchangeName) {
            case 'binance':
                return isset($exchangeOrder['avgPrice']) ? (float)$exchangeOrder['avgPrice'] : null;
            case 'bybit':
                return isset($exchangeOrder['avgPrice']) ? (float)$exchangeOrder['avgPrice'] : null;
            case 'bingx':
                return isset($exchangeOrder['avgPrice']) ? (float)$exchangeOrder['avgPrice'] : null;
            default:
                return null;
        }
    }

    /**
     * Extract symbol from exchange order
     */
    private function extractSymbol($exchangeOrder, string $exchangeName): ?string
    {
        return $exchangeOrder['symbol'] ?? null;
    }

    /**
     * Extract side from exchange order
     */
    private function extractSide($exchangeOrder, string $exchangeName): ?string
    {
        switch ($exchangeName) {
            case 'binance':
                return $exchangeOrder['side'] ?? null;
            case 'bybit':
                return $exchangeOrder['side'] ?? null;
            case 'bingx':
                return $exchangeOrder['side'] ?? null;
            default:
                return null;
        }
    }

    /**
     * Detect TP/SL or reduce-only closing orders across exchanges
     */
    private function isTpSlOrClosing(array $exchangeOrder, string $exchangeName): bool
    {
        $reduceOnly = ($exchangeOrder['reduceOnly'] ?? false) === true;
        $closeOnTrigger = ($exchangeOrder['closeOnTrigger'] ?? false) === true;
        $hasTrigger = isset($exchangeOrder['triggerPrice']) || isset($exchangeOrder['stopPrice']);
        $hasSlOrTpField = !empty($exchangeOrder['stopLoss'] ?? null) || !empty($exchangeOrder['takeProfit'] ?? null);

        $stopOrderType = strtolower((string)($exchangeOrder['stopOrderType'] ?? ''));
        $isStopOrderType = in_array($stopOrderType, ['stop', 'stoploss', 'sl', 'takeprofit', 'tp', 'trailing_stop']);

        $orderType = strtoupper((string)($exchangeOrder['orderType'] ?? ($exchangeOrder['type'] ?? '')));
        $isStopType = in_array($orderType, ['STOP', 'STOP_MARKET', 'TAKE_PROFIT', 'TAKE_PROFIT_MARKET']);

        if (($reduceOnly && ($hasTrigger || $isStopType || $isStopOrderType)) || $hasSlOrTpField || $closeOnTrigger) {
            return true;
        }
        return false;
    }

    /**
     * Map exchange status to our internal status
     */
    private function mapExchangeStatus($exchangeStatus)
    {
        $statusMap = [
            'NEW' => 'pending',
            'PENDING' => 'pending',
            'PARTIALLY_FILLED' => 'partially_filled',
            'FILLED' => 'filled',
            'CANCELED' => 'cancelled',
            'CANCELLED' => 'cancelled',
            'REJECTED' => 'rejected',
            'EXPIRED' => 'expired',
            'CLOSED' => 'closed'
        ];

        return $statusMap[strtoupper($exchangeStatus)] ?? 'unknown';
    }
}