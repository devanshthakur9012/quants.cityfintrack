{{-- FILE: resources/views/themes/{active_theme}/user/gap-reversal-analysis/index.blade.php --}}
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
.gr-wrap{font-family:var(--f-sans);color:var(--c-text);background:var(--c-bg);}
.gr-wrap *{box-sizing:border-box;} .gr-wrap a{text-decoration:none;color:inherit;}
.mono{font-family:var(--f-mono);}
@keyframes grUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.gr-anim{animation:grUp .5s ease both;} .gr-anim.d1{animation-delay:.08s;}
@keyframes grSpin{to{transform:rotate(360deg);}}

.gr-hero{position:relative;overflow:hidden;background:var(--c-bg);border-bottom:1px solid var(--c-border);padding:36px 32px;display:flex;align-items:center;justify-content:space-between;gap:24px;}
.gr-hero::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(125,255,0,.022) 1px,transparent 1px),linear-gradient(90deg,rgba(125,255,0,.022) 1px,transparent 1px);background-size:56px 56px;mask-image:radial-gradient(ellipse 80% 80% at 20% 50%,black,transparent);pointer-events:none;}
.gr-hero::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 35% 70% at 5% 50%,rgba(125,255,0,.04),transparent 70%);pointer-events:none;}
.gr-hero-left{position:relative;z-index:1;} .gr-hero-icon{position:relative;z-index:1;width:72px;height:72px;border-radius:12px;background:var(--c-surface);border:1px solid var(--c-border2);display:flex;align-items:center;justify-content:center;font-size:30px;color:var(--c-lime);flex-shrink:0;box-shadow:0 0 24px rgba(125,255,0,.1);}
.gr-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--c-lime);margin-bottom:10px;}
.gr-eyebrow::before{content:'';display:block;width:16px;height:1px;background:var(--c-lime);}
.gr-hero h1{font-family:var(--f-display);font-size:clamp(22px,3.5vw,34px);font-weight:800;color:#fff;line-height:1.1;letter-spacing:-.015em;margin-bottom:10px;}
.gr-hero h1 span{color:var(--c-lime);} .gr-hero p{font-size:13px;color:var(--c-muted);line-height:1.7;max-width:640px;}
@media(max-width:768px){.gr-hero{flex-direction:column;padding:24px 18px;}.gr-hero-icon{display:none;}}

.gr-filter-bar{background:var(--c-surface);border-bottom:1px solid var(--c-border);padding:0 32px;position:sticky;top:0;z-index:200;box-shadow:0 4px 24px rgba(0,0,0,.3);}
.gr-filter-inner{display:flex;align-items:center;gap:12px;padding:11px 0;flex-wrap:wrap;}
.gr-filter-label{font-size:10px;color:var(--c-muted);font-weight:700;text-transform:uppercase;letter-spacing:.1em;font-family:var(--f-mono);flex-shrink:0;}
.gr-sep{width:1px;height:26px;background:var(--c-border2);flex-shrink:0;}
.gr-select,.gr-date-input{background:var(--c-panel);border:1px solid var(--c-border2);border-radius:7px;font-family:var(--f-mono);font-size:12px;font-weight:600;color:var(--c-text);outline:none;padding:6px 28px 6px 11px;transition:border-color .2s;appearance:none;cursor:pointer;min-width:120px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
.gr-date-input{min-width:auto;padding:6px 10px;background-image:none;}
.gr-date-input::-webkit-calendar-picker-indicator{filter:invert(1) opacity(.4);cursor:pointer;}
.gr-select:focus,.gr-date-input:focus{border-color:rgba(125,255,0,.45);}
.gr-analyze-btn{background:var(--c-lime);color:#000;border:none;border-radius:7px;padding:7px 20px;font-family:var(--f-display);font-size:12px;font-weight:700;letter-spacing:.06em;cursor:pointer;transition:all .2s;box-shadow:0 0 14px rgba(125,255,0,.2);}
.gr-analyze-btn:hover{background:#8FFF1A;box-shadow:0 0 22px rgba(125,255,0,.35);transform:translateY(-1px);}
.gr-reset-btn{background:var(--c-panel);border:1px solid var(--c-border2);color:var(--c-muted);border-radius:7px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--f-sans);}
.gr-filter-right{margin-left:auto;display:flex;align-items:center;gap:10px;} .gr-upd-text{font-size:10px;color:rgba(120,123,134,.45);font-family:var(--f-mono);}
@media(max-width:768px){.gr-filter-bar{padding:0 16px;}.gr-filter-right{margin-left:0;width:100%;}}

.gr-content{padding:24px 32px 64px;} @media(max-width:768px){.gr-content{padding:16px 12px 48px;}}
.gr-warn{background:rgba(255,167,38,.08);border:1px solid rgba(255,167,38,.25);border-radius:9px;padding:14px 18px;margin-bottom:18px;display:none;align-items:center;gap:12px;font-size:13px;color:var(--c-amber);}
.gr-warn.show{display:flex;}

.gr-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
@media(max-width:700px){.gr-stats{grid-template-columns:repeat(2,1fr);}}
.gr-stat-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:14px 16px;position:relative;overflow:hidden;}
.gr-stat-card::after{content:'';position:absolute;top:10px;bottom:10px;left:0;width:2px;border-radius:0 2px 2px 0;}
.gr-stat-card.s-buy::after{background:var(--c-teal);} .gr-stat-card.s-sell::after{background:var(--c-red);}
.gr-stat-card.s-wait::after{background:var(--c-amber);} .gr-stat-card.s-nosetup::after{background:var(--c-blue);}
.gr-stat-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--c-muted);margin-bottom:6px;font-family:var(--f-mono);}
.gr-stat-val{font-family:var(--f-display);font-size:24px;font-weight:800;color:#fff;}
.s-buy .gr-stat-val{color:#80CBC4;} .s-sell .gr-stat-val{color:#EF9A9A;} .s-wait .gr-stat-val{color:var(--c-amber);}

.gr-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;overflow:hidden;position:relative;}
.gr-card::before{content:'';position:absolute;top:0;left:16px;right:16px;height:1px;background:linear-gradient(90deg,transparent,var(--c-lime),transparent);opacity:.3;}
.gr-card-header{padding:13px 18px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;background:rgba(0,0,0,.2);}
.gr-card-title{font-family:var(--f-display);font-size:14px;font-weight:700;color:var(--c-text);}
.gr-card-subtitle{font-size:10px;color:var(--c-muted);font-family:var(--f-mono);}
.gr-tscroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}

.gr-table{width:100%;border-collapse:collapse;font-family:var(--f-mono);min-width:1100px;}
.gr-table thead th{padding:8px 10px;text-align:center;font-family:var(--f-mono);font-size:9px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;background:rgba(0,0,0,.25);color:var(--c-muted);border-bottom:1px solid var(--c-border);white-space:nowrap;}
.gr-table tbody td{padding:9px 10px;text-align:center;font-size:11px;border-bottom:1px solid var(--c-border);vertical-align:middle;white-space:nowrap;color:var(--c-muted);}
.gr-table tbody tr.gr-row{cursor:pointer;transition:background .15s;} .gr-table tbody tr.gr-row:hover td{background:rgba(255,255,255,.03)!important;}
.tr-even{background:var(--c-surface);} .tr-odd{background:rgba(0,0,0,.15);}
.tr-buy{background:rgba(38,166,154,.05)!important;} .tr-sell{background:rgba(239,83,80,.05)!important;}
.c-sym{font-size:12px;font-weight:800;color:var(--c-blue);} .c-date{font-size:11px;font-weight:700;color:var(--c-lime);}
.gap-up{color:#EF9A9A;font-weight:700;} .gap-down{color:#80CBC4;font-weight:700;} .gap-none{color:var(--c-muted);}
.badge{display:inline-block;padding:3px 10px;border-radius:5px;font-size:10px;font-weight:800;font-family:var(--f-sans);}
.badge-buy{background:rgba(38,166,154,.12);color:#4DB6AC;border:1px solid rgba(38,166,154,.3);}
.badge-sell{background:rgba(239,83,80,.1);color:#EF9A9A;border:1px solid rgba(239,83,80,.3);}
.badge-wait{background:rgba(255,167,38,.1);color:var(--c-amber);border:1px solid rgba(255,167,38,.25);}
.badge-nosetup{background:var(--c-panel);color:var(--c-muted);border:1px solid var(--c-border2);}
.pol-normal{color:#4DB6AC;font-size:9px;} .pol-inverted{color:var(--c-amber);font-size:9px;} .pol-unreliable{color:var(--c-red);font-size:9px;}
.confbar{width:60px;height:5px;background:var(--c-panel);border-radius:3px;overflow:hidden;display:inline-block;vertical-align:middle;margin-right:6px;}
.confbar-fill{height:100%;background:var(--c-lime);}
.step-chip{display:inline-block;width:9px;height:9px;border-radius:2px;margin:0 1px;}
.step-on{background:var(--c-teal);} .step-off{background:var(--c-border2);}
.gr-detail-row td{background:rgba(0,0,0,.3)!important;text-align:left;white-space:normal;padding:16px 20px;}
.gr-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;margin-bottom:12px;}
.gr-detail-block{background:var(--c-panel);border:1px solid var(--c-border);border-radius:8px;padding:10px 12px;}
.gr-detail-block h4{font-family:var(--f-mono);font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--c-muted);margin-bottom:6px;}
.gr-detail-block div{font-size:11px;color:var(--c-text);line-height:1.7;}
.gr-breakdown{list-style:none;font-size:11px;line-height:2;color:var(--c-text);}
.gr-empty{text-align:center;padding:52px 20px;color:var(--c-muted);}
.gr-spinner-row{display:flex;align-items:center;justify-content:center;gap:12px;padding:52px;color:var(--c-muted);font-size:12px;font-family:var(--f-mono);}
.gr-spinner{width:28px;height:28px;border:2px solid var(--c-border2);border-top:2px solid var(--c-lime);border-radius:50%;animation:grSpin .9s linear infinite;flex-shrink:0;}
</style>

<div class="gr-wrap">

<div class="gr-hero gr-anim">
    <div class="gr-hero-left">
        <div class="gr-eyebrow">Options Analytics</div>
        <h1>Advanced Gap-<span>Reversal</span> Analyzer</h1>
        <p>Gap → Reversal Pattern → Prev/Current OI → CE/PE Position → OI Migration & Wall Movement → Volume → Opening Range → BUY/SELL — with each stock's OI-vs-price behaviour learned from its own history, not a fixed list.</p>
    </div>
    <div class="gr-hero-icon"><i class="las la-wave-square"></i></div>
</div>

<div class="gr-filter-bar">
    <div class="gr-filter-inner">
        <span class="gr-filter-label">Symbol</span>
        <select id="gr-sym" class="gr-select" onchange="grAnalyze()"><option value="ALL">— All —</option></select>
        <div class="gr-sep"></div>
        <span class="gr-filter-label">Date</span>
        <input type="date" id="gr-date" class="gr-date-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="grOnDateChange()">
        <div class="gr-sep"></div>
        <span class="gr-filter-label">As of</span>
        <select id="gr-time" class="gr-select" onchange="grAnalyze()" style="min-width:90px;"><option value="">Latest</option></select>
        <div class="gr-sep"></div>
        <span class="gr-filter-label">Action</span>
        <select id="gr-action" class="gr-select" onchange="grAnalyze()">
            <option value="">All Actions</option>
            <option value="BUY">BUY Only</option>
            <option value="SELL">SELL Only</option>
            <option value="WAIT">WAIT Only</option>
        </select>
        <button class="gr-analyze-btn" onclick="grAnalyze()"><i class="las la-search"></i> Analyze</button>
        <button class="gr-reset-btn" onclick="grReset()">↺ Reset</button>
        <div class="gr-filter-right"><span class="gr-upd-text" id="gr-upd"></span></div>
    </div>
</div>

<div class="gr-content">
    <div class="gr-warn" id="gr-warn"><i class="las la-exclamation-triangle"></i><div><strong>No Analysis Config Found</strong><div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="gr-warn-msg"></div></div></div>

    <div class="gr-stats gr-anim">
        <div class="gr-stat-card s-buy"><div class="gr-stat-label">Buy Setups</div><div class="gr-stat-val" id="st-buy">—</div></div>
        <div class="gr-stat-card s-sell"><div class="gr-stat-label">Sell Setups</div><div class="gr-stat-val" id="st-sell">—</div></div>
        <div class="gr-stat-card s-wait"><div class="gr-stat-label">Wait</div><div class="gr-stat-val" id="st-wait">—</div></div>
        <div class="gr-stat-card s-nosetup"><div class="gr-stat-label">No Setup</div><div class="gr-stat-val" id="st-nosetup">—</div></div>
    </div>

    <div class="gr-card gr-anim d1">
        <div class="gr-card-header">
            <div class="gr-card-title">⊙ Gap-Reversal Flow</div>
            <span class="gr-card-subtitle" id="gr-subtitle">Detecting last available date…</span>
        </div>
        <div class="gr-tscroll">
            <table class="gr-table">
                <thead><tr>
                    <th>Symbol</th><th>Gap</th><th>LTP</th><th>Reversal</th><th>Steps</th>
                    <th>Polarity</th><th>Confidence</th><th>Action</th>
                </tr></thead>
                <tbody id="gr-tbody"><tr><td colspan="8"><div class="gr-spinner-row"><div class="gr-spinner"></div>Detecting last available date…</div></td></tr></tbody>
            </table>
        </div>
    </div>
</div>
</div>

@endsection
@push('script')
<script>
var GR_ANALYZE='{{route("gap-reversal-analysis.analyze")}}',GR_SYMBOLS='{{route("gap-reversal-analysis.symbols")}}',
    GR_LASTDATE='{{route("gap-reversal-analysis.last.date")}}',GR_INTERVALS='{{route("gap-reversal-analysis.intervals")}}',
    GR_TODAY='{{now()->toDateString()}}';
var grSymCache=null, grData=[];
function el(id){return document.getElementById(id);}
function html(id,h){var e=el(id);if(e)e.innerHTML=h;}
function txt(id,t){var e=el(id);if(e)e.textContent=t;}
document.addEventListener('DOMContentLoaded',function(){grResolveLastDateAndLoad();});

function grResolveLastDateAndLoad(){
    fetch(GR_LASTDATE,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(function(res){
        if(res.last_date) el('gr-date').value=res.last_date;
        grLoadSymbols(function(){ grLoadIntervals(function(){ grAnalyze(); }); });
    }).catch(function(){ grLoadSymbols(function(){ grAnalyze(); }); });
}
function grOnDateChange(){ grLoadIntervals(function(){ grAnalyze(); }); }
function grLoadIntervals(cb){
    var date=el('gr-date').value;
    fetch(GR_INTERVALS+'?date='+date,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(function(res){
        var sel=el('gr-time'),opts='<option value="">Latest</option>';
        (res.intervals||[]).forEach(function(t){opts+='<option value="'+t+'">'+t+'</option>';});
        sel.innerHTML=opts;
        if(cb)cb();
    }).catch(function(){ if(cb)cb(); });
}
function grLoadSymbols(cb){
    if(grSymCache!==null){ grRebuildSym(grSymCache); if(cb)cb(); return; }
    fetch(GR_SYMBOLS,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(function(res){
        if(res.no_config){ grShowWarn(res.message||''); grSymCache=[]; grRebuildSym([]); }
        else { grHideWarn(); grSymCache=res.symbols||[]; grRebuildSym(grSymCache); }
        if(cb)cb();
    }).catch(function(){ if(cb)cb(); });
}
function grRebuildSym(syms){
    var sel=el('gr-sym'),prev=sel.value,opts='<option value="ALL">— All Symbols —</option>';
    syms.forEach(function(s){opts+='<option value="'+s+'"'+(s===prev?' selected':'')+'>'+s+'</option>';});
    sel.innerHTML=opts;
}
function grAnalyze(){
    var date=el('gr-date').value, action=el('gr-action').value, sym=el('gr-sym').value, time=el('gr-time').value;
    if(!date) return;
    grHideWarn(); grResetStats();
    html('gr-tbody','<tr><td colspan="8"><div class="gr-spinner-row"><div class="gr-spinner"></div>Running Gap-Reversal flow for '+date+'…</div></td></tr>');
    txt('gr-subtitle',date+' · Loading…');
    var params=new URLSearchParams({date:date,filter_action:action});
    if(time) params.append('time',time);
    if(sym && sym!=='ALL') params.append('symbols[]',sym);
    fetch(GR_ANALYZE+'?'+params.toString(),{headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>{ if(!r.ok) throw new Error('Server error '+r.status); return r.json(); })
        .then(function(res){
            if(res.available_symbols && res.available_symbols.length){ grSymCache=res.available_symbols; grRebuildSym(grSymCache); if(sym&&sym!=='ALL') el('gr-sym').value=sym; }
            if(res.no_config){ grShowWarn(res.message); grEmptyTable('No active config.'); return; }
            if(!res.success || !res.data || !res.data.length){ grEmptyTable(res.message||'No signals found for this date.'); grResetStats(); txt('gr-subtitle',date+' · No data found'); return; }
            grData=res.data;
            grUpdateStats(res);
            grRenderTable(res.data);
            txt('gr-subtitle',date+' · '+res.message);
            txt('gr-upd','Updated '+new Date().toLocaleTimeString());
        }).catch(function(err){ grEmptyTable('⚠ '+err.message); });
}
function grRenderTable(data){
    var h='';
    data.forEach(function(r,i){
        var zebra=i%2===0?'tr-even':'tr-odd';
        var rowCls=r.final_action==='BUY'?'tr-buy':r.final_action==='SELL'?'tr-sell':'';
        var gapCls=r.gap_type==='GAP_UP'?'gap-up':r.gap_type==='GAP_DOWN'?'gap-down':'gap-none';
        var gapTxt=r.gap_type==='NONE'?'—':(r.gap_type==='GAP_UP'?'▲':'▼')+' '+(r.gap_pct>0?'+':'')+r.gap_pct+'%';
        var revTxt=r.reversal ? (r.reversal.confirmed?'✓ ':'✗ ')+esc(r.reversal.label||'—') : '—';
        var actBadge=actionBadge(r.final_action);
        var polTxt=r.polarity ? polarityChip(r.polarity) : '—';
        var confBar=r.confidence?'<span class="confbar"><span class="confbar-fill" style="width:'+r.confidence+'%;"></span></span>'+r.confidence+'%':'—';
        var steps=stepChips(r);
        h+='<tr class="gr-row '+rowCls+' '+zebra+'" onclick="grToggleDetail('+i+')">'+
            '<td class="c-sym">'+esc(r.symbol)+'</td>'+
            '<td class="'+gapCls+'">'+gapTxt+'</td>'+
            '<td>'+(r.ltp!=null?'₹'+r.ltp:'—')+'</td>'+
            '<td>'+revTxt+'</td>'+
            '<td>'+steps+'</td>'+
            '<td>'+polTxt+'</td>'+
            '<td>'+confBar+'</td>'+
            '<td>'+actBadge+'</td>'+
        '</tr>'+
        '<tr class="gr-detail-row" id="gr-detail-'+i+'" style="display:none;"><td colspan="8">'+detailHtml(r)+'</td></tr>';
    });
    html('gr-tbody',h||grEmptyHtml('No results.'));
}
function grToggleDetail(i){ var row=el('gr-detail-'+i); if(row) row.style.display = row.style.display==='none' ? 'table-row' : 'none'; }
function actionBadge(a){
    if(a==='BUY') return '<span class="badge badge-buy">📈 BUY</span>';
    if(a==='SELL') return '<span class="badge badge-sell">📉 SELL</span>';
    if(a==='WAIT') return '<span class="badge badge-wait">⏸ WAIT</span>';
    return '<span class="badge badge-nosetup">— NO SETUP</span>';
}
function polarityChip(p){
    if(p.polarity==='NORMAL') return '<span class="pol-normal">● Normal ('+p.match_rate+'%)</span>';
    if(p.polarity==='INVERTED') return '<span class="pol-inverted">◐ Inverted ('+p.match_rate+'%)</span>';
    return '<span class="pol-unreliable">○ Unreliable</span>';
}
function stepChips(r){
    if(!r.reversal) return '<span style="color:var(--c-muted);">—</span>';
    var flags=[r.reversal.confirmed, r.volume&&r.volume.confirmed, r.or_breakout&&r.or_breakout.confirmed];
    var h='';
    flags.forEach(function(f){ h+='<span class="step-chip '+(f?'step-on':'step-off')+'"></span>'; });
    return h;
}
function detailHtml(r){
    if(!r.reversal) return '<div style="color:var(--c-muted);font-size:11px;">No qualifying gap — flow not applicable.</div>';
    var pos='';
    if(r.positions){
        Object.keys(r.positions).forEach(function(k){
            var p=r.positions[k];
            pos+='<div>'+k+': CE '+trendTag(p.ce_trend)+' / PE '+trendTag(p.pe_trend)+'</div>';
        });
    }
    var bd='';
    (r.breakdown||[]).forEach(function(line){ bd+='<li>'+esc(line)+'</li>'; });
    return '<div class="gr-detail-grid">'+
        '<div class="gr-detail-block"><h4>Gap & Reversal</h4><div>'+
            'Prev Close: ₹'+r.prev_close+'<br>Today Open: ₹'+r.today_open+'<br>'+
            r.reversal.label+': '+(r.reversal.confirmed?'✓ Confirmed':'✗ Not yet')+'<br>'+
            'Extreme→Now: '+r.reversal.extreme+' → '+r.reversal.current+' ('+r.reversal.recovery_pct+'%)'+
        '</div></div>'+
        '<div class="gr-detail-block"><h4>OI (ATM)</h4><div>'+
            'Prev-day: CE '+trendTag(r.prev_oi.ce_trend)+' / PE '+trendTag(r.prev_oi.pe_trend)+' → '+r.prev_oi.signal+'<br>'+
            'Current: CE '+r.curr_oi.ce_pct+'% / PE '+r.curr_oi.pe_pct+'% → '+r.curr_oi.signal+
        '</div></div>'+
        '<div class="gr-detail-block"><h4>CE/PE Position</h4><div>'+(pos||'—')+'</div></div>'+
        '<div class="gr-detail-block"><h4>OI Wall Movement</h4><div>'+
            'CE wall: '+r.wall.ce_wall_open+' → '+r.wall.ce_wall_now+' ('+r.wall.ce_wall_dir+')<br>'+
            'PE wall: '+r.wall.pe_wall_open+' → '+r.wall.pe_wall_now+' ('+r.wall.pe_wall_dir+')'+
        '</div></div>'+
        '<div class="gr-detail-block"><h4>Volume & Range</h4><div>'+
            'Volume: '+r.volume.ratio+'x avg ('+(r.volume.confirmed?'confirmed':'not confirmed')+')<br>'+
            'OR '+r.or_breakout.type+': level ₹'+r.or_breakout.level+' vs LTP ₹'+r.or_breakout.ltp+' ('+(r.or_breakout.confirmed?'confirmed':'not yet')+')'+
        '</div></div>'+
    '</div>'+
    '<div class="gr-detail-block"><h4>Score Breakdown ('+r.score+' / '+r.max_score+')</h4><ul class="gr-breakdown">'+bd+'</ul></div>';
}
function trendTag(t){
    var cls = t==='Buildup'?'style="color:#4DB6AC;"':t==='Unwinding'?'style="color:#EF9A9A;"':'style="color:var(--c-muted);"';
    return '<span '+cls+'>'+t+'</span>';
}
function grUpdateStats(res){ txt('st-buy',res.buy_count||'0'); txt('st-sell',res.sell_count||'0'); txt('st-wait',res.wait_count||'0'); txt('st-nosetup',res.no_setup_count||'0'); }
function grResetStats(){ ['st-buy','st-sell','st-wait','st-nosetup'].forEach(function(id){ txt(id,'—'); }); }
function grShowWarn(msg){ el('gr-warn').classList.add('show'); txt('gr-warn-msg',msg||''); }
function grHideWarn(){ el('gr-warn').classList.remove('show'); }
function grEmptyTable(msg){ html('gr-tbody',grEmptyHtml(msg)); }
function grEmptyHtml(msg){ return '<tr><td colspan="8"><div class="gr-empty"><div>'+(msg||'No data found.')+'</div></div></td></tr>'; }
function grReset(){
    fetch(GR_LASTDATE,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(function(res){
        el('gr-date').value=res.last_date||GR_TODAY; el('gr-action').value=''; el('gr-sym').value='ALL'; el('gr-time').value='';
        grHideWarn(); grLoadIntervals(function(){ grAnalyze(); });
    }).catch(function(){ el('gr-date').value=GR_TODAY; grAnalyze(); });
}
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
@endpush