@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<style>
    .page-header { background:linear-gradient(135deg,#e65c00,#f9d423); color:white; padding:24px 28px; border-radius:14px; margin-bottom:24px; box-shadow:0 6px 20px rgba(230,92,0,0.4); }
    .page-header h4 { font-size:1.4rem; margin-bottom:4px; font-weight:700; }
    .page-header p  { font-size:13px; margin-bottom:0; opacity:.85; }

    .stats-box { background:#fff; padding:16px; border-radius:12px; text-align:center; border-left:5px solid #e65c00; box-shadow:0 3px 12px rgba(0,0,0,.08); transition:transform .2s; margin-bottom:20px; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.5px; }
    .stats-box strong { display:block; font-size:1.7rem; font-weight:700; margin-top:4px; }

    .custom--table thead th, .custom--table tbody td { vertical-align:middle; font-size:12px; padding:10px 10px !important; }

    /* ── Bootstrap 5 Modal overrides ─────────────────────── */
    .modal-content { border-radius:14px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-header-gradient {
        background:linear-gradient(135deg,#e65c00,#f9d423);
        color:white;
        border-radius:12px 12px 0 0;
        padding:18px 24px;
        border-bottom:none;
    }
    .modal-header-gradient .modal-title { color:white; font-weight:700; font-size:1rem; }
    .modal-header-gradient .btn-close { filter:brightness(0) invert(1); opacity:.9; }

    .rank-card { border-radius:10px; padding:14px 16px; margin-bottom:10px; border-left:5px solid #ccc; }
    .rank-card.rank-1 { border-left-color:#dc3545; background:#fff5f5; }
    .rank-card.rank-2 { border-left-color:#fd7e14; background:#fff8f0; }
    .rank-card.rank-3 { border-left-color:#007bff; background:#f0f4ff; }
    .rank-card.rank-4 { border-left-color:#6c757d; background:#f8f9fa; }
    .rank-card label  { font-size:12px; color:#555; margin-bottom:4px; }
    .rank-card .rank-title { font-weight:700; font-size:13px; margin-bottom:10px; }

    .section-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#e65c00; margin:18px 0 10px; padding-bottom:6px; border-bottom:2px solid #e65c0033; }
    .status-active   { background:#d4edda; color:#155724; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    .status-inactive { background:#f8d7da; color:#721c24; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    .btn-action  { padding:5px 10px; font-size:11px; border-radius:6px; margin:1px; }
    .form-section { background:#f8f9ff; border-radius:10px; padding:16px; margin-bottom:4px; }
    label  { color:#000 !important; }
    .badge { color:#000000 !important; }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- ===== PAGE HEADER ===== --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }}</h4>
                    <p>🛢️ MCX rank-based CE/PE auto trading configs — separate from NSE/NFO</p>
                </div>
                <div>
                    <a href="{{ route('mcx-oiiv.pece-analysis') }}" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-chart-bar"></i> PE/CE Analysis
                    </a>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createConfigModal">
                        <i class="fas fa-plus"></i> New Config
                    </button>
                </div>
            </div>
        </div>

        {{-- ===== STATS ===== --}}
        <div class="row mb-2">
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total Configs</small>
                    <strong class="text-dark">{{ $configs->total() }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color:#28a745;">
                    <small>Active</small>
                    <strong style="color:#28a745;">{{ $configs->where('status', true)->count() }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color:#dc3545;">
                    <small>Inactive</small>
                    <strong style="color:#dc3545;">{{ $configs->where('status', false)->count() }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box" style="border-left-color:#17a2b8;">
                    <small>Total Orders</small>
                    <strong style="color:#17a2b8;">{{ $configs->sum('orders_count') }}</strong>
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
                        <th>Order</th>
                        <th>Product</th>
                        <th>Series</th>
                        <th>Signal Mode</th>
                        <th>Base (CE/PE)</th>
                        <th>Rank 1</th>
                        <th>Rank 2</th>
                        <th>Rank 3</th>
                        <th>Rank 4</th>
                        <th>Orders</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($configs as $index => $config)
                    <tr>
                        <td><strong>{{ $configs->firstItem() + $index }}</strong></td>
                        <td><strong style="color:#e65c00;">{{ $config->broker->client_name ?? 'N/A' }}</strong></td>
                        <td>
                            <span class="badge badge-{{ $config->order_type === 'MARKET' ? 'success' : 'info' }}">{{ $config->order_type }}</span><br>
                            <small class="text-muted">Disc: {{ $config->disc_ltp }}%</small>
                        </td>
                        <td>
                            <span class="badge badge-{{ $config->product === 'NRML' ? 'primary' : 'warning' }}">{{ $config->product }}</span>
                        </td>
                        <td>
                            <span class="badge badge-{{ $config->option_series === 'current' ? 'secondary' : 'info' }}">{{ strtoupper($config->option_series) }}</span>
                        </td>
                        <td>
                            @if($config->signal_mode === 'align')
                                <span class="badge badge-success"><i class="fas fa-arrow-right"></i> ALIGN</span>
                            @else
                                <span class="badge badge-danger"><i class="fas fa-exchange-alt"></i> OPPOSITE</span>
                            @endif
                        </td>
                        <td>
                            <small>CE: <strong>{{ $config->ce_quantity ?? 0 }}</strong></small><br>
                            <small>PE: <strong>{{ $config->pe_quantity ?? 0 }}</strong></small>
                        </td>
                        @foreach([1,2,3,4] as $rank)
                        <td>
                            @php
                                $ceQty = $config->{"rank{$rank}_ce_quantity"} ?? 0;
                                $peQty = $config->{"rank{$rank}_pe_quantity"} ?? 0;
                            @endphp
                            <small class="{{ $ceQty > 0 ? 'text-success' : 'text-muted' }}">CE: <strong>{{ $ceQty }}</strong></small><br>
                            <small class="{{ $peQty > 0 ? 'text-danger' : 'text-muted' }}">PE: <strong>{{ $peQty }}</strong></small>
                        </td>
                        @endforeach
                        <td>
                            <a href="{{ route('mcx-oiiv.orders', $config->id) }}" class="text-primary fw-bold">
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
                            {{-- Edit --}}
                            <button class="btn btn-info btn-action btn-edit-config"
                                data-id="{{ $config->id }}"
                                data-broker="{{ $config->broker_api_id }}"
                                data-order_type="{{ $config->order_type }}"
                                data-product="{{ $config->product }}"
                                data-disc_ltp="{{ $config->disc_ltp }}"
                                data-signal_mode="{{ $config->signal_mode }}"
                                data-status="{{ $config->status ? '1' : '0' }}"
                                data-option_series="{{ $config->option_series }}"
                                data-ce="{{ $config->ce_quantity ?? 0 }}"
                                data-pe="{{ $config->pe_quantity ?? 0 }}"
                                data-rank1_ce="{{ $config->rank1_ce_quantity ?? 0 }}"
                                data-rank1_pe="{{ $config->rank1_pe_quantity ?? 0 }}"
                                data-rank2_ce="{{ $config->rank2_ce_quantity ?? 0 }}"
                                data-rank2_pe="{{ $config->rank2_pe_quantity ?? 0 }}"
                                data-rank3_ce="{{ $config->rank3_ce_quantity ?? 0 }}"
                                data-rank3_pe="{{ $config->rank3_pe_quantity ?? 0 }}"
                                data-rank4_ce="{{ $config->rank4_ce_quantity ?? 0 }}"
                                data-rank4_pe="{{ $config->rank4_pe_quantity ?? 0 }}"
                                title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>

                            {{-- Toggle Status --}}
                            <form action="{{ route('mcx-oiiv.toggle', $config->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit"
                                    class="btn btn-{{ $config->status ? 'warning' : 'success' }} btn-action"
                                    onclick="return confirm('{{ $config->status ? 'Deactivate' : 'Activate' }} this config?')">
                                    <i class="fas fa-{{ $config->status ? 'pause' : 'play' }}"></i>
                                </button>
                            </form>

                            {{-- View Orders --}}
                            <a href="{{ route('mcx-oiiv.orders', $config->id) }}" class="btn btn-primary btn-action" title="View Orders">
                                <i class="fas fa-list"></i>
                            </a>

                            {{-- Delete --}}
                            <form action="{{ route('mcx-oiiv.destroy', $config->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-action"
                                    onclick="return confirm('Delete this configuration?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="14" class="text-center py-5">
                            <i class="fas fa-oil-can" style="font-size:2.5rem; opacity:.3; color:#e65c00;"></i>
                            <p class="mt-3 text-muted">No MCX configurations yet.
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
</section>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{--  CREATE MODAL  (Bootstrap 5)                                   --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="createConfigModal" tabindex="-1" aria-labelledby="createConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title" id="createConfigModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>New MCX Configuration
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('mcx-oiiv.store') }}" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog me-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Order Type</label>
                                <select name="order_type" class="form-control" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Product</label>
                                <select name="product" class="form-control" required>
                                    <option value="NRML">NRML</option>
                                    <option value="MIS">MIS</option>
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
                                <small class="text-muted">0 = place at LTP</small>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" class="form-control" required>
                                    <option value="align">↗ ALIGN (follow signal)</option>
                                    <option value="opposite">↔ OPPOSITE (reverse)</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Option Series</label>
                                <select name="option_series" class="form-control" required>
                                    <option value="current">Current (nearest expiry)</option>
                                    <option value="next">Next expiry</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-boxes me-1"></i> Base Quantities (Fallback)</p>
                    <small class="text-muted d-block mb-2" style="font-size:11px;">Used when rank qty = 0. Set 0 to rely on rank config only.</small>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label" style="font-size:12px;">CE lots (base)</label>
                                <input type="number" name="ce_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label" style="font-size:12px;">PE lots (base)</label>
                                <input type="number" name="pe_quantity" class="form-control" value="0" min="0" required />
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-layer-group me-1"></i> Rank-Based Quantities</p>

                    <div class="rank-card rank-1">
                        <div class="rank-title" style="color:#dc3545;"><span class="badge bg-danger me-1">Rank 1</span> Strongest — |CE%−PE%| > 40</div>
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label">CE lots</label>
                                <input type="number" name="rank1_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label">PE lots</label>
                                <input type="number" name="rank1_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                    <div class="rank-card rank-2">
                        <div class="rank-title" style="color:#fd7e14;"><span class="badge bg-warning me-1">Rank 2</span> Strong — diff > 25</div>
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label">CE lots</label>
                                <input type="number" name="rank2_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label">PE lots</label>
                                <input type="number" name="rank2_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                    <div class="rank-card rank-3">
                        <div class="rank-title" style="color:#007bff;"><span class="badge bg-primary me-1">Rank 3</span> Moderate — diff > 10</div>
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label">CE lots</label>
                                <input type="number" name="rank3_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label">PE lots</label>
                                <input type="number" name="rank3_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                    <div class="rank-card rank-4">
                        <div class="rank-title" style="color:#6c757d;"><span class="badge bg-secondary me-1">Rank 4</span> Weak — diff > 5</div>
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label">CE lots</label>
                                <input type="number" name="rank4_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label">PE lots</label>
                                <input type="number" name="rank4_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                </div>{{-- end modal-body --}}
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-1"></i> Create Config
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{--  EDIT MODAL  (Bootstrap 5)                                     --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="editConfigModal" tabindex="-1" aria-labelledby="editConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title" id="editConfigModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit MCX Configuration
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editConfigForm" action="" method="POST">
                @csrf
                @method('POST'){{-- controller uses POST /update/{id} --}}
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog me-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" id="edit_broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Order Type</label>
                                <select name="order_type" id="edit_order_type" class="form-control" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Product</label>
                                <select name="product" id="edit_product" class="form-control" required>
                                    <option value="NRML">NRML</option>
                                    <option value="MIS">MIS</option>
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
                                <small class="text-muted">0 = place at LTP</small>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Status</label>
                                <select name="status" id="edit_status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" id="edit_signal_mode" class="form-control" required>
                                    <option value="align">↗ ALIGN (follow signal)</option>
                                    <option value="opposite">↔ OPPOSITE (reverse)</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size:12px; font-weight:600;">Option Series</label>
                                <select name="option_series" id="edit_option_series" class="form-control" required>
                                    <option value="current">Current (nearest expiry)</option>
                                    <option value="next">Next expiry</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-boxes me-1"></i> Base Quantities (Fallback)</p>
                    <small class="text-muted d-block mb-2" style="font-size:11px;">Used when rank qty = 0. Set 0 to rely on rank config only.</small>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label" style="font-size:12px;">CE lots (base)</label>
                                <input type="number" name="ce_quantity" id="edit_ce_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label" style="font-size:12px;">PE lots (base)</label>
                                <input type="number" name="pe_quantity" id="edit_pe_quantity" class="form-control" value="0" min="0" required />
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-layer-group me-1"></i> Rank-Based Quantities</p>

                    <div class="rank-card rank-1">
                        <div class="rank-title" style="color:#dc3545;"><span class="badge bg-danger me-1">Rank 1</span> Strongest — |CE%−PE%| > 40</div>
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label">CE lots</label>
                                <input type="number" name="rank1_ce_quantity" id="edit_rank1_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label">PE lots</label>
                                <input type="number" name="rank1_pe_quantity" id="edit_rank1_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                    <div class="rank-card rank-2">
                        <div class="rank-title" style="color:#fd7e14;"><span class="badge bg-warning me-1">Rank 2</span> Strong — diff > 25</div>
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label">CE lots</label>
                                <input type="number" name="rank2_ce_quantity" id="edit_rank2_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label">PE lots</label>
                                <input type="number" name="rank2_pe_quantity" id="edit_rank2_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                    <div class="rank-card rank-3">
                        <div class="rank-title" style="color:#007bff;"><span class="badge bg-primary me-1">Rank 3</span> Moderate — diff > 10</div>
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label">CE lots</label>
                                <input type="number" name="rank3_ce_quantity" id="edit_rank3_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label">PE lots</label>
                                <input type="number" name="rank3_pe_quantity" id="edit_rank3_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                    <div class="rank-card rank-4">
                        <div class="rank-title" style="color:#6c757d;"><span class="badge bg-secondary me-1">Rank 4</span> Weak — diff > 5</div>
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label">CE lots</label>
                                <input type="number" name="rank4_ce_quantity" id="edit_rank4_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 mb-0">
                                <label class="form-label">PE lots</label>
                                <input type="number" name="rank4_pe_quantity" id="edit_rank4_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                </div>{{-- end modal-body --}}
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
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
$(document).ready(function () {

    $(document).on('click', '.btn-edit-config', function () {
        const d = $(this).data();

        // Set form action URL (controller uses POST /update/{id})
        const url = '{{ route("mcx-oiiv.update", ":id") }}'.replace(':id', d.id);
        $('#editConfigForm').attr('action', url);

        // Broker & order fields
        $('#edit_broker_api_id').val(d.broker);
        $('#edit_order_type').val(d.order_type);
        $('#edit_product').val(d.product);
        $('#edit_disc_ltp').val(d.disc_ltp);
        $('#edit_status').val(d.status);
        $('#edit_signal_mode').val(d.signal_mode);
        $('#edit_option_series').val(d.option_series);

        // Base quantities
        $('#edit_ce_quantity').val(d.ce);
        $('#edit_pe_quantity').val(d.pe);

        // Rank quantities
        [1, 2, 3, 4].forEach(function (r) {
            $('#edit_rank' + r + '_ce_quantity').val(d['rank' + r + '_ce']);
            $('#edit_rank' + r + '_pe_quantity').val(d['rank' + r + '_pe']);
        });

        // Bootstrap 5 modal open
        const modal = new bootstrap.Modal(document.getElementById('editConfigModal'));
        modal.show();
    });

});
</script>
@endpush