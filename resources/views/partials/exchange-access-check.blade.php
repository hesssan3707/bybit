{{-- Exchange Access Check Partial --}}
{{-- This partial handles display of exchange access messages and form disabling --}}

@php
    $exchangeAccess = request()->attributes->get('exchange_access');
    $accessRestricted = request()->attributes->get('access_restricted', false);
    $restrictionReason = request()->attributes->get('restriction_reason');
    $requiredAccess = request()->attributes->get('required_access', 'any');
@endphp

{{-- Check for access restrictions based on middleware --}}
@if($accessRestricted)
    @if($restrictionReason === 'no_exchange')
        <div class="alert alert-danger exchange-access-alert">
            <div class="alert-content">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-text">
                    <strong>برای استفاده از این قسمت، لطفاً ابتدا صرافی خود را فعال کنید.</strong>
                    <br>
                    <a href="{{ route('profile.index') }}" class="alert-link">
                        رفتن به صفحه پروفایل
                    </a>
                </div>
            </div>
        </div>
    @elseif($restrictionReason === 'insufficient_access')
        @php
            $accessMessages = [
                'spot' => 'برای استفاده از این صفحه، کلید API شما باید مجوز معاملات اسپات داشته باشد.',
                'futures' => 'برای استفاده از این صفحه، کلید API شما باید مجوز معاملات آتی داشته باشد.',
                'any' => 'کلید API شما هیچ مجوز معاملاتی ندارد.'
            ];
            $message = $accessMessages[$requiredAccess] ?? $accessMessages['any'];
            $currentExchange = $exchangeAccess['current_exchange'] ?? null;
        @endphp
        <div class="alert alert-warning exchange-access-alert">
            <div class="alert-content">
                <i class="fas fa-lock"></i>
                <div class="alert-text">
                    <strong>{{ $message }}</strong>
                    @if($currentExchange)
                        <br>
                        <small>صرافی فعلی: {{ $currentExchange->exchange_display_name }}</small>
                        <br>
                        <small>دسترسی اسپات: {{ $exchangeAccess['spot_access'] ? 'دارد' : 'ندارد' }} | 
                               دسترسی آتی: {{ $exchangeAccess['futures_access'] ? 'دارد' : 'ندارد' }}</small>
                        @if($exchangeAccess['validation_summary'])
                            <br>
                            <small>وضعیت: {{ $exchangeAccess['validation_summary'] }}</small>
                        @endif
                        <br>
                        <a href="{{ route('exchanges.edit', $currentExchange) }}" class="alert-link">
                            ویرایش تنظیمات صرافی
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endif

{{-- Check for session-based flash messages (for backward compatibility) --}}
@if(session('exchange_required'))
    @php $exchangeRequired = session('exchange_required'); @endphp
    <div class="alert alert-danger exchange-access-alert">
        <div class="alert-content">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="alert-text">
                <strong>{{ $exchangeRequired['message'] }}</strong>
                <br>
                <a href="{{ $exchangeRequired['link_url'] }}" class="alert-link">
                    {{ $exchangeRequired['link_text'] }}
                </a>
            </div>
        </div>
    </div>
@endif

@if(session('access_restricted'))
    @php $accessRestrictedSession = session('access_restricted'); @endphp
    <div class="alert alert-warning exchange-access-alert">
        <div class="alert-content">
            <i class="fas fa-lock"></i>
            <div class="alert-text">
                <strong>{{ $accessRestrictedSession['message'] }}</strong>
                <br>
                <small>صرافی فعلی: {{ $accessRestrictedSession['current_exchange'] }}</small>
                <br>
                @if($accessRestrictedSession['validation_summary'])
                    <small>وضعیت دسترسی: {{ $accessRestrictedSession['validation_summary'] }}</small>
                    <br>
                @endif
                <a href="{{ $accessRestrictedSession['edit_url'] }}" class="alert-link">
                    {{ $accessRestrictedSession['edit_text'] }}
                </a>
            </div>
        </div>
    </div>
@endif

{{-- Check for validation errors related to access --}}
@if($errors->has('access'))
    <div class="alert alert-danger exchange-access-alert">
        <div class="alert-content">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="alert-text">
                <strong>{{ $errors->first('access') }}</strong>
            </div>
        </div>
    </div>
@endif

{{-- Check for dynamic access limitation messages (when API access changes) --}}
@if($exchangeAccess && $exchangeAccess['current_exchange'])
    @php
        $currentExchange = $exchangeAccess['current_exchange'];
        $lastValidation = $currentExchange->last_validation_at;
        $validationMessage = $currentExchange->validation_message;
        
        // Check if validation was recent and shows limitation
        $isRecentValidation = $lastValidation && $lastValidation->diffInMinutes(now()) <= 60;
        $hasLimitationMessage = $validationMessage && str_contains(strtolower($validationMessage), 'limitation');
    @endphp
    
    @if($isRecentValidation && $hasLimitationMessage)
        <div class="alert alert-warning exchange-access-alert">
            <div class="alert-content">
                <i class="fas fa-exclamation-circle"></i>
                <div class="alert-text">
                    <strong>تغییر دسترسی API تشخیص داده شد</strong>
                    <br>
                    <small>{{ $validationMessage }}</small>
                    <br>
                    <small>زمان تشخیص: {{ $lastValidation->diffForHumans() }}</small>
                    <br>
                    <a href="{{ route('exchanges.edit', $currentExchange) }}" class="alert-link">
                        بررسی و به‌روزرسانی تنظیمات صرافی
                    </a>
                </div>
            </div>
        </div>
    @endif
@endif

@push('styles')
<style>
    .exchange-access-alert {
        border-radius: 8px;
        margin-bottom: 20px;
        padding: 0;
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .exchange-access-alert .alert-content {
        display: flex;
        align-items: flex-start;
        padding: 15px;
        gap: 12px;
        text-align: center;
        justify-content: center;
        flex-direction: column;
    }
    
    .exchange-access-alert .alert-content i {
        margin: 0 auto 10px;
    }
    
    .exchange-access-alert i {
        font-size: 20px;
        margin-top: 2px;
        flex-shrink: 0;
        align-self: center;
    }
    
    .exchange-access-alert .alert-text {
        flex: 1;
        line-height: 1.5;
        text-align: center;
        width: 100%;
    }
    
    .exchange-access-alert .alert-link {
        color: inherit;
        text-decoration: underline;
        font-weight: bold;
        transition: opacity 0.3s;
    }
    
    .exchange-access-alert .alert-link:hover {
        opacity: 0.8;
        color: inherit;
        text-decoration: underline;
    }
    
    .alert-danger.exchange-access-alert {
        background: #f8d7da;
        color: #c53030;
        border-left: 4px solid #e53e3e;
    }
    
    .alert-warning.exchange-access-alert {
        background: #fff3cd;
        color: #856404;
        border-left: 4px solid #ffc107;
    }
    
    .alert-danger.exchange-access-alert i {
        color: #e53e3e;
    }
    
    .alert-warning.exchange-access-alert i {
        color: #ffc107;
    }
    
    /* Form disabling styles */
    .access-restricted form,
    .access-restricted .form-control,
    .access-restricted .submit-btn,
    .access-restricted .side-btn,
    .access-restricted button:not(.alert-link) {
        opacity: 0.6;
        pointer-events: none;
        cursor: not-allowed;
    }
    
    .access-restricted .submit-btn {
        background: #6c757d !important;
    }
</style>
@endpush

{{-- Add body class for access restriction --}}
@if($accessRestricted || session('access_restricted') || session('exchange_required') || ($exchangeAccess && !$exchangeAccess['current_exchange']))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('access-restricted');
        });
    </script>
    @endpush
@endif