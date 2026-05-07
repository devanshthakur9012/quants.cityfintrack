@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .page-header {
        background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);
        color:white; padding:20px 24px; border-radius:14px; margin-bottom:20px;
        border:1px solid rgba(243,156,18,.3); box-shadow:0 6px 24px rgba(0,0,0,.4);
    }
    .page-header h4 { margin:0 0 4px; font-size:17px; font-weight:800; }
    .page-header p  { margin:0; font-size:11px; color:rgba(255,255,255,.6); }

    .stats-box {
        background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
        padding:12px 10px; border-radius:10px; text-align:center;
        border-left:4px solid #f39c12; margin-bottom:14px;
    }
    .stats-box small  { display:block; color:rgba(255,255,255,.5); font-size:10px; text-transform:uppercase; }
    .stats-box strong { display:block; font-size:1.2rem; font-weight:800; margin-top:3px; color:white; }

    .custom--table thead th, .custom--table tbody td { vertical-align:middle; font-size:11px; padding:8px 8px !important; }

    .signal-ce { background:linear-gradient(135deg,#27ae60,#1abc9c); color:white; padding:2px 8px; border-radius:20px; font-weight:800; font-size:9px; }
    .signal-pe { background:linear-gradient(135deg,#e74c3c,#c0392b); color:white; padding:2px 8px; border-radius:20px; font-weight:800; font-size:9px; }

    .status-placed   { background:#d4edda; color:#155724; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; }
    .status-pending  { background:#fff3cd; color:#856404; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; }
    .status-failed   { background:#f8d7da; color:#721c24; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; }

    .sl-placed { background:linear-gradient(135deg,#e74c3c,#c0392b); color:white; padding:2px 7px; border-radius:10px; font-size:9px; font-weight:700; }
    .sl-none   { color:rgba(255,255,255,.3); font-size:10px; }

    .btn-amber { background:linear-gradient(135deg,#f39c12,#e67e22); color:white; border:none; font-weight:700; border-radius:8px; }
    label { color:rgba(255,255,255,.8) !important; }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- Header --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                <div>
                    <h4>📋 {{ $pageTitle }}</h4>
                    <p>
                        Config #{{ $config->id }} &nbsp;|&nbsp;
                        Broker: {{ $config->broker->client_name ?? 'N/A' }} &nbsp;|&nbsp;
                        Threshold: ±{{ $config->threshold }} pts &nbsp;|&nbsp;
                        Filter: {{ $config->filter }} &nbsp;|&nbsp;
                        Mode: {{ strtoupper($config->signal_mode) }} &nbsp;|&nbsp;
                        QtyMode: {{ strtoupper($config->quantity_mode) }}
                    </p>
                </div>
                <div>
                    <a href="{{ route('nifty-driven-breakout.config') }}" class="btn btn-sm btn-amber">
                        <i class="fas fa-arrow-left"></i> Back to Configs
                    </a>
                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="row mb-3">
            <div class="col-6 col-md-2">
                <div class="stats-box">
                    <small>Total</small>
                    <strong style="color:#f39c12;">{{ $orders->total() }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box" style="border-left-color:#27ae60;">
                    <small>Placed</small>
                    <strong style="color:#27ae60;">{{ $orders->where('is_order_placed', true)->count() }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box" style="border-left-color:#ffc107;">
                    <small>Pending</small>
                    <strong style="color:#ffc107;">{{ $orders->where('is_order_placed', false)->where('status', true)->count() }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box" style="border-left-color:#3498db;">
                    <small>CE Orders</small>
                    <strong style="color:#3498db;">{{ $orders->where('signal_type', 'CE')->count() }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box" style="border-left-color:#e74c3c;">
                    <small>PE Orders</small>
                    <strong style="color:#e74c3c;">{{ $orders->where('signal_type', 'PE')->count() }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stats-box" style="border-left-color:#a855f7;">
                    <small>SL Placed</small>
                    <strong style="color:#a855f7;">{{ $orders->where('stoploss_placed', true)->count() }}</strong>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table custom--table" style="min-width:1400px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>Signal</th>
                        <th>Option Type</th>
                        <th>Option Symbol</th>
                        <th>Strike</th>
                        <th>Expiry</th>
                        <th>NIFTY Open</th>
                        <th>NIFTY Trigger</th>
                        <th>Trigger Time</th>
                        <th>Move</th>
                        <th>Entry ₹</th>
                        <th>Qty (lots)</th>
                        <th>Investment</th>
                        <th>Order Type</th>
                        <th>Kite ID</th>
                        <th>Status</th>
                        <th>SL Price</th>
                        <th>SL Status</th>
                        <th>Detected At</th>
                        <th>Placed At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $i => $ord)
                    <tr>
                        <td><strong>{{ $orders->firstItem() + $i }}</strong></td>
                        <td><strong>{{ $ord->signal_date }}</strong></td>
                        <td><strong style="color:#f39c12;">{{ $ord->symbol }}</strong></td>
                        <td>
                            @if($ord->signal_type === 'CE')
                                <span class="signal-ce">📈 CE</span>
                            @else
                                <span class="signal-pe">📉 PE</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-{{ $ord->option_type === 'CE' ? 'success' : 'danger' }}">{{ $ord->option_type }}</span>
                        </td>
                        <td style="font-size:10px; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $ord->option_symbol }}">
                            <strong>{{ $ord->option_symbol }}</strong>
                        </td>
                        <td><strong>{{ number_format($ord->strike_price) }}</strong></td>
                        <td style="font-size:10px;">{{ $ord->expiry_date }}</td>
                        <td>₹{{ number_format($ord->nifty_open, 2) }}</td>
                        <td>
                            <strong style="color:{{ $ord->signal_type === 'CE' ? '#27ae60' : '#e74c3c' }};">
                                ₹{{ number_format($ord->nifty_trigger, 2) }}
                            </strong>
                        </td>
                        <td><strong>{{ $ord->trigger_time }}</strong></td>
                        <td>
                            @php $mv = $ord->nifty_move; @endphp
                            <span style="color:{{ $mv >= 0 ? '#27ae60' : '#e74c3c' }}; font-weight:700;">
                                {{ $mv >= 0 ? '+' : '' }}{{ number_format($mv, 2) }}
                            </span>
                        </td>
                        <td><strong style="color:#3498db;">₹{{ number_format($ord->entry_price, 2) }}</strong></td>
                        <td><strong>{{ $ord->quantity }}</strong></td>
                        <td><strong>₹{{ number_format($ord->investment, 0) }}</strong></td>
                        <td>
                            <span class="badge badge-{{ $ord->order_type === 'MARKET' ? 'success' : 'secondary' }}">{{ $ord->order_type }}</span>
                        </td>
                        <td style="font-size:10px;">{{ $ord->kite_order_id ?? '—' }}</td>
                        <td>
                            @if($ord->is_order_placed)
                                <span class="status-placed">✅ Placed</span>
                            @elseif($ord->error_message)
                                <span class="status-failed" title="{{ $ord->error_message }}">❌ Failed</span>
                            @else
                                <span class="status-pending">⏳ Pending</span>
                            @endif
                        </td>
                        <td>
                            @if($ord->stoploss_enabled && $ord->stoploss_price)
                                <strong style="color:#e74c3c;">₹{{ number_format($ord->stoploss_price, 2) }}</strong>
                            @else
                                <span class="sl-none">None</span>
                            @endif
                        </td>
                        <td>
                            @if($ord->stoploss_enabled)
                                @if($ord->stoploss_placed)
                                    <span class="sl-placed">🛡 Placed</span>
                                    @if($ord->stoploss_order_id)
                                        <br><small style="color:rgba(255,255,255,.4); font-size:9px;">{{ $ord->stoploss_order_id }}</small>
                                    @endif
                                @else
                                    <span class="badge badge-warning" style="font-size:9px;">⏳ Pending</span>
                                @endif
                            @else
                                <span class="sl-none">—</span>
                            @endif
                        </td>
                        <td style="font-size:10px;">{{ $ord->signal_detected_at ? $ord->signal_detected_at->format('d-M H:i') : '—' }}</td>
                        <td style="font-size:10px;">{{ $ord->order_placed_at ? $ord->order_placed_at->format('d-M H:i') : '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="22" class="text-center py-5">
                            <i class="fas fa-inbox" style="font-size:3rem; opacity:.3;"></i>
                            <p style="color:rgba(255,255,255,.4); margin-top:16px;">No orders yet for this config.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
        <div class="mt-3">{{ $orders->links() }}</div>
        @endif

    </div>
</section>
@endsection