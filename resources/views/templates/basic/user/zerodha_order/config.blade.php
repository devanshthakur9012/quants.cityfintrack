@extends($activeTemplate . 'layouts.master')
@section('content')
    <section class="pt-100 pb-100">
        <div class="container content-container">
            <div class="text-end">
                <button class="btn btn--base" type="button" data-bs-toggle="modal" data-bs-target="# masterConfigModal">
                    <i class="las la-plus"></i> @lang('Add Zerodha Configuration')
                </button>
            </div>
            <div class="row mt-3">
                <div class="col-lg-12">
                    <div class="custom--card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Zerodha Order Master</h5>
                            <p class="text-muted small">Auto-trade based on Supertrend + Donchian signals (Zerodha only)</p>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive--md table-responsive transparent-form">
                                <table class="table custom--table text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Created At</th>
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
                                                <td>{{ 'ZER00' . $master->id }}</td>
                                                <td>{{ $master->created_at->format('d M Y H:i') }}</td>
                                                <td>
                                                    <span class="badge badge--info">{{ $master->buildup_type }}</span>
                                                </td>
                                                <td>{{ $master->broker->client_name ?? 'N/A' }}</td>
                                                <td>
                                                    <span
                                                        class="badge {{ $master->order_type == 'LIMIT' ? 'badge--warning' : 'badge--success' }}">
                                                        {{ $master->order_type }}
                                                    </span>
                                                </td>
                                                <td>{{ $master->product }}</td>
                                                <td>{{ $master->order_type == 'LIMIT' ? $master->disc_ltp . '%' : '-' }}</td>
                                                <td>{{ $master->quantity }}</td>
                                                <td>{{ $master->pyramid_percent ?? '100' }}%</td>
                                                <td>{{ $master->pyramid_freq }}</td>
                                                <td>
                                                    <span class="badge badge--dark">
                                                        {{ $master->zerodhaPortfolio()->count() }} symbols
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($master->last_sync_at)
                                                        {{ $master->last_sync_at->diffForHumans() }}
                                                    @else
                                                        <span class="text-muted">Never</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($master->status == 1)
                                                        <span class="badge badge--success">Active</span>
                                                    @else
                                                        <span class="badge badge--danger">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="{{ route('user.zerodha-order-detail', ['id' => $master->id]) }}"
                                                            class="btn btn-sm btn-primary" target="_blank"
                                                            title="View Symbols">
                                                            <i class="las la-eye"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-danger delete-master"
                                                            data-id="{{ $master->id }}" title="Deactivate">
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
                                                        <h5 class="text-muted">No Zerodha Configurations Found</h5>
                                                        <p class="text-muted">Create a configuration to automatically trade
                                                            on signal matches</p>
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
    <div class="modal fade" id="masterConfigModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('user.zerodha-store') }}" class="transparent-form" method="post">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Zerodha Configuration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert--info">
                            <i class="las la-robot"></i>
                            <strong>Auto Trading</strong> - System will automatically place orders when both Supertrend AND
                            Donchian signals agree
                        </div>

                        <div class="row">
                            <div class="col-lg-6 form-group">
                                <label class="required">Buildup Type<sup class="text--danger">*</sup></label>
                                <select name="buildup_type" class="form--control" required>
                                    <option value="">Select Buildup Type</option>
                                    <option value="Strong Bullish">Strong Bullish</option>
                                    <option value="Mild Bullish">Mild Bullish</option>
                                    <option value="Mild Bearish">Mild Bearish</option>
                                    <option value="Strong Bearish">Strong Bearish</option>
                                </select>
                            </div>

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
                                <select name="order_type" class="form--control" required id="order_type">
                                    <option value="">Select Order Type</option>
                                    <option value="LIMIT" selected>LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Disc LTP %<sup class="text--danger">*</sup></label>
                                <input type="number" name="disc_ltp" max="100" min="0" step="0.01"
                                    placeholder="Discount from LTP" class="form--control" required id="disc_ltp">
                                <small class="text-muted">For LIMIT orders only</small>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Product<sup class="text--danger">*</sup></label>
                                <select name="product" class="form--control" required>
                                    <option value="">Select Product</option>
                                    <option value="NRML" selected>NRML (Normal)</option>
                                    <option value="MIS">MIS (Intraday)</option>
                                </select>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Base Quantity<sup class="text--danger">*</sup></label>
                                <input type="number" name="quantity" placeholder="Enter Quantity" class="form--control"
                                    min="1" required>
                                <small class="text-muted">Total quantity for futures</small>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label>Pyramid Percentage</label>
                                <select name="pyramid_percent" class="form--control">
                                    <option value="100" selected>100% (Single order)</option>
                                    <option value="50">50% (2 levels)</option>
                                    <option value="33">33% (3 levels)</option>
                                </select>
                                <small class="text-muted">Split orders across levels</small>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Pyramid Frequency<sup class="text--danger">*</sup></label>
                                <input type="number" name="pyramid_freq" placeholder="Minutes" class="form--control"
                                    min="0" value="0" required>
                                <small class="text-muted">Delay between pyramid levels (0 = immediate)</small>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Status<sup class="text--danger">*</sup></label>
                                <select name="status" class="form--control" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Order Date<sup class="text--danger">*</sup></label>
                                <input type="date" name="order_date" class="form--control" required>
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
                    <h5 class="modal-title">Confirm Deactivation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert--warning">
                        <i class="las la-exclamation-triangle"></i>
                        This will deactivate the configuration and stop auto-trading.
                    </div>
                    <p>Are you sure?</p>
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
                $('.delete-master').on('click', function() {
                    const masterId = $(this).data('id');
                    const deleteUrl = "{{ route('user.zerodha-destroy', ':id') }}".replace(':id', masterId);
                    $('#deleteForm').attr('action', deleteUrl);
                    $('#deleteModal').modal('show');
                });

                $('#order_type').on('change', function() {
                    const isLimit = $(this).val() === 'LIMIT';
                    $('#disc_ltp').prop('required', isLimit);
                    if (!isLimit) {
                        $('#disc_ltp').val('0');
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

            .badge--primary {
                background-color: #3b82f6;
                color: white;
            }

            .badge--info {
                background-color: #06b6d4;
                color: white;
            }

            .badge--warning {
                background-color: #f59e0b;
                color: white;
            }

            .badge--success {
                background-color: #10b981;
                color: white;
            }

            .badge--danger {
                background-color: #ef4444;
                color: white;
            }

            .badge--dark {
                background-color: #374151;
                color: white;
            }

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
        </style>
    @endpush
@endsection