@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap');
:root{
  --g:#00e676;--r:#ff1744;--w:#ffc107;--a:#ff6b00;--a2:#ff9f00;
  --b:#4fc3f7;--card:#07071a;--card2:#0d0d24;--bdr:rgba(255,107,0,.12);
  --font:'Space Grotesk',sans-serif;--mono:'JetBrains Mono',monospace;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font)}

/* Header */
.pg-hdr{background:linear-gradient(135deg,#060614,#12042a 50%,#060614);border:1px solid rgba(255,107,0,.18);border-radius:14px;padding:16px 22px;margin-bottom:14px;position:relative;overflow:hidden}
.pg-hdr::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--a),var(--a2),var(--a),transparent)}
.pg-hdr h4{color:var(--a2);font-size:17px;font-weight:700}
.pg-hdr p{color:rgba(255,255,255,.35);font-size:11px;margin-top:4px}

/* Upload zone */
.upload-zone{border:2px dashed rgba(255,107,0,.25);border-radius:12px;background:rgba(255,107,0,.03);padding:28px 24px;text-align:center;cursor:pointer;transition:all .2s;position:relative;margin-bottom:14px}
.upload-zone:hover,.upload-zone.drag{border-color:var(--a2);background:rgba(255,107,0,.07)}
.upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%}
.uz-title{font-size:14px;font-weight:700;color:var(--a2);margin-bottom:3px}
.uz-sub{font-size:11px;color:rgba(255,255,255,.3)}
.uz-files{margin-top:10px;display:flex;flex-wrap:wrap;gap:7px;justify-content:center}
.uz-file{background:rgba(255,107,0,.1);border:1px solid rgba(255,107,0,.25);border-radius:6px;padding:3px 10px;font-size:10px;font-weight:700;color:var(--a2);display:flex;align-items:center;gap:5px}
.uz-file .rm{cursor:pointer;color:rgba(255,255,255,.3)}
.uz-file .rm:hover{color:var(--r)}

/* Run button */
.btn-run{background:linear-gradient(135deg,var(--a),var(--a2));color:#000;border:none;border-radius:8px;padding:9px 28px;font-size:13px;font-weight:800;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:7px}
.btn-run:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(255,107,0,.3)}
.btn-run:disabled{opacity:.4;cursor:not-allowed;transform:none}

/* Loading */
.lw{display:flex;flex-direction:column;align-items:center;padding:60px;color:var(--a2);font-size:12px;font-weight:600;gap:12px}
.sp{width:26px;height:26px;border:3px solid rgba(255,107,0,.09);border-top-color:var(--a2);border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* Summary bar */
.sum-bar{background:var(--card2);border:1px solid var(--bdr);border-radius:10px;padding:10px 16px;margin-bottom:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.sb-item{flex:1;min-width:70px}
.sb-item .l{font-size:7px;font-weight:700;color:rgba(255,255,255,.22);text-transform:uppercase;letter-spacing:.7px;margin-bottom:2px}
.sb-item .v{font-size:18px;font-weight:700;font-family:var(--mono);line-height:1}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px}
.srch{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:#fff;border-radius:7px;padding:6px 12px;font-size:11px;font-family:var(--font);outline:none;width:220px}
.srch:focus{border-color:rgba(255,107,0,.4);background:rgba(255,107,0,.05)}
.srch::placeholder{color:rgba(255,255,255,.25)}
.fpill{padding:5px 12px;border-radius:20px;font-size:10px;font-weight:700;cursor:pointer;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:rgba(255,255,255,.35);transition:all .15s;font-family:var(--font);white-space:nowrap}
.fpill:hover{background:rgba(255,107,0,.1);color:var(--a2);border-color:rgba(255,107,0,.3)}
.fpill.active{background:rgba(255,107,0,.18);color:var(--a2);border-color:rgba(255,107,0,.5)}
.fpill.active.p-win{background:rgba(0,230,118,.15);color:var(--g);border-color:rgba(0,230,118,.4)}
.fpill.active.p-loss{background:rgba(255,23,68,.12);color:var(--r);border-color:rgba(255,23,68,.35)}
.fpill.active.p-ce{background:rgba(0,230,118,.15);color:var(--g);border-color:rgba(0,230,118,.4)}
.fpill.active.p-pe{background:rgba(255,23,68,.12);color:var(--r);border-color:rgba(255,23,68,.35)}
.row-count{margin-left:auto;font-size:9px;color:rgba(255,255,255,.25);font-family:var(--mono)}

/* Export button */
.btn-exp{background:rgba(79,195,247,.08);border:1px solid rgba(79,195,247,.25);color:var(--b);border-radius:7px;padding:6px 14px;font-size:10px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-exp:hover{background:rgba(79,195,247,.15)}

/* Table wrapper */
.tbl-wrap{background:var(--card);border:1px solid var(--bdr);border-radius:12px;overflow:hidden}
.tbl-scroll{overflow-x:auto}
table.pt{width:100%;border-collapse:collapse;font-size:11px}

/* Header */
table.pt thead th{
  padding:10px 14px;text-align:left;font-size:8px;font-weight:800;
  color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.8px;
  background:#0a0a20;border-bottom:1px solid rgba(255,255,255,.06);
  white-space:nowrap;cursor:pointer;user-select:none;
}
table.pt thead th:hover{color:var(--a2)}
table.pt thead th .sort-icon{margin-left:4px;opacity:.4;font-size:8px}
table.pt thead th.sorted .sort-icon{opacity:1;color:var(--a2)}

/* Rows */
table.pt tbody tr{border-bottom:1px solid rgba(255,255,255,.035);transition:background .1s}
table.pt tbody tr:hover{background:rgba(255,255,255,.025)}
table.pt tbody tr.win-row  td:first-child{border-left:3px solid var(--g)}
table.pt tbody tr.loss-row td:first-child{border-left:3px solid var(--r)}
table.pt tbody tr.nd-row   td:first-child{border-left:3px solid rgba(255,193,7,.4)}
table.pt tbody tr.pend-row td:first-child{border-left:3px solid rgba(255,255,255,.1)}
table.pt tbody td{padding:9px 14px;white-space:nowrap;color:rgba(255,255,255,.75)}

/* Cell types */
.sym-cell{font-family:var(--mono);font-weight:600;color:var(--a2)!important;font-size:10px}
.price-cell{font-family:var(--mono);font-weight:600}
.date-cell{font-size:10px;color:rgba(255,255,255,.5)!important}
.time-cell{font-size:9px;color:rgba(255,255,255,.35)!important;font-family:var(--mono)}
.pnl-pos{color:var(--g)!important;font-family:var(--mono);font-weight:700}
.pnl-neg{color:var(--r)!important;font-family:var(--mono);font-weight:700}
.pnl-null{color:rgba(255,255,255,.2)!important;font-family:var(--mono)}

/* Outcome badge */
.ob{display:inline-block;padding:2px 7px;border-radius:4px;font-size:8px;font-weight:800}
.ob-win {background:rgba(0,230,118,.12);color:var(--g);border:1px solid rgba(0,230,118,.25)}
.ob-loss{background:rgba(255,23,68,.1);color:var(--r);border:1px solid rgba(255,23,68,.2)}
.ob-nd  {background:rgba(255,193,7,.08);color:var(--w);border:1px solid rgba(255,193,7,.2)}
.ob-pend{background:rgba(255,255,255,.04);color:rgba(255,255,255,.3);border:1px solid rgba(255,255,255,.08)}

/* CE/PE badge */
.ot-ce{background:rgba(0,230,118,.08);color:var(--g);border:1px solid rgba(0,230,118,.2);display:inline-block;padding:1px 6px;border-radius:3px;font-size:8px;font-weight:800}
.ot-pe{background:rgba(255,23,68,.08);color:var(--r);border:1px solid rgba(255,23,68,.18);display:inline-block;padding:1px 6px;border-radius:3px;font-size:8px;font-weight:800}

/* Proxy badge */
.proxy-bdg{background:rgba(255,193,7,.08);color:var(--w);border:1px solid rgba(255,193,7,.2);display:inline-block;padding:1px 5px;border-radius:3px;font-size:7px;font-weight:700;margin-left:4px}

.api-bdg{background:rgba(79,195,247,.08);color:var(--b);border:1px solid rgba(79,195,247,.2);display:inline-block;padding:1px 5px;border-radius:3px;font-size:7px;font-weight:700;margin-left:4px}
.no-rows{text-align:center;padding:40px;color:rgba(255,255,255,.2);font-size:12px}
.nd{text-align:center;padding:60px;color:rgba(255,255,255,.2);font-size:12px;line-height:1.8}
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

<div class="pg-hdr">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4>📋 Trade P&L Table</h4>
      <p>BUY entry → next day OHLC exit &nbsp;|&nbsp; Every position as a single row</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('trade-backtest.index') }}"
         style="background:rgba(255,107,0,.07);color:var(--a2);border:1px solid rgba(255,107,0,.2);border-radius:7px;padding:6px 14px;font-size:10px;font-weight:700;text-decoration:none">← Card View</a>
      <a href="{{ route('eod-backtest.index') }}"
         style="background:rgba(0,230,118,.07);color:var(--g);border:1px solid rgba(0,230,118,.2);border-radius:7px;padding:6px 14px;font-size:10px;font-weight:700;text-decoration:none">← EOD Backtest</a>
    </div>
  </div>
</div>

<!-- Upload -->
<div class="upload-zone" id="dropZone">
  <input type="file" id="fileInput" multiple accept=".xlsx,.xls" onchange="onFileChange(event)">
  <div class="uz-title">📊 Drop Zerodha tradebook files here or click to browse</div>
  <div class="uz-sub">Multiple files supported (.xlsx)</div>
  <div class="uz-files" id="fileList"></div>
</div>

<div class="d-flex align-items-center gap-10 mb-3 flex-wrap">
  <button class="btn-run" id="runBtn" onclick="runBacktest()" disabled>▶ Run</button>
  <span style="font-size:10px;color:rgba(255,255,255,.2)" id="statusTxt">Upload files to begin</span>
</div>

<!-- Summary -->
<div id="summaryBar"></div>

<!-- Toolbar -->
<div class="toolbar" id="toolbar" style="display:none">
  <input type="text" class="srch" id="searchBox" placeholder="🔍 Search symbol…" oninput="applyFilters()">

  <span style="font-size:9px;color:rgba(255,255,255,.25);font-weight:700;text-transform:uppercase;letter-spacing:.5px">Option</span>
  <button class="fpill active"  id="fo-ALL" onclick="setFilter('option','ALL')">All</button>
  <button class="fpill p-ce"    id="fo-CE"  onclick="setFilter('option','CE')" >📈 CE</button>
  <button class="fpill p-pe"    id="fo-PE"  onclick="setFilter('option','PE')" >📉 PE</button>

  <span style="font-size:9px;color:rgba(255,255,255,.25);font-weight:700;text-transform:uppercase;letter-spacing:.5px">Outcome</span>
  <button class="fpill active"  id="fout-ALL"     onclick="setFilter('outcome','ALL')"    >All</button>
  <button class="fpill p-win"   id="fout-WIN"     onclick="setFilter('outcome','WIN')"    >✅ WIN</button>
  <button class="fpill p-loss"  id="fout-LOSS"    onclick="setFilter('outcome','LOSS')"   >❌ LOSS</button>
  <button class="fpill"         id="fout-NO_DATA" onclick="setFilter('outcome','NO_DATA')">⚠ No Data</button>

  <button class="btn-exp" onclick="exportCSV()">⬇ Export CSV</button>
  <span class="row-count" id="rowCount"></span>
</div>

<!-- Table -->
<div id="tableWrap"></div>

</div>
</section>
@endsection

@push('script')
<script>
let selectedFiles = [];
let allRows  = [];   // full flat array from server
let sortCol  = 'buy_date';
let sortDir  = 1;    // 1 = asc, -1 = desc
let fOption  = 'ALL';
let fOutcome = 'ALL';

// ── Drag & Drop ──────────────────────────────────────────────────────
const dz = document.getElementById('dropZone');
dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('drag'); });
dz.addEventListener('dragleave', () => dz.classList.remove('drag'));
dz.addEventListener('drop', e => {
  e.preventDefault(); dz.classList.remove('drag');
  addFiles([...e.dataTransfer.files].filter(f => f.name.match(/\.xlsx?$/i)));
});
function onFileChange(e) { addFiles([...e.target.files]); e.target.value = ''; }
function addFiles(files) {
  files.forEach(f => { if (!selectedFiles.find(x => x.name === f.name)) selectedFiles.push(f); });
  renderFileList();
}
function removeFile(name) { selectedFiles = selectedFiles.filter(f => f.name !== name); renderFileList(); }
function renderFileList() {
  document.getElementById('fileList').innerHTML = selectedFiles.map(f =>
    `<div class="uz-file"><span>📄 ${esc(f.name)}</span><span class="rm" onclick="removeFile('${esc(f.name)}')">✕</span></div>`
  ).join('');
  document.getElementById('runBtn').disabled = !selectedFiles.length;
  document.getElementById('statusTxt').textContent = selectedFiles.length ? selectedFiles.length + ' file(s) selected' : 'Upload files to begin';
}

// ── Run ──────────────────────────────────────────────────────────────
function runBacktest() {
  if (!selectedFiles.length) return;
  const btn = document.getElementById('runBtn');
  btn.disabled = true;
  document.getElementById('tableWrap').innerHTML = '<div class="lw"><div class="sp"></div>Processing & checking OHLC data…</div>';
  document.getElementById('summaryBar').innerHTML = '';
  document.getElementById('toolbar').style.display = 'none';
  document.getElementById('statusTxt').textContent = 'Running…';

  const fd = new FormData();
  fd.append('_token', '{{ csrf_token() }}');
  selectedFiles.forEach(f => fd.append('files[]', f));

  fetch('{{ route("trade-backtest.uploadTable") }}', { method:'POST', body:fd })
    .then(r => r.json())
    .then(res => {
      btn.disabled = false;
      document.getElementById('statusTxt').textContent = res.success ? '✓ Done — ' + new Date().toLocaleTimeString() : '⚠ Error';
      if (!res.success) {
        document.getElementById('tableWrap').innerHTML = `<div class="nd">⚠ ${esc(res.message||'Error')}</div>`;
        return;
      }
      allRows = res.rows;
      fOption = fOutcome = 'ALL';
      document.querySelectorAll('.fpill').forEach(p => p.classList.remove('active'));
      ['fo-ALL','fout-ALL'].forEach(id => document.getElementById(id)?.classList.add('active'));
      renderSummary(res.summary);
      document.getElementById('toolbar').style.display = 'flex';
      applyFilters();
    })
    .catch(e => {
      btn.disabled = false;
      document.getElementById('statusTxt').textContent = '⚠ Error';
      document.getElementById('tableWrap').innerHTML = `<div class="nd">⚠ Network error: ${esc(e.message)}</div>`;
    });
}

// ── Summary bar ──────────────────────────────────────────────────────
function renderSummary(s) {
  const pC  = s.total_pnl > 0 ? 'var(--g)' : s.total_pnl < 0 ? 'var(--r)' : 'rgba(255,255,255,.3)';
  const wrC = s.win_rate >= 60 ? 'var(--g)' : s.win_rate >= 40 ? 'var(--w)' : s.win_rate != null ? 'var(--r)' : 'rgba(255,255,255,.3)';
  document.getElementById('summaryBar').innerHTML = `
    <div class="sum-bar">
      <div class="sb-item"><div class="l">Total P&L</div><div class="v" style="color:${pC}">${s.total_pnl!=null?(s.total_pnl>=0?'+':'')+'₹'+fmt0r(s.total_pnl):'—'}</div></div>
      <div class="sb-item"><div class="l">Win Rate</div><div class="v" style="color:${wrC}">${s.win_rate!=null?s.win_rate+'%':'—'}</div></div>
      <div class="sb-item"><div class="l">Wins</div><div class="v" style="color:var(--g)">${s.wins}</div></div>
      <div class="sb-item"><div class="l">Losses</div><div class="v" style="color:var(--r)">${s.losses}</div></div>
      <div class="sb-item"><div class="l">No Data</div><div class="v" style="color:var(--w)">${s.no_data}</div></div>
      <div class="sb-item"><div class="l">Total</div><div class="v" style="color:rgba(255,255,255,.5)">${s.total}</div></div>
    </div>` + (s.api_fetches > 0 ? `<div class="sum-bar" style="margin-top:6px"><div class="sb-item"><div class="l">Live API Fetched</div><div class="v" style="color:var(--b);font-size:14px">${s.api_fetches}</div><div style="font-size:8px;color:rgba(255,255,255,.2)">fallback data</div></div></div>` : '');
}

// ── Filters ──────────────────────────────────────────────────────────
function setFilter(group, val) {
  if (group === 'option')  fOption  = val;
  if (group === 'outcome') fOutcome = val;
  const cfg = {
    option:  { pre:'fo-',   vals:['ALL','CE','PE'] },
    outcome: { pre:'fout-', vals:['ALL','WIN','LOSS','NO_DATA'] },
  };
  const g = cfg[group];
  g.vals.forEach(v => document.getElementById(g.pre+v)?.classList.toggle('active', v===val));
  applyFilters();
}

function applyFilters() {
  const q = (document.getElementById('searchBox')?.value || '').trim().toLowerCase();
  const filtered = allRows.filter(r =>
    (fOption  === 'ALL' || r.option_type === fOption)  &&
    (fOutcome === 'ALL' || r.outcome     === fOutcome) &&
    (!q || r.symbol.toLowerCase().includes(q))
  );
  // Sort
  filtered.sort((a, b) => {
    let av = a[sortCol], bv = b[sortCol];
    if (av == null) av = sortDir === 1 ? Infinity : -Infinity;
    if (bv == null) bv = sortDir === 1 ? Infinity : -Infinity;
    if (typeof av === 'string') return av.localeCompare(bv) * sortDir;
    return (av - bv) * sortDir;
  });
  document.getElementById('rowCount').textContent = filtered.length + ' / ' + allRows.length + ' rows';
  renderTable(filtered);
}

function sortBy(col) {
  if (sortCol === col) sortDir *= -1;
  else { sortCol = col; sortDir = 1; }
  // Update header icons
  document.querySelectorAll('table.pt thead th').forEach(th => {
    th.classList.remove('sorted');
    const ic = th.querySelector('.sort-icon');
    if (ic) ic.textContent = '↕';
  });
  const th = document.querySelector(`th[data-col="${col}"]`);
  if (th) {
    th.classList.add('sorted');
    const ic = th.querySelector('.sort-icon');
    if (ic) ic.textContent = sortDir === 1 ? '↑' : '↓';
  }
  applyFilters();
}

// ── Render table ─────────────────────────────────────────────────────
function renderTable(rows) {
  if (!rows.length) {
    document.getElementById('tableWrap').innerHTML =
      '<div class="tbl-wrap"><div class="no-rows">No trades match current filter</div></div>';
    return;
  }

  const cols = [
    { key:'buy_date',   label:'Buy Date',   cls:'date-cell' },
    { key:'buy_time',   label:'Buy Time',   cls:'time-cell' },
    { key:'symbol',     label:'Symbol',     cls:'sym-cell'  },
    { key:'option_type',label:'Type',       cls:''          },
    { key:'buy_price',  label:'Buy Price',  cls:'price-cell'},
    { key:'qty',        label:'Qty',        cls:'price-cell'},
    { key:'sell_date',  label:'Sell Date',  cls:'date-cell' },
    { key:'sell_time',  label:'Sell Time',  cls:'time-cell' },
    { key:'sell_price', label:'Sell Price', cls:'price-cell'},
    { key:'pnl',        label:'P&L (₹)',    cls:''          },
    { key:'pnl_pct',    label:'Move %',     cls:''          },
    { key:'outcome',    label:'Result',     cls:''          },
  ];

  const thead = cols.map(c =>
    `<th data-col="${c.key}" onclick="sortBy('${c.key}')">${c.label} <span class="sort-icon">${sortCol===c.key?(sortDir===1?'↑':'↓'):'↕'}</span></th>`
  ).join('');

  const tbody = rows.map(r => {
    const cls = r.outcome==='WIN'?'win-row':r.outcome==='LOSS'?'loss-row':r.outcome==='NO_DATA'?'nd-row':'pend-row';

    // Buy date / time — split the "02 Mar 2026, 09:30 AM" format
    const [buyDatePart, buyTimePart] = splitDateTime(r.buy_time);

    // Sell date / time
    const sellDateDisplay = r.sell_date || '—';
    const [, sellTimePart] = splitDateTime(r.sell_time);

    // Sell price
    const sellPriceDisplay = r.sell_price != null ? '₹' + fmt2(r.sell_price) : '—';

    // P&L
    let pnlCell = '<span class="pnl-null">—</span>';
    if (r.pnl != null) {
      const cls2 = r.pnl >= 0 ? 'pnl-pos' : 'pnl-neg';
      pnlCell = `<span class="${cls2}">${r.pnl>=0?'+':''}₹${fmt0r(r.pnl)}</span>`;
    }

    // Move %
    let movCell = '<span class="pnl-null">—</span>';
    if (r.pnl_pct != null) {
      const mc = r.pnl_pct >= 0 ? 'pnl-pos' : 'pnl-neg';
      movCell = `<span class="${mc}">${r.pnl_pct>=0?'+':''}${fmt2(r.pnl_pct)}%</span>`;
    }

    // Outcome badge
    const outBadge = {
      WIN:     `<span class="ob ob-win">✅ WIN</span>`,
      LOSS:    `<span class="ob ob-loss">❌ LOSS</span>`,
      NO_DATA: `<span class="ob ob-nd">⚠ No Data</span>`,
      PENDING: `<span class="ob ob-pend">⏳ Pending</span>`,
    }[r.outcome] || `<span class="ob ob-pend">${esc(r.outcome)}</span>`;

    // CE/PE badge
    const otBadge = r.option_type === 'CE'
      ? `<span class="ot-ce">CE</span>`
      : `<span class="ot-pe">PE</span>`;

    // Proxy / API flag
    const fbFlag = r.ohlc_fallback ? `<span class="proxy-bdg">proxy</span>` : '';
    const apiFlag = r.data_source === 'api' ? `<span class="api-bdg">📡 API</span>` : '';

    return `<tr class="${cls}">
      <td class="date-cell">${esc(r.buy_date || '—')}</td>
      <td class="time-cell">${esc(buyTimePart)}</td>
      <td class="sym-cell">${esc(r.symbol)}${fbFlag}${apiFlag}</td>
      <td>${otBadge}</td>
      <td class="price-cell">₹${fmt2(r.buy_price)}</td>
      <td class="price-cell" style="color:rgba(255,255,255,.4)">${fmt0(r.qty)}</td>
      <td class="date-cell">${esc(sellDateDisplay)}</td>
      <td class="time-cell">${esc(sellTimePart)}</td>
      <td class="price-cell">${sellPriceDisplay}</td>
      <td>${pnlCell}</td>
      <td>${movCell}</td>
      <td>${outBadge}</td>
    </tr>`;
  }).join('');

  document.getElementById('tableWrap').innerHTML = `
    <div class="tbl-wrap">
      <div class="tbl-scroll">
        <table class="pt">
          <thead><tr>${thead}</tr></thead>
          <tbody>${tbody}</tbody>
        </table>
      </div>
    </div>`;
}

// ── Export CSV ───────────────────────────────────────────────────────
function exportCSV() {
  const q = (document.getElementById('searchBox')?.value || '').trim().toLowerCase();
  const rows = allRows.filter(r =>
    (fOption  === 'ALL' || r.option_type === fOption)  &&
    (fOutcome === 'ALL' || r.outcome     === fOutcome) &&
    (!q || r.symbol.toLowerCase().includes(q))
  );
  if (!rows.length) return;

  const headers = ['Account','Buy Date','Buy Time','Symbol','Option Type','Buy Price','Qty','Sell Date','Sell Time','Sell Price','P&L (₹)','Move %','Outcome'];
  const csvRows = rows.map(r => {
    const [, bt] = splitDateTime(r.buy_time);
    const [, st] = splitDateTime(r.sell_time);
    return [
      r.account, r.buy_date, bt, r.symbol, r.option_type,
      r.buy_price, r.qty, r.sell_date||'', st,
      r.sell_price!=null?r.sell_price:'',
      r.pnl!=null?r.pnl:'',
      r.pnl_pct!=null?r.pnl_pct+'%':'',
      r.outcome
    ].map(v => `"${String(v||'').replace(/"/g,'""')}"`).join(',');
  });

  const blob = new Blob([headers.join(',') + '\n' + csvRows.join('\n')], { type:'text/csv' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = 'trade_pnl_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
}

// ── Helpers ──────────────────────────────────────────────────────────
function splitDateTime(val) {
  if (!val) return ['—', '—'];
  // "02 Mar 2026, 09:30 AM"  or  "2026-03-02"  or  "03 Mar 2026, 11:30"
  const m = String(val).match(/^(.*?),\s*(.+)$/);
  if (m) return [m[1].trim(), m[2].trim()];
  return [String(val), '—'];
}
function fmt0(n)  { return n!=null ? Number(n).toLocaleString('en-IN') : '—'; }
function fmt0r(n) { return n!=null ? Math.abs(Number(n)).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) : '—'; }
function fmt2(n)  { return n!=null ? Number(n).toFixed(2) : '—'; }
function esc(s)   { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
@endpush