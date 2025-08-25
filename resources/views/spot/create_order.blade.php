@extends('layouts.app')

@section('title', 'Create Spot Order')

@push('styles')
<style>
    .container {
        background: #ffffff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        max-width: 600px;
        margin: 0 auto;
    }
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
        color: #333;
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
    
    @media screen and (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        .container {
            margin: 10px;
            max-width: calc(100% - 20px);
        }
    }
</style>
@endpush

@section('content')
<div class="container">
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
            <select name="symbol" id="symbol" class="form-control" required>
                <option value="">انتخاب جفت ارز</option>
                @foreach($tradingPairs as $pair)
                    <option value="{{ $pair['symbol'] }}" {{ old('symbol') == $pair['symbol'] ? 'selected' : '' }}>
                        {{ $pair['symbol'] }} ({{ $pair['baseCoin'] }}/{{ $pair['quoteCoin'] }})
                    </option>
                @endforeach
            </select>
            <div class="help-text">جفت ارز مورد نظر برای معاملات اسپات را انتخاب کنید</div>
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
                <div class="help-text">قیمت برای سفارش محدود (اختیاری برای سفارش بازار)</div>
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
});

// Form validation
document.getElementById('spotOrderForm').addEventListener('submit', function(e) {
    const orderType = document.getElementById('orderType').value;
    const price = document.getElementById('price').value;
    
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