@extends($activeTemplate . 'layouts.master')
@section('content')
    <section class="pt-100 pb-100">
        <div class="container content-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Pyramid Orders</h4>
                <a href="{{ route('user.pyramid-orders.create') }}" class="btn btn--base">
                    <i class="las la-plus"></i> Create New Pyramid Order
                </a>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="custom--card">
                        <div class="card-body p-0">
                            <div class="table-responsive--md table-responsive">
                                <table class="table custom--table text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Created</th>
                                            <th>Contract</th>
                                            <th>Type</th>
                                            <th>Broker</th>
                                            <th>Manual LTP</th>
                                            <th>Base Disc %</th>
                                            <th>Pyramids</th>
                                            <th>Orders Placed</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($pyramidOrders as $order)
                                            <tr>
                                                <td><span class="badge badge--dark">PYR-{{ $order->id }}</span></td>
                                                <td>{{ $order->created_at->format('d M Y H:i') }}</td>
                                                <td>
                                                    <strong>{{ $order->symbol }}</strong><br>
                                                    <small class="text-muted">
                                                        {{ $order->expiry_date->format('d-M-y') }}
                                                        {{ $order->strike_price }}
                                                        {{ $order->option_type }}
                                                    </small>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge {{ $order->transaction_type == 'BUY' ? 'badge--success' : 'badge--danger' }}">
                                                        {{ $order->transaction_type }}
                                                    </span>
                                                </td>
                                                <td>{{ $order->broker->client_name ?? 'N/A' }}</td>
                                                <td>₹{{ number_format($order->manual_ltp, 2) }}</td>
                                                <td>{{ $order->base_discount_pct }}%</td>
                                                <td>{{ $order->num_pyramids }}</td>
                                                <td>
                                                    <span
                                                        class="badge {{ $order->orders_placed == $order->num_pyramids ? 'badge--success' : ($order->orders_placed > 0 ? 'badge--warning' : 'badge--danger') }}">
                                                        {{ $order->orders_placed }}/{{ $order->num_pyramids }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($order->status == 'completed')
                                                        <span class="badge badge--success">Completed</span>
                                                    @elseif($order->status == 'partial')
                                                        <span class="badge badge--warning">Partial</span>
                                                    @elseif($order->status == 'processing')
                                                        <span class="badge badge--info">Processing</span>
                                                    @elseif($order->status == 'failed')
                                                        <span class="badge badge--danger">Failed</span>
                                                    @else
                                                        <span class="badge badge--secondary">Pending</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('user.pyramid-orders.show', $order->id) }}"
                                                        class="btn btn-sm btn--base" title="View Details">
                                                        <i class="las la-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="11" class="text-center">
                                                    <div class="py-5">
                                                        <i class="las la-inbox text-muted" style="font-size: 3rem;"></i>
                                                        <h5 class="text-muted mt-3">No Pyramid Orders Found</h5>
                                                        <p class="text-muted">Create your first pyramid order to get started
                                                        </p>
                                                        <a href="{{ route('user.pyramid-orders.create') }}"
                                                            class="btn btn--base mt-2">
                                                            <i class="las la-plus"></i> Create Pyramid Order
                                                        </a>
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
            @if ($pyramidOrders->hasPages())
                <div class="mt-4 d-flex justify-content-center">
                    {{ $pyramidOrders->links() }}
                </div>
            @endif
        </div>
    </section>

    @push('style')
        <style>
            .badge {
                font-size: 0.75rem;
                padding: 0.35rem 0.65rem;
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

            .badge--secondary {
                background-color: #6b7280;
                color: white;
            }
        </style>
    @endpush
@endsection