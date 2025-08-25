<?php

namespace App\Services\Exchanges;

use App\Models\UserExchange;

class ExchangeConfigService
{
    /**
     * Get all available exchange configurations
     */
    public static function getAllExchanges(): array
    {
        return UserExchange::$exchanges;
    }

    /**
     * Get configuration for a specific exchange
     */
    public static function getExchangeConfig(string $exchangeName): ?array
    {
        return UserExchange::$exchanges[strtolower($exchangeName)] ?? null;
    }

    /**
     * Get exchange display information for UI
     */
    public static function getExchangeDisplayInfo(string $exchangeName): array
    {
        $config = self::getExchangeConfig($exchangeName);
        
        if (!$config) {
            return [
                'name' => ucfirst($exchangeName),
                'display_name' => ucfirst($exchangeName),
                'color' => '#007bff',
                'color_rgb' => '0, 123, 255',
                'logo' => '/images/exchanges/default.png',
                'description' => 'Exchange configuration not found',
            ];
        }

        return [
            'name' => $config['name'],
            'display_name' => $config['display_name'],
            'short_name' => $config['short_name'],
            'color' => $config['color'],
            'color_rgb' => $config['color_rgb'],
            'logo' => $config['logo'],
            'description' => $config['description'],
            'website' => $config['website'],
            'supported_features' => $config['supported_features'],
        ];
    }

    /**
     * Get technical configuration for API integration
     */
    public static function getExchangeTechnicalConfig(string $exchangeName): array
    {
        $config = self::getExchangeConfig($exchangeName);
        
        if (!$config) {
            return [
                'api_url' => '',
                'api_rate_limit' => 60,
                'min_order_size' => 0.001,
                'supported_symbols' => [],
            ];
        }

        return [
            'api_url' => $config['api_url'],
            'docs_url' => $config['docs_url'],
            'api_rate_limit' => $config['api_rate_limit'],
            'min_order_size' => $config['min_order_size'],
            'supported_symbols' => $config['supported_symbols'],
            'supported_features' => $config['supported_features'],
        ];
    }

    /**
     * Check if an exchange supports a specific feature
     */
    public static function supportsFeature(string $exchangeName, string $feature): bool
    {
        $config = self::getExchangeConfig($exchangeName);
        
        if (!$config || !isset($config['supported_features'])) {
            return false;
        }

        return in_array(strtolower($feature), array_map('strtolower', $config['supported_features']));
    }

    /**
     * Check if an exchange supports a trading pair
     */
    public static function supportsSymbol(string $exchangeName, string $symbol): bool
    {
        $config = self::getExchangeConfig($exchangeName);
        
        if (!$config || !isset($config['supported_symbols'])) {
            return true; // Assume support if not specified
        }

        return in_array(strtoupper($symbol), array_map('strtoupper', $config['supported_symbols']));
    }

    /**
     * Get all supported exchanges with their basic info
     */
    public static function getSupportedExchangesList(): array
    {
        $exchanges = [];
        
        foreach (self::getAllExchanges() as $key => $config) {
            $exchanges[] = [
                'key' => $key,
                'name' => $config['name'],
                'display_name' => $config['display_name'],
                'color' => $config['color'],
                'logo' => $config['logo'],
                'description' => $config['description'],
                'website' => $config['website'],
                'supported_features' => $config['supported_features'],
            ];
        }

        return $exchanges;
    }

    /**
     * Validate exchange configuration
     */
    public static function validateExchangeConfig(string $exchangeName): array
    {
        $config = self::getExchangeConfig($exchangeName);
        $errors = [];

        if (!$config) {
            $errors[] = "Exchange '{$exchangeName}' is not configured";
            return ['valid' => false, 'errors' => $errors];
        }

        // Required fields
        $required = ['name', 'color', 'api_url'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate color format
        if (!empty($config['color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $config['color'])) {
            $errors[] = "Invalid color format: {$config['color']}";
        }

        // Validate API URL
        if (!empty($config['api_url']) && !filter_var($config['api_url'], FILTER_VALIDATE_URL)) {
            $errors[] = "Invalid API URL: {$config['api_url']}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'config' => $config,
        ];
    }

    /**
     * Get exchange statistics for admin dashboard
     */
    public static function getExchangeStatistics(): array
    {
        $stats = [];
        
        foreach (array_keys(self::getAllExchanges()) as $exchangeName) {
            $userCount = UserExchange::where('exchange_name', $exchangeName)->count();
            $activeCount = UserExchange::where('exchange_name', $exchangeName)->active()->count();
            $pendingCount = UserExchange::where('exchange_name', $exchangeName)->pending()->count();
            
            $stats[$exchangeName] = [
                'total_users' => $userCount,
                'active_users' => $activeCount,
                'pending_users' => $pendingCount,
                'activation_rate' => $userCount > 0 ? round(($activeCount / $userCount) * 100, 2) : 0,
            ];
        }

        return $stats;
    }

    /**
     * Get color scheme for CSS variables
     */
    public static function getExchangeColorScheme(string $exchangeName): array
    {
        $config = self::getExchangeConfig($exchangeName);
        
        if (!$config) {
            return [
                'primary' => '#007bff',
                'primary_rgb' => '0, 123, 255',
                'light' => '#e3f2fd',
                'dark' => '#1976d2',
            ];
        }

        // Generate color variations
        $primary = $config['color'];
        $rgb = $config['color_rgb'];
        
        return [
            'primary' => $primary,
            'primary_rgb' => $rgb,
            'light' => $primary . '20', // 20% opacity
            'dark' => self::darkenColor($primary, 20),
            'hover' => self::lightenColor($primary, 10),
        ];
    }

    /**
     * Darken a hex color
     */
    private static function darkenColor(string $hex, int $percent): string
    {
        $hex = str_replace('#', '', $hex);
        $r = max(0, hexdec(substr($hex, 0, 2)) - ($percent * 255 / 100));
        $g = max(0, hexdec(substr($hex, 2, 2)) - ($percent * 255 / 100));
        $b = max(0, hexdec(substr($hex, 4, 2)) - ($percent * 255 / 100));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Lighten a hex color
     */
    private static function lightenColor(string $hex, int $percent): string
    {
        $hex = str_replace('#', '', $hex);
        $r = min(255, hexdec(substr($hex, 0, 2)) + ($percent * 255 / 100));
        $g = min(255, hexdec(substr($hex, 2, 2)) + ($percent * 255 / 100));
        $b = min(255, hexdec(substr($hex, 4, 2)) + ($percent * 255 / 100));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}