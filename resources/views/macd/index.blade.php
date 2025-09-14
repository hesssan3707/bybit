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
            max-width: 250px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .base-market-selector label {
            margin: 0 10px;
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
                        <select name="altcoin" id="altcoin">
                            @foreach($altcoins as $altcoin)
                                <option value="{{ $altcoin }}" {{ $selectedAltcoin == $altcoin ? 'selected' : '' }}>
                                    {{ $altcoin }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="base-market-selector">
                        <label>بازار پایه:</label>
                        <input type="radio" id="btc" name="base_market" value="BTCUSDT" {{ $baseMarket == 'BTCUSDT' ? 'checked' : '' }}>
                        <label for="btc">BTCUSDT</label>
                        <input type="radio" id="eth" name="base_market" value="ETHUSDT" {{ $baseMarket == 'ETHUSDT' ? 'checked' : '' }}>
                        <label for="eth">ETHUSDT</label>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>زمان</th>
                            <th>مکدی نرمال شده {{ $selectedAltcoin }}</th>
                            <th>مکدی نرمال شده {{ $baseMarket }}</th>
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
                                    @if(isset($comparisonData[$timeframe]['base']))
                                        {{ number_format($comparisonData[$timeframe]['base']['normalized_macd'], 4) }}
                                    @else
                                        <span style="color: red;">داده کافی نیست</span>
                                    @endif
                                </td>
                                <td style="font-size: 1.5em;">
                                    {{ $comparisonData[$timeframe]['trend'] }}
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
        const baseMarketRadios = document.querySelectorAll('input[name="base_market"]');

        altcoinSelect.addEventListener('change', () => form.submit());
        baseMarketRadios.forEach(radio => {
            radio.addEventListener('change', () => form.submit());
        });
    });
</script>
@endpush
