@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ✅ SMALLER FONTS */
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
    .custom--table tbody td:nth-child(3) {
        text-align: left !important;
    }

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

    /* Trade Action Badges - SMALLER */
    .action-buy-ce {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(40, 167, 69, 0.3);
    }

    .action-buy-pe {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
    }

    .action-both {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(255, 193, 7, 0.3);
    }

    /* Sentiment Badges - SMALLER */
    .sentiment-strong-bullish,
    .sentiment-bullish,
    .sentiment-strong-bearish,
    .sentiment-bearish,
    .sentiment-neutral {
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 9px;
        display: inline-block;
    }

    .sentiment-strong-bullish {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
    }

    .sentiment-bullish {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
    }

    .sentiment-strong-bearish {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
        }

    .sentiment-bearish {
        background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);
        color: white;
    }

    .sentiment-neutral {
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white;
    }

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
        margin-bottom: 6px;
        font-size: 13px;
    }

    .filter-section .form-control {
        border: 2px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.9);
        color: #333;
        font-size: 12px;
        padding: 6px 10px;
    }

    /* ✅ SMALLER STATS BOXES */
    .stats-box {
        background: #fff;
        padding: 12px;
        border-radius: 10px;
        text-align: center;
        border-left: 4px solid #3498db;
        margin-bottom: 12px;
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
        font-size: 10px;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .stats-box strong {
        display: block;
        font-size: 1.4rem;
        margin-top: 3px;
        font-weight: 700;
    }

    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .page-header h4 {
        font-size: 1.4rem;
        margin-bottom: 5px;
    }

    .page-header p {
        font-size: 13px;
        margin-bottom: 0;
    }

    .ratio-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
    }

    .interpretation-put-writing {
        color: #28a745;
        font-weight: 700;
        font-size: 11px;
    }

    .interpretation-call-writing {
        color: #dc3545;
        font-weight: 700;
        font-size: 11px;
    }

    .interpretation-balanced {
        color: #6c757d;
        font-weight: 700;
        font-size: 11px;
    }

    .new-feature-badge {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 9px;
        font-weight: 700;
        margin-left: 5px;
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .custom--table {
        min-width: 2000px;
    }

    /* Sticky columns */
    .custom--table thead th:nth-child(1),
    .custom--table thead th:nth-child(2),
    .custom--table thead th:nth-child(3),
    .custom--table tbody td:nth-child(1),
    .custom--table tbody td:nth-child(2),
    .custom--table tbody td:nth-child(3) {
        position: sticky;
        z-index: 10;
    }

    .custom--table thead th:nth-child(1),
    .custom--table tbody td:nth-child(1) {
        left: 0;
    }

    .custom--table thead th:nth-child(2),
    .custom--table tbody td:nth-child(2) {
        left: 40px;
    }

    .custom--table thead th:nth-child(3),
    .custom--table tbody td:nth-child(3) {
        left: 120px;
    }

    /* ✅ Condition Badge Styles */
    .condition-ce-up-pe-down {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 9px;
        display: inline-block;
    }

    .condition-ce-down-pe-up {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 9px;
        display: inline-block;
    }

    .condition-both-up {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 9px;
        display: inline-block;
    }

    .condition-both-down {
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 9px;
        display: inline-block;
    }

    .condition-flat {
        background: #e9ecef;
        color: #495057;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 9px;
        display: inline-block;
    }

    .table-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        margin-top: 30px;
        margin-bottom: 15px;
    }

    .table-header h5 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
    }

    .interpretation-text {
        font-size: 10px;
        color: #555;
        font-weight: 600;
        font-style: italic;
    }

    .custom--table {
        min-width: 100%;
        table-layout: fixed;   /* 🔥 IMPORTANT */
        width: 100%;
    }

    /* Column 1 */
    .custom--table th:nth-child(1),
    .custom--table td:nth-child(1) {
        width: 20px;
    }

    /* Column 2 */
    .custom--table th:nth-child(2),
    .custom--table td:nth-child(2) {
        width: 30px;
    }

    /* Column 3 */
    .custom--table th:nth-child(3),
    .custom--table td:nth-child(3) {
        width: 30px;
    }

    /* Column 4-7 */
    .custom--table th:nth-child(4),
    .custom--table td:nth-child(4),
    .custom--table th:nth-child(5),
    .custom--table td:nth-child(5),
    .custom--table th:nth-child(6),
    .custom--table td:nth-child(6),
    .custom--table th:nth-child(7),
    .custom--table td:nth-child(7) {
        width: 30px;
    }

    /* Condition */
    .custom--table th:nth-child(8),
    .custom--table td:nth-child(8) {
        width: 30px;
    }

    /* Interpretation */
    .custom--table th:nth-child(9),
    .custom--table td:nth-child(9) {
        width: 30px;
    }

    /* Ratio */
    .custom--table th:nth-child(10),
    .custom--table td:nth-child(10) {
        width: 30px;
    }

    /* FUT % */
    .custom--table th:nth-child(11),
    .custom--table td:nth-child(11) {
        width: 30px;
    }

    /* Sentiment */
    .custom--table th:nth-child(12),
    .custom--table td:nth-child(12) {
        width: 30px;
    }

    /* Strong Signal */
    .custom--table th:nth-child(13),
    .custom--table td:nth-child(13) {
        width: 30px;
    }

    /* Action */
    .custom--table th:nth-child(14),
    .custom--table td:nth-child(14) {
        width: 30px;
    }

</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }} <span class="new-feature-badge">INDIVIDUAL</span></h4>
                    <p>3-Strike (ATM-1, ATM, ATM+1) & 2-Strike (CE: ATM, ATM+1 | PE: ATM-1, ATM) Analysis</p>
                </div>
                <div>
                    <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-layer-group"></i> Merged Analysis
                    </a>
                    <a href="{{ route('oiiv-auto.index') }}" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-chart-line"></i> OI+IV Analysis
                    </a>
                    <a href="{{ route('oiiv-auto.config') }}" class="btn btn-light btn-sm">
                        <i class="fas fa-cog"></i> Configs
                    </a>
                </div>
            </div>
        </div>

        <!-- Logic Explanation Alert -->
        <div class="alert" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; margin-bottom: 20px; padding: 15px;">
            <h6 style="color: white; margin-bottom: 10px; font-size: 14px;"><i class="fas fa-info-circle"></i> <strong>Individual Strike Analysis Logic:</strong></h6>
            
            <div class="row mb-2">
                <div class="col-md-4">
                    <small style="font-size: 11px;"><strong>📊 CE/PE OI Analysis</strong></small>
                    <ul style="font-size: 10px; margin-top: 5px;">
                        <li><strong>CE ↑ + PE ↓</strong> → BEARISH</li>
                        <li><strong>CE ↓ + PE ↑</strong> → BULLISH</li>
                        <li><strong>Both ↑</strong> → Compare strength</li>
                        <li><strong>Both ↓</strong> → Compare unwinding</li>
                    </ul>
                </div>
                
                <div class="col-md-4">
                    <small style="font-size: 11px;"><strong>🎯 Strike Combinations</strong></small>
                    <ul style="font-size: 10px; margin-top: 5px;">
                        <li><strong>3-Strike:</strong> ATM-1, ATM, ATM+1 (symmetric)</li>
                        <li><strong>2-Strike:</strong> CE: ATM+1, ATM | PE: ATM, ATM-1</li>
                    </ul>
                </div>
                
                <div class="col-md-4">
                    <small style="font-size: 11px;"><strong>🎯 Strong Signal</strong></small>
                    <ul style="font-size: 10px; margin-top: 5px;">
                        <li>For "Both" scenarios: Shows which side is stronger</li>
                        <li>CE Strong → More bearish pressure</li>
                        <li>PE Strong → More bullish pressure</li>
                    </ul>
                </div>
            </div>
            
            <hr style="border-color: rgba(255,255,255,0.3); margin: 10px 0;">
            <small style="font-size: 10px;"><i class="fas fa-lightbulb"></i> <strong>Note:</strong> Both tables show same data but with different strike combinations for comparison.</small>
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
                    <small style="color: rgba(255,255,255,0.8); font-size: 10px;">Leave empty for all</small>
                </div>

                <div class="col-md-3">
                    <label for="action_filter"><i class="fas fa-bullseye"></i> Trade Action Filter:</label>
                    <select id="action_filter" class="form-control">
                        <option value="">All Trade Actions</option>
                        <option value="BUY CE">BUY CE Only</option>
                        <option value="BUY PE">BUY PE Only</option>
                        <option value="WAIT">WAIT Only</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 text-center">
                    <button type="button" id="run_analysis" class="btn btn-light btn-lg" style="min-width: 150px; font-size: 13px;">
                        <i class="fas fa-search"></i> View Data
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg" style="min-width: 150px; font-size: 13px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total Records (3-Strike)</small>
                    <strong id="total_records_3" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total Records (2-Strike)</small>
                    <strong id="total_records_2" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>BULLISH Signals</small>
                    <strong id="bullish_count" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #dc3545;">
                    <small>BEARISH Signals</small>
                    <strong id="bearish_count" style="color: #dc3545;">0</strong>
                </div>
            </div>
        </div>

        <!-- ===== 3-STRIKE TABLE ===== -->
        <div class="table-header">
            <h5><i class="fas fa-layer-group"></i> 3-Strike Analysis (ATM-1, ATM, ATM+1)</h5>
        </div>

        <div style="position: relative; min-height: 300px;">
            <div class="loading-overlay" id="loading-overlay-3" style="display: none;">
                <div class="spinner"></div>
                <div class="loading-text">Loading 3-strike data...</div>
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
                            <th>Condition</th>
                            <th>Interpretation</th>
                            <th>Ratio</th>
                            <th>FUT %</th>
                            <th>Sentiment</th>
                            <th>Strong Signal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody-3">
                        <tr>
                            <td colspan="14" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-chart-pie" style="font-size: 3rem; opacity: 0.5 !important;"></i>
                                    <p style="font-size: 1.1rem; margin-top: 20px !important;">Click <strong>"View Data"</strong></p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== 2-STRIKE TABLE ===== -->
        <div class="table-header">
            <h5><i class="fas fa-grip-horizontal"></i> 2-Strike Analysis (CE: ATM, ATM+1 | PE: ATM-1, ATM)</h5>
        </div>

        <div style="position: relative; min-height: 300px !important;">
            <div class="loading-overlay" id="loading-overlay-2" style="display: none !important;">
                <div class="spinner"></div>
                <div class="loading-text">Loading 2-strike data...</div>
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
                            <th>Condition</th>
                            <th>Interpretation</th>
                            <th>Ratio</th>
                            <th>FUT %</th>
                            <th>Sentiment</th>
                            <th>Strong Signal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody-2">
                        <tr>
                            <td colspan="14" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-chart-pie" style="font-size: 3rem; opacity: 0.5;"></i>
                                    <p style="font-size: 1.1rem; margin-top: 20px;">Click <strong>"View Data"</strong></p>
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
    let threeStrikeData = [];
    let twoStrikeData = [];

    function toggleLoading(tableId, show, message = 'Loading data...') {
        const overlayId = tableId === 3 ? 'loading-overlay-3' : 'loading-overlay-2';
        if (show) {
            $('#' + overlayId + ' .loading-text').text(message);
            $('#' + overlayId).show();
        } else {
            $('#' + overlayId).hide();
        }
    }

    $(document).ready(function() {
        loadSymbols();
        setTimeout(() => runAnalysis(), 500);
    });

    function loadSymbols() {
        $.ajax({
            url: '{{ route("oiiv-individual.symbols") }}',
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
        const filterAction = $('#action_filter').val();

        if (!fromDate || !toDate) {
            alert('Please select both dates');
            return;
        }

        toggleLoading(3, true, 'Loading data...');
        toggleLoading(2, true, 'Loading data...');
        threeStrikeData = [];
        twoStrikeData = [];

        $.ajax({
            url: '{{ route("oiiv-individual.analyze") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                filter_action: filterAction
            },
            success: function (response) {
                console.log('Analysis Response:', response);
                
                if (response.success) {
                    threeStrikeData = response.three_strike_data || [];
                    twoStrikeData = response.two_strike_data || [];
                    
                    displayAnalysisTable(3, threeStrikeData);
                    displayAnalysisTable(2, twoStrikeData);
                    updateStatistics();
                } else {
                    showNoData(3, response.message || 'No data found');
                    showNoData(2, response.message || 'No data found');
                    resetStatistics();
                }
                
                toggleLoading(3, false);
                toggleLoading(2, false);
            },
            error: function (xhr) {
                console.error('Analysis Error:', xhr);
                showNoData(3, 'Error loading data');
                showNoData(2, 'Error loading data');
                resetStatistics();
                toggleLoading(3, false);
                toggleLoading(2, false);
            }
        });
    }

    function displayAnalysisTable(tableId, data) {
        const tbodyId = tableId === 3 ? 'analysis-tbody-3' : 'analysis-tbody-2';
        
        if (!data || data.length === 0) {
            showNoData(tableId, 'No data available');
            return;
        }

        let html = '';
        
        data.forEach(function (row, index) {
            let conditionBadge = '';
            let conditionClass = '';
            
            if (row.oi_condition) {
                if (row.oi_condition.includes('CE ↑ + PE ↓')) {
                    conditionClass = 'condition-ce-up-pe-down';
                } else if (row.oi_condition.includes('CE ↓ + PE ↑')) {
                    conditionClass = 'condition-ce-down-pe-up';
                } else if (row.oi_condition.includes('Both ↑')) {
                    conditionClass = 'condition-both-up';
                } else if (row.oi_condition.includes('Both ↓')) {
                    conditionClass = 'condition-both-down';
                } else {
                    conditionClass = 'condition-flat';
                }
                conditionBadge = `<span class="${conditionClass}">${row.oi_condition}</span>`;
            } else {
                conditionBadge = '<span class="condition-flat">N/A</span>';
            }
            
            let ratioBadge = `<span class="ratio-badge">${row.pe_ce_ratio}</span>`;
            
            let ratioInterpretClass = row.ratio_interpretation === 'Put Writing' ? 'interpretation-put-writing' : 
                                row.ratio_interpretation === 'Call Writing' ? 'interpretation-call-writing' : 
                                'interpretation-balanced';
            
            let sentimentBadge = row.final_sentiment === 'BULLISH' ? '<span class="sentiment-strong-bullish">🟢 BULLISH</span>' :
                    row.final_sentiment === 'BEARISH' ? '<span class="sentiment-strong-bearish">🔴 BEARISH</span>' :
                    '<span class="sentiment-neutral">⚪ NEUTRAL</span>';
            
            // ✅ Strong Signal badge
            let strongSignalBadge = '';
            if (row.strong_signal) {
                if (row.strong_signal.includes('PE')) {
                    // Red box
                    strongSignalBadge = '<span class="sentiment-strong-bearish">' 
                        + row.strong_signal + 
                        '</span>';
                } else if (row.strong_signal.includes('CE')) {
                    // Green box
                    strongSignalBadge = '<span class="sentiment-strong-bullish">' 
                        + row.strong_signal + 
                        '</span>';
                } else {
                    strongSignalBadge = '<span class="sentiment-neutral">' 
                        + row.strong_signal + 
                        '</span>';
                }
            }
            
            let actionBadge = row.trade_action === 'BUY CE' ? '<span class="action-buy-ce">📈 CE</span>' :
                            row.trade_action === 'BUY PE' ? '<span class="action-buy-pe">📉 PE</span>' :
                            '<span class="action-both">⏸️ WAIT</span>';
            
            let ceOiChangeClass = row.ce_oi_change_pct > 0 ? 'text-success' : 'text-danger';
            let peOiChangeClass = row.pe_oi_change_pct > 0 ? 'text-success' : 'text-danger';
            let futOiChangeClass = row.fut_oi_change_pct > 0 ? 'text-success' : 'text-danger';

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.date}</strong></td>
                    <td><strong style="color: #667eea;">${row.symbol}</strong></td>
                    <td><strong>${row.ce_oi.toLocaleString()}</strong></td>
                    <td class="${ceOiChangeClass}"><strong>${row.ce_oi_change_pct > 0 ? '+' : ''}${row.ce_oi_change_pct}%</strong></td>
                    <td><strong>${row.pe_oi.toLocaleString()}</strong></td>
                    <td class="${peOiChangeClass}"><strong>${row.pe_oi_change_pct > 0 ? '+' : ''}${row.pe_oi_change_pct}%</strong></td>
                    <td>${conditionBadge}</td>
                    <td><span class="interpretation-text">${row.oi_reason || 'N/A'}</span></td>
                    <td>${ratioBadge}<br><span class="${ratioInterpretClass}">${row.ratio_interpretation}</span></td>
                    <td class="${futOiChangeClass}"><strong>${row.fut_oi_change_pct > 0 ? '+' : ''}${row.fut_oi_change_pct}%</strong></td>
                    <td>${sentimentBadge}</td>
                    <td>${strongSignalBadge}</td>
                    <td>${actionBadge}</td>
                </tr>
            `;
        });

        $('#' + tbodyId).html(html);
    }

    function updateStatistics() {
        $('#total_records_3').text(threeStrikeData.length);
        $('#total_records_2').text(twoStrikeData.length);
        
        const allData = [...threeStrikeData, ...twoStrikeData];
        $('#bullish_count').text(allData.filter(r => r.final_sentiment === 'BULLISH').length);
        $('#bearish_count').text(allData.filter(r => r.final_sentiment === 'BEARISH').length);
    }

    function resetStatistics() {
        $('#total_records_3, #total_records_2, #bullish_count, #bearish_count').text('0');
    }

    function showNoData(tableId, message) {
        const tbodyId = tableId === 3 ? 'analysis-tbody-3' : 'analysis-tbody-2';
        $('#' + tbodyId).html(`
            <tr>
                <td colspan="14" class="text-center py-5">
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
        $('#symbol_filter, #action_filter').val('');
        
        threeStrikeData = [];
        twoStrikeData = [];
        showNoData(3, 'Click "View Data"');
        showNoData(2, 'Click "View Data"');
        resetStatistics();
        setTimeout(() => runAnalysis(), 300);
    }

    $('#run_analysis').click(() => runAnalysis());
    $('#reset_filters').click(() => resetFilters());
</script>
@endpush