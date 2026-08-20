@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .cfg-page-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:26px 30px;border-radius:12px;margin-bottom:25px;box-shadow:0 4px 15px rgba(102,126,234,.35);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
    .cfg-page-header h4{margin:0;}
    .cfg-table thead th,.cfg-table tbody td{text-align:center!important;vertical-align:middle;font-size:.85rem;padding:10px 8px!important;}
    .cfg-table thead th:nth-child(2),.cfg-table tbody td:nth-child(2){text-align:left!important;}
    .badge-broker-zerodha{background:#387ed1;color:#fff;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:700;}
    .badge-broker-angel{background:#ff6a3d;color:#fff;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:700;}
    .badge-type{background:#e9ecef;color:#333;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:600;}
    .badge-align{background:rgba(40,167,69,.12);color:#28a745;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:700;}
    .badge-opposite{background:rgba(220,53,69,.12);color:#dc3545;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:700;}
    .qty-ce{color:#28a745;font-weight:700;} .qty-pe{color:#dc3545;font-weight:700;}
    .status-on{background:#28a745;} .status-off{background:#6c757d;}
    .status-pill{color:#fff;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;cursor:pointer;border:none;}
    .empty-state{text-align:center;padding:50px 20px;color:#8a8a8a;}
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    <div class="cfg-page-header">
        <div>
            <h4>{{ $pageTitle }}</h4>
            <small style="opacity:.9;">Link an analysis to a broker — orders place automatically when signals fire.</small>
        </div>
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#cfgModal" onclick="cfgResetForm()">
            <i class="fas fa-plus"></i> New Config
        </button>
    </div>

    <div class="table-responsive">
        <table class="table cfg-table bg-white">
            <thead class="thead-light">
                <tr>
                    <th>#</th>
                    <th>Analysis</th>
                    <th>Broker</th>
                    <th>Order</th>
                    <th>Product</th>
                    <th>Disc %</th>
                    <th>Signal Mode</th>
                    <th>CE Qty</th>
                    <th>PE Qty</th>
                    <th>Orders</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($configs as $config)
                    <tr>
                        <td>{{ $configs->firstItem() + $loop->index }}</td>
                        <td>
                            <strong>{{ $config->analysis->name ?? '—' }}</strong><br>
                            <small class="text-muted">{{ $config->analysis->route_name ?? '' }}</small>
                        </td>
                        <td>
                            <span class="badge-broker-{{ strtolower($config->broker_type) }}">{{ $config->broker_type }}</span><br>
                            <small class="text-muted">{{ $config->broker->client_name ?? '—' }}</small>
                        </td>
                        <td><span class="badge-type">{{ $config->order_type }}</span></td>
                        <td><span class="badge-type">{{ $config->product }}</span></td>
                        <td>{{ $config->order_type === 'LIMIT' ? number_format($config->disc_ltp, 2) . '%' : '—' }}</td>
                        <td><span class="badge-{{ $config->signal_mode }}">{{ ucfirst($config->signal_mode) }}</span></td>
                        <td class="qty-ce">{{ $config->ce_quantity }}</td>
                        <td class="qty-pe">{{ $config->pe_quantity }}</td>
                        <td>
                            <a href="#" class="text-decoration-none">{{ $config->orders_count }}</a>
                        </td>
                        <td>
                            <form action="{{ route('cp.order-configs.toggle', $config->id) }}" method="GET" class="d-inline">
                                <button type="submit" class="status-pill {{ $config->status ? 'status-on' : 'status-off' }}">
                                    {{ $config->status ? 'ACTIVE' : 'PAUSED' }}
                                </button>
                            </form>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="cfgEdit({{ $config->id }})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('cp.order-configs.destroy', $config->id) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete this config? This cannot be undone.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">
                            <div class="empty-state">
                                <i class="fas fa-cogs" style="font-size:2.5rem;opacity:.4;"></i>
                                <p class="mt-3">No configs yet. Click <strong>"New Config"</strong> to link an analysis to a broker.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $configs->links() }}</div>
</div>
</section>

<!-- ══ Create / Edit Modal ══ -->
<div class="modal fade" id="cfgModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="cfgForm" method="POST" action="{{ route('cp.order-configs.store') }}">
                @csrf
                <input type="hidden" name="_method" id="cfg_method" value="POST">

                <div class="modal-header">
                    <h5 class="modal-title" id="cfgModalTitle">New Order Config</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Analysis <span class="text-danger">*</span></label>
                            <select name="cp_analysis_id" class="form-control" required>
                                <option value="">— Select Analysis —</option>
                                @foreach($analyses as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Broker <span class="text-danger">*</span></label>
                            <select name="broker_api_id" id="cfg_broker_api_id" class="form-control" required onchange="cfgSyncBrokerType()">
                                <option value="">— Select Broker —</option>
                                @foreach($brokers as $b)
                                    <option value="{{ $b->id }}" data-type="{{ $b->client_type }}">
                                        {{ $b->client_name }} ({{ $b->client_type }})
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="broker_type" id="cfg_broker_type">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Order Type <span class="text-danger">*</span></label>
                            <select name="order_type" id="cfg_order_type" class="form-control" required onchange="cfgToggleDisc()">
                                <option value="MARKET">MARKET</option>
                                <option value="LIMIT">LIMIT</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Product <span class="text-danger">*</span></label>
                            <select name="product" class="form-control" required>
                                <option value="MIS">MIS (Intraday)</option>
                                <option value="NRML">NRML (Carryforward)</option>
                            </select>
                        </div>

                        <div class="col-md-4" id="cfg_disc_wrap" style="display:none;">
                            <label class="form-label">Discount off LTP (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="disc_ltp" class="form-control" value="0">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Signal Mode <span class="text-danger">*</span></label>
                            <select name="signal_mode" class="form-control" required>
                                <option value="align">Align — trade the side the signal says (BUY CE → CE)</option>
                                <option value="opposite">Opposite — trade the flipped side (BUY CE → PE)</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">CE Quantity (lots)</label>
                            <input type="number" min="0" name="ce_quantity" class="form-control" value="0" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">PE Quantity (lots)</label>
                            <input type="number" min="0" name="pe_quantity" class="form-control" value="0" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" id="cfg_status" value="1" checked>
                                <label class="form-check-label" for="cfg_status">Active</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Config</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
function cfgToggleDisc() {
    $('#cfg_disc_wrap').toggle($('#cfg_order_type').val() === 'LIMIT');
}
function cfgSyncBrokerType() {
    var opt = $('#cfg_broker_api_id option:selected');
    $('#cfg_broker_type').val(opt.data('type') || '');
}
function cfgResetForm() {
    $('#cfgModalTitle').text('New Order Config');
    $('#cfgForm')[0].reset();
    $('#cfgForm').attr('action', '{{ route("cp.order-configs.store") }}');
    $('#cfg_method').val('POST');
    cfgToggleDisc();
    cfgSyncBrokerType();
}
function cfgEdit(id) {
    $.get('/cp/order-configs/' + id + '/data', function (res) {
        if (!res.success) { alert('Failed to load config.'); return; }
        var c = res.config;

        $('#cfgModalTitle').text('Edit Order Config');
        $('#cfgForm').attr('action', '/cp/order-configs/' + id);
        $('#cfg_method').val('PUT'); // controller uses POST + route accepts POST; Laravel resolves via method spoof if you switch route to PUT, otherwise leave POST

        $('#cfgForm select[name="cp_analysis_id"]').val(c.cp_analysis_id);
        $('#cfg_broker_api_id').val(c.broker_api_id);
        $('#cfg_order_type').val(c.order_type);
        $('#cfgForm select[name="product"]').val(c.product);
        $('#cfgForm input[name="disc_ltp"]').val(c.disc_ltp);
        $('#cfgForm select[name="signal_mode"]').val(c.signal_mode);
        $('#cfgForm input[name="ce_quantity"]').val(c.ce_quantity);
        $('#cfgForm input[name="pe_quantity"]').val(c.pe_quantity);
        $('#cfg_status').prop('checked', !!c.status);

        cfgToggleDisc();
        cfgSyncBrokerType();
        $('#cfgModal').modal('show');
    });
}
$(function () { cfgToggleDisc(); });
</script>
@endpush