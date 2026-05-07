@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ===== PAGE HEADER ===== */
    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 24px 28px;
        border-radius: 14px;
        margin-bottom: 24px;
        box-shadow: 0 6px 20px rgba(102,126,234,0.4);
    }
    .page-header h4 { font-size: 1.4rem; margin-bottom: 4px; font-weight: 700; }
    .page-header p  { font-size: 13px; margin-bottom: 0; opacity: .85; }

    /* ===== STATS BOXES ===== */
    .stats-box {
        background: #fff;
        padding: 16px;
        border-radius: 12px;
        text-align: center;
        border-left: 5px solid #667eea;
        box-shadow: 0 3px 12px rgba(0,0,0,.08);
        transition: transform .2s;
        margin-bottom: 20px;
    }
    .stats-box:hover { transform: translateY(-2px); }
    .stats-box small  { display:block; color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.5px; }
    .stats-box strong { display:block; font-size:1.7rem; font-weight:700; margin-top:4px; }

    /* ===== TABLE ===== */
    .custom--table thead th,
    .custom--table tbody td {
        vertical-align: middle;
        font-size: 12px;
        padding: 10px 10px !important;
    }

    /* ===== MODAL ===== */
    .modal-header-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 18px 24px;
    }
    .modal-header-gradient .close { color: white; opacity: 1; font-size: 1.4rem; }
    .modal-content { border-radius: 14px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,.2); }

    /* ===== RANK CARD ===== */
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

    /* ===== SIGNAL MODE ===== */
    .signal-mode-btn { cursor: pointer; }
    .signal-mode-btn input { display: none; }
    .signal-mode-btn .mode-box {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 10px 16px;
        text-align: center;
        transition: all .2s;
        font-size: 12px;
    }
    .signal-mode-btn input:checked + .mode-box {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea15, #764ba215);
        color: #667eea;
        font-weight: 700;
    }

    /* ===== STATUS BADGE ===== */
    .status-active   { background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
    .status-inactive { background: #f8d7da; color: #721c24; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }

    /* ===== SECTION DIVIDER ===== */
    .section-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #667eea;
        margin: 18px 0 10px;
        padding-bottom: 6px;
        border-bottom: 2px solid #667eea33;
    }

    /* ===== ACTION BUTTONS ===== */
    .btn-action { padding: 5px 10px; font-size: 11px; border-radius: 6px; margin: 1px; }

    .form-section {
        background: #f8f9ff;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 4px;
    }

    label { color:#000 !important; }
    .badge { color: #000000 !important; }

    /* ===== SYMBOL PICKER ===== */
    .symbol-picker-wrap {
        border: 1.5px solid #dee2e6;
        border-radius: 10px;
        background: #fff;
        overflow: hidden;
    }
    .symbol-picker-search {
        padding: 8px 12px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f8f9ff;
    }
    .symbol-picker-search input {
        border: none;
        background: transparent;
        outline: none;
        font-size: 12px;
        flex: 1;
    }
    .symbol-picker-search .sp-count {
        font-size: 11px;
        font-weight: 700;
        color: #667eea;
        white-space: nowrap;
    }
    .symbol-picker-actions {
        display: flex;
        gap: 6px;
        padding: 6px 12px;
        border-bottom: 1px solid #f0f0f0;
        flex-wrap: wrap;
        background: #fff;
    }
    .sp-action-btn {
        font-size: 10px;
        padding: 3px 10px;
        border-radius: 20px;
        border: 1px solid #dee2e6;
        background: white;
        cursor: pointer;
        font-weight: 600;
        transition: all .15s;
        color: #555;
    }
    .sp-action-btn:hover { background: #667eea; color: white; border-color: #667eea; }
    .sp-action-btn.active-filter { background: #667eea; color: white; border-color: #667eea; }

    .symbol-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 10px 12px;
        max-height: 220px;
        overflow-y: auto;
    }
    .symbol-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        border: 1.5px solid #dee2e6;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all .15s;
        background: white;
        color: #555;
        user-select: none;
    }
    .symbol-chip:hover { border-color: #667eea; color: #667eea; background: #f0f4ff; }
    .symbol-chip.selected {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white !important;
        border-color: #667eea;
    }
    .symbol-chip.is-index { border-color: #28a745; color: #28a745; }
    .symbol-chip.is-index.selected { background: linear-gradient(135deg, #28a745, #20c997); border-color: #28a745; }
    .symbol-chip .chip-dot { width:6px; height:6px; border-radius:50%; background:currentColor; flex-shrink:0; }

    .symbol-picker-footer {
        padding: 8px 12px;
        border-top: 1px solid #f0f0f0;
        background: #f8f9ff;
        font-size: 11px;
        color: #888;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .selected-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 8px;
        min-height: 24px;
    }
    .selected-tag {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .selected-tag .remove-tag {
        cursor: pointer;
        opacity: .7;
        font-size: 12px;
        line-height: 1;
    }
    .selected-tag .remove-tag:hover { opacity: 1; }

    /* ===== SYMBOL FILTER BADGE in table ===== */
    .sym-filter-badge {
        background: #e8f5e9;
        color: #1b5e20;
        border: 1px solid #c8e6c9;
        padding: 2px 7px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        display: inline-block;
    }
    .sym-all-badge {
        background: #e3f2fd;
        color: #0d47a1;
        border: 1px solid #bbdefb;
        padding: 2px 7px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        display: inline-block;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">

        {{-- PAGE HEADER --}}
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }}</h4>
                    <p>Manage rank-based CE/PE auto trading configurations</p>
                </div>
                <div>
                    <a href="{{ route('oiiv-auto.index') }}" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-chart-line"></i> OI+IV
                    </a>
                    <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-chart-bar"></i> PE/CE Analysis
                    </a>
                    <button class="btn btn-light btn-sm mr-2" data-bs-toggle="modal" data-bs-target="#createConfigModal">
                        <i class="fas fa-plus"></i> New Config
                    </button>
                    <form action="{{ route('oiiv-auto.run-signals') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm"
                            onclick="return confirm('Run EOD signal detection + order placement NOW?')">
                            <i class="fas fa-play-circle"></i> Run EOD Signals Now
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- STATS --}}
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

        {{-- CONFIGS TABLE --}}
        <div style="border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.08); background:white;">
            <div class="table-responsive">
                <table class="table custom--table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Broker</th>
                            <th>Order</th>
                            <th>Product</th>
                            <th>Signal Mode</th>
                            <th>Symbols</th>
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
                                <strong style="color:#667eea;">{{ $config->broker->client_name ?? 'N/A' }}</strong><br>
                                <small class="text-muted">{{ $config->order_type }}</small>
                            </td>
                            <td>
                                <span class="badge badge-{{ $config->order_type === 'MARKET' ? 'success' : 'info' }}">
                                    {{ $config->order_type }}
                                </span><br>
                                <small class="text-muted">Disc: {{ $config->disc_ltp }}%</small>
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

                            {{-- SYMBOL FILTER COLUMN (NEW) --}}
                            <td>
                                @if($config->allowed_symbols === null)
                                    <span class="sym-all-badge">🌐 All</span>
                                @elseif(count($config->allowed_symbols) === 0)
                                    <span style="color:#dc3545;font-size:11px;font-weight:700;">⛔ None</span>
                                @else
                                    <span class="sym-filter-badge" title="{{ implode(', ', $config->allowed_symbols) }}">
                                        🎯 {{ count($config->allowed_symbols) }} symbols
                                    </span>
                                    <div style="margin-top:3px;max-width:130px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                                        <small class="text-muted" style="font-size:10px;">
                                            {{ implode(', ', array_slice($config->allowed_symbols, 0, 3)) }}{{ count($config->allowed_symbols) > 3 ? '...' : '' }}
                                        </small>
                                    </div>
                                @endif
                            </td>

                            <td>
                                <small>CE: <strong>{{ $config->index_ce_quantity ?? $config->index_quantity ?? 0 }}</strong></small><br>
                                <small>PE: <strong>{{ $config->index_pe_quantity ?? $config->index_quantity ?? 0 }}</strong></small>
                            </td>
                            <td>
                                <small>CE: <strong>{{ $config->stock_ce_quantity ?? $config->stock_quantity ?? 0 }}</strong></small><br>
                                <small>PE: <strong>{{ $config->stock_pe_quantity ?? $config->stock_quantity ?? 0 }}</strong></small>
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
                                <a href="{{ route('oiiv-auto.orders', $config->id) }}" class="text-primary font-weight-bold">
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
                                    data-index_ce="{{ $config->index_ce_quantity ?? $config->index_quantity ?? 0 }}"
                                    data-index_pe="{{ $config->index_pe_quantity ?? $config->index_quantity ?? 0 }}"
                                    data-stock_ce="{{ $config->stock_ce_quantity ?? $config->stock_quantity ?? 0 }}"
                                    data-stock_pe="{{ $config->stock_pe_quantity ?? $config->stock_quantity ?? 0 }}"
                                    data-rank1_ce="{{ $config->rank1_ce_quantity ?? 0 }}"
                                    data-rank1_pe="{{ $config->rank1_pe_quantity ?? 0 }}"
                                    data-rank2_ce="{{ $config->rank2_ce_quantity ?? 0 }}"
                                    data-rank2_pe="{{ $config->rank2_pe_quantity ?? 0 }}"
                                    data-rank3_ce="{{ $config->rank3_ce_quantity ?? 0 }}"
                                    data-rank3_pe="{{ $config->rank3_pe_quantity ?? 0 }}"
                                    data-rank4_ce="{{ $config->rank4_ce_quantity ?? 0 }}"
                                    data-rank4_pe="{{ $config->rank4_pe_quantity ?? 0 }}"
                                    data-allowed_symbols="{{ json_encode($config->allowed_symbols) }}"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>

                                {{-- Toggle Status --}}
                                <form action="{{ route('oiiv-auto.toggle', $config->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-{{ $config->status ? 'warning' : 'success' }} btn-action"
                                        title="{{ $config->status ? 'Deactivate' : 'Activate' }}"
                                        onclick="return confirm('{{ $config->status ? 'Deactivate' : 'Activate' }} this config?')">
                                        <i class="fas fa-{{ $config->status ? 'pause' : 'play' }}"></i>
                                    </button>
                                </form>

                                {{-- View Orders --}}
                                <a href="{{ route('oiiv-auto.orders', $config->id) }}" class="btn btn-primary btn-action" title="View Orders">
                                    <i class="fas fa-list"></i>
                                </a>

                                {{-- Delete --}}
                                <form action="{{ route('oiiv-auto.destroy', $config->id) }}" method="POST" style="display:inline;">
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
                                <p class="mt-3 text-muted">No configurations yet. <a href="#" data-bs-toggle="modal" data-bs-target="#createConfigModal">Create one</a>.</p>
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
                <h5 class="mb-0"><i class="fas fa-plus-circle mr-2"></i>New Configuration</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('oiiv-auto.store') }}" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">

                    {{-- BROKER & ORDER SETTINGS --}}
                    <p class="section-label"><i class="fas fa-cog mr-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>
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
                                    <option value="NRML">NRML</option>
                                    <option value="MIS">MIS</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Discount % (LIMIT orders)</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                                <small class="text-muted">0 = place at LTP</small>
                            </div>
                            <div class="col-md-4 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" class="form-control" required>
                                    <option value="align">↗ ALIGN (follow signal)</option>
                                    <option value="opposite">↔ OPPOSITE (reverse signal)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- SYMBOL FILTER --}}
                    <p class="section-label"><i class="fas fa-filter mr-1"></i> Symbol Filter <small style="font-weight:400;color:#888;text-transform:none;letter-spacing:0;">(leave empty = trade ALL symbols)</small></p>
                    <div class="form-section" style="padding-bottom:12px;">
                        <div class="symbol-picker-wrap" id="createSymbolPicker">
                            <div class="symbol-picker-search">
                                <i class="fas fa-search" style="color:#aaa;font-size:12px;"></i>
                                <input type="text" placeholder="Search symbols..." class="sp-search-input" oninput="filterChips('create', this.value)">
                                <span class="sp-count" id="createSelectedCount">0 selected</span>
                            </div>
                            <div class="symbol-picker-actions">
                                <button type="button" class="sp-action-btn" onclick="selectAll('create')">Select All</button>
                                <button type="button" class="sp-action-btn" onclick="clearAll('create')">Clear All</button>
                                <button type="button" class="sp-action-btn active-filter" onclick="filterCategory('create','all',this)">All</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('create','index',this)">Indices</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('create','nifty50',this)">Nifty50</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('create','banknifty',this)">BankNifty</button>
                            </div>
                            <div class="symbol-grid" id="createSymbolGrid">
                                <div class="text-muted" style="font-size:12px;padding:8px;">Loading symbols...</div>
                            </div>
                            <div class="symbol-picker-footer">
                                <span>💡 Empty = trade all &nbsp;|&nbsp; <span style="color:#28a745;font-weight:700;">Green = Index</span></span>
                                <span id="createFooterCount" style="font-weight:600;color:#667eea;">0 selected</span>
                            </div>
                        </div>
                        {{-- Hidden inputs injected by JS --}}
                        <div id="createHiddenInputs"></div>
                        {{-- Preview tags --}}
                        <div class="selected-preview mt-2" id="createSelectedPreview"></div>
                    </div>

                    {{-- BASE QUANTITIES --}}
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

                    {{-- RANK QUANTITIES --}}
                    <p class="section-label"><i class="fas fa-layer-group mr-1"></i> Rank-Based Quantities</p>

                    <div class="rank-card rank-1">
                        <div class="rank-title" style="color:#dc3545;"><span class="badge badge-danger mr-1">Rank 1</span> Strongest — diff &gt; 40</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0"><label>CE Quantity (lots)</label><input type="number" name="rank1_ce_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                            <div class="col-md-6 form-group mb-0"><label>PE Quantity (lots)</label><input type="number" name="rank1_pe_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                        </div>
                    </div>
                    <div class="rank-card rank-2">
                        <div class="rank-title" style="color:#fd7e14;"><span class="badge badge-warning mr-1">Rank 2</span> Strong — diff &gt; 25</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0"><label>CE Quantity (lots)</label><input type="number" name="rank2_ce_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                            <div class="col-md-6 form-group mb-0"><label>PE Quantity (lots)</label><input type="number" name="rank2_pe_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                        </div>
                    </div>
                    <div class="rank-card rank-3">
                        <div class="rank-title" style="color:#007bff;"><span class="badge badge-primary mr-1">Rank 3</span> Moderate — diff &gt; 10</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0"><label>CE Quantity (lots)</label><input type="number" name="rank3_ce_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                            <div class="col-md-6 form-group mb-0"><label>PE Quantity (lots)</label><input type="number" name="rank3_pe_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                        </div>
                    </div>
                    <div class="rank-card rank-4">
                        <div class="rank-title" style="color:#6c757d;"><span class="badge badge-secondary mr-1">Rank 4</span> Weak — diff &gt; 5</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0"><label>CE Quantity (lots)</label><input type="number" name="rank4_ce_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                            <div class="col-md-6 form-group mb-0"><label>PE Quantity (lots)</label><input type="number" name="rank4_pe_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Create Config</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{--  EDIT MODAL                                                    --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="editConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit mr-2"></i>Edit Configuration</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editConfigForm" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body px-4 py-3">

                    {{-- BROKER & ORDER SETTINGS --}}
                    <p class="section-label"><i class="fas fa-cog mr-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label style="font-size:12px; font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" id="edit_broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }}</option>
                                    @endforeach
                                </select>
                            </div>
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
                                    <option value="NRML">NRML</option>
                                    <option value="MIS">MIS</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Discount % (LIMIT orders)</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" id="edit_disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                            </div>
                            <div class="col-md-4 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Status</label>
                                <select name="status" id="edit_status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group mb-0">
                                <label style="font-size:12px; font-weight:600;">Signal Mode</label>
                                <select name="signal_mode" id="edit_signal_mode" class="form-control" required>
                                    <option value="align">↗ ALIGN (follow signal)</option>
                                    <option value="opposite">↔ OPPOSITE (reverse signal)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- SYMBOL FILTER --}}
                    <p class="section-label"><i class="fas fa-filter mr-1"></i> Symbol Filter <small style="font-weight:400;color:#888;text-transform:none;letter-spacing:0;">(leave empty = trade ALL symbols)</small></p>
                    <div class="form-section" style="padding-bottom:12px;">
                        <div class="symbol-picker-wrap" id="editSymbolPicker">
                            <div class="symbol-picker-search">
                                <i class="fas fa-search" style="color:#aaa;font-size:12px;"></i>
                                <input type="text" placeholder="Search symbols..." class="sp-search-input" oninput="filterChips('edit', this.value)">
                                <span class="sp-count" id="editSelectedCount">0 selected</span>
                            </div>
                            <div class="symbol-picker-actions">
                                <button type="button" class="sp-action-btn" onclick="selectAll('edit')">Select All</button>
                                <button type="button" class="sp-action-btn" onclick="clearAll('edit')">Clear All</button>
                                <button type="button" class="sp-action-btn active-filter" onclick="filterCategory('edit','all',this)">All</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('edit','index',this)">Indices</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('edit','nifty50',this)">Nifty50</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('edit','banknifty',this)">BankNifty</button>
                            </div>
                            <div class="symbol-grid" id="editSymbolGrid">
                                <div class="text-muted" style="font-size:12px;padding:8px;">Loading symbols...</div>
                            </div>
                            <div class="symbol-picker-footer">
                                <span>💡 Empty = trade all &nbsp;|&nbsp; <span style="color:#28a745;font-weight:700;">Green = Index</span></span>
                                <span id="editFooterCount" style="font-weight:600;color:#667eea;">0 selected</span>
                            </div>
                        </div>
                        <div id="editHiddenInputs"></div>
                        <div class="selected-preview mt-2" id="editSelectedPreview"></div>
                    </div>

                    {{-- BASE QUANTITIES --}}
                    <p class="section-label"><i class="fas fa-boxes mr-1"></i> Base Quantities (Index & Stock)</p>
                    <small class="text-muted d-block mb-2" style="font-size:11px;">Fallback quantities. Set to 0 if using rank quantities only.</small>
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

                    {{-- RANK QUANTITIES --}}
                    <p class="section-label"><i class="fas fa-layer-group mr-1"></i> Rank-Based Quantities</p>
                    <div class="rank-card rank-1">
                        <div class="rank-title" style="color:#dc3545;"><span class="badge badge-danger mr-1">Rank 1</span> Strongest — diff &gt; 40</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0"><label>CE Quantity (lots)</label><input type="number" name="rank1_ce_quantity" id="edit_rank1_ce_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                            <div class="col-md-6 form-group mb-0"><label>PE Quantity (lots)</label><input type="number" name="rank1_pe_quantity" id="edit_rank1_pe_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                        </div>
                    </div>
                    <div class="rank-card rank-2">
                        <div class="rank-title" style="color:#fd7e14;"><span class="badge badge-warning mr-1">Rank 2</span> Strong — diff &gt; 25</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0"><label>CE Quantity (lots)</label><input type="number" name="rank2_ce_quantity" id="edit_rank2_ce_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                            <div class="col-md-6 form-group mb-0"><label>PE Quantity (lots)</label><input type="number" name="rank2_pe_quantity" id="edit_rank2_pe_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                        </div>
                    </div>
                    <div class="rank-card rank-3">
                        <div class="rank-title" style="color:#007bff;"><span class="badge badge-primary mr-1">Rank 3</span> Moderate — diff &gt; 10</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0"><label>CE Quantity (lots)</label><input type="number" name="rank3_ce_quantity" id="edit_rank3_ce_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                            <div class="col-md-6 form-group mb-0"><label>PE Quantity (lots)</label><input type="number" name="rank3_pe_quantity" id="edit_rank3_pe_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                        </div>
                    </div>
                    <div class="rank-card rank-4">
                        <div class="rank-title" style="color:#6c757d;"><span class="badge badge-secondary mr-1">Rank 4</span> Weak — diff &gt; 5</div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0"><label>CE Quantity (lots)</label><input type="number" name="rank4_ce_quantity" id="edit_rank4_ce_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                            <div class="col-md-6 form-group mb-0"><label>PE Quantity (lots)</label><input type="number" name="rank4_pe_quantity" id="edit_rank4_pe_quantity" class="form-control form-control-sm" value="0" min="0" /></div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Update Config</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
// ─────────────────────────────────────────────────────────────────────────────
//  SYMBOL PICKER ENGINE
// ─────────────────────────────────────────────────────────────────────────────

var SP = {
    allSymbols: [],   // [{symbol, isIndex, isNifty50, isBankNifty}]
    loaded: false,

    INDEX_SYMBOLS: ['NIFTY','BANKNIFTY','FINNIFTY','MIDCPNIFTY','SENSEX','BANKEX'],

    NIFTY50: ['ADANIENT','ADANIPORTS','APOLLOHOSP','ASIANPAINT','AXISBANK',
              'BAJAJ-AUTO','BAJAJFINSV','BAJFINANCE','BEL','BHARTIARTL',
              'CIPLA','COALINDIA','DRREDDY','EICHERMOT','ETERNAL',
              'GRASIM','HCLTECH','HDFCBANK','HDFCLIFE','HINDALCO',
              'HINDUNILVR','ICICIBANK','INDIGO','INFY','ITC',
              'JIOFIN','JSWSTEEL','KOTAKBANK','LT','M&M',
              'MARUTI','MAXHEALTH','NESTLEIND','NTPC','ONGC',
              'POWERGRID','RELIANCE','SBILIFE','SBIN','SHRIRAMFIN',
              'SUNPHARMA','TATACONSUM','TATASTEEL','TCS','TECHM',
              'TITAN','TMPV','TRENT','ULTRACEMCO','WIPRO'],

    BANKNIFTY: ['HDFCBANK','ICICIBANK','AXISBANK','SBIN','KOTAKBANK',
                'FEDERALBNK','INDUSINDBK','AUBANK','BANKBARODA','CANBK'],

    // Selected state per context
    selected: { create: new Set(), edit: new Set() },
    // Current search/category filter per context
    currentFilter: { create: 'all', edit: 'all' },
    currentSearch:  { create: '', edit: '' },
};

// ── Load symbols from server once ─────────────────────────────────────────
function loadSymbolsOnce(callback) {
    if (SP.loaded && SP.allSymbols.length > 0) { callback && callback(); return; }

    $.get('{{ route("oiiv-auto.symbols") }}', function(res) {
        if (!res.success) return;
        SP.allSymbols = (res.symbols || []).map(function(sym) {
            sym = sym.toString().toUpperCase();
            return {
                symbol:       sym,
                isIndex:      SP.INDEX_SYMBOLS.includes(sym),
                isNifty50:    SP.NIFTY50.includes(sym),
                isBankNifty:  SP.BANKNIFTY.includes(sym),
            };
        });
        SP.loaded = true;
        callback && callback();
    });
}

// ── Render chips for a context (create or edit) ───────────────────────────
function renderChips(ctx) {
    var grid    = document.getElementById(ctx + 'SymbolGrid');
    var search  = SP.currentSearch[ctx].toLowerCase();
    var cat     = SP.currentFilter[ctx];

    var filtered = SP.allSymbols.filter(function(s) {
        var matchSearch = !search || s.symbol.toLowerCase().includes(search);
        var matchCat    = cat === 'all'       ? true
                        : cat === 'index'     ? s.isIndex
                        : cat === 'nifty50'   ? s.isNifty50
                        : cat === 'banknifty' ? s.isBankNifty
                        : true;
        return matchSearch && matchCat;
    });

    var html = '';
    filtered.forEach(function(s) {
        var sel = SP.selected[ctx].has(s.symbol);
        var idxCls = s.isIndex ? ' is-index' : '';
        var selCls = sel       ? ' selected' : '';
        html += '<span class="symbol-chip' + idxCls + selCls + '" onclick="toggleChip(\'' + ctx + '\',\'' + s.symbol + '\')">' +
                    '<span class="chip-dot"></span>' + s.symbol +
                '</span>';
    });

    grid.innerHTML = html || '<div class="text-muted" style="font-size:12px;padding:8px;">No symbols match</div>';
    updateCounts(ctx);
}

// ── Toggle a chip ─────────────────────────────────────────────────────────
function toggleChip(ctx, sym) {
    if (SP.selected[ctx].has(sym)) {
        SP.selected[ctx].delete(sym);
    } else {
        SP.selected[ctx].add(sym);
    }
    renderChips(ctx);
    renderHiddenInputs(ctx);
    renderPreviewTags(ctx);
}

// ── Select / clear all ────────────────────────────────────────────────────
function selectAll(ctx) {
    SP.allSymbols.forEach(function(s) { SP.selected[ctx].add(s.symbol); });
    renderChips(ctx);
    renderHiddenInputs(ctx);
    renderPreviewTags(ctx);
}

function clearAll(ctx) {
    SP.selected[ctx].clear();
    renderChips(ctx);
    renderHiddenInputs(ctx);
    renderPreviewTags(ctx);
}

// ── Category filter ───────────────────────────────────────────────────────
function filterCategory(ctx, cat, btn) {
    SP.currentFilter[ctx] = cat;
    // Update active button
    var wrap = document.getElementById(ctx + 'SymbolPicker');
    wrap.querySelectorAll('.sp-action-btn').forEach(function(b) { b.classList.remove('active-filter'); });
    btn.classList.add('active-filter');
    renderChips(ctx);
}

// ── Search filter ─────────────────────────────────────────────────────────
function filterChips(ctx, val) {
    SP.currentSearch[ctx] = val;
    renderChips(ctx);
}

// ── Update count badges ───────────────────────────────────────────────────
function updateCounts(ctx) {
    var n = SP.selected[ctx].size;
    document.getElementById(ctx + 'SelectedCount').textContent = n + ' selected';
    document.getElementById(ctx + 'FooterCount').textContent   = n + ' selected';
}

// ── Inject hidden inputs so form submits correctly ────────────────────────
function renderHiddenInputs(ctx) {
    var wrap = document.getElementById(ctx + 'HiddenInputs');
    wrap.innerHTML = '';
    SP.selected[ctx].forEach(function(sym) {
        var inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'allowed_symbols[]';
        inp.value = sym;
        wrap.appendChild(inp);
    });
}

// ── Preview tags below the picker ─────────────────────────────────────────
function renderPreviewTags(ctx) {
    var wrap  = document.getElementById(ctx + 'SelectedPreview');
    var syms  = Array.from(SP.selected[ctx]).sort();
    if (syms.length === 0) {
        wrap.innerHTML = '<small class="text-muted" style="font-size:11px;">No filter — all symbols will be traded</small>';
        return;
    }
    wrap.innerHTML = syms.map(function(s) {
        return '<span class="selected-tag">' + s +
               '<span class="remove-tag" onclick="toggleChip(\'' + ctx + '\',\'' + s + '\')">×</span></span>';
    }).join('');
}

// ─────────────────────────────────────────────────────────────────────────
//  MODAL INIT
// ─────────────────────────────────────────────────────────────────────────

// CREATE modal — load symbols when first opened
$('#createConfigModal').on('show.bs.modal', function() {
    loadSymbolsOnce(function() {
        // Reset selection
        SP.selected.create.clear();
        SP.currentFilter.create = 'all';
        SP.currentSearch.create = '';
        renderChips('create');
        renderHiddenInputs('create');
        renderPreviewTags('create');
    });
});

// EDIT button — populate modal with existing data
$(document).on('click', '.btn-edit-config', function () {
    var d = $(this).data();

    // Set form action
    var url = '{{ route("oiiv-auto.update", ":id") }}'.replace(':id', d.id);
    $('#editConfigForm').attr('action', url);

    // Basic fields
    $('#edit_broker_api_id').val(d.broker);
    $('#edit_order_type').val(d.order_type);
    $('#edit_product').val(d.product);
    $('#edit_disc_ltp').val(d.disc_ltp);
    $('#edit_status').val(d.status);
    $('#edit_signal_mode').val(d.signal_mode);

    // Quantities
    $('#edit_index_ce_quantity').val(d.index_ce);
    $('#edit_index_pe_quantity').val(d.index_pe);
    $('#edit_stock_ce_quantity').val(d.stock_ce);
    $('#edit_stock_pe_quantity').val(d.stock_pe);
    [1,2,3,4].forEach(function(r) {
        $('#edit_rank' + r + '_ce_quantity').val(d['rank' + r + '_ce']);
        $('#edit_rank' + r + '_pe_quantity').val(d['rank' + r + '_pe']);
    });

    // Symbol filter — pre-select existing allowed_symbols
    var existing = [];
    try {
        var raw = d.allowed_symbols;
        if (raw && raw !== 'null') {
            existing = typeof raw === 'string' ? JSON.parse(raw) : raw;
        }
    } catch(e) { existing = []; }

    SP.selected.edit.clear();
    SP.currentFilter.edit = 'all';
    SP.currentSearch.edit = '';

    loadSymbolsOnce(function() {
        if (Array.isArray(existing) && existing.length > 0) {
            existing.forEach(function(s) { SP.selected.edit.add(s.toUpperCase()); });
        }
        renderChips('edit');
        renderHiddenInputs('edit');
        renderPreviewTags('edit');
    });

    $('#editConfigModal').modal('show');
});

// Init preview text on page load
$(document).ready(function() {
    ['create','edit'].forEach(function(ctx) {
        renderPreviewTags(ctx);
    });
});
</script>
@endpush