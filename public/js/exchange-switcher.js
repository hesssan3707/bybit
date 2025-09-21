/**
 * Exchange Switcher - Standalone JavaScript Function
 * 
 * This function provides a reliable way to switch between exchanges
 * by submitting a form with proper CSRF token handling.
 * 
 * Usage:
 * switchExchange(exchangeId, options)
 * 
 * @param {number} exchangeId - The ID of the exchange to switch to
 * @param {Object} options - Configuration options
 * @param {string} options.baseUrl - Base URL for the application (optional, auto-detected)
 * @param {Function} options.onSuccess - Callback function on successful submission (optional)
 * @param {Function} options.onError - Callback function on error (optional)
 * @param {boolean} options.showConfirm - Whether to show confirmation dialog (default: true)
 * @param {string} options.confirmMessage - Custom confirmation message (optional)
 */

(function(window) {
    'use strict';

    /**
     * Main function to switch between exchanges
     */
    function switchExchange(exchangeId, options = {}) {
        // Default options
        const config = {
            baseUrl: options.baseUrl || window.location.origin,
            onSuccess: options.onSuccess || null,
            onError: options.onError || null,
            showConfirm: options.showConfirm !== false,
            confirmMessage: options.confirmMessage || 'آیا می‌خواهید به این صرافی تغییر دهید؟',
            ...options
        };

        // If confirmation is required, show it first
        if (config.showConfirm) {
            if (typeof modernConfirm === 'function') {
                // Use existing modernConfirm if available
                modernConfirm(
                    config.confirmMessage,
                    () => performExchangeSwitch(exchangeId, config),
                    'تغییر صرافی'
                );
            } else if (confirm(config.confirmMessage)) {
                // Fallback to native confirm
                performExchangeSwitch(exchangeId, config);
            }
        } else {
            // No confirmation required
            performExchangeSwitch(exchangeId, config);
        }
    }

    /**
     * Perform the actual exchange switch
     */
    function performExchangeSwitch(exchangeId, config) {
        try {
            // Step 1: Verify the presence of a CSRF token in the document
            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfTokenMeta) {
                throw new Error('CSRF token meta tag not found in document. Please ensure <meta name="csrf-token" content="{{ csrf_token() }}"> is present in your HTML head.');
            }

            const csrfTokenValue = csrfTokenMeta.getAttribute('content');
            if (!csrfTokenValue || csrfTokenValue.trim() === '') {
                throw new Error('CSRF token value is empty or invalid');
            }

            // Step 2: Validate exchangeId parameter
            if (!exchangeId || isNaN(exchangeId) || exchangeId <= 0) {
                throw new Error('Invalid exchange ID provided. Exchange ID must be a positive number.');
            }

            // Step 3: Create a dynamic form with POST method
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${config.baseUrl}/exchanges/${exchangeId}/switch`;
            form.style.display = 'none'; // Hide the form from view
            form.setAttribute('data-exchange-switcher', 'true'); // Mark for identification

            // Step 4: Create hidden input field containing the CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfTokenValue;

            // Append CSRF token to form
            form.appendChild(csrfInput);
            
            // Append form to document body
            document.body.appendChild(form);
            
            // Call success callback if provided
            if (typeof config.onSuccess === 'function') {
                config.onSuccess(exchangeId, form);
            }
            
            // Submit the form
            form.submit();
            
            // Clean up - remove form after submission
            setTimeout(() => {
                if (form.parentNode) {
                    form.parentNode.removeChild(form);
                }
            }, 100);

        } catch (error) {
            console.error('Error switching exchange:', error);
            
            // Handle errors appropriately while focusing on the switching functionality
            let errorMessage = 'خطا در تغییر صرافی. لطفاً دوباره تلاش کنید.';
            
            // Provide specific error messages for different scenarios
            if (error.message.includes('CSRF')) {
                errorMessage = 'خطای امنیتی. لطفاً صفحه را تازه‌سازی کنید و دوباره تلاش کنید.';
            } else if (error.message.includes('exchange ID')) {
                errorMessage = 'شناسه صرافی نامعتبر است.';
            } else if (error.message.includes('meta tag')) {
                errorMessage = 'خطای پیکربندی صفحه. لطفاً صفحه را تازه‌سازی کنید.';
            }
            
            // Call error callback if provided
            if (typeof config.onError === 'function') {
                config.onError(error, errorMessage);
            } else {
                // Fallback error display
                if (typeof modernAlert === 'function') {
                    modernAlert(errorMessage, 'error');
                } else {
                    alert(errorMessage);
                }
            }
        }
    }

    /**
     * Utility function to check if CSRF token is available
     */
    function isCSRFTokenAvailable() {
        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
        return csrfTokenMeta && csrfTokenMeta.getAttribute('content');
    }

    /**
     * Utility function to get current CSRF token value
     */
    function getCSRFToken() {
        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
        return csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : null;
    }

    // Expose functions to global scope
    window.switchExchange = switchExchange;
    window.ExchangeSwitcher = {
        switch: switchExchange,
        isCSRFTokenAvailable: isCSRFTokenAvailable,
        getCSRFToken: getCSRFToken
    };

})(window);

/**
 * Example Usage:
 * 
 * // Basic usage
 * switchExchange(123);
 * 
 * // With custom options
 * switchExchange(123, {
 *     baseUrl: 'https://bridge.etebareshahr.com',
 *     showConfirm: false,
 *     onSuccess: function(exchangeId, form) {
 *         console.log('Switching to exchange:', exchangeId);
 *     },
 *     onError: function(error, message) {
 *         console.error('Switch failed:', error);
 *     }
 * });
 * 
 * // Using the ExchangeSwitcher object
 * ExchangeSwitcher.switch(123, { showConfirm: false });
 * 
 * // Check if CSRF token is available
 * if (ExchangeSwitcher.isCSRFTokenAvailable()) {
 *     ExchangeSwitcher.switch(123);
 * }
 */