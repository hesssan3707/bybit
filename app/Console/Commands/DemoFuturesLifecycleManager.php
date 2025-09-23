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
    protected $signature = 'demo:futures:lifecycle {--user=}';

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

            // Sync order statuses
            $this->syncOrderStatuses($exchangeService, $userExchange);

            // Sync PnL records for hedge mode
            $this->syncPnlRecords($exchangeService, $userExchange);

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

            foreach ($positions as $position) {
                // Skip positions with zero size
                if ($position['size'] == 0) {
                    continue;
                }

                // Find or create trade record
                $trade = Trade::where('user_exchange_id', $userExchange->id)
                    ->where('is_demo', true)
                    ->where('symbol', $position['symbol'])
                    ->where('side', $position['side'])
                    ->first();

                if (!$trade) {
                    // Create new trade record
                    $trade = new Trade([
                        'user_exchange_id' => $userExchange->id,
                        'is_demo' => true,
                        'symbol' => $position['symbol'],
                        'side' => $position['side'],
                        'quantity' => $position['size'],
                        'entry_price' => $position['entryPrice'] ?? 0,
                        'pnl' => $position['unrealizedPnl'] ?? 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $trade->save();
                } else {
                    // Update existing trade
                    $trade->quantity = $position['size'];
                    $trade->entry_price = $position['entryPrice'] ?? $trade->entry_price;
                    $trade->pnl = $position['unrealizedPnl'] ?? 0;
                    $trade->updated_at = now();
                    $trade->save();
                }
            }

            // Mark closed positions
            $symbols = collect($positions)->pluck('symbol')->unique();
            Trade::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', true)
                ->whereNotIn('symbol', $symbols)
                ->update(['pnl' => 0, 'updated_at' => now()]);

        } catch (Exception $e) {
            $this->warn("خطا در همگام‌سازی سوابق PnL دمو: " . $e->getMessage());
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