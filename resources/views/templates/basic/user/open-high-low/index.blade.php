{{-- FILE: resources/views/themes/{active_theme}/user/open-high-low/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — OPEN=HIGH / OPEN=LOW  v2.0
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

.ohl-wrap {
    font-family: var(--f-sans);
    color: var(--c-text);
    background: var(--c-bg);
}
.ohl-wrap * { box-sizing: border-box; }
.ohl-wrap a { text-decoration: none; color: inherit; }
.mono { font-family: var(--f-mono); }

@keyframes ohlFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.ohl-anim    { animation: ohlFadeUp .5s ease both; }
.ohl-anim.d1 { animation-delay: .08s; }
.ohl-anim.d2 { animation-delay: .16s; }
@keyframes ohlSpin { to { transform: rotate(360deg); } }

/* ══ HERO ═════════════════════════════════════ */
.ohl-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 36px 32px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 24px;
}
.ohl-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.ohl-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 35% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.ohl-hero-left { position: relative; z-index: 1; }
.ohl-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 10px;
}
.ohl-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.ohl-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em; margin-bottom: 10px;
}
.ohl-hero h1 span { color: var(--c-lime); }
.ohl-hero p { font-size: 13px; color: var(--c-muted); line-height: 1.7; max-width: 580px; margin-bottom: 12px; }

/* Logic pills */
.ohl-logic-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.ohl-pill {
    display: inline-block; padding: 3px 11px; border-radius: 4px;
    font-family: var(--f-mono); font-size: 10px; font-weight: 700; letter-spacing: .04em;
}
.ohl-pill-oh { background: rgba(239,83,80,.08);  color: #EF9A9A; border: 1px solid rgba(239,83,80,.2);  }
.ohl-pill-ol { background: rgba(38,166,154,.08); color: #80CBC4; border: 1px solid rgba(38,166,154,.2); }

.ohl-hero-icon {
    position: relative; z-index: 1;
    width: 72px; height: 72px; border-radius: 12px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--c-lime); flex-shrink: 0;
    box-shadow: 0 0 24px rgba(125,255,0,.1);
}
@media (max-width: 768px) {
    .ohl-hero { flex-direction: column; padding: 24px 18px; }
    .ohl-logic-pills { flex-wrap: wrap; }
    .ohl-hero-icon { display: none; }
}

/* ══ FILTER BAR ═══════════════════════════════ */
.ohl-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 32px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.ohl-filter-inner {
    display: flex; align-items: center;
    gap: 12px; padding: 11px 0; flex-wrap: wrap;
}
.ohl-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono); flex-shrink: 0;
}
.ohl-sep { width: 1px; height: 26px; background: var(--c-border2); flex-shrink: 0; }

/* Instrument tabs — matches pivot + momentum */
.ohl-inst-tabs { display: flex; gap: 4px; }
.ohl-inst-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 6px;
    border: 1px solid var(--c-border2);
    font-size: 11px; font-weight: 600; letter-spacing: .05em;
    text-transform: uppercase; color: var(--c-muted); cursor: pointer;
    background: transparent; font-family: var(--f-sans);
    transition: all .2s; white-space: nowrap;
}
.ohl-inst-tab:hover { color: var(--c-text); border-color: var(--c-border2); }
.ohl-inst-tab.on-stock  { background: rgba(38,166,154,.1);  border-color: rgba(38,166,154,.3);  color: var(--c-teal);   }
.ohl-inst-tab.on-fut    { background: var(--c-lime-dim);     border-color: rgba(125,255,0,.3);   color: var(--c-lime);   }
.ohl-inst-tab.on-option { background: rgba(171,71,188,.1);  border-color: rgba(171,71,188,.3);  color: var(--c-purple); }

/* Symbol select */
.ohl-sym-select {
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
.ohl-sym-select:focus { border-color: rgba(125,255,0,.45); }

/* Date controls */
.ohl-date-wrap { display: flex; align-items: center; gap: 4px; }
.ohl-date-input {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 11px;
    font-weight: 600; color: var(--c-text);
    outline: none; cursor: pointer; transition: border-color .2s;
}
.ohl-date-input:focus { border-color: rgba(125,255,0,.45); }
.ohl-date-input::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.4); cursor: pointer; }
.ohl-date-nav {
    width: 28px; height: 30px;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 6px; color: var(--c-muted);
    cursor: pointer; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; font-family: var(--f-sans);
}
.ohl-date-nav:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.ohl-today-btn { width: auto; padding: 0 10px; font-size: 9px; font-family: var(--f-mono); font-weight: 700; letter-spacing: .1em; }

/* Date badges */
.ohl-live-badge { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }
.ohl-hist-badge { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); border-radius: 100px; font-size: 10px; font-weight: 700; padding: 2px 9px; font-family: var(--f-mono); }

/* Tolerance input */
.ohl-tol-input {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 6px 10px;
    font-family: var(--f-mono); font-size: 12px; font-weight: 700;
    color: var(--c-amber); outline: none; width: 64px;
    transition: border-color .2s;
}
.ohl-tol-input:focus { border-color: rgba(125,255,0,.45); }
.ohl-tol-unit { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }

/* Buttons */
.ohl-analyze-btn {
    background: var(--c-teal); color: #000; border: none; border-radius: 7px;
    padding: 7px 20px; font-family: var(--f-display); font-size: 12px;
    font-weight: 700; letter-spacing: .06em; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 14px rgba(38,166,154,.2);
    display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
}
.ohl-analyze-btn:hover { background: #4DB6AC; box-shadow: 0 0 22px rgba(38,166,154,.35); transform: translateY(-1px); }
.ohl-reset-btn {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); border-radius: 7px;
    padding: 7px 14px; font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: var(--f-sans);
    transition: all .2s;
}
.ohl-reset-btn:hover { color: var(--c-text); border-color: var(--c-border2); }

.ohl-filter-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.ohl-info-text { font-size: 10px; color: var(--c-muted); font-family: var(--f-mono); }
.ohl-upd-text  { font-size: 10px; color: rgba(120,123,134,.45); font-family: var(--f-mono); }

@media (max-width: 768px) {
    .ohl-filter-bar { padding: 0 16px; }
    .ohl-filter-inner { gap: 8px; }
    .ohl-filter-right { margin-left: 0; width: 100%; }
}

/* ══ CONTENT ══════════════════════════════════ */
.ohl-content { padding: 24px 32px 64px; }
@media (max-width: 768px) { .ohl-content { padding: 16px 12px 48px; } }

/* Config warning */
.ohl-warn {
    background: rgba(255,167,38,.08);
    border: 1px solid rgba(255,167,38,.25);
    border-radius: 9px; padding: 14px 18px; margin-bottom: 18px;
    display: none; align-items: center; gap: 12px;
    font-size: 13px; color: var(--c-amber);
}
.ohl-warn.show { display: flex; }
.ohl-warn i { font-size: 18px; flex-shrink: 0; }
.ohl-warn strong { color: #fff; }

/* ══ TWO TABLE LAYOUT ═════════════════════════ */
.ohl-tables-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
}
@media (max-width: 900px) { .ohl-tables-row { grid-template-columns: 1fr; } }

/* ══ TABLE CARD ═══════════════════════════════ */
.ohl-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    position: relative;
    transition: border-color .25s;
}
.ohl-card.oh-card { border-color: rgba(239,83,80,.2); }
.ohl-card.ol-card { border-color: rgba(38,166,154,.2); }
/* Top accent lines */
.ohl-card.oh-card::before { content: ''; position: absolute; top: 0; left: 14px; right: 14px; height: 1px; background: linear-gradient(90deg, transparent, rgba(239,83,80,.55), transparent); }
.ohl-card.ol-card::before { content: ''; position: absolute; top: 0; left: 14px; right: 14px; height: 1px; background: linear-gradient(90deg, transparent, rgba(38,166,154,.55), transparent); }
.ohl-card.oh-card:hover { border-color: rgba(239,83,80,.35); }
.ohl-card.ol-card:hover { border-color: rgba(38,166,154,.35); }

.ohl-card-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--c-border);
    background: rgba(0,0,0,.2);
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
}
.oh-card .ohl-card-header { color: #EF9A9A; }
.ol-card .ohl-card-header { color: #80CBC4; }

/* Action badges in header */
.act-badge {
    display: inline-block; border-radius: 5px; padding: 3px 10px;
    font-family: var(--f-sans); font-size: 10px; font-weight: 800; letter-spacing: .05em;
}
.act-buy-pe  { background: rgba(239,83,80,.12); color: #EF9A9A; border: 1px solid rgba(239,83,80,.3);  }
.act-buy-ce  { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.3); }
.act-sell-ce { background: rgba(239,83,80,.07);  color: #FFCDD2; border: 1px solid rgba(239,83,80,.2);  }
.act-sell-pe { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); }

.ohl-count-pill {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-text); padding: 2px 9px; border-radius: 100px;
    font-size: 10px; font-weight: 700; font-family: var(--f-mono);
}
.ohl-tol-pill {
    background: rgba(255,167,38,.1); color: var(--c-amber);
    border: 1px solid rgba(255,167,38,.25);
    padding: 2px 8px; border-radius: 4px;
    font-size: 10px; font-weight: 700; font-family: var(--f-mono);
}

/* ══ TABLE ════════════════════════════════════ */
.ohl-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.ohl-table { width: 100%; border-collapse: collapse; font-family: var(--f-mono); min-width: 520px; }

.ohl-table thead th {
    padding: 9px 10px; text-align: center;
    font-family: var(--f-mono); font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .08em;
    background: var(--c-panel); color: var(--c-muted);
    border-bottom: 1px solid var(--c-border); white-space: nowrap;
}
.ohl-table tbody td {
    padding: 8px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid var(--c-border);
    vertical-align: middle; white-space: nowrap;
    color: var(--c-muted); transition: background .15s;
}
.ohl-table tbody tr:hover td { background: rgba(255,255,255,.02) !important; }
.ohl-table tbody tr:last-child td { border-bottom: none; }
.tr-even { background: var(--c-surface); }
.tr-odd  { background: rgba(0,0,0,.15); }

/* Cell styles */
.c-num   { font-size: 9px; color: rgba(120,123,134,.35); }
.c-date  { font-size: 11px; color: var(--c-lime); font-weight: 700; }
.c-sym   { font-size: 12px; font-weight: 800; color: var(--c-blue); }
.c-sym small { display: block; font-size: 8px; color: var(--c-muted); font-weight: 400; margin-top: 1px; }
.c-opt-ce { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); border-radius: 4px; padding: 1px 7px; font-size: 9px; font-weight: 800; }
.c-opt-pe { background: rgba(239,83,80,.1);  color: #EF9A9A; border: 1px solid rgba(239,83,80,.22);  border-radius: 4px; padding: 1px 7px; font-size: 9px; font-weight: 800; }
.c-open  { color: var(--c-muted); font-weight: 600; }
.c-h915  { color: #EF9A9A; font-weight: 700; }
.c-l915  { color: #80CBC4; font-weight: 700; }
.c-dh    { color: var(--c-blue); font-weight: 700; }
.c-dl    { color: var(--c-amber); font-weight: 700; }
.c-ltp   { color: #fff; font-weight: 700; }
.c-up    { color: #80CBC4; font-weight: 700; }
.c-down  { color: #EF9A9A; font-weight: 700; }
.c-neu   { color: var(--c-muted); }

/* ══ LOADING / EMPTY ══════════════════════════ */
.ohl-spinner-row {
    display: flex; align-items: center; justify-content: center;
    gap: 12px; padding: 48px; color: var(--c-muted);
    font-size: 12px; font-family: var(--f-mono);
}
.ohl-spinner {
    width: 28px; height: 28px;
    border: 2px solid var(--c-border2);
    border-top: 2px solid var(--c-teal);
    border-radius: 50%;
    animation: ohlSpin .9s linear infinite; flex-shrink: 0;
}
.ohl-empty { text-align: center; padding: 48px 20px; color: var(--c-muted); }
.ohl-empty-icon {
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px; font-size: 18px;
}
.ohl-empty p { font-size: 12px; font-family: var(--f-mono); margin-top: 4px; }
</style>

<div class="ohl-wrap">

{{-- ══ HERO ══ --}}
<div class="ohl-hero ohl-anim">
    <div class="ohl-hero-left">
        <div class="ohl-hero-eyebrow">Options Analytics</div>
        <h1>Open=High / <span>Open=Low</span></h1>
        <p>
            Identify stocks where the opening candle has Open equal (or near equal)
            to its High or Low — a classic reversal signal in intraday trading.
        </p>
        <div class="ohl-logic-pills">
            <span class="ohl-pill ohl-pill-oh">Open = High → BUY PE</span>
            <span class="ohl-pill ohl-pill-ol">Open = Low → BUY CE</span>
        </div>
    </div>
    <div class="ohl-hero-icon">
        <i class="las la-exchange-alt"></i>
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="ohl-filter-bar">
    <div class="ohl-filter-inner">

        <span class="ohl-filter-label">Type</span>
        <div class="ohl-inst-tabs">
            <button class="ohl-inst-tab on-stock" data-inst="stock"
                    onclick="ohlSetInst('stock',this)">
                <i class="las la-chart-line"></i> Stock EQ
            </button>
            <button class="ohl-inst-tab" data-inst="fut"
                    onclick="ohlSetInst('fut',this)">
                <i class="las la-fire"></i> Futures
            </button>
            <button class="ohl-inst-tab" data-inst="option"
                    onclick="ohlSetInst('option',this)">
                <i class="las la-layer-group"></i> Options
            </button>
        </div>

        <div class="ohl-sep"></div>

        <span class="ohl-filter-label">Symbol</span>
        <select id="ohl-sym" class="ohl-sym-select" onchange="ohlAnalyze()">
            <option value="ALL">— All —</option>
        </select>

        <div class="ohl-sep"></div>

        <span class="ohl-filter-label">Date</span>
        <div class="ohl-date-wrap">
            <button class="ohl-date-nav" onclick="ohlShiftDate(-1)">‹</button>
            <input type="date" id="ohl-date" class="ohl-date-input"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="ohlAnalyze()">
            <button class="ohl-date-nav" onclick="ohlShiftDate(1)">›</button>
            <button class="ohl-date-nav ohl-today-btn" onclick="ohlGoToday()">TODAY</button>
            <span id="ohl-date-badge"></span>
        </div>

        <div class="ohl-sep"></div>

        <span class="ohl-filter-label">Tol.</span>
        <input type="number" id="ohl-tol" class="ohl-tol-input"
               value="1" min="0" max="100" step="0.5"
               title="Tolerance in points">
        <span class="ohl-tol-unit">pts</span>

        <button class="ohl-analyze-btn" onclick="ohlAnalyze()">
            <i class="las la-search"></i> Analyze
        </button>
        <button class="ohl-reset-btn" onclick="ohlReset()">↺ Reset</button>

        <div class="ohl-filter-right">
            <span class="ohl-info-text" id="ohl-info"></span>
            <span class="ohl-upd-text"  id="ohl-upd"></span>
        </div>
    </div>
</div>

{{-- ══ CONTENT ══ --}}
<div class="ohl-content">

    {{-- Config warning --}}
    <div class="ohl-warn" id="ohl-warn">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong>No Analysis Config Found</strong>
            <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="ohl-warn-msg">
                Go to Admin → Analysis Config and create a config with symbols.
            </div>
        </div>
    </div>

    {{-- Two-table layout --}}
    <div class="ohl-tables-row ohl-anim d1">

        {{-- Open=High → BUY PE --}}
        <div class="ohl-card oh-card">
            <div class="ohl-card-header">
                🔴 Open = High
                <span style="font-size:12px;color:var(--c-muted);">→</span>
                <span class="act-badge act-buy-pe">BUY PE</span>
                <span class="ohl-count-pill" id="oh-count">0</span>
                <span class="ohl-tol-pill"   id="oh-tol" style="display:none;"></span>
            </div>
            <div class="ohl-table-scroll">
                <table class="ohl-table">
                    <thead>
                        <tr id="oh-thead-row">
                            <th>#</th><th>Date</th><th>Symbol</th>
                            <th>Open</th><th>High (09:15)</th><th>Day High</th>
                            <th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="oh-tbody">
                        <tr><td colspan="10">
                            <div class="ohl-empty">
                                <div class="ohl-empty-icon"><i class="las la-chart-area"></i></div>
                                <p>Detecting last available date…</p>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Open=Low → BUY CE --}}
        <div class="ohl-card ol-card">
            <div class="ohl-card-header">
                🟢 Open = Low
                <span style="font-size:12px;color:var(--c-muted);">→</span>
                <span class="act-badge act-buy-ce">BUY CE</span>
                <span class="ohl-count-pill" id="ol-count">0</span>
                <span class="ohl-tol-pill"   id="ol-tol" style="display:none;"></span>
            </div>
            <div class="ohl-table-scroll">
                <table class="ohl-table">
                    <thead>
                        <tr id="ol-thead-row">
                            <th>#</th><th>Date</th><th>Symbol</th>
                            <th>Open</th><th>Low (09:15)</th><th>Day Low</th>
                            <th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="ol-tbody">
                        <tr><td colspan="10">
                            <div class="ohl-empty">
                                <div class="ohl-empty-icon"><i class="las la-chart-area"></i></div>
                                <p>Detecting last available date…</p>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>{{-- /.ohl-tables-row --}}
</div>{{-- /.ohl-content --}}
</div>{{-- /.ohl-wrap --}}

@endsection

@push('script')
<script>
/* ════════════════════════════════════════════════════════════
   Open=High / Open=Low — JS (all logic identical to original)
════════════════════════════════════════════════════════════ */

var OHL_ANALYZE  = '{{ route("open-high-low.analyze") }}';
var OHL_SYMBOLS  = '{{ route("open-high-low.symbols") }}';
var OHL_LASTDATE = '{{ route("open-high-low.last.date") }}';
var OHL_TODAY    = '{{ now()->toDateString() }}';

var ohlInst     = 'stock';
var ohlSymCache = {};

function el(id)      { return document.getElementById(id); }
function html(id, h) { var e = el(id); if (e) e.innerHTML = h; }
function txt(id, t)  { var e = el(id); if (e) e.textContent = t; }

/* ── BOOT ── */
document.addEventListener('DOMContentLoaded', function () {
    ohlResolveLastDateAndLoad();
});

function ohlResolveLastDateAndLoad() {
    fetch(OHL_LASTDATE + '?instrument=' + ohlInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) el('ohl-date').value = res.last_date;
        ohlLoadSymbols(function () { ohlAnalyze(); });
    })
    .catch(function () {
        ohlLoadSymbols(function () { ohlAnalyze(); });
    });
}

/* ── INSTRUMENT ── */
function ohlSetInst(inst, btn) {
    ohlInst = inst;
    document.querySelectorAll('.ohl-inst-tab').forEach(function (b) {
        b.className = 'ohl-inst-tab';
    });
    btn.classList.add('on-' + inst);

    var isOpt = inst === 'option';
    var optTh = isOpt ? '<th>Type</th>' : '';
    el('oh-thead-row').innerHTML =
        '<th>#</th><th>Date</th><th>Symbol</th>' + optTh
        + '<th>Open</th><th>High (09:15)</th><th>Day High</th>'
        + '<th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>';
    el('ol-thead-row').innerHTML =
        '<th>#</th><th>Date</th><th>Symbol</th>' + optTh
        + '<th>Open</th><th>Low (09:15)</th><th>Day Low</th>'
        + '<th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>';

    fetch(OHL_LASTDATE + '?instrument=' + ohlInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.last_date) el('ohl-date').value = res.last_date;
        ohlLoadSymbols(function () { ohlAnalyze(); });
    })
    .catch(function () {
        ohlLoadSymbols(function () { ohlAnalyze(); });
    });
}

/* ── DATE ── */
function ohlGetDate() { return el('ohl-date').value; }

function ohlShiftDate(d) {
    var picker = el('ohl-date');
    var dt     = new Date(picker.value);
    dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > OHL_TODAY) return;
    picker.value = s;
    ohlAnalyze();
}

function ohlGoToday() {
    el('ohl-date').value = OHL_TODAY;
    ohlAnalyze();
}

function ohlUpdateDateBadge(isToday) {
    el('ohl-date-badge').innerHTML = isToday
        ? '<span class="ohl-live-badge">● Live</span>'
        : '<span class="ohl-hist-badge">📅 Historical</span>';
}

/* ── SYMBOLS ── */
function ohlLoadSymbols(callback) {
    var key = ohlInst;
    if (ohlSymCache[key] && ohlSymCache[key].length) {
        ohlRebuildSym(ohlSymCache[key]);
        if (callback) callback();
        return;
    }
    fetch(OHL_SYMBOLS + '?instrument=' + ohlInst, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.no_config) {
            ohlShowWarn('No active analysis config found.');
            ohlRebuildSym([]);
        } else {
            ohlHideWarn();
            ohlSymCache[key] = res.symbols || [];
            ohlRebuildSym(ohlSymCache[key]);
        }
        if (callback) callback();
    })
    .catch(function () { if (callback) callback(); });
}

function ohlRebuildSym(symbols) {
    var sel  = el('ohl-sym');
    var prev = sel.value;
    var opts = '<option value="ALL">— All Symbols —</option>';
    symbols.forEach(function (s) {
        opts += '<option value="' + s + '"' + (s === prev ? ' selected' : '') + '>' + s + '</option>';
    });
    sel.innerHTML = opts;
    if (prev && prev !== 'ALL') {
        sel.value = prev;
        if (sel.value !== prev) sel.value = 'ALL';
    }
}

/* ── ANALYZE ── */
function ohlAnalyze() {
    var date = ohlGetDate();
    var sym  = el('ohl-sym').value;
    var tol  = parseFloat(el('ohl-tol').value) || 1;
    if (!date) return;

    ohlHideWarn();
    ohlShowLoading();

    var params = new URLSearchParams({ instrument: ohlInst, date: date, tolerance: tol });
    if (sym && sym !== 'ALL') params.append('symbol', sym);

    fetch(OHL_ANALYZE + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
    })
    .then(function (res) {
        if (typeof res.is_today !== 'undefined') ohlUpdateDateBadge(res.is_today);

        if (res.available_symbols && res.available_symbols.length) {
            ohlSymCache[ohlInst] = res.available_symbols;
            ohlRebuildSym(res.available_symbols);
            if (sym && sym !== 'ALL') el('ohl-sym').value = sym;
        }

        if (res.no_config) { ohlShowWarn(res.message); ohlEmptyBoth('—', 10); return; }

        if (!res.success || !res.data || !res.data.length) {
            ohlEmptyBoth(res.message || 'No signals found for this date.', 10);
            txt('ohl-info', '');
            ohlUpdateCounts(0, 0, tol);
            return;
        }

        var ohRows = res.data.filter(function (r) { return r.signal === 'OPEN=HIGH'; });
        var olRows = res.data.filter(function (r) { return r.signal === 'OPEN=LOW';  });

        ohlUpdateCounts(ohRows.length, olRows.length, res.tolerance);

        el('ohl-info').innerHTML =
            '<span style="color:#EF9A9A;">O=H: ' + ohRows.length + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:#80CBC4;">O=L: ' + olRows.length + '</span>'
            + ' &nbsp;·&nbsp; '
            + '<span style="color:var(--c-amber);">Tol: ±' + parseFloat(res.tolerance).toFixed(1) + ' pts</span>'
            + ' · ' + res.instrument;
        txt('ohl-upd', 'Updated ' + new Date().toLocaleTimeString());

        ohlRenderOH(ohRows);
        ohlRenderOL(olRows);
    })
    .catch(function (err) { ohlEmptyBoth('⚠ ' + err.message, 10); });
}

/* ── RENDERERS ── */
function ohlRenderOH(rows) {
    var isOpt = ohlInst === 'option';
    var cols  = isOpt ? 11 : 10;
    if (!rows.length) { html('oh-tbody', ohlEmptyHtml(cols, 'No Open=High signals found.')); return; }
    var h = '';
    rows.forEach(function (r, i) {
        h += '<tr class="' + (i % 2 === 0 ? 'tr-even' : 'tr-odd') + '">'
            + '<td class="c-num">'  + (i + 1) + '</td>'
            + '<td class="c-date">' + r.date   + '</td>'
            + '<td class="c-sym">'  + esc(r.symbol) + (r.expiry ? '<small>' + r.expiry + '</small>' : '') + '</td>'
            + (isOpt ? '<td>' + ohlOptBadge(r.opt_type) + '</td>' : '')
            + '<td class="c-open">₹' + f(r.open)       + '</td>'
            + '<td class="c-h915">₹' + f(r.high_open)  + '</td>'
            + '<td class="c-dh">₹'   + f(r.day_high)   + '</td>'
            + '<td class="c-ltp">₹'  + f(r.ltp)        + '</td>'
            + '<td>'                  + ohlChangeTd(r.change)          + '</td>'
            + '<td>'                  + ohlPctTd(r.change_pct)         + '</td>'
            + '<td>'                  + ohlActionBadge(r.trade_action) + '</td>'
            + '</tr>';
    });
    html('oh-tbody', h);
}

function ohlRenderOL(rows) {
    var isOpt = ohlInst === 'option';
    var cols  = isOpt ? 11 : 10;
    if (!rows.length) { html('ol-tbody', ohlEmptyHtml(cols, 'No Open=Low signals found.')); return; }
    var h = '';
    rows.forEach(function (r, i) {
        h += '<tr class="' + (i % 2 === 0 ? 'tr-even' : 'tr-odd') + '">'
            + '<td class="c-num">'  + (i + 1) + '</td>'
            + '<td class="c-date">' + r.date   + '</td>'
            + '<td class="c-sym">'  + esc(r.symbol) + (r.expiry ? '<small>' + r.expiry + '</small>' : '') + '</td>'
            + (isOpt ? '<td>' + ohlOptBadge(r.opt_type) + '</td>' : '')
            + '<td class="c-open">₹' + f(r.open)      + '</td>'
            + '<td class="c-l915">₹' + f(r.low_open)  + '</td>'
            + '<td class="c-dl">₹'   + f(r.day_low)   + '</td>'
            + '<td class="c-ltp">₹'  + f(r.ltp)       + '</td>'
            + '<td>'                  + ohlChangeTd(r.change)          + '</td>'
            + '<td>'                  + ohlPctTd(r.change_pct)         + '</td>'
            + '<td>'                  + ohlActionBadge(r.trade_action) + '</td>'
            + '</tr>';
    });
    html('ol-tbody', h);
}

/* ── UI HELPERS ── */
function ohlShowLoading() {
    var spin = '<tr><td colspan="11"><div class="ohl-spinner-row">'
        + '<div class="ohl-spinner"></div>Analysing 09:15 candles…</div></td></tr>';
    html('oh-tbody', spin);
    html('ol-tbody', spin);
}

function ohlEmptyBoth(msg, cols) {
    var h = ohlEmptyHtml(cols || 10, msg);
    html('oh-tbody', h); html('ol-tbody', h);
    ohlUpdateCounts(0, 0, null);
}

function ohlEmptyHtml(cols, msg) {
    return '<tr><td colspan="' + cols + '">'
        + '<div class="ohl-empty">'
        + '<div class="ohl-empty-icon"><i class="las la-chart-area"></i></div>'
        + '<p>' + (msg || 'No data found.') + '</p>'
        + '</div></td></tr>';
}

function ohlUpdateCounts(oh, ol, tol) {
    txt('oh-count', oh);
    txt('ol-count', ol);
    if (tol !== null && tol !== undefined) {
        var t = '±' + parseFloat(tol).toFixed(1) + ' pts';
        txt('oh-tol', t); el('oh-tol').style.display = '';
        txt('ol-tol', t); el('ol-tol').style.display = '';
    } else {
        el('oh-tol').style.display = 'none';
        el('ol-tol').style.display = 'none';
    }
}

function ohlShowWarn(msg) { el('ohl-warn').classList.add('show'); txt('ohl-warn-msg', msg || ''); }
function ohlHideWarn()    { el('ohl-warn').classList.remove('show'); }

function ohlReset() {
    fetch(OHL_LASTDATE + '?instrument=' + ohlInst, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        el('ohl-date').value = res.last_date || OHL_TODAY;
        el('ohl-tol').value  = '1';
        el('ohl-sym').value  = 'ALL';
        ohlHideWarn(); ohlAnalyze();
    })
    .catch(function () {
        el('ohl-date').value = OHL_TODAY;
        el('ohl-tol').value  = '1';
        el('ohl-sym').value  = 'ALL';
        ohlHideWarn(); ohlAnalyze();
    });
}

/* ── BADGE HELPERS ── */
function ohlActionBadge(action) {
    var map = {
        'BUY PE':  '<span class="act-badge act-buy-pe">BUY PE</span>',
        'BUY CE':  '<span class="act-badge act-buy-ce">BUY CE</span>',
        'SELL CE': '<span class="act-badge act-sell-ce">SELL CE</span>',
        'SELL PE': '<span class="act-badge act-sell-pe">SELL PE</span>',
    };
    return map[action] || '<span style="color:var(--c-muted);font-size:9px;">' + (action || '—') + '</span>';
}

function ohlOptBadge(type) {
    if (type === 'CE') return '<span class="c-opt-ce">CE</span>';
    if (type === 'PE') return '<span class="c-opt-pe">PE</span>';
    return '<span style="color:var(--c-muted);">—</span>';
}

function ohlChangeTd(v) {
    var n = parseFloat(v) || 0;
    if (n > 0) return '<span class="c-up">▲ ₹'   + f(n)           + '</span>';
    if (n < 0) return '<span class="c-down">▼ ₹' + f(Math.abs(n)) + '</span>';
    return '<span class="c-neu">₹' + f(n) + '</span>';
}

function ohlPctTd(v) {
    var n = parseFloat(v) || 0;
    if (n > 0) return '<span class="c-up">+'  + f(n) + '%</span>';
    if (n < 0) return '<span class="c-down">' + f(n) + '%</span>';
    return '<span class="c-neu">' + f(n) + '%</span>';
}

function f(v)   { return parseFloat(v || 0).toFixed(2); }
function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush