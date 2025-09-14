@extends('layouts.app')

@section('title', 'MACD Strategy')

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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            font-weight: bold;
        }
        select {
            width: 100%;
            max-width: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn {
            display: inline-block;
            padding: 12px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: opacity 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
    </style>
@endpush

@section('content')
    <div class="glass-card container">
        <div class="strategy-card">
            <h2>MACD Strategy Comparison</h2>

            <form method="GET" action="{{ route('futures.macd_strategy') }}" class="form-group">
                <label for="market">Select a market to compare:</label>
                <select name="market" id="market" onchange="this.form.submit()">
                    @foreach($markets as $marketOption)
                        <option value="{{ $marketOption }}" {{ $selectedMarket == $marketOption ? 'selected' : '' }}>
                            {{ $marketOption }}
                        </option>
                    @endforeach
                </select>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Timeframe</th>
                            <th>Market</th>
                            <th>Normalized MACD</th>
                            <th>Normalized Signal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timeframes as $timeframe)
                            @foreach(['BTCUSDT', 'ETHUSDT', $selectedMarket] as $market)
                                @if(isset($macdData[$timeframe][$market]))
                                    <tr>
                                        <td>{{ $timeframe }}</td>
                                        <td>{{ $market }}</td>
                                        <td>{{ number_format($macdData[$timeframe][$market]['normalized_macd'], 4) }}</td>
                                        <td>{{ number_format($macdData[$timeframe][$market]['normalized_signal'], 4) }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td>{{ $timeframe }}</td>
                                        <td>{{ $market }}</td>
                                        <td colspan="2" style="color: red;">Not enough data</td>
                                    </tr>
                                @endif
                            @endforeach
                            <tr style="background-color: #e0e0e0;">
                                <td colspan="4" style="height: 2px;"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    </div>
@endsection
