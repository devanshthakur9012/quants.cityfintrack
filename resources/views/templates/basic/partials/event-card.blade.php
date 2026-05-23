{{-- FILE: resources/views/themes/{active_theme}/partials/event-card.blade.php --}}
<div class="qev-card {{ $isPast ? 'past' : '' }}"
     data-type="{{ $ev->type }}"
     data-city="{{ $ev->city }}"
     data-title="{{ strtolower($ev->title) }}"
     style="{{ $isPast ? 'filter:none;' : '' }}">

    <div class="qev-card-thumb">
        <img src="{{ $ev->thumbnail_url }}" alt="{{ $ev->title }}" loading="lazy"
             style="{{ $isPast ? 'filter:grayscale(.3)' : '' }}">
        <span class="qev-card-badge {{ $ev->badge }}">{{ ucfirst($ev->badge) }}</span>
        @if(!$isPast && $ev->seats_left !== null)
        <span class="qev-seats-badge {{ $ev->seats_low ? 'low' : '' }}">
            <i class="fas fa-chair"></i> {{ $ev->seats_left }} left
        </span>
        @endif
        <div class="qev-card-price-ov">
            <span class="qev-price-txt">
                @if($ev->type === 'free') FREE
                @else ₹{{ number_format($ev->price) }}/-
                    @if($ev->mrp > $ev->price)
                        <span class="strike">₹{{ number_format($ev->mrp) }}/-</span>
                        @if($ev->discount_label_auto)<span class="disc">{{ $ev->discount_label_auto }}</span>@endif
                    @endif
                @endif
            </span>
            <a href="{{ route('events.detail', $ev->slug) }}" class="qev-view-lnk">
                Details <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>

    <div class="qev-card-body">
        @if($ev->formatted_date !== '—')
        <div class="qev-card-date-strip"
             @if($isPast) style="background:#fce4ec;border-color:#f48fb1;" @endif>
            <i class="fas fa-calendar-alt" @if($isPast) style="color:#c62828;" @endif></i>
            <span @if($isPast) style="color:#c62828;" @endif>
                {{ $ev->formatted_date }}
                @if($ev->formatted_time !== '—') &nbsp;·&nbsp; {{ $ev->formatted_time }} @endif
            </span>
        </div>
        @endif

        <div class="qev-card-title">{{ $ev->title }}</div>

        @if($ev->description)
        <div class="qev-card-desc">{{ Str::limit($ev->description, 90) }}</div>
        @endif

        @if($ev->tags_array)
        <div class="qev-card-tags">
            @foreach(array_slice($ev->tags_array, 0, 3) as $tag)
                <span class="qev-card-tag">{{ $tag }}</span>
            @endforeach
        </div>
        @endif

        <div class="qev-card-meta">
            @if($ev->location)
            <div class="qev-card-meta-row"><i class="fas fa-map-marker-alt"></i><span class="mv">{{ Str::limit($ev->location, 25) }}</span></div>
            @endif
            @if($ev->formatted_duration !== '—')
            <div class="qev-card-meta-row"><i class="fas fa-clock"></i><span class="mv">{{ $ev->formatted_duration }}</span></div>
            @endif
            <div class="qev-card-meta-row">
                <i class="fas fa-users"></i>
                <span class="mv">{{ $ev->total_booked }} registered</span>
            </div>
        </div>
    </div>

    {{-- Countdown (upcoming only) --}}
    @if(!$isPast && $ev->countdown_seconds > 0)
    <div class="qev-countdown" data-ts="{{ time() + $ev->countdown_seconds }}">
        <span class="qev-countdown-label">Starts in</span>
        <div class="qev-countdown-boxes">
            <div class="qev-countdown-unit"><div class="qev-countdown-num cd-days">--</div><div class="qev-countdown-sub">Days</div></div>
            <div class="qev-countdown-unit"><div class="qev-countdown-num cd-hrs">--</div><div class="qev-countdown-sub">Hrs</div></div>
            <div class="qev-countdown-unit"><div class="qev-countdown-num cd-mins">--</div><div class="qev-countdown-sub">Mins</div></div>
        </div>
    </div>
    @endif

    <div class="qev-card-footer">
        <div class="qev-footer-price">
            @if($ev->type === 'free') FREE
            @else ₹{{ number_format($ev->price) }}/-
                @if($ev->mrp > $ev->price)<span class="orig">₹{{ number_format($ev->mrp) }}/-</span>@endif
                @if($ev->discount_label_auto)<span class="pct">{{ $ev->discount_label_auto }}</span>@endif
            @endif
        </div>
        <a href="{{ route('events.detail', $ev->slug) }}"
           class="qev-register-btn {{ $isPast ? 'past' : '' }}">
            {{ $isPast ? 'View Details' : 'Register' }} <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</div>
