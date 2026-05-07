@extends($activeTemplate . 'layouts.master')

@section('content')
    <section class="pt-100 pb-100">
        <div class="container-fluid content-container">
            <div class="custom--card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="las la-hand-pointer"></i> Manual Order Placement (5-Min Signals)
                    </h5>
                    <p class="text-muted small mb-0">Today's signals - Click to place orders instantly</p>
                </div>
                <div class="card-body">
                    <!-- Info Alert -->
                    {{-- <div class="alert alert--info mb-4">
                        <h6 class="mb-2"><i class="las la-info-circle"></i> How It Works</h6>
                        <ul class="mb-0 small">
                            <li>Shows ALL today's 5-minute signals detected by the system (same as auto cron)</li>
                            <li>Click "Place Order" to execute real orders on your linked Zerodha account</li>
                            <li><strong>Signal LTP</strong> = Price at signal time | <strong>Current LTP</strong> = Live
                                price now</li>
                            <li>Orders use your active config settings (quantity, product type, etc.)</li>
                        </ul>
                    </div> --}}

                    <!-- Controls -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <button type="button" class="btn btn--base me-2" id="loadSignalsBtn">
                                <i class="las la-sync"></i> Load Today's Signals
                            </button>
                            <button type="button" class="btn btn-info" id="refreshBtn">
                                <i class="las la-redo"></i> Refresh LTPs
                            </button>
                        </div>
                    </div>

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
                                    <th>Signal LTP</th>
                                    <th>Current LTP</th>
                                    <th>Qty</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="resultsTableBody">
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div class="text-center py-5" id="emptyState">
                        <i class="las la-chart-bar text-muted" style="font-size: 80px;"></i>
                        <h5 class="text-muted mt-3">No Signals Yet</h5>
                        <p class="text-muted">Click "Load Today's Signals" to see available orders</p>
                    </div>

                    <!-- Loading State -->
                    <div class="text-center py-5" id="loadingState" style="display:none;">
                        <div class="spinner-border text--base" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Fetching signals...</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Order Modal -->
    <div class="modal fade" id="quickOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg--success">
                    <h5 class="modal-title text-white">
                        <i class="las la-shopping-cart"></i> Quick Order
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="orderData">

                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Future</small>
                            <strong id="qFutureSymbol">-</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Signal</small>
                            <span id="qSignalType" class="badge">-</span>
                        </div>
                        <div class="col-12 mb-2">
                            <small class="text-muted d-block">Option</small>
                            <strong id="qOptionSymbol">-</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Current LTP</small>
                            <strong class="text--primary">₹<span id="qCurrentLTP">0.00</span></strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Quantity</small>
                            <strong><span id="qQuantity">0</span> lots</strong>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="forceOrder">
                        <label class="form-check-label" for="forceOrder">
                            <strong>Force Order</strong> (Place even if exists)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn--success" id="confirmOrderBtn">
                        <i class="las la-check"></i> Place Order Now
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
    $(document).ready(function() {
        let currentSignals = [];

        // Load signals on page load
        loadSignals();

        $('#loadSignalsBtn').on('click', function() {
            loadSignals();
        });

        $('#refreshBtn').on('click', function() {
            if (currentSignals.length > 0) {
                updateAllLTPs();
            } else {
                loadSignals();
            }
        });

        function loadSignals() {
            $('#loadingState').show();
            $('#emptyState').hide();
            $('#resultsTableContainer').hide();
            $('#resultsSummary').hide();
            $('#loadSignalsBtn').prop('disabled', true).html(
                '<i class="las la-spinner la-spin"></i> Loading...');

            $.ajax({
                url: '{{ route('manual-orders.fetch') }}',
                type: 'GET',
                success: function(response) {
                    console.log('✅ Response:', response);
                    $('#loadingState').hide();
                    $('#loadSignalsBtn').prop('disabled', false).html(
                        '<i class="las la-sync"></i> Load Today\'s Signals');

                    if (response.success && response.data.length > 0) {
                        currentSignals = response.data;
                        displaySignals(response.data);

                        // ✅ Start progressive LTP updates (Signal LTP first, then Live LTP)
                        progressivelyUpdateSignalLTPs();
                        setTimeout(() => {
                            progressivelyUpdateLiveLTPs();
                        }, 1000); // Wait 1 sec before starting live LTP fetch

                        iziToast.success({
                            message: response.message,
                            position: 'topRight'
                        });
                    } else {
                        $('#emptyState').show();
                        iziToast.info({
                            message: response.message || 'No signals found for today',
                            position: 'topRight'
                        });
                    }
                },
                error: function(xhr) {
                    console.error('❌ Error:', xhr);
                    $('#loadingState').hide();
                    $('#emptyState').show();
                    $('#loadSignalsBtn').prop('disabled', false).html(
                        '<i class="las la-sync"></i> Load Today\'s Signals');

                    iziToast.error({
                        message: xhr.responseJSON?.message || 'Error loading signals',
                        position: 'topRight'
                    });
                }
            });
        }

        // ✅ Progressive Signal LTP Updates (10 signals at a time)
        function progressivelyUpdateSignalLTPs() {
            const BATCH_SIZE = 10;
            const brokerGroups = {};

            // Group signals by broker
            currentSignals.forEach(signal => {
                if (!brokerGroups[signal.broker_id]) {
                    brokerGroups[signal.broker_id] = [];
                }
                brokerGroups[signal.broker_id].push(signal);
            });

            // Process each broker's signals
            Object.keys(brokerGroups).forEach(brokerId => {
                const signals = brokerGroups[brokerId];

                // Split into batches of 10
                for (let i = 0; i < signals.length; i += BATCH_SIZE) {
                    const batch = signals.slice(i, i + BATCH_SIZE);
                    const signalData = batch.map(s => ({
                        token: s.option_token,
                        symbol: s.option_symbol,
                        timestamp: s.signal_timestamp
                    }));

                    setTimeout(() => {
                        fetchSignalLTPBatch(brokerId, signalData);
                    }, i / BATCH_SIZE * 3000); // 3 second delay between batches
                }
            });
        }

        function fetchSignalLTPBatch(brokerId, signalData) {
            $.ajax({
                url: '{{ route('manual-orders.fetch-signal-ltps') }}',
                type: 'POST',
                data: {
                    broker_id: brokerId,
                    signals: signalData,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Update signal_ltp in currentSignals
                        currentSignals.forEach(signal => {
                            if (response.data[signal.option_token]) {
                                signal.signal_ltp = response.data[signal.option_token];
                            }
                        });

                        // Refresh display
                        displaySignals(currentSignals);
                        console.log('✅ Updated Signal LTPs for', signalData.length, 'signals');
                    }
                },
                error: function(xhr) {
                    console.error('❌ Signal LTP Batch Error:', xhr);
                }
            });
        }

        // ✅ Progressive Live LTP Updates (20 tokens at a time)
        function progressivelyUpdateLiveLTPs() {
            const BATCH_SIZE = 20;
            const brokerGroups = {};

            // Group signals by broker
            currentSignals.forEach(signal => {
                if (!brokerGroups[signal.broker_id]) {
                    brokerGroups[signal.broker_id] = [];
                }
                brokerGroups[signal.broker_id].push(signal);
            });

            // Process each broker's signals
            Object.keys(brokerGroups).forEach(brokerId => {
                const signals = brokerGroups[brokerId];
                const tokens = signals.map(s => s.option_token);

                // Split into batches of 20
                for (let i = 0; i < tokens.length; i += BATCH_SIZE) {
                    const batch = tokens.slice(i, i + BATCH_SIZE);

                    setTimeout(() => {
                        fetchLiveLTPBatch(brokerId, batch);
                    }, i / BATCH_SIZE * 2000); // 2 second delay between batches
                }
            });
        }

        function fetchLiveLTPBatch(brokerId, tokens) {
            $.ajax({
                url: '{{ route('manual-orders.fetch-ltps') }}',
                type: 'POST',
                data: {
                    broker_id: brokerId,
                    tokens: tokens,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Update current_ltp in currentSignals
                        currentSignals.forEach(signal => {
                            if (response.data[signal.option_token]) {
                                signal.current_ltp = response.data[signal.option_token];
                            }
                        });

                        // Refresh display
                        displaySignals(currentSignals);
                        console.log('✅ Updated Live LTPs for', tokens.length, 'instruments');
                    }
                },
                error: function(xhr) {
                    console.error('❌ Live LTP Batch Error:', xhr);
                }
            });
        }

        function updateAllLTPs() {
            $('#refreshBtn').prop('disabled', true).html(
                '<i class="las la-spinner la-spin"></i> Refreshing...');
            progressivelyUpdateLiveLTPs(); // Only refresh live LTP (signal LTP never changes)
            setTimeout(() => {
                $('#refreshBtn').prop('disabled', false).html(
                    '<i class="las la-redo"></i> Refresh LTPs');
            }, 3000);
        }

        function displaySignals(data) {
    const buyCount = data.filter(d => d.signal_type === 'BUY').length;
    const sellCount = data.filter(d => d.signal_type === 'SELL').length;
    const ordersPlacedCount = data.filter(d => d.is_order_placed).length;

    $('#totalSignals').text(data.length);
    $('#buySignals').text(buyCount);
    $('#sellSignals').text(sellCount);
    $('#ordersPlaced').text(ordersPlacedCount);
    $('#resultsSummary').show();

    let tableHtml = '';
    data.forEach(function(signal) {
        const signalClass = signal.signal_type === 'BUY' ? 'badge--success' : 'badge--danger';
        
        // ✅ Safe number parsing
        const signalLTP = parseFloat(signal.signal_ltp) || 0;
        const currentLTP = parseFloat(signal.current_ltp) || 0;
        
        // Calculate difference with parsed values
        const ltpDiff = currentLTP - signalLTP;
        const ltpDiffClass = ltpDiff >= 0 ? 'text--success' : 'text--danger';
        const ltpDiffText = ltpDiff >= 0 ? '+' + ltpDiff.toFixed(2) : ltpDiff.toFixed(2);

        // ✅ Show loading for both Signal LTP and Current LTP
        const signalLTPDisplay = signalLTP === 0 ?
            '<small class="text-muted">Loading...</small>' :
            `₹${signalLTP.toFixed(2)}`;

        const currentLTPDisplay = currentLTP === 0 ?
            '<small class="text-muted">Loading...</small>' :
            `<strong>₹${currentLTP.toFixed(2)}</strong><small class="d-block ${ltpDiffClass}">${ltpDiffText}</small>`;

        let statusBadge = '';
        let actionButton = '';

        if (signal.is_order_placed) {
            statusBadge = `<span class="badge badge--success"><i class="las la-check"></i> Placed</span>
                   <small class="d-block text-muted mt-1">${signal.order_placed_at}</small>`;
            actionButton = '<small class="text-muted">Already executed</small>';
        } else if (signal.has_order) {
            statusBadge =
                '<span class="badge badge--warning"><i class="las la-clock"></i> Pending</span>';
            actionButton = `<button class="btn btn-sm btn--success place-order-btn w-100" 
                        data-signal='${JSON.stringify(signal).replace(/'/g, "&apos;")}'>
                        <i class="las la-shopping-cart"></i> Place Now
                    </button>`;
        } else {
            statusBadge =
                '<span class="badge badge--secondary"><i class="las la-minus"></i> Not Placed</span>';
            actionButton = `<button class="btn btn-sm btn--success place-order-btn w-100" 
                        data-signal='${JSON.stringify(signal).replace(/'/g, "&apos;")}'>
                        <i class="las la-shopping-cart"></i> Place Order
                    </button>`;
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
        <td class="text-end">${signalLTPDisplay}</td>
        <td class="text-end">${currentLTPDisplay}</td>
        <td class="text-center"><strong>${signal.quantity}</strong></td>
        <td class="text-center">${statusBadge}</td>
        <td>${actionButton}</td>
    </tr>
`;
    });

    $('#resultsTableBody').html(tableHtml);
    $('#resultsTableContainer').show();
}

        // ... rest of modal code stays same ...
        $(document).on('click', '.place-order-btn', function() {
            const signalJson = $(this).attr('data-signal').replace(/&apos;/g, "'");
            const signal = JSON.parse(signalJson);

            $('#orderData').val(JSON.stringify(signal));
            $('#qFutureSymbol').text(signal.future_symbol);
            $('#qSignalType').text(signal.signal_type)
                .removeClass('badge--success badge--danger')
                .addClass(signal.signal_type === 'BUY' ? 'badge--success' : 'badge--danger');
            $('#qOptionSymbol').text(signal.option_symbol);
            // $('#qCurrentLTP').text(signal.current_ltp === 0 ? 'Loading...' : signal.current_ltp.toFixed(2));

            const modalCurrentLTP = parseFloat(signal.current_ltp) || 0;
            $('#qCurrentLTP').text(modalCurrentLTP === 0 ? 'Loading...' : modalCurrentLTP.toFixed(2));

            $('#qQuantity').text(signal.quantity);
            $('#forceOrder').prop('checked', false);

            $('#quickOrderModal').modal('show');
        });

        $('#confirmOrderBtn').on('click', function() {
            const signal = JSON.parse($('#orderData').val());
            const force = $('#forceOrder').is(':checked');

            $(this).prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Placing...');

            $.ajax({
                url: '{{ route('manual-orders.place') }}',
                type: 'POST',
                data: {
                    config_id: signal.config_id,
                    symbol_id: signal.symbol_id,
                    future_symbol: signal.future_symbol,
                    signal_time: signal.signal_time,
                    signal_type: signal.signal_type,
                    option_symbol: signal.option_symbol,
                    option_token: signal.option_token,
                    strike_price: signal.strike_price,
                    future_price: signal.future_price,
                    force: force,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    $('#quickOrderModal').modal('hide');
                    $('#confirmOrderBtn').prop('disabled', false).html(
                        '<i class="las la-check"></i> Place Order Now');

                    iziToast.success({
                        message: response.message,
                        position: 'topRight',
                        timeout: 5000
                    });

                    loadSignals();
                },
                error: function(xhr) {
                    $('#confirmOrderBtn').prop('disabled', false).html(
                        '<i class="las la-check"></i> Place Order Now');

                    iziToast.error({
                        message: xhr.responseJSON?.message || 'Error placing order',
                        position: 'topRight',
                        timeout: 5000
                    });
                }
            });
        });
    });
</script>
@endpush

@push('style')
    <style>
        .dashboard-widget {
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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

        .badge--success {
            background: #10b981;
            color: white;
        }

        .badge--danger {
            background: #ef4444;
            color: white;
        }

        .badge--warning {
            background: #f59e0b;
            color: white;
        }

        .badge--secondary {
            background: #6b7280;
            color: white;
        }

        .text--success {
            color: #10b981 !important;
        }

        .text--danger {
            color: #ef4444 !important;
        }

        .text--primary {
            color: #3b82f6 !important;
        }

        .table-sm td,
        .table-sm th {
            padding: 0.5rem;
            font-size: 0.85rem;
        }

        .btn-sm {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }

        .modal-header.bg--success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .alert--info {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e40af;
        }
    </style>
@endpush