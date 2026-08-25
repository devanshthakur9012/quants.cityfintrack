{{-- FILE: resources/views/themes/{active_theme}/user/oi-flow-multi-time/index.blade.php --}}
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
.omt-wrap{font-family:var(--f-sans);color:var(--c-text);background:var(--c-bg);}
.omt-wrap *{box-sizing:border-box;}
.omt-wrap a{text-decoration:none;color:inherit;}
.mono{font-family:var(--f-mono);}
@keyframes omtUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.omt-anim{animation:omtUp .5s ease both;}
.omt-anim.d1{animation-delay:.08s;}.omt-anim.d2{animation-delay:.16s;}
@keyframes omtSpin{to{transform:rotate(360deg);}}

/* HERO */
.omt-hero{position:relative;overflow:hidden;background:var(--c-bg);border-bottom:1px solid var(--c-border);padding:36px 32px;display:flex;align-items:center;justify-content:space-between;gap:24px;}
.omt-hero::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(125,255,0,.022) 1px,transparent 1px),linear-gradient(90deg,rgba(125,255,0,.022) 1px,transparent 1px);background-size:56px 56px;mask-image:radial-gradient(ellipse 80% 80% at 20% 50%,black,transparent);pointer-events:none;}
.omt-hero::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 35% 70% at 5% 50%,rgba(125,255,0,.04),transparent 70%);pointer-events:none;}
.omt-hero-left{position:relative;z-index:1;}
.omt-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--c-lime);margin-bottom:10px;}
.omt-eyebrow::before{content:'';display:block;width:16px;height:1px;background:var(--c-lime);}
.omt-hero h1{font-family:var(--f-display);font-size:clamp(22px,3.5vw,36px);font-weight:800;color:#fff;line-height:1.1;letter-spacing:-.015em;margin-bottom:10px;}
.omt-hero h1 span{color:var(--c-lime);}
.omt-hero p{font-size:13px;color:var(--c-muted);line-height:1.7;max-width:680px;}
.omt-hero-icon{position:relative;z-index:1;width:72px;height:72px;border-radius:12px;background:var(--c-surface);border:1px solid var(--c-border2);display:flex;align-items:center;justify-content:center;font-size:30px;color:var(--c-lime);flex-shrink:0;box-shadow:0 0 24px rgba(125,255,0,.1);}
@media(max-width:768px){.omt-hero{flex-direction:column;padding:24px 18px;}.omt-hero-icon{display:none;}}

/* FILTER BAR */
.omt-filter-bar{background:var(--c-surface);border-bottom:1px solid var(--c-border);padding:0 32px;position:sticky;top:0;z-index:200;box-shadow:0 4px 24px rgba(0,0,0,.3);}
.omt-filter-inner{display:flex;align-items:center;gap:12px;padding:11px 0;flex-wrap:wrap;}
.omt-filter-label{font-size:10px;color:var(--c-muted);font-weight:700;text-transform:uppercase;letter-spacing:.1em;font-family:var(--f-mono);flex-shrink:0;}
.omt-sep{width:1px;height:26px;background:var(--c-border2);flex-shrink:0;}
.omt-select,.omt-date-input{background:var(--c-panel);border:1px solid var(--c-border2);border-radius:7px;font-family:var(--f-mono);font-size:12px;font-weight:600;color:var(--c-text);outline:none;padding:6px 28px 6px 11px;transition:border-color .2s;appearance:none;cursor:pointer;min-width:130px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
.omt-date-input{min-width:auto;padding:6px 10px;background-image:none;}
.omt-date-input::-webkit-calendar-picker-indicator{filter:invert(1) opacity(.4);cursor:pointer;}
.omt-select:focus,.omt-date-input:focus{border-color:rgba(125,255,0,.45);}
.omt-date-wrap{display:flex;align-items:center;gap:4px;}
.omt-date-nav{width:28px;height:30px;background:var(--c-panel);border:1px solid var(--c-border2);border-radius:6px;color:var(--c-muted);cursor:pointer;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;transition:all .2s;font-family:var(--f-sans);}
.omt-date-nav:hover{border-color:rgba(125,255,0,.3);color:var(--c-lime);}
.omt-today-btn{width:auto;padding:0 10px;font-size:9px;font-family:var(--f-mono);font-weight:700;letter-spacing:.1em;}
.omt-live-badge{background:rgba(38,166,154,.12);color:#4DB6AC;border:1px solid rgba(38,166,154,.25);border-radius:100px;font-size:10px;font-weight:700;padding:2px 9px;font-family:var(--f-mono);}
.omt-hist-badge{background:rgba(255,167,38,.1);color:var(--c-amber);border:1px solid rgba(255,167,38,.25);border-radius:100px;font-size:10px;font-weight:700;padding:2px 9px;font-family:var(--f-mono);}
.omt-range-badge{background:rgba(171,71,188,.12);color:#CE93D8;border:1px solid rgba(171,71,188,.3);border-radius:100px;font-size:10px;font-weight:700;padding:2px 9px;font-family:var(--f-mono);}
.omt-analyze-btn{background:var(--c-lime);color:#000;border:none;border-radius:7px;padding:7px 20px;font-family:var(--f-display);font-size:12px;font-weight:700;letter-spacing:.06em;cursor:pointer;transition:all .2s;box-shadow:0 0 14px rgba(125,255,0,.2);display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.omt-analyze-btn:hover{background:#8FFF1A;box-shadow:0 0 22px rgba(125,255,0,.35);transform:translateY(-1px);}
.omt-reset-btn{background:var(--c-panel);border:1px solid var(--c-border2);color:var(--c-muted);border-radius:7px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--f-sans);transition:all .2s;white-space:nowrap;}
.omt-reset-btn:hover{color:var(--c-text);}
.omt-history-btn{background:rgba(171,71,188,.1);border:1px solid rgba(171,71,188,.3);color:#CE93D8;border-radius:7px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--f-sans);transition:all .2s;white-space:nowrap;display:inline-flex;align-items:center;gap:5px;}
.omt-history-btn:hover{background:rgba(171,71,188,.18);}
.omt-filter-right{margin-left:auto;display:flex;align-items:center;gap:10px;}
.omt-info-text{font-size:10px;color:var(--c-muted);font-family:var(--f-mono);}
.omt-upd-text{font-size:10px;color:rgba(120,123,134,.45);font-family:var(--f-mono);}
@media(max-width:768px){.omt-filter-bar{padding:0 16px;}.omt-filter-inner{gap:8px;}.omt-filter-right{margin-left:0;width:100%;}}

/* CONTENT */
.omt-content{padding:24px 32px 64px;}
@media(max-width:768px){.omt-content{padding:16px 12px 48px;}}
.omt-warn{background:rgba(255,167,38,.08);border:1px solid rgba(255,167,38,.25);border-radius:9px;padding:14px 18px;margin-bottom:18px;display:none;align-items:center;gap:12px;font-size:13px;color:var(--c-amber);}
.omt-warn.show{display:flex;} .omt-warn i{font-size:18px;flex-shrink:0;} .omt-warn strong{color:#fff;}

/* Anchor note */
.omt-anchor-note{display:flex;align-items:center;gap:10px;background:rgba(0,184,212,.06);border:1px solid rgba(0,184,212,.18);border-radius:9px;padding:10px 16px;margin-bottom:18px;font-size:12px;color:var(--c-blue);font-family:var(--f-mono);}

/* STATS — one block per snapshot time */
.omt-stats-groups{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;}
@media(max-width:900px){.omt-stats-groups{grid-template-columns:1fr;}}
.omt-stat-group{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:14px;}
.omt-stat-group-title{font-family:var(--f-display);font-size:13px;font-weight:800;color:var(--c-lime);margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.omt-stat-row{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;}
.omt-mini-stat{text-align:center;background:var(--c-panel);border-radius:7px;padding:8px 4px;}
.omt-mini-label{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--c-muted);font-family:var(--f-mono);margin-bottom:3px;}
.omt-mini-val{font-family:var(--f-display);font-size:16px;font-weight:800;color:#fff;}
.omt-mini-val.v-ce{color:#80CBC4;} .omt-mini-val.v-pe{color:#EF9A9A;} .omt-mini-val.v-wait{color:var(--c-amber);}
.omt-mini-val.v-bull{color:#80CBC4;} .omt-mini-val.v-bear{color:#EF9A9A;}

/* TABLE CARD */
.omt-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;overflow:hidden;position:relative;}
.omt-card::before{content:'';position:absolute;top:0;left:16px;right:16px;height:1px;background:linear-gradient(90deg,transparent,var(--c-lime),transparent);opacity:.3;}
.omt-card-header{padding:13px 18px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;background:rgba(0,0,0,.2);}
.omt-card-title{font-family:var(--f-display);font-size:14px;font-weight:700;color:var(--c-text);}
.omt-card-subtitle{font-size:10px;color:var(--c-muted);font-family:var(--f-mono);}
.omt-tscroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}

/* TABLE */
.omt-table{width:100%;border-collapse:collapse;font-family:var(--f-mono);min-width:1700px;}
.omt-table thead tr.th-group th{padding:8px 10px 4px;text-align:center;font-family:var(--f-sans);font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--c-panel);border-bottom:none;white-space:nowrap;}
.omt-table thead tr.th-cols th{padding:4px 10px 8px;text-align:center;font-family:var(--f-mono);font-size:9px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;background:rgba(0,0,0,.25);color:var(--c-muted);border-bottom:1px solid var(--c-border);white-space:nowrap;}
.g-info{color:var(--c-blue)!important;} .g-anchor{color:var(--c-amber)!important;}
.g-s1{color:#80CBC4!important;} .g-s2{color:#4DB6AC!important;} .g-s3{color:#26A69A!important;}
.sep-anchor{border-left:1px solid rgba(255,167,38,.15)!important;}
.sep-s1{border-left:2px solid rgba(0,184,212,.2)!important;}
.sep-s2{border-left:2px solid rgba(0,184,212,.2)!important;}
.sep-s3{border-left:2px solid rgba(0,184,212,.2)!important;}
.omt-table tbody td{padding:8px 10px;text-align:center;font-size:11px;border-bottom:1px solid var(--c-border);vertical-align:middle;white-space:nowrap;color:var(--c-muted);transition:background .15s;}
.omt-table tbody tr:hover td{background:rgba(255,255,255,.02)!important;}
.tr-even{background:var(--c-surface);} .tr-odd{background:rgba(0,0,0,.15);}
.c-num{font-size:9px;color:rgba(120,123,134,.35);} .c-date{font-size:11px;font-weight:700;color:var(--c-lime);}
.c-sym{font-size:12px;font-weight:800;color:var(--c-blue);} .c-atm{font-size:10px;color:var(--c-amber);font-weight:700;}
.c-fut{font-size:10px;color:var(--c-blue);} .c-expiry{font-size:10px;color:var(--c-muted);}
.c-oi{font-size:11px;font-weight:700;color:var(--c-text);}
.pct-up{color:#80CBC4;font-weight:700;} .pct-down{color:#EF9A9A;font-weight:700;} .pct-neu{color:var(--c-muted);}
.sig-bull{display:inline-block;background:rgba(38,166,154,.12);color:#4DB6AC;border:1px solid rgba(38,166,154,.3);border-radius:5px;padding:3px 9px;font-family:var(--f-sans);font-size:9px;font-weight:800;}
.sig-bear{display:inline-block;background:rgba(239,83,80,.1);color:#EF9A9A;border:1px solid rgba(239,83,80,.3);border-radius:5px;padding:3px 9px;font-family:var(--f-sans);font-size:9px;font-weight:800;}
.sig-neut{display:inline-block;background:var(--c-panel);color:var(--c-muted);border:1px solid var(--c-border2);border-radius:5px;padding:3px 9px;font-family:var(--f-sans);font-size:9px;}
.act-ce{display:inline-block;background:rgba(38,166,154,.1);color:#4DB6AC;border:1px solid rgba(38,166,154,.25);border-radius:4px;padding:2px 7px;font-size:9px;font-weight:800;font-family:var(--f-sans);}
.act-pe{display:inline-block;background:rgba(239,83,80,.08);color:#EF9A9A;border:1px solid rgba(239,83,80,.22);border-radius:4px;padding:2px 7px;font-size:9px;font-weight:800;font-family:var(--f-sans);}
.act-wt{display:inline-block;background:rgba(255,167,38,.1);color:var(--c-amber);border:1px solid rgba(255,167,38,.25);border-radius:4px;padding:2px 7px;font-size:9px;font-family:var(--f-sans);}
.dash-cell{color:rgba(120,123,134,.4);}
.trend-buildup{color:var(--c-teal);font-weight:700;}
.trend-unwind{color:var(--c-red);font-weight:700;}
.trend-flat{color:var(--c-muted);}
.omt-empty{text-align:center;padding:52px 20px;color:var(--c-muted);}
.omt-empty-icon{width:52px;height:52px;border-radius:50%;background:var(--c-panel);border:1px solid var(--c-border);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:20px;}
.omt-empty p{font-size:12px;font-family:var(--f-mono);margin-top:4px;}
.omt-spinner-row{display:flex;align-items:center;justify-content:center;gap:12px;padding:52px;color:var(--c-muted);font-size:12px;font-family:var(--f-mono);}
.omt-spinner{width:28px;height:28px;border:2px solid var(--c-border2);border-top:2px solid var(--c-lime);border-radius:50%;animation:omtSpin .9s linear infinite;flex-shrink:0;}
</style>

<div class="omt-wrap">

<div class="omt-hero omt-anim">
    <div class="omt-hero-left">
        <div class="omt-eyebrow">Options Analytics</div>
        <h1>OI Flow Sentiment <span>Multi Snapshot</span></h1>
        <p>Anchors on the previous trading day's <strong style="color:#fff;">closing OI (15:00)</strong> and independently checks it against today's OI at <strong style="color:#fff;">10:15, 11:15 and 12:15</strong> — so you can see how sentiment is building across the morning session, not just at one point in time. Pick a single symbol to pull up its full <strong style="color:#fff;">History</strong> across a date range.</p>
    </div>
    <div class="omt-hero-icon"><i class="las la-layer-group"></i></div>
</div>

<div class="omt-filter-bar">
    <div class="omt-filter-inner">
        <span class="omt-filter-label">Symbol</span>
        <select id="omt-sym" class="omt-select" onchange="omtOnSymbolChange()"><option value="ALL">— All —</option></select>
        <div class="omt-sep"></div>

        <span class="omt-filter-label">Date</span>
        <div class="omt-date-wrap">
            <button class="omt-date-nav" onclick="omtShiftDate(-1)">‹</button>
            <input type="date" id="omt-date" class="omt-date-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="omtAnalyze()">
            <button class="omt-date-nav" onclick="omtShiftDate(1)">›</button>
            <button class="omt-date-nav omt-today-btn" onclick="omtGoToday()">TODAY</button>
        </div>

        {{-- History (date-range) controls — shown once a single symbol is picked --}}
        <div class="omt-sep" id="omt-range-sep" style="display:none"></div>
        <div class="omt-date-wrap" id="omt-range-wrap" style="display:none">
            <input type="date" id="omt-from-date" class="omt-date-input" max="{{ now()->toDateString() }}" onchange="omtAnalyze()">
            <span style="color:var(--c-muted);font-size:11px;">→</span>
            <input type="date" id="omt-to-date" class="omt-date-input" max="{{ now()->toDateString() }}" onchange="omtAnalyze()">
            <button class="omt-date-nav omt-today-btn" onclick="omtQuick30()" title="Last 30 days">30D</button>
        </div>

        <span id="omt-date-badge"></span>

        <div class="omt-sep"></div>
        <span class="omt-filter-label">Action</span>
        <select id="omt-action" class="omt-select" onchange="omtAnalyze()">
            <option value="">Any Snapshot</option>
            <option value="BUY CE">BUY CE (any snapshot)</option>
            <option value="BUY PE">BUY PE (any snapshot)</option>
            <option value="WAIT">WAIT (any snapshot)</option>
        </select>

        <button class="omt-history-btn" id="omt-history-btn" onclick="omtToggleRangeMode()" style="display:none">📊 History</button>
        <button class="omt-analyze-btn" onclick="omtAnalyze()"><i class="las la-search"></i> Analyze</button>
        <button class="omt-reset-btn" onclick="omtReset()">↺ Reset</button>

        <div class="omt-filter-right">
            <span class="omt-info-text" id="omt-info"></span>
            <span class="omt-upd-text"  id="omt-upd"></span>
        </div>
    </div>
</div>

<div class="omt-content">
    <div class="omt-warn" id="omt-warn"><i class="las la-exclamation-triangle"></i><div><strong>No Analysis Config Found</strong><div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="omt-warn-msg">Go to Admin → Analysis Config and create a config with symbols.</div></div></div>

    <div class="omt-anchor-note"><i class="las la-anchor"></i> Anchor = previous trading day's closing OI (15:00). Each snapshot column below is compared independently against this same anchor. <span id="omt-anchor-extra"></span></div>

    <div class="omt-stats-groups omt-anim" id="omt-stats-groups"></div>

    <div class="omt-card omt-anim d1">
        <div class="omt-card-header">
            <div class="omt-card-title">⊙ OI Flow — 10:15 / 11:15 / 12:15 vs Prev Close</div>
            <span class="omt-card-subtitle" id="omt-subtitle">Detecting last available date…</span>
        </div>
        <div class="omt-tscroll">
            <table class="omt-table">
                <thead>
                    <tr class="th-group" id="omt-thead-group">
                        <th colspan="5" class="g-info">Market Info</th>
                        <th colspan="3" class="g-anchor sep-anchor">Prev Day Close OI (Anchor)</th>
                        <!-- snapshot groups injected here -->
                    </tr>
                    <tr class="th-cols" id="omt-thead-cols">
                        <th>#</th><th>Date</th><th>Symbol</th><th>ATM / FUT</th><th>Expiry</th>
                        <th class="sep-anchor">CE OI</th><th>PE OI</th><th>Prev Trend</th>
                        <!-- snapshot columns injected here -->
                    </tr>
                </thead>
                <tbody id="omt-tbody">
                    <tr><td colspan="8"><div class="omt-spinner-row"><div class="omt-spinner"></div>Detecting last available date…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

@endsection
@push('script')
<script>
/* ═══ OI Flow Multi-Snapshot — JS ═══ */
var OMT_ANALYZE='{{route("oi-flow-multi.analyze")}}',OMT_SYMBOLS='{{route("oi-flow-multi.symbols")}}',OMT_LASTDATE='{{route("oi-flow-multi.last.date")}}',OMT_TODAY='{{now()->toDateString()}}';
var omtSymCache=null, omtLabels=['10:15','11:15','12:15']; // overwritten by server response
var omtMode='single'; // 'single' | 'range' — range mode = symbol-wise "History" view
function el(id){return document.getElementById(id);}
function html(id,h){var e=el(id);if(e)e.innerHTML=h;}
function txt(id,t){var e=el(id);if(e)e.textContent=t;}
document.addEventListener('DOMContentLoaded',function(){omtBuildHead();omtResolveLastDateAndLoad();});
function omtResolveLastDateAndLoad(){fetch(OMT_LASTDATE,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){if(res.last_date)el('omt-date').value=res.last_date;omtLoadSymbols(function(){omtAnalyze();});}).catch(function(){omtLoadSymbols(function(){omtAnalyze();});});}
function omtGetDate(){return el('omt-date').value;}
function omtShiftDate(d){var p=el('omt-date'),dt=new Date(p.value);dt.setDate(dt.getDate()+d);var s=dt.toISOString().split('T')[0];if(s>OMT_TODAY)return;p.value=s;omtAnalyze();}
function omtGoToday(){el('omt-date').value=OMT_TODAY;omtAnalyze();}
function omtUpdateDateBadge(isToday){el('omt-date-badge').innerHTML=isToday?'<span class="omt-live-badge">● Live</span>':'<span class="omt-hist-badge">📅 Historical</span>';}
function omtLoadSymbols(callback){if(omtSymCache!==null){omtRebuildSym(omtSymCache);if(callback)callback();return;}fetch(OMT_SYMBOLS,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){if(res.no_config){omtShowWarn(res.message||'');omtSymCache=[];omtRebuildSym([]);}else{omtHideWarn();omtSymCache=res.symbols||[];omtRebuildSym(omtSymCache);}if(callback)callback();}).catch(function(){if(callback)callback();});}
function omtRebuildSym(syms){var sel=el('omt-sym'),prev=sel.value,opts='<option value="ALL">— All Symbols —</option>';syms.forEach(function(s){opts+='<option value="'+s+'"'+(s===prev?' selected':'')+'>'+s+'</option>';});sel.innerHTML=opts;if(prev&&prev!=='ALL'){sel.value=prev;if(sel.value!==prev)sel.value='ALL';}}

/* ── Symbol change: reveal/hide the History (range) toggle ── */
function omtOnSymbolChange(){
    var sym=el('omt-sym').value;
    if(sym==='ALL'){
        el('omt-history-btn').style.display='none';
        if(omtMode==='range'){omtToggleRangeMode();return;} // snaps back to single-date mode & re-analyzes
    }else{
        el('omt-history-btn').style.display='inline-flex';
    }
    omtAnalyze();
}

/* ── Toggle between single-date and symbol-wise date-range (History) mode ── */
function omtToggleRangeMode(){
    omtMode=(omtMode==='single')?'range':'single';
    if(omtMode==='range'){
        el('omt-date').style.display='none';
        el('omt-range-wrap').style.display='flex';
        el('omt-range-sep').style.display='block';
        el('omt-history-btn').innerHTML='📅 Single Day';
        el('omt-date-badge').innerHTML='<span class="omt-range-badge">📊 History</span>';
        if(!el('omt-from-date').value||!el('omt-to-date').value)omtSetRangeDefaults(30);
        txt('omt-anchor-extra','Showing '+el('omt-sym').value+' across the selected range, most recent first.');
    }else{
        el('omt-date').style.display='inline-block';
        el('omt-range-wrap').style.display='none';
        el('omt-range-sep').style.display='none';
        el('omt-history-btn').innerHTML='📊 History';
        txt('omt-anchor-extra','');
    }
    omtAnalyze();
}
function omtSetRangeDefaults(days){
    var to=el('omt-date').value||OMT_TODAY;
    var toDt=new Date(to),fromDt=new Date(toDt);
    fromDt.setDate(fromDt.getDate()-(days-1));
    el('omt-to-date').value=toDt.toISOString().split('T')[0];
    el('omt-from-date').value=fromDt.toISOString().split('T')[0];
}
function omtQuick30(){omtSetRangeDefaults(30);omtAnalyze();}

var omtSnapClasses=['g-s1','g-s2','g-s3'];
function omtBuildHead(){
    var groupHtml='',colsHtml='';
    omtLabels.forEach(function(label,i){
        var cls=omtSnapClasses[i%omtSnapClasses.length];
        groupHtml+='<th colspan="6" class="'+cls+' sep-s'+(i+1)+'">'+label+' vs Prev Close</th>';
        colsHtml+='<th class="sep-s'+(i+1)+'">CE OI</th><th>CE %</th><th>PE OI</th><th>PE %</th><th>Sentiment</th><th>Action</th>';
    });
    el('omt-thead-group').innerHTML='<th colspan="5" class="g-info">Market Info</th><th colspan="3" class="g-anchor sep-anchor">Prev Day Close OI (Anchor)</th>'+groupHtml;
    el('omt-thead-cols').innerHTML='<th>#</th><th>Date</th><th>Symbol</th><th>ATM / FUT</th><th>Expiry</th><th class="sep-anchor">CE OI</th><th>PE OI</th><th>Prev Trend</th>'+colsHtml;
    omtBuildStatGroups();
}
function omtBuildStatGroups(){
    var h='';
    omtLabels.forEach(function(label){
        h+='<div class="omt-stat-group"><div class="omt-stat-group-title">🕐 '+label+' vs Prev Close</div>'+
           '<div class="omt-stat-row">'+
           '<div class="omt-mini-stat"><div class="omt-mini-label">Buy CE</div><div class="omt-mini-val v-ce" id="st-ce-'+omtKey(label)+'">—</div></div>'+
           '<div class="omt-mini-stat"><div class="omt-mini-label">Buy PE</div><div class="omt-mini-val v-pe" id="st-pe-'+omtKey(label)+'">—</div></div>'+
           '<div class="omt-mini-stat"><div class="omt-mini-label">Wait</div><div class="omt-mini-val v-wait" id="st-wait-'+omtKey(label)+'">—</div></div>'+
           '<div class="omt-mini-stat"><div class="omt-mini-label">Bullish</div><div class="omt-mini-val v-bull" id="st-bull-'+omtKey(label)+'">—</div></div>'+
           '<div class="omt-mini-stat"><div class="omt-mini-label">Bearish</div><div class="omt-mini-val v-bear" id="st-bear-'+omtKey(label)+'">—</div></div>'+
           '</div></div>';
    });
    html('omt-stats-groups',h);
}
function omtKey(label){return label.replace(':','');}

function omtAnalyze(){
    var action=el('omt-action').value, sym=el('omt-sym').value;
    var params, loadingLabel;

    if(omtMode==='range'){
        var from=el('omt-from-date').value, to=el('omt-to-date').value;
        if(!from||!to)return;
        if(sym==='ALL'){omtEmptyTable('Select a single symbol to view its history.');return;}
        params=new URLSearchParams({from_date:from,to_date:to,filter_action:action});
        params.append('symbols[]',sym);
        loadingLabel=sym+' · '+from+' → '+to;
    }else{
        var date=omtGetDate();
        if(!date)return;
        params=new URLSearchParams({date:date,filter_action:action});
        if(sym&&sym!=='ALL')params.append('symbols[]',sym);
        loadingLabel=date;
    }

    omtHideWarn();omtResetStats();
    var colspan=8+omtLabels.length*6;
    html('omt-tbody','<tr><td colspan="'+colspan+'"><div class="omt-spinner-row"><div class="omt-spinner"></div>Calculating multi-snapshot OI flow for '+loadingLabel+'…</div></div></td></tr>');
    txt('omt-subtitle',loadingLabel+' · Loading…');

    fetch(OMT_ANALYZE+'?'+params.toString(),{headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){if(!r.ok)throw new Error('Server error '+r.status);return r.json();})
        .then(function(res){
            if(res.snapshot_labels&&res.snapshot_labels.length){omtLabels=res.snapshot_labels;omtBuildHead();}
            if(omtMode==='single'&&typeof res.is_today!=='undefined')omtUpdateDateBadge(res.is_today);
            if(res.available_symbols&&res.available_symbols.length){omtSymCache=res.available_symbols;omtRebuildSym(omtSymCache);if(sym&&sym!=='ALL')el('omt-sym').value=sym;}
            if(res.no_config){omtShowWarn(res.message);omtEmptyTable('No active config.');return;}
            if(!res.success||!res.data||!res.data.length){omtEmptyTable(res.message||'No signals found for this date.');omtResetStats();txt('omt-subtitle',loadingLabel+' · No data found');return;}
            if(res.stats)omtUpdateStats(res.stats);
            omtRenderTable(res.data);
            txt('omt-info','Total: '+res.total_records+(omtMode==='range'?' across '+(new Set(res.data.map(function(r){return r.date;}))).size+' day(s)':''));
            txt('omt-subtitle',loadingLabel+' · '+res.message);
            txt('omt-upd','Updated '+new Date().toLocaleTimeString());
        }).catch(function(err){omtEmptyTable('⚠ '+err.message);});
}

function omtRenderTable(data){var h='',num=1;data.forEach(function(r,i){var zebra=i%2===0?'tr-even':'tr-odd';var prevTrend='<span class="'+trendCls(r.prev_ce_trend)+'">CE:'+dashOr(r.prev_ce_trend)+'</span><br><span class="'+trendCls(r.prev_pe_trend)+'">PE:'+dashOr(r.prev_pe_trend)+'</span>';var snapCells='';omtLabels.forEach(function(label,idx){var s=r.snapshots?r.snapshots[label]:null;var sepCls='sep-s'+(idx+1);if(!s){snapCells+='<td class="'+sepCls+' dash-cell">—</td><td class="dash-cell">—</td><td class="dash-cell">—</td><td class="dash-cell">—</td><td class="dash-cell">—</td><td class="dash-cell">—</td>';return;}var isBull=s.sentiment==='BULLISH',isBear=s.sentiment==='BEARISH';var sentBadge=isBull?'<span class="sig-bull">▲ BULL</span>':isBear?'<span class="sig-bear">▼ BEAR</span>':'<span class="sig-neut">— NEUT</span>';var actBadge=s.trade_action==='BUY CE'?'<span class="act-ce">📈 CE</span>':s.trade_action==='BUY PE'?'<span class="act-pe">📉 PE</span>':'<span class="act-wt">⏸ WAIT</span>';snapCells+='<td class="'+sepCls+' c-oi">'+nInt(s.ce_oi)+'</td><td>'+pctCell(s.ce_oi_pct)+'</td><td class="c-oi">'+nInt(s.pe_oi)+'</td><td>'+pctCell(s.pe_oi_pct)+'</td><td>'+sentBadge+'</td><td>'+actBadge+'</td>';});h+='<tr class="'+zebra+'">'+'<td class="c-num">'+num+++'</td>'+'<td class="c-date">'+r.date+'</td>'+'<td class="c-sym">'+esc(r.symbol)+'</td>'+'<td>'+(r.atm_strike?'<span class="c-atm">₹'+nInt(r.atm_strike)+'</span>':'—')+(r.fut_price?'<br><span class="c-fut">F:₹'+f(r.fut_price)+'</span>':'')+'</td>'+'<td class="c-expiry">'+(r.expiry||'—')+'</td>'+'<td class="sep-anchor c-oi">'+nInt(r.prev_close_ce_oi)+'</td>'+'<td class="c-oi">'+nInt(r.prev_close_pe_oi)+'</td>'+'<td>'+prevTrend+'</td>'+snapCells+'</tr>';});html('omt-tbody',h||omtEmptyHtml('No results.'));}

function omtUpdateStats(stats){omtLabels.forEach(function(label){var s=stats[label];if(!s)return;var k=omtKey(label);txt('st-ce-'+k,s.buy_ce);txt('st-pe-'+k,s.buy_pe);txt('st-wait-'+k,s.wait);txt('st-bull-'+k,s.bullish);txt('st-bear-'+k,s.bearish);});}
function omtResetStats(){omtLabels.forEach(function(label){var k=omtKey(label);['ce','pe','wait','bull','bear'].forEach(function(p){txt('st-'+p+'-'+k,'—');});});}
function omtShowWarn(msg){el('omt-warn').classList.add('show');txt('omt-warn-msg',msg||'');}
function omtHideWarn(){el('omt-warn').classList.remove('show');}
function omtEmptyTable(msg){html('omt-tbody',omtEmptyHtml(msg));}
function omtEmptyHtml(msg){var colspan=8+omtLabels.length*6;return'<tr><td colspan="'+colspan+'"><div class="omt-empty"><div class="omt-empty-icon"><i class="las la-chart-bar"></i></div><p>'+(msg||'No data found.')+'</p></div></td></tr>';}
function omtReset(){
    omtMode='single';
    el('omt-date').style.display='inline-block';
    el('omt-range-wrap').style.display='none';
    el('omt-range-sep').style.display='none';
    el('omt-history-btn').innerHTML='📊 History';
    txt('omt-anchor-extra','');
    fetch(OMT_LASTDATE,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){el('omt-date').value=res.last_date||OMT_TODAY;el('omt-action').value='';el('omt-sym').value='ALL';el('omt-history-btn').style.display='none';omtHideWarn();omtAnalyze();}).catch(function(){el('omt-date').value=OMT_TODAY;el('omt-action').value='';el('omt-sym').value='ALL';el('omt-history-btn').style.display='none';omtHideWarn();omtAnalyze();});
}

function pctCell(v){if(v==null)return'<span class="pct-neu">—</span>';var n=parseFloat(v)||0,cls=n>0?'pct-up':n<0?'pct-down':'pct-neu';return'<span class="'+cls+'">'+(n>0?'+':'')+n.toFixed(2)+'%</span>';}
function f(v){return parseFloat(v||0).toFixed(2);}
function nInt(v){var n=Number(v)||0;if(n>=1e7)return(n/1e7).toFixed(2)+'Cr';if(n>=1e5)return(n/1e5).toFixed(2)+'L';if(n>=1e3)return(n/1e3).toFixed(1)+'K';return n.toLocaleString('en-IN');}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function trendCls(t){return t==='Buildup'?'trend-buildup':t==='Unwinding'?'trend-unwind':'trend-flat';}
function dashOr(t){return(t&&t!=='-')?t:'—';}
</script>
@endpush