@extends('layouts.app')

@section('title', 'ژورنال معاملاتی')

@push('styles')
<style>
    .container {
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    h2 {
        text-align: center;
        margin-bottom: 25px;
    }
    .filters {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        justify-content: center;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: rgba(255, 255, 255, 0.05);
        padding: 20px;
        border-radius: 10px;
        text-align: center;
    }
    .stat-card h4 {
        margin-bottom: 10px;
        color: #adb5bd;
    }
    .stat-card p {
        font-size: 1.5rem;
        font-weight: bold;
    }
    .pnl-positive { color: #28a745; }
    .pnl-negative { color: #dc3545; }
    .chart-container {
        background: rgba(255, 255, 255, 0.05);
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 30px;
    }
</style>
@endpush

@section('content')
<div class="glass-card container">
    <h2>ژورنال معاملاتی</h2>

    <form method="GET" action="{{ route('futures.journal') }}" class="filters">
        <select name="month" class="form-control">
            <option value="last6months" {{ $month == 'last6months' ? 'selected' : '' }}>6 ماه گذشته</option>
            @foreach($availableMonths as $m)
                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($m . '-01')->format('F Y') }}</option>
            @endforeach
        </select>
        <select name="side" class="form-control">
            <option value="all" {{ $side == 'all' ? 'selected' : '' }}>همه</option>
            <option value="buy" {{ $side == 'buy' ? 'selected' : '' }}>معامله های خرید</option>
            <option value="sell" {{ $side == 'sell' ? 'selected' : '' }}>معامله های فروش</option>
        </select>
        <button type="submit" class="btn btn-primary">فیلتر</button>
    </form>

    <div class="stats-grid">
        <div class="stat-card">
            <h4>کل سود/ضرر</h4>
            <p class="{{ $totalPnl >= 0 ? 'pnl-positive' : 'pnl-negative' }}">${{ number_format($totalPnl, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>کل سود</h4>
            <p class="pnl-positive">${{ number_format($totalProfits, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>کل ضرر</h4>
            <p class="pnl-negative">${{ number_format($totalLosses, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>تعداد معامله</h4>
            <p>{{ $totalTrades }}</p>
        </div>
        <div class="stat-card">
            <h4>بزرگترین سود</h4>
            <p class="pnl-positive">${{ number_format($biggestProfit, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>بزرگترین ضرر</h4>
            <p class="pnl-negative">${{ number_format($biggestLoss, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>متوسط ریسک</h4>
            <p class="pnl-negative">${{ number_format($averageRisk, 2) }}</p>
        </div>
    </div>

    <div class="chart-container">
        <div id="pnlChart"></div>
    </div>
    <div class="chart-container">
        <div id="cumulativePnlChart"></div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Side</th>
                    <th>Quantity</th>
                    <th>Entry Price</th>
                    <th>Exit Price</th>
                    <th>PnL</th>
                    <th>Closed At</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($trades as $trade)
                    <tr>
                        <td>{{ $trade->symbol }}</td>
                        <td>{{ ucfirst($trade->side) }}</td>
                        <td>{{ rtrim(rtrim(number_format($trade->qty, 8), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($trade->avg_entry_price, 2), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($trade->avg_exit_price, 2), '0'), '.') }}</td>
                        <td class="{{ $trade->pnl >= 0 ? 'pnl-positive' : 'pnl-negative' }}">${{ number_format($trade->pnl, 2) }}</td>
                        <td>{{ $trade->closed_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No trades found for the selected period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // PnL Per Trade Chart
        var pnlOptions = {
            chart: {
                type: 'bar',
                height: 350,
                toolbar: { show: false }
            },
            series: [{
                name: 'PnL',
                data: {!! json_encode($chartData) !!}
            }],
            xaxis: {
                type: 'datetime',
                labels: { style: { colors: '#adb5bd' } }
            },
            yaxis: {
                labels: {
                    style: { colors: '#adb5bd' },
                    formatter: (value) => { return '$' + value.toFixed(2); }
                }
            },
            colors: [function({ value }) {
                return value >= 0 ? '#28a745' : '#dc3545'
            }],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '60%',
                }
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: 'rgba(255, 255, 255, 0.1)'
            },
            tooltip: {
                theme: 'dark',
                x: { format: 'dd MMM yyyy HH:mm' },
                y: {
                    formatter: function (val) {
                        return "$" + val.toFixed(2)
                    }
                }
            }
        };

        var pnlChart = new ApexCharts(document.querySelector("#pnlChart"), pnlOptions);
        pnlChart.render();

        // Cumulative PnL Chart
        var cumulativePnlOptions = {
            chart: {
                type: 'area',
                height: 350,
                toolbar: { show: false }
            },
            series: [{
                name: 'Cumulative PnL',
                data: {!! json_encode($cumulativePnl) !!}
            }],
            xaxis: {
                type: 'datetime',
                labels: { style: { colors: '#adb5bd' } }
            },
            yaxis: {
                labels: {
                    style: { colors: '#adb5bd' },
                    formatter: (value) => { return '$' + value.toFixed(2); }
                }
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.3,
                    stops: [0, 90, 100]
                }
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: 'rgba(255, 255, 255, 0.1)'
            },
            tooltip: {
                theme: 'dark',
                x: { format: 'dd MMM yyyy HH:mm' },
                 y: {
                    formatter: function (val) {
                        return "$" + val.toFixed(2)
                    }
                }
            }
        };

        var cumulativePnlChart = new ApexCharts(document.querySelector("#cumulativePnlChart"), cumulativePnlOptions);
        cumulativePnlChart.render();
    });
</script>
@endpush
