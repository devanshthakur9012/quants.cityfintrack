@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <!-- Add New AMO Config Card -->
        <div class="custom--card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-moon"></i> Add New AMO Configuration
                </h5>
                <p class="text-muted small mb-0">Configure profit targets for after-market orders by broker and symbol type</p>
            </div>
            <div class="card-body">
                <form id="addAmoConfigForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-2 form-group">
                            <label class="required">Broker Account<sup class="text--danger">*</sup></label>
                            <select class="form--control" name="broker_api_id" required>
                                <option value="">Select Broker</option>
                                @foreach($brokers as $broker)
                                    <option value="{{ $broker->id }}">
                                        {{ $broker->client_name }} ({{ $broker->account_user_name }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label class="required">Symbol Type<sup class="text--danger">*</sup></label>
                            <select class="form--control" name="symbol_type" required>
                                <option value="">Select Type</option>
                                <option value="CE">CE (Call)</option>
                                <option value="PE">PE (Put)</option>
                                <option value="BOTH">BOTH (CE+PE)</option>
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Old Position %</label>
                            <input type="number" class="form--control" name="old_position_profit_percent" 
                                   value="20" min="0" max="100" step="0.1">
                            <div class="custom-control custom-checkbox mt-1">
                                <input type="checkbox" class="custom-control-input" id="skip_old" name="skip_old_positions" value="1">
                                <label class="custom-control-label" for="skip_old">Skip Old</label>
                            </div>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Fresh Position %</label>
                            <input type="number" class="form--control" name="fresh_position_profit_percent" 
                                   value="10" min="0" max="100" step="0.1">
                            <div class="custom-control custom-checkbox mt-1">
                                <input type="checkbox" class="custom-control-input" id="skip_fresh" name="skip_fresh_positions" value="1">
                                <label class="custom-control-label" for="skip_fresh">Skip Fresh</label>
                            </div>
                        </div>
                        <div class="col-md-2 form-group">
                            <label class="required">Date<sup class="text--danger">*</sup></label>
                            <input type="date" class="form--control" name="config_date" 
                                   value="{{ date('Y-m-d') }}" min="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn--base w-100">
                                <i class="las la-plus"></i> Add
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert--info">
                                <strong>Fresh Positions:</strong> Today + Previous trading day (T-1)<br>
                                <strong>Old Positions:</strong> Before T-1<br>
                                <small>Example: If today is Tuesday, fresh = Monday + Tuesday | If today is Monday, fresh = Friday + Monday</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Manual Execution & Actions -->
        <div class="custom--card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-2">
                            <i class="las la-rocket"></i> Manual AMO Execution
                        </h6>
                        <p class="text-muted small mb-0">
                            Execute AMO orders manually for today's active configurations
                        </p>
                    </div>
                    <div class="col-md-6 text-right">
                        <button type="button" class="btn btn--success btn-lg" id="executeAmoBtn">
                            <i class="las la-play-circle"></i> Execute AMO Orders Now
                        </button>
                        <button type="button" class="btn btn--info ml-2" id="copyConfigsBtn">
                            <i class="las la-copy"></i> Copy to Another Date
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Configs Card -->
        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-cog"></i> AMO Configurations for <span id="displayDate">{{ date('d M Y') }}</span>
                </h5>
            </div>
            <div class="card-body">
                <div id="configsContainer">
                    @if($configs->isEmpty())
                        <div class="alert alert--warning">
                            <i class="las la-exclamation-triangle"></i> 
                            No AMO configurations found for today. Add your first configuration above.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table custom--table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Broker</th>
                                        <th>Type</th>
                                        <th>Old % / Skip</th>
                                        <th>Fresh % / Skip</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($configs as $config)
                                    <tr id="config-row-{{ $config->id }}">
                                        <td>{{ \Carbon\Carbon::parse($config->config_date)->format('d M Y') }}</td>
                                        <td>
                                            <strong>{{ $config->brokerApi->client_name }}</strong><br>
                                            <small class="text-muted">{{ $config->brokerApi->account_user_name }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge--{{ $config->symbol_type == 'CE' ? 'success' : ($config->symbol_type == 'PE' ? 'danger' : 'info') }}">
                                                {{ $config->symbol_type }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="old-profit-display">
                                                {{ $config->old_position_profit_percent }}%
                                                @if($config->skip_old_positions)
                                                    <span class="badge badge--warning badge-sm">SKIP</span>
                                                @endif
                                            </span>
                                            <div class="d-none edit-fields">
                                                <input type="number" class="form--control form--control-sm old-profit-input" 
                                                       value="{{ $config->old_position_profit_percent }}" min="0" max="100" step="0.1">
                                                <div class="custom-control custom-checkbox mt-1">
                                                    <input type="checkbox" class="custom-control-input skip-old-checkbox" 
                                                           id="skip_old_{{ $config->id }}" {{ $config->skip_old_positions ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="skip_old_{{ $config->id }}">Skip</label>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fresh-profit-display">
                                                {{ $config->fresh_position_profit_percent }}%
                                                @if($config->skip_fresh_positions)
                                                    <span class="badge badge--warning badge-sm">SKIP</span>
                                                @endif
                                            </span>
                                            <div class="d-none edit-fields">
                                                <input type="number" class="form--control form--control-sm fresh-profit-input" 
                                                       value="{{ $config->fresh_position_profit_percent }}" min="0" max="100" step="0.1">
                                                <div class="custom-control custom-checkbox mt-1">
                                                    <input type="checkbox" class="custom-control-input skip-fresh-checkbox" 
                                                           id="skip_fresh_{{ $config->id }}" {{ $config->skip_fresh_positions ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="skip_fresh_{{ $config->id }}">Skip</label>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($config->is_active)
                                                <span class="badge badge--success">Active</span>
                                            @else
                                                <span class="badge badge--warning">Inactive</span>
                                            @endif
                                        </td>
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
                    <h6 class="mb-2"><i class="las la-info-circle"></i> How AMO Configuration Works</h6>
                    <ul class="mb-0">
                        <li><strong>Broker Level:</strong> Configure separate settings for each broker account</li>
                        <li><strong>CE (Call Options):</strong> Only call option positions will use this config</li>
                        <li><strong>PE (Put Options):</strong> Only put option positions will use this config</li>
                        <li><strong>BOTH (CE+PE):</strong> Both call and put options will use this config</li>
                        <li><strong>Date-Based:</strong> Configurations are date-specific for precise control</li>
                        <li>AMO orders are placed <strong>after market hours (after 3:30 PM)</strong></li>
                        <li>Only <strong>ACTIVE</strong> configurations will be used when placing AMO orders</li>
                        <li>Orders respect freezing quantities and are split automatically if needed</li>
                        <li>Prices are rounded to correct tick sizes from Zerodha instruments</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Copy Config Modal -->
<div class="modal fade" id="copyConfigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="las la-copy"></i> Copy Configurations</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="copyConfigForm">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" class="form--control" name="from_date" value="{{ date('Y-m-d') }}" readonly>
                    </div>
                    <div class="form-group">
                        <label>To Date<sup class="text--danger">*</sup></label>
                        <input type="date" class="form--control" name="to_date" min="{{ date('Y-m-d', strtotime('+1 day')) }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn--base">
                        <i class="las la-copy"></i> Copy Configurations
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
    // Add new AMO configuration
    $('#addAmoConfigForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '{{ route("portfolio.amo-config.store") }}',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    iziToast.success({
                        message: response.message,
                        position: 'topRight'
                    });
                    
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            },
            error: function(xhr) {
                let message = 'Error adding configuration';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                
                iziToast.error({
                    message: message,
                    position: 'topRight'
                });
            }
        });
    });

    // Execute AMO Orders Manually
    $('#executeAmoBtn').on('click', function() {
        if (!confirm('Are you sure you want to execute AMO orders now for all active configurations?')) {
            return;
        }
        
        const btn = $(this);
        const originalText = btn.html();
        
        // Disable button and show loading
        btn.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Executing...');
        
        $.ajax({
            url: '{{ route("portfolio.amo-config.execute") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                btn.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    iziToast.success({
                        title: 'AMO Execution Complete',
                        message: response.message,
                        position: 'topRight',
                        timeout: 5000
                    });
                    
                    // Show execution summary if available
                    if (response.summary) {
                        let summaryHtml = '<div class="alert alert--success mt-3">';
                        summaryHtml += '<h6><i class="las la-check-circle"></i> Execution Summary</h6>';
                        summaryHtml += '<ul class="mb-0">';
                        summaryHtml += '<li>Total Positions: ' + response.summary.total_positions + '</li>';
                        summaryHtml += '<li>CE Orders Placed: ' + response.summary.ce_orders + '</li>';
                        summaryHtml += '<li>PE Orders Placed: ' + response.summary.pe_orders + '</li>';
                        summaryHtml += '<li>Failed Orders: ' + response.summary.failed_orders + '</li>';
                        summaryHtml += '</ul>';
                        summaryHtml += '</div>';
                        
                        $('#configsContainer').prepend(summaryHtml);
                    }
                } else {
                    iziToast.error({
                        title: 'Execution Failed',
                        message: response.message || 'Failed to execute AMO orders',
                        position: 'topRight',
                        timeout: 5000
                    });
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalText);
                
                let message = 'Error executing AMO orders';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                    message = xhr.responseJSON.error;
                }
                
                iziToast.error({
                    title: 'Execution Error',
                    message: message,
                    position: 'topRight',
                    timeout: 5000
                });
            }
        });
    });

    // Load configs for selected date
    $('#loadConfigsBtn').on('click', function() {
        const selectedDate = $('#filterDate').val();
        
        $.ajax({
            url: '{{ route("portfolio.amo-config.get-by-date") }}',
            type: 'GET',
            data: { date: selectedDate },
            success: function(response) {
                if (response.success) {
                    updateConfigsTable(response.data, selectedDate);
                }
            },
            error: function(xhr) {
                iziToast.error({
                    message: 'Error loading configurations',
                    position: 'topRight'
                });
            }
        });
    });

    // Show copy modal
    $('#copyConfigsBtn').on('click', function() {
        $('#copyConfigModal').modal('show');
    });

    // Copy configurations
    $('#copyConfigForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '{{ route("portfolio.amo-config.copy") }}',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    iziToast.success({
                        message: response.message,
                        position: 'topRight'
                    });
                    
                    $('#copyConfigModal').modal('hide');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            },
            error: function(xhr) {
                let message = 'Error copying configurations';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                
                iziToast.error({
                    message: message,
                    position: 'topRight'
                });
            }
        });
    });

    // Edit, Save, Cancel, Toggle, Delete
    $(document).on('click', '.edit-btn', function() {
        const row = $(this).closest('tr');
        row.find('.old-profit-display, .fresh-profit-display').addClass('d-none');
        row.find('.edit-fields').removeClass('d-none');
        $(this).addClass('d-none');
        row.find('.save-btn, .cancel-btn').removeClass('d-none');
        row.find('.toggle-btn, .delete-btn').addClass('d-none');
    });

    $(document).on('click', '.cancel-btn', function() {
        location.reload();
    });

    $(document).on('click', '.save-btn', function() {
        const configId = $(this).data('id');
        const row = $(this).closest('tr');
        
        const oldProfit = row.find('.old-profit-input').val();
        const freshProfit = row.find('.fresh-profit-input').val();
        const skipOld = row.find('.skip-old-checkbox').is(':checked') ? 1 : 0;
        const skipFresh = row.find('.skip-fresh-checkbox').is(':checked') ? 1 : 0;
        
        $.ajax({
            url: '{{ route("portfolio.amo-config.update", ":id") }}'.replace(':id', configId),
            type: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                old_position_profit_percent: oldProfit,
                fresh_position_profit_percent: freshProfit,
                skip_old_positions: skipOld,
                skip_fresh_positions: skipFresh
            },
            success: function(response) {
                if (response.success) {
                    iziToast.success({
                        message: response.message,
                        position: 'topRight'
                    });
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            },
            error: function(xhr) {
                iziToast.error({
                    message: 'Error updating configuration',
                    position: 'topRight'
                });
            }
        });
    });

    $(document).on('click', '.toggle-btn', function() {
        const configId = $(this).data('id');
        
        $.ajax({
            url: '{{ route("portfolio.amo-config.toggle", ":id") }}'.replace(':id', configId),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    iziToast.success({
                        message: response.message,
                        position: 'topRight'
                    });
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            }
        });
    });

    $(document).on('click', '.delete-btn', function() {
        if (!confirm('Are you sure you want to delete this configuration?')) {
            return;
        }
        
        const configId = $(this).data('id');
        
        $.ajax({
            url: '{{ route("portfolio.amo-config.destroy", ":id") }}'.replace(':id', configId),
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    iziToast.success({
                        message: response.message,
                        position: 'topRight'
                    });
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            }
        });
    });

    function updateConfigsTable(configs, date) {
        // Update display date
        const dateObj = new Date(date);
        $('#displayDate').text(dateObj.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }));
        
        // Build table HTML or show empty message
        // ... implementation similar to existing code
    }
});
</script>
@endpush

@push('style')
<style>
.button-group {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn--sm {
    padding: 5px 10px;
    font-size: 14px;
}

.form--control-sm {
    padding: 5px 10px;
    font-size: 14px;
    width: 100px;
}

.badge {
    padding: 5px 10px;
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

.badge--info {
    background: #0ea5e9;
    color: white;
}

.badge--warning {
    background: #f59e0b;
    color: white;
}

.alert--warning {
    background: #fef3c7;
    border-color: #fbbf24;
    color: #92400e;
}

.alert--info {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
}
</style>
@endpush