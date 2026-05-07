@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ── Base table — EXACT match to pece-analysis custom--table ── */
    .custom--table thead th,
    .custom--table tbody td {
        text-align: center !important;
        padding: 8px 6px !important;
        font-size: 11px !important;
        vertical-align: middle;
    }
    .custom--table thead th:first-child,
    .custom--table tbody td:first-child,
    .custom--table thead th:nth-child(2),
    .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3),
    .custom--table tbody td:nth-child(3) { text-align: left !important; }

    /* ── Sticky first 3 cols ──────────────────────────────────── */
    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .custom--table { min-width: 1300px; }
    .custom--table thead th:nth-child(1),.custom--table tbody td:nth-child(1) { position:sticky; left:0;    z-index:10; }
    .custom--table thead th:nth-child(2),.custom--table tbody td:nth-child(2) { position:sticky; left:40px; z-index:10; }
    .custom--table thead th:nth-child(3),.custom--table tbody td:nth-child(3) { position:sticky; left:120px;z-index:10; }

    /* ── Loading overlay ─────────────────────────────────────── */
    .loading-overlay {
        position:absolute; top:0; left:0; right:0; bottom:0;
        background:rgba(19,45,57,0.95);
        display:flex; flex-direction:column;
        justify-content:center; align-items:center;
        z-index:1000; border-radius:12px;
    }
    .spinner { width:50px; height:50px; border:5px solid #f3f3f3; border-top:5px solid #3498db; border-radius:50%; animation:spin 1s linear infinite; }
    .loading-text { color:white; margin-top:20px; font-size:16px; font-weight:600; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    /* ── Action badges ────────────────────────────────────────── */
    .action-buy-ce { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .action-buy-pe { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .action-wait   { background:linear-gradient(135deg,#ffc107,#ff9800); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Sentiment badges ─────────────────────────────────────── */
    .sentiment-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Strength badges ─────────────────────────────────────── */
    .strength-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .strength-normal  { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Exit col highlight ───────────────────────────────────── */
    .th-exit { background:rgba(168,85,247,0.15); color:#a855f7 !important; }
    .td-exit  { background:rgba(168,85,247,0.04); }
    .exit-badge { background:linear-gradient(135deg,#a855f7,#7c3aed); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Profit / P&L ─────────────────────────────────────────── */
    .profit-positive { color:#28a745; font-weight:700; font-size:11px; }
    .profit-negative { color:#dc3545; font-weight:700; font-size:11px; }
    .profit-loading  { color:#aaa; font-size:10px; font-style:italic; }

    /* ── Filter section ──────────────────────────────────────── */
    .filter-section {
        background: linear-gradient(135deg,#667eea,#764ba2);
        padding:20px; border-radius:12px; margin-bottom:20px;
        box-shadow:0 4px 15px rgba(102,126,234,0.4); color:white;
    }
    .filter-section label { color:white !important; font-weight:600; margin-bottom:6px; font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.9); color:#333; font-size:12px; padding:6px 10px; }

    /* ── Stats boxes ─────────────────────────────────────────── */
    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.1); transition:transform .2s; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#666; font-size:10px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box strong { display:block; font-size:1.4rem; font-weight:700; margin-top:3px; }

    /* ── Page header ─────────────────────────────────────────── */
    .page-header { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4); }

    /* ── Account section ─────────────────────────────────────── */
    .acc-section { margin-bottom:30px; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,.12); overflow:hidden; }
    .acc-section-hdr { display:flex; align-items:center; gap:12px; padding:12px 20px; font-weight:700; font-size:13px; }
    .acc-zzl-hdr { background:linear-gradient(135deg,#00514a,#007c73); color:white; }
    .acc-oqj-hdr { background:linear-gradient(135deg,#1e2e6e,#2d44ad); color:white; }
    .acc-tag-pill { font-size:9px; padding:2px 9px; border-radius:20px; background:rgba(255,255,255,.2); font-weight:700; text-transform:uppercase; letter-spacing:.5px; }

    /* ── Misc ────────────────────────────────────────────────── */
    .new-feature-badge { background:linear-gradient(135deg,#f093fb,#f5576c); color:white; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }
    .inv-tag  { background:#e0f2fe; color:#0369a1; padding:2px 7px; border-radius:4px; font-size:10px; font-weight:700; display:inline-block; }
    .lots-tag { background:#fef3c7; color:#92400e; padding:2px 7px; border-radius:4px; font-size:10px; font-weight:700; display:inline-block; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4>Account-Wise EOD Analysis <span class="new-feature-badge">BTST</span></h4>
                <p style="margin:0; font-size:12px; opacity:.85;">
                    Signal @ 3 PM &nbsp;|&nbsp; Buy = Today 15:15 close (fallback: 15:00) &nbsp;|&nbsp; Exit = Next day MAX high of 09:15 / 09:30 / 09:45
                </p>
            </div>
            <div>
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm mr-2"><i class="fas fa-clock"></i> PECE</a>
                <a href="{{ route('oiiv-auto.index') }}"         class="btn btn-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
            </div>
        </div>
    </div>

    {{-- ── Filters ───────────────────────────────────────────────── --}}
    <div class="filter-section">
        <div class="row mb-2">
            <div class="col-md-2">
                <label><i class="fas fa-calendar-alt"></i> From Date:</label>
                <input type="date" id="aw_from" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-calendar-alt"></i> To Date:</label>
                <input type="date" id="aw_to" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-wallet"></i> Investment / Stock:</label>
                <select id="aw_investment" class="form-control">
                    <option value="100000">₹1 Lakh</option>
                    <option value="200000">₹2 Lakh</option>
                    <option value="300000">₹3 Lakh</option>
                    <option value="400000">₹4 Lakh</option>
                    <option value="500000">₹5 Lakh</option>
                    <option value="600000">₹6 Lakh</option>
                    <option value="700000">₹7 Lakh</option>
                    <option value="800000">₹8 Lakh</option>
                    <option value="900000">₹9 Lakh</option>
                    <option value="1000000" selected>₹10 Lakh</option>
                </select>
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-filter"></i> Account:</label>
                <select id="aw_account" class="form-control">
                    <option value="both">Both (ZZL + OQJ)</option>
                    <option value="ZZL">ZZL Only</option>
                    <option value="OQJ">OQJ Only</option>
                </select>
            </div>
            <div class="col-md-4 text-center" style="margin-top:22px;">
                <button type="button" id="aw_run" class="btn btn-light btn-lg" style="min-width:150px; font-size:13px;">
                    <i class="fas fa-search"></i> View Data
                </button>
                <button type="button" id="aw_reset" class="btn btn-outline-light btn-lg ml-2" style="min-width:150px; font-size:13px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ── Combined stats ───────────────────────────────────────── --}}
    <div class="row mb-3" id="combined_stats" style="display:none;">
        <div class="col-md-2"><div class="stats-box"><small>Total Signals</small><strong id="sum_total" class="text-dark">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE</small><strong id="sum_ce" style="color:#28a745;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE</small><strong id="sum_pe" style="color:#dc3545;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#ffc107;"><small>WAIT</small><strong id="sum_wait" style="color:#ffc107;">0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Exit Total P/L</small><strong id="sum_exit_pl" style="font-size:1rem;">₹0</strong></div></div>
        <div class="col-md-2"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Exit Win Rate</small><strong id="sum_exit_wr" style="color:#a855f7; font-size:1rem;">0%</strong></div></div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- ZZL ACCOUNT                                                --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="acc-section">
        <div class="acc-section-hdr acc-zzl-hdr">
            <span style="font-size:20px;">🟢</span>
            <span>ZZL Account</span>
            <span class="acc-tag-pill">EOD BTST</span>
            <span style="font-size:10px; opacity:.75; margin-left:auto; font-weight:400;">
                ASIANPAINT &nbsp;·&nbsp; AUROPHARMA &nbsp;·&nbsp; BSE &nbsp;·&nbsp; MCX &nbsp;·&nbsp; BDL
            </span>
        </div>

        <div class="row px-3 pt-3 pb-0" id="zzl_stats" style="display:none;">
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#17a2b8;"><small>Signals</small><strong id="zzl_total" class="text-dark">0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE</small><strong id="zzl_ce" style="color:#28a745;">0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE</small><strong id="zzl_pe" style="color:#dc3545;">0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#ffc107;"><small>WAIT</small><strong id="zzl_wait" style="color:#ffc107;">0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Exit P/L</small><strong id="zzl_exit_pl" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Avg Exit ROI</small><strong id="zzl_exit_roi" style="color:#a855f7; font-size:1rem;">0%</strong></div></div>
        </div>

        <div style="position:relative; min-height:300px;">
            <div class="loading-overlay" id="zzl_loading" style="display:none;">
                <div class="spinner"></div>
                <div class="loading-text">Loading ZZL data…</div>
            </div>
            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Symbol</th>
                            <th>CE OI</th>
                            <th>CE %</th>
                            <th>PE OI</th>
                            <th>PE %</th>
                            <th>Sentiment</th>
                            <th>Strength</th>
                            <th>Action</th>
                            <th>Option</th>
                            <th>Investment</th>
                            <th>Buy ₹</th>
                            <th>Exit ₹</th>
                            <th>Exit P/L</th>
                            <th>Exit ROI%</th>
                        </tr>
                    </thead>
                    <tbody id="zzl_tbody">
                        <tr><td colspan="16" class="text-center py-5">
                            <i class="fas fa-chart-pie" style="font-size:3rem; opacity:0.5;"></i>
                            <p style="font-size:1.1rem; margin-top:20px;">Click <strong>"View Data"</strong> to load ZZL signals</p>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- OQJ ACCOUNT                                                --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="acc-section">
        <div class="acc-section-hdr acc-oqj-hdr">
            <span style="font-size:20px;">🔵</span>
            <span>OQJ Account</span>
            <span class="acc-tag-pill">EOD BTST</span>
            <span style="font-size:10px; opacity:.75; margin-left:auto; font-weight:400;">
                DRREDDY &nbsp;·&nbsp; CHOLAFIN &nbsp;·&nbsp; AXISBANK &nbsp;·&nbsp; HEROMOTOCO &nbsp;·&nbsp; BAJAJFINSV &nbsp;·&nbsp; BAJAJ-AUTO &nbsp;·&nbsp; CDSL &nbsp;·&nbsp; ADANIPORTS
            </span>
        </div>

        <div class="row px-3 pt-3 pb-0" id="oqj_stats" style="display:none;">
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#17a2b8;"><small>Signals</small><strong id="oqj_total" class="text-dark">0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#28a745;"><small>BUY CE</small><strong id="oqj_ce" style="color:#28a745;">0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#dc3545;"><small>BUY PE</small><strong id="oqj_pe" style="color:#dc3545;">0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#ffc107;"><small>WAIT</small><strong id="oqj_wait" style="color:#ffc107;">0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Exit P/L</small><strong id="oqj_exit_pl" style="font-size:1rem;">₹0</strong></div></div>
            <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#a855f7;"><small>🚪 Avg Exit ROI</small><strong id="oqj_exit_roi" style="color:#a855f7; font-size:1rem;">0%</strong></div></div>
        </div>

        <div style="position:relative; min-height:300px;">
            <div class="loading-overlay" id="oqj_loading" style="display:none;">
                <div class="spinner"></div>
                <div class="loading-text">Loading OQJ data…</div>
            </div>
            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Symbol</th>
                            <th>CE OI</th>
                            <th>CE %</th>
                            <th>PE OI</th>
                            <th>PE %</th>
                            <th>Sentiment</th>
                            <th>Strength</th>
                            <th>Action</th>
                            <th>Option</th>
                            <th>Investment</th>
                            <th>Buy ₹<br><small style="font-weight:400;opacity:.7;">Today 15:15</small></th>
                            <th>Exit ₹<br><small style="font-weight:400;opacity:.8;">Next day best of 09:15/09:30/09:45</small></th>
                            <th>Exit P/L</th>
                            <th>Exit ROI%</th>
                        </tr>
                    </thead>
                    <tbody id="oqj_tbody">
                        <tr><td colspan="16" class="text-center py-5">
                            <i class="fas fa-chart-pie" style="font-size:3rem; opacity:0.5;"></i>
                            <p style="font-size:1.1rem; margin-top:20px;">Click <strong>"View Data"</strong> to load OQJ signals</p>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
$(function () {

    /* ── Formatters ─────────────────────────────────────────────── */
    function fmtOI(n) {
        n = Number(n) || 0;
        if (n >= 1e6) return (n / 1e6).toFixed(2) + 'M';
        if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
        return n;
    }
    function plHtml(v) {
        v = Number(v) || 0;
        return `<strong class="${v >= 0 ? 'profit-positive' : 'profit-negative'}">${v >= 0 ? '+' : ''}₹${Math.abs(v).toFixed(2)}</strong>`;
    }
    function roiHtml(v) {
        v = Number(v) || 0;
        return `<strong class="${v >= 0 ? 'profit-positive' : 'profit-negative'}">${v >= 0 ? '+' : ''}${Math.abs(v).toFixed(2)}%</strong>`;
    }

    /* ── Badges ─────────────────────────────────────────────────── */
    function sentBadge(s) {
        if (s === 'BULLISH') return '<span class="sentiment-bullish">📈 BULLISH</span>';
        if (s === 'BEARISH') return '<span class="sentiment-bearish">📉 BEARISH</span>';
        return '<span class="sentiment-neutral">⏸ NEUTRAL</span>';
    }
    function actBadge(a) {
        if (a === 'BUY CE') return '<span class="action-buy-ce">📈 BUY CE</span>';
        if (a === 'BUY PE') return '<span class="action-buy-pe">📉 BUY PE</span>';
        return '<span class="action-wait">⏸ WAIT</span>';
    }
    function strengthBadge(rank, sentiment) {
        if (rank === 'Normal') return '<span class="strength-normal">Normal</span>';
        const n    = (rank || '').replace('Rank ', '');
        const bull = sentiment === 'BULLISH';
        return `<span class="${bull ? 'strength-bullish' : 'strength-bearish'}">${bull ? '📈' : '📉'} ${bull ? 'BULL' : 'BEAR'} (R${n})</span>`;
    }

    /* ── Build one <tr> ─────────────────────────────────────────── */
    function buildRow(row, i) {
        const cePct  = Number(row.ce_oi_change_pct) || 0;
        const pePct  = Number(row.pe_oi_change_pct) || 0;
        const ceCls  = cePct >= 0 ? 'text-success' : 'text-danger';
        const peCls  = pePct >= 0 ? 'text-success' : 'text-danger';
        const isWait = row.trade_action === 'WAIT';
        const dash   = '<span class="profit-loading">—</span>';

        /* Option symbol */
        const optCell = isWait
            ? dash
            : row.profit_error
                ? `<span class="badge badge-warning" style="font-size:9px;" title="${row.profit_error}">⚠ ${row.profit_error}</span>`
                : row.option_symbol
                    ? `<span title="${row.option_symbol}">${row.option_symbol}</span>`
                    : '<span class="profit-loading">N/A</span>';

        /* Investment + lots */
        const invCell = isWait
            ? dash
            : row.investment_actual > 0
                ? `<span class="inv-tag">₹${Number(row.investment_actual).toLocaleString('en-IN')}</span>`
                  + (row.lots_bought ? `<br><span class="lots-tag">×${row.lots_bought} lots</span>` : '')
                : '<span class="profit-loading">N/A</span>';

        /* Buy price */
        const buyCell = isWait
            ? dash
            : row.buy_price > 0
                ? `<strong>₹${Number(row.buy_price).toFixed(2)}</strong>`
                : '<span class="profit-loading">N/A</span>';

        /* Exit price — DB only, show N/A if missing */
        const exitPr  = Number(row.exit_price) || 0;
        const exitCell = isWait
            ? '<span class="profit-loading">WAIT</span>'
            : exitPr > 0
                ? `<span class="exit-badge">₹${exitPr.toFixed(2)}</span>`
                : '<span class="profit-loading">N/A</span>';

        const exitPlCell  = isWait || exitPr <= 0 ? dash : plHtml(row.exit_pl);
        const exitRoiCell = isWait || exitPr <= 0 ? dash : roiHtml(row.exit_roi);

        return `
        <tr>
            <td><strong>${i}</strong></td>
            <td><strong>${row.date}</strong></td>
            <td><strong style="color:#667eea;">${row.symbol}</strong></td>
            <td>
                <strong>${fmtOI(row.ce_oi)}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.ce_oi || 0).toLocaleString()}</small>
            </td>
            <td class="${ceCls}"><strong>${cePct >= 0 ? '+' : ''}${cePct.toFixed(2)}%</strong></td>
            <td>
                <strong>${fmtOI(row.pe_oi)}</strong><br>
                <small style="color:#aaa;font-size:9px;">${Number(row.pe_oi || 0).toLocaleString()}</small>
            </td>
            <td class="${peCls}"><strong>${pePct >= 0 ? '+' : ''}${pePct.toFixed(2)}%</strong></td>
            <td>${sentBadge(row.sentiment)}</td>
            <td>${strengthBadge(row.strength_rank, row.sentiment)}</td>
            <td>${actBadge(row.trade_action)}</td>
            <td>${optCell}</td>
            <td>${invCell}</td>
            <td>${buyCell}</td>
            <td class="td-exit">${exitCell}</td>
            <td class="td-exit">${exitPlCell}</td>
            <td class="td-exit">${exitRoiCell}</td>
        </tr>`;
    }

    /* ── Stats for one account ──────────────────────────────────── */
    function applyStats(pfx, rows) {
        const trades  = rows.filter(r => r.trade_action !== 'WAIT' && Number(r.exit_price) > 0);
        const exitPL  = trades.reduce((s, r) => s + (Number(r.exit_pl)  || 0), 0);
        const exitROI = trades.length
            ? trades.reduce((s, r) => s + (Number(r.exit_roi) || 0), 0) / trades.length
            : 0;
        const plCls = v => Number(v) >= 0 ? 'profit-positive' : 'profit-negative';

        $(`#${pfx}_stats`).show();
        $(`#${pfx}_total`).text(rows.length);
        $(`#${pfx}_ce`).text(rows.filter(r => r.trade_action === 'BUY CE').length);
        $(`#${pfx}_pe`).text(rows.filter(r => r.trade_action === 'BUY PE').length);
        $(`#${pfx}_wait`).text(rows.filter(r => r.trade_action === 'WAIT').length);
        $(`#${pfx}_exit_pl`).html(`<span class="${plCls(exitPL)}">${exitPL >= 0 ? '+' : ''}₹${Math.abs(exitPL).toFixed(2)}</span>`);
        $(`#${pfx}_exit_roi`).html(`<span class="${plCls(exitROI)}">${exitROI >= 0 ? '+' : ''}${Math.abs(exitROI).toFixed(2)}%</span>`);
    }

    /* ── Combined summary ───────────────────────────────────────── */
    function combinedStats(all) {
        const trades   = all.filter(r => r.trade_action !== 'WAIT' && Number(r.exit_price) > 0);
        const exitPL   = trades.reduce((s, r) => s + (Number(r.exit_pl) || 0), 0);
        const exitWins = trades.filter(r => Number(r.exit_pl) > 0).length;
        const wr       = trades.length ? ((exitWins / trades.length) * 100).toFixed(1) : 0;
        const plCls    = v => Number(v) >= 0 ? 'profit-positive' : 'profit-negative';

        $('#combined_stats').show();
        $('#sum_total').text(all.length);
        $('#sum_ce').text(all.filter(r => r.trade_action === 'BUY CE').length);
        $('#sum_pe').text(all.filter(r => r.trade_action === 'BUY PE').length);
        $('#sum_wait').text(all.filter(r => r.trade_action === 'WAIT').length);
        $('#sum_exit_pl').html(`<span class="${plCls(exitPL)}">${exitPL >= 0 ? '+' : ''}₹${Math.abs(exitPL).toFixed(2)}</span>`);
        $('#sum_exit_wr').html(`<span class="${parseFloat(wr) >= 50 ? 'profit-positive' : 'profit-negative'}">${wr}%</span>`);
    }

    /* ── Render table ───────────────────────────────────────────── */
    function renderTable(tbodyId, rows) {
        if (!rows || !rows.length) {
            $(`#${tbodyId}`).html('<tr><td colspan="16" class="text-center py-4 text-muted">No data for selected period</td></tr>');
            return;
        }
        let html = '';
        rows.forEach((row, i) => { html += buildRow(row, i + 1); });
        $(`#${tbodyId}`).html(html);
    }

    /* ── Main AJAX ───────────────────────────────────────────────── */
    function runAnalysis() {
        const from   = $('#aw_from').val();
        const to     = $('#aw_to').val();
        const acc    = $('#aw_account').val();
        const invest = $('#aw_investment').val();

        if (!from || !to) { alert('Please select both dates'); return; }

        $('#combined_stats').hide();
        if (acc === 'both' || acc === 'ZZL') { $('#zzl_loading').show(); $('#zzl_stats').hide(); }
        if (acc === 'both' || acc === 'OQJ') { $('#oqj_loading').show(); $('#oqj_stats').hide(); }

        $.ajax({
            url  : '{{ route("account-wise.analyze") }}',
            type : 'GET',
            data : { from_date: from, to_date: to, account: acc, investment: invest },
            success: function (res) {
                $('#zzl_loading, #oqj_loading').hide();

                if (!res.success || !res.data || !res.data.length) {
                    const msg = `<tr><td colspan="16" class="text-center py-4 text-muted">${res.message || 'No data found'}</td></tr>`;
                    if (acc === 'both' || acc === 'ZZL') { $('#zzl_tbody').html(msg); $('#zzl_stats').hide(); }
                    if (acc === 'both' || acc === 'OQJ') { $('#oqj_tbody').html(msg); $('#oqj_stats').hide(); }
                    return;
                }

                const zzlRows = res.data.filter(r => r.account === 'ZZL');
                const oqjRows = res.data.filter(r => r.account === 'OQJ');

                if (acc === 'both' || acc === 'ZZL') {
                    renderTable('zzl_tbody', zzlRows);
                    if (zzlRows.length) applyStats('zzl', zzlRows);
                }
                if (acc === 'both' || acc === 'OQJ') {
                    renderTable('oqj_tbody', oqjRows);
                    if (oqjRows.length) applyStats('oqj', oqjRows);
                }

                combinedStats(res.data);
            },
            error: function (xhr) {
                $('#zzl_loading, #oqj_loading').hide();
                const msg = `<tr><td colspan="16" class="text-center py-4 text-danger">Error: ${xhr.responseJSON?.message || 'Server error'}</td></tr>`;
                if (acc === 'both' || acc === 'ZZL') $('#zzl_tbody').html(msg);
                if (acc === 'both' || acc === 'OQJ') $('#oqj_tbody').html(msg);
            }
        });
    }

    function resetAll() {
        const today = '{{ date("Y-m-d") }}';
        $('#aw_from').val(today);
        $('#aw_to').val(today);
        $('#aw_account').val('both');
        $('#aw_investment').val('1000000');
        const ph = `<tr><td colspan="16" class="text-center py-5">
            <i class="fas fa-chart-pie" style="font-size:3rem; opacity:0.5;"></i>
            <p style="font-size:1.1rem; margin-top:20px;">Click <strong>"View Data"</strong> to load signals</p>
        </td></tr>`;
        $('#zzl_tbody, #oqj_tbody').html(ph);
        $('#zzl_stats, #oqj_stats').hide();
        $('#combined_stats').hide();
    }

    $('#aw_run').on('click', runAnalysis);
    $('#aw_reset').on('click', resetAll);
    runAnalysis();
});
</script>
@endpush