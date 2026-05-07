@extends($activeTemplate.'layouts.master')
@section('content')
<div class="pt-100 pb-100 bg-light">
    <div class="container">
        <div class="row mb-3 justify-content-end">
            <div class="col-lg-5">
                <form action="#" class="transparent-form">
                    <div class="input-group">
                        <input type="text" name="search" class="form--control form-control" value="{{ request()->search }}" placeholder="@lang('Search by signal name')">
                        <button class="input-group-text bg--base border-0 text-white" type="submit">
                            <i class="las la-search me-1"></i> @lang('Search')
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <table class="table table--responsive--lg">
            <thead>
                <tr>
                    <th>@lang('Send Signal At')</th>
                    <th>@lang('Name')</th>
                    <th>@lang('Details')</th>
                </tr>
            </thead>
            <tbody>
                @forelse($signals as $signal)
                    <tr>
                        <td>
                            <div>
                                {{ showDateTime($signal->created_at) }}<br>{{ diffForHumans($signal->created_at) }}
                            </div>
                        </td>
                        <td>
                            <div>
                                {{ strLimit($signal->signal->name, 60) }}
                            </div>
                        </td>
                        <td>
                            <button class="badge badge--icon badge--fill-base signalBtn"
                                data-signal="{{ $signal->signal->signal }}"
                                data-name="{{ $signal->signal->name }}"
                            >
                                <i class="fa fa-desktop"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="pt-50">
            {{ paginateLinks($signals) }}
        </div>
    </div>
</div>

<div id="signalModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('Signal Details')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="nameArea">
                    <span class="fw-bold me-2">@lang('Name'):</span>
                    <span class="name"></span>
                </div>
                <div class="signalArea mt-4">
                    <p class="signal"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark btn--sm" data-bs-dismiss="modal">@lang('Close')</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
    <script>
        (function ($) {
            "use strict";

            $('.signalBtn').on('click', function() {
                var modal = $('#signalModal');
                modal.find('.name').text($(this).data('name'));
                modal.find('.signal').text($(this).data('signal'));
                modal.modal('show');
            });

        })(jQuery);
    </script>
@endpush


