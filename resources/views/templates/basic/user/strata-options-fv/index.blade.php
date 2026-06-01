{{-- FILE: resources/views/themes/{active_theme}/user/strata-options-fv/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* ── BASE ── */
body { background:#f7f8fc; }
.sfv-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.sfv-wrap * { box-sizing:border-box; }
.sfv-wrap h1,.sfv-wrap h2,.sfv-wrap h3,.sfv-wrap h4 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes sfvFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.sfv-anim { animation:sfvFadeUp .5s ease both; }
@keyframes sfvSpin { to{ transform:rotate(360deg); } }

/* ── HERO ── */
.sfv-hero {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:32px 48px; display:flex; align-items:center;
    justify-content:space-between; gap:24px;
}
.sfv-hero-left h1 {
    font-size:clamp(26px,3.5vw,42px); font-weight:700;
    color:#1a1a2e; margin:0 0 8px; line-height:1.1;
}
.sfv-hero-left h1 span { color:#F5A623; }
.sfv-hero-left p { font-size:13px; color:#666; margin:0; line-height:1.7; max-width:580px; }
.sfv-hero-icon {
    width:80px; height:80px; border-radius:16px;
    background:linear-gradient(135deg,#0f1b2d,#1a3050);
    display:flex; align-items:center; justify-content:center;
    font-size:36px; color:#F5A623; flex-shrink:0;
}
@media(max-width:768px){
    .sfv-hero { flex-direction:column; padding:24px 16px; text-align:center; }
}

/* ── FILTER BAR ── */
.sfv-filter-bar {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:0 48px; position:sticky; top:0; z-index:200;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.sfv-filter-inner {
    display:flex; align-items:center; gap:14px;
    padding:13px 0; flex-wrap:wrap;
}
.sfv-filter-label {
    font-size:10.5px; color:#999; font-weight:700;
    text-transform:uppercase; letter-spacing:.07em;
}
.sfv-filter-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

/* Date */
.sfv-date-wrap { display:flex; align-items:center; gap:4px; }
.sfv-date-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
    color:#333; outline:none; cursor:pointer;
}
.sfv-date-input:focus { border-color:#F5A623; }
.sfv-date-nav {
    width:28px; height:32px; border:1.5px solid #e5e9f2; border-radius:6px;
    background:#fff; color:#888; cursor:pointer; font-weight:700; font-size:14px;
    display:flex; align-items:center; justify-content:center; transition:.2s;
}
.sfv-date-nav:hover { border-color:#F5A623; color:#F5A623; }
.sfv-today-btn { width:auto; padding:0 10px; font-size:10px; font-family:'Exo 2',sans-serif; font-weight:700; letter-spacing:.07em; }

/* Status badge */
.sfv-live-badge { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.sfv-hist-badge { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }

/* Strike pills */
.sfv-sp-wrap { display:flex; gap:3px; }
.sfv-sp {
    padding:5px 13px; border-radius:20px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:700;
    cursor:pointer; border:1.5px solid #e5e9f2; background:#fff; color:#888; transition:.15s;
}
.sfv-sp:hover  { border-color:#F5A623; color:#c97f00; }
.sfv-sp.active { background:rgba(245,166,35,.1); border-color:#F5A623; color:#c97f00; }

/* Symbol + Sort selects */
.sfv-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 30px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance:none; cursor:pointer; outline:none; min-width:150px;
}
.sfv-select:focus { border-color:#F5A623; }

/* Buttons */
.sfv-btn {
    background:#F5A623; color:#000; border:none; border-radius:8px;
    padding:8px 20px; font-family:'Rajdhani',sans-serif; font-size:13px;
    font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s;
}
.sfv-btn:hover { background:#d4890e; }
.sfv-reset-btn {
    background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px;
    padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer;
    font-family:'Exo 2',sans-serif; transition:.2s;
}
.sfv-reset-btn:hover { border-color:#F5A623; color:#c97f00; }
.sfv-auto-btn {
    background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px;
    padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer;
    font-family:'Exo 2',sans-serif; transition:.2s;
}
.sfv-auto-btn.on { border-color:#059669; background:rgba(5,150,105,.08); color:#047857; }

.sfv-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.sfv-info-text { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.sfv-last-upd  { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }

@media(max-width:768px){
    .sfv-filter-bar { padding:0 16px; }
    .sfv-filter-inner { gap:8px; }
    .sfv-filter-right { margin-left:0; width:100%; }
}

/* ── CONTENT ── */
.sfv-content { padding:28px 48px 64px; }
@media(max-width:768px){ .sfv-content { padding:16px 12px 48px; } }

/* Config warning */
.sfv-warn {
    background:#fff3e0; border:1px solid #ffcc80; border-radius:10px;
    padding:16px 20px; margin-bottom:20px;
    display:none; align-items:center; gap:14px; font-size:13px; color:#e65100;
}
.sfv-warn.show { display:flex; }
.sfv-warn i { font-size:20px; flex-shrink:0; }

/* ── STATS ── */
.sfv-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; }
.sfv-stat-box {
    background:#fff; border:1px solid #e8e8e8;
    border-radius:12px; padding:14px 18px; min-width:110px; flex:1;
}
.sfv-stat-box small {
    display:block; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700;
    text-transform:uppercase; letter-spacing:.07em; color:#aab; margin-bottom:5px;
}
.sfv-stat-box strong {
    display:block; font-family:'JetBrains Mono',monospace; font-size:1.2rem; font-weight:700;
}
.s-total { border-left:3px solid #F5A623; }
.s-ce    { border-left:3px solid #047857; }
.s-pe    { border-left:3px solid #b91c1c; }

/* ── TABLE CARD ── */
.sfv-card {
    background:#fff; border-radius:12px; border:1px solid #e8e8e8;
    overflow:hidden;
}
.sfv-card-header {
    padding:14px 20px; border-bottom:1px solid #f0f0f0;
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:8px; background:#fafafa;
}
.sfv-card-title {
    font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700;
    color:#1a1a2e; display:flex; align-items:center; gap:8px;
}
.sfv-card-subtitle { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.sfv-table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

/* ── TABLE ── */
.sfv-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:960px; }
.sfv-table thead tr.th-group th {
    padding:9px 10px 5px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800;
    letter-spacing:.1em; text-transform:uppercase;
    background:#f7f8fc; border-bottom:none; white-space:nowrap;
}
.sfv-table thead tr.th-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700;
    letter-spacing:.03em; text-transform:uppercase;
    background:#f4f6fb; color:#aab;
    border-bottom:2px solid #e8e8e8; white-space:nowrap;
}
.g-meta { color:#888 !important; }
.g-ce   { color:#047857 !important; }
.g-pe   { color:#b91c1c !important; }
.g-iv   { color:#c97f00 !important; }
.sep-ce   { border-left:2px solid rgba(4,120,87,.2)  !important; }
.sep-pe   { border-left:2px solid rgba(185,28,28,.2)  !important; }
.sep-dash { border-left:1px dashed #e8e8e8 !important; }

.sfv-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid #f5f5f5; vertical-align:middle;
    white-space:nowrap; color:#555;
}
.sfv-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }

.c-num  { font-size:9px; color:#ccc; }
.c-time { font-size:12px; font-weight:700; color:#F5A623; }
.c-spot { font-size:12px; font-weight:700; color:#1a1a2e; }
.c-sym  {
    display:inline-block; padding:2px 9px; border-radius:5px; font-size:10px; font-weight:700;
    background:rgba(245,166,35,.1); color:#c97f00; border:1px solid rgba(245,166,35,.25);
}
.c-level {
    display:inline-block; padding:1px 5px; border-radius:3px; font-size:7px; font-weight:700;
    background:#fff3e0; color:#e65100; border:1px solid #ffcc80; margin-top:2px;
}
.vb { display:inline-block; padding:2px 8px; border-radius:5px; font-size:9px; font-weight:700; }
.vb-over  { background:rgba(185,28,28,.1);  color:#b91c1c; border:1px solid rgba(185,28,28,.25); }
.vb-under { background:rgba(4,120,87,.1);   color:#047857; border:1px solid rgba(4,120,87,.25);  }
.vb-fair  { background:#f4f6fb; color:#aab; border:1px solid #e5e9f2; }
.vb-na    { color:#ccc; font-size:9px; }
.dp { color:#b91c1c; font-weight:700; }
.dn { color:#047857; font-weight:700; }
.dz { color:#ccc; }

/* Loading / empty */
.sfv-loading {
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; padding:60px 20px;
}
.sfv-spinner {
    width:36px; height:36px; border:3px solid #f0f0f0;
    border-top:3px solid #F5A623; border-radius:50%;
    animation:sfvSpin 1s linear infinite;
}
.sfv-loading-text { color:#aab; margin-top:12px; font-size:13px; font-family:'Exo 2',sans-serif; }
.sfv-empty { text-align:center; padding:56px 20px; color:#ccc; font-family:'Exo 2',sans-serif; font-size:13px; }
.sfv-empty i { font-size:2.5rem; display:block; margin-bottom:12px; }
</style>

<div class="sfv-wrap">

{{-- ══ HERO ══ --}}
<div class="sfv-hero sfv-anim">
    <div class="sfv-hero-left">
        <h1>Options <span>Fair Value</span></h1>
        <p>
            Black-Scholes fair price vs market LTP for CE &amp; PE options —
            using cross-leg IV derivation to eliminate circular mispricing bias
            and surface genuine valuation signals.
        </p>
    </div>
    <div class="sfv-hero-icon">
        <i class="las la-balance-scale"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="sfv-filter-bar">
    <div class="sfv-filter-inner">

        {{-- Symbol --}}
        <span class="sfv-filter-label">Symbol</span>
        <select id="sfv-sym" class="sfv-select" onchange="sfvRunAnalysis()">
            <option value="">— All Symbols —</option>
        </select>

        <div class="sfv-filter-sep"></div>

        {{-- Date --}}
        <span class="sfv-filter-label">Date</span>
        <div class="sfv-date-wrap">
            <button class="sfv-date-nav" onclick="sfvShiftDate(-1)">‹</button>
            <input type="date" id="sfv-date" class="sfv-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="sfvRunAnalysis()">
            <button class="sfv-date-nav" onclick="sfvShiftDate(1)">›</button>
            <button class="sfv-date-nav sfv-today-btn" onclick="sfvGoToday()">TODAY</button>
            <span id="sfv-date-badge"></span>
        </div>

        <div class="sfv-filter-sep"></div>

        {{-- Strike --}}
        <span class="sfv-filter-label">Strike</span>
        <div class="sfv-sp-wrap">
            <div class="sfv-sp" data-val="ATM-1">ATM−1</div>
            <div class="sfv-sp active" data-val="ATM">ATM</div>
            <div class="sfv-sp" data-val="ATM+1">ATM+1</div>
        </div>

        {{-- Sort --}}
        <select id="sfv-sort" class="sfv-select">
            <option value="symbol">Sort: A – Z</option>
            <option value="ce_overpriced">CE Most Overpriced</option>
            <option value="ce_underpriced">CE Most Underpriced</option>
            <option value="pe_overpriced">PE Most Overpriced</option>
            <option value="pe_underpriced">PE Most Underpriced</option>
            <option value="mispricing">Largest Mispricing</option>
        </select>

        <button class="sfv-btn" onclick="sfvRunAnalysis()">&#9670; Analyze</button>
        <button class="sfv-reset-btn" onclick="sfvClearSymbol()">All Symbols</button>
        <button class="sfv-auto-btn" id="sfv-auto-btn" onclick="sfvToggleAuto()">&#9654; Auto 60s</button>

        <div class="sfv-filter-right">
            <span class="sfv-info-text" id="sfv-info"></span>
            <span class="sfv-last-upd"  id="sfv-upd"></span>
        </div>

    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="sfv-content">

    {{-- Config warning --}}
    <div class="sfv-warn" id="sfv-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="sfv-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="sfv-stats" id="sfv-stats" style="display:none;">
        <div class="sfv-stat-box s-total"><small>Total Rows</small>   <strong id="st-total"    style="color:#F5A623;">0</strong></div>
        <div class="sfv-stat-box s-ce">  <small>CE Overpriced</small> <strong id="st-ce-over"  style="color:#b91c1c;">0</strong></div>
        <div class="sfv-stat-box s-ce">  <small>CE Underpriced</small><strong id="st-ce-under" style="color:#047857;">0</strong></div>
        <div class="sfv-stat-box s-pe">  <small>PE Overpriced</small> <strong id="st-pe-over"  style="color:#b91c1c;">0</strong></div>
        <div class="sfv-stat-box s-pe">  <small>PE Underpriced</small><strong id="st-pe-under" style="color:#047857;">0</strong></div>
    </div>

    {{-- Table card --}}
    <div class="sfv-card">
        <div class="sfv-card-header">
            <div class="sfv-card-title" id="sfv-card-title">&#9670; Strata Fair Value</div>
            <span class="sfv-card-subtitle" id="sfv-card-subtitle">Detecting last available date…</span>
        </div>
        <div class="sfv-table-scroll">
            <table class="sfv-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="5" class="g-meta">Info</th>
                        <th colspan="5" class="g-ce sep-ce">&#9651; CE — Market vs Fair</th>
                        <th colspan="5" class="g-pe sep-pe">&#9661; PE — Market vs Fair</th>
                        <th class="g-iv">ATM IV</th>
                        <th class="g-iv">Exp Move</th>
                    </tr>
                    <tr class="th-cols">
                        <th class="g-meta">#</th>
                        <th class="g-meta">Time</th>
                        <th class="g-meta" style="text-align:left;padding-left:14px;">Symbol</th>
                        <th class="g-meta">Spot</th>
                        <th class="g-meta">Strike<br><span style="font-size:7px;opacity:.5;font-weight:400;">Level · DTE</span></th>
                        <th class="g-ce sep-ce">LTP</th>
                        <th class="g-ce">Fair ₹</th>
                        <th class="g-ce">Status</th>
                        <th class="g-ce sep-dash">Diff ₹</th>
                        <th class="g-ce">Diff %</th>
                        <th class="g-pe sep-pe">LTP</th>
                        <th class="g-pe">Fair ₹</th>
                        <th class="g-pe">Status</th>
                        <th class="g-pe sep-dash">Diff ₹</th>
                        <th class="g-pe">Diff %</th>
                        <th class="g-iv">IV %</th>
                        <th class="g-iv">±₹</th>
                    </tr>
                </thead>
                <tbody id="sfv-tbody">
                    <tr><td colspan="17">
                        <div class="sfv-loading">
                            <div class="sfv-spinner"></div>
                            <div class="sfv-loading-text">Detecting last available date…</div>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.sfv-content --}}
</div>{{-- /.sfv-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  STRATA OPTIONS FAIR VALUE — JS (zero jQuery)
// ═══════════════════════════════════════════════════════════════

var SFV_ANALYZE_URL = '{{ route("strata-options-fv.analyze") }}';
var SFV_SYM_URL     = '{{ route("strata-options-fv.symbols") }}';
var SFV_LASTDATE    = '{{ route("strata-options-fv.last.date") }}';
var SFV_TODAY       = '{{ now()->toDateString() }}';

var sfvCurStrike = 'ATM';
var sfvSymCache  = null;
var sfvAutoTimer = null;

function sfvHtml(id, h) { var e = document.getElementById(id); if (e) e.innerHTML  = h; }
function sfvText(id, t) { var e = document.getElementById(id); if (e) e.textContent = t; }

// ═══════════════════════════════════════════════════════════════
//  BOOT — detect last available date then auto-analyze
// ═══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    // Strike pill clicks
    document.querySelectorAll('.sfv-sp').forEach(function (pill) {
        pill.addEventListener('click', function () {
            document.querySelectorAll('.sfv-sp').forEach(function (p) { p.classList.remove('active'); });
            pill.classList.add('active');
            sfvCurStrike = pill.getAttribute('data-val');
            sfvRunAnalysis();
        });
    });

    // Sort change
    document.getElementById('sfv-sort').addEventListener('change', sfvRunAnalysis);

    // Detect last date → load symbols → analyze
    sfvResolveLastDateAndLoad();
});

function sfvResolveLastDateAndLoad() {
    fetch(SFV_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.last_date) {
                document.getElementById('sfv-date').value = res.last_date;
            }
            sfvUpdateDateBadge();
            sfvLoadSymbols(function () { sfvRunAnalysis(); });
        })
        .catch(function () {
            sfvUpdateDateBadge();
            sfvLoadSymbols(function () { sfvRunAnalysis(); });
        });
}

// ── Date helpers ──────────────────────────────────────────────

function sfvShiftDate(d) {
    var picker = document.getElementById('sfv-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > SFV_TODAY) return;
    picker.value = s;
    sfvUpdateDateBadge();
    sfvRunAnalysis();
}

function sfvGoToday() {
    document.getElementById('sfv-date').value = SFV_TODAY;
    sfvUpdateDateBadge();
    sfvRunAnalysis();
}

function sfvUpdateDateBadge() {
    var d  = document.getElementById('sfv-date').value;
    var el = document.getElementById('sfv-date-badge');
    if (!el) return;
    el.innerHTML = d === SFV_TODAY
        ? '<span class="sfv-live-badge">● Live</span>'
        : '<span class="sfv-hist-badge">📅 Historical</span>';
}

// ── Symbol helpers ────────────────────────────────────────────

function sfvLoadSymbols(callback) {
    if (sfvSymCache !== null) {
        sfvRebuildSym(sfvSymCache);
        if (callback) callback();
        return;
    }

    fetch(SFV_SYM_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.no_config) {
                sfvShowWarn(res.message);
                sfvRebuildSym([]);
            } else {
                sfvHideWarn();
                sfvSymCache = res.symbols || [];
                sfvRebuildSym(sfvSymCache);
            }
            if (callback) callback();
        })
        .catch(function () {
            sfvRebuildSym([]);
            if (callback) callback();
        });
}

function sfvRebuildSym(syms) {
    var sel  = document.getElementById('sfv-sym');
    var prev = sel.value;
    sel.innerHTML = '<option value="">— All Symbols —</option>';
    syms.forEach(function (s) {
        var opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

function sfvClearSymbol() {
    document.getElementById('sfv-sym').value = '';
    sfvRunAnalysis();
}

// ── Auto refresh ──────────────────────────────────────────────

function sfvToggleAuto() {
    var btn = document.getElementById('sfv-auto-btn');
    if (sfvAutoTimer) {
        clearInterval(sfvAutoTimer);
        sfvAutoTimer = null;
        btn.textContent = '▶ Auto 60s';
        btn.classList.remove('on');
    } else {
        sfvAutoTimer = setInterval(sfvRunAnalysis, 60000);
        btn.textContent = '■ Stop';
        btn.classList.add('on');
        sfvRunAnalysis();
    }
}

// ── Main analysis call ────────────────────────────────────────

function sfvRunAnalysis() {
    var sym  = document.getElementById('sfv-sym').value;
    var sort = document.getElementById('sfv-sort').value;
    var date = document.getElementById('sfv-date').value;

    sfvUpdateDateBadge();
    document.getElementById('sfv-sort').style.display = sym ? 'none' : '';

    sfvShowLoading();

    var params = new URLSearchParams({
        strike_filter : sfvCurStrike,
        sort_by       : sort,
        date          : date,
    });
    if (sym) params.append('symbol', sym);

    fetch(SFV_ANALYZE_URL + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (res.no_config) { sfvShowWarn(res.message); sfvEmptyTable(); return; }
        if (!res.success)  { sfvEmptyTable(res.message); return; }

        sfvHideWarn();
        sfvRenderStats(res.summary, res.total_rows);
        sfvRenderTable(res.rows);

        sfvHtml('sfv-info',
            'Date: <span style="color:#c97f00">' + res.trade_date + '</span>'
            + ' &nbsp;·&nbsp; Time: <span style="color:#047857">' + (res.latest_time || '—') + '</span>'
        );
        sfvText('sfv-card-subtitle', res.total_rows + ' row(s) · ' + res.trade_date);
        sfvText('sfv-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) {
        sfvEmptyTable('⚠ ' + err.message);
    });
}

// ── Stats ─────────────────────────────────────────────────────

function sfvRenderStats(s, total) {
    sfvText('st-total',    total);
    sfvText('st-ce-over',  s.ceOver);
    sfvText('st-ce-under', s.ceUnder);
    sfvText('st-pe-over',  s.peOver);
    sfvText('st-pe-under', s.peUnder);
    var el = document.getElementById('sfv-stats');
    if (el) el.style.display = '';
}

// ── Table renderer ────────────────────────────────────────────

function sfvRenderTable(rows) {
    if (!rows || !rows.length) { sfvEmptyTable('No data for selected filters.'); return; }

    var html = '';
    rows.forEach(function (r, idx) {
        var zebra = idx % 2 === 0 ? 'tr-even' : 'tr-odd';

        var ceCols = r.ce_ltp != null
            ? sfvTd('sep-ce', '₹' + r.ce_ltp)
            + sfvTd('', '<strong style="color:#047857;">₹' + sfvNv(r.ce_fair) + '</strong>')
            + sfvTd('', sfvVbadge(r.ce_status))
            + sfvTd('sep-dash ' + sfvDc(r.ce_diff),  sfvDiffFmt(r.ce_diff, '₹'))
            + sfvTd(sfvDc(r.ce_diff_pct), sfvDiffPct(r.ce_diff_pct))
            : '<td colspan="5" class="sep-ce" style="color:#ccc;font-size:9px;">— no CE —</td>';

        var peCols = r.pe_ltp != null
            ? sfvTd('sep-pe', '₹' + r.pe_ltp)
            + sfvTd('', '<strong style="color:#b91c1c;">₹' + sfvNv(r.pe_fair) + '</strong>')
            + sfvTd('', sfvVbadge(r.pe_status))
            + sfvTd('sep-dash ' + sfvDc(r.pe_diff),  sfvDiffFmt(r.pe_diff, '₹'))
            + sfvTd(sfvDc(r.pe_diff_pct), sfvDiffPct(r.pe_diff_pct))
            : '<td colspan="5" class="sep-pe" style="color:#ccc;font-size:9px;">— no PE —</td>';

        var strikeMeta =
            '<span style="color:#c97f00;font-weight:700;">₹' + sfvFmt(r.strike) + '</span>'
            + '<br><span class="c-level">' + (r.strike_level || 'ATM') + '</span>'
            + '&thinsp;<span style="font-size:8px;color:#ccc;">' + r.days_to_expiry + 'd</span>';

        html += '<tr class="' + zebra + '">'
            + sfvTd('c-num', idx + 1)
            + sfvTd('c-time', r.time || '—')
            + '<td style="text-align:left;padding-left:14px;"><span class="c-sym">' + sfvEsc(r.symbol) + '</span></td>'
            + sfvTd('c-spot', '₹' + sfvFmt(r.spot))
            + '<td>' + strikeMeta + '</td>'
            + ceCols
            + peCols
            + sfvTd('', r.atm_iv != null
                ? '<span style="color:#c97f00;font-weight:700;">' + r.atm_iv + '%</span>'
                : sfvDash())
            + sfvTd('', r.expected_move != null
                ? '<span style="color:#F5A623;font-weight:700;">±₹' + r.expected_move + '</span>'
                : sfvDash())
            + '</tr>';
    });

    sfvHtml('sfv-tbody', html);
}

// ── Cell / badge helpers ──────────────────────────────────────

function sfvTd(cls, inner) {
    return '<td' + (cls ? ' class="' + cls + '"' : '') + '>' + inner + '</td>';
}

function sfvVbadge(st) {
    var map = { OVERPRICED:'vb-over', UNDERPRICED:'vb-under', FAIR:'vb-fair' };
    return '<span class="vb ' + (map[st] || 'vb-na') + '">'
        + (st === 'N/A' ? '—' : (st || '—')) + '</span>';
}

function sfvDc(v) {
    if (v == null) return 'dz';
    return Number(v) > 0 ? 'dp' : (Number(v) < 0 ? 'dn' : 'dz');
}

function sfvDiffFmt(v, pfx) {
    if (v == null) return sfvDash();
    var n = Number(v);
    return (n >= 0 ? '+' : '') + pfx + Math.abs(n).toFixed(2);
}

function sfvDiffPct(v) {
    if (v == null) return sfvDash();
    var n = Number(v);
    return (n >= 0 ? '+' : '') + n + '%';
}

function sfvNv(v)  { return v != null ? v : '—'; }
function sfvDash() { return '<span style="color:#ccc;font-size:9px;">—</span>'; }
function sfvFmt(v) {
    return v != null
        ? Number(v).toLocaleString('en-IN', { maximumFractionDigits:2 })
        : '—';
}
function sfvEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Loading / empty / warn ────────────────────────────────────

function sfvShowLoading() {
    sfvHtml('sfv-tbody',
        '<tr><td colspan="17">'
        + '<div class="sfv-loading"><div class="sfv-spinner"></div>'
        + '<div class="sfv-loading-text">Calculating fair values…</div></div>'
        + '</td></tr>'
    );
    var el = document.getElementById('sfv-stats');
    if (el) el.style.display = 'none';
}

function sfvEmptyTable(msg) {
    sfvHtml('sfv-tbody',
        '<tr><td colspan="17">'
        + '<div class="sfv-empty"><i class="las la-chart-line"></i>'
        + (msg || 'No data found for this date.')
        + '</div></td></tr>'
    );
    var el = document.getElementById('sfv-stats');
    if (el) el.style.display = 'none';
}

function sfvShowWarn(msg) {
    var el = document.getElementById('sfv-warn');
    if (el) el.classList.add('show');
    sfvText('sfv-warn-msg', msg || '');
}

function sfvHideWarn() {
    var el = document.getElementById('sfv-warn');
    if (el) el.classList.remove('show');
}
</script>
@endpush