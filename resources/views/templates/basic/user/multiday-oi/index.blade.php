@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ─────────────────────────────────────────────────────
   MULTI-DAY OI TRACKER — Dark Terminal + Amber Accents
   Aesthetic: Intelligence terminal / surveillance grid
───────────────────────────────────────────────────── */
:root {
    --ink:      #080c14;
    --surface:  #0d1421;
    --card:     #111b2b;
    --line:     rgba(255,255,255,0.06);
    --amber:    #ffb300;
    --amber-dim:#c67f00;
    --cyan:     #00e5ff;
    --green:    #00c853;
    --red:      #ff1744;
    --purple:   #aa00ff;
    --blue:     #2979ff;
    --dim:      #4a5568;
    --muted:    #2d3748;
    --txt:      #cbd5e0;
    --txt-dim:  #718096;
    --mono:     'JetBrains Mono','Fira Code','Consolas',monospace;
    --radius:   6px;
}

body { background: var(--ink); }
.md-page { padding: 24px 20px 80px; min-height: 100vh; background: var(--ink); }

/* ── Header ─────────────────────────────────────────── */
.md-header {
    border: 1px solid rgba(255,179,0,0.25);
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(255,179,0,0.06) 0%, rgba(9,12,20,0) 60%);
    padding: 20px 24px 16px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.md-header::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent, var(--amber), transparent);
}
.md-header h4 { color: var(--amber); font-family: var(--mono); font-size: 17px; font-weight: 700; margin: 0; letter-spacing: .5px; }
.md-header p  { color: var(--txt-dim); font-size: 11px; margin: 5px 0 0; }
.md-badge { background: rgba(255,179,0,0.15); border: 1px solid rgba(255,179,0,0.4); color: var(--amber); font-size: 9px; font-weight: 700; padding: 2px 9px; border-radius: 20px; letter-spacing: .8px; margin-left: 8px; }
.md-badge-new { background: rgba(0,229,255,0.12); border: 1px solid rgba(0,229,255,0.35); color: var(--cyan); font-size: 9px; font-weight: 700; padding: 2px 9px; border-radius: 20px; letter-spacing: .8px; margin-left: 6px; }

/* ── Logic cards ────────────────────────────────────── */
.logic-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 12px; margin-bottom: 20px; }
.logic-card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; border-top: 2px solid var(--amber); }
.logic-card h6 { color: var(--amber); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; font-family: var(--mono); }
.logic-card ul { margin: 0; padding: 0; list-style: none; }
.logic-card li { color: var(--txt-dim); font-size: 10px; margin-bottom: 3px; padding-left: 12px; position: relative; }
.logic-card li::before { content: '›'; position: absolute; left: 0; color: var(--amber); }
.logic-card li strong { color: var(--txt); }

/* ── Filter bar ─────────────────────────────────────── */
.md-filter { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 16px 20px; margin-bottom: 20px; }
.md-filter label { color: var(--txt-dim); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; display: block; margin-bottom: 5px; }
.md-filter .form-control { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); color: var(--txt); font-size: 12px; border-radius: var(--radius); padding: 7px 10px; font-family: var(--mono); }
.md-filter .form-control:focus { border-color: var(--amber); box-shadow: 0 0 0 2px rgba(255,179,0,0.1); outline: none; }
.md-filter select option { background: #0d1421; }

/* ── Buttons ────────────────────────────────────────── */
.btn-md-run { background: linear-gradient(90deg, var(--amber), #ff8f00); color: #000; font-weight: 700; font-size: 13px; border: none; border-radius: var(--radius); padding: 10px 26px; cursor: pointer; font-family: var(--mono); letter-spacing: .3px; transition: box-shadow .2s, transform .15s; }
.btn-md-run:hover { box-shadow: 0 0 20px rgba(255,179,0,0.45); transform: translateY(-1px); }
.btn-md-reset { background: transparent; border: 1px solid rgba(255,255,255,0.12); color: var(--txt-dim); font-size: 12px; border-radius: var(--radius); padding: 10px 18px; cursor: pointer; font-family: var(--mono); transition: border-color .2s, color .2s; }
.btn-md-reset:hover { border-color: var(--amber); color: var(--amber); }

/* ── Stats grid ─────────────────────────────────────── */
.md-stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
.md-stat { background: var(--card); border: 1px solid var(--line); border-radius: 8px; padding: 10px 14px; flex: 1; min-width: 100px; text-align: center; border-top: 2px solid transparent; transition: border-color .2s; }
.md-stat small { color: var(--txt-dim); font-size: 9px; text-transform: uppercase; letter-spacing: .4px; display: block; }
.md-stat strong { display: block; font-size: 1.25rem; font-weight: 700; margin-top: 4px; font-family: var(--mono); }
.md-stat.amber  { border-top-color: var(--amber); }
.md-stat.green  { border-top-color: var(--green); }
.md-stat.red    { border-top-color: var(--red); }
.md-stat.cyan   { border-top-color: var(--cyan); }
.md-stat.purple { border-top-color: var(--purple); }
.md-stat.blue   { border-top-color: var(--blue); }
.md-stat.dim    { border-top-color: var(--dim); }

/* ── Table ──────────────────────────────────────────── */
.md-table-wrap { background: var(--card); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; position: relative; }
.md-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

.md-table { width: 100%; border-collapse: collapse; font-family: var(--mono); font-size: 11px; min-width: 2400px; }

.md-table thead th {
    background: #09101c;
    color: var(--txt-dim);
    font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    padding: 10px 7px;
    border-bottom: 1px solid var(--line);
    text-align: center; white-space: nowrap;
}
.md-table thead tr.th-group-row th { padding: 5px 7px; font-size: 8px; }
.md-table thead th.th-ce     { border-bottom: 2px solid rgba(255,23,68,0.4); }
.md-table thead th.th-pe     { border-bottom: 2px solid rgba(0,200,83,0.4); }
.md-table thead th.th-comb   { border-bottom: 2px solid rgba(255,179,0,0.5); }
.md-table thead th.th-cls    { border-bottom: 2px solid rgba(84,110,122,0.4); }

.md-table tbody td { padding: 7px 6px; border-bottom: 1px solid var(--line); text-align: center; color: var(--txt); vertical-align: middle; }
.md-table tbody tr:hover { background: rgba(255,179,0,0.02); }
.md-table tbody tr.row-highlight { background: rgba(255,179,0,0.04); outline: 1px solid rgba(255,179,0,0.2); }
.md-table tbody tr.row-very-high { background: rgba(255,23,68,0.04); }

/* Sticky first 3 */
.md-table th:nth-child(1),.md-table td:nth-child(1),
.md-table th:nth-child(2),.md-table td:nth-child(2),
.md-table th:nth-child(3),.md-table td:nth-child(3) { position: sticky; z-index: 5; background: var(--card); }
.md-table th:nth-child(1),.md-table td:nth-child(1) { left: 0; }
.md-table th:nth-child(2),.md-table td:nth-child(2) { left: 36px; }
.md-table th:nth-child(3),.md-table td:nth-child(3) { left: 115px; }

/* ── 4-day sparkline cells ──────────────────────────── */
.spark-wrap { display: flex; align-items: flex-end; justify-content: center; gap: 3px; height: 28px; }
.spark-bar  { width: 10px; border-radius: 2px 2px 0 0; min-height: 2px; transition: height .3s; }
.spark-bar.pos { background: var(--red); }
.spark-bar.neg { background: var(--green); }
.spark-bar.zero{ background: var(--dim); }
.spark-label-row { display: flex; gap: 3px; justify-content: center; margin-top: 2px; }
.spark-label { font-size: 8px; width: 10px; text-align: center; color: var(--txt-dim); }

/* ── Badges ─────────────────────────────────────────── */
.b { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 9px; font-weight: 700; white-space: nowrap; }

/* Pattern */
.b-steady-build  { background: rgba(255,23,68,0.15);  color: #ff6090; border: 1px solid rgba(255,23,68,0.3); }
.b-mostly-build  { background: rgba(255,23,68,0.08);  color: #ff8aa6; border: 1px solid rgba(255,23,68,0.2); }
.b-steady-unw    { background: rgba(0,200,83,0.15);   color: #69f0ae; border: 1px solid rgba(0,200,83,0.3); }
.b-mostly-unw    { background: rgba(0,200,83,0.08);   color: #a5d6a7; border: 1px solid rgba(0,200,83,0.2); }
.b-stealth-build { background: rgba(170,0,255,0.12);  color: #ce93d8; border: 1px solid rgba(170,0,255,0.3); }
.b-stealth-unw   { background: rgba(41,121,255,0.12); color: #90caf9; border: 1px solid rgba(41,121,255,0.3); }
.b-rev-bull      { background: rgba(0,200,83,0.1);    color: #80e27e; border: 1px solid rgba(0,200,83,0.25); }
.b-rev-bear      { background: rgba(255,23,68,0.1);   color: #ff8a80; border: 1px solid rgba(255,23,68,0.25); }
.b-late-surge    { background: rgba(255,179,0,0.12);  color: #ffe082; border: 1px solid rgba(255,179,0,0.3); }
.b-early-dist    { background: rgba(255,152,0,0.1);   color: #ffb74d; border: 1px solid rgba(255,152,0,0.25); }
.b-choppy        { background: rgba(74,85,104,0.15);  color: #a0aec0; border: 1px solid rgba(74,85,104,0.3); }

/* Signal */
.b-sb  { background: rgba(0,200,83,0.18);  color: #00e676; border: 1px solid rgba(0,200,83,0.4); }
.b-b   { background: rgba(0,200,83,0.08);  color: #69f0ae; border: 1px solid rgba(0,200,83,0.2); }
.b-bw  { background: rgba(0,200,83,0.05);  color: #a5d6a7; border: 1px solid rgba(0,200,83,0.12); }
.b-sbe { background: rgba(255,23,68,0.18); color: #ff1744; border: 1px solid rgba(255,23,68,0.4); }
.b-be  { background: rgba(255,23,68,0.08); color: #ff6090; border: 1px solid rgba(255,23,68,0.2); }
.b-bew { background: rgba(255,23,68,0.05); color: #ff8aa6; border: 1px solid rgba(255,23,68,0.12); }
.b-neu { background: rgba(74,85,104,0.12); color: #a0aec0; border: 1px solid rgba(74,85,104,0.25); }

/* Combined action */
.act-ce { display: inline-block; padding: 4px 11px; border-radius: 5px; font-size: 10px; font-weight: 700; background: rgba(0,200,83,0.12); color: #00c853; border: 1px solid rgba(0,200,83,0.3); }
.act-pe { display: inline-block; padding: 4px 11px; border-radius: 5px; font-size: 10px; font-weight: 700; background: rgba(255,23,68,0.12); color: #ff4081; border: 1px solid rgba(255,23,68,0.3); }
.act-wt { display: inline-block; padding: 4px 11px; border-radius: 5px; font-size: 10px; font-weight: 700; background: rgba(74,85,104,0.12); color: #718096; border: 1px solid rgba(74,85,104,0.25); }

/* Confidence */
.conf-vh { color: #ff6d00; font-weight: 700; font-size: 10px; }
.conf-h  { color: #00c853; font-weight: 700; font-size: 10px; }
.conf-m  { color: #ffb300; font-weight: 700; font-size: 10px; }
.conf-l  { color: #718096; font-size: 10px; }
.conf-n  { color: var(--muted); font-size: 10px; }

/* Manip score bar */
.manip-wrap { display: flex; align-items: center; gap: 5px; justify-content: center; }
.manip-bar { height: 6px; border-radius: 3px; background: linear-gradient(90deg, var(--green), var(--amber), var(--red)); min-width: 60px; position: relative; }
.manip-fill { height: 100%; border-radius: 3px; background: rgba(0,0,0,0.6); position: absolute; right: 0; top: 0; }
.manip-score { font-family: var(--mono); font-size: 10px; font-weight: 700; min-width: 16px; }

/* Pct cell */
.pct-pos { color: #ff6090; font-weight: 700; }
.pct-neg { color: #69f0ae; font-weight: 700; }
.pct-zero{ color: var(--dim); }

/* Net change */
.net-pos { color: #ff6090; font-weight: 700; font-size: 11px; }
.net-neg { color: #69f0ae; font-weight: 700; font-size: 11px; }

/* 50MA */
.ma-bull { display: inline-block; padding: 2px 6px; border-radius: 3px; background: rgba(0,200,83,0.1); color: #69f0ae; font-size: 9px; font-weight: 700; }
.ma-bear { display: inline-block; padding: 2px 6px; border-radius: 3px; background: rgba(255,23,68,0.1); color: #ff6090; font-size: 9px; font-weight: 700; }
.ma-na   { color: var(--dim); font-size: 10px; }

/* Classic badge */
.cl-bull { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; background: rgba(0,200,83,0.08); color: #69f0ae; }
.cl-bear { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; background: rgba(255,23,68,0.08); color: #ff6090; }
.cl-neut { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; background: rgba(74,85,104,0.1); color: #a0aec0; }
.mismatch-dot { display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: var(--amber); margin-left: 4px; vertical-align: middle; }

/* Trend score dots */
.trend-dots { display: flex; gap: 3px; justify-content: center; align-items: center; }
.trend-dot { width: 7px; height: 7px; border-radius: 50%; }
.trend-dot.on  { background: var(--amber); box-shadow: 0 0 4px rgba(255,179,0,0.6); }
.trend-dot.off { background: var(--muted); }

/* Loading */
.md-loading { position: absolute; inset: 0; background: rgba(8,12,20,0.94); display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 100; border-radius: 10px; }
.md-spinner { width: 42px; height: 42px; border: 3px solid rgba(255,179,0,0.1); border-top: 3px solid var(--amber); border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.md-load-txt { color: var(--amber); font-size: 13px; margin-top: 14px; font-family: var(--mono); letter-spacing: .5px; }

/* Empty */
.md-empty { padding: 60px 20px; text-align: center; color: var(--txt-dim); }
.md-empty i { font-size: 3rem; opacity: .25; display: block; margin-bottom: 14px; color: var(--amber); }

/* Acceleration */
.acc-accel { color: #ff6d00; font-size: 9px; font-weight: 700; }
.acc-decel { color: #90caf9; font-size: 9px; font-weight: 700; }
.acc-surge { color: #ffe082; font-size: 9px; font-weight: 700; }
.acc-stable{ color: var(--dim); font-size: 9px; }

@media (max-width: 768px) {
    .logic-grid { grid-template-columns: 1fr 1fr; }
    .md-stats { gap: 8px; }
}
</style>
@endpush

<div class="md-page">

    {{-- ── Header ────────────────────────────────────────────── --}}
    <div class="md-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4>
                    📡 4-Day OI Accumulation Tracker
                    <span class="md-badge">MULTI-DAY</span>
                    <span class="md-badge-new">SLOW MONEY DETECTOR</span>
                </h4>
                <p>
                    Tracks OI positioning over 4 trading days — catches sustained call/put writing, stealth accumulation &amp; slow distribution
                    that a single prev-day comparison would completely miss.
                </p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn-md-reset" style="text-decoration:none; display:inline-block; line-height:1.6;">← EOD 1-Day</a>
                <a href="{{ route('oiiv-auto.index') }}" class="btn-md-reset" style="text-decoration:none; display:inline-block; line-height:1.6;">OI+IV</a>
            </div>
        </div>
    </div>

    {{-- ── Logic cards ──────────────────────────────────────────── --}}
    <div class="logic-grid">
        <div class="logic-card">
            <h6>📅 How it works</h6>
            <ul>
                <li>Fetches OI at <strong>D0 (today)</strong> and D1→D4 (4 prev days)</li>
                <li>Computes % change for <strong>each day individually</strong></li>
                <li>Scores trend consistency across all 4 days</li>
                <li>Detects <strong>who is accumulating slowly</strong></li>
            </ul>
        </div>
        <div class="logic-card">
            <h6>🔍 Pattern Types</h6>
            <ul>
                <li><strong>STEADY_BUILDUP</strong> — all 4 days same direction</li>
                <li><strong>STEALTH_BUILDUP</strong> — small consistent moves (hidden)</li>
                <li><strong>LATE_SURGE</strong> — flat then sudden spike</li>
                <li><strong>REVERSAL</strong> — direction flip last 2 days</li>
            </ul>
        </div>
        <div class="logic-card">
            <h6>⚡ Manipulation Score</h6>
            <ul>
                <li><strong>0–3</strong> → Random / noise</li>
                <li><strong>4–6</strong> → Moderate positioning</li>
                <li><strong>7–8</strong> → Likely deliberate</li>
                <li><strong>9–10</strong> → High probability slow money</li>
            </ul>
        </div>
        <div class="logic-card">
            <h6>🎯 Signal Logic</h6>
            <ul>
                <li>CE steady buildup 4d → <strong>BEARISH</strong> (call writing)</li>
                <li>CE steady unwind 4d → <strong>BULLISH</strong> (writers exit)</li>
                <li>PE steady buildup 4d → <strong>BULLISH</strong> (put writing)</li>
                <li>PE steady unwind 4d → <strong>BEARISH</strong> (put exit)</li>
            </ul>
        </div>
    </div>

    {{-- ── Filters ──────────────────────────────────────────────── --}}
    <div class="md-filter">
        <div class="row mb-2">
            <div class="col-md-2">
                <label>From Date</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-2">
                <label>To Date</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-2">
                <label>Symbols</label>
                <select id="symbol_filter" class="form-control" multiple size="2"></select>
            </div>
            <div class="col-md-2">
                <label>4-Day Signal</label>
                <select id="signal_filter" class="form-control">
                    <option value="">All Signals</option>
                    <option value="STRONG_BULLISH">Strong Bullish</option>
                    <option value="BULLISH">Bullish</option>
                    <option value="STRONG_BEARISH">Strong Bearish</option>
                    <option value="BEARISH">Bearish</option>
                    <option value="NEUTRAL">Neutral</option>
                </select>
            </div>
            <div class="col-md-2">
                <label>Pattern</label>
                <select id="pattern_filter" class="form-control">
                    <option value="">All Patterns</option>
                    <option value="STEADY_BUILDUP">Steady Buildup</option>
                    <option value="STEADY_UNWINDING">Steady Unwinding</option>
                    <option value="STEALTH_BUILDUP">Stealth Buildup</option>
                    <option value="STEALTH_UNWINDING">Stealth Unwinding</option>
                    <option value="LATE_SURGE">Late Surge</option>
                    <option value="REVERSAL_BULLISH">Reversal Bullish</option>
                    <option value="REVERSAL_BEARISH">Reversal Bearish</option>
                    <option value="CHOPPY">Choppy</option>
                </select>
            </div>
            <div class="col-md-2">
                <label>Min Manip Score (0–10)</label>
                <input type="number" id="min_manip" class="form-control" value="0" min="0" max="10" />
            </div>
        </div>
        <div class="text-center mt-2">
            <button type="button" id="btn_run" class="btn-md-run">📡 Scan 4-Day OI</button>
            <button type="button" id="btn_reset" class="btn-md-reset ml-2">↺ Reset</button>
        </div>
    </div>

    {{-- ── Stats ───────────────────────────────────────────────── --}}
    <div class="md-stats">
        <div class="md-stat amber"><small>Total</small><strong id="st_total" style="color:var(--amber);">0</strong></div>
        <div class="md-stat" style="border-top-color:#ff6d00;"><small>🔥 Very High Conf</small><strong id="st_vh" style="color:#ff6d00;">0</strong></div>
        <div class="md-stat green"><small>📈 BUY CE</small><strong id="st_ce" style="color:var(--green);">0</strong></div>
        <div class="md-stat red"><small>📉 BUY PE</small><strong id="st_pe" style="color:var(--red);">0</strong></div>
        <div class="md-stat cyan"><small>⏸ WAIT</small><strong id="st_wait" style="color:var(--cyan);">0</strong></div>
        <div class="md-stat purple"><small>🕵 Stealth Patterns</small><strong id="st_stealth" style="color:var(--purple);">0</strong></div>
        <div class="md-stat amber"><small>🔄 Reversals</small><strong id="st_reversal" style="color:var(--amber);">0</strong></div>
        <div class="md-stat dim"><small>Classic ≠ 4-Day</small><strong id="st_mismatch" style="color:#a0aec0;">0</strong></div>
    </div>

    {{-- ── Table ───────────────────────────────────────────────── --}}
    <div class="md-table-wrap">
        <div class="md-loading" id="md-loading" style="display:none;">
            <div class="md-spinner"></div>
            <div class="md-load-txt">Scanning 4-day OI patterns...</div>
        </div>
        <div class="md-table-scroll">
            <table class="md-table">
                <thead>
                    {{-- Group header row --}}
                    <tr class="th-group-row">
                        <th colspan="4" style="color:var(--txt-dim); border-bottom:1px solid var(--line); background:#080c14;">BASE</th>
                        <th colspan="6" style="color:#ff6090; border-bottom:1px solid rgba(255,23,68,0.3); background:rgba(255,23,68,0.04);">── CE (Call) ─────────────────</th>
                        <th colspan="6" style="color:#69f0ae; border-bottom:1px solid rgba(0,200,83,0.3); background:rgba(0,200,83,0.04);">── PE (Put) ──────────────────</th>
                        <th colspan="5" style="color:var(--amber); border-bottom:1px solid rgba(255,179,0,0.4); background:rgba(255,179,0,0.04);">── 4-DAY SIGNAL ──────────────</th>
                        <th colspan="3" style="color:var(--txt-dim); border-bottom:1px solid var(--line); background:#09101c;">── CLASSIC (1D) ──</th>
                        <th colspan="3" style="color:#90caf9; border-bottom:1px solid rgba(41,121,255,0.3); background:rgba(41,121,255,0.04);">── CONTEXT ──────</th>
                    </tr>
                    <tr>
                        {{-- Base --}}
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>Spot ₹</th>

                        {{-- CE --}}
                        <th class="th-ce" style="background:rgba(255,23,68,0.04);">CE 4-Day<br><small style="opacity:.7;">Sparkline</small></th>
                        <th class="th-ce" style="background:rgba(255,23,68,0.04);">CE Net<br><small style="opacity:.7;">4D %</small></th>
                        <th class="th-ce" style="background:rgba(255,23,68,0.04);">CE Pattern</th>
                        <th class="th-ce" style="background:rgba(255,23,68,0.04);">CE Trend<br><small style="opacity:.7;">Score /4</small></th>
                        <th class="th-ce" style="background:rgba(255,23,68,0.04);">CE Accel</th>
                        <th class="th-ce" style="background:rgba(255,23,68,0.04);">CE Signal</th>

                        {{-- PE --}}
                        <th class="th-pe" style="background:rgba(0,200,83,0.04);">PE 4-Day<br><small style="opacity:.7;">Sparkline</small></th>
                        <th class="th-pe" style="background:rgba(0,200,83,0.04);">PE Net<br><small style="opacity:.7;">4D %</small></th>
                        <th class="th-pe" style="background:rgba(0,200,83,0.04);">PE Pattern</th>
                        <th class="th-pe" style="background:rgba(0,200,83,0.04);">PE Trend<br><small style="opacity:.7;">Score /4</small></th>
                        <th class="th-pe" style="background:rgba(0,200,83,0.04);">PE Accel</th>
                        <th class="th-pe" style="background:rgba(0,200,83,0.04);">PE Signal</th>

                        {{-- 4-Day Signal --}}
                        <th class="th-comb" style="background:rgba(255,179,0,0.04);">4D Signal</th>
                        <th class="th-comb" style="background:rgba(255,179,0,0.04);">Action</th>
                        <th class="th-comb" style="background:rgba(255,179,0,0.04);">Confidence</th>
                        <th class="th-comb" style="background:rgba(255,179,0,0.04);">Manip<br><small style="opacity:.7;">Score</small></th>
                        <th class="th-comb" style="background:rgba(255,179,0,0.04); max-width:200px;">Reason</th>

                        {{-- Classic 1D --}}
                        <th class="th-cls">CE%<br><small style="opacity:.7;">D0 vs D1</small></th>
                        <th class="th-cls">PE%<br><small style="opacity:.7;">D0 vs D1</small></th>
                        <th class="th-cls">Classic<br><small style="opacity:.7;">1-Day</small></th>

                        {{-- Context --}}
                        <th style="background:rgba(41,121,255,0.04);">50 MA</th>
                        <th style="background:rgba(41,121,255,0.04);">Price<br><small style="opacity:.7;">Signal</small></th>
                        <th style="background:rgba(41,121,255,0.04);">Strength</th>
                    </tr>
                </thead>
                <tbody id="md-tbody">
                    <tr>
                        <td colspan="27" class="md-empty">
                            <i class="fas fa-satellite-dish"></i>
                            Click <strong>📡 Scan 4-Day OI</strong> to start
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('script')
<script>
let tableData = [];

/* ── Init ───────────────────────────────────────────── */
$(function() {
    loadSymbols();
    setTimeout(runScan, 400);
});

function loadSymbols() {
    $.get('{{ route("multiday-oi.symbols") }}', function(res) {
        if (!res.success) return;
        let o = '';
        res.symbols.forEach(s => { o += `<option value="${s}">${s}</option>`; });
        $('#symbol_filter').html(o);
    });
}

/* ── Run ────────────────────────────────────────────── */
function runScan() {
    const from     = $('#from_date').val();
    const to       = $('#to_date').val();
    const symbols  = $('#symbol_filter').val() || [];
    const sig      = $('#signal_filter').val();
    const pat      = $('#pattern_filter').val();
    const minManip = $('#min_manip').val() || 0;

    if (!from || !to) { alert('Please select dates'); return; }

    $('#md-loading').show();
    $('#md-tbody').html('');
    tableData = [];
    resetStats();

    $.ajax({
        url:  '{{ route("multiday-oi.analyze") }}',
        type: 'GET',
        data: { from_date: from, to_date: to, symbols, filter_signal: sig, filter_pattern: pat, min_manip_score: minManip },
        success: function(res) {
            $('#md-loading').hide();
            if (res.success && res.data && res.data.length > 0) {
                tableData = res.data;
                renderTable();
                updateStats();
            } else {
                $('#md-tbody').html(`<tr><td colspan="27" class="md-empty"><i class="fas fa-info-circle"></i>${res.message || 'No data found'}</td></tr>`);
            }
        },
        error: function() {
            $('#md-loading').hide();
            $('#md-tbody').html('<tr><td colspan="27" class="md-empty"><i class="fas fa-exclamation-triangle"></i>Error loading data</td></tr>');
        }
    });
}

/* ── Sparkline generator ────────────────────────────── */
function sparkline(chgs, side) {
    // chgs = [d1_chg, d2_chg, d3_chg, d4_chg]  (d1 = most recent)
    // Display oldest → newest (left to right): d4, d3, d2, d1
    const vals = [chgs[3], chgs[2], chgs[1], chgs[0]];
    const maxAbs = Math.max(...vals.map(Math.abs), 0.01);
    const labels = ['D4','D3','D2','D1'];

    let bars = '';
    let lbls = '';
    vals.forEach((v, i) => {
        const h   = Math.max(3, Math.round((Math.abs(v) / maxAbs) * 24));
        const cls = v > 0 ? 'pos' : (v < 0 ? 'neg' : 'zero');
        const title = `${labels[i]}: ${v > 0 ? '+' : ''}${v.toFixed(2)}%`;
        bars += `<div class="spark-bar ${cls}" style="height:${h}px;" title="${title}"></div>`;
        lbls += `<div class="spark-label">${labels[i]}</div>`;
    });

    return `<div class="spark-wrap">${bars}</div><div class="spark-label-row">${lbls}</div>`;
}

/* ── Trend dots ─────────────────────────────────────── */
function trendDots(score) {
    let html = '<div class="trend-dots">';
    for (let i = 0; i < 4; i++) {
        html += `<div class="trend-dot ${i < score ? 'on' : 'off'}"></div>`;
    }
    html += `</div><small style="color:var(--txt-dim);font-size:9px;">${score}/4</small>`;
    return html;
}

/* ── Manip score bar ────────────────────────────────── */
function manipBar(score) {
    const pct = (score / 10) * 100;
    const rem = 100 - pct;
    const col = score >= 8 ? '#ff1744' : (score >= 6 ? '#ffb300' : (score >= 4 ? '#00c853' : '#718096'));
    return `<div class="manip-wrap">
        <div class="manip-bar"><div class="manip-fill" style="width:${rem}%;"></div></div>
        <span class="manip-score" style="color:${col};">${score}</span>
    </div>`;
}

/* ── Badge helpers ──────────────────────────────────── */
function patternBadge(p) {
    const map = {
        'STEADY_BUILDUP':    ['b b-steady-build',  '📈📈 STEADY BUILD'],
        'MOSTLY_BUILDUP':    ['b b-mostly-build',  '📈 MOSTLY BUILD'],
        'STEADY_UNWINDING':  ['b b-steady-unw',    '📉📉 STEADY UNWIND'],
        'MOSTLY_UNWINDING':  ['b b-mostly-unw',    '📉 MOSTLY UNWIND'],
        'STEALTH_BUILDUP':   ['b b-stealth-build', '🕵 STEALTH BUILD'],
        'STEALTH_UNWINDING': ['b b-stealth-unw',   '🕵 STEALTH UNWIND'],
        'REVERSAL_BULLISH':  ['b b-rev-bull',      '🔄 REV BULL'],
        'REVERSAL_BEARISH':  ['b b-rev-bear',      '🔄 REV BEAR'],
        'LATE_SURGE':        ['b b-late-surge',    '⚡ LATE SURGE'],
        'EARLY_DISTRIBUTION':['b b-early-dist',    '📊 EARLY DIST'],
        'CHOPPY':            ['b b-choppy',        '〰 CHOPPY'],
    };
    const [cls, label] = map[p] || ['b b-choppy', p || '—'];
    return `<span class="${cls}">${label}</span>`;
}

function signalBadge(s) {
    const map = {
        'STRONG_BULLISH': ['b b-sb',  '🟢🟢 S.BULL'],
        'BULLISH':         ['b b-b',   '🟢 BULL'],
        'BULLISH_WEAK':    ['b b-bw',  '🟢 BULL~'],
        'STRONG_BEARISH':  ['b b-sbe', '🔴🔴 S.BEAR'],
        'BEARISH':         ['b b-be',  '🔴 BEAR'],
        'BEARISH_WEAK':    ['b b-bew', '🔴 BEAR~'],
        'NEUTRAL':         ['b b-neu', '⚪ NEUT'],
    };
    const [cls, label] = map[s] || ['b b-neu', s || '—'];
    return `<span class="${cls}">${label}</span>`;
}

function accelBadge(a) {
    const map = {
        'ACCELERATING': ['acc-accel', '🔥 ACCEL'],
        'DECELERATING': ['acc-decel', '❄ DECEL'],
        'LATE_SURGE':   ['acc-surge', '⚡ SURGE'],
        'STABLE':       ['acc-stable','→ STABLE'],
    };
    const [cls, label] = map[a] || ['acc-stable', a || '—'];
    return `<span class="${cls}">${label}</span>`;
}

function combSignalBadge(s) {
    const map = {
        'STRONG_BULLISH': ['b b-sb',  '🟢🟢 STRONG BULL'],
        'BULLISH':         ['b b-b',   '🟢 BULLISH'],
        'STRONG_BEARISH':  ['b b-sbe', '🔴🔴 STRONG BEAR'],
        'BEARISH':         ['b b-be',  '🔴 BEARISH'],
        'NEUTRAL':         ['b b-neu', '⚪ NEUTRAL'],
    };
    const [cls, label] = map[s] || ['b b-neu', s || '—'];
    return `<span class="${cls}">${label}</span>`;
}

function actionBadge(a) {
    if (a === 'BUY CE') return '<span class="act-ce">📈 BUY CE</span>';
    if (a === 'BUY PE') return '<span class="act-pe">📉 BUY PE</span>';
    return '<span class="act-wt">⏸ WAIT</span>';
}

function confBadge(c) {
    const map = {
        'VERY_HIGH': ['conf-vh', '🔥 VERY HIGH'],
        'HIGH':      ['conf-h',  '✅ HIGH'],
        'MEDIUM':    ['conf-m',  '⚡ MEDIUM'],
        'LOW':       ['conf-l',  '💧 LOW'],
        'NONE':      ['conf-n',  '— NONE'],
    };
    const [cls, label] = map[c] || ['conf-n', '—'];
    return `<span class="${cls}">${label}</span>`;
}

function pctCell(v) {
    const n = parseFloat(v) || 0;
    const cls = n > 0 ? 'pct-pos' : (n < 0 ? 'pct-neg' : 'pct-zero');
    return `<span class="${cls}">${n > 0 ? '+' : ''}${n.toFixed(2)}%</span>`;
}

function netCell(v) {
    const n = parseFloat(v) || 0;
    const cls = n > 0 ? 'net-pos' : (n < 0 ? 'net-neg' : 'pct-zero');
    return `<span class="${cls}" style="font-weight:700;">${n > 0 ? '+' : ''}${n.toFixed(2)}%</span>`;
}

function maBadge(s) {
    if (s === 'BULLISH') return '<span class="ma-bull">▲ Bull</span>';
    if (s === 'BEARISH') return '<span class="ma-bear">▼ Bear</span>';
    return '<span class="ma-na">N/A</span>';
}

function classicBadge(s) {
    if (s === 'BULLISH') return '<span class="cl-bull">🟢 BULL</span>';
    if (s === 'BEARISH') return '<span class="cl-bear">🔴 BEAR</span>';
    return '<span class="cl-neut">⚪ NEUT</span>';
}

function priceBadge(s, pct) {
    const n = parseFloat(pct) || 0;
    const str = `${n > 0 ? '+' : ''}${n.toFixed(2)}%`;
    if (s === 'BULLISH') return `<span class="cl-bull">▲ ${str}</span>`;
    if (s === 'BEARISH') return `<span class="cl-bear">▼ ${str}</span>`;
    return '<span class="cl-neut">—</span>';
}

function strengthBadge(rank, signal) {
    if (rank === 'Normal') return '<span style="color:var(--dim);font-size:10px;">Normal</span>';
    const n    = rank.replace('Rank ', '');
    const bull = signal === 'BULLISH' || signal === 'STRONG_BULLISH';
    return `<span class="${bull ? 'cl-bull' : 'cl-bear'}">${bull ? '🟢' : '🔴'} R${n}</span>`;
}

/* ── Render table ───────────────────────────────────── */
function renderTable() {
    let html = '';

    tableData.forEach(function(row, i) {
        const mismatch   = row.combined_signal !== 'NEUTRAL' &&
                           row.combined_signal.replace('STRONG_','') !== row.classic_signal;
        const isHighConf = row.combined_confidence === 'VERY_HIGH' || row.combined_confidence === 'HIGH';
        const isStealth  = row.ce_pattern.includes('STEALTH') || row.pe_pattern.includes('STEALTH');
        const rowCls     = row.manip_score >= 8 ? 'row-very-high' : (isHighConf ? 'row-highlight' : '');

        const ceChgs = [row.ce_chg_d1, row.ce_chg_d2, row.ce_chg_d3, row.ce_chg_d4];
        const peChgs = [row.pe_chg_d1, row.pe_chg_d2, row.pe_chg_d3, row.pe_chg_d4];

        html += `<tr class="${rowCls}">
            <td style="color:var(--txt-dim);">${i+1}</td>
            <td style="color:var(--amber); font-weight:700; font-family:var(--mono);">${row.date}</td>
            <td><strong style="color:var(--txt);">${row.symbol}</strong></td>
            <td style="color:var(--txt-dim); font-family:var(--mono);">₹${Number(row.spot_price).toLocaleString('en-IN')}</td>

            {{-- CE block --}}
            <td style="background:rgba(255,23,68,0.03);">${sparkline(ceChgs, 'CE')}</td>
            <td style="background:rgba(255,23,68,0.03);">${netCell(row.ce_net_4d)}</td>
            <td style="background:rgba(255,23,68,0.03);">${patternBadge(row.ce_pattern)}</td>
            <td style="background:rgba(255,23,68,0.03);">${trendDots(row.ce_trend_score)}</td>
            <td style="background:rgba(255,23,68,0.03);">${accelBadge(row.ce_acceleration)}</td>
            <td style="background:rgba(255,23,68,0.03);">${signalBadge(row.ce_signal)}</td>

            {{-- PE block --}}
            <td style="background:rgba(0,200,83,0.03);">${sparkline(peChgs, 'PE')}</td>
            <td style="background:rgba(0,200,83,0.03);">${netCell(row.pe_net_4d)}</td>
            <td style="background:rgba(0,200,83,0.03);">${patternBadge(row.pe_pattern)}</td>
            <td style="background:rgba(0,200,83,0.03);">${trendDots(row.pe_trend_score)}</td>
            <td style="background:rgba(0,200,83,0.03);">${accelBadge(row.pe_acceleration)}</td>
            <td style="background:rgba(0,200,83,0.03);">${signalBadge(row.pe_signal)}</td>

            {{-- 4-day combined --}}
            <td style="background:rgba(255,179,0,0.03);">${combSignalBadge(row.combined_signal)}</td>
            <td style="background:rgba(255,179,0,0.03);">${actionBadge(row.combined_action)}</td>
            <td style="background:rgba(255,179,0,0.03);">${confBadge(row.combined_confidence)}</td>
            <td style="background:rgba(255,179,0,0.03);">${manipBar(row.manip_score)}</td>
            <td style="background:rgba(255,179,0,0.03); text-align:left; max-width:200px; font-size:9px; color:var(--txt-dim); white-space:normal; line-height:1.4;">${row.combined_reason || '—'}</td>

            {{-- Classic 1-day --}}
            <td>${pctCell(row.classic_ce_pct)}</td>
            <td>${pctCell(row.classic_pe_pct)}</td>
            <td>${classicBadge(row.classic_signal)}${mismatch ? '<span class="mismatch-dot" title="Classic vs 4-Day mismatch"></span>' : ''}</td>

            {{-- Context --}}
            <td style="background:rgba(41,121,255,0.03);">${maBadge(row.fut_50ma_signal)}</td>
            <td style="background:rgba(41,121,255,0.03);">${priceBadge(row.price_signal, row.price_chg_pct)}</td>
            <td style="background:rgba(41,121,255,0.03);">${strengthBadge(row.strength_rank, row.combined_signal)}</td>
        </tr>`;
    });

    $('#md-tbody').html(html);
}

/* ── Stats ──────────────────────────────────────────── */
function updateStats() {
    const total    = tableData.length;
    const vh       = tableData.filter(r => r.combined_confidence === 'VERY_HIGH').length;
    const ce       = tableData.filter(r => r.combined_action === 'BUY CE').length;
    const pe       = tableData.filter(r => r.combined_action === 'BUY PE').length;
    const wait     = tableData.filter(r => r.combined_action === 'WAIT').length;
    const stealth  = tableData.filter(r => r.ce_pattern.includes('STEALTH') || r.pe_pattern.includes('STEALTH')).length;
    const reversal = tableData.filter(r => r.ce_pattern.includes('REVERSAL') || r.pe_pattern.includes('REVERSAL')).length;
    const mismatch = tableData.filter(r => {
        return r.combined_signal !== 'NEUTRAL' &&
               r.combined_signal.replace('STRONG_','') !== r.classic_signal;
    }).length;

    $('#st_total').text(total);
    $('#st_vh').text(vh);
    $('#st_ce').text(ce);
    $('#st_pe').text(pe);
    $('#st_wait').text(wait);
    $('#st_stealth').text(stealth);
    $('#st_reversal').text(reversal);
    $('#st_mismatch').text(mismatch);
}

function resetStats() {
    $('#st_total,#st_vh,#st_ce,#st_pe,#st_wait,#st_stealth,#st_reversal,#st_mismatch').text('0');
}

/* ── Events ─────────────────────────────────────────── */
$('#btn_run').click(runScan);
$('#btn_reset').click(function() {
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter,#signal_filter,#pattern_filter').val('');
    $('#min_manip').val(0);
    tableData = [];
    resetStats();
    $('#md-tbody').html('<tr><td colspan="27" class="md-empty"><i class="fas fa-satellite-dish"></i>Click <strong>📡 Scan 4-Day OI</strong> to start</td></tr>');
    setTimeout(runScan, 200);
});
</script>
@endpush