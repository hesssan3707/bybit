<?php

namespace App\Traits;

trait ParsesExchangeErrors
{
    /**
     * Parse exchange API error message and return user-friendly message
     * Supports Bybit, Binance, and BingX exchanges
     * 
     * @param string $errorMessage
     * @return string
     */
    public function parseExchangeError($errorMessage)
    {
        // Detect exchange type from error message
        $exchange = 'unknown';
        if (str_contains($errorMessage, 'Bybit API Error')) {
            $exchange = 'bybit';
        } elseif (str_contains($errorMessage, 'Binance API Error')) {
            $exchange = 'binance';
        } elseif (str_contains($errorMessage, 'BingX API Error')) {
            $exchange = 'bingx';
        }
        
        // Extract error code if present (handle both positive and negative codes)
        $errorCode = null;
        if (preg_match('/Code: (-?\d+)/', $errorMessage, $matches)) {
            $errorCode = $matches[1];
        }
        
        // Extract symbol if present
        $symbol = null;
        if (preg_match('/"symbol":"([^"]+)"/', $errorMessage, $matches)) {
            $symbol = $matches[1];
        }
        
        // Extract side if present  
        $side = null;
        if (preg_match('/"side":"([^"]+)"/', $errorMessage, $matches)) {
            $side = $matches[1] === 'Buy' ? 'خرید' : 'فروش';
        }
        
        // Handle exchange-specific error codes
        switch ($exchange) {
            case 'bybit':
                return $this->parseBybitSpecificError($errorCode, $errorMessage, $symbol, $side);
            case 'binance':
                return $this->parseBinanceSpecificError($errorCode, $errorMessage, $symbol, $side);
            case 'bingx':
                return $this->parseBingXSpecificError($errorCode, $errorMessage, $symbol, $side);
            default:
                return $this->parseGenericError($errorMessage);
        }
    }
    
    /**
     * Parse Bybit-specific error codes
     */
    protected function parseBybitSpecificError($errorCode, $errorMessage, $symbol, $side)
    {
        switch ($errorCode) {
            case '10001': // Position idx not match position mode or Parameter error
                if (str_contains($errorMessage, 'position idx not match position mode')) {
                    return "خطا در تنظیمات حالت معاملاتی.\n" .
                           "لطفاً از تنظیمات، حالت معاملاتی صرافی خود را به Hedge تغییر دهید و دوباره تلاش کنید.";
                }
                return "کلید API نامعتبر است.\n" .
                       "لطفاً کلیدهای API خود را بررسی کنید.";
                       
            case '170131': // Insufficient balance
                return "موجودی USDT شما برای این معامله کافی نیست.\n" .
                       "لطفاً ابتدا موجودی خود را شارژ کنید.";
                       
            case '170130': // Order value too small
                return "مقدار سفارش خیلی کم است.\n" .
                       "لطفاً درصد ریسک بیشتری وارد کنید.";
                       
            case '110001': // Order does not exist
                return "سفارش مورد نظر یافت نشد.\n" .
                       "احتمالاً سفارش قبلاً اجرا یا لغو شده است.";
                       
            case '110003': // Order quantity exceeds upper limit
                return "مقدار سفارش از حد مجاز بیشتر است.\n" .
                       "لطفاً درصد ریسک کمتری وارد کنید.";
                       
            case '110004': // Order price exceeds upper limit
                return "قیمت سفارش از حد مجاز بیشتر است.\n" .
                       "لطفاً قیمت کمتری وارد کنید.";
                       
            case '110005': // Order price is lower than the minimum
                return "قیمت سفارش کمتر از حد مجاز است.\n" .
                       "لطفاً قیمت بیشتری وارد کنید.";
                       
            case '10003': // Missing required parameter
                return "پارامترهای مورد نیاز ناقص هستند.\n" .
                       "لطفاً تمام فیلدها را پر کنید.";
                       
            case '10004': // Invalid signature
                return "امضای API نامعتبر است.\n" .
                       "لطفاً کلیدهای API خود را بررسی کنید.";
                       
            case '10005': // Permission denied
                return "دسترسی به این عملیات مجاز نیست.\n" .
                       "لطفاً مجوزهای API خود را بررسی کنید.";
                       
            case '10015': // IP not in whitelist
                return "آدرس IP شما در لیست مجاز نیست.\n" .
                       "لطفاً IP خود را به whitelist اضافه کنید.";
                       
            case '110012': // Order quantity is lower than the minimum
                return "مقدار سفارش کمتر از حداقل مجاز است.\n" .
                       "لطفاً مقدار بیشتری وارد کنید.";
                       
            case '110025': // Order would immediately trigger
                return "سفارش شما بلافاصله اجرا می‌شود.\n" .
                       "برای سفارش محدود، قیمت مناسب‌تری انتخاب کنید.";
                       
            case '110026': // Market is closed
                return "بازار در حال حاضر بسته است.\n" .
                       "لطفاً در ساعات کاری بازار مجدداً تلاش کنید.";
                       
            case '170213': // Wallet locked
                return "کیف پول شما قفل است.\n" .
                       "لطفاً با پشتیبانی صرافی تماس بگیرید.";
                       
            default:
                return $this->parseGenericError($errorMessage);
        }
    }
    
    /**
     * Parse Binance-specific error codes
     */
    protected function parseBinanceSpecificError($errorCode, $errorMessage, $symbol, $side)
    {
        switch ($errorCode) {
            case '-1021': // Timestamp outside recv window
                return "خطا در زمان‌بندی درخواست.\n" .
                       "لطفاً ساعت سیستم خود را تنظیم کرده و دوباره تلاش کنید.";
                       
            case '-1022': // Invalid signature
                return "امضای API نامعتبر است.\n" .
                       "لطفاً کلیدهای API خود را بررسی کنید.";
                       
            case '-2010': // New order rejected
                return "سفارش رد شد.\n" .
                       "لطفاً پارامترهای سفارش را بررسی کنید.";
                       
            case '-2019': // Margin is insufficient
                return "موجودی شما برای این معامله کافی نیست.\n" .
                       "لطفاً ابتدا موجودی خود را شارژ کنید.";
                       
            case '-4028': // Position side does not match user setting
                return "خطا در تنظیمات حالت معاملاتی.\n" .
                       "لطفاً از تنظیمات، حالت معاملاتی صرافی خود را به Hedge تغییر دهید و دوباره تلاش کنید.";
                       
            case '-1013': // Invalid quantity
                return "مقدار سفارش نامعتبر است.\n" .
                       "لطفاً مقدار صحیح وارد کنید.";
                       
            case '-1111': // Precision is over the maximum defined
                return "دقت اعشار بیش از حد مجاز است.\n" .
                       "لطفاً مقدار را با دقت کمتری وارد کنید.";
                       
            case '-1003': // Too many requests
                return "تعداد درخواست‌ها بیش از حد مجاز است.\n" .
                       "لطفاً چند لحظه صبر کرده و دوباره تلاش کنید.";
                       
            case '-1015': // Too many new orders
                return "تعداد سفارش‌های جدید بیش از حد مجاز است.\n" .
                       "لطفاً چند لحظه صبر کرده و دوباره تلاش کنید.";
                       
            case '-1102': // Mandatory parameter was not sent
                return "پارامترهای ضروری ناقص هستند.\n" .
                       "لطفاً تمام فیلدهای مورد نیاز را پر کنید.";
                       
            case '-1104': // Not all sent parameters were read
                return "پارامترهای اضافی ارسال شده است.\n" .
                       "لطفاً پارامترهای صحیح را ارسال کنید.";
                       
            case '-1106': // Parameter was empty
                return "یکی از پارامترها خالی است.\n" .
                       "لطفاً تمام فیلدها را پر کنید.";
                       
            case '-1112': // No orders on book for symbol
                return "هیچ سفارشی برای این جفت ارز وجود ندارد.\n" .
                       "لطفاً جفت ارز دیگری انتخاب کنید.";
                       
            default:
                return $this->parseGenericError($errorMessage);
        }
    }
    
    /**
     * Parse BingX-specific error codes
     */
    protected function parseBingXSpecificError($errorCode, $errorMessage, $symbol, $side)
    {
        switch ($errorCode) {
            case '100001': // Invalid signature
                return "امضای API نامعتبر است.\n" .
                       "لطفاً کلیدهای API خود را بررسی کنید.";
                       
            case '100002': // Invalid API key
                return "کلید API نامعتبر است.\n" .
                       "لطفاً تنظیمات صرافی خود را بررسی کنید.";
                       
            case '100003': // IP not allowed
                return "آدرس IP شما مجاز نیست.\n" .
                       "لطفاً IP فعلی را به لیست مجاز صرافی اضافه کنید.";
                       
            case '100004': // Timestamp error
                return "خطا در زمان‌بندی درخواست.\n" .
                       "لطفاً ساعت سیستم خود را تنظیم کرده و دوباره تلاش کنید.";
                       
            case '100005': // Permission denied
                return "دسترسی به این عملیات مجاز نیست.\n" .
                       "لطفاً مجوزهای API خود را بررسی کنید.";
                       
            case '100416': // Order does not exist
                return "سفارش مورد نظر یافت نشد.\n" .
                       "احتمالاً سفارش قبلاً اجرا یا لغو شده است.";
                       
            case '100437': // Insufficient balance
                return "موجودی شما برای این معامله کافی نیست.\n" .
                       "لطفاً ابتدا موجودی خود را شارژ کنید.";
                       
            case '100400': // Invalid parameter
                return "پارامترهای ورودی نامعتبر هستند.\n" .
                       "لطفاً اطلاعات وارد شده را بررسی کنید.";
                       
            case '100440': // Order quantity too small
                return "مقدار سفارش خیلی کم است.\n" .
                       "لطفاً مقدار بیشتری وارد کنید.";
                       
            case '100441': // Order quantity too large
                return "مقدار سفارش خیلی زیاد است.\n" .
                       "لطفاً مقدار کمتری وارد کنید.";
                       
            case '100442': // Order price too high
                return "قیمت سفارش خیلی بالا است.\n" .
                       "لطفاً قیمت کمتری وارد کنید.";
                       
            case '100443': // Order price too low
                return "قیمت سفارش خیلی پایین است.\n" .
                       "لطفاً قیمت بالاتری وارد کنید.";
                       
            case '100444': // Insufficient margin
                return "مارجین کافی نیست.\n" .
                       "لطفاً موجودی خود را افزایش دهید.";
                       
            default:
                return $this->parseGenericError($errorMessage);
        }
    }
    
    /**
     * Parse generic error patterns
     */
    private function parseGenericError($errorMessage)
    {
        $lowerMessage = strtolower($errorMessage);
        
        // Check for common error patterns across all exchanges
        if (str_contains($lowerMessage, 'insufficient') || str_contains($lowerMessage, 'balance')) {
            return "موجودی کافی نیست. لطفاً موجودی خود را بررسی کنید.";
        }
        if (str_contains($lowerMessage, 'permission') || str_contains($lowerMessage, 'forbidden') || str_contains($lowerMessage, 'denied')) {
            return "دسترسی مجاز نیست. لطفاً تنظیمات API خود را بررسی کنید.";
        }
        if (str_contains($lowerMessage, 'invalid api key') || str_contains($lowerMessage, 'api key')) {
            return "کلید API نامعتبر است. لطفاً کلیدهای API خود را بررسی کنید.";
        }
        if (str_contains($lowerMessage, 'signature')) {
            return "امضای API نامعتبر است. لطفاً کلیدهای API خود را بررسی کنید.";
        }
        if (str_contains($lowerMessage, 'timestamp') || str_contains($lowerMessage, 'time')) {
            return "خطا در زمان‌بندی درخواست. لطفاً ساعت سیستم خود را تنظیم کنید.";
        }
        if ((str_contains($lowerMessage, 'position') && str_contains($lowerMessage, 'mode')) || 
            (str_contains($lowerMessage, 'position') && str_contains($lowerMessage, 'side'))) {
            return "خطا در تنظیمات حالت معاملاتی. لطفاً حالت معاملاتی صرافی خود را بررسی کنید.";
        }
        if (str_contains($lowerMessage, 'invalid symbol')) {
            return "جفت ارز انتخاب شده معتبر نیست. لطفاً جفت ارز صحیح را انتخاب کنید.";
        }
        if (str_contains($lowerMessage, 'order not found')) {
            return "سفارش یافت نشد. احتمالاً سفارش قبلاً اجرا یا لغو شده است.";
        }
        
        // Return a generic but helpful message
        return "خطا در ایجاد سفارش: " . $errorMessage;
    }
}