@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<style>
/* ══════════════════════════════════════════════════════════
   MONTHLY P&L DASHBOARD — STYLES
   ══════════════════════════════════════════════════════════ */

* { box-sizing: border-box; }

.mpnl-wrap {
    background: #080f1e;
    min-height: 100vh;
    padding: 20px 16px 40px;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: #e2e8f0;
}

/* ── Page Header ─────────────────────────────────────── */
.mpnl-header {
    background: linear-gradient(135deg, #0d1b35 0%, #132240 60%, #0a2040 100%);
    border: 1px solid rgba(56,189,248,.2);
    border-radius: 14px;
    padding: 18px 22px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.mpnl-header h4 { font-size: 18px; font-weight: 800; color: #38bdf8; margin: 0 0 4px; letter-spacing: -.3px; }
.mpnl-header p  { margin: 0; font-size: 11px; color: rgba(255,255,255,.45); }

.hdr-btns { display: flex; gap: 8px; flex-wrap: wrap; }
.hdr-btns a {
    font-size: 11px; padding: 6px 14px; border-radius: 7px;
    background: rgba(255,255,255,.08); color: rgba(255,255,255,.7);
    border: 1px solid rgba(255,255,255,.15); text-decoration: none;
    transition: all .2s;
}
.hdr-btns a:hover { background: rgba(56,189,248,.12); color: #38bdf8; border-color: rgba(56,189,248,.35); }

/* ── Filter Bar ──────────────────────────────────────── */
.filter-bar {
    background: linear-gradient(135deg, #0d1b35, #111e38);
    border: 1px solid rgba(56,189,248,.15);
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 18px;
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}
.fb-group { display: flex; flex-direction: column; gap: 4px; }
.fb-group label { font-size: 10.5px; font-weight: 600; color: rgba(255,255,255,.55); }
.fb-group .form-control {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(56,189,248,.2);
    color: #fff; font-size: 12px;
    border-radius: 7px; padding: 7px 10px;
    min-width: 140px;
}
.fb-group .form-control option { background: #0f2040; }
.fb-group .form-control:focus { outline: none; border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56,189,248,.15); }
.fb-group select[multiple] { min-width: 160px; }

.btn-run {
    background: linear-gradient(135deg, #38bdf8, #0284c7);
    color: #fff; font-weight: 700; border: none;
    padding: 8px 22px; border-radius: 8px; font-size: 13px;
    cursor: pointer; transition: opacity .2s;
}
.btn-run:hover { opacity: .85; }
.btn-reset {
    background: transparent; border: 1px solid rgba(56,189,248,.35);
    color: #38bdf8; font-weight: 600; padding: 8px 18px;
    border-radius: 8px; font-size: 13px; cursor: pointer; transition: all .2s;
}
.btn-reset:hover { background: rgba(56,189,248,.08); }

/* ── Loading Overlay ─────────────────────────────────── */
.loading-overlay {
    position: fixed; inset: 0; background: rgba(8,15,30,.96);
    display: flex; flex-direction: column;
    justify-content: center; align-items: center; z-index: 9999;
}
.spinner {
    width: 48px; height: 48px;
    border: 4px solid rgba(255,255,255,.08);
    border-top: 4px solid #38bdf8;
    border-radius: 50%; animation: spin 1s linear infinite;
}
.loading-text { color: #38bdf8; margin-top: 16px; font-size: 13px; font-weight: 600; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Summary Cards ───────────────────────────────────── */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-bottom: 18px;
}
.scard {
    background: linear-gradient(135deg, #0d1b35, #0f2040);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 12px;
    padding: 12px 14px;
    position: relative;
    overflow: hidden;
}
.scard::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: var(--accent, #38bdf8);
}
.scard small  { font-size: 9.5px; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 5px; }
.scard strong { font-size: 1.1rem; font-weight: 800; display: block; }

/* ── Monthly Table ───────────────────────────────────── */
.month-table-wrap {
    background: linear-gradient(135deg, #0d1b35, #0f2040);
    border: 1px solid rgba(56,189,248,.12);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 18px;
}
.month-table-header {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255,255,255,.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.month-table-header h6 { color: #38bdf8; font-size: 12px; font-weight: 700; margin: 0; }

.mpnl-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    min-width: 1000px;    /* wider to accommodate 2 new cols */
}
.mpnl-table thead th {
    background: rgba(0,0,0,.3);
    color: #94a3b8;
    padding: 9px 10px;
    font-size: 9.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,.06);
    white-space: nowrap;
}
.mpnl-table thead th:first-child { text-align: left; padding-left: 16px; }
.mpnl-table tbody td {
    padding: 9px 10px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,.04);
    vertical-align: middle;
}
.mpnl-table tbody td:first-child { text-align: left; padding-left: 16px; }

/* ── Month row ───────────────────────────────────────── */
.mpnl-table .month-row { cursor: pointer; transition: background .2s; }
.mpnl-table .month-row:hover { background: rgba(56,189,248,.05); }
.mpnl-table .month-row td { font-weight: 600; }
.mpnl-table .month-row.expanded { background: rgba(56,189,248,.04); }

.month-label { display: flex; align-items: center; gap: 8px; }
.toggle-icon {
    width: 18px; height: 18px; border-radius: 4px;
    background: rgba(56,189,248,.15); color: #38bdf8;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 900; transition: transform .2s; flex-shrink: 0;
}
.expanded .toggle-icon { transform: rotate(90deg); }

/* ── Day rows ────────────────────────────────────────── */
.mpnl-table .day-row { background: rgba(0,0,0,.15); display: none; }
.mpnl-table .day-row td { font-size: 10.5px; padding: 7px 10px; border-bottom: 1px solid rgba(255,255,255,.02); }
.mpnl-table .day-row td:first-child { padding-left: 40px; }
.mpnl-table .day-row.visible { display: table-row; }

/* ── Total row ───────────────────────────────────────── */
.mpnl-table .total-row td {
    background: rgba(56,189,248,.06);
    font-weight: 800;
    border-top: 2px solid rgba(56,189,248,.2);
    border-bottom: 2px solid rgba(56,189,248,.2);
    font-size: 11.5px;
}
.mpnl-table .total-row td:first-child { padding-left: 16px; color: #38bdf8; }

/* ── Column group tints ──────────────────────────────── */
.th-invest { background: rgba(245,158,11,.08) !important; color: #fbbf24 !important; }
.th-margin { background: rgba(139,92,246,.08) !important; color: #c4b5fd !important; }
td.th-invest { background: rgba(245,158,11,.03) !important; }
td.th-margin { background: rgba(139,92,246,.03) !important; }

/* ── P/L colors ──────────────────────────────────────── */
.pos { color: #10b981 !important; }
.neg { color: #ef4444 !important; }
.na  { color: #475569; font-size: 9px; }

/* ── Win Rate pill ───────────────────────────────────── */
.wr-pill { display: inline-block; padding: 2px 7px; border-radius: 20px; font-size: 9px; font-weight: 700; white-space: nowrap; }
.wr-good { background: rgba(16,185,129,.15); color: #10b981; border: 1px solid rgba(16,185,129,.25); }
.wr-mid  { background: rgba(245,158,11,.12); color: #f59e0b; border: 1px solid rgba(245,158,11,.2); }
.wr-bad  { background: rgba(239,68,68,.12);  color: #ef4444; border: 1px solid rgba(239,68,68,.2); }

/* ── Signal badges ───────────────────────────────────── */
.sig-bull { background: rgba(16,185,129,.15); color: #10b981; border-radius: 4px; padding: 1px 5px; font-size: 9px; font-weight: 700; }
.sig-bear { background: rgba(239,68,68,.12);  color: #ef4444; border-radius: 4px; padding: 1px 5px; font-size: 9px; font-weight: 700; }

/* ── Empty state ─────────────────────────────────────── */
.empty-state { text-align: center; padding: 60px 20px; color: rgba(255,255,255,.25); }
.empty-state i { font-size: 3rem; margin-bottom: 14px; display: block; }
.empty-state p { font-size: 13px; margin: 0; }

/* ── Table responsive ────────────────────────────────── */
.table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ── P/L display ─────────────────────────────────────── */
.pl-main { font-weight: 700; font-size: 11px; display: block; }

/* ── Currency display ────────────────────────────────── */
.amt-val { font-weight: 700; font-size: 10.5px; }
.amt-zero { color: #475569; font-size: 9px; }
</style>
@endpush

<div class="mpnl-wrap">

    {{-- ══ HEADER ══════════════════════════════════════════════ --}}
    <div class="mpnl-header">
        <div>
            <h4>📅 Monthly P&amp;L Dashboard</h4>
            <p>FUT + Option Sell Strategy · OI Signal @ 14:45 · Exit: Next Day 09:15–10:30</p>
        </div>
        <div class="hdr-btns">
            <a href="{{ route('fut-option-strategy.index') }}">⚡ Daily Strategy</a>
            <a href="{{ route('oiiv-auto.index') }}">📊 OI+IV</a>
        </div>
    </div>

    {{-- ══ FILTER BAR ═══════════════════════════════════════════ --}}
    <div class="filter-bar">
        <div class="fb-group">
            <label><i class="fas fa-calendar-alt"></i> Month</label>
            <select id="month_picker" class="form-control" style="min-width:165px;"></select>
        </div>
        <div class="fb-group">
            <label>From Date</label>
            <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-01') }}" />
        </div>
        <div class="fb-group">
            <label>To Date</label>
            <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
        </div>
        <div class="fb-group">
            <label>Symbols <small style="opacity:.5;">(optional)</small></label>
            <select id="symbol_filter" class="form-control" multiple size="2"></select>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;">
            <button type="button" id="run_analysis" class="btn-run">
                <i class="fas fa-search"></i> Load Dashboard
            </button>
            <button type="button" id="reset_btn" class="btn-reset">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </div>

    {{-- ══ LOADING ═══════════════════════════════════════════════ --}}
    <div class="loading-overlay" id="loading-overlay" style="display:none;">
        <div class="spinner"></div>
        <div class="loading-text">Crunching monthly data…</div>
    </div>

    {{-- ══ SUMMARY CARDS ════════════════════════════════════════ --}}
    <div class="summary-grid" id="summary-cards" style="display:none;">
        <div class="scard" style="--accent:#38bdf8;">
            <small>Total Active Trades</small>
            <strong id="sc_trades">0</strong>
        </div>
        <div class="scard" style="--accent:#f97316;">
            <small>Combined P&amp;L</small>
            <strong id="sc_comb_pl">₹0</strong>
        </div>
        <div class="scard" style="--accent:#38bdf8;">
            <small>FUT P&amp;L</small>
            <strong id="sc_fut_pl">₹0</strong>
        </div>
        <div class="scard" style="--accent:#10b981;">
            <small>Option P&amp;L</small>
            <strong id="sc_opt_pl">₹0</strong>
        </div>
        <div class="scard" style="--accent:#10b981;">
            <small>Win Rate</small>
            <strong id="sc_win_rate">0%</strong>
        </div>
        <div class="scard" style="--accent:#6366f1;">
            <small>Wins / Losses</small>
            <strong id="sc_wl">0 / 0</strong>
        </div>
        <div class="scard" style="--accent:#f59e0b;">
            {{-- Investment = FUT notional + Option premium × 2 lots --}}
            <small>Total Investment</small>
            <strong id="sc_investment">₹0</strong>
        </div>
        <div class="scard" style="--accent:#8b5cf6;">
            {{-- FUT Margin = live from Zerodha /margins/orders --}}
            <small>FUT Margin Needed</small>
            <strong id="sc_fut_margin">₹0</strong>
        </div>
        <div class="scard" style="--accent:#14b8a6;">
            <small>Months Covered</small>
            <strong id="sc_months">0</strong>
        </div>
        <div class="scard" style="--accent:#10b981;">
            <small>Bullish Signals</small>
            <strong id="sc_bull" style="color:#10b981;">0</strong>
        </div>
        <div class="scard" style="--accent:#ef4444;">
            <small>Bearish Signals</small>
            <strong id="sc_bear" style="color:#ef4444;">0</strong>
        </div>
    </div>

    {{-- ══ MAIN TABLE ═══════════════════════════════════════════ --}}
    <div class="month-table-wrap">
        <div class="month-table-header">
            <h6>📋 Month-wise Breakdown
                <span id="tbl-subtitle" style="font-weight:400;color:#64748b;font-size:10px;"></span>
            </h6>
            <small style="color:#475569;font-size:10px;">Click any month row to expand daily view</small>
        </div>
        <div class="table-scroll">
            <table class="mpnl-table" id="mpnl-table">
                <thead>
                    <tr>
                        <th>Month / Day</th>
                        <th>Trading<br>Days</th>
                        <th>Signals<br><small style="opacity:.6;">Bull / Bear</small></th>
                        <th>FUT P&amp;L</th>
                        <th>Option P&amp;L</th>
                        <th>Combined P&amp;L</th>
                        <th>Wins</th>
                        <th>Losses</th>
                        <th>Win Rate</th>
                        {{-- NEW columns --}}
                        <th class="th-invest">
                            Total Investment ₹<br>
                            <small style="opacity:.6;font-weight:400;">FUT + Opt premium</small>
                        </th>
                        <th class="th-margin">
                            FUT Margin ₹<br>
                            <small style="opacity:.6;font-weight:400;">Zerodha live</small>
                        </th>
                    </tr>
                </thead>
                <tbody id="mpnl-tbody">
                    <tr>
                        <td colspan="11">
                            <div class="empty-state">
                                <i class="fas fa-chart-bar"></i>
                                <p>Click <strong>"Load Dashboard"</strong> to generate monthly P&amp;L</p>
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
/* ══════════════════════════════════════════════════════════════
   MONTHLY P&L DASHBOARD — JS
   ══════════════════════════════════════════════════════════════ */

/* ── Helpers ──────────────────────────────────────────────── */
function inr(v) {
    const n   = parseFloat(v) || 0;
    const abs = Math.abs(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (n >= 0 ? '+' : '−') + '₹' + abs;
}
function inrPlain(v) {
    // For investment / margin (always positive, no + prefix)
    const n = parseFloat(v) || 0;
    if (n === 0) return '<span class="amt-zero">—</span>';
    return `<span class="amt-val">₹${Math.abs(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
}
function plClass(v)  { return parseFloat(v) >= 0 ? 'pos' : 'neg'; }
function wrClass(v)  { const p = parseFloat(v); return p >= 55 ? 'wr-good' : p >= 40 ? 'wr-mid' : 'wr-bad'; }
function wrPill(v)   { return `<span class="wr-pill ${wrClass(v)}">${v}%</span>`; }
function plBlock(v)  { return `<span class="pl-main ${plClass(v)}">${inr(v)}</span>`; }

/* ── Month picker (last 24 months) ───────────────────────── */
function buildMonthPicker() {
    const sel   = document.getElementById('month_picker');
    const today = new Date();
    let opts    = '<option value="custom">— Custom Range —</option>';
    for (let i = 0; i < 24; i++) {
        const d   = new Date(today.getFullYear(), today.getMonth() - i, 1);
        const val = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        const lbl = d.toLocaleString('default', { month: 'long', year: 'numeric' });
        opts += `<option value="${val}" ${i === 0 ? 'selected' : ''}>${lbl}</option>`;
    }
    sel.innerHTML = opts;
}

/* ── Sync dates when month changes ───────────────────────── */
document.getElementById('month_picker').addEventListener('change', function () {
    if (this.value === 'custom') return;
    const [y, m] = this.value.split('-').map(Number);
    const first  = `${y}-${String(m).padStart(2, '0')}-01`;
    const last   = new Date(y, m, 0).getDate();
    const today  = new Date();
    const isCurr = (y === today.getFullYear() && m === today.getMonth() + 1);
    const end    = isCurr
        ? `${y}-${String(m).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
        : `${y}-${String(m).padStart(2, '0')}-${String(last).padStart(2, '0')}`;
    document.getElementById('from_date').value = first;
    document.getElementById('to_date').value   = end;
});

/* ── Render ───────────────────────────────────────────────── */
function renderDashboard(summary, months) {

    // ── Summary cards ──────────────────────────────────────
    document.getElementById('sc_trades').textContent   = summary.trades;
    document.getElementById('sc_comb_pl').innerHTML    = `<span class="${plClass(summary.combined_pl)}">${inr(summary.combined_pl)}</span>`;
    document.getElementById('sc_fut_pl').innerHTML     = `<span class="${plClass(summary.fut_pl)}">${inr(summary.fut_pl)}</span>`;
    document.getElementById('sc_opt_pl').innerHTML     = `<span class="${plClass(summary.opt_pl)}">${inr(summary.opt_pl)}</span>`;
    document.getElementById('sc_win_rate').innerHTML   = wrPill(summary.win_rate);
    document.getElementById('sc_wl').innerHTML         = `<span class="pos">${summary.wins}</span> / <span class="neg">${summary.losses}</span>`;
    document.getElementById('sc_months').textContent   = summary.months;
    document.getElementById('sc_bull').textContent     = summary.bullish;
    document.getElementById('sc_bear').textContent     = summary.bearish;

    // Investment & margin summary cards (plain positive amounts)
    const inv = parseFloat(summary.investment) || 0;
    const mgn = parseFloat(summary.fut_margin) || 0;
    document.getElementById('sc_investment').innerHTML  = inv > 0
        ? `<span style="color:#fbbf24;">₹${inv.toLocaleString('en-IN', {maximumFractionDigits:0})}</span>`
        : '<span class="na">—</span>';
    document.getElementById('sc_fut_margin').innerHTML  = mgn > 0
        ? `<span style="color:#c4b5fd;">₹${mgn.toLocaleString('en-IN', {maximumFractionDigits:0})}</span>`
        : '<span class="na">—</span>';

    document.getElementById('summary-cards').style.display = 'grid';
    document.getElementById('tbl-subtitle').textContent    = `(${months.length} month${months.length !== 1 ? 's' : ''} · ${summary.trades} trades)`;

    // ── Table ───────────────────────────────────────────────
    let html = '';

    months.forEach((m, mi) => {
        const mId  = `month-${mi}`;
        const mCls = plClass(m.combined_pl);

        html += `
        <tr class="month-row" data-month="${mId}" onclick="toggleMonth('${mId}', this)">
            <td>
                <div class="month-label">
                    <span class="toggle-icon">▶</span>
                    <strong>${m.month_label}</strong>
                </div>
            </td>
            <td>${m.days.length} days</td>
            <td>
                <span class="sig-bull">▲ ${m.bullish}</span>&nbsp;
                <span class="sig-bear">▼ ${m.bearish}</span>
            </td>
            <td>${plBlock(m.fut_pl)}</td>
            <td>${plBlock(m.opt_pl)}</td>
            <td><strong class="${mCls}" style="font-size:12px;">${inr(m.combined_pl)}</strong></td>
            <td><span class="pos">${m.wins}</span></td>
            <td><span class="neg">${m.losses}</span></td>
            <td>${wrPill(m.win_rate)}</td>
            <td class="th-invest">${inrPlain(m.investment)}</td>
            <td class="th-margin">${inrPlain(m.fut_margin)}</td>
        </tr>`;

        // Day rows
        m.days.forEach(d => {
            const dayBg = parseFloat(d.combined_pl) >= 0
                ? 'rgba(16,185,129,.03)'
                : 'rgba(239,68,68,.03)';

            html += `
            <tr class="day-row" data-group="${mId}" style="background:${dayBg};">
                <td><strong style="font-size:10px;color:#94a3b8;">${d.day_name}</strong></td>
                <td>${d.trades}</td>
                <td>
                    <span class="sig-bull">▲ ${d.bullish}</span>&nbsp;
                    <span class="sig-bear">▼ ${d.bearish}</span>
                </td>
                <td>${plBlock(d.fut_pl)}</td>
                <td>${plBlock(d.opt_pl)}</td>
                <td><strong class="${plClass(d.combined_pl)}">${inr(d.combined_pl)}</strong></td>
                <td><span class="pos">${d.wins}</span></td>
                <td><span class="neg">${d.losses}</span></td>
                <td>${wrPill(d.win_rate)}</td>
                <td class="th-invest">${inrPlain(d.investment)}</td>
                <td class="th-margin">${inrPlain(d.fut_margin)}</td>
            </tr>`;
        });
    });

    // Grand Total row
    html += `
    <tr class="total-row">
        <td>📌 GRAND TOTAL</td>
        <td>${summary.months} month${summary.months !== 1 ? 's' : ''}</td>
        <td>
            <span class="sig-bull">▲ ${summary.bullish}</span>&nbsp;
            <span class="sig-bear">▼ ${summary.bearish}</span>
        </td>
        <td><strong class="${plClass(summary.fut_pl)}">${inr(summary.fut_pl)}</strong></td>
        <td><strong class="${plClass(summary.opt_pl)}">${inr(summary.opt_pl)}</strong></td>
        <td><strong class="${plClass(summary.combined_pl)}" style="font-size:13px;">${inr(summary.combined_pl)}</strong></td>
        <td><span class="pos"><strong>${summary.wins}</strong></span></td>
        <td><span class="neg"><strong>${summary.losses}</strong></span></td>
        <td>${wrPill(summary.win_rate)}</td>
        <td class="th-invest"><strong>${inrPlain(summary.investment)}</strong></td>
        <td class="th-margin"><strong>${inrPlain(summary.fut_margin)}</strong></td>
    </tr>`;

    document.getElementById('mpnl-tbody').innerHTML = html;
}

/* ── Month toggle ─────────────────────────────────────────── */
function toggleMonth(mId, rowEl) {
    const isExpanded = rowEl.classList.contains('expanded');
    rowEl.classList.toggle('expanded', !isExpanded);
    document.querySelectorAll(`.day-row[data-group="${mId}"]`).forEach(el => {
        el.classList.toggle('visible', !isExpanded);
    });
}

/* ── Empty / error state ──────────────────────────────────── */
function showEmpty(msg) {
    document.getElementById('mpnl-tbody').innerHTML = `
        <tr>
            <td colspan="11">
                <div class="empty-state">
                    <i class="fas fa-info-circle"></i>
                    <p>${msg}</p>
                </div>
            </td>
        </tr>`;
    document.getElementById('summary-cards').style.display = 'none';
    document.getElementById('tbl-subtitle').textContent    = '';
}

/* ── Fetch ────────────────────────────────────────────────── */
function runAnalysis() {
    const from = document.getElementById('from_date').value;
    const to   = document.getElementById('to_date').value;
    const syms = Array.from(document.getElementById('symbol_filter').selectedOptions).map(o => o.value);

    if (!from || !to) { alert('Please select both dates.'); return; }

    document.getElementById('loading-overlay').style.display = 'flex';

    $.ajax({
        url  : '{{ route("fut-option-monthly.analyze") }}',
        type : 'GET',
        data : { from_date: from, to_date: to, symbols: syms },

        success(res) {
            document.getElementById('loading-overlay').style.display = 'none';
            if (res.success && res.months && res.months.length) {
                renderDashboard(res.summary, res.months);
            } else {
                showEmpty(res.message || 'No data found for this date range.');
            }
        },

        error(xhr) {
            document.getElementById('loading-overlay').style.display = 'none';
            showEmpty('⚠ ' + (xhr.responseJSON?.message || 'Server error — check logs.'));
        },
    });
}

/* ── Init ─────────────────────────────────────────────────── */
$(document).ready(function () {

    buildMonthPicker();
    document.getElementById('month_picker').dispatchEvent(new Event('change'));

    $.get('{{ route("fut-option-strategy.symbols") }}', res => {
        if (!res.success) return;
        const opts = (res.symbols || []).map(s => `<option value="${s}">${s}</option>`).join('');
        document.getElementById('symbol_filter').innerHTML = opts;
    });

    setTimeout(runAnalysis, 350);
});

document.getElementById('run_analysis').addEventListener('click', runAnalysis);

document.getElementById('reset_btn').addEventListener('click', function () {
    const today = new Date();
    const val   = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
    document.getElementById('month_picker').value = val;
    document.getElementById('month_picker').dispatchEvent(new Event('change'));
    document.getElementById('symbol_filter').value = null;
    setTimeout(runAnalysis, 300);
});
</script>
@endpush