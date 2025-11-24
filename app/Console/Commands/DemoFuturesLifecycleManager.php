<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;
use App\Models\Trade;
use App\Models\UserBan;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\DB;
use App\Jobs\CollectOrderCandlesJob;
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
     * Sync lifecycle for a specific exchange using the new approach:
     * Get orders from exchange and sync to database
     */
    private function syncLifecycleForExchange($exchangeService, User $user, UserExchange $userExchange)
    {
        // Only consider orders that have an open trade (closed_at is null) or have no trade record
        $oldestOrder = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->whereIn('status', ['pending', 'filled'])
            ->where(function($q) {
                $q->whereHas('trade', function($t) {
                    $t->whereNull('closed_at');
                })
                ->orWhereDoesntHave('trade');
            })
            ->orderBy('created_at', 'asc')
            ->first();

        // Calculate start time: oldest order date minus 5 minutes
        $startTime = null;
        if ($oldestOrder) {
            $startTime = $oldestOrder->created_at->subMinutes(5)->timestamp * 1000; // Convert to milliseconds
            try {
            // Get orders from exchange with time filtering
            $exchangeOrders = $exchangeService->getOrderHistory(null, 100, $startTime);
            $orders = $exchangeOrders['list'] ?? [];

            $this->info("دریافت " . count($orders) . " سفارش از صرافی {$userExchange->exchange_name} (دمو)");

            foreach ($orders as $exchangeOrder) {
                $this->syncExchangeOrderToDatabase($exchangeOrder, $userExchange);
            }
            // پس از همگام‌سازی سفارش‌ها، موقعیت‌های باز را نیز همگام‌سازی می‌کنیم (دمو)
            $this->syncPnlRecords($exchangeService, $userExchange);

            } catch (Exception $e) {
                $this->error("خطا در همگام‌سازی سفارشات از صرافی (دمو): " . $e->getMessage());
            }
        } else {
            $this->info("سفارشی یافت نشد");
        }
        // تأیید همگام‌سازی معاملات بسته و علامت‌گذاری موارد ناموفق
        $this->verifyClosedTradesSynchronization($exchangeService, $userExchange);
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
            return; // silently skip system-created TP/SL or closing orders (demo)
        }

        $order = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->where('order_id', $orderId)
            ->whereNotIn('status', ['expired'])
            ->first();

        if ($order) {
            $newStatus = $this->mapExchangeStatus($this->extractOrderStatus($exchangeOrder, $userExchange->exchange_name));
            if ($order->status !== $newStatus) {
                DB::transaction(function () use ($order, $newStatus, $exchangeOrder, $userExchange, $orderId) {
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
                    if ($newStatus === 'filled') {
                        $order->filled_at = now();
                    }
                    $order->save();
                    $this->info("[Demo] وضعیت سفارش {$orderId} به {$newStatus} تغییر یافت");

                    // ایجاد/به‌روزرسانی معامله باز در حالت FILLED (دمو)
                    if ($newStatus === 'filled') {
                        $symbol = $this->extractSymbol($exchangeOrder, $userExchange->exchange_name);
                        $side = $this->extractSide($exchangeOrder, $userExchange->exchange_name);
                        $qty = $this->extractFilledQuantity($exchangeOrder, $userExchange->exchange_name);
                        $avgPrice = $this->extractAveragePrice($exchangeOrder, $userExchange->exchange_name);

                        if ($symbol && $side && $qty !== null && $avgPrice !== null) {
                            $existingOpen = Trade::where('user_exchange_id', $userExchange->id)
                                ->where('is_demo', true)
                                ->where('order_id', $order->order_id)
                                ->whereNull('closed_at')
                                ->first();

                            if ($existingOpen) {
                                $existingOpen->qty = (float)$qty;
                                $existingOpen->avg_entry_price = (float)$avgPrice;
                                $existingOpen->save();
                                $this->info("[Demo] معامله باز برای سفارش {$orderId} به‌روزرسانی شد");
                            } else {
                                Trade::create([
                                    'user_exchange_id' => $userExchange->id,
                                    'is_demo' => true,
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
                                $this->info("[Demo] معامله باز برای سفارش {$orderId} ثبت شد");
                            }
                        }
                    }
                });
            }
        } else {
            // Skip unknown orders to ensure only system-created orders are processed, without logging (demo)
            return;
        }
    }

    private function syncPnlRecords($exchangeService, UserExchange $userExchange)
    {

        try {
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
                ->where('is_demo', true)
                ->whereNull('closed_at')
                ->get();

            foreach ($openTrades as $trade) {
                DB::transaction(function () use ($trade, $normalized, $exchangeService, $userExchange) {
                    // تلاش برای یافتن تطابق در موقعیت‌های باز صرافی
                    $matchedPosition = null;
                    foreach ($normalized as $p) {
                        if (($p['symbol'] ?? null) === $trade->symbol
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
                        return; // continue outer loop
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
                        $trade->synchronized = 1; // verified sync with exchange
                        $trade->updated_at = now();
                        $trade->save();
                   }
                });
            }
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
            $this->warn("[Demo] شکل لیست موقعیت‌ها نامعتبر برای صرافی {$exchangeName}");
            return [];
        }
        if (is_array($list) && empty($list)) {
            $this->info("[Demo] لیست موقعیت‌ها خالی برای صرافی {$exchangeName}");
        }
        return $list;
    }

    private function mapRawPositionToCommon(string $exchangeName, array $raw): ?array
    {
        $symbol = $raw['symbol'] ?? null;
        if (!$symbol) {
            $this->warn("[Demo] نماد نامشخص در موقعیت خام صرافی {$exchangeName}");
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
        if ($side === null) {
            $this->warn("[Demo] جهت پوزیشن نامشخص برای نماد {$symbol} در صرافی {$exchangeName}");
        }

        // Size/qty across exchanges
        $size = null;
        if (isset($raw['size'])) {
            $size = (float)$raw['size'];
        } elseif (isset($raw['positionAmt'])) {
            $size = abs((float)$raw['positionAmt']);
        }
        if (($size === null || $size == 0.0)) {
            $this->info("[Demo] حجم/سایز موقعیت نامشخص یا صفر برای نماد {$symbol} در صرافی {$exchangeName}");
        }

        // Entry price
        $entryPrice = $raw['entryPrice'] ?? ($raw['avgPrice'] ?? ($raw['avg_entry_price'] ?? null));
        if ($entryPrice !== null) { $entryPrice = (float)$entryPrice; }
        if ($entryPrice === null) {
            $this->info("[Demo] قیمت ورود نامشخص برای نماد {$symbol} در صرافی {$exchangeName}");
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
        if ($leverage === null) {
            $this->info("[Demo] لورج نامشخص برای نماد {$symbol} در صرافی {$exchangeName}");
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

        $stopOrderType = strtolower((string)($exchangeOrder['stopOrderType'] ?? ''));
        $isStopOrderType = in_array($stopOrderType, ['stop', 'stoploss', 'sl', 'takeprofit', 'tp', 'trailing_stop']);

        $orderType = strtoupper((string)($exchangeOrder['orderType'] ?? ($exchangeOrder['type'] ?? '')));
        $isStopType = in_array($orderType, ['STOP', 'STOP_MARKET', 'TAKE_PROFIT', 'TAKE_PROFIT_MARKET']);

        // Only treat explicit reduce-only or close-on-trigger as closing
        if ($reduceOnly || $closeOnTrigger) {
            return true;
        }

        // Require a trigger together with stop/TP semantics
        if ($hasTrigger && ($isStopType || $isStopOrderType)) {
            return true;
        }

        return false;
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
            $this->warn("[Demo] شکل لیست PnL بسته نامعتبر برای {$exchangeName}");
            return [];
        }
        if (empty($list)) {
            $this->info("[Demo] لیست PnL بسته خالی برای {$exchangeName}");
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
                $this->warn("[Demo] رویداد PnL بسته فاقد شناسه سفارش یا نماد؛ نادیده گرفته شد");
                continue;
            }
            if ($avgEntry === null || $avgExit === null) {
                $this->warn("[Demo] رویداد PnL بسته برای {$symbol} دارای مقادیر ناقص قیمت ورود/خروج است");
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
     * Verify closed trades against exchange PnL history and mark synchronization status.
     * synchronized: 1 = verified, 2 = not found (unverified)
     */
    private function verifyClosedTradesSynchronization($exchangeService, UserExchange $userExchange): void
    {


        try {
            $trades = Trade::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', true)
                ->whereNotNull('closed_at')
                ->where('synchronized', 0)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($trades->isEmpty()) { return; }

            $oldestCreatedAt = $trades->first()->created_at;
            $startTime = $oldestCreatedAt ? $oldestCreatedAt->timestamp * 1000 : null;

            // Group trades by symbol to minimize requests (one request per symbol)
            $bySymbol = $trades->groupBy('symbol');

            foreach ($bySymbol as $symbol => $symbolTrades) {
                $closedRaw = $exchangeService->getClosedPnl($symbol, 200, $startTime);
                $closedList = $this->normalizeClosedPnl($userExchange->exchange_name, $closedRaw);

                // Build quick lookup sets per symbol
                $records = array_values(array_filter($closedList, function($c) use ($symbol) {
                    return isset($c['symbol']) && $c['symbol'] === $symbol;
                }));

                foreach ($symbolTrades as $trade) {
                    $matched = null;
                    $epsilonQty = 1e-8;
                    $epsilonPrice = 1e-6;

                    // 1) Direct match by orderId or exact fields
                    foreach ($records as $c) {
                        $idMatch = isset($c['orderId']) && $trade->order_id && (string)$c['orderId'] === (string)$trade->order_id;
                        $fieldsMatch = isset($c['qty'], $c['avgEntryPrice'])
                            && abs((float)$c['qty'] - (float)$trade->qty) <= $epsilonQty
                            && abs((float)$c['avgEntryPrice'] - (float)$trade->avg_entry_price) <= $epsilonPrice;
                        if ($idMatch || $fieldsMatch) { $matched = [$c]; break; }
                    }

                    // 2) If not matched, try multi-record match (split closures)
                    if (!$matched) {
                        $cands = array_values(array_filter($records, function($c) use ($trade, $epsilonPrice) {
                            if (!isset($c['avgEntryPrice'], $c['qty'])) { return false; }
                            return abs((float)$c['avgEntryPrice'] - (float)$trade->avg_entry_price) <= $epsilonPrice;
                        }));

                        // Sum all candidates first
                        $sumQty = 0.0; $sumPnl = 0.0; $weightedExit = 0.0; $exitWeight = 0.0;
                        foreach ($cands as $c) {
                            $q = (float)($c['qty'] ?? 0.0); $sumQty += $q;
                            if (isset($c['realizedPnl'])) { $sumPnl += (float)$c['realizedPnl']; }
                            if (isset($c['avgExitPrice'])) { $weightedExit += $q * (float)$c['avgExitPrice']; $exitWeight += $q; }
                        }
                        if (abs($sumQty - (float)$trade->qty) <= $epsilonQty && $sumQty > 0) {
                            $matched = $cands;
                            $trade->avg_exit_price = $exitWeight > 0 ? ($weightedExit / $exitWeight) : $trade->avg_exit_price;
                            $trade->pnl = $sumPnl;
                        } else {
                            // Try pair combinations
                            $n = count($cands); $foundPair = null;
                            for ($i = 0; $i < $n; $i++) {
                                for ($j = $i + 1; $j < $n; $j++) {
                                    $q = (float)($cands[$i]['qty'] ?? 0.0) + (float)($cands[$j]['qty'] ?? 0.0);
                                    if (abs($q - (float)$trade->qty) <= $epsilonQty) { $foundPair = [$cands[$i], $cands[$j]]; break; }
                                }
                                if ($foundPair) { break; }
                            }
                            if ($foundPair) {
                                $matched = $foundPair;
                                $sumPnl = 0.0; $weightedExit = 0.0; $exitWeight = 0.0;
                                foreach ($foundPair as $c) {
                                    $q = (float)($c['qty'] ?? 0.0);
                                    if (isset($c['realizedPnl'])) { $sumPnl += (float)$c['realizedPnl']; }
                                    if (isset($c['avgExitPrice'])) { $weightedExit += $q * (float)$c['avgExitPrice']; $exitWeight += $q; }
                                }
                                $trade->avg_exit_price = $exitWeight > 0 ? ($weightedExit / $exitWeight) : $trade->avg_exit_price;
                                $trade->pnl = $sumPnl;
                            }
                        }
                    }

                    // Finalize
                    if ($matched) {
                        // If single record, prefer its explicit values
                        if (count($matched) === 1) {
                            $m = $matched[0];
                            if (array_key_exists('avgExitPrice', $m) && $m['avgExitPrice'] !== null) {
                                $trade->avg_exit_price = (float)$m['avgExitPrice'];
                            }
                            if (array_key_exists('realizedPnl', $m) && $m['realizedPnl'] !== null) {
                                $trade->pnl = (float)$m['realizedPnl'];
                            }
                        }
                        $trade->synchronized = 1;
                    } else {
                        $trade->synchronized = 2; // mark as unverified sync
                    }
                    $trade->save();

                    // Create bans based on closure outcomes (demo)
                    try {
                        $userId = $userExchange->user_id;
                        $isDemo = (bool)$trade->is_demo;

                        // Heuristic: exchange force close detected if exit far from both TP and SL (>0.2%)
                        $order = $trade->order;
                        if ($order && $trade->avg_exit_price) {
                            $exit = (float)$trade->avg_exit_price;
                            $tpDelta = isset($order->tp) ? abs(((float)$order->tp - $exit) / $exit) : null;
                            $slDelta = isset($order->sl) ? abs(((float)$order->sl - $exit) / $exit) : null;
                            if ($trade->closed_at !== null
                                && ((int)($trade->closed_by_user ?? 0) !== 1)
                                && $tpDelta !== null && $slDelta !== null
                                && $tpDelta > 0.002 && $slDelta > 0.002) {
                                $existsExchangeForceClose = UserBan::active()
                                    ->forUser($userId)
                                    ->accountType($isDemo)
                                    ->where('ban_type', 'exchange_force_close')
                                    ->exists();
                                if (!$existsExchangeForceClose) {
                                    UserBan::create([
                                        'user_id' => $userId,
                                        'is_demo' => $isDemo,
                                        'trade_id' => $trade->id,
                                        'ban_type' => 'exchange_force_close',
                                        'starts_at' => now(),
                                        'ends_at' => now()->addDays(3),
                                    ]);
                                }
                            }
                        }

                        // Loss-based bans
                        if ($trade->pnl !== null && (float)$trade->pnl < 0) {
                            // Single loss: 1 hour ban
                            $existsSingle = UserBan::where('trade_id', $trade->id)->where('ban_type', 'single_loss')->exists();
                            if (!$existsSingle) {
                                UserBan::create([
                                    'user_id' => $userId,
                                    'is_demo' => $isDemo,
                                    'trade_id' => $trade->id,
                                    'ban_type' => 'single_loss',
                                    'starts_at' => now(),
                                    'ends_at' => now()->addHours(1),
                                ]);
                            }

                            // Two consecutive losses: 24 hours ban
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
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Log ban creation errors for demo as well
                        $this->error("[Demo][Ban] خطا در ایجاد محرومیت: " . $e->getMessage());
                        $this->error("[Demo][Ban] Stack trace: " . $e->getTraceAsString());
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("[Demo] خطا در تأیید همگام‌سازی معاملات بسته: " . $e->getMessage());
        }
    }
}