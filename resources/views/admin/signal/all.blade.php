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
                            <th>@lang('Name')</th>
                            <th>@lang('Send Time')</th>
                            <th>@lang('Send Status')</th>
                            <th>@lang('Status')</th>
                            <th>@lang('Action')</th>
                        </tr>
                        </thead>
                        <tbody>
                            @forelse($signals as $signal)
                                <tr>
                                    <td>
                                        <span class="fw-bold">{{ strLimit($signal->name, 50) }}</span>
                                    </td>

                                    <td>
                                        @if(!$signal->send_signal_at)
                                            @lang('N/A')
                                        @else
                                            {{ showDateTime($signal->send_signal_at) }}
                                            <br>
                                            {{ diffForHumans($signal->send_signal_at) }}
                                        @endif
                                    </td>

                                    <td>
                                        @php echo $signal->showSendStatus; @endphp
                                    </td>

                                    <td>
                                       @php echo $signal->showStatus; @endphp
                                    </td>

                                    <td>
                                        <div class="d-flex justify-content-end flex-wrap gap-2">
                                            <a href="{{ route('admin.signal.edit', $signal->id) }}"
                                                class="btn btn-sm btn-outline--primary">
                                                <i class="la la-pencil"></i> @lang('Edit')
                                            </a>
                                            <button class="btn btn-sm btn-outline--danger confirmationBtn"
                                                data-question="@lang('Are you sure to delete this signal')?"
                                                data-action="{{ route('admin.signal.delete') }}"
                                                data-hidden_id="{{ $signal->id }}">
                                                <i class="la la-trash"></i> @lang('Delete')
                                            </button>
                                        </div>
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
            @if ($signals->hasPages())
                <div class="card-footer py-4">
                    {{ paginateLinks($signals) }}
                </div>
            @endif
        </div>
    </div>
</div>

<x-confirmation-modal />
@endsection

@if(!request()->routeIs('admin.signal.all'))
    @push('breadcrumb-plugins')
        <a href="{{ route('admin.signal.add.page') }}" class="btn btn-sm btn-outline--primary"><i class="las la-plus"></i>@lang('Add New')</a>
    @endpush
@endif
