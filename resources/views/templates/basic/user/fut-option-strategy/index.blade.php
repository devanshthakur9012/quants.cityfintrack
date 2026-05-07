@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<style>
/* ══════════════════════════════════════════════════════════
   FUT + OPTION SELL STRATEGY — BLADE STYLES
   ══════════════════════════════════════════════════════════ */

/* ── Base ─────────────────────────────────────────────── */
.custom--table thead th,
.custom--table tbody td {
    text-align: center !important;
    padding: 6px 5px !important;
    font-size: 10px !important;
    vertical-align: middle;
}
/* First 3 cols left-align */
.custom--table thead th:nth-child(-n+3),
.custom--table tbody td:nth-child(-n+3) { text-align: left !important; }

/* Sticky first 3 cols */
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.custom--table { min-width: 2200px; }

.custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { position:sticky;left:0;z-index:10;background:inherit; }
.custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { position:sticky;left:36px;z-index:10;background:inherit; }
.custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position:sticky;left:100px;z-index:10;background:inherit; }

.custom--table thead th { background:#0d1930 !important; color:#94a3b8 !important; border-bottom:2px solid #1e2d4a !important; }

/* ── Loading ──────────────────────────────────────────── */
.loading-overlay {
    position:absolute;top:0;left:0;right:0;bottom:0;
    background:rgba(10,18,38,.97);
    display:flex;flex-direction:column;justify-content:center;align-items:center;
    z-index:1000;border-radius:14px;
}
.spinner { width:44px;height:44px;border:4px solid rgba(255,255,255,.1);border-top:4px solid #38bdf8;border-radius:50%;animation:spin 1s linear infinite; }
.loading-text { color:#38bdf8;margin-top:14px;font-size:13px;font-weight:600; }
@keyframes spin { to { transform:rotate(360deg); } }

/* ── Page header ─────────────────────────────────────── */
.page-header {
    background:linear-gradient(135deg,#0a1226,#0f2040 55%,#172b55);
    color:#fff;padding:18px 22px;border-radius:14px;margin-bottom:18px;
    box-shadow:0 6px 28px rgba(0,0,0,.4);border:1px solid rgba(56,189,248,.15);
}
.page-header h4 { font-size:18px;font-weight:700;margin:0 0 4px;color:#38bdf8; }
.page-header p  { margin:0;font-size:11px;color:rgba(255,255,255,.5); }

/* ── Filter section ──────────────────────────────────── */
.filter-section {
    background:linear-gradient(135deg,#0a1226,#0f2040);
    border:1px solid rgba(56,189,248,.18);padding:16px 18px;
    border-radius:12px;margin-bottom:16px;
}
.filter-section label { color:rgba(255,255,255,.7)!important;font-weight:600;font-size:11.5px;margin-bottom:4px;display:block; }
.filter-section .form-control { background:rgba(255,255,255,.07);border:1px solid rgba(56,189,248,.22);color:#fff;font-size:12px;border-radius:7px; }
.filter-section .form-control option { background:#0f2040;color:#fff; }
.filter-section .form-control:focus { border-color:#38bdf8;box-shadow:0 0 0 2px rgba(56,189,248,.15); }

/* ── Stat cards ──────────────────────────────────────── */
.stat-card { background:#fff;border-radius:10px;padding:10px 12px;text-align:center;border-left:4px solid #38bdf8;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:12px;transition:transform .2s; }
.stat-card:hover { transform:translateY(-2px); }
.stat-card small  { display:block;color:#888;font-size:9px;text-transform:uppercase;letter-spacing:.4px; }
.stat-card strong { display:block;font-size:1.05rem;font-weight:700;margin-top:3px; }
.sc-green  { border-left-color:#10b981; }
.sc-red    { border-left-color:#ef4444; }
.sc-amber  { border-left-color:#f59e0b; }
.sc-purple { border-left-color:#8b5cf6; }
.sc-teal   { border-left-color:#14b8a6; }
.sc-blue   { border-left-color:#38bdf8; }
.sc-orange { border-left-color:#f97316; }

/* ── Dark summary cards ──────────────────────────────── */
.summary-section { background:linear-gradient(135deg,#0a1226,#0f2040);border:1px solid rgba(56,189,248,.18);border-radius:12px;padding:14px;margin-bottom:16px; }
.summary-section h6 { color:#38bdf8;font-size:12px;font-weight:700;margin-bottom:12px; }
.sdark { background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-left:3px solid #38bdf8;border-radius:8px;padding:9px;text-align:center;margin-bottom:8px; }
.sdark small  { display:block;color:rgba(255,255,255,.4);font-size:8.5px;text-transform:uppercase;letter-spacing:.4px; }
.sdark strong { display:block;font-size:.95rem;font-weight:700;color:#fff;margin-top:3px; }

/* ── Info box ────────────────────────────────────────── */
.info-box { background:linear-gradient(135deg,#0a1226,#0f2040);border:1px solid rgba(56,189,248,.15);border-radius:12px;padding:14px 16px;margin-bottom:16px;font-size:11px;color:rgba(255,255,255,.6); }
.info-box h6 { color:#38bdf8;font-size:12px;font-weight:700;margin-bottom:8px; }
.info-box ul { margin:0;padding-left:14px; }
.info-box li { margin-bottom:4px;line-height:1.6; }
.info-box strong { color:#fff; }

/* ── Badges ──────────────────────────────────────────── */
.b { display:inline-block;padding:2px 7px;border-radius:4px;font-weight:700;font-size:8.5px;white-space:nowrap; }
.b-bull    { background:linear-gradient(135deg,#10b981,#059669);color:#fff; }
.b-bear    { background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff; }
.b-neut    { background:#e5e7eb;color:#6b7280; }
.b-buyfut  { background:linear-gradient(135deg,#38bdf8,#0284c7);color:#fff; }
.b-sellfut { background:linear-gradient(135deg,#f97316,#ea580c);color:#fff; }
.b-wait    { background:#334155;color:#94a3b8; }
.b-sellce  { background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff; }
.b-sellpe  { background:linear-gradient(135deg,#10b981,#047857);color:#fff; }

.cond { display:inline-block;padding:1px 5px;border-radius:3px;font-weight:700;font-size:8px; }
.cond-ceup-pedown { background:linear-gradient(135deg,#ef4444,#f97316);color:#fff; }
.cond-cedn-peup   { background:linear-gradient(135deg,#10b981,#06b6d4);color:#fff; }
.cond-both-up     { background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff; }
.cond-both-dn     { background:#6b7280;color:#fff; }
.cond-flat        { background:#e5e7eb;color:#9ca3af; }

/* ── P/L ─────────────────────────────────────────────── */
.pl-pos { color:#10b981;font-weight:700; }
.pl-neg { color:#ef4444;font-weight:700; }
.pl-na  { color:#9ca3af;font-size:9px; }

/* ── Date/time pill ──────────────────────────────────── */
.dt-pill { display:inline-block;border-radius:5px;padding:2px 6px;font-size:9px;font-weight:700;line-height:1.5; }
.dt-entry { background:rgba(56,189,248,.12);color:#38bdf8;border:1px solid rgba(56,189,248,.25); }
.dt-exit  { background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.25); }

/* ── Column group tints ──────────────────────────────── */
.th-oi   { background:rgba(139,92,246,.09)!important;color:#c4b5fd!important; }
.th-fut  { background:rgba(56,189,248,.1)!important;  color:#7dd3fc!important; }
.th-opt  { background:rgba(16,185,129,.1)!important;  color:#6ee7b7!important; }
.th-comb { background:rgba(249,115,22,.1)!important;  color:#fdba74!important; }
td.th-oi   { background:rgba(139,92,246,.03)!important; }
td.th-fut  { background:rgba(56,189,248,.03)!important; }
td.th-opt  { background:rgba(16,185,129,.03)!important; }
td.th-comb { background:rgba(249,115,22,.04)!important; }

/* ── Group header row ────────────────────────────────── */
.thead-group th { font-size:9.5px!important;padding:4px 5px!important;letter-spacing:.3px;text-transform:uppercase; }

/* ── Lot badge ───────────────────────────────────────── */
.lot-badge { display:inline-block;background:rgba(255,255,255,.15);color:#e2e8f0;border-radius:3px;padding:0 4px;font-size:7.5px;font-weight:700;margin-left:2px;vertical-align:middle; }

/* ── Buttons ─────────────────────────────────────────── */
.btn-run   { background:linear-gradient(135deg,#38bdf8,#0284c7);color:#fff;font-weight:700;border:none;padding:9px 26px;border-radius:8px;font-size:13px; }
.btn-run:hover   { opacity:.88;color:#fff; }
.btn-reset { background:transparent;border:1px solid rgba(56,189,248,.4);color:#38bdf8;font-weight:600;padding:9px 22px;border-radius:8px;font-size:13px; }
.btn-reset:hover { background:rgba(56,189,248,.08);color:#38bdf8; }

/* ── Price cells ─────────────────────────────────────── */
.price-val { font-weight:700;font-size:10px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

{{-- ══ PAGE HEADER ════════════════════════════════════════════ --}}
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <div>
            <h4>⚡ FUT + Option Sell Strategy</h4>
            <p>
                OI Signal @ <strong>14:45</strong> &nbsp;→&nbsp;
                Position: <strong>1 lot FUT + 2 lots ATM Option SELL</strong>
                &nbsp;|&nbsp; Position Time: <strong>14:45 (Signal Day)</strong>
                &nbsp;|&nbsp; Exit Window: <strong>Next Trading Day 09:15 → 10:30</strong>
            </p>
        </div>
        <div class="d-flex" style="gap:8px;flex-wrap:wrap;">
            <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-sm"
               style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);">
               PE/CE Analysis
            </a>
            <a href="{{ route('oiiv-auto.index') }}" class="btn btn-sm"
               style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);">
               OI+IV
            </a>
        </div>
    </div>
</div>

{{-- ══ INFO BOX ════════════════════════════════════════════════ --}}
<div class="info-box">
    <h6><i class="fas fa-info-circle"></i> How This Strategy Works</h6>
    <div class="row">
        <div class="col-md-4">
            <ul>
                <li><strong>BULLISH Signal</strong> → BUY 1 lot FUT + SELL 2 lots CE (ATM)</li>
                <li><strong>BEARISH Signal</strong> → SELL 1 lot FUT + SELL 2 lots PE (ATM)</li>
                <li><strong>NEUTRAL</strong> → WAIT (no trade)</li>
            </ul>
        </div>
        <div class="col-md-4">
            <ul>
                <li><strong>Position Date</strong>: Signal day (the date you select)</li>
                <li><strong>Position Time</strong>: 14:45 candle close</li>
                <li><strong>Exit Date</strong>: NEXT trading day only</li>
                <li><strong>Exit Window</strong>: 09:15 → 10:30 (next day)</li>
            </ul>
        </div>
        <div class="col-md-4">
            <ul>
                <li><strong>FUT BUY exit</strong>: MAX HIGH candle in window</li>
                <li><strong>FUT SELL exit</strong>: MIN LOW candle in window</li>
                <li><strong>OPT SELL exit</strong>: MIN LOW candle (max decay benefit)</li>
                <li><strong>Option P/L</strong>: (Sold Premium − Bought Back) × lot × 2</li>
            </ul>
        </div>
    </div>
</div>

{{-- ══ FILTERS ═════════════════════════════════════════════════ --}}
<div class="filter-section">
    <div class="row mb-3">
        <div class="col-md-3">
            <label><i class="fas fa-calendar-alt"></i> From Date <small style="opacity:.5;">(Signal Day)</small></label>
            <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
        </div>
        <div class="col-md-3">
            <label><i class="fas fa-calendar-alt"></i> To Date <small style="opacity:.5;">(Signal Day)</small></label>
            <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
        </div>
        <div class="col-md-3">
            <label><i class="fas fa-filter"></i> Symbols <small style="opacity:.5;">(optional)</small></label>
            <select id="symbol_filter" class="form-control" multiple size="2"></select>
        </div>
        <div class="col-md-3">
            <label><i class="fas fa-bullseye"></i> Filter Signal</label>
            <select id="action_filter" class="form-control">
                <option value="">All Signals</option>
                <option value="BUY FUT">BUY FUT (Bullish)</option>
                <option value="SELL FUT">SELL FUT (Bearish)</option>
                <option value="WAIT">WAIT only</option>
            </select>
        </div>
    </div>
    <div class="text-center">
        <button type="button" id="run_analysis" class="btn btn-run">
            <i class="fas fa-search"></i> Analyse
        </button>
        <button type="button" id="reset_btn" class="btn btn-reset ml-2">
            <i class="fas fa-undo"></i> Reset
        </button>
    </div>
</div>

{{-- ══ STAT CARDS ══════════════════════════════════════════════ --}}
<div class="row">
    <div class="col-6 col-md-2"><div class="stat-card sc-blue">   <small>Total Signals</small>    <strong id="st_total">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="stat-card sc-green">  <small>BUY FUT (Bullish)</small> <strong id="st_buy"   style="color:#10b981;">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="stat-card sc-red">    <small>SELL FUT (Bearish)</small><strong id="st_sell"  style="color:#ef4444;">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="stat-card sc-amber">  <small>WAIT</small>             <strong id="st_wait"  style="color:#f59e0b;">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="stat-card sc-teal">   <small>FUT Total P/L</small>    <strong id="st_fut_pl">₹0</strong></div></div>
    <div class="col-6 col-md-2"><div class="stat-card sc-purple"> <small>Option Total P/L</small> <strong id="st_opt_pl">₹0</strong></div></div>
</div>

{{-- ══ SUMMARY ═════════════════════════════════════════════════ --}}
<div class="summary-section">
    <h6>📊 Performance Summary</h6>
    <div class="row">
        <div class="col-6 col-md-2"><div class="sdark" style="border-left-color:#f97316;"><small>Combined P/L</small><strong id="st_comb_pl">₹0</strong></div></div>
        <div class="col-6 col-md-2"><div class="sdark" style="border-left-color:#10b981;"><small>FUT Win Rate</small><strong id="st_fut_win">0%</strong></div></div>
        <div class="col-6 col-md-2"><div class="sdark" style="border-left-color:#6ee7b7;"><small>Option Win Rate</small><strong id="st_opt_win">0%</strong></div></div>
        <div class="col-6 col-md-2"><div class="sdark" style="border-left-color:#38bdf8;"><small>Avg FUT P/L</small><strong id="st_avg_fut">₹0</strong></div></div>
        <div class="col-6 col-md-2"><div class="sdark" style="border-left-color:#c4b5fd;"><small>Avg Option P/L</small><strong id="st_avg_opt">₹0</strong></div></div>
        <div class="col-6 col-md-2"><div class="sdark" style="border-left-color:#f59e0b;"><small>Avg Combined</small><strong id="st_avg_comb">₹0</strong></div></div>
    </div>
</div>

{{-- ══ TABLE ════════════════════════════════════════════════════ --}}
<div style="position:relative;min-height:400px;">

    <div class="loading-overlay" id="loading-overlay" style="display:none;">
        <div class="spinner"></div>
        <div class="loading-text">Analysing signals…</div>
    </div>

    <div class="table-responsive">
        <table class="table custom--table">
            <thead>

                {{-- Group header row --}}
                <tr class="thead-group">
                    <th colspan="3"></th>

                    {{-- OI block --}}
                    <th colspan="3" class="th-oi" style="border-right:2px solid rgba(139,92,246,.3);">
                        OI ANALYSIS
                    </th>

                    {{-- Signal block --}}
                    <th colspan="2">SIGNAL</th>

                    {{-- FUT block --}}
                    <th colspan="6" class="th-fut"
                        style="border-left:2px solid rgba(56,189,248,.3);border-right:2px solid rgba(56,189,248,.3);">
                        FUT TRADE &nbsp;<span class="lot-badge">1 LOT</span>
                    </th>

                    {{-- Option block --}}
                    <th colspan="8" class="th-opt"
                        style="border-right:2px solid rgba(16,185,129,.3);">
                        OPTION SELL &nbsp;<span class="lot-badge">2 LOTS</span>
                    </th>

                    {{-- Combined --}}
                    <th colspan="1" class="th-comb">COMBINED</th>
                </tr>

                {{-- Column header row --}}
                <tr>
                    <th>#</th>
                    <th>Signal Date</th>
                    <th>Symbol</th>

                    {{-- OI --}}
                    <th class="th-oi">CE OI %</th>
                    <th class="th-oi">PE OI %</th>
                    <th class="th-oi" style="border-right:2px solid rgba(139,92,246,.3);">OI Signal</th>

                    {{-- Signal --}}
                    <th>FUT Action</th>
                    <th>Opt Action</th>

                    {{-- FUT Trade --}}
                    <th class="th-fut" style="border-left:2px solid rgba(56,189,248,.3);">
                        Position Date<br><small style="opacity:.6;font-weight:400;">(Signal Day)</small>
                    </th>
                    <th class="th-fut">
                        Position Time<br><small style="opacity:.6;font-weight:400;">14:45</small>
                    </th>
                    <th class="th-fut">
                        Position Price ₹<br><small style="opacity:.6;font-weight:400;">FUT @ 14:45</small>
                    </th>
                    <th class="th-fut">
                        Exit Date<br><small style="opacity:.6;font-weight:400;">Next Day</small>
                    </th>
                    <th class="th-fut">
                        Exit Time<br><small style="opacity:.6;font-weight:400;">09:15-10:30</small>
                    </th>
                    <th class="th-fut" style="border-right:2px solid rgba(56,189,248,.3);">
                        Exit Price ₹ &amp; P/L<br><small style="opacity:.6;font-weight:400;">1 lot</small>
                    </th>

                    {{-- Option Trade --}}
                    <th class="th-opt">
                        Option Symbol<br><small style="opacity:.6;font-weight:400;">ATM strike</small>
                    </th>
                    <th class="th-opt">
                        Position Date<br><small style="opacity:.6;font-weight:400;">(Signal Day)</small>
                    </th>
                    <th class="th-opt">
                        Position Time<br><small style="opacity:.6;font-weight:400;">14:45</small>
                    </th>
                    <th class="th-opt">
                        Position Price ₹<br><small style="opacity:.6;font-weight:400;">Premium Sold</small>
                    </th>
                    <th class="th-opt">
                        Exit Date<br><small style="opacity:.6;font-weight:400;">Next Day</small>
                    </th>
                    <th class="th-opt">
                        Exit Time<br><small style="opacity:.6;font-weight:400;">09:15-10:30</small>
                    </th>
                    <th class="th-opt">
                        Exit Price ₹<br><small style="opacity:.6;font-weight:400;">Bought Back At</small>
                    </th>
                    <th class="th-opt" style="border-right:2px solid rgba(16,185,129,.3);">
                        Option P/L ₹<br><small style="opacity:.6;font-weight:400;">2 lots</small>
                    </th>

                    {{-- Combined --}}
                    <th class="th-comb">
                        Combined P/L ₹<br><small style="opacity:.6;font-weight:400;">FUT + Opt</small>
                    </th>
                </tr>
            </thead>
            <tbody id="analysis-tbody">
                <tr>
                    <td colspan="22" class="text-center py-5">
                        <i class="fas fa-chart-area" style="font-size:3rem;color:#38bdf8;opacity:.3;"></i>
                        <p style="font-size:1rem;margin-top:14px;color:#888;">
                            Click <strong>"Analyse"</strong> to load signals
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

</div>
</section>
@endsection

@push('script')
<script>
/* ══════════════════════════════════════════════════════════════
   FUT + OPTION SELL STRATEGY — JAVASCRIPT
   ══════════════════════════════════════════════════════════════ */

let analysisData = [];

/* ── Helpers ──────────────────────────────────────────────── */
const toggle = show => $('#loading-overlay').toggle(show);

function fmt(v, decimals = 2) {
    return Number(v).toLocaleString('en-IN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function inr(v) {
    const n   = parseFloat(v) || 0;
    const abs = Math.abs(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (n >= 0 ? '+' : '−') + '₹' + abs;
}

/** P/L cell — big number + small % below */
function plCell(pl, pct) {
    const n = parseFloat(pl) || 0;
    const p = parseFloat(pct) || 0;
    const c = n >= 0 ? 'pl-pos' : 'pl-neg';
    return `<span class="${c}">${inr(n)}</span><br>`
         + `<small class="${c}">${p >= 0 ? '+' : ''}${Math.abs(p).toFixed(2)}%</small>`;
}

/** Price display */
function priceCell(v) {
    if (v === null || v === undefined || v === '') return '<span class="pl-na">—</span>';
    return `<strong class="price-val">₹${fmt(v)}</strong>`;
}

/** OI % colored */
function oiPct(v) {
    const n = parseFloat(v) || 0;
    const c = n > 0 ? 'pl-pos' : n < 0 ? 'pl-neg' : '';
    return `<span class="${c}">${n > 0 ? '+' : ''}${n.toFixed(2)}%</span>`;
}

/** Date pill */
function datePill(date, isExit) {
    if (!date) return '<span class="pl-na">—</span>';
    const cls = isExit ? 'dt-exit' : 'dt-entry';
    return `<span class="dt-pill ${cls}" style="font-size:9px;">${date}</span>`;
}

/** Time pill */
function timePill(time, isExit) {
    if (!time) return '<span class="pl-na">—</span>';
    const cls = isExit ? 'dt-exit' : 'dt-entry';
    return `<span class="dt-pill ${cls}"><strong>${time}</strong></span>`;
}

/** OI Signal badge */
function signalBadge(s) {
    if (s === 'BULLISH') return '<span class="b b-bull">📈 BULLISH</span>';
    if (s === 'BEARISH') return '<span class="b b-bear">📉 BEARISH</span>';
    return '<span class="b b-neut">⏸ NEUTRAL</span>';
}

/** OI condition badge */
function condBadge(c) {
    if (!c) return '<span class="cond cond-flat">—</span>';
    if (c.includes('CE ↑ + PE ↓')) return `<span class="cond cond-ceup-pedown">${c}</span>`;
    if (c.includes('CE ↓ + PE ↑')) return `<span class="cond cond-cedn-peup">${c}</span>`;
    if (c.includes('Both ↑'))       return `<span class="cond cond-both-up">${c}</span>`;
    if (c.includes('Both ↓'))       return `<span class="cond cond-both-dn">${c}</span>`;
    return `<span class="cond cond-flat">${c}</span>`;
}

/** FUT action badge */
function futBadge(a) {
    if (a === 'BUY FUT')  return '<span class="b b-buyfut">📈 BUY FUT</span><span class="lot-badge">1L</span>';
    if (a === 'SELL FUT') return '<span class="b b-sellfut">📉 SELL FUT</span><span class="lot-badge">1L</span>';
    return '<span class="b b-wait">⏸ WAIT</span>';
}

/** Option action badge */
function optBadge(a) {
    if (!a) return '<span class="pl-na">—</span>';
    if (a === 'SELL CE') return '<span class="b b-sellce">🔴 SELL CE</span><span class="lot-badge">2L</span>';
    if (a === 'SELL PE') return '<span class="b b-sellpe">✅ SELL PE</span><span class="lot-badge">2L</span>';
    return `<span class="b b-neut">${a}</span>`;
}

/* ── Render Table ─────────────────────────────────────────── */
function renderTable() {
    if (!analysisData.length) { noData('No signals found for this range'); return; }

    let html = '';

    analysisData.forEach((r, i) => {
        const active = r.trade_action !== 'WAIT';

        // ── Exit price + P/L cell for FUT (combined in one cell to save columns)
        let futExitCell = '<span class="pl-na">—</span>';
        if (active && r.fut_exit_price !== null) {
            futExitCell = priceCell(r.fut_exit_price)
                + '<br>'
                + plCell(r.fut_pl, r.fut_pl_pct);
        } else if (active) {
            futExitCell = '<span class="pl-na">No data in window</span>';
        }

        // ── Option symbol cell
        let optSymHtml = '<span class="pl-na">—</span>';
        if (r.opt_error === 'NO_ATM') {
            optSymHtml = '<span class="pl-na" style="color:#f97316;">⚠ No ATM found</span>';
        } else if (r.opt_error === 'ZERO_PREMIUM') {
            optSymHtml = `<strong style="font-size:9px;color:#f97316;">${r.opt_symbol ?? '—'}</strong>`
                       + '<br><small style="color:#f97316;">Zero premium</small>';
        } else if (r.opt_symbol) {
            optSymHtml = `<strong style="font-size:9px;color:#059669;">${r.opt_symbol}</strong>`
                       + `<br><small style="color:#aaa;font-size:8px;">K: ${r.opt_strike ?? '—'}</small>`;
        }

        // ── Option P/L cell
        let optPlHtml = '<span class="pl-na">—</span>';
        if (r.opt_pl !== 0 && !r.opt_error) {
            optPlHtml = plCell(r.opt_pl, r.opt_pl_pct);
        }

        // ── Combined P/L cell
        const combCls = (r.combined_pl || 0) >= 0 ? 'pl-pos' : 'pl-neg';
        const combHtml = active
            ? `<strong class="${combCls}">${inr(r.combined_pl)}</strong>`
            : '<span class="pl-na">—</span>';

        html += `
        <tr>
            {{-- # / Date / Symbol --}}
            <td><strong style="color:#64748b;">${i + 1}</strong></td>
            <td>
                <strong style="font-size:10px;">${r.signal_date}</strong>
                <br><small style="color:#64748b;font-size:8px;">Exit: ${r.exit_date_exp}</small>
            </td>
            <td><strong style="color:#0284c7;">${r.symbol}</strong></td>

            {{-- OI --}}
            <td class="th-oi">${oiPct(r.ce_oi_pct)}</td>
            <td class="th-oi">${oiPct(r.pe_oi_pct)}</td>
            <td class="th-oi" style="border-right:2px solid rgba(139,92,246,.15);">
                ${signalBadge(r.oi_signal)}<br>${condBadge(r.oi_condition)}
            </td>

            {{-- Signal / Action --}}
            <td>${futBadge(r.trade_action)}</td>
            <td>${optBadge(r.option_action)}</td>

            {{-- ── FUT BLOCK ─────────────────────────────── --}}
            <td class="th-fut" style="border-left:2px solid rgba(56,189,248,.15);">
                ${active ? datePill(r.fut_position_date, false) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-fut">
                ${active ? timePill(r.fut_position_time, false) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-fut">
                ${active ? priceCell(r.fut_position_price) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-fut">
                ${active ? datePill(r.fut_exit_date, true) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-fut">
                ${active ? timePill(r.fut_exit_time, true) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-fut" style="border-right:2px solid rgba(56,189,248,.15);">
                ${futExitCell}
            </td>

            {{-- ── OPTION BLOCK ───────────────────────────── --}}
            <td class="th-opt">${optSymHtml}</td>
            <td class="th-opt">
                ${(active && !r.opt_error) ? datePill(r.opt_position_date, false) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-opt">
                ${(active && !r.opt_error) ? timePill(r.opt_position_time, false) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-opt">
                ${r.opt_position_price ? priceCell(r.opt_position_price) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-opt">
                ${r.opt_exit_date ? datePill(r.opt_exit_date, true) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-opt">
                ${r.opt_exit_time ? timePill(r.opt_exit_time, true) : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-opt">
                ${(r.opt_exit_price !== null && r.opt_exit_price !== undefined)
                    ? priceCell(r.opt_exit_price)
                    : '<span class="pl-na">—</span>'}
            </td>
            <td class="th-opt" style="border-right:2px solid rgba(16,185,129,.15);">
                ${optPlHtml}
            </td>

            {{-- ── COMBINED ────────────────────────────────── --}}
            <td class="th-comb">${combHtml}</td>
        </tr>`;
    });

    $('#analysis-tbody').html(html);
    updateStats();
}

/* ── Stats ────────────────────────────────────────────────── */
function updateStats() {
    const active  = analysisData.filter(r => r.trade_action !== 'WAIT');
    const buyFut  = analysisData.filter(r => r.trade_action === 'BUY FUT').length;
    const sellFut = analysisData.filter(r => r.trade_action === 'SELL FUT').length;
    const wait    = analysisData.filter(r => r.trade_action === 'WAIT').length;

    $('#st_total').text(analysisData.length);
    $('#st_buy').text(buyFut);
    $('#st_sell').text(sellFut);
    $('#st_wait').text(wait);

    const futPL  = active.reduce((s, r) => s + (parseFloat(r.fut_pl)  || 0), 0);
    const optPL  = active.reduce((s, r) => s + (parseFloat(r.opt_pl)  || 0), 0);
    const combPL = active.reduce((s, r) => s + (parseFloat(r.combined_pl) || 0), 0);

    const futWins = active.filter(r => (parseFloat(r.fut_pl)  || 0) > 0).length;
    const optRows = active.filter(r => r.opt_pl != 0 && !r.opt_error);
    const optWins = optRows.filter(r => (parseFloat(r.opt_pl) || 0) > 0).length;

    const ph = v => `<span class="${v >= 0 ? 'pl-pos' : 'pl-neg'}">${inr(v)}</span>`;
    const wh = (w, d) => {
        const p = d > 0 ? (w / d * 100) : 0;
        return `<span class="${p >= 50 ? 'pl-pos' : 'pl-neg'}">${p.toFixed(1)}%</span>`;
    };

    $('#st_fut_pl').html(ph(futPL));
    $('#st_opt_pl').html(ph(optPL));
    $('#st_comb_pl').html(ph(combPL));
    $('#st_fut_win').html(wh(futWins, active.length));
    $('#st_opt_win').html(wh(optWins, optRows.length));
    $('#st_avg_fut').html(ph(active.length    ? futPL  / active.length    : 0));
    $('#st_avg_opt').html(ph(optRows.length   ? optPL  / optRows.length   : 0));
    $('#st_avg_comb').html(ph(active.length   ? combPL / active.length    : 0));
}

function resetStats() {
    ['#st_total','#st_buy','#st_sell','#st_wait'].forEach(id => $(id).text('0'));
    ['#st_fut_pl','#st_opt_pl','#st_comb_pl','#st_avg_fut','#st_avg_opt','#st_avg_comb'].forEach(id => $(id).html('₹0'));
    ['#st_fut_win','#st_opt_win'].forEach(id => $(id).html('0%'));
}

function noData(msg) {
    $('#analysis-tbody').html(`
        <tr><td colspan="22" class="text-center py-5">
            <i class="fas fa-info-circle" style="color:#38bdf8;font-size:2.5rem;opacity:.4;"></i>
            <p style="color:#888;margin-top:14px;">${msg}</p>
        </td></tr>`);
}

/* ── Fetch ────────────────────────────────────────────────── */
function runAnalysis() {
    const from   = $('#from_date').val();
    const to     = $('#to_date').val();
    const syms   = $('#symbol_filter').val() || [];
    const action = $('#action_filter').val();

    if (!from || !to) { alert('Please select both dates.'); return; }

    toggle(true);
    analysisData = [];
    resetStats();

    $.ajax({
        url  : '{{ route("fut-option-strategy.analyze") }}',
        type : 'GET',
        data : { from_date: from, to_date: to, symbols: syms, filter_action: action },

        success(res) {
            toggle(false);
            if (res.success && res.data && res.data.length) {
                analysisData = res.data;
                renderTable();
            } else {
                noData(res.message || 'No signals found for this date range.');
                resetStats();
            }
        },

        error(xhr) {
            toggle(false);
            noData('⚠ ' + (xhr.responseJSON?.message || 'Server error — please check logs.'));
            resetStats();
        },
    });
}

/* ── Init ─────────────────────────────────────────────────── */
$(document).ready(function () {
    // Load symbols into multi-select
    $.get('{{ route("fut-option-strategy.symbols") }}', res => {
        if (!res.success) return;
        const opts = (res.symbols || []).map(s => `<option value="${s}">${s}</option>`).join('');
        $('#symbol_filter').html(opts);
    });

    // Auto-run on page load
    setTimeout(runAnalysis, 350);
});

$('#run_analysis').on('click', runAnalysis);

$('#reset_btn').on('click', function () {
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter').val(null);
    $('#action_filter').val('');
    analysisData = [];
    noData('Click "Analyse" to load signals');
    resetStats();
    setTimeout(runAnalysis, 300);
});
</script>
@endpush