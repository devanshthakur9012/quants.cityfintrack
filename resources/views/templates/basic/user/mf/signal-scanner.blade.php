@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════
   MF SIGNAL SCANNER  ·  EMA + RSI + Positions + P&L
═══════════════════════════════════════════════════ */
.mfs { background:#090c13; min-height:100vh; font-family:'DM Mono',monospace; color:#e2e8f0; }
.mfs * { box-sizing:border-box; }

/* Header */
.mfs-hd { background:linear-gradient(130deg,#0f7a55 0%,#0ea5e9 55%,#6366f1 100%); border-radius:12px; padding:16px 22px; margin-bottom:12px; box-shadow:0 0 50px rgba(14,165,233,.2); display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.mfs-hd h4 { color:#fff; margin:0; font-size:17px; font-weight:800; }
.mfs-hd p  { color:rgba(255,255,255,.7); margin:4px 0 0; font-size:9px; }

/* Fund Performance Cards */
.fp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:10px; margin-bottom:14px; }
.fp-card { background:#111520; border:1px solid rgba(14,165,233,.15); border-radius:10px; padding:12px 14px; }
.fp-card .fc-name { font-size:9.5px; font-weight:800; color:#38bdf8; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; }
.fp-card .fc-name span { font-size:8px; font-weight:600; color:#64748b; }
.fp-row { display:flex; justify-content:space-between; margin-bottom:4px; }
.fp-lbl { font-size:8px; color:#64748b; }
.fp-val { font-size:8.5px; font-weight:700; color:#e2e8f0; }
.fp-pnl-pos { color:#4ade80 !important; }
.fp-pnl-neg { color:#f87171 !important; }
.fp-divider { border:none; border-top:1px solid #1a1f35; margin:6px 0; }
.fp-pnl-big { font-size:13px !important; font-weight:800 !important; }
.fp-input-row { display:flex; gap:5px; margin-top:8px; }
.fp-input-row input { flex:1; background:#0a0d14; border:1px solid rgba(14,165,233,.3); color:#e2e8f0; font-size:9px; border-radius:5px; padding:4px 7px; height:26px; }
.fp-input-row button { background:#0ea5e9; border:none; color:#fff; font-size:9px; font-weight:700; border-radius:5px; padding:0 9px; cursor:pointer; white-space:nowrap; }

/* Stat bar */
.mfs-st { background:#111520; border-radius:9px; padding:10px 14px; border-left:3px solid #0ea5e9; margin-bottom:10px; }
.mfs-st small  { display:block; color:#64748b; font-size:8px; text-transform:uppercase; letter-spacing:.5px; margin-bottom:2px; }
.mfs-st strong { display:block; font-size:1.25rem; font-weight:800; }

/* Filters */
.mfs-fl { background:#111520; border:1px solid rgba(14,165,233,.18); border-radius:10px; padding:12px 16px; margin-bottom:12px; }
.mfs-fl label { color:#38bdf8; font-size:10px; font-weight:600; display:block; margin-bottom:3px; }
.mfs-fl .form-control { background:#090c13 !important; border:1px solid rgba(14,165,233,.28) !important; color:#e2e8f0 !important; font-size:10px; border-radius:6px; padding:5px 9px; height:30px; }
.mfs-fl .form-control:focus { border-color:#0ea5e9 !important; outline:none; box-shadow:none !important; }
.mb { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; border-radius:7px; border:none; cursor:pointer; font-size:10px; font-weight:700; transition:all .15s; }
.mb.pr { background:#0ea5e9; color:#fff; }
.mb.sc { background:rgba(255,255,255,.1); color:#fff; border:1px solid rgba(255,255,255,.25); }
.mb:hover { opacity:.85; }
.sig-filter { display:flex; gap:5px; flex-wrap:wrap; margin-top:8px; align-items:center; }
.sf-btn { padding:4px 10px; border-radius:5px; border:1.5px solid rgba(14,165,233,.2); background:transparent; color:#94a3b8; font-size:9px; font-weight:700; cursor:pointer; transition:all .15s; }
.sf-btn.ab { background:#1e3a8a; border-color:#3b82f6; color:#93c5fd; }
.sf-btn.ag { background:#052e16; border-color:#16a34a; color:#4ade80; }
.sf-btn.ar { background:#450a0a; border-color:#dc2626; color:#f87171; }
.sf-btn.aw { background:#1a1200; border-color:#ca8a04; color:#facc15; }

/* Toggle auto-trade */
.at-toggle { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:6px; background:#1a1f35; border:1px solid rgba(99,102,241,.3); color:#a5b4fc; font-size:9px; font-weight:700; cursor:pointer; margin-left:6px; }
.at-toggle.on { background:#1e3a5f; border-color:#0ea5e9; color:#38bdf8; }

/* Table card */
.mfs-cd { background:#111520; border-radius:10px; overflow:hidden; margin-bottom:12px; border:1px solid rgba(14,165,233,.15); }
.mfs-ch { display:flex; align-items:center; justify-content:space-between; padding:8px 14px; border-bottom:1px solid rgba(14,165,233,.12); background:#0d1020; }
.mfs-ct { font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.6px; margin:0; }
.mfs-tw { overflow-x:auto; -webkit-overflow-scrolling:touch; }

/* Table */
.mfs-t { width:100%; border-collapse:collapse; font-size:8.5px; min-width:1500px; table-layout:fixed; }
.mfs-t thead th {
    background:#0d1020; color:#64748b; font-size:7.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:.3px; padding:5px 3px;
    border-bottom:2px solid #1a1f35; text-align:center;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    position:sticky; top:0; z-index:5;
}
.th-info { background:rgba(14,165,233,.08)  !important; color:#38bdf8 !important; border-bottom-color:#0ea5e9 !important; }
.th-sig  { background:rgba(34,197,94,.06)   !important; color:#4ade80 !important; border-bottom-color:#16a34a !important; }
.th-pos  { background:rgba(251,191,36,.06)  !important; color:#fbbf24 !important; border-bottom-color:#d97706 !important; }
.th-pnl  { background:rgba(167,139,250,.06) !important; color:#c084fc !important; border-bottom-color:#9333ea !important; }
.th-fund { background:rgba(249,115,22,.06)  !important; color:#fb923c !important; border-bottom-color:#ea580c !important; }

/* Sticky left */
.mfs-t thead th.sl1 { position:sticky; left:0;    z-index:15; background:#0d1020; }
.mfs-t thead th.sl2 { position:sticky; left:22px; z-index:15; background:#0d1020; }
.mfs-t thead th.sl3 { position:sticky; left:92px; z-index:15; background:#0d1020; }

.mfs-t tbody td {
    padding:5px 3px; border-bottom:1px solid #161b2e;
    text-align:center; background:#111520;
    vertical-align:middle; white-space:nowrap;
    overflow:hidden; text-overflow:ellipsis; font-size:8.5px; color:#cbd5e1;
}
.mfs-t tbody td.sl1 { position:sticky; left:0;    z-index:5; background:#111520; }
.mfs-t tbody td.sl2 { position:sticky; left:22px; z-index:5; background:#111520; }
.mfs-t tbody td.sl3 { position:sticky; left:92px; z-index:5; background:#111520; }
.mfs-t tbody tr:hover td { background:#141824 !important; }
.mfs-t tbody tr.rb td,
.mfs-t tbody tr.rb td.sl1,
.mfs-t tbody tr.rb td.sl2,
.mfs-t tbody tr.rb td.sl3 { background:#041a0c !important; }
.mfs-t tbody tr.rs td,
.mfs-t tbody tr.rs td.sl1,
.mfs-t tbody tr.rs td.sl2,
.mfs-t tbody tr.rs td.sl3 { background:#1a0505 !important; }

/* Badges */
.mk { display:inline-block; padding:2px 5px; border-radius:3px; font-size:7.5px; font-weight:700; white-space:nowrap; line-height:1.4; }
.mk-buy  { background:#16a34a; color:#fff; }
.mk-sell { background:#dc2626; color:#fff; }
.mk-wait { background:#292400; color:#facc15; }
.mk-str  { background:#052e16; color:#bbf7d0; }
.mk-med  { background:#78350f; color:#fef3c7; }
.mk-wk   { background:#1e293b; color:#94a3b8; }
.mk-neu  { background:#0f172a; color:#475569; }
.mk-os   { background:#172554; color:#93c5fd; }
.mk-nt   { background:#052e16; color:#4ade80; }
.mk-hi2  { background:#431407; color:#fed7aa; }
.mk-ob   { background:#450a0a; color:#fca5a5; }
.mk-up   { background:#052e16; color:#4ade80; }
.mk-dn   { background:#450a0a; color:#fca5a5; }
.mk-gc   { background:#14532d; color:#bbf7d0; }
.mk-dc   { background:#500724; color:#fbcfe8; }
.mk-open { background:#0c2340; color:#60a5fa; border:1px solid rgba(59,130,246,.3); }
.mk-cld  { background:#1c1300; color:#fbbf24; border:1px solid rgba(251,191,36,.3); }
.mk-nop  { background:#1a1f35; color:#475569; }

/* P&L colours */
.pp { color:#4ade80; font-weight:700; }
.pn { color:#f87171; font-weight:700; }
.pz { color:#475569; }

/* Fund allocation chips */
.fund-wrap { display:flex; flex-wrap:wrap; gap:2px; justify-content:center; }
.fund-chip { display:inline-flex; align-items:center; gap:2px; padding:2px 5px; border-radius:3px; font-size:7px; font-weight:700; background:#1e2235; border:1px solid rgba(14,165,233,.2); color:#94a3b8; white-space:nowrap; }
.fund-chip .fc { color:#38bdf8; } .fund-chip .fa { color:#4ade80; }

/* Loader */
.mfs-ld { display:flex; align-items:center; justify-content:center; flex-direction:column; padding:50px; }
.mfs-sp { width:30px; height:30px; border:3px solid #1e293b; border-top-color:#0ea5e9; border-radius:50%; animation:spin .75s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* Inline text utils */
.sym-lbl { color:#38bdf8; font-weight:800; font-size:9px; letter-spacing:.3px; display:block; }
.sec-lbl { color:#475569; font-size:7px; display:block; margin-top:1px; }
.price-v { font-size:9.5px; font-weight:700; color:#e2e8f0; }
.ema-hint { font-size:7px; color:#475569; display:block; margin-top:1px; }
.rsi-bar-wrap { width:100%; height:3px; background:#1e2235; border-radius:2px; margin-top:2px; }
.rsi-bar { height:3px; border-radius:2px; }
.sc-p { color:#4ade80; font-weight:800; font-size:10px; }
.sc-n { color:#f87171; font-weight:800; font-size:10px; }
.sc-z { color:#475569; }

/* Position detail pop-in */
.pos-cell { white-space:normal !important; text-align:left !important; padding:4px 6px !important; }
.pos-entry { background:#0d1020; border-radius:4px; padding:4px 6px; margin-bottom:3px; font-size:7.5px; }
.pos-entry:last-child { margin-bottom:0; }
.pos-entry .pe-fund { color:#38bdf8; font-weight:700; font-size:8px; margin-bottom:3px; }
.pos-entry .pe-row  { display:flex; justify-content:space-between; gap:8px; }
.pos-entry .pe-lbl  { color:#475569; }
.pos-entry .pe-val  { font-weight:600; color:#cbd5e1; }
.pos-entry .pe-pnl-pos { color:#4ade80; font-weight:800; }
.pos-entry .pe-pnl-neg { color:#f87171; font-weight:800; }
.pe-close-btn { background:#dc2626; border:none; color:#fff; font-size:7px; font-weight:700; border-radius:3px; padding:2px 5px; cursor:pointer; margin-top:3px; }
</style>
@endpush

<div class="mfs">
<section class="pt-20 pb-40">
<div class="container-fluid content-container">

{{-- HEADER --}}
<div class="mfs-hd">
    <div>
        <h4>📈 MF Signal Scanner — EMA + RSI + Positions</h4>
        <p>1-Hr OHLC · EMA(20/50) Trend · RSI(14) · Position Tracking · Running P&L · Fund Performance</p>
    </div>
    <span style="color:rgba(255,255,255,.5);font-size:8.5px">Latest: <strong style="color:#fff" id="hd-date">{{ $latestDate ?? '—' }}</strong></span>
</div>

{{-- FUND PERFORMANCE CARDS --}}
<div id="fp-grid" class="fp-grid" style="display:none">
    {{-- Filled by JS --}}
</div>

{{-- STAT BAR --}}
<div class="row mb-2">
    <div class="col-6 col-md-2"><div class="mfs-st" style="border-left-color:#6366f1"><small>Scanned</small><strong style="color:#6366f1" id="st-s">—</strong></div></div>
    <div class="col-6 col-md-2"><div class="mfs-st" style="border-left-color:#4ade80"><small>BUY</small><strong style="color:#4ade80" id="st-b">—</strong></div></div>
    <div class="col-6 col-md-2"><div class="mfs-st" style="border-left-color:#f87171"><small>SELL</small><strong style="color:#f87171" id="st-e">—</strong></div></div>
    <div class="col-6 col-md-2"><div class="mfs-st" style="border-left-color:#facc15"><small>WAIT</small><strong style="color:#facc15" id="st-w">—</strong></div></div>
    <div class="col-6 col-md-2"><div class="mfs-st" style="border-left-color:#60a5fa"><small>Open Positions</small><strong style="color:#60a5fa" id="st-op">—</strong></div></div>
    <div class="col-6 col-md-2"><div class="mfs-st" style="border-left-color:#a78bfa"><small>Running P&L</small><strong id="st-pnl">—</strong></div></div>
</div>

{{-- FILTERS --}}
<div class="mfs-fl">
    <div class="row align-items-end" style="row-gap:8px">
        <div class="col-6 col-md-2">
            <label>Scan Date</label>
            <input type="date" id="f-date" class="form-control" value="{{ $latestDate ?? now()->format('Y-m-d') }}">
        </div>
        <div class="col-6 col-md-3">
            <label>Mutual Fund <span style="color:#475569;font-weight:400">(blank = all)</span></label>
            <select id="f-fund" class="form-control">
                <option value="">— All Funds —</option>
                @foreach($funds as $f)
                    <option value="{{ $f->id }}">{{ $f->code }} — {{ $f->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label>Signal</label>
            <select id="f-sig" class="form-control">
                <option value="">All Signals</option>
                <option value="BUY">BUY Only</option>
                <option value="SELL">SELL Only</option>
                <option value="WAIT">WAIT Only</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label>Strength</label>
            <select id="f-str" class="form-control">
                <option value="">All</option>
                <option value="STRONG">STRONG</option>
                <option value="MEDIUM">MEDIUM</option>
                <option value="WEAK">WEAK</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex align-items-end" style="gap:5px">
            <button class="mb pr flex-fill" onclick="run()"><i class="fas fa-search"></i> Scan</button>
            <button class="mb sc at-toggle" id="atBtn" onclick="toggleAT()" title="Auto-open/close positions on signal">⚡ Auto-Trade: OFF</button>
            <button class="mb sc" onclick="resetAll()"><i class="fas fa-undo"></i></button>
        </div>
    </div>
    <div class="sig-filter">
        <span style="color:#64748b;font-size:9px;font-weight:600">SHOW:</span>
        <button class="sf-btn ab" onclick="setQ('',this)"      id="sfA">ALL</button>
        <button class="sf-btn"    onclick="setQ('BUY',this)"   id="sfB">▲ BUY</button>
        <button class="sf-btn"    onclick="setQ('SELL',this)"  id="sfS">▼ SELL</button>
        <button class="sf-btn"    onclick="setQ('WAIT',this)"  id="sfW">⏸ WAIT</button>
    </div>
</div>

{{-- TABLE --}}
<div class="mfs-cd">
    <div class="mfs-ch">
        <span class="mfs-ct">EMA(20/50) + RSI(14) · Entry/Exit · Running P&L · Fund Allocation</span>
        <small style="color:#475569;font-size:8.5px" id="cnt">—</small>
    </div>
    <div id="ld" style="display:none" class="mfs-ld">
        <div class="mfs-sp"></div>
        <div style="color:#0ea5e9;font-size:10px;font-weight:700;margin-top:10px">Computing indicators + positions...</div>
    </div>
    <div class="mfs-tw">
    <table class="mfs-t">
        <colgroup>
            <col style="width:22px">  {{-- # --}}
            <col style="width:75px">  {{-- Symbol --}}
            <col style="width:115px"> {{-- Name --}}
            <col style="width:80px">  {{-- NAV/Close --}}
            <col style="width:72px">  {{-- EMA Signal --}}
            <col style="width:72px">  {{-- RSI --}}
            <col style="width:50px">  {{-- Score --}}
            <col style="width:62px">  {{-- Strength --}}
            <col style="width:120px"> {{-- Reasons --}}
            <col style="width:80px">  {{-- Signal --}}
            <col style="width:85px">  {{-- Total Invested --}}
            <col style="width:85px">  {{-- Current Value --}}
            <col style="width:85px">  {{-- Running P&L --}}
            <col style="width:70px">  {{-- Booked P --}}
            <col style="width:55px">  {{-- Alloc % --}}
            <col style="width:260px"> {{-- Position Detail (Buy/Sell time, per fund) --}}
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2" class="sl1">#</th>
                <th rowspan="2" class="sl2 th-info">Symbol</th>
                <th rowspan="2" class="sl3 th-info">Name / Sector</th>
                {{-- Signal group --}}
                <th class="th-sig" colspan="6">Signal · EMA + RSI</th>
                {{-- Money group --}}
                <th class="th-pnl" colspan="4">P&L Tracker</th>
                {{-- Fund / Position --}}
                <th class="th-fund" colspan="2">Allocation</th>
                <th class="th-pos">Entry · Exit · Position Detail</th>
            </tr>
            <tr>
                <th class="th-sig">NAV<br>Price</th>
                <th class="th-sig">EMA<br>Signal</th>
                <th class="th-sig">RSI<br>14</th>
                <th class="th-sig">Score</th>
                <th class="th-sig">Strength</th>
                <th class="th-sig">Reasons</th>
                <th class="th-pnl">Invested<br>Amt</th>
                <th class="th-pnl">Current<br>Value</th>
                <th class="th-pnl">Running<br>P&L</th>
                <th class="th-pnl">Booked<br>Profit</th>
                <th class="th-fund">Funds &<br>Alloc %</th>
                <th class="th-fund">Total<br>Alloc %</th>
                <th class="th-pos">Buy Price · Time → Sell Price · Time · Qty · P&L per Fund</th>
            </tr>
        </thead>
        <tbody id="tb">
            <tr><td colspan="16" style="padding:50px;text-align:center;color:#475569">
                <i class="fas fa-chart-line" style="font-size:2rem;opacity:.15;display:block;margin-bottom:10px;color:#0ea5e9"></i>
                Select a date and click <strong style="color:#38bdf8">Scan</strong>
            </td></tr>
        </tbody>
    </table>
    </div>
</div>

</div></section></div>
@endsection

@push('script')
<script>
const API        = '{{ route("mf-signals.scan") }}';
const CLOSE_API  = '{{ route("mf-signals.close-position") }}';
const SAVE_API   = '{{ route("mf-signals.save-amount") }}';
const CSRF       = '{{ csrf_token() }}';

let all       = [];
let quickFilt = '';
let autoTrade = false;

/* ── Number helpers ────────────────────────────────────────────── */
const inr = v => '₹' + parseFloat(v||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
const pct  = (v, suffix='%') => {
    const n = parseFloat(v||0), s = n>0?'+':'', c = n>0?'pp':n<0?'pn':'pz';
    return `<span class="${c}">${s}${n.toFixed(2)}${suffix}</span>`;
};
const pnlV = v => {
    const n = parseFloat(v||0), s = n>0?'+':'', c = n>0?'pp':n<0?'pn':'pz';
    return `<span class="${c}">${s}${inr(n)}</span>`;
};
const score = v => {
    const n = parseFloat(v||0), s = n>0?'+':'';
    const c = n >= 3.5 ? 'sc-p' : n <= -3 ? 'sc-n' : 'sc-z';
    return `<span class="${c}">${s}${n.toFixed(1)}</span>`;
};

/* ── Badges ─────────────────────────────────────────────────────── */
function sigBadge(s) {
    if (s==='BUY')  return '<span class="mk mk-buy">▲ BUY</span>';
    if (s==='SELL') return '<span class="mk mk-sell">▼ SELL</span>';
    return '<span class="mk mk-wait">⏸ WAIT</span>';
}
function strBadge(s) {
    const m = {'STRONG':'<span class="mk mk-str">🔥</span>','MEDIUM':'<span class="mk mk-med">⚡MED</span>','WEAK':'<span class="mk mk-wk">WK</span>','NEUTRAL':'<span class="mk mk-neu">—</span>'};
    return m[s] || s;
}
function emaBadge(uptrend, gc, dc, gap) {
    if (gc) return `<span class="mk mk-gc">✦ GoldX</span><span class="ema-hint">${pct(gap)}</span>`;
    if (dc) return `<span class="mk mk-dc">✦ DeathX</span><span class="ema-hint">${pct(gap)}</span>`;
    const b = uptrend ? '<span class="mk mk-up">↑ UP</span>' : '<span class="mk mk-dn">↓ DOWN</span>';
    return `${b}<span class="ema-hint">${pct(gap)}</span>`;
}
function rsiBadge(zone, rsi) {
    const m = {'OVERSOLD':'<span class="mk mk-os">OS</span>','NEUTRAL':'<span class="mk mk-nt">NTL</span>','HIGH':'<span class="mk mk-hi2">HI</span>','OVERBOUGHT':'<span class="mk mk-ob">OB</span>'};
    const bc = rsi<40?'#3b82f6':rsi<=60?'#4ade80':rsi<=70?'#fbbf24':'#ef4444';
    const col = rsi<40?'#93c5fd':rsi<=60?'#4ade80':rsi<=70?'#fbbf24':'#f87171';
    return `<span style="font-size:10px;font-weight:800;color:${col}">${parseFloat(rsi).toFixed(1)}</span>
        ${m[zone]||zone}
        <div class="rsi-bar-wrap"><div class="rsi-bar" style="width:${Math.min(rsi,100)}%;background:${bc}"></div></div>`;
}
function reasonsHtml(r) {
    if (!r) return '—';
    return r.split(' + ').map(x => `<span style="display:inline-block;padding:1px 3px;margin:1px;background:#1e293b;border-radius:2px;font-size:7px;color:#94a3b8">${x}</span>`).join('');
}
function fundChips(positions) {
    if (!positions||!positions.length) return '—';
    return '<div class="fund-wrap">' + positions.map(p =>
        `<span class="fund-chip" title="${p.fund_name}"><span class="fc">${p.fund_code}</span>|<span class="fa">${p.allocation_pct}%</span></span>`
    ).join('') + '</div>';
}

/* ── Position detail cell ───────────────────────────────────────── */
function posCell(positions) {
    if (!positions || !positions.length) return '<span style="color:#334155;font-size:8px">—</span>';
    let h = '';
    positions.forEach(p => {
        const statusBadge = p.position_status==='OPEN' ? '<span class="mk mk-open">● OPEN</span>'
            : p.position_status==='CLOSED' ? '<span class="mk mk-cld">✓ CLOSED</span>'
            : '<span class="mk mk-nop">NO POS</span>';

        h += `<div class="pos-entry">
<div class="pe-fund">${p.fund_code} <span style="color:#64748b;font-weight:400">|</span> ${statusBadge}
<span style="color:#64748b;font-size:7px;margin-left:4px">${p.allocation_pct}% · ${inr(p.stock_invested)}</span></div>`;

        if (p.position_status === 'OPEN') {
            const pnlCls = parseFloat(p.running_pnl||0) >= 0 ? 'pe-pnl-pos' : 'pe-pnl-neg';
            h += `<div class="pe-row">
<span class="pe-lbl">Buy</span><span class="pe-val">${inr(p.buy_price)} · ${p.buy_time||'—'}</span>
</div><div class="pe-row">
<span class="pe-lbl">NAV</span><span class="pe-val">${inr(p.nav)}</span>
<span class="pe-lbl">Qty</span><span class="pe-val">${parseFloat(p.quantity||0).toFixed(2)}</span>
</div><div class="pe-row">
<span class="pe-lbl">Running P&L</span>
<span class="${pnlCls}">${inr(p.running_pnl)} (${parseFloat(p.running_pnl_pct||0)>0?'+':''}${parseFloat(p.running_pnl_pct||0).toFixed(2)}%)</span>
</div>
<button class="pe-close-btn" onclick="manualClose(${p.position_id},${p.nav})">✕ Close @ Current</button>`;
        } else if (p.position_status === 'CLOSED') {
            const pnlCls = parseFloat(p.booked_profit||0) >= 0 ? 'pe-pnl-pos' : 'pe-pnl-neg';
            h += `<div class="pe-row">
<span class="pe-lbl">Buy</span><span class="pe-val">${inr(p.buy_price)} · ${p.buy_time||'—'}</span>
</div><div class="pe-row">
<span class="pe-lbl">Sell</span><span class="pe-val">${inr(p.sell_price)} · ${p.sell_time||'—'}</span>
</div><div class="pe-row">
<span class="pe-lbl">Qty</span><span class="pe-val">${parseFloat(p.quantity||0).toFixed(2)}</span>
<span class="pe-lbl">Booked</span><span class="${pnlCls}">${inr(p.booked_profit)}</span>
</div>`;
        } else {
            h += `<div class="pe-row"><span style="color:#334155;font-size:7.5px">No position yet</span></div>`;
        }
        h += '</div>';
    });
    return h;
}

/* ── Main render ─────────────────────────────────────────────────── */
function render(data) {
    if (!data || !data.length) { empty('No results'); return; }
    let h = '';
    data.forEach((d, i) => {
        const rowCls = d.signal==='BUY'?'rb':d.signal==='SELL'?'rs':'';
        const pnlCls = parseFloat(d.total_running_pnl||0) >= 0 ? 'pp' : 'pn';
        const bkCls  = parseFloat(d.total_booked||0) >= 0 ? 'pp' : 'pn';
        const curDiff = parseFloat(d.total_current_val||0) - parseFloat(d.total_invested||0);

        h += `<tr class="${rowCls}">
<td class="sl1" style="color:#334155">${i+1}</td>
<td class="sl2"><span class="sym-lbl">${d.symbol}</span></td>
<td class="sl3" style="text-align:left;padding-left:6px"><span class="sym-lbl">${d.name}</span><span class="sec-lbl">${d.sector||'—'}</span></td>
<td><span class="price-v">${inr(d.nav)}</span><span class="ema-hint">${pct(d.price_vs_ema20)} EMA20</span></td>
<td>${emaBadge(d.uptrend, d.golden_cross, d.death_cross, d.ema_gap_pct)}</td>
<td>${rsiBadge(d.rsi_zone, d.rsi)}</td>
<td>${score(d.score)}</td>
<td>${strBadge(d.strength)}</td>
<td style="white-space:normal;text-align:left;padding:3px 4px">${reasonsHtml(d.reasons)}</td>
<td>${sigBadge(d.signal)}</td>
<td><span style="font-size:8.5px;color:#cbd5e1">${inr(d.total_invested)}</span></td>
<td><span style="font-size:8.5px;color:${curDiff>=0?'#4ade80':'#f87171'}">${inr(d.total_current_val)}</span></td>
<td><span class="${pnlCls}">${inr(d.total_running_pnl)}</span></td>
<td><span class="${bkCls}">${inr(d.total_booked)}</span></td>
<td style="font-size:7.5px;color:#60a5fa">${d.total_alloc_pct}%<br><span style="color:#334155">${d.candle_time}</span></td>
<td class="pos-cell">${posCell(d.positions)}</td>
</tr>`;
    });
    document.getElementById('tb').innerHTML = h;
}

/* ── Fund performance cards ─────────────────────────────────────── */
function renderFundCards(summary) {
    if (!summary || !summary.length) return;
    const grid = document.getElementById('fp-grid');
    grid.style.display = 'grid';
    grid.innerHTML = summary.map(f => {
        const pnlCls = parseFloat(f.total_pnl||0) >= 0 ? 'fp-pnl-pos' : 'fp-pnl-neg';
        const pctStr = (parseFloat(f.total_pnl_pct||0) > 0 ? '+' : '') + parseFloat(f.total_pnl_pct||0).toFixed(2) + '%';
        return `<div class="fp-card">
<div class="fc-name">${f.fund_code} — ${f.category||'—'} <span>${f.stock_count} stocks · ${f.open_positions} open</span></div>
<div class="fp-row"><span class="fp-lbl">Invested</span><span class="fp-val">${inr(f.fund_invested)}</span></div>
<div class="fp-row"><span class="fp-lbl">Current Value</span><span class="fp-val ${pnlCls}">${inr(f.current_value)}</span></div>
<hr class="fp-divider">
<div class="fp-row"><span class="fp-lbl">Running P&L</span><span class="fp-val ${parseFloat(f.running_pnl)>=0?'fp-pnl-pos':'fp-pnl-neg'}">${inr(f.running_pnl)}</span></div>
<div class="fp-row"><span class="fp-lbl">Booked Profit</span><span class="fp-val ${parseFloat(f.booked_profit)>=0?'fp-pnl-pos':'fp-pnl-neg'}">${inr(f.booked_profit)}</span></div>
<div class="fp-row"><span class="fp-lbl">Total P&L</span><span class="fp-val fp-pnl-big ${pnlCls}">${inr(f.total_pnl)} (${pctStr})</span></div>
<hr class="fp-divider">
<div class="fp-row"><span class="fp-lbl">Trades</span><span class="fp-val">${f.total_trades} · Win Rate: ${f.win_rate}%</span></div>
<div class="fp-row"><span class="fp-lbl">Idle Cash</span><span class="fp-val">${inr(f.idle_amount)}</span></div>
<div class="fp-input-row">
    <input type="number" id="amt_${f.fund_id}" placeholder="Change invest amt" value="${f.fund_invested}">
    <button onclick="saveAmt(${f.fund_id})">Save</button>
</div>
</div>`;
    }).join('');
}

/* ── Run scan ───────────────────────────────────────────────────── */
function run() {
    const date = document.getElementById('f-date').value;
    const fund = document.getElementById('f-fund').value;
    const sig  = document.getElementById('f-sig').value || quickFilt;

    let url = `${API}?date=${date}&auto_trade=${autoTrade?1:0}`;
    if (fund) url += `&fund_id=${fund}`;
    if (sig)  url += `&signal=${sig}`;

    document.getElementById('ld').style.display = 'flex';
    document.getElementById('tb').innerHTML = '';
    document.getElementById('cnt').textContent = 'Computing…';

    fetch(url)
        .then(r => r.json())
        .then(res => {
            document.getElementById('ld').style.display = 'none';
            document.getElementById('hd-date').textContent = res.date || date;
            if (!res.success) { empty(res.message || 'No data'); return; }
            all = res.data || [];
            applyFilters();
            updateStats(res.summary);
            if (res.fund_summary) renderFundCards(res.fund_summary);
        })
        .catch(e => {
            document.getElementById('ld').style.display = 'none';
            empty('Error: ' + e.message);
        });
}

function applyFilters() {
    const str = document.getElementById('f-str').value;
    let d = [...all];
    if (quickFilt) d = d.filter(r => r.signal === quickFilt);
    if (str)       d = d.filter(r => r.strength === str);
    render(d);
    document.getElementById('cnt').textContent = d.length + ' stock' + (d.length !== 1 ? 's' : '');
}

function updateStats(s) {
    if (!s) return;
    document.getElementById('st-s').textContent  = s.symbols || '—';
    document.getElementById('st-b').textContent  = s.buy  || 0;
    document.getElementById('st-e').textContent  = s.sell || 0;
    document.getElementById('st-w').textContent  = s.wait || 0;
    document.getElementById('st-op').textContent = s.open_positions || 0;
    // Running P&L from fund summary — calculated from all positions
    const totalPnl = (window._fundSummaryPnl || 0);
    const el = document.getElementById('st-pnl');
    el.textContent = totalPnl >= 0 ? `+₹${totalPnl.toLocaleString('en-IN')}` : `-₹${Math.abs(totalPnl).toLocaleString('en-IN')}`;
    el.className   = totalPnl >= 0 ? 'fp-pnl-pos' : 'fp-pnl-neg';
}

/* ── Manual close position ─────────────────────────────────────── */
function manualClose(posId, sellPrice) {
    if (!confirm(`Close position at ₹${sellPrice}?`)) return;
    fetch(CLOSE_API, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': CSRF},
        body: JSON.stringify({position_id: posId, sell_price: sellPrice, reason: 'Manual close from scanner'})
    }).then(r => r.json()).then(res => {
        if (res.success) {
            alert(`✅ Closed! P&L: ₹${res.booked_profit} (${parseFloat(res.pct||0).toFixed(2)}%)`);
            run(); // refresh
        } else { alert('Error: ' + res.message); }
    });
}

/* ── Save fund investment amount ────────────────────────────────── */
function saveAmt(fundId) {
    const amt = document.getElementById('amt_' + fundId).value;
    fetch(SAVE_API, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': CSRF},
        body: JSON.stringify({fund_id: fundId, amount: parseFloat(amt)})
    }).then(r => r.json()).then(res => {
        if (res.success) { alert('✅ Saved! Re-run scan to reflect new amount.'); }
        else { alert('Error: ' + res.message); }
    });
}

/* ── Auto-trade toggle ──────────────────────────────────────────── */
function toggleAT() {
    autoTrade = !autoTrade;
    const btn = document.getElementById('atBtn');
    btn.className   = 'mb sc at-toggle' + (autoTrade ? ' on' : '');
    btn.textContent = `⚡ Auto-Trade: ${autoTrade ? 'ON' : 'OFF'}`;
}

function setQ(v, btn) {
    quickFilt = v;
    document.querySelectorAll('.sf-btn').forEach(b => b.className = 'sf-btn');
    btn.classList.add(v===''?'ab':v==='BUY'?'ag':v==='SELL'?'ar':'aw');
    if (all.length) applyFilters();
}

function empty(m) {
    document.getElementById('tb').innerHTML =
        `<tr><td colspan="16" style="padding:50px;text-align:center;color:#475569">${m}</td></tr>`;
}

function resetAll() {
    document.getElementById('f-date').value = '{{ $latestDate ?? now()->format("Y-m-d") }}';
    document.getElementById('f-fund').value = '';
    document.getElementById('f-sig').value  = '';
    document.getElementById('f-str').value  = '';
    quickFilt = ''; autoTrade = false;
    document.querySelectorAll('.sf-btn').forEach(b => b.className = 'sf-btn');
    document.getElementById('sfA').classList.add('ab');
    document.getElementById('atBtn').className = 'mb sc at-toggle';
    document.getElementById('atBtn').textContent = '⚡ Auto-Trade: OFF';
    all = [];
    empty('Click Scan to compute signals');
    document.getElementById('fp-grid').style.display = 'none';
    ['st-s','st-b','st-e','st-w','st-op','st-pnl'].forEach(id => document.getElementById(id).textContent = '—');
    document.getElementById('cnt').textContent = '—';
}

document.getElementById('f-str').addEventListener('change', applyFilters);
document.getElementById('f-sig').addEventListener('change', () => {
    quickFilt = document.getElementById('f-sig').value;
    document.querySelectorAll('.sf-btn').forEach(b => b.className = 'sf-btn');
    document.getElementById('sfA').classList.add('ab');
    if (all.length) applyFilters();
});
</script>
@endpush