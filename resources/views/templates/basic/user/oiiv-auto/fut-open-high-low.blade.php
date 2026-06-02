@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — FUT OHL ANALYSIS  v2.0
   Dark terminal · Matches homepage design system
══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; }

:root {
    --c-bg:       #0B0E11;
    --c-surface:  #131722;
    --c-panel:    #1C2030;
    --c-border:   rgba(255,255,255,.06);
    --c-border2:  rgba(255,255,255,.11);
    --c-lime:     #7DFF00;
    --c-lime-dim: rgba(125,255,0,.1);
    --c-blue:     #00B8D4;
    --c-red:      #EF5350;
    --c-teal:     #26A69A;
    --c-amber:    #FFA726;
    --c-green:    #66BB6A;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.03);
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.ohl-wrap {
    font-family: var(--f-sans);
    color: var(--c-text);
    background: var(--c-bg);
    padding: 24px 20px 64px;
    min-height: 80vh;
}
.ohl-wrap * { box-sizing: border-box; }
.ohl-wrap a { text-decoration: none; color: inherit; }

@keyframes ohlFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.ohl-anim    { animation: ohlFadeUp .45s ease both; }
.ohl-anim.d1 { animation-delay: .08s; }
.ohl-anim.d2 { animation-delay: .16s; }
@keyframes ohlSpin  { to { transform: rotate(360deg); } }

/* ── PAGE HEADER ───────────────────────────── */
.ohl-header {
    position: relative; overflow: hidden;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; padding: 20px 24px;
    margin-bottom: 16px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.ohl-header::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .4;
}
.ohl-header::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 40% 80% at 5% 50%, rgba(125,255,0,.03), transparent 70%);
    pointer-events: none;
}
.ohl-header-left { position: relative; z-index: 1; }
.ohl-title {
    font-family: var(--f-display);
    font-size: clamp(16px, 2.5vw, 22px);
    font-weight: 800; color: #fff; margin-bottom: 6px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.ohl-title-tag {
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.25);
    color: var(--c-lime); font-size: 9px; font-weight: 700;
    padding: 3px 9px; border-radius: 100px; letter-spacing: .1em;
    font-family: var(--f-mono); text-transform: uppercase;
}
.ohl-subtitle {
    font-size: 12px; color: var(--c-muted); line-height: 1.6;
}
.ohl-subtitle strong { color: var(--c-text); }
.ohl-back-btn {
    position: relative; z-index: 1;
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--c-panel); color: var(--c-muted);
    border: 1px solid var(--c-border2); border-radius: 7px;
    padding: 8px 16px; font-size: 12px; font-weight: 600;
    transition: all .2s; font-family: var(--f-sans); white-space: nowrap;
}
.ohl-back-btn:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }

/* ── SERIES PILLS ──────────────────────────── */
.ohl-series-wrap {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; margin-bottom: 16px; flex-wrap: wrap;
}
.ohl-series-label {
    font-size: 10px; color: var(--c-blue); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em; font-family: var(--f-mono);
    white-space: nowrap; flex-shrink: 0;
}
.series-pills-row { display: flex; gap: 6px; flex-wrap: wrap; }
.series-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 13px; border-radius: 100px; cursor: pointer;
    border: 1px solid var(--c-border2); font-size: 11px; font-weight: 600;
    background: transparent; color: var(--c-muted);
    transition: all .2s; font-family: var(--f-sans); white-space: nowrap;
}
.series-pill:hover  { background: var(--c-lime-dim); color: var(--c-lime); border-color: rgba(125,255,0,.3); }
.series-pill.active { background: var(--c-lime); color: #000; border-color: var(--c-lime); font-weight: 700; }
.series-pill.current-series::after {
    content: 'LIVE'; font-size: 8px; font-family: var(--f-mono);
    background: rgba(0,0,0,.25); color: inherit;
    padding: 1px 5px; border-radius: 100px; margin-left: 3px; letter-spacing: .06em;
}

/* ── FILTER BAR ────────────────────────────── */
.ohl-filter {
    position: relative; overflow: hidden;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; padding: 18px 20px;
    margin-bottom: 20px;
}
.ohl-filter::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-blue), transparent);
    opacity: .35;
}
.ohl-filter-row {
    display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap;
}
.ohl-field { display: flex; flex-direction: column; gap: 5px; }
.ohl-label {
    font-size: 10px; font-weight: 600; letter-spacing: .1em;
    text-transform: uppercase; color: var(--c-muted); font-family: var(--f-mono);
}
.ohl-label small { text-transform: none; letter-spacing: 0; font-size: 9px; color: rgba(120,123,134,.6); }

/* Inputs */
.ohl-input, .ohl-select {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 8px 12px;
    font-size: 12px; font-weight: 600; color: var(--c-text);
    font-family: var(--f-mono); outline: none;
    transition: border-color .2s;
}
.ohl-input:focus, .ohl-select:focus { border-color: rgba(125,255,0,.45); }
.ohl-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.ohl-select { min-width: 130px; }
.ohl-select option { background: var(--c-panel); }
.ohl-num-input { width: 90px; }

/* Buttons */
.ohl-btn-primary {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    letter-spacing: .05em; padding: 9px 22px; border-radius: 7px;
    border: none; cursor: pointer; transition: all .2s;
    box-shadow: 0 0 16px rgba(125,255,0,.2);
}
.ohl-btn-primary:hover { background: #8FFF1A; box-shadow: 0 0 26px rgba(125,255,0,.35); transform: translateY(-1px); }
.ohl-btn-ghost {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--c-panel); color: var(--c-muted);
    border: 1px solid var(--c-border2);
    font-size: 13px; font-weight: 600; padding: 9px 18px;
    border-radius: 7px; cursor: pointer; transition: all .2s; font-family: var(--f-sans);
}
.ohl-btn-ghost:hover { color: var(--c-text); border-color: var(--c-border2); }
.ohl-btn-row { display: flex; gap: 8px; align-items: center; }

/* ── TWO-TABLE LAYOUT ──────────────────────── */
.ohl-tables-row {
    display: flex; gap: 16px; align-items: flex-start;
    position: relative; min-height: 280px;
}
@media (max-width: 900px) { .ohl-tables-row { flex-direction: column; } }

/* ── TABLE CARD ────────────────────────────── */
.ohl-table-card {
    flex: 1; min-width: 0;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    position: relative;
}
.ohl-table-card.bearish { border-color: rgba(239,83,80,.22); }
.ohl-table-card.bullish { border-color: rgba(38,166,154,.22); }
.ohl-table-card.bearish::before { content: ''; position: absolute; top:0; left:14px; right:14px; height:1px; background:linear-gradient(90deg,transparent,rgba(239,83,80,.5),transparent); }
.ohl-table-card.bullish::before { content: ''; position: absolute; top:0; left:14px; right:14px; height:1px; background:linear-gradient(90deg,transparent,rgba(38,166,154,.5),transparent); }

.ohl-card-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--c-border);
    background: rgba(0,0,0,.2);
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
}
.bearish .ohl-card-header { color: #EF9A9A; }
.bullish .ohl-card-header { color: #80CBC4; }

/* Signal action badges */
.sig-pe {
    background: rgba(239,83,80,.15); color: #EF9A9A;
    border: 1px solid rgba(239,83,80,.3);
    padding: 3px 10px; border-radius: 5px;
    font-size: 10px; font-weight: 800; letter-spacing: .06em;
    font-family: var(--f-mono); display: inline-block;
}
.sig-ce {
    background: rgba(38,166,154,.15); color: #4DB6AC;
    border: 1px solid rgba(38,166,154,.3);
    padding: 3px 10px; border-radius: 5px;
    font-size: 10px; font-weight: 800; letter-spacing: .06em;
    font-family: var(--f-mono); display: inline-block;
}
.ohl-count-pill {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-text); padding: 2px 9px; border-radius: 100px;
    font-size: 10px; font-weight: 700; font-family: var(--f-mono);
}
.ohl-tol-badge {
    background: rgba(255,167,38,.1); color: var(--c-amber);
    border: 1px solid rgba(255,167,38,.25);
    padding: 2px 8px; border-radius: 4px; font-size: 10px;
    font-weight: 700; font-family: var(--f-mono);
}

/* Table */
.ohl-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.ohl-table { width: 100%; border-collapse: collapse; min-width: 540px; }
.ohl-table thead th {
    padding: 9px 10px; text-align: center;
    font-size: 9px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; color: var(--c-muted);
    border-bottom: 1px solid var(--c-border);
    background: rgba(0,0,0,.25); white-space: nowrap;
    font-family: var(--f-mono);
}
.ohl-table tbody td {
    padding: 9px 10px; text-align: center;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; font-size: 11px;
    white-space: nowrap; color: var(--c-muted);
    font-family: var(--f-mono);
}
.ohl-table tbody tr:nth-child(odd)  { background: var(--c-surface); }
.ohl-table tbody tr:nth-child(even) { background: rgba(0,0,0,.15); }
.ohl-table tbody tr:hover td { background: rgba(255,255,255,.03) !important; }
.ohl-table tbody tr:last-child td { border-bottom: none; }

/* Cell value styles */
.sym-badge { font-weight: 800; font-size: 12px; color: var(--c-blue); letter-spacing: .03em; }
.price-val { font-weight: 700; font-size: 12px; color: var(--c-text); }
.ohl-open  { color: var(--c-muted) !important; }
.ohl-high  { color: #EF9A9A !important; font-weight: 700; }
.ohl-low   { color: #80CBC4 !important; font-weight: 700; }
.ohl-lh    { color: var(--c-blue) !important; font-weight: 700; }
.ohl-ll    { color: var(--c-amber) !important; font-weight: 700; }
.ohl-ltp   { color: #fff !important; font-weight: 700; }
.c-num     { font-size: 9px; color: rgba(120,123,134,.35); }

/* Change arrows */
.chg-up   { color: #80CBC4; font-weight: 700; }
.chg-down { color: #EF9A9A; font-weight: 700; }
.chg-neu  { color: var(--c-muted); }
.arr-up   { display:inline-block;width:0;height:0;border-left:4px solid transparent;border-right:4px solid transparent;border-bottom:6px solid #80CBC4;margin-right:2px;vertical-align:middle; }
.arr-down { display:inline-block;width:0;height:0;border-left:4px solid transparent;border-right:4px solid transparent;border-top:6px solid #EF9A9A;margin-right:2px;vertical-align:middle; }

/* No-data row */
.no-data-row td {
    color: var(--c-muted); font-style: italic; font-size: 11px;
    padding: 36px !important; text-align: center;
    font-family: var(--f-sans);
}

/* ── LOADING OVERLAY ───────────────────────── */
.ohl-loading {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(11,14,17,.88);
    display: none; flex-direction: column;
    justify-content: center; align-items: center;
    z-index: 100; border-radius: 10px;
    backdrop-filter: blur(4px);
}
.ohl-spinner {
    width: 34px; height: 34px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-lime);
    border-radius: 50%;
    animation: ohlSpin .9s linear infinite;
}
.ohl-loading-text { color: var(--c-muted); margin-top: 12px; font-size: 12px; font-family: var(--f-mono); }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="ohl-wrap ohl-anim">

    {{-- ── PAGE HEADER ── --}}
    <div class="ohl-header ohl-anim">
        <div class="ohl-header-left">
            <div class="ohl-title">
                FUT Open=High / Open=Low
                <span class="ohl-title-tag">9:15 Candle Only</span>
            </div>
            <div class="ohl-subtitle">
                9:15 Open = High → <strong>BUY PE</strong>
                &nbsp;|&nbsp;
                9:15 Open = Low → <strong>BUY CE</strong>
                &nbsp;|&nbsp; All data sourced from OHLC
            </div>
        </div>
        <a href="{{ route('9to12.pece-analysis') }}" class="ohl-back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    {{-- ── SERIES PILLS ── --}}
    <div class="ohl-series-wrap ohl-anim d1">
        <span class="ohl-series-label">📅 Series</span>
        <div class="series-pills-row" id="series-pills">
            <span style="color:var(--c-muted);font-size:11px;font-style:italic;font-family:var(--f-mono);">Loading…</span>
        </div>
    </div>

    {{-- ── FILTER BAR ── --}}
    <div class="ohl-filter ohl-anim d1">
        <div class="ohl-filter-row">
            <div class="ohl-field">
                <label class="ohl-label">From Date</label>
                <input type="date" id="from_date" class="ohl-input" value="{{ date('Y-m-d') }}">
            </div>
            <div class="ohl-field">
                <label class="ohl-label">To Date</label>
                <input type="date" id="to_date" class="ohl-input" value="{{ date('Y-m-d') }}">
            </div>
            <div class="ohl-field">
                <label class="ohl-label">Symbols <small>(optional)</small></label>
                <select id="symbol_filter" class="ohl-select" multiple size="2"></select>
            </div>
            <div class="ohl-field">
                <label class="ohl-label">Tolerance (pts) <small>default 1</small></label>
                <input type="number" id="tolerance" class="ohl-input ohl-num-input"
                       value="1" min="0" max="50" step="0.5">
            </div>
            <div class="ohl-btn-row" style="padding-bottom:2px;">
                <button id="run_btn" class="ohl-btn-primary">
                    <i class="fas fa-search"></i> View Data
                </button>
                <button id="reset_btn" class="ohl-btn-ghost">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ── TABLES ── --}}
    <div style="position:relative;">
        <div class="ohl-loading" id="loading-overlay">
            <div class="ohl-spinner"></div>
            <div class="ohl-loading-text">Analysing 9:15 candles…</div>
        </div>

        <div class="ohl-tables-row ohl-anim d2">

            {{-- Open-High → BUY PE --}}
            <div class="ohl-table-card bearish">
                <div class="ohl-card-header">
                    🔴 Open-High &rarr; <span class="sig-pe">BUY PE</span>
                    <span class="ohl-count-pill" id="pe_count">0</span>
                    <span class="ohl-tol-badge" id="pe_tol" style="display:none;"></span>
                </div>
                <div class="ohl-table-scroll">
                    <table class="ohl-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Symbol</th>
                                <th>Open</th>
                                <th>High</th>
                                <th>Latest High</th>
                                <th>LTP</th>
                                <th>Change</th>
                                <th>Change %</th>
                            </tr>
                        </thead>
                        <tbody id="pe-tbody">
                            <tr class="no-data-row"><td colspan="8">Select a series and click View Data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Open-Low → BUY CE --}}
            <div class="ohl-table-card bullish">
                <div class="ohl-card-header">
                    🟢 Open-Low &rarr; <span class="sig-ce">BUY CE</span>
                    <span class="ohl-count-pill" id="ce_count">0</span>
                    <span class="ohl-tol-badge" id="ce_tol" style="display:none;"></span>
                </div>
                <div class="ohl-table-scroll">
                    <table class="ohl-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Symbol</th>
                                <th>Open</th>
                                <th>Low</th>
                                <th>Latest Low</th>
                                <th>LTP</th>
                                <th>Change</th>
                                <th>Change %</th>
                            </tr>
                        </thead>
                        <tbody id="ce-tbody">
                            <tr class="no-data-row"><td colspan="8">Select a series and click View Data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>{{-- /.ohl-tables-row --}}
    </div>

</div>
</section>

@endsection

@push('script')
<script>
/* ════════════════════════════════════════════════════════
   FUT OHL — JS  (all logic identical to original)
════════════════════════════════════════════════════════ */

let activeSeries = null, currentSeries = null, allSeries = [];

/* ── Series ── */
function loadSeries() {
    $.get('{{ route("fut-ohl.series") }}', function(res) {
        if (!res.success || !res.series.length) return;
        allSeries = res.series;
        currentSeries = res.current_series;
        renderPills();
        selectSeries(currentSeries, false);
    });
}

function renderPills() {
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    let html = '';
    allSeries.forEach(s => {
        const d   = new Date(s.value);
        const lbl = months[d.getMonth()] + ' ' + d.getFullYear();
        const isActive  = s.value === activeSeries  ? 'active' : '';
        const isCurrent = s.value === currentSeries ? 'current-series' : '';
        html += `<span class="series-pill ${isActive} ${isCurrent}"
                      onclick="selectSeries('${s.value}', true)">📅 ${lbl}</span>`;
    });
    $('#series-pills').html(html);
}

function selectSeries(exp, run) {
    activeSeries = exp;
    renderPills();
    if (run) analyze();
}

/* ── Load symbols ── */
$.get('{{ route("fut-ohl.symbols") }}', function(res) {
    if (!res.success) return;
    let o = '';
    res.symbols.forEach(s => o += `<option value="${s}">${s}</option>`);
    $('#symbol_filter').html(o);
});

/* ── Analyze ── */
function analyze() {
    if (!activeSeries) { alert('Select a series first'); return; }
    const from      = $('#from_date').val();
    const to        = $('#to_date').val();
    const symbols   = $('#symbol_filter').val() || [];
    const tolerance = parseFloat($('#tolerance').val());
    if (!from || !to) { alert('Select both dates'); return; }

    $('#loading-overlay').css('display', 'flex');
    $('#pe-tbody,#ce-tbody').html('<tr class="no-data-row"><td colspan="8">Loading…</td></tr>');
    $('#pe_count,#ce_count').text('0');
    $('#pe_tol,#ce_tol').hide();

    $.ajax({
        url : '{{ route("fut-ohl.analyze") }}',
        type: 'GET',
        data: { from_date: from, to_date: to, symbols, series_expiry: activeSeries, tolerance },
        success: function(res) {
            $('#loading-overlay').hide();
            if (!res.success || !res.data || !res.data.length) {
                showEmpty(res.message || 'No signals found');
                return;
            }
            renderTables(res.data, res.tolerance);
        },
        error: function() {
            $('#loading-overlay').hide();
            showEmpty('Error loading data — check console');
        }
    });
}

/* ── Render tables ── */
function fmt(n) { return parseFloat(n).toFixed(2); }

function changeHtml(change) {
    if (change > 0)
        return `<span class="chg-up"><span class="arr-up"></span>₹${fmt(change)}</span>`;
    if (change < 0)
        return `<span class="chg-down"><span class="arr-down"></span>₹${fmt(Math.abs(change))}</span>`;
    return `<span class="chg-neu">₹${fmt(change)}</span>`;
}

function pctHtml(pct) {
    if (pct > 0)  return `<span class="chg-up">+${fmt(pct)}%</span>`;
    if (pct < 0)  return `<span class="chg-down">${fmt(pct)}%</span>`;
    return `<span class="chg-neu">${fmt(pct)}%</span>`;
}

function renderTables(data, tol) {
    const peRows = data.filter(r => r.signal === 'OPEN=HIGH');
    const ceRows = data.filter(r => r.signal === 'OPEN=LOW');

    $('#pe_count').text(peRows.length);
    $('#ce_count').text(ceRows.length);

    if (tol !== undefined) {
        const tolText = `Tol: ±${tol} pt`;
        $('#pe_tol').text(tolText).show();
        $('#ce_tol').text(tolText).show();
    }

    /* Open-High table (BUY PE) */
    if (!peRows.length) {
        $('#pe-tbody').html('<tr class="no-data-row"><td colspan="8">No Open=High signals found</td></tr>');
    } else {
        let html = '';
        peRows.forEach((r, i) => {
            html += `
            <tr>
                <td class="c-num">${i + 1}</td>
                <td><span class="sym-badge">${r.symbol}</span></td>
                <td><span class="price-val ohl-open">₹${fmt(r.open)}</span></td>
                <td><span class="price-val ohl-high">₹${fmt(r.high_915)}</span></td>
                <td><span class="price-val ohl-lh">₹${fmt(r.latest_high)}</span></td>
                <td><span class="price-val ohl-ltp">₹${fmt(r.ltp)}</span></td>
                <td>${changeHtml(r.change)}</td>
                <td>${pctHtml(r.change_pct)}</td>
            </tr>`;
        });
        $('#pe-tbody').html(html);
    }

    /* Open-Low table (BUY CE) */
    if (!ceRows.length) {
        $('#ce-tbody').html('<tr class="no-data-row"><td colspan="8">No Open=Low signals found</td></tr>');
    } else {
        let html = '';
        ceRows.forEach((r, i) => {
            html += `
            <tr>
                <td class="c-num">${i + 1}</td>
                <td><span class="sym-badge">${r.symbol}</span></td>
                <td><span class="price-val ohl-open">₹${fmt(r.open)}</span></td>
                <td><span class="price-val ohl-low">₹${fmt(r.low_915)}</span></td>
                <td><span class="price-val ohl-ll">₹${fmt(r.latest_low)}</span></td>
                <td><span class="price-val ohl-ltp">₹${fmt(r.ltp)}</span></td>
                <td>${changeHtml(r.change)}</td>
                <td>${pctHtml(r.change_pct)}</td>
            </tr>`;
        });
        $('#ce-tbody').html(html);
    }
}

function showEmpty(msg) {
    $('#pe-tbody,#ce-tbody').html(`<tr class="no-data-row"><td colspan="8">${msg}</td></tr>`);
    $('#pe_count,#ce_count').text('0');
    $('#pe_tol,#ce_tol').hide();
}

/* ── Init ── */
$(document).ready(function() { loadSeries(); });
$('#run_btn').on('click', () => analyze());
$('#reset_btn').on('click', function() {
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter').val('');
    $('#tolerance').val('1');
    showEmpty('Reset — select a series and click View Data');
    if (currentSeries) selectSeries(currentSeries, false);
});
</script>
@endpush