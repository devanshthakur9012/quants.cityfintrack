@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
:root{
  --g:#00e676;--r:#ff1744;--w:#ffc107;--a:#ff6b00;--a2:#ff9f00;
  --b:#4fc3f7;--pu:#ce93d8;--card:#0a0a18;--bdr:rgba(255,107,0,.13);
  --dim:rgba(255,255,255,.28)
}
.hdr{background:linear-gradient(135deg,#080814,#140828 50%,#081414);border:1px solid rgba(255,107,0,.2);border-radius:12px;padding:14px 20px;margin-bottom:12px;position:relative;overflow:hidden}
.hdr::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--a),var(--a2),var(--a),transparent)}
.hdr h4{color:var(--a2);font-size:16px;font-weight:900;margin:0}
.hdr p{color:rgba(255,255,255,.36);margin:4px 0 0;font-size:10px;line-height:1.7}

/* Tabs */
.tabs{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap}
.tab{padding:7px 20px;border-radius:8px;font-size:11px;font-weight:800;cursor:pointer;border:1px solid;transition:all .18s}
.t-normal{background:rgba(0,230,118,.06);color:var(--g);border-color:rgba(0,230,118,.2)}
.t-normal.active{background:rgba(0,230,118,.18);border-color:rgba(0,230,118,.5);box-shadow:0 0 14px rgba(0,230,118,.15)}
.t-contra{background:rgba(255,23,68,.06);color:var(--r);border-color:rgba(255,23,68,.2)}
.t-contra.active{background:rgba(255,23,68,.18);border-color:rgba(255,23,68,.5);box-shadow:0 0 14px rgba(255,23,68,.15)}
.t-both{background:rgba(206,147,216,.06);color:var(--pu);border-color:rgba(206,147,216,.2)}
.t-both.active{background:rgba(206,147,216,.18);border-color:rgba(206,147,216,.5);box-shadow:0 0 14px rgba(206,147,216,.15)}

/* Mode desc */
.mdesc{border-radius:9px;padding:8px 14px;margin-bottom:10px;font-size:10px;line-height:1.7;border:1px solid;display:none}
.mdesc.active{display:block}
.md-n{background:rgba(0,230,118,.04);border-color:rgba(0,230,118,.15);color:rgba(0,230,118,.75)}
.md-c{background:rgba(255,23,68,.04);border-color:rgba(255,23,68,.15);color:rgba(255,70,80,.75)}
.md-b{background:rgba(206,147,216,.04);border-color:rgba(206,147,216,.15);color:rgba(206,147,216,.85)}

/* Filter bar */
.fb{background:var(--card);border:1px solid var(--bdr);padding:9px 16px;border-radius:10px;margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.fb label{color:var(--dim);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;margin:0}
.fsel,.di{background:rgba(255,107,0,.07);border:1px solid rgba(255,107,0,.22);color:var(--a2);border-radius:7px;padding:5px 10px;font-size:11px;font-weight:700;outline:none;cursor:pointer}
.fsel option{background:#0d0d1f}
.di::-webkit-calendar-picker-indicator{filter:invert(.7) sepia(1) saturate(5) hue-rotate(-15deg)}
.nb{background:rgba(255,107,0,.08);border:1px solid rgba(255,107,0,.18);color:var(--a2);border-radius:6px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px}
.nb:hover{background:rgba(255,107,0,.2)}
.btn-p{background:linear-gradient(135deg,var(--a),var(--a2));color:#000;border:none;border-radius:7px;padding:6px 20px;font-weight:900;font-size:11px;cursor:pointer}
.dv{width:1px;height:20px;background:rgba(255,255,255,.07)}
.lu{font-size:9px;color:rgba(255,255,255,.18);margin-left:auto}

/* Stats */
.stats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.sc{background:var(--card);border:1px solid var(--bdr);border-radius:9px;padding:9px 14px;flex:1;min-width:75px}
.sc .l{font-size:7px;font-weight:800;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:.7px;margin-bottom:3px}
.sc .v{font-size:20px;font-weight:900;line-height:1}
.sc .s{font-size:7px;color:rgba(255,255,255,.2);margin-top:2px}

/* Date banner */
.dbanner{background:rgba(255,107,0,.05);border:1px solid rgba(255,107,0,.15);border-radius:9px;padding:8px 14px;margin-bottom:12px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.dbanner .dl{font-size:11px;font-weight:800}
.dbanner .da{font-size:9px;color:rgba(255,255,255,.3)}

/* Card grid */
.card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(370px,1fr));gap:12px}

/* Signal card */
.sig-card{background:var(--card);border:1px solid var(--bdr);border-radius:12px;overflow:hidden}
.sig-card.win-c {border-left:4px solid var(--g)}
.sig-card.loss-c{border-left:4px solid var(--r)}
.sig-card.pend-c{border-left:4px solid rgba(255,255,255,.1)}
.sig-card.nd-c  {border-left:4px solid rgba(255,193,7,.3)}

/* Card header */
.ch{padding:10px 14px;display:flex;align-items:center;gap:8px;border-bottom:1px solid rgba(255,255,255,.05)}
.ch .sym{font-size:16px;font-weight:900;color:var(--a2)}
.ch .str{font-size:8px;font-weight:700;margin-left:auto}
.cbar{display:flex;align-items:center;gap:4px}
.cbar-t{width:36px;height:4px;background:rgba(255,255,255,.06);border-radius:2px;overflow:hidden}
.cbar-f{height:4px;border-radius:2px}

/* Strike row */
.srow{padding:7px 14px;background:rgba(255,255,255,.015);border-bottom:1px solid rgba(255,255,255,.04);display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.srow .si .l{font-size:7px;color:rgba(255,255,255,.3);font-weight:800;text-transform:uppercase;letter-spacing:.4px}
.srow .si .v{font-size:12px;font-weight:900}

/* Outcome banner */
.ob{padding:9px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;border-bottom:1px solid rgba(255,255,255,.04)}
.ob-win {background:rgba(0,230,118,.05)}
.ob-loss{background:rgba(255,23,68,.04)}
.ob-pend{background:rgba(255,255,255,.01)}
.ob-nd  {background:rgba(255,193,7,.03)}

.move-big{font-size:24px;font-weight:900;line-height:1}
.move-lbl{font-size:7px;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:1px}
.ccount{font-size:10px;font-weight:700;margin-left:auto;background:rgba(255,255,255,.04);border-radius:5px;padding:3px 8px}

/* Both legs */
.legs{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.04)}
.leg{border-radius:8px;padding:8px 10px;border:1px solid}
.leg-ce{background:rgba(0,230,118,.04);border-color:rgba(0,230,118,.18)}
.leg-pe{background:rgba(255,23,68,.04);border-color:rgba(255,23,68,.15)}
.leg-lbl{font-size:8px;font-weight:800;margin-bottom:5px}
.leg-move{font-size:16px;font-weight:900}
.leg-sub{font-size:8px;color:rgba(255,255,255,.3);margin-top:2px}

/* Candle table */
.ctgl{width:100%;padding:5px;background:rgba(255,255,255,.01);border:none;
  color:rgba(255,255,255,.2);font-size:9px;cursor:pointer;text-align:center;font-weight:700}
.ctgl:hover{background:rgba(255,107,0,.05);color:var(--a2)}

.csec{display:none;padding:10px 14px 14px}
.csec.open{display:block}
.cs-ttl{font-size:8px;font-weight:800;color:rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:.6px;margin-bottom:7px}
.eline{display:flex;align-items:center;gap:8px;padding:4px 8px;margin-bottom:6px;
  background:rgba(255,107,0,.07);border-radius:4px;border:1px solid rgba(255,107,0,.2);
  font-size:9px;font-weight:700;color:var(--a2)}
.eline span{font-size:8px;color:rgba(255,255,255,.3);font-weight:400}
.ctw{overflow-x:auto}
table.ct{width:100%;border-collapse:collapse;font-size:9px}
table.ct thead th{padding:4px 7px;text-align:right;font-size:7px;font-weight:800;
  color:rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:.4px;
  border-bottom:1px solid rgba(255,255,255,.06);white-space:nowrap}
table.ct thead th:first-child{text-align:left}
table.ct tbody td{padding:3px 7px;border-bottom:1px solid rgba(255,255,255,.03);
  text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
table.ct tbody td:first-child{text-align:left;color:rgba(255,255,255,.4)}
table.ct tbody tr.above td{background:rgba(0,230,118,.05)}
table.ct tbody tr.above td.hcol{color:var(--g);font-weight:800}
table.ct tbody tr.below td.hcol{color:rgba(255,255,255,.25)}
.best-note{font-size:8px;color:rgba(255,255,255,.2);margin-top:6px}

/* Badges */
.bdg{display:inline-block;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:800;white-space:nowrap}
.b-bull{background:rgba(0,230,118,.12);color:var(--g);border:1px solid rgba(0,230,118,.25)}
.b-bear{background:rgba(255,23,68,.1);color:var(--r);border:1px solid rgba(255,23,68,.2)}
.b-win{background:rgba(0,230,118,.15);color:var(--g);border:1px solid rgba(0,230,118,.3)}
.b-loss{background:rgba(255,23,68,.12);color:var(--r);border:1px solid rgba(255,23,68,.25)}
.b-pend{background:rgba(255,255,255,.04);color:rgba(255,255,255,.3);border:1px solid rgba(255,255,255,.08)}
.b-warn{background:rgba(255,193,7,.1);color:var(--w);border:1px solid rgba(255,193,7,.2)}
.b-neu{background:rgba(255,255,255,.04);color:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.07)}

/* Loading */
.lw{display:flex;flex-direction:column;align-items:center;padding:60px;color:var(--a2);font-size:12px;font-weight:600;gap:12px}
.sp{width:28px;height:28px;border:3px solid rgba(255,107,0,.09);border-top-color:var(--a2);border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.nd{text-align:center;padding:50px;color:rgba(255,255,255,.2);font-size:12px;line-height:1.8}
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

<div class="hdr">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4>📊 EOD Backtest — Option Strike Tracker</h4>
      <p>Signal date → exact strike + entry price → next day option HIGH candle-by-candle →
        <strong style="color:var(--a2)">WIN = any candle HIGH goes above entry price</strong></p>
    </div>
    <a href="{{ route('eod-signal.index') }}"
       style="background:rgba(0,230,118,.07);color:var(--g);border:1px solid rgba(0,230,118,.2);border-radius:7px;padding:6px 14px;font-size:10px;font-weight:700;text-decoration:none">← Today's Signal</a>
  </div>
</div>

<!-- Mode tabs -->
<div class="tabs">
  <button class="tab t-normal active" onclick="switchMode('normal')">✅ Normal — Follow Signal</button>
  <button class="tab t-contra"        onclick="switchMode('contra')">🔄 Contra — Trade Opposite</button>
  <button class="tab t-both"          onclick="switchMode('both')"  >⚡ Both — CE + PE Together</button>
</div>

<!-- Mode descriptions -->
<div class="mdesc md-n active" id="md-normal">
  <strong>NORMAL:</strong> Follow signal exactly. BUY CE signal → buy that CE option at signal's entry price → check next day CE candle HIGHs.
  WIN = any candle HIGH went above entry price. Shows max possible profit and worst drawdown.
</div>
<div class="mdesc md-c" id="md-contra">
  <strong>CONTRA:</strong> Trade the opposite option type. BUY CE signal → buy PE instead (highest-volume ATM PE strike).
  Tests if the opposite direction actually performed better. If contra consistently wins more, the signal is inverted.
</div>
<div class="mdesc md-b" id="md-both">
  <strong>BOTH (Straddle):</strong> Buy CE + PE simultaneously. Shows each leg separately.
  WIN = either leg's HIGH went above its entry price. Tells you if there was enough volatility to profit on either side.
</div>

<!-- Controls -->
<div class="fb">
  <label>Signal Date</label>
  <div class="d-flex align-items-center gap-1">
    <button class="nb" onclick="shift(-1)">‹</button>
    <input type="date" id="dp" class="di"
           value="{{ now()->subDays(1)->toDateString() }}"
           max="{{ now()->toDateString() }}"
           onchange="load()">
    <button class="nb" onclick="shift(1)">›</button>
  </div>
  <div class="dv"></div>
  <label>Symbol</label>
  <select id="sym" class="fsel" onchange="applyFilter()">
    <option value="ALL">All Symbols</option>
  </select>
  <div class="dv"></div>
  <label>Outcome</label>
  <select id="f-out" class="fsel" onchange="applyFilter()">
    <option value="ALL">All</option>
    <option value="WIN">WIN</option>
    <option value="LOSS">LOSS</option>
    <option value="PENDING">Pending</option>
    <option value="NO_DATA">No Data</option>
  </select>
  <button class="btn-p" onclick="load()">▶ Run</button>
  <span class="lu" id="lu"></span>
</div>

<div id="stats-row"></div>
<div id="date-banner"></div>
<div id="card-grid"><div class="lw"><div class="sp"></div>Select a date and click Run</div></div>

</div>
</section>
@endsection

@push('script')
<script>
const ROUTES = {
  normal: '{{ route("eod-backtest.run") }}',
  contra: '{{ route("eod-backtest.contra") }}',
  both  : '{{ route("eod-backtest.both") }}',
};
let allRows = [], currentMode = 'normal';

function switchMode(m) {
  currentMode = m;
  document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
  document.querySelector('.t-' + m).classList.add('active');
  document.querySelectorAll('.mdesc').forEach(d => d.classList.remove('active'));
  document.getElementById('md-' + m).classList.add('active');
  load();
}

function shift(d) {
  const el = document.getElementById('dp');
  const dt = new Date(el.value + 'T00:00:00');
  dt.setDate(dt.getDate() + d);
  const s = dt.toISOString().split('T')[0];
  if (s > '{{ now()->toDateString() }}') return;
  el.value = s; load();
}

function rebuildSyms(syms) {
  const sel = document.getElementById('sym'), prev = sel.value;
  sel.innerHTML = '<option value="ALL">All Symbols</option>';
  syms.forEach(s => {
    const o = document.createElement('option');
    o.value = s; o.textContent = s;
    if (s === prev) o.selected = true;
    sel.appendChild(o);
  });
}

function load() {
  document.getElementById('card-grid').innerHTML = '<div class="lw"><div class="sp"></div>Running analysis…</div>';
  document.getElementById('stats-row').innerHTML = '';
  document.getElementById('date-banner').innerHTML = '';

  $.ajax({
    url: ROUTES[currentMode],
    data: { date: document.getElementById('dp').value, symbol: 'ALL' },
    timeout: 120000,
    success(res) {
      if (res.available_symbols) rebuildSyms(res.available_symbols);
      document.getElementById('lu').textContent = 'Done: ' + new Date().toLocaleTimeString();
      if (!res.success || !res.data?.length) {
        document.getElementById('card-grid').innerHTML =
          '<div class="nd">No data for ' + esc(document.getElementById('dp').value) + '</div>';
        return;
      }
      allRows = res.data;
      renderBanner(res);
      renderStats(res.summary, res.mode);
      applyFilter();
    },
    error(xhr) {
      document.getElementById('card-grid').innerHTML =
        '<div class="nd">⚠ ' + esc((xhr.responseJSON||{}).message||'Server error') + '</div>';
    }
  });
}

function renderBanner(res) {
  const modeLabel = {normal:'✅ Follow Signal', contra:'🔄 Opposite Trade', both:'⚡ CE + PE Together'}[res.mode]||'';
  const modeC = {normal:'var(--g)', contra:'var(--r)', both:'var(--pu)'}[res.mode]||'var(--a2)';
  document.getElementById('date-banner').innerHTML =
    `<div class="dbanner">
      <div><div class="dl" style="color:var(--a2)">📅 Signal Date: <strong>${res.signal_date}</strong></div>
           <div class="da">Strike + entry from EOD signal</div></div>
      <div style="font-size:20px;color:rgba(255,107,0,.25)">→</div>
      <div><div class="dl" style="color:${res.trade_date?'var(--g)':'rgba(255,255,255,.3)'}">
             📈 Trade Date: <strong>${res.trade_date||'No next date'}</strong></div>
           <div class="da">Checking option candle HIGHs — WIN = HIGH &gt; entry price</div></div>
      <div style="margin-left:auto"><strong style="color:${modeC};font-size:10px">${modeLabel}</strong></div>
    </div>`;
}

function renderStats(sum, mode) {
  if (!sum) return;
  const wr  = sum.win_rate;
  const wrC = wr >= 60 ? 'var(--g)' : wr >= 40 ? 'var(--w)' : wr != null ? 'var(--r)' : 'rgba(255,255,255,.3)';
  const bmC = (sum.best_move||0) > 0 ? 'var(--g)' : 'rgba(255,255,255,.3)';

  let extra = '';
  if (mode === 'both' && sum.ce_wins != null) {
    extra = `<div class="sc"><div class="l">CE Wins</div><div class="v" style="color:var(--g)">${sum.ce_wins}</div></div>
             <div class="sc"><div class="l">PE Wins</div><div class="v" style="color:var(--r)">${sum.pe_wins}</div></div>`;
  }

  document.getElementById('stats-row').innerHTML =
    `<div class="stats">
      <div class="sc"><div class="l">Win Rate</div><div class="v" style="color:${wrC}">${wr!=null?wr+'%':'—'}</div><div class="s">${sum.completed} trades</div></div>
      <div class="sc"><div class="l">WIN</div><div class="v" style="color:var(--g)">${sum.wins}</div><div class="s">HIGH &gt; entry</div></div>
      <div class="sc"><div class="l">LOSS</div><div class="v" style="color:var(--r)">${sum.losses}</div></div>
      ${extra}
      <div class="sc"><div class="l">Avg Max Move</div><div class="v" style="color:var(--a2);font-size:14px">${sum.avg_max_move!=null?(sum.avg_max_move>=0?'+':'')+sum.avg_max_move+'%':'—'}</div></div>
      <div class="sc"><div class="l">Best Move</div><div class="v" style="color:${bmC};font-size:14px">${sum.best_move!=null?'+'+sum.best_move+'%':'—'}</div></div>
      <div class="sc"><div class="l">Pending</div><div class="v" style="color:rgba(255,255,255,.3)">${sum.pending}</div></div>
      <div class="sc"><div class="l">No Data</div><div class="v" style="color:var(--w)">${sum.no_data}</div></div>
      <div class="sc"><div class="l">Total</div><div class="v" style="color:var(--b)">${sum.total}</div></div>
    </div>`;
}

function applyFilter() {
  let f = allRows;
  const symF = document.getElementById('sym')?.value   || 'ALL';
  const outF = document.getElementById('f-out')?.value || 'ALL';
  if (symF !== 'ALL') f = f.filter(r => r.symbol  === symF);
  if (outF !== 'ALL') f = f.filter(r => r.outcome === outF);
  renderCards(f);
}

function renderCards(data) {
  if (!data.length) {
    document.getElementById('card-grid').innerHTML = '<div class="nd">No signals match filter</div>';
    return;
  }
  document.getElementById('card-grid').innerHTML =
    '<div class="card-grid">' + data.map((d, i) => {
      if (currentMode === 'both') return renderBothCard(d, i);
      if (currentMode === 'contra') return renderContraCard(d, i);
      return renderNormalCard(d, i);
    }).join('') + '</div>';
}

// ── NORMAL CARD ──────────────────────────────────────────────────────
function renderNormalCard(d, i) {
  const out    = d.outcome || 'PENDING';
  const actC   = (d.action||'').includes('CE') ? 'var(--g)' : 'var(--r)';
  const actBdg = (d.action||'').includes('CE') ? '<span class="bdg b-bull">▲ BUY CE</span>' : '<span class="bdg b-bear">▼ BUY PE</span>';
  const conf   = d.confidence || 0;
  const confC  = conf>=75?'var(--g)':conf>=55?'var(--w)':conf>=40?'var(--a2)':'rgba(255,255,255,.2)';
  const strS   = {STRONG:'color:var(--g)',MODERATE:'color:var(--w)',WEAK:'color:var(--a2)'}[d.strength]||'color:rgba(255,255,255,.25)';
  const cardCls= out==='WIN'?'win-c':out==='LOSS'?'loss-c':out==='PENDING'?'pend-c':'nd-c';

  const strikeRow = d.traded_strike ? `
    <div class="srow">
      <div class="si"><div class="l">Strike</div><div class="v" style="color:${actC}">${fmt0(d.traded_strike)} ${d.traded_option_type||''}</div></div>
      <div class="si"><div class="l">Position</div><div class="v" style="font-size:10px;color:var(--a2)">${d.signal_strike_pos||'ATM'}</div></div>
      <div class="si"><div class="l">Entry Price</div><div class="v" style="color:var(--w)">₹${fmt2(d.traded_entry_price)}</div></div>
      <div class="si"><div class="l">Expiry</div><div class="v" style="font-size:9px;color:rgba(255,255,255,.35)">${d.expiry||'—'}</div></div>
    </div>` : '';

  const ob = outcomeBanner(out, d, actC);
  const ct = candleTable(d.candles||[], d.traded_entry_price, d.max_move_candle, d.first_candle_above, d.max_high, d.max_move_pct, i, 'main');

  return `<div class="sig-card ${cardCls}">
    <div class="ch">
      <span class="sym">${d.symbol}</span>${actBdg}
      <div class="cbar"><span style="font-size:11px;font-weight:900;color:${confC}">${conf}%</span>
        <div class="cbar-t"><div class="cbar-f" style="width:${conf}%;background:${confC}"></div></div></div>
      <span class="str" style="${strS}">${d.strength||'—'}</span>
    </div>
    ${strikeRow}${ob}
    ${ct.btn}
    <div class="csec" id="cs-${i}-main">${ct.content}</div>
  </div>`;
}

// ── CONTRA CARD ──────────────────────────────────────────────────────
function renderContraCard(d, i) {
  const out    = d.outcome || 'PENDING';
  const origC  = (d.original_signal_type||'').includes('CE') ? 'var(--g)' : 'var(--r)';
  const contraC= (d.traded_option_type||'').includes('CE') ? 'var(--g)' : 'var(--r)';
  const conf   = d.confidence || 0;
  const confC  = conf>=75?'var(--g)':conf>=55?'var(--w)':conf>=40?'var(--a2)':'rgba(255,255,255,.2)';
  const strS   = {STRONG:'color:var(--g)',MODERATE:'color:var(--w)',WEAK:'color:var(--a2)'}[d.strength]||'color:rgba(255,255,255,.25)';
  const cardCls= out==='WIN'?'win-c':out==='LOSS'?'loss-c':out==='PENDING'?'pend-c':'nd-c';

  const strikeRow = d.traded_strike ? `
    <div class="srow">
      <div class="si"><div class="l">Signal was</div>
        <div class="v" style="font-size:10px;color:${origC};text-decoration:line-through;opacity:.5">
          ${fmt0(d.original_strike)} ${d.original_signal_type||''}</div></div>
      <div class="si"><div class="l">Contra Bought</div>
        <div class="v" style="color:${contraC}">${fmt0(d.traded_strike)} ${d.traded_option_type||''}</div></div>
      <div class="si"><div class="l">Entry Price</div>
        <div class="v" style="color:var(--w)">₹${fmt2(d.traded_entry_price)}</div></div>
      <div class="si"><div class="l">Expiry</div>
        <div class="v" style="font-size:9px;color:rgba(255,255,255,.35)">${d.expiry||'—'}</div></div>
    </div>` : '';

  const ob = outcomeBanner(out, d, contraC);
  const ct = candleTable(d.candles||[], d.traded_entry_price, d.max_move_candle, d.first_candle_above, d.max_high, d.max_move_pct, i, 'main');

  return `<div class="sig-card ${cardCls}">
    <div class="ch">
      <span class="sym">${d.symbol}</span>
      <span class="bdg" style="background:rgba(255,23,68,.1);color:var(--r);border:1px solid rgba(255,23,68,.2)">🔄 CONTRA ${d.traded_option_type||''}</span>
      <div class="cbar"><span style="font-size:11px;font-weight:900;color:${confC}">${conf}%</span>
        <div class="cbar-t"><div class="cbar-f" style="width:${conf}%;background:${confC}"></div></div></div>
      <span class="str" style="${strS}">${d.strength||'—'}</span>
    </div>
    ${strikeRow}${ob}
    ${ct.btn}
    <div class="csec" id="cs-${i}-main">${ct.content}</div>
  </div>`;
}

// ── BOTH CARD ────────────────────────────────────────────────────────
function renderBothCard(d, i) {
  const out    = d.outcome || 'PENDING';
  const conf   = d.confidence || 0;
  const confC  = conf>=75?'var(--g)':conf>=55?'var(--w)':conf>=40?'var(--a2)':'rgba(255,255,255,.2)';
  const strS   = {STRONG:'color:var(--g)',MODERATE:'color:var(--w)',WEAK:'color:var(--a2)'}[d.strength]||'color:rgba(255,255,255,.25)';
  const cardCls= out==='WIN'?'win-c':out==='LOSS'?'loss-c':out==='PENDING'?'pend-c':'nd-c';
  const bestMove = d.best_move_pct;
  const bmC    = bestMove>20?'var(--g)':bestMove>5?'var(--w)':bestMove>0?'rgba(0,230,118,.5)':'rgba(255,23,68,.5)';

  // Combined outcome banner
  let ob = '';
  if (out === 'WIN') {
    ob = `<div class="ob ob-win">
      <div><div class="move-lbl">Best Leg Move</div>
           <div class="move-big" style="color:${bmC}">+${fmt2(bestMove)}%</div></div>
      <div style="margin-left:8px"><div class="move-lbl">Best Leg</div>
           <div style="font-size:13px;font-weight:900;color:${d.best_leg==='CE'?'var(--g)':'var(--r)'}">${d.best_leg||'—'}</div></div>
      <span class="bdg b-win" style="margin-left:auto">✅ WIN</span>
    </div>`;
  } else if (out === 'LOSS') {
    ob = `<div class="ob ob-loss">
      <div><div class="move-lbl">Best possible</div>
           <div class="move-big" style="color:rgba(255,23,68,.6)">${fmt2(bestMove)}%</div></div>
      <span class="bdg b-loss" style="margin-left:auto">❌ LOSS</span>
    </div>`;
  } else if (out === 'PENDING') {
    ob = `<div class="ob ob-pend"><span class="bdg b-pend">⏳ Pending</span></div>`;
  } else {
    ob = `<div class="ob ob-nd"><span class="bdg b-warn">⚠ No Data</span></div>`;
  }

  // CE + PE legs side by side
  const legs = (d.ce_strike || d.pe_strike) ? `<div class="legs">
    <div class="leg leg-ce">
      <div class="leg-lbl" style="color:var(--g)">📈 CE ${d.ce_strike ? fmt0(d.ce_strike) : '—'}</div>
      <div>Entry: ₹${fmt2(d.ce_entry_price)}</div>
      <div class="leg-move" style="color:${(d.ce_max_move_pct||0)>0?'var(--g)':'rgba(255,23,68,.6)'}">
        ${d.ce_max_move_pct!=null?(d.ce_max_move_pct>=0?'+':'')+fmt2(d.ce_max_move_pct)+'%':'—'}</div>
      <div class="leg-sub">${d.ce_candles_above||0}/${d.ce_candles_total||0} candles above</div>
      <div style="margin-top:4px">${legBdg(d.ce_outcome)}</div>
    </div>
    <div class="leg leg-pe">
      <div class="leg-lbl" style="color:var(--r)">📉 PE ${d.pe_strike ? fmt0(d.pe_strike) : '—'}</div>
      <div>Entry: ₹${fmt2(d.pe_entry_price)}</div>
      <div class="leg-move" style="color:${(d.pe_max_move_pct||0)>0?'var(--g)':'rgba(255,23,68,.6)'}">
        ${d.pe_max_move_pct!=null?(d.pe_max_move_pct>=0?'+':'')+fmt2(d.pe_max_move_pct)+'%':'—'}</div>
      <div class="leg-sub">${d.pe_candles_above||0}/${d.pe_candles_total||0} candles above</div>
      <div style="margin-top:4px">${legBdg(d.pe_outcome)}</div>
    </div>
  </div>` : '';

  // Candle tables for CE and PE
  const ceCt = candleTable(d.ce_candles||[], d.ce_entry_price, d.ce_max_candle, d.ce_first_above, d.ce_max_high, d.ce_max_move_pct, i, 'ce');
  const peCt = candleTable(d.pe_candles||[], d.pe_entry_price, d.pe_max_candle, d.pe_first_above, d.pe_max_high, d.pe_max_move_pct, i, 'pe');

  const hasCt = (d.ce_candles||[]).length || (d.pe_candles||[]).length;

  return `<div class="sig-card ${cardCls}">
    <div class="ch">
      <span class="sym">${d.symbol}</span>
      <span class="bdg" style="background:rgba(206,147,216,.1);color:var(--pu);border:1px solid rgba(206,147,216,.2)">⚡ STRADDLE</span>
      <div class="cbar"><span style="font-size:11px;font-weight:900;color:${confC}">${conf}%</span>
        <div class="cbar-t"><div class="cbar-f" style="width:${conf}%;background:${confC}"></div></div></div>
      <span class="str" style="${strS}">${d.strength||'—'}</span>
    </div>
    ${ob}${legs}
    ${hasCt ? `<button class="ctgl" onclick="toggleCs(${i},'both')">Show candles ▼</button>
    <div class="csec" id="cs-${i}-both">
      <div style="font-size:8px;font-weight:800;color:var(--g);text-transform:uppercase;letter-spacing:.6px;padding:10px 14px 4px">📈 CE Candles — ${d.ce_strike?fmt0(d.ce_strike):''}</div>
      <div style="padding:0 14px 10px">${ceCt.content}</div>
      <div style="font-size:8px;font-weight:800;color:var(--r);text-transform:uppercase;letter-spacing:.6px;padding:0 14px 4px">📉 PE Candles — ${d.pe_strike?fmt0(d.pe_strike):''}</div>
      <div style="padding:0 14px 14px">${peCt.content}</div>
    </div>` : ''}
  </div>`;
}

// ── Outcome banner for normal/contra ────────────────────────────────
function outcomeBanner(out, d, actC) {
  if (out === 'WIN') {
    const mc = (d.max_move_pct||0)>=20?'var(--g)':(d.max_move_pct||0)>=5?'var(--w)':'rgba(0,230,118,.6)';
    return `<div class="ob ob-win">
      <div><div class="move-lbl">Max Move (HIGH)</div>
           <div class="move-big" style="color:${mc}">+${fmt2(d.max_move_pct)}%</div></div>
      <div style="margin-left:10px"><div class="move-lbl">Max Loss (LOW)</div>
           <div style="font-size:14px;font-weight:800;color:${(d.max_loss_pct||0)<-10?'var(--r)':'rgba(255,255,255,.4)'}">${fmt2(d.max_loss_pct)}%</div></div>
      <div class="ccount" style="color:var(--g)">✓ ${d.candles_above}/${d.candles_total} above entry</div>
      <span class="bdg b-win" style="margin-left:auto">✅ WIN</span>
    </div>`;
  }
  if (out === 'LOSS') {
    return `<div class="ob ob-loss">
      <div><div class="move-lbl">Max Reached (HIGH)</div>
           <div class="move-big" style="color:rgba(255,23,68,.6)">${fmt2(d.max_move_pct)}%</div></div>
      <div style="margin-left:10px"><div class="move-lbl">Max Loss (LOW)</div>
           <div style="font-size:14px;font-weight:800;color:var(--r)">${fmt2(d.max_loss_pct)}%</div></div>
      <div class="ccount" style="color:var(--r)">0/${d.candles_total||0} above entry</div>
      <span class="bdg b-loss" style="margin-left:auto">❌ LOSS</span>
    </div>`;
  }
  if (out === 'PENDING') return `<div class="ob ob-pend"><span class="bdg b-pend">⏳ Pending — no next trading day yet</span></div>`;
  return `<div class="ob ob-nd"><span class="bdg b-warn">⚠ ${out==='NO_ORDER'?'No order block built':'No option candle data for trade date'}</span></div>`;
}

// ── Candle table builder ─────────────────────────────────────────────
function candleTable(candles, entryPrice, maxCandle, firstAbove, maxHigh, maxMovePct, cardIdx, leg) {
  if (!candles.length) return { btn: '', content: '<div style="font-size:9px;color:rgba(255,255,255,.2);padding:4px">No candle data</div>' };

  const rows = candles.map(c => {
    const hC = c.above_entry ? 'var(--g)' : 'rgba(255,255,255,.25)';
    return `<tr class="${c.above_entry?'above':'below'}">
      <td>${c.time}</td>
      <td>${fmt2(c.open)}</td>
      <td class="hcol" style="color:${hC}">
        ${fmt2(c.high)}
        <span style="font-size:7px;margin-left:2px;color:${c.above_entry?'var(--g)':'rgba(255,255,255,.2)'}">
          ${c.high_move_pct>=0?'+':''}${fmt2(c.high_move_pct)}%
        </span>
      </td>
      <td style="color:${c.low_move_pct<-15?'var(--r)':'rgba(255,255,255,.3)'}">${fmt2(c.low)}</td>
      <td style="color:${c.close_move_pct>0?'rgba(0,230,118,.6)':c.close_move_pct<0?'rgba(255,23,68,.5)':'rgba(255,255,255,.3)'}">${fmt2(c.close)}</td>
      <td style="color:rgba(255,255,255,.3)">${fmtVol(c.volume)}</td>
    </tr>`;
  }).join('');

  const ep = entryPrice ? fmt2(entryPrice) : '—';
  const note = maxCandle ? `<div class="best-note">🏆 Best exit: <strong style="color:var(--a2)">${maxCandle}</strong> · MAX HIGH ₹${fmt2(maxHigh)} (+${fmt2(maxMovePct)}%)${firstAbove?' · First cross: <strong style="color:var(--g)">'+firstAbove+'</strong>':''}</div>` : '';

  const content = `
    <div class="eline"><span>📌 Entry Price</span> ₹${ep} <span>— candles highlighted green = HIGH above this</span></div>
    <div class="ctw"><table class="ct">
      <thead><tr><th style="text-align:left">Time</th><th>Open</th><th>High ▲</th><th>Low</th><th>Close</th><th>Vol</th></tr></thead>
      <tbody>${rows}</tbody>
    </table></div>${note}`;

  const id = `cs-${cardIdx}-${leg}`;
  const btn = `<button class="ctgl" onclick="toggleCs(${cardIdx},'${leg}')">Show ${candles.length} candles ▼</button>`;

  return { btn, content };
}

function toggleCs(i, leg) {
  const sec = document.getElementById(`cs-${i}-${leg}`);
  if (!sec) return;
  const open = sec.classList.toggle('open');
  const btn  = sec.previousElementSibling;
  if (btn && btn.classList.contains('ctgl')) {
    const n = sec.querySelectorAll('table.ct tbody tr').length;
    btn.textContent = open ? 'Hide candles ▲' : `Show ${n} candles ▼`;
  }
}

// ── Helpers ──────────────────────────────────────────────────────────
function legBdg(out) {
  if (out==='WIN')  return '<span class="bdg b-win" style="font-size:8px">WIN</span>';
  if (out==='LOSS') return '<span class="bdg b-loss" style="font-size:8px">LOSS</span>';
  if (out==='PENDING') return '<span class="bdg b-pend" style="font-size:8px">⏳</span>';
  return '<span class="bdg b-warn" style="font-size:8px">NO DATA</span>';
}
function fmt0(n) { return n != null ? Number(n).toLocaleString('en-IN') : '—'; }
function fmt2(n) { return n != null ? Number(n).toFixed(2) : '—'; }
function fmtVol(n) {
  if (!n) return '—';
  if (n>=1e5) return (n/1e5).toFixed(1)+'L';
  if (n>=1e3) return (n/1e3).toFixed(1)+'K';
  return n;
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
@endpush