@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ─────────────────────────────────────────────────────
   OI ENGINE — DESIGN SYSTEM
   Dark trading terminal aesthetic with neon accents
───────────────────────────────────────────────────── */
:root {
    --bg-base:      #0b0f1a;
    --bg-card:      #111827;
    --bg-row-alt:   rgba(255,255,255,0.02);
    --border:       rgba(255,255,255,0.07);
    --accent-cyan:  #00e5ff;
    --accent-green: #00e676;
    --accent-red:   #ff1744;
    --accent-amber: #ffab00;
    --accent-purple:#d500f9;
    --accent-blue:  #2979ff;
    --text-primary: #e8eaf6;
    --text-dim:     #546e7a;
    --text-muted:   #37474f;
    --radius:       6px;
    --font-mono:    'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
}

/* ── Layout ────────────────────────────────────────── */
.oe-page { background: var(--bg-base); min-height:100vh; padding:24px 20px 60px; }

/* ── Header ────────────────────────────────────────── */
.oe-header {
    border:1px solid rgba(0,229,255,0.2);
    border-radius:12px;
    padding:20px 24px;
    background: linear-gradient(135deg, rgba(0,229,255,0.06), rgba(41,121,255,0.04));
    margin-bottom:22px;
    display:flex; justify-content:space-between; align-items:center;
    flex-wrap:wrap; gap:12px;
}
.oe-header h4 {
    color: var(--accent-cyan);
    font-family: var(--font-mono);
    font-size:18px; font-weight:700; margin:0;
    letter-spacing:.5px;
}
.oe-header p { color:var(--text-dim); font-size:11px; margin:4px 0 0; }
.oe-badge {
    background: linear-gradient(90deg, #00e5ff22, #2979ff22);
    border:1px solid rgba(0,229,255,0.3);
    color: var(--accent-cyan);
    font-size:9px; font-weight:700;
    padding:3px 10px; border-radius:20px;
    letter-spacing:.8px; text-transform:uppercase;
    margin-left:8px;
}

/* ── Filter bar ────────────────────────────────────── */
.oe-filter {
    background: var(--bg-card);
    border:1px solid var(--border);
    border-radius:10px;
    padding:18px 20px;
    margin-bottom:20px;
}
.oe-filter label { color:var(--text-dim); font-size:11px; font-weight:600; display:block; margin-bottom:5px; text-transform:uppercase; letter-spacing:.4px; }
.oe-filter .form-control {
    background:#0d1117;
    border:1px solid rgba(255,255,255,0.1);
    color:var(--text-primary);
    font-size:12px; border-radius:6px;
    padding:7px 10px;
    font-family: var(--font-mono);
}
.oe-filter .form-control:focus { border-color: var(--accent-cyan); box-shadow:0 0 0 2px rgba(0,229,255,0.1); outline:none; }
.oe-filter select option { background:#0d1117; }

/* ── Buttons ────────────────────────────────────────── */
.btn-engine {
    background: linear-gradient(90deg, #00e5ff, #2979ff);
    color:#000; font-weight:700; font-size:13px;
    border:none; border-radius:7px;
    padding:10px 28px; cursor:pointer;
    font-family: var(--font-mono);
    letter-spacing:.3px;
    transition: box-shadow .2s, transform .15s;
}
.btn-engine:hover { box-shadow:0 0 20px rgba(0,229,255,0.4); transform:translateY(-1px); }
.btn-reset {
    background:transparent;
    border:1px solid rgba(255,255,255,0.15);
    color:var(--text-dim); font-size:13px;
    border-radius:7px; padding:10px 20px; cursor:pointer;
    font-family: var(--font-mono);
    transition: border-color .2s, color .2s;
}
.btn-reset:hover { border-color: var(--accent-amber); color: var(--accent-amber); }

/* ── Stats cards ────────────────────────────────────── */
.oe-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.oe-stat-card {
    background: var(--bg-card);
    border:1px solid var(--border);
    border-radius:8px;
    padding:12px 16px;
    min-width:110px; flex:1;
    text-align:center;
    border-top:2px solid transparent;
    transition: border-color .2s;
}
.oe-stat-card small { color:var(--text-dim); font-size:9px; text-transform:uppercase; letter-spacing:.5px; display:block; }
.oe-stat-card strong { display:block; font-size:1.3rem; font-weight:700; margin-top:4px; font-family:var(--font-mono); }
.oe-stat-card.cyan  { border-top-color: var(--accent-cyan); }
.oe-stat-card.green { border-top-color: var(--accent-green); }
.oe-stat-card.red   { border-top-color: var(--accent-red); }
.oe-stat-card.amber { border-top-color: var(--accent-amber); }
.oe-stat-card.purple{ border-top-color: var(--accent-purple); }
.oe-stat-card.blue  { border-top-color: var(--accent-blue); }

/* ── Legend ─────────────────────────────────────────── */
.oe-legend {
    background: var(--bg-card);
    border:1px solid var(--border);
    border-radius:10px;
    padding:14px 18px;
    margin-bottom:20px;
    display:flex; gap:0; flex-wrap:wrap;
}
.oe-legend-col { flex:1; min-width:200px; padding:0 14px; border-right:1px solid var(--border); }
.oe-legend-col:last-child { border-right:none; }
.oe-legend-col h6 { color:var(--accent-cyan); font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; font-family:var(--font-mono); }
.oe-legend-col li { color:var(--text-dim); font-size:10px; margin-bottom:3px; list-style:none; }
.oe-legend-col li strong { color:var(--text-primary); }
.oe-legend-col ul { padding:0; margin:0; }

/* ── Table ──────────────────────────────────────────── */
.oe-table-wrap {
    background: var(--bg-card);
    border:1px solid var(--border);
    border-radius:10px;
    overflow:hidden;
    position:relative;
}
.oe-table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.oe-table {
    width:100%; border-collapse:collapse;
    font-family: var(--font-mono);
    font-size:11px;
    min-width:1600px;
}
.oe-table thead th {
    background:#0d1117;
    color:var(--text-dim);
    font-size:9px; font-weight:700;
    text-transform:uppercase;
    letter-spacing:.5px;
    padding:10px 8px;
    border-bottom:1px solid var(--border);
    text-align:center;
    white-space:nowrap;
}
.oe-table thead th.th-group-ce   { border-bottom:2px solid rgba(255,171,0,0.5); }
.oe-table thead th.th-group-pe   { border-bottom:2px solid rgba(0,230,118,0.5); }
.oe-table thead th.th-group-eng  { border-bottom:2px solid rgba(0,229,255,0.5); }
.oe-table thead th.th-group-comb { border-bottom:2px solid rgba(213,0,249,0.5); }
.oe-table tbody td {
    padding:8px 7px;
    border-bottom:1px solid var(--border);
    text-align:center;
    color:var(--text-primary);
    vertical-align:middle;
}
.oe-table tbody tr:hover { background:rgba(0,229,255,0.03); }
.oe-table tbody tr:nth-child(even) { background: var(--bg-row-alt); }

/* Sticky first 3 cols */
.oe-table th:nth-child(1), .oe-table td:nth-child(1),
.oe-table th:nth-child(2), .oe-table td:nth-child(2),
.oe-table th:nth-child(3), .oe-table td:nth-child(3) { position:sticky; z-index:5; background:var(--bg-card); }
.oe-table th:nth-child(1), .oe-table td:nth-child(1) { left:0; }
.oe-table th:nth-child(2), .oe-table td:nth-child(2) { left:36px; }
.oe-table th:nth-child(3), .oe-table td:nth-child(3) { left:110px; }

/* ── Signal badges ─────────────────────────────────── */
.sb { display:inline-block; padding:3px 8px; border-radius:4px; font-size:9px; font-weight:700; letter-spacing:.3px; white-space:nowrap; }

.sb-bull-strong    { background:rgba(0,230,118,0.15); color:#00e676; border:1px solid rgba(0,230,118,0.35); }
.sb-bull           { background:rgba(0,230,118,0.08); color:#69f0ae; border:1px solid rgba(0,230,118,0.2); }
.sb-bull-accum     { background:rgba(41,121,255,0.15); color:#82b1ff; border:1px solid rgba(41,121,255,0.4); }
.sb-bear-strong    { background:rgba(255,23,68,0.15); color:#ff1744; border:1px solid rgba(255,23,68,0.35); }
.sb-bear           { background:rgba(255,23,68,0.08); color:#ff6090; border:1px solid rgba(255,23,68,0.2); }
.sb-bear-weak      { background:rgba(255,171,0,0.1); color:#ffab00; border:1px solid rgba(255,171,0,0.3); }
.sb-wait           { background:rgba(84,110,122,0.15); color:#78909c; border:1px solid rgba(84,110,122,0.3); }
.sb-neutral        { background:rgba(84,110,122,0.1);  color:#90a4ae; border:1px solid rgba(84,110,122,0.2); }

/* Phase badges */
.ph { display:inline-block; padding:2px 7px; border-radius:3px; font-size:8px; font-weight:700; letter-spacing:.2px; }
.ph-agg-build  { background:#ff174422; color:#ff6090; }
.ph-build      { background:#ffab0022; color:#ffd740; }
.ph-slow-build { background:#ffab0011; color:#ffcc80; }
.ph-strong-unw { background:#00e67622; color:#69f0ae; }
.ph-unw        { background:#00e67611; color:#a5d6a7; }
.ph-neutral    { background:#37474f22; color:#78909c; }

/* Speed badges */
.sp { display:inline-block; padding:2px 6px; border-radius:3px; font-size:8px; font-weight:700; }
.sp-accel { background:#2979ff22; color:#82b1ff; }
.sp-decel { background:#ff174411; color:#ff8a65; }
.sp-stable{ background:#37474f22; color:#78909c; }

/* Trend badges */
.tr { display:inline-block; padding:2px 6px; border-radius:3px; font-size:8px; font-weight:700; }
.tr-strong-build { background:#ffab0033; color:#ffd740; }
.tr-build        { background:#ffab0018; color:#ffcc80; }
.tr-strong-unw   { background:#00e67633; color:#69f0ae; }
.tr-unw          { background:#00e67618; color:#a5d6a7; }
.tr-side         { background:#37474f22; color:#78909c; }

/* Confidence */
.conf-high   { color:var(--accent-green);  font-size:10px; font-weight:700; }
.conf-medium { color:var(--accent-amber);  font-size:10px; font-weight:700; }
.conf-low    { color:var(--text-dim);      font-size:10px; }
.conf-none   { color:var(--text-muted);    font-size:10px; }

/* Combined action */
.act-buy-ce { display:inline-block; padding:4px 10px; border-radius:5px; font-size:10px; font-weight:700; background:rgba(0,230,118,0.12); color:#00e676; border:1px solid rgba(0,230,118,0.3); }
.act-buy-pe { display:inline-block; padding:4px 10px; border-radius:5px; font-size:10px; font-weight:700; background:rgba(255,23,68,0.12);  color:#ff4081; border:1px solid rgba(255,23,68,0.3); }
.act-wait   { display:inline-block; padding:4px 10px; border-radius:5px; font-size:10px; font-weight:700; background:rgba(84,110,122,0.1);  color:#78909c; border:1px solid rgba(84,110,122,0.25); }

/* Classic badge */
.cl-bull { display:inline-block; padding:2px 7px; border-radius:3px; font-size:9px; font-weight:700; background:rgba(0,230,118,0.1); color:#69f0ae; }
.cl-bear { display:inline-block; padding:2px 7px; border-radius:3px; font-size:9px; font-weight:700; background:rgba(255,23,68,0.1); color:#ff6090; }
.cl-neut { display:inline-block; padding:2px 7px; border-radius:3px; font-size:9px; font-weight:700; background:rgba(84,110,122,0.1); color:#78909c; }

/* Pct cell colors */
.pct-pos { color:#69f0ae; font-weight:700; }
.pct-neg { color:#ff6090; font-weight:700; }
.pct-zero{ color:#546e7a; }

/* ── Loading overlay ────────────────────────────────── */
.oe-loading {
    position:absolute; inset:0;
    background:rgba(11,15,26,0.93);
    display:flex; flex-direction:column;
    justify-content:center; align-items:center;
    z-index:100; border-radius:10px;
}
.oe-spinner {
    width:44px; height:44px;
    border:3px solid rgba(0,229,255,0.1);
    border-top:3px solid var(--accent-cyan);
    border-radius:50%;
    animation: oe-spin 1s linear infinite;
}
@keyframes oe-spin { to { transform:rotate(360deg); } }
.oe-loading-text { color:var(--accent-cyan); font-size:13px; margin-top:16px; font-family:var(--font-mono); letter-spacing:.5px; }

/* ── Empty state ────────────────────────────────────── */
.oe-empty { padding:60px 20px; text-align:center; color:var(--text-dim); }
.oe-empty i { font-size:3rem; opacity:.3; display:block; margin-bottom:16px; }

/* ── CE/PE section header row ───────────────────────── */
.th-sec-ce   { background:rgba(255,171,0,0.05)  !important; }
.th-sec-pe   { background:rgba(0,230,118,0.05)  !important; }
.th-sec-eng  { background:rgba(0,229,255,0.04)  !important; }
.th-sec-comb { background:rgba(213,0,249,0.05)  !important; }

/* Signal difference alert */
.mismatch-dot { display:inline-block; width:6px; height:6px; border-radius:50%; background:var(--accent-amber); margin-left:4px; vertical-align:middle; }

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 768px) {
    .oe-legend { flex-direction:column; }
    .oe-legend-col { border-right:none; border-bottom:1px solid var(--border); padding:10px 0; }
    .oe-stats .oe-stat-card { min-width:80px; }
}
</style>
@endpush

<div class="oe-page">

    {{-- ── Header ────────────────────────────────────────────── --}}
    <div class="oe-header">
        <div>
            <h4>⚡ OI ENGINE <span class="oe-badge">Phase · Speed · Trend · Intent</span></h4>
            <p>Advanced CE/PE OI analysis — distinguishes Call Writing vs Accumulation vs Unwinding</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn-reset" style="text-decoration:none; display:inline-block; line-height:1.4; font-size:12px;">
                ← EOD 3PM
            </a>
            <a href="{{ route('oiiv-auto.index') }}" class="btn-reset" style="text-decoration:none; display:inline-block; line-height:1.4; font-size:12px;">
                OI+IV
            </a>
        </div>
    </div>

    {{-- ── Legend ──────────────────────────────────────────────── --}}
    <div class="oe-legend">
        <div class="oe-legend-col">
            <h6>📊 Phase (OI change today)</h6>
            <ul>
                <li><strong>&gt; 50%</strong> → Aggressive Buildup</li>
                <li><strong>15–50%</strong> → Buildup</li>
                <li><strong>0–15%</strong> → Slow Buildup</li>
                <li><strong>&lt; 0%</strong> → Unwinding</li>
                <li><strong>&lt; -20%</strong> → Strong Unwinding</li>
            </ul>
        </div>
        <div class="oe-legend-col">
            <h6>⚡ Speed (momentum)</h6>
            <ul>
                <li><strong>Today % &gt; Yesterday %</strong> → Accelerating</li>
                <li><strong>Today % &lt; Yesterday %</strong> → Decelerating</li>
                <li><strong>Equal</strong> → Stable</li>
                <li style="margin-top:6px; color:#78909c;"><em>Decelerating Buildup = Saturation zone</em></li>
            </ul>
        </div>
        <div class="oe-legend-col">
            <h6>📈 Intent (spike vs accumulation)</h6>
            <ul>
                <li><strong>Spike (1 day)</strong> → Call Writing → Bearish</li>
                <li><strong>Consistent 2–3 days ↑</strong> → Accumulation → <span style="color:#82b1ff;">Bullish</span></li>
                <li><strong>Unwinding after buildup</strong> → Strong Bullish</li>
            </ul>
        </div>
        <div class="oe-legend-col">
            <h6>🎯 Combined Action</h6>
            <ul>
                <li><strong>CE unwind + PE buildup</strong> → BUY CE (HIGH)</li>
                <li><strong>CE buildup + PE unwind</strong> → BUY PE (HIGH)</li>
                <li><strong>CE accumulation</strong> → BUY CE (MEDIUM)</li>
                <li><strong>Mismatch</strong> → WAIT</li>
            </ul>
        </div>
    </div>

    {{-- ── Filters ─────────────────────────────────────────────── --}}
    <div class="oe-filter">
        <div class="row align-items-end">
            <div class="col-md-3 mb-3">
                <label>From Date</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-3 mb-3">
                <label>To Date</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-3 mb-3">
                <label>Symbols (optional, multi-select)</label>
                <select id="symbol_filter" class="form-control" multiple size="2"></select>
                <small style="color:var(--text-dim); font-size:9px;">Leave empty = all symbols</small>
            </div>
            <div class="col-md-3 mb-3 text-right">
                <button type="button" id="btn_run" class="btn-engine">
                    ⚡ Run Engine
                </button>
                <button type="button" id="btn_reset" class="btn-reset ml-2">
                    ↺ Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ── Stats ───────────────────────────────────────────────── --}}
    <div class="oe-stats">
        <div class="oe-stat-card cyan">
            <small>Total Rows</small>
            <strong id="st_total" style="color:var(--accent-cyan);">0</strong>
        </div>
        <div class="oe-stat-card green">
            <small>BUY CE</small>
            <strong id="st_buy_ce" style="color:var(--accent-green);">0</strong>
        </div>
        <div class="oe-stat-card red">
            <small>BUY PE</small>
            <strong id="st_buy_pe" style="color:var(--accent-red);">0</strong>
        </div>
        <div class="oe-stat-card amber">
            <small>WAIT</small>
            <strong id="st_wait" style="color:var(--accent-amber);">0</strong>
        </div>
        <div class="oe-stat-card blue">
            <small>HIGH Confidence</small>
            <strong id="st_high_conf" style="color:var(--accent-blue);">0</strong>
        </div>
        <div class="oe-stat-card purple">
            <small>BULLISH ACCUM</small>
            <strong id="st_accum" style="color:var(--accent-purple);">0</strong>
        </div>
        <div class="oe-stat-card green">
            <small>CE vs Classic Match</small>
            <strong id="st_classic_match" style="color:#69f0ae;">0%</strong>
        </div>
    </div>

    {{-- ── Table ───────────────────────────────────────────────── --}}
    <div class="oe-table-wrap">
        <div class="oe-loading" id="oe-loading" style="display:none;">
            <div class="oe-spinner"></div>
            <div class="oe-loading-text">Running OI Engine...</div>
        </div>
        <div class="oe-table-scroll">
            <table class="oe-table">
                <thead>
                    <tr>
                        {{-- Base --}}
                        <th rowspan="2">#</th>
                        <th rowspan="2">Date</th>
                        <th rowspan="2">Symbol</th>

                        {{-- Classic OI --}}
                        <th class="th-sec-ce" style="color:#ffab00;">CE %<br><small style="font-weight:400;opacity:.7;">Today</small></th>
                        <th class="th-sec-ce" style="color:#ffcc80;">CE %<br><small style="font-weight:400;opacity:.7;">Prev</small></th>
                        <th class="th-sec-pe" style="color:#69f0ae;">PE %<br><small style="font-weight:400;opacity:.7;">Today</small></th>
                        <th class="th-sec-pe" style="color:#a5d6a7;">PE %<br><small style="font-weight:400;opacity:.7;">Prev</small></th>
                        <th rowspan="2" style="color:#90a4ae;">Classic<br>Signal</th>

                        {{-- CE Engine --}}
                        <th class="th-sec-ce th-group-ce" style="color:#ffab00;">CE Phase</th>
                        <th class="th-sec-ce th-group-ce" style="color:#ffab00;">CE Speed</th>
                        <th class="th-sec-ce th-group-ce" style="color:#ffab00;">CE Trend</th>
                        <th class="th-sec-ce th-group-ce" style="color:#ffab00;">CE Engine</th>
                        <th class="th-sec-ce th-group-ce" style="color:#ffd740;">CE Intent</th>

                        {{-- PE Engine --}}
                        <th class="th-sec-pe th-group-pe" style="color:#69f0ae;">PE Phase</th>
                        <th class="th-sec-pe th-group-pe" style="color:#69f0ae;">PE Speed</th>
                        <th class="th-sec-pe th-group-pe" style="color:#69f0ae;">PE Trend</th>
                        <th class="th-sec-pe th-group-pe" style="color:#69f0ae;">PE Engine</th>
                        <th class="th-sec-pe th-group-pe" style="color:#a5d6a7;">PE Intent</th>

                        {{-- Combined --}}
                        <th class="th-sec-comb th-group-comb" style="color:#e040fb;">Signal</th>
                        <th class="th-sec-comb th-group-comb" style="color:#e040fb;">Action</th>
                        <th class="th-sec-comb th-group-comb" style="color:#e040fb;">Confidence</th>
                        <th class="th-sec-comb th-group-comb" style="color:#ce93d8; max-width:200px;">Reason</th>

                        {{-- Spot --}}
                        <th rowspan="2" style="color:var(--text-dim);">Spot ₹</th>
                    </tr>
                    {{-- Row 2 header labels hidden — rowspan handles the rest --}}
                    <tr style="display:none;"></tr>
                </thead>
                <tbody id="oe-tbody">
                    <tr>
                        <td colspan="24" class="oe-empty">
                            <i class="fas fa-microchip"></i>
                            Click <strong>⚡ Run Engine</strong> to start analysis
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('script')
<script>
let engineData = [];

/* ── Helpers ────────────────────────────────────────── */
function pctCell(v) {
    const n = parseFloat(v) || 0;
    const cls = n > 0 ? 'pct-pos' : (n < 0 ? 'pct-neg' : 'pct-zero');
    const sign = n > 0 ? '+' : '';
    return `<span class="${cls}">${sign}${n.toFixed(2)}%</span>`;
}

function phaseBadge(p) {
    const map = {
        'AGGRESSIVE_BUILDUP': ['ph-agg-build',  '🔴 AGG.BUILD'],
        'BUILDUP':            ['ph-build',       '🟡 BUILDUP'],
        'SLOW_BUILDUP':       ['ph-slow-build',  '🟠 SLOW BUILD'],
        'STRONG_UNWINDING':   ['ph-strong-unw',  '🟢 STR.UNWIND'],
        'UNWINDING':          ['ph-unw',         '🟢 UNWIND'],
        'NEUTRAL':            ['ph-neutral',     '⚪ NEUTRAL'],
    };
    const [cls, label] = map[p] || ['ph-neutral', p || '—'];
    return `<span class="ph ${cls}">${label}</span>`;
}

function speedBadge(s) {
    const map = {
        'ACCELERATING': ['sp-accel', '⬆ ACCEL'],
        'DECELERATING': ['sp-decel', '⬇ DECEL'],
        'STABLE':       ['sp-stable','→ STABLE'],
    };
    const [cls, label] = map[s] || ['sp-stable', s || '—'];
    return `<span class="sp ${cls}">${label}</span>`;
}

function trendBadge(t) {
    const map = {
        'STRONG_BUILDUP_TREND':   ['tr-strong-build', '📈📈 S.BUILD'],
        'BUILDUP_TREND':          ['tr-build',         '📈 BUILD'],
        'STRONG_UNWINDING_TREND': ['tr-strong-unw',    '📉📉 S.UNWIND'],
        'UNWINDING_TREND':        ['tr-unw',           '📉 UNWIND'],
        'SIDEWAYS':               ['tr-side',          '↔ SIDEWAYS'],
    };
    const [cls, label] = map[t] || ['tr-side', t || '—'];
    return `<span class="tr ${cls}">${label}</span>`;
}

function engineSignalBadge(s) {
    const map = {
        'BEARISH_STRONG':     ['sb sb-bear-strong', '🔴🔴 BEAR STRONG'],
        'BEARISH':            ['sb sb-bear',         '🔴 BEARISH'],
        'BEARISH_WEAK':       ['sb sb-bear-weak',    '🟠 BEAR WEAK'],
        'BULLISH_STRONG':     ['sb sb-bull-strong',  '🟢🟢 BULL STRONG'],
        'BULLISH':            ['sb sb-bull',          '🟢 BULLISH'],
        'BULLISH_ACCUMULATION':['sb sb-bull-accum',  '🔵 ACCUM'],
        'WAIT':               ['sb sb-wait',          '⏸ WAIT'],
    };
    const [cls, label] = map[s] || ['sb sb-neutral', s || '—'];
    return `<span class="${cls}">${label}</span>`;
}

function classicBadge(s) {
    if (s === 'BULLISH') return '<span class="cl-bull">🟢 BULL</span>';
    if (s === 'BEARISH') return '<span class="cl-bear">🔴 BEAR</span>';
    return '<span class="cl-neut">⚪ NEUT</span>';
}

function combSignalBadge(s) {
    if (s === 'BULLISH') return '<span class="sb sb-bull">🟢 BULLISH</span>';
    if (s === 'BEARISH') return '<span class="sb sb-bear">🔴 BEARISH</span>';
    return '<span class="sb sb-neutral">⚪ NEUTRAL</span>';
}

function actionBadge(a) {
    if (a === 'BUY CE') return '<span class="act-buy-ce">📈 BUY CE</span>';
    if (a === 'BUY PE') return '<span class="act-buy-pe">📉 BUY PE</span>';
    return '<span class="act-wait">⏸ WAIT</span>';
}

function confBadge(c) {
    const map = {
        'HIGH':   'conf-high',
        'MEDIUM': 'conf-medium',
        'LOW':    'conf-low',
        'NONE':   'conf-none',
    };
    const icons = { 'HIGH':'🔥', 'MEDIUM':'⚡', 'LOW':'💧', 'NONE':'—' };
    return `<span class="${map[c] || 'conf-none'}">${icons[c] || ''}${c || '—'}</span>`;
}

/* ── Loading ─────────────────────────────────────────── */
function loading(show) {
    $('#oe-loading').toggle(show);
}

/* ── Init ────────────────────────────────────────────── */
$(function() {
    loadSymbols();
    setTimeout(runEngine, 400);
});

function loadSymbols() {
    $.get('{{ route("oi-engine.symbols") }}', function(res) {
        if (!res.success) return;
        let opts = '';
        res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
        $('#symbol_filter').html(opts);
    });
}

/* ── Run ─────────────────────────────────────────────── */
function runEngine() {
    const from    = $('#from_date').val();
    const to      = $('#to_date').val();
    const symbols = $('#symbol_filter').val() || [];

    if (!from || !to) { alert('Please select both dates'); return; }

    loading(true);
    engineData = [];
    $('#oe-tbody').html('<tr><td colspan="24" class="oe-empty"><i class="fas fa-microchip"></i>Loading...</td></tr>');
    resetStats();

    $.ajax({
        url:  '{{ route("oi-engine.analyze") }}',
        type: 'GET',
        data: { from_date: from, to_date: to, symbols },
        success: function(res) {
            loading(false);
            if (res.success && res.data && res.data.length > 0) {
                engineData = res.data;
                renderTable();
                updateStats();
            } else {
                $('#oe-tbody').html(`<tr><td colspan="24" class="oe-empty"><i class="fas fa-info-circle"></i>${res.message || 'No data found'}</td></tr>`);
            }
        },
        error: function() {
            loading(false);
            $('#oe-tbody').html('<tr><td colspan="24" class="oe-empty"><i class="fas fa-exclamation-triangle"></i>Error loading data</td></tr>');
        }
    });
}

/* ── Render ──────────────────────────────────────────── */
function renderTable() {
    let html = '';
    engineData.forEach(function(row, i) {
        const mismatch = row.combined_signal !== row.classic_signal && row.combined_signal !== 'NEUTRAL';
        const mismatchDot = mismatch ? '<span class="mismatch-dot" title="Classic vs Engine mismatch"></span>' : '';

        html += `<tr>
            <td style="color:var(--text-dim);">${i+1}</td>
            <td style="color:var(--accent-cyan); font-weight:700;">${row.date}</td>
            <td style="color:#e8eaf6; font-weight:700;">${row.symbol}</td>

            <td>${pctCell(row.ce_oi_pct)}</td>
            <td>${pctCell(row.ce_pct_prev1)}</td>
            <td>${pctCell(row.pe_oi_pct)}</td>
            <td>${pctCell(row.pe_pct_prev1)}</td>
            <td>${classicBadge(row.classic_signal)}${mismatchDot}</td>

            <td>${phaseBadge(row.ce_phase)}</td>
            <td>${speedBadge(row.ce_speed)}</td>
            <td>${trendBadge(row.ce_trend)}</td>
            <td>${engineSignalBadge(row.ce_engine)}</td>
            <td>${engineSignalBadge(row.ce_intent)}</td>

            <td>${phaseBadge(row.pe_phase)}</td>
            <td>${speedBadge(row.pe_speed)}</td>
            <td>${trendBadge(row.pe_trend)}</td>
            <td>${engineSignalBadge(row.pe_engine)}</td>
            <td>${engineSignalBadge(row.pe_intent)}</td>

            <td>${combSignalBadge(row.combined_signal)}</td>
            <td>${actionBadge(row.combined_action)}</td>
            <td>${confBadge(row.combined_confidence)}</td>
            <td style="text-align:left; max-width:180px; color:var(--text-dim); font-size:10px; white-space:normal;">${row.combined_reason || '—'}</td>

            <td style="color:var(--text-primary);">₹${Number(row.spot_price).toLocaleString('en-IN')}</td>
        </tr>`;
    });
    $('#oe-tbody').html(html);
}

/* ── Stats ───────────────────────────────────────────── */
function updateStats() {
    const total   = engineData.length;
    const buyCE   = engineData.filter(r => r.combined_action === 'BUY CE').length;
    const buyPE   = engineData.filter(r => r.combined_action === 'BUY PE').length;
    const wait    = engineData.filter(r => r.combined_action === 'WAIT').length;
    const highC   = engineData.filter(r => r.combined_confidence === 'HIGH').length;
    const accum   = engineData.filter(r => r.ce_intent === 'BULLISH_ACCUMULATION' || r.pe_intent === 'BULLISH_ACCUMULATION').length;
    const matchC  = engineData.filter(r => r.combined_signal === r.classic_signal).length;
    const matchPct= total > 0 ? Math.round((matchC / total) * 100) : 0;

    $('#st_total').text(total);
    $('#st_buy_ce').text(buyCE);
    $('#st_buy_pe').text(buyPE);
    $('#st_wait').text(wait);
    $('#st_high_conf').text(highC);
    $('#st_accum').text(accum);
    $('#st_classic_match').text(matchPct + '%');
}

function resetStats() {
    $('#st_total,#st_buy_ce,#st_buy_pe,#st_wait,#st_high_conf,#st_accum').text('0');
    $('#st_classic_match').text('0%');
}

/* ── Events ──────────────────────────────────────────── */
$('#btn_run').click(runEngine);
$('#btn_reset').click(function() {
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter').val('');
    engineData = [];
    resetStats();
    $('#oe-tbody').html('<tr><td colspan="24" class="oe-empty"><i class="fas fa-microchip"></i>Click <strong>⚡ Run Engine</strong> to start analysis</td></tr>');
    setTimeout(runEngine, 200);
});
</script>
@endpush