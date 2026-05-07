@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    :root {
        --bg-deep:    #0a0f1a;
        --bg-card:    #0f1729;
        --bg-row-odd: #111827;
        --bg-row-eve: #0f1523;
        --border:     rgba(56,189,248,0.12);
        --accent:     #38bdf8;
        --accent2:    #818cf8;
        --green:      #34d399;
        --red:        #f87171;
        --orange:     #fb923c;
        --yellow:     #fbbf24;
        --muted:      rgba(148,163,184,0.6);
        --text:       #e2e8f0;
        --text-dim:   #94a3b8;
    }

    body { background: var(--bg-deep); }

    /* ── Page shell ─────────────────────────────── */
    .viewer-header {
        background: linear-gradient(135deg, #0f1729 0%, #1a1f35 100%);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 24px 28px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }
    .viewer-header::before {
        content: '';
        position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, var(--accent), var(--accent2), var(--green));
    }
    .viewer-header h4 { color: var(--accent); font-size: 18px; font-weight: 700; margin: 0 0 4px; letter-spacing: .3px; }
    .viewer-header p  { color: var(--text-dim); font-size: 11px; margin: 0; }

    /* ── Filter panel ───────────────────────────── */
    .filter-panel {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 20px;
    }
    .filter-panel label { color: var(--text-dim); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 6px; }
    .filter-panel .form-control {
        background: rgba(15,23,42,0.8);
        border: 1px solid var(--border);
        color: var(--text);
        border-radius: 8px;
        font-size: 12px;
        padding: 8px 12px;
    }
    .filter-panel .form-control:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 2px rgba(56,189,248,0.15); }
    .filter-panel select option { background: #1a1f35; color: var(--text); }

    .btn-fetch {
        background: linear-gradient(135deg, var(--accent), #0ea5e9);
        color: #fff; border: none; border-radius: 8px;
        padding: 10px 28px; font-size: 13px; font-weight: 700;
        cursor: pointer; transition: all .2s;
        letter-spacing: .3px;
    }
    .btn-fetch:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(56,189,248,0.35); }
    .btn-reset {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text-dim); border-radius: 8px;
        padding: 10px 20px; font-size: 13px; font-weight: 600;
        cursor: pointer; transition: all .2s;
    }
    .btn-reset:hover { border-color: var(--accent); color: var(--accent); }

    /* ── Tabs ───────────────────────────────────── */
    .tab-bar { display: flex; gap: 4px; margin-bottom: 16px; }
    .tab-btn {
        background: var(--bg-card);
        border: 1px solid var(--border);
        color: var(--text-dim); border-radius: 8px;
        padding: 8px 18px; font-size: 12px; font-weight: 600;
        cursor: pointer; transition: all .2s;
    }
    .tab-btn.active {
        background: rgba(56,189,248,0.12);
        border-color: var(--accent);
        color: var(--accent);
    }

    /* ── Summary cards ──────────────────────────── */
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 20px; }
    .s-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 12px 14px;
        text-align: center;
    }
    .s-card small { display: block; color: var(--text-dim); font-size: 9px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
    .s-card strong { display: block; font-size: 1.3rem; font-weight: 700; color: var(--text); }
    .s-card.warn strong { color: var(--orange); }
    .s-card.danger strong { color: var(--red); }
    .s-card.good strong { color: var(--green); }
    .s-card.accent strong { color: var(--accent); }

    /* ── ATM by date strip ──────────────────────── */
    .atm-strip {
        display: flex; flex-wrap: wrap; gap: 8px;
        margin-bottom: 16px;
    }
    .atm-chip {
        background: rgba(56,189,248,0.08);
        border: 1px solid rgba(56,189,248,0.2);
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 10px;
        color: var(--text-dim);
    }
    .atm-chip strong { color: var(--accent); font-size: 12px; }
    .atm-chip span   { color: var(--text-dim); }

    /* ── Table ──────────────────────────────────── */
    .table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); }
    .data-table { width: 100%; border-collapse: collapse; font-size: 11px; min-width: 1100px; }
    .data-table thead th {
        background: rgba(56,189,248,0.08);
        color: var(--accent);
        padding: 10px 8px;
        text-align: center;
        font-weight: 700;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .4px;
        border-bottom: 1px solid var(--border);
        position: sticky; top: 0; z-index: 10;
        white-space: nowrap;
    }
    .data-table thead th:nth-child(1),
    .data-table thead th:nth-child(2),
    .data-table thead th:nth-child(3) { text-align: left; }

    .data-table tbody td {
        padding: 7px 8px;
        text-align: center;
        color: var(--text);
        border-bottom: 1px solid rgba(56,189,248,0.05);
        white-space: nowrap;
    }
    .data-table tbody td:nth-child(1),
    .data-table tbody td:nth-child(2),
    .data-table tbody td:nth-child(3) { text-align: left; }

    .data-table tbody tr:hover td { background: rgba(56,189,248,0.04); }
    .data-table tbody tr.row-fut td { background: rgba(129,140,248,0.04); }
    .data-table tbody tr.row-ce  td { background: rgba(52,211,153,0.03); }
    .data-table tbody tr.row-pe  td { background: rgba(248,113,113,0.03); }
    .data-table tbody tr.row-missing td { opacity: .5; }

    /* ── Badges ─────────────────────────────────── */
    .badge-fut  { background:rgba(129,140,248,.15); color:#818cf8; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
    .badge-ce   { background:rgba(52,211,153,.15);  color:#34d399; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
    .badge-pe   { background:rgba(248,113,113,.15); color:#f87171; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
    .badge-atm  { background:rgba(251,191,36,.15);  color:#fbbf24; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
    .badge-atm1 { background:rgba(56,189,248,.15);  color:#38bdf8; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
    .badge-miss { background:rgba(239,68,68,.15);   color:#ef4444; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
    .badge-null { background:rgba(239,68,68,.2);    color:#f87171; padding:2px 6px; border-radius:4px; font-size:9px; font-weight:700; }

    .pos-up   { color: var(--green); font-weight: 700; }
    .pos-down { color: var(--red);   font-weight: 700; }

    /* ── Loading ─────────────────────────────────── */
    .loading-overlay {
        position: absolute; inset: 0;
        background: rgba(10,15,26,0.92);
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        z-index: 100; border-radius: 12px;
    }
    .spinner-ring {
        width: 44px; height: 44px;
        border: 4px solid rgba(56,189,248,0.15);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    .loading-txt { color: var(--accent); margin-top: 14px; font-size: 13px; font-weight: 600; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── OI comparison table ─────────────────────── */
    .oi-table { width: 100%; border-collapse: collapse; font-size: 11px; min-width: 700px; }
    .oi-table th {
        background: rgba(56,189,248,0.08);
        color: var(--accent); padding: 9px 8px;
        text-align: center; font-size: 10px;
        text-transform: uppercase; letter-spacing: .4px;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }
    .oi-table td { padding: 7px 8px; text-align: center; color: var(--text); border-bottom: 1px solid rgba(56,189,248,0.05); }
    .oi-table tr:hover td { background: rgba(56,189,248,0.04); }

    /* ── Empty / no-data ─────────────────────────── */
    .empty-state { text-align: center; padding: 60px 20px; color: var(--text-dim); }
    .empty-state i { font-size: 3rem; opacity: .3; display: block; margin-bottom: 16px; }

    /* ── Nav buttons ─────────────────────────────── */
    .nav-btn { background:rgba(255,255,255,0.06); border:1px solid var(--border); color:var(--text-dim); border-radius:7px; padding:6px 14px; font-size:11px; text-decoration:none; transition:all .2s; display:inline-block; }
    .nav-btn:hover { border-color:var(--accent); color:var(--accent); text-decoration:none; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="viewer-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px;">
            <div>
                <h4><i class="fas fa-database" style="margin-right:8px;"></i>{{ $pageTitle }}</h4>
                <p>Inspect raw OHLC, OI, strikes &amp; ATM stored in DB for any symbol &amp; date range</p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="nav-btn"><i class="fas fa-chart-bar"></i> EOD PE/CE</a>
                <a href="{{ route('strike-analysis.index') }}"  class="nav-btn"><i class="fas fa-layer-group"></i> Strike Analysis</a>
                <a href="{{ route('oiiv-auto.index') }}"        class="nav-btn"><i class="fas fa-chart-line"></i> OI+IV</a>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-panel">
        <div class="row mb-3">
            <div class="col-md-3">
                <label><i class="fas fa-tag"></i> Symbol</label>
                <select id="sym" class="form-control">
                    <option value="">— Select Symbol —</option>
                </select>
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-calendar"></i> From Date</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-calendar"></i> To Date</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-filter"></i> Instrument Type</label>
                <select id="instr_type" class="form-control">
                    <option value="ALL">All Types</option>
                    <option value="FUT">FUT Only</option>
                    <option value="CE">CE Only</option>
                    <option value="PE">PE Only</option>
                </select>
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-clock"></i> Time Slot</label>
                <select id="time_slot" class="form-control">
                    <option value="ALL">All Times</option>
                    <option value="09:15">09:15</option>
                    <option value="09:30">09:30</option>
                    <option value="12:00">12:00</option>
                    <option value="14:45">14:45</option>
                    <option value="15:00">15:00</option>
                    <option value="15:15">15:15</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-12 text-center" style="display:flex; gap:10px; justify-content:center;">
                <button class="btn-fetch" id="btn_fetch"><i class="fas fa-search"></i> Fetch Data</button>
                <button class="btn-fetch" id="btn_oi_compare" style="background:linear-gradient(135deg,#818cf8,#6366f1);"><i class="fas fa-balance-scale"></i> OI Compare (15:00 vs 15:15)</button>
                <button class="btn-reset" id="btn_reset"><i class="fas fa-undo"></i> Reset</button>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div id="summary_wrap" style="display:none;">
        <div class="summary-grid">
            <div class="s-card accent"><small>Total Rows</small><strong id="s_total">0</strong></div>
            <div class="s-card accent"><small>Dates</small><strong id="s_dates">0</strong></div>
            <div class="s-card"><small>Unique Strikes</small><strong id="s_strikes" style="color:var(--text);">0</strong></div>
            <div class="s-card warn"><small>NULL OI Rows</small><strong id="s_null_oi">0</strong></div>
            <div class="s-card warn"><small>Zero OI Rows</small><strong id="s_zero_oi">0</strong></div>
            <div class="s-card danger"><small>Missing Rows</small><strong id="s_missing">0</strong></div>
        </div>

        {{-- ATM by date --}}
        <div style="margin-bottom:16px;">
            <div style="color:var(--text-dim); font-size:10px; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px;">
                <i class="fas fa-crosshairs" style="color:var(--yellow);"></i> &nbsp;ATM Strike frozen per date (from 09:15 open)
            </div>
            <div class="atm-strip" id="atm_strip"></div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="tab-bar" id="tab_bar" style="display:none;">
        <button class="tab-btn active" data-tab="raw">📋 Raw OHLC</button>
        <button class="tab-btn" data-tab="oi">📊 OI Compare</button>
    </div>

    {{-- Main content area --}}
    <div style="position:relative; min-height:300px;">
        <div class="loading-overlay" id="loading" style="display:none;">
            <div class="spinner-ring"></div>
            <div class="loading-txt" id="loading_txt">Fetching data...</div>
        </div>

        {{-- RAW TAB --}}
        <div id="tab_raw">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Trading Symbol</th>
                            <th>Strike</th>
                            <th>ATM Strike</th>
                            <th>Position</th>
                            <th>Expiry</th>
                            <th>Fut Price</th>
                            <th>Open</th>
                            <th>High</th>
                            <th>Low</th>
                            <th>Close</th>
                            <th>Volume</th>
                            <th>OI</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_raw">
                        <tr><td colspan="17"><div class="empty-state"><i class="fas fa-database"></i>Select a symbol and click Fetch Data</div></td></tr>
                    </tbody>
                </table>
            </div>
            <div id="pagination_raw" style="text-align:center; margin-top:12px; color:var(--text-dim); font-size:11px;"></div>
        </div>

        {{-- OI COMPARE TAB --}}
        <div id="tab_oi" style="display:none;">
            <div style="color:var(--text-dim); font-size:11px; margin-bottom:12px; padding:10px 14px; background:rgba(56,189,248,0.06); border:1px solid var(--border); border-radius:8px;">
                <i class="fas fa-info-circle" style="color:var(--accent);"></i>
                &nbsp;Showing CE &amp; PE OI at <strong style="color:var(--accent);">15:00</strong> (today's signal candle) vs <strong style="color:var(--accent);">15:15</strong> (settled OI) for each date.
                NULL = data not collected for that slot. Use this to verify your collection pipeline.
            </div>
            <div class="table-wrap">
                <table class="oi-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Strike</th>
                            <th>ATM Strike</th>
                            <th>Position</th>
                            <th>OI @ 15:00</th>
                            <th>OI @ 15:15</th>
                            <th>OI Change</th>
                            <th>Close @ 15:00</th>
                            <th>Close @ 15:15</th>
                            <th>Missing?</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_oi">
                        <tr><td colspan="11"><div class="empty-state"><i class="fas fa-balance-scale"></i>Click "OI Compare" to load comparison</div></td></tr>
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
var allData    = [];
var currentPage = 1;
var pageSize    = 100;
var activeTab   = 'raw';

// ── Init ──────────────────────────────────────────────────────────────────
$(document).ready(function() {
    loadSymbols();
});

function loadSymbols() {
    $.get('{{ route("ohlc-viewer.symbols") }}', function(res) {
        if (!res.success) return;
        var opts = '<option value="">— Select Symbol —</option>';
        res.symbols.forEach(function(s) { opts += '<option value="' + s + '">' + s + '</option>'; });
        $('#sym').html(opts);
    });
}

// ── Tabs ──────────────────────────────────────────────────────────────────
$('.tab-btn').click(function() {
    $('.tab-btn').removeClass('active');
    $(this).addClass('active');
    activeTab = $(this).data('tab');
    $('#tab_raw, #tab_oi').hide();
    $('#tab_' + activeTab).show();
});

// ── Fetch raw data ─────────────────────────────────────────────────────────
$('#btn_fetch').click(function() { fetchData(); });

function fetchData() {
    var sym  = $('#sym').val();
    var from = $('#from_date').val();
    var to   = $('#to_date').val();
    if (!sym) { alert('Please select a symbol'); return; }
    if (!from || !to) { alert('Please select dates'); return; }

    showLoading('Fetching OHLC data...');
    allData = [];
    currentPage = 1;

    $.ajax({
        url: '{{ route("ohlc-viewer.data") }}', type: 'GET',
        data: {
            symbol: sym, from_date: from, to_date: to,
            instrument_type: $('#instr_type').val(),
            time_slot: $('#time_slot').val()
        },
        success: function(res) {
            hideLoading();
            if (!res.success) { showEmpty('tbody_raw', 17, res.message); hideSummary(); return; }
            allData = res.data;
            renderSummary(res.summary);
            renderTable();
            $('#tab_bar').show();
            // Switch to raw tab
            $('.tab-btn[data-tab="raw"]').click();
        },
        error: function() { hideLoading(); showEmpty('tbody_raw', 17, 'Server error'); }
    });
}

// ── Fetch OI compare ───────────────────────────────────────────────────────
$('#btn_oi_compare').click(function() {
    var sym  = $('#sym').val();
    var from = $('#from_date').val();
    var to   = $('#to_date').val();
    if (!sym) { alert('Please select a symbol'); return; }
    if (!from || !to) { alert('Please select dates'); return; }

    showLoading('Loading OI comparison...');

    $.ajax({
        url: '{{ route("ohlc-viewer.oi-compare") }}', type: 'GET',
        data: { symbol: sym, from_date: from, to_date: to },
        success: function(res) {
            hideLoading();
            if (!res.success || !res.data.length) {
                showEmpty('tbody_oi', 11, res.message || 'No data');
                $('#tab_bar').show();
                $('.tab-btn[data-tab="oi"]').click();
                return;
            }
            renderOiCompare(res.data);
            $('#tab_bar').show();
            $('.tab-btn[data-tab="oi"]').click();
        },
        error: function() { hideLoading(); showEmpty('tbody_oi', 11, 'Server error'); }
    });
});

// ── Render summary ─────────────────────────────────────────────────────────
function renderSummary(s) {
    $('#s_total').text(Number(s.null_oi + (allData.length)).toLocaleString());
    // re-derive from allData length
    $('#s_total').text(allData.length.toLocaleString());
    $('#s_dates').text(s.total_dates);
    $('#s_strikes').text(s.strikes.length);
    $('#s_null_oi').text(s.null_oi);
    $('#s_zero_oi').text(s.zero_oi);
    $('#s_missing').text(s.missing_rows);

    // ATM strip
    var strip = '';
    $.each(s.atm_by_date, function(date, info) {
        strip += '<div class="atm-chip">' +
            '<strong>' + date + '</strong> &nbsp;' +
            '<span>ATM: </span><strong style="color:var(--yellow);">' + (info.atm_strike || 'N/A') + '</strong>' +
            '&nbsp;<span style="font-size:9px; color:var(--muted);">FUT: ' + parseFloat(info.close || 0).toFixed(2) + '</span>' +
            '</div>';
    });
    $('#atm_strip').html(strip || '<span style="color:var(--text-dim); font-size:11px;">No FUT rows in selection</span>');
    $('#summary_wrap').show();
}

function hideSummary() { $('#summary_wrap').hide(); }

// ── Render raw table ───────────────────────────────────────────────────────
function renderTable() {
    if (!allData.length) { showEmpty('tbody_raw', 17, 'No records'); return; }

    var start = (currentPage - 1) * pageSize;
    var slice = allData.slice(start, start + pageSize);
    var html  = '';

    slice.forEach(function(row, i) {
        var globalIdx = start + i + 1;
        var typeBadge = row.instrument_type === 'FUT'
            ? '<span class="badge-fut">FUT</span>'
            : row.instrument_type === 'CE'
                ? '<span class="badge-ce">CE</span>'
                : '<span class="badge-pe">PE</span>';

        var posBadge = '';
        if (row.strike_position === 'ATM')      posBadge = '<span class="badge-atm">ATM</span>';
        else if (row.strike_position === 'ATM+1' || row.strike_position === 'ATM-1')
                                                 posBadge = '<span class="badge-atm1">' + row.strike_position + '</span>';
        else if (row.strike_position === 'N/A')  posBadge = '<span style="color:var(--muted);font-size:10px;">N/A</span>';
        else                                     posBadge = '<span style="color:var(--text-dim);font-size:10px;">' + (row.strike_position||'—') + '</span>';

        var oiHtml = row.oi === null
            ? '<span class="badge-null">NULL</span>'
            : row.oi == 0
                ? '<span style="color:var(--orange);font-weight:700;">0</span>'
                : '<strong style="color:var(--text);">' + Number(row.oi).toLocaleString() + '</strong>';

        var missHtml = row.is_missing == 1
            ? '<span class="badge-miss">MISSING</span>'
            : '<span style="color:var(--green);font-size:10px;">✓ OK</span>';

        var rowClass = row.instrument_type === 'FUT' ? 'row-fut'
                     : row.instrument_type === 'CE'  ? 'row-ce' : 'row-pe';
        if (row.is_missing == 1) rowClass += ' row-missing';

        html += '<tr class="' + rowClass + '">'
            + '<td style="color:var(--muted);">' + globalIdx + '</td>'
            + '<td><strong style="color:var(--text);">' + fmtDate(row.trade_date) + '</strong></td>'
            + '<td style="color:var(--accent);font-weight:700;">' + fmtTime(row.interval_time) + '</td>'
            + '<td>' + typeBadge + '</td>'
            + '<td style="color:var(--text-dim);font-size:10px;">' + (row.trading_symbol||'') + '</td>'
            + '<td><strong style="color:var(--yellow);">' + (row.strike !== null ? Number(row.strike).toLocaleString() : '—') + '</strong></td>'
            + '<td><strong style="color:var(--accent);">' + (row.atm_strike !== null ? Number(row.atm_strike).toLocaleString() : '—') + '</strong></td>'
            + '<td>' + posBadge + '</td>'
            + '<td style="color:var(--text-dim);font-size:10px;">' + (row.expiry_date||'—') + '</td>'
            + '<td style="color:var(--text-dim);">' + (row.future_price ? parseFloat(row.future_price).toFixed(2) : '—') + '</td>'
            + '<td>' + fmt(row.open) + '</td>'
            + '<td class="pos-up">' + fmt(row.high) + '</td>'
            + '<td class="pos-down">' + fmt(row.low) + '</td>'
            + '<td><strong style="color:var(--text);">' + fmt(row.close) + '</strong></td>'
            + '<td style="color:var(--text-dim);">' + (row.volume ? Number(row.volume).toLocaleString() : '0') + '</td>'
            + '<td>' + oiHtml + '</td>'
            + '<td>' + missHtml + '</td>'
            + '</tr>';
    });

    $('#tbody_raw').html(html);

    // Pagination info
    var total = allData.length;
    var totalPages = Math.ceil(total / pageSize);
    var info = 'Showing ' + (start+1) + '–' + Math.min(start+pageSize, total) + ' of ' + total.toLocaleString() + ' rows';
    if (totalPages > 1) {
        info += ' &nbsp;|&nbsp; <a href="#" onclick="changePage(-1); return false;" style="color:var(--accent);">← Prev</a>'
              + ' <span style="color:var(--text); margin:0 6px;">Page ' + currentPage + ' / ' + totalPages + '</span>'
              + '<a href="#" onclick="changePage(1); return false;" style="color:var(--accent);">Next →</a>';
    }
    $('#pagination_raw').html(info);
}

function changePage(dir) {
    var totalPages = Math.ceil(allData.length / pageSize);
    currentPage = Math.max(1, Math.min(totalPages, currentPage + dir));
    renderTable();
    window.scrollTo(0, 0);
}

// ── Render OI compare ──────────────────────────────────────────────────────
function renderOiCompare(data) {
    // Group by date + instrument_type + strike
    var grouped = {};
    data.forEach(function(row) {
        var key = row.trade_date + '|' + row.instrument_type + '|' + row.strike;
        if (!grouped[key]) {
            grouped[key] = {
                date: row.trade_date,
                type: row.instrument_type,
                strike: row.strike,
                atm_strike: row.atm_strike,
                position: row.strike_position,
                oi_1500: null, close_1500: null,
                oi_1515: null, close_1515: null,
                miss_1500: 0,  miss_1515: 0,
            };
        }
        var t = (row.interval_time || '').substring(0, 5);
        if (t === '15:00') {
            grouped[key].oi_1500    = row.oi;
            grouped[key].close_1500 = row.close;
            grouped[key].miss_1500  = row.is_missing;
        } else if (t === '15:15') {
            grouped[key].oi_1515    = row.oi;
            grouped[key].close_1515 = row.close;
            grouped[key].miss_1515  = row.is_missing;
        }
    });

    var html = '';
    var sortedKeys = Object.keys(grouped).sort();

    sortedKeys.forEach(function(key) {
        var r      = grouped[key];
        var typeBadge = r.type === 'CE'
            ? '<span class="badge-ce">CE</span>'
            : '<span class="badge-pe">PE</span>';

        var posBadge = r.position === 'ATM'
            ? '<span class="badge-atm">ATM</span>'
            : (r.position === 'ATM+1' || r.position === 'ATM-1')
                ? '<span class="badge-atm1">' + r.position + '</span>'
                : '<span style="color:var(--muted);font-size:10px;">' + (r.position||'—') + '</span>';

        var oi1500Html = r.oi_1500 === null
            ? '<span class="badge-null">NULL</span>'
            : r.oi_1500 == 0
                ? '<span style="color:var(--orange);font-weight:700;">0</span>'
                : '<strong>' + Number(r.oi_1500).toLocaleString() + '</strong>';

        var oi1515Html = r.oi_1515 === null
            ? '<span class="badge-null">NULL</span>'
            : r.oi_1515 == 0
                ? '<span style="color:var(--orange);font-weight:700;">0</span>'
                : '<strong>' + Number(r.oi_1515).toLocaleString() + '</strong>';

        // OI change between 15:00 and 15:15
        var changeHtml = '<span style="color:var(--muted);">—</span>';
        if (r.oi_1500 !== null && r.oi_1515 !== null && r.oi_1500 > 0) {
            var diff    = r.oi_1515 - r.oi_1500;
            var diffPct = ((diff / r.oi_1500) * 100).toFixed(2);
            var cls     = diff > 0 ? 'pos-up' : (diff < 0 ? 'pos-down' : '');
            changeHtml  = '<span class="' + cls + '">' + (diff > 0 ? '+' : '') + Number(diff).toLocaleString()
                        + ' (' + (diff >= 0 ? '+' : '') + diffPct + '%)</span>';
        }

        var missHtml = (r.miss_1500 == 1 || r.miss_1515 == 1)
            ? '<span class="badge-miss">YES</span>'
            : '<span style="color:var(--green);font-size:10px;">✓</span>';

        html += '<tr>'
            + '<td><strong style="color:var(--text);">' + fmtDate(r.date) + '</strong></td>'
            + '<td>' + typeBadge + '</td>'
            + '<td><strong style="color:var(--yellow);">' + (r.strike !== null ? Number(r.strike).toLocaleString() : '—') + '</strong></td>'
            + '<td style="color:var(--accent);">' + (r.atm_strike !== null ? Number(r.atm_strike).toLocaleString() : '—') + '</td>'
            + '<td>' + posBadge + '</td>'
            + '<td>' + oi1500Html + '</td>'
            + '<td>' + oi1515Html + '</td>'
            + '<td>' + changeHtml + '</td>'
            + '<td style="color:var(--text-dim);">' + fmt(r.close_1500) + '</td>'
            + '<td style="color:var(--text-dim);">' + fmt(r.close_1515) + '</td>'
            + '<td>' + missHtml + '</td>'
            + '</tr>';
    });

    $('#tbody_oi').html(html || '<tr><td colspan="11"><div class="empty-state"><i class="fas fa-inbox"></i>No rows</div></td></tr>');
}

// ── Reset ─────────────────────────────────────────────────────────────────
$('#btn_reset').click(function() {
    $('#sym').val('');
    $('#from_date').val('{{ date("Y-m-d") }}');
    $('#to_date').val('{{ date("Y-m-d") }}');
    $('#instr_type').val('ALL');
    $('#time_slot').val('ALL');
    allData = [];
    showEmpty('tbody_raw', 17, 'Select a symbol and click Fetch Data');
    showEmpty('tbody_oi',  11, 'Click "OI Compare" to load comparison');
    hideSummary();
    $('#tab_bar').hide();
});

// ── Helpers ───────────────────────────────────────────────────────────────
function fmt(v) {
    if (v === null || v === undefined || v === '') return '<span style="color:var(--muted);">—</span>';
    return parseFloat(v).toFixed(2);
}
function fmtOI(v) {
    if (v === null) return '<span class="badge-null">NULL</span>';
    var n = Number(v);
    if (n >= 1000000) return (n/1000000).toFixed(2) + 'M';
    if (n >= 1000)    return (n/1000).toFixed(1) + 'K';
    return n.toString();
}
function showLoading(msg) { $('#loading_txt').text(msg || 'Loading...'); $('#loading').show(); }
function hideLoading()     { $('#loading').hide(); }
function showEmpty(tbodyId, cols, msg) {
    $('#' + tbodyId).html('<tr><td colspan="' + cols + '"><div class="empty-state"><i class="fas fa-info-circle"></i>' + (msg||'No data') + '</div></td></tr>');
}
// ── Format raw datetime strings ───────────────────────────────────────────
function fmtDate(v) {
    if (!v) return '—';
    // Handles "2026-02-15T18:30:00.000000Z" or "2026-02-15 18:30:00" or "2026-02-15"
    var d = new Date(v.includes('T') ? v : v.replace(' ', 'T') + 'Z');
    if (isNaN(d)) return v;
    // Convert UTC to IST (UTC+5:30)
    var ist = new Date(d.getTime() + (5.5 * 60 * 60 * 1000));
    return ist.toISOString().slice(0, 10); // YYYY-MM-DD
}

function fmtTime(v) {
    if (!v) return '—';
    // If it's a full datetime, extract the time part in IST
    if (v.includes('T') || (v.length > 8 && v.includes(' '))) {
        var d = new Date(v.includes('T') ? v : v.replace(' ', 'T') + 'Z');
        if (isNaN(d)) return v;
        var ist = new Date(d.getTime() + (5.5 * 60 * 60 * 1000));
        return ist.toISOString().slice(11, 16); // HH:MM
    }
    // Already a time string like "15:00" or "15:00:00"
    return v.slice(0, 5);
}
</script>
@endpush