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
    .spinner { width:50px; height:50px; border:5px solid #f3f3f3; border-top:5px solid #3498db; border-radius:50%; animation:spin 1s linear infinite; }
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

    /* ── Condition badges ───────────────────────────────── */
    .condition-ce-up-pe-down { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-ce-down-pe-up { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-both-up       { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-both-down     { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .condition-flat          { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Misc badges ────────────────────────────────────── */
    .ratio-badge      { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:3px 5px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; }
    .new-feature-badge{ background:linear-gradient(135deg,#f093fb,#f5576c); color:white; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }
    .sector-tag       { background:rgba(102,126,234,0.12); color:#667eea; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; display:inline-block; }
    .bias-badge-bull  { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:5px 14px; border-radius:6px; font-weight:700; font-size:13px; display:inline-block; }
    .bias-badge-bear  { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:5px 14px; border-radius:6px; font-weight:700; font-size:13px; display:inline-block; }
    .bias-badge-neut  { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:5px 14px; border-radius:6px; font-weight:700; font-size:13px; display:inline-block; }

    /* ── Strength score ─────────────────────────────────── */
    .strength-score-1 { background:#6c757d; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-score-2 { background:#3a7bd5; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-score-3 { background:#f39c12; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-score-4 { background:#27ae60; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-score-5 { background:linear-gradient(135deg,#e74c3c,#c0392b); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Filter section ─────────────────────────────────── */
    .filter-section { background:linear-gradient(135deg,#667eea,#764ba2); padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4); color:white; }
    .filter-section label { color:white !important; font-weight:600; margin-bottom:6px; font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.9); color:#333; font-size:12px; padding:6px 10px; }

    /* ── Stats boxes ────────────────────────────────────── */
    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.1); transition:transform .2s; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#666; font-size:10px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box strong { display:block; font-size:1.4rem; font-weight:700; margin-top:3px; }

    /* ── Bias Summary Box ───────────────────────────────── */
    .bias-summary-box {
        border-radius: 12px;
        padding: 18px 22px;
        margin-bottom: 20px;
        border: 1px solid transparent;
    }
    .bias-summary-box.bullish { background:rgba(40,167,69,0.08); border-color:rgba(40,167,69,0.3); }
    .bias-summary-box.bearish { background:rgba(220,53,69,0.08); border-color:rgba(220,53,69,0.3); }
    .bias-summary-box.neutral { background:rgba(108,117,125,0.08); border-color:rgba(108,117,125,0.25); }

    /* ── Sector pills ───────────────────────────────────── */
    .sector-pill { background:#132d39; border:1px solid #dee2e6; border-radius:8px; padding:10px 14px; margin-bottom:10px; }
    .sector-pill.bull { border-left:4px solid #28a745; }
    .sector-pill.bear { border-left:4px solid #dc3545; }
    .sector-pill.neut { border-left:4px solid #ffc107; }

    /* ── Trade plan ─────────────────────────────────────── */
    .trade-plan-box { background:#132d39;border:1px solid #fff; border-radius:10px; padding:16px 20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .trade-plan-box h6 { font-weight:700; font-size:14px; margin-bottom:12px; color:#495057; }
    .tp-item { padding:8px 0; border-bottom:1px solid #f1f3f5; font-size:12px; }
    .tp-item:last-child { border-bottom:none; }
    .tp-label { color:#888; font-size:10px; text-transform:uppercase; letter-spacing:0.5px; }
    .tp-value { font-weight:700; font-size:13px; }
    .value-ce { color:#28a745; }
    .value-pe { color:#dc3545; }

    /* ── Breadth bar ────────────────────────────────────── */
    .breadth-bar { height:10px; border-radius:5px; background:#dee2e6; overflow:hidden; display:flex; margin:8px 0; }
    .breadth-bull { background:#28a745; transition:width 0.7s; }
    .breadth-bear { background:#dc3545; transition:width 0.7s; }
    .breadth-neut { background:#dee2e6; flex:1; }

    /* ── Page header ────────────────────────────────────── */
    .page-header { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4); }

    /* ── Aligned section ────────────────────────────────── */
    .aligned-section {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        border: 2px solid #00d2ff;
        border-radius: 14px;
        padding: 16px 20px 8px;
        margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(0,210,255,0.25);
    }
    .aligned-section-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
    .aligned-section-header h6 { color:#00d2ff; font-size:13px; font-weight:700; margin:0; text-transform:uppercase; letter-spacing:.5px; }
    .aligned-tag { background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:white; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:.5px; }
    .stats-box-dark { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); padding:10px 8px; border-radius:10px; text-align:center; border-left:4px solid #00d2ff; margin-bottom:12px; transition:transform .2s; }
    .stats-box-dark:hover { transform:translateY(-2px); }
    .stats-box-dark small  { display:block; color:rgba(255,255,255,0.55); font-size:9px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box-dark strong { display:block; font-size:1.1rem; font-weight:700; margin-top:4px; color:white; }
    .stats-box-dark.green  { border-left-color:#28a745; }
    .stats-box-dark.red    { border-left-color:#dc3545; }
    .stats-box-dark.cyan   { border-left-color:#00d2ff; }

    /* ── Sticky first 3 cols ────────────────────────────── */
    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .custom--table { min-width:1800px; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position:sticky; z-index:10; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left:0; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left:40px; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left:120px; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>{{ $pageTitle }} <span class="new-feature-badge">ALL 50 STOCKS</span></h4>
                <p style="margin:0; font-size:13px;">CE/PE OI analysis for all NIFTY 50 stocks → weighted sector bias → next day NIFTY direction</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm"><i class="fas fa-chart-bar"></i> PE/CE Analysis</a>
                <a href="{{ route('oiiv-auto.index') }}" class="btn btn-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
                <a href="{{ route('oi-change.index') }}" class="btn btn-light btn-sm"><i class="fas fa-exchange-alt"></i> OI Change</a>
            </div>
        </div>
    </div>

    {{-- Logic --}}
    <div class="alert" style="background:linear-gradient(135deg,#667eea,#764ba2); color:white; border:none; border-radius:12px; margin-bottom:20px; padding:15px;">
        <h6 style="color:white; margin-bottom:10px; font-size:14px;"><i class="fas fa-info-circle"></i> <strong>Logic Summary:</strong></h6>
        <div class="row">
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>📊 OI Signal (Same as PE/CE Analysis)</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li><strong>CE ↑ + PE ↓</strong> → BEARISH</li>
                    <li><strong>CE ↓ + PE ↑</strong> → BULLISH</li>
                    <li><strong>Both ↑</strong> → CE%>PE% = BEARISH</li>
                    <li><strong>Both ↓</strong> → CE%&lt;PE% = BULLISH</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>⚖️ Weighted Bias</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>Each stock weighted by sector %</li>
                    <li>Financial 35.45% → overrides</li>
                    <li>All 50 stocks counted</li>
                    <li>Majority vote per sector</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>🏆 Strength Rank = |CE%−PE%|</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>Rank 1 — diff > 40</li>
                    <li>Rank 2 — diff > 25</li>
                    <li>Rank 3 — diff > 10</li>
                    <li>Rank 4 — diff > 5</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>⏰ Best Time to Check</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>Run at 3:00–3:15 PM EOD</li>
                    <li>Next day trade at 09:30</li>
                    <li>Entry: first candle breakout</li>
                    <li>Use ATM CE / ATM PE</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-section">
        <div class="row align-items-end">
            <div class="col-md-3">
                <label><i class="fas fa-calendar-alt"></i> Analysis Date:</label>
                <input type="date" id="analysis_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-filter"></i> Signal Filter:</label>
                <select id="signal_filter" class="form-control">
                    <option value="">All Signals</option>
                    <option value="BULLISH">BULLISH Only</option>
                    <option value="BEARISH">BEARISH Only</option>
                    <option value="NEUTRAL">NEUTRAL Only</option>
                </select>
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-industry"></i> Sector Filter:</label>
                <select id="sector_filter" class="form-control">
                    <option value="">All Sectors</option>
                    <option value="Financial Services">Financial Services (35.45%)</option>
                    <option value="Oil Gas & Consumable Fuels">Oil Gas & Fuels (10.95%)</option>
                    <option value="Information Technology">Information Technology (9.40%)</option>
                    <option value="Automobile">Automobile (6.60%)</option>
                    <option value="FMCG">FMCG (5.96%)</option>
                    <option value="Telecommunication">Telecommunication (5.34%)</option>
                    <option value="Healthcare">Healthcare (4.68%)</option>
                    <option value="Metals & Mining">Metals & Mining (4.28%)</option>
                    <option value="Construction">Construction (4.02%)</option>
                    <option value="Power">Power (3.03%)</option>
                    <option value="Consumer Durables">Consumer Durables (2.55%)</option>
                    <option value="Consumer Services">Consumer Services (2.33%)</option>
                    <option value="Construction Materials">Construction Materials (2.19%)</option>
                    <option value="Services">Services (1.82%)</option>
                    <option value="Capital Goods">Capital Goods (1.40%)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>&nbsp;</label>
                <div>
                    <button type="button" id="btn_analyze" class="btn btn-light btn-lg" style="min-width:140px; font-size:13px;">
                        <i class="fas fa-bolt"></i> Analyze
                    </button>
                    <button type="button" id="btn_reset" class="btn btn-outline-light btn-lg ml-2" style="min-width:100px; font-size:13px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
                <small style="color:rgba(255,255,255,0.7); font-size:10px; margin-top:4px; display:block;" id="analyzed_at_label"></small>
            </div>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="row">
        <div class="col-md-2 col-6"><div class="stats-box"><small>Total Stocks</small><strong id="stat_total" class="text-dark">0</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#28a745;"><small>🟢 Bullish</small><strong id="stat_bullish" style="color:#28a745;">0</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#dc3545;"><small>🔴 Bearish</small><strong id="stat_bearish" style="color:#dc3545;">0</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#6c757d;"><small>⚪ Neutral</small><strong id="stat_neutral" style="color:#6c757d;">0</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#667eea;"><small>Bull Weight</small><strong id="stat_bull_wt" style="color:#667eea;">0%</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#f5a623;"><small>Bear Weight</small><strong id="stat_bear_wt" style="color:#f5a623;">0%</strong></div></div>
    </div>

    {{-- Bias Summary --}}
    <div class="bias-summary-box neutral" id="bias_box">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">NIFTY Next-Day Bias</div>
                <div id="bias_badge_wrap"><span class="bias-badge-neut">— Select date & analyze</span></div>
                <div id="bias_reason" style="font-size:12px; color:#666; margin-top:8px;"></div>
            </div>
            <div class="text-right">
                <div style="font-size:11px; color:#888; margin-bottom:6px;">Trade Plan</div>
                <div id="tp_action_inline" style="font-size:14px; font-weight:700; color:#495057;">—</div>
                <div id="tp_strike_inline" style="font-size:11px; color:#888; margin-top:2px;">—</div>
                <div id="tp_entry_inline" style="font-size:11px; color:#888; margin-top:2px;">—</div>
            </div>
            <div class="text-right">
                <div style="font-size:11px; color:#888; margin-bottom:4px;">Breadth (50 stocks)</div>
                <div class="breadth-bar" style="width:220px; margin:0 0 6px;">
                    <div class="breadth-bull" id="breadth_bull_bar" style="width:0%;"></div>
                    <div class="breadth-bear" id="breadth_bear_bar" style="width:0%;"></div>
                    <div class="breadth-neut"></div>
                </div>
                <div style="font-size:10px; display:flex; justify-content:space-between; width:220px;">
                    <span class="text-success font-weight-bold" id="breadth_bull_lbl">🟢 0</span>
                    <span class="text-secondary" id="breadth_neut_lbl">⚪ 0</span>
                    <span class="text-danger font-weight-bold" id="breadth_bear_lbl">🔴 0</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Sector Summary (aligned dark panel) --}}
    <div class="aligned-section" id="sector_panel" style="display:none;">
        <div class="aligned-section-header">
            <span style="font-size:20px;">🏭</span>
            <h6>Sector-wise OI Breakdown</h6>
            <span class="aligned-tag">Weighted</span>
        </div>
        <div class="row" id="sector_pills_row"></div>
    </div>

    {{-- Trade Plan --}}
    <div class="trade-plan-box" id="trade_plan_box" style="display:none;">
        <h6><i class="fas fa-bullseye text-danger"></i> Next Day Trade Plan</h6>
        <div class="row">
            <div class="col-md-3">
                <div class="tp-item"><div class="tp-label">Trade Date</div><div class="tp-value" id="tp_date">—</div></div>
                <div class="tp-item"><div class="tp-label">Action</div><div class="tp-value" id="tp_action">—</div></div>
            </div>
            <div class="col-md-3">
                <div class="tp-item"><div class="tp-label">Strike</div><div class="tp-value" id="tp_strike">—</div></div>
                <div class="tp-item"><div class="tp-label">Entry Time</div><div class="tp-value" id="tp_time">—</div></div>
            </div>
            <div class="col-md-3">
                <div class="tp-item"><div class="tp-label">Stop Loss</div><div class="tp-value text-danger" id="tp_sl">—</div></div>
                <div class="tp-item"><div class="tp-label">Target</div><div class="tp-value text-success" id="tp_target">—</div></div>
            </div>
            <div class="col-md-3">
                <div class="tp-item" style="border:none;">
                    <div class="tp-label">Entry Trigger</div>
                    <div style="font-size:12px; color:#495057; margin-top:4px;" id="tp_trigger">—</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Stock Table --}}
    <div style="position:relative; min-height:400px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text" id="loading-text">Analyzing all 50 NIFTY stocks...</div>
        </div>
        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Symbol</th>
                        <th>Sector</th>
                        <th>Wt%</th>
                        <th>FUT Price</th>
                        <th>CE OI</th>
                        <th>CE %</th>
                        <th>PE OI</th>
                        <th>PE %</th>
                        <th>Condition</th>
                        <th>Signal</th>
                        <th>Action</th>
                        <th>Strength</th>
                        <th>Score</th>
                        <th>Strong Side</th>
                        <th>P/C Ratio</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody id="stock-tbody">
                    <tr>
                        <td colspan="17" class="text-center py-5">
                            <i class="fas fa-chart-pie" style="font-size:3rem; opacity:0.4;"></i>
                            <p style="margin-top:16px;">Select a date and click <strong>Analyze</strong></p>
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
let allData = null;

/* ── Loading ──────────────────────────────────────────────────────────────── */
function toggleLoading(show, msg) {
    if (show) {
        if (msg) $('#loading-text').text(msg);
        $('#loading-overlay').show();
        $('#btn_analyze').prop('disabled', true);
    } else {
        $('#loading-overlay').hide();
        $('#btn_analyze').prop('disabled', false);
    }
}

/* ── Run Analysis ─────────────────────────────────────────────────────────── */
function runAnalysis() {
    const date = $('#analysis_date').val();
    if (!date) { alert('Please select a date'); return; }

    toggleLoading(true, 'Analyzing all 50 NIFTY stocks...');

    $.ajax({
        url : '{{ route("nifty50-sector.analyze") }}',
        type: 'GET',
        data: { date },
        success: function(res) {
            toggleLoading(false);
            if (!res.success) { alert('Error: ' + (res.message || 'Unknown')); return; }
            allData = res;
            $('#analyzed_at_label').text('Analyzed: ' + res.analyzed_at + ' | Prev day: ' + res.prev_date);
            renderAll(res);
            applyFilters();
        },
        error: function() {
            toggleLoading(false);
            alert('Network error — please try again');
        }
    });
}

/* ── Render Everything ────────────────────────────────────────────────────── */
function renderAll(data) {
    const bias  = data.bias;
    const plan  = data.trade_plan;
    const bread = data.breadth;

    // Stats
    $('#stat_total').text(data.total_tracked || 0);
    $('#stat_bullish').text(bread.bullish);
    $('#stat_bearish').text(bread.bearish);
    $('#stat_neutral').text(bread.neutral);
    $('#stat_bull_wt').text(bias.bull_weight + '%');
    $('#stat_bear_wt').text(bias.bear_weight + '%');

    // Bias Box
    const biasBox   = $('#bias_box');
    const dirClass  = bias.direction === 'BULLISH' ? 'bullish' : bias.direction === 'BEARISH' ? 'bearish' : 'neutral';
    biasBox.removeClass('bullish bearish neutral').addClass(dirClass);

    let badgeHtml = '';
    if (bias.direction === 'BULLISH') badgeHtml = `<span class="bias-badge-bull">🟢 BULLISH</span>`;
    else if (bias.direction === 'BEARISH') badgeHtml = `<span class="bias-badge-bear">🔴 BEARISH</span>`;
    else badgeHtml = `<span class="bias-badge-neut">⚪ ${bias.direction}</span>`;
    badgeHtml += ` <span class="badge badge-${bias.strength === 'STRONG' ? 'success' : bias.strength === 'MODERATE' ? 'warning' : 'secondary'} ml-2" style="font-size:11px;">${bias.strength} · ${bias.confidence}%</span>`;
    $('#bias_badge_wrap').html(badgeHtml);
    $('#bias_reason').text(bias.reason);

    // Breadth
    $('#breadth_bull_bar').css('width', bread.bull_pct + '%');
    $('#breadth_bear_bar').css('width', bread.bear_pct + '%');
    $('#breadth_bull_lbl').text('🟢 ' + bread.bullish + ' (' + bread.bull_pct + '%)');
    $('#breadth_neut_lbl').text('⚪ ' + bread.neutral);
    $('#breadth_bear_lbl').text('🔴 ' + bread.bearish + ' (' + bread.bear_pct + '%)');

    // Trade plan inline
    const optClass = plan.option_type === 'CE' ? 'value-ce' : plan.option_type === 'PE' ? 'value-pe' : '';
    $('#tp_action_inline').html(`<span class="${optClass}">${plan.action}</span>`);
    $('#tp_strike_inline').text('Strike: ' + plan.strike);
    $('#tp_entry_inline').text(plan.trade_date + ' @ ' + plan.entry_time);

    // Trade plan box
    $('#trade_plan_box').show();
    $('#tp_date').text(plan.trade_date);
    const actionBadge = plan.option_type === 'CE'
        ? `<span class="action-buy-ce">📈 ${plan.action}</span>`
        : plan.option_type === 'PE'
        ? `<span class="action-buy-pe">📉 ${plan.action}</span>`
        : `<span class="action-wait">⏸ ${plan.action}</span>`;
    $('#tp_action').html(actionBadge);
    $('#tp_strike').html(`<span class="${optClass} font-weight-bold">${plan.strike}</span>`);
    $('#tp_time').text(plan.entry_time);
    $('#tp_sl').text(plan.stop_loss);
    $('#tp_target').text(plan.target);
    $('#tp_trigger').text(plan.entry_trigger);

    // Sector panel
    renderSectorPanel(data.sectors);

    // Table (rendered by applyFilters)
}

/* ── Sector Panel ─────────────────────────────────────────────────────────── */
function renderSectorPanel(sectors) {
    $('#sector_panel').show();
    let html = '';
    sectors.forEach(s => {
        const sigClass = s.signal === 'BULLISH' ? 'bull' : s.signal === 'BEARISH' ? 'bear' : 'neut';
        const sigBadge = s.signal === 'BULLISH'
            ? '<span class="sentiment-bullish">🟢 BULLISH</span>'
            : s.signal === 'BEARISH'
            ? '<span class="sentiment-bearish">🔴 BEARISH</span>'
            : '<span class="sentiment-neutral">⚪ ' + (s.signal || 'N/D') + '</span>';

        html += `
        <div class="col-md-3 col-sm-6">
            <div class="sector-pill ${sigClass}">
                <div style="font-size:11px; font-weight:700; margin-bottom:4px;">${s.sector} <small style="color:#888;">${s.sector_weight}%</small></div>
                <div>${sigBadge}</div>
                <div style="font-size:10px; color:#888; margin-top:4px;">
                    🟢 ${s.bullish} &nbsp; 🔴 ${s.bearish} &nbsp; ⚪ ${s.neutral} &nbsp;/ ${s.stocks} tracked
                </div>
            </div>
        </div>`;
    });
    $('#sector_pills_row').html(html);
}

/* ── Apply Filters & Render Table ─────────────────────────────────────────── */
function applyFilters() {
    if (!allData) return;
    const sigFilter    = $('#signal_filter').val();
    const sectorFilter = $('#sector_filter').val();

    let stocks = allData.stocks || [];
    if (sigFilter)    stocks = stocks.filter(s => s.signal === sigFilter);
    if (sectorFilter) stocks = stocks.filter(s => s.sector === sectorFilter);

    renderTable(stocks);
}

/* ── Table Render ─────────────────────────────────────────────────────────── */
function renderTable(stocks) {
    if (!stocks || stocks.length === 0) {
        $('#stock-tbody').html('<tr><td colspan="17" class="text-center text-warning py-4">No data found for selected filters. Check that 14:45 candles are collected for this date.</td></tr>');
        return;
    }

    let html = '';
    stocks.forEach((row, i) => {
        // Condition badge
        let condCls = 'condition-flat';
        if (row.condition) {
            if      (row.condition.includes('CE ↑ + PE ↓')) condCls = 'condition-ce-up-pe-down';
            else if (row.condition.includes('CE ↓ + PE ↑')) condCls = 'condition-ce-down-pe-up';
            else if (row.condition.includes('Both ↑'))       condCls = 'condition-both-up';
            else if (row.condition.includes('Both ↓'))       condCls = 'condition-both-down';
        }
        const condBadge = `<span class="${condCls}">${row.condition || 'N/A'}</span>`;

        const sentBadge = row.signal === 'BULLISH'
            ? '<span class="sentiment-bullish">🟢 BULLISH</span>'
            : row.signal === 'BEARISH'
            ? '<span class="sentiment-bearish">🔴 BEARISH</span>'
            : '<span class="sentiment-neutral">⚪ NEUTRAL</span>';

        const actBadge = row.trade_action === 'BUY CE'
            ? '<span class="action-buy-ce">📈 BUY CE</span>'
            : row.trade_action === 'BUY PE'
            ? '<span class="action-buy-pe">📉 BUY PE</span>'
            : '<span class="action-wait">⏸ WAIT</span>';

        const ceCls = row.ce_oi_pct > 0 ? 'text-success' : 'text-danger';
        const peCls = row.pe_oi_pct > 0 ? 'text-success' : 'text-danger';

        // Strength badge
        const strBadge = row.strength_rank === 'Normal'
            ? '<span class="strength-normal">Normal</span>'
            : `<span class="${row.signal === 'BULLISH' ? 'strength-bullish' : 'strength-bearish'}">${row.signal === 'BULLISH' ? '🟢 BULL' : '🔴 BEAR'} (${row.strength_rank})</span>`;

        // Score
        const ss = getStrengthScore(row);
        const scoreBadge = `<span class="${ss.cls}">${ss.label}</span>`;

        // Stronger side
        let strongerBadge = '<span class="text-muted" style="font-size:11px;">—</span>';
        if (row.stronger_side === 'CE') strongerBadge = '<span class="badge badge-warning" style="font-size:10px;font-weight:700;color:#155724;">CE 💪</span>';
        if (row.stronger_side === 'PE') strongerBadge = '<span class="badge badge-danger" style="font-size:10px;font-weight:700;">PE 💪</span>';

        html += `<tr>
            <td><strong>${i + 1}</strong></td>
            <td><strong style="color:#667eea;">${row.symbol}</strong></td>
            <td><span class="sector-tag">${row.sector}</span></td>
            <td><small style="color:#888;">${row.sector_weight}%</small></td>
            <td>${row.fut_price ? '₹' + Number(row.fut_price).toLocaleString('en-IN') : '<span class="text-muted">—</span>'}</td>
            <td>
                <strong>${fmtOI(row.ce_oi)}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.ce_oi || 0).toLocaleString()}</small>
            </td>
            <td class="${ceCls}"><strong>${row.ce_oi_pct > 0 ? '+' : ''}${Number(row.ce_oi_pct).toFixed(2)}%</strong></td>
            <td>
                <strong>${fmtOI(row.pe_oi)}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.pe_oi || 0).toLocaleString()}</small>
            </td>
            <td class="${peCls}"><strong>${row.pe_oi_pct > 0 ? '+' : ''}${Number(row.pe_oi_pct).toFixed(2)}%</strong></td>
            <td>${condBadge}</td>
            <td>${sentBadge}</td>
            <td>${actBadge}</td>
            <td>${strBadge}</td>
            <td>${scoreBadge}</td>
            <td>${strongerBadge}</td>
            <td><span class="ratio-badge">${row.pe_ce_ratio}</span></td>
            <td><small>${row.reason || '—'}</small></td>
        </tr>`;
    });

    $('#stock-tbody').html(html);
}

/* ── OI formatter ─────────────────────────────────────────────────────────── */
function fmtOI(val) {
    const n = Number(val) || 0;
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
    return n.toString();
}

/* ── Strength score ───────────────────────────────────────────────────────── */
function getStrengthScore(row) {
    const ce   = parseFloat(row.ce_oi_pct) || 0;
    const pe   = parseFloat(row.pe_oi_pct) || 0;
    const diff = parseFloat(row.strength_diff) || 0;

    if (ce < 0 && pe < 0)                        return { cls:'strength-score-1', label:'⚫ Very Weak' };
    if (ce > 10 && pe > 10 && diff > 5)           return { cls:'strength-score-5', label:'🔥 Very Strong' };
    if (ce > 5  && pe > 5  && diff > 4)           return { cls:'strength-score-4', label:'💪 Strong' };
    if (ce > 0  && pe > 0  && diff > 1.5)         return { cls:'strength-score-3', label:'📊 Moderate' };
    return                                                { cls:'strength-score-2', label:'🔵 Weak' };
}

/* ── Event Handlers ───────────────────────────────────────────────────────── */
$('#btn_analyze').click(runAnalysis);
$('#btn_reset').click(function() {
    $('#analysis_date').val('{{ date("Y-m-d") }}');
    $('#signal_filter, #sector_filter').val('');
    allData = null;
    $('#stock-tbody').html('<tr><td colspan="17" class="text-center py-4"><p class="text-muted">Filters reset — click Analyze to reload</p></td></tr>');
    $('#sector_panel, #trade_plan_box').hide();
    ['stat_total','stat_bullish','stat_bearish','stat_neutral'].forEach(id => $('#' + id).text('0'));
    $('#stat_bull_wt,#stat_bear_wt').text('0%');
    $('#bias_badge_wrap').html('<span class="bias-badge-neut">— Select date & analyze</span>');
    $('#bias_reason,#analyzed_at_label').text('');
    $('#breadth_bull_bar,#breadth_bear_bar').css('width','0%');
    $('#breadth_bull_lbl').text('🟢 0');
    $('#breadth_neut_lbl').text('⚪ 0');
    $('#breadth_bear_lbl').text('🔴 0');
    $('#bias_box').removeClass('bullish bearish').addClass('neutral');
});

$('#signal_filter, #sector_filter').change(function() {
    if (allData) applyFilters();
});

// Auto run on load
$(document).ready(function() { setTimeout(runAnalysis, 400); });
</script>
@endpush