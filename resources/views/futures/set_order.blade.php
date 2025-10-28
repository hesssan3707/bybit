@extends('layouts.app')

@section('title', 'New Order')

@push('styles')
<style>
    .page-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        max-width: 1200px;
        margin: auto;
    }
    .tradingview-container {
        flex: 1;
        min-width: 300px;
    }
    .form-container {
        flex: 1;
        min-width: 300px;
        max-width: 800px; /* Adjusted for wider form */
        margin: auto; /* Center the form container */
    }
    .tradingview-container {
        margin-bottom: 20px; /* Space between chart and form fields */
    }
    .container {
        width: 100%;
        padding:20px;
        box-sizing: border-box;
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
        font-weight: 400;
        color: #ffffff;
        height: 30px;
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
    .submit-form-button {
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
    .submit-form-button:hover {
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
    }
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
    }
    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
    }
    .invalid-feedback { color: #842029; font-size: 14px; margin-top: 5px; display: block; }

    .form-row {
        display: flex;
        gap: 15px;
    }

    @media (max-width: 768px) {
        .page-container {
            flex-direction: column;
            align-items: center;
        }
        .form-row {
            gap: 10px;
        }
        .container {
            padding: 10px;
        }
    }
</style>
@endpush

@section('content')
<div class="page-container">
    <div class="form-container">
        <div class="glass-card container">
    @if(isset($user) && $user->future_strict_mode && $selectedMarket)
        <h2>ثبت سفارش جدید - {{ $selectedMarket }}</h2>
    @else
        <h2>ثبت سفارش جدید</h2>
    @endif

    <div class="tradingview-container">
        <!-- TradingView Widget BEGIN -->
        <div class="tradingview-widget-container">
          <div id="tradingview_12345"></div>
          <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const symbolSelect = document.getElementById('symbol');
                let exchangeName = "{{ $user['currentExchange']['exchange_name'] }}".toUpperCase();

                function updateTradingViewWidget(symbol) {
                    if (!symbol) return;
                    const tradingViewSymbol = `${exchangeName}:${symbol}`;
                    new TradingView.widget({
                        "width": "100%",
                        "height": 300,
                        "symbol": tradingViewSymbol,
                        "interval": "5",
                        "timezone": "Etc/UTC",
                        "theme": "dark",
                        "style": "1",
                        "locale": "en",
                        "toolbar_bg": "#f1f3f6",
                        "enable_publishing": false,
                        "allow_symbol_change": false,
                        "hide_side_toolbar": true,
                        "container_id": "tradingview_12345"
                    });
                }

                if (symbolSelect) {
                    symbolSelect.addEventListener('change', function () {
                        updateTradingViewWidget(this.value);
                    });
                    // Initial load
                    updateTradingViewWidget(symbolSelect.value);
                } else {
                    const selectedMarket = "{{ $selectedMarket ?? '' }}";
                    if (selectedMarket) {
                        updateTradingViewWidget(selectedMarket);
                    }
                }
            });
        </script>
        </div>
        <!-- TradingView Widget END -->
    </div>

    @include('partials.exchange-access-check')

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @php $hedgeHintDetected = false; @endphp
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
                @php
                    // Broaden detection for position mode related errors (Persian & English variants)
                    $msgLower = strtolower($error);
                    $hedgeKeywords = [
                        'حالت معاملاتی',
                        'حالت موقعیت',
                        'دوطرفه',
                        'یک‌طرفه',
                        'یک طرفه', // alternate typing
                        'hedge',
                        'one-way',
                        'position mode',
                        'position side does not match',
                        'positionidx',
                        'idx not match'
                    ];
                    foreach ($hedgeKeywords as $kw) {
                        if (strpos($msgLower, strtolower($kw)) !== false || strpos($error, $kw) !== false) {
                            $hedgeHintDetected = true;
                            break;
                        }
                    }
                @endphp
            @endforeach
            @php $exchangeAccess = request()->attributes->get('exchange_access'); @endphp
            @if(!empty($exchangeAccess['current_exchange']) && $hedgeHintDetected)
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-primary" id="enable-hedge-btn" data-exchange-id="{{ $exchangeAccess['current_exchange']->id }}">
                        تغییر حالت به Hedge
                    </button>
                </div>
            @endif
        </div>
    @endif

    <form action="{{ route('futures.order.store') }}" method="POST" id="order-form">
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

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label for="entry1">پایین‌ترین نقطه ورود*:</label>
                <input id="entry1" type="number" name="entry1" step="any" required value="{{ old('entry1') }}">
                @error('entry1') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

            <div class="form-group" style="flex: 1;">
                <label for="entry2">
                    بالاترین نقطه ورود*:
                    <span id="chain-icon" style="cursor: pointer; font-size: 20px;" title="Toggle Chained Prices">⛓️</span>
                </label>
                <input id="entry2" type="number" name="entry2" step="any" required value="{{ old('entry2') }}">
                @error('entry2') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label for="sl">حد ضرر (SL)*:</label>
                <input id="sl" type="number" name="sl" step="any" required value="{{ old('sl') }}">
                @error('sl') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

            <div class="form-group" style="flex: 1;">
                <label for="tp">حد سود (TP)*:</label>
                <input id="tp" type="number" name="tp" step="any" required value="{{ old('tp') }}">
                @error('tp') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label for="steps">تعداد پله‌ها*:</label>
                <input id="steps" type="number" name="steps" min="1" max="8" value="{{ old('steps', $defaultFutureOrderSteps ?? 1) }}" required>
                @error('steps') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

            <div class="form-group" style="flex: 1;">
                <label for="risk_percentage">
                    درصد ریسک @if(isset($user) && $user->future_strict_mode)(حداکثر ۱۰٪)@endif:
                </label>
                <input id="risk_percentage" type="number" name="risk_percentage" min="0.1" max="{{ isset($user) && $user->future_strict_mode ? '10' : '100' }}" step="0.1" value="{{ old('risk_percentage', $defaultRisk ?? 10) }}" required>
                @error('risk_percentage') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label for="expire">
                    مدت انقضای سفارش
                </label>
                <input id="expire" type="number" name="expire" min="1" max="999" value="{{ old('expire', $defaultExpiration ?? '') }}" placeholder="دقیقه" >
                @error('expire') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

            <div class="form-group" style="flex: 1;">
                <label for="cancel_price">
                    قیمت لغو سفارش
                </label>
                <input id="cancel_price" type="number" name="cancel_price" step="any" value="{{ old('cancel_price') }}">
                @error('cancel_price') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Submit button with dynamic state based on access --}}
        @php
            $exchangeAccess = request()->attributes->get('exchange_access');
            $accessRestricted = request()->attributes->get('access_restricted', false);
            $hasAccess = $exchangeAccess && $exchangeAccess['current_exchange'] && !$accessRestricted;
        @endphp

        <button class="submit-form-button" type="submit" {{ !$hasAccess ? 'disabled' : '' }}>
            @if($hasAccess)
                ارسال سفارش
            @else
                برای ارسال سفارش ابتدا صرافی فعال کنید
            @endif
        </button>
    </form>
</div>
</div>
</div>
@endsection

@include('partials.alert-modal')

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

        // Hedge mode enable handler if error hint detected
        var hedgeBtn = document.getElementById('enable-hedge-btn');
        if (hedgeBtn) {
            hedgeBtn.addEventListener('click', function () {
                var exchangeId = this.getAttribute('data-exchange-id');
                modernConfirm(
                    'تغییر حالت معاملاتی',
                    'حساب شما در حالت One-way است. با تایید، به Hedge تغییر داده می‌شود.',
                    function () {
                        var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        fetch('/exchanges/' + exchangeId + '/enable-hedge', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                        }).then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (data && data.success) {
                                modernAlert('حالت معاملاتی به Hedge تغییر شد. لطفاً مجدد سفارش خود را ثبت کنید.', 'success');
                            } else {
                                modernAlert((data && data.message) ? data.message : 'خطا در تغییر حالت معاملاتی', 'error');
                            }
                        }).catch(function () {
                            modernAlert('خطا در ارتباط با سرور', 'error');
                        });
                    },
                    function () {}
                );
            });
        }
    });
</script>
@endpush
