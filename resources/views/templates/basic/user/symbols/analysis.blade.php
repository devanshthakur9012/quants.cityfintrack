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

    .table-container {
        position: relative;
        min-height: 400px;
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
        padding: 4px 8px;
        border-radius: 4px;
    }

    .badge-sell {
        background-color: #dc3545 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .badge-hold {
        background-color: #6c757d !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
    }

    /* New badges for position */
    .badge-long {
        background-color: #17a2b8 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .badge-short {
        background-color: #fd7e14 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .badge-flat {
        background-color: #6c757d !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .uptrend {
        color: #28a745;
        font-weight: bold;
    }

    .downtrend {
        color: #dc3545;
        font-weight: bold;
    }

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

    .view-mode-badge {
        padding: 8px 15px;
        border-radius: 5px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .mode-latest {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .mode-filtered {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
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
                        <span id="view_mode_badge" class="view-mode-badge mode-latest">
                            📊 Latest View
                        </span>
                    </p>
                </div>
                <div>
                    <button type="button" id="manual_fetch_btn" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Fetch Latest Data
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-3">
                    <label for="trading_symbol_filter" class="form-label text-dark"><strong>Select Symbol:</strong></label>
                    <select id="trading_symbol_filter" class="form-control">
                        <option value="">-- Show All Symbols (Latest) --</option>
                        @foreach ($monitoredSymbols as $symbol)
                            <option value="{{ $symbol->trading_symbol }}">
                                {{ $symbol->trading_symbol }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Leave empty to see latest for all symbols</small>
                </div>

                <div class="col-md-2">
                    <label for="from_date" class="form-label text-dark"><strong>From Date:</strong></label>
                    <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="to_date" class="form-label text-dark"><strong>To Date:</strong></label>
                    <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" id="load_data" class="btn btn-success btn-sm me-2">
                        <i class="fas fa-search"></i> Load
                    </button>
                    <button type="button" id="refresh_data" class="btn btn-info btn-sm me-2">
                        <i class="fas fa-redo"></i> Refresh
                    </button>
                    <button type="button" id="export_csv" class="btn btn-warning btn-sm">
                        <i class="fas fa-download"></i> CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4" id="stats-container">
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Total Records</small>
                    <strong id="total_records" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>BUY Signals</small>
                    <strong id="buy_signals" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>SELL Signals</small>
                    <strong id="sell_signals" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>LONG Positions</small>
                    <strong id="long_positions" style="color: #17a2b8;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Latest ATR</small>
                    <strong id="latest_atr" class="text-dark">-</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Latest MA50</small>
                    <strong id="latest_ma50" class="text-dark">-</strong>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="loading-overlay" id="loading-overlay" style="display: none;">
                <div class="spinner"></div>
            </div>

            <div class="table-responsive">
                <table class="table custom--table">
                    <thead class="table-dark">
                        <tr>
                            <th>Date & Time</th>
                            <th>Symbol</th>
                            <th>Open</th>
                            <th>High</th>
                            <th>Low</th>
                            <th>Close</th>
                            <th>Volume</th>
                            <th>ATR</th>
                            <th>Supertrend</th>
                            <th>Direction</th>
                            <th>Event Signal</th>
                            <th>Position</th>
                            <th>MA50</th>
                            <th>Upper Band</th>
                            <th>Lower Band</th>
                        </tr>
                    </thead>
                    <tbody id="supertrend-tbody">
                        <tr>
                            <td colspan="15" class="text-center text-muted">
                                <div class="no-data-message">
                                    <i class="fas fa-sync fa-spin"></i>
                                    <p>Loading latest data...</p>
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
    let currentData = [];
    let currentMode = 'latest'; // 'latest' or 'filtered'

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    function updateViewModeBadge(mode) {
        currentMode = mode;
        const badge = $('#view_mode_badge');
        
        if (mode === 'latest') {
            badge.removeClass('mode-filtered').addClass('mode-latest');
            badge.html('📊 Latest View');
        } else {
            badge.removeClass('mode-latest').addClass('mode-filtered');
            badge.html('🔍 Filtered View');
        }
    }

    function formatVolume(volume) {
        if (volume >= 1000000) {
            return (volume / 1000000).toFixed(2) + 'M';
        } else if (volume >= 1000) {
            return (volume / 1000).toFixed(2) + 'K';
        }
        return volume.toString();
    }

    // Load latest row for each symbol (DEFAULT VIEW)
    function loadLatestData() {
        toggleLoading(true);

        $.ajax({
            url: '{{ route("symbols.analysis-latest") }}',
            type: 'GET',
            success: function(response) {
                console.log('Latest Response:', response);

                if (response.success && response.data && response.data.length > 0) {
                    currentData = response.data;
                    updateViewModeBadge('latest');
                    displayTable();
                    updateStatistics(response.data);
                } else {
                    $('#supertrend-tbody').html(`
                        <tr>
                            <td colspan="15" class="text-center">
                                <div class="no-data-message">
                                    <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                                    <p class="text-warning">No data available</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                }
                toggleLoading(false);
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $('#supertrend-tbody').html(`
                    <tr>
                        <td colspan="15" class="text-center">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                <p class="text-danger">Error loading data. Please try again.</p>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    // Load filtered data for specific symbol
    function loadFilteredData() {
        const tradingSymbol = $('#trading_symbol_filter').val();
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();

        if (!tradingSymbol) {
            // If no symbol selected, show latest view
            loadLatestData();
            return;
        }

        toggleLoading(true);

        $.ajax({
            url: '{{ route("symbols.analysis-fetch") }}',
            type: 'GET',
            data: {
                trading_symbol: tradingSymbol,
                from_date: fromDate,
                to_date: toDate
            },
            success: function(response) {
                console.log('Filtered Response:', response);

                if (response.success && response.data && response.data.length > 0) {
                    currentData = response.data;
                    updateViewModeBadge('filtered');
                    displayTable();
                    updateStatistics(response.data);
                } else {
                    const message = response.message || 'No data available';
                    $('#supertrend-tbody').html(`
                        <tr>
                            <td colspan="15" class="text-center">
                                <div class="no-data-message">
                                    <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                                    <p class="text-warning">${message}</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                }
                toggleLoading(false);
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $('#supertrend-tbody').html(`
                    <tr>
                        <td colspan="15" class="text-center">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                <p class="text-danger">Error loading data. Please try again.</p>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function displayTable() {
        if (!currentData || currentData.length === 0) {
            $('#supertrend-tbody').html(`
                <tr>
                    <td colspan="15" class="text-center text-muted">
                        <div class="no-data-message">
                            <i class="fas fa-inbox"></i>
                            <p>No data available</p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';

        currentData.forEach(function(row) {
            // Event Signal (only shows on crossover)
            const eventSignal = row.event_signal || null;
            let eventSignalHtml = '-';
            if (eventSignal === 'BUY') {
                eventSignalHtml = '<span class="badge badge-buy">▲ BUY</span>';
            } else if (eventSignal === 'SELL') {
                eventSignalHtml = '<span class="badge badge-sell">▼ SELL</span>';
            }

            // Position (current state)
            const position = row.position || 'FLAT';
            let positionClass = 'badge-flat';
            let positionIcon = '→';
            if (position === 'LONG') {
                positionClass = 'badge-long';
                positionIcon = '↑';
            } else if (position === 'SHORT') {
                positionClass = 'badge-short';
                positionIcon = '↓';
            }
            const positionHtml = `<span class="badge ${positionClass}">${positionIcon} ${position}</span>`;

            // Direction
            const directionClass = row.direction === 'UP' ? 'uptrend' : 
                                  row.direction === 'DOWN' ? 'downtrend' : '';

            const formatPrice = (val) => val != null && !isNaN(val) ? parseFloat(val).toFixed(2) : '-';

            html += `
                <tr>
                    <td><strong>${row.date || '-'}</strong></td>
                    <td><strong>${row.symbol || '-'}</strong></td>
                    <td>${formatPrice(row.open)}</td>
                    <td>${formatPrice(row.high)}</td>
                    <td>${formatPrice(row.low)}</td>
                    <td><strong>${formatPrice(row.close)}</strong></td>
                    <td>${formatVolume(row.volume || 0)}</td>
                    <td>${row.atr ? row.atr.toFixed(4) : '-'}</td>
                    <td><strong>${formatPrice(row.supertrend)}</strong></td>
                    <td><span class="${directionClass}">${row.direction || '-'}</span></td>
                    <td>${eventSignalHtml}</td>
                    <td>${positionHtml}</td>
                    <td><strong>${formatPrice(row.ma50)}</strong></td>
                    <td>${formatPrice(row.upper_band)}</td>
                    <td>${formatPrice(row.lower_band)}</td>
                </tr>
            `;
        });

        $('#supertrend-tbody').html(html);
    }

    function updateStatistics(data) {
        if (!data || data.length === 0) {
            resetStatistics();
            return;
        }

        const buyCount = data.filter(r => r.event_signal === 'BUY').length;
        const sellCount = data.filter(r => r.event_signal === 'SELL').length;
        const longCount = data.filter(r => r.position === 'LONG').length;
        const latestRecord = data[0];

        const latestATR = (latestRecord.atr != null) ? parseFloat(latestRecord.atr).toFixed(4) : '-';
        const latestMA50 = (latestRecord.ma50 != null) ? '₹' + parseFloat(latestRecord.ma50).toFixed(2) : '-';

        $('#total_records').text(data.length);
        $('#buy_signals').text(buyCount);
        $('#sell_signals').text(sellCount);
        $('#long_positions').text(longCount);
        $('#latest_atr').html(`<span class="text-info">${latestATR}</span>`);
        $('#latest_ma50').html(`<span class="text-primary">${latestMA50}</span>`);
    }

    function resetStatistics() {
        $('#total_records').text('0');
        $('#buy_signals').text('0');
        $('#sell_signals').text('0');
        $('#long_positions').text('0');
        $('#latest_atr').html('-');
        $('#latest_ma50').html('-');
    }

    function exportToCSV() {
        const tradingSymbol = $('#trading_symbol_filter').val();
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();

        const params = new URLSearchParams({
            symbol: tradingSymbol || '',
            from_date: fromDate || '',
            to_date: toDate || ''
        });

        window.location.href = '{{ route("symbols.export") }}?' + params.toString();
    }

    $(document).ready(function() {
        // Load latest data on page load
        loadLatestData();

        $('#load_data').click(function() {
            loadFilteredData();
        });

        $('#refresh_data').click(function() {
            if ($('#trading_symbol_filter').val()) {
                loadFilteredData();
            } else {
                loadLatestData();
            }
        });

        $('#export_csv').click(function() {
            exportToCSV();
        });

        $('#trading_symbol_filter').change(function() {
            if ($(this).val()) {
                loadFilteredData();
            } else {
                loadLatestData();
            }
        });

        // Manual Fetch Button
        $('#manual_fetch_btn').click(function() {
            if (!confirm('Fetch latest 15-minute data for all symbols?')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true);
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Fetching...');

            $.ajax({
                url: '{{ route("symbols.manual-fetch") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    alert(response.message);
                    loadLatestData(); // Reload after fetch
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error fetching data';
                    alert(message);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fas fa-sync-alt"></i> Fetch Latest Data');
                }
            });
        });
    });
</script>
@endpush