<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\SpotOrder;
use App\Services\Exchanges\ExchangeFactory;
use Exception;

class SpotOrderLifecycleManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:lifecycle {--user=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مدیریت چرخه حیات سفارشات نقدی برای تمام کاربران تایید شده';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('شروع مدیریت چرخه حیات سفارشات نقدی...');

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

        $this->info('مدیریت چرخه حیات سفارشات نقدی با موفقیت تکمیل شد.');
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
            ->where('spot_access', true)
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

            // Sync spot orders for this exchange
            $this->syncSpotOrdersForExchange($exchangeService, $user, $userExchange);

        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email}: " . $e->getMessage());
        }
    }

    /**
     * Sync order statuses with exchange
     */
    private function syncOrderStatuses($exchangeService, UserExchange $userExchange)
    {
        // Get all pending spot orders for this user exchange (real mode)
        $orders = SpotOrder::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->whereIn('status', ['pending', 'partially_filled'])
            ->get();

        foreach ($orders as $order) {
            try {
                // Get order status from exchange
                $exchangeOrder = $exchangeService->getSpotOrder($order->order_id, $order->symbol);

                // Update order status based on exchange response
                $newStatus = $this->mapExchangeStatus($exchangeOrder['status']);
                
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
                    
                    // Update fee if available
                    if (isset($exchangeOrder['fee'])) {
                        $order->fee = $exchangeOrder['fee']['cost'] ?? 0;
                        $order->fee_currency = $exchangeOrder['fee']['currency'] ?? null;
                    }
                    
                    $order->save();
                    
                    $this->info("وضعیت سفارش نقدی {$order->order_id} به {$newStatus} تغییر یافت");
                }

            } catch (Exception $e) {
                // Order might be deleted/expired on exchange
                if (strpos($e->getMessage(), 'not found') !== false || 
                    strpos($e->getMessage(), 'does not exist') !== false) {
                    $order->status = 'deleted';
                    $order->save();
                    $this->info("سفارش نقدی {$order->order_id} به عنوان حذف شده علامت‌گذاری شد");
                }
            }
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