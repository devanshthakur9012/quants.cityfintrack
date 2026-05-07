@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="mb-4">
            <a href="{{ route('user.zerodha-auto.index') }}" class="btn btn-secondary">
                <i class="las la-arrow-left"></i> Back to Configurations
            </a>
        </div>

        <div class="custom--card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Configuration Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Config ID:</strong><br>
                        AUTO{{ str_pad($config->id, 4, '0', STR_PAD_LEFT) }}
                    </div>
                    <div class="col-md-3">
                        <strong>Broker:</strong><br>
                        {{ $config->broker->client_name }}
                    </div>
                    <div class="col-md-2">
                        <strong>Strategy:</strong><br>
                        <span class="badge badge--{{ $config->signal_strategy == 'BOTH' ? 'primary' : ($config->signal_strategy == 'SUPERTREND' ? 'success' : 'warning') }}">
                            {{ $config->signal_strategy }}
                        </span>
                    </div>
                    <div class="col-md-2">
                        <strong>Order Type:</strong><br>
                        <span class="badge badge--{{ $config->order_type == 'LIMIT' ? 'warning' : 'success' }}">
                            {{ $config->order_type }}
                        </span>
                    </div>
                    <div class="col-md-2">
                        <strong>Status:</strong><br>
                        <span class="badge badge--{{ $config->status ? 'success' : 'danger' }}">
                            {{ $config->status ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-3">
                        <strong>Index Qty:</strong> {{ $config->index_quantity }}
                    </div>
                    <div class="col-md-3">
                        <strong>Stock Qty:</strong> {{ $config->stock_quantity }}
                    </div>
                    <div class="col-md-3">
                        <strong>Pyramid:</strong> {{ $config->pyramid_percent }}%
                    </div>
                    <div class="col-md-3">
                        <strong>Disc LTP:</strong> {{ $config->disc_ltp }}%
                    </div>
                    <div class="col-md-2">
                        <strong class="text-success">Profit %:</strong> 
                        <span class="badge badge--success">{{ $config->profit_percent }}%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">Auto Trading Orders (5-Minute Signals)</h5>
                <p class="text-muted small">Orders placed when Supertrend + VWAP signals synchronized</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive--md table-responsive">
                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>Signal Time</th>
                                <th>Future Symbol</th>
                                <th>Signal Type</th>
                                <th>Option Symbol</th>
                                <th>Option Type</th>
                                <th>Strike</th>
                                <th>Entry Price</th>
                                <th>Quantity</th>
                                <th>Order Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td>{{ $order->signal_detected_at->format('d M Y H:i') }}</td>
                                    <td><strong>{{ $order->future_symbol }}</strong></td>
                                    <td>
                                        <span class="badge badge--{{ $order->signal_type == 'BUY' ? 'success' : 'danger' }}">
                                            {{ $order->signal_type }}
                                        </span>
                                    </td>
                                    <td>{{ $order->option_symbol ?? 'Pending' }}</td>
                                    <td>
                                        @if($order->option_type)
                                            <span class="badge badge--{{ $order->option_type == 'CE' ? 'success' : 'danger' }}">
                                                {{ $order->option_type }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $order->strike_price ? number_format($order->strike_price, 2) : '-' }}</td>
                                    <td>{{ $order->entry_price ? '₹' . number_format($order->entry_price, 2) : '-' }}</td>
                                    <td>{{ $order->quantity }}</td>
                                    <td>
                                        @if($order->is_order_placed)
                                            <span class="badge badge--success">Placed</span>
                                            <br><small>{{ $order->order_placed_at->diffForHumans() }}</small>
                                        @else
                                            <span class="badge badge--warning">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-details" 
                                                data-id="{{ $order->id }}"
                                                data-future="{{ $order->future_symbol }}"
                                                data-option="{{ $order->option_symbol }}"
                                                data-st="{{ $order->supertrend_signal }}"
                                                data-vwap="{{ $order->vwap_signal }}"
                                                data-atm="{{ $order->atm_price }}"
                                                data-strategy="{{ $order->signal_strategy }}"
                                                title="View Details">
                                            <i class="las la-info-circle"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="las la-inbox text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted">No Orders Yet</h5>
                                        <p class="text-muted">Orders will appear when signals synchronize on 5-minute data</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $orders->links() }}
        </div>
    </div>
</section>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Future Details</h6>
                        <p><strong>Symbol:</strong> <span id="detail-future"></span></p>
                        <p><strong>ATM Price:</strong> ₹<span id="detail-atm"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Option Details</h6>
                        <p><strong>Symbol:</strong> <span id="detail-option"></span></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <h6>Signal Information (5-Minute)</h6>
                        <p><strong>Strategy:</strong> <span id="detail-strategy"></span></p>
                        <p><strong>Supertrend:</strong> <span id="detail-st"></span></p>
                        <p><strong>VWAP:</strong> <span id="detail-vwap"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
$(document).ready(function() {
    $('.view-details').click(function() {
        $('#detail-future').text($(this).data('future'));
        $('#detail-option').text($(this).data('option') || 'N/A');
        $('#detail-st').text($(this).data('st'));
        $('#detail-vwap').text($(this).data('vwap'));
        $('#detail-atm').text($(this).data('atm'));
        $('#detail-strategy').text($(this).data('strategy'));
        $('#detailsModal').modal('show');
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
.badge--info { background: #06b6d4; color: white; }
.badge--primary { background: #3b82f6; color: white; }
</style>
@endpush
@endsection