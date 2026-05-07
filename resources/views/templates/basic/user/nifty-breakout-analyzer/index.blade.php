@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');
:root {
    --navy-900:#0a0f1e; --navy-800:#0d1428; --navy-700:#111b35;
    --border:rgba(255,255,255,0.07);
    --amber:#f59e0b; --emerald:#10b981; --rose:#f43f5e;
    --sky:#38bdf8; --violet:#8b5cf6; --orange:#f97316;
    --text-1:rgba(255,255,255,0.92); --text-2:rgba(255,255,255,0.55); --text-3:rgba(255,255,255,0.25);
    --mono:'JetBrains Mono',monospace; --display:'Rajdhani',sans-serif;
    --accent:#f59e0b; /* amber — breakout theme */
}
body { background:var(--navy-900); }

/* ── Page Header ─────────────────────────────────────────────────── */
.ios-header {
    background:linear-gradient(135deg,#0d1428 0%,#1a2200 50%,#0d1428 100%);
    border:1px solid var(--border);
    border-bottom:2px solid var(--accent);
    border-radius:14px; padding:20px 28px; margin-bottom:18px;
    position:relative; overflow:hidden;
}
.ios-header::before {
    content:'BREAKOUT';
    position:absolute; right:24px; top:50%; transform:translateY(-50%);
    font-family:var(--display); font-size:72px; font-weight:700;
    color:rgba(245,158,11,0.05); letter-spacing:6px;
    pointer-events:none; user-select:none;
}
.ios-title { font-family:var(--display); font-size:22px; font-weight:700; color:var(--text-1); margin:0; }
.ios-title span {
    background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3);
    color:var(--accent); font-size:10px; font-weight:700;
    padding:2px 9px; border-radius:4px; margin-left:8px;
    vertical-align:middle; letter-spacing:2px;
}
.ios-title span.sig { background:rgba(56,189,248,0.12); border-color:rgba(56,189,248,0.3); color:var(--sky); }
.ios-sub { font-family:var(--mono); font-size:11px; color:var(--text-2); margin:7px 0 0; }
.lp {
    display:inline-block; font-family:var(--mono); font-size:10px;
    font-weight:600; padding:2px 9px; border-radius:4px; margin:3px 2px;
}
.lp-thresh { background:rgba(245,158,11,0.10); border:1px solid rgba(245,158,11,0.22); color:var(--amber); }
.lp-ce     { background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.25); color:var(--emerald); }
.lp-pe     { background:rgba(244,63,94,0.12);  border:1px solid rgba(244,63,94,0.25);  color:var(--rose); }
.lp-buy    { background:rgba(56,189,248,0.10); border:1px solid rgba(56,189,248,0.22); color:var(--sky); }

/* ── Controls ────────────────────────────────────────────────────── */
.ios-controls {
    background:var(--navy-800); border:1px solid var(--border);
    border-radius:12px; padding:14px 20px; margin-bottom:16px;
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
}
.ctrl-label {
    font-family:var(--display); font-size:10px; font-weight:700;
    color:var(--text-3); letter-spacing:1.5px; text-transform:uppercase;
}
.ctrl-sep { width:1px; height:28px; background:var(--border); flex-shrink:0; }
.tf-group { display:flex; gap:4px; }
.tf-btn {
    font-family:var(--display); font-size:12px; font-weight:700;
    padding:6px 15px; border-radius:7px; border:1px solid var(--border);
    background:transparent; color:var(--text-2); cursor:pointer; transition:.15s;
}
.tf-btn:hover { border-color:rgba(245,158,11,0.4); color:var(--amber); }
.tf-btn.active { background:rgba(245,158,11,0.15); border-color:var(--accent); color:var(--accent); }
.ios-date {
    background:rgba(255,255,255,0.06); border:1px solid var(--border);
    border-radius:8px; color:var(--text-1); padding:5px 10px;
    font-family:var(--mono); font-size:11px; outline:none;
}
.ios-date::-webkit-calendar-picker-indicator { filter:invert(.55); cursor:pointer; }
.ios-select {
    background:rgba(255,255,255,0.06); border:1px solid var(--border);
    color:var(--text-1); border-radius:8px; padding:5px 10px;
    font-family:var(--display); font-size:12px; font-weight:600;
    cursor:pointer; outline:none; min-width:130px;
}
.ios-select option { background:#0d1428; }
.ios-range-wrap { display:flex; flex-direction:column; align-items:center; min-width:100px; }
.ios-range {
    width:100%; accent-color:var(--accent); margin-top:4px;
    -webkit-appearance:none; height:4px; border-radius:2px;
    background:rgba(255,255,255,0.1); cursor:pointer;
}
.thresh-val {
    font-family:var(--mono); font-size:18px; font-weight:700;
    color:var(--accent); text-align:center; line-height:1; display:block;
}
.ios-btn {
    background:var(--accent); color:#000; border:none; border-radius:8px;
    padding:7px 22px; font-family:var(--display); font-size:13px;
    font-weight:800; cursor:pointer; transition:.15s;
}
.ios-btn:hover { background:#fbbf24; }
.ios-reset-btn {
    background:rgba(255,255,255,0.07); color:var(--text-2);
    border:1px solid var(--border); border-radius:8px; padding:6px 16px;
    font-family:var(--display); font-size:12px; font-weight:700; cursor:pointer;
}
.ml-auto { margin-left:auto; }
.last-upd { font-family:var(--mono); font-size:9px; color:var(--text-3); }

/* ── Warning ─────────────────────────────────────────────────────── */
.ios-warn {
    background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3);
    border-radius:10px; padding:14px 18px; margin-bottom:14px;
    font-family:var(--display); font-size:13px; color:var(--amber); display:none;
}

/* ── Stats ───────────────────────────────────────────────────────── */
.ios-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.stat-box {
    background:var(--navy-800); border:1px solid var(--border);
    border-radius:10px; padding:12px 16px; min-width:110px; flex:1;
}
.stat-box small {
    display:block; font-family:var(--display); font-size:9px; font-weight:700;
    text-transform:uppercase; letter-spacing:1px; color:var(--text-3); margin-bottom:4px;
}
.stat-box strong { display:block; font-family:var(--mono); font-size:1.2rem; font-weight:700; color:var(--text-1); }
.s-total  { border-left:3px solid var(--amber); }
.s-ce     { border-left:3px solid var(--emerald); }
.s-pe     { border-left:3px solid var(--rose); }
.s-syms   { border-left:3px solid var(--sky); }
.s-invest { border-left:3px solid var(--violet); }
.s-sig    { border-left:3px solid var(--orange); }

/* ── Main card ───────────────────────────────────────────────────── */
.ios-card {
    background:var(--navy-800); border:1px solid var(--border);
    border-radius:14px; overflow:hidden;
}
.ios-card-hdr {
    padding:14px 20px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px; background:var(--navy-700);
}
.ios-card-title { font-family:var(--display); font-size:14px; font-weight:700; color:var(--text-1); }

/* ── Table ───────────────────────────────────────────────────────── */
.ios-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.ios-table { width:100%; border-collapse:collapse; font-family:var(--mono); min-width:1100px; }
.ios-table thead tr.hdr-grp th {
    padding:9px 10px 5px; text-align:center;
    font-family:var(--display); font-size:9px; font-weight:800;
    letter-spacing:1px; text-transform:uppercase;
    background:rgba(0,0,0,0.4); border-bottom:none; white-space:nowrap;
}
.ios-table thead tr.hdr-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:var(--display); font-size:8px; font-weight:700;
    letter-spacing:.3px; text-transform:uppercase;
    background:rgba(0,0,0,0.3); color:var(--text-3);
    border-bottom:2px solid var(--border); white-space:nowrap;
}
.ios-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid rgba(255,255,255,0.03);
    vertical-align:middle; white-space:nowrap; color:var(--text-2);
}
.ios-table tbody tr:hover { background:rgba(255,255,255,0.04) !important; }
.row-even { background:rgba(255,255,255,0.01); }
.row-odd  { background:rgba(0,0,0,0.1); }
.row-ce { background:rgba(16,185,129,0.03) !important; }
.row-pe { background:rgba(244,63,94,0.03)  !important; }

/* ── Column group separators ─────────────────────────────────────── */
.sep-nifty  { border-left:2px solid rgba(245,158,11,0.35) !important; }
.sep-option { border-left:2px solid rgba(56,189,248,0.35) !important; }
.sep-trade  { border-left:2px solid rgba(16,185,129,0.35) !important; }
.hdr-nifty  { color:var(--amber) !important; }
.hdr-option { color:var(--sky) !important; }
.hdr-trade  { color:var(--emerald) !important; }

/* ── Cell content ────────────────────────────────────────────────── */
.c-num  { font-size:9px; color:var(--text-3); }
.c-date { font-size:11px; font-weight:700; color:var(--amber); }
.c-sym  { font-size:12px; font-weight:800; color:var(--sky); }
.c-sym small { display:block; font-size:8px; color:var(--text-3); font-weight:400; }
.c-price { font-size:11px; font-weight:700; color:var(--text-1); }
.c-time {
    display:inline-block; background:rgba(245,158,11,0.1);
    border:1px solid rgba(245,158,11,0.3); color:var(--amber);
    padding:2px 7px; border-radius:4px; font-size:10px; font-weight:700;
}
.c-time.buy {
    background:rgba(56,189,248,0.1); border-color:rgba(56,189,248,0.3); color:var(--sky);
}
.c-oi { font-size:10px; font-weight:700; color:var(--text-1); }
.c-oi small { display:block; font-size:8px; color:var(--text-3); }
.move-up   { color:#34d399; font-weight:700; }
.move-down { color:#fb7185; font-weight:700; }

.sig-ce {
    display:inline-block; background:rgba(16,185,129,0.2); color:#34d399;
    border:1px solid rgba(16,185,129,0.45); border-radius:6px;
    padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800;
}
.sig-pe {
    display:inline-block; background:rgba(244,63,94,0.2); color:#fb7185;
    border:1px solid rgba(244,63,94,0.45); border-radius:6px;
    padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800;
}
.c-invest { font-size:11px; font-weight:700; color:var(--violet); }

/* ── Group divider row ───────────────────────────────────────────── */
.group-row td {
    background:linear-gradient(90deg, rgba(245,158,11,0.08), transparent) !important;
    border-top:2px solid rgba(245,158,11,0.2) !important;
    padding:8px 12px !important;
    font-family:var(--display) !important;
    font-size:10px !important;
    color:rgba(245,158,11,0.75) !important;
    letter-spacing:.4px;
}

/* ── Loading / Empty ─────────────────────────────────────────────── */
.ios-loading {
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; padding:70px;
}
.ios-spinner {
    width:36px; height:36px; border:3px solid rgba(255,255,255,0.1);
    border-top:3px solid var(--accent); border-radius:50%;
    animation:iosspin 1s linear infinite;
}
@keyframes iosspin { to { transform:rotate(360deg); } }
.ios-spin-txt { color:var(--text-2); margin-top:12px; font-family:var(--display); font-size:13px; }
.ios-empty {
    text-align:center; padding:60px 20px;
    color:var(--text-3); font-family:var(--display); font-size:13px;
}
.ios-empty i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ─────────────────────────────────────────────────── --}}
    <div class="ios-header">
        <h4 class="ios-title">
            &#9670; NIFTY-Driven Multi-Symbol Breakout
            <span>NIFTY SIGNAL</span>
            <span class="sig">ALL SYMBOLS</span>
        </h4>
        <div class="ios-sub" style="margin-top:8px;">
            <span class="lp lp-thresh">Signal: NIFTY FUT 15-min candle HIGH/LOW vs 09:15 OPEN</span>
            <span class="lp lp-ce">HIGH ≥ OPEN + threshold → CE Breakout</span>
            <span class="lp lp-pe">LOW ≤ OPEN − threshold → PE Breakout</span>
            <span class="lp lp-buy">Buy at OPEN of NEXT candle after trigger</span>
        </div>
        <div class="ios-sub" style="margin-top:5px; color:var(--text-3);">
            Source: cp_fut_ohlc + cp_option_ohlc tables &nbsp;·&nbsp;
            Highest-OI strike per symbol &nbsp;·&nbsp;
            Config-scoped symbols &nbsp;·&nbsp;
            First breakout candle only per day
        </div>
    </div>

    {{-- ── Controls ───────────────────────────────────────────────── --}}
    <div class="ios-controls">
        {{-- Timeframe --}}
        <span class="ctrl-label">TF</span>
        <div class="tf-group">
            <button class="tf-btn active" data-tf="15min" onclick="setTf('15min',this)">15 Min</button>
            <button class="tf-btn"        data-tf="30min" onclick="setTf('30min',this)">30 Min</button>
            <button class="tf-btn"        data-tf="1hr"   onclick="setTf('1hr',this)">1 Hour</button>
        </div>

        <div class="ctrl-sep"></div>

        {{-- Dates --}}
        <span class="ctrl-label">FROM</span>
        <input type="date" id="ios-from" class="ios-date"
               value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
        <span class="ctrl-label">TO</span>
        <input type="date" id="ios-to"   class="ios-date"
               value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">

        <div class="ctrl-sep"></div>

        {{-- Threshold slider --}}
        <span class="ctrl-label">THRESHOLD</span>
        <div class="ios-range-wrap">
            <span class="thresh-val" id="thresh-display">30</span>
            <input type="range" id="ios-thresh" class="ios-range"
                   min="5" max="300" step="5" value="30">
            <div style="display:flex;justify-content:space-between;width:100%;font-size:8px;color:var(--text-3);">
                <span>5</span><span>300</span>
            </div>
        </div>

        <div class="ctrl-sep"></div>

        {{-- Signal filter --}}
        <span class="ctrl-label">SIGNAL</span>
        <select id="ios-signal" class="ios-select">
            <option value="BOTH">Both CE + PE</option>
            <option value="CE">CE Only</option>
            <option value="PE">PE Only</option>
        </select>

        {{-- Symbol filter --}}
        <span class="ctrl-label">SYMBOL</span>
        <select id="ios-sym" class="ios-select">
            <option value="ALL">All Symbols</option>
        </select>

        {{-- Actions --}}
        <button class="ios-btn" onclick="runAnalysis()">&#9670; Analyze</button>
        <button class="ios-reset-btn" onclick="resetAll()">&#8630; Reset</button>

        <div class="ml-auto d-flex align-items-center" style="gap:12px;">
            <span id="ios-info" style="font-family:var(--mono);font-size:10px;color:var(--text-2);"></span>
            <span class="last-upd" id="ios-upd"></span>
        </div>
    </div>

    {{-- ── Warning ─────────────────────────────────────────────────── --}}
    <div class="ios-warn" id="ios-warn">&#9888; <span id="ios-warn-msg"></span></div>

    {{-- ── Stats ──────────────────────────────────────────────────── --}}
    <div class="ios-stats">
        <div class="stat-box s-total">
            <small>Total Trades</small>
            <strong id="st-total" style="color:var(--amber);">0</strong>
        </div>
        <div class="stat-box s-ce">
            <small>&#8679; CE Trades</small>
            <strong id="st-ce" style="color:var(--emerald);">0</strong>
        </div>
        <div class="stat-box s-pe">
            <small>&#8681; PE Trades</small>
            <strong id="st-pe" style="color:var(--rose);">0</strong>
        </div>
        <div class="stat-box s-syms">
            <small>Symbols Hit</small>
            <strong id="st-syms" style="color:var(--sky);">0</strong>
        </div>
        <div class="stat-box s-invest">
            <small>Total Investment</small>
            <strong id="st-invest" style="color:var(--violet); font-size:.95rem;">&#8377;0</strong>
        </div>
        <div class="stat-box s-sig">
            <small>Signal Days</small>
            <strong id="st-sigdays" style="color:var(--orange);">0</strong>
        </div>
    </div>

    {{-- ── Trade Table ─────────────────────────────────────────────── --}}
    <div class="ios-card">
        <div class="ios-card-hdr">
            <span class="ios-card-title" id="ios-card-title">
                &#9670; NIFTY Breakout Signal Trades &nbsp;·&nbsp; 15 Min &nbsp;·&nbsp; Threshold: 30 pts
            </span>
            <span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);"
                  id="ios-card-info"></span>
        </div>

        <div class="ios-tscroll">
            <table class="ios-table">
                <thead>
                    <tr class="hdr-grp">
                        <th colspan="4">Info</th>
                        <th colspan="4" class="hdr-nifty sep-nifty">
                            &#9651; NIFTY FUT Signal
                        </th>
                        <th colspan="4" class="hdr-option sep-option">
                            &#9678; Option Details
                        </th>
                        <th colspan="3" class="hdr-trade sep-trade">
                            &#128200; Trade
                        </th>
                    </tr>
                    <tr class="hdr-cols">
                        {{-- Info --}}
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>Signal<br><span style="font-size:7px;opacity:.5;font-weight:400;">CE / PE</span></th>
                        {{-- NIFTY --}}
                        <th class="sep-nifty">NIFTY Open<br><span style="font-size:7px;opacity:.5;font-weight:400;">09:15 open</span></th>
                        <th>Trigger Val<br><span style="font-size:7px;opacity:.5;font-weight:400;">High / Low</span></th>
                        <th>Trigger Time<br><span style="font-size:7px;opacity:.5;font-weight:400;">candle</span></th>
                        <th>NIFTY Move<br><span style="font-size:7px;opacity:.5;font-weight:400;">pts</span></th>
                        {{-- Option --}}
                        <th class="sep-option">Strike<br><span style="font-size:7px;opacity:.5;font-weight:400;">highest OI</span></th>
                        <th>OI<br><span style="font-size:7px;opacity:.5;font-weight:400;">at buy time</span></th>
                        <th>Expiry</th>
                        <th>Buy Time<br><span style="font-size:7px;opacity:.5;font-weight:400;">next candle</span></th>
                        {{-- Trade --}}
                        <th class="sep-trade">Buy Price &#8377;<br><span style="font-size:7px;opacity:.5;font-weight:400;">open of buy candle</span></th>
                        <th>Lot Size</th>
                        <th>Investment &#8377;</th>
                    </tr>
                </thead>
                <tbody id="ios-tbody">
                    <tr>
                        <td colspan="15">
                            <div class="ios-empty">
                                <i class="fas fa-bolt"></i>
                                Select date range and click <strong>Analyze</strong>
                            </div>
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
const ANALYZE_URL = '{{ route("nifty-breakout-analyzer.analyze") }}';
const SYM_URL     = '{{ route("nifty-breakout-analyzer.symbols") }}';
const todayStr    = '{{ now()->toDateString() }}';

let curTf   = '15min';
let symCache = {};

/* ── Init ────────────────────────────────────────────────────────── */
$(document).ready(function () {
    loadSymbols();
    $('#ios-thresh').on('input', function () {
        $('#thresh-display').text($(this).val());
        $('#ios-card-title').text(buildCardTitle());
    });
});

/* ── Timeframe switch ────────────────────────────────────────────── */
function setTf(tf, btn) {
    curTf = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    $('#ios-card-title').text(buildCardTitle());
    loadSymbols();
}

function buildCardTitle() {
    return '⬡ NIFTY Breakout Signal Trades · ' + curTf.toUpperCase() + ' · Threshold: ' + $('#ios-thresh').val() + ' pts';
}

/* ── Load symbols ────────────────────────────────────────────────── */
function loadSymbols() {
    if (symCache[curTf]) { rebuildSym(symCache[curTf]); return; }
    $.get(SYM_URL, { timeframe: curTf }, function (res) {
        if (res.no_config) { showWarn(res.message || ''); rebuildSym([]); return; }
        hideWarn();
        symCache[curTf] = res.symbols || [];
        rebuildSym(symCache[curTf]);
    });
}

function rebuildSym(syms) {
    const sel = document.getElementById('ios-sym');
    sel.innerHTML = '<option value="ALL">All Symbols</option>' +
        syms.map(s => `<option value="${s}">${s}</option>`).join('');
}

/* ── Run analysis ────────────────────────────────────────────────── */
function runAnalysis() {
    const from   = $('#ios-from').val();
    const to     = $('#ios-to').val();
    const thresh = $('#ios-thresh').val();
    const signal = $('#ios-signal').val();
    const sym    = $('#ios-sym').val();

    if (!from || !to) { alert('Please select both dates.'); return; }

    hideWarn(); resetStats();
    $('#ios-card-title').text(buildCardTitle());
    $('#ios-tbody').html(`<tr><td colspan="15">
        <div class="ios-loading">
            <div class="ios-spinner"></div>
            <div class="ios-spin-txt">Scanning NIFTY FUT for breakout signals…</div>
        </div>
    </td></tr>`);

    $.ajax({
        url: ANALYZE_URL, type: 'GET',
        data: { timeframe: curTf, from_date: from, to_date: to,
                threshold: thresh, filter: signal, symbol_filter: sym },
        success(res) {
            if (res.no_config) { showWarn(res.message); emptyTable(); return; }
            if (!res.success || !res.data || !res.data.length) {
                emptyTable(res.message || 'No signals found.');
                return;
            }
            renderTable(res.data);
            updateStats(res);
            $('#ios-info').html(
                `CE: <span style="color:var(--emerald)">${res.ce_count}</span>` +
                ` &nbsp;·&nbsp; PE: <span style="color:var(--rose)">${res.pe_count}</span>` +
                ` &nbsp;·&nbsp; TF: <span style="color:var(--amber)">${res.timeframe}</span>`
            );
            $('#ios-card-info').text(res.message);
            $('#ios-upd').text('Updated ' + new Date().toLocaleTimeString());
        },
        error(xhr) {
            emptyTable('&#9888; ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error'));
        }
    });
}

/* ── Render table ────────────────────────────────────────────────── */
function renderTable(data) {
    let html       = '';
    let rowNum     = 1;
    let lastGroup  = null;

    data.forEach(function (r, i) {
        const groupKey = r.date + '_' + r.signal_type + '_' + r.trigger_time;

        if (groupKey !== lastGroup) {
            const moveSign = r.nifty_move >= 0 ? '+' : '';
            const sigLabel = r.signal_type === 'CE'
                ? '&#8679; CE BREAKOUT TRIGGERED'
                : '&#8681; PE BREAKOUT TRIGGERED';
            html += `<tr class="group-row">
                <td colspan="15">
                    ${esc(r.date)} &nbsp;|&nbsp; ${sigLabel}
                    &nbsp;|&nbsp; NIFTY trigger candle: <strong>${esc(r.trigger_time)}</strong>
                    &nbsp;→&nbsp; Buy at: <strong>${esc(r.buy_time)}</strong> (next candle)
                    &nbsp;|&nbsp; NIFTY move: <strong>${moveSign}${f(r.nifty_move)} pts</strong>
                    &nbsp;(open ₹${f(r.nifty_open)} → trigger ${r.signal_type === 'CE' ? 'HIGH' : 'LOW'} ₹${f(r.nifty_trigger)})
                </td>
            </tr>`;
            lastGroup = groupKey;
        }

        const isCe     = r.signal_type === 'CE';
        const rowCls   = (isCe ? 'row-ce' : 'row-pe') + ' ' + (i % 2 === 0 ? 'row-even' : 'row-odd');
        const sigBadge = isCe
            ? '<span class="sig-ce">&#8679; CE</span>'
            : '<span class="sig-pe">&#8681; PE</span>';
        const moveCls  = r.nifty_move >= 0 ? 'move-up' : 'move-down';
        const moveSign = r.nifty_move >= 0 ? '+' : '';

        html += `<tr class="${rowCls}">
            <td class="c-num">${rowNum++}</td>
            <td class="c-date">${esc(r.date)}</td>
            <td class="c-sym">${esc(r.symbol)}<small>${esc(r.expiry_date || '')}</small></td>
            <td>${sigBadge}</td>
            <td class="sep-nifty c-price" style="color:var(--amber);">₹${f(r.nifty_open)}</td>
            <td class="c-price" style="color:var(--amber);">₹${f(r.nifty_trigger)}</td>
            <td><span class="c-time">${esc(r.trigger_time)}</span></td>
            <td><strong class="${moveCls}">${moveSign}${f(r.nifty_move)}</strong></td>
            <td class="sep-option" style="color:var(--amber);font-weight:700;">₹${nInt(r.strike)}</td>
            <td class="c-oi">${fmtOI(r.strike_oi)}</td>
            <td style="font-size:9px;color:var(--text-3);">${esc(r.expiry_date || '—')}</td>
            <td><span class="c-time buy">${esc(r.buy_time)}</span></td>
            <td class="sep-trade" style="color:#34d399;font-weight:700;">₹${f(r.buy_price)}</td>
            <td style="color:var(--text-2);">${r.lot_size}</td>
            <td class="c-invest">₹${numFmt(r.investment)}</td>
        </tr>`;
    });

    if (!html) emptyTable('No results match your filters.');
    else $('#ios-tbody').html(html);
}

/* ── Stats ───────────────────────────────────────────────────────── */
function updateStats(res) {
    $('#st-total').text(res.total_records   || 0);
    $('#st-ce').text(res.ce_count           || 0);
    $('#st-pe').text(res.pe_count           || 0);
    $('#st-syms').text(res.symbols_hit      || 0);
    $('#st-invest').text('₹' + numFmt(res.total_investment || 0));
    $('#st-sigdays').text(res.signal_count  || 0);
}
function resetStats() {
    ['st-total','st-ce','st-pe','st-syms','st-sigdays'].forEach(id => $('#' + id).text('0'));
    $('#st-invest').text('₹0');
}

/* ── Helpers ─────────────────────────────────────────────────────── */
function f(v)       { return parseFloat(v || 0).toFixed(2); }
function numFmt(v)  { return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function nInt(v)    { return Number(v || 0).toLocaleString('en-IN'); }
function fmtOI(v)   {
    const n = Number(v) || 0;
    if (n >= 1e7) return (n / 1e7).toFixed(2) + ' Cr';
    if (n >= 1e5) return (n / 1e5).toFixed(2) + ' L';
    if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
    return n.toLocaleString('en-IN');
}
function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function emptyTable(msg) {
    $('#ios-tbody').html(`<tr><td colspan="15"><div class="ios-empty"><i class="fas fa-bolt"></i>${msg || 'Select dates and click Analyze'}</div></td></tr>`);
}
function showWarn(msg) { $('#ios-warn').show(); $('#ios-warn-msg').text(msg || ''); }
function hideWarn()    { $('#ios-warn').hide(); }

/* ── Reset ───────────────────────────────────────────────────────── */
function resetAll() {
    $('#ios-from, #ios-to').val(todayStr);
    $('#ios-thresh').val(30); $('#thresh-display').text('30');
    $('#ios-signal').val('BOTH');
    document.getElementById('ios-sym').value = 'ALL';
    resetStats(); emptyTable(); $('#ios-info, #ios-card-info').text('');
    hideWarn();
    $('#ios-card-title').text('⬡ NIFTY Breakout Signal Trades · 15 MIN · Threshold: 30 pts');
}
</script>
@endpush