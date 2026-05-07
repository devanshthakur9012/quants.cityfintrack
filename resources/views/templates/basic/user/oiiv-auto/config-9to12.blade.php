@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<style>
    .page-header {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 24px 28px;
        border-radius: 14px;
        margin-bottom: 24px;
        box-shadow: 0 6px 20px rgba(17,153,142,0.4);
    }
    .page-header h4 { font-size: 1.4rem; margin-bottom: 4px; font-weight: 700; }
    .page-header p  { font-size: 13px; margin-bottom: 0; opacity: .85; }

    .stats-box {
        background: #fff;
        padding: 16px;
        border-radius: 12px;
        text-align: center;
        border-left: 5px solid #11998e;
        box-shadow: 0 3px 12px rgba(0,0,0,.08);
        transition: transform .2s;
        margin-bottom: 20px;
    }
    .stats-box:hover { transform: translateY(-2px); }
    .stats-box small { display:block; color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.5px; }
    .stats-box strong { display:block; font-size:1.7rem; font-weight:700; margin-top:4px; }

    .custom--table thead th,
    .custom--table tbody td {
        vertical-align: middle;
        font-size: 12px;
        padding: 10px 10px !important;
    }

    .modal-header-gradient {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 18px 24px;
    }
    .modal-header-gradient .close { color: white; opacity: 1; font-size: 1.4rem; }
    .modal-content { border-radius: 14px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,.2); }

    .rank-card {
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 10px;
        border-left: 5px solid #ccc;
    }
    .rank-card.rank-1 { border-left-color: #dc3545; background: #fff5f5; }
    .rank-card.rank-2 { border-left-color: #fd7e14; background: #fff8f0; }
    .rank-card.rank-3 { border-left-color: #007bff; background: #f0f4ff; }
    .rank-card.rank-4 { border-left-color: #6c757d; background: #f8f9fa; }
    .rank-card label  { font-size: 12px; color: #555; margin-bottom: 4px; }
    .rank-card .rank-title { font-weight: 700; font-size: 13px; margin-bottom: 10px; }

    .status-active   { background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
    .status-inactive { background: #f8d7da; color: #721c24; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }

    .section-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #11998e;
        margin: 18px 0 10px;
        padding-bottom: 6px;
        border-bottom: 2px solid #11998e33;
    }

    .btn-action { padding: 5px 10px; font-size: 11px; border-radius: 6px; margin: 1px; }

    .form-section {
        background: #f0fffe;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 4px;
    }

    label { color: #000 !important; }
    .badge { color: #000000 !important; }

    .intraday-badge {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        margin-left: 6px;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- PAGE HEADER --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }} <span class="intraday-badge">9:15→12:15</span></h4>
                    <p>Rank-based CE/PE auto trading configurations for intraday morning session</p>
                </div>
                <div>
                    <a href="{{ route('9to12.pece-analysis') }}" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-chart-bar"></i> 9to12 Analysis
                    </a>
                    <a href="{{ route('oiiv-auto.config') }}" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-cog"></i> EOD Configs
                    </a>
                    {{-- FIX #5: was data-bs-toggle/data-bs-target (Bootstrap 5) → Bootstrap 4 --}}
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createConfigModal">
                        <i class="fas fa-plus"></i> New Config
                    </button>
                    {{-- Manual Run Button --}}
                    <form action="{{ route('9to12.run-signals') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm"
                            onclick="return confirm('Run 9to12 signal detection + order placement NOW?')"
                            title="Manually trigger signal detection and order placement">
                            <i class="fas fa-play-circle"></i> Run Signals Now
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- STATS --}}
        <div class="row mb-2">
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total 9to12 Configs</small>
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

        {{-- CONFIGS TABLE --}}
        <div style="border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.08); border:none; overflow:hidden;">
            <div class="table-responsive">
                <table class="table custom--table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Broker</th>
                            <th>Order</th>
                            <th>Series</th>
                            <th>Product</th>
                            <th>Signal Mode</th>
                            <th>Index Qty (CE/PE)</th>
                            <th>Stock Qty (CE/PE)</th>
                            <th>Rank 1 (CE/PE)</th>
                            <th>Rank 2 (CE/PE)</th>
                            <th>Rank 3 (CE/PE)</th>
                            <th>Rank 4 (CE/PE)</th>
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
                                <strong style="color:#11998e;">{{ $config->broker->client_name ?? 'N/A' }}</strong><br>
                                <small class="text-muted">{{ $config->order_type }}</small>
                            </td>
                            <td>
                                <span class="badge badge-{{ $config->order_type === 'MARKET' ? 'success' : 'info' }}">
                                    {{ $config->order_type }}
                                </span><br>
                                <small class="text-muted">Disc: {{ $config->disc_ltp }}%</small>
                            </td>

                            {{-- Series column --}}
                            <td>
                                @if(($config->option_series ?? 'current') === 'next')
                                    <span class="badge" style="background:linear-gradient(135deg,#667eea,#764ba2); color:white !important; font-size:10px; padding:4px 8px; border-radius:4px;">
                                        📅 NEXT
                                    </span>
                                @else
                                    <span class="badge" style="background:linear-gradient(135deg,#28a745,#20c997); color:white !important; font-size:10px; padding:4px 8px; border-radius:4px;">
                                        📅 CURRENT
                                    </span>
                                @endif
                            </td>

                            <td>
                                <span class="badge badge-{{ $config->product === 'NRML' ? 'primary' : 'warning' }}">
                                    {{ $config->product }}
                                </span>
                            </td>
                            <td>
                                @if($config->signal_mode === 'align')
                                    <span class="badge badge-success"><i class="fas fa-arrow-right"></i> ALIGN</span>
                                @else
                                    <span class="badge badge-danger"><i class="fas fa-exchange-alt"></i> OPPOSITE</span>
                                @endif
                            </td>
                            <td>
                                <small>CE: <strong>{{ $config->index_ce_quantity ?? 0 }}</strong></small><br>
                                <small>PE: <strong>{{ $config->index_pe_quantity ?? 0 }}</strong></small>
                            </td>
                            <td>
                                <small>CE: <strong>{{ $config->stock_ce_quantity ?? 0 }}</strong></small><br>
                                <small>PE: <strong>{{ $config->stock_pe_quantity ?? 0 }}</strong></small>
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
                                <a href="{{ route('9to12.orders', $config->id) }}" class="text-primary font-weight-bold">
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
                                {{-- FIX #3: Added data-option_series so JS can read and populate edit modal --}}
                                <button class="btn btn-info btn-action btn-edit-config"
                                    data-id="{{ $config->id }}"
                                    data-broker="{{ $config->broker_api_id }}"
                                    data-order_type="{{ $config->order_type }}"
                                    data-product="{{ $config->product }}"
                                    data-disc_ltp="{{ $config->disc_ltp }}"
                                    data-signal_mode="{{ $config->signal_mode }}"
                                    data-option_series="{{ $config->option_series ?? 'current' }}"
                                    data-status="{{ $config->status ? '1' : '0' }}"
                                    data-index_ce="{{ $config->index_ce_quantity ?? 0 }}"
                                    data-index_pe="{{ $config->index_pe_quantity ?? 0 }}"
                                    data-stock_ce="{{ $config->stock_ce_quantity ?? 0 }}"
                                    data-stock_pe="{{ $config->stock_pe_quantity ?? 0 }}"
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
                                <form action="{{ route('9to12.toggle', $config->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-{{ $config->status ? 'warning' : 'success' }} btn-action"
                                        title="{{ $config->status ? 'Deactivate' : 'Activate' }}"
                                        onclick="return confirm('{{ $config->status ? 'Deactivate' : 'Activate' }} this config?')">
                                        <i class="fas fa-{{ $config->status ? 'pause' : 'play' }}"></i>
                                    </button>
                                </form>

                                {{-- View Orders --}}
                                <a href="{{ route('9to12.orders', $config->id) }}" class="btn btn-primary btn-action" title="View Orders">
                                    <i class="fas fa-list"></i>
                                </a>

                                {{-- Delete --}}
                                <form action="{{ route('9to12.destroy', $config->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-action" title="Delete"
                                        onclick="return confirm('Delete this configuration?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="15" class="text-center py-5">
                                <i class="fas fa-cog" style="font-size:2.5rem; opacity:.3;"></i>
                                <p class="mt-3 text-muted">No 9to12 configurations yet. <a href="#" data-toggle="modal" data-target="#createConfigModal">Create one</a>.</p>
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

{{-- ══════════════════════════════════════════════════════════════ --}}
{{--  CREATE MODAL                                                  --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="createConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-plus-circle mr-2"></i>New 9to12 Configuration</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('9to12.store') }}" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog mr-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            {{-- Broker --}}
                            <div class="col-md-6 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">Broker Account <span class="text-danger">*</span></label>
                                <select name="broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- FIX #1: Was bare <div class="form-group"> — now wrapped in col-md-6 to sit
                                 correctly next to the broker field in the same row --}}
                            <div class="col-md-6 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">
                                    Option Series <span class="text-danger">*</span>
                                    <small class="text-muted d-block" style="font-size:10px; margin-top:2px; font-weight:400;">
                                        Current: JAN FUT → JAN CE/PE &nbsp;|&nbsp; Next: JAN FUT → FEB CE/PE
                                    </small>
                                </label>
                                <select name="option_series" id="create_option_series" class="form-control" required>
                                    <option value="current" {{ (old('option_series', 'current') === 'current') ? 'selected' : '' }}>
                                        📅 Current Series — Same expiry as FUT
                                    </option>
                                    <option value="next" {{ (old('option_series') === 'next') ? 'selected' : '' }}>
                                        📅 Next Series — Skip to next monthly expiry
                                    </option>
                                </select>
                                <small class="text-muted" style="font-size:10px;">
                                    <strong>Current:</strong> Nearest expiry &nbsp; <strong>Next:</strong> Following month (useful near expiry week)
                                </small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">Order Type</label>
                                <select name="order_type" class="form-control" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">Product</label>
                                <select name="product" class="form-control" required>
                                    <option value="MIS">MIS</option>
                                    <option value="NRML">NRML</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" class="form-control" required>
                                    <option value="align">↗ ALIGN (follow signal)</option>
                                    <option value="opposite">↔ OPPOSITE (reverse signal)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-4 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Discount % (LIMIT orders)</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                                <small class="text-muted">0 = place at LTP</small>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-boxes mr-1"></i> Base Quantities (Index & Stock)</p>
                    <small class="text-muted d-block mb-2" style="font-size:11px;">Fallback quantities. Set to 0 if using rank quantities only.</small>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px;">Index CE (lots)</label>
                                <input type="number" name="index_ce_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px;">Index PE (lots)</label>
                                <input type="number" name="index_pe_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px;">Stock CE (lots)</label>
                                <input type="number" name="stock_ce_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px;">Stock PE (lots)</label>
                                <input type="number" name="stock_pe_quantity" class="form-control" value="0" min="0" required />
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-layer-group mr-1"></i> Rank-Based Quantities</p>

                    <div class="rank-card rank-1">
                        <div class="rank-title" style="color:#dc3545;"><span class="badge badge-danger mr-1">Rank 1</span> Strongest — diff &gt; 40</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label>CE Quantity (lots)</label>
                                <input type="number" name="rank1_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label>PE Quantity (lots)</label>
                                <input type="number" name="rank1_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>
                    <div class="rank-card rank-2">
                        <div class="rank-title" style="color:#fd7e14;"><span class="badge badge-warning mr-1">Rank 2</span> Strong — diff &gt; 25</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label>CE Quantity (lots)</label>
                                <input type="number" name="rank2_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label>PE Quantity (lots)</label>
                                <input type="number" name="rank2_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>
                    <div class="rank-card rank-3">
                        <div class="rank-title" style="color:#007bff;"><span class="badge badge-primary mr-1">Rank 3</span> Moderate — diff &gt; 10</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label>CE Quantity (lots)</label>
                                <input type="number" name="rank3_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label>PE Quantity (lots)</label>
                                <input type="number" name="rank3_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>
                    <div class="rank-card rank-4">
                        <div class="rank-title" style="color:#6c757d;"><span class="badge badge-secondary mr-1">Rank 4</span> Weak — diff &gt; 5</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label>CE Quantity (lots)</label>
                                <input type="number" name="rank4_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label>PE Quantity (lots)</label>
                                <input type="number" name="rank4_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save mr-1"></i> Create 9to12 Config
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{--  EDIT MODAL                                                    --}}
{{--  FIX #2: Removed {{ $config->option_series }} Blade binding   --}}
{{--  ($config is undefined here — $configs is a paginator).       --}}
{{--  option_series is now set ONLY via JS from data-option_series. --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="editConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit mr-2"></i>Edit 9to12 Configuration</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editConfigForm" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog mr-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">Broker Account <span class="text-danger">*</span></label>
                                <select name="broker_api_id" id="edit_broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- FIX #2: Plain <select> with no Blade binding — value set by JS --}}
                            <div class="col-md-6 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">
                                    Option Series <span class="text-danger">*</span>
                                    <small class="text-muted d-block" style="font-size:10px; margin-top:2px; font-weight:400;">
                                        Current: JAN FUT → JAN CE/PE &nbsp;|&nbsp; Next: JAN FUT → FEB CE/PE
                                    </small>
                                </label>
                                <select name="option_series" id="edit_option_series" class="form-control" required>
                                    <option value="current">📅 Current Series — Same expiry as FUT</option>
                                    <option value="next">📅 Next Series — Skip to next monthly expiry</option>
                                </select>
                                <small class="text-muted" style="font-size:10px;">
                                    <strong>Current:</strong> Nearest expiry &nbsp; <strong>Next:</strong> Following month
                                </small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">Order Type</label>
                                <select name="order_type" id="edit_order_type" class="form-control" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">Product</label>
                                <select name="product" id="edit_product" class="form-control" required>
                                    <option value="MIS">MIS</option>
                                    <option value="NRML">NRML</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Status</label>
                                <select name="status" id="edit_status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" id="edit_signal_mode" class="form-control" required>
                                    <option value="align">↗ ALIGN (follow signal)</option>
                                    <option value="opposite">↔ OPPOSITE (reverse signal)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-4 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Discount %</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" id="edit_disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-boxes mr-1"></i> Base Quantities</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px;">Index CE (lots)</label>
                                <input type="number" name="index_ce_quantity" id="edit_index_ce_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px;">Index PE (lots)</label>
                                <input type="number" name="index_pe_quantity" id="edit_index_pe_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px;">Stock CE (lots)</label>
                                <input type="number" name="stock_ce_quantity" id="edit_stock_ce_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3 form-group mb-0">
                                <label style="font-size:12px;">Stock PE (lots)</label>
                                <input type="number" name="stock_pe_quantity" id="edit_stock_pe_quantity" class="form-control" value="0" min="0" required />
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-layer-group mr-1"></i> Rank-Based Quantities</p>

                    <div class="rank-card rank-1">
                        <div class="rank-title" style="color:#dc3545;"><span class="badge badge-danger mr-1">Rank 1</span> Strongest — diff &gt; 40</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label>CE Quantity (lots)</label>
                                <input type="number" name="rank1_ce_quantity" id="edit_rank1_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label>PE Quantity (lots)</label>
                                <input type="number" name="rank1_pe_quantity" id="edit_rank1_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>
                    <div class="rank-card rank-2">
                        <div class="rank-title" style="color:#fd7e14;"><span class="badge badge-warning mr-1">Rank 2</span> Strong — diff &gt; 25</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label>CE Quantity (lots)</label>
                                <input type="number" name="rank2_ce_quantity" id="edit_rank2_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label>PE Quantity (lots)</label>
                                <input type="number" name="rank2_pe_quantity" id="edit_rank2_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>
                    <div class="rank-card rank-3">
                        <div class="rank-title" style="color:#007bff;"><span class="badge badge-primary mr-1">Rank 3</span> Moderate — diff &gt; 10</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label>CE Quantity (lots)</label>
                                <input type="number" name="rank3_ce_quantity" id="edit_rank3_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label>PE Quantity (lots)</label>
                                <input type="number" name="rank3_pe_quantity" id="edit_rank3_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>
                    <div class="rank-card rank-4">
                        <div class="rank-title" style="color:#6c757d;"><span class="badge badge-secondary mr-1">Rank 4</span> Weak — diff &gt; 5</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label>CE Quantity (lots)</label>
                                <input type="number" name="rank4_ce_quantity" id="edit_rank4_ce_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label>PE Quantity (lots)</label>
                                <input type="number" name="rank4_pe_quantity" id="edit_rank4_pe_quantity" class="form-control form-control-sm" value="0" min="0" />
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">
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
    $(document).on('click', '.btn-edit-config', function () {
        const d = $(this).data();

        const url = '{{ route("9to12.update", ":id") }}'.replace(':id', d.id);
        $('#editConfigForm').attr('action', url);

        // Broker & basic settings
        $('#edit_broker_api_id').val(d.broker);
        $('#edit_order_type').val(d.order_type);
        $('#edit_product').val(d.product);
        $('#edit_disc_ltp').val(d.disc_ltp);
        $('#edit_status').val(d.status);
        $('#edit_signal_mode').val(d.signal_mode);

        // FIX #4: Populate option_series from data-option_series on the button
        $('#edit_option_series').val(d.option_series || 'current');

        // Base quantities
        $('#edit_index_ce_quantity').val(d.index_ce);
        $('#edit_index_pe_quantity').val(d.index_pe);
        $('#edit_stock_ce_quantity').val(d.stock_ce);
        $('#edit_stock_pe_quantity').val(d.stock_pe);

        // Rank quantities
        [1, 2, 3, 4].forEach(function (r) {
            $('#edit_rank' + r + '_ce_quantity').val(d['rank' + r + '_ce']);
            $('#edit_rank' + r + '_pe_quantity').val(d['rank' + r + '_pe']);
        });

        $('#editConfigModal').modal('show');
    });
});
</script>
@endpush