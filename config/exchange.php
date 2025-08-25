<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Exchange Validation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for exchange API validation and IP checking
    |
    */

    'validation' => [
        'enabled' => env('EXCHANGE_VALIDATION_ENABLED', true),
        'server_ip' => env('SERVER_IP', ''),
        'site_url' => env('SITE_URL', env('APP_URL', 'http://localhost')),
        'timeout' => 30, // seconds for API validation calls
        'retry_attempts' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange Rate Limits
    |--------------------------------------------------------------------------
    |
    | Default rate limits for exchange APIs
    |
    */

    'rate_limits' => [
        'bybit' => [
            'requests_per_second' => 10,
            'requests_per_minute' => 600,
            'orders_per_second' => 5,
        ],
        'binance' => [
            'requests_per_second' => 20,
            'requests_per_minute' => 1200,
            'orders_per_second' => 10,
        ],
        'bingx' => [
            'requests_per_second' => 10,
            'requests_per_minute' => 600,
            'orders_per_second' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange Validation Messages
    |--------------------------------------------------------------------------
    |
    | Persian messages for different validation scenarios
    |
    */

    'messages' => [
        'ip_blocked' => 'آدرس IP سرور در لیست مجاز کلید API شما قرار ندارد',
        'spot_permission_denied' => 'کلید API شما مجوز معاملات اسپات ندارد',
        'futures_permission_denied' => 'کلید API شما مجوز معاملات آتی ندارد',
        'connection_failed' => 'اتصال به صرافی برقرار نشد',
        'validation_timeout' => 'زمان بررسی اعتبار کلید API به پایان رسید',
        'unknown_error' => 'خطای نامشخص در بررسی کلید API',
        'spot_access_required' => 'برای استفاده از این صفحه، کلید API شما باید مجوز معاملات اسپات داشته باشد. لطفاً کلید API خود را ویرایش کنید.',
        'futures_access_required' => 'برای استفاده از این صفحه، کلید API شما باید مجوز معاملات آتی داشته باشد. لطفاً کلید API خود را ویرایش کنید.',
    ],
];