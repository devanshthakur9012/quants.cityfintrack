{{-- FILE: resources/views/themes/{active_theme}/user/quantedge-smc/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* ── BASE ── */
.smc-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.smc-wrap * { box-sizing:border-box; }
.smc-wrap h1,.smc-wrap h2,.smc-wrap h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes smcFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.smc-anim { animation:smcFadeUp .5s ease both; }
@keyframes smcSpin { to{ transform:rotate(360deg); } }

/* ── HERO ── */
.smc-hero {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:32px 48px; display:flex; align-items:center;
    justify-content:space-between; gap:24px;
}
.smc-hero-left h1 {
    font-size:clamp(24px,3.5vw,40px); font-weight:700;
    color:#1a1a2e; margin:0 0 8px; line-height:1.1;
}
.smc-hero-left h1 span { color:#7DFF00; }
.smc-hero-left p { font-size:13px; color:#666; margin:0; line-height:1.7; max-width:580px; }
.smc-hero-pills { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
.smc-pill {
    display:inline-block; padding:3px 10px; border-radius:4px;
    font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700;
}
.smc-pill-buy  { background:rgba(4,120,87,.1);  color:#047857; border:1px solid rgba(4,120,87,.3); }
.smc-pill-sell { background:rgba(185,28,28,.08); color:#b91c1c; border:1px solid rgba(185,28,28,.25); }
.smc-pill-tf   { background:rgba(245,166,35,.12);color:#c97f00; border:1px solid rgba(245,166,35,.3); }
.smc-hero-icon {
    width:80px; height:80px; border-radius:16px;
    background:linear-gradient(135deg,#0f1b2d,#1a3050);
    display:flex; align-items:center; justify-content:center;
    font-size:36px; color:#7DFF00; flex-shrink:0;
}
@media(max-width:768px){
    .smc-hero { flex-direction:column; padding:24px 16px; text-align:center; }
    .smc-hero-pills { justify-content:center; }
    .smc-hero-icon  { display:none; }
}

/* ── FILTER BAR ── */
.smc-filter-bar {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:0 48px; position:sticky; top:0; z-index:200;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.smc-filter-inner {
    display:flex; align-items:center; gap:12px;
    padding:13px 0; flex-wrap:wrap;
}
.smc-filter-label {
    font-size:10.5px; color:#999; font-weight:700;
    text-transform:uppercase; letter-spacing:.07em;
}
.smc-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

/* Date controls */
.smc-date-wrap { display:flex; align-items:center; gap:4px; }
.smc-date-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
    color:#333; outline:none; cursor:pointer;
}
.smc-date-input:focus { border-color:#7DFF00; }
.smc-date-nav {
    width:28px; height:32px; border:1.5px solid #e5e9f2; border-radius:6px;
    background:#fff; color:#888; cursor:pointer; font-weight:700; font-size:14px;
    display:flex; align-items:center; justify-content:center; transition:.2s;
}
.smc-date-nav:hover { border-color:#7DFF00; color:#7DFF00; }
.smc-today-btn { width:auto; padding:0 10px; font-size:10px; font-family:'Exo 2',sans-serif; font-weight:700; letter-spacing:.07em; }

/* Date badge */
.smc-live-badge  { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.smc-hist-badge  { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.smc-range-badge { background:#ede9fe; color:#6d28d9; border:1px solid #ddd6fe; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }

/* Symbol select */
.smc-sym-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 30px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance:none; cursor:pointer; outline:none; min-width:150px;
}
.smc-sym-select:focus { border-color:#7DFF00; }

/* Filter signal pills */
.smc-fp-wrap { display:flex; gap:4px; flex-wrap:wrap; }
.smc-fp {
    padding:5px 12px; border-radius:20px; font-family:'Exo 2',sans-serif;
    font-size:10px; font-weight:700; cursor:pointer; transition:.15s;
    border:1.5px solid #e5e9f2; background:#fff; color:#888;
}
.smc-fp:hover     { border-color:#7DFF00; color:#c97f00; }
.smc-fp.fp-all    { border-color:#1a56db; background:rgba(26,86,219,.07); color:#1a56db; }
.smc-fp.fp-buy    { border-color:#059669; background:rgba(5,150,105,.08); color:#047857; }
.smc-fp.fp-sell   { border-color:#dc2626; background:rgba(220,38,38,.08); color:#b91c1c; }
.smc-fp.fp-pb     { border-color:#d97706; background:rgba(217,119,6,.08); color:#b45309; }
.smc-fp.fp-nt     { border-color:#ccc;    background:#f7f8fc; color:#aab; }

/* Buttons */
.smc-load-btn {
    background:#7DFF00; color:#000; border:none; border-radius:8px;
    padding:8px 20px; font-family:'Rajdhani',sans-serif; font-size:13px;
    font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s;
}
.smc-load-btn:hover { background:#d4890e; }
.smc-reset-btn {
    background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px;
    padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer;
    font-family:'Exo 2',sans-serif; transition:.2s;
}
.smc-reset-btn:hover { border-color:#7DFF00; color:#c97f00; }

.smc-ml-auto { margin-left:auto; display:flex; align-items:center; gap:10px; }
.smc-upd { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }

@media(max-width:768px){
    .smc-filter-bar   { padding:0 16px; }
    .smc-filter-inner { gap:8px; }
    .smc-ml-auto      { margin-left:0; width:100%; }
}

/* ── CONTENT ── */
.smc-content { padding:28px 48px 64px; }
@media(max-width:768px){ .smc-content { padding:16px 12px 48px; } }

/* Config warning */
.smc-warn {
    background:#fff3e0; border:1px solid #ffcc80; border-radius:10px;
    padding:16px 20px; margin-bottom:20px;
    display:none; align-items:center; gap:14px;
    font-size:13px; color:#e65100;
}
.smc-warn.show { display:flex; }
.smc-warn i { font-size:20px; flex-shrink:0; }

/* Stats row */
.smc-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.smc-stat {
    background:#fff; border:1px solid #e8e8e8; border-radius:12px;
    padding:14px 18px; min-width:110px; flex:1;
}
.smc-stat small { display:block; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#aab; margin-bottom:4px; }
.smc-stat strong { display:block; font-family:'JetBrains Mono',monospace; font-size:1.3rem; font-weight:700; }
.ss-tot  { border-left:3px solid #1a56db; }
.ss-buy  { border-left:3px solid #059669; }
.ss-sell { border-left:3px solid #dc2626; }
.ss-pb   { border-left:3px solid #d97706; }
.ss-nt   { border-left:3px solid #ccc; }

/* Table card */
.smc-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; overflow:hidden; }
.smc-card-header {
    padding:14px 20px; border-bottom:1px solid #f0f0f0;
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:8px; background:#fafafa;
}
.smc-card-title {
    font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700;
    color:#1a1a2e; display:flex; align-items:center; gap:8px;
}
.smc-card-sub { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }

.smc-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

/* ── TABLE ── */
.smc-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:900px; }

.smc-table thead tr.th-group th {
    padding:9px 10px 5px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800;
    letter-spacing:.1em; text-transform:uppercase;
    background:#f7f8fc; border-bottom:none; white-space:nowrap;
}
.smc-table thead tr.th-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700;
    letter-spacing:.03em; text-transform:uppercase;
    background:#f4f6fb; color:#aab;
    border-bottom:2px solid #e8e8e8; white-space:nowrap;
}

/* Group header colours */
.g-info   { color:#888 !important; }
.g-signal { color:#047857 !important; }
.g-smc    { color:#6d28d9 !important; }
.g-ema    { color:#1a56db !important; }

/* Column separators */
.sep-signal { border-left:2px solid rgba(5,150,105,.2) !important; }
.sep-smc    { border-left:2px solid rgba(124,58,237,.15) !important; }
.sep-ema    { border-left:2px solid rgba(26,86,219,.15) !important; }

/* Body cells */
.smc-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid #f5f5f5; vertical-align:middle;
    white-space:nowrap; color:#555;
}
.smc-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }
.tr-buy  { background:rgba(5,150,105,.03)  !important; }
.tr-sell { background:rgba(220,38,38,.03)  !important; }
.tr-pb   { background:rgba(217,119,6,.02)  !important; }

.c-num  { font-size:9px; color:#ccc; }
.c-sym  { display:inline-block; padding:2px 8px; border-radius:5px; font-size:10px; font-weight:700; background:rgba(5,150,105,.08); color:#047857; border:1px solid rgba(5,150,105,.2); }
.c-date { display:inline-block; padding:2px 7px; border-radius:4px; font-size:10px; font-weight:700; background:#ede9fe; color:#6d28d9; border:1px solid #ddd6fe; }

/* Signal badges */
.sig { display:inline-block; border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; letter-spacing:.04em; }
.sig-buy   { background:rgba(5,150,105,.15);  color:#047857; border:1px solid rgba(5,150,105,.35); }
.sig-sell  { background:rgba(220,38,38,.12);  color:#b91c1c; border:1px solid rgba(220,38,38,.3);  }
.sig-buyp  { background:rgba(217,119,6,.12);  color:#b45309; border:1px solid rgba(217,119,6,.3);  }
.sig-sellp { background:rgba(234,88,12,.12);  color:#c2410c; border:1px solid rgba(234,88,12,.3);  }
.sig-nt    { background:#f7f8fc; color:#ccc;  border:1px solid #e8e8e8; font-size:9px; }

/* Trend badges */
.trend-up   { display:inline-block; background:rgba(5,150,105,.1);  color:#047857; border:1px solid rgba(5,150,105,.25); border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.trend-dn   { display:inline-block; background:rgba(220,38,38,.1);  color:#b91c1c; border:1px solid rgba(220,38,38,.25); border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.trend-side { display:inline-block; background:#f4f6fb; color:#aab;  border:1px solid #e8e8e8; border-radius:5px; padding:2px 8px; font-size:10px; }

/* Bool pills */
.b-yes { display:inline-block; background:rgba(5,150,105,.1);  color:#047857; border:1px solid rgba(5,150,105,.25); border-radius:4px; padding:1px 7px; font-size:9px; font-weight:800; }
.b-no  { display:inline-block; background:#f7f8fc; color:#ccc; border:1px solid #e8e8e8; border-radius:4px; padding:1px 7px; font-size:9px; }
.b-wrn { display:inline-block; background:rgba(217,119,6,.1);  color:#b45309; border:1px solid rgba(217,119,6,.25); border-radius:4px; padding:1px 7px; font-size:9px; font-weight:800; }

/* EMA vs close */
.ema-abv { color:#047857; font-size:10px; font-weight:800; }
.ema-blw { color:#b91c1c; font-size:10px; font-weight:800; }

/* Loading / empty */
.smc-loading {
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; padding:64px 20px;
}
.smc-spinner {
    width:36px; height:36px; border:3px solid #f0f0f0;
    border-top:3px solid #7DFF00; border-radius:50%;
    animation:smcSpin 1s linear infinite;
}
.smc-loading-text { color:#aab; margin-top:12px; font-size:13px; font-family:'Exo 2',sans-serif; }
.smc-empty { text-align:center; padding:60px 20px; color:#ccc; font-family:'Exo 2',sans-serif; font-size:13px; }
.smc-empty i { font-size:2.5rem; display:block; margin-bottom:12px; }
</style>

<div class="smc-wrap">

{{-- ══ HERO ══ --}}
<div class="smc-hero smc-anim">
    <div class="smc-hero-left">
        <h1>QuantEdge — <span>Smart Money</span> Analysis</h1>
        <p>
            SMC signals on 15min candles — Liquidity sweeps, Fair Value Gaps,
            Order Blocks and EMA-20 confluence for high-probability trade setups.
        </p>
        <div class="smc-hero-pills">
            <span class="smc-pill smc-pill-buy">BUY: Sweep Low + Bullish FVG + Volume + Above EMA-20</span>
            <span class="smc-pill smc-pill-sell">SELL: Sweep High + Bearish FVG + Volume + Below EMA-20</span>
            <span class="smc-pill smc-pill-tf">15 Min · 60-candle rolling window</span>
        </div>
    </div>
    <div class="smc-hero-icon">
        <i class="las la-chart-area"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="smc-filter-bar">
    <div class="smc-filter-inner">

        {{-- Date FROM --}}
        <span class="smc-filter-label">From</span>
        <div class="smc-date-wrap">
            <button class="smc-date-nav" onclick="smcShiftDate('from',-1)">‹</button>
            <input type="date" id="smc-from" class="smc-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="smcSyncDates()">
            <button class="smc-date-nav" onclick="smcShiftDate('from',1)">›</button>
        </div>

        <span class="smc-filter-label">To</span>
        <div class="smc-date-wrap">
            <button class="smc-date-nav" onclick="smcShiftDate('to',-1)">‹</button>
            <input type="date" id="smc-to" class="smc-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="smcSyncDates()">
            <button class="smc-date-nav" onclick="smcShiftDate('to',1)">›</button>
            <button class="smc-date-nav smc-today-btn" onclick="smcGoToday()">TODAY</button>
            <span id="smc-date-badge"></span>
        </div>

        <div class="smc-sep"></div>

        {{-- Symbol --}}
        <span class="smc-filter-label">Symbol</span>
        <select id="smc-sym" class="smc-sym-select">
            <option value="ALL">— All Symbols —</option>
        </select>

        <button class="smc-load-btn" onclick="smcLoad()">
            <i class="las la-sync-alt"></i> Load
        </button>
        <button class="smc-reset-btn" onclick="smcGoToday()">Today</button>

        <div class="smc-sep"></div>

        {{-- Signal filter pills --}}
        <span class="smc-filter-label">Filter</span>
        <div class="smc-fp-wrap" id="smc-fp-wrap">
            <div class="smc-fp fp-all"  data-sig="ALL"           onclick="smcFilter('ALL',this)">All</div>
            <div class="smc-fp"         data-sig="BUY"           onclick="smcFilter('BUY',this)">↑ Buy</div>
            <div class="smc-fp"         data-sig="SELL"          onclick="smcFilter('SELL',this)">↓ Sell</div>
            <div class="smc-fp"         data-sig="BUY_PULLBACK"  onclick="smcFilter('BUY_PULLBACK',this)">Buy PB</div>
            <div class="smc-fp"         data-sig="SELL_PULLBACK" onclick="smcFilter('SELL_PULLBACK',this)">Sell PB</div>
            <div class="smc-fp"         data-sig="NO_TRADE"      onclick="smcFilter('NO_TRADE',this)">No Trade</div>
        </div>

        <div class="smc-ml-auto">
            <span class="smc-upd" id="smc-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="smc-content">

    {{-- Config warning --}}
    <div class="smc-warn" id="smc-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="smc-warn-msg">
                Go to Admin → Analysis Config and create a 15min config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="smc-stats" id="smc-stats" style="display:none;">
        <div class="smc-stat ss-tot"><small>Total</small><strong id="st-total" style="color:#1a56db;">0</strong></div>
        <div class="smc-stat ss-buy"><small>↑ Buy</small><strong id="st-buy" style="color:#047857;">0</strong></div>
        <div class="smc-stat ss-sell"><small>↓ Sell</small><strong id="st-sell" style="color:#b91c1c;">0</strong></div>
        <div class="smc-stat ss-pb"><small>Pullbacks</small><strong id="st-pb" style="color:#b45309;">0</strong></div>
        <div class="smc-stat ss-nt"><small>No Trade</small><strong id="st-nt" style="color:#aab;">0</strong></div>
    </div>

    {{-- Table card --}}
    <div class="smc-card">
        <div class="smc-card-header">
            <div class="smc-card-title">
                <i class="las la-chart-area" style="color:#7DFF00;"></i>
                QuantEdge Smart Money &nbsp;·&nbsp; 15 Min
            </div>
            <span class="smc-card-sub" id="smc-card-info"></span>
        </div>
        <div class="smc-tscroll">
            <table class="smc-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="4" class="g-info">Info</th>
                        <th colspan="2" class="g-signal sep-signal">⚡ Signal</th>
                        <th colspan="3" class="g-smc sep-smc">▲ SMC Conditions</th>
                        <th colspan="2" class="g-ema sep-ema">📈 EMA-20</th>
                    </tr>
                    <tr class="th-cols">
                        <th class="g-info">#</th>
                        <th class="g-info" style="text-align:left;padding-left:14px;">Symbol</th>
                        <th class="g-info">Date</th>
                        <th class="g-info">Close</th>

                        <th class="g-signal sep-signal">Signal</th>
                        <th class="g-signal">Trend</th>

                        <th class="g-smc sep-smc">Vol Spike<br><span style="font-size:7px;opacity:.5;font-weight:400;">&gt;1.2× avg</span></th>
                        <th class="g-smc">Sweep<br><span style="font-size:7px;opacity:.5;font-weight:400;">Low / High</span></th>
                        <th class="g-smc">FVG<br><span style="font-size:7px;opacity:.5;font-weight:400;">Bull / Bear</span></th>

                        <th class="g-ema sep-ema">EMA-20</th>
                        <th class="g-ema">vs Close</th>
                    </tr>
                </thead>
                <tbody id="smc-tbody">
                    <tr><td colspan="11">
                        <div class="smc-empty">
                            <i class="las la-chart-area"></i>
                            Select date range and click Load
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.smc-content --}}
</div>{{-- /.smc-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  QuantEdge SMC — Vanilla JS (no jQuery)
// ═══════════════════════════════════════════════════════════════

var SMC_TODAY      = '{{ now()->toDateString() }}';
var SMC_SIGNALS    = '{{ route("quantedge-smc.signals") }}';
var smcCurFilter   = 'ALL';
var smcAllResults  = [];

// ── DOM helpers ───────────────────────────────────────────────

function smcHtml(id, html) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = html;
}
function smcText(id, txt) {
    var el = document.getElementById(id);
    if (el) el.textContent = txt;
}
function smcShow(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = '';
}
function smcHide(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    smcUpdateDateBadge();
    smcLoad();
});

// ── Date helpers ──────────────────────────────────────────────

function smcShiftDate(which, d) {
    var id = which === 'from' ? 'smc-from' : 'smc-to';
    var el = document.getElementById(id);
    var dt = new Date(el.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > SMC_TODAY) return;
    el.value = s;
    smcSyncDates();
}

function smcSyncDates() {
    var fp = document.getElementById('smc-from');
    var tp = document.getElementById('smc-to');
    if (fp.value > SMC_TODAY) fp.value = SMC_TODAY;
    if (tp.value > SMC_TODAY) tp.value = SMC_TODAY;
    if (fp.value > tp.value)  tp.value = fp.value;
    tp.min = fp.value;
    smcUpdateDateBadge();
}

function smcGoToday() {
    document.getElementById('smc-from').value = SMC_TODAY;
    document.getElementById('smc-to').value   = SMC_TODAY;
    smcSyncDates();
    smcLoad();
}

function smcUpdateDateBadge() {
    var from = document.getElementById('smc-from').value;
    var to   = document.getElementById('smc-to').value;
    var el   = document.getElementById('smc-date-badge');
    if (!el) return;
    if (from === SMC_TODAY && to === SMC_TODAY) {
        el.innerHTML = '<span class="smc-live-badge">● Live</span>';
    } else if (from !== to) {
        el.innerHTML = '<span class="smc-range-badge">📅 Range</span>';
    } else {
        el.innerHTML = '<span class="smc-hist-badge">📅 Historical</span>';
    }
}

// ── Symbol dropdown ───────────────────────────────────────────

function smcRebuildSym(syms) {
    var sel  = document.getElementById('smc-sym');
    var prev = sel.value;
    sel.innerHTML = '<option value="ALL">— All Symbols —</option>';
    syms.forEach(function (s) {
        var opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

// ── Main loader ───────────────────────────────────────────────

function smcLoad() {
    var from = document.getElementById('smc-from').value;
    var to   = document.getElementById('smc-to').value;
    var sym  = document.getElementById('smc-sym').value || 'ALL';
    smcUpdateDateBadge();
    smcShowLoading();

    document.getElementById('smc-warn').classList.remove('show');

    var params = new URLSearchParams({ from_date: from, to_date: to, symbol: sym });

    fetch(SMC_SIGNALS + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (res.no_config) {
            document.getElementById('smc-warn').classList.add('show');
            smcText('smc-warn-msg', res.message || '');
            smcEmptyTable();
            return;
        }
        if (!res.success) {
            smcEmptyTable(res.message);
            return;
        }

        if (res.symbols && res.symbols.length) smcRebuildSym(res.symbols);

        smcAllResults = res.results || [];
        smcRenderStats(res.summary);
        smcRenderTable(smcAllResults);
        smcApplyFilter(smcCurFilter);

        smcText('smc-card-info', smcAllResults.length + ' row(s)' + (res.is_range ? ' · range' : ''));
        smcText('smc-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) {
        smcEmptyTable('⚠ ' + err.message);
    });
}

// ── Render table ──────────────────────────────────────────────

function smcRenderTable(rows) {
    if (!rows || !rows.length) { smcEmptyTable('No signals found.'); return; }

    var html = '';
    rows.forEach(function (r, i) {
        var sig    = r.signal || 'NO_TRADE';
        var zebra  = i % 2 === 0 ? 'tr-even' : 'tr-odd';
        var rowCls = zebra
            + (sig === 'BUY'                                       ? ' tr-buy'  : '')
            + (sig === 'SELL'                                      ? ' tr-sell' : '')
            + (sig === 'BUY_PULLBACK' || sig === 'SELL_PULLBACK'   ? ' tr-pb'   : '');

        var sigBadgeMap = {
            BUY:           '<span class="sig sig-buy">↑ BUY</span>',
            SELL:          '<span class="sig sig-sell">↓ SELL</span>',
            BUY_PULLBACK:  '<span class="sig sig-buyp">↩ BUY PB</span>',
            SELL_PULLBACK: '<span class="sig sig-sellp">↩ SELL PB</span>',
            NO_TRADE:      '<span class="sig sig-nt">— NO TRADE</span>',
        };
        var sigBadge = sigBadgeMap[sig] || '<span class="sig sig-nt">—</span>';

        var trendBadgeMap = {
            UPTREND:   '<span class="trend-up">↑ UP</span>',
            DOWNTREND: '<span class="trend-dn">↓ DOWN</span>',
            SIDEWAYS:  '<span class="trend-side">→ SIDE</span>',
        };
        var trendBadge = trendBadgeMap[r.trend] || smcDash();

        var sweepCell = (r.liquidity_sweep_low  ? '<span class="b-yes">L</span> ' : '<span class="b-no">L</span> ')
                      + (r.liquidity_sweep_high ? '<span class="b-wrn">H</span>'  : '<span class="b-no">H</span>');

        var fvgCell = (r.fvg_bullish ? '<span class="b-yes">↑</span> ' : '<span class="b-no">↑</span> ')
                    + (r.fvg_bearish ? '<span class="b-wrn">↓</span>'   : '<span class="b-no">↓</span>');

        var emaVs = smcDash();
        if (r.last_close && r.ema20) {
            emaVs = r.last_close > r.ema20
                ? '<span class="ema-abv">▲ ABV</span>'
                : '<span class="ema-blw">▼ BLW</span>';
        }

        html += '<tr class="' + rowCls + '" data-sig="' + sig + '">'
            + '<td class="c-num">' + (i + 1) + '</td>'
            + '<td style="text-align:left;padding-left:14px;"><span class="c-sym">' + smcEsc(r.symbol) + '</span></td>'
            + '<td><span class="c-date">' + smcEsc(r.analysis_date || '') + '</span></td>'
            + '<td style="font-family:\'JetBrains Mono\',monospace;font-weight:700;color:#1a1a2e;">'
            + (r.last_close ? '₹' + smcFmt(r.last_close) : smcDash()) + '</td>'
            + '<td class="sep-signal">' + sigBadge + '</td>'
            + '<td>' + trendBadge + '</td>'
            + '<td class="sep-smc">' + (r.volume_spike ? '<span class="b-yes">✓ YES</span>' : '<span class="b-no">✗</span>') + '</td>'
            + '<td>' + sweepCell + '</td>'
            + '<td>' + fvgCell + '</td>'
            + '<td class="sep-ema" style="color:#1a56db;font-weight:700;">'
            + (r.ema20 ? '₹' + smcFmt(r.ema20) : smcDash()) + '</td>'
            + '<td>' + emaVs + '</td>'
            + '</tr>';
    });

    smcHtml('smc-tbody', html);
}

// ── Signal filter ─────────────────────────────────────────────

function smcFilter(sig, btn) {
    smcCurFilter = sig;
    document.querySelectorAll('#smc-fp-wrap .smc-fp').forEach(function (b) {
        b.classList.remove('fp-all','fp-buy','fp-sell','fp-pb','fp-nt');
    });
    var cls = sig === 'ALL'           ? 'fp-all'
            : sig === 'BUY'           ? 'fp-buy'
            : sig === 'SELL'          ? 'fp-sell'
            : sig === 'NO_TRADE'      ? 'fp-nt'
            : 'fp-pb';
    btn.classList.add(cls);
    smcApplyFilter(sig);
}

function smcApplyFilter(sig) {
    document.querySelectorAll('#smc-tbody tr[data-sig]').forEach(function (row) {
        row.style.display = (sig === 'ALL' || row.dataset.sig === sig) ? '' : 'none';
    });
}

// ── Stats ─────────────────────────────────────────────────────

function smcRenderStats(s) {
    smcText('st-total', s.total);
    smcText('st-buy',   s.buy);
    smcText('st-sell',  s.sell);
    smcText('st-pb',    (s.buy_pullback || 0) + (s.sell_pullback || 0));
    smcText('st-nt',    s.no_trade);
    smcShow('smc-stats');
}

// ── Utilities ─────────────────────────────────────────────────

function smcFmt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 });
}
function smcDash() {
    return '<span style="color:#e0e0e0;font-size:9px;">—</span>';
}
function smcEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function smcShowLoading() {
    smcHtml('smc-tbody',
        '<tr><td colspan="11">'
        + '<div class="smc-loading"><div class="smc-spinner"></div>'
        + '<div class="smc-loading-text">Running SMC analysis…</div></div>'
        + '</td></tr>'
    );
    smcHide('smc-stats');
}

function smcEmptyTable(msg) {
    smcHtml('smc-tbody',
        '<tr><td colspan="11">'
        + '<div class="smc-empty"><i class="las la-chart-area"></i>'
        + smcEsc(msg || 'Select date range and click Load')
        + '</div></td></tr>'
    );
    smcHide('smc-stats');
}
</script>
@endpush