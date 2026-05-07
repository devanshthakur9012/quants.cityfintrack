@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ════════════════════════════════════════════════════════════════
   PIVOT POINT ANALYSIS — Professional Dark Trading Dashboard
   Font: Rajdhani (display) + JetBrains Mono (numbers)
   Theme: Deep navy + amber + emerald accents
════════════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');

:root {
    --navy-900: #0a0f1e;
    --navy-800: #0d1428;
    --navy-700: #111b35;
    --navy-600: #162040;
    --navy-500: #1e2d52;
    --border:   rgba(255,255,255,0.07);
    --amber:    #f59e0b;
    --amber-dim:#b45309;
    --emerald:  #10b981;
    --rose:     #f43f5e;
    --sky:      #38bdf8;
    --purple:   #a78bfa;
    --text-1:   rgba(255,255,255,0.92);
    --text-2:   rgba(255,255,255,0.55);
    --text-3:   rgba(255,255,255,0.25);
    --mono:     'JetBrains Mono', monospace;
    --display:  'Rajdhani', sans-serif;
}

* { box-sizing: border-box; }
body { background: var(--navy-900); }

/* ── Page header ────────────────────────────────────────────── */
.pa-header {
    background: linear-gradient(135deg, #0d1428 0%, #1a2744 50%, #0d1428 100%);
    border: 1px solid var(--border);
    border-bottom: 2px solid var(--amber);
    border-radius: 14px;
    padding: 20px 28px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.pa-header::before {
    content: 'PIVOT';
    position: absolute; right: 28px; top: 50%;
    transform: translateY(-50%);
    font-family: var(--display);
    font-size: 88px; font-weight: 700;
    color: rgba(245,158,11,0.05);
    letter-spacing: 8px;
    pointer-events: none;
    user-select: none;
}
.pa-header-title {
    font-family: var(--display);
    font-size: 22px; font-weight: 700;
    color: var(--text-1);
    letter-spacing: 1px;
    margin: 0;
}
.pa-header-title span {
    color: var(--amber);
    background: rgba(245,158,11,0.12);
    border: 1px solid rgba(245,158,11,0.25);
    padding: 2px 10px; border-radius: 5px;
    font-size: 11px; font-weight: 700;
    margin-left: 8px; vertical-align: middle;
    letter-spacing: 2px;
}
.pa-header-sub {
    font-size: 11px; color: var(--text-2);
    margin: 6px 0 0;
    font-family: var(--mono);
    letter-spacing: 0.5px;
}
.pa-formula-pill {
    display: inline-block;
    background: rgba(245,158,11,0.10);
    border: 1px solid rgba(245,158,11,0.2);
    color: var(--amber);
    font-family: var(--mono);
    font-size: 10px; font-weight: 600;
    padding: 2px 8px; border-radius: 4px;
    margin: 3px 2px;
}
.pa-formula-pill.s1 { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.25); color: var(--emerald); }
.pa-formula-pill.r1 { background: rgba(244,63,94,0.1);  border-color: rgba(244,63,94,0.25);  color: var(--rose); }

/* ── Control bar ────────────────────────────────────────────── */
.pa-controls {
    background: var(--navy-800);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 18px;
    display: flex; align-items: center;
    gap: 12px; flex-wrap: wrap;
}
.ctrl-label {
    font-family: var(--display);
    font-size: 10px; font-weight: 700;
    color: var(--text-3);
    letter-spacing: 1.5px;
    text-transform: uppercase;
}
.ctrl-sep { width: 1px; height: 28px; background: var(--border); flex-shrink: 0; }

/* ── Timeframe switcher ─────────────────────────────────────── */
.tf-group { display: flex; gap: 4px; }
.tf-btn {
    font-family: var(--display);
    font-size: 12px; font-weight: 700;
    padding: 6px 16px; border-radius: 7px;
    border: 1px solid var(--border);
    background: transparent; color: var(--text-2);
    cursor: pointer; transition: all .15s;
    letter-spacing: .5px;
}
.tf-btn:hover  { border-color: rgba(245,158,11,0.4); color: var(--amber); }
.tf-btn.active {
    background: rgba(245,158,11,0.15);
    border-color: var(--amber);
    color: var(--amber);
}

/* ── Instrument tab ─────────────────────────────────────────── */
.inst-group { display: flex; gap: 4px; }
.inst-btn {
    font-family: var(--display);
    font-size: 11px; font-weight: 700;
    padding: 6px 14px; border-radius: 7px;
    border: 1px solid var(--border);
    background: transparent; color: var(--text-2);
    cursor: pointer; transition: all .15s;
    letter-spacing: .5px;
}
.inst-btn:hover { border-color: rgba(56,189,248,0.4); color: var(--sky); }
.inst-btn.active { background: rgba(56,189,248,0.12); border-color: var(--sky); color: var(--sky); }
.inst-btn.active[data-inst="stock"]  { background: rgba(16,185,129,0.12); border-color: var(--emerald); color: var(--emerald); }
.inst-btn.active[data-inst="fut"]    { background: rgba(245,158,11,0.12); border-color: var(--amber);   color: var(--amber);   }
.inst-btn.active[data-inst="option"] { background: rgba(167,139,250,0.12); border-color: var(--purple); color: var(--purple);  }

/* ── Symbol select ──────────────────────────────────────────── */
.pa-sym-select {
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--border);
    color: var(--text-1);
    border-radius: 8px; padding: 6px 12px;
    font-family: var(--display); font-size: 12px; font-weight: 700;
    cursor: pointer; outline: none; min-width: 160px;
}
.pa-sym-select option { background: #0d1428; color: white; }

/* ── Date controls ──────────────────────────────────────────── */
.date-input {
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--border);
    border-radius: 8px; color: var(--text-1);
    padding: 5px 10px;
    font-family: var(--mono); font-size: 11px; font-weight: 600;
    cursor: pointer; outline: none;
}
.date-input::-webkit-calendar-picker-indicator { filter: invert(.6); cursor: pointer; }
.date-nav { background: rgba(255,255,255,0.06); border: 1px solid var(--border); color: var(--text-2); border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; font-weight: 700; transition: .15s; }
.date-nav:hover { background: rgba(245,158,11,0.15); color: var(--amber); }
.today-btn { width: auto; padding: 0 10px; font-size: 10px; font-family: var(--display); font-weight: 700; letter-spacing: 1px; }

/* ── Load btn ───────────────────────────────────────────────── */
.pa-load-btn {
    background: var(--amber);
    color: #000; border: none; border-radius: 8px;
    padding: 7px 20px;
    font-family: var(--display); font-size: 13px; font-weight: 800;
    cursor: pointer; letter-spacing: .5px; transition: .15s;
}
.pa-load-btn:hover { background: #fbbf24; }

/* ── Auto refresh ───────────────────────────────────────────── */
.auto-btn { background: rgba(255,255,255,0.06); color: var(--text-2); border: 1px solid var(--border); border-radius: 8px; padding: 6px 14px; font-family: var(--display); font-size: 11px; font-weight: 700; cursor: pointer; transition: .15s; }
.auto-btn.active { background: rgba(16,185,129,0.15); border-color: var(--emerald); color: var(--emerald); }

/* ── Status badges ──────────────────────────────────────────── */
.badge-live { background: rgba(16,185,129,0.15); color: var(--emerald); border: 1px solid rgba(16,185,129,0.3); border-radius: 10px; font-size: 9px; font-weight: 700; padding: 2px 8px; }
.badge-hist { background: rgba(245,158,11,0.12);  color: var(--amber);   border: 1px solid rgba(245,158,11,0.25);  border-radius: 10px; font-size: 9px; font-weight: 700; padding: 2px 8px; }
.ml-auto { margin-left: auto; }
.last-upd { font-family: var(--mono); font-size: 9px; color: var(--text-3); }

/* ── Main card ──────────────────────────────────────────────── */
.pa-card {
    background: var(--navy-800);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 20px;
}
.pa-card-header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 12px;
    background: var(--navy-700);
}
.pa-card-title {
    font-family: var(--display);
    font-size: 14px; font-weight: 700;
    color: var(--text-1); letter-spacing: .5px;
}
.pa-card-title .inst-tag {
    font-size: 10px; padding: 2px 8px; border-radius: 4px;
    font-weight: 700; letter-spacing: 1px; margin-left: 6px;
}
.inst-tag-stock  { background: rgba(16,185,129,0.15); color: var(--emerald); border: 1px solid rgba(16,185,129,0.3); }
.inst-tag-fut    { background: rgba(245,158,11,0.15);  color: var(--amber);   border: 1px solid rgba(245,158,11,0.3);  }
.inst-tag-option { background: rgba(167,139,250,0.15); color: var(--purple);  border: 1px solid rgba(167,139,250,0.3); }

/* ── Table scroll ───────────────────────────────────────────── */
.pa-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ════════════════════════════════════════════════════════════
   STOCK / FUT TABLE
════════════════════════════════════════════════════════════ */
.pa-table {
    width: 100%; border-collapse: collapse;
    font-family: var(--mono);
}

/* Stock/FUT min-width */
.pa-table.stock-table,
.pa-table.fut-table { min-width: 1200px; }

/* Option table */
.pa-table.option-table { min-width: 2000px; }

/* ── Header rows ────────────────────────────────────────────── */
.pa-table thead tr.hdr-group th {
    padding: 10px 10px 6px;
    text-align: center;
    font-family: var(--display);
    font-size: 10px; font-weight: 800;
    letter-spacing: 1px; text-transform: uppercase;
    background: rgba(0,0,0,0.4);
    border-bottom: none;
    white-space: nowrap;
}
.pa-table thead tr.hdr-cols th {
    padding: 6px 10px 9px;
    text-align: center;
    font-family: var(--display);
    font-size: 9px; font-weight: 700;
    letter-spacing: .3px; text-transform: uppercase;
    background: rgba(0,0,0,0.3);
    color: var(--text-3);
    border-bottom: 2px solid var(--border);
    white-space: nowrap;
}

/* ── Column group colors ────────────────────────────────────── */
.hdr-meta   { color: var(--text-2) !important; }
.hdr-ohlc   { color: var(--sky)    !important; }
.hdr-pivot  { color: var(--amber)  !important; }
.hdr-signal { color: var(--emerald)!important; }
.hdr-ce     { color: var(--emerald)!important; }
.hdr-pe     { color: var(--rose)   !important; }

/* ── Separators ─────────────────────────────────────────────── */
.sep-ohlc   { border-left: 2px solid rgba(56,189,248,0.3)  !important; }
.sep-pivot  { border-left: 2px solid rgba(245,158,11,0.35) !important; }
.sep-signal { border-left: 2px solid rgba(16,185,129,0.35) !important; }
.sep-ce     { border-left: 2px solid rgba(16,185,129,0.3)  !important; }
.sep-pe     { border-left: 2px solid rgba(244,63,94,0.3)   !important; }

/* ── Body cells ─────────────────────────────────────────────── */
.pa-table tbody td {
    padding: 7px 10px;
    text-align: center;
    font-size: 11px;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    vertical-align: middle;
    white-space: nowrap;
    color: var(--text-2);
}
.pa-table tbody tr:hover { background: rgba(255,255,255,0.04) !important; }
.row-even { background: rgba(255,255,255,0.01); }
.row-odd  { background: rgba(0,0,0,0.12); }

/* ── Cell types ─────────────────────────────────────────────── */
.c-num    { font-size: 9px; color: var(--text-3); }
.c-time   { font-size: 12px; font-weight: 700; color: var(--amber); font-family: var(--mono); }
.c-sym    { font-size: 10px; font-weight: 700; color: var(--sky); }
.c-sym small { display: block; font-size: 8px; color: var(--text-3); font-weight: 400; margin-top: 1px; }
.c-o      { color: rgba(255,255,255,.45); font-size: 10px; }
.c-h      { color: #ff9f7f; font-weight: 600; }
.c-l      { color: #7fc9a0; font-weight: 600; }
.c-c      { color: var(--sky); font-weight: 700; }
.c-vol    { font-size: 9px; color: var(--text-3); }

.c-pp     { color: var(--amber); font-weight: 800; font-family: var(--mono); }
.c-r1     { color: #fb7185; font-weight: 800; font-family: var(--mono); }
.c-r2     { color: #f87171; font-size: 9px; font-family: var(--mono); }
.c-s1     { color: #6ee7b7; font-weight: 800; font-family: var(--mono); }
.c-s2     { color: #34d399; font-size: 9px; font-family: var(--mono); }
.c-range  { font-size: 9px; color: var(--text-3); font-family: var(--mono); }

.c-oi { font-size: 9px; color: var(--text-3); font-family: var(--mono); }
.c-fut-price { font-size: 10px; color: var(--amber); font-family: var(--mono); }

/* ── Signal badges ──────────────────────────────────────────── */
.sig-bullish-strong {
    display: inline-block;
    background: rgba(16,185,129,0.22); color: #34d399;
    border: 1px solid rgba(16,185,129,0.45);
    border-radius: 6px; padding: 3px 9px;
    font-family: var(--display); font-size: 10px; font-weight: 800;
    letter-spacing: .3px;
}
.sig-bullish-mod {
    display: inline-block;
    background: rgba(16,185,129,0.12); color: #6ee7b7;
    border: 1px solid rgba(16,185,129,0.3);
    border-radius: 6px; padding: 3px 9px;
    font-family: var(--display); font-size: 10px; font-weight: 700;
}
.sig-bullish-weak {
    display: inline-block;
    background: rgba(16,185,129,0.07); color: #a7f3d0;
    border: 1px solid rgba(16,185,129,0.15);
    border-radius: 6px; padding: 3px 9px;
    font-family: var(--display); font-size: 10px; font-weight: 600;
}
.sig-bearish-strong {
    display: inline-block;
    background: rgba(244,63,94,0.22); color: #fb7185;
    border: 1px solid rgba(244,63,94,0.45);
    border-radius: 6px; padding: 3px 9px;
    font-family: var(--display); font-size: 10px; font-weight: 800;
    letter-spacing: .3px;
}
.sig-bearish-mod {
    display: inline-block;
    background: rgba(244,63,94,0.12); color: #fda4af;
    border: 1px solid rgba(244,63,94,0.3);
    border-radius: 6px; padding: 3px 9px;
    font-family: var(--display); font-size: 10px; font-weight: 700;
}
.sig-bearish-weak {
    display: inline-block;
    background: rgba(244,63,94,0.07); color: #fecdd3;
    border: 1px solid rgba(244,63,94,0.15);
    border-radius: 6px; padding: 3px 9px;
    font-family: var(--display); font-size: 10px; font-weight: 600;
}
.sig-neutral {
    display: inline-block;
    background: rgba(100,116,139,0.15); color: rgba(255,255,255,0.38);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px; padding: 3px 9px;
    font-family: var(--display); font-size: 10px; font-weight: 600;
}

/* ── Match pills ─────────────────────────────────────────────── */
.match-yes { display: inline-block; background: rgba(16,185,129,0.18); color: #34d399; border: 1px solid rgba(16,185,129,0.4); border-radius: 5px; padding: 2px 7px; font-size: 9px; font-weight: 800; }
.match-no  { display: inline-block; background: rgba(255,255,255,0.04); color: var(--text-3); border: 1px solid rgba(255,255,255,0.08); border-radius: 5px; padding: 2px 7px; font-size: 9px; }
.pp-cross  { display: inline-block; background: rgba(245,158,11,0.18); color: var(--amber); border: 1px solid rgba(245,158,11,0.35); border-radius: 5px; padding: 2px 7px; font-size: 9px; font-weight: 800; }

/* ── Strike / ATM badges ─────────────────────────────────────── */
.atm-badge { display: inline-block; background: rgba(245,158,11,0.12); color: var(--amber); border: 1px solid rgba(245,158,11,0.25); border-radius: 4px; padding: 1px 6px; font-size: 9px; font-weight: 700; }
.strike-ce { font-size: 10px; color: var(--emerald); font-weight: 700; }
.strike-pe { font-size: 10px; color: var(--rose);    font-weight: 700; }

/* ── Row highlight (significant signal) ─────────────────────── */
.row-breakout  { background: rgba(16,185,129,0.06) !important; }
.row-breakdown { background: rgba(244,63,94,0.06)  !important; }
.row-neutral   { background: transparent !important; }

/* ── Loading / empty ─────────────────────────────────────────── */
.pa-loading {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 70px 20px;
}
.pa-spinner {
    width: 38px; height: 38px;
    border: 3px solid rgba(255,255,255,0.1);
    border-top: 3px solid var(--amber);
    border-radius: 50%;
    animation: paspin 1s linear infinite;
}
@keyframes paspin { to { transform: rotate(360deg); } }
.pa-loading-text { color: var(--text-2); margin-top: 14px; font-family: var(--display); font-size: 13px; }
.pa-no-data { text-align: center; padding: 60px; color: var(--text-3); font-family: var(--display); font-size: 13px; }
.pa-no-data i { font-size: 2.5rem; opacity: .35; margin-bottom: 12px; display: block; }

/* ── Config warning ──────────────────────────────────────────── */
.pa-config-warn {
    background: rgba(245,158,11,0.1);
    border: 1px solid rgba(245,158,11,0.3);
    border-radius: 10px;
    padding: 18px 22px;
    display: flex; align-items: flex-start; gap: 14px;
    margin-bottom: 16px;
}
.pa-config-warn-icon { font-size: 1.6rem; flex-shrink: 0; }
.pa-config-warn-text { font-family: var(--display); font-size: 13px; color: var(--amber); }
.pa-config-warn-text p { margin: 4px 0 0; font-size: 11px; color: var(--text-2); font-family: var(--mono); }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── PAGE HEADER ─────────────────────────────────────────────────────── --}}
    <div class="pa-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="pa-header-title">
                    &#9651; Pivot Point Analysis
                    <span>PROFESSIONAL</span>
                </h4>
                <div class="pa-header-sub" style="margin-top:8px;">
                    <span class="pa-formula-pill">PP = (H+L+C) ÷ 3</span>
                    <span class="pa-formula-pill s1">S1 = 2×PP − H &nbsp;↑ BUY</span>
                    <span class="pa-formula-pill r1">R1 = 2×PP − L &nbsp;↓ SELL</span>
                    <span class="pa-formula-pill s1">S2 = PP − Range</span>
                    <span class="pa-formula-pill r1">R2 = PP + Range</span>
                </div>
                <div class="pa-header-sub" style="margin-top:6px;">
                    Candle-by-candle pivot levels for&nbsp;
                    <strong style="color:var(--emerald);">Stock EQ</strong> &nbsp;|&nbsp;
                    <strong style="color:var(--amber);">Futures</strong> &nbsp;|&nbsp;
                    <strong style="color:var(--purple);">Options (ATM CE/PE)</strong>
                    &nbsp;&nbsp;·&nbsp;&nbsp; Only configured symbols shown per timeframe
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONTROL BAR ─────────────────────────────────────────────────────── --}}
    <div class="pa-controls">

        {{-- Timeframe --}}
        <span class="ctrl-label">TF</span>
        <div class="tf-group">
            <button class="tf-btn active" data-tf="15min" onclick="setTimeframe('15min',this)">15 Min</button>
            <button class="tf-btn"        data-tf="30min" onclick="setTimeframe('30min',this)">30 Min</button>
            <button class="tf-btn"        data-tf="1hr"   onclick="setTimeframe('1hr',this)">1 Hour</button>
        </div>

        <div class="ctrl-sep"></div>

        {{-- Instrument type --}}
        <span class="ctrl-label">TYPE</span>
        <div class="inst-group">
            <button class="inst-btn active" data-inst="stock"  onclick="setInstrument('stock',this)">&#9679; Stock EQ</button>
            <button class="inst-btn"        data-inst="fut"    onclick="setInstrument('fut',this)">&#9651; Futures</button>
            <button class="inst-btn"        data-inst="option" onclick="setInstrument('option',this)">&#9670; Options</button>
        </div>

        <div class="ctrl-sep"></div>

        {{-- Date --}}
        <span class="ctrl-label">DATE</span>
        <div style="display:flex;align-items:center;gap:5px;">
            <button class="date-nav" onclick="shiftDate(-1)">&#8249;</button>
            <input type="date" id="pa-date" class="date-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="loadData()">
            <button class="date-nav" onclick="shiftDate(1)">&#8250;</button>
            <button class="date-nav today-btn" onclick="goToday()">TODAY</button>
            <span id="pa-date-badge"></span>
        </div>

        <div class="ctrl-sep"></div>

        {{-- Symbol --}}
        <span class="ctrl-label">SYMBOL</span>
        <select id="pa-sym-select" class="pa-sym-select" onchange="loadData()">
            <option value="ALL">— All Symbols —</option>
        </select>

        <button class="pa-load-btn" onclick="loadData()">&#8635; Load</button>

        <button class="auto-btn" id="pa-auto-btn" onclick="toggleAuto()">&#9654; Auto</button>
        <span id="pa-auto-tag" style="font-size:10px;color:var(--text-3);font-family:var(--mono);"></span>

        <div class="ml-auto d-flex align-items-center gap-3">
            <span id="pa-info-text" style="font-size:10px;color:var(--text-3);font-family:var(--mono);"></span>
            <span class="last-upd" id="pa-last-upd"></span>
        </div>
    </div>

    {{-- ── CONFIG WARNING (shown if no config found) ──────────────────────── --}}
    <div class="pa-config-warn" id="pa-config-warn" style="display:none;">
        <span class="pa-config-warn-icon">&#9888;</span>
        <div class="pa-config-warn-text">
            No active Analysis Config found for this timeframe.
            <p id="pa-config-warn-msg">Go to Admin → Analysis Config to create one and assign symbols.</p>
        </div>
    </div>

    {{-- ── TABLE CONTAINER ────────────────────────────────────────────────── --}}
    <div class="pa-card" id="pa-main-card">
        <div class="pa-card-header">
            <span class="pa-card-title" id="pa-card-title">
                &#9651; Pivot Points
                <span class="inst-tag inst-tag-stock" id="pa-inst-tag">STOCK EQ</span>
            </span>
            <span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);" id="pa-candle-info"></span>
        </div>

        {{-- STOCK & FUT TABLE --}}
        <div id="pa-sf-wrap">
            <div class="pa-table-scroll">
                <table class="pa-table stock-table" id="pa-sf-table">
                    <thead>
                        <tr class="hdr-group" id="pa-sf-hdr-group">
                            <th colspan="3" class="hdr-meta">Info</th>
                            <th colspan="5" class="hdr-ohlc  sep-ohlc">OHLC + Volume</th>
                            <th colspan="5" class="hdr-pivot sep-pivot">&#9651; Pivot Levels</th>
                            <th colspan="5" class="hdr-signal sep-signal">&#9680; Signal</th>
                        </tr>
                        <tr class="hdr-cols">
                            <th class="hdr-meta">#</th>
                            <th class="hdr-meta">Time</th>
                            <th class="hdr-meta">Symbol</th>

                            <th class="hdr-ohlc sep-ohlc">Open</th>
                            <th class="hdr-ohlc">High</th>
                            <th class="hdr-ohlc">Low</th>
                            <th class="hdr-ohlc">Close</th>
                            <th class="hdr-ohlc">Volume</th>

                            <th class="hdr-pivot sep-pivot">PP<br><span style="font-size:7px;opacity:.5;font-weight:400;">(H+L+C)/3</span></th>
                            <th class="hdr-pivot" style="color:#6ee7b7 !important;">S1<br><span style="font-size:7px;opacity:.5;font-weight:400;">2PP−H</span></th>
                            <th class="hdr-pivot" style="color:#34d399 !important;">S2<br><span style="font-size:7px;opacity:.5;font-weight:400;">PP−Range</span></th>
                            <th class="hdr-pivot" style="color:#fb7185 !important;">R1<br><span style="font-size:7px;opacity:.5;font-weight:400;">2PP−L</span></th>
                            <th class="hdr-pivot" style="color:#f87171 !important;">R2<br><span style="font-size:7px;opacity:.5;font-weight:400;">PP+Range</span></th>

                            <th class="hdr-signal sep-signal">Signal</th>
                            <th class="hdr-signal">S1 Touch<br><span style="font-size:7px;opacity:.5;font-weight:400;">Low ≤ S1</span></th>
                            <th class="hdr-signal">R1 Touch<br><span style="font-size:7px;opacity:.5;font-weight:400;">High ≥ R1</span></th>
                            <th class="hdr-signal">PP Cross<br><span style="font-size:7px;opacity:.5;font-weight:400;">Open↔Close</span></th>
                            <th class="hdr-signal" id="pa-oi-col-hdr" style="display:none;">OI</th>
                        </tr>
                    </thead>
                    <tbody id="pa-sf-tbody">
                        <tr><td colspan="18">
                            <div class="pa-loading">
                                <div class="pa-spinner"></div>
                                <div class="pa-loading-text">Loading pivot data&hellip;</div>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- OPTION TABLE (CE + PE side by side) --}}
        <div id="pa-opt-wrap" style="display:none;">
            <div class="pa-table-scroll">
                <table class="pa-table option-table" id="pa-opt-table">
                    <thead>
                        <tr class="hdr-group">
                            <th colspan="4" class="hdr-meta">Info</th>
                            <th colspan="8" class="hdr-ce sep-ce">&#128200; CE — ATM Call Option</th>
                            <th colspan="8" class="hdr-pe sep-pe">&#128201; PE — ATM Put Option</th>
                        </tr>
                        <tr class="hdr-cols">
                            <th class="hdr-meta">#</th>
                            <th class="hdr-meta">Time</th>
                            <th class="hdr-meta">Symbol</th>
                            <th class="hdr-meta">ATM<br><span style="font-size:7px;opacity:.5;font-weight:400;">Strike</span></th>

                            {{-- CE --}}
                            <th class="hdr-ce sep-ce">Open</th>
                            <th class="hdr-ce">High</th>
                            <th class="hdr-ce">Low</th>
                            <th class="hdr-ce">Close</th>
                            <th class="hdr-ce" style="color:var(--amber) !important;">PP</th>
                            <th class="hdr-ce" style="color:#6ee7b7 !important;">S1 &#129001;</th>
                            <th class="hdr-ce" style="color:#fb7185 !important;">R1 &#128997;</th>
                            <th class="hdr-ce">Signal</th>

                            {{-- PE --}}
                            <th class="hdr-pe sep-pe">Open</th>
                            <th class="hdr-pe">High</th>
                            <th class="hdr-pe">Low</th>
                            <th class="hdr-pe">Close</th>
                            <th class="hdr-pe" style="color:var(--amber) !important;">PP</th>
                            <th class="hdr-pe" style="color:#6ee7b7 !important;">S1 &#129001;</th>
                            <th class="hdr-pe" style="color:#fb7185 !important;">R1 &#128997;</th>
                            <th class="hdr-pe">Signal</th>
                        </tr>
                    </thead>
                    <tbody id="pa-opt-tbody">
                        <tr><td colspan="20">
                            <div class="pa-loading">
                                <div class="pa-spinner"></div>
                                <div class="pa-loading-text">Loading option pivot data&hellip;</div>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>{{-- /pa-card --}}

</div>
</section>
@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  PIVOT ANALYSIS — UI LOGIC
// ═══════════════════════════════════════════════════════════════

const todayStr    = '{{ now()->toDateString() }}';
let curTimeframe  = '15min';
let curInstrument = 'stock';
let autoTimer     = null;
let cachedSymbols = {}; // { 'stock-15min': [...], ... }

const ROUTES = {
    stock  : '{{ route("pivot-analysis.stock.signals") }}',
    fut    : '{{ route("pivot-analysis.fut.signals") }}',
    option : '{{ route("pivot-analysis.option.signals") }}',
};

const INST_LABELS = { stock: 'STOCK EQ', fut: 'FUTURES', option: 'OPTIONS' };
const INST_TAG_CLS = { stock: 'inst-tag-stock', fut: 'inst-tag-fut', option: 'inst-tag-option' };

$(document).ready(function () { loadData(); });

// ── State setters ─────────────────────────────────────────────

function setTimeframe(tf, btn) {
    curTimeframe = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    rebuildSymDropdown([]);
    loadData();
}

function setInstrument(inst, btn) {
    curInstrument = inst;
    document.querySelectorAll('.inst-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Toggle table visibility
    const isOption = (inst === 'option');
    document.getElementById('pa-sf-wrap').style.display  = isOption ? 'none'  : '';
    document.getElementById('pa-opt-wrap').style.display = isOption ? ''      : 'none';

    // OI column only for FUT
    const oiHdr = document.getElementById('pa-oi-col-hdr');
    if (oiHdr) oiHdr.style.display = (inst === 'fut') ? '' : 'none';

    // Update card title tag
    const tag = document.getElementById('pa-inst-tag');
    tag.textContent = INST_LABELS[inst];
    tag.className = 'inst-tag ' + INST_TAG_CLS[inst];

    // Reload symbols for this combo
    const cacheKey = inst + '-' + curTimeframe;
    if (cachedSymbols[cacheKey] && cachedSymbols[cacheKey].length) {
        rebuildSymDropdown(cachedSymbols[cacheKey]);
    } else {
        rebuildSymDropdown([]);
    }

    loadData();
}

// ── Date controls ─────────────────────────────────────────────

function getDate() { return document.getElementById('pa-date').value; }
function getSym()  { return document.getElementById('pa-sym-select').value; }

function shiftDate(days) {
    const picker = document.getElementById('pa-date');
    const d = new Date(picker.value);
    d.setDate(d.getDate() + days);
    const s = d.toISOString().split('T')[0];
    if (s > todayStr) return;
    picker.value = s;
    loadData();
}
function goToday() { document.getElementById('pa-date').value = todayStr; loadData(); }

function updateDateBadge(isToday) {
    const el = document.getElementById('pa-date-badge');
    el.innerHTML = isToday
        ? '<span class="badge-live">&#9679; Live</span>'
        : '<span class="badge-hist">&#128197; Hist</span>';
}

// ── Symbol dropdown ───────────────────────────────────────────

function rebuildSymDropdown(symbols) {
    const sel  = document.getElementById('pa-sym-select');
    const prev = sel.value;
    sel.innerHTML = '<option value="ALL">— All Symbols —</option>';
    symbols.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

// ── Auto refresh ──────────────────────────────────────────────

function toggleAuto() {
    const btn = document.getElementById('pa-auto-btn');
    const tag = document.getElementById('pa-auto-tag');
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        btn.textContent = '▶ Auto';
        btn.classList.remove('active');
        tag.textContent = '';
    } else {
        autoTimer = setInterval(loadData, 15000);
        btn.textContent = '■ Stop';
        btn.classList.add('active');
        tag.style.color = 'var(--emerald)';
        tag.textContent = '● live';
        loadData();
    }
}

// ── Main loader ───────────────────────────────────────────────

function loadData() {
    const date = getDate();
    const sym  = getSym();

    // Stop auto on historical date
    if (date !== todayStr && autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        document.getElementById('pa-auto-btn').textContent = '▶ Auto';
        document.getElementById('pa-auto-btn').classList.remove('active');
        document.getElementById('pa-auto-tag').textContent = '';
    }

    // Hide config warning
    document.getElementById('pa-config-warn').style.display = 'none';

    // Show loading state
    const isOption = (curInstrument === 'option');
    if (!isOption) {
        $('#pa-sf-tbody').html(loadingHtml(18, 'Fetching pivot data for ' + date + '&hellip;'));
    } else {
        $('#pa-opt-tbody').html(loadingHtml(20, 'Fetching option pivot data for ' + date + '&hellip;'));
    }

    $.ajax({
        url : ROUTES[curInstrument],
        data: { timeframe: curTimeframe, symbol: sym, date: date },
        success(res) {
            updateDateBadge(res.is_today);

            if (res.no_config) {
                document.getElementById('pa-config-warn').style.display = 'flex';
                document.getElementById('pa-config-warn-msg').textContent = res.message || '';
                if (!isOption) $('#pa-sf-tbody').html(noDataHtml(18));
                else           $('#pa-opt-tbody').html(noDataHtml(20));
                return;
            }

            if (res.available_symbols && res.available_symbols.length) {
                const cacheKey = curInstrument + '-' + curTimeframe;
                cachedSymbols[cacheKey] = res.available_symbols;
                rebuildSymDropdown(res.available_symbols);
            }

            if (!res.success || !res.data || !res.data.length) {
                if (!isOption) $('#pa-sf-tbody').html(noDataHtml(18, res.message));
                else           $('#pa-opt-tbody').html(noDataHtml(20, res.message));
                $('#pa-info-text').text('');
                return;
            }

            const mode         = res.data[0] ? (res.data[0].mode || 'summary') : 'summary';
            const isDetail     = (mode === 'detail');
            const totalCandles = res.data.reduce((a,d) => a + (d.total_candles || 0), 0);
            const modeLabel    = isDetail
                ? '<span style="color:var(--sky);font-family:var(--mono);">&#9776; DETAIL — all candles</span>'
                : '<span style="color:var(--amber);font-family:var(--mono);">&#9641; SUMMARY — latest only</span>';
            $('#pa-info-text').html(totalCandles + ' candles · ' + res.data.length + ' symbol(s) &nbsp;' + modeLabel);
            $('#pa-candle-info').html(totalCandles + ' candles · ' + res.data.length + ' symbol(s)');

            if (!isOption) renderSFTable(res.data);
            else           renderOptionTable(res.data);

            $('#pa-last-upd').text('Updated ' + new Date().toLocaleTimeString());
        },
        error(xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Server error';
            if (!isOption) $('#pa-sf-tbody').html(noDataHtml(18, '⚠ ' + msg));
            else           $('#pa-opt-tbody').html(noDataHtml(20, '⚠ ' + msg));
        }
    });
}

// ═══════════════════════════════════════════════════════════════
//  TABLE RENDERERS
// ═══════════════════════════════════════════════════════════════

// ── Stock / FUT table ─────────────────────────────────────────

function renderSFTable(dataArr) {
    const isFut = (curInstrument === 'fut');
    let rows = '';
    let rowNum = 1;

    dataArr.forEach(function(d, si) {
        const signals = d.signals || [];
        const zebraBase = si % 2 === 0;

        signals.forEach(function(s, idx) {
            const rowCls = signalRowClass(s.bias, s.signal);
            const zebra  = (idx % 2 === 0) ? 'row-even' : 'row-odd';

            rows += '<tr class="' + rowCls + ' ' + zebra + '">'
                + '<td class="c-num">' + (rowNum++) + '</td>'
                + '<td class="c-time">' + s.time + '</td>'
                + '<td class="c-sym">'
                    + escHtml(d.symbol)
                    + (d.expiry ? '<small>' + d.expiry + '</small>' : '')
                + '</td>'
                + '<td class="c-o sep-ohlc">₹' + n(s.open)  + '</td>'
                + '<td class="c-h">₹'          + n(s.high)  + '</td>'
                + '<td class="c-l">₹'          + n(s.low)   + '</td>'
                + '<td class="c-c">₹'          + n(s.close) + '</td>'
                + '<td class="c-vol">'          + nInt(s.volume) + '</td>'
                + '<td class="c-pp sep-pivot">₹' + n(s.PP) + '</td>'
                + '<td class="c-s1">₹'           + n(s.S1) + '</td>'
                + '<td class="c-s2">₹'           + n(s.S2) + '</td>'
                + '<td class="c-r1">₹'           + n(s.R1) + '</td>'
                + '<td class="c-r2">₹'           + n(s.R2) + '</td>'
                + '<td class="sep-signal">' + signalBadge(s.bias, s.signal, s.strength) + '</td>'
                + '<td>' + matchPill(s.s1_match) + '</td>'
                + '<td>' + matchPill(s.r1_match) + '</td>'
                + '<td>' + ppCrossPill(s.pp_cross) + '</td>'
                + (isFut
                    ? '<td class="c-oi">' + nInt(s.oi) + '</td>'
                    : '')
                + '</tr>';
        });
    });

    if (!rows) rows = noDataHtml(isFut ? 18 : 17);
    $('#pa-sf-tbody').html(rows);
}

// ── Option table ──────────────────────────────────────────────

function renderOptionTable(dataArr) {
    let rows = '';
    let rowNum = 1;

    dataArr.forEach(function(d, si) {
        const ceSignals = d.ce_signals || [];
        const peSignals = d.pe_signals || [];
        const atmStrike = d.atm_strike ? '₹' + nInt(d.atm_strike) : '—';

        // Build a time-keyed map
        const times = {};
        ceSignals.forEach(s => { times[s.time] = times[s.time] || {}; times[s.time].ce = s; });
        peSignals.forEach(s => { times[s.time] = times[s.time] || {}; times[s.time].pe = s; });

        Object.entries(times).forEach(function([time, row], idx) {
            const ce = row.ce || null;
            const pe = row.pe || null;
            const zebra = idx % 2 === 0 ? 'row-even' : 'row-odd';

            const ceCells = ce
                ? '<td class="sep-ce c-o">₹'  + n(ce.open)  + '</td>'
                + '<td class="c-h">₹'          + n(ce.high)  + '</td>'
                + '<td class="c-l">₹'          + n(ce.low)   + '</td>'
                + '<td class="c-c">₹'          + n(ce.close) + '</td>'
                + '<td class="c-pp">₹'         + n(ce.PP)    + '</td>'
                + '<td class="c-s1">₹'         + n(ce.S1)    + '</td>'
                + '<td class="c-r1">₹'         + n(ce.R1)    + '</td>'
                + '<td>' + signalBadge(ce.bias, ce.signal, ce.strength) + '</td>'
                : '<td colspan="8" class="sep-ce" style="color:var(--text-3);font-size:9px;">— no CE data —</td>';

            const peCells = pe
                ? '<td class="sep-pe c-o">₹'  + n(pe.open)  + '</td>'
                + '<td class="c-h">₹'          + n(pe.high)  + '</td>'
                + '<td class="c-l">₹'          + n(pe.low)   + '</td>'
                + '<td class="c-c">₹'          + n(pe.close) + '</td>'
                + '<td class="c-pp">₹'         + n(pe.PP)    + '</td>'
                + '<td class="c-s1">₹'         + n(pe.S1)    + '</td>'
                + '<td class="c-r1">₹'         + n(pe.R1)    + '</td>'
                + '<td>' + signalBadge(pe.bias, pe.signal, pe.strength) + '</td>'
                : '<td colspan="8" class="sep-pe" style="color:var(--text-3);font-size:9px;">— no PE data —</td>';

            rows += '<tr class="' + zebra + '">'
                + '<td class="c-num">' + (rowNum++) + '</td>'
                + '<td class="c-time">' + time + '</td>'
                + '<td class="c-sym">' + escHtml(d.symbol)
                    + (d.expiry ? '<small>' + d.expiry + '</small>' : '')
                + '</td>'
                + '<td><span class="atm-badge">₹' + nInt(d.atm_strike) + '</span></td>'
                + ceCells
                + peCells
                + '</tr>';
        });
    });

    if (!rows) rows = noDataHtml(20);
    $('#pa-opt-tbody').html(rows);
}

// ═══════════════════════════════════════════════════════════════
//  BADGE / CELL HELPERS
// ═══════════════════════════════════════════════════════════════

function signalBadge(bias, label, strength) {
    if (!bias || bias === 'NEUTRAL') return '<span class="sig-neutral">&#9135; ' + (label || 'At Pivot') + '</span>';

    if (bias === 'BULLISH') {
        if (strength === 'STRONG')   return '<span class="sig-bullish-strong">&#129033; ' + label + '</span>';
        if (strength === 'MODERATE') return '<span class="sig-bullish-mod">&#8679; '      + label + '</span>';
        return                              '<span class="sig-bullish-weak">&#8593; '      + label + '</span>';
    }
    if (bias === 'BEARISH') {
        if (strength === 'STRONG')   return '<span class="sig-bearish-strong">&#129035; ' + label + '</span>';
        if (strength === 'MODERATE') return '<span class="sig-bearish-mod">&#8681; '      + label + '</span>';
        return                              '<span class="sig-bearish-weak">&#8595; '      + label + '</span>';
    }
    return '<span class="sig-neutral">—</span>';
}

function signalRowClass(bias, label) {
    if (!label) return '';
    if (label === 'Above R1' || label === 'Above R2') return 'row-breakout';
    if (label === 'Below S1' || label === 'Below S2') return 'row-breakdown';
    return '';
}

function matchPill(matched) {
    if (matched === null || matched === undefined)
        return '<span style="color:var(--text-3);font-size:9px;">—</span>';
    return matched
        ? '<span class="match-yes">&#10003; YES</span>'
        : '<span class="match-no">&#215; NO</span>';
}

function ppCrossPill(cross) {
    if (!cross) return '<span style="color:var(--text-3);font-size:9px;">—</span>';
    return '<span class="pp-cross">&#9651; CROSS</span>';
}

// ── Number formatters ─────────────────────────────────────────

function n(v) {
    if (v == null || v === '') return '—';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function nInt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}
function escHtml(s) {
    if (!s) return '—';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Loading / empty HTML ──────────────────────────────────────

function loadingHtml(cols, msg) {
    return '<tr><td colspan="' + cols + '">'
        + '<div class="pa-loading"><div class="pa-spinner"></div>'
        + '<div class="pa-loading-text">' + (msg || 'Loading&hellip;') + '</div>'
        + '</div></td></tr>';
}
function noDataHtml(cols, msg) {
    return '<tr><td colspan="' + cols + '">'
        + '<div class="pa-no-data">'
        + '<i class="fas fa-chart-area"></i>'
        + (msg || 'No pivot data found for the selected date / symbol.')
        + '</div></td></tr>';
}
</script>
@endpush