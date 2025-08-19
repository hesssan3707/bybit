<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Trade;
use App\Services\BybitApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BybitLifecycle extends Command
{
    protected $signature = 'bybit:lifecycle';
    protected $description = 'Syncs local order statuses with the exchange.';

    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        parent::__construct();
        $this->bybitApiService = $bybitApiService;
    }

    public function handle(): int
    {
        $this->info('Starting order status sync...');

        $ordersToCheck = Order::whereIn('status', ['pending', 'filled'])
            ->where('symbol', 'ETHUSDT')
            ->get();

        $this->info("Found " . $ordersToCheck->count() . " pending or filled ETHUSDT orders to check.");

        foreach ($ordersToCheck as $dbOrder) {
            try {
                $symbol = $dbOrder->symbol;

                // Logic for 'pending' orders: Check if they have been filled or externally canceled.
                if ($dbOrder->status === 'pending') {
                    // We use getHistoryOrder because we only care about final states (Filled, Cancelled).
                    // Active 'New' orders are handled by the 'bybit:enforce' command for expiration checks.
                    $orderResult = $this->bybitApiService->getHistoryOrder($dbOrder->order_id);
                    $order = $orderResult['list'][0] ?? null;

                    if (!$order) {
                        // This is expected if the order is still 'New' and not in history.
                        // We can safely skip it, as it's not filled or canceled yet.
                        continue;
                    }

                    $bybitStatus = $order['orderStatus'];

                    if ($bybitStatus === 'Filled') {
                        $dbOrder->status = 'filled';
                        $dbOrder->save();
                        $this->info("Order {$dbOrder->order_id} is now 'filled'.");
                    } elseif (in_array($bybitStatus, ['Cancelled', 'Deactivated', 'Rejected'])) {
                        $dbOrder->status = 'canceled';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("Marked order {$dbOrder->order_id} as canceled to match exchange status.");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Lifecycle check failed for order ID {$dbOrder->id} (Bybit ID {$dbOrder->order_id}): " . $e->getMessage());
            }
        }

        // --- Mark 'filled' orders as 'closed' if position is no longer open ---
        $filledOrders = Order::where('status', 'filled')->get();
        if ($filledOrders->isNotEmpty()) {
            $positionResult = $this->bybitApiService->getPositionInfo('ETHUSDT');
            $position = collect($positionResult['list'] ?? [])->firstWhere('symbol', 'ETHUSDT');
            if (!$position || (float)$position['size'] == 0) {
                foreach ($filledOrders as $order) {
                    $order->status = 'closed';
                    $order->closed_at = now();
                    $order->save();
                    $this->info("Marked order {$order->order_id} as closed as position is no longer active.");
                }
            }
        }

        // --- Sync P&L records ---
        $this->syncPnlRecords('ETHUSDT');
    }

    private function syncPnlRecords(string $symbol)
    {
        $this->info("Syncing P&L records for symbol: {$symbol}");

        $pnlResult = $this->bybitApiService->getClosedPnl($symbol, 50);
        $pnlEvents = $pnlResult['list'] ?? [];

        if (empty($pnlEvents)) {
            $this->info('No P&L events found from API.');
            return;
        }

        $existingPnlOrderIds = Trade::pluck('order_id')->all();
        $newPnlEvents = collect($pnlEvents)->whereNotIn('order_id', $existingPnlOrderIds);

        if ($newPnlEvents->isEmpty()) {
            $this->info('No new P&L events to save.');
            return;
        }

        foreach ($newPnlEvents->reverse() as $pnlEvent) { // reverse to process oldest first
            Trade::create([
                'symbol' => $pnlEvent['symbol'],
                'side' => $pnlEvent['side'],
                'order_type' => $pnlEvent['orderType'],
                'leverage' => $pnlEvent['leverage'],
                'qty' => $pnlEvent['qty'],
                'avg_entry_price' => $pnlEvent['avgEntryPrice'],
                'avg_exit_price' => $pnlEvent['avgExitPrice'],
                'pnl' => $pnlEvent['closedPnl'],
                'order_id' => $pnlEvent['orderId'],
                'closed_at' => Carbon::createFromTimestampMs($pnlEvent['updatedTime']),
            ]);
            $this->info("Saved new P&L record for closing order ID: {$pnlEvent['orderId']}");
        }

        $this->info('Finished P&L sync.');
    }
}
