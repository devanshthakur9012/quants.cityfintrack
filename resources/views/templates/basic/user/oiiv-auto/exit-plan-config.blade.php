@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .page-header {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        color: white; padding: 24px 28px; border-radius: 14px;
        margin-bottom: 24px;
        box-shadow: 0 6px 20px rgba(0,210,255,0.2);
        border: 1px solid rgba(0,210,255,0.25);
    }
    .page-header h4 { font-size:1.4rem; margin-bottom:4px; font-weight:700; color:#00d2ff; }
    .page-header p  { font-size:13px; margin-bottom:0; opacity:.85; }

    .stats-box {
        background:#fff; padding:16px; border-radius:12px; text-align:center;
        border-left:5px solid #00d2ff; box-shadow:0 3px 12px rgba(0,0,0,.08);
        transition:transform .2s; margin-bottom:20px;
    }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.5px; }
    .stats-box strong { display:block; font-size:1.7rem; font-weight:700; margin-top:4px; }

    .custom--table thead th,
    .custom--table tbody td { vertical-align:middle; font-size:12px; padding:10px !important; }

    .modal-header-gradient {
        background: linear-gradient(135deg, #0f2027, #2c5364);
        color:white; border-radius:12px 12px 0 0; padding:18px 24px;
        border-bottom:2px solid rgba(0,210,255,0.4);
    }
    .modal-header-gradient h5 { color:#00d2ff; }
    .modal-header-gradient .close { color:white; opacity:1; font-size:1.4rem; }
    .modal-content { border-radius:14px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.2); }

    .section-label {
        font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;
        color:#00d2ff; margin:18px 0 10px; padding-bottom:6px;
        border-bottom:2px solid rgba(0,210,255,0.3);
    }
    .form-section { background:#f8f9ff; border-radius:10px; padding:16px; margin-bottom:4px; }

    .status-active   { background:#d4edda; color:#155724; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    .status-inactive { background:#f8d7da; color:#721c24; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    .btn-action { padding:5px 10px; font-size:11px; border-radius:6px; margin:1px; }

    label { color:#000 !important; }
    .badge { color:#000000 !important; }

    .qty-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .qty-box {
        background:#fff; border:1px solid #dee2e6; border-radius:8px;
        padding:12px 14px;
    }
    .qty-box.index { border-top:3px solid #00d2ff; }
    .qty-box.stock  { border-top:3px solid #764ba2; }
    .qty-box .qty-label { font-size:11px; font-weight:700; text-transform:uppercase;
        letter-spacing:.5px; margin-bottom:10px; }
    .qty-box .qty-label.index { color:#00d2ff; }
    .qty-box .qty-label.stock  { color:#764ba2; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
            <div>
                <h4><i class="fas fa-door-open mr-2"></i>{{ $pageTitle }}</h4>
                <p>Auto SELL orders when OI reversal detected at 09:30 AM next trading day</p>
            </div>
            <div class="d-flex flex-wrap" style="gap:6px;">
                <a href="{{ route('exit-plan.index') }}" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-chart-pie"></i> Exit Plan
                </a>
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-chart-bar"></i> PE/CE Analysis
                </a>
                <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createConfigModal">
                    <i class="fas fa-plus"></i> New Config
                </button>
                <form action="{{ route('exit-plan.config.run-all') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm"
                        onclick="return confirm('Run Exit Plan signal detection + SELL orders for ALL active configs NOW?')">
                        <i class="fas fa-play-circle"></i> Run All Now
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Stats ────────────────────────────────────────────────── --}}
    <div class="row mb-3">
        <div class="col-6 col-md-3">
            <div class="stats-box">
                <small>Total Configs</small>
                <strong class="text-dark">{{ $configs->total() }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-box" style="border-left-color:#28a745;">
                <small>Active</small>
                <strong style="color:#28a745;">{{ $configs->where('status', true)->count() }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-box" style="border-left-color:#dc3545;">
                <small>Inactive</small>
                <strong style="color:#dc3545;">{{ $configs->where('status', false)->count() }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-box" style="border-left-color:#17a2b8;">
                <small>Total Orders</small>
                <strong style="color:#17a2b8;">{{ $configs->sum('orders_count') }}</strong>
            </div>
        </div>
    </div>

    {{-- ── Table ────────────────────────────────────────────────── --}}
    <div style="border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.08); overflow:hidden;">
        <div class="table-responsive">
            <table class="table custom--table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Broker</th>
                        <th>Order / Product</th>
                        <th>Signal Mode</th>
                        <th>Index CE</th>
                        <th>Index PE</th>
                        <th>Stock CE</th>
                        <th>Stock PE</th>
                        <th>Orders</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($configs as $index => $config)
                    <tr>
                        <td><strong>{{ $configs->firstItem() + $index }}</strong></td>

                        <td>
                            <strong style="color:#00d2ff;">{{ $config->broker->client_name ?? 'N/A' }}</strong>
                        </td>

                        <td>
                            <span class="badge badge-{{ $config->order_type === 'MARKET' ? 'success' : 'info' }}">
                                {{ $config->order_type }}
                            </span>
                            <span class="badge badge-{{ $config->product === 'NRML' ? 'primary' : 'warning' }} ml-1">
                                {{ $config->product }}
                            </span>
                            @if($config->disc_ltp > 0)
                                <br><small class="text-muted">Disc: {{ $config->disc_ltp }}%</small>
                            @endif
                        </td>

                        <td>
                            @if($config->signal_mode === 'align')
                                <span class="badge badge-success">↗ ALIGN</span>
                                <br><small class="text-muted" style="font-size:10px;">SELL on EXIT</small>
                            @else
                                <span class="badge badge-danger">↔ OPPOSITE</span>
                                <br><small class="text-muted" style="font-size:10px;">SELL on HOLD</small>
                            @endif
                        </td>

                        <td>
                            <strong class="{{ $config->index_ce_quantity > 0 ? 'text-success' : 'text-muted' }}">
                                {{ $config->index_ce_quantity }} lots
                            </strong>
                        </td>
                        <td>
                            <strong class="{{ $config->index_pe_quantity > 0 ? 'text-danger' : 'text-muted' }}">
                                {{ $config->index_pe_quantity }} lots
                            </strong>
                        </td>
                        <td>
                            <strong class="{{ $config->stock_ce_quantity > 0 ? 'text-success' : 'text-muted' }}">
                                {{ $config->stock_ce_quantity }} lots
                            </strong>
                        </td>
                        <td>
                            <strong class="{{ $config->stock_pe_quantity > 0 ? 'text-danger' : 'text-muted' }}">
                                {{ $config->stock_pe_quantity }} lots
                            </strong>
                        </td>

                        <td>
                            <a href="{{ route('exit-plan.config.orders', $config->id) }}"
                               class="text-primary font-weight-bold">
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
                            {{-- ▶ Run this config --}}
                            <button class="btn btn-warning btn-action btn-run-config"
                                data-config-id="{{ $config->id }}"
                                data-broker="{{ $config->broker->client_name ?? 'N/A' }}"
                                data-bs-toggle="modal" data-bs-target="#runConfigModal"
                                title="Run exit plan signals now">
                                <i class="fas fa-play"></i>
                            </button>

                            {{-- ✏ Edit --}}
                            <button class="btn btn-info btn-action btn-edit-config"
                                data-id="{{ $config->id }}"
                                data-broker="{{ $config->broker_api_id }}"
                                data-order_type="{{ $config->order_type }}"
                                data-product="{{ $config->product }}"
                                data-disc_ltp="{{ $config->disc_ltp }}"
                                data-signal_mode="{{ $config->signal_mode }}"
                                data-status="{{ $config->status ? '1' : '0' }}"
                                data-index_ce="{{ $config->index_ce_quantity }}"
                                data-index_pe="{{ $config->index_pe_quantity }}"
                                data-stock_ce="{{ $config->stock_ce_quantity }}"
                                data-stock_pe="{{ $config->stock_pe_quantity }}"
                                title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>

                            {{-- ⏸/▶ Toggle --}}
                            <form action="{{ route('exit-plan.config.toggle', $config->id) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit"
                                    class="btn btn-{{ $config->status ? 'secondary' : 'success' }} btn-action"
                                    title="{{ $config->status ? 'Deactivate' : 'Activate' }}"
                                    onclick="return confirm('{{ $config->status ? 'Deactivate' : 'Activate' }} this config?')">
                                    <i class="fas fa-{{ $config->status ? 'pause' : 'play' }}"></i>
                                </button>
                            </form>

                            {{-- 📋 Orders --}}
                            <a href="{{ route('exit-plan.config.orders', $config->id) }}"
                               class="btn btn-primary btn-action" title="View Orders">
                                <i class="fas fa-list"></i>
                            </a>

                            {{-- 🗑 Delete --}}
                            <form action="{{ route('exit-plan.config.destroy', $config->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-action" title="Delete"
                                    onclick="return confirm('Delete this Exit Plan configuration?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="text-center py-5">
                            <i class="fas fa-door-open" style="font-size:2.5rem;opacity:.3;color:#00d2ff;"></i>
                            <p class="mt-3 text-muted">No Exit Plan configs yet.
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
    </div>

</div>
</section>

{{-- ═══════════════════════════════════════════ --}}
{{--  RUN MODAL                                  --}}
{{-- ═══════════════════════════════════════════ --}}
<div class="modal fade" id="runConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-play-circle mr-2"></i>Run Exit Plan</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="runConfigForm" action="" method="POST">
                @csrf
                <div class="modal-body p-3">
                    <p style="font-size:13px;margin-bottom:12px;">
                        Broker: <strong id="run_broker_name" style="color:#00d2ff;"></strong>
                    </p>
                    <div class="form-group mb-2">
                        <label style="font-size:12px;font-weight:600;">
                            Test Date <span class="text-muted">(optional — blank = today)</span>
                        </label>
                        <input type="date" name="test_date" class="form-control form-control-sm" />
                    </div>
                    <div class="alert alert-warning p-2 mb-0" style="font-size:11px;">
                        <i class="fas fa-info-circle"></i>
                        Runs signal detection + SELL placement for <strong>all active configs</strong>.
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm px-4">
                        <i class="fas fa-play mr-1"></i> Run Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════ --}}
{{--  CREATE MODAL                               --}}
{{-- ═══════════════════════════════════════════ --}}
<div class="modal fade" id="createConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-plus-circle mr-2"></i>New Exit Plan Config</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('exit-plan.config.store') }}" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">

                    {{-- Info note --}}
                    <div class="alert alert-info p-2 mb-3" style="font-size:11px;border-radius:8px;">
                        <strong>Signal Mode:</strong>
                        <span class="ml-1"><b>ALIGN</b> = place SELL when OI reverses (EXIT decision) — normal use.</span>
                        <span class="ml-1"><b>OPPOSITE</b> = place SELL when OI confirms direction (HOLD decision) — contrarian.</span>
                    </div>

                    <p class="section-label"><i class="fas fa-cog mr-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-12 form-group mb-3">
                                <label style="font-size:12px;font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group mb-3">
                                <label style="font-size:12px;font-weight:600;">Order Type</label>
                                <select name="order_type" class="form-control" required>
                                    <option value="MARKET">MARKET</option>
                                    <option value="LIMIT">LIMIT</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group mb-3">
                                <label style="font-size:12px;font-weight:600;">Product</label>
                                <select name="product" class="form-control" required>
                                    <option value="NRML">NRML</option>
                                    <option value="MIS">MIS</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group mb-3">
                                <label style="font-size:12px;font-weight:600;">Discount %</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label style="font-size:12px;font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" class="form-control" required>
                                    <option value="align">↗ ALIGN — SELL on EXIT signal</option>
                                    <option value="opposite">↔ OPPOSITE — SELL on HOLD signal</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label style="font-size:12px;font-weight:600;">Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-boxes mr-1"></i> Quantities (lots)</p>
                    <div class="form-section">
                        <div class="qty-grid">
                            <div class="qty-box index">
                                <div class="qty-label index"><i class="fas fa-mountain mr-1"></i>Index (NIFTY / BANKNIFTY etc.)</div>
                                <div class="row">
                                    <div class="col-6 form-group mb-0">
                                        <label style="font-size:11px;">CE lots</label>
                                        <input type="number" name="index_ce_quantity" class="form-control form-control-sm" value="0" min="0" required />
                                    </div>
                                    <div class="col-6 form-group mb-0">
                                        <label style="font-size:11px;">PE lots</label>
                                        <input type="number" name="index_pe_quantity" class="form-control form-control-sm" value="0" min="0" required />
                                    </div>
                                </div>
                            </div>
                            <div class="qty-box stock">
                                <div class="qty-label stock"><i class="fas fa-building mr-1"></i>Stocks (ADANIPORTS / SBIN etc.)</div>
                                <div class="row">
                                    <div class="col-6 form-group mb-0">
                                        <label style="font-size:11px;">CE lots</label>
                                        <input type="number" name="stock_ce_quantity" class="form-control form-control-sm" value="0" min="0" required />
                                    </div>
                                    <div class="col-6 form-group mb-0">
                                        <label style="font-size:11px;">PE lots</label>
                                        <input type="number" name="stock_pe_quantity" class="form-control form-control-sm" value="0" min="0" required />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save mr-1"></i> Create Config
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════ --}}
{{--  EDIT MODAL                                 --}}
{{-- ═══════════════════════════════════════════ --}}
<div class="modal fade" id="editConfigModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit mr-2"></i>Edit Exit Plan Config</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editConfigForm" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog mr-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-12 form-group mb-3">
                                <label style="font-size:12px;font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" id="edit_broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group mb-3">
                                <label style="font-size:12px;font-weight:600;">Order Type</label>
                                <select name="order_type" id="edit_order_type" class="form-control" required>
                                    <option value="MARKET">MARKET</option>
                                    <option value="LIMIT">LIMIT</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group mb-3">
                                <label style="font-size:12px;font-weight:600;">Product</label>
                                <select name="product" id="edit_product" class="form-control" required>
                                    <option value="NRML">NRML</option>
                                    <option value="MIS">MIS</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group mb-3">
                                <label style="font-size:12px;font-weight:600;">Discount %</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" id="edit_disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label style="font-size:12px;font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" id="edit_signal_mode" class="form-control" required>
                                    <option value="align">↗ ALIGN — SELL on EXIT signal</option>
                                    <option value="opposite">↔ OPPOSITE — SELL on HOLD signal</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label style="font-size:12px;font-weight:600;">Status</label>
                                <select name="status" id="edit_status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-boxes mr-1"></i> Quantities (lots)</p>
                    <div class="form-section">
                        <div class="qty-grid">
                            <div class="qty-box index">
                                <div class="qty-label index"><i class="fas fa-mountain mr-1"></i>Index</div>
                                <div class="row">
                                    <div class="col-6 form-group mb-0">
                                        <label style="font-size:11px;">CE lots</label>
                                        <input type="number" name="index_ce_quantity" id="edit_index_ce" class="form-control form-control-sm" value="0" min="0" required />
                                    </div>
                                    <div class="col-6 form-group mb-0">
                                        <label style="font-size:11px;">PE lots</label>
                                        <input type="number" name="index_pe_quantity" id="edit_index_pe" class="form-control form-control-sm" value="0" min="0" required />
                                    </div>
                                </div>
                            </div>
                            <div class="qty-box stock">
                                <div class="qty-label stock"><i class="fas fa-building mr-1"></i>Stock</div>
                                <div class="row">
                                    <div class="col-6 form-group mb-0">
                                        <label style="font-size:11px;">CE lots</label>
                                        <input type="number" name="stock_ce_quantity" id="edit_stock_ce" class="form-control form-control-sm" value="0" min="0" required />
                                    </div>
                                    <div class="col-6 form-group mb-0">
                                        <label style="font-size:11px;">PE lots</label>
                                        <input type="number" name="stock_pe_quantity" id="edit_stock_pe" class="form-control form-control-sm" value="0" min="0" required />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save mr-1"></i> Update Config
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
$(document).ready(function () {

    // ── Run modal ───────────────────────────────────────────────
    $(document).on('click', '.btn-run-config', function () {
        const configId = $(this).data('config-id');
        const broker   = $(this).data('broker');
        const url      = '{{ route("exit-plan.config.run", ":id") }}'.replace(':id', configId);
        $('#runConfigForm').attr('action', url);
        $('#run_broker_name').text(broker);
    });

    // ── Edit modal ──────────────────────────────────────────────
    $(document).on('click', '.btn-edit-config', function () {
        const d   = $(this).data();
        const url = '{{ route("exit-plan.config.update", ":id") }}'.replace(':id', d.id);

        $('#editConfigForm').attr('action', url);
        $('#edit_broker_api_id').val(d.broker);
        $('#edit_order_type').val(d.order_type);
        $('#edit_product').val(d.product);
        $('#edit_disc_ltp').val(d.disc_ltp);
        $('#edit_signal_mode').val(d.signal_mode);
        $('#edit_status').val(d.status);
        $('#edit_index_ce').val(d.index_ce);
        $('#edit_index_pe').val(d.index_pe);
        $('#edit_stock_ce').val(d.stock_ce);
        $('#edit_stock_pe').val(d.stock_pe);

        $('#editConfigModal').modal('show');
    });

});
</script>
@endpush