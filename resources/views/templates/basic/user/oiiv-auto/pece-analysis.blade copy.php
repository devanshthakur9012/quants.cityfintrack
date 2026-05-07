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

    /* Profit column styles */
    .profit-positive {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        font-weight: 700;
    }

    .profit-negative {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        font-weight: 700;
    }

    .profit-neutral {
        background: #e9ecef;
        color: #495057;
    }

    .text-info strong {
        text-shadow: 0 0 8px rgba(23, 162, 184, 0.3);
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .custom--table {
        min-width: 2200px;
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
        /* background: #f8f9fa; */
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

    /* ✅ Calculate P/L Button */
    .btn-calculate-profit {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        border: none;
        padding: 12px 24px;
        font-weight: 700;
        font-size: 14px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(240, 147, 251, 0.4);
        transition: all 0.3s ease;
    }

    .btn-calculate-profit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(240, 147, 251, 0.6);
        color: white;
    }

    .btn-calculate-profit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* ✅ NEW: Condition Badge Styles */
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

.custom--table {
    min-width: 2300px; /* ✅ UPDATED from 2200px to accommodate new column */
}
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }} <span class="new-feature-badge">NEW</span></h4>
                    <p>PE OI / CE OI Ratio Analysis with Trade Action Signals & Profit Calculation</p>
                </div>
                <div>
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
            <h6 style="color: white; margin-bottom: 10px; font-size: 14px;"><i class="fas fa-info-circle"></i> <strong>CE/PE OI Change Analysis Logic:</strong></h6>
            
            <div class="row mb-2">
                <div class="col-md-3">
                    <small style="font-size: 11px;"><strong>📊 CE/PE OI Analysis</strong></small>
                    <ul style="font-size: 10px; margin-top: 5px;">
                        <li><strong>CE ↑ + PE ↓</strong> → BEARISH (Call buildup + Put unwinding)</li>
                        <li><strong>CE ↓ + PE ↑</strong> → BULLISH (Call unwinding + Put buildup)</li>
                        <li><strong>Both ↑</strong> → Compare: CE% > PE% = BEARISH, else BULLISH</li>
                        <li><strong>Both ↓</strong> → Compare: CE% < PE% = BULLISH, else BEARISH</li>
                    </ul>
                </div>
                
                <div class="col-md-3">
                    <small style="font-size: 11px;"><strong>📈 PE/CE Ratio = PE_OI / CE_OI</strong></small>
                    <ul style="font-size: 10px; margin-top: 5px;">
                        <li>Ratio > 1.2 → Put Writing → <strong>Bullish</strong></li>
                        <li>Ratio < 0.8 → Call Writing → <strong>Bearish</strong></li>
                        <li>0.8 - 1.2 → Balanced OI → <strong>Neutral</strong></li>
                    </ul>
                </div>
                
                <div class="col-md-3">
                    <small style="font-size: 11px;"><strong>🔮 Futures OI View:</strong></small>
                    <ul style="font-size: 10px; margin-top: 5px;">
                        <li>FUT OI > +5% → <strong>Strong Build-up</strong></li>
                        <li>FUT OI < -5% → <strong>Position Unwinding</strong></li>
                        <li>Otherwise → <strong>Normal</strong></li>
                    </ul>
                </div>
                
                <div class="col-md-3">
                    <small style="font-size: 11px;"><strong>🎯 Trade Action:</strong></small>
                    <ul style="font-size: 10px; margin-top: 5px;">
                        <li>BULLISH → <strong style="color: #90EE90;">BUY CE</strong></li>
                        <li>BEARISH → <strong style="color: #FFB6C1;">BUY PE</strong></li>
                        <li>NEUTRAL → <strong style="color: #FFD700;">WAIT</strong></li>
                    </ul>
                </div>
            </div>
            
            <hr style="border-color: rgba(255,255,255,0.3); margin: 10px 0;">
            <small style="font-size: 10px;"><i class="fas fa-lightbulb"></i> <strong>Note:</strong> This analysis uses direct CE/PE OI % changes (NEW clean logic) for signal generation.</small>
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
                    <button type="button" id="calculate_profit" class="btn btn-calculate-profit btn-lg" style="min-width: 180px;" disabled>
                        <i class="fas fa-calculator"></i> Calculate P/L
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg" style="min-width: 150px; font-size: 13px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-3">
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Total Records</small>
                    <strong id="total_records" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>BUY CE</small>
                    <strong id="buy_ce_count" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #dc3545;">
                    <small>BUY PE</small>
                    <strong id="buy_pe_count" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #ffc107;">
                    <small>WAIT</small>
                    <strong id="both_count" style="color: #ffc107;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>Exit P/L</small>
                    <strong id="total_exit_profit" style="color: #28a745;">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #17a2b8;">
                    <small>Highest P/L</small>
                    <strong id="total_highest_profit" style="color: #17a2b8;">₹0</strong>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>BULLISH</small>
                    <strong id="strong_bullish_count" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #dc3545;">
                    <small>BEARISH</small>
                    <strong id="strong_bearish_count" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #007bff;">
                    <small>Investment</small>
                    <strong id="total_investment" style="color: #007bff;">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>Winners</small>
                    <strong id="winning_trades" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #ffc107;">
                    <small>Exit ROI %</small>
                    <strong id="exit_roi" style="color: #ffc107;">0%</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #17a2b8;">
                    <small>Best ROI %</small>
                    <strong id="highest_roi" style="color: #17a2b8;">0%</strong>
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
                            <th>CE OI</th>
                            <th>CE %</th>
                            <th>PE OI</th>
                            <th>PE %</th>
                            <th>Strength</th>
                            <th>Condition</th>
                            <th>Ratio</th>
                            <th>Interpretation</th>
                            <th>FUT %</th>
                            <th>Sentiment</th>
                            <th>Strong</th>
                            <th>Action</th>
                            <th>FUT Today</th>
                            <th>FUT Prev</th>
                            <th>FUT Δ</th>
                            <th>FUT Δ%</th>
                            <th>FUT Signal</th>
                            <th>Option</th>
                            <th>Investment</th>
                            <th>Entry ₹</th>
                            <th>Exit ₹</th>
                            <th>High ₹</th>
                            <th>Exit P/L</th>
                            <th>High P/L</th>
                            <th>Exit ROI</th>
                            <th>High ROI</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="23" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-chart-pie" style="font-size: 3rem; opacity: 0.5;"></i>
                                    <p style="font-size: 1.1rem; margin-top: 20px;">Click <strong>"View Data"</strong> to load signals</p>
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
    let profitCalculated = false;

    function toggleLoading(show, message = 'Loading data...') {
        if (show) {
            $('#loading-overlay .loading-text').text(message);
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
            url: '{{ route("oiiv-auto.symbols") }}',
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

        toggleLoading(true, 'Loading signals...');
        analysisData = [];
        profitCalculated = false;
        $('#calculate_profit').prop('disabled', true);

        $.ajax({
            url: '{{ route("oiiv-auto.analyze-pece") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                filter_action: filterAction
            },
            success: function (response) {
                console.log('Analysis Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    analysisData = response.data;
                    displayAnalysisTable();
                    updateStatistics();
                    $('#calculate_profit').prop('disabled', false);
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

    function calculateProfit() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        const selectedSymbols = $('#symbol_filter').val() || [];
        const filterAction = $('#action_filter').val();

        if (!fromDate || !toDate) {
            alert('Please select both dates');
            return;
        }

        toggleLoading(true, 'Calculating profits... This may take a while...');
        $('#calculate_profit').prop('disabled', true);

        $.ajax({
            url: '{{ route("oiiv-auto.calculate-bulk-profit") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                filter_action: filterAction
            },
            success: function (response) {
                console.log('Profit Calculation Response:', response);
                
                if (response.success && response.data) {
                    // Merge profit data with existing analysis data
                    mergeProfitData(response.data);
                    displayAnalysisTable();
                    updateStatisticsWithProfit(response.summary);
                    profitCalculated = true;
                } else {
                    alert('⚠️ No profit data available');
                }
                
                toggleLoading(false);
                $('#calculate_profit').prop('disabled', false);
            },
            error: function (xhr) {
                console.error('Profit Calculation Error:', xhr);
                alert('❌ Error calculating profit. Check console for details.');
                toggleLoading(false);
                $('#calculate_profit').prop('disabled', false);
            }
        });
    }

    function mergeProfitData(profitData) {
        // Create a map for quick lookup
        const profitMap = {};
        profitData.forEach(profit => {
            const key = `${profit.date}_${profit.symbol}`;
            profitMap[key] = profit;
        });

        // Merge with analysisData
        analysisData.forEach(row => {
            const key = `${row.date}_${row.symbol}`;
            if (profitMap[key]) {
                Object.assign(row, {
                    has_profit_data: true,
                    option_symbol: profitMap[key].option_symbol,
                    investment: profitMap[key].investment,
                    entry_price: profitMap[key].buy_price,
                    exit_price: profitMap[key].sell_price,
                    highest_price: profitMap[key].highest_price,
                    profit_loss: profitMap[key].profit_loss,
                    highest_profit: profitMap[key].highest_profit,
                    return_percent: profitMap[key].return_percent,
                    highest_return_percent: profitMap[key].highest_return_percent
                });
            }
        });
    }

    function displayAnalysisTable() {
        if (!analysisData || analysisData.length === 0) return;

        let html = '';
        
        analysisData.forEach(function (row, index) {
            // ===== FORMAT OI CONDITION BADGE =====
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
            
            // ===== PE/CE RATIO BADGE =====
            let ratioBadge = `<span class="ratio-badge">${row.pe_ce_ratio}</span>`;
            
            // ===== INTERPRETATION CLASS =====
            let interpretClass = row.oi_interpretation === 'Put Writing' ? 'interpretation-put-writing' : 
                                row.oi_interpretation === 'Call Writing' ? 'interpretation-call-writing' : 
                                'interpretation-balanced';
            
            // ===== SENTIMENT BADGE =====
            let sentimentBadge = row.final_sentiment === 'BULLISH' ? '<span class="sentiment-strong-bullish">🟢 BULLISH</span>' :
                    row.final_sentiment === 'BEARISH' ? '<span class="sentiment-strong-bearish">🔴 BEARISH</span>' :
                    '<span class="sentiment-neutral">⚪ NEUTRAL</span>';
            
            // ===== ACTION BADGE =====
            let actionBadge = row.trade_action === 'BUY CE' ? '<span class="action-buy-ce">📈 CE</span>' :
                            row.trade_action === 'BUY PE' ? '<span class="action-buy-pe">📉 PE</span>' :
                            '<span class="action-both">⏸️ WAIT</span>';
            
            // ===== OI CHANGE CLASSES =====
            let ceOiChangeClass = row.ce_oi_change_pct > 0 ? 'text-success' : 'text-danger';
            let peOiChangeClass = row.pe_oi_change_pct > 0 ? 'text-success' : 'text-danger';
            let futOiChangeClass = row.fut_oi_change_pct > 0 ? 'text-success' : 'text-danger';

            // ===== STRONGER SIDE BADGE =====
            let strongerBadge = '';
            if (row.stronger_side === 'CE') {
                strongerBadge = '<span class="badge badge-warning" style="font-size: 10px; font-weight: 700; color:#00bf63;">CE 💪</span>';
            } else if (row.stronger_side === 'PE') {
                strongerBadge = '<span class="badge badge-info" style="font-size: 10px; font-weight: 700; color:#fb1d28">PE 💪</span>';
            } else {
                strongerBadge = '<span class="badge badge-secondary" style="font-size: 10px;">EQUAL</span>';
            }

            // ===== NEW: SIGNAL STRENGTH LOGIC =====
            // let signalStrengthBadge = '';

            // if (row.ce_oi_change_pct < -10 && row.pe_oi_change_pct > 20) {
            //     signalStrengthBadge = '<span class="signal-strong-bullish">🔥 STRONG BULLISH</span>';
            // }
            // else if (row.ce_oi_change_pct > 20 && row.pe_oi_change_pct < -10) {
            //     signalStrengthBadge = '<span class="signal-strong-bearish">🔥 STRONG BEARISH</span>';
            // }
            // else {
            //     signalStrengthBadge = '<span class="signal-normal">Normal</span>';
            // }
            
            // ===== COMPLETE CE/PE SIGNAL LOGIC WITH STRENGTH RANK =====

            let signalStrengthBadge = '';
            let ce = row.ce_oi_change_pct || 0;
            let pe = row.pe_oi_change_pct || 0;

            let diff = Math.abs(ce - pe);
            let closeThreshold = 5;

            // ===== Determine Strength Rank =====
            let strengthRank = '';
            if (diff > 40) strengthRank = '1';
            else if (diff > 25) strengthRank = '2';
            else if (diff > 10) strengthRank = '3';
            else if (diff > 5) strengthRank = '4';
            else strengthRank = '';

            // ===============================
            // CASE 1: CE negative + PE positive → Bullish
            // ===============================
            if (ce < 0 && pe > 0) {
                signalStrengthBadge = strengthRank
                    ? `<span class="signal-strong-bullish">🟢 BULLISH (${strengthRank})</span>`
                    : '<span class="signal-normal">Normal</span>';
            }

            // ===============================
            // CASE 2: CE positive + PE negative → Bearish
            // ===============================
            else if (ce > 0 && pe < 0) {
                signalStrengthBadge = strengthRank
                    ? `<span class="signal-strong-bearish">🔴 BEARISH (${strengthRank})</span>`
                    : '<span class="signal-normal">Normal</span>';
            }

            // ===============================
            // CASE 3: BOTH POSITIVE
            // ===============================
            else if (ce > 0 && pe > 0) {

                if (diff <= closeThreshold) {
                    signalStrengthBadge = '<span class="signal-normal">Normal</span>';
                }
                else if (ce > pe) {
                    signalStrengthBadge =
                        `<span class="signal-strong-bearish">🔴 BEARISH (${strengthRank})</span>`;
                }
                else {
                    signalStrengthBadge =
                        `<span class="signal-strong-bullish">🟢 BULLISH (${strengthRank})</span>`;
                }
            }

            // ===============================
            // CASE 4: BOTH NEGATIVE
            // ===============================
            else if (ce < 0 && pe < 0) {

                if (diff <= closeThreshold) {
                    signalStrengthBadge = '<span class="signal-normal">Normal</span>';
                }
                else if (ce < pe) {
                    signalStrengthBadge =
                        `<span class="signal-strong-bullish">🟢 BULLISH (${strengthRank})</span>`;
                }
                else {
                    signalStrengthBadge =
                        `<span class="signal-strong-bearish">🔴 BEARISH (${strengthRank})</span>`;
                }
            }

            // ===============================
            else {
                signalStrengthBadge = '<span class="signal-normal">Normal</span>';
            }

            let futPriceToday = row.fut_price_today > 0 
                ? `<strong>₹${row.fut_price_today.toFixed(2)}</strong>` 
                : '<span class="text-muted">N/A</span>';

            let futPricePrev = row.fut_price_prev > 0 
                ? `₹${row.fut_price_prev.toFixed(2)}` 
                : '<span class="text-muted">N/A</span>';

            let futPriceChange = row.fut_price_change || 0;
            let futPriceChangeClass = futPriceChange >= 0 ? 'text-success' : 'text-danger';
            let futPriceChangeDisplay = `<strong class="${futPriceChangeClass}">
                ${futPriceChange >= 0 ? '+' : ''}₹${Math.abs(futPriceChange).toFixed(2)}
            </strong>`;

            let futPriceChangePct = row.fut_price_change_pct || 0;
            let futPriceChangePctClass = futPriceChangePct >= 0 ? 'text-success' : 'text-danger';
            let futPriceChangePctDisplay = `<strong class="${futPriceChangePctClass}">
                ${futPriceChangePct >= 0 ? '+' : ''}${futPriceChangePct.toFixed(2)}%
            </strong>`;

            // FUT Signal Badge
            let futSignalBadge = '';
            if (row.fut_price_signal === 'BULLISH') {
                futSignalBadge = '<span class="sentiment-strong-bullish">🟢 BULL</span>';
            } else if (row.fut_price_signal === 'BEARISH') {
                futSignalBadge = '<span class="sentiment-strong-bearish">🔴 BEAR</span>';
            } else if (row.fut_price_signal === 'NEUTRAL') {
                futSignalBadge = '<span class="sentiment-neutral">⚪ FLAT</span>';
            } else {
                futSignalBadge = '<span class="text-muted">N/A</span>';
            }

            
            // ===== PROFIT COLUMNS =====
            let profitColumns = '';
            if (row.has_profit_data && row.option_symbol && row.option_symbol !== 'N/A') {
                let exitPLClass = row.profit_loss >= 0 ? 'text-success' : 'text-danger';
                let highPLClass = row.highest_profit >= 0 ? 'text-success' : 'text-danger';
                
                profitColumns = `
                    <td><small>${row.option_symbol}</small></td>
                    <td><strong>₹${(row.investment || 0).toLocaleString()}</strong></td>
                    <td>₹${(row.entry_price || 0).toFixed(2)}</td>
                    <td>₹${(row.exit_price || 0).toFixed(2)}</td>
                    <td class="text-info"><strong>₹${(row.highest_price || 0).toFixed(2)}</strong></td>
                    <td class="${exitPLClass}"><strong>${row.profit_loss >= 0 ? '+' : ''}₹${(row.profit_loss || 0).toLocaleString()}</strong></td>
                    <td class="${highPLClass}"><strong>${row.highest_profit >= 0 ? '+' : ''}₹${(row.highest_profit || 0).toLocaleString()}</strong></td>
                    <td class="${exitPLClass}">${row.return_percent >= 0 ? '+' : ''}${(row.return_percent || 0).toFixed(2)}%</td>
                    <td class="${highPLClass}">${row.highest_return_percent >= 0 ? '+' : ''}${(row.highest_return_percent || 0).toFixed(2)}%</td>
                `;
            } else {
                profitColumns = `<td colspan="9" class="text-center text-muted"><small>Click "Calculate P/L"</small></td>`;
            }

            // ===== BUILD TABLE ROW =====
            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.date}</strong></td>
                    <td><strong style="color: #667eea;">${row.symbol}</strong></td>
                    <td><strong>${row.ce_oi.toLocaleString()}</strong></td>
                    <td class="${ceOiChangeClass}"><strong>${row.ce_oi_change_pct > 0 ? '+' : ''}${row.ce_oi_change_pct}%</strong></td>
                    <td><strong>${row.pe_oi.toLocaleString()}</strong></td>
                    <td class="${peOiChangeClass}"><strong>${row.pe_oi_change_pct > 0 ? '+' : ''}${row.pe_oi_change_pct}%</strong></td>
                    <td>${signalStrengthBadge}</td>
                    <td>${conditionBadge}</td>
                    <td>${ratioBadge}</td>
                    <td><span class="${interpretClass}">${row.oi_interpretation}</span></td>
                    <td class="${futOiChangeClass}"><strong>${row.fut_oi_change_pct > 0 ? '+' : ''}${row.fut_oi_change_pct}%</strong></td>
                    <td>${sentimentBadge}</td>
                    <td>${strongerBadge}</td>
                    <td>${actionBadge}</td>
                    <td>${futPriceToday}</td>
                    <td>${futPricePrev}</td>
                    <td>${futPriceChangeDisplay}</td>
                    <td>${futPriceChangePctDisplay}</td>
                    <td>${futSignalBadge}</td>
                    ${profitColumns}
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
        $('#buy_ce_count').text(analysisData.filter(r => r.trade_action === 'BUY CE').length);
        $('#buy_pe_count').text(analysisData.filter(r => r.trade_action === 'BUY PE').length);
        $('#both_count').text(analysisData.filter(r => r.trade_action === 'WAIT').length);
        $('#strong_bullish_count').text(analysisData.filter(r => r.final_sentiment === 'BULLISH').length);
        $('#strong_bearish_count').text(analysisData.filter(r => r.final_sentiment === 'BEARISH').length);

    }

    function updateStatisticsWithProfit(summary) {
        $('#total_investment').text('₹' + summary.total_investment.toLocaleString());
        $('#total_exit_profit').text('₹' + summary.total_profit_loss.toLocaleString()).css('color', summary.total_profit_loss >= 0 ? '#28a745' : '#dc3545');
        $('#total_highest_profit').text('₹' + summary.total_highest_profit.toLocaleString()).css('color', summary.total_highest_profit >= 0 ? '#17a2b8' : '#dc3545');
        $('#winning_trades').text(summary.winning_trades);
        $('#exit_roi').text(summary.roi_percent + '%').css('color', summary.roi_percent >= 0 ? '#28a745' : '#dc3545');
        $('#highest_roi').text(summary.highest_roi_percent + '%').css('color', summary.highest_roi_percent >= 0 ? '#17a2b8' : '#dc3545');
    }

    function resetStatistics() {
        $('#total_records, #buy_ce_count, #buy_pe_count, #both_count, #strong_bullish_count, #strong_bearish_count, #winning_trades').text('0');
        $('#total_investment, #total_exit_profit, #total_highest_profit').text('₹0');
        $('#exit_roi, #highest_roi').text('0%');
    }

    function showNoData(message) {
        $('#analysis-tbody').html(`
            <tr>
                <td colspan="31" class="text-center py-5"> <!-- ✅ 14 base + 6 new + 9 profit = 29 -->
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
        analysisData = [];
        profitCalculated = false;
        showNoData('Click "View Data" to load signals');
        resetStatistics();
        setTimeout(() => runAnalysis(), 300);
    }

    $('#run_analysis').click(() => runAnalysis());
    $('#calculate_profit').click(() => calculateProfit());
    $('#reset_filters').click(() => resetFilters());
</script>
@endpush