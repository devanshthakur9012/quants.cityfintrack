@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ══════════════════════════════════════════════════════
   MULTI-ASSET MF  —  Trade Log
   BUY 1 FUT + SELL 2 OTM CE  ·  Entry→Exit P&L
══════════════════════════════════════════════════════ */
.ma { background:#090c13; min-height:100vh; font-family:'DM Mono',monospace; color:#e2e8f0; }
.ma * { box-sizing:border-box; }

/* Header */
.ma-hd { background:linear-gradient(130deg,#18084a 0%,#5b21b6 55%,#0ea5e9 100%); border-radius:12px; padding:14px 22px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; box-shadow:0 0 50px rgba(91,33,182,.2); }
.ma-hd h4 { color:#fff; margin:0; font-size:16px; font-weight:800; }
.ma-hd p  { color:rgba(255,255,255,.6); margin:2px 0 0; font-size:9px; }

/* Stat bar */
.st-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:8px; margin-bottom:12px; }
@media(max-width:768px){ .st-grid { grid-template-columns:repeat(3,1fr); } }
.st-c { background:#111520; border-radius:8px; padding:9px 12px; border-left:3px solid #334155; }
.st-c small  { display:block; color:#64748b; font-size:7.5px; text-transform:uppercase; letter-spacing:.5px; margin-bottom:2px; }
.st-c strong { display:block; font-size:1rem; font-weight:800; }

/* Filter bar */
.ma-fl { background:#111520; border:1px solid rgba(91,33,182,.2); border-radius:10px; padding:12px 16px; margin-bottom:12px; }
.ma-fl label { color:#a78bfa; font-size:10px; font-weight:600; display:block; margin-bottom:3px; }
.ma-fl .form-control { background:#090c13 !important; border:1px solid rgba(91,33,182,.3) !important; color:#e2e8f0 !important; font-size:10px; border-radius:6px; padding:5px 9px; height:30px; }
.ma-fl .form-control:focus { border-color:#7c3aed !important; outline:none; box-shadow:none !important; }
.btn-ma { display:inline-flex; align-items:center; gap:5px; padding:6px 18px; border-radius:7px; border:none; cursor:pointer; font-size:10px; font-weight:700; transition:all .15s; }
.btn-ma.pr { background:#7c3aed; color:#fff; }
.btn-ma.sc { background:rgba(255,255,255,.07); color:#94a3b8; border:1px solid rgba(255,255,255,.12); }
.btn-ma:hover { opacity:.88; }

/* Table wrapper */
.ma-cd { background:#111520; border-radius:10px; overflow:hidden; border:1px solid rgba(91,33,182,.14); margin-bottom:14px; }
.ma-ch { display:flex; align-items:center; justify-content:space-between; padding:8px 14px; border-bottom:1px solid rgba(91,33,182,.12); background:#0d1020; }
.ma-ct { font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.6px; margin:0; }
.ma-tw { overflow-x:auto; -webkit-overflow-scrolling:touch; }

/* Table */
.ma-t { width:100%; border-collapse:collapse; font-size:8.5px; min-width:900px; }
.ma-t thead th {
    background:#0d1020; color:#64748b; font-size:7.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px;
    padding:7px 8px; border-bottom:2px solid #1a1f35;
    text-align:center; white-space:nowrap; position:sticky; top:0; z-index:5;
}
.th-s  { background:rgba(91,33,182,.07)  !important; color:#a78bfa !important; border-bottom-color:#7c3aed !important; } /* stock */
.th-e  { background:rgba(14,165,233,.06) !important; color:#38bdf8 !important; border-bottom-color:#0ea5e9 !important; } /* entry */
.th-x  { background:rgba(251,191,36,.05) !important; color:#fbbf24 !important; border-bottom-color:#d97706 !important; } /* exit */
.th-p  { background:rgba(34,197,94,.05)  !important; color:#4ade80 !important; border-bottom-color:#16a34a !important; } /* pnl */

.ma-t tbody td {
    padding:0; border-bottom:1px solid #131826;
    text-align:center; background:#111520;
    vertical-align:middle; font-size:8.5px; color:#cbd5e1;
}
.ma-t tbody td .c { padding:8px 7px; }
.ma-t tbody tr:hover td { background:#141824 !important; }

/* Row tints */
.ma-t tr.rp td { background:#040f08 !important; }
.ma-t tr.rn td { background:#110404 !important; }

/* Badges */
.mk { display:inline-block; padding:2px 7px; border-radius:3px; font-size:7.5px; font-weight:700; white-space:nowrap; }
.mk-buy  { background:#16a34a; color:#fff; }
.mk-sell { background:#dc2626; color:#fff; }
.mk-open { background:#0c2340; color:#60a5fa; border:1px solid rgba(96,165,250,.35); }
.mk-cld  { background:#1c1300; color:#fbbf24; border:1px solid rgba(251,191,36,.3); }
.mk-win  { background:#052e16; color:#4ade80; }
.mk-loss { background:#450a0a; color:#f87171; }

/* P&L */
.g    { color:#4ade80; font-weight:700; }
.r    { color:#f87171; font-weight:700; }
.gz   { color:#475569; }
.gbig { color:#4ade80; font-size:11px; font-weight:800; }
.rbig { color:#f87171; font-size:11px; font-weight:800; }

/* Symbol */
.sym-n { color:#a78bfa; font-weight:800; font-size:9.5px; display:block; }
.sym-s { color:#475569; font-size:7.5px; display:block; margin-top:1px; }
.sym-f { color:#334155; font-size:7px; display:block; }

/* Detail btn */
.btn-detail { background:#1e1a40; border:1px solid rgba(124,58,237,.35); color:#a78bfa; font-size:7.5px; font-weight:700; border-radius:5px; padding:3px 9px; cursor:pointer; }
.btn-detail:hover { background:#2d2560; }

/* Loader */
.ma-ld { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:50px; }
.ma-sp { width:28px; height:28px; border:3px solid #1e293b; border-top-color:#7c3aed; border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* ── DRAWER ─────────────────────────────────────── */
#drw { position:fixed; top:0; right:-530px; width:520px; max-width:96vw; height:100vh; background:#0d1020; border-left:1px solid rgba(91,33,182,.3); z-index:9991; transition:right .27s ease; display:flex; flex-direction:column; font-family:'DM Mono',monospace; }
#drw-ov { display:none; position:fixed; inset:0; background:rgba(0,0,0,.72); z-index:9990; }
.drw-hd { padding:13px 18px; border-bottom:1px solid #1a1f35; background:#090c13; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.drw-bd { overflow-y:auto; flex:1; padding:14px 18px; }

/* Candle table inside drawer */
.dc-t { width:100%; border-collapse:collapse; font-size:8px; }
.dc-t thead th { background:#0d1020; color:#64748b; font-size:7px; font-weight:700; text-transform:uppercase; padding:5px 7px; border-bottom:1px solid #1a1f35; text-align:center; }
.dc-t tbody td { padding:5px 7px; border-bottom:1px solid #131826; text-align:center; font-size:8px; color:#cbd5e1; }
.dc-t tr.dp td { background:#040f08 !important; }
.dc-t tr.dn td { background:#110404 !important; }
</style>
@endpush

<div class="ma">
<section class="pt-20 pb-40">
<div class="container-fluid content-container">

{{-- HEADER --}}
<div class="ma-hd">
    <div>
        <h4>⚡ Multi-Asset MF — FUT + Options Strategy</h4>
        <p>BUY 1 FUT lot · SELL 2 OTM CE lots (highest OI strike) · Select entry & exit date → see profit per stock</p>
    </div>
    <div style="text-align:right;font-size:8px;color:rgba(255,255,255,.5)">
        <div>Entry → Exit = Trade P&L</div>
        <div style="color:#a78bfa">CE sold = premium collected</div>
    </div>
</div>

{{-- STAT BAR --}}
<div class="st-grid">
    <div class="st-c" style="border-left-color:#a78bfa"><small>Symbols</small><strong style="color:#a78bfa" id="st-sym">—</strong></div>
    <div class="st-c" style="border-left-color:#60a5fa"><small>Open Trades</small><strong style="color:#60a5fa" id="st-op">—</strong></div>
    <div class="st-c" style="border-left-color:#fbbf24"><small>Closed Trades</small><strong style="color:#fbbf24" id="st-cl">—</strong></div>
    <div class="st-c" style="border-left-color:#4ade80"><small>Winners</small><strong style="color:#4ade80" id="st-wn">—</strong></div>
    <div class="st-c" style="border-left-color:#f87171"><small>Losers</small><strong style="color:#f87171" id="st-ls">—</strong></div>
    <div class="st-c" style="border-left-color:#a78bfa"><small>Total Net P&L</small><strong id="st-np">—</strong></div>
</div>

{{-- FILTERS --}}
<div class="ma-fl">
    <div class="row align-items-end" style="row-gap:8px">
        <div class="col-6 col-md-2">
            <label>📅 Entry Date (Buy on)</label>
            <select id="f-entry" class="form-control">
                <option value="">— Select —</option>
                @foreach($availableDates as $d)
                    <option value="{{ $d }}">{{ \Carbon\Carbon::parse($d)->format('d M Y') }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label>📅 Exit Date (Sell on) <span style="color:#475569;font-weight:400">(blank=OPEN)</span></label>
            <select id="f-exit" class="form-control">
                <option value="">— Still Open —</option>
                @foreach($availableDates as $d)
                    <option value="{{ $d }}">{{ \Carbon\Carbon::parse($d)->format('d M Y') }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label>Expiry <span style="color:#475569;font-weight:400">(auto if blank)</span></label>
            <select id="f-exp" class="form-control">
                <option value="">— Auto —</option>
                @foreach($expiries as $e)
                    <option value="{{ $e }}">{{ $e }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label>Fund Filter</label>
            <select id="f-fund" class="form-control">
                <option value="">All Funds</option>
                @foreach($funds as $f)
                    <option value="{{ $f->id }}">{{ $f->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4 d-flex align-items-end" style="gap:6px">
            <button class="btn-ma pr flex-fill" onclick="runSim()">
                <i class="fas fa-play"></i> Simulate Trade
            </button>
            <button class="btn-ma sc" onclick="resetAll()"><i class="fas fa-undo"></i></button>
        </div>
    </div>
</div>

{{-- TRADE LOG TABLE --}}
<div class="ma-cd">
    <div class="ma-ch">
        <span class="ma-ct" id="ma-title">Select dates and click Simulate Trade</span>
        <small style="color:#475569;font-size:8.5px" id="cnt">—</small>
    </div>
    <div id="ma-ld" style="display:none" class="ma-ld">
        <div class="ma-sp"></div>
        <div style="color:#7c3aed;font-size:10px;font-weight:700;margin-top:8px">Computing strategy P&L...</div>
    </div>
    <div class="ma-tw">
    <table class="ma-t">
        <colgroup>
            <col style="width:22px">
            <col style="width:120px">  {{-- Stock --}}
            <col style="width:58px">   {{-- Status --}}
            <col style="width:95px">   {{-- Entry --}}
            <col style="width:75px">   {{-- FUT Buy --}}
            <col style="width:95px">   {{-- CE Sell --}}
            <col style="width:95px">   {{-- Exit --}}
            <col style="width:75px">   {{-- FUT Exit --}}
            <col style="width:80px">   {{-- FUT P&L --}}
            <col style="width:80px">   {{-- CE P&L --}}
            <col style="width:90px">   {{-- Net P&L --}}
            <col style="width:60px">   {{-- Action --}}
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th class="th-s">Stock</th>
                <th class="th-s">Status</th>
                <th class="th-e">Entry Date<br><span style="font-size:6.5px">Buy Time</span></th>
                <th class="th-e">FUT Buy<br><span style="font-size:6.5px">Price</span></th>
                <th class="th-e">CE Sell<br><span style="font-size:6.5px">Strike · Premium</span></th>
                <th class="th-x">Exit Date<br><span style="font-size:6.5px">Sell Time</span></th>
                <th class="th-x">FUT Exit<br><span style="font-size:6.5px">Price</span></th>
                <th class="th-p">FUT P&L<br><span style="font-size:6.5px">1 lot</span></th>
                <th class="th-p">CE P&L<br><span style="font-size:6.5px">2 lots</span></th>
                <th class="th-p">Net P&L ₹<br><span style="font-size:6.5px">%</span></th>
                <th class="th-p">Detail</th>
            </tr>
        </thead>
        <tbody id="ma-tb">
            <tr><td colspan="12" style="padding:60px;text-align:center;color:#334155">
                <i class="fas fa-chart-bar" style="font-size:2rem;opacity:.08;display:block;margin-bottom:12px;color:#a78bfa"></i>
                <span style="color:#475569;font-size:9px">Select entry date and click <strong style="color:#a78bfa">Simulate Trade</strong></span>
            </td></tr>
        </tbody>
    </table>
    </div>
</div>

</div></section></div>

{{-- DRAWER — candle-by-candle P&L for one stock ─────────── --}}
<div id="drw-ov" onclick="closeDrawer()"></div>
<div id="drw">
    <div class="drw-hd">
        <div>
            <div id="drw-sym" style="color:#a78bfa;font-size:14px;font-weight:800">—</div>
            <div id="drw-sub" style="color:#475569;font-size:8px;margin-top:2px">Hourly P&L breakdown</div>
        </div>
        <button onclick="closeDrawer()" style="background:transparent;border:none;color:#64748b;font-size:22px;cursor:pointer;line-height:1;padding:0 6px">✕</button>
    </div>
    <div class="drw-bd" id="drw-body"></div>
</div>

@endsection

@push('script')
<script>
const SIM_API    = '{{ route("mf-multi-asset.simulate") }}';
const CANDLE_API = '{{ route("mf-multi-asset.candles") }}';

let _rows = [];

/* ── Formatters ──────────────────────────────────── */
const inr = v => '₹' + parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
const pct = v => { const n=parseFloat(v||0); return `<span class="${n>=0?'g':'r'}">${n>0?'+':''}${n.toFixed(2)}%</span>`; };
const pnl = v => { const n=parseFloat(v||0),s=n>0?'+':''; return `<span class="${n>=0?'g':'r'}">${s}${inr(n)}</span>`; };
const pnlBig = v => { const n=parseFloat(v||0),s=n>0?'+':''; return `<span class="${n>=0?'gbig':'rbig'}">${s}${inr(n)}</span>`; };

/* ── Run simulation ──────────────────────────────── */
function runSim() {
    const entry = document.getElementById('f-entry').value;
    const exit  = document.getElementById('f-exit').value;
    const exp   = document.getElementById('f-exp').value;
    const fund  = document.getElementById('f-fund').value;

    if (!entry) { alert('Select an entry date.'); return; }

    document.getElementById('ma-ld').style.display = 'flex';
    document.getElementById('ma-tb').innerHTML = '';
    document.getElementById('cnt').textContent = 'Computing...';

    let url = `${SIM_API}?entry_date=${entry}`;
    if (exit)  url += `&exit_date=${exit}`;
    if (exp)   url += `&expiry=${exp}`;
    if (fund)  url += `&fund_id=${fund}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            document.getElementById('ma-ld').style.display = 'none';
            if (!res.success) {
                document.getElementById('ma-tb').innerHTML = `<tr><td colspan="12" style="padding:30px;text-align:center;color:#f87171">${res.message}</td></tr>`;
                return;
            }
            _rows = res.data || [];
            renderTable(_rows);
            updateStats(res.summary);
            const label = exit
                ? `Entry: ${entry} → Exit: ${exit}`
                : `Entry: ${entry} → Still OPEN`;
            document.getElementById('ma-title').textContent = label + ` · ${_rows.length} stocks`;
            document.getElementById('cnt').textContent = _rows.length + ' stocks';
        })
        .catch(e => {
            document.getElementById('ma-ld').style.display = 'none';
            document.getElementById('ma-tb').innerHTML = `<tr><td colspan="12" style="padding:30px;text-align:center;color:#f87171">Error: ${e.message}</td></tr>`;
        });
}

/* ── Render table ────────────────────────────────── */
function renderTable(data) {
    if (!data.length) {
        document.getElementById('ma-tb').innerHTML = '<tr><td colspan="12" style="padding:40px;text-align:center;color:#475569">No data found for this period.</td></tr>';
        return;
    }
    let h = '';
    data.forEach((d, i) => {
        const isProfit = parseFloat(d.net_pnl||0) >= 0;
        const rowCls   = isProfit ? 'rp' : 'rn';
        const statusBadge = d.status === 'OPEN'
            ? '<span class="mk mk-open">● OPEN</span>'
            : '<span class="mk mk-cld">✓ CLOSED</span>';
        const sigBadge = isProfit
            ? '<span class="mk mk-win">▲ WIN</span>'
            : '<span class="mk mk-loss">▼ LOSS</span>';

        h += `<tr class="${rowCls}">
<td><div class="c" style="color:#334155">${i+1}</div></td>
<td><div class="c" style="text-align:left;padding-left:7px">
    <span class="sym-n">${d.symbol}</span>
    <span class="sym-s">${d.stock_name||'—'}</span>
    <span class="sym-f">${d.fund_code} · Lot: ${d.lot_size}</span>
</div></td>
<td><div class="c">${statusBadge}<br>${sigBadge}</div></td>

{{-- ENTRY --}}
<td><div class="c">
    <span class="mk mk-buy" style="font-size:7px">▲ BUY FUT</span><br>
    <span style="color:#38bdf8;font-size:8px;font-weight:700">${d.entry_date}</span><br>
    <span style="color:#475569;font-size:7px">${d.entry_time}</span>
</div></td>
<td><div class="c">
    <span style="color:#38bdf8;font-size:10px;font-weight:800">${inr(d.entry_fut_px)}</span><br>
    <span style="color:#334155;font-size:7px">entry</span>
</div></td>
<td><div class="c">
    <span class="mk mk-sell" style="font-size:7px">▼ SELL CE</span><br>
    <span style="color:#f87171;font-size:9px;font-weight:700">${parseFloat(d.entry_ce_strike).toLocaleString('en-IN')}</span><br>
    <span style="color:#fbbf24;font-size:8px">@ ${inr(d.entry_ce_px)}</span><br>
    <span style="color:#475569;font-size:7px">rcvd: ${inr(d.premium_rcvd)}</span>
</div></td>

{{-- EXIT --}}
<td><div class="c">
    ${d.status==='OPEN' ? '<span style="color:#60a5fa;font-size:8px">Running →</span>' : '<span class="mk mk-sell" style="font-size:7px;background:#d97706;border:none">✕ EXIT</span>'}
    <br><span style="color:#fbbf24;font-size:8px;font-weight:700">${d.exit_date}</span><br>
    <span style="color:#475569;font-size:7px">${d.exit_time}</span>
</div></td>
<td><div class="c">
    <span style="color:#fbbf24;font-size:10px;font-weight:800">${inr(d.exit_fut_px)}</span><br>
    <span style="${parseFloat(d.fut_chg_pct||0)>=0?'color:#4ade80':'color:#f87171'};font-size:7.5px">${parseFloat(d.fut_chg_pct||0)>0?'+':''}${parseFloat(d.fut_chg_pct||0).toFixed(2)}%</span>
</div></td>

{{-- P&L --}}
<td><div class="c">${pnl(d.fut_pnl)}</div></td>
<td><div class="c">${pnl(d.ce_pnl)}<br>
    <span style="color:#64748b;font-size:7px">CE decay: ${parseFloat(d.ce_decay_pct||0).toFixed(1)}%</span>
</div></td>
<td><div class="c">
    ${pnlBig(d.net_pnl)}<br>
    ${pct(d.net_pnl_pct)}
</div></td>
<td><div class="c">
    <button class="btn-detail" onclick="openDrawer(${i})">📋 Candles</button>
</div></td>
</tr>`;
    });
    document.getElementById('ma-tb').innerHTML = h;
}

/* ── Stat bar ────────────────────────────────────── */
function updateStats(s) {
    if (!s) return;
    const set = (id,v) => { const el=document.getElementById(id); if(el) el.innerHTML=v; };
    set('st-sym', s.total_symbols||0);
    set('st-op',  s.open_count||0);
    set('st-cl',  s.closed_count||0);
    set('st-wn',  `<span class="g">${s.win_count||0}</span>`);
    set('st-ls',  `<span class="r">${s.loss_count||0}</span>`);
    const np = parseFloat(s.total_net_pnl||0);
    set('st-np',  `<span class="${np>=0?'gbig':'rbig'}">${np>0?'+':''}${inr(np)}</span>`);
}

/* ── DRAWER — candle detail for one stock ────────── */
function openDrawer(idx) {
    const d = _rows[idx];
    if (!d) return;

    document.getElementById('drw-sym').textContent = d.symbol + ' — ' + (d.stock_name||'');
    document.getElementById('drw-sub').textContent =
        `Entry: ${d.entry_date} · Exit: ${d.exit_date} · Expiry: ${d.expiry}`;
    document.getElementById('drw-body').innerHTML = '<div style="color:#475569;font-size:9px;padding:20px">Loading candles...</div>';
    document.getElementById('drw-ov').style.display = 'block';
    document.getElementById('drw').style.right = '0';

    // Summary inside drawer
    const isProfit = parseFloat(d.net_pnl||0) >= 0;
    let summaryHtml = `
<div style="background:#0d1020;border-radius:8px;padding:12px;margin-bottom:12px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;font-size:8.5px">
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:2px">BUY FUT @</div>
         <div style="color:#38bdf8;font-weight:800;font-size:11px">${inr(d.entry_fut_px)}</div>
         <div style="color:#475569;font-size:7px">${d.entry_time}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:2px">SELL CE ${parseFloat(d.entry_ce_strike).toLocaleString('en-IN')} @</div>
         <div style="color:#f87171;font-weight:800;font-size:11px">${inr(d.entry_ce_px)}</div>
         <div style="color:#fbbf24;font-size:7px">2 lots · rcvd ${inr(d.premium_rcvd)}</div></div>
    <div><div style="color:#475569;font-size:6.5px;text-transform:uppercase;margin-bottom:2px">Net P&L</div>
         <div style="color:${isProfit?'#4ade80':'#f87171'};font-weight:800;font-size:13px">${isProfit?'+':''}${inr(d.net_pnl)}</div>
         <div style="${isProfit?'color:#4ade80':'color:#f87171'};font-size:8px">${isProfit?'+':''}${parseFloat(d.net_pnl_pct||0).toFixed(2)}%</div></div>
</div>`;

    // Fetch candles
    let url = `${CANDLE_API}?symbol=${d.symbol}&entry_date=${d.entry_date}&expiry=${d.expiry}`;
    if (d.exit_date && d.exit_date !== '—') url += `&exit_date=${d.exit_date}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                document.getElementById('drw-body').innerHTML = summaryHtml + `<div style="color:#f87171;padding:12px">${res.message}</div>`;
                return;
            }
            let tableHtml = `<div style="color:#64748b;font-size:7.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">
                Hourly P&L — Lot: ${res.lot_size} · Sell CE: ${parseFloat(res.sell_strike).toLocaleString('en-IN')}
            </div>
            <div style="overflow-x:auto">
            <table class="dc-t">
            <thead>
                <tr>
                    <th>DateTime</th>
                    <th style="color:#38bdf8">FUT Price</th>
                    <th style="color:#f87171">CE Price</th>
                    <th style="color:#38bdf8">FUT P&L</th>
                    <th style="color:#f87171">CE P&L</th>
                    <th style="color:#a78bfa">Net P&L</th>
                    <th>FUT OI</th>
                    <th>CE OI</th>
                </tr>
            </thead>
            <tbody>`;

            (res.candles||[]).forEach(c => {
                const pos = parseFloat(c.net_pnl||0) >= 0;
                tableHtml += `<tr class="${pos?'dp':'dn'}">
<td style="color:#94a3b8;font-weight:700">${c.datetime}</td>
<td style="color:#38bdf8">${inr(c.fut_price)}</td>
<td style="color:#f87171">${inr(c.ce_price)}</td>
<td>${pnl(c.fut_pnl)}</td>
<td>${pnl(c.ce_pnl)}</td>
<td style="font-weight:800">${pnl(c.net_pnl)}</td>
<td style="color:#475569;font-size:7.5px">${parseInt(c.fut_oi||0).toLocaleString('en-IN')}</td>
<td style="color:#475569;font-size:7.5px">${parseInt(c.ce_oi||0).toLocaleString('en-IN')}</td>
</tr>`;
            });

            tableHtml += '</tbody></table></div>';
            document.getElementById('drw-body').innerHTML = summaryHtml + tableHtml;
        })
        .catch(e => {
            document.getElementById('drw-body').innerHTML = summaryHtml + `<div style="color:#f87171;padding:12px">Error: ${e.message}</div>`;
        });
}

function closeDrawer() {
    document.getElementById('drw').style.right = '-530px';
    document.getElementById('drw-ov').style.display = 'none';
}

function resetAll() {
    document.getElementById('f-entry').value = '';
    document.getElementById('f-exit').value  = '';
    document.getElementById('f-exp').value   = '';
    document.getElementById('f-fund').value  = '';
    _rows = [];
    document.getElementById('ma-tb').innerHTML = '<tr><td colspan="12" style="padding:40px;text-align:center;color:#334155">Select dates and run simulation</td></tr>';
    document.getElementById('cnt').textContent = '—';
    document.getElementById('ma-title').textContent = 'Select dates and click Simulate Trade';
    ['st-sym','st-op','st-cl','st-wn','st-ls','st-np'].forEach(id => document.getElementById(id).textContent = '—');
}
</script>
@endpush