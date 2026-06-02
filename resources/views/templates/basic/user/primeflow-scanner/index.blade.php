{{-- FILE: resources/views/themes/{active_theme}/user/primeflow-scanner/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* ── BASE ── */
.pf-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.pf-wrap * { box-sizing:border-box; }
.pf-wrap h1,.pf-wrap h2,.pf-wrap h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes pfFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.pf-anim { animation:pfFadeUp .5s ease both; }
@keyframes pfSpin { to{ transform:rotate(360deg); } }

/* ── HERO ── */
.pf-hero {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:32px 48px; display:flex; align-items:center;
    justify-content:space-between; gap:24px;
}
.pf-hero-left h1 {
    font-size:clamp(24px,3.5vw,40px); font-weight:700;
    color:#1a1a2e; margin:0 0 8px; line-height:1.1;
}
.pf-hero-left h1 span { color:#7DFF00; }
.pf-hero-left p { font-size:13px; color:#666; margin:0; line-height:1.7; max-width:580px; }
.pf-hero-pills { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
.pf-pill {
    display:inline-block; padding:3px 10px; border-radius:4px;
    font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700;
}
.pf-pill-call   { background:rgba(4,120,87,.1);   color:#047857; border:1px solid rgba(4,120,87,.3);   }
.pf-pill-put    { background:rgba(185,28,28,.08);  color:#b91c1c; border:1px solid rgba(185,28,28,.25); }
.pf-pill-trap   { background:rgba(109,40,217,.1);  color:#6d28d9; border:1px solid rgba(109,40,217,.3); }
.pf-pill-score  { background:rgba(245,166,35,.12); color:#c97f00; border:1px solid rgba(245,166,35,.3); }
.pf-hero-icon {
    width:80px; height:80px; border-radius:16px;
    background:linear-gradient(135deg,#0f1b2d,#1a3050);
    display:flex; align-items:center; justify-content:center;
    font-size:32px; color:#7DFF00; flex-shrink:0; font-family:'Rajdhani',sans-serif;
    font-weight:900; letter-spacing:-1px;
}
@media(max-width:768px){
    .pf-hero { flex-direction:column; padding:24px 16px; text-align:center; }
    .pf-hero-pills { justify-content:center; }
}

/* ── FILTER BAR ── */
.pf-filter-bar {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:0 48px; position:sticky; top:0; z-index:200;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.pf-filter-inner {
    display:flex; align-items:center; gap:14px;
    padding:13px 0; flex-wrap:wrap;
}
.pf-filter-label {
    font-size:10.5px; color:#999; font-weight:700;
    text-transform:uppercase; letter-spacing:.07em;
}
.pf-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

/* Date controls */
.pf-date-wrap { display:flex; align-items:center; gap:4px; }
.pf-date-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
    color:#333; outline:none; cursor:pointer;
}
.pf-date-input:focus { border-color:#7DFF00; }
.pf-date-nav {
    width:28px; height:32px; border:1.5px solid #e5e9f2; border-radius:6px;
    background:#fff; color:#888; cursor:pointer; font-weight:700; font-size:14px;
    display:flex; align-items:center; justify-content:center; transition:.2s;
}
.pf-date-nav:hover { border-color:#7DFF00; color:#7DFF00; }
.pf-today-btn { width:auto; padding:0 10px; font-size:10px; font-family:'Exo 2',sans-serif; font-weight:700; letter-spacing:.07em; }

/* Badges */
.pf-live-badge { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.pf-hist-badge { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }

/* Buttons */
.pf-scan-btn {
    background:#7DFF00; color:#000; border:none; border-radius:8px;
    padding:8px 22px; font-family:'Rajdhani',sans-serif; font-size:13px;
    font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s;
}
.pf-scan-btn:hover { background:#d4890e; }
.pf-auto-btn {
    background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px;
    padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer;
    font-family:'Exo 2',sans-serif; transition:.2s;
}
.pf-auto-btn.on { border-color:#059669; background:rgba(5,150,105,.08); color:#047857; }

/* Filter pills */
.pf-pills-wrap { display:flex; gap:4px; flex-wrap:wrap; }
.pf-fp {
    padding:5px 13px; border-radius:20px; font-family:'Exo 2',sans-serif;
    font-size:11px; font-weight:700; cursor:pointer; border:1.5px solid #e5e9f2;
    background:#fff; color:#888; transition:.15s;
}
.pf-fp:hover           { border-color:#7DFF00; color:#c97f00; }
.pf-fp.active          { background:rgba(245,166,35,.1);  border-color:#7DFF00; color:#c97f00; }
.pf-fp.active-call     { background:rgba(4,120,87,.1);    border-color:#059669; color:#047857; }
.pf-fp.active-put      { background:rgba(185,28,28,.08);  border-color:#b91c1c; color:#b91c1c; }

.pf-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.pf-last-upd     { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }

@media(max-width:768px){
    .pf-filter-bar { padding:0 16px; }
    .pf-filter-inner { gap:8px; }
    .pf-filter-right { margin-left:0; width:100%; }
}

/* ── CONTENT ── */
.pf-content { padding:28px 48px 64px; }
@media(max-width:768px){ .pf-content { padding:16px 12px 48px; } }

/* Config warning */
.pf-warn {
    background:#fff3e0; border:1px solid #ffcc80; border-radius:10px;
    padding:16px 20px; margin-bottom:20px; font-size:13px; color:#e65100;
    align-items:center; gap:14px; display:none;
}
.pf-warn.show { display:flex; }
.pf-warn i { font-size:20px; flex-shrink:0; }

/* ── STATS ── */
.pf-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; }
.pf-stat {
    background:#fff; border:1px solid #e8e8e8; border-radius:12px;
    padding:14px 18px; min-width:110px; flex:1;
}
.pf-stat small {
    display:block; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700;
    text-transform:uppercase; letter-spacing:1px; color:#bbb; margin-bottom:5px;
}
.pf-stat strong { display:block; font-family:'JetBrains Mono',monospace; font-size:1.25rem; font-weight:700; }
.ps-total { border-left:3px solid rgba(56,189,248,.6); }
.ps-call  { border-left:3px solid #059669; }
.ps-put   { border-left:3px solid #b91c1c; }
.ps-trap  { border-left:3px solid #7c3aed; }
.ps-wait  { border-left:3px solid #7DFF00; }

/* ── CARD ── */
.pf-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; overflow:hidden; }
.pf-card-hdr {
    padding:14px 20px; border-bottom:1px solid #f0f0f0;
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:8px; background:#fafafa;
}
.pf-card-title { font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:#1a1a2e; }
.pf-card-info  { font-size:11px; color:#bbb; font-family:'JetBrains Mono',monospace; }

/* ── TABLE ── */
.pf-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.pf-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:980px; }

.pf-table thead tr.th-group th {
    padding:9px 10px 5px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800;
    letter-spacing:.1em; text-transform:uppercase;
    background:#f7f8fc; border-bottom:none; white-space:nowrap;
}
.pf-table thead tr.th-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:8.5px; font-weight:700;
    letter-spacing:.03em; text-transform:uppercase;
    background:#f4f6fb; color:#bbb;
    border-bottom:2px solid #e8e8e8; white-space:nowrap;
}

.g-info   { color:#888 !important; }
.g-trade  { color:#c97f00 !important; }
.g-entry  { color:#1a56db !important; }
.g-signal { color:#047857 !important; }

.sep-trade  { border-left:2px solid rgba(245,166,35,.25) !important; }
.sep-entry  { border-left:2px solid rgba(26,86,219,.15)  !important; }
.sep-signal { border-left:2px solid rgba(4,120,87,.2)    !important; }

.pf-table tbody td {
    padding:9px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid #f5f5f5; vertical-align:middle;
    white-space:nowrap; color:#555;
}
.pf-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }
.tr-call { background:rgba(4,120,87,.03)  !important; border-left:2px solid #059669 !important; }
.tr-put  { background:rgba(185,28,28,.03) !important; border-left:2px solid #b91c1c !important; }
.tr-wait { opacity:.7; }

/* Cell styles */
.c-num   { font-size:9px; color:#ccc; }
.c-sym   { display:inline-block; padding:3px 9px; border-radius:5px; font-size:11px; font-weight:800; background:#f4f6fb; color:#1a1a2e; border:1px solid #e8e8e8; }

/* Signal badges */
.sig-call { display:inline-block; background:rgba(4,120,87,.12); color:#047857; border:1px solid rgba(4,120,87,.35); border-radius:6px; padding:4px 11px; font-family:'Exo 2',sans-serif; font-size:11px; font-weight:800; }
.sig-put  { display:inline-block; background:rgba(185,28,28,.1);  color:#b91c1c; border:1px solid rgba(185,28,28,.3);  border-radius:6px; padding:4px 11px; font-family:'Exo 2',sans-serif; font-size:11px; font-weight:800; }
.sig-wait { display:inline-block; background:#f7f8fc; color:#bbb; border:1px solid #e8e8e8; border-radius:6px; padding:4px 10px; font-family:'Exo 2',sans-serif; font-size:10px; }
.sig-nd   { color:#ddd; font-size:10px; font-family:'Exo 2',sans-serif; }

/* Score bar */
.score-wrap  { display:flex; align-items:center; gap:6px; justify-content:center; }
.score-num   { font-size:12px; font-weight:800; min-width:18px; }
.score-track { width:48px; height:4px; background:#f0f0f0; border-radius:2px; overflow:hidden; }
.score-fill  { height:100%; border-radius:2px; }

/* Futures direction */
.fd-bull { color:#047857; font-size:11px; font-weight:800; }
.fd-bear { color:#b91c1c; font-size:11px; font-weight:800; }
.fd-side { color:#bbb; font-size:10px; }

/* Signal dots */
.sig-dots { display:flex; align-items:center; gap:3px; justify-content:center; flex-wrap:wrap; }
.sd       { width:8px; height:8px; border-radius:50%; display:inline-block; }
.sd-call  { background:#059669; box-shadow:0 0 4px rgba(5,150,105,.5); }
.sd-put   { background:#b91c1c; box-shadow:0 0 4px rgba(185,28,28,.5); }
.sd-trap  { background:#7c3aed; box-shadow:0 0 4px rgba(124,58,237,.5); }
.sd-off   { background:#e8e8e8; }

/* Entry price cells */
.c-entry  { color:#1a56db; font-weight:700; }
.c-target { color:#047857; font-weight:700; }
.c-sl     { color:#b91c1c; font-weight:700; }
.c-pcr    { font-size:10px; color:#888; }
.c-strike { color:#c97f00; font-weight:700; }
.c-time   { color:#7DFF00; font-weight:700; }
.c-strsym { display:block; font-size:8px; color:#bbb; margin-top:1px; }

/* Loading / empty */
.pf-loading {
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; padding:64px 20px;
}
.pf-spinner {
    width:36px; height:36px; border:3px solid #f0f0f0;
    border-top:3px solid #7DFF00; border-radius:50%;
    animation:pfSpin 1s linear infinite;
}
.pf-loading-txt { color:#bbb; margin-top:12px; font-family:'Exo 2',sans-serif; font-size:13px; }
.pf-empty { text-align:center; padding:60px 20px; color:#ccc; font-family:'Exo 2',sans-serif; font-size:13px; }
.pf-empty i { font-size:2.5rem; display:block; margin-bottom:12px; }
</style>

<div class="pf-wrap">

{{-- ══ HERO ══ --}}
<div class="pf-hero pf-anim">
    <div class="pf-hero-left">
        <h1>PrimeFlow <span>Option Scanner</span></h1>
        <p>
            Smart Entry Engine across all configured symbols — 7-signal confluence model
            running on live 15min option &amp; futures candle data. Entry window: 10:30–14:30.
        </p>
        <div class="pf-hero-pills">
            <span class="pf-pill pf-pill-score">Threshold: {{ $thresh_hold ?? 6 }}/17</span>
            <span class="pf-pill pf-pill-call">Prem Exp +3</span>
            <span class="pf-pill pf-pill-call">OI Build +2</span>
            <span class="pf-pill pf-pill-call">Vol Spike +2</span>
            <span class="pf-pill pf-pill-put">Futures Dir +2</span>
            <span class="pf-pill pf-pill-put">Gamma +2</span>
            <span class="pf-pill pf-pill-put">Momentum +2</span>
            <span class="pf-pill pf-pill-trap">MM Trap +4</span>
        </div>
    </div>
    <div class="pf-hero-icon">PF</div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="pf-filter-bar">
    <div class="pf-filter-inner">

        <span class="pf-filter-label">Date</span>
        <div class="pf-date-wrap">
            <button class="pf-date-nav" onclick="pfShiftDate(-1)">&#8249;</button>
            <input type="date" id="pf-date" class="pf-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="pfScan()">
            <button class="pf-date-nav" onclick="pfShiftDate(1)">&#8250;</button>
            <button class="pf-date-nav pf-today-btn" onclick="pfToday()">TODAY</button>
            <span id="pf-date-badge"></span>
        </div>

        <button class="pf-scan-btn" onclick="pfScan()">&#9670; Scan All</button>
        <button class="pf-auto-btn" id="pf-auto-btn" onclick="pfToggleAuto()">&#9654; Auto 60s</button>

        <div class="pf-sep"></div>

        <span class="pf-filter-label">Filter</span>
        <div class="pf-pills-wrap" id="pf-filter-pills">
            <div class="pf-fp active"      data-f="ALL"     onclick="pfSetFilter('ALL',this)">All</div>
            <div class="pf-fp"             data-f="CALL"    onclick="pfSetFilter('CALL',this)">&#8679; Call</div>
            <div class="pf-fp"             data-f="PUT"     onclick="pfSetFilter('PUT',this)">&#8681; Put</div>
            <div class="pf-fp"             data-f="TRADE"   onclick="pfSetFilter('TRADE',this)">&#128293; Trades</div>
            <div class="pf-fp"             data-f="NOTRADE" onclick="pfSetFilter('NOTRADE',this)">No Trade</div>
        </div>

        <div class="pf-filter-right">
            <span class="pf-last-upd" id="pf-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="pf-content">

    {{-- Config warning --}}
    <div class="pf-warn" id="pf-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="pf-warn-msg">
                Go to Admin → Analysis Config and create a 15min config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="pf-stats" id="pf-stats" style="display:none;">
        <div class="pf-stat ps-total"><small>Total</small><strong id="st-total" style="color:rgba(56,189,248,.9);">0</strong></div>
        <div class="pf-stat ps-call"><small>&#8679; Buy Call</small><strong id="st-call" style="color:#047857;">0</strong></div>
        <div class="pf-stat ps-put"><small>&#8681; Buy Put</small><strong id="st-put" style="color:#b91c1c;">0</strong></div>
        <div class="pf-stat ps-trap"><small>&#128375; MM Traps</small><strong id="st-trap" style="color:#7c3aed;">0</strong></div>
        <div class="pf-stat ps-wait"><small>No Trade</small><strong id="st-wait" style="color:#c97f00;">0</strong></div>
    </div>

    {{-- Table card --}}
    <div class="pf-card">
        <div class="pf-card-hdr">
            <span class="pf-card-title">&#9670; PrimeFlow Scanner &nbsp;·&nbsp; 15 Min</span>
            <span class="pf-card-info" id="pf-card-info"></span>
        </div>
        <div class="pf-tscroll">
            <table class="pf-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="3" class="g-info">Info</th>
                        <th colspan="2" class="g-trade  sep-trade">&#128200; Trade</th>
                        <th colspan="4" class="g-entry  sep-entry">Entry Details</th>
                        <th colspan="3" class="g-signal sep-signal">&#9889; Signals</th>
                    </tr>
                    <tr class="th-cols">
                        <th class="g-info">#</th>
                        <th class="g-info" style="text-align:left;padding-left:14px;">Symbol</th>
                        <th class="g-info">Futures Dir</th>
                        <th class="g-trade  sep-trade">Signal</th>
                        <th class="g-trade">Entry Time</th>
                        <th class="g-entry  sep-entry">Strike</th>
                        <th class="g-entry">Entry &#8377;</th>
                        <th class="g-entry">Target &#8377;</th>
                        <th class="g-entry">SL &#8377;</th>
                        <th class="g-signal sep-signal">Score /17</th>
                        <th class="g-signal">Active Signals</th>
                        <th class="g-signal">PCR</th>
                    </tr>
                </thead>
                <tbody id="pf-tbody">
                    <tr><td colspan="12">
                        <div class="pf-empty"><i class="las la-bolt"></i>Select date and click Scan All</div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.pf-content --}}
</div>{{-- /.pf-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  PRIMEFLOW SCANNER — Vanilla JS (no jQuery)
// ═══════════════════════════════════════════════════════════════

var PF_SCAN_URL = '{{ route("primeflow-scanner.data") }}';
var PF_TODAY    = '{{ now()->toDateString() }}';

var pfFilter    = 'ALL';
var pfAutoTimer = null;
var pfResults   = [];

// ── DOM helpers ───────────────────────────────────────────────
function pfHtml(id, h) { var e = document.getElementById(id); if (e) e.innerHTML = h; }
function pfText(id, t) { var e = document.getElementById(id); if (e) e.textContent = t; }

document.addEventListener('DOMContentLoaded', function () {
    pfUpdateDateBadge();
    pfScan();
});

// ── Date helpers ──────────────────────────────────────────────
function pfShiftDate(d) {
    var picker = document.getElementById('pf-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > PF_TODAY) return;
    picker.value = s;
    pfUpdateDateBadge();
    pfScan();
}

function pfToday() {
    document.getElementById('pf-date').value = PF_TODAY;
    pfUpdateDateBadge();
    pfScan();
}

function pfUpdateDateBadge() {
    var d  = document.getElementById('pf-date').value;
    var el = document.getElementById('pf-date-badge');
    if (!el) return;
    el.innerHTML = d === PF_TODAY
        ? '<span class="pf-live-badge">&#11044; Live</span>'
        : '<span class="pf-hist-badge">&#128197; Historical</span>';
}

// ── Auto refresh ──────────────────────────────────────────────
function pfToggleAuto() {
    var btn = document.getElementById('pf-auto-btn');
    if (pfAutoTimer) {
        clearInterval(pfAutoTimer); pfAutoTimer = null;
        btn.textContent = '▶ Auto 60s';
        btn.classList.remove('on');
    } else {
        if (document.getElementById('pf-date').value !== PF_TODAY) return;
        pfAutoTimer = setInterval(pfScan, 60000);
        btn.textContent = '■ Stop';
        btn.classList.add('on');
        pfScan();
    }
}

// ── Filter ────────────────────────────────────────────────────
function pfSetFilter(f, btn) {
    pfFilter = f;
    document.querySelectorAll('#pf-filter-pills .pf-fp').forEach(function (b) {
        b.classList.remove('active', 'active-call', 'active-put');
    });
    btn.classList.add(f === 'CALL' ? 'active-call' : f === 'PUT' ? 'active-put' : 'active');
    pfApplyFilter();
}

function pfApplyFilter() {
    document.querySelectorAll('#pf-tbody tr[data-sig]').forEach(function (row) {
        var sig  = row.dataset.sig;
        var show = pfFilter === 'ALL'
            || (pfFilter === 'CALL'    && sig === 'BUY_CALL')
            || (pfFilter === 'PUT'     && sig === 'BUY_PUT')
            || (pfFilter === 'TRADE'   && (sig === 'BUY_CALL' || sig === 'BUY_PUT'))
            || (pfFilter === 'NOTRADE' && sig === 'NO TRADE');
        row.style.display = show ? '' : 'none';
    });
}

// ── Main scan ─────────────────────────────────────────────────
function pfScan() {
    var date = document.getElementById('pf-date').value;

    if (pfAutoTimer && date !== PF_TODAY) {
        clearInterval(pfAutoTimer); pfAutoTimer = null;
        document.getElementById('pf-auto-btn').textContent = '▶ Auto 60s';
        document.getElementById('pf-auto-btn').classList.remove('on');
    }

    pfUpdateDateBadge();
    pfShowLoading();

    var params = new URLSearchParams({ date: date });

    fetch(PF_SCAN_URL + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (res.no_config) {
            pfShowWarn(res.message);
            pfEmptyTable('');
            return;
        }
        if (!res.success) {
            pfHideWarn();
            pfEmptyTable(res.message || 'No data available.');
            return;
        }

        pfHideWarn();
        pfResults = res.results || [];
        pfRenderStats(res);
        pfRenderTable(pfResults);
        pfApplyFilter();

        pfText('pf-card-info', res.total_symbols + ' symbols · scanned at ' + res.scanned_at);
        pfText('pf-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) {
        pfHideWarn();
        pfEmptyTable('&#9888; ' + err.message);
    });
}

// ── Render stats ──────────────────────────────────────────────
function pfRenderStats(res) {
    var R     = res.results || [];
    var calls = R.filter(function (r) { return r.signal === 'BUY_CALL'; }).length;
    var puts  = R.filter(function (r) { return r.signal === 'BUY_PUT';  }).length;
    var traps = R.filter(function (r) {
        return r.signals && r.signals.mmTrap &&
               (r.signals.mmTrap.call_trap || r.signals.mmTrap.put_trap);
    }).length;
    var waits = R.filter(function (r) { return r.signal === 'NO TRADE'; }).length;

    pfText('st-total', res.total_symbols || 0);
    pfText('st-call',  calls);
    pfText('st-put',   puts);
    pfText('st-trap',  traps);
    pfText('st-wait',  waits);

    document.getElementById('pf-stats').style.display = '';
}

// ── Render table ──────────────────────────────────────────────
function pfRenderTable(rows) {
    if (!rows || !rows.length) { pfEmptyTable('No data.'); return; }

    var html = '';

    rows.forEach(function (r, i) {
        var sig     = r.signal || 'NO TRADE';
        var isCall  = sig === 'BUY_CALL';
        var isPut   = sig === 'BUY_PUT';
        var isFired = isCall || isPut;
        var rowCls  = isFired ? (isCall ? 'tr-call' : 'tr-put') : 'tr-wait';
        var zebra   = i % 2 === 0 ? 'tr-even' : 'tr-odd';

        var sigBadge = isCall
            ? '<span class="sig-call">&#8679; BUY CALL</span>'
            : isPut
            ? '<span class="sig-put">&#8681; BUY PUT</span>'
            : (sig === 'NO DATA' || sig === 'ERROR')
            ? '<span class="sig-nd">&#8212; ' + pfEsc(sig) + '</span>'
            : '<span class="sig-wait">WAIT</span>';

        var fd = r.futures_dir || (r.signals && r.signals.futuresDir ? r.signals.futuresDir.direction : null);
        var futHtml = fd === 'BULLISH'
            ? '<span class="fd-bull">&#9650; BULL</span>'
            : fd === 'BEARISH'
            ? '<span class="fd-bear">&#9660; BEAR</span>'
            : '<span class="fd-side">&#9135; SIDE</span>';

        var timeHtml = isFired && r.entry_time
            ? '<span class="c-time">' + pfEsc(r.entry_time) + '</span>'
            : pfDash();

        var strikeHtml = isFired && r.strike
            ? '<span class="c-strike">' + pfFmt(r.strike) + '</span>'
            //   + (r.strike_sym ? '<span class="c-strsym">' + pfEsc(r.strike_sym) + '</span>' : '')
            : pfDash();

        var entryHtml  = isFired && r.entry_price ? '<span class="c-entry">&#8377;'  + r.entry_price  + '</span>' : pfDash();
        var targetHtml = isFired && r.target      ? '<span class="c-target">&#8377;' + r.target       + '</span>' : pfDash();
        var slHtml     = isFired && r.stoploss    ? '<span class="c-sl">&#8377;'     + r.stoploss     + '</span>' : pfDash();

        var score    = isFired ? (r.score || 0) : (r.peak_score || 0);
        var scorePct = Math.round((score / 17) * 100);
        var scoreCol = isCall ? '#059669' : isPut ? '#b91c1c' : '#ddd';
        var scoreHtml = '<div class="score-wrap">'
            + '<span class="score-num" style="color:' + scoreCol + '">' + score + '</span>'
            + '<div class="score-track"><div class="score-fill" style="width:' + scorePct + '%;background:' + scoreCol + ';"></div></div>'
            + '</div>';

        var dotsHtml = pfBuildDots(r.signals || {}, isCall, isPut);
        var pcrHtml  = r.pcr != null
            ? '<span class="c-pcr">' + r.pcr + '</span>'
            : pfDash();

        html += '<tr class="' + rowCls + ' ' + zebra + '" data-sig="' + pfEsc(sig) + '">'
            + '<td class="c-num">' + (i + 1) + '</td>'
            + '<td style="text-align:left;padding-left:14px;"><span class="c-sym">' + pfEsc(r.symbol) + '</span></td>'
            + '<td>' + futHtml + '</td>'
            + '<td class="sep-trade">' + sigBadge + '</td>'
            + '<td>' + timeHtml + '</td>'
            + '<td class="sep-entry">' + strikeHtml + '</td>'
            + '<td>' + entryHtml + '</td>'
            + '<td>' + targetHtml + '</td>'
            + '<td>' + slHtml + '</td>'
            + '<td class="sep-signal">' + scoreHtml + '</td>'
            + '<td>' + dotsHtml + '</td>'
            + '<td>' + pcrHtml + '</td>'
            + '</tr>';
    });

    pfHtml('pf-tbody', html);
}

// ── Signal dots ───────────────────────────────────────────────
function pfBuildDots(s, isCall, isPut) {
    if (!s || !Object.keys(s).length) return pfDash();

    var checks = [
        { key: isCall ? 'cePremEx'   : 'pePremEx',   type: 'std'     },
        { key: isCall ? 'ceOiBuild'  : 'peOiBuild',  type: 'std'     },
        { key: isCall ? 'ceVolSpike' : 'peVolSpike', type: 'std'     },
        { key: 'futuresDir',                          type: 'futures' },
        { key: 'gamma',                               type: 'gamma'   },
        { key: isCall ? 'ceAccel' : 'peAccel',        type: 'std'     },
        { key: 'mmTrap',                              type: 'trap'    },
    ];

    var dotColor = isCall ? 'sd-call' : isPut ? 'sd-put' : 'sd-call';

    var inner = checks.map(function (c) {
        var on = false;
        if (c.type === 'trap')    on = !!(s.mmTrap && (s.mmTrap.call_trap || s.mmTrap.put_trap));
        else if (c.type === 'futures') on = !!(s.futuresDir && (s.futuresDir.bullish || s.futuresDir.bearish));
        else if (c.type === 'gamma')   on = !!(s.gamma && s.gamma.active);
        else on = !!(s[c.key] && s[c.key].triggered);

        var cls = on ? (c.type === 'trap' ? 'sd sd-trap' : 'sd ' + dotColor) : 'sd sd-off';
        return '<span class="' + cls + '"></span>';
    }).join('');

    return '<div class="sig-dots">' + inner + '</div>';
}

// ── Helpers ───────────────────────────────────────────────────
function pfFmt(v) {
    if (v == null || v === '') return '—';
    return Number(v).toLocaleString('en-IN');
}
function pfDash() { return '<span style="color:#ddd;font-size:9px;">—</span>'; }
function pfEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function pfShowLoading() {
    pfHtml('pf-tbody',
        '<tr><td colspan="12">'
        + '<div class="pf-loading"><div class="pf-spinner"></div>'
        + '<div class="pf-loading-txt">Scanning all symbols…</div></div>'
        + '</td></tr>');
    document.getElementById('pf-stats').style.display = 'none';
}

function pfEmptyTable(msg) {
    pfHtml('pf-tbody',
        '<tr><td colspan="12">'
        + '<div class="pf-empty"><i class="las la-bolt"></i>'
        + (msg || 'Select date and click Scan All')
        + '</div></td></tr>');
    document.getElementById('pf-stats').style.display = 'none';
}

function pfShowWarn(msg) {
    var el = document.getElementById('pf-warn');
    if (el) el.classList.add('show');
    pfText('pf-warn-msg', msg || '');
}

function pfHideWarn() {
    var el = document.getElementById('pf-warn');
    if (el) el.classList.remove('show');
}
</script>
@endpush