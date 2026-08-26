{{-- FILE: resources/views/themes/{active_theme}/user/cp/multi-time-configs/index.blade.php --}}
@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .cfg-page-header{background:linear-gradient(135deg,#0f9b8e 0%,#0b6e8f 100%);color:#fff;padding:26px 30px;border-radius:12px;margin-bottom:25px;box-shadow:0 4px 15px rgba(15,155,142,.35);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
    .cfg-page-header h4{margin:0;}
    .cfg-table thead th,.cfg-table tbody td{text-align:center!important;vertical-align:middle;font-size:.85rem;padding:10px 8px!important;}
    .badge-broker-zerodha{background:#387ed1;color:#fff;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:700;}
    .badge-broker-angel{background:#ff6a3d;color:#fff;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:700;}
    .badge-type{background:#e9ecef;color:#333;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:600;}
    .badge-align{background:rgba(40,167,69,.12);color:#28a745;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:700;}
    .badge-opposite{background:rgba(220,53,69,.12);color:#dc3545;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:700;}
    .status-on{background:#28a745;} .status-off{background:#6c757d;}
    .status-pill{color:#fff;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;cursor:pointer;border:none;}
    .empty-state{text-align:center;padding:50px 20px;color:#8a8a8a;}
    .cfg-rule-badge{background:rgba(15,155,142,.1);color:#0f9b8e;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:700;}
    .cfg-rule-dash{color:#adb5bd;}
    .cfg-snap-badge{background:rgba(0,184,212,.1);color:#0097a7;padding:3px 8px;border-radius:5px;font-size:10px;font-weight:700;margin:1px;display:inline-block;}
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    <div class="cfg-page-header">
        <div>
            <h4>{{ $pageTitle }}</h4>
            <small style="opacity:.9;">OI Flow Sentiment — Multi Snapshot. Orders place automatically at 10:15 / 11:15 / 12:15 when signals fire.</small>
        </div>
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#mtCfgModal" onclick="mtCfgResetForm()">
            <i class="fas fa-plus"></i> New Config
        </button>
    </div>

    <div class="table-responsive">
        <table class="table cfg-table bg-white">
            <thead class="thead-light">
                <tr>
                    <th>#</th>
                    <th>Broker</th>
                    <th>Order</th>
                    <th>Product</th>
                    <th>Disc %</th>
                    <th>Signal Mode</th>
                    <th>Qty</th>
                    <th>Snapshots</th>
                    <th>Max Price %</th>
                    <th>Re-entry Drop %</th>
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
                            <span class="badge-broker-{{ strtolower($config->broker_type) }}">{{ $config->broker_type }}</span><br>
                            <small class="text-muted">{{ $config->broker->client_name ?? '—' }}</small>
                        </td>
                        <td><span class="badge-type">{{ $config->order_type }}</span></td>
                        <td><span class="badge-type">{{ $config->product }}</span></td>
                        <td>{{ $config->order_type === 'LIMIT' ? number_format($config->disc_ltp, 2) . '%' : '—' }}</td>
                        <td><span class="badge-{{ $config->signal_mode }}">{{ ucfirst($config->signal_mode) }}</span></td>
                        <td>{{ $config->quantity }} lot(s)</td>
                        <td>
                            @forelse(($config->snapshot_times ?? []) as $t)
                                <span class="cfg-snap-badge">{{ $t }}</span>
                            @empty
                                <span class="cfg-rule-dash">—</span>
                            @endforelse
                        </td>
                        <td>
                            @if($config->max_price_pct_of_underlying !== null)
                                <span class="cfg-rule-badge">≤ {{ number_format($config->max_price_pct_of_underlying, 2) }}%</span>
                            @else
                                <span class="cfg-rule-dash">—</span>
                            @endif
                        </td>
                        <td>
                            @if($config->reentry_min_drop_pct !== null)
                                <span class="cfg-rule-badge">≥ {{ number_format($config->reentry_min_drop_pct, 2) }}%</span>
                            @else
                                <span class="cfg-rule-dash">—</span>
                            @endif
                        </td>
                        <td><a href="#" class="text-decoration-none">{{ $config->orders_count }}</a></td>
                        <td>
                            <form action="{{ route('cp.multi-time-configs.toggle', $config->id) }}" method="GET" class="d-inline">
                                <button type="submit" class="status-pill {{ $config->status ? 'status-on' : 'status-off' }}">
                                    {{ $config->status ? 'ACTIVE' : 'PAUSED' }}
                                </button>
                            </form>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="mtCfgEdit({{ $config->id }})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('cp.multi-time-configs.destroy', $config->id) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete this config? This cannot be undone.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13">
                            <div class="empty-state">
                                <i class="fas fa-layer-group" style="font-size:2.5rem;opacity:.4;"></i>
                                <p class="mt-3">No configs yet. Click <strong>"New Config"</strong> to set up automatic Multi-Snapshot orders.</p>
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
<div class="modal fade" id="mtCfgModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="mtCfgForm" method="POST" action="{{ route('cp.multi-time-configs.store') }}">
                @csrf
                <input type="hidden" name="_method" id="mt_cfg_method" value="POST">

                <div class="modal-header">
                    <h5 class="modal-title" id="mtCfgModalTitle">New Multi-Snapshot Config</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Broker <span class="text-danger">*</span></label>
                            <select name="broker_api_id" id="mt_cfg_broker_api_id" class="form-control" required onchange="mtCfgSyncBrokerType()">
                                <option value="">— Select Broker —</option>
                                @foreach($brokers as $b)
                                    <option value="{{ $b->id }}" data-type="{{ $b->client_type }}">
                                        {{ $b->client_name }} ({{ $b->client_type }})
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="broker_type" id="mt_cfg_broker_type">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Signal Mode <span class="text-danger">*</span></label>
                            <select name="signal_mode" class="form-control" required>
                                <option value="align">Align — trade the side the signal says (BUY CE → CE)</option>
                                <option value="opposite">Opposite — trade the flipped side (BUY CE → PE)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Order Type <span class="text-danger">*</span></label>
                            <select name="order_type" id="mt_cfg_order_type" class="form-control" required onchange="mtCfgToggleDisc()">
                                <option value="LIMIT" selected>LIMIT</option>
                                <option value="MARKET">MARKET</option>
                            </select>
                        </div>

                        <div class="col-md-4" id="mt_cfg_disc_wrap">
                            <label class="form-label">Discount % (LIMIT orders)</label>
                            <input type="number" step="0.01" min="0" max="100" name="disc_ltp" id="mt_cfg_disc_ltp" class="form-control" value="0">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Product <span class="text-danger">*</span></label>
                            <select name="product" id="mt_cfg_product" class="form-control" required>
                                <option value="NRML" selected>NRML (Carryforward)</option>
                                <option value="MIS">MIS (Intraday)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Quantity (lots) <span class="text-danger">*</span></label>
                            <select name="quantity" class="form-control">
                                @for($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}" @selected($i === 1)>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" id="mt_cfg_status" value="1" checked>
                                <label class="form-check-label" for="mt_cfg_status">Active</label>
                            </div>
                        </div>

                        <div class="col-md-12"><hr></div>

                        <div class="col-md-12">
                            <label class="form-label d-block">Active Snapshots <span class="text-danger">*</span></label>
                            <small class="text-muted d-block mb-2">Pick which snapshot(s) this config should place orders on. At least one required.</small>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input mt-snap-cb" type="checkbox" name="snapshot_times[]" id="mt_snap_1015" value="10:15">
                                    <label class="form-check-label" for="mt_snap_1015">10:15</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input mt-snap-cb" type="checkbox" name="snapshot_times[]" id="mt_snap_1115" value="11:15">
                                    <label class="form-check-label" for="mt_snap_1115">11:15</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input mt-snap-cb" type="checkbox" name="snapshot_times[]" id="mt_snap_1215" value="12:15">
                                    <label class="form-check-label" for="mt_snap_1215">12:15</label>
                                </div>
                            </div>
                            <small class="text-danger d-none" id="mt_snap_error">Select at least one snapshot.</small>
                        </div>

                        <div class="col-md-12"><hr></div>

                        <div class="col-md-12">
                            <h6 class="mb-2">Multi-Snapshot Rules <small class="text-muted">(both optional — leave blank to disable)</small></h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Max Option Price (% of underlying)</label>
                            <input type="number" step="0.01" min="0" max="100" name="max_price_pct_of_underlying" class="form-control" placeholder="e.g. 5 → option must cost ≤5% of stock/future price">
                            <small class="text-muted">Skip any signal where the option premium is above this % of the underlying's price.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Re-entry Min Price Drop (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="reentry_min_drop_pct" class="form-control" placeholder="e.g. 10 → only re-buy same side if ≥10% cheaper">
                            <small class="text-muted">If a later snapshot signals the same side (CE→CE or PE→PE), only re-enter if the new price is at least this much lower than the earlier fill.</small>
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
function mtCfgToggleDisc() {
    var orderType = $('#mt_cfg_order_type').val();
    if (!orderType) {
        orderType = 'LIMIT';
        $('#mt_cfg_order_type').val('LIMIT');
    }
    if (orderType === 'LIMIT') {
        $('#mt_cfg_disc_wrap').show();
    } else {
        $('#mt_cfg_disc_wrap').hide();
        $('#mt_cfg_disc_ltp').val(0); // MARKET orders don't use it — clear so no stale value gets submitted
    }
}

function mtCfgSyncBrokerType() {
    var opt = $('#mt_cfg_broker_api_id option:selected');
    $('#mt_cfg_broker_type').val(opt.data('type') || '');
}

function mtCfgResetForm() {
    $('#mtCfgModalTitle').text('New Multi-Snapshot Config');
    $('#mtCfgForm')[0].reset();
    $('#mtCfgForm').attr('action', '{{ route("cp.multi-time-configs.store") }}');
    $('#mt_cfg_method').val('POST');
    $('#mt_cfg_order_type').val('LIMIT');
    $('#mt_cfg_product').val('NRML');
    $('.mt-snap-cb').prop('checked', false);
    $('#mt_snap_error').addClass('d-none');
    mtCfgToggleDisc();
    mtCfgSyncBrokerType();
}

function mtCfgEdit(id) {
    $.get('/cp/multi-time-configs/' + id + '/data', function (res) {
        if (!res.success) { alert('Failed to load config.'); return; }
        var c = res.config;

        // Reset FIRST so nothing from a previous "New Config" or a
        // different row's Edit can leak into this one.
        $('#mtCfgForm')[0].reset();

        $('#mtCfgModalTitle').text('Edit Multi-Snapshot Config');
        $('#mtCfgForm').attr('action', '/cp/multi-time-configs/' + id);
        $('#mt_cfg_method').val('POST'); // route is registered as POST — see cp.multi-time-configs.update

        $('#mt_cfg_broker_api_id').val(c.broker_api_id);
        $('#mt_cfg_order_type').val(c.order_type);
        $('#mt_cfg_product').val(c.product);
        $('#mt_cfg_disc_ltp').val(c.disc_ltp != null ? c.disc_ltp : 0);
        $('#mtCfgForm select[name="signal_mode"]').val(c.signal_mode);
        $('#mtCfgForm select[name="quantity"]').val(c.quantity);
        $('#mt_cfg_status').prop('checked', !!c.status);
        $('#mtCfgForm input[name="max_price_pct_of_underlying"]').val(c.max_price_pct_of_underlying ?? '');
        $('#mtCfgForm input[name="reentry_min_drop_pct"]').val(c.reentry_min_drop_pct ?? '');

        $('.mt-snap-cb').prop('checked', false);
        (c.snapshot_times || []).forEach(function (t) {
            $('.mt-snap-cb[value="' + t + '"]').prop('checked', true);
        });
        $('#mt_snap_error').addClass('d-none');

        // Run AFTER the values above are set, so visibility/derived fields
        // reflect what was just loaded, not stale defaults.
        mtCfgToggleDisc();
        mtCfgSyncBrokerType();

        // Bootstrap 5 API — this project's markup uses data-bs-* / btn-close,
        // so the modal must be driven by bootstrap.Modal, not the old
        // jQuery $('#mtCfgModal').modal('show') Bootstrap 4 API.
        var modalElement = document.getElementById('mtCfgModal');
        var modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        modal.show();
    }).fail(function () {
        alert('Failed to load config — check the network tab for the actual error.');
    });
}

// Client-side guard: block submit if zero snapshots checked (server also
// enforces this via 'snapshot_times' => 'required|array|min:1')
$(document).on('submit', '#mtCfgForm', function (e) {
    var anyChecked = $('.mt-snap-cb:checked').length > 0;
    if (!anyChecked) {
        e.preventDefault();
        $('#mt_snap_error').removeClass('d-none');
    }
});
</script>
@endpush