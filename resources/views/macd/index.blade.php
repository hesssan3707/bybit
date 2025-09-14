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
            width: 90px;
            height: 34px;
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
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #2196F3;
        }
        input:checked + .slider:before {
            transform: translateX(55px);
        }
        .slider.round {
            border-radius: 34px;
        }
        .slider.round:before {
            border-radius: 50%;
        }
        .switch-labels {
            position: absolute;
            top: 50%;
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
                            <th>هیستوگرام {{ $selectedAltcoin }}</th>
                            <th>مکدی نرمال شده {{ $baseMarket }}</th>
                            <th>هیستوگرام {{ $baseMarket }}</th>
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
                                        {{ number_format($comparisonData[$timeframe]['altcoin']['histogram_value'], 4) }}
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
                                        {{ number_format($comparisonData[$timeframe]['base']['histogram_value'], 4) }}
                                    @else
                                        <span style="color: red;">داده کافی نیست</span>
                                    @endif
                                </td>
                                <td style="font-size: 1.5em;" class="{{ $comparisonData[$timeframe]['trend'] === 'up' ? 'trend-up' : ($comparisonData[$timeframe]['trend'] === 'down' ? 'trend-down' : '') }}">
                                    @if($comparisonData[$timeframe]['trend'] === 'up')
                                        🔼
                                    @elseif($comparisonData[$timeframe]['trend'] === 'down')
                                        🔽
                                    @else
                                        ⚪
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
