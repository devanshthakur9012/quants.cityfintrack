@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');
:root {
    --navy-900:#0a0f1e; --navy-800:#0d1428; --navy-700:#111b35;
    --border:rgba(255,255,255,0.07);
    --ce:#38bdf8; --pe:#4ade80; --amber:#f59e0b; --teal:#14b8a6;
    --text-1:rgba(255,255,255,0.92); --text-2:rgba(255,255,255,0.55); --text-3:rgba(255,255,255,0.25);
    --mono:'JetBrains Mono',monospace; --display:'Rajdhani',sans-serif;
}

.ios-header {
    background:linear-gradient(135deg,#0d1428 0%,#0a1a1a 50%,#0d1428 100%);
    border:1px solid var(--border); border-bottom:2px solid var(--teal);
    border-radius:14px; padding:20px 28px; margin-bottom:18px;
    position:relative; overflow:hidden;
}
.ios-header::before {
    content:'SS'; position:absolute; right:24px; top:50%; transform:translateY(-50%);
    font-family:var(--display); font-size:100px; font-weight:700;
    color:rgba(20,184,166,0.04); pointer-events:none; user-select:none;
}
.ios-title { font-family:var(--display); font-size:22px; font-weight:700; color:var(--text-1); margin:0; }
.tag { font-size:10px; font-weight:700; padding:2px 9px; border-radius:4px; margin-left:6px; vertical-align:middle; letter-spacing:1.5px; }
.tag-t  { background:rgba(20,184,166,0.12); border:1px solid rgba(20,184,166,0.3); color:var(--teal); }
.tag-ce { background:rgba(56,189,248,0.12); border:1px solid rgba(56,189,248,0.3); color:var(--ce); }
.tag-pe { background:rgba(74,222,128,0.12); border:1px solid rgba(74,222,128,0.3); color:var(--pe); }
.ios-sub { font-family:var(--mono); font-size:11px; color:var(--text-2); margin:6px 0 0; }
.lp { display:inline-block; font-family:var(--mono); font-size:10px; padding:2px 9px; border-radius:4px; margin:2px; }
.lp-t { background:rgba(20,184,166,0.08); border:1px solid rgba(20,184,166,0.2); color:var(--teal); }

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
.tf-btn:hover  { border-color:rgba(20,184,166,0.4); color:var(--teal); }
.tf-btn.active { background:rgba(20,184,166,0.15); border-color:var(--teal); color:var(--teal); }
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
    font-weight:600; cursor:pointer; outline:none; min-width:160px;
}
.ios-select option { background:#0d1428; }
.ios-btn { background:var(--teal); color:#000; border:none; border-radius:8px; padding:7px 22px; font-family:var(--display); font-size:13px; font-weight:800; cursor:pointer; }
.ios-btn:hover { background:#2dd4bf; }
.ios-reset-btn {
    background:rgba(255,255,255,0.07); color:var(--text-2); border:1px solid var(--border);
    border-radius:8px; padding:6px 16px; font-family:var(--display); font-size:12px; font-weight:700; cursor:pointer;
}
.sp-wrap { display:flex; gap:3px; flex-wrap:wrap; }
.sp {
    padding:4px 12px; border-radius:20px; font-family:var(--display); font-size:10px; font-weight:700;
    cursor:pointer; border:1px solid var(--border); background:var(--navy-800); color:var(--text-2); transition:.15s;
}
.sp:hover       { border-color:rgba(20,184,166,0.4); color:var(--teal); }
.sp.active      { background:rgba(20,184,166,0.15); border-color:var(--teal); color:var(--teal); }
.sp.active-ce   { background:rgba(56,189,248,0.15); border-color:var(--ce); color:var(--ce); }
.sp.active-pe   { background:rgba(74,222,128,0.15); border-color:var(--pe); color:var(--pe); }
.sp.active-wait { background:rgba(245,158,11,0.12); border-color:var(--amber); color:var(--amber); }
.ml-auto { margin-left:auto; }
.last-upd { font-family:var(--mono); font-size:9px; color:var(--text-3); }
.dbadge { font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; }
.dbadge.live { background:rgba(20,184,166,0.12); color:var(--teal); border:1px solid rgba(20,184,166,0.25); }
.dbadge.hist { background:rgba(245,158,11,0.12); color:var(--amber); border:1px solid rgba(245,158,11,0.25); }

/* Warn */
.ios-warn { background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.28); border-radius:10px; padding:12px 18px; margin-bottom:14px; font-family:var(--display); font-size:13px; color:var(--amber); display:none; }

/* Stats */
.ios-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.stat-box { background:var(--navy-800); border:1px solid var(--border); border-radius:10px; padding:12px 16px; min-width:100px; flex:1; }
.stat-box small { display:block; font-family:var(--display); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-3); margin-bottom:4px; }
.stat-box strong { display:block; font-family:var(--mono); font-size:1.2rem; font-weight:700; }
.s-total { border-left:3px solid var(--teal); }
.s-ce    { border-left:3px solid var(--ce); }
.s-pe    { border-left:3px solid var(--pe); }
.s-wait  { border-left:3px solid var(--amber); }

/* Card / Table */
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
.ios-table tbody tr { cursor:pointer; }
.ios-table tbody tr:hover { background:rgba(255,255,255,0.04) !important; }
.row-even { background:rgba(255,255,255,0.008); }
.row-odd  { background:rgba(0,0,0,0.1); }
.row-ce   { background:rgba(56,189,248,0.04)  !important; border-left:2px solid var(--ce)  !important; }
.row-pe   { background:rgba(74,222,128,0.04)  !important; border-left:2px solid var(--pe)  !important; }
.row-wait { opacity:.75; }
.row-entry  { background:rgba(20,184,166,0.05) !important; border-left:2px solid var(--teal) !important; }
.row-latest { background:rgba(245,158,11,0.05) !important; border-left:2px solid var(--amber) !important; }

.sep-ce  { border-left:2px solid rgba(56,189,248,0.2)  !important; }
.sep-pe  { border-left:2px solid rgba(74,222,128,0.2)  !important; }
.sep-sig { border-left:2px solid rgba(20,184,166,0.25) !important; }
.hce { color:var(--ce) !important; } .hpe { color:var(--pe) !important; } .ht { color:var(--teal) !important; }

/* Signal badges */
.sig-ce   { display:inline-block; background:rgba(56,189,248,0.2);  color:var(--ce);  border:1px solid rgba(56,189,248,0.45); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-pe   { display:inline-block; background:rgba(74,222,128,0.2);  color:var(--pe);  border:1px solid rgba(74,222,128,0.45); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-wait { display:inline-block; background:rgba(255,255,255,0.04); color:rgba(255,255,255,0.2); border:1px solid var(--border); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:9px; }

/* Score bar */
.score-wrap  { display:flex; align-items:center; gap:4px; justify-content:center; }
.score-num   { font-size:12px; font-weight:800; min-width:14px; }
.score-track { width:40px; height:4px; background:rgba(255,255,255,0.08); border-radius:2px; overflow:hidden; }
.score-fill  { height:100%; border-radius:2px; }

/* Factor dots */
.factor-dots { display:flex; align-items:center; gap:2px; justify-content:center; flex-wrap:wrap; }
.fd { width:8px; height:8px; border-radius:50%; display:inline-block; }
.fd-ce   { background:var(--ce);   box-shadow:0 0 4px var(--ce); }
.fd-pe   { background:var(--pe);   box-shadow:0 0 4px var(--pe); }
.fd-neut { background:rgba(255,255,255,0.15); }
.fd-na   { background:rgba(255,255,255,0.05); }

/* Symbol badge */
.sym-badge { display:inline-block; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:800; background:rgba(20,184,166,0.10); color:var(--teal); border:1px solid rgba(20,184,166,0.22); }

.time-entry  { display:inline-block; background:rgba(20,184,166,0.15); color:var(--teal);  border:1px solid rgba(20,184,166,0.35); border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.time-latest { display:inline-block; background:rgba(245,158,11,0.15); color:var(--amber); border:1px solid rgba(245,158,11,0.35); border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.time-norm   { color:var(--teal); font-weight:700; font-size:11px; }

/* Detail header card */
.detail-hdr { background:rgba(20,184,166,0.04); border:1px solid rgba(20,184,166,0.2); border-radius:12px; padding:14px 18px; margin-bottom:14px; display:flex; align-items:center; flex-wrap:wrap; gap:12px; }
.detail-sym  { font-family:var(--display); font-size:20px; font-weight:900; color:var(--teal); }
.dm { border-radius:6px; padding:3px 10px; font-size:10px; font-weight:700; border:1px solid; }
.dm-t { background:rgba(20,184,166,0.1); color:var(--teal);  border-color:rgba(20,184,166,0.25); }
.dm-a { background:rgba(245,158,11,0.1); color:var(--amber); border-color:rgba(245,158,11,0.25); }

/* Loading / empty */
.ios-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px; }
.ios-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,0.1); border-top:3px solid var(--teal); border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.ios-spin-txt { color:var(--text-2); margin-top:12px; font-family:var(--display); font-size:13px; }
.ios-empty { text-align:center; padding:60px 20px; color:var(--text-3); font-family:var(--display); font-size:13px; }
.ios-empty i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }

/* Factor legend */
.factor-legend { padding:10px 18px; border-top:1px solid var(--border); font-size:10px; color:var(--text-3); line-height:1.9; background:rgba(0,0,0,0.15); }
.factor-legend strong { color:rgba(255,255,255,.4); }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="ios-header">
        <h4 class="ios-title">
            &#9670; Straddle &amp; Strangle — Signal Engine
            <span class="tag tag-t">5-FACTOR</span>
            <span class="tag tag-ce">BUY CE</span>
            <span class="tag tag-pe">BUY PE</span>
        </h4>
        <div class="ios-sub" style="margin-top:8px;">
            <span class="lp lp-t">5 factors · min 3/5 to signal · Futures + OI + Premium Momentum + PCR + Candle Structure</span>
        </div>
        <div class="ios-sub" style="margin-top:5px; color:var(--text-3);">
            Source: cp_option_ohlc + cp_fut_ohlc &nbsp;·&nbsp; Config-scoped symbols &nbsp;·&nbsp;
            No P&L &nbsp;·&nbsp; Pure directional analysis
        </div>
    </div>

    {{-- Controls --}}
    <div class="ios-controls">
        {{-- TF --}}
        <span class="ctrl-label">TF</span>
        <div class="tf-group">
            <button class="tf-btn active" data-tf="15min" onclick="setTf('15min',this)">15 Min</button>
            <button class="tf-btn"        data-tf="30min" onclick="setTf('30min',this)">30 Min</button>
            <button class="tf-btn"        data-tf="1hr"   onclick="setTf('1hr',this)">1 Hour</button>
        </div>

        <div class="ctrl-sep"></div>

        {{-- Strategy --}}
        <span class="ctrl-label">STRATEGY</span>
        <select id="ios-strat" class="ios-select" onchange="loadData()">
            <option value="long_straddle" selected>Long Straddle</option>
            <option value="short_straddle">Short Straddle</option>
            <option value="long_strangle">Long Strangle</option>
            <option value="short_strangle">Short Strangle</option>
        </select>

        <div class="ctrl-sep"></div>

        {{-- Date --}}
        <span class="ctrl-label">DATE</span>
        <div style="display:flex;align-items:center;gap:4px;">
            <button class="dnav" onclick="shiftDate(-1)">&#8249;</button>
            <input type="date" id="ios-date" class="ios-date"
                   value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"
                   onchange="loadData()">
            <button class="dnav" onclick="shiftDate(1)">&#8250;</button>
            <button class="dnav today-btn" onclick="goToday()">TODAY</button>
            <span id="date-badge"></span>
        </div>

        <div class="ctrl-sep"></div>

        {{-- Symbol --}}
        <span class="ctrl-label">SYMBOL</span>
        <select id="ios-sym" class="ios-select" onchange="loadData()">
            <option value="ALL">— All Symbols —</option>
        </select>

        <button class="ios-btn" onclick="loadData()">&#9670; Analyze</button>
        <button class="ios-reset-btn" onclick="clearSym()">All Symbols</button>

        <div class="ctrl-sep"></div>

        {{-- Filter --}}
        <span class="ctrl-label">FILTER</span>
        <div class="sp-wrap" id="filter-pills">
            <div class="sp active"    data-f="ALL"    onclick="setFilter('ALL',this)">All</div>
            <div class="sp"           data-f="BUY_CE" onclick="setFilter('BUY_CE',this)">&#8679; Buy CE</div>
            <div class="sp"           data-f="BUY_PE" onclick="setFilter('BUY_PE',this)">&#8681; Buy PE</div>
            <div class="sp"           data-f="WAIT"   onclick="setFilter('WAIT',this)">&#8213; Wait</div>
        </div>

        <div class="ml-auto">
            <span class="last-upd" id="ios-upd"></span>
        </div>
    </div>

    {{-- Warn --}}
    <div class="ios-warn" id="ios-warn">&#9888; <span id="ios-warn-msg"></span></div>

    {{-- Stats --}}
    <div class="ios-stats" id="ios-stats" style="display:none;">
        <div class="stat-box s-total"><small>Total</small><strong id="st-total" style="color:var(--teal);">0</strong></div>
        <div class="stat-box s-ce"><small>&#8679; Buy CE</small><strong id="st-ce" style="color:var(--ce);">0</strong></div>
        <div class="stat-box s-pe"><small>&#8681; Buy PE</small><strong id="st-pe" style="color:var(--pe);">0</strong></div>
        <div class="stat-box s-wait"><small>&#8213; Wait</small><strong id="st-wait" style="color:var(--amber);">0</strong></div>
    </div>

    {{-- Table --}}
    <div id="ios-content">
        <div class="ios-card">
            <div class="ios-card-hdr">
                <span class="ios-card-title" id="ios-card-title">&#9670; Straddle &amp; Strangle Signal Engine &nbsp;·&nbsp; 15 Min</span>
                <span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);" id="ios-card-info"></span>
            </div>
            <div class="ios-tscroll">
                <table class="ios-table">
                    <thead id="ios-thead">
                        <tr class="hdr-grp">
                            <th colspan="5">Info</th>
                            <th colspan="3" class="hce sep-ce">&#8679; CE</th>
                            <th colspan="3" class="hpe sep-pe">&#8681; PE</th>
                            <th colspan="4" class="ht sep-sig">&#9670; Signal (5 Factors, need 3+)</th>
                        </tr>
                        <tr class="hdr-cols">
                            <th>#</th>
                            <th style="text-align:left;padding-left:14px;">Symbol</th>
                            <th>ATM / Expiry</th>
                            <th>Spot</th>
                            <th>Combined<br>Premium</th>
                            <th class="sep-ce hce">CE Strike</th>
                            <th class="hce">CE LTP</th>
                            <th class="hce">CE OI</th>
                            <th class="sep-pe hpe">PE Strike</th>
                            <th class="hpe">PE LTP</th>
                            <th class="hpe">PE OI</th>
                            <th class="sep-sig ht">Signal</th>
                            <th class="ht">Score CE/PE</th>
                            <th class="ht">Factors</th>
                            <th class="ht">Reason</th>
                        </tr>
                    </thead>
                    <tbody id="ios-tbody">
                        <tr><td colspan="15">
                            <div class="ios-empty"><i class="fas fa-chart-area"></i>Select strategy and click Analyze</div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
            <div class="factor-legend" id="factor-legend">
                <strong>5 Factors scored (need 3+ on same side):</strong>
                &nbsp;&#9650; <strong style="color:var(--ce);">Futures Momentum</strong> (bullish/bearish candle)
                &nbsp;·&nbsp; &#9650; <strong style="color:var(--ce);">OI Confirmation</strong> (OI ↑ + LTP ↑ = fresh buying)
                &nbsp;·&nbsp; &#9650; <strong style="color:var(--ce);">Premium Momentum</strong> (which leg gaining faster, need >2% diff)
                &nbsp;·&nbsp; &#9650; <strong style="color:var(--ce);">PCR</strong> (&lt;0.80 bullish, &gt;1.20 bearish)
                &nbsp;·&nbsp; &#9650; <strong style="color:var(--ce);">Candle Structure</strong> (new high + bullish close = breakout candle)
                &nbsp;·&nbsp; <span style="color:var(--ce);">&#11044;</span> = CE factor &nbsp;
                <span style="color:var(--pe);">&#11044;</span> = PE factor &nbsp;
                <span style="color:rgba(255,255,255,.2);">&#11044;</span> = Neutral
            </div>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
const DATA_URL  = '{{ route("straddle-strategy.data") }}';
const TODAY_STR = '{{ now()->toDateString() }}';

let curTf     = '15min';
let curFilter = 'ALL';
let symCache  = {};
let curMode   = 'summary';

$(document).ready(function () { updateDateBadge(); loadData(); });

/* ── TF ──────────────────────────────────────────────────────────── */
function setTf(tf, btn) {
    curTf = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    updateTitle();
    loadData();
}

/* ── Date ────────────────────────────────────────────────────────── */
function shiftDate(d) {
    const p  = document.getElementById('ios-date');
    const dt = new Date(p.value); dt.setDate(dt.getDate() + d);
    const s  = dt.toISOString().split('T')[0];
    if (s > TODAY_STR) return;
    p.value = s; updateDateBadge(); loadData();
}
function goToday() { document.getElementById('ios-date').value = TODAY_STR; updateDateBadge(); loadData(); }
function updateDateBadge() {
    const d = document.getElementById('ios-date').value;
    $('#date-badge').html(d === TODAY_STR
        ? '<span class="dbadge live">&#11044; Live</span>'
        : '<span class="dbadge hist">&#9724; Historical</span>');
}
function updateTitle() {
    $('#ios-card-title').text('⬡ Straddle & Strangle Signal Engine · ' + curTf.toUpperCase());
}

/* ── Symbols ─────────────────────────────────────────────────────── */
function rebuildSym(syms) {
    const sel  = document.getElementById('ios-sym');
    const prev = sel.value;
    sel.innerHTML = '<option value="ALL">— All Symbols —</option>'
        + syms.map(s => `<option value="${s}"${s===prev?' selected':''}>${s}</option>`).join('');
}
function clearSym() { document.getElementById('ios-sym').value = 'ALL'; loadData(); }

/* ── Filter ──────────────────────────────────────────────────────── */
function setFilter(f, btn) {
    curFilter = f;
    document.querySelectorAll('#filter-pills .sp').forEach(b => {
        b.classList.remove('active','active-ce','active-pe','active-wait');
    });
    btn.classList.add(f === 'BUY_CE' ? 'active-ce' : f === 'BUY_PE' ? 'active-pe' : f === 'WAIT' ? 'active-wait' : 'active');
    applyFilter();
}
function applyFilter() {
    document.querySelectorAll('#ios-tbody tr[data-sig]').forEach(row => {
        const sig = row.dataset.sig;
        let show = curFilter === 'ALL'
            || (curFilter === 'BUY_CE' && sig === 'BUY_CE')
            || (curFilter === 'BUY_PE' && sig === 'BUY_PE')
            || (curFilter === 'WAIT'   && sig === 'WAIT');
        row.style.display = show ? '' : 'none';
    });
}

/* ── Load ────────────────────────────────────────────────────────── */
function loadData() {
    const date  = document.getElementById('ios-date').value;
    const strat = document.getElementById('ios-strat').value;
    const sym   = document.getElementById('ios-sym').value || 'ALL';
    updateDateBadge(); updateTitle();
    showLoading();

    $.ajax({
        url: DATA_URL, type: 'GET',
        data: { timeframe: curTf, strategy: strat, date: date, symbol: sym },
        success(res) {
            if (res.no_config) { showWarn(res.message); emptyTable(); return; }
            if (!res.success)  { emptyTable(res.message); return; }
            hideWarn();

            if (res.available_symbols && res.available_symbols.length) rebuildSym(res.available_symbols);

            curMode = res.mode;

            if (res.mode === 'detail') {
                renderDetail(res);
            } else {
                renderSummary(res);
                updateStats(res);
                applyFilter();
            }

            $('#ios-card-info').text(
                (res.total || res.total_intervals || 0) + ' row(s) · ' + res.strategy_name
            );
            $('#ios-upd').text('Updated ' + new Date().toLocaleTimeString());
        },
        error(xhr) { emptyTable('&#9888; ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error')); }
    });
}

/* ── Render Summary (ALL mode) ───────────────────────────────────── */
function renderSummary(res) {
    // Show filter pills only in summary mode
    $('#filter-pills').show();

    var html = '';
    (res.data || []).forEach(function (r, i) {
        const sig    = r.signal || 'WAIT';
        const isCe   = sig === 'BUY_CE';
        const isPe   = sig === 'BUY_PE';
        const rowCls = (i % 2 === 0 ? 'row-even' : 'row-odd')
            + (isCe ? ' row-ce' : isPe ? ' row-pe' : ' row-wait');

        html +=
            '<tr class="' + rowCls + '" data-sig="' + esc(sig) + '" onclick="jumpToSym(\'' + esc(r.symbol) + '\')">'
            + '<td class="c-num" style="font-size:9px;color:var(--text-3);">' + (i + 1) + '</td>'
            + '<td style="text-align:left;padding-left:14px;"><span class="sym-badge">' + esc(r.symbol) + '</span></td>'
            + '<td>'
            + '<span style="color:var(--amber);font-weight:700;font-size:10px;">&#8377;' + fmt(r.atm_strike) + '</span>'
            + (r.expiry ? '<br><span style="font-size:8px;color:var(--text-3);">' + esc(r.expiry) + '</span>' : '')
            + '</td>'
            + '<td style="font-weight:700;color:var(--text-1);">' + (r.spot ? '&#8377;' + fmt(r.spot) : dash()) + '</td>'
            + '<td style="color:var(--teal);font-weight:700;">' + (r.combined_prem ? '&#8377;' + fmt(r.combined_prem) : dash()) + '</td>'
            // CE
            + '<td class="sep-ce" style="color:var(--ce);font-weight:700;">' + (r.ce_strike ? fmt(r.ce_strike) : dash()) + '</td>'
            + '<td style="color:var(--ce);font-weight:700;">' + (r.ce_ltp !== null ? '&#8377;' + fmt(r.ce_ltp) : dash()) + '</td>'
            + '<td style="font-size:10px;color:var(--text-2);">' + (r.ce_oi !== null ? nInt(r.ce_oi) : dash()) + '</td>'
            // PE
            + '<td class="sep-pe" style="color:var(--pe);font-weight:700;">' + (r.pe_strike ? fmt(r.pe_strike) : dash()) + '</td>'
            + '<td style="color:var(--pe);font-weight:700;">' + (r.pe_ltp !== null ? '&#8377;' + fmt(r.pe_ltp) : dash()) + '</td>'
            + '<td style="font-size:10px;color:var(--text-2);">' + (r.pe_oi !== null ? nInt(r.pe_oi) : dash()) + '</td>'
            // Signal
            + '<td class="sep-sig">' + sigBadge(sig) + '</td>'
            + '<td>' + scoreBars(r.ce_score, r.pe_score) + '</td>'
            + '<td>' + factorDots(r.factors) + '</td>'
            + '<td style="font-size:9px;color:var(--text-2);text-align:left;max-width:200px;white-space:normal;padding:7px 10px;">'
            + esc(r.reason || '—') + '</td>'
            + '</tr>';
    });

    if (!html) { emptyTable('No data for selected filters.'); return; }
    $('#ios-tbody').html(html);
    $('#ios-stats').show();
}

/* ── Render Detail (single symbol mode) ─────────────────────────── */
function renderDetail(res) {
    $('#filter-pills').hide();
    $('#ios-stats').hide();

    // Detail header
    const hdr = `<div class="detail-hdr" style="margin-bottom:14px;">
        <span class="detail-sym">&#9670; ${esc(res.symbol)}</span>
        <span class="dm dm-t">ATM &#8377;${fmt(res.atm_strike)}</span>
        <span class="dm dm-a">Expiry: ${esc(res.expiry || '—')}</span>
        <span class="dm dm-t">${esc(res.strategy_name)}</span>
        <span class="dm dm-t">${esc(res.timeframe.toUpperCase())}</span>
        <span class="dm dm-a">Latest: ${esc(res.latest_slot || '—')}</span>
        <button onclick="clearSym()" style="background:rgba(255,255,255,0.06);border:1px solid var(--border);color:var(--text-2);border-radius:7px;padding:4px 14px;font-family:var(--display);font-size:11px;font-weight:700;cursor:pointer;margin-left:auto;">&#8592; All Symbols</button>
    </div>`;

    var html = '';
    (res.data || []).forEach(function (r, i) {
        const sig    = r.signal || 'WAIT';
        const isCe   = sig === 'BUY_CE';
        const isPe   = sig === 'BUY_PE';

        let rowCls = i % 2 === 0 ? 'row-even' : 'row-odd';
        if (r.is_entry)  rowCls = 'row-entry';
        else if (r.is_latest) rowCls = 'row-latest';
        else if (isCe) rowCls += ' row-ce';
        else if (isPe) rowCls += ' row-pe';
        else rowCls += ' row-wait';

        const timePill = r.is_entry  ? `<span class="time-entry">&#9650; ${r.time}</span>`
                       : r.is_latest ? `<span class="time-latest">&#9660; ${r.time}</span>`
                       : `<span class="time-norm">${r.time}</span>`;

        html +=
            '<tr class="' + rowCls + '" data-sig="' + esc(sig) + '">'
            + '<td class="c-num">' + (i + 1) + '</td>'
            + '<td>' + timePill + '</td>'
            + '<td style="font-weight:700;color:var(--text-1);">' + (r.spot ? '&#8377;' + fmt(r.spot) : dash()) + '</td>'
            + '<td style="color:var(--teal);font-weight:700;">' + (r.combined_prem ? '&#8377;' + fmt(r.combined_prem) : dash()) + '</td>'
            + '<td style="font-size:10px;color:var(--text-2);">' + (r.pcr !== null ? r.pcr : dash()) + '</td>'
            // CE
            + '<td class="sep-ce" style="color:var(--ce);font-weight:700;">' + (r.ce_ltp !== null ? '&#8377;' + fmt(r.ce_ltp) : dash()) + '</td>'
            + '<td style="font-size:10px;color:var(--text-2);">' + (r.ce_oi !== null ? nInt(r.ce_oi) : dash()) + '</td>'
            // PE
            + '<td class="sep-pe" style="color:var(--pe);font-weight:700;">' + (r.pe_ltp !== null ? '&#8377;' + fmt(r.pe_ltp) : dash()) + '</td>'
            + '<td style="font-size:10px;color:var(--text-2);">' + (r.pe_oi !== null ? nInt(r.pe_oi) : dash()) + '</td>'
            // Signal
            + '<td class="sep-sig">' + sigBadge(sig) + '</td>'
            + '<td>' + scoreBars(r.ce_score, r.pe_score) + '</td>'
            + '<td>' + factorDots(r.factors) + '</td>'
            + '<td style="font-size:9px;color:var(--text-2);text-align:left;max-width:220px;white-space:normal;padding:7px 10px;">'
            + esc(r.reason || '—') + '</td>'
            + '</tr>';
    });

    // Detail uses slightly different header (time instead of symbol)
    const theadHtml = `<thead>
        <tr class="hdr-grp">
            <th colspan="5">Info</th>
            <th colspan="2" class="hce sep-ce">&#8679; CE</th>
            <th colspan="2" class="hpe sep-pe">&#8681; PE</th>
            <th colspan="4" class="ht sep-sig">&#9670; Signal (5 Factors)</th>
        </tr>
        <tr class="hdr-cols">
            <th>#</th><th>Time</th><th>Spot</th><th>Combined Prem</th><th>PCR</th>
            <th class="sep-ce hce">CE LTP</th><th class="hce">CE OI</th>
            <th class="sep-pe hpe">PE LTP</th><th class="hpe">PE OI</th>
            <th class="sep-sig ht">Signal</th>
            <th class="ht">Score CE/PE</th>
            <th class="ht">Factors</th>
            <th class="ht">Reason</th>
        </tr>
    </thead>`;

    $('#ios-content').html(
        hdr +
        '<div class="ios-card">'
        + '<div class="ios-card-hdr"><span class="ios-card-title">&#9670; ' + esc(res.symbol) + ' — ' + esc(res.strategy_name) + ' &nbsp;·&nbsp; ' + res.timeframe.toUpperCase() + '</span>'
        + '<span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);">' + (res.total_intervals || 0) + ' intervals</span></div>'
        + '<div class="ios-tscroll"><table class="ios-table">'
        + theadHtml
        + '<tbody>' + (html || '<tr><td colspan="13"><div class="ios-empty"><i class="fas fa-chart-area"></i>No candle data</div></td></tr>') + '</tbody>'
        + '</table></div>'
        + '<div class="factor-legend">'
        + '<strong>Signal fires when 3+ factors align on same side.</strong> &nbsp;'
        + '<span style="color:var(--teal);">&#9650; Teal row</span> = Entry 09:15 &nbsp;'
        + '<span style="color:var(--amber);">&#9660; Amber row</span> = Latest candle &nbsp;·&nbsp; '
        + 'Click "All Symbols" to return to summary view.'
        + '</div></div>'
    );
}

/* ── Jump to symbol detail ───────────────────────────────────────── */
function jumpToSym(sym) {
    document.getElementById('ios-sym').value = sym;
    loadData();
}

/* ── Stats ───────────────────────────────────────────────────────── */
function updateStats(res) {
    $('#st-total').text(res.total || 0);
    $('#st-ce').text(res.buy_ce_count || 0);
    $('#st-pe').text(res.buy_pe_count || 0);
    $('#st-wait').text(res.wait_count || 0);
    $('#ios-stats').show();
}

/* ── Helpers ─────────────────────────────────────────────────────── */
function sigBadge(sig) {
    if (sig === 'BUY_CE') return '<span class="sig-ce">&#8679; BUY CE</span>';
    if (sig === 'BUY_PE') return '<span class="sig-pe">&#8681; BUY PE</span>';
    return '<span class="sig-wait">&#8213; WAIT</span>';
}

function scoreBars(ceScore, peScore) {
    ceScore = ceScore || 0; peScore = peScore || 0;
    const cePct = Math.round((ceScore / 5) * 100);
    const pePct = Math.round((peScore / 5) * 100);
    return '<div style="display:flex;flex-direction:column;gap:3px;align-items:center;">'
        + '<div class="score-wrap">'
        + '<span class="score-num" style="color:var(--ce);">' + ceScore + '</span>'
        + '<div class="score-track"><div class="score-fill" style="width:' + cePct + '%;background:var(--ce);"></div></div>'
        + '</div>'
        + '<div class="score-wrap">'
        + '<span class="score-num" style="color:var(--pe);">' + peScore + '</span>'
        + '<div class="score-track"><div class="score-fill" style="width:' + pePct + '%;background:var(--pe);"></div></div>'
        + '</div>'
        + '</div>';
}

function factorDots(factors) {
    if (!factors || !factors.length) return dash();
    return '<div class="factor-dots">' + factors.map(f => {
        const cls = f.side === 'CE' ? 'fd fd-ce' : f.side === 'PE' ? 'fd fd-pe' : f.side === 'N/A' ? 'fd fd-na' : 'fd fd-neut';
        return '<span class="' + cls + '" title="' + esc(f.name + ': ' + f.detail) + '"></span>';
    }).join('') + '</div>';
}

function fmt(v)  { return v != null ? Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'; }
function nInt(v) { const n = Number(v) || 0; if (n >= 1e7) return (n/1e7).toFixed(2)+'Cr'; if (n >= 1e5) return (n/1e5).toFixed(2)+'L'; if (n >= 1e3) return (n/1e3).toFixed(1)+'K'; return n.toLocaleString('en-IN'); }
function dash()  { return '<span style="color:rgba(255,255,255,.15);font-size:9px;">—</span>'; }
function esc(s)  { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showLoading() {
    if (curMode === 'summary') {
        $('#ios-tbody').html('<tr><td colspan="15"><div class="ios-loading"><div class="ios-spinner"></div><div class="ios-spin-txt">Calculating signals…</div></div></td></tr>');
    } else {
        $('#ios-content').html('<div class="ios-card" style="padding:70px;text-align:center;"><div class="ios-spinner" style="margin:0 auto;"></div><div class="ios-spin-txt" style="margin-top:12px;">Loading…</div></div>');
    }
    $('#ios-stats').hide();
}
function emptyTable(msg) {
    if (!$('#ios-tbody').length) {
        $('#ios-content').html('<div class="ios-card"><div class="ios-empty"><i class="fas fa-chart-area"></i>' + (msg || 'No data') + '</div></div>');
        return;
    }
    $('#ios-tbody').html('<tr><td colspan="15"><div class="ios-empty"><i class="fas fa-chart-area"></i>' + (msg || 'Select strategy and click Analyze') + '</div></td></tr>');
    $('#ios-stats').hide();
}
function showWarn(msg) { $('#ios-warn').show(); $('#ios-warn-msg').text(msg||''); }
function hideWarn()    { $('#ios-warn').hide(); }
</script>
@endpush