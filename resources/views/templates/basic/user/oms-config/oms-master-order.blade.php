@extends($activeTemplate.'layouts.master')
@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="row mt-3">
            <div class="col-lg-12">
                <div class="custom--card">
                    <div class="card-header d-flex gap-2 align-items-center">
                        <h5 class="card-title mb-0">OMS Config Orders : {{ "OMS00".$masterConfig->id }}</h5>
                        <span class="badge badge--primary">{{ portfolioName($masterConfig->portfolio_type) }}</span>
                        <span class="badge badge--info">{{ $masterConfig->buildup_type }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive--md table-responsive transparent-form">
                            <table class="table custom--table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Symbol Name</th>
                                        <th>TXN Type</th>
                                        <th>Order Type</th>
                                        <th>Product</th>
                                        <th>Disc LTP %</th>
                                        <th>Quantity</th>
                                        <th>Pyramid1</th>
                                        <th>Pyramid2</th>
                                        <th>Pyramid3</th>
                                        <th>Pyramid %</th>
                                        <th>Pyramid Freq</th>
                                        <!-- <th>Exit 1 Qty</th>
                                        <th>Exit 1 Target</th>
                                        <th>Exit 2 Qty</th>
                                        <th>Exit 2 Target</th> -->
                                        <th>Client Name</th>
                                        <th>Status</th>
                                        <!-- <th>Action</th> -->
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($configOrders as $order)
                                        <tr>
                                            <td>{{ $order->created_at->format('d M Y H:i') }}</td>
                                            <td>{{ $order->symbol_name }}</td>
                                            <td>{{ $order->txn_type }}</td>
                                            <td>
                                                <span class="badge {{ $order->order_type == 'LIMIT' ? 'badge--warning' : 'badge--success' }}">
                                                    {{ $order->order_type }}
                                                </span>
                                            </td>
                                            <td>{{ $order->product }}</td>
                                            <td>{{ $order->disc_ltp }}</td>
                                            <td>{{ $order->quantity }}</td>
                                            <td>{{ $order->pyramid_1 }}</td>
                                            <td>{{ $order->pyramid_2 }}</td>
                                            <td>{{ $order->pyramid_3 }}</td>
                                            <td>{{ $order->pyramid_percent }}</td>
                                            <td>{{ $order->pyramid_freq }}</td>
                                            <td>{{ $order->broker->client_name }}</td>
                                            <!-- <td>{{ $order->exit_1_qty ?? '-' }}</td>
                                            <td>{{ $order->exit_1_target ?? '-' }}</td>
                                            <td>{{ $order->exit_2_qty ?? '-' }}</td>
                                            <td>{{ $order->exit_2_target ?? '-' }}</td> -->
                                            <td>
                                                @if($order->status == 1)
                                                    <span class="badge badge--success">Active</span>
                                                @else
                                                    <span class="badge badge--danger">Inactive</span>
                                                @endif
                                            </td>
                                            <!-- <td>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-secondary edit-order" 
                                                            data-id="{{ $order->id }}" title="Edit">
                                                        <i class="las la-pencil-alt"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger delete-order" 
                                                            data-id="{{ $order->id }}" title="Delete">
                                                        <i class="las la-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td> -->
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="17" class="text-center">
                                                <div class="py-4">
                                                    <i class="las la-inbox text-muted" style="font-size: 3rem;"></i>
                                                    <h5 class="text-muted">No Orders Found</h5>
                                                    <p class="text-muted">Add orders under this master configuration to start trading automatically.</p>
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
        <div class="mt-4 justify-content-center d-flex">
            {{ $configOrders->links() }}
        </div>
    </div>
</section>
@endsection