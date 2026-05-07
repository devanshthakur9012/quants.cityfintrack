{{--
    resources/views/{activeTemplate}/user/sensex-intraday/index.blade.php
    SENSEX Intraday 15-Min OI Shift Tracker — v4.1
    Fixes: intervals → 15:15 | column tooltips with logic explanation
--}}
@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
:root {
    --c-bull: #22c55e;
    --c-bear: #ef4444;
    --c-neut: #6b7280;
    --c-ce:   #f97316;
    --c-pe:   #3b82f6;
    --c-acc:  #00d2ff;
    --bg-card:#112031;
    --bg-row: #0f1d2d;
    --border: rgba(0,210,255,.15);
    --muted:  #7a8fa6;
}
.pg-header { background:linear-gradient(135deg,#0f2240,#112031); border:1px solid var(--border); border-radius:14px; padding:20px 24px; margin-bottom:18px; }
.filter-wrap { background:#0d1e30; border:1px solid var(--border); border-radius:12px; padding:16px 20px; margin-bottom:18px; }
.filter-wrap label { color:#cdd5df; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:5px; }
.filter-wrap .form-control { background:rgba(255,255,255,.06); border:1px solid rgba(0,210,255,.2); color:#e2e8f0; font-size:12px; border-radius:8px; }

.sum-card { background:var(--bg-card); border-radius:10px; padding:13px 15px; margin-bottom:12px; border-top:3px solid var(--c-acc); }
.sum-card.ce   { border-top-color:var(--c-ce); }
.sum-card.pe   { border-top-color:var(--c-pe); }
.sum-card.bull { border-top-color:var(--c-bull); }
.sum-card small { display:block; color:var(--muted); font-size:10px; text-transform:uppercase; letter-spacing:.4px; }
.sum-card strong { display:block; font-size:1.3rem; font-weight:700; color:#fff; margin-top:3px; }
.sum-card span.sub { display:block; font-size:10px; color:var(--muted); margin-top:2px; }

.shift-banner { background:linear-gradient(135deg,rgba(0,210,255,.05),rgba(0,0,0,0)); border:1px solid var(--border); border-radius:12px; padding:16px 20px; margin-bottom:18px; display:none; }
.shift-row { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.shift-block { flex:1; min-width:140px; }
.shift-block .lbl { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
.shift-arrow { font-size:22px; color:var(--muted); }

.chart-wrap { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:18px; display:none; }
.chart-wrap .chart-title { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; font-weight:600; }

.tl-wrap { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; overflow:hidden; position:relative; min-height:200px; }
.tl-table { width:100%; border-collapse:collapse; }

/* ── Header with tooltip ──────────────────────────────────────────── */
.tl-table thead th {
    background:#091728;
    color:var(--muted);
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.4px;
    padding:10px 12px;
    text-align:center;
    white-space:nowrap;
    position:sticky;
    top:0;
    z-index:5;
    border-bottom:1px solid var(--border);
    cursor:help;
}
.tl-table thead th:first-child { text-align:left; cursor:default; }

/* Tooltip container */
.th-tip {
    position: relative;
    display: inline-block;
    width: 100%;
}
.th-tip .tip-box {
    visibility: hidden;
    opacity: 0;
    background: #0a1929;
    border: 1px solid var(--c-acc);
    color: #cbd5e1;
    font-size: 10px;
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    border-radius: 8px;
    padding: 10px 13px;
    position: absolute;
    top: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    width: 260px;
    z-index: 9999;
    line-height: 1.6;
    pointer-events: none;
    transition: opacity .18s ease;
    text-align: left;
    white-space: normal;
    box-shadow: 0 8px 32px rgba(0,0,0,.6);
}
/* Arrow */
.th-tip .tip-box::before {
    content:'';
    position:absolute;
    top:-6px;
    left:50%;
    transform:translateX(-50%);
    border:6px solid transparent;
    border-bottom-color: var(--c-acc);
    border-top: none;
}
.th-tip:hover .tip-box {
    visibility: visible;
    opacity: 1;
}
.tip-box .tip-title {
    color: var(--c-acc);
    font-weight: 700;
    font-size: 10px;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.tip-box .tip-formula {
    background: rgba(0,210,255,.08);
    border-left: 2px solid var(--c-acc);
    padding: 4px 7px;
    border-radius: 0 4px 4px 0;
    font-family: monospace;
    font-size: 10px;
    color: #e2e8f0;
    margin: 5px 0;
}
.tip-box .tip-bull { color: var(--c-bull); font-weight:700; }
.tip-box .tip-bear { color: var(--c-bear); font-weight:700; }
.tip-box .tip-ce   { color: var(--c-ce);  font-weight:700; }
.tip-box .tip-pe   { color: var(--c-pe);  font-weight:700; }

.tl-row td {
    padding: 8px 12px;
    font-size: 11px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,.035);
    vertical-align: middle;
}
.tl-row td:first-child { text-align:left; }
.tl-row:last-child td { border-bottom:none; }
.tl-row:hover { background:rgba(0,210,255,.04)!important; }
.tl-bull { background:rgba(34,197,94,.05); }
.tl-bear { background:rgba(239,68,68,.05); }
.tl-neut { background:var(--bg-row); }

.inline-bar { display:flex; align-items:center; gap:4px; justify-content:center; }
.ib-track { flex:1; height:12px; background:#1a2d40; border-radius:4px; overflow:hidden; position:relative; min-width:80px; }
.ib-ce { position:absolute; left:0; top:0; bottom:0; background:var(--c-ce); opacity:.85; }
.ib-pe { position:absolute; right:0; top:0; bottom:0; background:var(--c-pe); opacity:.85; }

.bd { display:inline-block; padding:2px 8px; border-radius:4px; font-size:9px; font-weight:700; }
.bd-bull { background:rgba(34,197,94,.15); color:var(--c-bull); }
.bd-bear { background:rgba(239,68,68,.15); color:var(--c-bear); }
.bd-neut { background:rgba(107,114,128,.15); color:var(--c-neut); }

.chg-pos { color:var(--c-bull); font-weight:700; }
.chg-neg { color:var(--c-bear); font-weight:700; }

.vwap-above { color:var(--c-bull); font-size:10px; font-weight:700; }
.vwap-below { color:var(--c-bear); font-size:10px; font-weight:700; }
.vwap-at    { color:#f59e0b;      font-size:10px; font-weight:700; }
.vwap-unk   { color:#374151;      font-size:10px; }

.oi-tag { font-size:9px; font-weight:700; padding:2px 5px; border-radius:3px; }
.oi-lb  { background:rgba(34,197,94,.15);  color:var(--c-bull); }
.oi-sb  { background:rgba(239,68,68,.15);  color:var(--c-bear); }
.oi-sc  { background:rgba(34,197,94,.1);   color:#86efac; }
.oi-lu  { background:rgba(239,68,68,.1);   color:#fca5a5; }
.oi-nt  { background:rgba(107,114,128,.1); color:#9ca3af; }

.flip-marker { display:inline-block; background:rgba(251,191,36,.15); color:#fbbf24; padding:2px 6px; border-radius:3px; font-size:8px; font-weight:700; margin-left:4px; }

.loading-ov { position:absolute; inset:0; background:rgba(9,23,40,.96); display:flex; flex-direction:column; align-items:center; justify-content:center; z-index:50; border-radius:12px; }
.spin { width:42px; height:42px; border:4px solid #1a2d40; border-top:4px solid var(--c-acc); border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.loading-msg { color:#e2e8f0; margin-top:14px; font-size:13px; font-weight:600; }
.loading-sub { color:var(--muted); font-size:11px; margin-top:4px; }

.expiry-badge { background:rgba(251,191,36,.15); color:#fbbf24; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; }
.table-scroll { overflow-x:auto; }
.tl-table { min-width:1200px; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="pg-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h4 style="color:#fff;margin:0;font-size:18px;">
                    SENSEX — 15-Min OI Shift Tracker
                    <span style="background:linear-gradient(135deg,#f97316,#3b82f6);color:#fff;padding:2px 9px;border-radius:4px;font-size:9px;font-weight:700;margin-left:8px;vertical-align:middle;">CE vs PE</span>
                </h4>
                <p style="margin:5px 0 0;font-size:12px;color:var(--muted);">
                    All metrics derived from <strong style="color:var(--c-acc);">30 SENSEX constituent stocks</strong> — Sensex-weight adjusted.
                    Price via <strong style="color:var(--c-acc);">Put-Call Parity</strong>.
                    <span style="color:var(--c-ce);">Orange = CE pressure</span> ·
                    <span style="color:var(--c-pe);">Blue = PE support</span> ·
                    <span style="color:var(--muted);">Hover column headers for logic</span>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span id="expiry_pill" style="display:none;" class="expiry-badge">⚠ EXPIRY DAY</span>
                <a href="{{ route('sensex-backtest.index') }}" class="btn btn-outline-light btn-sm">EOD Backtest</a>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="filter-wrap">
        <div class="row align-items-end g-3">
            <div class="col-md-3 col-sm-6">
                <label>Date</label>
                <input type="date" id="sel_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-3 col-sm-6">
                <label>View Mode</label>
                <select id="sel_view" class="form-control">
                    <option value="stocks">All-Strike OI (30 stocks weighted)</option>
                    <option value="atm">ATM OI Only (30 stocks weighted)</option>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label>&nbsp;</label>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" style="min-width:110px;" id="btn_load" onclick="loadData()">Load Day</button>
                    <button class="btn btn-outline-secondary" onclick="resetView()">Reset</button>
                </div>
                <small id="ts_label" style="color:var(--muted);font-size:10px;display:block;margin-top:5px;"></small>
            </div>
            <div class="col-md-3 col-sm-6">
                <label>Chart Type</label>
                <select id="sel_chart" class="form-control" onchange="switchChart()">
                    <option value="split">CE% vs PE% Area Split</option>
                    <option value="pcr">PCR (Put-Call Ratio)</option>
                    <option value="chg">OI Change% from Open</option>
                    <option value="synthetic">Synthetic Price Move%</option>
                    <option value="vwap">VWAP vs Synthetic Price</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="row" id="sum_row" style="display:none;">
        <div class="col-6 col-md-2">
            <div class="sum-card">
                <small>Candles</small>
                <strong id="sm_candles">—</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="sum-card ce">
                <small>CE OI Grew</small>
                <strong id="sm_open_ce" style="color:var(--c-ce);">—</strong>
                <span class="sub" id="sm_close_ce_lbl"></span>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="sum-card pe">
                <small>PE OI Grew</small>
                <strong id="sm_open_pe" style="color:var(--c-pe);">—</strong>
                <span class="sub" id="sm_close_pe_lbl"></span>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="sum-card">
                <small>PCR Range</small>
                <strong id="sm_pcr_range" style="color:#a78bfa;">—</strong>
                <span class="sub" id="sm_pcr_close"></span>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="sum-card bull">
                <small>Bull Candles</small>
                <strong id="sm_bull" style="color:var(--c-bull);">—</strong>
                <span class="sub" id="sm_bear_lbl"></span>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="sum-card">
                <small>OI Flips</small>
                <strong id="sm_flips" style="color:#fbbf24;">—</strong>
                <span class="sub" id="sm_flip_detail"></span>
            </div>
        </div>
    </div>

    {{-- Shift banner --}}
    <div class="shift-banner" id="shift_banner">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;font-weight:600;">Day OI Shift — Opening vs Closing</div>
        <div class="shift-row">
            <div class="shift-block">
                <div class="lbl">Opening (09:15)</div>
                <div id="open_bar_wrap">—</div>
            </div>
            <div class="shift-arrow">→</div>
            <div class="shift-block">
                <div class="lbl">Closing (15:15)</div>
                <div id="close_bar_wrap">—</div>
            </div>
            <div class="shift-block" style="min-width:160px;">
                <div class="lbl">OI Shift</div>
                <div id="shift_detail" style="font-size:12px;"></div>
            </div>
            <div class="shift-block" style="min-width:160px;">
                <div class="lbl">Peak PE Add</div>
                <div id="peak_pe_detail" style="font-size:12px;color:var(--c-pe);font-weight:700;"></div>
            </div>
            <div class="shift-block" style="min-width:180px;">
                <div class="lbl">Synthetic Price Move</div>
                <div id="synth_move_detail" style="font-size:12px;font-weight:700;"></div>
            </div>
        </div>
        <div id="flip_row" style="margin-top:10px;display:none;">
            <span style="font-size:10px;color:var(--muted);margin-right:8px;">SIGNAL FLIPS:</span>
            <span id="flip_tags"></span>
        </div>
    </div>

    {{-- Chart --}}
    <div class="chart-wrap" id="chart_wrap">
        <div class="chart-title" id="chart_title">CE% vs PE% Split — 15-Min Candles</div>
        <canvas id="main_chart" style="width:100%;height:240px;"></canvas>
    </div>

    {{-- Timeline Table --}}
    <div class="tl-wrap" id="tl_wrap">
        <div class="loading-ov" id="loading_ov" style="display:none;">
            <div class="spin"></div>
            <div class="loading-msg">Fetching 15-min OI data…</div>
            <div class="loading-sub">Aggregating all 30 SENSEX stocks · 09:15 → 15:15 · Put-Call Parity price</div>
        </div>
        <div class="table-scroll">
            <table class="tl-table">
                <thead>
                    <tr>

                        {{-- Time --}}
                        <th style="min-width:60px;cursor:default;">Time</th>

                        {{-- Signal --}}
                        <th>
                            <div class="th-tip">Signal
                                <div class="tip-box">
                                    <div class="tip-title">📶 Signal</div>
                                    Based on <strong>candle-to-candle OI delta</strong> across all 30 stocks.<br><br>
                                    <span class="tip-bull">BULLISH</span> — PE OI being added (put longs entering) or CE OI unwinding more than PE<br>
                                    <span class="tip-bear">BEARISH</span> — CE OI being added (call shorts entering) or PE OI unwinding more than CE<br><br>
                                    First candle uses vs-prev-day direction since there's no prior candle to delta against.
                                </div>
                            </div>
                        </th>

                        {{-- Delta --}}
                        <th style="min-width:180px;">
                            <div class="th-tip">▲ Candle Delta<br><span style="font-size:8px;opacity:.6;">CE vs PE added</span>
                                <div class="tip-box">
                                    <div class="tip-title">▲ Candle Delta — PRIMARY SIGNAL</div>
                                    OI added or removed <strong>this candle vs the previous one</strong>.
                                    <div class="tip-formula">wDelta_CE = Σ(ΔCE_stock × weight/100)<br>wDelta_PE = Σ(ΔPE_stock × weight/100)</div>
                                    <strong>Bar width and signal are weight-adjusted</strong> — HDFCBANK (14.2%) counts 14× more than ETERNAL (1.2%). Numbers shown are raw OI (K/M) for readability.<br><br>
                                    <span class="tip-ce">CE bar growing</span> = weighted call pressure = <span class="tip-bear">bearish</span><br>
                                    <span class="tip-pe">PE bar growing</span> = weighted put support = <span class="tip-bull">bullish</span>
                                </div>
                            </div>
                        </th>

                        {{-- Cum CE% --}}
                        <th>
                            <div class="th-tip">Cum CE%<br><span style="font-size:8px;opacity:.6;">from open</span>
                                <div class="tip-box">
                                    <div class="tip-title">Cumulative CE OI Change</div>
                                    How much CE open interest has grown since the 09:15 opening candle.
                                    <div class="tip-formula">(CE_OI_now − CE_OI_09:15) / CE_OI_09:15 × 100</div>
                                    Positive = more call OI built up today = resistance forming = <span class="tip-bear">bearish pressure</span><br>
                                    Negative = call OI unwound = shorts covering
                                </div>
                            </div>
                        </th>

                        {{-- Cum PE% --}}
                        <th>
                            <div class="th-tip">Cum PE%<br><span style="font-size:8px;opacity:.6;">from open</span>
                                <div class="tip-box">
                                    <div class="tip-title">Cumulative PE OI Change</div>
                                    How much PE open interest has grown since the 09:15 opening candle.
                                    <div class="tip-formula">(PE_OI_now − PE_OI_09:15) / PE_OI_09:15 × 100</div>
                                    Positive = more put OI built up today = support forming = <span class="tip-bull">bullish pressure</span><br>
                                    Negative = put OI unwound = longs exiting
                                </div>
                            </div>
                        </th>

                        {{-- Day CE% --}}
                        <th>
                            <div class="th-tip">Day CE%<br><span style="font-size:8px;opacity:.6;">vs prev day</span>
                                <div class="tip-box">
                                    <div class="tip-title">CE OI vs Previous Day Close</div>
                                    Compares current CE OI to yesterday's 15:00 closing OI. Same logic as EOD backtest engine.
                                    <div class="tip-formula">(CE_OI_now − CE_OI_prevday) / CE_OI_prevday × 100</div>
                                    Gives broader context — is today building more or less CE than yesterday left?
                                </div>
                            </div>
                        </th>

                        {{-- Day PE% --}}
                        <th>
                            <div class="th-tip">Day PE%<br><span style="font-size:8px;opacity:.6;">vs prev day</span>
                                <div class="tip-box">
                                    <div class="tip-title">PE OI vs Previous Day Close</div>
                                    Compares current PE OI to yesterday's 15:00 closing OI.
                                    <div class="tip-formula">(PE_OI_now − PE_OI_prevday) / PE_OI_prevday × 100</div>
                                    Positive = more put support than yesterday = structurally bullish<br>
                                    Negative = put OI lower than yesterday = less support
                                </div>
                            </div>
                        </th>

                        {{-- PCR --}}
                        <th>
                            <div class="th-tip">PCR
                                <div class="tip-box">
                                    <div class="tip-title">Put-Call Ratio</div>
                                    Ratio of total PE to CE open interest across all 30 stocks.
                                    <div class="tip-formula">PCR = Total_PE_OI / Total_CE_OI</div>
                                    PCR &gt; 1 → more puts than calls = <span class="tip-bull">bullish lean</span> (put writers support market)<br>
                                    PCR &lt; 1 → more calls than puts = <span class="tip-bear">bearish lean</span> (call writers resist rally)<br>
                                    PCR &gt; 1.3 = extreme bullish | PCR &lt; 0.7 = extreme bearish
                                </div>
                            </div>
                        </th>

                        {{-- Wt. Split --}}
                        <th style="min-width:160px;">
                            <div class="th-tip">Wt. Split<br><span style="font-size:8px;opacity:.6;">CE% vs PE%</span>
                                <div class="tip-box">
                                    <div class="tip-title">Weight-Adjusted OI Split</div>
                                    CE% and PE% share of total OI, where each stock is weighted by its Sensex index weight (HDFCBANK 14.2%, RELIANCE 9.2%, etc).
                                    <div class="tip-formula">wCE = Σ(stock_CE_OI × weight/100)<br>CE% = wCE / (wCE + wPE) × 100</div>
                                    <span class="tip-ce">CE% &gt; 55%</span> = heavy call writing = resistance = <span class="tip-bear">bearish</span><br>
                                    <span class="tip-pe">PE% &gt; 55%</span> = heavy put writing = support = <span class="tip-bull">bullish</span>
                                </div>
                            </div>
                        </th>

                        {{-- VWAP --}}
                        <th>
                            <div class="th-tip">VWAP<br><span style="font-size:8px;opacity:.6;">30-stock ATM</span>
                                <div class="tip-box">
                                    <div class="tip-title">Volume-Weighted Avg Price</div>
                                    Running VWAP from 09:15 using <strong>Synthetic Price</strong> as TP and ATM volume (CE+PE) as volume, weighted across 30 stocks.
                                    <div class="tip-formula">VWAP = Σ(S* × wVol) / Σ(wVol)<br>wVol = ATM_vol × weight/100</div>
                                    Price <span class="tip-bull">above VWAP</span> = bullish intraday bias<br>
                                    Price <span class="tip-bear">below VWAP</span> = bearish intraday bias<br>
                                    Dist% shown below arrow = how far price is from VWAP
                                </div>
                            </div>
                        </th>

                        {{-- Synth Price --}}
                        <th>
                            <div class="th-tip">Synth. Price<br><span style="font-size:8px;opacity:.6;">S* = K+C−P</span>
                                <div class="tip-box">
                                    <div class="tip-title">Put-Call Parity Synthetic Price</div>
                                    Implied spot price derived from options using Put-Call Parity theorem. Tracks the actual stock price directionally.
                                    <div class="tip-formula">S* = K + C − P<br>K = ATM strike<br>C = ATM CE premium<br>P = ATM PE premium</div>
                                    Weighted across all 30 stocks by Sensex weight → gives implied index level. Unlike mid-price (C+P)/2 which rises on IV spikes, S* moves with actual price direction.
                                </div>
                            </div>
                        </th>

                        {{-- Move% --}}
                        <th>
                            <div class="th-tip">Move%<br><span style="font-size:8px;opacity:.6;">from open</span>
                                <div class="tip-box">
                                    <div class="tip-title">Synthetic Price Move from Open</div>
                                    How much the weighted synthetic price has moved since the 09:15 opening candle.
                                    <div class="tip-formula">(S*_now − S*_09:15) / S*_09:15 × 100</div>
                                    <span class="tip-bull">Positive</span> = market implied spot risen = bullish<br>
                                    <span class="tip-bear">Negative</span> = market implied spot fallen = bearish<br><br>
                                    This is the options-derived equivalent of futures price move%.
                                </div>
                            </div>
                        </th>

                        {{-- OI Type --}}
                        <th style="min-width:100px;">
                            <div class="th-tip">OI Type
                                <div class="tip-box">
                                    <div class="tip-title">OI Position Type</div>
                                    Combines synthetic price move direction with aggregate OI change from open.
                                    <div class="tip-formula">price ↑ + OI ↑ → LONG BUILD<br>price ↓ + OI ↑ → SHORT BUILD<br>price ↑ + OI ↓ → SHORT COVER<br>price ↓ + OI ↓ → LONG UNWIND</div>
                                    <span class="tip-bull">LONG BUILD</span> = bulls adding = strong bullish<br>
                                    <span class="tip-bear">SHORT BUILD</span> = bears adding = strong bearish<br>
                                    SHORT COVER = bears exiting = weak bullish<br>
                                    LONG UNWIND = bulls exiting = weak bearish
                                </div>
                            </div>
                        </th>

                        {{-- ATM CE% --}}
                        <th>
                            <div class="th-tip">ATM CE%<br><span style="font-size:8px;opacity:.6;">30-stock wt.</span>
                                <div class="tip-box">
                                    <div class="tip-title">ATM CE OI % (30 Stocks)</div>
                                    CE share of total OI at the <strong>ATM strike only</strong> (strike_position = ATM), weight-adjusted across 30 stocks.
                                    <div class="tip-formula">ATM CE% = wATM_CE / (wATM_CE + wATM_PE) × 100</div>
                                    ATM strike is frozen at 09:15 FUT close for the entire day.<br>
                                    <span class="tip-ce">High ATM CE%</span> = call writers dominating ATM = strong resistance here<br>
                                    More sensitive than all-strike split — ATM is where smart money is most active.
                                </div>
                            </div>
                        </th>

                        {{-- ATM PE% --}}
                        <th>
                            <div class="th-tip">ATM PE%<br><span style="font-size:8px;opacity:.6;">30-stock wt.</span>
                                <div class="tip-box">
                                    <div class="tip-title">ATM PE OI % (30 Stocks)</div>
                                    PE share of total OI at the <strong>ATM strike only</strong>, weight-adjusted across 30 stocks.
                                    <div class="tip-formula">ATM PE% = 100 − ATM CE%</div>
                                    <span class="tip-pe">High ATM PE%</span> = put writers dominating ATM = strong support here<br>
                                    When ATM PE% rises intraday = smart money buying puts = bearish hedge OR bullish put selling depending on context.
                                </div>
                            </div>
                        </th>

                        {{-- ATM PCR --}}
                        <th>
                            <div class="th-tip">ATM PCR
                                <div class="tip-box">
                                    <div class="tip-title">ATM Put-Call Ratio (30 Stocks)</div>
                                    PCR computed only for ATM strikes, weighted across 30 stocks.
                                    <div class="tip-formula">ATM PCR = wATM_PE_OI / wATM_CE_OI</div>
                                    More precise than all-strike PCR — focuses on the strike with highest gamma and most directional relevance.<br>
                                    ATM PCR &gt; 1.2 = strong put support at ATM = <span class="tip-bull">bullish</span><br>
                                    ATM PCR &lt; 0.8 = heavy call resistance at ATM = <span class="tip-bear">bearish</span>
                                </div>
                            </div>
                        </th>

                    </tr>
                </thead>
                <tbody id="tl_body">
                    <tr>
                        <td colspan="16" style="text-align:center;padding:50px 20px;color:#374151;">
                            Select a date and click <strong style="color:var(--c-acc);">Load Day</strong>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
'use strict';

let _data  = null;
let _chart = null;

const gid = id => document.getElementById(id);
const tx  = (id, v) => { const e = gid(id); if (e) e.textContent = v; };
const ht  = (id, v) => { const e = gid(id); if (e) e.innerHTML  = v; };
const sh  = id => { const e = gid(id); if (e) e.style.display = ''; };
const hd  = id => { const e = gid(id); if (e) e.style.display = 'none'; };

/* ── Load ─────────────────────────────────────────────────────────── */
function loadData() {
    const date = gid('sel_date') ? gid('sel_date').value : '';
    if (!date) { alert('Please select a date'); return; }
    gid('loading_ov').style.display = 'flex';
    gid('btn_load').disabled = true;
    _data = null;

    fetch('{{ route("sensex-intraday.data") }}?date=' + date)
        .then(r => r.json())
        .then(res => {
            gid('loading_ov').style.display = 'none';
            gid('btn_load').disabled = false;
            if (!res.success) { alert('Error: ' + (res.message || 'Unknown')); return; }
            _data = res;
            tx('ts_label', 'Loaded ' + res.analyzed_at + '  |  Prev: ' + res.prev_date);
            gid('expiry_pill').style.display = res.is_expiry ? '' : 'none';
            if (!res.timeline || !res.timeline.length) {
                ht('tl_body', '<tr><td colspan="16" style="text-align:center;padding:50px;color:#6b7280;">No data found for this date.</td></tr>');
                return;
            }
            renderSummary(res.summary, res.timeline.length);
            renderShiftBanner(res.summary);
            renderChart();
            renderTable(res.timeline);
        })
        .catch(err => {
            gid('loading_ov').style.display = 'none';
            gid('btn_load').disabled = false;
            console.error(err);
            alert('Network error — check console.');
        });
}

/* ── Summary ──────────────────────────────────────────────────────── */
function renderSummary(s, total) {
    sh('sum_row');
    tx('sm_candles', total);
    tx('sm_open_ce', (s.net_ce_chg >= 0 ? '+' : '') + s.net_ce_chg + '%');
    ht('sm_close_ce_lbl', 'since 09:15<br><span style="color:var(--muted);font-size:9px;">vs prev: ' + (s.day_ce_chg >= 0 ? '+' : '') + s.day_ce_chg + '%</span>');
    tx('sm_open_pe', (s.net_pe_chg >= 0 ? '+' : '') + s.net_pe_chg + '%');
    ht('sm_close_pe_lbl', 'since 09:15<br><span style="color:var(--muted);font-size:9px;">vs prev: ' + (s.day_pe_chg >= 0 ? '+' : '') + s.day_pe_chg + '%</span>');
    tx('sm_pcr_range', s.pcr_min + ' – ' + s.pcr_max);
    ht('sm_pcr_close', 'Close PCR: <strong>' + (s.closing_pcr ?? '—') + '</strong>');
    tx('sm_bull', s.bull_candles + ' bull');
    ht('sm_bear_lbl', '<span style="color:var(--c-bear);">' + s.bear_candles + ' bear</span>  ' + s.neut_candles + ' neut');
    tx('sm_flips', s.total_flips);
    ht('sm_flip_detail', s.total_flips === 0
        ? '<span style="color:#6b7280;font-size:10px;">Consistent all day</span>'
        : s.flips.map(f => '<span style="font-size:10px;color:#fbbf24;">' + f.time + ' ' + f.from.slice(0,4) + '→' + f.to.slice(0,4) + '</span>').join(' '));
}

/* ── Shift banner ─────────────────────────────────────────────────── */
function renderShiftBanner(s) {
    sh('shift_banner');
    ht('open_bar_wrap', '<span style="font-size:13px;font-weight:700;">' + dirBadge(s.opening_signal) + '</span> <span style="color:var(--muted);font-size:11px;">PCR: ' + s.opening_pcr + '</span>');
    ht('close_bar_wrap', '<span style="font-size:13px;font-weight:700;">' + dirBadge(s.closing_signal) + '</span> <span style="color:var(--muted);font-size:11px;">PCR: ' + s.closing_pcr + '</span>');
    const cShift = s.net_ce_chg || 0, pShift = s.net_pe_chg || 0;
    ht('shift_detail',
        '<span style="color:var(--c-ce);font-weight:700;">CE: ' + (cShift >= 0 ? '+' : '') + cShift + '%</span>&nbsp;&nbsp;'
        + '<span style="color:var(--c-pe);font-weight:700;">PE: ' + (pShift >= 0 ? '+' : '') + pShift + '%</span>'
        + '<br><small style="color:var(--muted);">' + (pShift > cShift ? 'PE weight grew — bullish accumulation' : 'CE weight grew — resistance built') + '</small>');
    ht('peak_pe_detail',
        'Most PE added @ ' + (s.max_pe_add_time || '—')
        + '<br><small style="color:var(--muted);">Most CE added @ ' + (s.max_ce_add_time || '—') + '</small>');
    if (s.total_synthetic_move !== null && s.total_synthetic_move !== undefined) {
        const mc = s.total_synthetic_move >= 0 ? 'var(--c-bull)' : 'var(--c-bear)';
        ht('synth_move_detail',
            '<span style="color:' + mc + ';">' + (s.total_synthetic_move >= 0 ? '+' : '') + s.total_synthetic_move + '</span>'
            + '<br><small style="color:var(--muted);">Open: ' + (s.opening_synthetic ?? '—') + ' → Close: ' + (s.closing_synthetic ?? '—') + '</small>');
    } else {
        ht('synth_move_detail', '<span style="color:var(--muted);">No ATM data</span>');
    }
    if (s.total_flips > 0) {
        sh('flip_row');
        ht('flip_tags', s.flips.map(f => '<span class="flip-marker">' + f.time + ': ' + f.from.slice(0,4) + ' → ' + f.to.slice(0,4) + '</span>').join(' '));
    } else {
        hd('flip_row');
    }
}

/* ── Chart ────────────────────────────────────────────────────────── */
function switchChart() { if (_data && _data.timeline && _data.timeline.length) renderChart(); }

function renderChart() {
    sh('chart_wrap');
    if (_chart) { _chart.destroy(); _chart = null; }
    const tl = _data.timeline, mode = gid('sel_view').value, type = gid('sel_chart').value;
    const labels = tl.map(r => r.time);
    let datasets = [], title = '', yExtra = {};

    if (type === 'split') {
        title = 'CE% vs PE% — ' + viewLabel(mode);
        const [ce, pe] = splitSeries(tl, mode);
        datasets = [
            { label:'CE%', data:ce, borderColor:'#f97316', backgroundColor:'rgba(249,115,22,.18)', borderWidth:2, fill:true, tension:.35, pointRadius:3 },
            { label:'PE%', data:pe, borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,.18)',  borderWidth:2, fill:true, tension:.35, pointRadius:3 },
        ];
        yExtra = { min:0, max:100 };
    } else if (type === 'pcr') {
        title = 'PCR — ' + viewLabel(mode);
        const pcrs = tl.map(r => mode === 'atm' ? r.atm_pcr : r.pcr);
        datasets = [{ label:'PCR', data:pcrs, borderColor:'#a78bfa', backgroundColor:'rgba(167,139,250,.12)', borderWidth:2, fill:true, tension:.35, pointRadius:3, pointBackgroundColor: pcrs.map(v => v > 1 ? '#22c55e' : '#ef4444') }];
    } else if (type === 'chg') {
        title = 'Cumulative OI Change% from 09:15 Open';
        datasets = [
            { label:'CE Chg%', data:tl.map(r => r.cum_ce_chg), borderColor:'#f97316', borderWidth:2, tension:.35, pointRadius:3, fill:false },
            { label:'PE Chg%', data:tl.map(r => r.cum_pe_chg), borderColor:'#3b82f6', borderWidth:2, tension:.35, pointRadius:3, fill:false },
        ];
    } else if (type === 'synthetic') {
        title = 'Synthetic Price Move% from Open (S* = K+C−P, 30 stocks weighted)';
        const moves = tl.map(r => r.synthetic_move_pct);
        datasets = [{ label:'Synth Move%', data:moves, borderColor:'#00d2ff', backgroundColor:'rgba(0,210,255,.08)', borderWidth:2, fill:true, tension:.35, pointRadius:3, pointBackgroundColor: moves.map(v => v > 0 ? '#22c55e' : '#ef4444') }];
    } else if (type === 'vwap') {
        title = 'Synthetic Price vs VWAP (30 stocks ATM weighted)';
        datasets = [
            { label:'Synth Price', data:tl.map(r => r.synthetic_price), borderColor:'#00d2ff', borderWidth:2, tension:.35, pointRadius:2, fill:false },
            { label:'VWAP',        data:tl.map(r => r.vwap),            borderColor:'#fbbf24', borderWidth:2, tension:.35, pointRadius:2, fill:false, borderDash:[5,3] },
        ];
    }

    tx('chart_title', title);
    _chart = new Chart(gid('main_chart').getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive:true, maintainAspectRatio:false,
            interaction:{ mode:'index', intersect:false },
            plugins: {
                legend:{ labels:{ color:'#9ca3af', font:{ size:11 } } },
                tooltip:{ callbacks:{ afterBody: (items) => {
                    const r = tl[items[0].dataIndex];
                    return ['─────────────', 'Signal: ' + r.signal, 'VWAP: ' + (r.vwap_position||'—'), 'OI Type: ' + (r.oi_type||'—'), 'PCR: ' + r.pcr];
                }}}
            },
            scales:{
                x:{ ticks:{ color:'#6b7280', font:{ size:10 } }, grid:{ color:'rgba(255,255,255,.04)' } },
                y:{ ticks:{ color:'#6b7280', font:{ size:10 }, callback: v => v + (type==='split'?'%':'') }, grid:{ color:'rgba(255,255,255,.04)' }, ...yExtra }
            }
        }
    });
}

function splitSeries(tl, mode) {
    if (mode === 'atm') return [tl.map(r => r.atm_ce_pct), tl.map(r => r.atm_pe_pct)];
    return [tl.map(r => r.w_ce_pct), tl.map(r => r.w_pe_pct)];
}
function viewLabel(mode) { return mode === 'atm' ? 'ATM OI (30 stocks wt.)' : 'All-Strike OI (30 stocks wt.)'; }

/* ── Table ────────────────────────────────────────────────────────── */
function renderTable(tl) {
    let html = '', prevSig = null;
    tl.forEach((r, idx) => {
        const isFlip = idx > 0 && prevSig !== null && prevSig !== r.signal && r.signal !== 'NEUTRAL' && prevSig !== 'NEUTRAL';
        const rowCls = 'tl-row ' + (r.signal==='BULLISH'?'tl-bull':r.signal==='BEARISH'?'tl-bear':'tl-neut');
        const sigHtml = r.signal==='BULLISH' ? '<span class="bd bd-bull">BULLISH</span>'
                      : r.signal==='BEARISH' ? '<span class="bd bd-bear">BEARISH</span>'
                      : '<span class="bd bd-neut">NEUTRAL</span>';

        const dCe = r.delta_ce||0, dPe = r.delta_pe||0;         // raw OI (K/M display)
        const wdCe = r.w_delta_ce||0, wdPe = r.w_delta_pe||0;  // weighted (signal driver)
        const absDt = Math.abs(dCe) + Math.abs(dPe);
        const wAbsDt = Math.abs(wdCe) + Math.abs(wdPe);
        let deltaHtml;
        if (absDt === 0 && wAbsDt === 0) {
            // No OI change vs previous candle (or no prev-day data available)
            const d1C = r.day_ce_chg>=0?'chg-pos':'chg-neg';
            const d1P = r.day_pe_chg>=0?'chg-pos':'chg-neg';
            deltaHtml = `<span class="${d1C}" style="font-size:9px;">CE: ${r.day_ce_chg>=0?'+':''}${r.day_ce_chg}%</span>
                &nbsp;<span class="${d1P}" style="font-size:9px;">PE: ${r.day_pe_chg>=0?'+':''}${r.day_pe_chg}% vs prev</span>`;
        } else {
            // Bar width driven by WEIGHTED delta (so HDFCBANK matters more than ETERNAL)
            // Numbers shown are raw OI change (K/M format) for easy reading
            const ceBarW = wdCe>0 ? Math.round(Math.abs(wdCe)/wAbsDt*100) : 0;
            const peBarW = wdPe>0 ? Math.round(Math.abs(wdPe)/wAbsDt*100) : 0;
            const fmt = v => { const a=Math.abs(v),s=v>=0?'+':'-'; return s+(a>=1e6?(a/1e6).toFixed(2)+'M':a>=1e3?(a/1e3).toFixed(1)+'K':a); };
            // Colour: positive = pressure colour, negative = relief colour
            const ceCl = dCe>0?'var(--c-ce)':'var(--c-bull)';
            const peCl = dPe>0?'var(--c-pe)':'var(--c-bear)';
            deltaHtml = `<div style="display:flex;align-items:center;gap:4px;">
                <span style="color:${ceCl};font-size:9px;font-weight:700;">CE ${fmt(dCe)}</span>
                <div style="flex:1;height:10px;background:#1a2d40;border-radius:3px;overflow:hidden;position:relative;min-width:60px;" title="Bar width = weight-adjusted delta. HDFCBANK (14.2%) counts more than ETERNAL (1.2%).">
                    <div style="position:absolute;left:0;top:0;bottom:0;width:${ceBarW}%;background:var(--c-ce);opacity:.85;"></div>
                    <div style="position:absolute;right:0;top:0;bottom:0;width:${peBarW}%;background:var(--c-pe);opacity:.85;"></div>
                </div>
                <span style="color:${peCl};font-size:9px;font-weight:700;">PE ${fmt(dPe)}</span>
            </div>`;
        }

        const ccC=(r.cum_ce_chg||0)>=0?'chg-pos':'chg-neg', ccP=(r.cum_pe_chg||0)>=0?'chg-pos':'chg-neg';
        const dcC=(r.day_ce_chg||0)>=0?'chg-pos':'chg-neg', dcP=(r.day_pe_chg||0)>=0?'chg-pos':'chg-neg';
        const wCe=r.w_ce_pct||50, wPe=r.w_pe_pct||50;
        const splitBar = `<div class="inline-bar">
            <span style="color:var(--c-ce);font-weight:700;font-size:10px;min-width:34px;text-align:right;">${wCe}%</span>
            <div class="ib-track"><div class="ib-ce" style="width:${wCe}%;"></div><div class="ib-pe" style="width:${wPe}%;"></div></div>
            <span style="color:var(--c-pe);font-weight:700;font-size:10px;min-width:34px;">${wPe}%</span>
        </div>`;

        const vp=r.vwap_position||'UNKNOWN';
        const vpC=vp==='ABOVE'?'vwap-above':vp==='BELOW'?'vwap-below':vp==='AT'?'vwap-at':'vwap-unk';
        const vpL=vp==='ABOVE'?'↑ Above':vp==='BELOW'?'↓ Below':vp==='AT'?'≈ At':'—';
        const oiMap={LONG_BUILD:'oi-lb',SHORT_BUILD:'oi-sb',SHORT_COVER:'oi-sc',LONG_UNWIND:'oi-lu',NEUTRAL:'oi-nt'};
        const oiCls=oiMap[r.oi_type]||'oi-nt', oiLbl=r.oi_type?r.oi_type.replace(/_/g,' '):'—';
        const movC=(r.synthetic_move_pct||0)>=0?'#22c55e':'#ef4444';
        const synthDisplay=r.synthetic_price!=null?Number(r.synthetic_price).toLocaleString('en-IN',{maximumFractionDigits:2}):'—';

        html += `<tr class="${rowCls}">
            <td><strong style="color:var(--c-acc);font-size:12px;">${r.time}</strong>${isFlip?'<span class="flip-marker">FLIP</span>':''}</td>
            <td>${sigHtml}</td>
            <td>${deltaHtml}</td>
            <td><span class="${ccC}">${(r.cum_ce_chg>=0?'+':'')}${r.cum_ce_chg||0}%</span></td>
            <td><span class="${ccP}">${(r.cum_pe_chg>=0?'+':'')}${r.cum_pe_chg||0}%</span></td>
            <td><span class="${dcC}">${(r.day_ce_chg>=0?'+':'')}${r.day_ce_chg||0}%</span></td>
            <td><span class="${dcP}">${(r.day_pe_chg>=0?'+':'')}${r.day_pe_chg||0}%</span></td>
            <td><span style="color:#a78bfa;font-weight:700;">${r.pcr||'—'}</span></td>
            <td>${splitBar}</td>
            <td>
                <span class="${vpC}">${vpL}</span>
                ${r.vwap_dist_pct!=null?'<br><small style="color:var(--muted);">'+r.vwap_dist_pct+'%</small>':''}
                ${r.vwap!=null?'<br><small style="color:#4b5563;font-size:9px;">'+r.vwap+'</small>':''}
            </td>
            <td><span style="color:#e2e8f0;font-size:11px;">${synthDisplay}</span></td>
            <td><span style="color:${movC};font-weight:700;">${r.synthetic_move_pct!=null?(r.synthetic_move_pct>=0?'+':'')+r.synthetic_move_pct+'%':'—'}</span></td>
            <td><span class="oi-tag ${oiCls}">${oiLbl}</span></td>
            <td><strong style="color:var(--c-ce);">${r.atm_ce_pct||'—'}%</strong></td>
            <td><strong style="color:var(--c-pe);">${r.atm_pe_pct||'—'}%</strong></td>
            <td><span style="color:#a78bfa;">${r.atm_pcr||'—'}</span></td>
        </tr>`;
        prevSig = r.signal;
    });
    ht('tl_body', html || '<tr><td colspan="16" style="text-align:center;padding:40px;color:#6b7280;">No rows built</td></tr>');
}

function dirBadge(dir) {
    if (dir==='BULLISH') return '<span class="bd bd-bull">BULLISH</span>';
    if (dir==='BEARISH') return '<span class="bd bd-bear">BEARISH</span>';
    return '<span class="bd bd-neut">'+(dir||'NEUTRAL')+'</span>';
}

function resetView() {
    _data = null;
    if (_chart) { _chart.destroy(); _chart = null; }
    hd('sum_row'); hd('shift_banner'); hd('chart_wrap');
    gid('expiry_pill').style.display = 'none';
    tx('ts_label', '');
    ht('tl_body', '<tr><td colspan="16" style="text-align:center;padding:50px 20px;color:#374151;">Select a date and click <strong style="color:var(--c-acc);">Load Day</strong></td></tr>');
}

gid('sel_view').addEventListener('change', () => {
    if (_data && _data.timeline && _data.timeline.length) { renderTable(_data.timeline); renderChart(); }
});

document.addEventListener('DOMContentLoaded', () => setTimeout(loadData, 350));
</script>
@endpush