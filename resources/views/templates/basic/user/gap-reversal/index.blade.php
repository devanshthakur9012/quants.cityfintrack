{{-- FILE: resources/views/themes/{active_theme}/user/gap-reversal/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
    --c-bg:#0B0E11;--c-surface:#131722;--c-panel:#1C2030;
    --c-border:rgba(255,255,255,.06);--c-border2:rgba(255,255,255,.11);
    --c-lime:#7DFF00;--c-lime-dim:rgba(125,255,0,.1);
    --c-blue:#00B8D4;--c-red:#EF5350;--c-teal:#26A69A;
    --c-amber:#FFA726;--c-purple:#AB47BC;
    --c-text:#D1D4DC;--c-muted:#787B86;--c-faint:rgba(255,255,255,.03);
    --f-sans:'DM Sans',system-ui,sans-serif;
    --f-display:'Syne',sans-serif;--f-mono:'Space Grotesk',monospace;
}
.gr-wrap{font-family:var(--f-sans);color:var(--c-text);background:var(--c-bg);}
.gr-wrap *{box-sizing:border-box;}
.gr-wrap a{text-decoration:none;color:inherit;}
.mono{font-family:var(--f-mono);}
@keyframes grUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.gr-anim{animation:grUp .5s ease both;}
.gr-anim.d1{animation-delay:.08s;}.gr-anim.d2{animation-delay:.16s;}
@keyframes grSpin{to{transform:rotate(360deg);}}

/* HERO */
.gr-hero{position:relative;overflow:hidden;background:var(--c-bg);border-bottom:1px solid var(--c-border);padding:36px 32px;display:flex;align-items:center;justify-content:space-between;gap:24px;}
.gr-hero::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(125,255,0,.022) 1px,transparent 1px),linear-gradient(90deg,rgba(125,255,0,.022) 1px,transparent 1px);background-size:56px 56px;mask-image:radial-gradient(ellipse 80% 80% at 20% 50%,black,transparent);pointer-events:none;}
.gr-hero::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 35% 70% at 5% 50%,rgba(125,255,0,.04),transparent 70%);pointer-events:none;}
.gr-hero-left{position:relative;z-index:1;}
.gr-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--c-lime);margin-bottom:10px;}
.gr-eyebrow::before{content:'';display:block;width:16px;height:1px;background:var(--c-lime);}
.gr-hero h1{font-family:var(--f-display);font-size:clamp(22px,3.5vw,36px);font-weight:800;color:#fff;line-height:1.1;letter-spacing:-.015em;margin-bottom:10px;}
.gr-hero h1 span{color:var(--c-lime);}
.gr-hero p{font-size:13px;color:var(--c-muted);line-height:1.7;max-width:640px;}
.gr-hero-icon{position:relative;z-index:1;width:72px;height:72px;border-radius:12px;background:var(--c-surface);border:1px solid var(--c-border2);display:flex;align-items:center;justify-content:center;font-size:30px;color:var(--c-lime);flex-shrink:0;box-shadow:0 0 24px rgba(125,255,0,.1);}
@media(max-width:768px){.gr-hero{flex-direction:column;padding:24px 18px;}.gr-hero-icon{display:none;}}

/* FILTER BAR */
.gr-filter-bar{background:var(--c-surface);border-bottom:1px solid var(--c-border);padding:0 32px;position:sticky;top:0;z-index:200;box-shadow:0 4px 24px rgba(0,0,0,.3);}
.gr-filter-inner{display:flex;align-items:center;gap:12px;padding:11px 0;flex-wrap:wrap;}
.gr-filter-label{font-size:10px;color:var(--c-muted);font-weight:700;text-transform:uppercase;letter-spacing:.1em;font-family:var(--f-mono);flex-shrink:0;}
.gr-sep{width:1px;height:26px;background:var(--c-border2);flex-shrink:0;}
.gr-select,.gr-date-input{background:var(--c-panel);border:1px solid var(--c-border2);border-radius:7px;font-family:var(--f-mono);font-size:12px;font-weight:600;color:var(--c-text);outline:none;padding:6px 28px 6px 11px;transition:border-color .2s;appearance:none;cursor:pointer;min-width:130px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
.gr-date-input{min-width:auto;padding:6px 10px;background-image:none;}
.gr-date-input::-webkit-calendar-picker-indicator{filter:invert(1) opacity(.4);cursor:pointer;}
.gr-select:focus,.gr-date-input:focus{border-color:rgba(125,255,0,.45);}
.gr-date-wrap{display:flex;align-items:center;gap:4px;}
.gr-date-nav{width:28px;height:30px;background:var(--c-panel);border:1px solid var(--c-border2);border-radius:6px;color:var(--c-muted);cursor:pointer;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;transition:all .2s;font-family:var(--f-sans);}
.gr-date-nav:hover{border-color:rgba(125,255,0,.3);color:var(--c-lime);}
.gr-today-btn{width:auto;padding:0 10px;font-size:9px;font-family:var(--f-mono);font-weight:700;letter-spacing:.1em;}
.gr-live-badge{background:rgba(38,166,154,.12);color:#4DB6AC;border:1px solid rgba(38,166,154,.25);border-radius:100px;font-size:10px;font-weight:700;padding:2px 9px;font-family:var(--f-mono);}
.gr-hist-badge{background:rgba(255,167,38,.1);color:var(--c-amber);border:1px solid rgba(255,167,38,.25);border-radius:100px;font-size:10px;font-weight:700;padding:2px 9px;font-family:var(--f-mono);}
.gr-analyze-btn{background:var(--c-lime);color:#000;border:none;border-radius:7px;padding:7px 20px;font-family:var(--f-display);font-size:12px;font-weight:700;letter-spacing:.06em;cursor:pointer;transition:all .2s;box-shadow:0 0 14px rgba(125,255,0,.2);display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.gr-analyze-btn:hover{background:#8FFF1A;box-shadow:0 0 22px rgba(125,255,0,.35);transform:translateY(-1px);}
.gr-reset-btn{background:var(--c-panel);border:1px solid var(--c-border2);color:var(--c-muted);border-radius:7px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--f-sans);transition:all .2s;}
.gr-reset-btn:hover{color:var(--c-text);}
.gr-filter-right{margin-left:auto;display:flex;align-items:center;gap:10px;}
.gr-info-text{font-size:10px;color:var(--c-muted);font-family:var(--f-mono);}
.gr-upd-text{font-size:10px;color:rgba(120,123,134,.45);font-family:var(--f-mono);}
@media(max-width:768px){.gr-filter-bar{padding:0 16px;}.gr-filter-inner{gap:8px;}.gr-filter-right{margin-left:0;width:100%;}}

/* CONTENT */
.gr-content{padding:24px 32px 64px;}
@media(max-width:768px){.gr-content{padding:16px 12px 48px;}}
.gr-warn{background:rgba(255,167,38,.08);border:1px solid rgba(255,167,38,.25);border-radius:9px;padding:14px 18px;margin-bottom:18px;display:none;align-items:center;gap:12px;font-size:13px;color:var(--c-amber);}
.gr-warn.show{display:flex;} .gr-warn i{font-size:18px;flex-shrink:0;} .gr-warn strong{color:#fff;}

/* LEGEND (score weights) */
.gr-legend{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;}
.gr-legend-chip{background:var(--c-surface);border:1px solid var(--c-border);border-radius:100px;padding:5px 12px;font-size:10px;font-family:var(--f-mono);color:var(--c-muted);display:flex;align-items:center;gap:6px;}
.gr-legend-chip b{color:var(--c-lime);font-weight:700;}

/* STATS */
.gr-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;}
@media(max-width:900px){.gr-stats{grid-template-columns:repeat(3,1fr);}}
@media(max-width:500px){.gr-stats{grid-template-columns:repeat(2,1fr);}}
.gr-stat-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:14px 16px;position:relative;overflow:hidden;transition:border-color .25s;}
.gr-stat-card::after{content:'';position:absolute;top:10px;bottom:10px;left:0;width:2px;border-radius:0 2px 2px 0;}
.gr-stat-card.s-total::after{background:var(--c-blue);}
.gr-stat-card.s-buy::after{background:var(--c-teal);}
.gr-stat-card.s-sell::after{background:var(--c-red);}
.gr-stat-card.s-wait::after{background:var(--c-amber);}
.gr-stat-card.s-avg::after{background:var(--c-purple);}
.gr-stat-card:hover{border-color:var(--c-border2);}
.gr-stat-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--c-muted);margin-bottom:6px;font-family:var(--f-mono);}
.gr-stat-val{font-family:var(--f-display);font-size:24px;font-weight:800;color:#fff;}
.s-buy .gr-stat-val{color:#80CBC4;} .s-sell .gr-stat-val{color:#EF9A9A;}
.s-wait .gr-stat-val{color:var(--c-amber);} .s-avg .gr-stat-val{color:var(--c-purple);}

/* TABLE CARD */
.gr-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;overflow:hidden;position:relative;}
.gr-card::before{content:'';position:absolute;top:0;left:16px;right:16px;height:1px;background:linear-gradient(90deg,transparent,var(--c-lime),transparent);opacity:.3;}
.gr-card-header{padding:13px 18px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;background:rgba(0,0,0,.2);}
.gr-card-title{font-family:var(--f-display);font-size:14px;font-weight:700;color:var(--c-text);}
.gr-card-subtitle{font-size:10px;color:var(--c-muted);font-family:var(--f-mono);}
.gr-tscroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}

/* TABLE */
.gr-table{width:100%;border-collapse:collapse;font-family:var(--f-mono);min-width:1500px;}
.gr-table thead tr.th-group th{padding:8px 10px 4px;text-align:center;font-family:var(--f-sans);font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--c-panel);border-bottom:none;white-space:nowrap;}
.gr-table thead tr.th-cols th{padding:4px 10px 8px;text-align:center;font-family:var(--f-mono);font-size:9px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;background:rgba(0,0,0,.25);color:var(--c-muted);border-bottom:1px solid var(--c-border);white-space:nowrap;}
.g-info{color:var(--c-blue)!important;} .g-price{color:var(--c-amber)!important;} .g-oi{color:var(--c-teal)!important;} .g-pivot{color:var(--c-purple)!important;} .g-signal{color:#fff!important;}
.sep-price{border-left:1px solid rgba(255,167,38,.15)!important;} .sep-oi{border-left:1px solid rgba(38,166,154,.15)!important;} .sep-pivot{border-left:1px solid rgba(171,71,188,.15)!important;} .sep-signal{border-left:1px solid rgba(255,255,255,.15)!important;}
.gr-table tbody td{padding:8px 10px;text-align:center;font-size:11px;border-bottom:1px solid var(--c-border);vertical-align:middle;white-space:nowrap;color:var(--c-muted);transition:background .15s;}
.gr-table tbody tr:hover td{background:rgba(255,255,255,.02)!important;}
.tr-even{background:var(--c-surface);} .tr-odd{background:rgba(0,0,0,.15);}
.tr-buy{background:rgba(38,166,154,.04)!important;} .tr-sell{background:rgba(239,83,80,.04)!important;}
.c-num{font-size:9px;color:rgba(120,123,134,.35);} .c-date{font-size:11px;font-weight:700;color:var(--c-lime);}
.c-sym{font-size:12px;font-weight:800;color:var(--c-blue);} .c-atm{font-size:10px;color:var(--c-amber);font-weight:700;}
.c-fut{font-size:10px;color:var(--c-blue);} .c-expiry{font-size:10px;color:var(--c-muted);}
.c-oi{font-size:11px;font-weight:700;color:var(--c-text);}
.pct-up{color:#80CBC4;font-weight:700;} .pct-down{color:#EF9A9A;font-weight:700;} .pct-neu{color:var(--c-muted);}
/* condition ok/fail chips */
.chip-ok{display:inline-flex;align-items:center;gap:4px;background:rgba(38,166,154,.1);color:#4DB6AC;border:1px solid rgba(38,166,154,.25);border-radius:5px;padding:3px 8px;font-family:var(--f-sans);font-size:9px;font-weight:800;}
.chip-fail{display:inline-flex;align-items:center;gap:4px;background:rgba(239,83,80,.08);color:#EF9A9A;border:1px solid rgba(239,83,80,.2);border-radius:5px;padding:3px 8px;font-family:var(--f-sans);font-size:9px;font-weight:700;}
/* gap badges */
.gap-down{display:inline-block;background:rgba(239,83,80,.1);color:#EF9A9A;border:1px solid rgba(239,83,80,.25);border-radius:5px;padding:3px 9px;font-family:var(--f-sans);font-size:9px;font-weight:800;}
.gap-up{display:inline-block;background:rgba(38,166,154,.1);color:#4DB6AC;border:1px solid rgba(38,166,154,.25);border-radius:5px;padding:3px 9px;font-family:var(--f-sans);font-size:9px;font-weight:800;}
/* setup badges */
.sig-buy{display:inline-block;background:rgba(38,166,154,.14);color:#4DB6AC;border:1px solid rgba(38,166,154,.35);border-radius:5px;padding:4px 12px;font-family:var(--f-display);font-size:11px;font-weight:800;}
.sig-sell{display:inline-block;background:rgba(239,83,80,.12);color:#EF9A9A;border:1px solid rgba(239,83,80,.35);border-radius:5px;padding:4px 12px;font-family:var(--f-display);font-size:11px;font-weight:800;}
.sig-wait{display:inline-block;background:var(--c-panel);color:var(--c-muted);border:1px solid var(--c-border2);border-radius:5px;padding:4px 12px;font-family:var(--f-display);font-size:11px;}
/* score bar */
.score-wrap{display:flex;flex-direction:column;align-items:center;gap:3px;min-width:64px;}
.score-track{width:60px;height:5px;border-radius:3px;background:var(--c-panel);overflow:hidden;}
.score-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--c-red),var(--c-amber),var(--c-lime));}
.score-txt{font-size:10px;font-weight:800;color:#fff;font-family:var(--f-display);}
/* pivot zone tag */
.pivot-tag{display:inline-flex;flex-direction:column;gap:2px;font-size:9px;}
.pivot-tag .lvl{color:var(--c-text);font-weight:700;}
.pivot-tag .touch-yes{color:var(--c-lime);}
.pivot-tag .touch-no{color:rgba(120,123,134,.5);}
/* trend tag */
.trend-buildup{color:var(--c-teal);font-weight:700;}
.trend-unwind{color:var(--c-red);font-weight:700;}
.trend-flat{color:var(--c-muted);}
/* reason tooltip cell */
.c-reason{max-width:220px;white-space:normal!important;text-align:left!important;font-size:10px;color:var(--c-muted);line-height:1.5;}
/* loading / empty */
.gr-empty{text-align:center;padding:52px 20px;color:var(--c-muted);}
.gr-empty-icon{width:52px;height:52px;border-radius:50%;background:var(--c-panel);border:1px solid var(--c-border);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:20px;}
.gr-empty p{font-size:12px;font-family:var(--f-mono);margin-top:4px;}
.gr-spinner-row{display:flex;align-items:center;justify-content:center;gap:12px;padding:52px;color:var(--c-muted);font-size:12px;font-family:var(--f-mono);}
.gr-spinner{width:28px;height:28px;border:2px solid var(--c-border2);border-top:2px solid var(--c-lime);border-radius:50%;animation:grSpin .9s linear infinite;flex-shrink:0;}
</style>

<div class="gr-wrap">

<div class="gr-hero gr-anim">
    <div class="gr-hero-left">
        <div class="gr-eyebrow">Options Analytics</div>
        <h1>Gap Reversal <span>Strategy</span> Analyzer</h1>
        <p>Detects gap-day reversal setups — gap down/up, initial selloff/rally, higher low/lower high, CE/PE OI confirmation, option flow reversal and range breakout/breakdown — scored out of 100.</p>
    </div>
    <div class="gr-hero-icon"><i class="las la-random"></i></div>
</div>

<div class="gr-filter-bar">
    <div class="gr-filter-inner">
        <span class="gr-filter-label">Symbol</span>
        <select id="gr-sym" class="gr-select" onchange="grAnalyze()"><option value="ALL">— All —</option></select>
        <div class="gr-sep"></div>
        <span class="gr-filter-label">Date</span>
        <div class="gr-date-wrap">
            <button class="gr-date-nav" onclick="grShiftDate(-1)">‹</button>
            <input type="date" id="gr-date" class="gr-date-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="grAnalyze()">
            <button class="gr-date-nav" onclick="grShiftDate(1)">›</button>
            <button class="gr-date-nav gr-today-btn" onclick="grGoToday()">TODAY</button>
            <span id="gr-date-badge"></span>
        </div>
        <div class="gr-sep"></div>
        <span class="gr-filter-label">Setup</span>
        <select id="gr-setup" class="gr-select" onchange="grAnalyze()">
            <option value="">All Setups</option>
            <option value="BUY">BUY (CE) Only</option>
            <option value="SELL">SELL (PE) Only</option>
        </select>
        <button class="gr-analyze-btn" onclick="grAnalyze()"><i class="las la-search"></i> Analyze</button>
        <button class="gr-reset-btn" onclick="grReset()">↺ Reset</button>
        <div class="gr-filter-right">
            <span class="gr-info-text" id="gr-info"></span>
            <span class="gr-upd-text"  id="gr-upd"></span>
        </div>
    </div>
</div>

<div class="gr-content">
    <div class="gr-warn" id="gr-warn"><i class="las la-exclamation-triangle"></i><div><strong>No Analysis Config Found</strong><div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="gr-warn-msg">Go to Admin → Analysis Config and create a config with symbols.</div></div></div>

    <div class="gr-legend gr-anim">
        <span class="gr-legend-chip">Gap <b>+20</b></span>
        <span class="gr-legend-chip">Initial Move <b>+10</b></span>
        <span class="gr-legend-chip">Higher Low / Lower High <b>+20</b></span>
        <span class="gr-legend-chip">OI Confirmation <b>+20</b></span>
        <span class="gr-legend-chip">Option Reversal <b>+15</b></span>
        <span class="gr-legend-chip">Range Break <b>+10</b></span>
        <span class="gr-legend-chip">Volume <b>+5</b></span>
        <span class="gr-legend-chip" style="border-color:rgba(125,255,0,.3);">Total <b>100</b> — all six mandatory to fire BUY/SELL</span>
    </div>

    <div class="gr-stats gr-anim">
        <div class="gr-stat-card s-total"><div class="gr-stat-label">Total</div><div class="gr-stat-val" id="st-total">—</div></div>
        <div class="gr-stat-card s-buy">  <div class="gr-stat-label">BUY (CE)</div><div class="gr-stat-val" id="st-buy">—</div></div>
        <div class="gr-stat-card s-sell"> <div class="gr-stat-label">SELL (PE)</div><div class="gr-stat-val" id="st-sell">—</div></div>
        <div class="gr-stat-card s-wait"> <div class="gr-stat-label">WAIT</div><div class="gr-stat-val" id="st-wait">—</div></div>
        <div class="gr-stat-card s-avg">  <div class="gr-stat-label">Avg Score</div><div class="gr-stat-val" id="st-avg">—</div></div>
    </div>

    <div class="gr-card gr-anim d1">
        <div class="gr-card-header">
            <div class="gr-card-title">⤳ Gap Reversal Setups</div>
            <span class="gr-card-subtitle" id="gr-subtitle">Detecting last available date…</span>
        </div>
        <div class="gr-tscroll">
            <table class="gr-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="5" class="g-info">Market Info</th>
                        <th colspan="4" class="g-price sep-price">Price Action</th>
                        <th colspan="4" class="g-oi sep-oi">OI Confirmation</th>
                        <th colspan="2" class="g-pivot sep-pivot">Pivot Zones</th>
                        <th colspan="2" class="g-signal sep-signal">Signal</th>
                    </tr>
                    <tr class="th-cols">
                        <th>#</th><th>Date</th><th>Symbol</th><th>ATM / FUT</th><th>Expiry</th>
                        <th class="sep-price">Gap %</th>
                        <th>Initial Move</th>
                        <th>Reversal</th>
                        <th>Range Break</th>
                        <th class="sep-oi">CE% / PE%</th>
                        <th>OI Confirm</th>
                        <th>Prev Trend</th>
                        <th>Option Reversal</th>
                        <th class="sep-pivot">CE Zone</th>
                        <th>PE Zone</th>
                        <th class="sep-signal">Score</th>
                        <th>Setup</th>
                    </tr>
                </thead>
                <tbody id="gr-tbody">
                    <tr><td colspan="17"><div class="gr-spinner-row"><div class="gr-spinner"></div>Detecting last available date…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

@endsection
@push('script')
<script>
/* ═══ Gap Reversal Strategy Analyzer — JS ═══ */
var GR_ANALYZE='{{route("gap-reversal.analyze")}}',GR_SYMBOLS='{{route("gap-reversal.symbols")}}',GR_LASTDATE='{{route("gap-reversal.last.date")}}',GR_TODAY='{{now()->toDateString()}}';
var grSymCache=null;
function el(id){return document.getElementById(id);}
function html(id,h){var e=el(id);if(e)e.innerHTML=h;}
function txt(id,t){var e=el(id);if(e)e.textContent=t;}
document.addEventListener('DOMContentLoaded',function(){grResolveLastDateAndLoad();});
function grResolveLastDateAndLoad(){fetch(GR_LASTDATE,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){if(res.last_date)el('gr-date').value=res.last_date;grLoadSymbols(function(){grAnalyze();});}).catch(function(){grLoadSymbols(function(){grAnalyze();});});}
function grGetDate(){return el('gr-date').value;}
function grShiftDate(d){var p=el('gr-date'),dt=new Date(p.value);dt.setDate(dt.getDate()+d);var s=dt.toISOString().split('T')[0];if(s>GR_TODAY)return;p.value=s;grAnalyze();}
function grGoToday(){el('gr-date').value=GR_TODAY;grAnalyze();}
function grUpdateDateBadge(isToday){el('gr-date-badge').innerHTML=isToday?'<span class="gr-live-badge">● Live</span>':'<span class="gr-hist-badge">📅 Historical</span>';}
function grLoadSymbols(callback){if(grSymCache!==null){grRebuildSym(grSymCache);if(callback)callback();return;}fetch(GR_SYMBOLS,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){if(res.no_config){grShowWarn(res.message||'');grSymCache=[];grRebuildSym([]);}else{grHideWarn();grSymCache=res.symbols||[];grRebuildSym(grSymCache);}if(callback)callback();}).catch(function(){if(callback)callback();});}
function grRebuildSym(syms){var sel=el('gr-sym'),prev=sel.value,opts='<option value="ALL">— All Symbols —</option>';syms.forEach(function(s){opts+='<option value="'+s+'"'+(s===prev?' selected':'')+'>'+s+'</option>';});sel.innerHTML=opts;if(prev&&prev!=='ALL'){sel.value=prev;if(sel.value!==prev)sel.value='ALL';}}
function grAnalyze(){var date=grGetDate(),setupF=el('gr-setup').value,sym=el('gr-sym').value;if(!date)return;grHideWarn();grResetStats();html('gr-tbody','<tr><td colspan="17"><div class="gr-spinner-row"><div class="gr-spinner"></div>Scanning gap reversal setups for '+date+'…</div></td></tr>');txt('gr-subtitle',date+' · Loading…');var params=new URLSearchParams({date:date,filter_setup:setupF});if(sym&&sym!=='ALL')params.append('symbols[]',sym);fetch(GR_ANALYZE+'?'+params.toString(),{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){if(!r.ok)throw new Error('Server error '+r.status);return r.json();}).then(function(res){if(typeof res.is_today!=='undefined')grUpdateDateBadge(res.is_today);if(res.available_symbols&&res.available_symbols.length){grSymCache=res.available_symbols;grRebuildSym(grSymCache);if(sym&&sym!=='ALL')el('gr-sym').value=sym;}if(res.no_config){grShowWarn(res.message);grEmptyTable('No active config.');return;}if(!res.success||!res.data||!res.data.length){grEmptyTable(res.message||'No setups found for this date.');grResetStats();txt('gr-subtitle',date+' · No data found');return;}grUpdateStats(res);grRenderTable(res.data);el('gr-info').innerHTML='<span style="color:#80CBC4;">BUY: '+res.buy_count+'</span> &nbsp;·&nbsp; <span style="color:#EF9A9A;">SELL: '+res.sell_count+'</span>';txt('gr-subtitle',date+' · '+res.message);txt('gr-upd','Updated '+new Date().toLocaleTimeString());}).catch(function(err){grEmptyTable('⚠ '+err.message);});}
function grRenderTable(data){var h='',num=1;data.forEach(function(r,i){var isBuy=r.setup==='BUY',isSell=r.setup==='SELL';var rowCls=(isBuy?'tr-buy':isSell?'tr-sell':'');var zebra=i%2===0?'tr-even':'tr-odd';
var setupBadge=isBuy?'<span class="sig-buy">▲ BUY CE</span>':isSell?'<span class="sig-sell">▼ BUY PE</span>':'<span class="sig-wait">⏸ WAIT</span>';
var gapBadge=r.gap_type==='GAP DOWN'?'<span class="gap-down">'+r.gap_type+'</span>':r.gap_type==='GAP UP'?'<span class="gap-up">'+r.gap_type+'</span>':'<span class="pct-neu">'+r.gap_type+'</span>';
var initChip=chip(r.initial_ok, (r.gap_type==='GAP DOWN'?'Selloff ':'Rally ')+(r.initial_move_pct>0?'+':'')+r.initial_move_pct+'%');
var revChip=chip(r.reversal_ok, r.reversal_type);
var rangeChip=chip(r.range_break_ok, r.range_break_type);
var oiConfirmChip=chip(r.oi_confirm_ok, r.oi_sentiment);
var optRevChip=chip(r.option_reversal_ok, r.option_reversal_ok?'Reversed':'No Flip');
var prevTrend='<span class="'+trendCls(r.prev_ce_trend)+'">CE:'+r.prev_ce_trend+'</span><br><span class="'+trendCls(r.prev_pe_trend)+'">PE:'+r.prev_pe_trend+'</span>';
var ceZone=pivotTag(r.pivot_note?r.pivot_note.ce_zone:null);
var peZone=pivotTag(r.pivot_note?r.pivot_note.pe_zone:null);
var scoreCol='<div class="score-wrap"><div class="score-track"><div class="score-fill" style="width:'+r.score+'%;"></div></div><span class="score-txt">'+r.score+'/100</span></div>';
h+='<tr class="'+rowCls+' '+zebra+'" title="'+esc(r.reason)+'">'
+'<td class="c-num">'+num+++'</td>'
+'<td class="c-date">'+r.date+'</td>'
+'<td class="c-sym">'+esc(r.symbol)+'</td>'
+'<td>'+(r.atm_strike?'<span class="c-atm">₹'+nInt(r.atm_strike)+'</span>':'—')+(r.fut_price?'<br><span class="c-fut">F:₹'+f(r.fut_price)+'</span>':'')+'</td>'
+'<td class="c-expiry">'+(r.expiry||'—')+'</td>'
+'<td class="sep-price">'+gapBadge+'<br>'+pctCell(r.gap_pct)+'</td>'
+'<td>'+initChip+'</td>'
+'<td>'+revChip+'</td>'
+'<td>'+rangeChip+'</td>'
+'<td class="sep-oi">'+pctCell(r.ce_oi_pct)+' / '+pctCell(r.pe_oi_pct)+'</td>'
+'<td>'+oiConfirmChip+'</td>'
+'<td>'+prevTrend+'</td>'
+'<td>'+optRevChip+'</td>'
+'<td class="sep-pivot">'+ceZone+'</td>'
+'<td>'+peZone+'</td>'
+'<td class="sep-signal">'+scoreCol+'</td>'
+'<td>'+setupBadge+'</td>'
+'</tr>';});html('gr-tbody',h||grEmptyHtml('No results.'));}
function chip(ok,label){return ok?'<span class="chip-ok">✓ '+esc(label)+'</span>':'<span class="chip-fail">✗ '+esc(label)+'</span>';}
function trendCls(t){return t==='Long Buildup'?'trend-buildup':t==='Unwinding'?'trend-unwind':'trend-flat';}
function pivotTag(z){if(!z)return'<span class="pct-neu">—</span>';return'<div class="pivot-tag"><span class="lvl">'+z.level+'</span><span class="'+(z.touched?'touch-yes':'touch-no')+'">'+(z.touched?'✓ Touched':'Not touched')+'</span></div>';}
function grUpdateStats(res){txt('st-total',res.total_records||'0');txt('st-buy',res.buy_count||'0');txt('st-sell',res.sell_count||'0');txt('st-wait',res.wait_count||'0');var avg=0;if(res.data&&res.data.length){var sum=0;res.data.forEach(function(r){sum+=r.score||0;});avg=Math.round(sum/res.data.length);}txt('st-avg',res.data&&res.data.length?avg:'—');}
function grResetStats(){['st-total','st-buy','st-sell','st-wait','st-avg'].forEach(function(id){txt(id,'—');});}
function grShowWarn(msg){el('gr-warn').classList.add('show');txt('gr-warn-msg',msg||'');}
function grHideWarn(){el('gr-warn').classList.remove('show');}
function grEmptyTable(msg){html('gr-tbody',grEmptyHtml(msg));}
function grEmptyHtml(msg){return'<tr><td colspan="17"><div class="gr-empty"><div class="gr-empty-icon"><i class="las la-chart-line"></i></div><p>'+(msg||'No data found.')+'</p></div></td></tr>';}
function grReset(){fetch(GR_LASTDATE,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){el('gr-date').value=res.last_date||GR_TODAY;el('gr-setup').value='';el('gr-sym').value='ALL';grHideWarn();grAnalyze();}).catch(function(){el('gr-date').value=GR_TODAY;el('gr-setup').value='';el('gr-sym').value='ALL';grHideWarn();grAnalyze();});}
function pctCell(v){if(v==null)return'<span class="pct-neu">—</span>';var n=parseFloat(v)||0,cls=n>0?'pct-up':n<0?'pct-down':'pct-neu';return'<span class="'+cls+'">'+(n>0?'+':'')+n.toFixed(2)+'%</span>';}
function f(v){return parseFloat(v||0).toFixed(2);}
function nInt(v){var n=Number(v)||0;if(n>=1e7)return(n/1e7).toFixed(2)+'Cr';if(n>=1e5)return(n/1e5).toFixed(2)+'L';if(n>=1e3)return(n/1e3).toFixed(1)+'K';return n.toLocaleString('en-IN');}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
</script>
@endpush