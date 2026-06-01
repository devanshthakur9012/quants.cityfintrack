{{-- FILE: resources/views/themes/{active_theme}/user/index-driven-signal/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
.ids-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; background:#f7f8fc; }
.ids-wrap * { box-sizing:border-box; }
.ids-wrap h1,.ids-wrap h2,.ids-wrap h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
.mono { font-family:'JetBrains Mono',monospace; }
@keyframes idsUp   { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.ids-anim { animation:idsUp .5s ease both; }
@keyframes idsSpin { to{transform:rotate(360deg);} }

/* ── HERO ── */
.ids-hero { background:#fff; border-bottom:1px solid #e8e8e8; padding:32px 48px; display:flex; align-items:center; justify-content:space-between; gap:24px; }
.ids-hero-left h1 { font-size:clamp(24px,3.5vw,40px); font-weight:700; color:#1a1a2e; margin:0 0 8px; line-height:1.1; }
.ids-hero-left h1 span { color:#7DFF00; }
.ids-hero-left p { font-size:13px; color:#666; margin:0; line-height:1.7; max-width:640px; }
.ids-hero-icon { width:76px; height:76px; border-radius:16px; background:linear-gradient(135deg,#0f1b2d,#1a3050); display:flex; align-items:center; justify-content:center; font-size:32px; color:#7DFF00; flex-shrink:0; }
@media(max-width:768px){ .ids-hero{ flex-direction:column; padding:24px 16px; text-align:center; } .ids-hero-icon{ display:none; } }

/* ── FILTER BAR ── */
.ids-filter-bar { background:#fff; border-bottom:1px solid #e8e8e8; padding:0 48px; position:sticky; top:0; z-index:200; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.ids-filter-inner { display:flex; align-items:center; gap:12px; padding:12px 0; flex-wrap:wrap; }
.ids-filter-label { font-size:10.5px; color:#999; font-weight:700; text-transform:uppercase; letter-spacing:.07em; flex-shrink:0; }
.ids-sep { width:1px; height:28px; background:#e8e8e8; flex-shrink:0; }

/* Symbol select — single like pivot */
.ids-sym-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 30px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance:none; cursor:pointer; outline:none; min-width:140px;
}
.ids-sym-select:focus { border-color:#7DFF00; }

/* Date controls — same as pivot */
.ids-date-wrap { display:flex; align-items:center; gap:4px; }
.ids-date-input {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 10px;
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
    color:#333; outline:none; cursor:pointer;
}
.ids-date-input:focus { border-color:#7DFF00; }
.ids-date-nav {
    width:28px; height:32px; border:1.5px solid #e5e9f2; border-radius:6px;
    background:#fff; color:#888; cursor:pointer; font-weight:700; font-size:14px;
    display:flex; align-items:center; justify-content:center; transition:.2s;
}
.ids-date-nav:hover { border-color:#7DFF00; color:#7DFF00; }
.ids-today-btn { width:auto; padding:0 10px; font-size:10px; font-family:'Exo 2',sans-serif; font-weight:700; letter-spacing:.07em; }

/* Status badge */
.ids-live-badge { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }
.ids-hist-badge { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; border-radius:10px; font-size:10px; font-weight:700; padding:2px 9px; }

/* Generic select */
.ids-generic-select {
    border:1.5px solid #e5e9f2; border-radius:7px; padding:7px 28px 7px 10px;
    font-size:12px; font-weight:700; color:#333; font-family:'Exo 2',sans-serif;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 9px center;
    appearance:none; cursor:pointer; outline:none;
}
.ids-generic-select:focus { border-color:#7DFF00; }

/* Threshold */
.ids-thresh-wrap { display:flex; align-items:center; gap:8px; }
.ids-thresh-disp { font-family:'JetBrains Mono',monospace; font-size:14px; font-weight:700; color:#7DFF00; min-width:38px; text-align:center; background:rgba(245,166,35,.08); border:1px solid rgba(245,166,35,.3); border-radius:6px; padding:2px 6px; }
input[type=range].ids-range { accent-color:#7DFF00; width:110px; cursor:pointer; }

/* Buttons */
.ids-analyze-btn { background:#7DFF00; color:#000; border:none; border-radius:8px; padding:8px 22px; font-family:'Rajdhani',sans-serif; font-size:14px; font-weight:800; letter-spacing:.04em; cursor:pointer; transition:.2s; white-space:nowrap; }
.ids-analyze-btn:hover { background:#d4890e; }
.ids-reset-btn { background:#fff; border:1.5px solid #e5e9f2; color:#666; border-radius:8px; padding:7px 16px; font-size:12px; font-weight:700; cursor:pointer; transition:.2s; font-family:'Exo 2',sans-serif; }
.ids-reset-btn:hover { border-color:#7DFF00; color:#c97f00; }
.ids-filter-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.ids-info-text { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
@media(max-width:768px){ .ids-filter-bar{ padding:0 12px; } .ids-filter-inner{ gap:8px; } .ids-filter-right{ margin-left:0;width:100%; } }

/* ── CONTENT ── */
.ids-content { padding:28px 48px 64px; }
@media(max-width:768px){ .ids-content{ padding:16px 12px 48px; } }
.ids-warn { background:#fff3e0; border:1px solid #ffcc80; border-radius:10px; padding:14px 20px; margin-bottom:20px; display:none; align-items:center; gap:12px; font-size:13px; color:#e65100; }
.ids-warn.show { display:flex; }
.ids-warn i { font-size:18px; flex-shrink:0; }

/* ── STATS ── */
.ids-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:24px; }
@media(max-width:900px){ .ids-stats{ grid-template-columns:repeat(3,1fr); } }
@media(max-width:500px){ .ids-stats{ grid-template-columns:repeat(2,1fr); } }
.ids-stat-card { background:#fff; border-radius:12px; border:1px solid #e8e8e8; padding:14px 16px; border-left:3px solid #e8e8e8; }
.ids-stat-card.s-total { border-left-color:#1a56db; }
.ids-stat-card.s-ce    { border-left-color:#059669; }
.ids-stat-card.s-pe    { border-left-color:#dc2626; }
.ids-stat-card.s-syms  { border-left-color:#c97f00; }
.ids-stat-card.s-inv   { border-left-color:#7c3aed; }
.ids-stat-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#aab; margin-bottom:6px; }
.ids-stat-val { font-family:'JetBrains Mono',monospace; font-size:22px; font-weight:700; color:#1a1a2e; }
.s-total .ids-stat-val { color:#1a56db; }
.s-ce    .ids-stat-val { color:#047857; }
.s-pe    .ids-stat-val { color:#b91c1c; }
.s-syms  .ids-stat-val { color:#c97f00; }
.s-inv   .ids-stat-val { color:#6d28d9; font-size:16px; }

/* ── TABLE CARD ── */
.ids-card { background:#fff; border-radius:12px; border:1px solid #e8e8e8; overflow:hidden; margin-bottom:24px; }
.ids-card-header { padding:14px 20px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; background:#fafafa; }
.ids-card-title { font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:#1a1a2e; }
.ids-card-subtitle { font-size:11px; color:#aab; font-family:'JetBrains Mono',monospace; }
.ids-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

.ids-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:1100px; }
.ids-table thead tr.th-group th { padding:9px 10px 5px; text-align:center; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; background:#f7f8fc; border-bottom:none; white-space:nowrap; }
.ids-table thead tr.th-cols th  { padding:5px 10px 9px; text-align:center; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; background:#f4f6fb; color:#aab; border-bottom:2px solid #e8e8e8; white-space:nowrap; }
.g-info   { color:#555 !important; }
.g-nifty  { color:#1a56db !important; }
.g-option { color:#c97f00 !important; }
.g-entry  { color:#047857 !important; }
.sep-nifty  { border-left:2px solid rgba(26,86,219,.2) !important; }
.sep-option { border-left:2px solid rgba(245,166,35,.2) !important; }
.sep-entry  { border-left:2px solid rgba(5,150,105,.2)  !important; }

.ids-table tbody td { padding:8px 10px; text-align:center; font-size:11px; border-bottom:1px solid #f5f5f5; vertical-align:middle; white-space:nowrap; color:#555; }
.ids-table tbody tr:hover { background:#fafbff !important; }
.tr-even { background:#fff; }
.tr-odd  { background:#fbfcff; }
.tr-ce   { background:rgba(5,150,105,.03) !important; }
.tr-pe   { background:rgba(220,38,38,.03) !important; }

.ids-group-row td { background:linear-gradient(90deg,rgba(26,86,219,.06),rgba(26,86,219,.01)) !important; border-top:2px solid rgba(26,86,219,.15) !important; border-bottom:none !important; padding:10px 16px !important; text-align:left !important; font-family:'Exo 2',sans-serif; font-size:12px; font-weight:700; color:#1a56db !important; letter-spacing:.03em; }
.ids-group-row.gr-pe td { background:linear-gradient(90deg,rgba(220,38,38,.06),rgba(220,38,38,.01)) !important; border-top-color:rgba(220,38,38,.15) !important; color:#b91c1c !important; }

.c-num  { font-size:9px; color:#ccc; }
.c-date { font-size:11px; font-weight:700; color:#7DFF00; }
.c-sym  { font-size:12px; font-weight:800; color:#1a56db; }
.c-val  { font-size:11px; font-weight:700; color:#1a1a2e; }
.c-sm   { font-size:10px; color:#aab; }
.up     { color:#059669; font-weight:700; }
.dn     { color:#dc2626; font-weight:700; }

.sig-ce { display:inline-block; background:rgba(5,150,105,.12); color:#047857; border:1px solid rgba(5,150,105,.35); border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.sig-pe { display:inline-block; background:rgba(220,38,38,.1); color:#b91c1c; border:1px solid rgba(220,38,38,.35); border-radius:6px; padding:3px 10px; font-family:'Exo 2',sans-serif; font-size:10px; font-weight:800; }
.time-badge     { display:inline-block; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700; background:rgba(26,86,219,.08); border:1px solid rgba(26,86,219,.25); color:#1d4ed8; padding:2px 8px; border-radius:5px; }
.time-badge.pe  { background:rgba(220,38,38,.08); border-color:rgba(220,38,38,.25); color:#b91c1c; }
.time-badge.buy { background:rgba(245,166,35,.1); border-color:rgba(245,166,35,.3); color:#c97f00; }

.ids-empty { text-align:center; padding:56px 20px; color:#ccc; }
.ids-empty i { font-size:2.5rem; display:block; margin-bottom:12px; color:#e5e9f2; }
.ids-empty p { font-size:13px; }
.ids-spinner-row { display:flex; align-items:center; justify-content:center; gap:12px; padding:48px; color:#aab; font-size:13px; }
.ids-spinner { width:28px; height:28px; border:3px solid #f0f0f0; border-top:3px solid #7DFF00; border-radius:50%; animation:idsSpin 1s linear infinite; flex-shrink:0; }

/* ── EXIT P&L ── */
.ids-pnl-section { background:#fff; border-radius:12px; border:1px solid #e8e8e8; overflow:hidden; margin-bottom:24px; }
.ids-pnl-header { padding:14px 20px; border-bottom:1px solid #f0f0f0; background:#fafafa; }
.ids-pnl-header-title { font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:#1a1a2e; margin-bottom:8px; }
.ids-pnl-callout { background:rgba(26,86,219,.05); border:1px solid rgba(26,86,219,.15); border-radius:8px; padding:10px 14px; font-size:13px; color:#555; line-height:1.7; margin-bottom:12px; }
.ids-pnl-callout strong { color:#1d4ed8; }
.ids-pnl-btn-row { display:flex; gap:10px; flex-wrap:wrap; }
.ids-pnl-btn { border:none; border-radius:8px; padding:8px 20px; font-family:'Rajdhani',sans-serif; font-size:13px; font-weight:800; cursor:pointer; transition:.2s; }
.ids-pnl-btn.ce { background:rgba(5,150,105,.1); color:#047857; border:1.5px solid rgba(5,150,105,.3); }
.ids-pnl-btn.ce:hover { background:rgba(5,150,105,.2); }
.ids-pnl-btn.pe { background:rgba(220,38,38,.08); color:#b91c1c; border:1.5px solid rgba(220,38,38,.25); }
.ids-pnl-btn.pe:hover { background:rgba(220,38,38,.18); }
.ids-pnl-body { padding:20px; }
.ids-pnl-card { border-radius:10px; border:1px solid #e8e8e8; overflow:hidden; margin-bottom:16px; }
.ids-pnl-card-hdr { padding:12px 16px; font-family:'Rajdhani',sans-serif; font-size:14px; font-weight:700; border-bottom:1px solid #f0f0f0; }
.ids-pnl-card.type-ce .ids-pnl-card-hdr { background:rgba(5,150,105,.06); color:#047857; }
.ids-pnl-card.type-pe .ids-pnl-card-hdr { background:rgba(220,38,38,.05); color:#b91c1c; }

.pnl-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; min-width:600px; }
.pnl-table thead th { padding:9px 12px; text-align:center; font-family:'Exo 2',sans-serif; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; background:#f4f6fb; color:#aab; border-bottom:2px solid #e8e8e8; white-space:nowrap; }
.pnl-table tbody td { padding:9px 12px; text-align:center; font-size:11px; border-bottom:1px solid #f5f5f5; vertical-align:middle; color:#555; }
.pnl-table tbody tr:hover { background:#fafbff !important; }
.pnl-best  { background:rgba(5,150,105,.05) !important; }
.pnl-worst { background:rgba(220,38,38,.05) !important; }
.best-tag  { display:inline-block; background:#059669; color:#fff; padding:1px 6px; border-radius:3px; font-size:9px; font-weight:800; margin-left:4px; }
.worst-tag { display:inline-block; background:#dc2626; color:#fff; padding:1px 6px; border-radius:3px; font-size:9px; font-weight:800; margin-left:4px; }
</style>

<div class="ids-wrap">

{{-- ══ HERO ══ --}}
<div class="ids-hero ids-anim">
    <div class="ids-hero-left">
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

        {{-- Symbol — single select like pivot --}}
        <span class="ids-filter-label">Symbol</span>
        <select id="ids-sym" class="ids-sym-select" onchange="idsAnalyze()">
            <option value="ALL">— All —</option>
        </select>

        <div class="ids-sep"></div>

        {{-- Single date with nav buttons — same as pivot --}}
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
            <span style="font-size:10px;color:#aab;">pts</span>
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
            <div style="font-size:12px;margin-top:3px;" id="ids-warn-msg">
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
                        <th>NIFTY Open<br><span style="font-size:7px;font-weight:400;opacity:.6;">09:15</span></th>
                        <th>Trigger Val</th>
                        <th>Signal Bar<br><span style="font-size:7px;font-weight:400;opacity:.6;">time</span></th>
                        <th>Move (pts)</th>
                        <th class="sep-option">Strike</th>
                        <th>OI</th><th>Expiry</th><th>Lot Size</th>
                        <th class="sep-entry">Buy Time<br><span style="font-size:7px;font-weight:400;opacity:.6;">next candle</span></th>
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
            <div class="ids-pnl-header-title">📈 Exit P&L — Aggregate All-Symbol Exit Scenarios</div>
            <div class="ids-pnl-callout">
                <strong>How this works:</strong> After the breakout signal fires and we buy ATM options
                across all configured symbols at the next candle's open, this shows the aggregate P&L
                if you exit <strong>all positions simultaneously</strong> at every subsequent candle open.
                Run Analyze first, then load CE or PE exit tables.
            </div>
            <div class="ids-pnl-btn-row">
                <button class="ids-pnl-btn ce" onclick="idsLoadPnl('CE')">▲ Load CE Exit P&L</button>
                <button class="ids-pnl-btn pe" onclick="idsLoadPnl('PE')">▼ Load PE Exit P&L</button>
            </div>
        </div>
        <div class="ids-pnl-body">
            <div id="ce-pnl-wrap" style="display:none;"></div>
            <div id="pe-pnl-wrap" style="display:none;"></div>
            <div id="pnl-placeholder" style="text-align:center;padding:32px;color:#ccc;font-size:13px;">
                <i class="las la-chart-line" style="font-size:2rem;display:block;margin-bottom:8px;color:#e5e9f2;"></i>
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
//  Index-Driven Signal Scanner — JS (no jQuery)
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

// ── Symbols — single select like pivot ───────────────────

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
            'Threshold: <span style="color:#7DFF00;">' + res.threshold + 'pts</span>'
            + ' &nbsp;·&nbsp; Signals: <span style="color:#c97f00;">' + res.trigger_count + '</span>'
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
            + '<td class="sep-option c-val" style="color:#c97f00;">₹' + fmtOI(r.strike) + '</td>'
            + '<td class="c-sm">' + fmtOI(r.strike_oi) + '</td>'
            + '<td class="c-sm">' + (r.expiry_date || '—') + '</td>'
            + '<td class="c-sm">' + r.lot_size + '</td>'
            + '<td class="sep-entry"><span class="time-badge buy">' + r.buy_time + '</span></td>'
            + '<td><strong class="up">₹' + r.buy_price.toFixed(2) + '</strong></td>'
            + '<td><strong>₹' + fmt2(r.investment) + '</strong></td>'
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
        + '<div class="ids-spinner-row"><div class="ids-spinner" style="border-top-color:' + (type === 'CE' ? '#059669' : '#dc2626') + '"></div>Computing ' + type + ' exits…</div>'
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
                    + '<div style="text-align:center;padding:24px;color:#aab;font-size:13px;">No exit data found.</div></div>';
                return;
            }
            idsRenderPnl(type, slots, wrapId);
        })
        .catch(function (err) {
            el(wrapId).innerHTML = '<div style="text-align:center;padding:24px;color:#dc2626;font-size:13px;">⚠ Error: ' + err.message + '</div>';
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
            + '<td><strong style="color:#c97f00;">₹' + fmt2(row.sell_total)    + '</strong></td>'
            + '<td><strong>₹'                        + fmt2(row.investment)    + '</strong></td>'
            + '<td><strong class="' + plCls  + '">' + plSign + '₹' + fmt2(Math.abs(row.profit)) + '</strong></td>'
            + '<td><strong class="' + roiCls + '">' + rSign  + Math.abs(row.roi).toFixed(2) + '%</strong></td>'
            + '<td class="c-sm">' + row.trade_count + '</td>'
            + '</tr>';
    });

    el(wrapId).innerHTML = '<div class="ids-pnl-card type-' + type.toLowerCase() + '">'
        + '<div class="ids-pnl-card-hdr">' + (type === 'CE' ? '▲ CE' : '▼ PE') + ' Exit P&L &nbsp;<span style="font-size:10px;font-weight:400;color:#aab;">(' + slots.length + ' exit slots)</span></div>'
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
    return '<tr><td colspan="15"><div class="ids-empty"><i class="las la-bolt"></i><p>' + (msg || 'No data found.') + '</p></div></td></tr>';
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