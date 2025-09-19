<?php

namespace Tests\Mocks;

use App\Services\Exchanges\ExchangeApiServiceInterface;

class TestExchangeService implements ExchangeApiServiceInterface
{
    public function getAccountBalance(): array
    {
        return [
            'success' => true,
            'data' => [
                'USDT' => ['available' => '1000.00', 'total' => '1000.00'],
                'BTC' => ['available' => '0.1', 'total' => '0.1']
            ]
        ];
    }

    public function getTradingPairs(): array
    {
        return [
            'success' => true,
            'data' => [
                ['symbol' => 'BTCUSDT', 'status' => 'Trading'],
                ['symbol' => 'ETHUSDT', 'status' => 'Trading']
            ]
        ];
    }

    public function getTickerInfo(string $symbol): array
    {
        return [
            'success' => true,
            'data' => [
                'symbol' => $symbol,
                'lastPrice' => '50000.00',
                'bid' => '49999.00',
                'ask' => '50001.00'
            ]
        ];
    }

    public function getOrderHistory(string $symbol, int $limit = 50): array
    {
        return [
            'success' => true,
            'data' => []
        ];
    }

    public function createOrder(array $orderData): array
    {
        return [
            'success' => true,
            'data' => [
                'orderId' => 'test-order-' . uniqid(),
                'orderLinkId' => $orderData['orderLinkId'] ?? 'test-link-' . uniqid(),
                'symbol' => $orderData['symbol'],
                'side' => $orderData['side'],
                'orderType' => $orderData['orderType'],
                'qty' => $orderData['qty'],
                'price' => $orderData['price'] ?? null,
                'status' => 'New'
            ]
        ];
    }

    public function cancelOrder(string $orderId, string $symbol): array
    {
        return [
            'success' => true,
            'data' => [
                'orderId' => $orderId,
                'status' => 'Cancelled'
            ]
        ];
    }

    public function getInstrumentsInfo(string $symbol): array
    {
        return [
            'success' => true,
            'data' => [
                'symbol' => $symbol,
                'baseCoin' => 'BTC',
                'quoteCoin' => 'USDT',
                'minOrderQty' => '0.001',
                'maxOrderQty' => '100',
                'tickSize' => '0.01'
            ]
        ];
    }

    public function getPositions(): array
    {
        return [
            'success' => true,
            'data' => []
        ];
    }

    public function setStopLossAdvanced(array $params): array
    {
        return [
            'success' => true,
            'data' => ['message' => 'Stop loss set successfully']
        ];
    }
}