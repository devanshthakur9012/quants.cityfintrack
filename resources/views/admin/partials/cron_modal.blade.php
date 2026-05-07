<div id="cronModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLongTitle">@lang('Please Set Cron Job')</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="las la-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <div class="justify-content-between d-flex flex-wrap">
                        <label class="fw-bold">@lang('Cron Command')</label>
                        <small class="fst-italic">
                            @lang('Last Cron Run'): <strong>{{ $general->last_cron ? diffForHumans($general->last_cron) : 'N/A' }}</strong>
                        </small>
                    </div>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-lg" id="cronPath" value="curl -s {{ route('cron') }}" readonly>
                        <button type="button" class="input-group-text copytext btn--primary border-primary copyCronPath"> @lang('Copy')</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between d-flex flex-wrap">
                <p class="fst-italic">
                    @lang('Once per 5-15 minutes is ideal while once every minute is the best option')
                    <u><a href="{{ route('cron.all') }}" type="button" class="text--warning underline">@lang('Run manually')</a></u>
                </p>
                <a href="{{ route('admin.cron.index') }}">Manage Job Setting</a>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
    (function($){
        "use strict";

        $(document).on('click', '.copyCronPath', function(){
            var copyText = document.getElementById('cronPath');

            copyText.select();
            copyText.setSelectionRange(0, 99999);
            
            document.execCommand('copy');
            notify('success', 'Copied: '+copyText.value);
        });
        
    })(jQuery)
</script>
@endpush