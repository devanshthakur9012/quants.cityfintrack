@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════════
   VOLUME SPIKE 15MIN
   Dark terminal — matches screenshot layout
═══════════════════════════════════════════════════════ */
.page-header {
    background: linear-gradient(135deg, #0f0f1a 0%, #1a0a2e 50%, #0f1a1a 100%);
    border: 1px solid rgba(255,107,0,.3);
    border-radius: 12px; padding: 16px 24px; margin-bottom: 16px;
    box-shadow: 0 4px 30px rgba(255,107,0,.12);
    position: relative; overflow: hidden;
}
.page-header::before {
    content:''; position:absolute; top:0; left:0; right:0; height:2px;
    background: linear-gradient(90deg, transparent, #ff6b00, #ff9f00, #ff6b00, transparent);
}
.page-header h4 { color:#ff9f00; margin:0; font-size:17px; font-weight:800; }
.page-header p  { color:rgba(255,255,255,.5); margin:4px 0 0; font-size:11px; }

/* filter bar */
.filter-bar {
    background:#0d0d1f; border:1px solid rgba(255,107,0,.18);
    padding:10px 18px; border-radius:10px; margin-bottom:14px;
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    box-shadow:0 2px 16px rgba(0,0,0,.4);
}
.filter-bar label { color:rgba(255,255,255,.4); font-size:10px; font-weight:800; letter-spacing:1px; margin:0; }
.sym-select {
    background:rgba(255,107,0,.08); border:1px solid rgba(255,107,0,.3);
    color:#ff9f00; border-radius:8px; padding:6px 12px; font-size:12px;
    font-weight:700; cursor:pointer; outline:none; min-width:180px;
}
.sym-select option { background:#0d0d1f; color:#ff9f00; }
.date-wrap { display:flex; align-items:center; gap:5px; }
.date-wrap input[type="date"] {
    background:rgba(255,107,0,.08); border:1px solid rgba(255,107,0,.25);
    border-radius:8px; color:#ff9f00; padding:5px 10px; font-size:12px; font-weight:600; outline:none;
}
.date-wrap input[type="date"]::-webkit-calendar-picker-indicator { filter:invert(.7) sepia(1) saturate(5) hue-rotate(-15deg); cursor:pointer; }
.nav-btn {
    background:rgba(255,107,0,.1); border:1px solid rgba(255,107,0,.25); color:#ff9f00;
    border-radius:6px; width:26px; height:26px; display:flex; align-items:center; justify-content:center;
    cursor:pointer; font-size:14px; font-weight:700;
}
.nav-btn:hover { background:rgba(255,107,0,.22); }
.nav-btn.w-auto { width:auto; padding:0 9px; font-size:10px; }
.btn-load {
    background:linear-gradient(135deg,#ff6b00,#ff9f00); color:#000;
    border:none; border-radius:8px; padding:6px 20px; font-weight:900; font-size:12px; cursor:pointer;
}
.auto-btn {
    background:rgba(255,255,255,.05); color:rgba(255,255,255,.5);
    border:1px solid rgba(255,255,255,.1); border-radius:8px;
    padding:5px 13px; font-size:11px; font-weight:700; cursor:pointer;
}
.auto-btn.on { background:rgba(0,230,118,.1); color:#00e676; border-color:rgba(0,230,118,.3); }
.badge-today { background:rgba(0,230,118,.15); color:#00e676; border:1px solid rgba(0,230,118,.3); font-size:8px; font-weight:700; padding:2px 8px; border-radius:10px; }
.badge-hist  { background:rgba(255,193,7,.15);  color:#ffc107; border:1px solid rgba(255,193,7,.3); font-size:8px; font-weight:700; padding:2px 8px; border-radius:10px; }
.dv { width:1px; height:22px; background:rgba(255,255,255,.08); }
.last-upd { font-size:10px; color:rgba(255,255,255,.25); margin-left:auto; }

/* legend */
.legend {
    display:flex; align-items:center; gap:14px; flex-wrap:wrap;
    padding:8px 16px; background:rgba(0,0,0,.3); border:1px solid rgba(255,255,255,.06);
    border-radius:10px; margin-bottom:14px; font-size:10px;
}
.li { display:flex; align-items:center; gap:5px; color:rgba(255,255,255,.45); }
.ld { width:8px; height:8px; border-radius:50%; }

/* main card */
.main-card { border-radius:12px; overflow:hidden; border:1px solid rgba(255,107,0,.12); background:#080812; box-shadow:0 6px 30px rgba(0,0,0,.5); }
.ts { overflow-x:auto; }

/* table */
.vt { width:100%; border-collapse:collapse; }

/* header group row */
.vt thead tr.hg th {
    padding:10px 10px 6px; text-align:center; font-size:10px; font-weight:900;
    text-transform:uppercase; letter-spacing:.7px; white-space:nowrap; border-bottom:none;
}
/* header sub-col row */
.vt thead tr.hs th {
    padding:5px 9px 9px; text-align:center; font-size:8.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:.3px; white-space:nowrap;
    border-bottom:2px solid rgba(255,107,0,.12); color:rgba(255,255,255,.38);
}

/* group header colors */
.gh-meta  { background:rgba(0,0,0,.55) !important; color:rgba(255,255,255,.35) !important; }
.gh-atm   { background:rgba(255,107,0,.1) !important; color:#ff9f00 !important; }
.gh-pm1   { background:rgba(255,165,2,.07) !important; color:#ffa502 !important; }
.gh-pm2   { background:rgba(255,193,7,.05) !important; color:#ffc107 !important; }
.gh-final { background:rgba(224,64,251,.08) !important; color:#e040fb !important; }
.gh-oi    { background:rgba(79,195,247,.06) !important; color:#4fc3f7 !important; }

/* separator borders */
.sl-atm   { border-left:3px solid rgba(255,107,0,.5) !important; }
.sl-pm1   { border-left:3px solid rgba(255,165,2,.4) !important; }
.sl-pm2   { border-left:3px solid rgba(255,193,7,.3) !important; }
.sl-final { border-left:3px solid rgba(224,64,251,.5) !important; }
.sl-oi    { border-left:3px solid rgba(79,195,247,.4) !important; }
.sl-inner { border-left:1px solid rgba(255,255,255,.06) !important; }

/* body cells */
.vt tbody td {
    padding:2px 3px; text-align:center; font-size:11px;
    border-bottom:1px solid rgba(255,255,255,.03);
    vertical-align:middle; white-space:nowrap;
}
.vt tbody tr:hover td { background:rgba(255,107,0,.035) !important; }
.re { background:rgba(255,255,255,.007); }
.ro { background:rgba(0,0,0,.14); }

/* section bg tints */
.bg-atm   { background:rgba(255,107,0,.035) !important; }
.bg-pm1   { background:rgba(255,165,2,.025) !important; }
.bg-pm2   { background:rgba(255,193,7,.018) !important; }
.bg-final { background:rgba(224,64,251,.03) !important; }
.bg-oi    { background:rgba(79,195,247,.025) !important; }

/* meta cells */
.cn  { color:rgba(255,255,255,.2); font-size:8px; font-weight:700; }
.ct  { color:#ff9f00; font-size:13px; font-weight:900; letter-spacing:.5px; }
.bsym {
    display:inline-block; background:rgba(255,107,0,.12); color:#ff9f00;
    border:1px solid rgba(255,107,0,.3); border-radius:6px;
    padding:2px 9px; font-size:10px; font-weight:900;
}
.batm {
    display:inline-block; background:rgba(255,107,0,.08); color:#ffa502;
    border:1px solid rgba(255,107,0,.2); border-radius:5px;
    padding:1px 7px; font-size:8px; font-weight:700;
}
.bexp { display:block; color:rgba(255,255,255,.2); font-size:8px; font-weight:500; margin-top:2px; }

/* strike label inside cell */
.sk { display:block; font-size:8px; color:rgba(255,255,255,.28); font-weight:600; margin-bottom:2px; }

/* vol spike badges */
.vs-ext  { display:inline-block; background:rgba(255,0,0,.28); color:#ff4444; border:1px solid rgba(255,0,0,.6); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:900; white-space:nowrap; animation:predglo 1s ease-in-out infinite alternate; }
.vs-str  { display:inline-block; background:rgba(255,50,50,.2); color:#ff6b44; border:1px solid rgba(255,80,50,.5); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:900; white-space:nowrap; animation:blink .8s step-end infinite; }
.vs-spk  { display:inline-block; background:rgba(255,107,0,.2); color:#ff9f00; border:1px solid rgba(255,107,0,.55); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:800; white-space:nowrap; }
.vs-elv  { display:inline-block; background:rgba(255,193,7,.14); color:#ffc107; border:1px solid rgba(255,193,7,.35); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:700; white-space:nowrap; }
.vs-nrm  { display:inline-block; background:rgba(255,255,255,.04); color:rgba(255,255,255,.25); border:1px solid rgba(255,255,255,.08); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:600; white-space:nowrap; }
.vs-opn  { display:inline-block; background:rgba(79,195,247,.1); color:#4fc3f7; border:1px solid rgba(79,195,247,.25); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:700; white-space:nowrap; }
.vs-na   { color:rgba(255,255,255,.15); font-size:8px; }
/* ratio shown ABOVE the spike badge */
.vr-above {
    display:block; font-size:11px; font-weight:900;
    letter-spacing:.3px; margin-bottom:2px; line-height:1;
}

@keyframes predglo { from { box-shadow:0 0 4px rgba(255,0,0,.4); } to { box-shadow:0 0 14px rgba(255,0,0,.8); } }
@keyframes blink   { 50% { opacity:.5; } }

/* OI sentiment */
.oi-bull { display:inline-block; background:rgba(40,167,69,.18); color:#51cf66; border:1px solid rgba(40,167,69,.4); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:800; }
.oi-bear { display:inline-block; background:rgba(220,53,69,.18); color:#ff6b6b; border:1px solid rgba(220,53,69,.4); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:800; }
.oi-neu  { display:inline-block; background:rgba(108,117,125,.14); color:rgba(255,255,255,.35); border:1px solid rgba(255,255,255,.1); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:600; }
.oi-na   { color:rgba(255,255,255,.18); font-size:8px; }
.str-vs  { display:inline-block; background:rgba(255,71,87,.2); color:#ff4757; border:1px solid rgba(255,71,87,.4); border-radius:5px; padding:2px 6px; font-size:8px; font-weight:800; }
.str-s   { display:inline-block; background:rgba(255,165,2,.16); color:#ffa502; border:1px solid rgba(255,165,2,.38); border-radius:5px; padding:2px 6px; font-size:8px; font-weight:800; }
.str-m   { display:inline-block; background:rgba(255,193,7,.12); color:#ffc107; border:1px solid rgba(255,193,7,.28); border-radius:5px; padding:2px 6px; font-size:8px; font-weight:700; }
.str-w   { display:inline-block; background:rgba(255,255,255,.04); color:rgba(255,255,255,.25); border:1px solid rgba(255,255,255,.08); border-radius:5px; padding:2px 6px; font-size:8px; font-weight:600; }

/* time filter select */
.time-select {
    background:rgba(79,195,247,.08); border:1px solid rgba(79,195,247,.3);
    color:#4fc3f7; border-radius:8px; padding:6px 10px; font-size:12px;
    font-weight:700; cursor:pointer; outline:none; min-width:100px;
}
.time-select option { background:#0d0d1f; color:#4fc3f7; }
.time-select:focus { border-color:#4fc3f7; }
.time-badge {
    display:inline-block; background:rgba(79,195,247,.12); color:#4fc3f7;
    border:1px solid rgba(79,195,247,.25); border-radius:6px;
    padding:3px 10px; font-size:10px; font-weight:800; letter-spacing:.3px;
}

/* loading / empty */
.spinner { width:36px; height:36px; border:4px solid rgba(255,107,0,.1); border-top:4px solid #ff9f00; border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.loading-wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:80px; }
.no-data { text-align:center; padding:70px; color:rgba(255,255,255,.22); font-size:13px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>&#128293; Volume Spike 15Min
                    <span style="background:rgba(255,107,0,.15);color:#ff9f00;padding:2px 9px;border-radius:5px;font-size:10px;font-weight:700;margin-left:8px;border:1px solid rgba(255,107,0,.3);">ATM &middot; ATM&plusmn;1 &middot; ATM&plusmn;2</span>
                </h4>
                <p>
                    Vol Spike = Current Vol &divide; Session Avg &nbsp;&middot;&nbsp;
                    <span style="color:#ff9f00;">&#9679; ATM (own)</span> &nbsp;&middot;&nbsp;
                    <span style="color:#ffa502;">&#9679; ATM&plusmn;1 (CE-1, CE+1 / PE-1, PE+1)</span> &nbsp;&middot;&nbsp;
                    <span style="color:#ffc107;">&#9679; ATM&plusmn;2</span> &nbsp;&middot;&nbsp;
                    <span style="color:#e040fb;">&#9679; Final Block = Sum all strikes</span> &nbsp;&middot;&nbsp;
                    <span style="color:#4fc3f7;">&#9679; 15Min OI Sentiment</span>
                </p>
            </div>
            <a href="{{ route('pivot-signal-15.index') }}" class="btn btn-sm" style="background:rgba(255,107,0,.1);color:#ff9f00;border:1px solid rgba(255,107,0,.3);font-size:11px;font-weight:700;">
                &#9889; Pivot Signal
            </a>
        </div>
    </div>

    {{-- Legend --}}
    <div class="legend">
        <span style="font-weight:800;color:rgba(255,255,255,.35);font-size:8px;text-transform:uppercase;letter-spacing:1px;">Spike:</span>
        <span class="li"><span class="ld" style="background:#ff4444;"></span> EXTREME (&ge;3x)</span>
        <span class="li"><span class="ld" style="background:#ff6b44;"></span> STRONG (&ge;2x)</span>
        <span class="li"><span class="ld" style="background:#ff9f00;"></span> SPIKE (&ge;1.5x)</span>
        <span class="li"><span class="ld" style="background:#ffc107;"></span> ELEVATED (&ge;1.2x)</span>
        <span class="li"><span class="ld" style="background:rgba(255,255,255,.18);"></span> NORMAL</span>
        <span class="li"><span class="ld" style="background:#4fc3f7;"></span> OPENING (1st candle)</span>
        <span style="margin-left:auto;font-size:10px;color:rgba(255,255,255,.3);" id="row-count"></span>
    </div>

    {{-- Filter Bar --}}
    <div class="filter-bar">
        <label>DATE</label>
        <div class="date-wrap">
            <button class="nav-btn" onclick="shiftDate(-1)">&#8249;</button>
            <input type="date" id="dp" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="loadData()">
            <button class="nav-btn" onclick="shiftDate(1)">&#8250;</button>
            <button class="nav-btn w-auto" onclick="goToday()">Today</button>
            <span id="dbadge"></span>
        </div>
        <div class="dv"></div>
        <label>SYMBOL</label>
        <select id="ss" class="sym-select" onchange="loadData()">
            <option value="ALL">&#8212; All Symbols &#8212;</option>
        </select>
        <button class="btn-load" onclick="loadData()">&#8635; Load</button>
        <button class="auto-btn" id="abtn" onclick="toggleAuto()">&#9654; Auto 15s</button>
        <span id="atag" style="font-size:10px;color:#00e676;"></span>
        <div class="dv"></div>
        <label>CANDLE TIME</label>
        <select id="tf" class="time-select" onchange="applyTimeFilter()">
            <option value="LATEST">&#9657; Latest</option>
        </select>
        <span class="last-upd" id="lu"></span>
    </div>

    {{-- Table --}}
    <div class="main-card">
        <div class="ts">
            <table class="vt">
                <thead>
                    <tr class="hg">
                        {{-- Meta --}}
                        <th colspan="3" class="gh-meta">Meta</th>

                        {{-- ATM Vol Spike — 2 cols (no ratio cols) --}}
                        <th colspan="2" class="gh-atm sl-atm">
                            &#128293; ATM VOL SPIKE
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">CE ATM &nbsp;&middot;&nbsp; PE ATM</span>
                        </th>

                        {{-- ATM±1 — 4 cols --}}
                        <th colspan="4" class="gh-pm1 sl-pm1">
                            &#9650; ATM&plusmn;1 VOL SPIKE
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">CE ATM&minus;1 &nbsp;&middot;&nbsp; CE ATM+1 &nbsp;&nbsp; PE ATM&minus;1 &nbsp;&middot;&nbsp; PE ATM+1</span>
                        </th>

                        {{-- ATM±2 — 4 cols --}}
                        <th colspan="4" class="gh-pm2 sl-pm2">
                            &#8660; ATM&plusmn;2 VOL SPIKE
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">CE ATM&minus;2 &nbsp;&middot;&nbsp; CE ATM+2 &nbsp;&nbsp; PE ATM&minus;2 &nbsp;&middot;&nbsp; PE ATM+2</span>
                        </th>

                        {{-- Final Block — 2 cols --}}
                        <th colspan="2" class="gh-final sl-final">
                            &#9733; FINAL BLOCK
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">SUM ATM+ATM&plusmn;1+ATM&plusmn;2 &nbsp;&middot;&nbsp; CE &amp; PE</span>
                        </th>

                        {{-- 15Min OI Sentiment — 4 cols --}}
                        <th colspan="4" class="gh-oi sl-oi">
                            &#9889; 15Min OI Sentiment
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">Current candle &mdash; CE vs PE OI</span>
                        </th>
                    </tr>
                    <tr class="hs">
                        {{-- Meta --}}
                        <th class="gh-meta">#</th>
                        <th class="gh-meta">Time</th>
                        <th class="gh-meta">ATM Strike<br><span style="font-size:8px;opacity:.4;font-weight:400;">Symbol / Expiry</span></th>

                        {{-- ATM CE / PE — merged (ratio inline above badge) --}}
                        <th class="sl-atm bg-atm" style="color:#51cf66 !important;">CE ATM<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Ratio &middot; Spike</span></th>
                        <th class="bg-atm sl-inner" style="color:#ff6b6b !important;">PE ATM<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Ratio &middot; Spike</span></th>

                        {{-- ATM±1 CE-1, CE+1 --}}
                        <th class="sl-pm1 bg-pm1" style="color:#82e09a !important;">CE ATM&minus;1<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Strike &middot; Ratio &middot; Spike</span></th>
                        <th class="bg-pm1 sl-inner" style="color:#51cf66 !important;">CE ATM+1<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Strike &middot; Ratio &middot; Spike</span></th>
                        {{-- ATM±1 PE-1, PE+1 --}}
                        <th class="bg-pm1 sl-inner" style="color:#ff8a95 !important;">PE ATM&minus;1<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Strike &middot; Ratio &middot; Spike</span></th>
                        <th class="bg-pm1 sl-inner" style="color:#ff6b6b !important;">PE ATM+1<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Strike &middot; Ratio &middot; Spike</span></th>

                        {{-- ATM±2 CE-2, CE+2 --}}
                        <th class="sl-pm2 bg-pm2" style="color:#a8d8b8 !important;">CE ATM&minus;2<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Strike &middot; Ratio &middot; Spike</span></th>
                        <th class="bg-pm2 sl-inner" style="color:#82e09a !important;">CE ATM+2<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Strike &middot; Ratio &middot; Spike</span></th>
                        {{-- ATM±2 PE-2, PE+2 --}}
                        <th class="bg-pm2 sl-inner" style="color:#f0a0a8 !important;">PE ATM&minus;2<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Strike &middot; Ratio &middot; Spike</span></th>
                        <th class="bg-pm2 sl-inner" style="color:#ff8a95 !important;">PE ATM+2<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Strike &middot; Ratio &middot; Spike</span></th>

                        {{-- Final Block CE / PE --}}
                        <th class="sl-final bg-final" style="color:#ce93d8 !important;">&#931; CE TOTAL<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Ratio &middot; Spike</span></th>
                        <th class="bg-final sl-inner" style="color:#b39ddb !important;">&#931; PE TOTAL<br><span style="font-size:7.5px;opacity:.55;font-weight:400;">Ratio &middot; Spike</span></th>

                        {{-- OI Sentiment --}}
                        <th class="sl-oi bg-oi" style="color:#4fc3f7 !important;">Signal</th>
                        <th class="bg-oi" style="color:#82e09a !important;">CE OI%</th>
                        <th class="bg-oi" style="color:#ff8a95 !important;">PE OI%</th>
                        <th class="bg-oi" style="color:#ffc107 !important;">Strength</th>
                    </tr>
                </thead>
                <tbody id="vt-body">
                    <tr><td colspan="19">
                        <div class="loading-wrap">
                            <div class="spinner"></div>
                            <div style="color:#ff9f00;margin-top:14px;font-size:13px;font-weight:600;">Loading volume spike data&hellip;</div>
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
/* ═══════════════════════════════════════════════════
   VOLUME SPIKE 15MIN — JS
═══════════════════════════════════════════════════ */
let autoTimer  = null;
let cachedSyms = [];
let fullData   = [];   // stores complete API response for client-side time filtering
const todayStr = '{{ now()->toDateString() }}';

$(document).ready(() => loadData());

const dp  = () => document.getElementById('dp').value;
const sym = () => document.getElementById('ss').value;
const tf  = () => document.getElementById('tf').value;

function shiftDate(d) {
    const el = document.getElementById('dp');
    const dt = new Date(el.value);
    dt.setDate(dt.getDate() + d);
    const s = dt.toISOString().split('T')[0];
    if (s > todayStr) return;
    el.value = s; loadData();
}
function goToday() { document.getElementById('dp').value = todayStr; loadData(); }

function updateBadge(isToday) {
    document.getElementById('dbadge').innerHTML = isToday
        ? '<span class="badge-today">&#9679; Live</span>'
        : '<span class="badge-hist">&#128197; Historical</span>';
}
function rebuildSyms(syms) {
    if (JSON.stringify(cachedSyms) === JSON.stringify(syms)) return;
    cachedSyms = syms;
    const sel = document.getElementById('ss'), prev = sel.value;
    sel.innerHTML = '<option value="ALL">&#8212; All Symbols &#8212;</option>';
    syms.forEach(s => {
        const o = document.createElement('option');
        o.value = s; o.textContent = s;
        if (s === prev) o.selected = true;
        sel.appendChild(o);
    });
}

/**
 * Build time dropdown from all unique interval times across all symbols.
 * Default = LATEST (auto-picks the last available time across all data).
 * Preserves user selection if the time still exists in new data.
 */
function rebuildTimeSelect(data) {
    const sel  = document.getElementById('tf');
    const prev = sel.value; // preserve previous selection if possible

    // Collect all unique times from every symbol's intervals, sorted
    const timesSet = new Set();
    data.forEach(d => (d.intervals || []).forEach(iv => timesSet.add(iv.time)));
    const times = Array.from(timesSet).sort();

    sel.innerHTML = '<option value="LATEST">&#9657; Latest</option>';
    times.forEach(t => {
        const o = document.createElement('option');
        o.value = t; o.textContent = t;
        sel.appendChild(o);
    });

    // Restore prior selection if it still exists, otherwise default to LATEST
    if (prev !== 'LATEST' && times.includes(prev)) {
        sel.value = prev;
    } else {
        sel.value = 'LATEST';
    }
}

/**
 * Determine the effective time to filter by:
 * - 'LATEST' → use the maximum time present in the data
 * - Any specific time string → use that exact time
 */
function getEffectiveTime(data, selectedTime) {
    if (selectedTime !== 'LATEST') return selectedTime;
    // Find the latest time across all symbols
    let max = '';
    data.forEach(d => (d.intervals || []).forEach(iv => { if (iv.time > max) max = iv.time; }));
    return max;
}

/**
 * Re-render the table using the currently selected time filter.
 * No API call — pure client-side filter on fullData.
 */
function applyTimeFilter() {
    if (!fullData.length) return;
    const s = sym();
    if (s !== 'ALL') {
        renderAllIntervals(fullData);
        return;
    }
    const effectiveTime = getEffectiveTime(fullData, tf());
    renderFiltered(fullData, effectiveTime);
}

function toggleAuto() {
    const btn = document.getElementById('abtn');
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        btn.textContent = '\u25b6 Auto 15s'; btn.classList.remove('on');
        document.getElementById('atag').textContent = '';
    } else {
        autoTimer = setInterval(loadData, 15000);
        btn.textContent = '\u25a0 Stop'; btn.classList.add('on');
        document.getElementById('atag').textContent = '\u25cf live';
        loadData();
    }
}

function loadData() {
    const date = dp(), s = sym();
    if (date !== todayStr && autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        const btn = document.getElementById('abtn');
        btn.textContent = '\u25b6 Auto 15s'; btn.classList.remove('on');
        document.getElementById('atag').textContent = '';
    }
    const cols = 19;
    $('#vt-body').html('<tr><td colspan="' + cols + '"><div class="loading-wrap"><div class="spinner"></div><div style="color:#ff9f00;margin-top:12px;font-size:13px;">Fetching data for ' + date + '&hellip;</div></div></td></tr>');
    $.ajax({
        url: '{{ route("volume-spike-15.signals") }}',
        data: { symbol: s, date },
        success(res) {
            updateBadge(res.is_today);
            if (res.available_symbols && res.available_symbols.length) rebuildSyms(res.available_symbols);
            if (!res.success || !res.data || !res.data.length) {
                fullData = [];
                $('#vt-body').html('<tr><td colspan="' + cols + '"><div class="no-data"><div style="font-size:2rem;opacity:.25;">&#128293;</div><p style="margin-top:12px;">' + (res.message || 'No data for ' + date) + '</p></div></td></tr>');
                return;
            }

            fullData = res.data;

            // Rebuild time dropdown, then apply filter
            rebuildTimeSelect(fullData);
            if (s !== 'ALL') {
                renderAllIntervals(fullData);
            } else {
                const effectiveTime = getEffectiveTime(fullData, tf());
                const totalRows = fullData.reduce((a, d) => {
                    const count = (d.intervals || []).filter(iv => iv.time === effectiveTime).length;
                    return a + count;
                }, 0);
                document.getElementById('row-count').textContent =
                    totalRows + ' row(s) at ' + effectiveTime + ' across ' + fullData.length + ' symbol(s)';
                renderFiltered(fullData, effectiveTime);
            }
            document.getElementById('lu').textContent = 'Updated: ' + new Date().toLocaleTimeString();
        },
        error(xhr) {
            $('#vt-body').html('<tr><td colspan="' + cols + '"><div class="no-data">&#9888; ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error') + '</div></td></tr>');
        }
    });
}

function renderAllIntervals(data) {
    let rows = '', rn = 1;

    data.forEach((d, si) => {
        (d.intervals || []).forEach((iv, ivIdx) => {
            const z = ivIdx % 2 === 0 ? 're' : 'ro';
            const ce = iv.ce || {}, pe = iv.pe || {}, st = iv.strikes || {}, oi = iv.oi_sentiment || {};

            rows += '<tr class="' + z + '">'
                + '<td class="cn">' + rn++ + '</td>'
                + '<td class="ct">' + iv.time + '</td>'
                + '<td><span class="bsym">' + d.symbol + '</span></td>'
                + '<td class="sl-atm bg-atm">'  + vsBadge(ce.atm, null) + '</td>'
                + '<td class="bg-atm sl-inner">' + vsBadge(pe.atm, null) + '</td>'
                + '<td class="sl-pm1 bg-pm1">'  + vsBadge(ce.m1, st.m1) + '</td>'
                + '<td class="bg-pm1 sl-inner">' + vsBadge(ce.p1, st.p1) + '</td>'
                + '<td class="bg-pm1 sl-inner">' + vsBadge(pe.m1, st.m1) + '</td>'
                + '<td class="bg-pm1 sl-inner">' + vsBadge(pe.p1, st.p1) + '</td>'
                + '<td class="sl-pm2 bg-pm2">'  + vsBadge(ce.m2, st.m2) + '</td>'
                + '<td class="bg-pm2 sl-inner">' + vsBadge(ce.p2, st.p2) + '</td>'
                + '<td class="bg-pm2 sl-inner">' + vsBadge(pe.m2, st.m2) + '</td>'
                + '<td class="bg-pm2 sl-inner">' + vsBadge(pe.p2, st.p2) + '</td>'
                + '<td class="sl-final bg-final">' + vsBadge(iv.final_ce, null) + '</td>'
                + '<td class="bg-final sl-inner">'  + vsBadge(iv.final_pe, null) + '</td>'
                + '<td class="sl-oi bg-oi" title="' + (oi.condition||'') + ' | ' + (oi.reason||'') + '">' + oiBadge(oi.signal) + '</td>'
                + '<td class="bg-oi">' + pctCell(oi.ce_oi_pct) + '</td>'
                + '<td class="bg-oi">' + pctCell(oi.pe_oi_pct) + '</td>'
                + '<td class="bg-oi">' + strBadge(oi.strength)  + '</td>'
                + '</tr>';
        });
    });

    const matched = rows ? (rows.match(/<tr /g)||[]).length : 0;
    document.getElementById('row-count').textContent =
        matched + ' candle(s) for ' + (data[0] ? data[0].symbol : '') + ' (all intervals)';

    if (!rows) rows = '<tr><td colspan="19"><div class="no-data">No data found.</div></td></tr>';
    $('#vt-body').html(rows);
}

// ── Badge / cell builders ──────────────────────────────────────────────────

/**
 * Single merged cell:
 *   [strike label]        ← optional, for ATM±1/2
 *   1.91x                 ← ratio ABOVE the badge (colored, bold)
 *   [🔥 SPIKE badge]      ← signal label
 */
function vsBadge(vs, strikePx) {
    if (!vs) return '<span class="vs-na">&mdash;</span>';

    const sk = strikePx != null
        ? '<span class="sk">&#8377;' + ni(strikePx) + '</span>'
        : '';

    const t   = vs.spike_type || 'NORMAL';
    const map = {
        EXTREME:      ['vs-ext', '&#128308;&#128308; EXTREME'],
        STRONG_SPIKE: ['vs-str', '&#128308; STRONG'],
        SPIKE:        ['vs-spk', '&#128293; SPIKE'],
        ELEVATED:     ['vs-elv', '&#9650; ELEV'],
        NORMAL:       ['vs-nrm', '&#9135; NORMAL'],
        OPENING:      ['vs-opn', '&#9888; OPENING'],
    };
    const [cls, lbl] = map[t] || map.NORMAL;

    // Ratio shown ABOVE the badge — color-coded, no avg
    let ratioLine = '';
    if (vs.spike_ratio != null) {
        const r  = Number(vs.spike_ratio);
        const rc = r >= 3 ? '#ff4444' : r >= 2 ? '#ff6b44' : r >= 1.5 ? '#ff9f00' : r >= 1.2 ? '#ffc107' : 'rgba(255,255,255,.35)';
        ratioLine = '<span class="vr-above" style="color:' + rc + ';">' + r.toFixed(2) + 'x</span>';
    }

    return sk + ratioLine + '<span class="' + cls + '">' + lbl + '</span>';
}

function oiBadge(sig) {
    if (!sig || sig==='N/A') return '<span class="oi-na">&mdash;</span>';
    if (sig==='BULLISH') return '<span class="oi-bull">&#129033; BULLISH</span>';
    if (sig==='BEARISH') return '<span class="oi-bear">&#129035; BEARISH</span>';
    return '<span class="oi-neu">&#9679; NEUTRAL</span>';
}

function pctCell(pct) {
    if (pct==null) return '<span style="color:rgba(255,255,255,.18);font-size:8px;">&mdash;</span>';
    const v=Number(pct), c=v>0?'#51cf66':v<0?'#ff6b6b':'rgba(255,255,255,.3)';
    return '<strong style="color:'+c+';font-size:10px;">'+(v>0?'+':'')+v.toFixed(2)+'%</strong>';
}

function strBadge(str) {
    if (!str||str==='N/A') return '<span style="color:rgba(255,255,255,.15);font-size:8px;">&mdash;</span>';
    if (str==='Very Strong Signal') return '<span class="str-vs">&#128293; V.Strong</span>';
    if (str==='Strong Signal')      return '<span class="str-s">&#9889; Strong</span>';
    if (str==='Moderate Signal')    return '<span class="str-m">&#9733; Moderate</span>';
    return '<span class="str-w">Weak</span>';
}

// ── Main renderer (time-filtered) ──────────────────────────────────────────

function renderFiltered(data, effectiveTime) {
    let rows = '', rn = 1;

    data.forEach((d, si) => {
        const z = si%2===0?'re':'ro';
        // Only show the interval matching the selected time
        (d.intervals || [])
            .filter(iv => iv.time === effectiveTime)
            .forEach(iv => {
                const ce = iv.ce || {}, pe = iv.pe || {}, st = iv.strikes || {}, oi = iv.oi_sentiment || {};

                rows += '<tr class="' + z + '">'

                    // Meta
                    + '<td class="cn">' + rn++ + '</td>'
                    + '<td class="ct">' + iv.time + '</td>'
                    + '<td><span class="bsym">' + d.symbol + '</span></td>'

                    // ATM CE / PE (ratio inline above badge)
                    + '<td class="sl-atm bg-atm">'  + vsBadge(ce.atm, null) + '</td>'
                    + '<td class="bg-atm sl-inner">' + vsBadge(pe.atm, null) + '</td>'

                    // ATM±1 CE: ATM-1, ATM+1
                    + '<td class="sl-pm1 bg-pm1">'  + vsBadge(ce.m1, st.m1) + '</td>'
                    + '<td class="bg-pm1 sl-inner">' + vsBadge(ce.p1, st.p1) + '</td>'
                    // ATM±1 PE: ATM-1, ATM+1
                    + '<td class="bg-pm1 sl-inner">' + vsBadge(pe.m1, st.m1) + '</td>'
                    + '<td class="bg-pm1 sl-inner">' + vsBadge(pe.p1, st.p1) + '</td>'

                    // ATM±2 CE: ATM-2, ATM+2
                    + '<td class="sl-pm2 bg-pm2">'  + vsBadge(ce.m2, st.m2) + '</td>'
                    + '<td class="bg-pm2 sl-inner">' + vsBadge(ce.p2, st.p2) + '</td>'
                    // ATM±2 PE: ATM-2, ATM+2
                    + '<td class="bg-pm2 sl-inner">' + vsBadge(pe.m2, st.m2) + '</td>'
                    + '<td class="bg-pm2 sl-inner">' + vsBadge(pe.p2, st.p2) + '</td>'

                    // Final Block CE / PE
                    + '<td class="sl-final bg-final">' + vsBadge(iv.final_ce, null) + '</td>'
                    + '<td class="bg-final sl-inner">'  + vsBadge(iv.final_pe, null) + '</td>'

                    // OI Sentiment
                    + '<td class="sl-oi bg-oi" title="' + (oi.condition||'') + ' | ' + (oi.reason||'') + '">' + oiBadge(oi.signal) + '</td>'
                    + '<td class="bg-oi">' + pctCell(oi.ce_oi_pct) + '</td>'
                    + '<td class="bg-oi">' + pctCell(oi.pe_oi_pct) + '</td>'
                    + '<td class="bg-oi">' + strBadge(oi.strength)  + '</td>'

                    + '</tr>';
            });
    });

    // Update row count with current filter info
    const matched = rows ? (rows.match(/<tr /g)||[]).length : 0;
    document.getElementById('row-count').textContent =
        matched + ' row(s) at ' + effectiveTime + ' across ' + data.length + ' symbol(s)';

    if (!rows) rows = '<tr><td colspan="19"><div class="no-data">No data found for ' + effectiveTime + '.</div></td></tr>';
    $('#vt-body').html(rows);
}

function ni(v) {
    if (v==null||v===''||v===undefined) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}
</script>
@endpush