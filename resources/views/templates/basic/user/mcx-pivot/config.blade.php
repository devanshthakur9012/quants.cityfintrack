@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
.mcx-header {
    background: linear-gradient(135deg, #e65c00 0%, #f9d423 100%);
    color:white; padding:20px 26px; border-radius:14px;
    margin-bottom:22px; box-shadow:0 6px 20px rgba(230,92,0,.4);
}
.mcx-header h4 { color:#fff; margin:0; font-size:1.2rem; font-weight:700; }
.mcx-header p  { color:rgba(255,255,255,.85); margin:4px 0 0; font-size:12px; }

.cfg-card {
    background:#f4b285; border-radius:12px; padding:16px 18px;
    box-shadow:0 3px 12px rgba(0,0,0,.08); margin-bottom:16px;
    border-left:5px solid #e65c00;
}

.layer-section {
    background:#f8f9fa; border-radius:10px; padding:12px 14px; margin-bottom:10px;
}
.layer-section-hdr {
    font-size:10px; font-weight:800; text-transform:uppercase;
    letter-spacing:.5px; padding:3px 9px; border-radius:5px;
    display:inline-block; margin-bottom:8px;
}
.lbl-s1-ce { background:#d4edda; color:#155724; }
.lbl-s1-pe { background:#c3f7d8; color:#0f5132; }
.lbl-r1-ce { background:#f8d7da; color:#721c24; }
.lbl-r1-pe { background:#fce4e4; color:#7b1d1d; }

.layer-pill {
    display:inline-block; font-size:9px; font-weight:700;
    padding:2px 7px; border-radius:4px; margin:1px;
    background:#e9ecef; color:#495057;
}

.layer-row input, .layer-row select {
    font-size:11px !important; padding:4px 7px !important; height:28px !important;
}
.layer-row .btn-sm { font-size:10px; padding:3px 8px; height:28px; }

.custom--table thead th,
.custom--table tbody td { vertical-align:middle; font-size:11px; padding:8px 10px !important; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="mcx-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
            <div>
                <h4>&#9881; MCX Pivot Order Config</h4>
                <p>Configure order layers for CRUDEOIL &middot; CRUDEOILM &middot; NATGAS &nbsp;|&nbsp; 5 slots/day: 09:00 &middot; 12:00 &middot; 15:00 &middot; 18:00 &middot; 21:00 (full MCX day)</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('mcx-pivot.analysis') }}" class="btn btn-light btn-sm">&#128202; Analysis</a>
                <button class="btn btn-sm text-white" style="background:#e65c00;" onclick="showCreateForm()">
                    &#43; New Config
                </button>
            </div>
        </div>
    </div>

    {{-- Config list --}}
    <div id="configList">
    @forelse($configs as $cfg)
    <div class="cfg-card">
        <div class="row align-items-start g-2">
            {{-- Basic info --}}
            <div class="col-md-3">
                <div style="font-size:10px;color:#888;">Config #{{ $cfg->id }} &nbsp;&middot;&nbsp;
                    {{ $cfg->broker->client_name ?? '—' }}</div>
                <div style="font-size:13px;font-weight:700;">{{ $cfg->order_type }} / {{ $cfg->product }}</div>
                <div class="mt-1">
                    @if($cfg->status)
                        <span class="badge bg-success" style="font-size:9px;">Active</span>
                    @else
                        <span class="badge bg-secondary" style="font-size:9px;">Inactive</span>
                    @endif
                    <span class="badge bg-light text-dark" style="font-size:9px;">{{ $cfg->orders_count }} orders</span>
                </div>
            </div>

            {{-- Layer summaries --}}
            @foreach([
                ['s1_ce_layers','S1 CE','lbl-s1-ce'],
                ['s1_pe_layers','S1 PE','lbl-s1-pe'],
                ['r1_ce_layers','R1 CE','lbl-r1-ce'],
                ['r1_pe_layers','R1 PE','lbl-r1-pe'],
            ] as [$field, $label, $cls])
            <div class="col-6 col-md-2">
                <span class="layer-section-hdr {{ $cls }}">{{ $label }}</span>
                <div>
                    @if(!empty($cfg->$field))
                        @foreach($cfg->$field as $li => $l)
                            <span class="layer-pill">
                                L{{ $li+1 }}: {{ $l['quantity'] ?? 0 }}
                                &nbsp;{{ ($l['discount_direction']??'') === 'positive' ? '+' : '-' }}{{ $l['discount_pct']??0 }}%
                            </span>
                        @endforeach
                    @else
                        <span style="color:#aaa;font-size:10px;">—</span>
                    @endif
                </div>
            </div>
            @endforeach

            {{-- Actions --}}
            <div class="col-md-1 text-end">
                <button class="btn btn-sm btn-outline-warning mb-1"
                    onclick='editConfig(@json($cfg))' style="font-size:10px;">Edit</button>
                <a href="{{ route('mcx-pivot.config.orders', $cfg->id) }}"
                    class="btn btn-sm btn-outline-primary mb-1" style="font-size:10px;">Orders</a>
                <form method="POST" action="{{ route('mcx-pivot.config.toggle', $cfg->id) }}" style="display:inline;">
                    @csrf @method('PUT')
                    <button class="btn btn-sm {{ $cfg->status ? 'btn-outline-secondary' : 'btn-outline-success' }} mb-1"
                        style="font-size:10px;">{{ $cfg->status ? 'Off' : 'On' }}</button>
                </form>
                <form method="POST" action="{{ route('mcx-pivot.config.destroy', $cfg->id) }}"
                    onsubmit="return confirm('Delete config #{{ $cfg->id }}?')" style="display:inline;">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" style="font-size:10px;">Del</button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-5 text-muted">
        <i class="fas fa-oil-can" style="font-size:2.5rem;opacity:.2;"></i>
        <p class="mt-3">No MCX configs yet. Click <strong>New Config</strong> to create one.</p>
    </div>
    @endforelse
    {{ $configs->links() }}
    </div>

    {{-- Create / Edit Form --}}
    <div id="configFormWrap" style="display:none;">
        <div class="cfg-card">
            <h6 id="formTitle" style="font-weight:800;margin-bottom:16px;">New MCX Config</h6>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:11px;font-weight:700;">Broker</label>
                    <select id="f_broker" class="form-select form-select-sm">
                        <option value="">-- Select Broker --</option>
                        @foreach($brokers as $b)
                            <option value="{{ $b->id }}">{{ $b->client_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:11px;font-weight:700;">Order Type</label>
                    <select id="f_order_type" class="form-select form-select-sm">
                        <option value="LIMIT">LIMIT</option>
                        <option value="MARKET">MARKET</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:11px;font-weight:700;">Product</label>
                    <select id="f_product" class="form-select form-select-sm">
                        <option value="NRML">NRML</option>
                        <option value="MIS">MIS</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:11px;font-weight:700;">Status</label>
                    <select id="f_status" class="form-select form-select-sm">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            {{-- 4 Layer sections --}}
            <div class="row g-3">
                @foreach([
                    ['s1_ce_layers','S1 CE — BUY CE at S1','lbl-s1-ce'],
                    ['s1_pe_layers','S1 PE — BUY PE at S1','lbl-s1-pe'],
                    ['r1_ce_layers','R1 CE — SELL CE at R1','lbl-r1-ce'],
                    ['r1_pe_layers','R1 PE — SELL PE at R1','lbl-r1-pe'],
                ] as [$key, $label, $cls])
                <div class="col-md-6">
                    <div class="layer-section">
                        <span class="layer-section-hdr {{ $cls }}">{{ $label }}</span>
                        <div id="layers_{{ $key }}"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                            style="font-size:10px;" onclick="addLayer('{{ $key }}')">+ Add Layer</button>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-sm text-white" style="background:#e65c00;" onclick="submitConfig()">
                    &#9989; Save Config
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="cancelForm()">Cancel</button>
            </div>

            <input type="hidden" id="f_config_id" value="">
        </div>
    </div>

</div>
</section>

@push('script')
<script>
const STORE_URL  = '{{ route("mcx-pivot.config.store") }}';
const UPDATE_URL = '{{ route("mcx-pivot.config.update", ":id") }}';

const LAYER_KEYS = ['s1_ce_layers','s1_pe_layers','r1_ce_layers','r1_pe_layers'];

// Default layer seeds
const DEFAULT_SEEDS = {
    s1_ce_layers: [
        {discount_direction:'negative', discount_pct:1, quantity:0},
        {discount_direction:'negative', discount_pct:2, quantity:0},
        {discount_direction:'negative', discount_pct:3, quantity:0},
    ],
    s1_pe_layers: [
        {discount_direction:'negative', discount_pct:1, quantity:0},
        {discount_direction:'negative', discount_pct:2, quantity:0},
        {discount_direction:'negative', discount_pct:3, quantity:0},
    ],
    r1_ce_layers: [
        {discount_direction:'positive', discount_pct:1, quantity:0},
        {discount_direction:'positive', discount_pct:2, quantity:0},
        {discount_direction:'positive', discount_pct:3, quantity:0},
    ],
    r1_pe_layers: [
        {discount_direction:'positive', discount_pct:1, quantity:0},
        {discount_direction:'positive', discount_pct:2, quantity:0},
        {discount_direction:'positive', discount_pct:3, quantity:0},
    ],
};

function renderLayer(key, idx, layer) {
    return `
    <div class="d-flex gap-1 align-items-center mb-1 layer-row" data-key="${key}" data-idx="${idx}">
        <span style="font-size:10px;color:#888;min-width:16px;">L${idx+1}</span>
        <select name="${key}[${idx}][discount_direction]" class="form-select form-select-sm" style="width:95px;">
            <option value="positive" ${layer.discount_direction==='positive'?'selected':''}>+ Positive</option>
            <option value="negative" ${layer.discount_direction==='negative'?'selected':''}>- Negative</option>
        </select>
        <input type="number" name="${key}[${idx}][discount_pct]" class="form-control form-control-sm"
            placeholder="%" min="0" max="100" step="0.1" value="${layer.discount_pct}" style="width:65px;">
        <input type="number" name="${key}[${idx}][quantity]" class="form-control form-control-sm"
            placeholder="Qty" min="0" value="${layer.quantity}" style="width:65px;">
        <button type="button" class="btn btn-sm btn-outline-danger"
            onclick="removeLayer('${key}',this)">&times;</button>
    </div>`;
}

function loadLayers(key, layers) {
    const $el = $(`#layers_${key}`);
    $el.html('');
    (layers || []).forEach((l, i) => $el.append(renderLayer(key, i, l)));
}

function addLayer(key) {
    const $el = $(`#layers_${key}`);
    const count = $el.find('.layer-row').length;
    if (count >= 5) { alert('Max 5 layers'); return; }
    const isR1 = key.startsWith('r1');
    $el.append(renderLayer(key, count, {
        discount_direction: isR1 ? 'positive' : 'negative',
        discount_pct: 0, quantity: 0
    }));
}

function removeLayer(key, btn) {
    $(btn).closest('.layer-row').remove();
    // Re-index names
    $(`#layers_${key} .layer-row`).each(function(i) {
        $(this).attr('data-idx', i);
        $(this).find('span').text('L'+(i+1));
        $(this).find('select').attr('name', `${key}[${i}][discount_direction]`);
        $(this).find('input').eq(0).attr('name', `${key}[${i}][discount_pct]`);
        $(this).find('input').eq(1).attr('name', `${key}[${i}][quantity]`);
    });
}

function showCreateForm() {
    $('#formTitle').text('New MCX Config');
    $('#f_config_id').val('');
    $('#f_broker').val('');
    $('#f_order_type').val('LIMIT');
    $('#f_product').val('NRML');
    $('#f_status').val('1');
    LAYER_KEYS.forEach(k => loadLayers(k, DEFAULT_SEEDS[k]));
    $('#configFormWrap').show();
    $('html,body').animate({scrollTop: $('#configFormWrap').offset().top - 80}, 400);
}

function editConfig(cfg) {
    $('#formTitle').text('Edit MCX Config #' + cfg.id);
    $('#f_config_id').val(cfg.id);
    $('#f_broker').val(cfg.broker_api_id);
    $('#f_order_type').val(cfg.order_type);
    $('#f_product').val(cfg.product);
    $('#f_status').val(cfg.status ? '1' : '0');
    LAYER_KEYS.forEach(k => loadLayers(k, cfg[k] || DEFAULT_SEEDS[k]));
    $('#configFormWrap').show();
    $('html,body').animate({scrollTop: $('#configFormWrap').offset().top - 80}, 400);
}

function cancelForm() {
    $('#configFormWrap').hide();
}

function submitConfig() {
    const configId = $('#f_config_id').val();
    const data = {
        broker_api_id : $('#f_broker').val(),
        order_type    : $('#f_order_type').val(),
        product       : $('#f_product').val(),
        status        : $('#f_status').val(),
        _token        : '{{ csrf_token() }}',
    };

    LAYER_KEYS.forEach(key => {
        data[key] = [];
        $(`#layers_${key} .layer-row`).each(function(i) {
            data[key].push({
                discount_direction : $(this).find('select').val(),
                discount_pct       : parseFloat($(this).find('input').eq(0).val()) || 0,
                quantity           : parseInt($(this).find('input').eq(1).val()) || 0,
            });
        });
    });

    const url = configId
        ? UPDATE_URL.replace(':id', configId)
        : STORE_URL;
    const method = configId ? 'PUT' : 'POST';

    $.ajax({ url, method, data, dataType:'json' })
        .done(function(res) {
            if (res.success) {
                alert(res.message);
                location.reload();
            } else {
                alert('Error: ' + res.message);
            }
        })
        .fail(function(xhr) {
            const err = xhr.responseJSON;
            if (err && err.errors) {
                alert(Object.values(err.errors).flat().join('\n'));
            } else {
                alert('Server error. Check console.');
            }
        });
}
</script>
@endpush
@endsection