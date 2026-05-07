@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
.custom--table thead th, .custom--table tbody td { text-align:center!important; padding:8px 6px!important; font-size:11px!important; vertical-align:middle; }
.custom--table thead th:first-child, .custom--table tbody td:first-child,
.custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
.custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { text-align:left!important; }
.loading-overlay { position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(19,45,57,.95);display:flex;flex-direction:column;justify-content:center;align-items:center;z-index:1000;border-radius:12px; }
.spinner { width:50px;height:50px;border:5px solid #f3f3f3;border-top:5px solid #3498db;border-radius:50%;animation:spin 1s linear infinite; }
.loading-text { color:#fff;margin-top:20px;font-size:16px;font-weight:600; }
@keyframes spin { 0%{transform:rotate(0deg)}100%{transform:rotate(360deg)} }
.action-buy-ce { background:linear-gradient(135deg,#28a745,#20c997);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.action-buy-pe { background:linear-gradient(135deg,#dc3545,#fd7e14);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.action-wait   { background:linear-gradient(135deg,#ffc107,#ff9800);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.sentiment-bullish { background:linear-gradient(135deg,#28a745,#20c997);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.sentiment-bearish { background:linear-gradient(135deg,#dc3545,#c82333);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.sentiment-neutral { background:linear-gradient(135deg,#6c757d,#5a6268);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.condition-ce-up-pe-down { background:linear-gradient(135deg,#dc3545,#fd7e14);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.condition-ce-down-pe-up { background:linear-gradient(135deg,#28a745,#20c997);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.condition-both-up { background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.condition-both-down { background:linear-gradient(135deg,#6c757d,#5a6268);color:#fff;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.condition-flat { background:#e9ecef;color:#495057;padding:3px 8px;border-radius:4px;font-weight:700;font-size:9px;display:inline-block; }
.ratio-badge { background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:3px 5px;border-radius:4px;font-weight:700;font-size:10px;display:inline-block; }
.page-header { background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460);color:#fff;padding:20px;border-radius:12px;margin-bottom:20px;border:1px solid rgba(0,200,255,.2); }
.filter-section { background:linear-gradient(135deg,#0f3460,#1a1a2e);padding:20px;border-radius:12px;margin-bottom:20px;border:1px solid rgba(0,200,255,.15); }
.filter-section label { color:#e0e0e0!important;font-weight:600;margin-bottom:6px;font-size:12px; }
.filter-section .form-control { border:1px solid rgba(0,200,255,.3);background:rgba(255,255,255,.08);color:#e0e0e0;font-size:12px; }
.filter-section .form-control option { background:#16213e;color:#e0e0e0; }
.stats-box { background:#132d39;padding:12px;border-radius:10px;text-align:center;border-left:4px solid #3498db;margin-bottom:12px; }
.stats-box small { display:block;color:#aaa;font-size:10px;text-transform:uppercase; }
.stats-box strong { display:block;font-size:1.3rem;font-weight:700;margin-top:3px;color:#fff; }
.bias-box { border-radius:12px;padding:18px 22px;margin-bottom:20px;border:1px solid transparent; }
.bias-box.bullish { background:rgba(40,167,69,.1);border-color:rgba(40,167,69,.4); }
.bias-box.bearish { background:rgba(220,53,69,.1);border-color:rgba(220,53,69,.4); }
.bias-box.neutral  { background:rgba(108,117,125,.08);border-color:rgba(108,117,125,.3); }
.bias-badge-bull { background:linear-gradient(135deg,#28a745,#20c997);color:#fff;padding:6px 16px;border-radius:8px;font-weight:700;font-size:14px;display:inline-block; }
.bias-badge-bear { background:linear-gradient(135deg,#dc3545,#c82333);color:#fff;padding:6px 16px;border-radius:8px;font-weight:700;font-size:14px;display:inline-block; }
.bias-badge-neut { background:linear-gradient(135deg,#6c757d,#5a6268);color:#fff;padding:6px 16px;border-radius:8px;font-weight:700;font-size:14px;display:inline-block; }
.trap-box { border-radius:10px;padding:14px 18px;margin-bottom:16px;border:2px solid; }
.trap-box.ce-trap { background:rgba(220,53,69,.08);border-color:#dc3545; }
.trap-box.pe-trap { background:rgba(40,167,69,.08);border-color:#28a745; }
.trap-box.no-trap { background:rgba(108,117,125,.05);border-color:#6c757d; }
.rotation-box { border-radius:10px;padding:14px 18px;margin-bottom:16px;border:2px solid #00d2ff;background:rgba(0,210,255,.05); }
.sector-pill { background:#132d39;border-radius:8px;padding:10px 14px;margin-bottom:10px;border-left:4px solid #dee2e6; }
.sector-pill.bull { border-left-color:#28a745; }
.sector-pill.bear { border-left-color:#dc3545; }
.sector-pill.neut { border-left-color:#ffc107; }
.trade-plan-box { background:#132d39;border:1px solid rgba(0,200,255,.2);border-radius:10px;padding:16px 20px;margin-bottom:20px; }
.trade-plan-box h6 { font-weight:700;font-size:14px;margin-bottom:12px;color:#00d2ff; }
.tp-item { padding:7px 0;border-bottom:1px solid rgba(255,255,255,.08);font-size:12px; }
.tp-item:last-child { border-bottom:none; }
.tp-label { color:#888;font-size:10px;text-transform:uppercase;letter-spacing:.5px; }
.tp-value { font-weight:700;font-size:13px;color:#e0e0e0; }
.value-ce { color:#28a745; }
.value-pe { color:#dc3545; }
.breadth-bar { height:10px;border-radius:5px;background:#2d2d2d;overflow:hidden;display:flex;margin:8px 0; }
.breadth-bull { background:#28a745;transition:width .7s; }
.breadth-bear { background:#dc3545;transition:width .7s; }
.bt-win  { color:#28a745;font-weight:700; }
.bt-loss { color:#dc3545;font-weight:700; }
.metric-card { background:#132d39;border-radius:10px;padding:14px;text-align:center;border-top:3px solid #00d2ff;margin-bottom:12px; }
.metric-card small { display:block;color:#aaa;font-size:10px;text-transform:uppercase;letter-spacing:.5px; }
.metric-card strong { display:block;font-size:1.4rem;font-weight:700;color:#fff;margin-top:4px; }
.tab-btn { background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#aaa;padding:8px 18px;border-radius:8px;font-size:12px;cursor:pointer;transition:all .2s; }
.tab-btn.active { background:linear-gradient(135deg,#0f3460,#1a1a2e);border-color:#00d2ff;color:#00d2ff;font-weight:700; }
.table-responsive { overflow-x:auto; }
.custom--table { min-width:1600px; }
.build-badge { padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700; }
.build-ce { background:rgba(220,53,69,.15);color:#dc3545; }
.build-pe { background:rgba(40,167,69,.15);color:#28a745; }
.build-both { background:rgba(102,126,234,.15);color:#667eea; }
.build-unwind { background:rgba(108,117,125,.15);color:#aaa; }
#equity-chart { width:100%;height:200px; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 style="color:#fff;margin:0;">
                    SENSEX Backtest — Institutional Signal Engine
                    <span style="background:linear-gradient(135deg,#f093fb,#f5576c);color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;margin-left:8px;">SENSEX 30</span>
                </h4>
                <p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,.65);">
                    6-layer engine: Stock OI → Weighted Bias → Trap → Rotation → Futures → Final Signal + Backtest
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('nifty50-sector.index') }}" class="btn btn-outline-light btn-sm">NIFTY Analysis</a>
            </div>
        </div>
    </div>

    {{-- Logic Banner --}}
    <div class="alert" style="background:linear-gradient(135deg,#0f3460,#1a1a2e);color:#e0e0e0;border:1px solid rgba(0,200,255,.2);border-radius:12px;margin-bottom:20px;padding:15px;">
        <div class="row" style="font-size:11px;">
            <div class="col-md-3">
                <strong style="color:#00d2ff;">Priority 1 — Rotation</strong>
                <p style="margin:4px 0 0;color:#aaa;">Intraday shift: PE unwind + CE build = BULLISH. Opposite = BEARISH. Late-session weight boost.</p>
            </div>
            <div class="col-md-3">
                <strong style="color:#f5a623;">Priority 2 — Trap</strong>
                <p style="margin:4px 0 0;color:#aaa;">CE/PE OI divergence &gt;15% signals retail trap. CE_TRAP = DOWN. PE_TRAP = UP.</p>
            </div>
            <div class="col-md-3">
                <strong style="color:#28a745;">Priority 3 — Stock OI Weighted</strong>
                <p style="margin:4px 0 0;color:#aaa;">All 30 SENSEX stocks weighted. Financial sector (38%) overrides if dominant.</p>
            </div>
            <div class="col-md-3">
                <strong style="color:#667eea;">Priority 4 — Futures</strong>
                <p style="margin:4px 0 0;color:#aaa;">FUT price+OI fusion confirms or adjusts final direction and confidence level.</p>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <button class="tab-btn active" onclick="switchTab('analysis', this)">EOD Analysis</button>
        <button class="tab-btn" onclick="switchTab('backtest', this)">Backtest Engine</button>
    </div>

    {{-- ══════════════ TAB: EOD ANALYSIS ══════════════ --}}
    <div id="tab-analysis">

        {{-- Filters --}}
        <div class="filter-section">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label>Analysis Date</label>
                    <input type="date" id="analysis_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>
                <div class="col-md-3">
                    <label>Signal Filter</label>
                    <select id="signal_filter" class="form-control">
                        <option value="">All</option>
                        <option value="BULLISH">BULLISH</option>
                        <option value="BEARISH">BEARISH</option>
                        <option value="NEUTRAL">NEUTRAL</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Sector Filter</label>
                    <select id="sector_filter" class="form-control">
                        <option value="">All Sectors</option>
                        <option value="Financial Services">Financial Services (~38%)</option>
                        <option value="Information Technology">IT (~15%)</option>
                        <option value="Oil Gas & Energy">Oil Gas & Energy (~12%)</option>
                        <option value="FMCG">FMCG (~8%)</option>
                        <option value="Automobile">Automobile (~7%)</option>
                        <option value="Metals & Mining">Metals & Mining (~5%)</option>
                        <option value="Healthcare">Healthcare (~5%)</option>
                        <option value="Capital Goods">Capital Goods (~5%)</option>
                        <option value="Consumer Durables">Consumer Durables (~3%)</option>
                        <option value="Telecommunication">Telecommunication (~2%)</option>
                        <option value="Consumer Services">Consumer Services (~2%)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <div>
                        <button id="btn_analyze" class="btn btn-primary btn-lg" style="min-width:130px;" onclick="runAnalysis()">
                            Analyze
                        </button>
                        <button class="btn btn-outline-secondary btn-lg ml-2" onclick="resetAnalysis()">Reset</button>
                    </div>
                    <small id="analyzed_at" style="color:#888;font-size:10px;display:block;margin-top:4px;"></small>
                </div>
            </div>
        </div>

        {{-- Stats Row --}}
        <div class="row">
            <div class="col-md-2 col-6"><div class="stats-box"><small>Total Stocks</small><strong id="s_total">—</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#28a745;"><small>Bullish</small><strong id="s_bull" style="color:#28a745;">—</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#dc3545;"><small>Bearish</small><strong id="s_bear" style="color:#dc3545;">—</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#ffc107;"><small>Neutral</small><strong id="s_neut" style="color:#ffc107;">—</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#667eea;"><small>Bull Weight</small><strong id="s_bw" style="color:#667eea;">—</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#f5a623;"><small>Bear Weight</small><strong id="s_bew" style="color:#f5a623;">—</strong></div></div>
        </div>

        {{-- Bias + Trap + Rotation Row --}}
        <div class="row" id="signal_panel" style="display:none;">
            <div class="col-md-5">
                <div class="bias-box neutral" id="bias_box">
                    <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">SENSEX Next-Day Bias</div>
                    <div id="bias_badge_wrap"><span class="bias-badge-neut">—</span></div>
                    <div id="bias_reason" style="font-size:11px;color:#aaa;margin-top:8px;"></div>
                    <div style="margin-top:10px;">
                        <div class="breadth-bar">
                            <div class="breadth-bull" id="bb_bull" style="width:0%"></div>
                            <div class="breadth-bear" id="bb_bear" style="width:0%"></div>
                        </div>
                        <div style="font-size:10px;display:flex;justify-content:space-between;">
                            <span style="color:#28a745;" id="bb_bull_lbl">Bull: 0</span>
                            <span style="color:#aaa;" id="bb_neut_lbl">Neut: 0</span>
                            <span style="color:#dc3545;" id="bb_bear_lbl">Bear: 0</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="trap-box no-trap" id="trap_box">
                    <div style="font-size:10px;color:#888;text-transform:uppercase;margin-bottom:6px;">Trap Detection</div>
                    <div id="trap_type" style="font-size:14px;font-weight:700;color:#aaa;">—</div>
                    <div id="trap_desc" style="font-size:11px;margin-top:4px;color:#aaa;"></div>
                </div>
                <div class="rotation-box" id="rotation_box" style="display:none;">
                    <div style="font-size:10px;color:#00d2ff;text-transform:uppercase;margin-bottom:6px;">Rotation Detected</div>
                    <div id="rotation_type" style="font-size:13px;font-weight:700;color:#fff;"></div>
                    <div id="rotation_desc" style="font-size:11px;margin-top:4px;color:#aaa;"></div>
                </div>
            </div>
            <div class="col-md-3">
                <div style="background:#132d39;border-radius:10px;padding:12px;border:1px solid rgba(0,200,255,.15);">
                    <div style="font-size:10px;color:#888;text-transform:uppercase;margin-bottom:8px;">Futures Bias</div>
                    <div id="fut_bias" style="font-size:13px;font-weight:700;color:#fff;">—</div>
                    <div id="fut_detail" style="font-size:11px;color:#aaa;margin-top:4px;"></div>
                    <div id="sensex_oi_detail" style="font-size:11px;color:#888;margin-top:6px;border-top:1px solid rgba(255,255,255,.08);padding-top:6px;"></div>
                </div>
            </div>
        </div>

        {{-- Expiry Warning --}}
        <div id="expiry_warning" style="display:none;background:rgba(255,193,7,.12);border:1px solid #ffc107;border-radius:8px;padding:10px 16px;margin-bottom:12px;font-size:12px;color:#ffc107;">
            ⚠️ <strong>EXPIRY DAY</strong> — Confidence reduced by 15pts. SL auto-widened 1.5×. Gamma moves possible. Trade smaller size.
        </div>

        {{-- Signal Score --}}
        <div style="background:#132d39;border-radius:10px;padding:14px 18px;margin-bottom:16px;border:1px solid rgba(0,200,255,.2);">
            <div style="font-size:10px;color:#888;text-transform:uppercase;margin-bottom:8px;">Signal Score</div>
            <div id="signal_score_val"></div>
            <div id="score_breakdown" style="margin-top:6px;"></div>
        </div>

        {{-- VWAP --}}
        <div id="vwap_box" style="display:none;background:#132d39;border-radius:10px;padding:12px 16px;margin-bottom:16px;border:1px solid rgba(0,200,255,.1);">
            <div style="font-size:10px;color:#888;text-transform:uppercase;margin-bottom:6px;">VWAP Position</div>
            <div id="vwap_detail"></div>
        </div>

        {{-- Strike Level --}}
        <div style="background:#132d39;border-radius:10px;padding:12px 16px;margin-bottom:16px;border:1px solid rgba(0,200,255,.1);">
            <div style="font-size:10px;color:#888;text-transform:uppercase;margin-bottom:8px;">ATM Strike Analysis</div>
            <div class="row" style="font-size:11px;">
                <div class="col-4"><span style="color:#888;">Strike</span><br><strong id="atm_strike_val" style="color:#e0e0e0;">—</strong></div>
                <div class="col-4"><span style="color:#888;">ATM CE OI</span><br><strong id="atm_ce_oi_val" style="color:#dc3545;">—</strong></div>
                <div class="col-4"><span style="color:#888;">ATM PE OI</span><br><strong id="atm_pe_oi_val" style="color:#28a745;">—</strong></div>
                <div class="col-4 mt-2"><span style="color:#888;">ATM PCR</span><br><strong id="atm_pcr_val" style="color:#667eea;">—</strong></div>
                <div class="col-4 mt-2"><span style="color:#888;">Premium Bias</span><br><strong id="atm_prem_bias" style="color:#f5a623;">—</strong></div>
                <div class="col-4 mt-2"><span style="color:#888;">ATM Signal</span><br><strong id="atm_sig_val" style="color:#00d2ff;">—</strong></div>
            </div>
        </div>

        {{-- Trade Plan --}}
        <div class="trade-plan-box" id="trade_plan_box" style="display:none;">
            <h6>Next Day Trade Plan</h6>
            <div class="row">
                <div class="col-md-3">
                    <div class="tp-item"><div class="tp-label">Trade Date</div><div class="tp-value" id="tp_date">—</div></div>
                    <div class="tp-item"><div class="tp-label">Action</div><div id="tp_action">—</div></div>
                </div>
                <div class="col-md-3">
                    <div class="tp-item"><div class="tp-label">Strike</div><div class="tp-value" id="tp_strike">—</div></div>
                    <div class="tp-item"><div class="tp-label">Entry Time</div><div class="tp-value" id="tp_time">—</div></div>
                </div>
                <div class="col-md-3">
                    <div class="tp-item"><div class="tp-label">Stop Loss</div><div class="tp-value" style="color:#dc3545;" id="tp_sl">—</div></div>
                    <div class="tp-item"><div class="tp-label">Target</div><div class="tp-value" style="color:#28a745;" id="tp_target">—</div></div>
                </div>
                <div class="col-md-3">
                    <div class="tp-item" style="border:none;"><div class="tp-label">Entry Trigger</div><div style="font-size:12px;color:#ccc;margin-top:4px;" id="tp_trigger">—</div></div>
                </div>
            </div>
        </div>

        {{-- Sector Panel --}}
        <div id="sector_panel" style="display:none;margin-bottom:20px;">
            <h6 style="color:#00d2ff;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Sector Breakdown</h6>
            <div class="row" id="sector_pills"></div>
        </div>

        {{-- Stock Table --}}
        <div style="position:relative;min-height:300px;">
            <div class="loading-overlay" id="loading_overlay" style="display:none;">
                <div class="spinner"></div>
                <div class="loading-text" id="loading_text">Analyzing SENSEX 30 stocks...</div>
            </div>
            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>#</th><th>Symbol</th><th>Sector</th><th>Wt%</th>
                            <th>FUT Price</th><th>CE OI</th><th>CE%</th>
                            <th>PE OI</th><th>PE%</th><th>Condition</th>
                            <th>Signal</th><th>Action</th><th>Strength</th>
                            <th>Fusion</th><th>P/C Ratio</th><th>Reason</th>
                        </tr>
                    </thead>
                    <tbody id="stock_tbody">
                        <tr><td colspan="16" class="text-center py-5" style="color:#888;">Select a date and click Analyze</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══════════════ TAB: BACKTEST ══════════════ --}}
    <div id="tab-backtest" style="display:none;">
        <div class="filter-section">
            <div class="row align-items-end">
                <div class="col-md-2">
                    <label>From Date</label>
                    <input type="date" id="bt_from" class="form-control" value="{{ date('Y-m-d', strtotime('-30 days')) }}" />
                </div>
                <div class="col-md-2">
                    <label>To Date</label>
                    <input type="date" id="bt_to" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>
                <div class="col-md-2">
                    <label>Stop Loss %</label>
                    <input type="number" id="bt_sl" class="form-control" value="15" min="5" max="50" step="1" />
                </div>
                <div class="col-md-2">
                    <label>Target %</label>
                    <input type="number" id="bt_target" class="form-control" value="30" min="10" max="100" step="5" />
                </div>
                <div class="col-md-2">
                    <label>Min Score</label>
                    <input type="number" id="bt_min_score" class="form-control" value="55" min="0" max="100" step="5" />
                </div>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <button id="btn_backtest" class="btn btn-warning btn-lg w-100" onclick="runBacktest()">Run Backtest</button>
                    <div style="font-size:11px;color:#aaa;margin-top:6px;" id="bt_status"></div>
                </div>
            </div>
        </div>

        {{-- Metrics --}}
        <div id="bt_metrics" style="display:none;">
            <div class="row">
                <div class="col-md-2 col-6"><div class="metric-card"><small>Total Trades</small><strong id="m_total">0</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card" style="border-top-color:#28a745;"><small>Win Rate</small><strong id="m_wr" style="color:#28a745;">0%</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card" style="border-top-color:#28a745;"><small>Avg Win %</small><strong id="m_avgw" style="color:#28a745;">0%</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card" style="border-top-color:#dc3545;"><small>Avg Loss %</small><strong id="m_avgl" style="color:#dc3545;">0%</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card" style="border-top-color:#f5a623;"><small>R:R Ratio</small><strong id="m_rr" style="color:#f5a623;">0</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card" style="border-top-color:#667eea;"><small>Total PnL %</small><strong id="m_total_pnl">0%</strong></div></div>
            </div>
            <div class="row">
                <div class="col-md-2 col-6"><div class="metric-card"><small>Wins</small><strong id="m_wins" style="color:#28a745;">0</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card"><small>Losses</small><strong id="m_losses" style="color:#dc3545;">0</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card"><small>Max Win %</small><strong id="m_maxw" style="color:#28a745;">0%</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card"><small>Max Loss %</small><strong id="m_maxl" style="color:#dc3545;">0%</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card" style="border-top-color:#dc3545;"><small>Max Drawdown</small><strong id="m_dd" style="color:#dc3545;">0%</strong></div></div>
                <div class="col-md-2 col-6"><div class="metric-card"><small>Avg PnL %</small><strong id="m_avg_pnl">0%</strong></div></div>
            </div>

            {{-- Equity Curve --}}
            <div style="background:#132d39;border-radius:10px;padding:16px;margin-bottom:20px;border:1px solid rgba(0,200,255,.15);">
                <div style="color:#00d2ff;font-size:12px;font-weight:700;text-transform:uppercase;margin-bottom:10px;">Equity Curve (Cumulative PnL %)</div>
                <canvas id="equity-chart"></canvas>
            </div>
        </div>

        {{-- Trade Log --}}
        <div id="bt_trades" style="display:none;">
            <h6 style="color:#00d2ff;font-size:12px;font-weight:700;text-transform:uppercase;margin-bottom:12px;">Trade Log</h6>
            <div class="table-responsive">
                <table class="table custom--table" style="min-width:1200px;">
                    <thead>
                        <tr>
                            <th>#</th><th>Signal Date</th><th>Trade Date</th>
                            <th>Direction</th><th>Option</th><th>Confidence</th>
                            <th>Score</th><th>Source</th>
                            <th>Trap</th><th>Rotation</th>
                            <th>Raw Entry</th><th>Slippage</th><th>Entry</th>
                            <th>SL</th><th>Target</th>
                            <th>Exit</th><th>Exit Time</th><th>Exit Reason</th>
                            <th>PnL %</th><th>Result</th>
                        </tr>
                    </thead>
                    <tbody id="bt_tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
'use strict';

// ─────────────────────────────────────────────────────────────────────────────
// State
// ─────────────────────────────────────────────────────────────────────────────
let analysisData = null;
let equityChart  = null;

// ─────────────────────────────────────────────────────────────────────────────
// DOM Helpers
// ─────────────────────────────────────────────────────────────────────────────
function el(id)           { return document.getElementById(id); }
function setText(id, val) { var e = el(id); if (e) e.textContent = val; }
function setHtml(id, val) { var e = el(id); if (e) e.innerHTML  = val; }
function show(id)         { var e = el(id); if (e) e.style.display = ''; }
function hide(id)         { var e = el(id); if (e) e.style.display = 'none'; }

function fmtOI(val) {
    var n = Number(val) || 0;
    if (n >= 1000000) return (n / 1000000).toFixed(2) + 'M';
    if (n >= 1000)    return (n / 1000).toFixed(1) + 'K';
    return n.toString();
}

function fmtPct(val) {
    var n = Number(val) || 0;
    return (n > 0 ? '+' : '') + n.toFixed(2) + '%';
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab Switch
// ─────────────────────────────────────────────────────────────────────────────
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    el('tab-analysis').style.display = tab === 'analysis' ? '' : 'none';
    el('tab-backtest').style.display  = tab === 'backtest'  ? '' : 'none';
}

// ─────────────────────────────────────────────────────────────────────────────
// Loading State
// ─────────────────────────────────────────────────────────────────────────────
function setLoading(show, msg) {
    var overlay = el('loading_overlay');
    var btn     = el('btn_analyze');
    if (show) {
        if (overlay) overlay.style.display = 'flex';
        if (btn)     btn.disabled = true;
        if (msg)     setText('loading_text', msg);
    } else {
        if (overlay) overlay.style.display = 'none';
        if (btn)     btn.disabled = false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// EOD Analysis
// ─────────────────────────────────────────────────────────────────────────────
function runAnalysis() {
    var date = el('analysis_date') ? el('analysis_date').value : '';
    if (!date) { alert('Please select a date'); return; }
    setLoading(true, 'Running 6-layer signal engine on SENSEX 30...');
    fetch('{{ route("sensex-backtest.analyze") }}?date=' + date)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            setLoading(false);
            if (!res.success) { alert('Error: ' + (res.message || 'Unknown error')); return; }
            analysisData = res;
            setText('analyzed_at', 'Analyzed: ' + res.analyzed_at + ' | Prev: ' + res.prev_date);
            renderAnalysis(res);
            applyFilters();
        })
        .catch(function(err) {
            setLoading(false);
            console.error('Analysis fetch error:', err);
            alert('Network error — check console for details.');
        });
}

function renderAnalysis(d) {
    // ── Stats ──
    setText('s_total', d.total_tracked);
    setText('s_bull',  d.breadth.bullish);
    setText('s_bear',  d.breadth.bearish);
    setText('s_neut',  d.breadth.neutral);
    setText('s_bw',    (d.bias.bull_weight || 0) + '%');
    setText('s_bew',   (d.bias.bear_weight || 0) + '%');

    show('signal_panel');

    // ── Bias Box ──
    var biasBox = el('bias_box');
    if (biasBox) {
        biasBox.className = 'bias-box ' + (d.bias.direction === 'BULLISH' ? 'bullish' : d.bias.direction === 'BEARISH' ? 'bearish' : 'neutral');
    }

    var badge = '';
    if (d.bias.direction === 'BULLISH')      badge = '<span class="bias-badge-bull">BULLISH</span>';
    else if (d.bias.direction === 'BEARISH') badge = '<span class="bias-badge-bear">BEARISH</span>';
    else                                      badge = '<span class="bias-badge-neut">' + (d.bias.direction || 'NEUTRAL') + '</span>';
    badge += ' <span style="font-size:11px;font-weight:700;color:#aaa;margin-left:8px;">'
           + (d.bias.strength || '') + ' &nbsp;&middot;&nbsp; '
           + (d.bias.confidence || 0) + '% &nbsp;&middot;&nbsp; via '
           + (d.bias.source || '') + '</span>';
    setHtml('bias_badge_wrap', badge);
    setText('bias_reason', d.bias.reason || '');

    // ── Breadth Bar ──
    var bullEl = el('bb_bull');
    var bearEl = el('bb_bear');
    if (bullEl) bullEl.style.width = (d.breadth.bull_pct || 0) + '%';
    if (bearEl) bearEl.style.width = (d.breadth.bear_pct || 0) + '%';
    setText('bb_bull_lbl', 'Bull: ' + d.breadth.bullish + ' (' + d.breadth.bull_pct + '%)');
    setText('bb_neut_lbl', 'Neut: ' + d.breadth.neutral);
    setText('bb_bear_lbl', 'Bear: ' + d.breadth.bearish + ' (' + d.breadth.bear_pct + '%)');

    // ── Trap ──
    var trap    = d.trap || {};
    var trapBox = el('trap_box');
    if (trapBox) {
        trapBox.className = 'trap-box ' + (trap.type === 'CE_TRAP' ? 'ce-trap' : trap.type === 'PE_TRAP' ? 'pe-trap' : 'no-trap');
    }
    var trapTypeEl = el('trap_type');
    if (trapTypeEl) {
        trapTypeEl.textContent = trap.type || '—';
        trapTypeEl.style.color = trap.type === 'CE_TRAP' ? '#dc3545' : trap.type === 'PE_TRAP' ? '#28a745' : '#aaa';
    }
    setText('trap_desc', trap.description || '');

    // ── Rotation ──
    var rotBox = el('rotation_box');
    if (d.rotation && d.rotation.detected) {
        if (rotBox) rotBox.style.display = '';
        setText('rotation_type', d.rotation.type || '');
        setText('rotation_desc', d.rotation.description || '');
    } else {
        if (rotBox) rotBox.style.display = 'none';
    }

    // ── Futures ──
    var fb      = d.futures || {};
    var fbColor = (fb.bias || '').indexOf('BULLISH') !== -1 ? '#28a745'
                : (fb.bias || '').indexOf('BEARISH') !== -1 ? '#dc3545' : '#aaa';
    var futBiasEl = el('fut_bias');
    if (futBiasEl) { futBiasEl.textContent = fb.bias || '—'; futBiasEl.style.color = fbColor; }
    setText('fut_detail', fb.premium !== null && fb.premium !== undefined ? 'Premium: ' + fb.premium + ' pts | Type: ' + (fb.price_oi_type || '—') : 'No FUT data');

    var so = d.sensex_oi || {};
    setHtml('sensex_oi_detail',
        'CE OI: ' + fmtOI(so.ce_oi) + ' (' + fmtPct(so.ce_pct) + ')'
        + ' &nbsp;|&nbsp; PE OI: ' + fmtOI(so.pe_oi) + ' (' + fmtPct(so.pe_pct) + ')'
        + ' &nbsp;|&nbsp; PCR: ' + (so.pcr || '—'));

    // ── Expiry Warning ──
    var expEl = el('expiry_warning');
    if (expEl) expEl.style.display = d.is_expiry ? '' : 'none';

    // ── Signal Score ──
    var sc = d.signal_score || {};
    var scoreColor = sc.total >= 70 ? '#28a745' : sc.total >= 55 ? '#f5a623' : '#dc3545';
    setHtml('signal_score_val',
        '<span style="font-size:2rem;font-weight:700;color:' + scoreColor + ';">' + (sc.total || 0) + '</span>'
        + '<span style="font-size:13px;color:#aaa;"> / 100 &nbsp; Grade: <strong>' + (sc.grade || '—') + '</strong></span>');

    var bd = sc.breakdown || {};
    setHtml('score_breakdown',
        '<span style="font-size:10px;color:#888;">'
        + 'Bias: <b>' + (bd.bias || 0) + '</b> &nbsp;'
        + 'Rotation: <b>' + (bd.rotation || 0) + '</b> &nbsp;'
        + 'Trap: <b>' + (bd.trap || 0) + '</b> &nbsp;'
        + 'VWAP: <b>' + (bd.vwap || 0) + '</b> &nbsp;'
        + 'Futures: <b>' + (bd.futures || 0) + '</b> &nbsp;'
        + 'Strike: <b>' + (bd.strike || 0) + '</b>'
        + '</span>');

    // ── VWAP ──
    var vwap   = d.vwap || {};
    var vwapEl = el('vwap_box');
    if (vwapEl) {
        vwapEl.style.display = '';
        var vwapColor = vwap.position === 'ABOVE_VWAP' ? '#28a745'
                      : vwap.position === 'BELOW_VWAP' ? '#dc3545' : '#aaa';
        setHtml('vwap_detail',
            '<span style="color:' + vwapColor + ';font-weight:700;">' + (vwap.position || '—') + '</span>'
            + ' &nbsp;|&nbsp; VWAP: <strong>' + (vwap.vwap || '—') + '</strong>'
            + ' &nbsp;|&nbsp; Price: <strong>' + (vwap.current_price || '—') + '</strong>'
            + ' &nbsp;|&nbsp; Dist: <strong>' + (vwap.pct_from_vwap || '—') + '%</strong>');
    }

    // ── ATM Strike ──
    var sl = d.strike_level || {};
    setText('atm_strike_val',  sl.atm_strike    || '—');
    setText('atm_ce_oi_val',   fmtOI(sl.atm_ce_oi));
    setText('atm_pe_oi_val',   fmtOI(sl.atm_pe_oi));
    setText('atm_pcr_val',     sl.atm_pcr       || '—');
    setText('atm_prem_bias',   sl.premium_bias  || '—');
    setText('atm_sig_val',     sl.atm_oi_signal || '—');

    // ── Trade Plan ──
    var tp = d.trade_plan || {};
    show('trade_plan_box');
    setText('tp_date',    tp.trade_date    || '—');
    setText('tp_strike',  tp.strike        || '—');
    setText('tp_time',    tp.entry_time    || '—');
    setText('tp_sl',      tp.stop_loss     || '—');
    setText('tp_target',  tp.target        || '—');
    setText('tp_trigger', tp.entry_trigger || '—');
    var actClass = tp.option_type === 'CE' ? 'action-buy-ce'
                 : tp.option_type === 'PE' ? 'action-buy-pe' : 'action-wait';
    setHtml('tp_action', '<span class="' + actClass + '">' + (tp.action || 'WAIT') + '</span>');

    // ── Sectors ──
    renderSectors(d.sectors || []);
}

function renderSectors(sectors) {
    show('sector_panel');
    var html = '';
    sectors.forEach(function(s) {
        var cls   = s.signal === 'BULLISH' ? 'bull' : s.signal === 'BEARISH' ? 'bear' : 'neut';
        var badge = s.signal === 'BULLISH' ? '<span class="sentiment-bullish">BULLISH</span>'
                  : s.signal === 'BEARISH' ? '<span class="sentiment-bearish">BEARISH</span>'
                  : '<span class="sentiment-neutral">' + (s.signal || 'N/D') + '</span>';
        html += '<div class="col-md-3 col-sm-6">'
              + '<div class="sector-pill ' + cls + '">'
              + '<div style="font-size:11px;font-weight:700;color:#e0e0e0;">' + s.sector + ' <small style="color:#888;">' + s.weight + '%</small></div>'
              + '<div style="margin-top:4px;">' + badge + '</div>'
              + '<div style="font-size:10px;color:#888;margin-top:3px;">B:' + s.bullish + ' Be:' + s.bearish + ' N:' + s.neutral + ' / ' + s.stocks + '</div>'
              + '</div></div>';
    });
    setHtml('sector_pills', html);
}

function applyFilters() {
    if (!analysisData) return;
    var sig    = el('signal_filter') ? el('signal_filter').value : '';
    var sec    = el('sector_filter') ? el('sector_filter').value : '';
    var stocks = (analysisData.stocks || []).slice();
    if (sig) stocks = stocks.filter(function(s) { return s.signal === sig; });
    if (sec) stocks = stocks.filter(function(s) { return s.sector === sec; });
    renderStockTable(stocks);
}

function renderStockTable(stocks) {
    if (!stocks.length) {
        setHtml('stock_tbody', '<tr><td colspan="16" class="text-center py-4" style="color:#888;">No data for selected filters.</td></tr>');
        return;
    }

    var html = '';
    stocks.forEach(function(r, i) {
        // Condition badge
        var condCls = 'condition-flat';
        if (r.oi_condition) {
            if      (r.oi_condition.indexOf('CE ↑ + PE ↓') !== -1) condCls = 'condition-ce-up-pe-down';
            else if (r.oi_condition.indexOf('CE ↓ + PE ↑') !== -1) condCls = 'condition-ce-down-pe-up';
            else if (r.oi_condition.indexOf('Both ↑')      !== -1) condCls = 'condition-both-up';
            else if (r.oi_condition.indexOf('Both ↓')      !== -1) condCls = 'condition-both-down';
        }

        // Signal badge
        var sentBadge = r.signal === 'BULLISH' ? '<span class="sentiment-bullish">BULLISH</span>'
                      : r.signal === 'BEARISH' ? '<span class="sentiment-bearish">BEARISH</span>'
                      : '<span class="sentiment-neutral">NEUTRAL</span>';

        // Action badge
        var actBadge = r.trade_action === 'BUY CE' ? '<span class="action-buy-ce">BUY CE</span>'
                     : r.trade_action === 'BUY PE' ? '<span class="action-buy-pe">BUY PE</span>'
                     : '<span class="action-wait">WAIT</span>';

        // OI % colour
        var cePctColor = (Number(r.ce_oi_pct) >= 0) ? '#28a745' : '#dc3545';
        var pePctColor = (Number(r.pe_oi_pct) >= 0) ? '#28a745' : '#dc3545';

        // Fusion badge
        var fusionBadge = fusionTypeBadge(r.price_oi_fusion);

        // Strength
        var strBadge = (!r.strength_rank || r.strength_rank === 'Normal')
            ? '<span style="color:#888;font-size:10px;">Normal</span>'
            : '<span style="font-weight:700;font-size:10px;color:' + (r.signal === 'BULLISH' ? '#28a745' : '#dc3545') + ';">' + r.strength_rank + '</span>';

        // FUT price
        var futPriceCell = r.fut_price
            ? '&#8377;' + Number(r.fut_price).toLocaleString('en-IN')
            : '<span style="color:#555;">—</span>';

        html += '<tr>'
              + '<td><strong>' + (i + 1) + '</strong></td>'
              + '<td><strong style="color:#00d2ff;">' + r.symbol + '</strong></td>'
              + '<td><span style="background:rgba(102,126,234,.12);color:#667eea;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700;">' + r.sector + '</span></td>'
              + '<td><small style="color:#888;">' + r.weight + '%</small></td>'
              + '<td>' + futPriceCell + '</td>'
              + '<td><strong>' + fmtOI(r.ce_oi) + '</strong></td>'
              + '<td><strong style="color:' + cePctColor + ';">' + fmtPct(r.ce_oi_pct) + '</strong></td>'
              + '<td><strong>' + fmtOI(r.pe_oi) + '</strong></td>'
              + '<td><strong style="color:' + pePctColor + ';">' + fmtPct(r.pe_oi_pct) + '</strong></td>'
              + '<td><span class="' + condCls + '">' + (r.oi_condition || 'N/A') + '</span></td>'
              + '<td>' + sentBadge + '</td>'
              + '<td>' + actBadge + '</td>'
              + '<td>' + strBadge + '</td>'
              + '<td>' + fusionBadge + '</td>'
              + '<td><span class="ratio-badge">' + (r.pe_ce_ratio || 0) + '</span></td>'
              + '<td><small style="color:#aaa;">' + (r.oi_reason || '—') + '</small></td>'
              + '</tr>';
    });

    setHtml('stock_tbody', html);
}

function fusionTypeBadge(type) {
    var map = {
        'LONG_BUILD':     ['build-pe',     'Long Build'],
        'SHORT_BUILD':    ['build-ce',     'Short Build'],
        'STRONG_BEARISH': ['build-ce',     'Strong Bear'],
        'HEDGE_BULLISH':  ['build-pe',     'Hedge Bull'],
        'BOTH_BUILD_UP':  ['build-both',   'Both↑'],
        'BOTH_BUILD_DOWN':['build-both',   'Both↓'],
        'CE_UNWIND':      ['build-unwind', 'CE Unwind'],
        'PE_UNWIND':      ['build-unwind', 'PE Unwind'],
        'NEUTRAL':        ['build-unwind', 'Neutral'],
    };
    var entry = map[type] || ['build-unwind', type || '—'];
    return '<span class="build-badge ' + entry[0] + '">' + entry[1] + '</span>';
}

function resetAnalysis() {
    analysisData = null;
    ['s_total','s_bull','s_bear','s_neut','s_bw','s_bew'].forEach(function(id) { setText(id, '—'); });
    hide('signal_panel');
    hide('trade_plan_box');
    hide('sector_panel');
    setHtml('stock_tbody', '<tr><td colspan="16" class="text-center py-4" style="color:#888;">Filters reset</td></tr>');
    setText('analyzed_at', '');
}

// ─────────────────────────────────────────────────────────────────────────────
// Backtest
// ─────────────────────────────────────────────────────────────────────────────
function runBacktest() {
    var from     = el('bt_from')      ? el('bt_from').value      : '';
    var to       = el('bt_to')        ? el('bt_to').value        : '';
    var slPct    = el('bt_sl')        ? el('bt_sl').value        : 15;
    var target   = el('bt_target')    ? el('bt_target').value    : 30;
    var minScore = el('bt_min_score') ? el('bt_min_score').value : 55;

    if (!from || !to) { alert('Select a date range'); return; }

    var btn = el('btn_backtest');
    if (btn) { btn.disabled = true; btn.textContent = 'Running...'; }
    setText('bt_status', 'Processing — may take 30–90s for large ranges...');

    var url = '{{ route("sensex-backtest.backtest") }}?from=' + from
            + '&to=' + to
            + '&sl_pct=' + slPct
            + '&target_pct=' + target
            + '&min_score=' + minScore;

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (btn) { btn.disabled = false; btn.textContent = 'Run Backtest'; }
            setText('bt_status', 'Done — ' + (res.signals ? res.signals.length : 0) + ' signal days processed');
            if (!res.success) { alert('Error: ' + (res.message || 'Unknown')); return; }
            renderBacktest(res);
        })
        .catch(function(err) {
            if (btn) { btn.disabled = false; btn.textContent = 'Run Backtest'; }
            console.error('Backtest fetch error:', err);
            setText('bt_status', 'Error — check console');
            alert('Network error — check console for details.');
        });
}

function renderBacktest(data) {
    var m = data.metrics || {};
    show('bt_metrics');
    show('bt_trades');

    // ── Metrics ──
    setText('m_total',     m.total     || 0);
    setText('m_wins',      m.wins      || 0);
    setText('m_losses',    m.losses    || 0);
    setText('m_wr',        (m.win_rate || 0) + '%');
    setText('m_avgw',      '+' + (m.avg_win_pct  || 0) + '%');
    setText('m_avgl',      (m.avg_loss_pct || 0) + '%');
    setText('m_rr',        (m.rr_ratio || 0) + 'x');
    setText('m_maxw',      '+' + (m.max_win_pct  || 0) + '%');
    setText('m_maxl',      (m.max_loss_pct || 0) + '%');
    setText('m_dd',        '-' + (m.max_drawdown || 0) + '%');
    setText('m_avg_pnl',   ((m.avg_pnl_pct || 0) >= 0 ? '+' : '') + (m.avg_pnl_pct || 0) + '%');

    var totalPnl    = m.total_pnl_pct || 0;
    var totalPnlEl  = el('m_total_pnl');
    if (totalPnlEl) {
        totalPnlEl.textContent = (totalPnl >= 0 ? '+' : '') + totalPnl + '%';
        totalPnlEl.style.color = totalPnl >= 0 ? '#28a745' : '#dc3545';
    }

    // ── Equity Curve ──
    renderEquityCurve(m.equity_curve || []);

    // ── Trade Log ──
    var trades = data.trades || [];
    if (!trades.length) {
        setHtml('bt_tbody', '<tr><td colspan="20" class="text-center" style="color:#888;">No trades taken with current filters</td></tr>');
        return;
    }

    var html = '';
    trades.forEach(function(t, i) {
        var dirClass  = t.direction === 'BULLISH' ? 'action-buy-ce' : 'action-buy-pe';
        var pnlClass  = t.pnl_pct >= 0 ? 'bt-win' : 'bt-loss';
        var resClass  = t.result === 'WIN' ? 'bt-win' : 'bt-loss';
        var exitColor = t.exit_reason === 'TARGET' ? '#28a745'
                      : t.exit_reason === 'SL'     ? '#dc3545' : '#888';
        var scoreColor = t.score >= 70 ? '#28a745' : t.score >= 55 ? '#f5a623' : '#dc3545';
        var trapColor  = (!t.trap || t.trap === 'NO_TRAP') ? '#888' : '#f5a623';
        var rotHtml    = t.rotation
            ? '<span style="color:#00d2ff;font-size:10px;">YES</span>'
            : '<span style="color:#555;font-size:10px;">No</span>';

        html += '<tr>'
              + '<td>' + (i + 1) + '</td>'
              + '<td>' + (t.signal_date || '—') + '</td>'
              + '<td>' + (t.trade_date  || '—') + '</td>'
              + '<td><span class="' + dirClass + '">' + (t.direction || '—') + '</span></td>'
              + '<td><strong>' + (t.option_type || '—') + '</strong></td>'
              + '<td><span style="color:#667eea;">' + (t.confidence || 0) + '%</span></td>'
              + '<td style="color:' + scoreColor + ';">' + (t.score || 0) + '</td>'
              + '<td style="font-size:10px;color:#aaa;">' + (t.source || '—') + '</td>'
              + '<td><span style="font-size:10px;color:' + trapColor + ';">' + (t.trap || '—') + '</span></td>'
              + '<td>' + rotHtml + '</td>'
              + '<td>&#8377;' + (t.raw_entry || 0) + '</td>'
              + '<td style="color:#888;font-size:10px;">+&#8377;' + (t.slippage || 0) + '</td>'
              + '<td>&#8377;' + (t.entry_price || 0) + '</td>'
              + '<td style="color:#dc3545;">&#8377;' + (t.sl_price || 0) + '</td>'
              + '<td style="color:#28a745;">&#8377;' + (t.target_price || 0) + '</td>'
              + '<td>&#8377;' + (t.exit_price || 0) + '</td>'
              + '<td style="color:#888;">' + (t.exit_time || '—') + '</td>'
              + '<td style="color:' + exitColor + ';">' + (t.exit_reason || '—') + '</td>'
              + '<td class="' + pnlClass + '">' + (t.pnl_pct >= 0 ? '+' : '') + (t.pnl_pct || 0) + '%</td>'
              + '<td class="' + resClass + '">' + (t.result || '—') + '</td>'
              + '</tr>';
    });

    setHtml('bt_tbody', html);
}

function renderEquityCurve(curve) {
    var ctx = document.getElementById('equity-chart');
    if (!ctx) return;
    if (equityChart) { equityChart.destroy(); equityChart = null; }

    var labels = curve.map(function(c) { return c.date; });
    var values = curve.map(function(c) { return c.cumulative; });
    var ptColors = values.map(function(v) { return v >= 0 ? '#28a745' : '#dc3545'; });

    equityChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Cumulative PnL %',
                data: values,
                borderColor: '#00d2ff',
                backgroundColor: 'rgba(0,210,255,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: ptColors,
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#888', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.05)' } },
                y: { ticks: { color: '#888', font: { size: 10 }, callback: function(v) { return v + '%'; } },
                     grid: { color: 'rgba(255,255,255,.05)' } }
            }
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Event Bindings
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    var sigFilter = el('signal_filter');
    var secFilter = el('sector_filter');
    if (sigFilter) sigFilter.addEventListener('change', applyFilters);
    if (secFilter) secFilter.addEventListener('change', applyFilters);
    // Auto-run analysis on page load
    setTimeout(runAnalysis, 400);
});
</script>
@endpush