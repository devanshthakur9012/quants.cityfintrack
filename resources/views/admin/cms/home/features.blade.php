{{-- FILE: resources/views/admin/cms/home/features.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">

    {{-- LEFT: Meta + Add New --}}
    <div class="col-xl-4 mb-4">

        {{-- Section title --}}
        <div class="card b-radius--10 mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.home.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Section Title</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.home.features.meta') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <input type="text" name="section_title" class="form-control"
                               value="{{ old('section_title', $featureMeta->section_title ?? 'CityQuants App Feature Tools') }}">
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm w-100"><i class="las la-save"></i> Save Title</button>
                </form>
            </div>
        </div>

        {{-- Add utility --}}
        <div class="card b-radius--10">
            <div class="card-header"><h5 class="card-title mb-0">Add Utility Tab</h5></div>
            <div class="card-body">
                <form action="{{ route('admin.cms.home.utility.store') }}" method="POST">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label">Count</label>
                            <input type="text" name="count" class="form-control form-control-sm" placeholder="14" required>
                        </div>
                        <div class="col-8">
                            <label class="form-label">Label</label>
                            <input type="text" name="label" class="form-control form-control-sm" placeholder="Intraday" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Tool Title <small class="text-muted">(shown in panel)</small></label>
                        <input type="text" name="tool_title" class="form-control form-control-sm" placeholder="Intraday" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Icon <small class="text-muted">(fa-...)</small></label>
                        <input type="text" name="tool_icon" class="form-control form-control-sm" placeholder="fa-bolt">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bullet Points <small class="text-muted">(one per line)</small></label>
                        <textarea name="tool_points" class="form-control form-control-sm" rows="3"
                                  placeholder="Track intraday OI changes...&#10;Identify intraday trends..."></textarea>
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm w-100"><i class="las la-plus"></i> Add</button>
                </form>
            </div>
        </div>
    </div>

    {{-- RIGHT: Existing --}}
    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-header"><h5 class="card-title mb-0">Utility Tabs ({{ $utilities->count() }})</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead><tr><th>#</th><th>Count / Label</th><th>Tool Title</th><th>Icon</th><th>Points</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse($utilities as $i => $u)
                            <tr>
                                <td>{{ $i+1 }}</td>
                                <td><strong>{{ $u->count }}</strong> {{ $u->label }}</td>
                                <td>{{ $u->tool_title }}</td>
                                <td><i class="fas {{ $u->tool_icon }}"></i> <small class="text-muted">{{ $u->tool_icon }}</small></td>
                                <td><small class="text-muted">{{ count($u->tool_points ?? []) }} points</small></td>
                                <td>
                                    @if($u->status) <span class="badge badge--success">Active</span>
                                    @else <span class="badge badge--warning">Inactive</span>@endif
                                </td>
                                <td>
                                    <button class="btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#editUtil{{ $u->id }}"><i class="las la-pen"></i></button>
                                    <form action="{{ route('admin.cms.home.utility.destroy', $u) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Delete?')"><i class="las la-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            {{-- Edit modal --}}
                            <div class="modal fade" id="editUtil{{ $u->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">Edit: {{ $u->label }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <form action="{{ route('admin.cms.home.utility.update', $u) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="modal-body">
                                                <div class="row g-2 mb-2">
                                                    <div class="col-4"><label class="form-label">Count</label><input type="text" name="count" class="form-control form-control-sm" value="{{ $u->count }}" required></div>
                                                    <div class="col-8"><label class="form-label">Label</label><input type="text" name="label" class="form-control form-control-sm" value="{{ $u->label }}" required></div>
                                                </div>
                                                <div class="mb-2"><label class="form-label">Tool Title</label><input type="text" name="tool_title" class="form-control form-control-sm" value="{{ $u->tool_title }}" required></div>
                                                <div class="mb-2"><label class="form-label">Icon (fa-...)</label><input type="text" name="tool_icon" class="form-control form-control-sm" value="{{ $u->tool_icon }}"></div>
                                                <div class="mb-2">
                                                    <label class="form-label">Bullet Points (one per line)</label>
                                                    <textarea name="tool_points" class="form-control form-control-sm" rows="4">{{ implode("\n", $u->tool_points ?? []) }}</textarea>
                                                </div>
                                                <div><label class="form-label">Status</label>
                                                    <select name="status" class="form-select form-select-sm">
                                                        <option value="1" @selected($u->status==1)>Active</option>
                                                        <option value="0" @selected($u->status==0)>Inactive</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer"><button type="submit" class="btn btn--primary btn--sm"><i class="las la-save"></i> Save</button></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No utilities yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
