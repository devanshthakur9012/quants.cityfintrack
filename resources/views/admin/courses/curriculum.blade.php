{{-- FILE: resources/views/admin/courses/curriculum.blade.php --}}
@extends('admin.layouts.app')

@push('style')
<style>
/* ── Curriculum layout ──────────────────────────────────────────────────── */
.curriculum-section {
    border: 1px solid #dee2e6;
    border-radius: 10px;
    margin-bottom: 16px;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    transition: box-shadow .2s;
}
.curriculum-section:hover { box-shadow: 0 3px 12px rgba(0,0,0,.08); }

.section-header {
    background: linear-gradient(135deg, #f8f9fd 0%, #f1f4fb 100%);
    padding: 14px 16px;
    border-radius: 10px 10px 0 0;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: default;
    border-bottom: 1px solid #eaedf3;
}
.section-drag-handle { color: #bbb; cursor: grab; font-size: 18px; flex-shrink: 0; }
.section-drag-handle:active { cursor: grabbing; }
.section-title {
    font-weight: 700;
    font-size: 14px;
    flex: 1;
    color: #0f1b2d;
    min-width: 0;
}
.section-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    flex-wrap: wrap;
}
.section-meta-pill {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 20px;
    font-weight: 600;
    white-space: nowrap;
}
.pill-lessons  { background: #e8f0fe; color: #1a56db; }
.pill-duration { background: #e8faf0; color: #0a6640; }
.pill-preview  { background: #fff3e0; color: #b45309; }

/* ── Section preview video strip ────────────────────────────────────────── */
.section-preview-strip {
    background: #fafbff;
    border-bottom: 1px solid #eaedf3;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #4a5568;
}
.section-preview-strip i { color: #1a56db; font-size: 16px; }
.section-preview-strip a { color: #1a56db; font-weight: 600; text-decoration: none; }
.section-preview-strip a:hover { text-decoration: underline; }

/* ── Lesson list ────────────────────────────────────────────────────────── */
.lesson-list { padding: 10px 14px 4px; }
.lesson-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #eaedf3;
    border-radius: 8px;
    margin-bottom: 6px;
    background: #fdfdff;
    cursor: default;
    transition: box-shadow .15s, border-color .15s;
    position: relative;
}
.lesson-item:hover {
    box-shadow: 0 2px 8px rgba(26,86,219,.08);
    border-color: #c3d3f5;
}
.lesson-drag-handle {
    color: #ccc;
    font-size: 16px;
    cursor: grab;
    flex-shrink: 0;
}
.lesson-drag-handle:active { cursor: grabbing; }
.lesson-title {
    flex: 1;
    font-size: 13px;
    font-weight: 500;
    color: #1a202c;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.lesson-badges {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
    flex-wrap: wrap;
}
.lesson-duration-badge {
    font-size: 11px;
    color: #6b7280;
    background: #f3f4f6;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 500;
    white-space: nowrap;
}
.lesson-preview-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #f59e0b;
    flex-shrink: 0;
    title: "Has overview video";
}
.lesson-video-type {
    font-size: 10px;
    padding: 2px 7px;
    border-radius: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.vt-youtube  { background: #fde8e8; color: #c53030; }
.vt-upload   { background: #dbeafe; color: #1e3a8a; }
.vt-none     { background: #f3f4f6; color: #9ca3af; }
.status-dot  {
    width: 7px; height: 7px; border-radius: 50%;
    flex-shrink: 0;
}
.status-dot.active   { background: #10b981; }
.status-dot.inactive { background: #ef4444; }

.empty-section {
    text-align: center;
    padding: 24px;
    color: #aaa;
    font-size: 13px;
    border: 1px dashed #e0e0e0;
    border-radius: 8px;
    margin: 6px 14px 10px;
    background: #fafafa;
}
.dragging { opacity: 0.45; transform: scale(.98); }

/* ── Add lesson CTA ─────────────────────────────────────────────────────── */
.add-lesson-strip {
    padding: 8px 14px 12px;
}

/* ── Section video modal preview ────────────────────────────────────────── */
.yt-embed-wrap {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
    border-radius: 8px;
    background: #000;
}
.yt-embed-wrap iframe {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    border: 0;
}

/* ── Stats cards top ────────────────────────────────────────────────────── */
.curriculum-stat-card {
    background: #fff;
    border: 1px solid #eaedf3;
    border-radius: 10px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
}
.curriculum-stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.curriculum-stat-val  { font-size: 22px; font-weight: 800; color: #0f1b2d; line-height: 1.1; }
.curriculum-stat-lbl  { font-size: 12px; color: #6b7280; margin-top: 2px; }
</style>
@endpush

@section('panel')

{{-- ── TOP STAT STRIP ───────────────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-xl-3 col-sm-6">
        <div class="curriculum-stat-card">
            <div class="curriculum-stat-icon" style="background:#e8f0fe;">
                <i class="las la-layer-group" style="color:#1a56db;"></i>
            </div>
            <div>
                <div class="curriculum-stat-val">{{ $sections->count() }}</div>
                <div class="curriculum-stat-lbl">Sections</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="curriculum-stat-card">
            <div class="curriculum-stat-icon" style="background:#e8faf0;">
                <i class="las la-book-open" style="color:#0a6640;"></i>
            </div>
            <div>
                <div class="curriculum-stat-val">{{ $totalLessons }}</div>
                <div class="curriculum-stat-lbl">Total Lessons</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="curriculum-stat-card">
            <div class="curriculum-stat-icon" style="background:#fff3e0;">
                <i class="las la-clock" style="color:#b45309;"></i>
            </div>
            <div>
                <div class="curriculum-stat-val">{{ $totalDuration ?: '—' }}</div>
                <div class="curriculum-stat-lbl">Total Duration</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="curriculum-stat-card">
            <div class="curriculum-stat-icon" style="background:#fce7f3;">
                <i class="las la-video" style="color:#9d174d;"></i>
            </div>
            <div>
                <div class="curriculum-stat-val">
                    {{ $sections->sum(fn($s) => $s->lessons->where('video_type','upload')->count()) }}
                </div>
                <div class="curriculum-stat-lbl">Uploaded Videos</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h5 class="mb-0">{{ $course->title }}</h5>
                <small class="text-muted">Curriculum Builder</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn--secondary btn--sm">
                    <i class="las la-edit"></i> Edit Course
                </a>
                <button class="btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <i class="las la-plus"></i> Add Section
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8">

        {{-- CURRICULUM BUILDER ─────────────────────────────────────────── --}}
        <div id="sectionList">
            @forelse($sections as $section)
            @php
                $sectionLessonCount = $section->lessons->count();
                $sectionDuration    = $section->total_duration;
                $sectionSeconds     = $section->total_duration_seconds;
            @endphp
            <div class="curriculum-section" data-section-id="{{ $section->id }}">

                {{-- Section Header --}}
                <div class="section-header">
                    <span class="section-drag-handle"><i class="las la-grip-vertical"></i></span>

                    <div class="section-title" style="min-width:0;flex:1;">
                        <span>{{ $section->title }}</span>
                        @if($section->description)
                        <div style="font-size:11px;color:#6b7280;font-weight:400;margin-top:2px;white-space:normal;">
                            {{ Str::limit($section->description, 80) }}
                        </div>
                        @endif
                    </div>

                    <div class="section-meta">
                        <span class="section-meta-pill pill-lessons">
                            <i class="las la-book me-1"></i>{{ $sectionLessonCount }} lesson{{ $sectionLessonCount != 1 ? 's' : '' }}
                        </span>
                        @if($sectionSeconds > 0)
                        <span class="section-meta-pill pill-duration">
                            <i class="las la-clock me-1"></i>{{ $sectionDuration }}
                        </span>
                        @endif
                        @if($section->has_preview)
                        <span class="section-meta-pill pill-preview">
                            <i class="las la-play-circle me-1"></i>Overview
                        </span>
                        @endif
                    </div>

                    {{-- Section Actions --}}
                    <div class="dropdown ms-1">
                        <button class="btn btn-sm btn--light-secondary" data-bs-toggle="dropdown" style="padding:4px 10px;">
                            <i class="las la-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item editSectionBtn" href="#"
                                   data-id="{{ $section->id }}"
                                   data-title="{{ $section->title }}"
                                   data-description="{{ $section->description }}"
                                   data-preview-type="{{ $section->preview_video_type ?? 'none' }}"
                                   data-preview-url="{{ $section->preview_video_url }}">
                                    <i class="las la-pen me-1"></i> Edit Section
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-success"
                                   href="{{ route('admin.courses.lessons.create', [$course, $section]) }}">
                                    <i class="las la-plus me-1"></i> Add Lesson
                                </a>
                            </li>
                            @if($section->has_preview)
                            <li>
                                <a class="dropdown-item previewSectionVideoBtn" href="#"
                                   data-type="{{ $section->preview_video_type }}"
                                   data-url="{{ $section->preview_video_url }}"
                                   data-embed="{{ $section->preview_embed_id }}"
                                   data-title="{{ $section->title }}">
                                    <i class="las la-play-circle me-1"></i> Preview Video
                                </a>
                            </li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger confirmationBtn" href="#"
                                   data-action="{{ route('admin.courses.sections.destroy', $section) }}"
                                   data-question="Delete this section and all its lessons?">
                                    <i class="las la-trash me-1"></i> Delete Section
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- Section overview video strip --}}
                @if($section->has_preview)
                <div class="section-preview-strip">
                    <i class="las la-film"></i>
                    <span>Section overview:</span>
                    @if($section->preview_video_type === 'youtube' && $section->preview_embed_id)
                        <a href="#" class="previewSectionVideoBtn"
                           data-type="youtube"
                           data-embed="{{ $section->preview_embed_id }}"
                           data-title="{{ $section->title }}">
                            <i class="lab la-youtube text-danger"></i> Watch YouTube Preview
                        </a>
                    @elseif($section->preview_video_type === 'upload' && $section->preview_video_path)
                        <span class="text--muted"><i class="las la-video"></i> Uploaded video stored</span>
                    @endif
                </div>
                @endif

                {{-- Lessons --}}
                <div class="lesson-list sortable-lessons" data-section-id="{{ $section->id }}">
                    @forelse($section->lessons as $lesson)
                    <div class="lesson-item" data-lesson-id="{{ $lesson->id }}">
                        <span class="lesson-drag-handle"><i class="las la-grip-vertical"></i></span>

                        {{-- Status dot --}}
                        <span class="status-dot {{ $lesson->status ? 'active' : 'inactive' }}"
                              title="{{ $lesson->status ? 'Active' : 'Hidden' }}"></span>

                        {{-- Main video type icon --}}
                        @if($lesson->video_type === 'youtube')
                            <i class="lab la-youtube" title="YouTube" style="color:#c53030;font-size:17px;flex-shrink:0;"></i>
                        @elseif($lesson->video_type === 'upload')
                            <i class="las la-video" title="Uploaded Video" style="color:#1a56db;font-size:17px;flex-shrink:0;"></i>
                        @else
                            <i class="las la-minus-circle" title="No Video" style="color:#d1d5db;font-size:17px;flex-shrink:0;"></i>
                        @endif

                        <span class="lesson-title" title="{{ $lesson->title }}">{{ $lesson->title }}</span>

                        <div class="lesson-badges">
                            {{-- Overview video dot --}}
                            @if($lesson->has_preview)
                            <span class="lesson-preview-dot" title="Has overview video"
                                  style="width:8px;height:8px;border-radius:50%;background:#f59e0b;display:inline-block;"></span>
                            @endif

                            {{-- Duration --}}
                            @if($lesson->duration_seconds)
                            <span class="lesson-duration-badge">
                                <i class="las la-clock" style="font-size:10px;"></i>
                                {{ $lesson->formatted_duration }}
                            </span>
                            @endif

                            {{-- Attachment --}}
                            @if($lesson->attachment)
                            <i class="las la-paperclip text-muted" title="Has attachment" style="font-size:13px;"></i>
                            @endif
                        </div>

                        <div class="d-flex gap-1 flex-shrink-0">
                            <a href="{{ route('admin.courses.lessons.edit', [$course, $lesson]) }}"
                               class="btn btn--primary btn--sm" style="padding:4px 9px;" title="Edit">
                                <i class="las la-pen"></i>
                            </a>
                            <button class="btn btn--danger btn--sm confirmationBtn"
                                    style="padding:4px 9px;"
                                    data-action="{{ route('admin.courses.lessons.destroy', [$course, $lesson]) }}"
                                    data-question="Delete this lesson? Uploaded videos will be removed."
                                    title="Delete">
                                <i class="las la-trash"></i>
                            </button>
                        </div>
                    </div>
                    @empty
                    <div class="empty-section">
                        <i class="las la-video d-block mb-1" style="font-size:28px;opacity:.35;"></i>
                        No lessons yet —
                        <a href="{{ route('admin.courses.lessons.create', [$course, $section]) }}">Add first lesson</a>
                    </div>
                    @endforelse
                </div>

                <div class="add-lesson-strip">
                    <a href="{{ route('admin.courses.lessons.create', [$course, $section]) }}"
                       class="btn btn--outline-primary btn--sm w-100" style="font-size:12px;">
                        <i class="las la-plus"></i> Add Lesson to "{{ Str::limit($section->title, 35) }}"
                    </a>
                </div>

            </div>
            @empty
            <div class="card b-radius--10">
                <div class="card-body text-center py-5">
                    <i class="las la-layer-group" style="font-size:56px;opacity:.2;display:block;margin-bottom:12px;"></i>
                    <h5>No sections yet</h5>
                    <p class="text-muted mb-3">Start building your course curriculum by adding sections.</p>
                    <button class="btn btn--primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                        <i class="las la-plus"></i> Add First Section
                    </button>
                </div>
            </div>
            @endforelse
        </div>

        @if($sections->count())
        <div class="text-center mt-2 mb-4">
            <button class="btn btn--outline-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                <i class="las la-plus"></i> Add Another Section
            </button>
        </div>
        @endif
    </div>

    {{-- SIDEBAR ─────────────────────────────────────────────────────────── --}}
    <div class="col-xl-4">
        <div class="card b-radius--10 mb-3">
            <div class="card-header"><h6 class="card-title mb-0">Course Summary</h6></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Status</span>
                        {!! $course->status_badge !!}
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Sections</span>
                        <strong>{{ $sections->count() }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Total Lessons</span>
                        <strong>{{ $totalLessons }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Total Duration</span>
                        <strong>{{ $totalDuration ?: '—' }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Mode</span>
                        <span>{{ ucfirst($course->mode) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Level</span>
                        <span>{{ ucfirst($course->level) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Language</span>
                        <span>{{ ucfirst($course->language) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Price</span>
                        @if($course->type === 'free')
                            <span class="badge badge--success">FREE</span>
                        @else
                            <strong>{{ $course->formatted_price }}</strong>
                        @endif
                    </li>
                </ul>
            </div>
        </div>

        {{-- Section breakdown --}}
        @if($sections->count())
        <div class="card b-radius--10 mb-3">
            <div class="card-header"><h6 class="card-title mb-0">Section Breakdown</h6></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach($sections as $sec)
                    <li class="list-group-item d-flex justify-content-between align-items-start py-2">
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:12px;font-weight:600;color:#1a202c;
                                white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;">
                                {{ $sec->title }}
                            </div>
                            <div style="font-size:11px;color:#9ca3af;">
                                {{ $sec->lessons->count() }} lesson{{ $sec->lessons->count() != 1 ? 's' : '' }}
                            </div>
                        </div>
                        <span style="font-size:11px;color:#6b7280;white-space:nowrap;flex-shrink:0;margin-left:8px;">
                            {{ $sec->total_duration_seconds > 0 ? $sec->total_duration : '—' }}
                        </span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        @if($course->thumbnail)
        <div class="card b-radius--10">
            <div class="card-body p-2">
                <img src="{{ $course->thumbnail_url }}" class="rounded w-100"
                     style="object-fit:cover;max-height:160px;">
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ── ADD SECTION MODAL ─────────────────────────────────────────────────── --}}
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="las la-layer-group me-1"></i> Add Section</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.courses.sections.store', $course) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Section Title</label>
                        <input type="text" name="title" class="form-control" required
                               placeholder="e.g. Module 1: Introduction to Options">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <small class="text-muted">(optional)</small></label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Brief overview of what this section covers"></textarea>
                    </div>

                    {{-- Section overview video --}}
                    <div class="mb-3">
                        <label class="form-label">Section Overview Video <small class="text-muted">(optional — shown to students before purchase)</small></label>
                        <div class="d-flex gap-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="preview_video_type" value="none" id="addSecNone" checked>
                                <label class="form-check-label" for="addSecNone">None</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="preview_video_type" value="youtube" id="addSecYT">
                                <label class="form-check-label" for="addSecYT">
                                    <i class="lab la-youtube text-danger"></i> YouTube URL
                                </label>
                            </div>
                        </div>
                        <div id="addSecYTUrl" style="display:none;">
                            <input type="text" name="preview_video_url" class="form-control"
                                   placeholder="https://www.youtube.com/watch?v=...">
                            <small class="text-muted">Free preview shown to non-enrolled students. Unlisted/public URL works.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--secondary btn--sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn--primary btn--sm"><i class="las la-plus"></i> Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── EDIT SECTION MODAL ────────────────────────────────────────────────── --}}
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="las la-pen me-1"></i> Edit Section</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Section Title</label>
                    <input type="text" id="editSectionTitle" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="editSectionDesc" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Section Overview Video <small class="text-muted">(optional)</small></label>
                    <div class="d-flex gap-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="editPreviewType" value="none" id="editSecNone">
                            <label class="form-check-label" for="editSecNone">None</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="editPreviewType" value="youtube" id="editSecYT">
                            <label class="form-check-label" for="editSecYT">
                                <i class="lab la-youtube text-danger"></i> YouTube URL
                            </label>
                        </div>
                    </div>
                    <div id="editSecYTUrl" style="display:none;">
                        <input type="text" id="editSectionPreviewUrl" class="form-control"
                               placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary btn--sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary btn--sm" id="saveSectionEditBtn">
                    <i class="las la-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── SECTION VIDEO PREVIEW MODAL ──────────────────────────────────────── --}}
<div class="modal fade" id="sectionPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sectionPreviewModalTitle">
                    <i class="las la-play-circle me-1"></i> Section Overview
                </h5>
                <button class="btn-close" data-bs-dismiss="modal"
                        onclick="clearSectionPreview()"></button>
            </div>
            <div class="modal-body p-3">
                <div id="sectionPreviewContent"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// ── Section drag-and-drop reorder ────────────────────────────────────────────
var sectionList = document.getElementById('sectionList');
if (sectionList) {
    Sortable.create(sectionList, {
        handle: '.section-drag-handle',
        animation: 150,
        ghostClass: 'dragging',
        onEnd: function() {
            var order = [];
            document.querySelectorAll('.curriculum-section').forEach(function(el) {
                order.push(el.dataset.sectionId);
            });
            fetch('{{ route('admin.courses.sections.reorder') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ order: order })
            });
        }
    });
}

// ── Lesson drag-and-drop reorder (per section) ───────────────────────────────
document.querySelectorAll('.sortable-lessons').forEach(function(container) {
    Sortable.create(container, {
        handle: '.lesson-drag-handle',
        animation: 150,
        ghostClass: 'dragging',
        onEnd: function() {
            var order = [];
            container.querySelectorAll('.lesson-item').forEach(function(el) {
                order.push(el.dataset.lessonId);
            });
            fetch('{{ route('admin.courses.lessons.reorder') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ order: order })
            });
        }
    });
});

// ── Add Section modal: toggle YT URL field ───────────────────────────────────
document.querySelectorAll('input[name="preview_video_type"]').forEach(function(r) {
    r.addEventListener('change', function() {
        document.getElementById('addSecYTUrl').style.display =
            this.value === 'youtube' ? '' : 'none';
    });
});

// ── Edit Section modal ───────────────────────────────────────────────────────
var currentSectionId = null;

document.querySelectorAll('.editSectionBtn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        currentSectionId = this.dataset.id;

        document.getElementById('editSectionTitle').value        = this.dataset.title;
        document.getElementById('editSectionDesc').value         = this.dataset.description || '';
        document.getElementById('editSectionPreviewUrl').value   = this.dataset.previewUrl || '';

        var type = this.dataset.previewType || 'none';
        document.querySelector('input[name="editPreviewType"][value="' + type + '"]').checked = true;
        document.getElementById('editSecYTUrl').style.display = (type === 'youtube') ? '' : 'none';

        new bootstrap.Modal(document.getElementById('editSectionModal')).show();
    });
});

document.querySelectorAll('input[name="editPreviewType"]').forEach(function(r) {
    r.addEventListener('change', function() {
        document.getElementById('editSecYTUrl').style.display =
            this.value === 'youtube' ? '' : 'none';
    });
});

document.getElementById('saveSectionEditBtn').addEventListener('click', function() {
    var title   = document.getElementById('editSectionTitle').value.trim();
    var desc    = document.getElementById('editSectionDesc').value.trim();
    var pvType  = document.querySelector('input[name="editPreviewType"]:checked').value;
    var pvUrl   = document.getElementById('editSectionPreviewUrl').value.trim();

    if (!title) { alert('Title is required'); return; }

    fetch('/admin/courses/sections/' + currentSectionId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({
            title: title,
            description: desc,
            preview_video_type: pvType,
            preview_video_url:  pvUrl,
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
    });
});

// ── Section preview video modal ──────────────────────────────────────────────
document.querySelectorAll('.previewSectionVideoBtn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var embedId = this.dataset.embed;
        var title   = this.dataset.title;

        document.getElementById('sectionPreviewModalTitle').innerHTML =
            '<i class="las la-play-circle me-1"></i> ' + title + ' — Overview';

        var html = '';
        if (embedId) {
            html = '<div class="yt-embed-wrap">' +
                '<iframe src="https://www.youtube.com/embed/' + embedId + '?autoplay=1" ' +
                'allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>' +
                '</div>';
        }
        document.getElementById('sectionPreviewContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('sectionPreviewModal')).show();
    });
});

// Clear YT iframe when modal closes (stop autoplay)
document.getElementById('sectionPreviewModal').addEventListener('hide.bs.modal', function() {
    document.getElementById('sectionPreviewContent').innerHTML = '';
});

function clearSectionPreview() {
    document.getElementById('sectionPreviewContent').innerHTML = '';
}
</script>
@endpush