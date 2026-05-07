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

    .badge-buy { 
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important; 
        color: white !important; 
        padding: 5px 10px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }
    
    .badge-sell { 
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%) !important; 
        color: white !important; 
        padding: 5px 10px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }

    .badge-pullback {
        background-color: #17a2b8 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-break-retest {
        background-color: #6f42c1 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
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

    .stats-box.buy-stats {
        border-left-color: #28a745;
    }

    .stats-box.sell-stats {
        border-left-color: #dc3545;
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

    .pivot-levels {
        padding: 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        margin-top: 5px;
        line-height: 1.8;
    }

    .pivot-levels span {
        display: inline-block;
        margin: 2px 3px;
        padding: 2px 5px;
        background: #314558;
        border-radius: 3px;
        border-left: 3px solid #17a2b8;
        white-space: nowrap;
    }

    .pivot-levels span.resistance {
        border-left-color: #28a745;
        background: #1e3a2a;
    }

    .pivot-levels span.support {
        border-left-color: #dc3545;
        background: #3a1e1e;
    }

    .custom--table thead th, .custom--table tbody td {
        font-size: 10px !important;
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
                    <p>Standard Pivot Point Strategy: PP-based directional bias with pullback & break-retest entries (15-minute timeframe)</p>
                </div>
                <div>
                    <a href="{{ route('symbols.analysis') }}" class="btn btn-light me-2">
                        <i class="fas fa-chart-line"></i> Technical Analysis
                    </a>
                    <a href="{{ route('symbols.one-percent') }}" class="btn btn-light">
                        <i class="fas fa-percentage"></i> 1% Move
                    </a>
                </div>
            </div>
        </div>
        <!-- Add RIGHT AFTER the closing </div> of page-header -->
        <div class="alert alert-info" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); border: none; color: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3); margin-bottom: 25px;">
            <div class="row align-items-center">
                <div class="col-md-1 text-center">
                    <i class="fas fa-info-circle" style="font-size: 2.5rem;"></i>
                </div>
                <div class="col-md-11">
                    <h5 style="margin: 0 0 10px 0; font-weight: 700; color: white;">
                        <i class="fas fa-layer-group"></i> How Pivot Levels Work
                    </h5>
                    <div class="row" style="font-size: 0.9rem;">
                        <div class="col-md-6">
                            <strong style="color: #ffc107;">🎯 Active Trading Levels (Entry Signals):</strong>
                            <ul style="margin: 5px 0 0 0; padding-left: 20px; list-style: none;">
                                <li>✓ <strong>PP (Pivot Point):</strong> Directional bias & pullback entry</li>
                                <li>✓ <strong>R1 / S1:</strong> Pullback & break-retest entry</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <strong style="color: #20c997;">📊 Target Levels (Display Only):</strong>
                            <ul style="margin: 5px 0 0 0; padding-left: 20px; list-style: none;">
                                <li>• <strong>R2, R3:</strong> Profit targets for LONG positions</li>
                                <li>• <strong>S2, S3:</strong> Profit targets for SHORT positions</li>
                            </ul>
                        </div>
                    </div>
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
                    <label for="option_series_filter"><i class="fas fa-layer-group"></i> Option Series:</label>
                    <select id="option_series_filter" class="form-control">
                        <option value="current" selected>Current Series</option>
                        <option value="next">Next Series</option>
                    </select>
                </div>

                <div class="col-md-6">
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
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Buy @ Signal Time | Sell @ Exit Time
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Total Signals</small>
                    <strong id="total_signals" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box buy-stats">
                    <small>BUY Signals</small>
                    <strong id="buy_signals" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box sell-stats">
                    <small>SELL Signals</small>
                    <strong id="sell_signals" style="color: #dc3545;">0</strong>
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
                    <small>Total P/L</small>
                    <strong id="total_profit" class="text-dark">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box profit-stats">
                    <small>ROI %</small>
                    <strong id="roi_percent">0%</strong>
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
                            <th>Type</th>
                            <th>Entry Price</th>
                            <th>Pivot Level</th>
                            <th>Pivot Points</th>
                            <th>Option</th>
                            <th>Strike</th>
                            <th>Buy Price</th>
                            <th>Sell Price</th>
                            <th>Qty</th>
                            <th>Investment</th>
                            <th>P/L</th>
                            <th>ROI %</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="17" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-chart-line"></i>
                                    <p style="font-size: 1.1rem;">Select date range and click <strong>"Run Analysis"</strong></p>
                                    <small style="display: block; margin-top: 10px; color: #999;">
                                        Pivot Points calculated from previous day OHLC (15-minute timeframe)
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

        if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
        }

        toggleLoading(true);
        analysisData = [];
        profitData = [];

        $.ajax({
            url: '{{ route("pivot-point.analyze") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                option_series: optionSeries
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
                            <td colspan="17" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                                    <p class="text-info">${response.message || 'No pivot signals found'}</p>
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
                        <td colspan="17" class="text-center py-5">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                <p class="text-danger">Error running analysis</p>
                            </td>
                        </tr>
                    `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function displayAnalysisTable() {
        if (!analysisData || analysisData.length === 0) {
            return;
        }

        let html = '';
        
        analysisData.forEach(function (row, index) {
            let signalClass = row.signal === 'BUY' ? 'badge-buy' : 'badge-sell';
            let signalText = row.signal === 'BUY' ? 'BUY ▲' : 'SELL ▼';
            let typeClass = row.signal_type === 'PULLBACK' ? 'badge-pullback' : 'badge-break-retest';
            const timeOnly = row.signal_time ? row.signal_time.split(' ')[1].substring(0, 5) : '-';

            const pivotInfo = `
                <div class="pivot-levels">
                    <span class="resistance">R3: ₹${row.r3}</span>
                    <span class="resistance">R2: ₹${row.r2}</span>
                    <span class="resistance">R1: ₹${row.r1}</span>
                    <span>PP: ₹${row.pp}</span>
                    <span class="support">S1: ₹${row.s1}</span>
                    <span class="support">S2: ₹${row.s2}</span>
                    <span class="support">S3: ₹${row.s3}</span>
                </div>
            `;

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.date}</strong></td>
                    <td>${timeOnly}</td>
                    <td><strong style="color: #667eea;">${row.symbol}</strong></td>
                    <td><span class="badge ${signalClass}">${signalText}</span></td>
                    <td><span class="badge ${typeClass}">${row.signal_type}</span></td>
                    <td><strong>₹${row.entry_price}</strong></td>
                    <td><strong>${row.pivot_level}</strong> (₹${row.pivot_price})</td>
                    <td>${pivotInfo}</td>
                    <td><strong>${row.option_symbol || '-'}</strong></td>
                    <td>${row.strike_price || '-'}</td>
                    <td colspan="6" class="text-center text-muted">
                        <em>Click "Calculate P/L" to fetch prices</em>
                    </td>
                </tr>
            `;
        });

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
            url: '{{ route("pivot-point.profit") }}',
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

    function displayProfitTable() {
        if (!profitData || profitData.length === 0) {
            return;
        }

        let html = '';
        
        analysisData.forEach(function (signal, index) {
            const profit = profitData.find(p => p.option_symbol === signal.option_symbol);
            
            if (!profit) {
                return;
            }

            let signalClass = signal.signal === 'BUY' ? 'badge-buy' : 'badge-sell';
            let signalText = signal.signal === 'BUY' ? 'BUY ▲' : 'SELL ▼';
            let typeClass = signal.signal_type === 'PULLBACK' ? 'badge-pullback' : 'badge-break-retest';
            const profitClass = profit.profit_loss >= 0 ? 'badge-profit' : 'badge-loss';
            const profitSign = profit.profit_loss >= 0 ? '+' : '';
            const timeOnly = signal.signal_time ? signal.signal_time.split(' ')[1].substring(0, 5) : '-';

            const pivotInfo = `
                <div class="pivot-levels">
                    <span class="resistance">R3: ₹${signal.r3}</span>
                    <span class="resistance">R2: ₹${signal.r2}</span>
                    <span class="resistance">R1: ₹${signal.r1}</span>
                    <span>PP: ₹${signal.pp}</span>
                    <span class="support">S1: ₹${signal.s1}</span>
                    <span class="support">S2: ₹${signal.s2}</span>
                    <span class="support">S3: ₹${signal.s3}</span>
                </div>
            `;

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${signal.date}</strong></td>
                    <td>${timeOnly}</td>
                    <td><strong style="color: #667eea;">${signal.symbol}</strong></td>
                    <td><span class="badge ${signalClass}">${signalText}</span></td>
                    <td><span class="badge ${typeClass}">${signal.signal_type}</span></td>
                    <td><strong>₹${signal.entry_price}</strong></td>
                    <td><strong>${signal.pivot_level}</strong> (₹${signal.pivot_price})</td>
                    <td>${pivotInfo}</td>
                    <td><strong>${profit.option_symbol}</strong></td>
                    <td>${signal.strike_price}</td>
                    <td><strong>₹${profit.buy_price}</strong></td>
                    <td><strong>₹${profit.sell_price}</strong></td>
                    <td>${profit.quantity}</td>
                    <td><strong>₹${profit.investment.toLocaleString('en-IN')}</strong></td>
                    <td><span class="badge ${profitClass}">${profitSign}₹${Math.round(profit.profit_loss)}</span></td>
                    <td><span class="badge ${profitClass}">${profitSign}${profit.return_percent}%</span></td>
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

        const totalSignals = analysisData.length;
        const buyCount = analysisData.filter(r => r.signal === 'BUY').length;
        const sellCount = analysisData.filter(r => r.signal === 'SELL').length;

        $('#total_signals').text(totalSignals);
        $('#buy_signals').text(buyCount);
        $('#sell_signals').text(sellCount);
    }

    function updateProfitStatistics(summary) {
        if (!summary) return;

        $('#total_investment').text('₹' + summary.total_investment.toLocaleString('en-IN'));
        $('#total_profit').text('₹' + summary.total_profit_loss.toLocaleString('en-IN'));
        $('#roi_percent').text(summary.roi_percent + '%');
    }

    function resetStatistics() {
        $('#total_signals').text('0');
        $('#buy_signals').text('0');
        $('#sell_signals').text('0');
        $('#total_investment').text('₹0');
        $('#total_profit').text('₹0');
        $('#roi_percent').text('0%');
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter').val('');
        $('#option_series_filter').val('current');
        $('#exit_time_selector').val('15:30');
        
        analysisData = [];
        profitData = [];
        $('#analysis-tbody').html(`
            <tr>
                <td colspan="17" class="text-center py-5">
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
            url: '{{ route("pivot-point.export") }}',
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
                a.download = 'pivot_point_analysis_' + new Date().toISOString().slice(0, 10) + '.csv';
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