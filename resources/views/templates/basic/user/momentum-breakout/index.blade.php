@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');

:root {
    --navy-900: #0a0f1e; --navy-800: #0d1428; --navy-700: #111b35;
    --border: rgba(255,255,255,0.07);
    --amber: #f59e0b; --emerald: #10b981; --rose: #f43f5e;
    --sky: #38bdf8; --purple: #a78bfa; --orange: #fb923c;
    --text-1: rgba(255,255,255,0.92); --text-2: rgba(255,255,255,0.55);
    --text-3: rgba(255,255,255,0.25);
    --mono: 'JetBrains Mono', monospace; --display: 'Rajdhani', sans-serif;
}
body { background: var(--navy-900); }

/* ── Header ── */
.mb-header {
    background: linear-gradient(135deg,#0d1428 0%,#1e2d52 50%,#0d1428 100%);
    border: 1px solid var(--border); border-bottom: 2px solid var(--orange);
    border-radius: 14px; padding: 20px 28px; margin-bottom: 18px;
    position: relative; overflow: hidden;
}
.mb-header::before {
    content: 'BREAKOUT';
    position: absolute; right: 24px; top: 50%; transform: translateY(-50%);
    font-family: var(--display); font-size: 72px; font-weight: 700;
    color: rgba(251,146,60,0.05); letter-spacing: 6px;
    pointer-events: none; user-select: none;
}
.mb-title { font-family: var(--display); font-size: 22px; font-weight: 700; color: var(--text-1); margin: 0; }
.mb-title span { background: rgba(251,146,60,0.12); border: 1px solid rgba(251,146,60,0.3);
    color: var(--orange); font-size: 10px; font-weight: 700; padding: 2px 9px;
    border-radius: 4px; margin-left: 8px; vertical-align: middle; letter-spacing: 2px; }
.mb-sub { font-family: var(--mono); font-size: 11px; color: var(--text-2); margin: 7px 0 0; }
.logic-pill { display: inline-block; font-family: var(--mono); font-size: 10px; font-weight: 600;
    padding: 2px 9px; border-radius: 4px; margin: 3px 2px; }
.lp-ce { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); color: var(--emerald); }
.lp-pe { background: rgba(244,63,94,0.12); border: 1px solid rgba(244,63,94,0.25); color: var(--rose); }
.lp-note { background: rgba(56,189,248,0.10); border: 1px solid rgba(56,189,248,0.22); color: var(--sky); }

/* ── Controls ── */
.mb-controls {
    background: var(--navy-800); border: 1px solid var(--border);
    border-radius: 12px; padding: 14px 20px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.ctrl-label { font-family: var(--display); font-size: 10px; font-weight: 700;
    color: var(--text-3); letter-spacing: 1.5px; text-transform: uppercase; }
.ctrl-sep { width: 1px; height: 28px; background: var(--border); flex-shrink: 0; }

.tf-group, .inst-group { display: flex; gap: 4px; }
.tf-btn { font-family: var(--display); font-size: 12px; font-weight: 700;
    padding: 6px 15px; border-radius: 7px; border: 1px solid var(--border);
    background: transparent; color: var(--text-2); cursor: pointer; transition: .15s; }
.tf-btn:hover { border-color: rgba(251,146,60,0.4); color: var(--orange); }
.tf-btn.active { background: rgba(251,146,60,0.15); border-color: var(--orange); color: var(--orange); }

.inst-btn { font-family: var(--display); font-size: 11px; font-weight: 700;
    padding: 6px 14px; border-radius: 7px; border: 1px solid var(--border);
    background: transparent; color: var(--text-2); cursor: pointer; transition: .15s; }
.inst-btn:not(.active):hover { border-color: rgba(56,189,248,0.35); color: var(--sky); }
.inst-btn.active[data-inst="stock"]  { background: rgba(16,185,129,0.12); border-color: var(--emerald); color: var(--emerald); }
.inst-btn.active[data-inst="fut"]    { background: rgba(245,158,11,0.12); border-color: var(--amber);   color: var(--amber); }
.inst-btn.active[data-inst="option"] { background: rgba(167,139,250,0.12); border-color: var(--purple); color: var(--purple); }

.mb-date { background: rgba(255,255,255,0.06); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text-1); padding: 5px 10px;
    font-family: var(--mono); font-size: 11px; outline: none; }
.mb-date::-webkit-calendar-picker-indicator { filter: invert(.55); cursor: pointer; }

.mb-select { background: rgba(255,255,255,0.06); border: 1px solid var(--border);
    color: var(--text-1); border-radius: 8px; padding: 5px 10px;
    font-family: var(--display); font-size: 12px; font-weight: 600;
    cursor: pointer; outline: none; min-width: 130px; }
.mb-select option { background: #0d1428; color: white; }

.mb-num { background: rgba(255,255,255,0.06); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text-1); padding: 5px 10px;
    font-family: var(--mono); font-size: 12px; font-weight: 600;
    width: 72px; outline: none; }

.mb-sym-select { background: rgba(255,255,255,0.06); border: 1px solid var(--border);
    color: var(--text-1); border-radius: 8px; padding: 5px 8px;
    font-family: var(--display); font-size: 11px; font-weight: 600;
    cursor: pointer; outline: none; min-width: 150px; }
.mb-sym-select option { background: #0d1428; }

.mb-scan-btn { background: var(--orange); color: #000; border: none; border-radius: 8px;
    padding: 7px 22px; font-family: var(--display); font-size: 13px; font-weight: 800;
    cursor: pointer; transition: .15s; letter-spacing: .3px; }
.mb-scan-btn:hover { background: #fdba74; }
.mb-reset-btn { background: rgba(255,255,255,0.07); color: var(--text-2); border: 1px solid var(--border);
    border-radius: 8px; padding: 6px 16px; font-family: var(--display); font-size: 12px;
    font-weight: 700; cursor: pointer; }
.ml-auto { margin-left: auto; }
.last-upd { font-family: var(--mono); font-size: 9px; color: var(--text-3); }

/* ── Stats row ── */
.mb-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
.stat-box { background: var(--navy-800); border: 1px solid var(--border); border-radius: 10px;
    padding: 12px 16px; min-width: 120px; flex: 1; }
.stat-box small { display: block; font-family: var(--display); font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px; color: var(--text-3); margin-bottom: 4px; }
.stat-box strong { display: block; font-family: var(--mono); font-size: 1.25rem; font-weight: 700;
    color: var(--text-1); }
.stat-box.s-ce    { border-left: 3px solid var(--emerald); }
.stat-box.s-pe    { border-left: 3px solid var(--rose); }
.stat-box.s-nt    { border-left: 3px solid var(--text-3); }
.stat-box.s-inv   { border-left: 3px solid var(--sky); }
.stat-box.s-pl    { border-left: 3px solid var(--orange); }
.stat-box.s-win   { border-left: 3px solid var(--purple); }

/* ── Exit-time bar ── */
.mb-exit-bar {
    background: rgba(251,146,60,0.07); border: 1px solid rgba(251,146,60,0.25);
    border-radius: 10px; padding: 12px 18px; margin-bottom: 14px;
    display: none; align-items: center; gap: 16px; flex-wrap: wrap;
}
.mb-calc-btn { background: var(--purple); color: white; border: none; border-radius: 8px;
    padding: 7px 20px; font-family: var(--display); font-size: 12px; font-weight: 800;
    cursor: pointer; transition: .15s; }
.mb-calc-btn:hover { background: #c4b5fd; }

/* ── Config warn ── */
.mb-warn { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3);
    border-radius: 10px; padding: 14px 18px; margin-bottom: 14px;
    font-family: var(--display); font-size: 13px; color: var(--amber); display: none; }

/* ── Main card ── */
.mb-card { background: var(--navy-800); border: 1px solid var(--border); border-radius: 14px;
    overflow: hidden; }
.mb-card-hdr { padding: 14px 20px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px; background: var(--navy-700); }
.mb-card-title { font-family: var(--display); font-size: 14px; font-weight: 700;
    color: var(--text-1); letter-spacing: .3px; }

/* ── Table ── */
.mb-tscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.mb-table { width: 100%; border-collapse: collapse; font-family: var(--mono);
    min-width: 1200px; }
.mb-table thead tr.hdr-grp th {
    padding: 9px 10px 5px; text-align: center;
    font-family: var(--display); font-size: 9px; font-weight: 800;
    letter-spacing: 1px; text-transform: uppercase;
    background: rgba(0,0,0,0.4); border-bottom: none; white-space: nowrap;
}
.mb-table thead tr.hdr-cols th {
    padding: 5px 10px 9px; text-align: center;
    font-family: var(--display); font-size: 8px; font-weight: 700;
    letter-spacing: .3px; text-transform: uppercase;
    background: rgba(0,0,0,0.3); color: var(--text-3);
    border-bottom: 2px solid var(--border); white-space: nowrap;
}
.mb-table tbody td {
    padding: 7px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    vertical-align: middle; white-space: nowrap; color: var(--text-2);
}
.mb-table tbody tr:hover { background: rgba(255,255,255,0.04) !important; }
.row-even { background: rgba(255,255,255,0.01); }
.row-odd  { background: rgba(0,0,0,0.1); }
.row-ce   { background: rgba(16,185,129,0.04)  !important; }
.row-pe   { background: rgba(244,63,94,0.04)   !important; }
.row-nt   { background: rgba(0,0,0,0.08)        !important; opacity: .6; }

/* separators */
.sep-info  { border-left: 2px solid rgba(56,189,248,0.3)  !important; }
.sep-sig   { border-left: 2px solid rgba(251,146,60,0.35) !important; }
.sep-pl    { border-left: 2px solid rgba(167,139,250,0.35)!important; }

/* group headers */
.hdr-info  { color: var(--sky)    !important; }
.hdr-sig   { color: var(--orange) !important; }
.hdr-pl    { color: var(--purple) !important; }

/* cells */
.c-num  { font-size: 9px; color: var(--text-3); }
.c-date { font-size: 11px; font-weight: 700; color: var(--amber); }
.c-sym  { font-size: 12px; font-weight: 800; color: var(--sky); }
.c-sym small { display: block; font-size: 8px; color: var(--text-3); font-weight: 400; }
.c-time { color: var(--orange); font-weight: 700; }
.c-open { color: var(--text-1); font-weight: 600; }
.c-sig-px { color: var(--text-1); font-weight: 700; }
.c-dh   { color: #fb7185; font-weight: 600; }
.c-dl   { color: #6ee7b7; font-weight: 600; }
.c-lc   { color: var(--sky); font-weight: 600; }
.c-vol  { font-size: 9px; color: var(--text-3); }
.c-oi   { font-size: 9px; color: var(--text-3); }

/* signal badge */
.sig-ce { display:inline-block; background:rgba(16,185,129,0.2); color:#34d399;
    border:1px solid rgba(16,185,129,0.45); border-radius:6px; padding:3px 10px;
    font-family:var(--display); font-size:10px; font-weight:800; }
.sig-pe { display:inline-block; background:rgba(244,63,94,0.2); color:#fb7185;
    border:1px solid rgba(244,63,94,0.45); border-radius:6px; padding:3px 10px;
    font-family:var(--display); font-size:10px; font-weight:800; }
.sig-nt { display:inline-block; background:rgba(100,116,139,0.15); color:var(--text-3);
    border:1px solid rgba(255,255,255,0.08); border-radius:6px; padding:3px 10px;
    font-family:var(--display); font-size:10px; font-weight:600; }

/* pct */
.pct-up   { color: #34d399; font-weight: 700; }
.pct-down { color: #fb7185; font-weight: 700; }
.pct-neu  { color: var(--text-3); }

/* P/L cells */
.pl-pend { color: var(--text-3); font-size: 9px; font-style: italic; }
.pl-up   { color: #34d399; font-weight: 700; }
.pl-down { color: #fb7185; font-weight: 700; }
.best-badge { display:inline-block; background:rgba(245,158,11,0.15); color:var(--amber);
    border:1px solid rgba(245,158,11,0.3); border-radius:4px; padding:2px 7px;
    font-size:10px; font-weight:800; }

/* loading / empty */
.mb-loading { display:flex; flex-direction:column; align-items:center;
    justify-content:center; padding:70px 20px; }
.mb-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,0.1);
    border-top:3px solid var(--orange); border-radius:50%; animation:mbspin 1s linear infinite; }
@keyframes mbspin { to { transform:rotate(360deg); } }
.mb-spin-txt { color:var(--text-2); margin-top:12px; font-family:var(--display); font-size:13px; }
.mb-empty { text-align:center; padding:60px 20px; color:var(--text-3);
    font-family:var(--display); font-size:13px; }
.mb-empty i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }

.show-nt-wrap { display: flex; align-items: center; gap: 6px;
    font-family: var(--display); font-size: 11px; color: var(--text-2); }
.show-nt-wrap input[type="checkbox"] { accent-color: var(--orange); cursor: pointer; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── HEADER ── --}}
    <div class="mb-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="mb-title">
                    &#9889; Momentum Breakout Scanner
                    <span>INTRADAY</span>
                </h4>
                <div class="mb-sub" style="margin-top:8px;">
                    <span class="logic-pill lp-ce">Close ≥ Open + X% → BUY CE ↑</span>
                    <span class="logic-pill lp-pe">Close ≤ Open − X% → BUY PE ↓</span>
                    <span class="logic-pill lp-note">First trigger per symbol per day · Scans all candles 09:15 → 15:15</span>
                </div>
                <div class="mb-sub" style="margin-top:5px; color: var(--text-3);">
                    P/L calculated from ATM option prices in cp_option_ohlc_ table &nbsp;·&nbsp;
                    No external API · All data from configured symbols
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONTROLS ── --}}
    <div class="mb-controls">

        {{-- Timeframe --}}
        <span class="ctrl-label">TF</span>
        <div class="tf-group">
            <button class="tf-btn active" data-tf="15min" onclick="setTf('15min',this)">15 Min</button>
            <button class="tf-btn"        data-tf="30min" onclick="setTf('30min',this)">30 Min</button>
            <button class="tf-btn"        data-tf="1hr"   onclick="setTf('1hr',this)">1 Hour</button>
        </div>

        <div class="ctrl-sep"></div>

        {{-- Instrument --}}
        <span class="ctrl-label">TYPE</span>
        <div class="inst-group">
            <button class="inst-btn active" data-inst="stock"  onclick="setInst('stock',this)">&#9679; Stock EQ</button>
            <button class="inst-btn"        data-inst="fut"    onclick="setInst('fut',this)">&#9651; Futures</button>
            <button class="inst-btn"        data-inst="option" onclick="setInst('option',this)">&#9670; Options</button>
        </div>

        <div class="ctrl-sep"></div>

        {{-- Dates --}}
        <span class="ctrl-label">FROM</span>
        <input type="date" id="mb-from" class="mb-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
        <span class="ctrl-label">TO</span>
        <input type="date" id="mb-to"   class="mb-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">

        <div class="ctrl-sep"></div>

        {{-- Threshold --}}
        <span class="ctrl-label">MOVE %</span>
        <select id="mb-threshold" class="mb-select">
            <option value="0.5">0.5%</option>
            <option value="0.75">0.75%</option>
            <option value="1.0" selected>1.0%</option>
            <option value="1.25">1.25%</option>
            <option value="1.5">1.5%</option>
            <option value="2.0">2.0%</option>
            <option value="2.5">2.5%</option>
            <option value="3.0">3.0%</option>
        </select>

        <div class="ctrl-sep"></div>

        {{-- Symbol --}}
        <span class="ctrl-label">SYMBOL</span>
        <select id="mb-sym" class="mb-sym-select" multiple size="1">
            <option value="">Loading…</option>
        </select>

        {{-- Show NO_TRADE --}}
        <label class="show-nt-wrap">
            <input type="checkbox" id="mb-show-nt">
            Show No-Trade
        </label>

        <button class="mb-scan-btn" onclick="runScan()">&#9650; Scan</button>
        <button class="mb-reset-btn" onclick="resetAll()">&#8630; Reset</button>

        <div class="ml-auto d-flex align-items-center gap-3">
            <span id="mb-info" style="font-family:var(--mono);font-size:10px;color:var(--text-2);"></span>
            <span class="last-upd" id="mb-upd"></span>
        </div>
    </div>

    {{-- ── CONFIG WARN ── --}}
    <div class="mb-warn" id="mb-warn">
        &#9888; <span id="mb-warn-msg">No active Analysis Config for this timeframe.</span>
    </div>

    {{-- ── STATS ROW ── --}}
    <div class="mb-stats">
        <div class="stat-box">
            <small>Total Records</small>
            <strong id="st-total" class="text-info">0</strong>
        </div>
        <div class="stat-box s-ce">
            <small>BUY CE</small>
            <strong id="st-ce" style="color:var(--emerald);">0</strong>
        </div>
        <div class="stat-box s-pe">
            <small>BUY PE</small>
            <strong id="st-pe" style="color:var(--rose);">0</strong>
        </div>
        <div class="stat-box s-nt">
            <small>No Trade</small>
            <strong id="st-nt" style="color:var(--text-3);">0</strong>
        </div>
    </div>

    {{-- ── TABLE ── --}}
    <div class="mb-card">
        <div class="mb-card-hdr">
            <span class="mb-card-title" id="mb-card-title">
                &#9889; Momentum Breakout — Stock EQ &nbsp;·&nbsp; 15 Min
            </span>
            <span style="font-size:10px;color:var(--text-3);margin-left:auto;font-family:var(--mono);" id="mb-card-info"></span>
        </div>

        <div class="mb-tscroll">
            <table class="mb-table">
                <thead>
                    <tr class="hdr-grp">
                        <th colspan="4" class="hdr-info">Market Info</th>
                        <th colspan="5" class="hdr-sig sep-sig">&#9889; Breakout Signal</th>
                    </tr>
                    <tr class="hdr-cols">
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>Day Open</th>

                        <th class="sep-sig">Signal</th>
                        <th>Time</th>
                        <th>Price</th>
                        <th>Chg %</th>
                        <th>Day H/L</th>
                    </tr>
                </thead>
                <tbody id="mb-tbody">
                    <tr><td colspan="9">
                        <div class="mb-empty">
                            <i class="fas fa-chart-line"></i>
                            Select date range and click <strong>Scan</strong>
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
// ═══════════════════════════════════════════════════════════════════
//  Momentum Breakout Scanner — UI
// ═══════════════════════════════════════════════════════════════════

const SCAN_URL  = '{{ route("momentum-breakout.scan") }}';
const SYM_URL   = '{{ route("momentum-breakout.symbols") }}';
const todayStr  = '{{ now()->toDateString() }}';

let curTf       = '15min';
let curInst     = 'stock';
let scanData    = [];
let symCache    = {};

const INST_LABELS = { stock: 'Stock EQ', fut: 'Futures', option: 'Options' };

$(document).ready(function () { loadSymbols(); });

// ── State ─────────────────────────────────────────────────────────

function setTf(tf, btn) {
    curTf = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadSymbols();
    updateCardTitle();
}

function setInst(inst, btn) {
    curInst = inst;
    document.querySelectorAll('.inst-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadSymbols();
    updateCardTitle();
}

function updateCardTitle() {
    $('#mb-card-title').text('⚡ Momentum Breakout — ' + INST_LABELS[curInst] + ' · ' + curTf.toUpperCase());
}

// ── Symbols ───────────────────────────────────────────────────────

function loadSymbols() {
    const key = curInst + '-' + curTf;
    if (symCache[key]) { rebuildSym(symCache[key]); return; }

    $.get(SYM_URL, { timeframe: curTf }, function (res) {
        if (res.no_config) { showWarn(res.message || ''); rebuildSym([]); return; }
        hideWarn();
        symCache[key] = res.symbols || [];
        rebuildSym(symCache[key]);
    });
}

function rebuildSym(syms) {
    const sel  = document.getElementById('mb-sym');
    const prev = Array.from(sel.selectedOptions).map(o => o.value);
    sel.innerHTML = syms.length
        ? syms.map(s => `<option value="${s}"${prev.includes(s)?' selected':''}>${s}</option>`).join('')
        : '<option value="" disabled>No symbols</option>';
    sel.size = Math.min(3, Math.max(1, syms.length));
}

// ── Scan ──────────────────────────────────────────────────────────

function runScan() {
    const from      = $('#mb-from').val();
    const to        = $('#mb-to').val();
    const symbols   = Array.from(document.getElementById('mb-sym').selectedOptions).map(o => o.value).filter(Boolean);
    const threshold = parseFloat($('#mb-threshold').val()) || 1.0;
    const showNT    = $('#mb-show-nt').is(':checked') ? 1 : 0;

    if (!from || !to) { alert('Select both dates'); return; }

    hideWarn();
    scanData = [];
    resetStats();

    $('#mb-tbody').html(`<tr><td colspan="9"><div class="mb-loading">
        <div class="mb-spinner"></div>
        <div class="mb-spin-txt">Scanning candles for ${threshold}% breakout…</div>
    </div></td></tr>`);

    $.ajax({
        url : SCAN_URL,
        type: 'GET',
        data: { timeframe: curTf, instrument: curInst, from_date: from, to_date: to,
                symbols, threshold, show_no_trade: showNT },
        success(res) {
            if (res.no_config) { showWarn(res.message); emptyTable('No active config for this timeframe.'); return; }

            if (!res.success || !res.data || !res.data.length) {
                emptyTable(res.message || 'No signals found.');
                return;
            }

            scanData = res.data;
            renderTable(scanData);
            updateStats(res);
            updateCardTitle();

            $('#mb-info').html(
                `<span style="color:var(--emerald)">CE: ${res.buy_ce_count}</span> &nbsp;·&nbsp;`
                + `<span style="color:var(--rose)">PE: ${res.buy_pe_count}</span>`
                + ` &nbsp;·&nbsp; TF: <span style="color:var(--orange)">${res.timeframe}</span>`
                + ` &nbsp;·&nbsp; Threshold: ±${res.threshold}%`
            );
            $('#mb-card-info').text(res.message);
            $('#mb-upd').text('Updated ' + new Date().toLocaleTimeString());
        },
        error(xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Server error';
            emptyTable('⚠ ' + msg);
        }
    });
}

// ── P/L Calc ─────────────────────────────────────────────────────

// ── Table render ──────────────────────────────────────────────────

function renderTable(data) {
    if (!data || !data.length) { emptyTable('No data.'); return; }

    let html = '';
    let rowNum = 1;

    data.forEach(function(r, i) {
        const isNT = (r.signal === 'NO_TRADE');
        const isCE = (r.signal === 'BUY_CE');

        const rowCls = isNT ? 'row-nt' : isCE ? 'row-ce' : 'row-pe';
        const zebra  = i % 2 === 0 ? 'row-even' : 'row-odd';

        const sigBadge = isNT
            ? '<span class="sig-nt">— No Trade —</span>'
            : isCE
                ? '<span class="sig-ce">&#8679; BUY CE</span>'
                : '<span class="sig-pe">&#8681; BUY PE</span>';

        const pctHtml = r.change_pct != null
            ? `<span class="${r.change_pct > 0 ? 'pct-up' : r.change_pct < 0 ? 'pct-down' : 'pct-neu'}">${r.change_pct > 0 ? '+' : ''}${r.change_pct}%</span>`
            : '<span class="pct-neu">—</span>';

        const hlHtml = (r.day_high && r.day_low)
            ? `<span class="c-dh">₹${f(r.day_high)}</span> <span style="color:var(--text-3);">/</span> <span class="c-dl">₹${f(r.day_low)}</span>`
            : '—';

        html += `<tr class="${rowCls} ${zebra}">
            <td class="c-num">${isNT ? '' : rowNum++}</td>
            <td class="c-date">${r.date}</td>
            <td class="c-sym">${esc(r.symbol)}${r.expiry ? `<small>${r.expiry}</small>` : ''}</td>
            <td class="c-open">₹${r.day_open ? f(r.day_open) : '—'}</td>
            <td class="sep-sig">${sigBadge}</td>
            <td class="c-time">${r.signal_time || '—'}</td>
            <td class="c-sig-px">${r.signal_price ? '₹' + f(r.signal_price) : '—'}</td>
            <td>${pctHtml}</td>
            <td>${hlHtml}</td>
        </tr>`;
    });

    if (!html) emptyTable('No results.');
    else $('#mb-tbody').html(html);
}

// ── Stats ─────────────────────────────────────────────────────────

function updateStats(res) {
    $('#st-total').text(res.total_records || 0);
    $('#st-ce').text(res.buy_ce_count || 0);
    $('#st-pe').text(res.buy_pe_count || 0);
    $('#st-nt').text(res.no_trade_count || 0);
}

function resetStats() {
    ['st-total','st-ce','st-pe','st-nt'].forEach(id => $('#'+id).text('0'));
}

// ── Helpers ───────────────────────────────────────────────────────

function f(v)    { return parseFloat(v || 0).toFixed(2); }
function fInt(v) { return Math.round(parseFloat(v || 0)).toLocaleString('en-IN'); }
function esc(s)  { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function emptyTable(msg) {
    $('#mb-tbody').html(`<tr><td colspan="9"><div class="mb-empty">
        <i class="fas fa-chart-area"></i>${msg}
    </div></td></tr>`);
}

function showWarn(msg) { $('#mb-warn').show(); $('#mb-warn-msg').text(msg || ''); }
function hideWarn()    { $('#mb-warn').hide(); }

function resetAll() {
    $('#mb-from,#mb-to').val(todayStr);
    $('#mb-threshold').val('1.0');
    $('#mb-sym option').prop('selected', false);
    $('#mb-show-nt').prop('checked', false);
    scanData = [];
    resetStats();
    emptyTable('Reset — select dates and click Scan');
    $('#mb-info').text('');
    hideWarn();
}
</script>
@endpush