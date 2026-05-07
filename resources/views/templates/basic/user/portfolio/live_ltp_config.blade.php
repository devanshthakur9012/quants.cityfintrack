@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <!-- Add New Live LTP Config Card -->
        <div class="custom--card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-chart-line"></i> Add New Live LTP SELL Configuration
                </h5>
                <p class="text-muted small mb-0">Place SELL orders based on current Live LTP + profit percentage</p>
            </div>
            <div class="card-body">
                <form id="addLiveLtpConfigForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 form-group">
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
                        <div class="col-md-3 form-group">
                            <label class="required">Symbol Type<sup class="text--danger">*</sup></label>
                            <select class="form--control" name="symbol_type" required>
                                <option value="">Select Type</option>
                                <option value="CE">CE (Call)</option>
                                <option value="PE">PE (Put)</option>
                                <option value="BOTH">BOTH (CE+PE)</option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="required">Profit %<sup class="text--danger">*</sup></label>
                            <input type="number" class="form--control" name="profit_percent" 
                                   value="5" min="0" max="100" step="0.1" required>
                            <small class="text-muted">Add to Live LTP</small>
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
                                <strong><i class="las la-lightbulb"></i> How it works:</strong><br>
                                <small>
                                    • Fetches <strong>Live LTP</strong> (Last Traded Price) from exchange<br>
                                    • Calculates target price: <strong>LTP + Profit %</strong><br>
                                    • Places SELL orders immediately at target price<br>
                                    • Example: LTP = ₹100, Profit = 5% → SELL order at ₹105
                                </small>
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
                            <i class="las la-bolt"></i> Execute Live LTP Orders
                        </h6>
                        <p class="text-muted small mb-0">
                            Fetch live LTP and place SELL orders with configured profit percentage
                        </p>
                    </div>
                    <div class="col-md-6 text-right">
                        <button type="button" class="btn btn--success btn-lg" id="executeLtpBtn">
                            <i class="las la-play-circle"></i> Execute Live LTP Orders Now
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Configs Card -->
        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-cog"></i> Live LTP Configurations
                </h5>
            </div>
            <div class="card-body">
                <div id="configsContainer">
                    @if($configs->isEmpty())
                        <div class="alert alert--warning">
                            <i class="las la-exclamation-triangle"></i> 
                            No Live LTP configurations found. Add your first configuration above.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table custom--table">
                                <thead>
                                    <tr>
                                        <th>Broker</th>
                                        <th>Symbol Type</th>
                                        <th>Profit %</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($configs as $config)
                                    <tr id="config-row-{{ $config->id }}">
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
                                            <span class="profit-display">{{ $config->profit_percent }}%</span>
                                            <input type="number" class="form--control form--control-sm profit-input d-none" 
                                                   value="{{ $config->profit_percent }}" min="0" max="100" step="0.1">
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
                    <h6 class="mb-2"><i class="las la-info-circle"></i> Live LTP SELL Configuration</h6>
                    <ul class="mb-0">
                        <li><strong>Broker Level:</strong> Configure separate settings for each broker account</li>
                        <li><strong>Live LTP:</strong> Fetches current Last Traded Price from exchange</li>
                        <li><strong>Target Price:</strong> LTP + Profit % (e.g., LTP=₹100 + 5% = ₹105)</li>
                        <li><strong>Instant Execution:</strong> Orders placed immediately when you click Execute button</li>
                        <li>Only <strong>ACTIVE</strong> configurations will be used</li>
                        <li>Works during market hours (9:15 AM - 3:30 PM)</li>
                        <li>Respects freezing quantities and tick sizes</li>
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
    // Add new Live LTP configuration
    $('#addLiveLtpConfigForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '{{ route("portfolio.live-ltp-config.store") }}',
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

    // Execute Live LTP Orders
    $('#executeLtpBtn').on('click', function() {
        if (!confirm('Are you sure you want to execute Live LTP orders now?\n\nThis will fetch current LTP and place SELL orders immediately!')) {
            return;
        }
        
        const btn = $(this);
        const originalText = btn.html();
        
        // Disable button and show loading
        btn.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Fetching LTP & Placing Orders...');
        
        $.ajax({
            url: '{{ route("portfolio.live-ltp-config.execute") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                btn.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    iziToast.success({
                        title: 'Live LTP Execution Complete',
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
                        message: response.message || 'Failed to execute Live LTP orders',
                        position: 'topRight',
                        timeout: 5000
                    });
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalText);
                
                let message = 'Error executing Live LTP orders';
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

    // Edit button click
    $(document).on('click', '.edit-btn', function() {
        const row = $(this).closest('tr');
        row.find('.profit-display').addClass('d-none');
        row.find('.profit-input').removeClass('d-none');
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
        
        const profit = row.find('.profit-input').val();
        
        $.ajax({
            url: '{{ route("portfolio.live-ltp-config.update", ":id") }}'.replace(':id', configId),
            type: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                profit_percent: profit
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
            url: '{{ route("portfolio.live-ltp-config.toggle", ":id") }}'.replace(':id', configId),
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
            url: '{{ route("portfolio.live-ltp-config.destroy", ":id") }}'.replace(':id', configId),
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