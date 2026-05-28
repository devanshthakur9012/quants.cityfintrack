{{-- FILE: resources/views/themes/{active_theme}/user/oi-flow-sentiment/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* ── BASE ── */
.ofs-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.ofs-wrap * { box-sizing:border-box; }
.ofs-wrap h1,.ofs-wrap h2,.ofs-wrap h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes ofsUp   { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.ofs-anim { animation:ofsUp .5s ease both; }
@keyframes ofsSpin { to{ transform:rotate(360deg); } }

/* ── HERO ── */
.ofs-hero {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:32px 48px; display:flex; align-items:center;
    justify-content:space-between; gap:24px;
}
.ofs-hero-left h1 { font-size:clamp(24px,3.5vw,40px); font-weight:700; color:#1a1a2e; margin:0 0 8px; line-height:1.1; }
.ofs-hero-left h1 span { color:#F5A623; }
.ofs-hero-left p { font-size:13px; color:#666; margin:0 0 10px; line-height:1.7; max-width:640px; }
.ofs-hero-pills { display:flex; flex-wrap:wrap; gap:6px; }
.ofs-pill { display:inline-block; padding:3px 10px; border-radius:4px; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700; }
.ofs-pill-bull { background:rgba(5,150,105,.08); color:#047857; border:1px solid rgba(5,150,105,.25); }
.ofs-pill-bear { background:rgba(220,38,38,.07); color:#b91c1c; border:1px solid rgba(220,38,38,.22); }
.ofs-pill-note { background:rgba(26,86,219,.07); color:#1d4ed8; border:1px solid rgba(26,86,219,.2);  }
.ofs-hero-icon {
    width:76px; height:76px; border-radius:16px;
    background:linear-gradient(135deg,#0f1b2d,#1a3050);
    display:flex; align-items:center; justify-content:center;
    font-size:32px; color:#F5A623; flex-shrink:0;
}
@media(max-width:768px){
    .ofs-hero { flex-direction:column; padding:24px 16px; text-align:center; }
    .ofs-hero-pills { justify-content:center; }
    .ofs-hero-icon { display:none; }
}

/* ── FILTER BAR ── */
.ofs-filter-bar {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:0 48px; position:sticky; top:0; z-index:200;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.ofs-filter-inner { display:flex; align-items:center; gap:12px; padding:12px 0; flex-wrap:wrap; }
.ofs-filter-label { font-size:10.5px; color:#999; font-weight:700; text-transform:uppercase; letter-spacing:.07em; flex-shrink:0; }
.ofs-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

.ofs-date-input { border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px; font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; color:#333; outline:none; }
.ofs-date-input:focus { border-color:#F5A623; }

.ofs-sym-select { border:1.5px solid #e5e9f2; border-radius:7px; padding:6px 10px; font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif; background:#fff; cursor:pointer; outline:none; min-width:120px; }
.ofs-sym-select:focus { border-color:#F5A623; }

.ofs-action-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 28px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 9px center;
    appearance:none; cursor:pointer; outline:none; min-width:130px;
}
.ofs-action-select:focus { border-color:#F5A623; }

.ofs-analyze-btn {
    background:#F5A623; color:#000; border:none; border-radius:8px;
    padding:8px 22px; font-family:'Rajdhani',sans-serif; font-size:14px;
    font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s; white-space:nowrap;
}
.ofs-analyze-btn:hover { background:#d4890e; }
.ofs-reset-btn {
    background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px;
    padding:7px 16px; font-size:12px; font-weight:700; cursor:pointer; transition:.2s; font-family:'Exo 2',sans-serif;
}
.ofs-reset-btn:hover { border-color:#F5A623; color:#c97f00; }
.ofs-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.ofs-info-text { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.ofs-upd-text  { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }
@media(max-width:768px){ .ofs-filter-bar { padding:0 12px; } .ofs-filter-right { margin-left:0;width:100%; } }

/* ── CONTENT ── */
.ofs-content { padding:28px 48px 64px; }
@media(max-width:768px){ .ofs-content { padding:16px 12px 48px; } }

.ofs-warn { background:#fff3e0; border:1px solid #ffcc80; border-radius:10px; padding:14px 20px; margin-bottom:20px; display:none; align-items:center; gap:12px; font-size:13px; color:#e65100; }
.ofs-warn.show { display:flex; }

/* ── STATS ── */
.ofs-stats { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:24px; }
@media(max-width:900px){ .ofs-stats { grid-template-columns:repeat(3,1fr); } }
@media(max-width:500px){ .ofs-stats { grid-template-columns:repeat(2,1fr); } }
.ofs-stat-card { background:#fff; border-radius:12px; border:1px solid #e8e8e8; padding:14px 16px; border-left:3px solid #e8e8e8; }
.ofs-stat-card.s-total { border-left-color:#1a56db; }
.ofs-stat-card.s-ce    { border-left-color:#059669; }
.ofs-stat-card.s-pe    { border-left-color:#dc2626; }
.ofs-stat-card.s-wait  { border-left-color:#c97f00; }
.ofs-stat-card.s-bull  { border-left-color:#059669; }
.ofs-stat-card.s-bear  { border-left-color:#dc2626; }
.ofs-stat-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#aab; margin-bottom:6px; }
.ofs-stat-val { font-family:'JetBrains Mono',monospace; font-size:24px; font-weight:700; color:#1a1a2e; }
.s-ce .ofs-stat-val   { color:#047857; }
.s-pe .ofs-stat-val   { color:#b91c1c; }
.s-wait .ofs-stat-val { color:#c97f00; }
.s-bull .ofs-stat-val { color:#047857; }
.s-bear .ofs-stat-val { color:#b91c1c; }

/* ── TABLE CARD ── */
.ofs-card { background:#fff; border-radius:12px; border:1px solid #e8e8e8; overflow:hidden; }
.ofs-card-header {
    padding:14px 20px; border-bottom:1px solid #f0f0f0;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;
    background:#fafafa;
}
.ofs-card-title { font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:#1a1a2e; }
.ofs-card-subtitle { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.ofs-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

/* Table */
.ofs-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:1000px; }
.ofs-table thead tr.th-group th {
    padding:9px 10px 5px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800;
    letter-spacing:.1em; text-transform:uppercase;
    background:#f7f8fc; border-bottom:none; white-space:nowrap;
}
.ofs-table thead tr.th-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700;
    letter-spacing:.03em; text-transform:uppercase;
    background:#f4f6fb; color:#aab; border-bottom:2px solid #e8e8e8; white-space:nowrap;
}
.g-info   { color:#1a56db !important; }
.g-oi     { color:#c97f00 !important; }
.g-signal { color:#047857 !important; }
.sep-oi     { border-left:2px solid rgba(245,166,35,.2)  !important; }
.sep-signal { border-left:2px solid rgba(5,150,105,.2)   !important; }

.ofs-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid #f5f5f5; vertical-align:middle; white-space:nowrap; color:#555;
}
.ofs-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }
.tr-bull { background:rgba(5,150,105,.03) !important; }
.tr-bear { background:rgba(220,38,38,.03) !important; }

/* Cells */
.c-num  { font-size:9px; color:#ccc; }
.c-date { font-size:11px; font-weight:700; color:#F5A623; }
.c-sym  { font-size:12px; font-weight:800; color:#1a56db; }
.c-atm  { font-size:10px; color:#c97f00; font-weight:700; }
.c-fut  { font-size:10px; color:#1a56db; }
.c-expiry { font-size:10px; color:#aab; }
.c-oi   { font-size:11px; font-weight:700; color:#1a1a2e; }
.c-oi small { display:block; font-size:8px; color:#aab; font-weight:400; margin-top:1px; }
.pct-up   { color:#059669; font-weight:700; }
.pct-down { color:#dc2626; font-weight:700; }
.pct-neu  { color:#aab; }

/* Signal badges */
.sig-bull { display:inline-block; background:rgba(5,150,105,.12); color:#047857; border:1px solid rgba(5,150,105,.35); border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-bear { display:inline-block; background:rgba(220,38,38,.1); color:#b91c1c; border:1px solid rgba(220,38,38,.35); border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-neut { display:inline-block; background:#f4f6fb; color:#aab; border:1px solid #e5e9f2; border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:600; }

/* Action badges */
.act-ce { display:inline-block; background:rgba(5,150,105,.1); color:#047857; border:1px solid rgba(5,150,105,.3); border-radius:5px; padding:2px 8px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.act-pe { display:inline-block; background:rgba(220,38,38,.08); color:#b91c1c; border:1px solid rgba(220,38,38,.25); border-radius:5px; padding:2px 8px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.act-wt { display:inline-block; background:rgba(245,166,35,.1); color:#c97f00; border:1px solid rgba(245,166,35,.3); border-radius:5px; padding:2px 8px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:700; }

/* Condition badges */
.cond-base { display:inline-block; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
.cond-ce-pe { background:rgba(220,38,38,.1); color:#b91c1c; border:1px solid rgba(220,38,38,.25); }
.cond-pe-ce { background:rgba(5,150,105,.1); color:#047857; border:1px solid rgba(5,150,105,.25); }
.cond-both  { background:rgba(124,58,237,.1); color:#6d28d9; border:1px solid rgba(124,58,237,.25); }
.cond-flat  { background:#f4f6fb; color:#aab; border:1px solid #e5e9f2; }

/* Strength rank */
.rank-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:9px; font-weight:700; }
.rank-1 { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
.rank-2 { background:#fff7ed; color:#c97f00; border:1px solid #fed7aa; }
.rank-3 { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.rank-4 { background:#f0fdf4; color:#047857; border:1px solid #bbf7d0; }
.rank-n { background:#f4f6fb; color:#aab; border:1px solid #e5e9f2; }
.rank-diff { font-size:8px; color:#aab; margin-top:1px; }
.reason-tip { font-size:9px; color:#aab; margin-top:3px; line-height:1.4; max-width:200px; white-space:normal; }

/* Empty / Loading */
.ofs-empty { text-align:center; padding:56px 20px; color:#ccc; }
.ofs-empty i { font-size:2.5rem; display:block; margin-bottom:12px; color:#e5e9f2; }
.ofs-empty p { font-size:13px; }
.ofs-spinner-row { display:flex; align-items:center; justify-content:center; gap:12px; padding:48px; color:#aab; font-size:13px; }
.ofs-spinner { width:28px; height:28px; border:3px solid #f0f0f0; border-top:3px solid #F5A623; border-radius:50%; animation:ofsSpin 1s linear infinite; flex-shrink:0; }
</style>

<div class="ofs-wrap">

{{-- HERO --}}
<div class="ofs-hero ofs-anim">
    <div class="ofs-hero-left">
        <h1>OI Flow <span>Sentiment</span> Analyzer</h1>
        <p>
            Compares today's CE/PE Open Interest (at 14:45) vs previous day's OI (at 15:00)
            to determine market sentiment — bullish or bearish — based on option writing activity.
        </p>
        <div class="ofs-hero-pills">
            <span class="ofs-pill ofs-pill-bull">CE↓ + PE↑ → BULLISH → BUY CE</span>
            <span class="ofs-pill ofs-pill-bear">CE↑ + PE↓ → BEARISH → BUY PE</span>
            <span class="ofs-pill ofs-pill-note">Both ↑/↓ → stronger side wins</span>
        </div>
    </div>
    <div class="ofs-hero-icon"><i class="las la-wave-square"></i></div>
</div>

{{-- FILTER BAR --}}
<div class="ofs-filter-bar">
    <div class="ofs-filter-inner">

        <span class="ofs-filter-label">From</span>
        <input type="date" id="ofs-from" class="ofs-date-input"
               value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">

        <span class="ofs-filter-label">To</span>
        <input type="date" id="ofs-to" class="ofs-date-input"
               value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">

        <div class="ofs-sep"></div>

        <span class="ofs-filter-label">Symbol</span>
        <select id="ofs-sym" class="ofs-sym-select" multiple size="1">
            <option value="">Loading…</option>
        </select>

        <span class="ofs-filter-label">Action</span>
        <select id="ofs-action" class="ofs-action-select">
            <option value="">All Actions</option>
            <option value="BUY CE">BUY CE Only</option>
            <option value="BUY PE">BUY PE Only</option>
            <option value="WAIT">WAIT Only</option>
        </select>

        <button class="ofs-analyze-btn" onclick="ofsAnalyze()">
            <i class="las la-search"></i> Analyze
        </button>
        <button class="ofs-reset-btn" onclick="ofsReset()">↺ Reset</button>

        <div class="ofs-filter-right">
            <span class="ofs-info-text" id="ofs-info"></span>
            <span class="ofs-upd-text"  id="ofs-upd"></span>
        </div>
    </div>
</div>

{{-- CONTENT --}}
<div class="ofs-content">

    <div class="ofs-warn" id="ofs-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="ofs-warn-msg">
                Go to Admin → Analysis Config and create a 15min config.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="ofs-stats ofs-anim">
        <div class="ofs-stat-card s-total">
            <div class="ofs-stat-label">Total</div>
            <div class="ofs-stat-val" id="st-total">—</div>
        </div>
        <div class="ofs-stat-card s-ce">
            <div class="ofs-stat-label">BUY CE</div>
            <div class="ofs-stat-val" id="st-ce">—</div>
        </div>
        <div class="ofs-stat-card s-pe">
            <div class="ofs-stat-label">BUY PE</div>
            <div class="ofs-stat-val" id="st-pe">—</div>
        </div>
        <div class="ofs-stat-card s-wait">
            <div class="ofs-stat-label">WAIT</div>
            <div class="ofs-stat-val" id="st-wait">—</div>
        </div>
        <div class="ofs-stat-card s-bull">
            <div class="ofs-stat-label">Bullish</div>
            <div class="ofs-stat-val" id="st-bull">—</div>
        </div>
        <div class="ofs-stat-card s-bear">
            <div class="ofs-stat-label">Bearish</div>
            <div class="ofs-stat-val" id="st-bear">—</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="ofs-card ofs-anim">
        <div class="ofs-card-header">
            <div class="ofs-card-title">⊙ OI Flow Sentiment — 15min</div>
            <span class="ofs-card-subtitle" id="ofs-subtitle">
                Select dates and click Analyze
            </span>
        </div>
        <div class="ofs-tscroll">
            <table class="ofs-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="5" class="g-info">Market Info</th>
                        <th colspan="4" class="g-oi sep-oi">CE / PE Open Interest</th>
                        <th colspan="4" class="g-signal sep-signal">Signal</th>
                    </tr>
                    <tr class="th-cols">
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>ATM / FUT</th>
                        <th>Expiry</th>

                        <th class="sep-oi">CE OI<br><span style="font-size:7px;font-weight:400;opacity:.6;">Today / Prev</span></th>
                        <th>CE Chg %</th>
                        <th>PE OI<br><span style="font-size:7px;font-weight:400;opacity:.6;">Today / Prev</span></th>
                        <th>PE Chg %</th>

                        <th class="sep-signal">Sentiment</th>
                        <th>Condition</th>
                        <th>Strength</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ofs-tbody">
                    <tr><td colspan="13">
                        <div class="ofs-empty">
                            <i class="las la-chart-bar"></i>
                            <p>Select a date range and click <strong>Analyze</strong></p>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</div>

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════
//  OI Flow Sentiment — JS (no jQuery)
// ═══════════════════════════════════════════════════════════

var OFS_ANALYZE = '{{ route("oi-flow-sentiment.analyze") }}';
var OFS_SYMBOLS = '{{ route("oi-flow-sentiment.symbols") }}';
var OFS_TODAY   = '{{ now()->toDateString() }}';
var ofsSymCache = null;

function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

document.addEventListener('DOMContentLoaded', function() { ofsLoadSymbols(); });

// ── Symbols ───────────────────────────────────────────────

function ofsLoadSymbols() {
    if (ofsSymCache !== null) { ofsRebuildSym(ofsSymCache); return; }

    fetch(OFS_SYMBOLS, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.no_config) { ofsShowWarn(res.message || ''); ofsRebuildSym([]); return; }
            ofsHideWarn();
            ofsSymCache = res.symbols || [];
            ofsRebuildSym(ofsSymCache);
        });
}

function ofsRebuildSym(syms) {
    var sel  = el('ofs-sym');
    var prev = Array.from(sel.selectedOptions || []).map(function(o) { return o.value; });
    if (!syms.length) { sel.innerHTML = '<option value="" disabled>No symbols</option>'; sel.size = 1; return; }
    sel.innerHTML = syms.map(function(s) {
        return '<option value="' + s + '"' + (prev.indexOf(s) > -1 ? ' selected' : '') + '>' + s + '</option>';
    }).join('');
    sel.size = Math.min(3, Math.max(1, syms.length));
}

// ── Analyze ───────────────────────────────────────────────

function ofsAnalyze() {
    var from   = el('ofs-from').value;
    var to     = el('ofs-to').value;
    var action = el('ofs-action').value;
    var symSel = el('ofs-sym');
    var syms   = Array.from(symSel.selectedOptions || []).map(function(o) { return o.value; }).filter(Boolean);

    if (!from || !to) { alert('Please select both dates.'); return; }

    ofsHideWarn();
    ofsResetStats();

    html('ofs-tbody', '<tr><td colspan="13"><div class="ofs-spinner-row">'
        + '<div class="ofs-spinner"></div>'
        + 'Calculating CE/PE OI flow for ' + from + ' → ' + to + '…'
        + '</div></td></tr>');
    txt('ofs-subtitle', 'Loading…');

    var params = new URLSearchParams({ from_date: from, to_date: to, filter_action: action });
    syms.forEach(function(s) { params.append('symbols[]', s); });

    fetch(OFS_ANALYZE + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function(res) {
        if (res.no_config) { ofsShowWarn(res.message); ofsEmptyTable('No active config.'); return; }

        if (!res.success || !res.data || !res.data.length) {
            ofsEmptyTable(res.message || 'No signals found.');
            return;
        }

        ofsUpdateStats(res);
        ofsRenderTable(res.data);

        el('ofs-info').innerHTML =
            '<span style="color:#047857;">CE: ' + res.buy_ce_count + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:#b91c1c;">PE: ' + res.buy_pe_count + '</span>'
            + ' &nbsp;·&nbsp; 15min';
        txt('ofs-subtitle', '15min · ' + from + ' → ' + to + ' · ' + res.message);
        txt('ofs-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function(err) {
        ofsEmptyTable('⚠ ' + err.message);
    });
}

// ── Render ────────────────────────────────────────────────

function ofsRenderTable(data) {
    var h = '';
    var num = 1;

    data.forEach(function(r, i) {
        var isBull = r.sentiment === 'BULLISH';
        var isBear = r.sentiment === 'BEARISH';
        var rowCls = isBull ? 'tr-bull' : isBear ? 'tr-bear' : '';
        var zebra  = i % 2 === 0 ? 'tr-even' : 'tr-odd';

        var sentBadge = isBull
            ? '<span class="sig-bull">▲ BULLISH</span>'
            : isBear
                ? '<span class="sig-bear">▼ BEARISH</span>'
                : '<span class="sig-neut">— NEUTRAL</span>';

        var actBadge = r.trade_action === 'BUY CE'
            ? '<span class="act-ce">📈 BUY CE</span>'
            : r.trade_action === 'BUY PE'
                ? '<span class="act-pe">📉 BUY PE</span>'
                : '<span class="act-wt">⏸ WAIT</span>';

        // Condition badge class
        var condCls = 'cond-base cond-flat';
        var cond = r.condition || '';
        if (cond.includes('CE ↑') && cond.includes('PE ↓')) condCls = 'cond-base cond-ce-pe';
        else if (cond.includes('CE ↓') && cond.includes('PE ↑')) condCls = 'cond-base cond-pe-ce';
        else if (cond.includes('Both')) condCls = 'cond-base cond-both';

        // Strength rank
        var rankCls = { 'Rank 1':'rank-badge rank-1','Rank 2':'rank-badge rank-2',
                        'Rank 3':'rank-badge rank-3','Rank 4':'rank-badge rank-4',
                        'Normal':'rank-badge rank-n' }[r.strength_rank] || 'rank-badge rank-n';

        h += '<tr class="' + rowCls + ' ' + zebra + '">'
            + '<td class="c-num">' + num++ + '</td>'
            + '<td class="c-date">' + r.date + '</td>'
            + '<td class="c-sym">' + esc(r.symbol) + '</td>'
            + '<td>'
                + (r.atm_strike ? '<span class="c-atm">₹' + nInt(r.atm_strike) + '</span>' : '—')
                + (r.fut_price  ? '<br><span class="c-fut">F:₹' + f(r.fut_price) + '</span>' : '')
            + '</td>'
            + '<td class="c-expiry">' + (r.expiry || '—') + '</td>'
            + '<td class="sep-oi c-oi">' + nInt(r.ce_oi) + '<small>prev: ' + nInt(r.ce_oi_prev) + '</small></td>'
            + '<td>' + pctCell(r.ce_oi_pct) + '</td>'
            + '<td class="c-oi">' + nInt(r.pe_oi) + '<small>prev: ' + nInt(r.pe_oi_prev) + '</small></td>'
            + '<td>' + pctCell(r.pe_oi_pct) + '</td>'
            + '<td class="sep-signal">' + sentBadge + '</td>'
            + '<td>'
                + '<span class="' + condCls + '">' + esc(cond) + '</span>'
                + (r.reason ? '<div class="reason-tip">' + esc(r.reason) + '</div>' : '')
            + '</td>'
            + '<td>'
                + '<span class="' + rankCls + '">' + r.strength_rank + '</span>'
                + '<div class="rank-diff">Δ ' + r.oi_diff + '%</div>'
            + '</td>'
            + '<td>' + actBadge + '</td>'
            + '</tr>';
    });

    html('ofs-tbody', h || ofsEmptyHtml('No results.'));
}

// ── Stats / helpers ───────────────────────────────────────

function ofsUpdateStats(res) {
    txt('st-total', res.total_records  || '0');
    txt('st-ce',   res.buy_ce_count   || '0');
    txt('st-pe',   res.buy_pe_count   || '0');
    txt('st-wait', res.wait_count     || '0');
    txt('st-bull', res.bullish_count  || '0');
    txt('st-bear', res.bearish_count  || '0');
}

function ofsResetStats() {
    ['st-total','st-ce','st-pe','st-wait','st-bull','st-bear'].forEach(function(id){ txt(id,'—'); });
}

function ofsShowWarn(msg) { el('ofs-warn').classList.add('show'); txt('ofs-warn-msg', msg||''); }
function ofsHideWarn()    { el('ofs-warn').classList.remove('show'); }

function ofsEmptyTable(msg) { html('ofs-tbody', ofsEmptyHtml(msg)); }
function ofsEmptyHtml(msg) {
    return '<tr><td colspan="13"><div class="ofs-empty"><i class="las la-chart-bar"></i><p>'
        + (msg || 'No data found.') + '</p></div></td></tr>';
}

function ofsReset() {
    el('ofs-from').value = OFS_TODAY;
    el('ofs-to').value   = OFS_TODAY;
    el('ofs-action').value = '';
    Array.from(el('ofs-sym').options).forEach(function(o){ o.selected = false; });
    ofsResetStats();
    ofsEmptyTable('Reset — select dates and click Analyze.');
    txt('ofs-info',''); txt('ofs-upd','');
    txt('ofs-subtitle','Select dates and click Analyze');
    ofsHideWarn();
}

function pctCell(v) {
    if (v == null) return '<span class="pct-neu">—</span>';
    var n = parseFloat(v) || 0;
    var cls = n > 0 ? 'pct-up' : n < 0 ? 'pct-down' : 'pct-neu';
    return '<span class="' + cls + '">' + (n > 0 ? '+' : '') + n.toFixed(2) + '%</span>';
}

function f(v)   { return parseFloat(v || 0).toFixed(2); }
function nInt(v) {
    var n = Number(v) || 0;
    if (n >= 1e7) return (n/1e7).toFixed(2) + 'Cr';
    if (n >= 1e5) return (n/1e5).toFixed(2) + 'L';
    if (n >= 1e3) return (n/1e3).toFixed(1) + 'K';
    return n.toLocaleString('en-IN');
}
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush