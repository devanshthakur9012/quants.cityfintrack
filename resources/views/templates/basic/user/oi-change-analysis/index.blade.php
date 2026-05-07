@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* Table Styling */
    .custom--table thead th,
    .custom--table tbody td {
        text-align: center !important;
        padding: 10px 8px !important;
        font-size: 12px !important;
        vertical-align: middle;
    }

    .custom--table thead th:first-child,
    .custom--table tbody td:first-child,
    .custom--table thead th:nth-child(2),
    .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3),
    .custom--table tbody td:nth-child(3) {
        text-align: left !important;
    }

    /* Loading Overlay */
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(19, 45, 57, 0.95);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        border-radius: 12px;
    }

    .spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    .loading-text {
        color: white;
        margin-top: 20px;
        font-size: 16px;
        font-weight: 600;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Signal Badges */
    .signal-bullish {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(40, 167, 69, 0.3);
    }

    .signal-bearish {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
    }

    .signal-neutral {
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(108, 117, 125, 0.3);
    }

    /* Direction Indicators */
    .direction-up {
        color: #28a745;
        font-weight: 700;
    }

    .direction-down {
        color: #dc3545;
        font-weight: 700;
    }

    .direction-flat {
        color: #6c757d;
        font-weight: 700;
    }

    /* Filter Section */
    .filter-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .filter-section label {
        color: white !important;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 13px;
    }

    .filter-section .form-control {
        border: 2px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.9);
        color: #333;
        font-size: 13px;
        padding: 8px 12px;
    }

    /* Stats Boxes */
    .stats-box {
        background: #fff;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
        border-left: 4px solid #3498db;
        margin-bottom: 15px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .stats-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 14px rgba(0, 0, 0, 0.15);
    }

    .stats-box small {
        display: block;
        color: #666;
        font-size: 11px;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stats-box strong {
        display: block;
        font-size: 1.6rem;
        margin-top: 5px;
        font-weight: 700;
    }

    /* Page Header */
    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .page-header h4 {
        font-size: 1.5rem;
        margin-bottom: 8px;
    }

    .page-header p {
        font-size: 14px;
        margin-bottom: 0;
    }

    /* Logic Box */
    .logic-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .logic-box h6 {
        color: white;
        margin-bottom: 15px;
        font-size: 15px;
    }

    .logic-item {
        background: rgba(255, 255, 255, 0.1);
        padding: 10px 15px;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .logic-item:last-child {
        margin-bottom: 0;
    }

    .condition-badge {
        background: rgba(255, 255, 255, 0.9);
        color: #333;
        padding: 4px 10px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 11px;
        margin-right: 10px;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="fas fa-chart-line"></i> {{ $pageTitle }}</h4>
                    <p>Simple CE/PE OI Change Direction Analysis</p>
                </div>
                <div>
                    <a href="{{ route('oiiv-auto.index') }}" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-chart-bar"></i> OI+IV Analysis
                    </a>
                    <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-balance-scale"></i> PE/CE Ratio
                    </a>
                    <a href="{{ route('oiiv-auto.config') }}" class="btn btn-light btn-sm">
                        <i class="fas fa-cog"></i> Configs
                    </a>
                </div>
            </div>
        </div>

        <!-- Logic Explanation -->
        <div class="logic-box">
            <h6><i class="fas fa-info-circle"></i> <strong>Analysis Logic</strong></h6>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="logic-item">
                        <span class="condition-badge">CE ↑ + PE ↓</span>
                        <strong>→ BEARISH</strong>
                        <br><small>Call buildup + Put unwinding</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="logic-item">
                        <span class="condition-badge">CE ↓ + PE ↑</span>
                        <strong>→ BULLISH</strong>
                        <br><small>Call unwinding + Put buildup</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="logic-item">
                        <span class="condition-badge">Both ↑</span>
                        <strong>→ COMPARE</strong>
                        <br><small>If CE% > PE% → BEARISH, else BULLISH</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="logic-item">
                        <span class="condition-badge">Both ↓</span>
                        <strong>→ COMPARE</strong>
                        <br><small>If CE% < PE% (more negative) → BULLISH, else BEARISH</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row mb-2">
                <div class="col-md-3">
                    <label for="from_date"><i class="fas fa-calendar-alt"></i> From Date:</label>
                    <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-3">
                    <label for="to_date"><i class="fas fa-calendar-alt"></i> To Date:</label>
                    <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-3">
                    <label for="symbol_filter"><i class="fas fa-filter"></i> Symbols (Optional):</label>
                    <select id="symbol_filter" class="form-control" multiple size="2"></select>
                    <small style="color: rgba(255,255,255,0.8); font-size: 11px;">Leave empty for all</small>
                </div>

                <div class="col-md-3">
                    <label for="signal_filter"><i class="fas fa-signal"></i> Signal Filter:</label>
                    <select id="signal_filter" class="form-control">
                        <option value="">All Signals</option>
                        <option value="BULLISH">Bullish Only</option>
                        <option value="BEARISH">Bearish Only</option>
                        <option value="NEUTRAL">Neutral Only</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 text-center">
                    <button type="button" id="run_analysis" class="btn btn-light btn-lg" style="min-width: 150px;">
                        <i class="fas fa-search"></i> View Data
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg" style="min-width: 150px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total Records</small>
                    <strong id="total_records" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>Bullish Signals</small>
                    <strong id="bullish_count" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #dc3545;">
                    <small>Bearish Signals</small>
                    <strong id="bearish_count" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #6c757d;">
                    <small>Neutral Signals</small>
                    <strong id="neutral_count" style="color: #6c757d;">0</strong>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div style="position: relative; min-height: 400px;">
            <div class="loading-overlay" id="loading-overlay" style="display: none;">
                <div class="spinner"></div>
                <div class="loading-text">Loading data...</div>
            </div>

            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Symbol</th>
                            <th>Spot Price</th>
                            <th>CE OI</th>
                            <th>CE %</th>
                            <th>CE Dir</th>
                            <th>PE OI</th>
                            <th>PE %</th>
                            <th>PE Dir</th>
                            <th>Condition</th>
                            <th>Signal</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="13" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-chart-line" style="font-size: 3rem; opacity: 0.5;"></i>
                                    <p style="font-size: 1.1rem; margin-top: 20px;">Click <strong>"View Data"</strong> to load analysis</p>
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
    let analysisData = [];

    function toggleLoading(show) {
        if (show) {
            $('#loading-overlay').show();
        } else {
            $('#loading-overlay').hide();
        }
    }

    $(document).ready(function() {
        loadSymbols();
        setTimeout(() => runAnalysis(), 500);
    });

    function loadSymbols() {
        $.ajax({
            url: '{{ route("oi-change.symbols") }}',
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    let options = '';
                    response.symbols.forEach(symbol => {
                        options += `<option value="${symbol}">${symbol}</option>`;
                    });
                    $('#symbol_filter').html(options);
                }
            }
        });
    }

    function runAnalysis() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        const selectedSymbols = $('#symbol_filter').val() || [];
        const filterSignal = $('#signal_filter').val();

        if (!fromDate || !toDate) {
            alert('Please select both dates');
            return;
        }

        toggleLoading(true);
        analysisData = [];

        $.ajax({
            url: '{{ route("oi-change.analyze") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                filter_signal: filterSignal
            },
            success: function (response) {
                console.log('Analysis Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    analysisData = response.data;
                    displayAnalysisTable();
                    updateStatistics();
                } else {
                    showNoData(response.message || 'No data found');
                    resetStatistics();
                }
                toggleLoading(false);
            },
            error: function (xhr) {
                console.error('Analysis Error:', xhr);
                showNoData('Error loading data');
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function displayAnalysisTable() {
        if (!analysisData || analysisData.length === 0) return;

        let html = '';
        
        analysisData.forEach(function (row, index) {
            // Signal Badge
            let signalBadge = '';
            if (row.signal === 'BULLISH') {
                signalBadge = '<span class="signal-bullish">🟢 BULLISH</span>';
            } else if (row.signal === 'BEARISH') {
                signalBadge = '<span class="signal-bearish">🔴 BEARISH</span>';
            } else {
                signalBadge = '<span class="signal-neutral">⚪ NEUTRAL</span>';
            }
            
            // Direction Classes
            let ceClass = row.ce_direction === 'UP' ? 'direction-up' : 
                         row.ce_direction === 'DOWN' ? 'direction-down' : 'direction-flat';
            let peClass = row.pe_direction === 'UP' ? 'direction-up' : 
                         row.pe_direction === 'DOWN' ? 'direction-down' : 'direction-flat';
            
            // Direction Arrows
            let ceArrow = row.ce_direction === 'UP' ? '↑' : 
                         row.ce_direction === 'DOWN' ? '↓' : '→';
            let peArrow = row.pe_direction === 'UP' ? '↑' : 
                         row.pe_direction === 'DOWN' ? '↓' : '→';

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.date}</strong></td>
                    <td><strong style="color: #667eea;">${row.symbol}</strong></td>
                    <td><strong>₹${row.spot_price.toLocaleString()}</strong></td>
                    <td>${row.ce_oi.toLocaleString()}</td>
                    <td class="${ceClass}"><strong>${row.ce_oi_change_pct > 0 ? '+' : ''}${row.ce_oi_change_pct}%</strong></td>
                    <td class="${ceClass}"><strong>${ceArrow}</strong></td>
                    <td>${row.pe_oi.toLocaleString()}</td>
                    <td class="${peClass}"><strong>${row.pe_oi_change_pct > 0 ? '+' : ''}${row.pe_oi_change_pct}%</strong></td>
                    <td class="${peClass}"><strong>${peArrow}</strong></td>
                    <td><strong>${row.condition}</strong></td>
                    <td>${signalBadge}</td>
                    <td><small>${row.reason}</small></td>
                </tr>
            `;
        });

        $('#analysis-tbody').html(html);
    }

    function updateStatistics() {
        if (!analysisData || analysisData.length === 0) {
            resetStatistics();
            return;
        }

        $('#total_records').text(analysisData.length);
        $('#bullish_count').text(analysisData.filter(r => r.signal === 'BULLISH').length);
        $('#bearish_count').text(analysisData.filter(r => r.signal === 'BEARISH').length);
        $('#neutral_count').text(analysisData.filter(r => r.signal === 'NEUTRAL').length);
    }

    function resetStatistics() {
        $('#total_records, #bullish_count, #bearish_count, #neutral_count').text('0');
    }

    function showNoData(message) {
        $('#analysis-tbody').html(`
            <tr>
                <td colspan="13" class="text-center py-5">
                    <div class="no-data-message">
                        <i class="fas fa-info-circle" style="color: #17a2b8; font-size: 3rem;"></i>
                        <p class="text-info" style="margin-top: 20px;">${message}</p>
                    </div>
                </td>
            </tr>
        `);
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter, #signal_filter').val('');
        analysisData = [];
        showNoData('Click "View Data" to load analysis');
        resetStatistics();
        setTimeout(() => runAnalysis(), 300);
    }

    $('#run_analysis').click(() => runAnalysis());
    $('#reset_filters').click(() => resetFilters());
</script>
@endpush