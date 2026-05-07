@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════
   CORE TABLE
═══════════════════════════════════════════════════ */
.pivot-table thead th,
.pivot-table tbody td {
    padding: 5px 5px !important;
    font-size: 10px !important;
    vertical-align: middle;
    text-align: center;
    white-space: nowrap;
    border-color: rgba(255,255,255,0.06) !important;
    color: rgba(255,255,255,0.82);
    background: transparent;
}
.pivot-table thead th {
    color: rgba(255,255,255,0.5);
    font-size: 9px !important;
    text-transform: uppercase;
    letter-spacing: .3px;
    background: #0b1825 !important;
    position: sticky;
    top: 0;
    z-index: 10;
}
.pivot-table thead tr:first-child th { border-bottom: none !important; }
.pivot-table thead tr:last-child  th { border-top: none !important; }

.sub-ce td { background: rgba(40,167,69,0.055) !important; }
.sub-pe td { background: rgba(220,53,69,0.055)  !important; }
.sub-ce td.type-cell { border-left: 3px solid #28a745 !important; }
.sub-pe td.type-cell { border-left: 3px solid #dc3545 !important; }

.std-start { border-left: 2px solid rgba(0,210,255,0.45) !important; }
.cam-start { border-left: 2px solid rgba(168,85,247,0.5) !important; }

.lv-r4 { color:#00ff88; font-weight:800; }
.lv-r3 { color:#28a745; font-weight:700; }
.lv-r2 { color:#5cb85c; font-weight:700; }
.lv-r1 { color:#80c780; font-weight:700; }
.lv-tc { color:#17a2b8; font-weight:700; font-style:italic; }
.lv-p  { color:#00d2ff; font-weight:800; font-size:11px; }
.lv-bc { color:#8899aa; font-weight:700; font-style:italic; }
.lv-s1 { color:#fd7e14; font-weight:700; }
.lv-s2 { color:#dc3545; font-weight:700; }
.lv-s3 { color:#bd2130; font-weight:700; }
.lv-s4 { color:#8b0000; font-weight:800; }

.load-overlay {
    position:absolute; inset:0;
    background:rgba(8,14,26,.94);
    display:flex; flex-direction:column;
    justify-content:center; align-items:center;
    z-index:99; border-radius:12px;
}
.spin-r {
    width:46px; height:46px;
    border:4px solid rgba(255,255,255,.08);
    border-top:4px solid #00d2ff;
    border-radius:50%; animation:sp .85s linear infinite;
}
@keyframes sp{to{transform:rotate(360deg)}}
.load-txt { color:white; margin-top:13px; font-weight:600; font-size:14px; }

.pg-hdr { background:linear-gradient(135deg,#0b1825,#0f2235); border:1px solid rgba(0,210,255,.22); border-radius:14px; padding:18px 20px; margin-bottom:18px; }
.series-wrap { display:flex; align-items:center; gap:8px; padding:9px 14px; background:rgba(0,210,255,.05); border:1px solid rgba(0,210,255,.22); border-radius:10px; margin-bottom:16px; flex-wrap:wrap; }
.spill { display:inline-flex; align-items:center; gap:4px; padding:4px 13px; border-radius:18px; font-size:11px; font-weight:700; cursor:pointer; border:2px solid transparent; transition:all .18s; background:rgba(255,255,255,.05); color:rgba(255,255,255,.5); white-space:nowrap; }
.spill:hover  { background:rgba(0,210,255,.13); color:#00d2ff; border-color:rgba(0,210,255,.35); }
.spill.active { background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:white; border-color:#00d2ff; box-shadow:0 2px 10px rgba(0,210,255,.3); }
.spill.expired{ opacity:.5; }
.spill.current::after { content:'LIVE'; font-size:8px; background:rgba(255,255,255,.2); padding:1px 5px; border-radius:8px; margin-left:3px; }

.filter-bar { background:linear-gradient(135deg,#1e1b4b,#312e81); padding:16px 18px; border-radius:12px; margin-bottom:16px; border:1px solid rgba(99,102,241,.3); }
.filter-bar label { color:rgba(255,255,255,.8); font-weight:600; font-size:11px; display:block; margin-bottom:4px; }
.filter-bar .form-control, .filter-bar .form-select { border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.88); color:#1e1b4b; font-size:12px; }

/* Data date badge */
.data-date-badge {
    display:inline-flex; align-items:center; gap:6px;
    background:rgba(0,210,255,.1); border:1px solid rgba(0,210,255,.3);
    border-radius:8px; padding:6px 14px; font-size:11px; color:#00d2ff; font-weight:700;
}

.cbtn { padding:3px 9px; border-radius:5px; font-size:9px; font-weight:700; cursor:pointer; border:1px solid; transition:.15s; line-height:1; }
.cbtn-ce { background:rgba(40,167,69,.12); color:#4ade80; border-color:rgba(40,167,69,.4); }
.cbtn-pe { background:rgba(220,53,69,.12); color:#f87171; border-color:rgba(220,53,69,.4); }
.cbtn-ce:hover { background:rgba(40,167,69,.3); color:white; }
.cbtn-pe:hover { background:rgba(220,53,69,.3); color:white; }

#chartModal .modal-content { background:#0b1825; border:1px solid rgba(0,210,255,.3); border-radius:14px; }
#chartModal .modal-header  { background:#0f2235; border-bottom:1px solid rgba(255,255,255,.1); border-radius:14px 14px 0 0; }
#chartModal .modal-title   { color:white; font-weight:700; font-size:14px; }
#chartModal .nav-tabs       { border-bottom:1px solid rgba(255,255,255,.1); }
#chartModal .nav-link       { color:rgba(255,255,255,.45); border:none; padding:8px 14px; font-size:11px; }
#chartModal .nav-link.active { color:#00d2ff; border-bottom:2px solid #00d2ff; background:transparent; }
.ldot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:4px; vertical-align:middle; }

.table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.pivot-table  { min-width:2200px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="pg-hdr">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1" style="color:white;">
                    📍 CE / PE Pivot Analysis (Daily)
                    <span style="background:linear-gradient(135deg,#11998e,#38ef7d);color:white;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:700;margin-left:6px;">Daily OHLC</span>
                </h4>
                <p class="mb-0" style="color:rgba(255,255,255,.5);font-size:11px;">
                    Pivots computed from <strong style="color:#ffc107;">latest available day's CE/PE OHLC</strong> ·
                    Use next trading day · Standard & Camarilla side-by-side
                </p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                {{-- Data date badge — filled after load --}}
                <div id="data-date-wrap" style="display:none;">
                    <div class="data-date-badge">
                        📅 Data Date: <span id="data-date-lbl">—</span>
                        <span style="font-size:9px;opacity:.6;">→ use on next trading day</span>
                    </div>
                </div>
                <a href="{{ route('9to12.pece-analysis') }}" class="btn btn-sm btn-outline-info"><i class="fas fa-chart-bar"></i> 9to12</a>
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chart-line"></i> EOD</a>
            </div>
        </div>
    </div>

    {{-- Series selector --}}
    {{-- <div class="series-wrap">
        <span style="color:#00d2ff;font-weight:700;font-size:11px;text-transform:uppercase;white-space:nowrap;">📅 Series:</span>
        <div id="series-pills" style="display:flex;gap:7px;flex-wrap:wrap;flex:1;"></div>
        <span style="font-size:10px;color:rgba(255,255,255,.28);white-space:nowrap;">LIVE = nearest expiry ≥ today</span>
    </div> --}}

    {{-- Filters — no date range needed, just symbol + strike pref --}}
    <div class="filter-bar">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-3">
                <label>🎯 Strike Selection</label>
                <select id="strike_pref" class="form-select">
                    <option value="HIGH_VOL" selected>Auto — Highest Volume</option>
                    <option value="ATM">Force ATM only</option>
                    <option value="ATM-1">Force ATM −1</option>
                    <option value="ATM+1">Force ATM +1</option>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <label>📊 Symbols <small style="opacity:.55;">(blank = all)</small></label>
                <select id="sym_filter" class="form-select" multiple size="2"></select>
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button id="btn_load"  class="btn btn-light   flex-fill fw-bold"><i class="fas fa-search"></i> View Data</button>
                <button id="btn_reset" class="btn btn-outline-light flex-fill"><i class="fas fa-undo"></i> Reset</button>
            </div>
        </div>
    </div>

    {{-- Table section --}}
    <div style="position:relative;min-height:450px;">
        <div class="load-overlay" id="load-overlay" style="display:none;">
            <div class="spin-r"></div>
            <div class="load-txt" id="load-txt">Loading...</div>
        </div>

        <div id="placeholder" class="text-center py-5" style="color:rgba(255,255,255,.3);">
            <i class="fas fa-crosshairs" style="font-size:3rem;opacity:.6;"></i>
            <p style="margin-top:14px;font-size:1rem;">Select a series · click <strong style="color:#00d2ff;">View Data</strong></p>
        </div>

        <div id="tbl-section" style="display:none;">
            <div class="table-responsive">
            <table class="table table-bordered pivot-table mb-0 mt-3" id="main-table">
                <thead>
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2" style="min-width:80px;">Symbol</th>
                        <th rowspan="2" style="min-width:28px;">Type</th>
                        <th rowspan="2" style="min-width:60px;">Strike<br><span style="font-weight:400;font-style:italic;">(pos)</span></th>
                        <th rowspan="2" style="min-width:120px;">OHLC <span style="font-size:8px;opacity:.5;">(latest day)</span></th>

                        {{-- STANDARD --}}
                        <th colspan="9" class="std-start" style="background:rgba(0,210,255,.1) !important;color:#00d2ff;">📐 Standard Pivot Levels</th>

                        {{-- CAMARILLA --}}
                        <th colspan="8" class="cam-start" style="background:rgba(168,85,247,.1) !important;color:#a855f7;">🔮 Camarilla Levels</th>

                        <th rowspan="2" style="min-width:55px;">Chart</th>
                    </tr>
                    <tr>
                        <th class="std-start" style="color:#28a745;">R3</th>
                        <th style="color:#5cb85c;">R2</th>
                        <th style="color:#80c780;">R1</th>
                        <th style="color:#17a2b8;">TC</th>
                        <th style="color:#00d2ff;">P</th>
                        <th style="color:#8899aa;">BC</th>
                        <th style="color:#fd7e14;">S1</th>
                        <th style="color:#dc3545;">S2</th>
                        <th style="color:#bd2130;">S3</th>

                        <th class="cam-start" style="color:#00ff88;">R4</th>
                        <th style="color:#28a745;">R3</th>
                        <th style="color:#5cb85c;">R2</th>
                        <th style="color:#80c780;">R1</th>
                        <th style="color:#fd7e14;">S1</th>
                        <th style="color:#dc3545;">S2</th>
                        <th style="color:#bd2130;">S3</th>
                        <th style="color:#8b0000;">S4</th>
                    </tr>
                </thead>
                <tbody id="main-tbody"></tbody>
            </table>
            </div>
        </div>
    </div>

</div>
</section>

{{-- Chart Modal --}}
<div class="modal fade" id="chartModal" tabindex="-1" aria-labelledby="chartModalLbl" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chartModalLbl">📊 Daily Chart</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <ul class="nav nav-tabs mb-3" id="cTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" id="tc-price-btn" data-bs-toggle="tab" data-bs-target="#tc-price" type="button">📈 Price</button></li>
                    <li class="nav-item"><button class="nav-link" id="tc-std-btn"   data-bs-toggle="tab" data-bs-target="#tc-std"   type="button">📐 + Standard</button></li>
                    <li class="nav-item"><button class="nav-link" id="tc-cam-btn"   data-bs-toggle="tab" data-bs-target="#tc-cam"   type="button">🔮 + Camarilla</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tc-price"><canvas id="ch-price" height="200"></canvas></div>
                    <div class="tab-pane fade"             id="tc-std"  ><canvas id="ch-std"   height="200"></canvas></div>
                    <div class="tab-pane fade"             id="tc-cam"  ><canvas id="ch-cam"   height="200"></canvas></div>
                </div>
                <div id="ch-legend" class="mt-3 d-flex flex-wrap gap-3" style="font-size:10px;color:rgba(255,255,255,.55);"></div>
                <div id="ch-info"   class="mt-2"                        style="font-size:10px;color:rgba(255,255,255,.35);"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
let pivotData    = [];
let activeSeries = null;
let currentSeries= null;
let allSeries    = [];
let chartInsts   = {};

$(document).ready(() => {
    loadSymbols();
    loadSeries();
    $('#btn_load').on('click',  loadData);
    $('#btn_reset').on('click', resetAll);
    document.getElementById('chartModal')
        .addEventListener('hidden.bs.modal', destroyCharts);
});

/* ── SERIES ─────────────────────────────────────── */
function loadSeries() {
    $.get('{{ route("pivot.series") }}', res => {
        if (!res.success || !res.series.length) {
            $('#series-pills').html('<span style="color:rgba(255,255,255,.3);font-size:11px;">No series found</span>');
            return;
        }
        allSeries     = res.series;
        currentSeries = res.current_series;
        renderPills();
        selectSeries(currentSeries, true);
    });
}

function renderPills() {
    let html = '';
    allSeries.forEach(s => {
        const act = s.value === activeSeries ? 'active'  : '';
        const cur = s.is_current             ? 'current' : '';
        const exp = s.is_expired             ? 'expired' : '';
        const expTxt = s.is_expired ? ' <span style="font-size:8px;opacity:.55;">(expired)</span>' : '';
        html += `<span class="spill ${act} ${cur} ${exp}" onclick="selectSeries('${s.value}',true)">${s.label}${expTxt}</span>`;
    });
    $('#series-pills').html(html);
}

function selectSeries(exp, autoRun) {
    if (!exp) return;
    activeSeries = exp;
    renderPills();
    if (autoRun) loadData();
}

/* ── SYMBOLS ─────────────────────────────────────── */
function loadSymbols() {
    $.get('{{ route("pivot.symbols") }}', res => {
        if (!res.success) return;
        $('#sym_filter').html(res.symbols.map(s => `<option value="${s}">${s}</option>`).join(''));
    });
}

/* ── LOAD DATA ───────────────────────────────────── */
function loadData() {
    setLoad(true, 'Loading latest pivot data...');
    $('#placeholder,#tbl-section').hide();
    $('#data-date-wrap').hide();

    $.ajax({
        url : '{{ route("pivot.data") }}',
        type: 'GET',
        data: {
            symbols       : $('#sym_filter').val()  || [],
            series_expiry : activeSeries            || '',
            strike_pref   : $('#strike_pref').val() || 'HIGH_VOL',
        },
        success(res) {
            setLoad(false);
            if (!res.success || !res.data || !res.data.length) {
                showPh(res.message || 'No CE/PE data found.');
                return;
            }
            pivotData = res.data;

            // Show which date's data is being used
            if (res.data_date) {
                $('#data-date-lbl').text(res.data_date);
                $('#data-date-wrap').show();
            }

            if (res.active_series && !activeSeries) {
                activeSeries = res.active_series;
                renderPills();
            }

            renderTable();
            $('#tbl-section').show();
        },
        error(xhr) {
            setLoad(false);
            showPh(xhr.responseJSON?.message || 'Server error');
        }
    });
}

/* ── FORMAT HELPERS ──────────────────────────────── */
const fv = (v, d=2) => (v != null && !isNaN(v)) ? Number(v).toFixed(d) : '—';

function ohlcCell(o) {
    if (!o || !o.found) return '<span style="color:rgba(255,255,255,.2);">No data</span>';
    const cc = (o.close - o.open) >= 0 ? '#4ade80' : '#f87171';
    return `<div style="font-size:10px;line-height:1.6;">
        O:<b style="color:rgba(255,255,255,.8)">${fv(o.open)}</b>
        H:<b style="color:#4ade80">${fv(o.high)}</b>
        L:<b style="color:#f87171">${fv(o.low)}</b>
        C:<b style="color:${cc}">${fv(o.close)}</b>
        <span style="display:block;font-size:8px;opacity:.5;">OI:${o.oi??'—'} Vol:${o.volume??'—'}</span>
    </div>`;
}

function lvCell(val, cls) {
    return val != null
        ? `<span class="${cls}">${fv(val)}</span>`
        : '<span style="color:rgba(255,255,255,.15);">—</span>';
}

/* ── RENDER TABLE ────────────────────────────────── */
function renderTable() {
    if (!pivotData.length) return;

    const stdKeys   = ['R3','R2','R1','TC','P','BC','S1','S2','S3'];
    const stdColors = ['lv-r3','lv-r2','lv-r1','lv-tc','lv-p','lv-bc','lv-s1','lv-s2','lv-s3'];
    const camKeys   = ['R4','R3','R2','R1','S1','S2','S3','S4'];
    const camColors = ['lv-r4','lv-r3','lv-r2','lv-r1','lv-s1','lv-s2','lv-s3','lv-s4'];

    let html = '';

    pivotData.forEach((row, ri) => {
        const ce = row.ce, pe = row.pe;

        const ceStdCells = stdKeys.map((k,i) => `<td ${i===0?'class="std-start"':''}>${lvCell(row.ce_std?.[k], stdColors[i])}</td>`).join('');
        const ceCamCells = camKeys.map((k,i) => `<td ${i===0?'class="cam-start"':''}>${lvCell(row.ce_cam?.[k], camColors[i])}</td>`).join('');
        const peStdCells = stdKeys.map((k,i) => `<td ${i===0?'class="std-start"':''}>${lvCell(row.pe_std?.[k], stdColors[i])}</td>`).join('');
        const peCamCells = camKeys.map((k,i) => `<td ${i===0?'class="cam-start"':''}>${lvCell(row.pe_cam?.[k], camColors[i])}</td>`).join('');

        const strikeCell = (opt) => opt.found
            ? `<span style="font-weight:700;color:rgba(255,255,255,.8);">${opt.strike}</span>
               <br><span style="font-size:8px;color:rgba(255,255,255,.35);">${opt.strike_position||''}</span>`
            : '—';

        // CE row
        html += `<tr class="sub-ce">
            <td rowspan="2" style="background:#0b1320!important;">${ri+1}</td>
            <td rowspan="2" style="background:#0b1320!important;color:#818cf8;font-weight:800;">${row.symbol}</td>
            <td class="type-cell"><span style="color:#4ade80;font-weight:800;">CE</span></td>
            <td>${strikeCell(ce)}</td>
            <td>${ohlcCell(ce)}</td>
            ${ceStdCells}
            ${ceCamCells}
            <td><button class="cbtn cbtn-ce" onclick="openChart('${row.symbol}','CE','${row.series}','${row.strike_pref}')">📊</button></td>
        </tr>`;

        // PE row
        html += `<tr class="sub-pe">
            <td class="type-cell"><span style="color:#f87171;font-weight:800;">PE</span></td>
            <td>${strikeCell(pe)}</td>
            <td>${ohlcCell(pe)}</td>
            ${peStdCells}
            ${peCamCells}
            <td><button class="cbtn cbtn-pe" onclick="openChart('${row.symbol}','PE','${row.series}','${row.strike_pref}')">📊</button></td>
        </tr>`;
    });

    $('#main-tbody').html(html || '<tr><td colspan="26" class="text-center py-4" style="color:rgba(255,255,255,.25);">No rows</td></tr>');
}

/* ── CHART MODAL ─────────────────────────────────── */
function openChart(symbol, type, series, strikePref) {
    const lbl = type === 'CE' ? '🟢 CE' : '🔴 PE';
    $('#chartModalLbl').text(`📊 ${symbol} ${lbl} (Daily)`);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('chartModal')).show();
    bootstrap.Tab.getOrCreateInstance(document.getElementById('tc-price-btn')).show();
    destroyCharts();
    $('#ch-legend').html('<span style="color:rgba(255,255,255,.3);font-style:italic;">Loading...</span>');
    $('#ch-info').html('');

    $.ajax({
        url : '{{ route("pivot.candles") }}',
        type: 'GET',
        data: { symbol, type, series_expiry: series, strike_pref: strikePref },
        success(res) {
            if (!res.success || !res.candles.length) {
                $('#ch-legend').html('<span style="color:#f87171;">No candle found.</span>'); return;
            }
            $('#ch-info').html(`Data date: ${res.date} · Strike: ${res.candles[0].strike}`);
            buildDailyChart(res.candles[0], res.standard, res.camarilla, type);
        },
        error() { $('#ch-legend').html('<span style="color:#f87171;">Error loading candle.</span>'); }
    });
}

function destroyCharts() {
    Object.values(chartInsts).forEach(c => { try{c.destroy();}catch(e){} });
    chartInsts = {};
}

function buildDailyChart(candle, std, cam, type) {
    const labels = ['Daily'];
    const mc     = type === 'CE' ? '#4ade80' : '#f87171';

    const base = {
        responsive: true,
        plugins: {
            legend  : { labels: { color:'rgba(255,255,255,.6)', font:{size:10} } },
            tooltip : { backgroundColor:'rgba(8,14,26,.95)', titleColor:'#00d2ff', bodyColor:'white' }
        },
        scales: {
            x: { ticks:{color:'rgba(255,255,255,.4)'}, grid:{color:'rgba(255,255,255,.05)'} },
            y: { ticks:{color:'rgba(255,255,255,.4)',font:{size:9}}, grid:{color:'rgba(255,255,255,.05)'} },
        }
    };

    const pl = (lbl, val, color, dash=[]) => val ? {
        label:lbl, data:[val],
        borderColor:color, borderWidth:1.5, borderDash:dash,
        pointRadius:0, fill:false, tension:0
    } : null;

    chartInsts.price = new Chart(document.getElementById('ch-price').getContext('2d'), {
        type:'line',
        data:{ labels, datasets:[
            {label:'Close', data:[candle.close], borderColor:mc, borderWidth:2.5, pointRadius:5, pointBackgroundColor:mc, fill:false},
            {label:'High',  data:[candle.high],  borderColor:'rgba(74,222,128,.35)', borderWidth:1, pointRadius:0, borderDash:[3,3]},
            {label:'Low',   data:[candle.low],   borderColor:'rgba(248,113,113,.35)', borderWidth:1, pointRadius:0, borderDash:[3,3]},
            {label:'Open',  data:[candle.open],  borderColor:'rgba(255,255,255,.2)', borderWidth:1, pointRadius:0, borderDash:[2,4]},
        ]},
        options:base
    });

    if (std) {
        chartInsts.std = new Chart(document.getElementById('ch-std').getContext('2d'), {
            type:'line',
            data:{ labels, datasets:[
                {label:'Close', data:[candle.close], borderColor:mc, borderWidth:2, pointRadius:5, pointBackgroundColor:mc, fill:false},
                pl('R3',std.R3,'#28a745',[4,2]), pl('R2',std.R2,'#5cb85c',[4,2]), pl('R1',std.R1,'#80c780',[3,3]),
                pl('TC',std.TC,'#17a2b8',[3,3]), pl('P', std.P, '#00d2ff',[3,2]), pl('BC',std.BC,'#8899aa',[3,3]),
                pl('S1',std.S1,'#fd7e14',[3,3]), pl('S2',std.S2,'#dc3545',[4,2]), pl('S3',std.S3,'#bd2130',[4,2]),
            ].filter(Boolean)},
            options:base
        });
    }

    if (cam) {
        chartInsts.cam = new Chart(document.getElementById('ch-cam').getContext('2d'), {
            type:'line',
            data:{ labels, datasets:[
                {label:'Close', data:[candle.close], borderColor:mc, borderWidth:2, pointRadius:5, pointBackgroundColor:mc, fill:false},
                pl('R4',cam.R4,'#00ff88',[5,2]), pl('R3',cam.R3,'#28a745',[4,2]),
                pl('R2',cam.R2,'#5cb85c',[3,3]), pl('R1',cam.R1,'#80c780',[3,3]),
                pl('S1',cam.S1,'#fd7e14',[3,3]), pl('S2',cam.S2,'#dc3545',[3,3]),
                pl('S3',cam.S3,'#bd2130',[4,2]), pl('S4',cam.S4,'#8b0000',[5,2]),
            ].filter(Boolean)},
            options:base
        });
    }

    const items = [
        {c:mc,        l:`${type} Close`},
        ...(std ? [{c:'#28a745',l:'Std R3'},{c:'#00d2ff',l:'Std P'},{c:'#bd2130',l:'Std S3'}] : []),
        ...(cam ? [{c:'#00ff88',l:'Cam R4'},{c:'#8b0000',l:'Cam S4'}] : []),
    ];
    $('#ch-legend').html(items.map(i=>`<span><span class="ldot" style="background:${i.c}"></span>${i.l}</span>`).join(''));
}

/* ── UTILS ───────────────────────────────────────── */
function setLoad(on, msg='Loading...') {
    if (on) { $('#load-txt').text(msg); $('#load-overlay').show(); }
    else    { $('#load-overlay').hide(); }
}

function showPh(msg) {
    $('#tbl-section').hide();
    $('#data-date-wrap').hide();
    $('#placeholder').html(`
        <i class="fas fa-info-circle" style="font-size:2.5rem;color:#0ea5e9;"></i>
        <p style="margin-top:14px;color:rgba(255,255,255,.4);">${msg}</p>
    `).show();
}

function resetAll() {
    $('#sym_filter').val('');
    $('#strike_pref').val('HIGH_VOL');
    pivotData = [];
    $('#data-date-wrap').hide();
    if (currentSeries) selectSeries(currentSeries, false);
    showPh('Filters reset. Click <strong style="color:#00d2ff;">View Data</strong> to reload.');
    setTimeout(loadData, 200);
}
</script>
@endpush