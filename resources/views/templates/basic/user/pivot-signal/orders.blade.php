@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
.pv-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white; padding: 20px 26px; border-radius: 14px;
    margin-bottom: 22px; box-shadow: 0 6px 20px rgba(17,153,142,.4);
}
.pv-header h4 { color:#fff; margin:0; font-size:1.2rem; font-weight:700; }
.pv-header p  { color:rgba(255,255,255,.85); margin:4px 0 0; font-size:12px; }

.pv-stat {
    background:#fff; padding:12px 14px; border-radius:12px; text-align:center;
    border-left:5px solid #11998e; box-shadow:0 3px 10px rgba(0,0,0,.07); margin-bottom:18px;
}
.pv-stat small  { display:block; color:#888; font-size:10px; text-transform:uppercase; letter-spacing:.5px; }
.pv-stat strong { display:block; font-size:1.4rem; font-weight:700; margin-top:3px; }

.cfg-info {
    background:#fff; border-radius:12px; padding:16px 20px;
    box-shadow:0 3px 12px rgba(0,0,0,.07); margin-bottom:20px;
    border-left:5px solid #11998e;
}
.cfg-info .lbl { font-size:10px; color:#888; text-transform:uppercase; letter-spacing:.5px; margin-bottom:2px; }
.cfg-info .val { font-size:13px; font-weight:700; color:#222; }

/* Layer section headers inside config summary */
.layer-section-hdr {
    font-size:10px; font-weight:800; text-transform:uppercase;
    letter-spacing:.5px; padding:3px 9px; border-radius:5px;
    display:inline-block; margin-bottom:6px;
}
.lbl-s1-ce { background:#d4edda; color:#155724; }
.lbl-s1-pe { background:#c3f7d8; color:#0f5132; }
.lbl-r1-ce { background:#f8d7da; color:#721c24; }
.lbl-r1-pe { background:#fce4e4; color:#7b1d1d; }

.layer-pill {
    display:inline-block; font-size:9px; font-weight:700;
    padding:2px 7px; border-radius:4px; margin:1px;
    background:#e9ecef; color:#495057;
}

.bbuy { background:linear-gradient(135deg,#28a745,#20c997); color:#fff; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:800; display:inline-block; }
.bpe  { background:#f8d7da; color:#721c24; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; display:inline-block; }
.bce  { background:#d4edda; color:#155724; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; display:inline-block; }

.st-placed  { background:#d4edda; color:#155724; padding:2px 8px; border-radius:8px; font-size:10px; font-weight:700; display:inline-block; }
.st-dryrun  { background:#e2e3f3; color:#383d8c; padding:2px 8px; border-radius:8px; font-size:10px; font-weight:700; display:inline-block; }
.st-pending { background:#fff3cd; color:#856404; padding:2px 8px; border-radius:8px; font-size:10px; font-weight:700; display:inline-block; }
.st-failed  { background:#f8d7da; color:#721c24; padding:2px 8px; border-radius:8px; font-size:10px; font-weight:700; display:inline-block; }

.layer-badge {
    display:inline-flex; align-items:center; justify-content:center;
    width:22px; height:22px; border-radius:50%; font-size:10px; font-weight:800;
    background:#e9ecef; color:#495057;
}
.layer-badge.s1 { background:#d4edda; color:#155724; }
.layer-badge.r1 { background:#f8d7da; color:#721c24; }

.custom--table thead th,
.custom--table tbody td { vertical-align:middle; font-size:11px; padding:9px 10px !important; }
.mono { font-family:monospace; font-size:10px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="pv-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
            <div>
                <h4>&#128203; Pivot Orders &mdash; Config #{{ $config->id }}</h4>
                <p>
                    {{ $config->broker->client_name ?? 'N/A' }} &nbsp;&middot;&nbsp;
                    {{ $config->order_type }} / {{ $config->product }} &nbsp;&middot;&nbsp;
                    Runs for <strong>all symbols</strong> with 30-min data
                </p>
            </div>
            <a href="{{ route('pivot-signal.config.index') }}" class="btn btn-light btn-sm">&#8592; Back to Configs</a>
        </div>
    </div>

    {{-- Config Summary — 4 separate layer sections --}}
    <div class="cfg-info mb-4">
        <div class="row g-3">
            {{-- Basic info --}}
            <div class="col-6 col-md-2">
                <div class="lbl">Broker</div>
                <div class="val" style="font-size:12px;">{{ $config->broker->client_name ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="lbl">Order / Product</div>
                <div class="val">{{ $config->order_type }} / {{ $config->product }}</div>
            </div>
            <div class="col-6 col-md-1">
                <div class="lbl">Status</div>
                <div class="val">
                    @if($config->status)
                        <span style="color:#28a745;">&#9989; Active</span>
                    @else
                        <span style="color:#dc3545;">&#10060; Off</span>
                    @endif
                </div>
            </div>

            {{-- 4 layer sections --}}
            @foreach([
                ['s1_ce_layers', 'S1 CE (BUY CE @ S1)', 'lbl-s1-ce'],
                ['s1_pe_layers', 'S1 PE (BUY PE @ S1)', 'lbl-s1-pe'],
                ['r1_ce_layers', 'R1 CE (BUY CE @ R1)', 'lbl-r1-ce'],
                ['r1_pe_layers', 'R1 PE (BUY PE @ R1)', 'lbl-r1-pe'],
            ] as [$field, $label, $cls])
            <div class="col-6 col-md-3">
                <span class="layer-section-hdr {{ $cls }}">{{ $label }}</span>
                <div>
                    @if(!empty($config->$field))
                        @foreach($config->$field as $li => $l)
                            <span class="layer-pill">
                                L{{ $li+1 }}: {{ $l['quantity'] ?? 0 }} qty
                                &nbsp;{{ ($l['discount_direction'] ?? '') === 'positive' ? '+' : '-' }}{{ $l['discount_pct'] ?? 0 }}%
                            </span>
                        @endforeach
                    @else
                        <span style="color:#aaa;font-size:10px;">No layers</span>
                    @endif
                </div>
            </div>
            @endforeach

        </div>
    </div>

    {{-- Stats --}}
    <div class="row mb-3">
        <div class="col-6 col-md-3">
            <div class="pv-stat">
                <small>Total Orders</small>
                <strong class="text-dark">{{ $orders->total() }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="pv-stat" style="border-left-color:#28a745;">
                <small>Placed</small>
                <strong style="color:#28a745;">{{ $orders->where('is_order_placed', true)->count() }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="pv-stat" style="border-left-color:#ffc107;">
                <small>Pending / Dry-Run</small>
                <strong style="color:#ffc107;">
                    {{ $orders->where('is_order_placed', false)->whereNull('error_message')->count() }}
                </strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="pv-stat" style="border-left-color:#dc3545;">
                <small>Failed</small>
                <strong style="color:#dc3545;">{{ $orders->whereNotNull('error_message')->count() }}</strong>
            </div>
        </div>
    </div>

    {{-- Orders Table --}}
    <div style="border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);overflow:hidden;">
        <div class="table-responsive">
            <table class="table custom--table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date &amp; Time</th>
                        <th>Candle Slot</th>
                        <th>Symbol</th>
                        <th>Option Symbol</th>
                        <th>CE / PE</th>
                        <th>Level<br><small style="font-weight:400;color:#888;">S1 / R1</small></th>
                        <th>Layer</th>
                        <th>Tx</th>
                        <th>Raw Level<br><small style="font-weight:400;color:#888;">S1 or R1</small></th>
                        <th>Order Price<br><small style="font-weight:400;color:#888;">after discount</small></th>
                        <th>Qty</th>
                        <th>Kite ID</th>
                        <th>Status</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($orders as $i => $order)
                    <tr>
                        <td><strong>{{ $orders->firstItem() + $i }}</strong></td>

                        {{-- Date & Time --}}
                        <td>
                            <small style="color:#999;">
                                {{ \Carbon\Carbon::parse($order->created_at)->format('d M Y') }}
                            </small><br>
                            <strong style="color:#11998e;font-size:12px;">
                                {{ \Carbon\Carbon::parse($order->created_at)->format('H:i:s') }}
                            </strong>
                        </td>

                        {{-- Candle slot (e.g. "2026-03-03 09:15:00" or "09:15:00" → "09:15") --}}
                        <td>
                            <span class="mono" style="color:#555;font-size:11px;">
                                @if($order->candle_time)
                                    @php
                                        $ct = trim($order->candle_time);
                                        // If it contains a space it's "2026-03-03 09:15:00" → take part after space
                                        // Otherwise it's already "09:15:00" or "09:15"
                                        $timePart = str_contains($ct, ' ') ? explode(' ', $ct)[1] : $ct;
                                        echo substr($timePart, 0, 5); // "09:15"
                                    @endphp
                                @else
                                    —
                                @endif
                            </span>
                        </td>

                        <td><strong>{{ $order->symbol }}</strong></td>

                        {{-- Option symbol + strike --}}
                        <td>
                            <span class="mono">{{ $order->option_symbol ?? '—' }}</span>
                            @if($order->strike_price)
                                <br><small class="text-muted">&#8377;{{ number_format($order->strike_price, 0) }}</small>
                            @endif
                        </td>

                        {{-- CE / PE badge --}}
                        <td>
                            @if($order->option_type === 'CE')
                                <span class="bce">CE</span>
                            @else
                                <span class="bpe">PE</span>
                            @endif
                        </td>

                        {{-- Trigger level S1/R1 --}}
                        <td>
                            <span class="badge bg-{{ $order->trigger_level === 'S1' ? 'success' : 'danger' }}">
                                {{ $order->trigger_level }}
                            </span>
                        </td>

                        {{-- Layer index --}}
                        <td>
                            <span class="layer-badge {{ strtolower($order->trigger_level ?? 's1') }}">
                                {{ $order->layer_index ?? 1 }}
                            </span>
                        </td>

                        {{-- Transaction type (always BUY for pivot orders) --}}
                        <td><span class="bbuy">{{ $order->transaction_type }}</span></td>

                        {{-- Raw S1 / R1 --}}
                        <td>
                            <span style="color:#888;font-size:11px;">
                                &#8377;{{ number_format($order->raw_level_price, 2) }}
                            </span>
                        </td>

                        {{-- Final order price --}}
                        <td><strong>&#8377;{{ number_format($order->order_price, 2) }}</strong></td>

                        <td><strong>{{ $order->quantity }}</strong></td>

                        {{-- Kite order ID --}}
                        <td>
                            @if($order->kite_order_id)
                                <span class="mono">{{ $order->kite_order_id }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td>
                            @if($order->kite_status === 'DRY_RUN')
                                <span class="st-dryrun">&#128203; Dry Run</span>
                            @elseif($order->is_order_placed)
                                <span class="st-placed">&#9989; Placed</span>
                                @if($order->kite_status)
                                    <br><small class="text-muted">{{ $order->kite_status }}</small>
                                @endif
                            @elseif($order->error_message)
                                <span class="st-failed">&#10060; Failed</span>
                            @else
                                <span class="st-pending">&#9203; Pending</span>
                            @endif
                        </td>

                        {{-- Error message --}}
                        <td>
                            @if($order->error_message)
                                <span class="text-danger" style="font-size:10px;"
                                    title="{{ $order->error_message }}">
                                    {{ \Illuminate\Support\Str::limit($order->error_message, 45) }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox" style="font-size:2.5rem;opacity:.25;"></i>
                            <p class="mt-3 mb-0">No orders placed yet for this config.</p>
                            <small>
                                Cron runs at :48 and :18 each hour (Mon&ndash;Fri, 09:48&ndash;15:48 IST).
                                Use <code>php artisan pivot:place-orders --dry-run</code> to test.
                            </small>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="p-3">{{ $orders->links() }}</div>
        @endif
    </div>

</div>
</section>
@endsection