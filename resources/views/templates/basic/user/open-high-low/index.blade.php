{{-- FILE: resources/views/themes/{active_theme}/user/open-high-low/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* ── BASE ── */
.ohl-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.ohl-wrap * { box-sizing:border-box; }
.ohl-wrap h1,.ohl-wrap h2,.ohl-wrap h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.ohl-wrap a { text-decoration:none; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes ohlUp  { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.ohl-anim { animation:ohlUp .5s ease both; }
@keyframes ohlSpin { to{ transform:rotate(360deg); } }

/* ── HERO ── */
.ohl-hero {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:32px 48px; display:flex; align-items:center;
    justify-content:space-between; gap:24px;
}
.ohl-hero-left h1 {
    font-size:clamp(24px,3.5vw,40px); font-weight:700;
    color:#1a1a2e; margin:0 0 8px; line-height:1.1;
}
.ohl-hero-left h1 span { color:#F5A623; }
.ohl-hero-left p { font-size:13px; color:#666; margin:0 0 10px; line-height:1.7; max-width:600px; }
.ohl-logic-pills { display:flex; flex-wrap:wrap; gap:6px; }
.ohl-pill {
    display:inline-block; padding:3px 10px; border-radius:4px;
    font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700;
}
.ohl-pill-oh { background:rgba(220,38,38,.08); color:#b91c1c; border:1px solid rgba(220,38,38,.25); }
.ohl-pill-ol { background:rgba(5,150,105,.08);  color:#047857; border:1px solid rgba(5,150,105,.25); }
.ohl-hero-icon {
    width:76px; height:76px; border-radius:16px;
    background:linear-gradient(135deg,#0f1b2d,#1a3050);
    display:flex; align-items:center; justify-content:center;
    font-size:32px; color:#F5A623; flex-shrink:0;
}
@media(max-width:768px){
    .ohl-hero { flex-direction:column; padding:24px 16px; text-align:center; }
    .ohl-logic-pills { justify-content:center; }
    .ohl-hero-icon { display:none; }
}

/* ── FILTER BAR ── */
.ohl-filter-bar {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:0 48px; position:sticky; top:0; z-index:200;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.ohl-filter-inner {
    display:flex; align-items:center; gap:12px;
    padding:12px 0; flex-wrap:wrap;
}
.ohl-filter-label {
    font-size:10.5px; color:#999; font-weight:700;
    text-transform:uppercase; letter-spacing:.07em; flex-shrink:0;
}
.ohl-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

/* Instrument tabs */
.ohl-inst-tabs { display:flex; gap:4px; }
.ohl-inst-tab {
    padding:7px 15px; border-radius:6px; border:1.5px solid #e5e9f2;
    font-size:12px; font-weight:700; color:#666; cursor:pointer;
    background:#fff; transition:all .2s; font-family:'Exo 2',sans-serif; white-space:nowrap;
}
.ohl-inst-tab:hover { border-color:#F5A623; color:#c97f00; }
.ohl-inst-tab.on-stock  { border-color:#059669; background:rgba(5,150,105,.08); color:#047857; }
.ohl-inst-tab.on-fut    { border-color:#F5A623; background:rgba(245,166,35,.08); color:#c97f00; }
.ohl-inst-tab.on-option { border-color:#7c3aed; background:rgba(124,58,237,.08); color:#6d28d9; }

/* Symbol select — single, styled like pivot */
.ohl-sym-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 30px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance:none; cursor:pointer; outline:none; min-width:140px;
}
.ohl-sym-select:focus { border-color:#F5A623; }

/* Date controls — same style as pivot */
.ohl-date-wrap { display:flex; align-items:center; gap:4px; }
.ohl-date-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
    color:#333; outline:none; cursor:pointer;
}
.ohl-date-input:focus { border-color:#F5A623; }
.ohl-date-nav {
    width:28px; height:32px; border:1.5px solid #e5e9f2; border-radius:6px;
    background:#fff; color:#888; cursor:pointer; font-weight:700; font-size:14px;
    display:flex; align-items:center; justify-content:center; transition:.2s;
}
.ohl-date-nav:hover { border-color:#F5A623; color:#F5A623; }
.ohl-today-btn { width:auto; padding:0 10px; font-size:10px; font-family:'Exo 2',sans-serif; font-weight:700; letter-spacing:.07em; }

/* Status badge */
.ohl-live-badge { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.ohl-hist-badge { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }

/* Tolerance */
.ohl-tol-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:700;
    color:#333; outline:none; width:70px;
}
.ohl-tol-input:focus { border-color:#F5A623; }

/* Buttons */
.ohl-analyze-btn {
    background:#059669; color:#fff; border:none; border-radius:8px;
    padding:8px 22px; font-family:'Rajdhani',sans-serif; font-size:14px;
    font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s; white-space:nowrap;
}
.ohl-analyze-btn:hover { background:#047857; }
.ohl-reset-btn {
    background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px;
    padding:7px 16px; font-size:12px; font-weight:700; cursor:pointer; transition:.2s;
    font-family:'Exo 2',sans-serif;
}
.ohl-reset-btn:hover { border-color:#F5A623; color:#c97f00; }

.ohl-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.ohl-info-text { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.ohl-upd-text  { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }

@media(max-width:768px){
    .ohl-filter-bar { padding:0 12px; }
    .ohl-filter-inner { gap:8px; }
    .ohl-filter-right { margin-left:0; width:100%; }
}

/* ── CONTENT ── */
.ohl-content { padding:28px 48px 64px; }
@media(max-width:768px){ .ohl-content { padding:16px 12px 48px; } }

/* Config warning */
.ohl-warn {
    background:#fff3e0; border:1px solid #ffcc80; border-radius:10px;
    padding:14px 20px; margin-bottom:20px;
    display:none; align-items:center; gap:12px; font-size:13px; color:#e65100;
}
.ohl-warn.show { display:flex; }
.ohl-warn i { font-size:18px; flex-shrink:0; }

/* ── TWO TABLE LAYOUT ── */
.ohl-tables-row {
    display:grid; grid-template-columns:1fr 1fr; gap:20px;
}
@media(max-width:900px){ .ohl-tables-row { grid-template-columns:1fr; } }

/* Table card */
.ohl-card {
    background:#fff; border-radius:12px; overflow:hidden;
    border:1px solid #e8e8e8; transition:box-shadow .25s;
}
.ohl-card:hover { box-shadow:0 8px 32px rgba(0,0,0,.08); }
.ohl-card.oh-card { border-top:3px solid #dc2626; }
.ohl-card.ol-card { border-top:3px solid #059669; }

.ohl-card-header {
    padding:14px 18px; border-bottom:1px solid #f0f0f0;
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    background:#fafafa;
}
.oh-card .ohl-card-header { background:linear-gradient(90deg,rgba(220,38,38,.05),#fafafa); }
.ol-card .ohl-card-header { background:linear-gradient(90deg,rgba(5,150,105,.05),#fafafa); }
.ohl-card-title {
    font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700;
}
.oh-card .ohl-card-title { color:#b91c1c; }
.ol-card .ohl-card-title { color:#047857; }
.ohl-count-pill {
    background:#1a1a2e; color:#fff; border-radius:20px;
    padding:2px 10px; font-size:11px; font-weight:700;
}
.ohl-tol-pill {
    background:rgba(245,166,35,.12); color:#c97f00;
    border:1px solid rgba(245,166,35,.3); border-radius:4px;
    padding:2px 8px; font-size:10px; font-weight:700;
    font-family:'JetBrains Mono',monospace;
}

/* Table scroll */
.ohl-table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

/* Table */
.ohl-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:520px; }
.ohl-table thead th {
    padding:9px 10px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700;
    text-transform:uppercase; letter-spacing:.05em;
    background:#f4f6fb; color:#aab;
    border-bottom:2px solid #e8e8e8; white-space:nowrap;
}
.ohl-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid #f5f5f5; vertical-align:middle;
    white-space:nowrap; color:#555;
}
.ohl-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }

/* Cell types */
.c-num   { font-size:9px; color:#ccc; }
.c-date  { font-size:11px; color:#F5A623; font-weight:700; }
.c-sym   { font-size:12px; font-weight:800; color:#1a56db; }
.c-sym small { display:block; font-size:8px; color:#aab; font-weight:400; margin-top:1px; }
.c-opt-ce { background:rgba(5,150,105,.1); color:#047857; border:1px solid rgba(5,150,105,.3); border-radius:4px; padding:1px 6px; font-size:9px; font-weight:800; }
.c-opt-pe { background:rgba(220,38,38,.08); color:#b91c1c; border:1px solid rgba(220,38,38,.25); border-radius:4px; padding:1px 6px; font-size:9px; font-weight:800; }
.c-open  { color:#555; font-weight:600; }
.c-h915  { color:#b91c1c; font-weight:700; }
.c-l915  { color:#047857; font-weight:700; }
.c-dh    { color:#1a56db; font-weight:700; }
.c-dl    { color:#c97f00; font-weight:700; }
.c-ltp   { color:#1a1a2e; font-weight:700; }
.c-up    { color:#059669; font-weight:700; }
.c-down  { color:#dc2626; font-weight:700; }
.c-neu   { color:#aab; }

/* Action badges */
.act-badge { display:inline-block; border-radius:5px; padding:3px 9px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; letter-spacing:.04em; }
.act-buy-pe  { background:rgba(220,38,38,.1);  color:#b91c1c; border:1px solid rgba(220,38,38,.3);  }
.act-buy-ce  { background:rgba(5,150,105,.1);  color:#047857; border:1px solid rgba(5,150,105,.3);  }
.act-sell-ce { background:rgba(220,38,38,.07); color:#dc2626; border:1px solid rgba(220,38,38,.2);  }
.act-sell-pe { background:rgba(245,166,35,.1); color:#c97f00; border:1px solid rgba(245,166,35,.3); }

/* Empty / Loading */
.ohl-empty { text-align:center; padding:48px 20px; color:#ccc; }
.ohl-empty i { font-size:2.5rem; display:block; margin-bottom:12px; color:#e5e9f2; }
.ohl-empty p { font-size:13px; }

.ohl-spinner-row {
    display:flex; align-items:center; justify-content:center;
    gap:12px; padding:40px; color:#aab; font-size:13px;
}
.ohl-spinner {
    width:28px; height:28px; border:3px solid #f0f0f0;
    border-top:3px solid #059669; border-radius:50%;
    animation:ohlSpin 1s linear infinite; flex-shrink:0;
}
</style>

<div class="ohl-wrap">

{{-- ══ HERO ══ --}}
<div class="ohl-hero ohl-anim">
    <div class="ohl-hero-left">
        <h1>Open=High / <span>Open=Low</span></h1>
        <p>
            Identify stocks where the opening candle has Open equal (or near equal)
            to its High or Low — a classic reversal signal in intraday trading.
        </p>
    </div>
    <div class="ohl-hero-icon">
        <i class="las la-exchange-alt"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="ohl-filter-bar">
    <div class="ohl-filter-inner">

        {{-- Instrument --}}
        <span class="ohl-filter-label">Type</span>
        <div class="ohl-inst-tabs">
            <button class="ohl-inst-tab on-stock" data-inst="stock"
                    onclick="ohlSetInst('stock',this)">
                <i class="las la-chart-line"></i> Stock EQ
            </button>
            <button class="ohl-inst-tab" data-inst="fut"
                    onclick="ohlSetInst('fut',this)">
                <i class="las la-fire"></i> Futures
            </button>
            <button class="ohl-inst-tab" data-inst="option"
                    onclick="ohlSetInst('option',this)">
                <i class="las la-layer-group"></i> Options
            </button>
        </div>

        <div class="ohl-sep"></div>

        {{-- Symbol — single select like pivot --}}
        <span class="ohl-filter-label">Symbol</span>
        <select id="ohl-sym" class="ohl-sym-select" onchange="ohlAnalyze()">
            <option value="ALL">— All —</option>
        </select>

        <div class="ohl-sep"></div>

        {{-- Single date with nav buttons — exactly like pivot --}}
        <span class="ohl-filter-label">Date</span>
        <div class="ohl-date-wrap">
            <button class="ohl-date-nav" onclick="ohlShiftDate(-1)">‹</button>
            <input type="date" id="ohl-date" class="ohl-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="ohlAnalyze()">
            <button class="ohl-date-nav" onclick="ohlShiftDate(1)">›</button>
            <button class="ohl-date-nav ohl-today-btn" onclick="ohlGoToday()">TODAY</button>
            <span id="ohl-date-badge"></span>
        </div>

        {{-- Tolerance --}}
        <span class="ohl-filter-label">Tol.</span>
        <input type="number" id="ohl-tol" class="ohl-tol-input"
               value="1" min="0" max="100" step="0.5"
               title="Tolerance in points (how close Open must be to High/Low)">
        <span style="font-size:11px;color:#aab;">pts</span>

        <button class="ohl-analyze-btn" onclick="ohlAnalyze()">
            <i class="las la-search"></i> Analyze
        </button>
        <button class="ohl-reset-btn" onclick="ohlReset()">↺ Reset</button>

        <div class="ohl-filter-right">
            <span class="ohl-info-text" id="ohl-info"></span>
            <span class="ohl-upd-text"  id="ohl-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="ohl-content">

    {{-- Config warning --}}
    <div class="ohl-warn" id="ohl-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="ohl-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Two table layout --}}
    <div class="ohl-tables-row">

        {{-- ── OPEN=HIGH card ── --}}
        <div class="ohl-card oh-card ohl-anim">
            <div class="ohl-card-header">
                <span class="ohl-card-title">🔴 Open = High</span>
                <span style="font-size:13px;color:#888;">→</span>
                <span class="act-badge act-buy-pe">BUY PE</span>
                <span class="ohl-count-pill" id="oh-count">0</span>
                <span class="ohl-tol-pill"   id="oh-tol" style="display:none;"></span>
            </div>
            <div class="ohl-table-scroll">
                <table class="ohl-table">
                    <thead>
                        <tr id="oh-thead-row">
                            <th>#</th><th>Date</th><th>Symbol</th>
                            <th>Open</th><th>High (09:15)</th><th>Day High</th>
                            <th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="oh-tbody">
                        <tr><td colspan="10">
                            <div class="ohl-empty">
                                <i class="las la-chart-area"></i>
                                <p>Detecting last available date…</p>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── OPEN=LOW card ── --}}
        <div class="ohl-card ol-card ohl-anim">
            <div class="ohl-card-header">
                <span class="ohl-card-title">🟢 Open = Low</span>
                <span style="font-size:13px;color:#888;">→</span>
                <span class="act-badge act-buy-ce">BUY CE</span>
                <span class="ohl-count-pill" id="ol-count">0</span>
                <span class="ohl-tol-pill"   id="ol-tol" style="display:none;"></span>
            </div>
            <div class="ohl-table-scroll">
                <table class="ohl-table">
                    <thead>
                        <tr id="ol-thead-row">
                            <th>#</th><th>Date</th><th>Symbol</th>
                            <th>Open</th><th>Low (09:15)</th><th>Day Low</th>
                            <th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="ol-tbody">
                        <tr><td colspan="10">
                            <div class="ohl-empty">
                                <i class="las la-chart-area"></i>
                                <p>Detecting last available date…</p>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>{{-- /.ohl-tables-row --}}
</div>{{-- /.ohl-content --}}
</div>{{-- /.ohl-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  Open=High / Open=Low — JS (no jQuery)
// ═══════════════════════════════════════════════════════════════

var OHL_ANALYZE  = '{{ route("open-high-low.analyze") }}';
var OHL_SYMBOLS  = '{{ route("open-high-low.symbols") }}';
var OHL_LASTDATE = '{{ route("open-high-low.last.date") }}';
var OHL_TODAY    = '{{ now()->toDateString() }}';

var ohlInst     = 'stock';
var ohlSymCache = {};

// helpers
function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

// ═══════════════════════════════════════════════════════════════
//  BOOT — detect last available date then auto-analyze
// ═══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    ohlResolveLastDateAndLoad();
});

function ohlResolveLastDateAndLoad() {
    fetch(OHL_LASTDATE + '?instrument=' + ohlInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) {
            el('ohl-date').value = res.last_date;
        }
        // Load symbols then analyze
        ohlLoadSymbols(function () { ohlAnalyze(); });
    })
    .catch(function () {
        ohlLoadSymbols(function () { ohlAnalyze(); });
    });
}

// ── Instrument switcher ───────────────────────────────────────

function ohlSetInst(inst, btn) {
    ohlInst = inst;
    document.querySelectorAll('.ohl-inst-tab').forEach(function (b) {
        b.className = 'ohl-inst-tab';
    });
    btn.classList.add('on-' + inst);

    // Update table headers for options type column
    var isOpt = inst === 'option';
    var optTh = isOpt ? '<th>Type</th>' : '';
    el('oh-thead-row').innerHTML =
        '<th>#</th><th>Date</th><th>Symbol</th>' + optTh
        + '<th>Open</th><th>High (09:15)</th><th>Day High</th>'
        + '<th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>';
    el('ol-thead-row').innerHTML =
        '<th>#</th><th>Date</th><th>Symbol</th>' + optTh
        + '<th>Open</th><th>Low (09:15)</th><th>Day Low</th>'
        + '<th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>';

    // Re-detect last date for the new instrument, then reload symbols + analyze
    fetch(OHL_LASTDATE + '?instrument=' + ohlInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) el('ohl-date').value = res.last_date;
        ohlLoadSymbols(function () { ohlAnalyze(); });
    })
    .catch(function () {
        ohlLoadSymbols(function () { ohlAnalyze(); });
    });
}

// ── Date helpers — identical pattern to pivot ─────────────────

function ohlGetDate() { return el('ohl-date').value; }

function ohlShiftDate(d) {
    var picker = el('ohl-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > OHL_TODAY) return;
    picker.value = s;
    ohlAnalyze();
}

function ohlGoToday() {
    el('ohl-date').value = OHL_TODAY;
    ohlAnalyze();
}

function ohlUpdateDateBadge(isToday) {
    el('ohl-date-badge').innerHTML = isToday
        ? '<span class="ohl-live-badge">● Live</span>'
        : '<span class="ohl-hist-badge">📅 Historical</span>';
}

// ── Symbol dropdown — single select like pivot ────────────────

function ohlLoadSymbols(callback) {
    var key = ohlInst;
    if (ohlSymCache[key] && ohlSymCache[key].length) {
        ohlRebuildSym(ohlSymCache[key]);
        if (callback) callback();
        return;
    }

    fetch(OHL_SYMBOLS + '?instrument=' + ohlInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.no_config) {
            ohlShowWarn('No active analysis config found.');
            ohlRebuildSym([]);
        } else {
            ohlHideWarn();
            ohlSymCache[key] = res.symbols || [];
            ohlRebuildSym(ohlSymCache[key]);
        }
        if (callback) callback();
    })
    .catch(function () {
        if (callback) callback();
    });
}

function ohlRebuildSym(symbols) {
    var sel  = el('ohl-sym');
    var prev = sel.value;

    // Build options: "All" + individual symbols
    var opts = '<option value="ALL">— All Symbols —</option>';
    symbols.forEach(function (s) {
        opts += '<option value="' + s + '"' + (s === prev ? ' selected' : '') + '>' + s + '</option>';
    });
    sel.innerHTML = opts;
    // Restore previous selection if it still exists
    if (prev && prev !== 'ALL') {
        sel.value = prev;
        if (sel.value !== prev) sel.value = 'ALL'; // fallback
    }
}

// ── Analyze ───────────────────────────────────────────────────

function ohlAnalyze() {
    var date = ohlGetDate();
    var sym  = el('ohl-sym').value;
    var tol  = parseFloat(el('ohl-tol').value) || 1;

    if (!date) { return; }

    ohlHideWarn();
    ohlShowLoading();

    var params = new URLSearchParams({
        instrument : ohlInst,
        date       : date,
        tolerance  : tol,
    });
    // Send symbol only when a specific one is chosen
    if (sym && sym !== 'ALL') {
        params.append('symbol', sym);
    }

    fetch(OHL_ANALYZE + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        // Update date badge
        if (typeof res.is_today !== 'undefined') {
            ohlUpdateDateBadge(res.is_today);
        }

        // Rebuild symbol list if server sends available_symbols
        if (res.available_symbols && res.available_symbols.length) {
            ohlSymCache[ohlInst] = res.available_symbols;
            ohlRebuildSym(res.available_symbols);
            // Restore the symbol selection after rebuild
            if (sym && sym !== 'ALL') el('ohl-sym').value = sym;
        }

        if (res.no_config) {
            ohlShowWarn(res.message);
            ohlEmptyBoth('—', 10);
            return;
        }

        if (!res.success || !res.data || !res.data.length) {
            ohlEmptyBoth(res.message || 'No signals found for this date.', 10);
            txt('ohl-info', '');
            ohlUpdateCounts(0, 0, tol);
            return;
        }

        var ohRows = res.data.filter(function (r) { return r.signal === 'OPEN=HIGH'; });
        var olRows = res.data.filter(function (r) { return r.signal === 'OPEN=LOW';  });

        ohlUpdateCounts(ohRows.length, olRows.length, res.tolerance);

        el('ohl-info').innerHTML =
            '<span style="color:#b91c1c;">O=H: ' + ohRows.length + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:#047857;">O=L: ' + olRows.length + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:#c97f00;">Tol: ±' + parseFloat(res.tolerance).toFixed(1) + ' pts</span>'
            + ' · ' + res.instrument;
        txt('ohl-upd', 'Updated ' + new Date().toLocaleTimeString());

        ohlRenderOH(ohRows);
        ohlRenderOL(olRows);
    })
    .catch(function (err) {
        ohlEmptyBoth('⚠ ' + err.message, 10);
    });
}

// ── Renderers ─────────────────────────────────────────────────

function ohlRenderOH(rows) {
    var isOpt = ohlInst === 'option';
    var cols  = isOpt ? 11 : 10;
    if (!rows.length) {
        html('oh-tbody', ohlEmptyHtml(cols, 'No Open=High signals found for this date.'));
        return;
    }
    var h = '';
    rows.forEach(function (r, i) {
        h += '<tr class="' + (i % 2 === 0 ? 'tr-even' : 'tr-odd') + '">'
            + '<td class="c-num">'  + (i + 1) + '</td>'
            + '<td class="c-date">' + r.date   + '</td>'
            + '<td class="c-sym">'  + esc(r.symbol)
                + (r.expiry ? '<small>' + r.expiry + '</small>' : '') + '</td>'
            + (isOpt ? '<td>' + ohlOptBadge(r.opt_type) + '</td>' : '')
            + '<td class="c-open">₹' + f(r.open)      + '</td>'
            + '<td class="c-h915">₹' + f(r.high_open)  + '</td>'
            + '<td class="c-dh">₹'   + f(r.day_high)   + '</td>'
            + '<td class="c-ltp">₹'  + f(r.ltp)        + '</td>'
            + '<td>'                  + ohlChangeTd(r.change)       + '</td>'
            + '<td>'                  + ohlPctTd(r.change_pct)      + '</td>'
            + '<td>'                  + ohlActionBadge(r.trade_action) + '</td>'
            + '</tr>';
    });
    html('oh-tbody', h);
}

function ohlRenderOL(rows) {
    var isOpt = ohlInst === 'option';
    var cols  = isOpt ? 11 : 10;
    if (!rows.length) {
        html('ol-tbody', ohlEmptyHtml(cols, 'No Open=Low signals found for this date.'));
        return;
    }
    var h = '';
    rows.forEach(function (r, i) {
        h += '<tr class="' + (i % 2 === 0 ? 'tr-even' : 'tr-odd') + '">'
            + '<td class="c-num">'  + (i + 1) + '</td>'
            + '<td class="c-date">' + r.date   + '</td>'
            + '<td class="c-sym">'  + esc(r.symbol)
                + (r.expiry ? '<small>' + r.expiry + '</small>' : '') + '</td>'
            + (isOpt ? '<td>' + ohlOptBadge(r.opt_type) + '</td>' : '')
            + '<td class="c-open">₹' + f(r.open)    + '</td>'
            + '<td class="c-l915">₹' + f(r.low_open) + '</td>'
            + '<td class="c-dl">₹'   + f(r.day_low)  + '</td>'
            + '<td class="c-ltp">₹'  + f(r.ltp)      + '</td>'
            + '<td>'                  + ohlChangeTd(r.change)       + '</td>'
            + '<td>'                  + ohlPctTd(r.change_pct)      + '</td>'
            + '<td>'                  + ohlActionBadge(r.trade_action) + '</td>'
            + '</tr>';
    });
    html('ol-tbody', h);
}

// ── UI helpers ────────────────────────────────────────────────

function ohlShowLoading() {
    var spinHtml = '<tr><td colspan="11"><div class="ohl-spinner-row">'
        + '<div class="ohl-spinner"></div>Analysing 09:15 candles…</div></td></tr>';
    html('oh-tbody', spinHtml);
    html('ol-tbody', spinHtml);
}

function ohlEmptyBoth(msg, cols) {
    var h = ohlEmptyHtml(cols || 10, msg);
    html('oh-tbody', h);
    html('ol-tbody', h);
    ohlUpdateCounts(0, 0, null);
}

function ohlEmptyHtml(cols, msg) {
    return '<tr><td colspan="' + cols + '">'
        + '<div class="ohl-empty">'
        + '<i class="las la-chart-area"></i>'
        + '<p>' + (msg || 'No data found.') + '</p>'
        + '</div></td></tr>';
}

function ohlUpdateCounts(oh, ol, tol) {
    txt('oh-count', oh);
    txt('ol-count', ol);
    if (tol !== null && tol !== undefined) {
        var tolTxt = '±' + parseFloat(tol).toFixed(1) + ' pts';
        txt('oh-tol', tolTxt); el('oh-tol').style.display = '';
        txt('ol-tol', tolTxt); el('ol-tol').style.display = '';
    } else {
        el('oh-tol').style.display = 'none';
        el('ol-tol').style.display = 'none';
    }
}

function ohlShowWarn(msg) {
    el('ohl-warn').classList.add('show');
    txt('ohl-warn-msg', msg || '');
}
function ohlHideWarn() { el('ohl-warn').classList.remove('show'); }

function ohlReset() {
    // Re-detect last available date on reset
    fetch(OHL_LASTDATE + '?instrument=' + ohlInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        el('ohl-date').value = res.last_date || OHL_TODAY;
        el('ohl-tol').value  = '1';
        el('ohl-sym').value  = 'ALL';
        ohlHideWarn();
        ohlAnalyze();
    })
    .catch(function () {
        el('ohl-date').value = OHL_TODAY;
        el('ohl-tol').value  = '1';
        el('ohl-sym').value  = 'ALL';
        ohlHideWarn();
        ohlAnalyze();
    });
}

// ── Badge helpers ─────────────────────────────────────────────

function ohlActionBadge(action) {
    var map = {
        'BUY PE':  '<span class="act-badge act-buy-pe">BUY PE</span>',
        'BUY CE':  '<span class="act-badge act-buy-ce">BUY CE</span>',
        'SELL CE': '<span class="act-badge act-sell-ce">SELL CE</span>',
        'SELL PE': '<span class="act-badge act-sell-pe">SELL PE</span>',
    };
    return map[action] || '<span style="color:#aab;font-size:9px;">' + (action || '—') + '</span>';
}

function ohlOptBadge(type) {
    if (type === 'CE') return '<span class="c-opt-ce">CE</span>';
    if (type === 'PE') return '<span class="c-opt-pe">PE</span>';
    return '<span style="color:#aab;">—</span>';
}

function ohlChangeTd(v) {
    var n = parseFloat(v) || 0;
    if (n > 0) return '<span class="c-up">▲ ₹'   + f(n)            + '</span>';
    if (n < 0) return '<span class="c-down">▼ ₹' + f(Math.abs(n))  + '</span>';
    return '<span class="c-neu">₹' + f(n) + '</span>';
}

function ohlPctTd(v) {
    var n = parseFloat(v) || 0;
    if (n > 0) return '<span class="c-up">+'  + f(n) + '%</span>';
    if (n < 0) return '<span class="c-down">' + f(n) + '%</span>';
    return '<span class="c-neu">' + f(n) + '%</span>';
}

function f(v)   { return parseFloat(v || 0).toFixed(2); }
function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>
@endpush