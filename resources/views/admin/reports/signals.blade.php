@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10 ">
            <div class="card-body p-0">
                <div class="table-responsive--md  table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                        <tr>
                            <th>@lang('Receiver')</th>
                            <th>@lang('Name')</th>
                            <th>@lang('Send Signal At')</th>
                            <th>@lang('Details')</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($signals as $log)
                        <tr>
                            <td>
                                <span class="fw-bold">{{$log->user->fullname}}</span>
                                <br>
                                <span class="small">
                                <a href="{{ route('admin.users.detail', $log->user_id) }}"><span>@</span>{{ $log->user->username }}</a>
                                </span>
                            </td>
                            <td>
                                {{ strLimit($log->signal->name, 50) }}
                            </td>
                            <td>
                                {{ showDateTime($log->created_at) }} <br> {{ diffForHumans($log->created_at) }}
                            </td>

                            <td>
                                <button class="btn btn-sm btn-outline--primary signalBtn"
                                    data-signal="{{ $log->signal->signal }}"
                                    data-name="{{ $log->signal->name }}"
                                >
                                    <i class="las la-desktop"></i> @lang('Details')
                                </button>
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

<div id="signalModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('Signal Details')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div>
                    <h6 class="name"></h6>
                </div>
                <div class="mt-3">
                    <p class="signal"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--danger btn-sm" data-bs-dismiss="modal">@lang('Close')</button>
            </div>
        </div>
    </div>
</div>
@endsection

@if(!request()->routeIs('admin.users.signal.log'))
    @push('breadcrumb-plugins')
        <x-search-form dateSearch='yes' />
    @endpush
@endif
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

@push('script-lib')
    <script src="{{ asset('assets/admin/js/vendor/datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/datepicker.en.js') }}"></script>
@endpush

@push('script')
  <script>
    (function($){
        "use strict";
        if(!$('.datepicker-here').val()){
            $('.datepicker-here').datepicker();
        }
    })(jQuery)
  </script>
@endpush
