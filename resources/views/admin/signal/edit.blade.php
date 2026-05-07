@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-xxl-12 col-md-12 mb-30">
        <div class="card-body ps-0 pe-0">
            <form action="{{ route('admin.signal.update')}}" method="POST" class="form">
                @csrf
                <input type="hidden" name="id" value="{{ $signal->id }}" required>
                <div class="row">
                    <div class="col-xl-8 mt-xl-0">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title mt-2 justify-content-between d-flex flex-wrap">
                                    <div>
                                        <h6 class="d-inline">@lang('Send Status')</h6> @php echo $signal->showSendStatus; @endphp
                                    </div>
                                    <div>
                                        <h6 class="d-inline">@lang('Send Time')</h6>
                                        @if($signal->send_signal_at)
                                            {{ showDateTime($signal->send_signal_at) }},
                                            <small>({{ diffForHumans($signal->send_signal_at) }})</small>
                                        @else
                                            @lang('N/A')
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-xxl-12">
                                        <div class="form-group">
                                            <label>@lang('Name')</label>
                                            <input type="text" class="form-control" name="name" placeholder="@lang('Name')" required value="{{ $signal->name }}">
                                        </div>
                                    </div>
                                    <div class="col-xxl-12">
                                        <div class="form-group">
                                            <label>@lang('Signal Details')</label>
                                            <textarea name="signal" rows="6" class="form-control" required placeholder="@lang('Signal Details')">{{ $signal->signal }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-md-6">
                                        <div class="form-group">
                                            <label>@lang('Set Time')</label>
                                            <select name="set_time" class="form-control setTime" required>
                                                <option value="">@lang('Select One')</option>
                                                <option value="0" {{ !$signal->minute ? 'selected' : null }}>@lang('Send Now')</option>
                                                <option value="1" {{ $signal->minute ? 'selected' : null }}>@lang('Set Minute')</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-md-6 form-group">
                                        <label>@lang('Send Signal After')</label>
                                        <div class="input-group">
                                            <input type="number" name="minute" id="minute" class="form-control" placeholder="@lang('Minute')" value="{{ $signal->minute }}">
                                            <span class="input-group-text">@lang('Minutes')</span>
                                        </div>
                                    </div>
                                    @if($signal->send)
                                        <div class="col-xxl-3 col-md-6 form-group">
                                            <div class="form-group">
                                                <label>@lang('Signal Resend Now')</label>
                                                <input type="checkbox" data-width="100%" data-size="large" data-onstyle="-success" data-offstyle="-danger" data-bs-toggle="toggle" data-height="50" data-on="@lang('Yes')" data-off="@lang('No')" name="resend" class="line-height-27">
                                            </div>
                                        </div>
                                    @endif
                                    <div class="col-xxl-3 col-md-6 form-group">
                                        <div class="form-group statusArea">
                                            <label>@lang('Status')</label>
                                            <input type="checkbox" data-width="100%" data-size="large" data-onstyle="-success" data-offstyle="-danger" data-bs-toggle="toggle" data-height="50" data-on="@lang('Enable')" data-off="@lang('Disable')" name="status" class="line-height-27 status" @if($signal->status == 1) checked @endif>
                                        </div>
                                    </div>
                                    <div class="col-xxl-12 border-top pt-4">
                                        <div class="row">
                                            <div class="col-xxl-12">
                                                <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 mt-xl-0 mt-3">
                        <div class="card">
                            <div class="card-header justify-content-between d-flex flex-wrap">
                                <h6 class="card-title">@lang('Selected Product For This Signal')</h6>
                                <div>
                                    <button class="btn btn-sm btn-outline--primary checkedPackage" type="button">@lang('Select All')</button>
                                </div>
                            </div>
                            <div class="card-body packages">
                                <ol>
                                    @foreach($packages as $package)
                                        <li>
                                            <input type="checkbox" name="packages[]" class="form--control" value="{{ $package->id }}" id="{{ $package->id }}"
                                            @if(in_array($package->id, $signal->package_id))
                                                checked
                                            @endif
                                            >
                                            <label for="{{ $package->id }}">
                                                {{ __($package->name) }}
                                            </label>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-header justify-content-between d-flex flex-wrap">
                                <h6 class="card-title">@lang('Notification Send Via')</h6>
                                <div>
                                    <button class="btn btn-sm btn-outline--primary checkedVia" type="button">@lang('Select All')</button>
                                </div>
                            </div>
                            <div class="card-body via">
                                <ol>
                                    @foreach($sendVia as $via)
                                        <li>
                                            <input type="checkbox" name="send_via[]" class="form--control" value="{{ strtolower($via) }}" id="{{ $via }}"
                                            @if(in_array(strtolower($via), $signal->send_via))
                                                checked
                                            @endif
                                            >
                                            <label for="{{ $via }}">
                                                {{ __($via) }}
                                            </label>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="confirmationModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('Confirmation Alert!')</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="las la-times"></i>
                </button>
            </div>
            <form action="#" method="POST" class="confirmForm">
                @csrf

                <input type="hidden" name="id">

                <div class="modal-body">
                    <p class="question">@lang('Are you sure to resend this signal')?</p>
                    <p class="description mt-3 fw-bold">
                        @lang('If this signal has already been sent via')
                        ({{ implode(',', $sendVia) }})
                        @lang('we cannot modify that. The system updates it from our panel and resends the signal via ')
                        ({{ implode(',', $sendVia) }})
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('No')</button>
                    <button type="submit" class="btn btn--primary">@lang('Yes')</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
<x-back route="{{ route('admin.signal.all') }}" />
@endpush

@push('script')
<script>
    (function ($) {
        "use strict";

        var submit = null;
        var confirm = false;
        var checkedVia = false;
        var checkedPackage = false;

        $('.checkedPackage').on('click', function(){
            if(checkedPackage){
                checkedPackage = false;
                return $('.packages input:checkbox').prop('checked', false);
            }

            checkedPackage = true;
            return $('.packages input:checkbox').prop('checked', true);
        });

        $('.checkedVia').on('click', function(){
            if(checkedVia){
                checkedVia = false;
                return $('.via input:checkbox').prop('checked', false);
            }

            checkedVia = true;
            return $('.via input:checkbox').prop('checked', true);
        });

        $('.setTime').on('change', function () {
            var selected =  $('.setTime option:selected').val();

            if(selected == 0){
                $('#minute').attr('disabled', 'disabled');
                $('.statusArea').hide();
            }else{
                $('#minute').removeAttr('disabled');
                $('.statusArea').show();
            }
        }).change();

        $('.form').on('submit', function(e){
            var resend = $('input[name="resend"]').prop('checked');

            if(!confirm && resend){
                submit = false;
            }else{
                submit = true;
            }

            if(!submit){
                $('#confirmationModal').modal('show');
                return false;
            }

            return true;
        })

        $('.confirmForm').on('submit', function(e){
            e.preventDefault();
            submit = true;
            confirm = true;
            return $('.form').submit();
        })

    })(jQuery);
</script>
@endpush
