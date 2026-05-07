@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════════════
   AUROPHARMA OI ANALYSIS — v3 Final
   All 21 columns on one screen. 8-9px font. Micro badges.
═══════════════════════════════════════════════════════════ */
.aoi-page { background:#f4f6fb; min-height:100vh; font-family:-apple-system,BlinkMacSystemFont,'DM Sans',sans-serif; color:#1e293b; }
.aoi-page * { box-sizing:border-box; }

/* Header */
.aoi-hd { background:linear-gradient(135deg,#667eea,#764ba2); border-radius:12px; padding:14px 20px; margin-bottom:12px; box-shadow:0 4px 20px rgba(102,126,234,.28); }
.aoi-hd h4 { color:#fff; margin:0 0 2px; font-size:16px; font-weight:700; }
.aoi-hd p  { color:rgba(255,255,255,.75); margin:0; font-size:10px; }

/* Logic */
.aoi-lg { background:linear-gradient(135deg,#667eea,#764ba2); border-radius:10px; padding:10px 14px; margin-bottom:12px; }
.aoi-lg h6 { color:#fff; font-size:10px; font-weight:700; margin-bottom:5px; }
.aoi-lg ul { font-size:8.5px; color:rgba(255,255,255,.82); margin:2px 0 0; padding-left:11px; line-height:1.55; }
.aoi-lg small { color:#fff; font-size:9.5px; font-weight:700; display:block; margin-bottom:2px; }

/* Stats */
.aoi-st { background:#fff; border-radius:9px; padding:9px 12px; border-left:3px solid #667eea; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:10px; }
.aoi-st small  { display:block; color:#64748b; font-size:8.5px; text-transform:uppercase; letter-spacing:.5px; margin-bottom:2px; }
.aoi-st strong { display:block; font-size:1.25rem; font-weight:700; color:#1e293b; }

/* Filter */
.aoi-fl { background:linear-gradient(135deg,#667eea,#764ba2); border-radius:10px; padding:12px 16px; margin-bottom:12px; box-shadow:0 3px 15px rgba(102,126,234,.25); }
.aoi-fl label { color:#fff !important; font-size:10px; font-weight:600; display:block; margin-bottom:3px; }
.aoi-fl .form-control { background:rgba(255,255,255,.93) !important; border:1.5px solid rgba(255,255,255,.4) !important; color:#1e293b !important; font-size:10px; border-radius:6px; padding:4px 8px; height:30px; }
.ab { display:inline-flex; align-items:center; gap:4px; padding:5px 12px; border-radius:6px; border:none; cursor:pointer; font-size:10px; font-weight:600; transition:all .18s; }
.ab.pr { background:#fff; color:#667eea; }
.ab.sc { background:rgba(255,255,255,.15); color:#fff; border:1px solid rgba(255,255,255,.38); }
.ab:hover { opacity:.86; transform:translateY(-1px); }

/* Card */
.aoi-cd { background:#fff; border-radius:10px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow:hidden; margin-bottom:12px; }
.aoi-ch { display:flex; align-items:center; justify-content:space-between; padding:7px 12px; border-bottom:1px solid #f1f5f9; background:#fafbff; }
.aoi-ct { font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.6px; margin:0; }

/* ── TABLE ─────────────────────────────────────────────────
   Target: fit ~21 columns in 1300-1400px viewport
   Each col width carefully chosen so sum ≈ 1350px
*/
.aoi-tw { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.aoi-t {
    width:100%;
    border-collapse:collapse;
    font-size:8.5px;
    table-layout:fixed;
    min-width:1340px;
}

/* Column widths — total = 1340px */
.aoi-t colgroup col { }
.col-n  { width:24px; }   /* # */
.col-d  { width:70px; }   /* Date */
.col-sg { width:46px; }   /* Signal */
.col-oi { width:58px; }   /* OI Today */
.col-o2 { width:50px; }   /* OI T-1 / T-2 */
.col-pc { width:40px; }   /* % change */
.col-cn { width:48px; }   /* Condition */
.col-gp { width:32px; }   /* Gap */
.col-fw { width:60px; }   /* Flow */
.col-sp { width:42px; }   /* Spike */
.col-ct { width:44px; }   /* CE Trend */
.col-sc { width:36px; }   /* Score */
.col-cf { width:58px; }   /* Confidence */
.col-ex { width:48px; }   /* Expiry */

/* col count:
   1(n) + 1(d) + 1(sg) + 3(ce-oi) + 2(ce-pct) + 3(pe-oi) + 2(pe-pct) + 1(cond) + 1(gap) + 1(flow) + 1(spike) + 1(cetrend) + 1(score) + 1(conf) + 1(expiry)
   = 21 cols
   24+70+46 + (58+50+50)+(40+40) + (58+50+50)+(40+40) + 48+32+60+42+44 + 36+58+48
   = 140 + 158+80 + 158+80 + 226 + 142
   = 140+238+238+226+142 = 984... need bigger cols
   Let me recount with actual widths:
   24+70+46 = 140
   58+50+50+40+40 = 238 (CE group)
   58+50+50+40+40 = 238 (PE group)
   48+32+60+42+44 = 226 (Flow group)
   36+58+48 = 142 (Verdict)
   Total = 984. Spread remaining 356px across OI cols to make them readable.
   Set col-oi=70, col-o2=60, col-pc=44, col-oi * 2 = 140+120+120+120+88+88 = 676
   Actually just set min-width:1300px and let it auto
*/

.aoi-t thead th {
    background:#f8fafc; color:#64748b;
    font-size:7.5px; font-weight:700; text-transform:uppercase; letter-spacing:.3px;
    padding:5px 2px; border-bottom:2px solid #e2e8f0; text-align:center;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    position:sticky; top:0; z-index:5;
}
.th-ce   { background:rgba(251,191,36,.1) !important; color:#92400e !important; border-bottom-color:#fbbf24 !important; }
.th-pe   { background:rgba(167,139,250,.1) !important; color:#6d28d9 !important; border-bottom-color:#a78bfa !important; }
.th-fl   { background:rgba(34,197,94,.08)  !important; color:#166534 !important; border-bottom-color:#4ade80 !important; }
.th-vd   { background:rgba(239,68,68,.08)  !important; color:#991b1b !important; border-bottom-color:#f87171 !important; }

/* Sticky first 3 header cells */
.aoi-t thead th.s1 { position:sticky; left:0;    z-index:15; background:#f8fafc; }
.aoi-t thead th.s2 { position:sticky; left:24px; z-index:15; background:#f8fafc; }
.aoi-t thead th.s3 { position:sticky; left:94px; z-index:15; background:#f8fafc; }

.aoi-t tbody td {
    padding:4px 2px; border-bottom:1px solid #f1f5f9;
    text-align:center; color:#1e293b; background:#fff;
    vertical-align:middle; white-space:nowrap;
    overflow:hidden; text-overflow:ellipsis;
    font-size:8.5px;
}
.aoi-t tbody td.s1 { position:sticky; left:0;    z-index:5; background:#fff; }
.aoi-t tbody td.s2 { position:sticky; left:24px; z-index:5; background:#fff; }
.aoi-t tbody td.s3 { position:sticky; left:94px; z-index:5; background:#fff; }

.aoi-t tbody tr:hover td { background:#f8fafc !important; }
.aoi-t tbody tr.rce td,
.aoi-t tbody tr.rce td.s1,
.aoi-t tbody tr.rce td.s2,
.aoi-t tbody tr.rce td.s3 { background:#f0fdf4 !important; }
.aoi-t tbody tr.rpe td,
.aoi-t tbody tr.rpe td.s1,
.aoi-t tbody tr.rpe td.s2,
.aoi-t tbody tr.rpe td.s3 { background:#fff5f5 !important; }

/* OI numbers — two line display */
.oi-a { font-size:9px; font-weight:600; color:#1e293b; display:block; line-height:1.2; }
.oi-b { font-size:7px; color:#94a3b8; display:block; line-height:1.1; }
.oi-dim { opacity:.65; }

/* % cells */
.pp { color:#16a34a; font-weight:700; }
.pn { color:#dc2626; font-weight:700; }
.pz { color:#94a3b8; }

/* ── Micro badges — uniform height 14px, font 7.5px ──────── */
.mk { display:inline-block; padding:2px 4px; border-radius:3px; font-size:7.5px; font-weight:700; white-space:nowrap; line-height:1.3; }

/* Signal */
.mk-ce { background:#16a34a; color:#fff; }
.mk-pe { background:#dc2626; color:#fff; }
.mk-wt { background:#d97706; color:#fff; }

/* Confidence */
.mk-hi  { background:#14532d; color:#fff; }
.mk-md  { background:#d97706; color:#fff; }
.mk-lo  { background:#cbd5e1; color:#334155; }
.mk-co  { background:#581c87; color:#fff; }
.mk-ne  { background:#6b7280; color:#fff; }
.mk-tr  { background:#7c3aed; color:#fff; }
.mk-lb  { background:#bbf7d0; color:#14532d; }
.mk-lbe { background:#fecaca; color:#7f1d1d; }
.mk-nu  { background:#f1f5f9; color:#475569; }

/* Flow */
.mk-sb  { background:#14532d; color:#fff; }
.mk-sb2 { background:#7f1d1d; color:#fff; }
.mk-co2 { background:#1e40af; color:#fff; }
.mk-re  { background:#c2410c; color:#fff; }
.mk-tp  { background:#6b21a8; color:#fff; }
.mk-mx  { background:#e2e8f0; color:#475569; font-weight:600; }

/* Spike */
.mk-sd { background:#831843; color:#fff; }
.mk-sc { background:#9a3412; color:#fff; }
.mk-sp { background:#1e3a8a; color:#fff; }

/* OI condition */
.mk-bu { background:#dcfce7; color:#14532d; }
.mk-be { background:#fee2e2; color:#7f1d1d; }
.mk-bo { background:#ede9fe; color:#4c1d95; }
.mk-bd { background:#fff7ed; color:#9a3412; }

/* Score */
.sc-p { color:#16a34a; font-weight:800; font-size:9.5px; }
.sc-n { color:#dc2626; font-weight:800; font-size:9.5px; }
.sc-z { color:#94a3b8; font-size:9px; }

/* Gap */
.gp-h { color:#16a34a; font-weight:700; }
.gp-m { color:#d97706; font-weight:700; }
.gp-l { color:#dc2626; }

/* Cont */
.ct-a { color:#16a34a; font-weight:700; }
.ct-d { color:#d97706; font-weight:700; }
.ct-r { color:#dc2626; font-weight:700; }
.ct-s { color:#94a3b8; }

/* Loader */
.aoi-ld { display:flex; align-items:center; justify-content:center; flex-direction:column; padding:35px; }
.aoi-sp { width:28px; height:28px; border:3px solid #e2e8f0; border-top-color:#667eea; border-radius:50%; animation:spin .8s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
@endpush

@section('content')
<div class="aoi-page">
<section class="pt-20 pb-40">
<div class="container-fluid content-container">

{{-- HEADER --}}
<div class="aoi-hd d-flex justify-content-between align-items-center flex-wrap" style="gap:8px">
    <div>
        <h4>Auropharma OI Analysis</h4>
        <p>Pure OI · 3-Day Flow (T-2→T-1→T) · CE% & PE% each day · Score ≥±3 → Signal · No price</p>
    </div>
    <a href="{{ route('oiiv-auto.pece-analysis') }}" class="ab sc" style="font-size:10px">← All Stocks</a>
</div>

{{-- LOGIC STRIP --}}
<div class="aoi-lg">
    <h6><i class="fas fa-info-circle"></i> Signal construction</h6>
    <div class="row">
        <div class="col-md-3"><small>📊 Base (T vs T-1)</small><ul>
            <li>CE↓+PE↑ = BULLISH (unwind+build)</li>
            <li>CE↑+PE↓ = BEARISH (build+unwind)</li>
            <li>Both same dir → bigger % wins</li>
        </ul></div>
        <div class="col-md-3"><small>🔄 Flow (T-2→T-1→T)</small><ul>
            <li>STRONG: both sides, both days</li>
            <li>TRAP: abs T-1>25% reversed >15%</li>
            <li>CONT: 1+ side same dir 2 days</li>
            <li>REVERSAL: both sides flip today</li>
        </ul></div>
        <div class="col-md-3"><small>⚡ Score → Signal</small><ul>
            <li>Base ±2, Flow ±3 (CONT ±1.5)</li>
            <li>Gap>25% → ×1.15 · Gap>40% → ×1.3</li>
            <li>TRAP → score=0 (spike ignored)</li>
            <li>Score ≥ +3 → BUY CE · ≤ -3 → BUY PE</li>
        </ul></div>
        <div class="col-md-3"><small>⚠ Veto / No trade</small><ul>
            <li>Gap &lt;10% = NO EDGE (no info)</li>
            <li>CONFLICT: base opposes flow</li>
            <li>TRAP flow → WAIT regardless of score</li>
            <li>CE/PE spike if abs%>35</li>
        </ul></div>
    </div>
</div>

{{-- STATS --}}
<div class="row mb-2">
    <div class="col-6 col-md-3"><div class="aoi-st" style="border-left-color:#667eea">
        <small>Total Days</small><strong style="color:#667eea" id="st-t">—</strong></div></div>
    <div class="col-6 col-md-3"><div class="aoi-st" style="border-left-color:#16a34a">
        <small>BUY CE</small><strong style="color:#16a34a" id="st-c">—</strong></div></div>
    <div class="col-6 col-md-3"><div class="aoi-st" style="border-left-color:#dc2626">
        <small>BUY PE</small><strong style="color:#dc2626" id="st-p">—</strong></div></div>
    <div class="col-6 col-md-3"><div class="aoi-st" style="border-left-color:#d97706">
        <small>Wait / No Edge</small><strong style="color:#d97706" id="st-w">—</strong></div></div>
</div>

{{-- FILTER --}}
<div class="aoi-fl">
    <div class="row align-items-end" style="gap:0">
        <div class="col-md-3"><label>From Date</label>
            <input type="date" id="f-from" class="form-control" value="{{ now()->subDays(30)->format('Y-m-d') }}"></div>
        <div class="col-md-3"><label>To Date</label>
            <input type="date" id="f-to" class="form-control" value="{{ now()->format('Y-m-d') }}"></div>
        <div class="col-md-3"><label>Filter</label>
            <select id="f-sig" class="form-control">
                <option value="">All Signals</option>
                <option value="BUY CE">BUY CE Only</option>
                <option value="BUY PE">BUY PE Only</option>
                <option value="WAIT">WAIT Only</option>
            </select></div>
        <div class="col-md-3 d-flex" style="gap:6px;padding-bottom:1px">
            <button class="ab pr flex-fill" onclick="run()"><i class="fas fa-search"></i> Analyse</button>
            <button class="ab sc" onclick="reset()"><i class="fas fa-undo"></i></button>
        </div>
    </div>
</div>

{{-- TABLE --}}
<div class="aoi-cd">
    <div class="aoi-ch">
        <span class="aoi-ct">Auropharma OI Analysis — every column filled</span>
        <small style="color:#94a3b8;font-size:8.5px" id="cnt">—</small>
    </div>
    <div id="ld" style="display:none" class="aoi-ld">
        <div class="aoi-sp"></div>
        <div style="color:#667eea;font-size:10px;font-weight:600;margin-top:7px">Loading OI data...</div>
    </div>
    <div class="aoi-tw">
        <table class="aoi-t">
            <colgroup>
                <col class="col-n"><col class="col-d"><col class="col-sg">
                <col class="col-oi"><col class="col-o2"><col class="col-o2"><col class="col-pc"><col class="col-pc">
                <col class="col-oi"><col class="col-o2"><col class="col-o2"><col class="col-pc"><col class="col-pc">
                <col class="col-cn"><col class="col-gp"><col class="col-fw"><col class="col-sp"><col class="col-ct">
                <col class="col-sc"><col class="col-cf"><col class="col-ex">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="s1">#</th>
                    <th rowspan="2" class="s2">Date</th>
                    <th rowspan="2" class="s3">Sig</th>
                    <th class="th-ce" colspan="5">CE Open Interest</th>
                    <th class="th-pe" colspan="5">PE Open Interest</th>
                    <th class="th-fl" colspan="5">3-Day Flow Engine</th>
                    <th class="th-vd" colspan="3">Verdict</th>
                </tr>
                <tr>
                    <th class="th-ce">Today<br>14:45</th>
                    <th class="th-ce">T-1<br>15:00</th>
                    <th class="th-ce">T-2<br>15:00</th>
                    <th class="th-ce">CE%<br>T↔T-1</th>
                    <th class="th-ce">CE%<br>T-1↔T-2</th>
                    <th class="th-pe">Today<br>14:45</th>
                    <th class="th-pe">T-1<br>15:00</th>
                    <th class="th-pe">T-2<br>15:00</th>
                    <th class="th-pe">PE%<br>T↔T-1</th>
                    <th class="th-pe">PE%<br>T-1↔T-2</th>
                    <th class="th-fl">OI<br>Cond</th>
                    <th class="th-fl">Gap<br>|Δ%|</th>
                    <th class="th-fl">Flow<br>T-2→T</th>
                    <th class="th-fl">Spike<br>CE/PE</th>
                    <th class="th-fl">CE<br>Trend</th>
                    <th class="th-vd">Score</th>
                    <th class="th-vd">Conf</th>
                    <th class="th-vd">Expiry</th>
                </tr>
            </thead>
            <tbody id="tb">
                <tr><td colspan="21" class="aoi-ld" style="padding:30px">
                    <i class="fas fa-chart-area" style="font-size:1.6rem;opacity:.25;display:block;margin-bottom:7px;color:#667eea"></i>
                    <span style="color:#94a3b8">Click <strong>Analyse</strong> to load</span>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

</div></section></div>
@endsection

@push('script')
<script>
const API = '{{ route("auro-oi.analyze") }}';
let all = [];

/* ── Formatters ─────────────────────────────────────── */
function fmtN(v) {
    const n = parseInt(v)||0;
    if (!n) return '0';
    if (n>=1e6) return (n/1e6).toFixed(2)+'M';
    if (n>=1e3) return (n/1e3).toFixed(1)+'K';
    return n.toString();
}
function pct(v) {
    const n=parseFloat(v)||0, s=n>0?'+':'', c=n>0?'pp':n<0?'pn':'pz';
    return `<span class="${c}">${s}${n.toFixed(1)}%</span>`;
}
function oi(v) {
    const n=parseInt(v)||0;
    return `<span class="oi-a">${fmtN(n)}</span><span class="oi-b">${n.toLocaleString('en-IN')}</span>`;
}
function oiDim(v) {
    const n=parseInt(v)||0;
    return `<span class="oi-a oi-dim">${fmtN(n)}</span><span class="oi-b">${n.toLocaleString('en-IN')}</span>`;
}
function sig(s) {
    if(s==='BUY CE') return '<span class="mk mk-ce">▲CE</span>';
    if(s==='BUY PE') return '<span class="mk mk-pe">▼PE</span>';
    return '<span class="mk mk-wt">⏸WT</span>';
}
function cls(s) { return s==='BUY CE'?'rce':s==='BUY PE'?'rpe':''; }
function conf(c) {
    const m={
        'HIGH':     '<span class="mk mk-hi">🔥HI</span>',
        'MEDIUM':   '<span class="mk mk-md">⚡MD</span>',
        'LOW':      '<span class="mk mk-lo">LO</span>',
        'CONFLICT': '<span class="mk mk-co">⚠CONF</span>',
        'NO EDGE':  '<span class="mk mk-ne">NOEDGE</span>',
        'TRAP':     '<span class="mk mk-tr">TRAP</span>',
        'LEAN BULL':'<span class="mk mk-lb">↗BULL</span>',
        'LEAN BEAR':'<span class="mk mk-lbe">↘BEAR</span>',
        'NEUTRAL':  '<span class="mk mk-nu">NEUT</span>',
    };
    return m[c]||`<span class="mk mk-nu">${c||'NEUT'}</span>`;
}
function flow(f) {
    const m={
        'STRONG_BULL': '<span class="mk mk-sb">🟢SBUL</span>',
        'STRONG_BEAR': '<span class="mk mk-sb2">🔴SBEA</span>',
        'CONTINUATION':'<span class="mk mk-co2">→CONT</span>',
        'REVERSAL':    '<span class="mk mk-re">↩REVR</span>',
        'TRAP':        '<span class="mk mk-tp">⚠TRAP</span>',
        'MIXED':       '<span class="mk mk-mx">~MIX</span>',
    };
    return m[f]||'<span class="mk mk-mx">—</span>';
}
function spike(s) {
    const m={
        'DUAL':'<span class="mk mk-sd">⚡⚡</span>',
        'CE':  '<span class="mk mk-sc">⚡CE</span>',
        'PE':  '<span class="mk mk-sp">⚡PE</span>',
        'NONE':'<span style="color:#94a3b8;font-size:8px">—</span>',
    };
    return m[s]||'<span style="color:#94a3b8;font-size:8px">—</span>';
}
function cond(c) {
    const m={
        'CE↓ PE↑':'<span class="mk mk-bu">CE↓PE↑</span>',
        'CE↑ PE↓':'<span class="mk mk-be">CE↑PE↓</span>',
        'Both↑':  '<span class="mk mk-bo">Both↑</span>',
        'Both↓':  '<span class="mk mk-bd">Both↓</span>',
    };
    return m[c]||`<span style="color:#94a3b8;font-size:8px">${c||'—'}</span>`;
}
function cont(s) {
    if(!s||s==='—') return '<span class="ct-s">—</span>';
    const c=s.includes('Accel')?'ct-a':s.includes('Decel')?'ct-d':s.includes('Rev')?'ct-r':'ct-s';
    return `<span class="${c}">${s}</span>`;
}
function score(v) {
    const n=parseFloat(v)||0, s=n>0?'+':'';
    const c=n>=3?'sc-p':n<=-3?'sc-n':'sc-z';
    return `<span class="${c}">${s}${n.toFixed(1)}</span>`;
}
function gap(v) {
    const n=parseFloat(v)||0;
    const c=n>25?'gp-h':n>10?'gp-m':'gp-l';
    return `<span class="${c}">${n.toFixed(1)}</span>`;
}

/* ── Run ─────────────────────────────────────────────── */
function run() {
    const from=document.getElementById('f-from').value;
    const to=document.getElementById('f-to').value;
    if(!from||!to){alert('Select both dates');return;}
    document.getElementById('ld').style.display='flex';
    document.getElementById('tb').innerHTML='';
    fetch(`${API}?from_date=${from}&to_date=${to}`)
        .then(r=>r.json()).then(res=>{
            document.getElementById('ld').style.display='none';
            if(!res.success){empty(res.message||'No data');return;}
            all=res.data||[];
            filter();
            stats(res.summary);
        }).catch(e=>{document.getElementById('ld').style.display='none';empty('Error: '+e.message);});
}

function filter() {
    const flt=document.getElementById('f-sig').value;
    const d=flt?all.filter(r=>r.signal===flt):all;
    render(d);
    document.getElementById('cnt').textContent=d.length+' records';
}

function render(data) {
    if(!data||!data.length){empty('No records match');return;}
    let h='';
    data.forEach((d,i)=>{
        h+=`<tr class="${cls(d.signal)}">
<td class="s1" style="color:#94a3b8">${i+1}</td>
<td class="s2" style="font-weight:700;color:#667eea">${d.date}</td>
<td class="s3">${sig(d.signal)}</td>
<td>${oi(d.ce_oi_t)}</td>
<td>${oiDim(d.ce_oi_t1)}</td>
<td>${oiDim(d.ce_oi_t2)}</td>
<td>${pct(d.ce_pct_t)}</td>
<td style="opacity:.75">${pct(d.ce_pct_t1)}</td>
<td>${oi(d.pe_oi_t)}</td>
<td>${oiDim(d.pe_oi_t1)}</td>
<td>${oiDim(d.pe_oi_t2)}</td>
<td>${pct(d.pe_pct_t)}</td>
<td style="opacity:.75">${pct(d.pe_pct_t1)}</td>
<td>${cond(d.condition)}</td>
<td>${gap(d.oi_diff)}</td>
<td>${flow(d.flow_signal)}</td>
<td>${spike(d.spike)}</td>
<td>${cont(d.ce_cont)}</td>
<td>${score(d.score)}</td>
<td>${conf(d.confidence)}</td>
<td style="font-size:7.5px;color:#64748b">${d.expiry||'—'}</td>
</tr>`;
    });
    document.getElementById('tb').innerHTML=h;
}

function stats(s) {
    if(!s)return;
    document.getElementById('st-t').textContent=s.total||0;
    document.getElementById('st-c').textContent=s.buy_ce||0;
    document.getElementById('st-p').textContent=s.buy_pe||0;
    document.getElementById('st-w').textContent=s.wait||0;
}

function empty(m) {
    document.getElementById('tb').innerHTML=
        `<tr><td colspan="21" style="padding:35px;text-align:center;color:#94a3b8">
        <i class="fas fa-info-circle" style="font-size:1.5rem;opacity:.3;display:block;margin-bottom:7px;color:#667eea"></i>${m}
        </td></tr>`;
}

function reset() {
    document.getElementById('f-from').value='{{ now()->subDays(30)->format("Y-m-d") }}';
    document.getElementById('f-to').value='{{ now()->format("Y-m-d") }}';
    document.getElementById('f-sig').value='';
    all=[];
    empty('Click Analyse to load data');
    ['st-t','st-c','st-p','st-w'].forEach(id=>document.getElementById(id).textContent='—');
    document.getElementById('cnt').textContent='—';
}

document.getElementById('f-sig').addEventListener('change',filter);
document.addEventListener('DOMContentLoaded',run);
</script>
@endpush