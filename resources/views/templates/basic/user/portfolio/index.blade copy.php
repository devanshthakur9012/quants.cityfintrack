@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-briefcase"></i> Portfolio & Positions
                </h5>
                <p class="text-muted small mb-0">View and manage your open positions with purchase date tracking</p>
            </div>
            <div class="card-body">
                <!-- Broker Selection and Date Filter -->
                <div class="row mb-4">
                    <div class="col-lg-4 form-group">
                        <label class="required">Select Broker<sup class="text--danger">*</sup></label>
                        <select class="form--control" id="brokerSelect">
                            <option value="">-- Select Broker --</option>
                            @foreach($brokers as $broker)
                                <option value="{{ $broker->id }}">
                                    {{ $broker->client_name }} ({{ $broker->account_user_name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 form-group">
                        <label>Filter by Purchase Date</label>
                        <select class="form--control" id="purchaseDateFilter" disabled>
                            <option value="">All Dates</option>
                        </select>
                        <small class="text-muted">Select broker first</small>
                    </div>
                    <div class="col-lg-5 form-group">
                        <label class="d-block">&nbsp;</label>
                        <button class="btn btn--base" id="fetchPositionsBtn">
                            <i class="las la-sync"></i> Fetch Positions
                        </button>
                        <button class="btn btn--success" id="refreshBtn" style="display:none;">
                            <i class="las la-redo"></i> Refresh
                        </button>
                        <button class="btn btn--info" id="clearFilterBtn" style="display:none;">
                            <i class="las la-times"></i> Clear Filter
                        </button>
                    </div>
                </div>

                @if($brokers->isEmpty())
                    <div class="alert alert--danger">
                        <i class="las la-exclamation-triangle"></i>
                        <strong>No Active Brokers Found!</strong> Please add and login to a Zerodha broker first.
                        <a href="{{ route('zerodha-broker.index') }}" class="text-decoration-underline">Go to Brokers</a>
                    </div>
                @endif

                <!-- Summary Cards -->
                <div class="row mb-4" id="summarySection" style="display:none;">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="dashboard-widget bg--info">
                            <div class="dashboard-widget__content">
                                <h4 class="dashboard-widget__number text-white" id="totalPositions">0</h4>
                                <span class="dashboard-widget__text text-white">Total Positions</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="dashboard-widget" id="pnlCard">
                            <div class="dashboard-widget__content">
                                <h4 class="dashboard-widget__number" id="totalPnl">₹0.00</h4>
                                <span class="dashboard-widget__text">Total P&L</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="dashboard-widget bg--success">
                            <div class="dashboard-widget__content">
                                <h4 class="dashboard-widget__number text-white" id="profitableCount">0</h4>
                                <span class="dashboard-widget__text text-white">Profitable</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="dashboard-widget bg--danger">
                            <div class="dashboard-widget__content">
                                <h4 class="dashboard-widget__number text-white" id="lossCount">0</h4>
                                <span class="dashboard-widget__text text-white">Loss Making</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Last Updated Info -->
                <div class="alert alert--primary" id="lastUpdated" style="display:none;">
                    <i class="las la-clock"></i> Last updated: <strong id="fetchTime">-</strong>
                    <span id="dateFilterInfo" class="ms-3" style="display:none;">
                        | <i class="las la-filter"></i> Filtered by: <strong id="selectedDate"></strong>
                    </span>
                </div>

                <!-- Positions Table -->
                <div class="table-responsive--md table-responsive" id="positionsTableContainer" style="display:none;">
                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>Symbol</th>
                                <th>Purchase Date</th>
                                <th>Type</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Avg Price</th>
                                <th class="text-end">LTP</th>
                                <th class="text-end">P&L</th>
                                <th class="text-end">P&L %</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="positionsTableBody">
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div class="text-center py-5" id="emptyState">
                    <i class="las la-chart-line text-muted" style="font-size: 80px;"></i>
                    <h5 class="text-muted mt-3">No Positions Data</h5>
                    <p class="text-muted">Select a broker and click "Fetch Positions" to view your open positions</p>
                </div>

                <!-- Loading State -->
                <div class="text-center py-5" id="loadingState" style="display:none;">
                    <div class="spinner-border text--base" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Fetching positions from Zerodha...</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Enhanced Sell Position Modal -->
<div class="modal fade" id="sellModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="sellForm">
                <div class="modal-header bg--danger">
                    <h5 class="modal-title text-white">
                        <i class="las la-times-circle"></i> Square Off Position
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="sellBrokerId">
                    <input type="hidden" id="sellTradingSymbol">
                    <input type="hidden" id="sellExchange">
                    <input type="hidden" id="sellProduct">
                    <input type="hidden" id="sellPositionType">
                    <input type="hidden" id="sellAvgPrice">
                    <input type="hidden" id="sellCurrentLTP">
                    
                    <!-- Position Summary Card -->
                    <div class="alert alert--info mb-4">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <h5 class="mb-3 text-dark"><i class="las la-info-circle"></i> Position Summary</h5>
                                <div class="row">
                                    <div class="col-md-3 col-6 mb-2">
                                        <small class="text-muted d-block">Symbol</small>
                                        <strong id="modalSymbol">-</strong>
                                    </div>
                                    <div class="col-md-3 col-6 mb-2">
                                        <small class="text-muted d-block">Position Type</small>
                                        <span id="modalPositionType" class="badge">-</span>
                                    </div>
                                    <div class="col-md-3 col-6 mb-2">
                                        <small class="text-muted d-block">Quantity</small>
                                        <strong id="modalQuantity">-</strong>
                                    </div>
                                    <div class="col-md-3 col-6 mb-2">
                                        <small class="text-muted d-block">Product</small>
                                        <strong id="modalProduct">-</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 col-6 mb-2">
                                <small class="text-muted d-block">Buy Rate (Avg)</small>
                                <strong class="text--primary">₹<span id="modalBuyRate">0.00</span></strong>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <small class="text-muted d-block">Current LTP</small>
                                <strong class="text--primary">₹<span id="modalLTP">0.00</span></strong>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <small class="text-muted d-block">Current P&L</small>
                                <strong id="modalPnL" class="text--success">₹0.00</strong>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <small class="text-muted d-block">Current P&L %</small>
                                <strong id="modalPnLPercent" class="text--success">0.00%</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Order Details -->
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="required">Quantity to Square Off<sup class="text--danger">*</sup></label>
                            <input type="number" class="form--control" id="sellQuantity" min="1" required>
                            <small class="text-muted">Available: <strong id="availableQty"></strong></small>
                        </div>
                        
                        <div class="col-md-6 form-group">
                            <label class="required">Order Type<sup class="text--danger">*</sup></label>
                            <select class="form--control" id="sellOrderType" required>
                                <option value="MARKET" selected>MARKET (Immediate)</option>
                                <option value="LIMIT">LIMIT (Set Price)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Target Profit Calculator -->
                    <div class="alert" style="background: #f0fdf4; border-color: #86efac; padding: 15px;">
                        <h6 class="mb-3"><i class="las la-calculator"></i> Target Profit Calculator</h6>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label class="text-dark">Target Profit %</label>
                                <input type="number" class="form--control" id="targetProfitPercent" 
                                       min="0" max="1000" step="0.1" placeholder="e.g., 5.00">
                                <small class="text-muted">Enter desired profit % to auto-calculate sell price</small>
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label class="text-dark">Calculated Sell Price</label>
                                <input type="text" class="form--control bg-white" id="calculatedSellPrice" 
                                       readonly placeholder="Auto-calculated">
                                <small class="text-muted">Based on your buy rate + profit %</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manual Limit Price (for LIMIT orders) -->
                    <div class="form-group" id="limitPriceDiv" style="display:none;">
                        <label class="required">Manual Limit Price<sup class="text--danger">*</sup></label>
                        <input type="number" class="form--control" id="sellPrice" step="0.05" placeholder="Enter your price">
                        <small class="text-muted">
                            Current LTP: ₹<strong id="currentLTPHelper">0.00</strong>
                            <span class="mx-2">|</span>
                            Buy Rate: ₹<strong id="buyRateHelper">0.00</strong>
                        </small>
                    </div>

                    <!-- Use Calculated Price Button -->
                    <div class="text-center mb-3" id="useCalculatedPriceBtn" style="display:none;">
                        <button type="button" class="btn btn--success btn-sm" id="applyCalculatedPrice">
                            <i class="las la-check"></i> Use Calculated Price (₹<span id="calcPriceDisplay">0.00</span>)
                        </button>
                    </div>

                    <div class="alert alert--warning">
                        <i class="las la-exclamation-triangle"></i>
                        <strong>Confirmation Required:</strong> This will square off your position. 
                        <span id="finalPnLMessage">Check P&L before confirming.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="las la-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn--danger" id="confirmSellBtn">
                        <i class="las la-check"></i> Confirm Square Off
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
$(document).ready(function() {
    let currentBrokerId = null;
    let currentPositions = [];
    let availableDates = [];

    // Enable date filter when broker is selected
    $('#brokerSelect').on('change', function() {
        const brokerId = $(this).val();
        if (brokerId) {
            $('#purchaseDateFilter').prop('disabled', false);
            currentBrokerId = brokerId;
        } else {
            $('#purchaseDateFilter').prop('disabled', true).val('');
            $('#clearFilterBtn').hide();
        }
    });

    // Fetch positions
    $('#fetchPositionsBtn, #refreshBtn').on('click', function() {
        const brokerId = $('#brokerSelect').val();
        
        if (!brokerId) {
            iziToast.error({
                message: 'Please select a broker first',
                position: 'topRight'
            });
            return;
        }

        currentBrokerId = brokerId;
        const purchaseDate = $('#purchaseDateFilter').val();
        fetchPositions(brokerId, purchaseDate);
    });

    // Apply date filter
    $('#purchaseDateFilter').on('change', function() {
        const purchaseDate = $(this).val();
        if (currentBrokerId) {
            fetchPositions(currentBrokerId, purchaseDate);
            
            if (purchaseDate) {
                $('#clearFilterBtn').show();
            } else {
                $('#clearFilterBtn').hide();
            }
        }
    });

    // Clear date filter
    $('#clearFilterBtn').on('click', function() {
        $('#purchaseDateFilter').val('');
        $('#clearFilterBtn').hide();
        if (currentBrokerId) {
            fetchPositions(currentBrokerId, '');
        }
    });

    function fetchPositions(brokerId, purchaseDate = '') {
        $('#loadingState').show();
        $('#emptyState').hide();
        $('#positionsTableContainer').hide();
        $('#summarySection').hide();
        $('#lastUpdated').hide();

        const requestData = {
            broker_id: brokerId,
            _token: '{{ csrf_token() }}'
        };

        if (purchaseDate) {
            requestData.purchase_date = purchaseDate;
        }

        $.ajax({
            url: '{{ route("portfolio.fetch-positions") }}',
            type: 'POST',
            data: requestData,
            success: function(response) {
                $('#loadingState').hide();
                
                if (response.success && response.data.positions.length > 0) {
                    currentPositions = response.data.positions;
                    
                    // Populate date filter dropdown
                    if (response.data.available_dates && response.data.available_dates.length > 0) {
                        availableDates = response.data.available_dates;
                        populateDateFilter(availableDates);
                    }
                    
                    displayPositions(response.data);
                    $('#refreshBtn').show();
                    $('#fetchTime').text(response.data.fetched_at);
                    $('#lastUpdated').show();
                    
                    // Show filter info if date is selected
                    if (purchaseDate) {
                        $('#selectedDate').text(purchaseDate);
                        $('#dateFilterInfo').show();
                    } else {
                        $('#dateFilterInfo').hide();
                    }
                    
                    iziToast.success({
                        message: 'Positions fetched successfully',
                        position: 'topRight'
                    });
                } else {
                    $('#emptyState').show();
                    iziToast.info({
                        message: purchaseDate ? 'No positions found for selected date' : 'No open positions found',
                        position: 'topRight'
                    });
                }
            },
            error: function(xhr) {
                $('#loadingState').hide();
                $('#emptyState').show();
                
                let message = 'Error fetching positions';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                
                iziToast.error({
                    message: message,
                    position: 'topRight'
                });
            }
        });
    }

    function populateDateFilter(dates) {
        const currentSelected = $('#purchaseDateFilter').val();
        let options = '<option value="">All Dates</option>';
        
        dates.forEach(function(date) {
            const formattedDate = new Date(date).toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
            options += `<option value="${date}">${formattedDate}</option>`;
        });
        
        $('#purchaseDateFilter').html(options);
        
        // Restore previous selection if exists
        if (currentSelected && dates.includes(currentSelected)) {
            $('#purchaseDateFilter').val(currentSelected);
        }
    }

    function displayPositions(data) {
        // Update summary
        $('#totalPositions').text(data.total_positions);
        $('#totalPnl').text('₹' + Number(data.total_pnl).toFixed(2));
        
        // Color code P&L card
        if (data.total_pnl >= 0) {
            $('#pnlCard').removeClass('bg--danger').addClass('bg--success');
            $('#pnlCard').find('.dashboard-widget__number, .dashboard-widget__text').addClass('text-white');
        } else {
            $('#pnlCard').removeClass('bg--success').addClass('bg--danger');
            $('#pnlCard').find('.dashboard-widget__number, .dashboard-widget__text').addClass('text-white');
        }

        let profitableCount = data.positions.filter(p => p.pnl > 0).length;
        let lossCount = data.positions.filter(p => p.pnl < 0).length;
        
        $('#profitableCount').text(profitableCount);
        $('#lossCount').text(lossCount);
        $('#summarySection').show();

        // Build table
        let tableHtml = '';
        data.positions.forEach(function(position) {
            let pnlClass = position.pnl >= 0 ? 'text--success' : 'text--danger';
            let pnlIcon = position.pnl >= 0 ? '▲' : '▼';
            let typeClass = position.position_type === 'LONG' ? 'badge badge--success' : 'badge badge--danger';
            
            // Format purchase date
            let purchaseDate = position.purchase_date ? 
                new Date(position.purchase_date).toLocaleDateString('en-IN', {
                    day: '2-digit',
                    month: 'short',
                    hour: '2-digit',
                    minute: '2-digit'
                }) : 'N/A';
            
            tableHtml += `
                <tr>
                    <td><strong>${position.tradingsymbol}</strong></td>
                    <td><small>${purchaseDate}</small></td>
                    <td><span class="${typeClass}">${position.position_type}</span></td>
                    <td class="text-end"><strong>${Math.abs(position.quantity)}</strong></td>
                    <td class="text-end">₹${Number(position.average_price).toFixed(2)}</td>
                    <td class="text-end">₹${Number(position.last_price).toFixed(2)}</td>
                    <td class="text-end ${pnlClass}">
                        <strong>${pnlIcon} ₹${Math.abs(position.pnl).toFixed(2)}</strong>
                    </td>
                    <td class="text-end ${pnlClass}">
                        <strong>${Number(position.pnl_percentage).toFixed(2)}%</strong>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn--danger sell-btn" 
                                data-position='${JSON.stringify(position).replace(/'/g, "&apos;")}'>
                            <i class="las la-times"></i> Square Off
                        </button>
                    </td>
                </tr>
            `;
        });

        $('#positionsTableBody').html(tableHtml);
        $('#positionsTableContainer').show();
    }

    // Show sell modal with enhanced details
    $(document).on('click', '.sell-btn', function() {
        const positionJson = $(this).attr('data-position').replace(/&apos;/g, "'");
        const position = JSON.parse(positionJson);
        
        // Store values
        $('#sellBrokerId').val(currentBrokerId);
        $('#sellTradingSymbol').val(position.tradingsymbol);
        $('#sellExchange').val(position.exchange);
        $('#sellProduct').val(position.product);
        $('#sellPositionType').val(position.position_type);
        $('#sellAvgPrice').val(position.average_price);
        $('#sellCurrentLTP').val(position.last_price);
        
        // Display summary
        $('#modalSymbol').text(position.tradingsymbol);
        $('#modalPositionType').text(position.position_type)
            .removeClass('badge--success badge--danger')
            .addClass(position.position_type === 'LONG' ? 'badge--success' : 'badge--danger');
        $('#modalQuantity').text(Math.abs(position.quantity));
        $('#modalProduct').text(position.product);
        $('#modalBuyRate').text(Number(position.average_price).toFixed(2));
        $('#modalLTP').text(Number(position.last_price).toFixed(2));
        
        // P&L display
        let pnlClass = position.pnl >= 0 ? 'text--success' : 'text--danger';
        let pnlIcon = position.pnl >= 0 ? '▲' : '▼';
        $('#modalPnL').text(pnlIcon + ' ₹' + Math.abs(position.pnl).toFixed(2))
            .removeClass('text--success text--danger').addClass(pnlClass);
        $('#modalPnLPercent').text(Number(position.pnl_percentage).toFixed(2) + '%')
            .removeClass('text--success text--danger').addClass(pnlClass);
        
        // Quantity
        $('#sellQuantity').val(Math.abs(position.quantity));
        $('#sellQuantity').attr('max', Math.abs(position.quantity));
        $('#availableQty').text(Math.abs(position.quantity));
        
        // Helpers
        $('#currentLTPHelper').text(Number(position.last_price).toFixed(2));
        $('#buyRateHelper').text(Number(position.average_price).toFixed(2));
        $('#sellPrice').attr('placeholder', Number(position.last_price).toFixed(2));
        
        $('#sellModal').modal('show');
    });

    // Calculate sell price based on profit %
    $('#targetProfitPercent').on('input', function() {
        const profitPercent = parseFloat($(this).val()) || 0;
        const buyRate = parseFloat($('#sellAvgPrice').val()) || 0;
        
        if (profitPercent > 0 && buyRate > 0) {
            const targetSellPrice = buyRate * (1 + (profitPercent / 100));
            $('#calculatedSellPrice').val('₹' + targetSellPrice.toFixed(2));
            $('#calcPriceDisplay').text(targetSellPrice.toFixed(2));
            
            if ($('#sellOrderType').val() === 'LIMIT') {
                $('#useCalculatedPriceBtn').show();
            }
            
            const quantity = Math.abs(parseFloat($('#sellQuantity').val()) || 0);
            const potentialPnL = (targetSellPrice - buyRate) * quantity;
            $('#finalPnLMessage').html(
                `Potential P&L at this price: <strong class="${potentialPnL >= 0 ? 'text--success' : 'text--danger'}">₹${potentialPnL.toFixed(2)}</strong>`
            );
        } else {
            $('#calculatedSellPrice').val('');
            $('#useCalculatedPriceBtn').hide();
            $('#finalPnLMessage').text('Check P&L before confirming.');
        }
    });

    // Apply calculated price to limit price field
    $('#applyCalculatedPrice').on('click', function() {
        const calculatedPrice = $('#calculatedSellPrice').val().replace('₹', '');
        $('#sellPrice').val(calculatedPrice);
        
        iziToast.success({
            message: 'Calculated price applied!',
            position: 'topRight',
            timeout: 2000
        });
    });

    // Toggle limit price field
    $('#sellOrderType').on('change', function() {
        if ($(this).val() === 'LIMIT') {
            $('#limitPriceDiv').show();
            $('#sellPrice').prop('required', true);
            
            if ($('#targetProfitPercent').val()) {
                $('#useCalculatedPriceBtn').show();
            }
        } else {
            $('#limitPriceDiv').hide();
            $('#sellPrice').prop('required', false);
            $('#useCalculatedPriceBtn').hide();
        }
    });

    // Confirm sell
    $('#confirmSellBtn').on('click', function() {
        const quantity = $('#sellQuantity').val();
        const orderType = $('#sellOrderType').val();
        const price = $('#sellPrice').val();

        if (!quantity || quantity <= 0) {
            iziToast.error({
                message: 'Please enter valid quantity',
                position: 'topRight'
            });
            return;
        }

        if (orderType === 'LIMIT' && (!price || price <= 0)) {
            iziToast.error({
                message: 'Please enter valid limit price',
                position: 'topRight'
            });
            return;
        }

        const data = {
            broker_id: $('#sellBrokerId').val(),
            tradingsymbol: $('#sellTradingSymbol').val(),
            exchange: $('#sellExchange').val(),
            product: $('#sellProduct').val(),
            position_type: $('#sellPositionType').val(),
            quantity: quantity,
            order_type: orderType,
            price: price,
            _token: '{{ csrf_token() }}'
        };

        $(this).prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Processing...');

        $.ajax({
            url: '{{ route("portfolio.sell-position") }}',
            type: 'POST',
            data: data,
            success: function(response) {
                $('#sellModal').modal('hide');
                $('#confirmSellBtn').prop('disabled', false).html('<i class="las la-check"></i> Confirm Square Off');
                
                iziToast.success({
                    message: response.message,
                    position: 'topRight',
                    timeout: 5000
                });
                
                setTimeout(function() {
                    if (currentBrokerId) {
                        const purchaseDate = $('#purchaseDateFilter').val();
                        fetchPositions(currentBrokerId, purchaseDate);
                    }
                }, 2000);
            },
            error: function(xhr) {
                $('#confirmSellBtn').prop('disabled', false).html('<i class="las la-check"></i> Confirm Square Off');
                
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

    // Reset form on modal close
    $('#sellModal').on('hidden.bs.modal', function() {
        $('#sellForm')[0].reset();
        $('#limitPriceDiv').hide();
        $('#sellPrice').prop('required', false);
        $('#calculatedSellPrice').val('');
        $('#useCalculatedPriceBtn').hide();
        $('#finalPnLMessage').text('Check P&L before confirming.');
    });
});
</script>
@endpush

@push('style')
<style>
.dashboard-widget {
    padding: 20px;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.dashboard-widget.bg--info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.dashboard-widget.bg--success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.dashboard-widget.bg--danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.dashboard-widget__number {
    font-size: 1.5rem;
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

.text--success {
    color: #10b981 !important;
    font-weight: 600;
}

.text--danger {
    color: #ef4444 !important;
    font-weight: 600;
}

.text--primary {
    color: #3b82f6 !important;
    font-weight: 600;
}

.alert--primary {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
}

.alert--info {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
}

.alert--warning {
    background: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
}

.alert--danger {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #991b1b;
}

.spinner-border.text--base {
    color: var(--base-color) !important;
}

.modal-header.bg--danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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

.btn--success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: white;
}

.btn--success:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: white;
}

.btn--info {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border: none;
    color: white;
}

.btn--info:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: white;
}

.modal-lg {
    max-width: 900px;
}

#calculatedSellPrice {
    font-weight: bold;
    color: #059669;
    font-size: 1.1rem;
}

.alert h6 {
    font-size: 1rem;
    font-weight: 600;
    color: #059669;
}
</style>
@endpush