@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════════════════
   SMART ENTRY ENGINE — Terminal Black / Signal Green
═══════════════════════════════════════════════════════════════ */
:root {
    --ink:        #07090e;
    --surface:    #0d1018;
    --raised:     #131720;
    --hover:      #191f2d;
    --rim:        rgba(255,255,255,0.06);
    --rim2:       rgba(255,255,255,0.10);

    --call:       #00ff88;
    --call-dim:   rgba(0,255,136,0.10);
    --call-bdr:   rgba(0,255,136,0.28);
    --call-glow:  rgba(0,255,136,0.18);

    --put:        #ff4060;
    --put-dim:    rgba(255,64,96,0.10);
    --put-bdr:    rgba(255,64,96,0.28);
    --put-glow:   rgba(255,64,96,0.18);

    --wait:       #f0b429;
    --wait-dim:   rgba(240,180,41,0.10);
    --wait-bdr:   rgba(240,180,41,0.30);

    --trap:       #c084fc;
    --trap-dim:   rgba(192,132,252,0.10);
    --trap-bdr:   rgba(192,132,252,0.30);

    --cyan:       #22d3ee;
    --dim1:       rgba(255,255,255,0.80);
    --dim2:       rgba(255,255,255,0.50);
    --dim3:       rgba(255,255,255,0.25);
    --dim4:       rgba(255,255,255,0.10);

    --r:          10px;
    --rs:         6px;
    --rm:         14px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--ink); }

/* ─── WRAP ────────────────────────────────────────────────── */
.see-wrap { max-width: 1400px; margin: 0 auto; padding: 24px 16px 80px; }

/* ─── TOP BAR ─────────────────────────────────────────────── */
.top-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
.top-logo { font-size: 11px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; color: var(--dim3); }
.top-logo span { color: var(--call); }
.top-sep { flex: 1; height: 1px; background: var(--rim); }
.top-pill {
    font-size: 9px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
    padding: 3px 10px; border-radius: 20px;
    background: var(--raised); border: 1px solid var(--rim2); color: var(--dim3);
}
.top-pill.live   { color: var(--call); border-color: var(--call-bdr); background: var(--call-dim); }
.top-pill.expiry { color: var(--put);  border-color: var(--put-bdr);  background: var(--put-dim);  }

/* ─── FILTER BAR ──────────────────────────────────────────── */
.filter-bar {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    background: var(--surface); border: 1px solid var(--rim);
    border-radius: var(--r); padding: 10px 14px; margin-bottom: 16px;
}
.fl { font-size: 9px; font-weight: 700; color: var(--dim3); text-transform: uppercase; letter-spacing: 0.8px; }
.sym-btn {
    font-size: 10px; font-weight: 700; padding: 5px 13px;
    border-radius: var(--rs); border: 1px solid var(--rim);
    background: var(--raised); color: var(--dim3); cursor: pointer; transition: 0.12s;
}
.sym-btn:hover, .sym-btn.on { background: var(--wait-dim); border-color: var(--wait-bdr); color: var(--wait); }
.fdiv { width: 1px; height: 18px; background: var(--rim); flex-shrink: 0; }
.date-wrap { display: flex; align-items: center; gap: 5px; }
.date-wrap input[type="date"] {
    font-size: 10px; font-weight: 600;
    background: var(--raised); border: 1px solid var(--rim);
    border-radius: var(--rs); color: var(--dim1); padding: 5px 10px; outline: none; cursor: pointer;
}
.date-wrap input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.6); cursor: pointer; }
.fnav {
    width: 28px; height: 28px; background: var(--raised); border: 1px solid var(--rim);
    border-radius: var(--rs); color: var(--dim2); font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.12s;
}
.fnav:hover { background: var(--wait-dim); border-color: var(--wait-bdr); color: var(--wait); }
.btn-load {
    font-size: 10px; font-weight: 800;
    background: var(--wait); color: var(--ink); border: none;
    border-radius: var(--rs); padding: 6px 18px; cursor: pointer; transition: 0.15s;
}
.btn-load:hover { opacity: 0.85; }
.btn-auto {
    font-size: 9px; font-weight: 700;
    background: var(--raised); border: 1px solid var(--rim);
    color: var(--dim2); border-radius: var(--rs); padding: 5px 12px; cursor: pointer;
}
.btn-auto.on { border-color: var(--call-bdr); color: var(--call); }
.f-upd { font-size: 9px; color: var(--dim3); margin-left: auto; }

/* ─── TWO-COLUMN GRID ─────────────────────────────────────── */
.main-grid { display: grid; grid-template-columns: 1fr 380px; gap: 14px; align-items: start; }
@media(max-width: 960px) { .main-grid { grid-template-columns: 1fr; } }

/* ─── TRADE DECISION BOX — left column ───────────────────── */
.decision-box {
    border-radius: var(--rm); border: 1px solid var(--rim);
    background: var(--surface); overflow: hidden; position: relative;
}
/* colour the top border based on signal */
.decision-box.call  { border-color: var(--call-bdr); }
.decision-box.put   { border-color: var(--put-bdr);  }
.decision-box.wait  { border-color: var(--wait-bdr); }
.decision-box.none  { border-color: var(--rim2);     }

/* glow strip at top */
.decision-box::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    opacity: 0;
}
.decision-box.call::before  { background: var(--call); opacity: 1; }
.decision-box.put::before   { background: var(--put);  opacity: 1; }
.decision-box.wait::before  { background: var(--wait); opacity: 0.7; }

.db-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--rim);
    background: var(--raised);
    display: flex; align-items: center; justify-content: space-between;
}
.db-label { font-size: 9px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--dim3); margin-bottom: 5px; }
.db-title { font-size: 30px; font-weight: 900; letter-spacing: -1px; line-height: 1; }
.db-title.call  { color: var(--call); }
.db-title.put   { color: var(--put);  }
.db-title.wait  { color: var(--wait); }
.db-title.none  { color: var(--dim3); }
.db-time { text-align: right; }
.db-time-label { font-size: 9px; color: var(--dim3); letter-spacing: 0.5px; margin-bottom: 4px; }
.db-time-val { font-size: 22px; font-weight: 900; color: var(--dim1); }
.db-time-sub { font-size: 9px; color: var(--dim3); margin-top: 3px; }

/* confidence bar */
.conf-bar-wrap { padding: 0 24px 0; margin-top: 14px; }
.conf-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px; }
.conf-label { font-size: 9px; font-weight: 700; color: var(--dim3); letter-spacing: 0.5px; text-transform: uppercase; }
.conf-pct { font-size: 20px; font-weight: 900; }
.conf-pct.call { color: var(--call); }
.conf-pct.put  { color: var(--put);  }
.conf-track { width: 100%; height: 6px; background: var(--dim4); border-radius: 3px; overflow: hidden; margin-bottom: 4px; }
.conf-fill  { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.conf-fill.call { background: linear-gradient(90deg, var(--call-dim), var(--call)); }
.conf-fill.put  { background: linear-gradient(90deg, var(--put-dim),  var(--put));  }
.conf-sub { font-size: 9px; color: var(--dim3); text-align: right; }

/* score pills */
.score-row { display: flex; gap: 6px; flex-wrap: wrap; padding: 14px 24px 0; }
.score-pill {
    font-size: 9px; font-weight: 800; letter-spacing: 0.3px;
    padding: 3px 10px; border-radius: 20px;
    background: var(--raised); border: 1px solid var(--rim2); color: var(--dim3);
}
.score-pill.call { background: var(--call-dim); border-color: var(--call-bdr); color: var(--call); }
.score-pill.put  { background: var(--put-dim);  border-color: var(--put-bdr);  color: var(--put);  }
.score-pill.trap { background: var(--trap-dim); border-color: var(--trap-bdr); color: var(--trap); }

/* reasons list */
.reasons-wrap { padding: 16px 24px; }
.reasons-title { font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--dim3); margin-bottom: 10px; }
.reason-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 12px; border-radius: var(--rs);
    border: 1px solid var(--rim); margin-bottom: 6px;
    background: var(--raised);
    transition: 0.15s;
}
.reason-item:hover { background: var(--hover); }
.reason-left { display: flex; align-items: center; gap: 8px; }
.reason-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--dim3); flex-shrink: 0; }
.reason-dot.call { background: var(--call); box-shadow: 0 0 6px var(--call); }
.reason-dot.put  { background: var(--put);  box-shadow: 0 0 6px var(--put);  }
.reason-dot.trap { background: var(--trap); box-shadow: 0 0 6px var(--trap); }
.reason-key { font-size: 11px; font-weight: 700; color: var(--dim1); }
.reason-val { font-size: 10px; color: var(--dim2); margin-left: 6px; }
.reason-weight { font-size: 10px; font-weight: 800; color: var(--dim3); }
.reason-weight.call { color: var(--call); }
.reason-weight.put  { color: var(--put);  }
.reason-weight.trap { color: var(--trap); }

/* strike card */
.strike-wrap { padding: 0 24px 16px; }
.strike-card {
    border-radius: var(--r); padding: 18px 20px;
    display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0;
    border: 1px solid var(--rim);
}
.strike-card.call { background: var(--call-dim); border-color: var(--call-bdr); }
.strike-card.put  { background: var(--put-dim);  border-color: var(--put-bdr);  }

.sk-col { text-align: center; }
.sk-col + .sk-col { border-left: 1px solid var(--rim); }
.sk-label { font-size: 8px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: var(--dim3); margin-bottom: 5px; }
.sk-val { font-size: 20px; font-weight: 900; }
.sk-val.call { color: var(--call); }
.sk-val.put  { color: var(--put);  }
.sk-val.entry { color: var(--dim1); }
.sk-val.tgt   { color: var(--call); }
.sk-val.sl    { color: var(--put);  }
.sk-sub { font-size: 9px; color: var(--dim3); margin-top: 3px; }

/* exit note */
.exit-note {
    margin: 0 24px 20px; padding: 10px 14px; border-radius: var(--rs);
    background: var(--raised); border: 1px solid var(--rim);
    font-size: 10px; color: var(--dim2); line-height: 1.7;
    display: flex; gap: 12px;
}
.exit-note .en-item { display: flex; align-items: center; gap: 5px; }
.en-dot { width: 6px; height: 6px; border-radius: 50%; }
.en-dot.tgt { background: var(--call); }
.en-dot.sl  { background: var(--put);  }

/* wait state */
.wait-state {
    padding: 40px 24px; text-align: center;
}
.wait-icon { font-size: 40px; margin-bottom: 14px; opacity: 0.5; }
.wait-title { font-size: 18px; font-weight: 800; color: var(--wait); margin-bottom: 8px; }
.wait-sub { font-size: 12px; color: var(--dim3); line-height: 1.7; max-width: 300px; margin: 0 auto; }
.wait-score { margin-top: 16px; font-size: 11px; color: var(--dim2); }
.wait-score span { font-weight: 800; color: var(--wait); }

/* mm trap banner */
.trap-banner {
    margin: 0 24px 16px; padding: 12px 16px; border-radius: var(--rs);
    background: var(--trap-dim); border: 1px solid var(--trap-bdr);
    font-size: 10px; color: var(--trap); line-height: 1.6;
}
.trap-banner strong { font-weight: 800; }

/* ─── RIGHT COLUMN ────────────────────────────────────────── */
.right-col { display: flex; flex-direction: column; gap: 12px; }

/* OI Walls card */
.oi-card {
    background: var(--surface); border: 1px solid var(--rim);
    border-radius: var(--r); overflow: hidden;
}
.oi-card-hdr {
    padding: 11px 16px;
    background: var(--raised);
    border-bottom: 1px solid var(--rim);
    font-size: 10px; font-weight: 800; color: var(--dim2);
    display: flex; align-items: center; gap: 6px;
}
.oi-walls-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.oi-wall-side { padding: 14px 16px; }
.oi-wall-side + .oi-wall-side { border-left: 1px solid var(--rim); }
.owlabel { font-size: 8px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: var(--dim3); margin-bottom: 6px; }
.owstrike { font-size: 22px; font-weight: 900; margin-bottom: 2px; }
.owstrike.call { color: var(--call); }
.owstrike.put  { color: var(--put);  }
.owoi { font-size: 10px; color: var(--dim3); margin-bottom: 8px; }
.ow-bar-wrap { margin-top: 8px; }
.ow-bar-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; font-size: 9px; color: var(--dim3); }
.ow-bar-track { height: 4px; background: var(--dim4); border-radius: 2px; overflow: hidden; margin-bottom: 6px; }
.ow-bar-fill { height: 100%; border-radius: 2px; }
.ow-bar-fill.call { background: var(--call); }
.ow-bar-fill.put  { background: var(--put);  }

/* Pressure timeline chart */
.pressure-card {
    background: var(--surface); border: 1px solid var(--rim);
    border-radius: var(--r); overflow: hidden;
}
.pressure-card-hdr {
    padding: 11px 16px; background: var(--raised);
    border-bottom: 1px solid var(--rim);
    font-size: 10px; font-weight: 800; color: var(--dim2);
    display: flex; align-items: center; justify-content: space-between;
}
.pressure-chart { padding: 14px 16px; }
.p-chart-bars { display: flex; align-items: flex-end; gap: 3px; height: 80px; position: relative; }
.p-threshold-line {
    position: absolute; left: 0; right: 0; border-top: 1px dashed rgba(240,180,41,0.40);
}
.p-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.p-bar {
    width: 100%; border-radius: 2px 2px 0 0; transition: height 0.4s ease;
    cursor: default; min-height: 2px; position: relative;
}
.p-bar.p-low    { background: rgba(255,255,255,0.12); }
.p-bar.p-watch  { background: rgba(240,180,41,0.4);  }
.p-bar.p-high   { background: rgba(240,180,41,0.75); }
.p-bar.p-call   { background: var(--call); box-shadow: 0 0 8px var(--call-glow); }
.p-bar.p-put    { background: var(--put);  box-shadow: 0 0 8px var(--put-glow);  }
.p-bar.p-fired  { animation: pulse 1.5s ease infinite; }
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.6; }
}
.p-slot { font-size: 7px; color: var(--dim3); writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg); }
.p-fired-dot { position: absolute; top: -6px; left: 50%; transform: translateX(-50%); width: 6px; height: 6px; border-radius: 50%; }
.p-fired-dot.call { background: var(--call); box-shadow: 0 0 8px var(--call); }
.p-fired-dot.put  { background: var(--put);  box-shadow: 0 0 8px var(--put);  }

.p-legend { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
.p-leg-item { display: flex; align-items: center; gap: 4px; font-size: 8px; color: var(--dim3); }
.p-leg-dot { width: 8px; height: 8px; border-radius: 2px; }

/* Score breakdown table */
.score-table-card {
    background: var(--surface); border: 1px solid var(--rim);
    border-radius: var(--r); overflow: hidden;
}
.score-table-hdr {
    padding: 11px 16px; background: var(--raised);
    border-bottom: 1px solid var(--rim);
    font-size: 10px; font-weight: 800; color: var(--dim2);
}
.score-table { width: 100%; border-collapse: collapse; font-size: 10px; }
.score-table th {
    padding: 6px 12px; text-align: left; font-size: 8px; font-weight: 700; letter-spacing: 0.3px;
    color: var(--dim3); text-transform: uppercase;
    border-bottom: 1px solid var(--rim); background: rgba(0,0,0,0.3);
}
.score-table td {
    padding: 7px 12px; border-bottom: 1px solid rgba(255,255,255,0.025);
    color: var(--dim2); vertical-align: middle;
}
.score-table tr:hover td { background: var(--hover); }
.sc-name { font-weight: 700; color: var(--dim1); }
.sc-active { color: var(--call); font-weight: 800; }
.sc-inactive { color: var(--dim3); }
.sc-w { font-weight: 800; text-align: right; }

/* ─── STATS ROW ───────────────────────────────────────────── */
.stats-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.sbox {
    background: var(--surface); border: 1px solid var(--rim);
    border-radius: var(--r); padding: 12px 16px; flex: 1; min-width: 120px; text-align: center;
}
.sbox.hl { border-color: var(--wait-bdr); background: var(--wait-dim); }
.sbox-label { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--dim3); margin-bottom: 5px; }
.sbox-val { font-size: 22px; font-weight: 900; }
.sbox-val.call { color: var(--call); }
.sbox-val.put  { color: var(--put);  }
.sbox-val.wait { color: var(--wait); }
.sbox-val.trap { color: var(--trap); }
.sbox-sub { font-size: 9px; color: var(--dim3); margin-top: 2px; }

/* ─── LOADING / EMPTY ─────────────────────────────────────── */
.spin { width: 28px; height: 28px; border: 2px solid var(--rim); border-top: 2px solid var(--wait); border-radius: 50%; animation: s 0.9s linear infinite; }
@keyframes s { to { transform: rotate(360deg); } }
.loading-s { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 70px; gap: 12px; color: var(--dim3); font-size: 11px; }
.empty-s { text-align: center; padding: 70px; color: var(--dim3); font-size: 12px; }

.hint { cursor: help; }

/* not expiry warn */
.not-expiry-warn {
    padding: 10px 16px; border-radius: var(--rs);
    background: rgba(240,180,41,0.06); border: 1px solid rgba(240,180,41,0.2);
    font-size: 10px; color: rgba(240,180,41,0.7);
    margin-bottom: 14px;
}
</style>
@endpush

<section class="pt-40 pb-50">
<div class="see-wrap">

    {{-- ── TOP BAR ── --}}
    <div class="top-bar">
        <span class="top-logo">⚡ Smart <span>Entry</span> Engine</span>
        <span class="top-sep"></span>
        <span class="top-pill">One Trade · High Accuracy</span>
        <span class="top-pill">MM Trap Detection</span>
        <span id="badge-live" class="top-pill" style="display:none;"></span>
    </div>

    {{-- ── FILTER BAR ── --}}
    <div class="filter-bar">
        <span class="fl">Symbol</span>
        <select id="symSelect" class="sym-btn" style="padding:5px 10px;cursor:pointer;" onchange="selSym(this.value)">
            <option value="">Loading…</option>
        </select>
        <div class="fdiv"></div>
        <span class="fl">Date</span>
        <div class="date-wrap">
            <button class="fnav" onclick="shiftDate(-1)">‹</button>
            <input type="date" id="dp" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" onchange="load()">
            <button class="fnav" onclick="shiftDate(1)">›</button>
            <button class="fnav" style="width:auto;padding:0 9px;font-size:8px;font-weight:800;" onclick="goToday()">TODAY</button>
        </div>
        <button class="btn-load" onclick="load()">↺ Analyze</button>
        <button class="btn-auto" id="btnAuto" onclick="toggleAuto()">▶ Auto 60s</button>
        <span class="f-upd" id="lastUpd"></span>
    </div>

    {{-- ── MAIN OUTPUT ── --}}
    <div id="main">
        <div class="loading-s"><div class="spin"></div><span>Initialising engine…</span></div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
const TODAY = '{{ now()->toDateString() }}';
let sym = 'NIFTY', autoTimer = null;

$(document).ready(() => {
    // Load symbols into dropdown, then load data
    $.ajax({
        url: '{{ route("expiry-oi.symbols") }}',
        success(res) {
            const sel = document.getElementById('symSelect');
            if (res.success && res.symbols.length) {
                sel.innerHTML = res.symbols.map(s =>
                    `<option value="${s}" ${s === sym ? 'selected' : ''}>${s}</option>`
                ).join('');
            } else {
                sel.innerHTML = '<option value="NIFTY">NIFTY</option>';
            }
            load();
        },
        error() {
            document.getElementById('symSelect').innerHTML = '<option value="NIFTY">NIFTY</option>';
            load();
        }
    });
});

function selSym(s) {
    sym = s; load();
}

function shiftDate(d) {
    const dp = document.getElementById('dp');
    const dt = new Date(dp.value); dt.setDate(dt.getDate() + d);
    const s  = dt.toISOString().split('T')[0];
    if (s > TODAY) return; dp.value = s; load();
}
function goToday() { document.getElementById('dp').value = TODAY; load(); }
function toggleAuto() {
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        const b = document.getElementById('btnAuto');
        b.innerHTML = '▶ Auto 60s'; b.classList.remove('on');
    } else {
        autoTimer = setInterval(load, 60000);
        const b = document.getElementById('btnAuto');
        b.innerHTML = '⬛ Stop'; b.classList.add('on');
        load();
    }
}
function load() {
    const date = document.getElementById('dp').value;
    if (date !== TODAY && autoTimer) toggleAuto();
    $('#main').html('<div class="loading-s"><div class="spin"></div><span>Analyzing ' + sym + ' · ' + date + '…</span></div>');
    $.ajax({
        url : '{{ route("expiry-oi.data") }}',
        data: { symbol: sym, date },
        success(res) {
            updateBadge(res);
            if (!res.success || !res.timeline) {
                $('#main').html('<div class="empty-s">⚠ ' + (res.message || 'No data') + '</div>');
                return;
            }
            renderAll(res);
            document.getElementById('lastUpd').textContent = 'Analyzed ' + new Date().toLocaleTimeString();
        },
        error(xhr) {
            $('#main').html('<div class="empty-s">⚠ ' + ((xhr.responseJSON||{}).message||'Server error') + '</div>');
        }
    });
}
function updateBadge(res) {
    const el = document.getElementById('badge-live');
    el.style.display = 'inline-block';
    if      (res.is_expiry_date && res.is_today) { el.textContent = '🔥 EXPIRY TODAY'; el.className = 'top-pill expiry'; }
    else if (res.is_expiry_date)                 { el.textContent = '🔥 EXPIRY DATE';  el.className = 'top-pill expiry'; }
    else if (res.is_today)                       { el.textContent = '● LIVE';          el.className = 'top-pill live';   }
    else                                         { el.textContent = '📅 Historical';   el.className = 'top-pill';        }
}

// ═════════════════════════════════════════════════════════════════════════════
function renderAll(res) {
    let html = '';
    if (!res.is_expiry_date) {
        html += `<div class="not-expiry-warn">⚠ <strong>${res.date}</strong> is not an expiry day for <strong>${res.symbol}</strong>. Signals still run but gamma effects are weaker. Nearest expiry: <strong style="color:var(--wait);">${res.expiry||'N/A'}</strong></div>`;
    }
    html += renderStats(res);
    html += `<div class="main-grid">
        <div>${renderDecisionBox(res)}</div>
        <div class="right-col">
            ${renderOIWalls(res)}
            ${renderPressureChart(res.timeline)}
            ${renderScoreTable(res.best_trade, res.timeline)}
        </div>
    </div>`;
    $('#main').html(html);
}

// ─── STATS ROW ────────────────────────────────────────────────────────────────
function renderStats(res) {
    const sm = res.summary || {};
    const bt = res.best_trade;
    const walls = res.oi_walls || {};
    return `<div class="stats-row">
        <div class="sbox hl">
            <div class="sbox-label">Trade Signal</div>
            <div class="sbox-val ${bt ? (bt.signal==='BUY_CALL'?'call':'put') : 'wait'}">${bt ? (bt.signal==='BUY_CALL'?'BUY CALL':'BUY PUT') : 'WAIT'}</div>
            <div class="sbox-sub">at ${bt ? bt.slot : '—'}</div>
        </div>
        <div class="sbox">
            <div class="sbox-label">Confidence</div>
            <div class="sbox-val ${bt ? (bt.signal==='BUY_CALL'?'call':'put') : 'wait'}">${bt ? bt.confidence+'%' : '—'}</div>
            <div class="sbox-sub">score ${bt ? bt.score+'/17' : sm.peak_score+'/17 peak'}</div>
        </div>
        <div class="sbox">
            <div class="sbox-label">Call Wall</div>
            <div class="sbox-val call">${fNum(walls.call_wall||0)}</div>
            <div class="sbox-sub">${fOi(walls.call_wall_oi)} OI</div>
        </div>
        <div class="sbox">
            <div class="sbox-label">Put Wall</div>
            <div class="sbox-val put">${fNum(walls.put_wall||0)}</div>
            <div class="sbox-sub">${fOi(walls.put_wall_oi)} OI</div>
        </div>
        <div class="sbox">
            <div class="sbox-label">MM Traps</div>
            <div class="sbox-val trap">${sm.mm_trap_slots||0}</div>
            <div class="sbox-sub">slots detected</div>
        </div>
        <div class="sbox">
            <div class="sbox-label">Gamma Slots</div>
            <div class="sbox-val call">${sm.gamma_slots||0}</div>
            <div class="sbox-sub">squeeze candles</div>
        </div>
        <div class="sbox">
            <div class="sbox-label">Slots Analyzed</div>
            <div class="sbox-val wait">${sm.slots_analyzed||0}</div>
            <div class="sbox-sub">30-min candles</div>
        </div>
    </div>`;
}

// ─── MAIN DECISION BOX ───────────────────────────────────────────────────────
function renderDecisionBox(res) {
    const bt  = res.best_trade;
    const sm  = res.summary || {};

    if (!bt) {
        // No trade fired
        const peak = sm.peak_score || 0;
        return `<div class="decision-box wait">
            <div class="db-header">
                <div>
                    <div class="db-label">Engine Decision</div>
                    <div class="db-title wait">NO TRADE TODAY</div>
                </div>
                <div class="db-time">
                    <div class="db-time-label">Peak Score</div>
                    <div class="db-time-val" style="color:var(--wait);">${peak}<span style="font-size:13px;color:var(--dim3);">/17</span></div>
                    <div class="db-time-sub">Need ≥ 8 to fire</div>
                </div>
            </div>
            <div class="wait-state">
                <div class="wait-icon">⏳</div>
                <div class="wait-title">Signals Not Strong Enough</div>
                <div class="wait-sub">Engine scanned ${sm.slots_analyzed||0} candles.<br>Peak pressure: ${peak}/17 — below threshold of 8.<br><br>Staying out is also a trade decision. Protect capital.</div>
                <div class="wait-score">Threshold: <span>8 / 17</span> &nbsp;|&nbsp; Best seen: <span>${peak} / 17</span> at <span>${sm.peak_slot||'—'}</span></div>
            </div>
        </div>`;
    }

    const isCall  = bt.signal === 'BUY_CALL';
    const cls     = isCall ? 'call' : 'put';
    const title   = isCall ? '📈 BUY CALL' : '📉 BUY PUT';
    const reasons = bt.reasons || [];
    const strike  = bt.strike;
    const mmTrap  = bt.mm_trap;
    const trapActive = mmTrap && (mmTrap.call_trap || mmTrap.put_trap);

    let reasonsHtml = reasons.map(r => {
        const isMMTrap = r.key.includes('MM') || r.key.includes('Trap');
        const dotCls   = isMMTrap ? 'trap' : cls;
        return `<div class="reason-item">
            <div class="reason-left">
                <div class="reason-dot ${dotCls}"></div>
                <div>
                    <span class="reason-key">${esc(r.key)}</span>
                    <span class="reason-val">${esc(String(r.val||''))}</span>
                </div>
            </div>
            <div class="reason-weight ${isMMTrap ? 'trap' : cls}">+${r.score}</div>
        </div>`;
    }).join('');

    let strikeHtml = '';
    if (strike) {
        strikeHtml = `<div class="strike-wrap">
            <div class="strike-card ${cls}">
                <div class="sk-col">
                    <div class="sk-label">Strike</div>
                    <div class="sk-val ${cls}">${fNum(strike.strike)}</div>
                    <div class="sk-sub">${esc(strike.symbol||'')} · ${esc(strike.position||'ATM')}</div>
                </div>
                <div class="sk-col">
                    <div class="sk-label">Entry / Target</div>
                    <div class="sk-val entry" style="font-size:15px;">₹${bt.entry_price||'—'}</div>
                    <div class="sk-sub" style="color:var(--call);">Target ₹${bt.target||'—'}</div>
                </div>
                <div class="sk-col">
                    <div class="sk-label">Stop Loss</div>
                    <div class="sk-val sl" style="font-size:15px; color:var(--put);">₹${bt.stoploss||'—'}</div>
                    <div class="sk-sub">Risk ₹${bt.entry_price && bt.stoploss ? Math.round(bt.entry_price - bt.stoploss) : '—'}</div>
                </div>
            </div>
        </div>`;
    }

    const exitNote = `<div class="exit-note">
        <div class="en-item"><div class="en-dot tgt"></div> <strong>Target +200%</strong> — exit when 3× your entry price</div>
        <div class="en-item"><div class="en-dot sl"></div>  <strong>Stop Loss −50%</strong> — exit if premium drops to half</div>
    </div>`;

    const trapBanner = trapActive ? `<div class="trap-banner">
        🪤 <strong>Market Maker Trap Detected</strong> — ${esc(mmTrap.detail||'')}
    </div>` : '';

    const scoreHTML = `<div class="score-row">
        <div class="score-pill ${cls}">Score ${bt.score}/17</div>
        <div class="score-pill ${cls}">Confidence ${bt.confidence}%</div>
        ${trapActive ? `<div class="score-pill trap">🪤 MM Trap +4</div>` : ''}
        ${bt.mm_trap?.call_trap || bt.mm_trap?.put_trap ? `<div class="score-pill trap">Wall Breakout</div>` : ''}
    </div>`;

    return `<div class="decision-box ${cls}">
        <div class="db-header">
            <div>
                <div class="db-label">Best Entry Found</div>
                <div class="db-title ${cls}">${title}</div>
            </div>
            <div class="db-time">
                <div class="db-time-label">Entry Time</div>
                <div class="db-time-val">${bt.slot}</div>
                <div class="db-time-sub">Based on prev candle</div>
            </div>
        </div>
        <div class="conf-bar-wrap">
            <div class="conf-row">
                <span class="conf-label">Confidence</span>
                <span class="conf-pct ${cls}">${bt.confidence}%</span>
            </div>
            <div class="conf-track">
                <div class="conf-fill ${cls}" style="width:${bt.confidence}%;"></div>
            </div>
            <div class="conf-sub">Score ${bt.score} / 17 &nbsp;·&nbsp; Bull ${bt.bull_score} vs Bear ${bt.bear_score} &nbsp;·&nbsp; Futures: ${esc(bt.futures_dir?.direction||'—')}</div>
        </div>
        ${scoreHTML}
        ${trapBanner}
        <div class="reasons-wrap">
            <div class="reasons-title">Why This Trade Fired</div>
            ${reasonsHtml || '<div style="color:var(--dim3);font-size:11px;padding:8px 0;">Signals data loading…</div>'}
        </div>
        ${strikeHtml}
        ${exitNote}
    </div>`;
}

// ─── OI WALLS ────────────────────────────────────────────────────────────────
function renderOIWalls(res) {
    const w   = res.oi_walls || {};
    const tce = w.top_ce_strikes || {};
    const tpe = w.top_pe_strikes || {};

    const maxCe = Object.values(tce)[0] || 1;
    const maxPe = Object.values(tpe)[0] || 1;

    const ceRows = Object.entries(tce).slice(0,5).map(([k,v]) => {
        const pct = Math.round((v / maxCe) * 100);
        const isWall = parseFloat(k) === w.call_wall;
        return `<div class="ow-bar-row">
            <span style="${isWall?'color:var(--call);font-weight:800;':''}">${fNum(k)}</span>
            <span>${fOi(v)}</span>
        </div>
        <div class="ow-bar-track"><div class="ow-bar-fill call" style="width:${pct}%;"></div></div>`;
    }).join('');

    const peRows = Object.entries(tpe).slice(0,5).map(([k,v]) => {
        const pct = Math.round((v / maxPe) * 100);
        const isWall = parseFloat(k) === w.put_wall;
        return `<div class="ow-bar-row">
            <span style="${isWall?'color:var(--put);font-weight:800;':''}">${fNum(k)}</span>
            <span>${fOi(v)}</span>
        </div>
        <div class="ow-bar-track"><div class="ow-bar-fill put" style="width:${pct}%;"></div></div>`;
    }).join('');

    return `<div class="oi-card">
        <div class="oi-card-hdr">🧱 OI Walls (Market Maker Positions)</div>
        <div class="oi-walls-grid">
            <div class="oi-wall-side">
                <div class="owlabel">Call Wall (CE sellers)</div>
                <div class="owstrike call">${fNum(w.call_wall||0)}</div>
                <div class="owoi">${fOi(w.call_wall_oi)} OI</div>
                <div class="ow-bar-wrap">${ceRows}</div>
            </div>
            <div class="oi-wall-side">
                <div class="owlabel">Put Wall (PE sellers)</div>
                <div class="owstrike put">${fNum(w.put_wall||0)}</div>
                <div class="owoi">${fOi(w.put_wall_oi)} OI</div>
                <div class="ow-bar-wrap">${peRows}</div>
            </div>
        </div>
    </div>`;
}

// ─── PRESSURE CHART ───────────────────────────────────────────────────────────
function renderPressureChart(tl) {
    if (!tl || !tl.length) return '';
    const SCORE_MAX = 17;
    const MAX_H = 70;
    const THRESH_PCT = Math.round((8 / SCORE_MAX) * MAX_H);

    const bars = tl.map(row => {
        const score   = row.max_score || 0;
        const fired   = row.is_best_entry || false;
        const sig     = row.trade_fired ? (row.dominant_side === 'CE' ? 'call' : 'put') : null;
        const h       = score > 0 ? Math.max(3, Math.round((score / SCORE_MAX) * MAX_H)) : 2;
        const inW     = row.in_window;
        const isFirst = row.is_first_hour;

        let barCls = 'p-low';
        if (fired && sig)    barCls = `p-${sig} p-fired`;
        else if (score >= 8) barCls = 'p-high';
        else if (score >= 5) barCls = 'p-watch';

        const firedDot = fired && sig ? `<div class="p-fired-dot ${sig}"></div>` : '';
        const opacity  = isFirst ? 'opacity:0.35;' : '';

        return `<div class="p-bar-col" title="${row.slot} — Score ${score}/17${fired?' ★ TRADE FIRED':''}">
            <div class="p-bar ${barCls}" style="height:${h}px;${opacity}" >${firedDot}</div>
            <div class="p-slot">${row.slot}</div>
        </div>`;
    }).join('');

    return `<div class="pressure-card">
        <div class="pressure-card-hdr">
            <span>📊 Pressure Timeline</span>
            <span style="font-size:8px;color:var(--dim3);">bars = score strength</span>
        </div>
        <div class="pressure-chart">
            <div class="p-chart-bars" style="position:relative;">
                <div class="p-threshold-line" style="bottom:${THRESH_PCT}px;" title="Trade threshold (8/17)"></div>
                ${bars}
            </div>
            <div class="p-legend">
                <div class="p-leg-item"><div class="p-leg-dot" style="background:rgba(255,255,255,0.12);"></div> Low pressure</div>
                <div class="p-leg-item"><div class="p-leg-dot" style="background:rgba(240,180,41,0.5);"></div> Building</div>
                <div class="p-leg-item"><div class="p-leg-dot" style="background:var(--call);"></div> BUY CALL fired</div>
                <div class="p-leg-item"><div class="p-leg-dot" style="background:var(--put);"></div> BUY PUT fired</div>
                <div class="p-leg-item">— = threshold (8/17)</div>
            </div>
        </div>
    </div>`;
}

// ─── SCORE BREAKDOWN TABLE ───────────────────────────────────────────────────
function renderScoreTable(bt, tl) {
    if (!bt || !tl) return '';

    // Find the row that fired the trade
    const firedRow = tl.find(r => r.is_best_entry);
    if (!firedRow) return '';
    const s    = firedRow.signals || {};
    const isCall = bt.signal === 'BUY_CALL';
    const cls    = isCall ? 'call' : 'put';

    const rows = [
        { name: 'Premium Expansion', w: 3, active: isCall ? s.cePremEx?.triggered : s.pePremEx?.triggered, val: isCall ? (s.cePremChg != null ? '+'+s.cePremChg+'%' : '—') : (s.pePremChg != null ? '+'+s.pePremChg+'%' : '—') },
        { name: 'OI Build-Up',       w: 2, active: isCall ? s.ceOiBuild?.triggered : s.peOiBuild?.triggered, val: isCall ? (s.ceOiChg != null ? '+'+s.ceOiChg+'%' : '—') : (s.peOiChg != null ? '+'+s.peOiChg+'%' : '—') },
        { name: 'Volume Spike',      w: 2, active: isCall ? s.ceVolSpike?.triggered : s.peVolSpike?.triggered, val: isCall ? (s.ceVolRatio != null ? s.ceVolRatio+'x' : '—') : (s.peVolRatio != null ? s.peVolRatio+'x' : '—') },
        { name: 'Futures Direction', w: 2, active: isCall ? s.futuresDir?.bullish : s.futuresDir?.bearish, val: s.futuresDir?.direction || '—' },
        { name: 'Gamma Squeeze',     w: 2, active: isCall ? s.gamma?.ce : s.gamma?.pe, val: 'ATM + ATM+1' },
        { name: 'Momentum Accel',    w: 2, active: isCall ? s.ceAccel?.triggered : s.peAccel?.triggered, val: '2 rising candles' },
        { name: '🪤 MM Trap',        w: 4, active: isCall ? s.mmTrap?.call_trap : s.mmTrap?.put_trap, val: 'Wall breakout' },
    ];

    const trs = rows.map(r => {
        const isTrap   = r.name.includes('MM') || r.name.includes('Trap');
        const activeCls = r.active ? (isTrap ? 'style="color:var(--trap);"' : `class="sc-active"`) : 'class="sc-inactive"';
        const wCls      = r.active ? (isTrap ? 'style="color:var(--trap);font-weight:800;"' : `class="sc-w ${cls}"`) : 'class="sc-w"';
        return `<tr>
            <td class="sc-name">${esc(r.name)}</td>
            <td><span ${activeCls}>${r.active ? '✓' : '—'}</span></td>
            <td style="color:var(--dim3);font-size:9px;">${esc(String(r.val))}</td>
            <td ${wCls}>${r.active ? '+'+r.w : '0'}</td>
        </tr>`;
    }).join('');

    return `<div class="score-table-card">
        <div class="score-table-hdr">📋 Score Breakdown at ${esc(bt.slot)}</div>
        <table class="score-table">
            <thead><tr>
                <th>Signal</th><th>Active</th><th>Value</th><th style="text-align:right;">Weight</th>
            </tr></thead>
            <tbody>${trs}</tbody>
        </table>
        <div style="padding:8px 12px 10px;font-size:9px;color:var(--dim3);border-top:1px solid var(--rim);display:flex;justify-content:space-between;">
            <span>Total score</span>
            <strong style="color:${isCall ? 'var(--call)':'var(--put)'};">${bt.score} / 17</strong>
        </div>
    </div>`;
}

// ─── FORMATTERS ───────────────────────────────────────────────────────────────
function fP(v)   { return v != null ? Number(v).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) : '—'; }
function fNum(v) { return v != null ? Number(v).toLocaleString('en-IN') : '—'; }
function fOi(v) {
    if (v == null || v === 0) return '—';
    const n = Number(v);
    if (n >= 10000000) return (n/10000000).toFixed(2) + 'Cr';
    if (n >= 100000)   return (n/100000).toFixed(2)   + 'L';
    if (n >= 1000)     return (n/1000).toFixed(1)     + 'K';
    return n.toLocaleString('en-IN');
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
@endpush