@extends('layouts.app')

@section('body-class', 'spot-page')

@section('title', 'Spot Balances')

@push('styles')
<style>
    .container {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        animation: fadeIn 0.5s ease-out;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px);} to { opacity: 1; transform: translateY(0);} }
    h2 {
        text-align: center;
        margin-bottom: 25px;
    }
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .summary-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .summary-card h3 {
        margin: 0 0 10px 0;
        font-size: 16px;
        opacity: 0.9;
    }
    .summary-card .amount {
        font-size: 24px;
        font-weight: bold;
        margin: 0;
    }

    .balances-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    .balance-card {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 20px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .balance-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .currency-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }
    .currency-name {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }
    .currency-icon {
        width: 30px;
        height: 30px;
        background: var(--primary-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 12px;
    }

    .balance-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    .balance-item {
        text-align: center;
    }
    .balance-item .label {
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .balance-item .value {
        font-size: 16px;
        font-weight: bold;
        color: #333;
    }
    .balance-item .usd-value {
        font-size: 12px;
        color: #28a745;
        margin-top: 2px;
    }

    .no-balances {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }
    .no-balances i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    .error-message {
        background: #f8d7da;
        color: #842029;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 20px;
    }

    .refresh-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 20px;
        transition: background-color 0.3s;
    }
    .refresh-btn:hover {
        background: var(--primary-hover);
        color: white;
        text-decoration: none;
    }

    @media screen and (max-width: 768px) {
        .summary-cards {
            grid-template-columns: 1fr;
        }
        .balances-grid {
            grid-template-columns: 1fr;
        }
        .balance-details {
            grid-template-columns: 1fr;
            gap: 10px;
        }
    }
</style>
@endpush

@section('content')
<div class="container">
    <h2>موجودی‌های اسپات</h2>

    @include('partials.exchange-access-check')

    {{-- Show refresh button only if user has proper access --}}
    @php
        $exchangeAccess = request()->attributes->get('exchange_access');
        $accessRestricted = request()->attributes->get('access_restricted', false);
        $hasExchangeAccess = $exchangeAccess && $exchangeAccess['current_exchange'] && !$accessRestricted;
    @endphp

    @if($hasExchangeAccess)
        <a href="{{ route('spot.balances.view') }}" class="refresh-btn">🔄 بروزرسانی موجودی‌ها</a>
    @endif

    @if(isset($error))
        <div class="error-message">
            {{ $error }}
        </div>
    @endif

    @if(!isset($error))
        <div class="summary-cards">
            <div class="summary-card">
                <h3>کل موجودی کیف پول</h3>
                <p class="amount">${{ number_format($totalWalletBalance, 2) }}</p>
            </div>
            <div class="summary-card">
                <h3>کل ارزش کیف پول</h3>
                <p class="amount">${{ number_format($totalEquity, 2) }}</p>
            </div>
        </div>

        @if(count($balances) > 0)
            <div class="balances-grid">
                @foreach($balances as $balance)
                    <div class="balance-card">
                        <div class="currency-header">
                            <div class="currency-name">{{ $balance['currency'] }}</div>
                            <div class="currency-icon">
                                {{ substr($balance['currency'], 0, 2) }}
                            </div>
                        </div>

                        <div class="balance-details">
                            <div class="balance-item">
                                <div class="label">موجودی کیف پول</div>
                                <div class="value">{{ number_format($balance['walletBalance'], 8) }}</div>
                                @if($balance['usdValue'])
                                    <div class="usd-value">${{ number_format($balance['usdValue'], 2) }}</div>
                                @endif
                            </div>

                            <div class="balance-item">
                                <div class="label">قابل انتقال</div>
                                <div class="value">{{ number_format($balance['transferBalance'], 8) }}</div>
                            </div>

                            @if($balance['bonus'] > 0)
                                <div class="balance-item">
                                    <div class="label">بونوس</div>
                                    <div class="value">{{ number_format($balance['bonus'], 8) }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="no-balances">
                <div style="font-size: 48px; margin-bottom: 15px;">💰</div>
                <h3>هیچ موجودی فعالی یافت نشد</h3>
                <p>حساب اسپات شما خالی است یا تمام موجودی‌ها صفر هستند.</p>
            </div>
        @endif
    @endif

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center;">
        <small style="color: #6c757d;">
            آخرین بروزرسانی: {{ now()->format('Y/m/d H:i:s') }}
        </small>
    </div>
</div>
@endsection
