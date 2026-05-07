@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ── Base table ──────────────────────────────────────── */
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

    /* ── Sticky columns ──────────────────────────────────── */
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .custom--table { min-width: 1800px; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position: sticky; z-index: 10; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left: 0; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left: 40px; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left: 120px; }

    /* ── Loading ─────────────────────────────────────────── */
    .loading-overlay {
        position: absolute; top:0; left:0; right:0; bottom:0;
        background: rgba(19,45,57,0.95); display: flex; flex-direction: column;
        justify-content: center; align-items: center; z-index: 1000; border-radius: 12px;
    }
    .spinner { width:50px;height:50px;border:5px solid #f3f3f3;border-top:5px solid #3498db;border-radius:50%;animation:spin 1s linear infinite; }
    .loading-text { color:white;margin-top:20px;font-size:16px;font-weight:600; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    /* ── Page header ─────────────────────────────────────── */
    .page-header { background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 20px rgba(15,52,96,0.5); border:1px solid rgba(0,212,255,0.2); }

    /* ── Filter section ──────────────────────────────────── */
    .filter-section { background:linear-gradient(135deg,#0f3460,#16213e); padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(0,0,0,0.3); color:white; border:1px solid rgba(0,212,255,0.15); }
    .filter-section label { color:rgba(255,255,255,0.9) !important; font-weight:600; margin-bottom:6px; font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.92); color:#333; font-size:12px; padding:6px 10px; }

    /* ── Stats boxes ─────────────────────────────────────── */
    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.08); transition:transform .2s; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#666; font-size:10px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box strong { display:block; font-size:1.3rem; font-weight:700; margin-top:3px; }

    /* ── Action badges ───────────────────────────────────── */
    .action-buy-ce { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:4px 10px; border-radius:5px; font-weight:700; font-size:10px; display:inline-block; }
    .action-buy-pe { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:4px 10px; border-radius:5px; font-weight:700; font-size:10px; display:inline-block; }
    .action-wait   { background:linear-gradient(135deg,#6c757d,#495057); color:white; padding:4px 10px; border-radius:5px; font-weight:700; font-size:10px; display:inline-block; }

    /* ── FUT direction badges ────────────────────────────── */
    .fut-up   { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; }
    .fut-down { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; }
    .fut-flat { background:#6c757d; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:10px; display:inline-block; }

    /* ── OI signal badges ────────────────────────────────── */
    .oi-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .oi-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .oi-neutral { background:#6c757d; color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Alignment glow ──────────────────────────────────── */
    .row-aligned-ce { background:rgba(40,167,69,0.06); outline:1px solid rgba(40,167,69,0.2); }
    .row-aligned-pe { background:rgba(220,53,69,0.06); outline:1px solid rgba(220,53,69,0.2); }

    /* ── Confidence dot ──────────────────────────────────── */
    .conf-high   { color:#28a745; font-size:10px; font-weight:700; }
    .conf-medium { color:#ffc107; font-size:10px; font-weight:700; }
    .conf-low    { color:#dc3545; font-size:10px; font-weight:700; }

    /* ── Option badge ────────────────────────────────────── */
    .option-badge { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:3px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }

    /* ── Expiry badge ────────────────────────────────────── */
    .expiry-badge { background:#fff3cd; color:#856404; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; }
    .expiry-day-badge { background:#dc3545; color:white; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; }

    /* ── Logic info box ──────────────────────────────────── */
    .logic-box { background:linear-gradient(135deg,#0f3460,#16213e); border:1px solid rgba(0,212,255,0.25); border-radius:12px; padding:16px 20px; margin-bottom:20px; color:white; }
    .logic-box h6 { color:#00d4ff; font-size:14px; font-weight:700; margin-bottom:12px; }
    .logic-box ul { margin:0; padding-left:18px; }
    .logic-box ul li { font-size:11px; color:rgba(255,255,255,0.85); margin-bottom:4px; }
    .logic-box strong { color:#00d4ff; }

    /* ── Consistency badge ───────────────────────────────── */
    .consist-match    { background:#28a745; color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .consist-mismatch { background:#dc3545; color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .consist-partial  { background:#ffc107; color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .consist-na       { color:#aaa; font-size:10px; }

    /* ── Section header ──────────────────────────────────── */
    .section-divider { border:none; border-top:1px solid rgba(0,212,255,0.15); margin:16px 0; }
    .profit-positive { color:#28a745; font-weight:700; }
    .profit-negative { color:#dc3545; font-weight:700; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Page Header ────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 style="margin:0;">⚡ FUT Contrarian OI Analysis</h4>
                <p style="margin:6px 0 0; font-size:12px; opacity:.85;">
                    FUT UP → BUY PE &nbsp;|&nbsp; FUT DOWN → BUY CE &nbsp;|&nbsp;
                    Option = highest OI among ATM / ATM±1 &nbsp;|&nbsp;
                    OI-30min: Prev 15:15 → Today 09:45 &nbsp;|&nbsp; OI-1HR: Prev 15:15 → Today 10:15
                </p>
            </div>
            <div>
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-outline-light btn-sm mr-2">EOD Analysis</a>
                <a href="{{ route('oiiv-auto.index') }}" class="btn btn-outline-light btn-sm">OI+IV</a>
            </div>
        </div>
    </div>

    {{-- ── Logic Summary ───────────────────────────────────── --}}
    <div class="logic-box">
        <h6><i class="fas fa-lightbulb"></i> How This Works</h6>
        <div class="row">
            <div class="col-md-3">
                <strong>📈 FUT Direction (Contrarian)</strong>
                <ul>
                    <li>Compare <strong>Prev day 15:00 close</strong> vs <strong>Today 09:30 open</strong></li>
                    <li>FUT <strong>UP</strong> → BUY PE (trapped put writers)</li>
                    <li>FUT <strong>DOWN</strong> → BUY CE (trapped call writers)</li>
                </ul>
            </div>
            <div class="col-md-3">
                <strong>🎯 Option Selection</strong>
                <ul>
                    <li>Scan <strong>ATM, ATM+1, ATM-1</strong> strikes</li>
                    <li>Pick strike with <strong>highest OI</strong></li>
                    <li>Scoped to <strong>active expiry</strong> only</li>
                    <li>Price = 09:30 candle open</li>
                </ul>
            </div>
            <div class="col-md-3">
                <strong>⏱ OI-30min Signal</strong>
                <ul>
                    <li>Base: <strong>Prev day 15:15</strong> candle OI</li>
                    <li>Compare: <strong>Today 09:45</strong> candle OI</li>
                    <li>CE↑ PE↓ = BEARISH &nbsp;|&nbsp; CE↓ PE↑ = BULLISH</li>
                    <li>Both↑/↓: dominant side wins</li>
                </ul>
            </div>
            <div class="col-md-3">
                <strong>⏱ OI-1HR Signal</strong>
                <ul>
                    <li>Base: <strong>Prev day 15:15</strong> candle OI</li>
                    <li>Compare: <strong>Today 10:15</strong> candle OI</li>
                    <li>Same rules as 30-min</li>
                    <li>Use to <strong>confirm</strong> the 30-min signal</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── Filters ─────────────────────────────────────────── --}}
    <div class="filter-section">
        <div class="row mb-3">
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
                <small style="color:rgba(255,255,255,0.7);font-size:10px;">Leave empty = all symbols</small>
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-bullseye"></i> Trade Action Filter:</label>
                <select id="action_filter" class="form-control">
                    <option value="">All</option>
                    <option value="BUY CE">BUY CE Only</option>
                    <option value="BUY PE">BUY PE Only</option>
                    <option value="WAIT">WAIT Only</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-12 text-center">
                <button type="button" id="btn_analyze" class="btn btn-light btn-lg" style="min-width:160px;">
                    <i class="fas fa-search"></i> Run Analysis
                </button>
                <button type="button" id="btn_reset" class="btn btn-outline-light btn-lg ml-2" style="min-width:140px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ── Stats Row ────────────────────────────────────────── --}}
    <div class="row mb-3">
        <div class="col-6 col-md-2"><div class="stats-box"><small>Total Records</small><strong id="stat_total" class="text-dark">0</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE</small><strong id="stat_ce" style="color:#28a745;">0</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE</small><strong id="stat_pe" style="color:#dc3545;">0</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#6c757d;"><small>WAIT</small><strong id="stat_wait" style="color:#6c757d;">0</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#00d4ff;"><small>OI-30m Aligned</small><strong id="stat_30m_aligned" style="color:#00d4ff;">0</strong></div></div>
        <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#667eea;"><small>OI-1HR Aligned</small><strong id="stat_1h_aligned" style="color:#667eea;">0</strong></div></div>
    </div>

    {{-- ── Alignment Stats Panel ─────────────────────────────── --}}
    <div style="border:1px solid rgba(0,212,255,0.25); border-radius:12px; padding:14px 18px; margin-bottom:16px; background:rgba(0,212,255,0.04);">
        <h6 style="color:#00d4ff; font-size:13px; font-weight:700; margin-bottom:12px;">🎯 Signal Alignment — When FUT Contrarian + OI Signal Agree</h6>
        <div style="font-size:10px; color:#888; margin-bottom:12px; display:flex; gap:20px; flex-wrap:wrap;">
            <span>✅ <strong>MATCH</strong> = FUT action matches both OI signals</span>
            <span>🟡 <strong>PARTIAL</strong> = Matches one of two OI signals</span>
            <span>❌ <strong>CONFLICT</strong> = OI signals oppose FUT action</span>
        </div>
        <div class="row">
            <div class="col-6 col-md-3"><div class="stats-box" style="border-left-color:#28a745;"><small>✅ Full Match (Both OI agree)</small><strong id="align_full" style="color:#28a745;">0</strong></div></div>
            <div class="col-6 col-md-3"><div class="stats-box" style="border-left-color:#ffc107;"><small>🟡 Partial Match (1 of 2 OI)</small><strong id="align_partial" style="color:#ffc107;">0</strong></div></div>
            <div class="col-6 col-md-3"><div class="stats-box" style="border-left-color:#dc3545;"><small>❌ Conflict (OI opposes FUT)</small><strong id="align_conflict" style="color:#dc3545;">0</strong></div></div>
            <div class="col-6 col-md-3"><div class="stats-box" style="border-left-color:#6c757d;"><small>⚪ No OI Data</small><strong id="align_na" style="color:#6c757d;">0</strong></div></div>
        </div>
    </div>

    {{-- ── P/L Performance Cards ──────────────────────────────── --}}
    <div id="pl-section" style="display:none; margin-bottom:20px;">

        {{-- OI-30min P/L Card --}}
        <div style="border:2px solid rgba(0,212,255,0.35); border-radius:14px; padding:18px 20px; margin-bottom:16px; background:linear-gradient(135deg,rgba(0,212,255,0.05),rgba(0,212,255,0.02));">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
                <span style="font-size:20px;">⏱</span>
                <h6 style="margin:0; font-size:13px; font-weight:700; color:#00d4ff;">OI-30min Aligned Trades P/L</h6>
                <span style="background:#00d4ff; color:#000; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px;">Buy @ 10:00 Open · Sell @ Day High</span>
                <span id="pl30_loading_badge" style="font-size:10px; color:#aaa; font-style:italic;">Loading...</span>
            </div>
            <div class="row">
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#00d4ff;"><small>Total Trades</small><strong id="pl30_trades" style="color:#00d4ff;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>Wins (P/L > 0)</small><strong id="pl30_wins" style="color:#28a745;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>Win Rate</small><strong id="pl30_winrate" style="color:#a855f7;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#667eea;"><small>Total Investment</small><strong id="pl30_avg_inv" style="color:#667eea; font-size:1rem;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>Total P/L</small><strong id="pl30_total_pl" style="font-size:1rem;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#fd7e14;"><small>Avg ROI %</small><strong id="pl30_avg_roi" style="font-size:1rem;">—</strong></div></div>
            </div>
            {{-- Mini breakdown --}}
            <div style="background:rgba(0,212,255,0.06); border:1px solid rgba(0,212,255,0.2); border-radius:8px; padding:8px 14px; font-size:10px; color:rgba(255,255,255,0.75); display:flex; gap:20px; flex-wrap:wrap;">
                <span>📅 <strong id="pl30_trade_dates" style="color:#00d4ff;">—</strong> trade dates</span>
                <span>📈 <strong id="pl30_best_roi" style="color:#28a745;">—</strong> best ROI</span>
                <span>📉 <strong id="pl30_worst_roi" style="color:#dc3545;">—</strong> worst ROI</span>
                <span>💰 <strong id="pl30_total_inv" style="color:#ffc107;">—</strong> total capital deployed</span>
            </div>
        </div>

        {{-- OI-1HR P/L Card --}}
        <div style="border:2px solid rgba(102,126,234,0.4); border-radius:14px; padding:18px 20px; background:linear-gradient(135deg,rgba(102,126,234,0.05),rgba(102,126,234,0.02));">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
                <span style="font-size:20px;">🕐</span>
                <h6 style="margin:0; font-size:13px; font-weight:700; color:#667eea;">OI-1HR Aligned Trades P/L</h6>
                <span style="background:#667eea; color:white; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px;">Buy @ 10:30 Open · Sell @ Day High (after 10:30)</span>
                <span id="pl1h_loading_badge" style="font-size:10px; color:#aaa; font-style:italic;">Loading...</span>
            </div>
            <div class="row">
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#667eea;"><small>Total Trades</small><strong id="pl1h_trades" style="color:#667eea;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>Wins (P/L > 0)</small><strong id="pl1h_wins" style="color:#28a745;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>Win Rate</small><strong id="pl1h_winrate" style="color:#a855f7;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>Total Investment</small><strong id="pl1h_avg_inv" style="color:#17a2b8; font-size:1rem;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>Total P/L</small><strong id="pl1h_total_pl" style="font-size:1rem;">—</strong></div></div>
                <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#fd7e14;"><small>Avg ROI %</small><strong id="pl1h_avg_roi" style="font-size:1rem;">—</strong></div></div>
            </div>
            <div style="background:rgba(102,126,234,0.07); border:1px solid rgba(102,126,234,0.25); border-radius:8px; padding:8px 14px; font-size:10px; color:rgba(255,255,255,0.75); display:flex; gap:20px; flex-wrap:wrap;">
                <span>📅 <strong id="pl1h_trade_dates" style="color:#667eea;">—</strong> trade dates</span>
                <span>📈 <strong id="pl1h_best_roi" style="color:#28a745;">—</strong> best ROI</span>
                <span>📉 <strong id="pl1h_worst_roi" style="color:#dc3545;">—</strong> worst ROI</span>
                <span>💰 <strong id="pl1h_total_inv" style="color:#ffc107;">—</strong> total capital deployed</span>
            </div>
        </div>

    </div>

    {{-- ── Table ────────────────────────────────────────────── --}}
    <div style="position:relative; min-height:400px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Analysing signals...</div>
        </div>

        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        {{-- sticky cols --}}
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>

                        {{-- FUT data --}}
                        <th>FUT Prev<br><small style="font-weight:400;opacity:.7;">15:00 Close</small></th>
                        <th>FUT Today<br><small style="font-weight:400;opacity:.7;">09:30 Open</small></th>
                        <th>FUT Chg%</th>
                        <th>FUT Dir</th>

                        {{-- Contrarian action --}}
                        <th style="background:rgba(102,126,234,0.1);">Action<br><small style="font-weight:400;opacity:.7;">Contrarian</small></th>

                        {{-- Best option --}}
                        <th>Option<br><small style="font-weight:400;opacity:.7;">Type</small></th>
                        <th>Strike<br><small style="font-weight:400;opacity:.7;">Best OI</small></th>
                        <th>Strike Pos</th>
                        <th>Option OI<br><small style="font-weight:400;opacity:.7;">09:30</small></th>
                        <th>Entry ₹<br><small style="font-weight:400;opacity:.7;">09:30 Open</small></th>

                        {{-- OI base (prev 15:15) --}}
                        <th style="background:rgba(108,117,125,0.08);">CE OI<br><small style="font-weight:400;opacity:.7;">Prev 15:15</small></th>
                        <th style="background:rgba(108,117,125,0.08);">PE OI<br><small style="font-weight:400;opacity:.7;">Prev 15:15</small></th>

                        {{-- OI-30min --}}
                        <th style="background:rgba(0,212,255,0.08);">CE OI<br><small style="font-weight:400;opacity:.7;">09:45</small></th>
                        <th style="background:rgba(0,212,255,0.08);">PE OI<br><small style="font-weight:400;opacity:.7;">09:45</small></th>
                        <th style="background:rgba(0,212,255,0.08);">CE%<br><small style="font-weight:400;opacity:.7;">30-min</small></th>
                        <th style="background:rgba(0,212,255,0.08);">PE%<br><small style="font-weight:400;opacity:.7;">30-min</small></th>
                        <th style="background:rgba(0,212,255,0.1);">OI Signal<br><small style="font-weight:400;opacity:.7;">30-min</small></th>

                        {{-- OI-1HR --}}
                        <th style="background:rgba(102,126,234,0.08);">CE OI<br><small style="font-weight:400;opacity:.7;">10:15</small></th>
                        <th style="background:rgba(102,126,234,0.08);">PE OI<br><small style="font-weight:400;opacity:.7;">10:15</small></th>
                        <th style="background:rgba(102,126,234,0.08);">CE%<br><small style="font-weight:400;opacity:.7;">1-HR</small></th>
                        <th style="background:rgba(102,126,234,0.08);">PE%<br><small style="font-weight:400;opacity:.7;">1-HR</small></th>
                        <th style="background:rgba(102,126,234,0.1);">OI Signal<br><small style="font-weight:400;opacity:.7;">1-HR</small></th>

                        {{-- Alignment --}}
                        <th style="background:rgba(255,193,7,0.1);">Alignment<br><small style="font-weight:400;opacity:.7;">FUT vs OI</small></th>

                        {{-- Expiry --}}
                        <th>Expiry</th>
                    </tr>
                </thead>
                <tbody id="analysis-tbody">
                    <tr>
                        <td colspan="30" class="text-center py-5">
                            <i class="fas fa-chart-area" style="font-size:3rem; opacity:0.4;"></i>
                            <p style="font-size:1.1rem; margin-top:16px;">Click <strong>"Run Analysis"</strong> to load signals</p>
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

// ── Helpers ──────────────────────────────────────────────────
function fmtOI(val) {
    const n = Number(val) || 0;
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
    return n.toString();
}

function pctClass(v) { return parseFloat(v) >= 0 ? 'text-success' : 'text-danger'; }
function pctStr(v)   { const n = parseFloat(v) || 0; return (n >= 0 ? '+' : '') + n.toFixed(2) + '%'; }

function oiSignalBadge(signal, cond) {
    if (!signal || signal === 'NEUTRAL') {
        return `<span class="oi-neutral">⚪ NEUT</span>${cond ? '<br><small style="color:#aaa;font-size:9px;">' + cond + '</small>' : ''}`;
    }
    const cls = signal === 'BULLISH' ? 'oi-bullish' : 'oi-bearish';
    const icon = signal === 'BULLISH' ? '🟢' : '🔴';
    return `<span class="${cls}">${icon} ${signal}</span><br><small style="color:#888;font-size:9px;">${cond || ''}</small>`;
}

function alignmentBadge(row) {
    const action  = row.trade_action;
    const sig30   = row.oi_30min_signal;
    const sig1h   = row.oi_1hr_signal;

    // Map action to expected OI signal for confirmation
    // BUY CE (FUT DOWN): we want OI to show BULLISH (CE unwinding, PE buildup)
    // BUY PE (FUT UP):   we want OI to show BEARISH (CE buildup, PE unwinding)
    const expected = action === 'BUY CE' ? 'BULLISH' : action === 'BUY PE' ? 'BEARISH' : null;
    if (!expected) return '<span class="consist-na">WAIT</span>';

    const hasData30 = sig30 && sig30 !== 'NEUTRAL';
    const hasData1h = sig1h && sig1h !== 'NEUTRAL';

    if (!hasData30 && !hasData1h) return '<span class="consist-na">No OI Data</span>';

    const match30 = hasData30 && sig30 === expected;
    const match1h = hasData1h && sig1h === expected;

    if (match30 && match1h)   return '<span class="consist-match">✅ FULL MATCH</span>';
    if (match30 || match1h)   return '<span class="consist-partial">🟡 PARTIAL</span>';
    return '<span class="consist-mismatch">❌ CONFLICT</span>';
}

// ── Loading ───────────────────────────────────────────────────
function toggleLoading(show, msg) {
    if (show) { $('#loading-overlay .loading-text').text(msg || 'Analysing...'); $('#loading-overlay').show(); }
    else       { $('#loading-overlay').hide(); }
}

// ── Init ──────────────────────────────────────────────────────
$(document).ready(function () {
    loadSymbols();
    setTimeout(() => runAnalysis(), 500);
});

function loadSymbols() {
    $.get('{{ route("fut-contrarian.symbols") }}', function (res) {
        if (!res.success) return;
        let opts = '';
        res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
        $('#symbol_filter').html(opts);
    });
}

// ── Main call ─────────────────────────────────────────────────
function runAnalysis() {
    const fromDate = $('#from_date').val();
    const toDate   = $('#to_date').val();
    const symbols  = $('#symbol_filter').val() || [];
    const action   = $('#action_filter').val();

    if (!fromDate || !toDate) { alert('Please select both dates'); return; }

    toggleLoading(true, 'Loading contrarian signals...');
    analysisData = [];

    $.ajax({
        url : '{{ route("fut-contrarian.analyze") }}',
        type: 'GET',
        data: { from_date: fromDate, to_date: toDate, symbols, filter_action: action },
        success: function (res) {
            if (res.success && res.data && res.data.length > 0) {
                analysisData = res.data;
                renderTable();
                updateStats();
                loadPLData();
            } else {
                showEmpty(res.message || 'No data found for selected range');
                resetStats();
                resetPLSection();
            }
            toggleLoading(false);
        },
        error: function (xhr) {
            const msg = xhr.responseJSON?.message || 'Server error';
            showEmpty('Error: ' + msg);
            resetStats();
            resetPLSection();
            toggleLoading(false);
        }
    });
}

// ── Table render ──────────────────────────────────────────────
function renderTable() {
    let html = '';

    analysisData.forEach(function (row, i) {
        const action = row.trade_action;
        const futDir = row.fut_direction;

        const futDirBadge = futDir === 'UP'
            ? '<span class="fut-up">▲ UP</span>'
            : futDir === 'DOWN'
                ? '<span class="fut-down">▼ DOWN</span>'
                : '<span class="fut-flat">— FLAT</span>';

        const actBadge = action === 'BUY CE'
            ? '<span class="action-buy-ce">📈 BUY CE</span>'
            : action === 'BUY PE'
                ? '<span class="action-buy-pe">📉 BUY PE</span>'
                : '<span class="action-wait">⏸ WAIT</span>';

        const chgCls   = parseFloat(row.fut_change_pct) >= 0 ? 'text-success' : 'text-danger';
        const rowClass = action === 'BUY CE' ? 'row-aligned-ce' : action === 'BUY PE' ? 'row-aligned-pe' : '';

        const strikeCell = row.best_strike
            ? `<strong>${row.best_strike}</strong>`
            : '<span style="color:#aaa;">—</span>';

        const strikePosCell = row.best_strike_pos
            ? `<span style="font-size:10px;font-weight:700;color:#667eea;">${row.best_strike_pos}</span>`
            : '<span style="color:#aaa;font-size:10px;">—</span>';

        const optTypeCell = row.option_type
            ? `<span style="background:${row.option_type === 'CE' ? '#28a745' : '#dc3545'};color:white;padding:2px 8px;border-radius:3px;font-weight:700;font-size:10px;">${row.option_type}</span>`
            : '<span style="color:#aaa;">—</span>';

        const entryCell = row.best_open_price
            ? `<strong>₹${Number(row.best_open_price).toFixed(2)}</strong>`
            : '<span style="color:#aaa;">—</span>';

        const bestOICell = row.best_oi > 0
            ? `<strong style="color:#667eea;">${fmtOI(row.best_oi)}</strong><br><small style="color:#aaa;font-size:9px;">${Number(row.best_oi).toLocaleString()}</small>`
            : '<span style="color:#aaa;">—</span>';

        const optSymCell = row.best_option_sym
            ? `<span class="option-badge" title="${row.best_option_sym}">${row.best_option_sym}</span>`
            : '<span style="color:#aaa;">—</span>';

        // OI data
        const prevCE = fmtOI(row.prev_ce_oi_1515);
        const prevPE = fmtOI(row.prev_pe_oi_1515);
        const ce0945 = fmtOI(row.ce_oi_0945);
        const pe0945 = fmtOI(row.pe_oi_0945);
        const ce1015 = fmtOI(row.ce_oi_1015);
        const pe1015 = fmtOI(row.pe_oi_1015);

        const expiryCell = row.current_expiry
            ? (row.is_expiry_day
                ? `<span class="expiry-day-badge">⚠ EXPIRY</span><br><small style="font-size:9px;">${row.current_expiry}</small>`
                : `<span class="expiry-badge">${row.current_expiry}</span>`)
            : '<span style="color:#aaa;">—</span>';

        html += `
        <tr class="${rowClass}">
            <td><strong>${i + 1}</strong></td>
            <td><strong>${row.date}</strong></td>
            <td><strong>${row.symbol}</strong></td>

            <td><strong>₹${Number(row.fut_prev_close).toFixed(2)}</strong></td>
            <td><strong>₹${Number(row.fut_today_open).toFixed(2)}</strong></td>
            <td class="${chgCls}"><strong>${pctStr(row.fut_change_pct)}</strong></td>
            <td>${futDirBadge}</td>

            <td style="background:rgba(102,126,234,0.05);">${actBadge}</td>

            <td>${optTypeCell}</td>
            <td>${strikeCell}</td>
            <td>${strikePosCell}</td>
            <td>${bestOICell}</td>
            <td>${entryCell}</td>

            <td style="background:rgba(108,117,125,0.05);">
                <strong>${prevCE}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.prev_ce_oi_1515||0).toLocaleString()}</small>
            </td>
            <td style="background:rgba(108,117,125,0.05);">
                <strong>${prevPE}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.prev_pe_oi_1515||0).toLocaleString()}</small>
            </td>

            <td style="background:rgba(0,212,255,0.04);">
                <strong>${ce0945}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.ce_oi_0945||0).toLocaleString()}</small>
            </td>
            <td style="background:rgba(0,212,255,0.04);">
                <strong>${pe0945}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.pe_oi_0945||0).toLocaleString()}</small>
            </td>
            <td style="background:rgba(0,212,255,0.04);" class="${pctClass(row.ce_oi_30min_pct)}">
                <strong>${pctStr(row.ce_oi_30min_pct)}</strong>
            </td>
            <td style="background:rgba(0,212,255,0.04);" class="${pctClass(row.pe_oi_30min_pct)}">
                <strong>${pctStr(row.pe_oi_30min_pct)}</strong>
            </td>
            <td style="background:rgba(0,212,255,0.07);">${oiSignalBadge(row.oi_30min_signal, row.oi_30min_cond)}</td>

            <td style="background:rgba(102,126,234,0.04);">
                <strong>${ce1015}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.ce_oi_1015||0).toLocaleString()}</small>
            </td>
            <td style="background:rgba(102,126,234,0.04);">
                <strong>${pe1015}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.pe_oi_1015||0).toLocaleString()}</small>
            </td>
            <td style="background:rgba(102,126,234,0.04);" class="${pctClass(row.ce_oi_1hr_pct)}">
                <strong>${pctStr(row.ce_oi_1hr_pct)}</strong>
            </td>
            <td style="background:rgba(102,126,234,0.04);" class="${pctClass(row.pe_oi_1hr_pct)}">
                <strong>${pctStr(row.pe_oi_1hr_pct)}</strong>
            </td>
            <td style="background:rgba(102,126,234,0.07);">${oiSignalBadge(row.oi_1hr_signal, row.oi_1hr_cond)}</td>

            <td style="background:rgba(255,193,7,0.05);">${alignmentBadge(row)}</td>
            <td>${expiryCell}</td>
        </tr>`;
    });

    $('#analysis-tbody').html(html);
}

// ── Stats ─────────────────────────────────────────────────────
function updateStats() {
    const total   = analysisData.length;
    const ceBuys  = analysisData.filter(r => r.trade_action === 'BUY CE').length;
    const peBuys  = analysisData.filter(r => r.trade_action === 'BUY PE').length;
    const waits   = analysisData.filter(r => r.trade_action === 'WAIT').length;

    $('#stat_total').text(total);
    $('#stat_ce').text(ceBuys);
    $('#stat_pe').text(peBuys);
    $('#stat_wait').text(waits);

    // Count 30-min OI rows that have data
    const with30Data = analysisData.filter(r =>
        r.prev_ce_oi_1515 > 0 || r.ce_oi_0945 > 0
    ).length;
    const with1hData = analysisData.filter(r =>
        r.prev_ce_oi_1515 > 0 || r.ce_oi_1015 > 0
    ).length;
    $('#stat_30m_aligned').text(with30Data);
    $('#stat_1h_aligned').text(with1hData);

    // Alignment counts
    let fullMatch = 0, partial = 0, conflict = 0, na = 0;
    analysisData.forEach(r => {
        const badge = alignmentBadge(r);
        if      (badge.includes('FULL MATCH')) fullMatch++;
        else if (badge.includes('PARTIAL'))    partial++;
        else if (badge.includes('CONFLICT'))   conflict++;
        else                                   na++;
    });
    $('#align_full').text(fullMatch);
    $('#align_partial').text(partial);
    $('#align_conflict').text(conflict);
    $('#align_na').text(na);
}

function resetStats() {
    $('#stat_total,#stat_ce,#stat_pe,#stat_wait,#stat_30m_aligned,#stat_1h_aligned').text('0');
    $('#align_full,#align_partial,#align_conflict,#align_na').text('0');
}

function showEmpty(msg) {
    $('#analysis-tbody').html(`
        <tr><td colspan="30" class="text-center py-5">
            <i class="fas fa-info-circle" style="color:#17a2b8;font-size:3rem;"></i>
            <p class="text-info mt-3">${msg}</p>
        </td></tr>`);
}

// ── P/L cards ─────────────────────────────────────────────────
function loadPLData() {
    // Only take aligned rows: trade action is BUY CE or BUY PE AND
    // the alignment badge is FULL MATCH or PARTIAL (at least one OI agrees)
    const alignedSignals = analysisData
        .map((row, idx) => ({ row, idx }))
        .filter(({ row }) => {
            if (row.trade_action === 'WAIT') return false;
            const badge = alignmentBadge(row);
            // Include FULL MATCH and PARTIAL — exclude CONFLICT and No OI Data
            return badge.includes('FULL MATCH') || badge.includes('PARTIAL');
        })
        .map(({ row, idx }) => ({
            idx,
            symbol         : row.symbol,
            date           : row.date,
            option_type    : row.option_type,
            best_strike    : row.best_strike,
            current_expiry : row.current_expiry,
        }));

    if (alignedSignals.length === 0) {
        hidePLSection();
        return;
    }

    $('#pl-section').show();
    $('#pl30_loading_badge, #pl1h_loading_badge').text('Calculating...').show();

    $.ajax({
        url : '{{ route("fut-contrarian.calculate-pl") }}',
        type: 'POST',
        data: {
            _token  : '{{ csrf_token() }}',
            signals : alignedSignals,
        },
        success: function (res) {
            if (res.success) {
                renderPLCard('30m', res.data_30min);
                renderPLCard('1h',  res.data_1hr);
            } else {
                $('#pl30_loading_badge, #pl1h_loading_badge').text('Error: ' + (res.message || 'Failed'));
            }
        },
        error: function (xhr) {
            const msg = xhr.responseJSON?.message || 'Server error';
            $('#pl30_loading_badge, #pl1h_loading_badge').text('Error: ' + msg);
        }
    });
}

function renderPLCard(type, data) {
    const pfx    = type === '30m' ? 'pl30' : 'pl1h';
    const badge  = `#${pfx}_loading_badge`;

    const valid  = data.filter(d => !d.error);
    const count  = valid.length;

    if (count === 0) {
        $(badge).text('No data available');
        $(`#${pfx}_trades`).text('0');
        return;
    }

    $(badge).hide();

    const wins      = valid.filter(d => d.pl > 0).length;
    const winRate   = ((wins / count) * 100).toFixed(1);
    const totalPL   = valid.reduce((s, d) => s + d.pl, 0);
    const totalInv  = valid.reduce((s, d) => s + d.investment, 0);
    const avgInv    = totalInv / count;
    const avgRoi    = valid.reduce((s, d) => s + d.roi, 0) / count;
    const bestRoi   = Math.max(...valid.map(d => d.roi));
    const worstRoi  = Math.min(...valid.map(d => d.roi));
    const uniqueDates = new Set(valid.map(d => d.date)).size;

    const plCls  = v => v >= 0 ? 'profit-positive' : 'profit-negative';
    const fmt    = v => (v >= 0 ? '+' : '') + '₹' + Math.abs(v).toFixed(2);
    const fmtRoi = v => (v >= 0 ? '+' : '') + Math.abs(v).toFixed(2) + '%';
    const wCls   = p => parseFloat(p) >= 50 ? '#28a745' : '#dc3545';

    $(`#${pfx}_trades`).text(count);
    $(`#${pfx}_wins`).text(wins);
    $(`#${pfx}_winrate`).html(`<span style="color:${wCls(winRate)};font-weight:700;">${winRate}%</span>`);
    $(`#${pfx}_avg_inv`).html(`₹${Math.round(totalInv).toLocaleString('en-IN')}`);
    $(`#${pfx}_total_pl`).html(`<span class="${plCls(totalPL)}">${fmt(totalPL)}</span>`);
    $(`#${pfx}_avg_roi`).html(`<span class="${plCls(avgRoi)}">${fmtRoi(avgRoi)}</span>`);
    $(`#${pfx}_trade_dates`).text(uniqueDates);
    $(`#${pfx}_best_roi`).text(fmtRoi(bestRoi));
    $(`#${pfx}_worst_roi`).text(fmtRoi(worstRoi));
    $(`#${pfx}_total_inv`).html(`₹${Math.round(totalInv).toLocaleString('en-IN')}`);
}

function hidePLSection() {
    $('#pl-section').hide();
}

function resetPLSection() {
    hidePLSection();
    const ids = ['pl30_trades','pl30_wins','pl30_winrate','pl30_avg_inv','pl30_total_pl','pl30_avg_roi',
                 'pl30_trade_dates','pl30_best_roi','pl30_worst_roi','pl30_total_inv',
                 'pl1h_trades','pl1h_wins','pl1h_winrate','pl1h_avg_inv','pl1h_total_pl','pl1h_avg_roi',
                 'pl1h_trade_dates','pl1h_best_roi','pl1h_worst_roi','pl1h_total_inv'];
    ids.forEach(id => $(`#${id}`).text('—'));
    $('#pl30_loading_badge, #pl1h_loading_badge').text('Loading...').show();
}

// ── Buttons ───────────────────────────────────────────────────
$('#btn_analyze').click(() => runAnalysis());
$('#btn_reset').click(function () {
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter').val('');
    $('#action_filter').val('');
    analysisData = [];
    showEmpty('Click "Run Analysis" to load signals');
    resetStats();
    resetPLSection();
    setTimeout(() => runAnalysis(), 300);
});
</script>
@endpush