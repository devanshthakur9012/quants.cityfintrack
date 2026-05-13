{{-- FILE: resources/views/admin/courses/form.blade.php --}}
@extends('admin.layouts.app')

@push('style')
<style>
/* ── Trainer multi-select pills ─────────────────────────────────────────── */
.trainer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    max-height: 280px;
    overflow-y: auto;
    padding: 4px 2px;
}
.trainer-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 2px solid #e5e9f2;
    border-radius: 8px;
    cursor: pointer;
    transition: all .2s;
    background: #fafbff;
    user-select: none;
}
.trainer-item:hover      { border-color: #93c5fd; background: #eff6ff; }
.trainer-item.selected   { border-color: #1a56db; background: #dbeafe; }
.trainer-avatar-sm {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    background: #1a56db; color: #fff; font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.trainer-avatar-sm img   { width: 100%; height: 100%; object-fit: cover; }
.trainer-check           { margin-left: auto; color: #1a56db; display: none; }
.trainer-item.selected .trainer-check { display: block; }

/* ── Pricing calculator ──────────────────────────────────────────────────── */
.price-preview-box {
    background: linear-gradient(135deg, #0f1b2d, #1a3050);
    border-radius: 10px;
    padding: 16px 18px;
    color: #fff;
    margin-top: 14px;
    display: none;
}
.price-preview-box .pp-label { font-size: 11px; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: .06em; }
.price-preview-box .pp-mrp   { font-size: 14px; text-decoration: line-through; color: rgba(255,255,255,.5); }
.price-preview-box .pp-price { font-size: 28px; font-weight: 700; color: #f5a623; line-height: 1.1; }
.price-preview-box .pp-disc  { font-size: 12px; background: #f5a623; color: #0f1b2d; padding: 2px 8px; border-radius: 4px; font-weight: 700; display: inline-block; margin-top: 4px; }

/* ── FAQ builder ─────────────────────────────────────────────────────────── */
.faq-item {
    border: 1px solid #e5e9f2;
    border-radius: 8px;
    margin-bottom: 8px;
    overflow: hidden;
    background: #fff;
}
.faq-item-header {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px; background: #f8f9fd; cursor: pointer;
}
.faq-drag-handle { color: #ccc; cursor: grab; font-size: 16px; }
.faq-question-text { flex: 1; font-size: 13.5px; font-weight: 600; color: #0f1b2d; }
.faq-item-body { padding: 12px 14px; border-top: 1px solid #f0f2f7; display: none; }
.faq-item-body.open { display: block; }

/* ── Certificate toggle ─────────────────────────────────────────────────── */
.cert-toggle-label {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; border: 2px solid #e5e9f2; border-radius: 9px;
    cursor: pointer; transition: all .2s; background: #fafbff;
}
.cert-toggle-label:has(input:checked) { border-color: #1a56db; background: #dbeafe; }
.cert-icon { font-size: 28px; color: #f5a623; }
.cert-text-main { font-size: 14px; font-weight: 600; color: #0f1b2d; }
.cert-text-sub  { font-size: 12px; color: #7a8499; }
</style>
@endpush

@section('panel')

@php
    $editing          = isset($course);
    $selectedTrainers = $selectedTrainers ?? [];
    $faqs             = $faqs ?? collect();
@endphp

<form action="{{ $editing ? route('admin.courses.update', $course) : route('admin.courses.store') }}"
      method="POST" enctype="multipart/form-data" id="courseForm">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="row">

        {{-- ═══════════════════════════════════
             LEFT COLUMN
        ═══════════════════════════════════ --}}
        <div class="col-xl-8">

            {{-- ── 1. BASIC INFO ────────────────────────────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-info-circle me-1"></i> Basic Information</h5>
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label required">Course Title</label>
                        <input type="text" name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $course->title ?? '') }}"
                               placeholder="e.g. Trading for Beginners" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Category</label>
                        <select name="course_category_id"
                                class="form-select @error('course_category_id') is-invalid @enderror" required>
                            <option value="">-- Select Category --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}"
                                    @selected(old('course_category_id', $course->course_category_id ?? '') == $cat->id)>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('course_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Short Description
                            <small class="text-muted">(shown on course card)</small>
                        </label>
                        <textarea name="short_description" class="form-control" rows="2"
                                  placeholder="Brief summary of what this course covers">{{ old('short_description', $course->short_description ?? '') }}</textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Full Description</label>
                        <textarea name="description" class="form-control nicEdit" rows="8"
                                  id="descEditor">{{ old('description', $course->description ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- ── 2. PRICING (auto-calculate) ──────────────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-rupee-sign me-1"></i> Pricing</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        {{-- Type --}}
                        <div class="col-md-3">
                            <label class="form-label">Course Type</label>
                            <select name="type" class="form-select" id="priceType">
                                <option value="paid" @selected(old('type', $course->type ?? 'paid') == 'paid')>Paid</option>
                                <option value="free" @selected(old('type', $course->type ?? '') == 'free')>Free</option>
                            </select>
                        </div>

                        {{-- MRP --}}
                        <div class="col-md-3 paidFields">
                            <label class="form-label">MRP (Original Price ₹)
                                <i class="las la-info-circle text-muted" title="The original / crossed-out price"></i>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="mrp" id="mrpInput"
                                       class="form-control" step="1" min="0"
                                       value="{{ old('mrp', $course->mrp ?? '') }}"
                                       placeholder="e.g. 4999">
                            </div>
                        </div>

                        {{-- Discount % --}}
                        <div class="col-md-3 paidFields">
                            <label class="form-label">Discount %
                                <small class="text-muted">(auto-calculates price)</small>
                            </label>
                            <div class="input-group">
                                <input type="number" name="discount_percent" id="discountInput"
                                       class="form-control" step="0.5" min="0" max="100"
                                       value="{{ old('discount_percent', $course->discount_percent ?? '') }}"
                                       placeholder="e.g. 40">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                        {{-- Final Price (read-only, auto-filled) --}}
                        <div class="col-md-3 paidFields">
                            <label class="form-label">Final Price ₹
                                <small class="text-muted">(auto-calculated)</small>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="price" id="priceInput"
                                       class="form-control" step="1" min="0" readonly
                                       value="{{ old('price', $course->price ?? '') }}"
                                       placeholder="Auto">
                            </div>
                            <small class="text-muted">Set MRP + Discount % to auto-fill</small>
                        </div>

                    </div>

                    {{-- Live price preview --}}
                    <div class="price-preview-box" id="pricePreview">
                        <div class="pp-label">Price Preview (as seen by student)</div>
                        <div style="display:flex;align-items:baseline;gap:10px;margin-top:6px;">
                            <span class="pp-price" id="ppPrice">₹0</span>
                            <span class="pp-mrp"  id="ppMrp"></span>
                        </div>
                        <span class="pp-disc" id="ppDisc"></span>
                    </div>
                </div>
            </div>

            {{-- ── 3. TRAINERS (select from employees) ─────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0"><i class="las la-chalkboard-teacher me-1"></i> Trainers
                        <small class="text-muted fw-normal">(select from employees)</small>
                    </h5>
                    <span class="badge badge--primary" id="trainerCount">
                        {{ count($selectedTrainers) }} selected
                    </span>
                </div>
                <div class="card-body">

                    @if($employees->count())
                    {{-- Hidden inputs built by JS when trainer is toggled --}}
                    <div id="trainerHiddenInputs">
                        @foreach($selectedTrainers as $tid)
                            <input type="hidden" name="trainer_ids[]" value="{{ $tid }}">
                        @endforeach
                    </div>

                    <div class="trainer-grid" id="trainerGrid">
                        @foreach($employees as $emp)
                        @php
                            $isSelected  = in_array($emp->id, $selectedTrainers);
                            $fullName    = trim($emp->firstname . ' ' . $emp->lastname);
                            $initial     = strtoupper(substr($emp->firstname, 0, 1));
                            $designation = $emp->employeeProfile->designation ?? 'Employee';
                            $avatarPath  = $emp->profile_pic
                                ? asset(getFilePath('userProfile') . '/' . $emp->profile_pic)
                                : null;
                        @endphp
                        <div class="trainer-item {{ $isSelected ? 'selected' : '' }}"
                             data-id="{{ $emp->id }}"
                             onclick="toggleTrainer(this, {{ $emp->id }})">

                            <div class="trainer-avatar-sm">
                                @if($avatarPath)
                                    <img src="{{ $avatarPath }}" alt="{{ $fullName }}">
                                @else
                                    {{ $initial }}
                                @endif
                            </div>

                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:600;color:#0f1b2d;
                                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    {{ $fullName }}
                                </div>
                                <div style="font-size:11px;color:#7a8499;">
                                    {{ $designation }}
                                </div>
                            </div>

                            <i class="las la-check-circle trainer-check"></i>
                        </div>
                        @endforeach
                    </div>

                    <small class="text-muted d-block mt-2">
                        <i class="las la-info-circle"></i>
                        Name and designation are pulled from the employee's profile.
                        To add more trainers, first assign the <strong>employee</strong> role to a user.
                    </small>

                    @else
                    <div class="alert alert--warning d-flex align-items-center gap-2">
                        <i class="las la-exclamation-triangle"></i>
                        No employees found. Go to
                        <a href="{{ route('admin.users.index') }}" class="ms-1">Users</a>
                        and assign the <strong>employee</strong> role to team members first.
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── 4. FAQ BUILDER ──────────────────────────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="las la-question-circle me-1"></i> FAQs
                        <small class="text-muted fw-normal">(optional)</small>
                    </h5>
                    <button type="button" class="btn btn--primary btn--sm" id="addFaqBtn"
                            onclick="addFaqRow()">
                        <i class="las la-plus"></i> Add FAQ
                    </button>
                </div>
                <div class="card-body" id="faqContainer">

                    {{-- Existing FAQs (edit mode) --}}
                    @foreach($faqs as $faq)
                    <div class="faq-item" data-faq-id="{{ $faq->id }}">
                        <div class="faq-item-header" onclick="toggleFaqBody(this)">
                            <i class="las la-grip-vertical faq-drag-handle"></i>
                            <span class="faq-question-text">{{ $faq->question }}</span>
                            <button type="button" class="btn btn--danger btn--sm"
                                    style="padding:3px 8px;"
                                    onclick="event.stopPropagation(); deleteFaq(this, {{ $faq->id }})">
                                <i class="las la-trash"></i>
                            </button>
                            <i class="las la-angle-down" style="color:#aaa;font-size:13px;"></i>
                        </div>
                        <div class="faq-item-body">
                            <div class="mb-2">
                                <label class="form-label">Question</label>
                                <input type="text" class="form-control form-control-sm faq-q-input"
                                       value="{{ $faq->question }}"
                                       onblur="saveFaq(this, {{ $faq->id }})">
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Answer</label>
                                <textarea class="form-control form-control-sm faq-a-input" rows="3"
                                          onblur="saveFaq(this, {{ $faq->id }})">{{ $faq->answer }}</textarea>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    {{-- New FAQ rows injected by JS --}}
                    <div id="newFaqRows"></div>

                    @if($faqs->isEmpty())
                    <div id="faqEmptyNote" class="text-center py-3 text-muted" style="font-size:13px;">
                        <i class="las la-question-circle" style="font-size:32px;opacity:.3;display:block;margin-bottom:8px;"></i>
                        No FAQs yet. Click "Add FAQ" to add your first.
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── 5. SEO (collapsible, optional) ──────────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <a data-bs-toggle="collapse" href="#seoBlock"
                           class="text-dark text-decoration-none d-flex align-items-center justify-content-between">
                            <span><i class="las la-search me-1"></i> SEO
                                <small class="text-muted fw-normal">(optional)</small>
                            </span>
                            <i class="las la-angle-down"></i>
                        </a>
                    </h5>
                </div>
                <div class="collapse {{ old('meta_title', $course->meta_title ?? '') ? 'show' : '' }}"
                     id="seoBlock">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Meta Title</label>
                            <input type="text" name="meta_title" class="form-control"
                                   value="{{ old('meta_title', $course->meta_title ?? '') }}"
                                   placeholder="Defaults to course title if left blank">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta Description</label>
                            <textarea name="meta_description" class="form-control" rows="2"
                                      placeholder="150–160 characters recommended">{{ old('meta_description', $course->meta_description ?? '') }}</textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Meta Keywords</label>
                            <input type="text" name="meta_keywords" class="form-control"
                                   value="{{ old('meta_keywords', $course->meta_keywords ?? '') }}"
                                   placeholder="options trading, beginner, hindi">
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- /col-xl-8 --}}

        {{-- ═══════════════════════════════════
             RIGHT COLUMN — sidebar
        ═══════════════════════════════════ --}}
        <div class="col-xl-4">

            {{-- ── PUBLISH ──────────────────────────────────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-rocket me-1"></i> Publish</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft"    @selected(old('status', $course->status ?? 'draft') == 'draft')>Draft</option>
                            <option value="upcoming" @selected(old('status', $course->status ?? '') == 'upcoming')>Upcoming</option>
                            <option value="ongoing"  @selected(old('status', $course->status ?? '') == 'ongoing')>Ongoing</option>
                            <option value="recorded" @selected(old('status', $course->status ?? '') == 'recorded')>Recorded</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" min="0"
                               value="{{ old('sort_order', $course->sort_order ?? 0) }}">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_featured" value="1"
                               id="featuredToggle"
                               @checked(old('is_featured', $course->is_featured ?? false))>
                        <label class="form-check-label" for="featuredToggle">Mark as Featured</label>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.courses.index') }}" class="btn btn--secondary btn--sm w-50">Cancel</a>
                    <button type="submit" class="btn btn--primary btn--sm w-50" id="saveBtn">
                        <i class="las la-save"></i> {{ $editing ? 'Update' : 'Create' }}
                    </button>
                </div>
            </div>

            {{-- ── THUMBNAIL ───────────────────────────────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-image me-1"></i> Feature Image</h5>
                </div>
                <div class="card-body">
                    @if($editing && $course->thumbnail)
                        <img id="thumbPreview" src="{{ $course->thumbnail_url }}"
                             class="rounded w-100 mb-2" style="max-height:160px;object-fit:cover;">
                    @else
                        <div id="thumbPlaceholder"
                             class="border rounded p-4 text-center text-muted mb-2"
                             style="border-style:dashed!important;cursor:pointer;"
                             onclick="document.getElementById('thumbnailInput').click()">
                            <i class="las la-image" style="font-size:40px;opacity:.4;display:block;"></i>
                            <small>Click to select image</small>
                        </div>
                        <img id="thumbPreview" class="rounded w-100 mb-2"
                             style="max-height:160px;object-fit:cover;display:none;">
                    @endif
                    <input type="file" name="thumbnail" id="thumbnailInput"
                           accept="image/*" class="form-control form-control-sm">
                    <small class="text-muted">JPG/PNG/WEBP — max 2 MB · Recommended: 800×450px</small>
                </div>
            </div>

            {{-- ── PREVIEW VIDEO ──────────────────────────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-film me-1"></i> Preview Video</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label">Source</label>
                        <select name="preview_video_type" class="form-select form-select-sm">
                            <option value="youtube"
                                @selected(old('preview_video_type', $course->preview_video_type ?? 'youtube') == 'youtube')>
                                YouTube URL
                            </option>
                            <option value="upload"
                                @selected(old('preview_video_type', $course->preview_video_type ?? '') == 'upload')>
                                Uploaded
                            </option>
                        </select>
                    </div>
                    <input type="text" name="preview_video_url" class="form-control form-control-sm"
                           value="{{ old('preview_video_url', $course->preview_video_url ?? '') }}"
                           placeholder="https://www.youtube.com/watch?v=...">
                </div>
            </div>

            {{-- ── CLASSIFICATION ─────────────────────────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-tags me-1"></i> Classification</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Mode</label>
                        <select name="mode" class="form-select form-select-sm">
                            <option value="online"  @selected(old('mode', $course->mode ?? 'online') == 'online')>Online</option>
                            <option value="offline" @selected(old('mode', $course->mode ?? '') == 'offline')>Offline</option>
                            <option value="hybrid"  @selected(old('mode', $course->mode ?? '') == 'hybrid')>Hybrid</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-select form-select-sm">
                            <option value="beginner"     @selected(old('level', $course->level ?? 'beginner') == 'beginner')>Beginner</option>
                            <option value="intermediate" @selected(old('level', $course->level ?? '') == 'intermediate')>Intermediate</option>
                            <option value="advanced"     @selected(old('level', $course->level ?? '') == 'advanced')>Advanced</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Language</label>
                        <select name="language" class="form-select form-select-sm">
                            <option value="hindi"    @selected(old('language', $course->language ?? 'hindi') == 'hindi')>Hindi</option>
                            <option value="english"  @selected(old('language', $course->language ?? '') == 'english')>English</option>
                            <option value="gujarati" @selected(old('language', $course->language ?? '') == 'gujarati')>Gujarati</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- ── CERTIFICATE ─────────────────────────────────────────── --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-certificate me-1"></i> Certificate</h5>
                </div>
                <div class="card-body">
                    <label class="cert-toggle-label">
                        <input type="checkbox" name="has_certificate" value="1"
                               id="certToggle" style="display:none;"
                               @checked(old('has_certificate', $course->has_certificate ?? false))>
                        <i class="las la-certificate cert-icon"></i>
                        <div>
                            <div class="cert-text-main">Certificate of Completion</div>
                            <div class="cert-text-sub">Students receive a certificate after finishing this course</div>
                        </div>
                        <div style="margin-left:auto;flex-shrink:0;">
                            <span class="badge badge--success" id="certBadgeOn"
                                  style="{{ (old('has_certificate', $course->has_certificate ?? false)) ? '' : 'display:none;' }}">
                                ✓ Enabled
                            </span>
                            <span class="badge badge--secondary" id="certBadgeOff"
                                  style="{{ (old('has_certificate', $course->has_certificate ?? false)) ? 'display:none;' : '' }}">
                                Disabled
                            </span>
                        </div>
                    </label>
                    <small class="text-muted d-block mt-2">
                        <i class="las la-info-circle"></i>
                        When enabled, a "Certificate Available" badge is shown on the course detail page.
                    </small>
                </div>
            </div>

        </div>{{-- /col-xl-4 --}}
    </div>{{-- /row --}}
</form>

@endsection

@push('script')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// ── 1. THUMBNAIL PREVIEW ────────────────────────────────────────────────────
document.getElementById('thumbnailInput').addEventListener('change', function () {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        var img         = document.getElementById('thumbPreview');
        var placeholder = document.getElementById('thumbPlaceholder');
        img.src          = e.target.result;
        img.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
});

// ── 2. PRICE CALCULATOR ─────────────────────────────────────────────────────
var mrpInput      = document.getElementById('mrpInput');
var discountInput = document.getElementById('discountInput');
var priceInput    = document.getElementById('priceInput');
var pricePreview  = document.getElementById('pricePreview');
var ppPrice       = document.getElementById('ppPrice');
var ppMrp         = document.getElementById('ppMrp');
var ppDisc        = document.getElementById('ppDisc');

function recalcPrice() {
    var mrp  = parseFloat(mrpInput.value) || 0;
    var disc = parseFloat(discountInput.value) || 0;

    if (mrp <= 0) {
        pricePreview.style.display = 'none';
        priceInput.value = '';
        return;
    }

    if (disc > 0 && disc <= 100) {
        var price = Math.round(mrp * (1 - disc / 100));
        priceInput.value = price;
        ppPrice.textContent = '₹' + price.toLocaleString('en-IN');
        ppMrp.textContent   = '₹' + mrp.toLocaleString('en-IN');
        ppDisc.textContent  = Math.round(disc) + '% off';
        ppDisc.style.display = 'inline-block';
    } else {
        priceInput.value = mrp;
        ppPrice.textContent = '₹' + mrp.toLocaleString('en-IN');
        ppMrp.textContent   = '';
        ppDisc.style.display = 'none';
    }

    pricePreview.style.display = 'block';
}

if (mrpInput)      mrpInput.addEventListener('input', recalcPrice);
if (discountInput) discountInput.addEventListener('input', recalcPrice);

// Init on page load (edit mode)
recalcPrice();

// ── 3. PAID / FREE TOGGLE ───────────────────────────────────────────────────
function togglePriceFields() {
    var isFree = document.getElementById('priceType').value === 'free';
    document.querySelectorAll('.paidFields').forEach(function (f) {
        f.style.display = isFree ? 'none' : '';
    });
    if (isFree) {
        pricePreview.style.display = 'none';
        priceInput.value = 0;
    }
}
document.getElementById('priceType').addEventListener('change', togglePriceFields);
togglePriceFields();

// ── 4. TRAINER MULTI-SELECT ─────────────────────────────────────────────────
var selectedTrainerIds = {!! json_encode($selectedTrainers) !!};

function toggleTrainer(el, id) {
    var idx = selectedTrainerIds.indexOf(id);
    if (idx === -1) {
        selectedTrainerIds.push(id);
        el.classList.add('selected');
    } else {
        selectedTrainerIds.splice(idx, 1);
        el.classList.remove('selected');
    }
    rebuildTrainerInputs();
    document.getElementById('trainerCount').textContent = selectedTrainerIds.length + ' selected';
}

function rebuildTrainerInputs() {
    var container = document.getElementById('trainerHiddenInputs');
    container.innerHTML = '';
    selectedTrainerIds.forEach(function (id) {
        var inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'trainer_ids[]';
        inp.value = id;
        container.appendChild(inp);
    });
}

// ── 5. CERTIFICATE TOGGLE ───────────────────────────────────────────────────
document.querySelector('.cert-toggle-label').addEventListener('click', function () {
    var cb  = document.getElementById('certToggle');
    cb.checked = !cb.checked;
    document.getElementById('certBadgeOn').style.display  = cb.checked ? '' : 'none';
    document.getElementById('certBadgeOff').style.display = cb.checked ? 'none' : '';
});

// ── 6. FAQ BUILDER ──────────────────────────────────────────────────────────
var newFaqCounter = 0;
var courseId      = {{ $editing ? $course->id : 'null' }};

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
                'onclick="event.stopPropagation(); this.closest(\'.faq-item\').remove(); updateFaqCounter();">' +
                '<i class="las la-trash"></i>' +
            '</button>' +
            '<i class="las la-angle-down" style="color:#aaa;font-size:13px;"></i>' +
        '</div>' +
        '<div class="faq-item-body open">' +
            '<div class="mb-2">' +
                '<label class="form-label">Question</label>' +
                '<input type="text" name="new_faqs[' + idx + '][question]" class="form-control form-control-sm" ' +
                    'placeholder="e.g. What is options trading?" ' +
                    'oninput="this.closest(\'.faq-item\').querySelector(\'.faq-question-text\').textContent = this.value || \'New FAQ ' + idx + '\'">' +
            '</div>' +
            '<div class="mb-0">' +
                '<label class="form-label">Answer</label>' +
                '<textarea name="new_faqs[' + idx + '][answer]" class="form-control form-control-sm" rows="3" ' +
                    'placeholder="Type the answer here..."></textarea>' +
            '</div>' +
        '</div>' +
    '</div>';

    document.getElementById('newFaqRows').insertAdjacentHTML('beforeend', html);
}

function toggleFaqBody(header) {
    header.classList.toggle('open');
    var body = header.nextElementSibling;
    if (body) body.classList.toggle('open');
}

function updateFaqCounter() {
    // no-op, just for cleanup
}

// Save existing FAQ via AJAX (blur on input)
function saveFaq(inputEl, faqId) {
    if (!courseId || !faqId) return;
    var item     = inputEl.closest('.faq-item');
    var question = item.querySelector('.faq-q-input').value.trim();
    var answer   = item.querySelector('.faq-a-input').value.trim();
    if (!question || !answer) return;

    fetch('/admin/courses/faqs/' + faqId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ question: question, answer: answer })
    });
    item.querySelector('.faq-question-text').textContent = question;
}

// Delete existing FAQ via AJAX
function deleteFaq(btn, faqId) {
    if (!confirm('Delete this FAQ?')) return;
    if (faqId && courseId) {
        fetch('/admin/courses/faqs/' + faqId, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
    }
    btn.closest('.faq-item').remove();
}

// ── 7. SAVE new FAQs with form submit ──────────────────────────────────────
// new_faqs[idx][question] and new_faqs[idx][answer] are submitted with form
// Handle them in controller store/update

// ── FAQ SORTABLE ────────────────────────────────────────────────────────────
var faqContainer = document.getElementById('faqContainer');
if (faqContainer && typeof Sortable !== 'undefined') {
    Sortable.create(faqContainer, {
        handle: '.faq-drag-handle',
        animation: 150,
        onEnd: function () {
            var order = [];
            faqContainer.querySelectorAll('.faq-item[data-faq-id]').forEach(function (el) {
                order.push(el.dataset.faqId);
            });
            if (order.length && courseId) {
                fetch('/admin/courses/faqs/reorder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ order: order })
                });
            }
        }
    });
}
</script>

{{-- Handle new FAQs on form submit inside controller (store/update) --}}
{{-- Add this to CourseController store() and update() after $course is saved: --}}
{{--
    if ($request->has('new_faqs')) {
        $maxOrder = $course->faqs()->max('sort_order') ?? 0;
        foreach ($request->new_faqs as $faq) {
            if (!empty($faq['question']) && !empty($faq['answer'])) {
                $course->faqs()->create([
                    'question'   => $faq['question'],
                    'answer'     => $faq['answer'],
                    'sort_order' => ++$maxOrder,
                ]);
            }
        }
    }
--}}
@endpush