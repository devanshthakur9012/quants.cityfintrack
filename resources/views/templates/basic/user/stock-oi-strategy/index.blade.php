{{-- FILE: resources/views/themes/{active_theme}/user/stock-oi-strategy/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
    --c-bg:#0B0E11;--c-surface:#131722;--c-panel:#1C2030;
    --c-border:rgba(255,255,255,.06);--c-border2:rgba(255,255,255,.11);
    --c-lime:#7DFF00;--c-blue:#00B8D4;--c-red:#EF5350;--c-teal:#26A69A;
    --c-amber:#FFA726;--c-purple:#AB47BC;
    --c-text:#D1D4DC;--c-muted:#787B86;
    --f-sans:'DM Sans',system-ui,sans-serif;--f-display:'Syne',sans-serif;--f-mono:'Space Grotesk',monospace;
}
.sos-wrap{font-family:var(--f-sans);color:var(--c-text);background:var(--c-bg);}
.sos-wrap *{box-sizing:border-box;}
.mono{font-family:var(--f-mono);}
@keyframes sosUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.sos-anim{animation:sosUp .5s ease both;}
@keyframes sosSpin{to{transform:rotate(360deg);}}

/* HERO */
.sos-hero{position:relative;overflow:hidden;background:var(--c-bg);border-bottom:1px solid var(--c-border);padding:36px 32px;display:flex;align-items:center;justify-content:space-between;gap:24px;}
.sos-hero::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 35% 70% at 5% 50%,rgba(125,255,0,.04),transparent 70%);pointer-events:none;}
.sos-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--c-lime);margin-bottom:10px;}
.sos-eyebrow::before{content:'';display:block;width:16px;height:1px;background:var(--c-lime);}
.sos-hero h1{font-family:var(--f-display);font-size:clamp(22px,3.5vw,34px);font-weight:800;color:#fff;line-height:1.1;letter-spacing:-.015em;margin-bottom:10px;}
.sos-hero h1 span{color:var(--c-lime);}
.sos-hero p{font-size:13px;color:var(--c-muted);line-height:1.7;max-width:640px;}
@media(max-width:768px){.sos-hero{padding:24px 18px;}}

/* SYMBOL TABS */
.sos-tabs{display:flex;gap:8px;padding:14px 32px 0;background:var(--c-surface);border-bottom:1px solid var(--c-border);flex-wrap:wrap;}
.sos-tab{background:var(--c-panel);border:1px solid var(--c-border2);border-bottom:none;border-radius:8px 8px 0 0;padding:9px 18px;font-family:var(--f-display);font-size:12px;font-weight:700;letter-spacing:.04em;color:var(--c-muted);cursor:pointer;transition:all .2s;}
.sos-tab.active{color:#000;background:var(--c-lime);border-color:var(--c-lime);}
.sos-tab .eng-tag{display:block;font-family:var(--f-mono);font-size:8px;font-weight:600;letter-spacing:.06em;opacity:.7;margin-top:2px;}
@media(max-width:768px){.sos-tabs{padding:12px 16px 0;}}

/* FILTER BAR */
.sos-filter-bar{background:var(--c-surface);border-bottom:1px solid var(--c-border);padding:0 32px;position:sticky;top:0;z-index:200;box-shadow:0 4px 24px rgba(0,0,0,.3);}
.sos-filter-inner{display:flex;align-items:center;gap:12px;padding:11px 0;flex-wrap:wrap;}
.sos-filter-label{font-size:10px;color:var(--c-muted);font-weight:700;text-transform:uppercase;letter-spacing:.1em;font-family:var(--f-mono);flex-shrink:0;}
.sos-sep{width:1px;height:26px;background:var(--c-border2);flex-shrink:0;}
.sos-date-input{background:var(--c-panel);border:1px solid var(--c-border2);border-radius:7px;font-family:var(--f-mono);font-size:12px;font-weight:600;color:var(--c-text);outline:none;padding:6px 10px;}
.sos-date-input::-webkit-calendar-picker-indicator{filter:invert(1) opacity(.4);cursor:pointer;}
.sos-date-nav{width:28px;height:30px;background:var(--c-panel);border:1px solid var(--c-border2);border-radius:6px;color:var(--c-muted);cursor:pointer;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;}
.sos-date-nav:hover{border-color:rgba(125,255,0,.3);color:var(--c-lime);}
.sos-today-btn{width:auto;padding:0 10px;font-size:9px;font-family:var(--f-mono);font-weight:700;letter-spacing:.1em;}
.sos-live-badge{background:rgba(38,166,154,.12);color:#4DB6AC;border:1px solid rgba(38,166,154,.25);border-radius:100px;font-size:10px;font-weight:700;padding:2px 9px;font-family:var(--f-mono);}
.sos-hist-badge{background:rgba(255,167,38,.1);color:var(--c-amber);border:1px solid rgba(255,167,38,.25);border-radius:100px;font-size:10px;font-weight:700;padding:2px 9px;font-family:var(--f-mono);}
.sos-analyze-btn{background:var(--c-lime);color:#000;border:none;border-radius:7px;padding:7px 20px;font-family:var(--f-display);font-size:12px;font-weight:700;letter-spacing:.06em;cursor:pointer;box-shadow:0 0 14px rgba(125,255,0,.2);}
.sos-analyze-btn:hover{background:#8FFF1A;}
.sos-filter-right{margin-left:auto;display:flex;align-items:center;gap:10px;}
.sos-upd-text{font-size:10px;color:rgba(120,123,134,.45);font-family:var(--f-mono);}
@media(max-width:768px){.sos-filter-bar{padding:0 16px;}}

/* CONTENT */
.sos-content{padding:24px 32px 64px;}
@media(max-width:768px){.sos-content{padding:16px 12px 48px;}}
.sos-warn{background:rgba(255,167,38,.08);border:1px solid rgba(255,167,38,.25);border-radius:9px;padding:14px 18px;margin-bottom:18px;display:none;align-items:center;gap:12px;font-size:13px;color:var(--c-amber);}
.sos-warn.show{display:flex;}

/* SIGNAL BANNER */
.sos-signal-banner{border-radius:12px;padding:22px 26px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;border:1px solid var(--c-border2);}
.sos-signal-banner.sig-ce,.sos-signal-banner.sig-buy{background:linear-gradient(135deg,rgba(38,166,154,.12),rgba(38,166,154,.03));border-color:rgba(38,166,154,.3);}
.sos-signal-banner.sig-pe,.sos-signal-banner.sig-sell{background:linear-gradient(135deg,rgba(239,83,80,.1),rgba(239,83,80,.03));border-color:rgba(239,83,80,.3);}
.sos-signal-banner.sig-none{background:var(--c-surface);}
.sos-signal-left .sig-label{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--c-muted);font-family:var(--f-mono);margin-bottom:6px;}
.sos-signal-left .sig-val{font-family:var(--f-display);font-size:30px;font-weight:800;color:#fff;}
.sos-signal-left .sig-val.ce{color:#4DB6AC;} .sos-signal-left .sig-val.pe{color:#EF9A9A;}
.sos-signal-reasons{display:flex;flex-wrap:wrap;gap:6px;max-width:460px;}
.sos-reason-chip{background:rgba(255,255,255,.05);border:1px solid var(--c-border2);border-radius:100px;padding:4px 12px;font-size:10px;font-family:var(--f-mono);color:var(--c-text);}
.sos-score-box{text-align:right;}
.sos-score-box .score-val{font-family:var(--f-display);font-size:24px;font-weight:800;color:#fff;}
.sos-score-box .score-label{font-size:9px;color:var(--c-muted);text-transform:uppercase;letter-spacing:.1em;font-family:var(--f-mono);}

/* GRID CARDS */
.sos-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-bottom:20px;}
.sos-metric{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:14px 16px;}
.sos-metric-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--c-muted);margin-bottom:6px;font-family:var(--f-mono);}
.sos-metric-val{font-family:var(--f-display);font-size:19px;font-weight:800;color:#fff;}
.sos-metric-sub{font-size:10px;color:var(--c-muted);font-family:var(--f-mono);margin-top:2px;}
.pct-up{color:#80CBC4;} .pct-down{color:#EF9A9A;} .pct-neu{color:var(--c-muted);}

/* CARD */
.sos-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;overflow:hidden;margin-bottom:18px;}
.sos-card-header{padding:13px 18px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;justify-content:space-between;background:rgba(0,0,0,.2);}
.sos-card-title{font-family:var(--f-display);font-size:14px;font-weight:700;color:var(--c-text);}
.sos-tscroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}

/* TABLE */
.sos-table{width:100%;border-collapse:collapse;font-family:var(--f-mono);min-width:760px;}
.sos-table thead th{padding:8px 10px;text-align:center;font-size:9px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:rgba(0,0,0,.25);color:var(--c-muted);border-bottom:1px solid var(--c-border);white-space:nowrap;}
.sos-table tbody td{padding:7px 10px;text-align:center;font-size:11px;border-bottom:1px solid var(--c-border);color:var(--c-muted);white-space:nowrap;}
.sos-table tbody tr:hover td{background:rgba(255,255,255,.02);}
.tr-buy{background:rgba(38,166,154,.04);} .tr-sell{background:rgba(239,83,80,.04);}
.sig-badge{display:inline-block;padding:3px 10px;border-radius:5px;font-size:10px;font-weight:800;font-family:var(--f-sans);}
.sig-buy-b{background:rgba(38,166,154,.12);color:#4DB6AC;border:1px solid rgba(38,166,154,.3);}
.sig-sell-b{background:rgba(239,83,80,.1);color:#EF9A9A;border:1px solid rgba(239,83,80,.3);}
.sig-wait-b{background:var(--c-panel);color:var(--c-muted);border:1px solid var(--c-border2);}

/* PROFILE STRIP */
.sos-profile-list{display:flex;flex-direction:column;gap:8px;padding:14px 18px;}
.sos-profile-row{display:flex;align-items:center;gap:10px;font-size:11px;font-family:var(--f-mono);}
.sos-profile-key{width:170px;color:var(--c-text);font-weight:700;flex-shrink:0;}
.sos-profile-bar{flex:1;height:6px;background:var(--c-panel);border-radius:4px;overflow:hidden;}
.sos-profile-bar-fill{height:100%;background:var(--c-lime);}
.sos-profile-n{width:70px;text-align:right;color:var(--c-muted);flex-shrink:0;}

/* EMPTY / LOADING */
.sos-empty{text-align:center;padding:52px 20px;color:var(--c-muted);}
.sos-spinner-row{display:flex;align-items:center;justify-content:center;gap:12px;padding:52px;color:var(--c-muted);font-size:12px;font-family:var(--f-mono);}
.sos-spinner{width:28px;height:28px;border:2px solid var(--c-border2);border-top:2px solid var(--c-lime);border-radius:50%;animation:sosSpin .9s linear infinite;}
</style>

<div class="sos-wrap">

<div class="sos-hero sos-anim">
    <div>
        <div class="sos-eyebrow">Options Analytics</div>
        <h1>Stock OI <span>Strategy</span> Engine</h1>
        <p>Per-stock intraday OI strategies: LICHSGFIN's rule-based 10:30 gap-fail engine, plus PAYTM/SHRIRAMFIN's adaptive OI-learning engine that blends each stock's own historical CE/PE behaviour with standard OI interpretation.</p>
    </div>
</div>

<div class="sos-tabs">
    <div class="sos-tab" data-symbol="LICHSGFIN" onclick="sosSelectSymbol('LICHSGFIN')">LICHSGFIN<span class="eng-tag">10:30 GAP-FAIL</span></div>
    <div class="sos-tab" data-symbol="PAYTM" onclick="sosSelectSymbol('PAYTM')">PAYTM<span class="eng-tag">ADAPTIVE LEARNING</span></div>
    <div class="sos-tab" data-symbol="SHRIRAMFIN" onclick="sosSelectSymbol('SHRIRAMFIN')">SHRIRAMFIN<span class="eng-tag">ADAPTIVE LEARNING</span></div>
</div>

<div class="sos-filter-bar">
    <div class="sos-filter-inner">
        <span class="sos-filter-label">Date</span>
        <button class="sos-date-nav" onclick="sosShiftDate(-1)">‹</button>
        <input type="date" id="sos-date" class="sos-date-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="sosAnalyze()">
        <button class="sos-date-nav" onclick="sosShiftDate(1)">›</button>
        <button class="sos-date-nav sos-today-btn" onclick="sosGoToday()">TODAY</button>
        <span id="sos-date-badge"></span>
        <div class="sos-sep"></div>
        <button class="sos-analyze-btn" onclick="sosAnalyze()"><i class="las la-search"></i> Analyze</button>
        <div class="sos-filter-right">
            <span class="sos-upd-text" id="sos-upd"></span>
        </div>
    </div>
</div>

<div class="sos-content">
    <div class="sos-warn" id="sos-warn"><i class="las la-exclamation-triangle"></i>
        <div><strong>No Analysis Config Found</strong><div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="sos-warn-msg">Go to Admin → Analysis Config and create a config with symbols.</div></div>
    </div>

    <div id="sos-body">
        <div class="sos-spinner-row"><div class="sos-spinner"></div>Detecting last available date…</div>
    </div>
</div>
</div>

@endsection
@push('script')
<script>
var SOS_ANALYZE='{{route("stock-oi-strategy.analyze")}}',SOS_LASTDATE='{{route("stock-oi-strategy.last.date")}}',SOS_TODAY='{{now()->toDateString()}}';
var sosSymbol='LICHSGFIN';
function el(id){return document.getElementById(id);}
function html(id,h){var e=el(id);if(e)e.innerHTML=h;}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function f(v,d){d=d===undefined?2:d;return (v===null||v===undefined)?'—':parseFloat(v).toFixed(d);}

document.addEventListener('DOMContentLoaded',function(){sosSetActiveTab();sosResolveLastDateAndLoad();});

function sosResolveLastDateAndLoad(){
    fetch(SOS_LASTDATE,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(function(res){
        if(res.last_date) el('sos-date').value=res.last_date;
        sosAnalyze();
    }).catch(function(){sosAnalyze();});
}
function sosSelectSymbol(sym){sosSymbol=sym;sosSetActiveTab();sosAnalyze();}
function sosSetActiveTab(){document.querySelectorAll('.sos-tab').forEach(function(t){t.classList.toggle('active',t.dataset.symbol===sosSymbol);});}
function sosGetDate(){return el('sos-date').value;}
function sosShiftDate(d){var p=el('sos-date'),dt=new Date(p.value);dt.setDate(dt.getDate()+d);var s=dt.toISOString().split('T')[0];if(s>SOS_TODAY)return;p.value=s;sosAnalyze();}
function sosGoToday(){el('sos-date').value=SOS_TODAY;sosAnalyze();}
function sosUpdateDateBadge(isToday){el('sos-date-badge').innerHTML=isToday?'<span class="sos-live-badge">● Live</span>':'<span class="sos-hist-badge">📅 Historical</span>';}
function sosHideWarn(){el('sos-warn').classList.remove('show');}
function sosShowWarn(msg){el('sos-warn').classList.add('show');el('sos-warn-msg').textContent=msg||'';}

function sosAnalyze(){
    var date=sosGetDate();
    if(!date)return;
    sosHideWarn();
    html('sos-body','<div class="sos-spinner-row"><div class="sos-spinner"></div>Analyzing '+sosSymbol+' for '+date+'…</div>');
    var params=new URLSearchParams({date:date,symbol:sosSymbol});
    fetch(SOS_ANALYZE+'?'+params.toString(),{headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){if(!r.ok)throw new Error('Server error '+r.status);return r.json();})
        .then(function(res){
            if(typeof res.is_today!=='undefined')sosUpdateDateBadge(res.is_today);
            if(res.no_config){sosShowWarn(res.message);html('sos-body',sosEmptyHtml('No active config.'));return;}
            if(!res.success){html('sos-body',sosEmptyHtml(res.message||'No data found.'));return;}
            if(res.engine==='GAP_FAIL_1030') sosRenderLichsgfin(res.data);
            else sosRenderAdaptive(res.data,res.symbol);
            el('sos-upd').textContent='Updated '+new Date().toLocaleTimeString();
        })
        .catch(function(err){html('sos-body',sosEmptyHtml('⚠ '+err.message));});
}
function sosEmptyHtml(msg){return '<div class="sos-card"><div class="sos-empty">'+esc(msg||'No data found.')+'</div></div>';}

/* ── LICHSGFIN (gap-fail engine) render ── */
function sosRenderLichsgfin(d){
    if(!d||d.signal===undefined){html('sos-body',sosEmptyHtml('No data.'));return;}
    if(d.signal==='NO_TRADE' && d.reason && typeof d.reason==='string' && !d.gap_pct){
        html('sos-body',sosEmptyHtml(d.reason));return;
    }
    var isCe=d.signal==='BUY_CE',isPe=d.signal==='BUY_PE';
    var bannerCls=isCe?'sig-ce':(isPe?'sig-pe':'sig-none');
    var sigVal=isCe?'BUY CE':(isPe?'BUY PE':'NO TRADE');
    var sigCls=isCe?'ce':(isPe?'pe':'');
    var reasons=(d.reason||[]).map(function(r){return '<span class="sos-reason-chip">'+esc(r)+'</span>';}).join('');

    var h='';
    h+='<div class="sos-signal-banner '+bannerCls+'">';
    h+='  <div class="sos-signal-left"><div class="sig-label">LICHSGFIN · 10:30 Signal</div><div class="sig-val '+sigCls+'">'+sigVal+'</div></div>';
    h+='  <div class="sos-signal-reasons">'+reasons+'</div>';
    h+='  <div class="sos-score-box"><div class="score-val">'+f(d.ce_buy_score,1)+' / '+f(d.pe_buy_score,1)+'</div><div class="score-label">CE / PE Buy Score</div></div>';
    h+='</div>';

    h+='<div class="sos-grid">';
    h+=sosMetric('Gap %',f(d.gap_pct,3)+'%',(d.gap_down?'Gap Down':d.gap_up?'Gap Up':'Flat Open'));
    h+=sosMetric('Gap Failed?',(d.gap_down_failed?'DOWN FAILED':d.gap_up_failed?'UP FAILED':'No'),'');
    h+=sosMetric('Price From Open',f(d.price_from_open_pct,3)+'%','vs 09:15 open');
    h+=sosMetric('Price vs Prev Close',f(d.price_vs_previous_close_pct,3)+'%','');
    h+=sosMetric('Future OI Δ',f(d.future_oi_from_open_pct,3)+'%','since open');
    h+=sosMetric('Range Position',f(d.close_position_in_opening_range,3),'0=low,1=high');
    h+='</div>';

    h+=sosProgressionCard('CE Progression',d.ce_progression,d.ce_consistency);
    h+=sosProgressionCard('PE Progression',d.pe_progression,d.pe_consistency);

    html('sos-body',h);
}
function sosMetric(label,val,sub){
    return '<div class="sos-metric"><div class="sos-metric-label">'+esc(label)+'</div><div class="sos-metric-val">'+esc(val)+'</div>'+(sub?'<div class="sos-metric-sub">'+esc(sub)+'</div>':'')+'</div>';
}
function sosProgressionCard(title,prog,consistency){
    if(!prog||!prog.details)return '';
    var rows='';
    Object.keys(prog.details).forEach(function(k){
        var x=prog.details[k];
        rows+='<tr><td style="text-align:left;color:#fff;">'+esc(k)+'</td><td>'+esc(x.behaviour)+'</td><td>'+f(x.premium_change_pct)+'%</td><td>'+f(x.oi_change_pct)+'%</td><td>'+f(x.score,2)+'</td></tr>';
    });
    var cons=consistency?('Bull steps: '+consistency.bull+' · Bear steps: '+consistency.bear+' · Neutral: '+consistency.neutral):'';
    return '<div class="sos-card"><div class="sos-card-header"><div class="sos-card-title">'+esc(title)+' (score: '+f(prog.score,2)+')</div><span class="mono" style="font-size:10px;color:var(--c-muted);">'+esc(cons)+'</span></div>'+
        '<div class="sos-tscroll"><table class="sos-table"><thead><tr><th>Strike</th><th>Behaviour</th><th>Premium %</th><th>OI %</th><th>Score</th></tr></thead><tbody>'+rows+'</tbody></table></div></div>';
}

/* ── PAYTM / SHRIRAMFIN (adaptive engine) render ── */
function sosRenderAdaptive(d,symbol){
    if(!d||!d.latest||d.latest.signal===undefined || (d.latest.reason && !d.latest.oi_score)){
        html('sos-body',sosEmptyHtml((d&&d.latest&&d.latest.reason)?d.latest.reason:'No data.'));return;
    }
    var L=d.latest;
    var isBuy=L.signal==='BUY',isSell=L.signal==='SELL';
    var bannerCls=isBuy?'sig-buy':(isSell?'sig-sell':'sig-none');
    var sigCls=isBuy?'ce':(isSell?'pe':'');

    var h='';
    h+='<div class="sos-signal-banner '+bannerCls+'">';
    h+='  <div class="sos-signal-left"><div class="sig-label">'+esc(symbol)+' · Intraday Signal ('+esc(L.date)+')</div><div class="sig-val '+sigCls+'">'+esc(L.signal)+'</div></div>';
    h+='  <div class="sos-signal-reasons"><span class="sos-reason-chip">Gap: '+esc(L.gap_type)+' ('+f(L.gap_pct,3)+'%)</span><span class="sos-reason-chip">Reversal: '+esc(L.reversal_state)+'</span><span class="sos-reason-chip">Range: '+esc(L.range_state)+'</span><span class="sos-reason-chip">OI: '+esc(L.oi_label)+'</span></div>';
    h+='  <div class="sos-score-box"><div class="score-val">'+f(L.signal_score,1)+'/100</div><div class="score-label">Confidence '+f(L.signal_confidence,0)+'%</div></div>';
    h+='</div>';

    h+='<div class="sos-grid">';
    h+=sosMetric('Previous Close',f(L.previous_close),'');
    h+=sosMetric('Open',f(L.open),'');
    h+=sosMetric('Last Close',f(L.last_close),f(L.today_return_pct,3)+'% today');
    h+=sosMetric('OI Score',f(L.oi_score,3),'-1 bearish / +1 bullish');
    h+=sosMetric('Opening Range',f(L.opening_range.low)+' – '+f(L.opening_range.high),'');
    h+=sosMetric('Overnight Signal',L.overnight_signal,f(L.overnight_score,1)+'/100 · '+f(L.overnight_confidence,0)+'% conf');
    h+='</div>';

    // Gap/reversal historical study
    if(d.gap_reversal_study){
        var rows='';
        Object.keys(d.gap_reversal_study).forEach(function(k){
            var s=d.gap_reversal_study[k];
            rows+='<tr><td style="text-align:left;color:#fff;">'+esc(k)+'</td><td>'+s.n+'</td><td>'+f(s.bullish_outcome_pct,1)+'%</td><td>'+f(s.avg_return_pct,3)+'%</td></tr>';
        });
        h+='<div class="sos-card"><div class="sos-card-header"><div class="sos-card-title">Gap + Reversal Historical Study</div></div>'+
           '<div class="sos-tscroll"><table class="sos-table"><thead><tr><th>Gap Type</th><th>N</th><th>Bullish Outcome</th><th>Avg Return</th></tr></thead><tbody>'+(rows||'<tr><td colspan="4">Not enough history yet.</td></tr>')+'</tbody></table></div></div>';
    }

    // Backtest summary + rows
    if(d.backtest_summary){
        var bs=d.backtest_summary;
        h+='<div class="sos-grid">';
        ['BUY','SELL'].forEach(function(side){
            var s=bs[side]||{};
            h+=sosMetric(side+' Backtest',(s.trades||0)+' trades',s.win_rate_pct!=null?('Win rate '+f(s.win_rate_pct,1)+'% · Avg '+f(s.avg_return_pct,3)+'%'):'No trades');
        });
        h+='</div>';
    }
    if(d.backtest_rows && d.backtest_rows.length){
        var brows='';
        d.backtest_rows.forEach(function(r){
            var cls=r.signal==='BUY'?'tr-buy':(r.signal==='SELL'?'tr-sell':'');
            var badge=r.signal==='BUY'?'sig-buy-b':(r.signal==='SELL'?'sig-sell-b':'sig-wait-b');
            brows+='<tr class="'+cls+'"><td style="color:#fff;">'+esc(r.date)+'</td><td><span class="sig-badge '+badge+'">'+esc(r.signal)+'</span></td><td>'+f(r.score,1)+'</td><td>'+esc(r.gap_type)+' ('+f(r.gap_pct,2)+'%)</td><td>'+esc(r.reversal_state)+'</td><td>'+esc(r.range_state)+'</td><td>'+f(r.oi_score,2)+'</td><td>'+(r.future_return_pct!=null?f(r.future_return_pct,2)+'%':'—')+'</td></tr>';
        });
        h+='<div class="sos-card"><div class="sos-card-header"><div class="sos-card-title">Recent Backtest (last '+d.backtest_rows.length+' sessions)</div></div>'+
           '<div class="sos-tscroll"><table class="sos-table"><thead><tr><th>Date</th><th>Signal</th><th>Score</th><th>Gap</th><th>Reversal</th><th>Range</th><th>OI Score</th><th>Future Return</th></tr></thead><tbody>'+brows+'</tbody></table></div></div>';
    }

    html('sos-body',h);
}
</script>
@endpush