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
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }}</h4>
                    <p class="text-muted">
                        <i class="fas fa-chart-line"></i> Supertrend + 50 MA Strategy | 
                        <i class="fas fa-clock"></i> 15-Minute Interval |
                        <i class="fas fa-robot"></i> Order Simulation (CE/PE)
                    </p>
                </div>
                <div>
                    <a href="{{ route('symbols.analysis') }}" class="btn btn-info me-2">
                        <i class="fas fa-chart-line"></i> Analysis View
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row mb-3">
                <div class="col-md-2">
                    <label for="from_date" class="form-label text-dark"><strong>From Date:</strong></label>
                    <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="to_date" class="form-label text-dark"><strong>To Date:</strong></label>
                    <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="option_series_filter" class="form-label text-dark"><strong>Option Series:</strong></label>
                    <select id="option_series_filter" class="form-control">
                        <option value="current" selected>Current Series</option>
                        <option value="next">Next Series</option>
                    </select>
                    <small class="text-muted">
                        <strong>Current:</strong> Same expiry as FUT<br>
                        <strong>Next:</strong> Skip to next expiry
                    </small>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-dark"><strong>Symbol (Optional):</strong></label>
                    <select id="symbol_filter" class="form-control" multiple size="3">
                        @foreach($monitoredSymbols as $symbol)
                            <option value="{{ $symbol->trading_symbol }}">{{ $symbol->trading_symbol }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Leave empty for all symbols</small>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" id="run_backtest" class="btn btn-success me-2">
                        <i class="fas fa-play"></i> Run Backtest
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total Order Triggers</small>
                    <strong id="total_signals" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <small>CE Orders (BUY Signals)</small>
                    <strong id="buy_signals" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <small>PE Orders (SELL Signals)</small>
                    <strong id="sell_signals" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #17a2b8;">
                    <small>Strategy</small>
                    <strong class="text-info">Supertrend + 50 MA</strong>
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
                            <th>Future Price</th>
                            <th>MA50</th>
                            <th>ATR</th>
                            <th>Option Type</th>
                            <th>Option Symbol</th>
                            <th>Strike Price</th>
                            <th>OI</th> <!-- ✅ NEW -->
                            <th>Fair Price</th> 
                            <th>LTP</th> 
                            {{-- <th>Valuation</th>
                            <th>Order Action</th> --}}
                        </tr>
                    </thead>
                    <tbody id="backtest-tbody">
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-rocket"></i>
                                    <p>Select date range, then click "Run Backtest"</p>
                                    <small class="text-muted">
                                        Strategy: Supertrend + 50 MA Filter<br>
                                        BUY Signal → Place CE order | SELL Signal → Place PE order
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
    let backtestData = [];

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    function runBacktest() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        const selectedSymbols = $('#symbol_filter').val() || [];
        const optionSeries = $('#option_series_filter').val();

        if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
        }

        toggleLoading(true);

        $.ajax({
            url: '{{ route("symbols.backtesting-fetch") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                option_series: optionSeries
            },
            success: function (response) {
                console.log('Backtest Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    backtestData = response.data;
                    displayBacktestTable();
                    updateStatistics();
                } else {
                    $('#backtest-tbody').html(`
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                                    <p class="text-info">${response.message || 'No order triggers found'}</p>
                                    <small class="text-muted">
                                        Try a different date range or check if data is available
                                    </small>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Backtest Error:', error);
                console.error('Response:', xhr.responseText);
                
                $('#backtest-tbody').html(`
                    <tr>
                        <td colspan="11" class="text-center py-5">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                <p class="text-danger">Error running backtest</p>
                                <small class="text-muted">Check console for details</small>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function displayBacktestTable() {
        if (!backtestData || backtestData.length === 0) {
            $('#backtest-tbody').html(`
                <tr>
                    <td colspan="15" class="text-center py-5">
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
        
        backtestData.forEach(function (row, index) {
            const signalClass = row.signal_type === 'BUY' ? 'badge-buy' : 'badge-sell';
            const optionClass = row.option_type === 'CE' ? 'badge-ce' : 'badge-pe';
            
            // ✅ Valuation badge color
            let valuationClass = 'badge-secondary';
            if (row.valuation === 'UNDERPRICED') {
                valuationClass = 'badge-success';
            } else if (row.valuation === 'OVERPRICED') {
                valuationClass = 'badge-danger';
            } else if (row.valuation === 'FAIR') {
                valuationClass = 'badge-info';
            }

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.future_symbol}</strong></td>
                    <td>${row.signal_time}</td>
                    <td><span class="badge ${signalClass}">${row.signal_type}</span></td>
                    <td><strong>₹${row.future_price}</strong></td>
                    <td><strong>₹${row.ma50}</strong></td>
                    <td>${row.atr}</td>
                    <td><span class="badge ${optionClass}">${row.option_type}</span></td>
                    <td><strong>${row.option_symbol}</strong></td>
                    <td><strong>₹${row.strike_price}</strong></td>
                    <td><strong>${row.oi ? row.oi.toLocaleString() : 'N/A'}</strong></td>
                    <td><strong>₹${row.fair_price ?? 'N/A'}</strong></td>
                    <td><strong>₹${row.ltp ?? 'N/A'}</strong></td>
                </tr>
            `;
        });
        // <td><span class="badge ${valuationClass}">${row.valuation}</span></td>
        // <td><em class="text-muted">${row.order_action}</em></td>
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

    function resetStatistics() {
        $('#total_signals').text('0');
        $('#buy_signals').text('0');
        $('#sell_signals').text('0');
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter').val('');
        $('#option_series_filter').val('current');
        
        backtestData = [];
        
        $('#backtest-tbody').html(`
            <tr>
                <td colspan="11" class="text-center py-5">
                    <div class="no-data-message">
                        <i class="fas fa-rocket"></i>
                        <p>Select date range, then click "Run Backtest"</p>
                        <small class="text-muted">
                            Strategy: Supertrend + 50 MA Filter<br>
                            BUY Signal → Place CE order | SELL Signal → Place PE order
                        </small>
                    </div>
                </td>
            </tr>
        `);
        
        resetStatistics();
    }

    $(document).ready(function () {
        $('#run_backtest').click(function () {
            runBacktest();
        });

        $('#reset_filters').click(function () {
            resetFilters();
        });
    });
</script>
@endpush