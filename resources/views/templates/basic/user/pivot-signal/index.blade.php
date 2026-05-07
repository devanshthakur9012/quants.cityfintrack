@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════════════════════════
   PIVOT SIGNAL — MAIN STYLES
═══════════════════════════════════════════════════════════════════════ */
.page-header {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white; padding: 18px 24px; border-radius: 12px;
    margin-bottom: 20px; box-shadow: 0 4px 15px rgba(17,153,142,0.4);
}
.page-header h4 { color: white; margin: 0; }
.page-header p  { color: rgba(255,255,255,0.85); margin: 4px 0 0; font-size: 12px; }

/* ── Filter Bar ── */
.filter-bar {
    background: linear-gradient(135deg,#667eea,#764ba2);
    padding: 12px 20px; border-radius: 12px; margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(102,126,234,0.4);
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.filter-bar label { color: rgba(255,255,255,0.7) !important; font-size: 11px; font-weight: 700; margin: 0; }
.sym-select {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.35);
    color: white; border-radius: 8px; padding: 6px 12px; font-size: 12px;
    font-weight: 700; cursor: pointer; outline: none; min-width: 180px;
}
.sym-select option { background: #2d2d5e; color: white; }
.sym-select:focus  { border-color: rgba(255,255,255,0.7); }
.date-input-wrap { display: flex; align-items: center; gap: 5px; }
.date-input-wrap input[type="date"] {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px; color: white; padding: 5px 10px; font-size: 12px;
    font-weight: 600; cursor: pointer; outline: none;
}
.date-input-wrap input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
.date-nav-btn {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
    color: white; border-radius: 6px; width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 14px; font-weight: 700; transition: .15s;
}
.date-nav-btn:hover { background: rgba(255,255,255,0.28); }
.date-nav-btn.today-btn { width: auto; padding: 0 10px; font-size: 10px; }
.btn-load {
    background: white; color: #667eea; border: none; border-radius: 8px;
    padding: 7px 22px; font-weight: 800; font-size: 13px; cursor: pointer;
}
.btn-load:hover { background: #f0f0ff; }
.auto-btn {
    background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 700; cursor: pointer;
}
.date-badge { font-size: 10px; font-weight: 700; padding: 2px 9px; border-radius: 10px; }
.badge-today { background: rgba(0,255,136,0.2); color: #00ff88; border: 1px solid rgba(0,255,136,0.3); }
.badge-hist  { background: rgba(255,193,7,0.2);  color: #ffc107; border: 1px solid rgba(255,193,7,0.3); }
.last-upd  { font-size: 10px; color: rgba(255,255,255,0.5); margin-left: auto; }
.divider-v { width: 1px; height: 24px; background: rgba(255,255,255,0.15); flex-shrink: 0; }

/* ══════════════════════════════════════════════════════════════════════
   TRAP FILTER PANEL
══════════════════════════════════════════════════════════════════════ */
.trap-filter-wrap {
    border: 1px solid rgba(156,39,176,0.35);
    border-radius: 12px; overflow: hidden;
    margin-bottom: 20px;
}
.trap-filter-header {
    background: linear-gradient(135deg, rgba(156,39,176,0.18), rgba(103,26,122,0.22));
    padding: 11px 18px;
    display: flex; align-items: center; gap: 10px;
    cursor: pointer; user-select: none;
}
.trap-filter-header .trap-title {
    color: #ce93d8; font-size: 12px; font-weight: 800;
    letter-spacing: .5px; text-transform: uppercase; flex: 1;
}
.trap-filter-header .trap-chevron { color: #ce93d8; font-size: 13px; transition: .2s; }
.trap-filter-controls {
    background: rgba(156,39,176,0.06);
    padding: 14px 18px;
    display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap;
}
.trap-ctrl-group { display: flex; flex-direction: column; gap: 4px; }
.trap-ctrl-group label {
    font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.5);
    text-transform: uppercase; letter-spacing: .4px; margin: 0;
}
.trap-ctrl-group input[type="date"],
.trap-ctrl-group select {
    background: rgba(255,255,255,0.08); border: 1px solid rgba(156,39,176,0.4);
    border-radius: 8px; color: white; padding: 6px 10px;
    font-size: 12px; font-weight: 600; outline: none; min-width: 140px; cursor: pointer;
}
.trap-ctrl-group input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
.trap-ctrl-group select option { background: #1a0a2e; color: white; }
.btn-find-traps {
    background: linear-gradient(135deg, #9c27b0, #7b1fa2);
    color: white; border: none; border-radius: 8px;
    padding: 8px 22px; font-size: 12px; font-weight: 800;
    cursor: pointer; white-space: nowrap; align-self: flex-end;
}
.btn-find-traps:hover { opacity: .88; }
.trap-scan-status { font-size: 10px; color: rgba(255,255,255,.4); align-self: center; }

/* Trap Results */
.trap-results-area {
    border-top: 1px solid rgba(156,39,176,0.2);
    padding: 14px 18px;
    display: none;
}
.trap-stats-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.trap-stat-card {
    background: rgba(156,39,176,0.12); border: 1px solid rgba(156,39,176,0.3);
    border-radius: 8px; padding: 7px 16px; min-width: 90px; text-align: center;
}
.trap-stat-card strong { color: #ce93d8; font-size: 18px; font-weight: 800; display: block; line-height: 1.2; }
.trap-stat-card small  { color: rgba(255,255,255,0.4); font-size: 9px; text-transform: uppercase; letter-spacing: .3px; }
.trap-result-scroll { overflow-x: auto; }
.trap-result-table { width: 100%; border-collapse: collapse; min-width: 860px; }
.trap-result-table thead th {
    padding: 7px 10px; text-align: left; font-size: 9px; font-weight: 700;
    text-transform: uppercase; color: #ce93d8; letter-spacing: .4px;
    background: rgba(0,0,0,0.3); border-bottom: 1px solid rgba(156,39,176,0.3);
    white-space: nowrap;
}
.trap-result-table tbody td {
    padding: 8px 10px; font-size: 11px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.75); vertical-align: middle; white-space: nowrap;
}
.trap-result-table tbody tr:hover td { background: rgba(156,39,176,0.07); }
.trap-no-data   { text-align: center; padding: 40px; color: rgba(255,255,255,0.3); font-size: 12px; }
.trap-error-msg { text-align: center; padding: 20px; color: #ff6b6b; font-size: 12px; }
.trap-loading   { display: flex; align-items: center; gap: 10px; justify-content: center; padding: 30px; color: rgba(255,255,255,.4); font-size: 12px; }

/* ── Main Card & Table ── */
.main-card {
    border-radius: 12px; overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
}
.table-scroll { overflow-x: auto; }
.sig-table { width: 100%; border-collapse: collapse; min-width: 2100px; }

/* ── Headers ── */
.sig-table thead tr.hdr-group th {
    padding: 10px 10px 6px; text-align: center; font-size: 10px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .5px; white-space: nowrap;
    background: rgba(0,0,0,0.45); border-bottom: none;
}
.sig-table thead tr.hdr-cols th {
    padding: 6px 10px 9px; text-align: center; font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .3px; white-space: nowrap;
    background: rgba(0,0,0,0.35); color: rgba(255,255,255,0.55);
    border-bottom: 2px solid rgba(255,255,255,0.08);
}
.hdr-meta  { color: rgba(255,255,255,0.5) !important; }
.hdr-ce    { color: #51cf66 !important; }
.hdr-pe    { color: #ff6b6b !important; }
.hdr-oi1hr { color: #f9ca24 !important; }
.sub-ce    { color: #51cf66 !important; }
.sub-pe    { color: #ff6b6b !important; }
.sub-oi1hr { color: #f9ca24 !important; }

/* ── Borders / separators ── */
.sep-ce       { border-left: 2px solid rgba(81,207,102,0.35) !important; }
.sep-pe       { border-left: 2px solid rgba(255,107,107,0.35) !important; }
.sep-match-ce { border-left: 1px dashed rgba(81,207,102,0.25) !important; }
.sep-match-pe { border-left: 1px dashed rgba(255,107,107,0.25) !important; }
.sep-oi1hr    { border-left: 2px solid rgba(249,202,36,0.4) !important; }
.sep-mmtrap   { border-left: 2px solid rgba(156,39,176,0.4) !important; }

/* ── Body cells ── */
.sig-table tbody td {
    padding: 8px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle; white-space: nowrap;
}
.sig-table tbody tr:hover { background: rgba(255,255,255,0.05) !important; }
.sym-even { background: rgba(255,255,255,0.01); }
.sym-odd  { background: rgba(0,0,0,0.10); }

/* ── Cell styles ── */
.c-num    { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.35); }
.c-time   { font-size: 12px; font-weight: 800; color: #00d2ff; }
.c-strike { font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 600; }
.c-atm    { font-size: 10px; color: #f9ca24; font-weight: 700; }
.c-o      { color: rgba(255,255,255,.55); }
.c-h      { color: #ff9f7f; font-weight: 600; }
.c-l      { color: #7fff9f; font-weight: 600; }
.c-close  { color: #17a2b8; font-weight: 700; }
.c-pp     { color: #00d2ff; font-weight: 800; }
.c-s1     { color: #51cf66; font-weight: 800; }
.c-r1     { color: #ff6b6b; font-weight: 800; }
.badge-sym {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 10px; font-weight: 800;
    background: rgba(0,210,255,0.15); color: #00d2ff;
    border: 1px solid rgba(0,210,255,0.25);
}
.badge-atm {
    display: inline-block; padding: 1px 6px; border-radius: 5px;
    font-size: 9px; font-weight: 700;
    background: rgba(249,202,36,0.15); color: #f9ca24;
    border: 1px solid rgba(249,202,36,0.25);
    margin-top: 2px;
}

/* ── Match pills ── */
.match-yes {
    display: inline-block; background: rgba(40,167,69,0.25); color: #51cf66;
    border: 1px solid rgba(40,167,69,0.5); border-radius: 6px;
    padding: 2px 7px; font-size: 9px; font-weight: 800; letter-spacing: .3px;
}
.match-no {
    display: inline-block; background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.08); border-radius: 6px;
    padding: 2px 7px; font-size: 9px; font-weight: 600;
}

/* ── OI Sentiment badges ── */
.oi-bullish {
    display: inline-block; background: rgba(40,167,69,0.25); color: #51cf66;
    border: 1px solid rgba(40,167,69,0.5); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; letter-spacing: .3px;
}
.oi-bearish {
    display: inline-block; background: rgba(220,53,69,0.25); color: #ff6b6b;
    border: 1px solid rgba(220,53,69,0.5); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; letter-spacing: .3px;
}
.oi-neutral {
    display: inline-block; background: rgba(108,117,125,0.2); color: rgba(255,255,255,0.4);
    border: 1px solid rgba(255,255,255,0.1); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 600;
}
.oi-na { display: inline-block; color: rgba(255,255,255,0.2); font-size: 9px; font-weight: 600; }

/* ── Strength badges ── */
.str-very-strong {
    display: inline-block; background: rgba(255,71,87,0.25); color: #ff4757;
    border: 1px solid rgba(255,71,87,0.5); border-radius: 5px;
    padding: 2px 7px; font-size: 9px; font-weight: 800; letter-spacing: .3px; white-space: nowrap;
}
.str-strong {
    display: inline-block; background: rgba(255,165,2,0.22); color: #ffa502;
    border: 1px solid rgba(255,165,2,0.45); border-radius: 5px;
    padding: 2px 7px; font-size: 9px; font-weight: 800; letter-spacing: .3px; white-space: nowrap;
}
.str-moderate {
    display: inline-block; background: rgba(0,210,255,0.15); color: #00d2ff;
    border: 1px solid rgba(0,210,255,0.35); border-radius: 5px;
    padding: 2px 7px; font-size: 9px; font-weight: 700; letter-spacing: .2px; white-space: nowrap;
}
.str-weak {
    display: inline-block; background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.3);
    border: 1px solid rgba(255,255,255,0.1); border-radius: 5px;
    padding: 2px 7px; font-size: 9px; font-weight: 600; white-space: nowrap;
}
.str-na   { color: rgba(255,255,255,0.2); font-size: 9px; }
.str-diff { font-size: 8px; color: rgba(255,255,255,0.35); margin-top: 2px; }

/* ── OI section bg ── */
.bg-oi1hr  { background: rgba(249,202,36,0.04) !important; }
.bg-mmtrap { background: rgba(156,39,176,0.04) !important; }
.hdr-mmtrap { color: #ce93d8 !important; }
.sub-mmtrap { color: #ce93d8 !important; }

/* ── MM Trap badges ── */
.mm-call-trap {
    display: inline-block; background: rgba(255,71,87,0.22); color: #ff4757;
    border: 1px solid rgba(255,71,87,0.5); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; letter-spacing: .3px; white-space: nowrap;
}
.mm-put-trap {
    display: inline-block; background: rgba(255,165,2,0.22); color: #ffa502;
    border: 1px solid rgba(255,165,2,0.5); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; letter-spacing: .3px; white-space: nowrap;
}
.mm-no-trap { color: rgba(255,255,255,0.18); font-size: 9px; }

/* ── Misc ── */
.summary-strip { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.spinner { width:36px; height:36px; border:4px solid rgba(255,255,255,0.12); border-top:4px solid #00d2ff; border-radius:50%; animation:spin 1s linear infinite; }
.spinner-sm { width:18px; height:18px; border:3px solid rgba(156,39,176,0.3); border-top:3px solid #ce93d8; border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.loading-wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px; }
.no-data { text-align:center; padding:60px; color:rgba(255,255,255,0.3); font-size:13px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── Page Header ── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>&#9889; Pivot Signal
                    <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;margin-left:6px;">ATM &middot; 1hr Candle</span>
                </h4>
                <p>
                    PP = (H+L+C)/3 &nbsp;&middot;&nbsp; S1 = 2P&minus;H &nbsp;&middot;&nbsp; R1 = 2P&minus;L &nbsp;&middot;&nbsp;
                    <strong style="color:#b2ffda;">S1 &rarr; BUY</strong> &nbsp;&middot;&nbsp;
                    <strong style="color:#ffb2b2;">R1 &rarr; SELL</strong> &nbsp;&middot;&nbsp;
                    <span style="color:#f9ca24;">&#9679; ATM = frozen at 09:15 FUT close</span>
                </p>
            </div>
            <a href="{{ route('pivot-signal.config.index') }}" class="btn btn-light btn-sm">&#9881; Configs</a>
        </div>
    </div>

    {{-- ── Main Filter Bar ── --}}
    <div class="filter-bar">
        <label>DATE:</label>
        <div class="date-input-wrap">
            <button class="date-nav-btn" onclick="shiftDate(-1)" title="Previous day">&#8249;</button>
            <input type="date" id="date-picker"
                value="{{ now()->toDateString() }}"
                max="{{ now()->toDateString() }}"
                onchange="loadData()">
            <button class="date-nav-btn" onclick="shiftDate(1)" title="Next day">&#8250;</button>
            <button class="date-nav-btn today-btn" onclick="goToday()">Today</button>
            <span id="date-badge"></span>
        </div>
        <div class="divider-v"></div>
        <label>SYMBOL:</label>
        <select id="sym-select" class="sym-select" onchange="loadData()">
            <option value="ALL">&#8212; All Symbols &#8212;</option>
        </select>
        <button class="btn-load" onclick="loadData()">&#8635; Load</button>
        <button class="auto-btn" id="auto-btn" onclick="toggleAuto()">&#9654; Auto 60s</button>
        <span id="auto-tag" style="font-size:10px;color:rgba(255,255,255,0.5);"></span>
        <span class="last-upd" id="last-upd"></span>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         MM TRAP FILTER PANEL — date range search (NEW)
         Completely independent of main table. Zero impact on existing logic.
    ══════════════════════════════════════════════════════════════════════ --}}
    <div class="trap-filter-wrap">

        {{-- Header toggle --}}
        <div class="trap-filter-header" onclick="toggleTrapPanel()">
            <span style="font-size:15px;">&#9888;</span>
            <span class="trap-title">&#128202; MM Trap Filter &mdash; Date Range Search</span>
            <span class="trap-chevron" id="trap-chevron">&#9650;</span>
        </div>

        {{-- Controls --}}
        <div class="trap-filter-controls" id="trap-filter-controls">
            <div class="trap-ctrl-group">
                <label>Start Date</label>
                <input type="date" id="trap-start"
                    value="{{ now()->subDays(7)->toDateString() }}"
                    max="{{ now()->toDateString() }}">
            </div>
            <div class="trap-ctrl-group">
                <label>End Date</label>
                <input type="date" id="trap-end"
                    value="{{ now()->toDateString() }}"
                    max="{{ now()->toDateString() }}">
            </div>
            <div class="trap-ctrl-group">
                <label>Symbol</label>
                <select id="trap-sym">
                    <option value="ALL">&#8212; All Symbols &#8212;</option>
                    @foreach(\App\Http\Controllers\User\PivotSignalController::ALL_SYMBOLS as $sym)
                        <option value="{{ $sym }}">{{ $sym }}</option>
                    @endforeach
                </select>
            </div>
            <div class="trap-ctrl-group">
                <label>Trap Type</label>
                <select id="trap-type">
                    <option value="ALL">All Traps</option>
                    <option value="CE_TRAP">&#128308; CE Trap only (Bearish)</option>
                    <option value="PE_TRAP">&#128992; PE Trap only (Bullish)</option>
                </select>
            </div>
            <button class="btn-find-traps" onclick="runTrapFilter()">&#9889; Find Traps</button>
            <span class="trap-scan-status" id="trap-scan-status"></span>
        </div>

        {{-- Results area (hidden until search runs) --}}
        <div class="trap-results-area" id="trap-results-area"></div>

    </div>
    {{-- / MM TRAP FILTER PANEL --}}

    <div class="summary-strip" id="summary-strip" style="display:none;"></div>

    {{-- ── Main Signal Table ── --}}
    <div class="main-card">
        <div class="table-scroll">
            <table class="sig-table">
                <thead>
                    <tr class="hdr-group">
                        <th colspan="4" class="hdr-meta">Meta</th>
                        <th colspan="6" class="hdr-oi1hr sep-oi1hr">&#9889; 1hr OI Sentiment<br><span style="font-size:8px;font-weight:400;opacity:.7;">Latest candle &mdash; CE vs PE OI</span></th>
                        <th colspan="3" class="hdr-mmtrap sep-mmtrap">&#9888; MM Trap<br><span style="font-size:8px;font-weight:400;opacity:.7;">OI Wall breakout</span></th>
                        <th colspan="10" class="hdr-ce sep-ce">&#128200; CE &mdash; ATM (own pivot)</th>
                        <th colspan="10" class="hdr-pe sep-pe">&#128201; PE &mdash; ATM (own pivot)</th>
                    </tr>
                    <tr class="hdr-cols">
                        <th class="hdr-meta">#</th>
                        <th class="hdr-meta">Time</th>
                        <th class="hdr-meta">Symbol</th>
                        <th class="hdr-meta">ATM<br><span style="font-size:8px;opacity:.6;font-weight:400;">Frozen 09:15</span></th>

                        <th class="sub-oi1hr sep-oi1hr">Signal</th>
                        <th class="sub-oi1hr">CE%<br><span style="font-size:8px;opacity:.6;font-weight:400;">OI Chg</span></th>
                        <th class="sub-oi1hr">PE%<br><span style="font-size:8px;opacity:.6;font-weight:400;">OI Chg</span></th>
                        <th class="sub-oi1hr">Strength<br><span style="font-size:8px;opacity:.6;font-weight:400;">|CE%&minus;PE%|</span></th>
                        <th class="sub-oi1hr">Prev Signal<br><span style="font-size:8px;opacity:.6;font-weight:400;">Prior candle</span></th>
                        <th class="sub-oi1hr">Decision<br><span style="font-size:8px;opacity:.6;font-weight:400;">Hold / Exit</span></th>

                        <th class="sub-mmtrap sep-mmtrap">Type</th>
                        <th class="sub-mmtrap">Walls<br><span style="font-size:8px;opacity:.6;font-weight:400;">Call / Put</span></th>
                        <th class="sub-mmtrap">Detail</th>

                        <th class="sub-ce sep-ce">Strike</th>
                        <th class="sub-ce">O</th>
                        <th class="sub-ce">H</th>
                        <th class="sub-ce">L</th>
                        <th class="sub-ce">C</th>
                        <th class="sub-ce">PP</th>
                        <th class="sub-ce" style="color:#51cf66 !important;font-weight:900;">S1 &#129001;</th>
                        <th class="sub-ce" style="color:#ff9f7f !important;font-weight:900;">R1 &#128997;</th>
                        <th class="sub-ce sep-match-ce" style="color:#51cf66 !important;">S1 Match<br><span style="font-size:8px;opacity:.6;font-weight:400;">S1 &ge; Low</span></th>
                        <th class="sub-ce" style="color:#ff9f7f !important;">R1 Match<br><span style="font-size:8px;opacity:.6;font-weight:400;">R1 &ge; High</span></th>

                        <th class="sub-pe sep-pe">Strike</th>
                        <th class="sub-pe">O</th>
                        <th class="sub-pe">H</th>
                        <th class="sub-pe">L</th>
                        <th class="sub-pe">C</th>
                        <th class="sub-pe">PP</th>
                        <th class="sub-pe" style="color:#51cf66 !important;font-weight:900;">S1 &#129001;</th>
                        <th class="sub-pe" style="color:#ff6b6b !important;font-weight:900;">R1 &#128997;</th>
                        <th class="sub-pe sep-match-pe" style="color:#51cf66 !important;">S1 Match<br><span style="font-size:8px;opacity:.6;font-weight:400;">S1 &ge; Low</span></th>
                        <th class="sub-pe" style="color:#ff6b6b !important;">R1 Match<br><span style="font-size:8px;opacity:.6;font-weight:400;">R1 &ge; High</span></th>
                    </tr>
                </thead>
                <tbody id="sig-tbody">
                    <tr><td colspan="30">
                        <div class="loading-wrap">
                            <div class="spinner"></div>
                            <div style="color:white;margin-top:14px;font-size:13px;">Loading signals...</div>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════════════
//  MAIN SIGNAL TABLE — unchanged from original
// ═══════════════════════════════════════════════════════════════════════
let autoTimer        = null;
let availableSymbols = [];
const todayStr       = '{{ now()->toDateString() }}';

$(document).ready(function () { loadData(); });

function getSelectedDate() { return document.getElementById('date-picker').value; }
function getSelectedSym()  { return document.getElementById('sym-select').value; }

function shiftDate(days) {
    const picker = document.getElementById('date-picker');
    const d = new Date(picker.value);
    d.setDate(d.getDate() + days);
    const s = d.toISOString().split('T')[0];
    if (s > todayStr) return;
    picker.value = s;
    loadData();
}

function goToday() {
    document.getElementById('date-picker').value = todayStr;
    loadData();
}

function updateDateBadge(isToday) {
    const el = document.getElementById('date-badge');
    el.innerHTML = isToday
        ? '<span class="date-badge badge-today">&#9679; Live</span>'
        : '<span class="date-badge badge-hist">&#128197; Historical</span>';
}

function rebuildSymDropdown(symbols) {
    if (JSON.stringify(availableSymbols) === JSON.stringify(symbols)) return;
    availableSymbols = symbols;
    const sel  = document.getElementById('sym-select');
    const prev = sel.value;
    sel.innerHTML = '<option value="ALL">&#8212; All Symbols &#8212;</option>';
    symbols.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

function toggleAuto() {
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        $('#auto-btn').text('\u25b6 Auto 60s'); $('#auto-tag').text('');
    } else {
        autoTimer = setInterval(loadData, 60000);
        $('#auto-btn').text('\u25a0 Stop');
        $('#auto-tag').css('color','#51cf66').text('\u25cf live');
        loadData();
    }
}

function loadData() {
    const date = getSelectedDate();
    const sym  = getSelectedSym();

    if (date !== todayStr && autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        $('#auto-btn').text('\u25b6 Auto 60s'); $('#auto-tag').text('');
    }

    $('#sig-tbody').html('<tr><td colspan="30"><div class="loading-wrap"><div class="spinner"></div><div style="color:white;margin-top:12px;font-size:13px;">Fetching ' + date + '&hellip;</div></div></td></tr>');
    $('#summary-strip').hide();

    $.ajax({
        url : '{{ route("pivot-signal.signals") }}',
        data: { symbol: sym, date: date },
        success(res) {
            updateDateBadge(res.is_today);
            if (res.available_symbols && res.available_symbols.length) {
                rebuildSymDropdown(res.available_symbols);
            }
            if (!res.success || !res.data || !res.data.length) {
                $('#sig-tbody').html('<tr><td colspan="30"><div class="no-data"><i class="fas fa-calendar-times" style="font-size:2.5rem;opacity:.3;"></i><p style="margin-top:14px;">' + (res.message || 'No data for ' + date) + '</p><small style="color:rgba(255,255,255,.2);">Check if market was open on this date.</small></div></td></tr>');
                return;
            }
            renderTable(res.data);
            $('#last-upd').text('Updated: ' + new Date().toLocaleTimeString());
        },
        error(xhr) {
            $('#sig-tbody').html('<tr><td colspan="30"><div class="no-data">\u26a0 ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error') + '</div></td></tr>');
        }
    });
}

// ── OI Sentiment badge builders ────────────────────────────────────────
function oiSentimentBadge(sentiment) {
    if (!sentiment || sentiment === 'N/A') return '<span class="oi-na">&mdash;</span>';
    if (sentiment === 'BULLISH') return '<span class="oi-bullish">&#129033; BULLISH</span>';
    if (sentiment === 'BEARISH') return '<span class="oi-bearish">&#129035; BEARISH</span>';
    return '<span class="oi-neutral">&#9679; NEUTRAL</span>';
}

function oiPctCell(pct) {
    if (pct === undefined || pct === null) return '<span style="color:rgba(255,255,255,.2);font-size:9px;">&mdash;</span>';
    const v   = Number(pct);
    const cls = v > 0 ? 'color:#51cf66;' : v < 0 ? 'color:#ff6b6b;' : 'color:rgba(255,255,255,.35);';
    const pfx = v > 0 ? '+' : '';
    return `<strong style="${cls}font-size:10px;">${pfx}${v.toFixed(2)}%</strong>`;
}

function strengthBadge(strength, difference) {
    if (!strength || strength === 'N/A') return '<span class="str-na">&mdash;</span>';
    let cls = 'str-weak', icon = '';
    if (strength === 'Very Strong Signal') { cls = 'str-very-strong'; icon = '&#128293; '; }
    else if (strength === 'Strong Signal') { cls = 'str-strong';      icon = '&#9889; '; }
    else if (strength === 'Moderate Signal') { cls = 'str-moderate';  icon = '&#9733; '; }
    const diffStr = (difference !== undefined && difference !== null)
        ? '<div class="str-diff">&Delta; ' + Number(difference).toFixed(2) + '%</div>' : '';
    return `<div>${icon}<span class="${cls}">${strength.replace(' Signal','')}</span>${diffStr}</div>`;
}

function mmTrapBadge(mm) {
    if (!mm || !mm.type) return '<span class="mm-no-trap">&mdash;</span>';
    if (mm.type === 'CE_TRAP') {
        const str = mm.strength === 'STRONG' ? '&#128308;&#128308;' : '&#128308;';
        return `<span class="mm-call-trap">${str} CE TRAP<br><span style="font-size:8px;opacity:.8;">${mm.strength || ''}</span></span>`;
    }
    if (mm.type === 'PE_TRAP') {
        const str = mm.strength === 'STRONG' ? '&#128992;&#128992;' : '&#128992;';
        return `<span class="mm-put-trap">${str} PE TRAP<br><span style="font-size:8px;opacity:.8;">${mm.strength || ''}</span></span>`;
    }
    return '<span class="mm-no-trap">&mdash;</span>';
}

function mmWallsCell(mm) {
    if (!mm || (!mm.ce_oi_pct && !mm.put_oi_pct))
        return '<span style="color:rgba(255,255,255,.18);font-size:9px;">&mdash;</span>';
    const cePct = mm.ce_oi_pct != null
        ? `<span style="color:#ff6b6b;font-size:10px;font-weight:700;">CE: ${mm.ce_oi_pct > 0 ? '+' : ''}${mm.ce_oi_pct}%</span>` : '';
    const pePct = mm.put_oi_pct != null
        ? `<span style="color:#51cf66;font-size:10px;font-weight:700;">PE: ${mm.put_oi_pct > 0 ? '+' : ''}${mm.put_oi_pct}%</span>` : '';
    const diff  = mm.diff != null
        ? `<span style="font-size:8px;color:rgba(255,255,255,.35);">Δ${mm.diff}%</span>` : '';
    return [cePct, pePct, diff].filter(Boolean).join('<br>');
}

function mmDetailCell(mmTrap) {
    if (!mmTrap || !mmTrap.detail)
        return '<span style="color:rgba(255,255,255,.18);font-size:9px;">&mdash;</span>';
    return '<span style="font-size:9px;color:rgba(255,255,255,.5);">' + mmTrap.detail + '</span>';
}

// ── Main table renderer ────────────────────────────────────────────────
function renderTable(dataArr) {
    let rows   = '';
    let rowNum = 1;

    dataArr.forEach(function(d, si) {
        const signals = d.signals || [];
        if (!signals.length) return;

        const times = {};
        signals.forEach(function(s) {
            if (!times[s.time]) times[s.time] = { ce: null, pe: null };
            times[s.time][s.type.toLowerCase()] = s;
        });

        const zebraClass = si % 2 === 0 ? 'sym-even' : 'sym-odd';
        const atmStrike  = d.atm_strike ? n(d.atm_strike) : '&mdash;';

        Object.entries(times).forEach(function([time, row]) {
            const ce = row.ce;
            const pe = row.pe;

            const h1 = (ce && ce.hourly_oi) ? ce.hourly_oi : ((pe && pe.hourly_oi) ? pe.hourly_oi : {});
            const mm = (ce && ce.mm_trap)   ? ce.mm_trap   : ((pe && pe.mm_trap)   ? pe.mm_trap   : null);

            const mmType   = mmTrapBadge(mm);
            const mmWalls  = mmWallsCell(mm);
            const mmDetail = mmDetailCell(mm);
            const mmTitle  = (mm && mm.detail) ? ` title="${mm.detail}"` : '';

            const h1Signal   = oiSentimentBadge(h1.signal);
            const h1CePct    = oiPctCell(h1.ce_oi_pct);
            const h1PePct    = oiPctCell(h1.pe_oi_pct);
            const h1Strength = strengthBadge(h1.strength, h1.difference);
            const h1Title    = h1.condition ? ` title="${h1.condition} | ${h1.reason || ''}"` : '';

            const ceS1Match = ce ? (ce.s1_match !== undefined ? ce.s1_match : ce.S1 >= ce.low)  : null;
            const ceR1Match = ce ? (ce.r1_match !== undefined ? ce.r1_match : ce.R1 >= ce.high) : null;
            const peS1Match = pe ? (pe.s1_match !== undefined ? pe.s1_match : pe.S1 >= pe.low)  : null;
            const peR1Match = pe ? (pe.r1_match !== undefined ? pe.r1_match : pe.R1 >= pe.high) : null;

            const ceCells = ce
                ? '<td class="sep-ce c-strike">&#8377;' + n(ce.strike) + '</td>'
                + '<td class="c-o">&#8377;'     + n(ce.open)  + '</td>'
                + '<td class="c-h">&#8377;'     + n(ce.high)  + '</td>'
                + '<td class="c-l">&#8377;'     + n(ce.low)   + '</td>'
                + '<td class="c-close">&#8377;' + n(ce.close) + '</td>'
                + '<td class="c-pp">&#8377;'    + n(ce.PP)    + '</td>'
                + '<td class="c-s1" title="CE S1 - BUY">&#8377;'  + n(ce.S1) + '</td>'
                + '<td class="c-r1" title="CE R1 - SELL">&#8377;' + n(ce.R1) + '</td>'
                + '<td class="sep-match-ce">' + matchPill(ceS1Match) + '</td>'
                + '<td>'                       + matchPill(ceR1Match) + '</td>'
                : '<td colspan="10" class="sep-ce" style="color:rgba(255,255,255,.12);font-size:9px;">&mdash; no CE data &mdash;</td>';

            const peCells = pe
                ? '<td class="sep-pe c-strike">&#8377;' + n(pe.strike) + '</td>'
                + '<td class="c-o">&#8377;'     + n(pe.open)  + '</td>'
                + '<td class="c-h">&#8377;'     + n(pe.high)  + '</td>'
                + '<td class="c-l">&#8377;'     + n(pe.low)   + '</td>'
                + '<td class="c-close">&#8377;' + n(pe.close) + '</td>'
                + '<td class="c-pp">&#8377;'    + n(pe.PP)    + '</td>'
                + '<td class="c-s1" title="PE S1 - BUY">&#8377;'  + n(pe.S1) + '</td>'
                + '<td class="c-r1" title="PE R1 - SELL">&#8377;' + n(pe.R1) + '</td>'
                + '<td class="sep-match-pe">' + matchPill(peS1Match) + '</td>'
                + '<td>'                       + matchPill(peR1Match) + '</td>'
                : '<td colspan="10" class="sep-pe" style="color:rgba(255,255,255,.12);font-size:9px;">&mdash; no PE data &mdash;</td>';

            const prevSignal      = (ce && ce.prev_signal) ? ce.prev_signal : ((pe && pe.prev_signal) ? pe.prev_signal : 'N/A');
            const decision        = (ce && ce.decision)    ? ce.decision    : ((pe && pe.decision)    ? pe.decision    : 'N/A');
            const prevSignalBadge = oiSentimentBadge(prevSignal);
            const decisionBadge   = decision === 'HOLD'
                ? '<span style="background:rgba(0,210,255,0.15);color:#00d2ff;border:1px solid rgba(0,210,255,0.35);border-radius:6px;padding:3px 8px;font-size:10px;font-weight:800;">&#9654; HOLD</span>'
                : decision === 'EXIT'
                ? '<span style="background:rgba(255,71,87,0.2);color:#ff4757;border:1px solid rgba(255,71,87,0.5);border-radius:6px;padding:3px 8px;font-size:10px;font-weight:800;">&#9888; EXIT</span>'
                : '<span style="color:rgba(255,255,255,.2);font-size:9px;">&mdash;</span>';

            const oi1hrCells = `
                <td class="sep-oi1hr bg-oi1hr"${h1Title}>${h1Signal}</td>
                <td class="bg-oi1hr">${h1CePct}</td>
                <td class="bg-oi1hr">${h1PePct}</td>
                <td class="bg-oi1hr">${h1Strength}</td>
                <td class="bg-oi1hr">${prevSignalBadge}</td>
                <td class="bg-oi1hr">${decisionBadge}</td>`;

            const mmTrapCells = `
                <td class="sep-mmtrap bg-mmtrap"${mmTitle}>${mmType}</td>
                <td class="bg-mmtrap">${mmWalls}</td>
                <td class="bg-mmtrap">${mmDetail}</td>`;

            rows += '<tr class="' + zebraClass + '">'
                + '<td class="c-num">' + rowNum++ + '</td>'
                + '<td class="c-time">' + time + '</td>'
                + '<td><span class="badge-sym">' + d.symbol + '</span></td>'
                + '<td class="c-atm"><span class="badge-atm">&#8377;' + atmStrike + '</span></td>'
                + oi1hrCells
                + mmTrapCells
                + ceCells
                + peCells
                + '</tr>';
        });
    });

    if (!rows) {
        rows = '<tr><td colspan="30"><div class="no-data">No candle data found.</div></td></tr>';
    }
    $('#sig-tbody').html(rows);
}

function matchPill(matched) {
    if (matched === null || matched === undefined)
        return '<span style="color:rgba(255,255,255,.15);font-size:9px;">&mdash;</span>';
    return matched
        ? '<span class="match-yes">\u2713 YES</span>'
        : '<span class="match-no">\u2717 NO</span>';
}

function n(v) {
    if (v == null || v === '' || v === undefined) return '\u2014';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ═══════════════════════════════════════════════════════════════════════
//  MM TRAP FILTER — completely self-contained, calls dedicated API
// ═══════════════════════════════════════════════════════════════════════

let trapPanelOpen = true;

function toggleTrapPanel() {
    trapPanelOpen = !trapPanelOpen;
    const body    = document.getElementById('trap-filter-controls');
    const results = document.getElementById('trap-results-area');
    const chevron = document.getElementById('trap-chevron');
    body.style.display    = trapPanelOpen ? 'flex' : 'none';
    results.style.display = 'none';
    chevron.innerHTML     = trapPanelOpen ? '&#9650;' : '&#9660;';
}

function runTrapFilter() {
    const startDate  = document.getElementById('trap-start').value;
    const endDate    = document.getElementById('trap-end').value;
    const sym        = document.getElementById('trap-sym').value;
    const trapType   = document.getElementById('trap-type').value;
    const statusEl   = document.getElementById('trap-scan-status');
    const resultsEl  = document.getElementById('trap-results-area');

    // Basic validation
    if (!startDate || !endDate) {
        alert('Please select both start and end dates.');
        return;
    }
    if (startDate > endDate) {
        alert('Start date must be on or before end date.');
        return;
    }

    // Show loading
    resultsEl.style.display = 'block';
    resultsEl.innerHTML = `
        <div class="trap-loading">
            <div class="spinner-sm"></div>
            <span id="trap-progress-text">Scanning date range for MM Traps&hellip;</span>
        </div>`;
    statusEl.textContent = '';

    $.ajax({
        url    : '{{ route("pivot-signal.traps") }}',
        method : 'GET',
        data   : {
            start_date : startDate,
            end_date   : endDate,
            symbol     : sym,
            trap_type  : trapType,
        },
        success: function(res) {
            statusEl.textContent = '';
            if (!res.success) {
                resultsEl.innerHTML = `<div class="trap-error-msg">&#9888; ${res.message || 'Server error.'}</div>`;
                return;
            }
            renderTrapResults(res.data || [], res.meta || {});
        },
        error: function(xhr) {
            statusEl.textContent = '';
            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Server error.';
            resultsEl.innerHTML = `<div class="trap-error-msg">&#9888; ${msg}</div>`;
        }
    });
}

function renderTrapResults(traps, meta) {
    const resultsEl = document.getElementById('trap-results-area');

    const datesScanned = meta.dates_scanned || 0;
    const ceCount      = meta.ce_traps      || 0;
    const peCount      = meta.pe_traps      || 0;
    const totalTraps   = meta.traps_found   || traps.length;

    // ── Stats row ──
    const statsHtml = `
        <div class="trap-stats-row">
            <div class="trap-stat-card">
                <strong>${totalTraps}</strong>
                <small>Traps Found</small>
            </div>
            <div class="trap-stat-card">
                <strong style="color:#ff4757;">${ceCount}</strong>
                <small>CE Traps</small>
            </div>
            <div class="trap-stat-card">
                <strong style="color:#ffa502;">${peCount}</strong>
                <small>PE Traps</small>
            </div>
            <div class="trap-stat-card">
                <strong style="color:rgba(255,255,255,.5);">${datesScanned}</strong>
                <small>Days Scanned</small>
            </div>
            ${meta.start_date ? `<div class="trap-stat-card" style="min-width:160px;">
                <strong style="font-size:11px;color:rgba(255,255,255,.6);">${meta.start_date} &rarr; ${meta.end_date}</strong>
                <small>Date Range</small>
            </div>` : ''}
        </div>`;

    if (!traps.length) {
        resultsEl.innerHTML = statsHtml + `<div class="trap-no-data">&#9888; No MM Trap signals found for the selected filters.</div>`;
        return;
    }

    // ── Table rows ──
    const rows = traps.map(function(t, i) {

        const typeBadge = t.type === 'CE_TRAP'
            ? '<span class="mm-call-trap">&#128308; CE TRAP</span>'
            : '<span class="mm-put-trap">&#128992; PE TRAP</span>';

        const strBadge = t.strength === 'STRONG'
            ? '<span class="str-very-strong">STRONG</span>'
            : '<span class="str-strong">MODERATE</span>';

        const cePctHtml = t.ce_oi_pct != null
            ? `<strong style="color:${t.ce_oi_pct >= 0 ? '#51cf66' : '#ff6b6b'};font-size:11px;">` +
              (t.ce_oi_pct > 0 ? '+' : '') + Number(t.ce_oi_pct).toFixed(2) + `%</strong>`
            : '&mdash;';

        const pePctHtml = t.put_oi_pct != null
            ? `<strong style="color:${t.put_oi_pct >= 0 ? '#51cf66' : '#ff6b6b'};font-size:11px;">` +
              (t.put_oi_pct > 0 ? '+' : '') + Number(t.put_oi_pct).toFixed(2) + `%</strong>`
            : '&mdash;';

        const diffHtml = t.diff != null
            ? `<span style="color:#f9ca24;font-weight:700;">&Delta;${Number(t.diff).toFixed(2)}%</span>`
            : '&mdash;';

        const detailHtml = t.detail
            ? `<span style="font-size:9px;color:rgba(255,255,255,.4);white-space:normal;max-width:280px;display:inline-block;">${t.detail}</span>`
            : '&mdash;';

        return `<tr>
            <td style="color:rgba(255,255,255,.3);font-size:10px;">${i + 1}</td>
            <td style="color:#00d2ff;font-weight:700;font-size:12px;">${t.date}</td>
            <td style="color:#a78bfa;font-weight:700;">${t.time}</td>
            <td><span class="badge-sym">${t.symbol}</span></td>
            <td>${typeBadge}</td>
            <td>${strBadge}</td>
            <td>${cePctHtml}</td>
            <td>${pePctHtml}</td>
            <td>${diffHtml}</td>
            <td style="white-space:normal;max-width:300px;">${detailHtml}</td>
        </tr>`;
    }).join('');

    resultsEl.innerHTML = statsHtml + `
        <div class="trap-result-scroll">
            <table class="trap-result-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Symbol</th>
                        <th>Trap Type</th>
                        <th>Strength</th>
                        <th>CE OI%</th>
                        <th>PE OI%</th>
                        <th>&Delta; Diff</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
}
</script>
@endpush