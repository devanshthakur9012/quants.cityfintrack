{{-- FILE: resources/views/admin/cms/home/cert_slides.blade.php --}}
@extends('admin.layouts.app')

@push('style')
<style>
.slide-card { border:1px solid #e5e9f2; border-radius:10px; overflow:hidden; background:#fff; transition:box-shadow .2s; }
.slide-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.08); }
.slide-thumb { width:100%; height:160px; object-fit:cover; display:block; background:#f0f2f7; }
.slide-thumb-ph { width:100%; height:160px; background:linear-gradient(135deg,#0f1b2d,#1a3050); display:flex; align-items:center; justify-content:center; color:rgba(245,166,35,.4); font-size:40px; }
</style>
@endpush

@section('panel')
<div class="row">
    {{-- ADD NEW --}}
    <div class="col-xl-4 mb-4">
        <div class="card b-radius--10 h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.home.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Add New Slide</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.home.cert_slides.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label required">Slide Image <small class="text-muted">(shown full-width in slider)</small></label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                        <small class="text-muted">Recommended: 960×420px</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Badge Text <small class="text-muted">(shown on image overlay)</small></label>
                        <input type="text" name="badge_text" class="form-control"
                               placeholder="e.g. Intermediate >> Advance Course">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Language Pill</label>
                        <input type="text" name="language" class="form-control" placeholder="e.g. In Hindi">
                    </div>
                    <button type="submit" class="btn btn--primary w-100"><i class="las la-plus"></i> Add Slide</button>
                </form>
            </div>
        </div>
    </div>

    {{-- EXISTING SLIDES --}}
    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-header"><h5 class="card-title mb-0">Existing Slides ({{ $slides->count() }})</h5></div>
            <div class="card-body">
                @if($slides->isEmpty())
                <div class="text-center py-4 text-muted"><i class="las la-images" style="font-size:40px;opacity:.3;display:block;margin-bottom:8px;"></i> No slides yet. Add your first one.</div>
                @else
                <div class="row g-3">
                    @foreach($slides as $slide)
                    <div class="col-md-6">
                        <div class="slide-card">
                            @if($slide->image_url)
                                <img src="{{ $slide->image_url }}" class="slide-thumb" alt="{{ $slide->badge_text }}">
                            @else
                                <div class="slide-thumb-ph"><i class="las la-image"></i></div>
                            @endif
                            <div style="padding:12px;">
                                <form action="{{ route('admin.cms.home.cert_slides.update', $slide) }}" method="POST" enctype="multipart/form-data">
                                    @csrf @method('PUT')
                                    <div class="mb-2">
                                        <label class="form-label" style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Replace Image</label>
                                        <input type="file" name="image" class="form-control form-control-sm" accept="image/*">
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="badge_text" class="form-control form-control-sm"
                                               value="{{ $slide->badge_text }}" placeholder="Badge text">
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="language" class="form-control form-control-sm"
                                               value="{{ $slide->language }}" placeholder="Language pill">
                                    </div>
                                    <div class="mb-2">
                                        <select name="status" class="form-select form-select-sm">
                                            <option value="1" @selected($slide->status == 1)>Active</option>
                                            <option value="0" @selected($slide->status == 0)>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn--primary btn--sm flex-grow-1"><i class="las la-save"></i> Save</button>
                                        <a href="{{ route('admin.cms.home.cert_slides.destroy', $slide) }}"
                                           onclick="return confirm('Delete this slide?')"
                                           class="btn btn--danger btn--sm"
                                           data-method="DELETE">
                                            <i class="las la-trash"></i>
                                        </a>
                                    </div>
                                </form>
                                {{-- Delete form --}}
                                <form id="del_cert_{{ $slide->id }}" action="{{ route('admin.cms.home.cert_slides.destroy', $slide) }}" method="POST" style="display:none;">
                                    @csrf @method('DELETE')
                                </form>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
document.querySelectorAll('[data-method="DELETE"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
        e.preventDefault();
        if (!confirm('Delete this slide?')) return;
        var id = this.href.split('/').pop();
        document.getElementById('del_cert_' + id.split('?')[0]).submit();
    });
});
</script>
@endpush

@endsection
