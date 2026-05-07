@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');
:root {
    --navy-900:#0a0f1e; --navy-800:#0d1428; --navy-700:#111b35;
    --border:rgba(255,255,255,0.07);
    --call:#00ff88; --call-dim:rgba(0,255,136,0.10); --call-bdr:rgba(0,255,136,0.28);
    --put:#ff4060;  --put-dim:rgba(255,64,96,0.10);  --put-bdr:rgba(255,64,96,0.28);
    --wait:#f0b429; --wait-dim:rgba(240,180,41,0.08); --wait-bdr:rgba(240,180,41,0.28);
    --trap:#c084fc; --trap-dim:rgba(192,132,252,0.10); --trap-bdr:rgba(192,132,252,0.28);
    --text-1:rgba(255,255,255,0.92); --text-2:rgba(255,255,255,0.55); --text-3:rgba(255,255,255,0.25);
    --mono:'JetBrains Mono',monospace; --display:'Rajdhani',sans-serif;
}

.ios-header {
    background:linear-gradient(135deg,#0d1428 0%,#1a1500 50%,#0d1428 100%);
    border:1px solid var(--border); border-bottom:2px solid var(--wait);
    border-radius:14px; padding:20px 28px; margin-bottom:18px;
    position:relative; overflow:hidden;
}
.ios-header::before {
    content:'FLOW'; position:absolute; right:24px; top:50%; transform:translateY(-50%);
    font-family:var(--display); font-size:80px; font-weight:700;
    color:rgba(240,180,41,0.04); letter-spacing:6px; pointer-events:none; user-select:none;
}
.ios-title { font-family:var(--display); font-size:22px; font-weight:700; color:var(--text-1); margin:0; }
.tag { font-size:10px; font-weight:700; padding:2px 9px; border-radius:4px; margin-left:6px; vertical-align:middle; letter-spacing:1.5px; }
.tag-call { background:var(--call-dim); border:1px solid var(--call-bdr); color:var(--call); }
.tag-put  { background:var(--put-dim);  border:1px solid var(--put-bdr);  color:var(--put); }
.tag-trap { background:var(--trap-dim); border:1px solid var(--trap-bdr); color:var(--trap); }
.ios-sub  { font-family:var(--mono); font-size:11px; color:var(--text-2); margin:6px 0 0; }
.lp { display:inline-block; font-family:var(--mono); font-size:10px; padding:2px 9px; border-radius:4px; margin:2px; }
.lp-w { background:var(--wait-dim); border:1px solid var(--wait-bdr); color:var(--wait); }

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
.tf-btn:hover  { border-color:var(--wait-bdr); color:var(--wait); }
.tf-btn.active { background:var(--wait-dim); border-color:var(--wait); color:var(--wait); }
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
.ios-btn { background:var(--wait); color:#000; border:none; border-radius:8px; padding:7px 22px; font-family:var(--display); font-size:13px; font-weight:800; cursor:pointer; }
.ios-btn:hover { background:#fbbf24; }
.auto-btn { background:rgba(255,255,255,0.07); color:var(--text-2); border:1px solid var(--border); border-radius:8px; padding:6px 14px; font-family:var(--display); font-size:10px; font-weight:700; cursor:pointer; }
.auto-btn.on { border-color:var(--call-bdr); color:var(--call); }
.sp-wrap { display:flex; gap:3px; flex-wrap:wrap; }
.sp { padding:4px 12px; border-radius:20px; font-family:var(--display); font-size:10px; font-weight:700; cursor:pointer; border:1px solid var(--border); background:var(--navy-800); color:var(--text-2); transition:.15s; }
.sp:hover       { border-color:var(--wait-bdr); color:var(--wait); }
.sp.active      { background:var(--wait-dim); border-color:var(--wait); color:var(--wait); }
.sp.active-call { background:var(--call-dim); border-color:var(--call-bdr); color:var(--call); }
.sp.active-put  { background:var(--put-dim);  border-color:var(--put-bdr);  color:var(--put); }
.ml-auto { margin-left:auto; }
.last-upd { font-family:var(--mono); font-size:9px; color:var(--text-3); }
.dbadge { font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; }
.dbadge.live { background:var(--call-dim); color:var(--call); border:1px solid var(--call-bdr); }
.dbadge.hist { background:var(--wait-dim); color:var(--wait); border:1px solid var(--wait-bdr); }

.ios-warn { background:rgba(240,180,41,0.08); border:1px solid rgba(240,180,41,0.28); border-radius:10px; padding:12px 18px; margin-bottom:14px; font-family:var(--display); font-size:13px; color:var(--wait); display:none; }

.ios-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.stat-box { background:var(--navy-800); border:1px solid var(--border); border-radius:10px; padding:12px 16px; min-width:100px; flex:1; }
.stat-box small { display:block; font-family:var(--display); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-3); margin-bottom:4px; }
.stat-box strong { display:block; font-family:var(--mono); font-size:1.2rem; font-weight:700; }
.s-call  { border-left:3px solid var(--call); }
.s-put   { border-left:3px solid var(--put); }
.s-trap  { border-left:3px solid var(--trap); }
.s-wait  { border-left:3px solid var(--wait); }
.s-total { border-left:3px solid rgba(56,189,248,0.6); }

.ios-card { background:var(--navy-800); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
.ios-card-hdr { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; background:var(--navy-700); }
.ios-card-title { font-family:var(--display); font-size:14px; font-weight:700; color:var(--text-1); }
.ios-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

.ios-table { width:100%; border-collapse:collapse; font-family:var(--mono); min-width:900px; }
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
.ios-table tbody tr:hover { background:rgba(255,255,255,0.04) !important; }
.row-even { background:rgba(255,255,255,0.008); }
.row-odd  { background:rgba(0,0,0,0.1); }
.row-call { border-left:2px solid var(--call) !important; background:rgba(0,255,136,0.03) !important; }
.row-put  { border-left:2px solid var(--put)  !important; background:rgba(255,64,96,0.03)  !important; }
.row-wait { border-left:2px solid transparent; opacity:.7; }
.sep-trade { border-left:2px solid rgba(0,255,136,0.25) !important; }
.sep-sig   { border-left:2px solid rgba(240,180,41,0.25) !important; }
.hg-trade  { color:var(--call) !important; }
.hg-sig    { color:var(--wait) !important; }

.c-num { font-size:9px; color:var(--text-3); }
.sym-badge { display:inline-block; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:800; background:rgba(255,255,255,0.07); color:var(--text-1); border:1px solid var(--border); }
.sig-call { display:inline-block; background:var(--call-dim); color:var(--call); border:1px solid var(--call-bdr); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-put  { display:inline-block; background:var(--put-dim);  color:var(--put);  border:1px solid var(--put-bdr);  border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-wait { display:inline-block; background:rgba(255,255,255,0.04); color:rgba(255,255,255,0.2); border:1px solid var(--border); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:9px; }
.sig-nd   { display:inline-block; color:rgba(255,255,255,0.15); font-size:9px; font-family:var(--display); }
.score-wrap  { display:flex; align-items:center; gap:5px; justify-content:center; }
.score-num   { font-size:11px; font-weight:800; min-width:16px; }
.score-track { width:44px; height:3px; background:rgba(255,255,255,0.08); border-radius:2px; overflow:hidden; }
.score-fill  { height:100%; border-radius:2px; }
.sig-dots    { display:flex; align-items:center; gap:3px; justify-content:center; flex-wrap:wrap; }
.sd          { width:7px; height:7px; border-radius:50%; display:inline-block; }
.sd-on-call  { background:var(--call); box-shadow:0 0 4px var(--call); }
.sd-on-put   { background:var(--put);  box-shadow:0 0 4px var(--put); }
.sd-on-trap  { background:var(--trap); box-shadow:0 0 4px var(--trap); }
.sd-off      { background:rgba(255,255,255,0.1); }
.fd-bull { color:var(--call); font-size:10px; font-weight:800; }
.fd-bear { color:var(--put);  font-size:10px; font-weight:800; }
.fd-side { color:var(--text-3); font-size:10px; }

.ios-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px; }
.ios-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,0.1); border-top:3px solid var(--wait); border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.ios-spin-txt { color:var(--text-2); margin-top:12px; font-family:var(--display); font-size:13px; }
.ios-empty { text-align:center; padding:60px 20px; color:var(--text-3); font-family:var(--display); font-size:13px; }
.ios-empty i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    <div class="ios-header">
        <h4 class="ios-title">
            &#9670; PrimeFlow — Option Scanner
            <span class="tag tag-call">BUY CALL</span>
            <span class="tag tag-put">BUY PUT</span>
            <span class="tag tag-trap">MM TRAP</span>
        </h4>
        <div class="ios-sub" style="margin-top:8px;">
            <span class="lp lp-w">Score threshold: {{ $thresh_hold ?? 6 }}/17 &nbsp;·&nbsp; Entry window: 10:30–14:30</span>
        </div>
        <div class="ios-sub" style="margin-top:5px; color:var(--text-3);">
            Premium Expansion(+3) &nbsp;·&nbsp; OI Build(+2) &nbsp;·&nbsp; Vol Spike(+2) &nbsp;·&nbsp;
            Futures Dir(+2) &nbsp;·&nbsp; Gamma(+2) &nbsp;·&nbsp; Momentum(+2) &nbsp;·&nbsp; MM Trap(+4)
            &nbsp;·&nbsp; Source: cp_option_ohlc + cp_fut_ohlc
        </div>
    </div>

    <div class="ios-controls">
        {{-- Timeframe — 15min active by default --}}
        <span class="ctrl-label">TF</span>
        <div class="tf-group">
            <button class="tf-btn active" data-tf="15min" onclick="setTf('15min',this)">15 Min</button>
            <button class="tf-btn"        data-tf="30min" onclick="setTf('30min',this)">30 Min</button>
            <button class="tf-btn"        data-tf="1hr"   onclick="setTf('1hr',this)">1 Hour</button>
        </div>

        <div class="ctrl-sep"></div>

        <span class="ctrl-label">DATE</span>
        <div style="display:flex;align-items:center;gap:4px;">
            <button class="dnav" onclick="shiftDate(-1)">&#8249;</button>
            <input type="date" id="ios-date" class="ios-date"
                   value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"
                   onchange="runScan()">
            <button class="dnav" onclick="shiftDate(1)">&#8250;</button>
            <button class="dnav today-btn" onclick="goToday()">TODAY</button>
            <span id="date-badge"></span>
        </div>

        <button class="ios-btn" onclick="runScan()">&#9670; Scan All</button>
        <button class="auto-btn" id="auto-btn" onclick="toggleAuto()">&#9654; Auto 60s</button>

        <div class="ctrl-sep"></div>

        <span class="ctrl-label">FILTER</span>
        <div class="sp-wrap" id="filter-pills">
            <div class="sp active"  data-f="ALL"     onclick="setFilter('ALL',this)">All</div>
            <div class="sp"         data-f="CALL"    onclick="setFilter('CALL',this)">&#8679; Call</div>
            <div class="sp"         data-f="PUT"     onclick="setFilter('PUT',this)">&#8681; Put</div>
            <div class="sp"         data-f="TRADE"   onclick="setFilter('TRADE',this)">&#128293; Trades</div>
            <div class="sp"         data-f="NOTRADE" onclick="setFilter('NOTRADE',this)">No Trade</div>
        </div>

        <div class="ml-auto">
            <span class="last-upd" id="ios-upd"></span>
        </div>
    </div>

    <div class="ios-warn" id="ios-warn">&#9888; <span id="ios-warn-msg"></span></div>

    <div class="ios-stats" id="ios-stats" style="display:none;">
        <div class="stat-box s-total"><small>Total</small><strong id="st-total" style="color:#38bdf8;">0</strong></div>
        <div class="stat-box s-call"><small>&#8679; Buy Call</small><strong id="st-call" style="color:var(--call);">0</strong></div>
        <div class="stat-box s-put"><small>&#8681; Buy Put</small><strong id="st-put" style="color:var(--put);">0</strong></div>
        <div class="stat-box s-trap"><small>&#128375; MM Traps</small><strong id="st-trap" style="color:var(--trap);">0</strong></div>
        <div class="stat-box s-wait"><small>No Trade</small><strong id="st-wait" style="color:var(--wait);">0</strong></div>
    </div>

    <div class="ios-card">
        <div class="ios-card-hdr">
            <span class="ios-card-title" id="ios-card-title">&#9670; PrimeFlow Scanner &nbsp;·&nbsp; 15 Min</span>
            <span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);" id="ios-card-info"></span>
        </div>
        <div class="ios-tscroll">
            <table class="ios-table">
                <thead>
                    <tr class="hdr-grp">
                        <th colspan="3">Info</th>
                        <th colspan="2" class="hg-trade sep-trade">&#128200; Trade</th>
                        <th colspan="4" class="hg-trade">Entry Details</th>
                        <th colspan="3" class="hg-sig sep-sig">&#9889; Signals</th>
                    </tr>
                    <tr class="hdr-cols">
                        <th>#</th>
                        <th style="text-align:left;padding-left:14px;">Symbol</th>
                        <th>Futures Dir</th>
                        <th class="sep-trade hg-trade">Signal</th>
                        <th class="hg-trade">Entry Time</th>
                        <th>Strike</th>
                        <th>Entry ₹</th>
                        <th>Target ₹</th>
                        <th>SL ₹</th>
                        <th class="sep-sig">Score /17</th>
                        <th>Active Signals</th>
                        <th>PCR</th>
                    </tr>
                </thead>
                <tbody id="ios-tbody">
                    <tr><td colspan="12">
                        <div class="ios-empty"><i class="fas fa-bolt"></i>Select date and click Scan All</div>
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
const SCAN_URL  = '{{ route("primeflow-scanner.data") }}';
const TODAY_STR = '{{ now()->toDateString() }}';

let curTf      = '15min';   // default 15min
let curFilter  = 'ALL';
let allResults = [];
let autoTimer  = null;

$(document).ready(function () {
    updateDateBadge();
    runScan();
});

function setTf(tf, btn) {
    curTf = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    $('#ios-card-title').text('⬡ PrimeFlow Scanner · ' + tf.toUpperCase());
    runScan();
}

function shiftDate(d) {
    const p  = document.getElementById('ios-date');
    const dt = new Date(p.value); dt.setDate(dt.getDate() + d);
    const s  = dt.toISOString().split('T')[0];
    if (s > TODAY_STR) return;
    p.value = s; updateDateBadge(); runScan();
}
function goToday() { document.getElementById('ios-date').value = TODAY_STR; updateDateBadge(); runScan(); }
function updateDateBadge() {
    const d = document.getElementById('ios-date').value;
    $('#date-badge').html(d === TODAY_STR
        ? '<span class="dbadge live">&#11044; Live</span>'
        : '<span class="dbadge hist">&#9724; Historical</span>');
}

function toggleAuto() {
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        $('#auto-btn').text('▶ Auto 60s').removeClass('on');
    } else {
        if (document.getElementById('ios-date').value !== TODAY_STR) return;
        autoTimer = setInterval(runScan, 60000);
        $('#auto-btn').text('■ Stop').addClass('on');
        runScan();
    }
}

function setFilter(f, btn) {
    curFilter = f;
    document.querySelectorAll('#filter-pills .sp').forEach(b => {
        b.classList.remove('active','active-call','active-put');
    });
    btn.classList.add(f === 'CALL' ? 'active-call' : f === 'PUT' ? 'active-put' : 'active');
    applyFilter();
}
function applyFilter() {
    document.querySelectorAll('#ios-tbody tr[data-sig]').forEach(row => {
        const sig = row.dataset.sig;
        let show = curFilter === 'ALL'
            || (curFilter === 'CALL'    && sig === 'BUY_CALL')
            || (curFilter === 'PUT'     && sig === 'BUY_PUT')
            || (curFilter === 'TRADE'   && (sig === 'BUY_CALL' || sig === 'BUY_PUT'))
            || (curFilter === 'NOTRADE' && sig === 'NO TRADE');
        row.style.display = show ? '' : 'none';
    });
}

function runScan() {
    const date = document.getElementById('ios-date').value;
    if (autoTimer && date !== TODAY_STR) toggleAuto();
    updateDateBadge();
    showLoading();

    $.ajax({
        url: SCAN_URL, type: 'GET',
        data: { timeframe: curTf, date: date },
        success(res) {
            if (res.no_config) { showWarn(res.message); emptyTable(); return; }
            if (!res.success)  { emptyTable(res.message); return; }
            hideWarn();
            allResults = res.results || [];
            renderStats(res);
            renderTable(allResults);
            applyFilter();
            $('#ios-card-info').text(res.total_symbols + ' symbols · scanned at ' + res.scanned_at);
            $('#ios-upd').text('Updated ' + new Date().toLocaleTimeString());
        },
        error(xhr) { emptyTable('&#9888; ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error')); }
    });
}

function renderTable(rows) {
    if (!rows || !rows.length) { emptyTable('No data.'); return; }
    var html = '';

    rows.forEach(function (r, i) {
        const sig     = r.signal || 'NO TRADE';
        const isCall  = sig === 'BUY_CALL';
        const isPut   = sig === 'BUY_PUT';
        const isFired = isCall || isPut;
        const rowCls  = isFired ? (isCall ? 'row-call' : 'row-put') : 'row-wait';

        const sigBadge = isCall ? '<span class="sig-call">&#8679; BUY CALL</span>'
                       : isPut  ? '<span class="sig-put">&#8681; BUY PUT</span>'
                       : (sig === 'NO DATA' || sig === 'ERROR') ? '<span class="sig-nd">— NO DATA</span>'
                       : '<span class="sig-wait">WAIT</span>';

        const fd = r.futures_dir || (r.signals && r.signals.futuresDir ? r.signals.futuresDir.direction : null);
        const futHtml = fd === 'BULLISH' ? '<span class="fd-bull">&#9650; BULL</span>'
                      : fd === 'BEARISH' ? '<span class="fd-bear">&#9660; BEAR</span>'
                      : '<span class="fd-side">&#9135; SIDE</span>';

        const timeHtml   = isFired && r.entry_time  ? '<span style="color:var(--wait);font-weight:700;">'    + esc(r.entry_time)   + '</span>' : dash();
        const strikeHtml = isFired && r.strike
            ? '<span style="color:var(--wait);font-weight:700;">' + fmt(r.strike) + '</span>'
            + (r.strike_sym ? '<br><span style="font-size:8px;color:var(--text-3);">' + esc(r.strike_sym) + '</span>' : '')
            : dash();
        const entryHtml  = isFired && r.entry_price ? '<span style="color:var(--text-1);font-weight:700;">&#8377;' + r.entry_price  + '</span>' : dash();
        const tgtHtml    = isFired && r.target      ? '<span style="color:var(--call);font-weight:700;">&#8377;' + r.target       + '</span>' : dash();
        const slHtml     = isFired && r.stoploss    ? '<span style="color:var(--put);font-weight:700;">&#8377;'  + r.stoploss     + '</span>' : dash();

        const score    = isFired ? (r.score || 0) : (r.peak_score || 0);
        const scorePct = Math.round((score / 17) * 100);
        const scoreCol = isCall ? 'var(--call)' : isPut ? 'var(--put)' : 'rgba(255,255,255,0.3)';
        const scoreHtml = `<div class="score-wrap">
            <span class="score-num" style="color:${scoreCol}">${score}</span>
            <div class="score-track"><div class="score-fill" style="width:${scorePct}%;background:${scoreCol};"></div></div>
        </div>`;

        const s        = r.signals || {};
        const dotsHtml = buildDots(s, isCall, isPut);
        const pcrHtml  = r.pcr != null ? '<span style="font-size:10px;color:var(--text-2);">' + r.pcr + '</span>' : dash();

        html +=
            '<tr class="' + rowCls + '" data-sig="' + esc(sig) + '">'
            + '<td class="c-num">' + (i + 1) + '</td>'
            + '<td style="text-align:left;padding-left:14px;"><span class="sym-badge">' + esc(r.symbol) + '</span></td>'
            + '<td>' + futHtml + '</td>'
            + '<td class="sep-trade">' + sigBadge + '</td>'
            + '<td>' + timeHtml + '</td>'
            + '<td>' + strikeHtml + '</td>'
            + '<td>' + entryHtml + '</td>'
            + '<td>' + tgtHtml + '</td>'
            + '<td>' + slHtml + '</td>'
            + '<td class="sep-sig">' + scoreHtml + '</td>'
            + '<td>' + dotsHtml + '</td>'
            + '<td>' + pcrHtml + '</td>'
            + '</tr>';
    });

    $('#ios-tbody').html(html);
}

function buildDots(s, isCall, isPut) {
    if (!s || !Object.keys(s).length) return dash();
    const checks = [
        { key: isCall ? 'cePremEx'  : 'pePremEx',  special: false },
        { key: isCall ? 'ceOiBuild' : 'peOiBuild',  special: false },
        { key: isCall ? 'ceVolSpike': 'peVolSpike', special: false },
        { key: 'futuresDir', special: true  },
        { key: 'gamma',      special2: true },
        { key: isCall ? 'ceAccel' : 'peAccel', special: false },
        { key: 'mmTrap', trap: true },
    ];
    return '<div class="sig-dots">' + checks.map(c => {
        let on = false;
        if (c.trap)    on = !!(s.mmTrap && (s.mmTrap.call_trap || s.mmTrap.put_trap));
        else if (c.special)  on = !!(s.futuresDir && (s.futuresDir.bullish || s.futuresDir.bearish));
        else if (c.special2) on = !!(s.gamma && s.gamma.active);
        else on = !!(s[c.key] && s[c.key].triggered);
        const cls = on ? (c.trap ? 'sd sd-on-trap' : (isCall ? 'sd sd-on-call' : (isPut ? 'sd sd-on-put' : 'sd sd-on-call'))) : 'sd sd-off';
        return '<span class="' + cls + '"></span>';
    }).join('') + '</div>';
}

function renderStats(res) {
    const R     = res.results || [];
    const calls = R.filter(r => r.signal === 'BUY_CALL').length;
    const puts  = R.filter(r => r.signal === 'BUY_PUT').length;
    const traps = R.filter(r => r.signals && r.signals.mmTrap && (r.signals.mmTrap.call_trap || r.signals.mmTrap.put_trap)).length;
    const waits = R.filter(r => r.signal === 'NO TRADE').length;
    $('#st-total').text(res.total_symbols || 0);
    $('#st-call').text(calls); $('#st-put').text(puts);
    $('#st-trap').text(traps); $('#st-wait').text(waits);
    $('#ios-stats').show();
}

function fmt(v)  { return v != null ? Number(v).toLocaleString('en-IN') : '—'; }
function dash()  { return '<span style="color:rgba(255,255,255,.15);font-size:9px;">—</span>'; }
function esc(s)  { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showLoading() {
    $('#ios-tbody').html('<tr><td colspan="12"><div class="ios-loading"><div class="ios-spinner"></div><div class="ios-spin-txt">Scanning all symbols…</div></div></td></tr>');
    $('#ios-stats').hide();
}
function emptyTable(msg) {
    $('#ios-tbody').html('<tr><td colspan="12"><div class="ios-empty"><i class="fas fa-bolt"></i>' + (msg || 'Select date and scan') + '</div></td></tr>');
    $('#ios-stats').hide();
}
function showWarn(msg) { $('#ios-warn').show(); $('#ios-warn-msg').text(msg||''); }
function hideWarn()    { $('#ios-warn').hide(); }
</script>
@endpush