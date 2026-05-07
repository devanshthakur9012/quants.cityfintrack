@extends($activeTemplate . 'layouts.master')
@section('content')
    <section class="pt-100 pb-100">
        <div class="container content-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Pyramid Order Details - PYR-{{ $pyramidOrder->id }}</h4>
                <a href="{{ route('user.pyramid-orders.index') }}" class="btn btn-secondary">
                    <i class="las la-arrow-left"></i> Back to List
                </a>
            </div>

            <!-- Order Summary -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="custom--card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <small class="text-muted">Contract</small>
                                    <h6>{{ $pyramidOrder->contract_name }}</h6>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <small class="text-muted">Transaction</small>
                                    <h6>
                                        <span
                                            class="badge {{ $pyramidOrder->transaction_type == 'BUY' ? 'badge--success' : 'badge--danger' }}">
                                            {{ $pyramidOrder->transaction_type }}
                                        </span>
                                    </h6>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <small class="text-muted">Broker</small>
                                    <h6>{{ $pyramidOrder->broker->client_name }}</h6>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <small class="text-muted">Manual LTP</small>
                                    <h6>₹{{ number_format($pyramidOrder->manual_ltp, 2) }}</h6>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <small class="text-muted">Status</small>
                                    <h6>
                                        @if ($pyramidOrder->status == 'completed')
                                            <span class="badge badge--success">Completed</span>
                                        @elseif($pyramidOrder->status == 'partial')
                                            <span class="badge badge--warning">Partial Success</span>
                                        @elseif($pyramidOrder->status == 'processing')
                                            <span class="badge badge--info">Processing</span>
                                        @elseif($pyramidOrder->status == 'failed')
                                            <span class="badge badge--danger">Failed</span>
                                        @else
                                            <span class="badge badge--secondary">Pending</span>
                                        @endif
                                    </h6>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-3">
                                    <small class="text-muted">Base Discount %</small>
                                    <h6>{{ $pyramidOrder->base_discount_pct }}%</h6>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Discount Increment %</small>
                                    <h6>{{ $pyramidOrder->discount_increment_pct }}%</h6>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Lots Per Order</small>
                                    <h6>{{ $pyramidOrder->lots_per_order }} × {{ $pyramidOrder->lot_size }}</h6>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Orders Placed</small>
                                    <h6>
                                        <span
                                            class="badge {{ $pyramidOrder->orders_placed == $pyramidOrder->num_pyramids ? 'badge--success' : 'badge--warning' }}">
                                            {{ $pyramidOrder->orders_placed }} / {{ $pyramidOrder->num_pyramids }}
                                        </span>
                                    </h6>
                                </div>
                            </div>

                            @if ($pyramidOrder->error_message)
                                <div class="alert alert--danger mt-3 mb-0">
                                    <strong>Error:</strong> {{ $pyramidOrder->error_message }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Individual Orders -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="custom--card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Pyramid Orders Breakdown</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table custom--table text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Pyramid #</th>
                                            <th>Effective Discount</th>
                                            <th>Order Price</th>
                                            <th>Quantity</th>
                                            <th>Total Value</th>
                                            <th>Angel Order ID</th>
                                            <th>Status</th>
                                            <th>Message</th>
                                            <th>Placed At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($pyramidOrder->details as $detail)
                                            <tr>
                                                <td><strong>{{ $detail->pyramid_index }}</strong></td>
                                                <td>{{ number_format($detail->effective_discount_pct, 2) }}%</td>
                                                <td>₹{{ number_format($detail->order_price, 2) }}</td>
                                                <td>{{ $detail->quantity }}</td>
                                                <td>₹{{ number_format($detail->order_price * $detail->quantity, 2) }}</td>
                                                <td>
                                                    @if ($detail->angel_order_id)
                                                        <code>{{ $detail->angel_order_id }}</code>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($detail->order_status == 'placed' || $detail->order_status == 'complete')
                                                        <span
                                                            class="badge badge--success">{{ ucfirst($detail->order_status) }}</span>
                                                    @elseif($detail->order_status == 'failed' || $detail->order_status == 'rejected')
                                                        <span
                                                            class="badge badge--danger">{{ ucfirst($detail->order_status) }}</span>
                                                    @else
                                                        <span
                                                            class="badge badge--secondary">{{ ucfirst($detail->order_status) }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($detail->status_message)
                                                        <small class="text-muted">{{ $detail->status_message }}</small>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($detail->placed_at)
                                                        {{ $detail->placed_at->format('d M Y H:i:s') }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="las la-info-circle text-muted" style="font-size: 2rem;"></i>
                                                    <p class="text-muted mt-2">No order details available</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if ($pyramidOrder->details->count() > 0)
                                        <tfoot>
                                            <tr class="table-active">
                                                <th colspan="4" class="text-end text-white">Total Value:</th>
                                                <th class="text-white">₹{{ number_format($pyramidOrder->details->sum(function ($d) {return $d->order_price * $d->quantity;}),2) }}
                                                </th>
                                                <th colspan="4"></th>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timestamps -->
            <div class="row mt-3">
                <div class="col-lg-12">
                    <div class="custom--card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <small class="text-muted">Created At</small>
                                    <p class="mb-0">{{ $pyramidOrder->created_at->format('d M Y H:i:s') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Updated At</small>
                                    <p class="mb-0">{{ $pyramidOrder->updated_at->format('d M Y H:i:s') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Executed At</small>
                                    <p class="mb-0">
                                        @if ($pyramidOrder->executed_at)
                                            {{ $pyramidOrder->executed_at->format('d M Y H:i:s') }}
                                        @else
                                            <span class="text-muted">Not executed yet</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('style')
        <style>
            .badge {
                font-size: 0.75rem;
                padding: 0.35rem 0.65rem;
            }

            .badge--success {
                background-color: #10b981;
                color: white;
            }

            .badge--danger {
                background-color: #ef4444;
                color: white;
            }

            .badge--warning {
                background-color: #f59e0b;
                color: white;
            }

            .badge--info {
                background-color: #06b6d4;
                color: white;
            }

            .badge--secondary {
                background-color: #6b7280;
                color: white;
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
                font-size: 0.85em;
            }
        </style>
    @endpush
@endsection