@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
:root{
  --bull:#00e676;--bear:#ff1744;--warn:#ffc107;--acc:#ff6b00;--acc2:#ff9f00;
  --blue:#4fc3f7;--lime:#b2ff59;
  --card:#0a0a18;--border:rgba(255,107,0,.13);
  --dim:rgba(255,255,255,.28);--dimmer:rgba(255,255,255,.15);
}
.hdr{background:linear-gradient(135deg,#080814,#140828 50%,#081414);border:1px solid rgba(255,107,0,.22);border-radius:14px;padding:16px 22px;margin-bottom:14px;position:relative;overflow:hidden}
.hdr::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--acc),var(--acc2),var(--acc),transparent)}
.hdr h4{color:var(--acc2);font-size:17px;font-weight:900;margin:0}
.hdr p{color:rgba(255,255,255,.36);margin:4px 0 0;font-size:10px;line-height:1.7}
.badge-v4{background:rgba(0,230,118,.1);color:var(--bull);border:1px solid rgba(0,230,118,.3);padding:2px 8px;border-radius:5px;font-size:8px;font-weight:800;margin-left:6px}
/* tabs */
.tabs{display:flex;gap:0;border-bottom:1px solid rgba(255,255,255,.07);margin-bottom:0}
.tab{background:transparent;border:none;border-bottom:2px solid transparent;color:rgba(255,255,255,.35);padding:10px 22px;font-size:12px;font-weight:800;cursor:pointer;transition:all .15s;margin-bottom:-1px}
.tab:hover{color:rgba(255,255,255,.6)}
.tab.on{color:var(--acc2);border-bottom-color:var(--acc2)}
.tp{display:none;padding-top:14px}
.tp.on{display:block}
/* filter bar */
.fb{background:var(--card);border:1px solid var(--border);padding:9px 16px;border-radius:10px;margin-bottom:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.fb label{color:var(--dim);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;margin:0;white-space:nowrap}
.fsel,.di{background:rgba(255,107,0,.07);border:1px solid rgba(255,107,0,.22);color:var(--acc2);border-radius:7px;padding:5px 10px;font-size:11px;font-weight:700;outline:none;cursor:pointer}
.fsel option{background:#0d0d1f}
.di::-webkit-calendar-picker-indicator{filter:invert(.7) sepia(1) saturate(5) hue-rotate(-15deg);cursor:pointer}
.nb{background:rgba(255,107,0,.08);border:1px solid rgba(255,107,0,.18);color:var(--acc2);border-radius:6px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;flex-shrink:0}
.nb:hover{background:rgba(255,107,0,.2)}
.btn-p{background:linear-gradient(135deg,var(--acc),var(--acc2));color:#000;border:none;border-radius:7px;padding:6px 18px;font-weight:900;font-size:11px;cursor:pointer;white-space:nowrap}
.btn-p:disabled{opacity:.5;cursor:not-allowed}
.btn-s{background:rgba(79,195,247,.08);color:var(--blue);border:1px solid rgba(79,195,247,.22);border-radius:7px;padding:6px 14px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap}
.dv{width:1px;height:20px;background:rgba(255,255,255,.07);flex-shrink:0}
.lu{font-size:9px;color:rgba(255,255,255,.18);margin-left:auto;white-space:nowrap}
.info-note{font-size:9px;color:rgba(255,255,255,.25);background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:6px;padding:4px 10px}
/* stats row */
.stats-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.sbox{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:10px 14px;min-width:90px;flex:1}
.sbox .lbl{font-size:8px;font-weight:800;color:var(--dimmer);text-transform:uppercase;letter-spacing:.7px;margin-bottom:4px}
.sbox .val{font-size:20px;font-weight:900;line-height:1}
.sbox .sub{font-size:8px;color:var(--dimmer);margin-top:3px}
/* sym grid */
.sym-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:6px;margin-bottom:12px}
.sc{background:var(--card);border:1px solid var(--border);border-radius:9px;padding:9px 11px;cursor:pointer;transition:border-color .15s;position:relative;overflow:hidden}
.sc:hover{border-color:rgba(255,107,0,.3)}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.sc.use{border-color:rgba(0,230,118,.2)}.sc.use::before{background:linear-gradient(90deg,transparent,var(--bull),transparent)}
.sc.nope{border-color:rgba(255,23,68,.18)}.sc.nope::before{background:linear-gradient(90deg,transparent,var(--bear),transparent)}
.sc.bord{border-color:rgba(255,193,7,.18)}.sc.bord::before{background:linear-gradient(90deg,transparent,var(--warn),transparent)}
.sc-name{font-size:12px;font-weight:900;color:var(--acc2)}
.sc-rate{font-size:18px;font-weight:900;margin:3px 0 1px;line-height:1}
.sc-bar{width:100%;height:3px;background:rgba(255,255,255,.05);border-radius:2px;overflow:hidden;margin:4px 0}
.sc-fill{height:3px;border-radius:2px}
.sc-meta{font-size:8px;color:var(--dimmer);display:flex;gap:5px;flex-wrap:wrap}
.verdict{display:inline-block;font-size:7px;font-weight:800;padding:1px 5px;border-radius:3px;margin-top:3px}
.v-use{background:rgba(0,230,118,.12);color:var(--bull);border:1px solid rgba(0,230,118,.2)}
.v-nope{background:rgba(255,23,68,.1);color:var(--bear);border:1px solid rgba(255,23,68,.18)}
.v-bord{background:rgba(255,193,7,.09);color:var(--warn);border:1px solid rgba(255,193,7,.18)}
.v-pend{background:rgba(255,255,255,.04);color:var(--dimmer);border:1px solid rgba(255,255,255,.07)}
/* tables */
.tbl-wrap{overflow-x:auto}
.mt{width:100%;border-collapse:collapse;font-size:10px}
.mt thead th{padding:6px 8px;text-align:left;font-size:8px;font-weight:800;color:var(--dimmer);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid rgba(255,255,255,.07);white-space:nowrap;cursor:pointer;user-select:none;background:rgba(255,255,255,.01)}
.mt thead th:hover{color:var(--acc2)}
.mt thead th.sa::after{content:' ▲';color:var(--acc2)}
.mt thead th.sd::after{content:' ▼';color:var(--acc2)}
.mt tbody td{padding:5px 8px;border-bottom:1px solid rgba(255,255,255,.03);white-space:nowrap;vertical-align:middle}
.mt tbody tr:hover td{background:rgba(255,107,0,.02)}
.mt tbody tr.win-r td{background:rgba(0,230,118,.03)!important}
.mt tbody tr.loss-r td{background:rgba(255,23,68,.025)!important}
.mt tbody tr.flat-r td{background:rgba(255,193,7,.015)!important}
.tbl-footer{font-size:9px;color:var(--dimmer);padding:6px 8px;border-top:1px solid rgba(255,255,255,.04)}
/* badges */
.badge{display:inline-block;padding:2px 6px;border-radius:4px;font-size:9px;font-weight:800;white-space:nowrap}
.b-bull{background:rgba(0,230,118,.12);color:var(--bull);border:1px solid rgba(0,230,118,.25)}
.b-bear{background:rgba(255,23,68,.1);color:var(--bear);border:1px solid rgba(255,23,68,.2)}
.b-warn{background:rgba(255,193,7,.1);color:var(--warn);border:1px solid rgba(255,193,7,.2)}
.b-dim{background:rgba(255,255,255,.04);color:rgba(255,255,255,.28);border:1px solid rgba(255,255,255,.08)}
.b-win{background:rgba(0,230,118,.15);color:var(--bull);border:1px solid rgba(0,230,118,.3)}
.b-loss{background:rgba(255,23,68,.12);color:var(--bear);border:1px solid rgba(255,23,68,.25)}
.b-flat{background:rgba(255,193,7,.1);color:var(--warn);border:1px solid rgba(255,193,7,.2)}
.b-pend{background:rgba(255,255,255,.04);color:rgba(255,255,255,.3);border:1px solid rgba(255,255,255,.07)}
/* conf bar */
.cb-wrap{display:flex;align-items:center;gap:5px}
.cb-track{width:48px;height:5px;background:rgba(255,255,255,.05);border-radius:2px;overflow:hidden;flex-shrink:0}
.cb-fill{height:5px;border-radius:2px}
/* market status */
.ms-bar{display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:8px;margin-bottom:10px;font-size:10px;font-weight:700;border:1px solid}
.ms-open{background:rgba(255,193,7,.06);border-color:rgba(255,193,7,.22);color:var(--warn)}
.ms-closed{background:rgba(0,230,118,.05);border-color:rgba(0,230,118,.2);color:var(--bull)}
.ms-hist{background:rgba(79,195,247,.04);border-color:rgba(79,195,247,.15);color:var(--blue)}
.ms-dot{width:7px;height:7px;border-radius:50%;animation:pulse 1.2s ease-in-out infinite alternate;flex-shrink:0}
@keyframes pulse{from{opacity:.4;transform:scale(.9)}to{opacity:1;transform:scale(1.1)}}
/* section */
.sec{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 16px;margin-bottom:12px}
.sec h5{font-size:11px;font-weight:800;color:var(--acc2);margin:0 0 10px;text-transform:uppercase;letter-spacing:.6px}
.sec-note{font-size:10px;color:rgba(255,255,255,.36);line-height:1.65;margin:0 0 12px}
/* loading */
.lw{display:flex;flex-direction:column;align-items:center;padding:50px;color:var(--acc2);font-size:12px;font-weight:600;gap:12px}
.sp{width:30px;height:30px;border:3px solid rgba(255,107,0,.09);border-top-color:var(--acc2);border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.nd{text-align:center;padding:40px 20px;color:rgba(255,255,255,.16);font-size:12px}
@media(max-width:640px){.sym-grid{grid-template-columns:repeat(3,1fr)}.stats-row{flex-wrap:wrap}}
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

<div class="hdr">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4>🧠 Smart EOD v4 — PCR + OI + Price Engine <span class="badge-v4">v4</span></h4>
      <p>
        <strong style="color:var(--bull)">PCR</strong>: PE OI ÷ CE OI (institutional positioning) &nbsp;·&nbsp;
        <strong style="color:var(--blue)">OI Change</strong>: Price-confirmed long/short buildup &nbsp;·&nbsp;
        <strong style="color:var(--warn)">Price</strong>: Day direction + last-hour confirmation
        &nbsp;·&nbsp; All 3 must agree → signal &nbsp;·&nbsp; Any conflict → AVOID
      </p>
    </div>
  </div>
</div>

<div class="tabs">
  <div class="tab on" onclick="swTab('t-live',this)">📊 Live Signals</div>
  <div class="tab"    onclick="swTab('t-bt',this)">🔬 Backtest</div>
  <div class="tab"    onclick="swTab('t-acc',this)">📈 Accuracy Report</div>
</div>

{{-- ════════════ LIVE SIGNALS ════════════ --}}
<div id="t-live" class="tp on">
  <div id="ms-bar"></div>
  <div class="fb">
    <label>Date</label>
    <div class="d-flex align-items-center gap-1">
      <button class="nb" onclick="shiftDate(-1)">‹</button>
      <input type="date" id="dp" class="di" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="loadSignals()">
      <button class="nb" onclick="shiftDate(1)">›</button>
      <button class="nb" style="width:auto;padding:0 8px;font-size:9px" onclick="goToday()">Today</button>
    </div>
    <div class="dv"></div>
    <label>Symbol</label>
    <select id="sym-live" class="fsel" onchange="loadSignals()"><option value="ALL">All Symbols</option></select>
    <label style="display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--dim);font-size:9px;font-weight:800">
      <input type="checkbox" id="saveChk"> Save to DB
    </label>
    <button class="btn-p" onclick="loadSignals()">↻ Load</button>
    <span class="lu" id="lu-live"></span>
  </div>
  <div id="live-stats"></div>
  <div class="sec"><div id="live-tbl"><div class="lw"><div class="sp"></div>Loading…</div></div></div>
</div>

{{-- ════════════ BACKTEST ════════════ --}}
<div id="t-bt" class="tp">
  <div class="sec" style="border-color:rgba(178,255,89,.15)">
    <h5 style="color:var(--lime)">⚡ Run Backtest on Historical Data</h5>
    <p class="sec-note">
      Choose a <strong style="color:rgba(255,255,255,.55)">start date and end date</strong>. The engine will run the v4 signal logic for every trading day in that range,
      then check the <strong style="color:rgba(255,255,255,.55)">next day's FUT price</strong> to determine WIN (T1 hit +0.8%), LOSS (SL hit −0.5%) or FLAT.
      All results are saved to the database. The <strong style="color:var(--blue)">Accuracy Report tab</strong> then reads these saved results.
      <br><span style="color:var(--warn)">First time: set start to your earliest data date to backtest everything. After that, unchecked "Re-run existing" skips already-saved dates.</span>
    </p>
    <div class="fb" style="margin-bottom:0;background:rgba(255,255,255,.02)">
      <label>Symbol</label>
      <select id="bt-sym" class="fsel"><option value="ALL">All Symbols</option></select>
      <div class="dv"></div>
      <label>Start</label>
      <input type="date" id="bt-start" class="di" value="{{ now()->subDays(30)->toDateString() }}">
      <label>End</label>
      <input type="date" id="bt-end" class="di" value="{{ now()->subDay()->toDateString() }}" max="{{ now()->subDay()->toDateString() }}">
      <div class="dv"></div>
      <label style="display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--dim);font-size:9px;font-weight:800">
        <input type="checkbox" id="bt-force"> Re-run existing
      </label>
      <button class="btn-p" onclick="runBacktest()" id="bt-btn">⚡ Run Backtest</button>
    </div>
  </div>
  <div id="bt-prog" style="display:none" class="sec">
    <div class="lw" style="padding:30px"><div class="sp"></div><span id="bt-msg">Running…</span></div>
  </div>
  <div id="bt-result"></div>
</div>

{{-- ════════════ ACCURACY REPORT ════════════ --}}
<div id="t-acc" class="tp">
  <div class="fb">
    <label>Symbol</label>
    <select id="acc-sym" class="fsel" onchange="loadAccuracy()"><option value="ALL">All Symbols</option></select>
    <div class="dv"></div>
    <label>Days back</label>
    <input type="number" id="acc-days" value="90" min="5" max="9999" class="di" style="width:65px">
    <button class="btn-p" onclick="loadAccuracy()">↻ Refresh</button>
    <div class="dv"></div>
    <label>Outcome</label>
    <select id="f-out" class="fsel" onchange="applyFilters()">
      <option value="ALL">All</option><option value="WIN">WIN</option>
      <option value="LOSS">LOSS</option><option value="FLAT">FLAT</option>
      <option value="PENDING">PENDING</option>
    </select>
    <label>Action</label>
    <select id="f-act" class="fsel" onchange="applyFilters()">
      <option value="ALL">All</option><option value="BUY_CE">BUY CE</option>
      <option value="BUY_PE">BUY PE</option><option value="AVOID">AVOID</option>
    </select>
    <button class="btn-s" onclick="exportCsv()" style="font-size:9px;padding:4px 10px">⬇ CSV</button>
    <span class="lu" id="lu-acc"></span>
  </div>
  <div id="acc-content"><div class="lw"><div class="sp"></div>Loading…</div></div>
</div>

</div>
</section>
@endsection

@push('script')
<script>
const todayStr = '{{ now()->toDateString() }}';
let cachedSyms=[], allPreds=[], sortCol='signal_date', sortDir=-1;

$(document).ready(() => { loadSignals(); loadAccuracy(); });

/* tabs */
function swTab(id,btn){
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('on'));
  document.querySelectorAll('.tp').forEach(p=>p.classList.remove('on'));
  btn.classList.add('on'); document.getElementById(id).classList.add('on');
}

/* shared sym dropdown */
function rebuildSyms(syms){
  if(JSON.stringify(cachedSyms)===JSON.stringify(syms)) return;
  cachedSyms=syms;
  ['sym-live','bt-sym','acc-sym'].forEach(id=>{
    const sel=document.getElementById(id),prev=sel.value;
    sel.innerHTML='<option value="ALL">All Symbols</option>';
    syms.forEach(s=>{const o=document.createElement('option');o.value=s;o.textContent=s;if(s===prev)o.selected=true;sel.appendChild(o);});
  });
}

/* ═══════ LIVE SIGNALS ═══════ */
const dp=()=>document.getElementById('dp').value;
const lsym=()=>document.getElementById('sym-live').value;
function shiftDate(d){const el=document.getElementById('dp'),dt=new Date(el.value);dt.setDate(dt.getDate()+d);const s=dt.toISOString().split('T')[0];if(s>todayStr)return;el.value=s;loadSignals();}
function goToday(){document.getElementById('dp').value=todayStr;loadSignals();}

function loadSignals(){
  document.getElementById('live-tbl').innerHTML='<div class="lw"><div class="sp"></div>Running v4 engine…</div>';
  document.getElementById('live-stats').innerHTML='';
  const save=document.getElementById('saveChk').checked?1:0;
  $.ajax({
    url:'{{ route("smart-eod-v4.signals") }}',
    data:{symbol:lsym(),date:dp(),save},
    success(res){
      renderMsBar(res);
      if(res.available_symbols) rebuildSyms(res.available_symbols);
      if(!res.success||!res.data?.length){document.getElementById('live-tbl').innerHTML='<div class="nd">No data for '+dp()+'</div>';return;}
      renderLiveStats(res.data);
      renderLiveTable(res.data,res.next_trading_day);
      document.getElementById('lu-live').textContent='Updated: '+new Date().toLocaleTimeString();
    },
    error(xhr){document.getElementById('live-tbl').innerHTML='<div class="nd">⚠ '+esc((xhr.responseJSON||{}).message||'Error')+'</div>';}
  });
}

function renderMsBar(res){
  const el=document.getElementById('ms-bar');
  if(!res.is_today){el.innerHTML='<div class="ms-bar ms-hist">📅 Historical — '+res.today+'</div>';return;}
  if(res.market_closed)
    el.innerHTML='<div class="ms-bar ms-closed"><span class="ms-dot" style="background:var(--bull)"></span>✅ Market closed · Signals final · Entry next day: <strong>'+(res.next_trading_day||'—')+'</strong></div>';
  else
    el.innerHTML='<div class="ms-bar ms-open"><span class="ms-dot" style="background:var(--warn)"></span>⏳ Market open ('+res.current_time+') · Signal ready after <strong>15:00</strong></div>';
}

function renderLiveStats(data){
  let bull=0,bear=0,avoid=0,wait=0;
  data.forEach(d=>{const a=(d.signal||{}).action;if(a==='BUY_CE')bull++;else if(a==='BUY_PE')bear++;else if(a==='WAIT')wait++;else avoid++;});
  document.getElementById('live-stats').innerHTML=`<div class="stats-row">
    ${sb('BUY CE',bull,'var(--bull)','signals')}
    ${sb('BUY PE',bear,'var(--bear)','signals')}
    ${sb('AVOID',avoid,'rgba(255,255,255,.3)','signals')}
    ${wait?sb('WAIT',wait,'var(--warn)','signals'):''}
    ${sb('Total',data.length,'var(--acc2)','symbols')}
  </div>`;
}
function sb(l,v,c,s){return `<div class="sbox"><div class="lbl">${l}</div><div class="val" style="color:${c}">${v}</div><div class="sub">${s}</div></div>`;}

function renderLiveTable(data,nextDay){
  if(!data.length){document.getElementById('live-tbl').innerHTML='<div class="nd">No signals</div>';return;}
  const rows=data.map(d=>{
    const sig=d.signal||{},ind=d.indicators||{},ds=d.day_summary||{};
    const action=sig.action||'AVOID',conf=sig.confidence||0;
    const pcr=ind.pcr||{},oi=ind.oi||{},price=ind.price||{};
    const chP=ds.change_pct,pcrEod=ds.pcr_eod;
    const rowCls=action==='BUY_CE'?'bull-r':action==='BUY_PE'?'bear-r':'';
    const actBadge=action==='BUY_CE'?'<span class="badge b-bull">🟢 BUY CE</span>':action==='BUY_PE'?'<span class="badge b-bear">🔴 BUY PE</span>':action==='WAIT'?'<span class="badge b-warn">⏳ WAIT</span>':'<span class="badge b-dim">⛔ AVOID</span>';
    const confC=conf>=75?'var(--bull)':conf>=55?'var(--warn)':conf>=40?'var(--acc2)':'rgba(255,255,255,.2)';
    const pcrC=pcrEod>=1.1?'var(--bull)':pcrEod<=0.8?'var(--bear)':'rgba(255,255,255,.4)';
    const gr=sig.gap_rules||{};
    const entry=action!=='AVOID'&&action!=='WAIT'?esc(gr.entry_step_1||'')+'<br><span style="font-size:8px;color:rgba(255,255,255,.3)">SL: '+esc(gr.stop_loss||'—')+'</span>':'—';
    return `<tr class="${rowCls}">
      <td style="font-weight:900;color:var(--acc2)">${d.symbol}</td>
      <td>${actBadge}</td>
      <td><div class="cb-wrap"><span style="font-size:12px;font-weight:900;color:${confC}">${conf}%</span><div class="cb-track"><div class="cb-fill" style="width:${conf}%;background:${confC}"></div></div></div></td>
      <td>${dots(sig.indicators_aligned,action)}</td>
      <td style="color:${pcrC};font-weight:800">${pcrEod?Number(pcrEod).toFixed(3):'—'}</td>
      <td>${bc(pcr.bias)}</td><td>${bc(oi.bias)}</td><td>${bc(price.bias)}</td>
      <td style="color:${chP>0?'var(--bull)':chP<0?'var(--bear)':'rgba(255,255,255,.3)'}">${chP!=null?(chP>=0?'+':'')+Number(chP).toFixed(2)+'%':'—'}</td>
      <td style="font-size:10px">${entry}</td>
      <td style="font-size:9px;color:var(--blue)">${nextDay||'—'}</td>
    </tr>`;
  }).join('');
  document.getElementById('live-tbl').innerHTML=`<div class="tbl-wrap"><table class="mt">
    <thead><tr><th>Symbol</th><th>Signal</th><th>Confidence</th><th>Aligned</th><th>PCR EOD</th><th>PCR</th><th>OI</th><th>Price</th><th>Day %</th><th>Entry Rule</th><th>Trade Date</th></tr></thead>
    <tbody>${rows}</tbody></table></div>
  <div class="tbl-footer">${data.length} symbols · Valid for ${nextDay||'next trading day'} 9:15–10:30 ONLY · Check "Save to DB" to record signals for accuracy tracking</div>`;
}

/* ═══════ BACKTEST ═══════ */
function runBacktest(){
  const sym=document.getElementById('bt-sym').value;
  const start=document.getElementById('bt-start').value;
  const end=document.getElementById('bt-end').value;
  const force=document.getElementById('bt-force').checked?1:0;
  if(!start||!end){alert('Set start and end dates');return;}
  if(start>end){alert('Start must be before end');return;}
  document.getElementById('bt-result').innerHTML='';
  document.getElementById('bt-prog').style.display='block';
  document.getElementById('bt-msg').textContent='Running backtest ('+sym+') from '+start+' to '+end+'…';
  document.getElementById('bt-btn').disabled=true;
  $.ajax({
    url:'{{ route("smart-eod-backtest.run") }}',
    data:{symbol:sym,start,end,save:1,force},
    timeout:300000,
    success(res){
      document.getElementById('bt-prog').style.display='none';
      document.getElementById('bt-btn').disabled=false;
      if(!res.success){document.getElementById('bt-result').innerHTML='<div class="nd">⚠ '+esc(res.message)+'</div>';return;}
      renderBtResult(res);
      loadAccuracy(); // auto-refresh accuracy after backtest
    },
    error(xhr){
      document.getElementById('bt-prog').style.display='none';
      document.getElementById('bt-btn').disabled=false;
      document.getElementById('bt-result').innerHTML='<div class="nd">⚠ '+esc((xhr.responseJSON||{}).message||'Error — check server logs')+'</div>';
    }
  });
}

function renderBtResult(res){
  const sum=res.summary||{},wr=sum.overall_win_rate;
  const wrC=wr>=60?'var(--bull)':wr>=50?'var(--warn)':wr!=null?'var(--bear)':'rgba(255,255,255,.3)';
  const rows=(res.data||[]).filter(d=>['BUY_CE','BUY_PE'].includes((d.signal||{}).action)).map(d=>{
    const out=d.outcome||'PENDING',chP=d.outcome_detail?.change_pct;
    const rowCls=out==='WIN'?'win-r':out==='LOSS'?'loss-r':out==='FLAT'?'flat-r':'';
    const outBadge=out==='WIN'?'<span class="badge b-win">WIN</span>':out==='LOSS'?'<span class="badge b-loss">LOSS</span>':out==='FLAT'?'<span class="badge b-flat">FLAT</span>':'<span class="badge b-pend">PEND</span>';
    return `<tr class="${rowCls}">
      <td style="font-weight:800;color:var(--acc2)">${d.symbol}</td>
      <td style="color:rgba(255,255,255,.4);font-size:9px">${d.date}</td>
      <td><span class="badge ${(d.signal||{}).action==='BUY_CE'?'b-bull':'b-bear'}">${(d.signal||{}).action}</span></td>
      <td style="font-weight:800">${(d.signal||{}).confidence||0}%</td>
      <td>${dots((d.signal||{}).indicators_aligned,(d.signal||{}).action)}</td>
      <td>${bc((d.indicators?.pcr||{}).bias)}</td>
      <td>${bc((d.indicators?.oi||{}).bias)}</td>
      <td>${bc((d.indicators?.price||{}).bias)}</td>
      <td style="font-size:9px;color:rgba(255,255,255,.35)">${d.next_trading_day||'—'}</td>
      <td>${outBadge}</td>
      <td style="color:${chP>0?'var(--bull)':chP<0?'var(--bear)':'rgba(255,255,255,.3)'}">${chP!=null?(chP>0?'+':'')+Number(chP).toFixed(2)+'%':'—'}</td>
      <td style="color:${d.outcome_detail?.hit_t1?'var(--bull)':'rgba(255,255,255,.15)'}">${d.outcome_detail?.hit_t1?'✓':'✗'}</td>
      <td style="color:${d.outcome_detail?.hit_sl?'var(--bear)':'rgba(255,255,255,.15)'}">${d.outcome_detail?.hit_sl?'✓':'✗'}</td>
    </tr>`;
  }).join('');
  document.getElementById('bt-result').innerHTML=`
    <div class="stats-row">
      ${sb('Signals',sum.total_signals||0,'var(--acc2)','BUY CE/PE')}
      ${sb('Wins',sum.total_wins||0,'var(--bull)','T1 hit')}
      ${sb('Losses',sum.total_losses||0,'var(--bear)','SL hit')}
      ${sb('Flat',sum.total_flat||0,'var(--warn)','neither')}
      ${sb('Win Rate',wr!=null?wr+'%':'—',wrC,'overall')}
    </div>
    <div class="sec"><h5>Backtest detail — actionable signals only</h5>
      <div class="tbl-wrap"><table class="mt">
        <thead><tr><th>Symbol</th><th>Signal Date</th><th>Action</th><th>Conf</th><th>Aligned</th><th>PCR</th><th>OI</th><th>Price</th><th>Trade Date</th><th>Outcome</th><th>Next Day %</th><th>T1</th><th>SL</th></tr></thead>
        <tbody>${rows||'<tr><td colspan="13" class="nd" style="padding:16px">No actionable signals</td></tr>'}</tbody>
      </table></div>
      <div class="tbl-footer">Period: ${res.period?.start||'—'} → ${res.period?.end||'—'} · Saved to DB ✓ · Accuracy Report tab auto-refreshed</div>
    </div>`;
}

/* ═══════ ACCURACY REPORT ═══════ */
function loadAccuracy(){
  const sym=document.getElementById('acc-sym').value;
  const days=document.getElementById('acc-days').value;
  document.getElementById('acc-content').innerHTML='<div class="lw"><div class="sp"></div>Loading saved results…</div>';
  $.ajax({
    url:'{{ route("smart-eod-backtest.results") }}',
    data:{symbol:sym,days},
    success(res){
      if(!res.success||!res.predictions?.length){
        document.getElementById('acc-content').innerHTML='<div class="nd"><p>No saved predictions yet.</p><p style="font-size:10px;margin-top:6px;color:rgba(255,255,255,.25)">Go to Backtest tab, set a date range, and run the backtest.</p></div>';
        return;
      }
      allPreds=res.predictions;
      renderAcc(res);
      document.getElementById('lu-acc').textContent='Loaded: '+new Date().toLocaleTimeString();
    },
    error(){document.getElementById('acc-content').innerHTML='<div class="nd">⚠ Error</div>';}
  });
}

function renderAcc(res){
  const ov=res.overall||{},bySym=res.by_symbol||{};
  const wr=ov.win_rate;
  const wrC=wr>=60?'var(--bull)':wr>=50?'var(--warn)':wr!=null?'var(--bear)':'rgba(255,255,255,.3)';
  const statsHtml=`<div class="stats-row">
    ${sb('Win Rate',wr!=null?wr+'%':'—',wrC,(ov.completed||0)+' done')}
    ${sb('Signals',ov.actionable_signals||0,'var(--acc2)','given')}
    ${sb('Wins',ov.wins||0,'var(--bull)','T1 hit')}
    ${sb('Losses',ov.losses||0,'var(--bear)','SL hit')}
    ${sb('Flat',ov.flat||0,'var(--warn)','neither')}
    ${sb('Pending',ov.pending||0,'rgba(255,255,255,.3)','awaiting')}
    ${sb('Total Days',ov.total_predictions||0,'rgba(255,255,255,.45)','scanned')}
  </div>`;
  const symHtml=Object.entries(bySym).map(([s,d])=>{
    const wr=d.win_rate;
    const c=wr>=60?'var(--bull)':wr>=50?'var(--warn)':wr!=null?'var(--bear)':'rgba(255,255,255,.3)';
    const cls=d.verdict==='USE_THIS'?'use':d.verdict==='DO_NOT_USE'?'nope':d.verdict==='BORDERLINE'?'bord':'';
    const vCls=d.verdict==='USE_THIS'?'v-use':d.verdict==='DO_NOT_USE'?'v-nope':d.verdict==='BORDERLINE'?'v-bord':'v-pend';
    return `<div class="sc ${cls}" onclick="filterSym('${s}')">
      <div class="sc-name">${s}</div>
      <div class="sc-rate" style="color:${c}">${wr!=null?wr+'%':'—'}</div>
      <div class="sc-bar"><div class="sc-fill" style="width:${wr!=null?Math.min(100,wr):0}%;background:${c};opacity:.7"></div></div>
      <div class="sc-meta">
        <span style="color:var(--bull)">${d.wins}W</span>
        <span style="color:var(--bear)">${d.losses}L</span>
        <span style="color:var(--warn)">${d.flat}F</span>
        <span style="color:rgba(255,255,255,.25)">${d.pending}P</span>
        <span>${d.signals}sig</span>
      </div>
      <span class="verdict ${vCls}">${d.verdict||'PENDING'}</span>
    </div>`;
  }).join('');
  document.getElementById('acc-content').innerHTML=`
    ${statsHtml}
    <div class="sec"><h5>Per-symbol accuracy <span style="font-weight:400;font-size:9px;text-transform:none;letter-spacing:0">(click symbol to filter table · ≥60% = USE THIS · 50-59% = BORDERLINE · &lt;50% = DO NOT USE)</span></h5>
      <div class="sym-grid">${symHtml}</div>
    </div>
    <div class="sec"><h5>All saved predictions</h5>
      <div id="acc-tbl"></div>
    </div>`;
  renderAccTable(allPreds);
}

function filterSym(s){
  const sel=document.getElementById('acc-sym');
  for(let i=0;i<sel.options.length;i++){if(sel.options[i].value===s){sel.selectedIndex=i;break;}}
  applyFilters();
}

function applyFilters(){
  const outF=document.getElementById('f-out')?.value||'ALL';
  const actF=document.getElementById('f-act')?.value||'ALL';
  const symF=document.getElementById('acc-sym')?.value||'ALL';
  let f=allPreds;
  if(symF!=='ALL') f=f.filter(p=>p.symbol===symF);
  if(outF!=='ALL') f=f.filter(p=>p.outcome===outF);
  if(actF!=='ALL') f=f.filter(p=>p.action===actF);
  renderAccTable(f);
}

function sortBy(col){
  if(sortCol===col)sortDir*=-1;else{sortCol=col;sortDir=-1;}
  applyFilters();
}

function renderAccTable(data){
  const wrap=document.getElementById('acc-tbl');
  if(!wrap) return;
  if(!data.length){wrap.innerHTML='<div class="nd">No predictions match filter</div>';return;}
  const sorted=[...data].sort((a,b)=>{
    let av=a[sortCol]??'',bv=b[sortCol]??'';
    if(typeof av==='number') return (av-bv)*sortDir;
    return String(av).localeCompare(String(bv))*sortDir;
  });
  const rows=sorted.map(p=>{
    const out=p.outcome||'PENDING';
    const rowCls=out==='WIN'?'win-r':out==='LOSS'?'loss-r':out==='FLAT'?'flat-r':'';
    const chP=p.next_day_change_pct;
    const outBadge=out==='WIN'?'<span class="badge b-win">WIN</span>':out==='LOSS'?'<span class="badge b-loss">LOSS</span>':out==='FLAT'?'<span class="badge b-flat">FLAT</span>':'<span class="badge b-pend">PEND</span>';
    const actC=p.action==='BUY_CE'?'var(--bull)':p.action==='BUY_PE'?'var(--bear)':'rgba(255,255,255,.3)';
    const confC=(p.confidence||0)>=75?'var(--bull)':(p.confidence||0)>=55?'var(--warn)':(p.confidence||0)>=40?'var(--acc2)':'rgba(255,255,255,.2)';
    // loss reason hint
    let lossHint='';
    if(out==='LOSS'){
      const notes=[];
      if(p.action==='BUY_CE'&&chP<0) notes.push('price went down');
      if(p.action==='BUY_PE'&&chP>0) notes.push('price went up');
      if((p.indicators_aligned||0)<3) notes.push('weak alignment '+p.indicators_aligned+'/3');
      if(notes.length) lossHint='<div style="font-size:8px;color:rgba(255,80,80,.6);margin-top:1px">'+notes.join(' · ')+'</div>';
    }
    return `<tr class="${rowCls}">
      <td style="font-weight:800;color:var(--acc2)">${p.symbol}</td>
      <td style="color:rgba(255,255,255,.4);font-size:9px">${p.signal_date||'—'}</td>
      <td style="color:rgba(255,255,255,.35);font-size:9px">${p.trade_date||'—'}</td>
      <td><span style="color:${actC};font-weight:800">${p.action}</span></td>
      <td><div class="cb-wrap"><span style="color:${confC};font-weight:800">${p.confidence||0}%</span><div class="cb-track"><div class="cb-fill" style="width:${p.confidence||0}%;background:${confC}"></div></div></div></td>
      <td>${dots(p.indicators_aligned,p.action)}</td>
      <td>${bc(p.pcr_bias)}</td>
      <td>${bc(p.oi_bias)}</td>
      <td>${bc(p.price_direction==='UP'?'BULLISH':p.price_direction==='DOWN'?'BEARISH':'NEUTRAL')}</td>
      <td>${outBadge}${lossHint}</td>
      <td style="color:${chP>0?'var(--bull)':chP<0?'var(--bear)':'rgba(255,255,255,.3)'}">${chP!=null?(chP>0?'+':'')+Number(chP).toFixed(2)+'%':'—'}</td>
      <td style="color:${p.hit_t1?'var(--bull)':'rgba(255,255,255,.15)'}">${p.hit_t1?'✓':'✗'}</td>
      <td style="color:${p.hit_t2?'var(--bull)':'rgba(255,255,255,.15)'}">${p.hit_t2?'✓':'✗'}</td>
      <td style="color:${p.hit_sl?'var(--bear)':'rgba(255,255,255,.15)'}">${p.hit_sl?'✓':'✗'}</td>
    </tr>`;
  }).join('');
  const th=(col,lbl)=>`<th onclick="sortBy('${col}')" class="${sortCol===col?'s'+(sortDir>0?'a':'d'):''}">${lbl}</th>`;
  wrap.innerHTML=`<div style="font-size:9px;color:var(--dimmer);margin-bottom:5px">${data.length} row(s) · WIN = T1 hit (+0.8%) · LOSS = SL hit (−0.5%) · FLAT = neither · Click header to sort</div>
    <div class="tbl-wrap"><table class="mt">
      <thead><tr>${th('symbol','Symbol')}${th('signal_date','Signal Date')}${th('trade_date','Trade Date')}${th('action','Action')}${th('confidence','Conf')}<th>Aligned</th>${th('pcr_bias','PCR')}${th('oi_bias','OI')}<th>Price</th>${th('outcome','Outcome')}${th('next_day_change_pct','Next Day %')}<th>T1</th><th>T2</th><th>SL</th></tr></thead>
      <tbody>${rows}</tbody>
    </table></div>
    <div class="tbl-footer">${data.length} row(s)</div>`;
}

/* CSV */
function exportCsv(){
  const cols=['symbol','signal_date','trade_date','action','confidence','indicators_aligned','pcr_bias','oi_bias','price_direction','outcome','next_day_change_pct','hit_t1','hit_t2','hit_sl'];
  const csv=[cols.join(','),...allPreds.map(p=>cols.map(c=>JSON.stringify(p[c]??'')).join(','))].join('\n');
  const a=document.createElement('a');a.href='data:text/csv;charset=utf-8,'+encodeURIComponent(csv);a.download='v4_predictions.csv';a.click();
}

/* Helpers */
function dots(aligned,action){
  const isBull=action==='BUY_CE',isBear=action==='BUY_PE';
  return Array.from({length:3},(_,i)=>{
    const on=i<(aligned||0);
    const c=on?(isBull?'var(--bull)':isBear?'var(--bear)':'var(--warn)'):'rgba(255,255,255,.1)';
    return `<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${c};margin-right:2px"></span>`;
  }).join('')+`<span style="font-size:8px;color:rgba(255,255,255,.2);margin-left:2px">${aligned||0}/3</span>`;
}
function bc(b){
  if(!b||b==='NEUTRAL') return '<span style="color:rgba(255,255,255,.18);font-size:9px">—</span>';
  if(b==='BULLISH') return '<span class="badge b-bull" style="padding:1px 5px">▲</span>';
  if(b==='BEARISH') return '<span class="badge b-bear" style="padding:1px 5px">▼</span>';
  return '<span style="color:rgba(255,255,255,.3);font-size:9px">'+esc(b)+'</span>';
}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
</script>
@endpush