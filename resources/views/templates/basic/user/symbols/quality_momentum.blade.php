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
        background-color: #28a745 !important; 
        color: white !important; 
        padding: 4px 12px; 
        border-radius: 4px;
        font-weight: 600;
    }
    
    .badge-sell { 
        background-color: #dc3545 !important; 
        color: white !important; 
        padding: 4px 12px; 
        border-radius: 4px;
        font-weight: 600;
    }

    .badge-bullish {
        background-color: #28a745 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .badge-bearish {
        background-color: #dc3545 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .badge-neutral {
        background-color: #6c757d !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .filter-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .filter-section label {
        color: white !important;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .filter-section small {
        color: rgba(255,255,255,0.9);
    }

    .stats-box {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        border-left: 4px solid #667eea;
        margin-bottom: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }

    .stats-box:hover {
        transform: translateY(-5px);
    }

    .stats-box small {
        display: block;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .stats-box strong {
        display: block;
        font-size: 2rem;
        margin-top: 5px;
    }

    .quality-badge {
        display: inline-block;
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
        margin-left: 10px;
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
        color: #667eea;
    }

    .info-box {
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .info-box h6 {
        color: #1976d2;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .info-box ul {
        margin: 0;
        padding-left: 20px;
        color: #424242;
    }

    .info-box ul li {
        margin-bottom: 5px;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>
                        {{ $pageTitle }}
                        <span class="quality-badge">
                            <i class="fas fa-star"></i> Pure Quality Filter
                        </span>
                    </h4>
                    <p class="text-muted">Scan candles with strong volume + price momentum (no indicators needed)</p>
                </div>
                <div>
                    <a href="{{ route('symbols.backtesting') }}" class="btn btn-info me-2">
                        <i class="fas fa-chart-bar"></i> Backtesting
                    </a>
                    <a href="{{ route('symbols.analysis') }}" class="btn btn-secondary">
                        <i class="fas fa-chart-line"></i> Technical Analysis
                    </a>
                </div>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <h6><i class="fas fa-info-circle"></i> How Quality Momentum Works:</h6>
            <ul>
                <li><strong>Volume Check:</strong> At least 1 out of last 3 candles must have volume above 8-period average</li>
                <li><strong>Price Momentum:</strong> At least 1 out of 2 consecutive candles showing directional movement</li>
                <li><strong>No Indicators:</strong> Pure price action and volume - scans all candles independently</li>
                <li><strong>Both Directions:</strong> Finds both BUY (bullish) and SELL (bearish) momentum signals</li>
            </ul>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row mb-3">
                <div class="col-md-2">
                    <label for="from_date" class="form-label"><strong>From Date:</strong></label>
                    <input type="date" id="from_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="to_date" class="form-label"><strong>To Date:</strong></label>
                    <input type="date" id="to_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="interval_filter" class="form-label"><strong>Timeframe:</strong></label>
                    <select id="interval_filter" class="form-control">
                        <option value="minute">1 Minute</option>
                        <option value="5minute" selected>5 Minutes</option>
                        <option value="15minute">15 Minutes</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="momentum_type" class="form-label"><strong>Signal Type:</strong></label>
                    <select id="momentum_type" class="form-control">
                        <option value="both" selected>Both BUY & SELL</option>
                        <option value="buy">Only BUY Momentum</option>
                        <option value="sell">Only SELL Momentum</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label"><strong>Symbol (Optional):</strong></label>
                    <select id="symbol_filter" class="form-control" multiple size="3">
                        @foreach($monitoredSymbols as $symbol)
                            <option value="{{ $symbol->trading_symbol }}">{{ $symbol->trading_symbol }}</option>
                        @endforeach
                    </select>
                    <small>Leave empty for all symbols</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <button type="button" id="run_scan" class="btn btn-success btn-lg me-2">
                        <i class="fas fa-search"></i> Scan Quality Momentum
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-light btn-lg">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total Signals</small>
                    <strong id="total_signals" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>BUY Signals</small>
                    <strong id="buy_signals" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #dc3545;">
                    <small>SELL Signals</small>
                    <strong id="sell_signals" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #17a2b8;">
                    <small>Unique Symbols</small>
                    <strong id="unique_symbols" class="text-info">0</strong>
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
                            <th>Symbol</th>
                            <th>Timestamp</th>
                            <th>Signal Type</th>
                            <th>Open</th>
                            <th>High</th>
                            <th>Low</th>
                            <th>Close</th>
                            <th>Volume</th>
                            <th>OI Change %</th>
                            <th>OI Signal</th>
                        </tr>
                    </thead>
                    <tbody id="quality-tbody">
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-filter"></i>
                                    <p>Select date range and click "Scan Quality Momentum"</p>
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
    let qualityData = [];

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    function runQualityScan() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        const interval = $('#interval_filter').val();
        const momentumType = $('#momentum_type').val();
        const selectedSymbols = $('#symbol_filter').val() || [];

        if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
        }

        toggleLoading(true);

        $.ajax({
            url: '{{ route("symbols.quality-momentum-fetch") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                interval: interval,
                momentum_type: momentumType,
                symbols: selectedSymbols
            },
            success: function (response) {
                console.log('Quality Scan Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    qualityData = response.data;
                    displayQualityTable();
                    updateStatistics();
                } else {
                    $('#quality-tbody').html(`
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                                    <p class="text-info">${response.message || 'No quality momentum signals found'}</p>
                                    <small class="text-muted">Try adjusting date range or timeframe</small>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Error:', error);
                $('#quality-tbody').html(`
                    <tr>
                        <td colspan="11" class="text-center py-5">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                <p class="text-danger">Error running scan. Check console.</p>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function displayQualityTable() {
        if (!qualityData || qualityData.length === 0) {
            $('#quality-tbody').html(`
                <tr>
                    <td colspan="11" class="text-center py-5">
                        <div class="no-data-message">
                            <i class="fas fa-inbox"></i>
                            <p>No quality momentum signals found</p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        
        qualityData.forEach(function (row, index) {
            const signalClass = row.signal_type === 'BUY' ? 'badge-buy' : 'badge-sell';
            const oiClass = row.oi_signal === 'BULLISH' ? 'badge-bullish' : 
                           row.oi_signal === 'BEARISH' ? 'badge-bearish' : 'badge-neutral';

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.symbol}</strong></td>
                    <td>${row.timestamp}</td>
                    <td><span class="badge ${signalClass}">${row.signal_type}</span></td>
                    <td>₹${row.open}</td>
                    <td>₹${row.high}</td>
                    <td>₹${row.low}</td>
                    <td><strong>₹${row.close}</strong></td>
                    <td><strong>${row.volume.toLocaleString()}</strong></td>
                    <td><strong>${row.oi_change_percent}%</strong></td>
                    <td><span class="badge ${oiClass}">${row.oi_signal}</span></td>
                </tr>
            `;
        });

        $('#quality-tbody').html(html);
    }

    function updateStatistics() {
        if (!qualityData || qualityData.length === 0) {
            resetStatistics();
            return;
        }

        const totalSignals = qualityData.length;
        const buyCount = qualityData.filter(r => r.signal_type === 'BUY').length;
        const sellCount = qualityData.filter(r => r.signal_type === 'SELL').length;
        const uniqueSymbols = [...new Set(qualityData.map(r => r.symbol))].length;

        $('#total_signals').text(totalSignals);
        $('#buy_signals').text(buyCount);
        $('#sell_signals').text(sellCount);
        $('#unique_symbols').text(uniqueSymbols);
    }

    function resetStatistics() {
        $('#total_signals').text('0');
        $('#buy_signals').text('0');
        $('#sell_signals').text('0');
        $('#unique_symbols').text('0');
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#interval_filter').val('5minute');
        $('#momentum_type').val('both');
        $('#symbol_filter').val('');
        
        qualityData = [];
        $('#quality-tbody').html(`
            <tr>
                <td colspan="11" class="text-center py-5">
                    <div class="no-data-message">
                        <i class="fas fa-filter"></i>
                        <p>Select date range and click "Scan Quality Momentum"</p>
                    </div>
                </td>
            </tr>
        `);
        resetStatistics();
    }

    $(document).ready(function () {
        $('#run_scan').click(function () {
            runQualityScan();
        });

        $('#reset_filters').click(function () {
            resetFilters();
        });
    });
</script>
@endpush