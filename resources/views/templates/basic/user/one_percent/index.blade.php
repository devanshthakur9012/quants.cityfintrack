@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: left !important;
        padding: 8px !important;
        font-size: 0.85rem;
    }

    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(19, 45, 57, 0.9);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .badge-buy-ce { 
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important; 
        color: white !important; 
        padding: 5px 6px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }
    
    .badge-buy-pe { 
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%) !important; 
        color: white !important; 
        padding: 5px 6px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }
    
    .badge-no-trade { 
        background-color: #6c757d !important; 
        color: white !important; 
        padding: 6px 12px; 
        border-radius: 6px;
        font-weight: 600;
    }

    .badge-profit {
        background-color: #28a745 !important;
        color: white !important;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
    }

    .badge-loss {
        background-color: #dc3545 !important;
        color: white !important;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
    }

    .filter-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .filter-section label {
        color: white !important;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .filter-section .form-control {
        border: 2px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.9);
        color: #333;
    }

    .filter-section .form-control:focus {
        border-color: #fff;
        box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
    }

    .stats-box {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        border-left: 5px solid #3498db;
        margin-bottom: 15px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .stats-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
    }

    .stats-box small {
        display: block;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stats-box strong {
        display: block;
        font-size: 1.8rem;
        margin-top: 5px;
        font-weight: 700;
    }

    .stats-box.ce-stats {
        border-left-color: #28a745;
    }

    .stats-box.pe-stats {
        border-left-color: #dc3545;
    }

    .stats-box.no-trade-stats {
        border-left-color: #6c757d;
    }

    .stats-box.profit-stats {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-left-color: #fff;
    }

    .stats-box.profit-stats small {
        color: rgba(255,255,255,0.9);
    }

    .exit-time-selector {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
        color: white;
    }

    .exit-time-selector label {
        color: white !important;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .exit-time-selector .form-control {
        border: 2px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.9);
        color: #333;
    }

    .exit-time-selector .alert {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
    }

    .no-data-message {
        padding: 60px 20px;
        text-align: center;
        color: #6c757d;
    }

    .no-data-message i {
        font-size: 4rem;
        margin-bottom: 20px;
        display: block;
        opacity: 0.5;
    }

    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .page-header h4 {
        margin: 0;
        font-weight: 700;
        font-size: 1.8rem;
    }

    .page-header p {
        margin: 10px 0 0 0;
        opacity: 0.9;
    }

    .btn-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        font-weight: 600;
        padding: 10px 20px;
        border-radius: 8px;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        color: white;
    }

    .price-positive {
        color: #28a745;
        font-weight: 700;
    }

    .price-negative {
        color: #dc3545;
        font-weight: 700;
    }

    .table-responsive {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .table thead {
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        color: white;
    }

    .table tbody tr:hover {
        background-color: rgba(102, 126, 234, 0.05);
    }

    .oi-badge {
        background-color: #17a2b8;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .oi-change-positive {
        color: #28a745;
        font-weight: 600;
    }

    .oi-change-negative {
        color: #dc3545;
        font-weight: 600;
    }

    .highest-price-badge {
        background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        color: #333;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 700;
        display: inline-block;
        margin-top: 4px;
    }

    /* ✅ NEW - Signal badges */
    .signal-buildup {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 3px 6px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .signal-unwinding {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        color: white;
        padding: 3px 6px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }

    .signal-bullish {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 3px 6px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .signal-bearish {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        color: white;
        padding: 3px 6px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }

    .signal-neutral {
        background-color: #6c757d;
        color: white;
        padding: 3px 6px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
    }

    .strength-badge {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-left: 4px;
    }

    .market-bias-strong-bullish {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 800;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 3px 6px rgba(40, 167, 69, 0.4);
        text-transform: uppercase;
    }

    .market-bias-strong-bearish {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 800;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 3px 6px rgba(220, 53, 69, 0.4);
        text-transform: uppercase;
    }

    .market-bias-moderate-bullish {
        background-color: #28a745;
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .market-bias-moderate-bearish {
        background-color: #dc3545;
        color: white;
        padding: 3px 6px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }

    .market-bias-mixed {
        background-color: #ffc107;
        color: #333;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
    }

    .custom--table thead th, .custom--table tbody td{
        font-size: 10px !important;
    }

    .iv-box {
        background: #f8f9fa;
        padding: 8px;
        border-radius: 6px;
        border-left: 3px solid #6c757d;
        min-width: 80px;
    }

    .iv-box.iv-increased {
        border-left-color: #dc3545;
        background: #fff5f5;
    }

    .iv-box.iv-decreased {
        border-left-color: #28a745;
        background: #f0fff4;
    }

    .iv-value {
        font-size: 12px;
        font-weight: 700;
        display: block;
        margin-bottom: 2px;
    }

    .iv-change {
        font-size: 9px;
        display: block;
        opacity: 0.9;
    }

    /* Update table column width for IV columns */
    .custom--table thead th:nth-child(11),  /* CE IV */
    .custom--table thead th:nth-child(12) { /* PE IV */
        min-width: 100px;
        text-align: center !important;
    }

    .custom--table tbody td:nth-child(11),
    .custom--table tbody td:nth-child(12) {
        text-align: center !important;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }}</h4>
                    <p>Detect stocks that move <strong id="current-percentage-display">±1%</strong> from day opening price with OI Analysis & P/L tracking</p>
                </div>
                <div>
                    <a href="{{ route('symbols.analysis') }}" class="btn btn-light me-2">
                        <i class="fas fa-chart-line"></i> Technical Analysis
                    </a>
                    <a href="{{ route('symbols.backtesting') }}" class="btn btn-light">
                        <i class="fas fa-history"></i> Backtesting
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row mb-3">
                <div class="col-md-2">
                    <label for="from_date"><i class="fas fa-calendar-alt"></i> From Date:</label>
                    <input type="date" id="from_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="to_date"><i class="fas fa-calendar-alt"></i> To Date:</label>
                    <input type="date" id="to_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="percentage_input"><i class="fas fa-percentage"></i> Move % Threshold:</label>
                    <select id="percentage_input" class="form-control">
                        <option value="0.5">0.5%</option>
                        <option value="0.75">0.75%</option>
                        <option value="1.0" selected>1.0%</option>
                        <option value="1.25">1.25%</option>
                        <option value="1.5">1.5%</option>
                        <option value="2.0">2.0%</option>
                        <option value="2.5">2.5%</option>
                        <option value="3.0">3.0%</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="option_series_filter"><i class="fas fa-layer-group"></i> Option Series:</label>
                    <select id="option_series_filter" class="form-control">
                        <option value="current" selected>Current Series</option>
                        <option value="next">Next Series</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="symbol_filter"><i class="fas fa-filter"></i> Symbols (Optional):</label>
                    <select id="symbol_filter" class="form-control" multiple size="3">
                        @foreach($monitoredSymbols as $symbol)
                            <option value="{{ $symbol->trading_symbol }}">{{ $symbol->trading_symbol }}</option>
                        @endforeach
                    </select>
                    <small style="color: rgba(255,255,255,0.8);">Leave empty for all symbols</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 text-center">
                    <button type="button" id="run_analysis" class="btn btn-light btn-lg me-3" style="min-width: 180px;">
                        <i class="fas fa-play-circle"></i> Run Analysis
                    </button>
                    <button type="button" id="export_csv" class="btn btn-outline-light btn-lg me-3" style="min-width: 180px; display: none;">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg" style="min-width: 180px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Exit Time Selector -->
        <div class="exit-time-selector" id="exit-time-section" style="display: none;">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label for="exit_time_selector"><i class="fas fa-clock"></i> Select Exit Time:</label>
                    <select id="exit_time_selector" class="form-control">
                        <option value="15:15">3:15 PM (15:15)</option>
                        <option value="15:20">3:20 PM (15:20)</option>
                        <option value="15:25">3:25 PM (15:25)</option>
                        <option value="15:30" selected>3:30 PM (15:30)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="calculate_profit" class="btn btn-light btn-lg" style="min-width: 200px;">
                        <i class="fas fa-calculator"></i> Calculate P/L
                    </button>
                </div>
                <div class="col-md-6">
                    <div class="alert mb-0">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Buy @ Signal Time | Sell @ Exit Time | Highest = Best possible exit
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Total Days</small>
                    <strong id="total_days" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box ce-stats">
                    <small>CE Signals</small>
                    <strong id="ce_signals" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box pe-stats">
                    <small>PE Signals</small>
                    <strong id="pe_signals" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Total Investment</small>
                    <strong id="total_investment" class="text-dark">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Exit P/L</small>
                    <strong id="total_profit" class="text-dark">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box profit-stats">
                    <small>Best P/L</small>
                    <strong id="total_highest_profit">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Exit ROI</small>
                    <strong id="roi_percent" style="color: #ffc107;">0%</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box profit-stats">
                    <small>Best ROI</small>
                    <strong id="highest_roi_percent">0%</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Win Rate</small>
                    <strong id="win_rate" style="color: #17a2b8;">0%</strong>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div style="position: relative; min-height: 400px;">
            <div class="loading-overlay" id="loading-overlay" style="display: none;">
                <div class="spinner"></div>
            </div>

            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Symbol</th>
                            <th>Signal</th>
                            <th>Price</th>
                            <th>%</th>
                            <th>FUT Signal</th>     
                            <th>CE Signal</th>      
                            <th>PE Signal</th> 
                            <th>CE IV</th>          
                            <th>PE IV</th>          
                            <th>Option</th>
                            <th>Option</th>
                            <th>Buy</th>
                            <th>Sell</th>
                            <th>Best Price</th>
                            <th>Qty</th>
                            <th>Investment</th>
                            <th>Exit P/L</th>
                            <th>Best P/L</th>
                            <th>Exit ROI</th>
                            <th>Best ROI</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="20" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-chart-line"></i>
                                    <p style="font-size: 1.1rem;">Select date range and click <strong>"Run Analysis"</strong></p>
                                    <small style="display: block; margin-top: 10px; color: #999;">
                                        Analysis includes OI data (FUT/CE/PE) and highest price tracking
                                    </small>
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
    let profitData = [];

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    function runAnalysis() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        const selectedSymbols = $('#symbol_filter').val() || [];
        const optionSeries = $('#option_series_filter').val();
        const percentage = parseFloat($('#percentage_input').val());

        if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
        }

        $('#current-percentage-display').text('±' + percentage + '%');

        toggleLoading(true);
        analysisData = [];
        profitData = [];

        $.ajax({
            url: '{{ route("symbols.one-percent-analyze") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                option_series: optionSeries,
                percentage: percentage
            },
            success: function (response) {
                console.log('Analysis Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    analysisData = response.data;
                    displayAnalysisTable();
                    updateBasicStatistics();
                    $('#exit-time-section').show();
                    $('#export_csv').show();
                } else {
                    $('#analysis-tbody').html(`
                        <tr>
                            <td colspan="20" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                                    <p class="text-info">${response.message || 'No data found'}</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                    $('#exit-time-section').hide();
                    $('#export_csv').hide();
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Analysis Error:', error);
                $('#analysis-tbody').html(`
                    <tr>
                        <td colspan="19" class="text-center py-5">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                <p class="text-danger">Error running analysis</p>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function formatOI(oi) {
        if (oi >= 10000000) {
            return (oi / 10000000).toFixed(2) + 'Cr';
        } else if (oi >= 100000) {
            return (oi / 100000).toFixed(2) + 'L';
        } else if (oi >= 1000) {
            return (oi / 1000).toFixed(2) + 'K';
        }
        return oi.toString();
    }

    function formatOIChange(change) {
        const sign = change >= 0 ? '+' : '';
        const cssClass = change >= 0 ? 'oi-change-positive' : 'oi-change-negative';
        return `<span class="${cssClass}">${sign}${formatOI(change)}</span>`;
    }

    // function formatSignal(signal, type) {
    //     if (!signal || signal === 'N/A') {
    //         return '<span class="signal-neutral">N/A</span>';
    //     }

    //     let cssClass = '';
    //     let icon = '';
        
    //     // if (type === 'FUT') {
    //     //     if (signal === 'BUILDUP') {
    //     //         cssClass = 'signal-buildup';
    //     //         icon = '⬆️';
    //     //     } else if (signal === 'UNWINDING') {
    //     //         cssClass = 'signal-unwinding';
    //     //         icon = '⬇️';
    //     //     } else {
    //     //         cssClass = 'signal-neutral';
    //     //         icon = '➡️';
    //     //     }
    //     // } else if (type === 'CE' || type === 'PE') {
    //     //     if (signal === 'BULLISH') {
    //     //         cssClass = 'signal-bullish';
    //     //         icon = '⬆️';
    //     //     } else if (signal === 'BEARISH') {
    //     //         cssClass = 'signal-bearish';
    //     //         icon = '⬇️';
    //     //     } else {
    //     //         cssClass = 'signal-neutral';
    //     //         icon = '➡️';
    //     //     }
    //     // }

    //     if (type === 'FUT') {
    //         if (signal === 'BUILDUP') {
    //             cssClass = 'signal-bullish';
    //             icon = '⬆️';
    //             signal = 'BULLISH';
    //         } else if (signal === 'UNWINDING') {
    //             cssClass = 'signal-bearish';
    //             icon = '⬇️';
    //             signal = 'BEARISH';
    //         } else {
    //             cssClass = 'signal-neutral';
    //             icon = '➡️';
    //         }
    //     } else if (type === 'CE' || type === 'PE') {
    //         if (signal === 'BULLISH') {
    //             cssClass = 'signal-bearish';
    //             icon = '⬇️';
    //             signal = 'BEARISH';
    //         } else if (signal === 'BEARISH') {
    //             cssClass = 'signal-bullish';
    //             icon = '⬆️';
    //             signal = 'BULLISH';
    //         } else {
    //             cssClass = 'signal-neutral';
    //             icon = '➡️';
    //         }
    //     }

    //     return `<span class="${cssClass}">${signal} ${icon}</span>`;
    // }

    function formatSignal(signal, type) {
        if (!signal || signal === 'N/A' || signal === 'NEUTRAL') {
            return '<span class="signal-neutral">NEUTRAL</span>';
        }

        let cssClass = '';
        
        // ✅ Simple logic - backend already normalized everything
        if (signal === 'BULLISH') {
            cssClass = 'signal-bullish';
        } else if (signal === 'BEARISH') {
            cssClass = 'signal-bearish';
        } else {
            cssClass = 'signal-neutral';
        }

        return `<span class="${cssClass}">${signal}</span>`;
    }

    function formatMarketBias(bias) {
        if (!bias || bias === 'N/A') {
            return '<span class="signal-neutral">N/A</span>';
        }

        let cssClass = '';
        
        if (bias.includes('STRONG_BULLISH')) {
            cssClass = 'market-bias-strong-bullish';
        } else if (bias.includes('STRONG_BEARISH')) {
            cssClass = 'market-bias-strong-bearish';
        } else if (bias.includes('MODERATE_BULLISH')) {
            cssClass = 'market-bias-moderate-bullish';
        } else if (bias.includes('MODERATE_BEARISH')) {
            cssClass = 'market-bias-moderate-bearish';
        } else {
            cssClass = 'market-bias-mixed';
        }

        // Simplify display
        const displayText = bias
            .replace('STRONG_', '')
            .replace('MODERATE_', 'MOD ')
            .replace('MIXED_OR_RANGE', 'MIXED')
            .replace(/_/g, ' ');

        return `<span class="${cssClass}">${displayText}</span>`;
    }

    // ✅ NEW - Format IV data with percentage, absolute value, and trend
    function formatIV(ivData) {
        if (!ivData.iv || ivData.iv === null) {
            return '<span class="text-muted">N/A</span>';
        }

        // Convert decimal to percentage (0.1234 → 12.34%)
        const ivPercent = (ivData.iv * 100).toFixed(2);
        const ivChange = ivData.iv_change;
        const ivChangePct = ivData.iv_change_pct;

        // Determine color and trend icon
        let colorClass = 'text-secondary';
        let trendIcon = '';
        let changeText = '';

        // ✅ FIX: Proper null/undefined checks before using .toFixed()
        if (ivChange !== null && ivChange !== undefined && ivChangePct !== null && ivChangePct !== undefined) {
            if (ivChange > 0) {
                colorClass = 'text-danger';  // Red for increasing IV (bad for buyers)
                trendIcon = '▲';
            } else if (ivChange < 0) {
                colorClass = 'text-success'; // Green for decreasing IV (good for buyers)
                trendIcon = '▼';
            } else {
                colorClass = 'text-secondary';
                trendIcon = '→';
            }

            // Format change: +0.0050 (+4.22%)
            const sign = ivChange > 0 ? '+' : '';
            const absChangePercent = (Math.abs(ivChange) * 100).toFixed(2);
            
            // ✅ FIX: Safe conversion to fixed decimal with type checking
            const formattedChangePct = typeof ivChangePct === 'number' ? ivChangePct.toFixed(2) : '0.00';
            
            changeText = `<br><small class="${colorClass}" style="font-size: 9px;">
                ${sign}${absChangePercent}% (${sign}${formattedChangePct}%)
            </small>`;
        }

        return `
            <div style="text-align: center;">
                <strong class="${colorClass}" style="font-size: 11px;">
                    ${ivPercent}% ${trendIcon}
                </strong>
                ${changeText}
            </div>
        `;
    }

    // function displayAnalysisTable() {
    //     if (!analysisData || analysisData.length === 0) {
    //         return;
    //     }

    //     let html = '';
        
    //     analysisData.forEach(function (row, index) {
    //         if (row.signal === 'NO_TRADE') {
    //             return;
    //         }

    //         let signalClass = row.signal === 'BUY_CE' ? 'badge-buy-ce' : 'badge-buy-pe';
    //         let signalText = row.signal === 'BUY_CE' ? 'CE ▲' : 'PE ▼';
    //         const changePctClass = row.change_pct > 0 ? 'price-positive' : 'price-negative';
    //         const timeOnly = row.signal_time ? row.signal_time.split(' ')[1].substring(0, 5) : '-';

    //         // <td>${formatSignal(row.ce_signal, 'CE')}</td>
    //         // <td>${formatSignal(row.pe_signal, 'PE')}</td>
            
    //         html += `
    //             <tr>
    //                 <td><strong>${index + 1}</strong></td>
    //                 <td><strong>${row.date}</strong></td>
    //                 <td>${timeOnly}</td>
    //                 <td><strong style="color: #667eea;">${row.symbol}</strong></td>
    //                 <td><span class="badge ${signalClass}">${signalText}</span></td>
    //                 <td>₹${row.signal_price || '-'}</td>
    //                 <td class="${changePctClass}">
    //                     ${row.change_pct !== null ? (row.change_pct > 0 ? '+' : '') + row.change_pct + '%' : '-'}
    //                 </td>
    //                 <td>${formatSignal(row.fut_signal, 'FUT')}
    //                 </td>
                    
    //                 <td>${formatSignal(row.ce_signal, 'CE')}
    //                     ${row.ce_iv_change_pct !== null ? 
    //                         '<br><small class="' + (row.ce_iv_change_pct > 0 ? 'text-success' : 'text-danger') + '">' + 
    //                         'IV: ' + (row.ce_iv_change_pct > 0 ? '+' : '') + row.ce_iv_change_pct + '%</small>' 
    //                         : ''}
    //                 </td>
    //                 <td>${formatSignal(row.pe_signal, 'PE')}
    //                     ${row.pe_iv_change_pct !== null ? 
    //                         '<br><small class="' + (row.pe_iv_change_pct > 0 ? 'text-success' : 'text-danger') + '">' + 
    //                         'IV: ' + (row.pe_iv_change_pct > 0 ? '+' : '') + row.pe_iv_change_pct + '%</small>' 
    //                         : ''}
    //                 </td>
    //                 <td><strong>${row.option_symbol || '-'}</strong></td>
    //                 <td colspan="9" class="text-center text-muted">
    //                     <em>Click "Calculate P/L" to fetch prices</em>
    //                 </td>
    //             </tr>
    //         `;
    //                 // <td>${formatMarketBias(row.market_bias)}</td>
    //     });

    //     if (html === '') {
    //         html = `
    //             <tr>
    //                 <td colspan="20" class="text-center py-5">
    //                     <div class="no-data-message">
    //                         <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
    //                         <p class="text-info">All signals were NO_TRADE</p>
    //                     </div>
    //                 </td>
    //             </tr>
    //         `;
    //     }

    //     $('#analysis-tbody').html(html);
    // }

    // ✅ Update displayAnalysisTable function
    function displayAnalysisTable() {
        if (!analysisData || analysisData.length === 0) {
            return;
        }

        let html = '';
        
        analysisData.forEach(function (row, index) {
            if (row.signal === 'NO_TRADE') {
                return;
            }

            let signalClass = row.signal === 'BUY_CE' ? 'badge-buy-ce' : 'badge-buy-pe';
            let signalText = row.signal === 'BUY_CE' ? 'CE ▲' : 'PE ▼';
            const changePctClass = row.change_pct > 0 ? 'price-positive' : 'price-negative';
            const timeOnly = row.signal_time ? row.signal_time.split(' ')[1].substring(0, 5) : '-';

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.date}</strong></td>
                    <td>${timeOnly}</td>
                    <td><strong style="color: #667eea;">${row.symbol}</strong></td>
                    <td><span class="badge ${signalClass}">${signalText}</span></td>
                    <td>₹${row.signal_price || '-'}</td>
                    <td class="${changePctClass}">
                        ${row.change_pct !== null ? (row.change_pct > 0 ? '+' : '') + row.change_pct + '%' : '-'}
                    </td>
                    <td>${formatSignal(row.fut_signal, 'FUT')}</td>
                    <td>${formatSignal(row.ce_signal, 'CE')}</td>
                    <td>${formatSignal(row.pe_signal, 'PE')}</td>
                    <td>${formatIV({
                        iv: row.ce_iv,
                        iv_change: row.ce_iv_change,
                        iv_change_pct: row.ce_iv_change_pct
                    })}</td>
                    <td>${formatIV({
                        iv: row.pe_iv,
                        iv_change: row.pe_iv_change,
                        iv_change_pct: row.pe_iv_change_pct
                    })}</td>
                    <td><strong>${row.option_symbol || '-'}</strong></td>
                    <td colspan="9" class="text-center text-muted">
                        <em>Click "Calculate P/L" to fetch prices</em>
                    </td>
                </tr>
            `;
        });

        if (html === '') {
            html = `
                <tr>
                    <td colspan="22" class="text-center py-5">
                        <div class="no-data-message">
                            <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                            <p class="text-info">All signals were NO_TRADE</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        $('#analysis-tbody').html(html);
    }

    function calculateProfit() {
        if (!analysisData || analysisData.length === 0) {
            alert('No analysis data available. Please run analysis first.');
            return;
        }

        const exitTime = $('#exit_time_selector').val();
        toggleLoading(true);

        $.ajax({
            url: '{{ route("symbols.one-percent-profit") }}',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                signals: analysisData,
                exit_time: exitTime
            }),
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function (response) {
                console.log('Profit Response:', response);
                
                if (response.success && response.data) {
                    profitData = response.data;
                    displayProfitTable();
                    updateProfitStatistics(response.summary);
                } else {
                    alert(response.message || 'Error calculating profit');
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Profit Calculation Error:', error);
                alert('Error calculating profit. Check if option data is available.');
                toggleLoading(false);
            }
        });
    }

    // function displayProfitTable() {
    //     if (!profitData || profitData.length === 0) {
    //         return;
    //     }

    //     let html = '';
    //     let displayIndex = 1;
        
    //     analysisData.forEach(function (signal, index) {
    //         if (signal.signal === 'NO_TRADE') {
    //             return;
    //         }

    //         const profit = profitData.find(p => p.option_symbol === signal.option_symbol);
            
    //         if (!profit) {
    //             return;
    //         }

    //         let signalClass = signal.signal === 'BUY_CE' ? 'badge-buy-ce' : 'badge-buy-pe';
    //         let signalText = signal.signal === 'BUY_CE' ? 'CE ▲' : 'PE ▼';
    //         const changePctClass = signal.change_pct > 0 ? 'price-positive' : 'price-negative';
    //         const profitClass = profit.profit_loss >= 0 ? 'badge-profit' : 'badge-loss';
    //         const highestProfitClass = profit.highest_profit >= 0 ? 'badge-profit' : 'badge-loss';
    //         const profitSign = profit.profit_loss >= 0 ? '+' : '';
    //         const highestProfitSign = profit.highest_profit >= 0 ? '+' : '';
    //         const timeOnly = signal.signal_time ? signal.signal_time.split(' ')[1].substring(0, 5) : '-';

    //         const highestPrice = profit.highest_price || profit.sell_price || 0;
    //         const highestPriceTime = profit.highest_price_time || '-';
    //         const highestProfit = profit.highest_profit || profit.profit_loss || 0;
    //         const highestReturnPercent = profit.highest_return_percent || profit.return_percent || 0;

    //         // <td>${formatSignal(signal.ce_signal, 'CE')}</td>
    //         // <td>${formatSignal(signal.pe_signal, 'PE')}</td>

    //         html += `
    //             <tr>
    //                 <td><strong>${displayIndex++}</strong></td>
    //                 <td><strong>${signal.date}</strong></td>
    //                 <td>${timeOnly}</td>
    //                 <td><strong style="color: #667eea;">${signal.symbol}</strong></td>
    //                 <td><span class="badge ${signalClass}">${signalText}</span></td>
    //                 <td>₹${signal.signal_price}</td>
    //                 <td class="${changePctClass}">
    //                     ${(signal.change_pct > 0 ? '+' : '') + signal.change_pct}%
    //                 </td>
    //                 <td>${formatSignal(signal.fut_signal, 'FUT')}</td>
    //                 <td>${formatSignal(row.ce_signal, 'CE')}
    //                     ${row.ce_iv_change_pct !== null ? 
    //                         '<br><small class="' + (row.ce_iv_change_pct > 0 ? 'text-success' : 'text-danger') + '">' + 
    //                         'IV: ' + (row.ce_iv_change_pct > 0 ? '+' : '') + row.ce_iv_change_pct + '%</small>' 
    //                         : ''}
    //                 </td>
    //                 <td>${formatSignal(row.pe_signal, 'PE')}
    //                     ${row.pe_iv_change_pct !== null ? 
    //                         '<br><small class="' + (row.pe_iv_change_pct > 0 ? 'text-success' : 'text-danger') + '">' + 
    //                         'IV: ' + (row.pe_iv_change_pct > 0 ? '+' : '') + row.pe_iv_change_pct + '%</small>' 
    //                         : ''}
    //                 </td>
    //                 <td><strong>${profit.option_symbol}</strong></td>
    //                 <td><strong>₹${profit.buy_price}</strong></td>
    //                 <td><strong>₹${profit.sell_price}</strong></td>
    //                 <td>
    //                     <span class="highest-price-badge">₹${highestPrice}</span><br>
    //                     <small style="color: #666;">@ ${highestPriceTime}</small>
    //                 </td>
    //                 <td>${profit.quantity}</td>
    //                 <td><strong>₹${profit.investment.toLocaleString('en-IN')}</strong></td>
    //                 <td><span class="badge ${profitClass}">${profitSign}₹${Math.round(profit.profit_loss)}</span></td>
    //                 <td><span class="badge ${highestProfitClass}">${highestProfitSign}₹${Math.round(highestProfit)}</span></td>
    //                 <td><span class="badge ${profitClass}">${profitSign}${profit.return_percent}%</span></td>
    //                 <td><span class="badge ${highestProfitClass}">${highestProfitSign}${highestReturnPercent}%</span></td>
    //             </tr>
    //         `;
    //                 // <td>${formatMarketBias(signal.market_bias)}</td>
    //     });

    //     $('#analysis-tbody').html(html);
    // }

    function displayProfitTable() {
        if (!profitData || profitData.length === 0) {
            return;
        }

        let html = '';
        let displayIndex = 1;
        
        analysisData.forEach(function (signal, index) {
            if (signal.signal === 'NO_TRADE') {
                return;
            }

            const profit = profitData.find(p => p.option_symbol === signal.option_symbol);
            
            if (!profit) {
                return;
            }

            let signalClass = signal.signal === 'BUY_CE' ? 'badge-buy-ce' : 'badge-buy-pe';
            let signalText = signal.signal === 'BUY_CE' ? 'CE ▲' : 'PE ▼';
            const changePctClass = signal.change_pct > 0 ? 'price-positive' : 'price-negative';
            const profitClass = profit.profit_loss >= 0 ? 'badge-profit' : 'badge-loss';
            const highestProfitClass = profit.highest_profit >= 0 ? 'badge-profit' : 'badge-loss';
            const profitSign = profit.profit_loss >= 0 ? '+' : '';
            const highestProfitSign = profit.highest_profit >= 0 ? '+' : '';
            const timeOnly = signal.signal_time ? signal.signal_time.split(' ')[1].substring(0, 5) : '-';

            const highestPrice = profit.highest_price || profit.sell_price || 0;
            const highestPriceTime = profit.highest_price_time || '-';
            const highestProfit = profit.highest_profit || profit.profit_loss || 0;
            const highestReturnPercent = profit.highest_return_percent || profit.return_percent || 0;

            html += `
                <tr>
                    <td><strong>${displayIndex++}</strong></td>
                    <td><strong>${signal.date}</strong></td>
                    <td>${timeOnly}</td>
                    <td><strong style="color: #667eea;">${signal.symbol}</strong></td>
                    <td><span class="badge ${signalClass}">${signalText}</span></td>
                    <td>₹${signal.signal_price}</td>
                    <td class="${changePctClass}">
                        ${(signal.change_pct > 0 ? '+' : '') + signal.change_pct}%
                    </td>
                    <td>${formatSignal(signal.fut_signal, 'FUT')}</td>
                    <td>${formatSignal(signal.ce_signal, 'CE')}</td>
                    <td>${formatSignal(signal.pe_signal, 'PE')}</td>
                    <td>${formatIV({
                        iv: signal.ce_iv,
                        iv_change: signal.ce_iv_change,
                        iv_change_pct: signal.ce_iv_change_pct
                    })}</td>
                    <td>${formatIV({
                        iv: signal.pe_iv,
                        iv_change: signal.pe_iv_change,
                        iv_change_pct: signal.pe_iv_change_pct
                    })}</td>
                    <td><strong>${profit.option_symbol}</strong></td>
                    <td><strong>₹${profit.buy_price}</strong></td>
                    <td><strong>₹${profit.sell_price}</strong></td>
                    <td>
                        <span class="highest-price-badge">₹${highestPrice}</span><br>
                        <small style="color: #666;">@ ${highestPriceTime}</small>
                    </td>
                    <td>${profit.quantity}</td>
                    <td><strong>₹${profit.investment.toLocaleString('en-IN')}</strong></td>
                    <td><span class="badge ${profitClass}">${profitSign}₹${Math.round(profit.profit_loss)}</span></td>
                    <td><span class="badge ${highestProfitClass}">${highestProfitSign}₹${Math.round(highestProfit)}</span></td>
                    <td><span class="badge ${profitClass}">${profitSign}${profit.return_percent}%</span></td>
                    <td><span class="badge ${highestProfitClass}">${highestProfitSign}${highestReturnPercent}%</span></td>
                </tr>
            `;
        });

        $('#analysis-tbody').html(html);
    }

    function updateBasicStatistics() {
        if (!analysisData || analysisData.length === 0) {
            resetStatistics();
            return;
        }

        const totalDays = analysisData.length;
        const ceCount = analysisData.filter(r => r.signal === 'BUY_CE').length;
        const peCount = analysisData.filter(r => r.signal === 'BUY_PE').length;

        $('#total_days').text(totalDays);
        $('#ce_signals').text(ceCount);
        $('#pe_signals').text(peCount);
    }

    function updateProfitStatistics(summary) {
        if (!summary) return;

        $('#total_investment').text('₹' + summary.total_investment.toLocaleString('en-IN')); // ✅ ADD THIS
        $('#total_profit').text('₹' + summary.total_profit_loss.toLocaleString('en-IN'));
        $('#total_highest_profit').text('₹' + summary.total_highest_profit.toLocaleString('en-IN'));
        $('#win_rate').text(summary.win_rate + '%');
        $('#roi_percent').text(summary.roi_percent + '%');
        $('#highest_roi_percent').text(summary.highest_roi_percent + '%');
    }

    function resetStatistics() {
        $('#total_days').text('0');
        $('#ce_signals').text('0');
        $('#pe_signals').text('0');
        $('#total_investment').text('₹0'); // ✅ ADD THIS
        $('#total_profit').text('₹0');
        $('#total_highest_profit').text('₹0');
        $('#win_rate').text('0%');
        $('#roi_percent').text('0%');
        $('#highest_roi_percent').text('0%');
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter').val('');
        $('#option_series_filter').val('current');
        $('#exit_time_selector').val('15:30');
        $('#percentage_input').val('1.0');
        $('#current-percentage-display').text('±1%');
        
        analysisData = [];
        profitData = [];
        $('#analysis-tbody').html(`
            <tr>
                <td colspan="20" class="text-center py-5">
                    <div class="no-data-message">
                        <i class="fas fa-chart-line"></i>
                        <p style="font-size: 1.1rem;">Select date range and click <strong>"Run Analysis"</strong></p>
                    </div>
                </td>
            </tr>
        `);
        resetStatistics();
        $('#exit-time-section').hide();
        $('#export_csv').hide();
    }

    function exportCSV() {
        if (!analysisData || analysisData.length === 0) {
            alert('No data to export');
            return;
        }

        $.ajax({
            url: '{{ route("symbols.one-percent-export") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                data: JSON.stringify(analysisData)
            },
            xhrFields: {
                responseType: 'blob'
            },
            success: function (blob) {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'one_percent_analysis_' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            },
            error: function () {
                alert('Error exporting CSV');
            }
        });
    }

    $(document).ready(function () {
        $('#percentage_input').change(function() {
            const percentage = $(this).val();
            $('#current-percentage-display').text('±' + percentage + '%');
        });

        $('#run_analysis').click(function () {
            runAnalysis();
        });

        $('#calculate_profit').click(function () {
            calculateProfit();
        });

        $('#export_csv').click(function () {
            exportCSV();
        });

        $('#reset_filters').click(function () {
            resetFilters();
        });
        
    });
</script>
@endpush