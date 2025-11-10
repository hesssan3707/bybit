@extends('layouts.app')

@section('title', 'Edit Order')

@push('styles')
<style>
    .page-container { display:flex; flex-wrap:wrap; gap:20px; max-width:1200px; margin:auto; }
    .form-container { flex:1; min-width:300px; max-width:800px; margin:auto; }
    .container { width:100%; padding:20px; box-sizing:border-box; }
    h2 { text-align:center; margin-bottom:20px; }
    .form-group { margin-bottom:15px; }
    label { display:block; font-weight:400; color:#ffffff; height:32px; }
    input, select { width:100%; padding:12px; border:1px solid #ccc; border-radius:8px; font-size:14px; box-sizing:border-box; transition:border-color .3s, box-shadow .3s; }
    input:focus, select:focus { border-color:var(--primary-color); box-shadow:0 0 8px rgba(0,123,255,.25); outline:none; }
    input[type=number]{ direction:ltr; text-align:left; }
    .submit-form-button { width:100%; padding:14px; background:linear-gradient(90deg, var(--primary-color), var(--primary-hover)); color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; margin-top:20px; cursor:pointer; transition:opacity .3s; }
    .submit-form-button:hover { opacity:.9; }
    .alert { padding:15px; margin-bottom:20px; border-radius:8px; text-align:center; font-size:16px; }
    .alert-success { background:#d1e7dd; color:#0f5132; }
    .alert-danger { background:#f8d7da; color:#721c24; }
    @media (max-width:768px){ .page-container{flex-direction:column; align-items:center;} .container{padding:10px;} }
</style>
@endpush

@section('content')
<div class="page-container">
  <div class="form-container">
    <div class="glass-card container">
      <h2>ویرایش سفارش</h2>

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

      <form action="{{ route('futures.order.store') }}" method="POST">
        @csrf
        <!-- Hidden original order id -->
        <input type="hidden" name="order_id" value="{{ $order->id }}">

        @if(isset($user) && $user->future_strict_mode && $selectedMarket)
          <div class="form-group">
            <label>بازار (حالت سخت‌گیرانه)</label>
            <input type="hidden" name="symbol" value="{{ $selectedMarket }}">
            <input type="text" value="{{ $selectedMarket }}" disabled>
          </div>
        @else
          <div class="form-group">
            <label for="symbol">انتخاب بازار</label>
            <select id="symbol" name="symbol" required>
              @foreach($availableMarkets as $m)
                <option value="{{ $m }}" {{ ($prefill['symbol'] ?? $currentSymbol) === $m ? 'selected' : '' }}>{{ $m }}</option>
              @endforeach
            </select>
          </div>
        @endif

        <div class="form-group">
          <label for="entry1">قیمت ورود 1</label>
          <input id="entry1" type="number" name="entry1" step="any" required value="{{ old('entry1', $prefill['entry1']) }}">
          @error('entry1') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
          <label for="entry2">قیمت ورود 2</label>
          <input id="entry2" type="number" name="entry2" step="any" required value="{{ old('entry2', $prefill['entry2']) }}">
          @error('entry2') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
          <label for="sl">حد ضرر (SL)</label>
          <input id="sl" type="number" name="sl" step="any" required value="{{ old('sl', $prefill['sl']) }}">
          @error('sl') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
          <label for="tp">حد سود (TP)</label>
          <input id="tp" type="number" name="tp" step="any" required value="{{ old('tp', $prefill['tp']) }}">
          @error('tp') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label for="steps">تعداد مراحل</label>
            <input id="steps" type="number" name="steps" min="1" max="8" value="{{ old('steps', $prefill['steps']) }}" required>
            @error('steps') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="form-group" style="flex:1;">
            <label for="risk_percentage">درصد ریسک</label>
            <input id="risk_percentage" type="number" name="risk_percentage" min="0.1" max="{{ isset($user) && $user->future_strict_mode ? '10' : '100' }}" step="0.1" value="{{ old('risk_percentage', $prefill['risk_percentage']) }}" required>
            @error('risk_percentage') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label for="expire">انقضا (دقیقه)</label>
            <input id="expire" type="number" name="expire" min="1" max="999" value="{{ old('expire', $prefill['expire']) }}" placeholder="دقیقه">
            @error('expire') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="form-group" style="flex:1;">
            <label for="cancel_price">قیمت لغو خودکار</label>
            <input id="cancel_price" type="number" name="cancel_price" step="any" value="{{ old('cancel_price', $prefill['cancel_price']) }}">
            @error('cancel_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
        </div>

        <button type="submit" class="submit-form-button">Edit Order</button>
      </form>
    </div>
  </div>
</div>
@endsection