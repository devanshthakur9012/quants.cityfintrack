{{-- FILE: resources/views/themes/{active_theme}/user/momentum-breakout/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — MOMENTUM BREAKOUT SCANNER  v2.0
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
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.mb-wrap {
    font-family: var(--f-sans);
    color: var(--c-text);
    background: var(--c-bg);
}
.mb-wrap * { box-sizing: border-box; }
.mb-wrap a { text-decoration: none; color: inherit; }
.mono { font-family: var(--f-mono); }

@keyframes mbFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.mb-anim    { animation: mbFadeUp .5s ease both; }
.mb-anim.d1 { animation-delay: .08s; }
.mb-anim.d2 { animation-delay: .16s; }
@keyframes mbSpin { to { transform: rotate(360deg); } }

/* ══ HERO ═════════════════════════════════════ */
.mb-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.mb-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.mb-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.mb-hero-left { position: relative; z-index: 1; }
.mb-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.mb-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.mb-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.mb-hero h1 span { color: var(--c-lime); }
.mb-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 580px; }
.mb-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--c-lime); flex-shrink: 0;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}
@media (max-width: 768px) {
    .mb-hero { flex-direction: column; padding: 24px 18px; }
    .mb-hero-icon { display: none; }
}

/* ══ FILTER BAR ═══════════════════════════════ */
.mb-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.mb-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.mb-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.mb-sep {
    width: 1px; height: 26px;
    background: var(--c-border2); flex-shrink: 0;
}

/* Instrument tabs */
.mb-inst-tabs { display: flex; gap: 4px; }
.mb-inst-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 6px;
    border: 1px solid var(--c-border2);
    font-size: 11px; font-weight: 600; letter-spacing: .05em;
    text-transform: uppercase; color: var(--c-muted); cursor: pointer;
    background: transparent; font-family: var(--f-sans);
    transition: all .2s; white-space: nowrap;
}
.mb-inst-tab:hover { color: var(--c-text); border-color: var(--c-border2); }
.mb-inst-tab.on-stock  { background: rgba(38,166,154,.1);  border-color: rgba(38,166,154,.3);  color: var(--c-teal);   }
.mb-inst-tab.on-fut    { background: var(--c-lime-dim);     border-color: rgba(125,255,0,.3);   color: var(--c-lime);   }
.mb-inst-tab.on-option { background: rgba(171,71,188,.1);  border-color: rgba(171,71,188,.3);  color: var(--c-purple); }

/* Symbol select */
.mb-sym-select {
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
.mb-sym-select:focus { border-color: rgba(125,255,0,.45); }

/* Date controls */
.mb-date-wrap { display: flex; align-items: center; gap: 4px; }
.mb-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.mb-date-input:focus { border-color: rgba(125,255,0,.45); }
.mb-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.mb-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.mb-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.mb-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Date badges */
.mb-live-badge { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.mb-hist-badge { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Move % select */
.mb-threshold-select {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 26px 6px 11px;
    font-size: 12px; font-weight: 600; color: var(--c-lime);
    font-family: var(--f-mono);
    appearance: none; cursor: pointer; outline: none; min-width: 80px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 8px center;
    transition: border-color .2s;
}
.mb-threshold-select:focus { border-color: rgba(125,255,0,.45); }
.mb-threshold-select option { background: var(--c-panel); color: var(--c-text); }

/* Show No-Trade checkbox */
.mb-nt-wrap {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--c-muted); font-weight: 600;
    cursor: pointer; white-space: nowrap;
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 12px;
    transition: all .2s;
}
.mb-nt-wrap:hover { border-color: rgba(125,255,0,.3); color: var(--c-text); }
.mb-nt-wrap input { accent-color: var(--c-lime); cursor: pointer; }

/* Buttons */
.mb-scan-btn {
    background: var(--c-lime); color: #000; border: none; border-radius: 7px;
    padding: 7px 20px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(125,255,0,.2);
    display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
}
.mb-scan-btn:hover { background: #8FFF1A; box-shadow: 0 0 22px rgba(125,255,0,.35); transform: translateY(-1px); }
.mb-reset-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: var(--f-sans);
    transition: all .2s;
}
.mb-reset-btn:hover { color: var(--c-text); border-color: var(--c-border2); }

.mb-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.mb-info-text { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.mb-upd-text  { font-size: 10px; color: rgba(120,123,134,.45); font-family: var(--f-mono); }

@media (max-width: 768px) {
    .mb-filter-bar { padding: 0 16px; }
    .mb-filter-inner { gap: 8px; }
    .mb-filter-right { margin-left: 0; width: 100%; }
}

/* ══ CONTENT ══════════════════════════════════ */
.mb-content { padding: 24px 32px 64px; }
@media (max-width: 768px) { .mb-content { padding: 16px 12px 48px; } }

/* Config warning */
.mb-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.mb-warn.show { display: flex; }
.mb-warn i { font-size: 18px; flex-shrink: 0; }
.mb-warn strong { color: #fff; }

/* ══ STATS ROW ════════════════════════════════ */
.mb-stats {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 12px; margin-bottom: 20px;
}
@media (max-width: 768px) { .mb-stats { grid-template-columns: repeat(2, 1fr); } }

.mb-stat-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; padding: 16px 18px;
    position: relative; overflow: hidden;
    transition: border-color .25s, transform .2s;
}
/* Left accent stripe */
.mb-stat-card::after {
    content: '';
    position: absolute; top: 12px; bottom: 12px; left: 0; width: 2px;
    border-radius: 0 2px 2px 0;
}
.mb-stat-card.s-total::after { background: var(--c-blue);   }
.mb-stat-card.s-ce::after    { background: var(--c-teal);   }
.mb-stat-card.s-pe::after    { background: var(--c-red);    }
.mb-stat-card.s-nt::after    { background: var(--c-muted);  }
.mb-stat-card:hover { border-color: var(--c-border2); transform: translateY(-2px); }

.mb-stat-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--c-muted); margin-bottom: 6px;
    font-family: var(--f-mono);
}
.mb-stat-val {
    font-family: var(--f-display); font-size: 26px; font-weight: 800; color: #fff;
}
.s-ce .mb-stat-val { color: #80CBC4; }
.s-pe .mb-stat-val { color: #EF9A9A; }
.s-nt .mb-stat-val { color: var(--c-muted); }

/* ══ TABLE CARD ═══════════════════════════════ */
.mb-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    position: relative;
}
.mb-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.mb-card-header {
    padding: 13px 18px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    background: rgba(0,0,0,.2);
}
.mb-card-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: var(--c-text); display: flex; align-items: center; gap: 8px;
}
.mb-card-subtitle { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }

/* Instrument label */
.mb-inst-label {
    display: inline-block; padding: 3px 10px; border-radius: 100px;
    font-size: 10px; font-weight: 700; letter-spacing: .08em; font-family: var(--f-mono);
}
.mb-il-stock  { background: rgba(38,166,154,.1);  color: var(--c-teal);   border: 1px solid rgba(38,166,154,.25); }
.mb-il-fut    { background: var(--c-lime-dim);     color: var(--c-lime);   border: 1px solid rgba(125,255,0,.25); }
.mb-il-option { background: rgba(171,71,188,.1);  color: var(--c-purple); border: 1px solid rgba(171,71,188,.25); }

/* ══ TABLE ════════════════════════════════════ */
.mb-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.mb-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 860px; }

.mb-table thead tr.th-group th {
    padding: 8px 10px 4px; text-align: center;
    font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    background: var(--c-panel); border-bottom: none; white-space: nowrap;
}
.mb-table thead tr.th-cols th {
    padding: 4px 10px 8px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}
.g-info   { color: var(--c-blue)  !important; }
.g-signal { color: var(--c-amber) !important; }
.sep-signal { border-left: 1px solid rgba(255,167,38,.15) !important; }

.mb-table tbody td {
    padding: 8px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.mb-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.tr-even { background: var(--c-surface); }
.tr-odd  { background: rgba(0,0,0,.15); }
.tr-ce   { background: rgba(38,166,154,.04)  !important; }
.tr-pe   { background: rgba(239,83,80,.04)   !important; }
.tr-nt   { background: rgba(0,0,0,.2)        !important; opacity: .6; }

/* Cell styles */
.c-num  { font-size: 9px; color: rgba(120,123,134,.35); }
.c-date { font-size: 11px; font-weight: 700; color: var(--c-lime); }
.c-sym  { font-size: 12px; font-weight: 800; color: var(--c-blue); }
.c-sym small { display: block; font-size: 8px; color: var(--c-muted); font-weight: 400; margin-top: 1px; }
.c-open { color: var(--c-muted); font-weight: 600; }
.c-time { color: var(--c-amber); font-weight: 700; }
.c-px   { color: var(--c-text); font-weight: 700; }
.c-dh   { color: #EF9A9A; font-weight: 600; }
.c-dl   { color: #80CBC4; font-weight: 600; }

/* Signal badges */
.sig-ce {
    display: inline-block; background: rgba(38,166,154,.12); color: #4DB6AC;
    border: 1px solid rgba(38,166,154,.3); border-radius: 5px;
    padding: 3px 10px; font-family: var(--f-sans); font-size: 10px; font-weight: 800;
}
.sig-pe {
    display: inline-block; background: rgba(239,83,80,.1); color: #EF9A9A;
    border: 1px solid rgba(239,83,80,.3); border-radius: 5px;
    padding: 3px 10px; font-family: var(--f-sans); font-size: 10px; font-weight: 800;
}
.sig-nt {
    display: inline-block; background: var(--c-panel); color: var(--c-muted);
    border: 1px solid var(--c-border2); border-radius: 5px;
    padding: 3px 10px; font-family: var(--f-sans); font-size: 10px; font-weight: 600;
}

/* Pct */
.pct-up   { color: #80CBC4; font-weight: 700; }
.pct-down { color: #EF9A9A; font-weight: 700; }
.pct-neu  { color: var(--c-muted); }

/* ══ LOADING / EMPTY ══════════════════════════ */
.mb-spinner-row {
    display: flex; align-items: center; justify-content: center;
    gap: 12px; padding: 52px; color: var(--c-muted);
    font-size: 12px; font-family: var(--f-mono);
}
.mb-spinner {
    width: 28px; height: 28px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-lime);
    border-radius: 50%;
    animation: mbSpin .9s linear infinite; flex-shrink: 0;
}
.mb-empty { text-align: center; padding: 52px 20px; color: var(--c-muted); }
.mb-empty-icon {
    width: 52px; height: 52px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 20px;
}
.mb-empty p { font-size: 12px; font-family: var(--f-mono); margin-top: 4px; }
</style>

<div class="mb-wrap">

{{-- ══ HERO ══ --}}
<div class="mb-hero mb-anim">
    <div class="mb-hero-left">
        <div class="mb-hero-eyebrow">Options Analytics</div>
        <h1>Momentum <span>Breakout</span> Scanner</h1>
        <p>
            Scan intraday candles to detect when price moves beyond a set percentage
            threshold from the day's open — generating BUY CE or BUY PE signals.
        </p>
    </div>
    <div class="mb-hero-icon">
        <i class="las la-bolt"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="mb-filter-bar">
    <div class="mb-filter-inner">

        <span class="mb-filter-label">Type</span>
        <div class="mb-inst-tabs">
            <button class="mb-inst-tab on-stock" data-inst="stock"
                    onclick="mbSetInst('stock',this)">
                <i class="las la-chart-line"></i> Stock EQ
            </button>
            <button class="mb-inst-tab" data-inst="fut"
                    onclick="mbSetInst('fut',this)">
                <i class="las la-fire"></i> Futures
            </button>
            <button class="mb-inst-tab" data-inst="option"
                    onclick="mbSetInst('option',this)">
                <i class="las la-layer-group"></i> Options
            </button>
        </div>

        <div class="mb-sep"></div>

        <span class="mb-filter-label">Symbol</span>
        <select id="mb-sym" class="mb-sym-select" onchange="mbScan()">
            <option value="ALL">— All —</option>
        </select>

        <div class="mb-sep"></div>

        <span class="mb-filter-label">Date</span>
        <div class="mb-date-wrap">
            <button class="mb-date-nav" onclick="mbShiftDate(-1)">‹</button>
            <input type="date" id="mb-date" class="mb-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="mbScan()">
            <button class="mb-date-nav" onclick="mbShiftDate(1)">›</button>
            <button class="mb-date-nav mb-today-btn" onclick="mbGoToday()">TODAY</button>
            <span id="mb-date-badge"></span>
        </div>

        <div class="mb-sep"></div>

        <span class="mb-filter-label">Move %</span>
        <select id="mb-threshold" class="mb-threshold-select" onchange="mbScan()">
            <option value="0.5">0.5%</option>
            <option value="0.75">0.75%</option>
            <option value="1.0" selected>1.0%</option>
            <option value="1.25">1.25%</option>
            <option value="1.5">1.5%</option>
            <option value="2.0">2.0%</option>
            <option value="2.5">2.5%</option>
            <option value="3.0">3.0%</option>
        </select>

        <label class="mb-nt-wrap">
            <input type="checkbox" id="mb-show-nt" onchange="mbScan()">
            No-Trade
        </label>

        <button class="mb-scan-btn" onclick="mbScan()">
            <i class="las la-search"></i> Scan
        </button>
        <button class="mb-reset-btn" onclick="mbReset()">↺ Reset</button>

        <div class="mb-filter-right">
            <span class="mb-info-text" id="mb-info"></span>
            <span class="mb-upd-text"  id="mb-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="mb-content">

    {{-- Config warning --}}
    <div class="mb-warn" id="mb-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="mb-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="mb-stats mb-anim">
        <div class="mb-stat-card s-total">
            <div class="mb-stat-label">Total Records</div>
            <div class="mb-stat-val" id="st-total">—</div>
        </div>
        <div class="mb-stat-card s-ce">
            <div class="mb-stat-label">▲ BUY CE</div>
            <div class="mb-stat-val" id="st-ce">—</div>
        </div>
        <div class="mb-stat-card s-pe">
            <div class="mb-stat-label">▼ BUY PE</div>
            <div class="mb-stat-val" id="st-pe">—</div>
        </div>
        <div class="mb-stat-card s-nt">
            <div class="mb-stat-label">No Trade</div>
            <div class="mb-stat-val" id="st-nt">—</div>
        </div>
    </div>

    {{-- Table card --}}
    <div class="mb-card mb-anim d1">
        <div class="mb-card-header">
            <div class="mb-card-title">
                <span class="mb-inst-label mb-il-stock" id="mb-il">STOCK EQ</span>
                Breakout Signals
            </div>
            <span class="mb-card-subtitle" id="mb-subtitle">Detecting last available date…</span>
        </div>
        <div class="mb-table-scroll">
            <table class="mb-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="4" class="g-info">Market Info</th>
                        <th colspan="5" class="g-signal sep-signal">⚡ Breakout Signal</th>
                    </tr>
                    <tr class="th-cols">
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>Day Open</th>
                        <th class="sep-signal">Signal</th>
                        <th>Time</th>
                        <th>Price</th>
                        <th>Chg %</th>
                        <th>Day H / L</th>
                    </tr>
                </thead>
                <tbody id="mb-tbody">
                    <tr><td colspan="9">
                        <div class="mb-spinner-row">
                            <div class="mb-spinner"></div>
                            Detecting last available date…
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.mb-content --}}
</div>{{-- /.mb-wrap --}}

@endsection

@push('script')
<script>
/* ════════════════════════════════════════════════════════════
   Momentum Breakout Scanner — JS (all logic identical)
════════════════════════════════════════════════════════════ */

var MB_SCAN     = '{{ route("momentum-breakout.scan") }}';
var MB_SYM      = '{{ route("momentum-breakout.symbols") }}';
var MB_LASTDATE = '{{ route("momentum-breakout.last.date") }}';
var MB_TODAY    = '{{ now()->toDateString() }}';

var mbInst     = 'stock';
var mbSymCache = {};

function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

/* ── BOOT ── */
document.addEventListener('DOMContentLoaded', function () {
    mbResolveLastDateAndLoad();
});

function mbResolveLastDateAndLoad() {
    fetch(MB_LASTDATE + '?instrument=' + mbInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) el('mb-date').value = res.last_date;
        mbLoadSymbols(function () { mbScan(); });
    })
    .catch(function () {
        mbLoadSymbols(function () { mbScan(); });
    });
}

/* ── INSTRUMENT ── */
function mbSetInst(inst, btn) {
    mbInst = inst;
    document.querySelectorAll('.mb-inst-tab').forEach(function (b) {
        b.className = 'mb-inst-tab';
    });
    btn.classList.add('on-' + inst);

    var il     = el('mb-il');
    var labels = { stock:'STOCK EQ', fut:'FUTURES', option:'OPTIONS' };
    var cls    = { stock:'mb-il-stock', fut:'mb-il-fut', option:'mb-il-option' };
    if (il) { il.textContent = labels[inst]; il.className = 'mb-inst-label ' + cls[inst]; }

    fetch(MB_LASTDATE + '?instrument=' + mbInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) el('mb-date').value = res.last_date;
        mbLoadSymbols(function () { mbScan(); });
    })
    .catch(function () {
        mbLoadSymbols(function () { mbScan(); });
    });
}

/* ── DATE ── */
function mbGetDate() { return el('mb-date').value; }

function mbShiftDate(d) {
    var picker = el('mb-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > MB_TODAY) return;
    picker.value = s;
    mbScan();
}

function mbGoToday() {
    el('mb-date').value = MB_TODAY;
    mbScan();
}

function mbUpdateDateBadge(isToday) {
    el('mb-date-badge').innerHTML = isToday
        ? '<span class="mb-live-badge">● Live</span>'
        : '<span class="mb-hist-badge">📅 Historical</span>';
}

/* ── SYMBOLS ── */
function mbLoadSymbols(callback) {
    var key = mbInst;
    if (mbSymCache[key] && mbSymCache[key].length) {
        mbRebuildSym(mbSymCache[key]);
        if (callback) callback();
        return;
    }
    fetch(MB_SYM + '?instrument=' + mbInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.no_config) {
            mbShowWarn(res.message || '');
            mbRebuildSym([]);
        } else {
            mbHideWarn();
            mbSymCache[key] = res.symbols || [];
            mbRebuildSym(mbSymCache[key]);
        }
        if (callback) callback();
    })
    .catch(function () { if (callback) callback(); });
}

function mbRebuildSym(syms) {
    var sel  = el('mb-sym');
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

/* ── SCAN ── */
function mbScan() {
    var date      = mbGetDate();
    var threshold = el('mb-threshold').value || '1.0';
    var showNT    = el('mb-show-nt').checked ? '1' : '0';
    var sym       = el('mb-sym').value;

    if (!date) return;
    mbHideWarn();
    mbResetStats();

    html('mb-tbody', '<tr><td colspan="9"><div class="mb-spinner-row">'
        + '<div class="mb-spinner"></div>'
        + 'Scanning ' + threshold + '% breakout for ' + date + '…'
        + '</div></td></tr>');
    txt('mb-subtitle', date + ' · Scanning…');

    var params = new URLSearchParams({
        instrument    : mbInst,
        date          : date,
        threshold     : threshold,
        show_no_trade : showNT,
    });
    if (sym && sym !== 'ALL') params.append('symbols[]', sym);

    fetch(MB_SCAN + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (typeof res.is_today !== 'undefined') mbUpdateDateBadge(res.is_today);

        if (res.available_symbols && res.available_symbols.length) {
            mbSymCache[mbInst] = res.available_symbols;
            mbRebuildSym(res.available_symbols);
            if (sym && sym !== 'ALL') el('mb-sym').value = sym;
        }
        if (res.no_config) { mbShowWarn(res.message); mbEmptyTable('No active config.'); return; }
        if (!res.success || !res.data || !res.data.length) {
            mbEmptyTable(res.message || 'No signals found for this date.');
            mbUpdateStats({ total_records:0, buy_ce_count:0, buy_pe_count:0, no_trade_count:0 });
            txt('mb-subtitle', date + ' · No signals found');
            return;
        }

        mbUpdateStats(res);
        mbRenderTable(res.data);

        el('mb-info').innerHTML =
            '<span style="color:#80CBC4;">CE: ' + res.buy_ce_count + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:#EF9A9A;">PE: ' + res.buy_pe_count + '</span>'
            + ' &nbsp;·&nbsp; ±' + res.threshold + '%'
            + ' · ' + res.instrument;
        txt('mb-subtitle', date + ' · ' + res.message);
        txt('mb-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) { mbEmptyTable('⚠ ' + err.message); });
}

/* ── RENDERER ── */
function mbRenderTable(data) {
    if (!data || !data.length) { mbEmptyTable('No data.'); return; }
    var h   = '';
    var num = 1;

    data.forEach(function (r, i) {
        var isNT  = r.signal === 'NO_TRADE';
        var isCE  = r.signal === 'BUY_CE';
        var rowCls= isNT ? 'tr-nt' : isCE ? 'tr-ce' : 'tr-pe';
        var zebra = i % 2 === 0 ? 'tr-even' : 'tr-odd';

        var sigHtml = isNT
            ? '<span class="sig-nt">— No Trade —</span>'
            : isCE
                ? '<span class="sig-ce">▲ BUY CE</span>'
                : '<span class="sig-pe">▼ BUY PE</span>';

        var pctHtml = r.change_pct != null
            ? '<span class="' + (r.change_pct > 0 ? 'pct-up' : r.change_pct < 0 ? 'pct-down' : 'pct-neu') + '">'
                + (r.change_pct > 0 ? '+' : '') + f(r.change_pct) + '%</span>'
            : '<span class="pct-neu">—</span>';

        var hlHtml = (r.day_high && r.day_low)
            ? '<span class="c-dh">₹' + f(r.day_high) + '</span>'
                + ' <span style="color:rgba(120,123,134,.3);">/</span> '
                + '<span class="c-dl">₹' + f(r.day_low) + '</span>'
            : '—';

        h += '<tr class="' + rowCls + ' ' + zebra + '">'
            + '<td class="c-num">'  + (isNT ? '' : num++) + '</td>'
            + '<td class="c-date">' + r.date + '</td>'
            + '<td class="c-sym">'  + esc(r.symbol)
                + (r.expiry ? '<small>' + r.expiry + '</small>' : '') + '</td>'
            + '<td class="c-open">' + (r.day_open ? '₹' + f(r.day_open) : '—') + '</td>'
            + '<td class="sep-signal">' + sigHtml + '</td>'
            + '<td class="c-time">' + (r.signal_time  || '—') + '</td>'
            + '<td class="c-px">'   + (r.signal_price ? '₹' + f(r.signal_price) : '—') + '</td>'
            + '<td>' + pctHtml + '</td>'
            + '<td>' + hlHtml  + '</td>'
            + '</tr>';
    });

    html('mb-tbody', h || mbEmptyHtml('No results.'));
}

/* ── STATS ── */
function mbUpdateStats(res) {
    txt('st-total', res.total_records  || '0');
    txt('st-ce',   res.buy_ce_count   || '0');
    txt('st-pe',   res.buy_pe_count   || '0');
    txt('st-nt',   res.no_trade_count || '0');
}
function mbResetStats() {
    ['st-total','st-ce','st-pe','st-nt'].forEach(function (id) { txt(id, '—'); });
}

/* ── HELPERS ── */
function mbShowWarn(msg) { el('mb-warn').classList.add('show'); txt('mb-warn-msg', msg || ''); }
function mbHideWarn()    { el('mb-warn').classList.remove('show'); }
function mbEmptyTable(msg) { html('mb-tbody', mbEmptyHtml(msg)); }

function mbEmptyHtml(msg) {
    return '<tr><td colspan="9"><div class="mb-empty">'
        + '<div class="mb-empty-icon"><i class="las la-chart-bar"></i></div>'
        + '<p>' + (msg || 'No data found.') + '</p>'
        + '</div></td></tr>';
}

function mbReset() {
    fetch(MB_LASTDATE + '?instrument=' + mbInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        el('mb-date').value      = res.last_date || MB_TODAY;
        el('mb-threshold').value = '1.0';
        el('mb-show-nt').checked = false;
        el('mb-sym').value       = 'ALL';
        mbHideWarn(); mbScan();
    })
    .catch(function () {
        el('mb-date').value      = MB_TODAY;
        el('mb-threshold').value = '1.0';
        el('mb-show-nt').checked = false;
        el('mb-sym').value       = 'ALL';
        mbHideWarn(); mbScan();
    });
}

function f(v)   { return parseFloat(v || 0).toFixed(2); }
function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush