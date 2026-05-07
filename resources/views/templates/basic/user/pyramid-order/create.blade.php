@extends($activeTemplate . 'layouts.master')
@section('content')
    <section class="pt-100 pb-100">
        <div class="container content-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Create Pyramid Order</h4>
                <a href="{{ route('user.pyramid-orders.index') }}" class="btn btn-secondary">
                    <i class="las la-arrow-left"></i> Back to List
                </a>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="custom--card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Order Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('user.pyramid-orders.store') }}" method="POST" id="pyramidOrderForm">
                                @csrf

                                <!-- Contract Selection -->
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label for="broker_api_id" class="required">Broker <sup
                                                class="text--danger">*</sup></label>
                                        <select name="broker_api_id" id="broker_api_id" class="form--control" required>
                                            <option value="">Select Broker</option>
                                            @foreach ($brokers as $broker)
                                                <option value="{{ $broker->id }}"
                                                    data-broker-type="{{ $broker->client_type }}"
                                                    {{ old('broker_api_id') == $broker->id ? 'selected' : '' }}>
                                                    {{ $broker->client_name }} ({{ $broker->client_type }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="symbol" class="required">Symbol <sup
                                                class="text--danger">*</sup></label>
                                        <select name="symbol" id="symbol" class="form--control" required disabled>
                                            <option value="">Select broker first</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="expiry_date" class="required">Expiry Date <sup
                                                class="text--danger">*</sup></label>
                                        <select name="expiry_date" id="expiry_date" class="form--control" required disabled>
                                            <option value="">Select symbol first</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="strike_price" class="required">Strike Price <sup
                                                class="text--danger">*</sup></label>
                                        <select name="strike_price" id="strike_price" class="form--control" required
                                            disabled>
                                            <option value="">Select expiry first</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="option_type" class="required">Option Type <sup
                                                class="text--danger">*</sup></label>
                                        <select name="option_type" id="option_type" class="form--control" required>
                                            <option value="">Select Type</option>
                                            <option value="CE" {{ old('option_type') == 'CE' ? 'selected' : '' }}>CE
                                                (Call)</option>
                                            <option value="PE" {{ old('option_type') == 'PE' ? 'selected' : '' }}>PE
                                                (Put)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="transaction_type" class="required">Transaction Type <sup
                                                class="text--danger">*</sup></label>
                                        <select name="transaction_type" id="transaction_type" class="form--control"
                                            required>
                                            <option value="">Select Type</option>
                                            <option value="BUY"
                                                {{ old('transaction_type') == 'BUY' ? 'selected' : '' }}>BUY</option>
                                            <option value="SELL"
                                                {{ old('transaction_type') == 'SELL' ? 'selected' : '' }}>SELL</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Pricing Parameters -->
                                <hr class="my-4">
                                <h6 class="mb-3">Pricing Parameters</h6>

                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label for="manual_ltp" class="required">Manual LTP <sup
                                                class="text--danger">*</sup></label>
                                        <input type="number" name="manual_ltp" id="manual_ltp" class="form--control"
                                            step="0.01" min="0.01" value="{{ old('manual_ltp') }}" required>
                                        <small class="text-muted">Reference price for calculations</small>
                                    </div>

                                    <div class="col-md-4 form-group">
                                        <label for="base_discount_pct" class="required">Base Discount % <sup
                                                class="text--danger">*</sup></label>
                                        <input type="number" name="base_discount_pct" id="base_discount_pct"
                                            class="form--control" step="0.01" min="0" max="50"
                                            value="{{ old('base_discount_pct', 10) }}" required>
                                        <small class="text-muted">Initial discount for first order</small>
                                    </div>

                                    <div class="col-md-4 form-group">
                                        <label for="discount_increment_pct" class="required">Discount Increment % <sup
                                                class="text--danger">*</sup></label>
                                        <input type="number" name="discount_increment_pct" id="discount_increment_pct"
                                            class="form--control" step="0.01" min="0" max="100"
                                            value="{{ old('discount_increment_pct', 25) }}" required>
                                        <small class="text-muted">Increment applied per pyramid</small>
                                    </div>
                                </div>

                                <!-- Quantity Parameters -->
                                <hr class="my-4">
                                <h6 class="mb-3">Quantity Parameters</h6>

                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label for="lots_per_order" class="required">Lots Per Order <sup
                                                class="text--danger">*</sup></label>
                                        <input type="number" name="lots_per_order" id="lots_per_order"
                                            class="form--control" min="1" value="{{ old('lots_per_order', 1) }}"
                                            required>
                                    </div>

                                    <div class="col-md-4 form-group">
                                        <label for="num_pyramids" class="required">Number of Pyramids <sup
                                                class="text--danger">*</sup></label>
                                        <input type="number" name="num_pyramids" id="num_pyramids"
                                            class="form--control" min="1" max="10"
                                            value="{{ old('num_pyramids', 3) }}" required>
                                    </div>

                                    <div class="col-md-4 form-group">
                                        <label for="lot_size" class="required">Lot Size <sup
                                                class="text--danger">*</sup></label>
                                        <input type="number" name="lot_size" id="lot_size" class="form--control"
                                            min="1" value="{{ old('lot_size') }}" required readonly>
                                        <small class="text-muted">Auto-fetched from symbol</small>
                                    </div>
                                </div>

                                <div class="form-group mt-4">
                                    <button type="button" class="btn btn--info" id="previewBtn">
                                        <i class="las la-eye"></i> Preview Calculations
                                    </button>
                                    <button type="submit" class="btn btn--base">
                                        <i class="las la-check"></i> Place Orders
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Preview Panel -->
                <div class="col-lg-4">
                    <div class="custom--card" id="previewPanel" style="display: none;">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Order Preview</h5>
                        </div>
                        <div class="card-body">
                            <div id="previewContent">
                                <!-- Dynamic content -->
                            </div>
                        </div>
                    </div>

                    <div class="custom--card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Formula Reference</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert--info mb-3">
                                <strong>Effective Discount (i):</strong><br>
                                <code>base_disc × (1 + ((i-1) × incr/100))</code>
                            </div>
                            <div class="alert alert--success mb-3">
                                <strong>BUY Price:</strong><br>
                                <code>ltp × (1 - eff_disc/100)</code>
                            </div>
                            <div class="alert alert--danger mb-0">
                                <strong>SELL Price:</strong><br>
                                <code>ltp × (1 + eff_disc/100)</code>
                            </div>
                        </div>
                    </div>

                    <!-- Broker Type Indicator -->
                    <div class="custom--card" id="brokerTypeCard" style="display: none;">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Selected Broker</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <h4 id="brokerTypeBadge" class="badge badge--primary mb-0"></h4>
                                <p class="text-muted mt-2 mb-0">
                                    <small id="brokerTypeInfo"></small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('script')
        <script>
            $(document).ready(function() {
                let currentBrokerType = null;

                // Store Angel and Zerodha symbols
                const angelSymbols = @json($angelSymbols);
                const zerodhaSymbols = @json($zerodhaSymbols);

                // Handle broker selection
                $('#broker_api_id').on('change', function() {
                    const brokerId = $(this).val();
                    
                    if (!brokerId) {
                        currentBrokerType = null;
                        $('#symbol').html('<option value="">Select broker first</option>').prop('disabled', true);
                        $('#expiry_date').prop('disabled', true).html('<option value="">Select symbol first</option>');
                        $('#strike_price').prop('disabled', true).html('<option value="">Select expiry first</option>');
                        $('#lot_size').val('');
                        $('#brokerTypeCard').hide();
                        return;
                    }

                    // Get broker type from data attribute
                    currentBrokerType = $(this).find(':selected').data('broker-type');
                    const brokerName = $(this).find(':selected').text();
                    
                    // Show broker type indicator
                    $('#brokerTypeBadge').text(currentBrokerType).removeClass('badge--primary badge--success')
                        .addClass(currentBrokerType === 'Angel' ? 'badge--primary' : 'badge--success');
                    
                    $('#brokerTypeInfo').text('Orders will be placed via ' + currentBrokerType + ' API');
                    $('#brokerTypeCard').fadeIn();
                    
                    // Update symbol dropdown based on broker type
                    let symbolOptions = '<option value="">Select Symbol</option>';
                    const symbolList = currentBrokerType === 'Angel' ? angelSymbols : zerodhaSymbols;
                    
                    symbolList.forEach(symbol => {
                        const isSelected = '{{ old("symbol") }}' === symbol ? 'selected' : '';
                        symbolOptions += `<option value="${symbol}" ${isSelected}>${symbol}</option>`;
                    });
                    
                    $('#symbol').html(symbolOptions).prop('disabled', false);
                    
                    // Reset dependent fields
                    $('#expiry_date').prop('disabled', true).html('<option value="">Select symbol first</option>');
                    $('#strike_price').prop('disabled', true).html('<option value="">Select expiry first</option>');
                    $('#lot_size').val('');
                });

                // Fetch expiries when symbol changes
                $('#symbol').on('change', function() {
                    const symbol = $(this).val();

                    if (!symbol || !currentBrokerType) {
                        $('#expiry_date').prop('disabled', true).html('<option value="">Select symbol first</option>');
                        $('#strike_price').prop('disabled', true).html('<option value="">Select expiry first</option>');
                        $('#lot_size').val('');
                        return;
                    }

                    // Show loading state
                    $('#expiry_date').html('<option value="">Loading...</option>').prop('disabled', true);
                    $('#lot_size').val('');

                    // Fetch lot size
                    $.ajax({
                        url: '{{ route('user.pyramid-orders.get-lot-size') }}',
                        method: 'GET',
                        data: {
                            symbol: symbol,
                            broker_type: currentBrokerType
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#lot_size').val(response.lot_size);
                            } else {
                                alert('Error fetching lot size: ' + response.message);
                            }
                        },
                        error: function(xhr) {
                            console.error('Lot size fetch error:', xhr);
                            alert('Failed to fetch lot size');
                        }
                    });

                    // Fetch expiries
                    $.ajax({
                        url: '{{ route('user.pyramid-orders.get-expiries') }}',
                        method: 'GET',
                        data: {
                            symbol: symbol,
                            broker_type: currentBrokerType
                        },
                        success: function(response) {
                            if (response.success) {
                                let options = '<option value="">Select Expiry Date</option>';
                                response.data.forEach(item => {
                                    const isSelected = '{{ old("expiry_date") }}' === item.value ? 'selected' : '';
                                    options += `<option value="${item.value}" ${isSelected}>${item.label}</option>`;
                                });
                                $('#expiry_date').html(options).prop('disabled', false);
                            } else {
                                $('#expiry_date').html('<option value="">No expiries found</option>');
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function(xhr) {
                            console.error('Expiry fetch error:', xhr);
                            $('#expiry_date').html('<option value="">Error loading expiries</option>');
                            alert('Failed to fetch expiries');
                        }
                    });
                });

                // Fetch strikes when expiry changes
                $('#expiry_date').on('change', function() {
                    const symbol = $('#symbol').val();
                    const expiry = $(this).val();

                    if (!symbol || !expiry || !currentBrokerType) {
                        $('#strike_price').prop('disabled', true).html('<option value="">Select expiry first</option>');
                        return;
                    }

                    // Show loading state
                    $('#strike_price').html('<option value="">Loading...</option>').prop('disabled', true);

                    $.ajax({
                        url: '{{ route('user.pyramid-orders.get-strikes') }}',
                        method: 'GET',
                        data: {
                            symbol: symbol,
                            expiry: expiry,
                            broker_type: currentBrokerType
                        },
                        success: function(response) {
                            if (response.success) {
                                let options = '<option value="">Select Strike Price</option>';
                                response.data.forEach(item => {
                                    const isSelected = '{{ old("strike_price") }}' == item.value ? 'selected' : '';
                                    options += `<option value="${item.value}" ${isSelected}>${item.label}</option>`;
                                });
                                $('#strike_price').html(options).prop('disabled', false);
                            } else {
                                $('#strike_price').html('<option value="">No strikes found</option>');
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function(xhr) {
                            console.error('Strike fetch error:', xhr);
                            $('#strike_price').html('<option value="">Error loading strikes</option>');
                            alert('Failed to fetch strikes');
                        }
                    });
                });

                // Preview calculations
                $('#previewBtn').on('click', function() {
                    const data = {
                        manual_ltp: $('#manual_ltp').val(),
                        base_discount_pct: $('#base_discount_pct').val(),
                        discount_increment_pct: $('#discount_increment_pct').val(),
                        lots_per_order: $('#lots_per_order').val(),
                        num_pyramids: $('#num_pyramids').val(),
                        lot_size: $('#lot_size').val(),
                        transaction_type: $('#transaction_type').val(),
                        _token: '{{ csrf_token() }}'
                    };

                    // Validate required fields
                    if (!data.manual_ltp || !data.base_discount_pct || !data.discount_increment_pct ||
                        !data.lots_per_order || !data.num_pyramids || !data.lot_size || !data.transaction_type
                    ) {
                        alert('Please fill all pricing and quantity fields');
                        return;
                    }

                    // Show loading state
                    $('#previewContent').html('<div class="text-center"><i class="las la-spinner la-spin"></i> Calculating...</div>');
                    $('#previewPanel').fadeIn();

                    $.ajax({
                        url: '{{ route('user.pyramid-orders.preview') }}',
                        method: 'POST',
                        data: data,
                        success: function(response) {
                            if (response.success) {
                                let html =
                                    '<div class="table-responsive"><table class="table table-sm">';
                                html +=
                                    '<thead><tr><th>#</th><th>Disc</th><th>Price</th><th>Qty</th><th>Value</th></tr></thead><tbody>';

                                let totalValue = 0;
                                response.data.forEach(item => {
                                    const value = parseFloat(item.value.replace(/,/g, ''));
                                    totalValue += value;
                                    html += `<tr>
                                        <td>${item.pyramid}</td>
                                        <td>${item.discount}</td>
                                        <td>₹${item.price}</td>
                                        <td>${item.quantity}</td>
                                        <td>₹${item.value}</td>
                                    </tr>`;
                                });

                                html += `<tr class="table-active">
                                    <th colspan="4" class="text-end">Total:</th>
                                    <th>₹${totalValue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</th>
                                </tr>`;

                                html += '</tbody></table></div>';
                                
                                html += `<div class="alert alert--info mt-3 mb-0">
                                    <small><strong>Total Orders:</strong> ${response.data.length}<br>
                                    <strong>Total Investment:</strong> ₹${totalValue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</small>
                                </div>`;
                                
                                $('#previewContent').html(html);
                            } else {
                                $('#previewContent').html('<div class="alert alert--danger mb-0">Error: ' + response.message + '</div>');
                            }
                        },
                        error: function(xhr) {
                            console.error('Preview error:', xhr);
                            $('#previewContent').html('<div class="alert alert--danger mb-0">Failed to generate preview</div>');
                        }
                    });
                });

                // Trigger preview on input change if panel is visible
                $('#manual_ltp, #base_discount_pct, #discount_increment_pct, #lots_per_order, #num_pyramids, #transaction_type')
                    .on('change', function() {
                        if ($('#previewPanel').is(':visible')) {
                            $('#previewBtn').click();
                        }
                    });

                // Form validation before submit
                $('#pyramidOrderForm').on('submit', function(e) {
                    if (!currentBrokerType) {
                        e.preventDefault();
                        alert('Please select a broker');
                        return false;
                    }

                    const symbol = $('#symbol').val();
                    const expiry = $('#expiry_date').val();
                    const strike = $('#strike_price').val();

                    if (!symbol || !expiry || !strike) {
                        e.preventDefault();
                        alert('Please complete all contract selection fields');
                        return false;
                    }

                    // Show confirmation
                    const confirmMsg = `You are about to place ${$('#num_pyramids').val()} orders via ${currentBrokerType}. Continue?`;
                    if (!confirm(confirmMsg)) {
                        e.preventDefault();
                        return false;
                    }
                });

                // Auto-trigger broker change if old value exists
                @if(old('broker_api_id'))
                    $('#broker_api_id').trigger('change');
                    @if(old('symbol'))
                        setTimeout(function() {
                            $('#symbol').val('{{ old("symbol") }}').trigger('change');
                        }, 500);
                    @endif
                @endif
            });
        </script>
    @endpush

    @push('style')
        <style>
            .alert--info {
                background-color: #dbeafe;
                border-color: #93c5fd;
                color: #1e40af;
            }

            .alert--success {
                background-color: #d1fae5;
                border-color: #6ee7b7;
                color: #065f46;
            }

            .alert--danger {
                background-color: #fee2e2;
                border-color: #fca5a5;
                color: #991b1b;
            }

            code {
                background-color: rgba(0, 0, 0, 0.05);
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 0.9em;
            }

            #previewContent td {
                color: #fff !important;
            }

            #previewContent th {
                color: #fff !important;
            }

            .badge--primary {
                background-color: #3b82f6;
                color: white;
                font-size: 1.1rem;
                padding: 0.5rem 1rem;
            }

            .badge--success {
                background-color: #10b981;
                color: white;
                font-size: 1.1rem;
                padding: 0.5rem 1rem;
            }

            #brokerTypeCard {
                margin-top: 1rem;
            }

            .la-spinner {
                font-size: 2rem;
            }

            .table-active th {
                font-weight: bold;
            }

            /* Loading animations */
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            .la-spin {
                animation: spin 1s linear infinite;
            }

            /* Form field focus */
            .form--control:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
            }

            /* Disabled state */
            .form--control:disabled {
                background-color: #f3f4f6;
                cursor: not-allowed;
            }

            /* Required field indicator */
            .required {
                position: relative;
            }

            sup.text--danger {
                color: #ef4444;
                font-size: 0.875rem;
            }

            /* Card hover effect */
            .custom--card {
                transition: box-shadow 0.3s ease;
            }

            .custom--card:hover {
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            }

            /* Button loading state */
            .btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
        </style>
    @endpush
@endsection