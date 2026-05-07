@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<style>
    .page-header { background:linear-gradient(135deg,#11998e,#38ef7d); color:white; padding:24px 28px; border-radius:14px; margin-bottom:24px; box-shadow:0 6px 20px rgba(17,153,142,0.4); }
    .page-header h4 { font-size:1.4rem; margin-bottom:4px; font-weight:700; }
    .page-header p  { font-size:13px; margin-bottom:0; opacity:.85; }

    .stats-box { background:#fff; padding:16px; border-radius:12px; text-align:center; border-left:5px solid #11998e; box-shadow:0 3px 12px rgba(0,0,0,.08); transition:transform .2s; margin-bottom:20px; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.5px; }
    .stats-box strong { display:block; font-size:1.7rem; font-weight:700; margin-top:4px; }

    .custom--table thead th, .custom--table tbody td { vertical-align:middle; font-size:12px; padding:10px !important; }

    .modal-header-gradient { background:linear-gradient(135deg,#11998e,#38ef7d); color:white; border-radius:12px 12px 0 0; padding:18px 24px; }
    .modal-header-gradient .btn-close { filter:brightness(0) invert(1); opacity:1; }
    .modal-content { border-radius:14px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.2); }

    .section-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#11998e; margin:18px 0 10px; padding-bottom:6px; border-bottom:2px solid #11998e33; }
    .status-active   { background:#d4edda; color:#155724; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    .status-inactive { background:#f8d7da; color:#721c24; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    .btn-action  { padding:5px 10px; font-size:11px; border-radius:6px; margin:1px; }
    .form-section { background:#f8fff9; border-radius:10px; padding:16px; margin-bottom:4px; border:1px solid #c3e6cb; }
    label  { color:#000 !important; }
    .badge { color:#000000 !important; }

    /* ── Run Now panel ── */
    .run-panel { background:linear-gradient(135deg,rgba(17,153,142,.12),rgba(56,239,125,.08)); border:2px solid #11998e44; border-radius:14px; padding:20px 24px; margin-bottom:24px; }
    .run-panel h6 { font-weight:700; color:#11998e; margin-bottom:6px; }
    .run-panel small { color:#555; font-size:12px; }
    .run-result { display:none; margin-top:12px; padding:10px 16px; border-radius:8px; font-size:12px; font-weight:600; }
    .run-result.ok  { background:#d4edda; color:#155724; }
    .run-result.err { background:#f8d7da; color:#721c24; }
    .run-summary-grid { display:flex; gap:12px; flex-wrap:wrap; margin-top:10px; }
    .run-sum-box { background:white; border-radius:8px; padding:10px 16px; text-align:center; flex:1; min-width:80px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
    .run-sum-box small { display:block; font-size:10px; color:#888; text-transform:uppercase; }
    .run-sum-box strong { display:block; font-size:1.4rem; font-weight:700; color:#11998e; }

    /* orders mini-table */
    .recent-order-badge { font-size:10px; padding:2px 8px; border-radius:10px; }
    .badge-open-high { background:#fde8e8; color:#c82333; }
    .badge-open-low  { background:#e8f8e8; color:#155724; }
    .badge-placed    { background:#d4edda; color:#155724; }
    .badge-pending   { background:#fff3cd; color:#856404; }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- ===== PAGE HEADER ===== --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }}</h4>
                    <p>📈 9:15 FUT candle Open=High → BUY PE &nbsp;|&nbsp; Open=Low → BUY CE &nbsp;|&nbsp; Configurable tolerance</p>
                </div>
                <div>
                    <a href="{{ route('fut-ohl.analysis') }}" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-chart-bar"></i> Analysis
                    </a>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createConfigModal">
                        <i class="fas fa-plus"></i> New Config
                    </button>
                </div>
            </div>
        </div>

        {{-- ===== MANUAL RUN PANEL ===== --}}
        <div class="run-panel">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h6>⚡ Run Now — Manual Trigger</h6>
                    <small>Detects today's 9:15 Open=High/Low signals and places pending orders immediately. Use anytime after 9:15 AM.</small>
                    <div class="mt-2">
                        <label style="font-size:11px; color:#555; margin-right:8px;">Test date (optional):</label>
                        <input type="date" id="manual_test_date" class="form-control d-inline-block" style="width:160px; font-size:12px; padding:4px 8px;">
                    </div>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button id="btn_run_now" class="btn btn-success px-4" onclick="runNow()">
                        <i class="fas fa-play"></i> Run Now
                    </button>
                    <button id="btn_place_only" class="btn btn-outline-success px-3" onclick="placePending()">
                        <i class="fas fa-paper-plane"></i> Place Pending
                    </button>
                </div>
            </div>

            <div class="run-result" id="run_result"></div>

            <div class="run-summary-grid" id="run_summary" style="display:none;">
                <div class="run-sum-box"><small>Detected</small><strong id="rs_detected">0</strong></div>
                <div class="run-sum-box"><small>Created</small><strong id="rs_created">0</strong></div>
                <div class="run-sum-box"><small>Skipped</small><strong id="rs_skipped" style="color:#856404;">0</strong></div>
                <div class="run-sum-box"><small>Placed</small><strong id="rs_placed" style="color:#155724;">0</strong></div>
                <div class="run-sum-box"><small>Failed</small><strong id="rs_failed" style="color:#721c24;">0</strong></div>
            </div>
        </div>

        {{-- ===== STATS ===== --}}
        <div class="row mb-2">
            <div class="col-md-3">
                <div class="stats-box"><small>Total Configs</small><strong class="text-dark">{{ $configs->total() }}</strong></div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color:#28a745;">
                    <small>Active</small><strong style="color:#28a745;">{{ $configs->where('status',true)->count() }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color:#dc3545;">
                    <small>Inactive</small><strong style="color:#dc3545;">{{ $configs->where('status',false)->count() }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color:#17a2b8;">
                    <small>Total Orders</small><strong style="color:#17a2b8;">{{ $configs->sum('orders_count') }}</strong>
                </div>
            </div>
        </div>

        {{-- ===== CONFIGS TABLE ===== --}}
        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Broker</th>
                        <th>Tolerance</th>
                        <th>Signal Mode</th>
                        <th>Order</th>
                        <th>Product</th>
                        <th>Series</th>
                        <th>CE Lots</th>
                        <th>PE Lots</th>
                        <th>Orders</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($configs as $index => $config)
                    <tr>
                        <td><strong>{{ $configs->firstItem() + $index }}</strong></td>
                        <td><strong style="color:#11998e;">{{ $config->broker->client_name ?? 'N/A' }}</strong></td>
                        <td>
                            <span class="badge bg-warning">±{{ $config->tolerance }} pt</span>
                        </td>
                        <td>
                            @if($config->signal_mode === 'align')
                                <span class="badge bg-success">↗ ALIGN</span>
                            @else
                                <span class="badge bg-danger">↔ OPPOSITE</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $config->order_type === 'MARKET' ? 'success' : 'info' }}">{{ $config->order_type }}</span><br>
                            <small class="text-muted">Disc: {{ $config->disc_ltp }}%</small>
                        </td>
                        <td><span class="badge bg-{{ $config->product === 'NRML' ? 'primary' : 'warning' }}">{{ $config->product }}</span></td>
                        <td><span class="badge bg-secondary">{{ strtoupper($config->option_series) }}</span></td>
                        <td><span class="text-success fw-bold">{{ $config->ce_quantity }}</span></td>
                        <td><span class="text-danger fw-bold">{{ $config->pe_quantity }}</span></td>
                        <td>
                            <a href="{{ route('fut-ohl-auto.orders', $config->id) }}" class="text-primary fw-bold">
                                {{ $config->orders_count }}
                            </a>
                        </td>
                        <td>
                            @if($config->status)
                                <span class="status-active">✅ Active</span>
                            @else
                                <span class="status-inactive">❌ Inactive</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-info btn-action btn-edit-config"
                                data-id="{{ $config->id }}"
                                data-broker="{{ $config->broker_api_id }}"
                                data-tolerance="{{ $config->tolerance }}"
                                data-signal_mode="{{ $config->signal_mode }}"
                                data-option_series="{{ $config->option_series }}"
                                data-order_type="{{ $config->order_type }}"
                                data-product="{{ $config->product }}"
                                data-disc_ltp="{{ $config->disc_ltp }}"
                                data-ce="{{ $config->ce_quantity }}"
                                data-pe="{{ $config->pe_quantity }}"
                                data-status="{{ $config->status ? '1' : '0' }}"
                                title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>

                            <form action="{{ route('fut-ohl-auto.toggle', $config->id) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-{{ $config->status ? 'warning' : 'success' }} btn-action"
                                    onclick="return confirm('{{ $config->status ? 'Deactivate' : 'Activate' }} this config?')">
                                    <i class="fas fa-{{ $config->status ? 'pause' : 'play' }}"></i>
                                </button>
                            </form>

                            <a href="{{ route('fut-ohl-auto.orders', $config->id) }}" class="btn btn-primary btn-action">
                                <i class="fas fa-list"></i>
                            </a>

                            <form action="{{ route('fut-ohl-auto.destroy', $config->id) }}" method="POST" style="display:inline;">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-action"
                                    onclick="return confirm('Delete this configuration?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="text-center py-5">
                            <i class="fas fa-chart-line" style="font-size:2.5rem; opacity:.3; color:#11998e;"></i>
                            <p class="mt-3 text-muted">No configurations yet.
                                <a href="#" data-bs-toggle="modal" data-bs-target="#createConfigModal">Create one</a>.
                            </p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($configs->hasPages())
        <div class="p-3">{{ $configs->links() }}</div>
        @endif

        {{-- ===== RECENT ORDERS ===== --}}
        @if($recentOrders->count())
        <h6 class="mt-4 mb-2 fw-bold" style="color:#11998e;">🕐 Recent Orders (Last 10)</h6>
        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>Signal</th>
                        <th>Option</th>
                        <th>Strike</th>
                        <th>Entry ₹</th>
                        <th>Qty</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentOrders as $i => $order)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $order->signal_date }}</td>
                        <td><strong style="color:#11998e;">{{ $order->symbol }}</strong></td>
                        <td>
                            <span class="recent-order-badge {{ $order->signal_type === 'OPEN=HIGH' ? 'badge-open-high' : 'badge-open-low' }}">
                                {{ $order->signal_type }}
                            </span>
                        </td>
                        <td><small>{{ $order->option_symbol ?? '—' }}</small></td>
                        <td>{{ $order->strike_price ? '₹' . $order->strike_price : '—' }}</td>
                        <td>₹{{ $order->entry_price }}</td>
                        <td>{{ $order->quantity }}</td>
                        <td>
                            @if($order->is_order_placed)
                                <span class="recent-order-badge badge-placed">✅ Placed</span>
                            @else
                                <span class="recent-order-badge badge-pending">⏳ Pending</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- CREATE MODAL                                                   --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="createConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New FUT OHL Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('fut-ohl-auto.store') }}" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog me-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" class="form-select" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Order Type</label>
                                <select name="order_type" class="form-select" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Product</label>
                                <select name="product" class="form-select" required>
                                    <option value="MIS">MIS</option>
                                    <option value="NRML">NRML</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Discount % (LIMIT)</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">0 = at LTP</small>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" class="form-select" required>
                                    <option value="align">↗ ALIGN (default logic)</option>
                                    <option value="opposite">↔ OPPOSITE (reverse)</option>
                                </select>
                                <small class="text-muted">Align: Open=High→PE, Open=Low→CE</small>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Option Series</label>
                                <select name="option_series" class="form-select" required>
                                    <option value="current">Current expiry</option>
                                    <option value="next">Next expiry</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-sliders-h me-1"></i> Signal Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Tolerance (points)</label>
                                <div class="input-group">
                                    <input type="number" name="tolerance" class="form-control" value="1" min="0" max="100" step="0.5" required />
                                    <span class="input-group-text">pts</span>
                                </div>
                                <small class="text-muted">|Open − High/Low| ≤ tolerance triggers signal</small>
                            </div>
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">CE Lots <small class="text-muted">(Open=Low → BUY CE)</small></label>
                                <input type="number" name="ce_quantity" class="form-control" value="0" min="0" required />
                                <small class="text-muted">Lots to buy when Open=Low signal fires</small>
                            </div>
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">PE Lots <small class="text-muted">(Open=High → BUY PE)</small></label>
                                <input type="number" name="pe_quantity" class="form-control" value="0" min="0" required />
                                <small class="text-muted">Lots to buy when Open=High signal fires</small>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save me-1"></i> Create Config
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- EDIT MODAL                                                     --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="editConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit FUT OHL Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editConfigForm" action="" method="POST">
                @csrf @method('PUT')
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog me-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" id="edit_broker_api_id" class="form-select" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Order Type</label>
                                <select name="order_type" id="edit_order_type" class="form-select" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Product</label>
                                <select name="product" id="edit_product" class="form-select" required>
                                    <option value="MIS">MIS</option>
                                    <option value="NRML">NRML</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Discount % (LIMIT)</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" id="edit_disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Status</label>
                                <select name="status" id="edit_status" class="form-select" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" id="edit_signal_mode" class="form-select" required>
                                    <option value="align">↗ ALIGN</option>
                                    <option value="opposite">↔ OPPOSITE</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Option Series</label>
                                <select name="option_series" id="edit_option_series" class="form-select" required>
                                    <option value="current">Current expiry</option>
                                    <option value="next">Next expiry</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-sliders-h me-1"></i> Signal Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Tolerance (points)</label>
                                <div class="input-group">
                                    <input type="number" name="tolerance" id="edit_tolerance" class="form-control" value="1" min="0" max="100" step="0.5" required />
                                    <span class="input-group-text">pts</span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">CE Lots (Open=Low)</label>
                                <input type="number" name="ce_quantity" id="edit_ce_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">PE Lots (Open=High)</label>
                                <input type="number" name="pe_quantity" id="edit_pe_quantity" class="form-control" value="0" min="0" required />
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save me-1"></i> Update Config
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
// ── Edit modal population ────────────────────────────────────────────────────
$(document).on('click', '.btn-edit-config', function () {
    const d   = $(this).data();
    const url = '{{ route("fut-ohl-auto.update", ":id") }}'.replace(':id', d.id);
    $('#editConfigForm').attr('action', url);
    $('#edit_broker_api_id').val(d.broker);
    $('#edit_order_type').val(d.order_type);
    $('#edit_product').val(d.product);
    $('#edit_disc_ltp').val(d.disc_ltp);
    $('#edit_status').val(d.status);
    $('#edit_signal_mode').val(d.signal_mode);
    $('#edit_option_series').val(d.option_series);
    $('#edit_tolerance').val(d.tolerance);
    $('#edit_ce_quantity').val(d.ce);
    $('#edit_pe_quantity').val(d.pe);
    const editModal = new bootstrap.Modal(document.getElementById('editConfigModal'));
    editModal.show();
});

// ── Run Now ──────────────────────────────────────────────────────────────────
function showRunResult(msg, ok, summary) {
    const $r = $('#run_result');
    $r.removeClass('ok err').addClass(ok ? 'ok' : 'err').text(msg).show();

    if (summary && ok) {
        $('#rs_detected').text(summary.detected ?? 0);
        $('#rs_created').text(summary.created ?? 0);
        $('#rs_skipped').text(summary.skipped ?? 0);
        $('#rs_placed').text(summary.placed ?? 0);
        $('#rs_failed').text(summary.failed ?? 0);
        $('#run_summary').show();
    } else {
        $('#run_summary').hide();
    }
}

function setRunBtns(loading) {
    const icon = loading ? '<i class="fas fa-spinner fa-spin"></i> Running...' : '<i class="fas fa-play"></i> Run Now';
    $('#btn_run_now').prop('disabled', loading).html(icon);
    $('#btn_place_only').prop('disabled', loading);
}

function runNow() {
    setRunBtns(true);
    const testDate = $('#manual_test_date').val();

    $.ajax({
        url  : '{{ route("fut-ohl-auto.run-now") }}',
        type : 'POST',
        data : { _token: '{{ csrf_token() }}', test_date: testDate || null },
        success: function(res) {
            setRunBtns(false);
            showRunResult(res.message, res.success, res.summary);
            if (res.success) setTimeout(() => location.reload(), 3000);
        },
        error: function(xhr) {
            setRunBtns(false);
            const msg = xhr.responseJSON?.message || 'Server error';
            showRunResult('❌ ' + msg, false, null);
        }
    });
}

function placePending() {
    $('#btn_place_only').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.ajax({
        url  : '{{ route("fut-ohl-auto.place-pending") }}',
        type : 'POST',
        data : { _token: '{{ csrf_token() }}' },
        success: function(res) {
            $('#btn_place_only').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Place Pending');
            showRunResult(res.message, res.success, { placed: res.summary?.placed, failed: res.summary?.failed });
        },
        error: function() {
            $('#btn_place_only').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Place Pending');
            showRunResult('❌ Server error', false, null);
        }
    });
}
</script>
@endpush