<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\SpotOrder;
use App\Services\Exchanges\ExchangeFactory;
use Exception;

class DemoSpotOrderLifecycleManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:spot:lifecycle {--user=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مدیریت چرخه حیات سفارشات نقدی دمو برای تمام کاربران تایید شده';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('شروع مدیریت چرخه حیات سفارشات نقدی دمو...');

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

        $this->info('مدیریت چرخه حیات سفارشات نقدی دمو با موفقیت تکمیل شد.');
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
            ->where('demo_spot_access', true)
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

            // Sync spot orders for this exchange
            $this->syncSpotOrdersForExchange($exchangeService, $user, $userExchange);

        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} (دمو) برای کاربر {$user->email}: " . $e->getMessage());
        }
    }

    /**
     * Sync spot orders workflow for this exchange (demo)
     */
    private function syncSpotOrdersForExchange($exchangeService, User $user, UserExchange $userExchange)
    {
        // Currently we focus on syncing order statuses
        $this->syncOrderStatuses($exchangeService, $userExchange);
    }

    /**
     * Sync order statuses with exchange
     */
    private function syncOrderStatuses($exchangeService, UserExchange $userExchange)
    {
        // Get all pending spot orders for this user exchange (demo mode)
        $orders = SpotOrder::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->whereIn('status', ['New', 'PartiallyFilled'])
            ->get();

        foreach ($orders as $order) {
            try {
                // Get order status from exchange with cross-exchange handling
                $exchangeOrder = $this->getExchangeSpotOrder($exchangeService, $userExchange, $order->order_id, $order->symbol);

                // Normalize different exchange response shapes
                $normalized = $this->normalizeSpotExchangeOrder($exchangeOrder, $userExchange->exchange_name);

                // Update order status based on exchange response (mapped to model statuses)
                $newStatus = $this->mapExchangeStatus($normalized['status'] ?? null);
                
                if ($order->status !== $newStatus) {
                    $order->status = $newStatus;
                    
                    // Update executed quantity if available
                    if (isset($normalized['filled'])) {
                        $order->executed_qty = $normalized['filled'];
                    }

                    // Update executed (average) price if available
                    if (isset($normalized['average'])) {
                        $order->executed_price = $normalized['average'];
                    }

                    // Update commission if available
                    if (isset($normalized['fee'])) {
                        $order->commission = $normalized['fee']['cost'] ?? 0;
                        $order->commission_asset = $normalized['fee']['currency'] ?? null;
                    }
                    
                    $order->save();
                    
                    $this->info("وضعیت سفارش نقدی دمو {$order->order_id} به {$newStatus} تغییر یافت");
                }

            } catch (Exception $e) {
                // Order might be deleted/expired on exchange
                if (strpos($e->getMessage(), 'not found') !== false || 
                    strpos($e->getMessage(), 'does not exist') !== false) {
                    // Align with enum values: mark as cancelled when missing on exchange
                    $order->status = 'cancelled';
                    $order->save();
                    $this->info("سفارش نقدی دمو {$order->order_id} به عنوان کنسل‌شده علامت‌گذاری شد");
                }
            }
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
                return 'New';
            case 'FILLED':
                return 'Filled';
            case 'CANCELED':
            case 'CANCELLED':
                // Enum uses lowercase 'cancelled'
                return 'cancelled';
            case 'EXPIRED':
                // No 'Expired' in enum; treat as cancelled
                return 'cancelled';
            default:
                return 'New';
        }
    }

    /**
     * Cross-exchange getter for a spot order
     */
    private function getExchangeSpotOrder($exchangeService, UserExchange $userExchange, string $orderId, string $symbol): array
    {
        $exchange = strtolower($userExchange->exchange_name);
        try {
            switch ($exchange) {
                case 'bybit':
                    // Bybit supports fetching by orderId only
                    return $exchangeService->getSpotOrder($orderId);
                case 'bingx':
                    // BingX requires symbol for spot order lookup
                    if (method_exists($exchangeService, 'getSpotOrderWithSymbol')) {
                        return $exchangeService->getSpotOrderWithSymbol($orderId, $symbol);
                    }
                    // Fallback if service doesn't expose the helper
                    return $exchangeService->getSpotOrder($orderId);
                case 'binance':
                    // Use dedicated Binance spot service due to interface differences
                    $spotService = new \App\Services\Exchanges\BinanceSpotApiService($userExchange->is_demo_active);
                    $spotService->setCredentials($userExchange->demo_api_key ?? '', $userExchange->demo_api_secret ?? '');
                    return $spotService->getSpotOrderWithSymbol($orderId, $symbol);
                default:
                    return $exchangeService->getSpotOrder($orderId);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Normalize spot order response across exchanges to a common shape
     * Returns ['status' => string, 'filled' => float|null, 'average' => float|null, 'fee' => ['cost' => float, 'currency' => string]|null]
     */
    private function normalizeSpotExchangeOrder($exchangeOrder, string $exchangeName): array
    {
        $name = strtolower($exchangeName);
        $normalized = [
            'status' => null,
            'filled' => null,
            'average' => null,
            'fee' => null,
        ];

        // Unwrap common Bybit v5 response format
        $item = $exchangeOrder;
        if (isset($exchangeOrder['result']['list']) && is_array($exchangeOrder['result']['list'])) {
            $item = $exchangeOrder['result']['list'][0] ?? [];
        } elseif (isset($exchangeOrder['list']) && is_array($exchangeOrder['list'])) {
            $item = $exchangeOrder['list'][0] ?? [];
        }

        switch ($name) {
            case 'bybit':
                $normalized['status'] = $item['orderStatus'] ?? ($item['status'] ?? null);
                $normalized['filled'] = isset($item['cumExecQty']) ? (float)$item['cumExecQty'] : (isset($item['executedQty']) ? (float)$item['executedQty'] : null);
                $normalized['average'] = isset($item['avgPrice']) ? (float)$item['avgPrice'] : null;
                // Fee fields may vary; attempt common keys
                if (isset($item['cumExecFee']) || isset($item['feeAmount'])) {
                    $normalized['fee'] = [
                        'cost' => isset($item['cumExecFee']) ? (float)$item['cumExecFee'] : (float)($item['feeAmount'] ?? 0),
                        'currency' => $item['feeCurrency'] ?? ($item['feeToken'] ?? null),
                    ];
                }
                break;

            case 'binance':
                $normalized['status'] = $item['status'] ?? null;
                $normalized['filled'] = isset($item['executedQty']) ? (float)$item['executedQty'] : null;
                // Binance does not return avgPrice directly; compute if possible
                if (!empty($item['executedQty']) && !empty($item['cummulativeQuoteQty']) && (float)$item['executedQty'] > 0) {
                    $normalized['average'] = (float)$item['cummulativeQuoteQty'] / (float)$item['executedQty'];
                }
                break;

            case 'bingx':
                $normalized['status'] = $item['status'] ?? null;
                $normalized['filled'] = isset($item['executedQty']) ? (float)$item['executedQty'] : (isset($item['cumExecQty']) ? (float)$item['cumExecQty'] : null);
                $normalized['average'] = isset($item['avgPrice']) ? (float)$item['avgPrice'] : null;
                break;

            default:
                $normalized['status'] = $item['status'] ?? null;
                $normalized['filled'] = $item['filled'] ?? null;
                $normalized['average'] = $item['average'] ?? null;
                $normalized['fee'] = $item['fee'] ?? null;
        }

        return $normalized;
    }
}
