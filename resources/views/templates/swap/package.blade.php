@extends($extends)
@section('content')

@include($activeTemplate.'sections.package')

@if($sections->secs != null)
    @foreach(json_decode($sections->secs) as $sec)
        @include($activeTemplate.'sections.'.$sec)
    @endforeach
@endif
@endsection

