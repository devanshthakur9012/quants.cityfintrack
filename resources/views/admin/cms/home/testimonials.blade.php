{{-- FILE: resources/views/admin/cms/home/testimonials.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-xl-4 mb-4">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.home.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Add Testimonial</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.home.testimonials.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-2"><label class="form-label required">Name</label><input type="text" name="name" class="form-control form-control-sm" required placeholder="Ravi Bhatt"></div>
                    <div class="mb-2"><label class="form-label">Avatar <small class="text-muted">(optional)</small></label><input type="file" name="avatar" class="form-control form-control-sm" accept="image/*"></div>
                    <div class="mb-2">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select form-select-sm">
                            @for($r=5;$r>=1;$r--)<option value="{{ $r }}">{{ $r }} Star{{ $r>1?'s':'' }}</option>@endfor
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label required">Review</label><textarea name="review" class="form-control form-control-sm" rows="4" required></textarea></div>
                    <button type="submit" class="btn btn--primary btn--sm w-100"><i class="las la-plus"></i> Add</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-header"><h5 class="card-title mb-0">Testimonials ({{ $testimonials->count() }})</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead><tr><th>Avatar</th><th>Name</th><th>Rating</th><th>Review</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse($testimonials as $t)
                            <tr>
                                <td>
                                    @if($t->avatar_url)
                                        <img src="{{ $t->avatar_url }}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                    @else
                                        <div style="width:40px;height:40px;border-radius:50%;background:#1a56db;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">{{ strtoupper(substr($t->name,0,1)) }}</div>
                                    @endif
                                </td>
                                <td><strong>{{ $t->name }}</strong></td>
                                <td>
                                    @for($s=0;$s<$t->rating;$s++)<i class="fas fa-star text-warning" style="font-size:11px;"></i>@endfor
                                </td>
                                <td><small class="text-muted">{{ Str::limit($t->review, 60) }}</small></td>
                                <td>@if($t->status)<span class="badge badge--success">Active</span>@else<span class="badge badge--warning">Off</span>@endif</td>
                                <td>
                                    <button class="btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#editT{{ $t->id }}"><i class="las la-pen"></i></button>
                                    <form action="{{ route('admin.cms.home.testimonials.destroy', $t) }}" method="POST" style="display:inline;">@csrf @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Delete?')"><i class="las la-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <div class="modal fade" id="editT{{ $t->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">Edit: {{ $t->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <form action="{{ route('admin.cms.home.testimonials.update', $t) }}" method="POST" enctype="multipart/form-data">
                                            @csrf @method('PUT')
                                            <div class="modal-body">
                                                <div class="mb-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control form-control-sm" value="{{ $t->name }}" required></div>
                                                <div class="mb-2"><label class="form-label">Avatar</label><input type="file" name="avatar" class="form-control form-control-sm" accept="image/*">
                                                @if($t->avatar_url)<img src="{{ $t->avatar_url }}" style="height:40px;border-radius:50%;margin-top:4px;">@endif</div>
                                                <div class="mb-2"><label class="form-label">Rating</label>
                                                    <select name="rating" class="form-select form-select-sm">@for($r=5;$r>=1;$r--)<option value="{{ $r }}" @selected($t->rating==$r)>{{ $r }} Star{{ $r>1?'s':'' }}</option>@endfor</select>
                                                </div>
                                                <div class="mb-2"><label class="form-label">Review</label><textarea name="review" class="form-control form-control-sm" rows="4" required>{{ $t->review }}</textarea></div>
                                                <div><label class="form-label">Status</label>
                                                    <select name="status" class="form-select form-select-sm"><option value="1" @selected($t->status==1)>Active</option><option value="0" @selected($t->status==0)>Inactive</option></select>
                                                </div>
                                            </div>
                                            <div class="modal-footer"><button type="submit" class="btn btn--primary btn--sm"><i class="las la-save"></i> Save</button></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No testimonials yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
