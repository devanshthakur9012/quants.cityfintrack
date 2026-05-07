@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');

:root {
    --navy-900:#0a0f1e; --navy-800:#0d1428; --navy-700:#111b35;
    --border:rgba(255,255,255,0.07);
    --amber:#f59e0b; --emerald:#10b981; --rose:#f43f5e;
    --sky:#38bdf8; --purple:#a78bfa; --cyan:#06b6d4;
    --text-1:rgba(255,255,255,0.92); --text-2:rgba(255,255,255,0.55);
    --text-3:rgba(255,255,255,0.25);
    --mono:'JetBrains Mono',monospace; --display:'Rajdhani',sans-serif;
}
body { background:var(--navy-900); }

/* ── Header ── */
.ofs-header {
    background:linear-gradient(135deg,#0d1428 0%,#1a2744 50%,#0d1428 100%);
    border:1px solid var(--border); border-bottom:2px solid var(--cyan);
    border-radius:14px; padding:20px 28px; margin-bottom:18px;
    position:relative; overflow:hidden;
}
.ofs-header::before {
    content:'OI FLOW';
    position:absolute; right:24px; top:50%; transform:translateY(-50%);
    font-family:var(--display); font-size:72px; font-weight:700;
    color:rgba(6,182,212,0.05); letter-spacing:6px;
    pointer-events:none; user-select:none;
}
.ofs-title { font-family:var(--display); font-size:22px; font-weight:700; color:var(--text-1); margin:0; }
.ofs-title span {
    background:rgba(6,182,212,0.12); border:1px solid rgba(6,182,212,0.3);
    color:var(--cyan); font-size:10px; font-weight:700; padding:2px 9px;
    border-radius:4px; margin-left:8px; vertical-align:middle; letter-spacing:2px;
}
.ofs-sub { font-family:var(--mono); font-size:11px; color:var(--text-2); margin:7px 0 0; }
.logic-pill { display:inline-block; font-family:var(--mono); font-size:10px; font-weight:600; padding:2px 9px; border-radius:4px; margin:3px 2px; }
.lp-bull  { background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.25); color:var(--emerald); }
.lp-bear  { background:rgba(244,63,94,0.12);  border:1px solid rgba(244,63,94,0.25);  color:var(--rose); }
.lp-note  { background:rgba(6,182,212,0.10);  border:1px solid rgba(6,182,212,0.22);  color:var(--cyan); }

/* ── Controls ── */
.ofs-controls {
    background:var(--navy-800); border:1px solid var(--border);
    border-radius:12px; padding:14px 20px; margin-bottom:16px;
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
}
.ctrl-label { font-family:var(--display); font-size:10px; font-weight:700; color:var(--text-3); letter-spacing:1.5px; text-transform:uppercase; }
.ctrl-sep   { width:1px; height:28px; background:var(--border); flex-shrink:0; }

.tf-group { display:flex; gap:4px; }
.tf-btn { font-family:var(--display); font-size:12px; font-weight:700; padding:6px 15px; border-radius:7px; border:1px solid var(--border); background:transparent; color:var(--text-2); cursor:pointer; transition:.15s; }
.tf-btn:hover { border-color:rgba(6,182,212,0.4); color:var(--cyan); }
.tf-btn.active { background:rgba(6,182,212,0.15); border-color:var(--cyan); color:var(--cyan); }

.ofs-date { background:rgba(255,255,255,0.06); border:1px solid var(--border); border-radius:8px; color:var(--text-1); padding:5px 10px; font-family:var(--mono); font-size:11px; outline:none; }
.ofs-date::-webkit-calendar-picker-indicator { filter:invert(.55); cursor:pointer; }

.ofs-select { background:rgba(255,255,255,0.06); border:1px solid var(--border); color:var(--text-1); border-radius:8px; padding:5px 10px; font-family:var(--display); font-size:12px; font-weight:600; cursor:pointer; outline:none; min-width:140px; }
.ofs-select option { background:#0d1428; color:white; }

.ofs-sym-select { background:rgba(255,255,255,0.06); border:1px solid var(--border); color:var(--text-1); border-radius:8px; padding:5px 8px; font-family:var(--display); font-size:11px; font-weight:600; cursor:pointer; outline:none; min-width:150px; }
.ofs-sym-select option { background:#0d1428; }

.ofs-scan-btn { background:var(--cyan); color:#000; border:none; border-radius:8px; padding:7px 22px; font-family:var(--display); font-size:13px; font-weight:800; cursor:pointer; transition:.15s; letter-spacing:.3px; }
.ofs-scan-btn:hover { background:#67e8f9; }
.ofs-reset-btn { background:rgba(255,255,255,0.07); color:var(--text-2); border:1px solid var(--border); border-radius:8px; padding:6px 16px; font-family:var(--display); font-size:12px; font-weight:700; cursor:pointer; }
.ml-auto { margin-left:auto; }
.last-upd { font-family:var(--mono); font-size:9px; color:var(--text-3); }

/* ── Config warn ── */
.ofs-warn { background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3); border-radius:10px; padding:14px 18px; margin-bottom:14px; font-family:var(--display); font-size:13px; color:var(--amber); display:none; }

/* ── Stats ── */
.ofs-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.stat-box { background:var(--navy-800); border:1px solid var(--border); border-radius:10px; padding:12px 16px; min-width:110px; flex:1; }
.stat-box small { display:block; font-family:var(--display); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-3); margin-bottom:4px; }
.stat-box strong { display:block; font-family:var(--mono); font-size:1.25rem; font-weight:700; color:var(--text-1); }
.stat-box.s-ce   { border-left:3px solid var(--emerald); }
.stat-box.s-pe   { border-left:3px solid var(--rose); }
.stat-box.s-wait { border-left:3px solid var(--amber); }
.stat-box.s-bull { border-left:3px solid var(--emerald); }
.stat-box.s-bear { border-left:3px solid var(--rose); }

/* ── Main card ── */
.ofs-card { background:var(--navy-800); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
.ofs-card-hdr { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; background:var(--navy-700); }
.ofs-card-title { font-family:var(--display); font-size:14px; font-weight:700; color:var(--text-1); }

/* ── Table ── */
.ofs-tscroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.ofs-table { width:100%; border-collapse:collapse; font-family:var(--mono); min-width:900px; }

.ofs-table thead tr.hdr-grp th {
    padding:9px 10px 5px; text-align:center;
    font-family:var(--display); font-size:9px; font-weight:800;
    letter-spacing:1px; text-transform:uppercase;
    background:rgba(0,0,0,0.4); border-bottom:none; white-space:nowrap;
}
.ofs-table thead tr.hdr-cols th {
    padding:5px 10px 9px; text-align:center;
    font-family:var(--display); font-size:8px; font-weight:700;
    letter-spacing:.3px; text-transform:uppercase;
    background:rgba(0,0,0,0.3); color:var(--text-3);
    border-bottom:2px solid var(--border); white-space:nowrap;
}
.ofs-table tbody td {
    padding:8px 10px; text-align:center; font-size:11px;
    border-bottom:1px solid rgba(255,255,255,0.03);
    vertical-align:middle; white-space:nowrap; color:var(--text-2);
}
.ofs-table tbody tr:hover { background:rgba(255,255,255,0.04) !important; }
.row-even { background:rgba(255,255,255,0.01); }
.row-odd  { background:rgba(0,0,0,0.1); }
.row-bull { background:rgba(16,185,129,0.04)  !important; }
.row-bear { background:rgba(244,63,94,0.04)   !important; }

/* separators */
.sep-info  { border-left:2px solid rgba(6,182,212,0.3)   !important; }
.sep-oi    { border-left:2px solid rgba(245,158,11,0.35) !important; }
.sep-sig   { border-left:2px solid rgba(16,185,129,0.35) !important; }

/* group header colors */
.hdr-info  { color:var(--cyan)    !important; }
.hdr-oi    { color:var(--amber)   !important; }
.hdr-sig   { color:var(--emerald) !important; }

/* ── Cell types ── */
.c-num  { font-size:9px; color:var(--text-3); }
.c-date { font-size:11px; font-weight:700; color:var(--cyan); }
.c-sym  { font-size:12px; font-weight:800; color:var(--sky); }
.c-sym small { display:block; font-size:8px; color:var(--text-3); font-weight:400; }
.c-expiry { font-size:9px; color:var(--text-3); }
.c-atm { font-size:10px; color:var(--amber); font-weight:700; }
.c-fut { font-size:10px; color:var(--sky); font-weight:600; }
.c-oi  { font-size:10px; font-weight:700; color:var(--text-1); }
.c-oi small { display:block; font-size:8px; color:var(--text-3); font-weight:400; }
.pct-up   { color:#34d399; font-weight:700; }
.pct-down { color:#fb7185; font-weight:700; }
.pct-neu  { color:var(--text-3); }
.c-diff   { font-size:10px; font-weight:700; color:var(--amber); }
.c-pc     { font-size:10px; color:var(--text-2); }

/* ── Signal badges ── */
.sig-bull { display:inline-block; background:rgba(16,185,129,0.2); color:#34d399; border:1px solid rgba(16,185,129,0.45); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-bear { display:inline-block; background:rgba(244,63,94,0.2);  color:#fb7185; border:1px solid rgba(244,63,94,0.45);  border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:800; }
.sig-neut { display:inline-block; background:rgba(100,116,139,0.15); color:var(--text-3); border:1px solid rgba(255,255,255,0.08); border-radius:6px; padding:3px 10px; font-family:var(--display); font-size:10px; font-weight:600; }

/* ── Action badges ── */
.act-ce { display:inline-block; background:rgba(16,185,129,0.18); color:#34d399; border:1px solid rgba(16,185,129,0.4); border-radius:5px; padding:2px 8px; font-family:var(--display); font-size:9px; font-weight:800; }
.act-pe { display:inline-block; background:rgba(244,63,94,0.18);  color:#fb7185; border:1px solid rgba(244,63,94,0.4);  border-radius:5px; padding:2px 8px; font-family:var(--display); font-size:9px; font-weight:800; }
.act-wt { display:inline-block; background:rgba(245,158,11,0.12); color:var(--amber); border:1px solid rgba(245,158,11,0.3); border-radius:5px; padding:2px 8px; font-family:var(--display); font-size:9px; font-weight:700; }

/* ── Condition badge ── */
.cond-ce-pe { background:rgba(244,63,94,0.15); color:#fda4af; border:1px solid rgba(244,63,94,0.3); padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
.cond-pe-ce { background:rgba(16,185,129,0.15); color:#a7f3d0; border:1px solid rgba(16,185,129,0.3); padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
.cond-both  { background:rgba(167,139,250,0.15); color:#c4b5fd; border:1px solid rgba(167,139,250,0.3); padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
.cond-flat  { background:rgba(100,116,139,0.12); color:var(--text-3); border:1px solid rgba(255,255,255,0.08); padding:2px 7px; border-radius:4px; font-size:9px; display:inline-block; }

/* ── Strength rank ── */
.rank-1 { background:#7f1d1d; color:#fca5a5; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:800; display:inline-block; }
.rank-2 { background:#78350f; color:#fcd34d; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:800; display:inline-block; }
.rank-3 { background:#1e3a5f; color:#93c5fd; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
.rank-4 { background:#14532d; color:#86efac; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
.rank-n { background:rgba(100,116,139,0.12); color:var(--text-3); padding:2px 7px; border-radius:4px; font-size:9px; display:inline-block; }

/* ── Loading / empty ── */
.ofs-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px 20px; }
.ofs-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,0.1); border-top:3px solid var(--cyan); border-radius:50%; animation:ofsspin 1s linear infinite; }
@keyframes ofsspin { to { transform:rotate(360deg); } }
.ofs-spin-txt { color:var(--text-2); margin-top:12px; font-family:var(--display); font-size:13px; }
.ofs-empty { text-align:center; padding:60px 20px; color:var(--text-3); font-family:var(--display); font-size:13px; }
.ofs-empty i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }

/* ── Reason tooltip ── */
.reason-tip { font-size:9px; color:var(--text-3); margin-top:2px; max-width:180px; white-space:normal; line-height:1.3; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── HEADER ── --}}
    <div class="ofs-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="ofs-title">
                    &#9678; OI Flow Sentiment Analyzer
                    <span>EOD SIGNAL</span>
                </h4>
                <div class="ofs-sub" style="margin-top:8px;">
                    <span class="logic-pill lp-bear">CE↑ + PE↓ → BEARISH → BUY PE</span>
                    <span class="logic-pill lp-bull">CE↓ + PE↑ → BULLISH → BUY CE</span>
                    <span class="logic-pill lp-note">Both↑/↓ → stronger side wins</span>
                </div>
                <div class="ofs-sub" style="margin-top:5px; color:var(--text-3);">
                    Compares T (today 14:45) vs T-1 (prev day 15:00) CE/PE OI &nbsp;·&nbsp;
                    Config-scoped symbols only &nbsp;·&nbsp; All data from cp_option_ohlc_ tables
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONTROLS ── --}}
    <div class="ofs-controls">

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
        <input type="date" id="ofs-from" class="ofs-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
        <span class="ctrl-label">TO</span>
        <input type="date" id="ofs-to"   class="ofs-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">

        <div class="ctrl-sep"></div>

        {{-- Symbol --}}
        <span class="ctrl-label">SYMBOL</span>
        <select id="ofs-sym" class="ofs-sym-select" multiple size="1">
            <option value="">Loading…</option>
        </select>

        {{-- Action filter --}}
        <span class="ctrl-label">ACTION</span>
        <select id="ofs-action" class="ofs-select">
            <option value="">All Actions</option>
            <option value="BUY CE">BUY CE Only</option>
            <option value="BUY PE">BUY PE Only</option>
            <option value="WAIT">WAIT Only</option>
        </select>

        <button class="ofs-scan-btn" onclick="runAnalysis()">&#9670; Analyze</button>
        <button class="ofs-reset-btn" onclick="resetAll()">&#8630; Reset</button>

        <div class="ml-auto d-flex align-items-center gap-3">
            <span id="ofs-info" style="font-family:var(--mono);font-size:10px;color:var(--text-2);"></span>
            <span class="last-upd" id="ofs-upd"></span>
        </div>
    </div>

    {{-- ── CONFIG WARN ── --}}
    <div class="ofs-warn" id="ofs-warn">
        &#9888; <span id="ofs-warn-msg">No active Analysis Config for this timeframe.</span>
    </div>

    {{-- ── STATS ── --}}
    <div class="ofs-stats">
        <div class="stat-box">
            <small>Total Records</small>
            <strong id="st-total" style="color:var(--cyan);">0</strong>
        </div>
        <div class="stat-box s-ce">
            <small>BUY CE</small>
            <strong id="st-ce" style="color:var(--emerald);">0</strong>
        </div>
        <div class="stat-box s-pe">
            <small>BUY PE</small>
            <strong id="st-pe" style="color:var(--rose);">0</strong>
        </div>
        <div class="stat-box s-wait">
            <small>WAIT</small>
            <strong id="st-wt" style="color:var(--amber);">0</strong>
        </div>
        <div class="stat-box s-bull">
            <small>BULLISH</small>
            <strong id="st-bull" style="color:var(--emerald);">0</strong>
        </div>
        <div class="stat-box s-bear">
            <small>BEARISH</small>
            <strong id="st-bear" style="color:var(--rose);">0</strong>
        </div>
    </div>

    {{-- ── TABLE ── --}}
    <div class="ofs-card">
        <div class="ofs-card-hdr">
            <span class="ofs-card-title" id="ofs-card-title">&#9678; OI Flow Sentiment — 15 Min</span>
            <span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);" id="ofs-card-info"></span>
        </div>

        <div class="ofs-tscroll">
            <table class="ofs-table">
                <thead>
                    <tr class="hdr-grp">
                        <th colspan="5" class="hdr-info">Market Info</th>
                        <th colspan="4" class="hdr-oi sep-oi">&#9651; CE / PE OI Change</th>
                        <th colspan="4" class="hdr-sig sep-sig">&#9678; Signal</th>
                    </tr>
                    <tr class="hdr-cols">
                        {{-- Info --}}
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>ATM / FUT<br><span style="font-size:7px;opacity:.5;font-weight:400;">Strike / Price</span></th>
                        <th>Expiry</th>
                        {{-- OI --}}
                        <th class="sep-oi">CE OI<br><span style="font-size:7px;opacity:.5;font-weight:400;">Today vs Prev</span></th>
                        <th>CE %<br><span style="font-size:7px;opacity:.5;font-weight:400;">T vs T-1</span></th>
                        <th>PE OI<br><span style="font-size:7px;opacity:.5;font-weight:400;">Today vs Prev</span></th>
                        <th>PE %<br><span style="font-size:7px;opacity:.5;font-weight:400;">T vs T-1</span></th>
                        {{-- Signal --}}
                        <th class="sep-sig">Sentiment</th>
                        <th>Condition</th>
                        <th>Strength<br><span style="font-size:7px;opacity:.5;font-weight:400;">|CE−PE| diff</span></th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ofs-tbody">
                    <tr><td colspan="13">
                        <div class="ofs-empty">
                            <i class="fas fa-chart-bar"></i>
                            Select date range and click <strong>Analyze</strong>
                        </div>
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
// ═══════════════════════════════════════════════════════════
//  OI Flow Sentiment Analyzer — UI
// ═══════════════════════════════════════════════════════════

const ANALYZE_URL = '{{ route("oi-flow-sentiment.analyze") }}';
const SYM_URL     = '{{ route("oi-flow-sentiment.symbols") }}';
const todayStr    = '{{ now()->toDateString() }}';

let curTf    = '15min';
let symCache = {};

$(document).ready(function () { loadSymbols(); });

// ── State ─────────────────────────────────────────────────

function setTf(tf, btn) {
    curTf = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    $('#ofs-card-title').text('⊙ OI Flow Sentiment — ' + tf.toUpperCase());
    loadSymbols();
}

// ── Symbols ───────────────────────────────────────────────

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
    const sel  = document.getElementById('ofs-sym');
    const prev = Array.from(sel.selectedOptions).map(o => o.value);
    sel.innerHTML = syms.length
        ? syms.map(s => `<option value="${s}"${prev.includes(s)?' selected':''}>${s}</option>`).join('')
        : '<option value="" disabled>No symbols</option>';
    sel.size = Math.min(3, Math.max(1, syms.length));
}

// ── Run analysis ──────────────────────────────────────────

function runAnalysis() {
    const from   = $('#ofs-from').val();
    const to     = $('#ofs-to').val();
    const syms   = Array.from(document.getElementById('ofs-sym').selectedOptions).map(o => o.value).filter(Boolean);
    const action = $('#ofs-action').val();

    if (!from || !to) { alert('Select both dates'); return; }

    hideWarn();
    resetStats();

    $('#ofs-tbody').html(`<tr><td colspan="13"><div class="ofs-loading">
        <div class="ofs-spinner"></div>
        <div class="ofs-spin-txt">Calculating CE/PE OI flow for ${from} → ${to}&hellip;</div>
    </div></td></tr>`);

    $.ajax({
        url : ANALYZE_URL,
        type: 'GET',
        data: { timeframe: curTf, from_date: from, to_date: to,
                symbols: syms, filter_action: action },
        success(res) {
            if (res.no_config) { showWarn(res.message); emptyTable('No active config.'); return; }

            if (!res.success || !res.data || !res.data.length) {
                emptyTable(res.message || 'No signals found.');
                return;
            }

            renderTable(res.data);
            updateStats(res);

            $('#ofs-info').html(
                `<span style="color:var(--emerald)">CE: ${res.buy_ce_count}</span> &nbsp;·&nbsp;`
                + `<span style="color:var(--rose)">PE: ${res.buy_pe_count}</span>`
                + ` &nbsp;·&nbsp; TF: <span style="color:var(--cyan)">${res.timeframe}</span>`
            );
            $('#ofs-card-info').text(res.message);
            $('#ofs-upd').text('Updated ' + new Date().toLocaleTimeString());
        },
        error(xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Server error';
            emptyTable('⚠ ' + msg);
        }
    });
}

// ── Render ────────────────────────────────────────────────

function renderTable(data) {
    let html = '';
    let rowNum = 1;

    data.forEach(function (r, i) {
        const isBull = r.sentiment === 'BULLISH';
        const isBear = r.sentiment === 'BEARISH';
        const rowCls = isBull ? 'row-bull' : isBear ? 'row-bear' : '';
        const zebra  = i % 2 === 0 ? 'row-even' : 'row-odd';

        // Signal badge
        const sentBadge = isBull
            ? '<span class="sig-bull">&#8679; BULLISH</span>'
            : isBear
                ? '<span class="sig-bear">&#8681; BEARISH</span>'
                : '<span class="sig-neut">&#9135; NEUTRAL</span>';

        // Action badge
        const actBadge = r.trade_action === 'BUY CE'
            ? '<span class="act-ce">&#128200; BUY CE</span>'
            : r.trade_action === 'BUY PE'
                ? '<span class="act-pe">&#128201; BUY PE</span>'
                : '<span class="act-wt">&#9646; WAIT</span>';

        // Condition badge
        let condCls = 'cond-flat', condTxt = r.condition || 'Flat';
        if (condTxt.includes('CE ↑') && condTxt.includes('PE ↓')) condCls = 'cond-ce-pe';
        else if (condTxt.includes('CE ↓') && condTxt.includes('PE ↑')) condCls = 'cond-pe-ce';
        else if (condTxt.includes('Both')) condCls = 'cond-both';
        const condBadge = `<span class="${condCls}">${condTxt}</span>`;

        // Strength rank badge
        const rankMap = { 'Rank 1': 'rank-1', 'Rank 2': 'rank-2',
                          'Rank 3': 'rank-3', 'Rank 4': 'rank-4', 'Normal': 'rank-n' };
        const rankCls = rankMap[r.strength_rank] || 'rank-n';
        const rankBadge = `<span class="${rankCls}">${r.strength_rank}</span>
            <div style="font-size:8px;color:var(--text-3);margin-top:1px;">Δ ${r.oi_diff}%</div>`;

        // OI cells
        const cePctHtml  = pctCell(r.ce_oi_pct);
        const pePctHtml  = pctCell(r.pe_oi_pct);

        html += `<tr class="${rowCls} ${zebra}">
            <td class="c-num">${rowNum++}</td>
            <td class="c-date">${r.date}</td>
            <td class="c-sym">${esc(r.symbol)}</td>
            <td>
                ${r.atm_strike ? `<span class="c-atm">₹${nInt(r.atm_strike)}</span>` : '—'}
                ${r.fut_price  ? `<br><span class="c-fut">F:₹${f(r.fut_price)}</span>` : ''}
            </td>
            <td class="c-expiry">${r.expiry || '—'}</td>

            <td class="sep-oi c-oi">
                ${nInt(r.ce_oi)}
                <small>prev: ${nInt(r.ce_oi_prev)}</small>
            </td>
            <td>${cePctHtml}</td>
            <td class="c-oi">
                ${nInt(r.pe_oi)}
                <small>prev: ${nInt(r.pe_oi_prev)}</small>
            </td>
            <td>${pePctHtml}</td>

            <td class="sep-sig">${sentBadge}</td>
            <td>
                ${condBadge}
                ${r.reason ? `<div class="reason-tip">${esc(r.reason)}</div>` : ''}
            </td>
            <td>${rankBadge}</td>
            <td>${actBadge}</td>
        </tr>`;
    });

    if (!html) emptyTable('No results.');
    else $('#ofs-tbody').html(html);
}

// ── Stats ─────────────────────────────────────────────────

function updateStats(res) {
    $('#st-total').text(res.total_records  || 0);
    $('#st-ce').text(res.buy_ce_count      || 0);
    $('#st-pe').text(res.buy_pe_count      || 0);
    $('#st-wt').text(res.wait_count        || 0);
    $('#st-bull').text(res.bullish_count   || 0);
    $('#st-bear').text(res.bearish_count   || 0);
}

function resetStats() {
    ['st-total','st-ce','st-pe','st-wt','st-bull','st-bear'].forEach(id => $('#'+id).text('0'));
}

// ── Helpers ───────────────────────────────────────────────

function pctCell(v) {
    if (v == null) return '<span class="pct-neu">—</span>';
    const n = parseFloat(v) || 0;
    const cls = n > 0 ? 'pct-up' : n < 0 ? 'pct-down' : 'pct-neu';
    return `<span class="${cls}">${n > 0 ? '+' : ''}${n.toFixed(2)}%</span>`;
}

function f(v)    { return parseFloat(v||0).toFixed(2); }
function nInt(v) {
    const n = Number(v)||0;
    if (n >= 1e7) return (n/1e7).toFixed(2)+'Cr';
    if (n >= 1e5) return (n/1e5).toFixed(2)+'L';
    if (n >= 1e3) return (n/1e3).toFixed(1)+'K';
    return n.toLocaleString('en-IN');
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function emptyTable(msg) {
    $('#ofs-tbody').html(`<tr><td colspan="13"><div class="ofs-empty">
        <i class="fas fa-chart-bar"></i>${msg}
    </div></td></tr>`);
}

function showWarn(msg) { $('#ofs-warn').show(); $('#ofs-warn-msg').text(msg||''); }
function hideWarn()    { $('#ofs-warn').hide(); }

function resetAll() {
    $('#ofs-from,#ofs-to').val(todayStr);
    $('#ofs-sym option').prop('selected',false);
    $('#ofs-action').val('');
    resetStats();
    emptyTable('Reset — select dates and click Analyze');
    $('#ofs-info').text('');
    hideWarn();
}
</script>
@endpush