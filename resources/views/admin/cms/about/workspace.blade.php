{{-- FILE: resources/views/admin/cms/about/workspace.blade.php --}}
@extends('admin.layouts.app')
@section('panel')

{{-- Section heading --}}
<div class="card b-radius--10 mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <a href="{{ route('admin.cms.about.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
        <h5 class="card-title mb-0">Workspace Section</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.cms.about.workspace.update') }}" method="POST" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Section Heading</label>
                <input type="text" name="heading" class="form-control"
                       value="{{ old('heading', $workspace->heading ?? 'Our Workspace') }}" placeholder="Our Workspace">
            </div>
            <div class="col-md-6">
                <label class="form-label">Sub Text</label>
                <input type="text" name="sub" class="form-control"
                       value="{{ old('sub', $workspace->sub ?? '') }}"
                       placeholder="Where ideas meet execution...">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn--primary w-100"><i class="las la-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    {{-- PHOTO SLIDES --}}
    <div class="col-xl-6 mb-4">
        <div class="card b-radius--10">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Photo Slides <small class="text-muted fw-normal">({{ $slides->count() }})</small></h5>
            </div>
            <div class="card-body">
                {{-- Add slide --}}
                <form action="{{ route('admin.cms.about.workspace.slides.store') }}" method="POST" enctype="multipart/form-data" class="mb-3 p-3 border rounded">
                    @csrf
                    <div class="mb-2"><label class="form-label">Photo</label><input type="file" name="image" class="form-control form-control-sm" accept="image/*"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-8"><label class="form-label required">Caption</label><input type="text" name="caption" class="form-control form-control-sm" placeholder="Belgaum HQ — Main Trading Floor" required></div>
                        <div class="col-4"><label class="form-label">Tag</label><input type="text" name="tag" class="form-control form-control-sm" placeholder="HQ"></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Sub Caption</label><input type="text" name="sub_caption" class="form-control form-control-sm" placeholder="Lower Parel, Belgaum"></div>
                    <button type="submit" class="btn btn--primary btn--sm w-100"><i class="las la-plus"></i> Add Slide</button>
                </form>

                {{-- Existing --}}
                @foreach($slides as $slide)
                <div class="border rounded mb-2 overflow-hidden">
                    @if($slide->image_url)
                    <img src="{{ $slide->image_url }}" style="width:100%;height:100px;object-fit:cover;">
                    @endif
                    <form action="{{ route('admin.cms.about.workspace.slides.update', $slide) }}" method="POST" enctype="multipart/form-data" class="p-2">
                        @csrf @method('PUT')
                        <div class="mb-1"><input type="file" name="image" class="form-control form-control-sm" accept="image/*"></div>
                        <div class="row g-1 mb-1">
                            <div class="col-8"><input type="text" name="caption" class="form-control form-control-sm" value="{{ $slide->caption }}" required></div>
                            <div class="col-4"><input type="text" name="tag" class="form-control form-control-sm" value="{{ $slide->tag }}"></div>
                        </div>
                        <div class="row g-1 mb-1">
                            <div class="col-6"><input type="text" name="sub_caption" class="form-control form-control-sm" value="{{ $slide->sub_caption }}" placeholder="Sub caption"></div>
                            <div class="col-3">
                                <select name="status" class="form-select form-select-sm"><option value="1" @selected($slide->status==1)>Active</option><option value="0" @selected($slide->status==0)>Off</option></select>
                            </div>
                            <div class="col-3 d-flex gap-1">
                                <button type="submit" class="btn btn--primary btn--sm flex-grow-1"><i class="las la-save"></i></button>
                                <button type="button" class="btn btn--danger btn--sm"
                                        onclick="if(confirm('Delete?')){document.getElementById('delSlide{{ $slide->id }}').submit()}">
                                    <i class="las la-trash"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                    <form id="delSlide{{ $slide->id }}" action="{{ route('admin.cms.about.workspace.slides.destroy', $slide) }}" method="POST" style="display:none;">@csrf @method('DELETE')</form>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- CITY OFFICES --}}
    <div class="col-xl-6 mb-4">
        <div class="card b-radius--10">
            <div class="card-header"><h5 class="card-title mb-0">City Offices <small class="text-muted fw-normal">({{ $offices->count() }})</small></h5></div>
            <div class="card-body">
                {{-- Add office --}}
                <form action="{{ route('admin.cms.about.offices.store') }}" method="POST" enctype="multipart/form-data" class="mb-3 p-3 border rounded">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label required">City Name</label><input type="text" name="city" class="form-control form-control-sm" placeholder="Bangalore" required></div>
                        <div class="col-3"><label class="form-label">Flag Emoji</label><input type="text" name="flag" class="form-control form-control-sm" placeholder="🌿"></div>
                        <div class="col-3"><label class="form-label">Tag</label><input type="text" name="tag" class="form-control form-control-sm" placeholder="TECH HUB"></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Office Photo</label><input type="file" name="photo" class="form-control form-control-sm" accept="image/*"></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="desc" class="form-control form-control-sm" rows="2"></textarea></div>
                    <div class="mb-2"><label class="form-label">Address</label><input type="text" name="address" class="form-control form-control-sm" placeholder="Koramangala, Bengaluru — 560034"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Team</label><input type="text" name="team" class="form-control form-control-sm" placeholder="35+ engineers"></div>
                        <div class="col-6"><label class="form-label">Hours</label><input type="text" name="hours" class="form-control form-control-sm" placeholder="Mon–Fri, 9–6 PM"></div>
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm w-100"><i class="las la-plus"></i> Add Office</button>
                </form>

                {{-- Existing --}}
                @foreach($offices as $office)
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>{{ $office->flag }} {{ $office->city }}</strong>
                        <div class="d-flex gap-1">
                            <button class="btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#editOffice{{ $office->id }}"><i class="las la-pen"></i></button>
                            <form action="{{ route('admin.cms.about.offices.destroy', $office) }}" method="POST" style="display:inline;">@csrf @method('DELETE')
                                <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Delete?')"><i class="las la-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <small class="text-muted d-block">{{ Str::limit($office->address, 60) }}</small>
                </div>

                <div class="modal fade" id="editOffice{{ $office->id }}" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Edit: {{ $office->city }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <form action="{{ route('admin.cms.about.offices.update', $office) }}" method="POST" enctype="multipart/form-data">
                                @csrf @method('PUT')
                                <div class="modal-body">
                                    <div class="row g-2">
                                        <div class="col-6 mb-2"><label class="form-label">City</label><input type="text" name="city" class="form-control form-control-sm" value="{{ $office->city }}" required></div>
                                        <div class="col-3 mb-2"><label class="form-label">Flag</label><input type="text" name="flag" class="form-control form-control-sm" value="{{ $office->flag }}"></div>
                                        <div class="col-3 mb-2"><label class="form-label">Tag</label><input type="text" name="tag" class="form-control form-control-sm" value="{{ $office->tag }}"></div>
                                        <div class="col-12 mb-2"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control form-control-sm" accept="image/*">@if($office->photo_url)<img src="{{ $office->photo_url }}" style="height:40px;border-radius:5px;margin-top:4px;">@endif</div>
                                        <div class="col-12 mb-2"><label class="form-label">Description</label><textarea name="desc" class="form-control form-control-sm" rows="3">{{ $office->desc }}</textarea></div>
                                        <div class="col-12 mb-2"><label class="form-label">Address</label><input type="text" name="address" class="form-control form-control-sm" value="{{ $office->address }}"></div>
                                        <div class="col-6 mb-2"><label class="form-label">Team</label><input type="text" name="team" class="form-control form-control-sm" value="{{ $office->team }}"></div>
                                        <div class="col-6 mb-2"><label class="form-label">Hours</label><input type="text" name="hours" class="form-control form-control-sm" value="{{ $office->hours }}"></div>
                                        <div class="col-4"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm"><option value="1" @selected($office->status==1)>Active</option><option value="0" @selected($office->status==0)>Inactive</option></select></div>
                                    </div>
                                </div>
                                <div class="modal-footer"><button type="submit" class="btn btn--primary btn--sm"><i class="las la-save"></i> Save</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
