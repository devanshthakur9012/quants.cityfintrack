@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="mb-4">
            <a href="{{ route('one-percent-auto.index') }}" class="btn btn-secondary">
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
                        1PCT{{ str_pad($config->id, 4, '0', STR_PAD_LEFT) }}
                    </div>
                    <div class="col-md-3">
                        <strong>Broker:</strong><br>
                        {{ $config->broker->client_name }}
                    </div>
                    <div class="col-md-2">
                        <strong>Move Threshold:</strong><br>
                        <span class="badge badge--warning">±{{ $config->move_threshold }}%</span>
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
                        <strong class="text-success">Profit %:</strong> 
                        <span class="badge badge--success">{{ $config->profit_percent }}%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">One-Percent Auto Orders (±X% Move + OI Confirmation)</h5>
                <p class="text-muted small">Orders placed when price moves ±X% AND OI signal is BULLISH</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive--md table-responsive">
                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>Signal Time</th>
                                <th>Future Symbol</th>
                                <th>Signal Type</th>
                                <th>Move %</th>
                                <th>Day Open</th>
                                <th>Signal Price</th>
                                <th>FUT OI</th>
                                <th>CE OI</th>
                                <th>PE OI</th>
                                <th>Option Symbol</th>
                                <th>Strike</th>
                                <th>Entry Price</th>
                                <th>Quantity</th>
                                <th>Order Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td>{{ $order->signal_detected_at->format('d M Y H:i') }}</td>
                                    <td><strong>{{ $order->trading_symbol }}</strong></td>
                                    <td>
                                        <span class="badge badge--{{ $order->signal_type == 'BUY_CE' ? 'success' : 'danger' }}">
                                            {{ $order->signal_type }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="{{ $order->change_pct > 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $order->change_pct > 0 ? '+' : '' }}{{ number_format($order->change_pct, 2) }}%
                                        </strong>
                                    </td>
                                    <td>₹{{ number_format($order->day_open_price, 2) }}</td>
                                    <td>₹{{ number_format($order->signal_price, 2) }}</td>
                                    <td>
                                        @if($order->fut_signal == 'BULLISH')
                                            <span class="badge badge--success">{{ $order->fut_signal }}</span>
                                        @elseif($order->fut_signal == 'BEARISH')
                                            <span class="badge badge--danger">{{ $order->fut_signal }}</span>
                                        @else
                                            <span class="badge badge--secondary">{{ $order->fut_signal }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order->ce_signal == 'BULLISH')
                                            <span class="badge badge--success">{{ $order->ce_signal }}</span>
                                        @elseif($order->ce_signal == 'BEARISH')
                                            <span class="badge badge--danger">{{ $order->ce_signal }}</span>
                                        @else
                                            <span class="badge badge--secondary">{{ $order->ce_signal }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order->pe_signal == 'BULLISH')
                                            <span class="badge badge--success">{{ $order->pe_signal }}</span>
                                        @elseif($order->pe_signal == 'BEARISH')
                                            <span class="badge badge--danger">{{ $order->pe_signal }}</span>
                                        @else
                                            <span class="badge badge--secondary">{{ $order->pe_signal }}</span>
                                        @endif
                                    </td>
                                    <td><strong>{{ $order->option_symbol }}</strong></td>
                                    <td>{{ number_format($order->strike_price, 2) }}</td>
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
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center py-5">
                                        <i class="las la-inbox text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted">No Orders Yet</h5>
                                        <p class="text-muted">Orders will appear when ±X% move is confirmed by OI analysis</p>
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
@endsection

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
.badge--secondary { background: #6b7280; color: white; }
</style>
@endpush