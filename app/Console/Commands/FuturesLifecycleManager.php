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
        // Get the oldest pending/filled order date for this user exchange
        $oldestOrder = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->whereIn('status', ['pending', 'filled', 'partially_filled'])
            ->orderBy('created_at', 'asc')
            ->first();

        // Calculate start time: oldest order date minus 15 minutes, or 24 hours ago if no orders
        $startTime = null;
        if ($oldestOrder) {
            $startTime = $oldestOrder->created_at->subMinutes(15)->timestamp * 1000; // Convert to milliseconds
        } else {
            $startTime = now()->subHours(24)->timestamp * 1000; // Last 24 hours if no orders
        }

        try {
            // Get orders from exchange with time filtering
            $exchangeOrders = $exchangeService->getOrderHistory(null, 100, $startTime);
            $orders = $exchangeOrders['list'] ?? [];

            $this->info("دریافت {count($orders)} سفارش از صرافی {$userExchange->exchange_name}");

            foreach ($orders as $exchangeOrder) {
                $this->syncExchangeOrderToDatabase($exchangeOrder, $userExchange);
            }

            // Sync PnL records for hedge mode
            $this->syncPnlRecords($exchangeService, $userExchange);

        } catch (Exception $e) {
            $this->error("خطا در همگام‌سازی سفارشات از صرافی: " . $e->getMessage());
        }
    }

    /**
     * Sync individual exchange order to database
     */
    private function syncExchangeOrderToDatabase($exchangeOrder, UserExchange $userExchange)
    {
        // Extract order ID based on exchange format
        $orderId = $this->extractOrderId($exchangeOrder, $userExchange->exchange_name);
        
        if (!$orderId) {
            return; // Skip if we can't get order ID
        }

        // Find order in our database
        $order = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->where('order_id', $orderId)
            ->first();

        if ($order) {
            // Order exists in database - update status
            $newStatus = $this->mapExchangeStatus($this->extractOrderStatus($exchangeOrder, $userExchange->exchange_name));
            
            if ($order->status !== $newStatus) {
                $order->status = $newStatus;
                
                // Update filled quantity if available
                $filledQty = $this->extractFilledQuantity($exchangeOrder, $userExchange->exchange_name);
                if ($filledQty !== null) {
                    $order->filled_quantity = $filledQty;
                }
                
                // Update average price if available
                $avgPrice = $this->extractAveragePrice($exchangeOrder, $userExchange->exchange_name);
                if ($avgPrice !== null) {
                    $order->average_price = $avgPrice;
                }

                // If order is closed, set closed_at
                if (in_array($newStatus, ['filled', 'canceled', 'expired', 'closed'])) {
                    $order->closed_at = now();
                }
                
                $order->save();
                $this->info("وضعیت سفارش {$orderId} به {$newStatus} تغییر یافت");
            }
        } else {
            // Order not found in database - this is a closed order, sync PnL
            $this->syncClosedOrderPnl($exchangeOrder, $userExchange);
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
        // Only sync if position mode is hedge
        if ($userExchange->position_mode !== 'hedge') {
            return;
        }

        try {
            // Get positions from exchange
            $positions = $exchangeService->getPositions();
            if($userExchange->exchange_name == 'bybit')
			{
				$positions = $positions['list'];
			}

            foreach ($positions as $position) {
                // Skip positions with zero size
                if ($position['size'] == 0) {
                    continue;
                }

                // Find or create trade record
                $trade = Trade::where('user_exchange_id', $userExchange->id)
                    ->where('is_demo', false)
                    ->where('symbol', $position['symbol'])
                    ->where('side', $position['side'])
                    ->first();

                if (!$trade) {
                    // Create new trade record
                    $trade = new Trade([
                        'user_exchange_id' => $userExchange->id,
                        'is_demo' => false,
                        'symbol' => $position['symbol'],
                        'side' => $position['side'],
                        'order_type' => 'Market', // Default order type for position tracking
                        'leverage' => $position['leverage'] ?? 1.0,
                        'qty' => $position['size'],
                        'avg_entry_price' => $position['entryPrice'] ?? 0,
                        'avg_exit_price' => 0, // Not applicable for open positions
                        'pnl' => $position['unrealizedPnl'] ?? 0,
                        'order_id' => $position['orderId'] ?? 'position_' . uniqid(), // Generate unique ID if not available
                        'closed_at' => now(), // Use current time for tracking
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $trade->save();
                } else {
                    // Update existing trade
                    $trade->qty = $position['size'];
                    $trade->avg_entry_price = $position['entryPrice'] ?? $trade->avg_entry_price;
                    $trade->leverage = $position['leverage'] ?? $trade->leverage;
                    $trade->pnl = $position['unrealizedPnl'] ?? 0;
                    $trade->updated_at = now();
                    $trade->save();
                }
            }

            // Mark closed positions
            $symbols = collect($positions)->pluck('symbol')->unique();
            Trade::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', false)
                ->whereNotIn('symbol', $symbols)
                ->update(['pnl' => 0, 'updated_at' => now()]);

        } catch (Exception $e) {
            $this->warn("خطا در همگام‌سازی سوابق PnL: " . $e->getMessage());
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