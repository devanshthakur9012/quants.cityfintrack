{{-- FILE: resources/views/themes/{active_theme}/user/straddle-strategy/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* ── BASE ── */
.ss-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.ss-wrap * { box-sizing:border-box; }
.ss-wrap h1,.ss-wrap h2,.ss-wrap h3,.ss-wrap h4 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes ssFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.ss-anim { animation:ssFadeUp .5s ease both; }
@keyframes ssSpin { to{ transform:rotate(360deg); } }

/* ── HERO ── */
.ss-hero {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:32px 48px; display:flex; align-items:center;
    justify-content:space-between; gap:24px;
}
.ss-hero-left h1 {
    font-size:clamp(26px,3.5vw,42px); font-weight:700;
    color:#1a1a2e; margin:0 0 8px; line-height:1.1;
}
.ss-hero-left h1 span { color:#7DFF00; }
.ss-hero-left p { font-size:13px; color:#666; margin:0; line-height:1.7; max-width:560px; }
.ss-hero-pills { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
.ss-pill {
    display:inline-block; padding:3px 10px; border-radius:4px;
    font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700;
}
.ss-pill-factor  { background:rgba(245,166,35,.12); color:#c97f00; border:1px solid rgba(245,166,35,.3); }
.ss-pill-ce      { background:rgba(5,150,105,.1);   color:#047857; border:1px solid rgba(5,150,105,.3);  }
.ss-pill-pe      { background:rgba(220,38,38,.08);  color:#b91c1c; border:1px solid rgba(220,38,38,.25); }
.ss-hero-icon {
    width:80px; height:80px; border-radius:16px;
    background:linear-gradient(135deg,#0f1b2d,#1a3050);
    display:flex; align-items:center; justify-content:center;
    font-size:36px; color:#7DFF00; flex-shrink:0;
}
@media(max-width:768px){
    .ss-hero { flex-direction:column; padding:24px 16px; text-align:center; }
    .ss-hero-pills { justify-content:center; }
}

/* ── FILTER BAR ── */
.ss-filter-bar {
    background:#fff; border-bottom:1px solid #e8e8e8;
    padding:0 48px; position:sticky; top:0; z-index:200;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.ss-filter-inner {
    display:flex; align-items:center; gap:14px;
    padding:13px 0; flex-wrap:wrap;
}
.ss-filter-label {
    font-size:10.5px; color:#999; font-weight:700;
    text-transform:uppercase; letter-spacing:.07em;
}

/* Strategy select */
.ss-strat-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 30px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance:none; cursor:pointer; outline:none; min-width:180px;
}
.ss-strat-select:focus { border-color:#7DFF00; }

/* Symbol select */
.ss-sym-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 30px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance:none; cursor:pointer; outline:none; min-width:140px;
}
.ss-sym-select:focus { border-color:#7DFF00; }

/* Date */
.ss-date-wrap { display:flex; align-items:center; gap:4px; }
.ss-date-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
    color:#333; outline:none; cursor:pointer;
}
.ss-date-input:focus { border-color:#7DFF00; }
.ss-date-nav {
    width:28px; height:32px; border:1.5px solid #e5e9f2; border-radius:6px;
    background:#fff; color:#888; cursor:pointer; font-weight:700; font-size:14px;
    display:flex; align-items:center; justify-content:center; transition:.2s;
}
.ss-date-nav:hover { border-color:#7DFF00; color:#7DFF00; }
.ss-today-btn { width:auto; padding:0 10px; font-size:10px; font-family:'Exo 2',sans-serif; font-weight:700; letter-spacing:.07em; }

/* Status badge */
.ss-live-badge { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.ss-hist-badge { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }

/* Buttons */
.ss-load-btn {
    background:#7DFF00; color:#000; border:none; border-radius:8px;
    padding:8px 20px; font-family:'Rajdhani',sans-serif; font-size:13px;
    font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s;
}
.ss-load-btn:hover { background:#d4890e; }
.ss-reset-btn {
    background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px;
    padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer;
    font-family:'Exo 2',sans-serif; transition:.2s;
}
.ss-reset-btn:hover { border-color:#7DFF00; color:#c97f00; }

/* Filter pills */
.ss-fp-wrap { display:flex; gap:4px; flex-wrap:wrap; }
.ss-fp {
    padding:5px 13px; border-radius:20px; font-family:'Exo 2',sans-serif;
    font-size:10px; font-weight:700; cursor:pointer;
    border:1.5px solid #e5e9f2; background:#fff; color:#999; transition:.15s;
}
.ss-fp:hover         { border-color:#7DFF00; color:#c97f00; }
.ss-fp.f-all         { border-color:#7DFF00; background:rgba(245,166,35,.08); color:#c97f00; }
.ss-fp.f-ce          { border-color:#047857; background:rgba(5,150,105,.08);  color:#047857; }
.ss-fp.f-pe          { border-color:#b91c1c; background:rgba(220,38,38,.08);  color:#b91c1c; }
.ss-fp.f-wait        { border-color:#aab; background:#f7f8fc; color:#aab; }

.ss-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.ss-info-text { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.ss-last-upd  { font-size:10px; color:#ccc; font-family:'JetBrains Mono',monospace; }

.ss-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

@media(max-width:768px){
    .ss-filter-bar { padding:0 16px; }
    .ss-filter-inner { gap:8px; }
    .ss-filter-right { margin-left:0; width:100%; }
}

/* ── CONTENT ── */
.ss-content { padding:28px 48px 64px; }
@media(max-width:768px){ .ss-content { padding:16px 12px 48px; } }

/* Config warning */
.ss-warn {
    background:#fff3e0; border:1px solid #ffcc80; border-radius:10px;
    padding:16px 20px; margin-bottom:20px;
    display:none; align-items:center; gap:14px; font-size:13px; color:#e65100;
}
.ss-warn.show { display:flex; }
.ss-warn i { font-size:20px; flex-shrink:0; }

/* ── STATS ── */
.ss-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.ss-stat {
    background:#fff; border:1px solid #e8e8e8; border-radius:12px;
    padding:12px 18px; flex:1; min-width:100px;
}
.ss-stat small {
    display:block; font-size:9px; font-weight:700; text-transform:uppercase;
    letter-spacing:.1em; color:#aab; margin-bottom:4px; font-family:'Exo 2',sans-serif;
}
.ss-stat strong {
    display:block; font-family:'JetBrains Mono',monospace; font-size:1.2rem; font-weight:700;
}
.ss-stat-total { border-left:3px solid #7DFF00; }
.ss-stat-ce    { border-left:3px solid #047857; }
.ss-stat-pe    { border-left:3px solid #b91c1c; }
.ss-stat-wait  { border-left:3px solid #aab; }

/* ── CARD ── */
.ss-card {
    background:#fff; border-radius:12px; border:1px solid #e8e8e8;
    overflow:hidden; margin-bottom:24px;
}
.ss-card-header {
    padding:14px 20px; border-bottom:1px solid #f0f0f0;
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:8px; background:#fafafa;
}
.ss-card-title {
    font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700;
    color:#1a1a2e; display:flex; align-items:center; gap:8px;
}
.ss-card-info { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }

/* ── DETAIL HEADER ── */
.ss-detail-hdr {
    background:rgba(245,166,35,.04); border:1px solid rgba(245,166,35,.2);
    border-radius:12px; padding:14px 18px; margin-bottom:14px;
    display:flex; align-items:center; flex-wrap:wrap; gap:10px;
}
.ss-detail-sym { font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:900; color:#7DFF00; }
.ss-dm {
    border-radius:6px; padding:3px 10px; font-size:10px; font-weight:700;
    border:1px solid; font-family:'Exo 2',sans-serif;
}
.ss-dm-amber { background:rgba(245,166,35,.1); color:#c97f00; border-color:rgba(245,166,35,.25); }
.ss-dm-green { background:rgba(5,150,105,.1);  color:#047857; border-color:rgba(5,150,105,.25); }

/* ── TABLE ── */
.ss-table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.ss-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:1000px; }
.ss-table.detail-table { min-width:860px; }

.ss-table thead tr.th-group th {
    padding:9px 10px 5px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800;
    letter-spacing:.1em; text-transform:uppercase;
    background:#f7f8fc; border-bottom:none; white-space:nowrap;
}
.ss-table thead tr.th-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700;
    letter-spacing:.03em; text-transform:uppercase;
    background:#f4f6fb; color:#aab;
    border-bottom:2px solid #e8e8e8; white-space:nowrap;
}

/* Group colors */
.g-info  { color:#888 !important; }
.g-ce    { color:#047857 !important; }
.g-pe    { color:#b91c1c !important; }
.g-sig   { color:#c97f00 !important; }

/* Separators */
.sep-ce  { border-left:2px solid rgba(5,150,105,.2)  !important; }
.sep-pe  { border-left:2px solid rgba(220,38,38,.2)  !important; }
.sep-sig { border-left:2px solid rgba(245,166,35,.2) !important; }

/* Body cells */
.ss-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid #f5f5f5; vertical-align:middle;
    white-space:nowrap; color:#555;
}
.ss-table tbody tr { cursor:pointer; }
.ss-table tbody tr:hover { background:#fafbff !important; }
.tr-even     { background:#fff; }
.tr-odd      { background:#fbfcff; }
.tr-ce       { background:rgba(5,150,105,.04)  !important; border-left:2px solid #047857 !important; }
.tr-pe       { background:rgba(220,38,38,.04)  !important; border-left:2px solid #b91c1c !important; }
.tr-wait     { opacity:.75; }
.tr-entry    { background:rgba(245,166,35,.05) !important; border-left:2px solid #7DFF00 !important; }
.tr-latest   { background:rgba(124,58,237,.04) !important; border-left:2px solid #7c3aed !important; }

/* Cell styles */
.c-num   { font-size:9px; color:#ccc; }
.c-sym   { font-size:11px; font-weight:700; color:#1a56db; }
.c-time  { font-size:12px; font-weight:700; color:#7DFF00; }
.c-amber { color:#c97f00; font-weight:700; }
.c-green { color:#047857; font-weight:700; }
.c-red   { color:#b91c1c; font-weight:700; }
.c-muted { font-size:9px; color:#aab; }

/* Time pills */
.tp-entry  { display:inline-block; background:rgba(245,166,35,.15); color:#c97f00;  border:1px solid rgba(245,166,35,.35); border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.tp-latest { display:inline-block; background:rgba(124,58,237,.12); color:#6d28d9; border:1px solid rgba(124,58,237,.3);  border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }

/* Signal badges */
.sig-ce   { display:inline-block; background:rgba(5,150,105,.12);  color:#047857; border:1px solid rgba(5,150,105,.35);  border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-pe   { display:inline-block; background:rgba(220,38,38,.10);  color:#b91c1c; border:1px solid rgba(220,38,38,.3);   border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-wait { display:inline-block; background:#f7f8fc; color:#aab;  border:1px solid #e8e8e8; border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:9px; }

/* Score bars */
.score-wrap  { display:flex; align-items:center; gap:4px; justify-content:center; }
.score-num   { font-size:12px; font-weight:800; min-width:14px; }
.score-track { width:40px; height:4px; background:#f0f0f0; border-radius:2px; overflow:hidden; }
.score-fill  { height:100%; border-radius:2px; }

/* Factor dots */
.fd-wrap { display:flex; align-items:center; gap:3px; justify-content:center; flex-wrap:wrap; }
.fd { width:9px; height:9px; border-radius:50%; display:inline-block; }
.fd-ce   { background:#047857; box-shadow:0 0 4px rgba(5,150,105,.5); }
.fd-pe   { background:#b91c1c; box-shadow:0 0 4px rgba(220,38,38,.5); }
.fd-neut { background:#e5e9f2; }
.fd-na   { background:#f4f6fb; border:1px solid #e8e8e8; }

/* Symbol badge */
.sym-badge { display:inline-block; padding:2px 9px; border-radius:5px; font-size:11px; font-weight:800; background:rgba(245,166,35,.1); color:#c97f00; border:1px solid rgba(245,166,35,.25); }

/* Factor legend */
.ss-legend { padding:10px 18px; border-top:1px solid #f0f0f0; font-size:10px; color:#aab; line-height:2; background:#fafafa; }
.ss-legend strong { color:#888; }

/* Loading / empty */
.ss-loading {
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; padding:60px 20px;
}
.ss-spinner {
    width:36px; height:36px; border:3px solid #f0f0f0;
    border-top:3px solid #7DFF00; border-radius:50%;
    animation:ssSpin 1s linear infinite;
}
.ss-loading-text { color:#aab; margin-top:12px; font-size:13px; }
.ss-empty { text-align:center; padding:56px 20px; color:#ccc; }
.ss-empty i { font-size:2.5rem; display:block; margin-bottom:12px; }
</style>

<div class="ss-wrap">

{{-- ══ HERO ══ --}}
<div class="ss-hero ss-anim">
    <div class="ss-hero-left">
        <h1>Straddle &amp; Strangle <span>Signal Engine</span></h1>
        <p>
            5-factor directional scoring for Long/Short Straddle &amp; Strangle setups —
            powered by live 15min candle data. Minimum 3/5 factors required to fire a signal.
        </p>
        <div class="ss-hero-pills">
            <span class="ss-pill ss-pill-factor">5-FACTOR SCORING</span>
            <span class="ss-pill ss-pill-factor">MIN 3/5 TO SIGNAL</span>
            <span class="ss-pill ss-pill-ce">▲ BUY CE</span>
            <span class="ss-pill ss-pill-pe">▼ BUY PE</span>
            <span class="ss-pill ss-pill-factor">Futures · OI · Premium · PCR · Structure</span>
        </div>
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

        <div class="ss-sep"></div>

        {{-- Symbol --}}
        <span class="ss-filter-label">Symbol</span>
        <select id="ss-sym" class="ss-sym-select" onchange="ssLoad()">
            <option value="ALL">— All —</option>
        </select>
        <button class="ss-reset-btn" onclick="ssClearSym()">All Symbols</button>

        <div class="ss-sep"></div>

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

        <div class="ss-sep"></div>

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
                Go to Admin → Analysis Config and create a 15min config with symbols.
            </div>
        </div>
    </div>

    {{-- Stats (summary mode only) --}}
    <div class="ss-stats" id="ss-stats" style="display:none;">
        <div class="ss-stat ss-stat-total">
            <small>Total</small>
            <strong id="ss-st-total" style="color:#7DFF00;">0</strong>
        </div>
        <div class="ss-stat ss-stat-ce">
            <small>▲ Buy CE</small>
            <strong id="ss-st-ce" style="color:#047857;">0</strong>
        </div>
        <div class="ss-stat ss-stat-pe">
            <small>▼ Buy PE</small>
            <strong id="ss-st-pe" style="color:#b91c1c;">0</strong>
        </div>
        <div class="ss-stat ss-stat-wait">
            <small>— Wait</small>
            <strong id="ss-st-wait" style="color:#aab;">0</strong>
        </div>
    </div>

    {{-- Main output area --}}
    <div id="ss-output">
        <div class="ss-card">
            <div class="ss-card-header">
                <div class="ss-card-title" id="ss-card-title">
                    ◆ Straddle &amp; Strangle Signal Engine · 15min
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
                            <th class="g-sig">Reason</th>
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
                &nbsp;▲ <strong style="color:#047857;">Futures Momentum</strong> (bullish/bearish candle)
                &nbsp;·&nbsp; ▲ <strong style="color:#047857;">OI Confirmation</strong> (OI ↑ + LTP ↑ = fresh buying)
                &nbsp;·&nbsp; ▲ <strong style="color:#047857;">Premium Momentum</strong> (which leg gaining faster, need &gt;2% diff)
                &nbsp;·&nbsp; ▲ <strong style="color:#047857;">PCR</strong> (&lt;0.80 bullish, &gt;1.20 bearish)
                &nbsp;·&nbsp; ▲ <strong style="color:#047857;">Candle Structure</strong> (new high + bullish close = breakout)
                &nbsp;&nbsp;
                <span style="color:#047857;">●</span> = CE factor &nbsp;
                <span style="color:#b91c1c;">●</span> = PE factor &nbsp;
                <span style="color:#e5e9f2;border:1px solid #e8e8e8;border-radius:50%;display:inline-block;width:9px;height:9px;vertical-align:middle;"></span> = Neutral
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

    // Show loading in current mode area
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
            + '<div class="ss-loading-text" style="margin-top:12px;color:#aab;">Loading…</div>'
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
            ssShowEmptyTable('No active 15min config.');
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
    // Only rebuild if the shell table was replaced by detail view
    if (!document.getElementById('ss-tbody')) {
        outputEl.innerHTML =
            '<div class="ss-card">'
            + '<div class="ss-card-header">'
            + '<div class="ss-card-title" id="ss-card-title">◆ Straddle &amp; Strangle Signal Engine · 15min</div>'
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
            + ' ▲ <strong style="color:#047857;">Futures Momentum</strong>'
            + ' · ▲ <strong style="color:#047857;">OI Confirmation</strong>'
            + ' · ▲ <strong style="color:#047857;">Premium Momentum</strong>'
            + ' · ▲ <strong style="color:#047857;">PCR</strong>'
            + ' · ▲ <strong style="color:#047857;">Candle Structure</strong>'
            + ' &nbsp; <span style="color:#047857;">●</span> CE &nbsp;'
            + ' <span style="color:#b91c1c;">●</span> PE'
            + '</div></div>';
        // Show filter pills again
        document.getElementById('ss-fp-wrap').style.display = '';
        document.getElementById('ss-fp-label').style.display = '';
        document.getElementById('ss-stats').style.display = '';
    }
}

// ═══════════════════════════════════════════════════════════════
//  RENDERERS
// ═══════════════════════════════════════════════════════════════

function ssRenderSummary(res) {
    // Show filter pills
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
            + '<td style="font-weight:700;color:#1a1a2e;">' + (r.spot !== null ? '₹' + ssFmt(r.spot) : ssDash()) + '</td>'
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
            + '<td style="font-size:9px;color:#aab;text-align:left;max-width:200px;white-space:normal;padding:7px 10px;">'
            + ssEsc(r.reason || '—') + '</td>'
            + '</tr>';
    });

    ssHtml('ss-tbody', html || ssEmptyRows(15, 'No data for the selected filters.'));
    document.getElementById('ss-stats').style.display = '';
}

function ssRenderDetail(res) {
    // Hide filter pills — not needed in detail mode
    document.getElementById('ss-fp-wrap').style.display = 'none';
    document.getElementById('ss-fp-label').style.display = 'none';
    document.getElementById('ss-stats').style.display = 'none';

    // Detail header
    var hdr = '<div class="ss-detail-hdr">'
        + '<span class="ss-detail-sym">◆ ' + ssEsc(res.symbol) + '</span>'
        + '<span class="ss-dm ss-dm-amber">ATM ₹' + ssFmt(res.atm_strike) + '</span>'
        + '<span class="ss-dm ss-dm-amber">Expiry: ' + ssEsc(res.expiry || '—') + '</span>'
        + '<span class="ss-dm ss-dm-green">' + ssEsc(res.strategy_name) + '</span>'
        + '<span class="ss-dm ss-dm-green">15min</span>'
        + '<span class="ss-dm ss-dm-amber">Latest: ' + ssEsc(res.latest_slot || '—') + '</span>'
        + '<button onclick="ssClearSym()" style="margin-left:auto;background:#fff;border:1.5px solid #e5e9f2;color:#666;border-radius:8px;padding:5px 14px;font-family:\'Exo 2\',sans-serif;font-size:11px;font-weight:700;cursor:pointer;">‹ All Symbols</button>'
        + '</div>';

    var html = '';
    var data = res.data || [];

    data.forEach(function(r, i) {
        var sig   = r.signal || 'WAIT';
        var isCe  = sig === 'BUY_CE';
        var isPe  = sig === 'BUY_PE';
        var rowCls;
        if (r.is_entry)  rowCls = 'tr-entry';
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
            + '<td style="font-weight:700;color:#1a1a2e;">' + (r.spot !== null ? '₹' + ssFmt(r.spot) : ssDash()) + '</td>'
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
            + '<td style="font-size:9px;color:#aab;text-align:left;max-width:220px;white-space:normal;padding:7px 10px;">'
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
        + '<span style="color:#7DFF00;">▲ Amber row</span> = Entry 09:15 &nbsp;'
        + '<span style="color:#6d28d9;">▼ Purple row</span> = Latest candle &nbsp;·&nbsp; '
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
        + '<span class="score-num" style="color:#047857;">' + ceScore + '</span>'
        + '<div class="score-track"><div class="score-fill" style="width:' + cePct + '%;background:#047857;"></div></div>'
        + '</div>'
        + '<div class="score-wrap">'
        + '<span class="score-num" style="color:#b91c1c;">' + peScore + '</span>'
        + '<div class="score-track"><div class="score-fill" style="width:' + pePct + '%;background:#b91c1c;"></div></div>'
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
function ssDash() { return '<span style="color:#ccc;font-size:9px;">—</span>'; }
function ssEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
@endpush