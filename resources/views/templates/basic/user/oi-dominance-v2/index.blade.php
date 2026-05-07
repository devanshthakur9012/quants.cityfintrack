@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ─────────────────────────────────────────────────
   OI DOMINANCE V2 — Industrial Terminal Aesthetic
   Gunmetal base · Amber signal · Green/Red action
───────────────────────────────────────────────── */
:root {
    --bg:      #060810;
    --card:    #0c1018;
    --surface: #101620;
    --border:  rgba(255,255,255,0.055);
    --amber:   #f59e0b;
    --cyan:    #22d3ee;
    --green:   #22c55e;
    --red:     #ef4444;
    --purple:  #a855f7;
    --blue:    #3b82f6;
    --orange:  #fb923c;
    --dim:     #374151;
    --txt:     #d1d5db;
    --dim-txt: #6b7280;
    --mono:    'JetBrains Mono','Fira Code','Consolas',monospace;
}

body { background: var(--bg); }
.v2-page { padding: 20px 16px 80px; min-height: 100vh; background: var(--bg); }

/* ── Header ─────────────────────────────────────── */
.v2-hdr {
    border: 1px solid rgba(245,158,11,0.18);
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(245,158,11,0.05), rgba(6,8,16,0) 60%);
    padding: 16px 20px; margin-bottom: 16px;
    position: relative; overflow: hidden;
}
.v2-hdr::before { content:''; position:absolute; top:0; left:0; right:0; height:2px;
    background: linear-gradient(90deg,transparent,var(--amber),var(--cyan),transparent); }
.v2-hdr h4 { color: var(--amber); font-family: var(--mono); font-size: 15px; font-weight: 700; margin: 0; }
.v2-hdr p  { color: var(--dim-txt); font-size: 10px; margin: 4px 0 0; line-height: 1.6; }
.v2-chip   { display:inline-block; padding:2px 8px; border-radius:20px; font-size:8px; font-weight:700;
    letter-spacing:.6px; margin-left:5px; }
.chip-a { background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); color:var(--amber); }
.chip-g { background:rgba(34,197,94,0.1);  border:1px solid rgba(34,197,94,0.25); color:var(--green); }
.chip-r { background:rgba(239,68,68,0.1);  border:1px solid rgba(239,68,68,0.25); color:var(--red); }
.chip-c { background:rgba(34,211,238,0.1); border:1px solid rgba(34,211,238,0.25);color:var(--cyan); }
.chip-p { background:rgba(168,85,247,0.1); border:1px solid rgba(168,85,247,0.25);color:var(--purple); }

/* ── Fix summary strip ──────────────────────────── */
.fix-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:8px; margin-bottom:14px; }
.fix-card  { background:var(--card); border:1px solid var(--border); border-radius:7px; padding:10px 12px;
    border-left:2px solid var(--amber); }
.fix-card h6 { color:var(--amber); font-size:9px; font-weight:700; text-transform:uppercase;
    letter-spacing:.5px; margin:0 0 6px; font-family:var(--mono); }
.fix-card p  { color:var(--dim-txt); font-size:9px; margin:0; line-height:1.5; }
.fix-card p strong { color:var(--txt); }
.fix-card.fx-g { border-left-color:var(--green); } .fix-card.fx-g h6 { color:var(--green); }
.fix-card.fx-r { border-left-color:var(--red);   } .fix-card.fx-r h6 { color:var(--red); }
.fix-card.fx-c { border-left-color:var(--cyan);  } .fix-card.fx-c h6 { color:var(--cyan); }
.fix-card.fx-p { border-left-color:var(--purple);} .fix-card.fx-p h6 { color:var(--purple); }
.fix-card.fx-b { border-left-color:var(--blue);  } .fix-card.fx-b h6 { color:var(--blue); }
.fix-card.fx-o { border-left-color:var(--orange);} .fix-card.fx-o h6 { color:var(--orange); }

/* ── Filter ─────────────────────────────────────── */
.v2-filter { background:var(--card); border:1px solid var(--border); border-radius:8px; padding:12px 16px; margin-bottom:14px; }
.v2-filter label { color:var(--dim-txt); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:3px; }
.v2-filter .form-control { background:rgba(0,0,0,0.4); border:1px solid rgba(255,255,255,0.08); color:var(--txt); font-size:11px; border-radius:5px; padding:6px 9px; font-family:var(--mono); }
.v2-filter .form-control:focus { border-color:var(--amber); box-shadow:0 0 0 2px rgba(245,158,11,.1); outline:none; }
.v2-filter select option { background:#0c1018; }
.btn-v2-run { background:linear-gradient(90deg,var(--amber),var(--orange)); color:#000; font-weight:700; font-size:12px; border:none; border-radius:5px; padding:9px 22px; cursor:pointer; font-family:var(--mono); transition:box-shadow .2s,transform .15s; }
.btn-v2-run:hover { box-shadow:0 0 16px rgba(245,158,11,.4); transform:translateY(-1px); }
.btn-v2-rst { background:transparent; border:1px solid rgba(255,255,255,.1); color:var(--dim-txt); font-size:11px; border-radius:5px; padding:9px 14px; cursor:pointer; font-family:var(--mono); }
.btn-v2-rst:hover { border-color:var(--amber); color:var(--amber); }

/* ── Stats ──────────────────────────────────────── */
.v2-stats { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
.v2-stat  { background:var(--card); border:1px solid var(--border); border-radius:6px; padding:8px 12px; flex:1; min-width:85px; text-align:center; border-top:2px solid transparent; }
.v2-stat small  { color:var(--dim-txt); font-size:8px; text-transform:uppercase; letter-spacing:.4px; display:block; }
.v2-stat strong { display:block; font-size:1.15rem; font-weight:700; margin-top:3px; font-family:var(--mono); }
.s-a{border-top-color:var(--amber);} .s-g{border-top-color:var(--green);}
.s-r{border-top-color:var(--red);}   .s-c{border-top-color:var(--cyan);}
.s-p{border-top-color:var(--purple);}.s-o{border-top-color:var(--orange);}
.s-b{border-top-color:var(--blue);}  .s-d{border-top-color:var(--dim);}

/* ── Table ──────────────────────────────────────── */
.v2-wrap { background:var(--card); border:1px solid var(--border); border-radius:8px; overflow:hidden; position:relative; }
.v2-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.v2-tbl { width:100%; border-collapse:collapse; font-family:var(--mono); font-size:11px; min-width:2800px; }
.v2-tbl thead th { background:#08101a; color:var(--dim-txt); font-size:8.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:.5px; padding:8px 5px; border-bottom:1px solid var(--border);
    text-align:center; white-space:nowrap; }
.v2-tbl thead tr.grp th { padding:4px 5px; font-size:8px; }
.v2-tbl tbody td { padding:6px 5px; border-bottom:1px solid var(--border); text-align:center; color:var(--txt); vertical-align:middle; }
.v2-tbl tbody tr:hover { background:rgba(245,158,11,0.02); }

/* sticky */
.v2-tbl th:nth-child(1),.v2-tbl td:nth-child(1),
.v2-tbl th:nth-child(2),.v2-tbl td:nth-child(2),
.v2-tbl th:nth-child(3),.v2-tbl td:nth-child(3) { position:sticky; z-index:5; background:var(--card); }
.v2-tbl th:nth-child(1),.v2-tbl td:nth-child(1) { left:0; }
.v2-tbl th:nth-child(2),.v2-tbl td:nth-child(2) { left:34px; }
.v2-tbl th:nth-child(3),.v2-tbl td:nth-child(3) { left:112px; }

/* Row classes */
.row-vh   { background:rgba(245,158,11,0.05) !important; outline:1px solid rgba(245,158,11,0.3); }
.row-bull { background:rgba(34,197,94,0.03)  !important; }
.row-bear { background:rgba(239,68,68,0.03)  !important; }
.row-skip { background:rgba(55,65,81,0.06)   !important; opacity:.55; }
.row-ov   { background:rgba(251,146,60,0.05) !important; outline:1px solid rgba(251,146,60,0.25); }
.row-risk { background:rgba(239,68,68,0.04)  !important; }

/* ── Badges ─────────────────────────────────────── */
.b { display:inline-block; padding:2px 6px; border-radius:3px; font-size:8.5px; font-weight:700; white-space:nowrap; }
.b-sb { background:rgba(34,197,94,0.14);  color:#4ade80; border:1px solid rgba(34,197,94,.3); }
.b-b  { background:rgba(34,197,94,0.07);  color:#86efac; border:1px solid rgba(34,197,94,.15); }
.b-sbe{ background:rgba(239,68,68,0.14);  color:#f87171; border:1px solid rgba(239,68,68,.3); }
.b-be { background:rgba(239,68,68,0.07);  color:#fca5a5; border:1px solid rgba(239,68,68,.15); }
.b-neu{ background:rgba(107,114,128,0.1); color:#9ca3af; border:1px solid rgba(107,114,128,.2); }

.act-ce  { display:inline-block; padding:3px 9px; border-radius:4px; font-size:9px; font-weight:700; background:rgba(34,197,94,0.12);  color:#22c55e; border:1px solid rgba(34,197,94,.28); }
.act-pe  { display:inline-block; padding:3px 9px; border-radius:4px; font-size:9px; font-weight:700; background:rgba(239,68,68,0.12);  color:#ef4444; border:1px solid rgba(239,68,68,.28); }
.act-nt  { display:inline-block; padding:3px 9px; border-radius:4px; font-size:9px; font-weight:700; background:rgba(107,114,128,0.1); color:#6b7280; border:1px solid rgba(107,114,128,.2); }
.act-ov  { display:inline-block; padding:3px 9px; border-radius:4px; font-size:9px; font-weight:700; background:rgba(251,146,60,0.12); color:#fb923c; border:1px solid rgba(251,146,60,.28); }
.act-sk  { display:inline-block; padding:3px 9px; border-radius:4px; font-size:9px; font-weight:700; background:rgba(55,65,81,0.15);  color:#374151; border:1px solid rgba(55,65,81,.2); }

.str-strong   { color:#fb923c; font-weight:700; font-size:9px; }
.str-moderate { color:#22d3ee; font-weight:700; font-size:9px; }
.str-weak     { color:#6b7280; font-size:9px; }
.str-override { color:#f59e0b; font-weight:700; font-size:9px; }
.str-conflict { color:#374151; font-size:9px; }
.str-skipped  { color:#1f2937; font-size:9px; }

.pos-full  { display:inline-block; padding:2px 6px; border-radius:3px; font-size:8px; font-weight:700; background:rgba(34,197,94,0.08);  color:#22c55e; border:1px solid rgba(34,197,94,.2); }
.pos-half  { display:inline-block; padding:2px 6px; border-radius:3px; font-size:8px; font-weight:700; background:rgba(245,158,11,0.08); color:#f59e0b; border:1px solid rgba(245,158,11,.2); }
.pos-avoid { display:inline-block; padding:2px 6px; border-radius:3px; font-size:8px; font-weight:700; background:rgba(107,114,128,0.08);color:#6b7280; border:1px solid rgba(107,114,128,.18); }

.conf-vh { color:#fb923c; font-weight:700; font-size:9px; }
.conf-h  { color:#22c55e; font-weight:700; font-size:9px; }
.conf-m  { color:#f59e0b; font-weight:700; font-size:9px; }
.conf-l  { color:#6b7280; font-size:9px; }
.conf-n  { color:#1f2937; font-size:9px; }

.pct-cep { color:#f87171; font-weight:700; } /* CE up = bearish pressure */
.pct-cen { color:#86efac; font-weight:700; } /* CE down = bullish pressure */
.pct-pep { color:#86efac; font-weight:700; } /* PE up = bullish pressure */
.pct-pen { color:#f87171; font-weight:700; } /* PE down = bearish pressure */
.pct-z   { color:var(--dim); }

.dom-bull { color:#86efac; font-weight:700; }
.dom-bear { color:#f87171; font-weight:700; }
.dom-zero { color:var(--dim); }

.bu-lb { background:rgba(34,197,94,0.1);  color:#86efac; padding:2px 5px; border-radius:3px; font-size:8px; font-weight:700; }
.bu-sb { background:rgba(239,68,68,0.1);  color:#f87171; padding:2px 5px; border-radius:3px; font-size:8px; font-weight:700; }
.bu-sc { background:rgba(34,211,238,0.1); color:#67e8f9; padding:2px 5px; border-radius:3px; font-size:8px; font-weight:700; }
.bu-lu { background:rgba(245,158,11,0.1); color:#fcd34d; padding:2px 5px; border-radius:3px; font-size:8px; font-weight:700; }
.bu-uk { background:rgba(55,65,81,0.1);   color:#9ca3af; padding:2px 5px; border-radius:3px; font-size:8px; }

/* Failure pattern pills */
.fp  { display:inline-block; padding:1px 5px; border-radius:2px; font-size:7.5px; font-weight:700;
    background:rgba(239,68,68,0.12); color:#f87171; border:1px solid rgba(239,68,68,.2);
    margin:1px; white-space:nowrap; }
.fp-ok { background:rgba(34,197,94,0.08); color:#86efac; border-color:rgba(34,197,94,.15); }

/* Agree dots */
.ag-dots { display:flex; gap:3px; justify-content:center; }
.ag-d { width:7px; height:7px; border-radius:50%; }
.ag-bull { background:var(--green); box-shadow:0 0 4px rgba(34,197,94,.5); }
.ag-bear { background:var(--red);   box-shadow:0 0 4px rgba(239,68,68,.5); }
.ag-off  { background:var(--dim); }

/* Manip bar */
.ms-w  { display:flex; align-items:center; gap:4px; justify-content:center; }
.ms-bar{ width:44px; height:4px; border-radius:2px; background:linear-gradient(90deg,var(--green),var(--amber),var(--red)); position:relative; }
.ms-fill { height:100%; border-radius:2px; background:rgba(0,0,0,0.7); position:absolute; right:0; top:0; }
.ms-n  { font-family:var(--mono); font-size:9px; font-weight:700; min-width:12px; }

/* Close pos bar */
.cp-wrap { display:flex; align-items:center; gap:3px; justify-content:center; }
.cp-bar  { width:36px; height:4px; border-radius:2px; background:var(--dim); position:relative; }
.cp-fill { height:100%; border-radius:2px; position:absolute; left:0; top:0; }

/* Loading */
.v2-load { position:absolute; inset:0; background:rgba(6,8,16,.94); display:flex; flex-direction:column; justify-content:center; align-items:center; z-index:100; border-radius:8px; }
.v2-spin { width:38px; height:38px; border:3px solid rgba(245,158,11,.1); border-top:3px solid var(--amber); border-radius:50%; animation:sp 1s linear infinite; }
@keyframes sp { to { transform:rotate(360deg); } }
.v2-load-t { color:var(--amber); font-size:12px; margin-top:12px; font-family:var(--mono); }

.v2-empty { padding:50px 20px; text-align:center; color:var(--dim-txt); }
.v2-empty i { font-size:2.5rem; opacity:.2; display:block; margin-bottom:10px; color:var(--amber); }

.atm-chip { background:rgba(34,211,238,0.08); border:1px solid rgba(34,211,238,.25); color:var(--cyan); padding:2px 6px; border-radius:3px; font-size:8.5px; font-weight:700; }
.exp-warn { background:rgba(239,68,68,0.1); color:#f87171; padding:1px 4px; border-radius:2px; font-size:7.5px; font-weight:700; margin-top:2px; display:inline-block; }
.sr-res { color:#f87171; font-size:9px; font-weight:700; }
.sr-sup { color:#86efac; font-size:9px; font-weight:700; }
.sr-blk { color:#fb923c; font-size:8px; font-weight:700; }
</style>
@endpush

<div class="v2-page">

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="v2-hdr">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4>
                    ⚡ OI Dominance V2 — ATM −5 to +5 Unified
                    <span class="v2-chip chip-a">10 FIXES</span>
                    <span class="v2-chip chip-c">WEIGHTED DOM</span>
                    <span class="v2-chip chip-g">FAILURE TRACK</span>
                </h4>
                <p>
                    Same 11 strikes (ATM −5→+5) for CE <em>and</em> PE — no directional bias &nbsp;·&nbsp;
                    Weighted dominance (% + absolute) &nbsp;·&nbsp;
                    Liquidity gate · Volatility gate · SR block · Manip gate · Expiry caution · Failure patterns
                </p>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn-v2-rst" style="text-decoration:none;display:inline-block;line-height:1.6;">EOD</a>
                <a href="{{ route('multiday-oi.index') }}" class="btn-v2-rst" style="text-decoration:none;display:inline-block;line-height:1.6;">4-Day</a>
            </div>
        </div>
    </div>

    {{-- ── Fix Summary ──────────────────────────────────────────── --}}
    <div class="fix-strip">
        <div class="fix-card"><h6>FIX 1 — Weighted Dom</h6><p><strong>60% pct + 40% absolute</strong> OI change. Prevents fake signal when one side has bigger raw volume.</p></div>
        <div class="fix-card fx-c"><h6>FIX 2 — Liquidity Gate</h6><p>Min <strong>5L OI</strong> (CE+PE combined). Low-liquidity strikes → auto SKIP.</p></div>
        <div class="fix-card fx-g"><h6>FIX 3 — Same Strikes</h6><p>ATM −5 to +5 for <strong>both CE and PE</strong>. No more apples vs oranges comparison.</p></div>
        <div class="fix-card fx-o"><h6>FIX 4 — Vol Gate</h6><p>Range &lt; 60% of 5-day avg → sideways → SKIP. Avoids noise trades.</p></div>
        <div class="fix-card fx-b"><h6>FIX 5 — Price Context</h6><p><strong>Close pos + prev close trend</strong> combined (60/40 weight). Better entry score.</p></div>
        <div class="fix-card fx-r"><h6>FIX 6 — SR Block</h6><p>BUY CE blocked if price within <strong>1% of resistance</strong>. BUY PE blocked near support.</p></div>
        <div class="fix-card fx-p"><h6>FIX 7 — Manip Gate</h6><p>Score &lt; 3 → NO TRADE. Score &gt; 7 → HIGH CONFIDENCE. Forces quality over quantity.</p></div>
        <div class="fix-card"><h6>FIX 8 — Expiry Caution</h6><p>Days to expiry &lt; 2 → position halved, strength reduced. OI unreliable near expiry.</p></div>
        <div class="fix-card fx-c"><h6>FIX 9 — BTST Window</h6><p>Next day high + low returned for proper P/L calculation (gap + intraday move).</p></div>
        <div class="fix-card fx-g"><h6>FIX 10 — Failure Tags</h6><p>Each row tagged with known failure patterns: <strong>CE_SPIKE_TRAP, BOTH_RISING, SR_VIOLATION</strong> etc.</p></div>
    </div>

    {{-- ── Filters ──────────────────────────────────────────────── --}}
    <div class="v2-filter">
        <div class="row mb-2">
            <div class="col-md-2"><label>From Date</label><input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" /></div>
            <div class="col-md-2"><label>To Date</label><input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" /></div>
            <div class="col-md-2"><label>Symbols</label><select id="symbol_filter" class="form-control" multiple size="2"></select></div>
            <div class="col-md-2">
                <label>Final Action</label>
                <select id="action_filter" class="form-control">
                    <option value="">All</option>
                    <option value="BUY CE">BUY CE</option>
                    <option value="BUY PE">BUY PE</option>
                    <option value="NO TRADE">NO TRADE</option>
                </select>
            </div>
            <div class="col-md-2">
                <label>Strength</label>
                <select id="strength_filter" class="form-control">
                    <option value="">All</option>
                    <option value="STRONG">Strong</option>
                    <option value="OVERRIDE">Override</option>
                    <option value="MODERATE">Moderate</option>
                    <option value="WEAK">Weak</option>
                    <option value="CONFLICT">Conflict</option>
                </select>
            </div>
            <div class="col-md-2">
                <label>Min Manip Score</label>
                <input type="number" id="min_manip" class="form-control" value="0" min="0" max="10" />
            </div>
        </div>
        <div class="text-center">
            <button type="button" id="btn_run" class="btn-v2-run">⚡ Run V2 Analysis</button>
            <button type="button" id="btn_rst" class="btn-v2-rst ml-2">↺ Reset</button>
        </div>
    </div>

    {{-- ── Stats ───────────────────────────────────────────────── --}}
    <div class="v2-stats">
        <div class="v2-stat s-a"><small>Total</small><strong id="st_total" style="color:var(--amber);">0</strong></div>
        <div class="v2-stat s-o"><small>🔥 STRONG</small><strong id="st_strong" style="color:var(--orange);">0</strong></div>
        <div class="v2-stat s-g"><small>📈 BUY CE</small><strong id="st_ce" style="color:var(--green);">0</strong></div>
        <div class="v2-stat s-r"><small>📉 BUY PE</small><strong id="st_pe" style="color:var(--red);">0</strong></div>
        <div class="v2-stat s-c"><small>⏸ NO TRADE</small><strong id="st_nt" style="color:var(--cyan);">0</strong></div>
        <div class="v2-stat s-p"><small>🛑 Skipped (gate)</small><strong id="st_skip" style="color:var(--purple);">0</strong></div>
        <div class="v2-stat s-b"><small>⚡ Override</small><strong id="st_ov" style="color:var(--blue);">0</strong></div>
        <div class="v2-stat s-o"><small>⚠ Risk Flags</small><strong id="st_risk" style="color:var(--orange);">0</strong></div>
        <div class="v2-stat s-d"><small>🕵 High Manip</small><strong id="st_hm" style="color:var(--amber);">0</strong></div>
    </div>

    {{-- ── Table ───────────────────────────────────────────────── --}}
    <div class="v2-wrap">
        <div class="v2-load" id="v2-loading" style="display:none;">
            <div class="v2-spin"></div>
            <div class="v2-load-t">Running V2 analysis with 10 fixes...</div>
        </div>
        <div class="v2-scroll">
            <table class="v2-tbl">
                <thead>
                    <tr class="grp">
                        <th colspan="4">BASE</th>
                        <th colspan="6" style="color:#f59e0b;background:rgba(245,158,11,0.04);border-bottom:1px solid rgba(245,158,11,.25);">── OI (ATM ±5 unified — FIX 3) ─────────────────────────────</th>
                        <th colspan="5" style="color:#22d3ee;background:rgba(34,211,238,0.04);border-bottom:1px solid rgba(34,211,238,.25);">── DOMINANCE (FIX 1: weighted) ─────────────</th>
                        <th colspan="3" style="color:#22c55e;background:rgba(34,197,94,0.04);border-bottom:1px solid rgba(34,197,94,.25);">── SIGNALS (near·far·all) ──</th>
                        <th colspan="4" style="color:#a855f7;background:rgba(168,85,247,0.04);border-bottom:1px solid rgba(168,85,247,.25);">── PRICE + CONTEXT ─────────────</th>
                        <th colspan="3" style="color:#ef4444;background:rgba(239,68,68,0.04);border-bottom:1px solid rgba(239,68,68,.25);">── GATES ───────</th>
                        <th colspan="6" style="color:#f59e0b;background:rgba(245,158,11,0.05);border-bottom:1px solid rgba(245,158,11,.35);">── FINAL DECISION ─────────────────────</th>
                    </tr>
                    <tr>
                        <th>#</th><th>Date</th><th>Symbol</th>
                        <th>ATM<br><small>Spot·Step</small></th>

                        <th style="background:rgba(245,158,11,0.025);">CE OI<br><small>Today</small></th>
                        <th style="background:rgba(245,158,11,0.025);">PE OI<br><small>Today</small></th>
                        <th style="background:rgba(245,158,11,0.025);">CE OI<br><small>Prev</small></th>
                        <th style="background:rgba(245,158,11,0.025);">PE OI<br><small>Prev</small></th>
                        <th style="background:rgba(245,158,11,0.025);">CE %<br><small>chg</small></th>
                        <th style="background:rgba(245,158,11,0.025);">PE %<br><small>chg</small></th>

                        <th style="background:rgba(34,211,238,0.025);">Dom %<br><small>All</small></th>
                        <th style="background:rgba(34,211,238,0.025);">Dom %<br><small>Near</small></th>
                        <th style="background:rgba(34,211,238,0.025);">Dom %<br><small>Far</small></th>
                        <th style="background:rgba(34,211,238,0.025);">Dom Abs<br><small>PE−CE</small></th>
                        <th style="background:rgba(34,211,238,0.025);">Dom<br><small>Combined</small></th>

                        <th style="background:rgba(34,197,94,0.025);">Near<br><small>Signal</small></th>
                        <th style="background:rgba(34,197,94,0.025);">Far<br><small>Signal</small></th>
                        <th style="background:rgba(34,197,94,0.025);">All<br><small>Signal</small></th>

                        <th style="background:rgba(168,85,247,0.025);">Close<br><small>Pos</small></th>
                        <th style="background:rgba(168,85,247,0.025);">Price<br><small>Score</small></th>
                        <th style="background:rgba(168,85,247,0.025);">Prev<br><small>Trend</small></th>
                        <th style="background:rgba(168,85,247,0.025);">Buildup<br><small>Type</small></th>

                        <th style="background:rgba(239,68,68,0.025);">Manip<br><small>Score</small></th>
                        <th style="background:rgba(239,68,68,0.025);">Sup<br><small>Res</small></th>
                        <th style="background:rgba(239,68,68,0.025);">Exp<br><small>Days</small></th>

                        <th style="background:rgba(245,158,11,0.04);">Agree</th>
                        <th style="background:rgba(245,158,11,0.04);">Strength</th>
                        <th style="background:rgba(245,158,11,0.04);">Action</th>
                        <th style="background:rgba(245,158,11,0.04);">Pos</th>
                        <th style="background:rgba(245,158,11,0.04);">Conf</th>
                        <th style="background:rgba(245,158,11,0.04);">Risk Flags<br><small>Failure Patterns</small></th>
                    </tr>
                </thead>
                <tbody id="v2-tbody">
                    <tr><td colspan="30" class="v2-empty">
                        <i class="fas fa-flask"></i>
                        Click <strong>⚡ Run V2 Analysis</strong> to start
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('script')
<script>
let v2Data = [];

$(function() { loadSymbols(); setTimeout(runV2, 400); });

function loadSymbols() {
    $.get('{{ route("oi-dominance-v2.symbols") }}', function(r) {
        if (!r.success) return;
        let o = ''; r.symbols.forEach(s => { o += `<option value="${s}">${s}</option>`; });
        $('#symbol_filter').html(o);
    });
}

function runV2() {
    const from = $('#from_date').val(), to = $('#to_date').val();
    const syms = $('#symbol_filter').val()||[], act = $('#action_filter').val();
    const str  = $('#strength_filter').val(), mm = $('#min_manip').val()||0;
    if (!from||!to) { alert('Select dates'); return; }

    $('#v2-loading').show(); v2Data=[]; resetStats();

    $.ajax({
        url: '{{ route("oi-dominance-v2.analyze") }}', type: 'GET',
        data: { from_date:from, to_date:to, symbols:syms, filter_action:act, filter_strength:str, min_manip:mm },
        success: function(r) {
            $('#v2-loading').hide();
            if (r.success && r.data && r.data.length) { v2Data=r.data; renderTable(); updateStats(); }
            else noData(r.message||'No data');
        },
        error: function() { $('#v2-loading').hide(); noData('Error'); }
    });
}

/* ── Formatters ─────────────────────────────────── */
function fmtOI(v) {
    const n=Number(v)||0;
    return n>=1e6?(n/1e6).toFixed(2)+'M':n>=1e3?(n/1e3).toFixed(1)+'K':n.toString();
}

function cePctCell(v) {
    const n=parseFloat(v)||0, s=n>0?'+':'';
    return `<span class="${n>0?'pct-cep':n<0?'pct-cen':'pct-z'}">${s}${n.toFixed(2)}%</span>`;
}

function pePctCell(v) {
    const n=parseFloat(v)||0, s=n>0?'+':'';
    return `<span class="${n>0?'pct-pep':n<0?'pct-pen':'pct-z'}">${s}${n.toFixed(2)}%</span>`;
}

function domCell(v) {
    const n=parseFloat(v)||0, s=n>0?'+':'';
    return `<span class="${n>0?'dom-bull':n<0?'dom-bear':'dom-zero'}" style="font-weight:700;">${s}${n.toFixed(2)}</span>`;
}

function domAbsCell(v) {
    const n=Number(v)||0, s=n>0?'+':'';
    const cls=n>0?'dom-bull':n<0?'dom-bear':'dom-zero';
    const fmt=n>=1e6?(n/1e6).toFixed(2)+'M':n>=1e3?(n/1e3).toFixed(1)+'K':n.toString();
    return `<span class="${cls}" style="font-weight:700;">${n>0?'+':''}${fmt}</span>`;
}

function sigBadge(s) {
    const m={'STRONG_BULLISH':['b b-sb','🟢🟢 S.BULL'],'BULLISH':['b b-b','🟢 BULL'],
        'STRONG_BEARISH':['b b-sbe','🔴🔴 S.BEAR'],'BEARISH':['b b-be','🔴 BEAR'],'NEUTRAL':['b b-neu','⚪ NEUT']};
    const [c,l]=m[s]||['b b-neu',s||'—']; return `<span class="${c}">${l}</span>`;
}

function actBadge(a) {
    if (a==='BUY CE')   return '<span class="act-ce">📈 BUY CE</span>';
    if (a==='BUY PE')   return '<span class="act-pe">📉 BUY PE</span>';
    if (a==='NO TRADE') return '<span class="act-nt">⏸ NO TRADE</span>';
    if (a==='SKIP')     return '<span class="act-sk">🚫 SKIP</span>';
    return '<span class="act-ov">⚡ OV</span>';
}

function strBadge(s) {
    const m={'STRONG':['str-strong','🔥 STRONG'],'OVERRIDE':['str-override','⚡ OV'],
        'MODERATE':['str-moderate','✅ MOD'],'WEAK':['str-weak','💧 WEAK'],
        'CONFLICT':['str-conflict','❌ CONF'],'SR_BLOCKED':['str-weak','🚫 SR'],
        'SKIPPED':['str-skipped','— SKIP']};
    const [c,l]=m[s]||['str-conflict','—']; return `<span class="${c}">${l}</span>`;
}

function posBadge(p) {
    if(p==='FULL')  return '<span class="pos-full">FULL</span>';
    if(p==='HALF')  return '<span class="pos-half">HALF</span>';
    return '<span class="pos-avoid">AVOID</span>';
}

function confBadge(c) {
    const m={'VERY_HIGH':['conf-vh','🔥 VH'],'HIGH':['conf-h','✅ H'],'MEDIUM':['conf-m','⚡ M'],
        'LOW':['conf-l','💧 L'],'OVERRIDE':['conf-vh','⚡ OV'],'NONE':['conf-n','—']};
    const [cl,l]=m[c]||['conf-n','—']; return `<span class="${cl}">${l}</span>`;
}

function agDots(n, sig) {
    const isBull = sig==='BULLISH'||sig==='STRONG_BULLISH';
    const onCls = isBull ? 'ag-bull' : 'ag-bear';
    let h='<div class="ag-dots">';
    for(let i=0;i<3;i++) h+=`<div class="ag-d ${i<n?onCls:'ag-off'}"></div>`;
    return h+`</div><small style="color:var(--dim-txt);font-size:8px;">${n}/3</small>`;
}

function manipBar(s) {
    const rem=100-((s/10)*100);
    const col=s>=8?'#ef4444':s>=6?'#f59e0b':s>=4?'#22c55e':'#374151';
    return `<div class="ms-w"><div class="ms-bar"><div class="ms-fill" style="width:${rem}%;"></div></div><span class="ms-n" style="color:${col};">${s}</span></div>`;
}

function cpBar(v) {
    const n=parseFloat(v)||0.5, pct=Math.round(n*100);
    const col=n>0.6?'#22c55e':n<0.4?'#ef4444':'#f59e0b';
    return `<div class="cp-wrap"><div class="cp-bar"><div class="cp-fill" style="width:${pct}%;background:${col};"></div></div><span style="font-size:8px;color:${col};">${n.toFixed(2)}</span></div>`;
}

function buildupBadge(b) {
    const m={'LONG_BUILDUP':['bu-lb','📈 L.BU'],'SHORT_BUILDUP':['bu-sb','📉 S.BU'],
        'SHORT_COVERING':['bu-sc','⚡ SC'],'LONG_UNWINDING':['bu-lu','⬇ LU'],'UNKNOWN':['bu-uk','—']};
    const [c,l]=m[b]||['bu-uk','—']; return `<span class="${c}">${l}</span>`;
}

function failurePills(fp, has) {
    if (!fp || fp.length===0) return '<span style="color:var(--dim);font-size:8px;">—</span>';
    return fp.map(p => {
        const ok = p==='LONG_BUILDUP'||p==='SHORT_COVERING';
        return `<span class="fp${ok?' fp-ok':''}">${p}</span>`;
    }).join('');
}

function srCell(res, sup, blocked, reason) {
    let h='';
    if(res) h+=`<span class="sr-res">R:${Number(res).toLocaleString('en-IN')}</span>`;
    if(sup) h+=`<br><span class="sr-sup">S:${Number(sup).toLocaleString('en-IN')}</span>`;
    if(blocked) h+=`<br><span class="sr-blk">🚫 BLOCKED</span>`;
    return h||'<span style="color:var(--dim);">—</span>';
}

function expCell(days, warn) {
    const col = days<=2?'#ef4444':days<=5?'#f59e0b':'#6b7280';
    const w = warn?` <span class="exp-warn">⚠ CAUTION</span>`:'';
    return `<span style="color:${col};font-weight:700;font-size:9px;">${days}d</span>${w}`;
}

/* ── Render ─────────────────────────────────────── */
function renderTable() {
    let html='';
    v2Data.forEach(function(r,i) {
        const isSkip   = r.skip_reason;
        const isStrong = r.signal_strength==='STRONG' && !isSkip;
        const isOv     = r.override_active;
        const isRisk   = r.has_risk_flag && !isSkip;
        let rowCls = isSkip?'row-skip':isStrong&&r.final_signal==='BULLISH'?'row-bull':
            isStrong&&r.final_signal==='BEARISH'?'row-bear':isOv?'row-ov':isRisk?'row-risk':'';

        const isVH = r.confidence==='VERY_HIGH';
        if(isVH) rowCls='row-vh';

        const prevTrendBadge = r.prev_trend==='UP'?'<span style="color:#86efac;font-size:9px;font-weight:700;">▲ UP</span>':
            r.prev_trend==='DOWN'?'<span style="color:#f87171;font-size:9px;font-weight:700;">▼ DN</span>':
            '<span style="color:var(--dim);font-size:9px;">→ FL</span>';

        html+=`<tr class="${rowCls}">
            <td style="color:var(--dim-txt);">${i+1}</td>
            <td style="color:var(--amber);font-weight:700;font-family:var(--mono);">${r.date}</td>
            <td style="font-weight:700;">${r.symbol}</td>
            <td>
                <span class="atm-chip">₹${Number(r.atm_strike).toLocaleString('en-IN')}</span>
                <br><small style="color:var(--dim-txt);font-size:8px;">₹${Number(r.spot_price).toLocaleString('en-IN')} | s:${r.strike_step}</small>
            </td>

            <td style="background:rgba(245,158,11,0.02);">${fmtOI(r.ce_oi_all)}<br><small style="color:var(--dim-txt);font-size:8px;">${(r.ce_oi_all||0).toLocaleString()}</small></td>
            <td style="background:rgba(245,158,11,0.02);">${fmtOI(r.pe_oi_all)}<br><small style="color:var(--dim-txt);font-size:8px;">${(r.pe_oi_all||0).toLocaleString()}</small></td>
            <td style="background:rgba(245,158,11,0.02);color:var(--dim-txt);">${fmtOI(r.ce_oi_prev)}</td>
            <td style="background:rgba(245,158,11,0.02);color:var(--dim-txt);">${fmtOI(r.pe_oi_prev)}</td>
            <td style="background:rgba(245,158,11,0.02);">${cePctCell(r.ce_pct_all)}</td>
            <td style="background:rgba(245,158,11,0.02);">${pePctCell(r.pe_pct_all)}</td>

            <td style="background:rgba(34,211,238,0.02);">${domCell(r.dom_pct_all)}</td>
            <td style="background:rgba(34,211,238,0.02);">${domCell(r.dom_pct_near)}</td>
            <td style="background:rgba(34,211,238,0.02);">${domCell(r.dom_pct_far)}</td>
            <td style="background:rgba(34,211,238,0.02);">${domAbsCell(r.dom_absolute)}</td>
            <td style="background:rgba(34,211,238,0.02);"><strong>${domCell(r.dom_combined)}</strong></td>

            <td style="background:rgba(34,197,94,0.02);">${sigBadge(r.sig_near)}</td>
            <td style="background:rgba(34,197,94,0.02);">${sigBadge(r.sig_far)}</td>
            <td style="background:rgba(34,197,94,0.02);">${sigBadge(r.sig_all)}</td>

            <td style="background:rgba(168,85,247,0.02);">${cpBar(r.close_pos)}</td>
            <td style="background:rgba(168,85,247,0.02);"><span style="font-size:9px;font-family:var(--mono);">${Number(r.price_score||0).toFixed(3)}</span></td>
            <td style="background:rgba(168,85,247,0.02);">${prevTrendBadge}</td>
            <td style="background:rgba(168,85,247,0.02);">${buildupBadge(r.buildup_type)}</td>

            <td style="background:rgba(239,68,68,0.02);">${manipBar(r.manip_score)}${r.manip_high_conf?'<br><span style="color:#fb923c;font-size:7.5px;font-weight:700;">HIGH CONF</span>':''}</td>
            <td style="background:rgba(239,68,68,0.02);">${srCell(r.resistance, r.support, r.sr_blocked, r.sr_reason)}</td>
            <td style="background:rgba(239,68,68,0.02);">${expCell(r.days_to_exp||0, r.expiry_warn)}</td>

            <td style="background:rgba(245,158,11,0.03);">${agDots(r.agreements||0, r.final_signal)}</td>
            <td style="background:rgba(245,158,11,0.03);">${strBadge(r.signal_strength)}</td>
            <td style="background:rgba(245,158,11,0.03);">${actBadge(r.final_action)}</td>
            <td style="background:rgba(245,158,11,0.03);">${posBadge(r.position_size)}</td>
            <td style="background:rgba(245,158,11,0.03);">${confBadge(r.confidence)}</td>
            <td style="background:rgba(245,158,11,0.03);text-align:left;max-width:180px;white-space:normal;line-height:1.5;">
                ${failurePills(r.failure_patterns)}
                ${r.override_active?`<br><span style="color:var(--orange);font-size:8px;">⚡${r.override_rule}</span>`:''}
                ${r.sr_blocked?`<br><span style="color:#f87171;font-size:8px;">🚫${r.sr_reason}</span>`:''}
                ${r.expiry_warn?'<br><span class="exp-warn">⚠ EXPIRY NEAR</span>':''}
                <br><small style="color:var(--dim-txt);font-size:8px;">${(r.final_reason||'').substring(0,80)}</small>
            </td>
        </tr>`;
    });
    $('#v2-tbody').html(html);
}

function updateStats() {
    const t=v2Data.length;
    $('#st_total').text(t);
    $('#st_strong').text(v2Data.filter(r=>r.signal_strength==='STRONG').length);
    $('#st_ce').text(v2Data.filter(r=>r.final_action==='BUY CE').length);
    $('#st_pe').text(v2Data.filter(r=>r.final_action==='BUY PE').length);
    $('#st_nt').text(v2Data.filter(r=>r.final_action==='NO TRADE').length);
    $('#st_skip').text(v2Data.filter(r=>r.skip_reason).length);
    $('#st_ov').text(v2Data.filter(r=>r.override_active).length);
    $('#st_risk').text(v2Data.filter(r=>r.has_risk_flag).length);
    $('#st_hm').text(v2Data.filter(r=>r.manip_high_conf).length);
}
function resetStats() {
    $('#st_total,#st_strong,#st_ce,#st_pe,#st_nt,#st_skip,#st_ov,#st_risk,#st_hm').text('0');
}
function noData(m) {
    $('#v2-tbody').html(`<tr><td colspan="30" class="v2-empty"><i class="fas fa-flask"></i><br>${m}</td></tr>`);
}

$('#btn_run').click(runV2);
$('#btn_rst').click(function() {
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter,#action_filter,#strength_filter').val('');
    $('#min_manip').val(0);
    v2Data=[]; resetStats(); noData('Click ⚡ Run V2 Analysis to start');
    setTimeout(runV2,200);
});
</script>
@endpush