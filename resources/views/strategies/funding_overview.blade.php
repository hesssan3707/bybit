@extends('layouts.app')

@section('title', 'تحلیل فاندینگ و اوپن اینترست')

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
        margin-bottom: 20px;
        text-align: center;
    }
    .table-responsive {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    thead th {
        background-color: rgba(253, 253, 253, 0.05);
        font-weight: bold;
        direction: rtl;
        padding: 12px 15px;
        text-align: right;
    }
    tbody td {
        padding: 12px 15px;
        text-align: right;
    }
    .status-banner {
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .banner-critical { background-color: #ffe5e5; }
    .banner-elevated { background-color: #fff3cd; }
    .banner-normal { background-color: #e7f5ff; }
    .risk-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 0.95em; margin-right: 8px; }
    .badge-critical { background: #3b0000; color: #fff; border: 1px solid rgba(220,53,69,0.35); }
    .badge-elevated { background: #1f1a00; color: #ffd66b; border: 1px solid rgba(255, 214, 107, 0.35); }
    .badge-normal { background: rgba(108,117,125,0.15); color: #6c757d; border: 1px solid rgba(108,117,125,0.35); }
    .risk-legend {
        margin: 10px 0 20px 0;
        padding: 12px 14px;
        border-radius: 12px;
        background: rgba(255,255,255,0.05);
        line-height: 1.8;
    }
    .risk-legend strong { display: block; margin-bottom: 6px; }
    .chart-wrap { width: 100%; }
    .chart-box { width: 100%; height: 360px; }
    .latest-table .exchange-group-row { display: none; }
    @media screen and (max-width: 768px) {
        .chart-box { height: 320px; }
    }
    @media screen and (max-width: 768px) {
        label { display: none; }
        table thead { display: none; }
        table tr {
            display: block;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        table td {
            display: flex;
            justify-content: space-between;
            text-align: right;
            padding: 10px 15px;
            border: none;
            border-bottom: 1px solid rgba(238, 238, 238, 0.25);
        }
        table td:last-child { border-bottom: 0; }
        table td::before {
            content: attr(data-label);
            font-weight: bold;
            padding-left: 10px;
            text-align: left;
        }

        .latest-table td.exchange-cell { display: none; }
        .latest-table .exchange-group-row { display: block; }
        .latest-table .exchange-group-row td {
            display: block;
            padding: 12px 15px;
            font-weight: 800;
            text-align: center;
            background: rgba(255,255,255,0.06);
            border-bottom: 0;
        }
        .latest-table .exchange-group-row td::before { content: ''; display: none; }
    }
</style>
@endpush

@section('content')
<div class="glass-card container">
    <div class="strategy-card">
        <h2>تحلیل فاندینگ و اوپن اینترست بازار آتی</h2>
        <div class="status-banner {{ $analysis['worst_level'] === 'critical' ? 'banner-critical' : ($analysis['worst_level'] === 'elevated' ? 'banner-elevated' : 'banner-normal') }}">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                <span class="risk-badge {{ $analysis['worst_level'] === 'critical' ? 'badge-critical' : ($analysis['worst_level'] === 'elevated' ? 'badge-elevated' : 'badge-normal') }}">
                    {{ $analysis['worst_level'] === 'critical' ? 'پرریسک' : ($analysis['worst_level'] === 'elevated' ? 'ریسک بالا' : 'نرمال') }}
                </span>
                <strong>وضعیت کلی بازار</strong>
            </div>
            <p style="margin: 0; line-height: 1.7;">
                {{ $analysis['message'] }}
            </p>
        </div>

        <div class="risk-legend">
            <strong>سطوح ریسک دقیقاً چه هستند؟</strong>
            <div>
                <span class="risk-badge badge-normal">نرمال</span>
                زمانی که فاندینگ در محدوده طبیعی باشد و اوپن اینترست نسبت به میانگین ۳ روز اخیر جهش غیرعادی نداشته باشد.
            </div>
            <div>
                <span class="risk-badge badge-elevated">بالا</span>
                زمانی که فاندینگ از محدوده طبیعی عبور کند یا اوپن اینترست نسبت به میانگین ۳ روز اخیر افزایش قابل توجه داشته باشد.
            </div>
            <div>
                <span class="risk-badge badge-critical">خیلی بالا</span>
                زمانی که فاندینگ بسیار بزرگ باشد یا اوپن اینترست نسبت به میانگین ۳ روز اخیر جهش شدید داشته باشد.
            </div>
        </div>

        <div class="table-responsive" style="margin-bottom: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>تحلیل تجمیعی</th>
                        <th>نماد</th>
                        <th>میانگین فاندینگ</th>
                        <th>جمع اوپن اینترست</th>
                        <th>تعداد صرافی‌ها (فاندینگ/اوپن)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($symbols as $symbol)
                        @php
                            $agg = $analysis['aggregates'][$symbol] ?? null;
                            $avgFunding = $agg && $agg['avg_funding_rate'] !== null ? number_format($agg['avg_funding_rate'] * 100, 4) . ' %' : 'نامشخص';
                            $sumOi = $agg && $agg['sum_open_interest'] !== null ? number_format($agg['sum_open_interest'], 2) : 'نامشخص';
                            $counts = $agg ? ($agg['funding_count'] . ' / ' . $agg['oi_count']) : '0 / 0';
                        @endphp
                        <tr>
                            <td data-label="تحلیل تجمیعی">کل بازار</td>
                            <td data-label="نماد">{{ $symbol }}</td>
                            <td data-label="میانگین فاندینگ" dir="ltr">{{ $avgFunding }}</td>
                            <td data-label="جمع اوپن اینترست" dir="ltr">{{ $sumOi }}</td>
                            <td data-label="تعداد صرافی‌ها">{{ $counts }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="table-responsive" style="margin-bottom: 20px;">
            <table class="latest-table">
                <thead>
                    <tr>
                        <th>صرافی</th>
                        <th>نماد</th>
                        <th>فاندینگ</th>
                        <th>اوپن اینترست</th>
                        <th>سطح ریسک</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exchanges as $exchange)
                        <tr class="exchange-group-row">
                            <td colspan="5">{{ strtoupper($exchange) }}</td>
                        </tr>
                        @foreach ($symbols as $idx => $symbol)
                            @php
                                $snapshot = $latest[$exchange][$symbol] ?? null;
                                $entry = $analysis['levels'][$exchange][$symbol] ?? null;
                            @endphp
                            <tr>
                                @if ($idx === 0)
                                    <td data-label="صرافی" class="exchange-cell" rowspan="{{ count($symbols) }}">{{ strtoupper($exchange) }}</td>
                                @endif
                                <td data-label="نماد">{{ $symbol }}</td>
                                <td data-label="فاندینگ" dir="ltr">
                                    {{ $snapshot && $snapshot->funding_rate !== null ? number_format($snapshot->funding_rate * 100, 4) . ' %' : 'نامشخص' }}
                                </td>
                                <td data-label="اوپن اینترست" dir="ltr">
                                    {{ $snapshot && $snapshot->open_interest !== null ? number_format($snapshot->open_interest, 2) : 'نامشخص' }}
                                </td>
                                <td data-label="سطح ریسک">
                                    @if ($entry)
                                        @php
                                            $badgeClass = $entry['level'] === 'critical' ? 'badge-critical' : ($entry['level'] === 'elevated' ? 'badge-elevated' : 'badge-normal');
                                            $label = $entry['level'] === 'critical' ? 'خیلی بالا' : ($entry['level'] === 'elevated' ? 'بالا' : 'نرمال');
                                        @endphp
                                        <span class="risk-badge {{ $badgeClass }}">{{ $label }}</span>
                                    @else
                                        <span class="risk-badge badge-normal">نامشخص</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="chart-wrap" style="margin-top: 10px;">
            <h3 style="margin: 0 0 10px 0;">تاریخچه فاندینگ</h3>
            <div id="fundingHistoryChart" class="chart-box"></div>
        </div>

        <div class="chart-wrap" style="margin-top: 25px;">
            <h3 style="margin: 0 0 10px 0;">تاریخچه اوپن اینترست</h3>
            <div id="openInterestHistoryChart" class="chart-box"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var fundingSeries = {!! json_encode($fundingSeries ?? []) !!};
        var oiSeries = {!! json_encode($oiSeries ?? []) !!};

        var common = {
            chart: {
                type: 'line',
                height: 360,
                toolbar: { show: true },
                zoom: { enabled: true }
            },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: {
                type: 'datetime'
            },
            grid: {
                borderColor: 'rgba(0, 0, 0, 0.08)'
            },
            legend: { show: true }
        };

        if (fundingSeries.length) {
            var fundingOptions = Object.assign({}, common, {
                series: fundingSeries,
                yaxis: {
                    labels: {
                        formatter: function(val){ return (val || 0).toFixed(4) + '%'; }
                    }
                },
                tooltip: {
                    x: { format: 'yyyy-MM-dd HH:mm' },
                    y: {
                        formatter: function(val){ return (val || 0).toFixed(6) + '%'; }
                    }
                }
            });
            new ApexCharts(document.querySelector('#fundingHistoryChart'), fundingOptions).render();
        } else {
            document.querySelector('#fundingHistoryChart').innerHTML = '<div style="padding:10px; text-align:center; color:#777;">داده‌ای برای نمایش وجود ندارد.</div>';
        }

        if (oiSeries.length) {
            var oiOptions = Object.assign({}, common, {
                series: oiSeries,
                yaxis: {
                    labels: {
                        formatter: function(val){
                            if (val === null || val === undefined) return '-';
                            return Number(val).toLocaleString(undefined, { maximumFractionDigits: 2 });
                        }
                    }
                },
                tooltip: {
                    x: { format: 'yyyy-MM-dd HH:mm' },
                    y: {
                        formatter: function(val){
                            if (val === null || val === undefined) return '-';
                            return Number(val).toLocaleString(undefined, { maximumFractionDigits: 6 });
                        }
                    }
                }
            });
            new ApexCharts(document.querySelector('#openInterestHistoryChart'), oiOptions).render();
        } else {
            document.querySelector('#openInterestHistoryChart').innerHTML = '<div style="padding:10px; text-align:center; color:#777;">داده‌ای برای نمایش وجود ندارد.</div>';
        }
    });
</script>
@endpush
