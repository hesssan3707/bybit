<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;
use App\Services\Exchanges\ExchangeFactory;
use Exception;

class FuturesSlTpSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'futures:sync-sltp {--user=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'همگام‌سازی سطوح Stop Loss و Take Profit برای کاربران در حالت سخت‌گیرانه';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('شروع همگام‌سازی Stop Loss و Take Profit...');

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

        $this->info('همگام‌سازی Stop Loss و Take Profit با موفقیت تکمیل شد.');
        return 0;
    }

    /**
     * Sync SL/TP for all users in strict mode
     */
    private function syncForAllUsers()
    {
        // Find all users who are in strict mode
        $users = User::where('future_strict_mode', true)->get();
        
        $this->info("پردازش {$users->count()} کاربر در حالت سخت‌گیرانه...");

        foreach ($users as $user) {
            $this->syncForUser($user);
        }
    }

    /**
     * Sync SL/TP for a specific user
     */
    private function syncForUser(User $user)
    {
        $this->info("پردازش کاربر: {$user->email}");

        // Get all user exchanges for this user
        $userExchanges = UserExchange::where('user_id', $user->id)
            ->where('futures_access', true)
            ->get();

        foreach ($userExchanges as $userExchange) {
            $this->syncForUserExchange($user, $userExchange);
        }
    }

    /**
     * Sync SL/TP for a specific user exchange
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

            // Get all filled orders for this user exchange (real mode)
            $filledOrders = Order::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', false)
                ->where('status', 'filled')
                ->get();

            if ($filledOrders->isEmpty()) {
                $this->info("هیچ سفارش تکمیل شده‌ای برای کاربر {$user->email} در صرافی {$userExchange->exchange_name} یافت نشد");
                return;
            }

            // Get current positions from exchange
            $positions = $exchangeService->getPositions();

            if($userExchange->exchange_name == 'bybit')
			{
				$positions = $positions['list'];
			}

            foreach ($filledOrders as $order) {
                // Find corresponding open position for this order
                $position = $this->findPositionForOrder($positions, $order);
                
                if (!$position) {
                    continue; // No open position for this order
                }

                // Check and sync Stop Loss
                $this->syncStopLoss($exchangeService, $order, $position);

                // Check and sync Take Profit
                $this->syncTakeProfit($exchangeService, $order, $position);
            }

        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email}: " . $e->getMessage());
        }
    }

    /**
     * Find position that corresponds to the given order
     */
    private function findPositionForOrder($positions, Order $order)
    {
        foreach ($positions as $position) {
            // Check if symbol matches
            if ($position['symbol'] !== $order->symbol) {
                continue;
            }

            // Check if position has size (is open)
            if (($position['size'] ?? 0) == 0) {
                continue;
            }

            // Check if side matches
            $positionSide = strtolower($position['side'] ?? '');
            $orderSide = strtolower($order->side);
            
            if ($positionSide === $orderSide) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Check and sync Stop Loss
     */
    private function syncStopLoss($exchangeService, Order $order, $position)
    {
        // Check if SL is defined in database
        if (!$order->sl) {
            return; // No SL defined in database
        }

        try {
            // Get current stop loss orders from exchange
            $stopLossOrders = $exchangeService->getStopLossOrders($order->symbol, $order->side);
            
            $currentSL = null;
            if (!empty($stopLossOrders)) {
                $currentSL = $stopLossOrders[0]['stopPrice'] ?? null;
            }

            // Check if SL exists and matches database
            if (!$currentSL || abs($currentSL - $order->sl) > 0.0001) {
                // SL doesn't exist or has changed, reset it
                
                // First, cancel any existing SL orders
                if (!empty($stopLossOrders)) {
                    foreach ($stopLossOrders as $slOrder) {
                        $exchangeService->cancelOrder($slOrder['orderId'], $order->symbol);
                    }
                }

                // Create new SL order
                $exchangeService->createStopLossOrder(
                    $order->symbol,
                    $order->side,
                    $position['size'],
                    $order->sl
                );

                $this->info("Stop Loss برای سفارش {$order->order_id} در قیمت {$order->sl} تنظیم شد");
            }

        } catch (Exception $e) {
            $this->warn("خطا در همگام‌سازی Stop Loss برای سفارش {$order->order_id}: " . $e->getMessage());
        }
    }

    /**
     * Check and sync Take Profit
     */
    private function syncTakeProfit($exchangeService, Order $order, $position)
    {
        // Check if TP is defined in database
        if (!$order->tp) {
            return; // No TP defined in database
        }

        try {
            // Get current take profit orders from exchange
            $takeProfitOrders = $exchangeService->getTakeProfitOrders($order->symbol, $order->side);
            
            $currentTP = null;
            if (!empty($takeProfitOrders)) {
                $currentTP = $takeProfitOrders[0]['stopPrice'] ?? null;
            }

            // Check if TP exists and matches database
            if ($currentTP && abs($currentTP - $order->tp) > 0.0001) {
                // TP exists but is different, delete it
                foreach ($takeProfitOrders as $tpOrder) {
                    $exchangeService->cancelOrder($tpOrder['orderId'], $order->symbol);
                }
                $currentTP = null; // Mark as deleted
            }

            // If no TP exists or we just deleted it, create new one
            if (!$currentTP) {
                // Create new TP order as reduce order to close the main position
                $exchangeService->createTakeProfitOrder(
                    $order->symbol,
                    $order->side,
                    $position['size'],
                    $order->tp,
                    'reduce' // Reduce order type to close position
                );

                $this->info("Take Profit برای سفارش {$order->order_id} در قیمت {$order->tp} تنظیم شد");
            }

        } catch (Exception $e) {
            $this->warn("خطا در همگام‌سازی Take Profit برای سفارش {$order->order_id}: " . $e->getMessage());
        }
    }
}