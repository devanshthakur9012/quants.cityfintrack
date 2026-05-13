{{-- FILE: resources/views/admin/courses/categories/index.blade.php --}}
@extends('admin.layouts.app')

@section('panel')

<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">@lang('Course Categories')</h5>
                <a href="{{ route('admin.courses.categories.create') }}" class="btn btn--primary btn--sm">
                    <i class="las la-plus"></i> Add Category
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive--sm table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>@lang('Category')</th>
                                <th>@lang('Icon')</th>
                                <th>@lang('Courses')</th>
                                <th>@lang('Sort')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $i => $cat)
                            <tr>
                                <td>{{ $categories->firstItem() + $i }}</td>
                                <td>
                                    <strong>{{ $cat->name }}</strong>
                                    @if($cat->description)
                                    <small class="d-block text-muted">{{ Str::limit($cat->description, 60) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($cat->icon)
                                        <i class="fas {{ $cat->icon }}" style="font-size:20px;color:{{ $cat->color ?? '#555' }};"></i>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.courses.index', ['category' => $cat->id]) }}"
                                       class="badge badge--primary">{{ $cat->courses_count }} courses</a>
                                </td>
                                <td>{{ $cat->sort_order }}</td>
                                <td>
                                    <a href="{{ route('admin.courses.categories.status', $cat) }}">
                                        @if($cat->status)
                                            <span class="badge badge--success">Active</span>
                                        @else
                                            <span class="badge badge--danger">Inactive</span>
                                        @endif
                                    </a>
                                </td>
                                <td>
                                    <div class="button--group">
                                        <a href="{{ route('admin.courses.categories.edit', $cat) }}"
                                           class="btn btn-sm btn--primary"><i class="las la-pen"></i></a>
                                        <button class="btn btn-sm btn--danger confirmationBtn"
                                                data-action="{{ route('admin.courses.categories.destroy', $cat) }}"
                                                data-question="Delete this category? This will fail if courses exist under it.">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    No categories yet. <a href="{{ route('admin.courses.categories.create') }}">Create one</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($categories->hasPages())
            <div class="card-footer">{{ $categories->links() }}</div>
            @endif
        </div>
    </div>
</div>

@endsection