@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-md-12">
            <div class="card-body ps-0 pe-0">
                <form action="{{ route('admin.signal.add')}}" method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ @$signal->id }}" required>
                    <div class="row">
                        <div class="col-xl-8 mt-xl-0">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-xxl-12">
                                            <div class="form-group">
                                                <label>@lang('Name')</label>
                                                <input type="text" class="form-control" name="name" required value="{{ old('name') }}">
                                            </div>
                                        </div>
                                        <div class="col-xxl-12">
                                            <div class="form-group">
                                                <label>@lang('Signal Details')</label>
                                                <textarea name="signal" rows="6" class="form-control" required>{{ old('signal') }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-xxl-4 col-md-6">
                                            <div class="form-group">
                                                <label>@lang('Set Time')</label>
                                                <select name="set_time" class="form-control setTime" required>
                                                    <option value="">@lang('Select One')</option>
                                                    <option value="0">@lang('Send Now')</option>
                                                    <option value="1">@lang('Set Minute')</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-xxl-4 col-md-6 form-group">
                                            <label>@lang('Send Signal After')</label>
                                            <div class="input-group">
                                                <input type="number" name="minute" id="minute" class="form-control" value="{{ old('minute') }}">
                                                <span class="input-group-text">@lang('Minutes')</span>
                                            </div>
                                        </div>
                                        <div class="col-xxl-4 form-group">
                                            <div class="form-group statusArea">
                                                <label>@lang('Status')</label>
                                                <input type="checkbox" data-width="100%" data-size="large" data-onstyle="-success" data-offstyle="-danger" data-bs-toggle="toggle" data-height="50" data-on="@lang('Enable')" data-off="@lang('Disable')" name="status" class="line-height-27 status" checked>
                                            </div>
                                        </div>
                                        <div class="col-xxl-12 mt-3 border-top pt-4">
                                            <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 mt-xl-0 mt-3">
                            <div class="card">
                                <div class="card-header justify-content-between d-flex flex-wrap">
                                    <h6 class="card-title">@lang('Selected Product For This Signal')</h6>
                                    <button class="btn btn-sm btn-outline--primary checkedPackage" type="button">@lang('Select All')</button>
                                </div>
                                <div class="card-body packages">
                                    <ol>
                                        @foreach($packages as $package)
                                            <li>
                                                <input type="checkbox" name="packages[]" class="form--control" value="{{ $package->id }}" id="{{ $package->id }}">
                                                <label for="{{ $package->id }}">{{ __($package->name) }}</label>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            </div>
                            <div class="card mt-3">
                                <div class="card-header justify-content-between d-flex flex-wrap">
                                    <h6 class="card-title">@lang('Notification Send Via')</h6>
                                    <button class="btn btn-sm btn-outline--primary checkedVia" type="button">@lang('Select All')</button>
                                </div>
                                <div class="card-body via">
                                    <ol>
                                        @foreach(sendVia() as $via)
                                            <li>
                                                <input type="checkbox" name="send_via[]" class="form--control" value="{{ strtolower($via) }}" id="{{ $via }}">
                                                <label for="{{ $via }}">{{ __($via) }}</label>
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
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.signal.all') }}" />
@endpush

@push('script')
<script>
    (function ($) {
        "use strict";

        $('.setTime').on('change', function () {
            var selected =  $('.setTime option:selected').val();

            if(selected == 0){
                $('#minute').attr('disabled', 'disabled');
                $('.statusArea').hide();
            }else{
                $('#minute').removeAttr('disabled');
                $('.statusArea').show();
            }
        });

        var checkedPackage = false;
        var checkedVia = false;

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

    })(jQuery);
</script>
@endpush
