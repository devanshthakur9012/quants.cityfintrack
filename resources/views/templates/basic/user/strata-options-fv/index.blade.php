@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');
:root {
    --navy-900:#0a0f1e; --navy-800:#0d1428; --navy-700:#111b35;
    --border:rgba(255,255,255,0.07);
    --ce:#38bdf8; --pe:#4ade80; --iv:#fbbf24; --sig:#a78bfa;
    --over:#f87171; --under:#4ade80;
    --text-1:rgba(255,255,255,0.92); --text-2:rgba(255,255,255,0.55); --text-3:rgba(255,255,255,0.25);
    --mono:'JetBrains Mono',monospace; --display:'Rajdhani',sans-serif;
}

.ios-header {
    background:linear-gradient(135deg,#0d1428 0%,#1a1035 50%,#0d1428 100%);
    border:1px solid var(--border); border-bottom:2px solid var(--sig);
    border-radius:14px; padding:20px 28px; margin-bottom:18px;
    position:relative; overflow:hidden;
}
.ios-header::before {
    content:'STRATA'; position:absolute; right:24px; top:50%; transform:translateY(-50%);
    font-family:var(--display); font-size:72px; font-weight:700;
    color:rgba(167,139,250,0.05); letter-spacing:6px; pointer-events:none; user-select:none;
}
.ios-title { font-family:var(--display); font-size:22px; font-weight:700; color:var(--text-1); margin:0; }
.tag {
    font-size:10px; font-weight:700; padding:2px 9px; border-radius:4px;
    margin-left:6px; vertical-align:middle; letter-spacing:1.5px;
    background:rgba(167,139,250,0.12); border:1px solid rgba(167,139,250,0.3); color:var(--sig);
}
.tag-ce { background:rgba(56,189,248,0.12); border-color:rgba(56,189,248,0.3); color:var(--ce); }
.tag-pe { background:rgba(74,222,128,0.12); border-color:rgba(74,222,128,0.3); color:var(--pe); }
.ios-sub { font-family:var(--mono); font-size:11px; color:var(--text-2); margin:6px 0 0; }

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
.tf-btn:hover  { border-color:rgba(167,139,250,0.4); color:var(--sig); }
.tf-btn.active { background:rgba(167,139,250,0.15); border-color:var(--sig); color:var(--sig); }
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
.sp-wrap { display:flex; gap:3px; }
.sp {
    padding:4px 12px; border-radius:20px; font-family:var(--display); font-size:10px; font-weight:700;
    cursor:pointer; border:1px solid var(--border); background:var(--navy-800); color:var(--text-2); transition:.15s;
}
.sp:hover  { border-color:var(--iv); color:var(--iv); }
.sp.active { background:rgba(251,191,36,0.15); border-color:var(--iv); color:var(--iv); }
.ios-select {
    background:rgba(255,255,255,0.06); border:1px solid var(--border); color:var(--text-1);
    border-radius:8px; padding:5px 10px; font-family:var(--display); font-size:12px;
    font-weight:600; cursor:pointer; outline:none; min-width:150px;
}
.ios-select option { background:#0d1428; }
.ios-btn {
    background:var(--sig); color:#fff; border:none; border-radius:8px;
    padding:7px 22px; font-family:var(--display); font-size:13px; font-weight:800; cursor:pointer;
}
.ios-btn:hover { background:#c4b5fd; color:#000; }
.ios-reset-btn {
    background:rgba(255,255,255,0.07); color:var(--text-2); border:1px solid var(--border);
    border-radius:8px; padding:6px 16px; font-family:var(--display); font-size:12px; font-weight:700; cursor:pointer;
}
.ml-auto { margin-left:auto; }
.last-upd { font-family:var(--mono); font-size:9px; color:var(--text-3); }
.dbadge { font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; }
.dbadge.live { background:rgba(74,222,128,0.12); color:#4ade80; border:1px solid rgba(74,222,128,0.25); }
.dbadge.hist { background:rgba(251,191,36,0.12); color:var(--iv); border:1px solid rgba(251,191,36,0.25); }
.auto-btn {
    background:rgba(255,255,255,0.07); color:var(--text-2); border:1px solid var(--border);
    border-radius:8px; padding:6px 14px; font-family:var(--display); font-size:10px; font-weight:700; cursor:pointer;
}
.auto-btn.on { border-color:#4ade80; color:#4ade80; }

/* Warn */
.ios-warn {
    background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.28);
    border-radius:10px; padding:12px 18px; margin-bottom:14px;
    font-family:var(--display); font-size:13px; color:var(--iv); display:none;
}

/* Stats */
.ios-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.stat-box {
    background:var(--navy-800); border:1px solid var(--border);
    border-radius:10px; padding:12px 16px; min-width:100px; flex:1;
}
.stat-box small { display:block; font-family:var(--display); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-3); margin-bottom:4px; }
.stat-box strong { display:block; font-family:var(--mono); font-size:1.15rem; font-weight:700; }
.s-total { border-left:3px solid var(--sig); }
.s-ce    { border-left:3px solid var(--ce); }
.s-pe    { border-left:3px solid var(--pe); }

/* Card / Table */
.ios-card { background:var(--navy-800); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
.ios-card-hdr {
    padding:14px 20px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px; background:var(--navy-700);
}
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

.sep-ce  { border-left:2px solid rgba(56,189,248,0.2)  !important; }
.sep-pe  { border-left:2px solid rgba(74,222,128,0.2)  !important; }
.sep-d   { border-left:1px dashed rgba(255,255,255,0.07)!important; }
.hce { color:var(--ce) !important; }
.hpe { color:var(--pe) !important; }

.c-num  { font-size:9px; color:var(--text-3); }
.c-time { font-size:12px; font-weight:700; color:var(--sig); }
.c-spot { font-size:12px; font-weight:700; color:var(--text-1); }
.sym-badge {
    display:inline-block; padding:2px 8px; border-radius:5px; font-size:10px; font-weight:700;
    background:rgba(167,139,250,0.10); color:var(--sig); border:1px solid rgba(167,139,250,0.22);
}
.level-badge {
    display:inline-block; padding:1px 5px; border-radius:3px; font-size:7px; font-weight:700;
    background:rgba(251,191,36,0.10); color:var(--iv); border:1px solid rgba(251,191,36,0.20); margin-top:2px;
}

/* valuation badge */
.vb { display:inline-block; padding:2px 8px; border-radius:5px; font-size:9px; font-weight:700; }
.vb-over  { background:rgba(248,113,113,0.15); color:var(--over);  border:1px solid rgba(248,113,113,0.30); }
.vb-under { background:rgba(74,222,128,0.15);  color:var(--under); border:1px solid rgba(74,222,128,0.30); }
.vb-fair  { background:rgba(255,255,255,0.04); color:var(--text-3); border:1px solid var(--border); }
.vb-na    { color:rgba(255,255,255,0.15); font-size:9px; }
.dp { color:var(--over);  font-weight:700; }
.dn { color:var(--under); font-weight:700; }
.dz { color:var(--text-3); }

/* Loading / empty */
.ios-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px; }
.ios-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,0.1); border-top:3px solid var(--sig); border-radius:50%; animation:spin 1s linear infinite; }
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
            &#9670; Strata — Options Fair Value
            <span class="tag">BLACK-SCHOLES</span>
            <span class="tag tag-ce">CE</span>
            <span class="tag tag-pe">PE</span>
        </h4>
        <div class="ios-sub">
            Fair Price = BS(Spot, Strike, ATM IV, DTE) &nbsp;·&nbsp;
            OVERPRICED = LTP &gt; Fair &nbsp;·&nbsp;
            UNDERPRICED = LTP &lt; Fair &nbsp;·&nbsp;
            Tolerance ±5% &nbsp;·&nbsp;
            Source: cp_option_ohlc + cp_fut_ohlc
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

        <span class="ctrl-label">DATE</span>
        <div style="display:flex;align-items:center;gap:4px;">
            <button class="dnav" onclick="shiftDate(-1)">&#8249;</button>
            <input type="date" id="ios-date" class="ios-date"
                   value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"
                   onchange="runAnalysis()">
            <button class="dnav" onclick="shiftDate(1)">&#8250;</button>
            <button class="dnav today-btn" onclick="goToday()">TODAY</button>
            <span id="date-badge"></span>
        </div>

        <div class="ctrl-sep"></div>

        <span class="ctrl-label">STRIKE</span>
        <div class="sp-wrap">
            <div class="sp" data-val="ATM-1">ATM−1</div>
            <div class="sp active" data-val="ATM">ATM</div>
            <div class="sp" data-val="ATM+1">ATM+1</div>
        </div>

        <div class="ctrl-sep"></div>

        <span class="ctrl-label">SYMBOL</span>
        <select id="ios-sym" class="ios-select" onchange="runAnalysis()">
            <option value="">— All Symbols —</option>
        </select>

        <select id="ios-sort" class="ios-select">
            <option value="symbol">Sort: A – Z</option>
            <option value="ce_overpriced">CE Most Overpriced</option>
            <option value="ce_underpriced">CE Most Underpriced</option>
            <option value="pe_overpriced">PE Most Overpriced</option>
            <option value="pe_underpriced">PE Most Underpriced</option>
            <option value="mispricing">Largest Mispricing</option>
        </select>

        <button class="ios-btn" onclick="runAnalysis()">&#9670; Analyze</button>
        <button class="ios-reset-btn" onclick="clearSymbol()">All Symbols</button>
        <button class="auto-btn" id="auto-btn" onclick="toggleAuto()">&#9654; Auto 60s</button>

        <div class="ml-auto d-flex align-items-center" style="gap:12px;">
            <span id="ios-info" style="font-family:var(--mono);font-size:10px;color:var(--text-2);"></span>
            <span class="last-upd" id="ios-upd"></span>
        </div>
    </div>

    {{-- Warn --}}
    <div class="ios-warn" id="ios-warn">&#9888; <span id="ios-warn-msg"></span></div>

    {{-- Stats --}}
    <div class="ios-stats" id="ios-stats" style="display:none;">
        <div class="stat-box s-total"><small>Total Rows</small><strong id="st-total" style="color:var(--sig);">0</strong></div>
        <div class="stat-box s-ce"><small>CE Overpriced</small><strong id="st-ce-over"  style="color:var(--over);">0</strong></div>
        <div class="stat-box s-ce"><small>CE Underpriced</small><strong id="st-ce-under" style="color:var(--under);">0</strong></div>
        <div class="stat-box s-pe"><small>PE Overpriced</small><strong id="st-pe-over"  style="color:var(--over);">0</strong></div>
        <div class="stat-box s-pe"><small>PE Underpriced</small><strong id="st-pe-under" style="color:var(--under);">0</strong></div>
    </div>

    {{-- Table --}}
    <div class="ios-card">
        <div class="ios-card-hdr">
            <span class="ios-card-title" id="ios-card-title">&#9670; Strata Fair Value &nbsp;·&nbsp; 15 Min</span>
            <span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);" id="ios-card-info"></span>
        </div>
        <div class="ios-tscroll">
            <table class="ios-table">
                <thead>
                    <tr class="hdr-grp">
                        <th colspan="5">Info</th>
                        <th colspan="5" class="hce sep-ce">&#9651; CE — Market vs Fair</th>
                        <th colspan="5" class="hpe sep-pe">&#9661; PE — Market vs Fair</th>
                        <th>ATM IV</th>
                        <th>Exp Move</th>
                    </tr>
                    <tr class="hdr-cols">
                        <th>#</th>
                        <th>Time</th>
                        <th style="text-align:left;padding-left:14px;">Symbol</th>
                        <th>Spot</th>
                        <th>Strike<br><span style="font-size:7px;opacity:.5;font-weight:400;">Level · DTE</span></th>
                        {{-- CE --}}
                        <th class="hce sep-ce">LTP</th>
                        <th class="hce">Fair ₹</th>
                        <th class="hce">Status</th>
                        <th class="hce sep-d">Diff ₹</th>
                        <th class="hce">Diff %</th>
                        {{-- PE --}}
                        <th class="hpe sep-pe">LTP</th>
                        <th class="hpe">Fair ₹</th>
                        <th class="hpe">Status</th>
                        <th class="hpe sep-d">Diff ₹</th>
                        <th class="hpe">Diff %</th>
                        <th>IV %</th>
                        <th>±₹</th>
                    </tr>
                </thead>
                <tbody id="ios-tbody">
                    <tr><td colspan="17">
                        <div class="ios-empty"><i class="fas fa-chart-line"></i>Select date and click Analyze</div>
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
const ANALYZE_URL = '{{ route("strata-options-fv.analyze") }}';
const SYM_URL     = '{{ route("strata-options-fv.symbols") }}';
const todayStr    = '{{ now()->toDateString() }}';

let curTf = '15min', curStrike = 'ATM', symCache = {}, autoTimer = null;

$(document).ready(function () {
    loadSymbols(); runAnalysis(); updateDateBadge();
    $('.sp').on('click', function () { $('.sp').removeClass('active'); $(this).addClass('active'); curStrike = $(this).data('val'); runAnalysis(); });
    $('#ios-sort').on('change', runAnalysis);
});

function setTf(tf, btn) {
    curTf = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    $('#ios-card-title').text('⬡ Strata Fair Value · ' + tf.toUpperCase());
    loadSymbols();
}

function shiftDate(d) {
    var p = document.getElementById('ios-date');
    var dt = new Date(p.value); dt.setDate(dt.getDate() + d);
    var s = dt.toISOString().split('T')[0];
    if (s > todayStr) return;
    p.value = s; updateDateBadge(); runAnalysis();
}
function goToday() { document.getElementById('ios-date').value = todayStr; updateDateBadge(); runAnalysis(); }
function updateDateBadge() {
    var d = document.getElementById('ios-date').value;
    $('#date-badge').html(d === todayStr
        ? '<span class="dbadge live">&#11044; Live</span>'
        : '<span class="dbadge hist">&#9724; Historical</span>');
}

function loadSymbols() {
    if (symCache[curTf]) { rebuildSym(symCache[curTf]); return; }
    $.get(SYM_URL, { timeframe: curTf }, function (res) {
        if (res.no_config) { showWarn(res.message); rebuildSym([]); return; }
        hideWarn(); symCache[curTf] = res.symbols || []; rebuildSym(symCache[curTf]);
    });
}
function rebuildSym(syms) {
    $('#ios-sym').html('<option value="">— All Symbols —</option>' + syms.map(s => `<option value="${s}">${s}</option>`).join(''));
}
function clearSymbol() { $('#ios-sym').val(''); runAnalysis(); }

function toggleAuto() {
    if (autoTimer) { clearInterval(autoTimer); autoTimer = null; $('#auto-btn').text('▶ Auto 60s').removeClass('on'); }
    else { autoTimer = setInterval(runAnalysis, 60000); $('#auto-btn').text('■ Stop').addClass('on'); runAnalysis(); }
}

function runAnalysis() {
    var sym  = $('#ios-sym').val();
    var sort = $('#ios-sort').val();
    var date = document.getElementById('ios-date').value;
    updateDateBadge();
    $('#ios-sort').toggle(!sym);
    showLoading();

    $.ajax({
        url: ANALYZE_URL, type: 'GET',
        data: { timeframe: curTf, strike_filter: curStrike, sort_by: sort, symbol: sym, date: date },
        success(res) {
            if (res.no_config) { showWarn(res.message); emptyTable(); return; }
            if (!res.success)  { emptyTable(res.message); return; }
            hideWarn();
            renderStats(res.summary, res.total_rows);
            renderTable(res.rows);
            $('#ios-info').html(`Date: <span style="color:var(--iv)">${res.trade_date}</span> &nbsp;·&nbsp; Time: <span style="color:var(--ce)">${res.latest_time || '—'}</span> &nbsp;·&nbsp; TF: <span style="color:var(--sig)">${res.timeframe}</span>`);
            $('#ios-card-info').text(res.total_rows + ' row(s)');
            $('#ios-upd').text('Updated ' + new Date().toLocaleTimeString());
        },
        error(xhr) { emptyTable('&#9888; ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error')); }
    });
}

function renderStats(s, total) {
    $('#st-total').text(total);
    $('#st-ce-over').text(s.ceOver); $('#st-ce-under').text(s.ceUnder);
    $('#st-pe-over').text(s.peOver); $('#st-pe-under').text(s.peUnder);
    $('#ios-stats').show();
}

function renderTable(rows) {
    if (!rows || !rows.length) { emptyTable('No data for selected filters.'); return; }
    var html = '';

    rows.forEach(function (r, idx) {
        var zebra = idx % 2 === 0 ? 'row-even' : 'row-odd';

        var ceCols = r.ce_ltp != null
            ? td('sep-ce', '₹' + r.ce_ltp)
            + td('', '<strong style="color:var(--ce);">₹' + nv(r.ce_fair) + '</strong>')
            + td('', vbadge(r.ce_status))
            + td('sep-d ' + dc(r.ce_diff), diffFmt(r.ce_diff, '₹'))
            + td(dc(r.ce_diff_pct), diffp(r.ce_diff_pct))
            : '<td colspan="5" class="sep-ce" style="color:rgba(255,255,255,.1);font-size:9px;">— no CE —</td>';

        var peCols = r.pe_ltp != null
            ? td('sep-pe', '₹' + r.pe_ltp)
            + td('', '<strong style="color:var(--pe);">₹' + nv(r.pe_fair) + '</strong>')
            + td('', vbadge(r.pe_status))
            + td('sep-d ' + dc(r.pe_diff), diffFmt(r.pe_diff, '₹'))
            + td(dc(r.pe_diff_pct), diffp(r.pe_diff_pct))
            : '<td colspan="5" class="sep-pe" style="color:rgba(255,255,255,.1);font-size:9px;">— no PE —</td>';

        var strikeMeta =
            '<span style="color:var(--iv);font-weight:700;">₹' + fmt(r.strike) + '</span>'
            + '<br><span class="level-badge">' + (r.strike_level || 'ATM') + '</span>'
            + '&thinsp;<span style="font-size:8px;color:var(--text-3);">' + r.days_to_expiry + 'd</span>';

        html +=
            '<tr class="' + zebra + '">'
            + td('c-num', idx + 1)
            + td('c-time', r.time || '—')
            + '<td style="text-align:left;padding-left:14px;"><span class="sym-badge">' + esc(r.symbol) + '</span></td>'
            + td('c-spot', '₹' + fmt(r.spot))
            + '<td>' + strikeMeta + '</td>'
            + ceCols + peCols
            + td('', r.atm_iv != null ? '<span style="color:var(--iv);font-weight:700;">' + r.atm_iv + '%</span>' : dash())
            + td('', r.expected_move != null ? '<span style="color:var(--sig);font-weight:700;">±₹' + r.expected_move + '</span>' : dash())
            + '</tr>';
    });

    $('#ios-tbody').html(html);
}

function td(cls, inner) { return '<td' + (cls ? ' class="' + cls + '"' : '') + '>' + inner + '</td>'; }
function vbadge(st) {
    var map = { OVERPRICED:'vb-over', UNDERPRICED:'vb-under', FAIR:'vb-fair' };
    return '<span class="vb ' + (map[st] || 'vb-na') + '">' + (st === 'N/A' ? '—' : (st || '—')) + '</span>';
}
function dc(v)           { if (v == null) return 'dz'; return Number(v) > 0 ? 'dp' : (Number(v) < 0 ? 'dn' : 'dz'); }
function diffFmt(v, pfx) { if (v == null) return dash(); var n = Number(v); return (n >= 0 ? '+' : '') + pfx + Math.abs(n).toFixed(2); }
function diffp(v)        { if (v == null) return dash(); var n = Number(v); return (n >= 0 ? '+' : '') + n + '%'; }
function nv(v)           { return v != null ? v : '—'; }
function dash()          { return '<span style="color:rgba(255,255,255,.15);font-size:9px;">—</span>'; }
function fmt(v)          { return v != null ? Number(v).toLocaleString('en-IN', { maximumFractionDigits: 2 }) : '—'; }
function esc(s)          { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showLoading() {
    $('#ios-tbody').html('<tr><td colspan="17"><div class="ios-loading"><div class="ios-spinner"></div><div class="ios-spin-txt">Calculating fair values…</div></div></td></tr>');
    $('#ios-stats').hide();
}
function emptyTable(msg) {
    $('#ios-tbody').html('<tr><td colspan="17"><div class="ios-empty"><i class="fas fa-chart-line"></i>' + (msg || 'Select date and click Analyze') + '</div></td></tr>');
    $('#ios-stats').hide();
}
function showWarn(msg) { $('#ios-warn').show(); $('#ios-warn-msg').text(msg || ''); }
function hideWarn()    { $('#ios-warn').hide(); }
</script>
@endpush