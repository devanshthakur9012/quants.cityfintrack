@extends($activeTemplate.'layouts.master')
@section('content')

<section class="pt-100 pb-100 bg-light">
    <div class="container">
        <div class="row justify-content-center mt-4">
            <div class="col-md-10">
                <div class="custom--card">
                    <div class="card-header">
                        <h5 class="card-title text-center">{{ __($pageTitle) }}</h5>
                    </div>
                    <div class="card-body">
                        <form class="transparent-form" action="{{route('ticket.store')}}"  method="post" enctype="multipart/form-data" onsubmit="return submitUserForm();">
                            @csrf
                            <div class="row">
                                <div class="form-group col-md-6 mb-3">
                                    <label for="name" class="form-label">@lang('Name')</label>
                                    <input type="text" name="name" value="{{@$user->firstname . ' '.@$user->lastname}}" class="form--control form-control" readonly>
                                </div>
                                <div class="form-group col-md-6 mb-3">
                                    <label for="email" class="form-label">@lang('Email address')</label>
                                    <input type="email"  name="email" value="{{@$user->email}}" class="form--control form-control"readonly>
                                </div>

                                <div class="form-group col-md-6 mb-3">
                                    <label for="website" class="form-label">@lang('Subject')</label>
                                    <input type="text" name="subject" value="{{old('subject')}}" class="form--control form-control" required>
                                </div>
                                <div class="form-group col-md-6 mb-3">
                                    <label for="priority" class="form-label">@lang('Priority')</label>
                                    <select name="priority" class="form--control form-control form-select">
                                        <option value="3">@lang('High')</option>
                                        <option value="2">@lang('Medium')</option>
                                        <option value="1">@lang('Low')</option>
                                    </select>
                                </div>
                                <div class="col-12 form-group mb-3">
                                    <label for="inputMessage" class="form-label">@lang('Message')</label>
                                    <textarea name="message" id="inputMessage" rows="6" class="form--control form-control" required>{{old('message')}}</textarea>
                                </div>
                            </div>

                            <div class="row form-group mb-3">
                                <div class="col-sm-12 file-upload">
                                    <label for="inputAttachments" class="form-label">
                                        @lang('Attachments')</label> <small class="text--danger">@lang('Max 5 files can be uploaded'). @lang('Maximum upload size is') {{ ini_get('upload_max_filesize') }}</small>
                                    </label>

                                    <div class="input-group">
                                        <input type="file" name="attachments[]" id="inputAttachments" class="form-control form--control rounded"/>
                                        <button class="btn--success btn--sm btn addFile ms-2 rounded" type="button"><i class="las la-plus"></i></button>
                                    </div>

                                    <div id="fileUploadsContainer"></div>
                                    <label class="form-lebel small">
                                        @lang('Allowed File Extensions'): .@lang('jpg'), .@lang('jpeg'), .@lang('png'), .@lang('pdf'), .@lang('doc'), .@lang('docx')
                                    </label>
                                </div>

                            </div>

                            <div class="row form-group justify-content-center">
                                <div class="col-md-12">
                                    <button class="btn btn--base w-100 text-center" type="submit" id="recaptcha">@lang('Submit')</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('style')
    <style>
        .input-group-text:focus{
            box-shadow: none !important;
        }
    </style>
@endpush

@push('script')
    <script>
        (function ($) {
            "use strict";
            var fileAdded = 0;
            $('.addFile').on('click',function(){
                if (fileAdded >= 4) {
                    notify('error','You\'ve added maximum number of file');
                    return false;
                }
                fileAdded++;
                $("#fileUploadsContainer").append(`
                    <div class="input-group my-3">
                        <input type="file" name="attachments[]" class="form-control form--control rounded" required />
                        <button type="button" class="btn--danger btn--sm btn remove-btn ms-2 rounded"><i class="las la-times"></i></button>
                    </div>
                `)
            });
            $(document).on('click','.remove-btn',function(){
                fileAdded--;
                $(this).closest('.input-group').remove();
            });
        })(jQuery);
    </script>
@endpush
