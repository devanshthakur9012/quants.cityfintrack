@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
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

    .loading-overlay {
        position: absolute; top:0; left:0; right:0; bottom:0;
        background: rgba(19,45,57,0.95);
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        z-index: 1000; border-radius: 12px;
    }
    .spinner { width:50px; height:50px; border:5px solid #f3f3f3; border-top:5px solid #3498db; border-radius:50%; animation:spin 1s linear infinite; }
    .loading-text { color:white; margin-top:20px; font-size:16px; font-weight:600; }
    @keyframes spin { 0%{transform:rotate(0deg);} 100%{transform:rotate(360deg);} }

    .action-buy-ce { background:linear-gradient(135deg,#28a745,#20c997); color:#fff; padding:4px 10px; border-radius:6px; font-weight:700; font-size:10px; display:inline-block; box-shadow:0 2px 6px rgba(40,167,69,.3); }
    .action-buy-pe { background:linear-gradient(135deg,#dc3545,#fd7e14); color:#fff; padding:4px 10px; border-radius:6px; font-weight:700; font-size:10px; display:inline-block; box-shadow:0 2px 6px rgba(220,53,69,.3); }
    .action-both   { background:linear-gradient(135deg,#ffc107,#ff9800); color:#fff; padding:4px 10px; border-radius:6px; font-weight:700; font-size:10px; display:inline-block; box-shadow:0 2px 6px rgba(255,193,7,.3); }

    .sentiment-strong-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-strong-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-neutral        { background:linear-gradient(135deg,#6c757d,#5a6268); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .ma-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .filter-section { background:linear-gradient(135deg,#667eea,#764ba2); padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,.4); color:#fff; }
    .filter-section label { color:#fff !important; font-weight:600; margin-bottom:6px; font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,.3); background:rgba(255,255,255,.9); color:#333; font-size:12px; padding:6px 10px; }

    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.1); transition:transform .2s; }
    .stats-box:hover { transform:translateY(-2px); box-shadow:0 5px 14px rgba(0,0,0,.15); }
    .stats-box small { display:block; color:#666; font-size:10px; margin-bottom:5px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box strong { display:block; font-size:1.4rem; margin-top:3px; font-weight:700; }

    /* ── Aligned signal cards ── */
    .aligned-section {
        background: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
        border: 2px solid #00d2ff;
        border-radius: 14px;
        padding: 16px 20px 8px;
        margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(0,210,255,0.25);
    }
    .aligned-section-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
    .aligned-section-header h6 { color:#00d2ff; font-size:13px; font-weight:700; margin:0; text-transform:uppercase; letter-spacing:.5px; }
    .aligned-tag { background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:white; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:.5px; }
    .aligned-rule { background:rgba(0,210,255,0.08); border:1px solid rgba(0,210,255,0.25); border-radius:8px; padding:6px 12px; margin-bottom:12px; font-size:10px; color:rgba(255,255,255,0.75); display:flex; gap:20px; flex-wrap:wrap; }
    .aligned-rule span strong { color:#00d2ff; }
    .stats-box-dark { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); padding:10px 8px; border-radius:10px; text-align:center; border-left:4px solid #00d2ff; margin-bottom:12px; transition:transform .2s; }
    .stats-box-dark:hover { transform:translateY(-2px); }
    .stats-box-dark small { display:block; color:rgba(255,255,255,0.55); font-size:9px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box-dark strong { display:block; font-size:1.1rem; font-weight:700; margin-top:4px; color:white; }
    .stats-box-dark.green  { border-left-color:#28a745; }
    .stats-box-dark.red    { border-left-color:#dc3545; }
    .stats-box-dark.cyan   { border-left-color:#00d2ff; }
    .stats-box-dark.orange { border-left-color:#fd7e14; }
    .stats-box-dark.purple { border-left-color:#6f42c1; }
    .stats-box-dark.gold   { border-left-color:#ffc107; }

    .page-header { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,.4); }
    .ratio-badge { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; }
    .new-feature-badge { background:linear-gradient(135deg,#f093fb,#f5576c); color:#fff; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }

    .interpretation-put-writing  { color:#28a745; font-weight:700; font-size:11px; }
    .interpretation-call-writing { color:#dc3545; font-weight:700; font-size:11px; }
    .interpretation-balanced     { color:#6c757d; font-weight:700; font-size:11px; }

    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .custom--table { min-width:2500px; }

    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position:sticky; z-index:10; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left:0; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left:40px; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left:120px; }

    .btn-calculate-profit { background:linear-gradient(135deg,#f093fb,#f5576c); color:#fff; border:none; padding:12px 24px; font-weight:700; font-size:14px; border-radius:8px; box-shadow:0 4px 12px rgba(240,147,251,.4); transition:all .3s; }
    .btn-calculate-profit:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(240,147,251,.6); color:#fff; }
    .btn-calculate-profit:disabled { opacity:.6; cursor:not-allowed; }

    .condition-ce-up-pe-down { background:linear-gradient(135deg,#dc3545,#fd7e14); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-ce-down-pe-up { background:linear-gradient(135deg,#28a745,#20c997); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-both-up       { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-both-down     { background:linear-gradient(135deg,#6c757d,#5a6268); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-flat          { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .signal-strong-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .signal-strong-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:#fff; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .signal-normal         { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-size:9px; display:inline-block; }

    .profit-positive { color:#28a745; font-weight:700; }
    .profit-negative { color:#dc3545; font-weight:700; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4>{{ $pageTitle }} <span class="new-feature-badge">INDEX</span></h4>
                <p>PE/CE OI Change Analysis — data from <strong>index_option_strikes</strong> table &nbsp;|&nbsp; 50 MA from <strong>option_ohlc_data</strong></p>
            </div>
            <div>
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm mr-2"><i class="fas fa-exchange-alt"></i> Stock PE/CE</a>
                <a href="{{ route('oiiv-auto.config') }}" class="btn btn-light btn-sm"><i class="fas fa-cog"></i> Configs</a>
            </div>
        </div>
    </div>

    {{-- Logic alert --}}
    <div class="alert" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:12px;margin-bottom:20px;padding:15px;">
        <h6 style="color:#fff;margin-bottom:10px;font-size:14px;"><i class="fas fa-info-circle"></i> <strong>CE/PE OI Change Analysis Logic:</strong></h6>
        <div class="row mb-2">
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>📊 CE/PE OI Analysis</strong></small>
                <ul style="font-size:10px;margin-top:5px;">
                    <li><strong>CE ↑ + PE ↓</strong> → BEARISH</li>
                    <li><strong>CE ↓ + PE ↑</strong> → BULLISH</li>
                    <li><strong>Both ↑</strong> → CE% > PE% = BEARISH</li>
                    <li><strong>Both ↓</strong> → CE% &lt; PE% = BULLISH</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>📈 PE/CE Ratio = PE_OI / CE_OI</strong></small>
                <ul style="font-size:10px;margin-top:5px;">
                    <li>Ratio > 1.2 → Put Writing → Bullish</li>
                    <li>Ratio &lt; 0.8 → Call Writing → Bearish</li>
                    <li>0.8–1.2 → Balanced OI → Neutral</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>📉 50 MA Signal</strong></small>
                <ul style="font-size:10px;margin-top:5px;">
                    <li>FUT EOD close <strong>above</strong> 50 MA → 🟢 BULLISH</li>
                    <li>FUT EOD close <strong>below</strong> 50 MA → 🔴 BEARISH</li>
                    <li>Source: <code>option_ohlc_data</code> FUT candles</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>🎯 Trade Action</strong></small>
                <ul style="font-size:10px;margin-top:5px;">
                    <li>BULLISH → <strong style="color:#90EE90;">BUY CE</strong></li>
                    <li>BEARISH → <strong style="color:#FFB6C1;">BUY PE</strong></li>
                    <li>NEUTRAL → <strong style="color:#FFD700;">WAIT</strong></li>
                    <li>🎯 Aligned = Sentiment + 50MA match</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-section">
        <div class="row mb-2">
            <div class="col-md-3">
                <label><i class="fas fa-calendar-alt"></i> From Date:</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-calendar-alt"></i> To Date:</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-filter"></i> Symbols (Optional):</label>
                <select id="symbol_filter" class="form-control" multiple size="2"></select>
                <small style="color:rgba(255,255,255,.8);font-size:10px;">Leave empty for all</small>
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-bullseye"></i> Trade Action:</label>
                <select id="action_filter" class="form-control">
                    <option value="">All Trade Actions</option>
                    <option value="BUY CE">BUY CE Only</option>
                    <option value="BUY PE">BUY PE Only</option>
                    <option value="WAIT">WAIT Only</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12 text-center">
                <button type="button" id="run_analysis" class="btn btn-light btn-lg" style="min-width:150px;font-size:13px;">
                    <i class="fas fa-search"></i> View Data
                </button>
                <button type="button" id="calculate_profit" class="btn btn-calculate-profit btn-lg" style="min-width:180px;" disabled>
                    <i class="fas fa-calculator"></i> Calculate P/L
                </button>
                <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg" style="min-width:150px;font-size:13px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- Stats Row 1 --}}
    <div class="row mb-2">
        <div class="col-md-2"><div class="stats-box"><small>Total Records</small><strong id="total_records" class="text-dark">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE</small><strong id="buy_ce_count" style="color:#28a745;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE</small><strong id="buy_pe_count" style="color:#dc3545;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>WAIT</small><strong id="both_count" style="color:#ffc107;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>Exit P/L</small><strong id="total_exit_profit" style="color:#28a745;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>Highest P/L</small><strong id="total_highest_profit" style="color:#17a2b8;">₹0</strong></div></div>
    </div>

    {{-- Stats Row 2 --}}
    <div class="row mb-3">
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BULLISH</small><strong id="strong_bullish_count" style="color:#28a745;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BEARISH</small><strong id="strong_bearish_count" style="color:#dc3545;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#007bff;"><small>Investment</small><strong id="total_investment" style="color:#007bff;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>Winners</small><strong id="winning_trades" style="color:#28a745;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>Exit ROI %</small><strong id="exit_roi" style="color:#ffc107;">0%</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>Best ROI %</small><strong id="highest_roi" style="color:#17a2b8;">0%</strong></div></div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- 🎯 ALIGNED SIGNALS SECTION (Sentiment + 50MA Confirmed)           --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="aligned-section">
        <div class="aligned-section-header">
            <span style="font-size:20px;">🎯</span>
            <h6>Aligned Signals Only — Sentiment + 50MA Confirmed</h6>
            <span class="aligned-tag">High Confidence</span>
        </div>

        <div class="aligned-rule">
            <span>✅ <strong>BUY CE Confirmed</strong> = Sentiment BULLISH + 50MA Above MA</span>
            <span>✅ <strong>BUY PE Confirmed</strong> = Sentiment BEARISH + 50MA Below MA</span>
            <span style="color:rgba(255,255,255,0.45);">❌ Mismatched (e.g. BEARISH + Above MA) excluded</span>
            <span style="color:rgba(255,255,255,0.45);">⚙️ P/L updates after clicking "Calculate P/L"</span>
        </div>

        {{-- Row 1: Counts --}}
        <div class="row">
            <div class="col-6 col-md-2">
                <div class="stats-box-dark cyan">
                    <small>🎯 Aligned Total</small>
                    <strong id="aligned_count">0</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark green">
                    <small>📈 BUY CE Aligned</small>
                    <strong id="aligned_buy_ce" style="color:#28a745;">0</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark red">
                    <small>📉 BUY PE Aligned</small>
                    <strong id="aligned_buy_pe" style="color:#dc3545;">0</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark gold">
                    <small>💰 Avg Investment</small>
                    <strong id="aligned_avg_inv" style="color:#ffc107;">₹0</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark" style="border-left-color:#adb5bd;">
                    <small>📊 Win Rate (Exit)</small>
                    <strong id="aligned_win_rate" style="color:#adb5bd;">0%</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark" style="border-left-color:#adb5bd;">
                    <small>📊 Win Rate (High)</small>
                    <strong id="aligned_win_rate_high" style="color:#adb5bd;">0%</strong>
                </div>
            </div>
        </div>

        {{-- Row 2: P/L & ROI --}}
        <div class="row">
            <div class="col-6 col-md-2">
                <div class="stats-box-dark cyan">
                    <small>💼 Total Investment</small>
                    <strong id="aligned_total_inv">₹0</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark cyan">
                    <small>📈 Exit Total P/L</small>
                    <strong id="aligned_exit_pl">₹0</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark cyan">
                    <small>📈 Exit Avg ROI</small>
                    <strong id="aligned_exit_roi">0%</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark orange">
                    <small>🚀 High Total P/L</small>
                    <strong id="aligned_high_pl">₹0</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark orange">
                    <small>🚀 High Avg ROI</small>
                    <strong id="aligned_high_roi">0%</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box-dark purple">
                    <small>🏆 Aligned Winners</small>
                    <strong id="aligned_winners" style="color:#6f42c1;">0</strong>
                </div>
            </div>
        </div>
    </div>
    {{-- ══════════════════════════════════════════════════════════════════ --}}

    {{-- Table --}}
    <div style="position:relative;min-height:400px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Loading data...</div>
        </div>
        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>CE OI</th>
                        <th>CE %</th>
                        <th>PE OI</th>
                        <th>PE %</th>
                        <th>Strength</th>
                        <th>Condition</th>
                        <th>Ratio</th>
                        <th>Interpretation</th>
                        <th>FUT %</th>
                        <th>Sentiment</th>
                        <th>50MA</th>
                        <th>Strong</th>
                        <th>Action</th>
                        <th>FUT Today</th>
                        <th>FUT Prev</th>
                        <th>FUT Δ</th>
                        <th>FUT Δ%</th>
                        <th>FUT Signal</th>
                        <th>Option (Strike)</th>
                        <th>Investment</th>
                        <th>Entry ₹</th>
                        <th>Exit ₹</th>
                        <th>High ₹</th>
                        <th>Exit P/L</th>
                        <th>High P/L</th>
                        <th>Exit ROI</th>
                        <th>High ROI</th>
                    </tr>
                </thead>
                <tbody id="analysis-tbody">
                    <tr>
                        <td colspan="30" class="text-center py-5">
                            <i class="fas fa-chart-pie" style="font-size:3rem;opacity:.5;"></i>
                            <p style="font-size:1.1rem;margin-top:20px;">Click <strong>"View Data"</strong> to load signals</p>
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
    let analysisData    = [];
    let profitCalculated = false;

    function toggleLoading(show, message = 'Loading data...') {
        if (show) { $('#loading-overlay .loading-text').text(message); $('#loading-overlay').show(); }
        else { $('#loading-overlay').hide(); }
    }

    $(document).ready(function () {
        loadSymbols();
        setTimeout(() => runAnalysis(), 500);
    });

    function loadSymbols() {
        $.ajax({
            url: '{{ route("index-oi.symbols") }}', type: 'GET',
            success: function (res) {
                if (res.success) {
                    let opts = '';
                    res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
                    $('#symbol_filter').html(opts);
                }
            }
        });
    }

    // ── Alignment helper ──────────────────────────────────────────────────────
    // BULLISH sentiment + BULLISH 50MA  →  confirmed BUY CE
    // BEARISH sentiment + BEARISH 50MA  →  confirmed BUY PE
    function isAligned(row) {
        const sent = row.final_sentiment;
        const ma   = row.fut_50ma_signal;
        return (sent === 'BULLISH' && ma === 'BULLISH') ||
               (sent === 'BEARISH' && ma === 'BEARISH');
    }

    // ── 50MA badge helper ─────────────────────────────────────────────────────
    function getMa50Badge(signal) {
        if (!signal || signal === 'N/A') return '<span class="text-muted" style="font-size:11px;">N/A</span>';
        if (signal === 'BULLISH') return '<span class="ma-bullish">🟢 Above MA</span>';
        if (signal === 'BEARISH') return '<span class="ma-bearish">🔴 Below MA</span>';
        return '<span class="ma-neutral">⚪ On MA</span>';
    }

    // ── P/L formatting helpers ────────────────────────────────────────────────
    function plHtml(v) {
        const cls = v >= 0 ? 'profit-positive' : 'profit-negative';
        return `<strong class="${cls}">${v >= 0 ? '+' : ''}₹${Math.abs(v).toFixed(2)}</strong>`;
    }
    function roiHtml(v) {
        const cls = v >= 0 ? 'profit-positive' : 'profit-negative';
        return `<strong class="${cls}">${v >= 0 ? '+' : ''}${Math.abs(v).toFixed(2)}%</strong>`;
    }
    function wCls(pct) { return parseFloat(pct) >= 50 ? 'profit-positive' : 'profit-negative'; }

    // ── Run analysis ──────────────────────────────────────────────────────────
    function runAnalysis() {
        const fromDate       = $('#from_date').val();
        const toDate         = $('#to_date').val();
        const selectedSymbols = $('#symbol_filter').val() || [];
        const filterAction   = $('#action_filter').val();

        if (!fromDate || !toDate) { alert('Please select both dates'); return; }

        toggleLoading(true, 'Loading signals...');
        analysisData     = [];
        profitCalculated = false;
        $('#calculate_profit').prop('disabled', true);

        $.ajax({
            url: '{{ route("index-oi.analyze-pece") }}', type: 'GET',
            data: { from_date: fromDate, to_date: toDate, symbols: selectedSymbols, filter_action: filterAction },
            success: function (res) {
                if (res.success && res.data && res.data.length > 0) {
                    analysisData = res.data;
                    displayAnalysisTable();
                    updateStatistics();
                    updateAlignedCounts();   // counts only — no profit yet
                    $('#calculate_profit').prop('disabled', false);
                } else {
                    showNoData(res.message || 'No data found');
                    resetStatistics();
                    resetAlignedStats();
                }
                toggleLoading(false);
            },
            error: function () {
                showNoData('Error loading data');
                resetStatistics();
                resetAlignedStats();
                toggleLoading(false);
            }
        });
    }

    // ── Calculate profit ──────────────────────────────────────────────────────
    function calculateProfit() {
        const fromDate        = $('#from_date').val();
        const toDate          = $('#to_date').val();
        const selectedSymbols = $('#symbol_filter').val() || [];
        const filterAction    = $('#action_filter').val();

        if (!fromDate || !toDate) { alert('Please select both dates'); return; }

        toggleLoading(true, 'Calculating profits… This may take a while…');
        $('#calculate_profit').prop('disabled', true);

        $.ajax({
            url: '{{ route("index-oi.calculate-bulk-profit") }}', type: 'POST',
            data: { _token: '{{ csrf_token() }}', from_date: fromDate, to_date: toDate, symbols: selectedSymbols, filter_action: filterAction },
            success: function (res) {
                if (res.success && res.data) {
                    mergeProfitData(res.data);
                    displayAnalysisTable();
                    updateStatisticsWithProfit(res.summary);
                    updateAlignedStats();   // now with profit data
                    profitCalculated = true;
                } else {
                    alert('⚠️ No profit data available');
                }
                toggleLoading(false);
                $('#calculate_profit').prop('disabled', false);
            },
            error: function () {
                alert('❌ Error calculating profit');
                toggleLoading(false);
                $('#calculate_profit').prop('disabled', false);
            }
        });
    }

    function mergeProfitData(profitData) {
        const map = {};
        profitData.forEach(p => { map[`${p.date}_${p.symbol}`] = p; });
        analysisData.forEach(row => {
            const p = map[`${row.date}_${row.symbol}`];
            if (p) Object.assign(row, {
                has_profit_data        : true,
                option_symbol          : p.option_symbol,
                investment             : p.investment,
                entry_price            : p.buy_price,
                exit_price             : p.sell_price,
                highest_price          : p.highest_price,
                profit_loss            : p.profit_loss,
                highest_profit         : p.highest_profit,
                return_percent         : p.return_percent,
                highest_return_percent : p.highest_return_percent,
            });
        });
    }

    // ── Table render ──────────────────────────────────────────────────────────
    function displayAnalysisTable() {
        if (!analysisData || !analysisData.length) return;
        let html = '';

        analysisData.forEach(function (row, index) {

            // Condition
            let condCls = 'condition-flat';
            if (row.oi_condition) {
                if      (row.oi_condition.includes('CE ↑ + PE ↓')) condCls = 'condition-ce-up-pe-down';
                else if (row.oi_condition.includes('CE ↓ + PE ↑')) condCls = 'condition-ce-down-pe-up';
                else if (row.oi_condition.includes('Both ↑'))       condCls = 'condition-both-up';
                else if (row.oi_condition.includes('Both ↓'))       condCls = 'condition-both-down';
            }
            const condBadge = `<span class="${condCls}">${row.oi_condition || 'N/A'}</span>`;

            // Sentiment
            const sentBadge = row.final_sentiment === 'BULLISH'
                ? '<span class="sentiment-strong-bullish">🟢 BULLISH</span>'
                : row.final_sentiment === 'BEARISH'
                    ? '<span class="sentiment-strong-bearish">🔴 BEARISH</span>'
                    : '<span class="sentiment-neutral">⚪ NEUTRAL</span>';

            // 50MA
            const maBadge = getMa50Badge(row.fut_50ma_signal);

            // Action
            const actBadge = row.trade_action === 'BUY CE'
                ? '<span class="action-buy-ce">📈 CE</span>'
                : row.trade_action === 'BUY PE'
                    ? '<span class="action-buy-pe">📉 PE</span>'
                    : '<span class="action-both">⏸️ WAIT</span>';

            // Stronger side
            const strongerBadge = row.stronger_side === 'CE'
                ? '<span class="badge badge-warning" style="font-size:10px;font-weight:700;color:#00bf63;">CE 💪</span>'
                : row.stronger_side === 'PE'
                    ? '<span class="badge badge-info" style="font-size:10px;font-weight:700;color:#fb1d28;">PE 💪</span>'
                    : '<span class="badge badge-secondary" style="font-size:10px;">EQUAL</span>';

            // Strength rank
            let ce = row.ce_oi_change_pct || 0, pe = row.pe_oi_change_pct || 0;
            let diff = Math.abs(ce - pe);
            let rank = diff > 40 ? '1' : diff > 25 ? '2' : diff > 10 ? '3' : diff > 5 ? '4' : '';
            let strengthBadge = '';
            if      (ce < 0 && pe > 0) strengthBadge = rank ? `<span class="signal-strong-bullish">🟢 BULLISH (${rank})</span>` : '<span class="signal-normal">Normal</span>';
            else if (ce > 0 && pe < 0) strengthBadge = rank ? `<span class="signal-strong-bearish">🔴 BEARISH (${rank})</span>` : '<span class="signal-normal">Normal</span>';
            else if (ce > 0 && pe > 0) strengthBadge = diff <= 5 ? '<span class="signal-normal">Normal</span>' : (ce > pe ? `<span class="signal-strong-bearish">🔴 BEARISH (${rank})</span>` : `<span class="signal-strong-bullish">🟢 BULLISH (${rank})</span>`);
            else if (ce < 0 && pe < 0) strengthBadge = diff <= 5 ? '<span class="signal-normal">Normal</span>' : (ce < pe ? `<span class="signal-strong-bullish">🟢 BULLISH (${rank})</span>` : `<span class="signal-strong-bearish">🔴 BEARISH (${rank})</span>`);
            else strengthBadge = '<span class="signal-normal">Normal</span>';

            // OI % colours
            const ceCls  = row.ce_oi_change_pct  > 0 ? 'text-success' : 'text-danger';
            const peCls  = row.pe_oi_change_pct  > 0 ? 'text-success' : 'text-danger';
            const futCls = row.fut_oi_change_pct > 0 ? 'text-success' : 'text-danger';

            // Interpretation
            const interpCls = row.oi_interpretation === 'Put Writing'  ? 'interpretation-put-writing'  :
                              row.oi_interpretation === 'Call Writing' ? 'interpretation-call-writing' :
                              'interpretation-balanced';

            // FUT price
            const futTodayHtml  = row.fut_price_today > 0 ? `<strong>₹${parseFloat(row.fut_price_today).toFixed(2)}</strong>` : '<span class="text-muted">N/A</span>';
            const futPrevHtml   = row.fut_price_prev  > 0 ? `₹${parseFloat(row.fut_price_prev).toFixed(2)}` : '<span class="text-muted">N/A</span>';
            const dCls          = (row.fut_price_change || 0) >= 0 ? 'text-success' : 'text-danger';
            const dpCls         = (row.fut_price_change_pct || 0) >= 0 ? 'text-success' : 'text-danger';
            const futDelta      = `<strong class="${dCls}">${(row.fut_price_change||0)>=0?'+':''}₹${Math.abs(row.fut_price_change||0).toFixed(2)}</strong>`;
            const futDeltaPct   = `<strong class="${dpCls}">${(row.fut_price_change_pct||0)>=0?'+':''}${parseFloat(row.fut_price_change_pct||0).toFixed(2)}%</strong>`;
            const futSigBadge   = row.fut_price_signal === 'BULLISH' ? '<span class="sentiment-strong-bullish">🟢 BULL</span>'
                                : row.fut_price_signal === 'BEARISH' ? '<span class="sentiment-strong-bearish">🔴 BEAR</span>'
                                : row.fut_price_signal === 'NEUTRAL' ? '<span class="sentiment-neutral">⚪ FLAT</span>'
                                : '<span class="text-muted">N/A</span>';

            // Option column
            const optionCol = (row.option_symbol && row.option_symbol !== 'N/A')
                ? `<small>${row.option_symbol}</small>`
                : '<span class="text-muted">—</span>';

            // Profit columns
            let profitCols = '';
            if (row.has_profit_data && row.option_symbol && row.option_symbol !== 'N/A') {
                const epCls = row.profit_loss    >= 0 ? 'text-success' : 'text-danger';
                const hpCls = row.highest_profit >= 0 ? 'text-success' : 'text-danger';
                profitCols = `
                    <td><strong>₹${parseFloat(row.investment||0).toLocaleString()}</strong></td>
                    <td>₹${parseFloat(row.entry_price||0).toFixed(2)}</td>
                    <td>₹${parseFloat(row.exit_price||0).toFixed(2)}</td>
                    <td class="text-info"><strong>₹${parseFloat(row.highest_price||0).toFixed(2)}</strong></td>
                    <td class="${epCls}"><strong>${row.profit_loss>=0?'+':''}₹${parseFloat(row.profit_loss||0).toLocaleString()}</strong></td>
                    <td class="${hpCls}"><strong>${row.highest_profit>=0?'+':''}₹${parseFloat(row.highest_profit||0).toLocaleString()}</strong></td>
                    <td class="${epCls}">${row.return_percent>=0?'+':''}${parseFloat(row.return_percent||0).toFixed(2)}%</td>
                    <td class="${hpCls}">${row.highest_return_percent>=0?'+':''}${parseFloat(row.highest_return_percent||0).toFixed(2)}%</td>
                `;
            } else {
                profitCols = `<td colspan="8" class="text-center text-muted"><small>Click "Calculate P/L"</small></td>`;
            }

            // Row highlight if aligned
            const rowStyle = isAligned(row) ? 'style="background:rgba(0,210,255,0.06); outline:1px solid rgba(0,210,255,0.2);"' : '';

            html += `
            <tr ${rowStyle}>
                <td><strong>${index + 1}</strong>${isAligned(row) ? ' <span title="Aligned" style="color:#00d2ff;font-size:10px;">🎯</span>' : ''}</td>
                <td><strong>${row.date}</strong></td>
                <td><strong style="color:#667eea;">${row.symbol}</strong></td>
                <td><strong>${parseInt(row.ce_oi||0).toLocaleString()}</strong></td>
                <td class="${ceCls}"><strong>${row.ce_oi_change_pct>0?'+':''}${row.ce_oi_change_pct}%</strong></td>
                <td><strong>${parseInt(row.pe_oi||0).toLocaleString()}</strong></td>
                <td class="${peCls}"><strong>${row.pe_oi_change_pct>0?'+':''}${row.pe_oi_change_pct}%</strong></td>
                <td>${strengthBadge}</td>
                <td>${condBadge}</td>
                <td><span class="ratio-badge">${row.pe_ce_ratio}</span></td>
                <td><span class="${interpCls}">${row.oi_interpretation}</span></td>
                <td class="${futCls}"><strong>${row.fut_oi_change_pct>0?'+':''}${row.fut_oi_change_pct}%</strong></td>
                <td>${sentBadge}</td>
                <td>${maBadge}</td>
                <td>${strongerBadge}</td>
                <td>${actBadge}</td>
                <td>${futTodayHtml}</td>
                <td>${futPrevHtml}</td>
                <td>${futDelta}</td>
                <td>${futDeltaPct}</td>
                <td>${futSigBadge}</td>
                <td>${optionCol}</td>
                ${profitCols}
            </tr>`;
        });

        $('#analysis-tbody').html(html);
    }

    // ── Aligned counts (no profit needed) ────────────────────────────────────
    function updateAlignedCounts() {
        const aligned = analysisData.filter(r => isAligned(r));
        const buyCE   = aligned.filter(r => r.trade_action === 'BUY CE').length;
        const buyPE   = aligned.filter(r => r.trade_action === 'BUY PE').length;

        $('#aligned_count').text(aligned.length);
        $('#aligned_buy_ce').text(buyCE);
        $('#aligned_buy_pe').text(buyPE);

        // Investment / P/L only if profit data already merged
        if (profitCalculated) updateAlignedStats();
    }

    // ── Aligned stats (requires profit data) ────────────────────────────────
    function updateAlignedStats() {
        const aligned = analysisData.filter(r => isAligned(r) && r.has_profit_data);
        const count   = aligned.length;

        if (count === 0) {
            $('#aligned_avg_inv,#aligned_total_inv').text('₹0');
            $('#aligned_exit_pl,#aligned_high_pl').text('₹0');
            $('#aligned_exit_roi,#aligned_high_roi').text('0%');
            $('#aligned_win_rate,#aligned_win_rate_high').text('0%');
            $('#aligned_winners').text('0');
            return;
        }

        const totalInv   = aligned.reduce((s, r) => s + (r.investment    || 0), 0);
        const exitPL     = aligned.reduce((s, r) => s + (r.profit_loss   || 0), 0);
        const highPL     = aligned.reduce((s, r) => s + (r.highest_profit|| 0), 0);
        const avgExitRoi = aligned.reduce((s, r) => s + (r.return_percent|| 0), 0) / count;
        const avgHighRoi = aligned.reduce((s, r) => s + (r.highest_return_percent||0), 0) / count;

        const exitWins    = aligned.filter(r => (r.profit_loss    || 0) > 0).length;
        const highWins    = aligned.filter(r => (r.highest_profit || 0) > 0).length;
        const exitWinPct  = ((exitWins / count) * 100).toFixed(1);
        const highWinPct  = ((highWins / count) * 100).toFixed(1);

        const fmt = (v) => (v >= 0 ? '+' : '') + '₹' + Math.abs(v).toFixed(2);
        const fmtR = (v) => (v >= 0 ? '+' : '') + Math.abs(v).toFixed(2) + '%';
        const plCls = (v) => v >= 0 ? 'profit-positive' : 'profit-negative';

        $('#aligned_count').text(count);
        $('#aligned_avg_inv').html(`₹${Math.round(totalInv / count).toLocaleString()}`);
        $('#aligned_total_inv').html(`₹${Math.round(totalInv).toLocaleString()}`);
        $('#aligned_exit_pl').html(`<span class="${plCls(exitPL)}">${fmt(exitPL)}</span>`);
        $('#aligned_exit_roi').html(`<span class="${plCls(avgExitRoi)}">${fmtR(avgExitRoi)}</span>`);
        $('#aligned_high_pl').html(`<span class="${plCls(highPL)}">${fmt(highPL)}</span>`);
        $('#aligned_high_roi').html(`<span class="${plCls(avgHighRoi)}">${fmtR(avgHighRoi)}</span>`);
        $('#aligned_win_rate').html(`<span class="${wCls(exitWinPct)}">${exitWinPct}%</span>`);
        $('#aligned_win_rate_high').html(`<span class="${wCls(highWinPct)}">${highWinPct}%</span>`);
        $('#aligned_winners').text(exitWins);
    }

    function resetAlignedStats() {
        $('#aligned_count,#aligned_buy_ce,#aligned_buy_pe,#aligned_winners').text('0');
        $('#aligned_avg_inv,#aligned_total_inv,#aligned_exit_pl,#aligned_high_pl').text('₹0');
        $('#aligned_exit_roi,#aligned_high_roi,#aligned_win_rate,#aligned_win_rate_high').text('0%');
    }

    // ── Regular stats ─────────────────────────────────────────────────────────
    function updateStatistics() {
        if (!analysisData || !analysisData.length) { resetStatistics(); return; }
        $('#total_records').text(analysisData.length);
        $('#buy_ce_count').text(analysisData.filter(r => r.trade_action === 'BUY CE').length);
        $('#buy_pe_count').text(analysisData.filter(r => r.trade_action === 'BUY PE').length);
        $('#both_count').text(analysisData.filter(r => r.trade_action === 'WAIT').length);
        $('#strong_bullish_count').text(analysisData.filter(r => r.final_sentiment === 'BULLISH').length);
        $('#strong_bearish_count').text(analysisData.filter(r => r.final_sentiment === 'BEARISH').length);
    }

    function updateStatisticsWithProfit(summary) {
        $('#total_investment').text('₹' + summary.total_investment.toLocaleString()).css('color', '#007bff');
        $('#total_exit_profit').text('₹' + summary.total_profit_loss.toLocaleString()).css('color', summary.total_profit_loss >= 0 ? '#28a745' : '#dc3545');
        $('#total_highest_profit').text('₹' + summary.total_highest_profit.toLocaleString()).css('color', summary.total_highest_profit >= 0 ? '#17a2b8' : '#dc3545');
        $('#winning_trades').text(summary.winning_trades);
        $('#exit_roi').text(summary.roi_percent + '%').css('color', summary.roi_percent >= 0 ? '#28a745' : '#dc3545');
        $('#highest_roi').text(summary.highest_roi_percent + '%').css('color', summary.highest_roi_percent >= 0 ? '#17a2b8' : '#dc3545');
    }

    function resetStatistics() {
        $('#total_records,#buy_ce_count,#buy_pe_count,#both_count,#strong_bullish_count,#strong_bearish_count,#winning_trades').text('0');
        $('#total_investment,#total_exit_profit,#total_highest_profit').text('₹0');
        $('#exit_roi,#highest_roi').text('0%');
    }

    function showNoData(message) {
        $('#analysis-tbody').html(`
            <tr><td colspan="30" class="text-center py-5">
                <i class="fas fa-info-circle" style="color:#17a2b8;font-size:3rem;"></i>
                <p class="text-info" style="margin-top:20px;">${message}</p>
            </td></tr>
        `);
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter,#action_filter').val('');
        analysisData     = [];
        profitCalculated = false;
        showNoData('Click "View Data" to load signals');
        resetStatistics();
        resetAlignedStats();
        setTimeout(() => runAnalysis(), 300);
    }

    $('#run_analysis').click(() => runAnalysis());
    $('#calculate_profit').click(() => calculateProfit());
    $('#reset_filters').click(() => resetFilters());
</script>
@endpush