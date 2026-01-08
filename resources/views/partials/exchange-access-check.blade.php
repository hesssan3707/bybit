{{-- Exchange Access Check Partial --}}
{{-- This partial handles display of exchange access messages and form disabling --}}

@php
    $exchangeAccess = request()->attributes->get('exchange_access');
    $accessRestricted = request()->attributes->get('access_restricted', false);
    $restrictionReason = request()->attributes->get('restriction_reason');
    $requiredAccess = request()->attributes->get('required_access', 'any');
    $user = auth()->user();
@endphp

{{-- Do not show investors any warnings related to exchange access, exchange expiration, or missing exchanges. --}}
@if($user && $user->isInvestor())
    @php return; @endphp
@endif

{{-- Check for access restrictions based on middleware --}}
@if($accessRestricted)
    @if($restrictionReason === 'no_exchange')
        <div class="no-exchange-card">
            <div class="no-exchange-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="no-exchange-content">
                <h3>صرافی فعالی ندارید</h3>
                <p>برای شروع معاملات، ابتدا یک صرافی اضافه کنید</p>
                <a href="{{ route('exchanges.create') }}" class="add-exchange-btn">
                    <i class="fas fa-plus"></i>
                    افزودن صرافی
                </a>
            </div>
        </div>
    @elseif($restrictionReason === 'insufficient_access')
        @php
            $currentExchange = $exchangeAccess['current_exchange'] ?? null;
            $accessState = $currentExchange ? $currentExchange->getAccessState() : null;
            // Detect previously approved exchange with now-invalid/expired API access
            $isExpiredInvalid = false;
            if ($currentExchange) {
                try {
                    $statusApproved = ($currentExchange->status === 'approved');
                    $recentlyValidated = method_exists($currentExchange, 'hasRecentValidation') ? $currentExchange->hasRecentValidation() : (bool)$currentExchange->last_validation_at;
                    $spotColumn = (bool) ($accessState['spot_access'] ?? null);
                    $futuresColumn = (bool) ($accessState['futures_access'] ?? null);
                    $ipOk = ($accessState['ip_access'] ?? null) !== false; // treat null as unknown/ok
                    $isExpiredInvalid = $statusApproved && $recentlyValidated && $ipOk && (!$spotColumn || !$futuresColumn);
                } catch (\Throwable $e) {
                    $isExpiredInvalid = false;
                }
            }
        @endphp
        <div class="no-exchange-card">
            <div class="no-exchange-icon">
                <i class="fas fa-lock"></i>
            </div>
            <div class="no-exchange-content">
                @if($isExpiredInvalid)
                    <h3>کلید API شما احتمالاً منقضی شده یا تغییر کرده است</h3>
                    <p>برای ادامه، تنظیمات صرافی را بررسی و به‌روزرسانی کنید.</p>
                @else
                    <h3>دسترسی API کافی نیست</h3>
                    <p>برای ادامه، تنظیمات صرافی را بررسی و به‌روزرسانی کنید.</p>
                @endif
                @if($currentExchange)
                    <a href="{{ route('exchanges.edit', $currentExchange) }}" class="add-exchange-btn">
                        <i class="fas fa-cog"></i>
                        ویرایش تنظیمات صرافی
                    </a>
                @else
                    <a href="{{ route('exchanges.create') }}" class="add-exchange-btn">
                        <i class="fas fa-plus"></i>
                        افزودن صرافی
                    </a>
                @endif
            </div>
        </div>
    @endif
@endif

{{-- Check for session-based flash messages (for backward compatibility) --}}
@if(session('exchange_required'))
    @php $exchangeRequired = session('exchange_required'); @endphp
    <div class="no-exchange-card">
        <div class="no-exchange-icon">
            <i class="fas fa-circle-info"></i>
        </div>
        <div class="no-exchange-content">
            <h3>نیاز به فعال‌سازی صرافی</h3>
            <p>{{ $exchangeRequired['message'] }}</p>
            <a href="{{ $exchangeRequired['link_url'] }}" class="add-exchange-btn">
                <i class="fas fa-exchange-alt"></i>
                {{ $exchangeRequired['link_text'] }}
            </a>
        </div>
    </div>
@endif

@if(session('access_restricted'))
    @php $accessRestrictedSession = session('access_restricted'); @endphp
    <div class="no-exchange-card">
        <div class="no-exchange-icon">
            <i class="fas fa-lock"></i>
        </div>
        <div class="no-exchange-content">
            <h3>دسترسی محدود شده است</h3>
            <p>{{ $accessRestrictedSession['message'] }}</p>
            <a href="{{ $accessRestrictedSession['edit_url'] }}" class="add-exchange-btn">
                <i class="fas fa-cog"></i>
                {{ $accessRestrictedSession['edit_text'] }}
            </a>
        </div>
    </div>
@endif

{{-- Check for validation errors related to access --}}
@if($errors->has('access'))
    <div class="no-exchange-card">
        <div class="no-exchange-icon" style="background: linear-gradient(135deg, #f44336, #d32f2f);">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="no-exchange-content">
            <h3>خطای دسترسی</h3>
            <p>{{ $errors->first('access') }}</p>
        </div>
    </div>
@endif

{{-- Check for dynamic access limitation messages (when API access changes) --}}
@if($exchangeAccess && $exchangeAccess['current_exchange'])
    @php
        $currentExchange = $exchangeAccess['current_exchange'];
        $accessState = $currentExchange->getAccessState();
        $lastValidation = $accessState['last_validation_at'] ?? null;
        $validationMessage = $accessState['validation_message'] ?? null;

        $isRecentValidation = $lastValidation && $lastValidation->diffInMinutes(now()) <= 60;
        $hasLimitationMessage = $validationMessage && str_contains(strtolower($validationMessage), 'limitation');
    @endphp

    @if($isRecentValidation && $hasLimitationMessage)
        <div class="no-exchange-card">
            <div class="no-exchange-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="no-exchange-content">
                <h3>تغییر دسترسی API تشخیص داده شد</h3>
                <p>{{ $validationMessage }}</p>
                <a href="{{ route('exchanges.edit', $currentExchange) }}" class="add-exchange-btn">
                    <i class="fas fa-sync-alt"></i>
                    بررسی و به‌روزرسانی تنظیمات صرافی
                </a>
            </div>
        </div>
    @endif
@endif

{{-- Fallback warning for pages without middleware that set access attributes (e.g., Balance) --}}
@if(isset($error) && $error)
    <div class="no-exchange-card">
        <div class="no-exchange-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="no-exchange-content">
            <h3>صرافی فعالی ندارید</h3>
            <p>برای شروع معاملات، ابتدا یک صرافی اضافه کنید</p>
            @php $currentExchange = $exchangeAccess['current_exchange'] ?? null; @endphp
            @if($currentExchange)
                <a href="{{ route('exchanges.edit', $currentExchange) }}" class="add-exchange-btn">
                    <i class="fas fa-cog"></i>
                    ویرایش تنظیمات صرافی
                </a>
            @else
                <a href="{{ route('exchanges.create') }}" class="add-exchange-btn">
                    <i class="fas fa-plus"></i>
                    افزودن صرافی
                </a>
            @endif
        </div>
    </div>
@endif

@push('styles')
<style>
    /* Exact profile no-exchange-card styles */
    .no-exchange-card {
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 152, 0, 0.05));
        backdrop-filter: blur(15px);
        border-radius: 18px;
        border: 1px solid rgba(255, 193, 7, 0.2);
        padding: 40px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
        margin-bottom: 20px;
    }
    .no-exchange-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #FFC107, #FF9800);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2em;
        color: white;
        box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
    }
    .no-exchange-content h3 {
        margin: 0 0 10px 0;
        font-size: 1.5em;
        font-weight: 700;
        color: #fff;
    }
    .no-exchange-content p {
        margin: 0 0 20px 0;
        color: #bbb;
        font-size: 1.1em;
        line-height: 1.5;
    }
    .add-exchange-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 15px 25px;
        background: linear-gradient(135deg, #4CAF50, #45a049);
        color: white;
        text-decoration: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1em;
        transition: all 0.3s ease;
        box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
    }
    .add-exchange-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(76, 175, 80, 0.4);
        text-decoration: none;
        color: white;
    }
    @media (max-width: 768px) {
        .no-exchange-card { padding: 24px; gap: 16px; }
        .no-exchange-icon { width: 64px; height: 64px; font-size: 1.6em; }
        .no-exchange-content h3 { font-size: 1.2em; }
    }
</style>
@endpush

{{-- No JS body-class injection; warnings are visual-only per unified style --}}
