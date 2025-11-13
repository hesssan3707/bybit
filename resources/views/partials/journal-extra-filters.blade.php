<div style="display:grid; grid-template-columns: repeat(12, 1fr); gap:10px;">
    <div style="grid-column: span 4;">
        <label class="filter-label">دوره</label>
        <select name="period_id" class="filter-select">
            @foreach($recentPeriods as $p)
                <option value="{{ $p->id }}" {{ ($selectedPeriod && $selectedPeriod->id === $p->id) ? 'selected' : '' }}>
                    {{ $p->name }} — {{ optional($p->started_at)->format('Y-m-d') }} تا {{ $p->ended_at ? $p->ended_at->format('Y-m-d') : 'جاری' }}
                    {{ $p->is_default ? ' • پیش‌فرض' : '' }}
                    {{ $p->is_active ? ' • فعال' : ' • پایان‌یافته' }}
                </option>
            @endforeach
        </select>
    </div>
    <div style="grid-column: span 4;">
        <label class="filter-label">جهت</label>
        <select name="side" class="filter-select">
            <option value="all" {{ $side == 'all' ? 'selected' : '' }}>همه</option>
            <option value="buy" {{ $side == 'buy' ? 'selected' : '' }}>معامله های خرید</option>
            <option value="sell" {{ $side == 'sell' ? 'selected' : '' }}>معامله های فروش</option>
        </select>
    </div>
    <div style="grid-column: span 4;">
        <label class="filter-label">صرافی</label>
        <select name="user_exchange_id" class="filter-select">
            <option value="all" {{ $userExchangeId == 'all' ? 'selected' : '' }}>همه صرافی‌ها</option>
            @foreach($exchangeOptions as $ex)
                <option value="{{ $ex->id }}" {{ (string)$userExchangeId === (string)$ex->id ? 'selected' : '' }}>
                    {{ strtoupper($ex->exchange_name) }} — {{ $ex->is_demo_active ? 'دمو' : 'واقعی' }}
                </option>
            @endforeach
        </select>
    </div>
</div>