@extends('layouts.app')

@section('title', 'Trading Journal')

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
        align-items: center;
    }
    .filters .form-control, .filters .btn {
        background-color: rgba(255, 255, 255, 0.1);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
    }
    .filters .form-control:focus {
        background-color: rgba(255, 255, 255, 0.2);
        color: #fff;
        border-color: var(--primary-color);
        box-shadow: none;
    }
    .filters .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        transition: background-color 0.3s;
    }
    .filters .btn-primary:hover {
        background-color: var(--primary-hover);
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
        overflow-x: hidden;
    }
    .mobile-redirect-section { display: none; }
    .redirect-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
    .redirect-btn {
        flex: 1; padding: 15px; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
        color: white; text-decoration: none; border-radius: 10px; text-align: center; font-weight: bold;
        transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,123,255,0.3);
    }
    .redirect-btn:hover {
        transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        color: white; text-decoration: none;
    }
    .redirect-btn.secondary {
        background: linear-gradient(135deg, #28a745, #20c997);
        box-shadow: 0 4px 15px rgba(40,167,69,0.3);
    }
    @media (max-width: 768px) {
        .filters {
            flex-direction: column;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .mobile-redirect-section { display: block; }
        .redirect-buttons { flex-direction: column; gap: 15px; }
        .redirect-btn { padding: 18px; font-size: 16px; }
    }
</style>
@endpush

@section('content')
<div class="glass-card container">
    <h2>Trading Journal</h2>

    <!-- Mobile redirect buttons (only visible on mobile) -->
    <div class="mobile-redirect-section">
        <div class="redirect-buttons">
            <a href="{{ route('futures.orders') }}" class="redirect-btn">
                📊 سفارش‌های آتی
            </a>
            <a href="{{ route('futures.pnl_history') }}" class="redirect-btn secondary">
                📈 سود و زیان
            </a>
            <a href="{{ route('futures.journal') }}" class="redirect-btn">
                📓 ژورنال
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('futures.journal') }}" class="filters">
        <select name="month" class="form-control">
            <option value="last6months" {{ $month == 'last6months' ? 'selected' : '' }}>Last 6 Months</option>
            @foreach($availableMonths as $m)
                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($m . '-01')->format('F Y') }}</option>
            @endforeach
        </select>
        <select name="side" class="form-control">
            <option value="all" {{ $side == 'all' ? 'selected' : '' }}>All</option>
            <option value="buy" {{ $side == 'buy' ? 'selected' : '' }}>Buy</option>
            <option value="sell" {{ $side == 'sell' ? 'selected' : '' }}>Sell</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total PnL</h4>
            <p class="{{ $totalPnl >= 0 ? 'pnl-positive' : 'pnl-negative' }}">${{ number_format($totalPnl, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>Total Profits</h4>
            <p class="pnl-positive">${{ number_format($totalProfits, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>Total Losses</h4>
            <p class="pnl-negative">${{ number_format($totalLosses, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>Total Trades</h4>
            <p>{{ $totalTrades }}</p>
        </div>
        <div class="stat-card">
            <h4>Biggest Profit</h4>
            <p class="pnl-positive">${{ number_format($biggestProfit, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>Biggest Loss</h4>
            <p class="pnl-negative">${{ number_format($biggestLoss, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>Average Risk %</h4>
            <p class="pnl-negative">{{ number_format($averageRisk, 2) }}%</p>
        </div>
        <div class="stat-card">
            <h4>متوسط ریسک به ریوارد</h4>
            <p>1 : {{ number_format($averageRRR, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>Profitable Trades</h4>
            <p class="pnl-positive">{{ $profitableTradesCount }}</p>
        </div>
        <div class="stat-card">
            <h4>Losing Trades</h4>
            <p class="pnl-negative">{{ $losingTradesCount }}</p>
        </div>
    </div>

    <div class="chart-container">
        <div id="pnlChart"></div>
    </div>
    <div class="chart-container">
        <div id="cumulativePnlChart"></div>
    </div>

    <div class="text-center text-muted mt-4">
        <p>This page only calculates trades submitted from this site and trades that can't be synchronized are not calculated.</p>
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
                toolbar: {
                    show: true,
                    tools: {
                        pan: true,
                        zoom: true
                    }
                }
            },
            series: [{
                name: 'PnL',
                data: {!! json_encode($pnlChartData) !!}
            }],
            title: {
                text: 'PnL Per Trade',
                align: 'left',
                style: {
                    color: '#fff'
                }
            },
            xaxis: {
                type: 'category',
                labels: {
                    show: false // Hide x-axis labels to avoid clutter
                }
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
                x: {
                    formatter: function(val, { series, seriesIndex, dataPointIndex, w }) {
                        return w.globals.initialSeries[seriesIndex].data[dataPointIndex].date;
                    }
                },
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
                toolbar: {
                    show: true,
                    tools: {
                        pan: true,
                        zoom: true
                    }
                },
                zoom: {
                    type: 'x',
                    enabled: true,
                    autoScaleYaxis: true
                },
            },
            series: [{
                name: 'Cumulative PnL',
                data: {!! json_encode($cumulativePnl) !!}
            }],
            title: {
                text: 'Cumulative PnL Over Time',
                align: 'left',
                style: {
                    color: '#fff'
                }
            },
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
