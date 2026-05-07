@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ── Page Header ── */
.page-header {
    background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
    color: white; padding: 18px 24px; border-radius: 12px;
    margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.4);
}
.page-header h4 { color: white; margin: 0; }
.page-header p  { color: rgba(255,255,255,0.75); margin: 4px 0 0; font-size: 12px; }

/* ── Filter Bar ── */
.filter-bar {
    background: linear-gradient(135deg,#667eea,#764ba2);
    padding: 12px 20px; border-radius: 12px; margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(102,126,234,0.4);
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.filter-bar label {
    color: rgba(255,255,255,0.7) !important; font-size: 11px; font-weight: 700; margin: 0;
}

/* ── Date / symbol controls ── */
.date-input-wrap { display: flex; align-items: center; gap: 5px; }
.date-input-wrap input[type="date"] {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px; color: white; padding: 5px 10px; font-size: 12px;
    font-weight: 600; cursor: pointer; outline: none;
}
.date-input-wrap input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }

.date-nav-btn {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
    color: white; border-radius: 6px; width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 14px; font-weight: 700; transition: .15s;
}
.date-nav-btn:hover { background: rgba(255,255,255,0.28); }
.date-nav-btn.today-btn { width: auto; padding: 0 10px; font-size: 10px; font-weight: 800; }

.date-badge { font-size: 10px; font-weight: 700; padding: 2px 9px; border-radius: 10px; }
.badge-today { background: rgba(0,255,136,0.2); color: #00ff88; border: 1px solid rgba(0,255,136,0.3); }
.badge-hist  { background: rgba(255,193,7,0.2);  color: #ffc107; border: 1px solid rgba(255,193,7,0.3); }
.badge-range { background: rgba(162,155,254,0.2); color: #a29bfe; border: 1px solid rgba(162,155,254,0.3); }

.range-sep {
    color: rgba(255,255,255,0.5); font-size: 11px; font-weight: 700; padding: 0 2px;
}

/* Symbol select — same style as pivot */
.sym-select {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.35);
    color: white; border-radius: 8px; padding: 6px 12px; font-size: 12px;
    font-weight: 700; cursor: pointer; outline: none; min-width: 160px;
}
.sym-select option { background: #2d2d5e; color: white; }
.sym-select:focus  { border-color: rgba(255,255,255,0.7); }

/* Load button */
.btn-load {
    background: white; color: #667eea; border: none; border-radius: 8px;
    padding: 7px 20px; font-weight: 800; font-size: 13px; cursor: pointer;
    transition: .15s;
}
.btn-load:hover { background: #f0f0ff; }

/* Filter pills */
.filter-btn-group { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-pill {
    background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25);
    color: rgba(255,255,255,0.8); border-radius: 20px; padding: 5px 14px;
    font-size: 11px; font-weight: 700; cursor: pointer; transition: .15s; white-space: nowrap;
}
.filter-pill:hover          { background: rgba(255,255,255,0.22); color: white; }
.filter-pill.active         { background: white; color: #764ba2; border-color: white; }
.filter-pill.active-buy     { background: rgba(40,167,69,0.9);   color: white; border-color: #28a745; }
.filter-pill.active-sell    { background: rgba(220,53,69,0.9);   color: white; border-color: #dc3545; }
.filter-pill.active-pb      { background: rgba(255,165,2,0.9);   color: white; border-color: #ffa502; }
.filter-pill.active-notrade { background: rgba(100,100,100,0.7); color: white; border-color: #666; }

.divider-v { width: 1px; height: 24px; background: rgba(255,255,255,0.2); flex-shrink: 0; }
.last-upd  { font-size: 10px; color: rgba(255,255,255,0.45); margin-left: auto; }

/* ── Summary Strip ── */
.summary-strip { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.sum-card {
    flex: 1; min-width: 110px; background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 12px 16px; text-align: center;
}
.sum-card .s-lbl { font-size: 10px; color: rgba(255,255,255,0.4); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.sum-card .s-val { font-size: 26px; font-weight: 800; line-height: 1.1; margin-top: 4px; }
.sum-card.sc-buy   { border-color: rgba(40,167,69,0.4);   } .sum-card.sc-buy   .s-val { color: #51cf66; }
.sum-card.sc-sell  { border-color: rgba(220,53,69,0.4);   } .sum-card.sc-sell  .s-val { color: #ff6b6b; }
.sum-card.sc-pb    { border-color: rgba(255,165,2,0.4);   } .sum-card.sc-pb    .s-val { color: #ffa502; }
.sum-card.sc-nt    { border-color: rgba(255,255,255,0.1); } .sum-card.sc-nt    .s-val { color: rgba(255,255,255,0.4); }
.sum-card.sc-total .s-val { color: #00d2ff; }

/* ── Main Card / Table ── */
.main-card {
    border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.2);
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
}
.table-scroll { overflow-x: auto; }
.smc-table { width: 100%; border-collapse: collapse; min-width: 960px; }

.smc-table thead tr.hdr-group th {
    padding: 10px 10px 6px; text-align: center; font-size: 10px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .5px; white-space: nowrap;
    background: rgba(0,0,0,0.45); border-bottom: none;
}
.smc-table thead tr.hdr-cols th {
    padding: 6px 10px 9px; text-align: center; font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .3px; white-space: nowrap;
    background: rgba(0,0,0,0.35); color: rgba(255,255,255,0.5);
    border-bottom: 2px solid rgba(255,255,255,0.08);
}

.hdr-meta   { color: rgba(255,255,255,0.45) !important; }
.hdr-signal { color: #00d2ff !important; }
.hdr-trend  { color: #a29bfe !important; }
.hdr-volume { color: #f9ca24 !important; }
.hdr-sweep  { color: #fd79a8 !important; }
.hdr-fvg    { color: #55efc4 !important; }
.hdr-ob     { color: #fdcb6e !important; }
.hdr-ema    { color: #74b9ff !important; }

.sep-signal { border-left: 2px solid rgba(0,210,255,0.4) !important; }
.sep-trend  { border-left: 2px solid rgba(162,155,254,0.4) !important; }
.sep-volume { border-left: 2px solid rgba(249,202,36,0.4) !important; }
.sep-sweep  { border-left: 2px solid rgba(253,121,168,0.4) !important; }
.sep-fvg    { border-left: 2px solid rgba(85,239,196,0.4) !important; }
.sep-ob     { border-left: 2px solid rgba(253,203,110,0.4) !important; }
.sep-ema    { border-left: 2px solid rgba(116,185,255,0.4) !important; }

.smc-table tbody td {
    padding: 9px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle; white-space: nowrap;
}
.smc-table tbody tr:hover { background: rgba(255,255,255,0.05) !important; cursor: pointer; }
.sym-even { background: rgba(255,255,255,0.01); }
.sym-odd  { background: rgba(0,0,0,0.10); }
.c-num    { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.3); }
.c-muted  { color: rgba(255,255,255,.2); font-size: 9px; }

/* All badge/pill classes used by renderTable() */
.badge-sym    { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:800; background:rgba(0,210,255,0.12); color:#00d2ff; border:1px solid rgba(0,210,255,0.25); }
.badge-date   { display:inline-block; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; background:rgba(162,155,254,0.15); color:#a29bfe; border:1px solid rgba(162,155,254,0.3); font-family:monospace; }
.sig-buy      { display:inline-block; background:rgba(40,167,69,0.22);  color:#51cf66; border:1px solid rgba(40,167,69,0.5);   border-radius:6px; padding:3px 9px; font-size:10px; font-weight:800; letter-spacing:.3px; }
.sig-sell     { display:inline-block; background:rgba(220,53,69,0.22);  color:#ff6b6b; border:1px solid rgba(220,53,69,0.5);   border-radius:6px; padding:3px 9px; font-size:10px; font-weight:800; letter-spacing:.3px; }
.sig-buy-pb   { display:inline-block; background:rgba(255,165,2,0.18);  color:#ffa502; border:1px solid rgba(255,165,2,0.45);  border-radius:6px; padding:3px 9px; font-size:10px; font-weight:800; letter-spacing:.3px; }
.sig-sell-pb  { display:inline-block; background:rgba(253,121,168,0.18);color:#fd79a8; border:1px solid rgba(253,121,168,0.45);border-radius:6px; padding:3px 9px; font-size:10px; font-weight:800; letter-spacing:.3px; }
.sig-no-trade { display:inline-block; background:rgba(255,255,255,0.05);color:rgba(255,255,255,.3); border:1px solid rgba(255,255,255,0.1); border-radius:6px; padding:3px 9px; font-size:10px; font-weight:600; }
.sig-no-data  { display:inline-block; color:rgba(255,255,255,.2); font-size:9px; }
.trend-up     { display:inline-block; background:rgba(40,167,69,0.18);  color:#51cf66; border:1px solid rgba(40,167,69,0.4);  border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.trend-down   { display:inline-block; background:rgba(220,53,69,0.18);  color:#ff6b6b; border:1px solid rgba(220,53,69,0.4);  border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.trend-side   { display:inline-block; background:rgba(255,255,255,0.06);color:rgba(255,255,255,.35);border:1px solid rgba(255,255,255,0.1);border-radius:5px;padding:2px 8px;font-size:10px;font-weight:600; }
.bool-yes     { display:inline-block; background:rgba(40,167,69,0.2);   color:#51cf66; border:1px solid rgba(40,167,69,0.4);  border-radius:5px; padding:2px 7px; font-size:9px; font-weight:800; }
.bool-no      { display:inline-block; background:rgba(255,255,255,0.04);color:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,0.08);border-radius:5px;padding:2px 7px;font-size:9px; }
.bool-warn    { display:inline-block; background:rgba(255,165,2,0.18);  color:#ffa502; border:1px solid rgba(255,165,2,0.4);  border-radius:5px; padding:2px 7px; font-size:9px; font-weight:800; }
.view-link    { display:inline-block; padding:4px 12px; border-radius:5px; border:1px solid rgba(255,255,255,0.15); color:rgba(255,255,255,.45); font-size:10px; font-weight:700; text-decoration:none; transition:.15s; }
.view-link:hover { border-color:#00d2ff; color:#00d2ff; background:rgba(0,210,255,0.08); }

/* Spinner */
.spinner { width:36px; height:36px; border:4px solid rgba(255,255,255,0.1); border-top:4px solid #00d2ff; border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.loading-wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px; }
.no-data { text-align:center; padding:60px; color:rgba(255,255,255,0.25); font-size:13px; }

/* Legend */
.dot-legend { display:flex; flex-wrap:wrap; gap:16px; padding:14px 20px; border-top:1px solid rgba(255,255,255,0.06); }
.dot-item   { display:flex; align-items:center; gap:6px; font-size:10px; color:rgba(255,255,255,.4); }
.dot        { width:8px; height:8px; border-radius:50%; display:inline-block; }
.dot-green  { background:#51cf66; box-shadow:0 0 4px #51cf66; }
.dot-yellow { background:#ffa502; }
.dot-off    { background:rgba(255,255,255,0.12); }

/* Range info bar */
.range-info-bar {
    background: rgba(162,155,254,0.08); border: 1px solid rgba(162,155,254,0.2);
    border-radius: 8px; padding: 8px 16px; margin-bottom: 14px;
    font-size: 11px; color: rgba(255,255,255,.5); display: none;
}
.range-info-bar.visible { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.range-info-bar strong  { color: #a29bfe; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── Page Header ── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>&#129504; Smart Money Analysis
                    <span style="background:rgba(255,255,255,0.15);padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;margin-left:6px;">SMC &middot; Daily EOD</span>
                </h4>
                <p>
                    Trend &middot; Volume Spike &middot; Liquidity Sweep &middot; Fair Value Gap &middot; Order Block &middot; EMA-20
                    &nbsp;&middot;&nbsp;
                    <strong style="color:#b2ffda;">&#129033; BUY = Sweep Low + FVG + Volume</strong>
                    &nbsp;&middot;&nbsp;
                    <strong style="color:#ffb2b2;">&#129035; SELL = Sweep High + FVG + Volume</strong>
                </p>
            </div>
        </div>
    </div>

    {{-- ── Filter Bar ── --}}
    <div class="filter-bar">

        {{-- From date --}}
        <label>FROM:</label>
        <div class="date-input-wrap">
            <button class="date-nav-btn" onclick="shiftDate('from', -1)" title="Previous day">&#8249;</button>
            <input type="date" id="from-picker"
                   value="{{ $todayStr }}" max="{{ $todayStr }}"
                   onchange="syncDateConstraints()">
            <button class="date-nav-btn" onclick="shiftDate('from', 1)" title="Next day">&#8250;</button>
        </div>

        <span class="range-sep">&#8594;</span>

        {{-- To date --}}
        <label>TO:</label>
        <div class="date-input-wrap">
            <button class="date-nav-btn" onclick="shiftDate('to', -1)" title="Previous day">&#8249;</button>
            <input type="date" id="to-picker"
                   value="{{ $todayStr }}" max="{{ $todayStr }}"
                   onchange="syncDateConstraints()">
            <button class="date-nav-btn" onclick="shiftDate('to', 1)" title="Next day">&#8250;</button>
            <button class="date-nav-btn today-btn" onclick="goToday()">Today</button>
            <span id="date-badge"></span>
        </div>

        <div class="divider-v"></div>

        {{-- Symbol selector --}}
        <label>SYMBOL:</label>
        <select id="sym-select" class="sym-select" onchange="">
            <option value="ALL">&#8212; All Symbols &#8212;</option>
            {{-- Populated by JS after first AJAX response --}}
        </select>

        {{-- Load button --}}
        <button class="btn-load" onclick="loadData()">&#8635; Load</button>

        <div class="divider-v"></div>

        {{-- Signal filter pills --}}
        <label>FILTER:</label>
        <div class="filter-btn-group" id="filter-pills">
            <button class="filter-pill active" onclick="filterTable('ALL',this)">&#9726; All</button>
            <button class="filter-pill"        onclick="filterTable('BUY',this)">&#129033; Buy</button>
            <button class="filter-pill"        onclick="filterTable('SELL',this)">&#129035; Sell</button>
            <button class="filter-pill"        onclick="filterTable('BUY_PULLBACK',this)">&#8629; Buy PB</button>
            <button class="filter-pill"        onclick="filterTable('SELL_PULLBACK',this)">&#8629; Sell PB</button>
            <button class="filter-pill"        onclick="filterTable('NO_TRADE',this)">&#9675; No Trade</button>
        </div>

        <span class="last-upd" id="last-upd"></span>
    </div>

    {{-- ── Range info bar (shown only for multi-day ranges) ── --}}
    <div class="range-info-bar" id="range-info-bar">
        <span>&#128197; Showing range:</span>
        <strong id="range-label">&mdash;</strong>
        <span id="range-rows-label" style="color:rgba(255,255,255,.35);"></span>
        <span style="margin-left:auto;color:rgba(255,255,255,.3);font-size:10px;">Each row = one symbol on one date. Sorted: latest date first, then by signal.</span>
    </div>

    {{-- ── Summary Strip ── --}}
    <div class="summary-strip">
        <div class="sum-card sc-buy">  <div class="s-lbl">&#129033; Buy</div>      <div class="s-val" id="cnt-buy">—</div></div>
        <div class="sum-card sc-sell"> <div class="s-lbl">&#129035; Sell</div>     <div class="s-val" id="cnt-sell">—</div></div>
        <div class="sum-card sc-pb">   <div class="s-lbl">&#8629; Pullbacks</div>  <div class="s-val" id="cnt-pb">—</div></div>
        <div class="sum-card sc-nt">   <div class="s-lbl">&#9675; No Trade</div>   <div class="s-val" id="cnt-nt">—</div></div>
        <div class="sum-card sc-total"><div class="s-lbl">&#931; Total</div>       <div class="s-val" id="cnt-total">—</div></div>
    </div>

    {{-- ── Main Table ── --}}
    <div class="main-card">
        <div class="table-scroll">
            <table class="smc-table" id="smc-table">
                <thead>
                    <tr class="hdr-group">
                        <th colspan="4" class="hdr-meta">Meta</th>
                        <th colspan="2" class="hdr-signal sep-signal">&#9889; Signal</th>
                        <th colspan="1" class="hdr-trend sep-trend">&#128200; Trend</th>
                        <th colspan="2" class="hdr-volume sep-volume">&#128314; Volume</th>
                        <th colspan="2" class="hdr-sweep sep-sweep">&#127959; Liquidity Sweep</th>
                        <th colspan="2" class="hdr-fvg sep-fvg">&#9644; FVG</th>
                        <th colspan="2" class="hdr-ob sep-ob">&#9633; Order Block</th>
                        <th colspan="2" class="hdr-ema sep-ema">&#128200; EMA-20</th>
                        <th colspan="1" class="hdr-meta">&nbsp;</th>
                    </tr>
                    <tr class="hdr-cols">
                        <th class="hdr-meta">#</th>
                        <th class="hdr-meta">Symbol</th>
                        <th class="hdr-meta">Analysis Date<br><span style="font-size:8px;opacity:.5;font-weight:400;">Signal as of</span></th>
                        <th class="hdr-meta">Price<br><span style="font-size:8px;opacity:.5;font-weight:400;">Close on date</span></th>
                        <th class="hdr-signal sep-signal">Signal</th>
                        <th class="hdr-signal">Reason</th>
                        <th class="hdr-trend sep-trend">Trend<br><span style="font-size:8px;opacity:.6;font-weight:400;">HH/HL vs LH/LL</span></th>
                        <th class="hdr-volume sep-volume">Spike<br><span style="font-size:8px;opacity:.6;font-weight:400;">&gt;1.5× avg</span></th>
                        <th class="hdr-volume">Avg Vol<br><span style="font-size:8px;opacity:.6;font-weight:400;">20-day</span></th>
                        <th class="hdr-sweep sep-sweep">Low Sweep<br><span style="font-size:8px;opacity:.6;font-weight:400;">Bullish trap</span></th>
                        <th class="hdr-sweep">High Sweep<br><span style="font-size:8px;opacity:.6;font-weight:400;">Bearish trap</span></th>
                        <th class="hdr-fvg sep-fvg">Bull FVG</th>
                        <th class="hdr-fvg">Bear FVG</th>
                        <th class="hdr-ob sep-ob">Bull OB<br><span style="font-size:8px;opacity:.6;font-weight:400;">Demand</span></th>
                        <th class="hdr-ob">Bear OB<br><span style="font-size:8px;opacity:.6;font-weight:400;">Supply</span></th>
                        <th class="hdr-ema sep-ema">EMA-20</th>
                        <th class="hdr-ema">vs Close</th>
                        <th class="hdr-meta">&nbsp;</th>
                    </tr>
                </thead>
                <tbody id="smc-tbody">
                    <tr><td colspan="18">
                        <div class="loading-wrap">
                            <div class="spinner"></div>
                            <div style="color:white;margin-top:14px;font-size:13px;">Loading signals&hellip;</div>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
        <div class="dot-legend">
            <span style="font-size:10px;color:rgba(255,255,255,.35);font-weight:700;text-transform:uppercase;">Legend:</span>
            <span class="dot-item"><span class="dot dot-green"></span> Bullish / Yes</span>
            <span class="dot-item"><span class="dot dot-yellow"></span> Bearish / Warning</span>
            <span class="dot-item"><span class="dot dot-off"></span> Not detected</span>
            <span class="dot-item" style="margin-left:auto;color:rgba(255,255,255,.25);">Click any row to view symbol detail</span>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
// ── Constants ─────────────────────────────────────────────────────────────────
const SIGNALS_URL  = '{{ route("smart-money.signals") }}';
const SHOW_BASE    = '{{ url("smart-money") }}';
const TODAY_STR    = '{{ $todayStr }}';

let activeFilter      = 'ALL';
let availableSymbols  = [];   // populated from first AJAX response

// ── On page load ──────────────────────────────────────────────────────────────
$(document).ready(function () {
    loadData();
});

// ── Date helpers ──────────────────────────────────────────────────────────────

function getFromDate() { return document.getElementById('from-picker').value; }
function getToDate()   { return document.getElementById('to-picker').value; }

function shiftDate(which, days) {
    const id     = which === 'from' ? 'from-picker' : 'to-picker';
    const picker = document.getElementById(id);
    const d      = new Date(picker.value);
    d.setDate(d.getDate() + days);
    const s = d.toISOString().split('T')[0];
    if (s > TODAY_STR) return;
    picker.value = s;
    syncDateConstraints();
}

function goToday() {
    document.getElementById('from-picker').value = TODAY_STR;
    document.getElementById('to-picker').value   = TODAY_STR;
    syncDateConstraints();
    loadData();
}

/**
 * Keep from <= to constraints valid.
 * If user changes from > to, push to = from.
 * If user changes to < from, push from = to.
 * Then trigger loadData.
 */
function syncDateConstraints() {
    const fp = document.getElementById('from-picker');
    const tp = document.getElementById('to-picker');

    // Clamp both to today
    if (fp.value > TODAY_STR) fp.value = TODAY_STR;
    if (tp.value > TODAY_STR) tp.value = TODAY_STR;

    // Enforce from <= to
    if (fp.value > tp.value) tp.value = fp.value;

    // Update to-picker minimum to from date
    tp.min = fp.value;

    updateDateBadge();
    loadData();
}

function updateDateBadge() {
    const from    = getFromDate();
    const to      = getToDate();
    const isToday = (from === TODAY_STR && to === TODAY_STR);
    const isRange = (from !== to);
    const el      = document.getElementById('date-badge');

    if (isToday) {
        el.innerHTML = '<span class="date-badge badge-today">&#9679; Live</span>';
    } else if (isRange) {
        el.innerHTML = '<span class="date-badge badge-range">&#128197; Range</span>';
    } else {
        el.innerHTML = '<span class="date-badge badge-hist">&#128197; Historical</span>';
    }
}

// ── Symbol dropdown ────────────────────────────────────────────────────────────

function rebuildSymbolDropdown(symbols) {
    if (JSON.stringify(availableSymbols) === JSON.stringify(symbols)) return;
    availableSymbols = symbols;

    const sel  = document.getElementById('sym-select');
    const prev = sel.value;

    sel.innerHTML = '<option value="ALL">&#8212; All Symbols &#8212;</option>';
    symbols.forEach(s => {
        const opt = document.createElement('option');
        opt.value       = s;
        opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

function getSelectedSymbol() {
    return document.getElementById('sym-select').value || 'ALL';
}

// ── Main AJAX loader ───────────────────────────────────────────────────────────

function loadData() {
    const fromDate = getFromDate();
    const toDate   = getToDate();
    const symbol   = getSelectedSymbol();
    const isRange  = (fromDate !== toDate);

    updateDateBadge();

    // Show spinner
    $('#smc-tbody').html(
        '<tr><td colspan="18">' +
        '<div class="loading-wrap">' +
        '<div class="spinner"></div>' +
        '<div style="color:white;margin-top:12px;font-size:13px;">' +
        'Fetching ' + (isRange ? fromDate + ' → ' + toDate : fromDate) +
        (symbol !== 'ALL' ? ' &nbsp;·&nbsp; ' + symbol : '') +
        '&hellip;</div>' +
        '</div></td></tr>'
    );
    $('#last-upd').text('');
    $('#range-info-bar').removeClass('visible');

    $.ajax({
        url:  SIGNALS_URL,
        data: { from_date: fromDate, to_date: toDate, symbol: symbol },
        success: function(res) {

            // Rebuild symbol dropdown from response (only on first load)
            if (res.symbols && res.symbols.length) {
                rebuildSymbolDropdown(res.symbols);
            }

            if (!res.success || !res.results || !res.results.length) {
                $('#smc-tbody').html(
                    '<tr><td colspan="18">' +
                    '<div class="no-data">' +
                    '<i class="fas fa-calendar-times" style="font-size:2.5rem;opacity:.3;"></i>' +
                    '<p style="margin-top:14px;">' + (res.message || 'No data for selected range') + '</p>' +
                    '<small style="color:rgba(255,255,255,.2);">Market may have been closed, or no data loaded yet.</small>' +
                    '</div></td></tr>'
                );
                updateSummary({ buy:0, sell:0, buy_pullback:0, sell_pullback:0, no_trade:0, total:0 });
                return;
            }

            updateSummary(res.summary);
            renderTable(res.results, res.to_date, res.is_today, res.is_range);
            updateFilterPillCounts(res.summary);
            applyFilter(activeFilter);

            // Show range info bar for multi-day
            if (res.is_range) {
                $('#range-label').text(res.from_date + ' → ' + res.to_date);
                $('#range-rows-label').text(res.results.length + ' rows across ' + countUniqueDates(res.results) + ' trading day(s)');
                $('#range-info-bar').addClass('visible');
            } else {
                $('#range-info-bar').removeClass('visible');
            }

            $('#last-upd').text('Updated: ' + new Date().toLocaleTimeString());
        },
        error: function(xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message : 'Server error';
            $('#smc-tbody').html(
                '<tr><td colspan="18"><div class="no-data">&#9888; ' + msg + '</div></td></tr>'
            );
        }
    });
}

// ── Summary ────────────────────────────────────────────────────────────────────

function updateSummary(s) {
    $('#cnt-buy').text(s.buy);
    $('#cnt-sell').text(s.sell);
    $('#cnt-pb').text((s.buy_pullback || 0) + (s.sell_pullback || 0));
    $('#cnt-nt').text(s.no_trade);
    $('#cnt-total').text(s.total);
}

function updateFilterPillCounts(s) {
    const pills  = document.querySelectorAll('#filter-pills .filter-pill');
    const counts = [s.total, s.buy, s.sell, s.buy_pullback, s.sell_pullback, s.no_trade];
    const labels = ['&#9726; All','&#129033; Buy','&#129035; Sell','&#8629; Buy PB','&#8629; Sell PB','&#9675; No Trade'];
    pills.forEach((btn, i) => {
        btn.innerHTML = labels[i] + ' <span style="opacity:.7;">(' + (counts[i] || 0) + ')</span>';
    });
}

// ── Table renderer ─────────────────────────────────────────────────────────────

function renderTable(results, toDate, isToday, isRange) {
    const yes  = '<span class="bool-yes">&#10003; YES</span>';
    const no   = '<span class="bool-no">&#10007; NO</span>';
    const warn = '<span class="bool-warn">&#10003; YES</span>';

    let rows = '';

    results.forEach(function(r, i) {
        const zebraClass = i % 2 === 0 ? 'sym-even' : 'sym-odd';

        // Detail URL always passes the analysis_date so show page uses same date
        const analysisDate = r.analysis_date || toDate;
        const isAnalysisToday = (analysisDate === TODAY_STR);
        const detailUrl = SHOW_BASE + '/' + r.symbol + (isAnalysisToday ? '' : '?date=' + analysisDate);

        // Signal badge
        const sigMap = {
            'BUY':           '<span class="sig-buy">&#129033; BUY</span>',
            'SELL':          '<span class="sig-sell">&#129035; SELL</span>',
            'BUY_PULLBACK':  '<span class="sig-buy-pb">&#8629; BUY PB</span>',
            'SELL_PULLBACK': '<span class="sig-sell-pb">&#8629; SELL PB</span>',
            'NO_TRADE':      '<span class="sig-no-trade">&#9675; NO TRADE</span>',
        };
        const sigHtml = sigMap[r.signal] || '<span class="sig-no-data">? NO DATA</span>';

        // Trend badge
        const trendMap = {
            'UPTREND':   '<span class="trend-up">&#8593; UP</span>',
            'DOWNTREND': '<span class="trend-down">&#8595; DOWN</span>',
            'SIDEWAYS':  '<span class="trend-side">&#8594; SIDE</span>',
        };
        const trendHtml = trendMap[r.trend] || '<span class="c-muted">&mdash;</span>';

        // EMA vs close
        let emaVs = '';
        if (r.last_close && r.ema20) {
            emaVs = r.last_close > r.ema20
                ? '<span style="color:#51cf66;font-size:10px;font-weight:800;">&#9650; ABV</span>'
                : '<span style="color:#ff6b6b;font-size:10px;font-weight:800;">&#9660; BLW</span>';
        }

        // Analysis date cell — show badge when range mode
        const dateCellHtml = isRange
            ? '<span class="badge-date">' + fmtDateShort(analysisDate) + '</span>'
            : '<span style="font-size:10px;color:rgba(255,255,255,.5);">' + fmtDate(analysisDate) + '</span>';

        const priceCellHtml = r.last_close
            ? '<span style="font-family:monospace;font-size:11px;font-weight:700;color:white;">&#8377;' + n(r.last_close) + '</span>'
            : '<span class="c-muted">&mdash;</span>';

        // OB cells
        const obBull = r.order_block_bull ? '<span class="bool-yes">&#8377;' + n(r.order_block_bull) + '</span>' : no;
        const obBear = r.order_block_bear ? '<span class="bool-warn">&#8377;' + n(r.order_block_bear) + '</span>' : no;

        const avgVol = r.avg_volume
            ? '<span style="font-family:monospace;font-size:10px;color:rgba(255,255,255,.35);">' + nInt(r.avg_volume) + '</span>'
            : '<span class="c-muted">&mdash;</span>';

        const emaVal = r.ema20
            ? '<span style="font-family:monospace;font-size:11px;color:#74b9ff;">&#8377;' + n(r.ema20) + '</span>'
            : '&mdash;';

        rows +=
            '<tr class="' + zebraClass + '" data-signal="' + r.signal + '" ' +
            'onclick="window.location=\'' + detailUrl + '\'" style="cursor:pointer;">' +

            '<td class="c-num">' + (i + 1) + '</td>' +
            '<td><span class="badge-sym">' + r.symbol + '</span></td>' +
            '<td>' + dateCellHtml + '</td>' +
            '<td>' + priceCellHtml + '</td>' +

            '<td class="sep-signal">' + sigHtml + '</td>' +
            '<td style="max-width:220px;text-align:left;"><span style="font-size:10px;color:rgba(255,255,255,.45);line-height:1.4;display:block;">' + (r.reason || '') + '</span></td>' +

            '<td class="sep-trend">' + trendHtml + '</td>' +

            '<td class="sep-volume">' + (r.volume_spike ? yes : no) + '</td>' +
            '<td>' + avgVol + '</td>' +

            '<td class="sep-sweep">' + (r.liquidity_sweep_low  ? yes  : no) + '</td>' +
            '<td>'                  + (r.liquidity_sweep_high ? warn : no) + '</td>' +

            '<td class="sep-fvg">' + (r.fvg_bullish ? yes  : no) + '</td>' +
            '<td>'                 + (r.fvg_bearish ? warn : no) + '</td>' +

            '<td class="sep-ob">' + obBull + '</td>' +
            '<td>'                + obBear + '</td>' +

            '<td class="sep-ema">' + emaVal + '</td>' +
            '<td>' + emaVs + '</td>' +

            '<td><a href="' + detailUrl + '" class="view-link" onclick="event.stopPropagation()">Detail &rarr;</a></td>' +
            '</tr>';
    });

    if (!rows) {
        rows = '<tr><td colspan="18"><div class="no-data"><p>No candle data found.</p></div></td></tr>';
    }

    $('#smc-tbody').html(rows);
}

// ── Filter ────────────────────────────────────────────────────────────────────

function filterTable(signal, btn) {
    activeFilter = signal;
    document.querySelectorAll('#filter-pills .filter-pill').forEach(b => {
        b.classList.remove('active','active-buy','active-sell','active-pb','active-notrade');
    });
    const classMap = {
        'ALL':'active','BUY':'active-buy','SELL':'active-sell',
        'BUY_PULLBACK':'active-pb','SELL_PULLBACK':'active-pb','NO_TRADE':'active-notrade'
    };
    btn.classList.add(classMap[signal] || 'active');
    applyFilter(signal);
}

function applyFilter(signal) {
    document.querySelectorAll('#smc-tbody tr[data-signal]').forEach(row => {
        row.style.display = (signal === 'ALL' || row.dataset.signal === signal) ? '' : 'none';
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function countUniqueDates(results) {
    return new Set(results.map(r => r.analysis_date)).size;
}

function n(v) {
    if (v == null || v === '') return '\u2014';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function nInt(v) {
    if (v == null) return '\u2014';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function fmtDate(d) {
    if (!d) return '\u2014';
    const p = String(d).split('-');
    if (p.length < 3) return d;
    const m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return parseInt(p[2]) + ' ' + m[parseInt(p[1])-1] + ' ' + p[0];
}

function fmtDateShort(d) {
    // e.g. "14 Mar" for range mode
    if (!d) return '\u2014';
    const p = String(d).split('-');
    if (p.length < 3) return d;
    const m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return parseInt(p[2]) + ' ' + m[parseInt(p[1])-1];
}
</script>
@endpush