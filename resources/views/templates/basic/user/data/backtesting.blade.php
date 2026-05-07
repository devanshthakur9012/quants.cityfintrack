@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container-fluid content-container">
        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-chart-line"></i> Backtesting Analysis (Order Simulation)
                </h5>
                <p class="text-muted small mb-0">Test trading strategies on historical data with real order placement</p>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form id="backtestForm">
                    <div class="row">

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

                        <div class="col-lg-3 col-md-6 form-group">
                            <label class="required">Signal Strategy<sup class="text--danger">*</sup></label>
                            <select class="form--control" id="strategy" name="strategy" required>
                                <option value="SUPERTREND_VWAP">BOTH (Supertrend + VWAP)</option>
                                <option value="SUPERTREND">Supertrend Only</option>
                                <option value="VWAP">VWAP Only</option>
                                <option value="RSI">RSI Only</option>
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6 form-group">
                            <label class="required">Interval<sup class="text--danger">*</sup></label>
                            <select class="form--control" id="interval" name="interval" required>
                                <option value="minute">1 Minute</option>
                                <option value="5minute" selected>5 Minutes</option>
                                <option value="15minute">15 Minutes</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-3 col-md-6 form-group">
                            <label>Symbols (Optional)</label>
                            <select class="form--control select2-multi" id="symbols" name="symbols[]" 
                                    multiple="multiple">
                                @foreach($monitoredSymbols as $symbol)
                                    <option value="{{ $symbol->trading_symbol }}">
                                        {{ $symbol->trading_symbol }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Leave empty for all symbols</small>
                        </div>

                        <div class="col-lg-3 col-md-6 form-group">
                            <label class="required">Option Series<sup class="text--danger">*</sup></label>
                            <select class="form--control" id="optionSeries" name="option_series" required>
                                <option value="current" selected>Current Series</option>
                                <option value="next">Next Series</option>
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6 form-group">
                            <label>Quality Filter</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" 
                                       id="enableQualityFilter" name="enable_quality_filter" checked>
                                <label class="form-check-label" for="enableQualityFilter">
                                    Enable Quality Momentum Filter
                                </label>
                            </div>
                            <small class="text-muted">Filter weak signals</small>
                        </div>

                        <div class="col-lg-3 col-md-6 form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-secondary" id="setDefaultDatesBtn" 
                                        title="Set last 7 days">
                                    <i class="las la-calendar"></i> Last 7 Days
                                </button>
                                <button type="submit" class="btn btn--base" id="runBacktestBtn">
                                    <i class="las la-play"></i> Run Backtest
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <hr>

                <!-- Results Summary -->
                <div id="resultsSummary" style="display:none;">
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-widget bg--primary">
                                <div class="dashboard-widget__content">
                                    <h4 class="dashboard-widget__number text-white" id="totalSignals">0</h4>
                                    <span class="dashboard-widget__text text-white">Total Signals</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-widget bg--success">
                                <div class="dashboard-widget__content">
                                    <h4 class="dashboard-widget__number text-white" id="buySignals">0</h4>
                                    <span class="dashboard-widget__text text-white">BUY Signals (CE)</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-widget bg--danger">
                                <div class="dashboard-widget__content">
                                    <h4 class="dashboard-widget__number text-white" id="sellSignals">0</h4>
                                    <span class="dashboard-widget__text text-white">SELL Signals (PE)</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-widget bg--info">
                                <div class="dashboard-widget__content">
                                    <h4 class="dashboard-widget__number text-white" id="ordersPlaced">0</h4>
                                    <span class="dashboard-widget__text text-white">Orders Placed</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="table-responsive" id="resultsTableContainer" style="display:none;">
                    <table class="table custom--table table-sm" id="resultsTable">
                        <thead>
                            <tr>
                                <th>Signal Time</th>
                                <th>Future Symbol</th>
                                <th>Signal</th>
                                <th>Strategy</th>
                                <th>Future Price</th>
                                <th>Option Symbol</th>
                                <th>Strike</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="resultsTableBody">
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div class="text-center py-5" id="emptyState">
                    <i class="las la-chart-bar text-muted" style="font-size: 80px;"></i>
                    <h5 class="text-muted mt-3">No Backtest Results</h5>
                    <p class="text-muted">Select date range and click "Run Backtest" to see signals</p>
                </div>

                <!-- Loading State -->
                <div class="text-center py-5" id="loadingState" style="display:none;">
                    <div class="spinner-border text--base" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Running backtest analysis...</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Order Placement Modal -->
<div class="modal fade" id="placeOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg--success">
                <h5 class="modal-title text-white">
                    <i class="las la-shopping-cart"></i> Place Order (1-Click Buy)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="orderFutureSymbol">
                <input type="hidden" id="orderSignalTime">
                <input type="hidden" id="orderSignalType">
                <input type="hidden" id="orderOptionSymbol">
                <input type="hidden" id="orderStrikePrice">
                <input type="hidden" id="orderFuturePrice">

                <!-- Signal Summary -->
                <div class="alert alert--info mb-4">
                    <h6 class="mb-3"><i class="las la-info-circle"></i> Signal Details</h6>
                    <div class="row">
                        <div class="col-md-3 col-6 mb-2">
                            <small class="text-muted d-block">Future Symbol</small>
                            <strong id="modalFutureSymbol">-</strong>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <small class="text-muted d-block">Signal Type</small>
                            <span id="modalSignalType" class="badge">-</span>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <small class="text-muted d-block">Signal Time</small>
                            <strong id="modalSignalTime">-</strong>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <small class="text-muted d-block">Future Price</small>
                            <strong class="text--primary">₹<span id="modalFuturePrice">0.00</span></strong>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6 col-6 mb-2">
                            <small class="text-muted d-block">Option Symbol</small>
                            <strong id="modalOptionSymbol">-</strong>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <small class="text-muted d-block">Strike Price</small>
                            <strong>₹<span id="modalStrikePrice">0</span></strong>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <small class="text-muted d-block">Option Type</small>
                            <span id="modalOptionType" class="badge">-</span>
                        </div>
                    </div>
                </div>

                <!-- Config Info -->
                <div class="alert" style="background: #f0fdf4; border-color: #86efac;">
                    <h6 class="mb-2"><i class="las la-cog"></i> Active Configuration</h6>
                    <p class="mb-0 small">
                        This order will use your latest active configuration for the broker linked to this symbol.
                        Quantities, product type, and order type will be applied from your config.
                    </p>
                </div>

                <div class="alert alert--warning">
                    <i class="las la-exclamation-triangle"></i>
                    <strong>Important:</strong> This will place a REAL order on Zerodha. 
                    Make sure the signal is valid and you have sufficient funds.
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="forceOrder" value="1">
                    <label class="form-check-label" for="forceOrder">
                        <strong>Force Order</strong> - Place order even if one already exists for this signal
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="las la-times"></i> Cancel
                </button>
                <button type="button" class="btn btn--success" id="confirmPlaceOrderBtn">
                    <i class="las la-shopping-cart"></i> Confirm & Place Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Square Off Modal -->
<div class="modal fade" id="squareOffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg--danger">
                <h5 class="modal-title text-white">
                    <i class="las la-times-circle"></i> Square Off Position
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="squareOffOrderId">

                <!-- Position Summary -->
                <div class="alert alert--info mb-4">
                    <h6 class="mb-3"><i class="las la-info-circle"></i> Position Details</h6>
                    <div class="row">
                        <div class="col-md-4 col-6 mb-2">
                            <small class="text-muted d-block">Option Symbol</small>
                            <strong id="sqModalOptionSymbol">-</strong>
                        </div>
                        <div class="col-md-4 col-6 mb-2">
                            <small class="text-muted d-block">Order ID</small>
                            <strong id="sqModalOrderId">-</strong>
                        </div>
                        <div class="col-md-4 col-6 mb-2">
                            <small class="text-muted d-block">Status</small>
                            <span class="badge badge--success">Executed</span>
                        </div>
                    </div>
                </div>

                <div class="alert" style="background: #fef3c7; border-color: #fcd34d;">
                    <h6 class="mb-2"><i class="las la-info-circle"></i> Square Off Details</h6>
                    <p class="mb-0 small">
                        This will place MARKET SELL orders to square off your position.
                        Orders will be split into 20 qty chunks if needed.
                    </p>
                </div>

                <div class="alert alert--warning">
                    <i class="las la-exclamation-triangle"></i>
                    <strong>Confirmation Required:</strong> This will SELL all executed quantities at market price.
                    Make sure you want to exit this position.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="las la-times"></i> Cancel
                </button>
                <button type="button" class="btn btn--danger" id="confirmSquareOffBtn">
                    <i class="las la-check"></i> Confirm Square Off
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    let currentResults = [];

    // Initialize select2
    $('.select2-multi').select2({
        placeholder: 'Select symbols (optional)',
        allowClear: true,
        width: '100%'
    });

    // ✅ ADD: Clear error state when dates change
    $('#from_date, #to_date').on('change', function() {
        const inputId = $(this).attr('id');
        if ($(this).val()) {
            $(`#${inputId}Helper`).hide();
            $(this).removeClass('border-danger');
        }
    });

    // ✅ ADD: Manual date setter button
    $('#setDefaultDatesBtn').on('click', function() {
        console.log('🔄 [MANUAL] Setting default dates...');
        initializeDates();
        
        iziToast.info({
            message: 'Dates set to last 7 days',
            position: 'topRight',
            timeout: 2000
        });
    });

    // Run backtest
    $('#backtestForm').on('submit', function(e) {
        e.preventDefault();
        runBacktest();
    });

    function runBacktest() {
        // ✅ VALIDATE DATES FIRST
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();

        // ✅ SHOW/HIDE HELPERS
        if (!fromDate) {
            $('#fromDateHelper').show();
            $('#from_date').addClass('border-danger');
        } else {
            $('#fromDateHelper').hide();
            $('#from_date').removeClass('border-danger');
        }

        if (!toDate) {
            $('#toDateHelper').show();
            $('#to_date').addClass('border-danger');
        } else {
            $('#toDateHelper').hide();
            $('#to_date').removeClass('border-danger');
        }

        if (!fromDate || !toDate) {
            iziToast.error({
                message: 'Please select both From and To dates',
                position: 'topRight'
            });
            return;
        }

        // ✅ VALIDATE: From date should be before or equal to To date
        if (new Date(fromDate) > new Date(toDate)) {
            iziToast.error({
                message: 'From Date must be before or equal to To Date',
                position: 'topRight'
            });
            $('#from_date').addClass('border-danger');
            $('#to_date').addClass('border-danger');
            return;
        }

        console.log('📅 [DEBUG] From Date:', fromDate);
        console.log('📅 [DEBUG] To Date:', toDate);

        $('#loadingState').show();
        $('#emptyState').hide();
        $('#resultsTableContainer').hide();
        $('#resultsSummary').hide();
        $('#runBacktestBtn').prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Processing...');

        const formData = {
            from_date: fromDate,
            to_date: toDate,
            strategy: $('#strategy').val(),
            interval: $('#interval').val(),
            option_series: $('#optionSeries').val(),
            enable_quality_filter: $('#enableQualityFilter').is(':checked'),
            symbols: $('#symbols').val() || []
        };

        console.log('📦 [DEBUG] Form Data:', formData);

        $.ajax({
            url: '{{ route("data.backtesting-fetch") }}',
            type: 'GET',
            data: formData,
            traditional: true, // ✅ ADDED: Proper array handling for 'symbols'
            success: function(response) {
                console.log('✅ [DEBUG] Response:', response);
                $('#loadingState').hide();
                $('#runBacktestBtn').prop('disabled', false).html('<i class="las la-play"></i> Run Backtest');

                if (response.success && response.data.length > 0) {
                    currentResults = response.data;
                    displayResults(response.data);
                    
                    iziToast.success({
                        message: response.message,
                        position: 'topRight'
                    });
                } else {
                    $('#emptyState').show();
                    iziToast.info({
                        message: response.message || 'No signals found',
                        position: 'topRight'
                    });
                }
            },
            error: function(xhr) {
                console.error('❌ [DEBUG] Error:', xhr);
                console.error('❌ [DEBUG] Response:', xhr.responseJSON);
                
                $('#loadingState').hide();
                $('#emptyState').show();
                $('#runBacktestBtn').prop('disabled', false).html('<i class="las la-play"></i> Run Backtest');

                let message = 'Error running backtest';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                iziToast.error({
                    message: message,
                    position: 'topRight',
                    timeout: 5000
                });
            }
        });
    }

    function displayResults(data) {
        // Update summary
        const buyCount = data.filter(d => d.signal_type === 'BUY').length;
        const sellCount = data.filter(d => d.signal_type === 'SELL').length;
        const ordersPlacedCount = data.filter(d => d.has_order).length;

        $('#totalSignals').text(data.length);
        $('#buySignals').text(buyCount);
        $('#sellSignals').text(sellCount);
        $('#ordersPlaced').text(ordersPlacedCount);
        $('#resultsSummary').show();

        // Build table
        let tableHtml = '';
        data.forEach(function(signal, index) {
            let signalClass = signal.signal_type === 'BUY' ? 'badge--success' : 'badge--danger';
            let optionTypeClass = signal.option_type === 'CE' ? 'badge--success' : 'badge--danger';
            
            let statusBadge = '';
            let actionButtons = '';

            if (signal.has_order) {
                if (signal.is_order_placed) {
                    statusBadge = '<span class="badge badge--success"><i class="las la-check"></i> Placed</span>';
                    actionButtons = `
                        <button class="btn btn-sm btn--danger square-off-btn" 
                                data-order-id="${signal.order_id}"
                                data-option-symbol="${signal.option_symbol}"
                                title="Square Off">
                            <i class="las la-times"></i> Square Off
                        </button>
                    `;
                } else {
                    statusBadge = '<span class="badge badge--warning"><i class="las la-clock"></i> Pending</span>';
                    actionButtons = '<small class="text-muted">Order created, not placed yet</small>';
                }
            } else {
                statusBadge = '<span class="badge badge--secondary"><i class="las la-minus"></i> Not Placed</span>';
                actionButtons = `
                    <button class="btn btn-sm btn--success place-order-btn" 
                            data-signal='${JSON.stringify(signal).replace(/'/g, "&apos;")}'
                            title="Place Order">
                        <i class="las la-shopping-cart"></i> Place Order
                    </button>
                `;
            }

            tableHtml += `
                <tr>
                    <td class="small">${signal.signal_time}</td>
                    <td><strong>${signal.future_symbol}</strong></td>
                    <td><span class="badge ${signalClass}">${signal.signal_type}</span></td>
                    <td class="small">${signal.strategy}</td>
                    <td class="text-end">₹${Number(signal.future_price).toFixed(2)}</td>
                    <td><small>${signal.option_symbol}</small></td>
                    <td class="text-end">₹${Number(signal.strike_price).toFixed(0)}</td>
                    <td class="text-center">${statusBadge}</td>
                    <td class="text-center">${actionButtons}</td>
                </tr>
            `;
        });

        $('#resultsTableBody').html(tableHtml);
        $('#resultsTableContainer').show();
    }

    // Show place order modal
    $(document).on('click', '.place-order-btn', function() {
        const signalJson = $(this).attr('data-signal').replace(/&apos;/g, "'");
        const signal = JSON.parse(signalJson);

        $('#orderFutureSymbol').val(signal.future_symbol);
        $('#orderSignalTime').val(signal.signal_time);
        $('#orderSignalType').val(signal.signal_type);
        $('#orderOptionSymbol').val(signal.option_symbol);
        $('#orderStrikePrice').val(signal.strike_price);
        $('#orderFuturePrice').val(signal.future_price);

        $('#modalFutureSymbol').text(signal.future_symbol);
        $('#modalSignalType').text(signal.signal_type)
            .removeClass('badge--success badge--danger')
            .addClass(signal.signal_type === 'BUY' ? 'badge--success' : 'badge--danger');
        $('#modalSignalTime').text(signal.signal_time);
        $('#modalFuturePrice').text(Number(signal.future_price).toFixed(2));
        $('#modalOptionSymbol').text(signal.option_symbol);
        $('#modalStrikePrice').text(Number(signal.strike_price).toFixed(0));
        $('#modalOptionType').text(signal.option_type)
            .removeClass('badge--success badge--danger')
            .addClass(signal.option_type === 'CE' ? 'badge--success' : 'badge--danger');

        $('#forceOrder').prop('checked', false);
        $('#placeOrderModal').modal('show');
    });

    // Confirm place order
    $('#confirmPlaceOrderBtn').on('click', function() {
        const data = {
            future_symbol: $('#orderFutureSymbol').val(),
            signal_time: $('#orderSignalTime').val(),
            signal_type: $('#orderSignalType').val(),
            option_symbol: $('#orderOptionSymbol').val(),
            strike_price: $('#orderStrikePrice').val(),
            future_price: $('#orderFuturePrice').val(),
            force: $('#forceOrder').is(':checked'),
            _token: '{{ csrf_token() }}'
        };

        $(this).prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Placing...');

        $.ajax({
            url: '{{ route("data.place-backtest-order") }}',
            type: 'POST',
            data: data,
            success: function(response) {
                $('#placeOrderModal').modal('hide');
                $('#confirmPlaceOrderBtn').prop('disabled', false)
                    .html('<i class="las la-shopping-cart"></i> Confirm & Place Order');

                iziToast.success({
                    message: response.message,
                    position: 'topRight',
                    timeout: 5000
                });

                // Refresh results
                runBacktest();
            },
            error: function(xhr) {
                $('#confirmPlaceOrderBtn').prop('disabled', false)
                    .html('<i class="las la-shopping-cart"></i> Confirm & Place Order');

                let message = 'Error placing order';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                iziToast.error({
                    message: message,
                    position: 'topRight',
                    timeout: 5000
                });
            }
        });
    });

    // Show square off modal
    $(document).on('click', '.square-off-btn', function() {
        const orderId = $(this).data('order-id');
        const optionSymbol = $(this).data('option-symbol');

        $('#squareOffOrderId').val(orderId);
        $('#sqModalOrderId').text('#' + orderId);
        $('#sqModalOptionSymbol').text(optionSymbol);

        $('#squareOffModal').modal('show');
    });

    // Confirm square off
    $('#confirmSquareOffBtn').on('click', function() {
        const orderId = $('#squareOffOrderId').val();

        $(this).prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Processing...');

        $.ajax({
            url: '{{ route("data.square-off-backtest-order") }}',
            type: 'POST',
            data: {
                order_id: orderId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $('#squareOffModal').modal('hide');
                $('#confirmSquareOffBtn').prop('disabled', false)
                    .html('<i class="las la-check"></i> Confirm Square Off');

                iziToast.success({
                    message: response.message,
                    position: 'topRight',
                    timeout: 5000
                });

                // Refresh results
                runBacktest();
            },
            error: function(xhr) {
                $('#confirmSquareOffBtn').prop('disabled', false)
                    .html('<i class="las la-check"></i> Confirm Square Off');

                let message = 'Error squaring off';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                iziToast.error({
                    message: message,
                    position: 'topRight',
                    timeout: 5000
                });
            }
        });
    });

    // Set default dates (last 7 days) - simplified without max constraint
    function initializeDates() {
        const today = new Date();
        const lastWeek = new Date();
        lastWeek.setDate(today.getDate() - 7);

        const todayStr = today.toISOString().split('T')[0];
        const lastWeekStr = lastWeek.toISOString().split('T')[0];

        console.log('📅 [INIT] Setting default dates:', lastWeekStr, 'to', todayStr);

        $('#from_date').val(lastWeekStr).trigger('change');
        $('#to_date').val(todayStr).trigger('change');

        // Quick verification
        setTimeout(function() {
            const fromCheck = $('#from_date').val();
            const toCheck = $('#to_date').val();
            
            if (fromCheck && toCheck) {
                console.log('✅ [INIT] Dates set successfully:', fromCheck, 'to', toCheck);
            } else {
                console.warn('⚠️ [INIT] Date initialization issue - please select manually');
            }
        }, 100);
    }

    // Initialize dates on page load
    initializeDates();
});
</script>
@endpush

@push('style')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
.dashboard-widget {
    padding: 20px;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.dashboard-widget.bg--primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.dashboard-widget.bg--success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.dashboard-widget.bg--danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.dashboard-widget.bg--info {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
}

.dashboard-widget__number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.dashboard-widget__text {
    font-size: 0.9rem;
    opacity: 0.9;
}

.badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
    border-radius: 4px;
    font-weight: 600;
}

.badge--success { background: #10b981; color: white; }
.badge--danger { background: #ef4444; color: white; }
.badge--warning { background: #f59e0b; color: white; }
.badge--secondary { background: #6b7280; color: white; }
.badge--info { background: #06b6d4; color: white; }

.text--success { color: #10b981 !important; font-weight: 600; }
.text--danger { color: #ef4444 !important; font-weight: 600; }
.text--primary { color: #3b82f6 !important; font-weight: 600; }

.alert--info { background: #dbeafe; border-color: #93c5fd; color: #1e40af; }
.alert--warning { background: #fef3c7; border-color: #fcd34d; color: #92400e; }

.modal-header.bg--success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.modal-header.bg--danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.btn--success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: white;
}

.btn--success:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: white;
}

.btn--danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border: none;
    color: white;
}

.btn--danger:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
}

.form-check-input:checked {
    background-color: #10b981;
    border-color: #10b981;
}

.table-sm td, .table-sm th {
    padding: 0.5rem;
    font-size: 0.85rem;
}

.select2-container--default .select2-selection--multiple {
    border: 1px solid #e5e7eb;
    border-radius: 5px;
}

.select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: var(--base-color);
}
</style>
@endpush