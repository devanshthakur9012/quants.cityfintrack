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

    .badge-buy { background-color: #28a745 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-sell { background-color: #dc3545 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-hold { background-color: #6c757d !important; color: white !important; padding: 4px 8px; border-radius: 4px; }

    .uptrend { color: #28a745; font-weight: bold; }
    .downtrend { color: #dc3545; font-weight: bold; }

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

    .fetch-status {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
    }

    .expiry-alert {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .expiry-alert.no-expiry {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
                    <p class="text-muted">1-Minute Supertrend Analysis for Expiry Trading</p>
                </div>
                <div>
                    <button type="button" id="manual_fetch_btn" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Fetch Latest Data
                    </button>
                </div>
            </div>
        </div>

        <!-- Expiry Status Alert -->
        @if ($expiringToday->isEmpty())
            <div class="expiry-alert no-expiry">
                <i class="fas fa-info-circle"></i>
                <strong>No symbols expiring today.</strong>
                <p class="mb-0 mt-2">Expiry trading is ONLY active on expiry days. You can still view historical data below.</p>
            </div>
        @else
            <div class="expiry-alert">
                <i class="fas fa-check-circle"></i>
                <strong>🎯 EXPIRY DAY! Trading active for:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($expiringToday as $symbol)
                        @php
                            $expiry = $symbol->getClosestExpiry();
                        @endphp
                        <li><strong>{{ $symbol->symbol }}</strong> - Expiry: {{ $expiry->format('d M Y') }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-3">
                    <label for="symbol_filter" class="form-label text-dark">Symbol:</label>
                    <select id="symbol_filter" class="form-control form-control-sm">
                        <option value="">-- Select Symbol --</option>
                        @foreach($allSymbols as $symbol)
                            <option value="{{ $symbol->symbol }}">{{ $symbol->symbol }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="from_date" class="form-label text-dark">From Date:</label>
                    <input type="date" id="from_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="to_date" class="form-label text-dark">To Date:</label>
                    <input type="date" id="to_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-5 d-flex align-items-end">
                    <button type="button" id="load_data" class="btn btn-success btn-sm me-2">
                        <i class="fas fa-search"></i> Load Data
                    </button>
                    <button type="button" id="refresh_data" class="btn btn-info btn-sm me-2">
                        <i class="fas fa-redo"></i> Refresh
                    </button>
                    <button type="button" id="export_csv" class="btn btn-warning btn-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4" id="stats-container">
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total Candles</small>
                    <strong id="total_records" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Current Trend</small>
                    <strong id="current_trend" class="text-dark">-</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Buy Signals</small>
                    <strong id="buy_signals" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Sell Signals</small>
                    <strong id="sell_signals" style="color: #dc3545;">0</strong>
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
                            <th>Signal</th>
                        </tr>
                    </thead>
                    <tbody id="data-tbody">
                        <tr>
                            <td colspan="11" class="text-center text-muted">
                                <div class="no-data-message">
                                    <i class="fas fa-chart-line"></i>
                                    <p>Select a symbol and click "Load Data"</p>
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

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    function formatVolume(volume) {
        if (volume >= 1000000) {
            return (volume / 1000000).toFixed(2) + 'M';
        } else if (volume >= 1000) {
            return (volume / 1000).toFixed(2) + 'K';
        }
        return volume.toString();
    }

    function loadExpiryData() {
        const symbol = $('#symbol_filter').val();
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();

        if (!symbol) {
            alert('Please select a symbol');
            return;
        }

        toggleLoading(true);

        $.ajax({
            url: '{{ route("expiry.fetch") }}',
            type: 'GET',
            data: {
                symbol: symbol,
                from_date: fromDate,
                to_date: toDate
            },
            success: function (response) {
                console.log('Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    currentData = response.data;
                    displayTable();
                    updateStatistics(response.data);
                } else {
                    const message = response.message || 'No data available for the selected filters.';
                    $('#data-tbody').html(`
                        <tr>
                            <td colspan="11" class="text-center">
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
            error: function (xhr, status, error) {
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                $('#data-tbody').html(`
                    <tr>
                        <td colspan="11" class="text-center">
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
            $('#data-tbody').html(`
                <tr>
                    <td colspan="11" class="text-center text-muted">
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
        let currentTrend = null;

        currentData.forEach(function (row) {
            // PERSISTENT SUPERTREND SIGNAL (same logic as Futures)
            let signal = row.signal || 'HOLD';
            let signalClass = 'badge-hold';
            let signalIcon = '→';
            
            if (signal === 'BUY') {
                currentTrend = 'BUY';
                signalClass = 'badge-buy';
                signalIcon = '▲';
            } else if (signal === 'SELL') {
                currentTrend = 'SELL';
                signalClass = 'badge-sell';
                signalIcon = '▼';
            } else if (row.direction === 'UP' && currentTrend === 'BUY') {
                signal = 'BUY';
                signalClass = 'badge-buy';
                signalIcon = '▲';
            } else if (row.direction === 'DOWN' && currentTrend === 'SELL') {
                signal = 'SELL';
                signalClass = 'badge-sell';
                signalIcon = '▼';
            }

            // Direction badge
            const directionClass = row.direction === 'UP' ? 'uptrend' : 'downtrend';
            const directionIcon = row.direction === 'UP' ? '↑' : '↓';

            // Safe number formatting
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
                    <td><span class="${directionClass}">${directionIcon} ${row.direction || '-'}</span></td>
                    <td><span class="badge ${signalClass}"><span class="signal-icon">${signalIcon}</span> ${signal}</span></td>
                </tr>
            `;
        });

        $('#data-tbody').html(html);
    }

    function updateStatistics(data) {
        if (!data || data.length === 0) {
            resetStatistics();
            return;
        }

        const buyCount = data.filter(r => r.signal === 'BUY').length;
        const sellCount = data.filter(r => r.signal === 'SELL').length;
        
        const latestRecord = data[0];
        let currentTrendText = '-';
        let currentTrendClass = '';
        
        if (latestRecord && latestRecord.direction) {
            if (latestRecord.direction === 'UP') {
                currentTrendText = 'Uptrend';
                currentTrendClass = 'uptrend';
            } else if (latestRecord.direction === 'DOWN') {
                currentTrendText = 'Downtrend';
                currentTrendClass = 'downtrend';
            }
        }

        $('#total_records').text(data.length);
        $('#buy_signals').text(buyCount);
        $('#sell_signals').text(sellCount);
        $('#current_trend').html(`<span class="${currentTrendClass}">${currentTrendText}</span>`);
    }

    function resetStatistics() {
        $('#total_records').text('0');
        $('#buy_signals').text('0');
        $('#sell_signals').text('0');
        $('#current_trend').html('-');
    }

    function exportToCSV() {
        const symbol = $('#symbol_filter').val();
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();

        if (!symbol) {
            alert('Please select a symbol first');
            return;
        }

        const params = new URLSearchParams({
            symbol: symbol,
            from_date: fromDate || '',
            to_date: toDate || ''
        });

        window.location.href = '{{ route("expiry.export") }}?' + params.toString();
    }

    $(document).ready(function () {
        $('#load_data').click(function () {
            loadExpiryData();
        });

        $('#refresh_data').click(function () {
            if ($('#symbol_filter').val()) {
                loadExpiryData();
            } else {
                alert('Please select a symbol first');
            }
        });

        $('#export_csv').click(function () {
            exportToCSV();
        });

        $('#symbol_filter').change(function () {
            if ($(this).val()) {
                loadExpiryData();
            } else {
                currentData = [];
                $('#data-tbody').html(`
                    <tr>
                        <td colspan="11" class="text-center text-muted">
                            <div class="no-data-message">
                                <i class="fas fa-chart-line"></i>
                                <p>Select a symbol and click "Load Data"</p>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
            }
        });

        // Manual Fetch Button
        $('#manual_fetch_btn').click(function () {
            if (!confirm('This will fetch the latest 1-minute data from Zerodha for expiring symbols. Continue?')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true);
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Fetching...');

            const $status = $('<div class="alert alert-info fetch-status">Fetching latest expiry data from Zerodha...</div>');
            $('body').append($status);

            $.ajax({
                url: '{{ route("expiry.manual-fetch") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function (response) {
                    $status.removeClass('alert-info').addClass('alert-success');
                    $status.html('<i class="fas fa-check-circle"></i> ' + response.message);
                    
                    setTimeout(() => {
                        $status.fadeOut(() => $status.remove());
                        if ($('#symbol_filter').val()) {
                            loadExpiryData();
                        }
                    }, 3000);
                },
                error: function (xhr) {
                    const message = xhr.responseJSON?.message || 'Error fetching data';
                    $status.removeClass('alert-info').addClass('alert-danger');
                    $status.html('<i class="fas fa-exclamation-circle"></i> ' + message);
                    
                    setTimeout(() => {
                        $status.fadeOut(() => $status.remove());
                    }, 5000);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fas fa-sync-alt"></i> Fetch Latest Data');
                }
            });
        });
    });
</script>
@endpush