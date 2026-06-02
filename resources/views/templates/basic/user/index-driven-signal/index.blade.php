{{-- FILE: resources/views/themes/{active_theme}/user/index-driven-signal/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   INDEX-DRIVEN SIGNAL SCANNER
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

.ids-wrap { font-family: var(--f-sans); color: var(--c-text); background: var(--c-bg); }
.ids-wrap * { box-sizing: border-box; }
.mono { font-family: var(--f-mono); }

@keyframes idsUp   { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.ids-anim { animation: idsUp .5s ease both; }
@keyframes idsSpin { to { transform: rotate(360deg); } }

/* ── HERO ── */
.ids-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.ids-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.ids-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.ids-hero-left { position: relative; z-index: 1; }
.ids-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.ids-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.ids-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.ids-hero h1 span { color: var(--c-lime); }
.ids-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 540px; }
.ids-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--c-lime); flex-shrink: 0;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}
@media(max-width:768px){ .ids-hero{ flex-direction:column; padding:24px 18px; } .ids-hero-icon{ display:none; } }

/* ── FILTER BAR ── */
.ids-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.ids-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.ids-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.ids-sep { width: 1px; height: 26px; background: var(--c-border2); flex-shrink: 0; }

/* Symbol select */
.ids-sym-select {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 28px 6px 11px;
    font-size: 12px; font-weight: 600; color: var(--c-text);
    font-family: var(--f-mono);
    appearance: none; cursor: pointer; outline: none; min-width: 160px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    transition: border-color .2s;
}
.ids-sym-select:focus { border-color: rgba(125,255,0,.45); }

/* Date controls */
.ids-date-wrap { display: flex; align-items: center; gap: 4px; }
.ids-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer; transition: border-color .2s;
}
.ids-date-input:focus { border-color: rgba(125,255,0,.45); }
.ids-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.ids-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.ids-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.ids-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Date / status badges */
.ids-live-badge { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.ids-hist-badge { background: rgba(255,167,38,.1); color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Generic select */
.ids-generic-select {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 26px 6px 11px;
    font-size: 12px; font-weight: 600; color: var(--c-text);
    font-family: var(--f-mono);
    appearance: none; cursor: pointer; outline: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 9px center;
    transition: border-color .2s;
}
.ids-generic-select:focus { border-color: rgba(125,255,0,.45); }

/* Threshold */
.ids-thresh-wrap { display: flex; align-items: center; gap: 8px; }
.ids-thresh-disp {
    font-family: var(--f-mono); font-size: 13px; font-weight: 700;
    color: var(--c-lime); min-width: 38px; text-align: center;
    background: rgba(125,255,0,.08); border: 1px solid rgba(125,255,0,.2);
    border-radius: 6px; padding: 2px 6px;
}
input[type=range].ids-range { accent-color: var(--c-lime); width: 110px; cursor: pointer; }

/* Buttons */
.ids-analyze-btn {
    background: var(--c-lime); color: #000; border: none; border-radius: 7px;
    padding: 7px 18px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(125,255,0,.2);
    display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
}
.ids-analyze-btn:hover { background: #8FFF1A; box-shadow: 0 0 22px rgba(125,255,0,.35); transform: translateY(-1px); }
.ids-reset-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: var(--f-mono);
    transition: all .2s; letter-spacing: .05em;
}
.ids-reset-btn:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }

.ids-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.ids-info-text { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
@media(max-width:768px){ .ids-filter-bar{ padding:0 16px; } .ids-filter-inner{ gap:8px; } .ids-filter-right{ margin-left:0; width:100%; } }

/* ── CONTENT ── */
.ids-content { padding: 24px 32px 64px; }
@media(max-width:768px){ .ids-content{ padding:16px 12px 48px; } }

/* Config warning */
.ids-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.ids-warn.show { display: flex; }
.ids-warn i { font-size: 18px; flex-shrink: 0; }
.ids-warn strong { color: #fff; }

/* ── STATS ── */
.ids-stats { display: grid; grid-template-columns: repeat(5,1fr); gap: 14px; margin-bottom: 24px; }
@media(max-width:900px){ .ids-stats{ grid-template-columns: repeat(3,1fr); } }
@media(max-width:500px){ .ids-stats{ grid-template-columns: repeat(2,1fr); } }
.ids-stat-card {
    background: var(--c-surface); border-radius: 10px;
    border: 1px solid var(--c-border);
    padding: 14px 16px; border-left: 2px solid var(--c-border2);
}
.ids-stat-card.s-total { border-left-color: var(--c-blue); }
.ids-stat-card.s-ce    { border-left-color: var(--c-teal); }
.ids-stat-card.s-pe    { border-left-color: var(--c-red); }
.ids-stat-card.s-syms  { border-left-color: var(--c-amber); }
.ids-stat-card.s-inv   { border-left-color: var(--c-purple); }
.ids-stat-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--c-muted); margin-bottom: 6px; font-family: var(--f-mono); }
.ids-stat-val { font-family: var(--f-mono); font-size: 22px; font-weight: 700; color: var(--c-text); }
.s-total .ids-stat-val { color: var(--c-blue); }
.s-ce    .ids-stat-val { color: var(--c-teal); }
.s-pe    .ids-stat-val { color: var(--c-red); }
.s-syms  .ids-stat-val { color: var(--c-amber); }
.s-inv   .ids-stat-val { color: var(--c-purple); font-size: 16px; }

/* ── TABLE CARD ── */
.ids-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    margin-bottom: 20px; position: relative;
}
.ids-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .25;
}
.ids-card-header {
    padding: 13px 18px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    background: rgba(0,0,0,.2);
}
.ids-card-title { font-family: var(--f-display); font-size: 14px; font-weight: 700; color: var(--c-text); display: flex; align-items: center; gap: 8px; }
.ids-card-subtitle { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.ids-tscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ── MAIN TABLE ── */
.ids-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 1100px; }

.ids-table thead tr.th-group th {
    padding: 8px 10px 4px; text-align: center;
    font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    background: var(--c-panel); border-bottom: none; white-space: nowrap;
}
.ids-table thead tr.th-cols th {
    padding: 4px 10px 8px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}

/* Column group colors */
.g-info   { color: var(--c-muted)  !important; }
.g-nifty  { color: var(--c-blue)   !important; }
.g-option { color: var(--c-amber)  !important; }
.g-entry  { color: var(--c-teal)   !important; }

/* Column separators */
.sep-nifty  { border-left: 1px solid rgba(0,184,212,.15)  !important; }
.sep-option { border-left: 1px solid rgba(255,167,38,.15) !important; }
.sep-entry  { border-left: 1px solid rgba(38,166,154,.15) !important; }

/* Body cells */
.ids-table tbody td {
    padding: 7px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.ids-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.tr-even { background: var(--c-surface); }
.tr-odd  { background: rgba(0,0,0,.15); }
.tr-ce   { background: rgba(38,166,154,.04)  !important; }
.tr-pe   { background: rgba(239,83,80,.04)   !important; }

/* Group header row */
.ids-group-row td {
    background: rgba(0,184,212,.05) !important;
    border-top: 1px solid rgba(0,184,212,.15) !important;
    border-bottom: none !important;
    padding: 9px 16px !important; text-align: left !important;
    font-family: var(--f-sans); font-size: 12px; font-weight: 700;
    color: var(--c-blue) !important; letter-spacing: .03em;
}
.ids-group-row.gr-pe td {
    background: rgba(239,83,80,.05) !important;
    border-top-color: rgba(239,83,80,.15) !important;
    color: var(--c-red) !important;
}

/* Cell value classes */
.c-num  { font-size: 9px; color: rgba(120,123,134,.35); }
.c-date { font-size: 11px; font-weight: 700; color: var(--c-lime); }
.c-sym  { font-size: 12px; font-weight: 700; color: var(--c-blue); }
.c-val  { font-size: 11px; font-weight: 700; color: var(--c-text); }
.c-sm   { font-size: 10px; color: var(--c-muted); }
.up     { color: var(--c-teal); font-weight: 700; }
.dn     { color: var(--c-red);  font-weight: 700; }

/* Signal badges */
.sig-ce {
    display: inline-block; background: rgba(38,166,154,.12); color: #4DB6AC;
    border: 1px solid rgba(38,166,154,.3); border-radius: 4px;
    padding: 3px 10px; font-family: var(--f-sans); font-size: 10px; font-weight: 700;
}
.sig-pe {
    display: inline-block; background: rgba(239,83,80,.1); color: #EF9A9A;
    border: 1px solid rgba(239,83,80,.28); border-radius: 4px;
    padding: 3px 10px; font-family: var(--f-sans); font-size: 10px; font-weight: 700;
}

/* Time badges */
.time-badge {
    display: inline-block; font-family: var(--f-mono); font-size: 10px; font-weight: 700;
    background: rgba(0,184,212,.08); border: 1px solid rgba(0,184,212,.2);
    color: var(--c-blue); padding: 2px 8px; border-radius: 5px;
}
.time-badge.pe  { background: rgba(239,83,80,.08); border-color: rgba(239,83,80,.2); color: #EF9A9A; }
.time-badge.buy { background: rgba(255,167,38,.1); border-color: rgba(255,167,38,.25); color: var(--c-amber); }

/* Empty & spinner */
.ids-empty { text-align: center; padding: 52px 20px; color: var(--c-muted); }
.ids-empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 22px;
}
.ids-empty p { font-size: 12px; font-family: var(--f-mono); margin-top: 4px; }
.ids-spinner-row { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 48px; color: var(--c-muted); font-size: 12px; font-family: var(--f-mono); }
.ids-spinner { width: 28px; height: 28px; border: 2px solid var(--c-border2); border-top: 2px solid var(--c-lime); border-radius: 50%; animation: idsSpin .9s linear infinite; flex-shrink: 0; }

/* ── EXIT P&L SECTION ── */
.ids-pnl-section {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden; margin-bottom: 24px;
    position: relative;
}
.ids-pnl-section::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .2;
}
.ids-pnl-header { padding: 14px 18px; border-bottom: 1px solid var(--c-border); background: rgba(0,0,0,.2); }
.ids-pnl-header-title { font-family: var(--f-display); font-size: 14px; font-weight: 700; color: var(--c-text); margin-bottom: 10px; }
.ids-pnl-callout {
    background: rgba(0,184,212,.05); border: 1px solid rgba(0,184,212,.12);
    border-radius: 8px; padding: 10px 14px; font-size: 12px; color: var(--c-muted);
    line-height: 1.7; margin-bottom: 12px; font-family: var(--f-sans);
}
.ids-pnl-callout strong { color: var(--c-blue); }
.ids-pnl-btn-row { display: flex; gap: 10px; flex-wrap: wrap; }
.ids-pnl-btn {
    border: none; border-radius: 7px; padding: 7px 18px;
    font-family: var(--f-display); font-size: 12px; font-weight: 700;
    cursor: pointer; transition: all .2s; letter-spacing: .04em;
}
.ids-pnl-btn.ce { background: rgba(38,166,154,.1); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); }
.ids-pnl-btn.ce:hover { background: rgba(38,166,154,.2); }
.ids-pnl-btn.pe { background: rgba(239,83,80,.08); color: #EF9A9A; border: 1px solid rgba(239,83,80,.2); }
.ids-pnl-btn.pe:hover { background: rgba(239,83,80,.16); }
.ids-pnl-body { padding: 18px; }
.ids-pnl-card { border-radius: 8px; border: 1px solid var(--c-border); overflow: hidden; margin-bottom: 16px; }
.ids-pnl-card-hdr {
    padding: 11px 16px; font-family: var(--f-display); font-size: 13px;
    font-weight: 700; border-bottom: 1px solid var(--c-border);
}
.ids-pnl-card.type-ce .ids-pnl-card-hdr { background: rgba(38,166,154,.06); color: #4DB6AC; }
.ids-pnl-card.type-pe .ids-pnl-card-hdr { background: rgba(239,83,80,.05); color: #EF9A9A; }

/* P&L table */
.pnl-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 600px; }
.pnl-table thead th {
    padding: 8px 12px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}
.pnl-table tbody td {
    padding: 8px 12px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border); vertical-align: middle;
    color: var(--c-muted);
}
.pnl-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.pnl-best  { background: rgba(38,166,154,.06)  !important; }
.pnl-worst { background: rgba(239,83,80,.05)   !important; }
.best-tag  { display: inline-block; background: var(--c-teal); color: #000; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; margin-left: 4px; }
.worst-tag { display: inline-block; background: var(--c-red);  color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; margin-left: 4px; }

/* P&L placeholder */
#pnl-placeholder { text-align: center; padding: 32px; color: var(--c-muted); font-size: 12px; font-family: var(--f-mono); }
#pnl-placeholder i { font-size: 2rem; display: block; margin-bottom: 8px; opacity: .25; }
</style>

<div class="ids-wrap">

{{-- ══ HERO ══ --}}
<div class="ids-hero ids-anim">
    <div class="ids-hero-left">
        <div class="ids-hero-eyebrow">Breakout Analytics</div>
        <h1>Index-Driven <span>Signal Scanner</span></h1>
        <p>
            Detects intraday breakout signals using NIFTY FUT candles, then maps ATM option
            entry trades across all configured symbols at the next candle open.
        </p>
    </div>
    <div class="ids-hero-icon"><i class="las la-bolt"></i></div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="ids-filter-bar">
    <div class="ids-filter-inner">

        {{-- Symbol — single select --}}
        <span class="ids-filter-label">Symbol</span>
        <select id="ids-sym" class="ids-sym-select" onchange="idsAnalyze()">
            <option value="ALL">— All —</option>
        </select>

        <div class="ids-sep"></div>

        {{-- Date with nav --}}
        <span class="ids-filter-label">Date</span>
        <div class="ids-date-wrap">
            <button class="ids-date-nav" onclick="idsShiftDate(-1)">‹</button>
            <input type="date" id="ids-date" class="ids-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="idsAnalyze()">
            <button class="ids-date-nav" onclick="idsShiftDate(1)">›</button>
            <button class="ids-date-nav ids-today-btn" onclick="idsGoToday()">TODAY</button>
            <span id="ids-date-badge"></span>
        </div>

        <div class="ids-sep"></div>

        {{-- Threshold --}}
        <span class="ids-filter-label">Threshold</span>
        <div class="ids-thresh-wrap">
            <span class="ids-thresh-disp" id="ids-thresh-disp">30</span>
            <span style="font-size:10px;color:var(--c-muted);">pts</span>
            <input type="range" id="ids-thresh" class="ids-range" min="5" max="300" step="5" value="30">
        </div>

        <div class="ids-sep"></div>

        {{-- Signal filter --}}
        <span class="ids-filter-label">Signal</span>
        <select id="ids-signal" class="ids-generic-select" style="min-width:110px;" onchange="idsAnalyze()">
            <option value="BOTH">CE + PE</option>
            <option value="CE">CE Only</option>
            <option value="PE">PE Only</option>
        </select>

        <button class="ids-analyze-btn" onclick="idsAnalyze()">
            <i class="las la-bolt"></i> Analyze
        </button>
        <button class="ids-reset-btn" onclick="idsReset()">↺ Reset</button>

        <div class="ids-filter-right">
            <span class="ids-info-text" id="ids-info"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="ids-content">

    <div class="ids-warn" id="ids-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="ids-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="ids-stats ids-anim">
        <div class="ids-stat-card s-total"><div class="ids-stat-label">Total Trades</div><div class="ids-stat-val" id="st-total">—</div></div>
        <div class="ids-stat-card s-ce">  <div class="ids-stat-label">CE Signals</div> <div class="ids-stat-val" id="st-ce">—</div></div>
        <div class="ids-stat-card s-pe">  <div class="ids-stat-label">PE Signals</div> <div class="ids-stat-val" id="st-pe">—</div></div>
        <div class="ids-stat-card s-syms"><div class="ids-stat-label">Symbols</div>    <div class="ids-stat-val" id="st-syms">—</div></div>
        <div class="ids-stat-card s-inv"> <div class="ids-stat-label">Total Inv.</div> <div class="ids-stat-val" id="st-inv">—</div></div>
    </div>

    {{-- Signal table --}}
    <div class="ids-card ids-anim">
        <div class="ids-card-header">
            <div class="ids-card-title">⚡ Index-Driven Breakout Signals</div>
            <span class="ids-card-subtitle" id="ids-subtitle">Detecting last available date…</span>
        </div>
        <div class="ids-tscroll">
            <table class="ids-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="3" class="g-info">Info</th>
                        <th colspan="4" class="g-nifty sep-nifty">▲ NIFTY Signal</th>
                        <th colspan="4" class="g-option sep-option">◆ ATM Option</th>
                        <th colspan="3" class="g-entry sep-entry">▶ Entry</th>
                    </tr>
                    <tr class="th-cols">
                        <th>#</th><th>Date</th><th>Symbol</th>
                        <th class="sep-nifty">Signal</th>
                        <th>NIFTY Open<br><span style="font-size:7px;font-weight:400;opacity:.5;">09:15</span></th>
                        <th>Trigger Val</th>
                        <th>Signal Bar<br><span style="font-size:7px;font-weight:400;opacity:.5;">time</span></th>
                        <th>Move (pts)</th>
                        <th class="sep-option">Strike</th>
                        <th>OI</th><th>Expiry</th><th>Lot Size</th>
                        <th class="sep-entry">Buy Time<br><span style="font-size:7px;font-weight:400;opacity:.5;">next candle</span></th>
                        <th>Buy Price</th><th>Investment</th>
                    </tr>
                </thead>
                <tbody id="ids-tbody">
                    <tr><td colspan="15">
                        <div class="ids-spinner-row">
                            <div class="ids-spinner"></div>
                            Detecting last available date…
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Exit P&L section --}}
    <div class="ids-pnl-section ids-anim">
        <div class="ids-pnl-header">
            <div class="ids-pnl-header-title">📈 Exit P&amp;L — Aggregate All-Symbol Exit Scenarios</div>
            <div class="ids-pnl-callout">
                <strong>How this works:</strong> After the breakout signal fires and we buy ATM options
                across all configured symbols at the next candle's open, this shows the aggregate P&amp;L
                if you exit <strong>all positions simultaneously</strong> at every subsequent candle open.
                Run Analyze first, then load CE or PE exit tables.
            </div>
            <div class="ids-pnl-btn-row">
                <button class="ids-pnl-btn ce" onclick="idsLoadPnl('CE')">▲ Load CE Exit P&amp;L</button>
                <button class="ids-pnl-btn pe" onclick="idsLoadPnl('PE')">▼ Load PE Exit P&amp;L</button>
            </div>
        </div>
        <div class="ids-pnl-body">
            <div id="ce-pnl-wrap" style="display:none;"></div>
            <div id="pe-pnl-wrap" style="display:none;"></div>
            <div id="pnl-placeholder">
                <i class="las la-chart-line"></i>
                Run Analyze, then click a button above to load exit scenarios.
            </div>
        </div>
    </div>

</div>{{-- /.ids-content --}}
</div>{{-- /.ids-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════
//  Index-Driven Signal Scanner — JS (no logic changes)
// ═══════════════════════════════════════════════════════════

var IDS_ANALYZE  = '{{ route("index-driven-signal.analyze") }}';
var IDS_SYMBOLS  = '{{ route("index-driven-signal.symbols") }}';
var IDS_PNL      = '{{ route("index-driven-signal.exit-pnl") }}';
var IDS_LASTDATE = '{{ route("index-driven-signal.last.date") }}';
var IDS_TODAY    = '{{ now()->toDateString() }}';

var idsSymCache = null;
var idsLastData = [];

function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

// Threshold slider
el('ids-thresh').addEventListener('input', function () {
    txt('ids-thresh-disp', this.value);
});

// ═══════════════════════════════════════════════════════════
//  BOOT — detect last available date then auto-analyze
// ═══════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    idsResolveLastDateAndLoad();
});

function idsResolveLastDateAndLoad() {
    fetch(IDS_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.last_date) el('ids-date').value = res.last_date;
            idsLoadSymbols(function () { idsAnalyze(); });
        })
        .catch(function () {
            idsLoadSymbols(function () { idsAnalyze(); });
        });
}

// ── Date helpers ──────────────────────────────────────────

function idsGetDate() { return el('ids-date').value; }

function idsShiftDate(d) {
    var picker = el('ids-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > IDS_TODAY) return;
    picker.value = s;
    idsAnalyze();
}

function idsGoToday() {
    el('ids-date').value = IDS_TODAY;
    idsAnalyze();
}

function idsUpdateDateBadge(isToday) {
    el('ids-date-badge').innerHTML = isToday
        ? '<span class="ids-live-badge">● Live</span>'
        : '<span class="ids-hist-badge">📅 Historical</span>';
}

// ── Symbols — single select ───────────────────────────────

function idsLoadSymbols(callback) {
    if (idsSymCache !== null) {
        idsRebuildSym(idsSymCache);
        if (callback) callback();
        return;
    }

    fetch(IDS_SYMBOLS, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.no_config) {
                idsShowWarn(res.message || '');
                idsSymCache = [];
                idsRebuildSym([]);
            } else {
                idsHideWarn();
                idsSymCache = res.symbols || [];
                idsRebuildSym(idsSymCache);
            }
            if (callback) callback();
        })
        .catch(function () { if (callback) callback(); });
}

function idsRebuildSym(syms) {
    var sel  = el('ids-sym');
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

function idsAnalyze() {
    var date = idsGetDate();
    var sig  = el('ids-signal').value;
    var thr  = el('ids-thresh').value;
    var sym  = el('ids-sym').value;

    if (!date) return;

    idsHideWarn();
    idsResetStats();
    idsLastData = [];
    el('ce-pnl-wrap').style.display = 'none';
    el('pe-pnl-wrap').style.display = 'none';
    el('pnl-placeholder').style.display = 'block';

    html('ids-tbody', '<tr><td colspan="15"><div class="ids-spinner-row">'
        + '<div class="ids-spinner"></div>'
        + 'Scanning NIFTY FUT for ' + thr + 'pt breakout signals on ' + date + '…'
        + '</div></td></tr>');
    txt('ids-subtitle', date + ' · Scanning…');

    var params = new URLSearchParams({ date: date, filter: sig, threshold: thr });
    if (sym && sym !== 'ALL') {
        params.append('symbols[]', sym);
    }

    fetch(IDS_ANALYZE + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { if (!r.ok) throw new Error('Server error ' + r.status); return r.json(); })
    .then(function (res) {
        if (typeof res.is_today !== 'undefined') {
            idsUpdateDateBadge(res.is_today);
        }

        if (res.available_symbols && res.available_symbols.length) {
            idsSymCache = res.available_symbols;
            idsRebuildSym(idsSymCache);
            if (sym && sym !== 'ALL') el('ids-sym').value = sym;
        }

        if (res.no_config) { idsShowWarn(res.message); idsEmptyTable('No active config.'); return; }

        if (!res.success || !res.data || !res.data.length) {
            idsEmptyTable(res.message || 'No signals found for this date.');
            txt('ids-subtitle', date + ' · No signals found');
            return;
        }

        idsLastData = res.data;
        idsRenderTable(res.data);
        idsUpdateStats(res);

        el('ids-info').innerHTML =
            'Threshold: <span style="color:var(--c-lime);">' + res.threshold + 'pts</span>'
            + ' &nbsp;·&nbsp; Signals: <span style="color:var(--c-amber);">' + res.trigger_count + '</span>'
            + ' &nbsp;·&nbsp; ' + res.message;
        txt('ids-subtitle', date + ' · ' + res.message);
    })
    .catch(function (err) { idsEmptyTable('⚠ ' + err.message); });
}

// ── Render ────────────────────────────────────────────────

function idsRenderTable(data) {
    var h         = '', num = 1;
    var lastGroup = null;

    data.forEach(function (r, i) {
        var groupKey = r.date + '|' + r.signal_type + '|' + r.trigger_time;
        var isCE     = r.signal_type === 'CE';

        if (groupKey !== lastGroup) {
            var moveSign = r.nifty_move >= 0 ? '+' : '';
            h += '<tr class="ids-group-row' + (isCE ? '' : ' gr-pe') + '">'
                + '<td colspan="15">'
                + r.date + ' &nbsp;|&nbsp; '
                + (isCE ? '📈 CE BREAKOUT' : '📉 PE BREAKOUT')
                + ' &nbsp;|&nbsp; NIFTY Open: ₹' + r.nifty_open.toFixed(2)
                + ' → Trigger: ₹' + r.nifty_trigger.toFixed(2)
                + ' &nbsp;|&nbsp; Bar: ' + r.trigger_time
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
            + '<td class="c-sym">'  + esc(r.symbol) + '</td>'
            + '<td class="sep-nifty">' + (isCE ? '<span class="sig-ce">📈 CE</span>' : '<span class="sig-pe">📉 PE</span>') + '</td>'
            + '<td class="c-val">₹' + r.nifty_open.toFixed(2) + '</td>'
            + '<td class="c-val ' + (isCE ? 'up' : 'dn') + '">₹' + r.nifty_trigger.toFixed(2) + '</td>'
            + '<td><span class="time-badge' + (isCE ? '' : ' pe') + '">' + r.trigger_time + '</span></td>'
            + '<td><span class="' + moveCls + '">' + moveSign + r.nifty_move.toFixed(2) + '</span></td>'
            + '<td class="sep-option c-val" style="color:var(--c-amber);">₹' + fmtOI(r.strike) + '</td>'
            + '<td class="c-sm">' + fmtOI(r.strike_oi) + '</td>'
            + '<td class="c-sm">' + (r.expiry_date || '—') + '</td>'
            + '<td class="c-sm">' + r.lot_size + '</td>'
            + '<td class="sep-entry"><span class="time-badge buy">' + r.buy_time + '</span></td>'
            + '<td><strong class="up">₹' + r.buy_price.toFixed(2) + '</strong></td>'
            + '<td><strong style="color:var(--c-text);">₹' + fmt2(r.investment) + '</strong></td>'
            + '</tr>';
    });

    html('ids-tbody', h || idsEmptyHtml('No results.'));
}

// ── P&L ───────────────────────────────────────────────────

function idsLoadPnl(type) {
    if (!idsLastData.length) { alert('Please run Analyze first.'); return; }

    var date = idsGetDate();
    var thr  = el('ids-thresh').value;
    var sym  = el('ids-sym').value;

    var wrapId = type.toLowerCase() + '-pnl-wrap';
    el('pnl-placeholder').style.display = 'none';
    el(wrapId).style.display = 'block';
    el(wrapId).innerHTML = '<div class="ids-pnl-card type-' + type.toLowerCase() + '">'
        + '<div class="ids-pnl-card-hdr">' + (type === 'CE' ? '▲ CE Exit P&L' : '▼ PE Exit P&L') + '</div>'
        + '<div class="ids-spinner-row"><div class="ids-spinner" style="border-top-color:' + (type === 'CE' ? 'var(--c-teal)' : 'var(--c-red)') + '"></div>Computing ' + type + ' exits…</div>'
        + '</div>';

    var params = new URLSearchParams({ date: date, filter: type, threshold: thr });
    if (sym && sym !== 'ALL') {
        params.append('symbols[]', sym);
    }

    fetch(IDS_PNL + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var slots = res[type.toLowerCase()] || [];
            if (!slots.length) {
                el(wrapId).innerHTML = '<div class="ids-pnl-card type-' + type.toLowerCase() + '">'
                    + '<div class="ids-pnl-card-hdr">' + (type === 'CE' ? '▲ CE' : '▼ PE') + ' Exit P&L</div>'
                    + '<div style="text-align:center;padding:24px;color:var(--c-muted);font-size:12px;font-family:var(--f-mono);">No exit data found.</div></div>';
                return;
            }
            idsRenderPnl(type, slots, wrapId);
        })
        .catch(function (err) {
            el(wrapId).innerHTML = '<div style="text-align:center;padding:24px;color:var(--c-red);font-size:12px;font-family:var(--f-mono);">⚠ Error: ' + err.message + '</div>';
        });
}

function idsRenderPnl(type, slots, wrapId) {
    var maxP = Math.max.apply(null, slots.map(function (r) { return r.profit; }));
    var minP = Math.min.apply(null, slots.map(function (r) { return r.profit; }));
    var h    = '';

    slots.forEach(function (row) {
        var isBest  = row.profit === maxP;
        var isWorst = row.profit === minP && row.profit < 0;
        var rowCls  = isBest ? 'pnl-best' : isWorst ? 'pnl-worst' : '';
        var plCls   = row.profit >= 0 ? 'up' : 'dn';
        var roiCls  = row.roi    >= 0 ? 'up' : 'dn';
        var plSign  = row.profit >= 0 ? '+' : '';
        var rSign   = row.roi    >= 0 ? '+' : '';

        h += '<tr class="' + rowCls + '">'
            + '<td><span class="time-badge' + (type === 'PE' ? ' pe' : '') + '">' + row.exit_time + '</span>'
                + (isBest  ? '<span class="best-tag">BEST</span>'   : '')
                + (isWorst ? '<span class="worst-tag">WORST</span>' : '') + '</td>'
            + '<td><strong style="color:var(--c-amber);">₹' + fmt2(row.sell_total)    + '</strong></td>'
            + '<td><strong style="color:var(--c-text);">₹' + fmt2(row.investment)    + '</strong></td>'
            + '<td><strong class="' + plCls  + '">' + plSign + '₹' + fmt2(Math.abs(row.profit)) + '</strong></td>'
            + '<td><strong class="' + roiCls + '">' + rSign  + Math.abs(row.roi).toFixed(2) + '%</strong></td>'
            + '<td class="c-sm">' + row.trade_count + '</td>'
            + '</tr>';
    });

    el(wrapId).innerHTML = '<div class="ids-pnl-card type-' + type.toLowerCase() + '">'
        + '<div class="ids-pnl-card-hdr">' + (type === 'CE' ? '▲ CE' : '▼ PE') + ' Exit P&L &nbsp;<span style="font-size:10px;font-weight:400;color:var(--c-muted);">(' + slots.length + ' exit slots)</span></div>'
        + '<div class="ids-tscroll"><table class="pnl-table">'
        + '<thead><tr><th>Exit Time</th><th>Sell Value</th><th>Investment</th><th>Profit/Loss</th><th>ROI %</th><th>Trades</th></tr></thead>'
        + '<tbody>' + h + '</tbody></table></div></div>';
}

// ── Stats / helpers ───────────────────────────────────────

function idsUpdateStats(res) {
    txt('st-total', res.total_records || '0');
    txt('st-ce',   res.ce_count      || '0');
    txt('st-pe',   res.pe_count      || '0');
    txt('st-syms', res.symbol_count  || '0');
    txt('st-inv',  '₹' + Number(res.total_investment || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 }));
}

function idsResetStats() {
    ['st-total','st-ce','st-pe','st-syms'].forEach(function (id) { txt(id, '—'); });
    txt('st-inv', '—');
}

function idsShowWarn(msg) { el('ids-warn').classList.add('show'); txt('ids-warn-msg', msg || ''); }
function idsHideWarn()    { el('ids-warn').classList.remove('show'); }
function idsEmptyTable(msg) { html('ids-tbody', idsEmptyHtml(msg)); }
function idsEmptyHtml(msg) {
    return '<tr><td colspan="15"><div class="ids-empty">'
        + '<div class="ids-empty-icon"><i class="las la-bolt"></i></div>'
        + '<p>' + (msg || 'No data found.') + '</p></div></td></tr>';
}

function idsReset() {
    fetch(IDS_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            el('ids-date').value   = res.last_date || IDS_TODAY;
            el('ids-thresh').value = '30';
            txt('ids-thresh-disp', '30');
            el('ids-signal').value = 'BOTH';
            el('ids-sym').value    = 'ALL';
            idsHideWarn();
            idsResetStats();
            idsLastData = [];
            el('ce-pnl-wrap').style.display = 'none';
            el('pe-pnl-wrap').style.display = 'none';
            el('pnl-placeholder').style.display = 'block';
            idsAnalyze();
        })
        .catch(function () {
            el('ids-date').value   = IDS_TODAY;
            el('ids-thresh').value = '30';
            txt('ids-thresh-disp', '30');
            el('ids-signal').value = 'BOTH';
            el('ids-sym').value    = 'ALL';
            idsHideWarn();
            idsAnalyze();
        });
}

function fmtOI(v) {
    var n = Number(v) || 0;
    if (n >= 1e7) return (n/1e7).toFixed(2) + 'Cr';
    if (n >= 1e5) return (n/1e5).toFixed(2) + 'L';
    if (n >= 1e3) return (n/1e3).toFixed(1) + 'K';
    return n.toLocaleString('en-IN');
}
function fmt2(v) { return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 }); }
function esc(s)  { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
@endpush