{{-- FILE: resources/views/themes/{active_theme}/user/nifty-breakout-analyzer/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
.nb-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.nb-wrap * { box-sizing:border-box; }
.nb-wrap h1,.nb-wrap h2,.nb-wrap h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes nbUp   { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.nb-anim { animation:nbUp .5s ease both; }
@keyframes nbSpin { to{transform:rotate(360deg);} }

/* ── HERO ── */
.nb-hero { background:#fff; border-bottom:1px solid #e8e8e8; padding:32px 48px; display:flex; align-items:center; justify-content:space-between; gap:24px; }
.nb-hero-left h1 { font-size:clamp(24px,3.5vw,40px); font-weight:700; color:#1a1a2e; margin:0 0 8px; line-height:1.1; }
.nb-hero-left h1 span { color:#7DFF00; }
.nb-hero-left p { font-size:13px; color:#666; margin:0; line-height:1.7; max-width:640px; }
.nb-hero-icon { width:76px; height:76px; border-radius:16px; background:linear-gradient(135deg,#0f1b2d,#1a3050); display:flex; align-items:center; justify-content:center; font-size:32px; color:#7DFF00; flex-shrink:0; }
@media(max-width:768px){ .nb-hero{ flex-direction:column; padding:24px 16px; text-align:center; } .nb-hero-icon{ display:none; } }

/* ── FILTER BAR ── */
.nb-filter-bar { background:#fff; border-bottom:1px solid #e8e8e8; padding:0 48px; position:sticky; top:0; z-index:200; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.nb-filter-inner { display:flex; align-items:center; gap:12px; padding:12px 0; flex-wrap:wrap; }
.nb-filter-label { font-size:10.5px; color:#999; font-weight:700; text-transform:uppercase; letter-spacing:.07em; flex-shrink:0; }
.nb-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

/* Symbol select — single like pivot */
.nb-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 28px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 9px center;
    appearance:none; cursor:pointer; outline:none; min-width:120px;
}
.nb-select:focus { border-color:#7DFF00; }

/* Date controls — same as pivot */
.nb-date-wrap { display:flex; align-items:center; gap:4px; }
.nb-date-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
    color:#333; outline:none; cursor:pointer;
}
.nb-date-input:focus { border-color:#7DFF00; }
.nb-date-nav {
    width:28px; height:32px; border:1.5px solid #e5e9f2; border-radius:6px;
    background:#fff; color:#888; cursor:pointer; font-weight:700; font-size:14px;
    display:flex; align-items:center; justify-content:center; transition:.2s;
}
.nb-date-nav:hover { border-color:#7DFF00; color:#7DFF00; }
.nb-today-btn { width:auto; padding:0 10px; font-size:10px; font-family:'Exo 2',sans-serif; font-weight:700; letter-spacing:.07em; }

/* Status badge */
.nb-live-badge { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.nb-hist-badge { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }

/* Threshold slider */
.nb-thresh-wrap { display:flex; align-items:center; gap:8px; }
.nb-thresh-disp { font-family:'JetBrains Mono',monospace; font-size:14px; font-weight:700; color:#7DFF00; min-width:40px; text-align:center; background:rgba(245,166,35,.08); border:1px solid rgba(245,166,35,.3); border-radius:6px; padding:2px 7px; }
input[type=range].nb-range { accent-color:#7DFF00; width:120px; cursor:pointer; }

/* Buttons */
.nb-analyze-btn { background:#7DFF00; color:#000; border:none; border-radius:8px; padding:8px 22px; font-family:'Rajdhani',sans-serif; font-size:14px; font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s; white-space:nowrap; }
.nb-analyze-btn:hover { background:#d4890e; }
.nb-reset-btn { background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px; padding:7px 16px; font-size:12px; font-weight:700; cursor:pointer; transition:.2s; font-family:'Exo 2',sans-serif; }
.nb-reset-btn:hover { border-color:#7DFF00; color:#c97f00; }
.nb-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.nb-info-text { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.nb-upd-text  { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }
@media(max-width:768px){ .nb-filter-bar{ padding:0 12px; } .nb-filter-inner{ gap:8px; } .nb-filter-right{ margin-left:0;width:100%; } }

/* ── CONTENT ── */
.nb-content { padding:28px 48px 64px; }
@media(max-width:768px){ .nb-content{ padding:16px 12px 48px; } }
.nb-warn { background:#fff3e0; border:1px solid #ffcc80; border-radius:10px; padding:14px 20px; margin-bottom:20px; display:none; align-items:center; gap:12px; font-size:13px; color:#e65100; }
.nb-warn.show { display:flex; }
.nb-warn i { font-size:18px; flex-shrink:0; }

/* ── STATS ── */
.nb-stats { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:24px; }
@media(max-width:900px){ .nb-stats{ grid-template-columns:repeat(3,1fr); } }
@media(max-width:500px){ .nb-stats{ grid-template-columns:repeat(2,1fr); } }
.nb-stat-card { background:#fff; border-radius:12px; border:1px solid #e8e8e8; padding:14px 16px; border-left:3px solid #e8e8e8; }
.nb-stat-card.s-total { border-left-color:#c97f00; }
.nb-stat-card.s-ce    { border-left-color:#059669; }
.nb-stat-card.s-pe    { border-left-color:#dc2626; }
.nb-stat-card.s-syms  { border-left-color:#1a56db; }
.nb-stat-card.s-inv   { border-left-color:#7c3aed; }
.nb-stat-card.s-sig   { border-left-color:#c2410c; }
.nb-stat-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#aab; margin-bottom:6px; }
.nb-stat-val { font-family:'JetBrains Mono',monospace; font-size:22px; font-weight:700; color:#1a1a2e; }
.s-total .nb-stat-val { color:#c97f00; }
.s-ce    .nb-stat-val { color:#047857; }
.s-pe    .nb-stat-val { color:#b91c1c; }
.s-syms  .nb-stat-val { color:#1a56db; }
.s-inv   .nb-stat-val { color:#6d28d9; font-size:16px; }
.s-sig   .nb-stat-val { color:#c2410c; }

/* ── TABLE CARD ── */
.nb-card { background:#fff; border-radius:12px; border:1px solid #e8e8e8; overflow:hidden; }
.nb-card-header { padding:14px 20px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; background:#fafafa; }
.nb-card-title { font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:#1a1a2e; }
.nb-card-subtitle { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.nb-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

.nb-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:1100px; }
.nb-table thead tr.th-group th { padding:9px 10px 5px; text-align:center; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; background:#f7f8fc; border-bottom:none; white-space:nowrap; }
.nb-table thead tr.th-cols th  { padding:5px 10px 9px; text-align:center; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; background:#f4f6fb; color:#aab; border-bottom:2px solid #e8e8e8; white-space:nowrap; }
.g-info   { color:#555 !important; }
.g-nifty  { color:#c97f00 !important; }
.g-option { color:#1a56db !important; }
.g-trade  { color:#047857 !important; }
.sep-nifty  { border-left:2px solid rgba(245,166,35,.2)  !important; }
.sep-option { border-left:2px solid rgba(26,86,219,.2)   !important; }
.sep-trade  { border-left:2px solid rgba(5,150,105,.2)   !important; }

.nb-table tbody td { padding:8px 10px; text-align:center; font-size:11px; border-bottom:1px solid #f5f5f5; vertical-align:middle; white-space:nowrap; color:#555; }
.nb-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }
.tr-ce   { background:rgba(5,150,105,.03) !important; }
.tr-pe   { background:rgba(220,38,38,.03) !important; }

.nb-group-row td { background:linear-gradient(90deg,rgba(245,166,35,.08),rgba(245,166,35,.01)) !important; border-top:2px solid rgba(245,166,35,.15) !important; border-bottom:none !important; padding:10px 16px !important; text-align:left !important; font-family:'Exo 2',sans-serif; font-size:12px; font-weight:700; color:#c97f00 !important; }
.nb-group-row.gr-pe td { background:linear-gradient(90deg,rgba(220,38,38,.06),rgba(220,38,38,.01)) !important; border-top-color:rgba(220,38,38,.15) !important; color:#b91c1c !important; }

.c-num  { font-size:9px; color:#ccc; }
.c-date { font-size:11px; font-weight:700; color:#7DFF00; }
.c-sym  { font-size:12px; font-weight:800; color:#1a56db; }
.c-sym small { display:block; font-size:8px; color:#aab; font-weight:400; margin-top:1px; }
.c-val  { font-size:11px; font-weight:700; color:#1a1a2e; }
.c-sm   { font-size:10px; color:#aab; }
.up     { color:#059669; font-weight:700; }
.dn     { color:#dc2626; font-weight:700; }

.sig-ce { display:inline-block; background:rgba(5,150,105,.12); color:#047857; border:1px solid rgba(5,150,105,.35); border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-pe { display:inline-block; background:rgba(220,38,38,.1); color:#b91c1c; border:1px solid rgba(220,38,38,.35); border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.time-trig { display:inline-block; background:rgba(245,166,35,.1); border:1px solid rgba(245,166,35,.3); color:#c97f00; padding:2px 8px; border-radius:5px; font-size:10px; font-weight:700; }
.time-buy  { display:inline-block; background:rgba(26,86,219,.08); border:1px solid rgba(26,86,219,.25); color:#1d4ed8; padding:2px 8px; border-radius:5px; font-size:10px; font-weight:700; }
.time-trig.pe { background:rgba(220,38,38,.08); border-color:rgba(220,38,38,.25); color:#b91c1c; }
.c-invest { font-size:11px; font-weight:700; color:#6d28d9; }

.nb-empty { text-align:center; padding:56px 20px; color:#ccc; }
.nb-empty i { font-size:2.5rem; display:block; margin-bottom:12px; color:#e5e9f2; }
.nb-empty p { font-size:13px; }
.nb-spinner-row { display:flex; align-items:center; justify-content:center; gap:12px; padding:48px; color:#aab; font-size:13px; }
.nb-spinner { width:28px; height:28px; border:3px solid #f0f0f0; border-top:3px solid #7DFF00; border-radius:50%; animation:nbSpin 1s linear infinite; flex-shrink:0; }
</style>

<div class="nb-wrap">

{{-- ══ HERO ══ --}}
<div class="nb-hero nb-anim">
    <div class="nb-hero-left">
        <h1>NIFTY-Driven <span>Breakout</span> Analyzer</h1>
        <p>
            Monitors NIFTY FUT candles for threshold breaches from the day's open price,
            then maps ATM option entry trades across all configured symbols at the next
            candle's open using the highest-OI strike.
        </p>
    </div>
    <div class="nb-hero-icon"><i class="las la-chart-area"></i></div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="nb-filter-bar">
    <div class="nb-filter-inner">

        {{-- Symbol — single select like pivot --}}
        <span class="nb-filter-label">Symbol</span>
        <select id="nb-sym" class="nb-select" style="min-width:140px;" onchange="nbAnalyze()">
            <option value="ALL">— All Symbols —</option>
        </select>

        <div class="nb-sep"></div>

        {{-- Single date with nav buttons — same as pivot --}}
        <span class="nb-filter-label">Date</span>
        <div class="nb-date-wrap">
            <button class="nb-date-nav" onclick="nbShiftDate(-1)">‹</button>
            <input type="date" id="nb-date" class="nb-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="nbAnalyze()">
            <button class="nb-date-nav" onclick="nbShiftDate(1)">›</button>
            <button class="nb-date-nav nb-today-btn" onclick="nbGoToday()">TODAY</button>
            <span id="nb-date-badge"></span>
        </div>

        <div class="nb-sep"></div>

        {{-- Threshold --}}
        <span class="nb-filter-label">Threshold</span>
        <div class="nb-thresh-wrap">
            <span class="nb-thresh-disp" id="nb-thresh-disp">30</span>
            <span style="font-size:10px;color:#aab;">pts</span>
            <input type="range" id="nb-thresh" class="nb-range" min="5" max="300" step="5" value="30">
        </div>

        <div class="nb-sep"></div>

        {{-- Signal filter --}}
        <span class="nb-filter-label">Signal</span>
        <select id="nb-signal" class="nb-select" style="min-width:110px;" onchange="nbAnalyze()">
            <option value="BOTH">CE + PE</option>
            <option value="CE">CE Only</option>
            <option value="PE">PE Only</option>
        </select>

        <button class="nb-analyze-btn" onclick="nbAnalyze()">
            <i class="las la-search"></i> Analyze
        </button>
        <button class="nb-reset-btn" onclick="nbReset()">↺ Reset</button>

        <div class="nb-filter-right">
            <span class="nb-info-text" id="nb-info"></span>
            <span class="nb-upd-text"  id="nb-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="nb-content">

    <div class="nb-warn" id="nb-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="nb-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="nb-stats nb-anim">
        <div class="nb-stat-card s-total"><div class="nb-stat-label">Total Trades</div><div class="nb-stat-val" id="st-total">—</div></div>
        <div class="nb-stat-card s-ce">  <div class="nb-stat-label">↑ CE Trades</div> <div class="nb-stat-val" id="st-ce">—</div></div>
        <div class="nb-stat-card s-pe">  <div class="nb-stat-label">↓ PE Trades</div> <div class="nb-stat-val" id="st-pe">—</div></div>
        <div class="nb-stat-card s-syms"><div class="nb-stat-label">Symbols Hit</div>  <div class="nb-stat-val" id="st-syms">—</div></div>
        <div class="nb-stat-card s-inv"> <div class="nb-stat-label">Total Inv.</div>   <div class="nb-stat-val" id="st-inv">—</div></div>
        <div class="nb-stat-card s-sig"> <div class="nb-stat-label">Signal Days</div>  <div class="nb-stat-val" id="st-sig">—</div></div>
    </div>

    {{-- Table --}}
    <div class="nb-card nb-anim">
        <div class="nb-card-header">
            <div class="nb-card-title" id="nb-card-title">◆ NIFTY Breakout Signal Trades · Threshold: 30 pts</div>
            <span class="nb-card-subtitle" id="nb-subtitle">Detecting last available date…</span>
        </div>
        <div class="nb-tscroll">
            <table class="nb-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="4" class="g-info">Info</th>
                        <th colspan="4" class="g-nifty sep-nifty">▲ NIFTY FUT Signal</th>
                        <th colspan="4" class="g-option sep-option">◆ Option Details</th>
                        <th colspan="3" class="g-trade sep-trade">▶ Trade</th>
                    </tr>
                    <tr class="th-cols">
                        <th>#</th><th>Date</th><th>Symbol</th><th>Signal</th>
                        <th class="sep-nifty">NIFTY Open<br><span style="font-size:7px;font-weight:400;opacity:.6;">09:15</span></th>
                        <th>Trigger Val<br><span style="font-size:7px;font-weight:400;opacity:.6;">H/L</span></th>
                        <th>Signal Bar</th><th>Move (pts)</th>
                        <th class="sep-option">Strike<br><span style="font-size:7px;font-weight:400;opacity:.6;">highest OI</span></th>
                        <th>OI</th><th>Expiry</th>
                        <th>Buy Time<br><span style="font-size:7px;font-weight:400;opacity:.6;">next candle</span></th>
                        <th class="sep-trade">Buy Price ₹</th><th>Lot Size</th><th>Investment ₹</th>
                    </tr>
                </thead>
                <tbody id="nb-tbody">
                    <tr><td colspan="15">
                        <div class="nb-spinner-row">
                            <div class="nb-spinner"></div>
                            Detecting last available date…
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.nb-content --}}
</div>{{-- /.nb-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════
//  NIFTY Breakout Analyzer — JS (no jQuery)
// ═══════════════════════════════════════════════════════════

var NB_ANALYZE  = '{{ route("nifty-breakout-analyzer.analyze") }}';
var NB_SYMBOLS  = '{{ route("nifty-breakout-analyzer.symbols") }}';
var NB_LASTDATE = '{{ route("nifty-breakout-analyzer.last.date") }}';
var NB_TODAY    = '{{ now()->toDateString() }}';

var nbSymCache = null;

function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

// Threshold slider — update display + card title
el('nb-thresh').addEventListener('input', function () {
    txt('nb-thresh-disp', this.value);
    txt('nb-card-title', '◆ NIFTY Breakout Signal Trades · Threshold: ' + this.value + ' pts');
});

// ═══════════════════════════════════════════════════════════
//  BOOT — detect last available date then auto-analyze
// ═══════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    nbResolveLastDateAndLoad();
});

function nbResolveLastDateAndLoad() {
    fetch(NB_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.last_date) el('nb-date').value = res.last_date;
            nbLoadSymbols(function () { nbAnalyze(); });
        })
        .catch(function () {
            nbLoadSymbols(function () { nbAnalyze(); });
        });
}

// ── Date helpers ──────────────────────────────────────────

function nbGetDate() { return el('nb-date').value; }

function nbShiftDate(d) {
    var picker = el('nb-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > NB_TODAY) return;
    picker.value = s;
    nbAnalyze();
}

function nbGoToday() {
    el('nb-date').value = NB_TODAY;
    nbAnalyze();
}

function nbUpdateDateBadge(isToday) {
    el('nb-date-badge').innerHTML = isToday
        ? '<span class="nb-live-badge">● Live</span>'
        : '<span class="nb-hist-badge">📅 Historical</span>';
}

// ── Symbols — single select like pivot ───────────────────

function nbLoadSymbols(callback) {
    if (nbSymCache !== null) {
        nbRebuildSym(nbSymCache);
        if (callback) callback();
        return;
    }

    fetch(NB_SYMBOLS, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.no_config) {
                nbShowWarn(res.message || '');
                nbSymCache = [];
                nbRebuildSym([]);
            } else {
                nbHideWarn();
                nbSymCache = res.symbols || [];
                nbRebuildSym(nbSymCache);
            }
            if (callback) callback();
        })
        .catch(function () { if (callback) callback(); });
}

function nbRebuildSym(syms) {
    var sel  = el('nb-sym');
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

// ── Analyze ───────────────────────────────────────────────

function nbAnalyze() {
    var date = nbGetDate();
    var thr  = el('nb-thresh').value;
    var sig  = el('nb-signal').value;
    var sym  = el('nb-sym').value;

    if (!date) return;

    nbHideWarn();
    nbResetStats();

    html('nb-tbody', '<tr><td colspan="15"><div class="nb-spinner-row">'
        + '<div class="nb-spinner"></div>'
        + 'Scanning NIFTY FUT for ' + thr + 'pt breakout signals on ' + date + '…'
        + '</div></td></tr>');
    txt('nb-subtitle', date + ' · Scanning…');

    var params = new URLSearchParams({
        date: date, threshold: thr,
        filter: sig, symbol_filter: sym,
    });

    fetch(NB_ANALYZE + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { if (!r.ok) throw new Error('Server error ' + r.status); return r.json(); })
    .then(function (res) {
        if (typeof res.is_today !== 'undefined') {
            nbUpdateDateBadge(res.is_today);
        }

        if (res.available_symbols && res.available_symbols.length) {
            nbSymCache = res.available_symbols;
            nbRebuildSym(nbSymCache);
            if (sym && sym !== 'ALL') el('nb-sym').value = sym;
        }

        if (res.no_config) { nbShowWarn(res.message); nbEmptyTable('No active config.'); return; }

        if (!res.success || !res.data || !res.data.length) {
            nbEmptyTable(res.message || 'No signals found for this date.');
            txt('nb-subtitle', date + ' · No signals found');
            return;
        }

        nbRenderTable(res.data);
        nbUpdateStats(res);

        el('nb-info').innerHTML =
            '<span style="color:#047857;">CE: ' + res.ce_count + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:#b91c1c;">PE: ' + res.pe_count + '</span>'
            + ' &nbsp;·&nbsp; Threshold: <span style="color:#7DFF00;">' + res.threshold + 'pts</span>';
        txt('nb-subtitle', date + ' · ' + res.message);
        txt('nb-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) { nbEmptyTable('⚠ ' + err.message); });
}

// ── Render ────────────────────────────────────────────────

function nbRenderTable(data) {
    var h         = '', num = 1;
    var lastGroup = null;

    data.forEach(function (r, i) {
        var groupKey = r.date + '|' + r.signal_type + '|' + r.trigger_time;
        var isCE     = r.signal_type === 'CE';

        if (groupKey !== lastGroup) {
            var moveSign = r.nifty_move >= 0 ? '+' : '';
            h += '<tr class="nb-group-row' + (isCE ? '' : ' gr-pe') + '">'
                + '<td colspan="15">'
                + r.date + ' &nbsp;|&nbsp; '
                + (isCE ? '📈 CE BREAKOUT' : '📉 PE BREAKOUT')
                + ' &nbsp;|&nbsp; NIFTY Open: ₹' + r.nifty_open.toFixed(2)
                + ' → ' + (isCE ? 'HIGH' : 'LOW') + ': ₹' + r.nifty_trigger.toFixed(2)
                + ' &nbsp;|&nbsp; Signal Bar: ' + r.trigger_time
                + ' → Entry: ' + r.buy_time
                + ' &nbsp;|&nbsp; Move: ' + moveSign + r.nifty_move.toFixed(2) + ' pts'
                + '</td></tr>';
            lastGroup = groupKey;
        }

        var moveCls  = r.nifty_move >= 0 ? 'up' : 'dn';
        var moveSign = r.nifty_move >= 0 ? '+' : '';
        var rowCls   = (isCE ? 'tr-ce' : 'tr-pe') + ' ' + (i % 2 === 0 ? 'tr-even' : 'tr-odd');

        h += '<tr class="' + rowCls + '">'
            + '<td class="c-num">'  + num++ + '</td>'
            + '<td class="c-date">' + r.date + '</td>'
            + '<td class="c-sym">'  + esc(r.symbol) + (r.expiry_date ? '<small>' + r.expiry_date + '</small>' : '') + '</td>'
            + '<td>' + (isCE ? '<span class="sig-ce">📈 CE</span>' : '<span class="sig-pe">📉 PE</span>') + '</td>'
            + '<td class="sep-nifty c-val" style="color:#c97f00;">₹' + r.nifty_open.toFixed(2) + '</td>'
            + '<td class="c-val ' + (isCE ? 'up' : 'dn') + '">₹' + r.nifty_trigger.toFixed(2) + '</td>'
            + '<td><span class="time-trig' + (isCE ? '' : ' pe') + '">' + r.trigger_time + '</span></td>'
            + '<td><span class="' + moveCls + '">' + moveSign + r.nifty_move.toFixed(2) + '</span></td>'
            + '<td class="sep-option c-val" style="color:#c97f00;">₹' + nInt(r.strike) + '</td>'
            + '<td class="c-sm">' + fmtOI(r.strike_oi) + '</td>'
            + '<td class="c-sm">' + (r.expiry_date || '—') + '</td>'
            + '<td><span class="time-buy">' + r.buy_time + '</span></td>'
            + '<td class="sep-trade"><strong class="up">₹' + r.buy_price.toFixed(2) + '</strong></td>'
            + '<td class="c-sm">' + r.lot_size + '</td>'
            + '<td class="c-invest">₹' + numFmt(r.investment) + '</td>'
            + '</tr>';
    });

    html('nb-tbody', h || nbEmptyHtml('No results.'));
}

// ── Stats / helpers ───────────────────────────────────────

function nbUpdateStats(res) {
    txt('st-total', res.total_records  || '0');
    txt('st-ce',   res.ce_count       || '0');
    txt('st-pe',   res.pe_count       || '0');
    txt('st-syms', res.symbols_hit    || '0');
    txt('st-inv',  '₹' + Number(res.total_investment || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 }));
    txt('st-sig',  res.signal_count   || '0');
}

function nbResetStats() {
    ['st-total','st-ce','st-pe','st-syms','st-sig'].forEach(function (id) { txt(id, '—'); });
    txt('st-inv', '—');
}

function nbShowWarn(msg) { el('nb-warn').classList.add('show'); txt('nb-warn-msg', msg || ''); }
function nbHideWarn()    { el('nb-warn').classList.remove('show'); }
function nbEmptyTable(msg) { html('nb-tbody', nbEmptyHtml(msg)); }
function nbEmptyHtml(msg) {
    return '<tr><td colspan="15"><div class="nb-empty"><i class="las la-chart-area"></i><p>' + (msg || 'No data found.') + '</p></div></td></tr>';
}

function nbReset() {
    fetch(NB_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            el('nb-date').value   = res.last_date || NB_TODAY;
            el('nb-thresh').value = '30';
            txt('nb-thresh-disp', '30');
            txt('nb-card-title', '◆ NIFTY Breakout Signal Trades · Threshold: 30 pts');
            el('nb-signal').value = 'BOTH';
            el('nb-sym').value    = 'ALL';
            nbHideWarn();
            nbAnalyze();
        })
        .catch(function () {
            el('nb-date').value   = NB_TODAY;
            el('nb-thresh').value = '30';
            txt('nb-thresh-disp', '30');
            txt('nb-card-title', '◆ NIFTY Breakout Signal Trades · Threshold: 30 pts');
            el('nb-signal').value = 'BOTH';
            el('nb-sym').value    = 'ALL';
            nbHideWarn();
            nbAnalyze();
        });
}

function fmtOI(v)  { var n=Number(v)||0; if(n>=1e7)return(n/1e7).toFixed(2)+'Cr'; if(n>=1e5)return(n/1e5).toFixed(2)+'L'; if(n>=1e3)return(n/1e3).toFixed(1)+'K'; return n.toLocaleString('en-IN'); }
function nInt(v)   { return Number(v || 0).toLocaleString('en-IN'); }
function numFmt(v) { return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 }); }
function esc(s)    { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
@endpush