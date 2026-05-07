@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ── Base ─────────────────────────────────────────────── */
    .custom--table thead th,
    .custom--table tbody td {
        text-align: center !important;
        padding: 8px 6px !important;
        font-size: 11px !important;
        vertical-align: middle;
    }
    .custom--table thead th:first-child,
    .custom--table tbody td:first-child,
    .custom--table thead th:nth-child(2),
    .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3),
    .custom--table tbody td:nth-child(3) { text-align: left !important; }

    /* ── Sticky first 3 cols ─────────────────────────────── */
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .custom--table { min-width: 1100px; }

    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) {
        position: sticky; z-index: 10;
    }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left: 0; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left: 40px; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left: 120px; }

    /* ── Loading overlay ─────────────────────────────────── */
    .loading-overlay {
        position: absolute; top:0; left:0; right:0; bottom:0;
        background: rgba(10,20,35,0.95);
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        z-index: 1000; border-radius: 12px;
    }
    .spinner {
        width: 48px; height: 48px;
        border: 5px solid rgba(255,255,255,0.15);
        border-top: 5px solid #00d2ff;
        border-radius: 50%; animation: spin 0.9s linear infinite;
    }
    .loading-text { color: #00d2ff; margin-top: 16px; font-size: 14px; font-weight: 600; letter-spacing: 0.5px; }
    @keyframes spin { 0% { transform: rotate(0deg) } 100% { transform: rotate(360deg) } }

    /* ── Page header ─────────────────────────────────────── */
    .page-header {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        color: white; padding: 20px; border-radius: 14px;
        margin-bottom: 20px; border: 1px solid rgba(0,210,255,0.2);
        box-shadow: 0 4px 20px rgba(0,210,255,0.15);
    }
    .page-header h4 { color: #00d2ff; margin: 0 0 6px; font-size: 18px; font-weight: 700; }
    .page-header p  { color: rgba(255,255,255,0.65); margin: 0; font-size: 12px; }

    /* ── Filter section ──────────────────────────────────── */
    .filter-section {
        background: linear-gradient(135deg, #1a1a2e, #16213e);
        padding: 20px; border-radius: 14px; margin-bottom: 20px;
        border: 1px solid rgba(0,210,255,0.2);
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }
    .filter-section label { color: rgba(255,255,255,0.85) !important; font-weight: 600; margin-bottom: 6px; font-size: 12px; display: block; }
    .filter-section .form-control {
        border: 1.5px solid rgba(0,210,255,0.3); background: rgba(255,255,255,0.07);
        color: white; font-size: 12px; padding: 7px 10px; border-radius: 8px;
        transition: border-color 0.2s;
    }
    .filter-section .form-control:focus { border-color: #00d2ff; outline: none; background: rgba(255,255,255,0.1); }
    .filter-section .form-control option { background: #1a1a2e; color: white; }
    .btn-analyze {
        background: linear-gradient(135deg, #00d2ff, #3a7bd5);
        color: white; border: none; padding: 10px 28px;
        border-radius: 8px; font-size: 13px; font-weight: 700;
        letter-spacing: 0.3px; cursor: pointer; transition: opacity 0.2s, transform 0.15s;
    }
    .btn-analyze:hover { opacity: 0.9; transform: translateY(-1px); }
    .btn-reset {
        background: transparent; color: rgba(255,255,255,0.7);
        border: 1.5px solid rgba(255,255,255,0.25); padding: 10px 22px;
        border-radius: 8px; font-size: 13px; font-weight: 600;
        cursor: pointer; transition: all 0.2s; margin-left: 10px;
    }
    .btn-reset:hover { border-color: rgba(255,255,255,0.6); color: white; }

    /* ── Stats row ───────────────────────────────────────── */
    .stats-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
    .stat-card {
        flex: 1; min-width: 100px; background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.09); border-radius: 10px;
        padding: 10px 12px; text-align: center; border-top: 3px solid #00d2ff;
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-card small { display: block; color: rgba(255,255,255,0.45); font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; }
    .stat-card strong { display: block; font-size: 1.25rem; font-weight: 700; margin-top: 4px; color: white; }
    .stat-card.green  { border-top-color: #28a745; }
    .stat-card.red    { border-top-color: #dc3545; }
    .stat-card.yellow { border-top-color: #ffc107; }
    .stat-card.purple { border-top-color: #a855f7; }
    .stat-card.orange { border-top-color: #fd7e14; }

    /* ── Logic legend ────────────────────────────────────── */
    .logic-panel {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        border: 1px solid rgba(0,210,255,0.2); border-radius: 14px;
        padding: 16px 20px; margin-bottom: 20px;
    }
    .logic-panel h6 { color: #00d2ff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
    .logic-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .logic-col h6 { color: rgba(255,255,255,0.6); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 6px; margin-bottom: 8px; }
    .logic-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 10px; color: rgba(255,255,255,0.7); }
    .logic-badge { padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: 700; white-space: nowrap; }

    /* ── Sentiment badges ────────────────────────────────── */
    .sent-bullish { background: linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sent-bearish { background: linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sent-neutral { background: linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Strategy badges ─────────────────────────────────── */
    .strat-bull-dir  { background: linear-gradient(135deg,#00b894,#00cec9); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strat-bear-dir  { background: linear-gradient(135deg,#d63031,#e17055); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strat-long-str  { background: linear-gradient(135deg,#a855f7,#7c3aed); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strat-short-str { background: linear-gradient(135deg,#fd7e14,#e67e22); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strat-no-trade  { background: #2d3748; color: #a0aec0; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Condition badges ────────────────────────────────── */
    .cond-ce-up-pe-dn { background: linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-ce-dn-pe-up { background: linear-gradient(135deg,#28a745,#20c997); color:white; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-both-up     { background: linear-gradient(135deg,#667eea,#764ba2); color:white; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-both-dn     { background: linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-flat        { background: #2d3748; color:#a0aec0; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── First leg badge ─────────────────────────────────── */
    .leg-buy-ce    { color: #00b894; font-weight:700; font-size:10px; }
    .leg-buy-pe    { color: #d63031; font-weight:700; font-size:10px; }
    .leg-sell-ce   { color: #fd7e14; font-weight:700; font-size:10px; }
    .leg-sell-pe   { color: #e67e22; font-weight:700; font-size:10px; }
    .leg-none      { color: #4a5568; font-weight:700; font-size:10px; }

    /* ── Divergence highlight ────────────────────────────── */
    .diverge-row td { background: rgba(255,165,0,0.06) !important; }

    /* ── OI pct colours ──────────────────────────────────── */
    .pct-pos { color: #28a745; font-weight: 700; }
    .pct-neg { color: #dc3545; font-weight: 700; }
    .pct-zero{ color: #6c757d; font-weight: 600; }

    /* ── Table theming for dark bg ───────────────────────── */
    .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; }
    .custom--table thead th { background: rgba(0,210,255,0.06); color: #fff !important; border-bottom: 1px solid rgba(0,210,255,0.15); }
    .custom--table tbody tr:hover td { background: rgba(0,210,255,0.05) !important; }

    /* th highlight for strategy col */
    .th-strategy { background: rgba(168,85,247,0.12) !important; color: #fff !important; }

    .mm-call-trap {
        display:inline-block; background:rgba(255,71,87,0.22); color:#ff4757;
        border:1px solid rgba(255,71,87,0.5); border-radius:6px;
        padding:3px 8px; font-size:10px; font-weight:800; white-space:nowrap;
    }
    .mm-put-trap {
        display:inline-block; background:rgba(255,165,2,0.22); color:#ffa502;
        border:1px solid rgba(255,165,2,0.5); border-radius:6px;
        padding:3px 8px; font-size:10px; font-weight:800; white-space:nowrap;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- ── Page Header ─────────────────────────────────────── --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
                <div>
                    <h4><i class="fas fa-chess"></i> OI Strategy Analysis</h4>
                    <p>
                        Sentiment (old logic) &nbsp;+&nbsp; Strategy (new logic: Long/Short Straddle · Directional)
                        &nbsp;|&nbsp; EOD 14:45 CE/PE OI vs Prev Day 15:00
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-chart-bar"></i> PE/CE Analysis
                    </a>
                    <a href="{{ route('oiiv-auto.index') }}" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-chart-line"></i> OI+IV
                    </a>
                </div>
            </div>
        </div>

        {{-- ── Filters ──────────────────────────────────────────── --}}
        <div class="filter-section">
            <div class="row mb-3">
                <div class="col-md-2 col-sm-6">
                    <label><i class="fas fa-calendar-alt"></i> From Date</label>
                    <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label><i class="fas fa-calendar-alt"></i> To Date</label>
                    <input type="date" id="to_date"   class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label><i class="fas fa-tag"></i> Symbols <small style="opacity:.6;">(leave empty = all)</small></label>
                    <select id="symbol_filter" class="form-control" multiple size="2"></select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label><i class="fas fa-chess-knight"></i> Filter by Strategy</label>
                    <select id="strategy_filter" class="form-control">
                        <option value="">All Strategies</option>
                        <option value="BULLISH_DIRECTIONAL">BULLISH DIRECTIONAL</option>
                        <option value="BEARISH_DIRECTIONAL">BEARISH DIRECTIONAL</option>
                        <option value="LONG_STRADDLE">LONG STRADDLE</option>
                        <option value="SHORT_STRADDLE">SHORT STRADDLE</option>
                        <option value="NO_TRADE">NO TRADE</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-12 d-flex align-items-end">
                    <div>
                        <button type="button" id="btn_analyze" class="btn-analyze">
                            <i class="fas fa-search"></i> Analyze
                        </button>
                        <button type="button" id="btn_reset" class="btn-reset">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Stats ────────────────────────────────────────────── --}}
        <div class="stats-row" id="stats-row">
            <div class="stat-card"><small>Total Records</small><strong id="stat_total">0</strong></div>
            <div class="stat-card green"><small>Bullish Dir.</small><strong id="stat_bull_dir" style="color:#28a745;">0</strong></div>
            <div class="stat-card red"><small>Bearish Dir.</small><strong id="stat_bear_dir" style="color:#dc3545;">0</strong></div>
            <div class="stat-card purple"><small>Long Straddle</small><strong id="stat_long_str" style="color:#a855f7;">0</strong></div>
            <div class="stat-card orange"><small>Short Straddle</small><strong id="stat_short_str" style="color:#fd7e14;">0</strong></div>
            <div class="stat-card yellow"><small>No Trade</small><strong id="stat_no_trade" style="color:#ffc107;">0</strong></div>
            <div class="stat-card" style="border-top-color:#00d2ff;"><small>🔀 Sentiment ≠ Strategy</small><strong id="stat_diverge" style="color:#00d2ff;">0</strong></div>
        </div>

        {{-- ── Table ────────────────────────────────────────────── --}}
        <div class="card" style="position:relative; min-height:420px;">
            <div class="loading-overlay" id="loading-overlay" style="display:none;">
                <div class="spinner"></div>
                <div class="loading-text" id="loading-text">Analyzing OI data...</div>
            </div>
            <div class="table-responsive p-2">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Symbol</th>
                            <th>CE OI<br><small style="opacity:.6;">Today</small></th>
                            <th>CE %<br><small style="opacity:.6;">Change</small></th>
                            <th>PE OI<br><small style="opacity:.6;">Today</small></th>
                            <th>PE %<br><small style="opacity:.6;">Change</small></th>
                            <th>OI Condition</th>
                            <th>Sentiment<br><small style="opacity:.6;">Old Logic</small></th>
                            <th class="th-strategy">Strategy<br><small style="font-weight:400;opacity:.7;">New Logic</small></th>
                            <th class="th-strategy">MM Trap</th>
                            <th class="th-strategy">First Leg</th>
                            <th class="th-strategy">Remark</th>
                        </tr>
                    </thead>
                    <tbody id="result-tbody">
                        <tr>
                            <td colspan="12" class="text-center py-5" style="color:rgba(255,255,255,0.4);">
                                <i class="fas fa-chess" style="font-size:3rem; opacity:0.3;"></i>
                                <p style="margin-top:16px; font-size:1.05rem;">Click <strong style="color:#00d2ff;">Analyze</strong> to load data</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>
@endsection

@push('script')
<script>
let analysisData = [];

/* ── Helpers ─────────────────────────────────────────────── */
function fmtOI(val) {
    const n = Number(val) || 0;
    if (n >= 1_000_000) return (n/1_000_000).toFixed(2)+'M';
    if (n >= 1_000)     return (n/1_000).toFixed(1)+'K';
    return n.toString();
}

function pctHtml(pct) {
    const n  = Number(pct);
    const cls = n > 0 ? 'pct-pos' : n < 0 ? 'pct-neg' : 'pct-zero';
    return `<span class="${cls}">${n > 0 ? '+':''}${n.toFixed(2)}%</span>`;
}

function sentBadge(s) {
    if (s === 'BULLISH') return '<span class="sent-bullish">🟢 BULLISH</span>';
    if (s === 'BEARISH') return '<span class="sent-bearish">🔴 BEARISH</span>';
    return '<span class="sent-neutral">⚪ NEUTRAL</span>';
}

function stratBadge(s) {
    switch(s) {
        case 'BULLISH_DIRECTIONAL': return '<span class="strat-bull-dir">📈 BULL DIR.</span>';
        case 'BEARISH_DIRECTIONAL': return '<span class="strat-bear-dir">📉 BEAR DIR.</span>';
        case 'LONG_STRADDLE':       return '<span class="strat-long-str">⚡ LONG STRADDLE</span>';
        case 'SHORT_STRADDLE':      return '<span class="strat-short-str">📦 SHORT STRADDLE</span>';
        default:                    return '<span class="strat-no-trade">— NO TRADE</span>';
    }
}

function legBadge(leg) {
    switch(leg) {
        case 'BUY_CE':       return '<span class="leg-buy-ce">BUY CE</span>';
        case 'BUY_CE_FIRST': return '<span class="leg-buy-ce">BUY CE 1st</span>';
        case 'BUY_PE':       return '<span class="leg-buy-pe">BUY PE</span>';
        case 'BUY_PE_FIRST': return '<span class="leg-buy-pe">BUY PE 1st</span>';
        case 'SELL_CE_FIRST':return '<span class="leg-sell-ce">SELL CE 1st</span>';
        case 'SELL_PE_FIRST':return '<span class="leg-sell-pe">SELL PE 1st</span>';
        default:             return '<span class="leg-none">—</span>';
    }
}

function condBadge(c) {
    if (!c) return '<span class="cond-flat">N/A</span>';
    if (c.includes('CE ↑ + PE ↓'))      return `<span class="cond-ce-up-pe-dn">${c}</span>`;
    if (c.includes('CE ↓ + PE ↑'))      return `<span class="cond-ce-dn-pe-up">${c}</span>`;
    if (c.includes('Both ↑'))           return `<span class="cond-both-up">${c}</span>`;
    if (c.includes('Both ↓'))           return `<span class="cond-both-dn">${c}</span>`;
    return `<span class="cond-flat">${c}</span>`;
}

/* Is sentiment and strategy diverging? */
function isDiverging(row) {
    const s = row.sentiment;
    const st = row.strategy;
    if (s === 'BULLISH' && st === 'BEARISH_DIRECTIONAL') return true;
    if (s === 'BEARISH' && st === 'BULLISH_DIRECTIONAL') return true;
    if ((s === 'BULLISH' || s === 'BEARISH') && st === 'LONG_STRADDLE') return true;
    return false;
}

/* ── Toggle loading ──────────────────────────────────────── */
function setLoading(show, msg) {
    if (show) { $('#loading-text').text(msg || 'Analyzing OI data...'); $('#loading-overlay').show(); }
    else      { $('#loading-overlay').hide(); }
}

function mmTrapBadge(trap) {
    if (!trap || trap.trap === 'NO_TRAP') {
        return '<span style="color:rgba(255,255,255,0.18);font-size:9px;">—</span>';
    }
    if (trap.trap === 'CE_TRAP') {
        const dots = trap.strength === 'STRONG' ? '🔴🔴' : '🔴';
        return `<span class="mm-call-trap">${dots} CE TRAP<br><span style="font-size:8px;opacity:.8;">${trap.strength}</span></span>`;
    }
    if (trap.trap === 'PE_TRAP') {
        const dots = trap.strength === 'STRONG' ? '🟠🟠' : '🟠';
        return `<span class="mm-put-trap">${dots} PE TRAP<br><span style="font-size:8px;opacity:.8;">${trap.strength}</span></span>`;
    }
    return '<span style="color:rgba(255,255,255,0.18);font-size:9px;">—</span>';
}

/* ── Init ─────────────────────────────────────────────────── */
$(document).ready(function () {
    loadSymbols();
    setTimeout(() => runAnalysis(), 400);
});

function loadSymbols() {
    $.get('{{ route("oi-strategy.symbols") }}', function(res) {
        if (!res.success) return;
        const opts = res.symbols.map(s => `<option value="${s}">${s}</option>`).join('');
        $('#symbol_filter').html(opts);
    });
}

/* ── Main analysis ───────────────────────────────────────── */
function runAnalysis() {
    const fromDate = $('#from_date').val();
    const toDate   = $('#to_date').val();
    const symbols  = $('#symbol_filter').val() || [];
    const strategy = $('#strategy_filter').val();

    if (!fromDate || !toDate) { alert('Please select both dates'); return; }

    setLoading(true, 'Fetching OI data & computing strategy...');
    analysisData = [];

    $.ajax({
        url: '{{ route("oi-strategy.analyze") }}',
        type: 'GET',
        data: { from_date: fromDate, to_date: toDate, symbols: symbols, filter_strategy: strategy },
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                analysisData = res.data;
                renderTable();
                updateStats();
            } else {
                showEmpty(res.message || 'No data found for selected range');
                resetStats();
            }
            setLoading(false);
        },
        error: function() {
            showEmpty('Error fetching data. Please try again.');
            resetStats();
            setLoading(false);
        }
    });
}

/* ── Render table ────────────────────────────────────────── */
function renderTable() {
    let html = '';
    analysisData.forEach(function(row, i) {
        const diverge  = isDiverging(row) ? 'diverge-row' : '';
        const divIcon  = diverge ? ' <span title="Sentiment vs Strategy diverging" style="color:#ffc107;font-size:9px;">⚠</span>' : '';
        const stratCol = `style="background:rgba(168,85,247,0.05);"`;

        html += `
        <tr class="${diverge}">
            <td><strong>${i+1}</strong>${divIcon}</td>
            <td><strong>${row.date}</strong></td>
            <td><strong style="color:#00d2ff;">${row.symbol}</strong></td>

            <td>
                <strong>${fmtOI(row.ce_oi)}</strong><br>
                <small style="color:rgba(255,255,255,0.35);font-size:9px;">${Number(row.ce_oi).toLocaleString()}</small>
            </td>
            <td>${pctHtml(row.ce_oi_pct)}</td>

            <td>
                <strong>${fmtOI(row.pe_oi)}</strong><br>
                <small style="color:rgba(255,255,255,0.35);font-size:9px;">${Number(row.pe_oi).toLocaleString()}</small>
            </td>
            <td>${pctHtml(row.pe_oi_pct)}</td>

            <td>${condBadge(row.oi_condition)}</td>
            <td>${sentBadge(row.sentiment)}</td>

            <td ${stratCol}>${stratBadge(row.strategy)}</td>
            <td ${stratCol}>${mmTrapBadge(row.mm_trap)}</td>
            <td ${stratCol}>${legBadge(row.first_leg)}</td>
            <td ${stratCol}><small style="color:rgba(255,255,255,0.55);font-size:10px;">${row.strategy_remark || ''}</small></td>
        </tr>`;
    });
    $('#result-tbody').html(html);
}

/* ── Stats ───────────────────────────────────────────────── */
function updateStats() {
    const d = analysisData;
    $('#stat_total').text(d.length);
    $('#stat_bull_dir').text(d.filter(r=>r.strategy==='BULLISH_DIRECTIONAL').length);
    $('#stat_bear_dir').text(d.filter(r=>r.strategy==='BEARISH_DIRECTIONAL').length);
    $('#stat_long_str').text(d.filter(r=>r.strategy==='LONG_STRADDLE').length);
    $('#stat_short_str').text(d.filter(r=>r.strategy==='SHORT_STRADDLE').length);
    $('#stat_no_trade').text(d.filter(r=>r.strategy==='NO_TRADE').length);
    $('#stat_diverge').text(d.filter(r=>isDiverging(r)).length);
}

function resetStats() {
    $('#stat_total,#stat_bull_dir,#stat_bear_dir,#stat_long_str,#stat_short_str,#stat_no_trade,#stat_diverge').text('0');
}

function showEmpty(msg) {
    $('#result-tbody').html(`
        <tr><td colspan="12" class="text-center py-5" style="color:rgba(255,255,255,0.4);">
            <i class="fas fa-info-circle" style="font-size:2.5rem; color:rgba(0,210,255,0.4);"></i>
            <p style="margin-top:14px; font-size:1rem; color:rgba(255,255,255,0.5);">${msg}</p>
        </td></tr>`);
}

/* ── Button handlers ─────────────────────────────────────── */
$('#btn_analyze').click(() => runAnalysis());
$('#btn_reset').click(() => {
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter').val([]);
    $('#strategy_filter').val('');
    analysisData = [];
    showEmpty('Click Analyze to load data');
    resetStats();
    setTimeout(() => runAnalysis(), 300);
});
</script>
@endpush