@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: center !important;
        padding: 7px 5px !important;
        font-size: 11px !important;
        vertical-align: middle;
    }
    .custom--table thead th:nth-child(1),
    .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2),
    .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3),
    .custom--table tbody td:nth-child(3) { text-align: left !important; }

    .loading-overlay {
        position: absolute; top:0; left:0; right:0; bottom:0;
        background: rgba(19,45,57,0.95);
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        z-index: 1000; border-radius: 12px;
    }
    .spinner { width:50px; height:50px; border:5px solid #f3f3f3; border-top:5px solid #3498db; border-radius:50%; animation: spin 1s linear infinite; }
    .loading-text { color:white; margin-top:20px; font-size:16px; font-weight:600; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    .sent-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sent-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sent-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .cond-ce-up-pe-down { background:linear-gradient(135deg,#dc3545,#fd7e14); color:white; padding:3px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-ce-down-pe-up { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-both-up       { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:3px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-both-down     { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .cond-flat          { background:#e9ecef; color:#495057; padding:3px 6px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    .filter-section {
        background: linear-gradient(135deg,#667eea,#764ba2);
        padding:20px; border-radius:12px; margin-bottom:20px;
        box-shadow:0 4px 15px rgba(102,126,234,0.4); color:white;
    }
    .filter-section label { color:white !important; font-weight:600; margin-bottom:6px; font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.9); color:#333; font-size:12px; padding:6px 10px; }

    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.1); transition:transform .2s; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#666; font-size:10px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box strong { display:block; font-size:1.4rem; font-weight:700; margin-top:3px; }

    .page-header { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4); }
    .new-feature-badge { background:linear-gradient(135deg,#f093fb,#f5576c); color:white; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }

    .th-group-atm   { background:rgba(40,167,69,0.15) !important; color:#28a745 !important; font-size:11px !important; text-align:center !important; }
    .th-group-combo { background:rgba(102,126,234,0.15) !important; color:#667eea !important; font-size:11px !important; text-align:center !important; }
    .th-group-all   { background:rgba(253,126,20,0.15) !important; color:#fd7e14 !important; font-size:11px !important; text-align:center !important; }

    .td-atm   { border-left:2px solid rgba(40,167,69,0.3) !important; }
    .td-combo { border-left:2px solid rgba(102,126,234,0.3) !important; }
    .td-all   { border-left:2px solid rgba(253,126,20,0.3) !important; }

    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .custom--table { min-width:1600px; }

    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position:sticky; z-index:10; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left:0; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left:36px; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left:115px; }

    /* .custom--table tbody tr:nth-child(even) td { background:#f9f9f9; }
    .custom--table tbody tr:nth-child(odd)  td { background:#ffffff; } */
    .custom--table thead th { background:#2c3e50; color:#fff; }

    .atm-strike-badge { background:linear-gradient(135deg,#17a2b8,#138496); color:white; padding:2px 6px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                <div>
                    <h4>{{ $pageTitle }} <span class="new-feature-badge">3 STRIKE VIEWS</span></h4>
                    <p style="margin:0; font-size:12px; opacity:.85;">
                        OI Change: Prev Day 15:15 &rarr; Today 15:00 &nbsp;|&nbsp;
                        ATM &middot; ATM+1CE/ATM-1PE &middot; All Strikes compared side-by-side
                    </p>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a href="{{ route('oiiv-auto.config') }}"        class="btn btn-light btn-sm"><i class="fas fa-cog"></i> Configs</a>
                    <a href="{{ route('9to12.pece-analysis') }}"     class="btn btn-light btn-sm"><i class="fas fa-clock"></i> 9:30&rarr;12:15</a>
                    <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm"><i class="fas fa-chart-bar"></i> EOD PE/CE</a>
                    <a href="{{ route('oiiv-auto.index') }}"         class="btn btn-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
                </div>
            </div>
        </div>

        <div class="alert" style="background:linear-gradient(135deg,#0f2027,#203a43,#2c5364); color:white; border:none; border-radius:12px; margin-bottom:20px; padding:15px;">
            <h6 style="color:#00d2ff; margin-bottom:10px; font-size:13px;"><i class="fas fa-info-circle"></i> <strong>Three-View OI Strike Comparison</strong></h6>
            <div class="row">
                <div class="col-md-4">
                    <span style="color:#28a745; font-weight:700; font-size:11px;">&#x1F7E2; ATM Only</span>
                    <p style="font-size:10px; margin-top:4px; opacity:.85;">CE ATM vs PE ATM &mdash; single strike view. Cleanest signal from the most active strike.</p>
                </div>
                <div class="col-md-4">
                    <span style="color:#667eea; font-weight:700; font-size:11px;">&#x1F535; ATM + ATM+1 CE / ATM-1 PE</span>
                    <p style="font-size:10px; margin-top:4px; opacity:.85;">CE = ATM + next higher strike. PE = ATM + next lower strike. Broader near-money view.</p>
                </div>
                <div class="col-md-4">
                    <span style="color:#fd7e14; font-weight:700; font-size:11px;">&#x1F7E0; All Strikes (Existing)</span>
                    <p style="font-size:10px; margin-top:4px; opacity:.85;">All CE strikes merged vs all PE strikes. Same as existing EOD PE/CE analysis.</p>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="row mb-2">
                <div class="col-md-3">
                    <label><i class="fas fa-calendar-alt"></i> From Date:</label>
                    <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>
                <div class="col-md-3">
                    <label><i class="fas fa-calendar-alt"></i> To Date:</label>
                    <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>
                <div class="col-md-3">
                    <label><i class="fas fa-filter"></i> Symbols (Optional):</label>
                    <select id="symbol_filter" class="form-control" multiple size="2"></select>
                    <small style="color:rgba(255,255,255,0.8); font-size:10px;">Leave empty for all</small>
                </div>
                <div class="col-md-3">
                    <label><i class="fas fa-chart-pie"></i> Filter Sentiment:</label>
                    <select id="sentiment_filter" class="form-control">
                        <option value="">All Sentiments</option>
                        <option value="BULLISH">BULLISH Only</option>
                        <option value="BEARISH">BEARISH Only</option>
                        <option value="NEUTRAL">NEUTRAL Only</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 text-center">
                    <button type="button" id="run_analysis" class="btn btn-light btn-lg" style="min-width:150px; font-size:13px;">
                        <i class="fas fa-search"></i> View Data
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg ml-2" style="min-width:150px; font-size:13px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-6 col-md-2"><div class="stats-box"><small>Total Records</small><strong id="stat_total" class="text-dark">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#28a745;"><small>ATM Bullish</small><strong id="stat_atm_bull" style="color:#28a745;">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#dc3545;"><small>ATM Bearish</small><strong id="stat_atm_bear" style="color:#dc3545;">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#667eea;"><small>Combo Bullish</small><strong id="stat_combo_bull" style="color:#667eea;">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#c82333;"><small>Combo Bearish</small><strong id="stat_combo_bear" style="color:#c82333;">0</strong></div></div>
            <div class="col-6 col-md-2"><div class="stats-box" style="border-left-color:#fd7e14;"><small>All Bullish</small><strong id="stat_all_bull" style="color:#fd7e14;">0</strong></div></div>
        </div>

        <div style="position:relative; min-height:400px;">
            <div class="loading-overlay" id="loading-overlay" style="display:none;">
                <div class="spinner"></div>
                <div class="loading-text">Loading data...</div>
            </div>
            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th colspan="4" style="background:#2c3e50;"></th>
                            <th colspan="6" class="th-group-atm text-center">&#x1F7E2; ATM Only</th>
                            <th colspan="6" class="th-group-combo text-center">&#x1F535; ATM + ATM+1 CE &amp; ATM-1 PE</th>
                            <th colspan="6" class="th-group-all text-center">&#x1F7E0; All Strikes (Existing)</th>
                        </tr>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Symbol</th>
                            <th>ATM Strike</th>

                            <th class="td-atm">CE OI</th>
                            <th class="td-atm">CE %</th>
                            <th class="td-atm">PE OI</th>
                            <th class="td-atm">PE %</th>
                            <th class="td-atm">Condition</th>
                            <th class="td-atm">Sentiment</th>

                            <th class="td-combo">CE OI</th>
                            <th class="td-combo">CE %</th>
                            <th class="td-combo">PE OI</th>
                            <th class="td-combo">PE %</th>
                            <th class="td-combo">Condition</th>
                            <th class="td-combo">Sentiment</th>

                            <th class="td-all">CE OI</th>
                            <th class="td-all">CE %</th>
                            <th class="td-all">PE OI</th>
                            <th class="td-all">PE %</th>
                            <th class="td-all">Condition</th>
                            <th class="td-all">Sentiment</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="22" class="text-center py-5">
                                <i class="fas fa-chart-pie" style="font-size:3rem; opacity:0.5;"></i>
                                <p style="font-size:1.1rem; margin-top:20px;">Click <strong>"View Data"</strong> to load analysis</p>
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
    let analysisData = [];

    function toggleLoading(show, msg) {
        msg = msg || 'Loading data...';
        if (show) { $('#loading-overlay .loading-text').text(msg); $('#loading-overlay').show(); }
        else       { $('#loading-overlay').hide(); }
    }

    $(document).ready(function () {
        loadSymbols();
        setTimeout(function() { runAnalysis(); }, 500);
    });

    function loadSymbols() {
        $.ajax({
            url: '{{ route("strike-analysis.symbols") }}', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                var opts = '';
                res.symbols.forEach(function(s) { opts += '<option value="' + s + '">' + s + '</option>'; });
                $('#symbol_filter').html(opts);
            }
        });
    }

    function runAnalysis() {
        var fromDate  = $('#from_date').val();
        var toDate    = $('#to_date').val();
        var symbols   = $('#symbol_filter').val() || [];
        var sentiment = $('#sentiment_filter').val();

        if (!fromDate || !toDate) { alert('Please select both dates'); return; }

        toggleLoading(true, 'Loading strike comparison...');
        analysisData = [];

        $.ajax({
            url: '{{ route("strike-analysis.analyze") }}', type: 'GET',
            data: { from_date: fromDate, to_date: toDate, symbols: symbols, filter_sentiment: sentiment },
            success: function (res) {
                if (res.success && res.data && res.data.length > 0) {
                    analysisData = res.data;
                    renderTable();
                    updateStats();
                } else {
                    showNoData(res.message || 'No data found');
                    resetStats();
                }
                toggleLoading(false);
            },
            error: function () {
                showNoData('Error loading data');
                resetStats();
                toggleLoading(false);
            }
        });
    }

    function renderTable() {
        if (!analysisData.length) return;
        var html = '';

        analysisData.forEach(function(row, i) {
            html += '<tr>'
                + '<td><strong>' + (i + 1) + '</strong></td>'
                + '<td><strong>' + row.date + '</strong></td>'
                + '<td><strong style="color:#667eea;">' + row.symbol + '</strong></td>'
                + '<td><span class="atm-strike-badge">' + row.atm_strike + '</span></td>'

                // ATM
                + '<td class="td-atm"><strong>' + fmtOI(row.atm_ce_oi) + '</strong></td>'
                + '<td class="td-atm ' + (row.atm_ce_pct >= 0 ? 'text-success' : 'text-danger') + '"><strong>' + fmt2(row.atm_ce_pct) + '%</strong></td>'
                + '<td class="td-atm"><strong>' + fmtOI(row.atm_pe_oi) + '</strong></td>'
                + '<td class="td-atm ' + (row.atm_pe_pct >= 0 ? 'text-success' : 'text-danger') + '"><strong>' + fmt2(row.atm_pe_pct) + '%</strong></td>'
                + '<td class="td-atm">' + condBadge(row.atm_cond) + '</td>'
                + '<td class="td-atm">' + sentBadge(row.atm_sent) + '</td>'

                // Combo
                + '<td class="td-combo"><strong>' + fmtOI(row.atm1_ce_oi) + '</strong></td>'
                + '<td class="td-combo ' + (row.atm1_ce_pct >= 0 ? 'text-success' : 'text-danger') + '"><strong>' + fmt2(row.atm1_ce_pct) + '%</strong></td>'
                + '<td class="td-combo"><strong>' + fmtOI(row.atm1_pe_oi) + '</strong></td>'
                + '<td class="td-combo ' + (row.atm1_pe_pct >= 0 ? 'text-success' : 'text-danger') + '"><strong>' + fmt2(row.atm1_pe_pct) + '%</strong></td>'
                + '<td class="td-combo">' + condBadge(row.atm1_cond) + '</td>'
                + '<td class="td-combo">' + sentBadge(row.atm1_sent) + '</td>'

                // All
                + '<td class="td-all"><strong>' + fmtOI(row.all_ce_oi) + '</strong></td>'
                + '<td class="td-all ' + (row.all_ce_pct >= 0 ? 'text-success' : 'text-danger') + '"><strong>' + fmt2(row.all_ce_pct) + '%</strong></td>'
                + '<td class="td-all"><strong>' + fmtOI(row.all_pe_oi) + '</strong></td>'
                + '<td class="td-all ' + (row.all_pe_pct >= 0 ? 'text-success' : 'text-danger') + '"><strong>' + fmt2(row.all_pe_pct) + '%</strong></td>'
                + '<td class="td-all">' + condBadge(row.all_cond) + '</td>'
                + '<td class="td-all">' + sentBadge(row.all_sent) + '</td>'
                + '</tr>';
        });

        $('#analysis-tbody').html(html);
    }

    function updateStats() {
        $('#stat_total').text(analysisData.length);
        $('#stat_atm_bull').text(analysisData.filter(function(r){ return r.atm_sent   === 'BULLISH'; }).length);
        $('#stat_atm_bear').text(analysisData.filter(function(r){ return r.atm_sent   === 'BEARISH'; }).length);
        $('#stat_combo_bull').text(analysisData.filter(function(r){ return r.atm1_sent === 'BULLISH'; }).length);
        $('#stat_combo_bear').text(analysisData.filter(function(r){ return r.atm1_sent === 'BEARISH'; }).length);
        $('#stat_all_bull').text(analysisData.filter(function(r){ return r.all_sent   === 'BULLISH'; }).length);
    }

    function resetStats() {
        $('#stat_total,#stat_atm_bull,#stat_atm_bear,#stat_combo_bull,#stat_combo_bear,#stat_all_bull').text('0');
    }

    function fmtOI(val) {
        var n = Number(val) || 0;
        if (n >= 1000000) return (n / 1000000).toFixed(2) + 'M';
        if (n >= 1000)    return (n / 1000).toFixed(1) + 'K';
        return n.toString();
    }

    function fmt2(val) {
        var n = Number(val) || 0;
        return (n >= 0 ? '+' : '') + n.toFixed(2);
    }

    function condBadge(cond) {
        if (!cond) return '<span class="cond-flat">N/A</span>';
        if (cond.indexOf('CE \u2191 + PE \u2193') !== -1) return '<span class="cond-ce-up-pe-down">' + cond + '</span>';
        if (cond.indexOf('CE \u2193 + PE \u2191') !== -1) return '<span class="cond-ce-down-pe-up">' + cond + '</span>';
        if (cond.indexOf('Both \u2191') !== -1)           return '<span class="cond-both-up">'       + cond + '</span>';
        if (cond.indexOf('Both \u2193') !== -1)           return '<span class="cond-both-down">'     + cond + '</span>';
        return '<span class="cond-flat">' + cond + '</span>';
    }

    function sentBadge(sent) {
        if (sent === 'BULLISH') return '<span class="sent-bullish">&#x1F7E2; BULLISH</span>';
        if (sent === 'BEARISH') return '<span class="sent-bearish">&#x1F534; BEARISH</span>';
        return '<span class="sent-neutral">&#x26AA; NEUTRAL</span>';
    }

    function showNoData(msg) {
        $('#analysis-tbody').html('<tr><td colspan="22" class="text-center py-5"><i class="fas fa-info-circle" style="color:#17a2b8; font-size:3rem;"></i><p class="text-info" style="margin-top:20px;">' + msg + '</p></td></tr>');
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter').val('');
        $('#sentiment_filter').val('');
        analysisData = [];
        showNoData('Click "View Data" to load signals');
        resetStats();
        setTimeout(function() { runAnalysis(); }, 300);
    }

    $('#run_analysis').click(function() { runAnalysis(); });
    $('#reset_filters').click(function() { resetFilters(); });
</script>
@endpush