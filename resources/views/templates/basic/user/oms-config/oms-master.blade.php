@extends($activeTemplate.'layouts.master')
@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="text-end">
            <button class="btn btn--base" type="button" data-bs-toggle="modal" data-bs-target="#masterConfigModal">
                <i class="las la-plus"></i> @lang('Add Master Configuration')
            </button>
        </div>
        <div class="row mt-3">
            <div class="col-lg-12">
                <div class="custom--card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">OMS Master Configurations</h5>
                        <p class="text-muted small">These configurations will automatically pick up new symbols and place orders</p>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive--md table-responsive transparent-form">
                            <table class="table custom--table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Created At</th>
                                        <th>Portfolio Type</th>
                                        <th>Buildup Type</th>
                                        <th>Broker</th>
                                        <th>Order Type</th>
                                        <th>Product</th>
                                        <th>Disc LTP %</th>
                                        <th>Quantity</th>
                                        <th>Pyramid %</th>
                                        <th>Frequency (min)</th>
                                        <th>Active Symbols</th>
                                        <th>Last Sync</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($masterConfigs as $master)
                                        <tr>
                                            <td>{{ "OMS00".$master->id }}</td>
                                            <td>{{ $master->created_at->format('d M Y H:i') }}</td>
                                            <td>
                                                <span class="badge badge--primary">{{ portfolioName($master->portfolio_type) }}</span>
                                            </td>
                                            <td>
                                                <span class="badge badge--info">{{ $master->buildup_type }}</span>
                                            </td>
                                            <td>{{ $master->broker->client_name ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge {{ $master->order_type == 'LIMIT' ? 'badge--warning' : 'badge--success' }}">
                                                    {{ $master->order_type }}
                                                </span>
                                            </td>
                                            <td>{{ $master->product }}</td>
                                            <td>{{ $master->order_type == 'LIMIT' ? $master->disc_ltp.'%' : '-' }}</td>
                                            <td>{{ $master->quantity }}</td>
                                            <td>{{ $master->pyramid_percent ?? '-' }}</td>
                                            <td>{{ $master->pyramid_freq }}</td>
                                            <td>
                                                <span class="badge badge--dark">
                                                    {{ $master->omsConfigs()->count() }} symbols
                                                </span>
                                            </td>
                                            <td>
                                                @if($master->last_sync_at)
                                                    {{ $master->last_sync_at->diffForHumans() }}
                                                @else
                                                    <span class="text-muted">Never</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($master->status == 1)
                                                    <span class="badge badge--success">Active</span>
                                                @else
                                                    <span class="badge badge--danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="{{ route('user.oms-config-order',['id'=>$master->id]) }}" class="btn btn-sm btn-primary" target="_blank"
                                                            title="View Symbols">
                                                        <i class="las la-eye"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-danger delete-master" 
                                                            data-id="{{ $master->id }}"
                                                            title="Inactive">
                                                        <i class="las la-power-off"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="14" class="text-center">
                                                <div class="py-4">
                                                    <i class="las la-inbox text-muted" style="font-size: 3rem;"></i>
                                                    <h5 class="text-muted">No Master Configurations Found</h5>
                                                    <p class="text-muted">Create a master configuration to automatically manage symbol orders</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="mt-4 justify-content-center d-flex">
            {{ $masterConfigs->links() }}
        </div>
    </div>
</section>

<!-- Add Master Configuration Modal -->
<div class="modal fade" id="masterConfigModal" tabindex="-1" aria-labelledby="masterConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('user.oms-config-store') }}" class="transparent-form" method="post">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="masterConfigModalLabel">Add Master Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert--info">
                        <i class="las la-info-circle"></i>
                        <strong>Master Configuration</strong> - This will automatically detect and place orders for new symbols matching your criteria.
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-6 form-group">
                            <label for="portfolio_type" class="required">Portfolio Type<sup class="text--danger">*</sup></label>
                            <select name="portfolio_type" class="form--control" required="" id="portfolio_type">
                                <option value="">Select Portfolio Type</option>
                                <option value="PF_1">Directional</option> 
                                <option value="PF_2">Bi-Directional</option> 
                                <option value="Portfolio-Futures-Direct">Futures Direct</option> 
                                <option value="Portfolio-Options-Opposite">Options Opposite</option>
                                <option value="Portfolio-Futures-Opposite">Futures Opposite</option>
                            </select>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label for="buildup_type" class="required">Buildup Type<sup class="text--danger">*</sup></label>
                            <select name="buildup_type" class="form--control" required="" id="buildup_type">
                                <option value="">Select Buildup Type</option>
                                <!-- <option value="all">All Types</option>   -->
                                <option value="Long Built Up">Long Built Up</option> 
                                <option value="Short Built Up">Short Built Up</option> 
                                <option value="Short Covering">Short Covering</option> 
                                <option value="Long Unwinding">Long Unwinding</option>
                            </select>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label for="broker_api_id" class="required">Broker<sup class="text--danger">*</sup></label>
                            <select name="broker_api_id" class="form--control" required="" id="broker_api_id">
                                <option value="">Select Broker</option>
                                @foreach ($brokers as $broker)
                                    <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label for="disc_ltp" class="required">Disc LTP %<sup class="text--danger">*</sup></label>
                            <input type="number" name="disc_ltp" max="100" min="0" step="0.01" 
                                   placeholder="Enter Discount LTP %" class="form--control" required="" id="disc_ltp">
                            <small class="text-muted">Discount percentage from current LTP for limit orders</small>
                        </div>
                        
                        <div class="col-lg-6 form-group">
                            <label for="order_type" class="required">Order Type<sup class="text--danger">*</sup></label>
                            <select name="order_type" class="form--control" required="" id="order_type">
                                <option value="">Select Order Type</option>
                                <option value="LIMIT" selected>LIMIT</option>
                                <option value="MARKET">MARKET</option>  
                            </select>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label for="pyramid_percent">Pyramid Percentage</label>
                            <select name="pyramid_percent" class="form--control" id="pyramid_percent">
                                <option value="">No Pyramid (100%)</option>
                                <option value="33">33% (3 levels)</option>
                                <option value="50" selected>50% (2 levels)</option>  
                                <option value="100">100% (1 level)</option>  
                            </select>
                            <small class="text-muted">How to split the quantity across multiple orders</small>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label for="product" class="required">Product<sup class="text--danger">*</sup></label>
                            <select name="product" class="form--control" id="product" required>
                                <option value="">Select Product</option>
                                <option value="NRML" selected>NRML (Normal)</option>
                                <option value="MIS">MIS (Intraday)</option>  
                            </select>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label for="quantity" class="required">Base Quantity<sup class="text--danger">*</sup></label>
                            <input type="number" name="quantity" placeholder="Enter Base Quantity" 
                                   id="quantity" class="form--control" min="1" required>
                            <small class="text-muted">Base quantity (will be multiplied by lot size)</small>
                        </div> 

                        <div class="col-lg-6 form-group">
                            <label for="pyramid_freq" class="required">Pyramid Frequency (Minutes)<sup class="text--danger">*</sup></label>
                            <input type="number" name="pyramid_freq" placeholder="Enter Frequency" 
                                   id="pyramid_freq" class="form--control" min="0" required>
                            <small class="text-muted">Time gap between pyramid orders (0 = immediate)</small>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label for="status" class="required">Status<sup class="text--danger">*</sup></label>
                            <select name="status" id="status" class="form--control" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div> 
                        
                        <!-- <div class="col-lg-12">
                            <hr>
                            <h6>Exit Strategy (Optional)</h6>
                        </div>
                        
                        <div class="col-lg-6 form-group">
                            <label for="exit_1_qty">Exit 1 Quantity</label>
                            <input type="number" name="exit_1_qty" placeholder="Exit 1 Qty" 
                                   id="exit_1_qty" class="form--control" step="0.01" min="0">
                        </div>
                        
                        <div class="col-lg-6 form-group">
                            <label for="exit_1_target">Exit 1 Target</label>
                            <input type="number" name="exit_1_target" placeholder="Exit 1 Target" 
                                   id="exit_1_target" class="form--control" step="0.01" min="0">
                        </div>
                        
                        <div class="col-lg-6 form-group">
                            <label for="exit_2_qty">Exit 2 Quantity</label>
                            <input type="number" name="exit_2_qty" placeholder="Exit 2 Qty" 
                                   id="exit_2_qty" class="form--control" step="0.01" min="0">
                        </div>
                        
                        <div class="col-lg-6 form-group">
                            <label for="exit_2_target">Exit 2 Target</label>
                            <input type="number" name="exit_2_target" placeholder="Exit 2 Target" 
                                   id="exit_2_target" class="form--control" step="0.01" min="0">
                        </div> -->
                         
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn--base">Create Master Config</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Symbols Modal -->
<div class="modal fade" id="viewSymbolsModal" tabindex="-1" aria-labelledby="viewSymbolsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSymbolsModalLabel">Active Symbols</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="symbolsModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading symbols...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Inactive</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert--warning">
                    <i class="las la-exclamation-triangle"></i>
                    <strong>Warning!</strong> This will inactive the master configuration.
                </div>
                <p>Are you sure you want to inactive this master configuration?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Yes, Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
$(document).ready(function() {
    // View Symbols
    $('.view-symbols').on('click', function() {
        const masterId = $(this).data('id');
        $('#viewSymbolsModal').modal('show');
        
        $.ajax({
            url: "".replace(':id', masterId),
            method: 'GET',
            success: function(response) {
                $('#symbolsModalBody').html(response);
            },
            error: function() {
                $('#symbolsModalBody').html(
                    '<div class="alert alert--danger">' +
                    '<i class="las la-exclamation-triangle"></i> Error loading symbols' +
                    '</div>'
                );
            }
        });
    });
    
    // Delete Master Config
    $('.delete-master').on('click', function() {
        const masterId = $(this).data('id');
        const deleteUrl = "{{ route('user.oms-config-master-destroy', ':id') }}".replace(':id', masterId);
        $('#deleteForm').attr('action', deleteUrl);
        $('#deleteModal').modal('show');
    });
    
    // Form validation and UX improvements
    $('#order_type').on('change', function() {
        const isLimit = $(this).val() === 'LIMIT';
        $('#disc_ltp').prop('required', isLimit);
        if (!isLimit) {
            $('#disc_ltp').val('0');
        }
    });
    
    // Auto-calculate pyramid display
    $('#pyramid_percent, #quantity').on('change', function() {
        const percent = parseInt($('#pyramid_percent').val()) || 100;
        const quantity = parseInt($('#quantity').val()) || 0;
        
        if (quantity > 0) {
            let levels = 1;
            if (percent === 33) levels = 3;
            else if (percent === 50) levels = 2;
            
            const pyramids = calculatePyramids(quantity, levels);
            
            // Show preview (you can add a preview div)
            console.log('Pyramid breakdown:', pyramids);
        }
    });
});

// Helper function to calculate pyramids (same as PHP)
function calculatePyramids(total, levels) {
    if (levels === 1) return [total];
    if (levels === 2) return [Math.floor(total/2), Math.ceil(total/2)];
    if (levels === 3) {
        const third = Math.floor(total/3);
        return [third, third, total - (third * 2)];
    }
    return [total];
}
</script>
@endpush

@push('style')
<style>
.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
.badge--primary { background-color: #3b82f6; color: white; }
.badge--info { background-color: #06b6d4; color: white; }
.badge--warning { background-color: #f59e0b; color: white; }
.badge--success { background-color: #10b981; color: white; }
.badge--danger { background-color: #ef4444; color: white; }
.badge--dark { background-color: #374151; color: white; }

.alert--info { 
    background-color: #dbeafe; 
    border-color: #93c5fd; 
    color: #1e40af; 
}
.alert--warning { 
    background-color: #fef3c7; 
    border-color: #fcd34d; 
    color: #92400e; 
}
.alert--danger { 
    background-color: #fee2e2; 
    border-color: #fca5a5; 
    color: #dc2626; 
}

.btn-group .btn {
    margin-right: 0.25rem;
}

.spinner-border {
    width: 2rem;
    height: 2rem;
    border-width: 0.2em;
}
</style>
@endpush

@endsection