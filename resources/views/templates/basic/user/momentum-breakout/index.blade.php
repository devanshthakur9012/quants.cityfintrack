{{-- FILE: resources/views/themes/{active_theme}/user/momentum-breakout/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* ── BASE ── */
.mb-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.mb-wrap * { box-sizing:border-box; }
.mb-wrap h1,.mb-wrap h2,.mb-wrap h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.mb-wrap a { text-decoration:none; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes mbUp   { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.mb-anim { animation:mbUp .5s ease both; }
@keyframes mbSpin { to{ transform:rotate(360deg); } }

/* ── HERO ── */
.mb-hero {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:32px 48px; display:flex; align-items:center;
    justify-content:space-between; gap:24px;
}
.mb-hero-left h1 {
    font-size:clamp(24px,3.5vw,40px); font-weight:700;
    color:#1a1a2e; margin:0 0 8px; line-height:1.1;
}
.mb-hero-left h1 span { color:#7DFF00; }
.mb-hero-left p { font-size:13px; color:#666; margin:0; line-height:1.7; max-width:620px; }
.mb-hero-icon {
    width:76px; height:76px; border-radius:16px;
    background:linear-gradient(135deg,#0f1b2d,#1a3050);
    display:flex; align-items:center; justify-content:center;
    font-size:32px; color:#7DFF00; flex-shrink:0;
}
@media(max-width:768px){
    .mb-hero { flex-direction:column; padding:24px 16px; text-align:center; }
    .mb-hero-icon { display:none; }
}

/* ── FILTER BAR ── */
.mb-filter-bar {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:0 48px; position:sticky; top:0; z-index:200;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.mb-filter-inner {
    display:flex; align-items:center; gap:12px;
    padding:12px 0; flex-wrap:wrap;
}
.mb-filter-label {
    font-size:10.5px; color:#999; font-weight:700;
    text-transform:uppercase; letter-spacing:.07em; flex-shrink:0;
}
.mb-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

/* Instrument tabs */
.mb-inst-tabs { display:flex; gap:4px; }
.mb-inst-tab {
    padding:7px 15px; border-radius:6px; border:1.5px solid #e5e9f2;
    font-size:12px; font-weight:700; color:#666; cursor:pointer;
    background:#fff; transition:all .2s; font-family:'Exo 2',sans-serif; white-space:nowrap;
}
.mb-inst-tab:hover { border-color:#7DFF00; color:#c97f00; }
.mb-inst-tab.on-stock  { border-color:#059669; background:rgba(5,150,105,.08); color:#047857; }
.mb-inst-tab.on-fut    { border-color:#7DFF00; background:rgba(245,166,35,.08); color:#c97f00; }
.mb-inst-tab.on-option { border-color:#7c3aed; background:rgba(124,58,237,.08); color:#6d28d9; }

/* Symbol select — single like pivot */
.mb-sym-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 30px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance:none; cursor:pointer; outline:none; min-width:140px;
}
.mb-sym-select:focus { border-color:#7DFF00; }

/* Date controls — same as pivot */
.mb-date-wrap { display:flex; align-items:center; gap:4px; }
.mb-date-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
    color:#333; outline:none; cursor:pointer;
}
.mb-date-input:focus { border-color:#7DFF00; }
.mb-date-nav {
    width:28px; height:32px; border:1.5px solid #e5e9f2; border-radius:6px;
    background:#fff; color:#888; cursor:pointer; font-weight:700; font-size:14px;
    display:flex; align-items:center; justify-content:center; transition:.2s;
}
.mb-date-nav:hover { border-color:#7DFF00; color:#7DFF00; }
.mb-today-btn { width:auto; padding:0 10px; font-size:10px; font-family:'Exo 2',sans-serif; font-weight:700; letter-spacing:.07em; }

/* Status badge */
.mb-live-badge { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.mb-hist-badge { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }

/* Move % dropdown */
.mb-threshold-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 24px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'JetBrains Mono',monospace;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 8px center;
    appearance:none; cursor:pointer; outline:none; min-width:90px;
}
.mb-threshold-select:focus { border-color:#7DFF00; }

/* Checkbox */
.mb-nt-wrap {
    display:flex; align-items:center; gap:6px;
    font-size:12px; color:#666; font-weight:600; cursor:pointer; white-space:nowrap;
}
.mb-nt-wrap input { accent-color:#7DFF00; cursor:pointer; }

/* Buttons */
.mb-scan-btn {
    background:#7DFF00; color:#000; border:none; border-radius:8px;
    padding:8px 22px; font-family:'Rajdhani',sans-serif; font-size:14px;
    font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s; white-space:nowrap;
}
.mb-scan-btn:hover { background:#d4890e; }
.mb-reset-btn {
    background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px;
    padding:7px 16px; font-size:12px; font-weight:700; cursor:pointer; transition:.2s;
    font-family:'Exo 2',sans-serif;
}
.mb-reset-btn:hover { border-color:#7DFF00; color:#c97f00; }

.mb-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.mb-info-text { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.mb-upd-text  { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }

@media(max-width:768px){
    .mb-filter-bar { padding:0 12px; }
    .mb-filter-inner { gap:8px; }
    .mb-filter-right { margin-left:0; width:100%; }
}

/* ── CONTENT ── */
.mb-content { padding:28px 48px 64px; }
@media(max-width:768px){ .mb-content { padding:16px 12px 48px; } }

/* Config warning */
.mb-warn {
    background:#fff3e0; border:1px solid #ffcc80; border-radius:10px;
    padding:14px 20px; margin-bottom:20px;
    display:none; align-items:center; gap:12px; font-size:13px; color:#e65100;
}
.mb-warn.show { display:flex; }
.mb-warn i { font-size:18px; flex-shrink:0; }

/* ── STATS ROW ── */
.mb-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
@media(max-width:768px){ .mb-stats { grid-template-columns:repeat(2,1fr); } }
.mb-stat-card {
    background:#fff; border-radius:12px; border:1px solid #e8e8e8;
    padding:16px 18px; border-left:3px solid #e8e8e8;
}
.mb-stat-card.s-total { border-left-color:#1a56db; }
.mb-stat-card.s-ce    { border-left-color:#059669; }
.mb-stat-card.s-pe    { border-left-color:#dc2626; }
.mb-stat-card.s-nt    { border-left-color:#ccc;    }
.mb-stat-label { font-size:10px; font-weight:700; text-transform:uppercase;
    letter-spacing:.07em; color:#aab; margin-bottom:6px; }
.mb-stat-val { font-family:'JetBrains Mono',monospace; font-size:26px; font-weight:700; color:#1a1a2e; }
.s-ce .mb-stat-val { color:#047857; }
.s-pe .mb-stat-val { color:#b91c1c; }
.s-nt .mb-stat-val { color:#aab; }

/* ── TABLE CARD ── */
.mb-card {
    background:#fff; border-radius:12px; border:1px solid #e8e8e8; overflow:hidden;
}
.mb-card-header {
    padding:14px 20px; border-bottom:1px solid #f0f0f0;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;
    background:#fafafa;
}
.mb-card-title {
    font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:#1a1a2e;
    display:flex; align-items:center; gap:8px;
}
.mb-card-subtitle { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.mb-inst-label {
    display:inline-block; padding:3px 10px; border-radius:4px;
    font-size:11px; font-weight:700; letter-spacing:.06em;
}
.mb-il-stock  { background:rgba(5,150,105,.1);  color:#047857; border:1px solid rgba(5,150,105,.3); }
.mb-il-fut    { background:rgba(245,166,35,.1);  color:#c97f00; border:1px solid rgba(245,166,35,.3); }
.mb-il-option { background:rgba(124,58,237,.1);  color:#6d28d9; border:1px solid rgba(124,58,237,.3); }

/* Table scroll */
.mb-table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

/* Table */
.mb-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:900px; }
.mb-table thead tr.th-group th {
    padding:9px 10px 5px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800;
    letter-spacing:.1em; text-transform:uppercase;
    background:#f7f8fc; border-bottom:none; white-space:nowrap;
}
.mb-table thead tr.th-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700;
    letter-spacing:.03em; text-transform:uppercase;
    background:#f4f6fb; color:#aab; border-bottom:2px solid #e8e8e8; white-space:nowrap;
}
.g-info   { color:#1a56db !important; }
.g-signal { color:#c97f00 !important; }
.sep-signal { border-left:2px solid rgba(245,166,35,.2) !important; }

.mb-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid #f5f5f5; vertical-align:middle;
    white-space:nowrap; color:#555;
}
.mb-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }
.tr-ce   { background:rgba(5,150,105,.03) !important; }
.tr-pe   { background:rgba(220,38,38,.03) !important; }
.tr-nt   { background:#fbfcff !important; opacity:.65; }

/* Cells */
.c-num  { font-size:9px; color:#ccc; }
.c-date { font-size:11px; font-weight:700; color:#7DFF00; }
.c-sym  { font-size:12px; font-weight:800; color:#1a56db; }
.c-sym small { display:block; font-size:8px; color:#aab; font-weight:400; margin-top:1px; }
.c-open { color:#555; font-weight:600; }
.c-time { color:#c97f00; font-weight:700; }
.c-px   { color:#1a1a2e; font-weight:700; }
.c-dh   { color:#b91c1c; font-weight:600; }
.c-dl   { color:#047857; font-weight:600; }

/* Signal badges */
.sig-ce { display:inline-block; background:rgba(5,150,105,.12); color:#047857;
    border:1px solid rgba(5,150,105,.35); border-radius:6px; padding:3px 10px;
    font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-pe { display:inline-block; background:rgba(220,38,38,.1); color:#b91c1c;
    border:1px solid rgba(220,38,38,.35); border-radius:6px; padding:3px 10px;
    font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-nt { display:inline-block; background:#f4f6fb; color:#aab;
    border:1px solid #e5e9f2; border-radius:6px; padding:3px 10px;
    font-family:'Exo 2',sans-serif; font-size:10px; font-weight:600; }

/* Pct */
.pct-up   { color:#059669; font-weight:700; }
.pct-down { color:#dc2626; font-weight:700; }
.pct-neu  { color:#aab; }

/* Empty / Loading */
.mb-empty { text-align:center; padding:56px 20px; color:#ccc; }
.mb-empty i { font-size:2.5rem; display:block; margin-bottom:12px; color:#e5e9f2; }
.mb-empty p { font-size:13px; }

.mb-spinner-row {
    display:flex; align-items:center; justify-content:center;
    gap:12px; padding:48px; color:#aab; font-size:13px;
}
.mb-spinner {
    width:28px; height:28px; border:3px solid #f0f0f0;
    border-top:3px solid #7DFF00; border-radius:50%;
    animation:mbSpin 1s linear infinite; flex-shrink:0;
}
</style>

<div class="mb-wrap">

{{-- ══ HERO ══ --}}
<div class="mb-hero mb-anim">
    <div class="mb-hero-left">
        <h1>Momentum <span>Breakout</span> Scanner</h1>
        <p>
            Scan intraday candles to detect when price moves beyond a set percentage
            threshold from the day's open — generating BUY CE or BUY PE signals.
        </p>
    </div>
    <div class="mb-hero-icon">
        <i class="las la-bolt"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="mb-filter-bar">
    <div class="mb-filter-inner">

        {{-- Instrument --}}
        <span class="mb-filter-label">Type</span>
        <div class="mb-inst-tabs">
            <button class="mb-inst-tab on-stock" data-inst="stock"
                    onclick="mbSetInst('stock',this)">
                <i class="las la-chart-line"></i> Stock EQ
            </button>
            <button class="mb-inst-tab" data-inst="fut"
                    onclick="mbSetInst('fut',this)">
                <i class="las la-fire"></i> Futures
            </button>
            <button class="mb-inst-tab" data-inst="option"
                    onclick="mbSetInst('option',this)">
                <i class="las la-layer-group"></i> Options
            </button>
        </div>

        <div class="mb-sep"></div>

        {{-- Symbol — single select like pivot --}}
        <span class="mb-filter-label">Symbol</span>
        <select id="mb-sym" class="mb-sym-select" onchange="mbScan()">
            <option value="ALL">— All —</option>
        </select>

        <div class="mb-sep"></div>

        {{-- Single date with nav buttons — same as pivot --}}
        <span class="mb-filter-label">Date</span>
        <div class="mb-date-wrap">
            <button class="mb-date-nav" onclick="mbShiftDate(-1)">‹</button>
            <input type="date" id="mb-date" class="mb-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="mbScan()">
            <button class="mb-date-nav" onclick="mbShiftDate(1)">›</button>
            <button class="mb-date-nav mb-today-btn" onclick="mbGoToday()">TODAY</button>
            <span id="mb-date-badge"></span>
        </div>

        <div class="mb-sep"></div>

        {{-- Move % --}}
        <span class="mb-filter-label">Move %</span>
        <select id="mb-threshold" class="mb-threshold-select" onchange="mbScan()">
            <option value="0.5">0.5%</option>
            <option value="0.75">0.75%</option>
            <option value="1.0" selected>1.0%</option>
            <option value="1.25">1.25%</option>
            <option value="1.5">1.5%</option>
            <option value="2.0">2.0%</option>
            <option value="2.5">2.5%</option>
            <option value="3.0">3.0%</option>
        </select>

        {{-- Show No-Trade --}}
        <label class="mb-nt-wrap">
            <input type="checkbox" id="mb-show-nt" onchange="mbScan()">
            Show No-Trade
        </label>

        <button class="mb-scan-btn" onclick="mbScan()">
            <i class="las la-search"></i> Scan
        </button>
        <button class="mb-reset-btn" onclick="mbReset()">↺ Reset</button>

        <div class="mb-filter-right">
            <span class="mb-info-text" id="mb-info"></span>
            <span class="mb-upd-text"  id="mb-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="mb-content">

    {{-- Config warning --}}
    <div class="mb-warn" id="mb-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="mb-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="mb-stats mb-anim">
        <div class="mb-stat-card s-total">
            <div class="mb-stat-label">Total Records</div>
            <div class="mb-stat-val" id="st-total">—</div>
        </div>
        <div class="mb-stat-card s-ce">
            <div class="mb-stat-label">▲ BUY CE</div>
            <div class="mb-stat-val" id="st-ce">—</div>
        </div>
        <div class="mb-stat-card s-pe">
            <div class="mb-stat-label">▼ BUY PE</div>
            <div class="mb-stat-val" id="st-pe">—</div>
        </div>
        <div class="mb-stat-card s-nt">
            <div class="mb-stat-label">No Trade</div>
            <div class="mb-stat-val" id="st-nt">—</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="mb-card mb-anim">
        <div class="mb-card-header">
            <div class="mb-card-title">
                <span class="mb-inst-label mb-il-stock" id="mb-il">STOCK EQ</span>
                Breakout Signals
            </div>
            <span class="mb-card-subtitle" id="mb-subtitle">Detecting last available date…</span>
        </div>
        <div class="mb-table-scroll">
            <table class="mb-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="4" class="g-info">Market Info</th>
                        <th colspan="5" class="g-signal sep-signal">⚡ Breakout Signal</th>
                    </tr>
                    <tr class="th-cols">
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>Day Open</th>
                        <th class="sep-signal">Signal</th>
                        <th>Time</th>
                        <th>Price</th>
                        <th>Chg %</th>
                        <th>Day H / L</th>
                    </tr>
                </thead>
                <tbody id="mb-tbody">
                    <tr><td colspan="9">
                        <div class="mb-spinner-row">
                            <div class="mb-spinner"></div>
                            Detecting last available date…
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.mb-content --}}
</div>{{-- /.mb-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  Momentum Breakout Scanner — JS (no jQuery)
// ═══════════════════════════════════════════════════════════════

var MB_SCAN     = '{{ route("momentum-breakout.scan") }}';
var MB_SYM      = '{{ route("momentum-breakout.symbols") }}';
var MB_LASTDATE = '{{ route("momentum-breakout.last.date") }}';
var MB_TODAY    = '{{ now()->toDateString() }}';

var mbInst     = 'stock';
var mbSymCache = {};

function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

// ═══════════════════════════════════════════════════════════════
//  BOOT — detect last available date then auto-scan
// ═══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    mbResolveLastDateAndLoad();
});

function mbResolveLastDateAndLoad() {
    fetch(MB_LASTDATE + '?instrument=' + mbInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) el('mb-date').value = res.last_date;
        mbLoadSymbols(function () { mbScan(); });
    })
    .catch(function () {
        mbLoadSymbols(function () { mbScan(); });
    });
}

// ── Instrument ────────────────────────────────────────────────

function mbSetInst(inst, btn) {
    mbInst = inst;
    document.querySelectorAll('.mb-inst-tab').forEach(function (b) {
        b.className = 'mb-inst-tab';
    });
    btn.classList.add('on-' + inst);

    var il     = el('mb-il');
    var labels = { stock:'STOCK EQ', fut:'FUTURES', option:'OPTIONS' };
    var cls    = { stock:'mb-il-stock', fut:'mb-il-fut', option:'mb-il-option' };
    if (il) { il.textContent = labels[inst]; il.className = 'mb-inst-label ' + cls[inst]; }

    // Re-detect last date for new instrument, reload symbols, auto-scan
    fetch(MB_LASTDATE + '?instrument=' + mbInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) el('mb-date').value = res.last_date;
        mbLoadSymbols(function () { mbScan(); });
    })
    .catch(function () {
        mbLoadSymbols(function () { mbScan(); });
    });
}

// ── Date helpers — same pattern as pivot ──────────────────────

function mbGetDate() { return el('mb-date').value; }

function mbShiftDate(d) {
    var picker = el('mb-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > MB_TODAY) return;
    picker.value = s;
    mbScan();
}

function mbGoToday() {
    el('mb-date').value = MB_TODAY;
    mbScan();
}

function mbUpdateDateBadge(isToday) {
    el('mb-date-badge').innerHTML = isToday
        ? '<span class="mb-live-badge">● Live</span>'
        : '<span class="mb-hist-badge">📅 Historical</span>';
}

// ── Symbols — single select like pivot ───────────────────────

function mbLoadSymbols(callback) {
    var key = mbInst;
    if (mbSymCache[key] && mbSymCache[key].length) {
        mbRebuildSym(mbSymCache[key]);
        if (callback) callback();
        return;
    }

    fetch(MB_SYM + '?instrument=' + mbInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.no_config) {
            mbShowWarn(res.message || '');
            mbRebuildSym([]);
        } else {
            mbHideWarn();
            mbSymCache[key] = res.symbols || [];
            mbRebuildSym(mbSymCache[key]);
        }
        if (callback) callback();
    })
    .catch(function () {
        if (callback) callback();
    });
}

function mbRebuildSym(syms) {
    var sel  = el('mb-sym');
    var prev = sel.value;
    var opts = '<option value="ALL">— All Symbols —</option>';
    syms.forEach(function (s) {
        opts += '<option value="' + s + '"' + (s === prev ? ' selected' : '') + '>' + s + '</option>';
    });
    sel.innerHTML = opts;
    if (prev && prev !== 'ALL') {
        sel.value = prev;
        if (sel.value !== prev) sel.value = 'ALL';
    }
}

// ── Scan ──────────────────────────────────────────────────────

function mbScan() {
    var date      = mbGetDate();
    var threshold = el('mb-threshold').value || '1.0';
    var showNT    = el('mb-show-nt').checked ? '1' : '0';
    var sym       = el('mb-sym').value;

    if (!date) return;

    mbHideWarn();
    mbResetStats();

    html('mb-tbody', '<tr><td colspan="9"><div class="mb-spinner-row">'
        + '<div class="mb-spinner"></div>'
        + 'Scanning ' + threshold + '% breakout for ' + date + '…'
        + '</div></td></tr>');
    txt('mb-subtitle', date + ' · Scanning…');

    var params = new URLSearchParams({
        instrument    : mbInst,
        date          : date,
        threshold     : threshold,
        show_no_trade : showNT,
    });
    if (sym && sym !== 'ALL') {
        params.append('symbols[]', sym);
    }

    fetch(MB_SCAN + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (typeof res.is_today !== 'undefined') {
            mbUpdateDateBadge(res.is_today);
        }

        // Rebuild symbol list if server sends available_symbols
        if (res.available_symbols && res.available_symbols.length) {
            mbSymCache[mbInst] = res.available_symbols;
            mbRebuildSym(res.available_symbols);
            if (sym && sym !== 'ALL') el('mb-sym').value = sym;
        }

        if (res.no_config) {
            mbShowWarn(res.message);
            mbEmptyTable('No active config.');
            return;
        }

        if (!res.success || !res.data || !res.data.length) {
            mbEmptyTable(res.message || 'No signals found for this date.');
            mbUpdateStats({ total_records:0, buy_ce_count:0, buy_pe_count:0, no_trade_count:0 });
            txt('mb-subtitle', date + ' · No signals found');
            return;
        }

        mbUpdateStats(res);
        mbRenderTable(res.data);

        el('mb-info').innerHTML =
            '<span style="color:#047857;">CE: ' + res.buy_ce_count + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:#b91c1c;">PE: ' + res.buy_pe_count + '</span>'
            + ' &nbsp;·&nbsp; ±' + res.threshold + '%'
            + ' · ' + res.instrument;
        txt('mb-subtitle', date + ' · ' + res.message);
        txt('mb-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) {
        mbEmptyTable('⚠ ' + err.message);
    });
}

// ── Renderer ──────────────────────────────────────────────────

function mbRenderTable(data) {
    if (!data || !data.length) { mbEmptyTable('No data.'); return; }

    var h   = '';
    var num = 1;

    data.forEach(function (r, i) {
        var isNT  = r.signal === 'NO_TRADE';
        var isCE  = r.signal === 'BUY_CE';
        var rowCls= isNT ? 'tr-nt' : isCE ? 'tr-ce' : 'tr-pe';
        var zebra = i % 2 === 0 ? 'tr-even' : 'tr-odd';

        var sigHtml = isNT
            ? '<span class="sig-nt">— No Trade —</span>'
            : isCE
                ? '<span class="sig-ce">▲ BUY CE</span>'
                : '<span class="sig-pe">▼ BUY PE</span>';

        var pctHtml = r.change_pct != null
            ? '<span class="' + (r.change_pct > 0 ? 'pct-up' : r.change_pct < 0 ? 'pct-down' : 'pct-neu') + '">'
                + (r.change_pct > 0 ? '+' : '') + f(r.change_pct) + '%</span>'
            : '<span class="pct-neu">—</span>';

        var hlHtml = (r.day_high && r.day_low)
            ? '<span class="c-dh">₹' + f(r.day_high) + '</span>'
                + ' <span style="color:#ccc;">/</span> '
                + '<span class="c-dl">₹' + f(r.day_low) + '</span>'
            : '—';

        h += '<tr class="' + rowCls + ' ' + zebra + '">'
            + '<td class="c-num">'  + (isNT ? '' : num++) + '</td>'
            + '<td class="c-date">' + r.date + '</td>'
            + '<td class="c-sym">'  + esc(r.symbol)
                + (r.expiry ? '<small>' + r.expiry + '</small>' : '') + '</td>'
            + '<td class="c-open">' + (r.day_open ? '₹' + f(r.day_open) : '—') + '</td>'
            + '<td class="sep-signal">' + sigHtml + '</td>'
            + '<td class="c-time">' + (r.signal_time  || '—') + '</td>'
            + '<td class="c-px">'   + (r.signal_price ? '₹' + f(r.signal_price) : '—') + '</td>'
            + '<td>' + pctHtml + '</td>'
            + '<td>' + hlHtml  + '</td>'
            + '</tr>';
    });

    html('mb-tbody', h || mbEmptyHtml('No results.'));
}

// ── Stats ─────────────────────────────────────────────────────

function mbUpdateStats(res) {
    txt('st-total', res.total_records  || '0');
    txt('st-ce',   res.buy_ce_count   || '0');
    txt('st-pe',   res.buy_pe_count   || '0');
    txt('st-nt',   res.no_trade_count || '0');
}

function mbResetStats() {
    ['st-total','st-ce','st-pe','st-nt'].forEach(function (id) { txt(id, '—'); });
}

// ── Helpers ───────────────────────────────────────────────────

function mbShowWarn(msg) {
    el('mb-warn').classList.add('show');
    txt('mb-warn-msg', msg || '');
}
function mbHideWarn() { el('mb-warn').classList.remove('show'); }

function mbEmptyTable(msg) { html('mb-tbody', mbEmptyHtml(msg)); }

function mbEmptyHtml(msg) {
    return '<tr><td colspan="9"><div class="mb-empty">'
        + '<i class="las la-chart-bar"></i>'
        + '<p>' + (msg || 'No data found.') + '</p>'
        + '</div></td></tr>';
}

function mbReset() {
    fetch(MB_LASTDATE + '?instrument=' + mbInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        el('mb-date').value      = res.last_date || MB_TODAY;
        el('mb-threshold').value = '1.0';
        el('mb-show-nt').checked = false;
        el('mb-sym').value       = 'ALL';
        mbHideWarn();
        mbScan();
    })
    .catch(function () {
        el('mb-date').value      = MB_TODAY;
        el('mb-threshold').value = '1.0';
        el('mb-show-nt').checked = false;
        el('mb-sym').value       = 'ALL';
        mbHideWarn();
        mbScan();
    });
}

function f(v)   { return parseFloat(v || 0).toFixed(2); }
function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush