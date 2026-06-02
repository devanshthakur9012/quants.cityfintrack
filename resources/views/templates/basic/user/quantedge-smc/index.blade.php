{{-- FILE: resources/views/themes/{active_theme}/user/quantedge-smc/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   QUANTEDGE — SMART MONEY ANALYSIS  v2.0
   Dark terminal · Matches pivot analysis design
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

.smc-wrap { font-family: var(--f-sans); color: var(--c-text); background: var(--c-bg); }
.smc-wrap * { box-sizing: border-box; }
.mono { font-family: var(--f-mono); }

@keyframes smcFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.smc-anim { animation: smcFadeUp .5s ease both; }
@keyframes smcSpin { to { transform: rotate(360deg); } }

/* ══ HERO ══════════════════════════════════════ */
.smc-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.smc-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.smc-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.smc-hero-left { position: relative; z-index: 1; }
.smc-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.smc-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.smc-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.smc-hero h1 span { color: var(--c-lime); }
.smc-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 580px; }

.smc-hero-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px; }
.smc-pill {
    display: inline-block; padding: 3px 10px; border-radius: 4px;
    font-family: var(--f-mono); font-size: 10px; font-weight: 600;
    letter-spacing: .04em;
}
.smc-pill-buy  { background: rgba(38,166,154,.1);  color: var(--c-teal);  border: 1px solid rgba(38,166,154,.25); }
.smc-pill-sell { background: rgba(239,83,80,.08);   color: var(--c-red);   border: 1px solid rgba(239,83,80,.2);  }
.smc-pill-tf   { background: rgba(255,167,38,.1);   color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); }

.smc-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--c-lime); flex-shrink: 0;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}

@media (max-width: 768px) {
    .smc-hero { flex-direction: column; padding: 24px 18px; }
    .smc-hero-pills { flex-wrap: wrap; }
    .smc-hero-icon { display: none; }
}

/* ══ FILTER BAR ════════════════════════════════ */
.smc-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.smc-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.smc-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.smc-filter-sep { width: 1px; height: 26px; background: var(--c-border2); flex-shrink: 0; }

/* Date controls */
.smc-date-wrap { display: flex; align-items: center; gap: 4px; }
.smc-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.smc-date-input:focus { border-color: rgba(125,255,0,.45); }
.smc-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.smc-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.smc-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.smc-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Date badges */
.smc-live-badge  { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.smc-hist-badge  { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.smc-range-badge { background: rgba(171,71,188,.1);  color: var(--c-purple); border: 1px solid rgba(171,71,188,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Symbol select */
.smc-sym-select {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 28px 6px 11px;
    font-size: 12px; font-weight: 600; color: var(--c-text);
    font-family: var(--f-mono);
    appearance: none; cursor: pointer; outline: none; min-width: 150px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    transition: border-color .2s;
}
.smc-sym-select:focus { border-color: rgba(125,255,0,.45); }

/* Signal filter pills */
.smc-fp-wrap { display: flex; gap: 4px; flex-wrap: wrap; }
.smc-fp {
    padding: 5px 12px; border-radius: 20px;
    font-family: var(--f-mono); font-size: 10px; font-weight: 700;
    cursor: pointer; transition: all .15s;
    border: 1px solid var(--c-border2);
    background: transparent; color: var(--c-muted);
}
.smc-fp:hover     { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.smc-fp.fp-all    { border-color: rgba(0,184,212,.35);  background: rgba(0,184,212,.1);   color: var(--c-blue); }
.smc-fp.fp-buy    { border-color: rgba(38,166,154,.35); background: rgba(38,166,154,.1);  color: var(--c-teal); }
.smc-fp.fp-sell   { border-color: rgba(239,83,80,.35);  background: rgba(239,83,80,.1);   color: var(--c-red); }
.smc-fp.fp-pb     { border-color: rgba(255,167,38,.35); background: rgba(255,167,38,.1);  color: var(--c-amber); }
.smc-fp.fp-nt     { border-color: var(--c-border2);     background: var(--c-panel);        color: var(--c-muted); }

/* Action buttons */
.smc-load-btn {
    background: var(--c-lime); color: #000; border: none; border-radius: 7px;
    padding: 7px 18px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(125,255,0,.2);
    display: inline-flex; align-items: center; gap: 6px;
}
.smc-load-btn:hover { background: #8FFF1A; box-shadow: 0 0 22px rgba(125,255,0,.35); transform: translateY(-1px); }
.smc-reset-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: var(--f-mono);
    transition: all .2s; letter-spacing: .05em;
}
.smc-reset-btn:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }

.smc-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.smc-upd { font-size: 10px; color: rgba(120,123,134,.5); font-family: var(--f-mono); }

@media (max-width: 768px) {
    .smc-filter-bar { padding: 0 16px; }
    .smc-filter-inner { gap: 8px; }
    .smc-filter-right { margin-left: 0; width: 100%; }
}

/* ══ CONTENT ═══════════════════════════════════ */
.smc-content { padding: 24px 32px 64px; }
@media (max-width: 768px) { .smc-content { padding: 16px 12px 48px; } }

/* Config warning */
.smc-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.smc-warn.show { display: flex; }
.smc-warn i { font-size: 18px; flex-shrink: 0; }
.smc-warn strong { color: #fff; }

/* ── STATS ROW ───────────────────────────────── */
.smc-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.smc-stat {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    padding: 14px 18px; min-width: 110px; flex: 1;
}
.smc-stat small {
    display: block; font-family: var(--f-mono); font-size: 9px;
    font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--c-muted); margin-bottom: 4px;
}
.smc-stat strong { display: block; font-family: var(--f-mono); font-size: 1.3rem; font-weight: 700; }
.ss-tot  { border-left: 2px solid var(--c-blue);   border-radius: 0 10px 10px 0; }
.ss-buy  { border-left: 2px solid var(--c-teal);   border-radius: 0 10px 10px 0; }
.ss-sell { border-left: 2px solid var(--c-red);    border-radius: 0 10px 10px 0; }
.ss-pb   { border-left: 2px solid var(--c-amber);  border-radius: 0 10px 10px 0; }
.ss-nt   { border-left: 2px solid var(--c-border2); border-radius: 0 10px 10px 0; }

/* ── TABLE CARD ──────────────────────────────── */
.smc-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    margin-bottom: 20px; position: relative;
}
.smc-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.smc-card-header {
    padding: 13px 18px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    background: rgba(0,0,0,.2);
}
.smc-card-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: var(--c-text); display: flex; align-items: center; gap: 8px;
}
.smc-card-sub { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }

.smc-tscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ══ TABLE ═════════════════════════════════════ */
.smc-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 900px; }

.smc-table thead tr.th-group th {
    padding: 8px 10px 4px; text-align: center;
    font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    background: var(--c-panel); border-bottom: none; white-space: nowrap;
}
.smc-table thead tr.th-cols th {
    padding: 4px 10px 8px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}

/* Column group header colors */
.g-info   { color: var(--c-muted)   !important; }
.g-signal { color: var(--c-teal)    !important; }
.g-smc    { color: var(--c-purple)  !important; }
.g-ema    { color: var(--c-blue)    !important; }

/* Column separators */
.sep-signal { border-left: 1px solid rgba(38,166,154,.15)  !important; }
.sep-smc    { border-left: 1px solid rgba(171,71,188,.15)  !important; }
.sep-ema    { border-left: 1px solid rgba(0,184,212,.15)   !important; }

/* Body cells */
.smc-table tbody td {
    padding: 7px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.smc-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.tr-even { background: var(--c-surface); }
.tr-odd  { background: rgba(0,0,0,.15); }
.tr-buy  { background: rgba(38,166,154,.04)  !important; }
.tr-sell { background: rgba(239,83,80,.04)   !important; }
.tr-pb   { background: rgba(255,167,38,.03)  !important; }

/* Cell types */
.c-num  { font-size: 9px; color: rgba(120,123,134,.35); }
.c-sym  {
    display: inline-block; padding: 2px 8px; border-radius: 4px;
    font-size: 10px; font-weight: 700;
    background: rgba(0,184,212,.1); color: var(--c-blue);
    border: 1px solid rgba(0,184,212,.2);
}
.c-date {
    display: inline-block; padding: 2px 7px; border-radius: 4px;
    font-size: 10px; font-weight: 700;
    background: rgba(171,71,188,.1); color: var(--c-purple);
    border: 1px solid rgba(171,71,188,.2);
}
.c-close { color: var(--c-blue); font-weight: 700; font-size: 11px; }

/* Signal badges */
.sig { display: inline-block; border-radius: 4px; padding: 3px 8px; font-family: var(--f-sans); font-size: 10px; font-weight: 700; letter-spacing: .04em; white-space: nowrap; }
.sig-buy   { background: rgba(38,166,154,.15); color: #4DB6AC; border: 1px solid rgba(38,166,154,.3);  }
.sig-sell  { background: rgba(239,83,80,.12);  color: #EF9A9A; border: 1px solid rgba(239,83,80,.28);  }
.sig-buyp  { background: rgba(255,167,38,.12); color: #FFB74D; border: 1px solid rgba(255,167,38,.28); }
.sig-sellp { background: rgba(234,88,12,.12);  color: #FF8A65; border: 1px solid rgba(234,88,12,.28);  }
.sig-nt    { background: var(--c-panel); color: var(--c-muted); border: 1px solid var(--c-border2); font-size: 9px; }

/* Trend badges */
.trend-up   { display: inline-block; background: rgba(38,166,154,.1);  color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
.trend-dn   { display: inline-block; background: rgba(239,83,80,.1);   color: #EF9A9A; border: 1px solid rgba(239,83,80,.25);  border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
.trend-side { display: inline-block; background: var(--c-panel); color: var(--c-muted); border: 1px solid var(--c-border2); border-radius: 4px; padding: 2px 8px; font-size: 10px; }

/* Bool pills */
.b-yes { display: inline-block; background: rgba(38,166,154,.12); color: #4DB6AC;       border: 1px solid rgba(38,166,154,.25); border-radius: 4px; padding: 1px 7px; font-size: 9px; font-weight: 700; }
.b-no  { display: inline-block; background: var(--c-panel);       color: var(--c-muted); border: 1px solid var(--c-border);    border-radius: 4px; padding: 1px 7px; font-size: 9px; }
.b-wrn { display: inline-block; background: rgba(255,167,38,.1);  color: #FFB74D;        border: 1px solid rgba(255,167,38,.25); border-radius: 4px; padding: 1px 7px; font-size: 9px; font-weight: 700; }

/* EMA vs close */
.ema-abv { color: #4DB6AC; font-size: 10px; font-weight: 700; }
.ema-blw { color: #EF9A9A; font-size: 10px; font-weight: 700; }

/* ── LOADING / EMPTY ─────────────────────────── */
.smc-loading {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 56px 20px;
}
.smc-spinner {
    width: 32px; height: 32px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-lime);
    border-radius: 50%;
    animation: smcSpin .9s linear infinite;
}
.smc-loading-text { color: var(--c-muted); margin-top: 12px; font-size: 12px; font-family: var(--f-mono); }

.smc-empty { text-align: center; padding: 52px 20px; color: var(--c-muted); }
.smc-empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 22px;
}
.smc-empty p { font-size: 12px; font-family: var(--f-mono); margin-top: 4px; }
</style>

<div class="smc-wrap">

{{-- ══ HERO ══ --}}
<div class="smc-hero smc-anim">
    <div class="smc-hero-left">
        <div class="smc-hero-eyebrow">Smart Money Concepts</div>
        <h1>QuantEdge — <span>Smart Money</span> Analysis</h1>
        <p>
            SMC signals on 15min candles — Liquidity sweeps, Fair Value Gaps,
            Order Blocks and EMA-20 confluence for high-probability trade setups.
        </p>
    </div>
    <div class="smc-hero-icon">
        <i class="las la-chart-area"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="smc-filter-bar">
    <div class="smc-filter-inner">

        {{-- Date FROM --}}
        <span class="smc-filter-label">From</span>
        <div class="smc-date-wrap">
            <button class="smc-date-nav" onclick="smcShiftDate('from',-1)">‹</button>
            <input type="date" id="smc-from" class="smc-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="smcSyncDates()">
            <button class="smc-date-nav" onclick="smcShiftDate('from',1)">›</button>
        </div>

        <span class="smc-filter-label">To</span>
        <div class="smc-date-wrap">
            <button class="smc-date-nav" onclick="smcShiftDate('to',-1)">‹</button>
            <input type="date" id="smc-to" class="smc-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="smcSyncDates()">
            <button class="smc-date-nav" onclick="smcShiftDate('to',1)">›</button>
            <button class="smc-date-nav smc-today-btn" onclick="smcGoToday()">TODAY</button>
            <span id="smc-date-badge"></span>
        </div>

        <div class="smc-filter-sep"></div>

        {{-- Symbol --}}
        <span class="smc-filter-label">Symbol</span>
        <select id="smc-sym" class="smc-sym-select">
            <option value="ALL">— All Symbols —</option>
        </select>

        <button class="smc-load-btn" onclick="smcLoad()">
            <i class="las la-sync-alt"></i> Load
        </button>
        <button class="smc-reset-btn" onclick="smcGoToday()">Today</button>

        <div class="smc-filter-sep"></div>

        {{-- Signal filter pills --}}
        <span class="smc-filter-label">Filter</span>
        <div class="smc-fp-wrap" id="smc-fp-wrap">
            <div class="smc-fp fp-all"  data-sig="ALL"           onclick="smcFilter('ALL',this)">All</div>
            <div class="smc-fp"         data-sig="BUY"           onclick="smcFilter('BUY',this)">↑ Buy</div>
            <div class="smc-fp"         data-sig="SELL"          onclick="smcFilter('SELL',this)">↓ Sell</div>
            <div class="smc-fp"         data-sig="BUY_PULLBACK"  onclick="smcFilter('BUY_PULLBACK',this)">Buy PB</div>
            <div class="smc-fp"         data-sig="SELL_PULLBACK" onclick="smcFilter('SELL_PULLBACK',this)">Sell PB</div>
            <div class="smc-fp"         data-sig="NO_TRADE"      onclick="smcFilter('NO_TRADE',this)">No Trade</div>
        </div>

        <div class="smc-filter-right">
            <span class="smc-upd" id="smc-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="smc-content">

    {{-- Config warning --}}
    <div class="smc-warn" id="smc-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="smc-warn-msg">
                Go to Admin → Analysis Config and create a 15min config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="smc-stats" id="smc-stats" style="display:none;">
        <div class="smc-stat ss-tot"><small>Total</small><strong id="st-total" style="color:var(--c-blue);">0</strong></div>
        <div class="smc-stat ss-buy"><small>↑ Buy</small><strong id="st-buy" style="color:var(--c-teal);">0</strong></div>
        <div class="smc-stat ss-sell"><small>↓ Sell</small><strong id="st-sell" style="color:var(--c-red);">0</strong></div>
        <div class="smc-stat ss-pb"><small>Pullbacks</small><strong id="st-pb" style="color:var(--c-amber);">0</strong></div>
        <div class="smc-stat ss-nt"><small>No Trade</small><strong id="st-nt" style="color:var(--c-muted);">0</strong></div>
    </div>

    {{-- Table card --}}
    <div class="smc-card">
        <div class="smc-card-header">
            <div class="smc-card-title">
                <i class="las la-chart-area" style="color:var(--c-lime);"></i>
                QuantEdge Smart Money &nbsp;·&nbsp; 15 Min
            </div>
            <span class="smc-card-sub" id="smc-card-info"></span>
        </div>
        <div class="smc-tscroll">
            <table class="smc-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="4" class="g-info">Info</th>
                        <th colspan="2" class="g-signal sep-signal">⚡ Signal</th>
                        <th colspan="3" class="g-smc sep-smc">▲ SMC Conditions</th>
                        <th colspan="2" class="g-ema sep-ema">📈 EMA-20</th>
                    </tr>
                    <tr class="th-cols">
                        <th class="g-info">#</th>
                        <th class="g-info" style="text-align:left;padding-left:14px;">Symbol</th>
                        <th class="g-info">Date</th>
                        <th class="g-info">Close</th>

                        <th class="g-signal sep-signal">Signal</th>
                        <th class="g-signal">Trend</th>

                        <th class="g-smc sep-smc">Vol Spike<br><span style="font-size:7px;opacity:.4;font-weight:400;">&gt;1.2× avg</span></th>
                        <th class="g-smc">Sweep<br><span style="font-size:7px;opacity:.4;font-weight:400;">Low / High</span></th>
                        <th class="g-smc">FVG<br><span style="font-size:7px;opacity:.4;font-weight:400;">Bull / Bear</span></th>

                        <th class="g-ema sep-ema">EMA-20</th>
                        <th class="g-ema">vs Close</th>
                    </tr>
                </thead>
                <tbody id="smc-tbody">
                    <tr><td colspan="11">
                        <div class="smc-empty">
                            <div class="smc-empty-icon"><i class="las la-chart-area"></i></div>
                            <p>Select date range and click Load</p>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.smc-content --}}
</div>{{-- /.smc-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  QuantEdge SMC — Vanilla JS (no jQuery)
//  Logic unchanged — only CSS classes updated to match dark theme
// ═══════════════════════════════════════════════════════════════

var SMC_TODAY      = '{{ now()->toDateString() }}';
var SMC_SIGNALS    = '{{ route("quantedge-smc.signals") }}';
var smcCurFilter   = 'ALL';
var smcAllResults  = [];

// ── DOM helpers ───────────────────────────────────────────────

function smcHtml(id, html) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = html;
}
function smcText(id, txt) {
    var el = document.getElementById(id);
    if (el) el.textContent = txt;
}
function smcShow(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = '';
}
function smcHide(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    smcUpdateDateBadge();
    smcLoad();
});

// ── Date helpers ──────────────────────────────────────────────

function smcShiftDate(which, d) {
    var id = which === 'from' ? 'smc-from' : 'smc-to';
    var el = document.getElementById(id);
    var dt = new Date(el.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > SMC_TODAY) return;
    el.value = s;
    smcSyncDates();
}

function smcSyncDates() {
    var fp = document.getElementById('smc-from');
    var tp = document.getElementById('smc-to');
    if (fp.value > SMC_TODAY) fp.value = SMC_TODAY;
    if (tp.value > SMC_TODAY) tp.value = SMC_TODAY;
    if (fp.value > tp.value)  tp.value = fp.value;
    tp.min = fp.value;
    smcUpdateDateBadge();
}

function smcGoToday() {
    document.getElementById('smc-from').value = SMC_TODAY;
    document.getElementById('smc-to').value   = SMC_TODAY;
    smcSyncDates();
    smcLoad();
}

function smcUpdateDateBadge() {
    var from = document.getElementById('smc-from').value;
    var to   = document.getElementById('smc-to').value;
    var el   = document.getElementById('smc-date-badge');
    if (!el) return;
    if (from === SMC_TODAY && to === SMC_TODAY) {
        el.innerHTML = '<span class="smc-live-badge">● Live</span>';
    } else if (from !== to) {
        el.innerHTML = '<span class="smc-range-badge">📅 Range</span>';
    } else {
        el.innerHTML = '<span class="smc-hist-badge">📅 Historical</span>';
    }
}

// ── Symbol dropdown ───────────────────────────────────────────

function smcRebuildSym(syms) {
    var sel  = document.getElementById('smc-sym');
    var prev = sel.value;
    sel.innerHTML = '<option value="ALL">— All Symbols —</option>';
    syms.forEach(function (s) {
        var opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

// ── Main loader ───────────────────────────────────────────────

function smcLoad() {
    var from = document.getElementById('smc-from').value;
    var to   = document.getElementById('smc-to').value;
    var sym  = document.getElementById('smc-sym').value || 'ALL';
    smcUpdateDateBadge();
    smcShowLoading();

    document.getElementById('smc-warn').classList.remove('show');

    var params = new URLSearchParams({ from_date: from, to_date: to, symbol: sym });

    fetch(SMC_SIGNALS + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (res.no_config) {
            document.getElementById('smc-warn').classList.add('show');
            smcText('smc-warn-msg', res.message || '');
            smcEmptyTable();
            return;
        }
        if (!res.success) {
            smcEmptyTable(res.message);
            return;
        }

        if (res.symbols && res.symbols.length) smcRebuildSym(res.symbols);

        smcAllResults = res.results || [];
        smcRenderStats(res.summary);
        smcRenderTable(smcAllResults);
        smcApplyFilter(smcCurFilter);

        smcText('smc-card-info', smcAllResults.length + ' row(s)' + (res.is_range ? ' · range' : ''));
        smcText('smc-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) {
        smcEmptyTable('⚠ ' + err.message);
    });
}

// ── Render table ──────────────────────────────────────────────

function smcRenderTable(rows) {
    if (!rows || !rows.length) { smcEmptyTable('No signals found.'); return; }

    var html = '';
    rows.forEach(function (r, i) {
        var sig    = r.signal || 'NO_TRADE';
        var zebra  = i % 2 === 0 ? 'tr-even' : 'tr-odd';
        var rowCls = zebra
            + (sig === 'BUY'                                       ? ' tr-buy'  : '')
            + (sig === 'SELL'                                      ? ' tr-sell' : '')
            + (sig === 'BUY_PULLBACK' || sig === 'SELL_PULLBACK'   ? ' tr-pb'   : '');

        var sigBadgeMap = {
            BUY:           '<span class="sig sig-buy">↑ BUY</span>',
            SELL:          '<span class="sig sig-sell">↓ SELL</span>',
            BUY_PULLBACK:  '<span class="sig sig-buyp">↩ BUY PB</span>',
            SELL_PULLBACK: '<span class="sig sig-sellp">↩ SELL PB</span>',
            NO_TRADE:      '<span class="sig sig-nt">— NO TRADE</span>',
        };
        var sigBadge = sigBadgeMap[sig] || '<span class="sig sig-nt">—</span>';

        var trendBadgeMap = {
            UPTREND:   '<span class="trend-up">↑ UP</span>',
            DOWNTREND: '<span class="trend-dn">↓ DOWN</span>',
            SIDEWAYS:  '<span class="trend-side">→ SIDE</span>',
        };
        var trendBadge = trendBadgeMap[r.trend] || smcDash();

        var sweepCell = (r.liquidity_sweep_low  ? '<span class="b-yes">L</span> ' : '<span class="b-no">L</span> ')
                      + (r.liquidity_sweep_high ? '<span class="b-wrn">H</span>'  : '<span class="b-no">H</span>');

        var fvgCell = (r.fvg_bullish ? '<span class="b-yes">↑</span> ' : '<span class="b-no">↑</span> ')
                    + (r.fvg_bearish ? '<span class="b-wrn">↓</span>'   : '<span class="b-no">↓</span>');

        var emaVs = smcDash();
        if (r.last_close && r.ema20) {
            emaVs = r.last_close > r.ema20
                ? '<span class="ema-abv">▲ ABV</span>'
                : '<span class="ema-blw">▼ BLW</span>';
        }

        html += '<tr class="' + rowCls + '" data-sig="' + sig + '">'
            + '<td class="c-num">' + (i + 1) + '</td>'
            + '<td style="text-align:left;padding-left:14px;"><span class="c-sym">' + smcEsc(r.symbol) + '</span></td>'
            + '<td><span class="c-date">' + smcEsc(r.analysis_date || '') + '</span></td>'
            + '<td class="c-close">'
            + (r.last_close ? '₹' + smcFmt(r.last_close) : smcDash()) + '</td>'
            + '<td class="sep-signal">' + sigBadge + '</td>'
            + '<td>' + trendBadge + '</td>'
            + '<td class="sep-smc">' + (r.volume_spike ? '<span class="b-yes">✓ YES</span>' : '<span class="b-no">✗</span>') + '</td>'
            + '<td>' + sweepCell + '</td>'
            + '<td>' + fvgCell + '</td>'
            + '<td class="sep-ema" style="color:var(--c-blue);font-weight:700;">'
            + (r.ema20 ? '₹' + smcFmt(r.ema20) : smcDash()) + '</td>'
            + '<td>' + emaVs + '</td>'
            + '</tr>';
    });

    smcHtml('smc-tbody', html);
}

// ── Signal filter ─────────────────────────────────────────────

function smcFilter(sig, btn) {
    smcCurFilter = sig;
    document.querySelectorAll('#smc-fp-wrap .smc-fp').forEach(function (b) {
        b.classList.remove('fp-all','fp-buy','fp-sell','fp-pb','fp-nt');
    });
    var cls = sig === 'ALL'           ? 'fp-all'
            : sig === 'BUY'           ? 'fp-buy'
            : sig === 'SELL'          ? 'fp-sell'
            : sig === 'NO_TRADE'      ? 'fp-nt'
            : 'fp-pb';
    btn.classList.add(cls);
    smcApplyFilter(sig);
}

function smcApplyFilter(sig) {
    document.querySelectorAll('#smc-tbody tr[data-sig]').forEach(function (row) {
        row.style.display = (sig === 'ALL' || row.dataset.sig === sig) ? '' : 'none';
    });
}

// ── Stats ─────────────────────────────────────────────────────

function smcRenderStats(s) {
    smcText('st-total', s.total);
    smcText('st-buy',   s.buy);
    smcText('st-sell',  s.sell);
    smcText('st-pb',    (s.buy_pullback || 0) + (s.sell_pullback || 0));
    smcText('st-nt',    s.no_trade);
    smcShow('smc-stats');
}

// ── Utilities ─────────────────────────────────────────────────

function smcFmt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 });
}
function smcDash() {
    return '<span style="color:rgba(120,123,134,.35);font-size:9px;">—</span>';
}
function smcEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function smcShowLoading() {
    smcHtml('smc-tbody',
        '<tr><td colspan="11">'
        + '<div class="smc-loading"><div class="smc-spinner"></div>'
        + '<div class="smc-loading-text">Running SMC analysis…</div></div>'
        + '</td></tr>'
    );
    smcHide('smc-stats');
}

function smcEmptyTable(msg) {
    smcHtml('smc-tbody',
        '<tr><td colspan="11">'
        + '<div class="smc-empty">'
        + '<div class="smc-empty-icon"><i class="las la-chart-area"></i></div>'
        + '<p>' + smcEsc(msg || 'Select date range and click Load') + '</p>'
        + '</div></td></tr>'
    );
    smcHide('smc-stats');
}
</script>
@endpush