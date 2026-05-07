@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ════════════════════════════════════════════════════
   MF BACKTEST — Strategy Simulator
   Select a date → see every trade that would've happened
════════════════════════════════════════════════════ */
.bt { background:#090c13; min-height:100vh; font-family:'DM Mono',monospace; color:#e2e8f0; }
.bt * { box-sizing:border-box; }

/* Header */
.bt-hd {
    background:linear-gradient(130deg,#1a1040 0%,#0f4c75 50%,#0ea5e9 100%);
    border-radius:12px; padding:16px 22px; margin-bottom:14px;
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:10px;
    box-shadow:0 0 50px rgba(14,165,233,.18);
}
.bt-hd h4 { color:#fff; margin:0; font-size:17px; font-weight:800; }
.bt-hd p  { color:rgba(255,255,255,.6); margin:3px 0 0; font-size:9px; }

/* Fund summary cards */
.fp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:10px; margin-bottom:14px; }
.fp-card { background:#111520; border:1px solid rgba(14,165,233,.14); border-radius:10px; padding:12px 14px; position:relative; overflow:hidden; }
.fp-card::before { content:''; position:absolute; top:0; left:0; width:3px; height:100%; background:linear-gradient(180deg,#0ea5e9,#6366f1); }
.fpc-name { font-size:10px; font-weight:800; color:#38bdf8; margin-bottom:8px; display:flex; justify-content:space-between; }
.fpc-cat  { font-size:7.5px; color:#475569; background:#1e2235; padding:2px 6px; border-radius:3px; }
.fpr { display:flex; justify-content:space-between; margin-bottom:3px; }
.fpl { font-size:7.5px; color:#64748b; }
.fpv { font-size:8.5px; font-weight:700; }
.fpv.g { color:#4ade80; }
.fpv.r { color:#f87171; }
.fp-div { border:none; border-top:1px solid #1a1f35; margin:5px 0; }
.fp-big { font-size:13px !important; font-weight:800 !important; }
.fp-amt { display:flex; gap:5px; margin-top:8px; }
.fp-amt input  { flex:1; background:#090c13; border:1px solid rgba(14,165,233,.3); color:#e2e8f0; font-size:9px; border-radius:5px; padding:4px 7px; height:26px; }
.fp-amt button { background:#0ea5e9; border:none; color:#fff; font-size:9px; font-weight:700; border-radius:5px; padding:0 10px; cursor:pointer; }

/* Stat bar */
.st-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:8px; margin-bottom:12px; }
@media(max-width:768px){ .st-grid { grid-template-columns:repeat(3,1fr); } }
.st-c { background:#111520; border-radius:8px; padding:9px 12px; border-left:3px solid #334155; }
.st-c small  { display:block; color:#64748b; font-size:7.5px; text-transform:uppercase; letter-spacing:.5px; margin-bottom:2px; }
.st-c strong { display:block; font-size:1.1rem; font-weight:800; }

/* Filter bar */
.bt-fl { background:#111520; border:1px solid rgba(14,165,233,.15); border-radius:10px; padding:12px 16px; margin-bottom:12px; }
.bt-fl label { color:#38bdf8; font-size:10px; font-weight:600; display:block; margin-bottom:3px; }
.bt-fl .form-control { background:#090c13 !important; border:1px solid rgba(14,165,233,.25) !important; color:#e2e8f0 !important; font-size:10px; border-radius:6px; padding:5px 9px; height:30px; }
.bt-fl .form-control:focus { border-color:#0ea5e9 !important; outline:none; box-shadow:none !important; }
.btn-bt { display:inline-flex; align-items:center; gap:5px; padding:6px 16px; border-radius:7px; border:none; cursor:pointer; font-size:10px; font-weight:700; transition:all .15s; }
.btn-bt.pr { background:#0ea5e9; color:#fff; }
.btn-bt.sc { background:rgba(255,255,255,.08); color:#94a3b8; border:1px solid rgba(255,255,255,.15); }
.btn-bt:hover { opacity:.88; }

/* Signal filter pills */
.pills { display:flex; gap:5px; flex-wrap:wrap; margin-top:8px; align-items:center; }
.pill { padding:3px 10px; border-radius:20px; border:1.5px solid rgba(14,165,233,.2); background:transparent; color:#94a3b8; font-size:9px; font-weight:700; cursor:pointer; transition:all .15s; }
.pill.pa { background:#1e3a8a; border-color:#3b82f6; color:#93c5fd; }
.pill.pb { background:#052e16; border-color:#16a34a; color:#4ade80; }
.pill.ps { background:#450a0a; border-color:#dc2626; color:#f87171; }
.pill.ph { background:#1a1f35; border-color:#334155; color:#64748b; }
.pill.po { background:#1c1300; border-color:#ca8a04; color:#facc15; }

/* Table */
.bt-cd { background:#111520; border-radius:10px; overflow:hidden; margin-bottom:14px; border:1px solid rgba(14,165,233,.12); }
.bt-ch { display:flex; align-items:center; justify-content:space-between; padding:8px 14px; border-bottom:1px solid rgba(14,165,233,.1); background:#0d1020; }
.bt-ct { font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.6px; margin:0; }
.bt-tw { overflow-x:auto; -webkit-overflow-scrolling:touch; }

.bt-t { width:100%; border-collapse:collapse; font-size:8.5px; min-width:1100px; table-layout:fixed; }
.bt-t thead th {
    background:#0d1020; color:#64748b; font-size:7.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px;
    padding:6px 5px; border-bottom:2px solid #1a1f35; text-align:center;
    white-space:nowrap; position:sticky; top:0; z-index:5;
}
.th-a { background:rgba(14,165,233,.07)  !important; color:#38bdf8 !important; border-bottom-color:#0ea5e9 !important; }
.th-b { background:rgba(34,197,94,.05)   !important; color:#4ade80 !important; border-bottom-color:#16a34a !important; }
.th-c { background:rgba(251,191,36,.05)  !important; color:#fbbf24 !important; border-bottom-color:#d97706 !important; }
.th-d { background:rgba(167,139,250,.05) !important; color:#a78bfa !important; border-bottom-color:#7c3aed !important; }

.bt-t thead th.s1 { position:sticky; left:0;    z-index:15; background:#0d1020; }
.bt-t thead th.s2 { position:sticky; left:22px; z-index:15; background:#0d1020; }

.bt-t tbody td {
    padding:0; border-bottom:1px solid #131826;
    text-align:center; background:#111520;
    vertical-align:top; font-size:8.5px; color:#cbd5e1;
}
.bt-t tbody td.s1 { position:sticky; left:0;    z-index:5; background:#111520; }
.bt-t tbody td.s2 { position:sticky; left:22px; z-index:5; background:#111520; }
.bt-t tbody td .c { padding:8px 5px; }
.bt-t tbody tr:hover td { background:#131826 !important; }

/* Row tints */
.bt-t tr.ro td, .bt-t tr.ro td.s1, .bt-t tr.ro td.s2 { background:#06120d !important; }
.bt-t tr.rb td, .bt-t tr.rb td.s1, .bt-t tr.rb td.s2 { background:#091808 !important; }
.bt-t tr.rs td, .bt-t tr.rs td.s1, .bt-t tr.rs td.s2 { background:#180808 !important; }

/* Badges */
.mk { display:inline-block; padding:2px 6px; border-radius:3px; font-size:7.5px; font-weight:700; white-space:nowrap; line-height:1.4; }
.mk-buy  { background:#16a34a; color:#fff; }
.mk-sell { background:#dc2626; color:#fff; }
.mk-hold { background:#1e2235; color:#64748b; border:1px solid #2d3748; }
.mk-up   { background:#052e16; color:#4ade80; }
.mk-dn   { background:#450a0a; color:#fca5a5; }
.mk-gc   { background:#14532d; color:#bbf7d0; }
.mk-dc   { background:#500724; color:#fbcfe8; }
.mk-os   { background:#1e3a8a; color:#93c5fd; }
.mk-nt   { background:#052e16; color:#4ade80; }
.mk-hi2  { background:#431407; color:#fed7aa; }
.mk-ob   { background:#450a0a; color:#fca5a5; }
.mk-open { background:#0c2340; color:#60a5fa; border:1px solid rgba(96,165,250,.3); font-size:8px; }
.mk-cld  { background:#1c1300; color:#fbbf24; border:1px solid rgba(251,191,36,.3); }
.mk-nop  { background:#1e2235; color:#475569; }

/* P&L */
.g    { color:#4ade80; font-weight:700; }
.r    { color:#f87171; font-weight:700; }
.gz   { color:#475569; }
.gbig { color:#4ade80; font-size:11px; font-weight:800; }
.rbig { color:#f87171; font-size:11px; font-weight:800; }

/* Symbol */
.sym-n { color:#38bdf8; font-weight:800; font-size:9.5px; display:block; }
.sym-s { color:#475569; font-size:7.5px; display:block; margin-top:1px; }
.sym-t { color:#334155; font-size:7px; display:block; margin-top:2px; }

/* RSI bar */
.rsi-bg { width:48px; height:3px; background:#1e2235; border-radius:2px; display:inline-block; vertical-align:middle; margin-top:2px; }
.rsi-fg { height:3px; border-radius:2px; }

/* Position detail */
.pd { text-align:left !important; padding:6px 8px !important; }
.pd-fund { background:#0d1020; border-radius:5px; padding:7px 9px; margin-bottom:4px; }
.pd-fund:last-child { margin-bottom:0; }
.pd-fhd { display:flex; justify-content:space-between; align-items:center; margin-bottom:5px; }
.pd-fname { color:#fb923c; font-weight:700; font-size:8px; }
.pd-alloc { color:#64748b; font-size:7.5px; }
.pd-inv   { color:#60a5fa; font-weight:600; }

/* Open position block */
.pd-open { background:#061c10; border-radius:4px; padding:5px 7px; border-left:2px solid #16a34a; margin-bottom:4px; }
.pd-open .pd-row { display:flex; flex-wrap:wrap; gap:10px; margin-top:3px; }
.pd-item { display:flex; flex-direction:column; }
.pd-lbl  { color:#475569; font-size:6.5px; text-transform:uppercase; letter-spacing:.3px; }
.pd-val  { font-size:8px; font-weight:600; color:#e2e8f0; }
.pd-pnl  { font-size:9.5px; font-weight:800; }
.pd-hp   { font-size:7.5px; color:#fbbf24; }

/* Closed trades block */
.pd-closed { background:#110d04; border-radius:4px; padding:5px 7px; border-left:2px solid #d97706; margin-bottom:3px; }
.pd-closed .pd-row { display:flex; flex-wrap:wrap; gap:8px; margin-top:3px; }
.pd-closed-hd { display:flex; justify-content:space-between; margin-bottom:3px; }

/* Loader */
.bt-ld { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px; }
.bt-sp { width:36px; height:36px; border:3px solid #1e293b; border-top-color:#0ea5e9; border-radius:50%; animation:spin .75s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.bt-lt { color:#0ea5e9; font-size:10px; font-weight:700; margin-top:12px; }
.bt-ls { color:#334155; font-size:8.5px; margin-top:4px; }
</style>
@endpush

<div class="bt">
<section class="pt-20 pb-40">
<div class="container-fluid content-container">

{{-- HEADER --}}
<div class="bt-hd">
    <div>
        <h4>🔭 MF Strategy Backtest</h4>
        <p>Select a date → Simulate from 2024 → that date candle by candle → See exact P&L, open positions, closed trades</p>
    </div>
    <div style="color:rgba(255,255,255,.5);font-size:8.5px">
        Data: <strong style="color:#fff">{{ $earliestDate ?? '—' }}</strong>
        → <strong style="color:#fff">{{ $latestDate ?? '—' }}</strong>
    </div>
</div>

{{-- FUND CARDS --}}
<div class="fp-grid" id="fp-grid">
    @foreach($funds as $f)
    @php $inv = $fundInvestments[$f->id]->invested_amount ?? 1000000; @endphp
    <div class="fp-card">
        <div class="fpc-name">
            {{ $f->code }}
            <span class="fpc-cat">{{ $f->category }}</span>
        </div>
        <div class="fpr"><span class="fpl">Invested</span><span class="fpv" id="fi-{{ $f->id }}">₹{{ number_format($inv,0) }}</span></div>
        <div class="fpr"><span class="fpl">Booked Profit</span><span class="fpv" id="fb-{{ $f->id }}">—</span></div>
        <div class="fpr"><span class="fpl">Running P&L</span><span class="fpv" id="fr-{{ $f->id }}">—</span></div>
        <div class="fpr"><span class="fpl">Total P&L</span><span class="fpv fp-big" id="ft-{{ $f->id }}">—</span></div>
        <hr class="fp-div">
        <div class="fpr"><span class="fpl">Win Rate</span><span class="fpv" id="fw-{{ $f->id }}">—</span></div>
        <div class="fpr"><span class="fpl">Total Trades</span><span class="fpv" id="ftr-{{ $f->id }}">—</span></div>
        <div class="fp-amt">
            <input type="number" id="fa-{{ $f->id }}" value="{{ $inv }}" placeholder="Invest amount">
            <button onclick="saveAmt({{ $f->id }})">Save</button>
        </div>
    </div>
    @endforeach
</div>

{{-- STAT BAR --}}
<div class="st-grid">
    <div class="st-c" style="border-left-color:#6366f1"><small>Symbols</small><strong style="color:#a5b4fc" id="st-sym">—</strong></div>
    <div class="st-c" style="border-left-color:#60a5fa"><small>Open Positions</small><strong style="color:#60a5fa" id="st-op">—</strong></div>
    <div class="st-c" style="border-left-color:#64748b"><small>Closed Trades</small><strong style="color:#94a3b8" id="st-cl">—</strong></div>
    <div class="st-c" style="border-left-color:#4ade80"><small>Running P&L</small><strong id="st-rp">—</strong></div>
    <div class="st-c" style="border-left-color:#fbbf24"><small>Booked Profit</small><strong id="st-bp">—</strong></div>
    <div class="st-c" style="border-left-color:#a78bfa"><small>Total P&L</small><strong id="st-tp">—</strong></div>
</div>

{{-- FILTERS --}}
<div class="bt-fl">
    <div class="row align-items-end" style="row-gap:8px">
        <div class="col-6 col-md-2">
            <label>📅 Simulate As Of Date</label>
            <input type="date" id="f-date" class="form-control"
                value="{{ $latestDate ?? now()->format('Y-m-d') }}"
                min="{{ $earliestDate ?? '2024-01-01' }}"
                max="{{ $latestDate ?? now()->format('Y-m-d') }}">
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
            <label>Show</label>
            <select id="f-show" class="form-control">
                <option value="">All Stocks</option>
                <option value="open">Open Positions Only</option>
                <option value="closed">Has Closed Trades</option>
                <option value="BUY">BUY Signal</option>
                <option value="SELL">SELL Signal</option>
            </select>
        </div>
        <div class="col-12 col-md-5 d-flex align-items-end" style="gap:6px">
            <button class="btn-bt pr flex-fill" onclick="runSim()">
                <i class="fas fa-play"></i> Run Simulation
            </button>
            <button class="btn-bt sc" onclick="resetAll()"><i class="fas fa-undo"></i></button>
        </div>
    </div>
    <div class="pills">
        <span style="color:#64748b;font-size:9px;font-weight:600">FILTER:</span>
        <button class="pill pa" onclick="setPill('',this)"      id="pA">ALL</button>
        <button class="pill"    onclick="setPill('open',this)"  id="pO">● OPEN</button>
        <button class="pill"    onclick="setPill('BUY',this)"   id="pB">▲ BUY</button>
        <button class="pill"    onclick="setPill('SELL',this)"  id="pS">▼ SELL</button>
        <button class="pill"    onclick="setPill('HOLD',this)"  id="pH">— HOLD</button>
    </div>
</div>

{{-- TABLE --}}
<div class="bt-cd">
    <div class="bt-ch">
        <span class="bt-ct" id="bt-title">Select date and run simulation</span>
        <small style="color:#475569;font-size:8.5px" id="cnt">—</small>
    </div>
    <div id="ld" style="display:none" class="bt-ld">
        <div class="bt-sp"></div>
        <div class="bt-lt">Simulating candle by candle...</div>
        <div class="bt-ls" id="ld-msg">Loading all candles from 2024 → selected date</div>
    </div>
    <div class="bt-tw">
    <table class="bt-t">
        <colgroup>
            <col style="width:22px">
            <col style="width:130px">  {{-- Stock --}}
            <col style="width:85px">   {{-- NAV --}}
            <col style="width:72px">   {{-- EMA --}}
            <col style="width:72px">   {{-- RSI --}}
            <col style="width:60px">   {{-- Signal --}}
            <col style="width:82px">   {{-- Invested --}}
            <col style="width:88px">   {{-- Running P&L --}}
            <col style="width:88px">   {{-- Booked --}}
            <col style="width:88px">   {{-- Total P&L --}}
            <col style="width:55px">   {{-- Alloc % --}}
            <col style="width:160px">  {{-- Open Position (compact) --}}
            <col style="width:70px">   {{-- Detail btn --}}
        </colgroup>
        <thead>
            <tr>
                <th class="s1">#</th>
                <th class="s2 th-a">Stock</th>
                <th class="th-a">NAV<br><span style="font-size:6.5px">As of Date</span></th>
                <th class="th-b">EMA<br>Signal</th>
                <th class="th-b">RSI(14)</th>
                <th class="th-b">Signal<br>on Date</th>
                <th class="th-c">Invested<br>Amount</th>
                <th class="th-c">Running<br>P&amp;L</th>
                <th class="th-c">Booked<br>Profit</th>
                <th class="th-c">Total<br>P&amp;L</th>
                <th class="th-c">Total<br>Alloc%</th>
                <th class="th-d">Open Position<br><span style="font-size:6.5px">Buy Price · Date · P&L</span></th>
                <th class="th-d">Trades</th>
            </tr>
        </thead>
        <tbody id="tb">
            <tr><td colspan="13" style="padding:70px;text-align:center;color:#334155">
                <i class="fas fa-telescope" style="font-size:2.5rem;opacity:.1;display:block;margin-bottom:14px;color:#0ea5e9"></i>
                <span style="color:#475569;font-size:9px">Select a date and click <strong style="color:#38bdf8">Run Simulation</strong></span><br>
                <span style="color:#334155;font-size:8px;margin-top:4px;display:block">Simulates your EMA+RSI strategy from 2024 → selected date, showing every trade</span>
            </td></tr>
        </tbody>
    </table>
    </div>
</div>

</div></section></div>

{{-- ── TRADE HISTORY DRAWER ──────────────────────────────────── --}}
<div id="drw-ov" onclick="closeDrawer()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:9990"></div>
<div id="drw" style="position:fixed;top:0;right:-530px;width:520px;max-width:96vw;height:100vh;background:#0d1020;border-left:1px solid rgba(14,165,233,.25);z-index:9991;transition:right .28s ease;display:flex;flex-direction:column;font-family:'DM Mono',monospace">
    <div style="padding:14px 18px;border-bottom:1px solid #1a1f35;background:#090c13;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
        <div>
            <div id="drw-sym" style="color:#38bdf8;font-size:15px;font-weight:800">—</div>
            <div id="drw-sub" style="color:#475569;font-size:8.5px;margin-top:2px">Trade history — all funds</div>
        </div>
        <button onclick="closeDrawer()" style="background:transparent;border:none;color:#64748b;font-size:22px;cursor:pointer;line-height:1;padding:0 6px">✕</button>
    </div>
    <div id="drw-body" style="overflow-y:auto;flex:1;padding:14px 18px"></div>
</div>

@endsection

@push('script')
<script>
const SIM_API  = '{{ route("mf-backtest.simulate") }}';
const SAVE_API = '{{ route("mf-backtest.save-amount") }}';
const CSRF     = '{{ csrf_token() }}';

let allRows  = [];
let pillFilt = '';

/* ── Formatters ─────────────────────────────────────── */
const inr = v => '₹' + parseFloat(v||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
const pnlV = v => {
    const n = parseFloat(v||0), s = n>0?'+':'';
    const c = n>0?'g':n<0?'r':'gz';
    return `<span class="${c}">${s}${inr(n)}</span>`;
};
const pnlBig = v => {
    const n = parseFloat(v||0), s = n>0?'+':'';
    const c = n>0?'gbig':'rbig';
    return `<span class="${c}">${s}${inr(n)}</span>`;
};
const pctV = v => {
    const n = parseFloat(v||0), s = n>0?'+':'';
    const c = n>0?'g':n<0?'r':'gz';
    return `<span class="${c}">${s}${n.toFixed(2)}%</span>`;
};

/* ── EMA badge ─────────────────────────────────────── */
function emaBadge(lbl) {
    if (!lbl || lbl==='—') return '<span class="mk mk-hold">—</span>';
    if (lbl.includes('Golden')) return '<span class="mk mk-gc">✦ GldX</span>';
    if (lbl.includes('Death'))  return '<span class="mk mk-dc">✦ DthX</span>';
    if (lbl.includes('UP'))     return '<span class="mk mk-up">↑ UP</span>';
    return '<span class="mk mk-dn">↓ DOWN</span>';
}

/* ── RSI badge ─────────────────────────────────────── */
function rsiBadge(lbl, val) {
    const v = parseFloat(val||50);
    let cls='mk-nt', short='NTL';
    if (lbl&&lbl.includes('OS'))   { cls='mk-os';  short='OS'; }
    else if (lbl&&lbl.includes('OB')) { cls='mk-ob'; short='OB'; }
    else if (lbl&&lbl.includes('HI')) { cls='mk-hi2'; short='HI'; }
    const bc = v<40?'#3b82f6':v<=60?'#4ade80':v<=70?'#fbbf24':'#ef4444';
    const vc = v<40?'#93c5fd':v<=60?'#4ade80':v<=70?'#fbbf24':'#f87171';
    return `<span style="font-size:10px;font-weight:800;color:${vc}">${v.toFixed(1)}</span>
<span class="mk ${cls}">${short}</span>
<div><span class="rsi-bg"><span class="rsi-fg" style="width:${Math.min(v,100)}%;display:block;background:${bc}"></span></span></div>`;
}

/* ── Signal badge ──────────────────────────────────── */
function sigBadge(s) {
    if (s==='BUY')  return '<span class="mk mk-buy" style="font-size:9px">▲ BUY</span>';
    if (s==='SELL') return '<span class="mk mk-sell" style="font-size:9px">▼ SELL</span>';
    return '<span class="mk mk-hold">— HOLD</span>';
}

/* ── Compact open-position summary (inline in table) ── */
function openPosSummary(fundResults, nav) {
    if (!fundResults || !fundResults.length) return '<div class="c" style="color:#334155;font-size:8px">—</div>';
    const opens = fundResults.filter(fr => fr.open_position);
    if (!opens.length) {
        const withClosed = fundResults.filter(fr => fr.closed_trades && fr.closed_trades.length);
        if (!withClosed.length) return '<div class="c"><span class="mk mk-nop">No trades</span></div>';
        const last = withClosed[0].closed_trades[withClosed[0].closed_trades.length - 1];
        return `<div class="c" style="text-align:left;padding-left:6px">
<span class="mk mk-cld" style="font-size:7.5px">Last sold</span><br>
<span style="color:#fbbf24;font-size:8.5px;font-weight:700">${inr(last.sell_price)}</span>
<span style="color:#475569;font-size:7px;display:block">${last.sell_time}</span>
</div>`;
    }
    const fr  = opens[0];
    const op  = fr.open_position;
    const pClr  = parseFloat(op.running_pnl||0) >= 0 ? '#4ade80' : '#f87171';
    const pPct  = (parseFloat(op.running_pnl_pct||0)>0?'+':'') + parseFloat(op.running_pnl_pct||0).toFixed(2) + '%';
    const extra = opens.length > 1 ? `<span style="color:#60a5fa;font-size:7px"> +${opens.length-1} fund</span>` : '';
    return `<div class="c" style="text-align:left;padding-left:6px">
<span class="mk mk-open" style="font-size:7.5px">● OPEN · ${fr.fund_code} · ${op.lot_count||1} lot${(op.lot_count||1)>1?'s':''}${extra}</span><br>
<span style="color:#e2e8f0;font-size:8.5px;font-weight:700">Avg: ${inr(op.avg_buy_price||op.buy_price)}</span>
<span style="color:#64748b;font-size:7px;display:block">${op.first_buy_time||op.buy_time}</span>
<span style="color:${pClr};font-size:9px;font-weight:800">${inr(op.running_pnl)} (${pPct})</span>
</div>`;
}

/* ── Render table — compact rows ───────────────────── */
function render(data, asOfDate) {
    const disp = asOfDate || '—';
    if (!data || !data.length) {
        document.getElementById('tb').innerHTML = `<tr><td colspan="13" style="padding:50px;text-align:center;color:#475569">No results match filters</td></tr>`;
        return;
    }
    let h = '';
    data.forEach((d, i) => {
        const rowCls    = d.signal==='BUY' ? 'rb' : d.signal==='SELL' ? 'rs' : d.has_open ? 'ro' : '';
        const openCount = (d.fund_results||[]).filter(fr => fr.open_position).length;
        const buys      = (d.fund_results||[]).reduce((s,fr) => s + (fr.buy_count||0), 0);
        const sells     = (d.fund_results||[]).reduce((s,fr) => s + (fr.sell_count||0), 0);
        const winRate   = d.win_rate || 0;
        const pnlPct    = parseFloat(d.total_pnl_pct||0);
        const pnlPctStr = (pnlPct>0?'+':'') + pnlPct.toFixed(2) + '%';
        const pnlPctCls = pnlPct >= 0 ? 'g' : 'r';
        const idx = i;

        h += `<tr class="${rowCls}">
<td class="s1"><div class="c" style="color:#334155">${i+1}</div></td>
<td class="s2"><div class="c" style="text-align:left;padding-left:8px">
    <span class="sym-n">${d.symbol}</span>
    <span class="sym-s">${d.name}</span>
    <span class="sym-t">${d.sector||'—'} · WR: ${winRate}%</span>
</div></td>
<td><div class="c">
    <span style="font-size:10px;font-weight:800;color:#e2e8f0">${inr(d.nav)}</span><br>
    <span style="color:#334155;font-size:7px">${d.nav_time||'—'}</span>
</div></td>
<td><div class="c">${emaBadge(d.ema_label)}</div></td>
<td><div class="c">${rsiBadge(d.rsi_label, d.rsi_value)}</div></td>
<td><div class="c">${sigBadge(d.signal)}</div></td>
<td><div class="c" style="color:#60a5fa">${inr(d.total_invested)}</div></td>
<td><div class="c">${pnlBig(d.total_running_pnl)}</div></td>
<td><div class="c">${pnlBig(d.total_booked)}</div></td>
<td><div class="c">${pnlBig(d.total_pnl)}<br><span class="${pnlPctCls}" style="font-size:8px">${pnlPctStr}</span></div></td>
<td><div class="c" style="color:#60a5fa">${d.total_alloc_pct}%</div></td>
<td>${openPosSummary(d.fund_results, d.nav)}</td>
<td><div class="c">
    <button onclick="openDrawer(${idx})" style="background:#1e3a5f;border:1px solid rgba(14,165,233,.3);color:#38bdf8;font-size:8px;font-weight:700;border-radius:5px;padding:4px 9px;cursor:pointer;width:100%;margin-bottom:3px">
        📋 Detail
    </button>
    <span style="color:#4ade80;font-size:7px">${buys}B</span>
    <span style="color:#334155;font-size:7px"> · </span>
    <span style="color:#f87171;font-size:7px">${sells}S</span>
</div></td>
</tr>`;
    });
    document.getElementById('tb').innerHTML = h;
    document.getElementById('cnt').textContent = data.length + ' stock' + (data.length!==1?'s':'');
}

/* ── DRAWER — slide-in panel with full trade history ── */
let _renderCache = [];

function openDrawer(idx) {
    const d = _renderCache[idx];
    if (!d) return;
    document.getElementById('drw-sym').textContent = d.symbol + ' — ' + d.name;
    document.getElementById('drw-sub').textContent = 'Trade history as of ' + (d.nav_time||'—') + '  ·  NAV: ' + inr(d.nav);

    let h = '';
    (d.fund_results || []).forEach(fr => {
        h += `<div style="margin-bottom:16px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #1a1f35">
    <span style="color:#fb923c;font-size:12px;font-weight:800">${fr.fund_code}</span>
    <span style="color:#64748b;font-size:8px">${fr.allocation_pct}% · Invested: <span style="color:#60a5fa;font-weight:700">${inr(fr.stock_invested)}</span></span>
</div>`;

        // OPEN POSITION (multi-lot accumulation)
        if (fr.open_position) {
            const op   = fr.open_position;
            const pClr = parseFloat(op.running_pnl||0) >= 0 ? '#4ade80' : '#f87171';
            const pPct = (parseFloat(op.running_pnl_pct||0)>0?'+':'') + parseFloat(op.running_pnl_pct||0).toFixed(2) + '%';
            h += `<div style="background:#06180e;border-radius:7px;padding:12px;border-left:3px solid #16a34a;margin-bottom:10px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <span style="background:#052e16;color:#4ade80;font-size:8px;font-weight:700;padding:3px 8px;border-radius:3px">● OPEN · ${op.lot_count} lot${op.lot_count>1?'s':''}</span>
    <span style="color:#64748b;font-size:7.5px">First: ${op.first_buy_time} · Last: ${op.last_buy_time}</span>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:7px;font-size:8.5px;margin-bottom:8px">
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Avg Buy Price</div><div style="color:#4ade80;font-weight:800;font-size:10px">${inr(op.avg_buy_price)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Total Qty</div><div style="color:#e2e8f0">${parseFloat(op.total_qty||0).toFixed(2)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Total Cost</div><div style="color:#60a5fa;font-weight:700">${inr(op.total_cost)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">NAV Now</div><div style="color:#e2e8f0">${inr(op.nav)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Current Value</div><div style="color:#e2e8f0">${inr(op.current_value)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Cash Available</div><div style="color:#94a3b8">${inr(fr.available_cash)}</div></div>
</div>
<div style="border-top:1px solid #0a2e14;padding-top:8px;margin-bottom:8px">
    <div style="color:#475569;font-size:7px;text-transform:uppercase;margin-bottom:2px">Running P&L</div>
    <div style="color:${pClr};font-size:16px;font-weight:800">${inr(op.running_pnl)}&nbsp;<span style="font-size:11px">(${pPct})</span></div>
</div>
${op.lots && op.lots.length > 1 ? `<div style="margin-top:6px">
<div style="color:#475569;font-size:7px;font-weight:700;text-transform:uppercase;margin-bottom:4px">Individual Lots (${op.lots.length})</div>
<div style="max-height:150px;overflow-y:auto">
${op.lots.map((l,li) => `<div style="display:flex;justify-content:space-between;padding:3px 6px;background:#0a220f;border-radius:3px;margin-bottom:2px;font-size:7.5px">
    <span style="color:#64748b">#${li+1} ${l.type}</span>
    <span style="color:#4ade80;font-weight:700">${inr(l.buy_price)}</span>
    <span style="color:#94a3b8">${l.buy_time}</span>
    <span style="color:#60a5fa">${parseFloat(l.qty).toFixed(2)} × ${inr(l.cost)}</span>
    <span style="color:#475569">RSI ${l.rsi}</span>
</div>`).join('')}
</div>
</div>` : ''}
</div>`;
        }

        // CLOSED TRADES
        if (fr.closed_trades && fr.closed_trades.length) {
            h += `<div style="color:#64748b;font-size:8px;font-weight:700;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px">${fr.closed_trades.length} Sell Trade${fr.closed_trades.length>1?'s':''} (FIFO partial exits)</div>`;
            fr.closed_trades.slice().reverse().forEach((ct, idx2) => {
                const bpPos = parseFloat(ct.booked_pnl||0) >= 0;
                const bpClr = bpPos ? '#4ade80' : '#f87171';
                const bpBg  = bpPos ? '#061a0a' : '#1c0606';
                const bpBdr = bpPos ? '#16a34a' : '#dc2626';
                const bpPct = (parseFloat(ct.booked_pnl_pct||0)>0?'+':'') + parseFloat(ct.booked_pnl_pct||0).toFixed(2) + '%';
                const trNum = fr.closed_trades.length - idx2;
                h += `<div style="background:${bpBg};border-radius:6px;padding:10px 12px;border-left:3px solid ${bpBdr};margin-bottom:6px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
    <span style="color:#fbbf24;font-size:8.5px;font-weight:700">✓ Sell #${trNum} · <span style="color:#94a3b8;font-size:7.5px">${ct.type||'SELL'}</span></span>
    <span style="color:${bpClr};font-size:12px;font-weight:800">${inr(ct.booked_pnl)}&nbsp;<span style="font-size:9px">(${bpPct})</span></span>
</div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;font-size:8px">
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Sold @ (High)</div><div style="color:#f87171;font-weight:700">${inr(ct.sell_price)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Sell Date</div><div style="color:#94a3b8">${ct.sell_time}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Qty Sold</div><div style="color:#e2e8f0">${parseFloat(ct.sell_qty||0).toFixed(2)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Cost Basis</div><div style="color:#60a5fa">${inr(ct.cost_basis)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">Sale Value</div><div style="color:#e2e8f0">${inr(ct.sale_value)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:1px">RSI at Sell</div><div style="color:#fbbf24">${ct.rsi_at_sell||'—'}</div></div>
</div></div>`;
            });
        }

        if (!fr.open_position && (!fr.closed_trades || !fr.closed_trades.length)) {
            h += `<div style="color:#334155;font-size:8px;padding:6px 0">No trades in this simulation period</div>`;
        }

        h += '</div><hr style="border:none;border-top:1px solid #1a1f35;margin-bottom:14px">';
    });

    document.getElementById('drw-body').innerHTML = h;
    document.getElementById('drw-ov').style.display = 'block';
    document.getElementById('drw').style.right = '0';
}

function closeDrawer() {
    document.getElementById('drw').style.right  = '-530px';
    document.getElementById('drw-ov').style.display = 'none';
}

/* ── Apply local filters ───────────────────────────── */
function applyFilters() {
    const show = document.getElementById('f-show').value || pillFilt;
    let d = [...allRows];
    if (show === 'open')        d = d.filter(r => r.has_open);
    else if (show === 'closed') d = d.filter(r => r.total_trades > 0 && !r.has_open);
    else if (show === 'BUY')    d = d.filter(r => r.signal === 'BUY');
    else if (show === 'SELL')   d = d.filter(r => r.signal === 'SELL');
    else if (show === 'HOLD')   d = d.filter(r => r.signal === 'HOLD');
    _renderCache = d; // store filtered data so openDrawer(idx) can access it
    render(d, document.getElementById('f-date').value);
}

/* ── Run simulation ────────────────────────────────── */
function runSim() {
    const date = document.getElementById('f-date').value;
    const fund = document.getElementById('f-fund').value;
    if (!date) { alert('Select a date'); return; }

    document.getElementById('ld').style.display = 'flex';
    document.getElementById('tb').innerHTML = '';
    document.getElementById('cnt').textContent = 'Simulating...';
    document.getElementById('bt-title').textContent = `Simulation as of ${date} — running...`;

    let url = `${SIM_API}?as_of_date=${date}`;
    if (fund) url += `&fund_id=${fund}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            document.getElementById('ld').style.display = 'none';
            if (!res.success) {
                document.getElementById('tb').innerHTML = `<tr><td colspan="12" style="padding:40px;text-align:center;color:#f87171">${res.message}</td></tr>`;
                return;
            }
            allRows = res.data || [];
            document.getElementById('bt-title').textContent = `Strategy state as of ${res.as_of_date} — ${allRows.length} stocks simulated`;
            applyFilters();
            updateStats(res.totals);
            updateFundCards(res.fund_summary);
        })
        .catch(e => {
            document.getElementById('ld').style.display = 'none';
            document.getElementById('tb').innerHTML = `<tr><td colspan="12" style="padding:40px;text-align:center;color:#f87171">Error: ${e.message}</td></tr>`;
        });
}

/* ── Stats ─────────────────────────────────────────── */
function updateStats(t) {
    if (!t) return;
    document.getElementById('st-sym').textContent = t.symbols||0;
    document.getElementById('st-op').textContent  = t.open_count||0;
    document.getElementById('st-cl').textContent  = t.closed_count||0;
    const rp = parseFloat(t.running_pnl||0), bp = parseFloat(t.booked_pnl||0), tp = parseFloat(t.total_pnl||0);
    const tPct = parseFloat(t.total_pnl_pct||0);
    document.getElementById('st-rp').innerHTML = `<span class="${rp>=0?'g':'r'}">${rp>=0?'+':''}${inr(rp)}</span>`;
    document.getElementById('st-bp').innerHTML = `<span class="${bp>=0?'g':'r'}">${bp>=0?'+':''}${inr(bp)}</span>`;
    document.getElementById('st-tp').innerHTML = `<span class="${tp>=0?'g':'r'}">${tp>=0?'+':''}${inr(tp)}<br><span style="font-size:9px">(${tPct>0?'+':''}${tPct.toFixed(2)}%)</span></span>`;
}

/* ── Fund cards ─────────────────────────────────────── */
function updateFundCards(summary) {
    if (!summary) return;
    summary.forEach(f => {
        const set = (id, v) => { const el=document.getElementById(id); if(el) el.innerHTML=v; };
        const tCls = parseFloat(f.total_pnl||0) >= 0 ? 'g' : 'r';
        const pct  = (parseFloat(f.total_pnl_pct||0)>0?'+':'') + parseFloat(f.total_pnl_pct||0).toFixed(2) + '%';
        set(`fb-${f.fund_id}`, `<span class="${parseFloat(f.booked_profit||0)>=0?'g':'r'}">${inr(f.booked_profit)}</span>`);
        set(`fr-${f.fund_id}`, `<span class="${parseFloat(f.running_pnl||0)>=0?'g':'r'}">${inr(f.running_pnl)}</span>`);
        set(`ft-${f.fund_id}`, `<span class="${tCls} fp-big">${inr(f.total_pnl)} (${pct})</span>`);
        set(`fw-${f.fund_id}`, `${f.win_rate}%`);
        set(`ftr-${f.fund_id}`, `<span style="color:#4ade80">${f.buy_count||0}B</span> · <span style="color:#f87171">${f.sell_count||0}S</span>`);
    });
}

/* ── Save fund amount ───────────────────────────────── */
function saveAmt(fid) {
    const amt = document.getElementById(`fa-${fid}`).value;
    if (!amt || parseFloat(amt)<=0) { alert('Enter valid amount'); return; }
    fetch(SAVE_API, {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({fund_id:fid, amount:parseFloat(amt)})
    }).then(r=>r.json()).then(res => {
        if (res.success) alert('✅ Saved! Re-run simulation to apply.');
        else alert('Error: '+res.message);
    });
}

/* ── Pills ──────────────────────────────────────────── */
function setPill(v, btn) {
    pillFilt = v;
    document.querySelectorAll('.pill').forEach(b => b.className='pill');
    const cls = v===''?'pa':v==='open'?'po':v==='BUY'?'pb':v==='SELL'?'ps':'ph';
    btn.classList.add(cls);
    if (allRows.length) applyFilters();
}

function resetAll() {
    document.getElementById('f-date').value = '{{ $latestDate ?? now()->format("Y-m-d") }}';
    document.getElementById('f-fund').value = '';
    document.getElementById('f-show').value = '';
    pillFilt = '';
    document.querySelectorAll('.pill').forEach(b => b.className='pill');
    document.getElementById('pA').classList.add('pa');
    allRows = [];
    document.getElementById('tb').innerHTML = `<tr><td colspan="12" style="padding:50px;text-align:center;color:#334155">Click Run Simulation</td></tr>`;
    document.getElementById('cnt').textContent = '—';
    document.getElementById('bt-title').textContent = 'Select date and run simulation';
    ['st-sym','st-op','st-cl','st-rp','st-bp','st-tp'].forEach(id => document.getElementById(id).textContent='—');
}

document.getElementById('f-show').addEventListener('change', applyFilters);
</script>
@endpush