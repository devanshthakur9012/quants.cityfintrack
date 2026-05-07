@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════════════════
   SMART VOLUME DAILY EOD SIGNAL — Next Day Predictor
   Dark terminal aesthetic with high-signal visual hierarchy
═══════════════════════════════════════════════════════════════ */

:root {
    --bull: #00e676;
    --bear: #ff1744;
    --warn: #ffc107;
    --accent: #ff6b00;
    --accent2: #ff9f00;
    --purple: #e040fb;
    --blue: #4fc3f7;
    --bg-deep: #06060f;
    --bg-card: #0a0a18;
    --bg-row: #0d0d1f;
    --border: rgba(255,107,0,.15);
    --text-dim: rgba(255,255,255,.3);
}

* { box-sizing: border-box; }
body { background: var(--bg-deep); }

/* ── Page Header ── */
.eod-header {
    background: linear-gradient(135deg, #080814 0%, #140828 50%, #081414 100%);
    border: 1px solid rgba(255,107,0,.25);
    border-radius: 14px;
    padding: 18px 26px;
    margin-bottom: 16px;
    position: relative;
    overflow: hidden;
}
.eod-header::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent, var(--accent), var(--accent2), var(--accent), transparent);
}
.eod-header h4 {
    color: var(--accent2);
    font-size: 18px; font-weight: 900; margin: 0;
    letter-spacing: .3px;
}
.eod-header p {
    color: rgba(255,255,255,.4);
    margin: 5px 0 0; font-size: 11px; line-height: 1.6;
}
.v-badge {
    display: inline-block;
    background: rgba(0,230,118,.1); color: var(--bull);
    border: 1px solid rgba(0,230,118,.3);
    padding: 2px 10px; border-radius: 6px;
    font-size: 9px; font-weight: 800;
    margin-left: 10px;
}

/* ── Market Status Banner ── */
.market-status {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 18px; border-radius: 10px; margin-bottom: 14px;
    font-size: 11px; font-weight: 700;
    border: 1px solid;
}
.ms-open {
    background: rgba(255,193,7,.08);
    border-color: rgba(255,193,7,.3);
    color: var(--warn);
}
.ms-closed {
    background: rgba(0,230,118,.07);
    border-color: rgba(0,230,118,.3);
    color: var(--bull);
}
.ms-dot {
    width: 8px; height: 8px; border-radius: 50%;
    animation: pulse 1.2s ease-in-out infinite alternate;
}
@keyframes pulse {
    from { opacity: .4; transform: scale(.9); }
    to   { opacity: 1;  transform: scale(1.1); }
}

/* ── Filter Bar ── */
.filter-bar {
    background: var(--bg-card);
    border: 1px solid rgba(255,107,0,.15);
    padding: 10px 18px; border-radius: 10px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.filter-bar label {
    color: rgba(255,255,255,.35);
    font-size: 9px; font-weight: 800;
    text-transform: uppercase; letter-spacing: 1px; margin: 0;
}
.sym-select, .date-input {
    background: rgba(255,107,0,.07);
    border: 1px solid rgba(255,107,0,.28);
    color: var(--accent2); border-radius: 8px;
    padding: 6px 12px; font-size: 12px; font-weight: 700;
    cursor: pointer; outline: none;
}
.sym-select option, .date-input option { background: #0d0d1f; color: var(--accent2); }
.date-input::-webkit-calendar-picker-indicator {
    filter: invert(.7) sepia(1) saturate(5) hue-rotate(-15deg); cursor: pointer;
}
.nav-btn {
    background: rgba(255,107,0,.09);
    border: 1px solid rgba(255,107,0,.22); color: var(--accent2);
    border-radius: 6px; width: 27px; height: 27px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 15px; font-weight: 700; line-height: 1;
}
.nav-btn:hover { background: rgba(255,107,0,.2); }
.btn-load {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: #000; border: none; border-radius: 8px;
    padding: 7px 22px; font-weight: 900; font-size: 12px; cursor: pointer;
}
.btn-load:hover { opacity: .88; }
.auto-btn {
    background: rgba(255,255,255,.04);
    color: rgba(255,255,255,.45);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 8px; padding: 6px 14px;
    font-size: 11px; font-weight: 700; cursor: pointer;
}
.auto-btn.on {
    background: rgba(0,230,118,.08); color: var(--bull);
    border-color: rgba(0,230,118,.3);
}
.dv { width: 1px; height: 22px; background: rgba(255,255,255,.08); }
.lu { font-size: 10px; color: var(--text-dim); margin-left: auto; }

/* ── Signal Grid ── */
.signal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

/* ── Signal Card ── */
.sig-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px; overflow: hidden;
    transition: border-color .2s, transform .15s;
    position: relative;
}
.sig-card:hover {
    border-color: rgba(255,107,0,.35);
    transform: translateY(-1px);
}
.sig-card.bull-card { border-color: rgba(0,230,118,.25); }
.sig-card.bear-card { border-color: rgba(255,23,68,.25); }
.sig-card.avoid-card { border-color: rgba(255,255,255,.08); }
.sig-card.wait-card { border-color: rgba(255,193,7,.2); }

/* Card top accent bar */
.sig-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
}
.sig-card.bull-card::before  { background: linear-gradient(90deg, transparent, var(--bull), transparent); }
.sig-card.bear-card::before  { background: linear-gradient(90deg, transparent, var(--bear), transparent); }
.sig-card.avoid-card::before { background: linear-gradient(90deg, transparent, rgba(255,255,255,.2), transparent); }
.sig-card.wait-card::before  { background: linear-gradient(90deg, transparent, var(--warn), transparent); }

/* Card header */
.card-head {
    padding: 14px 16px 10px;
    display: flex; justify-content: space-between; align-items: flex-start;
    border-bottom: 1px solid rgba(255,255,255,.04);
}
.sym-name {
    font-size: 18px; font-weight: 900; color: var(--accent2);
    letter-spacing: .3px;
}
.sym-meta {
    font-size: 9px; color: var(--text-dim);
    font-weight: 600; margin-top: 2px;
}
.sym-atm {
    font-size: 10px; color: rgba(255,165,2,.7);
    font-weight: 700; margin-top: 1px;
}

/* Signal badge (main) */
.sig-badge {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 8px 16px; border-radius: 10px;
    font-size: 13px; font-weight: 900;
    white-space: nowrap; text-align: center;
    letter-spacing: .3px;
}
.sig-bull {
    background: rgba(0,230,118,.18); color: var(--bull);
    border: 1px solid rgba(0,230,118,.55);
    animation: bullglow 1.4s ease-in-out infinite alternate;
}
.sig-bear {
    background: rgba(255,23,68,.18); color: var(--bear);
    border: 1px solid rgba(255,23,68,.55);
    animation: bearglow 1.4s ease-in-out infinite alternate;
}
.sig-avoid {
    background: rgba(150,150,150,.08); color: rgba(255,255,255,.3);
    border: 1px solid rgba(255,255,255,.1);
}
.sig-wait {
    background: rgba(255,193,7,.1); color: var(--warn);
    border: 1px solid rgba(255,193,7,.3);
}
@keyframes bullglow {
    from { box-shadow: 0 0 6px rgba(0,230,118,.3); }
    to   { box-shadow: 0 0 20px rgba(0,230,118,.7); }
}
@keyframes bearglow {
    from { box-shadow: 0 0 6px rgba(255,23,68,.3); }
    to   { box-shadow: 0 0 20px rgba(255,23,68,.7); }
}

/* Card body */
.card-body { padding: 12px 16px; }

/* Confidence bar */
.conf-wrap { margin-bottom: 12px; }
.conf-top {
    display: flex; justify-content: space-between;
    font-size: 9px; color: var(--text-dim); font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px;
}
.conf-num {
    font-size: 22px; font-weight: 900;
    line-height: 1;
}
.conf-bar-outer {
    width: 100%; height: 8px;
    background: rgba(255,255,255,.06);
    border-radius: 4px; overflow: hidden;
}
.conf-bar-fill {
    height: 8px; border-radius: 4px;
    transition: width .5s cubic-bezier(.4,0,.2,1);
}

/* Strength badge */
.str-badge {
    display: inline-block; border-radius: 6px;
    padding: 2px 8px; font-size: 9px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .5px;
    margin-top: 4px;
}
.str-strong   { background: rgba(0,230,118,.15); color: var(--bull); border: 1px solid rgba(0,230,118,.3); }
.str-moderate { background: rgba(255,193,7,.12); color: var(--warn); border: 1px solid rgba(255,193,7,.3); }
.str-weak     { background: rgba(255,107,0,.1); color: var(--accent2); border: 1px solid rgba(255,107,0,.25); }
.str-avoid    { background: rgba(255,255,255,.04); color: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.08); }

/* Mini stat row */
.stat-row {
    display: flex; gap: 6px; flex-wrap: wrap;
    margin: 10px 0;
}
.stat-pill {
    display: flex; flex-direction: column; align-items: center;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 8px; padding: 5px 10px;
    min-width: 64px; flex: 1;
}
.stat-label {
    font-size: 8px; color: var(--text-dim); font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
}
.stat-val {
    font-size: 12px; font-weight: 900; margin-top: 2px;
    line-height: 1;
}
.up   { color: var(--bull); }
.down { color: var(--bear); }
.neu  { color: rgba(255,255,255,.35); }
.warn { color: var(--warn); }

/* Score bar */
.score-bars { margin: 10px 0; }
.score-row {
    display: flex; align-items: center; gap: 8px; margin-bottom: 5px;
}
.score-lbl { font-size: 9px; font-weight: 800; width: 28px; text-align: right; }
.score-track {
    flex: 1; height: 6px; background: rgba(255,255,255,.05);
    border-radius: 3px; overflow: hidden;
}
.score-fill {
    height: 6px; border-radius: 3px;
    transition: width .5s cubic-bezier(.4,0,.2,1);
}
.score-num { font-size: 9px; font-weight: 700; width: 32px; color: var(--text-dim); }

/* Reasons list */
.reasons-wrap {
    background: rgba(0,0,0,.25);
    border: 1px solid rgba(255,255,255,.05);
    border-radius: 8px; padding: 9px 11px;
    margin-top: 10px;
}
.reasons-title {
    font-size: 8px; font-weight: 800; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: .8px; margin-bottom: 6px;
}
.reason-item {
    display: flex; align-items: flex-start; gap: 6px;
    font-size: 10px; color: rgba(255,255,255,.5);
    line-height: 1.4; margin-bottom: 3px;
}
.reason-item::before {
    content: '›'; color: var(--accent); font-weight: 900; flex-shrink: 0;
}

/* Card footer */
.card-foot {
    padding: 9px 16px;
    background: rgba(0,0,0,.25);
    border-top: 1px solid rgba(255,255,255,.04);
    display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
}
.foot-item { font-size: 9px; color: var(--text-dim); }
.foot-item strong { color: rgba(255,255,255,.55); }

/* Day summary section */
.day-summary {
    display: flex; gap: 5px; flex-wrap: wrap; margin: 8px 0;
}
.ds-pill {
    display: flex; align-items: center; gap: 4px;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 6px; padding: 3px 8px;
    font-size: 9px;
}
.ds-pill .dl { color: var(--text-dim); font-weight: 700; }
.ds-pill .dv2 { color: rgba(255,255,255,.55); font-weight: 800; }

/* Regime badge */
.rg-trend    { color: var(--bull); background: rgba(0,230,118,.1); border: 1px solid rgba(0,230,118,.25); border-radius: 5px; padding: 1px 6px; font-size: 8px; font-weight: 800; }
.rg-break    { color: var(--accent2); background: rgba(255,107,0,.15); border: 1px solid rgba(255,107,0,.35); border-radius: 5px; padding: 1px 6px; font-size: 8px; font-weight: 900; }
.rg-side     { color: var(--warn); background: rgba(255,193,7,.1); border: 1px solid rgba(255,193,7,.25); border-radius: 5px; padding: 1px 6px; font-size: 8px; font-weight: 700; }
.rg-unk      { color: var(--text-dim); font-size: 8px; }

/* Valid for badge */
.valid-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(79,195,247,.07);
    border: 1px solid rgba(79,195,247,.25);
    border-radius: 8px; padding: 6px 12px;
    font-size: 10px; color: var(--blue); font-weight: 700;
    margin-top: 8px; width: 100%;
}

/* Candle breakdown accordion */
.breakdown-btn {
    display: flex; align-items: center; justify-content: space-between;
    background: rgba(255,255,255,.02);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 8px; padding: 7px 12px;
    color: var(--text-dim); font-size: 10px; font-weight: 700;
    cursor: pointer; width: 100%; text-align: left; margin-top: 8px;
}
.breakdown-btn:hover { background: rgba(255,255,255,.04); }
.breakdown-table { display: none; margin-top: 6px; overflow-x: auto; }
.breakdown-table.open { display: block; }
.bt { width: 100%; border-collapse: collapse; font-size: 10px; }
.bt th {
    padding: 4px 6px; text-align: center; font-size: 8px; font-weight: 800;
    color: var(--text-dim); text-transform: uppercase; letter-spacing: .5px;
    border-bottom: 1px solid rgba(255,255,255,.07); white-space: nowrap;
}
.bt td {
    padding: 3px 5px; text-align: center; border-bottom: 1px solid rgba(255,255,255,.03);
    white-space: nowrap;
}
.bt tbody tr:hover td { background: rgba(255,107,0,.03); }

/* Loading / empty */
.loading-wrap {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 80px; color: var(--accent2); font-size: 13px; font-weight: 600;
}
.spinner {
    width: 38px; height: 38px;
    border: 4px solid rgba(255,107,0,.1); border-top-color: var(--accent2);
    border-radius: 50%; animation: spin 1s linear infinite;
    margin-bottom: 14px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.no-data {
    text-align: center; padding: 80px 20px;
    color: rgba(255,255,255,.2); font-size: 13px;
}

/* Summary bar */
.summary-bar {
    display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
    padding: 10px 16px;
    background: var(--bg-card);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 10px; margin-bottom: 14px;
    font-size: 10px;
}
.sb-item { display: flex; align-items: center; gap: 6px; color: var(--text-dim); }
.sb-dot  { width: 8px; height: 8px; border-radius: 50%; }

/* OI bias pill */
.oi-bull-pill { display:inline-block; background:rgba(0,230,118,.1); color:var(--bull); border:1px solid rgba(0,230,118,.3); border-radius:5px; padding:1px 7px; font-size:8px; font-weight:800; }
.oi-bear-pill { display:inline-block; background:rgba(255,23,68,.1); color:var(--bear); border:1px solid rgba(255,23,68,.3); border-radius:5px; padding:1px 7px; font-size:8px; font-weight:800; }
.oi-mix-pill  { display:inline-block; background:rgba(255,193,7,.1); color:var(--warn); border:1px solid rgba(255,193,7,.25); border-radius:5px; padding:1px 7px; font-size:8px; font-weight:700; }
.oi-neu-pill  { display:inline-block; background:rgba(255,255,255,.04); color:var(--text-dim); border:1px solid rgba(255,255,255,.1); border-radius:5px; padding:1px 7px; font-size:8px; font-weight:600; }

@media (max-width: 640px) {
    .signal-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Page Header --}}
    <div class="eod-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>
                    📊 Daily EOD Signal — Next Day Predictor
                    <span class="v-badge">v1 EOD</span>
                </h4>
                <p>
                    Analyses <strong>full day 15-min candles</strong> (prev 14:45 → today 14:45)
                    &nbsp;·&nbsp; Adaptive vol scoring &nbsp;·&nbsp; Zone-weighted (REVERSAL = 1.5×)
                    &nbsp;·&nbsp; Price trend + OI trajectory &nbsp;·&nbsp;
                    <span style="color:var(--bull);">● Signal valid after 15:00</span>
                    &nbsp;·&nbsp; <span style="color:var(--warn);">Entry: 9:15 next trading day</span>
                </p>
            </div>
            <a href="{{ route('smart-volume-spike-15.index') }}" class="btn btn-sm" style="background:rgba(255,107,0,.1);color:#ff9f00;border:1px solid rgba(255,107,0,.3);font-size:11px;font-weight:700;">
                ⚡ Live 15Min
            </a>
        </div>
    </div>

    {{-- Market Status --}}
    <div id="market-status-bar"></div>

    {{-- Filter Bar --}}
    <div class="filter-bar">
        <label>DATE</label>
        <div class="d-flex align-items-center gap-1">
            <button class="nav-btn" onclick="shiftDate(-1)">‹</button>
            <input type="date" id="dp" class="date-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="loadData()">
            <button class="nav-btn" onclick="shiftDate(1)">›</button>
            <button class="nav-btn" style="width:auto;padding:0 9px;font-size:10px;" onclick="goToday()">Today</button>
        </div>
        <div class="dv"></div>
        <label>SYMBOL</label>
        <select id="ss" class="sym-select" onchange="loadData()">
            <option value="ALL">— All Symbols —</option>
        </select>
        <button class="btn-load" onclick="loadData()">↻ Load</button>
        <button class="auto-btn" id="abtn" onclick="toggleAuto()">▶ Auto 60s</button>
        <span id="atag" style="font-size:10px;color:var(--bull);"></span>
        <span class="lu" id="lu"></span>
    </div>

    {{-- Summary bar --}}
    <div id="summary-bar"></div>

    {{-- Signal Grid --}}
    <div id="signal-grid">
        <div class="loading-wrap">
            <div class="spinner"></div>
            Loading EOD signals…
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
/* ═══════════════════════════════════════════════════════
   SMART VOLUME DAILY EOD — JS
═══════════════════════════════════════════════════════ */
const todayStr = '{{ now()->toDateString() }}';
let autoTimer  = null;
let cachedSyms = [];

$(document).ready(() => loadData());

const dp  = () => document.getElementById('dp').value;
const sym = () => document.getElementById('ss').value;

function shiftDate(d) {
    const el = document.getElementById('dp');
    const dt = new Date(el.value);
    dt.setDate(dt.getDate() + d);
    const s = dt.toISOString().split('T')[0];
    if (s > todayStr) return;
    el.value = s; loadData();
}
function goToday() { document.getElementById('dp').value = todayStr; loadData(); }

function toggleAuto() {
    const btn = document.getElementById('abtn');
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        btn.textContent = '▶ Auto 60s'; btn.classList.remove('on');
        document.getElementById('atag').textContent = '';
    } else {
        autoTimer = setInterval(loadData, 60000);
        btn.textContent = '■ Stop'; btn.classList.add('on');
        document.getElementById('atag').textContent = '● live';
        loadData();
    }
}

function rebuildSyms(syms) {
    if (JSON.stringify(cachedSyms) === JSON.stringify(syms)) return;
    cachedSyms = syms;
    const sel = document.getElementById('ss'), prev = sel.value;
    sel.innerHTML = '<option value="ALL">— All Symbols —</option>';
    syms.forEach(s => {
        const o = document.createElement('option');
        o.value = s; o.textContent = s;
        if (s === prev) o.selected = true;
        sel.appendChild(o);
    });
}

function loadData() {
    const date = dp(), s = sym();
    document.getElementById('signal-grid').innerHTML =
        '<div class="loading-wrap"><div class="spinner"></div>Analysing ' + date + '…</div>';
    document.getElementById('summary-bar').innerHTML = '';

    $.ajax({
        url: '{{ route("smart-volume-daily-eod.signals") }}',
        data: { symbol: s, date },
        success(res) {
            updateMarketStatus(res);
            if (res.available_symbols && res.available_symbols.length) rebuildSyms(res.available_symbols);

            if (!res.success || !res.data || !res.data.length) {
                document.getElementById('signal-grid').innerHTML =
                    '<div class="no-data"><div style="font-size:2.5rem;opacity:.2;">📊</div><p style="margin-top:14px;">' +
                    (res.message || 'No data for ' + date) + '</p></div>';
                return;
            }
            renderSummaryBar(res);
            renderCards(res.data, res.market_closed, res.next_trading_day);
            document.getElementById('lu').textContent = 'Updated: ' + new Date().toLocaleTimeString();
        },
        error(xhr) {
            document.getElementById('signal-grid').innerHTML =
                '<div class="no-data">⚠ ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error') + '</div>';
        }
    });
}

// ── Market status banner ──────────────────────────────────────────────────

function updateMarketStatus(res) {
    const el = document.getElementById('market-status-bar');
    if (!res.is_today) {
        el.innerHTML = '<div class="market-status ms-closed" style="margin-bottom:14px;">📅 Historical data — ' + res.today + '</div>';
        return;
    }
    if (res.market_closed) {
        el.innerHTML = '<div class="market-status ms-closed" style="margin-bottom:14px;">' +
            '<span class="ms-dot" style="background:var(--bull);"></span>' +
            '✅ Market closed — EOD signals are final &nbsp;·&nbsp; Next trading day: <strong>' + (res.next_trading_day || '—') + '</strong>' +
            '</div>';
    } else {
        el.innerHTML = '<div class="market-status ms-open" style="margin-bottom:14px;">' +
            '<span class="ms-dot" style="background:var(--warn);"></span>' +
            '⏳ Market still open (' + res.current_time + ') — EOD signal will be ready after <strong>15:00</strong>' +
            '</div>';
    }
}

// ── Summary bar ───────────────────────────────────────────────────────────

function renderSummaryBar(res) {
    const data = res.data;
    let bull = 0, bear = 0, avoid = 0, wait = 0;
    data.forEach(d => {
        const a = d.eod_signal && d.eod_signal.action;
        if (a === 'BUY_CE') bull++;
        else if (a === 'BUY_PE') bear++;
        else if (a === 'WAIT') wait++;
        else avoid++;
    });

    document.getElementById('summary-bar').innerHTML =
        '<div class="summary-bar">' +
        '<span style="font-size:9px;font-weight:800;color:rgba(255,255,255,.3);letter-spacing:.8px;text-transform:uppercase;">EOD Summary:</span>' +
        (bull  ? '<span class="sb-item"><span class="sb-dot" style="background:var(--bull);"></span><strong style="color:var(--bull);">' + bull + '</strong>&nbsp;BUY CE</span>' : '') +
        (bear  ? '<span class="sb-item"><span class="sb-dot" style="background:var(--bear);"></span><strong style="color:var(--bear);">' + bear + '</strong>&nbsp;BUY PE</span>' : '') +
        (avoid ? '<span class="sb-item"><span class="sb-dot" style="background:rgba(255,255,255,.25);"></span><strong style="color:rgba(255,255,255,.3);">' + avoid + '</strong>&nbsp;AVOID</span>' : '') +
        (wait  ? '<span class="sb-item"><span class="sb-dot" style="background:var(--warn);"></span><strong style="color:var(--warn);">' + wait + '</strong>&nbsp;WAIT</span>' : '') +
        '<span style="margin-left:auto;font-size:9px;color:rgba(255,255,255,.2);">' + data.length + ' symbols · ' + (res.next_trading_day ? 'Next: ' + res.next_trading_day : '') + '</span>' +
        '</div>';
}

// ── Card renderer ─────────────────────────────────────────────────────────

function renderCards(data, marketClosed, nextDay) {
    const grid = document.getElementById('signal-grid');
    grid.className = 'signal-grid';

    if (!data.length) {
        grid.innerHTML = '<div class="no-data">No signals computed.</div>';
        return;
    }

    grid.innerHTML = data.map((d, i) => buildCard(d, i, nextDay)).join('');
}

function buildCard(d, i, nextDay) {
    const sig     = d.eod_signal || {};
    const action  = sig.action || 'AVOID';
    const conf    = sig.confidence || 0;
    const vs      = d.vol_scoring || {};
    const oi      = d.oi_summary  || {};
    const ds      = d.day_summary || {};
    const reasons = sig.reasons || [];

    const cardClass = action === 'BUY_CE' ? 'bull-card'
                    : action === 'BUY_PE' ? 'bear-card'
                    : action === 'WAIT'   ? 'wait-card'
                    : 'avoid-card';

    const badgeClass = action === 'BUY_CE' ? 'sig-bull'
                     : action === 'BUY_PE' ? 'sig-bear'
                     : action === 'WAIT'   ? 'sig-wait'
                     : 'sig-avoid';

    const confColor = conf >= 75 ? '#00e676' : conf >= 50 ? '#ffc107' : conf >= 25 ? '#ff9f00' : 'rgba(255,255,255,.2)';
    const confFill  = conf > 0 ? conf : 0;

    const changePct = ds.change_pct;
    const changeStr = changePct != null
        ? (changePct >= 0 ? '<span class="up">▲ ' + changePct.toFixed(2) + '%</span>' : '<span class="down">▼ ' + Math.abs(changePct).toFixed(2) + '%</span>')
        : '<span class="neu">—</span>';

    const ceScore = vs.total_ce_score || 0;
    const peScore = vs.total_pe_score || 0;
    const maxScore = Math.max(ceScore, peScore, 1);
    const cePct = Math.min(100, (ceScore / maxScore) * 100).toFixed(0);
    const pePct = Math.min(100, (peScore / maxScore) * 100).toFixed(0);

    const revCePct = Math.min(100, ((vs.reversal_ce_score || 0) / maxScore) * 100).toFixed(0);
    const revPePct = Math.min(100, ((vs.reversal_pe_score || 0) / maxScore) * 100).toFixed(0);

    const regBadge = regimeBadge(ds.dominant_regime);
    const oiBiasHtml = oiBadge(sig.oi_bias);
    const priceBiasHtml = priceBadge(sig.price_bias, ds.price_trend);

    const reasonsHtml = reasons.map(r => '<div class="reason-item">' + esc(r) + '</div>').join('');

    // Candle breakdown table
    const candles = d.candle_scores || [];
    const candleRows = candles.map(c => {
        const pdc = c.price_dir === 'UP' ? 'up' : c.price_dir === 'DOWN' ? 'down' : 'neu';
        const ceHi = c.ce_score > 0 ? '<span style="color:#ff6b6b;font-weight:800;">' + c.ce_score.toFixed(1) + '</span>' : '<span style="color:rgba(255,255,255,.15);">0</span>';
        const peHi = c.pe_score > 0 ? '<span style="color:#51cf66;font-weight:800;">' + c.pe_score.toFixed(1) + '</span>' : '<span style="color:rgba(255,255,255,.15);">0</span>';
        const zw = c.zone_weight >= 1.5 ? '<span style="color:#ff9f00;">×1.5</span>' : c.zone_weight <= 0.5 ? '<span style="color:rgba(255,255,255,.25);">×0.5</span>' : '×1';
        const tz = c.time_zone === 'NOISE' ? '<span style="color:#ffc107;">N</span>'
                 : c.time_zone === 'REVERSAL' ? '<span style="color:#ff9f00;">R</span>'
                 : '<span style="color:#00e676;">P</span>';
        const ceOiColor = c.ce_oi_pct > 0 ? 'color:var(--bear)' : c.ce_oi_pct < 0 ? 'color:var(--bull)' : 'color:rgba(255,255,255,.2)';
        const peOiColor = c.pe_oi_pct > 0 ? 'color:var(--bull)' : c.pe_oi_pct < 0 ? 'color:var(--bear)' : 'color:rgba(255,255,255,.2)';

        return '<tr>' +
            '<td style="color:rgba(255,255,255,.5);font-weight:700;">' + c.time + '</td>' +
            '<td>' + tz + '</td>' +
            '<td>' + zw + '</td>' +
            '<td class="' + pdc + '">' + (c.price_dir === 'UP' ? '▲' : c.price_dir === 'DOWN' ? '▼' : '—') + '</td>' +
            '<td>' + ceHi + '</td>' +
            '<td>' + peHi + '</td>' +
            '<td style="' + ceOiColor + ';">' + (c.ce_oi_pct != null ? (c.ce_oi_pct > 0 ? '+' : '') + c.ce_oi_pct.toFixed(1) + '%' : '—') + '</td>' +
            '<td style="' + peOiColor + ';">' + (c.pe_oi_pct != null ? (c.pe_oi_pct > 0 ? '+' : '') + c.pe_oi_pct.toFixed(1) + '%' : '—') + '</td>' +
            '<td style="color:rgba(255,255,255,.3);">' + (c.future_price ? ni(c.future_price) : '—') + '</td>' +
            '</tr>';
    }).join('');

    return `
    <div class="sig-card ${cardClass}">

        <div class="card-head">
            <div>
                <div class="sym-name">${d.symbol}</div>
                <div class="sym-meta">Expiry: ${d.expiry} &nbsp;·&nbsp; ${d.total_candles} candles</div>
                <div class="sym-atm">ATM ₹${ni(d.atm_strike)}</div>
            </div>
            <div style="text-align:right;">
                <div class="sig-badge ${badgeClass}">${sig.label || '—'}</div>
                ${sig.valid_for ? '<div style="font-size:8px;color:rgba(79,195,247,.7);margin-top:5px;font-weight:700;">Entry: ' + (sig.entry_time || sig.valid_for) + '</div>' : ''}
            </div>
        </div>

        <div class="card-body">

            <!-- Confidence -->
            <div class="conf-wrap">
                <div class="conf-top">
                    <span>Confidence</span>
                    <span>${strengthBadge(sig.strength)}</span>
                </div>
                <div style="display:flex;align-items:baseline;gap:6px;margin-bottom:4px;">
                    <span class="conf-num" style="color:${confColor};">${conf}%</span>
                    <span style="font-size:9px;color:rgba(255,255,255,.2);">signal strength</span>
                </div>
                <div class="conf-bar-outer">
                    <div class="conf-bar-fill" style="width:${confFill}%;background:${confColor};"></div>
                </div>
            </div>

            <!-- Day Summary pills -->
            <div class="day-summary">
                <div class="ds-pill"><span class="dl">Day</span><span class="dv2">${changeStr}</span></div>
                <div class="ds-pill"><span class="dl">Close</span><span class="dv2 neu">₹${ni(ds.close)}</span></div>
                <div class="ds-pill"><span class="dl">↑</span><span class="dv2 up">${ds.up_candles || 0}</span></div>
                <div class="ds-pill"><span class="dl">↓</span><span class="dv2 down">${ds.down_candles || 0}</span></div>
                <div class="ds-pill"><span class="dl">Regime</span><span class="dv2">${regBadge}</span></div>
            </div>

            <!-- Score Bars -->
            <div class="score-bars">
                <div style="font-size:8px;font-weight:800;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px;">Vol Score (zone-weighted)</div>
                <div class="score-row">
                    <span class="score-lbl" style="color:#ff6b6b;">CE</span>
                    <div class="score-track">
                        <div class="score-fill" style="width:${cePct}%;background:rgba(255,107,80,.7);"></div>
                    </div>
                    <span class="score-num">${ceScore.toFixed(1)}</span>
                </div>
                <div class="score-row">
                    <span class="score-lbl" style="color:#51cf66;">PE</span>
                    <div class="score-track">
                        <div class="score-fill" style="width:${pePct}%;background:rgba(0,200,100,.7);"></div>
                    </div>
                    <span class="score-num">${peScore.toFixed(1)}</span>
                </div>
                <div style="font-size:8px;font-weight:800;color:rgba(255,255,255,.18);letter-spacing:.5px;margin:6px 0 4px;">Reversal Zone (×1.5 weight)</div>
                <div class="score-row">
                    <span class="score-lbl" style="color:#ff6b6b;">CE</span>
                    <div class="score-track">
                        <div class="score-fill" style="width:${revCePct}%;background:rgba(255,107,80,.5);"></div>
                    </div>
                    <span class="score-num">${(vs.reversal_ce_score || 0).toFixed(1)}</span>
                </div>
                <div class="score-row">
                    <span class="score-lbl" style="color:#51cf66;">PE</span>
                    <div class="score-track">
                        <div class="score-fill" style="width:${revPePct}%;background:rgba(0,200,100,.5);"></div>
                    </div>
                    <span class="score-num">${(vs.reversal_pe_score || 0).toFixed(1)}</span>
                </div>
            </div>

            <!-- Mini stats -->
            <div class="stat-row">
                <div class="stat-pill">
                    <span class="stat-label">OI Bias</span>
                    <span class="stat-val">${oiBiasHtml}</span>
                </div>
                <div class="stat-pill">
                    <span class="stat-label">Price</span>
                    <span class="stat-val">${priceBiasHtml}</span>
                </div>
                <div class="stat-pill">
                    <span class="stat-label">CE OI%</span>
                    <span class="stat-val" style="font-size:11px;color:rgba(255,255,255,.45);">${oi.ce_build_pct || 0}% <span style="font-size:8px;color:rgba(255,255,255,.25);">candles</span></span>
                </div>
                <div class="stat-pill">
                    <span class="stat-label">PE OI%</span>
                    <span class="stat-val" style="font-size:11px;color:rgba(255,255,255,.45);">${oi.pe_build_pct || 0}% <span style="font-size:8px;color:rgba(255,255,255,.25);">candles</span></span>
                </div>
                <div class="stat-pill">
                    <span class="stat-label">🔥 CE Ext.</span>
                    <span class="stat-val" style="color:#ff6b6b;">${vs.extreme_ce_count || 0}</span>
                </div>
                <div class="stat-pill">
                    <span class="stat-label">🔥 PE Ext.</span>
                    <span class="stat-val" style="color:#51cf66;">${vs.extreme_pe_count || 0}</span>
                </div>
            </div>

            <!-- Valid for -->
            <div class="valid-badge">
                📅 Valid for: ${sig.valid_for || 'Next session'} ${nextDay ? '(' + nextDay + ')' : ''}
            </div>

            <!-- Reasons -->
            ${reasons.length ? `<div class="reasons-wrap"><div class="reasons-title">Signal Reasons</div>${reasonsHtml}</div>` : ''}

            <!-- Candle breakdown -->
            ${candles.length ? `
            <button class="breakdown-btn" onclick="toggleBreakdown('bd${i}', this)">
                📋 Candle-by-candle breakdown (${candles.length}) <span>▼</span>
            </button>
            <div id="bd${i}" class="breakdown-table">
                <table class="bt">
                    <thead>
                        <tr>
                            <th>Time</th><th>Zone</th><th>Wt</th><th>Dir</th>
                            <th style="color:#ff6b6b;">CE Score</th>
                            <th style="color:#51cf66;">PE Score</th>
                            <th style="color:#ff6b6b;">CE OI%</th>
                            <th style="color:#51cf66;">PE OI%</th>
                            <th>FUT Price</th>
                        </tr>
                    </thead>
                    <tbody>${candleRows}</tbody>
                </table>
            </div>` : ''}

        </div>

        <div class="card-foot">
            <span class="foot-item">Scores: <strong>CE ${(sig.scores && sig.scores.buy_ce) || 0} vs PE ${(sig.scores && sig.scores.buy_pe) || 0}</strong></span>
            <span class="foot-item">Vol bias: <strong>${sig.vol_bias || '—'}</strong></span>
            ${sig.conflicted ? '<span style="font-size:8px;background:rgba(255,193,7,.1);color:var(--warn);border:1px solid rgba(255,193,7,.25);border-radius:5px;padding:2px 7px;font-weight:800;">⚠ CONFLICTED</span>' : ''}
        </div>

    </div>`;
}

// ── Helpers ───────────────────────────────────────────────────────────────

function toggleBreakdown(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    const open = el.classList.toggle('open');
    btn.querySelector('span').textContent = open ? '▲' : '▼';
}

function strengthBadge(str) {
    if (!str) return '';
    const map = {
        STRONG:   ['str-badge str-strong',   '★★★ STRONG'],
        MODERATE: ['str-badge str-moderate', '★★ MODERATE'],
        WEAK:     ['str-badge str-weak',     '★ WEAK'],
        AVOID:    ['str-badge str-avoid',    'AVOID'],
    };
    const [cls, lbl] = map[str] || map.AVOID;
    return '<span class="' + cls + '">' + lbl + '</span>';
}

function regimeBadge(r) {
    if (!r) return '<span class="rg-unk">—</span>';
    if (r === 'TRENDING')  return '<span class="rg-trend">TREND</span>';
    if (r === 'BREAKOUT')  return '<span class="rg-break">BREAK</span>';
    if (r === 'SIDEWAYS')  return '<span class="rg-side">SIDE</span>';
    return '<span class="rg-unk">—</span>';
}

function oiBadge(bias) {
    if (!bias || bias === 'NEUTRAL') return '<span class="oi-neu-pill">NEU</span>';
    if (bias === 'BULLISH') return '<span class="oi-bull-pill">↑ BULL</span>';
    if (bias === 'BEARISH') return '<span class="oi-bear-pill">↓ BEAR</span>';
    if (bias === 'MIXED')   return '<span class="oi-mix-pill">⚠ MIXED</span>';
    return '<span class="oi-neu-pill">—</span>';
}

function priceBadge(bias, trend) {
    const str   = trend && trend.strength ? trend.strength : '';
    const chPct = trend && trend.change_pct != null ? ' (' + (trend.change_pct > 0 ? '+' : '') + trend.change_pct.toFixed(2) + '%)' : '';
    if (bias === 'UP')   return '<span class="up" style="font-size:11px;font-weight:900;">▲ UP</span><span style="font-size:8px;color:rgba(255,255,255,.3);">' + chPct + '</span>';
    if (bias === 'DOWN') return '<span class="down" style="font-size:11px;font-weight:900;">▼ DN</span><span style="font-size:8px;color:rgba(255,255,255,.3);">' + chPct + '</span>';
    return '<span class="neu" style="font-size:11px;">— FLAT</span>';
}

function ni(v) {
    if (v == null || v === '' || v === undefined) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}
function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
@endpush