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

    .badge-buy { background-color: #28a745 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-sell { background-color: #dc3545 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-ce { background-color: #007bff !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-pe { background-color: #fd7e14 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-profit { background-color: #28a745 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-loss { background-color: #dc3545 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }

    .filter-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #dee2e6;
    }

    .stats-box {
        background: #fff;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        border-left: 4px solid #3498db;
        margin-bottom: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .stats-box small {
        display: block;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }

    .stats-box strong {
        display: block;
        font-size: 1.5rem;
        margin-top: 5px;
    }

    .profit-stats-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-left: 4px solid #fff;
    }

    .profit-stats-box small {
        color: rgba(255,255,255,0.9);
    }

    .no-data-message {
        padding: 40px 20px;
        text-align: center;
        color: #6c757d;
    }

    .no-data-message i {
        font-size: 3rem;
        margin-bottom: 15px;
        display: block;
    }

    .time-filter-box {
        background: #fff;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #dee2e6;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }}</h4>
                    <p class="text-muted">Simulate CE/PE orders based on Supertrend, VWAP & RSI strategies with P/L calculation</p>
                </div>
                <div>
                    <a href="{{ route('futures.supertrend-analysis') }}" class="btn btn-info me-2">
                        <i class="fas fa-chart-line"></i> Technical Analysis
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row mb-3">
                <div class="col-md-2">
                    <label for="from_date" class="form-label text-dark"><strong>From Date:</strong></label>
                    <input type="date" id="from_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="to_date" class="form-label text-dark"><strong>To Date:</strong></label>
                    <input type="date" id="to_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="interval_filter" class="form-label text-dark"><strong>Timeframe:</strong></label>
                    <select id="interval_filter" class="form-control">
                        <option value="minute">1 Minute</option>
                        <option value="5minute">5 Minutes</option>
                        <option value="15minute" selected>15 Minutes</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="strategy_filter" class="form-label text-dark"><strong>Strategy:</strong></label>
                    <select id="strategy_filter" class="form-control">
                        <option value="SUPERTREND">Supertrend Only</option>
                        <option value="VWAP">VWAP Only</option>
                        <option value="RSI">RSI Only</option>
                        <option value="SUPERTREND_VWAP">Supertrend + VWAP</option>
                        <option value="VWAP_RSI">VWAP + RSI</option>
                        <option value="SUPERTREND_VWAP_RSI">All Three</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-dark"><strong>Symbol (Optional):</strong></label>
                    <select id="symbol_filter" class="form-control" multiple size="3">
                        @foreach($monitoredFutures as $future)
                            <option value="{{ $future->trading_symbol }}">{{ $future->trading_symbol }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Leave empty for all symbols</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <button type="button" id="run_backtest" class="btn btn-success me-2">
                        <i class="fas fa-play"></i> Run Backtest
                    </button>
                    <button type="button" id="calculate_profit" class="btn btn-primary me-2" style="display: none;">
                        <i class="fas fa-dollar-sign"></i> Calculate P/L
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Total Orders</small>
                    <strong id="total_signals" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>CE Orders</small>
                    <strong id="buy_signals" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>PE Orders</small>
                    <strong id="sell_signals" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #17a2b8;">
                    <small>Total Investment</small>
                    <strong id="total_investment" class="text-info">₹0.00</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box profit-stats-box">
                    <small>Total P/L</small>
                    <strong id="total_profit">₹0.00</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box profit-stats-box">
                    <small>Win Rate</small>
                    <strong id="win_rate">0%</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #ffc107;">
                    <small>ROI %</small>
                    <strong id="roi_percent" class="text-warning">0%</strong>
                </div>
            </div>
        </div>

        <!-- Time Filter Section -->
        <div class="time-filter-box">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label for="time_slot_filter" class="form-label text-dark"><strong><i class="fas fa-filter"></i> Filter by Time Slot:</strong></label>
                    <select id="time_slot_filter" class="form-control">
                        <option value="">All Time Slots</option>
                        <option value="09:15">09:15</option>
                        <option value="09:30">09:30</option>
                        <option value="09:45">09:45</option>
                        <option value="10:00">10:00</option>
                        <option value="10:30">10:30</option>
                        <option value="11:00">11:00</option>
                        <option value="12:00">12:00</option>
                        <option value="13:00">13:00</option>
                        <option value="14:00">14:00</option>
                        <option value="15:00">15:00</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" id="reset_time_filter" class="btn btn-warning">
                        <i class="fas fa-redo"></i> Reset Time Filter
                    </button>
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
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Future Symbol</th>
                            <th>Signal Time</th>
                            <th>Signal Type</th>
                            <th>Option Symbol</th>
                            <th>Entry Price</th>
                            <th>Exit Time</th>
                            <th>Exit Price</th>
                            <th>Quantity</th>
                            <th>Investment</th>
                            <th>P/L</th>
                            <th>Return %</th>
                        </tr>
                    </thead>
                    <tbody id="backtest-tbody">
                        <tr>
                            <td colspan="12" class="text-center text-muted py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-rocket"></i>
                                    <p>Select date range and strategy, then click "Run Backtest"</p>
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
    let backtestData = [];
    let profitData = [];

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    function runBacktest() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        const interval = $('#interval_filter').val();
        const strategy = $('#strategy_filter').val();
        const selectedSymbols = $('#symbol_filter').val() || [];

        if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
        }

        toggleLoading(true);
        profitData = []; // Reset profit data

        $.ajax({
            url: '{{ route("futures.backtesting-fetch") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                interval: interval,
                strategy: strategy,
                symbols: selectedSymbols
            },
            success: function (response) {
                if (response.success && response.data && response.data.length > 0) {
                    backtestData = response.data;
                    displayBacktestTable();
                    updateStatistics();
                    $('#calculate_profit').show(); // Show P/L button
                } else {
                    $('#backtest-tbody').html(`
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                                    <p class="text-info">${response.message || 'No order triggers found'}</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                    $('#calculate_profit').hide();
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Error:', error);
                $('#backtest-tbody').html(`
                    <tr>
                        <td colspan="11" class="text-center py-5">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                <p class="text-danger">Error running backtest. Check console.</p>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function calculateProfit() {
        if (!backtestData || backtestData.length === 0) {
            alert('No backtest data available. Please run backtest first.');
            return;
        }

        toggleLoading(true);

        $.ajax({
            url: '{{ route("futures.backtest-profit") }}',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                signals: backtestData
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
                alert('Error calculating profit. Please check if Zerodha access token is valid.');
                toggleLoading(false);
            }
        });
    }

    function displayBacktestTable(filteredData = null) {
        const dataToDisplay = filteredData || backtestData;
        
        if (!dataToDisplay || dataToDisplay.length === 0) {
            $('#backtest-tbody').html(`
                <tr>
                    <td colspan="11" class="text-center py-5">
                        <div class="no-data-message">
                            <i class="fas fa-inbox"></i>
                            <p>No order triggers found</p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        
        dataToDisplay.forEach(function (row, index) {
            const signalClass = row.signal_type === 'BUY' ? 'badge-buy' : 'badge-sell';
            const optionClass = row.option_type === 'CE' ? 'badge-ce' : 'badge-pe';

            // html += `
            //     <tr>
            //         <td><strong>${index + 1}</strong></td>
            //         <td><strong>${row.future_symbol}</strong></td>
            //         <td>${row.signal_time}</td>
            //         <td><span class="badge ${signalClass}">${row.signal_type}</span></td>
            //         <td><strong>${row.option_symbol}</strong></td>
            //         <td colspan="6" class="text-center text-muted">
            //             <em>Click "Calculate P/L" to fetch prices</em>
            //         </td>
            //     </tr>
            // `;

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.future_symbol}</strong></td>
                    <td>${row.signal_time}</td>
                    <td><span class="badge ${signalClass}">${row.signal_type}</span></td>
                    <td><strong>${row.option_symbol}</strong></td>
                    <td colspan="7" class="text-center text-muted">
                        <em>Click "Calculate P/L" to fetch prices</em>
                    </td>
                </tr>
            `;

        });

        $('#backtest-tbody').html(html);
    }

    function displayProfitTable(filteredData = null) {
        const dataToDisplay = filteredData || profitData;
        
        if (!dataToDisplay || dataToDisplay.length === 0) {
            return;
        }

        let html = '';
        
        dataToDisplay.forEach(function (row, index) {
            const profitClass = row.profit_loss >= 0 ? 'badge-profit' : 'badge-loss';
            const profitSign = row.profit_loss >= 0 ? '+' : '';

            // html += `
            //     <tr>
            //         <td><strong>${index + 1}</strong></td>
            //         <td><strong>${backtestData[index].future_symbol}</strong></td>
            //         <td>${backtestData[index].signal_time}</td>
            //         <td><span class="badge ${backtestData[index].signal_type === 'BUY' ? 'badge-buy' : 'badge-sell'}">${backtestData[index].signal_type}</span></td>
            //         <td><strong>${row.option_symbol}</strong></td>
            //         <td>₹${row.entry_price}</td>
            //         <td>${row.exit_time}</td>
            //         <td>₹${row.exit_price}</td>
            //         <td>${row.quantity}</td>
            //         <td><span class="badge ${profitClass}">${profitSign}₹${row.profit_loss}</span></td>
            //         <td><span class="badge ${profitClass}">${profitSign}${row.return_percent}%</span></td>
            //     </tr>
            // `;

             html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${backtestData[index].future_symbol}</strong></td>
                    <td>${backtestData[index].signal_time}</td>
                    <td><span class="badge ${backtestData[index].signal_type === 'BUY' ? 'badge-buy' : 'badge-sell'}">${backtestData[index].signal_type}</span></td>
                    <td><strong>${row.option_symbol}</strong></td>
                    <td>₹${row.entry_price}</td>
                    <td>${row.exit_time}</td>
                    <td>₹${row.exit_price}</td>
                    <td>${row.quantity}</td>
                    <td><strong>₹${row.investment}</strong></td>
                    <td><span class="badge ${profitClass}">${profitSign}₹${row.profit_loss}</span></td>
                    <td><span class="badge ${profitClass}">${profitSign}${row.return_percent}%</span></td>
                </tr>
            `;
        });

        $('#backtest-tbody').html(html);
    }

    function updateStatistics() {
        if (!backtestData || backtestData.length === 0) {
            resetStatistics();
            return;
        }

        const totalSignals = backtestData.length;
        const buyCount = backtestData.filter(r => r.signal_type === 'BUY').length;
        const sellCount = backtestData.filter(r => r.signal_type === 'SELL').length;

        $('#total_signals').text(totalSignals);
        $('#buy_signals').text(buyCount);
        $('#sell_signals').text(sellCount);
    }

    // function updateProfitStatistics(summary) {
    //     if (!summary) return;

    //     $('#total_profit').text('₹' + summary.total_profit_loss.toFixed(2));
    //     $('#win_rate').text(summary.win_rate + '%');
    // }

    function updateProfitStatistics(summary) {
        if (!summary) return;

        $('#total_investment').text('₹' + summary.total_investment.toFixed(2));
        $('#total_profit').text('₹' + summary.total_profit_loss.toFixed(2));
        $('#win_rate').text(summary.win_rate + '%');
        $('#roi_percent').text(summary.roi_percent + '%');
    }

    // function resetStatistics() {
    //     $('#total_signals').text('0');
    //     $('#buy_signals').text('0');
    //     $('#sell_signals').text('0');
    //     $('#total_profit').text('₹0.00');
    //     $('#win_rate').text('0%');
    // }

    function resetStatistics() {
        $('#total_signals').text('0');
        $('#buy_signals').text('0');
        $('#sell_signals').text('0');
        $('#total_investment').text('₹0.00');
        $('#total_profit').text('₹0.00');
        $('#win_rate').text('0%');
        $('#roi_percent').text('0%');
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#interval_filter').val('15minute');
        $('#strategy_filter').val('SUPERTREND');
        $('#symbol_filter').val('');
        $('#time_slot_filter').val('');
        
        backtestData = [];
        profitData = [];
        $('#backtest-tbody').html(`
            <tr>
                <td colspan="12" class="text-center py-5">
                    <div class="no-data-message">
                        <i class="fas fa-rocket"></i>
                        <p>Select date range and strategy, then click "Run Backtest"</p>
                    </div>
                </td>
            </tr>
        `);
        resetStatistics();
        $('#calculate_profit').hide();
    }

    $(document).ready(function () {
        $('#run_backtest').click(function () {
            runBacktest();
        });

        $('#calculate_profit').click(function () {
            calculateProfit();
        });

        $('#reset_filters').click(function () {
            resetFilters();
        });
    });
</script>
@endpush