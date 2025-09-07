@extends('layouts.app')

@section('body-class', 'spot-page')

@section('title', 'Create Spot Order')

@push('styles')
<style>
    .container {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        max-width: 600px;
        margin: 0 auto;
        animation: fadeIn 0.5s ease-out;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px);} to { opacity: 1; transform: translateY(0);} }
    h2 {
        text-align: center;
        margin-bottom: 25px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #ffffff;
    }
    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s, box-shadow 0.3s;
        box-sizing: border-box;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
    select.form-control {
        cursor: pointer;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    .submit-btn {
        background: var(--primary-color);
        color: white;
        padding: 15px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        width: 100%;
        transition: background-color 0.3s, transform 0.2s;
        margin-top: 10px;
    }
    .submit-btn:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }
    .submit-btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
        transform: none;
    }
    .back-btn {
        background: #6c757d;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 20px;
        transition: background-color 0.3s;
    }
    .back-btn:hover {
        background: #545b62;
        color: white;
        text-decoration: none;
    }
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .alert-danger {
        background: #f8d7da;
        color: #842029;
        border: 1px solid #f5c6cb;
    }
    .alert-success {
        background: #d1e7dd;
        color: #0f5132;
        border: 1px solid #badbcc;
    }
    .help-text {
        font-size: 14px;
        color: #6c757d;
        margin-top: 5px;
    }
    .order-type-info {
        background: #e7f3ff;
        border: 1px solid #b8d4fd;
        border-radius: 6px;
        padding: 10px;
        margin-top: 10px;
        font-size: 14px;
        color: #004085;
    }
    .price-field {
        transition: opacity 0.3s, height 0.3s;
    }
    .price-field.hidden {
        opacity: 0.5;
        pointer-events: none;
    }
    .side-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 5px;
    }
    .side-btn {
        padding: 10px;
        border: 2px solid #dee2e6;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s;
    }
    .side-btn.buy {
        border-color: #28a745;
        color: #28a745;
    }
    .side-btn.buy.active {
        background: #28a745;
        color: white;
    }
    .side-btn.sell {
        border-color: #dc3545;
        color: #dc3545;
    }
    .side-btn.sell.active {
        background: #dc3545;
        color: white;
    }

    /* Searchable dropdown styles */
    .search-dropdown {
        position: relative;
        width: 100%;
    }

    .search-input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        font-size: 16px;
        background: white;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }

    .dropdown-arrow {
        transition: transform 0.3s;
        color: #6c757d;
    }

    .dropdown-arrow.rotated {
        transform: rotate(180deg);
    }

    .dropdown-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 8px 8px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .dropdown-list.active {
        display: block;
    }

    .dropdown-item {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
    }

    .dropdown-item.selected {
        background-color: var(--primary-color);
        color: white;
    }

    .dropdown-item.favorite {
        background-color: #fff3cd;
        border-left: 4px solid #ffc107;
    }

    .dropdown-item.favorite:hover {
        background-color: #ffeaa7;
    }

    .favorite-star {
        color: #ffc107;
        font-size: 14px;
    }

    .pair-info {
        display: flex;
        flex-direction: column;
    }

    .pair-symbol {
        font-weight: bold;
        font-size: 14px;
    }

    .pair-details {
        font-size: 12px;
        color: #6c757d;
    }

    .no-results {
        padding: 15px;
        text-align: center;
        color: #6c757d;
        font-style: italic;
    }

    @media screen and (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        .container {
            max-width: calc(100% - 20px);
        }
    }
</style>
@endpush

@section('content')
<div class="glass-card container">
    <a href="{{ route('spot.orders.view') }}" class="back-btn">← بازگشت به سفارش‌ها</a>

    <h2>سفارش اسپات جدید</h2>

    @include('partials.exchange-access-check')

    @if(isset($error))
        <div class="alert alert-danger">
            {{ $error }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul style="margin: 0; padding-right: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($hasActiveExchange)
        <form action="{{ route('spot.order.store.web') }}" method="POST" id="spotOrderForm">
            @csrf

        <div class="form-group">
            <label for="symbol">جفت ارز</label>
            <div class="search-dropdown">
                <div class="search-input form-control" id="symbolSearchInput" onclick="toggleDropdown()">
                    <span id="selectedSymbolText">انتخاب جفت ارز</span>
                    <span class="dropdown-arrow" id="dropdownArrow">▼</span>
                </div>
                <div class="dropdown-list" id="symbolDropdown">
                    <input type="text" id="searchBox" placeholder="جستجو در جفت‌های ارز..."
                           style="width: 100%; padding: 8px; border: none; border-bottom: 1px solid rgba(238, 238, 238, 0.25); outline: none;"
                           oninput="filterSymbols()" onkeydown="handleKeyNavigation(event)">

                    @if($hasActiveExchange)
                        @if(!empty($favoriteMarkets))
                            <div class="dropdown-section-header" style="padding: 8px 15px; background: #f8f9fa; font-weight: bold; color: #495057; font-size: 12px;">
                                جفت‌های محبوب ⭐
                            </div>
                            @foreach($favoriteMarkets as $pair)
                                <div class="dropdown-item favorite" data-symbol="{{ $pair['symbol'] }}" data-base="{{ $pair['baseCoin'] }}" data-quote="{{ $pair['quoteCoin'] }}" onclick="selectSymbol('{{ $pair['symbol'] }}', '{{ $pair['baseCoin'] }}', '{{ $pair['quoteCoin'] }}')">
                                    <div class="pair-info">
                                        <div class="pair-symbol">{{ $pair['symbol'] }}</div>
                                        <div class="pair-details">{{ $pair['baseCoin'] }}/{{ $pair['quoteCoin'] }}</div>
                                    </div>
                                    <div class="favorite-star">⭐</div>
                                </div>
                            @endforeach

                            @if(!empty($tradingPairs))
                                <div class="dropdown-section-header" style="padding: 8px 15px; background: #f8f9fa; font-weight: bold; color: #495057; font-size: 12px;">
                                    سایر جفت‌های ارز
                                </div>
                            @endif
                        @endif

                        @if(!empty($tradingPairs))
                            @foreach($tradingPairs as $pair)
                                @if(!in_array($pair['symbol'], collect($favoriteMarkets)->pluck('symbol')->toArray()))
                                    <div class="dropdown-item" data-symbol="{{ $pair['symbol'] }}" data-base="{{ $pair['baseCoin'] }}" data-quote="{{ $pair['quoteCoin'] }}" onclick="selectSymbol('{{ $pair['symbol'] }}', '{{ $pair['baseCoin'] }}', '{{ $pair['quoteCoin'] }}')">
                                        <div class="pair-info">
                                            <div class="pair-symbol">{{ $pair['symbol'] }}</div>
                                            <div class="pair-details">{{ $pair['baseCoin'] }}/{{ $pair['quoteCoin'] }}</div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        @if(empty($tradingPairs) && empty($favoriteMarkets))
                            <div class="no-results">هیچ جفت ارزی یافت نشد</div>
                        @endif
                    @else
                        <div class="no-results">ابتدا صرافی خود را فعال کنید</div>
                    @endif
                </div>
            </div>
            <input type="hidden" name="symbol" id="symbol" value="{{ old('symbol') }}" required>
        </div>

        <div class="form-group">
            <label>جهت معامله</label>
            <div class="side-buttons">
                <button type="button" class="side-btn buy {{ old('side') == 'Buy' ? 'active' : '' }}" onclick="setSide('Buy')">
                    خرید (Buy)
                </button>
                <button type="button" class="side-btn sell {{ old('side') == 'Sell' ? 'active' : '' }}" onclick="setSide('Sell')">
                    فروش (Sell)
                </button>
            </div>
            <input type="hidden" name="side" id="side" value="{{ old('side', 'Buy') }}" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="orderType">نوع سفارش</label>
                <select name="orderType" id="orderType" class="form-control" required onchange="togglePriceField()">
                    <option value="Market" {{ old('orderType') == 'Market' ? 'selected' : '' }}>بازار (Market)</option>
                    <option value="Limit" {{ old('orderType') == 'Limit' ? 'selected' : '' }}>محدود (Limit)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="timeInForce">مدت اعتبار</label>
                <select name="timeInForce" id="timeInForce" class="form-control">
                    <option value="GTC" {{ old('timeInForce') == 'GTC' ? 'selected' : '' }}>GTC (تا لغو)</option>
                    <option value="IOC" {{ old('timeInForce') == 'IOC' ? 'selected' : '' }}>IOC (فوری یا لغو)</option>
                    <option value="FOK" {{ old('timeInForce') == 'FOK' ? 'selected' : '' }}>FOK (کامل یا لغو)</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="qty">مقدار</label>
                <input type="number" name="qty" id="qty" class="form-control"
                       step="0.00000001" min="0.00000001"
                       value="{{ old('qty') }}" required>
                <div class="help-text">مقدار ارز پایه که می‌خواهید معامله کنید</div>
            </div>

            <div class="form-group price-field" id="priceField">
                <label for="price">قیمت</label>
                <input type="number" name="price" id="price" class="form-control"
                       step="0.0001" min="0.0001"
                       value="{{ old('price') }}">
                <div class="help-text">قیمت برای سفارش محدود</div>
            </div>
        </div>

        <div class="order-type-info" id="orderTypeInfo">
            <strong>سفارش بازار:</strong> سفارش شما فوراً به بهترین قیمت موجود در بازار اجرا می‌شود.
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">
            ایجاد سفارش اسپات
        </button>
    </form>
    @else
        <!-- Form is disabled when no active exchange -->
        <div style="opacity: 0.5; pointer-events: none;">
            <div class="form-group">
                <label for="symbol">جفت ارز</label>
                <select disabled class="form-control">
                    <option>ابتدا صرافی خود را فعال کنید</option>
                </select>
            </div>
            <button type="button" class="submit-btn" disabled>
                برای ارسال سفارش ابتدا صرافی فعال کنید
            </button>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
let allSymbols = [];
let currentSelectedIndex = -1;
let filteredSymbols = [];

// Initialize symbols data from PHP
@if($hasActiveExchange)
    allSymbols = [
        @if(!empty($favoriteMarkets))
            @foreach($favoriteMarkets as $pair)
                {
                    symbol: '{{ $pair['symbol'] }}',
                    baseCoin: '{{ $pair['baseCoin'] }}',
                    quoteCoin: '{{ $pair['quoteCoin'] }}',
                    isFavorite: true
                },
            @endforeach
        @endif
        @if(!empty($tradingPairs))
            @foreach($tradingPairs as $pair)
                @if(!in_array($pair['symbol'], collect($favoriteMarkets)->pluck('symbol')->toArray()))
                    {
                        symbol: '{{ $pair['symbol'] }}',
                        baseCoin: '{{ $pair['baseCoin'] }}',
                        quoteCoin: '{{ $pair['quoteCoin'] }}',
                        isFavorite: false
                    },
                @endif
            @endforeach
        @endif
    ];
@endif

function toggleDropdown() {
    const dropdown = document.getElementById('symbolDropdown');
    const arrow = document.getElementById('dropdownArrow');
    const searchBox = document.getElementById('searchBox');

    if (dropdown.classList.contains('active')) {
        dropdown.classList.remove('active');
        arrow.classList.remove('rotated');
    } else {
        dropdown.classList.add('active');
        arrow.classList.add('rotated');
        searchBox.focus();
        searchBox.value = '';
        filterSymbols();
    }
}

function selectSymbol(symbol, baseCoin, quoteCoin) {
    document.getElementById('symbol').value = symbol;
    document.getElementById('selectedSymbolText').textContent = `${symbol} (${baseCoin}/${quoteCoin})`;

    const dropdown = document.getElementById('symbolDropdown');
    const arrow = document.getElementById('dropdownArrow');
    dropdown.classList.remove('active');
    arrow.classList.remove('rotated');

    // Remove previous selections
    document.querySelectorAll('.dropdown-item').forEach(item => {
        item.classList.remove('selected');
    });

    // Mark current selection
    const selectedItem = document.querySelector(`[data-symbol="${symbol}"]`);
    if (selectedItem) {
        selectedItem.classList.add('selected');
    }
}

function filterSymbols() {
    const searchTerm = document.getElementById('searchBox').value.toLowerCase();
    const items = document.querySelectorAll('.dropdown-item');
    const sectionHeaders = document.querySelectorAll('.dropdown-section-header');

    filteredSymbols = [];
    let favoritesVisible = false;
    let othersVisible = false;

    items.forEach((item, index) => {
        const symbol = item.getAttribute('data-symbol');
        const baseCoin = item.getAttribute('data-base');
        const quoteCoin = item.getAttribute('data-quote');
        const isFavorite = item.classList.contains('favorite');

        if (symbol && (
            symbol.toLowerCase().includes(searchTerm) ||
            baseCoin.toLowerCase().includes(searchTerm) ||
            quoteCoin.toLowerCase().includes(searchTerm)
        )) {
            item.style.display = 'flex';
            filteredSymbols.push(item);

            if (isFavorite) {
                favoritesVisible = true;
            } else {
                othersVisible = true;
            }
        } else {
            item.style.display = 'none';
        }
    });

    // Show/hide section headers based on content
    sectionHeaders.forEach(header => {
        if (header.textContent.includes('محبوب')) {
            header.style.display = favoritesVisible ? 'block' : 'none';
        } else if (header.textContent.includes('سایر')) {
            header.style.display = othersVisible ? 'block' : 'none';
        }
    });

    // Reset selection index
    currentSelectedIndex = -1;

    // Show no results message if needed
    const noResults = document.querySelector('.no-results');
    if (noResults) {
        noResults.style.display = filteredSymbols.length === 0 ? 'block' : 'none';
    }
}

function handleKeyNavigation(event) {
    if (filteredSymbols.length === 0) return;

    switch(event.key) {
        case 'ArrowDown':
            event.preventDefault();
            currentSelectedIndex = Math.min(currentSelectedIndex + 1, filteredSymbols.length - 1);
            updateHighlight();
            break;
        case 'ArrowUp':
            event.preventDefault();
            currentSelectedIndex = Math.max(currentSelectedIndex - 1, -1);
            updateHighlight();
            break;
        case 'Enter':
            event.preventDefault();
            if (currentSelectedIndex >= 0 && filteredSymbols[currentSelectedIndex]) {
                const item = filteredSymbols[currentSelectedIndex];
                const symbol = item.getAttribute('data-symbol');
                const baseCoin = item.getAttribute('data-base');
                const quoteCoin = item.getAttribute('data-quote');
                selectSymbol(symbol, baseCoin, quoteCoin);
            }
            break;
        case 'Escape':
            toggleDropdown();
            break;
    }
}

function updateHighlight() {
    filteredSymbols.forEach((item, index) => {
        if (index === currentSelectedIndex) {
            item.style.backgroundColor = 'var(--primary-color)';
            item.style.color = 'white';
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.style.backgroundColor = '';
            item.style.color = '';
        }
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const searchDropdown = document.querySelector('.search-dropdown');
    if (searchDropdown && !searchDropdown.contains(event.target)) {
        const dropdown = document.getElementById('symbolDropdown');
        const arrow = document.getElementById('dropdownArrow');
        dropdown.classList.remove('active');
        arrow.classList.remove('rotated');
    }
});

function setSide(side) {
    document.getElementById('side').value = side;

    // Update button states
    document.querySelectorAll('.side-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    if (side === 'Buy') {
        document.querySelector('.side-btn.buy').classList.add('active');
    } else {
        document.querySelector('.side-btn.sell').classList.add('active');
    }

    updateSubmitButton();
}

function togglePriceField() {
    const orderType = document.getElementById('orderType').value;
    const priceField = document.getElementById('priceField');
    const priceInput = document.getElementById('price');
    const orderTypeInfo = document.getElementById('orderTypeInfo');

    if (orderType === 'Market') {
        priceField.classList.add('hidden');
        priceInput.removeAttribute('required');
        orderTypeInfo.innerHTML = '<strong>سفارش بازار:</strong> سفارش شما فوراً به بهترین قیمت موجود در بازار اجرا می‌شود.';
    } else {
        priceField.classList.remove('hidden');
        priceInput.setAttribute('required', 'required');
        orderTypeInfo.innerHTML = '<strong>سفارش محدود:</strong> سفارش شما فقط در صورت رسیدن قیمت به مقدار تعیین شده اجرا می‌شود.';
    }

    updateSubmitButton();
}

function updateSubmitButton() {
    const side = document.getElementById('side').value;
    const orderType = document.getElementById('orderType').value;
    const submitBtn = document.getElementById('submitBtn');

    const sideText = side === 'Buy' ? 'خرید' : 'فروش';
    const typeText = orderType === 'Market' ? 'بازار' : 'محدود';

    submitBtn.textContent = `ایجاد سفارش ${sideText} ${typeText}`;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePriceField();
    updateSubmitButton();

    // Set initial side if not set
    if (!document.getElementById('side').value) {
        setSide('Buy');
    }

    // Set initial symbol if provided in old input
    const oldSymbol = '{{ old('symbol') }}';
    if (oldSymbol) {
        const symbolData = allSymbols.find(s => s.symbol === oldSymbol);
        if (symbolData) {
            selectSymbol(symbolData.symbol, symbolData.baseCoin, symbolData.quoteCoin);
        }
    }
});

// Form validation
document.getElementById('spotOrderForm').addEventListener('submit', function(e) {
    const orderType = document.getElementById('orderType').value;
    const price = document.getElementById('price').value;
    const symbol = document.getElementById('symbol').value;

    if (!symbol) {
        e.preventDefault();
        alert('لطفاً جفت ارز را انتخاب کنید');
        return false;
    }

    if (orderType === 'Limit' && (!price || parseFloat(price) <= 0)) {
        e.preventDefault();
        alert('برای سفارش محدود، قیمت الزامی است');
        return false;
    }

    // Disable submit button to prevent double submission
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'در حال ایجاد...';
});
</script>
@endpush
