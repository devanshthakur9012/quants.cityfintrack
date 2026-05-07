@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ─── Reset & base ─────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

.idt-wrap { padding: 24px 20px 40px; }

/* ─── Page header ──────────────────────────────────── */
.idt-header {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 12px;
    background: #fff; border-radius: 12px;
    padding: 18px 22px; margin-bottom: 18px;
    border-left: 5px solid #2563eb;
    box-shadow: 0 1px 6px rgba(0,0,0,.07);
}
.idt-header h4 { margin: 0; font-size: 17px; font-weight: 700; color: #1e293b; }
.idt-header p  { margin: 4px 0 0; font-size: 11px; color: #64748b; }
.idt-header .btn-sm { font-size: 11px; }

/* ─── Logic strip ──────────────────────────────────── */
.idt-logic {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 10px; padding: 12px 18px;
    margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 18px;
}
.idt-logic .step { display: flex; align-items: flex-start; gap: 8px; font-size: 11px; color: #475569; }
.idt-logic .step .icon { font-size: 16px; line-height: 1; flex-shrink: 0; }
.idt-logic .step strong { display: block; color: #1e293b; font-size: 11px; margin-bottom: 2px; }

/* ─── Filters ──────────────────────────────────────── */
.idt-filters {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 10px; padding: 16px 18px; margin-bottom: 18px;
}
.idt-filters label { font-size: 11px; font-weight: 600; color: #374151; margin-bottom: 4px; display: block; }
.idt-filters .form-control { font-size: 12px; border: 1px solid #d1d5db; border-radius: 6px; padding: 7px 10px; color: #1e293b; background: #fff; }
.idt-filters .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.15); outline: none; }

/* ─── Stats cards ──────────────────────────────────── */
.idt-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
.idt-stat {
    flex: 1 1 120px; min-width: 110px;
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 10px; padding: 12px 14px;
    border-top: 3px solid #e2e8f0;
}
.idt-stat small  { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; display: block; margin-bottom: 4px; }
.idt-stat strong { font-size: 1.25rem; font-weight: 700; color: #1e293b; display: block; }
.idt-stat.blue   { border-top-color: #2563eb; }
.idt-stat.green  { border-top-color: #16a34a; }
.idt-stat.red    { border-top-color: #dc2626; }
.idt-stat.purple { border-top-color: #7c3aed; }
.idt-stat.amber  { border-top-color: #d97706; }
.idt-stat.cyan   { border-top-color: #0891b2; }

/* ─── Table wrapper ────────────────────────────────── */
.idt-table-wrap {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 12px; overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    position: relative; min-height: 300px;
}
.table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ─── Table ────────────────────────────────────────── */
.idt-tbl { width: 100%; min-width: 1100px; border-collapse: collapse; font-size: 12px; }
.idt-tbl thead th {
    background: #f8fafc; color: #374151;
    font-weight: 600; font-size: 11px;
    padding: 10px 10px; text-align: center;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.idt-tbl thead th.left { text-align: left; }
.idt-tbl tbody td { padding: 10px 10px; text-align: center; border-bottom: 1px solid #f1f5f9; vertical-align: middle; background: #fff; }
.idt-tbl tbody td.left { text-align: left; }
.idt-tbl tbody tr:hover td { background: #f8faff; }

/* Sticky first 3 cols */
.idt-tbl thead th:nth-child(1),
.idt-tbl tbody td:nth-child(1) { position: sticky; left: 0; z-index: 5; min-width: 38px; }
.idt-tbl thead th:nth-child(2),
.idt-tbl tbody td:nth-child(2) { position: sticky; left: 38px; z-index: 5; min-width: 90px; }
.idt-tbl thead th:nth-child(3),
.idt-tbl tbody td:nth-child(3) { position: sticky; left: 128px; z-index: 5; min-width: 90px; }
.idt-tbl thead th { z-index: 6; }

/* Day summary row */
.idt-tbl tr.day-sum td {
    background: #f0f4ff !important;
    font-weight: 700; font-size: 11px;
    border-top: 2px solid #c7d2fe;
}

/* ─── Badges ───────────────────────────────────────── */
.badge-bull  { display:inline-block;padding:4px 10px;border-radius:6px;font-size:10px;font-weight:700;background:#dcfce7;color:#15803d; }
.badge-bear  { display:inline-block;padding:4px 10px;border-radius:6px;font-size:10px;font-weight:700;background:#fee2e2;color:#dc2626; }
.badge-neut  { display:inline-block;padding:4px 10px;border-radius:6px;font-size:10px;font-weight:700;background:#f1f5f9;color:#64748b; }
.badge-ce    { display:inline-block;padding:5px 12px;border-radius:6px;font-size:11px;font-weight:700;background:#2563eb;color:#fff; }
.badge-pe    { display:inline-block;padding:5px 12px;border-radius:6px;font-size:11px;font-weight:700;background:#dc2626;color:#fff; }
.badge-yes   { display:inline-block;padding:3px 9px;border-radius:5px;font-size:10px;font-weight:700;background:#bbf7d0;color:#15803d; }
.badge-ema-above { display:inline-block;padding:3px 9px;border-radius:5px;font-size:10px;font-weight:700;background:#dcfce7;color:#15803d; }
.badge-ema-below { display:inline-block;padding:3px 9px;border-radius:5px;font-size:10px;font-weight:700;background:#fee2e2;color:#dc2626; }
.badge-atm   { display:inline-block;padding:2px 7px;border-radius:4px;font-size:9px;font-weight:600;background:#eff6ff;color:#2563eb;margin-top:2px; }

/* ─── P/L ──────────────────────────────────────────── */
.pl-pos { color: #16a34a; font-weight: 700; }
.pl-neg { color: #dc2626; font-weight: 700; }
.pl-na  { color: #cbd5e1; font-size: 11px; }

/* ─── Section headers in table ─────────────────────── */
.th-signal { background: #eff6ff !important; }
.th-ema    { background: #f0fdf4 !important; }
.th-entry  { background: #fefce8 !important; }
.th-best   { background: #faf5ff !important; }
.th-close  { background: #eff6ff !important; }
.th-pl     { background: #f0fdf4 !important; }
.td-best   { background: #faf5ff22 !important; }
.td-close  { background: #eff6ff22 !important; }

/* ─── Loading ──────────────────────────────────────── */
.idt-loading {
    position: absolute; inset: 0; background: rgba(255,255,255,.9);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    border-radius: 12px; z-index: 20;
}
.idt-spinner {
    width: 40px; height: 40px;
    border: 4px solid #e2e8f0; border-top: 4px solid #2563eb;
    border-radius: 50%; animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.idt-loading p { margin-top: 14px; font-size: 13px; color: #64748b; font-weight: 500; }

/* ─── Empty state ──────────────────────────────────── */
.idt-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
.idt-empty i { font-size: 2.5rem; display: block; margin-bottom: 14px; }
.idt-empty p { font-size: 13px; }
</style>
@endpush

<div class="idt-wrap">

    {{-- Header --}}
    <div class="idt-header">
        <div>
            <h4>⚡ Intraday OI &mdash; Dual Confirmation
                <span style="background:#2563eb;color:#fff;font-size:10px;padding:2px 8px;border-radius:4px;margin-left:6px;font-weight:600;">10:00 ENTRY</span>
            </h4>
            <p>Signal 1: Prev 9:30 → Today 9:30 &nbsp;·&nbsp; Signal 2: 9:30 → 9:45 same day &nbsp;·&nbsp; EMA20 &amp; EMA50 trend filter &nbsp;·&nbsp; Only confirmed trades shown</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-moon"></i> EOD BTST</a>
            <a href="{{ route('oiiv-auto.index') }}"         class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
        </div>
    </div>

    {{-- Logic strip --}}
    <div class="idt-logic">
        <div class="step">
            <div class="icon">①</div>
            <div><strong>Signal 1 (Inter-day)</strong>Prev 9:30 OI → Today 9:30 OI<br>CE/PE direction → BULL/BEAR</div>
        </div>
        <div class="step">
            <div class="icon">②</div>
            <div><strong>Signal 2 (Intra-day)</strong>Today 9:30 OI → Today 9:45 OI<br>Must match Signal 1</div>
        </div>
        <div class="step">
            <div class="icon">③</div>
            <div><strong>EMA Filter</strong>FUT 9:45 close vs EMA20 &amp; EMA50<br>BULL needs ABOVE · BEAR needs BELOW</div>
        </div>
        <div class="step">
            <div class="icon">④</div>
            <div><strong>Entry @ 10:00</strong>CE → ATM or ATM-1 &nbsp;|&nbsp; PE → ATM or ATM+1<br>Exit: Best high till 15:15 · Close at 15:00</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="idt-filters">
        <div class="row mb-3">
            <div class="col-md-3 col-6 mb-2">
                <label>From Date</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-3 col-6 mb-2">
                <label>To Date</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-4 col-12 mb-2">
                <label>Symbols <span style="color:#94a3b8;font-weight:400;">(optional — leave empty for all)</span></label>
                <select id="symbol_filter" class="form-control" multiple size="2"></select>
            </div>
            <div class="col-md-2 col-12 d-flex align-items-end mb-2" style="gap:8px;">
                <button id="btn_run"   class="btn btn-primary btn-block" style="font-weight:600;"><i class="fas fa-search"></i> Analyse</button>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="idt-stats">
        <div class="idt-stat blue">  <small>Trades Found</small><strong id="s_total">0</strong></div>
        <div class="idt-stat green"> <small>BUY CE</small>      <strong id="s_ce" style="color:#2563eb;">0</strong></div>
        <div class="idt-stat red">   <small>BUY PE</small>      <strong id="s_pe" style="color:#dc2626;">0</strong></div>
        <div class="idt-stat cyan">  <small>Avg Investment</small><strong id="s_inv">₹0</strong></div>
        <div class="idt-stat purple"><small>Best Sell Total P/L</small><strong id="s_bpl">₹0</strong></div>
        <div class="idt-stat purple"><small>Best Avg ROI%</small><strong id="s_broi">0%</strong></div>
        <div class="idt-stat blue">  <small>Close Exit Total P/L</small><strong id="s_cpl">₹0</strong></div>
        <div class="idt-stat blue">  <small>Close Avg ROI%</small><strong id="s_croi">0%</strong></div>
        <div class="idt-stat green"> <small>Close Win Rate</small><strong id="s_cwr">0%</strong></div>
    </div>

    {{-- Table --}}
    <div class="idt-table-wrap">
        <div class="idt-loading" id="idt-loading" style="display:none;">
            <div class="idt-spinner"></div>
            <p id="idt-load-txt">Analysing signals…</p>
        </div>

        <div class="table-scroll">
            <table class="idt-tbl">
                <thead>
                    <tr>
                        <th class="left">#</th>
                        <th class="left">Date</th>
                        <th class="left">Symbol</th>
                        <th class="th-signal">Signal 1<br><small style="font-weight:400;opacity:.7;">Prev→Today 9:30</small></th>
                        <th class="th-signal">Signal 2<br><small style="font-weight:400;opacity:.7;">9:30→9:45</small></th>
                        <th class="th-ema">EMA Signal<br><small style="font-weight:400;opacity:.7;">EMA20 &amp; EMA50</small></th>
                        <th>Action</th>
                        <th class="th-entry">Option<br><small style="font-weight:400;opacity:.7;">ATM / ATM±1</small></th>
                        <th class="th-entry">Investment<br><small style="font-weight:400;opacity:.7;">Buy × Lot</small></th>
                        <th class="th-entry">Buy ₹<br><small style="font-weight:400;opacity:.7;">10:00 open</small></th>
                        <th class="th-best">Best Sell ₹<br><small style="font-weight:400;opacity:.7;">Max high →15:15</small></th>
                        <th class="th-pl">Best P/L</th>
                        <th class="th-pl">Best ROI%</th>
                        <th class="th-close">Close Exit ₹<br><small style="font-weight:400;opacity:.7;">15:00 close</small></th>
                        <th class="th-pl">Close P/L</th>
                        <th class="th-pl">Close ROI%</th>
                        <th class="th-pl">Day P/L</th>
                    </tr>
                </thead>
                <tbody id="main-tbody">
                    <tr>
                        <td colspan="17">
                            <div class="idt-empty">
                                <i class="fas fa-chart-bar"></i>
                                <p>Click <strong>Analyse</strong> to load confirmed intraday signals</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('script')
<script>
let td = [];

// ── Helpers ──────────────────────────────────────────────────
const fmt    = v => (v >= 0 ? '+' : '') + '₹' + Math.abs(v).toFixed(2);
const fmtRoi = v => (v >= 0 ? '+' : '') + Math.abs(v).toFixed(2) + '%';
const plCls  = v => parseFloat(v) > 0 ? 'pl-pos' : parseFloat(v) < 0 ? 'pl-neg' : 'pl-na';

function plHtml(v) {
    const n = parseFloat(v) || 0;
    if (n === 0) return '<span class="pl-na">—</span>';
    return `<span class="${plCls(n)}">${fmt(n)}</span>`;
}
function roiHtml(v) {
    const n = parseFloat(v) || 0;
    if (n === 0) return '<span class="pl-na">—</span>';
    return `<span class="${plCls(n)}">${fmtRoi(n)}</span>`;
}

function sigBadge(s) {
    if (s === 'BULLISH') return '<span class="badge-bull">🟢 BULL</span>';
    if (s === 'BEARISH') return '<span class="badge-bear">🔴 BEAR</span>';
    return '<span class="badge-neut">— NEUT</span>';
}

function emaBadge(sig, e20, e50) {
    const tip = [e20 ? 'EMA20: ' + parseFloat(e20).toFixed(1) : '', e50 ? 'EMA50: ' + parseFloat(e50).toFixed(1) : ''].filter(Boolean).join('  ·  ');
    if (sig === 'ABOVE') return `<span class="badge-ema-above" title="${tip}">▲ ABOVE EMA</span>`;
    if (sig === 'BELOW') return `<span class="badge-ema-below" title="${tip}">▼ BELOW EMA</span>`;
    return `<span class="badge-neut" title="${tip}">↔ MIXED</span>`;
}

// ── Loading ───────────────────────────────────────────────────
function setLoad(on, msg) {
    if (msg) $('#idt-load-txt').text(msg);
    $('#idt-loading').toggle(on);
}

// ── Init ──────────────────────────────────────────────────────
$(function () {
    loadSymbols();
    setTimeout(runAnalysis, 400);
});

function loadSymbols() {
    $.get('{{ route("intraday-oi.symbols") }}', function (res) {
        if (!res.success) return;
        $('#symbol_filter').html(res.symbols.map(s => `<option value="${s}">${s}</option>`).join(''));
    });
}

// ── Run ───────────────────────────────────────────────────────
function runAnalysis() {
    const from = $('#from_date').val();
    const to   = $('#to_date').val();
    const syms = $('#symbol_filter').val() || [];
    if (!from || !to) { alert('Please select both dates'); return; }

    setLoad(true, 'Analysing signals…');
    td = [];

    $.ajax({
        url: '{{ route("intraday-oi.analyze") }}', type: 'GET',
        data: { from_date: from, to_date: to, symbols: syms },
        success(res) {
            setLoad(false);
            if (res.success && res.data && res.data.length) {
                td = res.data;
                renderTable();
                updateStats();
            } else {
                showEmpty(res.message || 'No confirmed signals found for selected range');
                resetStats();
            }
        },
        error() { setLoad(false); showEmpty('Server error — please try again.'); resetStats(); }
    });
}

// ── Render table ──────────────────────────────────────────────
function renderTable() {
    if (!td.length) { showEmpty('No confirmed signals.'); return; }

    // Group by date for day-summary rows
    const byDate = {};
    td.forEach(row => { if (!byDate[row.date]) byDate[row.date] = []; byDate[row.date].push(row); });

    let html = '', n = 1;

    Object.keys(byDate).sort().reverse().forEach(date => {
        const rows = byDate[date];

        rows.forEach(row => {
            const hasBest  = row.best_sell  > 0;
            const hasClose = row.close_sell > 0;

            // Option cell
            const optCell = row.option_symbol
                ? `<span style="font-size:10px;font-weight:700;color:#1e293b;white-space:nowrap;" title="${row.option_symbol}">${row.option_symbol}</span>`
                    + `<br><span class="badge-atm">${row.atm_position || 'ATM'}</span>`
                : row.strike
                    ? `<span style="font-size:11px;font-weight:700;color:#2563eb;">${row.option_type} ${row.strike}</span>`
                        + `<br><span class="badge-atm">${row.atm_position || 'ATM'}</span>`
                    : '<span class="pl-na">N/A</span>';

            // Investment
            const invCell = row.investment > 0
                ? `<strong style="font-size:12px;">₹${Number(row.investment).toLocaleString('en-IN')}</strong><br><small style="color:#94a3b8;font-size:9px;">Lot: ${row.lot_size}</small>`
                : '<span class="pl-na">—</span>';

            // Buy cell
            const buyCell = row.buy_price > 0
                ? `<strong>₹${Number(row.buy_price).toFixed(2)}</strong>`
                : '<span class="pl-na">—</span>';

            // Best sell cell
            const bestCell = hasBest
                ? `<strong style="color:#7c3aed;">₹${Number(row.best_sell).toFixed(2)}</strong>`
                    + (row.best_sell_time ? `<br><small style="color:#94a3b8;font-size:9px;">@ ${row.best_sell_time}</small>` : '')
                : '<span class="pl-na">—</span>';

            // Close cell
            const closeCell = hasClose
                ? `<strong style="color:#2563eb;">₹${Number(row.close_sell).toFixed(2)}</strong>`
                : '<span class="pl-na">—</span>';

            // Action badge
            const actBadge = row.trade_action === 'BUY CE'
                ? '<span class="badge-ce">📈 BUY CE</span>'
                : '<span class="badge-pe">📉 BUY PE</span>';

            html += `
            <tr>
                <td class="left"><strong style="color:#94a3b8;">${n++}</strong></td>
                <td class="left"><strong style="font-size:11px;color:#000;">${row.date}</strong></td>
                <td class="left"><strong style="color:#2563eb;font-size:12px;">${row.symbol}</strong></td>

                <td class="th-signal">${sigBadge(row.signal1)}</td>
                <td class="th-signal">${sigBadge(row.signal2)}</td>
                <td class="th-ema">${emaBadge(row.ema_signal, row.ema20, row.ema50)}</td>

                <td>${actBadge}</td>

                <td class="td-entry">${optCell}</td>
                <td class="td-entry" style="color:#000;">${invCell}</td>
                <td class="td-entry" style="color:#000;">${buyCell}</td>

                <td class="td-best">${bestCell}</td>
                <td class="th-pl">${hasBest  ? plHtml(row.best_pl)   : '<span class="pl-na">—</span>'}</td>
                <td class="th-pl">${hasBest  ? roiHtml(row.best_roi) : '<span class="pl-na">—</span>'}</td>

                <td class="td-close">${closeCell}</td>
                <td class="th-pl">${hasClose ? plHtml(row.close_pl)   : '<span class="pl-na">—</span>'}</td>
                <td class="th-pl">${hasClose ? roiHtml(row.close_roi) : '<span class="pl-na">—</span>'}</td>
                <td class="th-pl">${hasClose ? plHtml(row.close_pl)   : '<span class="pl-na">—</span>'}</td>
            </tr>`;
        });

        // Day summary
        const dayRows  = rows.filter(r => r.close_sell > 0);
        if (dayRows.length) {
            const dayBest  = dayRows.reduce((s, r) => s + (r.best_pl  || 0), 0);
            const dayClose = dayRows.reduce((s, r) => s + (r.close_pl || 0), 0);
            const dayInv   = dayRows.reduce((s, r) => s + (r.investment || 0), 0);
            html += `
            <tr class="day-sum">
                <td colspan="8" style="text-align:right;">
                    📅 <strong>${date}</strong> &nbsp;|&nbsp; ${dayRows.length} trade(s) &nbsp;|&nbsp; Total Inv: ₹${dayInv.toLocaleString('en-IN')}
                </td>
                <td colspan="4" style="text-align:center;">Best Day P/L: ${plHtml(dayBest)}</td>
                <td colspan="5" style="text-align:center;">Close Day P/L: ${plHtml(dayClose)}</td>
            </tr>`;
        }
    });

    $('#main-tbody').html(html);
}

// ── Stats ─────────────────────────────────────────────────────
function updateStats() {
    const trades  = td;
    const withCl  = trades.filter(r => r.close_sell > 0);
    const wins    = withCl.filter(r => (r.close_pl || 0) > 0);
    const totalB  = trades.reduce((s, r) => s + (r.best_pl  || 0), 0);
    const totalC  = trades.reduce((s, r) => s + (r.close_pl || 0), 0);
    const avgInv  = trades.length ? trades.reduce((s, r) => s + (r.investment || 0), 0) / trades.length : 0;
    const avgBR   = trades.length ? trades.reduce((s, r) => s + (r.best_roi   || 0), 0) / trades.length : 0;
    const avgCR   = trades.length ? trades.reduce((s, r) => s + (r.close_roi  || 0), 0) / trades.length : 0;
    const cwr     = withCl.length ? ((wins.length / withCl.length) * 100).toFixed(1) + '%' : '0%';

    $('#s_total').text(trades.length);
    $('#s_ce').text(trades.filter(r => r.trade_action === 'BUY CE').length);
    $('#s_pe').text(trades.filter(r => r.trade_action === 'BUY PE').length);
    $('#s_inv').text('₹' + Math.round(avgInv).toLocaleString('en-IN'));
    $('#s_bpl').html(`<span class="${plCls(totalB)}">${fmt(totalB)}</span>`);
    $('#s_broi').html(`<span class="${plCls(avgBR)}">${fmtRoi(avgBR)}</span>`);
    $('#s_cpl').html(`<span class="${plCls(totalC)}">${fmt(totalC)}</span>`);
    $('#s_croi').html(`<span class="${plCls(avgCR)}">${fmtRoi(avgCR)}</span>`);
    $('#s_cwr').html(`<span class="${plCls(parseFloat(cwr) - 50)}">${cwr}</span>`);
}

function resetStats() {
    $('#s_total,#s_ce,#s_pe').text('0');
    $('#s_inv').text('₹0');
    $('#s_bpl,#s_cpl').text('₹0');
    $('#s_broi,#s_croi,#s_cwr').text('0%');
}

function showEmpty(msg) {
    $('#main-tbody').html(`<tr><td colspan="17"><div class="idt-empty"><i class="fas fa-search"></i><p>${msg}</p></div></td></tr>`);
}

$('#btn_run').click(runAnalysis);
</script>
@endpush