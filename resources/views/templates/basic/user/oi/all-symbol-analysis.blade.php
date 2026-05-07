@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════════════
   ALL-SYMBOL OI ANALYSIS — Signals Only (BUY CE / BUY PE)
   22 columns · 8-9px font · WAIT rows hidden
═══════════════════════════════════════════════════════════ */
.aoi-page { background:#0f1117; min-height:100vh; font-family:'DM Mono',monospace,sans-serif; color:#e2e8f0; }
.aoi-page * { box-sizing:border-box; }

/* ── Header ─────────────────────────────── */
.aoi-hd {
    background: linear-gradient(120deg,#0ea5e9 0%,#6366f1 60%,#a855f7 100%);
    border-radius:12px; padding:16px 22px; margin-bottom:12px;
    box-shadow:0 0 40px rgba(99,102,241,.35);
    display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
}
.aoi-hd h4 { color:#fff; margin:0; font-size:17px; font-weight:800; letter-spacing:.3px; }
.aoi-hd p  { color:rgba(255,255,255,.72); margin:4px 0 0; font-size:9.5px; letter-spacing:.4px; }
.ab { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; border-radius:7px; border:none; cursor:pointer; font-size:10px; font-weight:700; transition:all .18s; }
.ab.sc { background:rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.28); }
.ab.pr { background:#fff; color:#6366f1; }
.ab:hover { opacity:.82; transform:translateY(-1px); }

/* ── Logic strip ────────────────────────── */
.aoi-lg {
    background:#1a1d2e; border:1px solid rgba(99,102,241,.25);
    border-radius:10px; padding:10px 14px; margin-bottom:12px;
}
.aoi-lg h6 { color:#a5b4fc; font-size:10px; font-weight:700; margin-bottom:6px; letter-spacing:.5px; }
.aoi-lg ul { font-size:8.5px; color:#94a3b8; margin:2px 0 0; padding-left:12px; line-height:1.6; }
.aoi-lg small { color:#c4b5fd; font-size:9px; font-weight:700; display:block; margin-bottom:2px; }

/* ── Stat cards ─────────────────────────── */
.aoi-st {
    background:#1a1d2e; border-radius:9px; padding:10px 14px;
    border-left:3px solid #6366f1; box-shadow:0 2px 10px rgba(0,0,0,.35);
    margin-bottom:10px;
}
.aoi-st small  { display:block; color:#64748b; font-size:8px; text-transform:uppercase; letter-spacing:.6px; margin-bottom:3px; }
.aoi-st strong { display:block; font-size:1.3rem; font-weight:800; }

/* ── Filter bar ─────────────────────────── */
.aoi-fl {
    background:#1a1d2e; border:1px solid rgba(99,102,241,.22);
    border-radius:10px; padding:12px 16px; margin-bottom:12px;
}
.aoi-fl label { color:#a5b4fc; font-size:10px; font-weight:600; display:block; margin-bottom:3px; }
.aoi-fl .form-control {
    background:#0f1117 !important; border:1px solid rgba(99,102,241,.35) !important;
    color:#e2e8f0 !important; font-size:10px; border-radius:6px; padding:5px 9px; height:30px;
}
.aoi-fl .form-control:focus { border-color:#6366f1 !important; outline:none; box-shadow:none !important; }

/* ── Card / table wrapper ───────────────── */
.aoi-cd { background:#1a1d2e; border-radius:10px; box-shadow:0 2px 16px rgba(0,0,0,.45); overflow:hidden; margin-bottom:12px; border:1px solid rgba(99,102,241,.18); }
.aoi-ch { display:flex; align-items:center; justify-content:space-between; padding:8px 14px; border-bottom:1px solid rgba(99,102,241,.18); background:#13162a; }
.aoi-ct { font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.7px; margin:0; }

/* ── Table ──────────────────────────────── */
.aoi-tw { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.aoi-t {
    width:100%; border-collapse:collapse;
    font-size:8.5px; table-layout:fixed;
    min-width:1400px;
}

.aoi-t thead th {
    background:#13162a; color:#64748b;
    font-size:7.5px; font-weight:700; text-transform:uppercase; letter-spacing:.3px;
    padding:5px 2px; border-bottom:2px solid #1e2235; text-align:center;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    position:sticky; top:0; z-index:5;
}
.th-sym { background:rgba(14,165,233,.12)  !important; color:#38bdf8 !important; border-bottom-color:#0ea5e9 !important; }
.th-ce  { background:rgba(251,191,36,.08)  !important; color:#fbbf24 !important; border-bottom-color:#d97706 !important; }
.th-pe  { background:rgba(167,139,250,.08) !important; color:#a78bfa !important; border-bottom-color:#7c3aed !important; }
.th-fl  { background:rgba(34,197,94,.07)   !important; color:#4ade80 !important; border-bottom-color:#16a34a !important; }
.th-vd  { background:rgba(239,68,68,.07)   !important; color:#f87171 !important; border-bottom-color:#dc2626 !important; }

/* Sticky cols */
.aoi-t thead th.s1 { position:sticky; left:0;     z-index:15; background:#13162a; }
.aoi-t thead th.s2 { position:sticky; left:22px;  z-index:15; background:#13162a; }
.aoi-t thead th.s3 { position:sticky; left:92px;  z-index:15; background:#13162a; }
.aoi-t thead th.s4 { position:sticky; left:148px; z-index:15; background:#13162a; }

.aoi-t tbody td {
    padding:4px 2px; border-bottom:1px solid #1e2235;
    text-align:center; background:#1a1d2e;
    vertical-align:middle; white-space:nowrap;
    overflow:hidden; text-overflow:ellipsis;
    font-size:8.5px; color:#cbd5e1;
}
.aoi-t tbody td.s1 { position:sticky; left:0;     z-index:5; background:#1a1d2e; }
.aoi-t tbody td.s2 { position:sticky; left:22px;  z-index:5; background:#1a1d2e; }
.aoi-t tbody td.s3 { position:sticky; left:92px;  z-index:5; background:#1a1d2e; }
.aoi-t tbody td.s4 { position:sticky; left:148px; z-index:5; background:#1a1d2e; }

.aoi-t tbody tr:hover td { background:#1e2235 !important; }
.aoi-t tbody tr.rce td,
.aoi-t tbody tr.rce td.s1,
.aoi-t tbody tr.rce td.s2,
.aoi-t tbody tr.rce td.s3,
.aoi-t tbody tr.rce td.s4 { background:#0d1f15 !important; }
.aoi-t tbody tr.rpe td,
.aoi-t tbody tr.rpe td.s1,
.aoi-t tbody tr.rpe td.s2,
.aoi-t tbody tr.rpe td.s3,
.aoi-t tbody tr.rpe td.s4 { background:#1c0d0d !important; }

/* OI numbers */
.oi-a { font-size:9px; font-weight:600; color:#e2e8f0; display:block; line-height:1.2; }
.oi-b { font-size:7px; color:#475569; display:block; line-height:1.1; }
.oi-dim .oi-a { color:#64748b; }

/* % */
.pp { color:#4ade80; font-weight:700; }
.pn { color:#f87171; font-weight:700; }
.pz { color:#475569; }

/* ── Micro badges ───────────────────────── */
.mk { display:inline-block; padding:2px 5px; border-radius:3px; font-size:7.5px; font-weight:700; white-space:nowrap; line-height:1.3; }

/* Signal */
.mk-ce { background:#16a34a; color:#fff; }
.mk-pe { background:#dc2626; color:#fff; }

/* Symbol */
.sym-lbl { color:#38bdf8; font-weight:800; font-size:9px; letter-spacing:.3px; }

/* Confidence */
.mk-hi  { background:#14532d; color:#bbf7d0; }
.mk-md  { background:#78350f; color:#fef3c7; }
.mk-lo  { background:#1e293b; color:#94a3b8; }
.mk-co  { background:#3b0764; color:#e9d5ff; }
.mk-ne  { background:#1e293b; color:#64748b; }
.mk-tr  { background:#4c1d95; color:#ddd6fe; }
.mk-lb  { background:#052e16; color:#4ade80; }
.mk-lbe { background:#450a0a; color:#fca5a5; }
.mk-nu  { background:#1e293b; color:#64748b; }

/* Flow */
.mk-sb  { background:#052e16; color:#4ade80; }
.mk-sb2 { background:#450a0a; color:#fca5a5; }
.mk-co2 { background:#1e3a8a; color:#93c5fd; }
.mk-re  { background:#431407; color:#fed7aa; }
.mk-tp  { background:#2e1065; color:#ddd6fe; }
.mk-mx  { background:#1e293b; color:#64748b; }

/* Spike */
.mk-sd { background:#500724; color:#fbcfe8; }
.mk-sc { background:#431407; color:#fed7aa; }
.mk-sp { background:#172554; color:#bfdbfe; }

/* Condition */
.mk-bu { background:#052e16; color:#4ade80; }
.mk-be { background:#450a0a; color:#fca5a5; }
.mk-bo { background:#2e1065; color:#ddd6fe; }
.mk-bd { background:#1c1300; color:#fde68a; }

/* Score */
.sc-p { color:#4ade80; font-weight:800; font-size:9.5px; }
.sc-n { color:#f87171; font-weight:800; font-size:9.5px; }
.sc-z { color:#475569; font-size:9px; }

/* Gap */
.gp-h { color:#4ade80; font-weight:700; }
.gp-m { color:#fbbf24; font-weight:700; }
.gp-l { color:#f87171; }

/* Cont */
.ct-a { color:#4ade80; font-weight:700; }
.ct-d { color:#fbbf24; font-weight:700; }
.ct-r { color:#f87171; font-weight:700; }
.ct-s { color:#475569; }

/* Loader */
.aoi-ld { display:flex; align-items:center; justify-content:center; flex-direction:column; padding:40px; }
.aoi-sp { width:30px; height:30px; border:3px solid #1e293b; border-top-color:#6366f1; border-radius:50%; animation:spin .75s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* Filter signal btns */
.sig-filter { display:flex; gap:5px; flex-wrap:wrap; align-items:center; }
.sf-btn { padding:4px 10px; border-radius:5px; border:1.5px solid rgba(99,102,241,.3); background:transparent; color:#94a3b8; font-size:9px; font-weight:700; cursor:pointer; transition:all .15s; }
.sf-btn.active-ce { background:#14532d; border-color:#16a34a; color:#4ade80; }
.sf-btn.active-pe { background:#450a0a; border-color:#dc2626; color:#f87171; }
.sf-btn.active-all { background:#1e3a8a; border-color:#3b82f6; color:#93c5fd; }
</style>
@endpush

<div class="aoi-page">
<section class="pt-20 pb-40">
<div class="container-fluid content-container">

{{-- HEADER --}}
<div class="aoi-hd">
    <div>
        <h4>📊 OI Signal Scanner — All Symbols</h4>
        <p>Pure OI · 3-Day Flow (T-2→T-1→T) · Only BUY CE & BUY PE signals shown · WAIT rows hidden · Score ≥±3 → Trade</p>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="{{ route('auro-oi.index') }}" class="ab sc" style="font-size:10px">AURO Only ↗</a>
    </div>
</div>

{{-- LOGIC STRIP --}}
<div class="aoi-lg">
    <h6>⚙ Signal Logic</h6>
    <div class="row">
        <div class="col-md-3"><small>📊 Base (T vs T-1)</small><ul>
            <li>CE↓+PE↑ = BULLISH · CE↑+PE↓ = BEARISH</li>
            <li>Both same dir → bigger % dominates</li></ul></div>
        <div class="col-md-3"><small>🔄 Flow (T-2→T-1→T)</small><ul>
            <li>STRONG: both sides building 2 days</li>
            <li>TRAP: large move sharply reversed</li>
            <li>CONT: 1+ side same dir 2 days</li></ul></div>
        <div class="col-md-3"><small>⚡ Score → Signal</small><ul>
            <li>Base ±2 · Flow ±3 · CONT ±1.5</li>
            <li>Gap>25% → ×1.15 · Gap>40% → ×1.3</li>
            <li>TRAP → score=0 (spike ignored)</li></ul></div>
        <div class="col-md-3"><small>⛔ No-trade veto</small><ul>
            <li>Gap &lt;10% = NO EDGE</li>
            <li>CONFLICT: base vs flow disagree</li>
            <li>TRAP → always WAIT</li></ul></div>
    </div>
</div>

{{-- STATS --}}
<div class="row mb-2">
    <div class="col-6 col-md-3"><div class="aoi-st" style="border-left-color:#6366f1">
        <small>Symbols Scanned</small><strong style="color:#6366f1" id="st-s">—</strong></div></div>
    <div class="col-6 col-md-3"><div class="aoi-st" style="border-left-color:#4ade80">
        <small>BUY CE Signals</small><strong style="color:#4ade80" id="st-c">—</strong></div></div>
    <div class="col-6 col-md-3"><div class="aoi-st" style="border-left-color:#f87171">
        <small>BUY PE Signals</small><strong style="color:#f87171" id="st-p">—</strong></div></div>
    <div class="col-6 col-md-3"><div class="aoi-st" style="border-left-color:#38bdf8">
        <small>Total Signals</small><strong style="color:#38bdf8" id="st-t">—</strong></div></div>
</div>

{{-- FILTER --}}
<div class="aoi-fl">
    <div class="row align-items-end" style="row-gap:8px">
        <div class="col-6 col-md-2">
            <label>From Date</label>
            <input type="date" id="f-from" class="form-control" value="{{ now()->subDays(7)->format('Y-m-d') }}">
        </div>
        <div class="col-6 col-md-2">
            <label>To Date</label>
            <input type="date" id="f-to" class="form-control" value="{{ now()->format('Y-m-d') }}">
        </div>
        <div class="col-6 col-md-3">
            <label>Symbol <span style="color:#475569;font-weight:400">(blank = all)</span></label>
            <select id="f-sym" class="form-control">
                <option value="">— All Symbols —</option>
                @foreach($symbols as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label>Confidence</label>
            <select id="f-conf" class="form-control">
                <option value="">All Confidence</option>
                <option value="HIGH">HIGH Only</option>
                <option value="MEDIUM">MEDIUM Only</option>
                <option value="LOW">LOW Only</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex align-items-end" style="gap:5px;padding-bottom:1px">
            <button class="ab pr flex-fill" onclick="run()"><i class="fas fa-search"></i> Scan</button>
            <button class="ab sc" onclick="resetAll()" title="Reset"><i class="fas fa-undo"></i></button>
        </div>
    </div>
    {{-- Signal type quick-filter --}}
    <div class="sig-filter mt-2">
        <span style="color:#64748b;font-size:9px;font-weight:600">SHOW:</span>
        <button class="sf-btn active-all" onclick="setSig('all',this)" id="sfAll">ALL SIGNALS</button>
        <button class="sf-btn" onclick="setSig('BUY CE',this)" id="sfCE">▲ BUY CE ONLY</button>
        <button class="sf-btn" onclick="setSig('BUY PE',this)" id="sfPE">▼ BUY PE ONLY</button>
    </div>
</div>

{{-- TABLE --}}
<div class="aoi-cd">
    <div class="aoi-ch">
        <span class="aoi-ct">OI Signal Scanner — WAIT rows excluded</span>
        <small style="color:#475569;font-size:8.5px" id="cnt">—</small>
    </div>
    <div id="ld" style="display:none" class="aoi-ld">
        <div class="aoi-sp"></div>
        <div style="color:#6366f1;font-size:10px;font-weight:700;margin-top:8px">Scanning all symbols...</div>
    </div>
    <div class="aoi-tw">
        <table class="aoi-t">
            <colgroup>
                <col style="width:22px"><col style="width:70px"><col style="width:56px"><col style="width:50px">
                <col style="width:62px"><col style="width:54px"><col style="width:54px"><col style="width:42px"><col style="width:42px">
                <col style="width:62px"><col style="width:54px"><col style="width:54px"><col style="width:42px"><col style="width:42px">
                <col style="width:50px"><col style="width:34px"><col style="width:64px"><col style="width:44px"><col style="width:46px">
                <col style="width:38px"><col style="width:60px"><col style="width:50px">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="s1">#</th>
                    <th rowspan="2" class="s2 th-sym">Date</th>
                    <th rowspan="2" class="s3 th-sym">Symbol</th>
                    <th rowspan="2" class="s4">Sig</th>
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
                <tr><td colspan="22" style="padding:40px;text-align:center">
                    <div style="color:#475569;font-size:9px">
                        <i class="fas fa-radar" style="font-size:1.6rem;opacity:.2;display:block;margin-bottom:8px;color:#6366f1"></i>
                        Select date range and click <strong style="color:#a5b4fc">Scan</strong> to find signals
                    </div>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

</div></section></div>
@endsection

@push('script')
<script>
const API = '{{ route("oi-scanner.analyze") }}';
let all = [];
let sigFilter = 'all';

/* ── Formatters ─────────────────────────────────────── */
function fmtN(v) {
    const n=parseInt(v)||0;
    if(!n) return '0';
    if(n>=1e7) return (n/1e7).toFixed(2)+'Cr';
    if(n>=1e5) return (n/1e5).toFixed(2)+'L';
    if(n>=1e3) return (n/1e3).toFixed(1)+'K';
    return n.toString();
}
function pct(v) {
    const n=parseFloat(v)||0,s=n>0?'+':'',c=n>0?'pp':n<0?'pn':'pz';
    return `<span class="${c}">${s}${n.toFixed(1)}%</span>`;
}
function oiBlock(v,dim=false) {
    const n=parseInt(v)||0;
    const cls=dim?'oi-dim':'';
    return `<div class="${cls}"><span class="oi-a">${fmtN(n)}</span><span class="oi-b">${n.toLocaleString('en-IN')}</span></div>`;
}
function sig(s) {
    if(s==='BUY CE') return '<span class="mk mk-ce">▲ BUY CE</span>';
    if(s==='BUY PE') return '<span class="mk mk-pe">▼ BUY PE</span>';
    return '<span style="color:#475569">—</span>';
}
function rowCls(s) { return s==='BUY CE'?'rce':s==='BUY PE'?'rpe':''; }
function confBadge(c) {
    const m={
        'HIGH':     '<span class="mk mk-hi">🔥 HIGH</span>',
        'MEDIUM':   '<span class="mk mk-md">⚡ MED</span>',
        'LOW':      '<span class="mk mk-lo">LO</span>',
        'CONFLICT': '<span class="mk mk-co">⚠CONF</span>',
        'NO EDGE':  '<span class="mk mk-ne">NOEDGE</span>',
        'TRAP':     '<span class="mk mk-tr">TRAP</span>',
        'LEAN BULL':'<span class="mk mk-lb">↗BULL</span>',
        'LEAN BEAR':'<span class="mk mk-lbe">↘BEAR</span>',
        'NEUTRAL':  '<span class="mk mk-nu">NEUT</span>',
    };
    return m[c]||`<span class="mk mk-nu">${c||'—'}</span>`;
}
function flowBadge(f) {
    const m={
        'STRONG_BULL': '<span class="mk mk-sb">🟢 SBUL</span>',
        'STRONG_BEAR': '<span class="mk mk-sb2">🔴 SBEA</span>',
        'CONTINUATION':'<span class="mk mk-co2">→CONT</span>',
        'REVERSAL':    '<span class="mk mk-re">↩REVR</span>',
        'TRAP':        '<span class="mk mk-tp">⚠TRAP</span>',
        'MIXED':       '<span class="mk mk-mx">~MIX</span>',
    };
    return m[f]||'<span class="mk mk-mx">—</span>';
}
function spikeBadge(s) {
    const m={
        'DUAL':'<span class="mk mk-sd">⚡⚡DUAL</span>',
        'CE':  '<span class="mk mk-sc">⚡CE</span>',
        'PE':  '<span class="mk mk-sp">⚡PE</span>',
        'NONE':'<span style="color:#334155;font-size:8px">—</span>',
    };
    return m[s]||'<span style="color:#334155;font-size:8px">—</span>';
}
function condBadge(c) {
    const m={
        'CE↓ PE↑':'<span class="mk mk-bu">CE↓PE↑</span>',
        'CE↑ PE↓':'<span class="mk mk-be">CE↑PE↓</span>',
        'Both↑':  '<span class="mk mk-bo">Both↑</span>',
        'Both↓':  '<span class="mk mk-bd">Both↓</span>',
    };
    return m[c]||`<span style="color:#475569;font-size:8px">${c||'—'}</span>`;
}
function contBadge(s) {
    if(!s||s==='—') return '<span class="ct-s">—</span>';
    const c=s.includes('Accel')?'ct-a':s.includes('Decel')?'ct-d':s.includes('Rev')?'ct-r':'ct-s';
    return `<span class="${c}">${s}</span>`;
}
function scoreBadge(v) {
    const n=parseFloat(v)||0,s=n>0?'+':'';
    const c=n>=3?'sc-p':n<=-3?'sc-n':'sc-z';
    return `<span class="${c}">${s}${n.toFixed(1)}</span>`;
}
function gapBadge(v) {
    const n=parseFloat(v)||0;
    const c=n>25?'gp-h':n>10?'gp-m':'gp-l';
    return `<span class="${c}">${n.toFixed(1)}</span>`;
}

/* ── Main ───────────────────────────────────────────── */
function run() {
    const from=document.getElementById('f-from').value;
    const to=document.getElementById('f-to').value;
    if(!from||!to){alert('Select both dates');return;}
    const sym=document.getElementById('f-sym').value;
    let url=`${API}?from_date=${from}&to_date=${to}`;
    if(sym) url+=`&symbol=${encodeURIComponent(sym)}`;

    document.getElementById('ld').style.display='flex';
    document.getElementById('tb').innerHTML='';
    document.getElementById('cnt').textContent='Scanning…';

    fetch(url)
        .then(r=>r.json())
        .then(res=>{
            document.getElementById('ld').style.display='none';
            if(!res.success){empty(res.message||'No data');return;}
            all=res.data||[];
            applyFilters();
            updateStats(res.summary);
        })
        .catch(e=>{
            document.getElementById('ld').style.display='none';
            empty('Error: '+e.message);
        });
}

function applyFilters() {
    const conf=document.getElementById('f-conf').value;
    let d=[...all];
    if(sigFilter!=='all') d=d.filter(r=>r.signal===sigFilter);
    if(conf) d=d.filter(r=>r.confidence===conf);
    render(d);
    document.getElementById('cnt').textContent=d.length+' signal'+(d.length!==1?'s':'');
}

function render(data) {
    if(!data||!data.length){empty('No signals match — adjust filters or date range');return;}
    let h='';
    data.forEach((d,i)=>{
        h+=`<tr class="${rowCls(d.signal)}">
<td class="s1" style="color:#334155">${i+1}</td>
<td class="s2" style="font-weight:700;color:#38bdf8">${d.date}</td>
<td class="s3"><span class="sym-lbl">${d.symbol}</span></td>
<td class="s4">${sig(d.signal)}</td>
<td>${oiBlock(d.ce_oi_t)}</td>
<td>${oiBlock(d.ce_oi_t1,true)}</td>
<td>${oiBlock(d.ce_oi_t2,true)}</td>
<td>${pct(d.ce_pct_t)}</td>
<td style="opacity:.65">${pct(d.ce_pct_t1)}</td>
<td>${oiBlock(d.pe_oi_t)}</td>
<td>${oiBlock(d.pe_oi_t1,true)}</td>
<td>${oiBlock(d.pe_oi_t2,true)}</td>
<td>${pct(d.pe_pct_t)}</td>
<td style="opacity:.65">${pct(d.pe_pct_t1)}</td>
<td>${condBadge(d.condition)}</td>
<td>${gapBadge(d.oi_diff)}</td>
<td>${flowBadge(d.flow_signal)}</td>
<td>${spikeBadge(d.spike)}</td>
<td>${contBadge(d.ce_cont)}</td>
<td>${scoreBadge(d.score)}</td>
<td>${confBadge(d.confidence)}</td>
<td style="font-size:7.5px;color:#334155">${d.expiry||'—'}</td>
</tr>`;
    });
    document.getElementById('tb').innerHTML=h;
}

function updateStats(s) {
    if(!s)return;
    document.getElementById('st-s').textContent=s.symbols||'—';
    document.getElementById('st-c').textContent=s.buy_ce||0;
    document.getElementById('st-p').textContent=s.buy_pe||0;
    document.getElementById('st-t').textContent=s.total||0;
}

function empty(m) {
    document.getElementById('tb').innerHTML=
        `<tr><td colspan="22" style="padding:40px;text-align:center;color:#475569">
        <i class="fas fa-search" style="font-size:1.6rem;opacity:.2;display:block;margin-bottom:8px;color:#6366f1"></i>${m}
        </td></tr>`;
}

function setSig(v,btn) {
    sigFilter=v;
    document.querySelectorAll('.sf-btn').forEach(b=>{
        b.className='sf-btn';
    });
    if(v==='all')      btn.classList.add('active-all');
    else if(v==='BUY CE') btn.classList.add('active-ce');
    else               btn.classList.add('active-pe');
    if(all.length) applyFilters();
}

function resetAll() {
    document.getElementById('f-from').value='{{ now()->subDays(7)->format("Y-m-d") }}';
    document.getElementById('f-to').value='{{ now()->format("Y-m-d") }}';
    document.getElementById('f-sym').value='';
    document.getElementById('f-conf').value='';
    sigFilter='all';
    document.querySelectorAll('.sf-btn').forEach(b=>b.className='sf-btn');
    document.getElementById('sfAll').classList.add('active-all');
    all=[];
    empty('Click Scan to find signals');
    ['st-s','st-c','st-p','st-t'].forEach(id=>document.getElementById(id).textContent='—');
    document.getElementById('cnt').textContent='—';
}

document.getElementById('f-conf').addEventListener('change',applyFilters);
</script>
@endpush