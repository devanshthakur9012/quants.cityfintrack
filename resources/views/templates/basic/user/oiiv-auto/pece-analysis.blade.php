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

    /* ── Stats boxes (dark, for aligned section) ────────── */
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

    /* ── Exit column header highlight ───────────────────── */
    .th-exit { background:rgba(168,85,247,0.15); color:#a855f7 !important; }
    /* ── MM Trap ───────────────────────────────────────────── */
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
    .th-index { background:rgba(102,126,234,0.08); color:#667eea !important; }
.b-yes-n50 { background:#e8f5e9; color:#1b5e20; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
.b-yes-bn  { background:#e3f2fd; color:#0d47a1; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
.b-yes-sx  { background:#fff3e0; color:#bf360c; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
.b-no      { background:#f5f5f5; color:#aaa;    padding:2px 7px; border-radius:4px; font-size:9px; display:inline-block; }
.n50-star  { background:linear-gradient(135deg,#667eea,#764ba2); color:white; font-size:8px; padding:1px 5px; border-radius:10px; margin-left:3px; vertical-align:middle; }
/* ── Weighted OI Dominance badges ───────────────────────── */
.woi-strong-bull { background:#1b5e20; color:white; padding:3px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.woi-bull        { background:#28a745; color:white; padding:3px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.woi-strong-bear { background:#7f0000; color:white; padding:3px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.woi-bear        { background:#dc3545; color:white; padding:3px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.woi-neutral     { background:#6c757d; color:white; padding:3px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.th-woi          { background:rgba(255,193,7,0.12); }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- ── Header ─────────────────────────────────────────── --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }} <span class="new-feature-badge">EOD 3PM → BTST</span></h4>
                    <p>Buy @ Today 15:00 close &nbsp;|&nbsp; Window: Today 15:15 → Next day 09:30 &nbsp;|&nbsp; Exit: Next day 09:30 open (actual sell)</p>
                </div>
                <div>
                    <a href="{{ route('oiiv-auto.config') }}" class="btn btn-light btn-sm mr-2"><i class="fas fa-cog"></i> Configs</a>
                    <a href="{{ route('9to12.pece-analysis') }}" class="btn btn-light btn-sm mr-2"><i class="fas fa-clock"></i> 9:30→12:15</a>
                    <a href="{{ route('oiiv-auto.index') }}" class="btn btn-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
                </div>
            </div>
        </div>

        {{-- ── Logic Alert ─────────────────────────────────────── --}}
        <div class="alert" style="background:linear-gradient(135deg,#667eea,#764ba2); color:white; border:none; border-radius:12px; margin-bottom:20px; padding:15px;">
            <h6 style="color:white; margin-bottom:10px; font-size:14px;"><i class="fas fa-info-circle"></i> <strong>Logic Summary:</strong></h6>
            <div class="row mb-1">
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>📊 OI Analysis (Prev 15:15 → Today 15:00)</strong></small>
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
                        <li><strong>Buy ₹</strong> = ATM close @ today 15:00</li>
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
                        <li><strong>Buy ₹</strong> = Today 15:00 close</li>
                        <li><strong>Exit ₹</strong> = Next day 09:30 open (actual sell)</li>
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
        
        {{-- ── Nifty50 Trend Panel ──────────────────────────────── --}}
        <div id="nifty50-trend-section" style="border:1px solid rgba(102,126,234,0.3); border-radius:12px; padding:14px 16px; margin-bottom:16px; background:rgba(102,126,234,0.05);">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
                <span style="font-size:18px;">📊</span>
                <h6 style="margin:0; font-size:13px; font-weight:700;">Nifty50 Stock Trend — Derived from OI Signals</h6>
                <span style="font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; background:#e3f2fd; color:#0d47a1;">Live Derived</span>
            </div>
            <div style="font-size:10px; color:#888; border:1px solid rgba(102,126,234,0.2); border-radius:6px; padding:6px 12px; margin-bottom:12px; display:flex; gap:16px; flex-wrap:wrap;">
                <span>✅ <strong>Bullish</strong> = BUY CE action</span>
                <span> <strong>Bearish</strong> = BUY PE action</span>
                <span>⏸ <strong>Neutral</strong> = WAIT signal</span>
                <span style="opacity:.6;">Only Nifty50 constituent stocks counted here</span>
            </div>
            <div class="row mb-2">
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>Nifty50 in Data</small><strong id="n50_in_data" style="color:#17a2b8;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>📈 Bullish</small><strong id="n50_bull" style="color:#28a745;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>📉 Bearish</small><strong id="n50_bear" style="color:#dc3545;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>⏸ Neutral</small><strong id="n50_wait" style="color:#ffc107;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🎯 Trend Signal</small><strong id="n50_trend_badge">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#6c757d;"><small>📊 Bull %</small><strong id="n50_bull_pct">0%</strong></div></div>
            </div>
            <div style="display:none;background:white; border:1px solid #e0e0e0; border-radius:8px; padding:10px 14px;">
                <div style="display:flex; justify-content:space-between; font-size:11px; color:#888; margin-bottom:6px;">
                    <span id="n50_bar_bull_label">Bullish 0%</span>
                    <span id="n50_bar_bear_label">Bearish 0%</span>
                </div>
                <div style="height:12px; border-radius:6px; background:#eee; overflow:hidden; position:relative;">
                    <div id="n50_bull_bar" style="height:100%; border-radius:6px 0 0 6px; background:#28a745; width:0%; transition:width .5s;"></div>
                    <div id="n50_bear_bar" style="height:100%; border-radius:0 6px 6px 0; background:#dc3545; position:absolute; top:0; right:0; width:0%; transition:width .5s;"></div>
                </div>
                <div style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:12px; font-weight:700;">
                    <span id="n50_verdict_icon"></span>
                    <span id="n50_verdict_text">Loading...</span>
                    <span id="n50_verdict_sub" style="font-weight:400; color:#888; font-size:11px;"></span>
                </div>
            </div>
        </div>

        {{-- ── BankNifty Trend Panel ──────────────────────────────── --}}
        <div id="banknifty-trend-section" style="border:1px solid rgba(13,71,161,0.3); border-radius:12px; padding:14px 16px; margin-bottom:16px; background:rgba(13,71,161,0.04);">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
                <span style="font-size:18px;">🏦</span>
                <h6 style="margin:0; font-size:13px; font-weight:700;">BankNifty Stock Trend — Derived from OI Signals</h6>
                <span style="font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; background:#e3f2fd; color:#0d47a1;">Live Derived</span>
            </div>
            <div style="font-size:10px; color:#888; border:1px solid rgba(13,71,161,0.2); border-radius:6px; padding:6px 12px; margin-bottom:12px; display:flex; gap:16px; flex-wrap:wrap;">
                <span>✅ <strong>Bullish</strong> = BUY CE action</span>
                <span>🔴 <strong>Bearish</strong> = BUY PE action</span>
                <span>⏸ <strong>Neutral</strong> = WAIT signal</span>
                <span style="opacity:.6;">Only BankNifty constituent stocks counted here</span>
            </div>
            <div class="row mb-2">
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#0d47a1;"><small>BankNifty in Data</small><strong id="bn_in_data" style="color:#0d47a1;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>📈 Bullish</small><strong id="bn_bull" style="color:#28a745;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>📉 Bearish</small><strong id="bn_bear" style="color:#dc3545;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>⏸ Neutral</small><strong id="bn_wait" style="color:#ffc107;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🎯 Trend Signal</small><strong id="bn_trend_badge">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#6c757d;"><small>📊 Bull %</small><strong id="bn_bull_pct">0%</strong></div></div>
            </div>
        </div>

        {{-- ── Sensex Trend Panel ──────────────────────────────── --}}
        <div id="sensex-trend-section" style="border:1px solid rgba(191,54,12,0.3); border-radius:12px; padding:14px 16px; margin-bottom:16px; background:rgba(191,54,12,0.04);">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
                <span style="font-size:18px;">📈</span>
                <h6 style="margin:0; font-size:13px; font-weight:700;">Sensex Stock Trend — Derived from OI Signals</h6>
                <span style="font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; background:#fff3e0; color:#bf360c;">Live Derived</span>
            </div>
            <div style="font-size:10px; color:#888; border:1px solid rgba(191,54,12,0.2); border-radius:6px; padding:6px 12px; margin-bottom:12px; display:flex; gap:16px; flex-wrap:wrap;">
                <span>✅ <strong>Bullish</strong> = BUY CE action</span>
                <span>🔴 <strong>Bearish</strong> = BUY PE action</span>
                <span>⏸ <strong>Neutral</strong> = WAIT signal</span>
                <span style="opacity:.6;">Only Sensex constituent stocks counted here</span>
            </div>
            <div class="row mb-2">
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#bf360c;"><small>Sensex in Data</small><strong id="sx_in_data" style="color:#bf360c;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>📈 Bullish</small><strong id="sx_bull" style="color:#28a745;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>📉 Bearish</small><strong id="sx_bear" style="color:#dc3545;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>⏸ Neutral</small><strong id="sx_wait" style="color:#ffc107;">0</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🎯 Trend Signal</small><strong id="sx_trend_badge">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#6c757d;"><small>📊 Bull %</small><strong id="sx_bull_pct">0%</strong></div></div>
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
                            {{-- <th>FUT OI</th>
                            <th>FUT %</th> --}}
                            {{-- <th>Condition</th> --}}
                            <th>Sentiment</th>
                            {{-- <th>50MA</th> --}}
                            {{-- <th>MM Trap<br><small style="font-weight:400;opacity:.7;">Wall Break</small></th> --}}
                            {{-- <th>Walls<br><small style="font-weight:400;opacity:.7;">Call / Put</small></th> --}}
                            {{-- <th>Price Signal<br><small style="font-weight:400;opacity:.7;">Prev→Today</small></th> --}}
                            <th>Gann Bias<br><small style="font-weight:400;opacity:.7;">8-Zone</small></th>
                            <th>Near Level<br><small style="font-weight:400;opacity:.7;">Closest 1/8</small></th>
                            {{-- <th>Strong Side</th> --}}
                            {{-- <th>Strength</th> --}}
                            {{-- <th>Score<br><small style="font-weight:400;opacity:.7;">1-5</small></th> --}}
                            <th>Action</th>
                            {{-- <th>P/C Ratio</th> --}}
                            <th>WOI Signal<br><small style="font-weight:400;opacity:.7;">Weighted OI</small></th>
                            <th>Vol Impact<br><small style="font-weight:400;opacity:.7;">W×OI%×Diff</small></th>
                            <th>Option</th>
                            <th>Investment</th>
                            <th>Buy ₹<br><small style="font-weight:400;opacity:.7;">Today 15:00</small></th>
                            <th>Exit ₹<br><small style="font-weight:400;opacity:.8;">Next 09:30 open</small></th>
                            <th>Exit P/L</th>
                            <th>Exit ROI%</th>
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
                            <td colspan="30" class="text-center py-5">
                                <i class="fas fa-chart-pie" style="font-size:3rem; opacity:0.5;"></i>
                                <p style="font-size:1.1rem; margin-top:20px;">Click <strong>"View Data"</strong> to load signals</p>
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

    const NIFTY50_LIST = new Set([
        'ADANIENT','ADANIPORTS','APOLLOHOSP','ASIANPAINT','AXISBANK',
        'BAJAJ-AUTO','BAJAJFINSV','BAJFINANCE','BEL','BHARTIARTL',
        'CIPLA','COALINDIA','DRREDDY','EICHERMOT','ETERNAL',
        'GRASIM','HCLTECH','HDFCBANK','HDFCLIFE','HINDALCO',
        'HINDUNILVR','ICICIBANK','INDIGO','INFY','ITC',
        'JIOFIN','JSWSTEEL','KOTAKBANK','LT','M&M',
        'MARUTI','MAXHEALTH','NESTLEIND','NTPC','ONGC',
        'POWERGRID','RELIANCE','SBILIFE','SBIN','SHRIRAMFIN',
        'SUNPHARMA','TATACONSUM','TATASTEEL','TCS','TECHM',
        'TITAN','TMPV','TRENT','ULTRACEMCO','WIPRO'
    ]);

    const BANKNIFTY_LIST = new Set([
        'HDFCBANK','ICICIBANK','AXISBANK','SBIN','KOTAKBANK',
        'FEDERALBNK','INDUSINDBK','AUBANK','BANKBARODA','CANBK'
    ]);

    const SENSEX_LIST = new Set([
        'RELIANCE','HDFCBANK','BHARTIARTL','SBIN','TCS',
        'ICICIBANK','INFY','BAJFINANCE','LT','HINDUNILVR',
        'SUNPHARMA','MARUTI','HCLTECH','M&M','AXISBANK',
        'ITC','TITAN','KOTAKBANK','NTPC','ADANIPORTS',
        'ULTRACEMCO','BEL','POWERGRID','BAJAJFINSV','TATASTEEL',
        'ETERNAL','ASIANPAINT','INDIGO','TECHM','TRENT'
    ]);

    // ── Index weightage maps (% weight, normalized) ──────────────
    const NIFTY50_WEIGHTS = {
        'RELIANCE':10.04,'HDFCBANK':7.84,'ICICIBANK':6.78,'INFY':5.26,'BHARTIARTL':4.37,
        'TCS':4.15,'AXISBANK':3.26,'SBIN':3.07,'LT':2.90,'HINDUNILVR':2.58,
        'BAJFINANCE':2.47,'KOTAKBANK':2.40,'TITAN':2.12,'SUNPHARMA':1.96,'MARUTI':1.94,
        'HCLTECH':1.87,'M&M':1.85,'NTPC':1.75,'ADANIPORTS':1.62,'ULTRACEMCO':1.59,
        'POWERGRID':1.46,'BEL':1.39,'BAJAJFINSV':1.33,'ITC':1.30,'TATASTEEL':1.22,
        'ETERNAL':1.18,'ASIANPAINT':1.10,'INDIGO':1.05,'TECHM':0.98,'TRENT':0.92,
        'COALINDIA':0.88,'GRASIM':0.85,'JSWSTEEL':0.82,'HINDALCO':0.78,'WIPRO':0.76,
        'EICHERMOT':0.72,'DRREDDY':0.70,'HDFCLIFE':0.68,'ONGC':0.65,'CIPLA':0.63,
        'BAJAJ-AUTO':0.61,'NESTLEIND':0.58,'JIOFIN':0.55,'SHRIRAMFIN':0.52,
        'TATACONSUM':0.50,'MAXHEALTH':0.48,'ADANIENT':0.45,'SBILIFE':0.43,
        'APOLLOHOSP':0.40,'TMPV':0.38
    };

    const BANKNIFTY_WEIGHTS = {
        'HDFCBANK':19.01,'ICICIBANK':14.11,'AXISBANK':10.01,'SBIN':9.94,
        'KOTAKBANK':9.73,'FEDERALBNK':6.18,'INDUSINDBK':4.80,'AUBANK':4.49,
        'BANKBARODA':4.45,'CANBK':4.06
    };

    const SENSEX_WEIGHTS = {
        'RELIANCE':12.40,'HDFCBANK':7.84,'BHARTIARTL':7.40,'SBIN':6.38,'TCS':6.02,
        'ICICIBANK':5.91,'INFY':3.58,'BAJFINANCE':3.49,'LT':3.37,'HINDUNILVR':3.29,
        'SUNPHARMA':2.76,'MARUTI':2.69,'HCLTECH':2.58,'M&M':2.54,'AXISBANK':2.53,
        'ITC':2.49,'TITAN':2.47,'KOTAKBANK':2.42,'NTPC':2.37,'ADANIPORTS':2.15,
        'ULTRACEMCO':2.12,'BEL':2.09,'POWERGRID':1.83,'BAJAJFINSV':1.78,
        'TATASTEEL':1.64,'ETERNAL':1.52,'ASIANPAINT':1.41,'INDIGO':1.10,
        'TECHM':0.96,'TRENT':0.86
    };

    /**
     * Get weight for a symbol.
     * Priority: Nifty50 weight → BankNifty weight → Sensex weight → 1.0 (equal)
     */
    function getSymbolWeight(symbol) {
        return NIFTY50_WEIGHTS[symbol] || BANKNIFTY_WEIGHTS[symbol] || SENSEX_WEIGHTS[symbol] || 1.0;
    }

    /**
     * Calculate Weighted OI Dominance for a row.
     * Returns { signal, wtCE, wtPE, dominance, volImpact, cls, label }
     */
    function getWeightedOIDominance(row) {
        const w       = getSymbolWeight(row.symbol);
        const ceOI    = parseFloat(row.ce_oi)            || 0;
        const peOI    = parseFloat(row.pe_oi)            || 0;
        const cePct   = parseFloat(row.ce_oi_change_pct) || 0;
        const pePct   = parseFloat(row.pe_oi_change_pct) || 0;

        const wtCE    = ceOI * w;
        const wtPE    = peOI * w;
        const total   = wtCE + wtPE;

        // Weighted volume impact = weight × OI change% × price move proxy (diff)
        const diff    = Math.abs(cePct - pePct);
        const volImpact = (w * diff).toFixed(2);

        if (total === 0) return {
            signal: 'N/A', wtCE: 0, wtPE: 0, dominance: 0,
            volImpact, cls: 'woi-neutral', label: '—'
        };

        const dominance = (((wtPE - wtCE) / total) * 100).toFixed(1);

        let signal, cls, label;
        const dom = parseFloat(dominance);

        if (dom >= 15)       { signal = 'STRONG BULL'; cls = 'woi-strong-bull'; label = '🟢🟢 S.BULL'; }
        else if (dom >= 5)   { signal = 'BULLISH';     cls = 'woi-bull';        label = '🟢 BULL'; }
        else if (dom <= -15) { signal = 'STRONG BEAR'; cls = 'woi-strong-bear'; label = '🔴🔴 S.BEAR'; }
        else if (dom <= -5)  { signal = 'BEARISH';     cls = 'woi-bear';        label = '🔴 BEAR'; }
        else                 { signal = 'NEUTRAL';      cls = 'woi-neutral';     label = '⚪ NEUT'; }

        return { signal, wtCE, wtPE, dominance, volImpact, cls, label };
    }

    function updateBankNiftyTrend() {
        if (!analysisData || !analysisData.length) { resetBankNiftyTrend(); return; }
        const rows   = analysisData.filter(r => BANKNIFTY_LIST.has(r.symbol));
        const total  = rows.length;
        const bull   = rows.filter(r => r.trade_action === 'BUY CE').length;
        const bear   = rows.filter(r => r.trade_action === 'BUY PE').length;
        const wait   = rows.filter(r => r.trade_action === 'WAIT').length;
        const bullPct = total > 0 ? Math.round((bull / total) * 100) : 0;
        const bearPct = total > 0 ? Math.round((bear / total) * 100) : 0;

        $('#bn_in_data').text(total);
        $('#bn_bull').text(bull);
        $('#bn_bear').text(bear);
        $('#bn_wait').text(wait);
        $('#bn_bull_pct').text(bullPct + '%');

        let trend, icon, color;
        if      (bullPct >= 60)     { trend = 'BANKNIFTY MOSTLY BULLISH'; icon = '🟢'; color = '#28a745'; }
        else if (bearPct >= 60)     { trend = 'BANKNIFTY MOSTLY BEARISH'; icon = '🔴'; color = '#dc3545'; }
        else if (bullPct > bearPct) { trend = 'SLIGHT BULLISH BIAS';      icon = '🟡'; color = '#28a745'; }
        else if (bearPct > bullPct) { trend = 'SLIGHT BEARISH BIAS';      icon = '🟡'; color = '#dc3545'; }
        else                        { trend = 'MIXED / NEUTRAL';           icon = '⚪'; color = '#888'; }

        $('#bn_trend_badge').html(`<span style="color:${color};font-weight:700;">${icon} ${bull >= bear ? 'BULLISH' : 'BEARISH'}</span>`);
    }

    function resetBankNiftyTrend() {
        $('#bn_in_data,#bn_bull,#bn_bear,#bn_wait').text('0');
        $('#bn_bull_pct').text('0%');
        $('#bn_trend_badge').text('—');
    }

    function updateSensexTrend() {
        if (!analysisData || !analysisData.length) { resetSensexTrend(); return; }
        const rows   = analysisData.filter(r => SENSEX_LIST.has(r.symbol));
        const total  = rows.length;
        const bull   = rows.filter(r => r.trade_action === 'BUY CE').length;
        const bear   = rows.filter(r => r.trade_action === 'BUY PE').length;
        const wait   = rows.filter(r => r.trade_action === 'WAIT').length;
        const bullPct = total > 0 ? Math.round((bull / total) * 100) : 0;
        const bearPct = total > 0 ? Math.round((bear / total) * 100) : 0;

        $('#sx_in_data').text(total);
        $('#sx_bull').text(bull);
        $('#sx_bear').text(bear);
        $('#sx_wait').text(wait);
        $('#sx_bull_pct').text(bullPct + '%');

        let trend, icon, color;
        if      (bullPct >= 60)     { trend = 'SENSEX MOSTLY BULLISH'; icon = '🟢'; color = '#28a745'; }
        else if (bearPct >= 60)     { trend = 'SENSEX MOSTLY BEARISH'; icon = '🔴'; color = '#dc3545'; }
        else if (bullPct > bearPct) { trend = 'SLIGHT BULLISH BIAS';   icon = '🟡'; color = '#28a745'; }
        else if (bearPct > bullPct) { trend = 'SLIGHT BEARISH BIAS';   icon = '🟡'; color = '#dc3545'; }
        else                        { trend = 'MIXED / NEUTRAL';        icon = '⚪'; color = '#888'; }

        $('#sx_trend_badge').html(`<span style="color:${color};font-weight:700;">${icon} ${bull >= bear ? 'BULLISH' : 'BEARISH'}</span>`);
    }

    function resetSensexTrend() {
        $('#sx_in_data,#sx_bull,#sx_bear,#sx_wait').text('0');
        $('#sx_bull_pct').text('0%');
        $('#sx_trend_badge').text('—');
    }

    function indexBadge(inIndex, cls) {
        return inIndex
            ? `<span class="${cls}">✓ Yes</span>`
            : '<span class="">—</span>';
    }

    /* ── Loading ──────────────────────────────────────────────── */
    function toggleLoading(show, msg = 'Loading data...') {
        if (show) { $('#loading-overlay .loading-text').text(msg); $('#loading-overlay').show(); }
        else       { $('#loading-overlay').hide(); }
    }

    /* ── Init ─────────────────────────────────────────────────── */
    $(document).ready(function () {
        loadSymbols();
        setTimeout(() => runAnalysis(), 500);
    });

    function loadSymbols() {
        $.ajax({
            url: '{{ route("oiiv-auto.symbols") }}', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                let opts = '';
                res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
                $('#symbol_filter').html(opts);
            }
        });
    }

    /* ── Main analysis call ───────────────────────────────────── */
    function runAnalysis() {
        const fromDate = $('#from_date').val();
        const toDate   = $('#to_date').val();
        const symbols  = $('#symbol_filter').val() || [];
        const action   = $('#action_filter').val();

        if (!fromDate || !toDate) { alert('Please select both dates'); return; }

        toggleLoading(true, 'Loading signals...');
        analysisData = [];

        $.ajax({
            url: '{{ route("oiiv-auto.analyze-pece") }}', type: 'GET',
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

    /* ── Badge helpers ────────────────────────────────────────── */
    function getStrengthBadge(strengthRank, sentiment) {
        if (strengthRank === 'Normal') return `<span class="strength-normal">Normal</span>`;
        const n    = (strengthRank || '').replace('Rank ', '');
        const bull = sentiment === 'BULLISH';
        return `<span class="${bull ? 'strength-bullish' : 'strength-bearish'}">${bull ? 'BULL' : ' BEAR'} (R${n})</span>`;
    }

    function getStrengthScore(row) {
        const ce   = parseFloat(row.ce_oi_change_pct) || 0;
        const pe   = parseFloat(row.pe_oi_change_pct) || 0;
        const diff = parseFloat(row.strength_diff)    || 0;

        // Score 1 — Very Weak: both OI declining
        if (ce < 0 && pe < 0)
            return { score: 1, label: ' Very Weak',   cls: 'strength-score-1' };

        // Score 5 — Very Strong: both > 10%, diff > 5
        if (ce > 10 && pe > 10 && diff > 5)
            return { score: 5, label: '🔥 Very Strong', cls: 'strength-score-5' };

        // Score 4 — Strong: both > 5%, diff > 4
        if (ce > 5 && pe > 5 && diff > 4)
            return { score: 4, label: '💪 Strong',      cls: 'strength-score-4' };

        // Score 3 — Moderate: both positive, diff > 1.5
        if (ce > 0 && pe > 0 && diff > 1.5)
            return { score: 3, label: '📊 Moderate',    cls: 'strength-score-3' };

        // Score 2 — Weak: one side flat/negative, low diff
        return             { score: 2, label: ' Weak',        cls: 'strength-score-2' };
    }

    function getStrongerBadge(side) {
        if (side === 'CLEAR') return '<span class="text-muted" style="font-size:12px;font-weight:600;">—</span>';
        if (side === 'CE')    return '<span class="badge badge-warning" style="font-size:10px;font-weight:700;color:#155724;">CE 💪</span>';
        if (side === 'PE')    return '<span class="badge badge-danger"  style="font-size:10px;font-weight:700;">PE 💪</span>';
        return '<span class="badge badge-secondary" style="font-size:10px;">EQUAL</span>';
    }

    function getMa50Badge(signal) {
        if (!signal || signal === 'N/A') return '<span class="text-muted" style="font-size:11px;">N/A</span>';
        if (signal === 'BULLISH') return '<span class="ma-bullish">Above MA</span>';
        if (signal === 'BEARISH') return '<span class="ma-bearish"> Below MA</span>';
        return '<span class="ma-neutral"> On MA</span>';
    }

    function getMmTrapBadge(mm) {
        if (!mm || (!mm.call_trap && !mm.put_trap)) {
            return '<span class="mm-no-trap">&mdash;</span>';
        }
        if (mm.call_trap && mm.put_trap) return '<span class="mm-both-trap">⚠ BOTH TRAP</span>';
        if (mm.call_trap) return '<span class="mm-call-trap"> CALL TRAP</span>';
        if (mm.put_trap)  return '<span class="mm-put-trap"> PUT TRAP</span>';
        return '<span class="mm-no-trap">&mdash;</span>';
    }

    function getMmWallsCell(mm) {
        if (!mm || (!mm.call_wall && !mm.put_wall)) {
            return '<span class="mm-no-trap">&mdash;</span>';
        }
        const cw = mm.call_wall ? `<span style="color:#ff6b6b;font-size:10px;font-weight:700;">C:${Math.round(mm.call_wall).toLocaleString('en-IN')}</span>` : '';
        const pw = mm.put_wall  ? `<span style="color:#51cf66;font-size:10px;font-weight:700;">P:${Math.round(mm.put_wall).toLocaleString('en-IN')}</span>`  : '';
        return [cw, pw].filter(Boolean).join('<br>');
    }

    function isAligned(row) {
        const sent = row.final_sentiment;
        const ma   = row.fut_50ma_signal;
        return (sent === 'BULLISH' && ma === 'BULLISH') ||
               (sent === 'BEARISH' && ma === 'BEARISH');
    }

    /* ── Profit cell renderer ─────────────────────────────────── */
    function applyProfitToRow(item) {
        const idx = item.index;

        // Selector helper — includes all profit columns including the new exit cols
        const allCols = `.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},` +
                        `.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},` +
                        `.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},` +
                        `.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`;

        if (item.error === 'WAIT') {
            const dash = '<span class="text-muted" style="font-size:10px;">WAIT</span>';
            $(allCols).html(dash);
            return;
        }

        if (item.error) {
            const errBadge = `<span class="badge badge-warning" style="font-size:9px;" title="${item.error}">⚠ ${item.error}</span>`;
            $(`.pc-option-${idx}`).html(item.option_symbol
                ? `<span class="option-symbol-badge">${item.option_symbol}</span>`
                : errBadge);
            $(`.pc-invest-${idx},.pc-buy-${idx},` +
              `.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},` +
              `.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},` +
              `.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`).html(errBadge);
            return;
        }

        const plHtml  = pl  => `<strong class="${pl  >= 0 ? 'profit-positive' : 'profit-negative'}">${pl  >= 0 ? '+' : ''}₹${Math.abs(pl ).toFixed(2)}</strong>`;
        const roiHtml = roi => `<strong class="${roi >= 0 ? 'profit-positive' : 'profit-negative'}">${roi >= 0 ? '+' : ''}${Math.abs(roi).toFixed(2)}%</strong>`;

        $(`.pc-option-${idx}`).html(item.option_symbol
            ? `<span class="option-symbol-badge" title="${item.option_symbol}">${item.option_symbol}</span>`
            : '<span class="text-muted">N/A</span>');

        $(`.pc-invest-${idx}`).html(`<strong>₹${Number(item.investment).toLocaleString()}</strong>`);
        $(`.pc-buy-${idx}`).html(`<strong>₹${Number(item.buy_price).toFixed(2)}</strong>`);

        // ── Exit price (next day 09:30 open) ──────────────────────
        $(`.pc-exit-${idx}`).html(item.exit_price > 0
            ? `<strong style="color:#a855f7;">₹${Number(item.exit_price).toFixed(2)}</strong>`
            : '<span class="text-muted">N/A</span>');
        $(`.pc-exit-pl-${idx}`).html(item.exit_price > 0 ? plHtml(item.exit_pl || 0)   : '<span class="text-muted">—</span>');
        $(`.pc-exit-roi-${idx}`).html(item.exit_price > 0 ? roiHtml(item.exit_roi || 0) : '<span class="text-muted">—</span>');

        // ── High within window ────────────────────────────────────
        $(`.pc-high-${idx}`).html(item.high_price > 0
            ? `<strong style="color:#17a2b8;">₹${Number(item.high_price).toFixed(2)}</strong>` +
              (item.high_time ? `<br><small style="color:#6c757d;font-size:9px;">${item.high_time}</small>` : '')
            : '<span class="text-muted">—</span>');
        $(`.pc-high-pl-${idx}`).html(plHtml(item.high_pl  || 0));
        $(`.pc-high-roi-${idx}`).html(roiHtml(item.high_roi || 0));

        // ── Low within window ─────────────────────────────────────
        $(`.pc-low-${idx}`).html(item.low_price > 0
            ? `<strong style="color:#fd7e14;">₹${Number(item.low_price).toFixed(2)}</strong>` +
              (item.low_time ? `<br><small style="color:#6c757d;font-size:9px;">${item.low_time}</small>` : '')
            : '<span class="text-muted">—</span>');
        $(`.pc-low-pl-${idx}`).html(plHtml(item.low_pl  || 0));
        $(`.pc-low-roi-${idx}`).html(roiHtml(item.low_roi || 0));
    }

    function renderNoProfitRow(idx) {
        const dash = '<span class="text-muted">—</span>';
        $(`.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},` +
          `.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},` +
          `.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},` +
          `.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`).html(dash);
    }

    /* ── Profit stats (all + aligned) ────────────────────────── */
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

        const exitWins   = alignedTrades.filter(d => (d.exit_pl  || 0) > 0).length;
        const highWins   = alignedTrades.filter(d => (d.high_pl  || 0) > 0).length;
        const exitWinPct = ((exitWins / count) * 100).toFixed(1);
        const highWinPct = ((highWins / count) * 100).toFixed(1);

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
            // Condition badge
            let condCls = 'condition-flat';
            const ss = getStrengthScore(row);
            if (row.oi_condition) {
                if      (row.oi_condition.includes('CE ↑ + PE ↓')) condCls = 'condition-ce-up-pe-down';
                else if (row.oi_condition.includes('CE ↓ + PE ↑')) condCls = 'condition-ce-down-pe-up';
                else if (row.oi_condition.includes('Both ↑'))       condCls = 'condition-both-up';
                else if (row.oi_condition.includes('Both ↓'))       condCls = 'condition-both-down';
            }
            const condBadge = row.oi_condition
                ? `<span class="${condCls}">${row.oi_condition}</span>`
                : '<span class="condition-flat">N/A</span>';

            const sentBadge = row.final_sentiment === 'BULLISH'
                ? '<span class="sentiment-bullish">BULLISH</span>'
                : row.final_sentiment === 'BEARISH'
                    ? '<span class="sentiment-bearish"> BEARISH</span>'
                    : '<span class="sentiment-neutral"> NEUTRAL</span>';

            const actBadge = row.trade_action === 'BUY CE'
                ? '<span class="action-buy-ce">📈 BUY CE</span>'
                : row.trade_action === 'BUY PE'
                    ? '<span class="action-buy-pe">📉 BUY PE</span>'
                    : '<span class="action-wait">⏸ WAIT</span>';

            const rowStyle = isAligned(row)
                ? 'style="background:rgba(0,210,255,0.06); outline:1px solid rgba(0,210,255,0.25);"'
                : '';

            const ceCls  = row.ce_oi_change_pct  > 0 ? 'text-success' : 'text-danger';
            const peCls  = row.pe_oi_change_pct  > 0 ? 'text-success' : 'text-danger';
            const futCls = row.fut_oi_change_pct > 0 ? 'text-success' : 'text-danger';

            // Exit cols get a purple-tinted background
            const exitTd = 'style="background:rgba(168,85,247,0.06);"';

            // <td>${getMa50Badge(row.fut_50ma_signal)}</td>

            // <td>
            //     <strong>${fmtOI(row.fut_oi)}</strong><br>
            //     <small style="color:#aaa;font-size:9px;">${(row.fut_oi||0).toLocaleString()}</small>
            // </td>
            // <td class="${futCls}"><strong>${row.fut_oi_change_pct > 0 ? '+' : ''}${Number(row.fut_oi_change_pct).toFixed(2)}%</strong></td>

            // <td>${condBadge}</td>

            // Weighted OI Dominance
            const woi = getWeightedOIDominance(row);

            // <td title="${row.mm_trap && row.mm_trap.detail ? row.mm_trap.detail : ''}">${getMmTrapBadge(row.mm_trap)}</td>
            // <td>${getMmWallsCell(row.mm_trap)}</td>
            // <td>${getPriceSignalBadge(row.price_signal, row.price_change_pct)}</td>
            // <td>${getStrongerBadge(row.stronger_side)}</td>
            // <td>${getStrengthBadge(row.strength_rank, row.final_sentiment)}</td>
            // <td><span class="${ss.cls}" title="Score ${ss.score}/5">${ss.label}</span></td>
            // <td><span class="ratio-badge">${row.pe_ce_ratio}</span></td>

            html += `
            <tr ${rowStyle}>
                <td><strong>${i + 1}</strong>${isAligned(row) ? ' <span title="Aligned" style="color:#00d2ff;font-size:10px;">🎯</span>' : ''}</td>
                <td><strong>${row.date}</strong></td>
                <td>
                    <strong style="color:#667eea;">${row.symbol}</strong>
                    ${row.in_nifty50 ? '<span class="n50-star">N50</span>' : ''}
                </td>
                <td>
                    <strong>${fmtOI(row.ce_oi)}</strong><br>
                    <small style="color:#aaa;font-size:9px;">${(row.ce_oi||0).toLocaleString()}</small>
                </td>
                <td class="${ceCls}"><strong>${row.ce_oi_change_pct > 0 ? '+' : ''}${Number(row.ce_oi_change_pct).toFixed(2)}%</strong></td>

                <td>
                    <strong>${fmtOI(row.pe_oi)}</strong><br>
                    <small style="color:#aaa;font-size:9px;">${(row.pe_oi||0).toLocaleString()}</small>
                </td>
                <td class="${peCls}"><strong>${row.pe_oi_change_pct > 0 ? '+' : ''}${Number(row.pe_oi_change_pct).toFixed(2)}%</strong></td>
                <td>${sentBadge}</td>

                <td>${getGannBiasBadge(row.gann_bias, row.gann_zone)}</td>
                <td>${getGannNearLevel(row.gann_near_level, row.gann_near_price, row.gann_distance_pct)}</td>
                <td>${actBadge}</td>
                <td class="th-woi">
                    <span class="${woi.cls}">${woi.label}</span>
                    <br><small style="color:#888;font-size:9px;">${woi.dominance}%</small>
                </td>
                <td class="th-woi">
                    <strong style="font-size:10px;">${woi.volImpact}</strong>
                </td>
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

    function getPriceSignalBadge(signal, changePct) {
        if (!signal || signal === 'N/A') return '<span class="mm-no-trap">N/A</span>';
        const pct    = parseFloat(changePct) || 0;
        const sign   = pct >= 0 ? '+' : '';
        const pctStr = `<br><small style="font-size:9px;opacity:.85;">${sign}${pct.toFixed(2)}%</small>`;
        if (signal === 'BULLISH')  return `<span class="sentiment-bullish">▲ BULL${pctStr}</span>`;
        if (signal === 'BEARISH')  return `<span class="sentiment-bearish">▼ BEAR${pctStr}</span>`;
        return `<span class="sentiment-neutral">— NEUT${pctStr}</span>`;
    }

    function getGannBiasBadge(bias, zone) {
        if (!bias || bias === 'N/A') return '<span class="mm-no-trap">N/A</span>';
        const zoneStr = zone ? `<br><small style="font-size:9px;opacity:.85;">Zone ${zone}</small>` : '';
        const styles  = {
            'STRONG BULLISH': 'background:#1b5e20;color:white;',
            'BULLISH':        'background:#28a745;color:white;',
            'BEARISH':        'background:#dc3545;color:white;',
            'STRONG BEARISH': 'background:#7f0000;color:white;',
        };
        const labels  = {
            'STRONG BULLISH': '🟢🟢 S.BULL',
            'BULLISH':        '🟢 BULL',
            'BEARISH':        '🔴 BEAR',
            'STRONG BEARISH': '🔴🔴 S.BEAR',
        };
        const style = styles[bias] || 'background:#6c757d;color:white;';
        const label = labels[bias] || bias;
        return `<span style="${style}padding:3px 7px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block;">${label}${zoneStr}</span>`;
    }

    function getGannNearLevel(nearLevel, nearPrice, distPct) {
        if (!nearLevel) return '<span class="mm-no-trap">N/A</span>';
        const price  = nearPrice   ? `₹${Number(nearPrice).toLocaleString('en-IN')}` : '—';
        const dist   = distPct != null ? `${distPct}% away` : '';
        return `<span style="font-size:10px;font-weight:700;color:#667eea;">${nearLevel}</span>
                <br><small style="font-size:9px;color:#aaa;">${price}</small>
                <br><small style="font-size:9px;color:#888;">${dist}</small>`;
    }

    function updateNifty50Trend() {
        if (!analysisData || !analysisData.length) { resetNifty50Trend(); return; }

        const n50rows = analysisData.filter(r => NIFTY50_LIST.has(r.symbol));
        const total   = n50rows.length;
        const bull    = n50rows.filter(r => r.trade_action === 'BUY CE').length;
        const bear    = n50rows.filter(r => r.trade_action === 'BUY PE').length;
        const wait    = n50rows.filter(r => r.trade_action === 'WAIT').length;
        const bullPct = total > 0 ? Math.round((bull / total) * 100) : 0;
        const bearPct = total > 0 ? Math.round((bear / total) * 100) : 0;

        $('#n50_in_data').text(total);
        $('#n50_bull').text(bull);
        $('#n50_bear').text(bear);
        $('#n50_wait').text(wait);
        $('#n50_bull_pct').text(bullPct + '%');
        $('#n50_bull_bar').css('width', bullPct + '%');
        $('#n50_bear_bar').css('width', bearPct + '%');
        $('#n50_bar_bull_label').text('Bullish ' + bullPct + '%');
        $('#n50_bar_bear_label').text('Bearish ' + bearPct + '%');
        $('#n50_verdict_sub').text(`— ${bull} of ${total} Nifty50 stocks`);

        let trend, icon, color;
        if      (bullPct >= 60)         { trend = 'NIFTY50 MOSTLY BULLISH';  icon = '🟢'; color = '#28a745'; }
        else if (bearPct >= 60)         { trend = 'NIFTY50 MOSTLY BEARISH';  icon = ''; color = '#dc3545'; }
        else if (bullPct > bearPct)     { trend = 'SLIGHT BULLISH BIAS';     icon = '🟡'; color = '#28a745'; }
        else if (bearPct > bullPct)     { trend = 'SLIGHT BEARISH BIAS';     icon = '🟡'; color = '#dc3545'; }
        else                            { trend = 'MIXED / NEUTRAL';          icon = ''; color = '#888'; }

        $('#n50_verdict_icon').text(icon);
        $('#n50_verdict_text').text(trend).css('color', color);
        $('#n50_trend_badge').html(`<span style="color:${color};font-weight:700;">${icon} ${bull >= bear ? 'BULLISH' : 'BEARISH'}</span>`);
    }

    function resetNifty50Trend() {
        $('#n50_in_data,#n50_bull,#n50_bear,#n50_wait').text('0');
        $('#n50_bull_pct').text('0%');
        $('#n50_bull_bar,#n50_bear_bar').css('width','0%');
        $('#n50_verdict_text').text('No data').css('color','#888');
        $('#n50_verdict_icon').text('');
        $('#n50_trend_badge').text('—');
    }

    /* ── OI formatter ─────────────────────────────────────────── */
    function fmtOI(val) {
        const n = Number(val) || 0;
        if (n >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
        if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
        return n.toString();
    }

    /* ── Profit AJAX ──────────────────────────────────────────── */
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

        // Render dashes for WAIT rows immediately
        analysisData.forEach((row, idx) => {
            if (row.trade_action !== 'BUY CE' && row.trade_action !== 'BUY PE') {
                renderNoProfitRow(idx);
            }
        });

        if (signals.length === 0) { resetAlignedStats(); return; }

        $.ajax({
            url : '{{ route("oiiv-auto.calculate-profit") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', signals: signals },
            success: function (res) {
                if (res.success && res.data) {
                    const returned = new Set(res.data.map(d => Number(d.index)));
                    res.data.forEach(item => {
                        item.index = Number(item.index);
                        applyProfitToRow(item);
                    });
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
        updateNifty50Trend();
        updateBankNiftyTrend();
        updateSensexTrend();
    }

    function resetStatistics() {
        $('#total_records,#buy_ce_count,#buy_pe_count,#wait_count,#strong_bullish_count,#strong_bearish_count').text('0');
        $('#avg_investment').text('₹0');
        $('#high_trades').text('0');
        $('#exit_total_pl,#high_total_pl,#low_total_pl').text('₹0');
        $('#exit_avg_roi').text('0%');
        resetNifty50Trend();
        resetBankNiftyTrend();
        resetSensexTrend();
    }

    function showNoData(message) {
        $('#analysis-tbody').html(`
            <tr><td colspan="30" class="text-center py-5">
                <i class="fas fa-info-circle" style="color:#17a2b8; font-size:3rem;"></i>
                <p class="text-info" style="margin-top:20px;">${message}</p>
            </td></tr>`);
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter,#action_filter').val('');
        analysisData = [];
        showNoData('Click "View Data" to load signals');
        resetStatistics();
        resetAlignedStats();
        setTimeout(() => runAnalysis(), 300);
    }

    $('#run_analysis').click(() => runAnalysis());
    $('#reset_filters').click(() => resetFilters());
</script>
@endpush