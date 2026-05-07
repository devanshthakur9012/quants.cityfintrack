@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════════════
   FUT CONTRARIAN — MONTHLY P&L DASHBOARD
   ═══════════════════════════════════════════════════════════ */
* { box-sizing: border-box; }

.mpnl-wrap {
    background: #080f1e;
    min-height: 100vh;
    padding: 20px 16px 40px;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: #e2e8f0;
}

/* ── Header ──────────────────────────────────────────────── */
.mpnl-header {
    background: linear-gradient(135deg, #0d1b35, #132240, #0a2040);
    border: 1px solid rgba(0,212,255,.2);
    border-radius: 14px;
    padding: 18px 22px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.mpnl-header h4 { font-size: 18px; font-weight: 800; color: #00d4ff; margin: 0 0 4px; }
.mpnl-header p  { margin: 0; font-size: 11px; color: rgba(255,255,255,.45); }
.hdr-btns { display: flex; gap: 8px; flex-wrap: wrap; }
.hdr-btns a {
    font-size: 11px; padding: 6px 14px; border-radius: 7px;
    background: rgba(255,255,255,.08); color: rgba(255,255,255,.7);
    border: 1px solid rgba(255,255,255,.15); text-decoration: none; transition: all .2s;
}
.hdr-btns a:hover { background: rgba(0,212,255,.12); color: #00d4ff; border-color: rgba(0,212,255,.35); }

/* ── Filter bar ──────────────────────────────────────────── */
.filter-bar {
    background: linear-gradient(135deg, #0d1b35, #111e38);
    border: 1px solid rgba(0,212,255,.15);
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
    background: rgba(255,255,255,.06); border: 1px solid rgba(0,212,255,.2);
    color: #fff; font-size: 12px; border-radius: 7px; padding: 7px 10px; min-width: 140px;
}
.fb-group .form-control option { background: #0f2040; }
.fb-group .form-control:focus { outline: none; border-color: #00d4ff; box-shadow: 0 0 0 2px rgba(0,212,255,.15); }
.btn-run {
    background: linear-gradient(135deg, #00d4ff, #0284c7);
    color: #000; font-weight: 700; border: none;
    padding: 8px 22px; border-radius: 8px; font-size: 13px; cursor: pointer; transition: opacity .2s;
}
.btn-run:hover { opacity: .85; }
.btn-reset {
    background: transparent; border: 1px solid rgba(0,212,255,.35);
    color: #00d4ff; font-weight: 600; padding: 8px 18px;
    border-radius: 8px; font-size: 13px; cursor: pointer; transition: all .2s;
}
.btn-reset:hover { background: rgba(0,212,255,.08); }

/* ── Loading ─────────────────────────────────────────────── */
.loading-overlay {
    position: fixed; inset: 0; background: rgba(8,15,30,.96);
    display: flex; flex-direction: column;
    justify-content: center; align-items: center; z-index: 9999;
}
.spinner { width:48px;height:48px;border:4px solid rgba(255,255,255,.08);border-top:4px solid #00d4ff;border-radius:50%;animation:spin 1s linear infinite; }
.loading-text { color:#00d4ff;margin-top:16px;font-size:13px;font-weight:600; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Summary grid ────────────────────────────────────────── */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.scard {
    background: linear-gradient(135deg, #0d1b35, #0f2040);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 12px;
    padding: 11px 13px;
    position: relative; overflow: hidden;
}
.scard::before { content:''; position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,#00d4ff); }
.scard small  { font-size:9.5px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px; }
.scard strong { font-size:1.05rem;font-weight:800;display:block; }

/* ── Section header ──────────────────────────────────────── */
.section-head {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 16px;
    border-bottom: 1px solid rgba(255,255,255,.06);
}
.section-head h6 { color: var(--hcol, #00d4ff); font-size: 12px; font-weight: 700; margin: 0; }
.section-badge {
    font-size: 9px; font-weight: 700; padding: 2px 9px; border-radius: 20px;
    background: var(--badge-bg, rgba(0,212,255,.15)); color: var(--badge-col, #00d4ff);
}

/* ── Month table wrap ────────────────────────────────────── */
.month-table-wrap {
    background: linear-gradient(135deg, #0d1b35, #0f2040);
    border: 1px solid rgba(0,212,255,.12);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 22px;
}
.table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

.mpnl-table {
    width: 100%; border-collapse: collapse; font-size: 11px; min-width: 800px;
}
.mpnl-table thead th {
    background: rgba(0,0,0,.3); color: #94a3b8; padding: 9px 10px;
    font-size: 9.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .4px; text-align: center;
    border-bottom: 1px solid rgba(255,255,255,.06); white-space: nowrap;
}
.mpnl-table thead th:first-child { text-align: left; padding-left: 16px; }
.mpnl-table tbody td {
    padding: 9px 10px; text-align: center;
    border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle;
}
.mpnl-table tbody td:first-child { text-align: left; padding-left: 16px; }

/* ── Month row ───────────────────────────────────────────── */
.mpnl-table .month-row { cursor: pointer; transition: background .2s; }
.mpnl-table .month-row:hover { background: rgba(0,212,255,.05); }
.mpnl-table .month-row td { font-weight: 600; }
.month-label { display: flex; align-items: center; gap: 8px; }
.toggle-icon {
    width:18px;height:18px;border-radius:4px;background:rgba(0,212,255,.15);
    color:#00d4ff;display:inline-flex;align-items:center;justify-content:center;
    font-size:10px;font-weight:900;transition:transform .2s;flex-shrink:0;
}
.month-row.expanded .toggle-icon { transform: rotate(90deg); }

/* ── Day rows ────────────────────────────────────────────── */
.mpnl-table .day-row { background: rgba(0,0,0,.15); display: none; }
.mpnl-table .day-row td { font-size: 10.5px; padding: 7px 10px; border-bottom: 1px solid rgba(255,255,255,.02); }
.mpnl-table .day-row td:first-child { padding-left: 40px; }
.mpnl-table .day-row.visible { display: table-row; }

/* ── Total row ───────────────────────────────────────────── */
.mpnl-table .total-row td {
    background: rgba(0,212,255,.06); font-weight: 800;
    border-top: 2px solid rgba(0,212,255,.2);
    border-bottom: 2px solid rgba(0,212,255,.2); font-size: 11.5px;
}
.mpnl-table .total-row td:first-child { padding-left:16px; color:#00d4ff; }

/* ── P/L ─────────────────────────────────────────────────── */
.pos { color: #10b981 !important; } .neg { color: #ef4444 !important; }
.pl-main { font-weight:700;font-size:11px;display:block; }
.wr-pill { display:inline-block;padding:2px 7px;border-radius:20px;font-size:9px;font-weight:700; }
.wr-good { background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.25); }
.wr-mid  { background:rgba(245,158,11,.12);color:#f59e0b;border:1px solid rgba(245,158,11,.2); }
.wr-bad  { background:rgba(239,68,68,.12); color:#ef4444;border:1px solid rgba(239,68,68,.2);  }

/* ── Signal type badges ──────────────────────────────────── */
.type-ce { background:rgba(16,185,129,.15);color:#10b981;border-radius:4px;padding:1px 5px;font-size:9px;font-weight:700; }
.type-pe { background:rgba(239,68,68,.12); color:#ef4444;border-radius:4px;padding:1px 5px;font-size:9px;font-weight:700; }

/* ── Empty ───────────────────────────────────────────────── */
.empty-state { text-align:center;padding:50px 20px;color:rgba(255,255,255,.25); }
.empty-state i { font-size:3rem;margin-bottom:14px;display:block; }
.empty-state p  { font-size:13px;margin:0; }

.amt-zero { color:#475569;font-size:9px; }
.divider { border:none;border-top:1px solid rgba(0,212,255,.1);margin:6px 0 18px; }
</style>
@endpush

<div class="mpnl-wrap">

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="mpnl-header">
        <div>
            <h4>📅 Monthly P&amp;L — FUT Contrarian OI</h4>
            <p>OI-30min: Buy @ 10:00 · Sell @ Day High &nbsp;|&nbsp; OI-1HR: Buy @ 10:30 · Sell @ Day High after 10:30 &nbsp;|&nbsp; Aligned trades only</p>
        </div>
        <div class="hdr-btns">
            <a href="{{ route('fut-contrarian.index') }}">⚡ Daily Analysis</a>
            <a href="{{ route('oiiv-auto.pece-analysis') }}">📊 EOD Analysis</a>
        </div>
    </div>

    {{-- ── Filter bar ──────────────────────────────────────────── --}}
    <div class="filter-bar">
        <div class="fb-group">
            <label>Month</label>
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

    {{-- ── Loading ──────────────────────────────────────────────── --}}
    <div class="loading-overlay" id="loading-overlay" style="display:none;">
        <div class="spinner"></div>
        <div class="loading-text">Fetching trade dates…</div>
        <div style="margin-top:18px;width:280px;">
            <div style="background:rgba(255,255,255,.1);border-radius:20px;height:8px;overflow:hidden;">
                <div id="progress-bar" style="height:100%;background:#00d4ff;border-radius:20px;width:0%;transition:width .3s;"></div>
            </div>
            <div id="progress-text" style="color:rgba(255,255,255,.5);font-size:10px;margin-top:7px;text-align:center;"></div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         OI-30min SECTION
    ══════════════════════════════════════════════════════════════ --}}
    <div id="section-30m" style="display:none;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
            <span style="font-size:20px;">⏱</span>
            <h5 style="margin:0;color:#00d4ff;font-size:15px;font-weight:800;">OI-30min Trades</h5>
            <span style="background:rgba(0,212,255,.15);color:#00d4ff;font-size:9px;font-weight:700;padding:2px 9px;border-radius:20px;">
                Buy @ 10:00 Open · Sell @ Day High
            </span>
        </div>

        {{-- Summary cards 30m --}}
        <div class="summary-grid" id="summary-30m">
            <div class="scard" style="--accent:#00d4ff;"><small>Total Trades</small><strong id="s30_trades">—</strong></div>
            <div class="scard" style="--accent:#10b981;"><small>Wins</small><strong id="s30_wins" class="pos">—</strong></div>
            <div class="scard" style="--accent:#ef4444;"><small>Losses</small><strong id="s30_losses" class="neg">—</strong></div>
            <div class="scard" style="--accent:#a855f7;"><small>Win Rate</small><strong id="s30_winrate">—</strong></div>
            <div class="scard" style="--accent:#10b981;"><small>Total P/L</small><strong id="s30_pl">—</strong></div>
            <div class="scard" style="--accent:#f59e0b;"><small>Total Investment</small><strong id="s30_inv">—</strong></div>
            <div class="scard" style="--accent:#10b981;"><small>BUY CE Trades</small><strong id="s30_ce" style="color:#10b981;">—</strong></div>
            <div class="scard" style="--accent:#ef4444;"><small>BUY PE Trades</small><strong id="s30_pe" style="color:#ef4444;">—</strong></div>
            <div class="scard" style="--accent:#6366f1;"><small>Months</small><strong id="s30_months">—</strong></div>
        </div>

        {{-- Month table 30m --}}
        <div class="month-table-wrap">
            <div class="section-head" style="--hcol:#00d4ff;">
                <h6>📋 Month-wise Breakdown — OI-30min
                    <span id="sub30" style="font-weight:400;color:#64748b;font-size:10px;margin-left:6px;"></span>
                </h6>
                <small style="color:#475569;font-size:10px;margin-left:auto;">Click month to expand days</small>
            </div>
            <div class="table-scroll">
                <table class="mpnl-table">
                    <thead>
                        <tr>
                            <th>Month / Day</th>
                            <th>Trading Days</th>
                            <th>CE / PE<br><small style="opacity:.6;">Trade type</small></th>
                            <th>Total Trades</th>
                            <th>Wins</th>
                            <th>Losses</th>
                            <th>Win Rate</th>
                            <th>Total P/L</th>
                            <th>Total Investment</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-30m">
                        <tr><td colspan="9"><div class="empty-state"><i class="fas fa-chart-bar"></i><p>Click <strong>"Load Dashboard"</strong> to generate data</p></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <hr class="divider">

    {{-- ══════════════════════════════════════════════════════════
         OI-1HR SECTION
    ══════════════════════════════════════════════════════════════ --}}
    <div id="section-1h" style="display:none;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
            <span style="font-size:20px;">🕐</span>
            <h5 style="margin:0;color:#667eea;font-size:15px;font-weight:800;">OI-1HR Trades</h5>
            <span style="background:rgba(102,126,234,.15);color:#667eea;font-size:9px;font-weight:700;padding:2px 9px;border-radius:20px;">
                Buy @ 10:30 Open · Sell @ Day High after 10:30
            </span>
        </div>

        {{-- Summary cards 1HR --}}
        <div class="summary-grid" id="summary-1h">
            <div class="scard" style="--accent:#667eea;"><small>Total Trades</small><strong id="s1h_trades">—</strong></div>
            <div class="scard" style="--accent:#10b981;"><small>Wins</small><strong id="s1h_wins" class="pos">—</strong></div>
            <div class="scard" style="--accent:#ef4444;"><small>Losses</small><strong id="s1h_losses" class="neg">—</strong></div>
            <div class="scard" style="--accent:#a855f7;"><small>Win Rate</small><strong id="s1h_winrate">—</strong></div>
            <div class="scard" style="--accent:#10b981;"><small>Total P/L</small><strong id="s1h_pl">—</strong></div>
            <div class="scard" style="--accent:#f59e0b;"><small>Total Investment</small><strong id="s1h_inv">—</strong></div>
            <div class="scard" style="--accent:#10b981;"><small>BUY CE Trades</small><strong id="s1h_ce" style="color:#10b981;">—</strong></div>
            <div class="scard" style="--accent:#ef4444;"><small>BUY PE Trades</small><strong id="s1h_pe" style="color:#ef4444;">—</strong></div>
            <div class="scard" style="--accent:#6366f1;"><small>Months</small><strong id="s1h_months">—</strong></div>
        </div>

        {{-- Month table 1HR --}}
        <div class="month-table-wrap" style="border-color:rgba(102,126,234,.2);">
            <div class="section-head" style="--hcol:#667eea;">
                <h6>📋 Month-wise Breakdown — OI-1HR
                    <span id="sub1h" style="font-weight:400;color:#64748b;font-size:10px;margin-left:6px;"></span>
                </h6>
                <small style="color:#475569;font-size:10px;margin-left:auto;">Click month to expand days</small>
            </div>
            <div class="table-scroll">
                <table class="mpnl-table">
                    <thead>
                        <tr>
                            <th>Month / Day</th>
                            <th>Trading Days</th>
                            <th>CE / PE<br><small style="opacity:.6;">Trade type</small></th>
                            <th>Total Trades</th>
                            <th>Wins</th>
                            <th>Losses</th>
                            <th>Win Rate</th>
                            <th>Total P/L</th>
                            <th>Total Investment</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-1h">
                        <tr><td colspan="9"><div class="empty-state"><i class="fas fa-chart-bar"></i><p>Click <strong>"Load Dashboard"</strong> to generate data</p></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Empty state when no data at all --}}
    <div id="no-data-msg" style="display:none;">
        <div class="empty-state" style="color:rgba(255,255,255,.35);">
            <i class="fas fa-info-circle"></i>
            <p id="no-data-text">No aligned trades found for selected range.</p>
        </div>
    </div>

</div>
@endsection

@push('script')
<script>
/* ══════════════════════════════════════════════════════════════
   MONTHLY DASHBOARD JS
   ══════════════════════════════════════════════════════════════ */

/* ── Helpers ──────────────────────────────────────────────── */
function inr(v) {
    const n   = parseFloat(v) || 0;
    const abs = Math.abs(n).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 });
    return (n >= 0 ? '+' : '−') + '₹' + abs;
}
function inrPlain(v) {
    const n = parseFloat(v) || 0;
    if (n === 0) return '<span class="amt-zero">—</span>';
    return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 });
}
function plCls(v)  { return parseFloat(v) >= 0 ? 'pos' : 'neg'; }
function wrCls(v)  { const p = parseFloat(v); return p >= 55 ? 'wr-good' : p >= 40 ? 'wr-mid' : 'wr-bad'; }
function wrPill(v) { return `<span class="wr-pill ${wrCls(v)}">${v}%</span>`; }
function plBlock(v){ return `<span class="pl-main ${plCls(v)}">${inr(v)}</span>`; }

/* ── Month picker ─────────────────────────────────────────── */
function buildMonthPicker() {
    const sel   = document.getElementById('month_picker');
    const today = new Date();
    let opts    = '<option value="custom">— Custom Range —</option>';
    for (let i = 0; i < 24; i++) {
        const d   = new Date(today.getFullYear(), today.getMonth() - i, 1);
        const val = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
        const lbl = d.toLocaleString('default', { month:'long', year:'numeric' });
        opts += `<option value="${val}" ${i===0?'selected':''}>${lbl}</option>`;
    }
    sel.innerHTML = opts;
}

document.getElementById('month_picker').addEventListener('change', function () {
    if (this.value === 'custom') return;
    const [y, m] = this.value.split('-').map(Number);
    const first  = `${y}-${String(m).padStart(2,'0')}-01`;
    const last   = new Date(y, m, 0).getDate();
    const today  = new Date();
    const isCurr = (y === today.getFullYear() && m === today.getMonth()+1);
    const end    = isCurr
        ? `${y}-${String(m).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`
        : `${y}-${String(m).padStart(2,'0')}-${String(last).padStart(2,'0')}`;
    document.getElementById('from_date').value = first;
    document.getElementById('to_date').value   = end;
});

/* ── Table builder (shared for 30m and 1HR) ───────────────── */
function renderTable(tbodyId, togglePrefix, data) {
    const { months, summary } = data;
    if (!months || !months.length) return '';

    let html = '';

    months.forEach((m, mi) => {
        const mId  = `${togglePrefix}-m${mi}`;
        const mCls = plCls(m.pl);

        html += `
        <tr class="month-row" onclick="toggleMonth('${mId}', this)">
            <td><div class="month-label"><span class="toggle-icon">▶</span><strong>${m.month_label}</strong></div></td>
            <td>${m.days.length} days</td>
            <td>
                <span class="type-ce">CE ${m.ce_count}</span>&nbsp;
                <span class="type-pe">PE ${m.pe_count}</span>
            </td>
            <td>${m.trades}</td>
            <td><span class="pos">${m.wins}</span></td>
            <td><span class="neg">${m.losses}</span></td>
            <td>${wrPill(m.win_rate)}</td>
            <td>${plBlock(m.pl)}</td>
            <td>${inrPlain(m.investment)}</td>
        </tr>`;

        m.days.forEach(d => {
            const dayBg = parseFloat(d.pl) >= 0 ? 'rgba(16,185,129,.03)' : 'rgba(239,68,68,.03)';
            html += `
            <tr class="day-row" data-group="${mId}" style="background:${dayBg};">
                <td><strong style="font-size:10px;color:#94a3b8;">${d.day_name}</strong></td>
                <td>—</td>
                <td>
                    <span class="type-ce">CE ${d.ce_count}</span>&nbsp;
                    <span class="type-pe">PE ${d.pe_count}</span>
                </td>
                <td>${d.trades}</td>
                <td><span class="pos">${d.wins}</span></td>
                <td><span class="neg">${d.losses}</span></td>
                <td>${wrPill(d.win_rate)}</td>
                <td>${plBlock(d.pl)}</td>
                <td>${inrPlain(d.investment)}</td>
            </tr>`;
        });
    });

    // Grand total
    html += `
    <tr class="total-row">
        <td>📌 GRAND TOTAL</td>
        <td>${summary.months} month${summary.months !== 1 ? 's' : ''}</td>
        <td>
            <span class="type-ce">CE ${summary.ce_count}</span>&nbsp;
            <span class="type-pe">PE ${summary.pe_count}</span>
        </td>
        <td><strong>${summary.trades}</strong></td>
        <td><strong class="pos">${summary.wins}</strong></td>
        <td><strong class="neg">${summary.losses}</strong></td>
        <td>${wrPill(summary.win_rate)}</td>
        <td><strong class="${plCls(summary.pl)}" style="font-size:13px;">${inr(summary.pl)}</strong></td>
        <td><strong>${inrPlain(summary.investment)}</strong></td>
    </tr>`;

    document.getElementById(tbodyId).innerHTML = html;
}

/* ── Summary card filler ──────────────────────────────────── */
function fillSummaryCards(prefix, summary) {
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.innerHTML = val; };

    set(`${prefix}_trades`,  summary.trades);
    set(`${prefix}_wins`,    `<span class="pos">${summary.wins}</span>`);
    set(`${prefix}_losses`,  `<span class="neg">${summary.losses}</span>`);
    set(`${prefix}_winrate`, wrPill(summary.win_rate));
    set(`${prefix}_pl`,      `<span class="${plCls(summary.pl)}">${inr(summary.pl)}</span>`);
    set(`${prefix}_inv`,     inrPlain(summary.investment));
    set(`${prefix}_ce`,      summary.ce_count);
    set(`${prefix}_pe`,      summary.pe_count);
    set(`${prefix}_months`,  summary.months);
}

/* ── Month toggle ─────────────────────────────────────────── */
function toggleMonth(mId, rowEl) {
    const isExpanded = rowEl.classList.contains('expanded');
    rowEl.classList.toggle('expanded', !isExpanded);
    document.querySelectorAll(`.day-row[data-group="${mId}"]`)
        .forEach(el => el.classList.toggle('visible', !isExpanded));
}

/* ── State ────────────────────────────────────────────────── */
let allRows30m = [];
let allRows1h  = [];
let pendingDates = [];
let totalDates   = 0;
let symbols      = [];
let aborted      = false;

/* ── Progress bar ─────────────────────────────────────────── */
function setProgress(done, total) {
    const pct = total > 0 ? Math.round((done / total) * 100) : 0;
    document.getElementById('progress-bar').style.width  = pct + '%';
    document.getElementById('progress-text').textContent =
        `Processing day ${done} of ${total} (${pct}%)…`;
}

/* ── Main entry: fetch dates list first ───────────────────── */
function runAnalysis() {
    const from = document.getElementById('from_date').value;
    const to   = document.getElementById('to_date').value;
    symbols    = Array.from(document.getElementById('symbol_filter').selectedOptions).map(o => o.value);

    if (!from || !to) { alert('Please select both dates.'); return; }

    // Reset state
    aborted    = false;
    allRows30m = [];
    allRows1h  = [];

    document.getElementById('loading-overlay').style.display = 'flex';
    document.getElementById('section-30m').style.display     = 'none';
    document.getElementById('section-1h').style.display      = 'none';
    document.getElementById('no-data-msg').style.display     = 'none';
    setProgress(0, 1);

    $.ajax({
        url  : '{{ route("fut-contrarian-monthly.trade-dates") }}',
        type : 'GET',
        data : { from_date: from, to_date: to },
        success(res) {
            if (!res.success || !res.dates || res.dates.length === 0) {
                document.getElementById('loading-overlay').style.display = 'none';
                document.getElementById('no-data-text').textContent = 'No trading dates found.';
                document.getElementById('no-data-msg').style.display = 'block';
                return;
            }
            pendingDates = [...res.dates];
            totalDates   = res.dates.length;
            setProgress(0, totalDates);
            processNextDay(0);
        },
        error(xhr) {
            document.getElementById('loading-overlay').style.display = 'none';
            document.getElementById('no-data-text').textContent = '⚠ ' + (xhr.responseJSON?.message || 'Server error.');
            document.getElementById('no-data-msg').style.display = 'block';
        }
    });
}

/* ── Sequential day processor ─────────────────────────────── */
function processNextDay(index) {
    if (aborted || index >= pendingDates.length) {
        finalize();
        return;
    }

    const date = pendingDates[index];
    setProgress(index, totalDates);

    $.ajax({
        url     : '{{ route("fut-contrarian-monthly.analyze-day") }}',
        type    : 'GET',
        timeout : 30000,  // 30s per day — plenty for one date
        data    : { date: date, symbols: symbols },
        success(res) {
            if (res.success) {
                (res.rows_30m || []).forEach(r => allRows30m.push(r));
                (res.rows_1h  || []).forEach(r => allRows1h.push(r));
            }
            // Always move to next day, even if this day errored
            processNextDay(index + 1);
        },
        error() {
            // Skip failed day, continue
            processNextDay(index + 1);
        }
    });
}

/* ── Finalize: group + render once all days done ──────────── */
function finalize() {
    document.getElementById('loading-overlay').style.display = 'none';

    const d30 = groupByMonth(allRows30m);
    const d1h = groupByMonth(allRows1h);

    let hasAny = false;

    if (d30.summary.trades > 0) {
        fillSummaryCards('s30', d30.summary);
        renderTable('tbody-30m', '30m', d30);
        document.getElementById('sub30').textContent =
            `(${d30.summary.months} months · ${d30.summary.trades} trades)`;
        document.getElementById('section-30m').style.display = 'block';
        hasAny = true;
    }

    if (d1h.summary.trades > 0) {
        fillSummaryCards('s1h', d1h.summary);
        renderTable('tbody-1h', '1h', d1h);
        document.getElementById('sub1h').textContent =
            `(${d1h.summary.months} months · ${d1h.summary.trades} trades)`;
        document.getElementById('section-1h').style.display = 'block';
        hasAny = true;
    }

    if (!hasAny) {
        document.getElementById('no-data-text').textContent = 'No aligned trades found for this date range.';
        document.getElementById('no-data-msg').style.display = 'block';
    }
}

/* ── Client-side groupByMonth (mirrors PHP groupByMonth) ──── */
function groupByMonth(rows) {
    if (!rows || rows.length === 0) {
        return { months: [], summary: { trades:0, ce_count:0, pe_count:0, pl:0, investment:0, wins:0, losses:0, win_rate:0, months:0 } };
    }

    const monthMap = {};
    rows.forEach(r => {
        const month = r.date.substring(0, 7);
        const day   = r.date;
        if (!monthMap[month])    monthMap[month]    = {};
        if (!monthMap[month][day]) monthMap[month][day] = [];
        monthMap[month][day].push(r);
    });

    const months = [];

    Object.keys(monthMap).sort().forEach(month => {
        const days = monthMap[month];
        let mTrades = 0, mPL = 0, mInv = 0, mWins = 0, mLosses = 0, mCE = 0, mPE = 0;
        const dayRows = [];

        Object.keys(days).sort().forEach(day => {
            const trades   = days[day];
            const dCount   = trades.length;
            const dPL      = trades.reduce((s,t) => s + (parseFloat(t.pl)||0), 0);
            const dInv     = trades.reduce((s,t) => s + (parseFloat(t.investment)||0), 0);
            const dWins    = trades.filter(t => (parseFloat(t.pl)||0) > 0).length;
            const dLosses  = dCount - dWins;
            const dCE      = trades.filter(t => t.option_type === 'CE').length;
            const dPE      = trades.filter(t => t.option_type === 'PE').length;
            const dName    = new Date(day).toLocaleDateString('en-IN', { weekday:'short', day:'2-digit', month:'short' });

            dayRows.push({ date:day, day_name:dName, trades:dCount, ce_count:dCE, pe_count:dPE,
                pl:+dPL.toFixed(2), investment:+dInv.toFixed(2),
                wins:dWins, losses:dLosses,
                win_rate: dCount > 0 ? +(dWins/dCount*100).toFixed(1) : 0 });

            mTrades += dCount; mPL += dPL; mInv += dInv;
            mWins += dWins; mLosses += dLosses; mCE += dCE; mPE += dPE;
        });

        const monthLabel = new Date(month + '-15').toLocaleString('default', { month:'long', year:'numeric' });
        months.push({
            month, month_label: monthLabel, days: dayRows,
            trades:mTrades, ce_count:mCE, pe_count:mPE,
            pl:+mPL.toFixed(2), investment:+mInv.toFixed(2),
            wins:mWins, losses:mLosses,
            win_rate: mTrades > 0 ? +(mWins/mTrades*100).toFixed(1) : 0
        });
    });

    const totalTrades = months.reduce((s,m) => s + m.trades, 0);
    const totalPL     = months.reduce((s,m) => s + m.pl, 0);
    const totalInv    = months.reduce((s,m) => s + m.investment, 0);
    const totalWins   = months.reduce((s,m) => s + m.wins, 0);
    const totalLosses = months.reduce((s,m) => s + m.losses, 0);
    const totalCE     = months.reduce((s,m) => s + m.ce_count, 0);
    const totalPE     = months.reduce((s,m) => s + m.pe_count, 0);

    return {
        months,
        summary: {
            trades: totalTrades, ce_count: totalCE, pe_count: totalPE,
            pl: +totalPL.toFixed(2), investment: +totalInv.toFixed(2),
            wins: totalWins, losses: totalLosses,
            win_rate: totalTrades > 0 ? +(totalWins/totalTrades*100).toFixed(1) : 0,
            months: months.length
        }
    };
}

/* ── Init ─────────────────────────────────────────────────── */
$(document).ready(function () {
    buildMonthPicker();
    document.getElementById('month_picker').dispatchEvent(new Event('change'));

    $.get('{{ route("fut-contrarian.symbols") }}', res => {
        if (!res.success) return;
        const opts = (res.symbols || []).map(s => `<option value="${s}">${s}</option>`).join('');
        document.getElementById('symbol_filter').innerHTML = opts;
    });

    setTimeout(runAnalysis, 350);
});

document.getElementById('run_analysis').addEventListener('click', runAnalysis);

document.getElementById('reset_btn').addEventListener('click', function () {
    aborted = true;  // cancel any in-flight sequence
    const today = new Date();
    const val   = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}`;
    document.getElementById('month_picker').value = val;
    document.getElementById('month_picker').dispatchEvent(new Event('change'));
    document.getElementById('symbol_filter').value = null;
    setTimeout(runAnalysis, 300);
});
</script>
@endpush