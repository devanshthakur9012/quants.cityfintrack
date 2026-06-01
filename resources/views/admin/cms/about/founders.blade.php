{{-- FILE: resources/views/admin/cms/about/founders.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-xl-4 mb-4">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.about.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Add Founding Member</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.about.founders.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label required">Full Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="Vitthal Tallur">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Role / Title</label>
                        <input type="text" name="role" class="form-control form-control-sm" placeholder="Founder & CTO, CityQuants">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Credentials</label>
                        <input type="text" name="credentials" class="form-control form-control-sm" placeholder="CMT, CFA, CQF">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control form-control-sm" rows="4"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Photo</label>
                        <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">LinkedIn URL</label><input type="text" name="linkedin" class="form-control form-control-sm" placeholder="https://..."></div>
                        <div class="col-6"><label class="form-label">Twitter / X URL</label><input type="text" name="twitter" class="form-control form-control-sm" placeholder="https://..."></div>
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm w-100"><i class="las la-plus"></i> Add Member</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-header"><h5 class="card-title mb-0">Founding Members ({{ $founders->count() }})</h5></div>
            <div class="card-body">
                @if($founders->isEmpty())
                <div class="text-center py-4 text-muted"><i class="las la-user-tie" style="font-size:40px;opacity:.3;display:block;margin-bottom:8px;"></i> No founders yet.</div>
                @else
                @foreach($founders as $f)
                <div class="d-flex gap-3 align-items-start p-3 mb-3 border rounded" style="border-radius:10px!important;">
                    <div style="width:64px;height:64px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#1a3a6e,#d4840e);display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;overflow:hidden;">
                        @if($f->avatar_url)<img src="{{ $f->avatar_url }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">@else<i class="fas fa-user"></i>@endif
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong style="font-size:15px;">{{ $f->name }}</strong>
                                <div style="font-size:12px;color:#7DFF00;">{{ $f->role }}</div>
                                <div style="font-size:11px;color:#aaa;">{{ $f->credentials }}</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#editF{{ $f->id }}"><i class="las la-pen"></i></button>
                                <form action="{{ route('admin.cms.about.founders.destroy', $f) }}" method="POST" style="display:inline;">@csrf @method('DELETE')
                                    <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Delete?')"><i class="las la-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <p style="font-size:12.5px;color:#888;line-height:1.6;margin:6px 0 0;">{{ Str::limit($f->bio, 120) }}</p>
                    </div>
                </div>

                {{-- Edit modal --}}
                <div class="modal fade" id="editF{{ $f->id }}" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Edit: {{ $f->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <form action="{{ route('admin.cms.about.founders.update', $f) }}" method="POST" enctype="multipart/form-data">
                                @csrf @method('PUT')
                                <div class="modal-body">
                                    <div class="row g-2">
                                        <div class="col-6 mb-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control form-control-sm" value="{{ $f->name }}" required></div>
                                        <div class="col-6 mb-2"><label class="form-label">Role</label><input type="text" name="role" class="form-control form-control-sm" value="{{ $f->role }}"></div>
                                        <div class="col-6 mb-2"><label class="form-label">Credentials</label><input type="text" name="credentials" class="form-control form-control-sm" value="{{ $f->credentials }}"></div>
                                        <div class="col-6 mb-2">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select form-select-sm"><option value="1" @selected($f->status==1)>Active</option><option value="0" @selected($f->status==0)>Inactive</option></select>
                                        </div>
                                        <div class="col-12 mb-2"><label class="form-label">Bio</label><textarea name="bio" class="form-control form-control-sm" rows="4">{{ $f->bio }}</textarea></div>
                                        <div class="col-12 mb-2">
                                            <label class="form-label">Photo</label>
                                            <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*">
                                            @if($f->avatar_url)<img src="{{ $f->avatar_url }}" style="height:40px;border-radius:50%;margin-top:4px;">@endif
                                        </div>
                                        <div class="col-6 mb-2"><label class="form-label">LinkedIn</label><input type="text" name="linkedin" class="form-control form-control-sm" value="{{ $f->linkedin }}"></div>
                                        <div class="col-6 mb-2"><label class="form-label">Twitter / X</label><input type="text" name="twitter" class="form-control form-control-sm" value="{{ $f->twitter }}"></div>
                                    </div>
                                </div>
                                <div class="modal-footer"><button type="submit" class="btn btn--primary btn--sm"><i class="las la-save"></i> Save</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
