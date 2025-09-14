@extends('layouts.app')

@section('title', 'استراتژی MACD')

@push('styles')
    <style>
        .container {
            width: 100%;
            max-width: 1200px;
            margin: auto;
        }
        .strategy-card {
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .strategy-card h2 {
            margin-bottom: 30px;
            text-align: center;
            color: var(--primary-color);
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        thead th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .form-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 0;
			display : block ruby;
        }
        label {
            font-weight: bold;
            margin-left: 10px;
        }
        select {
            width: 100%;
            max-width: 400px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 98px;
            height: 46px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 40px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 40px;
            width: 40px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #2196F3;
        }
        input:checked + .slider:before {
            transform: translateX(52px);
        }
        .slider.round {
            border-radius: 36px;
        }
        .slider.round:before {
            border-radius: 50%;
        }
        .switch-labels {
            position: absolute;
            top: 49%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            padding: 0 10px;
            color: white;
            font-weight: bold;
            pointer-events: none;
        }
        .trend-up {
            color: green;
        }
        .trend-down {
            color: red;
        }
        .strategy-note {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
    </style>
@endpush

@section('content')
    <div class="glass-card container">
        <div class="strategy-card">
            <h2>مقایسه استراتژی MACD</h2>

            <form method="GET" action="{{ route('futures.macd_strategy') }}" id="strategy-form">
                <div class="form-container">
                    <div class="form-group">
                        <label for="altcoin">آلتکوین:</label>
                        <select name="altcoin" id="altcoin" onchange="this.form.submit()">
                            @foreach($altcoins as $altcoin)
                                <option value="{{ $altcoin }}" {{ $selectedAltcoin == $altcoin ? 'selected' : '' }}>
                                    {{ $altcoin }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>بازار پایه:</label>
                        <label class="switch">
                            <input type="checkbox" name="base_market_switch" id="base_market_switch" {{ $baseMarket == 'ETHUSDT' ? 'checked' : '' }}>
                            <span class="slider round"></span>
                            <div class="switch-labels">
                                <span>BTC</span>
                                <span>ETH</span>
                            </div>
                        </label>
                        <input type="hidden" name="base_market" id="base_market" value="{{ $baseMarket }}">
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>زمان</th>
                            <th>مکدی نرمال شده {{ $selectedAltcoin }}</th>
                            <th>هیستوگرام نرمال شده {{ $selectedAltcoin }}</th>
                            <th>مکدی نرمال شده {{ $baseMarket }}</th>
                            <th>هیستوگرام نرمال شده {{ $baseMarket }}</th>
                            <th>تفاوت هیستوگرام</th>
                            <th>روند</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timeframes as $timeframe)
                            <tr>
                                <td>{{ $timeframe }}</td>
                                <td>
                                    @if(isset($comparisonData[$timeframe]['altcoin']))
                                        {{ number_format($comparisonData[$timeframe]['altcoin']['normalized_macd'], 4) }}
                                    @else
                                        <span style="color: red;">داده کافی نیست</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($comparisonData[$timeframe]['altcoin']))
                                        {{ number_format($comparisonData[$timeframe]['altcoin']['normalized_histogram'], 4) }}
                                    @else
                                        <span style="color: red;">داده کافی نیست</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($comparisonData[$timeframe]['base']))
                                        {{ number_format($comparisonData[$timeframe]['base']['normalized_macd'], 4) }}
                                    @else
                                        <span style="color: red;">داده کافی نیست</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($comparisonData[$timeframe]['base']))
                                        {{ number_format($comparisonData[$timeframe]['base']['normalized_histogram'], 4) }}
                                    @else
                                        <span style="color: red;">داده کافی نیست</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($comparisonData[$timeframe]['histogram_diff']))
                                        {{ number_format($comparisonData[$timeframe]['histogram_diff'], 4) }}
                                    @else
                                        <span style="color: red;">داده کافی نیست</span>
                                    @endif
                                </td>
                                <td style="font-size: 1.5em;" class="{{ $comparisonData[$timeframe]['trend'] === 'up' || $comparisonData[$timeframe]['trend'] === 'strong_up' ? 'trend-up' : ($comparisonData[$timeframe]['trend'] === 'down' || $comparisonData[$timeframe]['trend'] === 'strong_down' ? 'trend-down' : '') }}">
                                    @if($comparisonData[$timeframe]['trend'] === 'strong_up')
                                        ▲▲
                                    @elseif($comparisonData[$timeframe]['trend'] === 'up')
                                        ▲
                                    @elseif($comparisonData[$timeframe]['trend'] === 'strong_down')
                                        ▼▼
                                    @elseif($comparisonData[$timeframe]['trend'] === 'down')
                                        ▼
                                    @else
                                        ●
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="strategy-note">
                <h4>توضیح استراتژی</h4>
                <p>
                    این استراتژی، قدرت نسبی یک آلتکوین را در برابر یک بازار پایه (BTC یا ETH) با استفاده از نشانگر MACD نرمال شده، مقایسه می‌کند.
                </p>
                <ul>
                    <li><strong>مکدی نرمال شده:</strong> مقدار MACD بر قیمت بسته شدن تقسیم شده و در 100 ضرب می‌شود. این کار مقایسه بین دارایی‌هایی با قیمت‌های مختلف را ممکن می‌سازد.</li>
                    <li><strong>هیستوگرام نرمال شده:</strong> مقدار هیستوگرام MACD (که خود تفاوت بین خط MACD و خط سیگنال است) نیز به همین روش نرمال سازی می‌شود تا قدرت و شتاب حرکت روند را نشان دهد.</li>
                    <li><strong>روند:</strong>
                        <ul>
                            <li><strong>جهت روند:</strong> با مقایسه مکدی نرمال شده آلتکوین و بازار پایه تعیین می‌شود. اگر مکدی آلتکوین بالاتر باشد، جهت روند صعودی و در غیر این صورت نزولی است.</li>
                            <li><strong>قدرت روند:</strong> با مقایسه هیستوگرام نرمال شده آلتکوین و بازار پایه تعیین می‌شود. تفاوت قابل توجه نشان دهنده یک روند قوی است.</li>
                        </ul>
                    </li>
                    <li><strong>نمادها:</strong>
                        <ul>
                            <li>▲▲: روند صعودی قوی</li>
                            <li>▲: روند صعودی</li>
                            <li>▼▼: روند نزولی قوی</li>
                            <li>▼: روند نزولی</li>
                            <li>●: خنثی</li>
                        </ul>
                    </li>
                </ul>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('strategy-form');
        const altcoinSelect = document.getElementById('altcoin');
        const baseMarketSwitch = document.getElementById('base_market_switch');
        const baseMarketInput = document.getElementById('base_market');

        altcoinSelect.addEventListener('change', () => form.submit());

        baseMarketSwitch.addEventListener('change', () => {
            if (baseMarketSwitch.checked) {
                baseMarketInput.value = 'ETHUSDT';
            } else {
                baseMarketInput.value = 'BTCUSDT';
            }
            form.submit();
        });
    });
</script>
@endpush
