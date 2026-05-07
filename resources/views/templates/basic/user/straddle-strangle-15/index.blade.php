@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
:root{
    --cyan:#00e5ff;--green:#00e676;--red:#ff1744;
    --amber:#ffab00;--purple:#ce93d8;--blue:#448aff;--teal:#1de9b6;
    --bg:rgba(255,255,255,.025);--border:rgba(255,255,255,.07);
    --dim:rgba(255,255,255,.22);--mid:rgba(255,255,255,.55);--full:rgba(255,255,255,.9);
}
.ss-hdr{background:linear-gradient(135deg,#071a1a,#0c2e2e 55%,#071a1a);
    border:1px solid rgba(29,233,182,.18);border-radius:14px;padding:18px 24px;margin-bottom:16px;
    position:relative;overflow:hidden;}
.ss-hdr::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,transparent,var(--teal),transparent);}
.ss-hdr h4{color:var(--teal);margin:0;font-size:18px;font-weight:800;letter-spacing:.5px;}
.ss-hdr p{color:var(--mid);margin:5px 0 0;font-size:11px;line-height:1.7;}
.hpill{display:inline-block;background:rgba(29,233,182,.1);border:1px solid rgba(29,233,182,.25);
    border-radius:5px;padding:2px 9px;font-size:10px;color:var(--teal);font-weight:700;margin-right:3px;}
.hpill-g{background:rgba(0,230,118,.12);border-color:rgba(0,230,118,.3);color:var(--green);}
.hpill-a{background:rgba(255,171,0,.12);border-color:rgba(255,171,0,.3);color:var(--amber);}

/* ── Filter bar ── */
.ss-filter{background:linear-gradient(135deg,#0e2020,#152e2e);
    border:1px solid rgba(29,233,182,.1);border-radius:11px;
    padding:11px 18px;margin-bottom:16px;
    display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.ss-filter label{color:var(--dim);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;margin:0;}
.f-sel,.f-date{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);
    color:white;border-radius:7px;padding:5px 11px;font-size:12px;font-weight:700;outline:none;cursor:pointer;}
.f-sel option{background:#0a1f1f;}
.f-strat{min-width:175px;font-size:13px;font-weight:800;}
.f-sym{min-width:155px;}
.f-date::-webkit-calendar-picker-indicator{filter:invert(1);cursor:pointer;}
.f-load{background:var(--teal);color:#000;border:none;border-radius:7px;padding:6px 20px;font-weight:900;font-size:12px;cursor:pointer;}
.f-load:hover{background:#4dffd9;}
.f-auto{background:rgba(255,255,255,.07);color:white;border:1px solid rgba(255,255,255,.14);border-radius:7px;padding:5px 13px;font-size:11px;font-weight:700;cursor:pointer;}
.f-clr{background:rgba(255,171,0,.15);color:var(--amber);border:1px solid rgba(255,171,0,.3);border-radius:7px;padding:5px 13px;font-size:11px;font-weight:700;cursor:pointer;}
.f-clr:hover{background:rgba(255,171,0,.25);}
.nav-btn{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);color:white;
    border-radius:6px;width:27px;height:27px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:15px;font-weight:700;}
.nav-btn:hover{background:rgba(255,255,255,.14);}
.nav-wrap{display:flex;align-items:center;gap:5px;}
.d-live{font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:rgba(0,230,118,.14);color:var(--green);border:1px solid rgba(0,230,118,.3);}
.d-hist{font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:rgba(255,171,0,.14);color:var(--amber);border:1px solid rgba(255,171,0,.3);}
.f-div{width:1px;height:24px;background:rgba(255,255,255,.1);flex-shrink:0;}
.last-upd{font-size:10px;color:var(--dim);margin-left:auto;}
.ltp-src{display:inline-flex;align-items:center;gap:6px;background:rgba(0,230,118,.1);
    border:1px solid rgba(0,230,118,.25);border-radius:8px;padding:3px 10px;font-size:10px;font-weight:700;color:var(--green);}
.ltp-src.fb{background:rgba(255,171,0,.1);border-color:rgba(255,171,0,.25);color:var(--amber);}
.ltp-dot{width:7px;height:7px;border-radius:50%;animation:pulse 1.5s ease-in-out infinite;}
.dot-g{background:var(--green);}
.dot-a{background:var(--amber);}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.3;}}

/* ── Strategy strip ── */
.strat-strip{display:flex;align-items:center;gap:10px;padding:8px 16px;
    background:rgba(0,0,0,.25);border:1px solid var(--border);border-radius:10px;
    margin-bottom:14px;flex-wrap:wrap;font-size:11px;}
.sp-long{background:rgba(0,230,118,.16);color:var(--green);border:1px solid rgba(0,230,118,.35);border-radius:6px;padding:3px 12px;font-size:11px;font-weight:800;}
.sp-short{background:rgba(255,23,68,.16);color:#ff6b6b;border:1px solid rgba(255,23,68,.35);border-radius:6px;padding:3px 12px;font-size:11px;font-weight:800;}
.meta-sep{color:var(--dim);}
.strat-info{color:var(--mid);}
.strat-info strong{color:var(--full);}

/* ── Detail mode header card ── */
.detail-hdr{
    background:linear-gradient(135deg,rgba(0,0,0,.4),rgba(29,233,182,.04));
    border:1px solid rgba(29,233,182,.2);border-radius:12px;
    padding:14px 18px;margin-bottom:14px;
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;
}
.detail-sym{font-size:22px;font-weight:900;color:var(--teal);letter-spacing:.5px;}
.detail-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.dm{border-radius:6px;padding:3px 10px;font-size:10px;font-weight:700;border:1px solid;}
.dm-teal{background:rgba(29,233,182,.1);color:var(--teal);border-color:rgba(29,233,182,.25);}
.dm-amber{background:rgba(255,171,0,.1);color:var(--amber);border-color:rgba(255,171,0,.25);}
.dm-green{background:rgba(0,230,118,.1);color:var(--green);border-color:rgba(0,230,118,.25);}
.dm-blue{background:rgba(68,138,255,.1);color:var(--blue);border-color:rgba(68,138,255,.25);}
.dm-purple{background:rgba(206,147,216,.1);color:var(--purple);border-color:rgba(206,147,216,.25);}

/* ── Table card ── */
.tbl-card{border-radius:12px;overflow:hidden;border:1px solid var(--border);background:var(--bg);}
.tbl-meta{padding:9px 16px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;gap:10px;flex-wrap:wrap;
    background:rgba(0,0,0,.28);font-size:11px;}
.mp{background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:5px;padding:2px 10px;font-size:10px;font-weight:700;}
.mp-t{background:rgba(29,233,182,.1);border-color:rgba(29,233,182,.25);color:var(--teal);}
.mp-a{background:rgba(255,171,0,.1);border-color:rgba(255,171,0,.25);color:var(--amber);}
.mp-b{background:rgba(68,138,255,.1);border-color:rgba(68,138,255,.25);color:var(--blue);}
.mp-p{background:rgba(206,147,216,.1);border-color:rgba(206,147,216,.25);color:var(--purple);}
.tbl-wrap{overflow-x:auto;}

/* ── Tables ── */
.ss-tbl{width:100%;border-collapse:collapse;}
/* summary min-width — extra cols for lot size & per-lot P&L */
.ss-tbl.summary{min-width:2320px;}
/* detail min-width */
.ss-tbl.detail{min-width:1950px;}

.ss-tbl thead tr.g-hdr th{padding:9px 10px 5px;text-align:center;font-size:9px;font-weight:800;
    text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;background:rgba(0,0,0,.45);border-bottom:none;}
.ss-tbl thead tr.c-hdr th{padding:5px 9px 9px;text-align:center;font-size:8.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.2px;white-space:nowrap;
    background:rgba(0,0,0,.3);color:var(--dim);border-bottom:2px solid var(--border);}
.ss-tbl tbody td{padding:8px 10px;text-align:center;border-bottom:1px solid rgba(255,255,255,.035);
    vertical-align:middle;white-space:nowrap;font-size:11px;}
.ss-tbl tbody tr:nth-child(odd) {background:rgba(255,255,255,.012);}
.ss-tbl tbody tr:nth-child(even){background:rgba(0,0,0,.1);}
.ss-tbl tbody tr:hover{background:rgba(255,255,255,.05) !important;}

/* Detail mode row highlights */
.tr-entry{background:rgba(29,233,182,.05) !important;}
.tr-latest{background:rgba(255,171,0,.05) !important;}

/* Separators */
.sep-sym{border-left:1px solid var(--border) !important;}
.sep-ce{border-left:2px solid rgba(0,230,118,.3) !important;}
.sep-pe{border-left:2px solid rgba(255,107,107,.3) !important;}
.sep-pr{border-left:2px solid rgba(29,233,182,.3) !important;}
.sep-oi{border-left:2px solid rgba(255,171,0,.3) !important;}
.sep-ex{border-left:2px solid rgba(206,147,216,.4) !important;}

/* BG tints */
.bg-ce{background:rgba(0,230,118,.018) !important;}
.bg-pe{background:rgba(255,107,107,.018) !important;}
.bg-pr{background:rgba(29,233,182,.018) !important;}
.bg-oi{background:rgba(255,171,0,.018) !important;}
.bg-ex{background:rgba(206,147,216,.02) !important;}

/* Header colors */
.h-s{color:rgba(255,255,255,.4) !important;}
.h-ce{color:#69f0ae !important;}
.h-pe{color:#ff8a80 !important;}
.h-pr{color:var(--teal) !important;}
.h-oi{color:var(--amber) !important;}
.h-ex{color:var(--purple) !important;}

/* Cell styles */
.sym-badge{display:inline-block;background:rgba(29,233,182,.12);border:1px solid rgba(29,233,182,.25);
    border-radius:6px;padding:3px 10px;font-size:12px;font-weight:800;color:var(--teal);}
.c-atm{font-size:9px;color:var(--amber);font-weight:600;}
.c-exp{font-size:9px;color:var(--dim);margin-top:1px;}
.time-entry{background:rgba(29,233,182,.15);color:var(--teal);border:1px solid rgba(29,233,182,.35);
    border-radius:5px;padding:2px 8px;font-size:10px;font-weight:800;}
.time-latest{background:rgba(255,171,0,.15);color:var(--amber);border:1px solid rgba(255,171,0,.35);
    border-radius:5px;padding:2px 8px;font-size:10px;font-weight:800;}
.time-normal{color:var(--cyan);font-weight:700;font-size:11px;}
.tb-buy{background:rgba(0,230,118,.16);color:var(--green);border:1px solid rgba(0,230,118,.35);border-radius:5px;padding:2px 9px;font-size:9px;font-weight:800;}
.tb-sell{background:rgba(255,23,68,.16);color:#ff6b6b;border:1px solid rgba(255,23,68,.35);border-radius:5px;padding:2px 9px;font-size:9px;font-weight:800;}
.c-sn{font-size:10px;color:var(--mid);}
.c-sk{font-size:9px;color:var(--dim);}
.c-pr{color:#e0fdf4;font-weight:700;}
.c-pr-live{color:var(--green);font-weight:800;}
.live-tag{display:inline-block;background:rgba(0,230,118,.15);color:var(--green);border:1px solid rgba(0,230,118,.3);
    border-radius:4px;padding:0 5px;font-size:8px;font-weight:800;margin-left:3px;vertical-align:middle;}
.open-tag{display:inline-block;background:rgba(255,171,0,.15);color:var(--amber);border:1px solid rgba(255,171,0,.3);
    border-radius:4px;padding:0 5px;font-size:8px;font-weight:800;margin-left:3px;vertical-align:middle;}
.c-oi{color:var(--mid);font-size:10px;}
.c-ltp{color:#e0fdf4;font-weight:700;}
.c-prem{color:var(--teal);font-weight:800;font-size:12px;}
.c-run{color:var(--amber);font-weight:800;font-size:12px;}
.c-pos{color:var(--green);font-weight:800;font-size:13px;}
.c-neg{color:#ff5252;font-weight:800;font-size:13px;}
.c-dim{color:var(--dim);font-size:9px;}
.pct-pos{color:var(--green);font-weight:700;}
.pct-neg{color:#ff6b6b;font-weight:700;}
.pct-z{color:var(--dim);}

/* Lot size badge */
.lot-badge{display:inline-block;background:rgba(206,147,216,.12);border:1px solid rgba(206,147,216,.3);
    border-radius:5px;padding:2px 8px;font-size:10px;font-weight:800;color:var(--purple);}

/* P&L per lot — slightly larger emphasis */
.c-pos-lot{color:#69ff9a;font-weight:900;font-size:14px;}
.c-neg-lot{color:#ff4040;font-weight:900;font-size:14px;}
/* Sub-label under per-unit P&L */
.pnl-unit-sub{display:block;font-size:9px;color:var(--dim);font-weight:400;margin-top:1px;}
/* Per-lot label below the big number */
.pnl-lot-sub{display:block;font-size:9px;color:rgba(255,255,255,.35);font-weight:400;margin-top:1px;}

.oi-bull{background:rgba(0,230,118,.2);color:var(--green);border:1px solid rgba(0,230,118,.45);border-radius:5px;padding:3px 10px;font-size:10px;font-weight:800;}
.oi-bear{background:rgba(255,23,68,.2);color:#ff5252;border:1px solid rgba(255,23,68,.45);border-radius:5px;padding:3px 10px;font-size:10px;font-weight:800;}
.oi-na{color:var(--dim);font-size:10px;}

.str-vs{background:rgba(255,23,68,.2);color:#ff5252;border:1px solid rgba(255,23,68,.4);border-radius:4px;padding:2px 7px;font-size:9px;font-weight:800;}
.str-s{background:rgba(255,171,0,.2);color:var(--amber);border:1px solid rgba(255,171,0,.4);border-radius:4px;padding:2px 7px;font-size:9px;font-weight:800;}
.str-m{background:rgba(29,233,182,.12);color:var(--teal);border:1px solid rgba(29,233,182,.3);border-radius:4px;padding:2px 7px;font-size:9px;font-weight:700;}
.str-w{background:rgba(255,255,255,.05);color:var(--dim);border:1px solid var(--border);border-radius:4px;padding:2px 7px;font-size:9px;font-weight:600;}

.ex-pe{background:rgba(255,107,107,.18);color:#ff6b6b;border:1px solid rgba(255,107,107,.4);border-radius:6px;padding:3px 11px;font-size:10px;font-weight:800;white-space:nowrap;}
.ex-ce{background:rgba(0,230,118,.18);color:#69f0ae;border:1px solid rgba(0,230,118,.4);border-radius:6px;padding:3px 11px;font-size:10px;font-weight:800;white-space:nowrap;}
.ex-both{background:rgba(255,171,0,.18);color:var(--amber);border:1px solid rgba(255,171,0,.4);border-radius:6px;padding:3px 11px;font-size:10px;font-weight:800;white-space:nowrap;}
.ex-hold{background:rgba(68,138,255,.18);color:var(--blue);border:1px solid rgba(68,138,255,.4);border-radius:6px;padding:3px 11px;font-size:10px;font-weight:800;white-space:nowrap;}
.ex-na{color:var(--dim);font-size:10px;}
.hd-ce,.hd-pe{background:rgba(29,233,182,.1);color:var(--teal);border:1px solid rgba(29,233,182,.25);border-radius:5px;padding:2px 9px;font-size:10px;font-weight:700;}
.hd-both{background:rgba(68,138,255,.12);color:var(--blue);border:1px solid rgba(68,138,255,.3);border-radius:5px;padding:2px 9px;font-size:10px;font-weight:700;}
.hd-none{color:var(--dim);font-size:9px;}

.remarks-td{text-align:left !important;white-space:normal !important;min-width:240px;max-width:360px;font-size:10px;line-height:1.55;color:var(--mid);}

.spinner{width:32px;height:32px;border:3px solid rgba(255,255,255,.1);border-top:3px solid var(--teal);border-radius:50%;animation:spin 1s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.load-box{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px;}
.no-box{text-align:center;padding:50px;color:var(--dim);font-size:13px;}

.legend{padding:8px 16px;border-top:1px solid var(--border);font-size:9.5px;color:var(--dim);line-height:1.8;background:rgba(0,0,0,.15);}
.legend strong{color:rgba(255,255,255,.45);}

/* Detail mode total row */
.tr-total td{font-weight:900 !important;background:rgba(29,233,182,.07) !important;border-top:2px solid rgba(29,233,182,.25) !important;}
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="ss-hdr">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>&#9889; Straddle &amp; Strangle — 15-Min Candle</h4>
                <p>
                    <span class="hpill hpill-g">&#9679; Entry: Live LTP @ 9:16</span>
                    <span class="hpill hpill-a">&#9660; Running: Latest 15-min candle</span>
                    <span class="hpill">&#128200; All symbols summary or pick one symbol for every 15-min interval</span>
                    <span class="hpill" style="background:rgba(206,147,216,.12);border-color:rgba(206,147,216,.3);color:var(--purple);">&#9670; P&amp;L shown per unit &amp; per lot</span>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('straddle-strangle.index') }}" class="btn btn-sm btn-outline-info">&#8594; 1hr Version</a>
                <a href="{{ route('pivot-signal.index') }}" class="btn btn-sm btn-outline-secondary">Pivot</a>
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="ss-filter">
        <label>STRATEGY:</label>
        <select id="strat-sel" class="f-sel f-strat" onchange="loadData()">
            <option value="long_strangle">Long Strangle</option>
            <option value="short_strangle">Short Strangle</option>
            <option value="long_straddle" selected>Long Straddle</option>
            <option value="short_straddle">Short Straddle</option>
        </select>

        <div class="f-div"></div>

        <label>SYMBOL:</label>
        <select id="sym-sel" class="f-sel f-sym" onchange="loadData()">
            <option value="ALL">— All Symbols —</option>
        </select>

        <div class="f-div"></div>

        <label>DATE:</label>
        <div class="nav-wrap">
            <button class="nav-btn" onclick="shiftDate(-1)">&#8249;</button>
            <input type="date" id="date-picker" class="f-date"
                   value="{{ now()->toDateString() }}"
                   max="{{ now()->toDateString() }}"
                   onchange="loadData()">
            <button class="nav-btn" onclick="shiftDate(1)">&#8250;</button>
            <button class="nav-btn" style="width:auto;padding:0 8px;font-size:10px;" onclick="goToday()">Today</button>
            <span id="d-badge"></span>
        </div>

        <button class="f-load" onclick="loadData()">&#8635; Load</button>
        <button class="f-auto" id="auto-btn" onclick="toggleAuto()">&#9654; Auto 30s</button>
        <button class="f-clr" onclick="clearCache()" title="Re-fetch live entry prices">&#128465; Clear LTP Cache</button>
        <span id="auto-tag" style="font-size:10px;color:rgba(255,255,255,.4);"></span>
        <span id="ltp-badge"></span>
        <span class="last-upd" id="last-upd"></span>
    </div>

    {{-- Strategy info strip --}}
    <div id="strat-strip" style="display:none;" class="strat-strip"></div>

    {{-- Main content --}}
    <div id="ss-wrap">
        <div class="load-box"><div class="spinner"></div>
            <div style="color:white;margin-top:14px;font-size:13px;">Loading…</div>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
const todayStr  = '{{ now()->toDateString() }}';
let autoTimer   = null;
let availSyms   = [];

$(document).ready(() => loadData());

const getDate  = () => document.getElementById('date-picker').value;
const getStrat = () => document.getElementById('strat-sel').value;
const getSym   = () => document.getElementById('sym-sel').value;

function shiftDate(d){
    const p=document.getElementById('date-picker');
    const dt=new Date(p.value); dt.setDate(dt.getDate()+d);
    const s=dt.toISOString().split('T')[0];
    if(s>todayStr) return; p.value=s; loadData();
}
function goToday(){ document.getElementById('date-picker').value=todayStr; loadData(); }

function updateDateBadge(isToday){
    document.getElementById('d-badge').innerHTML = isToday
        ? '<span class="d-live">&#9679; Live</span>'
        : '<span class="d-hist">&#128197; Historical</span>';
}

function updateLtpBadge(src){
    const el = document.getElementById('ltp-badge');
    if(src && src.includes('live'))
        el.innerHTML='<span class="ltp-src"><span class="ltp-dot dot-g"></span>Live LTP @ 9:16</span>';
    else
        el.innerHTML='<span class="ltp-src fb"><span class="ltp-dot dot-a"></span>09:15 Open</span>';
}

function rebuildSymDropdown(symbols){
    if(JSON.stringify(availSyms)===JSON.stringify(symbols)) return;
    availSyms = symbols;
    const sel=document.getElementById('sym-sel'), prev=sel.value;
    sel.innerHTML='<option value="ALL">— All Symbols —</option>';
    symbols.forEach(s=>{
        const o=document.createElement('option');
        o.value=s; o.textContent=s;
        if(s===prev) o.selected=true;
        sel.appendChild(o);
    });
}

function toggleAuto(){
    if(autoTimer){ clearInterval(autoTimer); autoTimer=null; $('#auto-btn').text('▶ Auto 30s'); $('#auto-tag').text(''); }
    else{ autoTimer=setInterval(loadData,30000); $('#auto-btn').text('■ Stop'); $('#auto-tag').css('color','#1de9b6').text('● live'); loadData(); }
}

function clearCache(){
    $.post('{{ route("straddle-strangle-15.clear-ltp-cache") }}',
        { strategy: getStrat(), _token:'{{ csrf_token() }}' },
        res=>{ if(res.success){ alert('✅ '+res.message); loadData(); } }
    ).fail(()=>alert('Failed.'));
}

// ── Load ──────────────────────────────────────────────────────────────────────

function loadData(){
    const date=getDate(), strat=getStrat(), sym=getSym();
    if(date!==todayStr&&autoTimer){ clearInterval(autoTimer); autoTimer=null; $('#auto-btn').text('▶ Auto 30s'); $('#auto-tag').text(''); }

    $('#ss-wrap').html('<div class="load-box"><div class="spinner"></div><div style="color:white;margin-top:13px;font-size:13px;">Fetching…</div></div>');
    $('#strat-strip').hide();

    $.ajax({
        url :'{{ route("straddle-strangle-15.data") }}',
        data:{ strategy:strat, date:date, symbol:sym },
        success(res){
            updateDateBadge(res.is_today);
            updateLtpBadge(res.price_source);
            if(res.available_symbols&&res.available_symbols.length) rebuildSymDropdown(res.available_symbols);
            if(!res.success||!res.data||!res.data.length){
                $('#ss-wrap').html('<div class="tbl-card"><div class="no-box"><p>'+(res.message||'No data for '+date)+'</p></div></div>');
                return;
            }
            if(res.mode==='detail') renderDetail(res);
            else                    renderSummary(res);
            $('#last-upd').text('Updated: '+new Date().toLocaleTimeString());
        },
        error(xhr){
            $('#ss-wrap').html('<div class="tbl-card"><div class="no-box">&#9888; '+((xhr.responseJSON&&xhr.responseJSON.message)||'Server error')+'</div></div>');
        }
    });
}

// ══════════════════════════════════════════════════════════════════════════════
// MODE 1: ALL symbols — summary table
// ══════════════════════════════════════════════════════════════════════════════

function renderSummary(res){
    const isShort=res.ce_type==='Sell';
    const spCls=isShort?'sp-short':'sp-long';
    const isLive=res.price_source&&res.price_source.includes('live');
    const priceLabel=isLive?'Live LTP @ 9:16':'09:15 Open';

    $('#strat-strip').html(`
        <span class="strat-pill ${spCls}">${isShort?'&#9660; SHORT':'&#9650; LONG'} — ${res.strategy_name}</span>
        <span class="strat-info">CE: <strong>${res.ce_type} ${res.ce_pos}</strong></span>
        <span class="meta-sep">|</span>
        <span class="strat-info">PE: <strong>${res.pe_type} ${res.pe_pos}</strong></span>
        <span class="meta-sep">|</span>
        <span class="strat-info">Date: <strong>${res.today}</strong></span>
        <span class="meta-sep">|</span>
        <span style="background:rgba(29,233,182,.1);border:1px solid rgba(29,233,182,.25);color:var(--teal);border-radius:6px;padding:3px 10px;font-size:11px;font-weight:800;">${res.total} Symbols</span>
        <span style="background:rgba(68,138,255,.1);border:1px solid rgba(68,138,255,.25);color:var(--blue);border-radius:6px;padding:3px 10px;font-size:10px;font-weight:700;">&#9201; 15-Min</span>
    `).show();

    const latestSlots=[...new Set(res.data.map(r=>r.latest_slot))];
    const latestLabel=latestSlots.length===1?latestSlots[0]:'mixed';

    const meta=`<div class="tbl-meta">
        <span style="color:var(--full);font-weight:700;">&#128200; ${res.strategy_name} — All Symbols</span>
        <span class="mp mp-t">Entry: ${res.entry_slot}</span>
        <span class="mp mp-a">Latest: ${latestLabel}</span>
        <span class="mp mp-b">&#9201; 15-Min</span>
        <span class="mp mp-p">${res.total} Symbols</span>
        <span style="font-size:10px;margin-left:4px;">${isLive?'<span style="color:var(--green);font-weight:700;">&#9679; Live LTP</span>':'<span style="color:var(--amber);font-weight:700;">&#9679; 09:15 Open</span>'}</span>
        <span style="font-size:10px;color:var(--dim);margin-left:auto;">${res.today}</span>
    </div>`;

    // ── 4 P&L columns now: per-unit | per-lot | lot-size
    const thead=`<thead>
        <tr class="g-hdr">
            <th colspan="3" class="h-s sep-sym">Symbol</th>
            <th colspan="7" class="h-ce sep-ce">&#128200; CE — ${res.ce_type} ${res.ce_pos}</th>
            <th colspan="7" class="h-pe sep-pe">&#128201; PE — ${res.pe_type} ${res.pe_pos}</th>
            <th colspan="5" class="h-pr sep-pr">&#9654; Premium &amp; P&amp;L</th>
            <th colspan="2" class="h-oi sep-oi">&#9889; OI Sentiment</th>
            <th colspan="3" class="h-ex sep-ex">&#9888; Exit Management</th>
        </tr>
        <tr class="c-hdr">
            <th class="h-s sep-sym">#</th><th class="h-s">Symbol</th><th class="h-s">ATM / Expiry</th>
            <th class="h-ce sep-ce bg-ce">TXN</th>
            <th class="h-ce bg-ce">CE Symbol</th>
            <th class="h-ce bg-ce">CE Price<br><span style="font-size:7px;opacity:.6;font-weight:400;">${priceLabel}</span></th>
            <th class="h-ce bg-ce">OI Init<br><span style="font-size:7px;opacity:.6;font-weight:400;">09:15</span></th>
            <th class="h-ce bg-ce">Crnt OI<br><span style="font-size:7px;opacity:.6;font-weight:400;">Latest</span></th>
            <th class="h-ce bg-ce">CE %</th>
            <th class="h-ce bg-ce">CE LTP</th>
            <th class="h-pe sep-pe bg-pe">TXN</th>
            <th class="h-pe bg-pe">PE Symbol</th>
            <th class="h-pe bg-pe">PE Price<br><span style="font-size:7px;opacity:.6;font-weight:400;">${priceLabel}</span></th>
            <th class="h-pe bg-pe">OI Init<br><span style="font-size:7px;opacity:.6;font-weight:400;">09:15</span></th>
            <th class="h-pe bg-pe">Crnt OI<br><span style="font-size:7px;opacity:.6;font-weight:400;">Latest</span></th>
            <th class="h-pe bg-pe">PE %</th>
            <th class="h-pe bg-pe">PE LTP</th>
            <th class="h-pr sep-pr bg-pr">Strategy<br>Premium</th>
            <th class="h-pr bg-pr">Running<br>Premium</th>
            <th class="h-pr bg-pr">P&amp;L<br><span style="font-size:7px;opacity:.6;font-weight:400;">Per Unit</span></th>
            <th class="h-pr bg-pr" style="color:var(--purple) !important;">Lot<br>Size</th>
            <th class="h-pr bg-pr" style="color:var(--purple) !important;">P&amp;L<br><span style="font-size:7px;opacity:.7;font-weight:400;">Per Lot ×Qty</span></th>
            <th class="h-oi sep-oi bg-oi">MKT Sentiment</th>
            <th class="h-oi bg-oi">Strength</th>
            <th class="h-ex sep-ex bg-ex">Exit First</th>
            <th class="h-ex bg-ex">Hold Leg</th>
            <th class="h-ex bg-ex">Remarks</th>
        </tr>
    </thead>`;

    let tbody='<tbody>';
    res.data.forEach((s,i)=>{
        const isLiveSrc=s.price_source==='live';
        const ptag=isLiveSrc?'<span class="live-tag">LIVE</span>':'<span class="open-tag">OPEN</span>';
        const priceCls=isLiveSrc?'c-pr-live':'c-pr';
        const ceSym=s.ce_trading_symbol?`<div class="c-sn">${s.ce_trading_symbol}</div>`:dim(s.ce_symbol_name||'—');
        const peSym=s.pe_trading_symbol?`<div class="c-sn">${s.pe_trading_symbol}</div>`:dim(s.pe_symbol_name||'—');

        // Per-unit P&L
        const pnlUnit = pnlUnitHtml(s.profit_loss);
        // Per-lot P&L
        const pnlLot  = pnlLotHtml(s.profit_loss_lot, s.lot_size);
        // Lot badge
        const lotBadge = s.lot_size && s.lot_size > 1
            ? `<span class="lot-badge">${nInt(s.lot_size)}</span>`
            : dim(s.lot_size || '—');

        tbody+=`<tr>
            <td class="sep-sym" style="color:var(--dim);font-size:10px;">${i+1}</td>
            <td><span class="sym-badge" style="cursor:pointer;" onclick="jumpToSym('${s.symbol}')">${s.symbol}</span></td>
            <td><div class="c-atm">ATM &#8377;${nInt(s.atm_strike)}</div><div class="c-exp">${s.expiry||'—'}</div></td>
            <td class="sep-ce bg-ce">${txnBadge(s.ce_txn_type)}</td>
            <td class="bg-ce">${ceSym}</td>
            <td class="${priceCls} bg-ce">${s.ce_price!==null?'&#8377;'+nf(s.ce_price)+ptag:dim('—')}</td>
            <td class="c-oi bg-ce">${s.ce_oi_init!==null?oiNum(s.ce_oi_init):dim('—')}</td>
            <td class="c-oi bg-ce">${s.ce_crnt_oi!==null?oiNum(s.ce_crnt_oi):dim('—')}</td>
            <td class="bg-ce">${pctHtml(s.ce_pct)}</td>
            <td class="c-ltp bg-ce">${s.ce_ltp!==null?'&#8377;'+nf(s.ce_ltp):dim('—')}</td>
            <td class="sep-pe bg-pe">${txnBadge(s.pe_txn_type)}</td>
            <td class="bg-pe">${peSym}</td>
            <td class="${priceCls} bg-pe">${s.pe_price!==null?'&#8377;'+nf(s.pe_price)+ptag:dim('—')}</td>
            <td class="c-oi bg-pe">${s.pe_oi_init!==null?oiNum(s.pe_oi_init):dim('—')}</td>
            <td class="c-oi bg-pe">${s.pe_crnt_oi!==null?oiNum(s.pe_crnt_oi):dim('—')}</td>
            <td class="bg-pe">${pctHtml(s.pe_pct)}</td>
            <td class="c-ltp bg-pe">${s.pe_ltp!==null?'&#8377;'+nf(s.pe_ltp):dim('—')}</td>
            <td class="c-prem sep-pr bg-pr">${s.strategy_combined_premium!==null?'&#8377;'+nf(s.strategy_combined_premium):dim('—')}</td>
            <td class="c-run bg-pr">${s.running_combined_premium!==null?'&#8377;'+nf(s.running_combined_premium):dim('—')}</td>
            <td class="bg-pr">${pnlUnit}</td>
            <td class="bg-pr">${lotBadge}</td>
            <td class="bg-pr">${pnlLot}</td>
            <td class="sep-oi bg-oi">${oiBadge(s.mkt_sentiment,s.oi_condition,s.oi_reason)}</td>
            <td class="bg-oi">${strBadge(s.oi_strength)}</td>
            <td class="sep-ex bg-ex">${exitBadge(s.exit_first)}</td>
            <td class="bg-ex">${holdBadge(s.hold_leg)}</td>
            <td class="bg-ex remarks-td">${esc(s.remarks||'—')}</td>
        </tr>`;
    });
    tbody+='</tbody>';

    const legend=legendBar();
    $('#ss-wrap').html(`<div class="tbl-card">${meta}<div class="tbl-wrap"><table class="ss-tbl summary">${thead}${tbody}</table></div>${legend}</div>`);
}

// ══════════════════════════════════════════════════════════════════════════════
// MODE 2: Single symbol — every 15-min interval
// ══════════════════════════════════════════════════════════════════════════════

function renderDetail(res){
    const isShort=res.ce_type==='Sell';
    const isLive=res.price_source&&res.price_source.includes('live');
    const priceLabel=isLive?'Live LTP @ 9:16':'09:15 Open';
    const lotSize = res.lot_size || 1;

    $('#strat-strip').hide();

    // Detail header card
    const detHdr=`<div class="detail-hdr">
        <div>
            <span class="detail-sym">&#9889; ${res.symbol}</span>
            <div style="font-size:11px;color:var(--mid);margin-top:4px;">${res.strategy_name} &nbsp;|&nbsp; CE: ${res.ce_type} ${res.ce_pos} &nbsp;|&nbsp; PE: ${res.pe_type} ${res.pe_pos}</div>
        </div>
        <div class="detail-meta">
            <span class="dm dm-teal">ATM &#8377;${nInt(res.atm_strike)}</span>
            <span class="dm dm-amber">Expiry: ${res.expiry||'—'}</span>
            <span class="dm dm-green">Entry: ${res.entry_slot} &nbsp;|&nbsp; ${isLive?'&#9679; Live LTP':'09:15 Open'}</span>
            <span class="dm dm-teal">CE Entry: &#8377;${nf(res.ce_price)} &nbsp;|&nbsp; PE Entry: &#8377;${nf(res.pe_price)}</span>
            <span class="dm dm-blue">Strategy Premium: &#8377;${nf(res.strategy_combined_premium)}</span>
            <span class="dm dm-purple">Lot Size: ${nInt(lotSize)}</span>
            <span class="dm dm-amber">Latest Slot: ${res.latest_slot||'—'}</span>
            <span class="dm dm-blue">&#9201; ${res.total_intervals} Intervals</span>
        </div>
    </div>`;

    // Table header — split P&L into per-unit + lot size + per-lot
    const thead=`<thead>
        <tr class="g-hdr">
            <th class="h-s sep-sym" rowspan="2">Time</th>
            <th colspan="5" class="h-ce sep-ce">&#128200; CE — ${res.ce_type} ${res.ce_pos} ${res.ce_trading_symbol?'('+res.ce_trading_symbol+')':''}</th>
            <th colspan="5" class="h-pe sep-pe">&#128201; PE — ${res.pe_type} ${res.pe_pos} ${res.pe_trading_symbol?'('+res.pe_trading_symbol+')':''}</th>
            <th colspan="5" class="h-pr sep-pr">&#9654; Premium &amp; P&amp;L</th>
            <th colspan="2" class="h-oi sep-oi">&#9889; OI Sentiment</th>
            <th colspan="3" class="h-ex sep-ex">&#9888; Exit Management</th>
        </tr>
        <tr class="c-hdr">
            <th class="h-ce sep-ce bg-ce">CE Price<br><span style="font-size:7px;opacity:.6;font-weight:400;">${priceLabel}</span></th>
            <th class="h-ce bg-ce">OI Init<br><span style="font-size:7px;opacity:.6;font-weight:400;">Fixed 09:15</span></th>
            <th class="h-ce bg-ce">Crnt OI</th>
            <th class="h-ce bg-ce">CE %</th>
            <th class="h-ce bg-ce">CE LTP</th>
            <th class="h-pe sep-pe bg-pe">PE Price<br><span style="font-size:7px;opacity:.6;font-weight:400;">${priceLabel}</span></th>
            <th class="h-pe bg-pe">OI Init<br><span style="font-size:7px;opacity:.6;font-weight:400;">Fixed 09:15</span></th>
            <th class="h-pe bg-pe">Crnt OI</th>
            <th class="h-pe bg-pe">PE %</th>
            <th class="h-pe bg-pe">PE LTP</th>
            <th class="h-pr sep-pr bg-pr">Strategy<br>Premium</th>
            <th class="h-pr bg-pr">Running<br>Premium</th>
            <th class="h-pr bg-pr">P&amp;L<br><span style="font-size:7px;opacity:.6;font-weight:400;">Per Unit</span></th>
            <th class="h-pr bg-pr" style="color:var(--purple) !important;">Lot<br>Size</th>
            <th class="h-pr bg-pr" style="color:var(--purple) !important;">P&amp;L<br><span style="font-size:7px;opacity:.7;font-weight:400;">Per Lot ×Qty</span></th>
            <th class="h-oi sep-oi bg-oi">MKT Sentiment</th>
            <th class="h-oi bg-oi">Strength</th>
            <th class="h-ex sep-ex bg-ex">Exit First</th>
            <th class="h-ex bg-ex">Hold Leg</th>
            <th class="h-ex bg-ex">Remarks</th>
        </tr>
    </thead>`;

    const isLiveSrc=isLive;
    const ptag=isLiveSrc?'<span class="live-tag">LIVE</span>':'<span class="open-tag">OPEN</span>';
    const priceCls=isLiveSrc?'c-pr-live':'c-pr';

    // Shared lot badge for all rows (same symbol, same lot size)
    const lotBadge = lotSize > 1 ? `<span class="lot-badge">${nInt(lotSize)}</span>` : dim(lotSize);

    let tbody='<tbody>';
    res.data.forEach(iv=>{
        const rowCls=iv.is_entry?'tr-entry':iv.is_latest?'tr-latest':'';
        const timePill=iv.is_entry
            ?`<span class="time-entry">&#9650; ${iv.time}</span>`
            :iv.is_latest
            ?`<span class="time-latest">&#9660; ${iv.time}</span>`
            :`<span class="time-normal">${iv.time}</span>`;

        const pnlUnit = pnlUnitHtml(iv.profit_loss);
        const pnlLot  = pnlLotHtml(iv.profit_loss_lot, iv.lot_size);

        tbody+=`<tr class="${rowCls}">
            <td class="sep-sym">${timePill}</td>
            <td class="${priceCls} sep-ce bg-ce">${iv.ce_price!==null?'&#8377;'+nf(iv.ce_price)+ptag:dim('—')}</td>
            <td class="c-oi bg-ce">${iv.ce_oi_init!==null?oiNum(iv.ce_oi_init):dim('—')}</td>
            <td class="c-oi bg-ce">${iv.ce_crnt_oi!==null?oiNum(iv.ce_crnt_oi):dim('—')}</td>
            <td class="bg-ce">${pctHtml(iv.ce_pct)}</td>
            <td class="c-ltp bg-ce">${iv.ce_ltp!==null?'&#8377;'+nf(iv.ce_ltp):dim('—')}</td>
            <td class="${priceCls} sep-pe bg-pe">${iv.pe_price!==null?'&#8377;'+nf(iv.pe_price)+ptag:dim('—')}</td>
            <td class="c-oi bg-pe">${iv.pe_oi_init!==null?oiNum(iv.pe_oi_init):dim('—')}</td>
            <td class="c-oi bg-pe">${iv.pe_crnt_oi!==null?oiNum(iv.pe_crnt_oi):dim('—')}</td>
            <td class="bg-pe">${pctHtml(iv.pe_pct)}</td>
            <td class="c-ltp bg-pe">${iv.pe_ltp!==null?'&#8377;'+nf(iv.pe_ltp):dim('—')}</td>
            <td class="c-prem sep-pr bg-pr">${iv.strategy_combined_premium!==null?'&#8377;'+nf(iv.strategy_combined_premium):dim('—')}</td>
            <td class="c-run bg-pr">${iv.running_combined_premium!==null?'&#8377;'+nf(iv.running_combined_premium):dim('—')}</td>
            <td class="bg-pr">${pnlUnit}</td>
            <td class="bg-pr">${lotBadge}</td>
            <td class="bg-pr">${pnlLot}</td>
            <td class="sep-oi bg-oi">${oiBadge(iv.mkt_sentiment,iv.oi_condition,iv.oi_reason)}</td>
            <td class="bg-oi">${strBadge(iv.oi_strength)}</td>
            <td class="sep-ex bg-ex">${exitBadge(iv.exit_first)}</td>
            <td class="bg-ex">${holdBadge(iv.hold_leg)}</td>
            <td class="bg-ex remarks-td">${esc(iv.remarks||'—')}</td>
        </tr>`;
    });
    tbody+='</tbody>';

    const legend=legendBar();
    $('#ss-wrap').html(detHdr+`<div class="tbl-card"><div class="tbl-wrap"><table class="ss-tbl detail">${thead}${tbody}</table></div>${legend}</div>`);
}

// Click symbol badge in summary → jump to detail
function jumpToSym(sym){
    const sel=document.getElementById('sym-sel');
    if(sel) sel.value=sym;
    loadData();
}

// ── P&L helpers ───────────────────────────────────────────────────────────────

/**
 * Per-unit P&L cell (smaller, secondary)
 */
function pnlUnitHtml(v){
    if(v===null||v===undefined) return dim('—');
    const pos = v >= 0;
    const cls = pos ? 'c-pos' : 'c-neg';
    return `<strong class="${cls}">${pos?'+':'-'}&#8377;${nf(Math.abs(v))}</strong>`;
}

/**
 * Per-lot P&L cell (larger, primary — what you actually made/lost)
 * profit_loss_lot = profit_loss × lot_size
 */
function pnlLotHtml(vLot, lotSize){
    if(vLot===null||vLot===undefined) return dim('—');
    const pos = vLot >= 0;
    const cls = pos ? 'c-pos-lot' : 'c-neg-lot';
    const arrow = pos ? '&#9650;' : '&#9660;';
    const sub = lotSize && lotSize > 1
        ? `<span class="pnl-lot-sub">×${nInt(lotSize)} lots</span>`
        : '';
    return `<span class="${cls}">${arrow} &#8377;${nf(Math.abs(vLot))}</span>`;
}

// ── Badge helpers ─────────────────────────────────────────────────────────────

function txnBadge(t){ return t==='Buy'?'<span class="tb-buy">&#9650; BUY</span>':'<span class="tb-sell">&#9660; SELL</span>'; }

function exitBadge(v){
    if(!v||v==='—') return '<span class="ex-na">—</span>';
    if(v==='EXIT PE')   return '<span class="ex-pe">&#9660; EXIT PE</span>';
    if(v==='EXIT CE')   return '<span class="ex-ce">&#9660; EXIT CE</span>';
    if(v==='EXIT BOTH') return '<span class="ex-both">&#9660; EXIT BOTH</span>';
    if(v==='HOLD BOTH') return '<span class="ex-hold">&#9654; HOLD BOTH</span>';
    return `<span class="ex-na">${esc(v)}</span>`;
}

function holdBadge(v){
    if(!v||v==='—'||v==='NONE') return '<span class="hd-none">NONE</span>';
    if(v==='HOLD CE')  return '<span class="hd-ce">HOLD CE</span>';
    if(v==='HOLD PE')  return '<span class="hd-pe">HOLD PE</span>';
    if(v==='BOTH')     return '<span class="hd-both">HOLD BOTH</span>';
    return `<span class="hd-ce">${esc(v)}</span>`;
}

function oiBadge(sig,cond,reason){
    const t=esc((cond||'')+(reason?' | '+reason:''));
    if(!sig||sig==='N/A') return '<span class="oi-na">—</span>';
    if(sig==='BULLISH') return `<span class="oi-bull" title="${t}">&#129033; BULLISH</span>`;
    if(sig==='BEARISH') return `<span class="oi-bear" title="${t}">&#129035; BEARISH</span>`;
    return '<span class="oi-na">—</span>';
}

function strBadge(s){
    if(!s||s==='—'||s==='N/A') return '<span style="color:var(--dim);font-size:9px;">—</span>';
    if(s==='Very Strong') return '<span class="str-vs">&#128293; V.Strong</span>';
    if(s==='Strong')      return '<span class="str-s">&#9889; Strong</span>';
    if(s==='Moderate')    return '<span class="str-m">&#9733; Moderate</span>';
    return '<span class="str-w">Weak</span>';
}

function pctHtml(v){
    if(v===null||v===undefined) return dim('—');
    const cls=v>0?'pct-pos':v<0?'pct-neg':'pct-z';
    return `<strong class="${cls}">${v>0?'&#9650;':'&#9660;'}${v>0?'+':''}${v.toFixed(2)}%</strong>`;
}

function oiNum(v){ return v!==null&&v!==undefined?`<span class="c-oi">${Number(v).toLocaleString('en-IN')}</span>`:dim('—'); }
function dim(t){ return `<span class="c-dim">${t}</span>`; }
function nf(v){ return v!==null&&v!==undefined?Number(v).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}):'—'; }
function nInt(v){ return v!==null&&v!==undefined?Number(v).toLocaleString('en-IN',{maximumFractionDigits:0}):'—'; }
function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function legendBar(){
    return `<div class="legend">
        <strong>Exit Logic:</strong>
        CE OI↑ &amp; PE OI↓ → EXIT PE, HOLD CE (Bearish) &nbsp;|&nbsp;
        PE OI↑ &amp; CE OI↓ → EXIT CE, HOLD PE (Bullish) &nbsp;|&nbsp;
        Both↑ → EXIT BOTH (Range) &nbsp;|&nbsp;
        Both↓ → HOLD BOTH (Volatility Expansion)
        &nbsp;·&nbsp; <strong>OI Init</strong> = OI at 09:15 candle (fixed all day)
        &nbsp;·&nbsp; <strong>OI%</strong> = (Crnt OI − Init OI) / Init OI × 100
        &nbsp;·&nbsp; <span style="color:var(--teal);font-weight:700;">&#9650; Teal row</span> = Entry (09:15)
        &nbsp;·&nbsp; <span style="color:var(--amber);font-weight:700;">&#9660; Amber row</span> = Latest complete candle
        &nbsp;·&nbsp; <span style="color:var(--purple);font-weight:700;">&#9670; P&amp;L per lot</span> = Per-unit P&amp;L × Lot size (from ZerodhaInstrument)
        &nbsp;·&nbsp; Click any symbol badge to drill into 15-min intervals
    </div>`;
}
</script>
@endpush