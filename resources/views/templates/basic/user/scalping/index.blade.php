@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
.ct thead th,.ct tbody td{text-align:center !important;padding:5px 3px !important;font-size:9.5px !important;vertical-align:middle;}
.ct thead th:nth-child(1),.ct tbody td:nth-child(1){position:sticky;left:0;z-index:10;min-width:22px;}
.ct thead th:nth-child(2),.ct tbody td:nth-child(2){position:sticky;left:22px;z-index:10;min-width:44px;}
.ct thead th:nth-child(3),.ct tbody td:nth-child(3){position:sticky;left:66px;z-index:10;min-width:62px;}
.tr{overflow-x:auto;}.ct{min-width:2300px;}
.lo{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(3,4,9,.97);display:flex;flex-direction:column;justify-content:center;align-items:center;z-index:1000;border-radius:9px;}
.sp{width:34px;height:34px;border:3px solid #06101c;border-top:3px solid #00d2ff;border-radius:50%;animation:spin .65s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}
.lt{color:#3a7a94;margin-top:10px;font-size:11px;font-weight:600;}
.ph{background:linear-gradient(135deg,#02040a,#040710,#030c1a);color:white;padding:13px 17px;border-radius:9px;margin-bottom:10px;border:1px solid rgba(0,210,255,0.12);}
.ld{display:inline-block;width:5px;height:5px;border-radius:50%;background:#ef4444;margin-right:4px;animation:blink 1.2s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.1}}
.fw{background:#02040a;padding:11px 13px;border-radius:8px;margin-bottom:10px;border:1px solid rgba(255,255,255,0.04);}
.fw label{color:rgba(255,255,255,0.32)!important;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;display:block;}
.fw .form-control{background:rgba(255,255,255,0.022);border:1px solid rgba(255,255,255,0.06);color:white;font-size:10.5px;padding:4px 6px;border-radius:4px;}
.pb{background:rgba(0,210,255,0.018);border:1px solid rgba(0,210,255,0.06);border-radius:5px;padding:7px 9px;}
.sc{background:rgba(255,255,255,0.014);border:1px solid rgba(255,255,255,0.038);border-radius:7px;padding:7px 5px;text-align:center;border-left:3px solid #00d2ff;margin-bottom:6px;}
.sc small{display:block;color:rgba(255,255,255,0.24);font-size:8px;text-transform:uppercase;letter-spacing:.3px;}
.sc strong{display:block;font-size:1rem;font-weight:700;color:white;margin-top:1px;}
.bb{background:#020b04;color:#22c55e;border:1px solid #041509;padding:1px 5px;border-radius:3px;font-weight:700;font-size:8px;}
.br2{background:#0a0202;color:#ef4444;border:1px solid #140505;padding:1px 5px;border-radius:3px;font-weight:700;font-size:8px;}
.bce{background:#020709;color:#00d2ff;border:1px solid #040f18;padding:1px 5px;border-radius:3px;font-weight:700;font-size:8px;}
.bpe{background:#050210;color:#c084fc;border:1px solid #0d0420;padding:1px 5px;border-radius:3px;font-weight:700;font-size:8px;}
.s3{background:#080600;color:#fbbf24;padding:1px 4px;border-radius:3px;font-size:8px;font-weight:700;}
.s2{background:#00070e;color:#60a5fa;padding:1px 4px;border-radius:3px;font-size:8px;font-weight:700;}
.vs{background:#040a02;color:#86efac;padding:1px 4px;border-radius:3px;font-size:7.5px;font-weight:700;}
.ch{background:#070300;color:#fb923c;border:1px solid #140700;padding:1px 4px;border-radius:3px;font-size:7.5px;font-weight:700;}
.l1{background:#001008;color:#4ade80;border:1px solid #001e10;padding:1px 4px;border-radius:3px;font-size:7.5px;}
.za{background:#030904;color:#4ade80;padding:1px 4px;border-radius:3px;font-size:7.5px;font-weight:700;}
.zm{background:#080600;color:#fbbf24;padding:1px 4px;border-radius:3px;font-size:7.5px;font-weight:700;}
.fF{background:#020b04;color:#22c55e;border:1px solid #041509;padding:2px 5px;border-radius:3px;font-weight:700;font-size:8px;display:inline-block;}
.fU{background:#060606;color:#2d3748;border:1px solid #111;padding:2px 5px;border-radius:3px;font-size:8px;display:inline-block;}
.oT{background:#020b04;color:#22c55e;border:1px solid #041509;padding:2px 5px;border-radius:3px;font-weight:700;font-size:8px;display:inline-block;}
.oS{background:#0a0202;color:#ef4444;border:1px solid #140505;padding:2px 5px;border-radius:3px;font-weight:700;font-size:8px;display:inline-block;}
/* EOD EXIT — unique styling */
.oEOD{background:#0a0700;color:#f59e0b;border:1px solid #1e1200;padding:2px 5px;border-radius:3px;font-weight:700;font-size:8px;display:inline-block;}
.oO{background:#020510;color:#60a5fa;border:1px solid #040a20;padding:2px 5px;border-radius:3px;font-weight:700;font-size:8px;display:inline-block;animation:blink 2s ease-in-out infinite;}
.oE{background:#060606;color:#6b7280;border:1px solid #111;padding:2px 5px;border-radius:3px;font-size:8px;display:inline-block;}
.eH{background:#020b04;color:#22c55e;border:1px solid #041509;padding:2px 5px;border-radius:3px;font-weight:700;font-size:8px;display:inline-block;}
.eM{background:#080600;color:#fbbf24;border:1px solid #160d00;padding:2px 5px;border-radius:3px;font-weight:700;font-size:8px;display:inline-block;}
.eW{background:#070300;color:#fb923c;border:1px solid #130700;padding:2px 5px;border-radius:3px;font-size:8px;display:inline-block;}
.eN{background:#040404;color:#374151;border:1px solid #0a0a0a;padding:2px 5px;border-radius:3px;font-size:8px;display:inline-block;}
.eB{background:#0a0202;color:#ef4444;border:1px solid #140505;padding:2px 5px;border-radius:3px;font-size:8px;display:inline-block;text-decoration:line-through;}
.closs{background:#0f0500;color:#fb923c;border:1px solid #1e0a00;padding:1px 4px;border-radius:3px;font-size:7.5px;font-weight:700;}
.pp{color:#22c55e;font-weight:700;}.pn{color:#ef4444;font-weight:700;}.pm{color:rgba(255,255,255,0.14);font-size:9px;}
.hp{color:#22c55e;font-weight:700;}.lp{color:#ef4444;font-weight:700;}.rp{color:#fbbf24;font-weight:700;}
.rb{display:inline-block;height:5px;border-radius:2px;background:linear-gradient(90deg,#ef4444,#fbbf24,#22c55e);vertical-align:middle;margin-left:2px;min-width:2px;}
.tn{display:flex;gap:3px;margin-bottom:8px;flex-wrap:wrap;}
.tb{background:rgba(255,255,255,0.018);border:1px solid rgba(255,255,255,0.045);color:rgba(255,255,255,0.28);padding:4px 10px;border-radius:4px;font-size:10px;cursor:pointer;}
.tb.active{background:rgba(0,210,255,0.06);border-color:rgba(0,210,255,0.16);color:#00d2ff;font-weight:700;}
.tp{display:none;}.tp.active{display:block;}
.tw{background:#02040a;border-radius:8px;border:1px solid rgba(255,255,255,0.032);overflow-x:auto;}
.pt{width:100%;font-size:9.5px;border-collapse:collapse;}
.pt th{background:rgba(0,210,255,0.025);color:rgba(0,210,255,0.48);font-size:8px;text-transform:uppercase;letter-spacing:.35px;padding:5px 6px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.032);}
.pt td{padding:4px 6px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.018);color:rgba(255,255,255,0.58);}
.pt tr:hover td{background:rgba(255,255,255,0.008);}
.cw{background:#02040a;border:1px solid rgba(255,255,255,0.032);border-radius:8px;padding:11px;}
tr.s3r td{background:rgba(251,191,36,0.014)!important;}
tr.br td{opacity:.28;} tr.ur td{opacity:.46;}
.sb{display:inline-block;height:6px;border-radius:2px;vertical-align:middle;margin-left:3px;min-width:3px;}
/* expectancy chip */
.exp-pos{background:#041208;color:#22c55e;border:1px solid #082416;padding:2px 6px;border-radius:3px;font-weight:700;font-size:8px;display:inline-block;}
.exp-neg{background:#100304;color:#ef4444;border:1px solid #200608;padding:2px 6px;border-radius:3px;font-weight:700;font-size:8px;display:inline-block;}
.exp-zer{background:#070707;color:#6b7280;border:1px solid #111;padding:2px 6px;border-radius:3px;font-size:8px;display:inline-block;}
</style>
@endpush

<section class="pt-40 pb-50" style="background:#010206;min-height:100vh;">
<div class="container-fluid content-container">

{{-- Header --}}
<div class="ph">
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:7px;">
        <div>
            <h4 style="color:#00d2ff;margin:0;font-size:15px;font-weight:700;">
                <span class="ld"></span>Volatile Index Scalping v7
                <span style="background:#060300;color:#f59e0b;border:1px solid #120700;font-size:8.5px;font-weight:700;padding:1px 6px;border-radius:3px;margin-left:5px;">EOD CLOSE + EXPECTANCY</span>
            </h4>
            <p style="color:rgba(255,255,255,0.26);font-size:9.5px;margin:3px 0 0;line-height:1.5;">
                All open trades closed at 15:00 · Expectancy-based edge · Simpler pattern key (more samples) · Consecutive loss protection
            </p>
        </div>
        <div>
            <div id="clock" style="color:#00d2ff;font-size:13px;font-weight:700;letter-spacing:2px;font-family:monospace;text-align:right;">--:--:--</div>
            <div id="mst" style="font-size:8px;color:rgba(255,255,255,0.24);text-align:right;">—</div>
        </div>
    </div>
</div>

{{-- Concept --}}
<div style="background:rgba(0,210,255,0.018);border:1px solid rgba(0,210,255,0.08);border-radius:6px;padding:8px 12px;margin-bottom:9px;font-size:9.5px;color:rgba(255,255,255,0.42);line-height:1.65;">
    <strong style="color:#00d2ff;">v7 changes:</strong>
    <span style="color:#f59e0b;">EOD Exit</span> — any trade not hitting Target/SL is closed at <strong style="color:white;">15:00 candle close</strong> (not EXPIRED at zero).
    <span style="color:#22c55e;">Expectancy</span> = (Win% × AvgWin) − (Loss% × AvgLoss) — now the primary edge metric.
    <span style="color:#c084fc;">Simpler pattern key</span> (5 dims not 7) = more samples per pattern = stronger signal.
    <span style="color:#fb923c;">Consecutive loss guard</span> — pattern blocked after 3 consecutive losses.
</div>

{{-- Filters --}}
<div class="fw">
    <div class="row align-items-end">
        <div class="col-md-2">
            <label>Date</label>
            <input type="date" id="trade_date" class="form-control" value="{{ date('Y-m-d') }}"/>
        </div>
        <div class="col-md-2">
            <label>Symbols</label>
            <select id="symbol_filter" class="form-control" multiple size="3">
                <option value="NIFTY" selected>NIFTY</option>
                <option value="BANKNIFTY" selected>BANKNIFTY</option>
                <option value="SENSEX" selected>SENSEX</option>
            </select>
        </div>
        <div class="col-md-5">
            <label>Parameters</label>
            <div class="pb">
                <div class="row">
                    <div class="col" style="padding:0 3px;"><label>Price %</label><input type="number" id="price_move_pct" class="form-control" value="0.15" min="0.05" max="2" step="0.05" style="color:#00d2ff;text-align:center;font-weight:700;"/></div>
                    <div class="col" style="padding:0 3px;"><label>OI %</label><input type="number" id="oi_change_pct" class="form-control" value="2.5" min="0.5" max="30" step="0.5" style="color:#00d2ff;text-align:center;font-weight:700;"/></div>
                    <div class="col" style="padding:0 3px;"><label>Target %</label><input type="number" id="target_pct" class="form-control" value="50" min="20" max="150" step="5" style="color:#22c55e;text-align:center;font-weight:700;"/></div>
                    <div class="col" style="padding:0 3px;"><label>SL %</label><input type="number" id="sl_pct" class="form-control" value="25" min="10" max="60" step="5" style="color:#ef4444;text-align:center;font-weight:700;"/></div>
                    <div class="col" style="padding:0 3px;"><label>Vol ×</label><input type="number" id="vol_mult" class="form-control" value="1.4" min="1" max="5" step="0.1" style="color:#fbbf24;text-align:center;font-weight:700;"/></div>
                    <div class="col" style="padding:0 3px;"><label>History</label><input type="number" id="history_days" class="form-control" value="30" min="5" max="90" step="5" style="color:#c084fc;text-align:center;font-weight:700;"/></div>
                </div>
            </div>
        </div>
        <div class="col-md-1" style="padding:0 3px;">
            <label>Edge</label>
            <select id="use_edge" class="form-control" style="font-weight:700;">
                <option value="1">ON ✅</option>
                <option value="0">OFF</option>
            </select>
        </div>
        <div class="col-md-2 d-flex flex-column" style="gap:4px;">
            <button id="btn_run" style="background:#00d2ff;color:#000;border:none;font-weight:700;font-size:12px;padding:8px;border-radius:5px;cursor:pointer;width:100%;">⚡ Run</button>
            <button id="btn_reset" style="background:transparent;color:rgba(255,255,255,0.22);border:1px solid rgba(255,255,255,0.05);font-size:9.5px;padding:5px;border-radius:4px;cursor:pointer;width:100%;">↺ Reset</button>
        </div>
    </div>
</div>

{{-- Stats row 1 --}}
<div class="row mb-1">
    <div class="col-6 col-md-2"><div class="sc"><small>Signals</small><strong id="st-tot">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#22c55e;"><small>✅ Allowed</small><strong id="st-ok" style="color:#22c55e;">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#ef4444;"><small>❌ Blocked</small><strong id="st-blk" style="color:#ef4444;">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#fbbf24;"><small>Fill Rate</small><strong id="st-fr" style="color:#fbbf24;">0%</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#22c55e;"><small>Win Rate</small><strong id="st-wr" style="color:#22c55e;">0%</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#9b59b6;"><small>Total P/L</small><strong id="st-pl">₹0</strong></div></div>
</div>
{{-- Stats row 2 --}}
<div class="row mb-2">
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#22c55e;"><small>✅ Target</small><strong id="st-t" style="color:#22c55e;">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#ef4444;"><small>🛑 SL</small><strong id="st-s" style="color:#ef4444;">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#f59e0b;"><small>🕐 EOD Exit <small id="st-eod-note" style="font-size:7px;"></small></small><strong id="st-eod" style="color:#f59e0b;">0</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#22c55e;"><small>Expectancy ₹</small><strong id="st-exp">₹0</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#22c55e;"><small>Avg Win</small><strong id="st-aw" style="color:#22c55e;">₹0</strong></div></div>
    <div class="col-6 col-md-2"><div class="sc" style="border-left-color:#ef4444;"><small>Avg Loss</small><strong id="st-al" style="color:#ef4444;">₹0</strong></div></div>
</div>

{{-- Tabs --}}
<div class="tn">
    <button class="tb active" onclick="sw('signals',this)">⚡ Signals</button>
    <button class="tb" onclick="sw('patterns',this)">🧠 Patterns</button>
    <button class="tb" onclick="sw('moves',this)">📊 Candle Moves</button>
    <button class="tb" onclick="sw('tpat',this)">🔁 Time Pattern</button>
    <button class="tb" onclick="sw('chart',this)">📈 Chart</button>
</div>

{{-- SIGNALS --}}
<div class="tp active" id="tp-signals">
    <div style="position:relative;min-height:260px;" class="tw">
        <div class="lo" id="lol" style="display:none;">
            <div class="sp"></div>
            <div class="lt" id="ltxt">Scanning history...</div>
        </div>
        <div class="tr">
            <table class="table ct table-dark" style="background:transparent;margin:0;">
                <thead style="background:rgba(0,0,0,0.9);position:sticky;top:0;z-index:20;">
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2">Sig</th>
                        <th rowspan="2">Sym</th>
                        <th rowspan="2">Dir</th>
                        <th rowspan="2">Side</th>
                        <th rowspan="2">Str</th>
                        <th rowspan="2">Vol</th>
                        <th rowspan="2">Zone</th>
                        <th rowspan="2" style="max-width:80px;">Reason</th>
                        <th colspan="3" style="background:rgba(0,210,255,0.045);color:#00d2ff;font-size:7.5px;">FUT Move</th>
                        <th colspan="2" style="background:rgba(251,191,36,0.045);color:#fbbf24;font-size:7.5px;">OI %</th>
                        <th colspan="5" style="background:rgba(192,132,252,0.045);color:#c084fc;font-size:7.5px;">🧠 Pattern Engine</th>
                        <th colspan="4" style="background:rgba(34,197,94,0.045);color:#22c55e;font-size:7.5px;">Order</th>
                        <th colspan="6" style="background:rgba(245,158,11,0.045);color:#f59e0b;font-size:7.5px;">Result + EOD Exit</th>
                    </tr>
                    <tr>
                        <th style="background:rgba(34,197,94,0.03);">H%↑</th>
                        <th style="background:rgba(239,68,68,0.03);">L%↓</th>
                        <th style="background:rgba(251,191,36,0.03);">Rng%</th>
                        <th style="background:rgba(251,191,36,0.025);">CE OI</th>
                        <th style="background:rgba(251,191,36,0.025);">PE OI</th>
                        <th style="background:rgba(192,132,252,0.04);">Score</th>
                        <th style="background:rgba(192,132,252,0.025);">Win%</th>
                        <th style="background:rgba(192,132,252,0.025);">Fill%</th>
                        <th style="background:rgba(192,132,252,0.025);" title="Expectancy = AvgWin×WinPct − AvgLoss×LossPct">Expect ₹</th>
                        <th style="background:rgba(192,132,252,0.04);">Edge</th>
                        <th style="background:rgba(34,197,94,0.03);">Type</th>
                        <th style="background:rgba(34,197,94,0.03);">Entry ₹</th>
                        <th style="background:rgba(34,197,94,0.03);color:#22c55e;">Tgt ₹</th>
                        <th style="background:rgba(239,68,68,0.03);color:#ef4444;">SL ₹</th>
                        <th style="background:rgba(245,158,11,0.04);">Outcome</th>
                        <th style="background:rgba(245,158,11,0.025);">Time</th>
                        <th style="background:rgba(245,158,11,0.025);">EOD Exit ₹</th>
                        <th style="background:rgba(245,158,11,0.025);">Tgt P/L</th>
                        <th style="background:rgba(96,165,250,0.05);">Actual P/L</th>
                        <th style="background:rgba(96,165,250,0.05);">ROI%</th>
                    </tr>
                </thead>
                <tbody id="sig-tb">
                    <tr><td colspan="29" style="text-align:center;padding:45px;color:rgba(255,255,255,0.14);">
                        <div style="font-size:1.7rem;">⚡</div>
                        <div style="margin-top:7px;font-size:12px;">Click <strong style="color:#00d2ff;">Run</strong></div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- PATTERNS --}}
<div class="tp" id="tp-patterns">
    <div class="tw" style="padding:11px;">
        <p style="color:rgba(255,255,255,0.26);font-size:9px;margin:0 0 7px;">
            Pattern stats from your last <strong id="hl2" style="color:white;">30</strong> days.
            <span style="color:#22c55e;">Expectancy > 0 + Win ≥ 38% + Fill ≥ 28% + Score ≥ 45 = TRADE</span>.
            Sorted by edge score.
        </p>
        <div class="tr">
            <table class="pt">
                <thead><tr>
                    <th>Pattern Key</th><th>Time</th><th>Str</th><th>Dir</th><th>Vol</th><th>Zone</th>
                    <th>Total</th><th>Filled</th><th>Wins</th><th>Losses</th><th>EOD</th>
                    <th style="color:#22c55e;">Win%</th><th style="color:#fbbf24;">Fill%</th>
                    <th style="color:#22c55e;">Avg Win ₹</th><th style="color:#ef4444;">Avg Loss ₹</th>
                    <th title="Expectancy = AvgWin×WinPct − AvgLoss×LossPct">Expectancy ₹</th>
                    <th style="color:#22c55e;">Total P/L</th>
                    <th style="color:#00d2ff;">Score</th><th>Conf</th>
                    <th>Consec Loss</th><th>Action</th>
                </tr></thead>
                <tbody id="pat-tb">
                    <tr><td colspan="21" style="text-align:center;padding:30px;color:rgba(255,255,255,0.14);">Run signals first</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MOVES --}}
<div class="tp" id="tp-moves">
    <div class="tw" style="padding:10px;">
        <table class="pt">
            <thead><tr>
                <th>Sym</th><th>Time</th><th>Zone</th><th>Prev</th><th>High</th><th>Low</th><th>Close</th>
                <th style="color:#22c55e;">H%↑</th><th style="color:#ef4444;">L%↓</th>
                <th style="color:#fbbf24;">Range%</th><th>Close%</th><th>Vol</th><th>CE₹</th><th>PE₹</th>
            </tr></thead>
            <tbody id="mv-tb">
                <tr><td colspan="14" style="text-align:center;padding:25px;color:rgba(255,255,255,0.14);">Run first</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- TIME PATTERN --}}
<div class="tp" id="tp-tpat">
    <div class="tw" style="padding:10px;">
        <table class="pt">
            <thead><tr>
                <th>Time</th><th>Zone</th><th>Syms</th><th>N</th>
                <th style="color:#22c55e;">Avg H%</th><th style="color:#22c55e;">Max H%</th>
                <th style="color:#ef4444;">Avg L%</th><th style="color:#ef4444;">Min L%</th>
                <th style="color:#fbbf24;">Avg Range%</th><th style="color:#fbbf24;">Max Range%</th>
                <th>Avg Close%</th><th>Slot Quality</th>
            </tr></thead>
            <tbody id="tp-tb">
                <tr><td colspan="12" style="text-align:center;padding:25px;color:rgba(255,255,255,0.14);">Run first</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- CHART --}}
<div class="tp" id="tp-chart">
    <div class="cw">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 style="color:rgba(255,255,255,0.38);font-size:9.5px;font-weight:700;text-transform:uppercase;margin:0;">Chart</h6>
            <div style="display:flex;gap:5px;align-items:center;">
                <select id="cs" style="background:rgba(255,255,255,0.018);border:1px solid rgba(255,255,255,0.06);color:white;font-size:9px;padding:2px 5px;border-radius:3px;">
                    <option value="NIFTY">NIFTY</option><option value="BANKNIFTY">BANKNIFTY</option><option value="SENSEX">SENSEX</option>
                </select>
                <select id="cm" style="background:rgba(255,255,255,0.018);border:1px solid rgba(255,255,255,0.06);color:white;font-size:9px;padding:2px 5px;border-radius:3px;">
                    <option value="move">High/Low/Range%</option>
                    <option value="oi">OI + Price</option>
                    <option value="vol">Volume Ratio</option>
                </select>
                <button id="btn_chart" style="background:rgba(0,210,255,0.055);color:#00d2ff;border:1px solid rgba(0,210,255,0.11);font-size:9px;padding:2px 8px;border-radius:3px;cursor:pointer;">Load</button>
            </div>
        </div>
        <canvas id="mc" height="100"></canvas>
    </div>
</div>

</div></section>
@endsection

@push('script')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let sigs=[],moves=[],pats=[],tpat=[],crows=[],mc=null;

(function tick(){
    const n=new Date(),p=v=>String(v).padStart(2,'0');
    document.getElementById('clock').textContent=`${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
    const m=n.getHours()*60+n.getMinutes(),el=document.getElementById('mst');
    if(m<555){el.textContent='Pre-market';el.style.color='#f59e0b';}
    else if(m>930){el.textContent='Market closed';el.style.color='#ef4444';}
    else{el.textContent=`Open · ${930-m}m left`;el.style.color='#22c55e';}
    setTimeout(tick,1000);
})();

function sw(n,btn){
    document.querySelectorAll('.tp').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.tb').forEach(b=>b.classList.remove('active'));
    document.getElementById('tp-'+n).classList.add('active'); btn.classList.add('active');
}
function ld(show,msg){
    if(msg)document.getElementById('ltxt').textContent=msg;
    document.getElementById('lol').style.display=show?'flex':'none';
}

function run(){
    const date=document.getElementById('trade_date').value;
    if(!date){alert('Select a date');return;}
    const syms=[...document.getElementById('symbol_filter').selectedOptions].map(o=>o.value);
    const hd=document.getElementById('history_days').value;
    document.getElementById('hl2').textContent=hd;
    const params=new URLSearchParams({
        date,
        price_move_pct:document.getElementById('price_move_pct').value,
        oi_change_pct:document.getElementById('oi_change_pct').value,
        target_pct:document.getElementById('target_pct').value,
        sl_pct:document.getElementById('sl_pct').value,
        vol_mult:document.getElementById('vol_mult').value,
        history_days:hd,
        use_edge:document.getElementById('use_edge').value,
    });
    syms.forEach(s=>params.append('symbols[]',s));
    ld(true,`Scanning ${hd} days of history...`);

    fetch(`{{ route('scalping.signals') }}?${params}`)
        .then(r=>r.json())
        .then(res=>{
            ld(false);
            if(res.success){
                sigs=res.signals||[];moves=res.candle_moves||[];
                pats=res.pattern_stats||[];tpat=res.time_pattern||[];
                renderSigs(sigs);renderPats(pats);renderMoves(moves);renderTP(tpat);
                updStats(res.stats||{});
                if(syms.length){document.getElementById('cs').value=syms[0];loadChart();}
            } else {
                noD('sig-tb',29,res.message||'No signals');
                noD('pat-tb',21,'—');noD('mv-tb',14,'—');noD('tp-tb',12,'—');resetStats();
            }
        }).catch(e=>{ld(false);noD('sig-tb',29,'Error: '+e.message);});
}

function renderSigs(s){
    if(!s.length){noD('sig-tb',29,'No signals');return;}
    document.getElementById('sig-tb').innerHTML=s.map((r,i)=>{
        const blk=!r.edge_allowed, fil=r.fill_status==='FILLED';
        const dB=r.direction==='BULLISH'?`<span class="bb">📈</span>`:`<span class="br2">📉</span>`;
        const sB=r.trade_side==='CE'?`<span class="bce">CE</span>`:`<span class="bpe">PE</span>`;
        const stB=r.strength===3?`<span class="s3">★★★</span>`:`<span class="s2">★★</span>`;
        const vB=r.volume_spike?`<span class="vs">🔊</span>`:`<span class="pm">—</span>`;
        const zB=r.time_zone==='aggressive'?`<span class="za">AGG</span>`:`<span class="zm">MOD</span>`;
        const tB=r.use_chase?`<span class="ch">🏃</span>`:`<span class="l1">L1</span>`;

        const sc=r.edge_score, scC=sc>=65?'#22c55e':sc>=45?'#fbbf24':'#ef4444';
        const eB=blk?`<span class="eB" title="${r.edge_reason}">❌</span>`
            :sc===null?`<span class="eN" title="${r.edge_reason}">? NEW</span>`
            :sc>=65?`<span class="eH">✅</span>`
            :sc>=45?`<span class="eM" title="${r.edge_reason}">⚠ OK</span>`
            :`<span class="eW" title="${r.edge_reason}">⚠ LOW</span>`;

        // Expectancy chip
        const exp=r.expectancy;
        const expB=exp===null?`<span class="exp-zer">—</span>`
            :exp>0?`<span class="exp-pos">+₹${exp}</span>`
            :exp===0?`<span class="exp-zer">₹0</span>`
            :`<span class="exp-neg">₹${exp}</span>`;

        // Consecutive loss warning
        const clB=r.consec_loss>=3?`<span class="closs">⚠ ${r.consec_loss}L</span>`:'';

        const fB=fil?`<span class="fF">✅</span>`:`<span class="fU">⏭</span>`;
        const oB={
            TARGET:'<span class="oT">✅ TGT</span>',
            SL:'<span class="oS">🛑 SL</span>',
            EOD_EXIT:'<span class="oEOD">🕐 EOD</span>',
            OPEN:'<span class="oO">🔵</span>',
            EXPIRED:'<span class="oE">⏰</span>',
            UNFILLED:'<span style="color:#1f2937;font-size:8px;">⏭</span>',
        }[r.outcome]||'—';

        const eodCell=r.eod_exit_price
            ? `<span style="color:#f59e0b;font-weight:700;font-size:8.5px;">₹${r.eod_exit_price}</span><br><small style="color:rgba(255,255,255,0.3);font-size:7.5px;" title="${r.eod_exit_note||''}">${r.eod_exit_note||''}</small>`
            : '<span class="pm">—</span>';

        const rc=`${r.strength===3?'s3r':''} ${blk?'br':''} ${!fil&&!blk?'ur':''}`;
        return `<tr class="${rc}">
            <td style="color:rgba(255,255,255,0.24);">${i+1}</td>
            <td><strong style="color:#00d2ff;font-size:9.5px;">${r.signal_time}</strong></td>
            <td><strong style="color:#f59e0b;">${r.symbol}</strong></td>
            <td>${dB}</td><td>${sB}</td><td>${stB}${clB}</td><td>${vB}</td><td>${zB}</td>
            <td style="text-align:left !important;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:rgba(255,255,255,0.34);font-size:8px;" title="${r.reason}">${r.reason}</td>
            <td style="background:rgba(34,197,94,0.025);"><span class="hp">+${r.fut_high_pct.toFixed(3)}%</span></td>
            <td style="background:rgba(239,68,68,0.025);"><span class="lp">${r.fut_low_pct.toFixed(3)}%</span></td>
            <td style="background:rgba(251,191,36,0.025);">
                <span class="rp">${r.fut_range_pct.toFixed(3)}%</span>
                <span class="rb" style="width:${Math.min(r.fut_range_pct*55,44)}px;"></span>
            </td>
            <td>${pct(r.ce_oi_pct)}</td><td>${pct(r.pe_oi_pct)}</td>
            <td style="background:rgba(192,132,252,0.035);">
                ${sc!==null?`<strong style="color:${scC};font-size:9px;">${sc}</strong>`:'<span class="pm">—</span>'}
            </td>
            <td>${r.pattern_win!==null?`<span style="color:${r.pattern_win>=38?'#22c55e':'#ef4444'};font-weight:700;font-size:8.5px;">${r.pattern_win}%</span>`:'<span class="pm">—</span>'}</td>
            <td>${r.pattern_fill!==null?`<span style="font-size:8.5px;">${r.pattern_fill}%</span>`:'<span class="pm">—</span>'}</td>
            <td>${expB}</td>
            <td style="background:rgba(192,132,252,0.04);">${eB}</td>
            <td>${tB}</td>
            <td><strong style="color:white;font-size:9px;">₹${r.entry_price.toFixed(2)}</strong></td>
            <td><strong class="pp">₹${r.target_price.toFixed(2)}</strong></td>
            <td><strong class="pn">₹${r.sl_price.toFixed(2)}</strong></td>
            <td>${oB}</td>
            <td style="color:rgba(255,255,255,0.3);font-size:8.5px;">${r.outcome_time||'—'}</td>
            <td>${eodCell}</td>
            <td>${r.target_pl!==null?`<span class="pp">+₹${Math.abs(r.target_pl).toFixed(0)}</span>`:'<span class="pm">—</span>'}</td>
            <td>${pl(r.actual_pl)}</td>
            <td>${roi(r.exit_roi_pct)}</td>
        </tr>`;
    }).join('');
}

function renderPats(p){
    if(!p.length){noD('pat-tb',21,'No pattern data');return;}
    document.getElementById('pat-tb').innerHTML=p.map(r=>{
        const ok=r.edge_score>=45&&r.filled>=10&&r.win_rate>=38&&r.fill_rate>=28&&r.expectancy>=0&&r.consec_loss<3;
        const sc=r.edge_score, scC=sc>=65?'#22c55e':sc>=45?'#fbbf24':'#ef4444';
        const cB={HIGH:`<span style="color:#22c55e;font-size:8px;font-weight:700;">●HI</span>`,MEDIUM:`<span style="color:#fbbf24;font-size:8px;font-weight:700;">●MED</span>`,LOW:`<span style="color:#fb923c;font-size:8px;font-weight:700;">●LO</span>`,NONE:`<span style="color:#374151;font-size:8px;">○</span>`}[r.confidence]||'—';
        const expB=r.expectancy>0?`<span class="exp-pos">+₹${r.expectancy}</span>`:r.expectancy===0?`<span class="exp-zer">₹0</span>`:`<span class="exp-neg">₹${r.expectancy}</span>`;
        const clB=r.consec_loss>=3?`<span class="closs">⚠ ${r.consec_loss}</span>`:`<span style="color:rgba(255,255,255,0.3);font-size:8.5px;">${r.consec_loss}</span>`;
        return `<tr style="${ok?'background:rgba(34,197,94,0.012)':''}">
            <td style="text-align:left!important;color:rgba(255,255,255,0.4);font-size:7.5px;font-family:monospace;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${r.pattern_key}">${r.pattern_key}</td>
            <td style="color:#00d2ff;font-size:8.5px;">${r.time_bucket}</td>
            <td>${r.strength===3?'<span class="s3">★★★</span>':'<span class="s2">★★</span>'}</td>
            <td>${r.direction==='BULLISH'?'<span class="bb">BULL</span>':'<span class="br2">BEAR</span>'}</td>
            <td style="font-size:8px;color:rgba(255,255,255,0.38);">${r.vol_bucket}</td>
            <td>${r.zone==='aggressive'?'<span class="za">AGG</span>':'<span class="zm">MOD</span>'}</td>
            <td style="color:rgba(255,255,255,0.36);">${r.total}</td>
            <td style="font-weight:700;">${r.filled}</td>
            <td style="color:#22c55e;">${r.wins}</td>
            <td style="color:#ef4444;">${r.losses}</td>
            <td style="color:#f59e0b;">${r.eod_exits||0}</td>
            <td><strong style="color:${r.win_rate>=38?'#22c55e':'#ef4444'};font-size:9px;">${r.win_rate}%</strong></td>
            <td><strong style="color:${r.fill_rate>=28?'#fbbf24':'#6b7280'};font-size:9px;">${r.fill_rate}%</strong></td>
            <td style="color:#22c55e;font-size:8.5px;">${r.avg_win_pl>0?'+':''}₹${(r.avg_win_pl||0).toFixed(0)}</td>
            <td style="color:#ef4444;font-size:8.5px;">₹${(r.avg_loss_pl||0).toFixed(0)}</td>
            <td>${expB}</td>
            <td>${pl(r.total_pl)}</td>
            <td>
                <strong style="color:${scC};font-size:10px;">${sc}</strong>
                <span class="sb" style="width:${Math.round(sc*0.44)}px;background:${scC};opacity:.3;"></span>
            </td>
            <td>${cB}</td>
            <td>${clB}</td>
            <td>${ok?'<span style="color:#22c55e;font-size:8px;font-weight:700;">✅</span>':'<span style="color:#1f2937;font-size:8px;">❌</span>'}</td>
        </tr>`;
    }).join('');
}

function renderMoves(m){
    if(!m.length){noD('mv-tb',14,'—');return;}
    const zB=t=>['09:15','09:30','09:45','10:00','10:15'].includes(t)?'<span class="za">AGG</span>':'<span class="zm">MOD</span>';
    document.getElementById('mv-tb').innerHTML=m.map(r=>`<tr>
        <td style="color:#f59e0b;font-weight:700;">${r.symbol}</td>
        <td style="color:#00d2ff;font-weight:700;">${r.time}</td>
        <td>${zB(r.time)}</td>
        <td>₹${r.prev_close.toLocaleString()}</td>
        <td style="color:#22c55e;">₹${r.high.toLocaleString()}</td>
        <td style="color:#ef4444;">₹${r.low.toLocaleString()}</td>
        <td>₹${r.close.toLocaleString()}</td>
        <td style="background:rgba(34,197,94,0.03);"><span class="hp">+${r.high_pct.toFixed(3)}%</span></td>
        <td style="background:rgba(239,68,68,0.03);"><span class="lp">${r.low_pct.toFixed(3)}%</span></td>
        <td style="background:rgba(251,191,36,0.04);">
            <span class="rp">${r.range_pct.toFixed(3)}%</span>
            <span class="rb" style="width:${Math.min(r.range_pct*58,50)}px;"></span>
        </td>
        <td>${pct(r.close_pct)}</td>
        <td style="color:rgba(255,255,255,0.3);">${r.volume.toLocaleString()}</td>
        <td style="color:#00d2ff;">₹${r.ce_close}</td>
        <td style="color:#c084fc;">₹${r.pe_close}</td>
    </tr>`).join('');
}

function renderTP(p){
    if(!p.length){noD('tp-tb',12,'—');return;}
    document.getElementById('tp-tb').innerHTML=p.map(r=>{
        const g=r.avg_range_pct>=0.25;
        const zB=r.zone==='aggressive'?'<span class="za">AGG</span>':'<span class="zm">MOD</span>';
        const q=g?`<span style="color:#22c55e;font-size:8px;font-weight:700;">✅ Active</span>`
            :r.avg_range_pct>=0.18?`<span style="color:#fbbf24;font-size:8px;">⚠ OK</span>`
            :`<span style="color:#ef4444;font-size:8px;">❌ Weak</span>`;
        return `<tr style="${g?'background:rgba(34,197,94,0.01)':''}">
            <td style="color:#00d2ff;font-weight:700;">${r.time}</td>
            <td>${zB}</td>
            <td style="color:#f59e0b;font-size:8.5px;">${r.symbols}</td>
            <td style="color:rgba(255,255,255,0.28);">${r.n}</td>
            <td><span class="hp">+${r.avg_high_pct.toFixed(3)}%</span></td>
            <td><span class="hp">+${r.max_high_pct.toFixed(3)}%</span></td>
            <td><span class="lp">${r.avg_low_pct.toFixed(3)}%</span></td>
            <td><span class="lp">${r.min_low_pct.toFixed(3)}%</span></td>
            <td>
                <span class="rp">${r.avg_range_pct.toFixed(3)}%</span>
                <span class="rb" style="width:${Math.min(r.avg_range_pct*65,52)}px;"></span>
            </td>
            <td><span class="rp">${r.max_range_pct.toFixed(3)}%</span></td>
            <td>${pct(r.avg_close_pct)}</td>
            <td>${q}</td>
        </tr>`;
    }).join('');
}

function updStats(s){
    document.getElementById('st-tot').textContent=s.total||0;
    document.getElementById('st-ok').textContent=s.edge_allowed||0;
    document.getElementById('st-blk').textContent=s.edge_blocked||0;
    document.getElementById('st-fr').textContent=(s.fill_rate||0)+'%';
    document.getElementById('st-wr').textContent=(s.winRate||0)+'%';
    document.getElementById('st-pl').innerHTML=pl(s.totalPL);
    document.getElementById('st-t').textContent=s.targets_count||0;
    document.getElementById('st-s').textContent=s.sls_count||0;
    const eodTotal=s.eod_exits_count||0;
    document.getElementById('st-eod').textContent=eodTotal;
    document.getElementById('st-eod-note').textContent=eodTotal>0?`${s.eod_profit||0}✅ ${s.eod_loss||0}🔴`:'';

    const exp=s.expectancy||0;
    document.getElementById('st-exp').innerHTML=exp>=0
        ? `<span class="exp-pos">+₹${exp}</span>`
        : `<span class="exp-neg">₹${exp}</span>`;
    document.getElementById('st-aw').innerHTML=`<span class="pp">+₹${Math.abs(s.avg_win||0).toFixed(2)}</span>`;
    document.getElementById('st-al').innerHTML=`<span class="pn">₹${Math.abs(s.avg_loss||0).toFixed(2)}</span>`;
}
function resetStats(){
    ['st-tot','st-ok','st-blk','st-t','st-s','st-eod'].forEach(id=>document.getElementById(id).textContent='0');
    ['st-fr','st-wr'].forEach(id=>document.getElementById(id).textContent='0%');
    ['st-pl','st-exp','st-aw','st-al'].forEach(id=>document.getElementById(id).textContent='₹0');
    document.getElementById('st-eod-note').textContent='';
}

function loadChart(){
    const date=document.getElementById('trade_date').value,sym=document.getElementById('cs').value;
    fetch(`{{ route('scalping.heatmap') }}?date=${date}&symbol=${sym}`)
        .then(r=>r.json())
        .then(res=>{if(res.success&&res.rows.length){crows=res.rows;drawChart(res.rows,document.getElementById('cm').value,sym);}});
}
function drawChart(rows,mode,sym){
    if(mc)mc.destroy();
    const ctx=document.getElementById('mc').getContext('2d'),labels=rows.map(r=>r.time);
    const ag=['09:15','09:30','09:45','10:00','10:15'];
    let ds;
    if(mode==='move'){
        ds=[
            {label:'H%↑',data:rows.map(r=>r.high_pct),type:'bar',yAxisID:'yM',backgroundColor:rows.map(r=>ag.includes(r.time)?'rgba(74,222,128,.68)':'rgba(34,197,94,.38)'),borderWidth:0,order:2},
            {label:'L%↓',data:rows.map(r=>r.low_pct),type:'bar',yAxisID:'yM',backgroundColor:rows.map(r=>ag.includes(r.time)?'rgba(239,68,68,.68)':'rgba(239,68,68,.36)'),borderWidth:0,order:3},
            {label:'Range%',data:rows.map(r=>r.range_pct),type:'line',yAxisID:'yM',borderColor:'#fbbf24',backgroundColor:'transparent',borderWidth:1.5,pointRadius:2,tension:0.3,order:1},
        ];
    } else if(mode==='vol'){
        ds=[
            {label:'Vol Ratio',data:rows.map(r=>r.vol_ratio||1),type:'bar',yAxisID:'yM',backgroundColor:rows.map(r=>(r.vol_ratio||1)>=1.4?'rgba(134,239,172,.64)':'rgba(100,116,139,.34)'),borderWidth:0},
            {label:`${sym} FUT`,data:rows.map(r=>r.close),type:'line',yAxisID:'yP',borderColor:'#00d2ff',backgroundColor:'transparent',borderWidth:1.5,pointRadius:1.5,tension:0.3},
        ];
    } else {
        ds=[
            {label:`${sym} FUT`,data:rows.map(r=>r.close),type:'line',yAxisID:'yP',borderColor:'#00d2ff',backgroundColor:'transparent',borderWidth:1.5,pointRadius:1.5,tension:0.3,order:0},
            {label:'CE OI%',data:rows.map(r=>r.ce_oi_pct),type:'bar',yAxisID:'yOI',backgroundColor:rows.map(r=>r.ce_oi_pct>=0?'rgba(239,68,68,.58)':'rgba(239,68,68,.2)'),borderWidth:0,order:1},
            {label:'PE OI%',data:rows.map(r=>r.pe_oi_pct),type:'bar',yAxisID:'yOI',backgroundColor:rows.map(r=>r.pe_oi_pct>=0?'rgba(34,197,94,.58)':'rgba(34,197,94,.2)'),borderWidth:0,order:2},
        ];
    }
    mc=new Chart(ctx,{
        data:{labels,datasets:ds},
        options:{responsive:true,interaction:{mode:'index',intersect:false},
            plugins:{legend:{labels:{color:'rgba(255,255,255,0.4)',font:{size:9.5}}},
                tooltip:{backgroundColor:'rgba(1,2,6,.97)',titleColor:'#00d2ff',bodyColor:'rgba(255,255,255,0.5)',
                    callbacks:{label:c=>{const v=c.raw;return c.dataset.label.includes('FUT')?` ${c.dataset.label}: ₹${Number(v).toLocaleString()}`:`  ${c.dataset.label}: ${v>=0?'+':''}${Number(v).toFixed(3)}${mode==='vol'?'×':'%'}`;}}}},
            scales:{
                x:{ticks:{color:'rgba(255,255,255,0.24)',font:{size:8}},grid:{color:'rgba(255,255,255,0.016)'}},
                yP:{position:'left',display:mode==='oi'||mode==='vol',ticks:{color:'#00d2ff',font:{size:8},callback:v=>'₹'+Number(v).toLocaleString()},grid:{color:'rgba(0,210,255,0.025)'}},
                yOI:{position:'right',display:mode==='oi',ticks:{color:'rgba(255,255,255,0.24)',font:{size:8},callback:v=>(v>=0?'+':'')+v.toFixed(1)+'%'},grid:{display:false}},
                yM:{position:'left',display:mode==='move'||mode==='vol',ticks:{color:'rgba(255,255,255,0.28)',font:{size:8},callback:v=>(v>=0?'+':'')+v.toFixed(2)+(mode==='vol'?'×':'%')},grid:{color:'rgba(255,255,255,0.016)'}},
            }}
    });
}
document.getElementById('cm').addEventListener('change',()=>{if(crows.length)drawChart(crows,document.getElementById('cm').value,document.getElementById('cs').value);});

function pct(v){v=parseFloat(v)||0;if(Math.abs(v)<.0005)return`<span class="pm">0%</span>`;return`<strong class="${v>=0?'pp':'pn'}">${v>=0?'+':''}${v.toFixed(3)}%</strong>`;}
function pl(v){if(v===null||v===undefined)return'<span class="pm">—</span>';v=parseFloat(v)||0;return`<strong class="${v>=0?'pp':'pn'}">${v>=0?'+':''}₹${Math.abs(v).toFixed(2)}</strong>`;}
function roi(v){if(v===null||v===undefined)return'<span class="pm">—</span>';v=parseFloat(v)||0;return`<strong class="${v>=0?'pp':'pn'}">${v>=0?'+':''}${v.toFixed(2)}%</strong>`;}
function noD(id,cols,msg){document.getElementById(id).innerHTML=`<tr><td colspan="${cols}" style="text-align:center;padding:25px;color:rgba(255,255,255,0.14);">${msg}</td></tr>`;}

document.getElementById('btn_run').addEventListener('click',run);
document.getElementById('btn_chart').addEventListener('click',loadChart);
document.getElementById('btn_reset').addEventListener('click',()=>{
    ['price_move_pct','oi_change_pct','target_pct','sl_pct','vol_mult','history_days'].forEach((id,i)=>{
        document.getElementById(id).value=['0.15','2.5','50','25','1.4','30'][i];
    });
    document.getElementById('use_edge').value='1';
    sigs=[];moves=[];pats=[];tpat=[];crows=[];
    noD('sig-tb',29,'Click Run');noD('pat-tb',21,'—');noD('mv-tb',14,'—');noD('tp-tb',12,'—');
    resetStats();if(mc){mc.destroy();mc=null;}
});
document.addEventListener('DOMContentLoaded',()=>setTimeout(run,300));
</script>
@endpush