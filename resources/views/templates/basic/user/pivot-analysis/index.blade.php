{{-- FILE: resources/views/themes/{active_theme}/user/pivot-analysis/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* ── BASE ── */
.pv-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.pv-wrap * { box-sizing:border-box; }
.pv-wrap h1,.pv-wrap h2,.pv-wrap h3,.pv-wrap h4 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.pv-wrap a { text-decoration:none; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes pvFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.pv-anim { animation:pvFadeUp .5s ease both; }
@keyframes pvSpin  { to{ transform:rotate(360deg); } }

/* ── HERO ── */
.pv-hero {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:32px 48px; display:flex; align-items:center;
    justify-content:space-between; gap:24px;
}
.pv-hero-left h1 {
    font-size:clamp(26px,3.5vw,42px); font-weight:700;
    color:#1a1a2e; margin:0 0 8px; line-height:1.1;
}
.pv-hero-left h1 span { color:#7DFF00; }
.pv-hero-left p { font-size:13px; color:#666; margin:0; line-height:1.7; max-width:560px; }
.pv-hero-formulas { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
.pv-pill {
    display:inline-block; padding:3px 10px; border-radius:4px;
    font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700;
}
.pv-pill-pp  { background:rgba(245,166,35,.12); color:#c97f00; border:1px solid rgba(245,166,35,.3); }
.pv-pill-s   { background:rgba(5,150,105,.1);  color:#047857; border:1px solid rgba(5,150,105,.3);  }
.pv-pill-r   { background:rgba(220,38,38,.08); color:#b91c1c; border:1px solid rgba(220,38,38,.25); }
.pv-hero-icon {
    width:80px; height:80px; border-radius:16px;
    background:linear-gradient(135deg,#0f1b2d,#1a3050);
    display:flex; align-items:center; justify-content:center;
    font-size:36px; color:#7DFF00; flex-shrink:0;
}
@media(max-width:768px){
    .pv-hero { flex-direction:column; padding:24px 16px; text-align:center; }
    .pv-hero-formulas { justify-content:center; }
}

/* ── FILTER BAR ── */
.pv-filter-bar {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:0 48px; position:sticky; top:0; z-index:200;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.pv-filter-inner {
    display:flex; align-items:center; gap:14px;
    padding:13px 0; flex-wrap:wrap;
}
.pv-filter-label {
    font-size:10.5px; color:#999; font-weight:700;
    text-transform:uppercase; letter-spacing:.07em;
}

/* Instrument tabs */
.pv-inst-tabs { display:flex; gap:4px; }
.pv-inst-tab {
    padding:7px 16px; border-radius:6px; border:1.5px solid #e5e9f2;
    font-size:12px; font-weight:700; color:#666; cursor:pointer;
    background:#fff; transition:all .2s; font-family:'Exo 2',sans-serif;
}
.pv-inst-tab:hover { border-color:#7DFF00; color:#c97f00; }
.pv-inst-tab.on-stock  { border-color:#059669; background:rgba(5,150,105,.08); color:#047857; }
.pv-inst-tab.on-fut    { border-color:#7DFF00; background:rgba(245,166,35,.08); color:#c97f00; }
.pv-inst-tab.on-option { border-color:#7c3aed; background:rgba(124,58,237,.08); color:#6d28d9; }

/* Symbol select */
.pv-sym-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 30px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance:none; cursor:pointer; outline:none; min-width:140px;
}
.pv-sym-select:focus { border-color:#7DFF00; }

/* Date input */
.pv-date-wrap { display:flex; align-items:center; gap:4px; }
.pv-date-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
    color:#333; outline:none; cursor:pointer;
}
.pv-date-input:focus { border-color:#7DFF00; }
.pv-date-nav {
    width:28px; height:32px; border:1.5px solid #e5e9f2; border-radius:6px;
    background:#fff; color:#888; cursor:pointer; font-weight:700; font-size:14px;
    display:flex; align-items:center; justify-content:center; transition:.2s;
}
.pv-date-nav:hover { border-color:#7DFF00; color:#7DFF00; }
.pv-today-btn { width:auto; padding:0 10px; font-size:10px; font-family:'Exo 2',sans-serif; font-weight:700; letter-spacing:.07em; }

/* Status badge */
.pv-live-badge { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.pv-hist-badge { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }

/* Buttons */
.pv-load-btn {
    background:#7DFF00; color:#000; border:none; border-radius:8px;
    padding:8px 20px; font-family:'Rajdhani',sans-serif; font-size:13px;
    font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s;
}
.pv-load-btn:hover { background:#d4890e; }
.pv-auto-btn {
    background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px;
    padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer;
    font-family:'Exo 2',sans-serif; transition:.2s;
}
.pv-auto-btn.on { border-color:#059669; background:rgba(5,150,105,.08); color:#047857; }

.pv-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.pv-info-text { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.pv-last-upd  { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }

@media(max-width:768px){
    .pv-filter-bar { padding:0 16px; }
    .pv-filter-inner { gap:8px; }
    .pv-filter-right { margin-left:0; width:100%; }
}

/* ── CONTENT AREA ── */
.pv-content { padding:28px 48px 64px; }
@media(max-width:768px){ .pv-content { padding:16px 12px 48px; } }

/* Config warning */
.pv-warn {
    background:#fff3e0; border:1px solid #ffcc80; border-radius:10px;
    padding:16px 20px; margin-bottom:20px;
    display:none; align-items:center; gap:14px; font-size:13px; color:#e65100;
}
.pv-warn.show { display:flex; }
.pv-warn i { font-size:20px; flex-shrink:0; }

/* ── TABLE CARD ── */
.pv-card {
    background:#fff; border-radius:12px; border:1px solid #e8e8e8;
    overflow:hidden; margin-bottom:24px;
}
.pv-card-header {
    padding:14px 20px; border-bottom:1px solid #f0f0f0;
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:8px; background:#fafafa;
}
.pv-card-title {
    font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700;
    color:#1a1a2e; display:flex; align-items:center; gap:8px;
}
.pv-card-subtitle { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.pv-inst-label {
    display:inline-block; padding:3px 10px; border-radius:4px;
    font-size:11px; font-weight:700; letter-spacing:.06em;
}
.pv-il-stock  { background:rgba(5,150,105,.1); color:#047857; border:1px solid rgba(5,150,105,.3); }
.pv-il-fut    { background:rgba(245,166,35,.1); color:#c97f00; border:1px solid rgba(245,166,35,.3); }
.pv-il-option { background:rgba(124,58,237,.1); color:#6d28d9; border:1px solid rgba(124,58,237,.3); }

/* Table scroll */
.pv-table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

/* ── TABLE BASE ── */
.pv-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; }
.pv-table.sf-table { min-width:1100px; }
.pv-table.opt-table { min-width:1800px; }

/* Header group row */
.pv-table thead tr.th-group th {
    padding:9px 10px 5px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800;
    letter-spacing:.1em; text-transform:uppercase;
    background:#f7f8fc; border-bottom:none; white-space:nowrap;
}
/* Header col row */
.pv-table thead tr.th-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700;
    letter-spacing:.03em; text-transform:uppercase;
    background:#f4f6fb; color:#aab;
    border-bottom:2px solid #e8e8e8; white-space:nowrap;
}

/* Column group colors */
.g-meta   { color:#888 !important; }
.g-ohlc   { color:#1a56db !important; }
.g-pivot  { color:#c97f00 !important; }
.g-signal { color:#047857 !important; }
.g-ce     { color:#047857 !important; }
.g-pe     { color:#b91c1c !important; }

/* Column separators */
.sep-ohlc   { border-left:2px solid rgba(26,86,219,.15) !important; }
.sep-pivot  { border-left:2px solid rgba(245,166,35,.2) !important; }
.sep-signal { border-left:2px solid rgba(5,150,105,.2)  !important; }
.sep-ce     { border-left:2px solid rgba(5,150,105,.2)  !important; }
.sep-pe     { border-left:2px solid rgba(220,38,38,.2)  !important; }

/* Body cells */
.pv-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid #f5f5f5; vertical-align:middle;
    white-space:nowrap; color:#555;
}
.pv-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }
.tr-breakout  { background:rgba(5,150,105,.04) !important; }
.tr-breakdown { background:rgba(220,38,38,.04) !important; }

/* Cell styles */
.c-num   { font-size:9px; color:#ccc; }
.c-time  { font-size:12px; font-weight:700; color:#7DFF00; }
.c-sym   { font-size:11px; font-weight:700; color:#1a56db; }
.c-sym small { display:block; font-size:8px; color:#aab; font-weight:400; margin-top:1px; }
.c-o     { color:#aab; font-size:10px; }
.c-h     { color:#c0392b; font-weight:700; }
.c-l     { color:#27ae60; font-weight:700; }
.c-c     { color:#1a56db; font-weight:700; }
.c-vol   { font-size:9px; color:#ccc; }
.c-pp    { color:#c97f00; font-weight:800; }
.c-r1    { color:#c0392b; font-weight:700; }
.c-r2    { color:#e74c3c; font-size:9px; }
.c-s1    { color:#27ae60; font-weight:700; }
.c-s2    { color:#2ecc71; font-size:9px; }
.c-oi    { font-size:9px; color:#aab; }
.c-atm   { font-size:10px; color:#c97f00; font-weight:700; }

/* Signal badges */
.sig { display:inline-block; border-radius:5px; padding:3px 8px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; letter-spacing:.04em; white-space:nowrap; }
.sig-bull-strong { background:rgba(5,150,105,.15); color:#047857; border:1px solid rgba(5,150,105,.35); }
.sig-bull-mod    { background:rgba(5,150,105,.08); color:#059669; border:1px solid rgba(5,150,105,.2);  }
.sig-bull-weak   { background:rgba(5,150,105,.05); color:#34d399; border:1px solid rgba(5,150,105,.12); }
.sig-bear-strong { background:rgba(220,38,38,.12);  color:#b91c1c; border:1px solid rgba(220,38,38,.3);  }
.sig-bear-mod    { background:rgba(220,38,38,.07);  color:#dc2626; border:1px solid rgba(220,38,38,.18); }
.sig-bear-weak   { background:rgba(220,38,38,.04);  color:#ef4444; border:1px solid rgba(220,38,38,.1);  }
.sig-neutral     { background:#f4f6fb; color:#aab;   border:1px solid #e5e9f2; }

/* Match pills */
.mp-yes { display:inline-block; background:rgba(5,150,105,.1);  color:#047857; border:1px solid rgba(5,150,105,.3);  border-radius:4px; padding:2px 7px; font-size:9px; font-weight:800; }
.mp-no  { display:inline-block; background:#f7f8fc; color:#ccc; border:1px solid #e8e8e8; border-radius:4px; padding:2px 7px; font-size:9px; }
.mp-pp  { display:inline-block; background:rgba(245,166,35,.12); color:#c97f00; border:1px solid rgba(245,166,35,.3); border-radius:4px; padding:2px 7px; font-size:9px; font-weight:800; }

/* Loading / empty */
.pv-loading {
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; padding:60px 20px;
}
.pv-spinner {
    width:36px; height:36px; border:3px solid #f0f0f0;
    border-top:3px solid #7DFF00; border-radius:50%;
    animation:pvSpin 1s linear infinite;
}
.pv-loading-text { color:#aab; margin-top:12px; font-size:13px; }
.pv-empty { text-align:center; padding:56px 20px; color:#ccc; }
.pv-empty i { font-size:2.5rem; display:block; margin-bottom:12px; }
</style>

<div class="pv-wrap">

{{-- ══ HERO ══ --}}
<div class="pv-hero pv-anim">
    <div class="pv-hero-left">
        <h1 class="mb-0">Pivot Point <span>Analysis</span></h1>
        <p>Real-time pivot levels for Stock EQ, Futures, and ATM Options — calculated on live candle data during market hours.</p>
    </div>
    <div class="pv-hero-icon">
        <i class="las la-chart-bar"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="pv-filter-bar">
    <div class="pv-filter-inner">

        {{-- Instrument --}}
        <span class="pv-filter-label">Type</span>
        <div class="pv-inst-tabs">
            <button class="pv-inst-tab on-stock" data-inst="stock"
                    onclick="pvSetInst('stock',this)">
                <i class="las la-chart-line"></i> Stock EQ
            </button>
            <button class="pv-inst-tab" data-inst="fut"
                    onclick="pvSetInst('fut',this)">
                <i class="las la-fire"></i> Futures
            </button>
            <button class="pv-inst-tab" data-inst="option"
                    onclick="pvSetInst('option',this)">
                <i class="las la-layer-group"></i> Options (ATM)
            </button>
        </div>

        <div style="width:1px;height:28px;background:#e8e8e8;flex-shrink:0;"></div>

        {{-- Symbol --}}
        <span class="pv-filter-label">Symbol</span>
        <select id="pv-sym" class="pv-sym-select" onchange="pvLoad()">
            <option value="ALL">— All —</option>
        </select>

        <div style="width:1px;height:28px;background:#e8e8e8;flex-shrink:0;"></div>

        {{-- Date --}}
        <span class="pv-filter-label">Date</span>
        <div class="pv-date-wrap">
            <button class="pv-date-nav" onclick="pvShiftDate(-1)">‹</button>
            <input type="date" id="pv-date" class="pv-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="pvLoad()">
            <button class="pv-date-nav" onclick="pvShiftDate(1)">›</button>
            <button class="pv-date-nav pv-today-btn" onclick="pvToday()">TODAY</button>
            <span id="pv-date-badge"></span>
        </div>

        <button class="pv-load-btn" onclick="pvLoad()">
            <i class="las la-sync-alt"></i> Load
        </button>
        <button class="pv-auto-btn" id="pv-auto-btn" onclick="pvToggleAuto()">
            ▶ Auto
        </button>

        <div class="pv-filter-right">
            <span class="pv-info-text" id="pv-info"></span>
            <span class="pv-last-upd"  id="pv-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="pv-content">

    {{-- Config warning --}}
    <div class="pv-warn" id="pv-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="pv-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- ── STOCK / FUT TABLE ── --}}
    <div id="pv-sf-wrap">
        <div class="pv-card">
            <div class="pv-card-header">
                <div class="pv-card-title">
                    <span class="pv-inst-label pv-il-stock" id="pv-il">STOCK EQ</span>
                    Pivot Point Signals
                </div>
                <span class="pv-card-subtitle" id="pv-subtitle">Loading…</span>
            </div>
            <div class="pv-table-scroll">
                <table class="pv-table sf-table" id="pv-sf-table">
                    <thead>
                        <tr class="th-group">
                            <th colspan="3" class="g-meta">Info</th>
                            <th colspan="5" class="g-ohlc  sep-ohlc">OHLC + Volume</th>
                            <th colspan="5" class="g-pivot sep-pivot">▲ Pivot Levels</th>
                            <th colspan="4" class="g-signal sep-signal">Signal</th>
                        </tr>
                        <tr class="th-cols">
                            <th class="g-meta">#</th>
                            <th class="g-meta">Time</th>
                            <th class="g-meta">Symbol</th>

                            <th class="g-ohlc sep-ohlc">Open</th>
                            <th class="g-ohlc">High</th>
                            <th class="g-ohlc">Low</th>
                            <th class="g-ohlc">Close</th>
                            <th class="g-ohlc">Volume</th>

                            <th class="g-pivot sep-pivot">PP</th>
                            <th class="g-pivot" style="color:#27ae60!important;">S1</th>
                            <th class="g-pivot" style="color:#2ecc71!important;">S2</th>
                            <th class="g-pivot" style="color:#c0392b!important;">R1</th>
                            <th class="g-pivot" style="color:#e74c3c!important;">R2</th>

                            <th class="g-signal sep-signal">Signal</th>
                            <th class="g-signal">S1 Touch</th>
                            <th class="g-signal">R1 Touch</th>
                            <th class="g-signal" id="pv-oi-hdr" style="display:none;">OI</th>
                            <th class="g-signal">PP Cross</th>
                        </tr>
                    </thead>
                    <tbody id="pv-sf-body">
                        <tr><td colspan="17">
                            <div class="pv-loading">
                                <div class="pv-spinner"></div>
                                <div class="pv-loading-text">Detecting last available date…</div>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── OPTION TABLE ── --}}
    <div id="pv-opt-wrap" style="display:none;">
        <div class="pv-card">
            <div class="pv-card-header">
                <div class="pv-card-title">
                    <span class="pv-inst-label pv-il-option">OPTIONS</span>
                    ATM CE / PE Pivot Signals
                </div>
                <span class="pv-card-subtitle" id="pv-opt-subtitle">ATM Strike</span>
            </div>
            <div class="pv-table-scroll">
                <table class="pv-table opt-table">
                    <thead>
                        <tr class="th-group">
                            <th colspan="4" class="g-meta">Info</th>
                            <th colspan="8" class="g-ce sep-ce">▲ CE — ATM Call</th>
                            <th colspan="8" class="g-pe sep-pe">▼ PE — ATM Put</th>
                        </tr>
                        <tr class="th-cols">
                            <th class="g-meta">#</th>
                            <th class="g-meta">Time</th>
                            <th class="g-meta">Symbol</th>
                            <th class="g-meta">ATM Strike</th>

                            <th class="g-ce sep-ce">Open</th>
                            <th class="g-ce">High</th>
                            <th class="g-ce">Low</th>
                            <th class="g-ce">Close</th>
                            <th class="g-ce" style="color:#c97f00!important;">PP</th>
                            <th class="g-ce" style="color:#27ae60!important;">S1</th>
                            <th class="g-ce" style="color:#c0392b!important;">R1</th>
                            <th class="g-ce">Signal</th>

                            <th class="g-pe sep-pe">Open</th>
                            <th class="g-pe">High</th>
                            <th class="g-pe">Low</th>
                            <th class="g-pe">Close</th>
                            <th class="g-pe" style="color:#c97f00!important;">PP</th>
                            <th class="g-pe" style="color:#27ae60!important;">S1</th>
                            <th class="g-pe" style="color:#c0392b!important;">R1</th>
                            <th class="g-pe">Signal</th>
                        </tr>
                    </thead>
                    <tbody id="pv-opt-body">
                        <tr><td colspan="20">
                            <div class="pv-loading">
                                <div class="pv-spinner"></div>
                                <div class="pv-loading-text">Detecting last available date…</div>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>{{-- /.pv-content --}}
</div>{{-- /.pv-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  PIVOT ANALYSIS — JS  (no jQuery dependency)
// ═══════════════════════════════════════════════════════════════

var PV_TODAY   = '{{ now()->toDateString() }}';
var PV_ROUTES  = {
    stock    : '{{ route("pivot-analysis.stock.signals") }}',
    fut      : '{{ route("pivot-analysis.fut.signals") }}',
    option   : '{{ route("pivot-analysis.option.signals") }}',
    lastDate : '{{ route("pivot-analysis.last.date") }}'
};
var pvInst     = 'stock';
var pvTimer    = null;
var pvSymCache = {};

// Helper: set innerHTML / textContent of element by id
function pvHtml(id, html) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = html;
}
function pvText(id, txt) {
    var el = document.getElementById(id);
    if (el) el.textContent = txt;
}

// ═══════════════════════════════════════════════════════════════
//  BOOT — resolve last available date then auto-load
// ═══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    pvResolveLastDateAndLoad();
});

/**
 * Ask the backend for the latest trade_date that has real data for the
 * current instrument, then set the date picker and fire pvLoad().
 * Falls back to today if the request fails.
 */
function pvResolveLastDateAndLoad() {
    fetch(PV_ROUTES.lastDate + '?instrument=' + pvInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) {
            document.getElementById('pv-date').value = res.last_date;
        }
        pvLoad();
    })
    .catch(function () {
        // Network error — just load with whatever date is in the picker
        pvLoad();
    });
}

// ── Instrument switcher ───────────────────────────────────────

function pvSetInst(inst, btn) {
    pvInst = inst;
    document.querySelectorAll('.pv-inst-tab').forEach(function(b){
        b.className = 'pv-inst-tab';
    });
    btn.classList.add('on-' + inst);

    var isOpt = inst === 'option';
    document.getElementById('pv-sf-wrap').style.display  = isOpt ? 'none' : '';
    document.getElementById('pv-opt-wrap').style.display = isOpt ? ''     : 'none';

    var oiHdr = document.getElementById('pv-oi-hdr');
    if (oiHdr) oiHdr.style.display = inst === 'fut' ? '' : 'none';

    var il = document.getElementById('pv-il');
    if (il) {
        il.className   = 'pv-inst-label pv-il-' + inst;
        il.textContent = { stock:'STOCK EQ', fut:'FUTURES', option:'OPTIONS' }[inst];
    }

    var cacheKey = inst;
    if (pvSymCache[cacheKey] && pvSymCache[cacheKey].length) {
        pvRebuildSym(pvSymCache[cacheKey]);
    } else {
        pvRebuildSym([]);
    }

    // When switching instruments, re-detect the last date for that instrument
    pvResolveLastDateAndLoad();
}

// ── Date ─────────────────────────────────────────────────────

function pvGetDate() { return document.getElementById('pv-date').value; }
function pvGetSym()  { return document.getElementById('pv-sym').value; }

function pvShiftDate(d) {
    var picker = document.getElementById('pv-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > PV_TODAY) return;
    picker.value = s;
    pvLoad();
}

function pvToday() {
    document.getElementById('pv-date').value = PV_TODAY;
    pvLoad();
}

function pvUpdateDateBadge(isToday) {
    var el = document.getElementById('pv-date-badge');
    el.innerHTML = isToday
        ? '<span class="pv-live-badge">● Live</span>'
        : '<span class="pv-hist-badge">📅 Historical</span>';
}

// ── Symbol dropdown ───────────────────────────────────────────

function pvRebuildSym(symbols) {
    var sel  = document.getElementById('pv-sym');
    var prev = sel.value;
    sel.innerHTML = '<option value="ALL">— All Symbols —</option>';
    symbols.forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

// ── Auto refresh ──────────────────────────────────────────────

function pvToggleAuto() {
    var btn = document.getElementById('pv-auto-btn');
    if (pvTimer) {
        clearInterval(pvTimer); pvTimer = null;
        btn.textContent = '▶ Auto';
        btn.classList.remove('on');
    } else {
        pvTimer = setInterval(pvLoad, 15000);
        btn.textContent = '■ Stop';
        btn.classList.add('on');
        pvLoad();
    }
}

// ── Main loader ───────────────────────────────────────────────

function pvLoad() {
    var date = pvGetDate();
    var sym  = pvGetSym();

    if (date !== PV_TODAY && pvTimer) {
        clearInterval(pvTimer); pvTimer = null;
        document.getElementById('pv-auto-btn').textContent = '▶ Auto';
        document.getElementById('pv-auto-btn').classList.remove('on');
    }

    document.getElementById('pv-warn').classList.remove('show');

    var isOpt  = pvInst === 'option';
    var colsNm = isOpt ? 20 : 17;
    var loadTr = '<tr><td colspan="' + colsNm + '">'
        + '<div class="pv-loading"><div class="pv-spinner"></div>'
        + '<div class="pv-loading-text">Fetching pivot data for ' + date + '…</div></div>'
        + '</td></tr>';

    pvHtml(isOpt ? 'pv-opt-body' : 'pv-sf-body', loadTr);

    var params = new URLSearchParams({ symbol: sym, date: date });
    fetch(PV_ROUTES[pvInst] + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function(res) {
        pvUpdateDateBadge(res.is_today);

        if (res.no_config) {
            document.getElementById('pv-warn').classList.add('show');
            pvText('pv-warn-msg', res.message || '');
            pvHtml(isOpt ? 'pv-opt-body' : 'pv-sf-body', pvEmptyHtml(colsNm));
            return;
        }

        if (res.available_symbols && res.available_symbols.length) {
            pvSymCache[pvInst] = res.available_symbols;
            pvRebuildSym(res.available_symbols);
        }

        if (!res.success || !res.data || !res.data.length) {
            pvHtml(isOpt ? 'pv-opt-body' : 'pv-sf-body', pvEmptyHtml(colsNm, res.message));
            pvText('pv-info', '');
            return;
        }

        var total = res.data.reduce(function(a,d){ return a + (d.total_candles||0); }, 0);
        pvText('pv-info',     total + ' candles · ' + res.data.length + ' symbol(s)');
        pvText('pv-subtitle', date + ' · ' + (res.data[0].mode === 'detail' ? 'Full Day' : 'Latest'));
        pvText('pv-upd',      'Updated ' + new Date().toLocaleTimeString());

        if (isOpt) pvRenderOption(res.data);
        else       pvRenderSF(res.data);
    })
    .catch(function(err) {
        pvHtml(isOpt ? 'pv-opt-body' : 'pv-sf-body', pvEmptyHtml(colsNm, '⚠ ' + err.message));
    });
}

// ═══════════════════════════════════════════════════════════════
//  RENDERERS
// ═══════════════════════════════════════════════════════════════

function pvRenderSF(data) {
    var isFut = pvInst === 'fut';
    var html  = '';
    var n     = 1;

    data.forEach(function(d) {
        (d.signals || []).forEach(function(s, i) {
            var rowCls = pvRowCls(s.bias, s.signal);
            var zebra  = i % 2 === 0 ? 'tr-even' : 'tr-odd';

            html += '<tr class="' + rowCls + ' ' + zebra + '">'
                + '<td class="c-num">'  + n++ + '</td>'
                + '<td class="c-time">' + s.time + '</td>'
                + '<td class="c-sym">'  + esc(d.symbol)
                    + (d.expiry ? '<small>' + d.expiry + '</small>' : '')
                + '</td>'
                + '<td class="c-o sep-ohlc">₹' + fmt(s.open)  + '</td>'
                + '<td class="c-h">₹'           + fmt(s.high)  + '</td>'
                + '<td class="c-l">₹'           + fmt(s.low)   + '</td>'
                + '<td class="c-c">₹'           + fmt(s.close) + '</td>'
                + '<td class="c-vol">'           + fmtInt(s.volume) + '</td>'
                + '<td class="c-pp  sep-pivot">₹' + fmt(s.PP) + '</td>'
                + '<td class="c-s1">₹'            + fmt(s.S1) + '</td>'
                + '<td class="c-s2">₹'            + fmt(s.S2) + '</td>'
                + '<td class="c-r1">₹'            + fmt(s.R1) + '</td>'
                + '<td class="c-r2">₹'            + fmt(s.R2) + '</td>'
                + '<td class="sep-signal">'  + pvSigBadge(s.bias, s.signal, s.strength) + '</td>'
                + '<td>'                     + pvMatchPill(s.s1_match) + '</td>'
                + '<td>'                     + pvMatchPill(s.r1_match) + '</td>'
                + (isFut ? '<td class="c-oi">' + fmtInt(s.oi) + '</td>' : '')
                + '<td>'                     + pvPPCross(s.pp_cross) + '</td>'
                + '</tr>';
        });
    });

    pvHtml('pv-sf-body', html || pvEmptyHtml(17));
}

function pvRenderOption(data) {
    var html = '';
    var n    = 1;

    data.forEach(function(d) {
        var ceMap = {};
        var peMap = {};
        (d.ce_signals || []).forEach(function(s){ ceMap[s.time] = s; });
        (d.pe_signals || []).forEach(function(s){ peMap[s.time] = s; });

        var times = Object.keys(Object.assign({}, ceMap, peMap)).sort();
        times.forEach(function(t, i) {
            var ce     = ceMap[t] || null;
            var pe     = peMap[t] || null;
            var zebra  = i % 2 === 0 ? 'tr-even' : 'tr-odd';

            var ceCells = ce
                ? '<td class="c-o sep-ce">₹' + fmt(ce.open)  + '</td>'
                + '<td class="c-h">₹'          + fmt(ce.high)  + '</td>'
                + '<td class="c-l">₹'          + fmt(ce.low)   + '</td>'
                + '<td class="c-c">₹'          + fmt(ce.close) + '</td>'
                + '<td class="c-pp">₹'         + fmt(ce.PP)    + '</td>'
                + '<td class="c-s1">₹'         + fmt(ce.S1)    + '</td>'
                + '<td class="c-r1">₹'         + fmt(ce.R1)    + '</td>'
                + '<td>'                        + pvSigBadge(ce.bias, ce.signal, ce.strength) + '</td>'
                : '<td colspan="8" class="sep-ce" style="color:#ccc;font-size:9px;">— no CE data —</td>';

            var peCells = pe
                ? '<td class="c-o sep-pe">₹' + fmt(pe.open)  + '</td>'
                + '<td class="c-h">₹'          + fmt(pe.high)  + '</td>'
                + '<td class="c-l">₹'          + fmt(pe.low)   + '</td>'
                + '<td class="c-c">₹'          + fmt(pe.close) + '</td>'
                + '<td class="c-pp">₹'         + fmt(pe.PP)    + '</td>'
                + '<td class="c-s1">₹'         + fmt(pe.S1)    + '</td>'
                + '<td class="c-r1">₹'         + fmt(pe.R1)    + '</td>'
                + '<td>'                        + pvSigBadge(pe.bias, pe.signal, pe.strength) + '</td>'
                : '<td colspan="8" class="sep-pe" style="color:#ccc;font-size:9px;">— no PE data —</td>';

            html += '<tr class="' + zebra + '">'
                + '<td class="c-num">'  + n++ + '</td>'
                + '<td class="c-time">' + t + '</td>'
                + '<td class="c-sym">'  + esc(d.symbol)
                    + (d.expiry ? '<small>' + d.expiry + '</small>' : '') + '</td>'
                + '<td class="c-atm">₹' + fmtInt(d.atm_strike) + '</td>'
                + ceCells + peCells
                + '</tr>';
        });
    });

    pvHtml('pv-opt-body', html || pvEmptyHtml(20));
}

// ═══════════════════════════════════════════════════════════════
//  BADGE HELPERS
// ═══════════════════════════════════════════════════════════════

function pvSigBadge(bias, label, strength) {
    if (!bias || bias === 'NEUTRAL')
        return '<span class="sig sig-neutral">— ' + (label || 'At Pivot') + '</span>';

    if (bias === 'BULLISH') {
        if (strength === 'STRONG')   return '<span class="sig sig-bull-strong">▲ ' + label + '</span>';
        if (strength === 'MODERATE') return '<span class="sig sig-bull-mod">↑ '    + label + '</span>';
        return                              '<span class="sig sig-bull-weak">↑ '    + label + '</span>';
    }
    if (bias === 'BEARISH') {
        if (strength === 'STRONG')   return '<span class="sig sig-bear-strong">▼ ' + label + '</span>';
        if (strength === 'MODERATE') return '<span class="sig sig-bear-mod">↓ '    + label + '</span>';
        return                              '<span class="sig sig-bear-weak">↓ '    + label + '</span>';
    }
    return '<span class="sig sig-neutral">—</span>';
}

function pvRowCls(bias, label) {
    if (!label) return '';
    if (label === 'Above R1' || label === 'Above R2') return 'tr-breakout';
    if (label === 'Below S1' || label === 'Below S2') return 'tr-breakdown';
    return '';
}

function pvMatchPill(v) {
    if (v === null || v === undefined) return '<span style="color:#ccc;font-size:9px;">—</span>';
    return v ? '<span class="mp-yes">✓ YES</span>' : '<span class="mp-no">✗ NO</span>';
}

function pvPPCross(v) {
    return v ? '<span class="mp-pp">⟷ CROSS</span>' : '<span style="color:#ccc;font-size:9px;">—</span>';
}

// ── Number formatters ─────────────────────────────────────────

function fmt(v) {
    if (v == null || v === '') return '—';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 });
}
function fmtInt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits:0 });
}
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Empty HTML ────────────────────────────────────────────────

function pvEmptyHtml(cols, msg) {
    return '<tr><td colspan="' + cols + '">'
        + '<div class="pv-empty">'
        + '<i class="las la-chart-area"></i>'
        + (msg || 'No pivot data found for this date / symbol.')
        + '</div></td></tr>';
}
</script>
@endpush