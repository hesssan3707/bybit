<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Exchanges\ExchangeConfigService;
use Illuminate\Http\Request;

class ExchangeConfigController extends Controller
{
    /**
     * Get all available exchange configurations
     */
    public function index()
    {
        try {
            $exchanges = ExchangeConfigService::getSupportedExchangesList();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'exchanges' => $exchanges,
                    'total_count' => count($exchanges),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get exchange configurations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get configuration for a specific exchange
     */
    public function show($exchangeName)
    {
        try {
            $displayInfo = ExchangeConfigService::getExchangeDisplayInfo($exchangeName);
            $technicalConfig = ExchangeConfigService::getExchangeTechnicalConfig($exchangeName);
            $validation = ExchangeConfigService::validateExchangeConfig($exchangeName);
            
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exchange configuration is invalid',
                    'errors' => $validation['errors']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'exchange_name' => $exchangeName,
                    'display_info' => $displayInfo,
                    'technical_config' => $technicalConfig,
                    'color_scheme' => ExchangeConfigService::getExchangeColorScheme($exchangeName),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get exchange configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if exchange supports a feature
     */
    public function checkFeature($exchangeName, $feature)
    {
        try {
            $supports = ExchangeConfigService::supportsFeature($exchangeName, $feature);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'exchange' => $exchangeName,
                    'feature' => $feature,
                    'supported' => $supports,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check feature support: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if exchange supports a trading symbol
     */
    public function checkSymbol($exchangeName, $symbol)
    {
        try {
            $supports = ExchangeConfigService::supportsSymbol($exchangeName, $symbol);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'exchange' => $exchangeName,
                    'symbol' => strtoupper($symbol),
                    'supported' => $supports,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check symbol support: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get exchange statistics (protected endpoint)
     */
    public function statistics()
    {
        try {
            $stats = ExchangeConfigService::getExchangeStatistics();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'generated_at' => now()->toDateTimeString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get exchange statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
