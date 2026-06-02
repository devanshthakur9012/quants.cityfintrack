{{-- FILE: resources/views/themes/{active_theme}/user/straddle-strategy/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — STRADDLE STRATEGY  v2.0
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

.ss-wrap {
    font-family: var(--f-sans);
    color: var(--c-text);
    background: var(--c-bg);
}
.ss-wrap * { box-sizing: border-box; }
.mono { font-family: var(--f-mono); }

@keyframes ssFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.ss-anim { animation: ssFadeUp .5s ease both; }
@keyframes ssSpin { to { transform: rotate(360deg); } }

/* ══ HERO ═════════════════════════════════════ */
.ss-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.ss-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.ss-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.ss-hero-left { position: relative; z-index: 1; }
.ss-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.ss-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.ss-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.ss-hero h1 span { color: var(--c-lime); }
.ss-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 540px; }

/* Formula pills */
.ss-hero-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px; }
.ss-pill {
    display: inline-block; padding: 3px 10px; border-radius: 4px;
    font-family: var(--f-mono); font-size: 10px; font-weight: 600;
    letter-spacing: .04em;
}
.ss-pill-factor { background: rgba(255,167,38,.1);  color: var(--c-amber);  border: 1px solid rgba(255,167,38,.25); }
.ss-pill-ce     { background: rgba(38,166,154,.1);  color: var(--c-teal);   border: 1px solid rgba(38,166,154,.25); }
.ss-pill-pe     { background: rgba(239,83,80,.08);  color: var(--c-red);    border: 1px solid rgba(239,83,80,.2);   }

/* Hero icon */
.ss-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--c-lime); flex-shrink: 0;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}

@media (max-width: 768px) {
    .ss-hero { flex-direction: column; padding: 24px 18px; }
    .ss-hero-pills { flex-wrap: wrap; }
}

/* ══ FILTER BAR ═══════════════════════════════ */
.ss-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.ss-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.ss-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.ss-filter-sep {
    width: 1px; height: 26px;
    background: var(--c-border2); flex-shrink: 0;
}

/* Strategy select */
.ss-strat-select {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 28px 6px 11px;
    font-size: 12px; font-weight: 600; color: var(--c-text);
    font-family: var(--f-mono);
    appearance: none; cursor: pointer; outline: none; min-width: 180px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    transition: border-color .2s;
}
.ss-strat-select:focus { border-color: rgba(125,255,0,.45); }

/* Symbol select */
.ss-sym-select {
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
.ss-sym-select:focus { border-color: rgba(125,255,0,.45); }

/* Date controls */
.ss-date-wrap { display: flex; align-items: center; gap: 4px; }
.ss-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.ss-date-input:focus { border-color: rgba(125,255,0,.45); }
.ss-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.ss-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.ss-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.ss-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Date / live badges */
.ss-live-badge { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.ss-hist-badge { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Action buttons */
.ss-load-btn {
    background: var(--c-lime); color: #000; border: none; border-radius: 7px;
    padding: 7px 18px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(125,255,0,.2);
    display: inline-flex; align-items: center; gap: 6px;
}
.ss-load-btn:hover { background: #8FFF1A; box-shadow: 0 0 22px rgba(125,255,0,.35); transform: translateY(-1px); }
.ss-reset-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: var(--f-mono);
    transition: all .2s; letter-spacing: .05em;
}
.ss-reset-btn:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }

/* Filter pills */
.ss-fp-wrap { display: flex; gap: 4px; flex-wrap: wrap; }
.ss-fp {
    padding: 5px 13px; border-radius: 100px;
    font-family: var(--f-mono); font-size: 10px; font-weight: 700;
    cursor: pointer; border: 1px solid var(--c-border2);
    background: transparent; color: var(--c-muted); transition: all .15s;
}
.ss-fp:hover       { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.ss-fp.f-all       { border-color: rgba(125,255,0,.35);  background: var(--c-lime-dim);        color: var(--c-lime);  }
.ss-fp.f-ce        { border-color: rgba(38,166,154,.35); background: rgba(38,166,154,.1);       color: var(--c-teal);  }
.ss-fp.f-pe        { border-color: rgba(239,83,80,.35);  background: rgba(239,83,80,.08);       color: var(--c-red);   }
.ss-fp.f-wait      { border-color: var(--c-border2);     background: rgba(255,255,255,.02);     color: var(--c-muted); }

.ss-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.ss-info-text { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.ss-last-upd  { font-size: 10px; color: rgba(120,123,134,.5); font-family: var(--f-mono); }

@media (max-width: 768px) {
    .ss-filter-bar { padding: 0 16px; }
    .ss-filter-inner { gap: 8px; }
    .ss-filter-right { margin-left: 0; width: 100%; }
}

/* ══ CONTENT ══════════════════════════════════ */
.ss-content { padding: 24px 32px 64px; }
@media (max-width: 768px) { .ss-content { padding: 16px 12px 48px; } }

/* Config warning */
.ss-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.ss-warn.show { display: flex; }
.ss-warn i { font-size: 18px; flex-shrink: 0; }
.ss-warn strong { color: #fff; }

/* ══ STATS ════════════════════════════════════ */
.ss-stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
.ss-stat {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; padding: 12px 18px; flex: 1; min-width: 100px;
    position: relative; overflow: hidden;
}
.ss-stat::before {
    content: ''; position: absolute;
    top: 0; left: 0; right: 0; height: 2px;
}
.ss-stat-total::before { background: var(--c-lime); }
.ss-stat-ce::before    { background: var(--c-teal); }
.ss-stat-pe::before    { background: var(--c-red);  }
.ss-stat-wait::before  { background: var(--c-muted);}
.ss-stat small {
    display: block; font-size: 9px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--c-muted); margin-bottom: 4px;
    font-family: var(--f-mono);
}
.ss-stat strong {
    display: block; font-family: var(--f-mono); font-size: 1.2rem; font-weight: 700;
}

/* ══ CARD ══════════════════════════════════════ */
.ss-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    margin-bottom: 20px; position: relative;
}
.ss-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.ss-card-header {
    padding: 13px 18px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    background: rgba(0,0,0,.2);
}
.ss-card-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: var(--c-text); display: flex; align-items: center; gap: 8px;
}
.ss-card-info { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }

/* ══ DETAIL HEADER ════════════════════════════ */
.ss-detail-hdr {
    background: rgba(255,167,38,.04);
    border: 1px solid rgba(255,167,38,.18);
    border-radius: 9px; padding: 13px 16px; margin-bottom: 14px;
    display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
}
.ss-detail-sym {
    font-family: var(--f-display); font-size: 20px; font-weight: 800;
    color: var(--c-lime);
}
.ss-dm {
    border-radius: 5px; padding: 3px 10px; font-size: 10px; font-weight: 700;
    border: 1px solid; font-family: var(--f-mono);
}
.ss-dm-amber { background: rgba(255,167,38,.1);  color: var(--c-amber); border-color: rgba(255,167,38,.25); }
.ss-dm-teal  { background: rgba(38,166,154,.1);  color: var(--c-teal);  border-color: rgba(38,166,154,.25); }

/* ══ TABLE ════════════════════════════════════ */
.ss-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.ss-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 1000px; }
.ss-table.detail-table { min-width: 860px; }

/* Header rows */
.ss-table thead tr.th-group th {
    padding: 8px 10px 4px; text-align: center;
    font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    background: var(--c-panel); border-bottom: none; white-space: nowrap;
}
.ss-table thead tr.th-cols th {
    padding: 4px 10px 8px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}

/* Column group header colors */
.g-info  { color: var(--c-muted)  !important; }
.g-ce    { color: var(--c-teal)   !important; }
.g-pe    { color: var(--c-red)    !important; }
.g-sig   { color: var(--c-amber)  !important; }

/* Separator borders */
.sep-ce  { border-left: 1px solid rgba(38,166,154,.15)  !important; }
.sep-pe  { border-left: 1px solid rgba(239,83,80,.15)   !important; }
.sep-sig { border-left: 1px solid rgba(255,167,38,.15)  !important; }

/* Body cells */
.ss-table tbody td {
    padding: 7px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.ss-table tbody tr { cursor: pointer; }
.ss-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.tr-even     { background: var(--c-surface); }
.tr-odd      { background: rgba(0,0,0,.15); }
.tr-ce       { background: rgba(38,166,154,.04)  !important; border-left: 2px solid var(--c-teal)   !important; }
.tr-pe       { background: rgba(239,83,80,.04)   !important; border-left: 2px solid var(--c-red)    !important; }
.tr-wait     { opacity: .7; }
.tr-entry    { background: rgba(255,167,38,.05)  !important; border-left: 2px solid var(--c-lime)   !important; }
.tr-latest   { background: rgba(171,71,188,.04)  !important; border-left: 2px solid var(--c-purple) !important; }

/* Cell value styles */
.c-num   { font-size: 9px; color: rgba(120,123,134,.35); }
.c-sym   { font-size: 11px; font-weight: 700; color: var(--c-blue); }
.c-time  { font-size: 12px; font-weight: 700; color: var(--c-lime); }
.c-amber { color: var(--c-amber); font-weight: 700; }
.c-green { color: var(--c-teal);  font-weight: 700; }
.c-red   { color: var(--c-red);   font-weight: 700; }
.c-muted { font-size: 9px; color: rgba(120,123,134,.5); }

/* Time pills */
.tp-entry  { display: inline-block; background: rgba(255,167,38,.12);  color: var(--c-amber);  border: 1px solid rgba(255,167,38,.3);  border-radius: 5px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
.tp-latest { display: inline-block; background: rgba(171,71,188,.12);  color: var(--c-purple); border: 1px solid rgba(171,71,188,.3);  border-radius: 5px; padding: 2px 8px; font-size: 10px; font-weight: 700; }

/* Signal badges */
.sig-ce   { display: inline-block; background: rgba(38,166,154,.12);  color: #4DB6AC; border: 1px solid rgba(38,166,154,.3);  border-radius: 5px; padding: 3px 10px; font-family: var(--f-sans); font-size: 10px; font-weight: 700; }
.sig-pe   { display: inline-block; background: rgba(239,83,80,.10);   color: #EF9A9A; border: 1px solid rgba(239,83,80,.28);  border-radius: 5px; padding: 3px 10px; font-family: var(--f-sans); font-size: 10px; font-weight: 700; }
.sig-wait { display: inline-block; background: var(--c-panel);        color: var(--c-muted); border: 1px solid var(--c-border2); border-radius: 5px; padding: 3px 10px; font-family: var(--f-sans); font-size: 9px; }

/* Score bars */
.score-wrap  { display: flex; align-items: center; gap: 4px; justify-content: center; }
.score-num   { font-size: 12px; font-weight: 700; min-width: 14px; }
.score-track { width: 40px; height: 3px; background: rgba(255,255,255,.06); border-radius: 2px; overflow: hidden; }
.score-fill  { height: 100%; border-radius: 2px; }

/* Factor dots */
.fd-wrap { display: flex; align-items: center; gap: 3px; justify-content: center; flex-wrap: wrap; }
.fd      { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.fd-ce   { background: var(--c-teal); box-shadow: 0 0 4px rgba(38,166,154,.5); }
.fd-pe   { background: var(--c-red);  box-shadow: 0 0 4px rgba(239,83,80,.5);  }
.fd-neut { background: rgba(255,255,255,.1); }
.fd-na   { background: rgba(255,255,255,.04); border: 1px solid var(--c-border); }

/* Symbol badge */
.sym-badge {
    display: inline-block; padding: 2px 9px; border-radius: 4px;
    font-size: 11px; font-weight: 700;
    background: rgba(255,167,38,.1); color: var(--c-amber);
    border: 1px solid rgba(255,167,38,.25);
}

/* Factor legend */
.ss-legend {
    padding: 10px 18px; border-top: 1px solid var(--c-border);
    font-size: 10px; color: var(--c-muted); line-height: 2;
    background: rgba(0,0,0,.2); font-family: var(--f-mono);
}
.ss-legend strong { color: var(--c-text); }

/* ══ LOADING / EMPTY ══════════════════════════ */
.ss-loading {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 56px 20px;
}
.ss-spinner {
    width: 32px; height: 32px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-lime);
    border-radius: 50%;
    animation: ssSpin .9s linear infinite;
}
.ss-loading-text { color: var(--c-muted); margin-top: 12px; font-size: 12px; font-family: var(--f-mono); }

.ss-empty {
    text-align: center; padding: 52px 20px; color: var(--c-muted);
}
.ss-empty i { font-size: 2rem; display: block; margin-bottom: 12px; }
</style>

<div class="ss-wrap">

{{-- ══ HERO ══ --}}
<div class="ss-hero ss-anim">
    <div class="ss-hero-left">
        <div class="ss-hero-eyebrow">Options Analytics</div>
        <h1>Straddle &amp; Strangle <span>Signal Engine</span></h1>
        <p>
            5-factor directional scoring for Long/Short Straddle &amp; Strangle setups —
            powered by live candle data. Minimum 3/5 factors required to fire a signal.
        </p>
    </div>
    <div class="ss-hero-icon">
        <i class="las la-layer-group"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="ss-filter-bar">
    <div class="ss-filter-inner">

        {{-- Strategy --}}
        <span class="ss-filter-label">Strategy</span>
        <select id="ss-strat" class="ss-strat-select" onchange="ssLoad()">
            <option value="long_straddle"  selected>Long Straddle</option>
            <option value="short_straddle">Short Straddle</option>
            <option value="long_strangle">Long Strangle</option>
            <option value="short_strangle">Short Strangle</option>
        </select>

        <div class="ss-filter-sep"></div>

        {{-- Symbol --}}
        <span class="ss-filter-label">Symbol</span>
        <select id="ss-sym" class="ss-sym-select" onchange="ssLoad()">
            <option value="ALL">— All —</option>
        </select>
        <button class="ss-reset-btn" onclick="ssClearSym()">All Symbols</button>

        <div class="ss-filter-sep"></div>

        {{-- Date --}}
        <span class="ss-filter-label">Date</span>
        <div class="ss-date-wrap">
            <button class="ss-date-nav" onclick="ssShiftDate(-1)">‹</button>
            <input type="date" id="ss-date" class="ss-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="ssLoad()">
            <button class="ss-date-nav" onclick="ssShiftDate(1)">›</button>
            <button class="ss-date-nav ss-today-btn" onclick="ssToday()">TODAY</button>
            <span id="ss-date-badge"></span>
        </div>

        <button class="ss-load-btn" onclick="ssLoad()">
            <i class="las la-sync-alt"></i> Analyze
        </button>

        <div class="ss-filter-sep"></div>

        {{-- Signal filter pills (summary mode only) --}}
        <span class="ss-filter-label" id="ss-fp-label">Filter</span>
        <div class="ss-fp-wrap" id="ss-fp-wrap">
            <button class="ss-fp f-all"  data-f="ALL"    onclick="ssSetFilter('ALL',this)">All</button>
            <button class="ss-fp"        data-f="BUY_CE" onclick="ssSetFilter('BUY_CE',this)">▲ Buy CE</button>
            <button class="ss-fp"        data-f="BUY_PE" onclick="ssSetFilter('BUY_PE',this)">▼ Buy PE</button>
            <button class="ss-fp"        data-f="WAIT"   onclick="ssSetFilter('WAIT',this)">— Wait</button>
        </div>

        <div class="ss-filter-right">
            <span class="ss-info-text" id="ss-info"></span>
            <span class="ss-last-upd"  id="ss-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="ss-content">

    {{-- Config warning --}}
    <div class="ss-warn" id="ss-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;" id="ss-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats (summary mode only) --}}
    <div class="ss-stats" id="ss-stats" style="display:none;">
        <div class="ss-stat ss-stat-total">
            <small>Total</small>
            <strong id="ss-st-total" style="color:var(--c-lime);">0</strong>
        </div>
        <div class="ss-stat ss-stat-ce">
            <small>▲ Buy CE</small>
            <strong id="ss-st-ce" style="color:var(--c-teal);">0</strong>
        </div>
        <div class="ss-stat ss-stat-pe">
            <small>▼ Buy PE</small>
            <strong id="ss-st-pe" style="color:var(--c-red);">0</strong>
        </div>
        <div class="ss-stat ss-stat-wait">
            <small>— Wait</small>
            <strong id="ss-st-wait" style="color:var(--c-muted);">0</strong>
        </div>
    </div>

    {{-- Main output area --}}
    <div id="ss-output">
        <div class="ss-card">
            <div class="ss-card-header">
                <div class="ss-card-title" id="ss-card-title">
                    ◆ Straddle &amp; Strangle Signal Engine
                </div>
                <span class="ss-card-info" id="ss-card-info"></span>
            </div>
            <div class="ss-table-scroll">
                <table class="ss-table" id="ss-main-table">
                    <thead id="ss-thead">
                        <tr class="th-group">
                            <th colspan="5" class="g-info">Info</th>
                            <th colspan="3" class="g-ce sep-ce">▲ CE</th>
                            <th colspan="3" class="g-pe sep-pe">▼ PE</th>
                            <th colspan="4" class="g-sig sep-sig">◆ Signal (5 Factors · need 3+)</th>
                        </tr>
                        <tr class="th-cols">
                            <th class="g-info">#</th>
                            <th class="g-info" style="text-align:left;padding-left:14px;">Symbol</th>
                            <th class="g-info">ATM / Expiry</th>
                            <th class="g-info">Spot</th>
                            <th class="g-info">Combined Prem</th>
                            <th class="g-ce sep-ce">CE Strike</th>
                            <th class="g-ce">CE LTP</th>
                            <th class="g-ce">CE OI</th>
                            <th class="g-pe sep-pe">PE Strike</th>
                            <th class="g-pe">PE LTP</th>
                            <th class="g-pe">PE OI</th>
                            <th class="g-sig sep-sig">Signal</th>
                            <th class="g-sig">Score CE/PE</th>
                            <th class="g-sig">Factors</th>
                        </tr>
                    </thead>
                    <tbody id="ss-tbody">
                        <tr><td colspan="15">
                            <div class="ss-empty">
                                <i class="las la-chart-area"></i>
                                Select strategy and click Analyze
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
            <div class="ss-legend">
                <strong>5 Factors scored (need 3+ on same side):</strong>
                &nbsp;▲ <strong style="color:var(--c-teal);">Futures Momentum</strong> (bullish/bearish candle)
                &nbsp;·&nbsp; ▲ <strong style="color:var(--c-teal);">OI Confirmation</strong> (OI ↑ + LTP ↑ = fresh buying)
                &nbsp;·&nbsp; ▲ <strong style="color:var(--c-teal);">Premium Momentum</strong> (which leg gaining faster, need &gt;2% diff)
                &nbsp;·&nbsp; ▲ <strong style="color:var(--c-teal);">PCR</strong> (&lt;0.80 bullish, &gt;1.20 bearish)
                &nbsp;·&nbsp; ▲ <strong style="color:var(--c-teal);">Candle Structure</strong> (new high + bullish close = breakout)
                &nbsp;&nbsp;
                <span style="color:var(--c-teal);">●</span> = CE factor &nbsp;
                <span style="color:var(--c-red);">●</span> = PE factor &nbsp;
                <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:rgba(255,255,255,.1);vertical-align:middle;"></span> = Neutral
            </div>
        </div>
    </div>

</div>{{-- /.ss-content --}}
</div>{{-- /.ss-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  STRADDLE STRATEGY — JS  (no jQuery dependency)
// ═══════════════════════════════════════════════════════════════

var SS_TODAY  = '{{ now()->toDateString() }}';
var SS_ROUTE  = '{{ route("straddle-strategy.data") }}';
var ssFilter  = 'ALL';
var ssMode    = 'summary';
var ssSymCache = [];

// ── Helpers ───────────────────────────────────────────────────

function ssHtml(id, html) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = html;
}
function ssTxt(id, txt) {
    var el = document.getElementById(id);
    if (el) el.textContent = txt;
}

document.addEventListener('DOMContentLoaded', function() {
    ssUpdateDateBadge();
    ssLoad();
});

// ── Date ─────────────────────────────────────────────────────

function ssGetDate() { return document.getElementById('ss-date').value; }
function ssGetSym()  { return document.getElementById('ss-sym').value; }
function ssGetStrat(){ return document.getElementById('ss-strat').value; }

function ssShiftDate(d) {
    var picker = document.getElementById('ss-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > SS_TODAY) return;
    picker.value = s;
    ssUpdateDateBadge();
    ssLoad();
}

function ssToday() {
    document.getElementById('ss-date').value = SS_TODAY;
    ssUpdateDateBadge();
    ssLoad();
}

function ssUpdateDateBadge() {
    var d  = ssGetDate();
    var el = document.getElementById('ss-date-badge');
    if (!el) return;
    el.innerHTML = d === SS_TODAY
        ? '<span class="ss-live-badge">● Live</span>'
        : '<span class="ss-hist-badge">📅 Historical</span>';
}

// ── Symbol dropdown ───────────────────────────────────────────

function ssRebuildSym(symbols) {
    var sel  = document.getElementById('ss-sym');
    var prev = sel.value;
    sel.innerHTML = '<option value="ALL">— All Symbols —</option>';
    symbols.forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
    ssSymCache = symbols;
}

function ssClearSym() {
    document.getElementById('ss-sym').value = 'ALL';
    ssLoad();
}

// ── Filter pills ──────────────────────────────────────────────

function ssSetFilter(f, btn) {
    ssFilter = f;
    document.querySelectorAll('.ss-fp').forEach(function(b) {
        b.className = 'ss-fp';
    });
    var cls = f === 'BUY_CE' ? 'f-ce' : f === 'BUY_PE' ? 'f-pe' : f === 'WAIT' ? 'f-wait' : 'f-all';
    btn.classList.add(cls);
    ssApplyFilter();
}

function ssApplyFilter() {
    document.querySelectorAll('#ss-tbody tr[data-sig]').forEach(function(row) {
        var sig  = row.dataset.sig;
        var show = ssFilter === 'ALL'
            || (ssFilter === 'BUY_CE' && sig === 'BUY_CE')
            || (ssFilter === 'BUY_PE' && sig === 'BUY_PE')
            || (ssFilter === 'WAIT'   && sig === 'WAIT');
        row.style.display = show ? '' : 'none';
    });
}

// ── Main loader ───────────────────────────────────────────────

function ssLoad() {
    var date  = ssGetDate();
    var sym   = ssGetSym();
    var strat = ssGetStrat();

    ssUpdateDateBadge();
    document.getElementById('ss-warn').classList.remove('show');

    if (ssMode === 'summary') {
        ssHtml('ss-tbody',
            '<tr><td colspan="15"><div class="ss-loading">'
            + '<div class="ss-spinner"></div>'
            + '<div class="ss-loading-text">Calculating signals for ' + date + '…</div>'
            + '</div></td></tr>');
    } else {
        ssHtml('ss-output',
            '<div class="ss-card" style="padding:70px;text-align:center;">'
            + '<div class="ss-spinner" style="margin:0 auto;"></div>'
            + '<div class="ss-loading-text" style="margin-top:12px;">Loading…</div>'
            + '</div>');
    }

    document.getElementById('ss-stats').style.display = 'none';

    var params = new URLSearchParams({ strategy: strat, symbol: sym, date: date });

    fetch(SS_ROUTE + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function(res) {
        if (res.no_config) {
            document.getElementById('ss-warn').classList.add('show');
            ssTxt('ss-warn-msg', res.message || '');
            ssShowEmptyTable('No active config.');
            return;
        }

        if (!res.success) {
            ssShowEmptyTable(res.message || 'No data found.');
            return;
        }

        if (res.available_symbols && res.available_symbols.length) {
            ssRebuildSym(res.available_symbols);
        }

        ssMode = res.mode;
        ssTxt('ss-card-info', (res.total || res.total_intervals || 0) + ' row(s) · ' + (res.strategy_name || ''));
        ssTxt('ss-upd', 'Updated ' + new Date().toLocaleTimeString());

        if (res.mode === 'detail') {
            ssRenderDetail(res);
        } else {
            ssRestoreSummaryShell();
            ssRenderSummary(res);
            ssUpdateStats(res);
            ssApplyFilter();
        }
    })
    .catch(function(err) {
        ssShowEmptyTable('⚠ ' + err.message);
    });
}

// ── Restore summary shell (after coming back from detail) ─────

function ssRestoreSummaryShell() {
    var outputEl = document.getElementById('ss-output');
    if (!document.getElementById('ss-tbody')) {
        outputEl.innerHTML =
            '<div class="ss-card">'
            + '<div class="ss-card-header">'
            + '<div class="ss-card-title" id="ss-card-title">◆ Straddle &amp; Strangle Signal Engine</div>'
            + '<span class="ss-card-info" id="ss-card-info"></span>'
            + '</div>'
            + '<div class="ss-table-scroll">'
            + '<table class="ss-table" id="ss-main-table">'
            + '<thead id="ss-thead">'
            + '<tr class="th-group">'
            + '<th colspan="5" class="g-info">Info</th>'
            + '<th colspan="3" class="g-ce sep-ce">▲ CE</th>'
            + '<th colspan="3" class="g-pe sep-pe">▼ PE</th>'
            + '<th colspan="4" class="g-sig sep-sig">◆ Signal (5 Factors · need 3+)</th>'
            + '</tr>'
            + '<tr class="th-cols">'
            + '<th class="g-info">#</th>'
            + '<th class="g-info" style="text-align:left;padding-left:14px;">Symbol</th>'
            + '<th class="g-info">ATM / Expiry</th>'
            + '<th class="g-info">Spot</th>'
            + '<th class="g-info">Combined Prem</th>'
            + '<th class="g-ce sep-ce">CE Strike</th><th class="g-ce">CE LTP</th><th class="g-ce">CE OI</th>'
            + '<th class="g-pe sep-pe">PE Strike</th><th class="g-pe">PE LTP</th><th class="g-pe">PE OI</th>'
            + '<th class="g-sig sep-sig">Signal</th>'
            + '<th class="g-sig">Score CE/PE</th>'
            + '<th class="g-sig">Factors</th>'
            + '<th class="g-sig">Reason</th>'
            + '</tr></thead>'
            + '<tbody id="ss-tbody"></tbody>'
            + '</table></div>'
            + '<div class="ss-legend">'
            + '<strong>5 Factors scored (need 3+ on same side):</strong>'
            + ' ▲ <strong style="color:var(--c-teal);">Futures Momentum</strong>'
            + ' · ▲ <strong style="color:var(--c-teal);">OI Confirmation</strong>'
            + ' · ▲ <strong style="color:var(--c-teal);">Premium Momentum</strong>'
            + ' · ▲ <strong style="color:var(--c-teal);">PCR</strong>'
            + ' · ▲ <strong style="color:var(--c-teal);">Candle Structure</strong>'
            + ' &nbsp; <span style="color:var(--c-teal);">●</span> CE &nbsp;'
            + ' <span style="color:var(--c-red);">●</span> PE'
            + '</div></div>';
        document.getElementById('ss-fp-wrap').style.display = '';
        document.getElementById('ss-fp-label').style.display = '';
        document.getElementById('ss-stats').style.display = '';
    }
}

// ═══════════════════════════════════════════════════════════════
//  RENDERERS
// ═══════════════════════════════════════════════════════════════

function ssRenderSummary(res) {
    document.getElementById('ss-fp-wrap').style.display = '';
    document.getElementById('ss-fp-label').style.display = '';

    var html = '';
    var data = res.data || [];

    data.forEach(function(r, i) {
        var sig    = r.signal || 'WAIT';
        var isCe   = sig === 'BUY_CE';
        var isPe   = sig === 'BUY_PE';
        var zebra  = i % 2 === 0 ? 'tr-even' : 'tr-odd';
        var rowCls = zebra + (isCe ? ' tr-ce' : isPe ? ' tr-pe' : ' tr-wait');

        html += '<tr class="' + rowCls + '" data-sig="' + ssEsc(sig) + '" onclick="ssJumpToSym(\'' + ssEsc(r.symbol) + '\')">'
            + '<td class="c-num">' + (i + 1) + '</td>'
            + '<td style="text-align:left;padding-left:14px;"><span class="sym-badge">' + ssEsc(r.symbol) + '</span></td>'
            + '<td>'
            + '<span class="c-amber">₹' + ssFmt(r.atm_strike) + '</span>'
            + (r.expiry ? '<br><span class="c-muted">' + ssEsc(r.expiry) + '</span>' : '')
            + '</td>'
            + '<td style="font-weight:700;color:var(--c-text);">' + (r.spot !== null ? '₹' + ssFmt(r.spot) : ssDash()) + '</td>'
            + '<td class="c-amber">' + (r.combined_prem !== null ? '₹' + ssFmt(r.combined_prem) : ssDash()) + '</td>'
            // CE
            + '<td class="c-green sep-ce">' + (r.ce_strike ? ssFmt(r.ce_strike) : ssDash()) + '</td>'
            + '<td class="c-green">' + (r.ce_ltp !== null ? '₹' + ssFmt(r.ce_ltp) : ssDash()) + '</td>'
            + '<td class="c-muted">' + (r.ce_oi !== null ? ssFmtInt(r.ce_oi) : ssDash()) + '</td>'
            // PE
            + '<td class="c-red sep-pe">' + (r.pe_strike ? ssFmt(r.pe_strike) : ssDash()) + '</td>'
            + '<td class="c-red">' + (r.pe_ltp !== null ? '₹' + ssFmt(r.pe_ltp) : ssDash()) + '</td>'
            + '<td class="c-muted">' + (r.pe_oi !== null ? ssFmtInt(r.pe_oi) : ssDash()) + '</td>'
            // Signal
            + '<td class="sep-sig">' + ssSigBadge(sig) + '</td>'
            + '<td>' + ssScoreBars(r.ce_score, r.pe_score) + '</td>'
            + '<td>' + ssFactorDots(r.factors) + '</td>'
            + '<td style="font-size:9px;color:var(--c-muted);text-align:left;max-width:200px;white-space:normal;padding:7px 10px;">'
            + '</tr>';
    });

    ssHtml('ss-tbody', html || ssEmptyRows(15, 'No data for the selected filters.'));
    document.getElementById('ss-stats').style.display = '';
}

function ssRenderDetail(res) {
    document.getElementById('ss-fp-wrap').style.display = 'none';
    document.getElementById('ss-fp-label').style.display = 'none';
    document.getElementById('ss-stats').style.display = 'none';

    var hdr = '<div class="ss-detail-hdr">'
        + '<span class="ss-detail-sym">◆ ' + ssEsc(res.symbol) + '</span>'
        + '<span class="ss-dm ss-dm-amber">ATM ₹' + ssFmt(res.atm_strike) + '</span>'
        + '<span class="ss-dm ss-dm-amber">Expiry: ' + ssEsc(res.expiry || '—') + '</span>'
        + '<span class="ss-dm ss-dm-teal">' + ssEsc(res.strategy_name) + '</span>'
        + '<span class="ss-dm ss-dm-teal">15min</span>'
        + '<span class="ss-dm ss-dm-amber">Latest: ' + ssEsc(res.latest_slot || '—') + '</span>'
        + '<button onclick="ssClearSym()" style="margin-left:auto;background:var(--c-panel);border:1px solid var(--c-border2);color:var(--c-muted);border-radius:7px;padding:5px 14px;font-family:var(--f-mono);font-size:11px;font-weight:700;cursor:pointer;transition:all .2s;">‹ All Symbols</button>'
        + '</div>';

    var html = '';
    var data = res.data || [];

    data.forEach(function(r, i) {
        var sig   = r.signal || 'WAIT';
        var isCe  = sig === 'BUY_CE';
        var isPe  = sig === 'BUY_PE';
        var rowCls;
        if (r.is_entry)       rowCls = 'tr-entry';
        else if (r.is_latest) rowCls = 'tr-latest';
        else rowCls = (i % 2 === 0 ? 'tr-even' : 'tr-odd') + (isCe ? ' tr-ce' : isPe ? ' tr-pe' : ' tr-wait');

        var timePill = r.is_entry
            ? '<span class="tp-entry">▲ ' + r.time + '</span>'
            : r.is_latest
                ? '<span class="tp-latest">▼ ' + r.time + '</span>'
                : '<span class="c-time">' + r.time + '</span>';

        html += '<tr class="' + rowCls + '" data-sig="' + ssEsc(sig) + '">'
            + '<td class="c-num">' + (i + 1) + '</td>'
            + '<td>' + timePill + '</td>'
            + '<td style="font-weight:700;color:var(--c-text);">' + (r.spot !== null ? '₹' + ssFmt(r.spot) : ssDash()) + '</td>'
            + '<td class="c-amber">' + (r.combined_prem !== null ? '₹' + ssFmt(r.combined_prem) : ssDash()) + '</td>'
            + '<td class="c-muted">' + (r.pcr !== null ? r.pcr : ssDash()) + '</td>'
            // CE
            + '<td class="c-green sep-ce">' + (r.ce_ltp !== null ? '₹' + ssFmt(r.ce_ltp) : ssDash()) + '</td>'
            + '<td class="c-muted">' + (r.ce_oi !== null ? ssFmtInt(r.ce_oi) : ssDash()) + '</td>'
            // PE
            + '<td class="c-red sep-pe">' + (r.pe_ltp !== null ? '₹' + ssFmt(r.pe_ltp) : ssDash()) + '</td>'
            + '<td class="c-muted">' + (r.pe_oi !== null ? ssFmtInt(r.pe_oi) : ssDash()) + '</td>'
            // Signal
            + '<td class="sep-sig">' + ssSigBadge(sig) + '</td>'
            + '<td>' + ssScoreBars(r.ce_score, r.pe_score) + '</td>'
            + '<td>' + ssFactorDots(r.factors) + '</td>'
            + '<td style="font-size:9px;color:var(--c-muted);text-align:left;max-width:220px;white-space:normal;padding:7px 10px;">'
            + ssEsc(r.reason || '—') + '</td>'
            + '</tr>';
    });

    var detailTable =
        '<div class="ss-card">'
        + '<div class="ss-card-header">'
        + '<div class="ss-card-title">◆ ' + ssEsc(res.symbol) + ' — ' + ssEsc(res.strategy_name) + ' · 15min</div>'
        + '<span class="ss-card-info" id="ss-card-info">' + (res.total_intervals || 0) + ' intervals</span>'
        + '</div>'
        + '<div class="ss-table-scroll">'
        + '<table class="ss-table detail-table">'
        + '<thead>'
        + '<tr class="th-group">'
        + '<th colspan="5" class="g-info">Info</th>'
        + '<th colspan="2" class="g-ce sep-ce">▲ CE</th>'
        + '<th colspan="2" class="g-pe sep-pe">▼ PE</th>'
        + '<th colspan="4" class="g-sig sep-sig">◆ Signal (5 Factors)</th>'
        + '</tr>'
        + '<tr class="th-cols">'
        + '<th class="g-info">#</th>'
        + '<th class="g-info">Time</th>'
        + '<th class="g-info">Spot</th>'
        + '<th class="g-info">Combined Prem</th>'
        + '<th class="g-info">PCR</th>'
        + '<th class="g-ce sep-ce">CE LTP</th>'
        + '<th class="g-ce">CE OI</th>'
        + '<th class="g-pe sep-pe">PE LTP</th>'
        + '<th class="g-pe">PE OI</th>'
        + '<th class="g-sig sep-sig">Signal</th>'
        + '<th class="g-sig">Score CE/PE</th>'
        + '<th class="g-sig">Factors</th>'
        + '<th class="g-sig">Reason</th>'
        + '</tr>'
        + '</thead>'
        + '<tbody>'
        + (html || ssEmptyRows(13, 'No candle data found.'))
        + '</tbody>'
        + '</table></div>'
        + '<div class="ss-legend">'
        + '<strong>Signal fires when 3+ factors align on same side.</strong> &nbsp;'
        + '<span style="color:var(--c-lime);">▲ Amber row</span> = Entry 09:15 &nbsp;'
        + '<span style="color:var(--c-purple);">▼ Purple row</span> = Latest candle &nbsp;·&nbsp; '
        + 'Click "All Symbols" to return to summary view.'
        + '</div></div>';

    ssHtml('ss-output', hdr + detailTable);
}

// ── Jump to detail ────────────────────────────────────────────

function ssJumpToSym(sym) {
    document.getElementById('ss-sym').value = sym;
    ssLoad();
}

// ── Stats ─────────────────────────────────────────────────────

function ssUpdateStats(res) {
    ssTxt('ss-st-total', res.total       || 0);
    ssTxt('ss-st-ce',    res.buy_ce_count || 0);
    ssTxt('ss-st-pe',    res.buy_pe_count || 0);
    ssTxt('ss-st-wait',  res.wait_count   || 0);
    document.getElementById('ss-stats').style.display = '';
}

// ── Helpers ───────────────────────────────────────────────────

function ssSigBadge(sig) {
    if (sig === 'BUY_CE') return '<span class="sig-ce">▲ BUY CE</span>';
    if (sig === 'BUY_PE') return '<span class="sig-pe">▼ BUY PE</span>';
    return '<span class="sig-wait">— WAIT</span>';
}

function ssScoreBars(ceScore, peScore) {
    ceScore = ceScore || 0; peScore = peScore || 0;
    var cePct = Math.round((ceScore / 5) * 100);
    var pePct = Math.round((peScore / 5) * 100);
    return '<div style="display:flex;flex-direction:column;gap:3px;align-items:center;">'
        + '<div class="score-wrap">'
        + '<span class="score-num" style="color:var(--c-teal);">' + ceScore + '</span>'
        + '<div class="score-track"><div class="score-fill" style="width:' + cePct + '%;background:var(--c-teal);"></div></div>'
        + '</div>'
        + '<div class="score-wrap">'
        + '<span class="score-num" style="color:var(--c-red);">' + peScore + '</span>'
        + '<div class="score-track"><div class="score-fill" style="width:' + pePct + '%;background:var(--c-red);"></div></div>'
        + '</div>'
        + '</div>';
}

function ssFactorDots(factors) {
    if (!factors || !factors.length) return ssDash();
    var dots = factors.map(function(f) {
        var cls = f.side === 'CE' ? 'fd fd-ce'
                : f.side === 'PE' ? 'fd fd-pe'
                : f.side === 'N/A' ? 'fd fd-na'
                : 'fd fd-neut';
        return '<span class="' + cls + '" title="' + ssEsc(f.name + ': ' + f.detail) + '"></span>';
    }).join('');
    return '<div class="fd-wrap">' + dots + '</div>';
}

function ssShowEmptyTable(msg) {
    if (!document.getElementById('ss-tbody')) {
        ssHtml('ss-output',
            '<div class="ss-card"><div class="ss-empty"><i class="las la-chart-area"></i>'
            + (msg || 'No data') + '</div></div>');
        return;
    }
    ssHtml('ss-tbody', ssEmptyRows(15, msg));
    document.getElementById('ss-stats').style.display = 'none';
}

function ssEmptyRows(cols, msg) {
    return '<tr><td colspan="' + cols + '">'
        + '<div class="ss-empty">'
        + '<i class="las la-chart-area"></i>'
        + (msg || 'No data found for this date / symbol.')
        + '</div></td></tr>';
}

function ssFmt(v) {
    if (v == null || v === '') return '—';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 });
}
function ssFmtInt(v) {
    if (v == null) return '—';
    var n = Number(v) || 0;
    if (n >= 1e7) return (n / 1e7).toFixed(2) + 'Cr';
    if (n >= 1e5) return (n / 1e5).toFixed(2) + 'L';
    if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
    return n.toLocaleString('en-IN');
}
function ssDash() { return '<span style="color:rgba(120,123,134,.35);font-size:9px;">—</span>'; }
function ssEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
@endpush