@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <form action="#" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label class="form-control-label"> @lang('Telegram Bot Api Token')</label>
                            <input type="text" class="form-control" name="bot_api_token" value="{{ @$general->telegram_config->bot_api_token }}"/>
                        </div>
                        <div class="form-group col-md-6">
                            <label>@lang('BOT Username')</label>
                            <div class="input-group">
                                <span class="input-group-text">http://t.me/</span>
                                <input type="text" name="bot_username" class="form-control" value="{{ @$general->telegram_config->bot_username }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div id="telegramBotModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('Telegram Bot Setup')</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="las la-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive overflow-hidden">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>@lang('To Do')</th>
                                <th>@lang('Description')</th>
                            </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>@lang('Step 1')</td>
                            <td>@lang('Install Telegram App.')</td>
                        </tr>
                        <tr>
                            <td>@lang('Step 2')</td>
                            <td>@lang('Open App and Search for ')<code class="text--primary">@BotFather</code></td>
                        </tr>
                        <tr>
                            <td>@lang('Step 3')</td>
                            <td>@lang('Start Conversion As ')<code class="text--primary">/newbot</code></td>
                        </tr>
                        <tr>
                            <td>@lang('Step 4')</td>
                            <td>@lang('Chose a Bot Name and Press Enter.')</td>
                        </tr>
                        <tr>
                            <td>@lang('Step 5')</td>
                            <td>@lang('Choose a username for your bot. It must end in `bot`. Like this, for example: TetrisBot or tetris_bot')</td>
                        </tr>
                        <tr>
                            <td>@lang('Step 6')</td>
                            <td>@lang('Bot will give you your BOT URL and API Key. Copy This and Paste Bellow.')</td>
                        </tr>
                        <tr>
                            <td>@lang('Step 7')</td>
                            <td>@lang('Write your Bot Description using ') <code class="text--primary">/setdescription</code></td>
                        </tr>
                        <tr>
                            <td>@lang('Step 8')</td>
                            <td>@lang('Set Bot Privacy using ') <code class="text--primary">/setprivacy</code></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
    <button type="button" data-bs-target="#telegramBotModal" data-bs-toggle="modal" class="btn btn-outline--primary mb-2">
        <i class="las la-question"></i>@lang('How To Create Telegram Bot')
    </button>
@endpush
