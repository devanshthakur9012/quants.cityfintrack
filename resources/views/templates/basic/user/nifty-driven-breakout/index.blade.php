@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ── Base ───────────────────────────────────────────────────────── */
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

/* ── Sticky cols ─────────────────────────────────────────────────── */
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.custom--table { min-width: 1200px; }
.custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
.custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
.custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) {
    position: sticky; z-index: 10;
}
.custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left: 0; }
.custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left: 40px; }
.custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left: 120px; }
.custom--table thead th { background:#161b22 !important; }
.custom--table tbody td:nth-child(1),
.custom--table tbody td:nth-child(2),
.custom--table tbody td:nth-child(3) { background:#0d1117; }

/* ── P&L table ───────────────────────────────────────────────────── */
.pnl-table { min-width: 600px; }
.pnl-table thead th,
.pnl-table tbody td {
    text-align: center !important;
    padding: 9px 10px !important;
    font-size: 12px !important;
    vertical-align: middle;
}

/* ── Loading ─────────────────────────────────────────────────────── */
.loading-overlay {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(13,17,23,0.96);
    display: flex; flex-direction: column;
    justify-content: center; align-items: center;
    z-index: 1000; border-radius: 12px;
}
.spinner { width:52px; height:52px; border:4px solid rgba(255,255,255,0.08); border-top:4px solid #00d2ff; border-radius:50%; animation: spin 0.8s linear infinite; }
.loading-text  { color:rgba(255,255,255,0.8); margin-top:18px; font-size:15px; font-weight:600; letter-spacing:.3px; }
.loading-sub   { color:rgba(255,255,255,0.35); margin-top:6px; font-size:12px; }
@keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

/* ── Badges ──────────────────────────────────────────────────────── */
.signal-ce { background:linear-gradient(135deg,#16a34a,#059669); color:#fff; padding:3px 9px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; letter-spacing:.3px; }
.signal-pe { background:linear-gradient(135deg,#dc2626,#ea580c); color:#fff; padding:3px 9px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; letter-spacing:.3px; }
.move-up   { color:#22c55e; font-weight:700; }
.move-down { color:#ef4444; font-weight:700; }
.profit-positive { color:#22c55e; font-weight:700; }
.profit-negative { color:#ef4444; font-weight:700; }

/* ── Page header ─────────────────────────────────────────────────── */
.page-header {
    background: linear-gradient(135deg,#0a0f1e,#0d1b2a,#091224);
    color:white; padding:22px 24px; border-radius:14px; margin-bottom:20px;
    border:1px solid rgba(0,210,255,0.2);
    box-shadow:0 4px 30px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.04);
}
.page-header h4 { color:#00d2ff; margin:0 0 6px; font-size:1.1rem; }
.nifty-badge { background:linear-gradient(135deg,#00d2ff,#0ea5e9); color:#000; padding:2px 9px; border-radius:4px; font-size:10px; font-weight:800; margin-left:7px; letter-spacing:.5px; }
.multi-badge { background:linear-gradient(135deg,#f59e0b,#d97706); color:#000; padding:2px 9px; border-radius:4px; font-size:10px; font-weight:800; margin-left:5px; letter-spacing:.5px; }

/* ── Filter section ──────────────────────────────────────────────── */
.filter-section {
    background:linear-gradient(135deg,#0a0f1e,#0d1b2a);
    border:1px solid rgba(0,210,255,0.18); padding:20px;
    border-radius:12px; margin-bottom:20px;
    box-shadow:0 4px 20px rgba(0,0,0,0.35);
}
.filter-section label { color:rgba(255,255,255,0.8) !important; font-weight:600; margin-bottom:5px; font-size:11.5px; display:block; }
.filter-section .form-control {
    border:1px solid rgba(0,210,255,0.25); background:rgba(255,255,255,0.06);
    color:white; font-size:12px; border-radius:7px;
    transition: border-color .2s, background .2s;
}
.filter-section .form-control:focus { border-color:#00d2ff; background:rgba(0,210,255,0.07); color:white; box-shadow:none; }
.filter-section .form-control option { background:#0d1b2a; color:white; }
.threshold-val { color:#00d2ff; font-size:20px; font-weight:800; display:block; text-align:center; margin-top:2px; line-height:1; }

/* ── Stats boxes ─────────────────────────────────────────────────── */
.stats-box {
    background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);
    padding:13px 10px; border-radius:10px; text-align:center;
    border-left:3px solid #00d2ff; margin-bottom:10px;
    transition: transform .15s;
}
.stats-box:hover { transform:translateY(-2px); }
.stats-box small  { display:block; color:rgba(255,255,255,0.45); font-size:9.5px; text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
.stats-box strong { display:block; font-size:1.25rem; font-weight:800; color:white; }
.stats-box.green  { border-left-color:#22c55e; }
.stats-box.red    { border-left-color:#ef4444; }
.stats-box.gold   { border-left-color:#f59e0b; }
.stats-box.cyan   { border-left-color:#00d2ff; }

/* ── Time badge ──────────────────────────────────────────────────── */
.time-badge {
    background:rgba(0,210,255,0.1); border:1px solid rgba(0,210,255,0.3);
    color:#00d2ff; padding:2px 7px; border-radius:4px; font-size:10px; font-weight:700;
}
.time-badge.orange { background:rgba(249,115,22,0.1); border-color:rgba(249,115,22,0.35); color:#f97316; }

/* ── Symbol chip ─────────────────────────────────────────────────── */
.sym-chip {
    display:inline-block; background:rgba(255,255,255,0.07);
    border:1px solid rgba(255,255,255,0.12); border-radius:5px;
    padding:2px 7px; font-size:11px; font-weight:700; color:rgba(255,255,255,0.9);
    letter-spacing:.3px;
}

/* ── Group row ───────────────────────────────────────────────────── */
.group-divider td {
    background: linear-gradient(90deg, rgba(0,210,255,0.08), transparent) !important;
    border-top: 2px solid rgba(0,210,255,0.2) !important;
    padding-top: 10px !important;
    font-size: 10px !important;
    color: rgba(0,210,255,0.7) !important;
    letter-spacing: .5px;
}

/* ── Buttons ─────────────────────────────────────────────────────── */
.btn-analyze {
    background:linear-gradient(135deg,#00d2ff,#0ea5e9);
    color:#000; font-weight:800; border:none; border-radius:8px;
    padding:9px 0; font-size:12.5px; letter-spacing:.3px;
    transition: opacity .2s, transform .1s;
}
.btn-analyze:hover { opacity:.88; transform:translateY(-1px); }

.btn-pnl-ce {
    background:linear-gradient(135deg,#16a34a,#059669);
    color:#fff; font-weight:700; border:none; border-radius:8px;
    padding:8px 14px; font-size:12px; letter-spacing:.3px;
    transition: opacity .2s, transform .1s; cursor:pointer;
}
.btn-pnl-ce:hover { opacity:.85; transform:translateY(-1px); }

.btn-pnl-pe {
    background:linear-gradient(135deg,#dc2626,#ea580c);
    color:#fff; font-weight:700; border:none; border-radius:8px;
    padding:8px 14px; font-size:12px; letter-spacing:.3px;
    transition: opacity .2s, transform .1s; cursor:pointer;
}
.btn-pnl-pe:hover { opacity:.85; transform:translateY(-1px); }

/* ── Section cards ───────────────────────────────────────────────── */
.section-card {
    background: linear-gradient(135deg,#0a0f1e,#0d1b2a);
    border:1px solid rgba(255,255,255,0.08); border-radius:12px;
    padding:18px; margin-bottom:22px;
    box-shadow:0 4px 20px rgba(0,0,0,0.3);
}
.section-title {
    font-size:13px; font-weight:700; color:#00d2ff;
    text-transform:uppercase; letter-spacing:.6px;
    margin-bottom:14px;
    padding-bottom:10px;
    border-bottom:1px solid rgba(0,210,255,0.15);
}
.pnl-section-title-ce { color:#22c55e; border-bottom-color:rgba(34,197,94,0.2); }
.pnl-section-title-pe { color:#ef4444; border-bottom-color:rgba(239,68,68,0.2); }

/* ── P&L highlight rows ──────────────────────────────────────────── */
.pnl-best  { background:rgba(34,197,94,0.07) !important; }
.pnl-worst { background:rgba(239,68,68,0.07) !important; }

/* ── Info callout ────────────────────────────────────────────────── */
.info-callout {
    background:rgba(0,210,255,0.06); border:1px solid rgba(0,210,255,0.2);
    border-radius:8px; padding:10px 14px; font-size:11px;
    color:rgba(255,255,255,0.6); margin-bottom:14px; line-height:1.7;
}
.info-callout strong { color:#00d2ff; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ───────────────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
            <div>
                <h4>
                    <i class="fas fa-bolt"></i> NIFTY-Driven Multi-Symbol Breakout
                    <span class="nifty-badge">NIFTY SIGNAL</span>
                    <span class="multi-badge">ALL SYMBOLS</span>
                </h4>
                <p style="color:rgba(255,255,255,0.55); margin:0; font-size:11.5px; line-height:1.8;">
                    Signal from <strong style="color:#00d2ff;">NIFTY FUT</strong> 15-min candles &nbsp;|&nbsp;
                    Open = 09:15 candle's <strong style="color:#f59e0b;">OPEN price</strong> &nbsp;|&nbsp;
                    CE trigger: candle HIGH ≥ open + threshold &nbsp;|&nbsp;
                    PE trigger: candle LOW ≤ open − threshold &nbsp;|&nbsp;
                    First occurrence only &nbsp;|&nbsp;
                    Buy at <strong style="color:#22c55e;">NEXT candle OPEN</strong> (trigger candle completes, market order next bar)
                </p>
            </div>
        </div>
    </div>

    {{-- ── Filters ────────────────────────────────────────────────────── --}}
    <div class="filter-section">
        <div class="row">
            <div class="col-6 col-md-2">
                <label><i class="fas fa-calendar-alt"></i> From Date</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-6 col-md-2">
                <label><i class="fas fa-calendar-alt"></i> To Date</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-6 col-md-2">
                <label>
                    <i class="fas fa-sliders-h"></i> Threshold (pts)
                    <span id="threshold_display" class="threshold-val">30</span>
                </label>
                <input type="range" id="threshold_range" min="10" max="300" step="5" value="30"
                    class="form-control-range" style="margin-top:4px; accent-color:#00d2ff;" />
                <div class="d-flex justify-content-between" style="font-size:9px; color:rgba(255,255,255,0.3);">
                    <span>10</span><span>300</span>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label><i class="fas fa-filter"></i> Signal Type</label>
                <select id="signal_filter" class="form-control">
                    <option value="BOTH">Both CE + PE</option>
                    <option value="CE">CE Only</option>
                    <option value="PE">PE Only</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label><i class="fas fa-chart-bar"></i> Symbol Filter</label>
                <select id="symbol_filter" class="form-control">
                    <option value="ALL">All Symbols</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex align-items-end">
                <div class="w-100">
                    <button type="button" id="run_analysis" class="btn btn-analyze btn-block mb-1 w-100">
                        <i class="fas fa-search"></i> Analyze
                    </button>
                    <button type="button" id="reset_btn" class="btn btn-block btn-sm w-100"
                        style="color:rgba(255,255,255,0.45); background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:7px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Stats ──────────────────────────────────────────────────────── --}}
    <div class="row mb-3">
        <div class="col-6 col-md-2"><div class="stats-box cyan">  <small>Total Trades</small>     <strong id="stat_total">—</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box green"> <small>📈 CE Trades</small>     <strong id="stat_ce" style="color:#22c55e;">—</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box red">   <small>📉 PE Trades</small>     <strong id="stat_pe" style="color:#ef4444;">—</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box gold">  <small>Symbols Hit</small>      <strong id="stat_syms" style="color:#f59e0b;">—</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box gold">  <small>Total Investment</small> <strong id="stat_invest" style="color:#f59e0b; font-size:.9rem;">—</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box">       <small>Avg Investment</small>   <strong id="stat_avg_invest" style="font-size:.9rem;">—</strong></div></div>
    </div>

    {{-- ── Trade Table ──────────────────────────────────────────────────── --}}
    <div class="section-card">
        <div class="section-title"><i class="fas fa-table"></i> Trade Details — Buy Price per Symbol</div>

        <div class="info-callout">
            <strong>Why "Trigger Time" ≠ "Buy Time"?</strong>
            A 09:45 candle on Zerodha is live from 09:45 → 10:00. The HIGH/LOW of that candle is only
            confirmed at <strong>10:00</strong> when it closes. So we <strong>buy at the OPEN of the NEXT candle</strong>
            (10:00 in this example). This matches what you'd actually execute in the market.
        </div>

        <div style="position:relative; min-height:300px;">
            <div class="loading-overlay" id="loading-trades" style="display:none;">
                <div class="spinner"></div>
                <div class="loading-text">Scanning NIFTY signal…</div>
                <div class="loading-sub">Picking highest-OI strikes across all symbols</div>
            </div>
            <div class="table-responsive">
                <table class="table custom--table" style="background:#0d1117; color:rgba(255,255,255,0.85);">
                    <thead>
                        <tr style="background:#0d1117; color:rgba(255,255,255,0.55); font-size:10.5px; text-transform:uppercase; letter-spacing:.4px;">
                            <th>#</th>
                            <th>Date</th>
                            <th>Symbol</th>
                            <th>Signal</th>
                            <th style="color:rgba(0,210,255,0.8);">NIFTY Open<br><small style="opacity:.5;">(09:15 open)</small></th>
                            <th style="color:rgba(0,210,255,0.8);">NIFTY Trigger<br><small style="opacity:.5;">High/Low val</small></th>
                            <th style="color:rgba(0,210,255,0.8);">Trigger<br><small style="opacity:.5;">candle time</small></th>
                            <th style="color:rgba(0,210,255,0.8);">NIFTY<br><small style="opacity:.5;">move pts</small></th>
                            <th>Strike</th>
                            <th>OI</th>
                            <th>Expiry</th>
                            <th>Buy Time<br><small style="opacity:.5;">next candle</small></th>
                            <th>Buy ₹<br><small style="opacity:.5;">open of buy candle</small></th>
                            <th>Lot Size</th>
                            <th>Investment</th>
                        </tr>
                    </thead>
                    <tbody id="results-tbody">
                        <tr>
                            <td colspan="15" class="text-center py-5" style="color:rgba(255,255,255,0.3);">
                                <i class="fas fa-bolt" style="font-size:3rem; color:#00d2ff; opacity:0.3;"></i>
                                <p style="margin-top:18px; font-size:1rem;">
                                    Select date range and click <strong style="color:#00d2ff;">Analyze</strong>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Exit P&L Section ─────────────────────────────────────────────── --}}
    <div class="section-card">
        <div class="section-title"><i class="fas fa-sign-out-alt"></i> Exit P&L — What If We Exit All At Once?</div>

        <div class="info-callout">
            <strong>How this works:</strong>
            After buying all symbols at the buy candle's OPEN, this table shows what would happen
            if you exited <strong>all positions simultaneously</strong> at the OPEN of each subsequent 15-min candle.
            Each row = one exit time. Load CE and PE tables separately using the buttons below.
        </div>

        <div class="d-flex align-items-center mb-3" style="gap:10px; flex-wrap:wrap;">
            <button type="button" id="load_pnl_ce" class="btn-pnl-ce">
                <i class="fas fa-arrow-up"></i> Load CE P&L Table
            </button>
            <button type="button" id="load_pnl_pe" class="btn-pnl-pe">
                <i class="fas fa-arrow-down"></i> Load PE P&L Table
            </button>
            <span style="color:rgba(255,255,255,0.3); font-size:11px; margin-left:6px;">
                Run Analyze first, then load the P&L table(s)
            </span>
        </div>

        {{-- CE P&L Table --}}
        <div id="ce-pnl-wrapper" style="display:none; margin-bottom:24px;">
            <div class="section-title pnl-section-title-ce">
                <i class="fas fa-arrow-trend-up"></i> CE Exit P&L — All Symbols
                <span id="ce-pnl-subtitle" style="font-size:10px; font-weight:400; margin-left:8px;"></span>
            </div>
            <div style="position:relative; min-height:160px;">
                <div class="loading-overlay" id="loading-pnl-ce" style="display:none;">
                    <div class="spinner" style="border-top-color:#22c55e;"></div>
                    <div class="loading-text" style="color:#22c55e;">Computing CE exit scenarios…</div>
                </div>
                <div class="table-responsive">
                    <table class="table pnl-table" style="background:#0d1117; color:rgba(255,255,255,0.85);">
                        <thead>
                            <tr style="background:#0d1117; color:rgba(255,255,255,0.55); font-size:10.5px; text-transform:uppercase; letter-spacing:.4px;">
                                <th>Exit Time</th>
                                <th>Total Sell Value<br><small style="opacity:.5;">sum of all sells</small></th>
                                <th>Total Investment<br><small style="opacity:.5;">sum of all buys</small></th>
                                <th>Profit / Loss<br><small style="opacity:.5;">sell − buy</small></th>
                                <th>ROI %</th>
                                <th>Trades</th>
                            </tr>
                        </thead>
                        <tbody id="ce-pnl-tbody">
                            <tr><td colspan="6" class="text-center py-3" style="color:rgba(255,255,255,0.3);">Click "Load CE P&L Table" above</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- PE P&L Table --}}
        <div id="pe-pnl-wrapper" style="display:none;">
            <div class="section-title pnl-section-title-pe">
                <i class="fas fa-arrow-trend-down"></i> PE Exit P&L — All Symbols
                <span id="pe-pnl-subtitle" style="font-size:10px; font-weight:400; margin-left:8px;"></span>
            </div>
            <div style="position:relative; min-height:160px;">
                <div class="loading-overlay" id="loading-pnl-pe" style="display:none;">
                    <div class="spinner" style="border-top-color:#ef4444;"></div>
                    <div class="loading-text" style="color:#ef4444;">Computing PE exit scenarios…</div>
                </div>
                <div class="table-responsive">
                    <table class="table pnl-table" style="background:#0d1117; color:rgba(255,255,255,0.85);">
                        <thead>
                            <tr style="background:#0d1117; color:rgba(255,255,255,0.55); font-size:10.5px; text-transform:uppercase; letter-spacing:.4px;">
                                <th>Exit Time</th>
                                <th>Total Sell Value<br><small style="opacity:.5;">sum of all sells</small></th>
                                <th>Total Investment<br><small style="opacity:.5;">sum of all buys</small></th>
                                <th>Profit / Loss<br><small style="opacity:.5;">sell − buy</small></th>
                                <th>ROI %</th>
                                <th>Trades</th>
                            </tr>
                        </thead>
                        <tbody id="pe-pnl-tbody">
                            <tr><td colspan="6" class="text-center py-3" style="color:rgba(255,255,255,0.3);">Click "Load PE P&L Table" above</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /.section-card -->

</div>
</section>
@endsection

@push('script')
<script>
let resultsData = [];

/* ── Threshold slider ────────────────────────────────────────────── */
$('#threshold_range').on('input', function () {
    $('#threshold_display').text($(this).val());
});

/* ── Init ────────────────────────────────────────────────────────── */
$(document).ready(function () { loadSymbols(); });

function loadSymbols() {
    $.ajax({
        url: '{{ route("nifty-driven-breakout.symbols") }}',
        type: 'GET',
        success: function (res) {
            if (!res.success) return;
            let opts = '<option value="ALL">All Symbols</option>';
            res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
            $('#symbol_filter').html(opts);
        }
    });
}

/* ── Current filter params ───────────────────────────────────────── */
function getParams() {
    return {
        from_date     : $('#from_date').val(),
        to_date       : $('#to_date').val(),
        threshold     : $('#threshold_range').val(),
        filter        : $('#signal_filter').val(),
        symbol_filter : $('#symbol_filter').val(),
    };
}

/* ── Main analysis ───────────────────────────────────────────────── */
function runAnalysis() {
    const p = getParams();
    if (!p.from_date || !p.to_date) { alert('Please select both dates'); return; }

    $('#loading-trades').show();
    resultsData = [];
    // Hide P&L tables when re-running
    $('#ce-pnl-wrapper, #pe-pnl-wrapper').hide();

    $.ajax({
        url  : '{{ route("nifty-driven-breakout.analyze") }}',
        type : 'GET',
        data : p,
        success: function (res) {
            $('#loading-trades').hide();
            if (res.success && res.data && res.data.length > 0) {
                resultsData = res.data;
                renderTradeTable();
                updateStats();
            } else {
                showTradeEmpty(res.message || 'No signals found for selected criteria.');
                resetStats();
            }
        },
        error: function () {
            $('#loading-trades').hide();
            showTradeEmpty('Server error. Please try again.');
            resetStats();
        }
    });
}

/* ── Load CE P&L ─────────────────────────────────────────────────── */
function loadCePnl() {
    const p = getParams();
    if (!p.from_date || !p.to_date) { alert('Please select dates and run Analyze first.'); return; }
    if (!resultsData.length) { alert('Please run Analyze first.'); return; }

    $('#ce-pnl-wrapper').show();
    $('#loading-pnl-ce').show();
    $('#ce-pnl-subtitle').text('');

    $.ajax({
        url  : '{{ route("nifty-driven-breakout.exit-pnl") }}',
        type : 'GET',
        data : { ...p, filter: 'CE' },
        success: function (res) {
            $('#loading-pnl-ce').hide();
            if (res.success && res.ce && res.ce.length) {
                renderPnlTable('ce', res.ce);
                $('#ce-pnl-subtitle').text(`(${res.ce.length} exit slots)`);
            } else {
                $('#ce-pnl-tbody').html(`<tr><td colspan="6" class="text-center py-3" style="color:rgba(255,255,255,0.35);">No CE data found.</td></tr>`);
            }
        },
        error: function () {
            $('#loading-pnl-ce').hide();
            $('#ce-pnl-tbody').html(`<tr><td colspan="6" class="text-center py-3" style="color:#ef4444;">Error loading CE P&L.</td></tr>`);
        }
    });
}

/* ── Load PE P&L ─────────────────────────────────────────────────── */
function loadPePnl() {
    const p = getParams();
    if (!p.from_date || !p.to_date) { alert('Please select dates and run Analyze first.'); return; }
    if (!resultsData.length) { alert('Please run Analyze first.'); return; }

    $('#pe-pnl-wrapper').show();
    $('#loading-pnl-pe').show();
    $('#pe-pnl-subtitle').text('');

    $.ajax({
        url  : '{{ route("nifty-driven-breakout.exit-pnl") }}',
        type : 'GET',
        data : { ...p, filter: 'PE' },
        success: function (res) {
            $('#loading-pnl-pe').hide();
            if (res.success && res.pe && res.pe.length) {
                renderPnlTable('pe', res.pe);
                $('#pe-pnl-subtitle').text(`(${res.pe.length} exit slots)`);
            } else {
                $('#pe-pnl-tbody').html(`<tr><td colspan="6" class="text-center py-3" style="color:rgba(255,255,255,0.35);">No PE data found.</td></tr>`);
            }
        },
        error: function () {
            $('#loading-pnl-pe').hide();
            $('#pe-pnl-tbody').html(`<tr><td colspan="6" class="text-center py-3" style="color:#ef4444;">Error loading PE P&L.</td></tr>`);
        }
    });
}

/* ── Render P&L Table ────────────────────────────────────────────── */
function renderPnlTable(type, slots) {
    const tbodyId = `#${type}-pnl-tbody`;

    // Find best and worst rows for highlighting
    const maxProfit = Math.max(...slots.map(r => r.profit));
    const minProfit = Math.min(...slots.map(r => r.profit));

    let html = '';
    slots.forEach(row => {
        const profit   = row.profit;
        const roi      = row.roi;
        const isBest   = profit === maxProfit;
        const isWorst  = profit === minProfit && profit < 0;
        const rowCls   = isBest ? 'pnl-best' : (isWorst ? 'pnl-worst' : '');
        const plCls    = profit >= 0 ? 'profit-positive' : 'profit-negative';
        const roiCls   = roi    >= 0 ? 'profit-positive' : 'profit-negative';
        const plSign   = profit >= 0 ? '+' : '';
        const roiSign  = roi    >= 0 ? '+' : '';
        const bestTag  = isBest  ? ' <span style="background:#22c55e;color:#000;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:800;">BEST</span>' : '';
        const worstTag = isWorst ? ' <span style="background:#ef4444;color:#fff;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:800;">WORST</span>' : '';

        html += `
        <tr class="${rowCls}" style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <td>
                <span class="time-badge${type==='pe' ? ' orange' : ''}">${row.exit_time}</span>
                ${bestTag}${worstTag}
            </td>
            <td><strong style="color:#f59e0b;">₹${Number(row.sell_total).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2})}</strong></td>
            <td><strong style="color:#fff;">₹${Number(row.investment).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2})}</strong></td>
            <td><strong class="${plCls}">${plSign}₹${Math.abs(profit).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2})}</strong></td>
            <td><strong class="${roiCls}">${roiSign}${Math.abs(roi).toFixed(2)}%</strong></td>
            <td><small style="color:rgba(255,255,255,0.5);">${row.trade_count}</small></td>
        </tr>`;
    });

    $(tbodyId).html(html);
}

/* ── Render Trade Table ──────────────────────────────────────────── */
function renderTradeTable() {
    let html = '';
    let lastGroupKey = null;

    resultsData.forEach((row, i) => {
        const groupKey = row.date + '_' + row.signal_type + '_' + row.trigger_time;

        if (groupKey !== lastGroupKey) {
            const sigLabel = row.signal_type === 'CE'
                ? '📈 CE SIGNAL TRIGGERED'
                : '📉 PE SIGNAL TRIGGERED';
            html += `
            <tr class="group-divider">
                <td colspan="15">
                    ${row.date} &nbsp;|&nbsp; ${sigLabel} &nbsp;|&nbsp;
                    NIFTY trigger candle: <strong>${row.trigger_time}</strong> &nbsp;→&nbsp;
                    Buy at: <strong>${row.buy_time}</strong> (next candle) &nbsp;|&nbsp;
                    Move: <strong>${row.nifty_move >= 0 ? '+' : ''}${row.nifty_move.toFixed(2)} pts</strong>
                    (open ${row.nifty_open} → trigger ${row.nifty_trigger})
                </td>
            </tr>`;
            lastGroupKey = groupKey;
        }

        const isCe     = row.signal_type === 'CE';
        const sigBadge = isCe
            ? '<span class="signal-ce">📈 CE</span>'
            : '<span class="signal-pe">📉 PE</span>';
        const moveCls  = row.nifty_move >= 0 ? 'move-up' : 'move-down';
        const moveSign = row.nifty_move >= 0 ? '+' : '';

        html += `
        <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <td><strong style="color:#00d2ff; opacity:.7;">${i + 1}</strong></td>
            <td><strong style="font-size:10.5px;">${row.date}</strong></td>
            <td><span class="sym-chip">${row.symbol}</span></td>
            <td>${sigBadge}</td>
            <td><strong style="color:#00d2ff;">₹${row.nifty_open.toFixed(2)}</strong></td>
            <td><strong style="color:#00d2ff;">₹${row.nifty_trigger.toFixed(2)}</strong></td>
            <td><span class="time-badge">${row.trigger_time}</span></td>
            <td><strong class="${moveCls}">${moveSign}${row.nifty_move.toFixed(2)}</strong></td>
            <td><strong style="color:#f59e0b;">${row.strike}</strong></td>
            <td><small style="color:rgba(255,255,255,0.5);">${fmtOI(row.strike_oi)}</small></td>
            <td><small style="color:rgba(255,255,255,0.4);">${row.expiry_date}</small></td>
            <td><span class="time-badge orange">${row.buy_time}</span></td>
            <td><strong style="color:#22c55e;">₹${row.buy_price.toFixed(2)}</strong></td>
            <td>${row.lot_size}</td>
            <td><strong>₹${Number(row.investment).toLocaleString()}</strong></td>
        </tr>`;
    });

    $('#results-tbody').html(html);
}

/* ── Stats ───────────────────────────────────────────────────────── */
function updateStats() {
    const total   = resultsData.length;
    const ceRows  = resultsData.filter(r => r.signal_type === 'CE');
    const peRows  = resultsData.filter(r => r.signal_type === 'PE');
    const syms    = new Set(resultsData.map(r => r.symbol)).size;
    const totalInv = resultsData.reduce((s, r) => s + (r.investment || 0), 0);
    const avgInv   = total > 0 ? totalInv / total : 0;

    $('#stat_total').text(total);
    $('#stat_ce').text(ceRows.length);
    $('#stat_pe').text(peRows.length);
    $('#stat_syms').text(syms);
    $('#stat_invest').text('₹' + Math.round(totalInv).toLocaleString());
    $('#stat_avg_invest').text('₹' + Math.round(avgInv).toLocaleString());
}

function resetStats() {
    ['#stat_total','#stat_ce','#stat_pe','#stat_syms','#stat_invest','#stat_avg_invest']
        .forEach(id => $(id).text('—'));
}

function showTradeEmpty(msg) {
    $('#results-tbody').html(`
        <tr>
            <td colspan="15" class="text-center py-5" style="color:rgba(255,255,255,0.3);">
                <i class="fas fa-info-circle" style="font-size:2.5rem; color:#00d2ff; opacity:0.4;"></i>
                <p style="margin-top:16px; color:rgba(255,255,255,0.4); font-size:.95rem;">${msg}</p>
            </td>
        </tr>`);
}

function fmtOI(val) {
    const n = Number(val) || 0;
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
    return n.toString();
}

/* ── Reset ───────────────────────────────────────────────────────── */
function resetAll() {
    $('#from_date,#to_date').val('{{ date("Y-m-d") }}');
    $('#threshold_range').val(30); $('#threshold_display').text('30');
    $('#signal_filter').val('BOTH'); $('#symbol_filter').val('ALL');
    resultsData = [];
    showTradeEmpty('Set filters and click Analyze');
    resetStats();
    $('#ce-pnl-wrapper, #pe-pnl-wrapper').hide();
}

/* ── Bindings ────────────────────────────────────────────────────── */
$('#run_analysis').click(() => runAnalysis());
$('#reset_btn').click(() => resetAll());
$('#load_pnl_ce').click(() => loadCePnl());
$('#load_pnl_pe').click(() => loadPePnl());
</script>
@endpush