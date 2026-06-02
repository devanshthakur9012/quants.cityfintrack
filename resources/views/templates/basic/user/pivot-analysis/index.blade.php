{{-- FILE: resources/views/themes/{active_theme}/user/pivot-analysis/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — PIVOT ANALYSIS  v2.0
   Dark terminal · Matches homepage design system
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
    /* semantic trading */
    --c-bull:     #26A69A;
    --c-bear:     #EF5350;
    --c-pivot:    #FFA726;
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.pv-wrap {
    font-family: var(--f-sans);
    color: var(--c-text);
    background: var(--c-bg);
}
.pv-wrap * { box-sizing: border-box; }
.pv-wrap a { text-decoration: none; color: inherit; }
.mono { font-family: var(--f-mono); }

@keyframes pvFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.pv-anim { animation: pvFadeUp .5s ease both; }
@keyframes pvSpin  { to { transform: rotate(360deg); } }

/* ══ HERO ═════════════════════════════════════ */
.pv-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.pv-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.pv-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.pv-hero-left { position: relative; z-index: 1; }
.pv-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.pv-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.pv-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.pv-hero h1 span { color: var(--c-lime); }
.pv-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 540px; }

/* Formula pills */
.pv-hero-formulas { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px; }
.pv-pill {
    display: inline-block; padding: 3px 10px; border-radius: 4px;
    font-family: var(--f-mono); font-size: 10px; font-weight: 600;
    letter-spacing: .04em;
}
.pv-pill-pp  { background: rgba(255,167,38,.1);  color: var(--c-amber);  border: 1px solid rgba(255,167,38,.25); }
.pv-pill-s   { background: rgba(38,166,154,.1);  color: var(--c-teal);   border: 1px solid rgba(38,166,154,.25); }
.pv-pill-r   { background: rgba(239,83,80,.08);  color: var(--c-red);    border: 1px solid rgba(239,83,80,.2);   }

/* Hero icon */
.pv-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--c-lime); flex-shrink: 0;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}

@media (max-width: 768px) {
    .pv-hero { flex-direction: column; padding: 24px 18px; }
    .pv-hero-formulas { flex-wrap: wrap; }
}

/* ══ FILTER BAR ═══════════════════════════════ */
.pv-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.pv-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.pv-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.pv-filter-sep {
    width: 1px; height: 26px;
    background: var(--c-border2); flex-shrink: 0;
}

/* Instrument tabs */
.pv-inst-tabs { display: flex; gap: 4px; }
.pv-inst-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 6px;
    border: 1px solid var(--c-border2);
    font-size: 11px; font-weight: 600; letter-spacing: .05em;
    text-transform: uppercase; color: var(--c-muted); cursor: pointer;
    background: transparent; font-family: var(--f-sans);
    transition: all .2s;
}
.pv-inst-tab:hover { color: var(--c-text); border-color: var(--c-border2); }
.pv-inst-tab.on-stock  { background: rgba(38,166,154,.1);  border-color: rgba(38,166,154,.3);  color: var(--c-teal);   }
.pv-inst-tab.on-fut    { background: var(--c-lime-dim);     border-color: rgba(125,255,0,.3);   color: var(--c-lime);   }
.pv-inst-tab.on-option { background: rgba(171,71,188,.1);  border-color: rgba(171,71,188,.3);  color: var(--c-purple); }

/* Symbol select */
.pv-sym-select {
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
.pv-sym-select:focus { border-color: rgba(125,255,0,.45); }

/* Date controls */
.pv-date-wrap { display: flex; align-items: center; gap: 4px; }
.pv-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.pv-date-input:focus { border-color: rgba(125,255,0,.45); }
/* dark calendar icon for webkit */
.pv-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.pv-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.pv-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.pv-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Date / live badges */
.pv-live-badge { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.pv-hist-badge { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Action buttons */
.pv-load-btn {
    background: var(--c-lime); color: #000; border: none; border-radius: 7px;
    padding: 7px 18px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(125,255,0,.2);
    display: inline-flex; align-items: center; gap: 6px;
}
.pv-load-btn:hover { background: #8FFF1A; box-shadow: 0 0 22px rgba(125,255,0,.35); transform: translateY(-1px); }
.pv-auto-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: var(--f-mono);
    transition: all .2s; letter-spacing: .05em;
}
.pv-auto-btn.on { border-color: rgba(38,166,154,.35); background: rgba(38,166,154,.1); color: var(--c-teal); }
.pv-auto-btn:hover:not(.on) { border-color: var(--c-border2); color: var(--c-text); }

.pv-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.pv-info-text { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.pv-last-upd  { font-size: 10px; color: rgba(120,123,134,.5); font-family: var(--f-mono); }

@media (max-width: 768px) {
    .pv-filter-bar { padding: 0 16px; }
    .pv-filter-inner { gap: 8px; }
    .pv-filter-right { margin-left: 0; width: 100%; }
}

/* ══ CONTENT ══════════════════════════════════ */
.pv-content { padding: 24px 32px 64px; }
@media (max-width: 768px) { .pv-content { padding: 16px 12px 48px; } }

/* Config warning */
.pv-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.pv-warn.show { display: flex; }
.pv-warn i { font-size: 18px; flex-shrink: 0; }
.pv-warn strong { color: #fff; }

/* ══ TABLE CARD ═══════════════════════════════ */
.pv-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    margin-bottom: 20px; position: relative;
}
.pv-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.pv-card-header {
    padding: 13px 18px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    background: rgba(0,0,0,.2);
}
.pv-card-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: var(--c-text); display: flex; align-items: center; gap: 8px;
}
.pv-card-subtitle { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }

/* Instrument label */
.pv-inst-label {
    display: inline-block; padding: 3px 10px; border-radius: 100px;
    font-size: 10px; font-weight: 700; letter-spacing: .08em;
    font-family: var(--f-mono);
}
.pv-il-stock  { background: rgba(38,166,154,.1);  color: var(--c-teal);   border: 1px solid rgba(38,166,154,.25); }
.pv-il-fut    { background: var(--c-lime-dim);     color: var(--c-lime);   border: 1px solid rgba(125,255,0,.25); }
.pv-il-option { background: rgba(171,71,188,.1);  color: var(--c-purple); border: 1px solid rgba(171,71,188,.25); }

/* ══ TABLES ═══════════════════════════════════ */
.pv-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.pv-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); }
.pv-table.sf-table  { min-width: 1100px; }
.pv-table.opt-table { min-width: 1800px; }

/* Header rows */
.pv-table thead tr.th-group th {
    padding: 8px 10px 4px; text-align: center;
    font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    background: var(--c-panel); border-bottom: none; white-space: nowrap;
}
.pv-table thead tr.th-cols th {
    padding: 4px 10px 8px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}

/* Column group header colors */
.g-meta   { color: var(--c-muted) !important; }
.g-ohlc   { color: var(--c-blue)   !important; }
.g-pivot  { color: var(--c-amber)  !important; }
.g-signal { color: var(--c-teal)   !important; }
.g-ce     { color: var(--c-teal)   !important; }
.g-pe     { color: var(--c-red)    !important; }

/* Separator borders */
.sep-ohlc   { border-left: 1px solid rgba(0,184,212,.15)  !important; }
.sep-pivot  { border-left: 1px solid rgba(255,167,38,.15) !important; }
.sep-signal { border-left: 1px solid rgba(38,166,154,.15) !important; }
.sep-ce     { border-left: 1px solid rgba(38,166,154,.15) !important; }
.sep-pe     { border-left: 1px solid rgba(239,83,80,.15)  !important; }

/* Body cells */
.pv-table tbody td {
    padding: 7px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.pv-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.tr-even { background: var(--c-surface); }
.tr-odd  { background: rgba(0,0,0,.15); }
.tr-breakout  { background: rgba(38,166,154,.04)  !important; }
.tr-breakdown { background: rgba(239,83,80,.04)   !important; }

/* Cell value styles */
.c-num  { font-size: 9px; color: rgba(120,123,134,.35); }
.c-time { font-size: 12px; font-weight: 700; color: var(--c-lime); }
.c-sym  { font-size: 11px; font-weight: 700; color: var(--c-blue); }
.c-sym small { display: block; font-size: 8px; color: var(--c-muted); font-weight: 400; margin-top: 1px; }
.c-o    { color: var(--c-muted); font-size: 10px; }
.c-h    { color: var(--c-red);   font-weight: 700; }
.c-l    { color: var(--c-teal);  font-weight: 700; }
.c-c    { color: var(--c-blue);  font-weight: 700; }
.c-vol  { font-size: 9px; color: rgba(120,123,134,.4); }
.c-pp   { color: var(--c-amber); font-weight: 700; }
.c-r1   { color: var(--c-red);   font-weight: 700; }
.c-r2   { color: rgba(239,83,80,.7); font-size: 9px; }
.c-s1   { color: var(--c-teal);  font-weight: 700; }
.c-s2   { color: rgba(38,166,154,.7); font-size: 9px; }
.c-oi   { font-size: 9px; color: var(--c-muted); }
.c-atm  { font-size: 10px; color: var(--c-amber); font-weight: 700; }

/* ── SIGNAL BADGES ──────────────────────────── */
.sig {
    display: inline-block; border-radius: 4px;
    padding: 3px 8px; font-family: var(--f-sans); font-size: 10px;
    font-weight: 700; letter-spacing: .04em; white-space: nowrap;
}
.sig-bull-strong { background: rgba(38,166,154,.15); color: #4DB6AC; border: 1px solid rgba(38,166,154,.3);  }
.sig-bull-mod    { background: rgba(38,166,154,.08); color: #80CBC4; border: 1px solid rgba(38,166,154,.18); }
.sig-bull-weak   { background: rgba(38,166,154,.05); color: #B2DFDB; border: 1px solid rgba(38,166,154,.12); }
.sig-bear-strong { background: rgba(239,83,80,.12);  color: #EF9A9A; border: 1px solid rgba(239,83,80,.28);  }
.sig-bear-mod    { background: rgba(239,83,80,.07);  color: #FFCDD2; border: 1px solid rgba(239,83,80,.18);  }
.sig-bear-weak   { background: rgba(239,83,80,.04);  color: rgba(239,83,80,.7); border: 1px solid rgba(239,83,80,.1); }
.sig-neutral     { background: var(--c-panel); color: var(--c-muted); border: 1px solid var(--c-border2); }

/* Match pills */
.mp-yes { display:inline-block; background:rgba(38,166,154,.12); color:#4DB6AC; border:1px solid rgba(38,166,154,.25); border-radius:4px; padding:2px 7px; font-size:9px; font-weight:700; }
.mp-no  { display:inline-block; background:var(--c-panel); color:rgba(120,123,134,.4); border:1px solid var(--c-border); border-radius:4px; padding:2px 7px; font-size:9px; }
.mp-pp  { display:inline-block; background:rgba(255,167,38,.1); color:var(--c-amber); border:1px solid rgba(255,167,38,.25); border-radius:4px; padding:2px 7px; font-size:9px; font-weight:700; }

/* ── LOADING / EMPTY ────────────────────────── */
.pv-loading {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 56px 20px;
}
.pv-spinner {
    width: 32px; height: 32px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-lime);
    border-radius: 50%;
    animation: pvSpin .9s linear infinite;
}
.pv-loading-text { color: var(--c-muted); margin-top: 12px; font-size: 12px; font-family: var(--f-mono); }

.pv-empty {
    text-align: center; padding: 52px 20px; color: var(--c-muted);
}
.pv-empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 22px;
}
.pv-empty p { font-size: 12px; font-family: var(--f-mono); margin-top: 4px; }
</style>

<div class="pv-wrap">

{{-- ══ HERO ══ --}}
<div class="pv-hero pv-anim">
    <div class="pv-hero-left">
        <div class="pv-hero-eyebrow">Options Analytics</div>
        <h1>Pivot Point <span>Analysis</span></h1>
        <p>Real-time pivot levels for Stock EQ, Futures, and ATM Options — calculated on live candle data during market hours.</p>
        <div class="pv-hero-formulas">
            <span class="pv-pill pv-pill-pp">PP = (H+L+C) / 3</span>
            <span class="pv-pill pv-pill-s">S1 = (2×PP) − H</span>
            <span class="pv-pill pv-pill-s">S2 = PP − (H−L)</span>
            <span class="pv-pill pv-pill-r">R1 = (2×PP) − L</span>
            <span class="pv-pill pv-pill-r">R2 = PP + (H−L)</span>
        </div>
    </div>
    <div class="pv-hero-icon">
        <i class="las la-chart-bar"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="pv-filter-bar">
    <div class="pv-filter-inner">

        <span class="pv-filter-label">Type</span>
        <div class="pv-inst-tabs">
            <button class="pv-inst-tab on-stock" data-inst="stock"
                    onclick="pvSetInst('stock',this)">
                <i class="las la-chart-line"></i> Stock EQ
            </button>
            <button class="pv-inst-tab" data-inst="fut"
                    onclick="pvSetInst('fut',this)">
                <i class="las la-fire"></i> Futures
            </button>
            <button class="pv-inst-tab" data-inst="option"
                    onclick="pvSetInst('option',this)">
                <i class="las la-layer-group"></i> Options ATM
            </button>
        </div>

        <div class="pv-filter-sep"></div>

        <span class="pv-filter-label">Symbol</span>
        <select id="pv-sym" class="pv-sym-select" onchange="pvLoad()">
            <option value="ALL">— All —</option>
        </select>

        <div class="pv-filter-sep"></div>

        <span class="pv-filter-label">Date</span>
        <div class="pv-date-wrap">
            <button class="pv-date-nav" onclick="pvShiftDate(-1)">‹</button>
            <input type="date" id="pv-date" class="pv-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="pvLoad()">
            <button class="pv-date-nav" onclick="pvShiftDate(1)">›</button>
            <button class="pv-date-nav pv-today-btn" onclick="pvToday()">TODAY</button>
            <span id="pv-date-badge"></span>
        </div>

        <button class="pv-load-btn" onclick="pvLoad()">
            <i class="las la-sync-alt"></i> Load
        </button>
        <button class="pv-auto-btn" id="pv-auto-btn" onclick="pvToggleAuto()">
            ▶ Auto
        </button>

        <div class="pv-filter-right">
            <span class="pv-info-text" id="pv-info"></span>
            <span class="pv-last-upd"  id="pv-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="pv-content">

    {{-- Config warning --}}
    <div class="pv-warn" id="pv-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="pv-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- ── STOCK / FUT TABLE ── --}}
    <div id="pv-sf-wrap">
        <div class="pv-card">
            <div class="pv-card-header">
                <div class="pv-card-title">
                    <span class="pv-inst-label pv-il-stock" id="pv-il">STOCK EQ</span>
                    Pivot Point Signals
                </div>
                <span class="pv-card-subtitle" id="pv-subtitle">Loading…</span>
            </div>
            <div class="pv-table-scroll">
                <table class="pv-table sf-table" id="pv-sf-table">
                    <thead>
                        <tr class="th-group">
                            <th colspan="3" class="g-meta">Info</th>
                            <th colspan="5" class="g-ohlc  sep-ohlc">OHLC + Volume</th>
                            <th colspan="5" class="g-pivot sep-pivot">▲ Pivot Levels</th>
                            <th colspan="4" class="g-signal sep-signal">Signal</th>
                        </tr>
                        <tr class="th-cols">
                            <th class="g-meta">#</th>
                            <th class="g-meta">Time</th>
                            <th class="g-meta">Symbol</th>
                            <th class="g-ohlc sep-ohlc">Open</th>
                            <th class="g-ohlc">High</th>
                            <th class="g-ohlc">Low</th>
                            <th class="g-ohlc">Close</th>
                            <th class="g-ohlc">Volume</th>
                            <th class="g-pivot sep-pivot">PP</th>
                            <th class="g-pivot" style="color:var(--c-teal)!important;">S1</th>
                            <th class="g-pivot" style="color:rgba(38,166,154,.6)!important;">S2</th>
                            <th class="g-pivot" style="color:var(--c-red)!important;">R1</th>
                            <th class="g-pivot" style="color:rgba(239,83,80,.6)!important;">R2</th>
                            <th class="g-signal sep-signal">Signal</th>
                            <th class="g-signal">S1 Touch</th>
                            <th class="g-signal">R1 Touch</th>
                            <th class="g-signal" id="pv-oi-hdr" style="display:none;">OI</th>
                            <th class="g-signal">PP Cross</th>
                        </tr>
                    </thead>
                    <tbody id="pv-sf-body">
                        <tr><td colspan="17">
                            <div class="pv-loading">
                                <div class="pv-spinner"></div>
                                <div class="pv-loading-text">Detecting last available date…</div>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── OPTION TABLE ── --}}
    <div id="pv-opt-wrap" style="display:none;">
        <div class="pv-card">
            <div class="pv-card-header">
                <div class="pv-card-title">
                    <span class="pv-inst-label pv-il-option">OPTIONS</span>
                    ATM CE / PE Pivot Signals
                </div>
                <span class="pv-card-subtitle" id="pv-opt-subtitle">ATM Strike</span>
            </div>
            <div class="pv-table-scroll">
                <table class="pv-table opt-table">
                    <thead>
                        <tr class="th-group">
                            <th colspan="4" class="g-meta">Info</th>
                            <th colspan="8" class="g-ce sep-ce">▲ CE — ATM Call</th>
                            <th colspan="8" class="g-pe sep-pe">▼ PE — ATM Put</th>
                        </tr>
                        <tr class="th-cols">
                            <th class="g-meta">#</th>
                            <th class="g-meta">Time</th>
                            <th class="g-meta">Symbol</th>
                            <th class="g-meta">ATM Strike</th>
                            <th class="g-ce sep-ce">Open</th>
                            <th class="g-ce">High</th>
                            <th class="g-ce">Low</th>
                            <th class="g-ce">Close</th>
                            <th class="g-ce" style="color:var(--c-amber)!important;">PP</th>
                            <th class="g-ce" style="color:var(--c-teal)!important;">S1</th>
                            <th class="g-ce" style="color:var(--c-red)!important;">R1</th>
                            <th class="g-ce">Signal</th>
                            <th class="g-pe sep-pe">Open</th>
                            <th class="g-pe">High</th>
                            <th class="g-pe">Low</th>
                            <th class="g-pe">Close</th>
                            <th class="g-pe" style="color:var(--c-amber)!important;">PP</th>
                            <th class="g-pe" style="color:var(--c-teal)!important;">S1</th>
                            <th class="g-pe" style="color:var(--c-red)!important;">R1</th>
                            <th class="g-pe">Signal</th>
                        </tr>
                    </thead>
                    <tbody id="pv-opt-body">
                        <tr><td colspan="20">
                            <div class="pv-loading">
                                <div class="pv-spinner"></div>
                                <div class="pv-loading-text">Detecting last available date…</div>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>{{-- /.pv-content --}}
</div>{{-- /.pv-wrap --}}

@endsection

@push('script')
<script>
/* ═══════════════════════════════════════════════════════════════
   PIVOT ANALYSIS — JS  (all logic identical to original)
═══════════════════════════════════════════════════════════════ */

var PV_TODAY   = '{{ now()->toDateString() }}';
var PV_ROUTES  = {
    stock    : '{{ route("pivot-analysis.stock.signals") }}',
    fut      : '{{ route("pivot-analysis.fut.signals") }}',
    option   : '{{ route("pivot-analysis.option.signals") }}',
    lastDate : '{{ route("pivot-analysis.last.date") }}'
};
var pvInst     = 'stock';
var pvTimer    = null;
var pvSymCache = {};

function pvHtml(id, html) { var el = document.getElementById(id); if (el) el.innerHTML = html; }
function pvText(id, txt)  { var el = document.getElementById(id); if (el) el.textContent = txt; }

/* ── BOOT ── */
document.addEventListener('DOMContentLoaded', function () {
    pvResolveLastDateAndLoad();
});

function pvResolveLastDateAndLoad() {
    fetch(PV_ROUTES.lastDate + '?instrument=' + pvInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) document.getElementById('pv-date').value = res.last_date;
        pvLoad();
    })
    .catch(function () { pvLoad(); });
}

/* ── INSTRUMENT SWITCHER ── */
function pvSetInst(inst, btn) {
    pvInst = inst;
    document.querySelectorAll('.pv-inst-tab').forEach(function(b){ b.className = 'pv-inst-tab'; });
    btn.classList.add('on-' + inst);

    var isOpt = inst === 'option';
    document.getElementById('pv-sf-wrap').style.display  = isOpt ? 'none' : '';
    document.getElementById('pv-opt-wrap').style.display = isOpt ? ''     : 'none';

    var oiHdr = document.getElementById('pv-oi-hdr');
    if (oiHdr) oiHdr.style.display = inst === 'fut' ? '' : 'none';

    var il = document.getElementById('pv-il');
    if (il) {
        il.className   = 'pv-inst-label pv-il-' + inst;
        il.textContent = { stock:'STOCK EQ', fut:'FUTURES', option:'OPTIONS' }[inst];
    }

    var cacheKey = inst;
    if (pvSymCache[cacheKey] && pvSymCache[cacheKey].length) pvRebuildSym(pvSymCache[cacheKey]);
    else pvRebuildSym([]);

    pvResolveLastDateAndLoad();
}

/* ── DATE ── */
function pvGetDate() { return document.getElementById('pv-date').value; }
function pvGetSym()  { return document.getElementById('pv-sym').value; }

function pvShiftDate(d) {
    var picker = document.getElementById('pv-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > PV_TODAY) return;
    picker.value = s;
    pvLoad();
}

function pvToday() {
    document.getElementById('pv-date').value = PV_TODAY;
    pvLoad();
}

function pvUpdateDateBadge(isToday) {
    var el = document.getElementById('pv-date-badge');
    el.innerHTML = isToday
        ? '<span class="pv-live-badge">● Live</span>'
        : '<span class="pv-hist-badge">📅 Historical</span>';
}

/* ── SYMBOL DROPDOWN ── */
function pvRebuildSym(symbols) {
    var sel  = document.getElementById('pv-sym');
    var prev = sel.value;
    sel.innerHTML = '<option value="ALL">— All Symbols —</option>';
    symbols.forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

/* ── AUTO REFRESH ── */
function pvToggleAuto() {
    var btn = document.getElementById('pv-auto-btn');
    if (pvTimer) {
        clearInterval(pvTimer); pvTimer = null;
        btn.textContent = '▶ Auto';
        btn.classList.remove('on');
    } else {
        pvTimer = setInterval(pvLoad, 15000);
        btn.textContent = '■ Stop';
        btn.classList.add('on');
        pvLoad();
    }
}

/* ── MAIN LOADER ── */
function pvLoad() {
    var date   = pvGetDate();
    var sym    = pvGetSym();
    var isOpt  = pvInst === 'option';
    var colsNm = isOpt ? 20 : 17;

    if (date !== PV_TODAY && pvTimer) {
        clearInterval(pvTimer); pvTimer = null;
        document.getElementById('pv-auto-btn').textContent = '▶ Auto';
        document.getElementById('pv-auto-btn').classList.remove('on');
    }

    document.getElementById('pv-warn').classList.remove('show');

    var loadTr = '<tr><td colspan="' + colsNm + '">'
        + '<div class="pv-loading"><div class="pv-spinner"></div>'
        + '<div class="pv-loading-text">Fetching pivot data for ' + date + '…</div></div>'
        + '</td></tr>';
    pvHtml(isOpt ? 'pv-opt-body' : 'pv-sf-body', loadTr);

    var params = new URLSearchParams({ symbol: sym, date: date });
    fetch(PV_ROUTES[pvInst] + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function(res) {
        pvUpdateDateBadge(res.is_today);

        if (res.no_config) {
            document.getElementById('pv-warn').classList.add('show');
            pvText('pv-warn-msg', res.message || '');
            pvHtml(isOpt ? 'pv-opt-body' : 'pv-sf-body', pvEmptyHtml(colsNm));
            return;
        }

        if (res.available_symbols && res.available_symbols.length) {
            pvSymCache[pvInst] = res.available_symbols;
            pvRebuildSym(res.available_symbols);
        }

        if (!res.success || !res.data || !res.data.length) {
            pvHtml(isOpt ? 'pv-opt-body' : 'pv-sf-body', pvEmptyHtml(colsNm, res.message));
            pvText('pv-info', '');
            return;
        }

        var total = res.data.reduce(function(a,d){ return a + (d.total_candles||0); }, 0);
        pvText('pv-info',     total + ' candles · ' + res.data.length + ' symbol(s)');
        pvText('pv-subtitle', date + ' · ' + (res.data[0].mode === 'detail' ? 'Full Day' : 'Latest'));
        pvText('pv-upd',      'Updated ' + new Date().toLocaleTimeString());

        if (isOpt) pvRenderOption(res.data);
        else       pvRenderSF(res.data);
    })
    .catch(function(err) {
        pvHtml(isOpt ? 'pv-opt-body' : 'pv-sf-body', pvEmptyHtml(colsNm, '⚠ ' + err.message));
    });
}

/* ── RENDERERS (identical logic, only CSS class names updated) ── */
function pvRenderSF(data) {
    var isFut = pvInst === 'fut';
    var html  = '';
    var n     = 1;

    data.forEach(function(d) {
        (d.signals || []).forEach(function(s, i) {
            var rowCls = pvRowCls(s.bias, s.signal);
            var zebra  = i % 2 === 0 ? 'tr-even' : 'tr-odd';

            html += '<tr class="' + rowCls + ' ' + zebra + '">'
                + '<td class="c-num">'  + n++ + '</td>'
                + '<td class="c-time">' + s.time + '</td>'
                + '<td class="c-sym">'  + esc(d.symbol)
                    + (d.expiry ? '<small>' + d.expiry + '</small>' : '') + '</td>'
                + '<td class="c-o sep-ohlc">₹' + fmt(s.open)  + '</td>'
                + '<td class="c-h">₹'           + fmt(s.high)  + '</td>'
                + '<td class="c-l">₹'           + fmt(s.low)   + '</td>'
                + '<td class="c-c">₹'           + fmt(s.close) + '</td>'
                + '<td class="c-vol">'           + fmtInt(s.volume) + '</td>'
                + '<td class="c-pp  sep-pivot">₹' + fmt(s.PP) + '</td>'
                + '<td class="c-s1">₹'            + fmt(s.S1) + '</td>'
                + '<td class="c-s2">₹'            + fmt(s.S2) + '</td>'
                + '<td class="c-r1">₹'            + fmt(s.R1) + '</td>'
                + '<td class="c-r2">₹'            + fmt(s.R2) + '</td>'
                + '<td class="sep-signal">'  + pvSigBadge(s.bias, s.signal, s.strength) + '</td>'
                + '<td>'                     + pvMatchPill(s.s1_match) + '</td>'
                + '<td>'                     + pvMatchPill(s.r1_match) + '</td>'
                + (isFut ? '<td class="c-oi">' + fmtInt(s.oi) + '</td>' : '')
                + '<td>'                     + pvPPCross(s.pp_cross) + '</td>'
                + '</tr>';
        });
    });

    pvHtml('pv-sf-body', html || pvEmptyHtml(17));
}

function pvRenderOption(data) {
    var html = '';
    var n    = 1;

    data.forEach(function(d) {
        var ceMap = {};
        var peMap = {};
        (d.ce_signals || []).forEach(function(s){ ceMap[s.time] = s; });
        (d.pe_signals || []).forEach(function(s){ peMap[s.time] = s; });

        var times = Object.keys(Object.assign({}, ceMap, peMap)).sort();
        times.forEach(function(t, i) {
            var ce    = ceMap[t] || null;
            var pe    = peMap[t] || null;
            var zebra = i % 2 === 0 ? 'tr-even' : 'tr-odd';

            var ceCells = ce
                ? '<td class="c-o sep-ce">₹' + fmt(ce.open)  + '</td>'
                + '<td class="c-h">₹'          + fmt(ce.high)  + '</td>'
                + '<td class="c-l">₹'          + fmt(ce.low)   + '</td>'
                + '<td class="c-c">₹'          + fmt(ce.close) + '</td>'
                + '<td class="c-pp">₹'         + fmt(ce.PP)    + '</td>'
                + '<td class="c-s1">₹'         + fmt(ce.S1)    + '</td>'
                + '<td class="c-r1">₹'         + fmt(ce.R1)    + '</td>'
                + '<td>'                        + pvSigBadge(ce.bias, ce.signal, ce.strength) + '</td>'
                : '<td colspan="8" class="sep-ce" style="color:rgba(120,123,134,.3);font-size:9px;">— no CE data —</td>';

            var peCells = pe
                ? '<td class="c-o sep-pe">₹' + fmt(pe.open)  + '</td>'
                + '<td class="c-h">₹'          + fmt(pe.high)  + '</td>'
                + '<td class="c-l">₹'          + fmt(pe.low)   + '</td>'
                + '<td class="c-c">₹'          + fmt(pe.close) + '</td>'
                + '<td class="c-pp">₹'         + fmt(pe.PP)    + '</td>'
                + '<td class="c-s1">₹'         + fmt(pe.S1)    + '</td>'
                + '<td class="c-r1">₹'         + fmt(pe.R1)    + '</td>'
                + '<td>'                        + pvSigBadge(pe.bias, pe.signal, pe.strength) + '</td>'
                : '<td colspan="8" class="sep-pe" style="color:rgba(120,123,134,.3);font-size:9px;">— no PE data —</td>';

            html += '<tr class="' + zebra + '">'
                + '<td class="c-num">'  + n++ + '</td>'
                + '<td class="c-time">' + t + '</td>'
                + '<td class="c-sym">'  + esc(d.symbol)
                    + (d.expiry ? '<small>' + d.expiry + '</small>' : '') + '</td>'
                + '<td class="c-atm">₹' + fmtInt(d.atm_strike) + '</td>'
                + ceCells + peCells + '</tr>';
        });
    });

    pvHtml('pv-opt-body', html || pvEmptyHtml(20));
}

/* ── BADGE HELPERS (identical logic) ── */
function pvSigBadge(bias, label, strength) {
    if (!bias || bias === 'NEUTRAL')
        return '<span class="sig sig-neutral">— ' + (label || 'At Pivot') + '</span>';
    if (bias === 'BULLISH') {
        if (strength === 'STRONG')   return '<span class="sig sig-bull-strong">▲ ' + label + '</span>';
        if (strength === 'MODERATE') return '<span class="sig sig-bull-mod">↑ '    + label + '</span>';
        return                              '<span class="sig sig-bull-weak">↑ '    + label + '</span>';
    }
    if (bias === 'BEARISH') {
        if (strength === 'STRONG')   return '<span class="sig sig-bear-strong">▼ ' + label + '</span>';
        if (strength === 'MODERATE') return '<span class="sig sig-bear-mod">↓ '    + label + '</span>';
        return                              '<span class="sig sig-bear-weak">↓ '    + label + '</span>';
    }
    return '<span class="sig sig-neutral">—</span>';
}

function pvRowCls(bias, label) {
    if (!label) return '';
    if (label === 'Above R1' || label === 'Above R2') return 'tr-breakout';
    if (label === 'Below S1' || label === 'Below S2') return 'tr-breakdown';
    return '';
}
function pvMatchPill(v) {
    if (v === null || v === undefined) return '<span style="color:rgba(120,123,134,.3);font-size:9px;">—</span>';
    return v ? '<span class="mp-yes">✓ YES</span>' : '<span class="mp-no">✗ NO</span>';
}
function pvPPCross(v) {
    return v ? '<span class="mp-pp">⟷ CROSS</span>' : '<span style="color:rgba(120,123,134,.3);font-size:9px;">—</span>';
}

/* ── NUMBER FORMATTERS (identical) ── */
function fmt(v) {
    if (v == null || v === '') return '—';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 });
}
function fmtInt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits:0 });
}
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── EMPTY HTML ── */
function pvEmptyHtml(cols, msg) {
    return '<tr><td colspan="' + cols + '">'
        + '<div class="pv-empty">'
        + '<div class="pv-empty-icon"><i class="las la-chart-area"></i></div>'
        + '<p>' + (msg || 'No pivot data found for this date / symbol.') + '</p>'
        + '</div></td></tr>';
}
</script>
@endpush