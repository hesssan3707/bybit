<?php

namespace App\Console\Commands;

use App\Models\BybitOrders;
use App\Services\BybitApiService;
use Illuminate\Console\Command;

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

        $ordersToCheck = BybitOrders::whereIn('status', ['pending', 'filled'])
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
                        $closeSide = (strtolower($dbOrder->side) === 'buy') ? 'Sell' : 'Buy';
                        $tpPrice = (float)$dbOrder->tp;
                        $tpOrderParams = [
                            'category' => 'linear',
                            'symbol' => $symbol,
                            'side' => $closeSide,
                            'orderType' => 'Limit',
                            'qty' => (string)$dbOrder->amount,
                            'price' => (string)$tpPrice,
                            'reduceOnly' => true,
                            'timeInForce' => 'GTC',
                        ];

                        $this->bybitApiService->createOrder($tpOrderParams);                        
                        $dbOrder->status = 'filled';
                        $dbOrder->save();
                        $this->info("Order {$dbOrder->order_id} is filled. Awaiting TP/SL execution.");
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

        // --- New, more robust P&L processing ---
        $this->processClosedPositions('ETHUSDT');
        return 1;
    }

    private function processClosedPositions(string $symbol)
    {
        $this->info("Processing P&L for symbol: {$symbol}");

        // Find the oldest 'filled' order for this symbol that hasn't been processed yet.
        $orderToProcess = BybitOrders::where('status', 'filled')
            ->where('symbol', $symbol)
            ->whereNull('pnl')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$orderToProcess) {
            $this->info("No 'filled' orders awaiting P&L processing for {$symbol}.");
            return;
        }

        // Check if the position is closed. If not, we can't process P&L yet.
        $positionResult = $this->bybitApiService->getPositionInfo($symbol);
        $position = collect($positionResult['list'] ?? [])->firstWhere('symbol', $symbol);
        if ($position && (float)$position['size'] > 0) {
            $this->info("Position for {$symbol} is still open. Cannot process P&L yet.");
            return;
        }

        // Get P&L events that have not been assigned to one of our orders yet.
        $usedClosingOrderIds = BybitOrders::whereNotNull('closing_order_id')->pluck('closing_order_id')->all();
        $pnlResult = $this->bybitApiService->getClosedPnl($symbol, 50); // Get a larger batch
        $unprocessedPnlEvents = collect($pnlResult['list'] ?? [])->whereNotIn('orderId', $usedClosingOrderIds);

        if ($unprocessedPnlEvents->isEmpty()) {
            $this->info("No new, unprocessed P&L events found for {$symbol}.");
            return;
        }

        // Find the first unprocessed P&L event.
        // We assume the oldest filled order corresponds to the oldest unprocessed P&L event.
        // This is not foolproof but is the most reliable approach without full execution matching.
        $pnlEventToAssign = $unprocessedPnlEvents->last(); // last() because the API returns newest first.

        if ($pnlEventToAssign) {
            $orderToProcess->status = 'closed';
            $orderToProcess->pnl = $pnlEventToAssign['closedPnl'];
            $orderToProcess->closure_price = $pnlEventToAssign['avgExitPrice'] ?? null;
            $orderToProcess->closing_order_id = $pnlEventToAssign['orderId']; // Mark PNL event as used
            $orderToProcess->closed_at = now();
            $orderToProcess->save();

            $this->info("Assigned P&L of {$pnlEventToAssign['closedPnl']} to order ID {$orderToProcess->id} (Bybit Order ID: {$orderToProcess->order_id}).");
        }

        $this->info('Finished order lifecycle management.');
        return self::SUCCESS;
    }
}
