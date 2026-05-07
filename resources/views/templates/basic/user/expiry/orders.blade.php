@extends($activeTemplate . 'layouts.master')

@section('content')
    <section class="pt-100 pb-100">
        <div class="container content-container">
            <div class="mb-4">
                <a href="{{ route('expiry.auto.index') }}" class="btn btn-secondary">
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
                            EXPIRY{{ str_pad($config->id, 4, '0', STR_PAD_LEFT) }}
                        </div>
                        <div class="col-md-3">
                            <strong>Broker:</strong><br>
                            {{ $config->broker->client_name }}
                        </div>
                        <div class="col-md-2">
                            <strong>Strategy:</strong><br>
                            <span class="badge badge--success">
                                SUPERTREND (1-Min)
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
                        <div class="col-md-2">
                            <strong>NIFTY Qty:</strong> {{ $config->nifty_quantity }} lots
                        </div>
                        <div class="col-md-2">
                            <strong>BANKNIFTY Qty:</strong> {{ $config->banknifty_quantity }} lots
                        </div>
                        <div class="col-md-2">
                            <strong>SENSEX Qty:</strong> {{ $config->sensex_quantity }} lots
                        </div>
                        <div class="col-md-2">
                            <strong>Pyramid:</strong> {{ $config->pyramid_percent }}%
                        </div>
                        <div class="col-md-2">
                            <strong>Pyramid Freq:</strong> {{ $config->pyramid_freq }} min
                        </div>
                        <div class="col-md-2">
                            <strong>Disc LTP:</strong> {{ $config->disc_ltp }}%
                        </div>
                    </div>
                </div>
            </div>

            <div class="custom--card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="las la-clock"></i> Expiry Auto Trading Orders (1-Minute Signals)
                    </h5>
                    <p class="text-muted small mb-0">
                        <i class="las la-info-circle"></i> Orders placed ONLY on expiry days when Supertrend signals change
                    </p>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table custom--table">
                            <thead>
                                <tr>
                                    <th>Signal Time</th>
                                    <th>Symbol</th>
                                    <th>Signal Type</th>
                                    <th>Option Symbol</th>
                                    <th>Option Type</th>
                                    <th>Strike</th>
                                    <th>Index Price</th>
                                    <th>Entry Price</th>
                                    <th>Quantity</th>
                                    <th>Pyramids</th>
                                    <th>Order Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($orders as $order)
                                    <tr>
                                        <td>
                                            <strong>{{ $order->signal_detected_at->format('d M Y') }}</strong><br>
                                            <small class="text-muted">{{ $order->signal_detected_at->format('H:i:s') }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $order->symbol }}</strong>
                                            <br>
                                            <span class="badge badge--info">Expiry Day</span>
                                        </td>
                                        <td>
                                            <span class="badge badge--{{ $order->signal_type == 'BUY' ? 'success' : 'danger' }}">
                                                <i class="las la-{{ $order->signal_type == 'BUY' ? 'arrow-up' : 'arrow-down' }}"></i>
                                                {{ $order->signal_type }}
                                            </span>
                                        </td>
                                        <td>
                                            <small>{{ $order->option_symbol ?? 'Pending' }}</small>
                                        </td>
                                        <td>
                                            @if ($order->option_type)
                                                <span class="badge badge--{{ $order->option_type == 'CE' ? 'success' : 'danger' }}">
                                                    {{ $order->option_type }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            {{ $order->strike_price ? number_format($order->strike_price, 2) : '-' }}
                                        </td>
                                        <td>
                                            <small>₹{{ $order->index_price ? number_format($order->index_price, 2) : '-' }}</small>
                                        </td>
                                        <td>
                                            <strong>₹{{ $order->entry_price ? number_format($order->entry_price, 2) : '-' }}</strong>
                                        </td>
                                        <td>
                                            <strong>{{ $order->quantity }}</strong> lots
                                        </td>
                                        <td>
                                            <small>
                                                @if($order->pyramid_1)
                                                    <span class="badge badge--primary">{{ $order->pyramid_1 }}</span>
                                                @endif
                                                @if($order->pyramid_2)
                                                    <span class="badge badge--primary">{{ $order->pyramid_2 }}</span>
                                                @endif
                                                @if($order->pyramid_3)
                                                    <span class="badge badge--primary">{{ $order->pyramid_3 }}</span>
                                                @endif
                                            </small>
                                        </td>
                                        <td>
                                            @if ($order->is_order_placed)
                                                <span class="badge badge--success">
                                                    <i class="las la-check-circle"></i> Placed
                                                </span>
                                                <br>
                                                <small class="text-muted">{{ $order->order_placed_at->format('H:i:s') }}</small>
                                            @else
                                                <span class="badge badge--warning">
                                                    <i class="las la-clock"></i> Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-details" 
                                                data-id="{{ $order->id }}"
                                                data-symbol="{{ $order->symbol }}"
                                                data-option="{{ $order->option_symbol }}"
                                                data-st="{{ $order->supertrend_signal }}"
                                                data-index-price="{{ $order->index_price }}"
                                                data-entry-price="{{ $order->entry_price }}"
                                                data-strike="{{ $order->strike_price }}"
                                                data-option-type="{{ $order->option_type }}"
                                                data-signal-time="{{ $order->signal_detected_at->format('d M Y H:i:s') }}"
                                                data-pyramid1="{{ $order->pyramid_1 }}"
                                                data-pyramid2="{{ $order->pyramid_2 }}"
                                                data-pyramid3="{{ $order->pyramid_3 }}"
                                                data-order-type="{{ $order->order_type }}"
                                                data-product="{{ $order->product }}"
                                                title="View Details">
                                                <i class="las la-info-circle"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center py-5">
                                            <i class="las la-calendar-times text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="text-muted">No Expiry Orders Yet</h5>
                                            <p class="text-muted">
                                                Orders will appear ONLY on expiry days when Supertrend signals change<br>
                                                <small>Next expiry: Check <a href="{{ route('expiry.analysis') }}">Expiry Analysis</a> page</small>
                                            </p>
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
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="las la-file-invoice"></i> Expiry Order Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Signal Information -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="las la-chart-line"></i> Signal Information (1-Minute Expiry)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Signal Time:</strong> <span id="detail-signal-time"></span></p>
                                    <p><strong>Symbol:</strong> <span id="detail-symbol" class="badge badge--info"></span></p>
                                    <p><strong>Supertrend Signal:</strong> <span id="detail-st"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Index Price at Signal:</strong> ₹<span id="detail-index-price"></span></p>
                                    <p><strong>Strike Price:</strong> <span id="detail-strike"></span></p>
                                    <p><strong>Option Type:</strong> <span id="detail-option-type"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Option Details -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="las la-tag"></i> Option Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Option Symbol:</strong><br><code id="detail-option"></code></p>
                                    <p><strong>Entry Price (LTP):</strong> ₹<span id="detail-entry-price"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Order Type:</strong> <span id="detail-order-type"></span></p>
                                    <p><strong>Product:</strong> <span id="detail-product"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pyramid Details -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="las la-layer-group"></i> Pyramid Execution
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <p id="pyramid-info"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('script')
        <script>
            $(document).ready(function() {
                $('.view-details').click(function() {
                    // Signal Info
                    $('#detail-signal-time').text($(this).data('signal-time'));
                    $('#detail-symbol').text($(this).data('symbol'));
                    $('#detail-st').html('<span class="badge badge--success">' + $(this).data('st') + '</span>');
                    
                    // Prices
                    $('#detail-index-price').text(parseFloat($(this).data('index-price')).toFixed(2));
                    $('#detail-strike').text(parseFloat($(this).data('strike')).toFixed(2));
                    $('#detail-entry-price').text(parseFloat($(this).data('entry-price')).toFixed(2));
                    
                    // Option Details
                    $('#detail-option').text($(this).data('option') || 'N/A');
                    $('#detail-option-type').html('<span class="badge badge--' + 
                        ($(this).data('option-type') == 'CE' ? 'success' : 'danger') + '">' + 
                        $(this).data('option-type') + '</span>');
                    
                    // Order Settings
                    $('#detail-order-type').html('<span class="badge badge--primary">' + 
                        $(this).data('order-type') + '</span>');
                    $('#detail-product').html('<span class="badge badge--info">' + 
                        $(this).data('product') + '</span>');
                    
                    // Pyramid Info
                    let pyramidHtml = '';
                    let p1 = $(this).data('pyramid1');
                    let p2 = $(this).data('pyramid2');
                    let p3 = $(this).data('pyramid3');
                    
                    if (p1) {
                        pyramidHtml += '<strong>Pyramid 1:</strong> ' + p1 + ' lots (Immediate)<br>';
                    }
                    if (p2) {
                        pyramidHtml += '<strong>Pyramid 2:</strong> ' + p2 + ' lots (After delay)<br>';
                    }
                    if (p3) {
                        pyramidHtml += '<strong>Pyramid 3:</strong> ' + p3 + ' lots (After delay)<br>';
                    }
                    
                    if (!pyramidHtml) {
                        pyramidHtml = '<em class="text-muted">No pyramid splitting</em>';
                    }
                    
                    $('#pyramid-info').html(pyramidHtml);
                    
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

            .badge--info {
                background: #06b6d4;
                color: white;
            }

            .badge--primary {
                background: #3b82f6;
                color: white;
            }

            .card-header.bg-light {
                background-color: #f8f9fa !important;
            }

            code {
                background: #f1f5f9;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 0.875rem;
            }

            .custom--table tbody tr:hover {
                background-color: #f8fafc;
            }

            .modal-header.bg-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
        </style>
    @endpush
@endsection