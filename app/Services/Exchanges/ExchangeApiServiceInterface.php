<?php

namespace App\Services\Exchanges;

interface ExchangeApiServiceInterface
{
    /**
     * Set API credentials for the exchange
     */
    public function setCredentials(string $apiKey, string $apiSecret): void;

    /**
     * Get account balance
     */
    public function getAccountBalance(): array;

    /**
     * Get wallet balance
     */
    public function getWalletBalance(string $accountType = 'UNIFIED', ?string $coin = null): array;

    /**
     * Get spot account balance
     */
    public function getSpotAccountBalance(): array;

    /**
     * Create a futures order
     */
    public function createOrder(array $orderData): array;

    /**
     * Create a spot order
     */
    public function createSpotOrder(array $orderData): array;

    /**
     * Get order details
     */
    public function getOrder(string $orderId): array;

    /**
     * Get spot order details
     */
    public function getSpotOrder(string $orderId): array;

    /**
     * Cancel an order
     */
    public function cancelOrder(string $orderId): array;

    /**
     * Cancel a spot order
     */
    public function cancelSpotOrder(string $orderId): array;

    /**
     * Get open orders
     */
    public function getOpenOrders(string $symbol = null): array;

    /**
     * Get open spot orders
     */
    public function getOpenSpotOrders(string $symbol = null): array;

    /**
     * Get order history
     */
    public function getOrderHistory(string $symbol = null, int $limit = 50): array;

    /**
     * Get spot order history
     */
    public function getSpotOrderHistory(string $symbol = null, int $limit = 50): array;

    /**
     * Get positions
     */
    public function getPositions(string $symbol = null): array;

    /**
     * Close a position
     */
    public function closePosition(string $symbol, string $side, float $qty): array;

    /**
     * Set stop loss
     */
    public function setStopLoss(string $symbol, float $stopLoss, string $side): array;

    /**
     * Get trading symbols/instruments
     */
    public function getInstrumentsInfo(): array;

    /**
     * Get spot trading symbols/instruments
     */
    public function getSpotInstrumentsInfo(): array;

    /**
     * Get ticker information for futures
     */
    public function getTickerInfo(string $symbol): array;

    /**
     * Get ticker information for spot
     */
    public function getSpotTickerInfo(string $symbol): array;

    /**
     * Get closed PnL records
     */
    public function getClosedPnl(string $symbol, int $limit = 50, ?int $startTime = null): array;

    /**
     * Get order history by order ID
     */
    public function getHistoryOrder(string $orderId): array;

    /**
     * Set trading stop (stop loss/take profit)
     */
    public function setTradingStop(array $params): array;

    /**
     * Get exchange name
     */
    public function getExchangeName(): string;

    /**
     * Test API connectivity
     */
    public function testConnection(): bool;

    /**
     * Get exchange-specific API rate limits
     */
    public function getRateLimits(): array;
}