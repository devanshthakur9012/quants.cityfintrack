@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: center !important;
        padding: 10px 8px !important;
        font-size: 0.85rem;
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

    .signal-buy-ce {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .signal-buy-pe {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        color: white;
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }

    .signal-no {
        background-color: #6c757d;
        color: white;
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
        display: inline-block;
    }

    .price-up {
        color: #28a745;
        font-weight: 700;
    }

    .price-down {
        color: #dc3545;
        font-weight: 700;
    }

    .oi-positive {
        color: #28a745;
        font-weight: 700;
    }

    .oi-negative {
        color: #dc3545;
        font-weight: 700;
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

    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .data-cell {
        line-height: 1.8;
    }

    .order-yes {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 12px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(40, 167, 69, 0.4);
    }

    .order-no {
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 12px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(108, 117, 125, 0.4);
    }

    .new-logic-badge {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        margin-left: 5px;
    }

    /* ✅ NEW: Lock status badge */
    .lock-badge {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
        margin-left: 5px;
        display: inline-block;
    }

    .lock-badge.unlocked {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }} <span class="new-logic-badge">LOCKED PRICE</span></h4>
                </div>
                <div>
                    <a href="{{ route('oiiv-auto.config') }}" class="btn btn-light">
                        <i class="fas fa-cog"></i> Manage Configs
                    </a>
                </div>
            </div>
        </div>

        <!-- NEW Logic Info Alert -->
        <div class="alert" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; margin-bottom: 25px;">
            <h6 style="color: white; margin-bottom: 10px;"><i class="fas fa-lock"></i> <strong>Fixed Candle Logic (9:30 - 3:00):</strong></h6>
            <div class="row">
                <div class="col-md-6">
                    <small><strong>🟢 BULLISH (BUY CE):</strong></small>
                    <ul style="font-size: 0.85rem; margin-top: 5px; margin-bottom: 0;">
                        <li>Price ↑ + OI ↓ = Short Covering</li>
                        <li>Price ↑ + OI ↑ = Long Buildup</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <small><strong>🔴 BEARISH (BUY PE):</strong></small>
                    <ul style="font-size: 0.85rem; margin-top: 5px; margin-bottom: 0;">
                        <li>Price ↓ + OI ↓ = Long Unwinding</li>
                        <li>Price ↓ + OI ↑ = Short Buildup</li>
                    </ul>
                </div>
            </div>
            <hr style="border-color: rgba(255,255,255,0.3); margin: 15px 0;">
            <small><i class="fas fa-info-circle"></i> <strong>Note:</strong> Close price is LOCKED at 3:00 PM. All calculations use the same locked price for consistency.</small>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="from_date"><i class="fas fa-calendar-alt"></i> From Date:</label>
                    <input type="date" id="from_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-3">
                    <label for="to_date"><i class="fas fa-calendar-alt"></i> To Date:</label>
                    <input type="date" id="to_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-6">
                    <label for="symbol_filter"><i class="fas fa-filter"></i> Symbols (Optional):</label>
                    <select id="symbol_filter" class="form-control" multiple size="3">
                        <!-- Populated via AJAX -->
                    </select>
                    <small style="color: rgba(255,255,255,0.8);">Leave empty for all symbols</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 text-center">
                    <button type="button" id="run_analysis" class="btn btn-light btn-lg" style="min-width: 180px;">
                        <i class="fas fa-search"></i> View Data
                    </button>
                    <button type="button" id="clear_cache" class="btn btn-warning btn-lg" style="min-width: 180px;">
                        <i class="fas fa-sync-alt"></i> Refresh Cache
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg" style="min-width: 180px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total Records</small>
                    <strong id="total_records" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>BUY CE Signals</small>
                    <strong id="buy_ce_count" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #dc3545;">
                    <small>BUY PE Signals</small>
                    <strong id="buy_pe_count" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color: #6c757d;">
                    <small>No Signal</small>
                    <strong id="no_signal_count" style="color: #6c757d;">0</strong>
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
                            <th>Symbol</th>
                            <th>FUT Symbol</th>
                            <th>Price Movement <span class="new-logic-badge">9:30-3:00</span></th>
                            <th>OI Change</th>
                            <th>Signal</th>
                            <th>Reason</th>
                            <th>Order Picked</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-chart-line" style="font-size: 3rem; opacity: 0.5;"></i>
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

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    $(document).ready(function() {
        loadSymbols();
        
        // Auto-load today's data
        setTimeout(function() {
            runAnalysis();
        }, 500);
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

        if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
        }

        toggleLoading(true);
        analysisData = [];

        $.ajax({
            url: '{{ route("oiiv-auto.analyze") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols
            },
            success: function (response) {
                console.log('Analysis Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    analysisData = response.data;
                    displayAnalysisTable();
                    updateStatistics();
                } else {
                    $('#analysis-tbody').html(`
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle" style="color: #17a2b8; font-size: 3rem;"></i>
                                    <p class="text-info" style="margin-top: 20px;">${response.message || 'No data found'}</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Analysis Error:', error);
                $('#analysis-tbody').html(`
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545; font-size: 3rem;"></i>
                                <p class="text-danger" style="margin-top: 20px;">Error loading data</p>
                            </td>
                        </tr>
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
            // ✅ Lock status badge
            let lockBadge = '';
            if (row.is_price_locked) {
                lockBadge = `<span class="lock-badge" title="Price locked at ${row.lock_time}">🔒 LOCKED</span>`;
            } else {
                lockBadge = `<span class="lock-badge unlocked">⏳ PENDING</span>`;
            }
            
            // Price direction
            let priceIcon = row.price_direction === 'UP' ? '↑' : (row.price_direction === 'DOWN' ? '↓' : '→');
            let priceClass = row.price_direction === 'UP' ? 'price-up' : (row.price_direction === 'DOWN' ? 'price-down' : '');
            
            // OI direction
            let oiClass = row.oi_direction === 'POSITIVE' ? 'oi-positive' : (row.oi_direction === 'NEGATIVE' ? 'oi-negative' : '');
            let oiSign = row.oi_change_pct > 0 ? '+' : '';
            
            // Signal badge
            let signalBadge = '';
            if (row.signal === 'BUY_CE') {
                signalBadge = '<span class="signal-buy-ce">🟢 BUY CE</span>';
            } else if (row.signal === 'BUY_PE') {
                signalBadge = '<span class="signal-buy-pe">🔴 BUY PE</span>';
            } else {
                signalBadge = '<span class="signal-no">⚪ NO SIGNAL</span>';
            }
            
            // Order picked
            let orderBadge = row.order_picked === 'YES' 
                ? '<span class="order-yes">✅ YES</span>' 
                : '<span class="order-no">❌ NO</span>';

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.date}</strong></td>
                    <td><strong style="color: #667eea;">${row.symbol}</strong></td>
                    <td><small class="text-muted">${row.fut_symbol}</small></td>
                    
                    <!-- Price Movement -->
                    <td class="data-cell">
                        ${lockBadge}
                        <div class="${priceClass}" style="font-size: 0.9rem; margin-top: 5px;">
                            ${priceIcon} ₹${Math.abs(row.price_change)} (${row.price_change_percent}%)
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            9:30: ₹${row.open_price} → 3:00: ₹${row.current_price}
                        </small>
                    </td>
                    
                    <!-- OI Change -->
                    <td class="${oiClass}" style="font-size: 0.95rem; font-weight: 700;">
                        ${oiSign}${row.oi_change_pct}%
                    </td>
                    
                    <!-- Signal -->
                    <td>${signalBadge}</td>
                    
                    <!-- Reason -->
                    <td>
                        <small><strong>${row.signal_scenario}</strong></small><br>
                        <small class="text-muted">${row.signal_reason}</small>
                    </td>
                    
                    <!-- Order Picked -->
                    <td>${orderBadge}</td>
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

        const totalRecords = analysisData.length;
        let buyCECount = analysisData.filter(row => row.signal === 'BUY_CE').length;
        let buyPECount = analysisData.filter(row => row.signal === 'BUY_PE').length;
        let noSignalCount = analysisData.filter(row => row.signal === 'NO_SIGNAL').length;

        $('#total_records').text(totalRecords);
        $('#buy_ce_count').text(buyCECount);
        $('#buy_pe_count').text(buyPECount);
        $('#no_signal_count').text(noSignalCount);
    }

    function resetStatistics() {
        $('#total_records').text('0');
        $('#buy_ce_count').text('0');
        $('#buy_pe_count').text('0');
        $('#no_signal_count').text('0');
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter').val('');
        
        analysisData = [];
        $('#analysis-tbody').html(`
            <tr>
                <td colspan="9" class="text-center py-5">
                    <div class="no-data-message">
                        <i class="fas fa-chart-line" style="font-size: 3rem; opacity: 0.5;"></i>
                        <p style="font-size: 1.1rem; margin-top: 20px;">Click <strong>"View Data"</strong> to load signals</p>
                    </div>
                </td>
            </tr>
        `);
        resetStatistics();
        
        setTimeout(function() {
            runAnalysis();
        }, 300);
    }

    $(document).ready(function () {
        $('#run_analysis').click(function () {
            runAnalysis();
        });

        $('#reset_filters').click(function () {
            resetFilters();
        });
    });

    // ✅ NEW: Clear cache handler
    $('#clear_cache').click(function() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        const selectedSymbols = $('#symbol_filter').val() || [];
        
        if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
        }
        
        if (!confirm('This will clear locked prices and re-fetch from API. Continue?')) {
            return;
        }
        
        toggleLoading(true);
        
        $.ajax({
            url: '{{ route("oiiv-auto.clear-cache") }}',
            type: 'POST',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                toggleLoading(false);
                
                if (response.success) {
                    // Show success message
                    alert(response.message);
                    
                    // Auto-reload data
                    runAnalysis();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                toggleLoading(false);
                alert('Error clearing cache: ' + error);
                console.error('Cache clear error:', error);
            }
        });
    });
</script>
@endpush