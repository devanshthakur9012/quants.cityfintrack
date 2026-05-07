@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ── Base table ────────────────────────────────────────── */
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

    /* ── Loading overlay ───────────────────────────────────── */
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

    /* ── Page header ───────────────────────────────────────── */
    .page-header {
        background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
        color: white; padding: 20px; border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(15, 52, 96, 0.5);
        border: 1px solid rgba(0, 210, 255, 0.2);
    }
    .page-header h4 { color: #00d2ff; margin-bottom: 5px; }
    .page-header p  { color: rgba(255,255,255,0.7); font-size: 12px; margin: 0; }

    /* ── Filter section ────────────────────────────────────── */
    .filter-section {
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 20px; border-radius: 12px; margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(102,126,234,0.4); color: white;
    }
    .filter-section label { color: white !important; font-weight: 600; margin-bottom: 6px; font-size: 13px; }
    .filter-section .form-control { border: 2px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.9); color: #333; font-size: 12px; padding: 6px 10px; }

    /* ── Stats boxes ───────────────────────────────────────── */
    .stats-box { background: #fff; padding: 12px; border-radius: 10px; text-align: center; border-left: 4px solid #3498db; margin-bottom: 12px; box-shadow: 0 3px 10px rgba(0,0,0,.1); }
    .stats-box small  { display: block; color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
    .stats-box strong { display: block; font-size: 1.4rem; font-weight: 700; margin-top: 3px; }

    /* ── Decision badges ───────────────────────────────────── */
    .decision-hold    { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 4px 10px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; }
    .decision-exit    { background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 4px 10px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; }
    .decision-monitor { background: linear-gradient(135deg, #ffc107, #e0a800); color: #333;  padding: 4px 10px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; }
    .decision-na      { background: #e9ecef; color: #6c757d; padding: 4px 10px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; }

    /* ── Sentiment badges ──────────────────────────────────── */
    .sentiment-bullish { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
    .sentiment-bearish { background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
    .sentiment-neutral { background: linear-gradient(135deg, #6c757d, #5a6268); color: white; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }

    /* ── Action badges ─────────────────────────────────────── */
    .action-buy-ce { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
    .action-buy-pe { background: linear-gradient(135deg, #dc3545, #fd7e14); color: white; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }

    /* ── Condition badges ──────────────────────────────────── */
    .condition-ce-up-pe-down { background: linear-gradient(135deg, #dc3545, #fd7e14); color: white; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
    .condition-ce-down-pe-up { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
    .condition-both-up       { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
    .condition-both-down     { background: linear-gradient(135deg, #6c757d, #5a6268); color: white; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
    .condition-flat          { background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }

    /* ── Price change ──────────────────────────────────────── */
    .price-up   { color: #28a745; font-weight: 700; }
    .price-down { color: #dc3545; font-weight: 700; }

    /* ── Today live panel ──────────────────────────────────── */
    .today-panel {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        border: 2px solid #00d2ff;
        border-radius: 14px;
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(0,210,255,0.25);
    }
    .today-panel h6 { color: #00d2ff; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px; }

    .stats-box-dark { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 10px 8px; border-radius: 10px; text-align: center; border-left: 4px solid #00d2ff; margin-bottom: 12px; }
    .stats-box-dark small  { display: block; color: rgba(255,255,255,0.55); font-size: 9px; text-transform: uppercase; letter-spacing: .3px; }
    .stats-box-dark strong { display: block; font-size: 1.1rem; font-weight: 700; margin-top: 4px; color: white; }
    .stats-box-dark.green  { border-left-color: #28a745; }
    .stats-box-dark.red    { border-left-color: #dc3545; }
    .stats-box-dark.yellow { border-left-color: #ffc107; }

    /* ── Sticky columns ────────────────────────────────────── */
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .custom--table { min-width: 2600px; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position: sticky; z-index: 10; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left: 0; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left: 40px; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left: 120px; }

    /* ── Section dividers ──────────────────────────────────── */
    .th-orig  { background: rgba(102,126,234,0.12); color: #667eea !important; }
    .th-exit  { background: rgba(0,210,255,0.10);   color: #00d2ff !important; }
    .th-exit2 { background: rgba(0,255,180,0.10);   color: #00ffb4 !important; }
    .th-dec   { background: rgba(168,85,247,0.12);  color: #a855f7 !important; }
    .th-dec2  { background: rgba(255,140,0,0.12);   color: #ff8c00 !important; }

    .td-orig  { background: rgba(102,126,234,0.04) !important; }
    .td-exit  { background: rgba(0,210,255,0.04)   !important; }
    .td-exit2 { background: rgba(0,255,180,0.04)   !important; }
    .td-dec   { background: rgba(168,85,247,0.06)  !important; }
    .td-dec2  { background: rgba(255,140,0,0.06)   !important; }

    /* ── Section separator border ──────────────────────────── */
    .section-sep { border-left: 2px solid rgba(255,255,255,0.15) !important; }

    /* ── Logic alert ───────────────────────────────────────── */
    .logic-alert {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white; border: none; border-radius: 12px;
        margin-bottom: 20px; padding: 15px;
    }
    .logic-alert h6 { color: white; margin-bottom: 10px; font-size: 14px; }
    .logic-alert ul  { font-size: 10px; margin-top: 5px; }
    .new-feature-badge { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; margin-left: 5px; }

    /* ── Time label pills ──────────────────────────────────── */
    .time-pill-915  { background: rgba(0,210,255,0.2);  color: #00d2ff;  border: 1px solid rgba(0,210,255,0.4);  padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; }
    .time-pill-930  { background: rgba(0,255,180,0.2);  color: #00ffb4;  border: 1px solid rgba(0,255,180,0.4);  padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- ── Header ──────────────────────────────────────────── --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>🎯 {{ $pageTitle }} <span class="new-feature-badge">BTST EXIT</span></h4>
                    <p>
                        Signal Date (trade taken after 3PM) → Next Day <strong>09:15</strong> &amp; <strong>09:30</strong> OI vs Signal Day 15:15 OI
                        &nbsp;|&nbsp; Same Direction = HOLD &nbsp;|&nbsp; Opposite = EXIT
                    </p>
                </div>
                <div>
                    <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-outline-light btn-sm mr-2">
                        <i class="fas fa-chart-pie"></i> PE/CE Analysis
                    </a>
                    <a href="{{ route('oiiv-auto.config') }}" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-cog"></i> Configs
                    </a>
                </div>
            </div>
        </div>

        {{-- ── Logic Explanation ───────────────────────────────── --}}
        <div class="logic-alert">
            <h6><i class="fas fa-info-circle"></i> <strong>Exit Plan Logic</strong></h6>
            <div class="row">
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>📅 Date Flow Example</strong></small>
                    <ul>
                        <li>Trade taken: <strong>9 March</strong> after 3PM</li>
                        <li>Exit check: <strong>10 March</strong> at 09:15 &amp; 09:30</li>
                        <li>"Exit Check Date" = next trading day</li>
                        <li>"Signal Date" = trade was taken on this day</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>📊 OI Comparison (both candles)</strong></small>
                    <ul>
                        <li><strong>Prev OI</strong> = Signal Date 15:15 CE/PE OI (same baseline)</li>
                        <li><strong>09:15 OI</strong> = Exit Date 09:15 CE/PE vs Prev</li>
                        <li><strong>09:30 OI</strong> = Exit Date 09:30 CE/PE vs Prev</li>
                        <li>Same signal logic applied to both</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>🚦 Decision Rules</strong></small>
                    <ul>
                        <li>🟢 <strong>HOLD</strong> — Exit sentiment = same as trade direction</li>
                        <li>🔴 <strong>EXIT</strong> — Exit sentiment = OPPOSITE to trade</li>
                        <li>🟡 <strong>MONITOR</strong> — Exit sentiment = NEUTRAL</li>
                        <li>⚪ <strong>N/A</strong> — No OI data for that candle</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <small style="font-size:11px;"><strong>📌 Trade Direction Map</strong></small>
                    <ul>
                        <li>BUY CE → needs BULLISH to hold</li>
                        <li>BUY PE → needs BEARISH to hold</li>
                        <li>Check both 09:15 &amp; 09:30 for confirmation</li>
                        <li>Weekend/holiday → skipped automatically</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- ── Today's Live Exit Check Panel ─────────────────────── --}}
        <div class="today-panel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6><i class="fas fa-bolt"></i> ⚡ Today's Live Exit Check
                    <small style="color:rgba(255,255,255,0.6); font-size:10px; font-weight:400; margin-left:10px;">
                        Trades from yesterday → Exit signal at today's 09:15 &amp; 09:30
                    </small>
                </h6>
                <button id="btn_today_check" class="btn btn-sm" style="background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:white; font-size:11px; font-weight:700;">
                    <i class="fas fa-sync-alt"></i> Check Today ({{ date('d M Y') }})
                </button>
            </div>
            <div class="row" id="today_stats" style="display:none;">
                <div class="col-6 col-md-3"><div class="stats-box-dark"><small>📋 Total Symbols</small><strong id="today_total">0</strong></div></div>
                <div class="col-6 col-md-3"><div class="stats-box-dark green"><small>🟢 HOLD (09:15)</small><strong id="today_hold" style="color:#28a745;">0</strong></div></div>
                <div class="col-6 col-md-3"><div class="stats-box-dark red"><small>🔴 EXIT (09:15)</small><strong id="today_exit" style="color:#dc3545;">0</strong></div></div>
                <div class="col-6 col-md-3"><div class="stats-box-dark yellow"><small>🟡 MONITOR (09:15)</small><strong id="today_monitor" style="color:#ffc107;">0</strong></div></div>
            </div>
            <div id="today_results" style="display:none;">
                <div class="table-responsive" style="max-height:320px; overflow-y:auto;">
                    <table class="table table-sm mb-0" style="font-size:11px; min-width:1200px;">
                        <thead style="position:sticky; top:0; background:#0f2027; z-index:5;">
                            <tr>
                                <th style="color:#00d2ff; padding:6px 8px;">Symbol</th>
                                <th style="color:#667eea; padding:6px 8px;">Original Trade</th>
                                <th style="color:#667eea; padding:6px 8px;">Orig Condition</th>

                                <th style="color:#00d2ff; padding:6px 8px; border-left:2px solid rgba(0,210,255,0.3);">
                                    <span class="time-pill-915">09:15</span> Exit OI
                                </th>
                                <th style="color:#00d2ff; padding:6px 8px;">
                                    <span class="time-pill-915">09:15</span> Condition
                                </th>
                                <th style="color:#a855f7; padding:6px 8px; font-size:12px;">
                                    🎯 <span class="time-pill-915">09:15</span>
                                </th>

                                <th style="color:#00ffb4; padding:6px 8px; border-left:2px solid rgba(0,255,180,0.3);">
                                    <span class="time-pill-930">09:30</span> Exit OI
                                </th>
                                <th style="color:#00ffb4; padding:6px 8px;">
                                    <span class="time-pill-930">09:30</span> Condition
                                </th>
                                <th style="color:#ff8c00; padding:6px 8px; font-size:12px;">
                                    🎯 <span class="time-pill-930">09:30</span>
                                </th>

                                <th style="color:rgba(255,255,255,0.6); padding:6px 8px;">Reason (09:15)</th>
                            </tr>
                        </thead>
                        <tbody id="today_tbody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="today_empty" style="color:rgba(255,255,255,0.5); font-size:12px; text-align:center; padding:20px 0;">
                Click "Check Today" to see live exit signals for today
            </div>
        </div>

        {{-- ── Filters ───────────────────────────────────────────── --}}
        <div class="filter-section">
            <div class="row mb-2">
                <div class="col-md-3">
                    <label><i class="fas fa-calendar-alt"></i> Exit Check Date From:</label>
                    <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d', strtotime('-7 days')) }}" />
                    <small style="color:rgba(255,255,255,0.8); font-size:10px;">The date you check exit (next day after trade)</small>
                </div>
                <div class="col-md-3">
                    <label><i class="fas fa-calendar-alt"></i> Exit Check Date To:</label>
                    <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>
                <div class="col-md-2">
                    <label><i class="fas fa-filter"></i> Symbols (Optional):</label>
                    <select id="symbol_filter" class="form-control" multiple size="2"></select>
                    <small style="color:rgba(255,255,255,0.8); font-size:10px;">Leave empty for all</small>
                </div>
                <div class="col-md-2">
                    <label><i class="fas fa-traffic-light"></i> Filter Decision (09:15):</label>
                    <select id="decision_filter" class="form-control">
                        <option value="">All Decisions</option>
                        <option value="HOLD">🟢 HOLD Only</option>
                        <option value="EXIT">🔴 EXIT Only</option>
                        <option value="MONITOR">🟡 MONITOR Only</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div style="width:100%;">
                        <button type="button" id="run_analysis" class="btn btn-light btn-block mb-1" style="font-size:12px; font-weight:700;">
                            <i class="fas fa-search"></i> View Exit Signals
                        </button>
                        <button type="button" id="reset_filters" class="btn btn-outline-light btn-block" style="font-size:11px;">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Stats ─────────────────────────────────────────────── --}}
        <div class="row mb-3">
            <div class="col-md-2"><div class="stats-box"><small>Total Records</small><strong id="stat_total" class="text-dark">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>🟢 HOLD (09:15)</small><strong id="stat_hold" style="color:#28a745;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>🔴 EXIT (09:15)</small><strong id="stat_exit" style="color:#dc3545;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>🟡 MONITOR (09:15)</small><strong id="stat_monitor" style="color:#ffc107;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE Trades</small><strong id="stat_buyce" style="color:#28a745;">0</strong></div></div>
            <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE Trades</small><strong id="stat_buype" style="color:#dc3545;">0</strong></div></div>
        </div>

        {{-- ── Main Table ────────────────────────────────────────── --}}
        <div style="position:relative; min-height:400px;">
            <div class="loading-overlay" id="loading-overlay" style="display:none;">
                <div class="spinner"></div>
                <div class="loading-text" id="loading_text">Loading exit signals...</div>
            </div>

            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Exit Check<br><small style="font-weight:400;opacity:.7;">Next Day</small></th>
                            <th>Symbol</th>

                            {{-- Original Signal --}}
                            <th class="th-orig">Signal Date<br><small style="font-weight:400;opacity:.7;">Trade Day</small></th>
                            <th class="th-orig">Original<br>Sentiment</th>
                            <th class="th-orig">Trade<br>Action</th>
                            <th class="th-orig">Orig CE%</th>
                            <th class="th-orig">Orig PE%</th>
                            <th class="th-orig">Orig Condition</th>

                            {{-- Exit Signal 09:15 --}}
                            <th class="th-exit section-sep">
                                <span class="time-pill-915">09:15</span><br>CE OI
                            </th>
                            <th class="th-exit">
                                <span class="time-pill-915">09:15</span><br>PE OI
                            </th>
                            <th class="th-exit">
                                <span class="time-pill-915">09:15</span><br>CE%
                            </th>
                            <th class="th-exit">
                                <span class="time-pill-915">09:15</span><br>PE%
                            </th>
                            <th class="th-exit">
                                <span class="time-pill-915">09:15</span><br>Condition
                            </th>
                            <th class="th-exit">
                                <span class="time-pill-915">09:15</span><br>Sentiment
                            </th>
                            <th class="th-dec section-sep" style="font-size:12px;">
                                🎯 Decision<br><span class="time-pill-915">09:15</span>
                            </th>
                            <th class="th-dec">
                                Reason<br><span class="time-pill-915">09:15</span>
                            </th>

                            {{-- Exit Signal 09:30 --}}
                            <th class="th-exit2 section-sep">
                                <span class="time-pill-930">09:30</span><br>CE OI
                            </th>
                            <th class="th-exit2">
                                <span class="time-pill-930">09:30</span><br>PE OI
                            </th>
                            <th class="th-exit2">
                                <span class="time-pill-930">09:30</span><br>CE%
                            </th>
                            <th class="th-exit2">
                                <span class="time-pill-930">09:30</span><br>PE%
                            </th>
                            <th class="th-exit2">
                                <span class="time-pill-930">09:30</span><br>Condition
                            </th>
                            <th class="th-exit2">
                                <span class="time-pill-930">09:30</span><br>Sentiment
                            </th>
                            <th class="th-dec2 section-sep" style="font-size:12px;">
                                🎯 Decision<br><span class="time-pill-930">09:30</span>
                            </th>
                            <th class="th-dec2">
                                Reason<br><span class="time-pill-930">09:30</span>
                            </th>

                            {{-- Price --}}
                            <th>Signal ₹<br><small style="font-weight:400;opacity:.7;">Entry ref</small></th>
                            <th>Exit ₹<br><small style="font-weight:400;opacity:.7;">09:15 price</small></th>
                            <th>Chg%</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="28" class="text-center py-5">
                                <i class="fas fa-door-open" style="font-size:3rem; opacity:0.4; color:#a855f7;"></i>
                                <p style="font-size:1.1rem; margin-top:20px; color:#888;">
                                    Click <strong>"View Exit Signals"</strong> to load the exit plan
                                </p>
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
    let exitData = [];

    /* ── Loading ─────────────────────────────────────────────── */
    function toggleLoading(show, msg = 'Loading exit signals...') {
        if (show) { $('#loading_text').text(msg); $('#loading-overlay').show(); }
        else       { $('#loading-overlay').hide(); }
    }

    /* ── Init ────────────────────────────────────────────────── */
    $(document).ready(function () {
        loadSymbols();
    });

    function loadSymbols() {
        $.ajax({
            url: '{{ route("exit-plan.symbols") }}', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                let opts = '';
                res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
                $('#symbol_filter').html(opts);
            }
        });
    }

    /* ── Badge helpers ───────────────────────────────────────── */
    function getSentimentBadge(s) {
        if (s === 'BULLISH') return '<span class="sentiment-bullish">🟢 BULLISH</span>';
        if (s === 'BEARISH') return '<span class="sentiment-bearish">🔴 BEARISH</span>';
        return '<span class="sentiment-neutral">⚪ NEUTRAL</span>';
    }

    function getDecisionBadge(d) {
        if (d === 'HOLD')    return '<span class="decision-hold">🟢 HOLD</span>';
        if (d === 'EXIT')    return '<span class="decision-exit">🔴 EXIT</span>';
        if (d === 'MONITOR') return '<span class="decision-monitor">🟡 MONITOR</span>';
        if (d === 'N/A')     return '<span class="decision-na">⚪ N/A</span>';
        return '<span class="text-muted">—</span>';
    }

    function getActionBadge(a) {
        if (a === 'BUY CE') return '<span class="action-buy-ce">📈 BUY CE</span>';
        if (a === 'BUY PE') return '<span class="action-buy-pe">📉 BUY PE</span>';
        return '<span class="text-muted">WAIT</span>';
    }

    function getConditionBadge(c) {
        if (!c || c === 'Flat') return '<span class="condition-flat">Flat</span>';
        if (c.includes('CE ↑ + PE ↓')) return `<span class="condition-ce-up-pe-down">${c}</span>`;
        if (c.includes('CE ↓ + PE ↑')) return `<span class="condition-ce-down-pe-up">${c}</span>`;
        if (c.includes('Both ↑'))       return `<span class="condition-both-up">${c}</span>`;
        if (c.includes('Both ↓'))       return `<span class="condition-both-down">${c}</span>`;
        return `<span class="condition-flat">${c}</span>`;
    }

    function fmtOI(v) {
        const n = Number(v) || 0;
        if (n >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
        if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
        return n.toString();
    }

    function fmtPct(v) {
        const n = parseFloat(v) || 0;
        const cls = n > 0 ? 'text-success' : (n < 0 ? 'text-danger' : 'text-muted');
        return `<span class="${cls}"><strong>${n > 0 ? '+' : ''}${n.toFixed(2)}%</strong></span>`;
    }

    /* ── Main analysis ───────────────────────────────────────── */
    function runAnalysis() {
        const fromDate = $('#from_date').val();
        const toDate   = $('#to_date').val();
        const symbols  = $('#symbol_filter').val() || [];
        const decision = $('#decision_filter').val();

        if (!fromDate || !toDate) { alert('Please select both dates'); return; }

        toggleLoading(true, 'Analysing exit signals...');
        exitData = [];

        $.ajax({
            url: '{{ route("exit-plan.signals") }}',
            type: 'GET',
            data: { from_date: fromDate, to_date: toDate, symbols: symbols, filter_decision: decision },
            success: function (res) {
                if (res.success && res.data && res.data.length > 0) {
                    exitData = res.data;
                    renderTable();
                    updateStats();
                } else {
                    showNoData(res.message || 'No exit signal data found for the selected range');
                    resetStats();
                }
                toggleLoading(false);
            },
            error: function (xhr) {
                showNoData('Error loading data: ' + (xhr.responseJSON?.message || 'Unknown error'));
                resetStats();
                toggleLoading(false);
            }
        });
    }

    /* ── Table render ────────────────────────────────────────── */
    function renderTable() {
        let html = '';

        exitData.forEach(function (row, i) {
            // Row highlight by 09:15 decision
            let rowStyle = '';
            if (row.exit_decision === 'HOLD')    rowStyle = 'style="background:rgba(40,167,69,0.04);"';
            if (row.exit_decision === 'EXIT')     rowStyle = 'style="background:rgba(220,53,69,0.06); outline:1px solid rgba(220,53,69,0.2);"';
            if (row.exit_decision === 'MONITOR')  rowStyle = 'style="background:rgba(255,193,7,0.05);"';

            const priceCls = (row.price_change_pct || 0) >= 0 ? 'price-up' : 'price-down';
            const pctSign  = (row.price_change_pct || 0) >= 0 ? '+' : '';

            html += `
            <tr ${rowStyle}>
                <td><strong>${i + 1}</strong></td>
                <td><strong style="color:#00d2ff;">${row.exit_check_date}</strong></td>
                <td><strong style="color:#667eea;">${row.symbol}</strong></td>

                {{-- Original --}}
                <td class="td-orig"><strong>${row.signal_date}</strong></td>
                <td class="td-orig">${getSentimentBadge(row.orig_sentiment)}</td>
                <td class="td-orig">${getActionBadge(row.orig_trade_action)}</td>
                <td class="td-orig">${fmtPct(row.orig_ce_oi_pct)}</td>
                <td class="td-orig">${fmtPct(row.orig_pe_oi_pct)}</td>
                <td class="td-orig">${getConditionBadge(row.orig_condition)}</td>

                {{-- Exit 09:15 --}}
                <td class="td-exit section-sep">
                    <strong>${fmtOI(row.exit_ce_oi)}</strong><br>
                    <small style="color:#aaa;font-size:9px;">${(row.exit_ce_oi||0).toLocaleString()}</small>
                </td>
                <td class="td-exit">
                    <strong>${fmtOI(row.exit_pe_oi)}</strong><br>
                    <small style="color:#aaa;font-size:9px;">${(row.exit_pe_oi||0).toLocaleString()}</small>
                </td>
                <td class="td-exit">${fmtPct(row.exit_ce_oi_pct)}</td>
                <td class="td-exit">${fmtPct(row.exit_pe_oi_pct)}</td>
                <td class="td-exit">${getConditionBadge(row.exit_condition)}</td>
                <td class="td-exit">${getSentimentBadge(row.exit_sentiment)}</td>
                <td class="td-dec section-sep" style="min-width:90px;">${getDecisionBadge(row.exit_decision)}</td>
                <td class="td-dec" style="max-width:200px; text-align:left !important; font-size:9px; color:#666;">${row.exit_reason || ''}</td>

                {{-- Exit 09:30 --}}
                <td class="td-exit2 section-sep">
                    <strong>${fmtOI(row.exit930_ce_oi)}</strong><br>
                    <small style="color:#aaa;font-size:9px;">${(row.exit930_ce_oi||0).toLocaleString()}</small>
                </td>
                <td class="td-exit2">
                    <strong>${fmtOI(row.exit930_pe_oi)}</strong><br>
                    <small style="color:#aaa;font-size:9px;">${(row.exit930_pe_oi||0).toLocaleString()}</small>
                </td>
                <td class="td-exit2">${fmtPct(row.exit930_ce_oi_pct)}</td>
                <td class="td-exit2">${fmtPct(row.exit930_pe_oi_pct)}</td>
                <td class="td-exit2">${getConditionBadge(row.exit930_condition)}</td>
                <td class="td-exit2">${getSentimentBadge(row.exit930_sentiment)}</td>
                <td class="td-dec2 section-sep" style="min-width:90px;">${getDecisionBadge(row.exit930_decision)}</td>
                <td class="td-dec2" style="max-width:200px; text-align:left !important; font-size:9px; color:#666;">${row.exit930_reason || ''}</td>

                {{-- Price --}}
                <td>${row.signal_price > 0 ? '<strong>₹' + Number(row.signal_price).toLocaleString() + '</strong>' : '<span class="text-muted">—</span>'}</td>
                <td>${row.exit_check_price > 0 ? '<strong>₹' + Number(row.exit_check_price).toLocaleString() + '</strong>' : '<span class="text-muted">—</span>'}</td>
                <td class="${priceCls}"><strong>${pctSign}${(row.price_change_pct || 0).toFixed(2)}%</strong></td>
            </tr>`;
        });

        $('#analysis-tbody').html(html);
    }

    /* ── Stats ───────────────────────────────────────────────── */
    function updateStats() {
        $('#stat_total').text(exitData.length);
        $('#stat_hold').text(exitData.filter(r => r.exit_decision === 'HOLD').length);
        $('#stat_exit').text(exitData.filter(r => r.exit_decision === 'EXIT').length);
        $('#stat_monitor').text(exitData.filter(r => r.exit_decision === 'MONITOR').length);
        $('#stat_buyce').text(exitData.filter(r => r.orig_trade_action === 'BUY CE').length);
        $('#stat_buype').text(exitData.filter(r => r.orig_trade_action === 'BUY PE').length);
    }

    function resetStats() {
        $('#stat_total,#stat_hold,#stat_exit,#stat_monitor,#stat_buyce,#stat_buype').text('0');
    }

    function showNoData(msg) {
        $('#analysis-tbody').html(`
            <tr><td colspan="28" class="text-center py-5">
                <i class="fas fa-info-circle" style="color:#17a2b8; font-size:3rem;"></i>
                <p class="text-info" style="margin-top:20px;">${msg}</p>
            </td></tr>`);
    }

    /* ── TODAY'S LIVE CHECK ──────────────────────────────────── */
    $('#btn_today_check').click(function () {
        const $btn = $(this);
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Checking...');
        $btn.prop('disabled', true);

        const symbols = $('#symbol_filter').val() || [];

        $.ajax({
            url: '{{ route("exit-plan.today") }}',
            type: 'GET',
            data: { check_date: '{{ date("Y-m-d") }}', symbols: symbols },
            success: function (res) {
                if (res.success) {
                    $('#today_total').text(res.total);
                    $('#today_hold').text(res.hold_count);
                    $('#today_exit').text(res.exit_count);
                    $('#today_monitor').text(res.monitor_count);
                    $('#today_stats').show();

                    if (res.data && res.data.length > 0) {
                        renderTodayTable(res.data);
                        $('#today_results').show();
                        $('#today_empty').hide();
                    } else {
                        $('#today_empty').text(res.message || 'No data found for today').show();
                        $('#today_results').hide();
                    }
                } else {
                    $('#today_empty').text(res.message || 'Error loading data').show();
                }
                $btn.html('<i class="fas fa-sync-alt"></i> Check Today ({{ date("d M Y") }})');
                $btn.prop('disabled', false);
            },
            error: function () {
                $('#today_empty').text('Error loading exit data').show();
                $btn.html('<i class="fas fa-sync-alt"></i> Check Today ({{ date("d M Y") }})');
                $btn.prop('disabled', false);
            }
        });
    });

    function renderTodayTable(data) {
        let html = '';
        data.forEach(function (row) {
            let trBg = '';
            if (row.exit_decision === 'EXIT')    trBg = 'background:rgba(220,53,69,0.12);';
            if (row.exit_decision === 'HOLD')    trBg = 'background:rgba(40,167,69,0.06);';
            if (row.exit_decision === 'MONITOR') trBg = 'background:rgba(255,193,7,0.06);';

            html += `
            <tr style="${trBg}">
                <td style="color:#00d2ff; font-weight:700; padding:5px 8px;">${row.symbol}</td>
                <td style="padding:5px 8px;">${getSentimentBadge(row.orig_sentiment)} ${getActionBadge(row.orig_trade_action)}</td>
                <td style="padding:5px 8px;">${getConditionBadge(row.orig_condition)}</td>

                <td style="padding:5px 8px; border-left:2px solid rgba(0,210,255,0.2);">
                    ${getSentimentBadge(row.exit_sentiment)}
                </td>
                <td style="padding:5px 8px;">${getConditionBadge(row.exit_condition)}</td>
                <td style="padding:5px 8px;">${getDecisionBadge(row.exit_decision)}</td>

                <td style="padding:5px 8px; border-left:2px solid rgba(0,255,180,0.2);">
                    ${getSentimentBadge(row.exit930_sentiment)}
                </td>
                <td style="padding:5px 8px;">${getConditionBadge(row.exit930_condition)}</td>
                <td style="padding:5px 8px;">${getDecisionBadge(row.exit930_decision)}</td>

                <td style="padding:5px 8px; color:rgba(255,255,255,0.6); font-size:9px; max-width:200px;">${row.exit_reason || ''}</td>
            </tr>`;
        });
        $('#today_tbody').html(html);
    }

    /* ── Buttons ─────────────────────────────────────────────── */
    $('#run_analysis').click(() => runAnalysis());
    $('#reset_filters').click(function () {
        $('#from_date').val('{{ date("Y-m-d", strtotime("-7 days")) }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter,#decision_filter').val('');
        exitData = [];
        showNoData('Click "View Exit Signals" to load the exit plan');
        resetStats();
    });
</script>
@endpush