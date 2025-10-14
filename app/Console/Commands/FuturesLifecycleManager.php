<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;
use App\Models\Trade;
use App\Services\Exchanges\ExchangeFactory;
use Exception;

class FuturesLifecycleManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'futures:lifecycle {--user=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مدیریت چرخه حیات سفارشات آتی برای تمام کاربران تایید شده';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('شروع مدیریت چرخه حیات سفارشات آتی...');

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

        $this->info('مدیریت چرخه حیات سفارشات آتی با موفقیت تکمیل شد.');
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

        // Get all user exchanges (both demo and real)
        $userExchanges = UserExchange::where('user_id', $user->id)
            ->where('futures_access', true)
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
            $this->info("پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email}");

            // Create exchange service (real mode)
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->api_key,
                $userExchange->api_secret,
                false // Real mode
            );

            // Test connection by trying to get account info
            try {
                $exchangeService->getAccountInfo();
            } catch (Exception $e) {
                $this->warn("صرافی {$userExchange->exchange_name} برای کاربر {$user->email} در دسترس نیست: " . $e->getMessage());
                return;
            }

            // Sync lifecycle for this exchange
            $this->syncLifecycleForExchange($exchangeService, $user, $userExchange);

        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email}: " . $e->getMessage());
        }
    }

    /**
     * Sync lifecycle for a specific exchange using the new approach:
     * Get orders from exchange and sync to database
     */
    private function syncLifecycleForExchange($exchangeService, User $user, UserExchange $userExchange)
    {
        // در محیط محلی اتصال به صرافی انجام نمی‌شود
        if (app()->environment('local')) {
            $this->info("در محیط محلی، اتصال به صرافی {$userExchange->exchange_name} انجام نمی‌شود");
            return;
        }
        // Get the oldest pending/filled order date for this user exchange
        $oldestOrder = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->whereIn('status', ['pending', 'filled'])
            ->orderBy('created_at', 'asc')
            ->first();

        // Calculate start time: oldest order date minus 5 minutes
        $startTime = null;
        if ($oldestOrder) {
            try {
                $startTime = $oldestOrder->created_at->subMinutes(5)->timestamp * 1000; // Convert to milliseconds

                // Get orders from exchange with time filtering
                $exchangeOrders = $exchangeService->getOrderHistory(null, 100, $startTime);
                $orders = $exchangeOrders['list'] ?? [];

                $this->info("دریافت " . count($orders) . " سفارش از صرافی {$userExchange->exchange_name}");

                foreach ($orders as $exchangeOrder) {
                    $this->syncExchangeOrderToDatabase($exchangeService, $exchangeOrder, $userExchange);
                }

                // پس از همگام‌سازی سفارش‌ها، موقعیت‌های باز را نیز همگام‌سازی می‌کنیم
                $this->syncOpenPositions($exchangeService, $userExchange);
                // Sync PnL records for hedge mode - disabled per new logic
                // $this->syncPnlRecords($exchangeService, $userExchange);

            } catch (Exception $e) {
                $this->error("خطا در همگام‌سازی سفارشات از صرافی: " . $e->getMessage());
            }
        }
    }

    /**
     * Sync individual exchange order to database
     */
    private function syncExchangeOrderToDatabase($exchangeService, $exchangeOrder, UserExchange $userExchange)
    {
        $orderId = $this->extractOrderId($exchangeOrder, $userExchange->exchange_name);
        if (!$orderId) {
            return;
        }

        // Skip system-created TP/SL or reduce-only closing orders across exchanges
        if ($this->isTpSlOrClosing($exchangeOrder, $userExchange->exchange_name)) {
            return; // silently skip system-created TP/SL or closing orders
        }

        $order = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->where('order_id', $orderId)
            ->whereNotIn('status', ['expired'])
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

                if (in_array($newStatus, ['filled','canceled', 'expired', 'closed'])) {
                    $order->closed_at = now();
                }
                $order->save();
                $this->info("وضعیت سفارش {$orderId} به {$newStatus} تغییر یافت");

                // ایجاد/به‌روزرسانی معامله باز در حالت FILLED
                if ($newStatus === 'filled') {
                    $symbol = $this->extractSymbol($exchangeOrder, $userExchange->exchange_name);
                    $side = $this->extractSide($exchangeOrder, $userExchange->exchange_name);
                    $qty = $this->extractFilledQuantity($exchangeOrder, $userExchange->exchange_name);
                    $avgPrice = $this->extractAveragePrice($exchangeOrder, $userExchange->exchange_name);

                    if ($symbol && $side && $qty !== null && $avgPrice !== null) {
                        $existingOpen = Trade::where('user_exchange_id', $userExchange->id)
                            ->where('is_demo', false)
                            ->where('order_id', $order->order_id)
                            ->whereNull('closed_at')
                            ->first();

                        if ($existingOpen) {
                            $existingOpen->qty = (float)$qty;
                            $existingOpen->avg_entry_price = (float)$avgPrice;
                            $existingOpen->save();
                            $this->info("معامله باز برای سفارش {$orderId} به‌روزرسانی شد");
                        } else {
                            Trade::create([
                                'user_exchange_id' => $userExchange->id,
                                'is_demo' => false,
                                'symbol' => $symbol,
                                'side' => $side,
                                'order_type' => 'Market',
                                'leverage' => 1.0,
                                'qty' => (float)$qty,
                                'avg_entry_price' => (float)$avgPrice,
                                'avg_exit_price' => 0,
                                'pnl' => 0,
                                'order_id' => $order->order_id,
                                'closed_at' => null,
                            ]);
                            $this->info("معامله باز برای سفارش {$orderId} ثبت شد");
                        }
                    }
                }

            }
        } else {
            // Skip unknown orders to enforce only system-created orders, without logging
            return;
        }
    }

    /**
     * Sync order statuses with exchange
     */
    private function syncOrderStatuses($exchangeService, UserExchange $userExchange)
    {
        // Get all pending orders for this user exchange (real mode)
        $orders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->whereIn('status', ['pending', 'filled'])
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
                    
                    $this->info("وضعیت سفارش {$order->order_id} به {$newStatus} تغییر یافت");
                }

            } catch (Exception $e) {
                // Order might be deleted/expired on exchange
                if (strpos($e->getMessage(), 'not found') !== false || 
                    strpos($e->getMessage(), 'does not exist') !== false) {
                    $order->status = 'deleted';
                    $order->save();
                    $this->info("سفارش {$order->order_id} به عنوان حذف شده علامت‌گذاری شد");
                }
            }
        }
    }

    /**
     * Sync PnL records for hedge mode
     */
    private function syncPnlRecords($exchangeService, UserExchange $userExchange)
    {
        // Skip exchange connectivity on localhost
        if (app()->environment('local')) {
            $this->info("[Real] در محیط محلی، همگام‌سازی PnL نادیده گرفته شد");
            return;
        }

        try {
            // دریافت موقعیت‌ها و نرمال‌سازی برای صرافی‌های مختلف
            $positionsRaw = $exchangeService->getPositions();
            $positions = $this->normalizePositionsList($userExchange->exchange_name, $positionsRaw);

            $normalized = [];
            foreach ($positions as $rawPosition) {
                if (!is_array($rawPosition)) { continue; }
                $p = $this->mapRawPositionToCommon($userExchange->exchange_name, $rawPosition);
                if (!$p) { continue; }
                if (empty($p['size']) || (float)$p['size'] == 0.0) { continue; }
                $normalized[] = $p;
            }

            // فقط معاملات باز (closed_at = null) را بررسی می‌کنیم
            $openTrades = Trade::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', false)
                ->whereNull('closed_at')
                ->get();

            foreach ($openTrades as $trade) {
                // تلاش برای یافتن تطابق در موقعیت‌های باز صرافی
                $matchedPosition = null;
                foreach ($normalized as $p) {
                    if (($p['symbol'] ?? null) === $trade->symbol
                        && ($p['side'] ?? null) === $trade->side
                        && isset($p['entryPrice']) && (float)$p['entryPrice'] == (float)$trade->avg_entry_price
                        && isset($p['size']) && (float)$p['size'] == (float)$trade->qty) {
                        $matchedPosition = $p;
                        break;
                    }
                }

                if ($matchedPosition) {
                    if (array_key_exists('leverage', $matchedPosition) && $matchedPosition['leverage'] !== null) {
                        $trade->leverage = $matchedPosition['leverage'];
                    }
                    if (array_key_exists('unrealizedPnl', $matchedPosition) && $matchedPosition['unrealizedPnl'] !== null) {
                        $trade->pnl = $matchedPosition['unrealizedPnl'];
                    }
                    $trade->updated_at = now();
                    $trade->save();
                    continue;
                }

                // تلاش برای یافتن در تاریخچه PnL بسته
                $symbol = $trade->symbol;
                $closedRaw = $exchangeService->getClosedPnl($symbol, 100, null);
                $closedList = $this->normalizeClosedPnl($userExchange->exchange_name, $closedRaw);

                $matchedClosed = null;
                foreach ($closedList as $c) {
                    $idMatch = isset($c['orderId']) && $trade->order_id && (string)$c['orderId'] === (string)$trade->order_id;
                    $fieldsMatch = (($c['symbol'] ?? null) === $symbol)
                        && isset($c['qty']) && (float)$c['qty'] == (float)$trade->qty
                        && isset($c['avgEntryPrice']) && (float)$c['avgEntryPrice'] == (float)$trade->avg_entry_price;
                    if ($idMatch || $fieldsMatch) {
                        $matchedClosed = $c;
                        break;
                    }
                }

                if ($matchedClosed) {
                    if (array_key_exists('avgExitPrice', $matchedClosed) && $matchedClosed['avgExitPrice'] !== null) {
                        $trade->avg_exit_price = $matchedClosed['avgExitPrice'];
                    }
                    if (array_key_exists('realizedPnl', $matchedClosed) && $matchedClosed['realizedPnl'] !== null) {
                        $trade->pnl = $matchedClosed['realizedPnl'];
                    }
                    $trade->closed_at = now();
                    $trade->updated_at = now();
                    $trade->save();
                }
            }
        } catch (Exception $e) {
            $this->warn("[Real] خطا در همگام‌سازی سوابق PnL: " . $e->getMessage());
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
     * Handle closed orders not found in database - sync PnL
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

            // Create trade record for closed position
            Trade::create([
                'user_exchange_id' => $userExchange->id,
                'is_demo' => false,
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

            $this->info("سفارش بسته شده {$orderId} در جدول معاملات ثبت شد");

        } catch (Exception $e) {
            $this->warn("خطا در ثبت سفارش بسته شده: " . $e->getMessage());
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
     * Map exchange status to our internal status
     */
    private function mapExchangeStatus($exchangeStatus)
    {
        $status = strtoupper((string)$exchangeStatus);
        switch ($status) {
            case 'NEW':
            case 'ACTIVE':
            case 'OPEN':
            case 'PENDING':
                return 'pending';
            case 'FILLED':
                return 'filled';
            case 'CANCELED':
            case 'CANCELLED':
                return 'canceled';
            case 'EXPIRED':
                return 'expired';
            default:
                return 'pending';
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
        $list = null;
        if (is_array($positionsRaw)) {
            if (array_key_exists('list', $positionsRaw)) {
                $list = $positionsRaw['list'];
            } elseif (array_key_exists('positions', $positionsRaw)) {
                $list = $positionsRaw['positions'];
            } elseif (array_key_exists('positionList', $positionsRaw)) {
                $list = $positionsRaw['positionList'];
            } elseif (array_key_exists('data', $positionsRaw)) {
                $list = $positionsRaw['data'];
            } else {
                $list = $positionsRaw;
            }
        } else {
            $list = $positionsRaw;
        }
        if (!is_array($list)) {
            if (!app()->environment('local')) {
                $this->warn("[Real] شکل لیست موقعیت‌ها نامعتبر برای صرافی {$exchangeName}");
            }
            return [];
        }
        if (is_array($list) && empty($list) && !app()->environment('local')) {
            $this->info("[Real] لیست موقعیت‌ها خالی برای صرافی {$exchangeName}");
        }
        return $list;
    }

    private function mapRawPositionToCommon(string $exchangeName, array $raw): ?array
    {
        $symbol = $raw['symbol'] ?? null;
        if (!$symbol) {
            if (!app()->environment('local')) {
                $this->warn("[Real] نماد نامشخص در موقعیت خام صرافی {$exchangeName}");
            }
            return null;
        }
    
        // Determine side across exchanges
        $side = $raw['side'] ?? null;
        if (!$side && isset($raw['positionSide'])) {
            $ps = strtoupper((string)$raw['positionSide']);
            $side = ($ps === 'LONG') ? 'Buy' : (($ps === 'SHORT') ? 'Sell' : null);
        }
        if (!$side && isset($raw['positionAmt'])) {
            $amt = (float)$raw['positionAmt'];
            if ($amt > 0) { $side = 'Buy'; }
            elseif ($amt < 0) { $side = 'Sell'; }
        }
        if ($side) {
            $s = strtoupper((string)$side);
            if (in_array($s, ['LONG','BUY'])) { $side = 'Buy'; }
            elseif (in_array($s, ['SHORT','SELL'])) { $side = 'Sell'; }
            else { $side = null; }
        }
        if ($side === null && !app()->environment('local')) {
            $this->warn("[Real] جهت پوزیشن نامشخص برای نماد {$symbol} در صرافی {$exchangeName}");
        }
    
        // Size/qty across exchanges
        $size = null;
        if (isset($raw['size'])) {
            $size = (float)$raw['size'];
        } elseif (isset($raw['positionAmt'])) {
            $size = abs((float)$raw['positionAmt']);
        }
        if (($size === null || $size == 0.0) && !app()->environment('local')) {
            $this->info("[Real] حجم/سایز موقعیت نامشخص یا صفر برای نماد {$symbol} در صرافی {$exchangeName}");
        }
    
        // Entry price
        $entryPrice = $raw['entryPrice'] ?? ($raw['avgPrice'] ?? ($raw['avg_entry_price'] ?? null));
        if ($entryPrice !== null) { $entryPrice = (float)$entryPrice; }
        if ($entryPrice === null && !app()->environment('local')) {
            $this->info("[Real] قیمت ورود نامشخص برای نماد {$symbol} در صرافی {$exchangeName}");
        }
    
        // Unrealized PnL
        $unrealizedPnl = null;
        if (isset($raw['unrealizedPnl'])) {
            $unrealizedPnl = (float)$raw['unrealizedPnl'];
        } elseif (isset($raw['unrealisedPnl'])) {
            $unrealizedPnl = (float)$raw['unrealisedPnl'];
        } elseif (isset($raw['unRealizedProfit'])) { // Binance
            $unrealizedPnl = (float)$raw['unRealizedProfit'];
        }
    
        // Leverage
        $leverage = null;
        if (isset($raw['leverage'])) { $leverage = (float)$raw['leverage']; }
        if ($leverage === null && !app()->environment('local')) {
            $this->info("[Real] لورج نامشخص برای نماد {$symbol} در صرافی {$exchangeName}");
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

    private function handlePotentialClosure($exchangeService, UserExchange $userExchange, Order $order, $exchangeOrder): void
    {
        try {
            $symbol = $this->extractSymbol($exchangeOrder, $userExchange->exchange_name) ?: $order->symbol;
            $side = $this->extractSide($exchangeOrder, $userExchange->exchange_name);

            // بررسی بسته بودن موقعیت
            $positionsRaw = $exchangeService->getPositions($symbol);
            $positions = $this->normalizePositionsList($userExchange->exchange_name, $positionsRaw);

            $isClosed = true;
            foreach ($positions as $raw) {
                if (!is_array($raw)) continue;
                $p = $this->mapRawPositionToCommon($userExchange->exchange_name, $raw);
                if (!$p) continue;
                if ($p['symbol'] !== $symbol) continue;
                if ($userExchange->position_mode === 'hedge' && $side && $p['side'] !== $side) continue;
                if ((float)$p['size'] > 0.0) { $isClosed = false; break; }
            }
            if (!$isClosed) return;

            // دریافت لیست PnL های بسته شده و ثبت چندگانه برای کلوزهای جزئی
            $startTime = $order->created_at ? $order->created_at->subMinutes(15)->timestamp * 1000 : null;
            $closedRaw = $exchangeService->getClosedPnl($symbol, 100, $startTime);
            $closedList = $this->normalizeClosedPnl($userExchange->exchange_name, $closedRaw);

            if (empty($closedList)) {
                // در صورت نبود داده، یک رکورد حداقلی ایجاد می‌کنیم
                Trade::create([
                    'user_exchange_id' => $userExchange->id,
                    'is_demo' => false,
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
                $this->info("معامله بسته شده برای سفارش {$order->order_id} ثبت شد (بدون داده PnL)");
                return;
            }

            // فیلتر رکوردها: براساس orderId یا نماد (و در حالت هج، سمت)
            $filtered = array_filter($closedList, function($c) use ($order, $symbol, $side, $userExchange) {
                $matchId = isset($c['orderId']) && (string)$c['orderId'] === (string)$order->order_id;
                $matchSymbol = ($c['symbol'] ?? null) === $symbol;
                $matchSide = $userExchange->position_mode === 'hedge' ? (($c['side'] ?? null) === $side) : true;
                return $matchId || ($matchSymbol && $matchSide);
            });

            // اگر چیزی فیلتر نشد، کل لیست را در نظر بگیر
            $events = !empty($filtered) ? array_values($filtered) : $closedList;

            // ثبت چندگانه با جلوگیری از دوباره‌کاری با کلید (order_id + closed_at)
            foreach ($events as $c) {
                $closedAtMs = $c['closedAt'] ?? null;
                $closedAt = $closedAtMs ? \Carbon\Carbon::createFromTimestampMs($closedAtMs) : now();

                $exists = Trade::where('user_exchange_id', $userExchange->id)
                    ->where('is_demo', false)
                    ->where('order_id', $order->order_id)
                    ->where('closed_at', $closedAt)
                    ->exists();
                if ($exists) { continue; }

                Trade::create([
                    'user_exchange_id' => $userExchange->id,
                    'is_demo' => false,
                    'symbol' => $c['symbol'] ?? $symbol,
                    'side' => $c['side'] ?? ($side ?: ($order->side ?? 'Buy')),
                    'order_type' => 'Market',
                    'leverage' => 1.0,
                    'qty' => (float)($c['qty'] ?? ($order->filled_quantity ?? 0)),
                    'avg_entry_price' => $c['avgEntryPrice'] ?? ($order->average_price ?? 0),
                    'avg_exit_price' => $c['avgExitPrice'] ?? ($order->average_price ?? 0),
                    'pnl' => (float)($c['realizedPnl'] ?? 0),
                    'order_id' => $order->order_id,
                    'closed_at' => $closedAt,
                ]);
            }

            $this->info("ثبت معاملات بسته شده برای سفارش {$order->order_id} تکمیل شد");
        } catch (Exception $e) {
            $this->warn("خطا در بررسی بسته شدن موقعیت و ثبت معامله: " . $e->getMessage());
        }
    }

    private function normalizeClosedPnl(string $exchangeName, $raw): array
    {
        // Decode JSON strings into arrays
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            }
        }
        $list = (is_array($raw) && isset($raw['list'])) ? $raw['list'] : $raw;
        if (!is_array($list)) {
            if (!app()->environment('local')) {
                $this->warn("[Real] شکل لیست PnL بسته نامعتبر برای {$exchangeName}");
            }
            return [];
        }
        if (empty($list)) {
            if (!app()->environment('local')) {
                $this->info("[Real] لیست PnL بسته خالی برای {$exchangeName}");
            }
            return [];
        }
    
        $out = [];
        foreach ($list as $item) {
            if (!is_array($item)) { continue; }
            $orderId = $item['orderId'] ?? ($item['order_id'] ?? null);
            $symbol = $item['symbol'] ?? null;
            $sideRaw = $item['side'] ?? null;
            $qty = $item['qty'] ?? ($item['size'] ?? null);
            $avgEntry = $item['avgEntryPrice'] ?? ($item['avg_entry_price'] ?? ($item['avgPrice'] ?? ($item['entryPrice'] ?? null)));
            $avgExit = $item['avgExitPrice'] ?? ($item['avg_exit_price'] ?? ($item['closePrice'] ?? ($item['avgPrice'] ?? null)));
            $pnl = $item['closedPnl'] ?? ($item['realisedPnl'] ?? ($item['realizedPnl'] ?? 0));
            $closedAt = $item['updatedTime'] ?? ($item['createdTime'] ?? ($item['closedAt'] ?? null));
    
            // Normalize side to Buy/Sell where possible
            $side = null;
            if ($sideRaw !== null) {
                $s = strtolower((string)$sideRaw);
                if (in_array($s, ['buy','long'])) { $side = 'Buy'; }
                elseif (in_array($s, ['sell','short'])) { $side = 'Sell'; }
                else { $side = $sideRaw; }
            }
    
            if (!$orderId || !$symbol) {
                if (!app()->environment('local')) {
                    $this->warn("[Real] رویداد PnL بسته فاقد شناسه سفارش یا نماد؛ نادیده گرفته شد");
                }
                continue;
            }
            if ($avgEntry === null || $avgExit === null) {
                if (!app()->environment('local')) {
                    $this->warn("[Real] رویداد PnL بسته برای {$symbol} دارای مقادیر ناقص قیمت ورود/خروج است");
                }
            }
    
            $out[] = [
                'orderId' => $orderId,
                'symbol' => $symbol,
                'side' => $side,
                'qty' => $qty !== null ? (float)$qty : null,
                'avgEntryPrice' => $avgEntry !== null ? (float)$avgEntry : null,
                'avgExitPrice' => $avgExit !== null ? (float)$avgExit : null,
                'realizedPnl' => (float)$pnl,
                'closedAt' => $closedAt ? (int)$closedAt : null,
            ];
        }
        return $out;
    }

    /**
     * Detect TP/SL or reduce-only closing orders across exchanges
     */
    private function isTpSlOrClosing(array $exchangeOrder, string $exchangeName): bool
    {
        $reduceOnly = ($exchangeOrder['reduceOnly'] ?? false) === true;
        $closeOnTrigger = ($exchangeOrder['closeOnTrigger'] ?? false) === true;
        $hasTrigger = isset($exchangeOrder['triggerPrice']) || isset($exchangeOrder['stopPrice']);

        $stopOrderType = strtolower((string)($exchangeOrder['stopOrderType'] ?? ''));
        $isStopOrderType = in_array($stopOrderType, ['stop', 'stoploss', 'sl', 'takeprofit', 'tp', 'trailing_stop']);

        $orderType = strtoupper((string)($exchangeOrder['orderType'] ?? ($exchangeOrder['type'] ?? '')));
        $isStopType = in_array($orderType, ['STOP', 'STOP_MARKET', 'TAKE_PROFIT', 'TAKE_PROFIT_MARKET']);

        // Only treat explicit reduce-only or close-on-trigger as closing
        // Do NOT treat mere presence of stopLoss/takeProfit on normal orders as closing.
        if ($reduceOnly || $closeOnTrigger || $hasTrigger || $isStopType || $isStopOrderType) {
            return true;
        }
        return false;
    }

    // Within syncLifecycleForExchange after syncing orders, invoke open positions sync
    private function syncOpenPositions($exchangeService, \App\Models\UserExchange $userExchange): void
    {
        // Skip exchange calls in local environment
        if (app()->environment('local')) {
            $this->info("[Real] در محیط محلی، همگام‌سازی موقعیت‌های باز انجام نمی‌شود");
            return;
        }
        // Enforce hedge-only mode for open position syncing
        if (($userExchange->position_mode ?? null) !== 'hedge') {
            $this->warn("[Real] حالت موقعیت کاربر ({$userExchange->position_mode}) پشتیبانی نمی‌شود؛ همگام‌سازی موقعیت‌های باز فقط در حالت hedge انجام می‌شود");
            return;
        }
        try {
            $positionsRaw = $exchangeService->getPositions();
            $positions = $this->normalizePositionsList($userExchange->exchange_name, $positionsRaw);
    
            foreach ($positions as $rawPosition) {
                if (!is_array($rawPosition)) { continue; }
                $position = $this->mapRawPositionToCommon($userExchange->exchange_name, $rawPosition);
                if (!$position) { continue; }
                if (empty($position['size']) || (float)$position['size'] == 0.0) { continue; }
    
                $trade = \App\Models\Trade::where('user_exchange_id', $userExchange->id)
                    ->where('is_demo', false)
                    ->whereNull('closed_at')
                    ->where('symbol', $position['symbol'])
                    ->where('side', $position['side'])
                    ->first();
    
                // Avoid creating dummy trades: only update existing open trade
                if ($trade) {
                    $trade->qty = (float)$position['size'];
                    if ($position['entryPrice'] !== null) {
                        $trade->avg_entry_price = $position['entryPrice'];
                    }
                    if ($position['leverage'] !== null) {
                        $trade->leverage = $position['leverage'];
                    }
                    if ($position['unrealizedPnl'] !== null) {
                        $trade->pnl = $position['unrealizedPnl'];
                    }
                    $trade->save();
                }
            }
        } catch (\Exception $e) {
            $this->warn("[Real] خطا در همگام‌سازی موقعیت‌های باز: " . $e->getMessage());
        }
    }
}