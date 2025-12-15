@extends('layouts.app')

@section('title', 'Edit Order')

@push('styles')
<style>
    .page-container { display:flex; flex-wrap:wrap; gap:20px; max-width:1200px; margin:auto; }
    .form-container { flex:1; min-width:300px; max-width:800px; margin:auto; }
    .tradingview-container { flex:1; min-width:300px; margin-bottom:20px; }
    .container { width:100%; padding:20px; box-sizing:border-box; }
    h2 { text-align:center; margin-bottom:20px; }
    .form-group { margin-bottom:15px; }
    .form-row { display:flex; gap:15px; }
    label { display:block; font-weight:400; color:#ffffff; height:32px; }
    input, select { width:100%; padding:12px; border:1px solid #ccc; border-radius:8px; font-size:14px; box-sizing:border-box; transition:border-color .3s, box-shadow .3s; }
    input:focus, select:focus { border-color:var(--primary-color); box-shadow:0 0 8px rgba(0,123,255,.25); outline:none; }
    input[type=number]{ direction:ltr; text-align:left; }
    .submit-form-button { width:100%; padding:14px; background:linear-gradient(90deg, var(--primary-color), var(--primary-hover)); color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; margin-top:20px; cursor:pointer; transition:opacity .3s; }
    .submit-form-button:hover { opacity:.9; }
    .alert { padding:15px; margin-bottom:20px; border-radius:8px; text-align:center; font-size:16px; }
    .alert-success { background:#d1e7dd; color:#0f5132; }
    .alert-danger { background:#f8d7da; color:#721c24; }
    .alert-warning { background-color:#fff3cd; color:#856404; }
    .invalid-feedback { color:#842029; font-size:14px; margin-top:5px; display:block; }
    @media (max-width:768px){ .page-container{flex-direction:column; align-items:center;} .container{padding:10px;} }
    @media (max-width:768px){ .form-row{gap:10px;} }
</style>
@endpush

@section('content')
<div class="page-container">
    <div class="form-container">
        <div class="glass-card container">
            @if(isset($user) && $user->future_strict_mode && $selectedMarket)
                <h2>ویرایش سفارش - {{ $selectedMarket }}</h2>
            @else
                <h2>ویرایش سفارش</h2>
            @endif

            <div class="tradingview-container">
                <div class="tradingview-widget-container">
                  <div id="tradingview_12345"></div>
                  <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const symbolSelect = document.getElementById('symbol');
                        let exchangeName = "{{ $user['currentExchange']['exchange_name'] ?? 'BINANCE' }}".toUpperCase();

                        function updateTradingViewWidget(symbol) {
                            if (!symbol) return;
                            const tradingViewSymbol = `${exchangeName}:${symbol}.P`;
                            const defaultInterval = "{{ isset($tvDefaultInterval) ? $tvDefaultInterval : '5' }}";
                            new TradingView.widget({
                                "width": "100%",
                                "height": 400,
                                "symbol": tradingViewSymbol,
                                "interval": defaultInterval,
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
            </div>

            @include('partials.exchange-access-check')

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(isset($activeBan) && $activeBan)
                @php
                    $sec = isset($banRemainingSeconds) ? (int)$banRemainingSeconds : 0;
                    $days = intdiv($sec, 86400);
                    $hrs = intdiv($sec % 86400, 3600);
                    $mins = intdiv($sec % 3600, 60);
                    $initialCountdownText = $days > 0
                        ? sprintf('%d : %02d : %02d', $days, $hrs, $mins)
                        : sprintf('%02d : %02d', $hrs, $mins);
                    $banRemainingFa = ($days > 0 ? ($days . ' روز') . ' و ' : '') . ($hrs . ' ساعت') . ' و ' . ($mins . ' دقیقه');
                @endphp
                <div class="alert alert-warning" id="ban-alert">
                    مدیریت ریسک فعال است. لطفاً تا
                    <span id="ban-countdown">{{ $sec > 0 ? $initialCountdownText : '' }}</span>
                    دیگر (حدود {{ $banRemainingFa }}) برای ثبت سفارش صبر کنید.
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    @php $hedgeHintDetected = false; @endphp
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                        @php
                            $msgLower = strtolower($error);
                            $hedgeKeywords = [
                                'حالت معاملاتی','حالت موقعیت','دوطرفه','یک‌طرفه','یک طرفه','hedge','one-way','position mode','position side does not match','positionidx','idx not match'
                            ];
                            foreach ($hedgeKeywords as $kw) {
                                if (strpos($msgLower, strtolower($kw)) !== false || strpos($error, $kw) !== false) { $hedgeHintDetected = true; break; }
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

            <form action="{{ route('futures.order.update', $order->id) }}" method="POST" id="order-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="order_id" value="{{ $order->id }}">

                @if(isset($user) && $user->future_strict_mode)
                    @if($selectedMarket)
                        <input type="hidden" name="symbol" value="{{ $selectedMarket }}">
                    @else
                        <div class="alert alert-warning">برای استفاده از حالت سخت‌گیرانه، ابتدا باید در تنظیمات بازار مورد نظر را انتخاب کنید.</div>
                    @endif
                @else
                    <div class="form-group">
                        <label for="symbol">انتخاب بازار:</label>
                        <select id="symbol" name="symbol" required>
                            <option value="">انتخاب بازار...</option>
                            @foreach($availableMarkets as $market)
                                <option value="{{ $market }}" {{ ($prefill['symbol'] ?? $currentSymbol) == $market ? 'selected' : '' }}>{{ $market }}</option>
                            @endforeach
                        </select>
                        @error('symbol') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                @endif

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="entry1">پایین‌ترین نقطه ورود*:</label>
                        <input id="entry1" type="number" name="entry1" step="any" required value="{{ old('entry1', $prefill['entry1']) }}">
                        @error('entry1') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label for="entry2">
                            بالاترین نقطه ورود*:
                            <span id="chain-icon" style="cursor: pointer; font-size: 20px;" title="Toggle Chained Prices">⛓️</span>
                        </label>
                        <input id="entry2" type="number" name="entry2" step="any" required value="{{ old('entry2', $prefill['entry2']) }}">
                        @error('entry2') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="sl">حد ضرر (SL)*:</label>
                        <input id="sl" type="number" name="sl" step="any" required value="{{ old('sl', $prefill['sl']) }}">
                        @error('sl') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label for="tp">حد سود (TP)*:</label>
                        <input id="tp" type="number" name="tp" step="any" required value="{{ old('tp', $prefill['tp']) }}">
                        @error('tp') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="steps">تعداد پله‌ها*:</label>
                        <input id="steps" type="number" name="steps" min="1" max="8" value="{{ old('steps', $prefill['steps']) }}" required>
                        @error('steps') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label for="risk_percentage">
                            درصد ریسک @if(isset($user) && $user->future_strict_mode)(حداکثر ۱۰٪)@endif:
                        </label>
                        <input id="risk_percentage" type="number" name="risk_percentage" min="0.1" max="{{ isset($user) && $user->future_strict_mode ? '10' : '100' }}" step="0.1" value="{{ old('risk_percentage', $prefill['risk_percentage']) }}" required>
                        @error('risk_percentage') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="expire">مدت انقضای سفارش</label>
                        <input id="expire" type="number" name="expire" min="1" max="999" value="{{ old('expire', $prefill['expire']) }}" placeholder="دقیقه" >
                        @error('expire') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label for="cancel_price">
                            قیمت لغو سفارش
                        </label>
                        <input id="cancel_price" type="number" name="cancel_price" step="any" value="{{ old('cancel_price', $prefill['cancel_price']) }}">
                        @error('cancel_price') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                </div>

                @php
                    $exchangeAccess = request()->attributes->get('exchange_access');
                    $accessRestricted = request()->attributes->get('access_restricted', false);
                    $hasAccess = $exchangeAccess && $exchangeAccess['current_exchange'] && !$accessRestricted;
                @endphp

                @php
                    $banActive = isset($activeBan) && $activeBan && isset($banRemainingSeconds) && $banRemainingSeconds > 0;
                @endphp
                <button class="submit-form-button" type="submit" {{ (!$hasAccess || $banActive) ? 'disabled' : '' }}>
                    @if($hasAccess)
                        @if($banActive)
                            مدیریت ریسک فعال است
                        @else
                            ویرایش سفارش
                        @endif
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
        const stepsInput = document.getElementById('steps');
        let isChained = true; // Chained by default

        function updateChainIcon() {
            if (!chainIcon) return;
            if (isChained) { chainIcon.textContent = '⛓️'; chainIcon.title = 'Prices are chained. Click to unchain.'; }
            else { chainIcon.textContent = '🚫'; chainIcon.title = 'Prices are unchained. Click to chain.'; }
        }

        function fetchMarketPrice(symbol) {
            if (!symbol) return;
            entry1Input.style.backgroundColor = '#f8f9fa';
            entry2Input.style.backgroundColor = '#f8f9fa';
            entry1Input.placeholder = 'در حال دریافت قیمت بازار...';
            entry2Input.placeholder = 'در حال دریافت قیمت بازار...';
            fetch(`/api/market-price/${symbol}`, { method: 'GET', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } })
            .then(r => r.json())
            .then(data => {
                entry1Input.style.backgroundColor = ''; entry2Input.style.backgroundColor = ''; entry1Input.placeholder = ''; entry2Input.placeholder = '';
                if (data.success && data.price) {
                    entry1Input.value = data.price; if (isChained) entry2Input.value = data.price;
                    if (stepsInput && parseInt(stepsInput.value || '1', 10) <= 1) { var h = document.getElementById('entry2_hidden'); if (h) h.value = data.price; }
                    entry1Input.style.backgroundColor = '#d4edda'; if (isChained) entry2Input.style.backgroundColor = '#d4edda'; setTimeout(() => { entry1Input.style.backgroundColor = ''; entry2Input.style.backgroundColor = ''; }, 1000);
                } else { console.warn('Failed to fetch market price:', data.message); entry1Input.value = ''; entry2Input.value = ''; }
            })
            .catch(err => { console.error('Error fetching market price:', err); entry1Input.style.backgroundColor = ''; entry2Input.style.backgroundColor = ''; entry1Input.placeholder = ''; entry2Input.placeholder = ''; entry1Input.value = ''; entry2Input.value = ''; });
        }

        updateChainIcon();
        if (entry1Input.value !== entry2Input.value) { isChained = false; updateChainIcon(); }

        entry1Input.addEventListener('input', function() {
            if (isChained) { entry2Input.value = this.value; }
            if (stepsInput && parseInt(stepsInput.value || '1', 10) <= 1) { entry2Input.value = this.value; var h = document.getElementById('entry2_hidden'); if (h) h.value = this.value; }
        });
        entry2Input.addEventListener('input', function() { if (isChained && entry1Input.value !== this.value) { isChained = false; updateChainIcon(); } });
        if (chainIcon) { chainIcon.addEventListener('click', function() { if (stepsInput && parseInt(stepsInput.value || '1', 10) <= 1) { return; } isChained = !isChained; if (isChained) { entry2Input.value = entry1Input.value; } updateChainIcon(); }); }

        function ensureHiddenEntry2() { var h = document.getElementById('entry2_hidden'); if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = 'entry2'; h.id = 'entry2_hidden'; h.value = entry1Input.value; var form = document.getElementById('order-form'); if (form) form.appendChild(h); } return h; }
        function updateStepsControls() { if (!stepsInput) return; var stepsVal = parseInt(stepsInput.value || '1', 10); var isSingle = stepsVal <= 1; entry2Input.disabled = isSingle; if (isSingle) { isChained = true; entry2Input.value = entry1Input.value; var h = ensureHiddenEntry2(); h.value = entry1Input.value; updateChainIcon(); } else { var h2 = document.getElementById('entry2_hidden'); if (h2) h2.remove(); } }
        if (stepsInput) { updateStepsControls(); stepsInput.addEventListener('input', updateStepsControls); }

        const marketPrice = '{{ $marketPrice ?? '' }}';
        const isStrictMode = {{ isset($user) && $user->future_strict_mode ? 'true' : 'false' }};
        const selectedMarket = '{{ $selectedMarket ?? '' }}';
        if (isStrictMode && marketPrice && marketPrice !== '0') { if (entry1Input.value === '') { entry1Input.value = marketPrice; if (isChained) { entry2Input.value = marketPrice; } } }
        else if (!isStrictMode && symbolSelect) { const defaultSymbol = symbolSelect.value; if (defaultSymbol && entry1Input.value === '') { fetchMarketPrice(defaultSymbol); } }

        (function() {
            var countdownEl = document.getElementById('ban-countdown');
            var submitBtn = document.querySelector('.submit-form-button');
            var banSeconds = {{ isset($banRemainingSeconds) ? (int)$banRemainingSeconds : 0 }};
            function pad(n) { return (n < 10 ? '0' : '') + n; }
            function formatRemaining(sec) {
                if (sec <= 0) return '00 : 00';
                var days = Math.floor(sec / 86400);
                var hrs = Math.floor((sec % 86400) / 3600);
                var mins = Math.floor((sec % 3600) / 60);
                if (days > 0) return days + ' : ' + pad(hrs) + ' : ' + pad(mins);
                return pad(hrs) + ' : ' + pad(mins);
            }
            function tick() {
                if (!countdownEl) return;
                if (banSeconds <= 0) { countdownEl.textContent = '00 : 00'; if (submitBtn) submitBtn.disabled = false; var banAlert = document.getElementById('ban-alert'); if (banAlert) banAlert.style.display = 'none'; return; }
                countdownEl.textContent = formatRemaining(banSeconds);
                banSeconds -= 1; setTimeout(tick, 1000);
            }
            if (banSeconds > 0) { if (submitBtn) submitBtn.disabled = true; tick(); }
        })();

        var hedgeBtn = document.getElementById('enable-hedge-btn');
        if (hedgeBtn) {
            hedgeBtn.addEventListener('click', function () {
                var exchangeId = this.getAttribute('data-exchange-id');
                modernConfirm(
                    'تغییر حالت معاملاتی',
                    'حساب شما در حالت One-way است. با تایید، به Hedge تغییر داده می‌شود.',
                    function () {
                        var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        fetch('/exchanges/' + exchangeId + '/enable-hedge', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
                        .then(function (res) { return res.json(); })
                        .then(function (data) { if (data && data.success) { modernAlert('حالت معاملاتی به Hedge تغییر شد. لطفاً مجدد سفارش خود را ثبت کنید.', 'success'); } else { modernAlert((data && data.message) ? data.message : 'خطا در تغییر حالت معاملاتی', 'error'); } })
                        .catch(function () { modernAlert('خطا در ارتباط با سرور', 'error'); });
                    }, function () {}
                );
            });
        }

        // --- Strict mode: update TP placeholder based on SL & RR ---
        (function() {
            const tpInput = document.getElementById('tp');
            const slInput = document.getElementById('sl');
            if (!tpInput || !slInput) return;

            const minRrStr = '{{ isset($user) ? \App\Models\UserAccountSetting::getMinRrRatio($user->id) : '3:1' }}';

            function parseRr(str) {
                var parts = (str || '').split(':');
                var loss = parseFloat(parts[0] || '1');
                var profit = parseFloat(parts[1] || '1');
                if (!isFinite(loss) || loss <= 0) loss = 1;
                if (!isFinite(profit) || profit <= 0) profit = 1;
                return { loss: loss, profit: profit };
            }

            function formatPrice(v) {
                if (!isFinite(v)) return '';
                // Shorter placeholder: show 2 decimals
                return Number(v).toFixed(2).replace(/\.0+$/,'');
            }

            function computeAvgEntry() {
                var e1 = parseFloat(entry1Input.value);
                var e2 = parseFloat(entry2Input.value);
                if (!isFinite(e1)) return null;
                if (!isFinite(e2)) e2 = e1;
                return (e1 + e2) / 2.0;
            }

            function updateTpPlaceholder() {
                if (!isStrictMode) { tpInput.placeholder = ''; return; }
                var avgEntry = computeAvgEntry();
                var slVal = parseFloat(slInput.value);
                if (!isFinite(avgEntry) || !isFinite(slVal)) { tpInput.placeholder = ''; return; }
                var rr = parseRr(minRrStr);
                var minProfitOverLoss = rr.profit / rr.loss;
                var slDistance = Math.abs(avgEntry - slVal);
                if (slDistance <= 0) { tpInput.placeholder = ''; return; }
                var side = (slVal > avgEntry) ? 'Sell' : 'Buy';
                var minTpDistance = minProfitOverLoss * slDistance;
                var minTpPrice = side === 'Buy' ? (avgEntry + minTpDistance) : (avgEntry - minTpDistance);
                tpInput.placeholder = 'حداقل مقدار ' + formatPrice(minTpPrice);
            }

            slInput.addEventListener('input', updateTpPlaceholder);
            entry1Input.addEventListener('input', updateTpPlaceholder);
            entry2Input.addEventListener('input', updateTpPlaceholder);
            updateTpPlaceholder();
        })();
    });
</script>
@endpush
