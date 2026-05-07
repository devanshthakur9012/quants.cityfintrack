@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
:root {
    --ink:      #07090e;
    --surface:  #0d1018;
    --raised:   #131720;
    --hover:    #191f2d;
    --rim:      rgba(255,255,255,0.06);
    --rim2:     rgba(255,255,255,0.10);
    --call:     #00ff88;
    --call-dim: rgba(0,255,136,0.10);
    --call-bdr: rgba(0,255,136,0.28);
    --put:      #ff4060;
    --put-dim:  rgba(255,64,96,0.10);
    --put-bdr:  rgba(255,64,96,0.28);
    --wait:     #f0b429;
    --wait-dim: rgba(240,180,41,0.08);
    --wait-bdr: rgba(240,180,41,0.28);
    --trap:     #c084fc;
    --trap-dim: rgba(192,132,252,0.10);
    --trap-bdr: rgba(192,132,252,0.28);
    --dim1:     rgba(255,255,255,0.85);
    --dim2:     rgba(255,255,255,0.55);
    --dim3:     rgba(255,255,255,0.28);
    --dim4:     rgba(255,255,255,0.08);
    --r:        10px;
    --rs:       6px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--ink); }
.scan-wrap { max-width: 100%; margin: 0 auto; padding: 24px 16px 80px; }

/* ── TOP BAR ── */
.top-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
.top-logo { font-size: 11px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; color: var(--dim3); }
.top-logo span { color: var(--wait); }
.top-sep { flex: 1; height: 1px; background: var(--rim); }
.top-pill {
    font-size: 9px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
    padding: 3px 10px; border-radius: 20px;
    background: var(--raised); border: 1px solid var(--rim2); color: var(--dim3);
}
.top-pill.live { color: var(--call); border-color: var(--call-bdr); background: var(--call-dim); }

/* ── FILTER BAR ── */
.filter-bar {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    background: var(--surface); border: 1px solid var(--rim);
    border-radius: var(--r); padding: 10px 16px; margin-bottom: 16px;
}
.fl { font-size: 9px; font-weight: 700; color: var(--dim3); text-transform: uppercase; letter-spacing: 0.8px; white-space: nowrap; }
.fdiv { width: 1px; height: 18px; background: var(--rim); flex-shrink: 0; }
.date-wrap { display: flex; align-items: center; gap: 5px; }
.date-wrap input[type="date"] {
    font-size: 10px; font-weight: 600;
    background: var(--raised); border: 1px solid var(--rim);
    border-radius: var(--rs); color: var(--dim1); padding: 5px 10px; outline: none; cursor: pointer;
}
.date-wrap input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.6); cursor: pointer; }
.fnav {
    width: 28px; height: 28px; background: var(--raised); border: 1px solid var(--rim);
    border-radius: var(--rs); color: var(--dim2); font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.12s;
}
.fnav:hover { background: var(--wait-dim); border-color: var(--wait-bdr); color: var(--wait); }
.btn-scan {
    font-size: 10px; font-weight: 800;
    background: var(--wait); color: var(--ink); border: none;
    border-radius: var(--rs); padding: 6px 20px; cursor: pointer; transition: 0.15s;
}
.btn-scan:hover { opacity: 0.85; }
.btn-auto {
    font-size: 9px; font-weight: 700;
    background: var(--raised); border: 1px solid var(--rim);
    color: var(--dim2); border-radius: var(--rs); padding: 5px 12px; cursor: pointer;
}
.btn-auto.on { border-color: var(--call-bdr); color: var(--call); }
.f-upd { font-size: 9px; color: var(--dim3); margin-left: auto; }

/* ── SUMMARY BOXES ── */
.summary-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.sbox {
    background: var(--surface); border: 1px solid var(--rim);
    border-radius: var(--r); padding: 11px 18px; text-align: center; flex: 1; min-width: 100px;
}
.sbox-label { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--dim3); margin-bottom: 4px; }
.sbox-val { font-size: 20px; font-weight: 900; }
.sbox-val.call { color: var(--call); }
.sbox-val.put  { color: var(--put); }
.sbox-val.wait { color: var(--wait); }
.sbox-val.trap { color: var(--trap); }
.sbox-val.dim  { color: var(--dim2); }
.sbox-sub { font-size: 9px; color: var(--dim3); margin-top: 2px; }

/* ── TABLE CARD ── */
.table-card { background: var(--surface); border: 1px solid var(--rim); border-radius: var(--r); overflow: hidden; }
.table-card-hdr {
    padding: 12px 18px; background: var(--raised); border-bottom: 1px solid var(--rim);
    display: flex; align-items: center; justify-content: space-between;
}
.table-card-hdr-title { font-size: 11px; font-weight: 800; color: var(--dim1); }
.table-card-hdr-sub   { font-size: 9px; color: var(--dim3); }

/* ── FILTER TABS ── */
.filter-tabs-wrap {
    padding: 10px 16px; border-bottom: 1px solid var(--rim);
    background: var(--raised); display: flex; gap: 14px; flex-wrap: wrap; align-items: center;
}
.filter-group { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.filter-group-label { font-size: 8px; font-weight: 700; color: var(--dim3); text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
.ftab {
    font-size: 9px; font-weight: 700; padding: 4px 11px; border-radius: 20px;
    border: 1px solid var(--rim2); background: transparent; color: var(--dim3); cursor: pointer; transition: 0.12s;
}
.ftab:hover { color: var(--dim1); border-color: var(--rim2); background: var(--dim4); }
.ftab.on      { background: var(--wait-dim); border-color: var(--wait-bdr); color: var(--wait); }
.ftab.on-call { background: var(--call-dim); border-color: var(--call-bdr); color: var(--call); }
.ftab.on-put  { background: var(--put-dim);  border-color: var(--put-bdr);  color: var(--put); }
.ftab.on-trap { background: var(--trap-dim); border-color: var(--trap-bdr); color: var(--trap); }

/* ── TABLE ── */
.scan-table-wrap { overflow-x: auto; }
.scan-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.scan-table thead tr.thead-main th {
    padding: 8px 10px; text-align: center;
    font-size: 8px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;
    color: var(--dim3); border-bottom: 1px solid var(--rim);
    background: rgba(0,0,0,0.3); white-space: nowrap;
}
.scan-table thead tr.thead-main th.th-left { text-align: left; }
/* Section group headers */
.scan-table thead tr.thead-group th {
    padding: 4px 10px; text-align: center;
    font-size: 8px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;
    border-bottom: 1px solid var(--rim);
}
.scan-table thead tr.thead-group th.grp-info   { background: rgba(255,255,255,0.02); color: var(--dim3); }
.scan-table thead tr.thead-group th.grp-signals { background: rgba(240,180,41,0.05); color: var(--wait); border-bottom-color: var(--wait-bdr); }
.scan-table thead tr.thead-group th.grp-trade   { background: rgba(0,255,136,0.04); color: var(--call); border-bottom-color: var(--call-bdr); }

.scan-table tbody tr { border-bottom: 1px solid rgba(255,255,255,0.025); transition: background 0.1s; }
.scan-table tbody tr:hover { background: var(--hover); }
.scan-table tbody td { padding: 9px 10px; vertical-align: middle; white-space: nowrap; text-align: center; }
.scan-table tbody td.td-left { text-align: left; }

/* Row colours */
.scan-table tbody tr.row-call { border-left: 2px solid var(--call); }
.scan-table tbody tr.row-put  { border-left: 2px solid var(--put); }
.scan-table tbody tr.row-none { border-left: 2px solid transparent; opacity: 0.55; }
.scan-table tbody tr.row-nodata { border-left: 2px solid transparent; opacity: 0.25; }

/* ── CELL TYPES ── */
.td-symbol  { font-weight: 800; font-size: 12px; color: var(--dim1); }
.expiry-dot { display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: var(--put); margin-left: 4px; vertical-align: middle; }

.sig-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 9px; font-weight: 800; padding: 3px 9px; border-radius: 20px; letter-spacing: 0.3px;
}
.sig-badge.call   { background: var(--call-dim); border: 1px solid var(--call-bdr); color: var(--call); }
.sig-badge.put    { background: var(--put-dim);  border: 1px solid var(--put-bdr);  color: var(--put); }
.sig-badge.wait   { background: var(--wait-dim); border: 1px solid var(--wait-bdr); color: var(--wait); opacity: 0.6; }
.sig-badge.nodata { background: var(--dim4);     border: 1px solid var(--rim);      color: var(--dim3); }

.td-time { font-size: 12px; font-weight: 700; color: var(--dim1); }
.td-dim  { color: var(--dim3); font-size: 10px; }

/* Signal cells — the 7 individual columns */
.sc { display: flex; flex-direction: column; align-items: center; gap: 2px; min-width: 56px; }
.sc-icon { font-size: 13px; line-height: 1; }
.sc-val  { font-size: 9px; font-weight: 600; color: var(--dim2); line-height: 1; }
.sc-pts  { font-size: 8px; font-weight: 800; line-height: 1; }
/* active signal */
.sc.active .sc-icon { filter: none; }
.sc.inactive .sc-icon { opacity: 0.18; }
.sc.inactive .sc-val  { color: var(--dim3); }
.sc.inactive .sc-pts  { color: var(--dim3); }
/* colour by type */
.sc.s-call .sc-pts { color: var(--call); }
.sc.s-put  .sc-pts { color: var(--put); }
.sc.s-trap .sc-pts { color: var(--trap); }
.sc.s-fut  .sc-pts { color: var(--wait); }
.sc.s-call.active .sc-val { color: var(--call); }
.sc.s-put.active  .sc-val { color: var(--put); }
.sc.s-trap.active .sc-val { color: var(--trap); }
.sc.s-fut.active  .sc-val { color: var(--wait); }

/* trade columns */
.td-strike { font-weight: 700; font-size: 11px; }
.td-strike.call { color: var(--call); }
.td-strike.put  { color: var(--put); }
.td-price { font-size: 11px; font-weight: 700; color: var(--dim1); }
.td-tgt   { color: var(--call); font-weight: 700; font-size: 11px; }
.td-sl    { color: var(--put);  font-weight: 700; font-size: 11px; }
.score-cell { display: flex; align-items: center; gap: 5px; justify-content: center; }
.score-num  { font-size: 11px; font-weight: 800; min-width: 18px; }
.score-num.call { color: var(--call); }
.score-num.put  { color: var(--put); }
.score-num.dim  { color: var(--dim3); }
.score-track { width: 50px; height: 3px; background: var(--dim4); border-radius: 2px; overflow: hidden; }
.score-fill { height: 100%; border-radius: 2px; }
.score-fill.call { background: var(--call); }
.score-fill.put  { background: var(--put); }
.score-fill.dim  { background: var(--dim3); }

/* Loading/empty */
.spin { width: 26px; height: 26px; border: 2px solid var(--rim); border-top: 2px solid var(--wait); border-radius: 50%; animation: sp 0.9s linear infinite; }
@keyframes sp { to { transform: rotate(360deg); } }
.loading-s { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px; gap: 12px; color: var(--dim3); font-size: 11px; }
.empty-s   { text-align: center; padding: 60px; color: var(--dim3); font-size: 12px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="scan-wrap">

    {{-- TOP BAR --}}
    <div class="top-bar">
        <span class="top-logo">📡 Multi-Symbol <span>Scanner</span></span>
        <span class="top-sep"></span>
        <span class="top-pill">All Symbols · One View</span>
        <span class="top-pill">Signal Breakdown</span>
        <span id="badge-live" class="top-pill" style="display:none;"></span>
    </div>

    {{-- FILTER BAR --}}
    <div class="filter-bar">
        <span class="fl">Date</span>
        <div class="date-wrap">
            <button class="fnav" onclick="shiftDate(-1)">‹</button>
            <input type="date" id="dp" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="load()">
            <button class="fnav" onclick="shiftDate(1)">›</button>
            <button class="fnav" style="width:auto;padding:0 9px;font-size:8px;font-weight:800;" onclick="goToday()">TODAY</button>
        </div>
        <div class="fdiv"></div>
        <button class="btn-scan" onclick="load()">↺ Scan All</button>
        <button class="btn-auto" id="btnAuto" onclick="toggleAuto()">▶ Auto 60s</button>
        <span class="f-upd" id="lastUpd"></span>
    </div>

    {{-- SUMMARY --}}
    <div id="summaryRow"></div>

    {{-- TABLE --}}
    <div id="tableArea">
        <div class="loading-s"><div class="spin"></div><span>Initialising scanner…</span></div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
const TODAY = '{{ now()->toDateString() }}';
let autoTimer = null, allResults = [];
// Active filters
let fSignal = 'ALL'; // ALL | CALL | PUT | TRADE | NOTRADE
let fSig    = 'ALL'; // ALL | premEx | oiBuild | volSpike | futDir | gamma | accel | mmTrap

$(document).ready(() => load());

function shiftDate(d) {
    const dp = document.getElementById('dp');
    const dt = new Date(dp.value); dt.setDate(dt.getDate() + d);
    const s  = dt.toISOString().split('T')[0];
    if (s > TODAY) return;
    dp.value = s; load();
}
function goToday() { document.getElementById('dp').value = TODAY; load(); }
function toggleAuto() {
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        const b = document.getElementById('btnAuto');
        b.innerHTML = '▶ Auto 60s'; b.classList.remove('on');
    } else {
        autoTimer = setInterval(load, 60000);
        const b = document.getElementById('btnAuto');
        b.innerHTML = '⬛ Stop'; b.classList.add('on');
        load();
    }
}

function load() {
    const date = document.getElementById('dp').value;
    if (date !== TODAY && autoTimer) toggleAuto();
    $('#tableArea').html('<div class="loading-s"><div class="spin"></div><span>Scanning all symbols for ' + date + '…</span></div>');
    $('#summaryRow').html('');
    $.ajax({
        url : '{{ route("expiry-oi.scan.data") }}',
        data: { date },
        success(res) {
            updateBadge(res);
            if (!res.success) {
                $('#tableArea').html('<div class="empty-s">⚠ ' + (res.message || 'Error') + '</div>');
                return;
            }
            allResults = res.results || [];
            fSignal = 'ALL'; fSig = 'ALL';
            renderSummary(res);
            renderTable();
            document.getElementById('lastUpd').textContent = 'Scanned at ' + res.scanned_at;
        },
        error(xhr) {
            $('#tableArea').html('<div class="empty-s">⚠ ' + ((xhr.responseJSON||{}).message || 'Server error') + '</div>');
        }
    });
}

function updateBadge(res) {
    const el = document.getElementById('badge-live');
    el.style.display = 'inline-block';
    if (res.is_today) { el.textContent = '● LIVE'; el.className = 'top-pill live'; }
    else              { el.textContent = '📅 Historical'; el.className = 'top-pill'; }
}

function renderSummary(res) {
    const R = res.results || [];
    const calls   = R.filter(r => r.signal === 'BUY_CALL').length;
    const puts    = R.filter(r => r.signal === 'BUY_PUT').length;
    const noTrade = R.filter(r => r.signal === 'NO TRADE').length;
    const noData  = R.filter(r => r.signal === 'NO DATA' || r.signal === 'ERROR').length;
    const mmTraps = R.filter(r => sigActive(r, 'mmTrap')).length;
    const gamma   = R.filter(r => sigActive(r, 'gamma')).length;
    $('#summaryRow').html(`<div class="summary-row">
        <div class="sbox"><div class="sbox-label">Total</div><div class="sbox-val dim">${res.total_symbols}</div><div class="sbox-sub">symbols</div></div>
        <div class="sbox"><div class="sbox-label">BUY CALL</div><div class="sbox-val call">${calls}</div><div class="sbox-sub">fired</div></div>
        <div class="sbox"><div class="sbox-label">BUY PUT</div><div class="sbox-val put">${puts}</div><div class="sbox-sub">fired</div></div>
        <div class="sbox"><div class="sbox-label">No Trade</div><div class="sbox-val wait">${noTrade}</div><div class="sbox-sub">below threshold</div></div>
        <div class="sbox"><div class="sbox-label">MM Traps</div><div class="sbox-val trap">${mmTraps}</div><div class="sbox-sub">detected</div></div>
        <div class="sbox"><div class="sbox-label">Gamma</div><div class="sbox-val call">${gamma}</div><div class="sbox-sub">squeezes</div></div>
        <div class="sbox"><div class="sbox-label">No Data</div><div class="sbox-val dim">${noData}</div><div class="sbox-sub">missing</div></div>
    </div>`);
}

// Check if a particular signal is active for a result row
// For directional signals (fuDir), isCall determines which side
function sigActive(r, key) {
    const s = r.signals;
    if (!s) return false;
    if (key === 'premEx')   return !!(s.cePremEx?.triggered || s.pePremEx?.triggered);
    if (key === 'oiBuild')  return !!(s.ceOiBuild?.triggered || s.peOiBuild?.triggered);
    if (key === 'volSpike') return !!(s.ceVolSpike?.triggered || s.peVolSpike?.triggered);
    if (key === 'futDir')   return !!(s.futuresDir?.bullish || s.futuresDir?.bearish);
    if (key === 'gamma')    return !!(s.gamma?.active);
    if (key === 'accel')    return !!(s.ceAccel?.triggered || s.peAccel?.triggered);
    if (key === 'mmTrap')   return !!(s.mmTrap?.call_trap || s.mmTrap?.put_trap);
    return false;
}

function setFSignal(f, el) {
    fSignal = f;
    document.querySelectorAll('.ftab-signal').forEach(b => b.className = 'ftab ftab-signal');
    if (el) el.className = 'ftab ftab-signal ' + (f === 'CALL' ? 'on-call' : f === 'PUT' ? 'on-put' : 'on');
    renderTable();
}
function setFSig(f, el) {
    fSig = (fSig === f) ? 'ALL' : f; // toggle off if same
    document.querySelectorAll('.ftab-sig').forEach(b => b.className = 'ftab ftab-sig');
    if (fSig !== 'ALL' && el) el.className = 'ftab ftab-sig ' + (f === 'mmTrap' ? 'on-trap' : f === 'futDir' ? 'on' : 'on');
    renderTable();
}

function getFilteredRows() {
    let rows = allResults;
    // Signal filter
    if      (fSignal === 'CALL')     rows = rows.filter(r => r.signal === 'BUY_CALL');
    else if (fSignal === 'PUT')      rows = rows.filter(r => r.signal === 'BUY_PUT');
    else if (fSignal === 'TRADE')    rows = rows.filter(r => r.signal === 'BUY_CALL' || r.signal === 'BUY_PUT');
    else if (fSignal === 'NOTRADE')  rows = rows.filter(r => r.signal === 'NO TRADE');
    // Individual signal filter
    if (fSig !== 'ALL') rows = rows.filter(r => sigActive(r, fSig));
    return rows;
}

function renderTable() {
    const rows = getFilteredRows();

    const tabsHtml = `<div class="filter-tabs-wrap">
        <div class="filter-group">
            <span class="filter-group-label">Trade:</span>
            ${[
                {k:'ALL',label:'All'},
                {k:'TRADE',label:'🔥 Trades'},
                {k:'CALL',label:'📈 Call'},
                {k:'PUT',label:'📉 Put'},
                {k:'NOTRADE',label:'No Trade'},
            ].map(f => {
                let cls = 'ftab ftab-signal';
                if (f.k === fSignal) cls += f.k==='CALL'?' on-call':f.k==='PUT'?' on-put':' on';
                return `<button class="${cls}" onclick="setFSignal('${f.k}',this)">${f.label}</button>`;
            }).join('')}
        </div>
        <div class="fdiv" style="height:18px;width:1px;background:var(--rim);"></div>
        <div class="filter-group">
            <span class="filter-group-label">Signal:</span>
            ${[
                {k:'premEx',  label:'Prem Exp'},
                {k:'oiBuild', label:'OI Build'},
                {k:'volSpike',label:'Vol Spike'},
                {k:'futDir',  label:'Futures Dir'},
                {k:'gamma',   label:'Gamma'},
                {k:'accel',   label:'Momentum'},
                {k:'mmTrap',  label:'🪤 MM Trap'},
            ].map(f => {
                let cls = 'ftab ftab-sig';
                if (f.k === fSig) cls += f.k==='mmTrap'?' on-trap':' on';
                return `<button class="${cls}" onclick="setFSig('${f.k}',this)">${f.label}</button>`;
            }).join('')}
        </div>
    </div>`;

    if (!rows.length) {
        $('#tableArea').html(`<div class="table-card">
            <div class="table-card-hdr"><span class="table-card-hdr-title">📊 Signal Breakdown</span><span class="table-card-hdr-sub">0 symbols</span></div>
            ${tabsHtml}
            <div class="empty-s">No symbols match this filter.</div>
        </div>`);
        return;
    }

    const trs = rows.map(r => buildRow(r)).join('');

    $('#tableArea').html(`<div class="table-card">
        <div class="table-card-hdr">
            <span class="table-card-hdr-title">📊 Signal Breakdown</span>
            <span class="table-card-hdr-sub">${rows.length} symbols shown</span>
        </div>
        ${tabsHtml}
        <div class="scan-table-wrap">
            <table class="scan-table">
                <thead>
                    <tr class="thead-group">
                        <th colspan="3" class="grp-info">Info</th>
                        <th colspan="7" class="grp-signals">── Individual Signals ─────────────────────────────────────</th>
                        <th colspan="5" class="grp-trade">── Trade Details ──────────</th>
                    </tr>
                    <tr class="thead-main">
                        <th class="th-left">Symbol</th>
                        <th>Signal</th>
                        <th>Time</th>
                        <th>Prem Exp</th>
                        <th>OI Build</th>
                        <th>Vol Spike</th>
                        <th>Futures Dir</th>
                        <th>Gamma</th>
                        <th>Momentum</th>
                        <th>🪤 MM Trap</th>
                        <th>Strike</th>
                        <th>Entry ₹</th>
                        <th>Target ₹</th>
                        <th>SL ₹</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>${trs}</tbody>
            </table>
        </div>
    </div>`);
}

function buildRow(r) {
    const isCall   = r.signal === 'BUY_CALL';
    const isPut    = r.signal === 'BUY_PUT';
    const isFired  = isCall || isPut;
    const isNoData = r.signal === 'NO DATA' || r.signal === 'ERROR';
    const rowCls   = isFired ? (isCall ? 'row-call' : 'row-put') : (isNoData ? 'row-nodata' : 'row-none');
    const cls      = isCall ? 'call' : (isPut ? 'put' : 'dim');
    const s        = r.signals || {};

    // Signal badge
    let badge = '';
    if      (isCall)   badge = `<span class="sig-badge call">📈 BUY CALL</span>`;
    else if (isPut)    badge = `<span class="sig-badge put">📉 BUY PUT</span>`;
    else if (isNoData) badge = `<span class="sig-badge nodata">— NO DATA</span>`;
    else               badge = `<span class="sig-badge wait">⏳ WAIT</span>`;

    // Expiry dot
    const expDot = r.is_expiry ? `<span class="expiry-dot" title="Expiry Day"></span>` : '';

    // Time
    const timeHtml = isFired ? `<span class="td-time">${esc(r.entry_time||'—')}</span>` : `<span class="td-dim">—</span>`;

    // ── 7 individual signal cells ──────────────────────────────────────────

    // 1. Premium Expansion — use whichever side is active
    const premCe  = s.cePremEx?.triggered;
    const premPe  = s.pePremEx?.triggered;
    const premAct = premCe || premPe;
    const premVal = premCe ? (s.cePremEx?.val != null ? s.cePremEx.val + '%' : '—')
                           : (premPe && s.pePremEx?.val != null ? s.pePremEx.val + '%' : '—');
    const premPts = premAct ? `+${premCe ? 3 : 3}` : '0';
    const premCol = premCe ? 's-call' : 's-put';
    const premCell = sigCell(premAct, '📊', premVal, premPts, premAct ? premCol : '');

    // 2. OI Build
    const oiCe  = s.ceOiBuild?.triggered;
    const oiPe  = s.peOiBuild?.triggered;
    const oiAct = oiCe || oiPe;
    const oiVal = oiCe ? (s.ceOiBuild?.val != null ? s.ceOiBuild.val + '%' : '—')
                       : (oiPe && s.peOiBuild?.val != null ? s.peOiBuild.val + '%' : '—');
    const oiCell = sigCell(oiAct, '📦', oiVal, oiAct ? '+2' : '0', oiAct ? (oiCe ? 's-call' : 's-put') : '');

    // 3. Volume Spike
    const volCe  = s.ceVolSpike?.triggered;
    const volPe  = s.peVolSpike?.triggered;
    const volAct = volCe || volPe;
    const volVal = volCe ? (s.ceVolSpike?.val != null ? s.ceVolSpike.val + 'x' : '—')
                         : (volPe && s.peVolSpike?.val != null ? s.peVolSpike.val + 'x' : '—');
    const volCell = sigCell(volAct, '📈', volVal, volAct ? '+2' : '0', volAct ? (volCe ? 's-call' : 's-put') : '');

    // 4. Futures Direction
    const futDir = s.futuresDir?.direction || '—';
    const futAct = s.futuresDir?.bullish || s.futuresDir?.bearish;
    const futVal = futDir;
    const futPts = futAct ? '+2' : '0';
    const futCol = s.futuresDir?.bullish ? 's-call' : (s.futuresDir?.bearish ? 's-put' : 's-fut');
    const futIcon = s.futuresDir?.bullish ? '▲' : (s.futuresDir?.bearish ? '▼' : '→');
    const futCell = sigCell(futAct, futIcon, futVal, futPts, futAct ? futCol : 's-fut');

    // 5. Gamma
    const gamAct = s.gamma?.active;
    const gamVal = s.gamma?.ce ? 'CE Squeeze' : (s.gamma?.pe ? 'PE Squeeze' : '—');
    const gamCell = sigCell(gamAct, '⚡', gamVal, gamAct ? '+2' : '0', gamAct ? (s.gamma?.ce ? 's-call' : 's-put') : '');

    // 6. Momentum Accel
    const accCe  = s.ceAccel?.triggered;
    const accPe  = s.peAccel?.triggered;
    const accAct = accCe || accPe;
    const accCell = sigCell(accAct, '🚀', accAct ? '2 rising' : '—', accAct ? '+2' : '0', accAct ? (accCe ? 's-call' : 's-put') : '');

    // 7. MM Trap
    const trapAct  = s.mmTrap?.call_trap || s.mmTrap?.put_trap;
    const trapType = s.mmTrap?.call_trap ? 'Call Trap' : (s.mmTrap?.put_trap ? 'Put Trap' : '—');
    const trapCell = sigCell(trapAct, '🪤', trapType, trapAct ? '+4' : '0', trapAct ? 's-trap' : '');

    // Trade columns
    const strikeHtml = isFired && r.strike
        ? `<span class="td-strike ${cls}">${fNum(r.strike)}</span><br><span style="font-size:8px;color:var(--dim3);">${esc(r.strike_sym||'')}</span>`
        : `<span class="td-dim">—</span>`;
    const entryHtml  = isFired && r.entry_price ? `<span class="td-price">₹${r.entry_price}</span>` : `<span class="td-dim">—</span>`;
    const tgtHtml    = isFired && r.target      ? `<span class="td-tgt">₹${r.target}</span>`        : `<span class="td-dim">—</span>`;
    const slHtml     = isFired && r.stoploss    ? `<span class="td-sl">₹${r.stoploss}</span>`       : `<span class="td-dim">—</span>`;

    // Score
    const scoreVal = isFired ? r.score : (r.peak_score || 0);
    const scorePct = Math.round((scoreVal / 17) * 100);
    const scoreHtml = `<div class="score-cell">
        <span class="score-num ${isFired ? cls : 'dim'}">${scoreVal}</span>
        <div class="score-track"><div class="score-fill ${isFired ? cls : 'dim'}" style="width:${scorePct}%;"></div></div>
        <span style="font-size:8px;color:var(--dim3);">/17</span>
    </div>`;

    return `<tr class="${rowCls}">
        <td class="td-left"><span class="td-symbol">${esc(r.symbol)}</span>${expDot}</td>
        <td>${badge}</td>
        <td>${timeHtml}</td>
        <td>${premCell}</td>
        <td>${oiCell}</td>
        <td>${volCell}</td>
        <td>${futCell}</td>
        <td>${gamCell}</td>
        <td>${accCell}</td>
        <td>${trapCell}</td>
        <td>${strikeHtml}</td>
        <td>${entryHtml}</td>
        <td>${tgtHtml}</td>
        <td>${slHtml}</td>
        <td>${scoreHtml}</td>
    </tr>`;
}

/**
 * Build a signal cell:
 * active=bool, icon=emoji/char, val=string, pts=string, colorCls=string
 */
function sigCell(active, icon, val, pts, colorCls) {
    const actCls = active ? 'active' : 'inactive';
    return `<div class="sc ${actCls} ${colorCls || ''}">
        <span class="sc-icon">${icon}</span>
        <span class="sc-val">${esc(String(val||'—'))}</span>
        <span class="sc-pts">${esc(String(pts||'0'))}</span>
    </div>`;
}

// Formatters
function fNum(v) { return v != null ? Number(v).toLocaleString('en-IN') : '—'; }
function esc(s)  { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
@endpush