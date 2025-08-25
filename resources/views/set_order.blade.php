@extends('layouts.app')

@section('title', 'New Order')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 500px;
        margin: auto;
    }
    h2 {
        text-align: center;
        margin-bottom: 20px;
    }
    #order-form {
        background: #ffffff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .form-group {
        margin-bottom: 15px;
    }
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #555;
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
    }
    .alert-success { background-color: #d1e7dd; color: #0f5132; }
    .alert-danger { background-color: #f8d7da; color: #842029; }
    .invalid-feedback { color: #842029; font-size: 14px; margin-top: 5px; display: block; }
</style>
@endpush

@section('content')
<div class="container">
    <h2>ثبت سفارش جدید</h2>
    
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

        <div class="form-group">
            <label for="expire">مدت انقضای سفارش (دقیقه):</label>
            <input id="expire" type="number" name="expire" min="1" value="{{ old('expire', 10) }}" required>
            @error('expire') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="risk_percentage">درصد ریسک (حداکثر ۱۰٪):</label>
            <input id="risk_percentage" type="number" name="risk_percentage" min="0.1" max="10" step="0.1" value="{{ old('risk_percentage', 10) }}" required>
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

        // --- Existing market price logic ---
        const marketPrice = '{{ $marketPrice ?? '' }}';
        if (marketPrice && marketPrice !== '0') {
            // Only set the value if the fields are empty (e.g., on first load, not after a validation error)
            if (entry1Input.value === '') {
                entry1Input.value = marketPrice;
                if (isChained) {
                    entry2Input.value = marketPrice;
                }
            }
        }
    });
</script>
@endpush
