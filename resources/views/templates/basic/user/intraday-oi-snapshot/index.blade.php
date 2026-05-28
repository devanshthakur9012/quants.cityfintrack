{{-- FILE: resources/views/themes/{active_theme}/user/intraday-oi-snapshot/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
.ios-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.ios-wrap * { box-sizing:border-box; }
.ios-wrap h1,.ios-wrap h2,.ios-wrap h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes iosUp   { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.ios-anim { animation:iosUp .5s ease both; }
@keyframes iosSpin { to{transform:rotate(360deg);} }

/* ── HERO ── */
.ios-hero {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:32px 48px; display:flex; align-items:center;
    justify-content:space-between; gap:24px;
}
.ios-hero-left h1 { font-size:clamp(24px,3.5vw,40px); font-weight:700; color:#1a1a2e; margin:0 0 8px; line-height:1.1; }
.ios-hero-left h1 span { color:#F5A623; }
.ios-hero-left p { font-size:13px; color:#666; margin:0 0 10px; line-height:1.7; max-width:640px; }
.ios-hero-pills { display:flex; flex-wrap:wrap; gap:6px; }
.ios-pill { display:inline-block; padding:3px 10px; border-radius:4px; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700; }
.ios-pill-snap { background:rgba(13,148,136,.08);  color:#0f766e; border:1px solid rgba(13,148,136,.25); }
.ios-pill-bull { background:rgba(5,150,105,.08);   color:#047857; border:1px solid rgba(5,150,105,.25); }
.ios-pill-bear { background:rgba(220,38,38,.07);   color:#b91c1c; border:1px solid rgba(220,38,38,.22); }
.ios-hero-icon {
    width:76px; height:76px; border-radius:16px;
    background:linear-gradient(135deg,#0f1b2d,#1a3050);
    display:flex; align-items:center; justify-content:center;
    font-size:32px; color:#F5A623; flex-shrink:0;
}
@media(max-width:768px){ .ios-hero{ flex-direction:column; padding:24px 16px; text-align:center; } .ios-hero-pills{ justify-content:center; } .ios-hero-icon{ display:none; } }

/* ── FILTER BAR ── */
.ios-filter-bar { background:#fff; border-bottom:1px solid #e8e8e8; padding:0 48px; position:sticky; top:0; z-index:200; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.ios-filter-inner { display:flex; align-items:center; gap:12px; padding:12px 0; flex-wrap:wrap; }
.ios-filter-label { font-size:10.5px; color:#999; font-weight:700; text-transform:uppercase; letter-spacing:.07em; flex-shrink:0; }
.ios-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

.ios-date-input { border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px; font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; color:#333; outline:none; }
.ios-date-input:focus { border-color:#F5A623; }
.ios-sym-select { border:1.5px solid #e5e9f2; border-radius:7px; padding:6px 10px; font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif; background:#fff; cursor:pointer; outline:none; min-width:120px; }
.ios-sym-select:focus { border-color:#F5A623; }
.ios-action-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 28px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 9px center;
    appearance:none; cursor:pointer; outline:none; min-width:130px;
}
.ios-action-select:focus { border-color:#F5A623; }
.ios-analyze-btn { background:#F5A623; color:#000; border:none; border-radius:8px; padding:8px 22px; font-family:'Rajdhani',sans-serif; font-size:14px; font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s; white-space:nowrap; }
.ios-analyze-btn:hover { background:#d4890e; }
.ios-reset-btn { background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px; padding:7px 16px; font-size:12px; font-weight:700; cursor:pointer; transition:.2s; font-family:'Exo 2',sans-serif; }
.ios-reset-btn:hover { border-color:#F5A623; color:#c97f00; }
.ios-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.ios-info-text { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.ios-upd-text  { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }
@media(max-width:768px){ .ios-filter-bar{ padding:0 12px; } .ios-filter-right{ margin-left:0;width:100%; } }

/* ── CONTENT ── */
.ios-content { padding:28px 48px 64px; }
@media(max-width:768px){ .ios-content{ padding:16px 12px 48px; } }

.ios-warn { background:#fff3e0; border:1px solid #ffcc80; border-radius:10px; padding:14px 20px; margin-bottom:20px; display:none; align-items:center; gap:12px; font-size:13px; color:#e65100; }
.ios-warn.show { display:flex; }

/* ── STATS ── */
.ios-stats { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:24px; }
@media(max-width:900px){ .ios-stats{ grid-template-columns:repeat(3,1fr); } }
@media(max-width:500px){ .ios-stats{ grid-template-columns:repeat(2,1fr); } }
.ios-stat-card { background:#fff; border-radius:12px; border:1px solid #e8e8e8; padding:14px 16px; border-left:3px solid #e8e8e8; }
.ios-stat-card.s-total { border-left-color:#0d9488; }
.ios-stat-card.s-ce    { border-left-color:#059669; }
.ios-stat-card.s-pe    { border-left-color:#dc2626; }
.ios-stat-card.s-wait  { border-left-color:#c97f00; }
.ios-stat-card.s-bull  { border-left-color:#059669; }
.ios-stat-card.s-bear  { border-left-color:#dc2626; }
.ios-stat-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#aab; margin-bottom:6px; }
.ios-stat-val { font-family:'JetBrains Mono',monospace; font-size:24px; font-weight:700; color:#1a1a2e; }
.s-total .ios-stat-val { color:#0d9488; }
.s-ce    .ios-stat-val { color:#047857; }
.s-pe    .ios-stat-val { color:#b91c1c; }
.s-wait  .ios-stat-val { color:#c97f00; }
.s-bull  .ios-stat-val { color:#047857; }
.s-bear  .ios-stat-val { color:#b91c1c; }

/* ── TABLE CARD ── */
.ios-card { background:#fff; border-radius:12px; border:1px solid #e8e8e8; overflow:hidden; }
.ios-card-header { padding:14px 20px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; background:#fafafa; }
.ios-card-title { font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:#1a1a2e; }
.ios-card-subtitle { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.ios-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

.ios-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:1000px; }
.ios-table thead tr.th-group th { padding:9px 10px 5px; text-align:center; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; background:#f7f8fc; border-bottom:none; white-space:nowrap; }
.ios-table thead tr.th-cols th  { padding:5px 10px 9px; text-align:center; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; background:#f4f6fb; color:#aab; border-bottom:2px solid #e8e8e8; white-space:nowrap; }
.g-info   { color:#0d9488 !important; }
.g-oi     { color:#c97f00 !important; }
.g-signal { color:#047857 !important; }
.sep-oi     { border-left:2px solid rgba(245,166,35,.2)  !important; }
.sep-signal { border-left:2px solid rgba(5,150,105,.2)   !important; }

.ios-table tbody td { padding:8px 10px; text-align:center; font-size:11px; border-bottom:1px solid #f5f5f5; vertical-align:middle; white-space:nowrap; color:#555; }
.ios-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }
.tr-bull { background:rgba(5,150,105,.03) !important; }
.tr-bear { background:rgba(220,38,38,.03) !important; }

.c-num  { font-size:9px; color:#ccc; }
.c-date { font-size:11px; font-weight:700; color:#F5A623; }
.c-sym  { font-size:12px; font-weight:800; color:#1a56db; }
.c-sym small { display:block; font-size:8px; color:#aab; font-weight:400; margin-top:1px; }
.c-atm  { font-size:10px; color:#c97f00; font-weight:700; }
.c-fut  { font-size:10px; color:#1a56db; }
.c-expiry { font-size:10px; color:#aab; }
.c-oi   { font-size:11px; font-weight:700; color:#1a1a2e; }
.c-oi small { display:block; font-size:8px; color:#aab; font-weight:400; margin-top:1px; }
.pct-up  { color:#059669; font-weight:700; }
.pct-dn  { color:#dc2626; font-weight:700; }
.pct-neu { color:#aab; }

.sig-bull { display:inline-block; background:rgba(5,150,105,.12); color:#047857; border:1px solid rgba(5,150,105,.35); border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-bear { display:inline-block; background:rgba(220,38,38,.1);  color:#b91c1c; border:1px solid rgba(220,38,38,.35);  border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-neut { display:inline-block; background:#f4f6fb; color:#aab; border:1px solid #e5e9f2; border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; }
.act-ce { display:inline-block; background:rgba(5,150,105,.1);  color:#047857; border:1px solid rgba(5,150,105,.3);  border-radius:5px; padding:2px 8px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.act-pe { display:inline-block; background:rgba(220,38,38,.08); color:#b91c1c; border:1px solid rgba(220,38,38,.25); border-radius:5px; padding:2px 8px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.act-wt { display:inline-block; background:rgba(245,166,35,.1); color:#c97f00; border:1px solid rgba(245,166,35,.3); border-radius:5px; padding:2px 8px; font-family:'Exo 2',sans-serif; font-size:10px; }

.cond-base  { display:inline-block; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
.cond-ce-pe { background:rgba(220,38,38,.1); color:#b91c1c; border:1px solid rgba(220,38,38,.25); }
.cond-pe-ce { background:rgba(5,150,105,.1); color:#047857; border:1px solid rgba(5,150,105,.25); }
.cond-both  { background:rgba(124,58,237,.1); color:#6d28d9; border:1px solid rgba(124,58,237,.25); }
.cond-flat  { background:#f4f6fb; color:#aab; border:1px solid #e5e9f2; }

.rank-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:9px; font-weight:700; }
.rank-1 { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
.rank-2 { background:#fff7ed; color:#c97f00; border:1px solid #fed7aa; }
.rank-3 { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.rank-4 { background:#f0fdf4; color:#047857; border:1px solid #bbf7d0; }
.rank-n { background:#f4f6fb; color:#aab; border:1px solid #e5e9f2; }
.rank-diff { font-size:8px; color:#aab; margin-top:1px; }
.reason-tip { font-size:9px; color:#aab; margin-top:3px; line-height:1.4; max-width:200px; white-space:normal; }

.ios-empty { text-align:center; padding:56px 20px; color:#ccc; }
.ios-empty i { font-size:2.5rem; display:block; margin-bottom:12px; color:#e5e9f2; }
.ios-empty p { font-size:13px; }
.ios-spinner-row { display:flex; align-items:center; justify-content:center; gap:12px; padding:48px; color:#aab; font-size:13px; }
.ios-spinner { width:28px; height:28px; border:3px solid #f0f0f0; border-top:3px solid #F5A623; border-radius:50%; animation:iosSpin 1s linear infinite; flex-shrink:0; }
</style>

<div class="ios-wrap">

{{-- HERO --}}
<div class="ios-hero ios-anim">
    <div class="ios-hero-left">
        <h1>Intraday OI <span>Snapshot</span></h1>
        <p>
            Compares CE/PE Open Interest at 09:15 (market open) vs 12:00 (midday snapshot)
            to detect intraday option writing momentum — bullish or bearish.
        </p>
        <div class="ios-hero-pills">
            <span class="ios-pill ios-pill-snap">09:15 Open → 12:00 Snapshot</span>
            <span class="ios-pill ios-pill-bull">CE↓ + PE↑ → BULLISH → BUY CE</span>
            <span class="ios-pill ios-pill-bear">CE↑ + PE↓ → BEARISH → BUY PE</span>
        </div>
    </div>
    <div class="ios-hero-icon"><i class="las la-camera"></i></div>
</div>

{{-- FILTER BAR --}}
<div class="ios-filter-bar">
    <div class="ios-filter-inner">
        <span class="ios-filter-label">From</span>
        <input type="date" id="ios-from" class="ios-date-input"
               value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">

        <span class="ios-filter-label">To</span>
        <input type="date" id="ios-to" class="ios-date-input"
               value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">

        <div class="ios-sep"></div>

        <span class="ios-filter-label">Symbol</span>
        <select id="ios-sym" class="ios-sym-select" multiple size="1">
            <option value="">Loading…</option>
        </select>

        <span class="ios-filter-label">Action</span>
        <select id="ios-action" class="ios-action-select">
            <option value="">All Actions</option>
            <option value="BUY CE">BUY CE Only</option>
            <option value="BUY PE">BUY PE Only</option>
            <option value="WAIT">WAIT Only</option>
        </select>

        <button class="ios-analyze-btn" onclick="iosAnalyze()">
            <i class="las la-camera"></i> Analyze
        </button>
        <button class="ios-reset-btn" onclick="iosReset()">↺ Reset</button>

        <div class="ios-filter-right">
            <span class="ios-info-text" id="ios-info"></span>
            <span class="ios-upd-text"  id="ios-upd"></span>
        </div>
    </div>
</div>

{{-- CONTENT --}}
<div class="ios-content">

    <div class="ios-warn" id="ios-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="ios-warn-msg">
                Go to Admin → Analysis Config and create a 15min config.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="ios-stats ios-anim">
        <div class="ios-stat-card s-total"><div class="ios-stat-label">Total</div><div class="ios-stat-val" id="st-total">—</div></div>
        <div class="ios-stat-card s-ce">  <div class="ios-stat-label">BUY CE</div><div class="ios-stat-val" id="st-ce">—</div></div>
        <div class="ios-stat-card s-pe">  <div class="ios-stat-label">BUY PE</div><div class="ios-stat-val" id="st-pe">—</div></div>
        <div class="ios-stat-card s-wait"><div class="ios-stat-label">WAIT</div>  <div class="ios-stat-val" id="st-wait">—</div></div>
        <div class="ios-stat-card s-bull"><div class="ios-stat-label">Bullish</div><div class="ios-stat-val" id="st-bull">—</div></div>
        <div class="ios-stat-card s-bear"><div class="ios-stat-label">Bearish</div><div class="ios-stat-val" id="st-bear">—</div></div>
    </div>

    {{-- Table --}}
    <div class="ios-card ios-anim">
        <div class="ios-card-header">
            <div class="ios-card-title">◆ Intraday OI Snapshot — 15min · 09:15 → 12:00</div>
            <span class="ios-card-subtitle" id="ios-subtitle">Select dates and click Analyze</span>
        </div>
        <div class="ios-tscroll">
            <table class="ios-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="5" class="g-info">Market Info</th>
                        <th colspan="4" class="g-oi sep-oi">CE / PE OI Change (09:15 → 12:00)</th>
                        <th colspan="4" class="g-signal sep-signal">Signal</th>
                    </tr>
                    <tr class="th-cols">
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>ATM / FUT</th>
                        <th>Expiry</th>
                        <th class="sep-oi">CE OI<br><span style="font-size:7px;font-weight:400;opacity:.6;">Snap / Open</span></th>
                        <th>CE Chg %</th>
                        <th>PE OI<br><span style="font-size:7px;font-weight:400;opacity:.6;">Snap / Open</span></th>
                        <th>PE Chg %</th>
                        <th class="sep-signal">Sentiment</th>
                        <th>Condition</th>
                        <th>Strength</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ios-tbody">
                    <tr><td colspan="13">
                        <div class="ios-empty">
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
//  Intraday OI Snapshot — JS (no jQuery)
// ═══════════════════════════════════════════════════════════

var IOS_ANALYZE = '{{ route("intraday-oi-snapshot.analyze") }}';
var IOS_SYMBOLS = '{{ route("intraday-oi-snapshot.symbols") }}';
var IOS_TODAY   = '{{ now()->toDateString() }}';
var iosSymCache = null;

function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

document.addEventListener('DOMContentLoaded', function() { iosLoadSymbols(); });

function iosLoadSymbols() {
    if (iosSymCache !== null) { iosRebuildSym(iosSymCache); return; }
    fetch(IOS_SYMBOLS, { headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (res.no_config) { iosShowWarn(res.message||''); iosRebuildSym([]); return; }
            iosHideWarn();
            iosSymCache = res.symbols || [];
            iosRebuildSym(iosSymCache);
        });
}

function iosRebuildSym(syms) {
    var sel  = el('ios-sym');
    var prev = Array.from(sel.selectedOptions||[]).map(function(o){ return o.value; });
    if (!syms.length) { sel.innerHTML='<option value="" disabled>No symbols</option>'; sel.size=1; return; }
    sel.innerHTML = syms.map(function(s){
        return '<option value="'+s+'"'+(prev.indexOf(s)>-1?' selected':'')+'>'+s+'</option>';
    }).join('');
    sel.size = Math.min(3, Math.max(1, syms.length));
}

function iosAnalyze() {
    var from   = el('ios-from').value;
    var to     = el('ios-to').value;
    var action = el('ios-action').value;
    var syms   = Array.from((el('ios-sym').selectedOptions)||[]).map(function(o){ return o.value; }).filter(Boolean);

    if (!from || !to) { alert('Please select both dates.'); return; }

    iosHideWarn();
    iosResetStats();

    html('ios-tbody', '<tr><td colspan="13"><div class="ios-spinner-row">'
        + '<div class="ios-spinner"></div>'
        + 'Comparing 09:15 → 12:00 OI for ' + from + ' → ' + to + '…'
        + '</div></td></tr>');
    txt('ios-subtitle', 'Loading…');

    var params = new URLSearchParams({ from_date:from, to_date:to, filter_action:action });
    syms.forEach(function(s){ params.append('symbols[]', s); });

    fetch(IOS_ANALYZE + '?' + params.toString(), { headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){ if(!r.ok) throw new Error('Server error '+r.status); return r.json(); })
        .then(function(res) {
            if (res.no_config) { iosShowWarn(res.message); iosEmptyTable('No active config.'); return; }
            if (!res.success || !res.data || !res.data.length) {
                iosEmptyTable(res.message || 'No signals found.');
                return;
            }
            iosUpdateStats(res);
            iosRenderTable(res.data);
            el('ios-info').innerHTML =
                '<span style="color:#047857;">CE: '+res.buy_ce_count+'</span>'
                +' &nbsp;·&nbsp; '
                +'<span style="color:#b91c1c;">PE: '+res.buy_pe_count+'</span>'
                +' &nbsp;·&nbsp; 15min · 09:15→12:00';
            txt('ios-subtitle', '15min · '+from+' → '+to+' · '+res.message);
            txt('ios-upd', 'Updated ' + new Date().toLocaleTimeString());
        })
        .catch(function(err){ iosEmptyTable('⚠ '+err.message); });
}

function iosRenderTable(data) {
    var h = '', num = 1;
    data.forEach(function(r, i) {
        var isBull = r.sentiment==='BULLISH', isBear = r.sentiment==='BEARISH';
        var rowCls = (isBull?'tr-bull':isBear?'tr-bear':'') + ' ' + (i%2===0?'tr-even':'tr-odd');
        var sentBadge = isBull ? '<span class="sig-bull">▲ BULLISH</span>'
                      : isBear ? '<span class="sig-bear">▼ BEARISH</span>'
                      : '<span class="sig-neut">— NEUTRAL</span>';
        var actBadge = r.trade_action==='BUY CE' ? '<span class="act-ce">📈 BUY CE</span>'
                     : r.trade_action==='BUY PE' ? '<span class="act-pe">📉 BUY PE</span>'
                     : '<span class="act-wt">⏸ WAIT</span>';
        var cond = r.condition || '';
        var condCls = 'cond-base cond-flat';
        if (cond.includes('CE ↑')&&cond.includes('PE ↓')) condCls='cond-base cond-ce-pe';
        else if (cond.includes('CE ↓')&&cond.includes('PE ↑')) condCls='cond-base cond-pe-ce';
        else if (cond.includes('Both')) condCls='cond-base cond-both';
        var rankCls = {'Rank 1':'rank-badge rank-1','Rank 2':'rank-badge rank-2','Rank 3':'rank-badge rank-3','Rank 4':'rank-badge rank-4','Normal':'rank-badge rank-n'}[r.strength_rank]||'rank-badge rank-n';

        h += '<tr class="'+rowCls+'">'
            +'<td class="c-num">'+num+'</td>'
            +'<td class="c-date">'+r.date+'</td>'
            +'<td class="c-sym">'+esc(r.symbol)+'</td>'
            +'<td>'+(r.atm_strike?'<span class="c-atm">₹'+nInt(r.atm_strike)+'</span>':'—')
                   +(r.fut_price?'<br><span class="c-fut">F:₹'+f(r.fut_price)+'</span>':'')+'</td>'
            +'<td class="c-expiry">'+(r.expiry||'—')+'</td>'
            +'<td class="sep-oi c-oi">'+nInt(r.ce_oi)+'<small>open: '+nInt(r.ce_oi_prev)+'</small></td>'
            +'<td>'+pctCell(r.ce_oi_pct)+'</td>'
            +'<td class="c-oi">'+nInt(r.pe_oi)+'<small>open: '+nInt(r.pe_oi_prev)+'</small></td>'
            +'<td>'+pctCell(r.pe_oi_pct)+'</td>'
            +'<td class="sep-signal">'+sentBadge+'</td>'
            +'<td><span class="'+condCls+'">'+esc(cond)+'</span>'
                +(r.reason?'<div class="reason-tip">'+esc(r.reason)+'</div>':'')+'</td>'
            +'<td><span class="'+rankCls+'">'+r.strength_rank+'</span><div class="rank-diff">Δ '+r.oi_diff+'%</div></td>'
            +'<td>'+actBadge+'</td>'
            +'</tr>';
        num++;
    });
    html('ios-tbody', h || iosEmptyHtml('No results.'));
}

function iosUpdateStats(res) {
    txt('st-total', res.total_records||'0');
    txt('st-ce',   res.buy_ce_count||'0');
    txt('st-pe',   res.buy_pe_count||'0');
    txt('st-wait', res.wait_count||'0');
    txt('st-bull', res.bullish_count||'0');
    txt('st-bear', res.bearish_count||'0');
}
function iosResetStats() {
    ['st-total','st-ce','st-pe','st-wait','st-bull','st-bear'].forEach(function(id){ txt(id,'—'); });
}
function iosShowWarn(msg) { el('ios-warn').classList.add('show'); txt('ios-warn-msg', msg||''); }
function iosHideWarn()    { el('ios-warn').classList.remove('show'); }
function iosEmptyTable(msg){ html('ios-tbody', iosEmptyHtml(msg)); }
function iosEmptyHtml(msg) {
    return '<tr><td colspan="13"><div class="ios-empty"><i class="las la-chart-bar"></i><p>'+(msg||'No data found.')+'</p></div></td></tr>';
}
function iosReset() {
    el('ios-from').value = IOS_TODAY;
    el('ios-to').value   = IOS_TODAY;
    el('ios-action').value = '';
    Array.from(el('ios-sym').options).forEach(function(o){ o.selected=false; });
    iosResetStats();
    iosEmptyTable('Reset — select dates and click Analyze.');
    txt('ios-info',''); txt('ios-upd','');
    txt('ios-subtitle','Select dates and click Analyze');
    iosHideWarn();
}
function pctCell(v) {
    if (v==null) return '<span class="pct-neu">—</span>';
    var n=parseFloat(v)||0, cls=n>0?'pct-up':n<0?'pct-dn':'pct-neu';
    return '<span class="'+cls+'">'+(n>0?'+':'')+n.toFixed(2)+'%</span>';
}
function f(v)   { return parseFloat(v||0).toFixed(2); }
function nInt(v){ var n=Number(v)||0; if(n>=1e7)return(n/1e7).toFixed(2)+'Cr'; if(n>=1e5)return(n/1e5).toFixed(2)+'L'; if(n>=1e3)return(n/1e3).toFixed(1)+'K'; return n.toLocaleString('en-IN'); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
@endpush