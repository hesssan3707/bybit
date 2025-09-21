<?php

namespace App\Services\Exchanges;

use App\Models\UserExchange;
use App\Services\Exchanges\BybitApiService;
use App\Services\Exchanges\BinanceApiService;
use App\Services\Exchanges\BingXApiService;
use Illuminate\Support\Facades\Log;

class ExchangeFactory
{
    /**
     * Create an exchange API service instance based on exchange name
     */
    public static function create(string $exchangeName, ?string $apiKey = null, ?string $apiSecret = null, ?bool $isDemo = null): ExchangeApiServiceInterface
    {
        switch (strtolower($exchangeName)) {
            case 'bybit':
                $service = new BybitApiService($isDemo);
                break;
                
            case 'binance':
                $service = new BinanceApiService($isDemo);
                break;
                
            case 'bingx':
                $service = new BingXApiService($isDemo);
                break;
                
            default:
                throw new \InvalidArgumentException("Unsupported exchange: {$exchangeName}");
        }

        // Set credentials if provided
        if ($apiKey && $apiSecret) {
            $service->setCredentials($apiKey, $apiSecret);
        }

        return $service;
    }

    /**
     * Create an exchange API service instance for a user's exchange account
     */
    public static function createForUserExchange(UserExchange $userExchange): ExchangeApiServiceInterface
    {
        if (!$userExchange->is_active) {
            throw new \Exception("Exchange account is not active");
        }

        // Get the correct credentials based on demo mode status
        $credentials = $userExchange->getCurrentApiCredentials();

        $service = self::create(
            $userExchange->exchange_name,
            $credentials['api_key'],
            $credentials['api_secret'],
            $credentials['is_demo']
        );

        return $service;
    }

    /**
     * Create an exchange API service instance for a user's exchange account with forced credential type
     */
    public static function createForUserExchangeWithCredentialType(UserExchange $userExchange, string $credentialType = 'auto'): ExchangeApiServiceInterface
    {
        if (!$userExchange->is_active) {
            throw new \Exception("Exchange account is not active");
        }

        // Get the specific credentials based on credential type
        $credentials = $userExchange->getApiCredentials($credentialType);

        $service = self::create(
            $userExchange->exchange_name,
            $credentials['api_key'],
            $credentials['api_secret'],
            $credentials['is_demo']
        );

        return $service;
    }

    /**
     * Create an exchange API service instance for a user's active exchange
     */
    public static function createForUser(int $userId, ?string $exchangeName = null): ExchangeApiServiceInterface
    {
        $query = UserExchange::where('user_id', $userId)->active();
        
        if ($exchangeName) {
            $query->where('exchange_name', $exchangeName);
        } else {
            // Use default exchange if no specific exchange is requested
            $query->where('is_default', true);
        }

        $userExchange = $query->first();

        if (!$userExchange) {
            if ($exchangeName) {
                throw new \Exception("No active {$exchangeName} exchange found for user");
            } else {
                throw new \Exception("No active default exchange found for user");
            }
        }

        return self::createForUserExchangeWithCredentialType($userExchange, 'auto');
    }

    /**
     * Get all supported exchange names
     */
    public static function getSupportedExchanges(): array
    {
        return ['bybit', 'binance', 'bingx'];
    }

    /**
     * Check if an exchange is supported
     */
    public static function isSupported(string $exchangeName): bool
    {
        return in_array(strtolower($exchangeName), self::getSupportedExchanges());
    }

    /**
     * Test connection for a user exchange
     */
    public static function testUserExchangeConnection(UserExchange $userExchange): bool
    {
        try {
            $service = self::createForUserExchangeWithCredentialType($userExchange, 'auto');
            return $service->testConnection();
        } catch (\Exception $e) {
            Log::error("Exchange connection test failed", [
                'user_exchange_id' => $userExchange->id,
                'exchange' => $userExchange->exchange_name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}