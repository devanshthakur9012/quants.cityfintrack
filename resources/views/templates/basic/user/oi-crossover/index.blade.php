@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg-primary:    #0a0e17;
    --bg-card:       #0f1520;
    --bg-panel:      #151d2e;
    --bg-row:        #111827;
    --border:        #1e2d45;
    --border-bright: #2a3f5f;
    --text-primary:  #e2e8f0;
    --text-muted:    #64748b;
    --text-dim:      #94a3b8;
    --accent:        #3b82f6;
    --accent-glow:   rgba(59,130,246,0.18);
    --bullish:       #10b981;
    --bullish-bg:    rgba(16,185,129,0.10);
    --bearish:       #ef4444;
    --bearish-bg:    rgba(239,68,68,0.10);
    --ce-color:      #f59e0b;
    --pe-color:      #8b5cf6;
    --mono:          'JetBrains Mono', monospace;
}
* { box-sizing: border-box; }
body { background: var(--bg-primary); color: var(--text-primary); }

.oi-page {
    min-height: 100vh;
    padding: 24px 20px 60px;
    background: radial-gradient(ellipse 80% 40% at 50% -10%, rgba(59,130,246,0.07) 0%, transparent 70%), var(--bg-primary);
}

/* Header */
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
.page-title  { font-weight:800; font-size:1.45rem; color:var(--text-primary); letter-spacing:-0.5px; }
.page-title span { color:var(--accent); }
.live-badge  { display:flex; align-items:center; gap:7px; background:var(--bg-panel); border:1px solid var(--border); border-radius:20px; padding:5px 14px; font-size:0.75rem; color:var(--text-dim); }
.live-dot    { width:7px; height:7px; border-radius:50%; background:var(--bullish); animation:pulse 1.8s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:0.3} }

/* Filter */
.filter-bar { background:var(--bg-panel); border:1px solid var(--border); border-radius:12px; padding:18px 20px; display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end; margin-bottom:24px; }
.filter-group { display:flex; flex-direction:column; gap:6px; min-width:130px; }
.filter-group label { font-size:0.67rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; font-weight:500; }
.filter-control { background:var(--bg-row); border:1px solid var(--border); border-radius:7px; color:var(--text-primary); padding:8px 12px; font-size:0.82rem; outline:none; transition:border-color 0.2s; }
.filter-control:focus { border-color:var(--accent); }
.filter-control option { background:var(--bg-row); }
.btn-apply  { background:var(--accent); color:white; border:none; border-radius:7px; padding:8px 22px; font-size:0.82rem; font-weight:700; cursor:pointer; align-self:flex-end; transition:opacity 0.2s; }
.btn-apply:hover { opacity:0.88; }
.btn-clear  { background:transparent; color:var(--text-muted); border:1px solid var(--border); border-radius:7px; padding:8px 16px; font-size:0.82rem; cursor:pointer; align-self:flex-end; }
.btn-clear:hover { border-color:var(--bearish); color:var(--bearish); }

/* Stats */
.stats-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
.stat-pill  { background:var(--bg-panel); border:1px solid var(--border); border-radius:8px; padding:10px 18px; text-align:center; min-width:110px; }
.stat-pill .num { font-weight:800; font-size:1.3rem; color:var(--text-primary); line-height:1; }
.stat-pill .lbl { font-size:0.67rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.8px; margin-top:4px; }
.stat-pill.bull .num { color:var(--bullish); }
.stat-pill.bear .num { color:var(--bearish); }

/* Section */
.section-header { display:flex; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
.section-title  { font-weight:700; font-size:1rem; color:var(--text-primary); }
.section-count  { background:var(--accent-glow); color:var(--accent); border:1px solid rgba(59,130,246,0.3); border-radius:20px; padding:2px 12px; font-size:0.72rem; font-weight:700; }
.mode-note      { font-size:0.72rem; color:var(--text-muted); margin-left:auto; font-style:italic; }

/* ══ OVERVIEW TABLE ══ */
.overview-wrap { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
.overview-table { width:100%; border-collapse:collapse; font-size:0.78rem; }
.overview-table thead tr { background:var(--bg-panel); }
.overview-table thead tr:first-child { border-bottom:1px solid var(--border-bright); }
.overview-table th { padding:10px 12px; text-align:center; font-size:0.63rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:500; white-space:nowrap; }
.overview-table th.left { text-align:left; }

/* Column group colors */
.th-sym   { background:rgba(59,130,246,0.06); }
.th-time  { background:rgba(56,189,248,0.08); color:#38bdf8 !important; }
.th-ce    { background:rgba(245,158,11,0.06); color:var(--ce-color) !important; }
.th-pe    { background:rgba(139,92,246,0.06); color:var(--pe-color) !important; }

.sep-ce { border-left:2px solid rgba(245,158,11,0.3) !important; }
.sep-pe { border-left:2px solid rgba(139,92,246,0.3) !important; }
.sep-time { border-left:none !important; }

.overview-table tbody tr { border-bottom:1px solid var(--border); transition:background 0.15s; }
.overview-table tbody tr:hover { background:var(--bg-panel); }
.overview-table tbody tr:last-child { border-bottom:none; }
.overview-table td { padding:0; vertical-align:middle; }

/* Symbol cell */
.sym-cell {
    padding:0; vertical-align:middle;
}
.sym-inner {
    display:flex; align-items:flex-start;
    padding:10px 14px; gap:6px; min-width:130px;
}
.sym-name { font-weight:800; font-size:0.9rem; color:var(--text-primary); }
.sym-link { text-decoration:none; color:inherit; }
.sym-link:hover .sym-name { color:var(--accent); }

/* Last time badge — sits below the symbol name */
.last-time-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(56, 189, 248, 0.12);
    border: 1px solid rgba(56, 189, 248, 0.25);
    border-radius: 6px;
    padding: 1px 5px;
    font-family: var(--mono);
    font-weight: 700;
    color: #38bdf8;
    white-space: nowrap;
    font-size: 10px;
}
.last-time-badge .dot { width:5px; height:5px; border-radius:50%; background:#38bdf8; animation:pulse 2s ease-in-out infinite; }
.last-time-warn { background:rgba(239,68,68,0.12); border-color:rgba(239,68,68,0.3); color:var(--bearish); }
.candle-count { font-size:0.63rem; color:var(--text-muted); font-style:italic;display:none; }

/* Option data cell */
.opt-cell { padding:9px 12px; vertical-align:middle; text-align:center; white-space:nowrap;vertical-align: middle; }
.opt-cell.left { text-align:left; }

/* Signal badges */
.sig-badge { display:inline-flex; align-items:center; gap:4px; font-size:0.68rem; font-weight:700; padding:2px 8px; border-radius:4px; }
.sig-badge.bullish { background:var(--bullish-bg); color:var(--bullish); border:1px solid rgba(16,185,129,0.25); }
.sig-badge.bearish { background:var(--bearish-bg); color:var(--bearish); border:1px solid rgba(239,68,68,0.25); }
.sig-badge.neutral { background:rgba(100,116,139,0.1); color:var(--text-muted); border:1px solid rgba(100,116,139,0.2); }
.sig-badge.cross   { box-shadow:0 0 8px currentColor; }

.type-pill { display:inline-block; font-size:0.65rem; font-weight:700; padding:1px 7px; border-radius:3px; }
.type-pill.ce { background:rgba(245,158,11,0.15); color:var(--ce-color); }
.type-pill.pe { background:rgba(139,92,246,0.15); color:var(--pe-color); }

.oi-val    { color:#38bdf8; font-weight:600; font-size:0.75rem; }
.ma-oi-val { color:#ef4444; font-weight:600; font-size:0.75rem; }
.price-val { color:var(--text-primary); font-size:0.75rem; }
.ma-pr-val { color:#a78bfa; font-weight:600; font-size:0.75rem; }
.na-val    { color:var(--text-muted); opacity:0.4; font-size:0.72rem; }

.btn-chart-sm { background:transparent; border:1px solid var(--border); border-radius:4px; color:var(--text-muted); padding:3px 8px; font-size:0.68rem; cursor:pointer; transition:all 0.2s; }
.btn-chart-sm:hover { border-color:var(--accent); color:var(--accent); }

.cross-info { display:inline-flex; align-items:center; gap:5px; }
.time-val   { color:var(--text-muted); font-size:0.72rem; font-family:var(--mono); }

.no-data-cell { color:var(--text-muted); font-size:0.72rem; opacity:0.5; text-align:center; padding:10px; }

/* ══ DETAIL MODE ══ */
.detail-header { display:flex; align-items:center; gap:14px; margin-bottom:20px; flex-wrap:wrap; }
.detail-back   { background:var(--bg-panel); border:1px solid var(--border); border-radius:7px; color:var(--text-dim); padding:6px 14px; font-size:0.8rem; cursor:pointer; text-decoration:none; transition:all 0.2s; display:inline-flex; align-items:center; gap:6px; }
.detail-back:hover { border-color:var(--accent); color:var(--accent); }
.detail-title    { font-weight:800; font-size:1.15rem; color:var(--text-primary); }
.detail-subtitle { font-size:0.78rem; color:var(--text-muted); }

.detail-block { margin-bottom:28px; }
.detail-block-hdr { display:flex; align-items:center; gap:10px; background:var(--bg-panel); border:1px solid var(--border); border-radius:10px 10px 0 0; padding:12px 16px; border-bottom:none; }

.detail-wrap { background:var(--bg-card); border:1px solid var(--border); border-radius:0 0 10px 10px; overflow:hidden; }
.detail-table { width:100%; border-collapse:collapse; font-size:0.77rem; }
.detail-table thead tr { background:var(--bg-panel); border-bottom:1px solid var(--border-bright); }
.detail-table th { padding:9px 12px; font-size:0.63rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:500; white-space:nowrap; text-align:center; }
.detail-table th.left { text-align:left; }
.detail-table tbody tr { border-bottom:1px solid rgba(30,45,69,0.5); transition:background 0.12s; }
.detail-table tbody tr:hover { background:var(--bg-panel); }
.detail-table tbody tr.has-cross { background:rgba(59,130,246,0.04); }
.detail-table tbody tr.last-row  { background:rgba(56,189,248,0.05); border-top:1px solid rgba(56,189,248,0.25); }
.detail-table tbody tr:last-child { border-bottom:none; }
.detail-table td { padding:8px 12px; vertical-align:middle; text-align:center; white-space:nowrap; }
.detail-table td.left { text-align:left; }

.price-up   { color:var(--bullish); }
.price-dn   { color:var(--bearish); }
.price-neut { color:var(--text-primary); }
.mono       { font-family:var(--mono); }

.time-cell-detail { font-family:var(--mono); font-size:0.73rem; color:#38bdf8; font-weight:700; letter-spacing:0.5px; }

/* Legend */
.legend-box { margin-top:14px; padding:14px 18px; background:var(--bg-panel); border:1px solid var(--border); border-radius:10px; font-size:0.73rem; color:var(--text-muted); line-height:1.9; }
.legend-box strong { color:var(--text-dim); }
.lg-bull { color:var(--bullish); font-weight:700; }
.lg-bear { color:var(--bearish); font-weight:700; }
.lg-oi   { color:#38bdf8; font-weight:700; }
.lg-ma-oi{ color:#ef4444; font-weight:700; }
.lg-pr   { color:var(--text-primary); font-weight:700; }
.lg-ma-pr{ color:#a78bfa; font-weight:700; }

/* Empty */
.empty-state { text-align:center; padding:50px 20px; color:var(--text-muted); }
.empty-state i { font-size:2.5rem; margin-bottom:12px; display:block; }

/* Chart modal */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.78); z-index:9999; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--bg-card); border:1px solid var(--border-bright); border-radius:14px; width:min(860px,97vw); padding:24px; position:relative; }
.modal-title    { font-weight:700; font-size:1rem; margin-bottom:4px; color:var(--text-primary); }
.modal-subtitle { font-size:0.72rem; color:var(--text-muted); margin-bottom:16px; }
.modal-close    { position:absolute; top:16px; right:18px; background:transparent; border:none; color:var(--text-muted); font-size:1.2rem; cursor:pointer; }
.chart-tabs { display:flex; gap:8px; margin-bottom:14px; }
.chart-tab  { background:var(--bg-row); border:1px solid var(--border); border-radius:6px; color:var(--text-muted); padding:5px 14px; font-size:0.74rem; cursor:pointer; transition:all 0.2s; }
.chart-tab.active { background:var(--accent); border-color:var(--accent); color:white; }
.chart-canvas-wrap { position:relative; height:260px; }
.legend-row { display:flex; gap:18px; font-size:0.72rem; color:var(--text-muted); margin-top:10px; flex-wrap:wrap; }
.legend-item { display:flex; align-items:center; gap:6px; }
.legend-dot  { width:22px; height:3px; border-radius:2px; }

@media(max-width:768px){
    .overview-table td,.overview-table th { padding:5px 6px; }
    .sym-inner { padding:8px 10px; }
}
</style>
@endpush

<div class="oi-page">
<div style="max-width:1700px; margin:0 auto;">

    {{-- Header --}}
    <div class="page-header">
        <h1 class="page-title">OI &amp; Price vs <span>{{ $maPeriod }}MA</span> Crossover</h1>
        <div class="live-badge">
            <span class="live-dot"></span>
            {{ $selectedDate ? \Carbon\Carbon::parse($selectedDate)->format('d M Y') : 'No data' }}
        </div>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ request()->url() }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label>Trade Date</label>
                <select name="trade_date" class="filter-control">
                    @foreach($availableDates as $d)
                        <option value="{{ $d }}" {{ $selectedDate == $d ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($d)->format('d M Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Symbol</label>
                <select name="symbol" class="filter-control" style="min-width:150px;">
                    <option value="">All Symbols</option>
                    @foreach($availableSymbols as $sym)
                        <option value="{{ $sym }}" {{ $selectedSymbol == $sym ? 'selected' : '' }}>{{ $sym }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>MA Period</label>
                <input type="number" name="ma_period" class="filter-control" value="{{ $maPeriod }}" min="2" max="200" style="width:80px;">
            </div>
            <div class="filter-group">
                <label>Option Type</label>
                <select name="option_type" class="filter-control">
                    <option value="BOTH" {{ $optionType=='BOTH'?'selected':'' }}>CE + PE</option>
                    <option value="CE"   {{ $optionType=='CE'  ?'selected':'' }}>CE Only</option>
                    <option value="PE"   {{ $optionType=='PE'  ?'selected':'' }}>PE Only</option>
                </select>
            </div>
            <button type="submit" class="btn-apply">▶ Apply</button>
            @if($selectedSymbol)
                <a href="{{ request()->url() }}?trade_date={{ $selectedDate }}&ma_period={{ $maPeriod }}&option_type={{ $optionType }}" class="btn-clear">✕ Clear Symbol</a>
            @endif
        </div>
    </form>

    @if($selectedDate)

    {{-- Stats --}}
    @php
        if($detailMode) {
            $totalCandles = collect($detailRows)->sum(fn($d) => $d['total_candles']);
            $totalCross   = collect($detailRows)->sum(fn($d) => $d['crossover_count']);
        } else {
            $ceBull = $crossovers->filter(fn($r) => isset($r['ce']['oi_signal']) && $r['ce']['oi_signal']==='BULLISH')->count();
            $ceBear = $crossovers->filter(fn($r) => isset($r['ce']['oi_signal']) && $r['ce']['oi_signal']==='BEARISH')->count();
            $peBull = $crossovers->filter(fn($r) => isset($r['pe']['oi_signal']) && $r['pe']['oi_signal']==='BULLISH')->count();
            $peBear = $crossovers->filter(fn($r) => isset($r['pe']['oi_signal']) && $r['pe']['oi_signal']==='BEARISH')->count();
        }
    @endphp
    <div class="stats-row">
        <div class="stat-pill"><div class="num">{{ $totalSymbols }}</div><div class="lbl">Symbols</div></div>
        @if($detailMode)
            <div class="stat-pill"><div class="num">{{ $totalCandles }}</div><div class="lbl">Candles</div></div>
            <div class="stat-pill bull"><div class="num">{{ $totalCross }}</div><div class="lbl">Cross Events</div></div>
        @else
            <div class="stat-pill"><div class="num">{{ $crossovers->count() }}</div><div class="lbl">Symbols w/ Data</div></div>
            <div class="stat-pill bull"><div class="num">{{ $ceBull }}</div><div class="lbl">CE OI Bull</div></div>
            <div class="stat-pill bear"><div class="num">{{ $ceBear }}</div><div class="lbl">CE OI Bear</div></div>
            <div class="stat-pill" style="--bullish:#8b5cf6;"><div class="num" style="color:#8b5cf6;">{{ $peBull }}</div><div class="lbl">PE OI Bull</div></div>
            <div class="stat-pill bear"><div class="num">{{ $peBear }}</div><div class="lbl">PE OI Bear</div></div>
        @endif
        <div class="stat-pill" style="margin-left:auto;"><div class="num" style="font-size:0.8rem; color:var(--text-dim);">{{ $maPeriod }}-period</div><div class="lbl">MA Window</div></div>
    </div>

    {{-- ════════════════════════════════════════════════════════
         OVERVIEW MODE
         Layout per row:
           [SYMBOL + Last Time] | [CE: strike / OI Sig / OI / OI-MA / Price Sig / LTP / Price-MA / Last Cross / Chart]
                                | [PE: same columns]
    ═════════════════════════════════════════════════════════ --}}
    @if(!$detailMode)

    <div class="section-header">
        <span class="section-title">Signal Summary — Last Candle of Day</span>
        <span class="section-count">{{ $crossovers->count() }} symbols</span>
        <span class="mode-note">Strike = most data-complete across the day &nbsp;|&nbsp; Click symbol name for 15-min breakdown →</span>
    </div>

    <div class="overview-wrap">
        @if($crossovers->isEmpty())
            <div class="empty-state"><i class="las la-chart-area"></i><h5>No Data</h5><p>No option data for selected date/filters.</p></div>
        @else
        <div style="overflow-x:auto;">
        <table class="overview-table">
            <thead>
                <tr>
                    {{-- Left fixed block --}}
                    <th class="left th-sym" rowspan="2" style="min-width:140px;">#&nbsp;&nbsp;Symbol<br><small style="font-weight:400; text-transform:none; color:#38bdf8; font-size:0.62rem;">↓ Last Candle Time</small></th>

                    {{-- CE columns --}}
                    <th class="th-ce sep-ce" colspan="8" style="border-bottom:1px solid rgba(245,158,11,0.3);">
                        <span class="type-pill ce" style="font-size:0.75rem; padding:2px 10px;">CE</span> — Call Option
                    </th>
                    <th class="th-ce" rowspan="2" style="border-left:1px solid rgba(245,158,11,0.15);">Chart</th>

                    {{-- PE columns --}}
                    <th class="th-pe sep-pe" colspan="8" style="border-bottom:1px solid rgba(139,92,246,0.3);">
                        <span class="type-pill pe" style="font-size:0.75rem; padding:2px 10px;">PE</span> — Put Option
                    </th>
                    <th class="th-pe" rowspan="2" style="border-left:1px solid rgba(139,92,246,0.15);">Chart</th>
                </tr>
                <tr>
                    {{-- CE sub-headers --}}
                    <th class="th-ce sep-ce">Strike</th>
                    <th class="th-ce">OI Signal</th>
                    <th class="th-ce" style="color:#38bdf8;">OI</th>
                    <th class="th-ce" style="color:#ef4444;">OI {{ $maPeriod }}MA</th>
                    <th class="th-ce">Price Signal</th>
                    <th class="th-ce">LTP</th>
                    <th class="th-ce" style="color:#a78bfa;">Price {{ $maPeriod }}MA</th>
                    <th class="th-ce">Last OI Cross</th>
                    {{-- PE sub-headers --}}
                    <th class="th-pe sep-pe">Strike</th>
                    <th class="th-pe">OI Signal</th>
                    <th class="th-pe" style="color:#38bdf8;">OI</th>
                    <th class="th-pe" style="color:#ef4444;">OI {{ $maPeriod }}MA</th>
                    <th class="th-pe">Price Signal</th>
                    <th class="th-pe">LTP</th>
                    <th class="th-pe" style="color:#a78bfa;">Price {{ $maPeriod }}MA</th>
                    <th class="th-pe">Last OI Cross</th>
                </tr>
            </thead>
            <tbody>
            @foreach($crossovers as $idx => $row)
            @php
                $ce = $row['ce'] ?? null;
                $pe = $row['pe'] ?? null;
                $lastTime = $row['last_time'] ?? null;
                $timeWarn = $lastTime && $lastTime < '15:15';

                $renderSig = function($sig) {
                    if (!$sig || $sig === 'NEUTRAL') return "<span class='na-val'>—</span>";
                    $cls = $sig === 'BULLISH' ? 'bullish' : 'bearish';
                    $ico = $sig === 'BULLISH' ? '▲' : '▼';
                    return "<span class='sig-badge {$cls}'>{$ico} {$sig}</span>";
                };

                $renderCross = function($time, $dir) {
                    if (!$time || !$dir) return "<span class='na-val'>—</span>";
                    $cls = $dir === 'BULLISH' ? 'bullish' : 'bearish';
                    $ico = $dir === 'BULLISH' ? '▲' : '▼';
                    return "<span class='cross-info'><span class='sig-badge {$cls} cross' style='font-size:0.6rem; padding:1px 5px;'>{$ico}</span><span class='time-val'>{$time}</span></span>";
                };
            @endphp
            <tr>
                {{-- Symbol + Last Time --}}
                <td class="sym-cell">
                    <div class="sym-inner">
                        <span style="font-size:0.67rem; color:var(--text-muted);">{{ $idx+1 }}</span>
                        <a href="{{ request()->url() }}?trade_date={{ $selectedDate }}&ma_period={{ $maPeriod }}&option_type={{ $optionType }}&symbol={{ $row['symbol'] }}"
                           class="sym-link"><span class="sym-name">{{ $row['symbol'] }}</span></a>
                        {{-- COMMON LAST CANDLE TIME --}}
                        @if($lastTime)
                            <div class="last-time-badge {{ $timeWarn ? 'last-time-warn' : '' }}">
                                <span class="dot"></span>
                                {{ $lastTime }}
                                @if($timeWarn)<span style="font-size:0.6rem; margin-left:2px;">⚠</span>@endif
                            </div>
                        @else
                            <span class="na-val">no time</span>
                        @endif
                        @if($ce)<span class="candle-count">{{ $ce['total_candles'] ?? 0 }} candles</span>@endif
                    </div>
                </td>

                {{-- CE Data --}}
                @if($ce)
                <td class="opt-cell sep-ce" style="min-width:120px;">
                    <span class="type-pill ce">CE</span>
                    <span style="font-size:0.78rem; font-weight:700; color:var(--ce-color);">{{ number_format((float)$ce['strike'], 0) }}</span>
                    <span style="font-size:0.62rem; color:var(--text-muted);">({{ $ce['strike_position'] ?? '—' }})</span>
                </td>
                <td class="opt-cell">{!! $renderSig($ce['oi_signal']) !!}</td>
                <td class="opt-cell oi-val">{{ number_format($ce['latest_oi']) }}</td>
                <td class="opt-cell ma-oi-val">{{ $ce['latest_oi_ma'] !== null ? number_format($ce['latest_oi_ma']) : '—' }}</td>
                <td class="opt-cell">{!! $renderSig($ce['price_signal']) !!}</td>
                <td class="opt-cell price-val">₹{{ number_format((float)$ce['ltp'], 2) }}</td>
                <td class="opt-cell ma-pr-val">{{ $ce['latest_close_ma'] !== null ? '₹'.number_format($ce['latest_close_ma'],2) : '—' }}</td>
                <td class="opt-cell">{!! $renderCross($ce['last_oi_cross_time'] ?? null, $ce['last_oi_cross_dir'] ?? null) !!}</td>
                <td class="opt-cell" style="border-left:1px solid rgba(245,158,11,0.15);">
                    <button class="btn-chart-sm" onclick="openChart('{{ $row['symbol'] }}','CE','{{ $ce['strike'] }}','{{ $selectedDate }}','{{ $maPeriod }}')">📈</button>
                </td>
                @else
                <td class="no-data-cell sep-ce" colspan="8">No CE data</td>
                <td class="opt-cell"></td>
                @endif

                {{-- PE Data --}}
                @if($pe)
                <td class="opt-cell sep-pe" style="min-width:120px;">
                    <span class="type-pill pe">PE</span>
                    <span style="font-size:0.78rem; font-weight:700; color:var(--pe-color);">{{ number_format((float)$pe['strike'], 0) }}</span>
                    <span style="font-size:0.62rem; color:var(--text-muted);">({{ $pe['strike_position'] ?? '—' }})</span>
                </td>
                <td class="opt-cell">{!! $renderSig($pe['oi_signal']) !!}</td>
                <td class="opt-cell oi-val">{{ number_format($pe['latest_oi']) }}</td>
                <td class="opt-cell ma-oi-val">{{ $pe['latest_oi_ma'] !== null ? number_format($pe['latest_oi_ma']) : '—' }}</td>
                <td class="opt-cell">{!! $renderSig($pe['price_signal']) !!}</td>
                <td class="opt-cell price-val">₹{{ number_format((float)$pe['ltp'], 2) }}</td>
                <td class="opt-cell ma-pr-val">{{ $pe['latest_close_ma'] !== null ? '₹'.number_format($pe['latest_close_ma'],2) : '—' }}</td>
                <td class="opt-cell">{!! $renderCross($pe['last_oi_cross_time'] ?? null, $pe['last_oi_cross_dir'] ?? null) !!}</td>
                <td class="opt-cell" style="border-left:1px solid rgba(139,92,246,0.15);">
                    <button class="btn-chart-sm" onclick="openChart('{{ $row['symbol'] }}','PE','{{ $pe['strike'] }}','{{ $selectedDate }}','{{ $maPeriod }}')">📈</button>
                </td>
                @else
                <td class="no-data-cell sep-pe" colspan="8">No PE data</td>
                <td class="opt-cell"></td>
                @endif
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    <div class="legend-box">
        <strong>Overview:</strong> Each row = one symbol. Time shown below the symbol name = last candle of the day (should be <strong>15:15</strong>). ⚠ warning if data is incomplete.
        Strike chosen = the one with the <strong>most candles</strong> across the full day (most complete coverage).<br>
        <span class="lg-bear">▼ BEARISH OI</span> = OI > {{ $maPeriod }}MA at last candle (writers dominating) &nbsp;|&nbsp;
        <span class="lg-bull">▲ BULLISH OI</span> = OI &lt; {{ $maPeriod }}MA (unwinding) &nbsp;|&nbsp;
        <span class="lg-bull">▲ BULLISH Price</span> = Close > {{ $maPeriod }}MA &nbsp;|&nbsp;
        <span class="lg-bear">▼ BEARISH Price</span> = Close &lt; {{ $maPeriod }}MA
    </div>

    {{-- ════════════════════════════════════════════════════════
         DETAIL MODE — all 15-min candles for one symbol
    ═════════════════════════════════════════════════════════ --}}
    @else

    <div class="detail-header">
        <a href="{{ request()->url() }}?trade_date={{ $selectedDate }}&ma_period={{ $maPeriod }}&option_type={{ $optionType }}" class="detail-back">← Back to All</a>
        <div>
            <div class="detail-title">{{ $selectedSymbol }} — Full Day Breakdown</div>
            <div class="detail-subtitle">{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }} &nbsp;·&nbsp; Strike = most candles across the day &nbsp;·&nbsp; 09:15 → 15:15</div>
        </div>
    </div>

    @foreach($detailRows as $block)
    @php
        $bType   = $block['type'];
        $n       = $block['total_candles'];
        $crosses = $block['crossover_count'];
        $lastRow = end($block['rows']);
        $lastT   = $lastRow ? $lastRow['time'] : null;
        $timeWarnDetail = $lastT && $lastT < '15:15';
    @endphp
    <div class="detail-block">
        <div class="detail-block-hdr">
            <span class="type-pill {{ strtolower($bType) }}" style="font-size:0.78rem; padding:3px 12px;">{{ $bType }}</span>
            <span style="font-weight:700; font-size:0.9rem;">{{ $block['trading_symbol'] }}</span>
            <span style="font-size:0.78rem; color:var(--text-muted);">Strike: <strong style="color:var(--text-primary);">{{ number_format((float)$block['strike'], 0) }}</strong></span>

            {{-- Last time badge --}}
            @if($lastT)
            <div class="last-time-badge {{ $timeWarnDetail ? 'last-time-warn' : '' }}" style="margin-left:8px;">
                <span class="dot"></span> Last: {{ $lastT }}
                @if($timeWarnDetail) <span style="font-size:0.6rem;">⚠ incomplete</span>@endif
            </div>
            @endif

            <span style="font-size:0.72rem; color:var(--text-muted); margin-left:4px;">{{ $n }} candles</span>
            <span class="section-count" style="margin-left:auto;">{{ $crosses }} cross events</span>
            <button class="btn-chart-sm" onclick="openChart('{{ $selectedSymbol }}','{{ $bType }}','{{ $block['strike'] }}','{{ $selectedDate }}','{{ $maPeriod }}')">📈 Chart</button>
        </div>
        <div class="detail-wrap">
            @if(empty($block['rows']))
                <div class="empty-state" style="padding:30px;"><p>No candle data.</p></div>
            @else
            <div style="overflow-x:auto;">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="color:#38bdf8;">Time</th>
                        <th>Open</th><th>High</th><th>Low</th>
                        <th>Close (LTP)</th>
                        <th style="color:#a78bfa;">Price {{ $maPeriod }}MA</th>
                        <th>💹 Price Signal</th>
                        <th style="color:#38bdf8; border-left:2px solid var(--border-bright);">OI</th>
                        <th style="color:#ef4444;">OI {{ $maPeriod }}MA</th>
                        <th>📊 OI Signal</th>
                        <th>Volume</th>
                        <th>Pos</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($block['rows'] as $ri => $row)
                @php
                    $hasCross  = $row['oi_cross'] || $row['price_cross'];
                    $isLast    = $ri === count($block['rows']) - 1;
                    $priceClass = 'price-neut';
                    if((float)$row['close'] > (float)$row['open']) $priceClass = 'price-up';
                    elseif((float)$row['close'] < (float)$row['open']) $priceClass = 'price-dn';
                    $rowClass = $isLast ? 'last-row' : ($hasCross ? 'has-cross' : '');
                @endphp
                <tr class="{{ $rowClass }}">
                    <td style="color:var(--text-muted); font-size:0.68rem;">
                        {{ $ri+1 }}
                        @if($hasCross)<span style="color:var(--accent)"> ⚡</span>@endif
                        @if($isLast)<span style="color:#38bdf8;"> ★</span>@endif
                    </td>
                    <td class="time-cell-detail">{{ $row['time'] }}{{ $isLast ? ' ←' : '' }}</td>
                    <td class="price-neut">{{ number_format((float)$row['open'], 2) }}</td>
                    <td class="price-up">{{ number_format((float)$row['high'], 2) }}</td>
                    <td class="price-dn">{{ number_format((float)$row['low'], 2) }}</td>
                    <td class="{{ $priceClass }}"><strong>{{ number_format((float)$row['close'], 2) }}</strong></td>
                    <td class="ma-pr-val">{{ $row['close_ma'] !== null ? number_format($row['close_ma'], 2) : '—' }}</td>
                    <td>
                        @if(in_array($row['price_signal'], ['BULLISH','BEARISH']))
                            <span class="sig-badge {{ strtolower($row['price_signal']) }} {{ $row['price_cross'] ? 'cross' : '' }}">
                                {{ $row['price_signal'] === 'BULLISH' ? '▲' : '▼' }} {{ $row['price_signal'] }}{{ $row['price_cross'] ? ' ✕' : '' }}
                            </span>
                        @else <span class="na-val">—</span>@endif
                    </td>
                    <td class="oi-val" style="border-left:2px solid var(--border-bright);">{{ number_format($row['oi']) }}</td>
                    <td class="ma-oi-val">{{ $row['oi_ma'] !== null ? number_format($row['oi_ma']) : '—' }}</td>
                    <td>
                        @if(in_array($row['oi_signal'], ['BULLISH','BEARISH']))
                            <span class="sig-badge {{ strtolower($row['oi_signal']) }} {{ $row['oi_cross'] ? 'cross' : '' }}">
                                {{ $row['oi_signal'] === 'BULLISH' ? '▲' : '▼' }} {{ $row['oi_signal'] }}{{ $row['oi_cross'] ? ' ✕' : '' }}
                            </span>
                        @else <span class="na-val">—</span>@endif
                    </td>
                    <td style="color:var(--text-dim); font-size:0.73rem;">{{ $row['volume'] ? number_format($row['volume']) : '—' }}</td>
                    <td style="font-size:0.68rem; color:var(--text-muted);">{{ $row['strike_position'] ?? '—' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>
    </div>
    @endforeach

    <div class="legend-box">
        <strong>Detail View:</strong> ★ ← = last candle (15:15) &nbsp;|&nbsp; ⚡ = crossover event row &nbsp;|&nbsp; ✕ = cross happened THIS candle<br>
        <span class="lg-bear">▼ BEARISH OI</span> = OI > {{ $maPeriod }}MA &nbsp;|&nbsp;
        <span class="lg-bull">▲ BULLISH OI</span> = OI &lt; {{ $maPeriod }}MA (inverted logic) &nbsp;|&nbsp;
        <span class="lg-bull">▲ BULLISH Price</span> = Close > {{ $maPeriod }}MA &nbsp;|&nbsp;
        <span class="lg-bear">▼ BEARISH Price</span> = Close &lt; {{ $maPeriod }}MA<br>
        <span class="lg-oi">Blue OI</span> vs <span class="lg-ma-oi">Red OI-MA</span> &nbsp;·&nbsp;
        <span class="lg-pr">White Close</span> vs <span class="lg-ma-pr">Purple Price-MA</span>
    </div>
    @endif

    @else
    <div class="empty-state"><i class="las la-database"></i><h5>No Data Available</h5><p>No option OHLC data found yet.</p></div>
    @endif

</div>
</div>

{{-- Chart Modal --}}
<div class="modal-overlay" id="chartModal" onclick="closeChartIfOutside(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="closeChart()">✕</button>
        <div class="modal-title" id="modalTitle">Chart</div>
        <div class="modal-subtitle" id="modalSubtitle"></div>
        <div class="chart-tabs">
            <button class="chart-tab active" onclick="switchChart('oi',this)">📊 OI vs {{ $maPeriod }}MA</button>
            <button class="chart-tab" onclick="switchChart('price',this)">💹 Price vs {{ $maPeriod }}MA</button>
        </div>
        <div class="chart-canvas-wrap"><canvas id="oiChart"></canvas></div>
        <div class="legend-row" id="chartLegend">
            <div class="legend-item"><div class="legend-dot" style="background:#38bdf8;"></div> OI</div>
            <div class="legend-item"><div class="legend-dot" style="background:#ef4444;"></div> {{ $maPeriod }}-SMA</div>
        </div>
    </div>
</div>

@push('script')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let oiChartInstance  = null;
let currentChartData = null;
let currentMaPeriod  = {{ $maPeriod }};
let activeChartType  = 'oi';

function openChart(symbol, type, strike, date, maPeriod) {
    currentMaPeriod = maPeriod;
    activeChartType = 'oi';
    document.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.chart-tab')[0].classList.add('active');
    document.getElementById('modalTitle').textContent    = symbol+' '+type+' '+parseFloat(strike).toLocaleString('en-IN')+' — MA Analysis';
    document.getElementById('modalSubtitle').textContent = 'OI vs '+maPeriod+'MA  |  Close vs '+maPeriod+'MA  |  Full Day 09:15→15:15';
    document.getElementById('chartModal').classList.add('open');

    const url = `{{ route('oi-crossover.chart-data') }}?symbol=${symbol}&type=${type}&strike=${strike}&date=${date}&ma_period=${maPeriod}`;
    fetch(url).then(r=>r.json()).then(data=>{ currentChartData=data; renderChart(data,'oi',maPeriod); }).catch(console.error);
}

function switchChart(t,btn){
    document.querySelectorAll('.chart-tab').forEach(x=>x.classList.remove('active'));
    btn.classList.add('active');
    activeChartType=t;
    if(currentChartData) renderChart(currentChartData,t,currentMaPeriod);
}

function closeChart(){ document.getElementById('chartModal').classList.remove('open'); if(oiChartInstance){oiChartInstance.destroy();oiChartInstance=null;} }
function closeChartIfOutside(e){ if(e.target.id==='chartModal') closeChart(); }

function renderChart(data, type, maPeriod) {
    const ctx = document.getElementById('oiChart').getContext('2d');
    if(oiChartInstance) oiChartInstance.destroy();
    let datasets=[], legendHTML='';
    const isOi = type==='oi';

    const vals   = isOi ? data.oi    : data.close;
    const maVals = isOi ? data.oi_ma : data.close_ma;

    const crossPts = [];
    for(let i=1;i<vals.length;i++){
        const pV=vals[i-1],cV=vals[i],pM=maVals[i-1],cM=maVals[i];
        if(pM!=null&&cM!=null){
            if((pV<=pM&&cV>cM)||(pV>=pM&&cV<cM)) crossPts.push(i);
        }
    }

    const mainColor = isOi ? '#38bdf8' : '#e2e8f0';
    const maColor   = isOi ? '#ef4444' : '#a78bfa';
    const mainBg    = isOi ? 'rgba(56,189,248,0.07)' : 'rgba(226,232,240,0.06)';

    datasets = [
        {
            label: isOi ? 'OI' : 'Close',
            data: vals,
            borderColor: mainColor, backgroundColor: mainBg,
            borderWidth: 1.8, fill: true, tension: 0.3,
            pointRadius: data.labels.map((_,i) => crossPts.includes(i)?5:0),
            pointBackgroundColor: data.labels.map((_,i)=>{
                if(!crossPts.includes(i)) return 'transparent';
                const aboveMa = vals[i] > (maVals[i]||0);
                return isOi ? (aboveMa ? '#ef4444':'#10b981') : (aboveMa ? '#10b981':'#ef4444');
            }),
            pointBorderWidth:0,
        },
        {
            label: maPeriod+'-SMA',
            data: maVals,
            borderColor: maColor, backgroundColor:'transparent',
            borderWidth:1.8, fill:false, tension:0.3,
            pointRadius:0, borderDash:[5,3],
        }
    ];

    if(isOi){
        legendHTML=`<div class="legend-item"><div class="legend-dot" style="background:#38bdf8;"></div> OI</div>
        <div class="legend-item"><div class="legend-dot" style="background:#ef4444;"></div> ${maPeriod}-SMA (dashed)</div>
        <div class="legend-item" style="font-size:0.7rem; color:#ef4444;">🔴 OI crosses ABOVE MA = BEARISH</div>
        <div class="legend-item" style="font-size:0.7rem; color:#10b981;">🟢 OI crosses BELOW MA = BULLISH</div>`;
    } else {
        legendHTML=`<div class="legend-item"><div class="legend-dot" style="background:#e2e8f0;"></div> Close</div>
        <div class="legend-item"><div class="legend-dot" style="background:#a78bfa;"></div> ${maPeriod}-SMA (dashed)</div>
        <div class="legend-item" style="font-size:0.7rem; color:#10b981;">🟢 Price crosses ABOVE MA = BULLISH</div>
        <div class="legend-item" style="font-size:0.7rem; color:#ef4444;">🔴 Price crosses BELOW MA = BEARISH</div>`;
    }

    document.getElementById('chartLegend').innerHTML = legendHTML;

    oiChartInstance = new Chart(ctx, {
        type:'line', data:{labels:data.labels, datasets},
        options:{
            responsive:true, maintainAspectRatio:false,
            interaction:{mode:'index',intersect:false}, animation:{duration:250},
            plugins:{
                legend:{display:false},
                tooltip:{
                    backgroundColor:'#0f1520', borderColor:'#1e2d45', borderWidth:1,
                    titleColor:'#94a3b8', bodyColor:'#e2e8f0',
                    callbacks:{label:c=>' '+c.dataset.label+': '+(c.raw!==null?c.raw.toLocaleString('en-IN'):'—')}
                }
            },
            scales:{
                x:{grid:{color:'rgba(30,45,69,0.5)'},ticks:{color:'#64748b',font:{size:10}}},
                y:{grid:{color:'rgba(30,45,69,0.5)'},ticks:{
                    color:'#64748b', font:{size:10},
                    callback: v => isOi ? (v/1000).toFixed(0)+'k' : '₹'+v.toLocaleString('en-IN')
                }}
            }
        }
    });
}
</script>
@endpush
@endsection