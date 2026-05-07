@extends($activeTemplate . 'layouts.master')

@section('content')
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1 text-dark">Auto Target Orders</h4>
                        <p class="text-muted mb-0">Automatic 20% profit target system</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" onclick="syncPositions()">
                            <i class="fas fa-sync-alt"></i> Sync Positions
                        </button>
                        <button class="btn btn-info" onclick="triggerMonitoring()">
                            <i class="fas fa-play"></i> Monitor Now
                        </button>
                        <button class="btn btn-secondary" onclick="refreshData()">
                            <i class="fas fa-refresh"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Active Targets
                            </div>
                            <div class="h5 mb-0 font-weight-bold">{{ $stats['active_targets'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-bullseye fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Placement
                            </div>
                            <div class="h5 mb-0 font-weight-bold">{{ $stats['pending'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Orders Placed
                            </div>
                            <div class="h5 mb-0 font-weight-bold">{{ $stats['placed'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Completed
                            </div>
                            <div class="h5 mb-0 font-weight-bold">{{ $stats['completed'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Broker Selection -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <label class="form-label text-dark">Select Broker</label>
                    <select id="brokerSelect" class="form-select">
                        <option value="">-- All Brokers --</option>
                        @foreach($brokers as $broker)
                            <option value="{{ $broker->id }}">
                                {{ $broker->account_user_name }} ({{ $broker->client_name }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <label class="form-label text-dark">Potential Profit</label>
                    <h4 class="text-success mb-0">
                        ₹{{ number_format($stats['total_potential_profit'] ?? 0, 2) }}
                    </h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto Targets Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Active Auto Target Orders</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="autoTargetsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Symbol</th>
                                    <th>Exchange</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Buy Price</th>
                                    <th>Target Price</th>
                                    <th>Current Price</th>
                                    <th>Current P&L</th>
                                    <th>Profit %</th>
                                    <th>Status</th>
                                    <th>Order ID</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($autoTargets as $target)
                                <tr>
                                    <td><strong>{{ $target->tradingsymbol }}</strong></td>
                                    <td>{{ $target->exchange }}</td>
                                    <td><span class="badge bg-secondary">{{ $target->product }}</span></td>
                                    <td>{{ $target->quantity }}</td>
                                    <td>₹{{ number_format($target->buy_price, 2) }}</td>
                                    <td class="text-success">₹{{ number_format($target->target_price, 2) }}</td>
                                    <td>₹{{ number_format($target->current_price ?? 0, 2) }}</td>
                                    <td class="{{ $target->current_profit >= 0 ? 'text-success' : 'text-danger' }}">
                                        ₹{{ number_format($target->current_profit ?? 0, 2) }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $target->current_profit_percentage >= 20 ? 'bg-success' : 'bg-warning' }}">
                                            {{ number_format($target->current_profit_percentage ?? 0, 2) }}%
                                        </span>
                                    </td>
                                    <td>
                                        @if($target->order_status == 'PENDING')
                                            <span class="badge bg-warning">Pending</span>
                                        @elseif($target->order_status == 'PLACED')
                                            <span class="badge bg-info">Placed</span>
                                        @elseif($target->order_status == 'TRIGGERED')
                                            <span class="badge bg-primary">Triggered</span>
                                        @elseif($target->order_status == 'COMPLETED')
                                            <span class="badge bg-success">Completed</span>
                                        @elseif($target->order_status == 'CANCELLED')
                                            <span class="badge bg-secondary">Cancelled</span>
                                        @elseif($target->order_status == 'FAILED')
                                            <span class="badge bg-danger">Failed</span>
                                        @else
                                            <span class="badge bg-dark">{{ $target->order_status }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($target->target_order_id)
                                            <small class="text-muted">{{ $target->target_order_id }}</small>
                                        @else
                                            <small class="text-muted">-</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if(in_array($target->order_status, ['PENDING', 'PLACED']))
                                            <button class="btn btn-sm btn-danger" onclick="cancelTarget({{ $target->id }})">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @endif
                                        <button class="btn btn-sm btn-info" onclick="viewDetails({{ $target->id }})">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">
                                        No auto target orders found. Click "Sync Positions" to create targets.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-3">
                        {{ $autoTargets->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Target Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Target Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
    function syncPositions() {
        const brokerId = document.getElementById('brokerSelect').value;
        
        if (!brokerId) {
            alert('Please select a broker first');
            return;
        }

        const btn = event.target.closest('button');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

        fetch('{{ route("auto-targets.sync") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ broker_id: brokerId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('error', data.message);
            }
        })
        .catch(error => {
            showNotification('error', 'Error syncing positions');
            console.error(error);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync Positions';
        });
    }

    function triggerMonitoring() {
        const btn = event.target.closest('button');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Monitoring...';

        fetch('{{ route("auto-targets.monitor") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('error', data.message);
            }
        })
        .catch(error => {
            showNotification('error', 'Error triggering monitoring');
            console.error(error);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Monitor Now';
        });
    }

    function cancelTarget(id) {
        if (!confirm('Are you sure you want to cancel this auto target order?')) {
            return;
        }

        fetch(`{{ url('auto-targets') }}/${id}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('error', data.message);
            }
        })
        .catch(error => {
            showNotification('error', 'Error cancelling target');
            console.error(error);
        });
    }

    function viewDetails(id) {
        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        modal.show();

        fetch(`{{ url('auto-targets') }}/${id}`, {
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const target = data.data;
                document.getElementById('detailsContent').innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Symbol:</strong> ${target.tradingsymbol}</p>
                            <p><strong>Exchange:</strong> ${target.exchange}</p>
                            <p><strong>Product:</strong> ${target.product}</p>
                            <p><strong>Quantity:</strong> ${target.quantity}</p>
                            <p><strong>Buy Price:</strong> ₹${target.buy_price}</p>
                            <p><strong>Target Price:</strong> ₹${target.target_price}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Current Price:</strong> ₹${target.current_price || 0}</p>
                            <p><strong>Current Profit:</strong> <span class="${target.current_profit >= 0 ? 'text-success' : 'text-danger'}">₹${target.current_profit || 0}</span></p>
                            <p><strong>Profit %:</strong> ${target.current_profit_percentage || 0}%</p>
                            <p><strong>Status:</strong> <span class="badge bg-info">${target.order_status}</span></p>
                            <p><strong>Order ID:</strong> ${target.target_order_id || 'Not placed yet'}</p>
                            <p><strong>Created:</strong> ${target.created_at}</p>
                        </div>
                    </div>
                    ${target.error_message ? `<div class="alert alert-danger mt-3">${target.error_message}</div>` : ''}
                `;
            }
        })
        .catch(error => {
            document.getElementById('detailsContent').innerHTML = '<div class="alert alert-danger">Error loading details</div>';
        });
    }

    function refreshData() {
        location.reload();
    }

    function showNotification(type, message) {
        // Implement your notification system here
        alert(message);
    }

    // Auto-refresh every 30 seconds
    setInterval(() => {
        if (!document.hidden) {
            refreshData();
        }
    }, 30000);
</script>
@endpush

@push('style')
<style>
    .border-left-primary {
        border-left: 4px solid #4e73df;
    }
    .border-left-success {
        border-left: 4px solid #1cc88a;
    }
    .border-left-info {
        border-left: 4px solid #36b9cc;
    }
    .border-left-warning {
        border-left: 4px solid #f6c23e;
    }
    .table td, .table th {
        vertical-align: middle;
    }
    .card {
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>
@endpush