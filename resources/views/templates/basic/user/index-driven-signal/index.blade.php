@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');
:root {
    --navy-900:#080d1a; --navy-800:#0c1220; --navy-700:#101829;
    --border:rgba(255,255,255,0.07);
    --cyan:#00d2ff; --emerald:#10b981; --rose:#f43f5e;
    --amber:#f59e0b; --sky:#38bdf8; --violet:#8b5cf6;
    --text-1:rgba(255,255,255,0.92); --text-2:rgba(255,255,255,0.55); --text-3:rgba(255,255,255,0.25);
    --mono:'JetBrains Mono',monospace; --display:'Rajdhani',sans-serif;
}
body { background:var(--navy-900); }

/* ── Page header ───────────────────────────────────────────────── */
.ids-header {
    background: linear-gradient(135deg,#080d1a 0%,#0f1c35 50%,#080d1a 100%);
    border:1px solid var(--border);
    border-bottom:2px solid var(--cyan);
    border-radius:14px; padding:22px 28px; margin-bottom:18px;
    position:relative; overflow:hidden;
}
.ids-header::before {
    content:'BREAKOUT';
    position:absolute; right:28px; top:50%; transform:translateY(-50%);
    font-family:var(--display); font-size:80px; font-weight:700;
    color:rgba(0,210,255,0.04); letter-spacing:8px;
    pointer-events:none; user-select:none;
}
.ids-title { font-family:var(--display); font-size:22px; font-weight:700; color:var(--text-1); margin:0; }
.ids-title span {
    background:rgba(0,210,255,0.12); border:1px solid rgba(0,210,255,0.3);
    color:var(--cyan); font-size:10px; font-weight:700;
    padding:2px 9px; border-radius:4px; margin-left:8px;
    vertical-align:middle; letter-spacing:2px;
}
.ids-sub { font-family:var(--mono); font-size:11px; color:var(--text-2); margin:7px 0 0; }
.lp { display:inline-block; font-family:var(--mono); font-size:10px; font-weight:600; padding:2px 9px; border-radius:4px; margin:3px 2px; }
.lp-cyan  { background:rgba(0,210,255,0.10); border:1px solid rgba(0,210,255,0.22); color:var(--cyan); }
.lp-bull  { background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.25); color:var(--emerald); }
.lp-bear  { background:rgba(244,63,94,0.12);  border:1px solid rgba(244,63,94,0.25);  color:var(--rose); }

/* ── Controls ──────────────────────────────────────────────────── */
.ids-controls {
    background:var(--navy-800); border:1px solid var(--border);
    border-radius:12px; padding:14px 20px; margin-bottom:16px;
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
}
.ctrl-label { font-family:var(--display); font-size:10px; font-weight:700; color:var(--text-3); letter-spacing:1.5px; text-transform:uppercase; }
.ctrl-sep   { width:1px; height:28px; background:var(--border); flex-shrink:0; }
.tf-group   { display:flex; gap:4px; }
.tf-btn {
    font-family:var(--display); font-size:12px; font-weight:700;
    padding:6px 15px; border-radius:7px; border:1px solid var(--border);
    background:transparent; color:var(--text-2); cursor:pointer; transition:.15s;
}
.tf-btn:hover  { border-color:rgba(0,210,255,0.4); color:var(--cyan); }
.tf-btn.active { background:rgba(0,210,255,0.15); border-color:var(--cyan); color:var(--cyan); }
.ids-date {
    background:rgba(255,255,255,0.06); border:1px solid var(--border);
    border-radius:8px; color:var(--text-1); padding:5px 10px;
    font-family:var(--mono); font-size:11px; outline:none;
}
.ids-date::-webkit-calendar-picker-indicator { filter:invert(.55); cursor:pointer; }
.ids-select {
    background:rgba(255,255,255,0.06); border:1px solid var(--border);
    color:var(--text-1); border-radius:8px; padding:5px 10px;
    font-family:var(--display); font-size:12px; font-weight:600;
    cursor:pointer; outline:none; min-width:130px;
}
.ids-select option { background:#0c1220; }
.ids-sym-select {
    background:rgba(255,255,255,0.06); border:1px solid var(--border);
    color:var(--text-1); border-radius:8px; padding:5px 8px;
    font-family:var(--display); font-size:11px; font-weight:600;
    cursor:pointer; outline:none; min-width:150px;
}
.ids-sym-select option { background:#0c1220; }

/* threshold slider */
.thresh-wrap { display:flex; flex-direction:column; gap:2px; }
.thresh-val-row { display:flex; align-items:center; gap:6px; }
.thresh-disp {
    font-family:var(--mono); font-size:14px; font-weight:700; color:var(--cyan);
    min-width:42px; text-align:center;
    background:rgba(0,210,255,0.08); border:1px solid rgba(0,210,255,0.2);
    border-radius:5px; padding:1px 6px;
}
input[type=range].ids-range { accent-color:var(--cyan); width:120px; cursor:pointer; }

.ids-btn {
    background:var(--cyan); color:#000; border:none; border-radius:8px;
    padding:7px 22px; font-family:var(--display); font-size:13px; font-weight:800; cursor:pointer;
}
.ids-btn:hover { background:#22e0ff; }
.ids-reset-btn {
    background:rgba(255,255,255,0.07); color:var(--text-2);
    border:1px solid var(--border); border-radius:8px;
    padding:6px 16px; font-family:var(--display); font-size:12px; font-weight:700; cursor:pointer;
}
.ml-auto { margin-left:auto; }

/* ── Warning ────────────────────────────────────────────────────── */
.ids-warn {
    background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3);
    border-radius:10px; padding:14px 18px; margin-bottom:14px;
    font-family:var(--display); font-size:13px; color:var(--amber); display:none;
}

/* ── Stats ──────────────────────────────────────────────────────── */
.ids-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.stat-box {
    background:var(--navy-800); border:1px solid var(--border);
    border-radius:10px; padding:12px 16px; min-width:110px; flex:1;
}
.stat-box small { display:block; font-family:var(--display); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-3); margin-bottom:4px; }
.stat-box strong { display:block; font-family:var(--mono); font-size:1.15rem; font-weight:700; color:var(--text-1); }
.s-total { border-left:3px solid var(--cyan); }
.s-ce    { border-left:3px solid var(--emerald); }
.s-pe    { border-left:3px solid var(--rose); }
.s-syms  { border-left:3px solid var(--amber); }
.s-inv   { border-left:3px solid var(--violet); }

/* ── Main card ──────────────────────────────────────────────────── */
.ids-card { background:var(--navy-800); border:1px solid var(--border); border-radius:14px; overflow:hidden; margin-bottom:18px; }
.ids-card-hdr {
    padding:14px 20px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px; background:var(--navy-700);
}
.ids-card-title { font-family:var(--display); font-size:14px; font-weight:700; color:var(--text-1); }

/* ── Table ──────────────────────────────────────────────────────── */
.ids-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.ids-table { width:100%; border-collapse:collapse; font-family:var(--mono); min-width:1100px; }
.ids-table thead tr.hdr-grp th {
    padding:9px 10px 5px; text-align:center;
    font-family:var(--display); font-size:9px; font-weight:800;
    letter-spacing:1px; text-transform:uppercase;
    background:rgba(0,0,0,0.4); border-bottom:none; white-space:nowrap;
}
.ids-table thead tr.hdr-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:var(--display); font-size:8px; font-weight:700;
    letter-spacing:.3px; text-transform:uppercase;
    background:rgba(0,0,0,0.3); color:var(--text-3);
    border-bottom:2px solid var(--border); white-space:nowrap;
}
.ids-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid rgba(255,255,255,0.03);
    vertical-align:middle; white-space:nowrap; color:var(--text-2);
}
.ids-table tbody tr:hover { background:rgba(255,255,255,0.04) !important; }
.row-ce   { background:rgba(16,185,129,0.04) !important; }
.row-pe   { background:rgba(244,63,94,0.04)  !important; }
.row-even { background:rgba(255,255,255,0.01); }
.row-odd  { background:rgba(0,0,0,0.10); }

/* group divider */
.group-div td {
    background:linear-gradient(90deg, rgba(0,210,255,0.10), transparent) !important;
    border-top:2px solid rgba(0,210,255,0.2) !important;
    padding:9px 14px !important; text-align:left !important;
    font-family:var(--display); font-size:11px; color:rgba(0,210,255,0.75) !important;
    letter-spacing:.4px; font-weight:700;
}
.group-div.pe-div td {
    background:linear-gradient(90deg, rgba(244,63,94,0.10), transparent) !important;
    border-top-color:rgba(244,63,94,0.25) !important;
    color:rgba(244,63,94,0.75) !important;
}

/* column separators */
.sep-nifty  { border-left:2px solid rgba(0,210,255,0.3) !important; }
.sep-option { border-left:2px solid rgba(245,158,11,0.35) !important; }
.sep-entry  { border-left:2px solid rgba(16,185,129,0.35) !important; }
.hdr-nifty  { color:var(--cyan) !important; }
.hdr-option { color:var(--amber) !important; }
.hdr-entry  { color:var(--emerald) !important; }

/* cell styles */
.c-num  { font-size:9px; color:var(--text-3); }
.c-date { font-size:11px; font-weight:700; color:var(--cyan); }
.c-sym  { font-size:12px; font-weight:800; color:var(--sky); }
.c-val  { font-size:10px; font-weight:700; color:var(--text-1); }
.c-sm   { font-size:9px; color:var(--text-3); }
.up     { color:#34d399; font-weight:700; }
.dn     { color:#fb7185; font-weight:700; }
.neu    { color:var(--text-3); }

.sig-ce {
    display:inline-block; background:rgba(16,185,129,0.2); color:#34d399;
    border:1px solid rgba(16,185,129,0.45); border-radius:6px;
    padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800;
}
.sig-pe {
    display:inline-block; background:rgba(244,63,94,0.2); color:#fb7185;
    border:1px solid rgba(244,63,94,0.45); border-radius:6px;
    padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800;
}

.time-badge {
    display:inline-block; font-family:var(--mono); font-size:10px; font-weight:700;
    background:rgba(0,210,255,0.10); border:1px solid rgba(0,210,255,0.25);
    color:var(--cyan); padding:2px 8px; border-radius:5px;
}
.time-badge.pe-time {
    background:rgba(244,63,94,0.10); border-color:rgba(244,63,94,0.3); color:#fb7185;
}
.time-badge.buy-time {
    background:rgba(245,158,11,0.10); border-color:rgba(245,158,11,0.3); color:var(--amber);
}

/* loading / empty */
.ids-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px; }
.ids-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,0.1); border-top:3px solid var(--cyan); border-radius:50%; animation:idspin 1s linear infinite; }
@keyframes idspin { to { transform:rotate(360deg); } }
.ids-spin-txt { color:var(--text-2); margin-top:12px; font-family:var(--display); font-size:13px; }
.ids-empty { text-align:center; padding:60px 20px; color:var(--text-3); font-family:var(--display); font-size:13px; }
.ids-empty i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }

/* ── Exit P&L card ──────────────────────────────────────────────── */
.pnl-card { background:var(--navy-800); border:1px solid var(--border); border-radius:14px; overflow:hidden; margin-bottom:18px; }
.pnl-card-hdr-ce { border-bottom:1px solid rgba(16,185,129,0.2); background:rgba(16,185,129,0.05); }
.pnl-card-hdr-pe { border-bottom:1px solid rgba(244,63,94,0.2);  background:rgba(244,63,94,0.05); }
.pnl-card-hdr { padding:14px 20px; display:flex; align-items:center; gap:10px; }
.pnl-title-ce { font-family:var(--display); font-size:13px; font-weight:700; color:#34d399; }
.pnl-title-pe { font-family:var(--display); font-size:13px; font-weight:700; color:#fb7185; }

.pnl-table { width:100%; border-collapse:collapse; font-family:var(--mono); min-width:600px; }
.pnl-table thead th {
    padding:9px 12px; text-align:center;
    font-family:var(--display); font-size:9px; font-weight:700; letter-spacing:.5px;
    text-transform:uppercase; background:rgba(0,0,0,0.3); color:var(--text-3);
    border-bottom:2px solid var(--border); white-space:nowrap;
}
.pnl-table tbody td {
    padding:9px 12px; text-align:center; font-size:11px;
    border-bottom:1px solid rgba(255,255,255,0.03);
    vertical-align:middle; color:var(--text-2);
}
.pnl-table tbody tr:hover { background:rgba(255,255,255,0.04) !important; }
.pnl-best  { background:rgba(16,185,129,0.07) !important; }
.pnl-worst { background:rgba(244,63,94,0.07)  !important; }

.pnl-load-btn {
    border:none; border-radius:8px; padding:7px 20px;
    font-family:var(--display); font-size:12px; font-weight:800; cursor:pointer;
    transition:.15s;
}
.pnl-load-btn.ce { background:rgba(16,185,129,0.15); color:#34d399; border:1px solid rgba(16,185,129,0.3); }
.pnl-load-btn.ce:hover { background:rgba(16,185,129,0.28); }
.pnl-load-btn.pe { background:rgba(244,63,94,0.15); color:#fb7185; border:1px solid rgba(244,63,94,0.3); }
.pnl-load-btn.pe:hover { background:rgba(244,63,94,0.28); }

.info-callout {
    background:rgba(0,210,255,0.05); border:1px solid rgba(0,210,255,0.15);
    border-radius:8px; padding:10px 14px; font-family:var(--display); font-size:12px;
    color:var(--text-2); margin-bottom:14px; line-height:1.7;
}
.info-callout strong { color:var(--cyan); }

.best-tag  { background:#22c55e; color:#000; padding:1px 5px; border-radius:3px; font-size:9px; font-weight:800; margin-left:4px; }
.worst-tag { background:#ef4444; color:#fff; padding:1px 5px; border-radius:3px; font-size:9px; font-weight:800; margin-left:4px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── Page header ─────────────────────────────────────────── --}}
    <div class="ids-header">
        <h4 class="ids-title">⚡ Index-Driven Signal Scanner <span>NIFTY → ALL SYMBOLS</span></h4>
        <div class="ids-sub" style="margin-top:8px;">
            <span class="lp lp-cyan">Open: 09:15 candle open &nbsp;→&nbsp; Trigger: High / Low breach</span>
            <span class="lp lp-bull">HIGH ≥ Open + Threshold → CE Signal → BUY CE</span>
            <span class="lp lp-bear">LOW  ≤ Open − Threshold → PE Signal → BUY PE</span>
        </div>
        <div class="ids-sub" style="margin-top:5px; color:var(--text-3);">
            NIFTY FUT candles (cp_fut_ohlc_) &nbsp;·&nbsp; ATM options (cp_option_ohlc_) &nbsp;·&nbsp; Entry = NEXT candle OPEN after signal bar closes &nbsp;·&nbsp; First trigger only per direction
        </div>
    </div>

    {{-- ── Controls ────────────────────────────────────────────── --}}
    <div class="ids-controls">
        <span class="ctrl-label">TF</span>
        <div class="tf-group">
            <button class="tf-btn active" data-tf="15min" onclick="setTf('15min',this)">15 Min</button>
            <button class="tf-btn"        data-tf="30min" onclick="setTf('30min',this)">30 Min</button>
            <button class="tf-btn"        data-tf="1hr"   onclick="setTf('1hr',this)">1 Hour</button>
        </div>
        <div class="ctrl-sep"></div>
        <span class="ctrl-label">FROM</span>
        <input type="date" id="ids-from" class="ids-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
        <span class="ctrl-label">TO</span>
        <input type="date" id="ids-to"   class="ids-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
        <div class="ctrl-sep"></div>
        <span class="ctrl-label">THRESHOLD</span>
        <div class="thresh-wrap">
            <div class="thresh-val-row">
                <span id="ids-thresh-disp" class="thresh-disp">30</span>
                <span style="font-family:var(--display);font-size:9px;color:var(--text-3);">pts</span>
            </div>
            <input type="range" id="ids-thresh" class="ids-range" min="5" max="300" step="5" value="30">
        </div>
        <div class="ctrl-sep"></div>
        <span class="ctrl-label">SIGNAL</span>
        <select id="ids-signal" class="ids-select">
            <option value="BOTH">CE + PE</option>
            <option value="CE">CE Only</option>
            <option value="PE">PE Only</option>
        </select>
        <span class="ctrl-label">SYMBOL</span>
        <select id="ids-sym" class="ids-sym-select" multiple size="1">
            <option value="">Loading…</option>
        </select>
        <button class="ids-btn" onclick="runAnalysis()">⚡ Analyze</button>
        <button class="ids-reset-btn" onclick="resetAll()">↺ Reset</button>
        <div class="ml-auto d-flex align-items-center gap-3">
            <span id="ids-info" style="font-family:var(--mono);font-size:10px;color:var(--text-2);"></span>
        </div>
    </div>

    {{-- ── Warning ──────────────────────────────────────────────── --}}
    <div class="ids-warn" id="ids-warn">⚠ <span id="ids-warn-msg"></span></div>

    {{-- ── Stats ───────────────────────────────────────────────── --}}
    <div class="ids-stats">
        <div class="stat-box s-total"><small>Total Trades</small><strong id="st-total" style="color:var(--cyan);">0</strong></div>
        <div class="stat-box s-ce">   <small>CE Signals</small> <strong id="st-ce"    style="color:var(--emerald);">0</strong></div>
        <div class="stat-box s-pe">   <small>PE Signals</small> <strong id="st-pe"    style="color:var(--rose);">0</strong></div>
        <div class="stat-box s-syms"> <small>Symbols Hit</small><strong id="st-syms"  style="color:var(--amber);">0</strong></div>
        <div class="stat-box s-inv">  <small>Total Inv.</small> <strong id="st-inv"   style="color:var(--violet);font-size:.95rem;">₹0</strong></div>
    </div>

    {{-- ── Signal Table ─────────────────────────────────────────── --}}
    <div class="ids-card">
        <div class="ids-card-hdr">
            <span class="ids-card-title" id="ids-card-title">⚡ Index-Driven Signal Scanner — 15 Min</span>
            <span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);" id="ids-card-info"></span>
        </div>
        <div class="ids-tscroll">
            <table class="ids-table">
                <thead>
                    <tr class="hdr-grp">
                        <th colspan="3">Info</th>
                        <th colspan="4" class="hdr-nifty sep-nifty">&#9650; NIFTY FUT Signal</th>
                        <th colspan="4" class="hdr-option sep-option">&#9670; Option Strike</th>
                        <th colspan="3" class="hdr-entry sep-entry">&#9654; Entry</th>
                    </tr>
                    <tr class="hdr-cols">
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th class="sep-nifty">Signal</th>
                        <th>NIFTY Open<br><span style="font-size:7px;opacity:.5;font-weight:400;">09:15 open</span></th>
                        <th>Trigger Val<br><span style="font-size:7px;opacity:.5;font-weight:400;">high / low</span></th>
                        <th>Trigger Time<br><span style="font-size:7px;opacity:.5;font-weight:400;">signal bar</span></th>
                        <th>Move (pts)<br><span style="font-size:7px;opacity:.5;font-weight:400;">from open</span></th>
                        <th class="sep-option">Strike</th>
                        <th>OI<br><span style="font-size:7px;opacity:.5;font-weight:400;">at trigger</span></th>
                        <th>Expiry</th>
                        <th>Lot Size</th>
                        <th class="sep-entry">Buy Time<br><span style="font-size:7px;opacity:.5;font-weight:400;">next candle open</span></th>
                        <th>Buy ₹<br><span style="font-size:7px;opacity:.5;font-weight:400;">entry price</span></th>
                        <th>Investment</th>
                    </tr>
                </thead>
                <tbody id="ids-tbody">
                    <tr><td colspan="15">
                        <div class="ids-empty">
                            <i class="fas fa-bolt"></i>
                            Select date range, set threshold and click <strong>Analyze</strong>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Exit P&L Section ────────────────────────────────────── --}}
    <div class="ids-card" style="padding:18px;">
        <div class="ids-card-hdr" style="background:transparent;border-bottom:none;padding:0 0 14px;">
            <span class="ids-card-title">&#128200; Exit P&amp;L — Aggregate All-Symbol Exit Scenarios</span>
        </div>

        <div class="info-callout">
            <strong>How this works:</strong> After the breakout signal fires and we buy ATM options across all
            configured symbols at the next candle's OPEN, this table shows the aggregate P&L if you exit
            <strong>all positions simultaneously</strong> at the OPEN of every subsequent candle.
            Load CE and PE exit tables separately.
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
            <button class="pnl-load-btn ce" onclick="loadPnl('CE')">▲ Load CE Exit P&amp;L</button>
            <button class="pnl-load-btn pe" onclick="loadPnl('PE')">▼ Load PE Exit P&amp;L</button>
            <span style="font-family:var(--display);font-size:11px;color:var(--text-3);align-self:center;">
                Run Analyze first, then load exit tables.
            </span>
        </div>

        {{-- CE P&L --}}
        <div id="ce-pnl-wrap" style="display:none;margin-bottom:22px;">
            <div class="pnl-card">
                <div class="pnl-card-hdr pnl-card-hdr-ce">
                    <div class="pnl-card-hdr" style="padding:12px 18px;">
                        <span class="pnl-title-ce">▲ CE Exit P&L &nbsp;<span id="ce-pnl-sub" style="font-size:10px;font-weight:400;color:var(--text-3);"></span></span>
                    </div>
                </div>
                <div style="position:relative;min-height:120px;">
                    <div id="loading-ce" style="display:none;position:absolute;inset:0;background:rgba(8,13,26,.9);display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:0 0 14px 14px;">
                        <div class="ids-spinner" style="border-top-color:#34d399;"></div>
                        <div class="ids-spin-txt" style="color:#34d399;">Computing CE exits…</div>
                    </div>
                    <div class="ids-tscroll">
                        <table class="pnl-table">
                            <thead><tr>
                                <th>Exit Time</th>
                                <th>Total Sell Value</th>
                                <th>Total Investment</th>
                                <th>Profit / Loss</th>
                                <th>ROI %</th>
                                <th>Trades</th>
                            </tr></thead>
                            <tbody id="ce-pnl-body">
                                <tr><td colspan="6" class="text-center" style="padding:24px;color:var(--text-3);font-family:var(--display);">Click "Load CE Exit P&L" above</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- PE P&L --}}
        <div id="pe-pnl-wrap" style="display:none;">
            <div class="pnl-card">
                <div class="pnl-card-hdr pnl-card-hdr-pe">
                    <div class="pnl-card-hdr" style="padding:12px 18px;">
                        <span class="pnl-title-pe">▼ PE Exit P&L &nbsp;<span id="pe-pnl-sub" style="font-size:10px;font-weight:400;color:var(--text-3);"></span></span>
                    </div>
                </div>
                <div style="position:relative;min-height:120px;">
                    <div id="loading-pe" style="display:none;position:absolute;inset:0;background:rgba(8,13,26,.9);display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:0 0 14px 14px;">
                        <div class="ids-spinner" style="border-top-color:#fb7185;"></div>
                        <div class="ids-spin-txt" style="color:#fb7185;">Computing PE exits…</div>
                    </div>
                    <div class="ids-tscroll">
                        <table class="pnl-table">
                            <thead><tr>
                                <th>Exit Time</th>
                                <th>Total Sell Value</th>
                                <th>Total Investment</th>
                                <th>Profit / Loss</th>
                                <th>ROI %</th>
                                <th>Trades</th>
                            </tr></thead>
                            <tbody id="pe-pnl-body">
                                <tr><td colspan="6" class="text-center" style="padding:24px;color:var(--text-3);font-family:var(--display);">Click "Load PE Exit P&L" above</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /.ids-card --}}

</div>
</section>
@endsection

@push('script')
<script>
const ANALYZE_URL = '{{ route("index-driven-signal.analyze") }}';
const SYM_URL     = '{{ route("index-driven-signal.symbols") }}';
const PNL_URL     = '{{ route("index-driven-signal.exit-pnl") }}';
const todayStr    = '{{ now()->toDateString() }}';

let curTf    = '15min';
let symCache = {};
let lastData = [];  // cached results for P&L usage

/* ── Threshold slider ─────────────────────────────────────────── */
document.getElementById('ids-thresh').addEventListener('input', function () {
    document.getElementById('ids-thresh-disp').textContent = this.value;
});

/* ── Init ─────────────────────────────────────────────────────── */
$(document).ready(function () { loadSymbols(); });

/* ── Timeframe switch ─────────────────────────────────────────── */
function setTf(tf, btn) {
    curTf = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    $('#ids-card-title').text('⚡ Index-Driven Signal Scanner — ' + tf.toUpperCase());
    loadSymbols();
}

/* ── Load symbols ─────────────────────────────────────────────── */
function loadSymbols() {
    if (symCache[curTf]) { rebuildSym(symCache[curTf]); return; }
    $.get(SYM_URL, { timeframe: curTf }, function (res) {
        if (res.no_config) { showWarn(res.message || ''); rebuildSym([]); return; }
        hideWarn();
        symCache[curTf] = res.symbols || [];
        rebuildSym(symCache[curTf]);
    });
}

function rebuildSym(syms) {
    const sel  = document.getElementById('ids-sym');
    const prev = Array.from(sel.selectedOptions).map(o => o.value);
    sel.innerHTML = syms.length
        ? syms.map(s => `<option value="${s}"${prev.includes(s) ? ' selected' : ''}>${s}</option>`).join('')
        : '<option value="" disabled>No symbols</option>';
    sel.size = Math.min(3, Math.max(1, syms.length));
}

/* ── Main analysis ────────────────────────────────────────────── */
function runAnalysis() {
    const from  = $('#ids-from').val();
    const to    = $('#ids-to').val();
    const syms  = Array.from(document.getElementById('ids-sym').selectedOptions).map(o => o.value).filter(Boolean);
    const sig   = $('#ids-signal').val();
    const thr   = $('#ids-thresh').val();
    if (!from || !to) { alert('Select both dates'); return; }

    hideWarn(); resetStats();
    lastData = [];
    $('#ce-pnl-wrap, #pe-pnl-wrap').hide();
    $('#ids-tbody').html(`<tr><td colspan="15"><div class="ids-loading"><div class="ids-spinner"></div><div class="ids-spin-txt">Scanning NIFTY candles for breakout signals…</div></div></td></tr>`);

    $.ajax({
        url: ANALYZE_URL, type: 'GET',
        data: { timeframe: curTf, from_date: from, to_date: to, symbols: syms, filter: sig, threshold: thr },
        success(res) {
            if (res.no_config) { showWarn(res.message); emptyTable(); return; }
            if (!res.success || !res.data || !res.data.length) { emptyTable(res.message || 'No signals found.'); return; }
            lastData = res.data;
            renderTable(res.data);
            updateStats(res);
            $('#ids-info').html(`Threshold: <span style="color:var(--cyan)">${res.threshold}pts</span> &nbsp;·&nbsp; Signals: <span style="color:var(--amber)">${res.trigger_count}</span>`);
            $('#ids-card-info').text(res.message);
        },
        error(xhr) { emptyTable('⚠ ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error')); }
    });
}

/* ── Render signal table ──────────────────────────────────────── */
function renderTable(data) {
    let html = '', rowNum = 1, lastGroupKey = null;

    data.forEach(function (r, i) {
        const groupKey = r.date + '|' + r.signal_type + '|' + r.trigger_time;

        if (groupKey !== lastGroupKey) {
            const isCe      = r.signal_type === 'CE';
            const signLabel = isCe ? '📈 CE BREAKOUT' : '📉 PE BREAKOUT';
            const moveSign  = r.nifty_move >= 0 ? '+' : '';
            html += `<tr class="group-div${isCe ? '' : ' pe-div'}">
                <td colspan="15">
                    ${r.date} &nbsp;|&nbsp; ${signLabel}
                    &nbsp;|&nbsp; NIFTY open: <strong>₹${r.nifty_open.toFixed(2)}</strong>
                    &nbsp;→&nbsp; Trigger: <strong>₹${r.nifty_trigger.toFixed(2)}</strong>
                    &nbsp;|&nbsp; Bar: <strong>${r.trigger_time}</strong>
                    &nbsp;→&nbsp; Entry candle: <strong>${r.buy_time}</strong>
                    &nbsp;|&nbsp; Move: <strong>${moveSign}${r.nifty_move.toFixed(2)} pts</strong>
                </td>
            </tr>`;
            lastGroupKey = groupKey;
        }

        const isCe    = r.signal_type === 'CE';
        const sigBadge = isCe ? '<span class="sig-ce">📈 CE</span>' : '<span class="sig-pe">📉 PE</span>';
        const moveCls  = r.nifty_move >= 0 ? 'up' : 'dn';
        const moveSign = r.nifty_move >= 0 ? '+' : '';
        const rowCls   = (isCe ? 'row-ce' : 'row-pe') + ' ' + (i % 2 === 0 ? 'row-even' : 'row-odd');

        html += `<tr class="${rowCls}">
            <td class="c-num">${rowNum++}</td>
            <td class="c-date">${r.date}</td>
            <td class="c-sym">${esc(r.symbol)}</td>
            <td class="sep-nifty">${sigBadge}</td>
            <td class="c-val">₹${r.nifty_open.toFixed(2)}</td>
            <td class="c-val" style="color:${isCe?'#34d399':'#fb7185'}">₹${r.nifty_trigger.toFixed(2)}</td>
            <td><span class="time-badge${isCe?'':' pe-time'}">${r.trigger_time}</span></td>
            <td><span class="${moveCls}">${moveSign}${r.nifty_move.toFixed(2)}</span></td>
            <td class="sep-option c-val" style="color:var(--amber)">₹${nInt(r.strike)}</td>
            <td class="c-sm">${fmtOI(r.strike_oi)}</td>
            <td class="c-sm">${r.expiry_date || '—'}</td>
            <td class="c-sm">${r.lot_size}</td>
            <td class="sep-entry"><span class="time-badge buy-time">${r.buy_time}</span></td>
            <td><strong style="color:#34d399;">₹${r.buy_price.toFixed(2)}</strong></td>
            <td><strong>₹${Number(r.investment).toLocaleString('en-IN', {minimumFractionDigits:2,maximumFractionDigits:2})}</strong></td>
        </tr>`;
    });

    if (!html) emptyTable('No results.');
    else $('#ids-tbody').html(html);
}

/* ── Load P&L ─────────────────────────────────────────────────── */
function loadPnl(type) {
    if (!lastData.length) { alert('Please run Analyze first.'); return; }
    const from = $('#ids-from').val();
    const to   = $('#ids-to').val();
    const thr  = $('#ids-thresh').val();
    const syms = Array.from(document.getElementById('ids-sym').selectedOptions).map(o => o.value).filter(Boolean);

    const wrapId   = `#${type.toLowerCase()}-pnl-wrap`;
    const bodyId   = `#${type.toLowerCase()}-pnl-body`;
    const loadId   = `#loading-${type.toLowerCase()}`;
    const subId    = `#${type.toLowerCase()}-pnl-sub`;

    $(wrapId).show();
    $(loadId).css('display','flex');
    $(subId).text('');

    $.ajax({
        url: PNL_URL, type: 'GET',
        data: { timeframe: curTf, from_date: from, to_date: to, threshold: thr, filter: type, symbols: syms },
        success(res) {
            $(loadId).hide();
            const slots = res[type.toLowerCase()] || [];
            if (!slots.length) {
                $(bodyId).html(`<tr><td colspan="6" class="text-center" style="padding:20px;color:var(--text-3);font-family:var(--display);">No exit data found.</td></tr>`);
                return;
            }
            renderPnl(type.toLowerCase(), slots, bodyId, subId);
        },
        error() {
            $(loadId).hide();
            $(bodyId).html(`<tr><td colspan="6" class="text-center" style="padding:20px;color:#fb7185;font-family:var(--display);">Error loading P&L.</td></tr>`);
        }
    });
}

function renderPnl(type, slots, bodyId, subId) {
    $(subId).text(`(${slots.length} exit slots)`);
    const maxP = Math.max(...slots.map(r => r.profit));
    const minP = Math.min(...slots.map(r => r.profit));
    let html = '';
    slots.forEach(row => {
        const isBest  = row.profit === maxP;
        const isWorst = row.profit === minP && row.profit < 0;
        const rowCls  = isBest ? 'pnl-best' : (isWorst ? 'pnl-worst' : '');
        const plCls   = row.profit >= 0 ? 'up' : 'dn';
        const roiCls  = row.roi    >= 0 ? 'up' : 'dn';
        const plSign  = row.profit >= 0 ? '+' : '';
        const roiSign = row.roi    >= 0 ? '+' : '';
        html += `<tr class="${rowCls}">
            <td>
                <span class="time-badge${type==='pe'?' pe-time':''}">${row.exit_time}</span>
                ${isBest  ? '<span class="best-tag">BEST</span>'  : ''}
                ${isWorst ? '<span class="worst-tag">WORST</span>': ''}
            </td>
            <td><strong style="color:var(--amber)">₹${fmt2(row.sell_total)}</strong></td>
            <td><strong style="color:var(--text-1)">₹${fmt2(row.investment)}</strong></td>
            <td><strong class="${plCls}">${plSign}₹${fmt2(Math.abs(row.profit))}</strong></td>
            <td><strong class="${roiCls}">${roiSign}${Math.abs(row.roi).toFixed(2)}%</strong></td>
            <td class="c-sm">${row.trade_count}</td>
        </tr>`;
    });
    $(bodyId).html(html);
}

/* ── Stats ────────────────────────────────────────────────────── */
function updateStats(res) {
    $('#st-total').text(res.total_records || 0);
    $('#st-ce').text(res.ce_count || 0);
    $('#st-pe').text(res.pe_count || 0);
    $('#st-syms').text(res.symbol_count || 0);
    $('#st-inv').text('₹' + Number(res.total_investment || 0).toLocaleString('en-IN', {maximumFractionDigits:0}));
}
function resetStats() {
    ['st-total','st-ce','st-pe','st-syms'].forEach(id => $('#' + id).text('0'));
    $('#st-inv').text('₹0');
}

/* ── Helpers ──────────────────────────────────────────────────── */
function fmtOI(v) {
    const n = Number(v) || 0;
    if (n >= 1e7) return (n / 1e7).toFixed(2) + 'Cr';
    if (n >= 1e5) return (n / 1e5).toFixed(2) + 'L';
    if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
    return n.toLocaleString('en-IN');
}
function nInt(v) {
    const n = Number(v) || 0;
    if (n >= 1e5) return (n / 1e5).toFixed(2) + 'L';
    return n.toLocaleString('en-IN');
}
function fmt2(v) { return Number(v || 0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function esc(s)  { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function emptyTable(msg) {
    $('#ids-tbody').html(`<tr><td colspan="15"><div class="ids-empty"><i class="fas fa-bolt"></i>${msg || 'Select dates and click Analyze'}</div></td></tr>`);
}
function showWarn(msg) { $('#ids-warn').show(); $('#ids-warn-msg').text(msg || ''); }
function hideWarn()    { $('#ids-warn').hide(); }

function resetAll() {
    $('#ids-from, #ids-to').val(todayStr);
    $('#ids-thresh').val(30);
    $('#ids-thresh-disp').text('30');
    $('#ids-signal').val('BOTH');
    document.getElementById('ids-sym').querySelectorAll('option').forEach(o => o.selected = false);
    resetStats();
    emptyTable();
    $('#ids-info').text('');
    hideWarn();
    lastData = [];
    $('#ce-pnl-wrap, #pe-pnl-wrap').hide();
}
</script>
@endpush