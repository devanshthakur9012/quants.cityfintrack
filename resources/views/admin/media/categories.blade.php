@extends('admin.layouts.app')
@section('panel')

<div class="row">
    {{-- ── ADD / EDIT FORM ── --}}
    <div class="col-lg-4">
        <div class="card b-radius--10">
            <div class="card-header">
                <h5 class="card-title mb-0">Add Category</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.media.categories.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" placeholder="e.g. Events, Team, Products">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Optional short description">{{ old('description') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
                        <small class="text-muted">Lower = shows first on frontend</small>
                    </div>
                    <button type="submit" class="btn btn--primary w-100">
                        <i class="las la-plus"></i> Add Category
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── CATEGORY LIST ── --}}
    <div class="col-lg-8">
        <div class="card b-radius--10">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">All Categories</h5>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Search..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn--primary btn--sm">Go</button>
                    <a href="{{ route('admin.media.categories') }}" class="btn btn--secondary btn--sm">Reset</a>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Sort</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $i => $cat)
                            <tr>
                                <td>{{ $categories->firstItem() + $i }}</td>
                                <td><strong>{{ $cat->name }}</strong></td>
                                <td><small class="text-muted">{{ Str::limit($cat->description, 50) ?: '—' }}</small></td>
                                <td>{{ $cat->sort_order }}</td>
                                <td>
                                    <a href="{{ route('admin.media.index', ['category_id' => $cat->id]) }}"
                                       class="badge badge--primary">{{ $cat->media_items_count }} items</a>
                                </td>
                                <td>
                                    <a href="{{ route('admin.media.categories.toggle', $cat) }}">
                                        @if($cat->is_active)
                                            <span class="badge badge--success">Active</span>
                                        @else
                                            <span class="badge badge--danger">Inactive</span>
                                        @endif
                                    </a>
                                </td>
                                <td>
                                    <div class="button--group">
                                        {{-- Edit inline modal --}}
                                        <button class="btn btn-sm btn--primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editCatModal{{ $cat->id }}"
                                                title="Edit">
                                            <i class="las la-pen"></i>
                                        </button>
                                        <button class="btn btn-sm btn--danger confirmationBtn"
                                                data-action="{{ route('admin.media.categories.destroy', $cat) }}"
                                                data-question="Delete '{{ $cat->name }}' and ALL its media? This cannot be undone.">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            {{-- Edit Modal --}}
                            <div class="modal fade" id="editCatModal{{ $cat->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Category</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="{{ route('admin.media.categories.update', $cat) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Name</label>
                                                    <input type="text" name="name" class="form-control" value="{{ $cat->name }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="description" class="form-control" rows="2">{{ $cat->description }}</textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Sort Order</label>
                                                    <input type="number" name="sort_order" class="form-control" value="{{ $cat->sort_order }}" min="0">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn--primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    No categories yet. Add one on the left.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($categories->hasPages())
            <div class="card-footer">{{ $categories->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection