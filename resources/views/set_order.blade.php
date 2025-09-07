@extends('layouts.app')

@section('title', 'New Order')

@push('styles')
<style>
    .container {
        max-width: 500px;
        margin: auto;
        padding:20px;
    }
    h2 {
        text-align: center;
        margin-bottom: 20px;
    }
    .form-group {
        margin-bottom: 15px;
    }
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 400;
        color: #ffffff;
    }
    input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 8px rgba(0,123,255,0.25);
        outline: none;
    }
    input[type=number] {
        direction: ltr;
        text-align: left;
    }
    select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
        background-color: white;
        color: #333;
    }
    select:focus {
        border-color: var(--primary-color);
        outline: none;
    }
    button {
        width: 100%;
        padding: 14px;
        background: linear-gradient(90deg, var(--primary-color), var(--primary-hover));
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        margin-top: 20px;
        cursor: pointer;
        transition: opacity 0.3s;
    }
    button:hover {
        opacity: 0.9;
    }
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        text-align: center;
        font-size: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .alert-success {
        background-color: #d1e7dd;
        color: #0f5132;
        border: 1px solid #badbcc;
        border-left: 4px solid #28a745;
    }
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f1aeb5;
        border-left: 4px solid #dc3545;
    }
    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
        border-left: 4px solid #ffc107;
    }
    .invalid-feedback { color: #842029; font-size: 14px; margin-top: 5px; display: block; }
</style>
@endpush

@section('content')
<div class="glass-card container">
    @if(isset($user) && $user->future_strict_mode && $selectedMarket)
        <h2>ثبت سفارش جدید - {{ $selectedMarket }}</h2>
    @else
        <h2>ثبت سفارش جدید</h2>
    @endif

    @include('partials.exchange-access-check')

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ route('order.store') }}" method="POST" id="order-form">
        @csrf

        @if(isset($user) && $user->future_strict_mode)
            {{-- For strict mode users, show selected market as read-only --}}
            @if($selectedMarket)
                <input type="hidden" name="symbol" value="{{ $selectedMarket }}">
            @else
                <div class="alert alert-warning">
                    برای استفاده از حالت سخت‌گیرانه، ابتدا باید در تنظیمات بازار مورد نظر را انتخاب کنید.
                </div>
            @endif
        @else
            {{-- For non-strict mode users, show market dropdown --}}
            <div class="form-group">
                <label for="symbol">انتخاب بازار:</label>
                <select id="symbol" name="symbol" required>
                    <option value="">انتخاب بازار...</option>
                    @foreach($availableMarkets as $market)
                        <option value="{{ $market }}" {{ old('symbol', 'BTCUSDT') == $market ? 'selected' : '' }}>
                            {{ $market }}
                        </option>
                    @endforeach
                </select>
                @error('symbol') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>
        @endif

        <div class="form-group">
            <label for="entry1">Entry 1 (پایین‌ترین نقطه ورود):</label>
            <input id="entry1" type="number" name="entry1" step="any" required value="{{ old('entry1') }}">
            @error('entry1') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="entry2">
                Entry 2 (بالاترین نقطه ورود):
                <span id="chain-icon" style="cursor: pointer; font-size: 20px;" title="Toggle Chained Prices">⛓️</span>
            </label>
            <input id="entry2" type="number" name="entry2" step="any" required value="{{ old('entry2') }}">
            @error('entry2') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="tp">Take Profit (TP):</label>
            <input id="tp" type="number" name="tp" step="any" required value="{{ old('tp') }}">
            @error('tp') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="sl">Stop Loss (SL):</label>
            <input id="sl" type="number" name="sl" step="any" required value="{{ old('sl') }}">
            @error('sl') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="steps">تعداد پله‌ها:</label>
            <input id="steps" type="number" name="steps" min="1" value="{{ old('steps', 4) }}" required>
            @error('steps') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-row" style="display: flex; gap: 15px;">
            <div class="form-group" style="flex: 1;">
                <label for="expire">
                    مدت انقضای سفارش (دقیقه)
                </label>
                <input id="expire" type="number" name="expire" min="1" value="{{ old('expire', $defaultExpiration ?? 999) }}" required>
                @error('expire') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

            <div class="form-group" style="flex: 1;">
                <label for="cancel_price">
                    قیمت لغو سفارش (اختیاری)
                </label>
                <input id="cancel_price" type="number" name="cancel_price" step="any" value="{{ old('cancel_price') }}" placeholder="در صورت پر شدن، سفارش لغو می‌شود">
                @error('cancel_price') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-group">
            <label for="risk_percentage">
                درصد ریسک @if(isset($user) && $user->future_strict_mode)(حداکثر ۱۰٪ - حالت سخت‌گیرانه)@else(حداکثر ۱۰٪)@endif:
            </label>
            <input id="risk_percentage" type="number" name="risk_percentage" min="0.1" max="{{ isset($user) && $user->future_strict_mode ? '10' : '100' }}" step="0.1" value="{{ old('risk_percentage', 10) }}" required>
            @error('risk_percentage') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        {{-- Submit button with dynamic state based on access --}}
        @php
            $exchangeAccess = request()->attributes->get('exchange_access');
            $accessRestricted = request()->attributes->get('access_restricted', false);
            $hasAccess = $exchangeAccess && $exchangeAccess['current_exchange'] && !$accessRestricted;
        @endphp

        <button type="submit" {{ !$hasAccess ? 'disabled' : '' }}>
            @if($hasAccess)
                ارسال سفارش
            @else
                برای ارسال سفارش ابتدا صرافی فعال کنید
            @endif
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const entry1Input = document.getElementById('entry1');
        const entry2Input = document.getElementById('entry2');
        const chainIcon = document.getElementById('chain-icon');
        const symbolSelect = document.getElementById('symbol');
        let isChained = true; // Chained by default

        function updateChainIcon() {
            if (isChained) {
                chainIcon.textContent = '⛓️';
                chainIcon.title = 'Prices are chained. Click to unchain.';
            } else {
                chainIcon.textContent = '🚫'; // Or any other "unchained" icon
                chainIcon.title = 'Prices are unchained. Click to chain.';
            }
        }

        // Function to fetch market price for selected symbol
        function fetchMarketPrice(symbol) {
            if (!symbol) return;

            // Show loading state with visual feedback
            entry1Input.style.backgroundColor = '#f8f9fa';
            entry2Input.style.backgroundColor = '#f8f9fa';
            entry1Input.placeholder = 'در حال دریافت قیمت بازار...';
            entry2Input.placeholder = 'در حال دریافت قیمت بازار...';

            fetch(`/api/market-price/${symbol}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                // Clear loading state first
                entry1Input.style.backgroundColor = '';
                entry2Input.style.backgroundColor = '';
                entry1Input.placeholder = '';
                entry2Input.placeholder = '';

                if (data.success && data.price) {
                        entry1Input.value = data.price;
                        if (isChained) {
                            entry2Input.value = data.price;
                        }
                        // Flash success feedback
                        entry1Input.style.backgroundColor = '#d4edda';
                        if (isChained) {
                            entry2Input.style.backgroundColor = '#d4edda';
                        }
                        setTimeout(() => {
                            entry1Input.style.backgroundColor = '';
                            entry2Input.style.backgroundColor = '';
                        }, 1000);

                } else {
                    // On error, clear the input values silently (no alert)
                    console.warn('Failed to fetch market price:', data.message);
                    entry1Input.value = '';
                    entry2Input.value = '';
                }
            })
            .catch(error => {
                console.error('Error fetching market price:', error);
                // Clear loading state and input values on network error
                entry1Input.style.backgroundColor = '';
                entry2Input.style.backgroundColor = '';
                entry1Input.placeholder = '';
                entry2Input.placeholder = '';
                entry1Input.value = '';
                entry2Input.value = '';
            });
        }

        // Initial state
        updateChainIcon();
        if (entry1Input.value !== entry2Input.value) {
            isChained = false;
            updateChainIcon();
        }

        // Event Listeners
        entry1Input.addEventListener('input', function() {
            if (isChained) {
                entry2Input.value = this.value;
            }
        });

        entry2Input.addEventListener('input', function() {
            if (isChained && entry1Input.value !== this.value) {
                isChained = false;
                updateChainIcon();
            }
        });

        chainIcon.addEventListener('click', function() {
            isChained = !isChained;
            if (isChained) {
                entry2Input.value = entry1Input.value;
            }
            updateChainIcon();
        });

        // Market selection change event (only for non-strict mode)
        if (symbolSelect) {
            symbolSelect.addEventListener('change', function() {
                const selectedSymbol = this.value;
                if (selectedSymbol) {
                    fetchMarketPrice(selectedSymbol);
                }
            });
        }

        // --- Initial market price logic ---
        const marketPrice = '{{ $marketPrice ?? '' }}';
        const isStrictMode = {{ isset($user) && $user->future_strict_mode ? 'true' : 'false' }};
        const selectedMarket = '{{ $selectedMarket ?? '' }}';

        // For strict mode, use server-provided price
        if (isStrictMode && marketPrice && marketPrice !== '0') {
            if (entry1Input.value === '') {
                entry1Input.value = marketPrice;
                if (isChained) {
                    entry2Input.value = marketPrice;
                }
            }
        }
        // For non-strict mode, fetch price for the default selected market
        else if (!isStrictMode && symbolSelect) {
            const defaultSymbol = symbolSelect.value;
            if (defaultSymbol && entry1Input.value === '') {
                fetchMarketPrice(defaultSymbol);
            }
        }
    });
</script>
@endpush
