@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ── Variables ───────────────────────────────── */
    :root {
        --g-bg:        #0b1120;
        --g-surface:   #111827;
        --g-card:      #1a2233;
        --g-border:    rgba(99,120,180,0.18);
        --g-gold:      #f5c842;
        --g-cyan:      #22d3ee;
        --g-green:     #22c55e;
        --g-red:       #ef4444;
        --g-purple:    #a78bfa;
        --g-orange:    #fb923c;
        --g-muted:     #6b7280;
        --g-text:      #e2e8f0;
        --g-radius:    10px;
    }

    body { background: var(--g-bg) !important; color: var(--g-text) !important; }

    /* ── Page Header ─────────────────────────────── */
    .gann-header {
        background: linear-gradient(135deg, #1a1040 0%, #0f2027 50%, #1a1040 100%);
        border: 1px solid rgba(245,200,66,0.25);
        border-radius: 14px;
        padding: 22px 28px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }
    .gann-header::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at top right, rgba(245,200,66,0.07), transparent 60%);
    }
    .gann-header h4 { color: var(--g-gold); font-size: 20px; font-weight: 700; margin: 0 0 6px; }
    .gann-header p  { color: rgba(226,232,240,0.6); font-size: 12px; margin: 0; }

    /* ── Filter Section ──────────────────────────── */
    .gann-filters {
        background: var(--g-surface);
        border: 1px solid var(--g-border);
        border-radius: var(--g-radius);
        padding: 18px 20px;
        margin-bottom: 20px;
    }
    .gann-filters label   { color: rgba(226,232,240,0.75); font-size: 12px; font-weight: 600; margin-bottom: 6px; display: block; }
    .gann-filters .form-control {
        background: rgba(255,255,255,0.06);
        border: 1px solid var(--g-border);
        color: var(--g-text);
        font-size: 12px;
        border-radius: 6px;
        padding: 8px 12px;
    }
    .gann-filters .form-control:focus { border-color: var(--g-gold); box-shadow: 0 0 0 2px rgba(245,200,66,0.15); outline: none; }
    .btn-gann-run {
        background: linear-gradient(135deg, #f5c842, #e8a020);
        color: #0b1120;
        font-weight: 700;
        border: none;
        border-radius: 8px;
        padding: 10px 28px;
        font-size: 13px;
        cursor: pointer;
        transition: transform .15s, box-shadow .15s;
    }
    .btn-gann-run:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(245,200,66,0.3); }
    .btn-gann-reset {
        background: transparent;
        color: rgba(226,232,240,0.7);
        border: 1px solid var(--g-border);
        border-radius: 8px;
        padding: 10px 20px;
        font-size: 13px;
        cursor: pointer;
        transition: border-color .15s;
    }
    .btn-gann-reset:hover { border-color: var(--g-cyan); color: var(--g-cyan); }

    /* ── Stats Bar ───────────────────────────────── */
    .stats-bar {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    @media (max-width: 768px) { .stats-bar { grid-template-columns: repeat(3, 1fr); } }
    .stat-card {
        background: var(--g-card);
        border: 1px solid var(--g-border);
        border-radius: var(--g-radius);
        padding: 12px 14px;
        text-align: center;
        border-top: 3px solid var(--g-border);
        transition: transform .15s;
    }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-card.gold   { border-top-color: var(--g-gold); }
    .stat-card.green  { border-top-color: var(--g-green); }
    .stat-card.red    { border-top-color: var(--g-red); }
    .stat-card.cyan   { border-top-color: var(--g-cyan); }
    .stat-card.purple { border-top-color: var(--g-purple); }
    .stat-card.orange { border-top-color: var(--g-orange); }
    .stat-card small { display: block; color: var(--g-muted); font-size: 10px; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
    .stat-card strong { display: block; font-size: 1.5rem; font-weight: 700; color: var(--g-text); }

    /* ── Loading ─────────────────────────────────── */
    .loading-overlay {
        position: absolute; inset: 0;
        background: rgba(11,17,32,0.92);
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        z-index: 50; border-radius: var(--g-radius);
    }
    .spinner-gann {
        width: 44px; height: 44px;
        border: 4px solid rgba(245,200,66,0.2);
        border-top-color: var(--g-gold);
        border-radius: 50%;
        animation: spin 0.9s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-txt { color: var(--g-gold); margin-top: 14px; font-size: 13px; font-weight: 600; }

    /* ── Table Wrapper ───────────────────────────── */
    .table-wrap {
        background: var(--g-surface);
        border: 1px solid var(--g-border);
        border-radius: var(--g-radius);
        overflow: hidden;
        position: relative;
        min-height: 300px;
    }
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

    /* ── Table ───────────────────────────────────── */
    .gann-table { min-width: 1200px; width: 100%; border-collapse: collapse; }
    .gann-table thead th {
        background: #0f1929;
        color: rgba(226,232,240,0.65);
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        padding: 10px 10px;
        text-align: center;
        border-bottom: 1px solid var(--g-border);
        white-space: nowrap;
    }
    .gann-table thead th:first-child,
    .gann-table thead th:nth-child(2),
    .gann-table thead th:nth-child(3) { text-align: left; }

    .gann-table tbody tr {
        border-bottom: 1px solid rgba(99,120,180,0.08);
        transition: background .1s;
    }
    .gann-table tbody tr:hover { background: rgba(245,200,66,0.03); }
    .gann-table tbody td {
        padding: 9px 10px;
        font-size: 11px;
        text-align: center;
        color: var(--g-text);
        vertical-align: middle;
    }
    .gann-table tbody td:first-child,
    .gann-table tbody td:nth-child(2),
    .gann-table tbody td:nth-child(3) { text-align: left; }

    /* Sticky first 3 cols */
    .gann-table thead th:nth-child(1), .gann-table tbody td:nth-child(1) { position: sticky; left: 0;     z-index: 5; background: #0f1929; }
    .gann-table thead th:nth-child(2), .gann-table tbody td:nth-child(2) { position: sticky; left: 35px;  z-index: 5; background: #0f1929; }
    .gann-table thead th:nth-child(3), .gann-table tbody td:nth-child(3) { position: sticky; left: 110px; z-index: 5; background: #0f1929; }
    .gann-table tbody tr:hover td:nth-child(1),
    .gann-table tbody tr:hover td:nth-child(2),
    .gann-table tbody tr:hover td:nth-child(3) { background: #131f33; }

    /* ── Badges ──────────────────────────────────── */
    .badge-pill {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
        white-space: nowrap;
    }
    .badge-buy-ce      { background: rgba(34,197,94,.18);  color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
    .badge-buy-pe      { background: rgba(239,68,68,.18);  color: #f87171; border: 1px solid rgba(239,68,68,.3); }
    .badge-wait        { background: rgba(245,200,66,.12); color: var(--g-gold); border: 1px solid rgba(245,200,66,.25); }

    .badge-strong-bull { background: #14532d; color: #86efac; border: 1px solid #16a34a; }
    .badge-bull        { background: rgba(34,197,94,.15);  color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
    .badge-strong-bear { background: #7f1d1d; color: #fca5a5; border: 1px solid #dc2626; }
    .badge-bear        { background: rgba(239,68,68,.15);  color: #f87171; border: 1px solid rgba(239,68,68,.3); }
    .badge-neutral     { background: rgba(107,114,128,.2); color: #9ca3af; border: 1px solid rgba(107,114,128,.3); }

    .badge-vol-strong  { background: rgba(34,211,238,.15); color: var(--g-cyan);   border: 1px solid rgba(34,211,238,.3); }
    .badge-vol-mod     { background: rgba(251,146,60,.15); color: var(--g-orange);  border: 1px solid rgba(251,146,60,.3); }
    .badge-vol-weak    { background: rgba(107,114,128,.15);color: #9ca3af;          border: 1px solid rgba(107,114,128,.3); }

    .badge-event       { background: rgba(239,68,68,.2);   color: #fca5a5; border: 1px solid rgba(239,68,68,.4); }
    .badge-big-move    { background: rgba(251,146,60,.15); color: var(--g-orange);  border: 1px solid rgba(251,146,60,.3); }
    .badge-normal      { background: transparent; color: var(--g-muted); border: 1px solid var(--g-border); }

    .badge-conf-high   { background: #14532d; color: #86efac; border: 1px solid #16a34a; }
    .badge-conf-med    { background: rgba(251,146,60,.15); color: var(--g-orange); border: 1px solid rgba(251,146,60,.3); }
    .badge-conf-low    { background: rgba(107,114,128,.2); color: #9ca3af; border: 1px solid rgba(107,114,128,.3); }
    .badge-conf-na     { background: transparent; color: var(--g-muted); border: 1px solid var(--g-border); }

    .badge-trap        { background: rgba(239,68,68,.12); color: #f87171; border: 1px solid rgba(239,68,68,.3); }
    .badge-expansion   { background: rgba(34,197,94,.12); color: #4ade80;  border: 1px solid rgba(34,197,94,.3); }
    .badge-exit-day    { background: rgba(167,139,250,.15); color: var(--g-purple); border: 1px solid rgba(167,139,250,.3); }
    .badge-setup       { background: rgba(245,200,66,.12); color: var(--g-gold); border: 1px solid rgba(245,200,66,.25); }
    .badge-astro-bull  { background: rgba(34,197,94,.12); color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
    .badge-astro-def   { background: rgba(107,114,128,.15); color: #9ca3af; border: 1px solid rgba(107,114,128,.3); }

    .badge-cycle-major { background: #7f1d1d; color: #fca5a5; border: 1px solid #dc2626; }
    .badge-cycle-vol   { background: rgba(251,146,60,.15); color: var(--g-orange); border: 1px solid rgba(251,146,60,.3); }
    .badge-cycle-rev   { background: rgba(167,139,250,.15); color: var(--g-purple); border: 1px solid rgba(167,139,250,.3); }
    .badge-cycle-norm  { background: transparent; color: var(--g-muted); border: 1px solid var(--g-border); }

    /* Gann level pill */
    .gann-level-pill {
        display: inline-block;
        background: rgba(245,200,66,.12);
        color: var(--g-gold);
        border: 1px solid rgba(245,200,66,.25);
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
    }

    /* OI sentiment */
    .oi-bull { color: var(--g-green); font-weight: 700; font-size: 10px; }
    .oi-bear { color: var(--g-red);   font-weight: 700; font-size: 10px; }
    .oi-neut { color: var(--g-muted); font-weight: 700; font-size: 10px; }

    .chg-pos { color: var(--g-green); font-weight: 700; }
    .chg-neg { color: var(--g-red);   font-weight: 700; }
    .chg-neu { color: var(--g-muted); }

    /* Empty state */
    .empty-state { text-align: center; padding: 60px 20px; color: var(--g-muted); }
    .empty-state .icon { font-size: 3rem; margin-bottom: 16px; opacity: .5; }

    /* Gann octave mini-chart in cell */
    .octave-bar {
        height: 6px;
        background: rgba(245,200,66,.1);
        border-radius: 3px;
        margin-top: 4px;
        overflow: hidden;
    }
    .octave-bar-fill {
        height: 100%;
        border-radius: 3px;
        background: linear-gradient(90deg, var(--g-red), var(--g-gold), var(--g-green));
        transition: width .4s;
    }

    /* Legend bar */
    .legend-bar {
        background: var(--g-card);
        border: 1px solid var(--g-border);
        border-radius: var(--g-radius);
        padding: 12px 16px;
        margin-bottom: 16px;
        font-size: 11px;
        color: rgba(226,232,240,0.65);
        display: flex; gap: 20px; flex-wrap: wrap; align-items: center;
    }
    .legend-bar strong { color: var(--g-gold); }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ─────────────────────────────────────────── --}}
    <div class="gann-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
            <div>
                <h4>⚡ Gann Octave Analysis</h4>
                <p>Gann 8-level octave · Volume confirmation · OI sentiment · Event override · Astro day bias</p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn-gann-reset" style="text-decoration:none; display:inline-block;">
                    <i class="fas fa-chart-bar"></i> OI Analysis
                </a>
                <a href="{{ route('oiiv-auto.index') }}" class="btn-gann-reset" style="text-decoration:none; display:inline-block;">
                    <i class="fas fa-chart-line"></i> OI+IV
                </a>
            </div>
        </div>
    </div>

    {{-- ── Priority Legend ─────────────────────────────────── --}}
    <div class="legend-bar">
        <span>⚡ <strong>Signal Priority:</strong></span>
        <span>① <strong style="color:#ef4444;">Event Override</strong> (sharp move + high volume)</span>
        <span>② <strong style="color:#22d3ee;">OI + Gann Aligned</strong></span>
        <span>③ <strong style="color:#f5c842;">Strong Gann Only</strong></span>
        <span>④ <strong style="color:#22c55e;">OI Only</strong> (low confidence)</span>
        <span>⑤ <strong style="color:#6b7280;">WAIT</strong></span>
    </div>

    {{-- ── Filters ──────────────────────────────────────────── --}}
    <div class="gann-filters">
        <div class="row mb-3">
            <div class="col-md-2">
                <label><i class="fas fa-calendar-alt"></i> From Date</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-calendar-alt"></i> To Date</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-filter"></i> Symbols <small style="opacity:.6;">(optional)</small></label>
                <select id="symbol_filter" class="form-control" multiple size="2"></select>
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-crosshairs"></i> Signal Filter</label>
                <select id="action_filter" class="form-control">
                    <option value="">All Signals</option>
                    <option value="BUY CE">BUY CE Only</option>
                    <option value="BUY PE">BUY PE Only</option>
                    <option value="WAIT">WAIT Only</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end" style="gap:10px; padding-bottom:1px;">
                <button type="button" id="btn_run" class="btn-gann-run">
                    <i class="fas fa-bolt"></i> Analyze
                </button>
                <button type="button" id="btn_reset" class="btn-gann-reset">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ── Stats Bar ────────────────────────────────────────── --}}
    <div class="stats-bar">
        <div class="stat-card gold">  <small>Total</small>   <strong id="st_total">0</strong></div>
        <div class="stat-card green"> <small>BUY CE</small>  <strong id="st_ce" style="color:#22c55e;">0</strong></div>
        <div class="stat-card red">   <small>BUY PE</small>  <strong id="st_pe" style="color:#ef4444;">0</strong></div>
        <div class="stat-card cyan">  <small>WAIT</small>    <strong id="st_wait" style="color:#22d3ee;">0</strong></div>
        <div class="stat-card purple"><small>High Conf</small><strong id="st_high" style="color:#a78bfa;">0</strong></div>
        <div class="stat-card orange"><small>Events</small>  <strong id="st_events" style="color:#fb923c;">0</strong></div>
    </div>

    {{-- ── Table ────────────────────────────────────────────── --}}
    <div class="table-wrap">
        <div class="loading-overlay" id="loading_overlay" style="display:none;">
            <div class="spinner-gann"></div>
            <div class="loading-txt">Running Gann analysis…</div>
        </div>
        <div class="table-responsive">
            <table class="gann-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>LTP</th>
                        <th>Chg%</th>
                        <th>Gann Bias<br><small style="opacity:.6;">8-level zone</small></th>
                        <th>Near Level<br><small style="opacity:.6;">closest 1/8</small></th>
                        <th>Swing H/L<br><small style="opacity:.6;">20-day</small></th>
                        <th>Volume<br><small style="opacity:.6;">strength</small></th>
                        <th>Event</th>
                        <th>OI Signal</th>
                        <th>CE OI%</th>
                        <th>PE OI%</th>
                        <th>Astro Day</th>
                        <th>Time Cycle</th>
                        <th>Signal</th>
                        <th>Reason</th>
                        <th>Confidence</th>
                    </tr>
                </thead>
                <tbody id="gann_tbody">
                    <tr>
                        <td colspan="18">
                            <div class="empty-state">
                                <div class="icon">⚡</div>
                                <p>Click <strong style="color:var(--g-gold);">Analyze</strong> to run Gann Octave analysis</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
let gannData = [];

$(document).ready(function () {
    loadSymbols();
    setTimeout(() => runAnalysis(), 500);
});

function loadSymbols() {
    $.ajax({
        url: '{{ route("gann.symbols") }}',
        type: 'GET',
        success: function (res) {
            if (!res.success) return;
            let opts = '';
            res.symbols.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
            $('#symbol_filter').html(opts);
        }
    });
}

function runAnalysis() {
    const from   = $('#from_date').val();
    const to     = $('#to_date').val();
    const syms   = $('#symbol_filter').val() || [];
    const action = $('#action_filter').val();

    if (!from || !to) { alert('Please select both dates'); return; }

    $('#loading_overlay').show();
    gannData = [];

    $.ajax({
        url:  '{{ route("gann.analyze") }}',
        type: 'GET',
        data: { from_date: from, to_date: to, symbols: syms, filter_action: action },
        success: function (res) {
            if (res.success && res.data && res.data.length > 0) {
                gannData = res.data;
                renderTable();
                updateStats();
            } else {
                showEmpty(res.message || 'No data found for selected range');
                resetStats();
            }
            $('#loading_overlay').hide();
        },
        error: function () {
            showEmpty('Error loading data. Please try again.');
            resetStats();
            $('#loading_overlay').hide();
        }
    });
}

/* ── Render ──────────────────────────────────────────────── */
function renderTable() {
    if (!gannData.length) return;
    let html = '';

    gannData.forEach(function (row, i) {
        const levels   = row.gann_levels || [];
        const low      = levels[0] || 0;
        const high     = levels[8] || 1;
        const pct      = high > low ? Math.min(100, Math.max(0, ((row.ltp - low) / (high - low)) * 100)) : 0;

        html += `
        <tr>
            <td><span style="color:var(--g-muted);font-size:10px;">${i + 1}</span></td>
            <td><strong style="color:var(--g-cyan);font-size:11px;">${row.date}</strong></td>
            <td><strong style="color:var(--g-gold);">${row.symbol}</strong></td>
            <td><strong>₹${numFmt(row.ltp)}</strong></td>
            <td class="${row.change_percent > 0 ? 'chg-pos' : row.change_percent < 0 ? 'chg-neg' : 'chg-neu'}">
                ${row.change_percent > 0 ? '+' : ''}${row.change_percent}%
            </td>
            <td>${gannBiasBadge(row.gann_bias)}</td>
            <td>
                <span class="gann-level-pill">${row.near_level?.label ?? '—'}</span>
                <div style="font-size:9px;color:var(--g-muted);margin-top:2px;">₹${numFmt(row.near_level?.value ?? 0)}</div>
                <div class="octave-bar"><div class="octave-bar-fill" style="width:${pct.toFixed(1)}%"></div></div>
            </td>
            <td>
                <div style="font-size:10px;"><span style="color:var(--g-green);">H:₹${numFmt(row.swing_high)}</span></div>
                <div style="font-size:10px;"><span style="color:var(--g-red);">L:₹${numFmt(row.swing_low)}</span></div>
                <div style="font-size:9px;color:var(--g-muted);">${row.swing_period}d</div>
            </td>
            <td>${volBadge(row.vol_strength)}</td>
            <td>${eventBadge(row.event)}</td>
            <td><span class="${row.oi_sentiment === 'BULLISH' ? 'oi-bull' : row.oi_sentiment === 'BEARISH' ? 'oi-bear' : 'oi-neut'}">${oiIcon(row.oi_sentiment)} ${row.oi_sentiment}</span></td>
            <td class="${row.ce_oi_pct > 0 ? 'chg-pos' : row.ce_oi_pct < 0 ? 'chg-neg' : 'chg-neu'}">${row.ce_oi_pct > 0 ? '+' : ''}${row.ce_oi_pct}%</td>
            <td class="${row.pe_oi_pct > 0 ? 'chg-pos' : row.pe_oi_pct < 0 ? 'chg-neg' : 'chg-neu'}">${row.pe_oi_pct > 0 ? '+' : ''}${row.pe_oi_pct}%</td>
            <td>${astroBadge(row.astro_bias)}</td>
            <td>${cycleBadge(row.time_cycle)}</td>
            <td>${signalBadge(row.signal)}</td>
            <td><span style="font-size:9px;color:var(--g-muted);">${row.reason}</span></td>
            <td>${confBadge(row.confidence)}</td>
        </tr>`;
    });

    $('#gann_tbody').html(html);
}

/* ── Stats ───────────────────────────────────────────────── */
function updateStats() {
    $('#st_total').text(gannData.length);
    $('#st_ce').text(gannData.filter(r => r.signal === 'BUY CE').length);
    $('#st_pe').text(gannData.filter(r => r.signal === 'BUY PE').length);
    $('#st_wait').text(gannData.filter(r => r.signal === 'WAIT').length);
    $('#st_high').text(gannData.filter(r => r.confidence === 'HIGH').length);
    $('#st_events').text(gannData.filter(r => r.event === 'EVENT_MOVE').length);
}

function resetStats() {
    $('#st_total,#st_ce,#st_pe,#st_wait,#st_high,#st_events').text('0');
}

/* ── Badge helpers ───────────────────────────────────────── */
function gannBiasBadge(bias) {
    const map = {
        'STRONG_BULLISH': ['badge-strong-bull', '🟢🟢 S.Bull'],
        'BULLISH':        ['badge-bull',         '🟢 Bull'],
        'STRONG_BEARISH': ['badge-strong-bear',  '🔴🔴 S.Bear'],
        'BEARISH':        ['badge-bear',         '🔴 Bear'],
        'NEUTRAL':        ['badge-neutral',      '⚪ Neutral'],
    };
    const [cls, label] = map[bias] || ['badge-neutral', '—'];
    return `<span class="badge-pill ${cls}">${label}</span>`;
}

function volBadge(v) {
    if (v === 'STRONG')   return `<span class="badge-pill badge-vol-strong">⚡ Strong</span>`;
    if (v === 'MODERATE') return `<span class="badge-pill badge-vol-mod">📊 Moderate</span>`;
    return `<span class="badge-pill badge-vol-weak">— Weak</span>`;
}

function eventBadge(e) {
    if (e === 'EVENT_MOVE') return `<span class="badge-pill badge-event">🔥 EVENT</span>`;
    if (e === 'BIG_MOVE')   return `<span class="badge-pill badge-big-move">📈 Big</span>`;
    return `<span class="badge-pill badge-normal">Normal</span>`;
}

function signalBadge(s) {
    if (s === 'BUY CE') return `<span class="badge-pill badge-buy-ce">📈 BUY CE</span>`;
    if (s === 'BUY PE') return `<span class="badge-pill badge-buy-pe">📉 BUY PE</span>`;
    return `<span class="badge-pill badge-wait">⏸ WAIT</span>`;
}

function confBadge(c) {
    if (c === 'HIGH')   return `<span class="badge-pill badge-conf-high">🏆 High</span>`;
    if (c === 'MEDIUM') return `<span class="badge-pill badge-conf-med">📊 Medium</span>`;
    if (c === 'LOW')    return `<span class="badge-pill badge-conf-low">💡 Low</span>`;
    return `<span class="badge-pill badge-conf-na">—</span>`;
}

function astroBadge(a) {
    const map = {
        'BULLISH':   ['badge-astro-bull', '📈 Bull Day'],
        'EXPANSION': ['badge-expansion',  '🚀 Expansion'],
        'TRAP':      ['badge-trap',       '⚠ Trap Day'],
        'EXIT':      ['badge-exit-day',   '🚪 Exit Day'],
        'SETUP':     ['badge-setup',      '⏳ Setup'],
    };
    const [cls, label] = map[a] || ['badge-astro-def', a];
    return `<span class="badge-pill ${cls}">${label}</span>`;
}

function cycleBadge(c) {
    if (c === 'MAJOR_REVERSAL') return `<span class="badge-pill badge-cycle-major">🔄 Major Rev</span>`;
    if (c === 'VOLATILE')       return `<span class="badge-pill badge-cycle-vol">⚡ Volatile</span>`;
    if (c === 'REVERSAL_ZONE')  return `<span class="badge-pill badge-cycle-rev">↩ Rev Zone</span>`;
    return `<span class="badge-pill badge-cycle-norm">Normal</span>`;
}

function oiIcon(s) {
    if (s === 'BULLISH') return '📈';
    if (s === 'BEARISH') return '📉';
    return '⏸';
}

function numFmt(v) {
    return Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function showEmpty(msg) {
    $('#gann_tbody').html(`
        <tr><td colspan="18">
            <div class="empty-state">
                <div class="icon">📊</div>
                <p>${msg}</p>
            </div>
        </td></tr>`);
}

$('#btn_run').click(() => runAnalysis());
$('#btn_reset').click(function () {
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter,#action_filter').val('');
    gannData = [];
    showEmpty('Click Analyze to run Gann Octave analysis');
    resetStats();
    setTimeout(() => runAnalysis(), 300);
});
</script>
@endpush