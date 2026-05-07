@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <!-- Add New SELL Config Card -->
        <div class="custom--card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-sun"></i> Add New SELL Order Configuration
                </h5>
                <p class="text-muted small mb-0">Configure profit targets for normal sell orders during market hours (9:15 AM - 3:30 PM)</p>
            </div>
            <div class="card-body">
                <form id="addSellConfigForm">
                    @csrf
                    <div class="row">
                        <!-- Broker -->
                        <div class="col-md-2 form-group">
                            <label>Broker Account<sup class="text--danger">*</sup></label>
                            <select class="form--control" name="broker_api_id" required>
                                <option value="">Select Broker</option>
                                @foreach($brokers as $broker)
                                    <option value="{{ $broker->id }}">
                                        {{ $broker->client_name }} ({{ $broker->account_user_name }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Symbol Type -->
                        <div class="col-md-1 form-group">
                            <label>Symbol Type<sup class="text--danger">*</sup></label>
                            <select class="form--control" name="symbol_type" required>
                                <option value="">Select</option>
                                <option value="CE">CE (Call)</option>
                                <option value="PE">PE (Put)</option>
                                <option value="BOTH">BOTH</option>
                            </select>
                        </div>

                        <!-- Price Type -->
                        <div class="col-md-1 form-group">
                            <label>Price Type<sup class="text--danger">*</sup></label>
                            <select class="form--control" name="price_type" required>
                                <option value="AVG">AVG Price</option>
                                <option value="LTP">Live LTP</option>
                            </select>
                            <small class="text-muted">Base for target</small>
                        </div>

                        <!-- Sell Qty % -->
                        <div class="col-md-1 form-group">
                            <label>Sell Qty %<sup class="text--danger">*</sup></label>
                            <input type="number" class="form--control" name="quantity_percent"
                                   value="100" min="1" max="100" step="1" required>
                            <small class="text-muted">1–100%</small>
                        </div>

                        <!-- Position Filter -->
                        <div class="col-md-2 form-group">
                            <label>Position Filter<sup class="text--danger">*</sup></label>
                            <select class="form--control" name="position_filter" required>
                                <option value="PROFIT" selected>Profit Positions</option>
                                <option value="LOSS">Loss Positions</option>
                                <option value="BOTH">Both (All)</option>
                            </select>
                            <small class="text-muted">LTP vs AVG comparison</small>
                        </div>

                        <!-- Old Position % -->
                        <div class="col-md-2 form-group">
                            <label>Old Position %</label>
                            <input type="number" class="form--control" name="old_position_profit_percent"
                                   value="20" min="-100" max="100" step="0.1">
                            <div class="custom-control custom-checkbox mt-1">
                                <input type="checkbox" class="custom-control-input" id="skip_old_sell"
                                       name="skip_old_positions" value="1">
                                <label class="custom-control-label" for="skip_old_sell">Skip Old</label>
                            </div>
                        </div>

                        <!-- Fresh Position % -->
                        <div class="col-md-2 form-group">
                            <label>Fresh Position %</label>
                            <input type="number" class="form--control" name="fresh_position_profit_percent"
                                   value="10" min="-100" max="100" step="0.1">
                            <div class="custom-control custom-checkbox mt-1">
                                <input type="checkbox" class="custom-control-input" id="skip_fresh_sell"
                                       name="skip_fresh_positions" value="1">
                                <label class="custom-control-label" for="skip_fresh_sell">Skip Fresh</label>
                            </div>
                        </div>

                        <!-- Add Button -->
                        <div class="col-md-1 form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn--base w-100">
                                <i class="las la-plus"></i> Add
                            </button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert--info">
                                <strong>Fresh Positions:</strong> Today + Previous trading day (T-1)&nbsp;&nbsp;|&nbsp;&nbsp;
                                <strong>Old Positions:</strong> Before T-1<br>
                                <strong>Sell Qty %:</strong> 50% means if you hold 100 qty only 50 will be sold&nbsp;&nbsp;|&nbsp;&nbsp;
                                <strong>Position Filter:</strong> Profit = LTP &gt; AVG | Loss = LTP &lt; AVG | Both = all positions
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Manual Execution -->
        <div class="custom--card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-2">
                            <i class="las la-rocket"></i> Manual SELL Order Execution
                        </h6>
                        <p class="text-muted small mb-0">
                            Execute SELL orders manually during market hours
                        </p>
                    </div>
                    <div class="col-md-6 text-right">
                        <button type="button" class="btn btn--success btn-lg" id="executeSellBtn">
                            <i class="las la-play-circle"></i> Execute SELL Orders Now
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Configs Card -->
        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-cog"></i> SELL Order Configurations
                </h5>
            </div>
            <div class="card-body">
                <div id="configsContainer">
                    @if($configs->isEmpty())
                        <div class="alert alert--warning">
                            <i class="las la-exclamation-triangle"></i>
                            No SELL order configurations found. Add your first configuration above.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table custom--table">
                                <thead>
                                    <tr>
                                        <th>Broker</th>
                                        <th>Type</th>
                                        <th>Price Type</th>
                                        <th>Sell Qty %</th>
                                        <th>Position Filter</th>
                                        <th>Old % / Skip</th>
                                        <th>Fresh % / Skip</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($configs as $config)
                                    <tr id="config-row-{{ $config->id }}">
                                        <!-- Broker -->
                                        <td>
                                            <strong>{{ $config->brokerApi->client_name }}</strong><br>
                                            <small class="text-muted">{{ $config->brokerApi->account_user_name }}</small>
                                        </td>

                                        <!-- Symbol Type -->
                                        <td>
                                            <span class="badge badge--{{ $config->symbol_type == 'CE' ? 'success' : ($config->symbol_type == 'PE' ? 'danger' : 'info') }}">
                                                {{ $config->symbol_type }}
                                            </span>
                                        </td>

                                        <!-- Price Type -->
                                        <td>
                                            <span class="price-type-display">
                                                @if($config->price_type === 'LTP')
                                                    <span class="badge badge--info">Live LTP</span>
                                                @else
                                                    <span class="badge badge--secondary" style="background:#6b7280;color:white;">AVG Price</span>
                                                @endif
                                            </span>
                                            <div class="d-none edit-fields">
                                                <select class="form--control form--control-sm price-type-select">
                                                    <option value="AVG" {{ $config->price_type === 'AVG' ? 'selected' : '' }}>AVG Price</option>
                                                    <option value="LTP" {{ $config->price_type === 'LTP' ? 'selected' : '' }}>Live LTP</option>
                                                </select>
                                            </div>
                                        </td>

                                        <!-- Sell Qty % -->
                                        <td>
                                            <span class="qty-percent-display">
                                                <span class="badge badge--primary">{{ $config->quantity_percent ?? 100 }}%</span>
                                            </span>
                                            <div class="d-none edit-fields">
                                                <input type="number" class="form--control form--control-sm qty-percent-input"
                                                       value="{{ $config->quantity_percent ?? 100 }}" min="1" max="100" step="1">
                                            </div>
                                        </td>

                                        <!-- Position Filter -->
                                        <td>
                                            <span class="position-filter-display">
                                                @php $pf = $config->position_filter ?? 'BOTH'; @endphp
                                                @if($pf === 'PROFIT')
                                                    <span class="badge badge--success">Profit Only</span>
                                                @elseif($pf === 'LOSS')
                                                    <span class="badge badge--danger">Loss Only</span>
                                                @else
                                                    <span class="badge badge--info">Both</span>
                                                @endif
                                            </span>
                                            <div class="d-none edit-fields">
                                                <select class="form--control form--control-sm position-filter-select">
                                                    <option value="PROFIT" {{ ($config->position_filter ?? 'PROFIT') === 'PROFIT' ? 'selected' : '' }}>Profit Only</option>
                                                    <option value="LOSS"   {{ ($config->position_filter ?? '') === 'LOSS'   ? 'selected' : '' }}>Loss Only</option>
                                                    <option value="BOTH"   {{ ($config->position_filter ?? '') === 'BOTH'   ? 'selected' : '' }}>Both (All)</option>
                                                </select>
                                            </div>
                                        </td>

                                        <!-- Old % / Skip -->
                                        <td>
                                            <span class="old-profit-display">
                                                {{ $config->old_position_profit_percent }}%
                                                @if($config->old_position_profit_percent < 0)
                                                    <span class="badge badge--danger badge-sm">SL</span>
                                                @endif
                                                @if($config->skip_old_positions)
                                                    <span class="badge badge--warning badge-sm">SKIP</span>
                                                @endif
                                            </span>
                                            <div class="d-none edit-fields">
                                                <input type="number" class="form--control form--control-sm old-profit-input"
                                                       value="{{ $config->old_position_profit_percent }}" min="-100" max="100" step="0.1">
                                                <div class="custom-control custom-checkbox mt-1">
                                                    <input type="checkbox" class="custom-control-input skip-old-checkbox"
                                                           id="skip_old_sell_{{ $config->id }}" {{ $config->skip_old_positions ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="skip_old_sell_{{ $config->id }}">Skip</label>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Fresh % / Skip -->
                                        <td>
                                            <span class="fresh-profit-display">
                                                {{ $config->fresh_position_profit_percent }}%
                                                @if($config->skip_fresh_positions)
                                                    <span class="badge badge--warning badge-sm">SKIP</span>
                                                @endif
                                            </span>
                                            <div class="d-none edit-fields">
                                                <input type="number" class="form--control form--control-sm fresh-profit-input"
                                                       value="{{ $config->fresh_position_profit_percent }}" min="-100" max="100" step="0.1">
                                                <div class="custom-control custom-checkbox mt-1">
                                                    <input type="checkbox" class="custom-control-input skip-fresh-checkbox"
                                                           id="skip_fresh_sell_{{ $config->id }}" {{ $config->skip_fresh_positions ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="skip_fresh_sell_{{ $config->id }}">Skip</label>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td>
                                            @if($config->is_active)
                                                <span class="badge badge--success">Active</span>
                                            @else
                                                <span class="badge badge--warning">Inactive</span>
                                            @endif
                                        </td>

                                        <!-- Actions -->
                                        <td>
                                            <div class="button-group justify-content-center">
                                                <button class="btn btn--sm btn--primary edit-btn" data-id="{{ $config->id }}" title="Edit">
                                                    <i class="las la-edit"></i>
                                                </button>
                                                <button class="btn btn--sm btn--success save-btn d-none" data-id="{{ $config->id }}" title="Save">
                                                    <i class="las la-save"></i>
                                                </button>
                                                <button class="btn btn--sm btn--secondary cancel-btn d-none" data-id="{{ $config->id }}" title="Cancel">
                                                    <i class="las la-times"></i>
                                                </button>
                                                <button class="btn btn--sm btn--{{ $config->is_active ? 'warning' : 'info' }} toggle-btn"
                                                        data-id="{{ $config->id }}"
                                                        title="{{ $config->is_active ? 'Deactivate' : 'Activate' }}">
                                                    <i class="las la-{{ $config->is_active ? 'pause' : 'play' }}"></i>
                                                </button>
                                                <button class="btn btn--sm btn--danger delete-btn" data-id="{{ $config->id }}" title="Delete">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Info Alert -->
                <div class="alert alert--info mt-4">
                    <h6 class="mb-2"><i class="las la-info-circle"></i> How SELL Order Configuration Works</h6>
                    <ul class="mb-0">
                        <li><strong>Broker Level:</strong> Configure separate settings for each broker account</li>
                        <li><strong>CE / PE / BOTH:</strong> Filter which option types this config applies to</li>
                        <li><strong>Sell Qty %:</strong> Sell only a portion of your holding (e.g. 50% of 100 qty = sell 50, always rounded to lot size)</li>
                        <li><strong>Position Filter:</strong> <em>Profit</em> = only sell if LTP &gt; AVG | <em>Loss</em> = only sell if LTP &lt; AVG | <em>Both</em> = sell regardless</li>
                        <li>SELL orders are placed <strong>during market hours (9:15 AM - 3:30 PM)</strong></li>
                        <li>Only <strong>ACTIVE</strong> configurations will be used when placing orders</li>
                        <li>Orders respect freezing quantities and are split automatically if needed</li>
                        <li>Prices are rounded to correct tick sizes from Zerodha instruments</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('script')
<script>
$(document).ready(function() {

    // ── Add new SELL configuration ────────────────────────────────────────────
    $('#addSellConfigForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            url: '{{ route("portfolio.broker-sell-config.store") }}',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    iziToast.success({ message: response.message, position: 'topRight' });
                    setTimeout(() => location.reload(), 1000);
                }
            },
            error: function(xhr) {
                let message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Error adding configuration';
                iziToast.error({ message: message, position: 'topRight' });
            }
        });
    });

    // ── Manual Execute ────────────────────────────────────────────────────────
    $('#executeSellBtn').on('click', function() {
        if (!confirm('Are you sure you want to execute SELL orders now for all active configurations?')) return;

        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Executing...');

        $.ajax({
            url: '{{ route("portfolio.broker-sell-config.execute") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                btn.prop('disabled', false).html(originalText);

                if (response.success) {
                    iziToast.success({ title: 'SELL Execution Complete', message: response.message, position: 'topRight', timeout: 5000 });

                    if (response.summary) {
                        let s = response.summary;
                        let html = `<div class="alert alert--success mt-3">
                            <h6><i class="las la-check-circle"></i> Execution Summary</h6>
                            <ul class="mb-0">
                                <li>Total Positions: ${s.total_positions}</li>
                                <li>CE Orders Placed: ${s.ce_orders}</li>
                                <li>PE Orders Placed: ${s.pe_orders}</li>
                                <li>Failed Orders: ${s.failed_orders}</li>
                            </ul></div>`;
                        $('#configsContainer').prepend(html);
                    }
                } else {
                    iziToast.error({ title: 'Execution Failed', message: response.message || 'Failed to execute SELL orders', position: 'topRight', timeout: 5000 });
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalText);
                let message = xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)
                    ? (xhr.responseJSON.message || xhr.responseJSON.error)
                    : 'Error executing SELL orders';
                iziToast.error({ title: 'Execution Error', message: message, position: 'topRight', timeout: 5000 });
            }
        });
    });

    // ── Edit ──────────────────────────────────────────────────────────────────
    $(document).on('click', '.edit-btn', function() {
        const row = $(this).closest('tr');
        // Hide display spans, show input fields
        row.find('.old-profit-display, .fresh-profit-display, .price-type-display, .qty-percent-display, .position-filter-display').addClass('d-none');
        row.find('.edit-fields').removeClass('d-none');
        $(this).addClass('d-none');
        row.find('.save-btn, .cancel-btn').removeClass('d-none');
        row.find('.toggle-btn, .delete-btn').addClass('d-none');
    });

    $(document).on('click', '.cancel-btn', function() {
        location.reload();
    });

    // ── Save ──────────────────────────────────────────────────────────────────
    $(document).on('click', '.save-btn', function() {
        const configId   = $(this).data('id');
        const row        = $(this).closest('tr');

        const oldProfit      = row.find('.old-profit-input').val();
        const freshProfit    = row.find('.fresh-profit-input').val();
        const skipOld        = row.find('.skip-old-checkbox').is(':checked') ? 1 : 0;
        const skipFresh      = row.find('.skip-fresh-checkbox').is(':checked') ? 1 : 0;
        const priceType      = row.find('.price-type-select').val();
        const qtyPercent     = row.find('.qty-percent-input').val();
        const positionFilter = row.find('.position-filter-select').val();

        $.ajax({
            url: '{{ route("portfolio.broker-sell-config.update", ":id") }}'.replace(':id', configId),
            type: 'PUT',
            data: {
                _token:                         '{{ csrf_token() }}',
                price_type:                     priceType,
                quantity_percent:               qtyPercent,
                position_filter:                positionFilter,
                old_position_profit_percent:    oldProfit,
                fresh_position_profit_percent:  freshProfit,
                skip_old_positions:             skipOld,
                skip_fresh_positions:           skipFresh,
            },
            success: function(response) {
                if (response.success) {
                    iziToast.success({ message: response.message, position: 'topRight' });
                    setTimeout(() => location.reload(), 1000);
                }
            },
            error: function() {
                iziToast.error({ message: 'Error updating configuration', position: 'topRight' });
            }
        });
    });

    // ── Toggle ────────────────────────────────────────────────────────────────
    $(document).on('click', '.toggle-btn', function() {
        const configId = $(this).data('id');

        $.ajax({
            url: '{{ route("portfolio.broker-sell-config.toggle", ":id") }}'.replace(':id', configId),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    iziToast.success({ message: response.message, position: 'topRight' });
                    setTimeout(() => location.reload(), 1000);
                }
            }
        });
    });

    // ── Delete ────────────────────────────────────────────────────────────────
    $(document).on('click', '.delete-btn', function() {
        if (!confirm('Are you sure you want to delete this configuration?')) return;

        const configId = $(this).data('id');

        $.ajax({
            url: '{{ route("portfolio.broker-sell-config.destroy", ":id") }}'.replace(':id', configId),
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    iziToast.success({ message: response.message, position: 'topRight' });
                    setTimeout(() => location.reload(), 1000);
                }
            }
        });
    });
});
</script>
@endpush

@push('style')
<style>
.button-group { display: flex; gap: 5px; flex-wrap: wrap; }
.btn--sm { padding: 5px 10px; font-size: 14px; }
.form--control-sm { padding: 5px 10px; font-size: 14px; width: 100px; }
.badge { padding: 5px 10px; border-radius: 4px; font-weight: 600; }
.badge--success  { background: #10b981; color: white; }
.badge--danger   { background: #ef4444; color: white; }
.badge--info     { background: #0ea5e9; color: white; }
.badge--warning  { background: #f59e0b; color: white; }
.badge--primary  { background: #6366f1; color: white; }
.badge--secondary{ background: #6b7280; color: white; }
.alert--warning  { background: #fef3c7; border-color: #fbbf24; color: #92400e; }
.alert--info     { background: #dbeafe; border-color: #93c5fd; color: #1e40af; }
</style>
@endpush