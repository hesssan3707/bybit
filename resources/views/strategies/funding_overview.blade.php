@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 1100px; margin: 20px auto;">
    <h1 style="font-size: 24px; margin-bottom: 10px;">تحلیل فاندینگ و اوپن اینترست بازار آتی</h1>
    <p style="margin-bottom: 20px; color: #555;">
        در این صفحه، آخرین مقادیر فاندینگ و اوپن اینترست بیت‌کوین و اتریوم در صرافی‌های مختلف و همچنین روند تاریخی اخیر آن‌ها نمایش داده می‌شود.
    </p>

    <div style="border-radius: 8px; padding: 15px; margin-bottom: 20px;
                background-color: {{ $analysis['worst_level'] === 'critical' ? '#ffe5e5' : ($analysis['worst_level'] === 'elevated' ? '#fff3cd' : '#e7f5ff') }};">
        <h2 style="font-size: 18px; margin-bottom: 8px;">
            وضعیت کلی بازار
        </h2>
        <p style="margin: 0; line-height: 1.7;">
            {{ $analysis['message'] }}
        </p>
    </div>

    <h2 style="font-size: 18px; margin-bottom: 10px;">وضعیت فعلی هر صرافی</h2>
    <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 25px;">
        @foreach ($exchanges as $exchange)
            <div style="flex: 1 1 260px; min-width: 0; border: 1px solid #ddd; border-radius: 8px; padding: 10px;">
                <h3 style="font-size: 16px; margin-bottom: 8px;">
                    {{ strtoupper($exchange) }}
                </h3>
                @foreach ($symbols as $symbol)
                    @php
                        $snapshot = $latest[$exchange][$symbol] ?? null;
                        $entry = $analysis['levels'][$exchange][$symbol] ?? null;
                    @endphp
                    <div style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed #eee;">
                        <strong>{{ $symbol }}</strong>
                        @if ($snapshot)
                            <div style="font-size: 13px; margin-top: 4px;">
                                <div>
                                    فاندینگ: 
                                    <span dir="ltr">
                                        {{ $snapshot->funding_rate !== null ? number_format($snapshot->funding_rate * 100, 4) . ' %' : 'نامشخص' }}
                                    </span>
                                </div>
                                <div>
                                    اوپن اینترست: 
                                    <span dir="ltr">
                                        {{ $snapshot->open_interest !== null ? number_format($snapshot->open_interest, 2) : 'نامشخص' }}
                                    </span>
                                </div>
                                <div>
                                    زمان داده: 
                                    <span dir="ltr">
                                        {{ optional($snapshot->metric_time)->format('Y-m-d H:i') ?? 'نامشخص' }}
                                    </span>
                                </div>
                                @if ($entry)
                                    <div style="margin-top: 4px;">
                                        سطح ریسک: 
                                        @if ($entry['level'] === 'critical')
                                            <span style="color:#c00;">خیلی بالا</span>
                                        @elseif ($entry['level'] === 'elevated')
                                            <span style="color:#a66b00;">بالا</span>
                                        @else
                                            <span style="color:#117a2d;">نرمال</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @else
                            <div style="font-size: 13px; margin-top: 4px; color: #777;">
                                داده‌ای برای این نماد در این صرافی ثبت نشده است.
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <h2 style="font-size: 18px; margin-bottom: 10px;">تاریخچه اخیر</h2>
    <div style="overflow-x: auto; border-radius: 8px; border: 1px solid #ddd;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead style="background-color: #f8f9fa;">
                <tr>
                    <th style="padding: 8px; border-bottom: 1px solid #ddd;">زمان</th>
                    <th style="padding: 8px; border-bottom: 1px solid #ddd;">صرافی</th>
                    <th style="padding: 8px; border-bottom: 1px solid #ddd;">نماد</th>
                    <th style="padding: 8px; border-bottom: 1px solid #ddd;">فاندینگ</th>
                    <th style="padding: 8px; border-bottom: 1px solid #ddd;">اوپن اینترست</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $row)
                    <tr>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #f1f1f1;" dir="ltr">
                            {{ optional($row->metric_time)->format('Y-m-d H:i') ?? 'نامشخص' }}
                        </td>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #f1f1f1;">
                            {{ strtoupper($row->exchange) }}
                        </td>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #f1f1f1;">
                            {{ $row->symbol ?? '-' }}
                        </td>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #f1f1f1;" dir="ltr">
                            {{ $row->funding_rate !== null ? number_format($row->funding_rate * 100, 4) . ' %' : 'نامشخص' }}
                        </td>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #f1f1f1;" dir="ltr">
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
@endsection

@push('styles')
<style>
@media (max-width: 768px) {
    .container {
        padding: 0 10px;
    }
}
</style>
@endpush

