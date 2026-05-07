@extends('admin.layouts.app')
<style>
    .note-toolbar .note-insert {
     display: none !important;
     }
 </style>
@section('panel')
  <!-- include summernote css/js -->
  
     
            <div>
                <form action="{{ route('admin.portfolio-insights.strategy.create')}}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-xl-12 mt-xl-0">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-xxl-12">
                                            <div class="form-group">
                                                <label>@lang('Strategy Name')</label>
                                                <input type="text" class="form-control" name="strategy_name" required >
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label>@lang('legs')</label>
                                                <input type="text" class="form-control" name="legs" required>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label>@lang('Risk')</label>
                                                <input type="text" class="form-control" name="risk" required >
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label>@lang('Prof.')</label>
                                                <input type="text" class="form-control" name="profit" required>
                                            </div>
                                        </div>

                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
                                            
                                            <div class="form-group">
                                                <label>@lang('Image.')</label><label class="" for="strategy_image"><code>Img type must be : JPG, JPEG & PNG</code></label><br>
                                                <input type="file" class="form-control" name="strategy_image" required accept="image/*">
                                            </div>
                                        </div>

                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label>@lang('Type')</label>
                                                <select name="market_trend" class="form-control" id="" required>
                                                    <option value="Bullish">@lang('Bullish')</option>
                                                    <option value="Bearish">@lang('Bearish')</option>
                                                    <option value="Volatile">@lang('Volatile')</option>
                                                    <option value="Oscillate">@lang('Oscillate')</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label>@lang('Status')</label>
                                                <select name="strategy_status" class="form-control" id="" required>
                                                    <option value="Enable">@lang('Enable')</option>
                                                    <option value="Disable">@lang('Disable')</option>
                                                   
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                           
                                            <div class="form-group">
                                                <label>@lang('Description')</label>
                                                <textarea class="form-control" name="description" id="summernote" required></textarea>
                                            </div>

                                        </div>
                                        <div class="col-xxl-12 mt-3 border-top pt-4">
                                            <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
  
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.portfolio-insights.strategy.all') }}" />
    
@endpush

@push('script')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.js"></script>
<script>
    $(document).ready(function() {
  $('#summernote').summernote({ height: 250});
});
</script>
@endpush


