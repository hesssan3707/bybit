@extends('layouts.app')

@section('title', 'P&L History')

@push('styles')
<style>
    .container {
        background: #ffffff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    h2 {
        text-align: center;
        margin-bottom: 25px;
    }
    .table-responsive {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    th, td {
        padding: 12px 15px;
        border: 1px solid #dee2e6;
        text-align: right;
    }
    thead {
        background-color: #f8f9fa;
    }
    tbody tr:nth-of-type(odd) {
        background-color: #f9f9f9;
    }
    .pagination {
        margin-top: 20px;
        display: flex;
        justify-content: center;
    }
</style>
@endpush

@section('content')
<div class="container">
    <h2>تاریخچه سود و زیان</h2>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Side</th>
                    <th>Qty</th>
                    <th>Avg. Entry Price</th>
                    <th>Avg. Exit Price</th>
                    <th>P&L</th>
                    <th>Closed At</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($positions as $position)
                    <tr>
                        <td>{{ $position->symbol }}</td>
                        <td>{{ $position->side }}</td>
                        <td>{{ rtrim(rtrim(number_format($position->qty, 8), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($position->avg_entry_price, 4), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($position->avg_exit_price, 4), '0'), '.') }}</td>
                        <td>
                            <span style="color: {{ $position->pnl >= 0 ? 'green' : 'red' }};">
                                {{ rtrim(rtrim(number_format($position->pnl, 4), '0'), '.') }}
                            </span>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($position->closed_at)->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center;">No closed positions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $positions->links() }}
    </div>
</div>
@endsection
