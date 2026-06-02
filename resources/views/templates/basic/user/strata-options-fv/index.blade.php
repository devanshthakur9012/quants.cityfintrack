{{-- FILE: resources/views/themes/{active_theme}/user/strata-options-fv/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — STRATA OPTIONS FAIR VALUE  v2.0
   Dark terminal · Matches pivot-analysis design
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
    --c-pivot:    #FFA726;
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.sfv-wrap { font-family: var(--f-sans); color: var(--c-text); background: var(--c-bg); }
.sfv-wrap * { box-sizing: border-box; }
.sfv-wrap a { text-decoration: none; color: inherit; }
.mono { font-family: var(--f-mono); }

@keyframes sfvFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.sfv-anim { animation: sfvFadeUp .5s ease both; }
@keyframes sfvSpin { to { transform: rotate(360deg); } }

/* ══ HERO ═══════════════════════════════════ */
.sfv-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.sfv-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.sfv-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.sfv-hero-left { position: relative; z-index: 1; }
.sfv-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.sfv-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.sfv-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.sfv-hero h1 span { color: var(--c-lime); }
.sfv-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 560px; }
.sfv-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--c-lime); flex-shrink: 0;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}
@media (max-width: 768px) {
    .sfv-hero { flex-direction: column; padding: 24px 18px; }
}

/* ══ FILTER BAR ══════════════════════════════ */
.sfv-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.sfv-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.sfv-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.sfv-filter-sep {
    width: 1px; height: 26px;
    background: var(--c-border2); flex-shrink: 0;
}

/* Symbol select */
.sfv-select {
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
.sfv-select:focus { border-color: rgba(125,255,0,.45); }

/* Date */
.sfv-date-wrap { display: flex; align-items: center; gap: 4px; }
.sfv-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.sfv-date-input:focus { border-color: rgba(125,255,0,.45); }
.sfv-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.sfv-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.sfv-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.sfv-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Date / live badges */
.sfv-live-badge { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.sfv-hist-badge { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Strike pills */
.sfv-sp-wrap { display: flex; gap: 3px; }
.sfv-sp {
    padding: 5px 13px; border-radius: 20px;
    font-family: var(--f-sans); font-size: 10px; font-weight: 700;
    cursor: pointer; border: 1px solid var(--c-border2);
    background: transparent; color: var(--c-muted); transition: all .15s;
    letter-spacing: .05em;
}
.sfv-sp:hover  { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.sfv-sp.active { background: var(--c-lime-dim); border-color: rgba(125,255,0,.3); color: var(--c-lime); }

/* Action buttons */
.sfv-btn {
    background: var(--c-lime); color: #000; border: none; border-radius: 7px;
    padding: 7px 18px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(125,255,0,.2);
    display: inline-flex; align-items: center; gap: 6px;
}
.sfv-btn:hover { background: #8FFF1A; box-shadow: 0 0 22px rgba(125,255,0,.35); transform: translateY(-1px); }
.sfv-reset-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: var(--f-mono);
    transition: all .2s; letter-spacing: .05em;
}
.sfv-reset-btn:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.sfv-auto-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: var(--f-mono);
    transition: all .2s; letter-spacing: .05em;
}
.sfv-auto-btn.on { border-color: rgba(38,166,154,.35); background: rgba(38,166,154,.1); color: var(--c-teal); }
.sfv-auto-btn:hover:not(.on) { border-color: var(--c-border2); color: var(--c-text); }

.sfv-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.sfv-info-text { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.sfv-last-upd  { font-size: 10px; color: rgba(120,123,134,.5); font-family: var(--f-mono); }

@media (max-width: 768px) {
    .sfv-filter-bar { padding: 0 16px; }
    .sfv-filter-inner { gap: 8px; }
    .sfv-filter-right { margin-left: 0; width: 100%; }
}

/* ══ CONTENT ═════════════════════════════════ */
.sfv-content { padding: 24px 32px 64px; }
@media (max-width: 768px) { .sfv-content { padding: 16px 12px 48px; } }

/* Config warning */
.sfv-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.sfv-warn.show { display: flex; }
.sfv-warn i { font-size: 18px; flex-shrink: 0; }
.sfv-warn strong { color: #fff; }

/* ══ STATS ═══════════════════════════════════ */
.sfv-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.sfv-stat-box {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; padding: 14px 18px; min-width: 110px; flex: 1;
}
.sfv-stat-box small {
    display: block; font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .07em; color: var(--c-muted); margin-bottom: 5px;
}
.sfv-stat-box strong {
    display: block; font-family: var(--f-mono); font-size: 1.2rem; font-weight: 700;
}
.s-total { border-left: 2px solid var(--c-lime); }
.s-ce    { border-left: 2px solid var(--c-teal); }
.s-pe    { border-left: 2px solid var(--c-red); }

/* ══ TABLE CARD ══════════════════════════════ */
.sfv-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    margin-bottom: 20px; position: relative;
}
.sfv-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.sfv-card-header {
    padding: 13px 18px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    background: rgba(0,0,0,.2);
}
.sfv-card-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: var(--c-text); display: flex; align-items: center; gap: 8px;
}
.sfv-card-subtitle { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.sfv-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ══ TABLE ═══════════════════════════════════ */
.sfv-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 1020px; }

.sfv-table thead tr.th-group th {
    padding: 8px 10px 4px; text-align: center;
    font-family: var(--f-sans); font-size: 9px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    background: var(--c-panel); border-bottom: none; white-space: nowrap;
}
.sfv-table thead tr.th-cols th {
    padding: 4px 10px 8px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    background: rgba(0,0,0,.25); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}

/* Column group header colours */
.g-meta   { color: var(--c-muted)  !important; }
.g-ce     { color: var(--c-teal)   !important; }
.g-pe     { color: var(--c-red)    !important; }
.g-iv     { color: var(--c-amber)  !important; }

/* Separator borders */
.sep-ce   { border-left: 1px solid rgba(38,166,154,.15)  !important; }
.sep-pe   { border-left: 1px solid rgba(239,83,80,.15)   !important; }
.sep-dash { border-left: 1px dashed rgba(255,255,255,.06) !important; }

/* Body cells */
.sfv-table tbody td {
    padding: 7px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.sfv-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.tr-even { background: var(--c-surface); }
.tr-odd  { background: rgba(0,0,0,.15); }

/* Cell value styles */
.c-num  { font-size: 9px; color: rgba(120,123,134,.35); }
.c-time { font-size: 12px; font-weight: 700; color: var(--c-lime); }
.c-spot { font-size: 12px; font-weight: 700; color: var(--c-text); }
.c-sym  {
    display: inline-block; padding: 2px 9px; border-radius: 5px;
    font-size: 10px; font-weight: 700;
    background: rgba(0,184,212,.1); color: var(--c-blue);
    border: 1px solid rgba(0,184,212,.2);
}
.c-level {
    display: inline-block; padding: 1px 5px; border-radius: 3px;
    font-size: 7px; font-weight: 700;
    background: rgba(255,167,38,.1); color: var(--c-amber);
    border: 1px solid rgba(255,167,38,.25); margin-top: 2px;
}

/* Valuation badges */
.vb { display: inline-block; border-radius: 4px; padding: 3px 8px; font-family: var(--f-sans); font-size: 10px; font-weight: 700; letter-spacing: .04em; white-space: nowrap; }
.vb-over  { background: rgba(239,83,80,.12);  color: #EF9A9A; border: 1px solid rgba(239,83,80,.28); }
.vb-under { background: rgba(38,166,154,.15); color: #4DB6AC; border: 1px solid rgba(38,166,154,.3); }
.vb-fair  { background: var(--c-panel); color: var(--c-muted); border: 1px solid var(--c-border2); }
.vb-na    { color: rgba(120,123,134,.4); font-size: 9px; }

/* Diff colours */
.dp { color: var(--c-red);  font-weight: 700; }
.dn { color: var(--c-teal); font-weight: 700; }
.dz { color: rgba(120,123,134,.4); }

/* ══ LOADING / EMPTY ═════════════════════════ */
.sfv-loading {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; padding: 56px 20px;
}
.sfv-spinner {
    width: 32px; height: 32px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-lime);
    border-radius: 50%;
    animation: sfvSpin .9s linear infinite;
}
.sfv-loading-text { color: var(--c-muted); margin-top: 12px; font-size: 12px; font-family: var(--f-mono); }
.sfv-empty {
    text-align: center; padding: 52px 20px; color: var(--c-muted);
}
.sfv-empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 22px;
}
.sfv-empty p { font-size: 12px; font-family: var(--f-mono); margin-top: 4px; }
</style>

<div class="sfv-wrap">

{{-- ══ HERO ══ --}}
<div class="sfv-hero sfv-anim">
    <div class="sfv-hero-left">
        <div class="sfv-hero-eyebrow">Options Analytics</div>
        <h1>Options <span>Fair Value</span></h1>
        <p>
            Black-Scholes fair price vs market LTP for CE &amp; PE options —
            using cross-leg IV derivation to eliminate circular mispricing bias
            and surface genuine valuation signals.
        </p>
    </div>
    <div class="sfv-hero-icon">
        <i class="las la-balance-scale"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="sfv-filter-bar">
    <div class="sfv-filter-inner">

        {{-- Symbol --}}
        <span class="sfv-filter-label">Symbol</span>
        <select id="sfv-sym" class="sfv-select" onchange="sfvRunAnalysis()">
            <option value="">— All Symbols —</option>
        </select>

        <div class="sfv-filter-sep"></div>

        {{-- Date --}}
        <span class="sfv-filter-label">Date</span>
        <div class="sfv-date-wrap">
            <button class="sfv-date-nav" onclick="sfvShiftDate(-1)">‹</button>
            <input type="date" id="sfv-date" class="sfv-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="sfvRunAnalysis()">
            <button class="sfv-date-nav" onclick="sfvShiftDate(1)">›</button>
            <button class="sfv-date-nav sfv-today-btn" onclick="sfvGoToday()">TODAY</button>
            <span id="sfv-date-badge"></span>
        </div>

        <div class="sfv-filter-sep"></div>

        {{-- Strike --}}
        <span class="sfv-filter-label">Strike</span>
        <div class="sfv-sp-wrap">
            <div class="sfv-sp" data-val="ATM-1">ATM−1</div>
            <div class="sfv-sp active" data-val="ATM">ATM</div>
            <div class="sfv-sp" data-val="ATM+1">ATM+1</div>
        </div>

        {{-- Sort --}}
        <select id="sfv-sort" class="sfv-select">
            <option value="symbol">Sort: A – Z</option>
            <option value="ce_overpriced">CE Most Overpriced</option>
            <option value="ce_underpriced">CE Most Underpriced</option>
            <option value="pe_overpriced">PE Most Overpriced</option>
            <option value="pe_underpriced">PE Most Underpriced</option>
            <option value="mispricing">Largest Mispricing</option>
        </select>

        <button class="sfv-btn" onclick="sfvRunAnalysis()">
            <i class="las la-sync-alt"></i> Analyze
        </button>
        <button class="sfv-reset-btn" onclick="sfvClearSymbol()">All Symbols</button>
        <button class="sfv-auto-btn" id="sfv-auto-btn" onclick="sfvToggleAuto()">▶ Auto 60s</button>

        <div class="sfv-filter-right">
            <span class="sfv-info-text" id="sfv-info"></span>
            <span class="sfv-last-upd"  id="sfv-upd"></span>
        </div>

    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="sfv-content">

    {{-- Config warning --}}
    <div class="sfv-warn" id="sfv-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="sfv-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="sfv-stats" id="sfv-stats" style="display:none;">
        <div class="sfv-stat-box s-total"><small>Total Rows</small>    <strong id="st-total"    style="color:var(--c-lime);">0</strong></div>
        <div class="sfv-stat-box s-ce">  <small>CE Overpriced</small>  <strong id="st-ce-over"  style="color:var(--c-red);">0</strong></div>
        <div class="sfv-stat-box s-ce">  <small>CE Underpriced</small> <strong id="st-ce-under" style="color:var(--c-teal);">0</strong></div>
        <div class="sfv-stat-box s-pe">  <small>PE Overpriced</small>  <strong id="st-pe-over"  style="color:var(--c-red);">0</strong></div>
        <div class="sfv-stat-box s-pe">  <small>PE Underpriced</small> <strong id="st-pe-under" style="color:var(--c-teal);">0</strong></div>
    </div>

    {{-- Table card --}}
    <div class="sfv-card">
        <div class="sfv-card-header">
            <div class="sfv-card-title">&#9670; Strata Fair Value</div>
            <span class="sfv-card-subtitle" id="sfv-card-subtitle">Detecting last available date…</span>
        </div>
        <div class="sfv-table-scroll">
            <table class="sfv-table">
                <thead>
                    <tr class="th-group">
                        <th colspan="5" class="g-meta">Info</th>
                        <th colspan="5" class="g-ce sep-ce">▲ CE — Market vs Fair</th>
                        <th colspan="5" class="g-pe sep-pe">▼ PE — Market vs Fair</th>
                        <th class="g-iv">ATM IV</th>
                        <th class="g-iv">Exp Move</th>
                    </tr>
                    <tr class="th-cols">
                        <th class="g-meta">#</th>
                        <th class="g-meta">Time</th>
                        <th class="g-meta" style="text-align:left;padding-left:14px;">Symbol</th>
                        <th class="g-meta">Spot</th>
                        <th class="g-meta">Strike<br><span style="font-size:7px;opacity:.5;font-weight:400;">Level · DTE</span></th>
                        <th class="g-ce sep-ce">LTP</th>
                        <th class="g-ce">Fair ₹</th>
                        <th class="g-ce">Status</th>
                        <th class="g-ce sep-dash">Diff ₹</th>
                        <th class="g-ce">Diff %</th>
                        <th class="g-pe sep-pe">LTP</th>
                        <th class="g-pe">Fair ₹</th>
                        <th class="g-pe">Status</th>
                        <th class="g-pe sep-dash">Diff ₹</th>
                        <th class="g-pe">Diff %</th>
                        <th class="g-iv">IV %</th>
                        <th class="g-iv">±₹</th>
                    </tr>
                </thead>
                <tbody id="sfv-tbody">
                    <tr><td colspan="17">
                        <div class="sfv-loading">
                            <div class="sfv-spinner"></div>
                            <div class="sfv-loading-text">Detecting last available date…</div>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /.sfv-content --}}
</div>{{-- /.sfv-wrap --}}

@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════
//  STRATA OPTIONS FAIR VALUE — JS (zero jQuery)
// ═══════════════════════════════════════════════════════════════

var SFV_ANALYZE_URL = '{{ route("strata-options-fv.analyze") }}';
var SFV_SYM_URL     = '{{ route("strata-options-fv.symbols") }}';
var SFV_LASTDATE    = '{{ route("strata-options-fv.last.date") }}';
var SFV_TODAY       = '{{ now()->toDateString() }}';

var sfvCurStrike = 'ATM';
var sfvSymCache  = null;
var sfvAutoTimer = null;

function sfvHtml(id, h) { var e = document.getElementById(id); if (e) e.innerHTML  = h; }
function sfvText(id, t) { var e = document.getElementById(id); if (e) e.textContent = t; }

// ═══════════════════════════════════════════════════════════════
//  BOOT — detect last available date then auto-analyze
// ═══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    // Strike pill clicks
    document.querySelectorAll('.sfv-sp').forEach(function (pill) {
        pill.addEventListener('click', function () {
            document.querySelectorAll('.sfv-sp').forEach(function (p) { p.classList.remove('active'); });
            pill.classList.add('active');
            sfvCurStrike = pill.getAttribute('data-val');
            sfvRunAnalysis();
        });
    });

    // Sort change
    document.getElementById('sfv-sort').addEventListener('change', sfvRunAnalysis);

    // Detect last date → load symbols → analyze
    sfvResolveLastDateAndLoad();
});

function sfvResolveLastDateAndLoad() {
    fetch(SFV_LASTDATE, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.last_date) {
                document.getElementById('sfv-date').value = res.last_date;
            }
            sfvUpdateDateBadge();
            sfvLoadSymbols(function () { sfvRunAnalysis(); });
        })
        .catch(function () {
            sfvUpdateDateBadge();
            sfvLoadSymbols(function () { sfvRunAnalysis(); });
        });
}

// ── Date helpers ──────────────────────────────────────────────

function sfvShiftDate(d) {
    var picker = document.getElementById('sfv-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > SFV_TODAY) return;
    picker.value = s;
    sfvUpdateDateBadge();
    sfvRunAnalysis();
}

function sfvGoToday() {
    document.getElementById('sfv-date').value = SFV_TODAY;
    sfvUpdateDateBadge();
    sfvRunAnalysis();
}

function sfvUpdateDateBadge() {
    var d  = document.getElementById('sfv-date').value;
    var el = document.getElementById('sfv-date-badge');
    if (!el) return;
    el.innerHTML = d === SFV_TODAY
        ? '<span class="sfv-live-badge">● Live</span>'
        : '<span class="sfv-hist-badge">📅 Historical</span>';
}

// ── Symbol helpers ────────────────────────────────────────────

function sfvLoadSymbols(callback) {
    if (sfvSymCache !== null) {
        sfvRebuildSym(sfvSymCache);
        if (callback) callback();
        return;
    }

    fetch(SFV_SYM_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.no_config) {
                sfvShowWarn(res.message);
                sfvRebuildSym([]);
            } else {
                sfvHideWarn();
                sfvSymCache = res.symbols || [];
                sfvRebuildSym(sfvSymCache);
            }
            if (callback) callback();
        })
        .catch(function () {
            sfvRebuildSym([]);
            if (callback) callback();
        });
}

function sfvRebuildSym(syms) {
    var sel  = document.getElementById('sfv-sym');
    var prev = sel.value;
    sel.innerHTML = '<option value="">— All Symbols —</option>';
    syms.forEach(function (s) {
        var opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

function sfvClearSymbol() {
    document.getElementById('sfv-sym').value = '';
    sfvRunAnalysis();
}

// ── Auto refresh ──────────────────────────────────────────────

function sfvToggleAuto() {
    var btn = document.getElementById('sfv-auto-btn');
    if (sfvAutoTimer) {
        clearInterval(sfvAutoTimer);
        sfvAutoTimer = null;
        btn.textContent = '▶ Auto 60s';
        btn.classList.remove('on');
    } else {
        sfvAutoTimer = setInterval(sfvRunAnalysis, 60000);
        btn.textContent = '■ Stop';
        btn.classList.add('on');
        sfvRunAnalysis();
    }
}

// ── Main analysis call ────────────────────────────────────────

function sfvRunAnalysis() {
    var sym  = document.getElementById('sfv-sym').value;
    var sort = document.getElementById('sfv-sort').value;
    var date = document.getElementById('sfv-date').value;

    sfvUpdateDateBadge();
    document.getElementById('sfv-sort').style.display = sym ? 'none' : '';

    sfvShowLoading();

    var params = new URLSearchParams({
        strike_filter : sfvCurStrike,
        sort_by       : sort,
        date          : date,
    });
    if (sym) params.append('symbol', sym);

    fetch(SFV_ANALYZE_URL + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (res.no_config) { sfvShowWarn(res.message); sfvEmptyTable(); return; }
        if (!res.success)  { sfvEmptyTable(res.message); return; }

        sfvHideWarn();
        sfvRenderStats(res.summary, res.total_rows);
        sfvRenderTable(res.rows);

        sfvHtml('sfv-info',
            'Date: <span style="color:var(--c-amber)">' + res.trade_date + '</span>'
            + ' &nbsp;·&nbsp; Time: <span style="color:var(--c-teal)">' + (res.latest_time || '—') + '</span>'
        );
        sfvText('sfv-card-subtitle', res.total_rows + ' row(s) · ' + res.trade_date);
        sfvText('sfv-upd', 'Updated ' + new Date().toLocaleTimeString());
    })
    .catch(function (err) {
        sfvEmptyTable('⚠ ' + err.message);
    });
}

// ── Stats ─────────────────────────────────────────────────────

function sfvRenderStats(s, total) {
    sfvText('st-total',    total);
    sfvText('st-ce-over',  s.ceOver);
    sfvText('st-ce-under', s.ceUnder);
    sfvText('st-pe-over',  s.peOver);
    sfvText('st-pe-under', s.peUnder);
    var el = document.getElementById('sfv-stats');
    if (el) el.style.display = '';
}

// ── Table renderer ────────────────────────────────────────────

function sfvRenderTable(rows) {
    if (!rows || !rows.length) { sfvEmptyTable('No data for selected filters.'); return; }

    var html = '';
    rows.forEach(function (r, idx) {
        var zebra = idx % 2 === 0 ? 'tr-even' : 'tr-odd';

        var ceCols = r.ce_ltp != null
            ? sfvTd('sep-ce', '₹' + r.ce_ltp)
            + sfvTd('', '<strong style="color:var(--c-teal);">₹' + sfvNv(r.ce_fair) + '</strong>')
            + sfvTd('', sfvVbadge(r.ce_status))
            + sfvTd('sep-dash ' + sfvDc(r.ce_diff),  sfvDiffFmt(r.ce_diff, '₹'))
            + sfvTd(sfvDc(r.ce_diff_pct), sfvDiffPct(r.ce_diff_pct))
            : '<td colspan="5" class="sep-ce" style="color:rgba(120,123,134,.3);font-size:9px;">— no CE —</td>';

        var peCols = r.pe_ltp != null
            ? sfvTd('sep-pe', '₹' + r.pe_ltp)
            + sfvTd('', '<strong style="color:var(--c-red);">₹' + sfvNv(r.pe_fair) + '</strong>')
            + sfvTd('', sfvVbadge(r.pe_status))
            + sfvTd('sep-dash ' + sfvDc(r.pe_diff),  sfvDiffFmt(r.pe_diff, '₹'))
            + sfvTd(sfvDc(r.pe_diff_pct), sfvDiffPct(r.pe_diff_pct))
            : '<td colspan="5" class="sep-pe" style="color:rgba(120,123,134,.3);font-size:9px;">— no PE —</td>';

        var strikeMeta =
            '<span style="color:var(--c-amber);font-weight:700;">₹' + sfvFmt(r.strike) + '</span>'
            + '<br><span class="c-level">' + (r.strike_level || 'ATM') + '</span>'
            + '&thinsp;<span style="font-size:8px;color:rgba(120,123,134,.4);">' + r.days_to_expiry + 'd</span>';

        html += '<tr class="' + zebra + '">'
            + sfvTd('c-num', idx + 1)
            + sfvTd('c-time', r.time || '—')
            + '<td style="text-align:left;padding-left:14px;"><span class="c-sym">' + sfvEsc(r.symbol) + '</span></td>'
            + sfvTd('c-spot', '₹' + sfvFmt(r.spot))
            + '<td>' + strikeMeta + '</td>'
            + ceCols
            + peCols
            + sfvTd('', r.atm_iv != null
                ? '<span style="color:var(--c-amber);font-weight:700;">' + r.atm_iv + '%</span>'
                : sfvDash())
            + sfvTd('', r.expected_move != null
                ? '<span style="color:var(--c-lime);font-weight:700;">±₹' + r.expected_move + '</span>'
                : sfvDash())
            + '</tr>';
    });

    sfvHtml('sfv-tbody', html);
}

// ── Cell / badge helpers ──────────────────────────────────────

function sfvTd(cls, inner) {
    return '<td' + (cls ? ' class="' + cls + '"' : '') + '>' + inner + '</td>';
}

function sfvVbadge(st) {
    var map = { OVERPRICED:'vb-over', UNDERPRICED:'vb-under', FAIR:'vb-fair' };
    return '<span class="vb ' + (map[st] || 'vb-na') + '">'
        + (st === 'N/A' ? '—' : (st || '—')) + '</span>';
}

function sfvDc(v) {
    if (v == null) return 'dz';
    return Number(v) > 0 ? 'dp' : (Number(v) < 0 ? 'dn' : 'dz');
}

function sfvDiffFmt(v, pfx) {
    if (v == null) return sfvDash();
    var n = Number(v);
    return (n >= 0 ? '+' : '') + pfx + Math.abs(n).toFixed(2);
}

function sfvDiffPct(v) {
    if (v == null) return sfvDash();
    var n = Number(v);
    return (n >= 0 ? '+' : '') + n + '%';
}

function sfvNv(v)  { return v != null ? v : '—'; }
function sfvDash() { return '<span style="color:rgba(120,123,134,.3);font-size:9px;">—</span>'; }
function sfvFmt(v) {
    return v != null
        ? Number(v).toLocaleString('en-IN', { maximumFractionDigits:2 })
        : '—';
}
function sfvEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Loading / empty / warn ────────────────────────────────────

function sfvShowLoading() {
    sfvHtml('sfv-tbody',
        '<tr><td colspan="17">'
        + '<div class="sfv-loading"><div class="sfv-spinner"></div>'
        + '<div class="sfv-loading-text">Calculating fair values…</div></div>'
        + '</td></tr>'
    );
    var el = document.getElementById('sfv-stats');
    if (el) el.style.display = 'none';
}

function sfvEmptyTable(msg) {
    sfvHtml('sfv-tbody',
        '<tr><td colspan="17">'
        + '<div class="sfv-empty">'
        + '<div class="sfv-empty-icon"><i class="las la-chart-line"></i></div>'
        + '<p>' + (msg || 'No data found for this date.') + '</p>'
        + '</div></td></tr>'
    );
    var el = document.getElementById('sfv-stats');
    if (el) el.style.display = 'none';
}

function sfvShowWarn(msg) {
    var el = document.getElementById('sfv-warn');
    if (el) el.classList.add('show');
    sfvText('sfv-warn-msg', msg || '');
}

function sfvHideWarn() {
    var el = document.getElementById('sfv-warn');
    if (el) el.classList.remove('show');
}
</script>
@endpush