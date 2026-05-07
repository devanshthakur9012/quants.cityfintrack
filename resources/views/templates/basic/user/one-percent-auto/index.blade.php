@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="text-end mb-3">
            <button class="btn btn--base" data-bs-toggle="modal" data-bs-target="#configModal">
                <i class="las la-plus"></i> Add One-Percent Config
            </button>
        </div>

        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">One-Percent Auto Trading Configurations</h5>
                <p class="text-muted small">Automatically trade CE/PE options based on ±X% move + OI analysis</p>
            </div>
            <div class="card-body p-0">
                <div class="alert alert--info m-3">
                    <i class="las la-info-circle"></i>
                    <strong>How It Works:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>+X% Move:</strong> If price moves up by threshold → Check CE OI Signal</li>
                        <li><strong>-X% Move:</strong> If price moves down by threshold → Check PE OI Signal</li>
                        <li><strong>OI Confirmation:</strong> Order placed ONLY if OI signal is BULLISH</li>
                        <li><strong>Multiple Orders:</strong> Can create multiple orders per day per symbol (alternating signals)</li>
                    </ul>
                    <div class="mt-2">
                        <strong>Example:</strong>
                        <ul class="mb-0 mt-1">
                            <li>NIFTY opens at 23,500 → Moves to 23,735 (+1%)</li>
                            <li>System checks: CE Signal = BULLISH ✅</li>
                            <li>Action: Place BUY CE order</li>
                        </ul>
                    </div>
                </div>

                <div class="table-responsive--md table-responsive">
                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>Config ID</th>
                                <th>Broker</th>
                                <th>Move %</th>
                                <th>Option Series</th>
                                <th>Order Type</th>
                                <th>Product</th>
                                <th>Index Qty</th>
                                <th>Stock Qty</th>
                                <th>Profit %</th>
                                <th>Pyramid</th>
                                <th>Orders</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($configs as $config)
                                <tr>
                                    <td><strong>1PCT{{ str_pad($config->id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                                    <td>{{ $config->broker->client_name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge badge--warning">
                                            ±{{ $config->move_threshold }}%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge--{{ $config->option_series == 'current' ? 'info' : 'warning' }}">
                                            {{ strtoupper($config->option_series) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $config->order_type == 'LIMIT' ? 'badge--warning' : 'badge--success' }}">
                                            {{ $config->order_type }}
                                        </span>
                                    </td>
                                    <td>{{ $config->product }}</td>
                                    <td><strong>{{ $config->index_quantity }}</strong></td>
                                    <td><strong>{{ $config->stock_quantity }}</strong></td>
                                    <td><strong class="text-success">{{ $config->profit_percent }}%</strong></td>
                                    <td>{{ $config->pyramid_percent }}% / {{ $config->pyramid_freq }}min</td>
                                    <td>
                                        <span class="badge badge--dark">{{ $config->orders_count }}</span>
                                    </td>
                                    <td>
                                        @if ($config->status)
                                            <span class="badge badge--success">Active</span>
                                        @else
                                            <span class="badge badge--danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('one-percent-auto.orders', $config->id) }}" 
                                               class="btn btn-sm btn-primary" 
                                               title="View Orders">
                                                <i class="las la-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-{{ $config->status ? 'warning' : 'success' }} toggle-status"
                                                    data-id="{{ $config->id }}" 
                                                    title="{{ $config->status ? 'Deactivate' : 'Activate' }}">
                                                <i class="las la-{{ $config->status ? 'pause' : 'play' }}"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info edit-config"
                                                data-config='@json($config)'
                                                title="Edit">
                                                <i class="las la-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-config"
                                                    data-id="{{ $config->id }}" 
                                                    title="Delete">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center py-5">
                                        <i class="las la-inbox text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted">No Configurations Found</h5>
                                        <p class="text-muted">Create a configuration to start one-percent auto trading</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $configs->links() }}
        </div>
    </div>
</section>
@endsection

<!-- Config Modal -->
<div class="modal fade" id="configModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="configForm" action="{{ route('one-percent-auto.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add One-Percent Auto Trading Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert--warning">
                        <i class="las la-exclamation-triangle"></i>
                        <strong>Important:</strong> Orders are placed only when ±X% move is confirmed by OI analysis. 
                        Multiple orders per day allowed (alternating signals).
                    </div>

                    <div class="row">
                        <div class="col-lg-6 form-group">
                            <label class="required">Zerodha Broker<sup class="text--danger">*</sup></label>
                            <select name="broker_api_id" class="form--control" required>
                                <option value="">Select Broker</option>
                                @foreach ($brokers as $broker)
                                    <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="config_id" id="config_id">
                            <input type="hidden" name="_method" id="form_method" value="POST">
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Move Threshold %<sup class="text--danger">*</sup></label>
                            <select name="move_threshold" class="form--control" required>
                                <option value="0.5">0.5% (Very Sensitive)</option>
                                <option value="0.75">0.75% (Balanced)</option>
                                <option value="1.0" selected>1.0% (Recommended)</option>
                                <option value="1.25">1.25% (Moderate)</option>
                                <option value="1.5">1.5% (Conservative)</option>
                                <option value="2.0">2.0% (Very Conservative)</option>
                                <option value="2.5">2.5%</option>
                                <option value="3.0">3.0%</option>
                            </select>
                            <small class="text-muted">Higher % = Fewer signals but stronger moves</small>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Option Series<sup class="text--danger">*</sup></label>
                            <select name="option_series" class="form--control" required>
                                <option value="current" selected>Current Series (Same as FUT)</option>
                                <option value="next">Next Series (Skip to Next Expiry)</option>
                            </select>
                            <small class="text-muted">
                                <strong>Current:</strong> JAN FUT → JAN CE/PE | 
                                <strong>Next:</strong> JAN FUT → FEB CE/PE
                            </small>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Order Type<sup class="text--danger">*</sup></label>
                            <select name="order_type" class="form--control" id="order_type" required>
                                <option value="LIMIT" selected>LIMIT</option>
                                <option value="MARKET">MARKET</option>
                            </select>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Product<sup class="text--danger">*</sup></label>
                            <select name="product" class="form--control" required>
                                <option value="MIS" selected>MIS (Intraday)</option>
                                <option value="NRML">NRML (Normal)</option>
                            </select>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Disc LTP %<sup class="text--danger">*</sup></label>
                            <input type="number" name="disc_ltp" class="form--control" 
                                   id="disc_ltp" min="0" max="100" step="0.01" 
                                   value="0.5" required>
                            <small class="text-muted">Discount from LTP for LIMIT orders</small>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Target Profit %<sup class="text--danger">*</sup></label>
                            <input type="number" name="profit_percent" class="form--control" 
                                id="profit_percent" min="0" max="1000" step="0.01" 
                                value="5.00" required>
                            <small class="text-muted">Auto-SELL target</small>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Index Quantity (Lots)<sup class="text--danger">*</sup></label>
                            <input type="number" name="index_quantity" class="form--control" 
                                   min="0" value="1" required>
                            <small class="text-muted">For NIFTY, BANKNIFTY, FINNIFTY</small>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Stock Quantity (Lots)<sup class="text--danger">*</sup></label>
                            <input type="number" name="stock_quantity" class="form--control" 
                                   min="0" value="1" required>
                            <small class="text-muted">For all stock futures</small>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label>Pyramid Percentage</label>
                            <select name="pyramid_percent" class="form--control">
                                <option value="100" selected>100% (Single order)</option>
                                <option value="50">50% (2 levels)</option>
                                <option value="33">33% (3 levels)</option>
                            </select>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Pyramid Frequency<sup class="text--danger">*</sup></label>
                            <input type="number" name="pyramid_freq" class="form--control" 
                                   min="0" value="0" required>
                            <small class="text-muted">Minutes between levels (0 = immediate)</small>
                        </div>

                        <div class="col-lg-6 form-group">
                            <label class="required">Status<sup class="text--danger">*</sup></label>
                            <select name="status" class="form--control" required>
                                <option value="1" selected>Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn--base">Create Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this configuration?</p>
                <p class="text-danger"><small>Pending orders will not be placed.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
$(document).ready(function() {
    $('.toggle-status').click(function() {
        const configId = $(this).data('id');
        const url = "{{ route('one-percent-auto.toggle', ':id') }}".replace(':id', configId);
        
        $.post(url, {
            _token: '{{ csrf_token() }}'
        }).done(function() {
            location.reload();
        }).fail(function() {
            alert('Error toggling status');
        });
    });

    $('.delete-config').click(function() {
        const configId = $(this).data('id');
        const url = "{{ route('one-percent-auto.destroy', ':id') }}".replace(':id', configId);
        $('#deleteForm').attr('action', url);
        $('#deleteModal').modal('show');
    });

    $('#order_type').change(function() {
        const isLimit = $(this).val() === 'LIMIT';
        $('#disc_ltp').prop('required', isLimit);
        if (!isLimit) {
            $('#disc_ltp').val('0');
        }
    });

    $('.edit-config').on('click', function () {
        const config = $(this).data('config');

        $('#configModal').modal('show');

        $('#configForm').attr(
            'action',
            "{{ route('one-percent-auto.update', ':id') }}".replace(':id', config.id)
        );

        $('#form_method').val('PUT');
        $('#config_id').val(config.id);

        $('[name="broker_api_id"]').val(config.broker_api_id);
        $('[name="move_threshold"]').val(config.move_threshold);
        $('[name="option_series"]').val(config.option_series);
        $('[name="order_type"]').val(config.order_type);
        $('[name="product"]').val(config.product);
        $('[name="disc_ltp"]').val(config.disc_ltp);
        $('[name="profit_percent"]').val(config.profit_percent);
        $('[name="index_quantity"]').val(config.index_quantity);
        $('[name="stock_quantity"]').val(config.stock_quantity);
        $('[name="pyramid_percent"]').val(config.pyramid_percent);
        $('[name="pyramid_freq"]').val(config.pyramid_freq);
        $('[name="status"]').val(config.status);
    });
});
</script>
@endpush

@push('style')
<style>
.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
.badge--success { background: #10b981; color: white; }
.badge--danger { background: #ef4444; color: white; }
.badge--warning { background: #f59e0b; color: white; }
.badge--dark { background: #374151; color: white; }
.badge--primary { background: #3b82f6; color: white; }
.badge--secondary { background: #6b7280; color: white; }
.badge--info { background: #06b6d4; color: white; }
.alert--info { background: #dbeafe; border-color: #93c5fd; color: #1e40af; }
.alert--warning { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
</style>
@endpush