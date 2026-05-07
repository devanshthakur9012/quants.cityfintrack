@extends($extends)

@section('content')
<section class="pt-100 pb-100 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="custom--card">
                    <div class="card-header card-header-bg d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="card-title mt-0">
                            @php echo $myTicket->statusBadge; @endphp
                            <span class="text-white">[@lang('Ticket')#{{ $myTicket->ticket }}] {{ $myTicket->subject }}</span>
                        </h5>
                        @if($myTicket->status != Status::TICKET_CLOSE && $myTicket->user)
                            <button class="btn btn-danger close-button btn--sm"
                                title="@lang('Close Ticket')" data-bs-toggle="modal" data-bs-target="#closeModal"
                            >
                                <i class="las la-times"></i>
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('ticket.reply', $myTicket->id) }}" enctype="multipart/form-data" class="transparent-form">
                            @csrf
                            <input type="hidden" name="replayTicket" value="1">
                            <div class="row justify-content-between">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <textarea name="message" class="form--control form-control" id="inputMessage" rows="4" cols="10" required></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row justify-content-between">
                                <div class="col-md-9">
                                    <div class="row justify-content-between">
                                        <div class="col-md-11">
                                            <div class="form-group">
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
                                    </div>
                                </div>
                                <div class="col-md-3 pt-2">
                                    <button type="submit" class="btn btn--base custom-success mt-md-4 w-100">
                                        <i class="fa fa-reply"></i> @lang('Reply')
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="row">
                            <div class="col-md-12">
                                @foreach($messages as $message)
                                    @if($message->admin_id == 0)
                                        <div class="row support-answer-wrapper radius-3 my-3 py-3 mx-2 border">
                                            <div class="col-md-3 border-right text-end">
                                                <h5 class="my-3">{{ $message->ticket->name }}</h5>
                                            </div>
                                            <div class="col-md-9 ps-lg-4">
                                                <p class="text-muted font-weight-bold my-3">
                                                    @lang('Posted on') {{ $message->created_at->format('l, dS F Y @ H:i') }}</p>
                                                <p>{{$message->message}}</p>
                                                @if($message->attachments()->count() > 0)
                                                    <div class="mt-2">
                                                        @foreach($message->attachments as $k=> $image)
                                                            <a href="{{route('ticket.download',encrypt($image->id))}}" class="me-3"><i class="fa fa-file"></i>  @lang('Attachment') {{++$k}} </a>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="row support-answer-wrapper support-answer-wrapper-admin my-3 py-3 mx-2 border bg-light">
                                            <div class="col-md-3 border-right text-right">
                                                <h5 class="my-3">{{ $message->admin->name }}</h5>
                                                <p class="lead text-muted">@lang('Staff')</p>
                                            </div>
                                            <div class="col-md-9">
                                                <p class="text-muted font-weight-bold my-3">
                                                    @lang('Posted on') {{ $message->created_at->format('l, dS F Y @ H:i') }}</p>
                                                <p>{{$message->message}}</p>
                                                @if($message->attachments()->count() > 0)
                                                    <div class="mt-2">
                                                        @foreach($message->attachments as $k=> $image)
                                                            <a href="{{route('ticket.download',encrypt($image->id))}}" class="me-3"><i class="fa fa-file"></i>  @lang('Attachment') {{++$k}} </a>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="closeModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('ticket.close', $myTicket->id) }}">
                @csrf
                <input type="hidden" name="replayTicket" value="2">
                <div class="modal-header">
                    <h5 class="modal-title"> @lang('Confirmation')!</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>@lang('Are you sure you want to close this support ticket')?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn--sm" data-bs-dismiss="modal">@lang('Close')</button>
                    <button type="submit" class="btn btn--success btn--sm">@lang("Confirm")</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

            $('.py-2').removeClass('py-2');
            $('.px-3').removeClass('px-3');
        })(jQuery);
    </script>
@endpush

