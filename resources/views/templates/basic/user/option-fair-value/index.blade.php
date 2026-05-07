@extends($activeTemplate . 'layouts.master')

@push('style')
<style>
/* ─── Google Font ─────────────────────────────────────────────── */
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Syne:wght@600;700;800&display=swap');

/* ─── Root palette ────────────────────────────────────────────── */
:root {
    --bg0:   #080c14;
    --bg1:   #0d1220;
    --bg2:   #111827;
    --bg3:   #161e30;
    --line:  rgba(255,255,255,0.06);
    --line2: rgba(255,255,255,0.11);
    --muted: rgba(255,255,255,0.30);
    --dim:   rgba(255,255,255,0.50);
    --text:  rgba(255,255,255,0.88);

    --ce:    #38bdf8;   /* call blue   */
    --pe:    #4ade80;   /* put green   */
    --iv:    #fbbf24;   /* IV amber    */
    --sig:   #a78bfa;   /* signal violet */
    --buy:   #22d3ee;   /* buy call    */
    --sell:  #fb923c;   /* buy put     */
    --over:  #f87171;
    --under: #4ade80;

    --r: 6px;
    --r2: 10px;
    --mono: 'JetBrains Mono', monospace;
    --head: 'Syne', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; }

/* ─── Page wrapper ────────────────────────────────────────────── */
.ofv-wrap {
    
    background: var(--bg0);
    min-height: 100vh;
    padding: 20px 16px 60px;
    color: var(--text);
}

/* ─── Header ──────────────────────────────────────────────────── */
.ofv-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 18px;
    padding: 18px 22px;
    background: var(--bg2);
    border: 1px solid var(--line2);
    border-radius: var(--r2);
    border-left: 3px solid var(--sig);
}
.ofv-header-title {
    font-family: var(--head);
    font-size: 18px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.3px;
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ofv-header-title .tag {
    
    font-size: 10px;
    font-weight: 600;
    background: rgba(167,139,250,0.15);
    color: var(--sig);
    border: 1px solid rgba(167,139,250,0.25);
    border-radius: 4px;
    padding: 2px 8px;
    letter-spacing: .5px;
}
.ofv-header-sub {
    font-size: 10px;
    color: var(--muted);
    line-height: 1.7;
    margin: 0;
}
.ofv-header-sub strong { font-weight: 700; }

/* ─── Filter bar ──────────────────────────────────────────────── */
.ofv-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 16px;
    background: var(--bg2);
    border: 1px solid var(--line2);
    border-radius: var(--r2);
    margin-bottom: 14px;
}
.ofv-filters label {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 1px;
    color: var(--muted);
    text-transform: uppercase;
    margin: 0;
}
.fv { width: 1px; height: 22px; background: var(--line2); flex-shrink: 0; }

/* date strip */
.date-wrap { display: flex; align-items: center; gap: 4px; }
.date-wrap input[type="date"] {
    background: var(--bg3);
    border: 1px solid var(--line2);
    border-radius: var(--r);
    color: var(--text);
    padding: 5px 10px;
    font-size: 11px;
    
    font-weight: 600;
    outline: none;
    cursor: pointer;
}
.date-wrap input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(.6); cursor: pointer; }
.dnav {
    background: var(--bg3);
    border: 1px solid var(--line2);
    color: var(--text);
    border-radius: var(--r);
    width: 26px; height: 26px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 13px; font-weight: 700;
    transition: background .12s;
}
.dnav:hover { background: rgba(255,255,255,0.1); }
.dnav.today { width: auto; padding: 0 10px; font-size: 9px; letter-spacing: .5px; }

.dbadge { font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 10px; }
.dbadge.live { background: rgba(74,222,128,0.12); color: #4ade80; border: 1px solid rgba(74,222,128,0.25); }
.dbadge.hist { background: rgba(251,191,36,0.12); color: #fbbf24; border: 1px solid rgba(251,191,36,0.25); }

/* strike pills */
.sp-wrap { display: flex; gap: 3px; }
.sp {
    padding: 4px 12px; border-radius: 20px;
    font-size: 10px; font-weight: 700;
    cursor: pointer;
    border: 1px solid var(--line2);
    background: var(--bg3); color: var(--muted);
    transition: all .15s; user-select: none;
    
}
.sp:hover  { border-color: var(--iv); color: var(--iv); }
.sp.active { background: var(--iv); color: #000; border-color: var(--iv); }

/* selects */
.ofv-select {
    background: var(--bg3);
    border: 1px solid var(--line2);
    color: var(--text);
    border-radius: var(--r);
    padding: 5px 10px;
    font-size: 11px;
    
    font-weight: 600;
    outline: none;
    cursor: pointer;
    min-width: 160px;
}
.ofv-select option { background: var(--bg2); color: var(--text); }
.ofv-select:focus  { border-color: rgba(167,139,250,0.5); }

.sort-sel { min-width: 0; width: 170px; }

/* buttons */
.btn-run {
    background: var(--sig);
    color: #fff;
    border: none;
    border-radius: var(--r);
    padding: 6px 18px;
    
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
    transition: opacity .15s;
    letter-spacing: .3px;
}
.btn-run:hover { opacity: .82; }
.btn-auto {
    background: var(--bg3);
    color: var(--muted);
    border: 1px solid var(--line2);
    border-radius: var(--r);
    padding: 5px 14px;
    
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
    letter-spacing: .3px;
}
.btn-auto.on { border-color: #4ade80; color: #4ade80; }
.tol-note {
    font-size: 9px; color: var(--muted);
    padding: 3px 9px;
    background: var(--bg3);
    border: 1px solid var(--line);
    border-radius: 4px;
    letter-spacing: .4px;
}
.tol-note span { color: var(--iv); font-weight: 700; }
.last-upd { font-size: 9px; color: rgba(255,255,255,0.25); margin-left: auto; }

/* ─── Mode banners ────────────────────────────────────────────── */
.mode-banner {
    display: none;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    border-radius: var(--r);
    padding: 8px 14px;
    margin-bottom: 12px;
    font-size: 10px;
    color: var(--muted);
}
.mode-banner.on { display: flex; }
.mode-banner.scanner {
    background: rgba(251,191,36,0.05);
    border: 1px solid rgba(251,191,36,0.18);
}
.mode-banner.analysis {
    background: rgba(56,189,248,0.05);
    border: 1px solid rgba(56,189,248,0.18);
}
.mode-banner strong { font-weight: 700; }
.back-btn {
    background: var(--bg3);
    border: 1px solid var(--line2);
    color: var(--text);
    border-radius: var(--r);
    padding: 3px 12px;
    font-size: 10px;
    
    font-weight: 700;
    cursor: pointer;
}
.back-btn:hover { background: rgba(255,255,255,0.1); }

/* ─── Summary strip ───────────────────────────────────────────── */
.sum-strip { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.sc {
    flex: 1; min-width: 90px;
    background: var(--bg2);
    border: 1px solid var(--line);
    border-radius: var(--r);
    padding: 10px 14px;
    border-left: 2px solid transparent;
}
.sc small { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); display: block; margin-bottom: 2px; }
.sc strong { display: block; font-size: 20px; font-weight: 700; font-family: var(--head); color: #fff; }
.sc.ce  { border-left-color: var(--ce); }
.sc.pe  { border-left-color: var(--pe); }
.sc.iv  { border-left-color: var(--iv); }
.sc.bc  { border-left-color: var(--buy); }
.sc.bp  { border-left-color: var(--sell); }

/* ─── Table card ──────────────────────────────────────────────── */
.tbl-card {
    background: var(--bg1);
    border: 1px solid var(--line2);
    border-radius: var(--r2);
    overflow: hidden;
}
.tbl-scroll { overflow-x: auto; }
.sig-table { width: 100%; border-collapse: collapse; min-width: 1780px; }

/* thead */
.sig-table thead tr.hg th {
    padding: 10px 10px 5px;
    text-align: center;
    font-family: var(--head);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
    white-space: nowrap;
    background: rgba(0,0,0,0.4);
    border-bottom: none;
}
.sig-table thead tr.hc th {
    padding: 5px 10px 9px;
    text-align: center;
    font-size: 8px;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
    white-space: nowrap;
    background: rgba(0,0,0,0.28);
    color: var(--muted);
    border-bottom: 2px solid var(--line2);
}
.hm  { color: rgba(255,255,255,0.35) !important; }
.hce { color: var(--ce) !important; }
.hpe { color: var(--pe) !important; }
.hiv { color: var(--iv) !important; }
.hsg { color: var(--sig) !important; }

/* col separators */
.sep-ce  { border-left: 2px solid rgba(56,189,248,0.20) !important; }
.sep-pe  { border-left: 2px solid rgba(74,222,128,0.20) !important; }
.sep-iv  { border-left: 2px solid rgba(251,191,36,0.25) !important; }
.sep-sig { border-left: 2px solid rgba(167,139,250,0.25) !important; }
.sep-d   { border-left: 1px dashed rgba(255,255,255,0.07) !important; }

/* tbody */
.sig-table tbody td {
    padding: 8px 10px;
    text-align: center;
    font-size: 11px;
    border-bottom: 1px solid var(--line);
    vertical-align: middle;
    white-space: nowrap;
    
}
.sig-table tbody tr:hover { background: rgba(255,255,255,0.035) !important; }
.row-e { background: rgba(255,255,255,0.008); }
.row-o { background: rgba(0,0,0,0.12); }

/* data cells */
.c-idx    { font-size: 9px; color: rgba(255,255,255,0.18); }
.c-time   { font-size: 12px; font-weight: 700; color: var(--ce); }
.c-spot   { font-size: 12px; font-weight: 700; color: #fff; }
.c-strike { font-size: 11px; font-weight: 700; color: var(--iv); }
.c-ltp    { color: rgba(255,255,255,0.65); font-weight: 600; }
.c-fair   { color: var(--ce); font-weight: 700; }

/* symbol badge */
.sym-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
    background: rgba(56,189,248,0.10);
    color: var(--ce);
    border: 1px solid rgba(56,189,248,0.22);
}
.level-badge {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 7px;
    font-weight: 700;
    background: rgba(251,191,36,0.10);
    color: var(--iv);
    border: 1px solid rgba(251,191,36,0.20);
    margin-top: 2px;
}

/* valuation badges */
.vb {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 5px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .3px;
}
.vb.OVERPRICED  { background: rgba(248,113,113,0.15); color: var(--over);  border: 1px solid rgba(248,113,113,0.30); }
.vb.UNDERPRICED { background: rgba(74,222,128,0.15);  color: var(--under); border: 1px solid rgba(74,222,128,0.30); }
.vb.FAIR        { background: rgba(255,255,255,0.04); color: var(--muted); border: 1px solid var(--line2); }
.vb.NA          { background: transparent; color: rgba(255,255,255,0.15); border: 1px solid var(--line); font-size: 8px; }

.dp { color: var(--over);  font-weight: 700; }
.dn { color: var(--under); font-weight: 700; }
.dz { color: var(--muted); }

/* IV cells */
.bg-iv  { background: rgba(251,191,36,0.025) !important; }
.bg-sig { background: rgba(167,139,250,0.025) !important; }

.iv-val { font-size: 12px; font-weight: 700; }
.iv-val.ce { color: var(--ce); }
.iv-val.pe { color: var(--pe); }

/* signal badges */
.sbadge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
}
.sbadge.BUY_CALL { background: rgba(34,211,238,0.15); color: var(--buy);  border: 1px solid rgba(34,211,238,0.35); }
.sbadge.BUY_PUT  { background: rgba(251,146,60,0.15);  color: var(--sell); border: 1px solid rgba(251,146,60,0.35); }
.sbadge.NO_TRADE { background: transparent; color: rgba(255,255,255,0.18); border: 1px solid var(--line); font-size: 9px; }
.imb-pos { color: var(--sell); font-weight: 700; }
.imb-neg { color: var(--buy);  font-weight: 700; }
.imb-neu { color: var(--muted); }

/* MA cell */
.ma-warm {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 8px;
    font-weight: 700;
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.22);
    border: 1px solid var(--line);
    letter-spacing: .4px;
}
.ma-regime {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 8px;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .3px;
}
.ma-above { background: rgba(74,222,128,0.14); color: #4ade80; border: 1px solid rgba(74,222,128,0.30); }
.ma-below { background: rgba(248,113,113,0.14); color: #f87171; border: 1px solid rgba(248,113,113,0.30); }
.ma-at    { background: rgba(255,255,255,0.04); color: var(--dim);  border: 1px solid var(--line2); }

/* loading / empty */
.ofv-loading, .ofv-empty {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 70px 20px;
    color: var(--muted);
}
.spin {
    width: 32px; height: 32px;
    border: 3px solid rgba(167,139,250,0.15);
    border-top: 3px solid var(--sig);
    border-radius: 50%;
    animation: spin .7s linear infinite;
    margin-bottom: 14px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.ofv-empty svg { opacity: .18; margin-bottom: 12px; }

/* prev-day seeding note */
.prev-day-note {
    display: none;
    align-items: center;
    gap: 8px;
    font-size: 10px;
    color: rgba(251,191,36,0.7);
    background: rgba(251,191,36,0.05);
    border: 1px solid rgba(251,191,36,0.15);
    border-radius: var(--r);
    padding: 6px 14px;
    margin-bottom: 10px;
}
.prev-day-note.on { display: flex; }

/* scrollbar */
::-webkit-scrollbar { height: 5px; width: 5px; }
::-webkit-scrollbar-track { background: var(--bg1); }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 3px; }
</style>
@endpush

@section('content')
<section class="pt-40 pb-50">
<div class="ofv-wrap">

    {{-- ── Header ── --}}
    <div class="ofv-header">
        <div>
            <h4 class="ofv-header-title">
                Option Fair Value Analyser
                <span class="tag">BLACK-SCHOLES</span>
                <span class="tag" style="background:rgba(56,189,248,0.12);color:var(--ce);border-color:rgba(56,189,248,0.25);">CE IV</span>
                <span class="tag" style="background:rgba(74,222,128,0.12);color:var(--pe);border-color:rgba(74,222,128,0.25);">PE IV</span>
                <span class="tag" style="background:rgba(74,222,128,0.10);color:#a3e635;border-color:rgba(163,230,53,0.25);">PREV-DAY&nbsp;MA</span>
            </h4>
            <p class="ofv-header-sub">
                Fair&nbsp;Price&nbsp;=&nbsp;BS(Spot,&nbsp;Strike,&nbsp;ATM&nbsp;IV,&nbsp;DTE)&nbsp;&nbsp;·&nbsp;&nbsp;
                <strong style="color:var(--over);">OVERPRICED → SELL</strong>&nbsp;&nbsp;·&nbsp;&nbsp;
                <strong style="color:var(--under);">UNDERPRICED → BUY</strong>&nbsp;&nbsp;·&nbsp;&nbsp;
                <strong style="color:var(--buy);">▲ BUY CALL</strong>&nbsp;/&nbsp;<strong style="color:var(--sell);">▼ BUY PUT</strong>&nbsp;&mdash;
                mispricing imbalance&nbsp;+&nbsp;independent CE/PE IV expansion&nbsp;&nbsp;·&nbsp;&nbsp;
                <span style="color:var(--iv);">● IV-11MA uses previous-day candles — no warm-up gap at 09:15</span>
            </p>
        </div>
    </div>

    {{-- ── Filter bar ── --}}
    <div class="ofv-filters">

        <label>DATE</label>
        <div class="date-wrap">
            <button class="dnav" onclick="shiftDate(-1)" title="Prev day">‹</button>
            <input type="date" id="date-picker"
                value="{{ now()->toDateString() }}"
                max="{{ now()->toDateString() }}"
                onchange="runAnalysis()">
            <button class="dnav" onclick="shiftDate(1)" title="Next day">›</button>
            <button class="dnav today" onclick="goToday()">TODAY</button>
            <span id="date-badge"></span>
        </div>

        <div class="fv"></div>
        <label>STRIKE</label>
        <div class="sp-wrap">
            <div class="sp" data-val="ATM-1">ATM−1</div>
            <div class="sp active" data-val="ATM">ATM</div>
            <div class="sp" data-val="ATM+1">ATM+1</div>
        </div>

        <div class="fv"></div>
        <label>SYMBOL</label>
        <select id="sym-filter" class="ofv-select" onchange="runAnalysis()">
            <option value="">— All Symbols —</option>
        </select>

        <select id="sort-by" class="ofv-select sort-sel" onchange="runAnalysis()">
            <option value="symbol">Sort: A – Z</option>
            <option value="signal">Sort: Signals first</option>
            <option value="iv_above">IV Above MA first</option>
            <option value="iv_below">IV Below MA first</option>
            <option value="ce_overpriced">CE Most Overpriced</option>
            <option value="ce_underpriced">CE Most Underpriced</option>
            <option value="pe_overpriced">PE Most Overpriced</option>
            <option value="pe_underpriced">PE Most Underpriced</option>
            <option value="mispricing">Largest Mispricing</option>
        </select>

        <div class="tol-note">Fair±<span>5%</span> · Signal±<span>1.5%</span></div>

        <button class="btn-run" id="btn-run">⟳ Refresh</button>
        <button class="btn-auto" id="auto-btn" onclick="toggleAuto()">▶ Auto 60s</button>
        <span id="auto-tag" style="font-size:9px;"></span>
        <span class="last-upd" id="last-upd"></span>
    </div>

    {{-- ── Banners ── --}}
    <div class="mode-banner scanner" id="scanner-banner">
        <span style="color:var(--iv);font-weight:800;">⌖ SCANNER MODE</span>
        <span style="color:var(--line2);">|</span>
        <span>Latest candle · <strong style="color:var(--iv);">IV-expansion gate bypassed</strong> — signals fire on mispricing imbalance only</span>
        <span style="font-size:9px;background:rgba(251,191,36,0.10);color:var(--iv);border:1px solid rgba(251,191,36,0.22);border-radius:4px;padding:2px 8px;">
            Select a symbol for full CE/PE IV-11MA analysis
        </span>
    </div>

    <div class="mode-banner analysis" id="mode-banner">
        <span style="color:var(--ce);font-weight:800;">◈ ANALYSIS MODE</span>
        <span style="color:var(--line2);">|</span>
        <strong id="mode-sym-name" style="color:var(--ce);"></strong>
        <span id="mode-candle-count" style="color:var(--muted);"></span>
        <span style="color:var(--line2);">|</span>
        <span>All candles 09:15→15:15 · <strong style="color:var(--ce);">CE IV-11MA + PE IV-11MA active · seeded from prev-day</strong></span>
        <button class="back-btn" onclick="clearSymbol()">← Scanner</button>
    </div>

    <div class="prev-day-note" id="prev-day-note">
        <span style="font-weight:800;">⬤ PREV-DAY SEED</span>
        <span id="prev-day-text"></span>
    </div>

    {{-- ── Summary ── --}}
    <div class="sum-strip" id="sum-strip" style="display:none;">
        <div class="sc"     ><small>Total Rows</small>      <strong id="s-total">0</strong></div>
        <div class="sc ce"  ><small>CE Overpriced</small>   <strong id="s-ce-over" style="color:var(--over);">0</strong></div>
        <div class="sc ce"  ><small>CE Underpriced</small>  <strong id="s-ce-under" style="color:var(--under);">0</strong></div>
        <div class="sc pe"  ><small>PE Overpriced</small>   <strong id="s-pe-over" style="color:var(--over);">0</strong></div>
        <div class="sc pe"  ><small>PE Underpriced</small>  <strong id="s-pe-under" style="color:var(--under);">0</strong></div>
        <div class="sc iv"  ><small>⬆ IV Above MA</small>   <strong id="s-iv-above" style="color:#4ade80;">0</strong></div>
        <div class="sc iv"  ><small>⬇ IV Below MA</small>   <strong id="s-iv-below" style="color:var(--over);">0</strong></div>
        <div class="sc bc"  ><small>▲ BUY CALL</small>      <strong id="s-buy-call" style="color:var(--buy);">0</strong></div>
        <div class="sc bp"  ><small>▼ BUY PUT</small>       <strong id="s-buy-put"  style="color:var(--sell);">0</strong></div>
    </div>

    {{-- ── Table ── --}}
    <div class="tbl-card">
        <div class="tbl-scroll">
            <table class="sig-table">
                <thead>
                    {{-- group row --}}
                    <tr class="hg">
                        <th colspan="5" class="hm" style="text-align:left;padding-left:14px;">Meta</th>
                        <th colspan="5" class="hce sep-ce">▲ CALL (CE) — Market vs Fair</th>
                        <th colspan="5" class="hpe sep-pe">▼ PUT (PE) — Market vs Fair</th>
                        <th colspan="2" class="hiv sep-iv bg-iv">CE IV</th>
                        <th colspan="2" class="hiv bg-iv">PE IV</th>
                        <th class="hiv bg-iv" rowspan="2" style="vertical-align:middle;min-width:68px;">Exp.<br>Move</th>
                        <th colspan="2" class="hsg sep-sig bg-sig">⚡ Signal</th>
                    </tr>
                    {{-- col labels --}}
                    <tr class="hc">
                        <th class="hm">#</th>
                        <th class="hm">Time</th>
                        <th class="hm" style="text-align:left;padding-left:14px;">Symbol</th>
                        <th class="hm">Spot</th>
                        <th class="hm">Strike<br><span style="font-size:7px;opacity:.5;font-weight:400;">Level · Days</span></th>
                        {{-- CE --}}
                        <th class="hce sep-ce">LTP</th>
                        <th class="hce">Fair ₹</th>
                        <th class="hce">Status</th>
                        <th class="hce sep-d">Diff ₹</th>
                        <th class="hce">Diff %</th>
                        {{-- PE --}}
                        <th class="hpe sep-pe">LTP</th>
                        <th class="hpe">Fair ₹</th>
                        <th class="hpe">Status</th>
                        <th class="hpe sep-d">Diff ₹</th>
                        <th class="hpe">Diff %</th>
                        {{-- CE IV --}}
                        <th class="hiv sep-iv bg-iv">IV %</th>
                        <th class="hiv bg-iv">11-MA</th>
                        {{-- PE IV --}}
                        <th class="hiv sep-d bg-iv">IV %</th>
                        <th class="hiv bg-iv">11-MA</th>
                        {{-- Move: rowspan above --}}
                        {{-- Signal --}}
                        <th class="hsg sep-sig bg-sig">Action<br><span style="font-size:7px;opacity:.5;font-weight:400;">Imbalance</span></th>
                        <th class="hsg bg-sig">Reason</th>
                    </tr>
                </thead>
                <tbody id="fv-tbody">
                    <tr><td colspan="22">
                        <div class="ofv-loading">
                            <div class="spin"></div>
                            <div style="font-size:12px;">Calculating fair values…</div>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
// ══════════════════════════════════════════════════════════════════
//  Option Fair Value Analyser  ·  CE/PE IV Architecture
//  - Separate CE IV + PE IV columns
//  - Separate ce_iv_11ma + pe_iv_11ma columns
//  - Previous-day MA seeding (no warm-up gap)
//  - BUY_CALL: peIvExpanding   BUY_PUT: ceIvExpanding
// ══════════════════════════════════════════════════════════════════

let _strike    = 'ATM';
let autoTimer  = null;
let rowIdx     = 1;
const todayStr = '{{ now()->toDateString() }}';

// ── Init ──────────────────────────────────────────────────────────
$(document).ready(function () {
    loadSymbols();
    runAnalysis();
    updateDateBadge();

    $('.sp').on('click', function () {
        $('.sp').removeClass('active');
        $(this).addClass('active');
        _strike = $(this).data('val');
        runAnalysis();
    });
    $('#sort-by').on('change', runAnalysis);
    $('#btn-run').on('click', runAnalysis);
});

// ── Date helpers ──────────────────────────────────────────────────
function getDate()  { return document.getElementById('date-picker').value; }

function shiftDate(d) {
    var p = document.getElementById('date-picker');
    var dt = new Date(p.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > todayStr) return;
    p.value = s;
    updateDateBadge();
    runAnalysis();
}
function goToday() {
    document.getElementById('date-picker').value = todayStr;
    updateDateBadge();
    runAnalysis();
}
function updateDateBadge() {
    var d  = getDate();
    var el = document.getElementById('date-badge');
    el.innerHTML = (d === todayStr)
        ? '<span class="dbadge live">● Live</span>'
        : '<span class="dbadge hist">▣ Historical</span>';
}

// ── Symbols ───────────────────────────────────────────────────────
function loadSymbols() {
    $.get('{{ route("option-fair-value.symbols") }}', function (res) {
        if (!res.success) return;
        var o = '<option value="">— All Symbols —</option>';
        res.symbols.forEach(function (s) { o += '<option value="' + s + '">' + s + '</option>'; });
        $('#sym-filter').html(o);
    });
}
function clearSymbol() { $('#sym-filter').val(''); runAnalysis(); }

// ── Auto-refresh ──────────────────────────────────────────────────
function toggleAuto() {
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        $('#auto-btn').text('▶ Auto 60s').removeClass('on');
        $('#auto-tag').text('');
    } else {
        autoTimer = setInterval(runAnalysis, 60000);
        $('#auto-btn').text('■ Stop').addClass('on');
        $('#auto-tag').css('color', '#4ade80').text('● live');
        runAnalysis();
    }
}

// ── Main request ──────────────────────────────────────────────────
function runAnalysis() {
    var sym  = $('#sym-filter').val();
    var sort = $('#sort-by').val();
    var date = getDate();
    updateDateBadge();

    if (date !== todayStr && autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        $('#auto-btn').text('▶ Auto 60s').removeClass('on');
        $('#auto-tag').text('');
    }
    $('#sort-by').toggle(!sym);
    showLoading();

    $.ajax({
        url : '{{ route("option-fair-value.analyze") }}',
        type: 'GET',
        data: { strike_filter: _strike, sort_by: sort, symbol: sym, date: date },
        success: function (res) {
            if (!res.success) { showEmpty(res.message || 'No data found.'); return; }
            renderBanners(res);
            renderSummary(res.summary, res.total_rows);
            renderTable(res.rows, res.mode);
            renderPrevDayNote(res);
            $('#last-upd').text(
                'Date: ' + res.trade_date
                + '  ·  Time: ' + (res.latest_time || '—')
                + '  ·  Updated: ' + new Date().toLocaleTimeString()
            );
        },
        error: function (xhr) {
            showEmpty((xhr.responseJSON && xhr.responseJSON.message) || 'Server error.');
        }
    });
}

// ── Banners ───────────────────────────────────────────────────────
function renderBanners(res) {
    var sym = $('#sym-filter').val();
    if (res.mode === 'single' && sym) {
        $('#scanner-banner').removeClass('on');
        $('#mode-sym-name').text(sym);
        $('#mode-candle-count').text('— ' + res.total_rows + ' candle' + (res.total_rows !== 1 ? 's' : ''));
        $('#mode-banner').addClass('on');
    } else {
        $('#mode-banner').removeClass('on');
        $('#scanner-banner').addClass('on');
    }
}

function renderPrevDayNote(res) {
    var note = document.getElementById('prev-day-note');
    var sym  = $('#sym-filter').val();
    if (res.mode === 'single' && sym && res.prev_date) {
        document.getElementById('prev-day-text').textContent =
            'IV-11MA seeded using candles from ' + res.prev_date + ' — signals available from first candle today';
        note.classList.add('on');
    } else {
        note.classList.remove('on');
    }
}

// ── Summary ───────────────────────────────────────────────────────
function renderSummary(s, total) {
    $('#s-total').text(total);
    $('#s-ce-over').text(s.ceOver);
    $('#s-ce-under').text(s.ceUnder);
    $('#s-pe-over').text(s.peOver);
    $('#s-pe-under').text(s.peUnder);
    $('#s-iv-above').text(s.ivAbove || 0);
    $('#s-iv-below').text(s.ivBelow || 0);
    $('#s-buy-call').text(s.buyCall || 0);
    $('#s-buy-put').text(s.buyPut  || 0);
    $('#sum-strip').show();
}

// ── Table render ──────────────────────────────────────────────────
function renderTable(rows, mode) {
    if (!rows || !rows.length) { showEmpty('No data for selected filters.'); return; }

    var html = '';
    rowIdx = 1;

    rows.forEach(function (r, idx) {
        var zebraCls = idx % 2 === 0 ? 'row-e' : 'row-o';
        var sig      = r.signal || 'NO_TRADE';
        var ceSt     = r.ce_status || 'N/A';
        var peSt     = r.pe_status || 'N/A';

        var trStyle = '';
        if      (sig === 'BUY_CALL') trStyle = ' style="background:rgba(34,211,238,0.035)"';
        else if (sig === 'BUY_PUT')  trStyle = ' style="background:rgba(251,146,60,0.035)"';

        // ── CE cells ──────────────────────────────────────────────
        var ceCells = (r.ce_ltp != null)
            ? td('sep-ce c-ltp', '₹' + r.ce_ltp)
            + td('c-fair', '<strong>₹' + nv(r.ce_fair) + '</strong>')
            + td('', vb(ceSt))
            + td('sep-d ' + dc(r.ce_diff), diffv(r.ce_diff, '₹'))
            + td(dc(r.ce_diff_pct), diffp(r.ce_diff_pct))
            : '<td colspan="5" class="sep-ce" style="color:rgba(255,255,255,.1);font-size:9px;">— no CE —</td>';

        // ── PE cells ──────────────────────────────────────────────
        var peCells = (r.pe_ltp != null)
            ? td('sep-pe c-ltp', '₹' + r.pe_ltp)
            + td('c-fair', '<strong>₹' + nv(r.pe_fair) + '</strong>')
            + td('', vb(peSt))
            + td('sep-d ' + dc(r.pe_diff), diffv(r.pe_diff, '₹'))
            + td(dc(r.pe_diff_pct), diffp(r.pe_diff_pct))
            : '<td colspan="5" class="sep-pe" style="color:rgba(255,255,255,.1);font-size:9px;">— no PE —</td>';

        // ── CE IV ─────────────────────────────────────────────────
        var ceIvCell = td('sep-iv bg-iv',
            r.ce_iv_pct != null
                ? '<span class="iv-val ce">' + r.ce_iv_pct + '%</span>'
                : dash()
        );
        var ceMaCell = maCell(r.ce_iv_pct, r.ce_iv_11ma, 'CE');

        // ── PE IV ─────────────────────────────────────────────────
        var peIvCell = td('sep-d bg-iv',
            r.pe_iv_pct != null
                ? '<span class="iv-val pe">' + r.pe_iv_pct + '%</span>'
                : dash()
        );
        var peMaCell = maCell(r.pe_iv_pct, r.pe_iv_11ma, 'PE');

        // ── Expected move ─────────────────────────────────────────
        var moveCell = td('bg-iv',
            r.expected_move != null
                ? '<span style="color:var(--sig);font-size:10px;font-weight:700;">±₹' + r.expected_move + '</span>'
                : dash()
        );

        // ── Signal ────────────────────────────────────────────────
        var imbRaw = (r.signal_imbalance !== null && r.signal_imbalance !== undefined)
                        ? Number(r.signal_imbalance) : null;
        var cePct  = r.signal_ce_diff_pct != null ? Number(r.signal_ce_diff_pct) : null;
        var pePct  = r.signal_pe_diff_pct != null ? Number(r.signal_pe_diff_pct) : null;

        var imbCls = imbRaw === null ? 'imb-neu'
                   : (imbRaw > 0 ? 'imb-pos' : (imbRaw < 0 ? 'imb-neg' : 'imb-neu'));
        var imbTxt = imbRaw !== null ? (imbRaw > 0 ? '+' : '') + imbRaw : '—';

        var diffLine = '';
        if (cePct !== null || pePct !== null) {
            var cs = cePct !== null
                ? '<span style="color:var(--ce);">CE ' + (cePct >= 0 ? '+' : '') + cePct + '%</span>' : '';
            var ps = pePct !== null
                ? '<span style="color:var(--pe);">PE ' + (pePct >= 0 ? '+' : '') + pePct + '%</span>' : '';
            diffLine = '<br><span style="font-size:8px;opacity:.5;">'
                + cs + (cs && ps ? '&nbsp;&nbsp;' : '') + ps + '</span>';
        }

        var reasonHtml = r.signal_reason
            ? r.signal_reason + diffLine
            : '<span style="color:rgba(255,255,255,.15);">—</span>' + diffLine;

        var sigCells =
            '<td class="sep-sig bg-sig" style="text-align:center;">'
            + sigBadge(sig)
            + '<br><span class="' + imbCls + '" style="font-size:9px;">Imb: ' + imbTxt + '</span>'
            + '</td>'
            + '<td class="bg-sig" style="font-size:9px;color:rgba(255,255,255,.4);max-width:150px;white-space:normal;text-align:left;padding:7px 10px;">'
            + reasonHtml
            + '</td>';

        // ── Strike meta ───────────────────────────────────────────
        var strikeMeta =
            '<span class="c-strike">₹' + fmt(r.strike) + '</span>'
            + '<br><span class="level-badge">' + (r.strike_level || 'ATM') + '</span>'
            + '&thinsp;<span style="font-size:8px;color:var(--muted);">' + r.days_to_expiry + 'd</span>';

        html +=
            '<tr class="' + zebraCls + '"' + trStyle + '>'
            + td('c-idx', rowIdx++)
            + td('c-time', r.time || '—')
            + '<td style="text-align:left;padding-left:14px;"><span class="sym-badge">' + r.symbol + '</span></td>'
            + td('c-spot', '₹' + fmt(r.spot))
            + '<td>' + strikeMeta + '</td>'
            + ceCells
            + peCells
            + ceIvCell + ceMaCell
            + peIvCell + peMaCell
            + moveCell
            + sigCells
            + '</tr>';
    });

    $('#fv-tbody').html(html);
}

// ── MA cell renderer ──────────────────────────────────────────────
// r.ce_iv_pct / r.ce_iv_11ma  and  r.pe_iv_pct / r.pe_iv_11ma
function maCell(iv, ma, label) {
    var baseClass = label === 'CE' ? 'bg-iv' : 'bg-iv';
    if (ma == null) {
        return '<td class="' + baseClass + '" style="text-align:center;padding:7px 10px;">'
            + '<span class="ma-warm">⧖ WARM</span>'
            + '</td>';
    }
    var diff   = Number((iv - ma).toFixed(2));
    var above  = diff > 0;
    var equal  = diff === 0;
    var regime = above ? 'ma-above' : (equal ? 'ma-at' : 'ma-below');
    var icon   = above ? '▲' : (equal ? '▬' : '▼');
    var dcolor = above ? '#4ade80' : (equal ? 'rgba(255,255,255,.35)' : '#f87171');
    var dtxt   = (diff >= 0 ? '+' : '') + diff;
    var maColor = label === 'CE' ? 'var(--ce)' : 'var(--pe)';

    return '<td class="' + baseClass + '" style="text-align:center;padding:7px 10px;">'
        + '<div style="font-size:11px;font-weight:700;color:' + maColor + ';margin-bottom:3px;">' + ma + '%</div>'
        + '<span class="ma-regime ' + regime + '">' + icon + ' ' + (above ? 'ABOVE' : (equal ? 'AT' : 'BELOW')) + '</span>'
        + '<div style="font-size:9px;color:' + dcolor + ';margin-top:2px;">' + dtxt + '</div>'
        + '</td>';
}

// ── Micro helpers ─────────────────────────────────────────────────
function td(cls, inner) {
    return '<td' + (cls ? ' class="' + cls + '"' : '') + '>' + inner + '</td>';
}
function vb(status) {
    var lbl = status === 'N/A' ? '—' : status;
    return '<span class="vb ' + status + '">' + lbl + '</span>';
}
function sigBadge(sig) {
    var icons = { BUY_CALL: '▲', BUY_PUT: '▼', NO_TRADE: '—' };
    return '<span class="sbadge ' + sig + '">' + (icons[sig] || '—') + ' ' + sig.replace(/_/g, ' ') + '</span>';
}
function dc(v) {
    if (v == null) return 'dz';
    return Number(v) > 0 ? 'dp' : Number(v) < 0 ? 'dn' : 'dz';
}
function diffv(v, prefix) {
    if (v == null) return dash();
    var n = Number(v);
    return (n >= 0 ? '+' : '') + prefix + n;
}
function diffp(v) {
    if (v == null) return dash();
    var n = Number(v);
    return (n >= 0 ? '+' : '') + n + '%';
}
function nv(v) {
    return v != null ? v : '—';
}
function dash() {
    return '<span style="color:rgba(255,255,255,.15);font-size:9px;">—</span>';
}
function fmt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

// ── States ────────────────────────────────────────────────────────
function showLoading() {
    $('#fv-tbody').html(
        '<tr><td colspan="22"><div class="ofv-loading">'
        + '<div class="spin"></div>'
        + '<div style="font-size:12px;">Calculating fair values…</div>'
        + '</div></td></tr>'
    );
    $('#sum-strip').hide();
    $('#prev-day-note').removeClass('on');
}

function showEmpty(msg) {
    $('#fv-tbody').html(
        '<tr><td colspan="22"><div class="ofv-empty">'
        + '<svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">'
        + '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 16l4-4 4 4 4-6"/></svg>'
        + '<div style="font-size:12px;">' + msg + '</div>'
        + '<div style="font-size:10px;color:rgba(255,255,255,.18);margin-top:6px;">Check option OHLC data exists for the selected date.</div>'
        + '</div></td></tr>'
    );
    $('#sum-strip').hide();
    $('#mode-banner').removeClass('on');
    $('#prev-day-note').removeClass('on');
}
</script>
@endpush