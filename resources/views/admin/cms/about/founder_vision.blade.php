{{-- FILE: resources/views/admin/cms/about/founder_vision.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.about.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Founder Vision Section</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.about.founder_vision.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3 mb-4">
                        <div class="col-md-3 text-center">
                            @if($vision && $vision->avatar_url)
                                <img src="{{ $vision->avatar_url }}"
                                     style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid #7DFF00;display:block;margin:0 auto 8px;">
                            @else
                                <div style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,#1a3a6e,#d4840e);display:flex;align-items:center;justify-content:center;font-size:36px;color:#fff;margin:0 auto 8px;"><i class="fas fa-user-tie"></i></div>
                            @endif
                            <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*">
                            <small class="text-muted d-block mt-1">Profile photo</small>
                        </div>
                        <div class="col-md-9">
                            <div class="row g-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control"
                                           value="{{ old('name', $vision->name ?? '') }}" placeholder="Vitthal Tallur">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Title / Role</label>
                                    <input type="text" name="title" class="form-control"
                                           value="{{ old('title', $vision->title ?? '') }}" placeholder="Founder & CTO, CityQuants">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Signature Text</label>
                                    <input type="text" name="signature" class="form-control"
                                           value="{{ old('signature', $vision->signature ?? '') }}" placeholder="Vitthal Tallur">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">LinkedIn URL</label>
                                    <input type="text" name="linkedin" class="form-control"
                                           value="{{ old('linkedin', $vision->linkedin ?? '') }}" placeholder="https://...">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Twitter / X URL</label>
                                    <input type="text" name="twitter" class="form-control"
                                           value="{{ old('twitter', $vision->twitter ?? '') }}" placeholder="https://...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        Vision Paragraphs <small class="text-muted fw-normal">(each textarea = one paragraph)</small>
                    </h6>

                    <div id="parasContainer">
                        @php $paras = $vision->paragraphs_array ?? ['']; @endphp
                        @foreach($paras as $i => $para)
                        <div class="para-row mb-2 d-flex gap-2 align-items-start">
                            <div class="flex-grow-1">
                                <textarea name="paragraphs[]" class="form-control" rows="3"
                                          placeholder="Paragraph {{ $i+1 }}...">{{ $para }}</textarea>
                            </div>
                            <button type="button" class="btn btn--danger btn--sm mt-1"
                                    style="padding:6px 10px;flex-shrink:0;"
                                    onclick="if(document.querySelectorAll('.para-row').length > 1) this.closest('.para-row').remove()">
                                <i class="las la-trash"></i>
                            </button>
                        </div>
                        @endforeach
                        <div id="newParas"></div>
                    </div>

                    <button type="button" class="btn btn--secondary btn--sm mb-4" onclick="addPara()">
                        <i class="las la-plus"></i> Add Paragraph
                    </button>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save Founder Vision</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
function addPara() {
    var count = document.querySelectorAll('.para-row').length + 1;
    var html = '<div class="para-row mb-2 d-flex gap-2 align-items-start">' +
        '<div class="flex-grow-1">' +
        '<textarea name="paragraphs[]" class="form-control" rows="3" placeholder="Paragraph ' + count + '..."></textarea>' +
        '</div>' +
        '<button type="button" class="btn btn--danger btn--sm mt-1" style="padding:6px 10px;flex-shrink:0;"' +
        ' onclick="this.closest(\'.para-row\').remove()">' +
        '<i class="las la-trash"></i></button>' +
        '</div>';
    document.getElementById('newParas').insertAdjacentHTML('beforeend', html);
}
</script>
@endpush

@endsection
