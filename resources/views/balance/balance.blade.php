@extends('layouts.app')

@section('title', 'موجودی حساب')

@push('styles')
<style>
    .container {
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        max-width: 1200px;
        margin: 0 auto;
    }

    .balance-container {
        width: 100%;
    }

    .balance-card {
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .balance-header {
        color: white;
        padding: 20px;
        text-align: center;
    }

    .balance-header h2 {
        margin: 0;
        font-size: 1.5em;
        font-weight: 600;
    }

    .balance-header .total-equity {
        font-size: 2em;
        font-weight: bold;
        margin: 10px 0;
    }

    .balance-content {
        padding: 20px;
    }

    .currency-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid rgba(238, 238, 238, 0.2);
    }

    .currency-item:last-child {
        border-bottom: none;
    }

    .currency-name {
        font-weight: 600;
        color: #fdfdfd;
    }

    .currency-balance {
        text-align: left;
        direction: ltr;
    }

    .balance-value {
        font-weight: bold;
        color: #ffffff;
    }

    .balance-usd {
        font-size: 0.9em;
        color: #718096;
    }

    .empty-balance {
        text-align: center;
        padding: 40px 20px;
        color: #718096;
    }

    .error-message {
        background: #fed7d7;
        color: #c53030;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 20px;
        line-height: 1.6;
    }

    .error-message a {
        display: inline-block;
        margin-top: 10px;
        color: #c53030;
        text-decoration: underline;
        font-weight: bold;
    }

    .loading {
        text-align: center;
        padding: 40px;
        color: #718096;
    }

    .refresh-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-size: 16px;
        cursor: pointer;
        width: 100%;
        margin-top: 10px;
    }

    .exchange-info {
        padding: 10px 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        text-align: center;
        color: #fdfdfd;
        font-weight: 500;
    }

    .balance-type-tabs {
        display: flex;
        border-radius: 8px;
        margin-bottom: 20px;
        overflow: hidden;
    }

    .balance-tab {
        flex: 1;
        padding: 12px;
        text-align: center;
        background: transparent;
        border: none;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .balance-tab.active {
        background: var(--primary-color);
        color: white;
    }

    .balance-section {
        display: none;
    }

    .balance-section.active {
        display: block;
    }
</style>
@endpush

@section('content')
<div class="glass-card container">
    <div class="balance-container">
        <h1 style="text-align: center; margin-bottom: 50px; color: #ffffff;"> موجودی حساب {{ $exchangeInfo['name'] ?? '' }}</h1>

    @if(isset($error))
        <div class="alert alert-info" style="text-align: center;">
            <div style="margin-bottom: 8px;"><strong>{{ $error }}</strong></div>
            @if(str_contains($error, 'صرافی'))
                <a href="{{ route('profile.show') }}" class="alert-link">برای فعال‌سازی صرافی کلیک کنید</a>
            @endif
        </div>
    @else
        <div class="balance-type-tabs">
            <button class="balance-tab active" onclick="switchTab('spot')">
                کیف پول اسپات
            </button>
            <button class="balance-tab" onclick="switchTab('perpetual')">
                کیف پول آتی
            </button>
        </div>

        <!-- Spot Balance Section -->
        <div class="balance-section active" id="spot-section">
            @if(isset($spotBalances) && count($spotBalances) > 0)
                <div class="balance-card">
                    <div class="balance-header spot">
                        <h2>کیف پول اسپات</h2>
                        <div class="total-equity">
                            ${{ number_format($spotTotalEquity ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="balance-content">
                        @foreach($spotBalances as $balance)
                            @if($balance['walletBalance'] > 0)
                                <div class="currency-item">
                                    <div class="currency-name">{{ $balance['currency'] }}</div>
                                    <div class="currency-balance">
                                        <div class="balance-value">{{ number_format($balance['walletBalance'], 8) }}</div>
                                        @if(isset($balance['usdValue']) && $balance['usdValue'] > 0)
                                            <div class="balance-usd">${{ number_format($balance['usdValue'], 2) }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @else
                <div class="balance-card">
                    <div class="balance-header spot">
                        <h2>کیف پول اسپات</h2>
                        <div class="total-equity">$0.00</div>
                    </div>
                    <div class="empty-balance">
                        @if(isset($spotError))
                            {{ $spotError }}
                        @else
                            موجودی اسپات یافت نشد
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Perpetual Balance Section -->
        <div class="balance-section" id="perpetual-section">
            @if(isset($perpetualBalances) && count($perpetualBalances) > 0)
                <div class="balance-card">
                    <div class="balance-header perpetual">
                        <h2>کیف پول آتی</h2>
                        <div class="total-equity">
                            ${{ number_format($perpetualTotalEquity ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="balance-content">
                        @foreach($perpetualBalances as $balance)
                            @if($balance['walletBalance'] > 0)
                                <div class="currency-item">
                                    <div class="currency-name">{{ $balance['coin'] }}</div>
                                    <div class="currency-balance">
                                        <div class="balance-value">{{ number_format($balance['walletBalance'], 8) }}</div>
                                        @if(isset($balance['usdValue']) && $balance['usdValue'] > 0)
                                            <div class="balance-usd">${{ number_format($balance['usdValue'], 2) }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @else
                <div class="balance-card">
                    <div class="balance-header perpetual">
                        <h2>کیف پول آتی</h2>
                        <div class="total-equity">$0.00</div>
                    </div>
                    <div class="empty-balance">
                        @if(isset($perpetualError))
                            {{ $perpetualError }}
                        @else
                            موجودی آتی یافت نشد
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <button class="refresh-btn" onclick="location.reload()">
            بروزرسانی موجودی
        </button>
    @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    function switchTab(tabName) {
        // Remove active class from all tabs and sections
        const tabs = document.querySelectorAll('.balance-tab');
        const sections = document.querySelectorAll('.balance-section');

        tabs.forEach(tab => tab.classList.remove('active'));
        sections.forEach(section => section.classList.remove('active'));

        // Add active class to selected tab and section
        event.target.classList.add('active');
        document.getElementById(tabName + '-section').classList.add('active');
    }
</script>
@endpush
