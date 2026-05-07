@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ── Base table ─────────────────────────────────────── */
    .custom--table thead th,
    .custom--table tbody td {
        text-align: center !important;
        padding: 7px 5px !important;
        font-size: 11px !important;
        vertical-align: middle;
    }
    .custom--table thead th:first-child,  .custom--table tbody td:first-child,
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) {
        text-align: left !important;
    }

    /* ── Sticky first 3 columns ─────────────────────────── */
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .custom--table    { min-width: 2600px; }

    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { position:sticky; left:0;    z-index:10; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { position:sticky; left:40px; z-index:10; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position:sticky; left:120px;z-index:10; }

    /* ── Loading overlay ────────────────────────────────── */
    .loading-overlay {
        position:absolute; top:0; left:0; right:0; bottom:0;
        background:rgba(19,45,57,0.95);
        display:flex; flex-direction:column;
        justify-content:center; align-items:center;
        z-index:1000; border-radius:12px;
    }
    .spinner {
        width:50px; height:50px;
        border:5px solid #f3f3f3; border-top:5px solid #3498db;
        border-radius:50%; animation:spin 1s linear infinite;
    }
    .loading-text { color:white; margin-top:20px; font-size:16px; font-weight:600; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    /* ── Sentiment column group headers ─────────────────── */
    .th-s1 { background: rgba(52,152,219,0.18) !important;}
    .th-s2 { background: rgba(46,204,113,0.18) !important;}
    .th-s3 { background: rgba(243,156,18,0.18) !important;}
    .th-exit { background:rgba(168,85,247,0.15) !important;}

    .td-s1 { background: rgba(52,152,219,0.05); }
    .td-s2 { background: rgba(46,204,113,0.05); }
    .td-s3 { background: rgba(243,156,18,0.05); }
    .td-exit { background: rgba(168,85,247,0.05); }

    /* ── Action badges ──────────────────────────────────── */
    .action-buy-ce { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .action-buy-pe { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .action-wait   { background:linear-gradient(135deg,#ffc107,#ff9800); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Sentiment badges ───────────────────────────────── */
    .sent-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sent-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sent-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Condition badges ───────────────────────────────── */
    .cond-ce-up-pe-down { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-ce-down-pe-up { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-both-up       { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-both-down     { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-flat          { background:#e9ecef; color:#495057; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── P/L ────────────────────────────────────────────── */
    .profit-positive { color:#28a745; font-weight:700; font-size:11px; }
    .profit-negative { color:#dc3545; font-weight:700; font-size:11px; }
    .profit-loading  { color:#aaa; font-size:10px; font-style:italic; }

    /* ── Stats boxes ────────────────────────────────────── */
    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.1); transition:transform .2s; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#666; font-size:10px; text-transform:uppercase; }
    .stats-box strong { display:block; font-size:1.4rem; font-weight:700; margin-top:3px; }

    /* ── Aligned section ────────────────────────────────── */
    .aligned-section {
        background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);
        border:2px solid #00d2ff; border-radius:14px;
        padding:16px 20px 8px; margin-bottom:20px;
        box-shadow:0 4px 20px rgba(0,210,255,0.25);
    }
    .aligned-section h6 { color:#00d2ff; font-size:13px; font-weight:700; margin:0; }
    .stats-box-dark { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); padding:10px 8px; border-radius:10px; text-align:center; border-left:4px solid #00d2ff; margin-bottom:12px; }
    .stats-box-dark small  { display:block; color:rgba(255,255,255,0.55); font-size:9px; text-transform:uppercase; }
    .stats-box-dark strong { display:block; font-size:1.1rem; font-weight:700; margin-top:4px; color:white; }

    /* ── Filter section ─────────────────────────────────── */
    .filter-section { background:linear-gradient(135deg,#667eea,#764ba2); padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4); color:white; }
    .filter-section label { color:white !important; font-weight:600; margin-bottom:6px; font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.9); color:#333; font-size:12px; padding:6px 10px; }

    /* ── Page header ────────────────────────────────────── */
    .page-header { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4); }
    .new-feature-badge { background:linear-gradient(135deg,#f093fb,#f5576c); color:white; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }

    /* ── 3-way agreement highlight ──────────────────────── */
    .row-full-agree  { outline:2px solid #ffd700; background:rgba(255,215,0,0.06) !important; }
    .row-two-agree   { outline:1px solid rgba(0,210,255,0.4); background:rgba(0,210,255,0.04) !important; }

    /* ── Agreement badge ────────────────────────────────── */
    .agree-3 { background:linear-gradient(135deg,#f39c12,#e67e22); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .agree-2 { background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .agree-1 { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .ma-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .ma-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:2px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .option-symbol-badge { color:white; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4>{{ $pageTitle }} <span class="new-feature-badge">3-SENTIMENT EOD BTST</span></h4>
                <p>Three independent OI sentiment signals per symbol &nbsp;|&nbsp; ATM strike-set comparison &nbsp;|&nbsp; Exit: Next day 09:30 open</p>
            </div>
            <div>
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm mr-2"><i class="fas fa-chart-bar"></i> Standard PE/CE</a>
                <a href="{{ route('oiiv-auto.config') }}"        class="btn btn-light btn-sm mr-2"><i class="fas fa-cog"></i> Configs</a>
                <a href="{{ route('oiiv-auto.index') }}"         class="btn btn-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
            </div>
        </div>
    </div>

    {{-- ── Logic Alert ─────────────────────────────────────────── --}}
    <div class="alert" style="background:linear-gradient(135deg,#667eea,#764ba2); color:white; border:none; border-radius:12px; margin-bottom:20px; padding:15px;">
        <h6 style="color:white; margin-bottom:10px; font-size:14px;"><i class="fas fa-info-circle"></i> <strong>Three Sentiment Modes — Same OI Logic, Different Strike Sets</strong></h6>
        <div class="row">
            <div class="col-md-3">
                <small style="color:#a8d8ff; font-size:11px;"><strong>🔵 Sentiment 1 — Wide (ATM-1 + ATM + ATM+1)</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>CE OI = sum of 3 CE strikes around ATM</li>
                    <li>PE OI = sum of 3 PE strikes around ATM</li>
                    <li>Broader market pressure view</li>
                    <li><strong>Primary trade action driven by S1</strong></li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="color:#a8ffb2; font-size:11px;"><strong>🟢 Sentiment 2 — ATM Only</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>CE OI = ATM CE strike only</li>
                    <li>PE OI = ATM PE strike only</li>
                    <li>Purest signal — max pain level</li>
                    <li>Highest precision, fewer triggers</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="color:#ffe8a8; font-size:11px;"><strong>🟠 Sentiment 3 — Directional</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>CE OI = ATM + ATM+1 (upside resistance)</li>
                    <li>PE OI = ATM-1 + ATM (downside support)</li>
                    <li>Captures asymmetric OI pressure</li>
                    <li>Good for breakout/breakdown trades</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="color:#ffa8e8; font-size:11px;"><strong>🏆 Agreement Score</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>⭐⭐⭐ All 3 agree → Highest confidence</li>
                    <li>⭐⭐ 2 of 3 agree → Moderate confidence</li>
                    <li>⭐ Only 1 → Low confidence / conflicted</li>
                    <li>Golden rows = 3-way agreement</li>
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
                <label><i class="fas fa-bullseye"></i> Trade Action (S1):</label>
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

    {{-- ── Stats Row ─────────────────────────────────────────────── --}}
    <div class="row mb-2">
        <div class="col-md-2"><div class="stats-box"><small>Total Records</small><strong id="total_records" class="text-dark">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE (S1)</small><strong id="buy_ce_count" style="color:#28a745;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE (S1)</small><strong id="buy_pe_count" style="color:#dc3545;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#ffd700;"><small>⭐⭐⭐ All 3 Agree</small><strong id="agree3_count" style="color:#e67e22;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#00d2ff;"><small>⭐⭐ 2-of-3 Agree</small><strong id="agree2_count" style="color:#00d2ff;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>Trade Count</small><strong id="trade_count" style="color:#17a2b8;">0</strong></div></div>
    </div>
    <div class="row mb-3">
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#6c757d;"><small>Avg Investment</small><strong id="avg_investment" class="text-dark" style="font-size:1rem;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Exit Total P/L</small><strong id="exit_total_pl" style="font-size:1rem;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Avg Exit ROI</small><strong id="exit_avg_roi" style="color:#a855f7; font-size:1rem;">0%</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#17a2b8;"><small>📈 High Total P/L</small><strong id="high_total_pl" style="font-size:1rem;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#fd7e14;"><small>📉 Low Total P/L</small><strong id="low_total_pl" style="font-size:1rem;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>🚪 Exit Win Rate</small><strong id="exit_win_rate" style="font-size:1rem;">0%</strong></div></div>
    </div>

    {{-- ── Aligned Section (All-3-Agree) ──────────────────────── --}}
    <div class="aligned-section">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span style="font-size:20px;">🏆</span>
            <h6>All 3-Sentiment Agree — Highest Confidence</h6>
            <span style="background:linear-gradient(135deg,#f39c12,#e67e22); color:white; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; text-transform:uppercase; margin-left:8px;">Triple Confirm</span>
        </div>
        <div class="row">
            <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#ffd700;"><small>⭐⭐⭐ Triple Agree</small><strong id="aligned_count" style="color:#ffd700;">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#28a745;"><small>BUY CE Aligned</small><strong id="aligned_buy_ce" style="color:#28a745;">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#dc3545;"><small>BUY PE Aligned</small><strong id="aligned_buy_pe" style="color:#dc3545;">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#ffc107;"><small>Avg Investment</small><strong id="aligned_avg_inv" style="color:#ffc107;">₹0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#a855f7;"><small>🚪 Exit Win Rate</small><strong id="aligned_exit_win_rate" style="color:#a855f7;">0%</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box-dark" style="border-left-color:#adb5bd;"><small>📊 High Win Rate</small><strong id="aligned_win_rate" style="color:#adb5bd;">0%</strong></div></div>
        </div>
        <div class="row">
            <div class="col-6 col-md-3"><div class="stats-box-dark" style="border-left-color:#a855f7;"><small>🚪 Exit Total P/L</small><strong id="aligned_exit_pl">₹0</strong></div></div>
            <div class="col-6 col-md-3"><div class="stats-box-dark" style="border-left-color:#a855f7;"><small>🚪 Exit Avg ROI</small><strong id="aligned_exit_roi">0%</strong></div></div>
            <div class="col-6 col-md-3"><div class="stats-box-dark" style="border-left-color:#00d2ff;"><small>📈 High Total P/L</small><strong id="aligned_high_pl">₹0</strong></div></div>
            <div class="col-6 col-md-3"><div class="stats-box-dark" style="border-left-color:#fd7e14;"><small>📉 Low Total P/L</small><strong id="aligned_low_pl">₹0</strong></div></div>
        </div>
    </div>

    {{-- ── Table ────────────────────────────────────────────────── --}}
    <div style="position:relative; min-height:400px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Loading data...</div>
        </div>
        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        {{-- Fixed left cols --}}
                        <th rowspan="2">#</th>
                        <th rowspan="2">Date</th>
                        <th rowspan="2">Symbol<br><small style="font-weight:400;opacity:.7;">ATM Strike</small></th>

                        {{-- Sentiment 1 header --}}
                        <th colspan="5" class="th-s1">🔵 Sentiment 1 — Wide (ATM-1 + ATM + ATM+1)</th>

                        {{-- Sentiment 2 header --}}
                        <th colspan="5" class="th-s2">🟢 Sentiment 2 — ATM Only</th>

                        {{-- Sentiment 3 header --}}
                        <th colspan="5" class="th-s3">🟠 Sentiment 3 — Directional (CE→ATM+ATM+1 | PE→ATM-1+ATM)</th>

                        {{-- Shared / derived --}}
                        <th rowspan="2">Agreement</th>
                        <th rowspan="2">50 MA</th>
                        <th rowspan="2">Strength<br><small style="font-weight:400;opacity:.7;">S1 rank</small></th>

                        {{-- Profit cols --}}
                        <th rowspan="2">Option</th>
                        <th rowspan="2">Investment</th>
                        <th rowspan="2">Buy ₹<br><small style="font-weight:400;opacity:.7;">14:45 close</small></th>
                        <th rowspan="2" class="th-exit">Exit ₹<br><small style="font-weight:400;opacity:.8;">Next 09:30</small></th>
                        <th rowspan="2" class="th-exit">Exit P/L</th>
                        <th rowspan="2" class="th-exit">Exit ROI%</th>
                        <th rowspan="2">High ₹<br><small style="font-weight:400;opacity:.7;">Window</small></th>
                        <th rowspan="2">High P/L</th>
                        <th rowspan="2">High ROI%</th>
                        <th rowspan="2">Low ₹<br><small style="font-weight:400;opacity:.7;">Window</small></th>
                        <th rowspan="2">Low P/L</th>
                        <th rowspan="2">Low ROI%</th>
                    </tr>
                    <tr>
                        {{-- S1 sub-cols --}}
                        <th class="th-s1">CE OI %</th>
                        <th class="th-s1">PE OI %</th>
                        <th class="th-s1">Condition</th>
                        <th class="th-s1">Sentiment</th>
                        <th class="th-s1">Action</th>
                        {{-- S2 sub-cols --}}
                        <th class="th-s2">CE OI %</th>
                        <th class="th-s2">PE OI %</th>
                        <th class="th-s2">Condition</th>
                        <th class="th-s2">Sentiment</th>
                        <th class="th-s2">Action</th>
                        {{-- S3 sub-cols --}}
                        <th class="th-s3">CE OI %</th>
                        <th class="th-s3">PE OI %</th>
                        <th class="th-s3">Condition</th>
                        <th class="th-s3">Sentiment</th>
                        <th class="th-s3">Action</th>
                    </tr>
                </thead>
                <tbody id="analysis-tbody">
                    <tr>
                        <td colspan="32" class="text-center py-5">
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
            url: '{{ route("oiiv-tri.symbols") }}', type: 'GET',
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

        toggleLoading(true, 'Loading tri-sentiment signals...');
        analysisData = [];

        $.ajax({
            url: '{{ route("oiiv-tri.analyze") }}', type: 'GET',
            data: { from_date: fromDate, to_date: toDate, symbols, filter_action: action },
            success: function (res) {
                if (res.success && res.data && res.data.length > 0) {
                    analysisData = res.data;
                    displayTable();
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

    /* ── Agreement helpers ────────────────────────────────────── */
    function countAgree(row) {
        const signals = [row.s1_signal, row.s2_signal, row.s3_signal];
        const bullish = signals.filter(s => s === 'BULLISH').length;
        const bearish = signals.filter(s => s === 'BEARISH').length;
        return Math.max(bullish, bearish);
    }

    function agreeBadge(row) {
        const n = countAgree(row);
        if (n === 3) return '<span class="agree-3">⭐⭐⭐ All 3</span>';
        if (n === 2) return '<span class="agree-2">⭐⭐ 2 of 3</span>';
        return '<span class="agree-1">⭐ 1 of 3</span>';
    }

    function rowClass(row) {
        const n = countAgree(row);
        if (n === 3) return 'row-full-agree';
        if (n === 2) return 'row-two-agree';
        return '';
    }

    /* ── Badge helpers ────────────────────────────────────────── */
    function sentBadge(signal) {
        if (signal === 'BULLISH') return '<span class="sent-bullish">🟢 BULL</span>';
        if (signal === 'BEARISH') return '<span class="sent-bearish">🔴 BEAR</span>';
        return '<span class="sent-neutral">⚪ NEUT</span>';
    }

    function condBadge(cond) {
        if (!cond) return '<span class="cond-flat">N/A</span>';
        if (cond.includes('CE ↑ + PE ↓')) return `<span class="cond-ce-up-pe-down">${cond}</span>`;
        if (cond.includes('CE ↓ + PE ↑')) return `<span class="cond-ce-down-pe-up">${cond}</span>`;
        if (cond.includes('Both ↑'))       return `<span class="cond-both-up">${cond}</span>`;
        if (cond.includes('Both ↓'))       return `<span class="cond-both-down">${cond}</span>`;
        return `<span class="cond-flat">${cond}</span>`;
    }

    function actBadge(action) {
        if (action === 'BUY CE') return '<span class="action-buy-ce">📈 BUY CE</span>';
        if (action === 'BUY PE') return '<span class="action-buy-pe">📉 BUY PE</span>';
        return '<span class="action-wait">⏸ WAIT</span>';
    }

    function maBadge(signal) {
        if (!signal || signal === 'N/A') return '<span class="text-muted" style="font-size:11px;">N/A</span>';
        if (signal === 'BULLISH') return '<span class="ma-bullish">🟢 Above</span>';
        if (signal === 'BEARISH') return '<span class="ma-bearish">🔴 Below</span>';
        return '<span class="ma-neutral">⚪ On</span>';
    }

    function strengthBadge(rank, sentiment) {
        if (rank === 'Normal') return '<span style="background:#e9ecef;color:#495057;padding:2px 6px;border-radius:4px;font-size:9px;font-weight:700;">Normal</span>';
        const n = (rank || '').replace('Rank ', '');
        const c = sentiment === 'BULLISH' ? 'sent-bullish' : 'sent-bearish';
        return `<span class="${c}">${sentiment === 'BULLISH' ? '🟢' : '🔴'} R${n}</span>`;
    }

    function pctHtml(pct) {
        const v = parseFloat(pct) || 0;
        return `<strong class="${v >= 0 ? 'text-success' : 'text-danger'}">${v >= 0 ? '+' : ''}${v.toFixed(2)}%</strong>`;
    }

    /* ── Main table render ────────────────────────────────────── */
    function displayTable() {
        if (!analysisData || !analysisData.length) return;
        let html = '';

        analysisData.forEach(function (row, i) {
            const rc = rowClass(row);

            html += `
            <tr class="${rc}">
                <td><strong>${i + 1}</strong></td>
                <td><strong>${row.date}</strong></td>
                <td>
                    <strong style="color:#667eea;">${row.symbol}</strong><br>
                    <small style="color:#aaa;font-size:9px;">ATM: ${row.atm_strike || 'N/A'}</small>
                </td>

                {{-- Sentiment 1 --}}
                <td class="td-s1">${pctHtml(row.s1_ce_pct)}</td>
                <td class="td-s1">${pctHtml(row.s1_pe_pct)}</td>
                <td class="td-s1">${condBadge(row.s1_condition)}</td>
                <td class="td-s1">${sentBadge(row.s1_signal)}</td>
                <td class="td-s1">${actBadge(row.s1_trade_action)}</td>

                {{-- Sentiment 2 --}}
                <td class="td-s2">${pctHtml(row.s2_ce_pct)}</td>
                <td class="td-s2">${pctHtml(row.s2_pe_pct)}</td>
                <td class="td-s2">${condBadge(row.s2_condition)}</td>
                <td class="td-s2">${sentBadge(row.s2_signal)}</td>
                <td class="td-s2">${actBadge(row.s2_trade_action)}</td>

                {{-- Sentiment 3 --}}
                <td class="td-s3">${pctHtml(row.s3_ce_pct)}</td>
                <td class="td-s3">${pctHtml(row.s3_pe_pct)}</td>
                <td class="td-s3">${condBadge(row.s3_condition)}</td>
                <td class="td-s3">${sentBadge(row.s3_signal)}</td>
                <td class="td-s3">${actBadge(row.s3_trade_action)}</td>

                <td>${agreeBadge(row)}</td>
                <td>${maBadge(row.fut_50ma_signal)}</td>
                <td>${strengthBadge(row.strength_rank, row.s1_signal)}</td>

                <td class="pc-option-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-invest-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-buy-${i}"><span class="profit-loading">…</span></td>
                <td class="pc-exit-${i} td-exit"><span class="profit-loading">…</span></td>
                <td class="pc-exit-pl-${i} td-exit"><span class="profit-loading">…</span></td>
                <td class="pc-exit-roi-${i} td-exit"><span class="profit-loading">…</span></td>
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

    /* ── Profit cell renderer ─────────────────────────────────── */
    function applyProfitToRow(item) {
        const idx = item.index;
        const all = `.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},` +
                    `.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},` +
                    `.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},` +
                    `.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`;

        if (item.error === 'WAIT') { $(all).html('<span class="text-muted" style="font-size:10px;">WAIT</span>'); return; }
        if (item.error) {
            const eb = `<span class="badge badge-warning" style="font-size:9px;" title="${item.error}">⚠ ${item.error}</span>`;
            $(`.pc-option-${idx}`).html(item.option_symbol ? `<span class="option-symbol-badge">${item.option_symbol}</span>` : eb);
            $(`.pc-invest-${idx},.pc-buy-${idx},.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},` +
              `.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`).html(eb);
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

        $(`.pc-high-${idx}`).html(item.high_price > 0
            ? `<strong style="color:#17a2b8;">₹${Number(item.high_price).toFixed(2)}</strong>${item.high_time ? `<br><small style="color:#6c757d;font-size:9px;">${item.high_time}</small>` : ''}`
            : '<span class="text-muted">—</span>');
        $(`.pc-high-pl-${idx}`).html(plHtml(item.high_pl || 0));
        $(`.pc-high-roi-${idx}`).html(roiHtml(item.high_roi || 0));

        $(`.pc-low-${idx}`).html(item.low_price > 0
            ? `<strong style="color:#fd7e14;">₹${Number(item.low_price).toFixed(2)}</strong>${item.low_time ? `<br><small style="color:#6c757d;font-size:9px;">${item.low_time}</small>` : ''}`
            : '<span class="text-muted">—</span>');
        $(`.pc-low-pl-${idx}`).html(plHtml(item.low_pl || 0));
        $(`.pc-low-roi-${idx}`).html(roiHtml(item.low_roi || 0));
    }

    function renderNoProfitRow(idx) {
        const dash = '<span class="text-muted">—</span>';
        $(`.pc-option-${idx},.pc-invest-${idx},.pc-buy-${idx},` +
          `.pc-exit-${idx},.pc-exit-pl-${idx},.pc-exit-roi-${idx},` +
          `.pc-high-${idx},.pc-high-pl-${idx},.pc-high-roi-${idx},` +
          `.pc-low-${idx},.pc-low-pl-${idx},.pc-low-roi-${idx}`).html(dash);
    }

    /* ── Profit AJAX ──────────────────────────────────────────── */
    function loadProfitData() {
        // Use S1 trade action as the primary signal for profit calculation
        const signals = analysisData
            .map((row, idx) => ({
                index        : idx,
                date         : row.date,
                symbol       : row.symbol,
                trade_action : row.s1_trade_action,
                spot_price   : row.spot_price || 0,
            }))
            .filter(r => r.trade_action === 'BUY CE' || r.trade_action === 'BUY PE');

        analysisData.forEach((row, idx) => {
            if (row.s1_trade_action !== 'BUY CE' && row.s1_trade_action !== 'BUY PE') {
                renderNoProfitRow(idx);
            }
        });

        if (signals.length === 0) { resetAlignedStats(); return; }

        $.ajax({
            url : '{{ route("oiiv-tri.calculate-profit") }}',
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

    /* ── Stats ────────────────────────────────────────────────── */
    function updateStats() {
        if (!analysisData || !analysisData.length) { resetStats(); return; }
        $('#total_records').text(analysisData.length);
        $('#buy_ce_count').text(analysisData.filter(r => r.s1_trade_action === 'BUY CE').length);
        $('#buy_pe_count').text(analysisData.filter(r => r.s1_trade_action === 'BUY PE').length);
        $('#agree3_count').text(analysisData.filter(r => countAgree(r) === 3).length);
        $('#agree2_count').text(analysisData.filter(r => countAgree(r) === 2).length);
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
        const exitWins    = trades.filter(d => (d.exit_pl || 0) > 0).length;
        const exitWinPct  = count > 0 ? ((exitWins / count) * 100).toFixed(1) : 0;

        const fmt    = v => (v >= 0 ? '+' : '') + '₹' + Math.abs(v).toFixed(2);
        const fmtRoi = v => (v >= 0 ? '+' : '') + Math.abs(v).toFixed(2) + '%';
        const plCls  = v => v >= 0 ? 'profit-positive' : 'profit-negative';

        $('#avg_investment').html(`₹${Math.round(avgInv).toLocaleString()}`);
        $('#trade_count').text(count);
        $('#exit_total_pl').html(`<span class="${plCls(exitTotalPL)}">${fmt(exitTotalPL)}</span>`);
        $('#exit_avg_roi').html(`<span class="${plCls(exitAvgRoi)}">${fmtRoi(exitAvgRoi)}</span>`);
        $('#high_total_pl').html(`<span class="${plCls(highTotalPL)}">${fmt(highTotalPL)}</span>`);
        $('#low_total_pl').html(`<span class="${plCls(lowTotalPL)}">${fmt(lowTotalPL)}</span>`);
        $('#exit_win_rate').html(`<span class="${parseFloat(exitWinPct) >= 50 ? 'profit-positive' : 'profit-negative'}">${exitWinPct}%</span>`);

        // Aligned = all 3 agree
        const alignedTrades = trades.filter(d => {
            const row = analysisData[d.index];
            return row && countAgree(row) === 3;
        });
        updateAlignedStats(alignedTrades);
    }

    function updateAlignedStats(alignedTrades) {
        const count = alignedTrades.length;
        if (count === 0) { resetAlignedStats(); return; }

        const buyCE      = alignedTrades.filter(d => { const r = analysisData[d.index]; return r && r.s1_trade_action === 'BUY CE'; }).length;
        const buyPE      = alignedTrades.filter(d => { const r = analysisData[d.index]; return r && r.s1_trade_action === 'BUY PE'; }).length;
        const avgInv     = alignedTrades.reduce((s, d) => s + (d.investment || 0), 0) / count;
        const exitPL     = alignedTrades.reduce((s, d) => s + (d.exit_pl  || 0), 0);
        const exitRoi    = alignedTrades.reduce((s, d) => s + (d.exit_roi || 0), 0) / count;
        const highPL     = alignedTrades.reduce((s, d) => s + (d.high_pl  || 0), 0);
        const lowPL      = alignedTrades.reduce((s, d) => s + (d.low_pl   || 0), 0);
        const exitWins   = alignedTrades.filter(d => (d.exit_pl  || 0) > 0).length;
        const highWins   = alignedTrades.filter(d => (d.high_pl  || 0) > 0).length;
        const ewPct      = ((exitWins / count) * 100).toFixed(1);
        const hwPct      = ((highWins / count) * 100).toFixed(1);

        const fmt    = v => (v >= 0 ? '+' : '') + '₹' + Math.abs(v).toFixed(2);
        const fmtRoi = v => (v >= 0 ? '+' : '') + Math.abs(v).toFixed(2) + '%';
        const plCls  = v => v >= 0 ? 'profit-positive' : 'profit-negative';
        const wCls   = p => parseFloat(p) >= 50 ? 'profit-positive' : 'profit-negative';

        $('#aligned_count').text(count);
        $('#aligned_buy_ce').text(buyCE);
        $('#aligned_buy_pe').text(buyPE);
        $('#aligned_avg_inv').html(`₹${Math.round(avgInv).toLocaleString()}`);
        $('#aligned_exit_win_rate').html(`<span class="${wCls(ewPct)}">${ewPct}%</span>`);
        $('#aligned_win_rate').html(`<span class="${wCls(hwPct)}">${hwPct}%</span>`);
        $('#aligned_exit_pl').html(`<span class="${plCls(exitPL)}">${fmt(exitPL)}</span>`);
        $('#aligned_exit_roi').html(`<span class="${plCls(exitRoi)}">${fmtRoi(exitRoi)}</span>`);
        $('#aligned_high_pl').html(`<span class="${plCls(highPL)}">${fmt(highPL)}</span>`);
        $('#aligned_low_pl').html(`<span class="${plCls(lowPL)}">${fmt(lowPL)}</span>`);
    }

    function resetStats() {
        $('#total_records,#buy_ce_count,#buy_pe_count,#agree3_count,#agree2_count,#trade_count').text('0');
        $('#avg_investment').text('₹0');
        $('#exit_total_pl,#high_total_pl,#low_total_pl').text('₹0');
        $('#exit_avg_roi,#exit_win_rate').text('0%');
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
        $('#analysis-tbody').html(`
            <tr><td colspan="32" class="text-center py-5">
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
        resetStats();
        setTimeout(() => runAnalysis(), 300);
    }

    $('#run_analysis').click(() => runAnalysis());
    $('#reset_filters').click(() => resetFilters());
</script>
@endpush