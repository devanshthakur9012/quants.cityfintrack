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
    .spinner {
        width:50px; height:50px;
        border:5px solid #f3f3f3; border-top:5px solid #3498db;
        border-radius:50%; animation: spin 1s linear infinite;
    }
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

    /* ── 50 MA badges ───────────────────────────────────── */
    .ma-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Exit badge ─────────────────────────────────────── */
    .exit-badge { background:linear-gradient(135deg,#a855f7,#7c3aed); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Profit / P&L ───────────────────────────────────── */
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

    /* ── Stats boxes (light) ────────────────────────────── */
    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.1); transition:transform .2s; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#666; font-size:10px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box strong { display:block; font-size:1.4rem; font-weight:700; margin-top:3px; }

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
    .aligned-rule { background:rgba(0,210,255,0.08); border:1px solid rgba(0,210,255,0.25); border-radius:8px; padding:6px 12px; margin-bottom:12px; font-size:10px; color:rgba(255,255,255,0.75); display:flex; gap:20px; flex-wrap:wrap; }
    .aligned-rule span strong { color:#00d2ff; }

    /* ── Stats boxes (dark) ─────────────────────────────── */
    .stats-box-dark { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); padding:10px 8px; border-radius:10px; text-align:center; border-left:4px solid #00d2ff; margin-bottom:12px; transition:transform .2s; }
    .stats-box-dark:hover { transform:translateY(-2px); }
    .stats-box-dark small  { display:block; color:rgba(255,255,255,0.55); font-size:9px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box-dark strong { display:block; font-size:1.1rem; font-weight:700; margin-top:4px; color:white; }
    .stats-box-dark.green  { border-left-color:#28a745; }
    .stats-box-dark.red    { border-left-color:#dc3545; }
    .stats-box-dark.cyan   { border-left-color:#00d2ff; }
    .stats-box-dark.orange { border-left-color:#fd7e14; }
    .stats-box-dark.purple { border-left-color:#6f42c1; }
    .stats-box-dark.gold   { border-left-color:#ffc107; }

    /* ── Misc ───────────────────────────────────────────── */
    .page-header { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4); }
    .ratio-badge { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:3px 5px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; }
    .new-feature-badge { background:linear-gradient(135deg,#f093fb,#f5576c); color:white; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }
    /* Next series badge — orange to distinguish */
    .next-series-badge { background:linear-gradient(135deg,#f39c12,#e67e22); color:white; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }

    /* ── Sticky first 3 columns ─────────────────────────── */
    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .custom--table { min-width:2100px; }
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

    /* ── Exit column ────────────────────────────────────── */
    .th-exit { background:rgba(168,85,247,0.15); color:#a855f7 !important; }

    /* ── MM Trap ────────────────────────────────────────── */
    .mm-call-trap { background:linear-gradient(135deg,#ff4757,#c0392b); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
    .mm-put-trap  { background:linear-gradient(135deg,#ffa502,#e67e22); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
    .mm-both-trap { background:linear-gradient(135deg,#a855f7,#7c3aed); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
    .mm-no-trap   { color:#aaa; font-size:9px; }
    .th-mmtrap    { background:rgba(168,85,247,0.1); color:#a855f7 !important; }

    .strength-score-1 { background:#6c757d; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-score-2 { background:#3a7bd5; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-score-3 { background:#f39c12; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-score-4 { background:#27ae60; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-score-5 { background:linear-gradient(135deg,#e74c3c,#c0392b); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- ── Header ─────────────────────────────────────────── --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }}
                        <span class="next-series-badge">NEXT SERIES</span>
                        <span class="new-feature-badge">EOD 3PM → BTST</span>
                    </h4>
                    <p>Next expiry OI &nbsp;|&nbsp; Buy @ Today 15:00 close &nbsp;|&nbsp; Window: Today 15:15 → Next day 09:30 &nbsp;|&nbsp; Exit: Next day 09:30 open</p>
                </div>
                <div>
                    <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm mr-2"><i class="fas fa-chart-bar"></i> Current Series</a>
                    <a href="{{ route('oiiv-auto.config') }}"        class="btn btn-light btn-sm mr-2"><i class="fas fa-cog"></i> Configs</a>
                    <a href="{{ route('oiiv-auto.index') }}"         class="btn btn-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
                </div>
            </div>
        </div>

        {{-- ── Logic Alert ─────────────────────────────────────── --}}
        <div class="alert" style="background:linear-gradient(135deg,#f39c12,#e67e22); color:white; border:none; border-radius:12px; margin-bottom:20px; padding:15px;">
            <h6 style="color:white; margin-bottom:10px; font-size:14px;"><i class="fas fa-info-circle"></i> <strong>Next Series Logic — OI from NEXT expiry (e.g. April while March is live)</strong></h6>
            <div class="row mb-1">
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>📊 OI Analysis (Prev 15:00 → Today 14:45)</strong></small>
                    <ul style="font-size:10px; margin-top:5px;">
                        <li><strong>CE ↑ + PE ↓</strong> → BEARISH</li>
                        <li><strong>CE ↓ + PE ↑</strong> → BULLISH</li>
                        <li><strong>Both ↑</strong> → CE%>PE% = BEARISH</li>
                        <li><strong>Both ↓</strong> → CE%&lt;PE% = BULLISH</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>⏰ Entry & Exit</strong></small>
                    <ul style="font-size:10px; margin-top:5px;">
                        <li>Signal detected @ 3 PM today</li>
                        <li><strong>Buy ₹</strong> = Next series ATM close @ 14:45</li>
                        <li><strong>Exit ₹</strong> = Next trading day 09:30 open</li>
                        <li>Weekend/holiday → skipped automatically</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>🏆 Rank = |CE%−PE%|</strong></small>
                    <ul style="font-size:10px; margin-top:5px;">
                        <li>Rank 1 — diff > 40</li>
                        <li>Rank 2 — diff > 25</li>
                        <li>Rank 3 — diff > 10</li>
                        <li>Rank 4 — diff > 5</li>
                        <li>Normal — diff ≤ 5</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>💰 Price Window (Today 15:00 → Next 09:30)</strong></small>
                    <ul style="font-size:10px; margin-top:5px;">
                        <li><strong>Buy ₹</strong> = Today 14:45 close (next series ATM)</li>
                        <li><strong>Exit ₹</strong> = Next day 09:30 open</li>
                        <li><strong>High ₹</strong> = Max high in window (15:15→09:30)</li>
                        <li><strong>Low ₹</strong> = Min low in window (15:15→09:30)</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- ── Filters ──────────────────────────────────────────── --}}
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
                    <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg ml-2" style="min-width:150px; font-size:13px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Stats Row 1 ──────────────────────────────────────── --}}
        <div class="row">
            <div class="col-md-2"><div class="stats-box"><small>Total Records</small><strong id="total_records" class="text-dark">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE</small><strong id="buy_ce_count" style="color:#28a745;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE</small><strong id="buy_pe_count" style="color:#dc3545;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>WAIT</small><strong id="wait_count" style="color:#ffc107;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BULLISH</small><strong id="strong_bullish_count" style="color:#28a745;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BEARISH</small><strong id="strong_bearish_count" style="color:#dc3545;">0</strong></div></div>
        </div>

        {{-- ── Stats Row 2: Performance ─────────────────────────── --}}
        <div class="row mb-3">
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#6c757d;"><small>Avg Investment</small><strong id="avg_investment" class="text-dark" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Exit Total P/L</small><strong id="exit_total_pl" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Avg Exit ROI</small><strong id="exit_avg_roi" style="color:#a855f7; font-size:1rem;">0%</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>📈 High Total P/L</small><strong id="high_total_pl" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#fd7e14;"><small>📉 Low Total P/L</small><strong id="low_total_pl" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>📊 Trade Count</small><strong id="high_trades" style="color:#17a2b8; font-size:1rem;">0</strong></div></div>
        </div>

        {{-- ── Aligned Signals Panel ────────────────────────────── --}}
        <div class="aligned-section">
            <div class="aligned-section-header">
                <span style="font-size:20px;">🎯</span>
                <h6>Aligned Signals Only — Sentiment + 50MA Confirmed</h6>
                <span class="aligned-tag">High Confidence</span>
            </div>
            <div class="aligned-rule">
                <span>✅ <strong>BUY CE Confirmed</strong> = Sentiment BULLISH + 50MA BULLISH (price above MA)</span>
                <span>✅ <strong>BUY PE Confirmed</strong> = Sentiment BEARISH + 50MA BEARISH (price below MA)</span>
                <span style="color:rgba(255,255,255,0.45);">❌ Mismatched signals excluded</span>
            </div>
            <div class="row">
                <div class="col-6 col-md-2"><div class="stats-box-dark cyan"><small>🎯 Aligned Total</small><strong id="aligned_count">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark green"><small>📈 BUY CE Aligned</small><strong id="aligned_buy_ce" style="color:#28a745;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark red"><small>📉 BUY PE Aligned</small><strong id="aligned_buy_pe" style="color:#dc3545;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark gold"><small>💰 Avg Investment</small><strong id="aligned_avg_inv" style="color:#ffc107;">₹0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#a855f7;"><small>🚪 Exit Win Rate</small><strong id="aligned_exit_win_rate" style="color:#a855f7;">0%</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#adb5bd;"><small>📊 High Win Rate</small><strong id="aligned_win_rate" style="color:#adb5bd;">0%</strong></div></div>
            </div>
            <div class="row">
                <div class="col-6 col-md-3"><div class="stats-box-dark purple"><small>🚪 Exit Total P/L</small><strong id="aligned_exit_pl">₹0</strong></div></div>
                <div class="col-6 col-md-3"><div class="stats-box-dark purple"><small>🚪 Exit Avg ROI</small><strong id="aligned_exit_roi">0%</strong></div></div>
                <div class="col-6 col-md-3"><div class="stats-box-dark cyan"><small>📈 High Total P/L</small><strong id="aligned_high_pl">₹0</strong></div></div>
                <div class="col-6 col-md-3"><div class="stats-box-dark orange"><small>📉 Low Total P/L</small><strong id="aligned_low_pl">₹0</strong></div></div>
            </div>
        </div>

        {{-- ── Table ────────────────────────────────────────────── --}}
        <div style="position:relative; min-height:400px;">
            <div class="loading-overlay" id="loading-overlay" style="display:none;">
                <div class="spinner"></div>
                <div class="loading-text">Loading next series data...</div>
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
                            <th>Condition</th>
                            <th>Sentiment</th>
                            <th class="th-mmtrap">MM Trap<br><small style="font-weight:400;opacity:.7;">Wall Break</small></th>
                            <th class="th-mmtrap">Walls<br><small style="font-weight:400;opacity:.7;">Call / Put</small></th>
                            <th>Strong Side</th>
                            <th>Strength</th>
                            <th>Score<br><small style="font-weight:400;opacity:.7;">1-5</small></th>
                            <th>Action</th>
                            <th>P/C Ratio</th>
                            <th>Option</th>
                            <th>Investment</th>
                            <th>Buy ₹<br><small style="font-weight:400;opacity:.7;">Today 14:45</small></th>
                            <th class="th-exit">Exit ₹<br><small style="font-weight:400;opacity:.8;">Next 09:30 open</small></th>
                            <th class="th-exit">Exit P/L</th>
                            <th class="th-exit">Exit ROI%</th>
                            <th>High ₹<br><small style="font-weight:400;opacity:.7;">15:15→09:30</small></th>
                            <th>High P/L</th>
                            <th>High ROI%</th>
                            <th>Low ₹<br><small style="font-weight:400;opacity:.7;">15:15→09:30</small></th>
                            <th>Low P/L</th>
                            <th>Low ROI%</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="28" class="text-center py-5">
                                <i class="fas fa-chart-pie" style="font-size:3rem; opacity:0.5;"></i>
                                <p style="font-size:1.1rem; margin-top:20px;">Click <strong>"View Data"</strong> to load next series signals</p>
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

    function toggleLoading(show, msg = 'Loading data...') {
        if (show) { $('#loading-overlay .loading-text').text(msg); $('#loading-overlay').show(); }
        else       { $('#loading-overlay').hide(); }
    }

    $(document).ready(function () {
        loadSymbols();
        setTimeout(() => runAnalysis(), 500);
    });

    function loadSymbols() {
        $.ajax({
            url: '{{ route("next-series-oiiv.symbols") }}', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                let opts = '';
                res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
                $('#symbol_filter').html(opts);
            }
        });
    }

    function runAnalysis() {
        const fromDate = $('#from_date').val();
        const toDate   = $('#to_date').val();
        const symbols  = $('#symbol_filter').val() || [];
        const action   = $('#action_filter').val();

        if (!fromDate || !toDate) { alert('Please select both dates'); return; }

        toggleLoading(true, 'Loading next series signals...');
        analysisData = [];

        $.ajax({
            url: '{{ route("next-series-oiiv.analyze-pece") }}', type: 'GET',
            data: { from_date: fromDate, to_date: toDate, symbols, filter_action: action },
            success: function (res) {
                if (res.success && res.data && res.data.length > 0) {
                    analysisData = res.data;
                    displayAnalysisTable();
                    updateStatistics();
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

    /* ── Badge helpers — identical to current series ──────────── */
    function getStrengthBadge(strengthRank, sentiment) {
        if (strengthRank === 'Normal') return `<span class="strength-normal">Normal</span>`;
        const n    = (strengthRank || '').replace('Rank ', '');
        const bull = sentiment === 'BULLISH';
        return `<span class="${bull ? 'strength-bullish' : 'strength-bearish'}">${bull ? '🟢 BULL' : '🔴 BEAR'} (R${n})</span>`;
    }

    function getStrengthScore(row) {
        const ce   = parseFloat(row.ce_oi_change_pct) || 0;
        const pe   = parseFloat(row.pe_oi_change_pct) || 0;
        const diff = parseFloat(row.strength_diff)    || 0;
        if (ce < 0 && pe < 0)              return { score: 1, label: '⚫ Very Weak',   cls: 'strength-score-1' };
        if (ce > 10 && pe > 10 && diff > 5) return { score: 5, label: '🔥 Very Strong', cls: 'strength-score-5' };
        if (ce > 5  && pe > 5  && diff > 4) return { score: 4, label: '💪 Strong',      cls: 'strength-score-4' };
        if (ce > 0  && pe > 0  && diff > 1.5) return { score: 3, label: '📊 Moderate',  cls: 'strength-score-3' };
        return                               { score: 2, label: '🔵 Weak',        cls: 'strength-score-2' };
    }

    function getStrongerBadge(side) {
        if (side === 'CLEAR') return '<span class="text-muted" style="font-size:12px;font-weight:600;">—</span>';
        if (side === 'CE')    return '<span class="badge badge-warning" style="font-size:10px;font-weight:700;color:#155724;">CE 💪</span>';
        if (side === 'PE')    return '<span class="badge badge-danger"  style="font-size:10px;font-weight:700;">PE 💪</span>';
        return '<span class="badge badge-secondary" style="font-size:10px;">EQUAL</span>';
    }

    function getMmTrapBadge(mm) {
        if (!mm || (!mm.call_trap && !mm.put_trap)) return '<span class="mm-no-trap">&mdash;</span>';
        if (mm.call_trap && mm.put_trap) return '<span class="mm-both-trap">⚠ BOTH TRAP</span>';
        if (mm.call_trap) return '<span class="mm-call-trap">🔴 CALL TRAP</span>';
        if (mm.put_trap)  return '<span class="mm-put-trap">🟠 PUT TRAP</span>';
        return '<span class="mm-no-trap">&mdash;</span>';
    }

    function getMmWallsCell(mm) {
        if (!mm || (!mm.call_wall && !mm.put_wall)) return '<span class="mm-no-trap">&mdash;</span>';
        const cw = mm.call_wall ? `<span style="color:#ff6b6b;font-size:10px;font-weight:700;">C:${Math.round(mm.call_wall).toLocaleString('en-IN')}</span>` : '';
        const pw = mm.put_wall  ? `<span style="color:#51cf66;font-size:10px;font-weight:700;">P:${Math.round(mm.put_wall).toLocaleString('en-IN')}</span>`  : '';
        return [cw, pw].filter(Boolean).join('<br>');
    }

    function isAligned(row) {
        const sent = row.final_sentiment;
        const ma   = row.fut_50ma_signal;
        return (sent === 'BULLISH' && ma === 'BULLISH') || (sent === 'BEARISH' && ma === 'BEARISH');
    }

    function fmtOI(val) {
        const n = Number(val) || 0;
        if (n >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
        if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
        return n.toString();
    }

    /* ── Profit cell renderer ─────────────────────────────────── */
    function applyProfitToRow(item) {
        const idx = item.index;
        const allCols = `.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},` +
                        `.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},` +
                        `.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},` +
                        `.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`;

        if (item.error === 'WAIT') { $(allCols).html('<span class="text-muted" style="font-size:10px;">WAIT</span>'); return; }

        if (item.error) {
            const eb = `<span class="badge badge-warning" style="font-size:9px;" title="${item.error}">⚠ ${item.error}</span>`;
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
        $(`.pc-exit-pl-${idx}`).html(item.exit_price > 0  ? plHtml(item.exit_pl   || 0) : '<span class="text-muted">—</span>');
        $(`.pc-exit-roi-${idx}`).html(item.exit_price > 0 ? roiHtml(item.exit_roi || 0) : '<span class="text-muted">—</span>');
        $(`.pc-high-${idx}`).html(item.high_price > 0 ? `<strong style="color:#17a2b8;">₹${Number(item.high_price).toFixed(2)}</strong>${item.high_time ? `<br><small style="color:#6c757d;font-size:9px;">${item.high_time}</small>` : ''}` : '<span class="text-muted">—</span>');
        $(`.pc-high-pl-${idx}`).html(plHtml(item.high_pl || 0));
        $(`.pc-high-roi-${idx}`).html(roiHtml(item.high_roi || 0));
        $(`.pc-low-${idx}`).html(item.low_price > 0 ? `<strong style="color:#fd7e14;">₹${Number(item.low_price).toFixed(2)}</strong>${item.low_time ? `<br><small style="color:#6c757d;font-size:9px;">${item.low_time}</small>` : ''}` : '<span class="text-muted">—</span>');
        $(`.pc-low-pl-${idx}`).html(plHtml(item.low_pl || 0));
        $(`.pc-low-roi-${idx}`).html(roiHtml(item.low_roi || 0));
    }

    function renderNoProfitRow(idx) {
        const dash = '<span class="text-muted">—</span>';
        $(`.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`).html(dash);
    }

    /* ── Profit stats ─────────────────────────────────────────── */
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
        $('#high_trades').text(count);
        $('#exit_total_pl').html(`<span class="${plCls(exitTotalPL)}">${fmt(exitTotalPL)}</span>`);
        $('#exit_avg_roi').html(`<span class="${plCls(exitAvgRoi)}">${fmtRoi(exitAvgRoi)}</span>`);
        $('#high_total_pl').html(`<span class="${plCls(highTotalPL)}">${fmt(highTotalPL)}</span>`);
        $('#low_total_pl').html(`<span class="${plCls(lowTotalPL)}">${fmt(lowTotalPL)}</span>`);

        const alignedTrades = trades.filter(d => { const row = analysisData[d.index]; return row && isAligned(row); });
        updateAlignedStats(alignedTrades);
    }

    function updateAlignedStats(alignedTrades) {
        const count = alignedTrades.length;
        if (count === 0) { resetAlignedStats(); return; }

        const buyCE      = alignedTrades.filter(d => { const r = analysisData[d.index]; return r && r.trade_action === 'BUY CE'; }).length;
        const buyPE      = alignedTrades.filter(d => { const r = analysisData[d.index]; return r && r.trade_action === 'BUY PE'; }).length;
        const avgInv     = alignedTrades.reduce((s, d) => s + (d.investment || 0), 0) / count;
        const exitTotalPL = alignedTrades.reduce((s, d) => s + (d.exit_pl  || 0), 0);
        const exitAvgRoi  = alignedTrades.reduce((s, d) => s + (d.exit_roi || 0), 0) / count;
        const highTotalPL = alignedTrades.reduce((s, d) => s + (d.high_pl  || 0), 0);
        const lowTotalPL  = alignedTrades.reduce((s, d) => s + (d.low_pl   || 0), 0);
        const exitWins    = alignedTrades.filter(d => (d.exit_pl  || 0) > 0).length;
        const highWins    = alignedTrades.filter(d => (d.high_pl  || 0) > 0).length;
        const exitWinPct  = ((exitWins / count) * 100).toFixed(1);
        const highWinPct  = ((highWins / count) * 100).toFixed(1);

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

    function resetAlignedStats() {
        $('#aligned_count,#aligned_buy_ce,#aligned_buy_pe').text('0');
        $('#aligned_avg_inv').text('₹0');
        $('#aligned_exit_win_rate,#aligned_win_rate').text('0%');
        $('#aligned_exit_pl,#aligned_high_pl,#aligned_low_pl').text('₹0');
        $('#aligned_exit_roi').text('0%');
    }

    /* ── Main table render ────────────────────────────────────── */
    function displayAnalysisTable() {
        if (!analysisData || analysisData.length === 0) return;
        let html = '';

        analysisData.forEach(function (row, i) {
            let condCls = 'condition-flat';
            const ss = getStrengthScore(row);
            if (row.oi_condition) {
                if      (row.oi_condition.includes('CE ↑ + PE ↓')) condCls = 'condition-ce-up-pe-down';
                else if (row.oi_condition.includes('CE ↓ + PE ↑')) condCls = 'condition-ce-down-pe-up';
                else if (row.oi_condition.includes('Both ↑'))       condCls = 'condition-both-up';
                else if (row.oi_condition.includes('Both ↓'))       condCls = 'condition-both-down';
            }
            const condBadge = row.oi_condition ? `<span class="${condCls}">${row.oi_condition}</span>` : '<span class="condition-flat">N/A</span>';
            const sentBadge = row.final_sentiment === 'BULLISH'
                ? '<span class="sentiment-bullish">🟢 BULLISH</span>'
                : row.final_sentiment === 'BEARISH'
                    ? '<span class="sentiment-bearish">🔴 BEARISH</span>'
                    : '<span class="sentiment-neutral">⚪ NEUTRAL</span>';
            const actBadge = row.trade_action === 'BUY CE'
                ? '<span class="action-buy-ce">📈 BUY CE</span>'
                : row.trade_action === 'BUY PE'
                    ? '<span class="action-buy-pe">📉 BUY PE</span>'
                    : '<span class="action-wait">⏸ WAIT</span>';

            const rowStyle = isAligned(row) ? 'style="background:rgba(0,210,255,0.06); outline:1px solid rgba(0,210,255,0.25);"' : '';
            const ceCls    = row.ce_oi_change_pct > 0 ? 'text-success' : 'text-danger';
            const peCls    = row.pe_oi_change_pct > 0 ? 'text-success' : 'text-danger';
            const exitTd   = 'style="background:rgba(168,85,247,0.06);"';

            html += `
            <tr ${rowStyle}>
                <td><strong>${i + 1}</strong>${isAligned(row) ? ' <span title="Aligned" style="color:#00d2ff;font-size:10px;">🎯</span>' : ''}</td>
                <td><strong>${row.date}</strong></td>
                <td><strong style="color:#f39c12;">${row.symbol}</strong><br></td>
                <td><strong>${fmtOI(row.ce_oi)}</strong><br><small style="color:#aaa;font-size:9px;">${(row.ce_oi||0).toLocaleString()}</small></td>
                <td class="${ceCls}"><strong>${row.ce_oi_change_pct > 0 ? '+' : ''}${Number(row.ce_oi_change_pct).toFixed(2)}%</strong></td>
                <td><strong>${fmtOI(row.pe_oi)}</strong><br><small style="color:#aaa;font-size:9px;">${(row.pe_oi||0).toLocaleString()}</small></td>
                <td class="${peCls}"><strong>${row.pe_oi_change_pct > 0 ? '+' : ''}${Number(row.pe_oi_change_pct).toFixed(2)}%</strong></td>
                <td>${condBadge}</td>
                <td>${sentBadge}</td>
                <td class="th-mmtrap" title="${row.mm_trap && row.mm_trap.detail ? row.mm_trap.detail : ''}">${getMmTrapBadge(row.mm_trap)}</td>
                <td class="th-mmtrap">${getMmWallsCell(row.mm_trap)}</td>
                <td>${getStrongerBadge(row.stronger_side)}</td>
                <td>${getStrengthBadge(row.strength_rank, row.final_sentiment)}</td>
                <td><span class="${ss.cls}" title="Score ${ss.score}/5">${ss.label}</span></td>
                <td>${actBadge}</td>
                <td><span class="ratio-badge">${row.pe_ce_ratio}</span></td>
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

    /* ── Profit AJAX — uses next-series-oiiv.calculate-profit ─── */
    function loadProfitData() {
        const signals = analysisData
            .map((row, idx) => ({
                index        : idx,
                date         : row.date,
                symbol       : row.symbol,
                trade_action : row.trade_action,
                spot_price   : row.fut_price_today || row.spot_price || 0,
            }))
            .filter(r => r.trade_action === 'BUY CE' || r.trade_action === 'BUY PE');

        analysisData.forEach((row, idx) => {
            if (row.trade_action !== 'BUY CE' && row.trade_action !== 'BUY PE') renderNoProfitRow(idx);
        });

        if (signals.length === 0) { resetAlignedStats(); return; }

        $.ajax({
            url : '{{ route("next-series-oiiv.calculate-profit") }}',
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

    /* ── Stats counters ───────────────────────────────────────── */
    function updateStatistics() {
        if (!analysisData || !analysisData.length) { resetStatistics(); return; }
        $('#total_records').text(analysisData.length);
        $('#buy_ce_count').text(analysisData.filter(r => r.trade_action === 'BUY CE').length);
        $('#buy_pe_count').text(analysisData.filter(r => r.trade_action === 'BUY PE').length);
        $('#wait_count').text(analysisData.filter(r => r.trade_action === 'WAIT').length);
        $('#strong_bullish_count').text(analysisData.filter(r => r.final_sentiment === 'BULLISH').length);
        $('#strong_bearish_count').text(analysisData.filter(r => r.final_sentiment === 'BEARISH').length);
    }

    function resetStatistics() {
        $('#total_records,#buy_ce_count,#buy_pe_count,#wait_count,#strong_bullish_count,#strong_bearish_count').text('0');
        $('#avg_investment').text('₹0');
        $('#high_trades').text('0');
        $('#exit_total_pl,#high_total_pl,#low_total_pl').text('₹0');
        $('#exit_avg_roi').text('0%');
    }

    function showNoData(message) {
        $('#analysis-tbody').html(`
            <tr><td colspan="28" class="text-center py-5">
                <i class="fas fa-info-circle" style="color:#17a2b8; font-size:3rem;"></i>
                <p class="text-info" style="margin-top:20px;">${message}</p>
            </td></tr>`);
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter,#action_filter').val('');
        analysisData = [];
        showNoData('Click "View Data" to load next series signals');
        resetStatistics();
        resetAlignedStats();
        setTimeout(() => runAnalysis(), 300);
    }

    $('#run_analysis').click(() => runAnalysis());
    $('#reset_filters').click(() => resetFilters());
</script>
@endpush