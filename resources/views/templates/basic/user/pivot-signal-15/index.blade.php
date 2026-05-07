@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
.page-header {
    background: linear-gradient(135deg, #fc4a1a, #f7b733);
    color: white; padding: 18px 24px; border-radius: 12px;
    margin-bottom: 20px; box-shadow: 0 4px 15px rgba(252,74,26,0.4);
}
.page-header h4 { color: white; margin: 0; }
.page-header p  { color: rgba(255,255,255,0.85); margin: 4px 0 0; font-size: 12px; }

.filter-bar {
    background: linear-gradient(135deg,#fc4a1a,#f7b733);
    padding: 12px 20px; border-radius: 12px; margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(252,74,26,0.35);
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.filter-bar label { color: rgba(255,255,255,0.75) !important; font-size: 11px; font-weight: 700; margin: 0; }
.sym-select {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.35);
    color: white; border-radius: 8px; padding: 6px 12px; font-size: 12px;
    font-weight: 700; cursor: pointer; outline: none; min-width: 180px;
}
.sym-select option { background: #3d1a00; color: white; }
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
    background: white; color: #fc4a1a; border: none; border-radius: 8px;
    padding: 7px 22px; font-weight: 800; font-size: 13px; cursor: pointer;
}
.btn-load:hover { background: #fff5f0; }
.auto-btn {
    background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 700; cursor: pointer;
}
.date-badge { font-size: 10px; font-weight: 700; padding: 2px 9px; border-radius: 10px; }
.badge-today { background: rgba(0,255,136,0.2); color: #00ff88; border: 1px solid rgba(0,255,136,0.3); }
.badge-hist  { background: rgba(255,193,7,0.2);  color: #ffc107; border: 1px solid rgba(255,193,7,0.3); }
.last-upd  { font-size: 10px; color: rgba(255,255,255,0.5); margin-left: auto; }
.divider-v { width: 1px; height: 24px; background: rgba(255,255,255,0.15); flex-shrink: 0; }

.main-card {
    border-radius: 12px; overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
}
.table-scroll { overflow-x: auto; }
.sig-table { width: 100%; border-collapse: collapse; min-width: 3200px; }

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
.hdr-meta    { color: rgba(255,255,255,0.5) !important; }
.hdr-ce      { color: #51cf66 !important; }
.hdr-pe      { color: #ff6b6b !important; }
.hdr-oi15m   { color: #f7b733 !important; }
.hdr-imbal   { color: #4fc3f7 !important; }   /* NEW: OI Imbalance — cyan */
.hdr-volspk  { color: #ff9800 !important; }   /* NEW: Vol Spike — orange */
.hdr-gamma   { color: #e040fb !important; }   /* NEW: Gamma — purple */
.sub-ce      { color: #51cf66 !important; }
.sub-pe      { color: #ff6b6b !important; }
.sub-oi15m   { color: #f7b733 !important; }
.sub-imbal   { color: #4fc3f7 !important; }
.sub-volspk  { color: #ff9800 !important; }
.sub-gamma   { color: #e040fb !important; }

/* ── Separators ── */
.sep-ce       { border-left: 2px solid rgba(81,207,102,0.35) !important; }
.sep-pe       { border-left: 2px solid rgba(255,107,107,0.35) !important; }
.sep-match-ce { border-left: 1px dashed rgba(81,207,102,0.25) !important; }
.sep-match-pe { border-left: 1px dashed rgba(255,107,107,0.25) !important; }
.sep-oi15m    { border-left: 2px solid rgba(247,183,51,0.4) !important; }
.sep-mmtrap   { border-left: 2px solid rgba(156,39,176,0.4) !important; }
.sep-imbal    { border-left: 2px solid rgba(79,195,247,0.4) !important; }   /* NEW */
.sep-volspk   { border-left: 2px solid rgba(255,152,0,0.4) !important; }    /* NEW */
.sep-gamma    { border-left: 2px solid rgba(224,64,251,0.4) !important; }   /* NEW */

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
.c-time   { font-size: 12px; font-weight: 800; color: #f7b733; }
.c-strike { font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 600; }
.c-atm    { font-size: 10px; color: #f7b733; font-weight: 700; }
.c-o      { color: rgba(255,255,255,.55); }
.c-h      { color: #ff9f7f; font-weight: 600; }
.c-l      { color: #7fff9f; font-weight: 600; }
.c-close  { color: #17a2b8; font-weight: 700; }
.c-pp     { color: #f7b733; font-weight: 800; }
.c-s1     { color: #51cf66; font-weight: 800; }
.c-r1     { color: #ff6b6b; font-weight: 800; }
.badge-sym {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 10px; font-weight: 800;
    background: rgba(247,183,51,0.15); color: #f7b733;
    border: 1px solid rgba(247,183,51,0.3);
}
.badge-atm {
    display: inline-block; padding: 1px 6px; border-radius: 5px;
    font-size: 9px; font-weight: 700;
    background: rgba(247,183,51,0.15); color: #f7b733;
    border: 1px solid rgba(247,183,51,0.25); margin-top: 2px;
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
    padding: 3px 8px; font-size: 10px; font-weight: 800;
}
.oi-bearish {
    display: inline-block; background: rgba(220,53,69,0.25); color: #ff6b6b;
    border: 1px solid rgba(220,53,69,0.5); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800;
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
    padding: 2px 7px; font-size: 9px; font-weight: 800; white-space: nowrap;
}
.str-strong {
    display: inline-block; background: rgba(255,165,2,0.22); color: #ffa502;
    border: 1px solid rgba(255,165,2,0.45); border-radius: 5px;
    padding: 2px 7px; font-size: 9px; font-weight: 800; white-space: nowrap;
}
.str-moderate {
    display: inline-block; background: rgba(247,183,51,0.15); color: #f7b733;
    border: 1px solid rgba(247,183,51,0.35); border-radius: 5px;
    padding: 2px 7px; font-size: 9px; font-weight: 700; white-space: nowrap;
}
.str-weak {
    display: inline-block; background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.3);
    border: 1px solid rgba(255,255,255,0.1); border-radius: 5px;
    padding: 2px 7px; font-size: 9px; font-weight: 600; white-space: nowrap;
}
.str-na  { color: rgba(255,255,255,0.2); font-size: 9px; }
.str-diff { font-size: 8px; color: rgba(255,255,255,0.35); margin-top: 2px; }

/* ── OI section bg ── */
.bg-oi15m  { background: rgba(247,183,51,0.04) !important; }

/* ── MM Trap badges ── */
.mm-call-trap {
    display: inline-block; background: rgba(255,71,87,0.22); color: #ff4757;
    border: 1px solid rgba(255,71,87,0.5); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; white-space: nowrap;
}
.mm-put-trap {
    display: inline-block; background: rgba(255,165,2,0.22); color: #ffa502;
    border: 1px solid rgba(255,165,2,0.5); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; white-space: nowrap;
}
.mm-both-trap {
    display: inline-block; background: rgba(156,39,176,0.22); color: #ce93d8;
    border: 1px solid rgba(156,39,176,0.5); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; white-space: nowrap;
}
.mm-no-trap  { color: rgba(255,255,255,0.18); font-size: 9px; }
.hdr-mmtrap  { color: #ce93d8 !important; }
.sub-mmtrap  { color: #ce93d8 !important; }
.bg-mmtrap   { background: rgba(156,39,176,0.04) !important; }

/* ── NEW: OI Imbalance badges ── */
.bg-imbal    { background: rgba(79,195,247,0.04) !important; }
.imbal-strong-bull {
    display: inline-block; background: rgba(40,167,69,0.3); color: #51cf66;
    border: 1px solid rgba(40,167,69,0.6); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; white-space: nowrap;
}
.imbal-bull {
    display: inline-block; background: rgba(40,167,69,0.18); color: #82e09a;
    border: 1px solid rgba(40,167,69,0.4); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 700; white-space: nowrap;
}
.imbal-mild-bull {
    display: inline-block; background: rgba(100,200,100,0.12); color: #a8d8b8;
    border: 1px solid rgba(100,200,100,0.3); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 600; white-space: nowrap;
}
.imbal-neutral {
    display: inline-block; background: rgba(108,117,125,0.15); color: rgba(255,255,255,0.35);
    border: 1px solid rgba(255,255,255,0.1); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 600; white-space: nowrap;
}
.imbal-mild-bear {
    display: inline-block; background: rgba(220,53,69,0.12); color: #f0a0a8;
    border: 1px solid rgba(220,53,69,0.3); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 600; white-space: nowrap;
}
.imbal-bear {
    display: inline-block; background: rgba(220,53,69,0.18); color: #ff8a95;
    border: 1px solid rgba(220,53,69,0.4); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 700; white-space: nowrap;
}
.imbal-strong-bear {
    display: inline-block; background: rgba(220,53,69,0.3); color: #ff6b6b;
    border: 1px solid rgba(220,53,69,0.6); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; white-space: nowrap;
}
.dir-buildup   { color: #51cf66; font-size: 10px; font-weight: 700; }
.dir-unwinding { color: #ff6b6b; font-size: 10px; font-weight: 700; }
.dir-flat      { color: rgba(255,255,255,0.3); font-size: 10px; }
.ratio-val     { font-size: 11px; font-weight: 800; }

/* ── NEW: Vol Spike badges ── */
.bg-volspk    { background: rgba(255,152,0,0.04) !important; }
.vol-strong-spike {
    display: inline-block; background: rgba(255,50,50,0.3); color: #ff4444;
    border: 1px solid rgba(255,50,50,0.6); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; white-space: nowrap;
    animation: blink 1s step-end infinite;
}
.vol-spike {
    display: inline-block; background: rgba(255,152,0,0.28); color: #ff9800;
    border: 1px solid rgba(255,152,0,0.6); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; white-space: nowrap;
}
.vol-elevated {
    display: inline-block; background: rgba(255,193,7,0.18); color: #ffc107;
    border: 1px solid rgba(255,193,7,0.4); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 700; white-space: nowrap;
}
.vol-normal {
    display: inline-block; background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.28);
    border: 1px solid rgba(255,255,255,0.1); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 600; white-space: nowrap;
}
.vol-opening {
    display: inline-block; background: rgba(79,195,247,0.12); color: #4fc3f7;
    border: 1px solid rgba(79,195,247,0.3); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 700; white-space: nowrap;
}
.vol-na { color: rgba(255,255,255,0.2); font-size: 9px; }
.vol-ratio { font-size: 10px; color: rgba(255,255,255,0.45); margin-top: 2px; }
@keyframes blink { 50% { opacity: .5; } }

/* ── NEW: Gamma badges ── */
.bg-gamma    { background: rgba(224,64,251,0.04) !important; }
.gamma-accel-strong {
    display: inline-block; background: rgba(40,167,69,0.25); color: #51cf66;
    border: 1px solid rgba(40,167,69,0.5); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; white-space: nowrap;
}
.gamma-accel {
    display: inline-block; background: rgba(40,167,69,0.15); color: #82e09a;
    border: 1px solid rgba(40,167,69,0.3); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 700; white-space: nowrap;
}
.gamma-mild-accel {
    display: inline-block; background: rgba(100,220,100,0.10); color: #a8d8b8;
    border: 1px solid rgba(100,220,100,0.25); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 600; white-space: nowrap;
}
.gamma-stable {
    display: inline-block; background: rgba(108,117,125,0.12); color: rgba(255,255,255,0.3);
    border: 1px solid rgba(255,255,255,0.08); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 600; white-space: nowrap;
}
.gamma-mild-decel {
    display: inline-block; background: rgba(220,53,69,0.10); color: #f0a0a8;
    border: 1px solid rgba(220,53,69,0.25); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 600; white-space: nowrap;
}
.gamma-decel {
    display: inline-block; background: rgba(220,53,69,0.18); color: #ff8a95;
    border: 1px solid rgba(220,53,69,0.4); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 700; white-space: nowrap;
}
.gamma-decel-strong {
    display: inline-block; background: rgba(220,53,69,0.28); color: #ff6b6b;
    border: 1px solid rgba(220,53,69,0.6); border-radius: 6px;
    padding: 3px 8px; font-size: 10px; font-weight: 800; white-space: nowrap;
}
.gamma-na { color: rgba(255,255,255,0.2); font-size: 9px; }
.delta-val { font-size: 8px; color: rgba(255,255,255,0.3); margin-top: 2px; }

/* ── Misc ── */
.spinner { width:36px; height:36px; border:4px solid rgba(255,255,255,0.12); border-top:4px solid #f7b733; border-radius:50%; animation:spin 1s linear infinite; }
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
                    <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;margin-left:6px;">ATM &middot; 15Min Candle</span>
                </h4>
                <p>
                    PP = (H+L+C)/3 &nbsp;&middot;&nbsp; S1 = 2P&minus;H &nbsp;&middot;&nbsp; R1 = 2P&minus;L &nbsp;&middot;&nbsp;
                    <strong style="color:#b2ffda;">S1 &rarr; BUY</strong> &nbsp;&middot;&nbsp;
                    <strong style="color:#ffb2b2;">R1 &rarr; SELL</strong> &nbsp;&middot;&nbsp;
                    <span style="color:#f7b733;">&#9679; 15-Min interval data</span> &nbsp;&middot;&nbsp;
                    <span style="color:#4fc3f7;">&#9679; OI Imbalance (-100 to +100)</span> &nbsp;&middot;&nbsp;
                    <span style="color:#ff9800;">&#9679; Vol Spike</span> &nbsp;&middot;&nbsp;
                    <span style="color:#e040fb;">&#9679; Gamma Proxy</span>
                </p>
            </div>
            <a href="{{ route('pivot-signal-15.config.index') }}" class="btn btn-light btn-sm">&#9881; Configs</a>
        </div>
    </div>

    {{-- ── Filter Bar ── --}}
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
        <button class="auto-btn" id="auto-btn" onclick="toggleAuto()">&#9654; Auto 15s</button>
        <span id="auto-tag" style="font-size:10px;color:rgba(255,255,255,0.5);"></span>
        <span id="candle-info" style="font-size:10px;color:rgba(255,255,255,0.5);"></span>
        <span class="last-upd" id="last-upd"></span>
    </div>

    {{-- ── Table ── --}}
    <div class="main-card">
        <div class="table-scroll">
            <table class="sig-table">
                <thead>
                    <tr class="hdr-group">
                        {{-- Meta --}}
                        <th colspan="4" class="hdr-meta">Meta</th>

                        {{-- 15Min OI Sentiment --}}
                        <th colspan="6" class="hdr-oi15m sep-oi15m">&#9889; 15Min OI Sentiment<br><span style="font-size:8px;font-weight:400;opacity:.7;">Current candle &mdash; CE vs PE OI</span></th>

                        {{-- MM Trap --}}
                        <th colspan="3" class="hdr-mmtrap sep-mmtrap">&#9888; MM Trap<br><span style="font-size:8px;font-weight:400;opacity:.7;">OI Wall breakout</span></th>

                        {{-- NEW: OI Imbalance --}}
                        <th colspan="4" class="hdr-imbal sep-imbal">&#9878; OI Change Imbalance<br><span style="font-size:8px;font-weight:400;opacity:.7;">(PE&minus;CE) &divide; (|PE|+|CE|) &times; 100</span></th>

                        {{-- NEW: Vol Spike --}}
                        <th colspan="3" class="hdr-volspk sep-volspk">&#128293; Vol Spike<br><span style="font-size:8px;font-weight:400;opacity:.7;">vs Session Avg</span></th>

                        {{-- NEW: Gamma Proxy --}}
                        <th colspan="3" class="hdr-gamma sep-gamma">&#915; Gamma Proxy<br><span style="font-size:8px;font-weight:400;opacity:.7;">&#916;(Delta) / &#916;(Underlying)</span></th>

                        {{-- CE --}}
                        <th colspan="10" class="hdr-ce sep-ce">&#128200; CE &mdash; ATM (own pivot)</th>

                        {{-- PE --}}
                        <th colspan="10" class="hdr-pe sep-pe">&#128201; PE &mdash; ATM (own pivot)</th>
                    </tr>
                    <tr class="hdr-cols">
                        {{-- Meta --}}
                        <th class="hdr-meta">#</th>
                        <th class="hdr-meta">Time</th>
                        <th class="hdr-meta">Symbol</th>
                        <th class="hdr-meta">ATM<br><span style="font-size:8px;opacity:.6;font-weight:400;">Strike</span></th>

                        {{-- 15Min OI cols --}}
                        <th class="sub-oi15m sep-oi15m">Signal</th>
                        <th class="sub-oi15m">CE%<br><span style="font-size:8px;opacity:.6;font-weight:400;">OI Chg</span></th>
                        <th class="sub-oi15m">PE%<br><span style="font-size:8px;opacity:.6;font-weight:400;">OI Chg</span></th>
                        <th class="sub-oi15m">Strength<br><span style="font-size:8px;opacity:.6;font-weight:400;">|CE%&minus;PE%|</span></th>
                        <th class="sub-oi15m">Next Signal<br><span style="font-size:8px;opacity:.6;font-weight:400;">Next candle</span></th>
                        <th class="sub-oi15m">Decision<br><span style="font-size:8px;opacity:.6;font-weight:400;">Hold / Exit</span></th>

                        {{-- MM Trap cols --}}
                        <th class="sub-mmtrap sep-mmtrap">Type</th>
                        <th class="sub-mmtrap">Walls<br><span style="font-size:8px;opacity:.6;font-weight:400;">Call / Put</span></th>
                        <th class="sub-mmtrap">Detail</th>

                        {{-- NEW: OI Imbalance cols --}}
                        <th class="sub-imbal sep-imbal">Score<br><span style="font-size:8px;opacity:.6;font-weight:400;">-100 &hellip; +100</span></th>
                        <th class="sub-imbal">Bias</th>
                        <th class="sub-imbal">CE Dir<br><span style="font-size:8px;opacity:.6;font-weight:400;">Buildup / Unwind</span></th>
                        <th class="sub-imbal">PE Dir<br><span style="font-size:8px;opacity:.6;font-weight:400;">Buildup / Unwind</span></th>

                        {{-- NEW: Vol Spike cols --}}
                        <th class="sub-volspk sep-volspk">Spike<br><span style="font-size:8px;opacity:.6;font-weight:400;">Label</span></th>
                        <th class="sub-volspk">x Ratio<br><span style="font-size:8px;opacity:.6;font-weight:400;">Cur &divide; Avg</span></th>
                        <th class="sub-volspk">Avg Vol<br><span style="font-size:8px;opacity:.6;font-weight:400;">Session</span></th>

                        {{-- NEW: Gamma cols --}}
                        <th class="sub-gamma sep-gamma">Label<br><span style="font-size:8px;opacity:.6;font-weight:400;">Accel / Decel</span></th>
                        <th class="sub-gamma">&#916;t<br><span style="font-size:8px;opacity:.6;font-weight:400;">Current Delta</span></th>
                        <th class="sub-gamma">&#916;t&#8722;1<br><span style="font-size:8px;opacity:.6;font-weight:400;">Prev Delta</span></th>

                        {{-- CE cols --}}
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

                        {{-- PE cols --}}
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
                    <tr><td colspan="40">
                        <div class="loading-wrap">
                            <div class="spinner"></div>
                            <div style="color:white;margin-top:14px;font-size:13px;">Loading 15-min signals...</div>
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
        $('#auto-btn').text('\u25b6 Auto 15s'); $('#auto-tag').text('');
    } else {
        autoTimer = setInterval(loadData, 15000);
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
        $('#auto-btn').text('\u25b6 Auto 15s'); $('#auto-tag').text('');
    }
    $('#sig-tbody').html('<tr><td colspan="40"><div class="loading-wrap"><div class="spinner"></div><div style="color:white;margin-top:12px;font-size:13px;">Fetching 15-min data for ' + date + '&hellip;</div></div></td></tr>');
    $.ajax({
        url : '{{ route("pivot-signal-15.signals") }}',
        data: { symbol: sym, date: date },
        success(res) {
            updateDateBadge(res.is_today);
            if (res.available_symbols && res.available_symbols.length) {
                rebuildSymDropdown(res.available_symbols);
            }
            if (!res.success || !res.data || !res.data.length) {
                $('#sig-tbody').html('<tr><td colspan="40"><div class="no-data"><i class="fas fa-calendar-times" style="font-size:2.5rem;opacity:.3;"></i><p style="margin-top:14px;">' + (res.message || 'No data for ' + date) + '</p></div></td></tr>');
                return;
            }
            const totalCandles = res.data.reduce((a,d)=>a+d.total_candles,0);
            $('#candle-info').text('~' + totalCandles + ' candles across ' + res.data.length + ' symbol(s)');
            renderTable(res.data);
            $('#last-upd').text('Updated: ' + new Date().toLocaleTimeString());
        },
        error(xhr) {
            $('#sig-tbody').html('<tr><td colspan="40"><div class="no-data">\u26a0 ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error') + '</div></td></tr>');
        }
    });
}

// ════════════════════════════════════════════════════════════
// BADGE BUILDERS — existing
// ════════════════════════════════════════════════════════════

function oiSentimentBadge(sentiment) {
    if (!sentiment || sentiment === 'N/A') return '<span class="oi-na">&mdash;</span>';
    if (sentiment === 'BULLISH') return '<span class="oi-bullish">&#129033; BULLISH</span>';
    if (sentiment === 'BEARISH') return '<span class="oi-bearish">&#129035; BEARISH</span>';
    return '<span class="oi-neutral">&#9679; NEUTRAL</span>';
}
function oiPctCell(pct) {
    if (pct === undefined || pct === null) return '<span style="color:rgba(255,255,255,.2);font-size:9px;">&mdash;</span>';
    const v = Number(pct);
    const cls = v > 0 ? 'color:#51cf66;' : v < 0 ? 'color:#ff6b6b;' : 'color:rgba(255,255,255,.35);';
    return `<strong style="${cls}font-size:10px;">${v > 0 ? '+' : ''}${v.toFixed(2)}%</strong>`;
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
    if (mm.type === 'CALL WALL')  return '<span class="mm-call-trap">&#128308; CALL WALL</span>';
    if (mm.type === 'PUT WALL')   return '<span class="mm-put-trap">&#128992; PUT WALL</span>';
    if (mm.type === 'CALL BREAK') return '<span class="mm-both-trap">&#9650; CALL BREAK</span>';
    if (mm.type === 'PUT BREAK')  return '<span class="mm-both-trap">&#9660; PUT BREAK</span>';
    return '<span class="mm-no-trap">&mdash;</span>';
}
function mmWallsCell(mm) {
    if (!mm || (!mm.call_wall && !mm.put_wall))
        return '<span style="color:rgba(255,255,255,.18);font-size:9px;">&mdash;</span>';
    const cw = mm.call_wall
        ? '<span style="color:#ff6b6b;font-size:10px;font-weight:700;">C:' + nInt(mm.call_wall)
          + (mm.call_oi_pct ? ' <span style="font-size:8px;">(' + (mm.call_oi_pct > 0 ? '+' : '') + mm.call_oi_pct + '%)</span>' : '')
          + '</span>' : '';
    const pw = mm.put_wall
        ? '<span style="color:#51cf66;font-size:10px;font-weight:700;">P:' + nInt(mm.put_wall)
          + (mm.put_oi_pct ? ' <span style="font-size:8px;">(' + (mm.put_oi_pct > 0 ? '+' : '') + mm.put_oi_pct + '%)</span>' : '')
          + '</span>' : '';
    return [cw, pw].filter(Boolean).join('<br>');
}
function mmDetailCell(mm) {
    if (!mm || !mm.detail)
        return '<span style="color:rgba(255,255,255,.18);font-size:9px;">&mdash;</span>';
    return '<span style="font-size:9px;color:rgba(255,255,255,.5);">' + mm.detail + '</span>';
}

// ════════════════════════════════════════════════════════════
// NEW BADGE BUILDERS
// ════════════════════════════════════════════════════════════

/**
 * OI Change Imbalance score cell — shows -100 to +100 with % sign
 * Positive = Bullish (PE dominant), Negative = Bearish (CE dominant)
 */
function imbalanceRatioCell(imb) {
    if (!imb || imb.imbalance_ratio === null || imb.imbalance_ratio === undefined)
        return '<span style="color:rgba(255,255,255,.18);font-size:9px;">&mdash;</span>';

    const s = Number(imb.imbalance_ratio);
    const abs = Math.abs(s);
    // Color: green shades for bullish, red shades for bearish, grey for neutral
    let color;
    if      (s >=  60) color = '#51cf66';
    else if (s >=  20) color = '#82e09a';
    else if (s >=   5) color = '#a8d8b8';
    else if (s >   -5) color = 'rgba(255,255,255,.4)';
    else if (s >= -20) color = '#f0a0a8';
    else if (s >= -60) color = '#ff8a95';
    else               color = '#ff6b6b';

    const pfx = s > 0 ? '+' : '';
    return `<span class="ratio-val" style="color:${color};">${pfx}${s.toFixed(1)}</span><div style="font-size:8px;color:rgba(255,255,255,.25);margin-top:1px;">score/100</div>`;
}

/**
 * OI Imbalance bias badge
 */
function imbalanceBiasBadge(imb) {
    if (!imb || !imb.imbalance_label || imb.imbalance_label === 'N/A')
        return '<span class="imbal-neutral">—</span>';

    const label = imb.imbalance_label;
    const map = {
        'Strong Bullish' : 'imbal-strong-bull',
        'Bullish'        : 'imbal-bull',
        'Mild Bullish'   : 'imbal-mild-bull',
        'Balanced'       : 'imbal-neutral',
        'Mild Bearish'   : 'imbal-mild-bear',
        'Bearish'        : 'imbal-bear',
        'Strong Bearish' : 'imbal-strong-bear',
    };
    const icon = imb.imbalance_bias === 'BULLISH' ? '&#129033; ' : imb.imbalance_bias === 'BEARISH' ? '&#129035; ' : '&#9654; ';
    const cls  = map[label] || 'imbal-neutral';
    return `<span class="${cls}">${icon}${label}</span>`;
}

/**
 * OI Direction cell for CE or PE
 */
function oiDirectionBadge(dir) {
    if (!dir || dir === 'N/A') return '<span style="color:rgba(255,255,255,.18);font-size:9px;">&mdash;</span>';
    if (dir === 'Buildup')   return '<span class="dir-buildup">&#129033; Buildup</span>';
    if (dir === 'Unwinding') return '<span class="dir-unwinding">&#129035; Unwind</span>';
    return '<span class="dir-flat">&#8211; Flat</span>';
}

/**
 * Vol Spike badge
 */
function volSpikeBadge(vs) {
    if (!vs || vs.spike_type === undefined)
        return '<span class="vol-na">&mdash;</span>';

    const map = {
        'STRONG_SPIKE' : ['vol-strong-spike', '&#128293;&#128293; STRONG'],
        'SPIKE'        : ['vol-spike',         '&#128293; SPIKE'],
        'ELEVATED'     : ['vol-elevated',      '&#9650; ELEVATED'],
        'NORMAL'       : ['vol-normal',        '&#9135; NORMAL'],
        'OPENING'      : ['vol-opening',       '&#9888; OPENING'],
    };
    const type  = vs.spike_type || 'NORMAL';
    const entry = map[type] || map['NORMAL'];
    return `<span class="${entry[0]}">${entry[1]}</span>`;
}

/**
 * Vol Spike ratio cell
 */
function volSpikeRatioCell(vs) {
    if (!vs || vs.spike_ratio === null || vs.spike_ratio === undefined)
        return '<span style="color:rgba(255,255,255,.2);font-size:9px;">&mdash;</span>';
    const r = Number(vs.spike_ratio);
    const color = r >= 2.0 ? '#ff4444' : r >= 1.5 ? '#ff9800' : r >= 1.2 ? '#ffc107' : 'rgba(255,255,255,.35)';
    return `<span style="color:${color};font-size:11px;font-weight:800;">${r.toFixed(2)}x</span>`;
}

/**
 * Gamma proxy badge
 */
function gammaBadge(g) {
    if (!g || !g.gamma_label || g.gamma_label === 'N/A')
        return '<span class="gamma-na">&mdash;</span>';

    const label = g.gamma_label;
    const map = {
        'Accel ↑↑'  : 'gamma-accel-strong',
        'Accel ↑'   : 'gamma-accel',
        'Mild Accel': 'gamma-mild-accel',
        'Stable'    : 'gamma-stable',
        'Mild Decel': 'gamma-mild-decel',
        'Decel ↓'   : 'gamma-decel',
        'Decel ↓↓'  : 'gamma-decel-strong',
    };
    const cls = map[label] || 'gamma-stable';
    return `<span class="${cls}">${label}</span>`;
}

/**
 * Delta value cell (current or previous)
 */
function deltaCell(val) {
    if (val === null || val === undefined)
        return '<span style="color:rgba(255,255,255,.18);font-size:9px;">&mdash;</span>';
    const v = Number(val);
    const color = v > 0 ? '#51cf66' : v < 0 ? '#ff6b6b' : 'rgba(255,255,255,.4)';
    return `<span style="color:${color};font-size:10px;font-weight:700;">${v.toFixed(3)}</span>`;
}

// ════════════════════════════════════════════════════════════
// MAIN TABLE RENDERER
// ════════════════════════════════════════════════════════════

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

            // ── Existing: OI Sentiment & MM Trap ──────────────────────────────
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

            // ── NEW: OI Imbalance ──────────────────────────────────────────────
            // Use CE signal's imbalance (it's the same per-interval shared data)
            const imb    = (ce && ce.oi_imbalance) ? ce.oi_imbalance : ((pe && pe.oi_imbalance) ? pe.oi_imbalance : null);
            const imbTitle = imb && imb.imbalance_label ? ` title="OI Imbalance Score: ${imb.imbalance_ratio} | ${imb.imbalance_label} | CE Change: ${imb.ce_oi_change} | PE Change: ${imb.pe_oi_change}"` : '';

            const imbRatioCell = imbalanceRatioCell(imb);
            const imbBias      = imbalanceBiasBadge(imb);
            const ceDirCell    = oiDirectionBadge(imb ? imb.ce_direction : null);
            const peDirCell    = oiDirectionBadge(imb ? imb.pe_direction : null);

            // ── NEW: Vol Spike ─────────────────────────────────────────────────
            // Shared per interval — use CE's, fallback to PE's
            const vs       = (ce && ce.vol_spike) ? ce.vol_spike : ((pe && pe.vol_spike) ? pe.vol_spike : null);
            const vsTitle  = vs && vs.spike_ratio ? ` title="Vol Spike x${vs.spike_ratio} | Current: ${nInt(vs.cur_vol)} | Avg: ${nInt(vs.avg_vol)}"` : '';
            const vsBadge  = volSpikeBadge(vs);
            const vsRatio  = volSpikeRatioCell(vs);
            const vsAvgVol = vs && vs.avg_vol ? `<span style="color:rgba(255,255,255,.3);font-size:9px;">${nInt(vs.avg_vol)}</span>` : '<span style="color:rgba(255,255,255,.18);font-size:9px;">&mdash;</span>';

            // ── NEW: Gamma — CE and PE have their own gamma (ATM-specific) ─────
            const ceGamma = (ce && ce.gamma) ? ce.gamma : null;
            const peGamma = (pe && pe.gamma) ? pe.gamma : null;
            // Show CE gamma in summary; in detail we show per-type
            const gammaToShow = ceGamma || peGamma;
            const gTitle   = gammaToShow && gammaToShow.gamma_proxy !== null
                ? ` title="Gamma Proxy: ${gammaToShow.gamma_proxy} | Delta(t): ${gammaToShow.delta_t} | Delta(t-1): ${gammaToShow.delta_prev}"` : '';
            const gBadge   = gammaBadge(gammaToShow);
            const gDeltaT  = deltaCell(gammaToShow ? gammaToShow.delta_t   : null);
            const gDeltaPrev = deltaCell(gammaToShow ? gammaToShow.delta_prev : null);

            // ── Existing: Decision ─────────────────────────────────────────────
            // Each candle shows the NEXT candle's OI signal.
            // HOLD = trend continuing | EXIT = reversal incoming | PENDING = latest candle (next not yet arrived)
            const nextSignal = (ce && ce.next_signal) ? ce.next_signal : ((pe && pe.next_signal) ? pe.next_signal : null);
            const decision   = (ce && ce.decision)    ? ce.decision    : ((pe && pe.decision)    ? pe.decision    : 'N/A');

            const nextSignalBadge = nextSignal ? oiSentimentBadge(nextSignal)
                : '<span style="color:rgba(255,255,255,.18);font-size:9px;">&mdash;</span>';

            const decisionBadge = decision === 'HOLD'
                ? '<span style="background:rgba(81,207,102,0.15);color:#51cf66;border:1px solid rgba(81,207,102,0.4);border-radius:6px;padding:3px 8px;font-size:10px;font-weight:800;">&#9654; HOLD</span>'
                : decision === 'EXIT'
                ? '<span style="background:rgba(255,71,87,0.2);color:#ff4757;border:1px solid rgba(255,71,87,0.5);border-radius:6px;padding:3px 8px;font-size:10px;font-weight:800;">&#9888; EXIT</span>'
                : decision === 'PENDING'
                ? '<span style="background:rgba(116,192,252,0.12);color:#74c0fc;border:1px solid rgba(116,192,252,0.3);border-radius:6px;padding:3px 8px;font-size:10px;font-weight:700;">&#8987; PENDING</span>'
                : '<span style="color:rgba(255,255,255,.2);font-size:9px;">&mdash;</span>';

            // ── Match flags ────────────────────────────────────────────────────
            const ceS1Match = ce ? (ce.s1_match !== undefined ? ce.s1_match : ce.S1 >= ce.low)  : null;
            const ceR1Match = ce ? (ce.r1_match !== undefined ? ce.r1_match : ce.R1 >= ce.high) : null;
            const peS1Match = pe ? (pe.s1_match !== undefined ? pe.s1_match : pe.S1 >= pe.low)  : null;
            const peR1Match = pe ? (pe.r1_match !== undefined ? pe.r1_match : pe.R1 >= pe.high) : null;

            // ── CE / PE OHLC cells ─────────────────────────────────────────────
            const ceCells = ce
                ? '<td class="sep-ce c-strike">&#8377;' + n(ce.strike) + '</td>'
                + '<td class="c-o">&#8377;' + n(ce.open)  + '</td>'
                + '<td class="c-h">&#8377;' + n(ce.high)  + '</td>'
                + '<td class="c-l">&#8377;' + n(ce.low)   + '</td>'
                + '<td class="c-close">&#8377;' + n(ce.close) + '</td>'
                + '<td class="c-pp">&#8377;'    + n(ce.PP)    + '</td>'
                + '<td class="c-s1" title="CE S1 - BUY">&#8377;'  + n(ce.S1) + '</td>'
                + '<td class="c-r1" title="CE R1 - SELL">&#8377;' + n(ce.R1) + '</td>'
                + '<td class="sep-match-ce">' + matchPill(ceS1Match) + '</td>'
                + '<td>'                       + matchPill(ceR1Match) + '</td>'
                : '<td colspan="10" class="sep-ce" style="color:rgba(255,255,255,.12);font-size:9px;">&mdash; no CE data &mdash;</td>';

            const peCells = pe
                ? '<td class="sep-pe c-strike">&#8377;' + n(pe.strike) + '</td>'
                + '<td class="c-o">&#8377;' + n(pe.open)  + '</td>'
                + '<td class="c-h">&#8377;' + n(pe.high)  + '</td>'
                + '<td class="c-l">&#8377;' + n(pe.low)   + '</td>'
                + '<td class="c-close">&#8377;' + n(pe.close) + '</td>'
                + '<td class="c-pp">&#8377;'    + n(pe.PP)    + '</td>'
                + '<td class="c-s1" title="PE S1 - BUY">&#8377;'  + n(pe.S1) + '</td>'
                + '<td class="c-r1" title="PE R1 - SELL">&#8377;' + n(pe.R1) + '</td>'
                + '<td class="sep-match-pe">' + matchPill(peS1Match) + '</td>'
                + '<td>'                       + matchPill(peR1Match) + '</td>'
                : '<td colspan="10" class="sep-pe" style="color:rgba(255,255,255,.12);font-size:9px;">&mdash; no PE data &mdash;</td>';

            // ── Assemble row ───────────────────────────────────────────────────
            const oi15mCells = `
                <td class="sep-oi15m bg-oi15m"${h1Title}>${h1Signal}</td>
                <td class="bg-oi15m">${h1CePct}</td>
                <td class="bg-oi15m">${h1PePct}</td>
                <td class="bg-oi15m">${h1Strength}</td>
                <td class="bg-oi15m">${nextSignalBadge}</td>
                <td class="bg-oi15m">${decisionBadge}</td>`;

            const mmTrapCells = `
                <td class="sep-mmtrap bg-mmtrap"${mmTitle}>${mmType}</td>
                <td class="bg-mmtrap">${mmWalls}</td>
                <td class="bg-mmtrap">${mmDetail}</td>`;

            const imbCells = `
                <td class="sep-imbal bg-imbal"${imbTitle}>${imbRatioCell}</td>
                <td class="bg-imbal">${imbBias}</td>
                <td class="bg-imbal">${ceDirCell}</td>
                <td class="bg-imbal">${peDirCell}</td>`;

            const volCells = `
                <td class="sep-volspk bg-volspk"${vsTitle}>${vsBadge}</td>
                <td class="bg-volspk">${vsRatio}</td>
                <td class="bg-volspk">${vsAvgVol}</td>`;

            const gammaCells = `
                <td class="sep-gamma bg-gamma"${gTitle}>${gBadge}</td>
                <td class="bg-gamma">${gDeltaT}</td>
                <td class="bg-gamma">${gDeltaPrev}</td>`;

            rows += '<tr class="' + zebraClass + '">'
                + '<td class="c-num">'  + rowNum++ + '</td>'
                + '<td class="c-time">' + time + '</td>'
                + '<td><span class="badge-sym">' + d.symbol + '</span></td>'
                + '<td class="c-atm"><span class="badge-atm">&#8377;' + atmStrike + '</span></td>'
                + oi15mCells
                + mmTrapCells
                + imbCells
                + volCells
                + gammaCells
                + ceCells
                + peCells
                + '</tr>';
        });
    });

    if (!rows) {
        rows = '<tr><td colspan="40"><div class="no-data">No 15-min candle data found.</div></td></tr>';
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
function nInt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}
</script>
@endpush