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
    .spinner {
        width:50px; height:50px;
        border:5px solid #f3f3f3; border-top:5px solid #3498db;
        border-radius:50%; animation: spin 1s linear infinite;
    }
    .loading-text { color:white; margin-top:20px; font-size:16px; font-weight:600; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }



    .action-buy-ce { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .action-buy-pe { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .action-both   { background:linear-gradient(135deg,#ffc107,#ff9800); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .sentiment-strong-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-strong-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-neutral        { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .strength-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-normal  { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .ma-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .pivot-above-r3  { background:linear-gradient(135deg,#155724,#28a745); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .pivot-r2-r3     { background:linear-gradient(135deg,#28a745,#5cb85c); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .pivot-r1-r2     { background:linear-gradient(135deg,#5cb85c,#80c780); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .pivot-p-r1      { background:linear-gradient(135deg,#17a2b8,#138496); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .pivot-s1-p      { background:linear-gradient(135deg,#fd7e14,#e55d00); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .pivot-s2-s1     { background:linear-gradient(135deg,#dc3545,#bd2130); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .pivot-s3-s2     { background:linear-gradient(135deg,#9c1221,#dc3545); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .pivot-below-s3  { background:linear-gradient(135deg,#3d0b0b,#9c1221); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .pivot-near      { background:linear-gradient(135deg,#ffc107,#e0a800); color:#212529; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .pivot-na        { background:#e9ecef; color:#6c757d; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .pivot-levels-mini { font-size:9px; color:#aaa; line-height:1.5; }
    .pivot-r-val { color:#28a745; font-weight:700; }
    .pivot-p-val { color:#17a2b8; font-weight:700; }
    .pivot-s-val { color:#dc3545; font-weight:700; }

    .profit-positive { color:#28a745; font-weight:700; font-size:11px; }
    .profit-negative { color:#dc3545; font-weight:700; font-size:11px; }
    .profit-loading  { color:#aaa; font-size:10px; font-style:italic; }

    .filter-section {
        background: linear-gradient(135deg,#667eea,#764ba2);
        padding:20px; border-radius:12px; margin-bottom:20px;
        box-shadow:0 4px 15px rgba(102,126,234,0.4); color:white;
    }
    .filter-section label { color:white !important; font-weight:600; margin-bottom:6px; font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.9); color:#333; font-size:12px; padding:6px 10px; }

    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.1); transition:transform .2s; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#666; font-size:10px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box strong { display:block; font-size:1.4rem; font-weight:700; margin-top:3px; }

    .aligned-section {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        border: 2px solid #00d2ff; border-radius: 14px;
        padding: 16px 20px 8px; margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(0, 210, 255, 0.25);
    }
    .aligned-section-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
    .aligned-section-header h6 { color:#00d2ff; font-size:13px; font-weight:700; margin:0; text-transform:uppercase; letter-spacing:.5px; }
    .aligned-tag { background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:white; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:.5px; }
    .aligned-rule { background:rgba(0,210,255,0.08); border:1px solid rgba(0,210,255,0.25); border-radius:8px; padding:6px 12px; margin-bottom:12px; font-size:10px; color:rgba(255,255,255,0.75); display:flex; gap:20px; flex-wrap:wrap; }
    .aligned-rule span strong { color:#00d2ff; }

    .stats-box-dark { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); padding:10px 8px; border-radius:10px; text-align:center; border-left:4px solid #00d2ff; margin-bottom:12px; transition:transform .2s, border-color .2s; }
    .stats-box-dark:hover { transform:translateY(-2px); border-color:#00d2ff; }
    .stats-box-dark small  { display:block; color:rgba(255,255,255,0.55); font-size:9px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box-dark strong { display:block; font-size:1.1rem; font-weight:700; margin-top:4px; color:white; }
    .stats-box-dark.green  { border-left-color:#28a745; }
    .stats-box-dark.red    { border-left-color:#dc3545; }
    .stats-box-dark.cyan   { border-left-color:#00d2ff; }
    .stats-box-dark.orange { border-left-color:#fd7e14; }
    .stats-box-dark.purple { border-left-color:#6f42c1; }
    .stats-box-dark.gold   { border-left-color:#ffc107; }

    .page-header { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4); }
    .ratio-badge { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:3px 5px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; }
    .new-feature-badge { background:linear-gradient(135deg,#11998e,#38ef7d); color:white; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }

    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .custom--table { min-width:2400px; }

    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position:sticky; z-index:10; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left:0; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left:40px; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left:120px; }

    .condition-ce-up-pe-down { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-ce-down-pe-up { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-both-up       { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-both-down     { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-flat          { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .option-symbol-badge     { color:white; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- Header --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }} <span class="new-feature-badge">9:30 → 12:15</span></h4>
                    <p>OI analysis 9:30→12:15 AM &nbsp;|&nbsp; Profit window: 12:15→3:15 PM &nbsp;|&nbsp; Series-scoped — no mixed expiry data</p>
                </div>
                <div>
                    <a href="{{ route('9to12.config') }}" class="btn btn-light btn-sm mr-2"><i class="fas fa-cog"></i> 9to12 Configs</a>
                    <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm mr-2"><i class="fas fa-chart-bar"></i> EOD Analysis</a>
                    <a href="{{ route('oiiv-auto.index') }}" class="btn btn-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
                </div>
            </div>
        </div>

        {{-- Series loaded automatically — no UI needed --}}

        {{-- Logic Alert --}}
        <div class="alert" style="background:linear-gradient(135deg,#667eea,#764ba2); color:white; border:none; border-radius:12px; margin-bottom:20px; padding:15px;">
            <h6 style="color:white; margin-bottom:10px; font-size:14px;"><i class="fas fa-info-circle"></i> <strong>Logic Summary:</strong></h6>
            <div class="row mb-1">
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>📊 OI Analysis (09:30 → 12:15)</strong></small>
                    <ul style="font-size:10px; margin-top:5px;">
                        <li><strong>CE ↑ + PE ↓</strong> → BEARISH</li>
                        <li><strong>CE ↓ + PE ↑</strong> → BULLISH</li>
                        <li><strong>Both ↑</strong> → CE%>PE% = BEARISH</li>
                        <li><strong>Both ↓</strong> → CE%&lt;PE% = BULLISH</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>⏰ Why 09:30 not 09:15?</strong></small>
                    <ul style="font-size:10px; margin-top:5px;">
                        <li>09:15 candle = pre-open noise</li>
                        <li>09:30 = first stable print</li>
                        <li>More accurate OI baseline</li>
                        <li>Industry standard open candle</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>🏆 Rank = |CE%−PE%|</strong></small>
                    <ul style="font-size:10px; margin-top:5px;">
                        <li>Rank 1 — diff > 40</li>
                        <li>Rank 2 — diff > 25</li>
                        <li>Rank 3 — diff > 10</li>
                        <li>Rank 4 — diff > 5</li>
                        <li>Normal — diff ≤ 5 (skip)</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>📍 Pivot Points (Prev-day FUT)</strong></small>
                    <ul style="font-size:10px; margin-top:5px;">
                        <li><strong style="color:#ffc107;">Near Xx</strong> = within 0.3%</li>
                        <li><strong style="color:#28a745;">R zones</strong> = resistance above pivot</li>
                        <li><strong style="color:#17a2b8;">P–R1</strong> = between pivot and R1</li>
                        <li><strong style="color:#dc3545;">S zones</strong> = support below pivot</li>
                        <li>Formula: (H+L+C)/3 of prev day</li>
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
                    <small style="color:rgba(255,255,255,0.8); font-size:10px;">Leave empty for all</small>
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
                    <button type="button" id="run_analysis" class="btn btn-light btn-lg" style="min-width:150px; font-size:13px;">
                        <i class="fas fa-search"></i> View Data
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg" style="min-width:150px; font-size:13px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        {{-- Stats Row 1 --}}
        <div class="row">
            <div class="col-md-2"><div class="stats-box"><small>Total Records</small><strong id="total_records" class="text-dark">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE</small><strong id="buy_ce_count" style="color:#28a745;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE</small><strong id="buy_pe_count" style="color:#dc3545;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>WAIT</small><strong id="both_count" style="color:#ffc107;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BULLISH</small><strong id="strong_bullish_count" style="color:#28a745;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BEARISH</small><strong id="strong_bearish_count" style="color:#dc3545;">0</strong></div></div>
        </div>

        {{-- Stats Row 2 --}}
        <div class="row mb-3">
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#6c757d;"><small>Avg Investment</small><strong id="avg_investment" class="text-dark" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>📈 High Trades</small><strong id="high_trades" style="color:#17a2b8; font-size:1rem;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>📈 High Total P/L</small><strong id="high_total_pl" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>📈 Avg ROI</small><strong id="high_avg_roi" style="color:#17a2b8; font-size:1rem;">0%</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#fd7e14;"><small>📉 Low Trades</small><strong id="low_trades" style="color:#fd7e14; font-size:1rem;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#fd7e14;"><small>📉 Low Total P/L</small><strong id="low_total_pl" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#fd7e14;"><small>📉 Avg ROI</small><strong id="low_avg_roi" style="color:#fd7e14; font-size:1rem;">0%</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#6f42c1;"><small>🕒 EOD Trades</small><strong id="eod_trades" style="color:#6f42c1; font-size:1rem;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#6f42c1;"><small>🕒 EOD Total P/L</small><strong id="eod_total_pl" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#6f42c1;"><small>🕒 Avg ROI</small><strong id="eod_avg_roi" style="color:#6f42c1; font-size:1rem;">0%</strong></div></div>
        </div>

        {{-- Aligned Signals --}}
        <div class="aligned-section">
            <div class="aligned-section-header">
                <span style="font-size:20px;">🎯</span>
                <h6>Aligned Signals Only — Sentiment + 50MA Confirmed</h6>
                <span class="aligned-tag">High Confidence</span>
            </div>
            <div class="aligned-rule">
                <span>✅ <strong>BUY CE Confirmed</strong> = Sentiment BULLISH + 50MA Above MA</span>
                <span>✅ <strong>BUY PE Confirmed</strong> = Sentiment BEARISH + 50MA Below MA</span>
                <span style="color:rgba(255,255,255,0.45);">❌ Mismatched signals excluded</span>
            </div>
            <div class="row">
                <div class="col-6 col-md-2"><div class="stats-box-dark cyan"><small>🎯 Aligned Total</small><strong id="aligned_count">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark green"><small>📈 BUY CE Aligned</small><strong id="aligned_buy_ce" style="color:#28a745;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark red"><small>📉 BUY PE Aligned</small><strong id="aligned_buy_pe" style="color:#dc3545;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark gold"><small>💰 Avg Investment</small><strong id="aligned_avg_inv" style="color:#ffc107;">₹0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#adb5bd;"><small>📊 Win Rate (High)</small><strong id="aligned_win_rate" style="color:#adb5bd;">0%</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#adb5bd;"><small>📊 Win Rate (EOD)</small><strong id="aligned_win_rate_eod" style="color:#adb5bd;">0%</strong></div></div>
            </div>
            <div class="row">
                <div class="col-6 col-md-2"><div class="stats-box-dark cyan"><small>📈 High Total P/L</small><strong id="aligned_high_pl">₹0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark cyan"><small>📈 High Avg ROI</small><strong id="aligned_high_roi">0%</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark orange"><small>📉 Low Total P/L</small><strong id="aligned_low_pl">₹0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark orange"><small>📉 Low Avg ROI</small><strong id="aligned_low_roi">0%</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark purple"><small>🕒 EOD Total P/L</small><strong id="aligned_eod_pl">₹0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark purple"><small>🕒 EOD Avg ROI</small><strong id="aligned_eod_roi">0%</strong></div></div>
            </div>
        </div>

        {{-- Table --}}
        <div style="position:relative; min-height:400px;">
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
                            <th>FUT OI</th>
                            <th>FUT %</th>
                            <th>Condition</th>
                            <th>Sentiment</th>
                            <th>50MA</th>
                            <th>MA Fresh</th>
                            <th>Strong</th>
                            <th>Strength</th>
                            <th>Action</th>
                            <th>Ratio</th>
                            <th>Pivot Zone</th>
                            <th>R3 / R2 / R1</th>
                            <th>Pivot</th>
                            <th>S1 / S2 / S3</th>
                            <th>Option</th>
                            <th>Investment</th>
                            <th>Buy ₹</th>
                            <th>High ₹</th>
                            <th>High P/L</th>
                            <th>High ROI%</th>
                            <th>Low ₹</th>
                            <th>Low P/L</th>
                            <th>Low ROI%</th>
                            <th>EOD ₹</th>
                            <th>EOD P/L</th>
                            <th>EOD ROI%</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="33" class="text-center py-5">
                                <i class="fas fa-chart-pie" style="font-size:3rem; opacity:0.5;"></i>
                                <p style="font-size:1.1rem; margin-top:20px;">Loading latest series data...</p>
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
    let analysisData  = [];
    let activeSeries  = null;   // resolved automatically from server (latest month's last expiry)

    // ── Loading helper ────────────────────────────────────────────────────────
    function toggleLoading(show, message = 'Loading data...') {
        if (show) { $('#loading-overlay .loading-text').text(message); $('#loading-overlay').show(); }
        else       { $('#loading-overlay').hide(); }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ANALYSIS — series resolved automatically, no UI selector
    // ══════════════════════════════════════════════════════════════════════════

    $(document).ready(function () {
        loadSymbols();
        // Fetch current series then immediately run analysis
        $.ajax({
            url : '{{ route("9to12.series") }}',
            type: 'GET',
            success: function (res) {
                if (res.success && res.current_series) {
                    activeSeries = res.current_series;
                    runAnalysis();
                }
            }
        });
    });

    function loadSymbols() {
        $.ajax({
            url: '{{ route("9to12.symbols") }}', type: 'GET',
            success: function (res) {
                if (res.success) {
                    let opts = '';
                    res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
                    $('#symbol_filter').html(opts);
                }
            }
        });
    }

    function runAnalysis() {
        if (!activeSeries) { alert('Please select a series first'); return; }

        const fromDate = $('#from_date').val();
        const toDate   = $('#to_date').val();
        const symbols  = $('#symbol_filter').val() || [];
        const action   = $('#action_filter').val();

        if (!fromDate || !toDate) { alert('Please select both dates'); return; }

        toggleLoading(true, 'Loading latest series data...');
        analysisData = [];

        $.ajax({
            url : '{{ route("9to12.analyze-pece") }}',
            type: 'GET',
            data: {
                from_date     : fromDate,
                to_date       : toDate,
                symbols       : symbols,
                filter_action : action,
                series_expiry : activeSeries,
            },
            success: function (res) {
                if (res.success && res.data && res.data.length > 0) {
                    analysisData = res.data;
                    displayAnalysisTable();
                    updateStatistics();
                } else {
                    showNoData(res.message || 'No data found for this series / date range');
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

    // ── Badges ───────────────────────────────────────────────────────────────
    function getStrengthBadge(strengthRank, sentiment) {
        if (strengthRank === 'Normal') return `<span class="strength-normal">Normal</span>`;
        const n    = strengthRank ? strengthRank.replace('Rank ', '') : '';
        const bull = sentiment === 'BULLISH';
        return `<span class="${bull ? 'strength-bullish' : 'strength-bearish'}">${bull ? '🟢' : '🔴'} ${bull ? 'BULLISH' : 'BEARISH'} (${n})</span>`;
    }

    function getStrongerBadge(side) {
        if (side === 'CLEAR') return '<span class="text-muted" style="font-size:13px;font-weight:600;">—</span>';
        if (side === 'CE')    return '<span class="badge badge-warning" style="font-size:10px;font-weight:700;color:#00bf63;">CE 💪</span>';
        if (side === 'PE')    return '<span class="badge badge-info"    style="font-size:10px;font-weight:700;color:#fb1d28;">PE 💪</span>';
        return '<span class="badge badge-secondary" style="font-size:10px;">EQUAL</span>';
    }

    function getMa50Badge(signal) {
        if (!signal || signal === 'N/A') return '<span class="text-muted" style="font-size:11px;">N/A</span>';
        if (signal === 'BULLISH') return '<span class="ma-bullish">🟢 Above MA</span>';
        if (signal === 'BEARISH') return '<span class="ma-bearish">🔴 Below MA</span>';
        return '<span class="ma-neutral">⚪ On MA</span>';
    }

    function getMaFreshBadge(freshness, signal) {
        if (!freshness || freshness === 'N/A')
            return '<span class="text-muted" style="font-size:11px;">N/A</span>';
        if (freshness === 'FRESH') {
            const color = signal === 'BULLISH' ? '#28a745' : '#dc3545';
            return `<span style="background:linear-gradient(135deg,${color},${signal === 'BULLISH' ? '#20c997' : '#c82333'});color:white;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block;">✨ FRESH</span>`;
        }
        return '<span style="background:#6c757d;color:white;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block;">🕐 OLD</span>';
    }

    function getPivotPositionBadge(pos) {
        if (!pos || pos === 'N/A') return '<span class="pivot-na">N/A</span>';
        if (pos.startsWith('Near'))  return `<span class="pivot-near">⚡ ${pos}</span>`;
        if (pos === 'Above R3')      return `<span class="pivot-above-r3">▲ Above R3</span>`;
        if (pos === 'R2–R3')         return `<span class="pivot-r2-r3">📈 R2–R3</span>`;
        if (pos === 'R1–R2')         return `<span class="pivot-r1-r2">📈 R1–R2</span>`;
        if (pos === 'P–R1')          return `<span class="pivot-p-r1">〰 P–R1</span>`;
        if (pos === 'S1–P')          return `<span class="pivot-s1-p">📉 S1–P</span>`;
        if (pos === 'S2–S1')         return `<span class="pivot-s2-s1">📉 S2–S1</span>`;
        if (pos === 'S3–S2')         return `<span class="pivot-s3-s2">📉 S3–S2</span>`;
        if (pos === 'Below S3')      return `<span class="pivot-below-s3">▼ Below S3</span>`;
        return `<span class="pivot-na">${pos}</span>`;
    }

    function getPivotResistanceLevels(row) {
        if (!row.r1 && !row.r2 && !row.r3) return '<span class="text-muted" style="font-size:9px;">N/A</span>';
        return `<div class="pivot-levels-mini">
            <span class="pivot-r-val">R3: ${row.r3 ? Number(row.r3).toFixed(0) : '—'}</span><br>
            <span class="pivot-r-val">R2: ${row.r2 ? Number(row.r2).toFixed(0) : '—'}</span><br>
            <span class="pivot-r-val">R1: ${row.r1 ? Number(row.r1).toFixed(0) : '—'}</span>
        </div>`;
    }

    function getPivotLevel(row) {
        if (!row.pivot) return '<span class="text-muted" style="font-size:9px;">N/A</span>';
        return `<strong class="pivot-p-val" style="font-size:11px;">P: ${Number(row.pivot).toFixed(0)}</strong>`;
    }

    function getPivotSupportLevels(row) {
        if (!row.s1 && !row.s2 && !row.s3) return '<span class="text-muted" style="font-size:9px;">N/A</span>';
        return `<div class="pivot-levels-mini">
            <span class="pivot-s-val">S1: ${row.s1 ? Number(row.s1).toFixed(0) : '—'}</span><br>
            <span class="pivot-s-val">S2: ${row.s2 ? Number(row.s2).toFixed(0) : '—'}</span><br>
            <span class="pivot-s-val">S3: ${row.s3 ? Number(row.s3).toFixed(0) : '—'}</span>
        </div>`;
    }

    function isAligned(row) {
        return (row.final_sentiment === 'BULLISH' && row.fut_50ma_signal === 'BULLISH') ||
               (row.final_sentiment === 'BEARISH' && row.fut_50ma_signal === 'BEARISH');
    }

    function isAlignedFresh(row) {
        return isAligned(row) && row.fut_50ma_freshness === 'FRESH';
    }

    // ── Profit cell renderer ─────────────────────────────────────────────────
    function applyProfitToRow(item) {
        const idx = item.index;

        if (item.error === 'WAIT') {
            const dash = '<span class="text-muted" style="font-size:10px;">WAIT</span>';
            $(`.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx},.pc-eod-${idx},.pc-eod-pl-${idx},.pc-eod-roi-${idx}`).html(dash);
            return;
        }

        if (item.error) {
            const errBadge = `<span class="badge badge-warning" style="font-size:9px;" title="${item.error}">⚠ ${item.error}</span>`;
            $(`.pc-option-${idx}`).html(item.option_symbol ? `<span class="option-symbol-badge">${item.option_symbol}</span>` : errBadge);
            $(`.pc-invest-${idx},.pc-buy-${idx},.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx},.pc-eod-${idx},.pc-eod-pl-${idx},.pc-eod-roi-${idx}`).html(errBadge);
            return;
        }

        const plHtml  = v => { const c = v >= 0 ? 'profit-positive' : 'profit-negative'; return `<strong class="${c}">${v >= 0 ? '+' : ''}₹${Math.abs(v).toFixed(2)}</strong>`; };
        const roiHtml = v => { const c = v >= 0 ? 'profit-positive' : 'profit-negative'; return `<strong class="${c}">${v >= 0 ? '+' : ''}${Math.abs(v).toFixed(2)}%</strong>`; };

        $(`.pc-option-${idx}`).html(item.option_symbol ? `<span class="option-symbol-badge" title="${item.option_symbol}">${item.option_symbol}</span>` : '<span class="text-muted">N/A</span>');
        $(`.pc-invest-${idx}`).html(`<strong>₹${Number(item.investment).toLocaleString()}</strong>`);
        $(`.pc-buy-${idx}`).html(`<strong>₹${Number(item.buy_price).toFixed(2)}</strong>`);
        $(`.pc-high-${idx}`).html(item.high_price > 0 ? `<strong style="color:#17a2b8;">₹${Number(item.high_price).toFixed(2)}</strong>${item.high_time ? `<br><small style="color:#6c757d;font-size:9px;">${item.high_time}</small>` : ''}` : '<span class="text-muted">—</span>');
        $(`.pc-high-pl-${idx}`).html(plHtml(item.high_pl || 0));
        $(`.pc-high-roi-${idx}`).html(roiHtml(item.high_roi || 0));
        $(`.pc-low-${idx}`).html(item.low_price > 0 ? `<strong style="color:#fd7e14;">₹${Number(item.low_price).toFixed(2)}</strong>${item.low_time ? `<br><small style="color:#6c757d;font-size:9px;">${item.low_time}</small>` : ''}` : '<span class="text-muted">—</span>');
        $(`.pc-low-pl-${idx}`).html(plHtml(item.low_pl || 0));
        $(`.pc-low-roi-${idx}`).html(roiHtml(item.low_roi || 0));
        $(`.pc-eod-${idx}`).html(item.eod_price > 0 ? `<strong style="color:#6f42c1;">₹${Number(item.eod_price).toFixed(2)}</strong>` : '<span class="text-muted">—</span>');
        $(`.pc-eod-pl-${idx}`).html(plHtml(item.eod_pl || 0));
        $(`.pc-eod-roi-${idx}`).html(roiHtml(item.eod_roi || 0));
    }

    function renderNoProfitRow(idx) {
        const dash = '<span class="text-muted">—</span>';
        $(`.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx},.pc-eod-${idx},.pc-eod-pl-${idx},.pc-eod-roi-${idx}`).html(dash);
    }

    // ── Profit AJAX ──────────────────────────────────────────────────────────
    function loadProfitData() {
        const signals = analysisData
            .map((row, idx) => ({
                index         : idx,
                date          : row.date,
                symbol        : row.symbol,
                trade_action  : row.trade_action,
                spot_price    : row.fut_price_today || row.spot_price || 0,
                series_expiry : row.series_expiry,
            }))
            .filter(r => r.trade_action === 'BUY CE' || r.trade_action === 'BUY PE');

        analysisData.forEach((row, idx) => {
            if (row.trade_action !== 'BUY CE' && row.trade_action !== 'BUY PE') renderNoProfitRow(idx);
        });

        if (signals.length === 0) { resetAlignedStats(); return; }

        $.ajax({
            url : '{{ route("9to12.profit") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', signals: signals },
            success: function (res) {
                if (res.success && res.data) {
                    const returned = new Set(res.data.map(d => Number(d.index)));
                    res.data.forEach(item => { item.index = Number(item.index); applyProfitToRow(item); });
                    signals.forEach(s => { if (!returned.has(s.index)) renderNoProfitRow(s.index); });
                    updateProfitStats(res.data);
                } else {
                    signals.forEach(s => renderNoProfitRow(s.index));
                    resetAlignedStats();
                }
            },
            error: function () {
                signals.forEach(s => renderNoProfitRow(s.index));
                resetAlignedStats();
            }
        });
    }

    function updateProfitStats(profitData) {
        const trades = profitData.filter(d => !d.error || d.error === null);
        const count  = trades.length;
        if (count === 0) return;

        const avgInv      = trades.reduce((s, d) => s + (d.investment || 0), 0) / count;
        const highTotalPL = trades.reduce((s, d) => s + (d.high_pl  || 0), 0);
        const highAvgRoi  = trades.reduce((s, d) => s + (d.high_roi || 0), 0) / count;
        const lowTotalPL  = trades.reduce((s, d) => s + (d.low_pl   || 0), 0);
        const lowAvgRoi   = trades.reduce((s, d) => s + (d.low_roi  || 0), 0) / count;
        const eodTotalPL  = trades.reduce((s, d) => s + (d.eod_pl   || 0), 0);
        const eodAvgRoi   = trades.reduce((s, d) => s + (d.eod_roi  || 0), 0) / count;

        const fmt    = v => (v >= 0 ? '+' : '') + '₹' + Math.abs(v).toFixed(2);
        const fmtRoi = v => (v >= 0 ? '+' : '') + Math.abs(v).toFixed(2) + '%';
        const plCls  = v => v >= 0 ? 'profit-positive' : 'profit-negative';

        $('#avg_investment').html(`₹${Math.round(avgInv).toLocaleString()}`);
        $('#high_trades').text(count);
        $('#high_total_pl').html(`<span class="${plCls(highTotalPL)}">${fmt(highTotalPL)}</span>`);
        $('#high_avg_roi').html(`<span class="${plCls(highAvgRoi)}">${fmtRoi(highAvgRoi)}</span>`);
        $('#low_trades').text(count);
        $('#low_total_pl').html(`<span class="${plCls(lowTotalPL)}">${fmt(lowTotalPL)}</span>`);
        $('#low_avg_roi').html(`<span class="${plCls(lowAvgRoi)}">${fmtRoi(lowAvgRoi)}</span>`);
        $('#eod_trades').text(count);
        $('#eod_total_pl').html(`<span class="${plCls(eodTotalPL)}">${fmt(eodTotalPL)}</span>`);
        $('#eod_avg_roi').html(`<span class="${plCls(eodAvgRoi)}">${fmtRoi(eodAvgRoi)}</span>`);

        const alignedTrades = trades.filter(d => { const row = analysisData[d.index]; return row && isAligned(row); });
        updateAlignedStats(alignedTrades);
    }

    function updateAlignedStats(alignedTrades) {
        const count = alignedTrades.length;
        if (count === 0) { resetAlignedStats(); return; }

        const buyCE = alignedTrades.filter(d => { const r = analysisData[d.index]; return r && r.trade_action === 'BUY CE'; }).length;
        const buyPE = alignedTrades.filter(d => { const r = analysisData[d.index]; return r && r.trade_action === 'BUY PE'; }).length;

        const avgInv      = alignedTrades.reduce((s, d) => s + (d.investment || 0), 0) / count;
        const highTotalPL = alignedTrades.reduce((s, d) => s + (d.high_pl  || 0), 0);
        const highAvgRoi  = alignedTrades.reduce((s, d) => s + (d.high_roi || 0), 0) / count;
        const lowTotalPL  = alignedTrades.reduce((s, d) => s + (d.low_pl   || 0), 0);
        const lowAvgRoi   = alignedTrades.reduce((s, d) => s + (d.low_roi  || 0), 0) / count;
        const eodTotalPL  = alignedTrades.reduce((s, d) => s + (d.eod_pl   || 0), 0);
        const eodAvgRoi   = alignedTrades.reduce((s, d) => s + (d.eod_roi  || 0), 0) / count;
        const highWinPct  = ((alignedTrades.filter(d => (d.high_pl || 0) > 0).length / count) * 100).toFixed(1);
        const eodWinPct   = ((alignedTrades.filter(d => (d.eod_pl  || 0) > 0).length / count) * 100).toFixed(1);

        const fmt    = v => (v >= 0 ? '+' : '') + '₹' + Math.abs(v).toFixed(2);
        const fmtRoi = v => (v >= 0 ? '+' : '') + Math.abs(v).toFixed(2) + '%';
        const plCls  = v => v >= 0 ? 'profit-positive' : 'profit-negative';
        const wCls   = p => parseFloat(p) >= 50 ? 'profit-positive' : 'profit-negative';

        $('#aligned_count').text(count);
        $('#aligned_buy_ce').text(buyCE);
        $('#aligned_buy_pe').text(buyPE);
        $('#aligned_avg_inv').html(`₹${Math.round(avgInv).toLocaleString()}`);
        $('#aligned_win_rate').html(`<span class="${wCls(highWinPct)}">${highWinPct}%</span>`);
        $('#aligned_win_rate_eod').html(`<span class="${wCls(eodWinPct)}">${eodWinPct}%</span>`);
        $('#aligned_high_pl').html(`<span class="${plCls(highTotalPL)}">${fmt(highTotalPL)}</span>`);
        $('#aligned_high_roi').html(`<span class="${plCls(highAvgRoi)}">${fmtRoi(highAvgRoi)}</span>`);
        $('#aligned_low_pl').html(`<span class="${plCls(lowTotalPL)}">${fmt(lowTotalPL)}</span>`);
        $('#aligned_low_roi').html(`<span class="${plCls(lowAvgRoi)}">${fmtRoi(lowAvgRoi)}</span>`);
        $('#aligned_eod_pl').html(`<span class="${plCls(eodTotalPL)}">${fmt(eodTotalPL)}</span>`);
        $('#aligned_eod_roi').html(`<span class="${plCls(eodAvgRoi)}">${fmtRoi(eodAvgRoi)}</span>`);
    }

    function resetAlignedStats() {
        $('#aligned_count,#aligned_buy_ce,#aligned_buy_pe').text('0');
        $('#aligned_avg_inv').text('₹0');
        $('#aligned_win_rate,#aligned_win_rate_eod').text('0%');
        $('#aligned_high_pl,#aligned_low_pl,#aligned_eod_pl').text('₹0');
        $('#aligned_high_roi,#aligned_low_roi,#aligned_eod_roi').text('0%');
    }

    // ── Main table render ────────────────────────────────────────────────────
    function displayAnalysisTable() {
        if (!analysisData || analysisData.length === 0) return;
        let html = '';

        analysisData.forEach(function (row, i) {
            let condCls = 'condition-flat';
            if (row.oi_condition) {
                if      (row.oi_condition.includes('CE ↑ + PE ↓')) condCls = 'condition-ce-up-pe-down';
                else if (row.oi_condition.includes('CE ↓ + PE ↑')) condCls = 'condition-ce-down-pe-up';
                else if (row.oi_condition.includes('Both ↑'))       condCls = 'condition-both-up';
                else if (row.oi_condition.includes('Both ↓'))       condCls = 'condition-both-down';
            }
            const condBadge = row.oi_condition ? `<span class="${condCls}">${row.oi_condition}</span>` : '<span class="condition-flat">N/A</span>';
            const sentBadge = row.final_sentiment === 'BULLISH' ? '<span class="sentiment-strong-bullish">🟢 BULLISH</span>'
                            : row.final_sentiment === 'BEARISH' ? '<span class="sentiment-strong-bearish">🔴 BEARISH</span>'
                            : '<span class="sentiment-neutral">⚪ NEUTRAL</span>';
            const actBadge  = row.trade_action === 'BUY CE' ? '<span class="action-buy-ce">📈 CE</span>'
                            : row.trade_action === 'BUY PE' ? '<span class="action-buy-pe">📉 PE</span>'
                            : '<span class="action-both">⏸️ WAIT</span>';
            const rowStyle  = isAligned(row) ? 'style="background:rgba(0,210,255,0.06); outline:1px solid rgba(0,210,255,0.25);"' : '';
            const ceCls     = row.ce_oi_change_pct  > 0 ? 'text-success' : 'text-danger';
            const peCls     = row.pe_oi_change_pct  > 0 ? 'text-success' : 'text-danger';
            const futCls    = row.fut_oi_change_pct > 0 ? 'text-success' : 'text-danger';

            html += `
            <tr ${rowStyle}>
                <td><strong>${i + 1}</strong>${isAligned(row) ? ` <span title="${isAlignedFresh(row) ? 'Aligned + Fresh' : 'Aligned'}" style="color:#00d2ff;font-size:10px;">${isAlignedFresh(row) ? '🎯✨' : '🎯'}</span>` : ''}</td>
                <td><strong>${row.date}</strong></td>
                <td><strong style="color:#667eea;">${row.symbol}</strong></td>
                <td><strong>${fmtOI(row.ce_oi)}</strong><br><small style="color:#aaa;font-size:9px;">${(row.ce_oi||0).toLocaleString()}</small></td>
                <td class="${ceCls}"><strong>${row.ce_oi_change_pct > 0 ? '+' : ''}${Number(row.ce_oi_change_pct).toFixed(2)}%</strong></td>
                <td><strong>${fmtOI(row.pe_oi)}</strong><br><small style="color:#aaa;font-size:9px;">${(row.pe_oi||0).toLocaleString()}</small></td>
                <td class="${peCls}"><strong>${row.pe_oi_change_pct > 0 ? '+' : ''}${Number(row.pe_oi_change_pct).toFixed(2)}%</strong></td>
                <td><strong>${fmtOI(row.fut_oi)}</strong><br><small style="color:#aaa;font-size:9px;">${(row.fut_oi||0).toLocaleString()}</small></td>
                <td class="${futCls}"><strong>${row.fut_oi_change_pct > 0 ? '+' : ''}${Number(row.fut_oi_change_pct).toFixed(2)}%</strong></td>
                <td>${condBadge}</td>
                <td>${sentBadge}</td>
                <td>${getMa50Badge(row.fut_50ma_signal)}</td>
                <td>${getMaFreshBadge(row.fut_50ma_freshness, row.fut_50ma_signal)}</td>
                <td>${getStrongerBadge(row.stronger_side)}</td>
                <td>${getStrengthBadge(row.strength_rank, row.final_sentiment)}</td>
                <td>${actBadge}</td>
                <td><span class="ratio-badge">${row.pe_ce_ratio}</span></td>
                <td>${getPivotPositionBadge(row.pivot_position)}</td>
                <td>${getPivotResistanceLevels(row)}</td>
                <td>${getPivotLevel(row)}</td>
                <td>${getPivotSupportLevels(row)}</td>
                <td class="pc-option-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-invest-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-buy-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-high-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-high-pl-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-high-roi-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-low-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-low-pl-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-low-roi-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-eod-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-eod-pl-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-eod-roi-${i}"><span class="profit-loading">…</span></td>
            </tr>`;
        });

        $('#analysis-tbody').html(html);
        loadProfitData();
    }

    function fmtOI(val) {
        const n = Number(val) || 0;
        if (n >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
        if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
        return n.toString();
    }

    function updateStatistics() {
        if (!analysisData || !analysisData.length) { resetStatistics(); return; }
        $('#total_records').text(analysisData.length);
        $('#buy_ce_count').text(analysisData.filter(r => r.trade_action === 'BUY CE').length);
        $('#buy_pe_count').text(analysisData.filter(r => r.trade_action === 'BUY PE').length);
        $('#both_count').text(analysisData.filter(r => r.trade_action === 'WAIT').length);
        $('#strong_bullish_count').text(analysisData.filter(r => r.final_sentiment === 'BULLISH').length);
        $('#strong_bearish_count').text(analysisData.filter(r => r.final_sentiment === 'BEARISH').length);
    }

    function resetStatistics() {
        $('#total_records,#buy_ce_count,#buy_pe_count,#both_count,#strong_bullish_count,#strong_bearish_count').text('0');
    }

    function showNoData(message) {
        $('#analysis-tbody').html(`<tr><td colspan="33" class="text-center py-5">
            <i class="fas fa-info-circle" style="color:#17a2b8; font-size:3rem;"></i>
            <p class="text-info" style="margin-top:20px;">${message}</p>
        </td></tr>`);
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter,#action_filter').val('');
        analysisData = [];
        resetStatistics();
        resetAlignedStats();
        runAnalysis();
    }

    $('#run_analysis').click(() => runAnalysis());
    $('#reset_filters').click(() => resetFilters());
</script>
@endpush