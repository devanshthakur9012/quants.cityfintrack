{{-- FILE: resources/views/themes/{active_theme}/user/nifty-breakout-analyzer/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — NIFTY BREAKOUT ANALYZER  v2.0
   Dark terminal · Matches Pivot Analysis design
══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --c-bg:       #0B0E11;
    --c-surface:  #131722;
    --c-panel:    #1C2030;
    --c-border:   rgba(255,255,255,.06);
    --c-border2:  rgba(255,255,255,.11);
    --c-lime:     #7DFF00;
    --c-lime-dim: rgba(125,255,0,.1);
    --c-lime-glo: rgba(125,255,0,.06);
    --c-blue:     #00B8D4;
    --c-red:      #EF5350;
    --c-teal:     #26A69A;
    --c-amber:    #FFA726;
    --c-purple:   #AB47BC;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.03);
    --c-bull:     #26A69A;
    --c-bear:     #EF5350;
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.nb-wrap { font-family: var(--f-sans); color: var(--c-text); background: var(--c-bg); }
.nb-wrap * { box-sizing: border-box; }
.mono { font-family: var(--f-mono); }

@keyframes nbFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.nb-anim { animation: nbFadeUp .5s ease both; }
@keyframes nbSpin { to { transform: rotate(360deg); } }

/* ══ HERO ═════════════════════════════════════ */
.nb-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.nb-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.nb-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.nb-hero-left { position: relative; z-index: 1; }
.nb-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.nb-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.nb-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.nb-hero h1 span { color: var(--c-lime); }
.nb-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 560px; }
.nb-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--c-lime); flex-shrink: 0;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}
@media (max-width: 768px) {
    .nb-hero { flex-direction: column; padding: 24px 18px; }
    .nb-hero-icon { display: none; }
}

/* ══ FILTER BAR ═══════════════════════════════ */
.nb-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.nb-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.nb-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.nb-filter-sep { width: 1px; height: 26px; background: var(--c-border2); flex-shrink: 0; }

/* Symbol select */
.nb-select {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 28px 6px 11px;
    font-size: 12px; font-weight: 600; color: var(--c-text);
    font-family: var(--f-mono);
    appearance: none; cursor: pointer; outline: none; min-width: 140px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    transition: border-color .2s;
}
.nb-select:focus { border-color: rgba(125,255,0,.45); }

/* Date controls */
.nb-date-wrap { display: flex; align-items: center; gap: 4px; }
.nb-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.nb-date-input:focus { border-color: rgba(125,255,0,.45); }
.nb-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.nb-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.nb-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.nb-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Badges */
.nb-live-badge { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.nb-hist-badge { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Threshold slider */
.nb-thresh-wrap { display: flex; align-items: center; gap: 8px; }
.nb-thresh-disp {
    font-family: var(--f-mono); font-size: 13px; font-weight: 700;
    color: var(--c-lime); min-width: 40px; text-align: center;
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.25);
    border-radius: 6px; padding: 2px 8px;
}
input[type=range].nb-range { accent-color: var(--c-lime); width: 110px; cursor: pointer; }

/* Action buttons */
.nb-analyze-btn {
    background: var(--c-lime); color: #000; border: none; border-radius: 7px;
    padding: 7px 18px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(125,255,0,.2);
    display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
}
.nb-analyze-btn:hover { background: #8FFF1A; box-shadow: 0 0 22px rgba(125,255,0,.35); transform: translateY(-1px); }
.nb-reset-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: var(--f-mono);
    transition: all .2s; letter-spacing: .05em;
}
.nb-reset-btn:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }

.nb-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.nb-info-text { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.nb-upd-text  { font-size: 10px; color: rgba(120,123,134,.5); font-family: var(--f-mono); }

@media (max-width: 768px) {
    .nb-filter-bar { padding: 0 16px; }
    .nb-filter-inner { gap: 8px; }
    .nb-filter-right { margin-left: 0; width: 100%; }
}

/* ══ CONTENT ══════════════════════════════════ */
.nb-content { padding: 24px 32px 64px; }
@media (max-width: 768px) { .nb-content { padding: 16px 12px 48px; } }

/* Config warning */
.nb-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.nb-warn.show { display: flex; }
.nb-warn i { font-size: 18px; flex-shrink: 0; }
.nb-warn strong { color: #fff; }

/* ══ STATS ════════════════════════════════════ */
.nb-stats {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px; margin-bottom: 20px;
}
@media (max-width: 900px) { .nb-stats { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 500px) { .nb-stats { grid-template-columns: repeat(2,1fr); } }

.nb-stat-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; padding: 14px 16px;
    border-left: 2px solid var(--c-border2);
    position: relative; overflow: hidden;
}
.nb-stat-card::before {
    content: ''; position: absolute;
    top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, currentColor, transparent);
    opacity: .2;
}
.nb-stat-card.s-total { border-left-color: var(--c-amber); }
.nb-stat-card.s-total::before { color: var(--c-amber); }
.nb-stat-card.s-ce    { border-left-color: var(--c-teal); }
.nb-stat-card.s-ce::before    { color: var(--c-teal); }
.nb-stat-card.s-pe    { border-left-color: var(--c-red); }
.nb-stat-card.s-pe::before    { color: var(--c-red); }
.nb-stat-card.s-syms  { border-left-color: var(--c-blue); }
.nb-stat-card.s-syms::before  { color: var(--c-blue); }
.nb-stat-card.s-inv   { border-left-color: var(--c-purple); }
.nb-stat-card.s-inv::before   { color: var(--c-purple); }
.nb-stat-card.s-sig   { border-left-color: var(--c-lime); }
.nb-stat-card.s-sig::before   { color: var(--c-lime); }

.nb-stat-label {
    font-size: 9px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--c-muted); margin-bottom: 6px;
    font-family: var(--f-mono);
}
.nb-stat-val { font-family: var(--f-mono); font-size: 22px; font-weight: 700; color: var(--c-text); }
.s-total .nb-stat-val { color: var(--c-amber); }
.s-ce    .nb-stat-val { color: #4DB6AC; }
.s-pe    .nb-stat-val { color: #EF9A9A; }
.s-syms  .nb-stat-val { color: var(--c-blue); }
.s-inv   .nb-stat-val { color: var(--c-purple); font-size: 16px; }
.s-sig   .nb-stat-val { color: var(--c-lime); }

/* ══ TABLE CARD ═══════════════════════════════ */
.nb-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    position: relative;
}
.nb-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.nb-card-header {
    padding: 13px 18px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    background: rgba(0,0,0,.2);
}
.nb-card-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: var(--c-text); display: flex; align-items: center; gap: 8px;
}
.nb-card-subtitle { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.nb-tscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ══ TABLE ════════════════════════════════════ */
.nb-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 1100px; }

.nb-table thead tr.th-group th {
    padding: 8px 10px 4px; text-align: center;
    font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    background: var(--c-panel); border-bottom: none; white-space: nowrap;
}
.nb-table thead tr.th-cols th {
    padding: 4px 10px 8px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}

/* Column group colors */
.g-info   { color: var(--c-muted)  !important; }
.g-nifty  { color: var(--c-amber)  !important; }
.g-option { color: var(--c-blue)   !important; }
.g-trade  { color: var(--c-teal)   !important; }

/* Separator borders */
.sep-nifty  { border-left: 1px solid rgba(255,167,38,.15) !important; }
.sep-option { border-left: 1px solid rgba(0,184,212,.15)  !important; }
.sep-trade  { border-left: 1px solid rgba(38,166,154,.15) !important; }

/* Body cells */
.nb-table tbody td {
    padding: 7px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.nb-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.tr-even { background: var(--c-surface); }
.tr-odd  { background: rgba(0,0,0,.15); }
.tr-ce   { background: rgba(38,166,154,.03) !important; }
.tr-pe   { background: rgba(239,83,80,.03)  !important; }

/* Group separator row */
.nb-group-row td {
    background: linear-gradient(90deg, rgba(255,167,38,.07), rgba(255,167,38,.01)) !important;
    border-top: 1px solid rgba(255,167,38,.18) !important;
    border-bottom: none !important;
    padding: 9px 16px !important; text-align: left !important;
    font-family: var(--f-sans); font-size: 11px; font-weight: 700;
    color: var(--c-amber) !important; letter-spacing: .02em;
}
.nb-group-row.gr-pe td {
    background: linear-gradient(90deg, rgba(239,83,80,.06), rgba(239,83,80,.01)) !important;
    border-top-color: rgba(239,83,80,.18) !important;
    color: #EF9A9A !important;
}

/* Cell styles */
.c-num  { font-size: 9px; color: rgba(120,123,134,.35); }
.c-date { font-size: 11px; font-weight: 700; color: var(--c-lime); }
.c-sym  { font-size: 11px; font-weight: 700; color: var(--c-blue); }
.c-sym small { display: block; font-size: 8px; color: var(--c-muted); font-weight: 400; margin-top: 1px; }
.c-val  { font-size: 11px; font-weight: 700; color: var(--c-text); }
.c-sm   { font-size: 10px; color: var(--c-muted); }
.up     { color: #4DB6AC; font-weight: 700; }
.dn     { color: #EF9A9A; font-weight: 700; }

/* Signal badges */
.sig-ce {
    display: inline-block;
    background: rgba(38,166,154,.12); color: #4DB6AC;
    border: 1px solid rgba(38,166,154,.3);
    border-radius: 4px; padding: 3px 9px;
    font-family: var(--f-sans); font-size: 10px; font-weight: 700;
}
.sig-pe {
    display: inline-block;
    background: rgba(239,83,80,.1); color: #EF9A9A;
    border: 1px solid rgba(239,83,80,.3);
    border-radius: 4px; padding: 3px 9px;
    font-family: var(--f-sans); font-size: 10px; font-weight: 700;
}

/* Time pills */
.time-trig {
    display: inline-block;
    background: rgba(255,167,38,.1); border: 1px solid rgba(255,167,38,.28);
    color: var(--c-amber); padding: 2px 8px; border-radius: 4px;
    font-size: 10px; font-weight: 700;
}
.time-buy {
    display: inline-block;
    background: rgba(0,184,212,.08); border: 1px solid rgba(0,184,212,.22);
    color: var(--c-blue); padding: 2px 8px; border-radius: 4px;
    font-size: 10px; font-weight: 700;
}
.time-trig.pe {
    background: rgba(239,83,80,.08); border-color: rgba(239,83,80,.25); color: #EF9A9A;
}

.c-invest { font-size: 11px; font-weight: 700; color: var(--c-purple); }

/* Loading / empty */
.nb-loading {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; padding: 56px 20px;
}
.nb-spinner {
    width: 32px; height: 32px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-lime);
    border-radius: 50%; animation: nbSpin .9s linear infinite;
}
.nb-loading-text { color: var(--c-muted); margin-top: 12px; font-size: 12px; font-family: var(--f-mono); }

.nb-empty { text-align: center; padding: 52px 20px; color: var(--c-muted); }
.nb-empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 22px;
}
.nb-empty p { font-size: 12px; font-family: var(--f-mono); margin-top: 4px; }
</style>

<div class="nb-wrap">

{{-- ══ HERO ══ --}}
<div class="nb-hero nb-anim">
    <div class="nb-hero-left">
        <div class="nb-hero-eyebrow">Breakout Analytics</div>
        <h1>NIFTY-Driven <span>Breakout</span> Analyzer</h1>
        <p>
            Monitors NIFTY FUT candles for threshold breaches from the day's open price,
            then maps ATM option entry trades across all configured symbols at the next
            candle's open using the highest-OI strike.
        </p>
    </div>
    <div class="nb-hero-icon"><i class="las la-chart-area"></i></div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="nb-filter-bar">
    <div class="nb-filter-inner">

        <span class="nb-filter-label">Symbol</span>
        <select id="nb-sym" class="nb-select" style="min-width:140px;" onchange="nbAnalyze()">
            <option value="ALL">— All Symbols —</option>
        </select>

        <div class="nb-filter-sep"></div>

        <span class="nb-filter-label">Date</span>
        <div class="nb-date-wrap">
            <button class="nb-date-nav" onclick="nbShiftDate(-1)">‹</button>
            <input type="date" id="nb-date" class="nb-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="nbAnalyze()">
            <button class="nb-date-nav" onclick="nbShiftDate(1)">›</button>
            <button class="nb-date-nav nb-today-btn" onclick="nbGoToday()">TODAY</button>
            <span id="nb-date-badge"></span>
        </div>

        <div class="nb-filter-sep"></div>

        <span class="nb-filter-label">Threshold</span>
        <div class="nb-thresh-wrap">
            <span class="nb-thresh-disp" id="nb-thresh-disp">30</span>
            <span style="font-size:10px;color:var(--c-muted);">pts</span>
            <input type="range" id="nb-thresh" class="nb-range" min="5" max="300" step="5" value="30">
        </div>

        <div class="nb-filter-sep"></div>

        <span class="nb-filter-label">Signal</span>
        <select id="nb-signal" class="nb-select" style="min-width:110px;" onchange="nbAnalyze()">
            <option value="BOTH">CE + PE</option>
            <option value="CE">CE Only</option>
            <option value="PE">PE Only</option>
        </select>

        <button class="nb-analyze-btn" onclick="nbAnalyze()">
            <i class="las la-search"></i> Analyze
        </button>
        <button class="nb-reset-btn" onclick="nbReset()">↺ Reset</button>

        <div class="nb-filter-right">
            <span class="nb-info-text" id="nb-info"></span>
            <span class="nb-upd-text"  id="nb-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="nb-content">

    <div class="nb-warn" id="nb-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="nb-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="nb-stats nb-anim">
        <div class="nb-stat-card s-total"><div class="nb-stat-label">Total Trades</div><div class="nb-stat-val" id="st-total">—</div></div>
        <div class="nb-stat-card s-ce">  <div class="nb-stat-label">↑ CE Trades</div> <div class="nb-stat-val" id="st-ce">—</div></div>
        <div class="nb-stat-card s-pe">  <div class="nb-stat-label">↓ PE Trades</div> <div class="nb-stat-val" id="st-pe">—</div></div>
        <div class="nb-stat-card s-syms"><div class="nb-stat-label">Symbols Hit</div>  <div class="nb-stat-val" id="st-syms">—</div></div>
        <div class="nb-stat-card s-inv"> <div class="nb-stat-label">Total Inv.</div>   <div class="nb-stat-val" id="st-inv">—</div></div>
        <div class="nb-stat-card s-sig"> <div class="nb-stat-label">Signal Days</div>  <div class="nb-stat-val" id="st-sig">—</div></div>
    </div>

    {{-- Table --}}
    <div class="nb-card nb-anim">
        <div class="nb-card-header">
            <div class="nb-card-title" id="nb-card-title">◆ NIFTY Breakout Signal Trades · Threshold: 30 pts</div>
            <span class="nb-card-subtitle" id="nb-subtitle">Detecting last available date…</span>
        </div>
        <div class="nb-tscroll">
            <table class="nb-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="4" class="g-info">Info</th>
                        <th colspan="4" class="g-nifty sep-nifty">▲ NIFTY FUT Signal</th>
                        <th colspan="4" class="g-option sep-option">◆ Option Details</th>
                        <th colspan="3" class="g-trade sep-trade">▶ Trade</th>
                    </tr>
                    <tr class="th-cols">
                        <th class="g-info">#</th>
                        <th class="g-info">Date</th>
                        <th class="g-info">Symbol</th>
                        <th class="g-info">Signal</th>
                        <th class="g-nifty sep-nifty">NIFTY Open<br><span style="font-size:7px;font-weight:400;opacity:.5;">09:15</span></th>
                        <th class="g-nifty">Trigger Val<br><span style="font-size:7px;font-weight:400;opacity:.5;">H/L</span></th>
                        <th class="g-nifty">Signal Bar</th>
                        <th class="g-nifty">Move (pts)</th>
                        <th class="g-option sep-option">Strike<br><span style="font-size:7px;font-weight:400;opacity:.5;">highest OI</span></th>
                        <th class="g-option">OI</th>
                        <th class="g-option">Expiry</th>
                        <th class="g-option">Buy Time<br><span style="font-size:7px;font-weight:400;opacity:.5;">next candle</span></th>
                        <th class="g-trade sep-trade">Buy Price ₹</th>
                        <th class="g-trade">Lot Size</th>
                        <th class="g-trade">Investment ₹</th>
                    </tr>
                </thead>
                <tbody id="nb-tbody">
                    <tr><td colspan="15">
                        <div class="nb-loading">
                            <div class="nb-spinner"></div>
                            <div class="nb-loading-text">Detecting last available date…</div>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.nb-content --}}
</div>{{-- /.nb-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════
//  NIFTY Breakout Analyzer — JS (no jQuery, logic unchanged)
// ═══════════════════════════════════════════════════════════

var NB_ANALYZE  = '{{ route("nifty-breakout-analyzer.analyze") }}';
var NB_SYMBOLS  = '{{ route("nifty-breakout-analyzer.symbols") }}';
var NB_LASTDATE = '{{ route("nifty-breakout-analyzer.last.date") }}';
var NB_TODAY    = '{{ now()->toDateString() }}';

var nbSymCache = null;

function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

// Threshold slider — update display + card title
el('nb-thresh').addEventListener('input', function () {
    txt('nb-thresh-disp', this.value);
    txt('nb-card-title', '◆ NIFTY Breakout Signal Trades · Threshold: ' + this.value + ' pts');
});

// ═══════════════════════════════════════════════════════════
//  BOOT — detect last available date then auto-analyze
// ═══════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    nbResolveLastDateAndLoad();
});

function nbResolveLastDateAndLoad() {
    fetch(NB_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.last_date) el('nb-date').value = res.last_date;
            nbLoadSymbols(function () { nbAnalyze(); });
        })
        .catch(function () {
            nbLoadSymbols(function () { nbAnalyze(); });
        });
}

// ── Date helpers ──────────────────────────────────────────

function nbGetDate() { return el('nb-date').value; }

function nbShiftDate(d) {
    var picker = el('nb-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > NB_TODAY) return;
    picker.value = s;
    nbAnalyze();
}

function nbGoToday() {
    el('nb-date').value = NB_TODAY;
    nbAnalyze();
}

function nbUpdateDateBadge(isToday) {
    el('nb-date-badge').innerHTML = isToday
        ? '<span class="nb-live-badge">● Live</span>'
        : '<span class="nb-hist-badge">📅 Historical</span>';
}

// ── Symbols ───────────────────────────────────────────────

function nbLoadSymbols(callback) {
    if (nbSymCache !== null) {
        nbRebuildSym(nbSymCache);
        if (callback) callback();
        return;
    }

    fetch(NB_SYMBOLS, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.no_config) {
                nbShowWarn(res.message || '');
                nbSymCache = [];
                nbRebuildSym([]);
            } else {
                nbHideWarn();
                nbSymCache = res.symbols || [];
                nbRebuildSym(nbSymCache);
            }
            if (callback) callback();
        })
        .catch(function () { if (callback) callback(); });
}

function nbRebuildSym(syms) {
    var sel  = el('nb-sym');
    var prev = sel.value;
    var opts = '<option value="ALL">— All Symbols —</option>';
    syms.forEach(function (s) {
        opts += '<option value="' + s + '"' + (s === prev ? ' selected' : '') + '>' + s + '</option>';
    });
    sel.innerHTML = opts;
    if (prev && prev !== 'ALL') {
        sel.value = prev;
        if (sel.value !== prev) sel.value = 'ALL';
    }
}

// ── Analyze ───────────────────────────────────────────────

function nbAnalyze() {
    var date = nbGetDate();
    var thr  = el('nb-thresh').value;
    var sig  = el('nb-signal').value;
    var sym  = el('nb-sym').value;

    if (!date) return;

    nbHideWarn();
    nbResetStats();

    html('nb-tbody', '<tr><td colspan="15">'
        + '<div class="nb-loading">'
        + '<div class="nb-spinner"></div>'
        + '<div class="nb-loading-text">Scanning NIFTY FUT for ' + thr + 'pt breakout signals on ' + date + '…</div>'
        + '</div></td></tr>');
    txt('nb-subtitle', date + ' · Scanning…');

    var params = new URLSearchParams({
        date: date, threshold: thr,
        filter: sig, symbol_filter: sym,
    });

    fetch(NB_ANALYZE + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { if (!r.ok) throw new Error('Server error ' + r.status); return r.json(); })
    .then(function (res) {
        if (typeof res.is_today !== 'undefined') {
            nbUpdateDateBadge(res.is_today);
        }

        if (res.available_symbols && res.available_symbols.length) {
            nbSymCache = res.available_symbols;
            nbRebuildSym(nbSymCache);
            if (sym && sym !== 'ALL') el('nb-sym').value = sym;
        }

        if (res.no_config) { nbShowWarn(res.message); nbEmptyTable('No active config.'); return; }

        if (!res.success || !res.data || !res.data.length) {
            nbEmptyTable(res.message || 'No signals found for this date.');
            txt('nb-subtitle', date + ' · No signals found');
            return;
        }

        nbRenderTable(res.data);
        nbUpdateStats(res);

        el('nb-info').innerHTML =
            '<span style="color:#4DB6AC;">CE: ' + res.ce_count + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:#EF9A9A;">PE: ' + res.pe_count + '</span>'
            + ' &nbsp;·&nbsp; Threshold: <span style="color:var(--c-lime);">' + res.threshold + 'pts</span>';
        txt('nb-subtitle', date + ' · ' + res.message);
        txt('nb-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) { nbEmptyTable('⚠ ' + err.message); });
}

// ── Render ────────────────────────────────────────────────

function nbRenderTable(data) {
    var h         = '', num = 1;
    var lastGroup = null;

    data.forEach(function (r, i) {
        var groupKey = r.date + '|' + r.signal_type + '|' + r.trigger_time;
        var isCE     = r.signal_type === 'CE';

        if (groupKey !== lastGroup) {
            var moveSign = r.nifty_move >= 0 ? '+' : '';
            h += '<tr class="nb-group-row' + (isCE ? '' : ' gr-pe') + '">'
                + '<td colspan="15">'
                + r.date + ' &nbsp;|&nbsp; '
                + (isCE ? '📈 CE BREAKOUT' : '📉 PE BREAKOUT')
                + ' &nbsp;|&nbsp; NIFTY Open: ₹' + r.nifty_open.toFixed(2)
                + ' → ' + (isCE ? 'HIGH' : 'LOW') + ': ₹' + r.nifty_trigger.toFixed(2)
                + ' &nbsp;|&nbsp; Signal Bar: ' + r.trigger_time
                + ' → Entry: ' + r.buy_time
                + ' &nbsp;|&nbsp; Move: ' + moveSign + r.nifty_move.toFixed(2) + ' pts'
                + '</td></tr>';
            lastGroup = groupKey;
        }

        var moveCls  = r.nifty_move >= 0 ? 'up' : 'dn';
        var moveSign = r.nifty_move >= 0 ? '+' : '';
        var rowCls   = (isCE ? 'tr-ce' : 'tr-pe') + ' ' + (i % 2 === 0 ? 'tr-even' : 'tr-odd');

        h += '<tr class="' + rowCls + '">'
            + '<td class="c-num">'  + num++ + '</td>'
            + '<td class="c-date">' + r.date + '</td>'
            + '<td class="c-sym">'  + esc(r.symbol) + (r.expiry_date ? '<small>' + r.expiry_date + '</small>' : '') + '</td>'
            + '<td>' + (isCE ? '<span class="sig-ce">📈 CE</span>' : '<span class="sig-pe">📉 PE</span>') + '</td>'
            + '<td class="sep-nifty c-val" style="color:var(--c-amber);">₹' + r.nifty_open.toFixed(2) + '</td>'
            + '<td class="c-val ' + (isCE ? 'up' : 'dn') + '">₹' + r.nifty_trigger.toFixed(2) + '</td>'
            + '<td><span class="time-trig' + (isCE ? '' : ' pe') + '">' + r.trigger_time + '</span></td>'
            + '<td><span class="' + moveCls + '">' + moveSign + r.nifty_move.toFixed(2) + '</span></td>'
            + '<td class="sep-option c-val" style="color:var(--c-amber);">₹' + nInt(r.strike) + '</td>'
            + '<td class="c-sm">' + fmtOI(r.strike_oi) + '</td>'
            + '<td class="c-sm">' + (r.expiry_date || '—') + '</td>'
            + '<td><span class="time-buy">' + r.buy_time + '</span></td>'
            + '<td class="sep-trade"><strong class="up">₹' + r.buy_price.toFixed(2) + '</strong></td>'
            + '<td class="c-sm">' + r.lot_size + '</td>'
            + '<td class="c-invest">₹' + numFmt(r.investment) + '</td>'
            + '</tr>';
    });

    html('nb-tbody', h || nbEmptyHtml('No results.'));
}

// ── Stats / helpers ───────────────────────────────────────

function nbUpdateStats(res) {
    txt('st-total', res.total_records  || '0');
    txt('st-ce',   res.ce_count       || '0');
    txt('st-pe',   res.pe_count       || '0');
    txt('st-syms', res.symbols_hit    || '0');
    txt('st-inv',  '₹' + Number(res.total_investment || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 }));
    txt('st-sig',  res.signal_count   || '0');
}

function nbResetStats() {
    ['st-total','st-ce','st-pe','st-syms','st-sig'].forEach(function (id) { txt(id, '—'); });
    txt('st-inv', '—');
}

function nbShowWarn(msg) { el('nb-warn').classList.add('show'); txt('nb-warn-msg', msg || ''); }
function nbHideWarn()    { el('nb-warn').classList.remove('show'); }
function nbEmptyTable(msg) { html('nb-tbody', nbEmptyHtml(msg)); }
function nbEmptyHtml(msg) {
    return '<tr><td colspan="15">'
        + '<div class="nb-empty">'
        + '<div class="nb-empty-icon"><i class="las la-chart-area"></i></div>'
        + '<p>' + (msg || 'No data found.') + '</p>'
        + '</div></td></tr>';
}

function nbReset() {
    fetch(NB_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            el('nb-date').value   = res.last_date || NB_TODAY;
            el('nb-thresh').value = '30';
            txt('nb-thresh-disp', '30');
            txt('nb-card-title', '◆ NIFTY Breakout Signal Trades · Threshold: 30 pts');
            el('nb-signal').value = 'BOTH';
            el('nb-sym').value    = 'ALL';
            nbHideWarn();
            nbAnalyze();
        })
        .catch(function () {
            el('nb-date').value   = NB_TODAY;
            el('nb-thresh').value = '30';
            txt('nb-thresh-disp', '30');
            txt('nb-card-title', '◆ NIFTY Breakout Signal Trades · Threshold: 30 pts');
            el('nb-signal').value = 'BOTH';
            el('nb-sym').value    = 'ALL';
            nbHideWarn();
            nbAnalyze();
        });
}

function fmtOI(v)  { var n=Number(v)||0; if(n>=1e7)return(n/1e7).toFixed(2)+'Cr'; if(n>=1e5)return(n/1e5).toFixed(2)+'L'; if(n>=1e3)return(n/1e3).toFixed(1)+'K'; return n.toLocaleString('en-IN'); }
function nInt(v)   { return Number(v || 0).toLocaleString('en-IN'); }
function numFmt(v) { return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 }); }
function esc(s)    { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
@endpush