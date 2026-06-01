{{-- FILE: resources/views/admin/events/form.blade.php --}}
@extends('admin.layouts.app')

@push('style')
<style>
.speaker-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px; max-height:280px; overflow-y:auto; }
.speaker-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border:2px solid #e5e9f2; border-radius:8px; cursor:pointer; transition:all .2s; background:#fafbff; user-select:none; }
.speaker-item:hover    { border-color:#93c5fd; background:#eff6ff; }
.speaker-item.selected { border-color:#1a56db; background:#dbeafe; }
.speaker-avatar-sm { width:36px; height:36px; border-radius:50%; flex-shrink:0; background:#1a56db; color:#fff; font-weight:700; font-size:14px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.speaker-avatar-sm img { width:100%; height:100%; object-fit:cover; }
.speaker-check { margin-left:auto; color:#1a56db; display:none; }
.speaker-item.selected .speaker-check { display:block; }
.price-preview-box { background:linear-gradient(135deg,#0f1b2d,#1a3050); border-radius:10px; padding:16px 18px; color:#fff; margin-top:12px; display:none; }
.pp-price { font-size:28px; font-weight:700; color:#7DFF00; line-height:1.1; }
.pp-mrp   { font-size:13px; text-decoration:line-through; color:rgba(255,255,255,.5); }
.pp-disc  { font-size:12px; background:#7DFF00; color:#0f1b2d; padding:2px 8px; border-radius:4px; font-weight:700; display:inline-block; margin-top:4px; }
.gallery-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:12px; }
.gallery-card { border:1px solid #e5e9f2; border-radius:8px; overflow:hidden; background:#fff; }
.gallery-card img { width:100%; height:110px; object-fit:cover; display:block; }
.gallery-card-body { padding:8px; }
.gallery-new-slot { border:1px dashed #ddd; border-radius:8px; padding:12px; background:#fafafa; }
.gallery-new-preview { width:100%; height:90px; object-fit:cover; border-radius:5px; display:none; margin-top:6px; }
.faq-item { border:1px solid #e5e9f2; border-radius:8px; margin-bottom:8px; overflow:hidden; }
.faq-item-header { display:flex; align-items:center; gap:10px; padding:12px 14px; background:#f8f9fd; cursor:pointer; }
.faq-question-text { flex:1; font-size:13px; font-weight:600; }
.faq-item-body { padding:12px 14px; border-top:1px solid #f0f2f7; display:none; }
.faq-item-body.open { display:block; }
.booking-status-card { border:2px solid; border-radius:9px; padding:12px 14px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
.booking-status-card.open   { border-color:#43a047; background:#f1fdf3; }
.booking-status-card.closed { border-color:#e53935; background:#fff5f5; }
</style>
@endpush

@section('panel')
@php $editing = isset($event); @endphp

<form action="{{ $editing ? route('admin.events.update',$event) : route('admin.events.store') }}"
      method="POST" enctype="multipart/form-data">
    @csrf @if($editing) @method('PUT') @endif

<div class="row">

{{-- ══ LEFT ══ --}}
<div class="col-xl-8">

    {{-- 1. BASIC INFO --}}
    <div class="card b-radius--10 mb-3">
        <div class="card-header"><h5 class="card-title mb-0"><i class="las la-info-circle me-1"></i> Basic Info</h5></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label required">Event Title</label>
                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" required
                       value="{{ old('title',$event->title ?? '') }}" placeholder="e.g. Option Symposium 8.0 — Bangalore">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"
                          placeholder="Describe the event...">{{ old('description',$event->description ?? '') }}</textarea>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label required">Badge Type</label>
                    <select name="badge" class="form-select" required>
                        @foreach(['symposium','workshop','seminar','bootcamp','conference','other'] as $b)
                            <option value="{{ $b }}" @selected(old('badge',$event->badge ?? 'seminar') === $b)>{{ ucfirst($b) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Status</label>
                    <select name="status" class="form-select" required>
                        @foreach(['upcoming','ongoing','past','draft'] as $s)
                            <option value="{{ $s }}" @selected(old('status',$event->status ?? 'upcoming') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <select name="city" class="form-select">
                        <option value="">-- Select City --</option>
                        @foreach($cities as $key => $label)
                            <option value="{{ $key }}" @selected(old('city',$event->city ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="form-label">Venue / Location Address</label>
                <input type="text" name="location" class="form-control"
                       value="{{ old('location',$event->location ?? '') }}"
                       placeholder="e.g. Koramangala Community Hall, 80 Feet Road, Bangalore">
            </div>
        </div>
    </div>

    {{-- 2. DATE & TIME --}}
    <div class="card b-radius--10 mb-3">
        <div class="card-header"><h5 class="card-title mb-0"><i class="las la-calendar me-1"></i> Date & Time</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Event Date</label>
                    <input type="date" name="event_date" class="form-control"
                           value="{{ old('event_date', isset($event->event_date) ? $event->event_date->format('Y-m-d') : '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Time</label>
                    <input type="time" name="event_time_start" class="form-control"
                           value="{{ old('event_time_start',$event->event_time_start ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Time</label>
                    <input type="time" name="event_time_end" class="form-control"
                           value="{{ old('event_time_end',$event->event_time_end ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Duration (hours)</label>
                    <input type="number" name="duration_hours" class="form-control" min="1" max="72"
                           value="{{ old('duration_hours',$event->duration_hours ?? '') }}" placeholder="e.g. 8">
                </div>
            </div>
            <small class="text-muted mt-1 d-block">
                <i class="las la-info-circle"></i>
                Countdown timer on the event page is auto-calculated from Event Date + Start Time vs current time.
            </small>
        </div>
    </div>

    {{-- 3. PRICING --}}
    <div class="card b-radius--10 mb-3">
        <div class="card-header"><h5 class="card-title mb-0"><i class="las la-rupee-sign me-1"></i> Pricing</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" id="evPriceType" onchange="toggleEvPrice()">
                        <option value="paid" @selected(old('type',$event->type ?? 'paid') === 'paid')>Paid</option>
                        <option value="free" @selected(old('type',$event->type ?? '') === 'free')>Free</option>
                    </select>
                </div>
                <div class="col-md-3 ev-paid-field">
                    <label class="form-label">MRP (₹)</label>
                    <div class="input-group"><span class="input-group-text">₹</span>
                    <input type="number" name="mrp" id="evMrp" class="form-control" min="0"
                           value="{{ old('mrp',$event->mrp ?? '') }}" placeholder="12000"></div>
                </div>
                <div class="col-md-3 ev-paid-field">
                    <label class="form-label">Discount %</label>
                    <div class="input-group">
                    <input type="number" name="discount_percent" id="evDisc" class="form-control"
                           step="0.5" min="0" max="100"
                           value="{{ old('discount_percent',$event->discount_percent ?? '') }}" placeholder="27">
                    <span class="input-group-text">%</span></div>
                </div>
                <div class="col-md-3 ev-paid-field">
                    <label class="form-label">Final Price ₹ <small class="text-muted">(auto)</small></label>
                    <div class="input-group"><span class="input-group-text">₹</span>
                    <input type="number" name="price" id="evPrice" class="form-control" readonly
                           value="{{ old('price',$event->price ?? '') }}" placeholder="Auto"></div>
                </div>
            </div>
            <div class="price-preview-box" id="evPricePreview">
                <div style="font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;">Price Preview</div>
                <div style="display:flex;align-items:baseline;gap:10px;margin-top:4px;">
                    <span class="pp-price" id="evPpPrice">₹0</span>
                    <span class="pp-mrp"   id="evPpMrp"></span>
                </div>
                <span class="pp-disc" id="evPpDisc"></span>
            </div>
            <div class="alert alert--success mt-2" id="evFreeNote" style="display:none;">
                Free event — price set to ₹0 automatically.
            </div>
        </div>
    </div>

    {{-- 4. SEATS --}}
    <div class="card b-radius--10 mb-3">
        <div class="card-header"><h5 class="card-title mb-0"><i class="las la-chair me-1"></i> Seats & Booking</h5></div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Total Seats <small class="text-muted">(blank = unlimited)</small></label>
                    <input type="number" name="total_seats" class="form-control" min="1"
                           value="{{ old('total_seats',$event->total_seats ?? '') }}" placeholder="e.g. 200">
                </div>
                @if($editing)
                <div class="col-md-8">
                    <label class="form-label">Booking Status</label>
                    <div class="booking-status-card {{ $event->booking_open ? 'open' : 'closed' }}">
                        <div>
                            <strong>{{ $event->booking_open ? '✅ Bookings Open' : '🔴 Bookings Closed' }}</strong>
                            <small class="d-block text-muted">{{ $event->total_booked }} booked so far</small>
                        </div>
                        <a href="{{ route('admin.events.booking.toggle',$event) }}"
                           class="btn btn--sm btn--{{ $event->booking_open ? 'danger' : 'success' }}">
                            {{ $event->booking_open ? 'Close Bookings' : 'Open Bookings' }}
                        </a>
                    </div>
                </div>
                @else
                <div class="col-md-4 d-flex align-items-center" style="padding-top:28px;">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="booking_open" value="1" id="bookOpen" checked>
                        <label class="form-check-label" for="bookOpen">Bookings Open</label>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- 5. VIDEO --}}
    <div class="card b-radius--10 mb-3">
        <div class="card-header"><h5 class="card-title mb-0"><i class="las la-film me-1"></i> Event Video <small class="text-muted fw-normal">(promo / recap)</small></h5></div>
        <div class="card-body">
            <div class="mb-2">
                <label class="form-label">Source</label>
                <select name="video_type" class="form-select" id="evVideoType" onchange="toggleVideoType()">
                    <option value="">None</option>
                    <option value="youtube" @selected(old('video_type',$event->video_type ?? '') === 'youtube')>YouTube URL</option>
                    <option value="upload"  @selected(old('video_type',$event->video_type ?? '') === 'upload')>Upload Video</option>
                </select>
            </div>
            <div id="ytField" style="display:none;">
                <input type="text" name="video_url_yt" class="form-control"
                       value="{{ old('video_url_yt', ($event->video_type ?? '') === 'youtube' ? ($event->video_url ?? '') : '') }}"
                       placeholder="https://www.youtube.com/watch?v=...">
            </div>
            <div id="uploadVidField" style="display:none;">
                <input type="file" name="video_file" class="form-control" accept="video/*">
                @if($editing && ($event->video_type ?? '') === 'upload' && $event->video_url)
                <small class="text-muted">Current: {{ $event->video_url }}</small>
                @endif
            </div>
        </div>
    </div>

    {{-- 6. GALLERY --}}
    <div class="card b-radius--10 mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0"><i class="las la-images me-1"></i> Gallery Section</h5>
            <button type="button" class="btn btn--primary btn--sm" onclick="addGallerySlot()">
                <i class="las la-plus"></i> Add Image
            </button>
        </div>
        <div class="card-body">
            {{-- Gallery section title (dynamic) --}}
            <div class="mb-3">
                <label class="form-label">Section Title <small class="text-muted">(shown on frontend above images)</small></label>
                <input type="text" name="gallery_section_title" class="form-control"
                       value="{{ old('gallery_section_title', $event->gallery_section_title ?? 'Event Gallery') }}"
                       placeholder="e.g. Event Gallery, Advanced Trading Tools, Tools Covered">
            </div>

            {{-- Existing items --}}
            @if($galleryItems->isNotEmpty())
            <div class="gallery-grid mb-3">
                @foreach($galleryItems as $i => $item)
                <div class="gallery-card" id="gal_{{ $item->id }}">
                    <img src="{{ $item->image_url }}" alt="{{ $item->title }}">
                    <div class="gallery-card-body">
                        <input type="hidden" name="gallery_keep_ids[]" value="{{ $item->id }}">
                        <input type="text" name="gallery_kept_titles[]" class="form-control form-control-sm mb-1"
                               value="{{ $item->title }}" placeholder="Image title">
                        <button type="button" class="btn btn--danger btn--sm w-100"
                                onclick="document.getElementById('gal_{{ $item->id }}').remove()">
                            <i class="las la-trash"></i> Remove
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- New upload slots --}}
            <div id="newGallerySlots"></div>

            @if($galleryItems->isEmpty())
            <p class="text-muted text-center py-3" id="galleryEmpty" style="font-size:13px;">
                <i class="las la-images" style="font-size:32px;opacity:.3;display:block;margin-bottom:6px;"></i>
                No images yet. Click "Add Image" to upload.
            </p>
            @endif
        </div>
    </div>

    {{-- 7. TAGS --}}
    <div class="card b-radius--10 mb-3">
        <div class="card-header"><h5 class="card-title mb-0"><i class="las la-tags me-1"></i> Tags</h5></div>
        <div class="card-body">
            <input type="text" name="tags" class="form-control"
                   value="{{ old('tags', isset($event) ? implode(', ', $event->tags_array) : '') }}"
                   placeholder="Comma-separated: Options Strategies, Algo Trading, Derivatives">
        </div>
    </div>

    {{-- 8. SPEAKERS --}}
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
                    $isSel       = in_array($emp->id, $selectedSpeakers);
                    $fullName    = trim($emp->firstname.' '.$emp->lastname);
                    $designation = optional($emp->employeeProfile)->designation ?? 'Speaker';
                    $avatarPath  = $emp->profile_pic ? asset(getFilePath('userProfile').'/'.$emp->profile_pic) : null;
                @endphp
                <div class="speaker-item {{ $isSel ? 'selected' : '' }}" onclick="toggleSpeaker(this, {{ $emp->id }})">
                    <div class="speaker-avatar-sm">
                        @if($avatarPath)<img src="{{ $avatarPath }}" alt="{{ $fullName }}">
                        @else{{ strtoupper(substr($emp->firstname,0,1)) }}@endif
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

    {{-- 9. FAQS --}}
    <div class="card b-radius--10 mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0"><i class="las la-question-circle me-1"></i> FAQs</h5>
            <button type="button" class="btn btn--primary btn--sm" onclick="addFaqRow()"><i class="las la-plus"></i> Add FAQ</button>
        </div>
        <div class="card-body" id="faqContainer">
            @foreach($faqs as $faq)
            <div class="faq-item" data-faq-id="{{ $faq->id }}">
                <div class="faq-item-header" onclick="toggleFaqBody(this)">
                    <span class="faq-question-text">{{ $faq->question }}</span>
                    <button type="button" class="btn btn--danger btn--sm" style="padding:3px 8px;"
                            onclick="event.stopPropagation(); deleteFaq(this, {{ $faq->id }})"><i class="las la-trash"></i></button>
                    <i class="las la-angle-down" style="color:#aaa;font-size:13px;"></i>
                </div>
                <div class="faq-item-body">
                    <div class="mb-2"><label class="form-label">Question</label>
                        <input type="text" class="form-control form-control-sm faq-q-input" value="{{ $faq->question }}" onblur="saveFaq(this, {{ $faq->id }})"></div>
                    <div><label class="form-label">Answer</label>
                        <textarea class="form-control form-control-sm faq-a-input" rows="3" onblur="saveFaq(this, {{ $faq->id }})">{{ $faq->answer }}</textarea></div>
                </div>
            </div>
            @endforeach
            <div id="newFaqRows"></div>
            @if($faqs->isEmpty())
            <p class="text-muted text-center py-2" id="faqEmpty" style="font-size:13px;">No FAQs yet. Click "Add FAQ".</p>
            @endif
        </div>
    </div>

</div>{{-- /col-xl-8 --}}

{{-- RIGHT SIDEBAR --}}
<div class="col-xl-4">
    <div class="card b-radius--10 mb-3">
        <div class="card-header"><h5 class="card-title mb-0"><i class="las la-rocket me-1"></i> Publish</h5></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" min="0"
                       value="{{ old('sort_order',$event->sort_order ?? 0) }}">
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_featured" value="1"
                       id="featuredToggle" @checked(old('is_featured',$event->is_featured ?? false))>
                <label class="form-check-label" for="featuredToggle">Featured Event</label>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <a href="{{ route('admin.events.index') }}" class="btn btn--secondary btn--sm w-50">Cancel</a>
            <button type="submit" class="btn btn--primary btn--sm w-50">
                <i class="las la-save"></i> {{ $editing ? 'Update' : 'Create' }}
            </button>
        </div>
    </div>

    <div class="card b-radius--10 mb-3">
        <div class="card-header"><h5 class="card-title mb-0"><i class="las la-image me-1"></i> Cover Image</h5></div>
        <div class="card-body">
            @if($editing && $event->thumbnail)
                <img id="thumbPreview" src="{{ $event->thumbnail_url }}" class="rounded w-100 mb-2" style="max-height:160px;object-fit:cover;">
            @else
                <img id="thumbPreview" class="rounded w-100 mb-2" style="max-height:160px;object-fit:cover;display:none;">
            @endif
            <input type="file" name="thumbnail" id="thumbnailInput" accept="image/*" class="form-control form-control-sm">
            <small class="text-muted">800×450px recommended</small>
        </div>
    </div>
</div>

</div>{{-- /row --}}
</form>

@endsection

@push('script')
<script>
var eventId = {{ $editing ? $event->id : 'null' }};

// Thumbnail
document.getElementById('thumbnailInput').addEventListener('change', function() {
    if (!this.files[0]) return;
    var r = new FileReader();
    r.onload = function(e) { var img = document.getElementById('thumbPreview'); img.src = e.target.result; img.style.display = 'block'; };
    r.readAsDataURL(this.files[0]);
});

// Price calculator
function recalcEvPrice() {
    var mrp  = parseFloat(document.getElementById('evMrp').value) || 0;
    var disc = parseFloat(document.getElementById('evDisc').value) || 0;
    var preview = document.getElementById('evPricePreview');
    if (mrp <= 0) { preview.style.display = 'none'; document.getElementById('evPrice').value = ''; return; }
    if (disc > 0 && disc <= 100) {
        var price = Math.round(mrp * (1 - disc / 100));
        document.getElementById('evPrice').value = price;
        document.getElementById('evPpPrice').textContent = '₹' + price.toLocaleString('en-IN');
        document.getElementById('evPpMrp').textContent   = '₹' + mrp.toLocaleString('en-IN');
        document.getElementById('evPpDisc').textContent  = Math.round(disc) + '% off';
        document.getElementById('evPpDisc').style.display = 'inline-block';
    } else {
        document.getElementById('evPrice').value = mrp;
        document.getElementById('evPpPrice').textContent = '₹' + mrp.toLocaleString('en-IN');
        document.getElementById('evPpMrp').textContent = '';
        document.getElementById('evPpDisc').style.display = 'none';
    }
    preview.style.display = 'block';
}
document.getElementById('evMrp').addEventListener('input', recalcEvPrice);
document.getElementById('evDisc').addEventListener('input', recalcEvPrice);
recalcEvPrice();

function toggleEvPrice() {
    var isFree = document.getElementById('evPriceType').value === 'free';
    document.querySelectorAll('.ev-paid-field').forEach(function(f) { f.style.display = isFree ? 'none' : ''; });
    document.getElementById('evPricePreview').style.display = 'none';
    document.getElementById('evFreeNote').style.display = isFree ? '' : 'none';
    if (isFree) document.getElementById('evPrice').value = 0;
}
toggleEvPrice();

// Video
function toggleVideoType() {
    var v = document.getElementById('evVideoType').value;
    document.getElementById('ytField').style.display       = v === 'youtube' ? '' : 'none';
    document.getElementById('uploadVidField').style.display = v === 'upload'  ? '' : 'none';
}
toggleVideoType();

// Gallery
var galCount = 0;
function addGallerySlot() {
    var e = document.getElementById('galleryEmpty'); if (e) e.style.display = 'none';
    galCount++;
    var idx = galCount - 1;
    var html = '<div class="gallery-new-slot mb-3" id="newGalSlot_' + galCount + '">' +
        '<div class="mb-2">' +
        '<input type="file" name="gallery_new_images[' + idx + ']" class="form-control form-control-sm" accept="image/*" ' +
        'onchange="previewGalImg(this,' + galCount + ')">' +
        '<img id="galPrev_' + galCount + '" class="gallery-new-preview">' +
        '</div>' +
        '<div class="d-flex gap-2">' +
        '<input type="text" name="gallery_new_titles[' + idx + ']" class="form-control form-control-sm" placeholder="Image title (e.g. Creating Options Strategy)">' +
        '<button type="button" class="btn btn--danger btn--sm flex-shrink-0" ' +
        'onclick="document.getElementById(\'newGalSlot_' + galCount + '\').remove()">' +
        '<i class="las la-trash"></i></button>' +
        '</div></div>';
    document.getElementById('newGallerySlots').insertAdjacentHTML('beforeend', html);
}
function previewGalImg(input, id) {
    if (!input.files[0]) return;
    var r = new FileReader();
    r.onload = function(e) { var img = document.getElementById('galPrev_' + id); img.src = e.target.result; img.style.display = 'block'; };
    r.readAsDataURL(input.files[0]);
}

// Speakers
var selectedSpeakerIds = {!! json_encode($selectedSpeakers) !!};
function toggleSpeaker(el, id) {
    var idx = selectedSpeakerIds.indexOf(id);
    if (idx === -1) { selectedSpeakerIds.push(id); el.classList.add('selected'); }
    else { selectedSpeakerIds.splice(idx, 1); el.classList.remove('selected'); }
    var c = document.getElementById('speakerHiddenInputs');
    c.innerHTML = '';
    selectedSpeakerIds.forEach(function(id) {
        var i = document.createElement('input'); i.type='hidden'; i.name='speaker_ids[]'; i.value=id; c.appendChild(i);
    });
    document.getElementById('speakerCount').textContent = selectedSpeakerIds.length + ' selected';
}

// FAQs
var faqCtr = 0;
function addFaqRow() {
    var e = document.getElementById('faqEmpty'); if (e) e.style.display = 'none';
    faqCtr++;
    var idx = faqCtr;
    var html = '<div class="faq-item"><div class="faq-item-header" onclick="toggleFaqBody(this)">' +
        '<span class="faq-question-text text-muted">New FAQ ' + idx + '</span>' +
        '<button type="button" class="btn btn--danger btn--sm" style="padding:3px 8px;" onclick="event.stopPropagation();this.closest(\'.faq-item\').remove()"><i class="las la-trash"></i></button>' +
        '<i class="las la-angle-down" style="color:#aaa;font-size:13px;"></i></div>' +
        '<div class="faq-item-body open">' +
        '<div class="mb-2"><label class="form-label">Question</label>' +
        '<input type="text" name="new_faqs[' + idx + '][question]" class="form-control form-control-sm" placeholder="e.g. How do I attend?" ' +
        'oninput="this.closest(\'.faq-item\').querySelector(\'.faq-question-text\').textContent = this.value || \'New FAQ ' + idx + '\'"></div>' +
        '<div><label class="form-label">Answer</label>' +
        '<textarea name="new_faqs[' + idx + '][answer]" class="form-control form-control-sm" rows="3"></textarea></div>' +
        '</div></div>';
    document.getElementById('newFaqRows').insertAdjacentHTML('beforeend', html);
}
function toggleFaqBody(h) { var b = h.nextElementSibling; if (b) b.classList.toggle('open'); }
function saveFaq(inputEl, faqId) {
    if (!eventId || !faqId) return;
    var item = inputEl.closest('.faq-item');
    var q = item.querySelector('.faq-q-input').value.trim();
    var a = item.querySelector('.faq-a-input').value.trim();
    if (!q || !a) return;
    fetch('/admin/events/faqs/' + faqId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ question: q, answer: a })
    });
    item.querySelector('.faq-question-text').textContent = q;
}
function deleteFaq(btn, faqId) {
    if (!confirm('Delete this FAQ?')) return;
    if (faqId && eventId) {
        fetch('/admin/events/faqs/' + faqId, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
    }
    btn.closest('.faq-item').remove();
}
</script>
@endpush
