{{-- FILE: resources/views/admin/webinars/form.blade.php --}}
@extends('admin.layouts.app')

@push('style')
<style>
/* ── Speaker pills ── */
.speaker-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px; max-height: 280px; overflow-y: auto; padding: 4px 2px;
}
.speaker-item {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px;
    border: 2px solid #e5e9f2; border-radius: 8px; cursor: pointer;
    transition: all .2s; background: #fafbff; user-select: none;
}
.speaker-item:hover    { border-color: #93c5fd; background: #eff6ff; }
.speaker-item.selected { border-color: #1a56db; background: #dbeafe; }
.speaker-avatar-sm {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    background: #1a56db; color: #fff; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.speaker-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }
.speaker-check { margin-left: auto; color: #1a56db; display: none; }
.speaker-item.selected .speaker-check { display: block; }

/* ── Price preview ── */
.price-preview-box {
    background: linear-gradient(135deg, #0f1b2d, #1a3050);
    border-radius: 10px; padding: 16px 18px; color: #fff; margin-top: 14px; display: none;
}
.price-preview-box .pp-label { font-size: 11px; color: rgba(255,255,255,.6); text-transform: uppercase; }
.price-preview-box .pp-price { font-size: 28px; font-weight: 700; color: #f5a623; line-height: 1.1; }
.price-preview-box .pp-mrp   { font-size: 14px; text-decoration: line-through; color: rgba(255,255,255,.5); }
.price-preview-box .pp-disc  { font-size: 12px; background: #f5a623; color: #0f1b2d; padding: 2px 8px; border-radius: 4px; font-weight: 700; display: inline-block; margin-top: 4px; }

/* ── FAQ builder ── */
.faq-item { border: 1px solid #e5e9f2; border-radius: 8px; margin-bottom: 8px; overflow: hidden; background: #fff; }
.faq-item-header { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #f8f9fd; cursor: pointer; }
.faq-drag-handle { color: #ccc; cursor: grab; font-size: 16px; }
.faq-question-text { flex: 1; font-size: 13.5px; font-weight: 600; color: #0f1b2d; }
.faq-item-body { padding: 12px 14px; border-top: 1px solid #f0f2f7; display: none; }
.faq-item-body.open { display: block; }

/* ── Tool builder ── */
.tool-item { border: 1px solid #e5e9f2; border-radius: 8px; margin-bottom: 10px; padding: 14px; background: #fff; }
.tool-thumb-preview { width: 100%; max-height: 100px; object-fit: cover; border-radius: 6px; display: none; margin-top: 8px; }
</style>
@endpush

@section('panel')

@php
    $editing          = isset($webinar);
    $selectedSpeakers = $selectedSpeakers ?? [];
    $faqs             = $faqs ?? collect();
    $tools            = $tools ?? collect();
@endphp

<form action="{{ $editing ? route('admin.webinars.update', $webinar) : route('admin.webinars.store') }}"
      method="POST" enctype="multipart/form-data" id="webinarForm">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="row">

        {{-- ════ LEFT ════ --}}
        <div class="col-xl-8">

            {{-- 1. BASIC INFO --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-info-circle me-1"></i> Basic Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Webinar Title</label>
                        <input type="text" name="title" required
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $webinar->title ?? '') }}"
                               placeholder="e.g. Options Mastery Blueprint">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label required">Status</label>
                            <select name="status" class="form-select" required>
                                @foreach(['upcoming','live','past'] as $s)
                                    <option value="{{ $s }}" @selected(old('status', $webinar->status ?? 'upcoming') === $s)>
                                        {{ ucfirst($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Type</label>
                            <select name="type" class="form-select" id="priceType" required onchange="togglePriceFields()">
                                <option value="paid" @selected(old('type', $webinar->type ?? 'paid') === 'paid')>Paid</option>
                                <option value="free" @selected(old('type', $webinar->type ?? '') === 'free')>Free</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Mode</label>
                            <select name="mode" class="form-select" required onchange="toggleAddressField()">
                                <option value="online"  @selected(old('mode', $webinar->mode ?? 'online') === 'online')>Online</option>
                                <option value="offline" @selected(old('mode', $webinar->mode ?? '') === 'offline')>Offline</option>
                                <option value="hybrid"  @selected(old('mode', $webinar->mode ?? '') === 'hybrid')>Hybrid</option>
                            </select>
                        </div>
                    </div>

                    {{-- Address (offline/hybrid only) --}}
                    <div class="mb-3" id="addressField" style="{{ in_array(old('mode', $webinar->mode ?? 'online'), ['offline','hybrid']) ? '' : 'display:none' }}">
                        <label class="form-label">Venue Address <small class="text-muted">(for offline/hybrid)</small></label>
                        <input type="text" name="address" class="form-control"
                               value="{{ old('address', $webinar->address ?? '') }}"
                               placeholder="e.g. Koramangala, Bangalore, Karnataka">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label required">Language</label>
                            <select name="language" class="form-select" required>
                                @foreach(['Hindi','English','Gujarati'] as $lang)
                                    <option value="{{ $lang }}" @selected(old('language', $webinar->language ?? 'Hindi') === $lang)>{{ $lang }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Level</label>
                            <select name="level" class="form-select" required>
                                @foreach(['Beginner Level','Intermediate Level','Advanced Level'] as $lvl)
                                    <option value="{{ $lvl }}" @selected(old('level', $webinar->level ?? 'Beginner Level') === $lvl)>{{ $lvl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date & Time</label>
                            <input type="datetime-local" name="webinar_date" class="form-control"
                                   value="{{ old('webinar_date', isset($webinar->webinar_date) ? $webinar->webinar_date->format('Y-m-d\TH:i') : '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-control"
                                   value="{{ old('duration', $webinar->duration ?? '') }}" placeholder="e.g. 2 hr">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Total Seats <small class="text-muted">(blank = unlimited)</small></label>
                            <input type="number" name="total_seats" class="form-control" min="1"
                                   value="{{ old('total_seats', $webinar->total_seats ?? '') }}" placeholder="e.g. 200">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0"
                                   value="{{ old('sort_order', $webinar->sort_order ?? 0) }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_featured" value="1"
                                       id="featuredToggle" @checked(old('is_featured', $webinar->is_featured ?? false))>
                                <label class="form-check-label" for="featuredToggle">Mark as Featured</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. PRICING --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-rupee-sign me-1"></i> Pricing</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 paidFields">
                            <label class="form-label">MRP (Original Price ₹)</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="mrp" id="mrpInput" class="form-control" step="1" min="0"
                                       value="{{ old('mrp', $webinar->mrp ?? '') }}" placeholder="e.g. 999">
                            </div>
                        </div>
                        <div class="col-md-4 paidFields">
                            <label class="form-label">Discount % <small class="text-muted">(auto-calculates price)</small></label>
                            <div class="input-group">
                                <input type="number" name="discount_percent" id="discountInput" class="form-control"
                                       step="0.5" min="0" max="100"
                                       value="{{ old('discount_percent', $webinar->discount_percent ?? '') }}" placeholder="e.g. 30">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-4 paidFields">
                            <label class="form-label">Final Price ₹ <small class="text-muted">(auto)</small></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="price" id="priceInput" class="form-control" readonly
                                       value="{{ old('price', $webinar->price ?? '') }}" placeholder="Auto">
                            </div>
                        </div>
                    </div>

                    <div class="price-preview-box" id="pricePreview">
                        <div class="pp-label">Price Preview</div>
                        <div style="display:flex;align-items:baseline;gap:10px;margin-top:6px;">
                            <span class="pp-price" id="ppPrice">₹0</span>
                            <span class="pp-mrp"   id="ppMrp"></span>
                        </div>
                        <span class="pp-disc" id="ppDisc"></span>
                    </div>

                    <div class="alert alert--success mt-3" id="freeNote" style="display:none;">
                        <i class="las la-info-circle"></i> Free webinar — price will be set to ₹0 automatically.
                    </div>
                </div>
            </div>

            {{-- 3. YOUTUBE PREVIEW VIDEO --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-film me-1"></i> YouTube Preview Video <small class="text-muted fw-normal">(optional)</small></h5>
                </div>
                <div class="card-body">
                    <input type="text" name="youtube_url" class="form-control"
                           value="{{ old('youtube_url', $webinar->youtube_url ?? '') }}"
                           placeholder="https://www.youtube.com/watch?v=...">
                    <small class="text-muted">Shown on the webinar detail page as a preview/intro video.</small>
                </div>
            </div>

            {{-- 4. SPEAKERS --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0"><i class="las la-chalkboard-teacher me-1"></i> Speakers</h5>
                    <span class="badge badge--primary" id="speakerCount">{{ count($selectedSpeakers) }} selected</span>
                </div>
                <div class="card-body">
                    @if($employees->count())
                    <div id="speakerHiddenInputs">
                        @foreach($selectedSpeakers as $sid)
                            <input type="hidden" name="speaker_ids[]" value="{{ $sid }}">
                        @endforeach
                    </div>
                    <div class="speaker-grid">
                        @foreach($employees as $emp)
                        @php
                            $isSelected  = in_array($emp->id, $selectedSpeakers);
                            $fullName    = trim($emp->firstname . ' ' . $emp->lastname);
                            $initial     = strtoupper(substr($emp->firstname, 0, 1));
                            $designation = $emp->employeeProfile->designation ?? 'Speaker';
                            $avatarPath  = $emp->profile_pic ? asset(getFilePath('userProfile') . '/' . $emp->profile_pic) : null;
                        @endphp
                        <div class="speaker-item {{ $isSelected ? 'selected' : '' }}"
                             data-id="{{ $emp->id }}" onclick="toggleSpeaker(this, {{ $emp->id }})">
                            <div class="speaker-avatar-sm">
                                @if($avatarPath)<img src="{{ $avatarPath }}" alt="{{ $fullName }}">
                                @else{{ $initial }}@endif
                            </div>
                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $fullName }}</div>
                                <div style="font-size:11px;color:#7a8499;">{{ $designation }}</div>
                            </div>
                            <i class="las la-check-circle speaker-check"></i>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="alert alert--warning">No employees found. Add users with the <strong>employee</strong> role first.</div>
                    @endif
                </div>
            </div>

            {{-- 5. FAQ BUILDER --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0"><i class="las la-question-circle me-1"></i> FAQs</h5>
                    <button type="button" class="btn btn--primary btn--sm" onclick="addFaqRow()">
                        <i class="las la-plus"></i> Add FAQ
                    </button>
                </div>
                <div class="card-body" id="faqContainer">
                    @foreach($faqs as $faq)
                    <div class="faq-item" data-faq-id="{{ $faq->id }}">
                        <div class="faq-item-header" onclick="toggleFaqBody(this)">
                            <i class="las la-grip-vertical faq-drag-handle"></i>
                            <span class="faq-question-text">{{ $faq->question }}</span>
                            <button type="button" class="btn btn--danger btn--sm" style="padding:3px 8px;"
                                    onclick="event.stopPropagation(); deleteFaq(this, {{ $faq->id }})">
                                <i class="las la-trash"></i>
                            </button>
                            <i class="las la-angle-down" style="color:#aaa;font-size:13px;"></i>
                        </div>
                        <div class="faq-item-body">
                            <div class="mb-2">
                                <label class="form-label">Question</label>
                                <input type="text" class="form-control form-control-sm faq-q-input"
                                       value="{{ $faq->question }}" onblur="saveFaq(this, {{ $faq->id }})">
                            </div>
                            <div>
                                <label class="form-label">Answer</label>
                                <textarea class="form-control form-control-sm faq-a-input" rows="3"
                                          onblur="saveFaq(this, {{ $faq->id }})">{{ $faq->answer }}</textarea>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    <div id="newFaqRows"></div>
                    @if($faqs->isEmpty())
                    <div id="faqEmptyNote" class="text-center py-3 text-muted" style="font-size:13px;">
                        <i class="las la-question-circle" style="font-size:32px;opacity:.3;display:block;margin-bottom:8px;"></i>
                        No FAQs yet. Click "Add FAQ" to add your first.
                    </div>
                    @endif
                </div>
            </div>

            {{-- 6. TOOL SHOWCASE (like "Advanced Trading Tools") --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="las la-th-large me-1"></i> Tool / Feature Showcase
                        <small class="text-muted fw-normal">(optional — shown as image grid on detail page)</small>
                    </h5>
                    <button type="button" class="btn btn--primary btn--sm" onclick="addToolRow()">
                        <i class="las la-plus"></i> Add Tool
                    </button>
                </div>
                <div class="card-body" id="toolContainer">
                    @foreach($tools as $i => $tool)
                    <div class="tool-item" data-tool-id="{{ $tool->id }}">
                        <input type="hidden" name="tools_ids[]" value="{{ $tool->id }}">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label">Title</label>
                                <input type="text" name="tools_title[]" class="form-control form-control-sm"
                                       value="{{ $tool->title }}" placeholder="e.g. Creating Options Strategy">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Description <small class="text-muted">(optional)</small></label>
                                <input type="text" name="tools_description[]" class="form-control form-control-sm"
                                       value="{{ $tool->description }}" placeholder="Brief description">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn--danger btn--sm w-100"
                                        onclick="removeToolRow(this)"><i class="las la-trash"></i></button>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Image</label>
                                @if($tool->image)
                                    <div class="mb-1">
                                        <img src="{{ $tool->image_url }}" style="height:60px;border-radius:4px;object-fit:cover;">
                                        <small class="d-block text-muted">Upload new to replace</small>
                                    </div>
                                @endif
                                <input type="file" name="tools_image[{{ $i }}]" class="form-control form-control-sm" accept="image/*"
                                       onchange="previewToolImage(this)">
                                <img class="tool-thumb-preview">
                            </div>
                        </div>
                    </div>
                    @endforeach
                    <div id="newToolRows"></div>
                    @if($tools->isEmpty())
                    <div id="toolEmptyNote" class="text-center py-3 text-muted" style="font-size:13px;">
                        <i class="las la-th-large" style="font-size:32px;opacity:.3;display:block;margin-bottom:8px;"></i>
                        No tools yet. Click "Add Tool" to showcase webinar features.
                    </div>
                    @endif
                </div>
            </div>

        </div>{{-- /col-xl-8 --}}

        {{-- ════ RIGHT SIDEBAR ════ --}}
        <div class="col-xl-4">

            {{-- PUBLISH --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-rocket me-1"></i> Publish</h5>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.webinars.index') }}" class="btn btn--secondary btn--sm w-50">Cancel</a>
                    <button type="submit" class="btn btn--primary btn--sm w-50">
                        <i class="las la-save"></i> {{ $editing ? 'Update' : 'Create' }}
                    </button>
                </div>
            </div>

            {{-- THUMBNAIL --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-image me-1"></i> Thumbnail</h5>
                </div>
                <div class="card-body">
                    @if($editing && $webinar->thumbnail)
                        <img id="thumbPreview" src="{{ $webinar->thumbnail_url }}"
                             class="rounded w-100 mb-2" style="max-height:160px;object-fit:cover;">
                    @else
                        <img id="thumbPreview" class="rounded w-100 mb-2"
                             style="max-height:160px;object-fit:cover;display:none;">
                    @endif
                    <input type="file" name="thumbnail" id="thumbnailInput" accept="image/*" class="form-control form-control-sm">
                    <small class="text-muted">Recommended: 800×450px</small>
                </div>
            </div>

        </div>

    </div>
</form>

@endsection

@push('script')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
var webinarId = {{ $editing ? $webinar->id : 'null' }};

// ── Thumbnail preview ──────────────────────────────────────────────────────
document.getElementById('thumbnailInput').addEventListener('change', function () {
    var file = this.files[0]; if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = document.getElementById('thumbPreview');
        img.src = e.target.result; img.style.display = 'block';
    };
    reader.readAsDataURL(file);
});

// ── Address field toggle ───────────────────────────────────────────────────
function toggleAddressField() {
    var mode = document.querySelector('[name="mode"]').value;
    var field = document.getElementById('addressField');
    field.style.display = (mode === 'offline' || mode === 'hybrid') ? '' : 'none';
}

// ── Price calculator ───────────────────────────────────────────────────────
var mrpInput = document.getElementById('mrpInput');
var discountInput = document.getElementById('discountInput');
var priceInput = document.getElementById('priceInput');

function recalcPrice() {
    var mrp  = parseFloat(mrpInput.value) || 0;
    var disc = parseFloat(discountInput.value) || 0;
    var preview = document.getElementById('pricePreview');
    if (mrp <= 0) { preview.style.display = 'none'; priceInput.value = ''; return; }
    if (disc > 0 && disc <= 100) {
        var price = Math.round(mrp * (1 - disc / 100));
        priceInput.value = price;
        document.getElementById('ppPrice').textContent = '₹' + price.toLocaleString('en-IN');
        document.getElementById('ppMrp').textContent   = '₹' + mrp.toLocaleString('en-IN');
        document.getElementById('ppDisc').textContent  = Math.round(disc) + '% off';
        document.getElementById('ppDisc').style.display = 'inline-block';
    } else {
        priceInput.value = mrp;
        document.getElementById('ppPrice').textContent = '₹' + mrp.toLocaleString('en-IN');
        document.getElementById('ppMrp').textContent   = '';
        document.getElementById('ppDisc').style.display = 'none';
    }
    preview.style.display = 'block';
}
function togglePriceFields() {
    var isFree = document.getElementById('priceType').value === 'free';
    document.querySelectorAll('.paidFields').forEach(function(f) { f.style.display = isFree ? 'none' : ''; });
    document.getElementById('pricePreview').style.display = 'none';
    document.getElementById('freeNote').style.display = isFree ? '' : 'none';
    if (isFree) { priceInput.value = 0; }
}
if (mrpInput)      mrpInput.addEventListener('input', recalcPrice);
if (discountInput) discountInput.addEventListener('input', recalcPrice);
togglePriceFields();
recalcPrice();

// ── Speaker multi-select ───────────────────────────────────────────────────
var selectedSpeakerIds = {!! json_encode($selectedSpeakers) !!};
function toggleSpeaker(el, id) {
    var idx = selectedSpeakerIds.indexOf(id);
    if (idx === -1) { selectedSpeakerIds.push(id); el.classList.add('selected'); }
    else { selectedSpeakerIds.splice(idx, 1); el.classList.remove('selected'); }
    rebuildSpeakerInputs();
    document.getElementById('speakerCount').textContent = selectedSpeakerIds.length + ' selected';
}
function rebuildSpeakerInputs() {
    var c = document.getElementById('speakerHiddenInputs');
    c.innerHTML = '';
    selectedSpeakerIds.forEach(function(id) {
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'speaker_ids[]'; i.value = id;
        c.appendChild(i);
    });
}

// ── FAQ builder ────────────────────────────────────────────────────────────
var newFaqCounter = 0;
function addFaqRow() {
    var empty = document.getElementById('faqEmptyNote');
    if (empty) empty.style.display = 'none';
    newFaqCounter++;
    var idx = newFaqCounter;
    var html = '<div class="faq-item new-faq" data-new-idx="' + idx + '">' +
        '<div class="faq-item-header" onclick="toggleFaqBody(this)">' +
            '<i class="las la-grip-vertical faq-drag-handle"></i>' +
            '<span class="faq-question-text text-muted">New FAQ ' + idx + '</span>' +
            '<button type="button" class="btn btn--danger btn--sm" style="padding:3px 8px;" ' +
                'onclick="event.stopPropagation(); this.closest(\'.faq-item\').remove()"><i class="las la-trash"></i></button>' +
            '<i class="las la-angle-down" style="color:#aaa;font-size:13px;"></i>' +
        '</div>' +
        '<div class="faq-item-body open">' +
            '<div class="mb-2"><label class="form-label">Question</label>' +
            '<input type="text" name="new_faqs[' + idx + '][question]" class="form-control form-control-sm" ' +
                'placeholder="e.g. How do I register?" ' +
                'oninput="this.closest(\'.faq-item\').querySelector(\'.faq-question-text\').textContent = this.value || \'New FAQ ' + idx + '\'"></div>' +
            '<div><label class="form-label">Answer</label>' +
            '<textarea name="new_faqs[' + idx + '][answer]" class="form-control form-control-sm" rows="3"></textarea></div>' +
        '</div></div>';
    document.getElementById('newFaqRows').insertAdjacentHTML('beforeend', html);
}
function toggleFaqBody(header) {
    var body = header.nextElementSibling;
    if (body) body.classList.toggle('open');
}
function saveFaq(inputEl, faqId) {
    if (!webinarId || !faqId) return;
    var item = inputEl.closest('.faq-item');
    var question = item.querySelector('.faq-q-input').value.trim();
    var answer   = item.querySelector('.faq-a-input').value.trim();
    if (!question || !answer) return;
    fetch('/admin/webinars/faqs/' + faqId, {
        method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ question: question, answer: answer })
    });
    item.querySelector('.faq-question-text').textContent = question;
}
function deleteFaq(btn, faqId) {
    if (!confirm('Delete this FAQ?')) return;
    if (faqId && webinarId) {
        fetch('/admin/webinars/faqs/' + faqId, {
            method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
    }
    btn.closest('.faq-item').remove();
}
// FAQ sortable
var faqContainer = document.getElementById('faqContainer');
if (faqContainer && typeof Sortable !== 'undefined') {
    Sortable.create(faqContainer, {
        handle: '.faq-drag-handle', animation: 150,
        onEnd: function() {
            var order = [];
            faqContainer.querySelectorAll('.faq-item[data-faq-id]').forEach(function(el) { order.push(el.dataset.faqId); });
            if (order.length && webinarId) {
                fetch('/admin/webinars/faqs/reorder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ order: order })
                });
            }
        }
    });
}

// ── Tool builder ────────────────────────────────────────────────────────────
var newToolCounter = 0;
function addToolRow() {
    var empty = document.getElementById('toolEmptyNote');
    if (empty) empty.style.display = 'none';
    newToolCounter++;
    var idx = newToolCounter;
    var html = '<div class="tool-item new-tool">' +
        '<input type="hidden" name="tools_ids[]" value="">' +
        '<div class="row g-2">' +
            '<div class="col-md-5"><label class="form-label">Title</label>' +
                '<input type="text" name="tools_title[]" class="form-control form-control-sm" placeholder="e.g. Options Strategy Builder"></div>' +
            '<div class="col-md-5"><label class="form-label">Description</label>' +
                '<input type="text" name="tools_description[]" class="form-control form-control-sm" placeholder="Short description"></div>' +
            '<div class="col-md-2 d-flex align-items-end">' +
                '<button type="button" class="btn btn--danger btn--sm w-100" onclick="removeToolRow(this)"><i class="las la-trash"></i></button></div>' +
            '<div class="col-12"><label class="form-label">Image</label>' +
                '<input type="file" name="tools_image[new_' + idx + ']" class="form-control form-control-sm" accept="image/*" onchange="previewToolImage(this)">' +
                '<img class="tool-thumb-preview"></div>' +
        '</div></div>';
    document.getElementById('newToolRows').insertAdjacentHTML('beforeend', html);
}
function removeToolRow(btn) {
    btn.closest('.tool-item').remove();
}
function previewToolImage(input) {
    if (!input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = input.nextElementSibling;
        img.src = e.target.result; img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
@endpush
