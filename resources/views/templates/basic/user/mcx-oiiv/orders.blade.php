@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="text-end mb-3">
            <a href="{{ route('mcx-oiiv.config') }}" class="btn btn--base">
                <i class="las la-arrow-left"></i> Back to Configs
            </a>
        </div>

        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">🛢️ MCX OI+IV Auto Trading Orders — Config #{{ $config->id }}</h5>
                <p class="text-muted small">
                    Order Type: {{ $config->order_type }} |
                    Product: {{ $config->product }} |
                    Mode: {{ strtoupper($config->signal_mode) }} |
                    Series: {{ strtoupper($config->option_series) }}
                </p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive--md table-responsive">
                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Symbol</th>
                                <th>Unit</th>
                                <th>Signal</th>
                                <th>Rank</th>
                                <th>CE OI</th>
                                <th>PE OI</th>
                                <th>Option</th>
                                <th>Strike</th>
                                <th>Spot ₹</th>
                                <th>Entry ₹</th>
                                <th>Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td><strong>{{ $order->id }}</strong></td>
                                    <td>{{ $order->signal_detected_at?->format('Y-m-d H:i') }}</td>
                                    <td><strong style="color:#e65c00;">{{ $order->symbol }}</strong></td>
                                    <td>{{ $order->unit ?? '—' }}</td>
                                    <td>
                                        <span class="badge badge--{{ $order->btst_signal == 'BUY_CE' ? 'success' : 'danger' }}">
                                            {{ $order->btst_signal }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($order->strength_rank)
                                            <span class="badge badge--primary">Rank {{ $order->strength_rank }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td><small>{{ $order->ce_oi_signal ?? '—' }}</small></td>
                                    <td><small>{{ $order->pe_oi_signal ?? '—' }}</small></td>
                                    <td><small>{{ $order->option_symbol ?? '—' }}</small></td>
                                    <td>{{ $order->strike_price ? '₹' . $order->strike_price : '—' }}</td>
                                    <td>₹{{ $order->spot_price }}</td>
                                    <td>₹{{ $order->entry_price }}</td>
                                    <td><strong>{{ $order->quantity }}</strong></td>
                                    <td>
                                        @if ($order->is_order_placed)
                                            <span class="badge badge--success">Placed</span>
                                        @else
                                            <span class="badge badge--warning">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center py-5">
                                        <i class="fas fa-oil-can text-muted" style="font-size:3rem; color:#e65c00;"></i>
                                        <h5 class="text-muted mt-3">No MCX Orders Found</h5>
                                        <p class="text-muted">Orders appear when signals are detected by the auto-trading engine</p>
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
.badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
.badge--success { background: #10b981; color: white; }
.badge--danger  { background: #ef4444; color: white; }
.badge--warning { background: #f59e0b; color: white; }
.badge--primary { background: #e65c00; color: white; }
</style>
@endpush