@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .page-header {
        background: linear-gradient(135deg, #11998e, #38ef7d);
        color: white; padding: 18px 24px; border-radius: 12px;
        margin-bottom: 20px; box-shadow: 0 4px 15px rgba(17,153,142,0.4);
    }
    .page-header h4 { color: white; margin: 0; }
    .page-header p  { color: rgba(255,255,255,0.85); margin: 4px 0 0; font-size: 12px; }

    .filter-section {
        background: linear-gradient(135deg,#667eea,#764ba2);
        padding: 16px 20px; border-radius: 12px; margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(102,126,234,0.4);
    }
    .filter-section label { color: white !important; font-weight: 600; font-size: 12px; margin-bottom: 4px; display: block; }
    .filter-section .form-control { border: 2px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.92); color: #333; font-size: 12px; padding: 5px 10px; }

    .series-wrap { display:flex; align-items:center; gap:8px; padding:8px 14px;
        background:rgba(0,210,255,0.1); border:1px solid rgba(0,210,255,0.3);
        border-radius:10px; margin-bottom:16px; flex-wrap:wrap; }
    .series-label { color:#00d2ff; font-weight:700; font-size:11px; text-transform:uppercase; white-space:nowrap; }
    .series-pill  { display:inline-flex; align-items:center; padding:4px 12px; border-radius:20px;
        font-size:11px; font-weight:700; cursor:pointer; border:2px solid transparent;
        background:rgba(255,255,255,0.07); color:rgba(255,255,255,0.6); transition:all .2s; white-space:nowrap; }
    .series-pill:hover  { background:rgba(0,210,255,0.15); color:#00d2ff; border-color:rgba(0,210,255,0.35); }
    .series-pill.active { background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:white; border-color:#00d2ff; }
    .series-pill.current-series::after { content:'ACTIVE'; font-size:8px; background:rgba(255,255,255,0.25);
        padding:1px 5px; border-radius:8px; margin-left:4px; }

    /* ── Two-table layout ── */
    .tables-row { display: flex; gap: 16px; align-items: flex-start; }
    .table-card  { flex: 1; min-width: 0; background: rgba(255,255,255,0.03);
        border-radius: 12px; overflow: hidden; border: 2px solid; }
    .table-card.bearish-card { border-color: rgba(220,53,69,0.5); }
    .table-card.bullish-card { border-color: rgba(40,167,69,0.5); }

    .table-card-header { padding: 12px 16px; font-size: 13px; font-weight: 700;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .bearish-card .table-card-header { background: linear-gradient(135deg,rgba(220,53,69,0.3),rgba(220,53,69,0.15)); color: #ff6b6b; }
    .bullish-card .table-card-header { background: linear-gradient(135deg,rgba(40,167,69,0.3),rgba(40,167,69,0.15)); color: #51cf66; }

    .table-scroll { overflow-x: auto; }
    .table-card table { width: 100%; border-collapse: collapse; min-width: 560px; }
    .table-card table thead th {
        padding: 9px 10px; text-align: center; font-size: 10px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .4px;
        border-bottom: 1px solid rgba(255,255,255,0.12);
        background: rgba(0,0,0,0.25); color: rgba(255,255,255,0.75);
        white-space: nowrap;
    }
    .table-card table tbody td {
        padding: 9px 10px; text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        vertical-align: middle; font-size: 11px; white-space: nowrap;
    }
    .table-card table tbody tr:hover  { background: rgba(255,255,255,0.05); }
    .table-card table tbody tr:last-child td { border-bottom: none; }

    /* value styles */
    .sym-badge   { font-weight: 800; font-size: 12px; color: #00d2ff; letter-spacing:.3px; }
    .price-val   { font-weight: 700; font-size: 12px; }
    .lh-val      { color: #17a2b8; font-weight: 700; }
    .ll-val      { color: #fd7e14; font-weight: 700; }
    .ltp-val     { color: #fff;    font-weight: 700; }

    /* change arrows + colours */
    .chg-up   { color: #51cf66; font-weight: 700; }
    .chg-down { color: #ff6b6b; font-weight: 700; }
    .chg-neu  { color: rgba(255,255,255,0.45); font-weight: 700; }
    .arr-up   { display:inline-block; width:0; height:0;
        border-left:4px solid transparent; border-right:4px solid transparent;
        border-bottom:6px solid #51cf66; margin-right:2px; vertical-align:middle; }
    .arr-down { display:inline-block; width:0; height:0;
        border-left:4px solid transparent; border-right:4px solid transparent;
        border-top:6px solid #ff6b6b; margin-right:2px; vertical-align:middle; }

    .action-pe { background:linear-gradient(135deg,#dc3545,#c82333); color:white;
        padding:3px 10px; border-radius:4px; font-weight:800; font-size:10px; display:inline-block; }
    .action-ce { background:linear-gradient(135deg,#28a745,#20c997); color:white;
        padding:3px 10px; border-radius:4px; font-weight:800; font-size:10px; display:inline-block; }

    .no-data-row td { color:rgba(255,255,255,0.4); font-style:italic; font-size:11px;
        padding:28px !important; text-align:center; }

    .count-pill { background:rgba(255,255,255,0.18); color:white; padding:2px 9px;
        border-radius:10px; font-size:10px; font-weight:700; margin-left:4px; }
    .tol-badge  { background:rgba(255,193,7,0.2); color:#ffc107; padding:2px 7px;
        border-radius:6px; font-size:11px; font-weight:700; margin-left:8px; }

    /* loading */
    .loading-overlay { position:absolute; top:0; left:0; right:0; bottom:0;
        background:rgba(15,32,39,0.92); display:flex; flex-direction:column;
        justify-content:center; align-items:center; z-index:100; border-radius:12px; }
    .spinner { width:40px; height:40px; border:4px solid #f3f3f3; border-top:4px solid #3498db;
        border-radius:50%; animation:spin 1s linear infinite; }
    .loading-text { color:white; margin-top:14px; font-size:14px; font-weight:600; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    @media (max-width: 900px) { .tables-row { flex-direction: column; } }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>FUT Open=High / Open=Low
                    &nbsp;<span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;">9:15 Candle Only</span>
                </h4>
                <p>9:15 Open = High &rarr; <strong>BUY PE</strong> &nbsp;|&nbsp; 9:15 Open = Low &rarr; <strong>BUY CE</strong> &nbsp;|&nbsp; All data sourced from OHLC</p>
            </div>
            <a href="{{ route('9to12.pece-analysis') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    {{-- Series Pills --}}
    {{-- <div class="series-wrap">
        <span class="series-label">&#128197; Series:</span>
        <div id="series-pills" style="display:flex;gap:6px;flex-wrap:wrap;">
            <span style="color:rgba(255,255,255,0.4);font-size:11px;font-style:italic;">Loading...</span>
        </div>
    </div> --}}

    {{-- Filters --}}
    <div class="filter-section">
        <div class="row align-items-end g-2">
            <div class="col-6 col-md-2">
                <label>From Date</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-6 col-md-2">
                <label>To Date</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-6 col-md-2">
                <label>Symbols <small style="color:rgba(255,255,255,0.6)">(optional)</small></label>
                <select id="symbol_filter" class="form-control" multiple size="2"></select>
            </div>
            <div class="col-6 col-md-2">
                <label>Tolerance (pts) <small style="color:rgba(255,255,255,0.6)">default 1</small></label>
                <input type="number" id="tolerance" class="form-control" value="1" min="0" max="50" step="0.5">
            </div>
            <div class="col-12 col-md-4 text-center mt-2 mt-md-0">
                <button id="run_btn" class="btn btn-light btn-lg px-4" style="font-size:13px;">
                    <i class="fas fa-search"></i> View Data
                </button>
                <button id="reset_btn" class="btn btn-outline-light btn-lg px-4" style="font-size:13px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- Tables --}}
    <div style="position:relative;min-height:300px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Analysing 9:15 candles...</div>
        </div>

        <div class="tables-row">

            {{-- Open-High / BUY PE --}}
            <div class="table-card bearish-card">
                <div class="table-card-header">
                    &#128308; Open-High &nbsp;&rarr;&nbsp; <span class="action-pe">BUY PE</span>
                    <span class="count-pill" id="pe_count">0</span>
                    <span class="tol-badge" id="pe_tol" style="display:none;"></span>
                </div>
                <div class="table-scroll">
                <table>
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

            {{-- Open-Low / BUY CE --}}
            <div class="table-card bullish-card">
                <div class="table-card-header">
                    &#128994; Open-Low &nbsp;&rarr;&nbsp; <span class="action-ce">BUY CE</span>
                    <span class="count-pill" id="ce_count">0</span>
                    <span class="tol-badge" id="ce_tol" style="display:none;"></span>
                </div>
                <div class="table-scroll">
                <table>
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

        </div>{{-- /.tables-row --}}
    </div>

</div>
</section>
@endsection

@push('script')
<script>
    let activeSeries = null, currentSeries = null, allSeries = [];

    // ── Series ────────────────────────────────────────────────────────────────
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
                          onclick="selectSeries('${s.value}', true)">&#128197; ${lbl}</span>`;
        });
        $('#series-pills').html(html);
    }

    function selectSeries(exp, run) {
        activeSeries = exp;
        renderPills();
        if (run) analyze();
    }

    // ── Load symbols ──────────────────────────────────────────────────────────
    $.get('{{ route("fut-ohl.symbols") }}', function(res) {
        if (!res.success) return;
        let o = '';
        res.symbols.forEach(s => o += `<option value="${s}">${s}</option>`);
        $('#symbol_filter').html(o);
    });

    // ── Analyze ───────────────────────────────────────────────────────────────
    function analyze() {
        if (!activeSeries) { alert('Select a series first'); return; }
        const from      = $('#from_date').val();
        const to        = $('#to_date').val();
        const symbols   = $('#symbol_filter').val() || [];
        const tolerance = parseFloat($('#tolerance').val());
        if (!from || !to) { alert('Select both dates'); return; }

        $('#loading-overlay').show();
        $('#pe-tbody,#ce-tbody').html('<tr class="no-data-row"><td colspan="8">Loading...</td></tr>');
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

    // ── Render tables ─────────────────────────────────────────────────────────
    function fmt(n) { return parseFloat(n).toFixed(2); }

    function changeHtml(change, pct) {
        if (change > 0) {
            return `<span class="chg-up"><span class="arr-up"></span>&#8377;${fmt(change)}</span>`;
        } else if (change < 0) {
            return `<span class="chg-down"><span class="arr-down"></span>&#8377;${fmt(Math.abs(change))}</span>`;
        }
        return `<span class="chg-neu">&#8377;${fmt(change)}</span>`;
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

        // Open-High table (BUY PE)
        if (!peRows.length) {
            $('#pe-tbody').html('<tr class="no-data-row"><td colspan="8">No Open=High signals found</td></tr>');
        } else {
            let html = '';
            peRows.forEach((r, i) => {
                html += `
                <tr>
                    <td><strong>${i + 1}</strong></td>
                    <td><span class="sym-badge">${r.symbol}</span></td>
                    <td><span class="price-val" style="color:#e0e0e0;">&#8377;${fmt(r.open)}</span></td>
                    <td><span class="price-val" style="color:#ff6b6b;">&#8377;${fmt(r.high_915)}</span></td>
                    <td><span class="price-val lh-val">&#8377;${fmt(r.latest_high)}</span></td>
                    <td><span class="price-val ltp-val">&#8377;${fmt(r.ltp)}</span></td>
                    <td>${changeHtml(r.change, r.change_pct)}</td>
                    <td>${pctHtml(r.change_pct)}</td>
                </tr>`;
            });
            $('#pe-tbody').html(html);
        }

        // Open-Low table (BUY CE)
        if (!ceRows.length) {
            $('#ce-tbody').html('<tr class="no-data-row"><td colspan="8">No Open=Low signals found</td></tr>');
        } else {
            let html = '';
            ceRows.forEach((r, i) => {
                html += `
                <tr>
                    <td><strong>${i + 1}</strong></td>
                    <td><span class="sym-badge">${r.symbol}</span></td>
                    <td><span class="price-val" style="color:#e0e0e0;">&#8377;${fmt(r.open)}</span></td>
                    <td><span class="price-val" style="color:#51cf66;">&#8377;${fmt(r.low_915)}</span></td>
                    <td><span class="price-val ll-val">&#8377;${fmt(r.latest_low)}</span></td>
                    <td><span class="price-val ltp-val">&#8377;${fmt(r.ltp)}</span></td>
                    <td>${changeHtml(r.change, r.change_pct)}</td>
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

    // ── Init ──────────────────────────────────────────────────────────────────
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