@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<style>
/* ── Base ─────────────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

.ohlc-page { background: #0a0f1e; min-height: 100vh; padding: 20px 16px 60px; color: #e2e8f0; }

/* ── Page Header ──────────────────────────────────────────────────────────── */
.ohlc-header {
    background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
    border: 1px solid rgba(0,210,255,.3);
    border-radius: 16px;
    padding: 22px 28px;
    margin-bottom: 22px;
    box-shadow: 0 8px 32px rgba(0,210,255,.15);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.ohlc-header h4 { margin: 0; font-size: 20px; font-weight: 800; color: #fff;
    background: linear-gradient(90deg,#00d2ff,#3a7bd5); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.ohlc-header p  { margin: 4px 0 0; font-size: 12px; color: rgba(255,255,255,.55); }
.header-badge { background: linear-gradient(135deg,#11998e,#38ef7d); color: #fff;
    font-size: 9px; font-weight: 800; padding: 3px 9px; border-radius: 20px; text-transform: uppercase; letter-spacing: .5px; }
.header-links .btn { font-size: 11px; border-radius: 8px; padding: 6px 14px; font-weight: 600; }

/* ── Logic Cards ─────────────────────────────────────────────────────────── */
.logic-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 22px; }
.logic-card {
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px; padding: 14px 16px;
    border-left: 4px solid #00d2ff;
}
.logic-card.buy  { border-left-color: #28a745; }
.logic-card.sell { border-left-color: #dc3545; }
.logic-card.oi   { border-left-color: #667eea; }
.logic-card.piv  { border-left-color: #ffc107; }
.logic-card h6   { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; color: #00d2ff; }
.logic-card.buy  h6 { color: #28a745; }
.logic-card.sell h6 { color: #dc3545; }
.logic-card.oi   h6 { color: #667eea; }
.logic-card.piv  h6 { color: #ffc107; }
.logic-card ul   { margin: 0; padding-left: 14px; }
.logic-card li   { font-size: 10px; color: rgba(255,255,255,.7); margin-bottom: 3px; line-height: 1.4; }

/* ── Series Pills ────────────────────────────────────────────────────────── */
.series-wrap {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    background: rgba(0,210,255,.07); border: 1px solid rgba(0,210,255,.25);
    border-radius: 12px; padding: 12px 16px; margin-bottom: 20px;
}
.series-label { color: #00d2ff; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; white-space: nowrap; }
.series-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 14px; border-radius: 20px; font-size: 11px; font-weight: 700;
    cursor: pointer; border: 2px solid transparent; transition: all .2s;
    background: rgba(255,255,255,.06); color: rgba(255,255,255,.55);
}
.series-pill:hover  { background: rgba(0,210,255,.15); color: #00d2ff; border-color: rgba(0,210,255,.4); }
.series-pill.active { background: linear-gradient(135deg,#00d2ff,#3a7bd5); color: #fff; border-color: #00d2ff; box-shadow: 0 3px 10px rgba(0,210,255,.35); }
.series-pill.is-current::after { content:'LIVE'; font-size:8px; background:rgba(255,255,255,.25); padding:1px 5px; border-radius:8px; margin-left:4px; }

/* ── Filter Panel ────────────────────────────────────────────────────────── */
.filter-panel {
    background: linear-gradient(135deg,#1a1a2e,#16213e);
    border: 1px solid rgba(102,126,234,.35); border-radius: 14px;
    padding: 20px 24px; margin-bottom: 22px;
    box-shadow: 0 4px 20px rgba(102,126,234,.2);
}
.filter-panel label { color: #a0aec0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px; display: block; }
.filter-panel .form-control {
    background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12);
    color: #e2e8f0; border-radius: 8px; font-size: 12px; padding: 7px 10px;
    transition: border-color .2s;
}
.filter-panel .form-control:focus { border-color: #00d2ff; outline: none; box-shadow: 0 0 0 3px rgba(0,210,255,.15); background: rgba(255,255,255,.1); }
.filter-panel .form-control option { background: #1a1a2e; color: #e2e8f0; }
.btn-run  { background: linear-gradient(135deg,#00d2ff,#3a7bd5); color:#fff; border:none; border-radius:10px; padding:10px 28px; font-weight:800; font-size:13px; cursor:pointer; transition: transform .15s, box-shadow .15s; }
.btn-run:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,210,255,.4); }
.btn-reset { background: rgba(255,255,255,.07); color: #a0aec0; border: 1px solid rgba(255,255,255,.12); border-radius:10px; padding:10px 20px; font-weight:700; font-size:13px; cursor:pointer; transition:.2s; }
.btn-reset:hover { background: rgba(255,255,255,.12); color:#fff; }

/* Interval Tabs */
.interval-tabs { display:flex; gap:6px; flex-wrap:wrap; }
.itab {
    padding:5px 14px; border-radius:20px; font-size:11px; font-weight:700;
    cursor:pointer; border:2px solid rgba(255,255,255,.15);
    background: rgba(255,255,255,.05); color: rgba(255,255,255,.5);
    transition:.2s;
}
.itab.active { background: linear-gradient(135deg,#667eea,#764ba2); color:#fff; border-color:#667eea; box-shadow:0 3px 10px rgba(102,126,234,.4); }
.itab:hover:not(.active) { border-color:rgba(255,255,255,.3); color:#fff; }

/* Symbol Tabs */
.sym-tabs { display:flex; gap:8px; }
.stab {
    padding:6px 18px; border-radius:20px; font-size:12px; font-weight:800;
    cursor:pointer; border:2px solid transparent; transition:.2s;
    background: rgba(255,255,255,.05); color: rgba(255,255,255,.5);
}
.stab.active.nifty    { background:linear-gradient(135deg,#11998e,#38ef7d); color:#fff; border-color:#38ef7d; }
.stab.active.banknifty{ background:linear-gradient(135deg,#f7971e,#ffd200); color:#1a1a2e; border-color:#ffd200; }
.stab.active.all      { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border-color:#667eea; }
.stab:hover:not(.active) { border-color:rgba(255,255,255,.3); color:#fff; }

/* ── Stats Grid ──────────────────────────────────────────────────────────── */
.stats-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:20px; }
.stat-card {
    background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
    border-radius:12px; padding:12px 14px; text-align:center;
    border-left:4px solid #00d2ff; transition:transform .2s;
}
.stat-card:hover { transform:translateY(-2px); }
.stat-card small { display:block; color:rgba(255,255,255,.45); font-size:9px; text-transform:uppercase; letter-spacing:.3px; }
.stat-card strong { display:block; font-size:1.25rem; font-weight:800; margin-top:4px; color:#fff; }
.stat-card.green  { border-left-color:#28a745; }
.stat-card.red    { border-left-color:#dc3545; }
.stat-card.yellow { border-left-color:#ffc107; }
.stat-card.purple { border-left-color:#764ba2; }
.stat-card.cyan   { border-left-color:#00d2ff; }

/* ── Confirmed Signal Panel ──────────────────────────────────────────────── */
.confirmed-panel {
    background: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
    border:2px solid #00d2ff; border-radius:16px; padding:18px 22px;
    margin-bottom:22px; box-shadow:0 4px 24px rgba(0,210,255,.2);
}
.confirmed-panel-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.confirmed-panel-header h6 { color:#00d2ff; font-size:13px; font-weight:800; margin:0; text-transform:uppercase; letter-spacing:.5px; }
.conf-rule { background:rgba(0,210,255,.06); border:1px solid rgba(0,210,255,.2); border-radius:8px; padding:7px 14px; margin-bottom:12px; font-size:10px; color:rgba(255,255,255,.7); display:flex; gap:20px; flex-wrap:wrap; }
.conf-rule strong { color:#00d2ff; }
.conf-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px; }
.conf-stat { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); border-radius:10px; padding:10px; text-align:center; }
.conf-stat small  { display:block; color:rgba(255,255,255,.45); font-size:9px; text-transform:uppercase; }
.conf-stat strong { display:block; font-size:1.1rem; font-weight:800; margin-top:3px; color:#fff; }

/* ── Table ───────────────────────────────────────────────────────────────── */
.table-wrap { position:relative; min-height:300px; }
.loading-overlay {
    position:absolute; inset:0; background:rgba(10,15,30,.95);
    display:flex; flex-direction:column; justify-content:center; align-items:center;
    z-index:100; border-radius:12px;
}
.spinner { width:48px; height:48px; border:5px solid rgba(0,210,255,.2); border-top-color:#00d2ff; border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.loading-text { color:#fff; margin-top:16px; font-size:14px; font-weight:700; }

.table-scroller { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.ohlc-table { min-width:2600px; width:100%; border-collapse:separate; border-spacing:0 3px; }
.ohlc-table thead th {
    background:rgba(0,210,255,.08); color:#00d2ff;
    font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.4px;
    padding:10px 8px; text-align:center; white-space:nowrap;
    border-bottom:2px solid rgba(0,210,255,.25);
    position:sticky; top:0; z-index:20;
}
.ohlc-table thead th:nth-child(1),
.ohlc-table thead th:nth-child(2),
.ohlc-table thead th:nth-child(3) { position:sticky; z-index:30; background:#0a0f1e; }
.ohlc-table thead th:nth-child(1) { left:0; }
.ohlc-table thead th:nth-child(2) { left:36px; }
.ohlc-table thead th:nth-child(3) { left:120px; }

.ohlc-table tbody tr { transition:background .15s; }
.ohlc-table tbody tr:hover { background:rgba(0,210,255,.04); }
.ohlc-table tbody td {
    padding:8px 7px; font-size:11px; text-align:center;
    color:rgba(255,255,255,.8); border-bottom:1px solid rgba(255,255,255,.04);
    vertical-align:middle;
}
.ohlc-table tbody td:nth-child(1),
.ohlc-table tbody td:nth-child(2),
.ohlc-table tbody td:nth-child(3) { position:sticky; z-index:10; background:#0a0f1e; }
.ohlc-table tbody td:nth-child(1) { left:0; }
.ohlc-table tbody td:nth-child(2) { left:36px; }
.ohlc-table tbody td:nth-child(3) { left:120px; }

.ohlc-table tbody tr.confirmed-row td:nth-child(1),
.ohlc-table tbody tr.confirmed-row td:nth-child(2),
.ohlc-table tbody tr.confirmed-row td:nth-child(3) { background:#0c1525; }

/* ── Row highlight ─────────────────────────────────────────────────────────*/
.ohlc-table tbody tr.confirmed-row { background:rgba(0,210,255,.05); outline:1px solid rgba(0,210,255,.2); }
.ohlc-table tbody tr.buy-row  { background:rgba(40,167,69,.04); }
.ohlc-table tbody tr.sell-row { background:rgba(220,53,69,.04); }

/* ── Badges ──────────────────────────────────────────────────────────────── */
.badge-b(t, fg, bg) { }
.pill { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:9px; font-weight:800; white-space:nowrap; }
.pill-buy     { background:linear-gradient(135deg,#28a745,#20c997); color:#fff; }
.pill-sell    { background:linear-gradient(135deg,#dc3545,#c82333); color:#fff; }
.pill-wait    { background:rgba(108,117,125,.3); color:#adb5bd; border:1px solid rgba(108,117,125,.4); }
.pill-strong  { background:linear-gradient(135deg,#ffc107,#ff9800); color:#212529; }
.pill-mod     { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; }
.pill-weak    { background:rgba(255,255,255,.08); color:rgba(255,255,255,.5); border:1px solid rgba(255,255,255,.15); }
.pill-bull    { background:linear-gradient(135deg,#28a745,#20c997); color:#fff; }
.pill-bear    { background:linear-gradient(135deg,#dc3545,#c82333); color:#fff; }
.pill-neut    { background:rgba(108,117,125,.3); color:#adb5bd; }
.pill-conf    { background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:#fff; }
.pill-part    { background:linear-gradient(135deg,#ffc107,#e0a800); color:#212529; }
.pill-unconf  { background:rgba(220,53,69,.2); color:#ff6b7a; border:1px solid rgba(220,53,69,.3); }
.pill-hammer  { background:linear-gradient(135deg,#11998e,#38ef7d); color:#111; }
.pill-star    { background:linear-gradient(135deg,#f7971e,#ffd200); color:#111; }
.pill-strong-c{ background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; }
.pill-expan   { background:linear-gradient(135deg,#00d2ff,#0099cc); color:#fff; }
.pill-normal  { background:rgba(255,255,255,.06); color:rgba(255,255,255,.4); }
.pill-nifty   { background:linear-gradient(135deg,#11998e,#38ef7d); color:#111; font-weight:900; }
.pill-bank    { background:linear-gradient(135deg,#f7971e,#ffd200); color:#111; font-weight:900; }

.pivot-r { color:#28a745; font-weight:800; }
.pivot-p { color:#17a2b8; font-weight:800; }
.pivot-s { color:#dc3545; font-weight:800; }

.up   { color:#28a745; font-weight:700; }
.down { color:#dc3545; font-weight:700; }

/* ── OHLC candle mini ──────────────────────────────────────────────────────*/
.candle-mini { display:inline-flex; align-items:center; gap:8px; }
.candle-body { width:10px; border-radius:2px; display:inline-block; }
.candle-info { text-align:left; font-size:10px; line-height:1.5; }

/* Factor dots */
.factor-dots { display:flex; gap:3px; flex-wrap:wrap; justify-content:center; margin-top:3px; }
.fdot { width:8px; height:8px; border-radius:50%; display:inline-block; }
.fdot.ok  { background:#28a745; }
.fdot.bad { background:#dc3545; }

/* ── No Data ─────────────────────────────────────────────────────────────── */
.no-data { text-align:center; padding:60px 20px; }
.no-data i { font-size:3rem; opacity:.3; color:#00d2ff; }
.no-data p { margin-top:16px; font-size:13px; color:rgba(255,255,255,.5); }

/* Tooltip */
[title] { cursor:help; }
</style>
@endpush

<div class="ohlc-page">

    {{-- ── HEADER ────────────────────────────────────────────────────────── --}}
    <div class="ohlc-header">
        <div>
            <h4>📊 OHLC Candle Signal Analysis <span class="header-badge">NIFTY & BANKNIFTY</span></h4>
            <p>Candlestick structure + Breakout/Breakdown + OI Alignment + Pivot Zone — Multi-Factor Confirmation</p>
        </div>
        <div class="header-links d-flex gap-2 flex-wrap">
            <a href="{{ route('9to12.pece-analysis') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-bar"></i> OI Analysis</a>
            <a href="{{ route('9to12.config') }}"       class="btn btn-outline-light btn-sm"><i class="fas fa-cog"></i> Config</a>
            <a href="{{ route('oiiv-auto.index') }}"    class="btn btn-outline-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
        </div>
    </div>

    {{-- ── LOGIC CARDS ───────────────────────────────────────────────────── --}}
    <div class="logic-grid">
        <div class="logic-card buy">
            <h6>🟢 BUY Signal</h6>
            <ul>
                <li>Close &gt; Prev Candle High (Breakout)</li>
                <li>Close &gt; Open (Bullish candle)</li>
                <li>Body &gt; 50% of total range</li>
                <li>OI: CE↓ + PE↑ (Put buildup)</li>
                <li>Price above Pivot Point</li>
            </ul>
        </div>
        <div class="logic-card sell">
            <h6>🔴 SELL Signal</h6>
            <ul>
                <li>Close &lt; Prev Candle Low (Breakdown)</li>
                <li>Close &lt; Open (Bearish candle)</li>
                <li>Body &gt; 50% of total range</li>
                <li>OI: CE↑ + PE↓ (Call buildup)</li>
                <li>Price below Pivot Point</li>
            </ul>
        </div>
        <div class="logic-card oi">
            <h6>📊 OI Confirmation</h6>
            <ul>
                <li>CE ↓ + PE ↑ → BULLISH</li>
                <li>CE ↑ + PE ↓ → BEARISH</li>
                <li>Both ↑: CE&gt;PE = Bearish</li>
                <li>Both ↓: PE&gt;CE = Bullish</li>
                <li>Base: 09:30 → 12:15 OI delta</li>
            </ul>
        </div>
        <div class="logic-card">
            <h6>🕯️ Candle Patterns</h6>
            <ul>
                <li><strong>Hammer</strong> — Lower wick &gt; 2×body</li>
                <li><strong>Shooting Star</strong> — Upper wick &gt; 2×body</li>
                <li><strong>Strong</strong> — Body &gt; 60% range</li>
                <li><strong>Expansion</strong> — Range &gt;1.5× avg (5)</li>
                <li><strong>Doji</strong> — Body &lt; 10% range</li>
            </ul>
        </div>
        <div class="logic-card piv">
            <h6>📍 Pivot Zones</h6>
            <ul>
                <li>P = (H+L+C)/3 prev-day FUT</li>
                <li>R1/R2/R3 — resistance levels</li>
                <li>S1/S2/S3 — support levels</li>
                <li>Near = within 0.3%</li>
                <li>BUY above P, SELL below P</li>
            </ul>
        </div>
        <div class="logic-card" style="border-left-color:#00d2ff;">
            <h6>🎯 Confirmation Levels</h6>
            <ul>
                <li><strong style="color:#00d2ff;">CONFIRMED</strong> — 4+ factors aligned</li>
                <li><strong style="color:#ffc107;">PARTIAL</strong> — 2–3 factors</li>
                <li><strong style="color:#dc3545;">UNCONFIRMED</strong> — &lt;2 factors</li>
                <li>Only trade CONFIRMED signals</li>
                <li>PARTIAL = watch + wait for entry</li>
            </ul>
        </div>
    </div>

    {{-- ── SERIES SELECTOR ────────────────────────────────────────────────── --}}
    <div class="series-wrap">
        <span class="series-label">📅 Series:</span>
        <div id="series-pills" style="display:flex; gap:8px; flex-wrap:wrap; flex:1;">
            <span style="color:rgba(255,255,255,.4); font-size:11px; font-style:italic;">Loading series...</span>
        </div>
        <span style="font-size:10px; color:rgba(255,255,255,.3);">Switch series to see historical data</span>
    </div>

    {{-- ── FILTER PANEL ────────────────────────────────────────────────────── --}}
    <div class="filter-panel">
        <div class="row align-items-end" style="gap:0 0;">
            <div class="col-md-2 mb-3">
                <label>📅 From Date</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-2 mb-3">
                <label>📅 To Date</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-2 mb-3">
                <label>🎯 Signal Filter</label>
                <select id="signal_filter" class="form-control">
                    <option value="">All Signals</option>
                    <option value="BUY">BUY Only</option>
                    <option value="SELL">SELL Only</option>
                    <option value="WAIT">WAIT Only</option>
                </select>
            </div>
        </div>
        <div class="text-center mt-2">
            <button class="btn-run"   onclick="runAnalysis()"><i class="fas fa-search"></i> &nbsp;Analyze Signals</button>
            <button class="btn-reset" onclick="resetAll()" style="margin-left:10px;"><i class="fas fa-undo"></i> &nbsp;Reset</button>
        </div>
    </div>

    {{-- ── STATS GRID ──────────────────────────────────────────────────────── --}}
    <div class="stats-grid">
        <div class="stat-card cyan">  <small>Total Signals</small><strong id="st-total">0</strong></div>
        <div class="stat-card green"> <small>🟢 BUY</small>      <strong id="st-buy" style="color:#28a745;">0</strong></div>
        <div class="stat-card red">   <small>🔴 SELL</small>     <strong id="st-sell" style="color:#dc3545;">0</strong></div>
        <div class="stat-card yellow"><small>⏸ WAIT</small>      <strong id="st-wait" style="color:#ffc107;">0</strong></div>
        <div class="stat-card purple"><small>CONFIRMED</small>    <strong id="st-conf" style="color:#00d2ff;">0</strong></div>
        <div class="stat-card">      <small>PARTIAL</small>      <strong id="st-part" style="color:#ffc107;">0</strong></div>
        <div class="stat-card green"> <small>🟢 NIFTY</small>    <strong id="st-nifty" style="color:#38ef7d;">0</strong></div>
        <div class="stat-card yellow"><small>💛 BANKNIFTY</small><strong id="st-bank" style="color:#ffd200;">0</strong></div>
    </div>

    {{-- ── CONFIRMED PANEL ─────────────────────────────────────────────────── --}}
    <div class="confirmed-panel">
        <div class="confirmed-panel-header">
            <span style="font-size:20px;">🎯</span>
            <h6>High-Confidence Confirmed Signals Only</h6>
            <span style="background:linear-gradient(135deg,#00d2ff,#3a7bd5);color:#fff;font-size:9px;font-weight:800;padding:2px 10px;border-radius:20px;text-transform:uppercase;">CONFIRMED = 4+ Factors</span>
        </div>
        <div class="conf-rule">
            <span>✅ <strong>BUY CONFIRMED</strong>: Bullish candle + Breakout + Body&gt;50% + OI Bullish + Above Pivot</span>
            <span>✅ <strong>SELL CONFIRMED</strong>: Bearish candle + Breakdown + Body&gt;50% + OI Bearish + Below Pivot</span>
            <span style="color:rgba(255,255,255,.35);">❌ WAIT/UNCONFIRMED excluded</span>
        </div>
        <div class="conf-stats">
            <div class="conf-stat"><small>🎯 Confirmed Total</small><strong id="cp-total">0</strong></div>
            <div class="conf-stat"><small>🟢 Confirmed BUY</small><strong id="cp-buy" style="color:#28a745;">0</strong></div>
            <div class="conf-stat"><small>🔴 Confirmed SELL</small><strong id="cp-sell" style="color:#dc3545;">0</strong></div>
            <div class="conf-stat"><small>💛 Conf BANKNIFTY</small><strong id="cp-bank" style="color:#ffd200;">0</strong></div>
            <div class="conf-stat"><small>🟢 Conf NIFTY</small><strong id="cp-nifty" style="color:#38ef7d;">0</strong></div>
            <div class="conf-stat"><small>💪 STRONG Signals</small><strong id="cp-strong" style="color:#ffc107;">0</strong></div>
        </div>
    </div>

    {{-- ── TABLE ───────────────────────────────────────────────────────────── --}}
    <div class="table-wrap">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text" id="loading-text">Analyzing candles...</div>
        </div>

        <div class="table-scroller">
            <table class="ohlc-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Symbol</th>
                        <th>Date / Time</th>
                        <th>Interval</th>
                        {{-- OHLC --}}
                        <th>Open</th>
                        <th>High</th>
                        <th>Low</th>
                        <th>Close</th>
                        {{-- Prev candle --}}
                        <th>Prev H</th>
                        <th>Prev L</th>
                        {{-- Structure --}}
                        <th>Candle Type</th>
                        <th>Pattern</th>
                        <th>Body%</th>
                        <th>Upper W%</th>
                        <th>Lower W%</th>
                        <th>Breakout</th>
                        {{-- OI --}}
                        <th>CE OI%</th>
                        <th>PE OI%</th>
                        <th>OI Sentiment</th>
                        <th>OI Condition</th>
                        {{-- Pivot --}}
                        <th>Pivot Zone</th>
                        <th>R3/R2/R1</th>
                        <th>Pivot (P)</th>
                        <th>S1/S2/S3</th>
                        {{-- Signal --}}
                        <th>Action</th>
                        <th>Strength</th>
                        <th>Confirmation</th>
                        <th>Factors</th>
                        <th>Reasons</th>
                    </tr>
                </thead>
                <tbody id="signal-tbody">
                    <tr>
                        <td colspan="29">
                            <div class="no-data">
                                <i class="fas fa-chart-candlestick"></i>
                                <p>Select a series above then click <strong style="color:#00d2ff;">Analyze Signals</strong></p>
                            </div>
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
// ═══════════════════════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════════════════════
let signalData   = [];
let activeSeries = null;
let currentSeries= null;
let allSeries    = [];
let activeSymbol = 'ALL';
let activeInterv = '15';

// ═══════════════════════════════════════════════════════════════════════════
// SERIES
// ═══════════════════════════════════════════════════════════════════════════
function loadSeries() {
    $('#series-pills').html('<span style="color:rgba(255,255,255,.4);font-size:11px;font-style:italic;">Loading...</span>');
    $.get('{{ route("ohlc.series") }}', function(res) {
        if (!res.success || !res.series?.length) {
            $('#series-pills').html('<span style="color:#dc3545;font-size:11px;">No series found</span>');
            return;
        }
        allSeries = res.series;
        currentSeries = res.current_series;
        renderSeriesPills();
        selectSeries(currentSeries, false);
    }).fail(() => {
        $('#series-pills').html('<span style="color:#dc3545;font-size:11px;">Error loading series</span>');
    });
}

function renderSeriesPills() {
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    let html = '';
    allSeries.forEach(s => {
        const d = new Date(s.value);
        const label = months[d.getMonth()] + ' ' + d.getFullYear();
        const isActive  = s.value === activeSeries;
        const isCurrent = s.value === currentSeries;
        html += `<span class="series-pill ${isActive?'active':''} ${isCurrent?'is-current':''}"
                       data-expiry="${s.value}" onclick="selectSeries('${s.value}',true)">
                    📅 ${label}
                 </span>`;
    });
    $('#series-pills').html(html);
}

function selectSeries(exp, autoRun) {
    if (!exp) return;
    activeSeries = exp;
    renderSeriesPills();
    if (autoRun) runAnalysis();
}

// ═══════════════════════════════════════════════════════════════════════════
// SYMBOL / INTERVAL SELECTORS
// ═══════════════════════════════════════════════════════════════════════════
function selectSym(el) {
    $('.stab').removeClass('active');
    $(el).addClass('active');
    activeSymbol = $(el).data('sym');
}

function selectInterval(el) {
    $('.itab').removeClass('active');
    $(el).addClass('active');
    activeInterv = $(el).data('min');
}

// ═══════════════════════════════════════════════════════════════════════════
// ANALYSIS
// ═══════════════════════════════════════════════════════════════════════════
function runAnalysis() {
    if (!activeSeries) { alert('Please select a series first'); return; }
    const from = $('#from_date').val();
    const to   = $('#to_date').val();
    if (!from || !to) { alert('Please select dates'); return; }

    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const d = new Date(activeSeries);
    const seriesLabel = months[d.getMonth()] + ' ' + d.getFullYear();

    showLoading(`Analyzing ${activeInterv}-min candles for ${seriesLabel}...`);
    signalData = [];

    $.ajax({
        url : '{{ route("ohlc.analyze") }}',
        type: 'GET',
        data: {
            from_date    : from,
            to_date      : to,
            series_expiry: activeSeries,
            interval     : activeInterv,
            symbol       : activeSymbol,
            filter_signal: $('#signal_filter').val(),
        },
        success(res) {
            hideLoading();
            if (res.success && res.data?.length) {
                signalData = res.data;
                renderTable();
                updateStats();
            } else {
                showNoData(res.message || 'No signals found for this filter combination');
                resetStats();
            }
        },
        error() {
            hideLoading();
            showNoData('Error loading data. Check console.');
            resetStats();
        }
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// TABLE RENDER
// ═══════════════════════════════════════════════════════════════════════════
function renderTable() {
    if (!signalData.length) return;
    let html = '';

    signalData.forEach((row, i) => {
        const isConf    = row.confirmation === 'CONFIRMED';
        const isBuy     = row.action === 'BUY';
        const isSell    = row.action === 'SELL';
        const isNifty   = row.symbol === 'NIFTY';

        const rowCls = [
            isConf  ? 'confirmed-row' : '',
            isBuy   ? 'buy-row'       : '',
            isSell  ? 'sell-row'      : '',
        ].join(' ');

        // Symbol pill
        const symPill = isNifty
            ? `<span class="pill pill-nifty">⚡ NIFTY</span>`
            : `<span class="pill pill-bank">🏦 BNKN</span>`;

        // Action pill
        const actPill = isBuy  ? `<span class="pill pill-buy">📈 BUY</span>`
                      : isSell ? `<span class="pill pill-sell">📉 SELL</span>`
                               : `<span class="pill pill-wait">⏸ WAIT</span>`;

        // Strength pill
        const stPill = row.strength === 'STRONG'   ? `<span class="pill pill-strong">💪 STRONG</span>`
                     : row.strength === 'MODERATE'  ? `<span class="pill pill-mod">⚡ MOD</span>`
                                                    : `<span class="pill pill-weak">WEAK</span>`;

        // Candle type
        const ctPill = row.candle_type === 'Bullish' ? `<span class="pill pill-bull">🟢 Bullish</span>`
                     : row.candle_type === 'Bearish' ? `<span class="pill pill-bear">🔴 Bearish</span>`
                                                     : `<span class="pill pill-wait">Doji</span>`;

        // Pattern pill
        const patMap = {
            'Hammer'      : `<span class="pill pill-hammer">🔨 Hammer</span>`,
            'ShootingStar': `<span class="pill pill-star">⭐ Shooting★</span>`,
            'Strong'      : `<span class="pill pill-strong-c">💪 Strong</span>`,
            'Expansion'   : `<span class="pill pill-expan">🚀 Expansion</span>`,
            'Normal'      : `<span class="pill pill-normal">Normal</span>`,
        };
        const patPill = patMap[row.pattern] || `<span class="pill pill-normal">${row.pattern}</span>`;

        // Breakout
        const boPill = row.breakout === 'Above Prev High'
            ? `<span class="pill pill-buy">▲ Above PH</span>`
            : row.breakout === 'Below Prev Low'
                ? `<span class="pill pill-sell">▼ Below PL</span>`
                : `<span class="pill pill-wait">—</span>`;

        // OI Sentiment
        const oiPill = row.oi_sentiment === 'BULLISH' ? `<span class="pill pill-bull">🟢 BULLISH</span>`
                     : row.oi_sentiment === 'BEARISH' ? `<span class="pill pill-bear">🔴 BEARISH</span>`
                     : row.oi_sentiment === 'N/A'     ? `<span class="pill pill-wait">N/A</span>`
                                                      : `<span class="pill pill-wait">⚪ NEUTRAL</span>`;

        // OI Condition
        let oiCondCls = 'pill-wait';
        if (row.oi_condition?.includes('CE ↓ + PE ↑')) oiCondCls = 'pill-bull';
        if (row.oi_condition?.includes('CE ↑ + PE ↓')) oiCondCls = 'pill-bear';
        const oiCondPill = `<span class="pill ${oiCondCls}" style="font-size:8px;">${row.oi_condition || '—'}</span>`;

        // Confirmation pill
        const confPill = row.confirmation === 'CONFIRMED'   ? `<span class="pill pill-conf">🎯 CONFIRMED</span>`
                       : row.confirmation === 'PARTIAL'     ? `<span class="pill pill-part">⚡ PARTIAL</span>`
                       : row.confirmation === 'UNCONFIRMED' ? `<span class="pill pill-unconf">⚠ UNCONF</span>`
                                                            : `<span class="pill pill-wait">N/A</span>`;

        // Factor dots
        let factorHtml = '—';
        if (row.confirmed_factors?.length) {
            factorHtml = '<div class="factor-dots">';
            row.confirmed_factors.forEach(f => {
                factorHtml += `<span class="fdot ${f.ok?'ok':'bad'}" title="${f.name}"></span>`;
            });
            factorHtml += '</div>';
            const okCount = row.confirmed_factors.filter(f=>f.ok).length;
            factorHtml += `<small style="font-size:9px;color:rgba(255,255,255,.4);">${okCount}/${row.confirmed_factors.length}</small>`;
        }

        // Reasons
        const reasonHtml = (row.reasons && row.reasons.length)
            ? `<div style="text-align:left;font-size:9px;color:rgba(255,255,255,.6);line-height:1.6;">${row.reasons.map(r=>`• ${r}`).join('<br>')}</div>`
            : '<span style="color:rgba(255,255,255,.25);">—</span>';

        // OI pct formatting
        const fmtPct = v => v > 0 ? `<span class="up">+${v}%</span>` : `<span class="down">${v}%</span>`;

        // Pivot zone badge
        const pivZone = getPivotBadge(row.pivot_position);

        // Pivot levels
        const rLevels = (row.r1 || row.r2 || row.r3) ? `
            <div style="font-size:9px;line-height:1.6;">
                <span class="pivot-r">R3: ${row.r3?Number(row.r3).toFixed(0):'—'}</span><br>
                <span class="pivot-r">R2: ${row.r2?Number(row.r2).toFixed(0):'—'}</span><br>
                <span class="pivot-r">R1: ${row.r1?Number(row.r1).toFixed(0):'—'}</span>
            </div>` : '<span style="color:rgba(255,255,255,.3);">N/A</span>';

        const pivotVal = row.pivot ? `<strong class="pivot-p">P: ${Number(row.pivot).toFixed(0)}</strong>` : '—';

        const sLevels = (row.s1 || row.s2 || row.s3) ? `
            <div style="font-size:9px;line-height:1.6;">
                <span class="pivot-s">S1: ${row.s1?Number(row.s1).toFixed(0):'—'}</span><br>
                <span class="pivot-s">S2: ${row.s2?Number(row.s2).toFixed(0):'—'}</span><br>
                <span class="pivot-s">S3: ${row.s3?Number(row.s3).toFixed(0):'—'}</span>
            </div>` : '<span style="color:rgba(255,255,255,.3);">N/A</span>';

        // Confirmed row star
        const confStar = isConf ? ` <span title="Confirmed Signal" style="color:#00d2ff;font-size:11px;">🎯</span>` : '';

        html += `
        <tr class="${rowCls}">
            <td><strong style="color:rgba(255,255,255,.5);">${i+1}</strong>${confStar}</td>
            <td>${symPill}</td>
            <td>
                <strong style="color:#e2e8f0;font-size:11px;">${row.date}</strong><br>
                <span style="color:#00d2ff;font-size:10px;font-weight:700;">${(row.candle_time||'').substring(0,5)}</span>
            </td>
            <td><span style="color:rgba(255,255,255,.45);font-size:10px;">${row.interval}</span></td>
            <td><strong>${row.open}</strong></td>
            <td><strong class="up">${row.high}</strong></td>
            <td><strong class="down">${row.low}</strong></td>
            <td><strong style="color:#fff;">${row.close}</strong></td>
            <td><span style="color:rgba(255,255,255,.5);">${row.prev_high}</span></td>
            <td><span style="color:rgba(255,255,255,.5);">${row.prev_low}</span></td>
            <td>${ctPill}</td>
            <td>${patPill}</td>
            <td>
                <strong style="color:${row.body_pct>=50?'#28a745':'rgba(255,255,255,.5)'};">${row.body_pct}%</strong>
            </td>
            <td><span style="color:rgba(255,255,255,.5);font-size:10px;">${row.upper_wick_pct}%</span></td>
            <td><span style="color:rgba(255,255,255,.5);font-size:10px;">${row.lower_wick_pct}%</span></td>
            <td>${boPill}</td>
            <td>${row.ce_oi_open>0 ? fmtPct(row.ce_oi_pct) : '<span style="color:rgba(255,255,255,.3);">N/A</span>'}</td>
            <td>${row.pe_oi_open>0 ? fmtPct(row.pe_oi_pct) : '<span style="color:rgba(255,255,255,.3);">N/A</span>'}</td>
            <td>${oiPill}</td>
            <td>${oiCondPill}</td>
            <td>${pivZone}</td>
            <td>${rLevels}</td>
            <td>${pivotVal}</td>
            <td>${sLevels}</td>
            <td>${actPill}</td>
            <td>${stPill}</td>
            <td>${confPill}</td>
            <td>${factorHtml}</td>
            <td>${reasonHtml}</td>
        </tr>`;
    });

    $('#signal-tbody').html(html);
}

// ═══════════════════════════════════════════════════════════════════════════
// PIVOT BADGE
// ═══════════════════════════════════════════════════════════════════════════
function getPivotBadge(pos) {
    if (!pos || pos === 'N/A') return '<span class="pill pill-wait">N/A</span>';
    if (pos.startsWith('Near')) return `<span class="pill" style="background:linear-gradient(135deg,#ffc107,#e0a800);color:#212529;">⚡ ${pos}</span>`;
    const map = {
        'Above R3' : 'background:linear-gradient(135deg,#155724,#28a745);color:#fff;',
        'R2–R3'    : 'background:linear-gradient(135deg,#28a745,#5cb85c);color:#fff;',
        'R1–R2'    : 'background:linear-gradient(135deg,#5cb85c,#9fd89f);color:#fff;',
        'P–R1'     : 'background:linear-gradient(135deg,#17a2b8,#138496);color:#fff;',
        'S1–P'     : 'background:linear-gradient(135deg,#fd7e14,#e55d00);color:#fff;',
        'S2–S1'    : 'background:linear-gradient(135deg,#dc3545,#bd2130);color:#fff;',
        'S3–S2'    : 'background:linear-gradient(135deg,#9c1221,#dc3545);color:#fff;',
        'Below S3' : 'background:linear-gradient(135deg,#3d0b0b,#9c1221);color:#fff;',
    };
    const style = map[pos] || '';
    return `<span class="pill" style="${style}">${pos}</span>`;
}

// ═══════════════════════════════════════════════════════════════════════════
// STATS
// ═══════════════════════════════════════════════════════════════════════════
function updateStats() {
    const total = signalData.length;
    const buys  = signalData.filter(r => r.action === 'BUY').length;
    const sells = signalData.filter(r => r.action === 'SELL').length;
    const waits = signalData.filter(r => r.action === 'WAIT').length;
    const conf  = signalData.filter(r => r.confirmation === 'CONFIRMED').length;
    const part  = signalData.filter(r => r.confirmation === 'PARTIAL').length;
    const nifty = signalData.filter(r => r.symbol === 'NIFTY').length;
    const bank  = signalData.filter(r => r.symbol === 'BANKNIFTY').length;

    $('#st-total').text(total);
    $('#st-buy').text(buys);
    $('#st-sell').text(sells);
    $('#st-wait').text(waits);
    $('#st-conf').text(conf);
    $('#st-part').text(part);
    $('#st-nifty').text(nifty);
    $('#st-bank').text(bank);

    // Confirmed panel
    const confRows = signalData.filter(r => r.confirmation === 'CONFIRMED');
    $('#cp-total').text(confRows.length);
    $('#cp-buy').text(confRows.filter(r=>r.action==='BUY').length);
    $('#cp-sell').text(confRows.filter(r=>r.action==='SELL').length);
    $('#cp-bank').text(confRows.filter(r=>r.symbol==='BANKNIFTY').length);
    $('#cp-nifty').text(confRows.filter(r=>r.symbol==='NIFTY').length);
    $('#cp-strong').text(confRows.filter(r=>r.strength==='STRONG').length);
}

function resetStats() {
    ['#st-total','#st-buy','#st-sell','#st-wait','#st-conf','#st-part','#st-nifty','#st-bank',
     '#cp-total','#cp-buy','#cp-sell','#cp-bank','#cp-nifty','#cp-strong'].forEach(id => $(id).text('0'));
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════
function showLoading(msg = 'Analyzing...') {
    $('#loading-text').text(msg);
    $('#loading-overlay').show();
}
function hideLoading() { $('#loading-overlay').hide(); }

function showNoData(msg) {
    $('#signal-tbody').html(`
        <tr><td colspan="29">
            <div class="no-data">
                <i class="fas fa-info-circle"></i>
                <p>${msg}</p>
            </div>
        </td></tr>`);
}

function resetAll() {
    $('#from_date,#to_date').val('{{ date("Y-m-d") }}');
    $('#signal_filter').val('');
    $('.stab').removeClass('active');
    $('.stab.all').addClass('active'); activeSymbol = 'ALL';
    $('.itab').removeClass('active');
    $('[data-min="15"]').addClass('active'); activeInterv = '15';
    signalData = [];
    resetStats();
    if (currentSeries) selectSeries(currentSeries, false);
    showNoData('Filters reset. Click Analyze Signals to reload.');
    setTimeout(runAnalysis, 200);
}

// ═══════════════════════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════════════════════
$(document).ready(function() {
    loadSeries();
    // Enter key triggers analysis
    $(document).on('keydown', function(e) {
        if (e.key === 'Enter') runAnalysis();
    });
});
</script>
@endpush