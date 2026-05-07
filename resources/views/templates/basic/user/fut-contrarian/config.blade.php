@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
.page-header {
    background: linear-gradient(135deg, #0f3460 0%, #16213e 60%, #1a1a2e 100%);
    color: white; padding: 22px 28px; border-radius: 14px;
    margin-bottom: 22px; box-shadow: 0 6px 20px rgba(15,52,96,0.5);
    border: 1px solid rgba(0,212,255,0.2);
}
.page-header h4 { font-size: 1.35rem; margin-bottom: 4px; font-weight: 700; color: #00d4ff; }
.page-header p  { font-size: 12px; margin-bottom: 0; opacity: .75; }

.stats-box {
    background: #fff; padding: 14px; border-radius: 12px; text-align: center;
    border-left: 5px solid #00d4ff; box-shadow: 0 3px 12px rgba(0,0,0,.08);
    transition: transform .2s; margin-bottom: 18px;
}
.stats-box:hover { transform: translateY(-2px); }
.stats-box small  { display:block; color:#888; font-size:10px; text-transform:uppercase; letter-spacing:.5px; }
.stats-box strong { display:block; font-size:1.6rem; font-weight:700; margin-top:4px; }

.custom--table thead th,
.custom--table tbody td { vertical-align:middle; font-size:12px; padding:9px 10px !important; }

.modal-header-gradient {
    background: linear-gradient(135deg, #0f3460, #16213e);
    color: white; border-radius: 12px 12px 0 0; padding: 16px 22px;
    border-bottom: 1px solid rgba(0,212,255,0.2);
}
.modal-header-gradient .btn-close { filter: brightness(0) invert(1); opacity: .8; }
.modal-content { border-radius: 14px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,.25); }

.section-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: #0f3460; margin: 16px 0 8px;
    padding-bottom: 6px; border-bottom: 2px solid rgba(0,212,255,0.3);
}
.form-section { background: #f8f9ff; border-radius: 10px; padding: 14px; margin-bottom: 4px; }

/* Window cards */
.window-card {
    border: 2px solid #dee2e6; border-radius: 10px; padding: 12px 16px;
    cursor: pointer; transition: all .2s; text-align: center; display: block;
    margin-bottom: 0;
}
.window-card.active-30 { border-color: #00d4ff; background: rgba(0,212,255,0.07); }
.window-card.active-1h { border-color: #667eea; background: rgba(102,126,234,0.07); }
.window-card .wc-icon  { font-size: 22px; margin-bottom: 4px; }
.window-card .wc-title { font-weight: 700; font-size: 13px; }
.window-card .wc-sub   { font-size: 10px; color: #888; margin-top: 2px; }

/* Status */
.status-active   { background:#d4edda; color:#155724; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
.status-inactive { background:#f8d7da; color:#721c24; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
.btn-action      { padding:5px 10px; font-size:11px; border-radius:6px; margin:1px; }

/* Symbol picker */
.symbol-picker-wrap { border:1.5px solid #dee2e6; border-radius:10px; background:#fff; overflow:hidden; }
.symbol-picker-search { padding:8px 12px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; gap:8px; background:#f8f9ff; }
.symbol-picker-search input { border:none; background:transparent; outline:none; font-size:12px; flex:1; }
.sp-count { font-size:11px; font-weight:700; color:#00d4ff; white-space:nowrap; }
.symbol-picker-actions { display:flex; gap:6px; padding:6px 12px; border-bottom:1px solid #f0f0f0; flex-wrap:wrap; background:#fff; }
.sp-action-btn { font-size:10px; padding:3px 10px; border-radius:20px; border:1px solid #dee2e6; background:white; cursor:pointer; font-weight:600; transition:all .15s; color:#555; }
.sp-action-btn:hover, .sp-action-btn.active-filter { background:#0f3460; color:white; border-color:#0f3460; }
.symbol-grid { display:flex; flex-wrap:wrap; gap:6px; padding:10px 12px; max-height:200px; overflow-y:auto; }
.symbol-chip { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:20px; border:1.5px solid #dee2e6; font-size:11px; font-weight:600; cursor:pointer; transition:all .15s; background:white; color:#555; user-select:none; }
.symbol-chip:hover { border-color:#00d4ff; color:#00d4ff; background:rgba(0,212,255,0.06); }
.symbol-chip.selected { background:linear-gradient(135deg,#0f3460,#16213e); color:white !important; border-color:#0f3460; }
.symbol-chip.is-index { border-color:#28a745; color:#28a745; }
.symbol-chip.is-index.selected { background:linear-gradient(135deg,#28a745,#20c997); border-color:#28a745; }
.symbol-chip .chip-dot { width:6px; height:6px; border-radius:50%; background:currentColor; flex-shrink:0; }
.symbol-picker-footer { padding:8px 12px; border-top:1px solid #f0f0f0; background:#f8f9ff; font-size:11px; color:#888; display:flex; align-items:center; justify-content:space-between; }
.selected-preview { display:flex; flex-wrap:wrap; gap:4px; margin-top:8px; min-height:22px; }
.selected-tag { background:linear-gradient(135deg,#0f3460,#16213e); color:white; font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; display:inline-flex; align-items:center; gap:4px; }
.selected-tag .remove-tag { cursor:pointer; opacity:.7; font-size:12px; line-height:1; }
.selected-tag .remove-tag:hover { opacity:1; }

.sym-filter-badge { background:#e8f5e9; color:#1b5e20; border:1px solid #c8e6c9; padding:2px 7px; border-radius:10px; font-size:10px; font-weight:700; display:inline-block; }
.sym-all-badge    { background:#e3f2fd; color:#0d47a1; border:1px solid #bbdefb; padding:2px 7px; border-radius:10px; font-size:10px; font-weight:700; display:inline-block; }
.win-30  { background:rgba(0,212,255,0.12); color:#006080; border:1px solid rgba(0,212,255,0.3); padding:2px 7px; border-radius:8px; font-size:10px; font-weight:700; display:inline-block; }
.win-1hr { background:rgba(102,126,234,0.12); color:#3a3fa0; border:1px solid rgba(102,126,234,0.3); padding:2px 7px; border-radius:8px; font-size:10px; font-weight:700; display:inline-block; }
label { color: #000 !important; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>⚡ {{ $pageTitle }}</h4>
                <p>Configure brokers, quantities and signal windows for the FUT Contrarian OI auto-trading system</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('fut-contrarian.index') }}" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-chart-area"></i> Analysis
                </a>
                <a href="{{ route('fut-contrarian-monthly.index') }}" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-calendar"></i> Monthly P&amp;L
                </a>
                <button class="btn btn-light btn-sm" id="btnOpenCreate">
                    <i class="fas fa-plus"></i> New Config
                </button>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row mb-2">
        <div class="col-6 col-md-3">
            <div class="stats-box"><small>Total Configs</small><strong class="text-dark">{{ $configs->total() }}</strong></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-box" style="border-left-color:#28a745;"><small>Active</small><strong style="color:#28a745;">{{ $configs->where('status', true)->count() }}</strong></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-box" style="border-left-color:#dc3545;"><small>Inactive</small><strong style="color:#dc3545;">{{ $configs->where('status', false)->count() }}</strong></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-box" style="border-left-color:#17a2b8;"><small>Total Orders</small><strong style="color:#17a2b8;">{{ $configs->sum('orders_count') }}</strong></div>
        </div>
    </div>

    {{-- Table --}}
    <div style="border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.08); background:white; overflow:hidden;">
        <div class="table-responsive">
            <table class="table custom--table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Broker</th>
                        <th>Window Mode</th>
                        <th>Order / Disc</th>
                        <th>Product</th>

                        <th>Symbols</th>
                        <th>Index Qty<br><small style="font-weight:400;opacity:.7;">CE / PE</small></th>
                        <th>Stock Qty<br><small style="font-weight:400;opacity:.7;">CE / PE</small></th>
                        <th>Orders</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($configs as $i => $config)
                    <tr>
                        <td><strong>{{ $configs->firstItem() + $i }}</strong></td>
                        <td>
                            <strong style="color:#0f3460;">{{ $config->broker->client_name ?? 'N/A' }}</strong><br>
                            <small class="text-muted">{{ $config->broker->client_type ?? '' }}</small>
                        </td>
                        <td>
                            @if($config->trade_30min)<span class="win-30">⏱ 30-min</span><br>@endif
                            @if($config->trade_1hr)<span class="win-1hr">🕐 1-HR</span>@endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $config->order_type === 'MARKET' ? 'success' : 'info' }}">{{ $config->order_type }}</span><br>
                            <small class="text-muted">Disc: {{ $config->disc_ltp }}%</small>
                        </td>
                        <td>
                            <span class="badge bg-{{ $config->product === 'NRML' ? 'primary' : 'warning' }}">{{ $config->product }}</span>
                        </td>
                        <td>
                            @php
                                $wmode = ($config->trade_30min && $config->trade_1hr) ? 'both'
                                       : ($config->trade_1hr ? '1hr' : '30min');
                            @endphp
                            @if($wmode === 'both')
                                <span class="badge bg-primary">⏱🕐 Both required</span>
                            @elseif($wmode === '1hr')
                                <span style="background:rgba(102,126,234,0.15);color:#3a3fa0;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;">🕐 1HR only</span>
                            @else
                                <span style="background:rgba(0,212,255,0.12);color:#006080;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;">⏱ 30min only</span>
                            @endif
                        </td>
                        <td>
                            @if($config->allowed_symbols === null)
                                <span class="sym-all-badge">🌐 All</span>
                            @elseif(count($config->allowed_symbols) === 0)
                                <span style="color:#dc3545;font-size:11px;font-weight:700;">⛔ None</span>
                            @else
                                <span class="sym-filter-badge" title="{{ implode(', ', $config->allowed_symbols) }}">
                                    🎯 {{ count($config->allowed_symbols) }} symbols
                                </span>
                                <div style="margin-top:2px;max-width:120px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                                    <small class="text-muted" style="font-size:10px;">
                                        {{ implode(', ', array_slice($config->allowed_symbols, 0, 3)) }}{{ count($config->allowed_symbols) > 3 ? '…' : '' }}
                                    </small>
                                </div>
                            @endif
                        </td>
                        <td>
                            <small>CE: <strong>{{ $config->index_ce_quantity }}</strong></small><br>
                            <small>PE: <strong>{{ $config->index_pe_quantity }}</strong></small>
                        </td>
                        <td>
                            <small>CE: <strong>{{ $config->stock_ce_quantity }}</strong></small><br>
                            <small>PE: <strong>{{ $config->stock_pe_quantity }}</strong></small>
                        </td>
                        <td>
                            <a href="{{ route('fut-contrarian-config.orders', $config->id) }}" class="text-primary fw-bold">
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
                                data-trade30="{{ $config->trade_30min ? '1' : '0' }}"
                                data-trade1h="{{ $config->trade_1hr ? '1' : '0' }}"
                                data-ordertype="{{ $config->order_type }}"
                                data-product="{{ $config->product }}"
                                data-discltp="{{ $config->disc_ltp }}"
                                data-alignment="{{ $config->alignment_mode }}"
                                data-status="{{ $config->status ? '1' : '0' }}"
                                data-indexce="{{ $config->index_ce_quantity }}"
                                data-indexpe="{{ $config->index_pe_quantity }}"
                                data-stockce="{{ $config->stock_ce_quantity }}"
                                data-stockpe="{{ $config->stock_pe_quantity }}"
                                data-symbols="{{ json_encode($config->allowed_symbols) }}"
                                title="Edit"><i class="fas fa-edit"></i>
                            </button>

                            {{-- Toggle --}}
                            <form action="{{ route('fut-contrarian-config.toggle', $config->id) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit"
                                    class="btn btn-{{ $config->status ? 'warning' : 'success' }} btn-action"
                                    onclick="return confirm('{{ $config->status ? 'Deactivate' : 'Activate' }} this config?')">
                                    <i class="fas fa-{{ $config->status ? 'pause' : 'play' }}"></i>
                                </button>
                            </form>

                            {{-- Orders --}}
                            <a href="{{ route('fut-contrarian-config.orders', $config->id) }}" class="btn btn-primary btn-action">
                                <i class="fas fa-list"></i>
                            </a>

                            {{-- Delete --}}
                            <form action="{{ route('fut-contrarian-config.destroy', $config->id) }}" method="POST" style="display:inline;">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-action"
                                    onclick="return confirm('Delete this config?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="text-center py-5">
                            <i class="fas fa-cog" style="font-size:2.5rem;opacity:.3;"></i>
                            <p class="mt-3 text-muted">No configurations yet. <a href="#" id="emptyCreateLink">Create one</a>.</p>
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

{{-- ════════════ CREATE MODAL ════════════ --}}
<div class="modal fade" id="createConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0" style="color:#00d4ff;"><i class="fas fa-plus-circle me-2"></i>New FUT Contrarian Config</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('fut-contrarian-config.store') }}" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog me-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }} ({{ $broker->client_type }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Order Type</label>
                                <select name="order_type" class="form-control" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Product</label>
                                <select name="product" class="form-control" required>
                                    <option value="NRML">NRML</option>
                                    <option value="MIS">MIS</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Discount % (LIMIT)</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">0 = place at candle open price</small>
                            </div>
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-clock me-1"></i> Trading Window Mode</p>
                    <div class="form-section">
                        {{-- Hidden inputs — set by JS based on dropdown --}}
                        <input type="hidden" name="trade_30min" id="c_trade_30min" value="1">
                        <input type="hidden" name="trade_1hr"   id="c_trade_1hr"   value="0">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-5">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Select Window</label>
                                <select id="c_window_mode" class="form-control" onchange="applyWindowMode('c', this.value)">
                                    <option value="30min">⏱ 30-min only (FUT + 30min OI → Buy @ 10:00)</option>
                                    <option value="1hr">🕐 1-HR only (FUT + 1HR OI → Buy @ 10:30)</option>
                                    <option value="both">⏱🕐 Both (FUT + 30min + 1HR all match)</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <div id="c_window_desc" class="p-3 rounded" style="background:rgba(0,212,255,0.07);border:1px solid rgba(0,212,255,0.25);font-size:11px;color:#444;line-height:1.7;">
                                    <strong style="color:#006080;">⏱ 30-min Window selected</strong><br>
                                    FUT direction (prev 15:00 vs today 09:30) + OI-30min signal (prev 15:15 vs today 09:45) must both agree.<br>
                                    ✅ Order placed at <strong>10:00 candle open</strong>.
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-filter me-1"></i> Symbol Filter <small style="font-weight:400;color:#888;text-transform:none;letter-spacing:0;">(empty = trade ALL)</small></p>
                    <div class="form-section pb-3">
                        <div class="symbol-picker-wrap" id="cSymbolPicker">
                            <div class="symbol-picker-search">
                                <i class="fas fa-search" style="color:#aaa;font-size:12px;"></i>
                                <input type="text" placeholder="Search symbols..." oninput="filterChips('c', this.value)">
                                <span class="sp-count" id="cSelectedCount">0 selected</span>
                            </div>
                            <div class="symbol-picker-actions">
                                <button type="button" class="sp-action-btn" onclick="selectAll('c')">Select All</button>
                                <button type="button" class="sp-action-btn" onclick="clearAll('c')">Clear All</button>
                                <button type="button" class="sp-action-btn active-filter" onclick="filterCategory('c','all',this)">All</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('c','index',this)">Indices</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('c','nifty50',this)">Nifty50</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('c','banknifty',this)">BankNifty</button>
                            </div>
                            <div class="symbol-grid" id="cSymbolGrid"><div class="text-muted p-2" style="font-size:12px;">Loading…</div></div>
                            <div class="symbol-picker-footer">
                                <span>💡 Empty = trade all &nbsp;|&nbsp; <span style="color:#28a745;font-weight:700;">Green = Index</span></span>
                                <span id="cFooterCount" style="font-weight:600;color:#00d4ff;">0 selected</span>
                            </div>
                        </div>
                        <div id="cHiddenInputs"></div>
                        <div class="selected-preview mt-2" id="cSelectedPreview"></div>
                    </div>

                    <p class="section-label"><i class="fas fa-boxes me-1"></i> Quantities (lots)</p>
                    <div class="form-section">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:12px;">Index CE (lots)</label>
                                <input type="number" name="index_ce_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:12px;">Index PE (lots)</label>
                                <input type="number" name="index_pe_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:12px;">Stock CE (lots)</label>
                                <input type="number" name="stock_ce_quantity" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:12px;">Stock PE (lots)</label>
                                <input type="number" name="stock_pe_quantity" class="form-control" value="0" min="0" required />
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2" style="font-size:11px;">
                            Index = NIFTY / BANKNIFTY / FINNIFTY / MIDCPNIFTY / SENSEX / BANKEX &nbsp;|&nbsp; Stock = all others
                        </small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Create Config</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ════════════ EDIT MODAL ════════════ --}}
<div class="modal fade" id="editConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-gradient d-flex justify-content-between align-items-center">
                <h5 class="mb-0" style="color:#00d4ff;"><i class="fas fa-edit me-2"></i>Edit Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editConfigForm" action="" method="POST">
                @csrf @method('PUT')
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog me-1"></i> Broker & Order Settings</p>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Broker Account</label>
                                <select name="broker_api_id" id="e_broker" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->client_name }} ({{ $broker->client_type }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Order Type</label>
                                <select name="order_type" id="e_order_type" class="form-control" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Product</label>
                                <select name="product" id="e_product" class="form-control" required>
                                    <option value="NRML">NRML</option>
                                    <option value="MIS">MIS</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Discount %</label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" id="e_disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-0">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Status</label>
                                <select name="status" id="e_status" class="form-control" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>

                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-clock me-1"></i> Trading Window Mode</p>
                    <div class="form-section">
                        <input type="hidden" name="trade_30min" id="e_trade_30min" value="1">
                        <input type="hidden" name="trade_1hr"   id="e_trade_1hr"   value="0">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-5">
                                <label class="form-label" style="font-size:12px;font-weight:600;">Select Window</label>
                                <select id="e_window_mode" class="form-control" onchange="applyWindowMode('e', this.value)">
                                    <option value="30min">⏱ 30-min only (FUT + 30min OI → Buy @ 10:00)</option>
                                    <option value="1hr">🕐 1-HR only (FUT + 1HR OI → Buy @ 10:30)</option>
                                    <option value="both">⏱🕐 Both (FUT + 30min + 1HR all match)</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <div id="e_window_desc" class="p-3 rounded" style="background:rgba(0,212,255,0.07);border:1px solid rgba(0,212,255,0.25);font-size:11px;color:#444;line-height:1.7;"></div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-filter me-1"></i> Symbol Filter <small style="font-weight:400;color:#888;text-transform:none;letter-spacing:0;">(empty = trade ALL)</small></p>
                    <div class="form-section pb-3">
                        <div class="symbol-picker-wrap" id="eSymbolPicker">
                            <div class="symbol-picker-search">
                                <i class="fas fa-search" style="color:#aaa;font-size:12px;"></i>
                                <input type="text" placeholder="Search symbols..." oninput="filterChips('e', this.value)">
                                <span class="sp-count" id="eSelectedCount">0 selected</span>
                            </div>
                            <div class="symbol-picker-actions">
                                <button type="button" class="sp-action-btn" onclick="selectAll('e')">Select All</button>
                                <button type="button" class="sp-action-btn" onclick="clearAll('e')">Clear All</button>
                                <button type="button" class="sp-action-btn active-filter" onclick="filterCategory('e','all',this)">All</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('e','index',this)">Indices</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('e','nifty50',this)">Nifty50</button>
                                <button type="button" class="sp-action-btn" onclick="filterCategory('e','banknifty',this)">BankNifty</button>
                            </div>
                            <div class="symbol-grid" id="eSymbolGrid"><div class="text-muted p-2" style="font-size:12px;">Loading…</div></div>
                            <div class="symbol-picker-footer">
                                <span>💡 Empty = trade all &nbsp;|&nbsp; <span style="color:#28a745;font-weight:700;">Green = Index</span></span>
                                <span id="eFooterCount" style="font-weight:600;color:#00d4ff;">0 selected</span>
                            </div>
                        </div>
                        <div id="eHiddenInputs"></div>
                        <div class="selected-preview mt-2" id="eSelectedPreview"></div>
                    </div>

                    <p class="section-label"><i class="fas fa-boxes me-1"></i> Quantities (lots)</p>
                    <div class="form-section">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:12px;">Index CE (lots)</label>
                                <input type="number" name="index_ce_quantity" id="e_index_ce" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:12px;">Index PE (lots)</label>
                                <input type="number" name="index_pe_quantity" id="e_index_pe" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:12px;">Stock CE (lots)</label>
                                <input type="number" name="stock_ce_quantity" id="e_stock_ce" class="form-control" value="0" min="0" required />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:12px;">Stock PE (lots)</label>
                                <input type="number" name="stock_pe_quantity" id="e_stock_pe" class="form-control" value="0" min="0" required />
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Update Config</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
/* =============================================================
   WINDOW MODE — dropdown drives two hidden inputs
   ctx: 'c' = create, 'e' = edit
   mode: '30min' | '1hr' | 'both'
   ============================================================= */

var WINDOW_DESCS = {
    '30min': '<strong style="color:#006080;">⏱ 30-min Window only</strong><br>'
           + 'Both <strong>FUT direction</strong> (prev 15:00 → today 09:30) and <strong>OI-30min signal</strong> (prev 15:15 → today 09:45) must agree.<br>'
           + '✅ Order placed at <strong>10:00 candle open</strong>.',

    '1hr':   '<strong style="color:#3a3fa0;">🕐 1-HR Window only</strong><br>'
           + 'Both <strong>FUT direction</strong> (prev 15:00 → today 09:30) and <strong>OI-1HR signal</strong> (prev 15:15 → today 10:15) must agree.<br>'
           + '✅ Order placed at <strong>10:30 candle open</strong>.',

    'both':  '<strong style="color:#0f3460;">⏱🕐 Both windows — all three must match</strong><br>'
           + '<strong>FUT direction</strong> + <strong>OI-30min</strong> + <strong>OI-1HR</strong> signals must all agree.<br>'
           + '✅ Two orders placed: <strong>10:00 open</strong> (30min) and <strong>10:30 open</strong> (1HR).',
};

function applyWindowMode(ctx, mode) {
    var inp30 = document.getElementById(ctx + '_trade_30min');
    var inp1h = document.getElementById(ctx + '_trade_1hr');
    var desc  = document.getElementById(ctx + '_window_desc');

    if (!inp30 || !inp1h) return;   // null guard

    if (mode === '30min') {
        inp30.value = '1'; inp1h.value = '0';
    } else if (mode === '1hr') {
        inp30.value = '0'; inp1h.value = '1';
    } else {  // both
        inp30.value = '1'; inp1h.value = '1';
    }

    if (desc) desc.innerHTML = WINDOW_DESCS[mode] || '';
}

// Set dropdown + hidden inputs from saved config values (trade_30min, trade_1hr)
function setWindowDropdown(ctx, has30, has1h) {
    var sel  = document.getElementById(ctx + '_window_mode');
    var mode = (has30 && has1h) ? 'both' : has1h ? '1hr' : '30min';
    if (sel) sel.value = mode;
    applyWindowMode(ctx, mode);
}

/* =============================================================
   SYMBOL PICKER
   ============================================================= */
var SP = {
    allSymbols: [], loaded: false,
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
    selected:      { c: new Set(), e: new Set() },
    currentFilter: { c: 'all', e: 'all' },
    currentSearch: { c: '', e: '' },
};

function loadSymbolsOnce(cb) {
    if (SP.loaded && SP.allSymbols.length > 0) { if (cb) cb(); return; }
    $.get('{{ route("fut-contrarian-config.symbols") }}', function(res) {
        if (!res.success) return;
        SP.allSymbols = (res.symbols || []).map(function(sym) {
            sym = sym.toString().toUpperCase();
            return { symbol:sym, isIndex:SP.INDEX_SYMBOLS.includes(sym),
                     isNifty50:SP.NIFTY50.includes(sym), isBankNifty:SP.BANKNIFTY.includes(sym) };
        });
        SP.loaded = true;
        if (cb) cb();
    });
}

function renderChips(ctx) {
    var grid = document.getElementById(ctx + 'SymbolGrid');
    if (!grid) return;
    var search = SP.currentSearch[ctx].toLowerCase();
    var cat    = SP.currentFilter[ctx];
    var list   = SP.allSymbols.filter(function(s) {
        var ms = !search || s.symbol.toLowerCase().includes(search);
        var mc = cat==='all'?true: cat==='index'?s.isIndex:
                 cat==='nifty50'?s.isNifty50: cat==='banknifty'?s.isBankNifty:true;
        return ms && mc;
    });
    var html = list.map(function(s) {
        var sel = SP.selected[ctx].has(s.symbol);
        return '<span class="symbol-chip'+(s.isIndex?' is-index':'')+(sel?' selected':'')+'"'
             + ' onclick="toggleChip(\''+ctx+'\',\''+s.symbol+'\')">'
             + '<span class="chip-dot"></span>'+s.symbol+'</span>';
    }).join('');
    grid.innerHTML = html || '<div class="text-muted p-2" style="font-size:12px;">No match</div>';
    updateCounts(ctx);
}

function toggleChip(ctx,sym) {
    SP.selected[ctx].has(sym)?SP.selected[ctx].delete(sym):SP.selected[ctx].add(sym);
    renderChips(ctx); renderHiddenInputs(ctx); renderPreviewTags(ctx);
}
function selectAll(ctx)  { SP.allSymbols.forEach(s=>SP.selected[ctx].add(s.symbol)); renderChips(ctx); renderHiddenInputs(ctx); renderPreviewTags(ctx); }
function clearAll(ctx)   { SP.selected[ctx].clear(); renderChips(ctx); renderHiddenInputs(ctx); renderPreviewTags(ctx); }
function filterCategory(ctx,cat,btn) {
    SP.currentFilter[ctx]=cat;
    var pk=document.getElementById(ctx+'SymbolPicker');
    if(pk) pk.querySelectorAll('.sp-action-btn').forEach(b=>b.classList.remove('active-filter'));
    if(btn) btn.classList.add('active-filter');
    renderChips(ctx);
}
function filterChips(ctx,val) { SP.currentSearch[ctx]=val; renderChips(ctx); }
function updateCounts(ctx) {
    var n=SP.selected[ctx].size;
    var sc=document.getElementById(ctx+'SelectedCount');
    var fc=document.getElementById(ctx+'FooterCount');
    if(sc) sc.textContent=n+' selected';
    if(fc) fc.textContent=n+' selected';
}
function renderHiddenInputs(ctx) {
    var wrap=document.getElementById(ctx+'HiddenInputs');
    if(!wrap) return;
    wrap.innerHTML='';
    SP.selected[ctx].forEach(function(sym){
        var inp=document.createElement('input');
        inp.type='hidden'; inp.name='allowed_symbols[]'; inp.value=sym;
        wrap.appendChild(inp);
    });
}
function renderPreviewTags(ctx) {
    var wrap=document.getElementById(ctx+'SelectedPreview');
    if(!wrap) return;
    var syms=Array.from(SP.selected[ctx]).sort();
    if(!syms.length){
        wrap.innerHTML='<small class="text-muted" style="font-size:11px;">No filter — all symbols will be traded</small>';
        return;
    }
    wrap.innerHTML=syms.map(s=>'<span class="selected-tag">'+s
        +'<span class="remove-tag" onclick="toggleChip(\''+ctx+'\',\''+s+'\')">×</span></span>').join('');
}

/* =============================================================
   MODAL EVENTS — Bootstrap 5 shown.bs.modal (DOM ready)
   ============================================================= */

// ── CREATE ─────────────────────────────────────────────────
var createModal = document.getElementById('createConfigModal');
createModal.addEventListener('shown.bs.modal', function () {
    // Default: 30min only
    setWindowDropdown('c', true, false);

    SP.selected.c.clear();
    SP.currentFilter.c = 'all';
    SP.currentSearch.c = '';
    loadSymbolsOnce(function() {
        renderChips('c');
        renderHiddenInputs('c');
        renderPreviewTags('c');
    });
});

document.getElementById('btnOpenCreate').addEventListener('click', function() {
    new bootstrap.Modal(createModal).show();
});
var emptyLink = document.getElementById('emptyCreateLink');
if (emptyLink) emptyLink.addEventListener('click', function(e) {
    e.preventDefault();
    new bootstrap.Modal(createModal).show();
});

// ── EDIT ───────────────────────────────────────────────────
var editModal   = document.getElementById('editConfigModal');
var editModalBS = new bootstrap.Modal(editModal);
var pendingEditData = null;

editModal.addEventListener('shown.bs.modal', function () {
    if (!pendingEditData) return;
    var d = pendingEditData;
    pendingEditData = null;

    // Set window dropdown (DOM is ready now)
    setWindowDropdown('e', d.trade30, d.trade1h);

    // Symbol picker
    SP.selected.e.clear();
    SP.currentFilter.e = 'all';
    SP.currentSearch.e = '';
    loadSymbolsOnce(function() {
        if (Array.isArray(d.symbols) && d.symbols.length)
            d.symbols.forEach(s => SP.selected.e.add(s.toUpperCase()));
        renderChips('e');
        renderHiddenInputs('e');
        renderPreviewTags('e');
    });
});

$(document).on('click', '.btn-edit-config', function () {
    var d = $(this).data();

    // Form action
    var url = '{{ route("fut-contrarian-config.update", ":id") }}'.replace(':id', d.id);
    $('#editConfigForm').attr('action', url);

    // Set all simple fields immediately
    document.getElementById('e_broker').value     = d.broker    || '';
    document.getElementById('e_order_type').value = d.ordertype || 'LIMIT';
    document.getElementById('e_product').value    = d.product   || 'NRML';
    document.getElementById('e_disc_ltp').value   = d.discltp   || 0;
    document.getElementById('e_status').value     = d.status    || '1';
    document.getElementById('e_index_ce').value   = d.indexce   || 0;
    document.getElementById('e_index_pe').value   = d.indexpe   || 0;
    document.getElementById('e_stock_ce').value   = d.stockce   || 0;
    document.getElementById('e_stock_pe').value   = d.stockpe   || 0;

    // Parse symbols
    var syms = [];
    try { var raw=d.symbols; if(raw&&raw!=='null') syms=typeof raw==='string'?JSON.parse(raw):raw; } catch(e){}

    // Store for shown.bs.modal
    pendingEditData = {
        trade30: (d.trade30==='1'||d.trade30===1||d.trade30===true),
        trade1h:  (d.trade1h ==='1'||d.trade1h ===1||d.trade1h ===true),
        symbols: syms,
    };

    editModalBS.show();
});

/* =============================================================
   INIT
   ============================================================= */
$(document).ready(function () {
    ['c','e'].forEach(ctx => renderPreviewTags(ctx));
});
</script>
@endpush