@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="text-end mb-3">
            <a href="{{ route('oiiv-auto.config') }}" class="btn btn--base">
                <i class="las la-arrow-left"></i> Back to Configs
            </a>
        </div>

        <div class="custom--card">
            <div class="card-header">
                <h5 class="card-title mb-0">OI+IV Auto Trading Orders - Config #{{ $config->id }}</h5>
                <p class="text-muted small">
                    Min Confidence: {{ $config->min_confidence }}% | 
                    Order Type: {{ $config->order_type }} | 
                    Product: {{ $config->product }}
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
                                <th>Signal</th>
                                <th>Confidence</th>
                                <th>FUT OI</th>
                                <th>CE OI/IV</th>
                                <th>PE OI/IV</th>
                                <th>Option</th>
                                <th>Strike</th>
                                <th>Entry Price</th>
                                <th>Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td><strong>{{ $order->id }}</strong></td>
                                    <td>{{ $order->signal_detected_at->format('Y-m-d H:i') }}</td>
                                    <td><strong>{{ $order->symbol }}</strong></td>
                                    <td>
                                        <span class="badge badge--{{ $order->btst_signal == 'BUY_CE' ? 'success' : 'danger' }}">
                                            {{ $order->btst_signal }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge--primary">{{ $order->btst_confidence }}%</span>
                                    </td>
                                    <td>
                                        <small>{{ $order->fut_oi_signal }}</small>
                                    </td>
                                    <td>
                                        <small>
                                            OI: {{ $order->ce_oi_signal }}<br>
                                            IV: {{ $order->ce_iv_signal }}
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            OI: {{ $order->pe_oi_signal }}<br>
                                            IV: {{ $order->pe_iv_signal }}
                                        </small>
                                    </td>
                                    <td><small>{{ $order->option_symbol }}</small></td>
                                    <td>₹{{ $order->strike_price }}</td>
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
                                    <td colspan="13" class="text-center py-5">
                                        <i class="las la-inbox text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted">No Orders Found</h5>
                                        <p class="text-muted">Orders will appear here when signals are detected</p>
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
.badge--primary { background: #3b82f6; color: white; }
</style>
@endpush