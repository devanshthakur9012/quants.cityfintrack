{{-- FILE: resources/views/themes/{active_theme}/user/intraday-oi-snapshot/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — INTRADAY OI SNAPSHOT  v2.0
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

.ios-wrap { font-family: var(--f-sans); color: var(--c-text); background: var(--c-bg); }
.ios-wrap * { box-sizing: border-box; }
.mono { font-family: var(--f-mono); }

@keyframes iosUp   { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.ios-anim { animation: iosUp .5s ease both; }
@keyframes iosSpin { to { transform: rotate(360deg); } }

/* ══ HERO ══ */
.ios-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.ios-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.ios-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.ios-hero-left { position: relative; z-index: 1; }
.ios-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.ios-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.ios-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.ios-hero h1 span { color: var(--c-lime); }
.ios-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 540px; }
.ios-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--c-lime); flex-shrink: 0;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}
@media (max-width: 768px) { .ios-hero { flex-direction: column; padding: 24px 18px; } .ios-hero-icon { display: none; } }

/* ══ FILTER BAR ══ */
.ios-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.ios-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.ios-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.ios-sep { width: 1px; height: 26px; background: var(--c-border2); flex-shrink: 0; }

/* Symbol / Action selects */
.ios-sym-select,
.ios-action-select {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 28px 6px 11px;
    font-size: 12px; font-weight: 600; color: var(--c-text);
    font-family: var(--f-mono);
    appearance: none; cursor: pointer; outline: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    transition: border-color .2s;
}
.ios-sym-select    { min-width: 150px; }
.ios-action-select { min-width: 140px; }
.ios-sym-select:focus, .ios-action-select:focus { border-color: rgba(125,255,0,.45); }

/* Date controls */
.ios-date-wrap { display: flex; align-items: center; gap: 4px; }
.ios-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.ios-date-input:focus { border-color: rgba(125,255,0,.45); }
.ios-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.ios-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.ios-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.ios-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Badges */
.ios-live-badge { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.ios-hist-badge { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Buttons */
.ios-analyze-btn {
    background: var(--c-lime); color: #000; border: none; border-radius: 7px;
    padding: 7px 18px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(125,255,0,.2);
    display: inline-flex; align-items: center; gap: 6px;
}
.ios-analyze-btn:hover { background: #8FFF1A; box-shadow: 0 0 22px rgba(125,255,0,.35); transform: translateY(-1px); }
.ios-reset-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: var(--f-mono);
    transition: all .2s; letter-spacing: .05em;
}
.ios-reset-btn:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }

.ios-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.ios-info-text { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.ios-upd-text  { font-size: 10px; color: rgba(120,123,134,.5); font-family: var(--f-mono); }
@media (max-width: 768px) { .ios-filter-bar { padding: 0 16px; } .ios-filter-inner { gap: 8px; } .ios-filter-right { margin-left: 0; width: 100%; } }

/* ══ CONTENT ══ */
.ios-content { padding: 24px 32px 64px; }
@media (max-width: 768px) { .ios-content { padding: 16px 12px 48px; } }

/* Config warning */
.ios-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.ios-warn.show { display: flex; }
.ios-warn i { font-size: 18px; flex-shrink: 0; }
.ios-warn strong { color: #fff; }

/* ══ STATS GRID ══ */
.ios-stats {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px; margin-bottom: 20px;
}
@media (max-width: 900px) { .ios-stats { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 500px)  { .ios-stats { grid-template-columns: repeat(2, 1fr); } }

.ios-stat-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; padding: 14px 16px;
    border-left: 2px solid var(--c-border2);
    position: relative; overflow: hidden;
}
.ios-stat-card::before {
    content: ''; position: absolute;
    top: 0; left: 0; right: 0; height: 1px;
    opacity: .25;
}
.ios-stat-card.s-total { border-left-color: #00B8D4; }
.ios-stat-card.s-total::before { background: #00B8D4; }
.ios-stat-card.s-ce    { border-left-color: #26A69A; }
.ios-stat-card.s-ce::before    { background: #26A69A; }
.ios-stat-card.s-pe    { border-left-color: #EF5350; }
.ios-stat-card.s-pe::before    { background: #EF5350; }
.ios-stat-card.s-wait  { border-left-color: #FFA726; }
.ios-stat-card.s-wait::before  { background: #FFA726; }
.ios-stat-card.s-bull  { border-left-color: #26A69A; }
.ios-stat-card.s-bull::before  { background: #26A69A; }
.ios-stat-card.s-bear  { border-left-color: #EF5350; }
.ios-stat-card.s-bear::before  { background: #EF5350; }

.ios-stat-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--c-muted); margin-bottom: 6px; font-family: var(--f-mono); }
.ios-stat-val   { font-family: var(--f-mono); font-size: 26px; font-weight: 700; color: var(--c-text); }
.s-total .ios-stat-val { color: #00B8D4; }
.s-ce    .ios-stat-val { color: #4DB6AC; }
.s-pe    .ios-stat-val { color: #EF9A9A; }
.s-wait  .ios-stat-val { color: #FFA726; }
.s-bull  .ios-stat-val { color: #4DB6AC; }
.s-bear  .ios-stat-val { color: #EF9A9A; }

/* ══ TABLE CARD ══ */
.ios-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    position: relative;
}
.ios-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.ios-card-header {
    padding: 13px 18px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    background: rgba(0,0,0,.2);
}
.ios-card-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: var(--c-text); display: flex; align-items: center; gap: 8px;
}
.ios-card-subtitle { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.ios-tscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ══ TABLE ══ */
.ios-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 1020px; }

.ios-table thead tr.th-group th {
    padding: 8px 10px 4px; text-align: center;
    font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    background: var(--c-panel); border-bottom: none; white-space: nowrap;
}
.ios-table thead tr.th-cols th {
    padding: 4px 10px 8px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}

/* Column group colors */
.g-info   { color: var(--c-muted)  !important; }
.g-oi     { color: var(--c-amber)  !important; }
.g-signal { color: var(--c-teal)   !important; }

/* Separator borders */
.sep-oi     { border-left: 1px solid rgba(255,167,38,.15)  !important; }
.sep-signal { border-left: 1px solid rgba(38,166,154,.15)  !important; }

/* Body cells */
.ios-table tbody td {
    padding: 7px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.ios-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.tr-even { background: var(--c-surface); }
.tr-odd  { background: rgba(0,0,0,.15); }
.tr-bull { background: rgba(38,166,154,.04)  !important; }
.tr-bear { background: rgba(239,83,80,.04)   !important; }

/* Cell value styles */
.c-num    { font-size: 9px; color: rgba(120,123,134,.35); }
.c-date   { font-size: 11px; font-weight: 700; color: var(--c-lime); }
.c-sym    { font-size: 12px; font-weight: 700; color: var(--c-blue); }
.c-atm    { font-size: 10px; color: var(--c-amber); font-weight: 700; }
.c-fut    { font-size: 10px; color: var(--c-blue); }
.c-expiry { font-size: 10px; color: var(--c-muted); }
.c-oi     { font-size: 11px; font-weight: 700; color: var(--c-text); }
.c-oi small { display: block; font-size: 8px; color: var(--c-muted); font-weight: 400; margin-top: 1px; }

.pct-up  { color: #4DB6AC; font-weight: 700; }
.pct-dn  { color: #EF9A9A; font-weight: 700; }
.pct-neu { color: var(--c-muted); }

/* Signal / Action badges */
.sig-bull { display:inline-block; background:rgba(38,166,154,.15); color:#4DB6AC; border:1px solid rgba(38,166,154,.3); border-radius:4px; padding:3px 8px; font-family:var(--f-sans); font-size:10px; font-weight:700; letter-spacing:.04em; }
.sig-bear { display:inline-block; background:rgba(239,83,80,.12);  color:#EF9A9A; border:1px solid rgba(239,83,80,.28); border-radius:4px; padding:3px 8px; font-family:var(--f-sans); font-size:10px; font-weight:700; letter-spacing:.04em; }
.sig-neut { display:inline-block; background:var(--c-panel); color:var(--c-muted); border:1px solid var(--c-border2); border-radius:4px; padding:3px 8px; font-family:var(--f-sans); font-size:10px; }

.act-ce { display:inline-block; background:rgba(38,166,154,.12); color:#4DB6AC; border:1px solid rgba(38,166,154,.3); border-radius:4px; padding:2px 8px; font-family:var(--f-sans); font-size:10px; font-weight:700; }
.act-pe { display:inline-block; background:rgba(239,83,80,.1);  color:#EF9A9A; border:1px solid rgba(239,83,80,.25); border-radius:4px; padding:2px 8px; font-family:var(--f-sans); font-size:10px; font-weight:700; }
.act-wt { display:inline-block; background:rgba(255,167,38,.1); color:var(--c-amber); border:1px solid rgba(255,167,38,.25); border-radius:4px; padding:2px 8px; font-family:var(--f-sans); font-size:10px; }

/* Condition pills */
.cond-base  { display:inline-block; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; font-family:var(--f-mono); }
.cond-ce-pe { background:rgba(239,83,80,.1);  color:#EF9A9A; border:1px solid rgba(239,83,80,.25); }
.cond-pe-ce { background:rgba(38,166,154,.1); color:#4DB6AC; border:1px solid rgba(38,166,154,.25); }
.cond-both  { background:rgba(171,71,188,.1); color:#CE93D8; border:1px solid rgba(171,71,188,.25); }
.cond-flat  { background:var(--c-panel); color:var(--c-muted); border:1px solid var(--c-border2); }

/* Strength rank badges */
.rank-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:9px; font-weight:700; font-family:var(--f-mono); }
.rank-1 { background:rgba(239,83,80,.1);   color:#EF9A9A; border:1px solid rgba(239,83,80,.25); }
.rank-2 { background:rgba(255,167,38,.1);  color:var(--c-amber); border:1px solid rgba(255,167,38,.25); }
.rank-3 { background:rgba(0,184,212,.08);  color:var(--c-blue);  border:1px solid rgba(0,184,212,.25); }
.rank-4 { background:rgba(38,166,154,.1);  color:#4DB6AC; border:1px solid rgba(38,166,154,.25); }
.rank-n { background:var(--c-panel); color:var(--c-muted); border:1px solid var(--c-border2); }

/* Loading / empty */
.ios-spinner-row {
    display: flex; align-items: center; justify-content: center;
    gap: 12px; padding: 52px 20px; color: var(--c-muted);
    font-size: 12px; font-family: var(--f-mono);
}
.ios-spinner {
    width: 28px; height: 28px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-lime);
    border-radius: 50%; animation: iosSpin .9s linear infinite; flex-shrink: 0;
}
.ios-empty { text-align: center; padding: 52px 20px; color: var(--c-muted); }
.ios-empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 22px; color: var(--c-muted);
}
.ios-empty p { font-size: 12px; font-family: var(--f-mono); margin-top: 4px; }
</style>

<div class="ios-wrap">

{{-- ══ HERO ══ --}}
<div class="ios-hero ios-anim">
    <div class="ios-hero-left">
        <div class="ios-hero-eyebrow">Options Analytics</div>
        <h1>Intraday OI <span>Snapshot</span></h1>
        <p>Tracks CE and PE Open Interest changes from market open to midday, helping identify intraday option writing momentum and market direction.</p>
    </div>
    <div class="ios-hero-icon"><i class="las la-camera"></i></div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="ios-filter-bar">
    <div class="ios-filter-inner">

        <span class="ios-filter-label">Symbol</span>
        <select id="ios-sym" class="ios-sym-select" onchange="iosAnalyze()">
            <option value="ALL">— All —</option>
        </select>

        <div class="ios-sep"></div>

        <span class="ios-filter-label">Date</span>
        <div class="ios-date-wrap">
            <button class="ios-date-nav" onclick="iosShiftDate(-1)">‹</button>
            <input type="date" id="ios-date" class="ios-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="iosAnalyze()">
            <button class="ios-date-nav" onclick="iosShiftDate(1)">›</button>
            <button class="ios-date-nav ios-today-btn" onclick="iosGoToday()">TODAY</button>
            <span id="ios-date-badge"></span>
        </div>

        <div class="ios-sep"></div>

        <span class="ios-filter-label">Action</span>
        <select id="ios-action" class="ios-action-select" onchange="iosAnalyze()">
            <option value="">All Actions</option>
            <option value="BUY CE">BUY CE Only</option>
            <option value="BUY PE">BUY PE Only</option>
            <option value="WAIT">WAIT Only</option>
        </select>

        <button class="ios-analyze-btn" onclick="iosAnalyze()">
            <i class="las la-camera"></i> Analyze
        </button>
        <button class="ios-reset-btn" onclick="iosReset()">↺ Reset</button>

        <div class="ios-filter-right">
            <span class="ios-info-text" id="ios-info"></span>
            <span class="ios-upd-text"  id="ios-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="ios-content">

    <div class="ios-warn" id="ios-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="ios-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="ios-stats ios-anim">
        <div class="ios-stat-card s-total"><div class="ios-stat-label">Total</div>   <div class="ios-stat-val" id="st-total">—</div></div>
        <div class="ios-stat-card s-ce">  <div class="ios-stat-label">BUY CE</div>  <div class="ios-stat-val" id="st-ce">—</div></div>
        <div class="ios-stat-card s-pe">  <div class="ios-stat-label">BUY PE</div>  <div class="ios-stat-val" id="st-pe">—</div></div>
        <div class="ios-stat-card s-wait"><div class="ios-stat-label">WAIT</div>    <div class="ios-stat-val" id="st-wait">—</div></div>
        <div class="ios-stat-card s-bull"><div class="ios-stat-label">Bullish</div> <div class="ios-stat-val" id="st-bull">—</div></div>
        <div class="ios-stat-card s-bear"><div class="ios-stat-label">Bearish</div> <div class="ios-stat-val" id="st-bear">—</div></div>
    </div>

    {{-- Table --}}
    <div class="ios-card ios-anim">
        <div class="ios-card-header">
            <div class="ios-card-title">◆ Intraday OI Snapshot</div>
            <span class="ios-card-subtitle" id="ios-subtitle">Detecting last available date…</span>
        </div>
        <div class="ios-tscroll">
            <table class="ios-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="5" class="g-info">Market Info</th>
                        <th colspan="4" class="g-oi sep-oi">CE / PE OI Change (Open → Midday)</th>
                        <th colspan="4" class="g-signal sep-signal">Signal</th>
                    </tr>
                    <tr class="th-cols">
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>ATM / FUT</th>
                        <th>Expiry</th>
                        <th class="sep-oi">CE OI<br><span style="font-size:7px;font-weight:400;opacity:.5;">Snap / Open</span></th>
                        <th>CE Chg %</th>
                        <th>PE OI<br><span style="font-size:7px;font-weight:400;opacity:.5;">Snap / Open</span></th>
                        <th>PE Chg %</th>
                        <th class="sep-signal">Sentiment</th>
                        <th>Condition</th>
                        <th>Strength</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ios-tbody">
                    <tr><td colspan="13">
                        <div class="ios-spinner-row">
                            <div class="ios-spinner"></div>
                            Detecting last available date…
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.ios-content --}}
</div>{{-- /.ios-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════
//  Intraday OI Snapshot — JS (no jQuery)
// ═══════════════════════════════════════════════════════════

var IOS_ANALYZE  = '{{ route("intraday-oi-snapshot.analyze") }}';
var IOS_SYMBOLS  = '{{ route("intraday-oi-snapshot.symbols") }}';
var IOS_LASTDATE = '{{ route("intraday-oi-snapshot.last.date") }}';
var IOS_TODAY    = '{{ now()->toDateString() }}';

var iosSymCache = null;

function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

// ── BOOT ─────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    iosResolveLastDateAndLoad();
});

function iosResolveLastDateAndLoad() {
    fetch(IOS_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.last_date) el('ios-date').value = res.last_date;
            iosLoadSymbols(function () { iosAnalyze(); });
        })
        .catch(function () {
            iosLoadSymbols(function () { iosAnalyze(); });
        });
}

// ── Date helpers ──────────────────────────────────────────

function iosGetDate() { return el('ios-date').value; }

function iosShiftDate(d) {
    var picker = el('ios-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > IOS_TODAY) return;
    picker.value = s;
    iosAnalyze();
}

function iosGoToday() {
    el('ios-date').value = IOS_TODAY;
    iosAnalyze();
}

function iosUpdateDateBadge(isToday) {
    el('ios-date-badge').innerHTML = isToday
        ? '<span class="ios-live-badge">● Live</span>'
        : '<span class="ios-hist-badge">📅 Historical</span>';
}

// ── Symbols ───────────────────────────────────────────────

function iosLoadSymbols(callback) {
    if (iosSymCache !== null) {
        iosRebuildSym(iosSymCache);
        if (callback) callback();
        return;
    }

    fetch(IOS_SYMBOLS, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.no_config) {
                iosShowWarn(res.message || '');
                iosSymCache = [];
                iosRebuildSym([]);
            } else {
                iosHideWarn();
                iosSymCache = res.symbols || [];
                iosRebuildSym(iosSymCache);
            }
            if (callback) callback();
        })
        .catch(function () { if (callback) callback(); });
}

function iosRebuildSym(syms) {
    var sel  = el('ios-sym');
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

function iosAnalyze() {
    var date   = iosGetDate();
    var action = el('ios-action').value;
    var sym    = el('ios-sym').value;

    if (!date) return;

    iosHideWarn();
    iosResetStats();

    html('ios-tbody', '<tr><td colspan="13"><div class="ios-spinner-row">'
        + '<div class="ios-spinner"></div>'
        + 'Comparing OI for ' + date + '…'
        + '</div></td></tr>');
    txt('ios-subtitle', date + ' · Loading…');

    var params = new URLSearchParams({ date: date, filter_action: action });
    if (sym && sym !== 'ALL') params.append('symbols[]', sym);

    fetch(IOS_ANALYZE + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (typeof res.is_today !== 'undefined') iosUpdateDateBadge(res.is_today);

        if (res.available_symbols && res.available_symbols.length) {
            iosSymCache = res.available_symbols;
            iosRebuildSym(iosSymCache);
            if (sym && sym !== 'ALL') el('ios-sym').value = sym;
        }

        if (res.no_config) {
            iosShowWarn(res.message);
            iosEmptyTable('No active config.');
            return;
        }

        if (!res.success || !res.data || !res.data.length) {
            iosEmptyTable(res.message || 'No signals found for this date.');
            iosResetStats();
            txt('ios-subtitle', date + ' · No data found');
            return;
        }

        iosUpdateStats(res);
        iosRenderTable(res.data);

        el('ios-info').innerHTML =
            '<span style="color:#4DB6AC;">CE: ' + res.buy_ce_count + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:#EF9A9A;">PE: ' + res.buy_pe_count + '</span>';
        txt('ios-subtitle', date + ' · ' + res.message);
        txt('ios-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) {
        iosEmptyTable('⚠ ' + err.message);
    });
}

// ── Render ────────────────────────────────────────────────

function iosRenderTable(data) {
    var h = '', num = 1;

    data.forEach(function (r, i) {
        var isBull = r.sentiment === 'BULLISH', isBear = r.sentiment === 'BEARISH';
        var rowCls = (isBull ? 'tr-bull' : isBear ? 'tr-bear' : '') + ' ' + (i % 2 === 0 ? 'tr-even' : 'tr-odd');

        var sentBadge = isBull ? '<span class="sig-bull">▲ BULLISH</span>'
                      : isBear ? '<span class="sig-bear">▼ BEARISH</span>'
                      : '<span class="sig-neut">— NEUTRAL</span>';

        var actBadge = r.trade_action === 'BUY CE' ? '<span class="act-ce">📈 BUY CE</span>'
                     : r.trade_action === 'BUY PE' ? '<span class="act-pe">📉 BUY PE</span>'
                     : '<span class="act-wt">⏸ WAIT</span>';

        var cond    = r.condition || '';
        var condCls = 'cond-base cond-flat';
        if (cond.includes('CE ↑') && cond.includes('PE ↓'))      condCls = 'cond-base cond-ce-pe';
        else if (cond.includes('CE ↓') && cond.includes('PE ↑')) condCls = 'cond-base cond-pe-ce';
        else if (cond.includes('Both'))                            condCls = 'cond-base cond-both';

        var rankMap = {
            'Rank 1':'rank-badge rank-1','Rank 2':'rank-badge rank-2',
            'Rank 3':'rank-badge rank-3','Rank 4':'rank-badge rank-4','Normal':'rank-badge rank-n'
        };
        var rankCls = rankMap[r.strength_rank] || 'rank-badge rank-n';

        h += '<tr class="' + rowCls + '">'
            + '<td class="c-num">'    + num + '</td>'
            + '<td class="c-date">'   + r.date + '</td>'
            + '<td class="c-sym">'    + esc(r.symbol) + '</td>'
            + '<td>'
                + (r.atm_strike ? '<span class="c-atm">₹' + nInt(r.atm_strike) + '</span>' : '—')
                + (r.fut_price  ? '<br><span class="c-fut">F:₹' + f(r.fut_price) + '</span>' : '')
            + '</td>'
            + '<td class="c-expiry">' + (r.expiry || '—') + '</td>'
            + '<td class="sep-oi c-oi">' + nInt(r.ce_oi) + '</td>'
            + '<td>' + pctCell(r.ce_oi_pct) + '</td>'
            + '<td class="c-oi">'     + nInt(r.pe_oi) + '</td>'
            + '<td>' + pctCell(r.pe_oi_pct) + '</td>'
            + '<td class="sep-signal">' + sentBadge + '</td>'
            + '<td><span class="' + condCls + '">' + esc(cond) + '</span></td>'
            + '<td><span class="' + rankCls + '">' + r.strength_rank + '</span></td>'
            + '<td>' + actBadge + '</td>'
            + '</tr>';
        num++;
    });

    html('ios-tbody', h || iosEmptyHtml('No results.'));
}

// ── Stats / helpers ───────────────────────────────────────

function iosUpdateStats(res) {
    txt('st-total', res.total_records || '0');
    txt('st-ce',   res.buy_ce_count  || '0');
    txt('st-pe',   res.buy_pe_count  || '0');
    txt('st-wait', res.wait_count    || '0');
    txt('st-bull', res.bullish_count || '0');
    txt('st-bear', res.bearish_count || '0');
}

function iosResetStats() {
    ['st-total','st-ce','st-pe','st-wait','st-bull','st-bear'].forEach(function (id) { txt(id, '—'); });
}

function iosShowWarn(msg) { el('ios-warn').classList.add('show'); txt('ios-warn-msg', msg || ''); }
function iosHideWarn()    { el('ios-warn').classList.remove('show'); }
function iosEmptyTable(msg) { html('ios-tbody', iosEmptyHtml(msg)); }
function iosEmptyHtml(msg) {
    return '<tr><td colspan="13">'
        + '<div class="ios-empty">'
        + '<div class="ios-empty-icon"><i class="las la-chart-bar"></i></div>'
        + '<p>' + (msg || 'No data found.') + '</p>'
        + '</div></td></tr>';
}

function iosReset() {
    fetch(IOS_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            el('ios-date').value   = res.last_date || IOS_TODAY;
            el('ios-action').value = '';
            el('ios-sym').value    = 'ALL';
            iosHideWarn();
            iosAnalyze();
        })
        .catch(function () {
            el('ios-date').value   = IOS_TODAY;
            el('ios-action').value = '';
            el('ios-sym').value    = 'ALL';
            iosHideWarn();
            iosAnalyze();
        });
}

function pctCell(v) {
    if (v == null) return '<span class="pct-neu">—</span>';
    var n = parseFloat(v) || 0;
    var cls = n > 0 ? 'pct-up' : n < 0 ? 'pct-dn' : 'pct-neu';
    return '<span class="' + cls + '">' + (n > 0 ? '+' : '') + n.toFixed(2) + '%</span>';
}

function f(v)    { return parseFloat(v || 0).toFixed(2); }
function nInt(v) {
    var n = Number(v) || 0;
    if (n >= 1e7) return (n/1e7).toFixed(2) + 'Cr';
    if (n >= 1e5) return (n/1e5).toFixed(2) + 'L';
    if (n >= 1e3) return (n/1e3).toFixed(1) + 'K';
    return n.toLocaleString('en-IN');
}
function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush