{{-- FILE: resources/views/themes/{active_theme}/user/oi-flow-sentiment/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ── SHARED DARK BASE ── */
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
.ofs-wrap{font-family:var(--f-sans);color:var(--c-text);background:var(--c-bg);}
.ofs-wrap *{box-sizing:border-box;}
.ofs-wrap a{text-decoration:none;color:inherit;}
.mono{font-family:var(--f-mono);}
@keyframes ofsUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.ofs-anim{animation:ofsUp .5s ease both;}
.ofs-anim.d1{animation-delay:.08s;}.ofs-anim.d2{animation-delay:.16s;}
@keyframes ofsSpin{to{transform:rotate(360deg);}}

/* HERO */
.ofs-hero{position:relative;overflow:hidden;background:var(--c-bg);border-bottom:1px solid var(--c-border);padding:36px 32px;display:flex;align-items:center;justify-content:space-between;gap:24px;}
.ofs-hero::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(125,255,0,.022) 1px,transparent 1px),linear-gradient(90deg,rgba(125,255,0,.022) 1px,transparent 1px);background-size:56px 56px;mask-image:radial-gradient(ellipse 80% 80% at 20% 50%,black,transparent);pointer-events:none;}
.ofs-hero::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 35% 70% at 5% 50%,rgba(125,255,0,.04),transparent 70%);pointer-events:none;}
.ofs-hero-left{position:relative;z-index:1;}
.ofs-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--c-lime);margin-bottom:10px;}
.ofs-eyebrow::before{content:'';display:block;width:16px;height:1px;background:var(--c-lime);}
.ofs-hero h1{font-family:var(--f-display);font-size:clamp(22px,3.5vw,36px);font-weight:800;color:#fff;line-height:1.1;letter-spacing:-.015em;margin-bottom:10px;}
.ofs-hero h1 span{color:var(--c-lime);}
.ofs-hero p{font-size:13px;color:var(--c-muted);line-height:1.7;max-width:600px;}
.ofs-hero-icon{position:relative;z-index:1;width:72px;height:72px;border-radius:12px;background:var(--c-surface);border:1px solid var(--c-border2);display:flex;align-items:center;justify-content:center;font-size:30px;color:var(--c-lime);flex-shrink:0;box-shadow:0 0 24px rgba(125,255,0,.1);}
@media(max-width:768px){.ofs-hero{flex-direction:column;padding:24px 18px;}.ofs-hero-icon{display:none;}}

/* FILTER BAR */
.ofs-filter-bar{background:var(--c-surface);border-bottom:1px solid var(--c-border);padding:0 32px;position:sticky;top:0;z-index:200;box-shadow:0 4px 24px rgba(0,0,0,.3);}
.ofs-filter-inner{display:flex;align-items:center;gap:12px;padding:11px 0;flex-wrap:wrap;}
.ofs-filter-label{font-size:10px;color:var(--c-muted);font-weight:700;text-transform:uppercase;letter-spacing:.1em;font-family:var(--f-mono);flex-shrink:0;}
.ofs-sep{width:1px;height:26px;background:var(--c-border2);flex-shrink:0;}
/* shared input styles */
.ofs-select,.ofs-date-input{background:var(--c-panel);border:1px solid var(--c-border2);border-radius:7px;font-family:var(--f-mono);font-size:12px;font-weight:600;color:var(--c-text);outline:none;padding:6px 28px 6px 11px;transition:border-color .2s;appearance:none;cursor:pointer;min-width:130px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
.ofs-date-input{min-width:auto;padding:6px 10px;background-image:none;}
.ofs-date-input::-webkit-calendar-picker-indicator{filter:invert(1) opacity(.4);cursor:pointer;}
.ofs-select:focus,.ofs-date-input:focus{border-color:rgba(125,255,0,.45);}
.ofs-date-wrap{display:flex;align-items:center;gap:4px;}
.ofs-date-nav{width:28px;height:30px;background:var(--c-panel);border:1px solid var(--c-border2);border-radius:6px;color:var(--c-muted);cursor:pointer;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;transition:all .2s;font-family:var(--f-sans);}
.ofs-date-nav:hover{border-color:rgba(125,255,0,.3);color:var(--c-lime);}
.ofs-today-btn{width:auto;padding:0 10px;font-size:9px;font-family:var(--f-mono);font-weight:700;letter-spacing:.1em;}
.ofs-live-badge{background:rgba(38,166,154,.12);color:#4DB6AC;border:1px solid rgba(38,166,154,.25);border-radius:100px;font-size:10px;font-weight:700;padding:2px 9px;font-family:var(--f-mono);}
.ofs-hist-badge{background:rgba(255,167,38,.1);color:var(--c-amber);border:1px solid rgba(255,167,38,.25);border-radius:100px;font-size:10px;font-weight:700;padding:2px 9px;font-family:var(--f-mono);}
.ofs-analyze-btn{background:var(--c-lime);color:#000;border:none;border-radius:7px;padding:7px 20px;font-family:var(--f-display);font-size:12px;font-weight:700;letter-spacing:.06em;cursor:pointer;transition:all .2s;box-shadow:0 0 14px rgba(125,255,0,.2);display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.ofs-analyze-btn:hover{background:#8FFF1A;box-shadow:0 0 22px rgba(125,255,0,.35);transform:translateY(-1px);}
.ofs-reset-btn{background:var(--c-panel);border:1px solid var(--c-border2);color:var(--c-muted);border-radius:7px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--f-sans);transition:all .2s;}
.ofs-reset-btn:hover{color:var(--c-text);}
.ofs-filter-right{margin-left:auto;display:flex;align-items:center;gap:10px;}
.ofs-info-text{font-size:10px;color:var(--c-muted);font-family:var(--f-mono);}
.ofs-upd-text{font-size:10px;color:rgba(120,123,134,.45);font-family:var(--f-mono);}
@media(max-width:768px){.ofs-filter-bar{padding:0 16px;}.ofs-filter-inner{gap:8px;}.ofs-filter-right{margin-left:0;width:100%;}}

/* CONTENT */
.ofs-content{padding:24px 32px 64px;}
@media(max-width:768px){.ofs-content{padding:16px 12px 48px;}}
.ofs-warn{background:rgba(255,167,38,.08);border:1px solid rgba(255,167,38,.25);border-radius:9px;padding:14px 18px;margin-bottom:18px;display:none;align-items:center;gap:12px;font-size:13px;color:var(--c-amber);}
.ofs-warn.show{display:flex;} .ofs-warn i{font-size:18px;flex-shrink:0;} .ofs-warn strong{color:#fff;}

/* STATS */
.ofs-stats{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px;}
@media(max-width:900px){.ofs-stats{grid-template-columns:repeat(3,1fr);}}
@media(max-width:500px){.ofs-stats{grid-template-columns:repeat(2,1fr);}}
.ofs-stat-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:14px 16px;position:relative;overflow:hidden;transition:border-color .25s;}
.ofs-stat-card::after{content:'';position:absolute;top:10px;bottom:10px;left:0;width:2px;border-radius:0 2px 2px 0;}
.ofs-stat-card.s-total::after{background:var(--c-blue);}
.ofs-stat-card.s-ce::after{background:var(--c-teal);}
.ofs-stat-card.s-pe::after{background:var(--c-red);}
.ofs-stat-card.s-wait::after{background:var(--c-amber);}
.ofs-stat-card.s-bull::after{background:var(--c-teal);}
.ofs-stat-card.s-bear::after{background:var(--c-red);}
.ofs-stat-card:hover{border-color:var(--c-border2);}
.ofs-stat-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--c-muted);margin-bottom:6px;font-family:var(--f-mono);}
.ofs-stat-val{font-family:var(--f-display);font-size:24px;font-weight:800;color:#fff;}
.s-ce .ofs-stat-val{color:#80CBC4;} .s-pe .ofs-stat-val{color:#EF9A9A;}
.s-wait .ofs-stat-val{color:var(--c-amber);} .s-bull .ofs-stat-val{color:#80CBC4;} .s-bear .ofs-stat-val{color:#EF9A9A;}

/* TABLE CARD */
.ofs-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;overflow:hidden;position:relative;}
.ofs-card::before{content:'';position:absolute;top:0;left:16px;right:16px;height:1px;background:linear-gradient(90deg,transparent,var(--c-lime),transparent);opacity:.3;}
.ofs-card-header{padding:13px 18px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;background:rgba(0,0,0,.2);}
.ofs-card-title{font-family:var(--f-display);font-size:14px;font-weight:700;color:var(--c-text);}
.ofs-card-subtitle{font-size:10px;color:var(--c-muted);font-family:var(--f-mono);}
.ofs-tscroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}

/* TABLE */
.ofs-table{width:100%;border-collapse:collapse;font-family:var(--f-mono);min-width:1000px;}
.ofs-table thead tr.th-group th{padding:8px 10px 4px;text-align:center;font-family:var(--f-sans);font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--c-panel);border-bottom:none;white-space:nowrap;}
.ofs-table thead tr.th-cols th{padding:4px 10px 8px;text-align:center;font-family:var(--f-mono);font-size:9px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;background:rgba(0,0,0,.25);color:var(--c-muted);border-bottom:1px solid var(--c-border);white-space:nowrap;}
.g-info{color:var(--c-blue)!important;} .g-oi{color:var(--c-amber)!important;} .g-signal{color:var(--c-teal)!important;}
.sep-oi{border-left:1px solid rgba(255,167,38,.15)!important;} .sep-signal{border-left:1px solid rgba(38,166,154,.15)!important;}
.ofs-table tbody td{padding:8px 10px;text-align:center;font-size:11px;border-bottom:1px solid var(--c-border);vertical-align:middle;white-space:nowrap;color:var(--c-muted);transition:background .15s;}
.ofs-table tbody tr:hover td{background:rgba(255,255,255,.02)!important;}
.tr-even{background:var(--c-surface);} .tr-odd{background:rgba(0,0,0,.15);}
.tr-bull{background:rgba(38,166,154,.04)!important;} .tr-bear{background:rgba(239,83,80,.04)!important;}
.c-num{font-size:9px;color:rgba(120,123,134,.35);} .c-date{font-size:11px;font-weight:700;color:var(--c-lime);}
.c-sym{font-size:12px;font-weight:800;color:var(--c-blue);} .c-atm{font-size:10px;color:var(--c-amber);font-weight:700;}
.c-fut{font-size:10px;color:var(--c-blue);} .c-expiry{font-size:10px;color:var(--c-muted);}
.c-oi{font-size:11px;font-weight:700;color:var(--c-text);}
.pct-up{color:#80CBC4;font-weight:700;} .pct-down{color:#EF9A9A;font-weight:700;} .pct-neu{color:var(--c-muted);}
/* sentiment / action / condition / rank badges */
.sig-bull{display:inline-block;background:rgba(38,166,154,.12);color:#4DB6AC;border:1px solid rgba(38,166,154,.3);border-radius:5px;padding:3px 10px;font-family:var(--f-sans);font-size:10px;font-weight:800;}
.sig-bear{display:inline-block;background:rgba(239,83,80,.1);color:#EF9A9A;border:1px solid rgba(239,83,80,.3);border-radius:5px;padding:3px 10px;font-family:var(--f-sans);font-size:10px;font-weight:800;}
.sig-neut{display:inline-block;background:var(--c-panel);color:var(--c-muted);border:1px solid var(--c-border2);border-radius:5px;padding:3px 10px;font-family:var(--f-sans);font-size:10px;}
.act-ce{display:inline-block;background:rgba(38,166,154,.1);color:#4DB6AC;border:1px solid rgba(38,166,154,.25);border-radius:4px;padding:2px 8px;font-size:10px;font-weight:800;font-family:var(--f-sans);}
.act-pe{display:inline-block;background:rgba(239,83,80,.08);color:#EF9A9A;border:1px solid rgba(239,83,80,.22);border-radius:4px;padding:2px 8px;font-size:10px;font-weight:800;font-family:var(--f-sans);}
.act-wt{display:inline-block;background:rgba(255,167,38,.1);color:var(--c-amber);border:1px solid rgba(255,167,38,.25);border-radius:4px;padding:2px 8px;font-size:10px;font-family:var(--f-sans);}
.cond-base{display:inline-block;padding:2px 7px;border-radius:4px;font-size:9px;font-weight:700;}
.cond-ce-pe{background:rgba(239,83,80,.1);color:#EF9A9A;border:1px solid rgba(239,83,80,.22);}
.cond-pe-ce{background:rgba(38,166,154,.1);color:#4DB6AC;border:1px solid rgba(38,166,154,.22);}
.cond-both{background:rgba(171,71,188,.1);color:var(--c-purple);border:1px solid rgba(171,71,188,.22);}
.cond-flat{background:var(--c-panel);color:var(--c-muted);border:1px solid var(--c-border);}
.rank-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:700;}
.rank-1{background:rgba(239,83,80,.1);color:#EF9A9A;border:1px solid rgba(239,83,80,.22);}
.rank-2{background:rgba(255,167,38,.1);color:var(--c-amber);border:1px solid rgba(255,167,38,.22);}
.rank-3{background:rgba(0,184,212,.1);color:var(--c-blue);border:1px solid rgba(0,184,212,.22);}
.rank-4{background:rgba(38,166,154,.1);color:#4DB6AC;border:1px solid rgba(38,166,154,.22);}
.rank-n{background:var(--c-panel);color:var(--c-muted);border:1px solid var(--c-border);}
/* loading / empty */
.ofs-empty{text-align:center;padding:52px 20px;color:var(--c-muted);}
.ofs-empty-icon{width:52px;height:52px;border-radius:50%;background:var(--c-panel);border:1px solid var(--c-border);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:20px;}
.ofs-empty p{font-size:12px;font-family:var(--f-mono);margin-top:4px;}
.ofs-spinner-row{display:flex;align-items:center;justify-content:center;gap:12px;padding:52px;color:var(--c-muted);font-size:12px;font-family:var(--f-mono);}
.ofs-spinner{width:28px;height:28px;border:2px solid var(--c-border2);border-top:2px solid var(--c-lime);border-radius:50%;animation:ofsSpin .9s linear infinite;flex-shrink:0;}
</style>

<div class="ofs-wrap">

<div class="ofs-hero ofs-anim">
    <div class="ofs-hero-left">
        <div class="ofs-eyebrow">Options Analytics</div>
        <h1>OI Flow <span>Sentiment</span> Analyzer</h1>
        <p>Analyzes CE and PE Open Interest changes to determine overall market sentiment — helping you identify whether smart money is positioned bullish or bearish.</p>
    </div>
    <div class="ofs-hero-icon"><i class="las la-wave-square"></i></div>
</div>

<div class="ofs-filter-bar">
    <div class="ofs-filter-inner">
        <span class="ofs-filter-label">Symbol</span>
        <select id="ofs-sym" class="ofs-select" onchange="ofsAnalyze()"><option value="ALL">— All —</option></select>
        <div class="ofs-sep"></div>
        <span class="ofs-filter-label">Date</span>
        <div class="ofs-date-wrap">
            <button class="ofs-date-nav" onclick="ofsShiftDate(-1)">‹</button>
            <input type="date" id="ofs-date" class="ofs-date-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="ofsAnalyze()">
            <button class="ofs-date-nav" onclick="ofsShiftDate(1)">›</button>
            <button class="ofs-date-nav ofs-today-btn" onclick="ofsGoToday()">TODAY</button>
            <span id="ofs-date-badge"></span>
        </div>
        <div class="ofs-sep"></div>
        <span class="ofs-filter-label">Action</span>
        <select id="ofs-action" class="ofs-select" onchange="ofsAnalyze()">
            <option value="">All Actions</option>
            <option value="BUY CE">BUY CE Only</option>
            <option value="BUY PE">BUY PE Only</option>
            <option value="WAIT">WAIT Only</option>
        </select>
        <button class="ofs-analyze-btn" onclick="ofsAnalyze()"><i class="las la-search"></i> Analyze</button>
        <button class="ofs-reset-btn" onclick="ofsReset()">↺ Reset</button>
        <div class="ofs-filter-right">
            <span class="ofs-info-text" id="ofs-info"></span>
            <span class="ofs-upd-text"  id="ofs-upd"></span>
        </div>
    </div>
</div>

<div class="ofs-content">
    <div class="ofs-warn" id="ofs-warn"><i class="las la-exclamation-triangle"></i><div><strong>No Analysis Config Found</strong><div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="ofs-warn-msg">Go to Admin → Analysis Config and create a config with symbols.</div></div></div>

    <div class="ofs-stats ofs-anim">
        <div class="ofs-stat-card s-total"><div class="ofs-stat-label">Total</div>  <div class="ofs-stat-val" id="st-total">—</div></div>
        <div class="ofs-stat-card s-ce">  <div class="ofs-stat-label">BUY CE</div> <div class="ofs-stat-val" id="st-ce">—</div></div>
        <div class="ofs-stat-card s-pe">  <div class="ofs-stat-label">BUY PE</div> <div class="ofs-stat-val" id="st-pe">—</div></div>
        <div class="ofs-stat-card s-wait"><div class="ofs-stat-label">WAIT</div>   <div class="ofs-stat-val" id="st-wait">—</div></div>
        <div class="ofs-stat-card s-bull"><div class="ofs-stat-label">Bullish</div><div class="ofs-stat-val" id="st-bull">—</div></div>
        <div class="ofs-stat-card s-bear"><div class="ofs-stat-label">Bearish</div><div class="ofs-stat-val" id="st-bear">—</div></div>
    </div>

    <div class="ofs-card ofs-anim d1">
        <div class="ofs-card-header">
            <div class="ofs-card-title">⊙ OI Flow Sentiment</div>
            <span class="ofs-card-subtitle" id="ofs-subtitle">Detecting last available date…</span>
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
                        <th>#</th><th>Date</th><th>Symbol</th><th>ATM / FUT</th><th>Expiry</th>
                        <th class="sep-oi">CE OI<br><span style="font-size:7px;font-weight:400;opacity:.6;">Today / Prev</span></th>
                        <th>CE Chg %</th>
                        <th>PE OI<br><span style="font-size:7px;font-weight:400;opacity:.6;">Today / Prev</span></th>
                        <th>PE Chg %</th>
                        <th class="sep-signal">Sentiment</th><th>Condition</th><th>Strength</th><th>Action</th>
                    </tr>
                </thead>
                <tbody id="ofs-tbody">
                    <tr><td colspan="13"><div class="ofs-spinner-row"><div class="ofs-spinner"></div>Detecting last available date…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

@endsection
@push('script')
<script>
/* ═══ OI Flow Sentiment — JS (all logic identical) ═══ */
var OFS_ANALYZE='{{route("oi-flow-sentiment.analyze")}}',OFS_SYMBOLS='{{route("oi-flow-sentiment.symbols")}}',OFS_LASTDATE='{{route("oi-flow-sentiment.last.date")}}',OFS_TODAY='{{now()->toDateString()}}';
var ofsSymCache=null;
function el(id){return document.getElementById(id);}
function html(id,h){var e=el(id);if(e)e.innerHTML=h;}
function txt(id,t){var e=el(id);if(e)e.textContent=t;}
document.addEventListener('DOMContentLoaded',function(){ofsResolveLastDateAndLoad();});
function ofsResolveLastDateAndLoad(){fetch(OFS_LASTDATE,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){if(res.last_date)el('ofs-date').value=res.last_date;ofsLoadSymbols(function(){ofsAnalyze();});}).catch(function(){ofsLoadSymbols(function(){ofsAnalyze();});});}
function ofsGetDate(){return el('ofs-date').value;}
function ofsShiftDate(d){var p=el('ofs-date'),dt=new Date(p.value);dt.setDate(dt.getDate()+d);var s=dt.toISOString().split('T')[0];if(s>OFS_TODAY)return;p.value=s;ofsAnalyze();}
function ofsGoToday(){el('ofs-date').value=OFS_TODAY;ofsAnalyze();}
function ofsUpdateDateBadge(isToday){el('ofs-date-badge').innerHTML=isToday?'<span class="ofs-live-badge">● Live</span>':'<span class="ofs-hist-badge">📅 Historical</span>';}
function ofsLoadSymbols(callback){if(ofsSymCache!==null){ofsRebuildSym(ofsSymCache);if(callback)callback();return;}fetch(OFS_SYMBOLS,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){if(res.no_config){ofsShowWarn(res.message||'');ofsSymCache=[];ofsRebuildSym([]);}else{ofsHideWarn();ofsSymCache=res.symbols||[];ofsRebuildSym(ofsSymCache);}if(callback)callback();}).catch(function(){if(callback)callback();});}
function ofsRebuildSym(syms){var sel=el('ofs-sym'),prev=sel.value,opts='<option value="ALL">— All Symbols —</option>';syms.forEach(function(s){opts+='<option value="'+s+'"'+(s===prev?' selected':'')+'>'+s+'</option>';});sel.innerHTML=opts;if(prev&&prev!=='ALL'){sel.value=prev;if(sel.value!==prev)sel.value='ALL';}}
function ofsAnalyze(){var date=ofsGetDate(),action=el('ofs-action').value,sym=el('ofs-sym').value;if(!date)return;ofsHideWarn();ofsResetStats();html('ofs-tbody','<tr><td colspan="13"><div class="ofs-spinner-row"><div class="ofs-spinner"></div>Calculating CE/PE OI flow for '+date+'…</div></td></tr>');txt('ofs-subtitle',date+' · Loading…');var params=new URLSearchParams({date:date,filter_action:action});if(sym&&sym!=='ALL')params.append('symbols[]',sym);fetch(OFS_ANALYZE+'?'+params.toString(),{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){if(!r.ok)throw new Error('Server error '+r.status);return r.json();}).then(function(res){if(typeof res.is_today!=='undefined')ofsUpdateDateBadge(res.is_today);if(res.available_symbols&&res.available_symbols.length){ofsSymCache=res.available_symbols;ofsRebuildSym(ofsSymCache);if(sym&&sym!=='ALL')el('ofs-sym').value=sym;}if(res.no_config){ofsShowWarn(res.message);ofsEmptyTable('No active config.');return;}if(!res.success||!res.data||!res.data.length){ofsEmptyTable(res.message||'No signals found for this date.');ofsResetStats();txt('ofs-subtitle',date+' · No data found');return;}ofsUpdateStats(res);ofsRenderTable(res.data);el('ofs-info').innerHTML='<span style="color:#80CBC4;">CE: '+res.buy_ce_count+'</span> &nbsp;·&nbsp; <span style="color:#EF9A9A;">PE: '+res.buy_pe_count+'</span>';txt('ofs-subtitle',date+' · '+res.message);txt('ofs-upd','Updated '+new Date().toLocaleTimeString());}).catch(function(err){ofsEmptyTable('⚠ '+err.message);});}
function ofsRenderTable(data){var h='',num=1;data.forEach(function(r,i){var isBull=r.sentiment==='BULLISH',isBear=r.sentiment==='BEARISH';var rowCls=(isBull?'tr-bull':isBear?'tr-bear':'');var zebra=i%2===0?'tr-even':'tr-odd';var sentBadge=isBull?'<span class="sig-bull">▲ BULLISH</span>':isBear?'<span class="sig-bear">▼ BEARISH</span>':'<span class="sig-neut">— NEUTRAL</span>';var actBadge=r.trade_action==='BUY CE'?'<span class="act-ce">📈 BUY CE</span>':r.trade_action==='BUY PE'?'<span class="act-pe">📉 BUY PE</span>':'<span class="act-wt">⏸ WAIT</span>';var cond=r.condition||'';var condCls='cond-base cond-flat';if(cond.includes('CE ↑')&&cond.includes('PE ↓'))condCls='cond-base cond-ce-pe';else if(cond.includes('CE ↓')&&cond.includes('PE ↑'))condCls='cond-base cond-pe-ce';else if(cond.includes('Both'))condCls='cond-base cond-both';var rankMap={'Rank 1':'rank-badge rank-1','Rank 2':'rank-badge rank-2','Rank 3':'rank-badge rank-3','Rank 4':'rank-badge rank-4','Normal':'rank-badge rank-n'};var rankCls=rankMap[r.strength_rank]||'rank-badge rank-n';h+='<tr class="'+rowCls+' '+zebra+'">'+'<td class="c-num">'+num+++'</td>'+'<td class="c-date">'+r.date+'</td>'+'<td class="c-sym">'+esc(r.symbol)+'</td>'+'<td>'+(r.atm_strike?'<span class="c-atm">₹'+nInt(r.atm_strike)+'</span>':'—')+(r.fut_price?'<br><span class="c-fut">F:₹'+f(r.fut_price)+'</span>':'')+'</td>'+'<td class="c-expiry">'+(r.expiry||'—')+'</td>'+'<td class="sep-oi c-oi">'+nInt(r.ce_oi)+'</td>'+'<td>'+pctCell(r.ce_oi_pct)+'</td>'+'<td class="c-oi">'+nInt(r.pe_oi)+'</td>'+'<td>'+pctCell(r.pe_oi_pct)+'</td>'+'<td class="sep-signal">'+sentBadge+'</td>'+'<td><span class="'+condCls+'">'+esc(cond)+'</span></td>'+'<td><span class="'+rankCls+'">'+r.strength_rank+'</span></td>'+'<td>'+actBadge+'</td>'+'</tr>';});html('ofs-tbody',h||ofsEmptyHtml('No results.'));}
function ofsUpdateStats(res){txt('st-total',res.total_records||'0');txt('st-ce',res.buy_ce_count||'0');txt('st-pe',res.buy_pe_count||'0');txt('st-wait',res.wait_count||'0');txt('st-bull',res.bullish_count||'0');txt('st-bear',res.bearish_count||'0');}
function ofsResetStats(){['st-total','st-ce','st-pe','st-wait','st-bull','st-bear'].forEach(function(id){txt(id,'—');});}
function ofsShowWarn(msg){el('ofs-warn').classList.add('show');txt('ofs-warn-msg',msg||'');}
function ofsHideWarn(){el('ofs-warn').classList.remove('show');}
function ofsEmptyTable(msg){html('ofs-tbody',ofsEmptyHtml(msg));}
function ofsEmptyHtml(msg){return'<tr><td colspan="13"><div class="ofs-empty"><div class="ofs-empty-icon"><i class="las la-chart-bar"></i></div><p>'+(msg||'No data found.')+'</p></div></td></tr>';}
function ofsReset(){fetch(OFS_LASTDATE,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){el('ofs-date').value=res.last_date||OFS_TODAY;el('ofs-action').value='';el('ofs-sym').value='ALL';ofsHideWarn();ofsAnalyze();}).catch(function(){el('ofs-date').value=OFS_TODAY;el('ofs-action').value='';el('ofs-sym').value='ALL';ofsHideWarn();ofsAnalyze();});}
function pctCell(v){if(v==null)return'<span class="pct-neu">—</span>';var n=parseFloat(v)||0,cls=n>0?'pct-up':n<0?'pct-down':'pct-neu';return'<span class="'+cls+'">'+(n>0?'+':'')+n.toFixed(2)+'%</span>';}
function f(v){return parseFloat(v||0).toFixed(2);}
function nInt(v){var n=Number(v)||0;if(n>=1e7)return(n/1e7).toFixed(2)+'Cr';if(n>=1e5)return(n/1e5).toFixed(2)+'L';if(n>=1e3)return(n/1e3).toFixed(1)+'K';return n.toLocaleString('en-IN');}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
</script>
@endpush