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
            <table>
                <thead>
                    <tr>
                        <th>صرافی</th>
                        <th>نماد</th>
                        <th>فاندینگ</th>
                        <th>اوپن اینترست</th>
                        <th>زمان داده</th>
                        <th>سطح ریسک</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exchanges as $exchange)
                        @foreach ($symbols as $symbol)
                            @php
                                $snapshot = $latest[$exchange][$symbol] ?? null;
                                $entry = $analysis['levels'][$exchange][$symbol] ?? null;
                            @endphp
                            <tr>
                                <td data-label="صرافی">{{ strtoupper($exchange) }}</td>
                                <td data-label="نماد">{{ $symbol }}</td>
                                <td data-label="فاندینگ" dir="ltr">
                                    {{ $snapshot && $snapshot->funding_rate !== null ? number_format($snapshot->funding_rate * 100, 4) . ' %' : 'نامشخص' }}
                                </td>
                                <td data-label="اوپن اینترست" dir="ltr">
                                    {{ $snapshot && $snapshot->open_interest !== null ? number_format($snapshot->open_interest, 2) : 'نامشخص' }}
                                </td>
                                <td data-label="زمان داده" dir="ltr">
                                    {{ $snapshot && optional($snapshot->metric_time)->format('Y-m-d H:i') ?? 'نامشخص' }}
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

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>زمان</th>
                        <th>صرافی</th>
                        <th>نماد</th>
                        <th>فاندینگ</th>
                        <th>اوپن اینترست</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $row)
                        <tr>
                            <td data-label="زمان" dir="ltr">
                                {{ optional($row->metric_time)->format('Y-m-d H:i') ?? 'نامشخص' }}
                            </td>
                            <td data-label="صرافی">{{ strtoupper($row->exchange) }}</td>
                            <td data-label="نماد">{{ $row->symbol ?? '-' }}</td>
                            <td data-label="فاندینگ" dir="ltr">
                                {{ $row->funding_rate !== null ? number_format($row->funding_rate * 100, 4) . ' %' : 'نامشخص' }}
                            </td>
                            <td data-label="اوپن اینترست" dir="ltr">
                                {{ $row->open_interest !== null ? number_format($row->open_interest, 2) : 'نامشخص' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 10px; text-align: center; color: #777;">
                                هنوز داده‌ای برای نمایش ثبت نشده است. ابتدا همگام‌سازی را از طریق API یا دستور کنسول اجرا کنید.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
