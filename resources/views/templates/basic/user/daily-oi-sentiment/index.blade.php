@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ── Page header ──────────────────────────────────────── */
.page-header {
    background: linear-gradient(135deg,#667eea,#764ba2);
    color: white; padding: 20px; border-radius: 12px;
    margin-bottom: 20px; box-shadow: 0 4px 15px rgba(102,126,234,0.4);
}
.page-header h4 { color: white; margin: 0; }

/* ── Filter section ───────────────────────────────────── */
.filter-section {
    background: linear-gradient(135deg,#667eea,#764ba2);
    padding: 20px; border-radius: 12px; margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(102,126,234,0.4); color: white;
}
.filter-section label { color: white !important; font-weight: 600; margin-bottom: 6px; font-size: 13px; }
.filter-section .form-control {
    border: 2px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.9);
    color: #333; font-size: 12px; padding: 6px 10px;
}

/* ── Stats boxes ──────────────────────────────────────── */
.stats-box {
    background: #fff; padding: 12px; border-radius: 10px; text-align: center;
    border-left: 4px solid #3498db; margin-bottom: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,.1); transition: transform .2s;
}
.stats-box:hover { transform: translateY(-2px); }
.stats-box small  { display: block; color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
.stats-box strong { display: block; font-size: 1.4rem; font-weight: 700; margin-top: 3px; }

/* ── Loading overlay ──────────────────────────────────── */
.loading-overlay {
    position: absolute; top:0; left:0; right:0; bottom:0;
    background: rgba(19,45,57,0.95);
    display: flex; flex-direction: column;
    justify-content: center; align-items: center;
    z-index: 1000; border-radius: 12px;
}
.spinner { width:50px; height:50px; border:5px solid #f3f3f3; border-top:5px solid #3498db; border-radius:50%; animation:spin 1s linear infinite; }
.loading-text { color:white; margin-top:20px; font-size:16px; font-weight:600; }
@keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

/* ── Table ────────────────────────────────────────────── */
.custom--table thead th,
.custom--table tbody td {
    text-align: center !important;
    padding: 8px 6px !important;
    font-size: 11px !important;
    vertical-align: middle;
}
.custom--table thead th{
    color:#fff !important;
}
.custom--table { min-width: 1300px; }
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* Sticky first 3 cols */
.custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
.custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
.custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position: sticky; z-index: 10; }
.custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left: 0; }
.custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left: 40px; }
.custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left: 110px; }

/* ── Group header bands ───────────────────────────────── */
.th-raw    { background: rgba(0,210,255,0.12)  !important; color: #00d2ff !important; border-left: 3px solid rgba(0,210,255,0.5) !important; }
.th-orig   { background: rgba(40,167,69,0.12)  !important; color: #28a745 !important; border-left: 3px solid rgba(40,167,69,0.5) !important; }
.th-pivot  { background: rgba(162,155,254,0.14)!important; color: #a29bfe !important; border-left: 3px solid rgba(162,155,254,0.5) !important; }
.th-new    { background: rgba(249,202,36,0.12) !important; color: #e2b900 !important; border-left: 3px solid rgba(249,202,36,0.5) !important; }
.td-orig   { border-left: 2px solid rgba(40,167,69,0.3) !important; }
.td-pivot  { border-left: 2px solid rgba(162,155,254,0.3) !important; }
.td-new    { border-left: 2px solid rgba(249,202,36,0.3) !important; }

/* ── Signal / condition badges ────────────────────────── */
.sig-bullish { background: linear-gradient(135deg,#28a745,#20c997); color: white; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
.sig-bearish { background: linear-gradient(135deg,#dc3545,#c82333); color: white; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
.sig-neutral { background: linear-gradient(135deg,#6c757d,#5a6268); color: white; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
.sig-na      { color: rgba(255,255,255,.25); font-size: 9px; }

.cond-ce-up-pe-down { background: linear-gradient(135deg,#dc3545,#fd7e14); color: white; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
.cond-ce-down-pe-up { background: linear-gradient(135deg,#28a745,#20c997); color: white; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
.cond-both-up       { background: linear-gradient(135deg,#667eea,#764ba2); color: white; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
.cond-both-down     { background: linear-gradient(135deg,#6c757d,#5a6268); color: white; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }
.cond-flat          { background: #e9ecef; color: #495057; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 9px; display: inline-block; }

/* ── Strength badges ──────────────────────────────────── */
.str-very   { background: rgba(255,71,87,0.22);  color: #ff4757; border: 1px solid rgba(255,71,87,.5);  border-radius: 4px; padding: 2px 7px; font-size: 9px; font-weight: 800; display: inline-block; }
.str-strong { background: rgba(255,165,2,0.2);   color: #ffa502; border: 1px solid rgba(255,165,2,.45); border-radius: 4px; padding: 2px 7px; font-size: 9px; font-weight: 800; display: inline-block; }
.str-mod    { background: rgba(0,210,255,0.14);  color: #00d2ff; border: 1px solid rgba(0,210,255,.35); border-radius: 4px; padding: 2px 7px; font-size: 9px; font-weight: 700; display: inline-block; }
.str-weak   { background: rgba(255,255,255,.05); color: rgba(255,255,255,.4); border: 1px solid rgba(255,255,255,.1); border-radius: 4px; padding: 2px 7px; font-size: 9px; display: inline-block; }

/* ── Misc ─────────────────────────────────────────────── */
.text-success { color: #28a745 !important; }
.text-danger  { color: #dc3545 !important; }
.new-feature-badge { background: linear-gradient(135deg,#f093fb,#f5576c); color: white; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; margin-left: 5px; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>{{ $pageTitle }}
                    <span class="new-feature-badge">3-Signal View</span>
                </h4>
                <p style="color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:12px;">
                    <span style="color:#51cf66;">&#9679; Original</span> &nbsp;&middot;&nbsp;
                    <span style="color:#a29bfe;">&#9679; Pivot Daily (prev vs day-before)</span> &nbsp;&middot;&nbsp;
                    <span style="color:#f9ca24;">&#9679; New Logic + Strength</span>
                </p>
            </div>
            <div>
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm mr-2">
                    <i class="fas fa-chart-bar"></i> PE/CE Analysis
                </a>
                <a href="{{ route('oiiv-auto.index') }}" class="btn btn-light btn-sm">
                    <i class="fas fa-chart-line"></i> OI+IV
                </a>
            </div>
        </div>
    </div>

    {{-- ── Logic Info ── --}}
    <div class="alert" style="background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);border:1px solid rgba(0,210,255,0.3);border-radius:12px;margin-bottom:20px;padding:14px 18px;">
        <div class="row">
            <div class="col-md-4">
                <small style="color:#51cf66;font-weight:700;font-size:11px;">&#10003; Col 1 — Original (unchanged)</small>
                <ul style="font-size:10px;color:rgba(255,255,255,.7);margin-top:4px;">
                    <li>CE ↑ + PE ↓ → BEARISH</li>
                    <li>CE ↓ + PE ↑ → BULLISH</li>
                    <li>Both ↑ → CE%>PE% = BEARISH</li>
                    <li>Both ↓ → CE%&lt;PE% = BULLISH</li>
                </ul>
            </div>
            <div class="col-md-4">
                <small style="color:#a29bfe;font-weight:700;font-size:11px;">&#128197; Col 2 — Pivot Daily</small>
                <ul style="font-size:10px;color:rgba(255,255,255,.7);margin-top:4px;">
                    <li>Source: OptionDailyOhlcData</li>
                    <li>Prev trading day vs day-before-prev</li>
                    <li>Total CE/PE OI sum per symbol</li>
                    <li>Signal: corrected 4-case logic</li>
                </ul>
            </div>
            <div class="col-md-4">
                <small style="color:#f9ca24;font-weight:700;font-size:11px;">&#9889; Col 3 — New Logic + Strength</small>
                <ul style="font-size:10px;color:rgba(255,255,255,.7);margin-top:4px;">
                    <li>Same CE%/PE% as Col 1</li>
                    <li>CE ↑ + PE ↓ → BEARISH &nbsp; CE ↓ + PE ↑ → BULLISH</li>
                    <li>Both ↑ → PE%>CE% = BULLISH</li>
                    <li>Both ↓ → |CE%|>|PE%| = BULLISH</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── Filters ── --}}
    <div class="filter-section">
        <div class="row mb-2">
            <div class="col-md-3">
                <label><i class="fas fa-calendar-alt"></i> From Date:</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-calendar-alt"></i> To Date:</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-filter"></i> Symbols <small style="opacity:.7;">(optional)</small></label>
                <select id="symbol_filter" class="form-control" multiple size="2"></select>
                <small style="color:rgba(255,255,255,0.7);font-size:10px;">Leave empty for all</small>
            </div>
            <div class="col-md-3 d-flex align-items-end pb-1">
                <div class="w-100 text-center">
                    <button type="button" id="btn_run" class="btn btn-light btn-lg" style="min-width:140px;">
                        <i class="fas fa-search"></i> View Data
                    </button>
                    <button type="button" id="btn_reset" class="btn btn-outline-light btn-lg ml-2" style="min-width:110px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Stats ── --}}
    <div class="row mb-3">
        <div class="col-6 col-md-2">
            <div class="stats-box">
                <small>Total Records</small>
                <strong id="st-total" class="text-dark">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#28a745;">
                <small>&#10003; Orig Bullish</small>
                <strong id="st-orig-bull" style="color:#28a745;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#dc3545;">
                <small>&#10003; Orig Bearish</small>
                <strong id="st-orig-bear" style="color:#dc3545;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#a29bfe;">
                <small>&#128197; Pivot Bullish</small>
                <strong id="st-piv-bull" style="color:#a29bfe;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#f9ca24;">
                <small>&#9889; New Bullish</small>
                <strong id="st-new-bull" style="color:#e2b900;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#fd7e14;">
                <small>&#9889; New Bearish</small>
                <strong id="st-new-bear" style="color:#fd7e14;">0</strong>
            </div>
        </div>
    </div>

    {{-- ── Table ── --}}
    <div style="position:relative;min-height:400px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Loading data...</div>
        </div>
        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        {{-- Meta (3 sticky) --}}
                        <th rowspan="2">#</th>
                        <th rowspan="2">Date</th>
                        <th rowspan="2">Symbol</th>

                        {{-- Raw OI data --}}
                        <th colspan="4" class="th-raw">&#128202; OI Data (14:45 vs prev 15:15)</th>

                        {{-- Col 1 — Original --}}
                        <th colspan="2" class="th-orig">&#10003; Original Signal<br><small style="font-weight:400;opacity:.75;">OIIVAutoController — unchanged</small></th>

                        {{-- Col 2 — Pivot Daily --}}
                        <th colspan="3" class="th-pivot">&#128197; Pivot Daily<br><small style="font-weight:400;opacity:.75;">OptionDailyOhlcData · prev vs day-before</small></th>

                        {{-- Col 3 — New Logic --}}
                        <th colspan="3" class="th-new">&#9889; New Logic + Strength<br><small style="font-weight:400;opacity:.75;">Corrected 4-case on same CE/PE%</small></th>
                    </tr>
                    <tr>
                        {{-- Raw --}}
                        <th class="th-raw">CE%</th>
                        <th class="th-raw">PE%</th>
                        <th class="th-raw">CE OI</th>
                        <th class="th-raw">PE OI</th>

                        {{-- Col 1 --}}
                        <th class="th-orig td-orig">Signal</th>
                        <th class="th-orig">Condition</th>

                        {{-- Col 2 --}}
                        <th class="th-pivot td-pivot">Signal</th>
                        <th class="th-pivot">CE%</th>
                        <th class="th-pivot">PE%</th>

                        {{-- Col 3 --}}
                        <th class="th-new td-new">Signal</th>
                        <th class="th-new">Condition</th>
                        <th class="th-new">Strength</th>
                    </tr>
                </thead>
                <tbody id="sig-tbody">
                    <tr>
                        <td colspan="16" class="text-center py-5">
                            <i class="fas fa-chart-pie" style="font-size:3rem;opacity:.4;"></i>
                            <p style="margin-top:18px;">Click <strong>"View Data"</strong> to load signals</p>
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
let tableData = [];

/* ── Init ──────────────────────────────────────────────── */
$(document).ready(function () {
    loadSymbols();
    setTimeout(() => runAnalysis(), 400);
});

/* ── Symbols ────────────────────────────────────────────── */
function loadSymbols() {
    $.get('{{ route("daily-oi-sentiment.symbols") }}', function (res) {
        if (!res.success) return;
        let html = '';
        res.symbols.forEach(s => { html += `<option value="${s}">${s}</option>`; });
        $('#symbol_filter').html(html);
    });
}

/* ── Main load ──────────────────────────────────────────── */
function runAnalysis() {
    const from    = $('#from_date').val();
    const to      = $('#to_date').val();
    const symbols = $('#symbol_filter').val() || [];

    if (!from || !to) { alert('Please select both dates'); return; }

    toggleLoading(true);
    tableData = [];

    $.ajax({
        url : '{{ route("daily-oi-sentiment.analyze") }}',
        type: 'GET',
        data: { from_date: from, to_date: to, symbols: symbols },
        success(res) {
            toggleLoading(false);
            if (res.success && res.data && res.data.length > 0) {
                tableData = res.data;
                renderTable();
                renderStats();
            } else {
                showNoData(res.message || 'No data found for selected range.');
                resetStats();
            }
        },
        error(xhr) {
            toggleLoading(false);
            const msg = xhr.responseJSON?.message || 'Server error';
            showNoData('&#9888; ' + msg);
            resetStats();
        }
    });
}

/* ── Loading ─────────────────────────────────────────────── */
function toggleLoading(show) {
    if (show) $('#loading-overlay').show();
    else      $('#loading-overlay').hide();
}

/* ── Signal badge ─────────────────────────────────────────── */
function sigBadge(signal) {
    if (!signal || signal === 'N/A') return '<span class="sig-na">&mdash;</span>';
    if (signal === 'BULLISH') return '<span class="sig-bullish">&#129033; BULL</span>';
    if (signal === 'BEARISH') return '<span class="sig-bearish">&#129035; BEAR</span>';
    return '<span class="sig-neutral">&#9679; NEUTRAL</span>';
}

/* ── Condition badge ──────────────────────────────────────── */
function condBadge(cond) {
    if (!cond || cond === 'N/A' || cond === 'Flat')
        return '<span class="cond-flat">' + (cond || 'N/A') + '</span>';
    if (cond.includes('CE ↑ + PE ↓')) return `<span class="cond-ce-up-pe-down">${cond}</span>`;
    if (cond.includes('CE ↓ + PE ↑')) return `<span class="cond-ce-down-pe-up">${cond}</span>`;
    if (cond.includes('Both ↑'))      return `<span class="cond-both-up">${cond}</span>`;
    if (cond.includes('Both ↓'))      return `<span class="cond-both-down">${cond}</span>`;
    return `<span class="cond-flat">${cond}</span>`;
}

/* ── Strength badge ───────────────────────────────────────── */
function strBadge(strength, diff) {
    if (!strength || strength === 'N/A') return '<span class="sig-na">&mdash;</span>';
    let cls = 'str-weak', icon = '';
    if      (strength === 'Very Strong') { cls = 'str-very';   icon = '&#128293; '; }
    else if (strength === 'Strong')      { cls = 'str-strong'; icon = '&#9889; '; }
    else if (strength === 'Moderate')    { cls = 'str-mod';    icon = '&#9733; '; }
    const diffTxt = diff != null
        ? `<br><span style="font-size:8px;color:rgba(255,255,255,.4);">&Delta;${Number(diff).toFixed(2)}%</span>`
        : '';
    return `<span class="${cls}">${icon}${strength}</span>${diffTxt}`;
}

/* ── % cell ───────────────────────────────────────────────── */
function pctCell(v) {
    if (v == null) return '<span style="color:rgba(255,255,255,.2);">—</span>';
    const n   = Number(v);
    const cls = n > 0 ? 'text-success' : n < 0 ? 'text-danger' : '';
    const pfx = n > 0 ? '+' : '';
    return `<strong class="${cls}">${pfx}${n.toFixed(2)}%</strong>`;
}

/* ── OI number ────────────────────────────────────────────── */
function fmtOI(v) {
    const n = Number(v) || 0;
    if (n >= 1e6) return (n / 1e6).toFixed(2) + 'M';
    if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
    return n.toString();
}

/* ── Render table ─────────────────────────────────────────── */
function renderTable() {
    if (!tableData.length) return;
    let html = '';

    tableData.forEach(function (d, i) {
        const cePctCls = d.ce_oi_pct > 0 ? 'text-success' : d.ce_oi_pct < 0 ? 'text-danger' : '';
        const pePctCls = d.pe_oi_pct > 0 ? 'text-success' : d.pe_oi_pct < 0 ? 'text-danger' : '';

        html += `
        <tr>
            <td><strong>${i + 1}</strong></td>
            <td><strong>${d.date}</strong></td>
            <td><strong style="color:#667eea;">${d.symbol}</strong></td>

            {{-- Raw OI --}}
            <td class="${cePctCls}"><strong>${(d.ce_oi_pct > 0 ? '+' : '') + Number(d.ce_oi_pct).toFixed(2)}%</strong></td>
            <td class="${pePctCls}"><strong>${(d.pe_oi_pct > 0 ? '+' : '') + Number(d.pe_oi_pct).toFixed(2)}%</strong></td>
            <td style="font-size:10px;color:rgba(255,255,255,.6);">${fmtOI(d.ce_oi)}</td>
            <td style="font-size:10px;color:rgba(255,255,255,.6);">${fmtOI(d.pe_oi)}</td>

            {{-- Col 1 — Original --}}
            <td class="td-orig" title="${d.orig_reason||''}">${sigBadge(d.orig_signal)}</td>
            <td>${condBadge(d.orig_condition)}</td>

            {{-- Col 2 — Pivot Daily --}}
            <td class="td-pivot" title="${d.pivot_reason||''}">${sigBadge(d.pivot_signal)}</td>
            <td>${pctCell(d.pivot_ce_pct)}</td>
            <td>${pctCell(d.pivot_pe_pct)}</td>

            {{-- Col 3 — New Logic --}}
            <td class="td-new" title="${d.new_reason||''}">${sigBadge(d.new_signal)}</td>
            <td>${condBadge(d.new_condition)}</td>
            <td>${strBadge(d.new_strength, d.new_difference)}</td>
        </tr>`;
    });

    $('#sig-tbody').html(html);
}

/* ── Stats ────────────────────────────────────────────────── */
function renderStats() {
    $('#st-total').text(tableData.length);
    $('#st-orig-bull').text(tableData.filter(d => d.orig_signal  === 'BULLISH').length);
    $('#st-orig-bear').text(tableData.filter(d => d.orig_signal  === 'BEARISH').length);
    $('#st-piv-bull').text( tableData.filter(d => d.pivot_signal === 'BULLISH').length);
    $('#st-new-bull').text( tableData.filter(d => d.new_signal   === 'BULLISH').length);
    $('#st-new-bear').text( tableData.filter(d => d.new_signal   === 'BEARISH').length);
}

function resetStats() {
    $('#st-total,#st-orig-bull,#st-orig-bear,#st-piv-bull,#st-new-bull,#st-new-bear').text('0');
}

function showNoData(msg) {
    $('#sig-tbody').html(`
        <tr><td colspan="16" class="text-center py-5">
            <i class="fas fa-info-circle" style="color:#17a2b8;font-size:3rem;"></i>
            <p class="text-info" style="margin-top:20px;">${msg}</p>
        </td></tr>`);
}

/* ── Buttons ──────────────────────────────────────────────── */
$('#btn_run').click(() => runAnalysis());
$('#btn_reset').click(function () {
    $('#from_date,#to_date').val('{{ date("Y-m-d") }}');
    $('#symbol_filter').val('');
    tableData = [];
    showNoData('Click "View Data" to load signals');
    resetStats();
    setTimeout(() => runAnalysis(), 300);
});
</script>
@endpush