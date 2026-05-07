@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    body { background: #0a0f1e; }

    /* ── Table core ──────────────────────────────────────── */
    .custom--table { min-width: 900px; border-collapse: separate; border-spacing: 0; }
    .custom--table thead th {
        background: rgba(0,210,255,0.06) !important;
        color: #fff !important;
        border-bottom: 1px solid rgba(0,210,255,0.15);
        padding: 10px 8px !important;
        font-size: 11px !important;
        font-weight: 700;
        text-align: center !important;
        white-space: nowrap;
    }
    .custom--table tbody td {
        padding: 9px 7px !important;
        font-size: 11px !important;
        text-align: center !important;
        vertical-align: middle;
        border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .custom--table thead th:nth-child(1),
    .custom--table thead th:nth-child(2),
    .custom--table tbody td:nth-child(1),
    .custom--table tbody td:nth-child(2) { text-align: left !important; }

    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .custom--table tbody tr:hover td { background: rgba(0,210,255,0.04) !important; }

    /* ── Column group colors ─────────────────────────────── */
    .th-entry { background: rgba(0,210,255,0.08) !important; }
    .th-gap   { background: rgba(255,193,7,0.07) !important; }
    .th-ce    { background: rgba(40,167,69,0.10) !important; }
    .th-pe    { background: rgba(220,53,69,0.10) !important; }
    .th-plan  { background: rgba(168,85,247,0.10) !important; }

    /* ── Loading ─────────────────────────────────────────── */
    .loading-overlay {
        position:absolute;top:0;left:0;right:0;bottom:0;
        background:rgba(10,15,30,0.96);display:flex;flex-direction:column;
        justify-content:center;align-items:center;z-index:1000;border-radius:14px;
    }
    .spinner { width:44px;height:44px;border:4px solid rgba(255,255,255,0.08);border-top:4px solid #00d2ff;border-radius:50%;animation:spin .85s linear infinite; }
    .loading-text { color:#00d2ff;margin-top:14px;font-size:13px;font-weight:600; }
    @keyframes spin { to{transform:rotate(360deg)} }

    /* ── Page header ─────────────────────────────────────── */
    .page-header {
        background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);
        color:white;padding:18px 22px;border-radius:14px;margin-bottom:18px;
        border:1px solid rgba(0,210,255,0.2);
    }
    .page-header h4 { color:#00d2ff;margin:0 0 4px;font-size:17px;font-weight:700; }
    .page-header p  { color:rgba(255,255,255,0.5);margin:0;font-size:11px; }

    /* ── Filter ──────────────────────────────────────────── */
    .filter-section {
        background:linear-gradient(135deg,#1a1a2e,#16213e);
        padding:18px 20px;border-radius:12px;margin-bottom:18px;
        border:1px solid rgba(0,210,255,0.18);
    }
    .filter-section label { color:rgba(255,255,255,0.8)!important;font-weight:600;margin-bottom:5px;font-size:12px;display:block; }
    .filter-section .form-control {
        border:1.5px solid rgba(0,210,255,0.25);background:rgba(255,255,255,0.06);
        color:white;font-size:12px;padding:7px 10px;border-radius:8px;
    }
    .filter-section .form-control:focus { border-color:#00d2ff;outline:none; }
    .filter-section .form-control option { background:#1a1a2e;color:white; }
    .btn-analyze {
        background:linear-gradient(135deg,#00d2ff,#3a7bd5);color:white;border:none;
        padding:9px 26px;border-radius:8px;font-size:13px;font-weight:700;
        cursor:pointer;transition:opacity .2s,transform .15s;
    }
    .btn-analyze:hover { opacity:.9;transform:translateY(-1px); }
    .btn-reset {
        background:transparent;color:rgba(255,255,255,.6);border:1.5px solid rgba(255,255,255,.2);
        padding:9px 20px;border-radius:8px;font-size:12px;font-weight:600;
        cursor:pointer;margin-left:8px;
    }

    /* ── Date banner ─────────────────────────────────────── */
    .date-banner {
        background:rgba(0,210,255,0.06);border:1px solid rgba(0,210,255,0.18);
        border-radius:9px;padding:9px 16px;margin-bottom:14px;
        display:flex;gap:20px;align-items:center;flex-wrap:wrap;font-size:12px;
    }
    .date-banner span { color:rgba(255,255,255,.5); }
    .date-banner strong { color:#00d2ff; }

    /* ── Stats ───────────────────────────────────────────── */
    .stats-row { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px; }
    .stat-card {
        flex:1;min-width:80px;background:rgba(255,255,255,0.03);
        border:1px solid rgba(255,255,255,0.07);border-radius:9px;
        padding:9px 10px;text-align:center;border-top:3px solid #00d2ff;
    }
    .stat-card small { display:block;color:rgba(255,255,255,.4);font-size:9px;text-transform:uppercase;letter-spacing:.3px; }
    .stat-card strong { display:block;font-size:1.2rem;font-weight:700;margin-top:3px;color:white; }
    .stat-card.green  { border-top-color:#28a745; }
    .stat-card.red    { border-top-color:#dc3545; }
    .stat-card.yellow { border-top-color:#ffc107; }
    .stat-card.purple { border-top-color:#a855f7; }

    /* ── Card ────────────────────────────────────────────── */
    .card { background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.07);border-radius:14px; }

    /* ── Price badges ────────────────────────────────────── */
    .price-entry  { color:#00d2ff;font-weight:700;font-size:12px; }
    .price-target { color:#28a745;font-weight:800;font-size:12px; }
    .price-sl     { color:#dc3545;font-weight:800;font-size:12px; }
    .pct-pos { color:#28a745;font-weight:600;font-size:10px; }
    .pct-neg { color:#dc3545;font-weight:600;font-size:10px; }

    /* ── Gap badges ──────────────────────────────────────── */
    .gap-up   { background:linear-gradient(135deg,#28a745,#20c997);color:white;padding:3px 8px;border-radius:5px;font-weight:700;font-size:10px;display:inline-block; }
    .gap-down { background:linear-gradient(135deg,#dc3545,#c82333);color:white;padding:3px 8px;border-radius:5px;font-weight:700;font-size:10px;display:inline-block; }
    .gap-flat { background:#2d3748;color:#a0aec0;padding:3px 8px;border-radius:5px;font-weight:700;font-size:10px;display:inline-block; }
    .str-strong   { background:rgba(255,71,87,0.2);color:#ff4757;border:1px solid rgba(255,71,87,0.4);padding:1px 6px;border-radius:3px;font-size:9px;font-weight:700;display:inline-block;margin-top:2px; }
    .str-moderate { background:rgba(255,165,2,0.15);color:#ffa502;border:1px solid rgba(255,165,2,0.35);padding:1px 6px;border-radius:3px;font-size:9px;font-weight:700;display:inline-block;margin-top:2px; }
    .str-weak     { color:rgba(255,255,255,.35);font-size:9px;display:inline-block;margin-top:2px; }

    /* ── Winner badge ────────────────────────────────────── */
    .winner-ce   { background:rgba(40,167,69,0.2);color:#28a745;border:1px solid rgba(40,167,69,0.4);padding:3px 10px;border-radius:5px;font-weight:800;font-size:11px;display:inline-block; }
    .winner-pe   { background:rgba(220,53,69,0.2);color:#dc3545;border:1px solid rgba(220,53,69,0.4);padding:3px 10px;border-radius:5px;font-weight:800;font-size:11px;display:inline-block; }
    .winner-wait { background:rgba(255,193,7,0.15);color:#ffc107;border:1px solid rgba(255,193,7,0.35);padding:3px 10px;border-radius:5px;font-weight:800;font-size:11px;display:inline-block; }

    /* ── Breakout cell ───────────────────────────────────── */
    .bo-cell { line-height: 1.8; }
    .bo-above { color:#28a745;font-weight:700;font-size:11px; }
    .bo-below { color:#dc3545;font-weight:700;font-size:11px; }
    .bo-fail  { color:#ff4757;font-size:9px;font-weight:700; }

    /* ── Time cell ───────────────────────────────────────── */
    .time-cell { line-height:1.8;font-size:10px; }
    .time-cell .tl { color:rgba(255,255,255,.35); }
    .time-cell .tv { color:#00d2ff;font-weight:700; }

    /* ── Reversal ────────────────────────────────────────── */
    .reversal-yes { background:rgba(168,85,247,0.2);color:#a855f7;border:1px solid rgba(168,85,247,0.4);border-radius:5px;padding:3px 8px;font-size:10px;font-weight:700;display:inline-block; }
    .reversal-no  { color:rgba(255,255,255,.15);font-size:10px; }

    /* ── Gap fail alert row ──────────────────────────────── */
    .gap-fail-row td { background:rgba(255,71,87,0.04) !important; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ──────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
            <div>
                <h4>⚡ Straddle Exit Planner</h4>
                <p>Buy ATM CE + PE at 3 PM → next-day exact exit prices for each leg</p>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('oi-strategy.index') }}" class="btn btn-outline-info btn-sm">OI Strategy</a>
                <a href="{{ route('oiiv-auto.index') }}"  class="btn btn-outline-light btn-sm">OI+IV</a>
            </div>
        </div>
    </div>

    {{-- ── Filters ──────────────────────────────────────────── --}}
    <div class="filter-section">
        <div class="row align-items-end">
            <div class="col-md-3 col-sm-6 mb-2">
                <label>Entry Date <small style="opacity:.5;">(Day you buy CE + PE at 3 PM)</small></label>
                <input type="date" id="entry_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
                <label>Symbols <small style="opacity:.5;">(empty = all)</small></label>
                <select id="symbol_filter" class="form-control" multiple size="3"></select>
            </div>
            <div class="col-md-4 col-sm-12 mb-2">
                <button type="button" id="btn_analyze" class="btn-analyze">
                    <i class="fas fa-rocket"></i> Generate Exit Plan
                </button>
                <button type="button" id="btn_reset" class="btn-reset">Reset</button>
            </div>
        </div>
    </div>

    {{-- ── Date banner ──────────────────────────────────────── --}}
    <div class="date-banner" id="date-banner" style="display:none;">
        <span>📅 Entry: <strong id="banner-entry">—</strong></span>
        <span>📤 Exit Day: <strong id="banner-exit">—</strong></span>
        <span>🎯 <strong id="banner-count">—</strong> symbols</span>
    </div>

    {{-- ── Stats ────────────────────────────────────────────── --}}
    <div class="stats-row">
        <div class="stat-card"><small>Total</small><strong id="stat_total">0</strong></div>
        <div class="stat-card green"><small>Gap Up → CE</small><strong id="stat_gap_up" style="color:#28a745;">0</strong></div>
        <div class="stat-card red"><small>Gap Down → PE</small><strong id="stat_gap_dn" style="color:#dc3545;">0</strong></div>
        <div class="stat-card yellow"><small>Flat → Wait</small><strong id="stat_flat" style="color:#ffc107;">0</strong></div>
        <div class="stat-card purple"><small>Reversal OK</small><strong id="stat_reversal" style="color:#a855f7;">0</strong></div>
    </div>

    {{-- ── Table ────────────────────────────────────────────── --}}
    <div class="card" style="position:relative;min-height:400px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text" id="loading-text">Building exit plans...</div>
        </div>
        <div class="table-responsive p-2">
            <table class="table custom--table mb-0">
                <thead>
                    <tr>
                        {{-- Base --}}
                        <th style="width:30px;">#</th>
                        <th class="th-entry">Symbol</th>
                        <th class="th-entry">Strike</th>

                        {{-- Entry prices --}}
                        <th class="th-ce">CE Entry</th>
                        <th class="th-pe">PE Entry</th>

                        {{-- Gap --}}
                        <th class="th-gap">Gap</th>

                        {{-- CE exit --}}
                        <th class="th-ce">CE Target</th>
                        <th class="th-ce">CE SL</th>

                        {{-- PE exit --}}
                        <th class="th-pe">PE Target</th>
                        <th class="th-pe">PE SL</th>

                        {{-- Decision --}}
                        <th class="th-plan">Winner</th>
                        <th class="th-plan">Breakout / Fail</th>
                        <th class="th-plan">Exit Times</th>
                        <th class="th-plan">Reversal</th>
                    </tr>
                </thead>
                <tbody id="result-tbody">
                    <tr>
                        <td colspan="14" class="text-center py-5" style="color:rgba(255,255,255,.3);">
                            <i class="fas fa-rocket" style="font-size:2.5rem;opacity:.2;"></i>
                            <p style="margin-top:12px;">Select date → <strong style="color:#00d2ff;">Generate Exit Plan</strong></p>
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
let planData = [];

/* ── Helpers ──────────────────────────────────────────────── */
function px(v)  {
    const n = parseFloat(v) || 0;
    return n > 0 ? '₹' + n.toFixed(2) : '—';
}
function pct(v) {
    const n = parseFloat(v) || 0;
    if (!n) return '';
    return `<span class="${n>0?'pct-pos':'pct-neg'}">${n>0?'+':''}${n.toFixed(1)}%</span>`;
}
function gapBadge(type, val) {
    const s = parseFloat(val) > 0 ? '+' : '';
    if (type === 'GAP_UP')   return `<span class="gap-up">▲ ${s}${val}%</span>`;
    if (type === 'GAP_DOWN') return `<span class="gap-down">▼ ${val}%</span>`;
    return `<span class="gap-flat">— ${val}%</span>`;
}
function strengthBadge(s) {
    if (s === 'STRONG')   return '<span class="str-strong">STRONG</span>';
    if (s === 'MODERATE') return '<span class="str-moderate">MOD</span>';
    return '<span class="str-weak">WEAK</span>';
}
function winnerBadge(w) {
    if (w === 'CE')   return '<span class="winner-ce">📈 CE WINS</span>';
    if (w === 'PE')   return '<span class="winner-pe">📉 PE WINS</span>';
    return '<span class="winner-wait">⏳ WAIT</span>';
}

function setLoading(show, msg) {
    if (show) { $('#loading-text').text(msg); $('#loading-overlay').show(); }
    else       { $('#loading-overlay').hide(); }
}

$(document).ready(() => {
    $.get('{{ route("straddle-exit.symbols") }}', res => {
        if (res.success)
            $('#symbol_filter').html(res.symbols.map(s => `<option value="${s}">${s}</option>`).join(''));
    });
});

/* ── Analysis ─────────────────────────────────────────────── */
function runAnalysis() {
    const entryDate = $('#entry_date').val();
    const symbols   = $('#symbol_filter').val() || [];
    if (!entryDate) { alert('Please select entry date'); return; }

    setLoading(true, 'Fetching ATM prices & building exit plans...');
    planData = [];

    $.ajax({
        url: '{{ route("straddle-exit.analyze") }}',
        type: 'GET',
        data: { entry_date: entryDate, symbols },
        success(res) {
            setLoading(false);
            if (res.success && res.data?.length) {
                planData = res.data;
                $('#banner-entry').text(res.entry_date);
                $('#banner-exit').text(res.exit_date);
                $('#banner-count').text(res.total_records);
                $('#date-banner').show();
                renderTable();
                updateStats();
            } else {
                $('#date-banner').hide();
                showEmpty(res.message || 'No data found');
                resetStats();
            }
        },
        error() { setLoading(false); showEmpty('Error — please try again.'); resetStats(); }
    });
}

/* ── Render ───────────────────────────────────────────────── */
function renderTable() {
    let html = '';
    planData.forEach((row, i) => {
        const p  = row.exit_plan || {};
        const te = p.time_exit  || {};

        // Gap fail highlight
        const failRow = (p.gap_fail_note && row.gap_type !== 'FLAT') ? 'gap-fail-row' : '';

        // Reversal
        const revHtml = p.reversal_play
            ? `<span class="reversal-yes">🔁 YES</span>`
            : `<span class="reversal-no">—</span>`;

        // Breakout / fail cell
        const failAlert = p.gap_fail_note
            ? `<br><span class="bo-fail">⚠ Fail @ ₹${p.fail_level}</span>`
            : '';
        const boHtml = `<div class="bo-cell">
            <span class="bo-above">▲ ₹${p.breakout_above || '—'}</span><br>
            <span class="bo-below">▼ ₹${p.breakout_below || '—'}</span>
            ${failAlert}
        </div>`;

        // Time exits
        const teHtml = `<div class="time-cell">
            <span class="tl">Loser:</span> <span class="tv">${te.loser_exit_by || '10:30'}</span><br>
            <span class="tl">Trail:</span> <span class="tv">${te.winner_trail_start || '11:00'}</span><br>
            <span class="tl">Hard:</span>  <span class="tv">${te.hard_exit || '14:30'}</span>
        </div>`;

        html += `
        <tr class="${failRow}">
            <td style="color:rgba(255,255,255,.3);">${i+1}</td>
            <td><strong style="color:#00d2ff;font-size:12px;">${row.symbol}</strong></td>
            <td><strong style="color:#ffc107;">₹${row.atm_strike || '—'}</strong></td>

            <td><span class="price-entry">${px(row.ce_entry)}</span></td>
            <td><span class="price-entry">${px(row.pe_entry)}</span></td>

            <td>
                ${gapBadge(row.gap_type, row.gap_pct)}<br>
                ${strengthBadge(row.gap_strength)}
            </td>

            <td>
                <span class="price-target">${px(p.ce_target)}</span><br>
                ${pct(p.ce_target_pct)}
            </td>
            <td>
                <span class="price-sl">${px(p.ce_sl)}</span><br>
                ${pct(p.ce_sl_pct)}
            </td>

            <td>
                <span class="price-target">${px(p.pe_target)}</span><br>
                ${pct(p.pe_target_pct)}
            </td>
            <td>
                <span class="price-sl">${px(p.pe_sl)}</span><br>
                ${pct(p.pe_sl_pct)}
            </td>

            <td>${winnerBadge(p.winner)}</td>
            <td>${boHtml}</td>
            <td>${teHtml}</td>
            <td>${revHtml}</td>
        </tr>`;
    });
    $('#result-tbody').html(html);
}

/* ── Stats ───────────────────────────────────────────────── */
function updateStats() {
    $('#stat_total').text(planData.length);
    $('#stat_gap_up').text(planData.filter(r => r.gap_type === 'GAP_UP').length);
    $('#stat_gap_dn').text(planData.filter(r => r.gap_type === 'GAP_DOWN').length);
    $('#stat_flat').text(planData.filter(r => r.gap_type === 'FLAT').length);
    $('#stat_reversal').text(planData.filter(r => r.exit_plan?.reversal_play).length);
}
function resetStats() {
    $('#stat_total,#stat_gap_up,#stat_gap_dn,#stat_flat,#stat_reversal').text('0');
}
function showEmpty(msg) {
    $('#result-tbody').html(`<tr><td colspan="14" class="text-center py-5" style="color:rgba(255,255,255,.3);">
        <i class="fas fa-info-circle" style="font-size:2rem;opacity:.3;"></i>
        <p style="margin-top:12px;">${msg}</p>
    </td></tr>`);
}

$('#btn_analyze').click(() => runAnalysis());
$('#btn_reset').click(() => {
    $('#entry_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter').val([]);
    planData = [];
    $('#date-banner').hide();
    showEmpty('Select date → Generate Exit Plan');
    resetStats();
});
</script>
@endpush