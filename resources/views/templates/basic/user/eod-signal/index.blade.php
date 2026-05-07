@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ── Design tokens ─────────────────────────────────────────────────────── */
:root{
  --g:#00e676;--r:#ff1744;--w:#ffc107;--a:#ff6b00;--a2:#ff9f00;
  --b:#4fc3f7;--pu:#ce93d8;--card:#0a0a18;
  --bdr:rgba(255,107,0,.13);--dim:rgba(255,255,255,.28)
}

/* ── Header ────────────────────────────────────────────────────────────── */
.hdr{background:linear-gradient(135deg,#080814,#140828 50%,#081414);border:1px solid rgba(255,107,0,.2);border-radius:12px;padding:14px 20px;margin-bottom:12px;position:relative;overflow:hidden}
.hdr::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--a),var(--a2),var(--a),transparent)}
.hdr h4{color:var(--a2);font-size:16px;font-weight:900;margin:0}
.hdr p{color:rgba(255,255,255,.36);margin:4px 0 0;font-size:10px;line-height:1.7}

/* ── Filter bar ────────────────────────────────────────────────────────── */
.fb{background:var(--card);border:1px solid var(--bdr);padding:9px 16px;border-radius:10px;margin-bottom:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.fb label{color:var(--dim);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;margin:0}
.fsel,.di{background:rgba(255,107,0,.07);border:1px solid rgba(255,107,0,.22);color:var(--a2);border-radius:7px;padding:5px 10px;font-size:11px;font-weight:700;outline:none;cursor:pointer}
.fsel option{background:#0d0d1f}
.di::-webkit-calendar-picker-indicator{filter:invert(.7) sepia(1) saturate(5) hue-rotate(-15deg);cursor:pointer}
.nb{background:rgba(255,107,0,.08);border:1px solid rgba(255,107,0,.18);color:var(--a2);border-radius:6px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px}
.nb:hover{background:rgba(255,107,0,.2)}
.btn-p{background:linear-gradient(135deg,var(--a),var(--a2));color:#000;border:none;border-radius:7px;padding:6px 18px;font-weight:900;font-size:11px;cursor:pointer}
.dv{width:1px;height:20px;background:rgba(255,255,255,.07)}
.lu{font-size:9px;color:rgba(255,255,255,.18);margin-left:auto}

/* ── Market status bar ─────────────────────────────────────────────────── */
.ms{display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:8px;margin-bottom:10px;font-size:10px;font-weight:700;border:1px solid}
.ms-open{background:rgba(255,193,7,.06);border-color:rgba(255,193,7,.22);color:var(--w)}
.ms-closed{background:rgba(0,230,118,.05);border-color:rgba(0,230,118,.2);color:var(--g)}
.ms-hist{background:rgba(79,195,247,.04);border-color:rgba(79,195,247,.15);color:var(--b)}
.ms-dot{width:7px;height:7px;border-radius:50%;animation:pulse 1.2s ease-in-out infinite alternate;flex-shrink:0}
@keyframes pulse{from{opacity:.4;transform:scale(.9)}to{opacity:1;transform:scale(1.1)}}

/* ── Summary stat boxes ────────────────────────────────────────────────── */
.stats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
.sb{background:var(--card);border:1px solid var(--bdr);border-radius:9px;padding:9px 13px;flex:1;min-width:80px}
.sb .l{font-size:8px;font-weight:800;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:.7px;margin-bottom:3px}
.sb .v{font-size:18px;font-weight:900;line-height:1}

/* ── Main table container ──────────────────────────────────────────────── */
.sec{background:var(--card);border:1px solid var(--bdr);border-radius:10px;overflow:hidden}
.tbl-wrap{overflow-x:auto}
table.mt{width:100%;border-collapse:collapse;font-size:10px}
table.mt thead th{
  padding:7px 10px;text-align:left;font-size:8px;font-weight:800;
  color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:.5px;
  border-bottom:1px solid rgba(255,255,255,.07);white-space:nowrap;
  background:rgba(255,255,255,.01)
}
table.mt tbody td{padding:6px 10px;border-bottom:1px solid rgba(255,255,255,.03);white-space:nowrap;vertical-align:middle}
table.mt tbody tr:hover td{background:rgba(255,107,0,.02)}
table.mt tbody tr.tr-bull td:first-child{border-left:3px solid var(--g)}
table.mt tbody tr.tr-bear td:first-child{border-left:3px solid var(--r)}
table.mt tbody tr.tr-wait td:first-child{border-left:3px solid rgba(255,255,255,.08)}

/* ── Badges ────────────────────────────────────────────────────────────── */
.bdg{display:inline-block;padding:2px 7px;border-radius:4px;font-size:9px;font-weight:800;white-space:nowrap}
.b-bull{background:rgba(0,230,118,.12);color:var(--g);border:1px solid rgba(0,230,118,.25)}
.b-bear{background:rgba(255,23,68,.10);color:var(--r);border:1px solid rgba(255,23,68,.2)}
.b-wait{background:rgba(255,193,7,.10);color:var(--w);border:1px solid rgba(255,193,7,.2)}
.b-neu{background:rgba(255,255,255,.04);color:rgba(255,255,255,.25);border:1px solid rgba(255,255,255,.07)}
.b-ideal{background:rgba(0,230,118,.10);color:var(--g);border:1px solid rgba(0,230,118,.2)}
.b-spec{background:rgba(255,193,7,.10);color:var(--w);border:1px solid rgba(255,193,7,.2)}
.b-exp{background:rgba(255,107,0,.10);color:var(--a2);border:1px solid rgba(255,107,0,.25)}
.b-junk{background:rgba(255,23,68,.07);color:rgba(255,80,80,.6);border:1px solid rgba(255,23,68,.15)}

/* ── Confidence bar ────────────────────────────────────────────────────── */
.cb{display:flex;align-items:center;gap:5px}
.cb-t{width:45px;height:5px;background:rgba(255,255,255,.05);border-radius:2px;overflow:hidden;flex-shrink:0}
.cb-f{height:5px;border-radius:2px}

/* ── Aligned dots ──────────────────────────────────────────────────────── */
.dot{width:7px;height:7px;border-radius:50%;display:inline-block;margin-right:2px}

/* ── ORDER CARD (the new element) ─────────────────────────────────────── */
.order-card{
  background:rgba(0,0,0,.3);border:1px solid rgba(255,107,0,.18);
  border-radius:8px;padding:8px 12px;margin-top:0;
  display:flex;align-items:center;gap:10px;flex-wrap:wrap
}
.order-card .oc-type{
  font-size:11px;font-weight:900;letter-spacing:.5px
}
.order-card .oc-strike{
  font-size:13px;font-weight:900;color:#fff
}
.order-card .oc-pos{
  font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;
  background:rgba(255,107,0,.13);color:var(--a2)
}
.order-card .oc-price{
  font-size:12px;font-weight:900
}
.order-card .oc-label{
  font-size:8px;color:rgba(255,255,255,.3);font-weight:700;
  text-transform:uppercase;letter-spacing:.5px;margin-bottom:1px
}
.order-card .oc-vol{
  font-size:9px;color:rgba(255,255,255,.35)
}
.order-card .oc-conv{
  font-size:8px;color:var(--g);font-weight:800
}
.order-card .oc-note{
  font-size:8px;color:rgba(255,255,255,.22);margin-left:auto;text-align:right
}

/* ── Detail expand panel ───────────────────────────────────────────────── */
.detail-row{display:none}
.detail-row.open{display:table-row}
.detail-cell{padding:10px 12px 12px;background:rgba(0,0,0,.18)}

/* ── Entry rules box ───────────────────────────────────────────────────── */
.entry-box{background:rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.05);border-radius:7px;padding:7px 10px;font-size:9px;color:rgba(255,255,255,.4);line-height:1.6}
.entry-box .et{font-size:8px;font-weight:800;color:rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:.7px;margin-bottom:4px}
.entry-box .er{display:flex;gap:5px;margin-bottom:2px}
.step-n{min-width:14px;height:14px;background:rgba(255,107,0,.13);color:var(--a2);border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:7px;font-weight:900;flex-shrink:0}

/* ── Candidates mini-table in detail ───────────────────────────────────── */
.cand-tbl{width:100%;border-collapse:collapse;font-size:9px;margin-top:6px}
.cand-tbl th{color:rgba(255,255,255,.2);font-weight:800;font-size:8px;text-align:left;padding:3px 6px;border-bottom:1px solid rgba(255,255,255,.05)}
.cand-tbl td{padding:3px 6px;border-bottom:1px solid rgba(255,255,255,.03)}
.cand-tbl tr.best-row td{background:rgba(255,107,0,.05)}
.cand-tbl tr.skip-row td{opacity:.4}

/* ── Loading / no-data ─────────────────────────────────────────────────── */
.lw{display:flex;flex-direction:column;align-items:center;padding:50px;color:var(--a2);font-size:12px;font-weight:600;gap:12px}
.sp{width:28px;height:28px;border:3px solid rgba(255,107,0,.09);border-top-color:var(--a2);border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.nd{text-align:center;padding:40px;color:rgba(255,255,255,.2);font-size:12px}
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

{{-- ── Header ─────────────────────────────────────────────────────────── --}}
<div class="hdr">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4>📊 EOD Signal — Daily Trade Picker <span style="font-size:10px;color:rgba(255,255,255,.25);font-weight:400">v9</span></h4>
      <p>
        PCR (35%) + OI microstructure (35%) + Price ATR (30%) →
        <strong style="color:var(--a2)">strike + entry price ready for next-day 9:45 entry</strong>
        &nbsp;·&nbsp; Signal final after 15:05 &nbsp;·&nbsp; Hard exit 11:00
      </p>
    </div>
    <a href="{{ route('eod-backtest.index') }}"
       style="background:rgba(178,255,89,.07);color:#b2ff59;border:1px solid rgba(178,255,89,.2);border-radius:7px;padding:6px 14px;font-size:10px;font-weight:700;text-decoration:none">
      📈 Track Record
    </a>
  </div>
</div>

{{-- ── Market status bar ──────────────────────────────────────────────── --}}
<div id="ms-bar"></div>

{{-- ── Filter bar ─────────────────────────────────────────────────────── --}}
<div class="fb">
  <label>Date</label>
  <div class="d-flex align-items-center gap-1">
    <button class="nb" onclick="shift(-1)">‹</button>
    <input type="date" id="dp" class="di"
           value="{{ now()->toDateString() }}"
           max="{{ now()->toDateString() }}"
           onchange="load()">
    <button class="nb" onclick="shift(1)">›</button>
    <button class="nb" style="width:auto;padding:0 8px;font-size:9px" onclick="goToday()">Today</button>
  </div>
  <div class="dv"></div>
  <label>Symbol</label>
  <select id="sym" class="fsel" onchange="load()">
    <option value="ALL">All Symbols</option>
  </select>
  <label style="display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--dim);font-size:9px;font-weight:800">
    <input type="checkbox" id="saveChk" style="cursor:pointer"> Save to DB
  </label>
  <button class="btn-p" onclick="load()">↻ Analyse</button>
  <span class="lu" id="lu"></span>
</div>

{{-- ── Stats row ───────────────────────────────────────────────────────── --}}
<div id="stats-row"></div>

{{-- ── Main table ─────────────────────────────────────────────────────── --}}
<div class="sec">
  <div id="tbl-wrap"><div class="lw"><div class="sp"></div>Loading…</div></div>
</div>

</div>
</section>
@endsection

@push('script')
<script>
const today = '{{ now()->toDateString() }}';
let symsCache = [];

$(document).ready(() => load());

const getDate = () => document.getElementById('dp').value;
const getSym  = () => document.getElementById('sym').value;

/* ── Date navigation ───────────────────────────────────────────────────── */
function shift(d) {
  const el = document.getElementById('dp');
  const dt = new Date(el.value);
  dt.setDate(dt.getDate() + d);
  const s = dt.toISOString().split('T')[0];
  if (s > today) return;
  el.value = s; load();
}
function goToday() { document.getElementById('dp').value = today; load(); }

/* ── Symbol dropdown rebuild ───────────────────────────────────────────── */
function rebuildSyms(syms) {
  if (JSON.stringify(symsCache) === JSON.stringify(syms)) return;
  symsCache = syms;
  const sel = document.getElementById('sym'), prev = sel.value;
  sel.innerHTML = '<option value="ALL">All Symbols</option>';
  syms.forEach(s => {
    const o = document.createElement('option');
    o.value = s; o.textContent = s;
    if (s === prev) o.selected = true;
    sel.appendChild(o);
  });
}

/* ── Main load ─────────────────────────────────────────────────────────── */
function load() {
  document.getElementById('tbl-wrap').innerHTML = '<div class="lw"><div class="sp"></div>Running analysis…</div>';
  document.getElementById('stats-row').innerHTML = '';
  const save = document.getElementById('saveChk').checked ? 1 : 0;

  $.ajax({
    url: '{{ route("eod-signal.signals") }}',
    data: { date: getDate(), symbol: getSym(), save },
    success(res) {
      renderMsBar(res);
      if (res.available_symbols) rebuildSyms(res.available_symbols);
      if (!res.success || !res.data?.length) {
        document.getElementById('tbl-wrap').innerHTML =
          '<div class="nd">No data for ' + getDate() +
          '<br><small>Make sure option data is collected for this date.</small></div>';
        return;
      }
      renderStats(res.data);
      renderTable(res.data, res.next_trade_date);
      document.getElementById('lu').textContent = 'Updated: ' + new Date().toLocaleTimeString();
    },
    error(xhr) {
      document.getElementById('tbl-wrap').innerHTML =
        '<div class="nd">⚠ ' + esc((xhr.responseJSON || {}).message || 'Server error') + '</div>';
    }
  });
}

/* ── Market status bar ─────────────────────────────────────────────────── */
function renderMsBar(res) {
  const el = document.getElementById('ms-bar');
  if (!res.is_today) {
    el.innerHTML = '<div class="ms ms-hist">📅 Historical view — ' + res.date + '</div>';
    return;
  }
  if (res.market_closed) {
    el.innerHTML =
      '<div class="ms ms-closed"><span class="ms-dot" style="background:var(--g)"></span>' +
      '✅ Market closed · Signals final · Trade on: <strong>' + (res.next_trade_date || '—') + '</strong>' +
      (res.incomplete_symbols?.length
        ? ' &nbsp;·&nbsp; <span style="color:var(--w)">⚠ ' + res.incomplete_symbols.length + ' symbol(s) data incomplete</span>'
        : '') +
      '</div>';
  } else {
    el.innerHTML =
      '<div class="ms ms-open"><span class="ms-dot" style="background:var(--w)"></span>' +
      '⏳ Market open (' + res.current_time + ') · Signal + strike ready after <strong>15:05</strong></div>';
  }
}

/* ── Stats boxes ───────────────────────────────────────────────────────── */
function renderStats(data) {
  let bull = 0, bear = 0, wait = 0, strong = 0;
  data.forEach(d => {
    const a = (d.signal || {}).action;
    if (a === 'BUY_CE') bull++;
    else if (a === 'BUY_PE') bear++;
    else wait++;
    if ((d.signal || {}).strength === 'STRONG') strong++;
  });
  document.getElementById('stats-row').innerHTML =
    '<div class="stats">' +
    sb('BUY CE',  bull,   'var(--g)') +
    sb('BUY PE',  bear,   'var(--r)') +
    sb('Strong',  strong, 'var(--a2)') +
    sb('Wait',    wait,   'rgba(255,255,255,.3)') +
    sb('Total',   data.length, 'var(--b)') +
    '</div>';
}
function sb(label, val, color) {
  return `<div class="sb"><div class="l">${label}</div><div class="v" style="color:${color}">${val}</div></div>`;
}

/* ── Main table render ─────────────────────────────────────────────────── */
function renderTable(data, nextDay) {
  if (!data.length) {
    document.getElementById('tbl-wrap').innerHTML = '<div class="nd">No signals</div>';
    return;
  }

  const rows = data.map((d, i) => {
    const sig    = d.signal     || {};
    const ind    = d.indicators || {};
    const day    = d.day        || {};
    const order  = d.order      || null;
    const action = sig.action   || 'WAIT';
    const conf   = sig.confidence || 0;
    const pcr    = ind.pcr    || {};
    const oi     = ind.oi     || {};
    const price  = ind.price  || {};
    const chP    = day.change_pct;

    // Row class
    const trCls = action === 'BUY_CE' ? 'tr-bull'
                : action === 'BUY_PE' ? 'tr-bear'
                : 'tr-wait';

    // Action badge
    const actBdg = action === 'BUY_CE'
      ? '<span class="bdg b-bull">🟢 BUY CE</span>'
      : action === 'BUY_PE'
        ? '<span class="bdg b-bear">🔴 BUY PE</span>'
        : '<span class="bdg b-wait">⏳ ' + esc(action) + '</span>';

    // Confidence color
    const confC = conf >= 75 ? 'var(--g)' : conf >= 55 ? 'var(--w)' : conf >= 40 ? 'var(--a2)' : 'rgba(255,255,255,.2)';

    // Day change color
    const chC = chP > 0 ? 'var(--g)' : chP < 0 ? 'var(--r)' : 'rgba(255,255,255,.3)';

    // PCR color
    const pcrC = (day.pcr_eod || 0) >= 1.1 ? 'var(--g)'
               : (day.pcr_eod || 0) <= 0.8 ? 'var(--r)'
               : 'rgba(255,255,255,.4)';

    // Alignment dots
    const dots = [pcr.bias, oi.bias, price.bias].map(b => {
      const on = (action === 'BUY_CE' && b === 'BULLISH') || (action === 'BUY_PE' && b === 'BEARISH');
      const c  = on ? (action === 'BUY_CE' ? 'var(--g)' : 'var(--r)') : 'rgba(255,255,255,.1)';
      return `<span class="dot" style="background:${c}"></span>`;
    }).join('') + `<span style="font-size:8px;color:rgba(255,255,255,.2)">${sig.aligned || 0}/3</span>`;

    // ── ORDER CARD (new) ──────────────────────────────────────────────────
    const orderCell = order
      ? renderOrderCard(order, action)
      : '<span style="font-size:9px;color:rgba(255,255,255,.18)">—</span>';

    // Data incomplete row
    if (d.data_incomplete) {
      return `<tr class="tr-wait" id="tr-${i}">
        <td style="font-weight:900;color:var(--a2)">${esc(d.symbol)}</td>
        <td><span class="bdg b-wait">⏳ WAIT</span></td>
        <td colspan="9" style="font-size:9px;color:rgba(255,193,7,.5)">${esc(d.incomplete_reason || 'Data incomplete')}</td>
        <td></td>
      </tr>`;
    }

    return `<tr class="${trCls}" id="tr-${i}">
      <td style="font-weight:900;color:var(--a2)">${esc(d.symbol)}</td>
      <td>${actBdg}</td>
      <td>
        <div class="cb">
          <span style="font-size:12px;font-weight:900;color:${confC}">${conf}%</span>
          <div class="cb-t"><div class="cb-f" style="width:${conf}%;background:${confC}"></div></div>
        </div>
      </td>
      <td>${dots}</td>
      <td style="color:${pcrC};font-weight:800">${day.pcr_eod ? Number(day.pcr_eod).toFixed(3) : '—'}</td>
      <td>${biasTag(pcr.bias)}</td>
      <td>${biasTag(oi.bias)}</td>
      <td>${biasTag(price.bias)}</td>
      <td style="color:${chC}">${chP != null ? (chP >= 0 ? '+' : '') + Number(chP).toFixed(2) + '%' : '—'}</td>
      <td>${orderCell}</td>
      <td style="font-size:9px;color:var(--b)">${nextDay || '—'}</td>
      <td>
        <button onclick="toggleDetail(${i})"
          style="background:rgba(255,107,0,.07);border:1px solid rgba(255,107,0,.18);color:var(--a2);border-radius:5px;padding:2px 8px;font-size:9px;cursor:pointer">
          Details ▼
        </button>
      </td>
    </tr>
    <tr class="detail-row" id="det-${i}">
      <td colspan="12" class="detail-cell">
        ${renderDetail(sig, ind, day, order)}
      </td>
    </tr>`;
  }).join('');

  document.getElementById('tbl-wrap').innerHTML = `
    <div class="tbl-wrap">
      <table class="mt">
        <thead>
          <tr>
            <th>Symbol</th>
            <th>Signal</th>
            <th>Confidence</th>
            <th>Aligned</th>
            <th>PCR EOD</th>
            <th>PCR</th>
            <th>OI</th>
            <th>Price</th>
            <th>Day %</th>
            <th>Order (Strike · Price)</th>
            <th>Trade Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
    <div style="font-size:9px;color:rgba(255,255,255,.18);padding:8px 10px;border-top:1px solid rgba(255,255,255,.04)">
      ${data.length} symbols analysed &nbsp;·&nbsp;
      Valid for ${nextDay || 'next trading day'} &nbsp;·&nbsp;
      Entry: 9:45 after two-candle confirmation &nbsp;·&nbsp;
      T1 +1% · T2 +2% · Hard exit 11:00
    </div>`;
}

/* ── Order card (inline in table row) ─────────────────────────────────── */
function renderOrderCard(order, action) {
  if (!order) return '<span style="font-size:9px;color:rgba(255,255,255,.18)">—</span>';

  const typeColor   = action === 'BUY_CE' ? 'var(--g)' : 'var(--r)';
  const priceColor  = action === 'BUY_CE' ? 'var(--g)' : 'var(--r)';
  const premBdg     = premiumBadge(order.premium_status);
  const convIcon    = order.volume_conviction ? '<span class="oc-conv">⚡ HiVol</span>' : '';
  const vol         = order.volume ? fmtVol(order.volume) : '—';
  const ep          = order.entry_price ? '₹' + Number(order.entry_price).toFixed(2) : '—';

  return `<div class="order-card">
    <div>
      <div class="oc-label">Strike</div>
      <div>
        <span class="oc-strike">${order.strike ? Number(order.strike).toLocaleString('en-IN') : '—'}</span>
        <span class="oc-pos">${esc(order.strike_position || '—')}</span>
      </div>
    </div>
    <div>
      <div class="oc-label">Type</div>
      <div class="oc-type" style="color:${typeColor}">${esc(order.option_type || '—')}</div>
    </div>
    <div>
      <div class="oc-label">Entry Price</div>
      <div class="oc-price" style="color:${priceColor}">${ep}</div>
    </div>
    <div>
      <div class="oc-label">Premium</div>
      <div>${premBdg}</div>
    </div>
    <div>
      <div class="oc-label">Volume</div>
      <div class="oc-vol">${vol} ${convIcon}</div>
    </div>
  </div>`;
}

/* ── Detail expand panel ───────────────────────────────────────────────── */
function renderDetail(sig, ind, day, order) {
  const pcr     = ind.pcr   || {};
  const oi      = ind.oi    || {};
  const price   = ind.price || {};
  const er      = sig.entry_rules || {};
  const reasons = (sig.reasons || []).slice(0, 8);

  // Indicator breakdown
  const indRow = (name, b, str, extra) =>
    `<div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;font-size:9px">
      <span style="width:50px;color:rgba(255,255,255,.3);font-weight:800">${name}</span>
      ${biasTag(b)}
      <span style="color:rgba(255,255,255,.3)">str: ${str || 0}</span>
      <span style="color:rgba(255,255,255,.25);font-size:8px">${esc(extra || '')}</span>
    </div>`;

  // Continuation + gap info
  const contAdj = sig.continuation_adj;
  const gapAdj  = sig.gap_adj;
  const composite = sig.composite;

  const scoreRow = `<div style="margin-top:8px;font-size:9px;display:flex;gap:12px;flex-wrap:wrap">
    <span style="color:rgba(255,255,255,.3)">Composite: <strong style="color:var(--a2)">${composite != null ? composite : '—'}</strong></span>
    <span style="color:rgba(255,255,255,.3)">Continuation: <strong style="${contAdj >= 0 ? 'color:var(--g)' : 'color:var(--r)'}">${contAdj != null ? (contAdj >= 0 ? '+' : '') + contAdj : '—'}</strong></span>
    <span style="color:rgba(255,255,255,.3)">Gap adj: <strong style="${gapAdj < 0 ? 'color:var(--r)' : 'color:rgba(255,255,255,.4)'}">${gapAdj != null ? (gapAdj >= 0 ? '+' : '') + gapAdj + '%' : '—'}</strong></span>
  </div>`;

  // Reasons
  const reasonsHtml = reasons.length
    ? `<div style="margin-top:8px;font-size:9px;color:rgba(255,255,255,.32);line-height:1.7">` +
      reasons.map(r => '› ' + esc(r)).join('<br>') +
      `</div>`
    : '';

  // Entry rules
  const entryHtml = Object.keys(er).length
    ? `<div class="entry-box">
        <div class="et">Next-day entry protocol</div>
        ${Object.values(er).map((v, i) =>
          `<div class="er"><div class="step-n">${i+1}</div><span>${esc(v)}</span></div>`
        ).join('')}
       </div>`
    : '';

  // Candidates table
  const candHtml = order && order.candidates && order.candidates.length
    ? renderCandidates(order)
    : '';

  return `<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
    <div>
      <div style="font-size:8px;font-weight:800;color:rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px">Indicator breakdown</div>
      ${indRow('PCR', pcr.bias, pcr.strength,
          'PCR: ' + (pcr.pcr_eod ? Number(pcr.pcr_eod).toFixed(3) : '—') +
          (pcr.context ? ' · ctx: ' + pcr.context : ''))}
      ${indRow('OI', oi.bias, oi.strength,
          'CE: ' + (oi.ce_bear_pct || 0) + '% bear · PE: ' + (oi.pe_bull_pct || 0) + '% bull')}
      ${indRow('PRICE', price.bias, price.strength,
          (price.day_pct || 0) + '% · LH: ' + (price.lh_pct || 0) + '% · cons: ' + (price.cons_pct || 0) + '%')}
      ${scoreRow}
      ${reasonsHtml}
    </div>
    <div>
      ${entryHtml}
      ${candHtml}
    </div>
  </div>`;
}

/* ── Candidates table (all 3 strikes with premium + volume) ────────────── */
function renderCandidates(order) {
  if (!order.candidates || !order.candidates.length) return '';
  const rows = order.candidates.map(c => {
    const isBest  = c.strike === order.strike;
    const skipped = c.skip_reason;
    const ep      = c.entry_price ? '₹' + Number(c.entry_price).toFixed(2) : '—';
    const vol     = c.volume ? fmtVol(c.volume) : '—';
    const oi      = c.oi ? fmtVol(c.oi) : '—';
    const premBdg = premiumBadge(c.premium_status);
    return `<tr class="${isBest ? 'best-row' : ''} ${skipped ? 'skip-row' : ''}">
      <td>${isBest ? '★ ' : ''}<strong>${c.position || '—'}</strong></td>
      <td>${c.strike ? Number(c.strike).toLocaleString('en-IN') : '—'}</td>
      <td>${ep}</td>
      <td>${premBdg}</td>
      <td>${vol}</td>
      <td>${oi}</td>
      <td style="font-size:8px;color:rgba(255,255,255,.3)">${skipped ? esc(c.skip_reason) : (isBest ? '← selected' : '')}</td>
    </tr>`;
  }).join('');

  return `<div style="margin-top:10px">
    <div style="font-size:8px;font-weight:800;color:rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:.7px;margin-bottom:4px">
      Strike candidates (ATM ± 1)
    </div>
    <table class="cand-tbl">
      <thead><tr>
        <th>Position</th><th>Strike</th><th>Entry ₹</th><th>Premium</th><th>Volume</th><th>OI</th><th>Note</th>
      </tr></thead>
      <tbody>${rows}</tbody>
    </table>
    <div style="font-size:8px;color:rgba(255,255,255,.18);margin-top:4px">
      Entry price = avg(14:45 + 15:00 candle close) · Selected = highest volume in ideal premium range
    </div>
  </div>`;
}

/* ── Toggle detail row ─────────────────────────────────────────────────── */
function toggleDetail(i) {
  const row = document.getElementById('det-' + i);
  row.classList.toggle('open');
  const btn = document.querySelector('#tr-' + i + ' button');
  if (btn) btn.innerHTML = row.classList.contains('open') ? 'Details ▲' : 'Details ▼';
}

/* ── Helpers ───────────────────────────────────────────────────────────── */
function biasTag(b) {
  if (!b || b === 'NEUTRAL') return '<span class="bdg b-neu">—</span>';
  if (b === 'BULLISH')       return '<span class="bdg b-bull">▲ Bull</span>';
  if (b === 'BEARISH')       return '<span class="bdg b-bear">▼ Bear</span>';
  return '<span class="bdg b-neu">' + esc(b) + '</span>';
}

function premiumBadge(status) {
  switch (status) {
    case 'IDEAL':           return '<span class="bdg b-ideal">Ideal</span>';
    case 'SPECULATIVE':     return '<span class="bdg b-spec">Speculative</span>';
    case 'EXPENSIVE':       return '<span class="bdg b-exp">Expensive</span>';
    case 'TOO_EXPENSIVE':   return '<span class="bdg b-junk">Too Exp.</span>';
    case 'JUNK':            return '<span class="bdg b-junk">Junk</span>';
    case 'FORCED_FALLBACK': return '<span class="bdg b-spec">Fallback</span>';
    default:                return '<span class="bdg b-neu">—</span>';
  }
}

function fmtVol(n) {
  if (!n) return '—';
  if (n >= 1e7) return (n / 1e7).toFixed(1) + 'Cr';
  if (n >= 1e5) return (n / 1e5).toFixed(1) + 'L';
  if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
  return String(n);
}

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush