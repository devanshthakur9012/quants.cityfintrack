{{-- FILE: resources/views/admin/cms/home/learning.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-xl-4 mb-4">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.home.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Add Learning Tab</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.home.learning.store') }}" method="POST">
                    @csrf
                    <div class="mb-2"><label class="form-label required">Tab Label</label><input type="text" name="tab_label" class="form-control form-control-sm" placeholder="Webinars" required></div>
                    <div class="mb-2"><label class="form-label">Highlight Text</label><input type="text" name="highlight_text" class="form-control form-control-sm" placeholder="200Hr of FREE videos"></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control form-control-sm" rows="3"></textarea></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Button Label</label><input type="text" name="btn_label" class="form-control form-control-sm" placeholder="View Now"></div>
                        <div class="col-6"><label class="form-label">Button URL</label><input type="text" name="btn_url" class="form-control form-control-sm" placeholder="#"></div>
                    </div>
                    <div class="mb-2"><label class="form-label">YouTube Video ID <small class="text-muted">(11 chars)</small></label><input type="text" name="video_id" class="form-control form-control-sm" placeholder="VvwjHncyQ88" maxlength="20"></div>
                    <div class="mb-2"><label class="form-label">Video Title</label><input type="text" name="video_title" class="form-control form-control-sm" placeholder="Global Finance to Local Impact"></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">Sub Label</label><input type="text" name="video_sub" class="form-control form-control-sm" placeholder="IN HINDI"></div>
                        <div class="col-3"><label class="form-label">Date</label><input type="text" name="video_date" class="form-control form-control-sm" placeholder="20 MAR 2024"></div>
                        <div class="col-3"><label class="form-label">Time</label><input type="text" name="video_time" class="form-control form-control-sm" placeholder="6:00 PM"></div>
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm w-100"><i class="las la-plus"></i> Add Tab</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-header"><h5 class="card-title mb-0">Learning Tabs ({{ $tabs->count() }})</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead><tr><th>#</th><th>Tab Label</th><th>Highlight</th><th>Video ID</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse($tabs as $i => $tab)
                            <tr>
                                <td>{{ $i+1 }}</td>
                                <td><strong>{{ $tab->tab_label }}</strong></td>
                                <td><small>{{ $tab->highlight_text }}</small></td>
                                <td><small class="text-muted">{{ $tab->video_id ?? '—' }}</small></td>
                                <td>@if($tab->status)<span class="badge badge--success">Active</span>@else<span class="badge badge--warning">Off</span>@endif</td>
                                <td>
                                    <button class="btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#editTab{{ $tab->id }}"><i class="las la-pen"></i></button>
                                    <form action="{{ route('admin.cms.home.learning.destroy', $tab) }}" method="POST" style="display:inline;">@csrf @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Delete?')"><i class="las la-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <div class="modal fade" id="editTab{{ $tab->id }}" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">Edit: {{ $tab->tab_label }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <form action="{{ route('admin.cms.home.learning.update', $tab) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="modal-body">
                                                <div class="row g-2">
                                                    <div class="col-6 mb-2"><label class="form-label">Tab Label</label><input type="text" name="tab_label" class="form-control form-control-sm" value="{{ $tab->tab_label }}" required></div>
                                                    <div class="col-6 mb-2"><label class="form-label">Highlight Text</label><input type="text" name="highlight_text" class="form-control form-control-sm" value="{{ $tab->highlight_text }}"></div>
                                                    <div class="col-12 mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control form-control-sm" rows="3">{{ $tab->description }}</textarea></div>
                                                    <div class="col-6 mb-2"><label class="form-label">Button Label</label><input type="text" name="btn_label" class="form-control form-control-sm" value="{{ $tab->btn_label }}"></div>
                                                    <div class="col-6 mb-2"><label class="form-label">Button URL</label><input type="text" name="btn_url" class="form-control form-control-sm" value="{{ $tab->btn_url }}"></div>
                                                    <div class="col-6 mb-2"><label class="form-label">YouTube Video ID</label><input type="text" name="video_id" class="form-control form-control-sm" value="{{ $tab->video_id }}" maxlength="20"></div>
                                                    <div class="col-6 mb-2"><label class="form-label">Video Title</label><input type="text" name="video_title" class="form-control form-control-sm" value="{{ $tab->video_title }}"></div>
                                                    <div class="col-4 mb-2"><label class="form-label">Sub Label</label><input type="text" name="video_sub" class="form-control form-control-sm" value="{{ $tab->video_sub }}"></div>
                                                    <div class="col-4 mb-2"><label class="form-label">Date</label><input type="text" name="video_date" class="form-control form-control-sm" value="{{ $tab->video_date }}"></div>
                                                    <div class="col-4 mb-2"><label class="form-label">Time</label><input type="text" name="video_time" class="form-control form-control-sm" value="{{ $tab->video_time }}"></div>
                                                    <div class="col-4"><label class="form-label">Status</label>
                                                        <select name="status" class="form-select form-select-sm"><option value="1" @selected($tab->status==1)>Active</option><option value="0" @selected($tab->status==0)>Inactive</option></select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer"><button type="submit" class="btn btn--primary btn--sm"><i class="las la-save"></i> Save</button></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No tabs yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
