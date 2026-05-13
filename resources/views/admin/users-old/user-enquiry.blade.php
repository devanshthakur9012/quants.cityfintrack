@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10 ">
            <div class="card-body p-0">
                <div class="table-responsive--lg">
                    <table class="table table--light style--two">
                        <thead>
                        <tr>
                            <th>@lang('SNO.')</th>
                            <th>@lang('User Code')</th>
                            <th>@lang('Product')</th>
                            <th>@lang('Name')</th>
                            <th>@lang('Email')</th>
                            <th>@lang('Phone')</th>
                        </tr>
                        </thead>
                        <tbody>
                            @forelse($enquiry as $item)
                                <tr>
                                    <td>
                                        <strong>{{++$loop->index}}</strong>
                                    </td>
                                    <td>
                                        {{$item->user->user_code}}
                                    </td>
                                    <td>
                                        {{$item->package->name}}
                                    </td>
                                    <td>
                                        <strong>{{ $item->name }}</strong>
                                    </td>
                                    <td>
                                        <strong>{{ $item->email }}</strong>
                                    </td>
                                    <td>
                                        {{ $item->phone }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table><!-- table end -->
                </div>
            </div>
            @if ($enquiry->hasPages())
                <div class="card-footer py-4">
                    {{ paginateLinks($enquiry) }}
                </div>
            @endif
        </div>
    </div>
</div>

<x-confirmation-modal />

<!-- Model pop with the form to upload an xls file -->
<div class="modal fade" id="uploadXlsModal" tabindex="-1" role="dialog" aria-labelledby="uploadXlsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadXlsModalLabel">@lang('Upload XLS File')</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.transaction.upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="xlsFile">@lang('Select XLS File')</label>
                        <input type="file" class="form-control" id="xlsFile" name="xlsFile" accept=".xlsx, .xls" required>
                    </div>
                    <button type="submit" class="btn btn-primary">@lang('Upload')</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@if(!request()->routeIs('admin.transaction'))
    @push('breadcrumb-plugins')
        {{-- <a href="{{ route('admin.financial-overview.ledger.add.page') }}" class="btn btn-sm btn-outline--primary"><i class="las la-plus"></i>@lang('Add New')</a> --}}
        <a href="{{ route('admin.transaction.download.template') }}" class="btn btn-sm btn-outline--primary"><i class="las la-download"></i>@lang('Download Excel Template')</a>
        <button class="btn btn-sm btn-outline--primary" data-bs-toggle="modal" data-bs-target="#uploadXlsModal"><i class="las la-upload"></i>@lang('Upload via XLS')</button>
    @endpush
@endif
