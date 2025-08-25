<?php

namespace App\Traits;

use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Log;

trait HandlesExchangeAccess
{
    /**
     * Handle API exceptions and update access validation if needed
     */
    protected function handleApiException(\Exception $e, UserExchange $userExchange, string $operationType = 'unknown')
    {
        $errorMessage = $e->getMessage();
        $errorCode = $e->getCode();
        
        // Check if this is an access permission error
        if ($this->isAccessPermissionError($errorMessage, $errorCode, $userExchange->exchange_name)) {
            $this->updateAccessValidationFromError($userExchange, $errorMessage, $operationType);
        }
        
        // Re-throw the exception so calling code can handle it appropriately
        throw $e;
    }
    
    /**
     * Check if the error indicates a permission/access issue
     */
    private function isAccessPermissionError(string $errorMessage, int $errorCode, string $exchangeName): bool
    {
        $errorMessage = strtolower($errorMessage);
        
        switch ($exchangeName) {
            case 'bybit':
                return $this->isAccessErrorBybit($errorMessage, $errorCode);
            case 'binance':
                return $this->isAccessErrorBinance($errorMessage, $errorCode);
            case 'bingx':
                return $this->isAccessErrorBingX($errorMessage, $errorCode);
            default:
                return false;
        }
    }
    
    /**
     * Check Bybit-specific access errors
     */
    private function isAccessErrorBybit(string $errorMessage, int $errorCode): bool
    {
        $accessErrorCodes = [10003, 10004, 10005, 10006, 10018, 10027, 170124, 170130, 170131, 170132, 170134, 170135, 170136, 170137, 170139, 170213];
        $accessErrorMessages = ['permission denied', 'invalid api key', 'no permission', 'insufficient permission', 'api key restrictions', 'not authorized', 'forbidden'];
        
        if (in_array($errorCode, $accessErrorCodes)) {
            return true;
        }
        
        foreach ($accessErrorMessages as $msg) {
            if (str_contains($errorMessage, $msg)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check Binance-specific access errors
     */
    private function isAccessErrorBinance(string $errorMessage, int $errorCode): bool
    {
        $accessErrorCodes = [-2014, -2015, -1022, -1021];
        $accessErrorMessages = ['api key format invalid', 'invalid api-key', 'signature for this request is not valid', 'timestamp for this request', 'insufficient permission'];
        
        if (in_array($errorCode, $accessErrorCodes)) {
            return true;
        }
        
        foreach ($accessErrorMessages as $msg) {
            if (str_contains($errorMessage, $msg)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check BingX-specific access errors
     */
    private function isAccessErrorBingX(string $errorMessage, int $errorCode): bool
    {
        $accessErrorCodes = [100001, 100002, 100403];
        $accessErrorMessages = ['invalid api key', 'signature verification failed', 'insufficient permission', 'access denied'];
        
        if (in_array($errorCode, $accessErrorCodes)) {
            return true;
        }
        
        foreach ($accessErrorMessages as $msg) {
            if (str_contains($errorMessage, $msg)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Update access validation based on the error encountered
     */
    private function updateAccessValidationFromError(UserExchange $userExchange, string $errorMessage, string $operationType)
    {
        $updateData = [
            'last_validation_at' => now(),
            'validation_message' => "Access limitation detected during {$operationType}: " . substr($errorMessage, 0, 255)
        ];
        
        // Determine which access type was affected based on operation type
        switch ($operationType) {
            case 'spot':
            case 'spot_balance':
            case 'spot_order':
                $updateData['spot_access'] = false;
                break;
                
            case 'futures':
            case 'futures_balance':
            case 'futures_order':
            case 'futures_position':
                $updateData['futures_access'] = false;
                break;
                
            default:
                // If we can't determine the specific type, mark both as potentially limited
                $updateData['spot_access'] = false;
                $updateData['futures_access'] = false;
                break;
        }
        
        // Update validation results with the new limitation
        $validationResults = $userExchange->validation_results ?? [];
        $validationResults['dynamic_check'] = [
            'timestamp' => now()->toISOString(),
            'operation' => $operationType,
            'error_detected' => true,
            'error_message' => $errorMessage,
            'access_updated' => true
        ];
        $updateData['validation_results'] = $validationResults;
        
        $userExchange->update($updateData);
        
        Log::warning("Updated exchange access validation due to API error", [
            'user_exchange_id' => $userExchange->id,
            'exchange' => $userExchange->exchange_name,
            'operation' => $operationType,
            'error' => $errorMessage,
            'updated_fields' => array_keys($updateData)
        ]);
    }
    
    /**
     * Validate current API access and update validation results
     */
    protected function validateAndUpdateApiAccess(UserExchange $userExchange): array
    {
        try {
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->api_key,
                $userExchange->api_secret
            );
            
            $validation = $exchangeService->validateAPIAccess();
            $userExchange->updateValidationResults($validation);
            
            return $validation;
            
        } catch (\Exception $e) {
            Log::error("Failed to validate API access for user exchange", [
                'user_exchange_id' => $userExchange->id,
                'exchange' => $userExchange->exchange_name,
                'error' => $e->getMessage()
            ]);
            
            // Mark as validation failed
            $userExchange->update([
                'last_validation_at' => now(),
                'validation_message' => 'Validation failed: ' . substr($e->getMessage(), 0, 255),
                'spot_access' => false,
                'futures_access' => false,
                'ip_access' => false
            ]);
            
            return [
                'success' => false,
                'message' => 'API validation failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user-friendly error message for access limitation
     */
    protected function getAccessLimitationMessage(string $operationType, string $exchangeName): string
    {
        $messages = [
            'spot' => "دسترسی به معاملات اسپات در صرافی {$exchangeName} محدود شده است. لطفاً تنظیمات کلید API خود را بررسی کنید.",
            'futures' => "دسترسی به معاملات آتی در صرافی {$exchangeName} محدود شده است. لطفاً تنظیمات کلید API خود را بررسی کنید.",
            'balance' => "دسترسی به اطلاعات موجودی در صرافی {$exchangeName} محدود شده است. لطفاً تنظیمات کلید API خود را بررسی کنید.",
            'order' => "دسترسی به ثبت سفارش در صرافی {$exchangeName} محدود شده است. لطفاً تنظیمات کلید API خود را بررسی کنید.",
            'default' => "دسترسی به صرافی {$exchangeName} محدود شده است. لطفاً تنظیمات کلید API خود را بررسی کنید."
        ];
        
        return $messages[$operationType] ?? $messages['default'];
    }
}