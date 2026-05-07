@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap');

:root {
    --navy-900: #0a0f1e;
    --navy-800: #0d1428;
    --navy-700: #111b35;
    --border:   rgba(255,255,255,0.07);
    --amber:    #f59e0b;
    --emerald:  #10b981;
    --rose:     #f43f5e;
    --sky:      #38bdf8;
    --purple:   #a78bfa;
    --text-1:   rgba(255,255,255,0.92);
    --text-2:   rgba(255,255,255,0.55);
    --text-3:   rgba(255,255,255,0.25);
    --mono:     'JetBrains Mono', monospace;
    --display:  'Rajdhani', sans-serif;
}

body { background: var(--navy-900); }

/* ── Page header ── */
.ohl-header {
    background: linear-gradient(135deg,#0d1428 0%,#1a2744 50%,#0d1428 100%);
    border: 1px solid var(--border);
    border-bottom: 2px solid #10b981;
    border-radius: 14px; padding: 20px 28px; margin-bottom: 18px;
    position: relative; overflow: hidden;
}
.ohl-header::before {
    content: 'O=H  O=L';
    position: absolute; right: 24px; top: 50%; transform: translateY(-50%);
    font-family: var(--display); font-size: 64px; font-weight: 700;
    color: rgba(16,185,129,0.05); letter-spacing: 6px;
    pointer-events: none; user-select: none;
}
.ohl-header-title {
    font-family: var(--display); font-size: 22px; font-weight: 700;
    color: var(--text-1); letter-spacing: 1px; margin: 0;
}
.ohl-header-title span {
    background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.3);
    color: var(--emerald); font-size: 10px; font-weight: 700; padding: 2px 9px;
    border-radius: 4px; margin-left: 8px; vertical-align: middle; letter-spacing: 2px;
}
.ohl-header-sub { font-family: var(--mono); font-size: 11px; color: var(--text-2); margin: 7px 0 0; }
.logic-pill {
    display: inline-block; font-family: var(--mono); font-size: 10px; font-weight: 600;
    padding: 2px 9px; border-radius: 4px; margin: 3px 2px;
}
.lp-oh { background: rgba(244,63,94,0.12); border: 1px solid rgba(244,63,94,0.25); color: var(--rose); }
.lp-ol { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); color: var(--emerald); }
.lp-tf { background: rgba(56,189,248,0.10); border: 1px solid rgba(56,189,248,0.22); color: var(--sky); }

/* ── Control bar ── */
.ohl-controls {
    background: var(--navy-800); border: 1px solid var(--border);
    border-radius: 12px; padding: 14px 20px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.ctrl-label { font-family: var(--display); font-size: 10px; font-weight: 700;
    color: var(--text-3); letter-spacing: 1.5px; text-transform: uppercase; }
.ctrl-sep { width: 1px; height: 28px; background: var(--border); flex-shrink: 0; }

/* timeframe btns */
.tf-group, .inst-group { display: flex; gap: 4px; }
.tf-btn {
    font-family: var(--display); font-size: 12px; font-weight: 700;
    padding: 6px 15px; border-radius: 7px; border: 1px solid var(--border);
    background: transparent; color: var(--text-2); cursor: pointer; transition: .15s;
}
.tf-btn:hover { border-color: rgba(245,158,11,0.4); color: var(--amber); }
.tf-btn.active { background: rgba(245,158,11,0.15); border-color: var(--amber); color: var(--amber); }

/* instrument btns */
.inst-btn {
    font-family: var(--display); font-size: 11px; font-weight: 700;
    padding: 6px 14px; border-radius: 7px; border: 1px solid var(--border);
    background: transparent; color: var(--text-2); cursor: pointer; transition: .15s;
}
.inst-btn.active[data-inst="stock"]  { background: rgba(16,185,129,0.12);  border-color: var(--emerald); color: var(--emerald); }
.inst-btn.active[data-inst="fut"]    { background: rgba(245,158,11,0.12);  border-color: var(--amber);   color: var(--amber); }
.inst-btn.active[data-inst="option"] { background: rgba(167,139,250,0.12); border-color: var(--purple);  color: var(--purple); }
.inst-btn:not(.active):hover { border-color: rgba(56,189,248,0.35); color: var(--sky); }

/* inputs */
.ohl-date { background: rgba(255,255,255,0.06); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text-1); padding: 5px 10px;
    font-family: var(--mono); font-size: 11px; font-weight: 600; outline: none; }
.ohl-date::-webkit-calendar-picker-indicator { filter: invert(.55); cursor: pointer; }

.ohl-select { background: rgba(255,255,255,0.06); border: 1px solid var(--border);
    color: var(--text-1); border-radius: 8px; padding: 6px 10px;
    font-family: var(--display); font-size: 12px; font-weight: 600;
    cursor: pointer; outline: none; min-width: 140px; }
.ohl-select option { background: #0d1428; color: white; }

.ohl-num { background: rgba(255,255,255,0.06); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text-1); padding: 5px 10px;
    font-family: var(--mono); font-size: 12px; font-weight: 600; width: 80px; outline: none; }

.ohl-load-btn { background: var(--emerald); color: #000; border: none; border-radius: 8px;
    padding: 7px 22px; font-family: var(--display); font-size: 13px; font-weight: 800;
    cursor: pointer; transition: .15s; }
.ohl-load-btn:hover { background: #34d399; }
.ohl-reset-btn { background: rgba(255,255,255,0.07); color: var(--text-2); border: 1px solid var(--border);
    border-radius: 8px; padding: 6px 16px; font-family: var(--display); font-size: 12px;
    font-weight: 700; cursor: pointer; }

.ml-auto { margin-left: auto; }
.last-upd { font-family: var(--mono); font-size: 9px; color: var(--text-3); }
.info-badge { font-family: var(--mono); font-size: 10px; color: var(--text-2); }

/* ── Config warn ── */
.ohl-warn { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3);
    border-radius: 10px; padding: 14px 18px; margin-bottom: 14px;
    font-family: var(--display); font-size: 13px; color: var(--amber); display: none; }

/* ── Two-table layout ── */
.ohl-tables-row { display: flex; gap: 16px; align-items: flex-start; }
@media (max-width: 900px) { .ohl-tables-row { flex-direction: column; } }

.ohl-card { flex: 1; min-width: 0; border-radius: 12px; overflow: hidden; border: 1px solid; }
.ohl-card.oh-card { border-color: rgba(244,63,94,0.4); }
.ohl-card.ol-card { border-color: rgba(16,185,129,0.4); }

.ohl-card-hdr { padding: 12px 18px; font-family: var(--display); font-size: 13px; font-weight: 700;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.oh-card .ohl-card-hdr { background: linear-gradient(135deg,rgba(244,63,94,0.22),rgba(244,63,94,0.08)); color: var(--rose); }
.ol-card .ohl-card-hdr { background: linear-gradient(135deg,rgba(16,185,129,0.22),rgba(16,185,129,0.08)); color: var(--emerald); }

.count-pill { background: rgba(255,255,255,0.15); color: white;
    padding: 2px 9px; border-radius: 10px; font-size: 10px; font-weight: 700; }
.tol-pill { background: rgba(245,158,11,0.2); color: var(--amber);
    padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;
    font-family: var(--mono); }

/* ── Table ── */
.ohl-table-scroll { overflow-x: auto; }
.ohl-table { width: 100%; border-collapse: collapse; min-width: 540px; font-family: var(--mono); }

.ohl-table thead th {
    padding: 9px 10px; text-align: center; font-family: var(--display);
    font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
    background: rgba(0,0,0,0.35); color: var(--text-3);
    border-bottom: 2px solid var(--border); white-space: nowrap;
}
.ohl-table tbody td {
    padding: 8px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle; white-space: nowrap; color: var(--text-2);
}
.ohl-table tbody tr:hover { background: rgba(255,255,255,0.04) !important; }
.row-even { background: rgba(255,255,255,0.01); }
.row-odd  { background: rgba(0,0,0,0.1); }

/* cell types */
.c-num   { font-size: 9px; color: var(--text-3); }
.c-date  { font-size: 10px; color: var(--amber); font-weight: 700; }
.c-sym   { font-size: 12px; font-weight: 800; color: var(--sky); }
.c-sym small { display:block; font-size:8px; color: var(--text-3); font-weight:400; }
.c-open  { color: var(--text-1); font-weight: 700; }
.c-h915  { color: #fb7185; font-weight: 700; }
.c-l915  { color: #6ee7b7; font-weight: 700; }
.c-dh    { color: var(--sky); font-weight: 700; }
.c-dl    { color: #fbbf24; font-weight: 700; }
.c-ltp   { color: white; font-weight: 700; }
.c-up    { color: #34d399; font-weight: 700; }
.c-down  { color: #fb7185; font-weight: 700; }
.c-neu   { color: var(--text-3); font-weight: 600; }

/* action badges */
.act-buy-pe { display:inline-block; background:rgba(244,63,94,0.2); color:#fb7185;
    border:1px solid rgba(244,63,94,0.45); border-radius:5px; padding:2px 8px;
    font-family:var(--display); font-size:9px; font-weight:800; }
.act-buy-ce { display:inline-block; background:rgba(16,185,129,0.2); color:#34d399;
    border:1px solid rgba(16,185,129,0.45); border-radius:5px; padding:2px 8px;
    font-family:var(--display); font-size:9px; font-weight:800; }
.act-sell-ce { display:inline-block; background:rgba(244,63,94,0.15); color:#fda4af;
    border:1px solid rgba(244,63,94,0.3); border-radius:5px; padding:2px 8px;
    font-family:var(--display); font-size:9px; font-weight:800; }
.act-sell-pe { display:inline-block; background:rgba(245,158,11,0.15); color:var(--amber);
    border:1px solid rgba(245,158,11,0.3); border-radius:5px; padding:2px 8px;
    font-family:var(--display); font-size:9px; font-weight:800; }

/* opt-type badge */
.opt-ce { background:rgba(16,185,129,0.15); color:#34d399; border:1px solid rgba(16,185,129,0.3);
    padding:1px 6px; border-radius:4px; font-size:9px; font-weight:700; }
.opt-pe { background:rgba(244,63,94,0.15); color:#fb7185; border:1px solid rgba(244,63,94,0.3);
    padding:1px 6px; border-radius:4px; font-size:9px; font-weight:700; }

/* no data */
.ohl-no-data { text-align:center; padding:48px 20px; color:var(--text-3);
    font-family:var(--display); font-size:12px; }
.ohl-no-data i { font-size:2rem; opacity:.3; display:block; margin-bottom:10px; }

/* loading */
.ohl-loading-wrap { position:relative; min-height:280px; }
.ohl-overlay { position:absolute; top:0; left:0; right:0; bottom:0;
    background:rgba(10,15,30,0.92); display:flex; flex-direction:column;
    align-items:center; justify-content:center; z-index:20; border-radius:12px;
    display:none; }
.ohl-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,0.1);
    border-top:3px solid var(--emerald); border-radius:50%; animation:ohlspin 1s linear infinite; }
@keyframes ohlspin { to { transform:rotate(360deg); } }
.ohl-spin-txt { color:var(--text-2); margin-top:12px; font-family:var(--display); font-size:13px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── HEADER ── --}}
    <div class="ohl-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="ohl-header-title">
                    Open=High &nbsp;/&nbsp; Open=Low
                    <span>9:15 SIGNAL</span>
                </h4>
                <div class="ohl-header-sub" style="margin-top:8px;">
                    <span class="logic-pill lp-oh">Open ≈ High → BUY PE (bearish open)</span>
                    <span class="logic-pill lp-ol">Open ≈ Low  → BUY CE (bullish open)</span>
                </div>
                <div class="ohl-header-sub" style="margin-top:5px;">
                    <span class="logic-pill lp-tf">15min: 09:15–09:30</span>
                    <span class="logic-pill lp-tf">30min: 09:15–09:45</span>
                    <span class="logic-pill lp-tf">1hr: 09:15–10:15</span>
                    &nbsp;—&nbsp; wider bars = different H/L hits per timeframe
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONTROLS ── --}}
    <div class="ohl-controls">

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

        {{-- From Date --}}
        <span class="ctrl-label">FROM</span>
        <input type="date" id="ohl-from" class="ohl-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">

        <span class="ctrl-label">TO</span>
        <input type="date" id="ohl-to" class="ohl-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">

        <div class="ctrl-sep"></div>

        {{-- Symbol --}}
        <span class="ctrl-label">SYMBOL</span>
        <select id="ohl-sym" class="ohl-select" multiple size="1">
            <option value="">Loading...</option>
        </select>

        {{-- Tolerance --}}
        <span class="ctrl-label">TOL</span>
        <input type="number" id="ohl-tol" class="ohl-num" value="1" min="0" max="100" step="0.5" title="Tolerance in points">
        <span style="font-size:10px;color:var(--text-3);font-family:var(--mono);">pts</span>

        <button class="ohl-load-btn" onclick="runAnalysis()">&#8635; Analyze</button>
        <button class="ohl-reset-btn" onclick="resetAll()">&#8630; Reset</button>

        <div class="ml-auto d-flex align-items-center gap-3">
            <span class="info-badge" id="ohl-info"></span>
            <span class="last-upd" id="ohl-upd"></span>
        </div>
    </div>

    {{-- ── CONFIG WARNING ── --}}
    <div class="ohl-warn" id="ohl-warn">
        &#9888; No active Analysis Config found for this timeframe.
        <span id="ohl-warn-msg" style="font-size:11px;color:var(--text-2);margin-left:8px;font-family:var(--mono);"></span>
    </div>

    {{-- ── TABLES ── --}}
    <div class="ohl-loading-wrap">
        <div class="ohl-overlay" id="ohl-overlay">
            <div class="ohl-spinner"></div>
            <div class="ohl-spin-txt">Analysing 09:15 candles&hellip;</div>
        </div>

        <div class="ohl-tables-row">

            {{-- OPEN=HIGH → BUY PE (bearish) --}}
            <div class="ohl-card oh-card">
                <div class="ohl-card-hdr">
                    &#128308; Open = High
                    <span style="font-size:11px;opacity:.7;">→</span>
                    <span class="act-buy-pe">BUY PE</span>
                    <span class="count-pill" id="oh-count">0</span>
                    <span class="tol-pill" id="oh-tol" style="display:none;"></span>
                </div>
                <div class="ohl-table-scroll">
                    <table class="ohl-table">
                        <thead id="oh-thead">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Symbol</th>
                                <th>Open</th>
                                <th>High (09:15)</th>
                                <th>Day High</th>
                                <th>LTP</th>
                                <th>Change</th>
                                <th>Chg %</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="oh-tbody">
                            <tr><td colspan="10"><div class="ohl-no-data"><i class="fas fa-chart-area"></i>Select dates and click Analyze</div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- OPEN=LOW → BUY CE (bullish) --}}
            <div class="ohl-card ol-card">
                <div class="ohl-card-hdr">
                    &#128994; Open = Low
                    <span style="font-size:11px;opacity:.7;">→</span>
                    <span class="act-buy-ce">BUY CE</span>
                    <span class="count-pill" id="ol-count">0</span>
                    <span class="tol-pill" id="ol-tol" style="display:none;"></span>
                </div>
                <div class="ohl-table-scroll">
                    <table class="ohl-table">
                        <thead id="ol-thead">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Symbol</th>
                                <th>Open</th>
                                <th>Low (09:15)</th>
                                <th>Day Low</th>
                                <th>LTP</th>
                                <th>Change</th>
                                <th>Chg %</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="ol-tbody">
                            <tr><td colspan="10"><div class="ohl-no-data"><i class="fas fa-chart-area"></i>Select dates and click Analyze</div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>{{-- /.ohl-tables-row --}}
    </div>

</div>
</section>
@endsection

@push('script')
<script>
// ═══════════════════════════════════════════════════════════════════
//  Open=High / Open=Low  — UI Logic
// ═══════════════════════════════════════════════════════════════════

const ANALYZE_URL  = '{{ route("open-hl.analyze") }}';
const SYMBOLS_URL  = '{{ route("open-hl.symbols") }}';
const todayStr     = '{{ now()->toDateString() }}';

let curTf   = '15min';
let curInst = 'stock';
let symCache = {}; // { 'stock-15min': [...] }

$(document).ready(function () { loadSymbols(); });

// ── State setters ─────────────────────────────────────────────────

function setTf(tf, btn) {
    curTf = tf;
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadSymbols();
}

function setInst(inst, btn) {
    curInst = inst;
    document.querySelectorAll('.inst-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Update table header for option (adds opt-type column)
    updateTableHeaders(inst);
    loadSymbols();
}

function updateTableHeaders(inst) {
    const isOpt = (inst === 'option');
    const ohExtra = isOpt ? '<th>Type</th>' : '';
    const olExtra = isOpt ? '<th>Type</th>' : '';
    const ohCols = 10 + (isOpt ? 1 : 0);
    const olCols = 10 + (isOpt ? 1 : 0);

    $('#oh-thead tr').html(`
        <th>#</th><th>Date</th><th>Symbol</th>
        ${ohExtra}
        <th>Open</th><th>High (09:15)</th><th>Day High</th>
        <th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>
    `);
    $('#ol-thead tr').html(`
        <th>#</th><th>Date</th><th>Symbol</th>
        ${olExtra}
        <th>Open</th><th>Low (09:15)</th><th>Day Low</th>
        <th>LTP</th><th>Change</th><th>Chg %</th><th>Action</th>
    `);
}

// ── Load symbols ──────────────────────────────────────────────────

function loadSymbols() {
    const key = curInst + '-' + curTf;
    if (symCache[key]) { rebuildSymSelect(symCache[key]); return; }

    $.get(SYMBOLS_URL, { timeframe: curTf, instrument: curInst }, function (res) {
        if (res.no_config) {
            showWarn('No active config for [' + curTf + '].');
            rebuildSymSelect([]);
            return;
        }
        hideWarn();
        symCache[key] = res.symbols || [];
        rebuildSymSelect(symCache[key]);
    });
}

function rebuildSymSelect(symbols) {
    const sel = document.getElementById('ohl-sym');
    const prev = Array.from(sel.selectedOptions).map(o => o.value);
    sel.innerHTML = symbols.length
        ? symbols.map(s => `<option value="${s}"${prev.includes(s) ? ' selected' : ''}>${s}</option>`).join('')
        : '<option value="" disabled>No symbols available</option>';
    sel.size = Math.min(3, Math.max(1, symbols.length));
}

// ── Run analysis ──────────────────────────────────────────────────

function runAnalysis() {
    const from     = $('#ohl-from').val();
    const to       = $('#ohl-to').val();
    const symbols  = Array.from(document.getElementById('ohl-sym').selectedOptions).map(o => o.value).filter(Boolean);
    const tol      = parseFloat($('#ohl-tol').val()) || 1;

    if (!from || !to) { alert('Select both dates'); return; }

    hideWarn();
    $('#ohl-overlay').css('display','flex');
    emptyRows('Loading…');

    $.ajax({
        url : ANALYZE_URL,
        type: 'GET',
        data: { timeframe: curTf, instrument: curInst, from_date: from, to_date: to,
                symbols: symbols, tolerance: tol },
        success(res) {
            $('#ohl-overlay').css('display','none');

            if (res.no_config) { showWarn(res.message); emptyRows('—'); return; }

            if (!res.success || !res.data || !res.data.length) {
                emptyRows(res.message || 'No signals found');
                updateCounts(0, 0, tol);
                $('#ohl-info').text('');
                return;
            }

            const ohRows = res.data.filter(r => r.signal === 'OPEN=HIGH');
            const olRows = res.data.filter(r => r.signal === 'OPEN=LOW');

            updateCounts(ohRows.length, olRows.length, res.tolerance);
            $('#ohl-info').html(
                '<span style="color:var(--rose);">O=H: ' + ohRows.length + '</span>'
                + ' &nbsp;·&nbsp; '
                + '<span style="color:var(--emerald);">O=L: ' + olRows.length + '</span>'
                + ' &nbsp;·&nbsp; TF: <span style="color:var(--amber);">' + res.timeframe + '</span>'
                + ' · ' + res.instrument
            );
            $('#ohl-upd').text('Updated ' + new Date().toLocaleTimeString());

            renderOH(ohRows);
            renderOL(olRows);
        },
        error(xhr) {
            $('#ohl-overlay').css('display','none');
            const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Server error';
            emptyRows('⚠ ' + msg);
        }
    });
}

// ── Render tables ─────────────────────────────────────────────────

function renderOH(rows) {
    if (!rows.length) {
        $('#oh-tbody').html('<tr><td colspan="11"><div class="ohl-no-data"><i class="fas fa-check-circle"></i>No Open=High signals</div></td></tr>');
        return;
    }
    const isOpt = (curInst === 'option');
    let html = '';
    rows.forEach((r, i) => {
        html += `<tr class="${i%2===0?'row-even':'row-odd'}">
            <td class="c-num">${i+1}</td>
            <td class="c-date">${r.date}</td>
            <td class="c-sym">${esc(r.symbol)}${r.expiry ? `<small>${r.expiry}</small>` : ''}</td>
            ${isOpt ? `<td>${optTypeBadge(r.opt_type)}</td>` : ''}
            <td class="c-open">₹${f(r.open)}</td>
            <td class="c-h915">₹${f(r.high_open)}</td>
            <td class="c-dh">₹${f(r.day_high)}</td>
            <td class="c-ltp">₹${f(r.ltp)}</td>
            <td>${changeTd(r.change)}</td>
            <td>${pctTd(r.change_pct)}</td>
            <td>${actionBadge(r.trade_action)}</td>
        </tr>`;
    });
    $('#oh-tbody').html(html);
}

function renderOL(rows) {
    if (!rows.length) {
        $('#ol-tbody').html('<tr><td colspan="11"><div class="ohl-no-data"><i class="fas fa-check-circle"></i>No Open=Low signals</div></td></tr>');
        return;
    }
    const isOpt = (curInst === 'option');
    let html = '';
    rows.forEach((r, i) => {
        html += `<tr class="${i%2===0?'row-even':'row-odd'}">
            <td class="c-num">${i+1}</td>
            <td class="c-date">${r.date}</td>
            <td class="c-sym">${esc(r.symbol)}${r.expiry ? `<small>${r.expiry}</small>` : ''}</td>
            ${isOpt ? `<td>${optTypeBadge(r.opt_type)}</td>` : ''}
            <td class="c-open">₹${f(r.open)}</td>
            <td class="c-l915">₹${f(r.low_open)}</td>
            <td class="c-dl">₹${f(r.day_low)}</td>
            <td class="c-ltp">₹${f(r.ltp)}</td>
            <td>${changeTd(r.change)}</td>
            <td>${pctTd(r.change_pct)}</td>
            <td>${actionBadge(r.trade_action)}</td>
        </tr>`;
    });
    $('#ol-tbody').html(html);
}

// ── Helpers ───────────────────────────────────────────────────────

function actionBadge(action) {
    const map = {
        'BUY PE':  '<span class="act-buy-pe">BUY PE</span>',
        'BUY CE':  '<span class="act-buy-ce">BUY CE</span>',
        'SELL CE': '<span class="act-sell-ce">SELL CE</span>',
        'SELL PE': '<span class="act-sell-pe">SELL PE</span>',
    };
    return map[action] || `<span style="color:var(--text-3);font-size:9px;">${action||'—'}</span>`;
}

function optTypeBadge(type) {
    if (type === 'CE') return '<span class="opt-ce">CE</span>';
    if (type === 'PE') return '<span class="opt-pe">PE</span>';
    return '<span style="color:var(--text-3);">—</span>';
}

function changeTd(v) {
    const n = parseFloat(v) || 0;
    if (n > 0) return `<span class="c-up">▲ ₹${f(n)}</span>`;
    if (n < 0) return `<span class="c-down">▼ ₹${f(Math.abs(n))}</span>`;
    return `<span class="c-neu">₹${f(n)}</span>`;
}

function pctTd(v) {
    const n = parseFloat(v) || 0;
    if (n > 0) return `<span class="c-up">+${f(n)}%</span>`;
    if (n < 0) return `<span class="c-down">${f(n)}%</span>`;
    return `<span class="c-neu">${f(n)}%</span>`;
}

function f(v)   { return parseFloat(v||0).toFixed(2); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function updateCounts(oh, ol, tol) {
    $('#oh-count').text(oh); $('#ol-count').text(ol);
    const tolTxt = '±' + parseFloat(tol).toFixed(1) + ' pt';
    if (tol !== undefined) {
        $('#oh-tol').text(tolTxt).show();
        $('#ol-tol').text(tolTxt).show();
    }
}

function emptyRows(msg) {
    const html = `<tr><td colspan="11"><div class="ohl-no-data"><i class="fas fa-chart-area"></i>${msg}</div></td></tr>`;
    $('#oh-tbody,#ol-tbody').html(html);
    $('#oh-count,#ol-count').text('0');
    $('#oh-tol,#ol-tol').hide();
}

function showWarn(msg) {
    $('#ohl-warn').show();
    $('#ohl-warn-msg').text(msg || '');
}
function hideWarn() { $('#ohl-warn').hide(); }

function resetAll() {
    $('#ohl-from,#ohl-to').val(todayStr);
    $('#ohl-tol').val('1');
    $('#ohl-sym option').prop('selected', false);
    emptyRows('Reset — select dates and click Analyze');
    $('#ohl-info').text('');
    hideWarn();
}
</script>
@endpush