@extends($activeTemplate . 'layouts.master')

@section('content')
    <section class="pt-100 pb-100">
        <div class="container content-container">
            <div class="text-end mb-3">
                <button class="btn btn--base" data-bs-toggle="modal" data-bs-target="#configModal">
                    <i class="las la-plus"></i> Add Auto Trading Config
                </button>
            </div>

            <div class="custom--card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Expiry Auto Trading Configurations (1-Minute)</h5>
                    <p class="text-muted small">Automatically trade based on 1-minute Supertrend signals</p>
                </div>
                <div class="card-body p-0">
                    <div class="alert alert--info m-3">
                        <i class="las la-info-circle"></i>
                        <strong>Auto Trading Info</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Signal:</strong> Based on 1-minute Supertrend indicator</li>
                            <li><strong>Instruments:</strong> NIFTY, BANKNIFTY, SENSEX futures</li>
                            <li><strong>Freeze Limits:</strong> Orders are automatically split if they exceed NSE limits
                            </li>
                        </ul>
                    </div>

                    <div class="table-responsive--md table-responsive">
                        <table class="table custom--table">
                            <thead>
                                <tr>
                                    <th>Config ID</th>
                                    <th>Broker</th>
                                    <th>Order Type</th>
                                    <th>Product</th>
                                    <th>Disc %</th>
                                    <th>NIFTY Qty</th>
                                    <th>BANKNIFTY Qty</th>
                                    <th>SENSEX Qty</th>
                                    <th>Pyramid</th>
                                    <th>Orders</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($configs as $config)
                                    <tr>
                                        <td><strong>AUTO{{ str_pad($config->id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                                        <td>{{ $config->broker->client_name ?? 'N/A' }}</td>
                                        <td>
                                            <span
                                                class="badge {{ $config->order_type == 'LIMIT' ? 'badge--warning' : 'badge--success' }}">
                                                {{ $config->order_type }}
                                            </span>
                                        </td>
                                        <td>{{ $config->product }}</td>
                                        <td>{{ $config->disc_ltp }}%</td>
                                        <td><strong>{{ $config->nifty_quantity }}</strong></td>
                                        <td><strong>{{ $config->banknifty_quantity }}</strong></td>
                                        <td><strong>{{ $config->sensex_quantity }}</strong></td>
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
                                                <a href="{{ route('expiry.auto.orders', $config->id) }}"
                                                    class="btn btn-sm btn-primary" title="View Orders">
                                                    <i class="las la-eye"></i>
                                                </a>
                                                <button
                                                    class="btn btn-sm btn-{{ $config->status ? 'warning' : 'success' }} toggle-status"
                                                    data-id="{{ $config->id }}"
                                                    title="{{ $config->status ? 'Deactivate' : 'Activate' }}">
                                                    <i class="las la-{{ $config->status ? 'pause' : 'play' }}"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-config"
                                                    data-id="{{ $config->id }}" title="Delete">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center py-5">
                                            <i class="las la-inbox text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="text-muted">No Configurations Found</h5>
                                            <p class="text-muted">Create a configuration to start auto trading</p>
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

    <!-- Config Modal -->
    <div class="modal fade" id="configModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('expiry.auto.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Auto Trading Configuration (1-Minute)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert--warning">
                            <i class="las la-exclamation-triangle"></i>
                            <strong>Important:</strong> This configuration will trade based on 1-minute Supertrend signals
                            for NIFTY, BANKNIFTY, and SENSEX futures.
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
                                    <option value="NRML" selected>NRML (Normal)</option>
                                    <option value="MIS">MIS (Intraday)</option>
                                </select>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Disc LTP %<sup class="text--danger">*</sup></label>
                                <input type="number" name="disc_ltp" class="form--control" id="disc_ltp" min="0"
                                    max="100" step="0.01" value="0.5" required>
                                <small class="text-muted">Discount from LTP for LIMIT orders</small>
                            </div>

                            <div class="col-lg-4 form-group">
                                <label class="required">NIFTY Quantity (Lots)<sup class="text--danger">*</sup></label>
                                <input type="number" name="nifty_quantity" class="form--control" min="1"
                                    value="1" required>
                                <small class="text-muted">Auto-splits if > 24 lots</small>
                            </div>

                            <div class="col-lg-4 form-group">
                                <label class="required">BANKNIFTY Quantity (Lots)<sup class="text--danger">*</sup></label>
                                <input type="number" name="banknifty_quantity" class="form--control" min="1"
                                    value="1" required>
                                <small class="text-muted">Auto-splits if > 25 lots</small>
                            </div>

                            <div class="col-lg-4 form-group">
                                <label class="required">SENSEX Quantity (Lots)<sup class="text--danger">*</sup></label>
                                <input type="number" name="sensex_quantity" class="form--control" min="1"
                                    value="1" required>
                                <small class="text-muted">Quantity per order</small>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label>Pyramid Percentage</label>
                                <select name="pyramid_percent" class="form--control">
                                    <option value="100" selected>100% (Single order)</option>
                                    <option value="50">50% (2 levels)</option>
                                    <option value="33">33% (3 levels)</option>
                                </select>
                                <small class="text-muted">Split quantity into multiple levels</small>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Pyramid Frequency<sup class="text--danger">*</sup></label>
                                <input type="number" name="pyramid_freq" class="form--control" min="0"
                                    value="0" required>
                                <small class="text-muted">Minutes between levels (0 = immediate)</small>
                            </div>

                            <div class="col-lg-12 form-group">
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
                    <p>Are you sure you want to deactivate this configuration?</p>
                    <p class="text-danger"><small>The configuration will be set to inactive status.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Yes, Deactivate</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('script')
        <script>
            $(document).ready(function() {
                // Toggle status
                $('.toggle-status').click(function() {
                    const configId = $(this).data('id');
                    const url = "{{ route('expiry.auto.toggle', ':id') }}".replace(':id', configId);

                    $.post(url, {
                        _token: '{{ csrf_token() }}'
                    }).done(function(response) {
                        if (response.success) {
                            iziToast.success({
                                message: response.message,
                                position: "topRight"
                            });
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    }).fail(function(xhr) {
                        iziToast.error({
                            message: xhr.responseJSON?.message || 'Error toggling status',
                            position: "topRight"
                        });
                    });
                });

                // Delete config
                $('.delete-config').click(function() {
                    const configId = $(this).data('id');
                    const url = "{{ route('expiry.auto.destroy', ':id') }}".replace(':id', configId);
                    $('#deleteForm').attr('action', url);
                    $('#deleteModal').modal('show');
                });

                // Handle order type change
                $('#order_type').change(function() {
                    const isLimit = $(this).val() === 'LIMIT';
                    $('#disc_ltp').prop('required', isLimit);
                    if (!isLimit) {
                        $('#disc_ltp').val('0');
                    } else {
                        $('#disc_ltp').val('0.5');
                    }
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

            .badge--dark {
                background: #374151;
                color: white;
            }

            .badge--primary {
                background: #3b82f6;
                color: white;
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

            .btn-group {
                display: flex;
                gap: 2px;
            }
        </style>
    @endpush
@endsection