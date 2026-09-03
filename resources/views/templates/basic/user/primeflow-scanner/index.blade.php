{{-- FILE: resources/views/themes/{active_theme}/user/primeflow-scanner/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — PRIMEFLOW SCANNER  v2.1
   Dark terminal · Matches Pivot Analysis design system
   v2.1 adds: per-symbol history mode + logic modal
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

.pf-wrap {
    font-family: var(--f-sans);
    color: var(--c-text);
    background: var(--c-bg);
}
.pf-wrap * { box-sizing: border-box; }
.mono { font-family: var(--f-mono); }

@keyframes pfFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.pf-anim { animation: pfFadeUp .5s ease both; }
@keyframes pfSpin  { to { transform: rotate(360deg); } }

/* ══ HERO ═════════════════════════════════════ */
.pf-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.pf-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.pf-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.pf-hero-left { position: relative; z-index: 1; }
.pf-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.pf-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.pf-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.pf-hero h1 span { color: var(--c-lime); }
.pf-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 560px; }

/* Signal pills */
.pf-hero-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px; }
.pf-pill {
    display: inline-block; padding: 3px 10px; border-radius: 4px;
    font-family: var(--f-mono); font-size: 10px; font-weight: 600; letter-spacing: .04em;
}
.pf-pill-score  { background: rgba(255,167,38,.1);  color: var(--c-amber);  border: 1px solid rgba(255,167,38,.25); }
.pf-pill-call   { background: rgba(38,166,154,.1);  color: var(--c-teal);   border: 1px solid rgba(38,166,154,.25); }
.pf-pill-put    { background: rgba(239,83,80,.08);  color: var(--c-red);    border: 1px solid rgba(239,83,80,.2);   }
.pf-pill-trap   { background: rgba(171,71,188,.1);  color: var(--c-purple); border: 1px solid rgba(171,71,188,.25); }

/* Hero icon */
.pf-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--f-display); font-size: 22px; font-weight: 800;
    color: var(--c-lime); flex-shrink: 0; letter-spacing: -1px;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}

@media (max-width: 768px) {
    .pf-hero { flex-direction: column; padding: 24px 18px; text-align: center; }
    .pf-hero-pills { justify-content: center; }
}

/* ══ FILTER BAR ═══════════════════════════════ */
.pf-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.pf-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.pf-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.pf-sep { width: 1px; height: 26px; background: var(--c-border2); flex-shrink: 0; }

/* Symbol select */
.pf-symbol-select {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer; max-width: 190px;
    transition: border-color .2s;
}
.pf-symbol-select:focus { border-color: rgba(125,255,0,.45); }

/* Scan-all vs history mode toggling */
.pf-scan-controls, .pf-history-controls {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.pf-scan-controls.hide, .pf-history-controls { display: none; }
.pf-history-controls.show { display: flex; }
.pf-scan-controls.hide { display: none !important; }

/* Date controls */
.pf-date-wrap { display: flex; align-items: center; gap: 4px; }
.pf-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.pf-date-input:focus { border-color: rgba(125,255,0,.45); }
.pf-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.pf-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.pf-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.pf-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Live / hist badges */
.pf-live-badge { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.pf-hist-badge { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Action buttons */
.pf-scan-btn {
    background: var(--c-lime); color: #000; border: none; border-radius: 7px;
    padding: 7px 18px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(125,255,0,.2);
    display: inline-flex; align-items: center; gap: 6px;
}
.pf-scan-btn:hover { background: #8FFF1A; box-shadow: 0 0 22px rgba(125,255,0,.35); transform: translateY(-1px); }
.pf-auto-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: var(--f-mono);
    transition: all .2s; letter-spacing: .05em;
}
.pf-auto-btn.on { border-color: rgba(38,166,154,.35); background: rgba(38,166,154,.1); color: var(--c-teal); }
.pf-auto-btn:hover:not(.on) { border-color: var(--c-border2); color: var(--c-text); }

/* Logic modal trigger button */
.pf-logic-btn {
    background: transparent; border: 1px solid rgba(0,184,212,.3);
    color: var(--c-blue); border-radius: 7px; padding: 6px 13px;
    font-family: var(--f-mono); font-size: 10px; font-weight: 700;
    letter-spacing: .06em; cursor: pointer; transition: all .2s;
    display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
}
.pf-logic-btn:hover { background: rgba(0,184,212,.1); border-color: rgba(0,184,212,.5); }

/* Filter pills */
.pf-pills-wrap { display: flex; gap: 4px; flex-wrap: wrap; }
.pf-fp {
    padding: 5px 13px; border-radius: 20px; font-family: var(--f-sans);
    font-size: 11px; font-weight: 700; cursor: pointer;
    border: 1px solid var(--c-border2);
    background: transparent; color: var(--c-muted); transition: all .15s;
}
.pf-fp:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.pf-fp.active         { background: var(--c-lime-dim); border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.pf-fp.active-call    { background: rgba(38,166,154,.1);  border-color: rgba(38,166,154,.3);  color: var(--c-teal); }
.pf-fp.active-put     { background: rgba(239,83,80,.08);  border-color: rgba(239,83,80,.3);   color: var(--c-red);  }

.pf-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.pf-last-upd { font-size: 10px; color: rgba(120,123,134,.5); font-family: var(--f-mono); }

@media (max-width: 768px) {
    .pf-filter-bar { padding: 0 16px; }
    .pf-filter-inner { gap: 8px; }
    .pf-filter-right { margin-left: 0; width: 100%; }
}

/* ══ CONTENT ══════════════════════════════════ */
.pf-content { padding: 24px 32px 64px; }
@media (max-width: 768px) { .pf-content { padding: 16px 12px 48px; } }

/* Config warning */
.pf-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.pf-warn.show { display: flex; }
.pf-warn i { font-size: 18px; flex-shrink: 0; }
.pf-warn strong { color: #fff; }

/* ══ STATS ════════════════════════════════════ */
.pf-stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
.pf-stat {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; padding: 12px 16px; min-width: 100px; flex: 1;
}
.pf-stat small {
    display: block; font-family: var(--f-mono); font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em; color: var(--c-muted); margin-bottom: 6px;
}
.pf-stat strong { display: block; font-family: var(--f-mono); font-size: 1.2rem; font-weight: 700; }
.ps-total { border-left: 2px solid rgba(0,184,212,.5); }
.ps-call  { border-left: 2px solid rgba(38,166,154,.5); }
.ps-put   { border-left: 2px solid rgba(239,83,80,.5); }
.ps-trap  { border-left: 2px solid rgba(171,71,188,.5); }
.ps-wait  { border-left: 2px solid rgba(125,255,0,.4); }

/* ══ CARD ═════════════════════════════════════ */
.pf-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    margin-bottom: 20px; position: relative;
}
.pf-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.pf-card-hdr {
    padding: 13px 18px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    background: rgba(0,0,0,.2);
}
.pf-card-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: var(--c-text); display: flex; align-items: center; gap: 8px;
}
.pf-card-info { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }

/* ══ TABLE ════════════════════════════════════ */
.pf-tscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.pf-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 980px; }

/* Header rows */
.pf-table thead tr.th-group th {
    padding: 8px 10px 4px; text-align: center;
    font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    background: var(--c-panel); border-bottom: none; white-space: nowrap;
}
.pf-table thead tr.th-cols th {
    padding: 4px 10px 8px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}

/* Column group colors */
.g-info   { color: var(--c-muted)   !important; }
.g-trade  { color: var(--c-amber)   !important; }
.g-entry  { color: var(--c-blue)    !important; }
.g-signal { color: var(--c-teal)    !important; }

/* Separator borders */
.sep-trade  { border-left: 1px solid rgba(255,167,38,.15)  !important; }
.sep-entry  { border-left: 1px solid rgba(0,184,212,.12)   !important; }
.sep-signal { border-left: 1px solid rgba(38,166,154,.15)  !important; }

/* Body cells */
.pf-table tbody td {
    padding: 8px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.pf-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.tr-even { background: var(--c-surface); }
.tr-odd  { background: rgba(0,0,0,.15); }
.tr-call { background: rgba(38,166,154,.04)  !important; border-left: 2px solid rgba(38,166,154,.5) !important; }
.tr-put  { background: rgba(239,83,80,.04)   !important; border-left: 2px solid rgba(239,83,80,.5)  !important; }
.tr-wait { opacity: .65; }

/* Cell value styles */
.c-num    { font-size: 9px; color: rgba(120,123,134,.35); }
.c-sym    {
    display: inline-block; padding: 3px 10px; border-radius: 5px;
    font-size: 11px; font-weight: 700; color: var(--c-blue);
    background: rgba(0,184,212,.07); border: 1px solid rgba(0,184,212,.15);
}
.c-date   {
    display: inline-block; padding: 3px 10px; border-radius: 5px;
    font-size: 11px; font-weight: 700; color: var(--c-lime);
    background: rgba(125,255,0,.06); border: 1px solid rgba(125,255,0,.18);
}
.c-entry  { color: var(--c-blue);   font-weight: 700; }
.c-target { color: var(--c-teal);   font-weight: 700; }
.c-sl     { color: var(--c-red);    font-weight: 700; }
.c-pcr    { font-size: 10px; color: var(--c-muted); }
.c-strike { color: var(--c-amber);  font-weight: 700; }
.c-time   { color: var(--c-lime);   font-weight: 700; }
.c-strsym { display: block; font-size: 8px; color: var(--c-muted); margin-top: 1px; }

/* Signal badges */
.sig-call { display:inline-block; background:rgba(38,166,154,.12); color:#4DB6AC; border:1px solid rgba(38,166,154,.3); border-radius:5px; padding:4px 11px; font-family:var(--f-sans); font-size:11px; font-weight:700; }
.sig-put  { display:inline-block; background:rgba(239,83,80,.1);   color:#EF9A9A; border:1px solid rgba(239,83,80,.28); border-radius:5px; padding:4px 11px; font-family:var(--f-sans); font-size:11px; font-weight:700; }
.sig-wait { display:inline-block; background:var(--c-panel); color:var(--c-muted); border:1px solid var(--c-border2); border-radius:5px; padding:4px 10px; font-family:var(--f-sans); font-size:10px; }
.sig-nd   { color: rgba(120,123,134,.35); font-size: 10px; font-family: var(--f-mono); }

/* Futures direction */
.fd-bull { color: var(--c-teal); font-size: 11px; font-weight: 700; }
.fd-bear { color: var(--c-red);  font-size: 11px; font-weight: 700; }
.fd-side { color: var(--c-muted); font-size: 10px; }

/* Score bar */
.score-wrap  { display: flex; align-items: center; gap: 6px; justify-content: center; }
.score-num   { font-size: 12px; font-weight: 700; min-width: 18px; }
.score-track { width: 48px; height: 4px; background: var(--c-panel); border-radius: 2px; overflow: hidden; }
.score-fill  { height: 100%; border-radius: 2px; }

/* Signal dots */
.sig-dots { display: flex; align-items: center; gap: 3px; justify-content: center; flex-wrap: wrap; }
.sd       { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.sd-call  { background: var(--c-teal); box-shadow: 0 0 4px rgba(38,166,154,.5); }
.sd-put   { background: var(--c-red);  box-shadow: 0 0 4px rgba(239,83,80,.5);  }
.sd-trap  { background: var(--c-purple); box-shadow: 0 0 4px rgba(171,71,188,.5); }
.sd-off   { background: rgba(120,123,134,.2); }

/* ══ LOADING / EMPTY ══════════════════════════ */
.pf-loading {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 56px 20px;
}
.pf-spinner {
    width: 32px; height: 32px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-lime);
    border-radius: 50%;
    animation: pfSpin .9s linear infinite;
}
.pf-loading-txt { color: var(--c-muted); margin-top: 12px; font-size: 12px; font-family: var(--f-mono); }

.pf-empty {
    text-align: center; padding: 52px 20px; color: var(--c-muted);
}
.pf-empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 22px;
}
.pf-empty p { font-size: 12px; font-family: var(--f-mono); margin-top: 4px; }

/* ══ LOGIC MODAL — dark terminal override (Bootstrap bs- classes) ═══ */
#pfLogicModal .modal-content {
    background: var(--c-surface); border: 1px solid var(--c-border2);
    color: var(--c-text); border-radius: 12px;
}
#pfLogicModal .modal-header { border-bottom: 1px solid var(--c-border); padding: 18px 22px; }
#pfLogicModal .modal-footer { border-top: 1px solid var(--c-border); padding: 14px 22px; }
#pfLogicModal .modal-body   { padding: 4px 22px 22px; }
#pfLogicModal .modal-title  { font-family: var(--f-display); font-weight: 800; color: #fff; font-size: 18px; }
#pfLogicModal .modal-title span { color: var(--c-lime); }
#pfLogicModal .btn-close    { filter: invert(1); opacity: .55; }
#pfLogicModal .btn-close:hover { opacity: .9; }
#pfLogicModal h6 {
    font-family: var(--f-display); color: var(--c-lime); font-size: 12.5px;
    letter-spacing: .05em; text-transform: uppercase; margin: 22px 0 10px;
    display: flex; align-items: center; gap: 8px;
}
#pfLogicModal h6::before { content: ''; width: 12px; height: 1px; background: var(--c-lime); display: inline-block; }
#pfLogicModal h6:first-child { margin-top: 0; }
#pfLogicModal p { font-size: 12.5px; color: var(--c-muted); line-height: 1.75; margin-bottom: 8px; }
#pfLogicModal .table { color: var(--c-text); font-family: var(--f-mono); font-size: 11.5px; margin-bottom: 0; }
#pfLogicModal .table > :not(caption) > * > * { background: transparent; border-color: var(--c-border); color: var(--c-text); box-shadow: none; }
#pfLogicModal .table thead th {
    color: var(--c-muted); font-family: var(--f-sans); font-size: 9.5px;
    text-transform: uppercase; letter-spacing: .07em; border-bottom-width: 1px; font-weight: 700;
}
#pfLogicModal .badge-w {
    font-family: var(--f-mono); font-weight: 700; padding: 2px 9px; border-radius: 5px;
    background: rgba(255,167,38,.1); color: var(--c-amber); border: 1px solid rgba(255,167,38,.25);
    display: inline-block; font-size: 11px;
}
#pfLogicModal code { background: var(--c-panel); color: var(--c-lime); padding: 1px 6px; border-radius: 4px; font-size: 11px; }
#pfLogicModal .pf-flow-step {
    display: flex; align-items: flex-start; gap: 10px; padding: 8px 0;
    border-bottom: 1px solid var(--c-border);
}
#pfLogicModal .pf-flow-step:last-child { border-bottom: none; }
#pfLogicModal .pf-flow-num {
    width: 20px; height: 20px; border-radius: 50%; background: var(--c-lime-dim);
    color: var(--c-lime); font-family: var(--f-mono); font-size: 10px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
}
</style>

<div class="pf-wrap">

{{-- ══ HERO ══ --}}
<div class="pf-hero pf-anim">
    <div class="pf-hero-left">
        <div class="pf-hero-eyebrow">Options Analytics</div>
        <h1>PrimeFlow <span>Option Scanner</span></h1>
        <p>
            Smart Entry Engine across all configured symbols — 7-signal confluence model
            running on live option &amp; futures candle data. Switch to a single symbol
            to pull its historical scan results across any date range.
        </p>
    </div>
    <div class="pf-hero-icon">PF</div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="pf-filter-bar">
    <div class="pf-filter-inner">

        <span class="pf-filter-label">Symbol</span>
        <select id="pf-symbol-select" class="pf-symbol-select" onchange="pfSymbolChanged()">
            <option value="">All Symbols</option>
            @foreach($symbols ?? [] as $sym)
                <option value="{{ $sym }}">{{ $sym }}</option>
            @endforeach
        </select>

        <div class="pf-sep"></div>

        {{-- Scan-all mode controls (default) --}}
        <div class="pf-scan-controls" id="pf-scan-controls">
            <span class="pf-filter-label">Date</span>
            <div class="pf-date-wrap">
                <button class="pf-date-nav" onclick="pfShiftDate(-1)">&#8249;</button>
                <input type="date" id="pf-date" class="pf-date-input"
                       value="{{ now()->toDateString() }}"
                       max="{{ now()->toDateString() }}"
                       onchange="pfScan()">
                <button class="pf-date-nav" onclick="pfShiftDate(1)">&#8250;</button>
                <button class="pf-date-nav pf-today-btn" onclick="pfToday()">TODAY</button>
                <span id="pf-date-badge"></span>
            </div>

            <button class="pf-scan-btn" onclick="pfScan()">&#9670; Scan All</button>
            <button class="pf-auto-btn" id="pf-auto-btn" onclick="pfToggleAuto()">&#9654; Auto 60s</button>
        </div>

        {{-- Single-symbol history mode controls (hidden until a symbol is chosen) --}}
        <div class="pf-history-controls" id="pf-history-controls">
            <span class="pf-filter-label">From</span>
            <input type="date" id="pf-hist-start" class="pf-date-input" max="{{ now()->toDateString() }}">
            <span class="pf-filter-label">To</span>
            <input type="date" id="pf-hist-end" class="pf-date-input" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
            <button class="pf-scan-btn" onclick="pfLoadHistory()">&#9670; Load History</button>
        </div>

        <div class="pf-sep"></div>

        <span class="pf-filter-label">Filter</span>
        <div class="pf-pills-wrap" id="pf-filter-pills">
            <div class="pf-fp active"  data-f="ALL"     onclick="pfSetFilter('ALL',this)">All</div>
            <div class="pf-fp"         data-f="CALL"    onclick="pfSetFilter('CALL',this)">&#8679; Call</div>
            <div class="pf-fp"         data-f="PUT"     onclick="pfSetFilter('PUT',this)">&#8681; Put</div>
            <div class="pf-fp"         data-f="TRADE"   onclick="pfSetFilter('TRADE',this)">&#128293; Trades</div>
            <div class="pf-fp"         data-f="NOTRADE" onclick="pfSetFilter('NOTRADE',this)">No Trade</div>
        </div>

        <div class="pf-filter-right">
            <button type="button" class="pf-logic-btn" data-bs-toggle="modal" data-bs-target="#pfLogicModal">
                <i class="las la-book-open"></i> View Logic
            </button>
            <span class="pf-last-upd" id="pf-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="pf-content">

    {{-- Config warning --}}
    <div class="pf-warn" id="pf-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="pf-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="pf-stats" id="pf-stats" style="display:none;">
        <div class="pf-stat ps-total"><small id="st-total-label">Total</small><strong id="st-total" style="color:var(--c-blue);">0</strong></div>
        <div class="pf-stat ps-call"><small>&#8679; Buy Call</small><strong id="st-call" style="color:var(--c-teal);">0</strong></div>
        <div class="pf-stat ps-put"><small>&#8681; Buy Put</small><strong id="st-put" style="color:var(--c-red);">0</strong></div>
        <div class="pf-stat ps-trap"><small>&#128375; MM Traps</small><strong id="st-trap" style="color:var(--c-purple);">0</strong></div>
        <div class="pf-stat ps-wait"><small>No Trade</small><strong id="st-wait" style="color:var(--c-lime);">0</strong></div>
    </div>

    {{-- Table card --}}
    <div class="pf-card">
        <div class="pf-card-hdr">
            <div class="pf-card-title" id="pf-card-title">&#9670; PrimeFlow Scanner &nbsp;·&nbsp; 15 Min</div>
            <span class="pf-card-info" id="pf-card-info"></span>
        </div>
        <div class="pf-tscroll">
            <table class="pf-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="3" class="g-info">Info</th>
                        <th colspan="2" class="g-trade  sep-trade">&#128200; Trade</th>
                        <th colspan="4" class="g-entry  sep-entry">Entry Details</th>
                        <th colspan="3" class="g-signal sep-signal">&#9889; Signals</th>
                    </tr>
                    <tr class="th-cols">
                        <th class="g-info">#</th>
                        <th class="g-info" style="text-align:left;padding-left:14px;" id="pf-col-symbol-head">Symbol</th>
                        <th class="g-info">Futures Dir</th>
                        <th class="g-trade  sep-trade">Signal</th>
                        <th class="g-trade">Entry Time</th>
                        <th class="g-entry  sep-entry">Strike</th>
                        <th class="g-entry">Entry &#8377;</th>
                        <th class="g-entry">Target &#8377;</th>
                        <th class="g-entry">SL &#8377;</th>
                        <th class="g-signal sep-signal">Score /17</th>
                        <th class="g-signal">Active Signals</th>
                        <th class="g-signal">PCR</th>
                    </tr>
                </thead>
                <tbody id="pf-tbody">
                    <tr><td colspan="12">
                        <div class="pf-empty">
                            <div class="pf-empty-icon"><i class="las la-bolt"></i></div>
                            <p>Select date and click Scan All</p>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.pf-content --}}

{{-- ══════════════════════════════════════════════════════════
     LOGIC MODAL — Bootstrap 5 (data-bs-*), full engine breakdown
══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="pfLogicModal" tabindex="-1" aria-labelledby="pfLogicModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pfLogicModalLabel">&#9670; PrimeFlow <span>— Complete Signal Logic</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <h6>Overview</h6>
                <p>
                    PrimeFlow runs a 7-signal "Smart Entry Engine" over every 15-minute candle of the
                    trading day, for every symbol in the active Analysis Config. Each signal that fires
                    on a candle adds weight to a Bull score (calls) and a Bear score (puts). The first
                    candle inside the entry window whose top score crosses the threshold becomes the
                    trade for that symbol/day — after that, the scanner stops looking for a better entry.
                </p>

                <h6>Data &amp; Scope</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            <tr><td style="width:38%;color:#fff;">Timeframe</td><td><code>15min</code> — hardcoded, no switching</td></tr>
                            <tr><td style="color:#fff;">Option candles</td><td><code>cp_option_ohlc_15min</code></td></tr>
                            <tr><td style="color:#fff;">Futures candles</td><td><code>cp_fut_ohlc_15min</code></td></tr>
                            <tr><td style="color:#fff;">Symbol scope</td><td>Active <code>analysis_configs</code> row + its <code>analysis_config_symbols</code></td></tr>
                        </tbody>
                    </table>
                </div>

                <h6>Signal Engine (max score 17)</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Signal</th><th>Weight</th><th>Fires when…</th></tr>
                        </thead>
                        <tbody>
                            <tr><td style="color:#fff;">Premium Expansion</td><td><span class="badge-w">+3</span></td><td>ATM option premium rises &gt; 10% while futures stay flat (&lt; 0.8% move)</td></tr>
                            <tr><td style="color:#fff;">OI Build</td><td><span class="badge-w">+2</span></td><td>ATM open interest rises &gt; 2% AND premium rises &gt; 5% in the same slot</td></tr>
                            <tr><td style="color:#fff;">Volume Spike</td><td><span class="badge-w">+2</span></td><td>ATM volume &gt; 1.3× the previous 15-min slot</td></tr>
                            <tr><td style="color:#fff;">Futures Direction</td><td><span class="badge-w">+2</span></td><td>Futures move &gt; 0.25% in one direction (bullish → call side, bearish → put side)</td></tr>
                            <tr><td style="color:#fff;">Gamma Squeeze</td><td><span class="badge-w">+2</span></td><td>Both ATM and ATM+1 strikes rise &gt; 12% together</td></tr>
                            <tr><td style="color:#fff;">Momentum Accel</td><td><span class="badge-w">+2</span></td><td>Two consecutive rising candles, with the second rise bigger than the first</td></tr>
                            <tr><td style="color:#fff;">MM Trap</td><td><span class="badge-w">+4</span></td><td>Futures price breaks through the call/put OI wall while premium keeps rising &gt; 10%</td></tr>
                        </tbody>
                    </table>
                </div>

                <h6>Trade Rule</h6>
                <div class="pf-flow-step">
                    <span class="pf-flow-num">1</span>
                    <div><strong style="color:#fff;">Score threshold</strong> — a trade only fires when the bull or bear score reaches <code>&ge; 6</code> out of 17.</div>
                </div>
                <div class="pf-flow-step">
                    <span class="pf-flow-num">2</span>
                    <div><strong style="color:#fff;">Entry window</strong> — signals are only actionable between <code>10:30</code> and <code>14:30</code>. Only the first qualifying candle is taken per symbol per day.</div>
                </div>
                <div class="pf-flow-step">
                    <span class="pf-flow-num">3</span>
                    <div><strong style="color:#fff;">Strike selection</strong> — ATM by default; if a Gamma Squeeze or MM Trap is active on the firing side, the engine steps one strike further out (ATM+1 for calls, ATM-1 for puts) for extra leverage.</div>
                </div>
                <div class="pf-flow-step">
                    <span class="pf-flow-num">4</span>
                    <div><strong style="color:#fff;">Target / Stoploss</strong> — Target = entry premium × <code>3.0</code>. Stoploss = entry premium × <code>0.5</code>.</div>
                </div>
                <div class="pf-flow-step">
                    <span class="pf-flow-num">5</span>
                    <div><strong style="color:#fff;">Confidence</strong> — <code>round(topScore / 17 × 100)</code>, capped at 100.</div>
                </div>

                <h6>Threshold Reference</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Constant</th><th>Value</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>PREMIUM_EXP_MIN</td><td>0.10 (10%)</td></tr>
                            <tr><td>FUTURES_FLAT_MAX</td><td>0.008 (0.8%)</td></tr>
                            <tr><td>OI_CHANGE_MIN</td><td>0.02 (2%)</td></tr>
                            <tr><td>OI_PREMIUM_MIN</td><td>0.05 (5%)</td></tr>
                            <tr><td>VOLUME_SPIKE_MIN</td><td>1.3×</td></tr>
                            <tr><td>FUTURES_MOMENTUM</td><td>0.0025 (0.25%)</td></tr>
                            <tr><td>GAMMA_THRESHOLD</td><td>0.12 (12%)</td></tr>
                            <tr><td>SCORE_THRESHOLD</td><td>6 / 17</td></tr>
                            <tr><td>TARGET_MULT / SL_MULT</td><td>3.0× / 0.5×</td></tr>
                        </tbody>
                    </table>
                </div>

                <h6>Reading the "Active Signals" dots</h6>
                <p>
                    Each row's dot strip mirrors the 7 signals in order — Premium Expansion,
                    OI Build, Volume Spike, Futures Direction, Gamma Squeeze, Momentum Accel, MM Trap
                    (purple). A lit dot means that signal was triggered on the entry candle
                    (or, for a No-Trade row, on the day's peak-score candle).
                </p>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm" style="background:var(--c-lime);color:#000;font-weight:700;font-family:var(--f-display);" data-bs-dismiss="modal">Got it</button>
            </div>
        </div>
    </div>
</div>

</div>{{-- /.pf-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  PRIMEFLOW SCANNER — Vanilla JS (no jQuery)
//  Two modes: 'scan' (all symbols, one date) / 'history' (one symbol, date range)
// ═══════════════════════════════════════════════════════════════

var PF_SCAN_URL    = '{{ route("primeflow-scanner.data") }}';
var PF_HISTORY_URL = '{{ route("primeflow-scanner.history") }}';
var PF_TODAY       = '{{ now()->toDateString() }}';

var pfFilter    = 'ALL';
var pfAutoTimer = null;
var pfResults   = [];
var pfMode      = 'scan'; // 'scan' | 'history'

// ── DOM helpers ───────────────────────────────────────────────
function pfHtml(id, h) { var e = document.getElementById(id); if (e) e.innerHTML = h; }
function pfText(id, t) { var e = document.getElementById(id); if (e) e.textContent = t; }

document.addEventListener('DOMContentLoaded', function () {
    pfUpdateDateBadge();

    // Default history range = last 7 days
    var d = new Date();
    d.setDate(d.getDate() - 7);
    document.getElementById('pf-hist-start').value = d.toISOString().split('T')[0];

    pfScan();
});

// ── Mode switching ───────────────────────────────────────────
function pfStopAuto() {
    if (pfAutoTimer) {
        clearInterval(pfAutoTimer); pfAutoTimer = null;
        var btn = document.getElementById('pf-auto-btn');
        btn.textContent = '▶ Auto 60s';
        btn.classList.remove('on');
    }
}

function pfSymbolChanged() {
    var sym = document.getElementById('pf-symbol-select').value;
    pfStopAuto();

    if (sym) {
        pfMode = 'history';
        document.getElementById('pf-scan-controls').classList.add('hide');
        document.getElementById('pf-history-controls').classList.add('show');
        pfText('pf-col-symbol-head', 'Date');
        pfText('pf-card-title', '◆ ' + sym + ' — History');
        pfText('st-total-label', 'Trading Days');
        pfLoadHistory();
    } else {
        pfMode = 'scan';
        document.getElementById('pf-scan-controls').classList.remove('hide');
        document.getElementById('pf-history-controls').classList.remove('show');
        pfText('pf-col-symbol-head', 'Symbol');
        pfText('pf-card-title', '◆ PrimeFlow Scanner  ·  15 Min');
        pfText('st-total-label', 'Total');
        pfScan();
    }
}

// ── Date helpers (scan-all mode) ───────────────────────────────
function pfShiftDate(d) {
    var picker = document.getElementById('pf-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > PF_TODAY) return;
    picker.value = s;
    pfUpdateDateBadge();
    pfScan();
}

function pfToday() {
    document.getElementById('pf-date').value = PF_TODAY;
    pfUpdateDateBadge();
    pfScan();
}

function pfUpdateDateBadge() {
    var d  = document.getElementById('pf-date').value;
    var el = document.getElementById('pf-date-badge');
    if (!el) return;
    el.innerHTML = d === PF_TODAY
        ? '<span class="pf-live-badge">&#11044; Live</span>'
        : '<span class="pf-hist-badge">&#128197; Historical</span>';
}

// ── Auto refresh (scan-all mode only) ───────────────────────────
function pfToggleAuto() {
    var btn = document.getElementById('pf-auto-btn');
    if (pfAutoTimer) {
        pfStopAuto();
    } else {
        if (document.getElementById('pf-date').value !== PF_TODAY) return;
        pfAutoTimer = setInterval(pfScan, 60000);
        btn.textContent = '■ Stop';
        btn.classList.add('on');
        pfScan();
    }
}

// ── Filter ────────────────────────────────────────────────────
function pfSetFilter(f, btn) {
    pfFilter = f;
    document.querySelectorAll('#pf-filter-pills .pf-fp').forEach(function (b) {
        b.classList.remove('active', 'active-call', 'active-put');
    });
    btn.classList.add(f === 'CALL' ? 'active-call' : f === 'PUT' ? 'active-put' : 'active');
    pfApplyFilter();
}

function pfApplyFilter() {
    document.querySelectorAll('#pf-tbody tr[data-sig]').forEach(function (row) {
        var sig  = row.dataset.sig;
        var show = pfFilter === 'ALL'
            || (pfFilter === 'CALL'    && sig === 'BUY_CALL')
            || (pfFilter === 'PUT'     && sig === 'BUY_PUT')
            || (pfFilter === 'TRADE'   && (sig === 'BUY_CALL' || sig === 'BUY_PUT'))
            || (pfFilter === 'NOTRADE' && sig === 'NO TRADE');
        row.style.display = show ? '' : 'none';
    });
}

// ── Main scan — ALL symbols, ONE date ───────────────────────────
function pfScan() {
    var date = document.getElementById('pf-date').value;

    if (pfAutoTimer && date !== PF_TODAY) pfStopAuto();

    pfUpdateDateBadge();
    pfShowLoading();

    var params = new URLSearchParams({ date: date });

    fetch(PF_SCAN_URL + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (res.no_config) {
            pfShowWarn(res.message);
            pfEmptyTable('');
            return;
        }
        if (!res.success) {
            pfHideWarn();
            pfEmptyTable(res.message || 'No data available.');
            return;
        }

        pfHideWarn();
        pfResults = res.results || [];
        pfRenderStats({
            total: res.total_symbols,
            calls: pfResults.filter(function (r) { return r.signal === 'BUY_CALL'; }).length,
            puts:  pfResults.filter(function (r) { return r.signal === 'BUY_PUT';  }).length,
            traps: pfCountTraps(pfResults),
            waits: pfResults.filter(function (r) { return r.signal === 'NO TRADE'; }).length,
        });
        pfRenderTable(pfResults, 'scan');
        pfApplyFilter();

        pfText('pf-card-info', res.total_symbols + ' symbols · scanned at ' + res.scanned_at);
        pfText('pf-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) {
        pfHideWarn();
        pfEmptyTable('&#9888; ' + err.message);
    });
}

// ── History load — ONE symbol, DATE RANGE ───────────────────────
function pfLoadHistory() {
    var sym   = document.getElementById('pf-symbol-select').value;
    var start = document.getElementById('pf-hist-start').value;
    var end   = document.getElementById('pf-hist-end').value;
    if (!sym || !start || !end) return;

    pfShowLoading();

    var params = new URLSearchParams({ symbol: sym, start_date: start, end_date: end });

    fetch(PF_HISTORY_URL + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (res.no_config) {
            pfShowWarn(res.message);
            pfEmptyTable('');
            return;
        }
        if (!res.success) {
            pfHideWarn();
            pfEmptyTable(res.message || 'No data available.');
            return;
        }

        pfHideWarn();
        pfResults = res.results || [];
        pfRenderStats({
            total: res.day_count,
            calls: pfResults.filter(function (r) { return r.signal === 'BUY_CALL'; }).length,
            puts:  pfResults.filter(function (r) { return r.signal === 'BUY_PUT';  }).length,
            traps: pfCountTraps(pfResults),
            waits: pfResults.filter(function (r) { return r.signal === 'NO TRADE'; }).length,
        });
        pfRenderTable(pfResults, 'history');
        pfApplyFilter();

        pfText('pf-card-info', res.day_count + ' trading days · ' + res.start_date + ' → ' + res.end_date);
        pfText('pf-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) {
        pfHideWarn();
        pfEmptyTable('&#9888; ' + err.message);
    });
}

function pfCountTraps(rows) {
    return rows.filter(function (r) {
        return r.signals && r.signals.mmTrap && (r.signals.mmTrap.call_trap || r.signals.mmTrap.put_trap);
    }).length;
}

// ── Render stats ──────────────────────────────────────────────
function pfRenderStats(s) {
    pfText('st-total', s.total || 0);
    pfText('st-call',  s.calls || 0);
    pfText('st-put',   s.puts  || 0);
    pfText('st-trap',  s.traps || 0);
    pfText('st-wait',  s.waits || 0);
    document.getElementById('pf-stats').style.display = '';
}

// ── Render table ──────────────────────────────────────────────
function pfRenderTable(rows, mode) {
    mode = mode || 'scan';
    if (!rows || !rows.length) { pfEmptyTable('No data.'); return; }

    var html = '';

    rows.forEach(function (r, i) {
        var sig     = r.signal || 'NO TRADE';
        var isCall  = sig === 'BUY_CALL';
        var isPut   = sig === 'BUY_PUT';
        var isFired = isCall || isPut;
        var rowCls  = isFired ? (isCall ? 'tr-call' : 'tr-put') : 'tr-wait';
        var zebra   = i % 2 === 0 ? 'tr-even' : 'tr-odd';

        var sigBadge = isCall
            ? '<span class="sig-call">&#8679; BUY CALL</span>'
            : isPut
            ? '<span class="sig-put">&#8681; BUY PUT</span>'
            : (sig === 'NO DATA' || sig === 'ERROR')
            ? '<span class="sig-nd">&#8212; ' + pfEsc(sig) + '</span>'
            : '<span class="sig-wait">WAIT</span>';

        var fd = r.futures_dir || (r.signals && r.signals.futuresDir ? r.signals.futuresDir.direction : null);
        var futHtml = fd === 'BULLISH'
            ? '<span class="fd-bull">&#9650; BULL</span>'
            : fd === 'BEARISH'
            ? '<span class="fd-bear">&#9660; BEAR</span>'
            : '<span class="fd-side">&#9135; SIDE</span>';

        var timeHtml = isFired && r.entry_time
            ? '<span class="c-time">' + pfEsc(r.entry_time) + '</span>'
            : pfDash();

        var strikeHtml = isFired && r.strike
            ? '<span class="c-strike">' + pfFmt(r.strike) + '</span>'
            : pfDash();

        var entryHtml  = isFired && r.entry_price ? '<span class="c-entry">&#8377;'  + r.entry_price  + '</span>' : pfDash();
        var targetHtml = isFired && r.target      ? '<span class="c-target">&#8377;' + r.target       + '</span>' : pfDash();
        var slHtml     = isFired && r.stoploss    ? '<span class="c-sl">&#8377;'     + r.stoploss     + '</span>' : pfDash();

        var score    = isFired ? (r.score || 0) : (r.peak_score || 0);
        var scorePct = Math.round((score / 17) * 100);
        var scoreCol = isCall ? 'var(--c-teal)' : isPut ? 'var(--c-red)' : 'rgba(120,123,134,.3)';
        var scoreHtml = '<div class="score-wrap">'
            + '<span class="score-num" style="color:' + scoreCol + '">' + score + '</span>'
            + '<div class="score-track"><div class="score-fill" style="width:' + scorePct + '%;background:' + scoreCol + ';"></div></div>'
            + '</div>';

        var dotsHtml = pfBuildDots(r.signals || {}, isCall, isPut);
        var pcrHtml  = r.pcr != null
            ? '<span class="c-pcr">' + r.pcr + '</span>'
            : pfDash();

        // First column identifies the row: Symbol in scan mode, Date in history mode
        var idHtml = mode === 'history'
            ? '<span class="c-date">' + pfEsc(r.date) + '</span>'
            : '<span class="c-sym">' + pfEsc(r.symbol) + '</span>';

        html += '<tr class="' + rowCls + ' ' + zebra + '" data-sig="' + pfEsc(sig) + '">'
            + '<td class="c-num">' + (i + 1) + '</td>'
            + '<td style="text-align:left;padding-left:14px;">' + idHtml + '</td>'
            + '<td>' + futHtml + '</td>'
            + '<td class="sep-trade">' + sigBadge + '</td>'
            + '<td>' + timeHtml + '</td>'
            + '<td class="sep-entry">' + strikeHtml + '</td>'
            + '<td>' + entryHtml + '</td>'
            + '<td>' + targetHtml + '</td>'
            + '<td>' + slHtml + '</td>'
            + '<td class="sep-signal">' + scoreHtml + '</td>'
            + '<td>' + dotsHtml + '</td>'
            + '<td>' + pcrHtml + '</td>'
            + '</tr>';
    });

    pfHtml('pf-tbody', html);
}

// ── Signal dots ───────────────────────────────────────────────
function pfBuildDots(s, isCall, isPut) {
    if (!s || !Object.keys(s).length) return pfDash();

    var checks = [
        { key: isCall ? 'cePremEx'   : 'pePremEx',   type: 'std'     },
        { key: isCall ? 'ceOiBuild'  : 'peOiBuild',  type: 'std'     },
        { key: isCall ? 'ceVolSpike' : 'peVolSpike', type: 'std'     },
        { key: 'futuresDir',                          type: 'futures' },
        { key: 'gamma',                               type: 'gamma'   },
        { key: isCall ? 'ceAccel' : 'peAccel',        type: 'std'     },
        { key: 'mmTrap',                              type: 'trap'    },
    ];

    var dotColor = isCall ? 'sd-call' : isPut ? 'sd-put' : 'sd-call';

    var inner = checks.map(function (c) {
        var on = false;
        if (c.type === 'trap')         on = !!(s.mmTrap && (s.mmTrap.call_trap || s.mmTrap.put_trap));
        else if (c.type === 'futures') on = !!(s.futuresDir && (s.futuresDir.bullish || s.futuresDir.bearish));
        else if (c.type === 'gamma')   on = !!(s.gamma && s.gamma.active);
        else                           on = !!(s[c.key] && s[c.key].triggered);

        var cls = on ? (c.type === 'trap' ? 'sd sd-trap' : 'sd ' + dotColor) : 'sd sd-off';
        return '<span class="' + cls + '"></span>';
    }).join('');

    return '<div class="sig-dots">' + inner + '</div>';
}

// ── Helpers ───────────────────────────────────────────────────
function pfFmt(v) {
    if (v == null || v === '') return '—';
    return Number(v).toLocaleString('en-IN');
}
function pfDash() { return '<span style="color:rgba(120,123,134,.25);font-size:9px;">—</span>'; }
function pfEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function pfShowLoading() {
    pfHtml('pf-tbody',
        '<tr><td colspan="12">'
        + '<div class="pf-loading"><div class="pf-spinner"></div>'
        + '<div class="pf-loading-txt">' + (pfMode === 'history' ? 'Loading history…' : 'Scanning all symbols…') + '</div></div>'
        + '</td></tr>');
    document.getElementById('pf-stats').style.display = 'none';
}

function pfEmptyTable(msg) {
    pfHtml('pf-tbody',
        '<tr><td colspan="12">'
        + '<div class="pf-empty">'
        + '<div class="pf-empty-icon"><i class="las la-bolt"></i></div>'
        + '<p>' + (msg || 'Select date and click Scan All') + '</p>'
        + '</div></td></tr>');
    document.getElementById('pf-stats').style.display = 'none';
}

function pfShowWarn(msg) {
    var el = document.getElementById('pf-warn');
    if (el) el.classList.add('show');
    pfText('pf-warn-msg', msg || '');
}

function pfHideWarn() {
    var el = document.getElementById('pf-warn');
    if (el) el.classList.remove('show');
}
</script>
@endpush