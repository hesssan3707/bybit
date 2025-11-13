@php
    $formAction = $action ?? url()->current();
    $formMethod = strtoupper($method ?? 'GET');
    $fromVal = old('from', $from ?? request('from'));
    $toVal = old('to', $to ?? request('to'));
    $symbolVal = old('symbol', $symbol ?? request('symbol'));
    $symbols = $symbols ?? [];
    $hideDate = isset($hideDate) ? (bool)$hideDate : false;
    $hideSymbol = isset($hideSymbol) ? (bool)$hideSymbol : false;
    $resetUrl = $resetUrl ?? $formAction;
    $extraHtml = $extraHtml ?? '';
@endphp

@push('styles')
<style>
    .filter-bar {
        margin: 12px 0 18px 0;
        background: rgba(255,255,255,0.06);
        border: 0 solid rgba(255,255,255,0.12);
        border-radius: 12px;
        padding: 12px;
    }
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 10px;
        align-items: center;
    }
    .filter-field { grid-column: span 3; }
    .filter-actions { grid-column: span 3; display:flex; gap:8px; }
    .filter-label { font-size: 12px; color: #e6e6e6; margin-bottom: 4px; display:block; }
    .filter-input, .filter-select {
        width: 100%;
        background: rgba(255,255,255,0.10);
        color: #fff;
        border: 0 solid rgba(255,255,255,0.22);
        border-radius: 8px;
        padding: 8px 10px;
        height: 38px;
        box-sizing: border-box;
    }
    .filter-input::placeholder { color: rgba(255,255,255,0.65); }
    .filter-button {
        background: rgba(255,255,255,0.10);
        color: #fff;
        border: 0 solid rgba(255,255,255,0.22);
        border-radius: 8px;
        padding: 8px 14px;
        font-weight: 600;
        transition: background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .filter-button:hover { background: rgba(255,255,255,0.14); box-shadow: 0 6px 16px rgba(255,255,255,0.08); }
    .reset-button {
        background: rgba(255,255,255,0.10);
        color: #fff;
        border: 0 solid rgba(255,255,255,0.22);
        border-radius: 8px;
        padding: 8px 14px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .reset-button:hover { background: rgba(255,255,255,0.14); box-shadow: 0 6px 16px rgba(255,255,255,0.08); }
    @media (max-width: 992px) {
        .filter-field { grid-column: span 6; }
        .filter-actions { grid-column: span 12; }
    }
    @media (max-width: 576px) {
        .filter-grid { grid-template-columns: repeat(6, 1fr); }
        .filter-field, .filter-actions { grid-column: span 6; }
    }
    /* Ensure select options readable */
    .filter-select option { color: #111; }
    .filter-hint { font-size: 11px; color: #cbd5e0; margin-top: 2px; display:block; }
    .filters-extras { grid-column: span 6; }
    @media (max-width: 992px) { .filters-extras { grid-column: span 12; } }
    @media (max-width: 576px) { .filters-extras { grid-column: span 6; } }
</style>
@endpush

<form method="{{ $formMethod }}" action="{{ $formAction }}" class="filter-bar">
    <div class="filter-grid">
        @unless($hideDate)
            <div class="filter-field">
                <input type="date" name="from" class="filter-input" value="{{ $fromVal }}" />
            </div>
            <div class="filter-field">
                <input type="date" name="to" class="filter-input" value="{{ $toVal }}" />
            </div>
        @endunless

        @unless($hideSymbol)
            <div class="filter-field">
                <select name="symbol" class="filter-select">
                    <option value="">همه نمادها</option>
                    @foreach($symbols as $sym)
                        <option value="{{ $sym }}" {{ (string)$symbolVal === (string)$sym ? 'selected' : '' }}>{{ $sym }}</option>
                    @endforeach
                </select>
            </div>
        @endunless

        @if(!empty($extraHtml))
            <div class="filters-extras">{!! $extraHtml !!}</div>
        @endif

        <div class="filter-actions">
            <button type="submit" class="filter-button">فیلتر</button>
            <a href="{{ $resetUrl }}" class="reset-button">ریست</a>
        </div>
    </div>
    <input type="hidden" name="_filter" value="1" />
</form>