<ul @if($isFirst) class="firstList" @endif>
    @foreach($user->referrals as $under)
        @if($loop->first)
            @php $layer++ @endphp
        @endif
        <li>{{ $under->fullname }} ( {{ $under->username }} )
            @if(($under->referrals->count()) > 0 && ($layer < $maxLevel))
                @include($activeTemplate.'partials.under_tree',['user'=>$under,'layer'=>$layer,'isFirst'=>false])
            @endif
        </li>
    @endforeach
</ul>
