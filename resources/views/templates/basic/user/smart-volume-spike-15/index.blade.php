@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════════
   SMART VOLUME SPIKE 15MIN
   Enhanced terminal — all new signals + trade logic
═══════════════════════════════════════════════════════ */
.page-header {
    background: linear-gradient(135deg, #0f0f1a 0%, #1a0a2e 50%, #0f1a1a 100%);
    border: 1px solid rgba(255,107,0,.3); border-radius: 12px;
    padding: 16px 24px; margin-bottom: 16px;
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
.badge-hist  { background:rgba(255,193,7,.15); color:#ffc107; border:1px solid rgba(255,193,7,.3); font-size:8px; font-weight:700; padding:2px 8px; border-radius:10px; }
.dv { width:1px; height:22px; background:rgba(255,255,255,.08); }
.last-upd { font-size:10px; color:rgba(255,255,255,.25); margin-left:auto; }
.time-select {
    background:rgba(79,195,247,.08); border:1px solid rgba(79,195,247,.3);
    color:#4fc3f7; border-radius:8px; padding:6px 10px; font-size:12px;
    font-weight:700; cursor:pointer; outline:none; min-width:100px;
}
.time-select option { background:#0d0d1f; color:#4fc3f7; }

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
.vt thead tr.hg th {
    padding:10px 10px 6px; text-align:center; font-size:10px; font-weight:900;
    text-transform:uppercase; letter-spacing:.7px; white-space:nowrap; border-bottom:none;
}
.vt thead tr.hs th {
    padding:5px 9px 9px; text-align:center; font-size:8.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:.3px; white-space:nowrap;
    border-bottom:2px solid rgba(255,107,0,.12); color:rgba(255,255,255,.38);
}

/* group header colors */
.gh-meta   { background:rgba(0,0,0,.55) !important; color:rgba(255,255,255,.35) !important; }
.gh-atm    { background:rgba(255,107,0,.1) !important; color:#ff9f00 !important; }
.gh-pm1    { background:rgba(255,165,2,.07) !important; color:#ffa502 !important; }
.gh-pm2    { background:rgba(255,193,7,.05) !important; color:#ffc107 !important; }
.gh-final  { background:rgba(224,64,251,.08) !important; color:#e040fb !important; }
.gh-oi     { background:rgba(79,195,247,.06) !important; color:#4fc3f7 !important; }
.gh-dom    { background:rgba(129,212,250,.06) !important; color:#81d4fa !important; }
.gh-trade  { background:rgba(0,230,118,.07) !important; color:#00e676 !important; }

/* separator borders */
.sl-atm   { border-left:3px solid rgba(255,107,0,.5) !important; }
.sl-pm1   { border-left:3px solid rgba(255,165,2,.4) !important; }
.sl-pm2   { border-left:3px solid rgba(255,193,7,.3) !important; }
.sl-final { border-left:3px solid rgba(224,64,251,.5) !important; }
.sl-oi    { border-left:3px solid rgba(79,195,247,.4) !important; }
.sl-dom   { border-left:3px solid rgba(129,212,250,.4) !important; }
.sl-trade { border-left:3px solid rgba(0,230,118,.5) !important; }
.sl-inner { border-left:1px solid rgba(255,255,255,.06) !important; }

/* body cells */
.vt tbody td {
    padding:3px 4px; text-align:center; font-size:11px;
    border-bottom:1px solid rgba(255,255,255,.03);
    vertical-align:middle; white-space:nowrap;
}
.vt tbody tr:hover td { background:rgba(255,107,0,.035) !important; }
.re { background:rgba(255,255,255,.007); }
.ro { background:rgba(0,0,0,.14); }

/* section tints */
.bg-atm   { background:rgba(255,107,0,.035) !important; }
.bg-pm1   { background:rgba(255,165,2,.025) !important; }
.bg-pm2   { background:rgba(255,193,7,.018) !important; }
.bg-final { background:rgba(224,64,251,.03) !important; }
.bg-oi    { background:rgba(79,195,247,.025) !important; }
.bg-dom   { background:rgba(129,212,250,.018) !important; }
.bg-trade { background:rgba(0,230,118,.02) !important; }

/* meta cells */
.cn  { color:rgba(255,255,255,.2); font-size:8px; font-weight:700; }
.ct  { color:#ff9f00; font-size:13px; font-weight:900; letter-spacing:.5px; }
.bsym {
    display:inline-block; background:rgba(255,107,0,.12); color:#ff9f00;
    border:1px solid rgba(255,107,0,.3); border-radius:6px;
    padding:2px 9px; font-size:10px; font-weight:900;
}
.sk { display:block; font-size:8px; color:rgba(255,255,255,.28); font-weight:600; margin-bottom:2px; }

/* vol spike badges */
.vs-ext  { display:inline-block; background:rgba(255,0,0,.28); color:#ff4444; border:1px solid rgba(255,0,0,.6); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:900; white-space:nowrap; animation:predglo 1s ease-in-out infinite alternate; }
.vs-str  { display:inline-block; background:rgba(255,50,50,.2); color:#ff6b44; border:1px solid rgba(255,80,50,.5); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:900; white-space:nowrap; animation:blink .8s step-end infinite; }
.vs-spk  { display:inline-block; background:rgba(255,107,0,.2); color:#ff9f00; border:1px solid rgba(255,107,0,.55); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:800; white-space:nowrap; }
.vs-elv  { display:inline-block; background:rgba(255,193,7,.14); color:#ffc107; border:1px solid rgba(255,193,7,.35); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:700; white-space:nowrap; }
.vs-nrm  { display:inline-block; background:rgba(255,255,255,.04); color:rgba(255,255,255,.25); border:1px solid rgba(255,255,255,.08); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:600; white-space:nowrap; }
.vs-opn  { display:inline-block; background:rgba(79,195,247,.1); color:#4fc3f7; border:1px solid rgba(79,195,247,.25); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:700; white-space:nowrap; }
.vs-early{ display:inline-block; background:rgba(255,193,7,.08); color:rgba(255,193,7,.6); border:1px solid rgba(255,193,7,.2); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:700; white-space:nowrap; }
.vs-low  { display:inline-block; background:rgba(150,150,150,.1); color:rgba(255,255,255,.3); border:1px solid rgba(255,255,255,.1); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:600; white-space:nowrap; }
.vs-na   { color:rgba(255,255,255,.15); font-size:8px; }
.vr-above { display:block; font-size:11px; font-weight:900; letter-spacing:.3px; margin-bottom:2px; line-height:1; }

@keyframes predglo { from { box-shadow:0 0 4px rgba(255,0,0,.4); } to { box-shadow:0 0 14px rgba(255,0,0,.8); } }
@keyframes blink   { 50% { opacity:.5; } }

/* OI sentiment */
.oi-sbull { display:inline-block; background:rgba(0,230,118,.22); color:#00e676; border:1px solid rgba(0,230,118,.5); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:900; animation:predglo2 1.2s ease-in-out infinite alternate; }
.oi-bull  { display:inline-block; background:rgba(40,167,69,.18); color:#51cf66; border:1px solid rgba(40,167,69,.4); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:800; }
.oi-sbear { display:inline-block; background:rgba(255,23,68,.22); color:#ff1744; border:1px solid rgba(255,23,68,.5); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:900; animation:predglo3 1.2s ease-in-out infinite alternate; }
.oi-bear  { display:inline-block; background:rgba(220,53,69,.18); color:#ff6b6b; border:1px solid rgba(220,53,69,.4); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:800; }
.oi-mix   { display:inline-block; background:rgba(255,193,7,.12); color:#ffc107; border:1px solid rgba(255,193,7,.35); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:700; }
.oi-neu   { display:inline-block; background:rgba(108,117,125,.14); color:rgba(255,255,255,.35); border:1px solid rgba(255,255,255,.1); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:600; }
.oi-na    { color:rgba(255,255,255,.18); font-size:8px; }
@keyframes predglo2 { from { box-shadow:0 0 4px rgba(0,230,118,.3); } to { box-shadow:0 0 12px rgba(0,230,118,.7); } }
@keyframes predglo3 { from { box-shadow:0 0 4px rgba(255,23,68,.3); } to { box-shadow:0 0 12px rgba(255,23,68,.7); } }

.str-vs { display:inline-block; background:rgba(255,71,87,.2); color:#ff4757; border:1px solid rgba(255,71,87,.4); border-radius:5px; padding:2px 6px; font-size:8px; font-weight:800; }
.str-s  { display:inline-block; background:rgba(255,165,2,.16); color:#ffa502; border:1px solid rgba(255,165,2,.38); border-radius:5px; padding:2px 6px; font-size:8px; font-weight:800; }
.str-m  { display:inline-block; background:rgba(255,193,7,.12); color:#ffc107; border:1px solid rgba(255,193,7,.28); border-radius:5px; padding:2px 6px; font-size:8px; font-weight:700; }
.str-w  { display:inline-block; background:rgba(255,255,255,.04); color:rgba(255,255,255,.25); border:1px solid rgba(255,255,255,.08); border-radius:5px; padding:2px 6px; font-size:8px; font-weight:600; }

/* price direction */
.pd-up   { color:#00e676; font-size:11px; font-weight:900; }
.pd-down { color:#ff4444; font-size:11px; font-weight:900; }
.pd-flat { color:rgba(255,255,255,.25); font-size:11px; }

/* time zone badges */
.tz-noise    { display:inline-block; background:rgba(255,193,7,.1); color:#ffc107; border:1px solid rgba(255,193,7,.3); border-radius:5px; padding:1px 6px; font-size:8px; font-weight:700; }
.tz-prime    { display:inline-block; background:rgba(0,230,118,.1); color:#00e676; border:1px solid rgba(0,230,118,.3); border-radius:5px; padding:1px 6px; font-size:8px; font-weight:700; }
.tz-reversal { display:inline-block; background:rgba(255,107,0,.15); color:#ff9f00; border:1px solid rgba(255,107,0,.4); border-radius:5px; padding:1px 6px; font-size:8px; font-weight:700; }

/* trade signal */
.ts-ce {
    display:inline-block; background:rgba(0,230,118,.18); color:#00e676;
    border:1px solid rgba(0,230,118,.5); border-radius:8px; padding:4px 10px;
    font-size:10px; font-weight:900; white-space:nowrap;
    animation:predglo2 1s ease-in-out infinite alternate;
}
.ts-pe {
    display:inline-block; background:rgba(255,23,68,.18); color:#ff1744;
    border:1px solid rgba(255,23,68,.5); border-radius:8px; padding:4px 10px;
    font-size:10px; font-weight:900; white-space:nowrap;
    animation:predglo3 1s ease-in-out infinite alternate;
}
.ts-avoid {
    display:inline-block; background:rgba(150,150,150,.1); color:rgba(255,255,255,.3);
    border:1px solid rgba(255,255,255,.1); border-radius:8px; padding:4px 10px;
    font-size:10px; font-weight:700; white-space:nowrap;
}
.ts-watch {
    display:inline-block; background:rgba(255,193,7,.1); color:#ffc107;
    border:1px solid rgba(255,193,7,.3); border-radius:8px; padding:4px 10px;
    font-size:10px; font-weight:800; white-space:nowrap;
}
.conf-bar { width:100%; height:6px; background:rgba(255,255,255,.07); border-radius:3px; margin-top:3px; overflow:hidden; }
.conf-fill { height:6px; border-radius:3px; transition:width .3s; }

/* continuation */
.cont-hi  { display:inline-block; background:rgba(255,107,0,.2); color:#ff9f00; border:1px solid rgba(255,107,0,.5); border-radius:5px; padding:2px 7px; font-size:8px; font-weight:900; animation:blink .6s step-end infinite; }
.cont-low { display:inline-block; background:rgba(255,193,7,.1); color:#ffc107; border:1px solid rgba(255,193,7,.3); border-radius:5px; padding:2px 7px; font-size:8px; font-weight:700; }

/* trap */
.trap-call { display:inline-block; background:rgba(255,152,0,.15); color:#ff9800; border:1px solid rgba(255,152,0,.4); border-radius:5px; padding:2px 7px; font-size:8px; font-weight:800; }
.trap-put  { display:inline-block; background:rgba(233,30,99,.15); color:#e91e63; border:1px solid rgba(233,30,99,.4); border-radius:5px; padding:2px 7px; font-size:8px; font-weight:800; }

.gh-next  { background:rgba(178,255,89,.07) !important; color:#b2ff59 !important; }
.sl-next  { border-left:3px solid rgba(178,255,89,.5) !important; }
.bg-next  { background:rgba(178,255,89,.015) !important; }

/* next 15m trend */
.n15-up   { display:inline-block; background:rgba(0,230,118,.18); color:#00e676; border:1px solid rgba(0,230,118,.5); border-radius:7px; padding:3px 9px; font-size:10px; font-weight:900; white-space:nowrap; }
.n15-down { display:inline-block; background:rgba(255,23,68,.18); color:#ff1744; border:1px solid rgba(255,23,68,.5); border-radius:7px; padding:3px 9px; font-size:10px; font-weight:900; white-space:nowrap; }
.n15-side { display:inline-block; background:rgba(255,193,7,.1); color:#ffc107; border:1px solid rgba(255,193,7,.3); border-radius:7px; padding:3px 9px; font-size:10px; font-weight:700; white-space:nowrap; }

/* candle body */
.cb-strong  { display:inline-block; background:rgba(0,230,118,.12); color:#00e676; border:1px solid rgba(0,230,118,.3); border-radius:5px; padding:1px 5px; font-size:8px; font-weight:800; }
.cb-mod     { display:inline-block; background:rgba(255,193,7,.1); color:#ffc107; border:1px solid rgba(255,193,7,.25); border-radius:5px; padding:1px 5px; font-size:8px; font-weight:700; }
.cb-weak    { display:inline-block; background:rgba(255,255,255,.05); color:rgba(255,255,255,.3); border:1px solid rgba(255,255,255,.1); border-radius:5px; padding:1px 5px; font-size:8px; }
.cb-doji    { display:inline-block; background:rgba(255,107,0,.1); color:#ff9f00; border:1px solid rgba(255,107,0,.3); border-radius:5px; padding:1px 5px; font-size:8px; font-weight:700; }

/* data missing */
.vs-miss { display:inline-block; background:rgba(150,0,0,.2); color:rgba(255,100,100,.7); border:1px solid rgba(200,0,0,.3); border-radius:6px; padding:2px 5px; font-size:8px; font-weight:700; white-space:nowrap; }
.spinner { width:36px; height:36px; border:4px solid rgba(255,107,0,.1); border-top:4px solid #ff9f00; border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.loading-wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:80px; }
.no-data { text-align:center; padding:70px; color:rgba(255,255,255,.22); font-size:13px; }
/* regime badges */
.rg-trending { display:inline-block; background:rgba(0,230,118,.15); color:#00e676; border:1px solid rgba(0,230,118,.4); border-radius:5px; padding:1px 6px; font-size:8px; font-weight:800; }
.rg-breakout { display:inline-block; background:rgba(255,107,0,.2); color:#ff9f00; border:1px solid rgba(255,107,0,.5); border-radius:5px; padding:1px 6px; font-size:8px; font-weight:900; animation:blink .6s step-end infinite; }
.rg-sideways { display:inline-block; background:rgba(255,193,7,.1); color:#ffc107; border:1px solid rgba(255,193,7,.25); border-radius:5px; padding:1px 6px; font-size:8px; font-weight:700; }
.rg-unknown  { color:rgba(255,255,255,.18); font-size:8px; }
/* dominance ratio */
.dom-ce  { color:#ff6b6b; font-size:11px; font-weight:900; }
.dom-pe  { color:#51cf66; font-size:11px; font-weight:900; }
.dom-bal { color:rgba(255,255,255,.3); font-size:10px; }
.dom-conflict { display:inline-block; background:rgba(255,152,0,.15); color:#ff9800; border:1px solid rgba(255,152,0,.35); border-radius:4px; padding:1px 5px; font-size:7px; font-weight:800; display:block; margin-top:2px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>&#9889; Smart Volume Spike 15Min
                    <span style="background:rgba(0,230,118,.12);color:#00e676;padding:2px 9px;border-radius:5px;font-size:10px;font-weight:700;margin-left:8px;border:1px solid rgba(0,230,118,.3);">v4 PRODUCTION</span>
                </h4>
                <p>
                    ATM + ATM&plusmn;1 only &nbsp;&middot;&nbsp; Adaptive thresholds &nbsp;&middot;&nbsp;
                    Market regime &nbsp;&middot;&nbsp; Dominance ratio &nbsp;&middot;&nbsp;
                    Prev-day seeded &nbsp;&middot;&nbsp;
                    <span style="color:#00e676;">&#9679; Trade signals</span> &nbsp;&middot;&nbsp;
                    <span style="color:#ffc107;">&#9679; Trap detection</span> &nbsp;&middot;&nbsp;
                    <span style="color:#81d4fa;">&#9679; Next 15min lean</span>
                </p>
            </div>
            <a href="{{ route('volume-spike-15.index') }}" class="btn btn-sm" style="background:rgba(255,107,0,.1);color:#ff9f00;border:1px solid rgba(255,107,0,.3);font-size:11px;font-weight:700;">
                &#128293; Basic Spike
            </a>
        </div>
    </div>

    {{-- Legend --}}
    <div class="legend">
        <span style="font-weight:800;color:rgba(255,255,255,.35);font-size:8px;text-transform:uppercase;letter-spacing:1px;">Spike (adaptive):</span>
        <span class="li"><span class="ld" style="background:#ff4444;"></span> EXTREME</span>
        <span class="li"><span class="ld" style="background:#ff6b44;"></span> STRONG</span>
        <span class="li"><span class="ld" style="background:#ff9f00;"></span> SPIKE</span>
        <span class="li"><span class="ld" style="background:#ffc107;"></span> ELEVATED</span>
        <span class="li"><span class="ld" style="background:rgba(255,193,7,.4);"></span> EARLY</span>
        <span class="li"><span class="ld" style="background:rgba(255,255,255,.18);"></span> LOW VOL</span>
        <span style="margin-left:8px;font-weight:800;color:rgba(255,255,255,.35);font-size:8px;">Zones:</span>
        <span class="li"><span class="ld" style="background:#ffc107;"></span> NOISE &lt;10:00</span>
        <span class="li"><span class="ld" style="background:#00e676;"></span> PRIME</span>
        <span class="li"><span class="ld" style="background:#ff9f00;"></span> REVERSAL &gt;14:30</span>
        <span style="margin-left:8px;font-weight:800;color:rgba(255,255,255,.35);font-size:8px;">Regime:</span>
        <span class="li"><span class="ld" style="background:#00e676;"></span> TRENDING</span>
        <span class="li"><span class="ld" style="background:#ff9f00;"></span> BREAKOUT</span>
        <span class="li"><span class="ld" style="background:#ffc107;"></span> SIDEWAYS</span>
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
        <span id="mode-hint" style="font-size:10px;color:rgba(255,193,7,.7);font-weight:700;"></span>
        <span class="last-upd" id="lu"></span>
    </div>

    {{-- Table --}}
    <div class="main-card">
        <div class="ts">
            <table class="vt">
                <thead>
                    <tr class="hg">
                        {{-- Meta (7 cols: #, Time, Zone, Regime, Candle, Price, Symbol) --}}
                        <th colspan="7" class="gh-meta">Meta</th>

                        {{-- ATM Vol Spike (2 cols) --}}
                        <th colspan="2" class="gh-atm sl-atm">
                            &#128293; ATM VOL SPIKE
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">CE ATM &nbsp;&middot;&nbsp; PE ATM</span>
                        </th>

                        {{-- ATM±1 only (4 cols) — ±2 REMOVED v4 --}}
                        <th colspan="4" class="gh-pm1 sl-pm1">
                            &#9650; ATM&plusmn;1 VOL SPIKE
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">CE &minus;1 &nbsp;&middot;&nbsp; CE +1 &nbsp;&nbsp;&nbsp; PE &minus;1 &nbsp;&middot;&nbsp; PE +1</span>
                        </th>

                        {{-- Weighted Final (2 cols) --}}
                        <th colspan="2" class="gh-final sl-final">
                            &#9733; WEIGHTED FINAL
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">ATM&times;3 + &plusmn;1&times;2 (adaptive)</span>
                        </th>

                        {{-- Dominance ratio (2 cols) --}}
                        <th colspan="2" class="gh-dom sl-dom">
                            &#9878; DOMINANCE
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">CE/PE Ratio &nbsp;&middot;&nbsp; Bias</span>
                        </th>

                        {{-- OI Sentiment (4 cols) --}}
                        <th colspan="4" class="gh-oi sl-oi">
                            &#9889; OI SENTIMENT
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">Signal (price+candle+dominance) &nbsp;&middot;&nbsp; CE% &nbsp;&middot;&nbsp; PE% &nbsp;&middot;&nbsp; Strength</span>
                        </th>

                        {{-- Trade Signal (3 cols) --}}
                        <th colspan="3" class="gh-trade sl-trade">
                            &#127919; TRADE SIGNAL
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">Action &nbsp;&middot;&nbsp; Cont. &nbsp;&middot;&nbsp; Trap</span>
                        </th>

                        {{-- Next 15m Trend (2 cols) --}}
                        <th colspan="2" class="gh-next sl-next">
                            &#9654; NEXT 15MIN
                            <br><span style="font-size:8px;font-weight:400;opacity:.6;">Direction &nbsp;&middot;&nbsp; Confidence</span>
                        </th>
                    </tr>
                    <tr class="hs">
                        {{-- Meta --}}
                        <th class="gh-meta">#</th>
                        <th class="gh-meta">Time</th>
                        <th class="gh-meta">Zone</th>
                        <th class="gh-meta">Regime</th>
                        <th class="gh-meta">Candle<br><span style="font-size:7.5px;opacity:.4;">Body%</span></th>
                        <th class="gh-meta">Price&#9651;</th>
                        <th class="gh-meta">Symbol<br><span style="font-size:7.5px;opacity:.4;font-weight:400;">ATM</span></th>

                        {{-- ATM --}}
                        <th class="sl-atm bg-atm" style="color:#51cf66 !important;">CE ATM</th>
                        <th class="bg-atm sl-inner" style="color:#ff6b6b !important;">PE ATM</th>

                        {{-- ±1 --}}
                        <th class="sl-pm1 bg-pm1" style="color:#82e09a !important;">CE &minus;1</th>
                        <th class="bg-pm1 sl-inner" style="color:#51cf66 !important;">CE +1</th>
                        <th class="bg-pm1 sl-inner" style="color:#ff8a95 !important;">PE &minus;1</th>
                        <th class="bg-pm1 sl-inner" style="color:#ff6b6b !important;">PE +1</th>

                        {{-- Final --}}
                        <th class="sl-final bg-final" style="color:#ce93d8 !important;">&#931; CE Wtd</th>
                        <th class="bg-final sl-inner" style="color:#b39ddb !important;">&#931; PE Wtd</th>

                        {{-- Dominance --}}
                        <th class="sl-dom bg-dom" style="color:#81d4fa !important;">CE/PE</th>
                        <th class="bg-dom" style="color:#81d4fa !important;">Bias</th>

                        {{-- OI --}}
                        <th class="sl-oi bg-oi" style="color:#4fc3f7 !important;">Signal</th>
                        <th class="bg-oi" style="color:#82e09a !important;">CE OI%</th>
                        <th class="bg-oi" style="color:#ff8a95 !important;">PE OI%</th>
                        <th class="bg-oi" style="color:#ffc107 !important;">Strength</th>

                        {{-- Trade --}}
                        <th class="sl-trade bg-trade" style="color:#00e676 !important;">Action</th>
                        <th class="bg-trade" style="color:#ffa502 !important;">Cont.</th>
                        <th class="bg-trade" style="color:#ff9800 !important;">Trap</th>

                        {{-- Next 15m --}}
                        <th class="sl-next bg-next" style="color:#b2ff59 !important;">Direction</th>
                        <th class="bg-next" style="color:#b2ff59 !important;">Conf.</th>
                    </tr>
                </thead>
                <tbody id="vt-body">
                    <tr><td colspan="26">
                        <div class="loading-wrap">
                            <div class="spinner"></div>
                            <div style="color:#ff9f00;margin-top:14px;font-size:13px;font-weight:600;">Loading smart volume data&hellip;</div>
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
   SMART VOLUME SPIKE 15MIN — JS
   All new signal columns rendered here
═══════════════════════════════════════════════════ */
const COLS = 26;
let autoTimer  = null;
let cachedSyms = [];
let fullData   = [];
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

function rebuildTimeSelect(data) {
    const sel  = document.getElementById('tf');
    const prev = sel.value;
    const timesSet = new Set();
    data.forEach(d => (d.intervals || []).forEach(iv => timesSet.add(iv.time)));
    const times = Array.from(timesSet).sort();
    sel.innerHTML = '<option value="LATEST">&#9657; Latest</option>';
    times.forEach(t => {
        const o = document.createElement('option');
        o.value = t; o.textContent = t;
        sel.appendChild(o);
    });
    sel.value = (prev !== 'LATEST' && times.includes(prev)) ? prev : 'LATEST';
}

function getEffectiveTime(data, selectedTime) {
    if (selectedTime !== 'LATEST') return selectedTime;
    let max = '';
    data.forEach(d => (d.intervals || []).forEach(iv => { if (iv.time > max) max = iv.time; }));
    return max;
}

function applyTimeFilter() {
    if (!fullData.length) return;
    // In single-symbol mode the time filter is ignored — all candles always shown
    if (sym() !== 'ALL') { renderFiltered(fullData, null); return; }
    renderFiltered(fullData, getEffectiveTime(fullData, tf()));
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
    $('#vt-body').html('<tr><td colspan="' + COLS + '"><div class="loading-wrap"><div class="spinner"></div><div style="color:#ff9f00;margin-top:12px;font-size:13px;">Fetching smart data for ' + date + '&hellip;</div></div></td></tr>');
    $.ajax({
        url: '{{ route("smart-volume-spike-15.signals") }}',
        data: { symbol: s, date },
        success(res) {
            updateBadge(res.is_today);
            if (res.available_symbols && res.available_symbols.length) rebuildSyms(res.available_symbols);
            if (!res.success || !res.data || !res.data.length) {
                fullData = [];
                $('#vt-body').html('<tr><td colspan="' + COLS + '"><div class="no-data"><div style="font-size:2rem;opacity:.25;">&#9889;</div><p style="margin-top:12px;">' + (res.message || 'No data for ' + date) + '</p></div></td></tr>');
                return;
            }
            fullData = res.data;
            rebuildTimeSelect(fullData);

            // Single symbol → disable time filter, show ALL candles
            // ALL symbols → time filter active, show one row per symbol
            const isAll = sym() === 'ALL';
            document.getElementById('tf').disabled = !isAll;
            document.getElementById('tf').style.opacity = isAll ? '1' : '0.4';
            document.getElementById('mode-hint').textContent = isAll ? '' : '📋 Showing all candles for selected symbol';

            if (isAll) {
                renderFiltered(fullData, getEffectiveTime(fullData, tf()));
            } else {
                renderFiltered(fullData, null); // null = show all intervals
            }

            document.getElementById('lu').textContent = 'Updated: ' + new Date().toLocaleTimeString();
        },
        error(xhr) {
            $('#vt-body').html('<tr><td colspan="' + COLS + '"><div class="no-data">&#9888; ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error') + '</div></td></tr>');
        }
    });
}

// ── Badge builders ─────────────────────────────────────────────────────────

function vsBadge(vs, strikePx) {
    if (!vs) return '<span class="vs-na">&mdash;</span>';
    const sk  = strikePx != null ? '<span class="sk">&#8377;' + ni(strikePx) + '</span>' : '';
    const t   = vs.spike_type || 'NORMAL';

    // DATA_MISSING — highest priority check
    if (t === 'DATA_MISSING' || (vs.data_missing)) return sk + '<span class="vs-miss">&#10005; NO DATA</span>';
    if (vs.early && t === 'OPENING') return sk + '<span class="vs-opn">&#9888; OPENING</span>';
    if (vs.early)                    return sk + '<span class="vs-early">&#8987; EARLY</span>';
    if (t === 'LOW_VOLUME')          return sk + ratioLine(vs) + '<span class="vs-low">&#8595; LOW VOL</span>';

    const map = {
        EXTREME:      ['vs-ext',  '&#128308;&#128308; EXTREME'],
        STRONG_SPIKE: ['vs-str',  '&#128308; STRONG'],
        SPIKE:        ['vs-spk',  '&#128293; SPIKE'],
        ELEVATED:     ['vs-elv',  '&#9650; ELEV'],
        NORMAL:       ['vs-nrm',  '&#9135; NORMAL'],
        OPENING:      ['vs-opn',  '&#9888; OPENING'],
    };
    const [cls, lbl] = map[t] || map.NORMAL;
    // Show delta vol below ratio if available
    const dv = (vs.delta_vol != null && vs.delta_vol > 0)
        ? '<span style="display:block;font-size:7px;color:rgba(255,255,255,.2);margin-top:1px;">Δ' + ni(vs.delta_vol) + '</span>'
        : '';
    return sk + ratioLine(vs) + '<span class="' + cls + '">' + lbl + '</span>' + dv;
}

function ratioLine(vs) {
    if (vs.spike_ratio == null) return '';
    const r  = Number(vs.spike_ratio);
    // Use adaptive thresholds if present, else fallback to fixed
    const te = vs.thresh_extreme || 3.0;
    const ts = vs.thresh_strong  || 2.0;
    const rc = r >= te ? '#ff4444' : r >= ts ? '#ff6b44' : r >= 1.5 ? '#ff9f00' : r >= 1.2 ? '#ffc107' : 'rgba(255,255,255,.35)';
    return '<span class="vr-above" style="color:' + rc + ';">' + r.toFixed(2) + 'x</span>';
}

function oiBadge(sig) {
    if (!sig || sig === 'N/A') return '<span class="oi-na">&mdash;</span>';
    const map = {
        STRONG_BULLISH: ['oi-sbull', '&#129033;&#129033; STR BULL'],
        BULLISH:        ['oi-bull',  '&#129033; BULLISH'],
        STRONG_BEARISH: ['oi-sbear', '&#129035;&#129035; STR BEAR'],
        BEARISH:        ['oi-bear',  '&#129035; BEARISH'],
        MIXED:          ['oi-mix',   '&#9888; MIXED'],
    };
    const [cls, lbl] = map[sig] || ['oi-neu', '&#9679; NEUTRAL'];
    return '<span class="' + cls + '">' + lbl + '</span>';
}

function pctCell(pct) {
    if (pct == null) return '<span style="color:rgba(255,255,255,.18);font-size:8px;">&mdash;</span>';
    const v = Number(pct), c = v > 0 ? '#51cf66' : v < 0 ? '#ff6b6b' : 'rgba(255,255,255,.3)';
    return '<strong style="color:' + c + ';font-size:10px;">' + (v > 0 ? '+' : '') + v.toFixed(2) + '%</strong>';
}

function strBadge(str) {
    if (!str || str === 'N/A') return '<span style="color:rgba(255,255,255,.15);font-size:8px;">&mdash;</span>';
    if (str === 'Very Strong Signal') return '<span class="str-vs">&#128293; V.Strong</span>';
    if (str === 'Strong Signal')      return '<span class="str-s">&#9889; Strong</span>';
    if (str === 'Moderate Signal')    return '<span class="str-m">&#9733; Moderate</span>';
    return '<span class="str-w">Weak</span>';
}

function tzBadge(tz) {
    const map = {
        NOISE:    ['tz-noise',    '&#128276; NOISE'],
        PRIME:    ['tz-prime',    '&#9989; PRIME'],
        REVERSAL: ['tz-reversal', '&#9195; REVERSAL'],
    };
    const [cls, lbl] = map[tz] || ['tz-prime', tz];
    return '<span class="' + cls + '">' + lbl + '</span>';
}

function priceDirBadge(dir) {
    if (dir === 'UP')   return '<span class="pd-up">&#9651; UP</span>';
    if (dir === 'DOWN') return '<span class="pd-down">&#9661; DOWN</span>';
    return '<span class="pd-flat">&#8212;</span>';
}

function regimeBadge(regime) {
    if (!regime || regime === 'UNKNOWN') return '<span class="rg-unknown">&mdash;</span>';
    const map = {
        TRENDING:  ['rg-trending', '&#128200; TREND'],
        BREAKOUT:  ['rg-breakout', '&#128293; BREAK'],
        SIDEWAYS:  ['rg-sideways', '&#8596; SIDE'],
    };
    const [cls, lbl] = map[regime] || ['rg-unknown', regime];
    return '<span class="' + cls + '">' + lbl + '</span>';
}

function dominanceRatioBadge(ratio, bias) {
    if (ratio == null) return '<span class="dom-bal">&mdash;</span>';
    const r = Number(ratio).toFixed(2);
    if (bias === 'BEARISH_PRESSURE') return '<span class="dom-ce">' + r + 'x</span><br><span style="font-size:7px;color:#ff6b6b;">CE dom</span>';
    if (bias === 'BULLISH_PRESSURE') return '<span class="dom-pe">' + r + 'x</span><br><span style="font-size:7px;color:#51cf66;">PE dom</span>';
    return '<span class="dom-bal">' + r + 'x</span><br><span style="font-size:7px;color:rgba(255,255,255,.25);">balanced</span>';
}

function dominanceBiasBadge(bias, domConflict) {
    let html = '';
    if (bias === 'BEARISH_PRESSURE') html = '<span style="color:#ff6b6b;font-size:9px;font-weight:800;">&#129035; BEAR</span>';
    else if (bias === 'BULLISH_PRESSURE') html = '<span style="color:#51cf66;font-size:9px;font-weight:800;">&#129033; BULL</span>';
    else html = '<span style="color:rgba(255,255,255,.2);font-size:9px;">NEU</span>';
    if (domConflict) html += '<br><span class="dom-conflict">&#9889; OI conflict</span>';
    return html;
}

function tradeActionBadge(ts) {
    if (!ts) return '<span class="ts-watch">&#128065; WATCH</span>';
    const conf  = ts.confidence || 0;
    const confC = conf >= 80 ? '#00e676' : conf >= 60 ? '#ffc107' : conf >= 40 ? '#ff9f00' : '#ff4444';
    let badge = '';
    if (ts.action === 'BUY_CE') badge = '<span class="ts-ce">&#129033; BUY CE</span>';
    else if (ts.action === 'BUY_PE') badge = '<span class="ts-pe">&#129035; BUY PE</span>';
    else if (ts.action === 'AVOID')  badge = '<span class="ts-avoid">&#9940; AVOID</span>';
    else                              badge = '<span class="ts-watch">&#128065; WATCH</span>';

    // FIX-10: 0% confidence → show dash, not empty bar
    const confHtml = conf > 0
        ? '<div class="conf-bar"><div class="conf-fill" style="width:' + conf + '%;background:' + confC + ';"></div></div>'
          + '<div style="font-size:7px;color:rgba(255,255,255,.3);margin-top:1px;">' + conf + '% conf</div>'
        : '<div style="font-size:7px;color:rgba(255,255,255,.15);margin-top:2px;">&mdash;</div>';

    const tooltip = (ts.reasons || []).join(' | ');
    return '<div title="' + tooltip + '">' + badge + confHtml + '</div>';
}

function continuationBadge(cont) {
    if (!cont || !cont.active) return '<span style="color:rgba(255,255,255,.1);font-size:9px;">&mdash;</span>';
    if (cont.high_conf) return '<span class="cont-hi">&#128293; ' + cont.label + '</span>';
    return '<span class="cont-low">&#8599; ' + cont.label + '</span>';
}

function trapBadge(trap) {
    if (!trap || !trap.detected) return '<span style="color:rgba(255,255,255,.1);font-size:9px;">&mdash;</span>';
    const cls = trap.type === 'CALL_TRAP' ? 'trap-call' : 'trap-put';
    return '<span class="' + cls + '" title="' + (trap.reason || '') + '">' + trap.label + '</span>';
}

function candleBodyBadge(cb) {
    if (!cb || cb.conviction === 'UNKNOWN') return '<span style="color:rgba(255,255,255,.15);font-size:8px;">&mdash;</span>';
    const isBull = cb.is_bull, isBear = cb.is_bear;
    const dir = isBull ? '&#9651;' : (isBear ? '&#9661;' : '&#9472;');
    const pct = cb.body_pct != null ? Math.round(cb.body_pct * 100) + '%' : '';
    const wick = cb.upper_wick > 0.4 ? ' <span style="color:#ff9800;font-size:7px;">UW</span>' : '';
    const map = {
        STRONG:   'cb-strong',
        MODERATE: 'cb-mod',
        WEAK:     'cb-weak',
        DOJI:     'cb-doji',
    };
    const cls = map[cb.conviction] || 'cb-weak';
    return '<span class="' + cls + '">' + dir + ' ' + pct + '</span>' + wick;
}

function next15mBadge(n) {
    if (!n) return '<span style="color:rgba(255,255,255,.15);font-size:8px;">&mdash;</span>';
    const dir = n.direction || 'SIDEWAYS';
    if (dir === 'UP')        return '<span class="n15-up">&#9651; UP</span>';
    if (dir === 'DOWN')      return '<span class="n15-down">&#9661; DOWN</span>';
    return '<span class="n15-side">&#8596; SIDE</span>';
}

function next15mConfBadge(n) {
    if (!n) return '<span style="color:rgba(255,255,255,.15);font-size:8px;">&mdash;</span>';
    const c    = n.confidence || 0;
    const lbl  = n.conf_label || 'LOW';
    const cc   = c >= 80 ? '#00e676' : c >= 60 ? '#ffc107' : c >= 40 ? '#ff9f00' : 'rgba(255,255,255,.3)';
    const tip  = (n.reason || '').replace(/'/g, '&#39;');
    return '<div title="' + tip + '">'
        + '<span style="color:' + cc + ';font-size:10px;font-weight:800;">' + c + '%</span>'
        + '<div style="font-size:7px;color:rgba(255,255,255,.3);">' + lbl + '</div>'
        + '<div class="conf-bar"><div class="conf-fill" style="width:' + c + '%;background:' + cc + ';"></div></div>'
        + '</div>';
}

// ── Main renderer ──────────────────────────────────────────────────────────
// effectiveTime = null  → single-symbol mode: render ALL candle intervals
// effectiveTime = 'H:i' → ALL-symbol mode: render only the matching interval

function renderFiltered(data, effectiveTime) {
    let rows = '', rn = 1;
    const showAll = (effectiveTime === null); // single symbol selected

    data.forEach((d, si) => {
        const z = si % 2 === 0 ? 're' : 'ro';
        const intervals = showAll
            ? (d.intervals || [])                                   // ALL candles
            : (d.intervals || []).filter(iv => iv.time === effectiveTime); // time-filtered

        intervals.forEach(iv => {
                const ce  = iv.ce  || {};
                const pe  = iv.pe  || {};
                const st  = iv.strikes || {};
                const oi  = iv.oi_sentiment || {};
                const ts  = iv.trade_signal || {};
                const cont= iv.continuation || {};
                const trap= iv.trap || {};

                rows += '<tr class="' + z + '">'

                    // Meta (7 cells)
                    + '<td class="cn">'   + rn++ + '</td>'
                    + '<td class="ct">'   + iv.time + '</td>'
                    + '<td>'              + tzBadge(iv.time_zone) + '</td>'
                    + '<td>'              + regimeBadge(iv.regime) + '</td>'
                    + '<td>'              + candleBodyBadge(iv.candle_body) + '</td>'
                    + '<td>'              + priceDirBadge(iv.price_dir) + (iv.future_price ? '<br><span style="font-size:8px;color:rgba(255,255,255,.25);">' + ni(iv.future_price) + '</span>' : '') + '</td>'
                    + '<td><span class="bsym">' + d.symbol + '</span><br><span style="font-size:8px;color:rgba(255,165,2,.7);">&#8377;' + ni(iv.atm_strike) + '</span></td>'

                    // ATM (2 cells)
                    + '<td class="sl-atm bg-atm">'  + vsBadge(ce.atm, null) + '</td>'
                    + '<td class="bg-atm sl-inner">' + vsBadge(pe.atm, null) + '</td>'

                    // ±1 (4 cells) — ±2 removed
                    + '<td class="sl-pm1 bg-pm1">'  + vsBadge(ce.m1, st.m1) + '</td>'
                    + '<td class="bg-pm1 sl-inner">' + vsBadge(ce.p1, st.p1) + '</td>'
                    + '<td class="bg-pm1 sl-inner">' + vsBadge(pe.m1, st.m1) + '</td>'
                    + '<td class="bg-pm1 sl-inner">' + vsBadge(pe.p1, st.p1) + '</td>'

                    // Weighted Final (2 cells)
                    + '<td class="sl-final bg-final">' + vsBadge(iv.final_ce, null) + '</td>'
                    + '<td class="bg-final sl-inner">'  + vsBadge(iv.final_pe, null) + '</td>'

                    // Dominance ratio (2 cells)
                    + '<td class="sl-dom bg-dom">' + dominanceRatioBadge(iv.dominance_ratio, iv.dominance_bias) + '</td>'
                    + '<td class="bg-dom">'        + dominanceBiasBadge(iv.dominance_bias, oi.domConflict) + '</td>'

                    // OI Sentiment (4 cells)
                    + '<td class="sl-oi bg-oi" title="' + (oi.condition||'') + ' | ' + (oi.reason||'') + '">' + oiBadge(oi.signal) + '</td>'
                    + '<td class="bg-oi">' + pctCell(oi.ce_oi_pct) + '</td>'
                    + '<td class="bg-oi">' + pctCell(oi.pe_oi_pct) + '</td>'
                    + '<td class="bg-oi">' + strBadge(oi.strength)  + '</td>'

                    // Trade Signal (3 cells)
                    + '<td class="sl-trade bg-trade">' + tradeActionBadge(ts)    + '</td>'
                    + '<td class="bg-trade">'           + continuationBadge(cont) + '</td>'
                    + '<td class="bg-trade">'           + trapBadge(trap)         + '</td>'

                    // Next 15m trend (2 cells)
                    + '<td class="sl-next bg-next">' + next15mBadge(iv.next_15m_trend)     + '</td>'
                    + '<td class="bg-next">'          + next15mConfBadge(iv.next_15m_trend) + '</td>'

                    + '</tr>';

            });
    });

    const matched = rows ? (rows.match(/<tr /g) || []).length : 0;
    if (showAll) {
        const sym = data.length ? data[0].symbol : '—';
        document.getElementById('row-count').textContent =
            matched + ' candle(s) — all intervals for ' + sym;
    } else {
        document.getElementById('row-count').textContent =
            matched + ' row(s) at ' + effectiveTime + ' across ' + data.length + ' symbol(s)';
    }

    if (!rows) {
        const msg = showAll ? 'No candle data found for this symbol.' : 'No data found for ' + effectiveTime + '.';
        rows = '<tr><td colspan="' + COLS + '"><div class="no-data">' + msg + '</div></td></tr>';
    }
    $('#vt-body').html(rows);
}

function ni(v) {
    if (v == null || v === '' || v === undefined) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}
</script>
@endpush