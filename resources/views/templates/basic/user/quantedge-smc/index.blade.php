@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');
:root {
    --navy-900:#0a0f1e; --navy-800:#0d1428; --navy-700:#111b35;
    --border:rgba(255,255,255,0.07);
    --green:#10b981; --red:#f43f5e; --amber:#f59e0b; --sky:#38bdf8; --violet:#a78bfa;
    --text-1:rgba(255,255,255,0.92); --text-2:rgba(255,255,255,0.55); --text-3:rgba(255,255,255,0.25);
    --mono:'JetBrains Mono',monospace; --display:'Rajdhani',sans-serif;
}

.ios-header {
    background:linear-gradient(135deg,#0d1428 0%,#0d2818 50%,#0d1428 100%);
    border:1px solid var(--border); border-bottom:2px solid var(--green);
    border-radius:14px; padding:20px 28px; margin-bottom:18px;
    position:relative; overflow:hidden;
}
.ios-header::before {
    content:'SMC'; position:absolute; right:24px; top:50%; transform:translateY(-50%);
    font-family:var(--display); font-size:90px; font-weight:700;
    color:rgba(16,185,129,0.04); letter-spacing:6px; pointer-events:none; user-select:none;
}
.ios-title { font-family:var(--display); font-size:22px; font-weight:700; color:var(--text-1); margin:0; }
.tag {
    font-size:10px; font-weight:700; padding:2px 9px; border-radius:4px;
    margin-left:6px; vertical-align:middle; letter-spacing:1.5px;
}
.tag-smc { background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:var(--green); }
.tag-eod { background:rgba(56,189,248,0.12); border:1px solid rgba(56,189,248,0.3); color:var(--sky); }
.ios-sub { font-family:var(--mono); font-size:11px; color:var(--text-2); margin:6px 0 0; }
.lp { display:inline-block; font-family:var(--mono); font-size:10px; font-weight:600; padding:2px 9px; border-radius:4px; margin:2px; }
.lp-buy  { background:rgba(16,185,129,0.10); border:1px solid rgba(16,185,129,0.22); color:var(--green); }
.lp-sell { background:rgba(244,63,94,0.10);  border:1px solid rgba(244,63,94,0.22);  color:var(--red); }

/* Controls */
.ios-controls {
    background:var(--navy-800); border:1px solid var(--border);
    border-radius:12px; padding:14px 20px; margin-bottom:16px;
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
}
.ctrl-label { font-family:var(--display); font-size:10px; font-weight:700; color:var(--text-3); letter-spacing:1.5px; text-transform:uppercase; }
.ctrl-sep   { width:1px; height:28px; background:var(--border); flex-shrink:0; }
.tf-group { display:flex; gap:4px; }
.tf-btn {
    font-family:var(--display); font-size:12px; font-weight:700;
    padding:6px 15px; border-radius:7px; border:1px solid var(--border);
    background:transparent; color:var(--text-2); cursor:pointer; transition:.15s;
}
.tf-btn:hover  { border-color:rgba(16,185,129,0.4); color:var(--green); }
.tf-btn.active { background:rgba(16,185,129,0.15); border-color:var(--green); color:var(--green); }
.ios-date {
    background:rgba(255,255,255,0.06); border:1px solid var(--border);
    border-radius:8px; color:var(--text-1); padding:5px 10px;
    font-family:var(--mono); font-size:11px; outline:none;
}
.ios-date::-webkit-calendar-picker-indicator { filter:invert(.55); cursor:pointer; }
.dnav {
    background:rgba(255,255,255,0.06); border:1px solid var(--border);
    color:var(--text-1); border-radius:7px; width:26px; height:26px;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; font-size:13px; font-weight:700; transition:.12s;
}
.dnav:hover { background:rgba(255,255,255,0.1); }
.dnav.today-btn { width:auto; padding:0 10px; font-family:var(--display); font-size:9px; }
.ios-select {
    background:rgba(255,255,255,0.06); border:1px solid var(--border); color:var(--text-1);
    border-radius:8px; padding:5px 10px; font-family:var(--display); font-size:12px;
    font-weight:600; cursor:pointer; outline:none; min-width:150px;
}
.ios-select option { background:#0d1428; }
.ios-btn {
    background:var(--green); color:#000; border:none; border-radius:8px;
    padding:7px 22px; font-family:var(--display); font-size:13px; font-weight:800; cursor:pointer;
}
.ios-btn:hover { background:#34d399; }
.ios-reset-btn {
    background:rgba(255,255,255,0.07); color:var(--text-2); border:1px solid var(--border);
    border-radius:8px; padding:6px 16px; font-family:var(--display); font-size:12px; font-weight:700; cursor:pointer;
}
.sp-wrap { display:flex; gap:3px; flex-wrap:wrap; }
.sp {
    padding:4px 12px; border-radius:20px; font-family:var(--display); font-size:10px; font-weight:700;
    cursor:pointer; border:1px solid var(--border); background:var(--navy-800); color:var(--text-2); transition:.15s;
}
.sp:hover  { border-color:var(--green); color:var(--green); }
.sp.active { background:rgba(16,185,129,0.15); border-color:var(--green); color:var(--green); }
.sp.active-sell { background:rgba(244,63,94,0.15); border-color:var(--red); color:var(--red); }
.sp.active-pb   { background:rgba(245,158,11,0.15); border-color:var(--amber); color:var(--amber); }
.sp.active-nt   { background:rgba(255,255,255,0.06); border-color:rgba(255,255,255,0.2); color:var(--text-2); }
.ml-auto { margin-left:auto; }
.last-upd { font-family:var(--mono); font-size:9px; color:var(--text-3); }
.dbadge { font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; }
.dbadge.live  { background:rgba(16,185,129,0.12); color:var(--green); border:1px solid rgba(16,185,129,0.25); }
.dbadge.hist  { background:rgba(251,191,36,0.12);  color:var(--amber); border:1px solid rgba(251,191,36,0.25); }
.dbadge.range { background:rgba(167,139,250,0.12); color:var(--violet); border:1px solid rgba(167,139,250,0.25); }

/* Warn */
.ios-warn {
    background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.28);
    border-radius:10px; padding:12px 18px; margin-bottom:14px;
    font-family:var(--display); font-size:13px; color:var(--amber); display:none;
}

/* Stats */
.ios-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.stat-box {
    background:var(--navy-800); border:1px solid var(--border);
    border-radius:10px; padding:12px 16px; min-width:100px; flex:1;
}
.stat-box small { display:block; font-family:var(--display); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-3); margin-bottom:4px; }
.stat-box strong { display:block; font-family:var(--mono); font-size:1.2rem; font-weight:700; }
.s-buy  { border-left:3px solid var(--green); }
.s-sell { border-left:3px solid var(--red); }
.s-pb   { border-left:3px solid var(--amber); }
.s-tot  { border-left:3px solid var(--sky); }

/* Card / Table */
.ios-card { background:var(--navy-800); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
.ios-card-hdr {
    padding:14px 20px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px; background:var(--navy-700);
}
.ios-card-title { font-family:var(--display); font-size:14px; font-weight:700; color:var(--text-1); }
.ios-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

.ios-table { width:100%; border-collapse:collapse; font-family:var(--mono); min-width:860px; }
.ios-table thead tr.hdr-grp th {
    padding:9px 10px 5px; text-align:center; font-family:var(--display);
    font-size:9px; font-weight:800; letter-spacing:1px; text-transform:uppercase;
    background:rgba(0,0,0,0.4); border-bottom:none; white-space:nowrap;
}
.ios-table thead tr.hdr-cols th {
    padding:5px 10px 9px; text-align:center; font-family:var(--display);
    font-size:8px; font-weight:700; letter-spacing:.3px; text-transform:uppercase;
    background:rgba(0,0,0,0.3); color:var(--text-3); border-bottom:2px solid var(--border); white-space:nowrap;
}
.ios-table tbody td {
    padding:9px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid rgba(255,255,255,0.03);
    vertical-align:middle; white-space:nowrap; color:var(--text-2);
}
.ios-table tbody tr { cursor:pointer; }
.ios-table tbody tr:hover { background:rgba(255,255,255,0.04) !important; }
.row-even { background:rgba(255,255,255,0.008); }
.row-odd  { background:rgba(0,0,0,0.1); }
.row-buy  { background:rgba(16,185,129,0.03)  !important; }
.row-sell { background:rgba(244,63,94,0.03)   !important; }
.row-pb   { background:rgba(245,158,11,0.02)  !important; }

.sep-sig  { border-left:2px solid rgba(16,185,129,0.25)  !important; }
.sep-smc  { border-left:2px solid rgba(167,139,250,0.25) !important; }
.sep-ema  { border-left:2px solid rgba(56,189,248,0.25)  !important; }
.hg-sig  { color:var(--green)  !important; }
.hg-smc  { color:var(--violet) !important; }
.hg-ema  { color:var(--sky)    !important; }

.c-num  { font-size:9px; color:var(--text-3); }
.sym-badge {
    display:inline-block; padding:2px 8px; border-radius:5px; font-size:10px; font-weight:700;
    background:rgba(16,185,129,0.10); color:var(--green); border:1px solid rgba(16,185,129,0.22);
}
.date-chip {
    display:inline-block; padding:2px 7px; border-radius:4px; font-size:10px; font-weight:700;
    background:rgba(167,139,250,0.12); color:var(--violet); border:1px solid rgba(167,139,250,0.25);
}

/* Signal badges */
.sig-buy   { display:inline-block; background:rgba(16,185,129,0.2);  color:#34d399; border:1px solid rgba(16,185,129,0.45); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-sell  { display:inline-block; background:rgba(244,63,94,0.2);   color:#fb7185; border:1px solid rgba(244,63,94,0.45);  border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-buyp  { display:inline-block; background:rgba(245,158,11,0.18); color:#fbbf24; border:1px solid rgba(245,158,11,0.45); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-sellp { display:inline-block; background:rgba(251,146,60,0.18); color:#fb923c; border:1px solid rgba(251,146,60,0.45); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-nt    { display:inline-block; background:transparent; color:rgba(255,255,255,0.2); border:1px solid var(--border); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:9px; }

/* Trend */
.trend-up   { display:inline-block; background:rgba(16,185,129,0.15); color:#34d399; border:1px solid rgba(16,185,129,0.3); border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.trend-dn   { display:inline-block; background:rgba(244,63,94,0.15);  color:#fb7185; border:1px solid rgba(244,63,94,0.3);  border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.trend-side { display:inline-block; background:rgba(255,255,255,0.05); color:var(--text-3); border:1px solid var(--border); border-radius:5px; padding:2px 8px; font-size:10px; }

/* Bool */
.b-yes { display:inline-block; background:rgba(16,185,129,0.18); color:#34d399; border:1px solid rgba(16,185,129,0.35); border-radius:4px; padding:1px 7px; font-size:9px; font-weight:800; }
.b-no  { display:inline-block; background:rgba(255,255,255,0.04); color:rgba(255,255,255,0.2); border:1px solid var(--border); border-radius:4px; padding:1px 7px; font-size:9px; }
.b-wrn { display:inline-block; background:rgba(245,158,11,0.15); color:#fbbf24; border:1px solid rgba(245,158,11,0.35); border-radius:4px; padding:1px 7px; font-size:9px; font-weight:800; }

/* Loading / empty */
.ios-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px; }
.ios-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,0.1); border-top:3px solid var(--green); border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.ios-spin-txt { color:var(--text-2); margin-top:12px; font-family:var(--display); font-size:13px; }
.ios-empty { text-align:center; padding:60px 20px; color:var(--text-3); font-family:var(--display); font-size:13px; }
.ios-empty i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="ios-header">
        <h4 class="ios-title">
            &#9670; QuantEdge — Smart Money Analysis
            <span class="tag tag-smc">SMC</span>
            <span class="tag tag-eod">STOCK OHLC</span>
        </h4>
        <div class="ios-sub" style="margin-top:8px;">
            <span class="lp lp-buy">BUY: Sweep Low + Bullish FVG + Volume + Above EMA-20</span>
            <span class="lp lp-sell">SELL: Sweep High + Bearish FVG + Volume + Below EMA-20</span>
        </div>
        <div class="ios-sub" style="margin-top:5px; color:var(--text-3);">
            Source: cp_stock_ohlc tables &nbsp;·&nbsp; Config-scoped symbols &nbsp;·&nbsp; 60-candle rolling window &nbsp;·&nbsp; Pullback = Order Block retest
        </div>
    </div>

    {{-- Controls --}}
    <div class="ios-controls">
        <span class="ctrl-label">TF</span>
        <div class="tf-group">
            <button class="tf-btn active" data-tf="15min" onclick="setTf('15min',this)">15 Min</button>
            <button class="tf-btn"        data-tf="30min" onclick="setTf('30min',this)">30 Min</button>
            <button class="tf-btn"        data-tf="1hr"   onclick="setTf('1hr',this)">1 Hour</button>
        </div>

        <div class="ctrl-sep"></div>

        <span class="ctrl-label">FROM</span>
        <div style="display:flex;align-items:center;gap:4px;">
            <button class="dnav" onclick="shiftDate('from',-1)">&#8249;</button>
            <input type="date" id="from-date" class="ios-date"
                   value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"
                   onchange="syncDates()">
            <button class="dnav" onclick="shiftDate('from',1)">&#8250;</button>
        </div>

        <span class="ctrl-label">TO</span>
        <div style="display:flex;align-items:center;gap:4px;">
            <button class="dnav" onclick="shiftDate('to',-1)">&#8249;</button>
            <input type="date" id="to-date" class="ios-date"
                   value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"
                   onchange="syncDates()">
            <button class="dnav" onclick="shiftDate('to',1)">&#8250;</button>
            <button class="dnav today-btn" onclick="goToday()">TODAY</button>
            <span id="date-badge"></span>
        </div>

        <div class="ctrl-sep"></div>

        <span class="ctrl-label">SYMBOL</span>
        <select id="ios-sym" class="ios-select">
            <option value="ALL">— All Symbols —</option>
        </select>

        <button class="ios-btn" onclick="loadData()">&#9670; Load</button>
        <button class="ios-reset-btn" onclick="goToday()">Today</button>

        <div class="ctrl-sep"></div>

        <span class="ctrl-label">FILTER</span>
        <div class="sp-wrap" id="filter-pills">
            <div class="sp active"      data-sig="ALL"           onclick="filterSig('ALL',this)">All</div>
            <div class="sp"             data-sig="BUY"           onclick="filterSig('BUY',this)">&#8679; Buy</div>
            <div class="sp"             data-sig="SELL"          onclick="filterSig('SELL',this)">&#8681; Sell</div>
            <div class="sp"             data-sig="BUY_PULLBACK"  onclick="filterSig('BUY_PULLBACK',this)">Buy PB</div>
            <div class="sp"             data-sig="SELL_PULLBACK" onclick="filterSig('SELL_PULLBACK',this)">Sell PB</div>
            <div class="sp"             data-sig="NO_TRADE"      onclick="filterSig('NO_TRADE',this)">No Trade</div>
        </div>

        <div class="ml-auto">
            <span class="last-upd" id="ios-upd"></span>
        </div>
    </div>

    {{-- Warn --}}
    <div class="ios-warn" id="ios-warn">&#9888; <span id="ios-warn-msg"></span></div>

    {{-- Stats --}}
    <div class="ios-stats" id="ios-stats" style="display:none;">
        <div class="stat-box s-tot"><small>Total</small><strong id="st-total" style="color:var(--sky);">0</strong></div>
        <div class="stat-box s-buy"><small>&#8679; Buy</small><strong id="st-buy" style="color:var(--green);">0</strong></div>
        <div class="stat-box s-sell"><small>&#8681; Sell</small><strong id="st-sell" style="color:var(--red);">0</strong></div>
        <div class="stat-box s-pb"><small>Pullbacks</small><strong id="st-pb" style="color:var(--amber);">0</strong></div>
        <div class="stat-box s-tot"><small>No Trade</small><strong id="st-nt" style="color:var(--text-3);">0</strong></div>
    </div>

    {{-- Table --}}
    <div class="ios-card">
        <div class="ios-card-hdr">
            <span class="ios-card-title" id="ios-card-title">&#9670; QuantEdge Smart Money &nbsp;·&nbsp; 15 Min</span>
            <span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);" id="ios-card-info"></span>
        </div>
        <div class="ios-tscroll">
            <table class="ios-table">
                <thead>
                    <tr class="hdr-grp">
                        <th colspan="4">Info</th>
                        <th colspan="2" class="hg-sig sep-sig">&#9889; Signal</th>
                        <th colspan="3" class="hg-smc sep-smc">&#9650; SMC Conditions</th>
                        <th colspan="2" class="hg-ema sep-ema">&#128200; EMA-20</th>
                    </tr>
                    <tr class="hdr-cols">
                        <th>#</th>
                        <th style="text-align:left;padding-left:14px;">Symbol</th>
                        <th>Date</th>
                        <th>Close</th>
                        <th class="sep-sig hg-sig">Signal</th>
                        <th class="hg-sig">Trend</th>
                        <th class="sep-smc">Vol Spike<br><span style="font-size:7px;opacity:.5;font-weight:400;">&gt;1.5× avg</span></th>
                        <th>Sweep<br><span style="font-size:7px;opacity:.5;font-weight:400;">Low / High</span></th>
                        <th>FVG<br><span style="font-size:7px;opacity:.5;font-weight:400;">Bull / Bear</span></th>
                        <th class="sep-ema hg-ema">EMA-20</th>
                        <th class="hg-ema">vs Close</th>
                    </tr>
                </thead>
                <tbody id="ios-tbody">
                    <tr><td colspan="11">
                        <div class="ios-empty"><i class="fas fa-chart-area"></i>Select date range and click Load</div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
const SIGNALS_URL = '{{ route("quantedge-smc.signals") }}';
const TODAY_STR   = '{{ now()->toDateString() }}';

let curTf      = '15min';
let curFilter  = 'ALL';
let symCache   = {};
let allResults = [];

$(document).ready(function () {
    updateDateBadge();
    loadData();
});

/* ── Timeframe ───────────────────────────────────────────────────── */
function setTf(tf, btn) {
    curTf = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    $('#ios-card-title').text('⬡ QuantEdge Smart Money · ' + tf.toUpperCase());
    loadData();
}

/* ── Date helpers ────────────────────────────────────────────────── */
function shiftDate(which, d) {
    const id = which === 'from' ? 'from-date' : 'to-date';
    const p  = document.getElementById(id);
    const dt = new Date(p.value); dt.setDate(dt.getDate() + d);
    const s  = dt.toISOString().split('T')[0];
    if (s > TODAY_STR) return;
    p.value = s;
    syncDates();
}
function syncDates() {
    const fp = document.getElementById('from-date');
    const tp = document.getElementById('to-date');
    if (fp.value > TODAY_STR) fp.value = TODAY_STR;
    if (tp.value > TODAY_STR) tp.value = TODAY_STR;
    if (fp.value > tp.value) tp.value = fp.value;
    tp.min = fp.value;
    updateDateBadge();
}
function goToday() {
    document.getElementById('from-date').value = TODAY_STR;
    document.getElementById('to-date').value   = TODAY_STR;
    syncDates();
    loadData();
}
function updateDateBadge() {
    const from = document.getElementById('from-date').value;
    const to   = document.getElementById('to-date').value;
    const el   = document.getElementById('date-badge');
    if (from === TODAY_STR && to === TODAY_STR) {
        el.innerHTML = '<span class="dbadge live">&#11044; Live</span>';
    } else if (from !== to) {
        el.innerHTML = '<span class="dbadge range">&#128197; Range</span>';
    } else {
        el.innerHTML = '<span class="dbadge hist">&#9724; Historical</span>';
    }
}

/* ── Symbols ─────────────────────────────────────────────────────── */
function rebuildSym(syms) {
    const sel  = document.getElementById('ios-sym');
    const prev = sel.value;
    sel.innerHTML = '<option value="ALL">— All Symbols —</option>'
        + syms.map(s => `<option value="${s}"${s===prev?' selected':''}>${s}</option>`).join('');
}

/* ── Load ────────────────────────────────────────────────────────── */
function loadData() {
    const from = document.getElementById('from-date').value;
    const to   = document.getElementById('to-date').value;
    const sym  = document.getElementById('ios-sym').value || 'ALL';
    updateDateBadge();
    showLoading();

    $.ajax({
        url: SIGNALS_URL, type: 'GET',
        data: { timeframe: curTf, from_date: from, to_date: to, symbol: sym },
        success(res) {
            if (res.no_config) { showWarn(res.message); emptyTable(); return; }
            if (!res.success)  { emptyTable(res.message); return; }
            hideWarn();

            if (res.symbols && res.symbols.length) rebuildSym(res.symbols);

            allResults = res.results || [];
            renderStats(res.summary);
            renderTable(allResults);
            applyFilter(curFilter);

            $('#ios-card-info').text(res.results.length + ' row(s)' + (res.is_range ? ' · range' : ''));
            $('#ios-upd').text('Updated ' + new Date().toLocaleTimeString());
        },
        error(xhr) { emptyTable('&#9888; ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error')); }
    });
}

/* ── Render ──────────────────────────────────────────────────────── */
function renderTable(rows) {
    if (!rows || !rows.length) { emptyTable('No signals found.'); return; }
    var html = '';

    rows.forEach(function (r, i) {
        const sig    = r.signal || 'NO_TRADE';
        const zebra  = i % 2 === 0 ? 'row-even' : 'row-odd';
        const rowCls = zebra
            + (sig === 'BUY'           ? ' row-buy'  : '')
            + (sig === 'SELL'          ? ' row-sell' : '')
            + (['BUY_PULLBACK','SELL_PULLBACK'].includes(sig) ? ' row-pb' : '');

        const sigBadge = {
            BUY:           '<span class="sig-buy">&#8679; BUY</span>',
            SELL:          '<span class="sig-sell">&#8681; SELL</span>',
            BUY_PULLBACK:  '<span class="sig-buyp">&#8629; BUY PB</span>',
            SELL_PULLBACK: '<span class="sig-sellp">&#8629; SELL PB</span>',
            NO_TRADE:      '<span class="sig-nt">— NO TRADE</span>',
        }[sig] || '<span class="sig-nt">—</span>';

        const trendBadge = {
            UPTREND:   '<span class="trend-up">&#8593; UP</span>',
            DOWNTREND: '<span class="trend-dn">&#8595; DOWN</span>',
            SIDEWAYS:  '<span class="trend-side">&#8594; SIDE</span>',
        }[r.trend] || dash();

        // Sweep: show both low & high compactly
        const sweepCell = (r.liquidity_sweep_low  ? '<span class="b-yes">L</span> ' : '<span class="b-no">L</span> ')
                        + (r.liquidity_sweep_high ? '<span class="b-wrn">H</span>'  : '<span class="b-no">H</span>');

        // FVG
        const fvgCell = (r.fvg_bullish ? '<span class="b-yes">&#8679;</span> ' : '<span class="b-no">&#8679;</span> ')
                      + (r.fvg_bearish ? '<span class="b-wrn">&#8681;</span>'   : '<span class="b-no">&#8681;</span>');

        // EMA vs close
        let emaVs = dash();
        if (r.last_close && r.ema20) {
            emaVs = r.last_close > r.ema20
                ? '<span style="color:var(--green);font-size:10px;font-weight:800;">&#9650; ABV</span>'
                : '<span style="color:var(--red);font-size:10px;font-weight:800;">&#9660; BLW</span>';
        }

        html +=
            '<tr class="' + rowCls + '" data-sig="' + sig + '">'
            + '<td class="c-num">' + (i+1) + '</td>'
            + '<td style="text-align:left;padding-left:14px;"><span class="sym-badge">' + esc(r.symbol) + '</span></td>'
            + '<td><span class="date-chip">' + esc(r.analysis_date || '') + '</span></td>'
            + '<td style="font-family:var(--mono);font-weight:700;color:var(--text-1);">'
            + (r.last_close ? '₹' + fmt(r.last_close) : dash()) + '</td>'
            + '<td class="sep-sig">' + sigBadge + '</td>'
            + '<td>' + trendBadge + '</td>'
            + '<td class="sep-smc">' + (r.volume_spike ? '<span class="b-yes">&#10003; YES</span>' : '<span class="b-no">&#10007;</span>') + '</td>'
            + '<td>' + sweepCell + '</td>'
            + '<td>' + fvgCell + '</td>'
            + '<td class="sep-ema" style="color:var(--sky);font-weight:700;">'
            + (r.ema20 ? '₹' + fmt(r.ema20) : dash()) + '</td>'
            + '<td>' + emaVs + '</td>'
            + '</tr>';
    });

    $('#ios-tbody').html(html);
}

/* ── Filter ──────────────────────────────────────────────────────── */
function filterSig(sig, btn) {
    curFilter = sig;
    document.querySelectorAll('#filter-pills .sp').forEach(b => {
        b.classList.remove('active','active-sell','active-pb','active-nt');
    });
    const cls = sig === 'SELL' || sig === 'SELL_PULLBACK' ? 'active-sell'
              : sig === 'BUY_PULLBACK' ? 'active-pb'
              : sig === 'NO_TRADE'     ? 'active-nt'
              : 'active';
    btn.classList.add(cls);
    applyFilter(sig);
}

function applyFilter(sig) {
    document.querySelectorAll('#ios-tbody tr[data-sig]').forEach(row => {
        row.style.display = (sig === 'ALL' || row.dataset.sig === sig) ? '' : 'none';
    });
}

/* ── Stats ───────────────────────────────────────────────────────── */
function renderStats(s) {
    $('#st-total').text(s.total);
    $('#st-buy').text(s.buy);
    $('#st-sell').text(s.sell);
    $('#st-pb').text((s.buy_pullback || 0) + (s.sell_pullback || 0));
    $('#st-nt').text(s.no_trade);
    $('#ios-stats').show();
}

/* ── Helpers ─────────────────────────────────────────────────────── */
function fmt(v)  { return v != null ? Number(v).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 }) : '—'; }
function dash()  { return '<span style="color:rgba(255,255,255,.15);font-size:9px;">—</span>'; }
function esc(s)  { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showLoading() {
    $('#ios-tbody').html('<tr><td colspan="11"><div class="ios-loading"><div class="ios-spinner"></div><div class="ios-spin-txt">Running SMC analysis…</div></div></td></tr>');
    $('#ios-stats').hide();
}
function emptyTable(msg) {
    $('#ios-tbody').html('<tr><td colspan="11"><div class="ios-empty"><i class="fas fa-chart-area"></i>' + (msg || 'Select date range and click Load') + '</div></td></tr>');
    $('#ios-stats').hide();
}
function showWarn(msg) { $('#ios-warn').show(); $('#ios-warn-msg').text(msg||''); }
function hideWarn()    { $('#ios-warn').hide(); }
</script>
@endpush