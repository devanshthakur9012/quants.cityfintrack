@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ── Base table ─────────────────────────────────────── */
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

    /* ── Loading overlay ────────────────────────────────── */
    .loading-overlay {
        position: absolute; top:0; left:0; right:0; bottom:0;
        background: rgba(19,45,57,0.95);
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        z-index: 1000; border-radius: 12px;
    }
    .spinner { width:50px; height:50px; border:5px solid #f3f3f3; border-top:5px solid #3498db; border-radius:50%; animation: spin 1s linear infinite; }
    .loading-text { color:white; margin-top:20px; font-size:16px; font-weight:600; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    /* ── Action badges ──────────────────────────────────── */
    .action-buy-ce { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .action-buy-pe { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .action-wait   { background:linear-gradient(135deg,#ffc107,#ff9800); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Sentiment badges ───────────────────────────────── */
    .sentiment-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Strength badges ────────────────────────────────── */
    .strength-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-normal  { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── MA badges ──────────────────────────────────────── */
    .ma-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Exit badge ─────────────────────────────────────── */
    .exit-badge { background:linear-gradient(135deg,#a855f7,#7c3aed); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── P/L ────────────────────────────────────────────── */
    .profit-positive { color:#28a745; font-weight:700; font-size:11px; }
    .profit-negative { color:#dc3545; font-weight:700; font-size:11px; }
    .profit-loading  { color:#aaa; font-size:10px; font-style:italic; }

    /* ── Filter section ─────────────────────────────────── */
    .filter-section {
        background: linear-gradient(135deg,#667eea,#764ba2);
        padding:20px; border-radius:12px; margin-bottom:20px;
        box-shadow:0 4px 15px rgba(102,126,234,0.4); color:white;
    }
    .filter-section label { color:white !important; font-weight:600; margin-bottom:6px; font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.9); color:#333; font-size:12px; padding:6px 10px; }

    /* ── Stats boxes ────────────────────────────────────── */
    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.1); }
    .stats-box small  { display:block; color:#666; font-size:10px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box strong { display:block; font-size:1.4rem; font-weight:700; margin-top:3px; }

    /* ── Page header ────────────────────────────────────── */
    .page-header { background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(0,0,0,0.4); border:1px solid rgba(0,229,255,0.2); }
    .page-header h4 { color:#00e5ff; }
    .new-feature-badge { background:linear-gradient(135deg,#00e5ff,#2979ff); color:#000; padding:2px 8px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }

    /* ── Strike breakdown mini-table ────────────────────── */
    .strike-breakdown { font-size:9px; white-space:nowrap; }
    .strike-row { display:flex; gap:6px; justify-content:center; align-items:center; margin-bottom:2px; }
    .strike-pill {
        background:rgba(102,126,234,0.12); border:1px solid rgba(102,126,234,0.3);
        border-radius:4px; padding:2px 6px;
        font-size:9px; font-weight:700; color:#667eea;
        display:inline-block;
    }
    .strike-pill.ce-pill { background:rgba(220,53,69,0.1); border-color:rgba(220,53,69,0.3); color:#dc3545; }
    .strike-pill.pe-pill { background:rgba(40,167,69,0.1); border-color:rgba(40,167,69,0.3); color:#28a745; }
    .oi-mini { font-size:9px; color:#888; }

    /* ── Sticky cols ────────────────────────────────────── */
    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .custom--table { min-width:2200px; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position:sticky; z-index:10; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left:0; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left:40px; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left:120px; }

    /* ── OI condition badges ────────────────────────────── */
    .condition-ce-up-pe-down { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-ce-down-pe-up { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-both-up       { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-both-down     { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-flat          { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .option-symbol-badge     { color:white; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }

    /* ── ATM badge ──────────────────────────────────────── */
    .atm-badge { background:linear-gradient(135deg,#00e5ff22,#2979ff22); border:1px solid rgba(0,229,255,0.4); color:#00e5ff; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Exit column highlight ──────────────────────────── */
    .th-exit { background:rgba(168,85,247,0.15); color:#a855f7 !important; }
    .ratio-badge { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:3px 5px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; }

    /* ── Aligned section ────────────────────────────────── */
    .aligned-section {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        border: 2px solid #00d2ff; border-radius: 14px;
        padding: 16px 20px 8px; margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(0,210,255,0.25);
    }
    .aligned-section-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
    .aligned-section-header h6 { color:#00d2ff; font-size:13px; font-weight:700; margin:0; text-transform:uppercase; letter-spacing:.5px; }
    .stats-box-dark { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); padding:10px 8px; border-radius:10px; text-align:center; border-left:4px solid #00d2ff; margin-bottom:12px; }
    .stats-box-dark small  { display:block; color:rgba(255,255,255,0.55); font-size:9px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box-dark strong { display:block; font-size:1.1rem; font-weight:700; margin-top:4px; color:white; }
    .stats-box-dark.green  { border-left-color:#28a745; }
    .stats-box-dark.red    { border-left-color:#dc3545; }
    .stats-box-dark.purple { border-left-color:#6f42c1; }
    .stats-box-dark.cyan   { border-left-color:#00d2ff; }
    .stats-box-dark.orange { border-left-color:#fd7e14; }
    .stats-box-dark.gold   { border-left-color:#ffc107; }

    /* ── Gann / Price badges ────────────────────────────── */
    .mm-no-trap { color:#aaa; font-size:9px; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ────────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>
                    ⚡ Near Strike OI Analysis
                    <span class="new-feature-badge">ATM −5 / −4 / +4 / +5 Only</span>
                </h4>
                <p style="color:rgba(255,255,255,0.7); font-size:12px; margin:4px 0 0;">
                    CE OI = sum of (ATM+4) + (ATM+5) strikes &nbsp;|&nbsp;
                    PE OI = sum of (ATM−4) + (ATM−5) strikes &nbsp;|&nbsp;
                    Compare today 14:45 vs prev 15:00
                </p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-bar"></i> All-Strike EOD</a>
                <a href="{{ route('oiiv-auto.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
            </div>
        </div>
    </div>

    {{-- ── Logic info ──────────────────────────────────────────── --}}
    <div class="alert" style="background:linear-gradient(135deg,#1a1a2e,#16213e); color:white; border:1px solid rgba(0,229,255,0.2); border-radius:12px; margin-bottom:20px; padding:15px;">
        <h6 style="color:#00e5ff; margin-bottom:10px; font-size:13px;"><i class="fas fa-crosshairs"></i> <strong>Why Near Strikes Only?</strong></h6>
        <div class="row">
            <div class="col-md-4">
                <small style="font-size:11px; color:#00e5ff;"><strong>📍 Strikes used</strong></small>
                <ul style="font-size:10px; margin-top:5px; color:rgba(255,255,255,0.8);">
                    <li><strong style="color:#dc3545;">CE side:</strong> ATM+4 and ATM+5 steps</li>
                    <li><strong style="color:#28a745;">PE side:</strong> ATM−4 and ATM−5 steps</li>
                    <li>Total = 4 strikes per symbol per day</li>
                </ul>
            </div>
            <div class="col-md-4">
                <small style="font-size:11px; color:#00e5ff;"><strong>💡 Why these strikes?</strong></small>
                <ul style="font-size:10px; margin-top:5px; color:rgba(255,255,255,0.8);">
                    <li>Near strikes carry the most liquidity</li>
                    <li>Writers target ±4/±5 for max premium decay</li>
                    <li>Cleaner signal — excludes far OTM noise</li>
                </ul>
            </div>
            <div class="col-md-4">
                <small style="font-size:11px; color:#00e5ff;"><strong>📊 Signal logic (same as EOD)</strong></small>
                <ul style="font-size:10px; margin-top:5px; color:rgba(255,255,255,0.8);">
                    <li>CE ↑ + PE ↓ → BEARISH</li>
                    <li>CE ↓ + PE ↑ → BULLISH</li>
                    <li>Both ↑ → stronger side wins</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── Filters ──────────────────────────────────────────────── --}}
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
                    <option value="">All Actions</option>
                    <option value="BUY CE">BUY CE Only</option>
                    <option value="BUY PE">BUY PE Only</option>
                    <option value="WAIT">WAIT Only</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12 text-center">
                <button type="button" id="run_analysis" class="btn btn-light btn-lg" style="min-width:150px; font-size:13px;">
                    <i class="fas fa-crosshairs"></i> Analyze Near Strikes
                </button>
                <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg ml-2" style="min-width:120px; font-size:13px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ── Stats Row ────────────────────────────────────────────── --}}
    <div class="row">
        <div class="col-md-2"><div class="stats-box"><small>Total Records</small><strong id="total_records" class="text-dark">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE</small><strong id="buy_ce_count" style="color:#28a745;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE</small><strong id="buy_pe_count" style="color:#dc3545;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>WAIT</small><strong id="wait_count" style="color:#ffc107;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BULLISH</small><strong id="bull_count" style="color:#28a745;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BEARISH</small><strong id="bear_count" style="color:#dc3545;">0</strong></div></div>
    </div>

    {{-- ── Performance Stats Row ───────────────────────────────── --}}
    <div class="row mb-3">
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#6c757d;"><small>Avg Investment</small><strong id="avg_investment" class="text-dark" style="font-size:1rem;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Exit Total P/L</small><strong id="exit_total_pl" style="font-size:1rem;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Avg Exit ROI</small><strong id="exit_avg_roi" style="color:#a855f7; font-size:1rem;">0%</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>📈 High Total P/L</small><strong id="high_total_pl" style="font-size:1rem;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#fd7e14;"><small>📉 Low Total P/L</small><strong id="low_total_pl" style="font-size:1rem;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>Trade Count</small><strong id="trade_count" style="color:#17a2b8; font-size:1rem;">0</strong></div></div>
    </div>

    {{-- ── Aligned section ─────────────────────────────────────── --}}
    <div class="aligned-section">
        <div class="aligned-section-header">
            <span style="font-size:20px;">🎯</span>
            <h6>Aligned Signals — Sentiment + 50MA Confirmed</h6>
            <span style="background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:white; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px;">High Confidence</span>
        </div>
        <div class="row">
            <div class="col-6 col-md-2"><div class="stats-box-dark cyan"><small>🎯 Aligned</small><strong id="aligned_count">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark green"><small>📈 BUY CE</small><strong id="aligned_buy_ce" style="color:#28a745;">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark red"><small>📉 BUY PE</small><strong id="aligned_buy_pe" style="color:#dc3545;">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark gold"><small>💰 Avg Investment</small><strong id="aligned_avg_inv" style="color:#ffc107;">₹0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#a855f7;"><small>🚪 Exit Win Rate</small><strong id="aligned_exit_win_rate" style="color:#a855f7;">0%</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#adb5bd;"><small>📊 High Win Rate</small><strong id="aligned_win_rate" style="color:#adb5bd;">0%</strong></div></div>
        </div>
        <div class="row">
            <div class="col-6 col-md-3"><div class="stats-box-dark purple"><small>🚪 Exit P/L</small><strong id="aligned_exit_pl">₹0</strong></div></div>
            <div class="col-6 col-md-3"><div class="stats-box-dark purple"><small>🚪 Exit Avg ROI</small><strong id="aligned_exit_roi">0%</strong></div></div>
            <div class="col-6 col-md-3"><div class="stats-box-dark cyan"><small>📈 High P/L</small><strong id="aligned_high_pl">₹0</strong></div></div>
            <div class="col-6 col-md-3"><div class="stats-box-dark orange"><small>📉 Low P/L</small><strong id="aligned_low_pl">₹0</strong></div></div>
        </div>
    </div>

    {{-- ── Table ────────────────────────────────────────────────── --}}
    <div style="position:relative; min-height:400px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Analyzing near strikes...</div>
        </div>
        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>

                        {{-- ATM info --}}
                        {{-- <th>ATM Strike<br><small style="font-weight:400;opacity:.7;">Step</small></th> --}}

                        {{-- CE near strikes --}}
                        <th style="background:rgba(220,53,69,0.06);">CE Strikes<br><small style="font-weight:400;opacity:.7;">ATM+4 / ATM+5</small></th>
                        <th style="background:rgba(220,53,69,0.06);">CE OI<br><small style="font-weight:400;opacity:.7;">Today Sum</small></th>
                        <th style="background:rgba(220,53,69,0.06);">CE OI Prev<br><small style="font-weight:400;opacity:.7;">Yesterday Sum</small></th>
                        <th style="background:rgba(220,53,69,0.06);">CE %<br><small style="font-weight:400;opacity:.7;">Change</small></th>

                        {{-- PE near strikes --}}
                        <th style="background:rgba(40,167,69,0.06);">PE Strikes<br><small style="font-weight:400;opacity:.7;">ATM−4 / ATM−5</small></th>
                        <th style="background:rgba(40,167,69,0.06);">PE OI<br><small style="font-weight:400;opacity:.7;">Today Sum</small></th>
                        <th style="background:rgba(40,167,69,0.06);">PE OI Prev<br><small style="font-weight:400;opacity:.7;">Yesterday Sum</small></th>
                        <th style="background:rgba(40,167,69,0.06);">PE %<br><small style="font-weight:400;opacity:.7;">Change</small></th>

                        {{-- Signals --}}
                        {{-- <th>Condition</th> --}}
                        <th>Sentiment</th>
                        {{-- <th>50 MA</th> --}}
                        {{-- <th>Price Signal<br><small style="font-weight:400;opacity:.7;">Prev→Today</small></th> --}}
                        <th>Gann Bias<br><small style="font-weight:400;opacity:.7;">8-Zone</small></th>
                        {{-- <th>Strength</th> --}}
                        <th>Action</th>
                        {{-- <th>P/C Ratio</th> --}}

                        {{-- Profit cols --}}
                        <th>Option</th>
                        <th>Investment</th>
                        <th>Buy ₹<br><small style="font-weight:400;opacity:.7;">14:45 close</small></th>
                        <th class="th-exit">Exit ₹<br><small style="font-weight:400;opacity:.8;">Next 09:30</small></th>
                        <th class="th-exit">Exit P/L</th>
                        <th class="th-exit">Exit ROI%</th>
                        <th>High ₹</th>
                        <th>High P/L</th>
                        <th>High ROI%</th>
                        <th>Low ₹</th>
                        <th>Low P/L</th>
                        <th>Low ROI%</th>
                    </tr>
                </thead>
                <tbody id="analysis-tbody">
                    <tr>
                        <td colspan="32" class="text-center py-5">
                            <i class="fas fa-crosshairs" style="font-size:3rem; opacity:0.4; color:#00e5ff;"></i>
                            <p style="font-size:1.1rem; margin-top:20px; color:#888;">Click <strong>"Analyze Near Strikes"</strong> to load signals</p>
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

/* ── Loading ────────────────────────────────────────── */
function toggleLoading(show, msg) {
    if (show) { $('#loading-overlay .loading-text').text(msg || 'Loading...'); $('#loading-overlay').show(); }
    else       { $('#loading-overlay').hide(); }
}

/* ── Init ───────────────────────────────────────────── */
$(document).ready(function () {
    loadSymbols();
    setTimeout(() => runAnalysis(), 500);
});

function loadSymbols() {
    $.ajax({
        url: '{{ route("near-strike-oi.symbols") }}', type: 'GET',
        success: function (res) {
            if (!res.success) return;
            let opts = '';
            res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
            $('#symbol_filter').html(opts);
        }
    });
}

/* ── OI formatter ───────────────────────────────────── */
function fmtOI(val) {
    const n = Number(val) || 0;
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
    return n.toString();
}

/* ── Strike breakdown cell ──────────────────────────── */
function strikeBreakdownCell(strikes, detail, type) {
    if (!strikes || strikes.length === 0) return '<span style="color:#aaa;">—</span>';
    const cls = type === 'CE' ? 'ce-pill' : 'pe-pill';
    let html = '<div class="strike-breakdown">';
    strikes.forEach((s, i) => {
        const d     = (detail || []).find(x => Math.abs(x.strike - s) < 0.01);
        const oi    = d ? fmtOI(d.oi) : '—';
        const label = type === 'CE' ? `ATM+${4 + i}` : `ATM−${4 + i}`;
        html += `<div class="strike-row">
            <span class="strike-pill ${cls}">${label}: ${Number(s).toLocaleString('en-IN')}</span>
            <span class="oi-mini">${oi}</span>
        </div>`;
    });
    html += '</div>';
    return html;
}

/* ── Aligned check ──────────────────────────────────── */
function isAligned(row) {
    const sent = row.oi_signal;
    const ma   = row.fut_50ma_signal;
    return (sent === 'BULLISH' && ma === 'BULLISH') ||
           (sent === 'BEARISH' && ma === 'BEARISH');
}

/* ── Badge helpers ──────────────────────────────────── */
function getConditionBadge(cond) {
    if (!cond) return '<span style="color:#aaa;">—</span>';
    if (cond.includes('CE ↑ + PE ↓')) return `<span class="condition-ce-up-pe-down">${cond}</span>`;
    if (cond.includes('CE ↓ + PE ↑')) return `<span class="condition-ce-down-pe-up">${cond}</span>`;
    if (cond.includes('Both ↑'))      return `<span class="condition-both-up">${cond}</span>`;
    if (cond.includes('Both ↓'))      return `<span class="condition-both-down">${cond}</span>`;
    return `<span class="condition-flat">${cond}</span>`;
}

function getSentBadge(s) {
    if (s === 'BULLISH') return '<span class="sentiment-bullish">🟢 BULLISH</span>';
    if (s === 'BEARISH') return '<span class="sentiment-bearish">🔴 BEARISH</span>';
    return '<span class="sentiment-neutral">⚪ NEUTRAL</span>';
}

function getActBadge(a) {
    if (a === 'BUY CE') return '<span class="action-buy-ce">📈 BUY CE</span>';
    if (a === 'BUY PE') return '<span class="action-buy-pe">📉 BUY PE</span>';
    return '<span class="action-wait">⏸ WAIT</span>';
}

function getMa50Badge(s) {
    if (!s || s === 'N/A') return '<span style="color:#aaa; font-size:10px;">N/A</span>';
    if (s === 'BULLISH') return '<span class="ma-bullish">Above MA</span>';
    if (s === 'BEARISH') return '<span class="ma-bearish">Below MA</span>';
    return '<span class="ma-neutral">On MA</span>';
}

function getPriceSignalBadge(signal, changePct) {
    if (!signal || signal === 'N/A') return '<span class="mm-no-trap">N/A</span>';
    const pct  = parseFloat(changePct) || 0;
    const sign = pct >= 0 ? '+' : '';
    const str  = `<br><small style="font-size:9px;opacity:.85;">${sign}${pct.toFixed(2)}%</small>`;
    if (signal === 'BULLISH') return `<span class="sentiment-bullish">▲ BULL${str}</span>`;
    if (signal === 'BEARISH') return `<span class="sentiment-bearish">▼ BEAR${str}</span>`;
    return `<span class="sentiment-neutral">— NEUT${str}</span>`;
}

function getGannBadge(bias, zone) {
    if (!bias || bias === 'N/A') return '<span class="mm-no-trap">N/A</span>';
    const zStr = zone ? `<br><small style="font-size:9px;opacity:.85;">Zone ${zone}</small>` : '';
    const styles = {
        'STRONG BULLISH': 'background:#1b5e20;color:white;',
        'BULLISH':        'background:#28a745;color:white;',
        'BEARISH':        'background:#dc3545;color:white;',
        'STRONG BEARISH': 'background:#7f0000;color:white;',
    };
    const labels = {
        'STRONG BULLISH': '🟢🟢 S.BULL',
        'BULLISH':        '🟢 BULL',
        'BEARISH':        '🔴 BEAR',
        'STRONG BEARISH': '🔴🔴 S.BEAR',
    };
    const style = styles[bias] || 'background:#6c757d;color:white;';
    const label = labels[bias] || bias;
    return `<span style="${style}padding:3px 7px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block;">${label}${zStr}</span>`;
}

function getStrengthBadge(rank, sentiment) {
    if (rank === 'Normal') return '<span class="strength-normal">Normal</span>';
    const n    = (rank || '').replace('Rank ', '');
    const bull = sentiment === 'BULLISH';
    return `<span class="${bull ? 'strength-bullish' : 'strength-bearish'}">${bull ? '🟢' : '🔴'} R${n}</span>`;
}

/* ── Main analysis ──────────────────────────────────── */
function runAnalysis() {
    const fromDate = $('#from_date').val();
    const toDate   = $('#to_date').val();
    const symbols  = $('#symbol_filter').val() || [];
    const action   = $('#action_filter').val();

    if (!fromDate || !toDate) { alert('Please select both dates'); return; }

    toggleLoading(true, 'Analyzing near strikes (ATM ±4/±5)...');
    analysisData = [];

    $.ajax({
        url: '{{ route("near-strike-oi.analyze") }}', type: 'GET',
        data: { from_date: fromDate, to_date: toDate, symbols, filter_action: action },
        success: function (res) {
            if (res.success && res.data && res.data.length > 0) {
                analysisData = res.data;
                renderTable();
                updateStats();
            } else {
                showNoData(res.message || 'No data found');
                resetStats();
            }
            toggleLoading(false);
        },
        error: function () {
            showNoData('Error loading data');
            resetStats();
            toggleLoading(false);
        }
    });
}

/* ── Render table ───────────────────────────────────── */
function renderTable() {
    let html = '';

    analysisData.forEach(function (row, i) {
        const aligned    = isAligned(row);
        const rowStyle   = aligned ? 'style="background:rgba(0,210,255,0.05); outline:1px solid rgba(0,210,255,0.2);"' : '';
        const cePctCls   = row.ce_oi_change_pct  > 0 ? 'text-danger' : 'text-success';
        const pePctCls   = row.pe_oi_change_pct  > 0 ? 'text-success' : 'text-danger';
        const exitTd     = 'style="background:rgba(168,85,247,0.06);"';

        // <td>
        //     <span class="atm-badge">₹${Number(row.atm_strike).toLocaleString('en-IN')}</span>
        //     <br><small style="color:#aaa;font-size:9px;">step: ${row.strike_step}</small>
        //     ${row.is_expiry_day ? '<br><span style="background:#ff9800;color:#000;font-size:8px;padding:1px 5px;border-radius:3px;font-weight:700;">EXPIRY</span>' : ''}
        // </td>

        // <td>${getConditionBadge(row.oi_condition)}</td>
        // <td>${getMa50Badge(row.fut_50ma_signal)}</td>
        // <td>${getPriceSignalBadge(row.price_signal, row.price_change_pct)}</td>
        // <td>${getStrengthBadge(row.strength_rank, row.oi_signal)}</td>
        // <td><span class="ratio-badge">${row.pe_ce_ratio}</span></td>

        html += `<tr ${rowStyle}>
            <td><strong>${i + 1}</strong>${aligned ? ' <span title="Aligned" style="color:#00d2ff;font-size:10px;">🎯</span>' : ''}</td>
            <td><strong>${row.date}</strong></td>
            <td><strong style="color:#667eea;">${row.symbol}</strong></td>


            <td style="background:rgba(220,53,69,0.04);">${strikeBreakdownCell(row.ce_strikes, row.ce_detail, 'CE')}</td>
            <td style="background:rgba(220,53,69,0.04);">
                <strong>${fmtOI(row.ce_oi)}</strong>
                <br><small style="color:#aaa;font-size:9px;">${(row.ce_oi||0).toLocaleString()}</small>
            </td>
            <td style="background:rgba(220,53,69,0.04);">
                <span style="color:#aaa;">${fmtOI(row.ce_oi_prev)}</span>
                <br><small style="color:#aaa;font-size:9px;">${(row.ce_oi_prev||0).toLocaleString()}</small>
            </td>
            <td style="background:rgba(220,53,69,0.04);" class="${cePctCls}">
                <strong>${row.ce_oi_change_pct > 0 ? '+' : ''}${Number(row.ce_oi_change_pct).toFixed(2)}%</strong>
            </td>

            <td style="background:rgba(40,167,69,0.04);">${strikeBreakdownCell(row.pe_strikes, row.pe_detail, 'PE')}</td>
            <td style="background:rgba(40,167,69,0.04);">
                <strong>${fmtOI(row.pe_oi)}</strong>
                <br><small style="color:#aaa;font-size:9px;">${(row.pe_oi||0).toLocaleString()}</small>
            </td>
            <td style="background:rgba(40,167,69,0.04);">
                <span style="color:#aaa;">${fmtOI(row.pe_oi_prev)}</span>
                <br><small style="color:#aaa;font-size:9px;">${(row.pe_oi_prev||0).toLocaleString()}</small>
            </td>
            <td style="background:rgba(40,167,69,0.04);" class="${pePctCls}">
                <strong>${row.pe_oi_change_pct > 0 ? '+' : ''}${Number(row.pe_oi_change_pct).toFixed(2)}%</strong>
            </td>

            <td>${getSentBadge(row.oi_signal)}</td>
            <td>${getGannBadge(row.gann_bias, row.gann_zone)}</td>
            <td>${getActBadge(row.trade_action)}</td>

            <td class="pc-option-${i}"><span class="profit-loading">…</span></td>
            <td class="pc-invest-${i}"><span class="profit-loading">…</span></td>
            <td class="pc-buy-${i}"><span class="profit-loading">…</span></td>

            <td class="pc-exit-${i}" ${exitTd}><span class="profit-loading">…</span></td>
            <td class="pc-exit-pl-${i}" ${exitTd}><span class="profit-loading">…</span></td>
            <td class="pc-exit-roi-${i}" ${exitTd}><span class="profit-loading">…</span></td>

            <td class="pc-high-${i}"><span class="profit-loading">…</span></td>
            <td class="pc-high-pl-${i}"><span class="profit-loading">…</span></td>
            <td class="pc-high-roi-${i}"><span class="profit-loading">…</span></td>
            <td class="pc-low-${i}"><span class="profit-loading">…</span></td>
            <td class="pc-low-pl-${i}"><span class="profit-loading">…</span></td>
            <td class="pc-low-roi-${i}"><span class="profit-loading">…</span></td>
        </tr>`;
    });

    $('#analysis-tbody').html(html);
    loadProfitData();
}

/* ── Profit loaders ─────────────────────────────────── */
function applyProfitToRow(item) {
    const idx = item.index;
    const allCols = `.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},` +
                    `.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},` +
                    `.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},` +
                    `.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`;

    if (item.error === 'WAIT') {
        $(allCols).html('<span class="text-muted" style="font-size:10px;">WAIT</span>'); return;
    }
    if (item.error) {
        const eb = `<span class="badge badge-warning" style="font-size:9px;" title="${item.error}">⚠</span>`;
        $(`.pc-option-${idx}`).html(item.option_symbol ? `<span class="option-symbol-badge">${item.option_symbol}</span>` : eb);
        $(`.pc-invest-${idx},.pc-buy-${idx},.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`).html(eb);
        return;
    }

    const plHtml  = pl  => `<strong class="${pl  >= 0 ? 'profit-positive' : 'profit-negative'}">${pl  >= 0 ? '+' : ''}₹${Math.abs(pl ).toFixed(2)}</strong>`;
    const roiHtml = roi => `<strong class="${roi >= 0 ? 'profit-positive' : 'profit-negative'}">${roi >= 0 ? '+' : ''}${Math.abs(roi).toFixed(2)}%</strong>`;

    $(`.pc-option-${idx}`).html(item.option_symbol ? `<span class="option-symbol-badge" title="${item.option_symbol}">${item.option_symbol}</span>` : '<span class="text-muted">N/A</span>');
    $(`.pc-invest-${idx}`).html(`<strong>₹${Number(item.investment).toLocaleString()}</strong>`);
    $(`.pc-buy-${idx}`).html(`<strong>₹${Number(item.buy_price).toFixed(2)}</strong>`);
    $(`.pc-exit-${idx}`).html(item.exit_price > 0 ? `<strong style="color:#a855f7;">₹${Number(item.exit_price).toFixed(2)}</strong>` : '<span class="text-muted">N/A</span>');
    $(`.pc-exit-pl-${idx}`).html(item.exit_price > 0 ? plHtml(item.exit_pl || 0)  : '<span class="text-muted">—</span>');
    $(`.pc-exit-roi-${idx}`).html(item.exit_price > 0 ? roiHtml(item.exit_roi || 0): '<span class="text-muted">—</span>');
    $(`.pc-high-${idx}`).html(item.high_price > 0 ? `<strong style="color:#17a2b8;">₹${Number(item.high_price).toFixed(2)}</strong>${item.high_time ? `<br><small style="color:#6c757d;font-size:9px;">${item.high_time}</small>` : ''}` : '<span class="text-muted">—</span>');
    $(`.pc-high-pl-${idx}`).html(plHtml(item.high_pl  || 0));
    $(`.pc-high-roi-${idx}`).html(roiHtml(item.high_roi || 0));
    $(`.pc-low-${idx}`).html(item.low_price > 0  ? `<strong style="color:#fd7e14;">₹${Number(item.low_price).toFixed(2)}</strong>${item.low_time  ? `<br><small style="color:#6c757d;font-size:9px;">${item.low_time}</small>`  : ''}` : '<span class="text-muted">—</span>');
    $(`.pc-low-pl-${idx}`).html(plHtml(item.low_pl   || 0));
    $(`.pc-low-roi-${idx}`).html(roiHtml(item.low_roi  || 0));
}

function renderNoProfitRow(idx) {
    const dash = '<span class="text-muted">—</span>';
    $(`.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`).html(dash);
}

function loadProfitData() {
    const signals = analysisData
        .map((row, idx) => ({
            index        : idx,
            date         : row.date,
            symbol       : row.symbol,
            trade_action : row.trade_action,
            spot_price   : row.spot_price || 0,
        }))
        .filter(r => r.trade_action === 'BUY CE' || r.trade_action === 'BUY PE');

    analysisData.forEach((row, idx) => {
        if (row.trade_action !== 'BUY CE' && row.trade_action !== 'BUY PE') renderNoProfitRow(idx);
    });

    if (signals.length === 0) { resetAlignedStats(); return; }

    $.ajax({
        url : '{{ route("near-strike-oi.calculate-profit") }}',
        type: 'POST',
        data: { _token: '{{ csrf_token() }}', signals },
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

/* ── Stats ──────────────────────────────────────────── */
function updateStats() {
    if (!analysisData || !analysisData.length) { resetStats(); return; }
    $('#total_records').text(analysisData.length);
    $('#buy_ce_count').text(analysisData.filter(r => r.trade_action === 'BUY CE').length);
    $('#buy_pe_count').text(analysisData.filter(r => r.trade_action === 'BUY PE').length);
    $('#wait_count').text(analysisData.filter(r => r.trade_action === 'WAIT').length);
    $('#bull_count').text(analysisData.filter(r => r.oi_signal === 'BULLISH').length);
    $('#bear_count').text(analysisData.filter(r => r.oi_signal === 'BEARISH').length);
}

function updateProfitStats(profitData) {
    const trades = profitData.filter(d => !d.error || d.error === null);
    const count  = trades.length;
    if (count === 0) return;

    const avgInv      = trades.reduce((s, d) => s + (d.investment || 0), 0) / count;
    const exitTotalPL = trades.reduce((s, d) => s + (d.exit_pl  || 0), 0);
    const exitAvgRoi  = trades.reduce((s, d) => s + (d.exit_roi || 0), 0) / count;
    const highTotalPL = trades.reduce((s, d) => s + (d.high_pl  || 0), 0);
    const lowTotalPL  = trades.reduce((s, d) => s + (d.low_pl   || 0), 0);

    const fmt    = v => (v >= 0 ? '+' : '') + '₹' + Math.abs(v).toFixed(2);
    const fmtRoi = v => (v >= 0 ? '+' : '') + Math.abs(v).toFixed(2) + '%';
    const plCls  = v => v >= 0 ? 'profit-positive' : 'profit-negative';

    $('#avg_investment').html(`₹${Math.round(avgInv).toLocaleString()}`);
    $('#trade_count').text(count);
    $('#exit_total_pl').html(`<span class="${plCls(exitTotalPL)}">${fmt(exitTotalPL)}</span>`);
    $('#exit_avg_roi').html(`<span class="${plCls(exitAvgRoi)}">${fmtRoi(exitAvgRoi)}</span>`);
    $('#high_total_pl').html(`<span class="${plCls(highTotalPL)}">${fmt(highTotalPL)}</span>`);
    $('#low_total_pl').html(`<span class="${plCls(lowTotalPL)}">${fmt(lowTotalPL)}</span>`);

    // Aligned subset
    const alignedTrades = trades.filter(d => {
        const row = analysisData[d.index];
        return row && isAligned(row);
    });
    updateAlignedStats(alignedTrades);
}

function updateAlignedStats(alignedTrades) {
    const count = alignedTrades.length;
    if (count === 0) { resetAlignedStats(); return; }

    const buyCE = alignedTrades.filter(d => { const r = analysisData[d.index]; return r && r.trade_action === 'BUY CE'; }).length;
    const buyPE = alignedTrades.filter(d => { const r = analysisData[d.index]; return r && r.trade_action === 'BUY PE'; }).length;

    const avgInv      = alignedTrades.reduce((s, d) => s + (d.investment || 0), 0) / count;
    const exitTotalPL = alignedTrades.reduce((s, d) => s + (d.exit_pl  || 0), 0);
    const exitAvgRoi  = alignedTrades.reduce((s, d) => s + (d.exit_roi || 0), 0) / count;
    const highTotalPL = alignedTrades.reduce((s, d) => s + (d.high_pl  || 0), 0);
    const lowTotalPL  = alignedTrades.reduce((s, d) => s + (d.low_pl   || 0), 0);
    const exitWinPct  = ((alignedTrades.filter(d => (d.exit_pl || 0) > 0).length / count) * 100).toFixed(1);
    const highWinPct  = ((alignedTrades.filter(d => (d.high_pl || 0) > 0).length / count) * 100).toFixed(1);

    const fmt    = v => (v >= 0 ? '+' : '') + '₹' + Math.abs(v).toFixed(2);
    const fmtRoi = v => (v >= 0 ? '+' : '') + Math.abs(v).toFixed(2) + '%';
    const plCls  = v => v >= 0 ? 'profit-positive' : 'profit-negative';
    const wCls   = p => parseFloat(p) >= 50 ? 'profit-positive' : 'profit-negative';

    $('#aligned_count').text(count);
    $('#aligned_buy_ce').text(buyCE);
    $('#aligned_buy_pe').text(buyPE);
    $('#aligned_avg_inv').html(`₹${Math.round(avgInv).toLocaleString()}`);
    $('#aligned_exit_win_rate').html(`<span class="${wCls(exitWinPct)}">${exitWinPct}%</span>`);
    $('#aligned_win_rate').html(`<span class="${wCls(highWinPct)}">${highWinPct}%</span>`);
    $('#aligned_exit_pl').html(`<span class="${plCls(exitTotalPL)}">${fmt(exitTotalPL)}</span>`);
    $('#aligned_exit_roi').html(`<span class="${plCls(exitAvgRoi)}">${fmtRoi(exitAvgRoi)}</span>`);
    $('#aligned_high_pl').html(`<span class="${plCls(highTotalPL)}">${fmt(highTotalPL)}</span>`);
    $('#aligned_low_pl').html(`<span class="${plCls(lowTotalPL)}">${fmt(lowTotalPL)}</span>`);
}

function resetStats() {
    $('#total_records,#buy_ce_count,#buy_pe_count,#wait_count,#bull_count,#bear_count').text('0');
    $('#avg_investment').text('₹0'); $('#trade_count').text('0');
    $('#exit_total_pl,#high_total_pl,#low_total_pl').text('₹0');
    $('#exit_avg_roi').text('0%');
    resetAlignedStats();
}

function resetAlignedStats() {
    $('#aligned_count,#aligned_buy_ce,#aligned_buy_pe').text('0');
    $('#aligned_avg_inv').text('₹0');
    $('#aligned_exit_win_rate,#aligned_win_rate').text('0%');
    $('#aligned_exit_pl,#aligned_high_pl,#aligned_low_pl').text('₹0');
    $('#aligned_exit_roi').text('0%');
}

function showNoData(message) {
    $('#analysis-tbody').html(`<tr><td colspan="32" class="text-center py-5">
        <i class="fas fa-info-circle" style="color:#17a2b8; font-size:3rem;"></i>
        <p class="text-info" style="margin-top:20px;">${message}</p>
    </td></tr>`);
}

$('#run_analysis').click(() => runAnalysis());
$('#reset_filters').click(function () {
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter,#action_filter').val('');
    analysisData = [];
    showNoData('Click "Analyze Near Strikes" to load signals');
    resetStats();
    setTimeout(() => runAnalysis(), 300);
});
</script>
@endpush