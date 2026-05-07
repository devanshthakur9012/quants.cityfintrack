@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ══════════════════════════════════════════
   SIGNAL INTELLIGENCE 5MIN — DARK DASHBOARD
   ══════════════════════════════════════════ */
:root {
    --bull:   #51cf66;
    --bear:   #ff6b6b;
    --gold:   #f7b733;
    --cyan:   #4fc3f7;
    --purple: #ce93d8;
    --orange: #ff9800;
    --muted:  rgba(255,255,255,0.22);
    --border: rgba(255,255,255,0.07);
    --card:   rgba(255,255,255,0.03);
}

/* ── Page Header ── */
.si-header {
    background: linear-gradient(135deg, #0d1f0d 0%, #1a3a1a 50%, #0f2a1a 100%);
    border: 1px solid rgba(81,207,102,0.2);
    border-radius: 14px; padding: 18px 24px; margin-bottom: 18px;
    box-shadow: 0 4px 24px rgba(81,207,102,0.12);
    position: relative; overflow: hidden;
}
.si-header::before {
    content: ''; position: absolute; top: -40px; right: -40px;
    width: 160px; height: 160px; border-radius: 50%;
    background: radial-gradient(circle, rgba(81,207,102,0.08), transparent 70%);
}
.si-header h4 { color: #fff; margin: 0; font-size: 18px; font-weight: 800; }
.si-header p  { color: rgba(255,255,255,0.55); margin: 5px 0 0; font-size: 11px; line-height: 1.6; }
.si-tag {
    background: rgba(81,207,102,0.18); color: var(--bull);
    border: 1px solid rgba(81,207,102,0.35); border-radius: 5px;
    padding: 2px 9px; font-size: 10px; font-weight: 800; margin-left: 8px;
}

/* ── Tab Bar ── */
.tab-bar {
    display: flex; gap: 6px; margin-bottom: 18px;
    background: rgba(0,0,0,0.3); border-radius: 10px;
    padding: 6px; border: 1px solid var(--border);
}
.tab-btn {
    flex: 1; background: transparent; border: none; color: var(--muted);
    padding: 9px 14px; border-radius: 7px; font-size: 12px; font-weight: 700;
    cursor: pointer; transition: all .2s;
}
.tab-btn.active, .tab-btn:hover {
    background: rgba(81,207,102,0.15); color: var(--bull);
    border: 1px solid rgba(81,207,102,0.3);
}
.tab-btn.active { box-shadow: 0 2px 12px rgba(81,207,102,0.2); }

/* ── Filter Bar ── */
.filter-bar {
    background: linear-gradient(135deg, rgba(81,207,102,0.08), rgba(247,183,51,0.06));
    border: 1px solid rgba(81,207,102,0.18); border-radius: 11px;
    padding: 11px 18px; margin-bottom: 18px;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.filter-label { color: var(--muted); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
.f-select, .f-date {
    background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.15);
    color: #fff; border-radius: 7px; padding: 6px 12px;
    font-size: 12px; font-weight: 700; outline: none; cursor: pointer;
}
.f-select option { background: #0a0f0a; }
.f-date::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
.btn-load {
    background: var(--bull); color: #000; border: none;
    border-radius: 7px; padding: 7px 20px; font-weight: 800;
    font-size: 12px; cursor: pointer; transition: .15s;
}
.btn-load:hover { background: #6ee07a; }
.auto-btn {
    background: rgba(255,255,255,0.06); color: var(--muted);
    border: 1px solid var(--border); border-radius: 7px;
    padding: 6px 14px; font-size: 11px; font-weight: 700; cursor: pointer; transition: .15s;
}
.auto-btn.on { background: rgba(81,207,102,0.12); color: var(--bull); border-color: rgba(81,207,102,0.3); }
.date-nav { background: rgba(255,255,255,0.06); border: 1px solid var(--border);
    color: #fff; border-radius: 6px; width: 28px; height: 28px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; }
.last-upd { margin-left: auto; font-size: 10px; color: rgba(255,255,255,0.3); }
.divider-v { width: 1px; height: 22px; background: rgba(255,255,255,0.1); }

/* ── Stats Row ── */
.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 18px; }
.stat-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 10px; padding: 14px 16px;
}
.stat-card .s-label { font-size: 10px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
.stat-card .s-val   { font-size: 22px; font-weight: 900; margin-top: 4px; }
.stat-card .s-sub   { font-size: 10px; color: var(--muted); margin-top: 2px; }

/* ── Main table card ── */
.main-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 12px; overflow: hidden;
}
.tbl-wrap { overflow-x: auto; }
.sig-tbl  { width: 100%; border-collapse: collapse; min-width: 1800px; }

/* ── Table headers ── */
.sig-tbl thead tr.grp th {
    padding: 10px 10px 5px; text-align: center;
    font-size: 10px; font-weight: 800; text-transform: uppercase;
    letter-spacing: .4px; background: rgba(0,0,0,0.5); white-space: nowrap;
}
.sig-tbl thead tr.cols th {
    padding: 5px 10px 9px; text-align: center; font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .3px; background: rgba(0,0,0,0.35);
    color: rgba(255,255,255,0.45); border-bottom: 2px solid var(--border); white-space: nowrap;
}
.hdr-meta    { color: rgba(255,255,255,0.4)  !important; }
.hdr-state   { color: var(--bull)            !important; }
.hdr-oi      { color: var(--gold)            !important; }
.hdr-vol     { color: var(--orange)          !important; }
.hdr-signal  { color: #74c0fc               !important; }
.hdr-time    { color: var(--cyan)            !important; }
.hdr-ce      { color: var(--bull)            !important; }
.hdr-pe      { color: var(--bear)            !important; }

/* ── Separators ── */
.sep-state  { border-left: 2px solid rgba(81,207,102,0.3) !important; }
.sep-oi     { border-left: 2px solid rgba(247,183,51,0.3) !important; }
.sep-vol    { border-left: 2px solid rgba(255,152,0,0.3) !important; }
.sep-signal { border-left: 2px solid rgba(116,192,252,0.3) !important; }
.sep-time   { border-left: 2px solid rgba(79,195,247,0.3) !important; }
.sep-ce     { border-left: 2px solid rgba(81,207,102,0.25) !important; }
.sep-pe     { border-left: 2px solid rgba(255,107,107,0.25) !important; }

/* ── Body cells ── */
.sig-tbl tbody td {
    padding: 9px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    vertical-align: middle; white-space: nowrap;
}
.sig-tbl tbody tr:hover { background: rgba(255,255,255,0.04) !important; }
.even { background: rgba(0,0,0,0.08); }
.odd  { background: rgba(255,255,255,0.01); }

/* ── Cell typography ── */
.c-num  { font-size: 10px; color: rgba(255,255,255,.25); font-weight: 700; }
.c-time { font-size: 12px; font-weight: 900; color: var(--gold); }
.c-sym  { }
.badge-sym {
    display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 800;
    background: rgba(247,183,51,0.12); color: var(--gold); border: 1px solid rgba(247,183,51,0.25);
}
.badge-atm {
    font-size: 9px; font-weight: 700; color: var(--gold); opacity: .7;
    display: block; margin-top: 2px;
}
.c-price { font-size: 11px; font-weight: 700; color: var(--cyan); }
.c-o  { color: rgba(255,255,255,.45); }
.c-h  { color: #ff9f7f; font-weight: 700; }
.c-l  { color: #7fff9f; font-weight: 700; }
.c-c  { color: var(--cyan); font-weight: 800; }

/* ── Market State badges ── */
.state-strong-bull {
    display:inline-block; background:rgba(81,207,102,.22); color:var(--bull);
    border:1px solid rgba(81,207,102,.5); border-radius:7px;
    padding:3px 10px; font-size:10px; font-weight:800; white-space:nowrap;
}
.state-strong-bear {
    display:inline-block; background:rgba(255,107,107,.22); color:var(--bear);
    border:1px solid rgba(255,107,107,.5); border-radius:7px;
    padding:3px 10px; font-size:10px; font-weight:800; white-space:nowrap;
}
.state-sideways {
    display:inline-block; background:rgba(134,142,150,.18); color:#adb5bd;
    border:1px solid rgba(134,142,150,.4); border-radius:7px;
    padding:3px 10px; font-size:10px; font-weight:700; white-space:nowrap;
}
.state-reversal {
    display:inline-block; background:rgba(255,165,2,.2); color:#ffa502;
    border:1px solid rgba(255,165,2,.5); border-radius:7px;
    padding:3px 10px; font-size:10px; font-weight:800; white-space:nowrap;
    animation: blink 1.2s ease-in-out infinite;
}
.state-na { color: var(--muted); font-size:9px; }

/* ── OI Price badges ── */
.oi-long-build   { color:var(--bull); font-size:10px; font-weight:700; }
.oi-short-build  { color:var(--bear); font-size:10px; font-weight:700; }
.oi-short-cover  { color:#ffd43b; font-size:10px; font-weight:700; }
.oi-long-unwind  { color:var(--orange); font-size:10px; font-weight:700; }
.oi-na           { color:var(--muted); font-size:9px; }

/* ── Vol Spike badges ── */
.vol-strong { background:rgba(255,50,50,.25); color:#ff4444; border:1px solid rgba(255,50,50,.5); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:800; white-space:nowrap; animation:blink 1s step-end infinite; }
.vol-spike  { background:rgba(255,152,0,.22); color:var(--orange); border:1px solid rgba(255,152,0,.5); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:800; white-space:nowrap; }
.vol-elev   { background:rgba(255,193,7,.15); color:#ffc107; border:1px solid rgba(255,193,7,.35); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:700; white-space:nowrap; }
.vol-norm   { background:rgba(255,255,255,.05); color:rgba(255,255,255,.3); border:1px solid rgba(255,255,255,.1); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:600; white-space:nowrap; }
.vol-open   { background:rgba(79,195,247,.12); color:var(--cyan); border:1px solid rgba(79,195,247,.3); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:700; white-space:nowrap; }

/* ── Entry Signal badges ── */
.sig-buy-ce {
    display:inline-block; background:rgba(81,207,102,.25); color:var(--bull);
    border:1px solid rgba(81,207,102,.6); border-radius:8px;
    padding:4px 14px; font-size:11px; font-weight:900; white-space:nowrap;
    box-shadow: 0 0 12px rgba(81,207,102,0.3);
}
.sig-buy-pe {
    display:inline-block; background:rgba(255,107,107,.25); color:var(--bear);
    border:1px solid rgba(255,107,107,.6); border-radius:8px;
    padding:4px 14px; font-size:11px; font-weight:900; white-space:nowrap;
    box-shadow: 0 0 12px rgba(255,107,107,0.3);
}
.sig-no-trade {
    display:inline-block; background:rgba(255,255,255,.04); color:rgba(255,255,255,.25);
    border:1px solid rgba(255,255,255,.09); border-radius:8px;
    padding:4px 14px; font-size:11px; font-weight:600; white-space:nowrap;
}
.sig-blocked {
    display:inline-block; background:rgba(134,142,150,.12); color:rgba(255,255,255,.2);
    border:1px solid rgba(134,142,150,.2); border-radius:8px;
    padding:4px 14px; font-size:10px; font-weight:600; white-space:nowrap;
}

/* ── Confidence badges ── */
.conf-very-high { color:var(--bull); font-size:10px; font-weight:800; }
.conf-high      { color:#82e09a;    font-size:10px; font-weight:700; }
.conf-medium    { color:var(--gold);font-size:10px; font-weight:700; }
.conf-low       { color:var(--muted);font-size:9px; font-weight:600; }

/* ── Time zone badges ── */
.zone-best    { background:rgba(81,207,102,.18); color:var(--bull); border:1px solid rgba(81,207,102,.3); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:800; white-space:nowrap; }
.zone-good    { background:rgba(81,207,102,.12); color:#a3e6b0; border:1px solid rgba(81,207,102,.2); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:700; white-space:nowrap; }
.zone-mod     { background:rgba(247,183,51,.14); color:var(--gold); border:1px solid rgba(247,183,51,.3); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:700; white-space:nowrap; }
.zone-avoid   { background:rgba(255,107,107,.14); color:var(--bear); border:1px solid rgba(255,107,107,.3); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:700; white-space:nowrap; }
.zone-caution { background:rgba(255,165,2,.14); color:#ffa502; border:1px solid rgba(255,165,2,.3); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:700; white-space:nowrap; }
.zone-notrade { background:rgba(134,142,150,.12); color:rgba(255,255,255,.3); border:1px solid rgba(134,142,150,.2); border-radius:6px; padding:2px 8px; font-size:10px; font-weight:600; white-space:nowrap; }

/* ── Stock time performance badge ── */
.perf-strong { color:var(--bull); font-size:10px; font-weight:800; }
.perf-mod    { color:var(--gold); font-size:10px; font-weight:700; }
.perf-weak   { color:var(--muted);font-size:9px; font-weight:600; }
.perf-na     { color:rgba(255,255,255,.15); font-size:9px; }

/* ── Exit badge ── */
.exit-badge {
    display:inline-block; background:rgba(255,71,87,.2); color:#ff4757;
    border:1px solid rgba(255,71,87,.5); border-radius:6px;
    padding:2px 9px; font-size:10px; font-weight:800; white-space:nowrap;
}

/* ── Score bar ── */
.score-wrap { display:inline-flex; align-items:center; gap:5px; }
.score-bar  { width:50px; height:5px; background:rgba(255,255,255,.1); border-radius:3px; overflow:hidden; }
.score-fill { height:100%; border-radius:3px; transition:.3s; }
.score-val  { font-size:10px; font-weight:800; }

/* ── Next Day Prediction panel ── */
#next-day-panel { display:none; }
.pred-card {
    background: rgba(0,0,0,0.3); border: 1px solid var(--border);
    border-radius: 12px; padding: 20px 24px; margin-bottom: 12px;
}
.pred-bias-badge {
    display:inline-block; padding:8px 24px; border-radius:10px;
    font-size:16px; font-weight:900; letter-spacing:.5px;
}
.bias-bull { background:rgba(81,207,102,.2); color:var(--bull); border:2px solid rgba(81,207,102,.5); }
.bias-bear { background:rgba(255,107,107,.2); color:var(--bear); border:2px solid rgba(255,107,107,.5); }
.bias-side { background:rgba(134,142,150,.2); color:#adb5bd;  border:2px solid rgba(134,142,150,.4); }
.conf-ring {
    width:80px; height:80px; border-radius:50%;
    display:flex; align-items:center; justify-content:center; flex-direction:column;
    border:4px solid; font-size:18px; font-weight:900;
}
.reason-item {
    background:rgba(255,255,255,.03); border:1px solid var(--border);
    border-radius:8px; padding:8px 14px; font-size:12px; margin-bottom:6px;
    color:rgba(255,255,255,.7);
}

/* ── Time Performance panel ── */
#time-perf-panel { display:none; }
.slot-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(130px, 1fr)); gap:8px; }
.slot-card {
    background:rgba(0,0,0,0.3); border:1px solid var(--border);
    border-radius:8px; padding:10px; text-align:center;
}
.slot-time  { font-size:13px; font-weight:900; color:var(--gold); }
.slot-acc   { font-size:20px; font-weight:900; margin:4px 0; }
.slot-sub   { font-size:9px; color:var(--muted); }
.slot-zone-STRONG   { border-color:rgba(81,207,102,.4)  !important; background:rgba(81,207,102,.05)   !important; }
.slot-zone-MODERATE { border-color:rgba(247,183,51,.35) !important; background:rgba(247,183,51,.04)  !important; }
.slot-zone-WEAK     { border-color:rgba(255,107,107,.3) !important; background:rgba(255,107,107,.03) !important; }

/* ── Misc ── */
.spinner { width:34px; height:34px; border:3px solid rgba(255,255,255,.1); border-top:3px solid var(--bull); border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin  { to { transform:rotate(360deg); } }
@keyframes blink { 50% { opacity:.5; } }
.loading-wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:60px; }
.no-data      { text-align:center; padding:60px; color:var(--muted); font-size:13px; }
.pct-up   { color:var(--bull); font-weight:700; }
.pct-down { color:var(--bear); font-weight:700; }
.pct-flat { color:var(--muted); }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── Page Header ── --}}
    <div class="si-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>⚡ Signal Intelligence
                    <span class="si-tag">5-MIN</span>
                    <span class="si-tag" style="background:rgba(247,183,51,.15);color:var(--gold);border-color:rgba(247,183,51,.3);">BETA</span>
                </h4>
                <p>
                    Market State Engine &middot; OI+Price Confluence &middot; Volume Spike Filter &middot;
                    <strong style="color:var(--bull);">BUY CE / BUY PE</strong> signals &middot;
                    <strong style="color:var(--gold);">Stock-specific time performance</strong> &middot;
                    <strong style="color:var(--cyan);">Next-day bias prediction</strong>
                </p>
            </div>
            <div style="font-size:10px;color:var(--muted);text-align:right;">
                <div>Signal = State + OI + Volume + Time (all 4 must align)</div>
                <div style="margin-top:3px;">Min score 55/100 to trigger entry</div>
            </div>
        </div>
    </div>

    {{-- ── Tab Bar ── --}}
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('signals')">📊 Live Signals</button>
        <button class="tab-btn" onclick="switchTab('timeperf')">⏱️ Time Performance</button>
        <button class="tab-btn" onclick="switchTab('nextday')">🔮 Next Day Bias</button>
    </div>

    {{-- ── Filter Bar ── --}}
    <div class="filter-bar">
        <span class="filter-label">Date</span>
        <button class="date-nav" onclick="shiftDate(-1)">‹</button>
        <input type="date" id="date-picker" class="f-date"
            value="{{ now()->toDateString() }}"
            max="{{ now()->toDateString() }}"
            onchange="onFilterChange()">
        <button class="date-nav" onclick="shiftDate(1)">›</button>
        <button class="date-nav" onclick="goToday()" style="width:auto;padding:0 10px;font-size:10px;">Today</button>
        <div class="divider-v"></div>
        <span class="filter-label">Symbol</span>
        <select id="sym-select" class="f-select" onchange="onFilterChange()">
            <option value="ALL">— All Symbols —</option>
        </select>
        <button class="btn-load" onclick="onFilterChange()">↻ Load</button>
        <button class="auto-btn" id="auto-btn" onclick="toggleAuto()">▶ Auto 5s</button>
        <span id="auto-tag" style="font-size:10px;color:var(--bull);display:none;">● live</span>
        <span id="candle-info" style="font-size:10px;color:var(--muted);"></span>
        <span class="last-upd" id="last-upd"></span>
    </div>

    {{-- ── Stats Row ── --}}
    <div class="stats-row" id="stats-row" style="display:none;">
        <div class="stat-card">
            <div class="s-label">Buy CE Signals</div>
            <div class="s-val" id="stat-buy-ce" style="color:var(--bull);">—</div>
            <div class="s-sub">This session</div>
        </div>
        <div class="stat-card">
            <div class="s-label">Buy PE Signals</div>
            <div class="s-val" id="stat-buy-pe" style="color:var(--bear);">—</div>
            <div class="s-sub">This session</div>
        </div>
        <div class="stat-card">
            <div class="s-label">Vol Spikes</div>
            <div class="s-val" id="stat-spikes" style="color:var(--orange);">—</div>
            <div class="s-sub">Confirmed spikes</div>
        </div>
        <div class="stat-card">
            <div class="s-label">Exit Alerts</div>
            <div class="s-val" id="stat-exits" style="color:#ff4757;">—</div>
            <div class="s-sub">Active positions</div>
        </div>
        <div class="stat-card">
            <div class="s-label">Market State</div>
            <div class="s-val" id="stat-state" style="font-size:13px;margin-top:8px;">—</div>
            <div class="s-sub">Latest candle</div>
        </div>
    </div>

    {{-- ══════════════ TAB: SIGNALS ══════════════ --}}
    <div id="signals-panel">
        <div class="main-card">
            <div class="tbl-wrap">
                <table class="sig-tbl">
                    <thead>
                        <tr class="grp">
                            <th colspan="4" class="hdr-meta">Meta</th>
                            <th colspan="3" class="hdr-state sep-state">🧠 Market State</th>
                            <th colspan="3" class="hdr-oi sep-oi">📈 OI + Price</th>
                            <th colspan="2" class="hdr-vol sep-vol">🔥 Volume</th>
                            <th colspan="2" class="hdr-time sep-time">⏱️ Time Zone</th>
                            <th colspan="4" class="hdr-signal sep-signal">🎯 Entry Signal</th>
                            <th colspan="4" class="hdr-ce sep-ce">📈 CE — ATM</th>
                            <th colspan="4" class="hdr-pe sep-pe">📉 PE — ATM</th>
                        </tr>
                        <tr class="cols">
                            <th class="hdr-meta">#</th>
                            <th class="hdr-meta">Time</th>
                            <th class="hdr-meta">Symbol</th>
                            <th class="hdr-meta">FUT<br><span style="font-size:8px;opacity:.6;font-weight:400;">Price</span></th>

                            <th class="hdr-state sep-state">State</th>
                            <th class="hdr-state">Structure</th>
                            <th class="hdr-state">Detail</th>

                            <th class="hdr-oi sep-oi">Signal</th>
                            <th class="hdr-oi">CE Rel</th>
                            <th class="hdr-oi">PE Rel</th>

                            <th class="hdr-vol sep-vol">Spike</th>
                            <th class="hdr-vol">x Ratio</th>

                            <th class="hdr-time sep-time">Generic<br><span style="font-size:8px;opacity:.6;font-weight:400;">Zone</span></th>
                            <th class="hdr-time">Stock Pref<br><span style="font-size:8px;opacity:.6;font-weight:400;">Accuracy</span></th>

                            <th class="hdr-signal sep-signal">Signal</th>
                            <th class="hdr-signal">Score<br><span style="font-size:8px;opacity:.6;font-weight:400;">/100</span></th>
                            <th class="hdr-signal">Conf</th>
                            <th class="hdr-signal">Exit?</th>

                            <th class="hdr-ce sep-ce">O</th>
                            <th class="hdr-ce">H</th>
                            <th class="hdr-ce">L</th>
                            <th class="hdr-ce">C</th>

                            <th class="hdr-pe sep-pe">O</th>
                            <th class="hdr-pe">H</th>
                            <th class="hdr-pe">L</th>
                            <th class="hdr-pe">C</th>
                        </tr>
                    </thead>
                    <tbody id="sig-tbody">
                        <tr><td colspan="26">
                            <div class="loading-wrap">
                                <div class="spinner"></div>
                                <div style="color:#fff;margin-top:14px;font-size:13px;">Loading signals…</div>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══════════════ TAB: TIME PERFORMANCE ══════════════ --}}
    <div id="time-perf-panel">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <span class="filter-label">Symbol:</span>
            <select id="perf-sym" class="f-select" style="min-width:140px;"></select>
            <select id="perf-days" class="f-select">
                <option value="20">Last 20 days</option>
                <option value="30" selected>Last 30 days</option>
                <option value="45">Last 45 days</option>
            </select>
            <button class="btn-load" onclick="loadTimePerf()">↻ Compute</button>
            <span style="font-size:10px;color:var(--muted);margin-left:auto;">🟢 Strong ≥65% &nbsp; 🟡 Moderate 50–65% &nbsp; 🔴 Weak &lt;50%</span>
        </div>
        <div id="time-perf-content">
            <div class="no-data">Select a symbol and click Compute to see stock-specific time performance.</div>
        </div>
    </div>

    {{-- ══════════════ TAB: NEXT DAY ══════════════ --}}
    <div id="next-day-panel">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
            <span class="filter-label">Date:</span>
            <input type="date" id="nd-date" class="f-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
            <button class="btn-load" onclick="loadNextDay()">↻ Analyze All Symbols</button>
            <span style="font-size:10px;color:var(--muted);">🟢 Bullish &nbsp; 🔴 Bearish &nbsp; ⚪ Sideways — sorted by confidence</span>
        </div>
        <div id="next-day-content">
            <div class="no-data">Click "Analyze All Symbols" to see next-day bias for all available symbols.</div>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
const todayStr = '{{ now()->toDateString() }}';
let autoTimer  = null;
let currentTab = 'signals';

// ══════════════════════════════════════════════════════
// TAB SWITCHING
// ══════════════════════════════════════════════════════
function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach((b,i) => {
        b.classList.toggle('active', ['signals','timeperf','nextday'][i] === tab);
    });
    document.getElementById('signals-panel').style.display  = tab === 'signals'  ? 'block' : 'none';
    document.getElementById('time-perf-panel').style.display = tab === 'timeperf' ? 'block' : 'none';
    document.getElementById('next-day-panel').style.display  = tab === 'nextday'  ? 'block' : 'none';

    // Auto-load next day analysis when tab opened (if not yet loaded)
    if (tab === 'nextday') {
        const el = document.getElementById('next-day-content');
        if (el && el.innerHTML.includes('no-data')) loadNextDay();
    }
    // Mirror symbols to time perf dropdown on switch
    if (tab === 'timeperf') {
        const sigSym = document.getElementById('sym-select');
        const target = document.getElementById('perf-sym');
        if (target && target.options.length === 0 && sigSym && sigSym.options.length > 1) {
            Array.from(sigSym.options).slice(1).forEach(o => {
                const opt = document.createElement('option');
                opt.value = o.value; opt.textContent = o.textContent;
                target.appendChild(opt);
            });
        }
    }
}

// ══════════════════════════════════════════════════════
// DATE / SYMBOL HELPERS
// ══════════════════════════════════════════════════════
function shiftDate(d) {
    const p = document.getElementById('date-picker');
    const dt = new Date(p.value); dt.setDate(dt.getDate() + d);
    const s = dt.toISOString().split('T')[0];
    if (s > todayStr) return;
    p.value = s; onFilterChange();
}
function goToday() { document.getElementById('date-picker').value = todayStr; onFilterChange(); }

function rebuildSymDropdown(syms) {
    const s = document.getElementById('sym-select');
    const prev = s.value;
    s.innerHTML = '<option value="ALL">— All Symbols —</option>';
    syms.forEach(sym => {
        const o = document.createElement('option');
        o.value = sym; o.textContent = sym;
        if (sym === prev) o.selected = true;
        s.appendChild(o);
    });
    // Mirror to other selects
    ['perf-sym','nd-sym'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const pv = el.value;
        el.innerHTML = '';
        syms.forEach(sym => {
            const o = document.createElement('option');
            o.value = sym; o.textContent = sym;
            if (sym === pv) o.selected = true;
            el.appendChild(o);
        });
    });
}

function toggleAuto() {
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        document.getElementById('auto-btn').textContent = '▶ Auto 5s';
        document.getElementById('auto-btn').classList.remove('on');
        document.getElementById('auto-tag').style.display = 'none';
    } else {
        autoTimer = setInterval(onFilterChange, 5000);
        document.getElementById('auto-btn').textContent = '⏹ Stop';
        document.getElementById('auto-btn').classList.add('on');
        document.getElementById('auto-tag').style.display = '';
        onFilterChange();
    }
}

function onFilterChange() {
    const date = document.getElementById('date-picker').value;
    if (date !== todayStr && autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        document.getElementById('auto-btn').textContent = '▶ Auto 5s';
        document.getElementById('auto-btn').classList.remove('on');
        document.getElementById('auto-tag').style.display = 'none';
    }
    loadSignals();
}

// ══════════════════════════════════════════════════════
// SIGNALS
// ══════════════════════════════════════════════════════
function loadSignals() {
    const date = document.getElementById('date-picker').value;
    const sym  = document.getElementById('sym-select').value;

    $('#sig-tbody').html('<tr><td colspan="26"><div class="loading-wrap"><div class="spinner"></div><div style="color:#fff;margin-top:12px;font-size:13px;">Fetching 5-min signals for ' + date + '…</div></div></td></tr>');

    $.ajax({
        url : '{{ route("signal-intel-5min.signals") }}',
        data: { symbol: sym, date: date },
        success(res) {
            if (res.available_symbols && res.available_symbols.length) {
                rebuildSymDropdown(res.available_symbols);
            }
            if (!res.success || !res.data || !res.data.length) {
                $('#sig-tbody').html('<tr><td colspan="26"><div class="no-data">⚠️ ' + (res.message || 'No data') + '</div></td></tr>');
                return;
            }
            renderSignals(res.data);
            updateStats(res.data);
            document.getElementById('last-upd').textContent = 'Updated: ' + new Date().toLocaleTimeString();
        },
        error(xhr) {
            $('#sig-tbody').html('<tr><td colspan="26"><div class="no-data">❌ ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error') + '</div></td></tr>');
        }
    });
}

function updateStats(data) {
    let buyCe=0, buyPe=0, spikes=0, exits=0, lastState='—';
    data.forEach(d => {
        (d.signals || []).forEach(s => {
            if (s.entry_signal.signal === 'BUY_CE') buyCe++;
            if (s.entry_signal.signal === 'BUY_PE') buyPe++;
            if (s.vol_spike && s.vol_spike.confirmed) spikes++;
            if (s.exit_signal && s.exit_signal.exit) exits++;
            lastState = s.market_state ? s.market_state.label : '—';
        });
    });
    document.getElementById('stat-buy-ce').textContent = buyCe;
    document.getElementById('stat-buy-pe').textContent = buyPe;
    document.getElementById('stat-spikes').textContent = spikes;
    document.getElementById('stat-exits').textContent  = exits;
    document.getElementById('stat-state').innerHTML    = stateBadge(data[0]?.signals?.slice(-1)?.[0]?.market_state);
    document.getElementById('stats-row').style.display = '';
}

function renderSignals(dataArr) {
    let rows = '', rn = 1;
    dataArr.forEach((d, si) => {
        const cls = si % 2 === 0 ? 'even' : 'odd';
        (d.signals || []).forEach(sig => {
            const ms  = sig.market_state     || {};
            const oip = sig.oi_price         || {};
            const vs  = sig.vol_spike        || {};
            const tw  = sig.time_window      || {};
            const stp = sig.stock_time_perf;
            const es  = sig.entry_signal     || {};
            const ex  = sig.exit_signal;
            const ce  = sig.ce;
            const pe  = sig.pe;

            // Score bar
            const score    = es.score ?? null;
            const scoreBar = score !== null
                ? `<div class="score-wrap">
                     <div class="score-bar"><div class="score-fill" style="width:${score}%;background:${score>=70?'var(--bull)':score>=55?'var(--gold)':'var(--bear)'};"></div></div>
                     <span class="score-val" style="color:${score>=70?'var(--bull)':score>=55?'var(--gold)':'var(--bear)'};">${score}</span>
                   </div>`
                : '<span style="color:var(--muted);font-size:9px;">—</span>';

            rows += `<tr class="${cls}">
                <td class="c-num">${rn++}</td>
                <td class="c-time">${sig.time}</td>
                <td class="c-sym"><span class="badge-sym">${d.symbol}</span><span class="badge-atm">₹${n(d.atm_strike)}</span></td>
                <td class="c-price">${sig.fut_price ? '₹'+n(sig.fut_price) : '—'}</td>

                <td class="sep-state">${stateBadge(ms)}</td>
                <td style="font-size:9px;color:var(--muted);">${ms.label || '—'}</td>
                <td style="font-size:9px;color:rgba(255,255,255,.3);">${(ms.detail||'').substring(0,30)}</td>

                <td class="sep-oi">${oiSignalBadge(oip.signal)}</td>
                <td>${oiRelBadge(oip.ceRelation)}</td>
                <td>${oiRelBadge(oip.peRelation)}</td>

                <td class="sep-vol">${volBadge(vs)}</td>
                <td>${vs.ratio ? `<span style="font-size:10px;font-weight:800;color:${vs.ratio>=2?'#ff4444':vs.ratio>=1.5?'var(--orange)':'var(--muted)'};">${vs.ratio}x</span>` : '<span style="color:var(--muted);font-size:9px;">—</span>'}</td>

                <td class="sep-time">${timeZoneBadge(tw)}</td>
                <td>${stockPerfCell(stp)}</td>

                <td class="sep-signal">${entrySigBadge(es)}</td>
                <td>${scoreBar}</td>
                <td>${confBadge(es.confidence)}</td>
                <td>${ex && ex.exit ? `<span class="exit-badge">⚠️ EXIT</span>` : '<span style="color:rgba(255,255,255,.12);font-size:9px;">—</span>'}</td>

                <td class="sep-ce c-o">${ce ? '₹'+n(ce.open)  : '—'}</td>
                <td class="c-h">${ce ? '₹'+n(ce.high)  : '—'}</td>
                <td class="c-l">${ce ? '₹'+n(ce.low)   : '—'}</td>
                <td class="c-c">${ce ? '₹'+n(ce.close) : '—'}</td>

                <td class="sep-pe c-o">${pe ? '₹'+n(pe.open)  : '—'}</td>
                <td class="c-h">${pe ? '₹'+n(pe.high)  : '—'}</td>
                <td class="c-l">${pe ? '₹'+n(pe.low)   : '—'}</td>
                <td class="c-c">${pe ? '₹'+n(pe.close) : '—'}</td>
            </tr>`;
        });
    });

    if (!rows) rows = '<tr><td colspan="26"><div class="no-data">No signals found.</div></td></tr>';
    $('#sig-tbody').html(rows);
}

// ══════════════════════════════════════════════════════
// TIME PERFORMANCE
// ══════════════════════════════════════════════════════
function loadTimePerf() {
    const sym  = document.getElementById('perf-sym').value  || 'NIFTY';
    const days = document.getElementById('perf-days').value || 30;
    const el   = document.getElementById('time-perf-content');
    el.innerHTML = '<div class="loading-wrap"><div class="spinner"></div><div style="color:#fff;margin-top:12px;">Computing performance for ' + sym + '…</div></div>';

    $.ajax({
        url : '{{ route("signal-intel-5min.time-performance") }}',
        data: { symbol: sym, days: days },
        success(res) {
            if (!res.success || !res.data || !Object.keys(res.data).length) {
                el.innerHTML = '<div class="no-data">No historical data found for ' + sym + '.</div>';
                return;
            }
            renderTimePerf(res.symbol, res.data, res.days);
        },
        error() { el.innerHTML = '<div class="no-data">❌ Error loading data.</div>'; }
    });
}

function renderTimePerf(symbol, data, days) {
    const slots  = Object.values(data);
    const total  = slots.reduce((a,s) => a+s.total, 0);
    const wins   = slots.reduce((a,s) => a+s.wins, 0);
    const strong = slots.filter(s => s.zone === 'STRONG').length;

    let html = `
        <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
            <div class="pred-card" style="flex:1;min-width:200px;padding:14px 18px;">
                <div style="font-size:10px;color:var(--muted);font-weight:800;">SYMBOL</div>
                <div style="font-size:24px;font-weight:900;color:var(--gold);">${symbol}</div>
                <div style="font-size:10px;color:var(--muted);">Last ${days} trading days</div>
            </div>
            <div class="pred-card" style="flex:1;min-width:150px;padding:14px 18px;">
                <div style="font-size:10px;color:var(--muted);font-weight:800;">TOTAL SIGNALS</div>
                <div style="font-size:24px;font-weight:900;color:#fff;">${total}</div>
            </div>
            <div class="pred-card" style="flex:1;min-width:150px;padding:14px 18px;">
                <div style="font-size:10px;color:var(--muted);font-weight:800;">OVERALL WIN RATE</div>
                <div style="font-size:24px;font-weight:900;color:var(--bull);">${total>0?Math.round(wins/total*100):0}%</div>
            </div>
            <div class="pred-card" style="flex:1;min-width:150px;padding:14px 18px;">
                <div style="font-size:10px;color:var(--muted);font-weight:800;">STRONG SLOTS</div>
                <div style="font-size:24px;font-weight:900;color:var(--bull);">${strong}</div>
                <div style="font-size:10px;color:var(--muted);">≥65% accuracy</div>
            </div>
        </div>
        <div class="slot-grid">`;

    Object.values(data).forEach(s => {
        const color = s.zone === 'STRONG' ? 'var(--bull)' : s.zone === 'MODERATE' ? 'var(--gold)' : 'var(--bear)';
        html += `
            <div class="slot-card slot-zone-${s.zone}" title="${s.total} signals | ${s.wins} wins | ${s.losses} losses">
                <div class="slot-time">${s.time}</div>
                <div class="slot-acc" style="color:${color};">${s.accuracy}%</div>
                <div class="slot-sub">${s.wins}W / ${s.losses}L</div>
                <div class="slot-sub" style="margin-top:2px;">CE:${s.ce_wins} PE:${s.pe_wins}</div>
            </div>`;
    });

    html += '</div>';
    document.getElementById('time-perf-content').innerHTML = html;
}

// ══════════════════════════════════════════════════════
// NEXT DAY PREDICTION
// ══════════════════════════════════════════════════════
function loadNextDay() {
    const date = document.getElementById('nd-date').value || todayStr;
    const el   = document.getElementById('next-day-content');
    el.innerHTML = '<div class="loading-wrap"><div class="spinner"></div><div style="color:#fff;margin-top:12px;">Analyzing all symbols for ' + date + '…</div></div>';

    $.ajax({
        url : '{{ route("signal-intel-5min.next-day-prediction") }}',
        data: { date: date },
        success(res) {
            if (!res.success || !res.data || !res.data.length) {
                el.innerHTML = '<div class="no-data">⚠️ ' + (res.message || 'No data found') + '</div>';
                return;
            }
            renderNextDayAll(res);
        },
        error() { el.innerHTML = '<div class="no-data">❌ Error loading data.</div>'; }
    });
}

function renderNextDayAll(res) {
    const pct = v => {
        if (v === undefined || v === null) return '—';
        return `<span class="${v>0?'pct-up':v<0?'pct-down':'pct-flat'}">${v>0?'+':''}${v}%</span>`;
    };

    const bullish  = res.data.filter(r => r.next_day_bias === 'BULLISH');
    const bearish  = res.data.filter(r => r.next_day_bias === 'BEARISH');
    const sideways = res.data.filter(r => r.next_day_bias === 'SIDEWAYS');

    let html = `
        <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
            <div class="pred-card" style="flex:1;min-width:120px;padding:12px 16px;border-color:rgba(81,207,102,.3);">
                <div style="font-size:10px;color:var(--muted);font-weight:800;">BULLISH</div>
                <div style="font-size:28px;font-weight:900;color:var(--bull);">${bullish.length}</div>
                <div style="font-size:9px;color:var(--muted);">symbols</div>
            </div>
            <div class="pred-card" style="flex:1;min-width:120px;padding:12px 16px;border-color:rgba(255,107,107,.3);">
                <div style="font-size:10px;color:var(--muted);font-weight:800;">BEARISH</div>
                <div style="font-size:28px;font-weight:900;color:var(--bear);">${bearish.length}</div>
                <div style="font-size:9px;color:var(--muted);">symbols</div>
            </div>
            <div class="pred-card" style="flex:1;min-width:120px;padding:12px 16px;">
                <div style="font-size:10px;color:var(--muted);font-weight:800;">SIDEWAYS</div>
                <div style="font-size:28px;font-weight:900;color:#adb5bd;">${sideways.length}</div>
                <div style="font-size:9px;color:var(--muted);">symbols</div>
            </div>
            <div class="pred-card" style="flex:2;min-width:200px;padding:12px 16px;">
                <div style="font-size:10px;color:var(--muted);font-weight:800;">ANALYSIS DATE → NEXT DAY</div>
                <div style="font-size:16px;font-weight:800;color:var(--gold);">${res.analysis_date}</div>
                <div style="font-size:9px;color:var(--muted);">${res.total} symbols analyzed</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;">`;

    res.data.forEach(r => {
        const biasCls   = r.next_day_bias === 'BULLISH' ? 'bias-bull' : r.next_day_bias === 'BEARISH' ? 'bias-bear' : 'bias-side';
        const biasIcon  = r.next_day_bias === 'BULLISH' ? '🟢' : r.next_day_bias === 'BEARISH' ? '🔴' : '⚪';
        const confColor = r.next_day_bias === 'BULLISH' ? 'var(--bull)' : r.next_day_bias === 'BEARISH' ? 'var(--bear)' : '#adb5bd';
        const oi        = r.oi_summary || {};
        const reasons   = (r.reasons || []).slice(0, 2).map(re => `<div style="font-size:9px;color:rgba(255,255,255,.5);margin-top:3px;">${re}</div>`).join('');
        const session   = r.tomorrow_session ? r.tomorrow_session.label : '—';
        const borderClr = r.next_day_bias === 'BULLISH' ? 'rgba(81,207,102,.25)' : r.next_day_bias === 'BEARISH' ? 'rgba(255,107,107,.25)' : 'rgba(134,142,150,.15)';

        html += `
            <div class="pred-card" style="border-color:${borderClr};padding:14px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                    <div>
                        <div style="font-size:14px;font-weight:900;color:var(--gold);">${r.symbol}</div>
                        <div style="font-size:9px;color:var(--muted);">expiry: ${r.expiry || '—'}</div>
                    </div>
                    <div style="text-align:right;">
                        <span class="pred-bias-badge ${biasCls}" style="font-size:11px;padding:3px 10px;">${biasIcon} ${r.next_day_bias}</span>
                        <div style="font-size:10px;font-weight:800;color:${confColor};margin-top:3px;">${r.confidence}% conf</div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;font-size:10px;margin-bottom:6px;">
                    <span style="color:var(--muted);">CE OI: ${pct(oi.ce_oi_pct)}</span>
                    <span style="color:var(--muted);">PE OI: ${pct(oi.pe_oi_pct)}</span>
                    <span style="color:var(--muted);">Close: ${r.close_ratio}%</span>
                </div>
                ${reasons}
                <div style="font-size:9px;color:var(--cyan);margin-top:6px;border-top:1px solid var(--border);padding-top:5px;">${session}</div>
            </div>`;
    });

    html += '</div>';
    document.getElementById('next-day-content').innerHTML = html;
}

// ══════════════════════════════════════════════════════
// BADGE BUILDERS
// ══════════════════════════════════════════════════════

function stateBadge(ms) {
    if (!ms || !ms.state) return '<span class="state-na">—</span>';
    const map = {
        STRONG_BULLISH: ['state-strong-bull', '🟢 Strong Bull'],
        STRONG_BEARISH: ['state-strong-bear', '🔴 Strong Bear'],
        SIDEWAYS:       ['state-sideways',    '⚪ Sideways'],
        REVERSAL:       ['state-reversal',    '⚠️ Reversal'],
    };
    const e = map[ms.state] || ['state-sideways', ms.label || ms.state];
    return `<span class="${e[0]}">${e[1]}</span>`;
}

function oiSignalBadge(sig) {
    if (!sig || sig === 'NEUTRAL') return '<span class="oi-na" style="color:var(--muted);font-size:9px;">—</span>';
    if (sig === 'BULLISH') return '<span style="color:var(--bull);font-size:10px;font-weight:800;">🟢 BULL</span>';
    if (sig === 'BEARISH') return '<span style="color:var(--bear);font-size:10px;font-weight:800;">🔴 BEAR</span>';
    return '<span style="color:var(--muted);font-size:9px;">NEUTRAL</span>';
}

function oiRelBadge(rel) {
    if (!rel || rel === 'N/A') return '<span class="oi-na">—</span>';
    const map = {
        LONG_BUILDUP:   ['oi-long-build',  '↑ Long Build'],
        SHORT_BUILDUP:  ['oi-short-build', '↓ Short Build'],
        SHORT_COVERING: ['oi-short-cover', '↑ Short Cover'],
        LONG_UNWINDING: ['oi-long-unwind', '↓ Long Unwind'],
    };
    const e = map[rel] || ['oi-na', rel];
    return `<span class="${e[0]}">${e[1]}</span>`;
}

function volBadge(vs) {
    if (!vs || !vs.type) return '<span style="color:var(--muted);font-size:9px;">—</span>';
    const map = {
        STRONG_SPIKE: 'vol-strong',
        SPIKE:        'vol-spike',
        ELEVATED:     'vol-elev',
        NORMAL:       'vol-norm',
        OPENING:      'vol-open',
    };
    return `<span class="${map[vs.type]||'vol-norm'}">${vs.label||vs.type}</span>`;
}

function timeZoneBadge(tw) {
    if (!tw || !tw.zone) return '<span style="color:var(--muted);font-size:9px;">—</span>';
    const map = {
        BEST:     'zone-best',
        GOOD:     'zone-good',
        MODERATE: 'zone-mod',
        AVOID:    'zone-avoid',
        CAUTION:  'zone-caution',
        NO_TRADE: 'zone-notrade',
        OPENING:  'zone-mod',
    };
    return `<span class="${map[tw.zone]||'zone-mod'}">${tw.label||tw.zone}</span>`;
}

function stockPerfCell(stp) {
    if (!stp) return '<span class="perf-na">N/A</span>';
    const cls = stp.zone === 'STRONG' ? 'perf-strong' : stp.zone === 'MODERATE' ? 'perf-mod' : 'perf-weak';
    const icon = stp.zone === 'STRONG' ? '🟢' : stp.zone === 'MODERATE' ? '🟡' : '🔴';
    return `<span class="${cls}">${icon} ${stp.accuracy}%<br><span style="font-size:8px;color:var(--muted);">${stp.wins}/${stp.total}</span></span>`;
}

function entrySigBadge(es) {
    if (!es || !es.signal) return '<span class="sig-no-trade">—</span>';
    if (es.signal === 'BUY_CE')   return '<span class="sig-buy-ce">🟢 BUY CE</span>';
    if (es.signal === 'BUY_PE')   return '<span class="sig-buy-pe">🔴 BUY PE</span>';
    if (es.signal === 'NO_TRADE') return '<span class="sig-no-trade">⚫ NO TRADE</span>';
    return '<span class="sig-blocked">🚫 BLOCKED</span>';
}

function confBadge(conf) {
    if (!conf) return '<span style="color:var(--muted);font-size:9px;">—</span>';
    const map = {
        VERY_HIGH: ['conf-very-high', '★★★★'],
        HIGH:      ['conf-high',      '★★★'],
        MEDIUM:    ['conf-medium',    '★★'],
        LOW:       ['conf-low',       '★'],
        BLOCKED:   ['conf-low',       '—'],
    };
    const e = map[conf] || ['conf-low', conf];
    return `<span class="${e[0]}">${e[1]}</span>`;
}

function n(v) {
    if (v == null || v === '') return '—';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function nInt(v) {
    if (v == null) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

// ══════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════
$(document).ready(function () {
    // Panels are hidden via inline style in HTML — no need to re-hide here.
    // switchTab() uses 'block' so they will show correctly when clicked.
    loadSignals();
});

// After signals load, rebuildSymDropdown mirrors symbols to perf-sym and nd-sym.
// But if user switches tab before signals finish, we also populate on tab switch.
// This is handled inside switchTab() above — no extra code needed here.
</script>
@endpush